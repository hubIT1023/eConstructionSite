<?php require_once('header.php'); ?>

<?php
$supplier_id = (int)$_SESSION['supplier_user']['supplier_id'];

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

$where_sql = implode(' AND ', $where_clauses);

$sql = "SELECT r.*, ri.return_item_id, ri.order_item_id, ri.product_id, ri.product_name, ri.sku, ri.size, ri.color, ri.item_type, ri.special_order_reference, ri.product_details, ri.quantity_returned, ri.unit_price as item_unit_price, ri.refund_amount as item_refund, ri.return_reason, ri.condition, ri.restock_status, ri.notes as item_notes
        FROM tbl_returns r
        JOIN tbl_return_items ri ON r.return_id = ri.return_id
        WHERE {$where_sql}
        ORDER BY r.return_id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$returns = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Metrics
$total_returns_count = count($returns);
$total_refund_amount = 0;
$total_units_returned = 0;
$total_restocked_units = 0;
$total_non_restocked_units = 0;

foreach ($returns as $ret) {
    $total_refund_amount += (float)$ret['item_refund'];
    $total_units_returned += (int)$ret['quantity_returned'];
    if ($ret['restock_status'] === 'RESTOCKED') {
        $total_restocked_units += (int)$ret['quantity_returned'];
    } else {
        $total_non_restocked_units += (int)$ret['quantity_returned'];
    }
}

// Fetch Supplier Info for printable slips
$stmt_s = $pdo->prepare("SELECT supplier_name, supplier_address, supplier_phone FROM tbl_supplier WHERE supplier_id=?");
$stmt_s->execute(array($supplier_id));
$supp_info = $stmt_s->fetch(PDO::FETCH_ASSOC);
?>

<section class="content-header">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap;">
        <h1>
            <i class="fa fa-undo" style="color: #dc2626;"></i> Return History & Refunds
            <small>Auditable log of processed item returns, restocked inventory, and customer refunds</small>
        </h1>
        <div>
            <a href="pos.php" class="btn btn-primary btn-sm"><i class="fa fa-calculator"></i> Go to POS</a>
            <a href="paid-orders.php" class="btn btn-default btn-sm"><i class="fa fa-check-square-o"></i> Paid Orders</a>
            <a href="sales-report.php" class="btn btn-default btn-sm"><i class="fa fa-line-chart"></i> Sales Report</a>
        </div>
    </div>
</section>

<section class="content">

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
        <div class="box-header with-border" style="padding: 12px 18px;">
            <h3 class="box-title" style="font-weight: 700; color: #1e293b; font-size: 16px;">
                <i class="fa fa-table text-danger"></i> Return Transactions Log
            </h3>
        </div>

        <div class="box-body table-responsive" style="padding: 10px 18px;">
            <table id="example1" class="table table-bordered table-striped table-hover" style="font-size: 13px;">
                <thead>
                    <tr style="background: #f8fafc;">
                        <th style="width: 40px; text-align: center;">#</th>
                        <th>Return Ref</th>
                        <th>Date & Time</th>
                        <th>Original Invoice</th>
                        <th>Customer</th>
                        <th>Product / Item Returned</th>
                        <th style="text-align: center;">Qty</th>
                        <th style="text-align: right;">Unit Price</th>
                        <th style="text-align: right;">Refund Amount</th>
                        <th>Reason</th>
                        <th>Condition</th>
                        <th>Restock Action</th>
                        <th>Method</th>
                        <th>Processed By</th>
                        <th style="text-align: center;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($returns) > 0): ?>
                        <?php 
                        $i = 0;
                        foreach ($returns as $row): 
                            $i++;
                            $is_special = ($row['item_type'] === 'SPECIAL_ORDER' || (int)$row['product_id'] === 0);
                            $is_restocked = ($row['restock_status'] === 'RESTOCKED');
                        ?>
                        <tr>
                            <td style="text-align: center; font-weight: 600;"><?php echo $i; ?></td>
                            <td>
                                <strong style="font-family: monospace; color: #dc2626; font-size: 13.5px;"><?php echo htmlspecialchars($row['return_reference']); ?></strong>
                            </td>
                            <td>
                                <div style="font-size: 12px; color: #334155; font-weight: 600;"><?php echo date('M d, Y', strtotime($row['return_date'])); ?></div>
                                <div style="font-size: 11px; color: #64748b;"><?php echo date('h:i A', strtotime($row['return_date'])); ?></div>
                            </td>
                            <td>
                                <strong style="font-family: monospace; color: #0284c7; font-size: 13px;"><?php echo htmlspecialchars($row['payment_id']); ?></strong>
                            </td>
                            <td>
                                <div style="font-weight: 700; color: #0f172a;"><?php echo htmlspecialchars($row['customer_name']); ?></div>
                                <?php if (!empty($row['customer_phone'])): ?>
                                    <div style="font-size: 11px; color: #64748b;"><i class="fa fa-phone"></i> <?php echo htmlspecialchars($row['customer_phone']); ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($is_special): ?>
                                    <span class="label label-warning" style="background-color: #d97706; font-size: 9px; padding: 2px 5px; font-weight: bold; text-transform: uppercase;">SPECIAL ORDER</span><br>
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
                            <td style="text-align: center; font-weight: 800; font-size: 14px; color: #991b1b;">
                                <?php echo (int)$row['quantity_returned']; ?>
                            </td>
                            <td style="text-align: right; color: #475569;">
                                &#8369;<?php echo number_format($row['item_unit_price'], 2); ?>
                            </td>
                            <td style="text-align: right; font-weight: 800; font-size: 14px; color: #dc2626;">
                                &#8369;<?php echo number_format($row['item_refund'], 2); ?>
                            </td>
                            <td>
                                <span style="font-weight: 600; color: #334155; font-size: 12px;"><?php echo htmlspecialchars($row['return_reason']); ?></span>
                            </td>
                            <td>
                                <?php
                                $cond = $row['condition'];
                                $badge_class = 'label-default';
                                if ($cond === 'Resellable' || $cond === 'Unopened') $badge_class = 'label-success';
                                elseif ($cond === 'Damaged' || $cond === 'Defective') $badge_class = 'label-danger';
                                elseif ($cond === 'Opened' || $cond === 'Used' || $cond === 'Needs Inspection') $badge_class = 'label-warning';
                                ?>
                                <span class="label <?php echo $badge_class; ?>" style="font-size: 11px; padding: 3px 6px;">
                                    <?php echo htmlspecialchars($cond); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($is_restocked): ?>
                                    <span class="label label-success" style="background: #16a34a; font-size: 10.5px; padding: 3px 6px;" title="Stock added back to product inventory">
                                        <i class="fa fa-check"></i> Restocked (+<?php echo (int)$row['quantity_returned']; ?>)
                                    </span>
                                <?php else: ?>
                                    <span class="label label-default" style="background: #94a3b8; font-size: 10.5px; padding: 3px 6px;" title="Non-sellable / Special order; stock not restored">
                                        <i class="fa fa-ban"></i> Not Restocked
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span style="font-size: 11.5px; font-weight: 600; color: #334155;"><?php echo htmlspecialchars($row['refund_method']); ?></span>
                            </td>
                            <td>
                                <span style="font-size: 12px; color: #475569;"><?php echo htmlspecialchars($row['processed_by']); ?></span>
                            </td>
                            <td style="text-align: center;">
                                <button type="button" class="btn btn-default btn-xs" onclick='openReturnSlipModal(<?php echo json_encode($row); ?>)' title="View & Print Official Return Slip">
                                    <i class="fa fa-print text-danger"></i> Slip
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="15" class="text-center text-muted" style="padding: 40px 10px;">
                                <i class="fa fa-folder-open-o fa-3x" style="color: #cbd5e1;"></i>
                                <p style="margin-top: 10px; font-size: 14px;">No return records found matching the specified criteria.</p>
                            </td>
                        </tr>
                    <?php endif; ?>
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
