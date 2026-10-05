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
                <i class="fa fa-file-text-o" style="color: #f59e0b; margin-right: 6px;"></i> On-Hold Order(s)
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
                            <div style="display: inline-flex; gap: 4px; align-items: center;">
                                <a href="pos.php?po_id=<?php echo urlencode($po_code); ?>" class="btn btn-success btn-xs" style="font-weight: 700; background-color: #16a34a; border-color: #15803d; border-radius: 4px; padding: 4px 8px; color: #fff; text-decoration: none;" title="Resume in POS terminal to add items or complete payment">
                                    <i class="fa fa-play"></i> Resume
                                </a>
                                <button type="button" class="po-btn-view" data-toggle="modal" data-target="#poDetailModal-<?php echo $po_id; ?>" title="View Complete PO Details">
                                    <i class="fa fa-eye"></i> View
                                </button>
                                <button type="button" class="po-btn-reprint" onclick="printPOVoucher('<?php echo $po_id; ?>')" title="Reprint PO Reference Voucher for Customer">
                                    <i class="fa fa-print"></i> Reprint
                                </button>
                            </div>
                        </td>
                    </tr>

                    <!-- ========================================== -->
                    <!-- READ-ONLY PO DETAIL & REPRINT MODAL       -->
                    <!-- ========================================== -->
                    <div class="modal fade" id="poDetailModal-<?php echo $po_id; ?>" tabindex="-1" role="dialog" aria-labelledby="poModalLabel-<?php echo $po_id; ?>" aria-hidden="true">
                        <div class="modal-dialog modal-lg" style="max-width: 720px; margin-top: 30px;">
                            <div class="modal-content" style="border-radius: 10px; overflow: hidden; border: none; box-shadow: 0 15px 40px rgba(0,0,0,0.2);">
                                
                                <!-- Modern Modal Header -->
                                <div class="modal-header-modern" style="background: #0284c7; display: flex; justify-content: space-between; align-items: center; padding: 14px 20px; color: #ffffff;">
                                    <h4 class="modal-title" id="poModalLabel-<?php echo $po_id; ?>" style="margin: 0; font-size: 16px; font-weight: 700; display: flex; align-items: center; gap: 8px; color: #ffffff;">
                                        <i class="fa fa-file-text-o"></i> Customer Purchase Order Voucher — <span style="font-family: monospace; font-size: 17px;"><?php echo htmlspecialchars($po_code); ?></span>
                                    </h4>
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <button type="button" class="btn btn-xs btn-default" onclick="printPOVoucher('<?php echo $po_id; ?>')" style="background: #ffffff; color: #0284c7; font-weight: 700; border: none; border-radius: 5px; padding: 6px 14px; font-size: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                            <i class="fa fa-print"></i> Print Voucher
                                        </button>
                                        <button type="button" class="close" data-dismiss="modal" aria-hidden="true" style="color: #ffffff; opacity: 0.9; font-size: 24px; margin: 0; line-height: 1;">&times;</button>
                                    </div>
                                </div>

                                <!-- Modern Modal Body -->
                                <div class="modal-body" style="padding: 20px; background: #f8fafc;">
                                    
                                    <!-- Status & Overview Bar -->
                                    <div style="background: #fffbeb; border: 1.5px solid #fde68a; border-radius: 8px; padding: 12px 18px; margin-bottom: 18px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                                        <div style="display: flex; align-items: center; gap: 10px;">
                                            <span class="label" style="background: #f59e0b; color: #ffffff; font-size: 12px; font-weight: 800; padding: 5px 10px; border-radius: 4px; text-transform: uppercase;">
                                                <i class="fa fa-clock-o"></i> Awaiting Cashier Payment
                                            </span>
                                            <span style="font-size: 12.5px; color: #78350f; font-weight: 600;">Customer must settle payment at counter</span>
                                        </div>
                                        <div style="font-size: 12px; color: #64748b; font-weight: 600;">
                                            <i class="fa fa-calendar"></i> <?php echo date('M d, Y h:i A', strtotime($order_date)); ?>
                                        </div>
                                    </div>

                                    <!-- 2-Column Info Cards -->
                                    <div class="row" style="margin-bottom: 18px;">
                                        <div class="col-sm-6 col-xs-12" style="margin-bottom: 10px;">
                                            <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px 16px; height: 100%;">
                                                <div style="font-size: 11px; font-weight: 800; text-transform: uppercase; color: #64748b; margin-bottom: 8px; letter-spacing: 0.5px;">
                                                    <i class="fa fa-user"></i> Customer Information
                                                </div>
                                                <div style="font-size: 14px; font-weight: 700; color: #0f172a; margin-bottom: 3px;">
                                                    <?php echo htmlspecialchars($cust_name ?? 'Walk-in Customer'); ?>
                                                </div>
                                                <?php if (!empty($cust_phone)): ?>
                                                    <div style="font-size: 12px; color: #475569; margin-bottom: 2px;"><i class="fa fa-phone" style="width: 14px; color: #0284c7;"></i> <?php echo htmlspecialchars($cust_phone); ?></div>
                                                <?php endif; ?>
                                                <?php if (!empty($cust_email)): ?>
                                                    <div style="font-size: 12px; color: #475569;"><i class="fa fa-envelope" style="width: 14px; color: #0284c7;"></i> <?php echo htmlspecialchars($cust_email); ?></div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="col-sm-6 col-xs-12" style="margin-bottom: 10px;">
                                            <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px 16px; height: 100%;">
                                                <div style="font-size: 11px; font-weight: 800; text-transform: uppercase; color: #64748b; margin-bottom: 8px; letter-spacing: 0.5px;">
                                                    <i class="fa fa-building-o"></i> Store &amp; Fulfillment
                                                </div>
                                                <div style="font-size: 13.5px; font-weight: 700; color: #0f172a; margin-bottom: 3px;">
                                                    <?php echo htmlspecialchars(!empty($store_name) ? $store_name : 'SAM & INRI CONSTRUCTION SUPPLY'); ?>
                                                </div>
                                                <div style="font-size: 12px; color: #475569; margin-bottom: 4px;">
                                                    <?php if ($is_delivery): ?>
                                                        <span class="label" style="background: #0284c7; font-size: 10.5px; padding: 3px 7px; border-radius: 3px;"><i class="fa fa-truck"></i> Delivery</span>
                                                    <?php else: ?>
                                                        <span class="label" style="background: #059669; font-size: 10.5px; padding: 3px 7px; border-radius: 3px;"><i class="fa fa-shopping-bag"></i> Store Pick-up</span>
                                                    <?php endif; ?>
                                                    <span style="margin-left: 6px; font-weight: 600;">Method: Purchase Order (PO)</span>
                                                </div>
                                                <?php if (!empty($store_phone)): ?>
                                                    <div style="font-size: 11.5px; color: #64748b;"><i class="fa fa-phone" style="width: 14px;"></i> Tel: <?php echo htmlspecialchars($store_phone); ?></div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Order Line Items Table -->
                                    <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; margin-bottom: 18px;">
                                        <div style="padding: 10px 16px; background: #f1f5f9; border-bottom: 1px solid #e2e8f0; font-size: 12px; font-weight: 800; text-transform: uppercase; color: #475569; letter-spacing: 0.5px;">
                                            <i class="fa fa-shopping-cart"></i> Ordered Items (<?php echo count($po_items); ?>)
                                        </div>
                                        <div class="table-responsive" style="margin-bottom: 0;">
                                            <table class="table table-hover" style="margin-bottom: 0; font-size: 13px;">
                                                <thead style="background: #f8fafc;">
                                                    <tr>
                                                        <th style="width: 40px; text-align: center; color: #64748b; font-weight: 700; border-bottom: 1px solid #e2e8f0;">#</th>
                                                        <th style="color: #475569; font-weight: 700; border-bottom: 1px solid #e2e8f0;">Item Description</th>
                                                        <th style="width: 80px; text-align: center; color: #475569; font-weight: 700; border-bottom: 1px solid #e2e8f0;">Qty</th>
                                                        <th style="width: 110px; text-align: right; color: #475569; font-weight: 700; border-bottom: 1px solid #e2e8f0;">Unit Price</th>
                                                        <th style="width: 120px; text-align: right; color: #475569; font-weight: 700; border-bottom: 1px solid #e2e8f0;">Subtotal</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php 
                                                    $calculated_subtotal = 0;
                                                    $item_idx = 0;
                                                    foreach ($po_items as $item): 
                                                        $item_idx++;
                                                        $item_qty = intval($item['quantity']);
                                                        $item_price = floatval($item['unit_price']);
                                                        $item_subtotal = $item_qty * $item_price;
                                                        $calculated_subtotal += $item_subtotal;
                                                        $is_sp = (isset($item['item_type']) && $item['item_type'] === 'SPECIAL_ORDER');
                                                        $unit_label = ($item_qty > 1 ? 'pcs' : 'pc');
                                                    ?>
                                                    <tr>
                                                        <td style="text-align: center; color: #94a3b8; font-weight: 600; vertical-align: middle;"><?php echo $item_idx; ?></td>
                                                        <td style="vertical-align: middle;">
                                                            <div style="font-weight: 700; color: #0f172a;">
                                                                <?php if ($is_sp): ?>
                                                                    <span class="label" style="background: #f59e0b; color: #fff; font-size: 10px; padding: 2px 6px; margin-right: 4px;">SPECIAL ORDER</span>
                                                                <?php endif; ?>
                                                                <?php echo htmlspecialchars($item['product_name']); ?>
                                                            </div>
                                                            <?php if (!empty($item['size']) || !empty($item['color'])): ?>
                                                                <div style="margin-top: 3px; display: flex; gap: 4px;">
                                                                    <?php if (!empty($item['size']) && $item['size'] !== '-'): ?>
                                                                        <span class="badge" style="background: #f1f5f9; color: #475569; font-size: 10.5px; font-weight: 600;">Size: <?php echo htmlspecialchars($item['size']); ?></span>
                                                                    <?php endif; ?>
                                                                    <?php if (!empty($item['color']) && $item['color'] !== '-'): ?>
                                                                        <span class="badge" style="background: #f1f5f9; color: #475569; font-size: 10.5px; font-weight: 600;">Color: <?php echo htmlspecialchars($item['color']); ?></span>
                                                                    <?php endif; ?>
                                                                </div>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td style="text-align: center; font-weight: 700; color: #1e293b; vertical-align: middle;"><?php echo $item_qty; ?> <span style="font-size: 11px; color: #64748b; font-weight: normal;"><?php echo $unit_label; ?></span></td>
                                                        <td style="text-align: right; color: #475569; font-weight: 600; vertical-align: middle;">&#8369;<?php echo number_format($item_price, 2); ?></td>
                                                        <td style="text-align: right; font-weight: 800; color: #0f172a; vertical-align: middle;">&#8369;<?php echo number_format($item_subtotal, 2); ?></td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>

                                    <!-- Financial Totals Block -->
                                    <div style="display: flex; justify-content: flex-end;">
                                        <div style="width: 100%; max-width: 320px; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px 18px;">
                                            <div style="display: flex; justify-content: space-between; font-size: 13px; color: #475569; margin-bottom: 6px;">
                                                <span>Subtotal:</span>
                                                <span style="font-weight: 700; color: #0f172a;">&#8369;<?php echo number_format($calculated_subtotal, 2); ?></span>
                                            </div>
                                            <div style="display: flex; justify-content: space-between; font-size: 13px; color: #475569; margin-bottom: 6px;">
                                                <span>Discount:</span>
                                                <span style="font-weight: 600; color: #16a34a;">&#8369;0.00</span>
                                            </div>
                                            <div style="border-top: 2px solid #e2e8f0; padding-top: 8px; margin-top: 6px; display: flex; justify-content: space-between; align-items: baseline;">
                                                <span style="font-size: 14px; font-weight: 800; color: #0f172a;">TOTAL DUE:</span>
                                                <span style="font-size: 18px; font-weight: 800; color: #0284c7;">&#8369;<?php echo number_format($total_amount, 2); ?></span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Hidden Silent Thermal Print Element -->
                                    <div id="po-printable-thermal-<?php echo $po_id; ?>" style="display: none;">
                                        <div style="font-family: 'Courier New', Consolas, monospace; font-size: 11pt; line-height: 1.25; color: #000; text-align: left;">
                                            <div style="text-align: center; font-weight: bold; overflow: hidden; white-space: nowrap;">================================</div>
                                            <div style="text-align: center;">
                                                <div style="font-size: 12pt; font-weight: bold; text-transform: uppercase;"><?php echo htmlspecialchars(!empty($store_name) ? strtoupper($store_name) : 'SAM & INRI CONSTRUCTION SUPPLY'); ?></div>
                                                <?php if (!empty($store_address)): ?>
                                                    <div style="font-size: 9.5pt;"><?php echo htmlspecialchars($store_address); ?></div>
                                                <?php endif; ?>
                                                <div style="font-size: 10pt;">Tel: <?php echo htmlspecialchars(!empty($store_phone) ? $store_phone : '09612735733'); ?></div>
                                                <div style="font-size: 11pt; font-weight: bold; text-transform: uppercase; margin-top: 2px;">PURCHASE ORDER VOUCHER</div>
                                                <div style="font-size: 10pt; font-weight: bold; text-transform: uppercase;">(UNPAID)</div>
                                            </div>
                                            <div style="text-align: center; font-weight: bold; overflow: hidden; white-space: nowrap;">================================</div>
                                            <table style="width: 100%; border-collapse: collapse; font-family: inherit; font-size: 10.5pt; margin-bottom: 2px;">
                                                <tr><td style="width: 28%; font-weight: bold;">PO NO   :</td><td style="font-weight: bold;"><?php echo htmlspecialchars($po_code); ?></td></tr>
                                                <tr><td style="font-weight: bold;">CUSTOMER:</td><td><?php echo htmlspecialchars($cust_name ?? 'Walk-in Customer'); ?></td></tr>
                                                <tr><td style="font-weight: bold;">STATUS  :</td><td style="font-weight: bold;">AWAITING PAYMENT</td></tr>
                                                <tr><td style="font-weight: bold;">DATE    :</td><td><?php echo date('Y-m-d H:i:s', strtotime($order_date)); ?></td></tr>
                                            </table>
                                            <div style="text-align: center; overflow: hidden; white-space: nowrap;">--------------------------------</div>
                                            <table style="width: 100%; border-collapse: collapse; font-family: inherit; font-size: 10.5pt;">
                                                <thead>
                                                    <tr>
                                                        <th style="text-align: left; width: 68%;">ITEM DESCRIPTION</th>
                                                        <th style="text-align: right; width: 32%;">AMOUNT</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($po_items as $item): 
                                                        $item_qty = intval($item['quantity']);
                                                        $item_price = floatval($item['unit_price']);
                                                        $item_subtotal = $item_qty * $item_price;
                                                        $is_sp = (isset($item['item_type']) && $item['item_type'] === 'SPECIAL_ORDER');
                                                        $unit_label = ($item_qty > 1 ? 'pcs' : 'pc');
                                                    ?>
                                                    <tr>
                                                        <td colspan="2" style="text-align: left; padding-top: 3px; font-weight: bold; word-break: break-word;">
                                                            <?php if ($is_sp): ?>[SPECIAL ORDER] <?php endif; ?>
                                                            <?php echo htmlspecialchars($item['product_name']); ?>
                                                            <?php if (!empty($item['size']) || !empty($item['color'])): ?>
                                                                <div style="font-size: 9pt; font-weight: normal;">
                                                                    <?php if (!empty($item['size']) && $item['size'] !== '-') echo 'Size: ' . htmlspecialchars($item['size']) . ' '; ?>
                                                                    <?php if (!empty($item['color']) && $item['color'] !== '-') echo 'Color: ' . htmlspecialchars($item['color']); ?>
                                                                </div>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td style="text-align: left; padding-left: 8px; padding-bottom: 3px;">
                                                            <?php echo $item_qty; ?> <?php echo $unit_label; ?> @ <?php echo number_format($item_price, 2); ?>
                                                        </td>
                                                        <td style="text-align: right; padding-bottom: 3px; white-space: nowrap; vertical-align: bottom;">
                                                            <?php echo number_format($item_subtotal, 2); ?>
                                                        </td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                            <div style="text-align: center; overflow: hidden; white-space: nowrap;">--------------------------------</div>
                                            <table style="width: 100%; border-collapse: collapse; font-family: inherit; font-size: 10.5pt; margin: 2px 0;">
                                                <tr><td>Subtotal:</td><td style="text-align: right;"><?php echo number_format($calculated_subtotal, 2); ?></td></tr>
                                                <tr style="font-weight: bold;"><td style="font-size: 1.08em;">TOTAL DUE:</td><td style="text-align: right; font-size: 1.08em;">PHP <?php echo number_format($total_amount, 2); ?></td></tr>
                                            </table>
                                            <div style="text-align: center; font-weight: bold; overflow: hidden; white-space: nowrap;">================================</div>
                                            <div style="text-align: center; font-weight: bold; padding: 4px 0; font-size: 11pt; letter-spacing: 0.5px;">
                                                *** ORDER ON HOLD ***
                                            </div>
                                            <div style="text-align: center; font-weight: bold; overflow: hidden; white-space: nowrap;">================================</div>
                                        </div>
                                    </div>

                                </div>
                                <div class="modal-footer" style="background: #f1f5f9; border-top: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; padding: 12px 20px;">
                                    <button type="button" class="btn btn-default btn-sm" data-dismiss="modal" style="font-weight: 700; border-radius: 5px;">Close</button>
                                    <div style="display: flex; gap: 8px;">
                                        <a href="pos.php?po_id=<?php echo urlencode($po_code); ?>" class="btn btn-success btn-sm" style="font-weight: 700; background-color: #16a34a; border-color: #15803d; border-radius: 5px; padding: 6px 14px;">
                                            <i class="fa fa-credit-card"></i> Process POS Payment
                                        </a>
                                        <button type="button" class="btn btn-primary btn-sm" onclick="printPOVoucher('<?php echo $po_id; ?>')" style="font-weight: 700; background-color: #0284c7; border-color: #0369a1; border-radius: 5px; padding: 6px 16px;" title="Print Voucher on Thermal Printer">
                                            <i class="fa fa-print"></i> Print Voucher
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

<?php require_once('footer.php'); ?>
