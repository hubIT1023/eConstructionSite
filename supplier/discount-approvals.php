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

// Filter by Status
$filter_status = isset($_GET['status']) ? trim($_GET['status']) : 'ALL';
$search_query = isset($_GET['q']) ? trim($_GET['q']) : '';

$where_clauses = ["supplier_id = ?"];
$params = [$supplier_id];

if ($filter_status === 'PENDING_ESCALATED') {
    $where_clauses[] = "status IN ('PENDING', 'ESCALATED')";
} elseif ($filter_status === 'APPROVED') {
    $where_clauses[] = "status IN ('APPROVED', 'APPROVED_MODIFIED')";
} elseif ($filter_status === 'REJECTED') {
    $where_clauses[] = "status = 'REJECTED'";
}

if (!empty($search_query)) {
    $where_clauses[] = "(request_id ILIKE ? OR product_name ILIKE ? OR cashier_name ILIKE ? OR sku ILIKE ? OR requester_role ILIKE ?)";
    $q_like = '%' . $search_query . '%';
    $params[] = $q_like;
    $params[] = $q_like;
    $params[] = $q_like;
    $params[] = $q_like;
    $params[] = $q_like;
}

ensure_supplier_user_schema($pdo);

$where_sql = implode(' AND ', $where_clauses);
$requests = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM tbl_discount_requests WHERE $where_sql ORDER BY id DESC LIMIT 200");
    $stmt->execute($params);
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $requests = [];
}

// Counts for Badges
$counts = ['total_all' => 0, 'total_pending' => 0, 'total_approved' => 0, 'total_rejected' => 0];
try {
    $stmt_cnt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_all,
            COUNT(CASE WHEN status IN ('PENDING', 'ESCALATED') THEN 1 END) as total_pending,
            COUNT(CASE WHEN status IN ('APPROVED', 'APPROVED_MODIFIED') THEN 1 END) as total_approved,
            COUNT(CASE WHEN status = 'REJECTED' THEN 1 END) as total_rejected
        FROM tbl_discount_requests 
        WHERE supplier_id = ?
    ");
    $stmt_cnt->execute(array($supplier_id));
    $db_counts = $stmt_cnt->fetch(PDO::FETCH_ASSOC);
    if ($db_counts) {
        $counts = $db_counts;
    }
} catch (Exception $e) {}
?>

<section class="content-header">
    <div class="content-header-left">
        <h1><i class="fa fa-check-square-o text-purple" style="color: #9333ea;"></i> POS Discount Approval Queue & Audit</h1>
    </div>
    <div class="content-header-right">
        <?php if ($is_admin): ?>
            <a href="discount-settings.php" class="btn btn-default btn-sm"><i class="fa fa-sliders"></i> Discount Policy Settings</a>
        <?php endif; ?>
        <a href="pos.php" class="btn btn-primary btn-sm"><i class="fa fa-calculator"></i> Open POS Terminal</a>
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

    <div class="row">
        <div class="col-md-12">

            <!-- Filter Navigation Tabs -->
            <div class="nav-tabs-custom" style="box-shadow: 0 2px 10px rgba(0,0,0,0.06); border-radius: 8px;">
                <ul class="nav nav-tabs" style="font-weight: bold; border-radius: 8px 8px 0 0; background: #f8fafc;">
                    <li class="<?php echo ($filter_status === 'ALL' && empty($search_query)) ? 'active' : ''; ?>">
                        <a href="discount-approvals.php">
                            All Requests <span class="badge" style="background-color: #64748b;"><?php echo (int)$counts['total_all']; ?></span>
                        </a>
                    </li>
                    <li class="<?php echo ($filter_status === 'PENDING_ESCALATED') ? 'active' : ''; ?>">
                        <a href="discount-approvals.php?status=PENDING_ESCALATED" style="color: #d97706;">
                            <i class="fa fa-clock-o"></i> Pending & Escalated 
                            <span class="badge" style="background-color: #f59e0b;"><?php echo (int)$counts['total_pending']; ?></span>
                        </a>
                    </li>
                    <li class="<?php echo ($filter_status === 'APPROVED') ? 'active' : ''; ?>">
                        <a href="discount-approvals.php?status=APPROVED" style="color: #059669;">
                            <i class="fa fa-check-circle"></i> Approved 
                            <span class="badge" style="background-color: #10b981;"><?php echo (int)$counts['total_approved']; ?></span>
                        </a>
                    </li>
                    <li class="<?php echo ($filter_status === 'REJECTED') ? 'active' : ''; ?>">
                        <a href="discount-approvals.php?status=REJECTED" style="color: #dc2626;">
                            <i class="fa fa-times-circle"></i> Rejected 
                            <span class="badge" style="background-color: #ef4444;"><?php echo (int)$counts['total_rejected']; ?></span>
                        </a>
                    </li>
                </ul>

                <div class="tab-content" style="padding: 20px;">
                    
                    <!-- Search Bar -->
                    <form method="GET" action="discount-approvals.php" class="form-inline" style="margin-bottom: 20px;">
                        <input type="hidden" name="status" value="<?php echo htmlspecialchars($filter_status); ?>">
                        <div class="form-group" style="margin-right: 10px;">
                            <div class="input-group">
                                <input type="text" name="q" class="form-control input-sm" style="width: 280px;" placeholder="Search Request ID, Product, Cashier..." value="<?php echo htmlspecialchars($search_query); ?>">
                                <span class="input-group-btn">
                                    <button type="submit" class="btn btn-primary btn-sm"><i class="fa fa-search"></i> Search</button>
                                </span>
                            </div>
                        </div>
                        <?php if (!empty($search_query)): ?>
                            <a href="discount-approvals.php?status=<?php echo htmlspecialchars($filter_status); ?>" class="btn btn-default btn-sm"><i class="fa fa-times"></i> Clear Search</a>
                        <?php endif; ?>
                    </form>

                    <!-- Requests Table -->
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover" style="font-size: 12.5px;">
                            <thead>
                                <tr style="background: #f1f5f9; color: #1e293b;">
                                    <th style="width: 155px;">Request Info</th>
                                    <th>Product / Item Details</th>
                                    <th style="width: 110px; text-align: right;">Gross Value</th>
                                    <th style="width: 135px; text-align: center;">Discount Request</th>
                                    <th style="width: 125px; text-align: right;">Approved Value</th>
                                    <th style="width: 120px; text-align: center;">Status</th>
                                    <th style="width: 160px;">Audit / Approver</th>
                                    <th style="width: 155px; text-align: center;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($requests)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center text-muted" style="padding: 40px 10px;">
                                            <i class="fa fa-inbox fa-3x" style="color: #cbd5e1;"></i>
                                            <p style="margin-top: 10px; font-size: 14px;">No discount requests found matching your filter.</p>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($requests as $r): 
                                        $is_pending = in_array($r['status'], ['PENDING', 'ESCALATED']);
                                        $is_special = ($r['approval_level'] === 'HIGHER_APPROVER' || $r['status'] === 'ESCALATED');
                                        $req_role = !empty($r['requester_role']) ? $r['requester_role'] : 'CASHIER';
                                        $req_role_label = ucfirst(strtolower($req_role));
                                        $req_badge_cls = in_array($req_role, ['ADMIN', 'MANAGER']) ? 'label-primary' : (($req_role === 'SUPERVISOR') ? 'label-warning' : 'label-info');
                                        
                                        $can_approve = can_user_approve_discount($r['cashier_id'], $req_role, $current_uid, $user_role, true);
                                        $is_self = ((int)$r['cashier_id'] === $current_uid);
                                        $req_approval_role_display = !empty($r['required_approval_role']) ? $r['required_approval_role'] : get_required_approval_role($req_role);
                                    ?>
                                    <tr id="row_<?php echo $r['request_id']; ?>" style="<?php echo $is_special ? 'background-color: #fffbeb;' : ''; ?>">
                                        
                                        <!-- Request Info -->
                                        <td>
                                            <strong style="font-family: monospace; color: #1e40af; font-size: 12px;"><?php echo htmlspecialchars($r['request_id']); ?></strong>
                                            <div style="font-size: 11px; color: #64748b; margin-top: 2px;">
                                                <i class="fa fa-calendar"></i> <?php echo date('M d, Y h:i A', strtotime($r['created_at'])); ?>
                                            </div>
                                            <div style="font-size: 11px; color: #334155; margin-top: 3px;">
                                                <i class="fa fa-user"></i> Requester: <strong><?php echo htmlspecialchars($r['cashier_name']); ?></strong><br>
                                                <span class="label <?php echo $req_badge_cls; ?>" style="font-size: 9.5px; padding: 1px 5px;"><?php echo $req_role_label; ?></span>
                                            </div>
                                            <div style="font-size: 10.5px; color: #0369a1; background: #e0f2fe; border: 1px solid #bae6fd; padding: 2px 5px; border-radius: 4px; margin-top: 4px; line-height: 1.2;">
                                                <i class="fa fa-shield"></i> Required:<br><strong><?php echo htmlspecialchars($req_approval_role_display); ?></strong>
                                            </div>
                                        </td>

                                        <!-- Product Details -->
                                        <td>
                                            <?php if ($r['item_type'] === 'SPECIAL_ORDER'): ?>
                                                <span class="label label-warning" style="background-color: #d97706; font-size: 9px; padding: 2px 5px;">SPECIAL ORDER</span><br>
                                            <?php endif; ?>
                                            <strong style="color: #0f172a; font-size: 13px;"><?php echo htmlspecialchars($r['product_name']); ?></strong>
                                            <?php if (!empty($r['product_details'])): ?>
                                                <div style="font-size: 11px; color: #475569;"><?php echo htmlspecialchars($r['product_details']); ?></div>
                                            <?php endif; ?>
                                            <div style="font-size: 11px; color: #64748b; font-family: monospace; margin-top: 2px;">
                                                <?php echo !empty($r['sku']) ? 'SKU: ' . htmlspecialchars($r['sku']) . ' | ' : ''; ?>
                                                &#8369;<?php echo number_format($r['original_unit_price'], 2); ?> &times; <?php echo (int)$r['quantity']; ?> unit(s)
                                            </div>
                                            <?php if (!empty($r['cashier_remarks'])): ?>
                                                <div style="font-size: 11px; color: #0369a1; background: #e0f2fe; padding: 2px 6px; border-radius: 4px; margin-top: 4px; display: inline-block;">
                                                    <i class="fa fa-comment-o"></i> Remarks: "<?php echo htmlspecialchars($r['cashier_remarks']); ?>"
                                                </div>
                                            <?php endif; ?>
                                        </td>

                                        <!-- Gross Value -->
                                        <td style="text-align: right; font-weight: 700; font-size: 13.5px; color: #334155;">
                                            &#8369;<?php echo number_format($r['original_item_value'], 2); ?>
                                        </td>

                                        <!-- Requested Discount -->
                                        <td style="text-align: center;">
                                            <?php 
                                            $req_discount_amt = ($r['discount_amount'] !== null && $r['discount_amount'] > 0 && ($r['status'] === 'PENDING' || $r['status'] === 'ESCALATED')) 
                                                ? floatval($r['discount_amount']) 
                                                : round(floatval($r['original_item_value']) * (floatval($r['requested_discount_percent']) / 100), 2);
                                            ?>
                                            <div style="font-size: 15px; font-weight: 900; color: <?php echo $is_special ? '#d97706' : '#2563eb'; ?>;">
                                                &#8369;<?php echo number_format($req_discount_amt, 2); ?>
                                            </div>
                                            <div style="font-size: 11px; font-weight: 600; color: #64748b; margin-top: 1px;">
                                                (<?php echo number_format($r['requested_discount_percent'], 2); ?>%)
                                            </div>
                                            <?php if ($is_special): ?>
                                                <span class="label label-warning" style="background-color: #d97706; font-size: 9px; padding: 2px 4px;">
                                                    <i class="fa fa-shield"></i> SPECIAL APPROVAL
                                                </span>
                                            <?php else: ?>
                                                <span class="label label-info" style="font-size: 9px; padding: 2px 4px;">NORMAL (&le; &#8369;<?php echo number_format($r['original_item_value'] * ($r['normal_max_discount_percent']/100), 2); ?>)</span>
                                            <?php endif; ?>
                                        </td>

                                        <!-- Approved Value -->
                                        <td style="text-align: right;">
                                            <?php if ($r['status'] === 'APPROVED' || $r['status'] === 'APPROVED_MODIFIED'): ?>
                                                <strong style="color: #166534; font-size: 14px;">&#8369;<?php echo number_format($r['final_item_value'], 2); ?></strong>
                                                <div style="font-size: 11px; color: #15803d; font-weight: 600;">
                                                    -&#8369;<?php echo number_format($r['discount_amount'], 2); ?> (<?php echo number_format($r['approved_discount_percent'], 2); ?>%)
                                                </div>
                                            <?php elseif ($r['status'] === 'REJECTED'): ?>
                                                <span class="text-danger" style="font-weight: 600;">&#8369;<?php echo number_format($r['original_item_value'], 2); ?></span>
                                                <div style="font-size: 10.5px; color: #991b1b;">(No discount)</div>
                                            <?php else: ?>
                                                <span class="text-muted">&#8369;<?php echo number_format($r['original_item_value'] - $req_discount_amt, 2); ?></span>
                                                <div style="font-size: 10.5px; color: #64748b;">(Pending)</div>
                                            <?php endif; ?>
                                        </td>

                                        <!-- Status Badge -->
                                        <td style="text-align: center;">
                                            <?php if ($r['status'] === 'PENDING'): ?>
                                                <span class="label label-warning" style="font-size: 10px; padding: 3px 6px;"><i class="fa fa-clock-o"></i> PENDING</span>
                                            <?php elseif ($r['status'] === 'ESCALATED'): ?>
                                                <span class="label label-warning" style="background-color: #ea580c; font-size: 10px; padding: 3px 6px;"><i class="fa fa-shield"></i> ESCALATED</span>
                                            <?php elseif ($r['status'] === 'APPROVED'): ?>
                                                <span class="label label-success" style="font-size: 10px; padding: 3px 6px;"><i class="fa fa-check"></i> APPROVED</span>
                                            <?php elseif ($r['status'] === 'APPROVED_MODIFIED'): ?>
                                                <span class="label label-primary" style="background-color: #0891b2; font-size: 10px; padding: 3px 6px;"><i class="fa fa-pencil"></i> MODIFIED</span>
                                            <?php elseif ($r['status'] === 'REJECTED'): ?>
                                                <span class="label label-danger" style="font-size: 10px; padding: 3px 6px;"><i class="fa fa-times"></i> REJECTED</span>
                                            <?php else: ?>
                                                <span class="label label-default" style="font-size: 10px; padding: 3px 6px;"><?php echo htmlspecialchars($r['status']); ?></span>
                                            <?php endif; ?>
                                        </td>

                                        <!-- Audit Details -->
                                        <td>
                                            <?php if (!empty($r['approver_name'])): ?>
                                                <div style="font-size: 11.5px; color: #1e293b;">
                                                    <i class="fa fa-user-circle text-primary"></i> <strong><?php echo htmlspecialchars($r['approver_name']); ?></strong>
                                                    <?php if (!empty($r['approver_role'])): ?>
                                                        <span class="label label-default" style="font-size: 9px; padding: 1px 4px;"><?php echo ucfirst(strtolower($r['approver_role'])); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                                <div style="font-size: 10.5px; color: #64748b;">
                                                    <?php echo date('M d, Y h:i A', strtotime($r['updated_at'])); ?>
                                                </div>
                                            <?php endif; ?>
                                            <?php if (!empty($r['approver_remarks'])): ?>
                                                <div style="font-size: 11px; color: #475569; background: #f1f5f9; padding: 3px 6px; border-radius: 4px; margin-top: 3px;">
                                                    <em>"<?php echo htmlspecialchars($r['approver_remarks']); ?>"</em>
                                                </div>
                                            <?php endif; ?>
                                            <?php if (!empty($r['payment_id'])): ?>
                                                <div style="font-size: 10.5px; color: #0284c7; margin-top: 2px;">
                                                    <i class="fa fa-file-text-o"></i> Sale: <strong><?php echo htmlspecialchars($r['payment_id']); ?></strong>
                                                </div>
                                            <?php endif; ?>
                                        </td>

                                        <!-- Action Buttons -->
                                        <td style="text-align: center;">
                                            <?php if ($is_pending): ?>
                                                <?php if ($can_approve): ?>
                                                    <div class="btn-group-vertical btn-group-xs" style="width: 100%;">
                                                        <button type="button" class="btn btn-success btn-xs" style="margin-bottom: 2px; font-weight: 700;" onclick="handleAction('approve', '<?php echo $r['request_id']; ?>', '&#8369;<?php echo number_format($req_discount_amt, 2); ?> (<?php echo number_format($r['requested_discount_percent'], 2); ?>%)', '<?php echo htmlspecialchars(addslashes($r['product_name'])); ?>')">
                                                            <i class="fa fa-check"></i> Approve (&#8369;<?php echo number_format($req_discount_amt, 2); ?>)
                                                        </button>
                                                        <button type="button" class="btn btn-info btn-xs" style="margin-bottom: 2px; font-weight: 700;" onclick="openModifyModal('<?php echo $r['request_id']; ?>', '<?php echo number_format($req_discount_amt, 2); ?>', '<?php echo number_format($r['original_item_value'], 2); ?>', '<?php echo number_format($r['absolute_max_discount_percent'], 2); ?>', '<?php echo htmlspecialchars(addslashes($r['product_name'])); ?>')">
                                                            <i class="fa fa-edit"></i> Modify ₱
                                                        </button>
                                                        <button type="button" class="btn btn-danger btn-xs" style="font-weight: 700;" onclick="openRejectModal('<?php echo $r['request_id']; ?>', '<?php echo htmlspecialchars(addslashes($r['product_name'])); ?>')">
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
                                                            <i class="fa fa-lock"></i> Requires<br><?php echo htmlspecialchars($req_approval_role_display); ?>
                                                        </span>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="text-muted" style="font-size: 11px;"><i class="fa fa-lock"></i> Locked</span>
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

<!-- Modal: Modify Discount -->
<div class="modal fade" id="modalModifyDiscount" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-sm" role="document">
        <div class="modal-content" style="border-radius: 8px;">
            <div class="modal-header" style="background: #0284c7; color: #fff;">
                <button type="button" class="close" data-dismiss="modal" style="color: #fff;">&times;</button>
                <h4 class="modal-title" style="font-weight: bold; font-size: 15px;"><i class="fa fa-pencil"></i> Modify & Approve Discount</h4>
            </div>
            <div class="modal-body">
                <input type="hidden" id="modReqId">
                <input type="hidden" id="modOrigValue">
                <input type="hidden" id="modAbsMaxPct">
                <p id="modProdName" style="font-weight: bold; color: #1e293b; margin-bottom: 4px;"></p>
                <div id="modifyModalAlertArea"></div>
                <p id="modItemGrossText" style="font-size: 12px; color: #64748b; margin-bottom: 12px;"></p>
                
                <div class="form-group">
                    <label style="font-size: 12px;">New Approved Discount Amount (₱): *</label>
                    <div class="input-group">
                        <span class="input-group-addon" style="font-weight: bold;">₱</span>
                        <input type="number" step="0.01" min="0.01" id="modDiscountInput" class="form-control" placeholder="e.g. 2.00" style="font-weight: bold; font-size: 15px; color: #0284c7;" oninput="calcModPreview()">
                    </div>
                    <p class="text-muted" style="font-size: 11px; margin-top: 4px;" id="modMaxNotice"></p>
                </div>

                <!-- Live Calculations Box -->
                <div id="modPreviewBox" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 8px 12px; margin-bottom: 12px; font-size: 12px;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                        <span style="color: #64748b;">Equivalent %:</span>
                        <strong id="modPreviewPct" style="color: #0284c7;">0.00%</strong>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span style="color: #64748b;">Final Net Price:</span>
                        <strong id="modPreviewFinal" style="color: #166534;">₱0.00</strong>
                    </div>
                </div>

                <div class="form-group">
                    <label style="font-size: 12px;">Approver Remarks / Reason (Optional):</label>
                    <textarea id="modRemarksInput" class="form-control" rows="2" placeholder="e.g. Discount adjusted based on store policy."></textarea>
                </div>
            </div>
            <div class="modal-footer" style="background: #f8fafc;">
                <button type="button" class="btn btn-default btn-sm" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary btn-sm" onclick="submitModifyDiscount()" style="font-weight: bold;"><i class="fa fa-check"></i> Save & Approve</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Reject Discount -->
<div class="modal fade" id="modalRejectDiscount" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-sm" role="document">
        <div class="modal-content" style="border-radius: 8px;">
            <div class="modal-header" style="background: #dc2626; color: #fff;">
                <button type="button" class="close" data-dismiss="modal" style="color: #fff;">&times;</button>
                <h4 class="modal-title" style="font-weight: bold; font-size: 15px;"><i class="fa fa-times-circle"></i> Reject Discount Request</h4>
            </div>
            <div class="modal-body">
                <div id="rejectModalAlertArea"></div>
                <input type="hidden" id="rejReqId">
                <p id="rejProdName" style="font-weight: bold; color: #1e293b; margin-bottom: 10px;"></p>
                
                <div class="form-group">
                    <label style="font-size: 12px;">Rejection Reason / Remarks:</label>
                    <textarea id="rejRemarksInput" class="form-control" rows="3" placeholder="e.g. Discount exceeds approved customer pricing policy."></textarea>
                </div>
            </div>
            <div class="modal-footer" style="background: #f8fafc;">
                <button type="button" class="btn btn-default btn-sm" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger btn-sm" onclick="submitRejectDiscount()" style="font-weight: bold;"><i class="fa fa-ban"></i> Confirm Rejection</button>
            </div>
        </div>
    </div>
</div>

<script>
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

function handleAction(type, reqId, discLabel, prodName) {
    if (type === 'approve') {
        if (confirm(`Approve requested ${discLabel} discount for ${prodName}?`)) {
            const remarks = prompt('Optional Remarks for Cashier:', '') || '';
            $.post('pos-discount-api.php', {
                action: 'approve_discount',
                request_id: reqId,
                approver_remarks: remarks
            }, function(res) {
                if (res.status === 'success') {
                    sessionStorage.setItem('flash_message', res.message || 'Discount approved successfully.');
                    sessionStorage.setItem('flash_type', 'success');
                    location.reload();
                } else {
                    showPageAlert(res.message || 'Failed to approve discount.', 'danger');
                }
            }, 'json').fail(function() {
                showPageAlert('Server error while processing discount approval.', 'danger');
            });
        }
    }
}

function openModifyModal(reqId, currentAmt, origVal, absMaxPct, prodName) {
    $('#modifyModalAlertArea').html('');
    $('#modReqId').val(reqId);
    $('#modOrigValue').val(origVal);
    $('#modAbsMaxPct').val(absMaxPct);
    $('#modProdName').text(prodName);
    $('#modItemGrossText').html('Item Gross Total: <strong>₱' + parseFloat(origVal).toFixed(2) + '</strong>');
    $('#modDiscountInput').val(parseFloat(currentAmt).toFixed(2));
    
    const maxAmt = (parseFloat(origVal) * (parseFloat(absMaxPct) / 100)).toFixed(2);
    $('#modDiscountInput').attr('max', maxAmt);
    $('#modMaxNotice').html(`Maximum allowed: <strong>₱${maxAmt}</strong> (${absMaxPct}%)`);
    $('#modRemarksInput').val('');
    calcModPreview();
    $('#modalModifyDiscount').modal('show');
    setTimeout(() => $('#modDiscountInput').focus(), 350);
}

function calcModPreview() {
    const origVal = parseFloat($('#modOrigValue').val()) || 0;
    const amt = parseFloat($('#modDiscountInput').val()) || 0;
    if (origVal > 0 && amt > 0) {
        const pct = (amt / origVal) * 100;
        const finalVal = Math.max(0, origVal - amt);
        $('#modPreviewPct').text(pct.toFixed(2) + '%');
        $('#modPreviewFinal').text('₱' + finalVal.toFixed(2));
    } else {
        $('#modPreviewPct').text('0.00%');
        $('#modPreviewFinal').text('₱' + origVal.toFixed(2));
    }
}

function submitModifyDiscount() {
    const reqId = $('#modReqId').val();
    const newAmt = parseFloat($('#modDiscountInput').val());
    const origVal = parseFloat($('#modOrigValue').val()) || 0;
    const absMaxPct = parseFloat($('#modAbsMaxPct').val()) || 100;
    const maxAmt = parseFloat((origVal * (absMaxPct / 100)).toFixed(2));
    const remarks = $('#modRemarksInput').val().trim();

    if (isNaN(newAmt) || newAmt <= 0) {
        showModalAlert('#modifyModalAlertArea', 'Please enter a valid discount amount greater than ₱0.00.', 'danger');
        return;
    }
    if (newAmt >= origVal) {
        showModalAlert('#modifyModalAlertArea', 'Discount amount cannot equal or exceed item gross total (₱' + origVal.toFixed(2) + ').', 'danger');
        return;
    }
    if (newAmt > maxAmt + 0.005) {
        showModalAlert('#modifyModalAlertArea', 'Discount amount exceeds maximum allowed (₱' + maxAmt.toFixed(2) + ' / ' + absMaxPct + '%).', 'danger');
        return;
    }

    $.post('pos-discount-api.php', {
        action: 'modify_discount',
        request_id: reqId,
        modified_discount_amount: newAmt,
        approver_remarks: remarks
    }, function(res) {
        if (res.status === 'success') {
            $('#modalModifyDiscount').modal('hide');
            sessionStorage.setItem('flash_message', res.message || 'Discount modified and approved.');
            sessionStorage.setItem('flash_type', 'success');
            location.reload();
        } else {
            showModalAlert('#modifyModalAlertArea', res.message || 'Failed to modify discount.', 'danger');
        }
    }, 'json').fail(function() {
        showModalAlert('#modifyModalAlertArea', 'Server error while modifying discount.', 'danger');
    });
}

function openRejectModal(reqId, prodName) {
    $('#rejectModalAlertArea').html('');
    $('#rejReqId').val(reqId);
    $('#rejProdName').text(prodName);
    $('#rejRemarksInput').val('');
    $('#modalRejectDiscount').modal('show');
}

function submitRejectDiscount() {
    const reqId = $('#rejReqId').val();
    const remarks = $('#rejRemarksInput').val().trim();

    $.post('pos-discount-api.php', {
        action: 'reject_discount',
        request_id: reqId,
        approver_remarks: remarks
    }, function(res) {
        if (res.status === 'success') {
            $('#modalRejectDiscount').modal('hide');
            sessionStorage.setItem('flash_message', res.message || 'Discount request rejected.');
            sessionStorage.setItem('flash_type', 'warning');
            location.reload();
        } else {
            showModalAlert('#rejectModalAlertArea', res.message || 'Failed to reject discount.', 'danger');
        }
    }, 'json').fail(function() {
        showModalAlert('#rejectModalAlertArea', 'Server error while rejecting discount.', 'danger');
    });
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.innerText = text;
    return div.innerHTML;
}
</script>

<?php require_once('footer.php'); ?>
