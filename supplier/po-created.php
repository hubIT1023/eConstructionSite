<?php require_once('header.php'); ?>

<?php
// Multi-tenant isolation & authentication
$supplier_id = (int)$_SESSION['supplier_user']['supplier_id'];
$user_role_raw = isset($_SESSION['supplier_user']['role']) ? $_SESSION['supplier_user']['role'] : 'USER';
$user_role = normalize_supplier_role($user_role_raw);

// Role Authorization Check:
// Allowed: ORDER_PROCESSING, ENCODER, OPERATOR, SUPERVISOR, MANAGER, ADMIN
$allowed_roles = ['ORDER_PROCESSING', 'ENCODER', 'OPERATOR', 'SUPERVISOR', 'MANAGER', 'ADMIN'];
if (!in_array($user_role, $allowed_roles) && !is_admin_or_manager_role($user_role)) {
    // If cashier without PO view permissions, redirect safely to pos.php
    header('Location: pos.php');
    exit;
}

// Fetch Supplier Store Profile for Printable PO Vouchers
$statement_s = $pdo->prepare("SELECT * FROM tbl_supplier WHERE supplier_id=?");
$statement_s->execute(array($supplier_id));
$current_supplier_data = $statement_s->fetch(PDO::FETCH_ASSOC);
$store_name = $current_supplier_data['supplier_name'] ?? 'E-Construction Supply Store';
$store_address = $current_supplier_data['supplier_address'] ?? '';
$store_phone = $current_supplier_data['supplier_phone'] ?? '';
$store_email = $current_supplier_data['supplier_email'] ?? '';

// Helper for customer avatar initials
if (!function_exists('get_po_customer_initials')) {
    function get_po_customer_initials($name) {
        $parts = explode(' ', trim($name ?? ''));
        $initials = '';
        foreach ($parts as $p) {
            if (!empty($p)) {
                $initials .= strtoupper(substr($p, 0, 1));
                if (strlen($initials) >= 2) break;
            }
        }
        return $initials ?: 'C';
    }
}

// Helper for relative time
if (!function_exists('get_po_relative_time')) {
    function get_po_relative_time($datetime_str) {
        if (empty($datetime_str)) return '';
        $ts = strtotime($datetime_str);
        if (!$ts) return $datetime_str;
        $diff = time() - $ts;
        if ($diff < 60) return 'Just now';
        if ($diff < 3600) return floor($diff / 60) . ' mins ago';
        if ($diff < 86400) return floor($diff / 3600) . ' hrs ago';
        if ($diff < 172800) return 'Yesterday';
        return date('M d, Y', $ts);
    }
}

// -------------------------------------------------------------
// Filter Parameters: Period (All, Day, Week, Month) & Search
// STRICTLY Awaiting for Payment (UNPAID)
// -------------------------------------------------------------
$period_filter = isset($_GET['period']) ? trim($_GET['period']) : 'all';
$search_query = isset($_GET['q']) ? trim($_GET['q']) : '';

$current_year = (int)date('Y');
$current_month = (int)date('m');
$current_week = (int)date('W');
$today_date = date('Y-m-d');

$selected_day = isset($_GET['day_date']) && !empty($_GET['day_date']) ? $_GET['day_date'] : $today_date;
$selected_year = isset($_GET['year']) && !empty($_GET['year']) ? (int)$_GET['year'] : $current_year;
$selected_month = isset($_GET['month']) && !empty($_GET['month']) ? (int)$_GET['month'] : $current_month;
$selected_week = isset($_GET['week']) && !empty($_GET['week']) ? (int)$_GET['week'] : $current_week;

// Build Query: ONLY display those payment status is 'Awaiting for Payment' or 'Pending' (Paid / Completed excluded)
$where_clauses = [
    "supplier_id = ?",
    "(payment_status = 'Awaiting for Payment' OR payment_status = 'Pending' OR payment_status = 'UNPAID')",
    "payment_status != 'Paid'",
    "payment_status != 'Completed'"
];
$query_params = [$supplier_id];

$period_label = "All Pending POs";

if ($period_filter === 'day') {
    $start_dt = $selected_day . ' 00:00:00';
    $end_dt = $selected_day . ' 23:59:59';
    $where_clauses[] = "(payment_date >= ? AND payment_date <= ?)";
    $query_params[] = $start_dt;
    $query_params[] = $end_dt;
    $period_label = "Today: " . date('F d, Y', strtotime($selected_day));
} elseif ($period_filter === 'week') {
    $dto = new DateTime();
    $dto->setISODate($selected_year, $selected_week);
    $week_start_date = $dto->format('Y-m-d');
    $dto->modify('+6 days');
    $week_end_date = $dto->format('Y-m-d');
    
    $where_clauses[] = "(payment_date >= ? AND payment_date <= ?)";
    $query_params[] = $week_start_date . ' 00:00:00';
    $query_params[] = $week_end_date . ' 23:59:59';
    $period_label = "Week $selected_week, $selected_year";
} elseif ($period_filter === 'month') {
    $m_str = str_pad($selected_month, 2, '0', STR_PAD_LEFT);
    $days_in_m = date('t', strtotime("$selected_year-$m_str-01"));
    $month_start_dt = "$selected_year-$m_str-01 00:00:00";
    $month_end_dt = "$selected_year-$m_str-$days_in_m 23:59:59";

    $where_clauses[] = "(payment_date >= ? AND payment_date <= ?)";
    $query_params[] = $month_start_dt;
    $query_params[] = $month_end_dt;
    $period_label = "Month: " . date('F Y', strtotime("$selected_year-$m_str-01"));
}

if (!empty($search_query)) {
    $where_clauses[] = "(payment_id ILIKE ? OR customer_name ILIKE ? OR customer_email ILIKE ? OR bank_transaction_info ILIKE ?)";
    $kw = '%' . $search_query . '%';
    $query_params[] = $kw;
    $query_params[] = $kw;
    $query_params[] = $kw;
    $query_params[] = $kw;
}

$where_sql = implode(" AND ", $where_clauses);
$sql_query = "SELECT * FROM tbl_payment WHERE $where_sql ORDER BY id DESC";

$statement = $pdo->prepare($sql_query);
$statement->execute($query_params);
$pending_pos = $statement->fetchAll(PDO::FETCH_ASSOC);
$total_pending_count = count($pending_pos);

// Calculate total pending amount
$total_pending_amount = 0;
foreach ($pending_pos as $po) {
    $total_pending_amount += floatval($po['paid_amount']);
}
?>

<style>
/* Modern POS & PO Management Styles */
.po-created-header {
    margin-bottom: 20px;
}
.po-stat-card {
    background: #ffffff;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
    padding: 16px 20px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 18px;
}
.po-stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
}
.po-stat-number {
    font-size: 22px;
    font-weight: 800;
    color: #0f172a;
    line-height: 1.2;
}
.po-stat-title {
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    color: #64748b;
    letter-spacing: 0.5px;
}
.po-table-panel {
    background: #ffffff;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 2px 6px rgba(0,0,0,0.04);
    overflow: hidden;
}
.po-table {
    margin-bottom: 0;
}
.po-table th {
    background: #f8fafc;
    color: #475569;
    font-size: 11.5px;
    text-transform: uppercase;
    font-weight: 800;
    letter-spacing: 0.5px;
    border-bottom: 2px solid #e2e8f0 !important;
    padding: 12px 14px !important;
}
.po-table td {
    vertical-align: middle !important;
    padding: 14px !important;
    border-top: 1px solid #f1f5f9 !important;
    font-size: 13px;
}
.po-badge-code {
    font-family: monospace;
    font-size: 14px;
    font-weight: 800;
    color: #0284c7;
    background: #f0f9ff;
    border: 1px solid #bae6fd;
    padding: 3px 8px;
    border-radius: 4px;
    display: inline-block;
}
.po-cust-avatar {
    width: 34px;
    height: 34px;
    border-radius: 50%;
    background: #e0f2fe;
    color: #0284c7;
    font-weight: 800;
    font-size: 13px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin-right: 8px;
    flex-shrink: 0;
}
.po-btn-view {
    background: #f1f5f9;
    color: #1e293b;
    border: 1px solid #cbd5e1;
    font-weight: 700;
    font-size: 12px;
    padding: 5px 10px;
    border-radius: 4px;
    transition: all 0.2s ease;
}
.po-btn-view:hover {
    background: #e2e8f0;
    color: #0f172a;
}
.po-btn-reprint {
    background: #0284c7;
    color: #ffffff;
    border: 1px solid #0369a1;
    font-weight: 700;
    font-size: 12px;
    padding: 5px 12px;
    border-radius: 4px;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.po-btn-reprint:hover {
    background: #0369a1;
    color: #ffffff;
}
.po-filter-pill {
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 700;
    color: #475569;
    background: #f1f5f9;
    border: 1px solid #e2e8f0;
    margin-right: 6px;
    margin-bottom: 6px;
    display: inline-block;
    text-decoration: none;
    transition: all 0.2s;
}
.po-filter-pill.active {
    background: #f59e0b;
    color: #ffffff;
    border-color: #d97706;
}
.po-filter-pill:hover {
    text-decoration: none;
    color: #0f172a;
    background: #e2e8f0;
}
.po-filter-pill.active:hover {
    color: #ffffff;
    background: #d97706;
}
</style>

<section class="content-header po-created-header">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
        <div>
            <h1 style="margin: 0; font-size: 22px; font-weight: 800; color: #0f172a;">
                <i class="fa fa-file-text-o" style="color: #f59e0b; margin-right: 6px;"></i> PO Created
                <small style="font-size: 13px; color: #64748b; font-weight: 600;">Purchase Orders Awaiting Cashier Payment</small>
            </h1>
        </div>
        <div style="display: flex; gap: 8px; align-items: center;">
            <a href="pos.php" class="btn btn-primary btn-sm" style="font-weight: 700; background-color: #0284c7; border-color: #0369a1; border-radius: 4px; padding: 6px 14px;">
                <i class="fa fa-calculator"></i> POS Terminal
            </a>
            <a href="po-created.php" class="btn btn-default btn-sm" style="font-weight: 700; border-radius: 4px; padding: 6px 12px;">
                <i class="fa fa-refresh"></i> Refresh
            </a>
        </div>
    </div>
</section>

<section class="content">

    <!-- Role Context & Workflow Explanation Alert -->
    <div style="background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%); border: 1.5px solid #fde68a; border-radius: 8px; padding: 14px 18px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
        <div style="display: flex; align-items: flex-start; gap: 12px;">
            <div style="background: #f59e0b; color: #fff; width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 16px; flex-shrink: 0; margin-top: 2px;">
                <i class="fa fa-info"></i>
            </div>
            <div>
                <strong style="color: #92400e; font-size: 14px;">Order Processing Staff &bull; Purchase Order Queue</strong>
                <p style="margin: 3px 0 0 0; font-size: 12.5px; color: #78350f; line-height: 1.5;">
                    This dashboard displays customer Purchase Orders that have been placed through the POS and are <strong>awaiting payment confirmation</strong> at the Cashier counter.
                    Once the Cashier accepts payment and marks the PO as <strong>PAID</strong>, the order will automatically clear from this pending queue while remaining permanently recorded in the system's order history.
                </p>
            </div>
        </div>
    </div>

    <!-- Summary Metric Cards -->
    <div class="row">
        <div class="col-sm-4 col-xs-12">
            <div class="po-stat-card">
                <div>
                    <div class="po-stat-title">Pending Purchase Orders</div>
                    <div class="po-stat-number"><?php echo number_format($total_pending_count); ?></div>
                    <div style="font-size: 11px; color: #d97706; font-weight: 700; margin-top: 2px;">
                        <i class="fa fa-clock-o"></i> Awaiting Cashier Payment
                    </div>
                </div>
                <div class="po-stat-icon" style="background: #fef3c7; color: #d97706;">
                    <i class="fa fa-file-text-o"></i>
                </div>
            </div>
        </div>
        <div class="col-sm-4 col-xs-12">
            <div class="po-stat-card">
                <div>
                    <div class="po-stat-title">Total Pending Value</div>
                    <div class="po-stat-number" style="color: #0284c7;">&#8369;<?php echo number_format($total_pending_amount, 2); ?></div>
                    <div style="font-size: 11px; color: #64748b; margin-top: 2px;">
                        Active receivables awaiting collection
                    </div>
                </div>
                <div class="po-stat-icon" style="background: #e0f2fe; color: #0284c7;">
                    <i class="fa fa-money"></i>
                </div>
            </div>
        </div>
        <div class="col-sm-4 col-xs-12">
            <div class="po-stat-card">
                <div>
                    <div class="po-stat-title">Current Role / Tenant</div>
                    <div class="po-stat-number" style="font-size: 17px; color: #15803d;"><?php echo htmlspecialchars(get_role_display_name($user_role)); ?></div>
                    <div style="font-size: 11px; color: #475569; margin-top: 2px;">
                        <?php echo htmlspecialchars($store_name); ?>
                    </div>
                </div>
                <div class="po-stat-icon" style="background: #dcfce7; color: #15803d;">
                    <i class="fa fa-building-o"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters & Search Toolbar -->
    <div class="box box-solid" style="border-radius: 8px; margin-bottom: 20px; border: 1px solid #e2e8f0;">
        <div class="box-body" style="padding: 15px 18px;">
            <div class="row" style="display: flex; align-items: center; flex-wrap: wrap; gap: 10px;">
                <div class="col-md-7 col-xs-12">
                    <span style="font-size: 12px; font-weight: 800; color: #475569; margin-right: 8px; text-transform: uppercase;">Filter:</span>
                    <a href="po-created.php?period=all<?php echo !empty($search_query) ? '&q='.urlencode($search_query) : ''; ?>" class="po-filter-pill <?php echo ($period_filter === 'all') ? 'active' : ''; ?>">
                        All Pending POs
                    </a>
                    <a href="po-created.php?period=day&day_date=<?php echo $today_date; ?><?php echo !empty($search_query) ? '&q='.urlencode($search_query) : ''; ?>" class="po-filter-pill <?php echo ($period_filter === 'day') ? 'active' : ''; ?>">
                        Today's POs
                    </a>
                    <a href="po-created.php?period=week&year=<?php echo $current_year; ?>&week=<?php echo $current_week; ?><?php echo !empty($search_query) ? '&q='.urlencode($search_query) : ''; ?>" class="po-filter-pill <?php echo ($period_filter === 'week') ? 'active' : ''; ?>">
                        This Week
                    </a>
                    <a href="po-created.php?period=month&year=<?php echo $current_year; ?>&month=<?php echo $current_month; ?><?php echo !empty($search_query) ? '&q='.urlencode($search_query) : ''; ?>" class="po-filter-pill <?php echo ($period_filter === 'month') ? 'active' : ''; ?>">
                        This Month
                    </a>
                </div>
                <div class="col-md-5 col-xs-12 text-right">
                    <form method="GET" action="po-created.php" class="form-inline" style="display: inline-flex; width: 100%; justify-content: flex-end; gap: 6px;">
                        <input type="hidden" name="period" value="<?php echo htmlspecialchars($period_filter); ?>">
                        <div class="input-group" style="width: 100%; max-width: 320px;">
                            <input type="text" name="q" class="form-control input-sm" placeholder="Search PO#, customer, phone..." value="<?php echo htmlspecialchars($search_query); ?>" style="border-radius: 4px 0 0 4px; height: 34px;">
                            <span class="input-group-btn">
                                <button type="submit" class="btn btn-primary btn-sm" style="height: 34px; border-radius: 0 4px 4px 0; background-color: #0284c7; border-color: #0369a1;">
                                    <i class="fa fa-search"></i>
                                </button>
                                <?php if (!empty($search_query)): ?>
                                <a href="po-created.php?period=<?php echo htmlspecialchars($period_filter); ?>" class="btn btn-default btn-sm" style="height: 34px; margin-left: 4px; border-radius: 4px;" title="Clear Search">
                                    <i class="fa fa-times text-danger"></i>
                                </a>
                                <?php endif; ?>
                            </span>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- PO Created Data Table -->
    <div class="po-table-panel">
        <?php if ($total_pending_count > 0): ?>
        <div class="table-responsive">
            <table class="table table-hover po-table">
                <thead>
                    <tr>
                        <th style="width: 50px;">#</th>
                        <th style="width: 170px;">PO Number</th>
                        <th style="width: 150px;">Date &amp; Time</th>
                        <th>Customer Details</th>
                        <th style="width: 180px;">Items Ordered</th>
                        <th style="width: 120px;">Fulfillment</th>
                        <th style="width: 130px; text-align: right;">Total Amount</th>
                        <th style="width: 140px; text-align: center;">Payment Status</th>
                        <th style="width: 170px; text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $idx = 0;
                    foreach ($pending_pos as $row): 
                        $idx++;
                        $po_id = $row['id'];
                        $po_code = $row['payment_id'] ?: ('PO-' . date('Ymd', strtotime($row['payment_date'])) . '-' . str_pad($po_id, 5, '0', STR_PAD_LEFT));
                        $cust_name = $row['customer_name'] ?: 'Walk-in Customer';
                        $cust_phone = $row['customer_phone'] ?? '';
                        $cust_email = $row['customer_email'] ?? '';
                        $cust_initials = get_po_customer_initials($cust_name);
                        $order_date = $row['payment_date'];
                        $total_amount = floatval($row['paid_amount']);
                        $bank_info = $row['bank_transaction_info'] ?? '';
                        $is_delivery = (stripos($bank_info, 'Delivery') !== false || stripos($row['shipping_status'], 'Delivery') !== false);

                        // Fetch items for this PO
                        $statement_items = $pdo->prepare("SELECT * FROM tbl_order WHERE payment_id=? AND supplier_id=?");
                        $statement_items->execute(array($row['payment_id'], $supplier_id));
                        $po_items = $statement_items->fetchAll(PDO::FETCH_ASSOC);
                        $items_count = count($po_items);
                        $total_qty = 0;
                        foreach ($po_items as $item) {
                            $total_qty += intval($item['quantity']);
                        }
                    ?>
                    <tr>
                        <td style="color: #64748b; font-weight: 700;"><?php echo $idx; ?></td>
                        <td>
                            <span class="po-badge-code"><?php echo htmlspecialchars($po_code); ?></span>
                        </td>
                        <td>
                            <strong style="color: #0f172a; font-size: 12.5px;"><?php echo date('M d, Y', strtotime($order_date)); ?></strong><br>
                            <span style="font-size: 11.5px; color: #64748b;"><?php echo date('h:i A', strtotime($order_date)); ?></span>
                            <span class="label" style="background: #f1f5f9; color: #475569; font-size: 10px; margin-left: 2px;"><?php echo get_po_relative_time($order_date); ?></span>
                        </td>
                        <td>
                            <div style="display: flex; align-items: center;">
                                <div class="po-cust-avatar"><?php echo $cust_initials; ?></div>
                                <div>
                                    <strong style="color: #1e293b; font-size: 13.5px;"><?php echo htmlspecialchars($cust_name); ?></strong>
                                    <?php if (!empty($cust_phone)): ?>
                                        <div style="font-size: 11.5px; color: #64748b;"><i class="fa fa-phone"></i> <?php echo htmlspecialchars($cust_phone); ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td>
                            <strong style="color: #0f172a;"><?php echo $items_count; ?> item<?php echo $items_count > 1 ? 's' : ''; ?></strong>
                            <span style="font-size: 11.5px; color: #64748b;">(<?php echo $total_qty; ?> total units)</span>
                            <?php if ($items_count > 0): ?>
                                <div style="font-size: 11px; color: #475569; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 170px;">
                                    <?php echo htmlspecialchars($po_items[0]['product_name']); ?><?php echo $items_count > 1 ? '...' : ''; ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($is_delivery): ?>
                                <span class="label" style="background-color: #0284c7; font-size: 11px; padding: 4px 8px; border-radius: 4px;">
                                    <i class="fa fa-truck"></i> Delivery
                                </span>
                            <?php else: ?>
                                <span class="label" style="background-color: #059669; font-size: 11px; padding: 4px 8px; border-radius: 4px;">
                                    <i class="fa fa-shopping-bag"></i> Store Pickup
                                </span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align: right;">
                            <strong style="font-size: 15px; font-weight: 800; color: #0284c7;">&#8369;<?php echo number_format($total_amount, 2); ?></strong>
                        </td>
                        <td style="text-align: center;">
                            <span class="label" style="background-color: #f59e0b; color: #fff; font-size: 11px; font-weight: 800; padding: 4px 8px; border-radius: 4px; text-transform: uppercase; letter-spacing: 0.3px;">
                                <i class="fa fa-clock-o"></i> UNPAID
                            </span>
                        </td>
                        <td style="text-align: center;">
                            <div style="display: inline-flex; gap: 4px;">
                                <button type="button" class="po-btn-view" data-toggle="modal" data-target="#poDetailModal-<?php echo $po_id; ?>" title="View Complete PO Details">
                                    <i class="fa fa-eye"></i> View
                                </button>
                                <button type="button" class="po-btn-reprint" onclick="reprintPOCreated('poPrintVoucher-<?php echo $po_id; ?>')" title="Reprint PO Reference Voucher for Customer">
                                    <i class="fa fa-print"></i> Reprint
                                </button>
                            </div>
                        </td>
                    </tr>

                    <!-- ========================================== -->
                    <!-- READ-ONLY PO DETAIL & REPRINT MODAL       -->
                    <!-- ========================================== -->
                    <div class="modal fade" id="poDetailModal-<?php echo $po_id; ?>" tabindex="-1" role="dialog" aria-labelledby="poModalLabel-<?php echo $po_id; ?>" aria-hidden="true">
                        <div class="modal-dialog" style="max-width: 650px; margin-top: 40px;">
                            <div class="modal-content" style="border-radius: 10px; overflow: hidden; box-shadow: 0 15px 40px rgba(0,0,0,0.3);">
                                <div class="modal-header" style="background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%); color: #fff; padding: 16px 22px;">
                                    <button type="button" class="close" data-dismiss="modal" aria-hidden="true" style="color: #fff; opacity: 0.9; font-size: 24px;">&times;</button>
                                    <h4 class="modal-title" id="poModalLabel-<?php echo $po_id; ?>" style="font-weight: 800; font-size: 17px; display: flex; align-items: center; gap: 8px;">
                                        <i class="fa fa-file-text-o"></i> Purchase Order Details &bull; <?php echo htmlspecialchars($po_code); ?>
                                    </h4>
                                </div>
                                <div class="modal-body" style="padding: 22px; font-size: 13px; color: #1e293b; background: #fff;">
                                    
                                    <!-- Status Alert -->
                                    <div style="background: #fffbeb; border: 1.5px solid #f59e0b; border-radius: 6px; padding: 12px 16px; margin-bottom: 16px; display: flex; align-items: center; justify-content: space-between;">
                                        <div>
                                            <div style="font-size: 11px; text-transform: uppercase; font-weight: 800; color: #b45309;">Payment Status</div>
                                            <div style="font-size: 14px; font-weight: 800; color: #d97706; margin-top: 2px;">
                                                <i class="fa fa-clock-o"></i> Awaiting for Payment (UNPAID)
                                            </div>
                                        </div>
                                        <div style="text-align: right; font-size: 11.5px; color: #78350f;">
                                            Customer must pay at the Cashier counter
                                        </div>
                                    </div>

                                    <!-- Printable PO Voucher Container (Read-Only) -->
                                    <div id="poPrintVoucher-<?php echo $po_id; ?>" style="background: #fff; border: 1px solid #e2e8f0; border-radius: 6px; padding: 16px;">
                                        
                                        <!-- Voucher Header -->
                                        <div style="text-align: center; border-bottom: 2px solid #0284c7; padding-bottom: 12px; margin-bottom: 14px;">
                                            <h3 style="margin: 0; font-size: 20px; font-weight: 900; color: #0f172a; text-transform: uppercase;">
                                                CUSTOMER PURCHASE ORDER VOUCHER
                                            </h3>
                                            <p style="margin: 2px 0 0 0; font-size: 12px; color: #64748b; font-weight: 600;">
                                                <?php echo htmlspecialchars($store_name); ?> &bull; Fulfillment Slip
                                            </p>
                                        </div>

                                        <!-- PO Reference Block -->
                                        <table style="width: 100%; font-size: 12px; margin-bottom: 14px; line-height: 1.5;">
                                            <tr>
                                                <td style="width: 50%; vertical-align: top; padding-right: 10px;">
                                                    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 10px;">
                                                        <strong style="color: #0369a1; font-size: 11px; text-transform: uppercase;">Purchase Order Number:</strong><br>
                                                        <span style="font-size: 16px; font-weight: 900; font-family: monospace; color: #0284c7;"><?php echo htmlspecialchars($po_code); ?></span><br>
                                                        <strong>Date:</strong> <?php echo date('M d, Y h:i A', strtotime($order_date)); ?><br>
                                                        <strong>Status:</strong> <span style="color: #d97706; font-weight: 800;">Awaiting Payment (UNPAID)</span>
                                                    </div>
                                                </td>
                                                <td style="width: 50%; vertical-align: top; padding-left: 10px;">
                                                    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 10px;">
                                                        <strong style="color: #0369a1; font-size: 11px; text-transform: uppercase;">Customer Information:</strong><br>
                                                        <strong><?php echo htmlspecialchars($cust_name); ?></strong><br>
                                                        <?php if (!empty($cust_phone)): ?>
                                                            Phone: <?php echo htmlspecialchars($cust_phone); ?><br>
                                                        <?php endif; ?>
                                                        Fulfillment: <strong><?php echo $is_delivery ? 'Delivery' : 'Store Pickup'; ?></strong>
                                                    </div>
                                                </td>
                                            </tr>
                                        </table>

                                        <!-- Order Items Table -->
                                        <table style="width: 100%; border-collapse: collapse; font-size: 12px; margin-bottom: 14px;">
                                            <thead>
                                                <tr style="background: #f1f5f9; border-top: 1.5px solid #cbd5e1; border-bottom: 1.5px solid #cbd5e1;">
                                                    <th style="padding: 7px 6px; text-align: left;">Product Item &amp; Details</th>
                                                    <th style="padding: 7px 6px; text-align: center; width: 50px;">Qty</th>
                                                    <th style="padding: 7px 6px; text-align: right; width: 90px;">Unit Price</th>
                                                    <th style="padding: 7px 6px; text-align: right; width: 100px;">Total</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php 
                                                $calculated_subtotal = 0;
                                                foreach ($po_items as $item): 
                                                    $item_qty = intval($item['quantity']);
                                                    $item_price = floatval($item['unit_price']);
                                                    $item_subtotal = $item_qty * $item_price;
                                                    $calculated_subtotal += $item_subtotal;
                                                    $is_sp = (isset($item['item_type']) && $item['item_type'] === 'SPECIAL_ORDER');
                                                ?>
                                                <tr style="border-bottom: 1px solid #f1f5f9; <?php echo $is_sp ? 'background-color: #fffbeb;' : ''; ?>">
                                                    <td style="padding: 7px 6px;">
                                                        <?php if ($is_sp): ?>
                                                            <span class="label label-warning" style="font-size: 9px; padding: 1px 4px;">SPECIAL ORDER</span><br>
                                                        <?php endif; ?>
                                                        <strong><?php echo htmlspecialchars($item['product_name']); ?></strong>
                                                        <?php if (!empty($item['size']) || !empty($item['color'])): ?>
                                                            <div style="font-size: 11px; color: #64748b;">
                                                                <?php if (!empty($item['size'])) echo 'Size: ' . htmlspecialchars($item['size']) . ' '; ?>
                                                                <?php if (!empty($item['color'])) echo 'Color: ' . htmlspecialchars($item['color']); ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td style="padding: 7px 6px; text-align: center; font-weight: 700;"><?php echo $item_qty; ?></td>
                                                    <td style="padding: 7px 6px; text-align: right;">&#8369;<?php echo number_format($item_price, 2); ?></td>
                                                    <td style="padding: 7px 6px; text-align: right; font-weight: 700;">&#8369;<?php echo number_format($item_subtotal, 2); ?></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                            <tfoot>
                                                <tr style="border-top: 2px solid #cbd5e1; background: #f8fafc; font-size: 14px;">
                                                    <td colspan="2" style="padding: 8px 6px;"></td>
                                                    <td style="padding: 8px 6px; text-align: right; font-weight: 800; color: #0284c7;">Total Amount Due:</td>
                                                    <td style="padding: 8px 6px; text-align: right; font-weight: 800; color: #0284c7;">&#8369;<?php echo number_format($total_amount, 2); ?></td>
                                                </tr>
                                            </tfoot>
                                        </table>

                                        <!-- Instruction Footer -->
                                        <div style="text-align: center; border-top: 1px dashed #cbd5e1; padding-top: 10px; font-size: 11px; color: #64748b;">
                                            <strong><i class="fa fa-info-circle text-info"></i> Cashier Instruction:</strong> Present this PO Voucher at the Cashier counter to process payment and receive your official sales receipt.
                                        </div>

                                    </div>

                                </div>
                                <div class="modal-footer" style="background: #f8fafc; border-top: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; padding: 12px 22px;">
                                    <span style="font-size: 11.5px; color: #64748b;">
                                        <i class="fa fa-lock text-muted"></i> Read-only mode &bull; No changes allowed
                                    </span>
                                    <div style="display: flex; gap: 6px;">
                                        <button type="button" class="btn btn-default btn-sm" data-dismiss="modal" style="font-weight: 700;">Close</button>
                                        <button type="button" class="btn btn-default btn-sm" onclick="reprintPOCreated('poPrintVoucher-<?php echo $po_id; ?>', 210)" style="font-weight: 600; background: #fff; border-color: #cbd5e1; color: #334155;" title="Print standard A4 / PDF Voucher">
                                            <i class="fa fa-file-pdf-o text-danger"></i> A4 / PDF
                                        </button>
                                        <button type="button" class="btn btn-primary btn-sm" onclick="reprintPOCreated('poPrintVoucher-<?php echo $po_id; ?>', 500)" style="font-weight: 700; background-color: #0284c7; border-color: #0369a1;" title="Print on 500mm Wide Thermal Roll (Primary Default Standard)">
                                            <i class="fa fa-print"></i> Reprint PO (500mm Thermal)
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <!-- Clean Empty State -->
        <div class="text-center" style="padding: 50px 20px; background: #fff; border-radius: 8px;">
            <div style="width: 70px; height: 70px; border-radius: 50%; background: #ecfdf5; color: #059669; font-size: 32px; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 16px;">
                <i class="fa fa-check"></i>
            </div>
            <h3 style="color: #0f172a; font-weight: 800; margin: 0 0 6px 0;">No Purchase Orders Awaiting Payment</h3>
            <p style="color: #64748b; font-size: 13.5px; max-width: 500px; margin: 0 auto 20px auto; line-height: 1.5;">
                <?php if (!empty($search_query)): ?>
                    No pending purchase orders matched your search "<strong><?php echo htmlspecialchars($search_query); ?></strong>".
                <?php else: ?>
                    All submitted purchase orders have been settled with the Cashier, or no new POs are currently pending.
                <?php endif; ?>
            </p>
            <div style="display: inline-flex; gap: 8px;">
                <?php if (!empty($search_query)): ?>
                <a href="po-created.php" class="btn btn-default btn-sm" style="font-weight: 700;">Clear Search</a>
                <?php endif; ?>
                <a href="pos.php" class="btn btn-primary btn-sm" style="font-weight: 700; background-color: #0284c7; border-color: #0369a1; padding: 6px 16px;">
                    <i class="fa fa-plus-circle"></i> Create New Order in POS
                </a>
            </div>
        </div>
        <?php endif; ?>
    </div>

</section>

<script>
// ==========================================
// REPRINT PO CREATED VOUCHER ENGINE
// ==========================================
function reprintPOCreated(elementId, requestedWidthMm) {
    const el = document.getElementById(elementId);
    if (!el) {
        alert('PO Voucher template not found.');
        return;
    }
    const rawContent = el.innerHTML;
    
    // Read user configured printer settings (500mm thermal / A4 / etc.)
    let widthMm = 500;
    let copies = 1;
    let isThermal = true;
    try {
        const saved = localStorage.getItem('pos_printer_settings');
        if (saved) {
            const parsed = JSON.parse(saved);
            widthMm = parseInt(parsed.paperWidthMm, 10) || 500;
            copies = Math.min(5, Math.max(1, parseInt(parsed.copies, 10) || 1));
            isThermal = (parsed.printerType !== 'normal' && parsed.printerMode !== 'normal');
        }
    } catch (e) {
        console.warn('Could not read pos_printer_settings', e);
    }

    if (requestedWidthMm) {
        widthMm = parseInt(requestedWidthMm, 10) || 500;
        isThermal = (widthMm !== 210);
    }

    const isA4 = (widthMm === 210 || (!isThermal && widthMm !== 500));
    const is500mm = (!isA4 && widthMm >= 450);
    const is58mm = (!isA4 && !is500mm && widthMm <= 65);
    const is80mm = (!isA4 && !is500mm && !is58mm);

    const actualWidthMm = isA4 ? 210 : (is500mm ? 500 : (is58mm ? 58 : 80));
    const bodyFontPt = isA4 ? 10.0 : (is500mm ? 12.0 : (is58mm ? 8.5 : 9.8));
    const titleFontPt = isA4 ? 14.0 : (is500mm ? 16.0 : (is58mm ? 10.5 : 12.0));

    let copiesHtml = '';
    for (let c = 0; c < copies; c++) {
        copiesHtml += '<div class="pos-print-page' + (c > 0 ? ' pos-page-break' : '') + '">' + rawContent + '</div>';
    }

    const printWin = window.open('', '_blank', 'width=850,height=900');
    if (!printWin) {
        alert('Print popup was blocked by browser. Please allow popups for this site.');
        return;
    }

    printWin.document.open();
    printWin.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <title>Customer Purchase Order Voucher (UNPAID) - ${actualWidthMm}mm</title>
            <style>
                * {
                    box-sizing: border-box;
                    -webkit-print-color-adjust: exact !important;
                    print-color-adjust: exact !important;
                }
                body {
                    font-family: ${!isA4 ? "'Courier New', Consolas, 'Liberation Mono', monospace, Arial, sans-serif" : "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif"} !important;
                    color: #000000 !important;
                    margin: 0;
                    padding: 0;
                    background: #ffffff !important;
                    font-size: ${bodyFontPt}pt !important;
                    line-height: 1.4 !important;
                    font-variant-numeric: tabular-nums;
                    font-weight: 500;
                }
                table { width: 100%; border-collapse: collapse; }
                th, td { vertical-align: top; padding: ${is500mm ? '6px 10px' : '4px 6px'}; color: #000000 !important; }
                th { font-weight: 800 !important; border-bottom: 1.5pt dashed #000000 !important; border-top: 1.5pt dashed #000000 !important; }
                .pos-page-break { page-break-before: always; margin-top: 25px; }

                @media screen {
                    body { padding: 25px; background: #f1f5f9; display: flex; justify-content: center; }
                    .pos-print-container {
                        width: ${actualWidthMm}mm;
                        max-width: 100%;
                        background: #ffffff;
                        padding: ${is500mm ? '12mm 16mm' : (isA4 ? '12mm 15mm' : '4mm')};
                        box-shadow: 0 4px 15px rgba(0,0,0,0.2);
                        border-radius: 6px;
                    }
                }

                @media print {
                    @page {
                        ${isA4 ? 'size: 210mm 297mm; margin: 12mm 15mm;' : `size: ${actualWidthMm}mm auto; margin: 0;`}
                    }
                    body {
                        margin: 0 !important;
                        padding: ${is500mm ? '8mm 12mm' : (isA4 ? '0' : '2mm')} !important;
                        width: ${actualWidthMm}mm !important;
                        max-width: ${actualWidthMm}mm !important;
                        background: #ffffff !important;
                    }
                    .pos-print-container {
                        width: 100% !important;
                        max-width: ${actualWidthMm}mm !important;
                        box-shadow: none !important;
                        padding: 0 !important;
                    }
                }
            </style>
        </head>
        <body>
            <div class="pos-print-container">
                ${copiesHtml}
            </div>
        </body>
        </html>
    `);
    printWin.document.close();
    printWin.focus();
    setTimeout(() => {
        printWin.print();
        printWin.close();
    }, 450);
}
</script>

<?php require_once('footer.php'); ?>
