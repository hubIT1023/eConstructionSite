<?php require_once('header.php'); ?>

<?php
// -------------------------------------------------------------
// 1. Initialize filter variables
// -------------------------------------------------------------
$filter_type = isset($_GET['filter_type']) ? $_GET['filter_type'] : 'year';
$current_year = date('Y');
$current_month = date('m');
$today_date = date('Y-m-d');

$selected_date = isset($_GET['day_date']) && !empty($_GET['day_date']) ? $_GET['day_date'] : $today_date;
$selected_year = isset($_GET['year']) && !empty($_GET['year']) ? (int)$_GET['year'] : (int)$current_year;
$selected_month = isset($_GET['month']) && !empty($_GET['month']) ? (int)$_GET['month'] : (int)$current_month;
$selected_week = isset($_GET['week']) && !empty($_GET['week']) ? (int)$_GET['week'] : (int)date('W');
$selected_quarter = isset($_GET['quarter']) && !empty($_GET['quarter']) ? (int)$_GET['quarter'] : (int)ceil((int)$current_month / 3);
$start_date_custom = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date_custom = isset($_GET['end_date']) ? $_GET['end_date'] : '';

$filter_label = '';
$start_datetime = '';
$end_datetime = '';

// Determine start and end datetime based on filter_type
switch ($filter_type) {
    case 'day':
        $start_datetime = $selected_date . ' 00:00:00';
        $end_datetime = $selected_date . ' 23:59:59';
        $filter_label = "Day: " . date('F d, Y', strtotime($selected_date));
        break;

    case 'week':
        $dto = new DateTime();
        $dto->setISODate($selected_year, $selected_week);
        $week_start = $dto->format('Y-m-d');
        $dto->modify('+6 days');
        $week_end = $dto->format('Y-m-d');
        $start_datetime = $week_start . ' 00:00:00';
        $end_datetime = $week_end . ' 23:59:59';
        $filter_label = "Week $selected_week, $selected_year (" . date('M d', strtotime($week_start)) . " - " . date('M d, Y', strtotime($week_end)) . ")";
        break;

    case 'month':
        $m_str = str_pad($selected_month, 2, '0', STR_PAD_LEFT);
        $days_in_m = cal_days_in_month(CAL_GREGORIAN, $selected_month, $selected_year);
        $start_datetime = "$selected_year-$m_str-01 00:00:00";
        $end_datetime = "$selected_year-$m_str-$days_in_m 23:59:59";
        $filter_label = "Month: " . date('F Y', strtotime("$selected_year-$m_str-01"));
        break;

    case 'quarter':
        $quarter_months = [
            1 => ['01-01', '03-31', 'Q1 (Jan - Mar)'],
            2 => ['04-01', '06-30', 'Q2 (Apr - Jun)'],
            3 => ['07-01', '09-30', 'Q3 (Jul - Sep)'],
            4 => ['10-01', '12-31', 'Q4 (Oct - Dec)'],
        ];
        $q_info = $quarter_months[$selected_quarter];
        $start_datetime = $selected_year . '-' . $q_info[0] . ' 00:00:00';
        $end_datetime = $selected_year . '-' . $q_info[1] . ' 23:59:59';
        $filter_label = $q_info[2] . " " . $selected_year;
        break;

    case 'year':
        $start_datetime = $selected_year . '-01-01 00:00:00';
        $end_datetime = $selected_year . '-12-31 23:59:59';
        $filter_label = "Year: " . $selected_year;
        break;

    case 'custom':
        if (!empty($start_date_custom) && !empty($end_date_custom)) {
            $start_datetime = $start_date_custom . ' 00:00:00';
            $end_datetime = $end_date_custom . ' 23:59:59';
            $filter_label = date('M d, Y', strtotime($start_date_custom)) . " to " . date('M d, Y', strtotime($end_date_custom));
        } else {
            $start_datetime = $current_year . '-01-01 00:00:00';
            $end_datetime = $current_year . '-12-31 23:59:59';
            $filter_label = "Year: " . $current_year;
        }
        break;

    default:
        $filter_type = 'year';
        $start_datetime = $selected_year . '-01-01 00:00:00';
        $end_datetime = $selected_year . '-12-31 23:59:59';
        $filter_label = "Year: " . $selected_year;
        break;
}

// -------------------------------------------------------------
// 2. Load catalog product financial map (Capital Price & Markup)
// -------------------------------------------------------------
$stmt_prod_map = $pdo->prepare("SELECT p_id, p_name, p_current_price, p_new_price, p_capital_price, p_markup FROM tbl_product WHERE supplier_id = ?");
$stmt_prod_map->execute(array($supplier_id));
$products_map = [];
while ($prow = $stmt_prod_map->fetch(PDO::FETCH_ASSOC)) {
    $products_map[$prow['p_id']] = $prow;
}

// Helper: Calculate item financials (Revenue, Unit Capital, Total Cost, Profit, Markup)
if (!function_exists('calculate_item_financials')) {
    function calculate_item_financials($product_id, $unit_price, $quantity, &$products_map) {
        $qty = (int)$quantity;
        $u_price = (float)$unit_price;
        $p_id = (int)$product_id;
        $subtotal = $qty * $u_price;
        $markup = 20.0;
        $unit_capital = 0.0;

        if ($p_id > 0 && isset($products_map[$p_id])) {
            $p = $products_map[$p_id];
            $m = (isset($p['p_markup']) && $p['p_markup'] !== '') ? (float)$p['p_markup'] : 20.0;
            if ($m <= 0) $m = 20.0;
            $markup = $m;

            if (isset($p['p_capital_price']) && (float)$p['p_capital_price'] > 0) {
                $unit_capital = (float)$p['p_capital_price'];
            } else {
                $unit_capital = round($u_price / (1 + ($markup / 100)), 2);
            }
        } else {
            // Fallback for special orders or unmapped items: default 20% markup
            $markup = 20.0;
            $unit_capital = round($u_price / 1.20, 2);
        }

        $total_capital = $unit_capital * $qty;
        $profit = max(0.0, $subtotal - $total_capital);
        $margin_percent = $subtotal > 0 ? ($profit / $subtotal) * 100 : 0.0;

        return [
            'qty' => $qty,
            'unit_price' => $u_price,
            'subtotal' => $subtotal,
            'unit_capital' => $unit_capital,
            'total_capital' => $total_capital,
            'profit' => $profit,
            'markup' => $markup,
            'margin_percent' => $margin_percent
        ];
    }
}

// Helper: Calculate multi-period aggregated metrics (Sales, Cost, Profit, Margins, Orders)
if (!function_exists('get_period_financial_metrics')) {
    function get_period_financial_metrics($pdo, $supplier_id, $start_dt, $end_dt, &$products_map) {
        $stmt_pay = $pdo->prepare("SELECT * FROM tbl_payment WHERE supplier_id = ? AND payment_date >= ? AND payment_date <= ?");
        $stmt_pay->execute(array($supplier_id, $start_dt, $end_dt));
        $orders = $stmt_pay->fetchAll(PDO::FETCH_ASSOC);

        $stmt_ret = $pdo->prepare("SELECT r.*, ri.product_id, ri.quantity_returned, ri.refund_amount, ri.unit_price as item_unit_price 
                                   FROM tbl_returns r
                                   JOIN tbl_return_items ri ON r.return_id = ri.return_id
                                   WHERE r.supplier_id = ? AND r.return_date >= ? AND r.return_date <= ?");
        $stmt_ret->execute(array($supplier_id, $start_dt, $end_dt));
        $returns = $stmt_ret->fetchAll(PDO::FETCH_ASSOC);

        $gross_sales = 0.0;
        $total_cost = 0.0;
        $gross_profit = 0.0;
        $units_sold = 0;
        $order_count = count($orders);

        foreach ($orders as $ord) {
            $paid = (float)$ord['paid_amount'];
            $gross_sales += $paid;

            $stmt_items = $pdo->prepare("SELECT product_id, unit_price, quantity FROM tbl_order WHERE payment_id = ?");
            $stmt_items->execute(array($ord['payment_id']));
            $items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

            foreach ($items as $it) {
                $fin = calculate_item_financials($it['product_id'], $it['unit_price'], $it['quantity'], $products_map);
                $total_cost += $fin['total_capital'];
                $gross_profit += $fin['profit'];
                $units_sold += $fin['qty'];
            }
        }

        $refunds_amt = 0.0;
        $units_ret = 0;
        $returns_profit_deduction = 0.0;
        foreach ($returns as $ret) {
            $r_fin = calculate_item_financials($ret['product_id'], $ret['item_unit_price'], $ret['quantity_returned'], $products_map);
            $refunds_amt += (float)$ret['refund_amount'];
            $units_ret += (int)$ret['quantity_returned'];
            $returns_profit_deduction += $r_fin['profit'];
        }

        $net_sales = max(0.0, $gross_sales - $refunds_amt);
        $net_profit = max(0.0, $gross_profit - $returns_profit_deduction);
        $net_units = max(0, $units_sold - $units_ret);
        $margin = $net_sales > 0 ? ($net_profit / $net_sales) * 100 : 0.0;

        return [
            'gross_sales' => $gross_sales,
            'refunds_amt' => $refunds_amt,
            'net_sales' => $net_sales,
            'total_cost' => $total_cost,
            'gross_profit' => $gross_profit,
            'net_profit' => $net_profit,
            'order_count' => $order_count,
            'units_sold' => $units_sold,
            'units_ret' => $units_ret,
            'net_units' => $net_units,
            'margin' => $margin
        ];
    }
}

// -------------------------------------------------------------
// 3. Compute 4-Horizon Snapshot: Day, Week, Month, and Year
// -------------------------------------------------------------
$today_start = date('Y-m-d 00:00:00');
$today_end = date('Y-m-d 23:59:59');
$metrics_today = get_period_financial_metrics($pdo, $supplier_id, $today_start, $today_end, $products_map);

$dto_curr = new DateTime();
$dto_curr->setISODate((int)$current_year, (int)date('W'));
$week_curr_start = $dto_curr->format('Y-m-d 00:00:00');
$dto_curr->modify('+6 days');
$week_curr_end = $dto_curr->format('Y-m-d 23:59:59');
$metrics_week = get_period_financial_metrics($pdo, $supplier_id, $week_curr_start, $week_curr_end, $products_map);

$month_curr_start = date('Y-m-01 00:00:00');
$month_curr_end = date('Y-m-t 23:59:59');
$metrics_month = get_period_financial_metrics($pdo, $supplier_id, $month_curr_start, $month_curr_end, $products_map);

$year_curr_start = date('Y-01-01 00:00:00');
$year_curr_end = date('Y-12-31 23:59:59');
$metrics_year = get_period_financial_metrics($pdo, $supplier_id, $year_curr_start, $year_curr_end, $products_map);

// -------------------------------------------------------------
// 4. Detailed Data Query for Current Filter Period
// -------------------------------------------------------------
// Fetch orders matching date range for this supplier
$stmt = $pdo->prepare("SELECT * FROM tbl_payment WHERE supplier_id = ? AND payment_date >= ? AND payment_date <= ? ORDER BY id DESC");
$stmt->execute(array($supplier_id, $start_datetime, $end_datetime));
$sales_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch returns matching date range for this supplier
$stmt_ret = $pdo->prepare("SELECT r.*, ri.product_id, ri.product_name, ri.quantity_returned, ri.refund_amount as item_refund, ri.unit_price as item_unit_price, ri.return_reason, ri.condition, ri.restock_status, ri.sku, ri.item_type, ri.special_order_reference, ri.product_details 
                           FROM tbl_returns r
                           JOIN tbl_return_items ri ON r.return_id = ri.return_id
                           WHERE r.supplier_id = ? AND r.return_date >= ? AND r.return_date <= ?
                           ORDER BY r.return_id DESC");
$stmt_ret->execute(array($supplier_id, $start_datetime, $end_datetime));
$period_returns = $stmt_ret->fetchAll(PDO::FETCH_ASSOC);

// Period detailed metrics
$total_gross_revenue = 0;
$total_orders_count = count($sales_orders);
$total_units_sold = 0;
$total_gross_cost = 0;
$total_gross_profit = 0;
$total_delivery_collected = 0;
$payment_methods_breakdown = [];
$top_products = [];
$trend_data = [];

// Pre-process each order with line-item profitability
$processed_orders = [];
foreach ($sales_orders as $ord) {
    $paid_amt = (float)$ord['paid_amount'];
    $total_gross_revenue += $paid_amt;

    // Payment method distribution
    $pm = !empty($ord['payment_method']) ? $ord['payment_method'] : 'Other';
    if (!isset($payment_methods_breakdown[$pm])) {
        $payment_methods_breakdown[$pm] = 0;
    }
    $payment_methods_breakdown[$pm] += $paid_amt;

    // Fetch order items
    $stmt_items = $pdo->prepare("SELECT * FROM tbl_order WHERE payment_id = ?");
    $stmt_items->execute(array($ord['payment_id']));
    $ord_items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

    $items_subtotal = 0;
    $order_cost = 0;
    $order_profit = 0;
    $processed_items = [];

    foreach ($ord_items as $item) {
        $fin = calculate_item_financials($item['product_id'], $item['unit_price'], $item['quantity'], $products_map);
        $items_subtotal += $fin['subtotal'];
        $order_cost += $fin['total_capital'];
        $order_profit += $fin['profit'];
        $total_units_sold += $fin['qty'];

        $item_entry = array_merge($item, $fin);
        $processed_items[] = $item_entry;

        $p_name = $item['product_name'];
        if (!isset($top_products[$p_name])) {
            $top_products[$p_name] = [
                'name' => $p_name,
                'qty' => 0,
                'revenue' => 0,
                'cost' => 0,
                'profit' => 0
            ];
        }
        $top_products[$p_name]['qty'] += $fin['qty'];
        $top_products[$p_name]['revenue'] += $fin['subtotal'];
        $top_products[$p_name]['cost'] += $fin['total_capital'];
        $top_products[$p_name]['profit'] += $fin['profit'];
    }

    $total_gross_cost += $order_cost;
    $total_gross_profit += $order_profit;

    $delivery_fee = $paid_amt - $items_subtotal;
    if ($delivery_fee > 0) {
        $total_delivery_collected += $delivery_fee;
    }

    $order_margin = $items_subtotal > 0 ? ($order_profit / $items_subtotal) * 100 : 0;
    $ord['items'] = $processed_items;
    $ord['items_subtotal'] = $items_subtotal;
    $ord['order_cost'] = $order_cost;
    $ord['order_profit'] = $order_profit;
    $ord['order_margin'] = $order_margin;
    $ord['delivery_fee'] = max(0, $delivery_fee);
    $processed_orders[] = $ord;

    // Trend grouping by date
    $p_date_key = date('Y-m-d', strtotime($ord['payment_date']));
    if (!isset($trend_data[$p_date_key])) {
        $trend_data[$p_date_key] = [
            'revenue' => 0,
            'profit' => 0,
            'orders' => 0
        ];
    }
    $trend_data[$p_date_key]['revenue'] += $paid_amt;
    $trend_data[$p_date_key]['profit'] += $order_profit;
    $trend_data[$p_date_key]['orders'] += 1;
}

// Returns Calculations
$total_refunds_amount = 0;
$total_units_returned = 0;
$total_returns_cost = 0;
$total_returns_profit_deduction = 0;

foreach ($period_returns as $r_row) {
    $r_fin = calculate_item_financials($r_row['product_id'], $r_row['item_unit_price'] ?: $r_row['unit_price'], $r_row['quantity_returned'], $products_map);
    $total_refunds_amount += (float)$r_row['item_refund'];
    $total_units_returned += (int)$r_row['quantity_returned'];
    $total_returns_cost += $r_fin['total_capital'];
    $total_returns_profit_deduction += $r_fin['profit'];
}

$total_net_revenue = max(0.0, $total_gross_revenue - $total_refunds_amount);
$total_net_profit = max(0.0, $total_gross_profit - $total_returns_profit_deduction);
$net_units_sold = max(0, $total_units_sold - $total_units_returned);
$period_profit_margin = $total_net_revenue > 0 ? ($total_net_profit / $total_net_revenue) * 100 : 0.0;
$aov = $total_orders_count > 0 ? ($total_gross_revenue / $total_orders_count) : 0.0;

// Sort top products by profit descending
uasort($top_products, function($a, $b) {
    return $b['profit'] <=> $a['profit'];
});
$top_products_list = array_slice($top_products, 0, 8);

// Sort trend data by date ascending
ksort($trend_data);
$trend_labels = array_keys($trend_data);
$trend_revenues = array_column($trend_data, 'revenue');
$trend_profits = array_column($trend_data, 'profit');
$trend_orders_counts = array_column($trend_data, 'orders');
?>

<!-- Load Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>

<style>
/* Overview Horizon Matrix Cards */
.horizon-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 16px 18px;
    margin-bottom: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.04);
    position: relative;
    overflow: hidden;
    transition: all 0.2s ease-in-out;
}
.horizon-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 16px rgba(0,0,0,0.08);
}
.horizon-card .horizon-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid #f1f5f9;
    padding-bottom: 10px;
    margin-bottom: 12px;
}
.horizon-card .horizon-badge {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 3px 8px;
    border-radius: 4px;
}
.badge-day { background: #e0f2fe; color: #0369a1; }
.badge-week { background: #fef3c7; color: #b45309; }
.badge-month { background: #dcfce7; color: #15803d; }
.badge-year { background: #ede9fe; color: #6d28d9; }

.horizon-profit {
    font-size: 23px;
    font-weight: 800;
    color: #10b981;
    margin: 4px 0 2px 0;
    line-height: 1.2;
}
.horizon-sales {
    font-size: 14px;
    font-weight: 600;
    color: #475569;
    margin-bottom: 8px;
}
.horizon-meta {
    font-size: 12px;
    color: #64748b;
    display: flex;
    justify-content: space-between;
    border-top: 1px dashed #e2e8f0;
    padding-top: 8px;
    margin-top: 8px;
}

/* Stat Cards */
.stat-card {
    border-radius: 8px;
    padding: 18px 20px;
    color: #fff;
    margin-bottom: 20px;
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
    position: relative;
    overflow: hidden;
    transition: transform 0.2s ease;
}
.stat-card:hover {
    transform: translateY(-2px);
}
.stat-card .icon-bg {
    position: absolute;
    right: 15px;
    bottom: 10px;
    font-size: 55px;
    opacity: 0.15;
}
.stat-card h3 {
    margin: 0 0 6px 0;
    font-size: 24px;
    font-weight: 800;
}
.stat-card p {
    margin: 0;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    opacity: 0.9;
    font-weight: 600;
}
.bg-gradient-blue {
    background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%);
}
.bg-gradient-green {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
}
.bg-gradient-red {
    background: linear-gradient(135deg, #ef4444 0%, #b91c1c 100%);
}
.bg-gradient-amber {
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
}
.bg-gradient-purple {
    background: linear-gradient(135deg, #8b5cf6 0%, #6d28d9 100%);
}
.bg-gradient-slate {
    background: linear-gradient(135deg, #334155 0%, #1e293b 100%);
}

.filter-box {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 25px;
    box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.05);
}
.filter-tabs {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 15px;
    border-bottom: 1px solid #e2e8f0;
    padding-bottom: 12px;
}
.filter-btn {
    padding: 8px 16px;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 600;
    border: 1px solid #cbd5e1;
    background: #f8fafc;
    color: #475569;
    text-decoration: none;
    cursor: pointer;
    transition: all 0.15s ease;
}
.filter-btn:hover {
    background: #e2e8f0;
    color: #0f172a;
    text-decoration: none;
}
.filter-btn.active {
    background: #0284c7;
    border-color: #0284c7;
    color: #ffffff;
}
.chart-box {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 25px;
    box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.05);
}
.chart-header {
    border-bottom: 1px solid #f1f5f9;
    padding-bottom: 12px;
    margin-bottom: 15px;
    font-size: 16px;
    font-weight: 700;
    color: #1e293b;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
@media print {
    .main-header, .main-sidebar, .content-header, .filter-box, .no-print, .btn, .dataTables_filter, .dataTables_length, .dataTables_paginate, .dataTables_info, .horizon-card {
        display: none !important;
    }
    .content-wrapper {
        margin: 0 !important;
        padding: 0 !important;
        background: #fff !important;
    }
    .stat-card, .chart-box, .box {
        box-shadow: none !important;
        border: 1px solid #ddd !important;
    }
}
</style>

<section class="content-header">
	<div class="content-header-left">
		<h1><i class="fa fa-line-chart" style="color: #0284c7;"></i> Sales & Profit Report</h1>
	</div>
    <div class="content-header-right no-print" style="float: right;">
        <button onclick="window.print();" class="btn btn-primary" style="background-color: #0284c7; border-color: #0284c7;">
            <i class="fa fa-print"></i> Print Report
        </button>
        <a href="javascript:void(0);" onclick="exportTableToCSV('sales-profit-report.csv')" class="btn btn-success">
            <i class="fa fa-file-excel-o"></i> Export CSV
        </a>
    </div>
</section>

<section class="content">

    <!-- ========================================================= -->
    <!-- Multi-Horizon Profit & Sales Overview (Day • Week • Month • Year) -->
    <!-- ========================================================= -->
    <div style="margin-bottom: 10px;">
        <h4 style="margin: 0 0 12px 0; font-weight: 700; color: #1e293b; display: flex; align-items: center; gap: 8px;">
            <i class="fa fa-pie-chart" style="color: #10b981;"></i> Profit & Sales Performance Overview
            <span style="font-size: 12px; font-weight: normal; color: #64748b;">(Multi-Horizon Snapshot)</span>
        </h4>
    </div>

    <div class="row">
        <!-- 1. PER DAY (Today) -->
        <div class="col-md-3 col-sm-6 col-xs-12">
            <div class="horizon-card" style="border-top: 4px solid #0284c7;">
                <div class="horizon-header">
                    <span class="horizon-badge badge-day"><i class="fa fa-calendar-o"></i> Today (Day)</span>
                    <a href="?filter_type=day&day_date=<?php echo $today_date; ?>" class="btn btn-xs btn-default" style="font-size: 11px;">Filter <i class="fa fa-arrow-right"></i></a>
                </div>
                <div style="font-size: 11px; text-transform: uppercase; color: #64748b; font-weight: 700;">Total Profit</div>
                <div class="horizon-profit">&#8369;<?php echo number_format($metrics_today['net_profit'], 2); ?></div>
                <div class="horizon-sales">
                    Sales: <strong>&#8369;<?php echo number_format($metrics_today['net_sales'], 2); ?></strong>
                    <span class="badge badge-success pull-right" style="background-color: #10b981;"><?php echo number_format($metrics_today['margin'], 1); ?>% Margin</span>
                </div>
                <div class="horizon-meta">
                    <span><strong><?php echo $metrics_today['order_count']; ?></strong> Orders</span>
                    <span><strong><?php echo $metrics_today['net_units']; ?></strong> Units Sold</span>
                </div>
            </div>
        </div>

        <!-- 2. PER WEEK (This Week) -->
        <div class="col-md-3 col-sm-6 col-xs-12">
            <div class="horizon-card" style="border-top: 4px solid #f59e0b;">
                <div class="horizon-header">
                    <span class="horizon-badge badge-week"><i class="fa fa-calendar-check-o"></i> This Week</span>
                    <a href="?filter_type=week&year=<?php echo $current_year; ?>&week=<?php echo date('W'); ?>" class="btn btn-xs btn-default" style="font-size: 11px;">Filter <i class="fa fa-arrow-right"></i></a>
                </div>
                <div style="font-size: 11px; text-transform: uppercase; color: #64748b; font-weight: 700;">Total Profit</div>
                <div class="horizon-profit">&#8369;<?php echo number_format($metrics_week['net_profit'], 2); ?></div>
                <div class="horizon-sales">
                    Sales: <strong>&#8369;<?php echo number_format($metrics_week['net_sales'], 2); ?></strong>
                    <span class="badge badge-success pull-right" style="background-color: #10b981;"><?php echo number_format($metrics_week['margin'], 1); ?>% Margin</span>
                </div>
                <div class="horizon-meta">
                    <span><strong><?php echo $metrics_week['order_count']; ?></strong> Orders</span>
                    <span><strong><?php echo $metrics_week['net_units']; ?></strong> Units Sold</span>
                </div>
            </div>
        </div>

        <!-- 3. PER MONTH (This Month) -->
        <div class="col-md-3 col-sm-6 col-xs-12">
            <div class="horizon-card" style="border-top: 4px solid #10b981;">
                <div class="horizon-header">
                    <span class="horizon-badge badge-month"><i class="fa fa-calendar"></i> This Month</span>
                    <a href="?filter_type=month&year=<?php echo $current_year; ?>&month=<?php echo (int)$current_month; ?>" class="btn btn-xs btn-default" style="font-size: 11px;">Filter <i class="fa fa-arrow-right"></i></a>
                </div>
                <div style="font-size: 11px; text-transform: uppercase; color: #64748b; font-weight: 700;">Total Profit</div>
                <div class="horizon-profit">&#8369;<?php echo number_format($metrics_month['net_profit'], 2); ?></div>
                <div class="horizon-sales">
                    Sales: <strong>&#8369;<?php echo number_format($metrics_month['net_sales'], 2); ?></strong>
                    <span class="badge badge-success pull-right" style="background-color: #10b981;"><?php echo number_format($metrics_month['margin'], 1); ?>% Margin</span>
                </div>
                <div class="horizon-meta">
                    <span><strong><?php echo $metrics_month['order_count']; ?></strong> Orders</span>
                    <span><strong><?php echo $metrics_month['net_units']; ?></strong> Units Sold</span>
                </div>
            </div>
        </div>

        <!-- 4. PER YEAR (This Year) -->
        <div class="col-md-3 col-sm-6 col-xs-12">
            <div class="horizon-card" style="border-top: 4px solid #8b5cf6;">
                <div class="horizon-header">
                    <span class="horizon-badge badge-year"><i class="fa fa-bar-chart"></i> This Year</span>
                    <a href="?filter_type=year&year=<?php echo $current_year; ?>" class="btn btn-xs btn-default" style="font-size: 11px;">Filter <i class="fa fa-arrow-right"></i></a>
                </div>
                <div style="font-size: 11px; text-transform: uppercase; color: #64748b; font-weight: 700;">Total Profit</div>
                <div class="horizon-profit">&#8369;<?php echo number_format($metrics_year['net_profit'], 2); ?></div>
                <div class="horizon-sales">
                    Sales: <strong>&#8369;<?php echo number_format($metrics_year['net_sales'], 2); ?></strong>
                    <span class="badge badge-success pull-right" style="background-color: #10b981;"><?php echo number_format($metrics_year['margin'], 1); ?>% Margin</span>
                </div>
                <div class="horizon-meta">
                    <span><strong><?php echo $metrics_year['order_count']; ?></strong> Orders</span>
                    <span><strong><?php echo $metrics_year['net_units']; ?></strong> Units Sold</span>
                </div>
            </div>
        </div>
    </div>

    <!-- ========================================================= -->
    <!-- Filter Control Panel -->
    <!-- ========================================================= -->
    <div class="filter-box no-print">
        <div class="filter-tabs">
            <a href="?filter_type=day&day_date=<?php echo $today_date; ?>" class="filter-btn <?php echo ($filter_type == 'day' && $selected_date == $today_date) ? 'active' : ''; ?>">
                <i class="fa fa-calendar-o"></i> Today
            </a>
            <a href="?filter_type=day&day_date=<?php echo date('Y-m-d', strtotime('-1 day')); ?>" class="filter-btn <?php echo ($filter_type == 'day' && $selected_date == date('Y-m-d', strtotime('-1 day'))) ? 'active' : ''; ?>">
                <i class="fa fa-calendar-minus-o"></i> Yesterday
            </a>
            <a href="?filter_type=week&year=<?php echo $current_year; ?>&week=<?php echo date('W'); ?>" class="filter-btn <?php echo ($filter_type == 'week' && $selected_week == date('W') && $selected_year == $current_year) ? 'active' : ''; ?>">
                <i class="fa fa-calendar-check-o"></i> This Week
            </a>
            <a href="?filter_type=month&year=<?php echo $current_year; ?>&month=<?php echo (int)$current_month; ?>" class="filter-btn <?php echo ($filter_type == 'month' && $selected_month == (int)$current_month && $selected_year == $current_year) ? 'active' : ''; ?>">
                <i class="fa fa-calendar"></i> This Month
            </a>
            <a href="?filter_type=quarter&year=<?php echo $current_year; ?>&quarter=<?php echo ceil((int)$current_month / 3); ?>" class="filter-btn <?php echo ($filter_type == 'quarter') ? 'active' : ''; ?>">
                <i class="fa fa-pie-chart"></i> Quarterly
            </a>
            <a href="?filter_type=year&year=<?php echo $current_year; ?>" class="filter-btn <?php echo ($filter_type == 'year' && $selected_year == $current_year) ? 'active' : ''; ?>">
                <i class="fa fa-bar-chart"></i> Yearly
            </a>
        </div>

        <form action="" method="get" class="form-inline" id="filterForm">
            <div class="form-group" style="margin-right: 12px;">
                <label style="margin-right: 8px; font-weight: 600; color: #334155;">Filter Mode:</label>
                <select name="filter_type" id="filter_type_select" class="form-control" onchange="toggleFilterInputs(this.value)">
                    <option value="day" <?php if($filter_type == 'day') echo 'selected'; ?>>By Day</option>
                    <option value="week" <?php if($filter_type == 'week') echo 'selected'; ?>>By Week</option>
                    <option value="month" <?php if($filter_type == 'month') echo 'selected'; ?>>By Month</option>
                    <option value="quarter" <?php if($filter_type == 'quarter') echo 'selected'; ?>>By Quarter</option>
                    <option value="year" <?php if($filter_type == 'year') echo 'selected'; ?>>By Year</option>
                    <option value="custom" <?php if($filter_type == 'custom') echo 'selected'; ?>>Custom Date Range</option>
                </select>
            </div>

            <!-- Day Selector -->
            <div class="form-group filter-input-group" id="group_day" style="display: <?php echo ($filter_type == 'day') ? 'inline-block' : 'none'; ?>; margin-right: 12px;">
                <label style="margin-right: 6px;">Select Date:</label>
                <input type="date" name="day_date" class="form-control" value="<?php echo htmlspecialchars($selected_date); ?>">
            </div>

            <!-- Week Selector -->
            <div class="form-group filter-input-group" id="group_week" style="display: <?php echo ($filter_type == 'week') ? 'inline-block' : 'none'; ?>; margin-right: 12px;">
                <label style="margin-right: 6px;">Year:</label>
                <select name="year" class="form-control" style="margin-right: 8px;">
                    <?php for($y = (int)$current_year; $y >= (int)$current_year - 5; $y--): ?>
                        <option value="<?php echo $y; ?>" <?php if($selected_year == $y) echo 'selected'; ?>><?php echo $y; ?></option>
                    <?php endfor; ?>
                </select>
                <label style="margin-right: 6px;">Week #:</label>
                <select name="week" class="form-control">
                    <?php for($w = 1; $w <= 53; $w++): ?>
                        <option value="<?php echo $w; ?>" <?php if($selected_week == $w) echo 'selected'; ?>>Week <?php echo $w; ?></option>
                    <?php endfor; ?>
                </select>
            </div>

            <!-- Month Selector -->
            <div class="form-group filter-input-group" id="group_month" style="display: <?php echo ($filter_type == 'month') ? 'inline-block' : 'none'; ?>; margin-right: 12px;">
                <label style="margin-right: 6px;">Year:</label>
                <select name="year" class="form-control" style="margin-right: 8px;">
                    <?php for($y = (int)$current_year; $y >= (int)$current_year - 5; $y--): ?>
                        <option value="<?php echo $y; ?>" <?php if($selected_year == $y) echo 'selected'; ?>><?php echo $y; ?></option>
                    <?php endfor; ?>
                </select>
                <label style="margin-right: 6px;">Month:</label>
                <select name="month" class="form-control">
                    <?php 
                    $months_names = [
                        1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
                        5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
                        9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
                    ];
                    foreach($months_names as $m_num => $m_name): 
                    ?>
                        <option value="<?php echo $m_num; ?>" <?php if($selected_month == $m_num) echo 'selected'; ?>><?php echo $m_name; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Quarter Selector -->
            <div class="form-group filter-input-group" id="group_quarter" style="display: <?php echo ($filter_type == 'quarter') ? 'inline-block' : 'none'; ?>; margin-right: 12px;">
                <label style="margin-right: 6px;">Year:</label>
                <select name="year" class="form-control" style="margin-right: 8px;">
                    <?php for($y = (int)$current_year; $y >= (int)$current_year - 5; $y--): ?>
                        <option value="<?php echo $y; ?>" <?php if($selected_year == $y) echo 'selected'; ?>><?php echo $y; ?></option>
                    <?php endfor; ?>
                </select>
                <label style="margin-right: 6px;">Quarter:</label>
                <select name="quarter" class="form-control">
                    <option value="1" <?php if($selected_quarter == 1) echo 'selected'; ?>>Q1 (Jan - Mar)</option>
                    <option value="2" <?php if($selected_quarter == 2) echo 'selected'; ?>>Q2 (Apr - Jun)</option>
                    <option value="3" <?php if($selected_quarter == 3) echo 'selected'; ?>>Q3 (Jul - Sep)</option>
                    <option value="4" <?php if($selected_quarter == 4) echo 'selected'; ?>>Q4 (Oct - Dec)</option>
                </select>
            </div>

            <!-- Year Selector -->
            <div class="form-group filter-input-group" id="group_year" style="display: <?php echo ($filter_type == 'year') ? 'inline-block' : 'none'; ?>; margin-right: 12px;">
                <label style="margin-right: 6px;">Year:</label>
                <select name="year" class="form-control">
                    <?php for($y = (int)$current_year; $y >= (int)$current_year - 5; $y--): ?>
                        <option value="<?php echo $y; ?>" <?php if($selected_year == $y) echo 'selected'; ?>><?php echo $y; ?></option>
                    <?php endfor; ?>
                </select>
            </div>

            <!-- Custom Date Range -->
            <div class="form-group filter-input-group" id="group_custom" style="display: <?php echo ($filter_type == 'custom') ? 'inline-block' : 'none'; ?>; margin-right: 12px;">
                <label style="margin-right: 6px;">From:</label>
                <input type="date" name="start_date" class="form-control" value="<?php echo htmlspecialchars($start_date_custom); ?>" style="margin-right: 8px;">
                <label style="margin-right: 6px;">To:</label>
                <input type="date" name="end_date" class="form-control" value="<?php echo htmlspecialchars($end_date_custom); ?>">
            </div>

            <button type="submit" class="btn btn-primary" style="background-color: #0284c7; border-color: #0284c7;">
                <i class="fa fa-filter"></i> Apply Filter
            </button>
            <a href="sales-report.php" class="btn btn-default" style="margin-left: 5px;">Reset</a>
        </form>
    </div>

    <!-- Active Filter Title Header -->
    <div style="margin-bottom: 20px;">
        <h4 style="margin: 0; font-weight: bold; color: #1e293b;">
            <i class="fa fa-clock-o text-primary"></i> Filtered Report Period: <span style="color: #0284c7;"><?php echo htmlspecialchars($filter_label); ?></span>
        </h4>
        <p style="margin: 3px 0 0 0; font-size: 13px; color: #64748b;">
            Showing sales revenue, capital cost, profit margins, returns, and itemized orders between <strong><?php echo date('M d, Y h:i A', strtotime($start_datetime)); ?></strong> and <strong><?php echo date('M d, Y h:i A', strtotime($end_datetime)); ?></strong>
        </p>
    </div>

    <!-- ========================================================= -->
    <!-- Analytics Key Metrics (KPI Cards for Filtered Period) -->
    <!-- ========================================================= -->
    <div class="row">
        <!-- 1. Gross Revenue -->
        <div class="col-md-4 col-sm-6 col-xs-12">
            <div class="stat-card bg-gradient-blue">
                <i class="fa fa-money icon-bg"></i>
                <h3>&#8369;<?php echo number_format($total_gross_revenue, 2); ?></h3>
                <p>Gross Sales Revenue</p>
            </div>
        </div>

        <!-- 2. Total Cost / Capital -->
        <div class="col-md-4 col-sm-6 col-xs-12">
            <div class="stat-card bg-gradient-slate">
                <i class="fa fa-cubes icon-bg"></i>
                <h3>&#8369;<?php echo number_format($total_gross_cost, 2); ?></h3>
                <p>Total Capital Cost (COGS)</p>
            </div>
        </div>

        <!-- 3. TOTAL PROFIT -->
        <div class="col-md-4 col-sm-6 col-xs-12">
            <div class="stat-card bg-gradient-green">
                <i class="fa fa-trophy icon-bg"></i>
                <h3>&#8369;<?php echo number_format($total_net_profit, 2); ?></h3>
                <p>Total Net Profit (<?php echo number_format($period_profit_margin, 1); ?>% Margin)</p>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- 4. Net Revenue -->
        <div class="col-md-4 col-sm-6 col-xs-12">
            <div class="stat-card bg-gradient-blue" style="background: linear-gradient(135deg, #0284c7 0%, #075985 100%);">
                <i class="fa fa-check-circle icon-bg"></i>
                <h3>&#8369;<?php echo number_format($total_net_revenue, 2); ?></h3>
                <p>Net Sales Revenue</p>
            </div>
        </div>

        <!-- 5. Returns & Refunds -->
        <div class="col-md-4 col-sm-6 col-xs-12">
            <div class="stat-card bg-gradient-red">
                <i class="fa fa-undo icon-bg"></i>
                <h3>&#8369;<?php echo number_format($total_refunds_amount, 2); ?></h3>
                <p>Total Returns & Refunds (<?php echo count($period_returns); ?> items)</p>
            </div>
        </div>

        <!-- 6. Completed Orders & Units -->
        <div class="col-md-4 col-sm-6 col-xs-12">
            <div class="stat-card bg-gradient-purple">
                <i class="fa fa-shopping-cart icon-bg"></i>
                <h3><?php echo number_format($total_orders_count); ?> <span style="font-size: 16px; font-weight: normal;">Orders (<?php echo $net_units_sold; ?> units)</span></h3>
                <p>Average Order Value: &#8369;<?php echo number_format($aov, 2); ?></p>
            </div>
        </div>
    </div>

    <!-- ========================================================= -->
    <!-- Visual Charts Analytics Row -->
    <!-- ========================================================= -->
    <div class="row">
        <!-- Sales & Profit Trend Chart -->
        <div class="col-md-8">
            <div class="chart-box">
                <div class="chart-header">
                    <span><i class="fa fa-area-chart text-primary"></i> Revenue & Profit Trend (&#8369;)</span>
                    <span style="font-size: 12px; font-weight: normal; color: #64748b;"><?php echo count($trend_labels); ?> active sales dates</span>
                </div>
                <div style="position: relative; height: 280px;">
                    <canvas id="revenueProfitTrendChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Payment Method Breakdown Chart -->
        <div class="col-md-4">
            <div class="chart-box">
                <div class="chart-header">
                    <span><i class="fa fa-pie-chart text-success"></i> Payment Channels</span>
                </div>
                <div style="position: relative; height: 280px;">
                    <canvas id="paymentMethodChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Top Products Ranking by Profit -->
    <div class="row">
        <div class="col-md-12">
            <div class="chart-box">
                <div class="chart-header">
                    <span><i class="fa fa-trophy text-warning"></i> Top Performing Products by Profit Contribution</span>
                </div>
                <div style="position: relative; height: 260px;">
                    <canvas id="topProductsChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- ========================================================= -->
    <!-- Returns Breakdown in this Period -->
    <!-- ========================================================= -->
    <?php if (count($period_returns) > 0): ?>
    <div class="row">
        <div class="col-md-12">
            <div class="box box-danger" style="border-radius: 8px;">
                <div class="box-header with-border" style="padding: 12px 18px; background-color: #fef2f2;">
                    <h3 class="box-title" style="font-weight: 700; color: #991b1b; font-size: 16px;">
                        <i class="fa fa-undo text-danger"></i> Returns & Refunds in this Period (<?php echo count($period_returns); ?> items)
                    </h3>
                    <div class="box-tools pull-right">
                        <span class="label label-danger" style="font-size: 12px; font-weight: bold;">
                            Total Refunded: -&#8369;<?php echo number_format($total_refunds_amount, 2); ?>
                        </span>
                    </div>
                </div>
                <div class="box-body table-responsive" style="padding: 10px 18px;">
                    <table class="table table-bordered table-hover table-striped" style="font-size: 13px;">
                        <thead>
                            <tr style="background: #f8fafc;">
                                <th>#</th>
                                <th>Return Ref</th>
                                <th>Return Date</th>
                                <th>Original Invoice</th>
                                <th>Customer</th>
                                <th>Product / Item</th>
                                <th class="text-center">Qty Returned</th>
                                <th class="text-right">Unit Price</th>
                                <th class="text-right">Refund Amount</th>
                                <th>Reason</th>
                                <th>Condition</th>
                                <th>Restock Action</th>
                                <th>Refund Method</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $r_idx = 0;
                            foreach ($period_returns as $pret): 
                                $r_idx++;
                                $is_sp = ($pret['item_type'] === 'SPECIAL_ORDER');
                                $is_rstk = ($pret['restock_status'] === 'RESTOCKED');
                            ?>
                            <tr>
                                <td><?php echo $r_idx; ?></td>
                                <td><strong style="font-family: monospace; color: #dc2626;"><?php echo htmlspecialchars($pret['return_reference']); ?></strong></td>
                                <td><?php echo date('M d, Y h:i A', strtotime($pret['return_date'])); ?></td>
                                <td><strong style="font-family: monospace; color: #0284c7;"><?php echo htmlspecialchars($pret['payment_id']); ?></strong></td>
                                <td><?php echo htmlspecialchars($pret['customer_name']); ?></td>
                                <td>
                                    <?php if ($is_sp): ?>
                                        <span class="label label-warning" style="background-color: #d97706; font-size: 9px; padding: 1px 4px; font-weight: bold;">SPECIAL ORDER</span><br>
                                    <?php endif; ?>
                                    <strong><?php echo htmlspecialchars($pret['product_name']); ?></strong>
                                    <?php if (!empty($pret['sku'])): ?>
                                        <span style="font-size: 11px; color: #64748b; font-family: monospace;">(<?php echo htmlspecialchars($pret['sku']); ?>)</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center font-bold" style="color: #991b1b; font-weight: bold;"><?php echo $pret['quantity_returned']; ?></td>
                                <td class="text-right">&#8369;<?php echo number_format($pret['item_unit_price'] ?: $pret['unit_price'], 2); ?></td>
                                <td class="text-right" style="font-weight: bold; color: #dc2626;">-&#8369;<?php echo number_format($pret['item_refund'], 2); ?></td>
                                <td><?php echo htmlspecialchars($pret['return_reason']); ?></td>
                                <td><span class="label label-default"><?php echo htmlspecialchars($pret['condition']); ?></span></td>
                                <td>
                                    <?php if ($is_rstk): ?>
                                        <span class="label label-success" style="background: #16a34a;"><i class="fa fa-check"></i> Restocked</span>
                                    <?php else: ?>
                                        <span class="label label-default"><i class="fa fa-ban"></i> Not Restocked</span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="label label-default"><?php echo htmlspecialchars($pret['refund_method']); ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr style="background: #fef2f2; font-weight: bold;">
                                <th colspan="8" class="text-right">Total Returns Deducted:</th>
                                <th class="text-right" style="color: #dc2626; font-size: 14px;">-&#8369;<?php echo number_format($total_refunds_amount, 2); ?></th>
                                <th colspan="4"></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ========================================================= -->
    <!-- Itemized Orders Table with Cost & Profit Breakdown -->
    <!-- ========================================================= -->
    <div class="row">
        <div class="col-md-12">
            <div class="box box-info" style="border-radius: 8px;">
                <div class="box-header with-border" style="padding: 12px 18px;">
                    <h3 class="box-title" style="font-weight: 700; color: #1e293b; font-size: 16px;">
                        <i class="fa fa-list-alt text-primary"></i> Itemized Sales Orders & Profitability in Period
                    </h3>
                </div>
                <div class="box-body table-responsive" style="padding: 10px 18px;">
                    <table id="example1" class="table table-bordered table-hover table-striped" style="font-size: 13px;">
                        <thead>
                            <tr style="background: #f8fafc;">
                                <th>#</th>
                                <th>Order Date</th>
                                <th>Transaction / Invoice ID</th>
                                <th>Customer</th>
                                <th>Purchased Items</th>
                                <th class="text-right">Delivery Fee</th>
                                <th>Payment Method</th>
                                <th class="text-right">Paid Sales</th>
                                <th class="text-right">Capital Cost</th>
                                <th class="text-right">Profit</th>
                                <th class="text-center">Status</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $count = 0;
                            foreach ($processed_orders as $row):
                                $count++;
                                $order_items = $row['items'];
                            ?>
                            <tr>
                                <td><?php echo $count; ?></td>
                                <td><?php echo date('M d, Y h:i A', strtotime($row['payment_date'])); ?></td>
                                <td>
                                    <span class="label label-info" style="font-size: 12px;">
                                        <?php echo htmlspecialchars($row['txnid'] ?: $row['payment_id']); ?>
                                    </span>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($row['customer_name']); ?></strong><br>
                                    <span style="font-size: 12px; color: #64748b;"><?php echo htmlspecialchars($row['customer_email']); ?></span>
                                </td>
                                <td>
                                    <?php foreach ($order_items as $it): ?>
                                        <div style="margin-bottom: 3px;">
                                            <strong><?php echo htmlspecialchars($it['product_name']); ?></strong> 
                                            &times; <?php echo $it['quantity']; ?>
                                            <span style="color: #64748b; font-size: 11px;">
                                                (@ &#8369;<?php echo number_format($it['unit_price'], 2); ?> | Ca: &#8369;<?php echo number_format($it['unit_capital'], 2); ?>)
                                            </span>
                                        </div>
                                    <?php endforeach; ?>
                                </td>
                                <td class="text-right">
                                    <?php if($row['delivery_fee'] > 0): ?>
                                        &#8369;<?php echo number_format($row['delivery_fee'], 2); ?>
                                    <?php else: ?>
                                        <span class="text-success">&#8369;0.00</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="label label-default"><?php echo htmlspecialchars($row['payment_method']); ?></span>
                                </td>
                                <td class="text-right" style="font-weight: bold; color: #0284c7;">
                                    &#8369;<?php echo number_format($row['paid_amount'], 2); ?>
                                </td>
                                <td class="text-right" style="color: #475569;">
                                    &#8369;<?php echo number_format($row['order_cost'], 2); ?>
                                </td>
                                <td class="text-right" style="font-weight: bold; color: #10b981;">
                                    +&#8369;<?php echo number_format($row['order_profit'], 2); ?>
                                    <br><span class="badge badge-success" style="background-color: #10b981; font-size: 10px;"><?php echo number_format($row['order_margin'], 1); ?>%</span>
                                </td>
                                <td class="text-center">
                                    <?php if($row['payment_status'] == 'Paid' || $row['payment_status'] == 'Completed'): ?>
                                        <span class="label label-success"><?php echo htmlspecialchars($row['payment_status']); ?></span>
                                    <?php else: ?>
                                        <span class="label label-warning"><?php echo htmlspecialchars($row['payment_status']); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <a href="#" data-toggle="modal" data-target="#po-report-modal-<?php echo $row['id']; ?>" class="btn btn-info btn-xs">
                                        <i class="fa fa-file-text-o"></i> View PO
                                    </a>

                                    <!-- Purchase Order Modal -->
                                    <div id="po-report-modal-<?php echo $row['id']; ?>" class="modal fade" role="dialog">
                                        <div class="modal-dialog modal-lg">
                                            <div class="modal-content">
                                                <div class="modal-header" style="background-color: #0284c7; color: #fff; text-align: left;">
                                                    <button type="button" class="close" data-dismiss="modal" style="color: #fff;">&times;</button>
                                                    <h4 class="modal-title" style="font-weight: bold;"><i class="fa fa-file-text-o"></i> Customer Purchase Order & Profit Breakdown</h4>
                                                </div>
                                                <div class="modal-body" id="po-report-print-<?php echo $row['id']; ?>" style="text-align: left; padding: 20px;">
                                                    <div style="border: 1px solid #e2e8f0; padding: 15px; border-radius: 6px;">
                                                        <div style="border-bottom: 2px solid #0284c7; padding-bottom: 10px; margin-bottom: 15px;">
                                                            <h3>E-CONSTRUCTION SUPPLY</h3>
                                                            <p style="margin: 0; color: #64748b;">Transaction ID: <strong><?php echo htmlspecialchars($row['txnid'] ?: $row['payment_id']); ?></strong></p>
                                                            <p style="margin: 0; color: #64748b;">Date: <?php echo date('M d, Y h:i A', strtotime($row['payment_date'])); ?></p>
                                                        </div>
                                                        <p><strong>Customer:</strong> <?php echo htmlspecialchars($row['customer_name']); ?> (<?php echo htmlspecialchars($row['customer_email']); ?>)</p>
                                                        <p><strong>Payment Status:</strong> <?php echo htmlspecialchars($row['payment_status']); ?> | <strong>Method:</strong> <?php echo htmlspecialchars($row['payment_method']); ?></p>
                                                        
                                                        <table class="table table-bordered" style="margin-top: 15px;">
                                                            <thead>
                                                                <tr style="background: #f8fafc;">
                                                                    <th>Item</th>
                                                                    <th>Size</th>
                                                                    <th>Color</th>
                                                                    <th class="text-center">Qty</th>
                                                                    <th class="text-right">Unit Price</th>
                                                                    <th class="text-right">Unit Capital</th>
                                                                    <th class="text-right">Subtotal Sales</th>
                                                                    <th class="text-right">Profit</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                <?php foreach($order_items as $oit): ?>
                                                                <tr>
                                                                    <td><?php echo htmlspecialchars($oit['product_name']); ?></td>
                                                                    <td><?php echo htmlspecialchars($oit['size'] ?: '-'); ?></td>
                                                                    <td><?php echo htmlspecialchars($oit['color'] ?: '-'); ?></td>
                                                                    <td class="text-center"><?php echo $oit['quantity']; ?></td>
                                                                    <td class="text-right">&#8369;<?php echo number_format($oit['unit_price'], 2); ?></td>
                                                                    <td class="text-right">&#8369;<?php echo number_format($oit['unit_capital'], 2); ?></td>
                                                                    <td class="text-right">&#8369;<?php echo number_format($oit['subtotal'], 2); ?></td>
                                                                    <td class="text-right" style="color: #10b981; font-weight: bold;">+&#8369;<?php echo number_format($oit['profit'], 2); ?></td>
                                                                </tr>
                                                                <?php endforeach; ?>
                                                                <tr style="font-weight: bold; background: #f8fafc;">
                                                                    <td colspan="6" class="text-right">Grand Total Paid:</td>
                                                                    <td class="text-right">&#8369;<?php echo number_format($row['paid_amount'], 2); ?></td>
                                                                    <td class="text-right" style="color: #10b981;">+&#8369;<?php echo number_format($row['order_profit'], 2); ?></td>
                                                                </tr>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr style="background: #f1f5f9; font-weight: bold;">
                                <th colspan="7" class="text-right">Gross Sales Total (<?php echo count($sales_orders); ?> Orders):</th>
                                <th class="text-right" style="color: #0284c7; font-size: 14px;">&#8369;<?php echo number_format($total_gross_revenue, 2); ?></th>
                                <th class="text-right" style="color: #475569; font-size: 14px;">&#8369;<?php echo number_format($total_gross_cost, 2); ?></th>
                                <th class="text-right" style="color: #10b981; font-size: 15px;">+&#8369;<?php echo number_format($total_gross_profit, 2); ?></th>
                                <th colspan="2"></th>
                            </tr>
                            <?php if ($total_refunds_amount > 0): ?>
                            <tr style="background: #fef2f2; font-weight: bold;">
                                <th colspan="7" class="text-right" style="color: #991b1b;">Less Returns & Refunds:</th>
                                <th class="text-right" style="color: #dc2626; font-size: 14px;">-&#8369;<?php echo number_format($total_refunds_amount, 2); ?></th>
                                <th class="text-right" style="color: #991b1b; font-size: 14px;">-&#8369;<?php echo number_format($total_returns_cost, 2); ?></th>
                                <th class="text-right" style="color: #dc2626; font-size: 14px;">-&#8369;<?php echo number_format($total_returns_profit_deduction, 2); ?></th>
                                <th colspan="2"></th>
                            </tr>
                            <?php endif; ?>
                            <tr style="background: #f0fdf4; font-weight: bold;">
                                <th colspan="7" class="text-right" style="color: #166534; font-size: 15px;">NET TOTALS:</th>
                                <th class="text-right" style="color: #15803d; font-size: 15px;">&#8369;<?php echo number_format($total_net_revenue, 2); ?></th>
                                <th class="text-right" style="color: #334155; font-size: 15px;">&#8369;<?php echo number_format(max(0, $total_gross_cost - $total_returns_cost), 2); ?></th>
                                <th class="text-right" style="color: #15803d; font-size: 17px; font-weight: 900;">
                                    +&#8369;<?php echo number_format($total_net_profit, 2); ?>
                                    <span style="font-size: 11px; font-weight: normal; display: block; color: #166534;">(<?php echo number_format($period_profit_margin, 1); ?>% Margin)</span>
                                </th>
                                <th colspan="2"></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>

</section>

<!-- Analytics Chart Initializer Script -->
<script>
function toggleFilterInputs(val) {
    $('.filter-input-group').hide();
    if(val === 'day') $('#group_day').show();
    else if(val === 'week') $('#group_week').show();
    else if(val === 'month') $('#group_month').show();
    else if(val === 'quarter') $('#group_quarter').show();
    else if(val === 'year') $('#group_year').show();
    else if(val === 'custom') $('#group_custom').show();
}

document.addEventListener("DOMContentLoaded", function() {
    // 1. Revenue & Profit Trend Chart
    var trendCtx = document.getElementById('revenueProfitTrendChart').getContext('2d');
    var trendLabels = <?php echo json_encode(!empty($trend_labels) ? $trend_labels : [date('Y-m-d')]); ?>;
    var trendRevenues = <?php echo json_encode(!empty($trend_revenues) ? $trend_revenues : [0]); ?>;
    var trendProfits = <?php echo json_encode(!empty($trend_profits) ? $trend_profits : [0]); ?>;
    var trendOrders = <?php echo json_encode(!empty($trend_orders_counts) ? $trend_orders_counts : [0]); ?>;

    new Chart(trendCtx, {
        type: 'line',
        data: {
            labels: trendLabels,
            datasets: [
                {
                    label: 'Gross Revenue (₱)',
                    data: trendRevenues,
                    borderColor: '#0284c7',
                    backgroundColor: 'rgba(2, 132, 199, 0.08)',
                    fill: true,
                    tension: 0.3,
                    yAxisID: 'y'
                },
                {
                    label: 'Total Profit (₱)',
                    data: trendProfits,
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16, 185, 129, 0.12)',
                    fill: true,
                    tension: 0.3,
                    yAxisID: 'y'
                },
                {
                    label: 'Orders Count',
                    data: trendOrders,
                    borderColor: '#f59e0b',
                    backgroundColor: 'rgba(245, 158, 11, 0.1)',
                    borderDash: [5, 5],
                    fill: false,
                    tension: 0.3,
                    yAxisID: 'y1'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false,
            },
            scales: {
                y: {
                    type: 'linear',
                    display: true,
                    position: 'left',
                    ticks: {
                        callback: function(value) { return '₱' + value.toLocaleString(); }
                    }
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    grid: { drawOnChartArea: false },
                    ticks: { precision: 0 }
                }
            }
        }
    });

    // 2. Payment Method Distribution Chart
    var pmCtx = document.getElementById('paymentMethodChart').getContext('2d');
    var pmLabels = <?php echo json_encode(array_keys($payment_methods_breakdown)); ?>;
    var pmData = <?php echo json_encode(array_values($payment_methods_breakdown)); ?>;

    new Chart(pmCtx, {
        type: 'doughnut',
        data: {
            labels: pmLabels.length ? pmLabels : ['No Sales'],
            datasets: [{
                data: pmData.length ? pmData : [1],
                backgroundColor: ['#0284c7', '#10b981', '#f59e0b', '#8b5cf6', '#ec4899', '#64748b']
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom' }
            }
        }
    });

    // 3. Top Products Chart (Revenue & Profit)
    var topProdCtx = document.getElementById('topProductsChart').getContext('2d');
    var topProdLabels = <?php echo json_encode(array_keys($top_products_list)); ?>;
    var topProdRevenues = <?php echo json_encode(array_column($top_products_list, 'revenue')); ?>;
    var topProdProfits = <?php echo json_encode(array_column($top_products_list, 'profit')); ?>;

    new Chart(topProdCtx, {
        type: 'bar',
        data: {
            labels: topProdLabels.length ? topProdLabels : ['No Products'],
            datasets: [
                {
                    label: 'Revenue (₱)',
                    data: topProdRevenues.length ? topProdRevenues : [0],
                    backgroundColor: '#0284c7',
                    borderRadius: 4
                },
                {
                    label: 'Profit (₱)',
                    data: topProdProfits.length ? topProdProfits : [0],
                    backgroundColor: '#10b981',
                    borderRadius: 4
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            indexAxis: 'y',
            scales: {
                x: {
                    ticks: {
                        callback: function(value) { return '₱' + value.toLocaleString(); }
                    }
                }
            }
        }
    });
});

// CSV Export Helper
function exportTableToCSV(filename) {
    var csv = [];
    var rows = document.querySelectorAll("#example1 tr");
    
    for (var i = 0; i < rows.length; i++) {
        var row = [], cols = rows[i].querySelectorAll("td, th");
        for (var j = 0; j < cols.length - 1; j++) { // Exclude action column
            var text = cols[j].innerText.replace(/(\r\n|\n|\r)/gm, " ").replace(/"/g, '""');
            row.push('"' + text.trim() + '"');
        }
        csv.push(row.join(","));
    }

    var csvFile = new Blob([csv.join("\n")], {type: "text/csv"});
    var downloadLink = document.createElement("a");
    downloadLink.download = filename;
    downloadLink.href = window.URL.createObjectURL(csvFile);
    downloadLink.style.display = "none";
    document.body.appendChild(downloadLink);
    downloadLink.click();
}
</script>

<?php require_once('footer.php'); ?>
