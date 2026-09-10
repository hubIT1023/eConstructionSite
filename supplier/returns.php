<?php require_once('header.php'); ?>

<?php
$supplier_id = (int)$_SESSION['supplier_user']['supplier_id'];
$user_role_raw = isset($_SESSION['supplier_user']['role']) ? $_SESSION['supplier_user']['role'] : 'USER';
$user_role = normalize_supplier_role($user_role_raw);
$is_admin = can_user_delete_return($user_role_raw);

// Date Filters
$today = date('Y-m-d');
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : $today;
$filter_method = isset($_GET['refund_method']) ? $_GET['refund_method'] : '';
$filter_condition = isset($_GET['condition']) ? $_GET['condition'] : '';
$search_query = isset($_GET['q']) ? trim($_GET['q']) : '';

// Build Query for Returns
$params = array($supplier_id);
$where_clauses = ["r.supplier_id = ?"];

if (!empty($start_date)) {
    $where_clauses[] = "r.return_date >= ?";
    $params[] = $start_date . ' 00:00:00';
}
if (!empty($end_date)) {
    $where_clauses[] = "r.return_date <= ?";
    $params[] = $end_date . ' 23:59:59';
}
if (!empty($filter_method)) {
    $where_clauses[] = "r.refund_method = ?";
    $params[] = $filter_method;
}
if (!empty($filter_condition)) {
    $where_clauses[] = "ri.condition = ?";
    $params[] = $filter_condition;
}
if (!empty($search_query)) {
    $where_clauses[] = "(r.return_reference ILIKE ? OR r.payment_id ILIKE ? OR r.customer_name ILIKE ? OR r.customer_phone ILIKE ? OR ri.product_name ILIKE ? OR ri.sku ILIKE ?)";
    $q_param = '%' . $search_query . '%';
    $params[] = $q_param;
    $params[] = $q_param;
    $params[] = $q_param;
    $params[] = $q_param;
    $params[] = $q_param;
    $params[] = $q_param;
}

ensure_supplier_user_schema($pdo);

$where_sql = implode(' AND ', $where_clauses);

$returns = [];
try {
    $sql = "SELECT r.*, COALESCE(ri.return_item_id, ri.item_id, 0) as return_item_id, ri.order_item_id, ri.product_id, ri.product_name, ri.sku, ri.size, ri.color, ri.item_type, ri.special_order_reference, ri.product_details, ri.quantity_returned, ri.unit_price as item_unit_price, ri.refund_amount as item_refund, ri.return_reason, ri.condition, ri.restock_status, ri.notes as item_notes
            FROM tbl_returns r
            JOIN tbl_return_items ri ON r.return_id = ri.return_id
            WHERE {$where_sql}
            ORDER BY r.return_id DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $returns = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $returns = [];
}

// Metrics
$total_returns_count = count($returns);
$total_refund_amount = 0;
$total_units_returned = 0;
$total_restocked_units = 0;
$total_non_restocked_units = 0;

foreach ($returns as $ret) {
    $total_refund_amount += isset($ret['item_refund']) ? (float)$ret['item_refund'] : 0;
    $total_units_returned += isset($ret['quantity_returned']) ? (int)$ret['quantity_returned'] : 0;
    if (isset($ret['restock_status']) && $ret['restock_status'] === 'RESTOCKED') {
        $total_restocked_units += (int)$ret['quantity_returned'];
    } else {
        $total_non_restocked_units += isset($ret['quantity_returned']) ? (int)$ret['quantity_returned'] : 0;
    }
}

// Fetch Supplier Info for printable slips
$supp_info = ['supplier_name' => '', 'supplier_address' => '', 'supplier_phone' => ''];
try {
    $stmt_s = $pdo->prepare("SELECT supplier_name, supplier_address, supplier_phone FROM tbl_supplier WHERE supplier_id=?");
    $stmt_s->execute(array($supplier_id));
    $supp_info_db = $stmt_s->fetch(PDO::FETCH_ASSOC);
    if ($supp_info_db) $supp_info = $supp_info_db;
} catch (Exception $e) {}
?>

<section class="content-header">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
        <h1>
            <i class="fa fa-undo" style="color: #dc2626;"></i> Return History & Refunds
            <small>Auditable log of processed item returns, restocked inventory, and customer refunds</small>
        </h1>
        <div style="display: flex; gap: 6px; align-items: center; flex-wrap: wrap;">
            <?php if ($is_admin): ?>
                <button type="button" class="btn btn-danger btn-sm btnDeleteSelectedTop" style="display: none; font-weight: bold;" onclick="deleteSelectedReturns()">
                    <i class="fa fa-trash"></i> Delete Selected (<span class="selectedCountText">0</span>)
                </button>
            <?php endif; ?>
            <?php if (is_supplier_approver()): ?>
                <a href="return-approvals.php" class="btn btn-danger btn-sm" style="font-weight: bold;"><i class="fa fa-check-circle"></i> Return Approval Queue</a>
            <?php endif; ?>
            <a href="pos.php" class="btn btn-primary btn-sm"><i class="fa fa-calculator"></i> Go to POS</a>
            <a href="paid-orders.php" class="btn btn-default btn-sm"><i class="fa fa-check-square-o"></i> Paid Orders</a>
            <a href="sales-report.php" class="btn btn-default btn-sm"><i class="fa fa-line-chart"></i> Sales Report</a>
        </div>
    </div>
</section>


<section class="content">

    <!-- Flash Message / Alert Component -->
    <div id="pageAlertContainer">
        <?php 
        $display_success = !empty($_SESSION['success_message']) ? $_SESSION['success_message'] : (!empty($success_message) ? $success_message : (!empty($_GET['success']) ? $_GET['success'] : ''));
        $display_error = !empty($_SESSION['error_message']) ? $_SESSION['error_message'] : (!empty($error_message) ? $error_message : (!empty($_GET['error']) ? $_GET['error'] : ''));
        $display_warning = !empty($_SESSION['warning_message']) ? $_SESSION['warning_message'] : (!empty($warning_message) ? $warning_message : (!empty($_GET['warning']) ? $_GET['warning'] : ''));
        $display_info = !empty($_SESSION['info_message']) ? $_SESSION['info_message'] : (!empty($info_message) ? $info_message : (!empty($_GET['info']) ? $_GET['info'] : ''));
        
        if (!empty($_SESSION['success_message'])) unset($_SESSION['success_message']);
        if (!empty($_SESSION['error_message'])) unset($_SESSION['error_message']);
        if (!empty($_SESSION['warning_message'])) unset($_SESSION['warning_message']);
        if (!empty($_SESSION['info_message'])) unset($_SESSION['info_message']);
        ?>

        <?php if (!empty($display_success)): ?>
            <div class="alert alert-success alert-dismissible" style="border-radius: 6px; font-weight: 500; margin-bottom: 15px; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">
                <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                <i class="icon fa fa-check"></i> <?php echo htmlspecialchars($display_success); ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($display_error)): ?>
            <div class="alert alert-danger alert-dismissible" style="border-radius: 6px; font-weight: 500; margin-bottom: 15px; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">
                <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                <i class="icon fa fa-ban"></i> <?php echo htmlspecialchars($display_error); ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($display_warning)): ?>
            <div class="alert alert-warning alert-dismissible" style="border-radius: 6px; font-weight: 500; margin-bottom: 15px; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">
                <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                <i class="icon fa fa-exclamation-triangle"></i> <?php echo htmlspecialchars($display_warning); ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($display_info)): ?>
            <div class="alert alert-info alert-dismissible" style="border-radius: 6px; font-weight: 500; margin-bottom: 15px; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">
                <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                <i class="icon fa fa-info-circle"></i> <?php echo htmlspecialchars($display_info); ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- KPI Metric Summary Cards -->
    <div class="row">
        <div class="col-md-3 col-sm-6 col-xs-12">

            <div class="info-box" style="border-radius: 8px; box-shadow: 0 2px 6px rgba(0,0,0,0.06); border-left: 4px solid #dc2626;">
                <span class="info-box-icon bg-red" style="border-radius: 8px 0 0 8px;"><i class="fa fa-undo"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text" style="font-weight: 700; color: #64748b;">Total Returns</span>
                    <span class="info-box-number" style="font-size: 22px; font-weight: 800; color: #0f172a;"><?php echo number_format($total_returns_count); ?></span>
                    <span class="text-muted" style="font-size: 11px;">Processed transactions</span>
                </div>
            </div>
        </div>

        <div class="col-md-3 col-sm-6 col-xs-12">
            <div class="info-box" style="border-radius: 8px; box-shadow: 0 2px 6px rgba(0,0,0,0.06); border-left: 4px solid #b91c1c;">
                <span class="info-box-icon bg-red" style="border-radius: 8px 0 0 8px; background-color: #991b1b !important;"><i class="fa fa-money"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text" style="font-weight: 700; color: #64748b;">Total Refunded</span>
                    <span class="info-box-number" style="font-size: 22px; font-weight: 800; color: #b91c1c;">&#8369;<?php echo number_format($total_refund_amount, 2); ?></span>
                    <span class="text-muted" style="font-size: 11px;">Total money refunded</span>
                </div>
            </div>
        </div>

        <div class="col-md-3 col-sm-6 col-xs-12">
            <div class="info-box" style="border-radius: 8px; box-shadow: 0 2px 6px rgba(0,0,0,0.06); border-left: 4px solid #16a34a;">
                <span class="info-box-icon bg-green" style="border-radius: 8px 0 0 8px;"><i class="fa fa-cubes"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text" style="font-weight: 700; color: #64748b;">Restocked Units</span>
                    <span class="info-box-number" style="font-size: 22px; font-weight: 800; color: #16a34a;"><?php echo number_format($total_restocked_units); ?></span>
                    <span class="text-muted" style="font-size: 11px;">Added back to inventory</span>
                </div>
            </div>
        </div>

        <div class="col-md-3 col-sm-6 col-xs-12">
            <div class="info-box" style="border-radius: 8px; box-shadow: 0 2px 6px rgba(0,0,0,0.06); border-left: 4px solid #d97706;">
                <span class="info-box-icon bg-yellow" style="border-radius: 8px 0 0 8px;"><i class="fa fa-exclamation-triangle"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text" style="font-weight: 700; color: #64748b;">Non-Restocked</span>
                    <span class="info-box-number" style="font-size: 22px; font-weight: 800; color: #d97706;"><?php echo number_format($total_non_restocked_units); ?></span>
                    <span class="text-muted" style="font-size: 11px;">Damaged / Special / Scrap</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter Form -->
    <div class="box box-default" style="border-radius: 8px;">
        <div class="box-body" style="padding: 15px 20px;">
            <form method="GET" action="returns.php" class="form-inline" style="display: flex; flex-wrap: wrap; gap: 10px; align-items: flex-end;">
                
                <div class="form-group">
                    <label style="font-size: 12px; display: block; margin-bottom: 3px;">Start Date:</label>
                    <input type="date" name="start_date" class="form-control input-sm" value="<?php echo htmlspecialchars($start_date); ?>">
                </div>

                <div class="form-group">
                    <label style="font-size: 12px; display: block; margin-bottom: 3px;">End Date:</label>
                    <input type="date" name="end_date" class="form-control input-sm" value="<?php echo htmlspecialchars($end_date); ?>">
                </div>

                <div class="form-group">
                    <label style="font-size: 12px; display: block; margin-bottom: 3px;">Refund Method:</label>
                    <select name="refund_method" class="form-control input-sm">
                        <option value="">All Methods</option>
                        <option value="Cash" <?php if($filter_method==='Cash') echo 'selected'; ?>>Cash</option>
                        <option value="Original Payment Method" <?php if($filter_method==='Original Payment Method') echo 'selected'; ?>>Original Payment Method</option>
                        <option value="Store Credit / Account Credit" <?php if($filter_method==='Store Credit / Account Credit') echo 'selected'; ?>>Store Credit</option>
                        <option value="GCash / Maya" <?php if($filter_method==='GCash / Maya') echo 'selected'; ?>>GCash / Maya</option>
                        <option value="Bank Transfer" <?php if($filter_method==='Bank Transfer') echo 'selected'; ?>>Bank Transfer</option>
                        <option value="Replacement" <?php if($filter_method==='Replacement') echo 'selected'; ?>>Replacement</option>
                        <option value="Other" <?php if($filter_method==='Other') echo 'selected'; ?>>Other</option>
                    </select>
                </div>

                <div class="form-group">
                    <label style="font-size: 12px; display: block; margin-bottom: 3px;">Condition:</label>
                    <select name="condition" class="form-control input-sm">
                        <option value="">All Conditions</option>
                        <option value="Resellable" <?php if($filter_condition==='Resellable') echo 'selected'; ?>>Resellable</option>
                        <option value="Unopened" <?php if($filter_condition==='Unopened') echo 'selected'; ?>>Unopened</option>
                        <option value="Opened" <?php if($filter_condition==='Opened') echo 'selected'; ?>>Opened</option>
                        <option value="Used" <?php if($filter_condition==='Used') echo 'selected'; ?>>Used</option>
                        <option value="Damaged" <?php if($filter_condition==='Damaged') echo 'selected'; ?>>Damaged</option>
                        <option value="Defective" <?php if($filter_condition==='Defective') echo 'selected'; ?>>Defective</option>
                        <option value="Needs Inspection" <?php if($filter_condition==='Needs Inspection') echo 'selected'; ?>>Needs Inspection</option>
                    </select>
                </div>

                <div class="form-group">
                    <label style="font-size: 12px; display: block; margin-bottom: 3px;">Search Keyword:</label>
                    <input type="text" name="q" class="form-control input-sm" placeholder="Ref #, Invoice, Customer, SKU..." value="<?php echo htmlspecialchars($search_query); ?>" style="width: 200px;">
                </div>

                <div class="form-group">
                    <button type="submit" class="btn btn-primary btn-sm" style="font-weight: 700;"><i class="fa fa-filter"></i> Filter</button>
                    <a href="returns.php" class="btn btn-default btn-sm"><i class="fa fa-refresh"></i> Reset</a>
                </div>

            </form>
        </div>
    </div>

    <!-- Returns Table -->
    <div class="box box-danger" style="border-radius: 8px;">
        <div class="box-header with-border" style="padding: 12px 18px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
            <h3 class="box-title" style="font-weight: 700; color: #1e293b; font-size: 16px; margin: 0;">
                <i class="fa fa-table text-danger"></i> Return Transactions Log
            </h3>
            <?php if ($is_admin): ?>
                <div>
                    <button type="button" class="btn btn-danger btn-sm btnDeleteSelectedTable" style="display: none; font-weight: bold;" onclick="deleteSelectedReturns()">
                        <i class="fa fa-trash"></i> Delete Selected (<span class="selectedCountText">0</span>)
                    </button>
                </div>
            <?php endif; ?>
        </div>

        <div class="box-body table-responsive" style="padding: 10px 18px;">
            <table id="example1" class="table table-bordered table-striped table-hover" style="font-size: 13px;">
                <thead>
                    <tr style="background: #f8fafc;">
                        <?php if ($is_admin): ?>
                            <th data-orderable="false" class="no-sort" style="width: 32px; text-align: center; vertical-align: middle;">
                                <input type="checkbox" id="selectAllReturns" style="cursor: pointer;" title="Select All Items">
                            </th>
                        <?php endif; ?>
                        <th style="width: 35px; text-align: center;">#</th>
                        <th>Return Ref</th>
                        <th>Date & Time</th>
                        <th>Original Invoice</th>
                        <th>Customer</th>
                        <th>Product / Item Returned</th>
                        <th style="text-align: center;">Qty</th>
                        <th style="text-align: right;">Unit Price</th>
                        <th style="text-align: right;">Refund Amount</th>
                        <th>Condition & Reason</th>
                        <th>Restock Action</th>
                        <th style="text-align: center;">Status</th>
                        <th>Audit / Approver</th>
                        <th data-orderable="false" class="no-sort" style="text-align: center; width: <?php echo $is_admin ? '120px' : '75px'; ?>;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $i = 0;
                    foreach ($returns as $row): 
                        $i++;
                        $is_special = ($row['item_type'] === 'SPECIAL_ORDER' || (int)$row['product_id'] === 0);
                        $is_restocked = ($row['restock_status'] === 'RESTOCKED');
                        $ret_status = $row['status'] ?: 'COMPLETED';
                        $is_pending = in_array($ret_status, ['PENDING_APPROVAL', 'Pending']);
                        $is_completed = in_array($ret_status, ['COMPLETED', 'APPROVED', 'REFUNDED']);
                        $is_rejected = ($ret_status === 'REJECTED');
                    ?>
                    <tr id="row_item_<?php echo $row['return_item_id']; ?>">
                        <?php if ($is_admin): ?>
                            <td style="text-align: center; vertical-align: middle;">
                                <input type="checkbox" class="ret-checkbox" value="<?php echo $row['return_item_id']; ?>" data-return-id="<?php echo $row['return_id']; ?>" data-ref="<?php echo htmlspecialchars($row['return_reference']); ?>" data-name="<?php echo htmlspecialchars($row['product_name']); ?>" style="cursor: pointer;">
                            </td>
                        <?php endif; ?>
                        <td style="text-align: center; font-weight: 600;"><?php echo $i; ?></td>
                        <td>
                            <strong style="font-family: monospace; color: #dc2626; font-size: 13px;"><?php echo htmlspecialchars($row['return_reference']); ?></strong>
                        </td>
                        <td>
                            <div style="font-size: 12px; color: #334155; font-weight: 600;"><?php echo date('M d, Y', strtotime($row['return_date'])); ?></div>
                            <div style="font-size: 11px; color: #64748b;"><?php echo date('h:i A', strtotime($row['return_date'])); ?></div>
                        </td>
                        <td>
                            <strong style="font-family: monospace; color: #0284c7; font-size: 12.5px;"><?php echo htmlspecialchars($row['payment_id']); ?></strong>
                        </td>
                        <td>
                            <div style="font-weight: 700; color: #0f172a;"><?php echo htmlspecialchars($row['customer_name']); ?></div>
                            <?php if (!empty($row['customer_phone'])): ?>
                                <div style="font-size: 11px; color: #64748b;"><i class="fa fa-phone"></i> <?php echo htmlspecialchars($row['customer_phone']); ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($is_special): ?>
                                <span class="label label-warning" style="background-color: #d97706; font-size: 9px; padding: 1px 4px; font-weight: bold; text-transform: uppercase;">SPECIAL ORDER</span><br>
                            <?php endif; ?>
                            <strong><?php echo htmlspecialchars($row['product_name']); ?></strong>
                            <?php if ($is_special && !empty($row['product_details'])): ?>
                                <div style="font-size: 11px; color: #475569; margin-top: 1px;"><?php echo htmlspecialchars($row['product_details']); ?></div>
                            <?php endif; ?>
                            <div style="font-size: 11px; color: #64748b; font-family: monospace;">
                                <?php if ($is_special && !empty($row['special_order_reference'])): ?>
                                    Ref: <?php echo htmlspecialchars($row['special_order_reference']); ?>
                                <?php elseif (!empty($row['sku'])): ?>
                                    SKU: <?php echo htmlspecialchars($row['sku']); ?>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td style="text-align: center; font-weight: 800; font-size: 13.5px; color: #991b1b;">
                            <?php echo (int)$row['quantity_returned']; ?>
                        </td>
                        <td style="text-align: right; color: #475569;">
                            &#8369;<?php echo number_format($row['item_unit_price'], 2); ?>
                        </td>
                        <td style="text-align: right; font-weight: 800; font-size: 14px; color: #dc2626;">
                            &#8369;<?php echo number_format($row['item_refund'], 2); ?>
                        </td>
                        <td>
                            <?php
                            $cond = $row['condition'];
                            $badge_class = 'label-default';
                            if ($cond === 'Good / Resalable' || $cond === 'Resellable' || $cond === 'Unopened') $badge_class = 'label-success';
                            elseif ($cond === 'Damaged' || $cond === 'Defective') $badge_class = 'label-danger';
                            elseif ($cond === 'Opened' || $cond === 'Used' || $cond === 'Needs Inspection') $badge_class = 'label-warning';
                            ?>
                            <span class="label <?php echo $badge_class; ?>" style="font-size: 10px; padding: 2px 5px; display: inline-block; margin-bottom: 2px;">
                                <?php echo htmlspecialchars($cond); ?>
                            </span>
                            <div style="font-size: 11px; color: #334155;">
                                <?php echo htmlspecialchars($row['return_reason']); ?>
                            </div>
                        </td>
                        <td>
                            <?php if ($is_restocked): ?>
                                <span class="label label-success" style="background: #16a34a; font-size: 10px; padding: 2px 5px;" title="Stock added back to product inventory">
                                    <i class="fa fa-check"></i> Restocked (+<?php echo (int)$row['quantity_returned']; ?>)
                                </span>
                            <?php else: ?>
                                <span class="label label-default" style="background: #94a3b8; font-size: 10px; padding: 2px 5px;" title="Non-sellable / Special order; stock not restored">
                                    <i class="fa fa-ban"></i> Not Restocked
                                </span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align: center;">
                            <?php if ($is_pending): ?>
                                <span class="label label-warning" style="font-size: 10px; background-color: #f59e0b; padding: 3px 6px;"><i class="fa fa-clock-o"></i> PENDING</span>
                            <?php elseif ($is_completed): ?>
                                <span class="label label-success" style="font-size: 10px; background-color: #10b981; padding: 3px 6px;"><i class="fa fa-check"></i> COMPLETED</span>
                            <?php elseif ($is_rejected): ?>
                                <span class="label label-danger" style="font-size: 10px; background-color: #ef4444; padding: 3px 6px;"><i class="fa fa-times"></i> REJECTED</span>
                            <?php else: ?>
                                <span class="label label-default" style="font-size: 10px;"><?php echo htmlspecialchars($ret_status); ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div style="font-size: 11px; color: #334155;">
                                Requester: <strong><?php echo htmlspecialchars($row['requested_by_name'] ?: $row['processed_by'] ?: 'Staff'); ?></strong>
                            </div>
                            <?php if (!empty($row['approver_name'])): ?>
                                <div style="font-size: 10.5px; color: #0284c7; margin-top: 2px;">
                                    <i class="fa fa-shield"></i> Approver: <strong><?php echo htmlspecialchars($row['approver_name']); ?></strong>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($row['rejection_reason'])): ?>
                                <div style="font-size: 10.5px; color: #dc2626; margin-top: 2px;">
                                    <em>"<?php echo htmlspecialchars($row['rejection_reason']); ?>"</em>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td style="text-align: center; white-space: nowrap;">
                            <button type="button" class="btn btn-default btn-xs" onclick='openReturnSlipModal(<?php echo json_encode($row); ?>)' title="View & Print Official Return Slip">
                                <i class="fa fa-print text-danger"></i> Slip
                            </button>
                            <?php if ($is_admin): ?>
                                <button type="button" class="btn btn-danger btn-xs" style="margin-left: 3px;" onclick="deleteSingleReturn(<?php echo (int)$row['return_item_id']; ?>, <?php echo (int)$row['return_id']; ?>, '<?php echo htmlspecialchars(addslashes($row['return_reference'])); ?>', '<?php echo htmlspecialchars(addslashes($row['product_name'])); ?>')" title="Delete this Returned Transaction Log (Admin Only)">
                                    <i class="fa fa-trash"></i> Delete
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</section>

<!-- Return Slip Modal -->
<div class="modal fade" id="returnsPageSlipModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document" style="max-width: 550px;">
        <div class="modal-content" style="border-radius: 8px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.3);">
            <div class="modal-header" style="background-color: #dc2626; color: #fff; padding: 14px 18px;">
                <button type="button" class="close" data-dismiss="modal" style="color: #fff; opacity: 0.9;">&times;</button>
                <h4 class="modal-title" style="font-weight: bold; font-size: 16px;">
                    <i class="fa fa-file-text-o"></i> Official Return & Refund Receipt
                </h4>
            </div>
            <div class="modal-body" id="returnsPageSlipContent" style="padding: 20px; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; color: #333;">
            </div>
            <div class="modal-footer" style="background: #f8fafc; border-top: 1px solid #e2e8f0; display: flex; justify-content: space-between;">
                <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                <button type="button" class="btn btn-danger" onclick="printPageReturnSlip()"><i class="fa fa-print"></i> Print Slip</button>
            </div>
        </div>
    </div>
</div>

<script>
const supplierStoreName = "<?php echo addslashes($supp_info['supplier_name'] ?: 'E-Construction Supply'); ?>";
const supplierStoreAddress = "<?php echo addslashes($supp_info['supplier_address'] ?: ''); ?>";
const supplierStorePhone = "<?php echo addslashes($supp_info['supplier_phone'] ?: ''); ?>";

// -------------------------------------------------------------
// Admin Returned Transaction Logs Deletion Handlers & Flash Alerts
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

$(document).ready(function() {
    const flashMsg = sessionStorage.getItem('flash_message');
    const flashType = sessionStorage.getItem('flash_type') || 'success';
    if (flashMsg) {
        sessionStorage.removeItem('flash_message');
        sessionStorage.removeItem('flash_type');
        showPageAlert(flashMsg, flashType);
    }
});

function updateSelectedState() {
    let checkedBoxes;
    if ($.fn.DataTable && $.fn.DataTable.isDataTable('#example1')) {
        checkedBoxes = $('#example1').DataTable().$('input.ret-checkbox:checked');
    } else {
        checkedBoxes = $('input.ret-checkbox:checked');
    }
    const count = checkedBoxes.length;
    $('.selectedCountText').text(count);

    if (count > 0) {
        $('.btnDeleteSelectedTop, .btnDeleteSelectedTable').show();
    } else {
        $('.btnDeleteSelectedTop, .btnDeleteSelectedTable').hide();
    }
}

$(document).on('change', '.ret-checkbox', function() {
    updateSelectedState();
});

$(document).on('change', '#selectAllReturns', function() {
    const isChecked = this.checked;
    if ($.fn.DataTable && $.fn.DataTable.isDataTable('#example1')) {
        $('#example1').DataTable().$('input.ret-checkbox').prop('checked', isChecked);
    } else {
        $('input.ret-checkbox').prop('checked', isChecked);
    }
    updateSelectedState();
});

function deleteSelectedReturns() {
    let checkedBoxes;
    if ($.fn.DataTable && $.fn.DataTable.isDataTable('#example1')) {
        checkedBoxes = $('#example1').DataTable().$('input.ret-checkbox:checked');
    } else {
        checkedBoxes = $('input.ret-checkbox:checked');
    }

    const selectedIds = [];
    checkedBoxes.each(function() {
        selectedIds.push($(this).val());
    });

    if (selectedIds.length === 0) {
        showPageAlert('Please select at least one returned transaction log item to delete.', 'warning');
        return;
    }

    if (confirm(`Are you sure you want to delete ${selectedIds.length} selected returned transaction log item(s)?\n\nThis action cannot be undone.`)) {
        $.post('pos-return-api.php', {
            action: 'delete_return_logs',
            return_item_ids: selectedIds
        }, function(res) {
            if (res.status === 'success') {
                sessionStorage.setItem('flash_message', res.message || 'Selected returned transaction log(s) successfully deleted.');
                sessionStorage.setItem('flash_type', 'success');
                location.reload();
            } else {
                showPageAlert(res.message || 'Failed to delete return transaction log(s).', 'danger');
            }
        }, 'json').fail(function() {
            showPageAlert('Server error while deleting return transaction log(s).', 'danger');
        });
    }
}

function deleteSingleReturn(returnItemId, returnId, returnRef, prodName) {
    if (confirm(`Are you sure you want to delete the returned transaction log for "${prodName}" (Ref: ${returnRef})?\n\nThis action cannot be undone.`)) {
        $.post('pos-return-api.php', {
            action: 'delete_return_logs',
            return_item_ids: [returnItemId],
            return_id: returnId
        }, function(res) {
            if (res.status === 'success') {
                sessionStorage.setItem('flash_message', res.message || 'Returned transaction log deleted successfully.');
                sessionStorage.setItem('flash_type', 'success');
                location.reload();
            } else {
                showPageAlert(res.message || 'Failed to delete returned transaction log.', 'danger');
            }
        }, 'json').fail(function() {
            showPageAlert('Server error while deleting returned transaction log.', 'danger');
        });
    }
}


function openReturnSlipModal(data) {
    const isSpecial = (data.item_type === 'SPECIAL_ORDER' || parseInt(data.product_id) === 0);
    const isRestocked = (data.restock_status === 'RESTOCKED');
    
    let specialTag = isSpecial ? '<span style="background: #d97706; color: #fff; font-size: 9px; font-weight: bold; padding: 1px 4px; border-radius: 3px; text-transform: uppercase;">SPECIAL ORDER</span><br>' : '';
    let detailsHtml = (isSpecial && data.product_details) ? `<div style="font-size: 11px; color: #475569; margin-top: 2px;">${escapeHtml(data.product_details)}</div>` : '';
    let refOrSku = isSpecial 
        ? (data.special_order_reference ? `Ref: ${escapeHtml(data.special_order_reference)}` : '')
        : (data.sku ? `SKU: ${escapeHtml(data.sku)}` : '');

    let restockText = isRestocked 
        ? '<span style="color: #15803d; font-weight: bold;">Restocked (+ ' + data.quantity_returned + ' units to active inventory)</span>'
        : '<span style="color: #b45309; font-weight: bold;">Not Restocked (' + escapeHtml(data.condition) + ')</span>';

    const slipHtml = `
        <div style="text-align: center; margin-bottom: 12px;">
            <h3 style="margin: 0; color: #1e3a8a; font-weight: bold; letter-spacing: 0.5px;">E-CONSTRUCTION SUPPLY</h3>
            <p style="margin: 2px 0 0 0; font-size: 12px; color: #64748b;">Supplier Store: <strong>${escapeHtml(supplierStoreName)}</strong></p>
            ${supplierStoreAddress ? `<p style="margin: 1px 0 0 0; font-size: 11px; color: #64748b;">${escapeHtml(supplierStoreAddress)} | Tel: ${escapeHtml(supplierStorePhone)}</p>` : ''}
            <hr style="margin: 10px 0; border: 0; border-top: 1px dashed #cbd5e1;">
            <h4 style="margin: 0; font-size: 15px; font-weight: bold; color: #dc2626; text-transform: uppercase; letter-spacing: 0.5px;">
                Official Return & Refund Slip
            </h4>
        </div>

        <table style="width: 100%; font-size: 12px; margin-bottom: 12px;">
            <tr>
                <td style="width: 50%; vertical-align: top; line-height: 1.4;">
                    <strong style="color: #475569; font-size: 10px; text-transform: uppercase;">Return Information:</strong><br>
                    <strong>Return Ref:</strong> <span style="font-family: monospace; color: #dc2626; font-weight: bold;">${escapeHtml(data.return_reference)}</span><br>
                    <strong>Date:</strong> ${escapeHtml(data.return_date)}<br>
                    <strong>Processed By:</strong> ${escapeHtml(data.processed_by || 'Cashier')}
                </td>
                <td style="width: 50%; vertical-align: top; text-align: right; line-height: 1.4;">
                    <strong style="color: #475569; font-size: 10px; text-transform: uppercase;">Original Transaction:</strong><br>
                    <strong>Invoice #:</strong> <span style="font-family: monospace; color: #0284c7; font-weight: bold;">${escapeHtml(data.payment_id)}</span><br>
                    <strong>Customer:</strong> ${escapeHtml(data.customer_name)}<br>
                    ${data.customer_phone ? `<strong>Phone:</strong> ${escapeHtml(data.customer_phone)}` : ''}
                </td>
            </tr>
        </table>

        <table style="width: 100%; border-collapse: collapse; font-size: 12px; margin-bottom: 12px;">
            <thead>
                <tr style="background: #f1f5f9; border-top: 1px solid #cbd5e1; border-bottom: 1px solid #cbd5e1;">
                    <th style="padding: 6px 4px; text-align: left;">Item Returned</th>
                    <th style="padding: 6px 4px; text-align: center; width: 45px;">Qty</th>
                    <th style="padding: 6px 4px; text-align: right; width: 75px;">Unit Price</th>
                    <th style="padding: 6px 4px; text-align: right; width: 85px;">Refund Total</th>
                </tr>
            </thead>
            <tbody>
                <tr style="border-bottom: 1px solid #f1f5f9;">
                    <td style="padding: 8px 4px;">
                        ${specialTag}
                        <strong style="color: #0f172a;">${escapeHtml(data.product_name)}</strong>
                        ${detailsHtml}
                        ${refOrSku ? `<div style="font-size: 10.5px; color: #64748b; font-family: monospace; margin-top: 1px;">${refOrSku}</div>` : ''}
                    </td>
                    <td style="padding: 8px 4px; text-align: center; font-weight: bold; color: #991b1b;">${data.quantity_returned}</td>
                    <td style="padding: 8px 4px; text-align: right;">₱${parseFloat(data.item_unit_price || data.unit_price).toFixed(2)}</td>
                    <td style="padding: 8px 4px; text-align: right; font-weight: bold; color: #dc2626;">₱${parseFloat(data.item_refund || data.refund_amount).toFixed(2)}</td>
                </tr>
            </tbody>
            <tfoot>
                <tr style="background: #fef2f2; border-top: 2px solid #fecaca;">
                    <td colspan="2" style="padding: 8px 4px; font-weight: 700; color: #991b1b;">TOTAL REFUND:</td>
                    <td colspan="2" style="padding: 8px 4px; text-align: right; font-size: 16px; font-weight: 900; color: #dc2626;">₱${parseFloat(data.item_refund || data.refund_amount).toFixed(2)}</td>
                </tr>
            </tfoot>
        </table>

        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 10px 12px; font-size: 12px; line-height: 1.5; margin-bottom: 15px;">
            <div><strong>Refund Method:</strong> <span style="font-weight: 700; color: #1e3a8a;">${escapeHtml(data.refund_method)}</span></div>
            <div><strong>Return Reason:</strong> ${escapeHtml(data.return_reason)}</div>
            <div><strong>Item Condition:</strong> ${escapeHtml(data.condition)}</div>
            <div><strong>Inventory Status:</strong> ${restockText}</div>
            ${data.notes ? `<div><strong>Notes:</strong> ${escapeHtml(data.notes)}</div>` : ''}
        </div>

        <div style="text-align: center; margin-top: 15px; font-size: 11px; color: #64748b;">
            <p style="margin: 0; font-weight: bold; color: #334155;">Customer Return Acknowledged</p>
            <p style="margin: 2px 0 0 0; font-size: 10px; color: #94a3b8;">This document confirms that the above item was returned and refunded.</p>
        </div>
    `;

    document.getElementById('returnsPageSlipContent').innerHTML = slipHtml;
    $('#returnsPageSlipModal').modal('show');
}

function printPageReturnSlip() {
    const printContent = document.getElementById('returnsPageSlipContent').innerHTML;
    const printWindow = window.open('', '_blank', 'width=750,height=800');
    if (!printWindow) {
        showPageAlert('Print popup was blocked by browser. Please allow popups for this site.', 'warning');
        return;
    }
    printWindow.document.write(`
        <html>
        <head>
            <title>Official Return & Refund Slip</title>
            <style>
                body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; padding: 20px; color: #333; margin: 0; }
                table { width: 100%; border-collapse: collapse; }
                @media print {
                    body { padding: 0; }
                    @page { margin: 10mm; }
                }
            </style>
        </head>
        <body>
            ${printContent}
        </body>
        </html>
    `);
    printWindow.document.close();
    printWindow.focus();
    setTimeout(() => {
        printWindow.print();
        printWindow.close();
    }, 350);
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.innerText = text;
    return div.innerHTML;
}
</script>

<?php require_once('footer.php'); ?>

