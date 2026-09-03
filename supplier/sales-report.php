<?php require_once('header.php'); ?>

<?php
// Initialize filter variables
$filter_type = isset($_GET['filter_type']) ? $_GET['filter_type'] : 'year';
$current_year = date('Y');
$current_month = date('m');
$today_date = date('Y-m-d');

$selected_date = isset($_GET['day_date']) && !empty($_GET['day_date']) ? $_GET['day_date'] : $today_date;
$selected_year = isset($_GET['year']) && !empty($_GET['year']) ? (int)$_GET['year'] : (int)$current_year;
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

// Fetch orders matching date range for this supplier
$stmt = $pdo->prepare("SELECT * FROM tbl_payment WHERE supplier_id = ? AND payment_date >= ? AND payment_date <= ? ORDER BY id DESC");
$stmt->execute(array($supplier_id, $start_datetime, $end_datetime));
$sales_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Metrics calculation
$total_revenue = 0;
$total_orders_count = count($sales_orders);
$total_units_sold = 0;
$total_delivery_collected = 0;
$payment_methods_breakdown = [];
$top_products = [];
$trend_data = [];

foreach ($sales_orders as $ord) {
    $paid_amt = (float)$ord['paid_amount'];
    $total_revenue += $paid_amt;

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
    foreach ($ord_items as $item) {
        $qty = (int)$item['quantity'];
        $u_price = (float)$item['unit_price'];
        $item_tot = $qty * $u_price;
        $items_subtotal += $item_tot;
        $total_units_sold += $qty;

        $p_name = $item['product_name'];
        if (!isset($top_products[$p_name])) {
            $top_products[$p_name] = [
                'name' => $p_name,
                'qty' => 0,
                'revenue' => 0
            ];
        }
        $top_products[$p_name]['qty'] += $qty;
        $top_products[$p_name]['revenue'] += $item_tot;
    }

    $delivery_fee = $paid_amt - $items_subtotal;
    if ($delivery_fee > 0) {
        $total_delivery_collected += $delivery_fee;
    }

    // Trend grouping by date
    $p_date_key = date('Y-m-d', strtotime($ord['payment_date']));
    if (!isset($trend_data[$p_date_key])) {
        $trend_data[$p_date_key] = [
            'revenue' => 0,
            'orders' => 0
        ];
    }
    $trend_data[$p_date_key]['revenue'] += $paid_amt;
    $trend_data[$p_date_key]['orders'] += 1;
}

$aov = $total_orders_count > 0 ? ($total_revenue / $total_orders_count) : 0;

// Sort top products by revenue descending
uasort($top_products, function($a, $b) {
    return $b['revenue'] <=> $a['revenue'];
});
$top_products_list = array_slice($top_products, 0, 8);

// Sort trend data by date ascending
ksort($trend_data);
$trend_labels = array_keys($trend_data);
$trend_revenues = array_column($trend_data, 'revenue');
$trend_orders_counts = array_column($trend_data, 'orders');
?>

<!-- Load Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>

<style>
.stat-card {
    border-radius: 8px;
    padding: 20px 22px;
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
    font-size: 60px;
    opacity: 0.15;
}
.stat-card h3 {
    margin: 0 0 6px 0;
    font-size: 26px;
    font-weight: 700;
}
.stat-card p {
    margin: 0;
    font-size: 13px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    opacity: 0.9;
}
.bg-gradient-blue {
    background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%);
}
.bg-gradient-green {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
}
.bg-gradient-amber {
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
}
.bg-gradient-purple {
    background: linear-gradient(135deg, #8b5cf6 0%, #6d28d9 100%);
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
    .main-header, .main-sidebar, .content-header, .filter-box, .no-print, .btn, .dataTables_filter, .dataTables_length, .dataTables_paginate, .dataTables_info {
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
		<h1><i class="fa fa-line-chart" style="color: #0284c7;"></i> Sales Report & Analytics</h1>
	</div>
    <div class="content-header-right no-print" style="float: right;">
        <button onclick="window.print();" class="btn btn-primary" style="background-color: #0284c7; border-color: #0284c7;">
            <i class="fa fa-print"></i> Print Report
        </button>
        <a href="javascript:void(0);" onclick="exportTableToCSV('sales-report.csv')" class="btn btn-success">
            <i class="fa fa-file-excel-o"></i> Export CSV
        </a>
    </div>
</section>

<section class="content">

    <!-- Filter Control Panel -->
    <div class="filter-box no-print">
        <div class="filter-tabs">
            <a href="?filter_type=day&day_date=<?php echo $today_date; ?>" class="filter-btn <?php echo ($filter_type == 'day' && $selected_date == $today_date) ? 'active' : ''; ?>">
                <i class="fa fa-calendar-o"></i> Today
            </a>
            <a href="?filter_type=day&day_date=<?php echo date('Y-m-d', strtotime('-1 day')); ?>" class="filter-btn <?php echo ($filter_type == 'day' && $selected_date == date('Y-m-d', strtotime('-1 day'))) ? 'active' : ''; ?>">
                <i class="fa fa-calendar-minus-o"></i> Yesterday
            </a>
            <a href="?filter_type=week&year=<?php echo $current_year; ?>&week=<?php echo date('W'); ?>" class="filter-btn <?php echo ($filter_type == 'week' && $selected_week == date('W')) ? 'active' : ''; ?>">
                <i class="fa fa-calendar-check-o"></i> This Week
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
            <i class="fa fa-clock-o text-primary"></i> Report Period: <span style="color: #0284c7;"><?php echo htmlspecialchars($filter_label); ?></span>
        </h4>
        <p style="margin: 3px 0 0 0; font-size: 13px; color: #64748b;">
            Showing sales performance and itemized orders between <strong><?php echo date('M d, Y h:i A', strtotime($start_datetime)); ?></strong> and <strong><?php echo date('M d, Y h:i A', strtotime($end_datetime)); ?></strong>
        </p>
    </div>

    <!-- Analytics Key Metrics (KPI Cards) -->
    <div class="row">
        <div class="col-md-3 col-sm-6 col-xs-12">
            <div class="stat-card bg-gradient-blue">
                <i class="fa fa-money icon-bg"></i>
                <h3>&#8369;<?php echo number_format($total_revenue, 2); ?></h3>
                <p>Gross Sales Revenue</p>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 col-xs-12">
            <div class="stat-card bg-gradient-green">
                <i class="fa fa-shopping-cart icon-bg"></i>
                <h3><?php echo number_format($total_orders_count); ?></h3>
                <p>Total Orders</p>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 col-xs-12">
            <div class="stat-card bg-gradient-amber">
                <i class="fa fa-cubes icon-bg"></i>
                <h3><?php echo number_format($total_units_sold); ?></h3>
                <p>Products Sold (Units)</p>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 col-xs-12">
            <div class="stat-card bg-gradient-purple">
                <i class="fa fa-calculator icon-bg"></i>
                <h3>&#8369;<?php echo number_format($aov, 2); ?></h3>
                <p>Average Order Value</p>
            </div>
        </div>
    </div>

    <!-- Visual Charts Analytics Row -->
    <div class="row">
        <!-- Sales Trend Chart -->
        <div class="col-md-8">
            <div class="chart-box">
                <div class="chart-header">
                    <span><i class="fa fa-area-chart text-primary"></i> Revenue Trend (&#8369;)</span>
                    <span style="font-size: 12px; font-weight: normal; color: #64748b;"><?php echo count($trend_labels); ?> active sales periods</span>
                </div>
                <div style="position: relative; height: 280px;">
                    <canvas id="revenueTrendChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Payment Method Share -->
        <div class="col-md-4">
            <div class="chart-box">
                <div class="chart-header">
                    <span><i class="fa fa-pie-chart text-primary"></i> Payment Methods</span>
                </div>
                <div style="position: relative; height: 280px;">
                    <canvas id="paymentMethodChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Top Products Analytics Row -->
    <div class="row">
        <div class="col-md-12">
            <div class="chart-box">
                <div class="chart-header">
                    <span><i class="fa fa-trophy text-warning"></i> Top Selling Products by Revenue (&#8369;)</span>
                </div>
                <div style="position: relative; height: 250px;">
                    <canvas id="topProductsChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Itemized "View Sales Report" Data Table -->
    <div class="row">
        <div class="col-md-12">
            <div class="box box-info" style="border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                <div class="box-header with-border">
                    <h3 class="box-title" style="font-weight: bold; color: #1e293b;">
                        <i class="fa fa-table text-primary"></i> Itemized Sales Transactions (<?php echo count($sales_orders); ?>)
                    </h3>
                </div>
                <div class="box-body table-responsive">
                    <table id="example1" class="table table-bordered table-striped table-hover">
                        <thead>
                            <tr style="background: #f8fafc;">
                                <th>#</th>
                                <th>Order Date & Time</th>
                                <th>Transaction / Order ID</th>
                                <th>Customer Details</th>
                                <th>Items Description</th>
                                <th class="text-right">Delivery Fee</th>
                                <th>Payment Method</th>
                                <th class="text-right">Total Amount</th>
                                <th class="text-center">Payment Status</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $count = 0;
                            foreach ($sales_orders as $row):
                                $count++;

                                // Fetch items for this order
                                $stmt_o = $pdo->prepare("SELECT * FROM tbl_order WHERE payment_id = ?");
                                $stmt_o->execute(array($row['payment_id']));
                                $order_items = $stmt_o->fetchAll(PDO::FETCH_ASSOC);

                                $sub_items_tot = 0;
                                foreach($order_items as $oi) {
                                    $sub_items_tot += ($oi['unit_price'] * $oi['quantity']);
                                }
                                $del_cost = $row['paid_amount'] - $sub_items_tot;
                                if ($del_cost < 0) $del_cost = 0;
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
                                        <div>
                                            <strong><?php echo htmlspecialchars($it['product_name']); ?></strong> 
                                            &times; <?php echo $it['quantity']; ?>
                                            <span style="color: #64748b; font-size: 11px;">(@ &#8369;<?php echo number_format($it['unit_price'], 2); ?>)</span>
                                        </div>
                                    <?php endforeach; ?>
                                </td>
                                <td class="text-right">
                                    <?php if($del_cost > 0): ?>
                                        &#8369;<?php echo number_format($del_cost, 2); ?>
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
                                                    <h4 class="modal-title" style="font-weight: bold;"><i class="fa fa-file-text-o"></i> Customer Purchase Order</h4>
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
                                                                    <th class="text-right">Subtotal</th>
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
                                                                    <td class="text-right">&#8369;<?php echo number_format($oit['unit_price'] * $oit['quantity'], 2); ?></td>
                                                                </tr>
                                                                <?php endforeach; ?>
                                                                <tr style="font-weight: bold; background: #f8fafc;">
                                                                    <td colspan="5" class="text-right">Grand Total Paid:</td>
                                                                    <td class="text-right">&#8369;<?php echo number_format($row['paid_amount'], 2); ?></td>
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
                                <th colspan="7" class="text-right">Total Summary (<?php echo count($sales_orders); ?> Orders):</th>
                                <th class="text-right" style="color: #0284c7; font-size: 15px;">&#8369;<?php echo number_format($total_revenue, 2); ?></th>
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
    else if(val === 'quarter') $('#group_quarter').show();
    else if(val === 'year') $('#group_year').show();
    else if(val === 'custom') $('#group_custom').show();
}

document.addEventListener("DOMContentLoaded", function() {
    // 1. Revenue Trend Chart
    var trendCtx = document.getElementById('revenueTrendChart').getContext('2d');
    var trendLabels = <?php echo json_encode(!empty($trend_labels) ? $trend_labels : [date('Y-m-d')]); ?>;
    var trendRevenues = <?php echo json_encode(!empty($trend_revenues) ? $trend_revenues : [0]); ?>;
    var trendOrders = <?php echo json_encode(!empty($trend_orders_counts) ? $trend_orders_counts : [0]); ?>;

    new Chart(trendCtx, {
        type: 'line',
        data: {
            labels: trendLabels,
            datasets: [
                {
                    label: 'Revenue (₱)',
                    data: trendRevenues,
                    borderColor: '#0284c7',
                    backgroundColor: 'rgba(2, 132, 199, 0.1)',
                    fill: true,
                    tension: 0.3,
                    yAxisID: 'y'
                },
                {
                    label: 'Orders Count',
                    data: trendOrders,
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
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

    // 3. Top Products Chart
    var topProdCtx = document.getElementById('topProductsChart').getContext('2d');
    var topProdLabels = <?php echo json_encode(array_keys($top_products_list)); ?>;
    var topProdRevenues = <?php echo json_encode(array_column($top_products_list, 'revenue')); ?>;
    var topProdQtys = <?php echo json_encode(array_column($top_products_list, 'qty')); ?>;

    new Chart(topProdCtx, {
        type: 'bar',
        data: {
            labels: topProdLabels.length ? topProdLabels : ['No Products'],
            datasets: [{
                label: 'Revenue (₱)',
                data: topProdRevenues.length ? topProdRevenues : [0],
                backgroundColor: '#0284c7',
                borderRadius: 4
            }]
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