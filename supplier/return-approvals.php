<?php require_once('header.php'); ?>

<?php
// Strict Server-Side Authorization: Admin, Manager, or Supervisor Only
if (!is_supplier_approver()) {
    header("Location: pos.php");
    exit;
}

$supplier_id = (int)$_SESSION['supplier_user']['supplier_id'];
$current_uid = (int)$_SESSION['supplier_user']['id'];
$user_role_raw = isset($_SESSION['supplier_user']['role']) ? $_SESSION['supplier_user']['role'] : 'USER';
$user_role = normalize_supplier_role($user_role_raw);
$is_admin = is_admin_or_manager_role($user_role);
$is_supervisor = is_supervisor_role($user_role);

ensure_return_schema($pdo);

// Filter by Status & Search
$filter_status = isset($_GET['status']) ? trim($_GET['status']) : 'ALL';
$search_query = isset($_GET['q']) ? trim($_GET['q']) : '';

$where_clauses = ["r.supplier_id = ?"];
$params = [$supplier_id];

if ($filter_status === 'PENDING') {
    $where_clauses[] = "r.status IN ('PENDING_APPROVAL', 'Pending')";
} elseif ($filter_status === 'APPROVED') {
    $where_clauses[] = "r.status IN ('COMPLETED', 'APPROVED', 'REFUNDED')";
} elseif ($filter_status === 'REJECTED') {
    $where_clauses[] = "r.status = 'REJECTED'";
}

if (!empty($search_query)) {
    $where_clauses[] = "(r.return_reference ILIKE ? OR r.payment_id ILIKE ? OR r.customer_name ILIKE ? OR r.requested_by_name ILIKE ? OR ri.product_name ILIKE ? OR ri.sku ILIKE ?)";
    $q_like = '%' . $search_query . '%';
    $params[] = $q_like;
    $params[] = $q_like;
    $params[] = $q_like;
    $params[] = $q_like;
    $params[] = $q_like;
    $params[] = $q_like;
}

$where_sql = implode(' AND ', $where_clauses);
$requests = [];

try {
    $sql = "SELECT r.*, 
                   ri.return_item_id, ri.order_item_id, ri.product_id, ri.product_name, ri.sku, ri.size, ri.color, 
                   ri.item_type, ri.special_order_reference, ri.product_details, ri.quantity_returned, 
                   ri.unit_price as item_unit_price, ri.refund_amount as item_refund, ri.return_reason as item_reason, 
                   ri.condition as item_condition, ri.restock_status as item_restock_status, ri.notes as item_notes
            FROM tbl_returns r
            LEFT JOIN tbl_return_items ri ON r.return_id = ri.return_id
            WHERE {$where_sql}
            ORDER BY r.return_id DESC LIMIT 250";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $requests = [];
}

// Counts for Tabs and Badges
$counts = ['total_all' => 0, 'total_pending' => 0, 'total_approved' => 0, 'total_rejected' => 0, 'total_refund_value' => 0];
try {
    $stmt_cnt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_all,
            COUNT(CASE WHEN status IN ('PENDING_APPROVAL', 'Pending') THEN 1 END) as total_pending,
            COUNT(CASE WHEN status IN ('COMPLETED', 'APPROVED', 'REFUNDED') THEN 1 END) as total_approved,
            COUNT(CASE WHEN status = 'REJECTED' THEN 1 END) as total_rejected,
            COALESCE(SUM(CASE WHEN status IN ('COMPLETED', 'APPROVED', 'REFUNDED') THEN refund_amount ELSE 0 END), 0) as total_refund_value
        FROM tbl_returns 
        WHERE supplier_id = ?
    ");
    $stmt_cnt->execute(array($supplier_id));
    $db_counts = $stmt_cnt->fetch(PDO::FETCH_ASSOC);
    if ($db_counts) {
        $counts = $db_counts;
    }
} catch (Exception $e) {}

// Fetch Supplier Info for Receipts
$supp_info = ['supplier_name' => 'eConstruction Supply', 'supplier_address' => '', 'supplier_phone' => ''];
try {
    $stmt_s = $pdo->prepare("SELECT supplier_name, supplier_address, supplier_phone FROM tbl_supplier WHERE supplier_id=?");
    $stmt_s->execute(array($supplier_id));
    $supp_db = $stmt_s->fetch(PDO::FETCH_ASSOC);
    if ($supp_db) $supp_info = $supp_db;
} catch (Exception $e) {}
?>

<section class="content-header">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap;">
        <h1>
            <i class="fa fa-check-circle" style="color: #f43f5e;"></i> POS Return Approvals & Audit Queue
            <small>Review, approve, or reject cashier return and refund requests</small>
        </h1>
        <div style="display: flex; gap: 6px; flex-wrap: wrap;">
            <a href="returns.php" class="btn btn-default btn-sm"><i class="fa fa-undo"></i> Return History</a>
            <a href="discount-approvals.php" class="btn btn-default btn-sm"><i class="fa fa-check-square-o"></i> Discount Approvals</a>
            <a href="pos.php" class="btn btn-primary btn-sm"><i class="fa fa-calculator"></i> Open POS Terminal</a>
        </div>
    </div>
</section>

<section class="content">

    <!-- Flash Message / Alert Component -->
    <div id="pageAlertContainer">
        <?php if (!empty($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible" style="border-radius: 6px; font-weight: 500; margin-bottom: 15px; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">
                <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                <i class="icon fa fa-check"></i> <?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-dismissible" style="border-radius: 6px; font-weight: 500; margin-bottom: 15px; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">
                <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                <i class="icon fa fa-ban"></i> <?php echo htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($_SESSION['warning_message'])): ?>
            <div class="alert alert-warning alert-dismissible" style="border-radius: 6px; font-weight: 500; margin-bottom: 15px; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">
                <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                <i class="icon fa fa-exclamation-triangle"></i> <?php echo htmlspecialchars($_SESSION['warning_message']); unset($_SESSION['warning_message']); ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($_SESSION['info_message'])): ?>
            <div class="alert alert-info alert-dismissible" style="border-radius: 6px; font-weight: 500; margin-bottom: 15px; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">
                <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                <i class="icon fa fa-info-circle"></i> <?php echo htmlspecialchars($_SESSION['info_message']); unset($_SESSION['info_message']); ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- KPI Metric Summary Cards -->
    <div class="row">
        <div class="col-md-3 col-sm-6 col-xs-12">
            <div class="info-box" style="border-radius: 8px; box-shadow: 0 2px 6px rgba(0,0,0,0.06); border-left: 4px solid #f59e0b;">
                <span class="info-box-icon bg-yellow" style="border-radius: 8px 0 0 8px; background-color: #f59e0b !important;"><i class="fa fa-clock-o"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text" style="font-weight: 700; color: #64748b;">Pending Approval</span>
                    <span class="info-box-number" style="font-size: 22px; font-weight: 800; color: #d97706;"><?php echo (int)$counts['total_pending']; ?></span>
                    <span class="text-muted" style="font-size: 11px;">Awaiting Manager Review</span>
                </div>
            </div>
        </div>

        <div class="col-md-3 col-sm-6 col-xs-12">
            <div class="info-box" style="border-radius: 8px; box-shadow: 0 2px 6px rgba(0,0,0,0.06); border-left: 4px solid #10b981;">
                <span class="info-box-icon bg-green" style="border-radius: 8px 0 0 8px; background-color: #10b981 !important;"><i class="fa fa-check"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text" style="font-weight: 700; color: #64748b;">Approved & Settled</span>
                    <span class="info-box-number" style="font-size: 22px; font-weight: 800; color: #059669;"><?php echo (int)$counts['total_approved']; ?></span>
                    <span class="text-muted" style="font-size: 11px;">Completed refunds</span>
                </div>
            </div>
        </div>

        <div class="col-md-3 col-sm-6 col-xs-12">
            <div class="info-box" style="border-radius: 8px; box-shadow: 0 2px 6px rgba(0,0,0,0.06); border-left: 4px solid #ef4444;">
                <span class="info-box-icon bg-red" style="border-radius: 8px 0 0 8px; background-color: #ef4444 !important;"><i class="fa fa-times"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text" style="font-weight: 700; color: #64748b;">Rejected Requests</span>
                    <span class="info-box-number" style="font-size: 22px; font-weight: 800; color: #dc2626;"><?php echo (int)$counts['total_rejected']; ?></span>
                    <span class="text-muted" style="font-size: 11px;">Declined by approver</span>
                </div>
            </div>
        </div>

        <div class="col-md-3 col-sm-6 col-xs-12">
            <div class="info-box" style="border-radius: 8px; box-shadow: 0 2px 6px rgba(0,0,0,0.06); border-left: 4px solid #6366f1;">
                <span class="info-box-icon bg-purple" style="border-radius: 8px 0 0 8px; background-color: #6366f1 !important;"><i class="fa fa-money"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text" style="font-weight: 700; color: #64748b;">Total Authorized Refund</span>
                    <span class="info-box-number" style="font-size: 20px; font-weight: 800; color: #4338ca;">&#8369;<?php echo number_format($counts['total_refund_value'], 2); ?></span>
                    <span class="text-muted" style="font-size: 11px;">All approved returns</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter Navigation Tabs and Main Card -->
    <div class="row">
        <div class="col-md-12">

            <div class="nav-tabs-custom" style="box-shadow: 0 2px 10px rgba(0,0,0,0.06); border-radius: 8px;">
                <ul class="nav nav-tabs" style="font-weight: bold; border-radius: 8px 8px 0 0; background: #f8fafc;">
                    <li class="<?php echo ($filter_status === 'ALL' && empty($search_query)) ? 'active' : ''; ?>">
                        <a href="return-approvals.php">
                            All Requests <span class="badge" style="background-color: #64748b;"><?php echo (int)$counts['total_all']; ?></span>
                        </a>
                    </li>
                    <li class="<?php echo ($filter_status === 'PENDING') ? 'active' : ''; ?>">
                        <a href="return-approvals.php?status=PENDING" style="color: #d97706;">
                            <i class="fa fa-clock-o"></i> Pending Approval 
                            <span class="badge" style="background-color: #f59e0b;"><?php echo (int)$counts['total_pending']; ?></span>
                        </a>
                    </li>
                    <li class="<?php echo ($filter_status === 'APPROVED') ? 'active' : ''; ?>">
                        <a href="return-approvals.php?status=APPROVED" style="color: #059669;">
                            <i class="fa fa-check-circle"></i> Approved &amp; Completed 
                            <span class="badge" style="background-color: #10b981;"><?php echo (int)$counts['total_approved']; ?></span>
                        </a>
                    </li>
                    <li class="<?php echo ($filter_status === 'REJECTED') ? 'active' : ''; ?>">
                        <a href="return-approvals.php?status=REJECTED" style="color: #dc2626;">
                            <i class="fa fa-times-circle"></i> Rejected 
                            <span class="badge" style="background-color: #ef4444;"><?php echo (int)$counts['total_rejected']; ?></span>
                        </a>
                    </li>
                </ul>

                <div class="tab-content" style="padding: 20px;">
                    
                    <!-- Search Bar -->
                    <form method="GET" action="return-approvals.php" class="form-inline" style="margin-bottom: 20px; display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                        <input type="hidden" name="status" value="<?php echo htmlspecialchars($filter_status); ?>">
                        <div class="form-group">
                            <div class="input-group">
                                <input type="text" name="q" class="form-control input-sm" style="width: 320px;" placeholder="Search Return Ref, Invoice, Customer, Requester, Item..." value="<?php echo htmlspecialchars($search_query); ?>">
                                <span class="input-group-btn">
                                    <button type="submit" class="btn btn-primary btn-sm"><i class="fa fa-search"></i> Search</button>
                                </span>
                            </div>
                        </div>
                        <?php if (!empty($search_query)): ?>
                            <a href="return-approvals.php?status=<?php echo htmlspecialchars($filter_status); ?>" class="btn btn-default btn-sm"><i class="fa fa-times"></i> Clear Search</a>
                        <?php endif; ?>
                    </form>

                    <!-- Requests Table -->
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover" style="font-size: 12.5px;">
                            <thead>
                                <tr style="background: #f1f5f9; color: #1e293b;">
                                    <th style="width: 155px;">Return Info</th>
                                    <th style="width: 150px;">Original Order</th>
                                    <th>Item &amp; Return Details</th>
                                    <th style="width: 140px;">Condition &amp; Reason</th>
                                    <th style="width: 120px; text-align: right;">Refund Amount</th>
                                    <th style="width: 120px; text-align: center;">Status</th>
                                    <th style="width: 160px;">Audit / Approver</th>
                                    <th style="width: 160px; text-align: center;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($requests)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center text-muted" style="padding: 40px 10px;">
                                            <i class="fa fa-undo fa-3x" style="color: #cbd5e1;"></i>
                                            <p style="margin-top: 10px; font-size: 14px;">No return requests found matching the current filter.</p>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($requests as $r): 
                                        $is_pending = in_array($r['status'], ['PENDING_APPROVAL', 'Pending']);
                                        $is_completed = in_array($r['status'], ['COMPLETED', 'APPROVED', 'REFUNDED']);
                                        $is_rejected = ($r['status'] === 'REJECTED');
                                        
                                        $req_role = !empty($r['requested_by_role']) ? $r['requested_by_role'] : 'CASHIER';
                                        $req_role_label = ucfirst(strtolower($req_role));
                                        $req_badge_cls = in_array($req_role, ['ADMIN', 'MANAGER']) ? 'label-primary' : (($req_role === 'SUPERVISOR') ? 'label-warning' : 'label-info');
                                        
                                        $is_self = ((int)$r['requested_by_id'] === $current_uid);
                                        $can_approve = can_user_approve_return($user_role, $current_uid, $r['requested_by_id']);
                                        
                                        $is_special = ($r['item_type'] === 'SPECIAL_ORDER' || (int)$r['product_id'] === 0);
                                        $is_restocked = ($r['item_restock_status'] === 'RESTOCKED');
                                    ?>
                                    <tr id="row_<?php echo $r['return_id']; ?>" style="<?php echo $is_pending ? 'background-color: #fffbeb;' : ''; ?>">
                                        
                                        <!-- Return Info -->
                                        <td>
                                            <strong style="font-family: monospace; color: #dc2626; font-size: 13px;"><?php echo htmlspecialchars($r['return_reference']); ?></strong>
                                            <div style="font-size: 11px; color: #64748b; margin-top: 2px;">
                                                <i class="fa fa-calendar"></i> <?php echo date('M d, Y h:i A', strtotime($r['return_date'])); ?>
                                            </div>
                                            <div style="font-size: 11px; color: #334155; margin-top: 3px;">
                                                <i class="fa fa-user"></i> Requester: <strong><?php echo htmlspecialchars($r['requested_by_name'] ?: 'Cashier'); ?></strong><br>
                                                <span class="label <?php echo $req_badge_cls; ?>" style="font-size: 9.5px; padding: 1px 5px;"><?php echo $req_role_label; ?></span>
                                            </div>
                                        </td>

                                        <!-- Original Order -->
                                        <td>
                                            <strong style="font-family: monospace; color: #0284c7; font-size: 12.5px;"><?php echo htmlspecialchars($r['payment_id']); ?></strong>
                                            <div style="font-size: 11.5px; font-weight: 700; color: #0f172a; margin-top: 2px;">
                                                <i class="fa fa-user-circle"></i> <?php echo htmlspecialchars($r['customer_name'] ?: 'Walk-in Customer'); ?>
                                            </div>
                                            <?php if (!empty($r['customer_phone'])): ?>
                                                <div style="font-size: 10.5px; color: #64748b;"><i class="fa fa-phone"></i> <?php echo htmlspecialchars($r['customer_phone']); ?></div>
                                            <?php endif; ?>
                                        </td>

                                        <!-- Item & Return Details -->
                                        <td>
                                            <?php if ($is_special): ?>
                                                <span class="label label-warning" style="background-color: #d97706; font-size: 9px; padding: 1px 4px; font-weight: bold;">SPECIAL ORDER</span><br>
                                            <?php endif; ?>
                                            <strong style="color: #0f172a; font-size: 13px;"><?php echo htmlspecialchars($r['product_name']); ?></strong>
                                            <?php if ($is_special && !empty($r['product_details'])): ?>
                                                <div style="font-size: 11px; color: #475569;"><?php echo htmlspecialchars($r['product_details']); ?></div>
                                            <?php endif; ?>
                                            <div style="font-size: 11px; color: #64748b; font-family: monospace; margin-top: 2px;">
                                                <?php echo !empty($r['sku']) ? 'SKU: ' . htmlspecialchars($r['sku']) . ' | ' : ''; ?>
                                                Qty: <strong style="color: #dc2626; font-size: 12px;"><?php echo (int)$r['quantity_returned']; ?> unit(s)</strong> @ &#8369;<?php echo number_format($r['item_unit_price'], 2); ?>
                                            </div>
                                            <?php if (!empty($r['notes'])): ?>
                                                <div style="font-size: 11px; color: #0369a1; background: #e0f2fe; padding: 2px 6px; border-radius: 4px; margin-top: 4px; display: inline-block;">
                                                    <i class="fa fa-comment-o"></i> Cashier Notes: "<?php echo htmlspecialchars($r['notes']); ?>"
                                                </div>
                                            <?php endif; ?>
                                        </td>

                                        <!-- Condition & Reason -->
                                        <td>
                                            <?php
                                            $cond = $r['item_condition'];
                                            $badge_class = 'label-default';
                                            if ($cond === 'Good / Resalable' || $cond === 'Resellable' || $cond === 'Unopened') $badge_class = 'label-success';
                                            elseif ($cond === 'Damaged' || $cond === 'Defective') $badge_class = 'label-danger';
                                            elseif ($cond === 'Opened' || $cond === 'Used' || $cond === 'Needs Inspection') $badge_class = 'label-warning';
                                            ?>
                                            <span class="label <?php echo $badge_class; ?>" style="font-size: 10.5px; padding: 2px 5px; display: inline-block; margin-bottom: 3px;">
                                                <?php echo htmlspecialchars($cond); ?>
                                            </span>
                                            <div style="font-size: 11px; color: #334155;">
                                                Reason: <strong><?php echo htmlspecialchars($r['item_reason']); ?></strong>
                                            </div>
                                            <?php if ($is_completed): ?>
                                                <div style="margin-top: 3px;">
                                                    <?php if ($is_restocked): ?>
                                                        <span class="label label-success" style="font-size: 9.5px; background: #16a34a;"><i class="fa fa-check"></i> Stock Restocked (+<?php echo (int)$r['quantity_returned']; ?>)</span>
                                                    <?php else: ?>
                                                        <span class="label label-default" style="font-size: 9.5px; background: #94a3b8;"><i class="fa fa-ban"></i> Stock Not Restocked</span>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>

                                        <!-- Refund Amount -->
                                        <td style="text-align: right;">
                                            <div style="font-size: 15px; font-weight: 900; color: #dc2626;">
                                                &#8369;<?php echo number_format($r['refund_amount'], 2); ?>
                                            </div>
                                            <div style="font-size: 10.5px; color: #64748b; font-weight: 600;">
                                                <?php echo htmlspecialchars($r['refund_method']); ?>
                                            </div>
                                        </td>

                                        <!-- Status Badge -->
                                        <td style="text-align: center;">
                                            <?php if ($is_pending): ?>
                                                <span class="label label-warning" style="font-size: 10px; padding: 3px 6px; background-color: #f59e0b;"><i class="fa fa-clock-o"></i> PENDING APPROVAL</span>
                                            <?php elseif ($is_completed): ?>
                                                <span class="label label-success" style="font-size: 10px; padding: 3px 6px; background-color: #10b981;"><i class="fa fa-check-circle"></i> COMPLETED</span>
                                            <?php elseif ($is_rejected): ?>
                                                <span class="label label-danger" style="font-size: 10px; padding: 3px 6px; background-color: #ef4444;"><i class="fa fa-times-circle"></i> REJECTED</span>
                                            <?php else: ?>
                                                <span class="label label-default" style="font-size: 10px; padding: 3px 6px;"><?php echo htmlspecialchars($r['status']); ?></span>
                                            <?php endif; ?>
                                        </td>

                                        <!-- Audit Details -->
                                        <td>
                                            <?php if (!empty($r['approver_name'])): ?>
                                                <div style="font-size: 11.5px; color: #1e293b;">
                                                    <i class="fa fa-shield text-primary"></i> <strong><?php echo htmlspecialchars($r['approver_name']); ?></strong>
                                                    <?php if (!empty($r['approver_role'])): ?>
                                                        <span class="label label-default" style="font-size: 9px; padding: 1px 4px;"><?php echo ucfirst(strtolower($r['approver_role'])); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                                <div style="font-size: 10.5px; color: #64748b;">
                                                    <?php echo !empty($r['approved_at']) ? date('M d, Y h:i A', strtotime($r['approved_at'])) : (!empty($r['rejected_at']) ? date('M d, Y h:i A', strtotime($r['rejected_at'])) : ''); ?>
                                                </div>
                                            <?php endif; ?>
                                            <?php if (!empty($r['approver_remarks']) && !$is_rejected): ?>
                                                <div style="font-size: 11px; color: #475569; background: #f1f5f9; padding: 2px 6px; border-radius: 4px; margin-top: 3px;">
                                                    <em>"<?php echo htmlspecialchars($r['approver_remarks']); ?>"</em>
                                                </div>
                                            <?php endif; ?>
                                            <?php if (!empty($r['rejection_reason'])): ?>
                                                <div style="font-size: 11px; color: #991b1b; background: #fee2e2; padding: 2px 6px; border-radius: 4px; margin-top: 3px;">
                                                    <strong>Reason:</strong> <?php echo htmlspecialchars($r['rejection_reason']); ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>

                                        <!-- Action Buttons -->
                                        <td style="text-align: center;">
                                            <?php if ($is_pending): ?>
                                                <?php if ($can_approve): ?>
                                                    <div class="btn-group-vertical btn-group-xs" style="width: 100%;">
                                                        <button type="button" class="btn btn-success btn-xs" style="margin-bottom: 3px; font-weight: 700; padding: 4px 6px;" onclick="handleDirectApprove(<?php echo $r['return_id']; ?>, '<?php echo htmlspecialchars(addslashes($r['return_reference'])); ?>', '<?php echo number_format($r['refund_amount'], 2); ?>', '<?php echo htmlspecialchars(addslashes($r['product_name'])); ?>')">
                                                            <i class="fa fa-check"></i> Approve (₱<?php echo number_format($r['refund_amount'], 2); ?>)
                                                        </button>
                                                        <button type="button" class="btn btn-info btn-xs" style="margin-bottom: 3px; font-weight: 700; padding: 3px 6px;" onclick="openReviewModal(<?php echo $r['return_id']; ?>)">
                                                            <i class="fa fa-eye"></i> Review &amp; Decide
                                                        </button>
                                                        <button type="button" class="btn btn-danger btn-xs" style="font-weight: 700; padding: 3px 6px;" onclick="openRejectModal(<?php echo $r['return_id']; ?>, '<?php echo htmlspecialchars(addslashes($r['return_reference'])); ?>', '<?php echo htmlspecialchars(addslashes($r['product_name'])); ?>')">
                                                            <i class="fa fa-times"></i> Reject
                                                        </button>
                                                    </div>
                                                <?php else: ?>
                                                    <?php if ($is_self): ?>
                                                        <span class="label label-danger" style="display: inline-block; font-size: 10.5px; padding: 4px 6px; white-space: normal; line-height: 1.3;">
                                                            <i class="fa fa-ban"></i> Self-Request<br><small style="font-size: 9.5px;">Cannot self-approve</small>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="label label-warning" style="display: inline-block; background-color: #d97706; font-size: 10.5px; padding: 4px 6px; white-space: normal; line-height: 1.3;">
                                                            <i class="fa fa-lock"></i> Requires Approver
                                                        </span>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            <?php elseif ($is_completed): ?>
                                                <button type="button" class="btn btn-default btn-xs" style="font-weight: 700; color: #dc2626; border-color: #cbd5e1;" onclick="fetchAndPrintReceipt(<?php echo $r['return_id']; ?>)" title="Print Return / Refund Slip">
                                                    <i class="fa fa-print"></i> Print Slip
                                                </button>
                                            <?php else: ?>
                                                <span class="text-muted" style="font-size: 11px;"><i class="fa fa-lock"></i> Rejected</span>
                                            <?php endif; ?>
                                        </td>

                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                </div>
            </div>

        </div>
    </div>
</section>

<!-- Modal: Review Return Request -->
<div class="modal fade" id="modalReviewReturn" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-md" role="document">
        <div class="modal-content" style="border-radius: 8px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.3);">
            <div class="modal-header" style="background: #0f172a; color: #fff; padding: 14px 18px;">
                <button type="button" class="close" data-dismiss="modal" style="color: #fff; opacity: 0.9;">&times;</button>
                <h4 class="modal-title" style="font-weight: bold; font-size: 16px;"><i class="fa fa-search"></i> Review Return Request</h4>
            </div>
            <div class="modal-body" id="revModalBody" style="padding: 18px;">
                <div class="text-center text-muted" style="padding: 30px;">
                    <i class="fa fa-spinner fa-spin fa-2x"></i>
                    <p style="margin-top: 8px;">Loading return details...</p>
                </div>
            </div>
            <div class="modal-footer" id="revModalFooter" style="background: #f8fafc; display: flex; justify-content: space-between; align-items: center;">
                <button type="button" class="btn btn-default btn-sm" data-dismiss="modal">Close</button>
                <div id="revModalActionBtns"></div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Reject Return Request -->
<div class="modal fade" id="modalRejectReturn" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-sm" role="document">
        <div class="modal-content" style="border-radius: 8px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.3);">
            <div class="modal-header" style="background: #dc2626; color: #fff; padding: 14px 18px;">
                <button type="button" class="close" data-dismiss="modal" style="color: #fff; opacity: 0.9;">&times;</button>
                <h4 class="modal-title" style="font-weight: bold; font-size: 15px;"><i class="fa fa-times-circle"></i> Reject Return Request</h4>
            </div>
            <div class="modal-body" style="padding: 18px;">
                <div id="rejectModalAlertArea"></div>
                <input type="hidden" id="rejReturnId">
                <p id="rejRefDisplay" style="font-weight: bold; color: #1e293b; margin-bottom: 4px; font-family: monospace;"></p>
                <p id="rejProdDisplay" style="font-size: 12.5px; color: #64748b; margin-bottom: 12px;"></p>
                
                <div class="form-group">
                    <label style="font-size: 12px; font-weight: 700; color: #1e293b;">Mandatory Rejection Reason: *</label>
                    <textarea id="rejReasonInput" class="form-control" rows="3" placeholder="e.g. Item condition not acceptable, exceeds 7-day return window, or receipt mismatch."></textarea>
                </div>
            </div>
            <div class="modal-footer" style="background: #f8fafc;">
                <button type="button" class="btn btn-default btn-sm" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger btn-sm" onclick="submitRejectReturn()" style="font-weight: bold;"><i class="fa fa-ban"></i> Confirm Rejection</button>
            </div>
        </div>
    </div>
</div>

<!-- Return Slip Modal (for instant print receipt popup) -->
<div class="modal fade" id="modalReturnSlipPrint" tabindex="-1" role="dialog" style="z-index: 10070;">
    <div class="modal-dialog" role="document" style="max-width: 550px;">
        <div class="modal-content" style="border-radius: 8px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.3);">
            <div class="modal-header" style="background-color: #dc2626; color: #fff; padding: 14px 18px;">
                <button type="button" class="close" data-dismiss="modal" style="color: #fff; opacity: 0.9;">&times;</button>
                <h4 class="modal-title" style="font-weight: bold; font-size: 16px;">
                    <i class="fa fa-file-text-o"></i> Official Return &amp; Refund Receipt
                </h4>
            </div>
            <div class="modal-body" id="returnSlipContentArea" style="padding: 20px; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; color: #333;">
                <!-- Dynamically generated -->
            </div>
            <div class="modal-footer" style="background: #f8fafc; border-top: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
                <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                <button type="button" class="btn btn-danger" onclick="executeReturnSlipPrint()"><i class="fa fa-print"></i> Print Slip</button>
            </div>
        </div>
    </div>
</div>

<script>
const supplierStoreName = "<?php echo addslashes($supp_info['supplier_name'] ?: 'E-Construction Supply'); ?>";
const supplierStoreAddress = "<?php echo addslashes($supp_info['supplier_address'] ?: ''); ?>";
const supplierStorePhone = "<?php echo addslashes($supp_info['supplier_phone'] ?: ''); ?>";

// -------------------------------------------------------------
// Flash Message & Alert Component Helpers
// -------------------------------------------------------------
function showPageAlert(message, type = 'success', autoDismiss = true) {
    const iconClass = type === 'success' ? 'fa-check' : (type === 'danger' ? 'fa-ban' : (type === 'warning' ? 'fa-exclamation-triangle' : 'fa-info-circle'));
    const alertHtml = `
        <div class="alert alert-${type} alert-dismissible" style="border-radius: 6px; font-weight: 500; margin-bottom: 15px; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">
            <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
            <i class="icon fa ${iconClass}"></i> ${escapeHtml(message)}
        </div>
    `;
    $('#pageAlertContainer').html(alertHtml).show();
    if ($('#pageAlertContainer').length && $('#pageAlertContainer').offset().top < $(window).scrollTop()) {
        $('html, body').animate({ scrollTop: $('#pageAlertContainer').offset().top - 20 }, 300);
    }
    if (autoDismiss) {
        setTimeout(function() {
            $('#pageAlertContainer .alert').fadeOut(500, function() {
                $(this).remove();
            });
        }, 5000);
    }
}

function showModalAlert(containerSelector, message, type = 'danger') {
    const iconClass = type === 'success' ? 'fa-check' : (type === 'danger' ? 'fa-ban' : (type === 'warning' ? 'fa-exclamation-triangle' : 'fa-info-circle'));
    const alertHtml = `
        <div class="alert alert-${type} alert-dismissible" style="border-radius: 6px; font-weight: 500; margin-bottom: 12px; padding: 8px 12px; font-size: 12px;">
            <button type="button" class="close" data-dismiss="alert" aria-hidden="true" style="right: -4px; top: -2px;">&times;</button>
            <i class="icon fa ${iconClass}"></i> ${escapeHtml(message)}
        </div>
    `;
    $(containerSelector).html(alertHtml).show();
}

$(document).ready(function() {
    const flashMsg = sessionStorage.getItem('flash_message');
    const flashType = sessionStorage.getItem('flash_type') || 'success';
    if (flashMsg) {
        sessionStorage.removeItem('flash_message');
        sessionStorage.removeItem('flash_type');
        showPageAlert(flashMsg, flashType);
    }
});

function handleDirectApprove(returnId, returnRef, refundAmt, prodName) {
    if (confirm(`Authorize return and refund of ₱${refundAmt} for ${prodName} (${returnRef})?`)) {
        const remarks = prompt('Optional Approver Remarks:', '') || '';
        $.post('pos-return-api.php', {
            action: 'approve_return',
            return_id: returnId,
            approver_remarks: remarks
        }, function(res) {
            if (res.status === 'success') {
                if (res.receipt) {
                    showPageAlert(res.message || 'Return request approved successfully.', 'success');
                    renderAndShowReturnSlip(res.receipt);
                } else {
                    sessionStorage.setItem('flash_message', res.message || 'Return request approved successfully.');
                    sessionStorage.setItem('flash_type', 'success');
                    location.reload();
                }
            } else {
                showPageAlert(res.message || 'Failed to approve return request.', 'danger');
            }
        }, 'json').fail(function() {
            showPageAlert('Server error while processing return approval.', 'danger');
        });
    }
}

function openReviewModal(returnId) {
    $('#revModalBody').html('<div class="text-center text-muted" style="padding: 30px;"><i class="fa fa-spinner fa-spin fa-2x"></i><p style="margin-top: 8px;">Loading details...</p></div>');
    $('#revModalActionBtns').html('');
    $('#modalReviewReturn').modal('show');

    $.get('pos-return-api.php', {
        action: 'get_return_details',
        return_id: returnId
    }, function(res) {
        if (res.status === 'success') {
            const ret = res.return;
            const items = res.items || [];
            const isSelf = res.is_self_request;
            const canApprove = !!res.can_approve;

            let itemsHtml = '';
            items.forEach((it, idx) => {
                const cond = it.condition || 'Resellable';
                const isRestockable = ['Good / Resalable', 'Resellable', 'Unopened'].includes(cond) && parseInt(it.product_id) > 0;
                itemsHtml += `
                    <tr style="border-bottom: 1px solid #e2e8f0;">
                        <td>
                            <strong>${escapeHtml(it.product_name)}</strong>
                            ${it.product_details ? `<div style="font-size: 11px; color: #64748b;">${escapeHtml(it.product_details)}</div>` : ''}
                            <div style="font-size: 11px; font-family: monospace; color: #64748b;">${it.sku ? 'SKU: ' + escapeHtml(it.sku) : ''}</div>
                        </td>
                        <td style="text-align: center; font-weight: bold; color: #dc2626;">${parseInt(it.quantity_returned)}</td>
                        <td style="text-align: right;">₱${parseFloat(it.unit_price).toFixed(2)}</td>
                        <td style="text-align: right; font-weight: bold;">₱${parseFloat(it.refund_amount).toFixed(2)}</td>
                        <td>
                            <span class="label ${isRestockable ? 'label-success' : 'label-danger'}" style="font-size: 10px;">${escapeHtml(cond)}</span><br>
                            <small style="color: #64748b;">${isRestockable ? '✓ Will Restock Stock' : '✗ Stock Non-sellable'}</small>
                        </td>
                    </tr>
                `;
            });

            const html = `
                <div id="reviewModalAlertArea"></div>
                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 12px; margin-bottom: 14px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                        <strong style="font-family: monospace; color: #dc2626; font-size: 14px;">${escapeHtml(ret.return_reference)}</strong>
                        <span class="label label-warning" style="font-size: 10px;">${escapeHtml(ret.status)}</span>
                    </div>
                    <div style="font-size: 12px; color: #334155;">
                        <div><strong>Original Sale / Invoice:</strong> ${escapeHtml(ret.payment_id)}</div>
                        <div><strong>Customer:</strong> ${escapeHtml(ret.customer_name || 'Walk-in Customer')} ${ret.customer_phone ? '(' + escapeHtml(ret.customer_phone) + ')' : ''}</div>
                        <div><strong>Requested By:</strong> ${escapeHtml(ret.requested_by_name || 'Cashier')} (${escapeHtml(ret.requested_by_role || 'Staff')}) on ${new Date(ret.return_date).toLocaleString()}</div>
                        ${ret.notes ? `<div style="margin-top: 4px; color: #0369a1;"><strong>Cashier Remarks:</strong> "${escapeHtml(ret.notes)}"</div>` : ''}
                    </div>
                </div>

                <div class="table-responsive" style="margin-bottom: 14px;">
                    <table class="table table-bordered table-condensed" style="font-size: 12px; margin-bottom: 0;">
                        <thead>
                            <tr style="background: #f1f5f9;">
                                <th>Item Description</th>
                                <th style="text-align: center; width: 60px;">Qty</th>
                                <th style="text-align: right; width: 85px;">Price</th>
                                <th style="text-align: right; width: 95px;">Refund</th>
                                <th style="width: 130px;">Condition &amp; Stock</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${itemsHtml}
                        </tbody>
                    </table>
                </div>

                <div style="background: #eff6ff; border: 1.5px solid #93c5fd; border-radius: 6px; padding: 10px 14px; margin-bottom: 14px; display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <span style="font-size: 11px; font-weight: bold; color: #1e40af; text-transform: uppercase;">Refund Amount Due:</span>
                        <div style="font-size: 11px; color: #64748b;">Method: <strong>${escapeHtml(ret.refund_method)}</strong></div>
                    </div>
                    <div style="font-size: 22px; font-weight: 900; color: #1d4ed8;">₱${parseFloat(ret.refund_amount).toFixed(2)}</div>
                </div>

                ${!canApprove && isSelf ? `
                    <div class="alert alert-danger" style="margin-bottom: 0; font-size: 12px;">
                        <i class="fa fa-ban"></i> <strong>Self-Approval Restriction:</strong> You created this return request. Company audit policy requires an Administrator to review and authorize it.
                    </div>
                ` : `
                    ${isSelf ? `
                        <div class="alert alert-info" style="margin-bottom: 10px; font-size: 11.5px; padding: 8px 12px;">
                            <i class="fa fa-info-circle"></i> <strong>Admin Self-Approval:</strong> As a Supplier Administrator, you are authorized to self-approve your return request.
                        </div>
                    ` : ''}
                    <div class="form-group" style="margin-bottom: 0;">
                        <label style="font-size: 12px; font-weight: bold; color: #334155;">Approver Remarks (Optional):</label>
                        <textarea id="revRemarksInput" class="form-control" rows="2" placeholder="e.g. Return authorized. Item inspected and in resalable condition."></textarea>
                    </div>
                `}
            `;

            $('#revModalBody').html(html);

            if (canApprove) {
                $('#revModalActionBtns').html(`
                    <button type="button" class="btn btn-danger btn-sm" onclick="openRejectModal(${ret.return_id}, '${escapeHtml(addslashes(ret.return_reference))}', '${escapeHtml(addslashes(items[0]?.product_name || 'Item'))}')" style="font-weight: bold;"><i class="fa fa-times"></i> Reject</button>
                    <button type="button" class="btn btn-success btn-sm" onclick="submitReviewApprove(${ret.return_id})" style="font-weight: bold;"><i class="fa fa-check"></i> Authorize &amp; Approve Return</button>
                `);
            }
        } else {
            $('#revModalBody').html('<div class="alert alert-danger">' + escapeHtml(res.message) + '</div>');
        }
    }, 'json');
}

function submitReviewApprove(returnId) {
    const remarks = $('#revRemarksInput').val() ? $('#revRemarksInput').val().trim() : '';
    $.post('pos-return-api.php', {
        action: 'approve_return',
        return_id: returnId,
        approver_remarks: remarks
    }, function(res) {
        if (res.status === 'success') {
            $('#modalReviewReturn').modal('hide');
            if (res.receipt) {
                showPageAlert(res.message || 'Return request approved successfully.', 'success');
                renderAndShowReturnSlip(res.receipt);
            } else {
                sessionStorage.setItem('flash_message', res.message || 'Return request approved successfully.');
                sessionStorage.setItem('flash_type', 'success');
                location.reload();
            }
        } else {
            showModalAlert('#reviewModalAlertArea', res.message || 'Failed to approve return request.', 'danger');
            showPageAlert(res.message || 'Failed to approve return request.', 'danger');
        }
    }, 'json').fail(function() {
        showModalAlert('#reviewModalAlertArea', 'Server error while processing return approval.', 'danger');
    });
}

function openRejectModal(returnId, returnRef, prodName) {
    $('#rejectModalAlertArea').html('');
    $('#rejReturnId').val(returnId);
    $('#rejRefDisplay').text('Return Ref: ' + returnRef);
    $('#rejProdDisplay').text('Item: ' + prodName);
    $('#rejReasonInput').val('');
    $('#modalRejectReturn').modal('show');
}

function submitRejectReturn() {
    const returnId = $('#rejReturnId').val();
    const reason = $('#rejReasonInput').val().trim();

    if (!reason) {
        showModalAlert('#rejectModalAlertArea', 'Please enter a valid rejection reason.', 'danger');
        return;
    }

    $.post('pos-return-api.php', {
        action: 'reject_return',
        return_id: returnId,
        rejection_reason: reason
    }, function(res) {
        if (res.status === 'success') {
            $('#modalRejectReturn').modal('hide');
            sessionStorage.setItem('flash_message', res.message || 'Return request rejected.');
            sessionStorage.setItem('flash_type', 'warning');
            location.reload();
        } else {
            showModalAlert('#rejectModalAlertArea', res.message || 'Failed to reject return request.', 'danger');
        }
    }, 'json').fail(function() {
        showModalAlert('#rejectModalAlertArea', 'Server error while rejecting return request.', 'danger');
    });
}

function fetchAndPrintReceipt(returnId) {
    $.get('pos-return-api.php', {
        action: 'get_return_receipt',
        return_id: returnId
    }, function(res) {
        if (res.status === 'success' && res.receipt) {
            renderAndShowReturnSlip(res.receipt);
        } else {
            showPageAlert(res.message || 'Failed to load return receipt.', 'danger');
        }
    }, 'json').fail(function() {
        showPageAlert('Server error while loading return receipt.', 'danger');
    });
}

function renderAndShowReturnSlip(data) {
    const isSpecial = (data.is_special_order || parseInt(data.product_id) === 0);
    const isRestocked = (data.restock_status === 'RESTOCKED');
    
    let specialTag = isSpecial ? '<span style="background: #d97706; color: #fff; font-size: 9px; font-weight: bold; padding: 1px 4px; border-radius: 3px; text-transform: uppercase;">SPECIAL ORDER</span><br>' : '';
    let detailsHtml = (isSpecial && data.product_details) ? `<div style="font-size: 11px; color: #475569; margin-top: 2px;">${escapeHtml(data.product_details)}</div>` : '';
    let refOrSku = isSpecial 
        ? (data.special_order_reference ? `Ref: ${escapeHtml(data.special_order_reference)}` : '')
        : (data.sku ? `SKU: ${escapeHtml(data.sku)}` : '');

    let html = `
        <div style="text-align: center; border-bottom: 2px solid #dc2626; padding-bottom: 12px; margin-bottom: 14px;">
            <h3 style="margin: 0; color: #1e3a8a; font-weight: bold; letter-spacing: 0.5px;">${escapeHtml(data.supplier_name || supplierStoreName)}</h3>
            ${data.supplier_address ? `<p style="margin: 2px 0 0 0; font-size: 12px; color: #64748b;">${escapeHtml(data.supplier_address)}</p>` : ''}
            ${data.supplier_phone ? `<p style="margin: 2px 0 0 0; font-size: 12px; color: #64748b;">Tel: ${escapeHtml(data.supplier_phone)}</p>` : ''}
            <div style="display: inline-block; margin-top: 8px; background-color: #dc2626; color: #fff; font-weight: bold; font-size: 11.5px; padding: 3px 12px; border-radius: 4px; text-transform: uppercase; letter-spacing: 0.5px;">
                OFFICIAL RETURN &amp; REFUND VOUCHER
            </div>
        </div>

        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 10px 14px; margin-bottom: 14px; font-size: 12.5px;">
            <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                <span style="color: #64748b;">Return Reference:</span>
                <strong style="font-family: monospace; color: #dc2626; font-size: 13.5px;">${escapeHtml(data.return_reference)}</strong>
            </div>
            <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                <span style="color: #64748b;">Return Date:</span>
                <strong>${new Date(data.return_date).toLocaleString()}</strong>
            </div>
            <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                <span style="color: #64748b;">Original Invoice / POS Ref:</span>
                <strong style="font-family: monospace; color: #0284c7;">${escapeHtml(data.payment_id)}</strong>
            </div>
            <div style="display: flex; justify-content: space-between;">
                <span style="color: #64748b;">Customer Name:</span>
                <strong>${escapeHtml(data.customer_name || 'Walk-in Customer')}</strong>
            </div>
        </div>

        <table style="width: 100%; border-collapse: collapse; margin-bottom: 14px; font-size: 12.5px;">
            <thead>
                <tr style="background: #f1f5f9; border-top: 1px solid #cbd5e1; border-bottom: 1px solid #cbd5e1;">
                    <th style="text-align: left; padding: 6px 8px;">Returned Item Description</th>
                    <th style="text-align: center; padding: 6px 8px; width: 60px;">Qty</th>
                    <th style="text-align: right; padding: 6px 8px; width: 85px;">Unit Price</th>
                    <th style="text-align: right; padding: 6px 8px; width: 95px;">Total</th>
                </tr>
            </thead>
            <tbody>
                <tr style="border-bottom: 1px solid #e2e8f0;">
                    <td style="padding: 8px 8px;">
                        ${specialTag}
                        <strong>${escapeHtml(data.product_name)}</strong>
                        ${detailsHtml}
                        <div style="font-size: 11px; color: #64748b; font-family: monospace;">${refOrSku}</div>
                        <div style="font-size: 11px; color: #475569; margin-top: 2px;">
                            Reason: <em>"${escapeHtml(data.return_reason)}"</em>
                        </div>
                    </td>
                    <td style="text-align: center; padding: 8px 8px; font-weight: bold; color: #dc2626;">${parseInt(data.quantity_returned)}</td>
                    <td style="text-align: right; padding: 8px 8px;">₱${parseFloat(data.unit_price).toFixed(2)}</td>
                    <td style="text-align: right; padding: 8px 8px; font-weight: bold; color: #dc2626;">₱${parseFloat(data.refund_amount).toFixed(2)}</td>
                </tr>
            </tbody>
        </table>

        <div style="background: #fef2f2; border: 1.5px solid #fecaca; border-radius: 6px; padding: 10px 14px; margin-bottom: 14px; font-size: 12.5px;">
            <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                <span style="color: #991b1b; font-weight: 700;">TOTAL REFUND AMOUNT:</span>
                <strong style="font-size: 17px; color: #dc2626;">₱${parseFloat(data.refund_amount).toFixed(2)}</strong>
            </div>
            <div style="display: flex; justify-content: space-between; font-size: 12px; color: #475569;">
                <span>Refund Method:</span>
                <strong>${escapeHtml(data.refund_method)}</strong>
            </div>
            <div style="display: flex; justify-content: space-between; font-size: 12px; color: #475569; margin-top: 2px;">
                <span>Item Condition:</span>
                <span class="label ${isRestocked ? 'label-success' : 'label-default'}" style="font-size: 10px;">${escapeHtml(data.condition)} (${isRestocked ? 'Restocked' : 'Non-Restocked'})</span>
            </div>
        </div>

        <div style="font-size: 11px; color: #64748b; border-top: 1px dashed #cbd5e1; padding-top: 8px; display: flex; justify-content: space-between; flex-wrap: wrap;">
            <div>Requested: <strong>${escapeHtml(data.requested_by || 'Cashier')}</strong></div>
            <div>Authorized: <strong>${escapeHtml(data.approved_by || 'Manager')}</strong></div>
        </div>

        <div style="text-align: center; margin-top: 15px; font-size: 11px; color: #94a3b8;">
            eConstruction Supply POS System &bull; Official Refund Record
        </div>
    `;

    $('#returnSlipContentArea').html(html);
    $('#modalReturnSlipPrint').modal('show');
}

function executeReturnSlipPrint() {
    const printArea = document.getElementById('returnSlipContentArea');
    if (!printArea) return;
    
    const printWindow = window.open('', '_blank', 'width=850,height=900');
    if (!printWindow) {
        showPageAlert('Print popup was blocked by browser. Please allow popups.', 'warning');
        return;
    }
    
    const html = `
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <title>Official Return Receipt</title>
            <style>
                * { box-sizing: border-box; }
                body { font-family: 'Courier New', Courier, monospace, sans-serif; color: #000; margin: 0; padding: 20px; background: #fff; font-size: 13px; line-height: 1.5; }
                table { width: 100%; border-collapse: collapse; }
                th, td { vertical-align: top; padding: 6px 8px; }
                @media print {
                    body { padding: 0 !important; }
                }
            </style>
        </head>
        <body>
            <div style="max-width: 500mm; margin: 0 auto;">
                ${printArea.innerHTML}
            </div>
        </body>
        </html>
    `;
    
    printWindow.document.open();
    printWindow.document.write(html);
    printWindow.document.close();
    printWindow.focus();
    setTimeout(() => {
        printWindow.print();
        printWindow.close();
    }, 450);
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.innerText = text;
    return div.innerHTML;
}
</script>

<?php require_once('footer.php'); ?>
