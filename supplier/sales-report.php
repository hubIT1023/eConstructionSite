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
        $days_in_m = function_exists('cal_days_in_month') ? @cal_days_in_month(CAL_GREGORIAN, (int)$selected_month, (int)$selected_year) : (int)date('t', strtotime("$selected_year-$m_str-01"));
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
ensure_supplier_user_schema($pdo);
$products_map = [];
try {
    $stmt_prod_map = $pdo->prepare("
        SELECT p.p_id, p.p_name, p.p_current_price, p.p_new_price, p.p_capital_price, p.p_markup, p.ecat_id,
               COALESCE(ec.ecat_name, 'General') as ecat_name,
               COALESCE(mc.mcat_name, 'Building Materials') as mcat_name,
               COALESCE(tc.tcat_name, 'Building Materials') as tcat_name
        FROM tbl_product p
        LEFT JOIN tbl_end_category ec ON p.ecat_id = ec.ecat_id
        LEFT JOIN tbl_mid_category mc ON ec.mcat_id = mc.mcat_id
        LEFT JOIN tbl_top_category tc ON mc.tcat_id = tc.tcat_id
        WHERE p.supplier_id = ?
    ");
    $stmt_prod_map->execute(array($supplier_id));
    while ($prow = $stmt_prod_map->fetch(PDO::FETCH_ASSOC)) {
        $products_map[$prow['p_id']] = $prow;
    }
} catch (Exception $e) {
    try {
        $stmt_prod_map = $pdo->prepare("SELECT p_id, p_name, p_current_price, p_new_price, p_capital_price, p_markup FROM tbl_product WHERE supplier_id = ?");
        $stmt_prod_map->execute(array($supplier_id));
        while ($prow = $stmt_prod_map->fetch(PDO::FETCH_ASSOC)) {
            $products_map[$prow['p_id']] = $prow;
        }
    } catch (Exception $e2) {
        try {
            $stmt_prod_map = $pdo->prepare("SELECT p_id, p_name, p_current_price FROM tbl_product WHERE supplier_id = ?");
            $stmt_prod_map->execute(array($supplier_id));
            while ($prow = $stmt_prod_map->fetch(PDO::FETCH_ASSOC)) {
                $products_map[$prow['p_id']] = $prow;
            }
        } catch (Exception $e3) {}
    }
}

// Helper: Calculate item financials (Revenue, Unit Capital, Total Cost, Profit, Markup)
if (!function_exists('calculate_item_financials')) {
    function calculate_item_financials($product_id, $unit_price, $quantity, &$products_map, $item_type = 'STANDARD') {
        $qty = max(1, (int)$quantity);
        $u_price = (float)$unit_price;
        $p_id = (int)$product_id;
        $is_return_credit = ($item_type === 'RETURN_CREDIT' || $u_price < 0);
        $abs_unit_price = abs($u_price);
        $subtotal = $qty * $abs_unit_price;
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
                $unit_capital = round($abs_unit_price / (1 + ($markup / 100)), 2);
            }
        } else {
            // Fallback for special orders or unmapped items: default 20% markup
            $markup = 20.0;
            $unit_capital = round($abs_unit_price / 1.20, 2);
        }

        $total_capital = $unit_capital * $qty;
        
        if ($is_return_credit) {
            $profit = -abs(max(0.0, $subtotal - $total_capital));
            $subtotal = -$subtotal;
            $total_capital = -$total_capital;
        } else {
            $profit = max(0.0, $subtotal - $total_capital);
        }
        $margin_percent = ($abs_unit_price > 0 && $subtotal > 0) ? ($profit / $subtotal) * 100 : 0.0;

        return [
            'qty' => $qty,
            'unit_price' => $u_price,
            'abs_unit_price' => $abs_unit_price,
            'subtotal' => $subtotal,
            'unit_capital' => $unit_capital,
            'total_capital' => $total_capital,
            'profit' => $profit,
            'markup' => $markup,
            'margin_percent' => $margin_percent,
            'is_return_credit' => $is_return_credit
        ];
    }
}

// Helper: Calculate multi-period aggregated metrics (Sales, Cost, Profit, Margins, Orders)
if (!function_exists('get_period_financial_metrics')) {
    function get_period_financial_metrics($pdo, $supplier_id, $start_dt, $end_dt, &$products_map) {
        $stmt_pay = $pdo->prepare("SELECT * FROM tbl_payment WHERE supplier_id = ? AND payment_date >= ? AND payment_date <= ? AND payment_status NOT IN ('Cancelled', 'Void', 'Declined', 'Failed', 'Awaiting for Payment', 'Pending', 'UNPAID') AND paid_amount > 0");
        $stmt_pay->execute(array($supplier_id, $start_dt, $end_dt));
        $orders = $stmt_pay->fetchAll(PDO::FETCH_ASSOC);

        $returns = [];
        try {
            $stmt_ret = $pdo->prepare("SELECT r.*, ri.product_id, ri.quantity_returned, ri.refund_amount, ri.unit_price as item_unit_price, ri.restock_status 
                                       FROM tbl_returns r
                                       JOIN tbl_return_items ri ON r.return_id = ri.return_id
                                       WHERE r.supplier_id = ? AND r.return_date >= ? AND r.return_date <= ?
                                         AND r.status IN ('COMPLETED', 'APPROVED', 'REFUNDED')");
            $stmt_ret->execute(array($supplier_id, $start_dt, $end_dt));
            $returns = $stmt_ret->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $returns = [];
        }

        $gross_sales = 0.0;
        $total_cost = 0.0;
        $gross_profit = 0.0;
        $units_sold = 0;
        $order_count = count($orders);

        foreach ($orders as $ord) {
            $paid = (float)$ord['paid_amount'];
            $gross_sales += $paid;

            $stmt_items = $pdo->prepare("SELECT product_id, unit_price, quantity, item_type FROM tbl_order WHERE payment_id = ?");
            $stmt_items->execute(array($ord['payment_id']));
            $items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

            $order_capital_cost = 0.0;
            foreach ($items as $it) {
                $is_rc = (!empty($it['item_type']) && $it['item_type'] === 'RETURN_CREDIT') || (float)$it['unit_price'] < 0;
                $fin = calculate_item_financials($it['product_id'], (float)$it['unit_price'], $it['quantity'], $products_map, $it['item_type'] ?? 'STANDARD');
                
                if ($is_rc) {
                    // Restocked item capital credited back into store
                    $order_capital_cost += $fin['total_capital']; // total_capital is negative
                } else {
                    $order_capital_cost += $fin['total_capital']; // positive capital for sold goods
                    $units_sold += $fin['qty'];
                }
            }

            // Capital-First Rule: Cash collected is applied to net Capital Cost first; excess becomes Realized Profit
            $order_capital_cost_clean = max(0.0, $order_capital_cost);
            $order_capital_recovered = min($paid, $order_capital_cost_clean);
            $order_realized_profit = max(0.0, $paid - $order_capital_cost_clean);

            $total_cost += $order_capital_recovered;
            $gross_profit += $order_realized_profit;
        }

        $standalone_refunds_amt = 0.0;
        $exchange_credits_amt = 0.0;
        $units_ret = 0;
        $returns_profit_deduction = 0.0;
        foreach ($returns as $ret) {
            $is_exchange = in_array(trim($ret['refund_method']), ['Store Credit / Exchange', 'In-Register Trade-In', 'Exchange'], true);
            $r_fin = calculate_item_financials($ret['product_id'], $ret['item_unit_price'], $ret['quantity_returned'], $products_map);
            
            if ($is_exchange) {
                // In-register exchanges are already factored into tbl_payment.paid_amount and the exchange order items.
                // Do NOT subtract from net sales a second time!
                $exchange_credits_amt += (float)$ret['refund_amount'];
            } else {
                // Standalone Cash/Card/GCash Refund:
                $standalone_refunds_amt += (float)$ret['refund_amount'];
                $units_ret += (int)$ret['quantity_returned'];
                $returns_profit_deduction += $r_fin['profit'];
            }
        }

        $net_sales = max(0.0, $gross_sales - $standalone_refunds_amt);
        $net_profit = max(0.0, $gross_profit - $returns_profit_deduction);
        $net_units = max(0, $units_sold - $units_ret);
        $margin = $net_sales > 0 ? ($net_profit / $net_sales) * 100 : 0.0;

        return [
            'gross_sales' => $gross_sales,
            'refunds_amt' => $standalone_refunds_amt,
            'exchange_credits_amt' => $exchange_credits_amt,
            'total_refunds_amt' => ($standalone_refunds_amt + $exchange_credits_amt),
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
// Fetch orders matching date range for this supplier (excluding cancelled & unpaid held orders)
$stmt = $pdo->prepare("SELECT * FROM tbl_payment WHERE supplier_id = ? AND payment_date >= ? AND payment_date <= ? AND payment_status NOT IN ('Cancelled', 'Void', 'Declined', 'Failed', 'Awaiting for Payment', 'Pending', 'UNPAID') AND paid_amount > 0 ORDER BY id DESC");
$stmt->execute(array($supplier_id, $start_datetime, $end_datetime));
$sales_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch returns matching date range for this supplier
$period_returns = [];
$returns_by_payment_id = [];
try {
    $stmt_ret = $pdo->prepare("SELECT r.*, ri.product_id, ri.product_name, ri.quantity_returned, ri.refund_amount as item_refund, ri.unit_price as item_unit_price, ri.return_reason, ri.condition, ri.restock_status, ri.sku, ri.item_type, ri.special_order_reference, ri.product_details 
                               FROM tbl_returns r
                               JOIN tbl_return_items ri ON r.return_id = ri.return_id
                               WHERE r.supplier_id = ? AND r.return_date >= ? AND r.return_date <= ?
                                 AND r.status IN ('COMPLETED', 'APPROVED', 'REFUNDED')
                               ORDER BY r.return_id DESC");
    $stmt_ret->execute(array($supplier_id, $start_datetime, $end_datetime));
    $period_returns = $stmt_ret->fetchAll(PDO::FETCH_ASSOC);

    foreach ($period_returns as $pret) {
        $p_key = trim($pret['payment_id'] ?? '');
        if (!empty($p_key)) {
            if (!isset($returns_by_payment_id[$p_key])) {
                $returns_by_payment_id[$p_key] = [];
            }
            $returns_by_payment_id[$p_key][] = $pret;
        }
    }
} catch (Exception $e) {
    $period_returns = [];
    $returns_by_payment_id = [];
}

// Also index returns linked to payments in this period
if (!empty($sales_orders)) {
    $p_ids = array_unique(array_filter(array_map(function($o) { return trim($o['payment_id'] ?? ''); }, $sales_orders)));
    if (!empty($p_ids)) {
        $in_placeholders = implode(',', array_fill(0, count($p_ids), '?'));
        try {
            $stmt_ret_pay = $pdo->prepare("SELECT r.*, ri.product_id, ri.product_name, ri.quantity_returned, ri.refund_amount as item_refund, ri.unit_price as item_unit_price, ri.return_reason, ri.condition, ri.restock_status, ri.sku, ri.item_type, ri.special_order_reference, ri.product_details 
                                           FROM tbl_returns r
                                           JOIN tbl_return_items ri ON r.return_id = ri.return_id
                                           WHERE r.payment_id IN ($in_placeholders) AND r.status IN ('COMPLETED', 'APPROVED', 'REFUNDED')");
            $stmt_ret_pay->execute(array_values($p_ids));
            $matched_returns = $stmt_ret_pay->fetchAll(PDO::FETCH_ASSOC);
            foreach ($matched_returns as $mret) {
                $p_key = trim($mret['payment_id'] ?? '');
                if (!empty($p_key)) {
                    if (!isset($returns_by_payment_id[$p_key])) {
                        $returns_by_payment_id[$p_key] = [];
                    }
                    $exists = false;
                    foreach ($returns_by_payment_id[$p_key] as $existing_ret) {
                        if (($existing_ret['return_reference'] ?? '') === ($mret['return_reference'] ?? '') && ($existing_ret['product_name'] ?? '') === ($mret['product_name'] ?? '')) {
                            $exists = true;
                            break;
                        }
                    }
                    if (!$exists) {
                        $returns_by_payment_id[$p_key][] = $mret;
                    }
                }
            }
        } catch (Exception $e) {}
    }
}

// Load all supplier staff / cashiers for mapping
$supplier_staff_members = [];
try {
    $stmt_staff = $pdo->prepare("SELECT id, full_name, email, role, COALESCE(employee_id, '') as employee_id FROM tbl_supplier_user WHERE supplier_id = ? ORDER BY id ASC");
    $stmt_staff->execute(array($supplier_id));
    $supplier_staff_members = $stmt_staff->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $supplier_staff_members = [];
}

// Helper: Resolve cashier / staff member responsible for order
if (!function_exists('resolve_order_cashier')) {
    function resolve_order_cashier($ord, &$staff_members = []) {
        // 0. Dedicated cashier_name from tbl_payment
        if (!empty($ord['cashier_name'])) {
            return trim($ord['cashier_name']);
        }

        $info = !empty($ord['bank_transaction_info']) ? $ord['bank_transaction_info'] : '';
        
        // 1. Check "Sent by <Role>: <Staff Name>"
        if (preg_match('/Sent by\s+([^:]+):\s*([A-Za-z0-9\.\s\-_]+?)(?:\)|\||\n|\r|$)/i', $info, $m)) {
            $name = trim($m[2]);
            if (!empty($name)) return $name;
        }

        // 2. Check "Staff: <Staff Name>" or "Cashier: <Staff Name>"
        if (preg_match('/(?:Staff|Cashier|User|Handled by|Processed by):\s*([A-Za-z0-9\.\s\-_]+?)(?:\||\(|\n|\r|$)/i', $info, $m)) {
            $name = trim($m[1]);
            if (!empty($name)) return $name;
        }

        // 3. Match known staff full names in transaction text
        foreach ($staff_members as $sm) {
            if (!empty($sm['full_name']) && stripos($info, $sm['full_name']) !== false) {
                return $sm['full_name'];
            }
        }

        // 4. Default to assigned counter staff or primary supplier user
        if (!empty($staff_members)) {
            foreach ($staff_members as $sm) {
                if (in_array(strtoupper(trim($sm['role'])), ['CASHIER', 'EMPLOYEE', 'OPERATOR'])) {
                    return $sm['full_name'];
                }
            }
            return $staff_members[0]['full_name'];
        }

        return 'Counter Staff / Cashier';
    }
}

// Period detailed metrics
$total_gross_revenue = 0;
$total_orders_count = count($sales_orders);
$total_units_sold = 0;
$total_gross_cost = 0;
$total_gross_profit = 0;
$total_delivery_collected = 0;
$payment_methods_breakdown = [];
$top_products = [];
$top_products_monthly = [];
$cashier_metrics = [];
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

    // Month index (1 to 12)
    $order_month_num = (int)date('n', strtotime($ord['payment_date']));

    // Fetch order items
    $stmt_items = $pdo->prepare("SELECT * FROM tbl_order WHERE payment_id = ?");
    $stmt_items->execute(array($ord['payment_id']));
    $ord_items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

    $items_subtotal = 0;
    $order_capital_total = 0;
    $order_profit_potential = 0;
    $processed_items = [];

    foreach ($ord_items as $item) {
        $is_rc = (!empty($item['item_type']) && $item['item_type'] === 'RETURN_CREDIT') || (float)$item['unit_price'] < 0;
        $fin = calculate_item_financials($item['product_id'], (float)$item['unit_price'], $item['quantity'], $products_map, $item['item_type'] ?? 'STANDARD');
        
        $items_subtotal += $fin['subtotal'];
        $order_capital_total += $fin['total_capital']; // Negative for return credits
        $order_profit_potential += $fin['profit'];
        
        if (!$is_rc) {
            $total_units_sold += $fin['qty'];
        }

        $item_entry = array_merge($item, $fin);
        $item_entry['is_return_credit'] = $is_rc;
        $processed_items[] = $item_entry;

        $p_name = $item['product_name'];
        if (!$is_rc) {
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

            // Aggregate monthly metrics for top products timeline
            if (!isset($top_products_monthly[$p_name])) {
                $top_products_monthly[$p_name] = [
                    'name' => $p_name,
                    'months' => array_fill(1, 12, ['orders' => 0, 'units' => 0, 'revenue' => 0]),
                    'total_orders' => 0,
                    'total_units' => 0,
                    'total_revenue' => 0,
                    'total_profit' => 0
                ];
            }
            $top_products_monthly[$p_name]['months'][$order_month_num]['orders'] += 1;
            $top_products_monthly[$p_name]['months'][$order_month_num]['units']  += $fin['qty'];
            $top_products_monthly[$p_name]['months'][$order_month_num]['revenue']+= $fin['subtotal'];
            $top_products_monthly[$p_name]['total_orders'] += 1;
            $top_products_monthly[$p_name]['total_units']  += $fin['qty'];
            $top_products_monthly[$p_name]['total_revenue']+= $fin['subtotal'];
            $top_products_monthly[$p_name]['total_profit'] += $fin['profit'];
        }
    }

    // Capital-First Allocation Rule:
    // Any payment received is credited towards recovering capital first.
    // If paid amount exceeds total capital cost, the excess is recognized as realized profit.
    $order_capital_cost_clean = max(0.0, $order_capital_total);
    $order_capital_recovered = min($paid_amt, $order_capital_cost_clean);
    $order_realized_profit = max(0.0, $paid_amt - $order_capital_cost_clean);
    $order_capital_at_risk = max(0.0, $order_capital_cost_clean - $paid_amt);

    $total_gross_cost += $order_capital_recovered;
    $total_gross_profit += $order_realized_profit;

    $delivery_fee = $paid_amt - $items_subtotal;
    if ($delivery_fee > 0) {
        $total_delivery_collected += $delivery_fee;
    }

    $order_margin = $paid_amt > 0 ? ($order_realized_profit / $paid_amt) * 100 : 0;
    
    // Check for associated returns and exchange credits
    $has_exchange_credit = false;
    foreach ($processed_items as $p_it) {
        if (!empty($p_it['is_return_credit'])) {
            $has_exchange_credit = true;
            break;
        }
    }
    $ord_pay_key = trim($ord['payment_id'] ?? '');
    $associated_returns = (!empty($ord_pay_key) && isset($returns_by_payment_id[$ord_pay_key])) ? $returns_by_payment_id[$ord_pay_key] : [];

    $ord['items'] = $processed_items;
    $ord['items_subtotal'] = $items_subtotal;
    $ord['order_cost'] = $order_capital_recovered;
    $ord['order_capital_total'] = $order_capital_total;
    $ord['order_capital_at_risk'] = $order_capital_at_risk;
    $ord['order_profit'] = $order_realized_profit;
    $ord['order_margin'] = $order_margin;
    $ord['delivery_fee'] = max(0, $delivery_fee);
    $ord['has_exchange_credit'] = $has_exchange_credit;
    $ord['associated_returns'] = $associated_returns;
    $ord['has_standalone_refund'] = !empty($associated_returns);
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
    $trend_data[$p_date_key]['profit'] += $order_realized_profit;
    $trend_data[$p_date_key]['orders'] += 1;

    // Cashier performance metrics aggregation
    $cashier_name = resolve_order_cashier($ord, $supplier_staff_members);
    if (!isset($cashier_metrics[$cashier_name])) {
        $cashier_metrics[$cashier_name] = [
            'name' => $cashier_name,
            'orders' => 0,
            'units' => 0,
            'revenue' => 0,
            'profit' => 0,
            'avg_ticket' => 0,
            'share_percent' => 0,
            'monthly_revenue' => array_fill(1, 12, 0),
            'monthly_orders' => array_fill(1, 12, 0),
            'first_sale' => $ord['payment_date'],
            'last_sale' => $ord['payment_date'],
        ];
    }
    $cashier_metrics[$cashier_name]['orders'] += 1;
    $cashier_metrics[$cashier_name]['units']  += count($processed_items);
    $cashier_metrics[$cashier_name]['revenue']+= $paid_amt;
    $cashier_metrics[$cashier_name]['profit'] += $order_realized_profit;
    $cashier_metrics[$cashier_name]['monthly_revenue'][$order_month_num] += $paid_amt;
    $cashier_metrics[$cashier_name]['monthly_orders'][$order_month_num]  += 1;

    if (strtotime($ord['payment_date']) < strtotime($cashier_metrics[$cashier_name]['first_sale'])) {
        $cashier_metrics[$cashier_name]['first_sale'] = $ord['payment_date'];
    }
    if (strtotime($ord['payment_date']) > strtotime($cashier_metrics[$cashier_name]['last_sale'])) {
        $cashier_metrics[$cashier_name]['last_sale'] = $ord['payment_date'];
    }
}

// Calculate cashier metrics averages and share percentages
foreach ($cashier_metrics as &$cm) {
    $cm['avg_ticket'] = ($cm['orders'] > 0) ? ($cm['revenue'] / $cm['orders']) : 0;
    $cm['share_percent'] = ($total_gross_revenue > 0) ? ($cm['revenue'] / $total_gross_revenue) * 100 : 0;
}
unset($cm);

// Sort cashiers by total revenue descending
uasort($cashier_metrics, function($a, $b) {
    return $b['revenue'] <=> $a['revenue'];
});

// Returns Calculations
$total_refunds_amount = 0;
$total_standalone_refunds = 0;
$total_exchange_credits = 0;
$total_units_returned = 0;
$total_returns_cost = 0;
$total_returns_profit_deduction = 0;

foreach ($period_returns as $r_row) {
    $is_exchange = in_array(trim($r_row['refund_method']), ['Store Credit / Exchange', 'In-Register Trade-In', 'Exchange'], true);
    $r_fin = calculate_item_financials($r_row['product_id'], $r_row['item_unit_price'] ?: $r_row['unit_price'], $r_row['quantity_returned'], $products_map);
    
    $total_refunds_amount += (float)$r_row['item_refund'];
    $total_units_returned += (int)$r_row['quantity_returned'];
    
    if ($is_exchange) {
        $total_exchange_credits += (float)$r_row['item_refund'];
    } else {
        $total_standalone_refunds += (float)$r_row['item_refund'];
        $total_returns_cost += $r_fin['total_capital'];
        $total_returns_profit_deduction += $r_fin['profit'];
    }
}

// Net sales = Gross paid amount collected - Standalone cash/card refunds (since exchanges are already netted out in paid_amount)
$total_net_revenue = max(0.0, $total_gross_revenue - $total_standalone_refunds);
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

// Prepare Top 5 Products Monthly Timeline Datasets
uasort($top_products_monthly, function($a, $b) {
    return $b['total_profit'] <=> $a['total_profit'];
});
$top_5_products_monthly = array_slice($top_products_monthly, 0, 5);

$palette_colors = [
    ['border' => '#0284c7', 'bg' => 'rgba(2, 132, 199, 0.85)', 'light' => 'rgba(2, 132, 199, 0.15)'],
    ['border' => '#10b981', 'bg' => 'rgba(16, 185, 129, 0.85)', 'light' => 'rgba(16, 185, 129, 0.15)'],
    ['border' => '#f59e0b', 'bg' => 'rgba(245, 158, 11, 0.85)', 'light' => 'rgba(245, 158, 11, 0.15)'],
    ['border' => '#8b5cf6', 'bg' => 'rgba(139, 92, 246, 0.85)', 'light' => 'rgba(139, 92, 246, 0.15)'],
    ['border' => '#ec4899', 'bg' => 'rgba(236, 72, 153, 0.85)', 'light' => 'rgba(236, 72, 153, 0.15)'],
    ['border' => '#06b6d4', 'bg' => 'rgba(6, 182, 212, 0.85)', 'light' => 'rgba(6, 182, 212, 0.15)']
];

$month_names_12 = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

$top_prod_monthly_datasets_orders = [];
$top_prod_monthly_datasets_units = [];
$p_idx = 0;
foreach ($top_5_products_monthly as $p_name => $p_data) {
    $c = $palette_colors[$p_idx % count($palette_colors)];
    $orders_arr = [];
    $units_arr = [];
    for ($m = 1; $m <= 12; $m++) {
        $orders_arr[] = $p_data['months'][$m]['orders'];
        $units_arr[]  = $p_data['months'][$m]['units'];
    }
    $top_prod_monthly_datasets_orders[] = [
        'label' => $p_name,
        'data' => $orders_arr,
        'backgroundColor' => $c['bg'],
        'borderColor' => $c['border'],
        'borderWidth' => 1.5,
        'borderRadius' => 4
    ];
    $top_prod_monthly_datasets_units[] = [
        'label' => $p_name,
        'data' => $units_arr,
        'backgroundColor' => $c['bg'],
        'borderColor' => $c['border'],
        'borderWidth' => 1.5,
        'borderRadius' => 4
    ];
    $p_idx++;
}

// Prepare Cashier Chart Datasets
$cashier_names = array_column($cashier_metrics, 'name');
$cashier_revenues = array_column($cashier_metrics, 'revenue');
$cashier_orders = array_column($cashier_metrics, 'orders');
$cashier_avg_tickets = array_column($cashier_metrics, 'avg_ticket');

// =============================================================
// 5. Monthly Product Leaderboard Data Aggregation (Top 50 Default)
// =============================================================
$lb_year_int = (int)$selected_year;
if (isset($_GET['lb_year']) && is_numeric($_GET['lb_year'])) {
    $lb_year_int = (int)$_GET['lb_year'];
}
$lb_selected_month = isset($_GET['lb_month']) ? (int)$_GET['lb_month'] : (int)date('n');
if ($lb_selected_month < 1 || $lb_selected_month > 12) {
    $lb_selected_month = (int)date('n');
}
$lb_selected_topn = isset($_GET['lb_topn']) ? $_GET['lb_topn'] : '50';
$lb_selected_metric = isset($_GET['lb_metric']) ? $_GET['lb_metric'] : 'orders';

// Fetch all order line items for the entire leaderboard year
$stmt_lb_all = $pdo->prepare("
    SELECT p.id as payment_id, p.payment_date, p.paid_amount,
           o.product_id, o.product_name, o.unit_price, o.quantity, o.item_type
    FROM tbl_payment p
    JOIN tbl_order o ON p.payment_id = o.payment_id
    WHERE p.supplier_id = ? 
      AND p.payment_date >= ? 
      AND p.payment_date <= ?
      AND p.payment_status NOT IN ('Cancelled', 'Void', 'Declined', 'Failed', 'Awaiting for Payment', 'Pending', 'UNPAID')
      AND p.paid_amount > 0
    ORDER BY p.payment_date ASC
");
$stmt_lb_all->execute(array($supplier_id, $lb_year_int . '-01-01 00:00:00', $lb_year_int . '-12-31 23:59:59'));
$lb_all_rows = $stmt_lb_all->fetchAll(PDO::FETCH_ASSOC);

// Structure: $lb_monthly_raw[month_num][product_name] = [...]
$lb_monthly_raw = [];
for ($m = 1; $m <= 12; $m++) {
    $lb_monthly_raw[$m] = [];
}
$lb_monthly_raw['all'] = [];
$lb_order_track = [];

foreach ($lb_all_rows as $lb_row) {
    // Skip trade-in exchange credit lines from leaderboard
    if ((!empty($lb_row['item_type']) && $lb_row['item_type'] === 'RETURN_CREDIT') || (float)$lb_row['unit_price'] < 0) {
        continue;
    }

    $p_date = strtotime($lb_row['payment_date']);
    $m_idx = (int)date('n', $p_date);
    $p_name = $lb_row['product_name'];
    $p_id = (int)$lb_row['product_id'];
    $qty = (int)$lb_row['quantity'];
    $u_price = (float)$lb_row['unit_price'];
    $pay_id = $lb_row['payment_id'];

    $fin = calculate_item_financials($p_id, $u_price, $qty, $products_map, $lb_row['item_type'] ?? 'STANDARD');
    
    // Category resolution
    $cat_name = 'Building Materials';
    if ($p_id > 0 && isset($products_map[$p_id])) {
        $cat_name = !empty($products_map[$p_id]['ecat_name']) ? $products_map[$p_id]['ecat_name'] : 
                    (!empty($products_map[$p_id]['mcat_name']) ? $products_map[$p_id]['mcat_name'] : 'Building Materials');
    }

    // Accumulate for specific month and for 'all'
    foreach ([$m_idx, 'all'] as $tgt_m) {
        if (!isset($lb_monthly_raw[$tgt_m][$p_name])) {
            $lb_monthly_raw[$tgt_m][$p_name] = [
                'id' => $p_id,
                'name' => $p_name,
                'category' => $cat_name,
                'orders' => 0,
                'units' => 0,
                'revenue' => 0.0,
                'cost' => 0.0,
                'profit' => 0.0,
                'margin' => 0.0
            ];
            $lb_order_track[$tgt_m][$p_name] = [];
        }

        // Count distinct orders containing this item
        if (!isset($lb_order_track[$tgt_m][$p_name][$pay_id])) {
            $lb_order_track[$tgt_m][$p_name][$pay_id] = true;
            $lb_monthly_raw[$tgt_m][$p_name]['orders'] += 1;
        }

        $lb_monthly_raw[$tgt_m][$p_name]['units'] += $qty;
        $lb_monthly_raw[$tgt_m][$p_name]['revenue'] += $fin['subtotal'];
        $lb_monthly_raw[$tgt_m][$p_name]['cost'] += $fin['total_capital'];
        $lb_monthly_raw[$tgt_m][$p_name]['profit'] += $fin['profit'];
    }
}

// Calculate margins
foreach ($lb_monthly_raw as $m_key => &$p_list) {
    foreach ($p_list as &$p_item) {
        $p_item['margin'] = ($p_item['revenue'] > 0) ? ($p_item['profit'] / $p_item['revenue']) * 100 : 0.0;
    }
}
unset($p_list, $p_item);

// Pre-calculate ranked movement for each month (1..12 and 'all') across 4 metrics
$monthly_leaderboard_payload = [];
$metrics_keys = ['orders', 'units', 'revenue', 'profit'];

for ($m = 1; $m <= 12; $m++) {
    $monthly_leaderboard_payload[$m] = [];
    $prev_m = ($m > 1) ? ($m - 1) : 12;

    foreach ($metrics_keys as $met) {
        // Current month list sorted by metric
        $curr_items = array_values($lb_monthly_raw[$m]);
        usort($curr_items, function($a, $b) use ($met) {
            if ($b[$met] == $a[$met]) {
                return $b['revenue'] <=> $a['revenue'];
            }
            return $b[$met] <=> $a[$met];
        });

        // Previous month ranks
        $prev_items = array_values($lb_monthly_raw[$prev_m]);
        usort($prev_items, function($a, $b) use ($met) {
            if ($b[$met] == $a[$met]) {
                return $b['revenue'] <=> $a['revenue'];
            }
            return $b[$met] <=> $a[$met];
        });
        $prev_ranks = [];
        foreach ($prev_items as $p_rank_idx => $p_obj) {
            if ($p_obj[$met] > 0) {
                $prev_ranks[$p_obj['name']] = $p_rank_idx + 1;
            }
        }

        // Build ranked item entries with MoM movement
        $ranked_list = [];
        $rank_num = 1;
        foreach ($curr_items as $c_item) {
            if ($c_item[$met] <= 0 && $c_item['revenue'] <= 0) continue;

            $item_copy = $c_item;
            $item_copy['rank'] = $rank_num;

            if (isset($prev_ranks[$c_item['name']])) {
                $p_rank = $prev_ranks[$c_item['name']];
                $item_copy['prev_rank'] = $p_rank;
                $diff = $p_rank - $rank_num; // positive means rank improved (e.g. was #5, now #2 -> +3)
                if ($diff > 0) {
                    $item_copy['movement_type'] = 'up';
                    $item_copy['movement_diff'] = $diff;
                    $item_copy['movement_label'] = '▲ +' . $diff;
                } elseif ($diff < 0) {
                    $item_copy['movement_type'] = 'down';
                    $item_copy['movement_diff'] = abs($diff);
                    $item_copy['movement_label'] = '▼ -' . abs($diff);
                } else {
                    $item_copy['movement_type'] = 'same';
                    $item_copy['movement_diff'] = 0;
                    $item_copy['movement_label'] = '― SAME';
                }
            } else {
                $item_copy['prev_rank'] = null;
                $item_copy['movement_type'] = 'new';
                $item_copy['movement_diff'] = 0;
                $item_copy['movement_label'] = '★ NEW';
            }

            $ranked_list[] = $item_copy;
            $rank_num++;
        }

        $monthly_leaderboard_payload[$m][$met] = $ranked_list;
    }
}

// Build 'all' for full year summary
$monthly_leaderboard_payload['all'] = [];
foreach ($metrics_keys as $met) {
    $all_items = array_values($lb_monthly_raw['all']);
    usort($all_items, function($a, $b) use ($met) {
        if ($b[$met] == $a[$met]) {
            return $b['revenue'] <=> $a['revenue'];
        }
        return $b[$met] <=> $a[$met];
    });
    $ranked_all = [];
    $rank_num = 1;
    foreach ($all_items as $a_item) {
        if ($a_item[$met] <= 0 && $a_item['revenue'] <= 0) continue;
        $item_copy = $a_item;
        $item_copy['rank'] = $rank_num;
        $item_copy['prev_rank'] = null;
        $item_copy['movement_type'] = 'same';
        $item_copy['movement_diff'] = 0;
        $item_copy['movement_label'] = '―';
        $ranked_all[] = $item_copy;
        $rank_num++;
    }
    $monthly_leaderboard_payload['all'][$met] = $ranked_all;
}

$monthly_leaderboard_json = json_encode($monthly_leaderboard_payload);

// Build 12-Month Annual Product Profiles & Velocity Map for 1-Year Drill-Down
$product_annual_trends = [];
if (!empty($lb_monthly_raw['all']) && is_array($lb_monthly_raw['all'])) {
    foreach ($lb_monthly_raw['all'] as $p_name => $all_p_data) {
        $p_id = (int)($all_p_data['id'] ?? 0);
        $cat = $all_p_data['category'] ?? 'Building Materials';
        $tot_orders = (int)($all_p_data['orders'] ?? 0);
        $tot_units = (int)($all_p_data['units'] ?? 0);
        $tot_rev = (float)($all_p_data['revenue'] ?? 0.0);
        $tot_profit = (float)($all_p_data['profit'] ?? 0.0);
        $avg_rate = ($tot_orders > 0) ? round($tot_units / $tot_orders, 2) : 0.0;

        $monthly_arr = [];
        $peak_month = 1;
        $peak_month_orders = -1;

        for ($m = 1; $m <= 12; $m++) {
            $m_entry = $lb_monthly_raw[$m][$p_name] ?? null;
            $m_orders = $m_entry ? (int)$m_entry['orders'] : 0;
            $m_units = $m_entry ? (int)$m_entry['units'] : 0;
            $m_rev = $m_entry ? (float)$m_entry['revenue'] : 0.0;
            $m_profit = $m_entry ? (float)$m_entry['profit'] : 0.0;
            $m_rate = ($m_orders > 0) ? round($m_units / $m_orders, 2) : 0.0;

            if ($m_orders > $peak_month_orders) {
                $peak_month_orders = $m_orders;
                $peak_month = $m;
            }

            $monthly_arr[$m] = [
                'month' => $m,
                'orders' => $m_orders,
                'units' => $m_units,
                'revenue' => $m_rev,
                'profit' => $m_profit,
                'rate' => $m_rate
            ];
        }

        $product_annual_trends[$p_name] = [
            'id' => $p_id,
            'name' => $p_name,
            'category' => $cat,
            'annual_orders' => $tot_orders,
            'annual_units' => $tot_units,
            'annual_revenue' => $tot_rev,
            'annual_profit' => $tot_profit,
            'avg_purchase_rate' => $avg_rate,
            'peak_month' => $peak_month,
            'peak_orders' => max(0, $peak_month_orders),
            'monthly' => $monthly_arr
        ];
    }
}

$product_annual_trends_json = json_encode($product_annual_trends);

// =============================================================
// 6. Top Profit & Order Velocity Horizon Data Aggregation
// (Daily, Weekly, Monthly, Yearly, Multi-Year Trend)
// =============================================================
$stmt_horizon_all = $pdo->prepare("
    SELECT p.id as payment_id, p.payment_date, p.paid_amount,
           o.product_id, o.product_name, o.unit_price, o.quantity, o.item_type
    FROM tbl_payment p
    JOIN tbl_order o ON p.payment_id = o.payment_id
    WHERE p.supplier_id = ? 
      AND p.payment_status NOT IN ('Cancelled', 'Void', 'Declined', 'Failed', 'Awaiting for Payment', 'Pending', 'UNPAID')
      AND p.paid_amount > 0
    ORDER BY p.payment_date ASC
");
$stmt_horizon_all->execute(array($supplier_id));
$horizon_all_rows = $stmt_horizon_all->fetchAll(PDO::FETCH_ASSOC);

$hz_daily_raw = [];
$hz_weekly_raw = [];
$hz_monthly_raw = [];
$hz_yearly_raw = [];
$hz_order_track = [];
$hz_available_dates = [];
$hz_available_weeks = [];
$hz_available_months = [];
$hz_available_years = [];

foreach ($horizon_all_rows as $hz_row) {
    if ((!empty($hz_row['item_type']) && $hz_row['item_type'] === 'RETURN_CREDIT') || (float)$hz_row['unit_price'] < 0) {
        continue;
    }
    $ts = strtotime($hz_row['payment_date']);
    $d_key = date('Y-m-d', $ts);
    $w_key = date('o-\WW', $ts);
    $m_key = date('Y-m', $ts);
    $y_key = date('Y', $ts);

    $p_id = (int)$hz_row['product_id'];
    $p_name = $hz_row['product_name'];
    $qty = (int)$hz_row['quantity'];
    $u_price = (float)$hz_row['unit_price'];
    $pay_id = $hz_row['payment_id'];

    $fin = calculate_item_financials($p_id, $u_price, $qty, $products_map, $hz_row['item_type'] ?? 'STANDARD');

    $cat_name = 'Building Materials';
    if ($p_id > 0 && isset($products_map[$p_id])) {
        $cat_name = !empty($products_map[$p_id]['ecat_name']) ? $products_map[$p_id]['ecat_name'] : 
                    (!empty($products_map[$p_id]['mcat_name']) ? $products_map[$p_id]['mcat_name'] : 'Building Materials');
    }

    $gran_map = [
        'daily' => [$d_key, &$hz_daily_raw, &$hz_available_dates],
        'weekly' => [$w_key, &$hz_weekly_raw, &$hz_available_weeks],
        'monthly' => [$m_key, &$hz_monthly_raw, &$hz_available_months],
        'yearly' => [$y_key, &$hz_yearly_raw, &$hz_available_years]
    ];

    foreach ($gran_map as $g_type => &$g_info) {
        $k = $g_info[0];
        $t_arr = &$g_info[1];
        $a_arr = &$g_info[2];

        if (!isset($t_arr[$k])) {
            $t_arr[$k] = [];
            $a_arr[$k] = true;
        }
        if (!isset($t_arr[$k][$p_name])) {
            $t_arr[$k][$p_name] = [
                'id' => $p_id,
                'name' => $p_name,
                'category' => $cat_name,
                'orders' => 0,
                'units' => 0,
                'revenue' => 0.0,
                'cost' => 0.0,
                'profit' => 0.0,
                'margin' => 0.0
            ];
            $hz_order_track[$g_type][$k][$p_name] = [];
        }

        if (!isset($hz_order_track[$g_type][$k][$p_name][$pay_id])) {
            $hz_order_track[$g_type][$k][$p_name][$pay_id] = true;
            $t_arr[$k][$p_name]['orders'] += 1;
        }
        $t_arr[$k][$p_name]['units'] += $qty;
        $t_arr[$k][$p_name]['revenue'] += $fin['subtotal'];
        $t_arr[$k][$p_name]['cost'] += $fin['total_capital'];
        $t_arr[$k][$p_name]['profit'] += $fin['profit'];
    }
}

// Convert associative arrays to indexed lists with margins calculated
$hz_processed = [
    'daily' => [],
    'weekly' => [],
    'monthly' => [],
    'yearly' => []
];

foreach (['daily' => $hz_daily_raw, 'weekly' => $hz_weekly_raw, 'monthly' => $hz_monthly_raw, 'yearly' => $hz_yearly_raw] as $g_key => $period_data) {
    foreach ($period_data as $pk => $p_items) {
        $items_list = array_values($p_items);
        foreach ($items_list as &$it) {
            $it['margin'] = ($it['revenue'] > 0) ? round(($it['profit'] / $it['revenue']) * 100, 1) : 0.0;
        }
        unset($it);
        $hz_processed[$g_key][$pk] = $items_list;
    }
}

// Build Yearly Trend Multi-Year Comparison Dataset
$distinct_years = array_keys($hz_available_years);
sort($distinct_years);

$all_time_products = [];
foreach ($hz_yearly_raw as $y_k => $y_items) {
    foreach ($y_items as $p_name => $p_obj) {
        if (!isset($all_time_products[$p_name])) {
            $all_time_products[$p_name] = [
                'name' => $p_name,
                'category' => $p_obj['category'],
                'total_profit' => 0.0,
                'total_orders' => 0,
                'total_units' => 0,
                'total_revenue' => 0.0,
                'years' => []
            ];
        }
        $all_time_products[$p_name]['total_profit'] += $p_obj['profit'];
        $all_time_products[$p_name]['total_orders'] += $p_obj['orders'];
        $all_time_products[$p_name]['total_units'] += $p_obj['units'];
        $all_time_products[$p_name]['total_revenue'] += $p_obj['revenue'];
        $all_time_products[$p_name]['years'][$y_k] = [
            'profit' => $p_obj['profit'],
            'orders' => $p_obj['orders'],
            'units' => $p_obj['units'],
            'revenue' => $p_obj['revenue'],
            'margin' => ($p_obj['revenue'] > 0) ? round(($p_obj['profit'] / $p_obj['revenue']) * 100, 1) : 0.0
        ];
    }
}
foreach ($all_time_products as &$at_p) {
    $at_p['margin'] = ($at_p['total_revenue'] > 0) ? round(($at_p['total_profit'] / $at_p['total_revenue']) * 100, 1) : 0.0;
    $at_p['profit'] = $at_p['total_profit'];
    $at_p['orders'] = $at_p['total_orders'];
    $at_p['units'] = $at_p['total_units'];
    $at_p['revenue'] = $at_p['total_revenue'];
}
unset($at_p);

uasort($all_time_products, function($a, $b) {
    return $b['total_profit'] <=> $a['total_profit'];
});

$hz_payload = [
    'daily' => $hz_processed['daily'],
    'weekly' => $hz_processed['weekly'],
    'monthly' => $hz_processed['monthly'],
    'yearly' => $hz_processed['yearly'],
    'yearly_trend' => [
        'years' => $distinct_years,
        'products' => array_values($all_time_products)
    ],
    'meta' => [
        'latest_date' => !empty($hz_available_dates) ? max(array_keys($hz_available_dates)) : date('Y-m-d'),
        'today_date' => date('Y-m-d'),
        'available_dates' => array_keys($hz_available_dates),
        'available_weeks' => array_keys($hz_available_weeks),
        'available_months' => array_keys($hz_available_months),
        'available_years' => $distinct_years
    ]
];
$hz_payload_json = json_encode($hz_payload);

// =============================================================
// 7. Daily Cash Inventory & Cashier End-of-Day Tally Aggregation
// =============================================================
$stmt_cashier_all = $pdo->prepare("
    SELECT p.id, p.payment_id, p.payment_date, p.paid_amount, p.payment_method, 
           p.payment_status, p.cashier_id, p.cashier_name, p.bank_transaction_info,
           p.customer_name, p.customer_email
    FROM tbl_payment p
    WHERE p.supplier_id = ? 
      AND p.payment_status NOT IN ('Cancelled', 'Void', 'Declined', 'Failed', 'Awaiting for Payment', 'Pending', 'UNPAID')
      AND p.paid_amount > 0
    ORDER BY p.payment_date ASC
");
$stmt_cashier_all->execute(array($supplier_id));
$cashier_all_payments = $stmt_cashier_all->fetchAll(PDO::FETCH_ASSOC);

// Build master list of all known cashiers (from tbl_supplier_user + payments)
$all_cashiers_map = [];
if (!empty($supplier_staff_members)) {
    foreach ($supplier_staff_members as $sm) {
        $cname = trim($sm['full_name']);
        if ($cname !== '') {
            $all_cashiers_map[$cname] = [
                'name' => $cname,
                'role' => !empty($sm['role']) ? $sm['role'] : 'Cashier',
                'email' => !empty($sm['email']) ? $sm['email'] : '',
                'employee_id' => !empty($sm['employee_id']) ? $sm['employee_id'] : '',
                'total_sales' => 0.0,
                'cash_sales' => 0.0,
                'digital_sales' => 0.0,
                'terms_po_sales' => 0.0,
                'orders' => 0,
                'active_days_count' => 0,
                'first_sale' => null,
                'last_sale' => null
            ];
        }
    }
}

$daily_cashier_inventory = [];
$cashier_available_dates = [];
$cashier_active_days_tracker = [];

foreach ($cashier_all_payments as $p) {
    $ts = strtotime($p['payment_date']);
    $d_key = date('Y-m-d', $ts);
    $time_str = date('h:i A', $ts);
    $cashier_available_dates[$d_key] = true;

    $c_name = resolve_order_cashier($p, $supplier_staff_members);
    $amount = (float)$p['paid_amount'];
    $pm = $p['payment_method'] ?: 'Cash (OTC)';
    
    // Ensure cashier exists in master map
    if (!isset($all_cashiers_map[$c_name])) {
        $all_cashiers_map[$c_name] = [
            'name' => $c_name,
            'role' => 'Cashier',
            'email' => '',
            'employee_id' => '',
            'total_sales' => 0.0,
            'cash_sales' => 0.0,
            'digital_sales' => 0.0,
            'terms_po_sales' => 0.0,
            'orders' => 0,
            'active_days_count' => 0,
            'first_sale' => null,
            'last_sale' => null
        ];
    }

    // Categorize payment method
    $pm_clean = trim($pm);
    $pm_type = 'other';
    if (stripos($pm_clean, 'cash') !== false || stripos($pm_clean, 'over the counter') !== false || stripos($pm_clean, 'counter') !== false) {
        $pm_type = 'cash';
    } elseif (stripos($pm_clean, 'gcash') !== false) {
        $pm_type = 'gcash';
    } elseif (stripos($pm_clean, 'maya') !== false || stripos($pm_clean, 'paymaya') !== false) {
        $pm_type = 'maya';
    } elseif (stripos($pm_clean, 'bank') !== false || stripos($pm_clean, 'transfer') !== false) {
        $pm_type = 'bank';
    } elseif (stripos($pm_clean, 'check') !== false || stripos($pm_clean, 'term') !== false) {
        $pm_type = 'terms';
    } elseif (stripos($pm_clean, 'po') !== false || stripos($pm_clean, 'purchase order') !== false) {
        $pm_type = 'po';
    } elseif (stripos($pm_clean, 'card') !== false || stripos($pm_clean, 'credit') !== false) {
        $pm_type = 'card';
    }

    // Accumulate master totals
    $all_cashiers_map[$c_name]['total_sales'] += $amount;
    $all_cashiers_map[$c_name]['orders'] += 1;
    if ($pm_type === 'cash') {
        $all_cashiers_map[$c_name]['cash_sales'] += $amount;
    } elseif (in_array($pm_type, ['gcash', 'maya', 'bank', 'card'])) {
        $all_cashiers_map[$c_name]['digital_sales'] += $amount;
    } else {
        $all_cashiers_map[$c_name]['terms_po_sales'] += $amount;
    }
    if (!$all_cashiers_map[$c_name]['first_sale']) $all_cashiers_map[$c_name]['first_sale'] = $p['payment_date'];
    $all_cashiers_map[$c_name]['last_sale'] = $p['payment_date'];
    $cashier_active_days_tracker[$c_name][$d_key] = true;

    // Daily bucket
    if (!isset($daily_cashier_inventory[$d_key])) {
        $daily_cashier_inventory[$d_key] = [
            'date' => $d_key,
            'day_name' => date('l', $ts),
            'formatted_date' => date('F d, Y', $ts),
            'totals' => [
                'total_sales' => 0.0,
                'cash_sales' => 0.0,
                'digital_sales' => 0.0,
                'terms_po_sales' => 0.0,
                'refunds' => 0.0,
                'net_cash_drawer' => 0.0,
                'orders_count' => 0,
                'cashiers_count' => 0
            ],
            'cashiers' => [],
            'transactions' => []
        ];
    }

    if (!isset($daily_cashier_inventory[$d_key]['cashiers'][$c_name])) {
        $daily_cashier_inventory[$d_key]['cashiers'][$c_name] = [
            'cashier_name' => $c_name,
            'status' => 'On Duty',
            'role' => $all_cashiers_map[$c_name]['role'],
            'employee_id' => $all_cashiers_map[$c_name]['employee_id'],
            'orders' => 0,
            'total_sales' => 0.0,
            'cash_sales' => 0.0,
            'digital_sales' => 0.0,
            'terms_po_sales' => 0.0,
            'refunds' => 0.0,
            'net_cash_drawer' => 0.0,
            'first_sale' => $time_str,
            'last_sale' => $time_str,
            'methods' => [
                'cash' => 0.0,
                'gcash' => 0.0,
                'maya' => 0.0,
                'bank' => 0.0,
                'terms' => 0.0,
                'po' => 0.0,
                'card' => 0.0,
                'other' => 0.0
            ]
        ];
    }

    $c_ref = &$daily_cashier_inventory[$d_key]['cashiers'][$c_name];
    $c_ref['orders'] += 1;
    $c_ref['total_sales'] += $amount;
    $c_ref['last_sale'] = $time_str;
    $c_ref['methods'][$pm_type] = ($c_ref['methods'][$pm_type] ?? 0.0) + $amount;

    if ($pm_type === 'cash') {
        $c_ref['cash_sales'] += $amount;
        $c_ref['net_cash_drawer'] += $amount;
        $daily_cashier_inventory[$d_key]['totals']['cash_sales'] += $amount;
        $daily_cashier_inventory[$d_key]['totals']['net_cash_drawer'] += $amount;
    } elseif (in_array($pm_type, ['gcash', 'maya', 'bank', 'card'])) {
        $c_ref['digital_sales'] += $amount;
        $daily_cashier_inventory[$d_key]['totals']['digital_sales'] += $amount;
    } else {
        $c_ref['terms_po_sales'] += $amount;
        $daily_cashier_inventory[$d_key]['totals']['terms_po_sales'] += $amount;
    }

    $daily_cashier_inventory[$d_key]['totals']['total_sales'] += $amount;
    $daily_cashier_inventory[$d_key]['totals']['orders_count'] += 1;
    unset($c_ref);

    // Save individual transaction audit record
    $daily_cashier_inventory[$d_key]['transactions'][] = [
        'id' => $p['id'],
        'payment_id' => $p['payment_id'],
        'time' => $time_str,
        'full_date' => $p['payment_date'],
        'cashier_name' => $c_name,
        'amount' => $amount,
        'payment_method' => $pm,
        'method_type' => $pm_type,
        'customer_name' => !empty($p['customer_name']) ? $p['customer_name'] : 'Walk-in Customer',
        'info' => !empty($p['bank_transaction_info']) ? $p['bank_transaction_info'] : ''
    ];
}

// Track active days count
foreach ($all_cashiers_map as $cname => &$c_info) {
    $c_info['active_days_count'] = isset($cashier_active_days_tracker[$cname]) ? count($cashier_active_days_tracker[$cname]) : 0;
}
unset($c_info);

// Sort master cashiers list descending by total sales
$all_cashiers_list = array_values($all_cashiers_map);
usort($all_cashiers_list, function($a, $b) {
    return $b['total_sales'] <=> $a['total_sales'];
});

// Prepare daily roster (active cashiers + off duty cashiers)
foreach ($daily_cashier_inventory as $dk => &$d_obj) {
    $d_obj['totals']['cashiers_count'] = count($d_obj['cashiers']);
    
    // Complete roster for this day
    $roster = [];
    foreach ($all_cashiers_list as $master_c) {
        $cname = $master_c['name'];
        if (isset($d_obj['cashiers'][$cname])) {
            $roster_item = $d_obj['cashiers'][$cname];
            $roster_item['status'] = 'On Duty';
            $roster_item['role'] = $master_c['role'];
            $roster_item['employee_id'] = $master_c['employee_id'];
        } else {
            $roster_item = [
                'cashier_name' => $cname,
                'status' => 'Off Duty',
                'role' => $master_c['role'],
                'employee_id' => $master_c['employee_id'],
                'orders' => 0,
                'total_sales' => 0.0,
                'cash_sales' => 0.0,
                'digital_sales' => 0.0,
                'terms_po_sales' => 0.0,
                'refunds' => 0.0,
                'net_cash_drawer' => 0.0,
                'first_sale' => '―',
                'last_sale' => '―',
                'methods' => [
                    'cash' => 0.0,
                    'gcash' => 0.0,
                    'maya' => 0.0,
                    'bank' => 0.0,
                    'terms' => 0.0,
                    'po' => 0.0,
                    'card' => 0.0,
                    'other' => 0.0
                ]
            ];
        }
        $roster[] = $roster_item;
    }

    $active_list = array_values($d_obj['cashiers']);
    usort($active_list, function($a, $b) {
        return $b['total_sales'] <=> $a['total_sales'];
    });

    $d_obj['cashiers'] = $active_list;
    $d_obj['roster'] = $roster;
}
unset($d_obj);

$daily_cashier_inventory_payload = [
    'days' => $daily_cashier_inventory,
    'all_cashiers' => $all_cashiers_list,
    'available_dates' => array_keys($cashier_available_dates),
    'latest_date' => !empty($cashier_available_dates) ? max(array_keys($cashier_available_dates)) : date('Y-m-d'),
    'today_date' => date('Y-m-d')
];
$daily_cashier_inventory_json = json_encode($daily_cashier_inventory_payload);
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
#tableLeaderboard tbody tr {
    cursor: pointer;
    transition: background-color 0.15s ease;
}
#tableLeaderboard tbody tr:hover {
    background-color: #f0f9ff !important;
}
#tableProductAnnualTrend tbody tr {
    transition: background-color 0.15s ease;
}
#tableProductAnnualTrend tbody tr:hover {
    background-color: #f1f5f9 !important;
}
#tableProfitOrderLeaderboard tbody tr {
    transition: background-color 0.15s ease;
}
#tableProfitOrderLeaderboard tbody tr:hover {
    background-color: #f0fdf4 !important;
}

/* Executive Multi-Tab Navigation Styles */
.report-nav-container {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 6px;
    margin-bottom: 20px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.04);
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}
.report-nav-tab {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 9px 16px;
    font-size: 13.5px;
    font-weight: 700;
    color: #475569;
    border-radius: 8px;
    text-decoration: none !important;
    transition: all 0.2s ease;
    border: 1px solid transparent;
    cursor: pointer;
    background: #f8fafc;
}
.report-nav-tab:hover {
    background: #e2e8f0;
    color: #0f172a;
}
.report-nav-tab.active {
    background: #0284c7;
    color: #ffffff !important;
    box-shadow: 0 2px 6px rgba(2, 132, 199, 0.35);
}
.report-nav-tab.active i {
    color: #ffffff !important;
}
.report-nav-tab.active .badge {
    background: #ffffff !important;
    color: #0284c7 !important;
}
.report-tab-pane {
    display: none;
}
.report-tab-pane.active {
    display: block;
    animation: fadeInTab 0.18s ease-in-out;
}
@keyframes fadeInTab {
    from { opacity: 0; transform: translateY(4px); }
    to { opacity: 1; transform: translateY(0); }
}

@media print {
    .main-header, .main-sidebar, .content-header, .filter-box, .no-print, .btn, .dataTables_filter, .dataTables_length, .dataTables_paginate, .dataTables_info, .horizon-card, #drilldownMetricBtns, #drilldownProductSelect, #hzGranularityBtns, #hzTimeControlsWrapper, #hzMetricBtns, #hzTopNSelect, #cashierDateInput, #searchCashierMatrixInput, .report-nav-container {
        display: none !important;
    }
    .report-tab-pane {
        display: block !important;
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
    <!-- Executive Multi-Tab Navigation Bar -->
    <!-- ========================================================= -->
    <div class="report-nav-container no-print">
        <a href="#overview" class="report-nav-tab active" data-tab="tab-overview" onclick="switchReportTab('tab-overview', true); return false;">
            <i class="fa fa-dashboard" style="color: #0284c7;"></i> Executive Overview
        </a>
        <a href="#products" class="report-nav-tab" data-tab="tab-products" onclick="switchReportTab('tab-products', true); return false;">
            <i class="fa fa-trophy" style="color: #f59e0b;"></i> Product Leaderboard &amp; Trends
        </a>
        <a href="#profit" class="report-nav-tab" data-tab="tab-profit" onclick="switchReportTab('tab-profit', true); return false;">
            <i class="fa fa-line-chart" style="color: #10b981;"></i> Profit &amp; Velocity Horizon
        </a>
        <a href="#cashier" class="report-nav-tab" data-tab="tab-cashier" onclick="switchReportTab('tab-cashier', true); return false;">
            <i class="fa fa-money" style="color: #8b5cf6;"></i> Cashier Balancing &amp; Drawer
        </a>
        <a href="#orders" class="report-nav-tab" data-tab="tab-orders" onclick="switchReportTab('tab-orders', true); return false;">
            <i class="fa fa-list-alt" style="color: #0284c7;"></i> Orders &amp; Returns Register
            <span class="badge" style="background: #0284c7; color: #fff; font-size: 11px; margin-left: 4px;"><?php echo count($sales_orders); ?></span>
        </a>
    </div>

    <!-- ========================================================= -->
    <!-- TAB 1: Executive Overview & Multi-Horizon Pulse -->
    <!-- ========================================================= -->
    <div id="tab-overview" class="report-tab-pane active">

    <!-- Multi-Horizon Profit & Sales Overview (Day • Week • Month • Year) -->
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
                <p>
                    <?php if ($total_exchange_credits > 0): ?>
                        Cash Refund: &#8369;<?php echo number_format($total_standalone_refunds, 2); ?> | Exch: &#8369;<?php echo number_format($total_exchange_credits, 2); ?>
                    <?php else: ?>
                        Total Returns & Refunds (<?php echo count($period_returns); ?> items)
                    <?php endif; ?>
                </p>
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

    </div> <!-- /#tab-overview -->

    <!-- ========================================================= -->
    <!-- TAB 2: Product Leaderboard & 1-Year Trends -->
    <!-- ========================================================= -->
    <div id="tab-products" class="report-tab-pane">

    <!-- Most Ordered Products by Month (Leaderboard - Top 50 Default) -->
    <div class="row">
        <div class="col-md-12">
            <div class="box box-primary" style="border-radius: 8px; border: 1px solid #e2e8f0; box-shadow: 0 2px 4px rgba(0,0,0,0.04); margin-bottom: 25px;">
                <div class="box-header with-border" style="padding: 12px 18px; background: #ffffff;">
                    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                        <div>
                            <h3 class="box-title" style="font-weight: 800; color: #0f172a; font-size: 16px; display: flex; align-items: center; gap: 6px;">
                                <i class="fa fa-trophy text-yellow" style="color: #f59e0b;"></i> Monthly Product Leaderboard (Top Sellers &amp; Rank Movement)
                            </h3>
                            <div style="font-size: 11.5px; color: #64748b; margin-top: 2px;" id="monthlyLeaderboardSubtitle">
                                Top Sellers &amp; Rank Movement for October 2026 (Displaying Top 50 Items)
                            </div>
                        </div>
                        <form method="GET" action="sales-report.php" class="form-inline" id="lbFilterForm" onsubmit="event.preventDefault(); updateMonthlyLeaderboard();">
                            <input type="hidden" name="filter_type" value="<?php echo htmlspecialchars($filter_type); ?>">
                            <?php if ($filter_type == 'year'): ?>
                                <input type="hidden" name="year" value="<?php echo htmlspecialchars($selected_year); ?>">
                            <?php endif; ?>
                            <div class="form-group" style="margin-bottom: 0; margin-right: 4px;">
                                <select name="lb_month" id="lbMonthSelect" class="form-control input-sm" onchange="updateMonthlyLeaderboard()" style="font-weight: 600;">
                                    <?php 
                                    $month_full_names = [
                                        1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
                                        5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
                                        9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
                                    ];
                                    foreach ($month_full_names as $m_num => $m_name): 
                                    ?>
                                        <option value="<?php echo $m_num; ?>" <?php if($lb_selected_month == $m_num) echo 'selected'; ?>><?php echo $m_name; ?></option>
                                    <?php endforeach; ?>
                                    <option value="all" <?php if(isset($_GET['lb_month']) && $_GET['lb_month'] === 'all') echo 'selected'; ?>>All Months (Full Year)</option>
                                </select>
                            </div>
                            <div class="form-group" style="margin-bottom: 0; margin-right: 4px;">
                                <input type="number" name="lb_year" id="lbYearInput" value="<?php echo $lb_year_int; ?>" min="2020" max="2035" class="form-control input-sm" style="width: 75px; font-weight: 600;" onchange="updateMonthlyLeaderboard()">
                            </div>
                            <div class="form-group" style="margin-bottom: 0; margin-right: 6px;">
                                <select name="lb_topn" id="lbTopNSelect" class="form-control input-sm" onchange="updateMonthlyLeaderboard()" style="font-weight: 700; background: #fef3c7; border-color: #fde68a; color: #92400e;">
                                    <option value="5" <?php if($lb_selected_topn == '5') echo 'selected'; ?>>Top 5</option>
                                    <option value="10" <?php if($lb_selected_topn == '10') echo 'selected'; ?>>Top 10</option>
                                    <option value="20" <?php if($lb_selected_topn == '20') echo 'selected'; ?>>Top 20</option>
                                    <option value="50" <?php if($lb_selected_topn == '50' || empty($lb_selected_topn)) echo 'selected'; ?>>Top 50 (Default)</option>
                                    <option value="100" <?php if($lb_selected_topn == '100') echo 'selected'; ?>>Top 100</option>
                                    <option value="all" <?php if($lb_selected_topn == 'all') echo 'selected'; ?>>All Products</option>
                                </select>
                            </div>
                            <div class="btn-group btn-group-sm no-print" role="group" style="margin-right: 6px;">
                                <button type="button" class="btn btn-primary active" id="btnLbOrders" onclick="switchLbMetric('orders')" title="Rank by Distinct Number of Times Ordered"><i class="fa fa-shopping-cart"></i> Orders</button>
                                <button type="button" class="btn btn-default" id="btnLbUnits" onclick="switchLbMetric('units')" title="Rank by Total Units Sold"><i class="fa fa-cubes"></i> Units</button>
                                <button type="button" class="btn btn-default" id="btnLbRevenue" onclick="switchLbMetric('revenue')" title="Rank by Gross Sales Revenue"><i class="fa fa-money"></i> Revenue</button>
                                <button type="button" class="btn btn-default" id="btnLbProfit" onclick="switchLbMetric('profit')" title="Rank by Realized Gross Profit"><i class="fa fa-line-chart"></i> Profit</button>
                            </div>
                            <button type="button" class="btn btn-default btn-sm" onclick="updateMonthlyLeaderboard()"><i class="fa fa-refresh"></i> Refresh</button>
                        </form>
                    </div>
                </div>
                <div class="box-body" style="padding: 16px 18px;">
                    <div class="row">
                        <!-- Left Column: Dynamic Horizontal Bar Chart -->
                        <div class="col-md-6 col-xs-12">
                            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 10px;">
                                <div style="max-height: 480px; overflow-y: auto;">
                                    <div id="chartLeaderboardWrapper" style="position: relative; width: 100%; height: 460px;">
                                        <canvas id="chartLeaderboard"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Right Column: Ranking Table with Sticky Header -->
                        <div class="col-md-6 col-xs-12">
                            <div style="margin-bottom: 8px;">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-addon" style="background: #f1f5f9; border-color: #cbd5e1;"><i class="fa fa-search text-muted"></i></span>
                                    <input type="text" id="lbSearchInput" class="form-control" placeholder="Search product name or category in leaderboard..." onkeyup="filterLbTable(this.value)" style="border-color: #cbd5e1;">
                                </div>
                            </div>
                            <div class="table-responsive" style="max-height: 440px; overflow-y: auto; border: 1px solid #e2e8f0; border-radius: 6px;">
                                <table class="table table-bordered table-striped table-hover" id="tableLeaderboard" style="font-size: 12.5px; margin-bottom: 0;">
                                    <thead style="position: sticky; top: 0; background: #f8fafc; z-index: 5; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">
                                        <tr style="background: #f8fafc; color: #334155;">
                                            <th style="width: 50px;" class="text-center">Rank</th>
                                            <th>Product Name</th>
                                            <th class="text-center" style="width: 75px;">Movement</th>
                                            <th class="text-right" style="width: 75px; cursor: pointer;" onclick="switchLbMetric('orders')" title="Click to rank by Orders (Descending)">
                                                <span id="thLbOrders">Orders <i class="fa fa-sort-amount-desc text-primary"></i></span>
                                            </th>
                                            <th class="text-right" style="width: 75px; cursor: pointer;" onclick="switchLbMetric('units')" title="Click to rank by Units (Descending)">
                                                <span id="thLbUnits">Units</span>
                                            </th>
                                            <th class="text-right" style="width: 95px; cursor: pointer;" onclick="switchLbMetric('revenue')" title="Click to rank by Revenue (Descending)">
                                                <span id="thLbRevenue">Revenue</span>
                                            </th>
                                            <th class="text-right" style="width: 95px; cursor: pointer;" onclick="switchLbMetric('profit')" title="Click to rank by Profit (Descending)">
                                                <span id="thLbProfit">Profit</span>
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody id="lbTableBody">
                                        <!-- Rendered via JS -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ========================================================= -->
    <!-- 1-Year Product Performance & Purchase Rate Drill-Down -->
    <!-- ========================================================= -->
    <div class="row" id="productAnnualTrendSection" style="margin-bottom: 25px;">
        <div class="col-md-12">
            <div class="box box-info" style="border-radius: 8px; border: 1.5px solid #bae6fd; box-shadow: 0 4px 12px rgba(2, 132, 199, 0.08); background: #ffffff;">
                <div class="box-header with-border" style="padding: 14px 18px; background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%); border-bottom: 1px solid #bae6fd;">
                    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                        <div>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <span class="badge" style="background: #0284c7; color: #fff; font-size: 11px; font-weight: 800; padding: 4px 8px; text-transform: uppercase;">
                                    <i class="fa fa-crosshairs"></i> 1-Year Trend Deep Dive
                                </span>
                                <h3 class="box-title" style="font-weight: 800; color: #0c4a6e; font-size: 16.5px; margin: 0;">
                                    <span id="drilldownProductTitle">Select Product from Leaderboard</span>
                                </h3>
                            </div>
                            <div style="font-size: 12px; color: #0369a1; margin-top: 4px;" id="drilldownProductSubtitle">
                                Click any product bar in the Leaderboard above to inspect that product's 12-month order count &amp; purchase rate trend.
                            </div>
                        </div>
                        
                        <!-- Metric switcher toolbar & product quick selector -->
                        <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                            <div class="btn-group btn-group-sm no-print" role="group" id="drilldownMetricBtns">
                                <button type="button" class="btn btn-primary active" id="btnTrendOrders" onclick="switchTrendMetric('orders')" title="Show 12-Month Orders Frequency">
                                    <i class="fa fa-shopping-cart"></i> Orders
                                </button>
                                <button type="button" class="btn btn-default" id="btnTrendUnits" onclick="switchTrendMetric('units')" title="Show 12-Month Units Sold">
                                    <i class="fa fa-cubes"></i> Units
                                </button>
                                <button type="button" class="btn btn-default" id="btnTrendRate" onclick="switchTrendMetric('rate')" title="Show Purchase Rate (Units per Order)">
                                    <i class="fa fa-tachometer"></i> Purchase Rate
                                </button>
                                <button type="button" class="btn btn-default" id="btnTrendRevenue" onclick="switchTrendMetric('revenue')" title="Show 12-Month Sales Revenue">
                                    <i class="fa fa-money"></i> Revenue
                                </button>
                            </div>
                            <select id="drilldownProductSelect" class="form-control input-sm" style="font-weight: 600; min-width: 200px; max-width: 280px; border-color: #7dd3fc;" onchange="loadProductAnnualTrend(this.value)">
                                <!-- Populated dynamically with Leaderboard Top Items -->
                            </select>
                        </div>
                    </div>
                </div>

                <div class="box-body" style="padding: 18px;">
                    <!-- 4 Quick KPI Summary Cards for Selected Product -->
                    <div class="row" style="margin-bottom: 16px;">
                        <div class="col-md-3 col-sm-6 col-xs-12" style="margin-bottom: 8px;">
                            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-left: 4px solid #0284c7; border-radius: 6px; padding: 10px 14px;">
                                <div style="font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase;">
                                    <i class="fa fa-shopping-cart text-primary"></i> Annual Order Frequency
                                </div>
                                <div style="font-size: 20px; font-weight: 800; color: #0f172a; margin-top: 2px;">
                                    <span id="kpiAnnualOrders">0</span> <span style="font-size: 12px; font-weight: 600; color: #64748b;">orders</span>
                                </div>
                                <div style="font-size: 11px; color: #0284c7; font-weight: 600;" id="kpiAnnualOrdersShare">Year <?php echo $lb_year_int; ?> Total Orders</div>
                            </div>
                        </div>

                        <div class="col-md-3 col-sm-6 col-xs-12" style="margin-bottom: 8px;">
                            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-left: 4px solid #10b981; border-radius: 6px; padding: 10px 14px;">
                                <div style="font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase;">
                                    <i class="fa fa-cubes text-success"></i> Total Annual Volume
                                </div>
                                <div style="font-size: 20px; font-weight: 800; color: #0f172a; margin-top: 2px;">
                                    <span id="kpiAnnualUnits">0</span> <span style="font-size: 12px; font-weight: 600; color: #64748b;">units / pcs</span>
                                </div>
                                <div style="font-size: 11px; color: #059669; font-weight: 600;" id="kpiAnnualUnitsAvg">Avg 0 pcs/mo</div>
                            </div>
                        </div>

                        <div class="col-md-3 col-sm-6 col-xs-12" style="margin-bottom: 8px;">
                            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-left: 4px solid #8b5cf6; border-radius: 6px; padding: 10px 14px;">
                                <div style="font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase;">
                                    <i class="fa fa-tachometer" style="color: #8b5cf6;"></i> Purchase Rate (Basket Depth)
                                </div>
                                <div style="font-size: 20px; font-weight: 800; color: #0f172a; margin-top: 2px;">
                                    <span id="kpiAnnualRate">0.00</span> <span style="font-size: 12px; font-weight: 600; color: #64748b;">pcs / order</span>
                                </div>
                                <div style="font-size: 11px; color: #7c3aed; font-weight: 600;">Average Units per Purchase</div>
                            </div>
                        </div>

                        <div class="col-md-3 col-sm-6 col-xs-12" style="margin-bottom: 8px;">
                            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-left: 4px solid #f59e0b; border-radius: 6px; padding: 10px 14px;">
                                <div style="font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase;">
                                    <i class="fa fa-trophy" style="color: #f59e0b;"></i> Peak Buying Month
                                </div>
                                <div style="font-size: 20px; font-weight: 800; color: #0f172a; margin-top: 2px;">
                                    <span id="kpiPeakMonth">-</span>
                                </div>
                                <div style="font-size: 11px; color: #d97706; font-weight: 600;" id="kpiPeakMonthStats">0 orders (0 pcs)</div>
                            </div>
                        </div>
                    </div>

                    <!-- Main Content Grid: Chart (Left) + Table (Right) -->
                    <div class="row">
                        <!-- Left: 12-Month Bar & Line Velocity Chart -->
                        <div class="col-md-7 col-xs-12">
                            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 14px;">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                                    <strong style="font-size: 13px; color: #334155;">
                                        <i class="fa fa-bar-chart text-info"></i> 12-Month Monthly Distribution &amp; Velocity (Jan – Dec <?php echo $lb_year_int; ?>)
                                    </strong>
                                    <span class="label label-info" id="chartTrendMetricBadge" style="background: #0284c7; font-size: 10.5px;">Orders (Bars) + Rate (Line)</span>
                                </div>
                                <div style="position: relative; height: 340px; width: 100%;">
                                    <canvas id="chartProductAnnualTrend"></canvas>
                                </div>
                            </div>
                        </div>

                        <!-- Right: 12-Month Matrix Table -->
                        <div class="col-md-5 col-xs-12">
                            <div class="table-responsive" style="max-height: 385px; overflow-y: auto; border: 1px solid #e2e8f0; border-radius: 6px;">
                                <table class="table table-bordered table-striped table-hover" id="tableProductAnnualTrend" style="font-size: 12px; margin-bottom: 0;">
                                    <thead style="position: sticky; top: 0; background: #f8fafc; z-index: 5; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">
                                        <tr style="background: #f8fafc; color: #334155;">
                                            <th style="width: 80px;">Month</th>
                                            <th class="text-right" style="width: 55px;">Orders</th>
                                            <th class="text-right" style="width: 55px;">Units</th>
                                            <th class="text-right" style="width: 75px;">Rate (pcs/ord)</th>
                                            <th class="text-right" style="width: 85px;">Revenue</th>
                                            <th class="text-center" style="width: 65px;">MoM</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tableProductAnnualTrendBody">
                                        <!-- Rendered via JS -->
                                    </tbody>
                                    <tfoot style="position: sticky; bottom: 0; background: #f1f5f9; font-weight: 800; border-top: 2px solid #cbd5e1;">
                                        <tr>
                                            <td>Total / Avg</td>
                                            <td class="text-right" id="tfAnnualOrders" style="color: #0284c7;">0</td>
                                            <td class="text-right" id="tfAnnualUnits">0</td>
                                            <td class="text-right" id="tfAnnualRate" style="color: #7c3aed;">0.00</td>
                                            <td class="text-right" id="tfAnnualRevenue">₱0.00</td>
                                            <td class="text-center" style="color: #059669;">1-Yr Total</td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>

    </div> <!-- /#tab-products -->

    <!-- ========================================================= -->
    <!-- TAB 3: Profit & Order Velocity Horizon -->
    <!-- ========================================================= -->
    <div id="tab-profit" class="report-tab-pane">

    <!-- Top Profit & Order Velocity Horizon (Descending Ranking) -->
    <div class="row" id="profitOrderLeaderboardSection" style="margin-bottom: 25px;">
        <div class="col-md-12">
            <div class="box box-success" style="border-radius: 8px; border: 1.5px solid #a7f3d0; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.08); background: #ffffff;">
                
                <!-- Section Header -->
                <div class="box-header with-border" style="padding: 14px 18px; background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%); border-bottom: 1px solid #a7f3d0;">
                    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
                        <div>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <span class="badge" style="background: #10b981; color: #fff; font-size: 11px; font-weight: 800; padding: 4px 8px; text-transform: uppercase;">
                                    <i class="fa fa-trophy"></i> Profit &amp; Order Velocity
                                </span>
                                <h3 class="box-title" style="font-weight: 800; color: #065f46; font-size: 16.5px; margin: 0;">
                                    Top Revenue &amp; Profit Generating Products
                                </h3>
                            </div>
                            <div style="font-size: 12px; color: #047857; margin-top: 4px;" id="hzSubtitle">
                                Ranked in descending order of <strong>Realized Profit (₱)</strong> for <span id="hzActivePeriodLabel">Today (Daily)</span> (Displaying Top 50 Items).
                            </div>
                        </div>
                        
                        <!-- Granularity Selector Pills -->
                        <div class="btn-group btn-group-sm no-print" role="group" id="hzGranularityBtns">
                            <button type="button" class="btn btn-success active" id="btnHzDaily" onclick="switchHzGranularity('daily')" title="Rank items for a single day">
                                <i class="fa fa-calendar-o"></i> Daily
                            </button>
                            <button type="button" class="btn btn-default" id="btnHzWeekly" onclick="switchHzGranularity('weekly')" title="Rank items for an ISO week">
                                <i class="fa fa-calendar"></i> Weekly
                            </button>
                            <button type="button" class="btn btn-default" id="btnHzMonthly" onclick="switchHzGranularity('monthly')" title="Rank items for a full month">
                                <i class="fa fa-calendar-check-o"></i> Monthly
                            </button>
                            <button type="button" class="btn btn-default" id="btnHzYearly" onclick="switchHzGranularity('yearly')" title="Rank items across full year">
                                <i class="fa fa-line-chart"></i> Yearly
                            </button>
                            <button type="button" class="btn btn-default" id="btnHzYearlyTrend" onclick="switchHzGranularity('yearly_trend')" title="Multi-year comparison trend">
                                <i class="fa fa-area-chart"></i> Yearly Trend
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Secondary Filter & Control Toolbar -->
                <div style="padding: 12px 18px; background: #fafafa; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
                    
                    <!-- Time/Date Controls -->
                    <div style="display: flex; align-items: center; gap: 6px; flex-wrap: wrap;" id="hzTimeControlsWrapper">
                        <!-- Daily Controls -->
                        <div id="hzDailyGroup" style="display: flex; align-items: center; gap: 4px;">
                            <button type="button" class="btn btn-default btn-sm" onclick="navHzDate(-1)" title="Previous Day"><i class="fa fa-chevron-left"></i></button>
                            <input type="date" id="hzDateInput" class="form-control input-sm" style="width: 145px; font-weight: 600;" onchange="renderHzReport()">
                            <button type="button" class="btn btn-default btn-sm" onclick="navHzDate(1)" title="Next Day"><i class="fa fa-chevron-right"></i></button>
                            <button type="button" class="btn btn-default btn-sm" onclick="setHzDateToday()" title="Jump to Today"><i class="fa fa-clock-o"></i> Today</button>
                        </div>

                        <!-- Weekly Controls -->
                        <div id="hzWeeklyGroup" style="display: none; align-items: center; gap: 4px;">
                            <select id="hzWeekSelect" class="form-control input-sm" style="font-weight: 600; min-width: 180px;" onchange="renderHzReport()">
                                <!-- Populated dynamically -->
                            </select>
                        </div>

                        <!-- Monthly Controls -->
                        <div id="hzMonthlyGroup" style="display: none; align-items: center; gap: 4px;">
                            <select id="hzMonthSelect" class="form-control input-sm" style="font-weight: 600; width: 120px;" onchange="renderHzReport()">
                                <option value="1">January</option>
                                <option value="2">February</option>
                                <option value="3">March</option>
                                <option value="4">April</option>
                                <option value="5">May</option>
                                <option value="6">June</option>
                                <option value="7">July</option>
                                <option value="8">August</option>
                                <option value="9">September</option>
                                <option value="10" selected>October</option>
                                <option value="11">November</option>
                                <option value="12">December</option>
                            </select>
                            <select id="hzMonthYearSelect" class="form-control input-sm" style="font-weight: 600; width: 85px;" onchange="renderHzReport()">
                                <!-- Populated dynamically with years -->
                            </select>
                        </div>

                        <!-- Yearly Controls -->
                        <div id="hzYearlyGroup" style="display: none; align-items: center; gap: 4px;">
                            <select id="hzYearSelect" class="form-control input-sm" style="font-weight: 600; width: 110px;" onchange="renderHzReport()">
                                <!-- Populated dynamically with years -->
                            </select>
                        </div>

                        <!-- Yearly Trend Controls -->
                        <div id="hzYearlyTrendGroup" style="display: none; align-items: center; gap: 4px;">
                            <span class="label label-success" style="font-size: 11px; padding: 5px 8px;"><i class="fa fa-bar-chart"></i> Multi-Year Historical Trend Comparison</span>
                        </div>
                    </div>

                    <!-- Metric Switcher & Top N Selector -->
                    <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                        <div class="btn-group btn-group-sm" role="group" id="hzMetricBtns">
                            <button type="button" class="btn btn-success active" id="btnHzMetricProfit" onclick="switchHzMetric('profit')" title="Sort descending by Realized Profit">
                                <i class="fa fa-money"></i> Profit (₱)
                            </button>
                            <button type="button" class="btn btn-default" id="btnHzMetricOrders" onclick="switchHzMetric('orders')" title="Sort descending by Number of Orders">
                                <i class="fa fa-shopping-cart"></i> Orders
                            </button>
                            <button type="button" class="btn btn-default" id="btnHzMetricUnits" onclick="switchHzMetric('units')" title="Sort descending by Units Sold">
                                <i class="fa fa-cubes"></i> Units
                            </button>
                            <button type="button" class="btn btn-default" id="btnHzMetricRevenue" onclick="switchHzMetric('revenue')" title="Sort descending by Gross Sales Revenue">
                                <i class="fa fa-line-chart"></i> Revenue
                            </button>
                        </div>

                        <select id="hzTopNSelect" class="form-control input-sm" style="font-weight: 600; width: 105px;" onchange="renderHzReport()">
                            <option value="10">Top 10</option>
                            <option value="25">Top 25</option>
                            <option value="50" selected>Top 50</option>
                            <option value="100">Top 100</option>
                            <option value="all">All Items</option>
                        </select>

                        <div class="input-group input-group-sm" style="width: 170px;">
                            <input type="text" id="hzSearchInput" class="form-control" placeholder="Search item..." onkeyup="filterHzTable(this.value)">
                            <span class="input-group-addon"><i class="fa fa-search"></i></span>
                        </div>
                    </div>

                </div>

                <!-- Box Body -->
                <div class="box-body" style="padding: 18px;">
                    
                    <!-- 4 Summary KPI Badges for Selected Granularity Period -->
                    <div class="row" style="margin-bottom: 16px;">
                        <div class="col-md-3 col-sm-6 col-xs-12" style="margin-bottom: 8px;">
                            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-left: 4px solid #10b981; border-radius: 6px; padding: 10px 14px;">
                                <div style="font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase;">
                                    <i class="fa fa-money text-success"></i> Period Realized Profit
                                </div>
                                <div style="font-size: 20px; font-weight: 800; color: #047857; margin-top: 2px;">
                                    <span id="kpiHzTotalProfit">₱0.00</span>
                                </div>
                                <div style="font-size: 11px; color: #059669; font-weight: 600;" id="kpiHzProfitMargin">0.0% Realized Margin</div>
                            </div>
                        </div>

                        <div class="col-md-3 col-sm-6 col-xs-12" style="margin-bottom: 8px;">
                            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-left: 4px solid #0284c7; border-radius: 6px; padding: 10px 14px;">
                                <div style="font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase;">
                                    <i class="fa fa-shopping-cart text-primary"></i> Total Order Frequency
                                </div>
                                <div style="font-size: 20px; font-weight: 800; color: #0f172a; margin-top: 2px;">
                                    <span id="kpiHzTotalOrders">0</span> <span style="font-size: 12px; font-weight: 600; color: #64748b;">orders</span>
                                </div>
                                <div style="font-size: 11px; color: #0284c7; font-weight: 600;" id="kpiHzTotalUnits">0 units / pcs sold</div>
                            </div>
                        </div>

                        <div class="col-md-3 col-sm-6 col-xs-12" style="margin-bottom: 8px;">
                            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-left: 4px solid #f59e0b; border-radius: 6px; padding: 10px 14px;">
                                <div style="font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase;">
                                    <i class="fa fa-trophy" style="color: #f59e0b;"></i> #1 Profit Leader
                                </div>
                                <div style="font-size: 15px; font-weight: 800; color: #0f172a; margin-top: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" id="kpiHzTopItemName">
                                    -
                                </div>
                                <div style="font-size: 11px; color: #d97706; font-weight: 600;" id="kpiHzTopItemStats">+₱0.00 profit</div>
                            </div>
                        </div>

                        <div class="col-md-3 col-sm-6 col-xs-12" style="margin-bottom: 8px;">
                            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-left: 4px solid #8b5cf6; border-radius: 6px; padding: 10px 14px;">
                                <div style="font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase;">
                                    <i class="fa fa-cubes" style="color: #8b5cf6;"></i> Active Products Selling
                                </div>
                                <div style="font-size: 20px; font-weight: 800; color: #0f172a; margin-top: 2px;">
                                    <span id="kpiHzActiveItems">0</span> <span style="font-size: 12px; font-weight: 600; color: #64748b;">items</span>
                                </div>
                                <div style="font-size: 11px; color: #7c3aed; font-weight: 600;" id="kpiHzGrossSales">₱0.00 Gross Sales</div>
                            </div>
                        </div>
                    </div>

                    <!-- Main Content Grid: Chart (Left) + Matrix Table (Right) -->
                    <div class="row">
                        <!-- Left: Descending Horizontal Bar Chart with Dynamic Height -->
                        <div class="col-md-7 col-xs-12">
                            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 14px;">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                                    <strong style="font-size: 13px; color: #334155;">
                                        <i class="fa fa-bar-chart text-success"></i> <span id="hzChartTitle">Ranked Items by Profit (Descending #1 at Top)</span>
                                    </strong>
                                    <span class="label label-success" id="hzChartBadge" style="font-size: 10.5px;">Realized Profit (₱)</span>
                                </div>
                                <div style="position: relative; max-height: 480px; overflow-y: auto;" id="chartHzWrapper">
                                    <canvas id="chartProfitOrderLeaderboard"></canvas>
                                </div>
                            </div>
                        </div>

                        <!-- Right: Ranked Financial Matrix Table -->
                        <div class="col-md-5 col-xs-12">
                            <div class="table-responsive" style="max-height: 525px; overflow-y: auto; border: 1px solid #e2e8f0; border-radius: 6px;">
                                <table class="table table-bordered table-striped table-hover" id="tableProfitOrderLeaderboard" style="font-size: 12px; margin-bottom: 0;">
                                    <thead style="position: sticky; top: 0; background: #f8fafc; z-index: 5; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">
                                        <tr style="background: #f8fafc; color: #334155;">
                                            <th class="text-center" style="width: 45px;">Rank</th>
                                            <th>Product Name</th>
                                            <th class="text-right" style="width: 80px;" id="thHzProfit">Profit</th>
                                            <th class="text-right" style="width: 50px;" id="thHzOrders">Ord</th>
                                            <th class="text-right" style="width: 50px;">Qty</th>
                                            <th class="text-right" style="width: 55px;">Margin</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tableProfitOrderBody">
                                        <!-- Rendered via JS -->
                                    </tbody>
                                    <tfoot style="position: sticky; bottom: 0; background: #f1f5f9; font-weight: 800; border-top: 2px solid #cbd5e1;">
                                        <tr>
                                            <td colspan="2">Total for Period</td>
                                            <td class="text-right" id="tfHzProfit" style="color: #10b981;">+₱0.00</td>
                                            <td class="text-right" id="tfHzOrders" style="color: #0284c7;">0</td>
                                            <td class="text-right" id="tfHzUnits">0</td>
                                            <td class="text-right" id="tfHzMargin" style="color: #047857;">0.0%</td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>

    </div> <!-- /#tab-profit -->

    <!-- ========================================================= -->
    <!-- TAB 4: Cashier Balancing & Cash Drawer -->
    <!-- ========================================================= -->
    <div id="tab-cashier" class="report-tab-pane">

    <!-- Daily Cash Inventory & Cashier End-of-Day Balancing Report -->
    <div class="row" id="dailyCashierInventorySection" style="margin-top: 10px;">
        <div class="col-md-12">
            <div class="chart-box" style="border-top: 3px solid #8b5cf6;">
                
                <!-- Section Header & Interactive Calendar / Cashier Filters -->
                <div class="chart-header" style="flex-wrap: wrap; gap: 12px; border-bottom: 2px solid #f1f5f9; padding-bottom: 14px;">
                    <div>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <i class="fa fa-money" style="color: #10b981; font-size: 20px;"></i>
                            <span style="font-size: 17px; font-weight: 800; color: #1e293b;">Daily Cash Inventory &amp; Cashier End-of-Day Balancing</span>
                        </div>
                        <div style="font-size: 12px; color: #64748b; margin-top: 3px;">
                            Complete store staff &amp; cashier audit: Physical cash in drawer, digital payments (GCash, Maya, Bank), and individual transaction reconciliation
                        </div>
                    </div>

                    <!-- Date & Cashier Filter Controls -->
                    <div class="no-print" style="display: flex; align-items: center; gap: 6px; flex-wrap: wrap;">
                        <!-- Cashier Selector Dropdown -->
                        <div style="min-width: 220px;">
                            <select id="cashierSelectFilter" class="form-control input-sm" onchange="switchSelectedCashier(this.value)" style="font-weight: 700; color: #1e293b; border-color: #8b5cf6;">
                                <option value="all">👤 All Cashiers (Store Roster)</option>
                            </select>
                        </div>

                        <!-- Date Navigation Buttons -->
                        <button type="button" class="btn btn-sm btn-default" onclick="navCashierDate(-1)" title="Previous Day">
                            <i class="fa fa-chevron-left"></i> Prev Day
                        </button>
                        <div class="input-group input-group-sm" style="width: 170px;">
                            <span class="input-group-addon" style="background: #f8fafc; font-weight: 600;"><i class="fa fa-calendar"></i></span>
                            <input type="date" id="cashierDateInput" class="form-control" onchange="renderDailyCashierInventory()" style="font-weight: 700; color: #1e293b;">
                        </div>
                        <button type="button" class="btn btn-sm btn-default" onclick="navCashierDate(1)" title="Next Day">
                            Next Day <i class="fa fa-chevron-right"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-info" onclick="setCashierDateToday()" style="background-color: #0284c7; border-color: #0284c7;">
                            <i class="fa fa-crosshairs"></i> Today
                        </button>
                        <span id="cashierActiveDateBadge" class="label label-primary" style="font-size: 12px; padding: 6px 10px; background: #8b5cf6; margin-left: 4px;">
                            <i class="fa fa-calendar-check-o"></i> Selected Day
                        </span>
                    </div>
                </div>

                <!-- 4 Topline Daily Summary KPI Cards -->
                <div class="row" style="margin-top: 15px; margin-bottom: 15px;">
                    <div class="col-md-3 col-sm-6 col-xs-12" style="margin-bottom: 8px;">
                        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-left: 4px solid #0284c7; border-radius: 8px; padding: 12px 16px;">
                            <div style="font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px;" id="lblKpiDailyTotal">
                                Total Daily Collections
                            </div>
                            <div id="kpiDailyTotalSales" style="font-size: 20px; font-weight: 800; color: #0f172a; margin-top: 4px;">
                                &#8369;0.00
                            </div>
                            <div id="kpiDailyTotalOrders" style="font-size: 11px; color: #64748b; margin-top: 2px;">
                                0 completed orders • 0 cashiers
                            </div>
                        </div>
                    </div>

                    <div class="col-md-3 col-sm-6 col-xs-12" style="margin-bottom: 8px;">
                        <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-left: 4px solid #10b981; border-radius: 8px; padding: 12px 16px;">
                            <div style="font-size: 11px; font-weight: 700; color: #166534; text-transform: uppercase; letter-spacing: 0.5px; display: flex; justify-content: space-between;">
                                <span>Physical Cash (OTC Drawer)</span>
                                <i class="fa fa-money text-success"></i>
                            </div>
                            <div id="kpiDailyCashSales" style="font-size: 20px; font-weight: 800; color: #15803d; margin-top: 4px;">
                                &#8369;0.00
                            </div>
                            <div id="kpiDailyCashShare" style="font-size: 11px; color: #166534; margin-top: 2px;">
                                Actual Physical Cash to Count &amp; Turn Over
                            </div>
                        </div>
                    </div>

                    <div class="col-md-3 col-sm-6 col-xs-12" style="margin-bottom: 8px;">
                        <div style="background: #eff6ff; border: 1px solid #bfdbfe; border-left: 4px solid #3b82f6; border-radius: 8px; padding: 12px 16px;">
                            <div style="font-size: 11px; font-weight: 700; color: #1e40af; text-transform: uppercase; letter-spacing: 0.5px; display: flex; justify-content: space-between;">
                                <span>Digital &amp; E-Wallet Channels</span>
                                <i class="fa fa-credit-card text-primary"></i>
                            </div>
                            <div id="kpiDailyDigitalSales" style="font-size: 20px; font-weight: 800; color: #1d4ed8; margin-top: 4px;">
                                &#8369;0.00
                            </div>
                            <div id="kpiDailyDigitalBreakdown" style="font-size: 11px; color: #1e40af; margin-top: 2px;">
                                GCash, Maya, Bank Transfer, Cards
                            </div>
                        </div>
                    </div>

                    <div class="col-md-3 col-sm-6 col-xs-12" style="margin-bottom: 8px;">
                        <div style="background: #faf5ff; border: 1px solid #e9d5ff; border-left: 4px solid #a855f7; border-radius: 8px; padding: 12px 16px;">
                            <div style="font-size: 11px; font-weight: 700; color: #6b21a8; text-transform: uppercase; letter-spacing: 0.5px; display: flex; justify-content: space-between;">
                                <span>Check, Terms &amp; PO</span>
                                <i class="fa fa-file-text-o text-purple"></i>
                            </div>
                            <div id="kpiDailyTermsSales" style="font-size: 20px; font-weight: 800; color: #7e22ce; margin-top: 4px;">
                                &#8369;0.00
                            </div>
                            <div id="kpiDailyTermsBreakdown" style="font-size: 11px; color: #6b21a8; margin-top: 2px;">
                                Account Receivables &amp; Purchase Orders
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Main Content Row: Left = Cashier Breakdown Chart, Right = Individual Cashier Cash Drawer Cards -->
                <div class="row">
                    <!-- Left: Cashier Performance & Payment Channels Chart -->
                    <div class="col-md-7 col-xs-12">
                        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px 16px; margin-bottom: 15px;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                                <strong style="font-size: 13px; color: #334155;">
                                    <i class="fa fa-bar-chart text-purple" style="color: #8b5cf6;"></i> <span id="cashierChartHeading">Cashier Sales by Payment Channel</span>
                                </strong>
                                <span style="font-size: 11px; color: #64748b;" id="cashierChartSubheading">Physical Cash (Drawer) vs Digital vs Terms</span>
                            </div>
                            <div style="position: relative; height: 320px;">
                                <canvas id="chartDailyCashierInventory"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Right: Cashier Cash Drawer Balance Cards -->
                    <div class="col-md-5 col-xs-12">
                        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px 16px; margin-bottom: 15px;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; border-bottom: 1px solid #f1f5f9; padding-bottom: 8px;">
                                <strong style="font-size: 13px; color: #334155;">
                                    <i class="fa fa-id-card-o text-primary"></i> Cashier Cash Drawer Balances
                                </strong>
                                <span id="cashierCardsCountBadge" class="badge" style="background: #8b5cf6; font-size: 10px;">0 Cashiers</span>
                            </div>
                            <div id="cashierDrawerCardsContainer" style="max-height: 310px; overflow-y: auto; padding-right: 4px;">
                                <!-- Populated dynamically by JS -->
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Granular Cashier End-of-Day Balancing & Reconciliation Table -->
                <div style="margin-top: 10px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px; margin-bottom: 10px;">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <strong style="font-size: 14px; color: #1e293b;">
                                <i class="fa fa-table text-primary"></i> Cashier Daily Balancing &amp; Staff Roster Matrix
                            </strong>
                            <!-- Toggle Roster Mode -->
                            <div class="btn-group btn-group-xs no-print" id="rosterModeBtnGroup">
                                <button type="button" class="btn btn-primary active" id="btnRosterAll" onclick="switchRosterMode('all')">All Staff Roster</button>
                                <button type="button" class="btn btn-default" id="btnRosterActive" onclick="switchRosterMode('active')">Active On Duty Only</button>
                            </div>
                        </div>
                        <div class="no-print" style="width: 220px;">
                            <input type="text" class="form-control input-sm" id="searchCashierMatrixInput" placeholder="Filter cashier name..." onkeyup="filterCashierMatrixTable(this.value)">
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover" id="tableDailyCashierMatrix" style="font-size: 12px; margin-bottom: 0;">
                            <thead>
                                <tr style="background: #f8fafc; color: #334155;">
                                    <th style="width: 40px;" class="text-center">#</th>
                                    <th>Cashier / Staff Name</th>
                                    <th class="text-center" style="width: 90px;">Status</th>
                                    <th class="text-center" style="width: 140px;">Shift Window</th>
                                    <th class="text-center" style="width: 65px;">Orders</th>
                                    <th class="text-right" style="background: #f0fdf4; color: #15803d; width: 125px;">
                                        <i class="fa fa-money"></i> Cash in Drawer
                                    </th>
                                    <th class="text-right" style="width: 95px;">GCash</th>
                                    <th class="text-right" style="width: 95px;">Maya / Card</th>
                                    <th class="text-right" style="width: 95px;">Bank Transfer</th>
                                    <th class="text-right" style="width: 95px;">Terms / PO</th>
                                    <th class="text-right" style="width: 125px; font-weight: 700;">Total Collected</th>
                                    <th class="text-right" style="background: #fefce8; color: #854d0e; width: 125px; font-weight: 800;">
                                        <i class="fa fa-bank"></i> Vault Turnover
                                    </th>
                                    <th class="text-center no-print" style="width: 75px;">Audit</th>
                                </tr>
                            </thead>
                            <tbody id="tableDailyCashierMatrixBody">
                                <!-- Populated dynamically by JS -->
                            </tbody>
                            <tfoot id="tableDailyCashierMatrixFoot" style="background: #f1f5f9; font-weight: 800; font-size: 12px;">
                                <!-- Sticky Summary Totals Row populated by JS -->
                            </tfoot>
                        </table>
                    </div>
                </div>

                <!-- Granular Transaction Audit Trail Section -->
                <div id="cashierTxAuditSection" style="margin-top: 20px; border-top: 1px dashed #cbd5e1; padding-top: 15px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                        <div>
                            <strong style="font-size: 13px; color: #334155;">
                                <i class="fa fa-list-alt text-primary"></i> <span id="txAuditHeading">Itemized Transaction Audit Trail</span>
                            </strong>
                            <span class="label label-info" id="txAuditCountBadge" style="margin-left: 6px;">0 Receipts</span>
                        </div>
                        <span style="font-size: 11px; color: #64748b;" id="txAuditSubtext">Detailed payment receipts for selected day</span>
                    </div>
                    <div class="table-responsive" style="max-height: 280px; overflow-y: auto; border: 1px solid #e2e8f0; border-radius: 6px;">
                        <table class="table table-bordered table-striped table-condensed" style="font-size: 11.5px; margin-bottom: 0;">
                            <thead>
                                <tr style="background: #f8fafc; color: #334155;">
                                    <th style="width: 40px;" class="text-center">#</th>
                                    <th style="width: 140px;">Payment ID / Ref</th>
                                    <th class="text-center" style="width: 80px;">Time</th>
                                    <th>Cashier / Staff</th>
                                    <th>Customer</th>
                                    <th>Method &amp; Details</th>
                                    <th class="text-right" style="width: 110px;">Paid Amount</th>
                                </tr>
                            </thead>
                            <tbody id="cashierTxAuditBody">
                                <!-- Populated dynamically by JS -->
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </div>

    </div> <!-- /#tab-cashier -->

    <!-- ========================================================= -->
    <!-- TAB 5: Sales Orders & Returns Register -->
    <!-- ========================================================= -->
    <div id="tab-orders" class="report-tab-pane">

    <!-- Returns Breakdown in this Period -->
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
                                <td>
                                    <?php if ($pret['refund_method'] === 'Store Credit / Exchange' || $pret['refund_method'] === 'In-Register Trade-In'): ?>
                                        <span class="label label-info" style="background-color: #0284c7;"><i class="fa fa-exchange"></i> In-Register Exchange</span>
                                    <?php else: ?>
                                        <span class="label label-default"><?php echo htmlspecialchars($pret['refund_method']); ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr style="background: #fef2f2; font-weight: bold;">
                                <th colspan="8" class="text-right">Total Standalone Cash Refunds Deducted from Gross Sales:</th>
                                <th class="text-right" style="color: #dc2626; font-size: 14px;">-&#8369;<?php echo number_format($total_standalone_refunds, 2); ?></th>
                                <th colspan="4"></th>
                            </tr>
                            <?php if ($total_exchange_credits > 0): ?>
                            <tr style="background: #f0f9ff; font-weight: bold;">
                                <th colspan="8" class="text-right" style="color: #0369a1;">In-Register Exchange / Trade-In Credits (Netted on POS Invoices):</th>
                                <th class="text-right" style="color: #0284c7; font-size: 14px;">&#8369;<?php echo number_format($total_exchange_credits, 2); ?></th>
                                <th colspan="4" style="color: #64748b; font-size: 11px;">Already netted against invoice paid amount</th>
                            </tr>
                            <?php endif; ?>
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
                    
                    <!-- Line Item Color Legend Toolbar -->
                    <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px; margin-bottom: 14px; padding: 8px 14px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px;" class="no-print">
                        <div style="font-size: 12px; font-weight: 700; color: #334155; display: flex; align-items: center; gap: 6px;">
                            <i class="fa fa-info-circle text-primary"></i> Line Item Color Legend:
                        </div>
                        <div style="display: flex; align-items: center; gap: 14px; flex-wrap: wrap; font-size: 11.5px;">
                            <span style="display: inline-flex; align-items: center; gap: 5px;">
                                <span style="width: 10px; height: 10px; border-radius: 50%; background: #3b82f6; display: inline-block;"></span>
                                <span style="color: #334155; font-weight: 600;">Standard Purchase</span>
                            </span>
                            <span style="display: inline-flex; align-items: center; gap: 5px;">
                                <span style="width: 10px; height: 10px; border-radius: 50%; background: #ea580c; display: inline-block;"></span>
                                <span style="color: #c2410c; font-weight: 700;">🟠 In-Register Trade-In / Exchange Credit</span>
                            </span>
                            <span style="display: inline-flex; align-items: center; gap: 5px;">
                                <span style="width: 10px; height: 10px; border-radius: 50%; background: #dc2626; display: inline-block;"></span>
                                <span style="color: #991b1b; font-weight: 700;">🔴 Standalone Cash / Card Refund</span>
                            </span>
                        </div>
                    </div>

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
                                    <?php if (!empty($row['has_exchange_credit'])): ?>
                                        <div style="margin-top: 3px;">
                                            <span class="label" style="background: #ea580c; color: #ffffff; font-size: 9.5px; font-weight: 800; padding: 2px 5px; border-radius: 3px;">
                                                <i class="fa fa-exchange"></i> EXCHANGE
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($row['has_standalone_refund'])): ?>
                                        <div style="margin-top: 3px;">
                                            <span class="label label-danger" style="background: #dc2626; color: #ffffff; font-size: 9.5px; font-weight: 800; padding: 2px 5px; border-radius: 3px;">
                                                <i class="fa fa-undo"></i> HAS REFUND
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($row['customer_name']); ?></strong><br>
                                    <span style="font-size: 12px; color: #64748b;"><?php echo htmlspecialchars($row['customer_email']); ?></span>
                                </td>
                                <td>
                                    <?php foreach ($order_items as $it): ?>
                                        <?php if (!empty($it['is_return_credit'])): ?>
                                            <!-- 🟠 Option 1: Orange/Amber Highlighting for Trade-In Return Credits -->
                                            <div style="background: #fff7ed; border: 1px solid #fed7aa; border-left: 3.5px solid #ea580c; border-radius: 5px; padding: 4px 8px; margin-bottom: 4px; box-shadow: 0 1px 2px rgba(234, 88, 12, 0.05);">
                                                <div style="display: flex; justify-content: space-between; align-items: baseline; flex-wrap: wrap; gap: 4px;">
                                                    <div>
                                                        <span class="label" style="background: #ea580c; color: #fff; font-size: 9px; font-weight: 800; padding: 1px 5px; border-radius: 3px; text-transform: uppercase;">
                                                            <i class="fa fa-exchange"></i> TRADE-IN CREDIT
                                                        </span>
                                                        <strong style="color: #9a3412; font-size: 12px; margin-left: 3px;"><?php echo htmlspecialchars($it['product_name']); ?></strong>
                                                    </div>
                                                    <span style="font-weight: 800; color: #ea580c; font-size: 11.5px;">
                                                        -&#8369;<?php echo number_format(abs($it['subtotal'] ?? ($it['quantity'] * $it['unit_price'])), 2); ?>
                                                    </span>
                                                </div>
                                                <div style="color: #c2410c; font-size: 10.5px; margin-top: 2px;">
                                                    &times; <?php echo abs($it['quantity']); ?> pcs @ -&#8369;<?php echo number_format(abs($it['unit_price']), 2); ?> <span style="font-style: italic; color: #7c2d12;">(Credit netted on invoice)</span>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <!-- Standard Purchased Item -->
                                            <div style="margin-bottom: 3px;">
                                                <strong><?php echo htmlspecialchars($it['product_name']); ?></strong> 
                                                &times; <?php echo $it['quantity']; ?>
                                                <span style="color: #64748b; font-size: 11px;">
                                                    (@ &#8369;<?php echo number_format($it['unit_price'], 2); ?> | Ca: &#8369;<?php echo number_format($it['unit_capital'], 2); ?>)
                                                </span>
                                            </div>
                                        <?php endif; ?>
                                    <?php endforeach; ?>

                                    <!-- 🔴 Option 1: Red Highlighting for Associated Standalone Cash/Card Refunds -->
                                    <?php if (!empty($row['associated_returns'])): ?>
                                        <?php foreach ($row['associated_returns'] as $ret_entry): ?>
                                            <div style="background: #fef2f2; border: 1px solid #fecaca; border-left: 3.5px solid #dc2626; border-radius: 5px; padding: 4px 8px; margin-top: 4px; margin-bottom: 3px; box-shadow: 0 1px 2px rgba(220, 38, 38, 0.05);">
                                                <div style="display: flex; justify-content: space-between; align-items: baseline; flex-wrap: wrap; gap: 4px;">
                                                    <div>
                                                        <span class="label label-danger" style="background: #dc2626; color: #fff; font-size: 9px; font-weight: 800; padding: 1px 5px; border-radius: 3px; text-transform: uppercase;">
                                                            <i class="fa fa-undo"></i> REFUND (<?php echo $ret_entry['quantity_returned']; ?> pcs)
                                                        </span>
                                                        <strong style="color: #991b1b; font-size: 12px; margin-left: 3px;"><?php echo htmlspecialchars($ret_entry['product_name']); ?></strong>
                                                    </div>
                                                    <span style="font-weight: 800; color: #dc2626; font-size: 11.5px;">
                                                        -&#8369;<?php echo number_format($ret_entry['item_refund'], 2); ?>
                                                    </span>
                                                </div>
                                                <div style="color: #b91c1c; font-size: 10.5px; margin-top: 2px;">
                                                    Ref: <strong><?php echo htmlspecialchars($ret_entry['return_reference']); ?></strong> &bull; Method: <?php echo htmlspecialchars($ret_entry['refund_method']); ?>
                                                    <?php if (!empty($ret_entry['return_reason'])): ?>
                                                        &bull; Reason: <em><?php echo htmlspecialchars($ret_entry['return_reason']); ?></em>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
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
                                    <?php if (!empty($row['order_capital_at_risk']) && $row['order_capital_at_risk'] > 0.01): ?>
                                        <div style="font-size: 10.5px; color: #d97706; font-weight: 700;" title="Unrecovered item capital pending collection">
                                            (&#8369;<?php echo number_format($row['order_capital_at_risk'], 2); ?> at risk)
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-right" style="font-weight: bold; color: <?php echo ($row['order_profit'] > 0 ? '#10b981' : '#64748b'); ?>;">
                                    +&#8369;<?php echo number_format($row['order_profit'], 2); ?>
                                    <br><span class="badge" style="background-color: <?php echo ($row['order_profit'] > 0 ? '#10b981' : '#94a3b8'); ?>; font-size: 10px;"><?php echo number_format($row['order_margin'], 1); ?>%</span>
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

                                    <!-- Purchase Order Modal (Unified POS Format & Profit Breakdown) -->
                                    <div id="po-report-modal-<?php echo $row['id']; ?>" class="modal fade" role="dialog" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog modal-lg" style="max-width: 750px; margin-top: 30px;">
                                            <div class="modal-content" style="border-radius: 10px; overflow: hidden; border: none; box-shadow: 0 15px 40px rgba(0,0,0,0.2);">
                                                
                                                <!-- Modern Modal Header -->
                                                <div class="modal-header-modern" style="background: #0284c7; display: flex; justify-content: space-between; align-items: center; padding: 14px 20px; color: #ffffff;">
                                                    <h4 class="modal-title" style="margin: 0; font-size: 16px; font-weight: 700; display: flex; align-items: center; gap: 8px; color: #ffffff;">
                                                        <i class="fa fa-file-text-o"></i> Customer Purchase Order Voucher — <span style="font-family: monospace; font-size: 17px;"><?php echo htmlspecialchars($row['txnid'] ?: $row['payment_id']); ?></span>
                                                    </h4>
                                                    <div style="display: flex; align-items: center; gap: 8px;">
                                                        <button type="button" class="btn btn-xs btn-default" onclick="printPOVoucher('<?php echo $row['id']; ?>')" style="background: #ffffff; color: #0284c7; font-weight: 700; border: none; border-radius: 5px; padding: 6px 14px; font-size: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                                            <i class="fa fa-print"></i> Print Voucher
                                                        </button>
                                                        <button type="button" class="close" data-dismiss="modal" aria-hidden="true" style="color: #ffffff; opacity: 0.9; font-size: 24px; margin: 0; line-height: 1;">&times;</button>
                                                    </div>
                                                </div>
                                                
                                                <div class="modal-body" style="padding: 18px 20px; background: #f8fafc;">
                                                    <!-- Nav Tabs: Voucher vs Profit Breakdown -->
                                                    <ul class="nav nav-pills" style="margin-bottom: 16px; background: #fff; padding: 6px; border-radius: 8px; border: 1px solid #e2e8f0; display: flex; gap: 6px;">
                                                        <li class="active"><a href="#tab-po-thermal-<?php echo $row['id']; ?>" data-toggle="tab" style="font-weight: 700; border-radius: 6px;"><i class="fa fa-file-text-o"></i> PO Voucher Details</a></li>
                                                        <li><a href="#tab-po-profit-<?php echo $row['id']; ?>" data-toggle="tab" style="font-weight: 700; border-radius: 6px;"><i class="fa fa-line-chart"></i> Profit &amp; Margin Analysis</a></li>
                                                    </ul>

                                                    <div class="tab-content">
                                                        <!-- Tab 1: PO Voucher View (Approach A) -->
                                                        <div class="tab-pane active" id="tab-po-thermal-<?php echo $row['id']; ?>">
                                                            
                                                            <?php 
                                                            $is_paid_status = ($row['payment_status'] === 'Paid' || $row['payment_status'] === 'Completed');
                                                            ?>
                                                            <!-- Status & Overview Bar -->
                                                            <div style="background: <?php echo $is_paid_status ? '#ecfdf5' : '#fffbeb'; ?>; border: 1.5px solid <?php echo $is_paid_status ? '#a7f3d0' : '#fde68a'; ?>; border-radius: 8px; padding: 12px 18px; margin-bottom: 18px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                                                                <div style="display: flex; align-items: center; gap: 10px;">
                                                                    <span class="label" style="background: <?php echo $is_paid_status ? '#059669' : '#f59e0b'; ?>; color: #ffffff; font-size: 12px; font-weight: 800; padding: 5px 10px; border-radius: 4px; text-transform: uppercase;">
                                                                        <i class="fa <?php echo $is_paid_status ? 'fa-check-circle' : 'fa-clock-o'; ?>"></i> <?php echo htmlspecialchars($row['payment_status']); ?>
                                                                    </span>
                                                                    <span style="font-size: 12.5px; color: <?php echo $is_paid_status ? '#065f46' : '#78350f'; ?>; font-weight: 600;">
                                                                        <?php echo $is_paid_status ? 'Purchase Order fulfilled &amp; recorded' : 'Customer must settle payment at counter'; ?>
                                                                    </span>
                                                                </div>
                                                                <div style="font-size: 12px; color: #64748b; font-weight: 600;">
                                                                    <i class="fa fa-calendar"></i> <?php echo date('M d, Y h:i A', strtotime($row['payment_date'])); ?>
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
                                                                            <?php echo htmlspecialchars($row['customer_name'] ?? 'Walk-in Customer'); ?>
                                                                        </div>
                                                                        <?php if (!empty($row['customer_email'])): ?>
                                                                            <div style="font-size: 12px; color: #475569;"><i class="fa fa-envelope" style="width: 14px; color: #0284c7;"></i> <?php echo htmlspecialchars($row['customer_email']); ?></div>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                </div>
                                                                <div class="col-sm-6 col-xs-12" style="margin-bottom: 10px;">
                                                                    <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px 16px; height: 100%;">
                                                                        <div style="font-size: 11px; font-weight: 800; text-transform: uppercase; color: #64748b; margin-bottom: 8px; letter-spacing: 0.5px;">
                                                                            <i class="fa fa-building-o"></i> Store &amp; Fulfillment
                                                                        </div>
                                                                        <div style="font-size: 13.5px; font-weight: 700; color: #0f172a; margin-bottom: 3px;">
                                                                            <?php echo htmlspecialchars(!empty($current_supplier_data['supplier_name']) ? $current_supplier_data['supplier_name'] : 'SAM & INRI CONSTRUCTION SUPPLY'); ?>
                                                                        </div>
                                                                        <div style="font-size: 12px; color: #475569; margin-bottom: 4px;">
                                                                            <span class="label" style="background: #0284c7; font-size: 10.5px; padding: 3px 7px; border-radius: 3px;"><i class="fa fa-credit-card"></i> <?php echo htmlspecialchars($row['payment_method'] ?? 'Purchase Order'); ?></span>
                                                                        </div>
                                                                        <?php if (!empty($current_supplier_data['supplier_phone'])): ?>
                                                                            <div style="font-size: 11.5px; color: #64748b;"><i class="fa fa-phone" style="width: 14px;"></i> Tel: <?php echo htmlspecialchars($current_supplier_data['supplier_phone']); ?></div>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            <!-- Ordered Items Table -->
                                                            <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; margin-bottom: 18px;">
                                                                <div style="padding: 10px 16px; background: #f1f5f9; border-bottom: 1px solid #e2e8f0; font-size: 12px; font-weight: 800; text-transform: uppercase; color: #475569; letter-spacing: 0.5px;">
                                                                    <i class="fa fa-shopping-cart"></i> Ordered Items (<?php echo count($order_items); ?>)
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
                                                                            $calc_subtotal = 0;
                                                                            $oit_idx = 0;
                                                                            foreach ($order_items as $oit): 
                                                                                $oit_idx++;
                                                                                $oit_qty = intval($oit['quantity'] ?? 1);
                                                                                $oit_price = floatval($oit['unit_price'] ?? 0);
                                                                                $oit_line = floatval($oit['subtotal'] ?? ($oit_qty * $oit_price));
                                                                                $calc_subtotal += $oit_line;
                                                                                $is_oit_return = !empty($oit['is_return_credit']) || ($oit_price < 0);
                                                                                $unit_label = ($oit_qty > 1 || $oit_qty < -1 ? 'pcs' : 'pc');
                                                                            ?>
                                                                            <tr style="<?php echo $is_oit_return ? 'background: #fff7ed;' : ''; ?>">
                                                                                <td style="text-align: center; color: <?php echo $is_oit_return ? '#ea580c' : '#94a3b8'; ?>; font-weight: 600; vertical-align: middle;"><?php echo $oit_idx; ?></td>
                                                                                <td style="vertical-align: middle;">
                                                                                    <div style="font-weight: 700; color: <?php echo $is_oit_return ? '#9a3412' : '#0f172a'; ?>;">
                                                                                        <?php if ($is_oit_return): ?>
                                                                                            <span class="badge" style="background: #ea580c; color: #fff; font-size: 9.5px; font-weight: 800; padding: 2px 6px; margin-right: 4px; vertical-align: text-top;"><i class="fa fa-exchange"></i> TRADE-IN CREDIT</span>
                                                                                        <?php endif; ?>
                                                                                        <?php echo htmlspecialchars($oit['product_name'] ?? ''); ?>
                                                                                    </div>
                                                                                    <?php if (!empty($oit['size']) || !empty($oit['color'])): ?>
                                                                                        <div style="margin-top: 3px; display: flex; gap: 4px;">
                                                                                            <?php if (!empty($oit['size']) && $oit['size'] !== '-'): ?>
                                                                                                <span class="badge" style="background: <?php echo $is_oit_return ? '#fed7aa; color: #9a3412' : '#f1f5f9; color: #475569'; ?>; font-size: 10.5px; font-weight: 600;">Size: <?php echo htmlspecialchars($oit['size']); ?></span>
                                                                                            <?php endif; ?>
                                                                                            <?php if (!empty($oit['color']) && $oit['color'] !== '-'): ?>
                                                                                                <span class="badge" style="background: <?php echo $is_oit_return ? '#fed7aa; color: #9a3412' : '#f1f5f9; color: #475569'; ?>; font-size: 10.5px; font-weight: 600;">Color: <?php echo htmlspecialchars($oit['color']); ?></span>
                                                                                            <?php endif; ?>
                                                                                        </div>
                                                                                    <?php endif; ?>
                                                                                </td>
                                                                                <td style="text-align: center; font-weight: 700; color: <?php echo $is_oit_return ? '#ea580c' : '#1e293b'; ?>; vertical-align: middle;"><?php echo abs($oit_qty); ?> <span style="font-size: 11px; color: #64748b; font-weight: normal;"><?php echo $unit_label; ?></span></td>
                                                                                <td style="text-align: right; color: <?php echo $is_oit_return ? '#ea580c' : '#475569'; ?>; font-weight: 600; vertical-align: middle;"><?php echo $is_oit_return ? '-&#8369;' . number_format(abs($oit_price), 2) : '&#8369;' . number_format($oit_price, 2); ?></td>
                                                                                <td style="text-align: right; font-weight: 800; color: <?php echo $is_oit_return ? '#ea580c' : '#0f172a'; ?>; vertical-align: middle;"><?php echo $is_oit_return ? '-&#8369;' . number_format(abs($oit_line), 2) : '&#8369;' . number_format($oit_line, 2); ?></td>
                                                                            </tr>
                                                                            <?php endforeach; ?>
                                                                        </tbody>
                                                                    </table>
                                                                </div>
                                                            </div>

                                                            <!-- Associated Standalone Refund Records (if any) -->
                                                            <?php if (!empty($row['associated_returns'])): ?>
                                                            <div style="background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: 12px 16px; margin-bottom: 18px;">
                                                                <div style="font-size: 11.5px; font-weight: 800; text-transform: uppercase; color: #dc2626; margin-bottom: 8px; letter-spacing: 0.5px; display: flex; align-items: center; gap: 6px;">
                                                                    <i class="fa fa-undo"></i> Associated Standalone Return &amp; Refund Records (<?php echo count($row['associated_returns']); ?>)
                                                                </div>
                                                                <div class="table-responsive" style="margin-bottom: 0;">
                                                                    <table class="table" style="margin-bottom: 0; font-size: 12px; background: #ffffff; border-radius: 6px; overflow: hidden;">
                                                                        <thead>
                                                                            <tr style="background: #fee2e2; color: #991b1b;">
                                                                                <th>Return Ref</th>
                                                                                <th>Item Refunded</th>
                                                                                <th class="text-center">Qty</th>
                                                                                <th>Method</th>
                                                                                <th>Reason</th>
                                                                                <th class="text-right">Refund Amount</th>
                                                                            </tr>
                                                                        </thead>
                                                                        <tbody>
                                                                            <?php foreach ($row['associated_returns'] as $ar): ?>
                                                                            <tr>
                                                                                <td style="font-weight: 700; color: #991b1b;"><?php echo htmlspecialchars($ar['return_reference']); ?></td>
                                                                                <td style="font-weight: 600; color: #0f172a;"><?php echo htmlspecialchars($ar['product_name']); ?></td>
                                                                                <td class="text-center font-weight-bold" style="color: #dc2626;"><?php echo $ar['quantity_returned']; ?> pcs</td>
                                                                                <td><span class="badge" style="background: #ef4444; color: #fff; font-size: 10px;"><?php echo htmlspecialchars($ar['refund_method']); ?></span></td>
                                                                                <td style="color: #64748b; font-style: italic;"><?php echo htmlspecialchars($ar['return_reason'] ?: 'No reason stated'); ?></td>
                                                                                <td class="text-right" style="font-weight: 800; color: #dc2626;">-&#8369;<?php echo number_format($ar['item_refund'], 2); ?></td>
                                                                            </tr>
                                                                            <?php endforeach; ?>
                                                                        </tbody>
                                                                    </table>
                                                                </div>
                                                            </div>
                                                            <?php endif; ?>

                                                            <!-- Financial Totals Block -->
                                                            <div style="display: flex; justify-content: flex-end;">
                                                                <div style="width: 100%; max-width: 320px; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px 18px;">
                                                                    <div style="display: flex; justify-content: space-between; font-size: 13px; color: #475569; margin-bottom: 6px;">
                                                                        <span>Subtotal:</span>
                                                                        <span style="font-weight: 700; color: #0f172a;">&#8369;<?php echo number_format($calc_subtotal, 2); ?></span>
                                                                    </div>
                                                                    <?php if (!empty($row['delivery_fee']) && floatval($row['delivery_fee']) > 0): ?>
                                                                    <div style="display: flex; justify-content: space-between; font-size: 13px; color: #475569; margin-bottom: 6px;">
                                                                        <span>Delivery Fee:</span>
                                                                        <span style="font-weight: 600; color: #0f172a;">&#8369;<?php echo number_format(floatval($row['delivery_fee']), 2); ?></span>
                                                                    </div>
                                                                    <?php endif; ?>
                                                                    <div style="border-top: 2px solid #e2e8f0; padding-top: 8px; margin-top: 6px; display: flex; justify-content: space-between; align-items: baseline;">
                                                                        <span style="font-size: 14px; font-weight: 800; color: #0f172a;">TOTAL PAID:</span>
                                                                        <span style="font-size: 18px; font-weight: 800; color: #0284c7;">&#8369;<?php echo number_format(floatval($row['paid_amount']), 2); ?></span>
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            <!-- Hidden Silent Thermal Print Element -->
                                                            <div id="po-printable-thermal-<?php echo $row['id']; ?>" style="display: none;">
                                                                <div style="font-family: 'Courier New', Consolas, monospace; font-size: 11pt; line-height: 1.25; color: #000; text-align: left;">
                                                                    <div style="text-align: center; font-weight: bold; overflow: hidden; white-space: nowrap;">================================</div>
                                                                    <div style="text-align: center;">
                                                                        <div style="font-size: 12pt; font-weight: bold; text-transform: uppercase;"><?php echo htmlspecialchars(!empty($current_supplier_data['supplier_name']) ? strtoupper($current_supplier_data['supplier_name']) : 'SAM & INRI CONSTRUCTION SUPPLY'); ?></div>
                                                                        <?php if (!empty($current_supplier_data['supplier_address'])): ?>
                                                                            <div style="font-size: 9.5pt;"><?php echo htmlspecialchars($current_supplier_data['supplier_address']); ?></div>
                                                                        <?php endif; ?>
                                                                        <div style="font-size: 10pt;">Tel: <?php echo htmlspecialchars(!empty($current_supplier_data['supplier_phone']) ? $current_supplier_data['supplier_phone'] : '09612735733'); ?></div>
                                                                        <div style="font-size: 11pt; font-weight: bold; text-transform: uppercase; margin-top: 2px;">PURCHASE ORDER VOUCHER</div>
                                                                        <div style="font-size: 10pt; font-weight: bold; text-transform: uppercase;"><?php echo ($row['payment_status'] === 'Paid' || $row['payment_status'] === 'Completed') ? '(PAID)' : '(UNPAID)'; ?></div>
                                                                    </div>
                                                                    <div style="text-align: center; font-weight: bold; overflow: hidden; white-space: nowrap;">================================</div>
                                                                    <table style="width: 100%; border-collapse: collapse; font-family: inherit; font-size: 10.5pt; margin-bottom: 2px;">
                                                                        <tr><td style="width: 28%; font-weight: bold;">PO NO   :</td><td style="font-weight: bold;"><?php echo htmlspecialchars($row['txnid'] ?: $row['payment_id']); ?></td></tr>
                                                                        <tr><td style="font-weight: bold;">CUSTOMER:</td><td><?php echo htmlspecialchars($row['customer_name'] ?? 'Walk-in Customer'); ?></td></tr>
                                                                        <tr><td style="font-weight: bold;">STATUS  :</td><td style="font-weight: bold;"><?php echo htmlspecialchars($row['payment_status'] ?? 'Paid'); ?></td></tr>
                                                                        <tr><td style="font-weight: bold;">DATE    :</td><td><?php echo date('Y-m-d H:i:s', strtotime($row['payment_date'])); ?></td></tr>
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
                                                                            <?php foreach ($order_items as $oit): 
                                                                                $oit_qty = intval($oit['quantity'] ?? 1);
                                                                                $oit_price = floatval($oit['unit_price'] ?? 0);
                                                                                $oit_line = floatval($oit['subtotal'] ?? ($oit_qty * $oit_price));
                                                                                $is_oit_return = !empty($oit['is_return_credit']) || ($oit_price < 0);
                                                                                $unit_label = ($oit_qty > 1 || $oit_qty < -1 ? 'pcs' : 'pc');
                                                                            ?>
                                                                            <tr>
                                                                                <td colspan="2" style="text-align: left; padding-top: 3px; font-weight: bold; word-break: break-word;">
                                                                                    <?php if ($is_oit_return): ?>[TRADE-IN RETURN CREDIT] <?php endif; ?>
                                                                                    <?php echo htmlspecialchars($oit['product_name'] ?? ''); ?>
                                                                                    <?php if (!empty($oit['size']) || !empty($oit['color'])): ?>
                                                                                        <div style="font-size: 9pt; font-weight: normal;">
                                                                                            <?php if (!empty($oit['size']) && $oit['size'] !== '-') echo 'Size: ' . htmlspecialchars($oit['size']) . ' '; ?>
                                                                                            <?php if (!empty($oit['color']) && $oit['color'] !== '-') echo 'Color: ' . htmlspecialchars($oit['color']); ?>
                                                                                        </div>
                                                                                    <?php endif; ?>
                                                                                </td>
                                                                            </tr>
                                                                            <tr>
                                                                                <td style="text-align: left; padding-left: 8px; padding-bottom: 3px;">
                                                                                    <?php echo abs($oit_qty); ?> <?php echo $unit_label; ?> @ <?php echo $is_oit_return ? '-' . number_format(abs($oit_price), 2) : number_format($oit_price, 2); ?>
                                                                                </td>
                                                                                <td style="text-align: right; padding-bottom: 3px; white-space: nowrap; vertical-align: bottom;">
                                                                                    <?php echo $is_oit_return ? '-' . number_format(abs($oit_line), 2) : number_format($oit_line, 2); ?>
                                                                                </td>
                                                                            </tr>
                                                                            <?php endforeach; ?>

                                                                            <?php if (!empty($row['associated_returns'])): ?>
                                                                            <tr>
                                                                                <td colspan="2" style="text-align: left; padding-top: 6px; font-weight: bold; font-size: 9.5pt; color: #000;">
                                                                                    -- STANDALONE REFUNDS --
                                                                                </td>
                                                                            </tr>
                                                                            <?php foreach ($row['associated_returns'] as $ar): ?>
                                                                            <tr>
                                                                                <td style="text-align: left; padding-left: 8px; font-size: 9pt;">
                                                                                    [REFUND] <?php echo htmlspecialchars($ar['product_name']); ?> (<?php echo $ar['quantity_returned']; ?> pcs)
                                                                                </td>
                                                                                <td style="text-align: right; font-size: 9pt; vertical-align: bottom;">
                                                                                    -<?php echo number_format($ar['item_refund'], 2); ?>
                                                                                </td>
                                                                            </tr>
                                                                            <?php endforeach; ?>
                                                                            <?php endif; ?>
                                                                        </tbody>
                                                                    </table>
                                                                    <div style="text-align: center; overflow: hidden; white-space: nowrap;">--------------------------------</div>
                                                                    <table style="width: 100%; border-collapse: collapse; font-family: inherit; font-size: 10.5pt; margin: 2px 0;">
                                                                        <tr><td>Subtotal:</td><td style="text-align: right;"><?php echo number_format($calc_subtotal, 2); ?></td></tr>
                                                                        <?php if (!empty($row['delivery_fee']) && floatval($row['delivery_fee']) > 0): ?>
                                                                        <tr><td>Delivery Fee:</td><td style="text-align: right;"><?php echo number_format(floatval($row['delivery_fee']), 2); ?></td></tr>
                                                                        <?php endif; ?>
                                                                        <tr style="font-weight: bold;"><td style="font-size: 1.08em;">TOTAL DUE:</td><td style="text-align: right; font-size: 1.08em;">PHP <?php echo number_format(floatval($row['paid_amount']), 2); ?></td></tr>
                                                                    </table>
                                                                    <div style="text-align: center; font-weight: bold; overflow: hidden; white-space: nowrap;">================================</div>
                                                                    <div style="text-align: center; line-height: 1.35; padding: 2px 0;">
                                                                        <?php if ($row['payment_status'] === 'Paid' || $row['payment_status'] === 'Completed'): ?>
                                                                            <div style="font-weight: bold;">*** OFFICIAL PO VOUCHER ***</div>
                                                                            <div style="margin-top: 3px;">Thank you for your business!</div>
                                                                            <div style="font-size: 9pt; margin-top: 2px;">eConstruction Supply POS</div>
                                                                        <?php else: ?>
                                                                            <div style="font-weight: bold; font-size: 11pt; letter-spacing: 0.5px;">*** ORDER ON HOLD ***</div>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                    <div style="text-align: center; font-weight: bold; overflow: hidden; white-space: nowrap;">================================</div>
                                                                </div>
                                                            </div>

                                                        </div>

                                                        <!-- Tab 2: Profit Breakdown (Preserved) -->
                                                        <div class="tab-pane" id="tab-po-profit-<?php echo $row['id']; ?>">
                                                            <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px;">
                                                                <div style="border-bottom: 2px solid #0284c7; padding-bottom: 10px; margin-bottom: 14px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap;">
                                                                    <div>
                                                                        <h4 style="margin: 0; font-weight: 800; color: #0f172a;">E-CONSTRUCTION SUPPLY</h4>
                                                                        <p style="margin: 2px 0 0 0; color: #64748b; font-size: 12px;">Transaction: <strong><?php echo htmlspecialchars($row['txnid'] ?: $row['payment_id']); ?></strong></p>
                                                                    </div>
                                                                    <div style="text-align: right;">
                                                                        <span class="label label-success" style="font-size: 11px;"><?php echo htmlspecialchars($row['payment_status']); ?></span>
                                                                        <div style="font-size: 11.5px; color: #64748b; margin-top: 2px;"><?php echo date('M d, Y h:i A', strtotime($row['payment_date'])); ?></div>
                                                                    </div>
                                                                </div>

                                                                <table class="table table-bordered table-striped" style="margin: 0; font-size: 12px;">
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
                                                                        <?php foreach($order_items as $oit): 
                                                                            $is_oit_return = !empty($oit['is_return_credit']) || floatval($oit['unit_price'] ?? 0) < 0;
                                                                        ?>
                                                                        <tr style="<?php echo $is_oit_return ? 'background: #fff7ed;' : ''; ?>">
                                                                            <td>
                                                                                <?php if ($is_oit_return): ?>
                                                                                    <span class="badge" style="background: #ea580c; color: #fff; font-size: 9px; font-weight: 800; padding: 1px 5px; margin-right: 3px;"><i class="fa fa-exchange"></i> TRADE-IN</span>
                                                                                <?php endif; ?>
                                                                                <strong style="<?php echo $is_oit_return ? 'color: #9a3412;' : ''; ?>"><?php echo htmlspecialchars($oit['product_name']); ?></strong>
                                                                            </td>
                                                                            <td><?php echo htmlspecialchars($oit['size'] ?: '-'); ?></td>
                                                                            <td><?php echo htmlspecialchars($oit['color'] ?: '-'); ?></td>
                                                                            <td class="text-center font-weight-bold" style="<?php echo $is_oit_return ? 'color: #ea580c;' : ''; ?>"><?php echo abs($oit['quantity']); ?></td>
                                                                            <td class="text-right" style="<?php echo $is_oit_return ? 'color: #ea580c;' : ''; ?>"><?php echo $is_oit_return ? '-&#8369;' . number_format(abs($oit['unit_price']), 2) : '&#8369;' . number_format($oit['unit_price'], 2); ?></td>
                                                                            <td class="text-right text-muted"><?php echo $is_oit_return ? '-&#8369;' . number_format(abs($oit['unit_capital']), 2) : '&#8369;' . number_format($oit['unit_capital'], 2); ?></td>
                                                                            <td class="text-right font-weight-bold" style="<?php echo $is_oit_return ? 'color: #ea580c;' : ''; ?>"><?php echo $is_oit_return ? '-&#8369;' . number_format(abs($oit['subtotal']), 2) : '&#8369;' . number_format($oit['subtotal'], 2); ?></td>
                                                                            <td class="text-right" style="color: <?php echo $is_oit_return ? '#ea580c' : ($oit['profit'] >= 0 ? '#10b981' : '#dc2626'); ?>; font-weight: 800;">
                                                                                <?php echo ($oit['profit'] >= 0 ? '+&#8369;' : '-&#8369;') . number_format(abs($oit['profit']), 2); ?>
                                                                            </td>
                                                                        </tr>
                                                                        <?php endforeach; ?>
                                                                        <tr style="font-weight: bold; background: #f0fdf4; font-size: 13px;">
                                                                            <td colspan="6" class="text-right">Total Order Sales &amp; Net Profit:</td>
                                                                            <td class="text-right" style="color: #0284c7;">&#8369;<?php echo number_format($row['paid_amount'], 2); ?></td>
                                                                            <td class="text-right" style="color: #10b981;">+&#8369;<?php echo number_format($row['order_profit'], 2); ?></td>
                                                                        </tr>
                                                                    </tbody>
                                                                </table>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="modal-footer" style="background: #f1f5f9; border-top: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; padding: 12px 20px;">
                                                    <button type="button" class="btn btn-default btn-sm" data-dismiss="modal" style="font-weight: 700; border-radius: 5px;">Close</button>
                                                    <button type="button" class="btn btn-primary btn-sm" onclick="printPOVoucher('<?php echo $row['id']; ?>')" style="font-weight: 700; background-color: #0284c7; border-color: #0369a1; border-radius: 5px; padding: 6px 16px;" title="Print Voucher on Thermal Printer">
                                                        <i class="fa fa-print"></i> Print Purchase Order Voucher
                                                    </button>
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
                            <?php if ($total_standalone_refunds > 0): ?>
                            <tr style="background: #fef2f2; font-weight: bold;">
                                <th colspan="7" class="text-right" style="color: #991b1b;">Less Standalone Cash/Card Refunds:</th>
                                <th class="text-right" style="color: #dc2626; font-size: 14px;">-&#8369;<?php echo number_format($total_standalone_refunds, 2); ?></th>
                                <th class="text-right" style="color: #991b1b; font-size: 14px;">-&#8369;<?php echo number_format($total_returns_cost, 2); ?></th>
                                <th class="text-right" style="color: #dc2626; font-size: 14px;">-&#8369;<?php echo number_format($total_returns_profit_deduction, 2); ?></th>
                                <th colspan="2"></th>
                            </tr>
                            <?php endif; ?>
                            <?php if ($total_exchange_credits > 0): ?>
                            <tr style="background: #f0f9ff; font-weight: bold;">
                                <th colspan="7" class="text-right" style="color: #0369a1;">In-Register Exchange Credits Applied (Netted on Invoices):</th>
                                <th class="text-right" style="color: #0284c7; font-size: 14px;">&#8369;<?php echo number_format($total_exchange_credits, 2); ?></th>
                                <th colspan="4" style="color: #64748b; font-size: 11px; font-weight: normal;">Already netted against invoice paid amount</th>
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

    </div> <!-- /#tab-orders -->

</section>

<!-- Analytics Chart Initializer Script -->
<script>
// =============================================================
// Executive Multi-Tab Navigation Controller
// =============================================================
var TAB_ID_MAP = {
    '#overview': 'tab-overview',
    '#products': 'tab-products',
    '#profit': 'tab-profit',
    '#cashier': 'tab-cashier',
    '#orders': 'tab-orders',
    'tab-overview': 'tab-overview',
    'tab-products': 'tab-products',
    'tab-profit': 'tab-profit',
    'tab-cashier': 'tab-cashier',
    'tab-orders': 'tab-orders'
};

var TAB_HASH_MAP = {
    'tab-overview': '#overview',
    'tab-products': '#products',
    'tab-profit': '#profit',
    'tab-cashier': '#cashier',
    'tab-orders': '#orders'
};

function switchReportTab(tabKey, updateHash) {
    var targetTabId = TAB_ID_MAP[tabKey] || 'tab-overview';
    var targetHash = TAB_HASH_MAP[targetTabId] || '#overview';

    // 1. Update Navigation Tabs Active State
    var navTabs = document.querySelectorAll('.report-nav-tab');
    for (var i = 0; i < navTabs.length; i++) {
        var t = navTabs[i];
        if (t.getAttribute('data-tab') === targetTabId) {
            t.classList.add('active');
        } else {
            t.classList.remove('active');
        }
    }

    // 2. Update Tab Panes Active State
    var panes = document.querySelectorAll('.report-tab-pane');
    for (var j = 0; j < panes.length; j++) {
        var p = panes[j];
        if (p.id === targetTabId) {
            p.classList.add('active');
        } else {
            p.classList.remove('active');
        }
    }

    // 3. Update URL Hash without scrolling
    if (updateHash) {
        if (history.replaceState) {
            history.replaceState(null, null, targetHash);
        } else {
            window.location.hash = targetHash;
        }
    }

    // 4. Trigger Chart Resizing & Table Alignment for Hidden Elements
    setTimeout(function() {
        if (targetTabId === 'tab-products') {
            if (window.chartLeaderboardInstance) {
                window.chartLeaderboardInstance.resize();
            } else if (typeof renderMonthlyLeaderboard === 'function') {
                renderMonthlyLeaderboard();
            }
            if (window.chartProductAnnualTrendInstance) {
                window.chartProductAnnualTrendInstance.resize();
            } else if (window.currentTrendProductName && typeof loadProductAnnualTrend === 'function') {
                loadProductAnnualTrend(window.currentTrendProductName);
            }
        } else if (targetTabId === 'tab-profit') {
            if (window.chartHzInstance) {
                window.chartHzInstance.resize();
            } else if (typeof renderHzReport === 'function') {
                renderHzReport();
            }
        } else if (targetTabId === 'tab-cashier') {
            if (window.chartDailyCashierInstance) {
                window.chartDailyCashierInstance.resize();
            } else if (typeof renderDailyCashierInventory === 'function') {
                renderDailyCashierInventory();
            }
        } else if (targetTabId === 'tab-orders') {
            if (window.jQuery && $.fn.dataTable) {
                try {
                    $($.fn.dataTable.tables(true)).DataTable().columns.adjust();
                } catch(e) {}
            }
        }
    }, 60);
}

// Global Browser History / Hashchange Handler
window.addEventListener('hashchange', function() {
    var h = window.location.hash;
    if (h && TAB_ID_MAP[h]) {
        switchReportTab(TAB_ID_MAP[h], false);
    }
});

function toggleFilterInputs(val) {
    $('.filter-input-group').hide();
    if(val === 'day') $('#group_day').show();
    else if(val === 'week') $('#group_week').show();
    else if(val === 'month') $('#group_month').show();
    else if(val === 'quarter') $('#group_quarter').show();
    else if(val === 'year') $('#group_year').show();
    else if(val === 'custom') $('#group_custom').show();
}

// Global Leaderboard Data & Controller
window.monthlyLeaderboardData = <?php echo $monthly_leaderboard_json; ?>;
window.currentLbMetric = '<?php echo $lb_selected_metric; ?>';
window.currentLbMonth = '<?php echo $lb_selected_month; ?>';
window.chartLeaderboardInstance = null;

// Global 1-Year Product Annual Trend Drill-Down Data & Controller
window.productAnnualTrends = <?php echo $product_annual_trends_json; ?>;
window.currentTrendMetric = 'orders';
window.currentTrendProductName = null;
window.chartProductAnnualTrendInstance = null;

var MONTH_SHORT_NAMES = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
var MONTH_FULL_NAMES = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

function escapeHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function selectProductFromLeaderboard(productName, shouldScroll) {
    switchReportTab('tab-products', true);
    loadProductAnnualTrend(productName);
    if (shouldScroll) {
        setTimeout(function() {
            var el = document.getElementById('productAnnualTrendSection');
            if (el) {
                el.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        }, 100);
    }
}

function syncDrilldownDropdown(items) {
    var sel = document.getElementById('drilldownProductSelect');
    if (!sel) return;
    
    var currentVal = sel.value || window.currentTrendProductName;
    sel.innerHTML = '';
    
    if (!items || items.length === 0) {
        var opt = document.createElement('option');
        opt.value = '';
        opt.textContent = 'No Products Available';
        sel.appendChild(opt);
        return;
    }
    
    for (var i = 0; i < items.length; i++) {
        var it = items[i];
        var opt = document.createElement('option');
        opt.value = it.name;
        opt.textContent = '#' + it.rank + ' ' + it.name + ' (' + it.orders + ' ord)';
        sel.appendChild(opt);
    }
    
    if (currentVal && window.productAnnualTrends && window.productAnnualTrends[currentVal]) {
        sel.value = currentVal;
    } else if (items.length > 0) {
        sel.value = items[0].name;
    }
}

function loadProductAnnualTrend(productName) {
    if (!window.productAnnualTrends) return;
    
    var profile = null;
    if (productName && window.productAnnualTrends[productName]) {
        profile = window.productAnnualTrends[productName];
    } else {
        var keys = Object.keys(window.productAnnualTrends);
        if (keys.length > 0) {
            profile = window.productAnnualTrends[keys[0]];
        }
    }
    
    if (!profile) {
        var titleEl = document.getElementById('drilldownProductTitle');
        if (titleEl) titleEl.innerText = 'No Product Data Available';
        return;
    }
    
    window.currentTrendProductName = profile.name;
    
    // Update Select Dropdown if different
    var sel = document.getElementById('drilldownProductSelect');
    if (sel && sel.value !== profile.name) {
        sel.value = profile.name;
    }
    
    // Update Header Titles
    var titleEl = document.getElementById('drilldownProductTitle');
    var subEl = document.getElementById('drilldownProductSubtitle');
    if (titleEl) {
        titleEl.innerHTML = '<span style="color: #0284c7;">' + escapeHtml(profile.name) + '</span>' + 
            ' <span class="badge" style="background:#e0f2fe; color:#0369a1; border:1px solid #bae6fd; font-size:11px; margin-left:6px;"><i class="fa fa-tag"></i> ' + escapeHtml(profile.category || 'General') + '</span>';
    }
    if (subEl) {
        subEl.innerHTML = 'Showing 12-month order frequency, sales volume, and purchase rate velocity for <strong>' + escapeHtml(profile.name) + '</strong> across Year <?php echo $lb_year_int; ?>.';
    }
    
    // Update KPI Summary Cards
    var kpiOrders = document.getElementById('kpiAnnualOrders');
    var kpiUnits = document.getElementById('kpiAnnualUnits');
    var kpiUnitsAvg = document.getElementById('kpiAnnualUnitsAvg');
    var kpiRate = document.getElementById('kpiAnnualRate');
    var kpiPeak = document.getElementById('kpiPeakMonth');
    var kpiPeakStats = document.getElementById('kpiPeakMonthStats');
    
    if (kpiOrders) kpiOrders.innerText = Number(profile.annual_orders || 0).toLocaleString();
    if (kpiUnits) kpiUnits.innerText = Number(profile.annual_units || 0).toLocaleString();
    if (kpiUnitsAvg) {
        var avgUnitsMo = ((profile.annual_units || 0) / 12).toFixed(1);
        kpiUnitsAvg.innerText = 'Avg ' + avgUnitsMo + ' pcs / month';
    }
    if (kpiRate) kpiRate.innerText = Number(profile.avg_purchase_rate || 0).toFixed(2);
    if (kpiPeak) {
        var peakM = profile.peak_month || 1;
        var peakMName = MONTH_FULL_NAMES[peakM - 1] || ('Month ' + peakM);
        kpiPeak.innerText = peakMName;
    }
    if (kpiPeakStats) {
        var peakM = profile.peak_month || 1;
        var peakEntry = profile.monthly && profile.monthly[peakM] ? profile.monthly[peakM] : null;
        var peakOrd = peakEntry ? peakEntry.orders : (profile.peak_orders || 0);
        var peakUnits = peakEntry ? peakEntry.units : 0;
        kpiPeakStats.innerText = peakOrd.toLocaleString() + ' orders (' + peakUnits.toLocaleString() + ' pcs)';
    }
    
    // Populate 12-Month Table
    var tbody = document.getElementById('tableProductAnnualTrendBody');
    if (tbody) {
        var html = '';
        var prevOrders = 0;
        var totOrders = 0, totUnits = 0, totRev = 0;
        
        for (var m = 1; m <= 12; m++) {
            var mEntry = (profile.monthly && profile.monthly[m]) ? profile.monthly[m] : { orders: 0, units: 0, revenue: 0, profit: 0, rate: 0 };
            var mName = MONTH_SHORT_NAMES[m - 1];
            var ord = mEntry.orders || 0;
            var units = mEntry.units || 0;
            var rate = (ord > 0) ? (units / ord).toFixed(2) : '0.00';
            var rev = mEntry.revenue || 0;
            
            totOrders += ord;
            totUnits += units;
            totRev += rev;
            
            // MoM Growth Badge
            var momHtml = '<span class="text-muted" style="font-size:11px;">―</span>';
            if (m > 1) {
                if (prevOrders === 0 && ord > 0) {
                    momHtml = '<span class="label label-primary" style="font-size:10px;">+100%</span>';
                } else if (prevOrders > 0) {
                    var diff = ord - prevOrders;
                    var pct = (diff / prevOrders) * 100;
                    if (pct > 0) {
                        momHtml = '<span class="text-success font-weight-bold" style="font-size:11px;"><i class="fa fa-caret-up"></i> +' + pct.toFixed(0) + '%</span>';
                    } else if (pct < 0) {
                        momHtml = '<span class="text-danger font-weight-bold" style="font-size:11px;"><i class="fa fa-caret-down"></i> ' + pct.toFixed(0) + '%</span>';
                    } else {
                        momHtml = '<span class="text-muted" style="font-size:11px;">0%</span>';
                    }
                }
            }
            prevOrders = ord;
            
            var isPeak = (m === profile.peak_month && ord > 0);
            var rowBg = isPeak ? 'background: #fefce8;' : '';
            var monthCell = '<strong>' + mName + '</strong>';
            if (isPeak) {
                monthCell += ' <span class="label label-warning" style="font-size:9px; padding:1px 4px; background:#f59e0b;" title="Highest volume month"><i class="fa fa-trophy"></i> Peak</span>';
            }
            
            html += '<tr style="' + rowBg + '">' +
                '<td>' + monthCell + '</td>' +
                '<td class="text-right font-weight-bold" style="color: #0284c7;">' + ord.toLocaleString() + '</td>' +
                '<td class="text-right font-weight-bold">' + units.toLocaleString() + '</td>' +
                '<td class="text-right" style="color: #7c3aed; font-weight: 600;">' + rate + '</td>' +
                '<td class="text-right">₱' + Number(rev).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) + '</td>' +
                '<td class="text-center" style="vertical-align: middle;">' + momHtml + '</td>' +
            '</tr>';
        }
        tbody.innerHTML = html;
        
        // Update Table Footers
        var tfOrd = document.getElementById('tfAnnualOrders');
        var tfUnits = document.getElementById('tfAnnualUnits');
        var tfRate = document.getElementById('tfAnnualRate');
        var tfRev = document.getElementById('tfAnnualRevenue');
        
        if (tfOrd) tfOrd.innerText = totOrders.toLocaleString();
        if (tfUnits) tfUnits.innerText = totUnits.toLocaleString();
        if (tfRate) tfRate.innerText = (totOrders > 0 ? (totUnits / totOrders).toFixed(2) : '0.00');
        if (tfRev) tfRev.innerText = '₱' + Number(totRev).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
    }
    
    // Render Dual-Axis Chart
    renderProductAnnualTrendChart(profile);
}

function renderProductAnnualTrendChart(profile) {
    var canvas = document.getElementById('chartProductAnnualTrend');
    if (!canvas) return;
    
    if (window.chartProductAnnualTrendInstance) {
        window.chartProductAnnualTrendInstance.destroy();
    }
    
    var metric = window.currentTrendMetric || 'orders';
    var labels = MONTH_SHORT_NAMES;
    
    var ordersData = [];
    var unitsData = [];
    var rateData = [];
    var revData = [];
    var profitData = [];
    
    for (var m = 1; m <= 12; m++) {
        var entry = (profile.monthly && profile.monthly[m]) ? profile.monthly[m] : { orders: 0, units: 0, revenue: 0, profit: 0, rate: 0 };
        ordersData.push(entry.orders || 0);
        unitsData.push(entry.units || 0);
        rateData.push(entry.rate || ((entry.orders > 0) ? Number((entry.units / entry.orders).toFixed(2)) : 0));
        revData.push(entry.revenue || 0);
        profitData.push(entry.profit || 0);
    }
    
    var primaryDataset = {};
    var secondaryDataset = {
        type: 'line',
        label: 'Purchase Rate (pcs/order)',
        data: rateData,
        borderColor: '#8b5cf6',
        backgroundColor: 'rgba(139, 92, 246, 0.15)',
        borderWidth: 2.5,
        tension: 0.3,
        pointBackgroundColor: '#8b5cf6',
        pointBorderColor: '#ffffff',
        pointBorderWidth: 2,
        pointRadius: 4,
        pointHoverRadius: 6,
        yAxisID: 'y1'
    };
    
    // Highlight peak month in bar chart
    var barColors = [];
    for (var i = 0; i < 12; i++) {
        var mIdx = i + 1;
        if (mIdx === profile.peak_month && ordersData[i] > 0) {
            barColors.push('rgba(245, 158, 11, 0.95)'); // Gold accent for peak
        } else {
            if (metric === 'units') barColors.push('rgba(16, 185, 129, 0.85)');
            else if (metric === 'revenue') barColors.push('rgba(14, 165, 233, 0.85)');
            else if (metric === 'rate') barColors.push('rgba(139, 92, 246, 0.85)');
            else barColors.push('rgba(2, 132, 199, 0.85)'); // Orders
        }
    }
    
    if (metric === 'orders') {
        primaryDataset = {
            type: 'bar',
            label: 'Monthly Orders Count',
            data: ordersData,
            backgroundColor: barColors,
            borderRadius: 5,
            borderWidth: 1,
            borderColor: 'rgba(0,0,0,0.05)',
            yAxisID: 'y'
        };
    } else if (metric === 'units') {
        primaryDataset = {
            type: 'bar',
            label: 'Units Sold (pcs)',
            data: unitsData,
            backgroundColor: barColors,
            borderRadius: 5,
            borderWidth: 1,
            borderColor: 'rgba(0,0,0,0.05)',
            yAxisID: 'y'
        };
    } else if (metric === 'revenue') {
        primaryDataset = {
            type: 'bar',
            label: 'Monthly Revenue (₱)',
            data: revData,
            backgroundColor: barColors,
            borderRadius: 5,
            borderWidth: 1,
            borderColor: 'rgba(0,0,0,0.05)',
            yAxisID: 'y'
        };
    } else if (metric === 'rate') {
        primaryDataset = {
            type: 'bar',
            label: 'Purchase Rate (pcs/order)',
            data: rateData,
            backgroundColor: barColors,
            borderRadius: 5,
            borderWidth: 1,
            borderColor: 'rgba(0,0,0,0.05)',
            yAxisID: 'y'
        };
        // Secondary line becomes Orders frequency
        secondaryDataset = {
            type: 'line',
            label: 'Orders Frequency',
            data: ordersData,
            borderColor: '#0284c7',
            backgroundColor: 'rgba(2, 132, 199, 0.15)',
            borderWidth: 2.5,
            tension: 0.3,
            pointBackgroundColor: '#0284c7',
            pointBorderColor: '#ffffff',
            pointBorderWidth: 2,
            pointRadius: 4,
            pointHoverRadius: 6,
            yAxisID: 'y1'
        };
    }
    
    var ctx = canvas.getContext('2d');
    window.chartProductAnnualTrendInstance = new Chart(ctx, {
        data: {
            labels: labels,
            datasets: [primaryDataset, secondaryDataset]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false
            },
            plugins: {
                legend: {
                    position: 'top',
                    labels: { boxWidth: 14, font: { size: 11, weight: '600' } }
                },
                tooltip: {
                    callbacks: {
                        title: function(context) {
                            var idx = context[0].dataIndex;
                            var mName = MONTH_FULL_NAMES[idx] || ('Month ' + (idx + 1));
                            return mName + ' <?php echo $lb_year_int; ?> • ' + profile.name;
                        },
                        afterBody: function(context) {
                            var idx = context[0].dataIndex;
                            var m = idx + 1;
                            var mEntry = (profile.monthly && profile.monthly[m]) ? profile.monthly[m] : null;
                            var ord = mEntry ? mEntry.orders : 0;
                            var un = mEntry ? mEntry.units : 0;
                            var rt = (ord > 0) ? (un / ord).toFixed(2) : '0.00';
                            var rv = mEntry ? mEntry.revenue : 0;
                            var pf = mEntry ? mEntry.profit : 0;
                            return [
                                '─────────────────────',
                                'Orders: ' + ord.toLocaleString() + ' transactions',
                                'Units Sold: ' + un.toLocaleString() + ' pcs',
                                'Purchase Rate: ' + rt + ' pcs / order',
                                'Gross Sales: ₱' + Number(rv).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}),
                                'Gross Profit: ₱' + Number(pf).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})
                            ];
                        }
                    }
                }
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: { font: { weight: '600' } }
                },
                y: {
                    type: 'linear',
                    position: 'left',
                    beginAtZero: true,
                    ticks: {
                        callback: function(val) {
                            if (metric === 'revenue') return '₱' + val.toLocaleString();
                            if (metric === 'rate') return val.toFixed(1) + ' pcs/ord';
                            return val.toLocaleString() + (metric === 'orders' ? ' ord' : ' pcs');
                        }
                    },
                    title: {
                        display: true,
                        text: (metric === 'orders' ? 'Orders Count' : (metric === 'units' ? 'Units Sold' : (metric === 'revenue' ? 'Revenue (₱)' : 'Purchase Rate'))),
                        font: { size: 11, weight: 'bold' }
                    }
                },
                y1: {
                    type: 'linear',
                    position: 'right',
                    beginAtZero: true,
                    grid: { drawOnChartArea: false },
                    ticks: {
                        callback: function(val) {
                            if (metric === 'rate') return val.toLocaleString() + ' ord';
                            return val.toFixed(1) + ' pcs/ord';
                        }
                    },
                    title: {
                        display: true,
                        text: (metric === 'rate' ? 'Orders Frequency' : 'Purchase Rate (Velocity)'),
                        font: { size: 11, weight: 'bold' }
                    }
                }
            }
        }
    });
}

function switchTrendMetric(metric) {
    window.currentTrendMetric = metric;
    
    var btnOrders = document.getElementById('btnTrendOrders');
    var btnUnits = document.getElementById('btnTrendUnits');
    var btnRate = document.getElementById('btnTrendRate');
    var btnRev = document.getElementById('btnTrendRevenue');
    var badge = document.getElementById('chartTrendMetricBadge');
    
    if (btnOrders) btnOrders.className = 'btn ' + (metric === 'orders' ? 'btn-primary active' : 'btn-default');
    if (btnUnits) btnUnits.className = 'btn ' + (metric === 'units' ? 'btn-primary active' : 'btn-default');
    if (btnRate) btnRate.className = 'btn ' + (metric === 'rate' ? 'btn-primary active' : 'btn-default');
    if (btnRev) btnRev.className = 'btn ' + (metric === 'revenue' ? 'btn-primary active' : 'btn-default');
    
    if (badge) {
        if (metric === 'orders') badge.innerText = 'Orders (Bars) + Rate (Line)';
        else if (metric === 'units') badge.innerText = 'Units Sold (Bars) + Rate (Line)';
        else if (metric === 'rate') badge.innerText = 'Purchase Rate (Bars) + Orders (Line)';
        else if (metric === 'revenue') badge.innerText = 'Revenue ₱ (Bars) + Rate (Line)';
    }
    
    loadProductAnnualTrend(window.currentTrendProductName);
}

function renderMonthlyLeaderboard() {
    var monthSelect = document.getElementById('lbMonthSelect');
    var yearInput = document.getElementById('lbYearInput');
    var topNSelect = document.getElementById('lbTopNSelect');

    var monthVal = monthSelect ? monthSelect.value : '10';
    var yearVal = yearInput ? yearInput.value : '2026';
    var topNVal = topNSelect ? topNSelect.value : '50';
    var metric = window.currentLbMetric || 'orders';

    var monthData = (window.monthlyLeaderboardData && window.monthlyLeaderboardData[monthVal]) 
                    ? (window.monthlyLeaderboardData[monthVal][metric] || []) 
                    : [];

    // Slice according to Top N
    var displayItems = monthData;
    if (topNVal !== 'all') {
        var limit = parseInt(topNVal, 10) || 50;
        displayItems = monthData.slice(0, limit);
    }

    // Update Subtitle
    var monthNames = {
        '1': 'January', '2': 'February', '3': 'March', '4': 'April',
        '5': 'May', '6': 'June', '7': 'July', '8': 'August',
        '9': 'September', '10': 'October', '11': 'November', '12': 'December',
        'all': 'Full Year'
    };
    var metricLabels = {
        'orders': 'Number of Orders',
        'units': 'Units Sold',
        'revenue': 'Gross Revenue (₱)',
        'profit': 'Gross Profit (₱)'
    };
    var subEl = document.getElementById('monthlyLeaderboardSubtitle');
    if (subEl) {
        var mText = monthNames[monthVal] || ('Month ' + monthVal);
        var limitText = (topNVal === 'all') ? 'All Products' : ('Displaying Top ' + topNVal + ' Items');
        subEl.innerHTML = 'Ranked by <strong>' + (metricLabels[metric] || metric) + '</strong> for ' + mText + ' ' + yearVal + ' (' + limitText + ' &bull; ' + displayItems.length + ' active items)';
    }

    // Render Table Body
    var tbody = document.getElementById('lbTableBody');
    if (tbody) {
        tbody.innerHTML = '';
        if (displayItems.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted" style="padding: 25px;"><i class="fa fa-info-circle"></i> No sales recorded for ' + (monthNames[monthVal] || 'this month') + ' ' + yearVal + '.</td></tr>';
        } else {
            var html = '';
            for (var i = 0; i < displayItems.length; i++) {
                var it = displayItems[i];
                var r = it.rank;
                var rankBadge = '<span style="font-weight: 700; color: #0284c7;">#' + r + '</span>';
                if (r === 1) {
                    rankBadge = '<span class="badge" style="background: #fef3c7; color: #b45309; border: 1px solid #fde68a; font-weight: 800;"><i class="fa fa-trophy text-yellow"></i> #1</span>';
                } else if (r === 2) {
                    rankBadge = '<span class="badge" style="background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; font-weight: 800;">#2</span>';
                } else if (r === 3) {
                    rankBadge = '<span class="badge" style="background: #ffedd5; color: #c2410c; border: 1px solid #fed7aa; font-weight: 800;">#3</span>';
                }

                var momBadge = it.movement_badge || '<span class="badge" style="background:#94a3b8; font-size:11px;">―</span>';
                if (it.movement_type === 'up') {
                    momBadge = '<span class="badge" style="background:#10b981; font-size:11px;" title="Jumped up ' + it.movement_diff + ' positions from last month"><i class="fa fa-arrow-up"></i> +' + it.movement_diff + '</span>';
                } else if (it.movement_type === 'down') {
                    momBadge = '<span class="badge" style="background:#ef4444; font-size:11px;" title="Dropped ' + it.movement_diff + ' positions from last month"><i class="fa fa-arrow-down"></i> -' + it.movement_diff + '</span>';
                } else if (it.movement_type === 'same') {
                    momBadge = '<span class="badge" style="background:#94a3b8; font-size:11px;" title="Maintained same rank from last month">― SAME</span>';
                } else if (it.movement_type === 'new') {
                    momBadge = '<span class="badge" style="background:#8b5cf6; font-size:11px;" title="New selling product this month"><i class="fa fa-star"></i> NEW</span>';
                }

                html += '<tr class="lb-row" data-product="' + escapeHtml(it.name) + '" title="Click to view 1-Year Performance &amp; Purchase Rate Trend">' +
                    '<td class="text-center" style="vertical-align: middle;">' + rankBadge + '</td>' +
                    '<td>' +
                        '<strong style="color: #0f172a;">' + escapeHtml(it.name) + '</strong><br>' +
                        '<span class="text-muted" style="font-size: 11px;"><i class="fa fa-tag text-muted"></i> ' + escapeHtml(it.category || 'General') + '</span>' +
                    '</td>' +
                    '<td class="text-center" style="vertical-align: middle;">' + momBadge + '</td>' +
                    '<td class="text-right font-weight-bold" style="vertical-align: middle; color: #0284c7;">' + it.orders.toLocaleString() + '</td>' +
                    '<td class="text-right font-weight-bold" style="vertical-align: middle;">' + it.units.toLocaleString() + '</td>' +
                    '<td class="text-right" style="vertical-align: middle; font-weight: 600;">₱' + Number(it.revenue).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) + '</td>' +
                    '<td class="text-right text-success font-weight-bold" style="vertical-align: middle; color: #10b981;">+₱' + Number(it.profit).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) + '</td>' +
                '</tr>';
            }
            tbody.innerHTML = html;
        }
    }

    // Render Chart
    var chartCanvas = document.getElementById('chartLeaderboard');
    var wrapperEl = document.getElementById('chartLeaderboardWrapper');
    if (chartCanvas && wrapperEl) {
        if (window.chartLeaderboardInstance) {
            window.chartLeaderboardInstance.destroy();
        }

        if (displayItems.length === 0) {
            wrapperEl.style.height = '360px';
            return;
        }

        // Dynamic height based on number of items (smooth scrolling for top 50)
        var dynamicHeight = Math.max(380, displayItems.length * 24 + 60);
        wrapperEl.style.height = dynamicHeight + 'px';

        // Prepare chart labels and values (descending order: #1 at top with longest bar)
        var chartItems = displayItems.slice();
        var labels = chartItems.map(function(item) {
            var n = item.name;
            var rankPrefix = '#' + item.rank + ' ';
            return rankPrefix + (n.length > 25 ? n.substring(0, 23) + '...' : n);
        });
        var values = chartItems.map(function(item) {
            return item[metric] || 0;
        });
        var bgColors = chartItems.map(function(item) {
            if (item.rank === 1) return 'rgba(245, 158, 11, 0.95)'; // Gold
            if (item.rank === 2) return 'rgba(148, 163, 184, 0.95)'; // Silver
            if (item.rank === 3) return 'rgba(217, 119, 6, 0.95)';  // Bronze
            return 'rgba(2, 132, 199, 0.85)';                     // Blue
        });

        var metricConfig = {
            'orders': { label: 'Order Count', color: '#0284c7', prefix: '', suffix: ' orders' },
            'units': { label: 'Units Sold', color: '#10b981', prefix: '', suffix: ' pcs' },
            'revenue': { label: 'Gross Revenue', color: '#0284c7', prefix: '₱', suffix: '' },
            'profit': { label: 'Realized Gross Profit', color: '#10b981', prefix: '₱', suffix: '' }
        };
        var activeCfg = metricConfig[metric] || metricConfig['orders'];

        var ctx = chartCanvas.getContext('2d');
        window.chartLeaderboardInstance = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: activeCfg.label,
                    data: values,
                    backgroundColor: bgColors,
                    borderRadius: 4,
                    borderWidth: 1,
                    borderColor: 'rgba(0,0,0,0.05)'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: 'y',
                onClick: function(evt, activeEls) {
                    if (activeEls && activeEls.length > 0) {
                        var idx = activeEls[0].index;
                        var it = chartItems[idx];
                        if (it && it.name) {
                            selectProductFromLeaderboard(it.name, true);
                        }
                    }
                },
                onHover: function(evt, activeEls) {
                    if (evt.native && evt.native.target) {
                        evt.native.target.style.cursor = (activeEls && activeEls.length > 0) ? 'pointer' : 'default';
                    }
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            title: function(context) {
                                var idx = context[0].dataIndex;
                                var it = chartItems[idx];
                                return '#' + it.rank + ' ' + it.name;
                            },
                            label: function(context) {
                                var idx = context.dataIndex;
                                var it = chartItems[idx];
                                var val = context.parsed.x;
                                var valStr = activeCfg.prefix + Number(val).toLocaleString(undefined, {minimumFractionDigits: (metric === 'revenue' || metric === 'profit' ? 2 : 0)}) + activeCfg.suffix;
                                return activeCfg.label + ': ' + valStr;
                            },
                            afterBody: function(context) {
                                var idx = context[0].dataIndex;
                                var it = chartItems[idx];
                                return [
                                    'Category: ' + (it.category || 'General'),
                                    'Distinct Orders: ' + it.orders.toLocaleString(),
                                    'Units Sold: ' + it.units.toLocaleString() + ' pcs',
                                    'Gross Sales: ₱' + Number(it.revenue).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}),
                                    'Gross Profit: ₱' + Number(it.profit).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' (' + Number(it.margin).toFixed(1) + '% margin)',
                                    'MoM Movement: ' + (it.movement_label || '―'),
                                    '💡 Click bar to view 1-Year Trend below'
                                ];
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(val) {
                                return activeCfg.prefix + val.toLocaleString() + (activeCfg.suffix ? ' ' + activeCfg.suffix.trim() : '');
                            }
                        }
                    },
                    y: {
                        ticks: {
                            font: { size: 11, weight: '600' }
                        },
                        grid: { display: false }
                    }
                }
            }
        });
    }

    // Sync Drilldown dropdown & default product
    syncDrilldownDropdown(displayItems);
    if (!window.currentTrendProductName && displayItems.length > 0) {
        loadProductAnnualTrend(displayItems[0].name);
    }
}

function switchLbMetric(metric) {
    window.currentLbMetric = metric;
    var btnOrders = document.getElementById('btnLbOrders');
    var btnUnits = document.getElementById('btnLbUnits');
    var btnRevenue = document.getElementById('btnLbRevenue');
    var btnProfit = document.getElementById('btnLbProfit');

    if (btnOrders) btnOrders.className = 'btn ' + (metric === 'orders' ? 'btn-primary active' : 'btn-default');
    if (btnUnits) btnUnits.className = 'btn ' + (metric === 'units' ? 'btn-primary active' : 'btn-default');
    if (btnRevenue) btnRevenue.className = 'btn ' + (metric === 'revenue' ? 'btn-primary active' : 'btn-default');
    if (btnProfit) btnProfit.className = 'btn ' + (metric === 'profit' ? 'btn-primary active' : 'btn-default');

    var thOrders = document.getElementById('thLbOrders');
    var thUnits = document.getElementById('thLbUnits');
    var thRevenue = document.getElementById('thLbRevenue');
    var thProfit = document.getElementById('thLbProfit');

    if (thOrders) thOrders.innerHTML = 'Orders' + (metric === 'orders' ? ' <i class="fa fa-sort-amount-desc text-primary"></i>' : '');
    if (thUnits) thUnits.innerHTML = 'Units' + (metric === 'units' ? ' <i class="fa fa-sort-amount-desc text-primary"></i>' : '');
    if (thRevenue) thRevenue.innerHTML = 'Revenue' + (metric === 'revenue' ? ' <i class="fa fa-sort-amount-desc text-primary"></i>' : '');
    if (thProfit) thProfit.innerHTML = 'Profit' + (metric === 'profit' ? ' <i class="fa fa-sort-amount-desc text-primary"></i>' : '');

    renderMonthlyLeaderboard();
}

function updateMonthlyLeaderboard() {
    renderMonthlyLeaderboard();
}

function filterLbTable(query) {
    var q = (query || '').toLowerCase().trim();
    var rows = document.querySelectorAll('#tableLeaderboard tbody tr');
    for (var i = 0; i < rows.length; i++) {
        var text = rows[i].innerText.toLowerCase();
        if (q === '' || text.indexOf(q) !== -1) {
            rows[i].style.display = '';
        } else {
            rows[i].style.display = 'none';
        }
    }
}

// =============================================================
// Top Profit & Order Velocity Horizon Data & Controller
// =============================================================
window.horizonData = <?php echo $hz_payload_json; ?>;
window.currentHzGranularity = 'daily';
window.currentHzMetric = 'profit';
window.chartHzInstance = null;

function initHzControls() {
    if (!window.horizonData || !window.horizonData.meta) return;
    var meta = window.horizonData.meta;
    
    // Set default daily date to latest date with sales (or today)
    var dateInput = document.getElementById('hzDateInput');
    if (dateInput) {
        dateInput.value = meta.latest_date || meta.today_date;
        if (meta.available_dates && meta.available_dates.length > 0) {
            dateInput.min = meta.available_dates[0];
            dateInput.max = meta.today_date;
        }
    }
    
    // Populate Weeks Select
    var weekSel = document.getElementById('hzWeekSelect');
    if (weekSel && meta.available_weeks) {
        weekSel.innerHTML = '';
        var weeksDesc = meta.available_weeks.slice().reverse();
        for (var i = 0; i < weeksDesc.length; i++) {
            var wKey = weeksDesc[i];
            var opt = document.createElement('option');
            opt.value = wKey;
            opt.textContent = 'Week ' + wKey.replace('-', ' (ISO ') + ')';
            weekSel.appendChild(opt);
        }
    }
    
    // Populate Months/Years Select
    var mYearSel = document.getElementById('hzMonthYearSelect');
    var ySel = document.getElementById('hzYearSelect');
    var years = meta.available_years && meta.available_years.length > 0 ? meta.available_years : [new Date().getFullYear().toString()];
    var yearsDesc = years.slice().sort().reverse();
    
    if (mYearSel) {
        mYearSel.innerHTML = '';
        for (var i = 0; i < yearsDesc.length; i++) {
            var opt = document.createElement('option');
            opt.value = yearsDesc[i];
            opt.textContent = yearsDesc[i];
            mYearSel.appendChild(opt);
        }
    }
    
    if (ySel) {
        ySel.innerHTML = '';
        for (var i = 0; i < yearsDesc.length; i++) {
            var opt = document.createElement('option');
            opt.value = yearsDesc[i];
            opt.textContent = yearsDesc[i];
            ySel.appendChild(opt);
        }
    }
}

function switchHzGranularity(granularity) {
    window.currentHzGranularity = granularity;
    
    var btns = {
        'daily': document.getElementById('btnHzDaily'),
        'weekly': document.getElementById('btnHzWeekly'),
        'monthly': document.getElementById('btnHzMonthly'),
        'yearly': document.getElementById('btnHzYearly'),
        'yearly_trend': document.getElementById('btnHzYearlyTrend')
    };
    
    for (var k in btns) {
        if (btns[k]) {
            btns[k].className = 'btn ' + (k === granularity ? 'btn-success active' : 'btn-default');
        }
    }
    
    var groups = {
        'daily': document.getElementById('hzDailyGroup'),
        'weekly': document.getElementById('hzWeeklyGroup'),
        'monthly': document.getElementById('hzMonthlyGroup'),
        'yearly': document.getElementById('hzYearlyGroup'),
        'yearly_trend': document.getElementById('hzYearlyTrendGroup')
    };
    
    for (var g in groups) {
        if (groups[g]) {
            groups[g].style.display = (g === granularity) ? 'flex' : 'none';
        }
    }
    
    renderHzReport();
}

function switchHzMetric(metric) {
    window.currentHzMetric = metric;
    
    var btns = {
        'profit': document.getElementById('btnHzMetricProfit'),
        'orders': document.getElementById('btnHzMetricOrders'),
        'units': document.getElementById('btnHzMetricUnits'),
        'revenue': document.getElementById('btnHzMetricRevenue')
    };
    
    for (var k in btns) {
        if (btns[k]) {
            btns[k].className = 'btn ' + (k === metric ? 'btn-success active' : 'btn-default');
        }
    }
    
    renderHzReport();
}

function navHzDate(offset) {
    var dateInput = document.getElementById('hzDateInput');
    if (!dateInput || !dateInput.value) return;
    
    var parts = dateInput.value.split('-');
    if (parts.length === 3) {
        var d = new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10));
        d.setDate(d.getDate() + offset);
        
        var y = d.getFullYear();
        var m = String(d.getMonth() + 1).padStart(2, '0');
        var day = String(d.getDate()).padStart(2, '0');
        dateInput.value = y + '-' + m + '-' + day;
        renderHzReport();
    }
}

function setHzDateToday() {
    var dateInput = document.getElementById('hzDateInput');
    if (!dateInput) return;
    var d = new Date();
    var y = d.getFullYear();
    var m = String(d.getMonth() + 1).padStart(2, '0');
    var day = String(d.getDate()).padStart(2, '0');
    dateInput.value = y + '-' + m + '-' + day;
    renderHzReport();
}

function getActiveHzItems() {
    if (!window.horizonData) return { items: [], label: 'No Data' };
    
    var gran = window.currentHzGranularity || 'daily';
    var items = [];
    var label = '';
    
    if (gran === 'daily') {
        var dateVal = document.getElementById('hzDateInput') ? document.getElementById('hzDateInput').value : '';
        if (!dateVal && window.horizonData.meta) dateVal = window.horizonData.meta.latest_date;
        items = (window.horizonData.daily && window.horizonData.daily[dateVal]) ? window.horizonData.daily[dateVal] : [];
        label = dateVal ? ('Date ' + dateVal) : 'Selected Day';
    } else if (gran === 'weekly') {
        var wSel = document.getElementById('hzWeekSelect');
        var wVal = wSel ? wSel.value : '';
        items = (window.horizonData.weekly && window.horizonData.weekly[wVal]) ? window.horizonData.weekly[wVal] : [];
        label = wVal ? ('Week ' + wVal) : 'Selected Week';
    } else if (gran === 'monthly') {
        var mSel = document.getElementById('hzMonthSelect');
        var ySel = document.getElementById('hzMonthYearSelect');
        var mVal = mSel ? mSel.value : '10';
        var yVal = ySel ? ySel.value : '2026';
        var mKey = yVal + '-' + String(mVal).padStart(2, '0');
        items = (window.horizonData.monthly && window.horizonData.monthly[mKey]) ? window.horizonData.monthly[mKey] : [];
        var monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
        label = (monthNames[parseInt(mVal, 10) - 1] || mVal) + ' ' + yVal;
    } else if (gran === 'yearly') {
        var ySel = document.getElementById('hzYearSelect');
        var yVal = ySel ? ySel.value : '2026';
        items = (window.horizonData.yearly && window.horizonData.yearly[yVal]) ? window.horizonData.yearly[yVal] : [];
        label = 'Year ' + yVal;
    } else if (gran === 'yearly_trend') {
        if (window.horizonData.yearly_trend && window.horizonData.yearly_trend.products) {
            items = window.horizonData.yearly_trend.products;
            label = 'Multi-Year Historical Trend (' + (window.horizonData.yearly_trend.years.join(', ')) + ')';
        }
    }
    
    return { items: items ? items.slice() : [], label: label };
}

function renderHzReport() {
    var activeData = getActiveHzItems();
    var rawItems = activeData.items;
    var periodLabel = activeData.label;
    var metric = window.currentHzMetric || 'profit';
    var topNSel = document.getElementById('hzTopNSelect');
    var topNVal = topNSel ? topNSel.value : '50';
    
    // Sort descending by selected metric
    rawItems.sort(function(a, b) {
        var valA = a[metric] || 0;
        var valB = b[metric] || 0;
        if (valB === valA) {
            return (b.profit || 0) - (a.profit || 0);
        }
        return valB - valA;
    });
    
    var displayItems = rawItems;
    if (topNVal !== 'all') {
        var lim = parseInt(topNVal, 10) || 50;
        displayItems = rawItems.slice(0, lim);
    }
    
    // Update Subtitles & Headers
    var subLabel = document.getElementById('hzActivePeriodLabel');
    if (subLabel) subLabel.innerText = periodLabel;
    
    var metricNames = {
        'profit': 'Realized Profit (₱)',
        'orders': 'Number of Orders',
        'units': 'Units Sold',
        'revenue': 'Gross Revenue (₱)'
    };
    
    var chartTitle = document.getElementById('hzChartTitle');
    var chartBadge = document.getElementById('hzChartBadge');
    if (chartTitle) chartTitle.innerText = 'Top Items Ranked by ' + (metricNames[metric] || metric) + ' (Descending #1 at Top)';
    if (chartBadge) chartBadge.innerText = (metricNames[metric] || metric);
    
    // Calculate KPI Totals
    var totProfit = 0, totOrders = 0, totUnits = 0, totRev = 0;
    var topItem = rawItems.length > 0 ? rawItems[0] : null;
    
    for (var i = 0; i < rawItems.length; i++) {
        totProfit += (rawItems[i].profit || 0);
        totOrders += (rawItems[i].orders || 0);
        totUnits += (rawItems[i].units || 0);
        totRev += (rawItems[i].revenue || 0);
    }
    var avgMargin = totRev > 0 ? ((totProfit / totRev) * 100).toFixed(1) : '0.0';
    
    var kpiProfit = document.getElementById('kpiHzTotalProfit');
    var kpiMargin = document.getElementById('kpiHzProfitMargin');
    var kpiOrders = document.getElementById('kpiHzTotalOrders');
    var kpiUnits = document.getElementById('kpiHzTotalUnits');
    var kpiTopName = document.getElementById('kpiHzTopItemName');
    var kpiTopStats = document.getElementById('kpiHzTopItemStats');
    var kpiActive = document.getElementById('kpiHzActiveItems');
    var kpiGross = document.getElementById('kpiHzGrossSales');
    
    if (kpiProfit) kpiProfit.innerText = '₱' + Number(totProfit).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
    if (kpiMargin) kpiMargin.innerText = avgMargin + '% Realized Margin';
    if (kpiOrders) kpiOrders.innerText = totOrders.toLocaleString();
    if (kpiUnits) kpiUnits.innerText = totUnits.toLocaleString() + ' units / pcs sold';
    if (kpiTopName) kpiTopName.innerText = topItem ? topItem.name : 'None';
    if (kpiTopStats) {
        if (topItem) {
            kpiTopStats.innerText = '+₱' + Number(topItem.profit || 0).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' profit (' + (topItem.orders || 0) + ' orders)';
        } else {
            kpiTopStats.innerText = 'No sales in period';
        }
    }
    if (kpiActive) kpiActive.innerText = rawItems.length.toLocaleString();
    if (kpiGross) kpiGross.innerText = '₱' + Number(totRev).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' Gross Sales';
    
    // Populate Table
    var tbody = document.getElementById('tableProfitOrderBody');
    if (tbody) {
        tbody.innerHTML = '';
        if (displayItems.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted" style="padding: 25px;"><i class="fa fa-info-circle"></i> No sales recorded for ' + periodLabel + '.</td></tr>';
        } else {
            var html = '';
            for (var i = 0; i < displayItems.length; i++) {
                var it = displayItems[i];
                var rank = i + 1;
                var rankBadge = '<span style="font-weight: 700; color: #10b981;">#' + rank + '</span>';
                if (rank === 1) rankBadge = '<span class="badge" style="background:#fef3c7; color:#b45309; border:1px solid #fde68a; font-weight:800;"><i class="fa fa-trophy text-yellow"></i> #1</span>';
                else if (rank === 2) rankBadge = '<span class="badge" style="background:#f1f5f9; color:#475569; border:1px solid #cbd5e1; font-weight:800;">#2</span>';
                else if (rank === 3) rankBadge = '<span class="badge" style="background:#ffedd5; color:#c2410c; border:1px solid #fed7aa; font-weight:800;">#3</span>';
                
                html += '<tr class="hz-row" data-product="' + escapeHtml(it.name) + '" style="cursor: pointer;" onclick="selectProductFromLeaderboard(\'' + escapeHtml(it.name).replace(/'/g, "\\'") + '\', true)" title="Click to view 1-Year Trend Deep Dive above">' +
                    '<td class="text-center" style="vertical-align: middle;">' + rankBadge + '</td>' +
                    '<td>' +
                        '<strong style="color: #0f172a;">' + escapeHtml(it.name) + '</strong><br>' +
                        '<span class="text-muted" style="font-size: 11px;"><i class="fa fa-tag text-muted"></i> ' + escapeHtml(it.category || 'Building Materials') + '</span>' +
                    '</td>' +
                    '<td class="text-right font-weight-bold" style="vertical-align: middle; color: #10b981;">+₱' + Number(it.profit || 0).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) + '</td>' +
                    '<td class="text-right font-weight-bold" style="vertical-align: middle; color: #0284c7;">' + (it.orders || 0).toLocaleString() + '</td>' +
                    '<td class="text-right" style="vertical-align: middle;">' + (it.units || 0).toLocaleString() + '</td>' +
                    '<td class="text-right" style="vertical-align: middle; font-weight: 600; color: #047857;">' + Number(it.margin || 0).toFixed(1) + '%</td>' +
                '</tr>';
            }
            tbody.innerHTML = html;
        }
        
        // Update Table Footers
        var tfProfit = document.getElementById('tfHzProfit');
        var tfOrders = document.getElementById('tfHzOrders');
        var tfUnits = document.getElementById('tfHzUnits');
        var tfMargin = document.getElementById('tfHzMargin');
        
        if (tfProfit) tfProfit.innerText = '+₱' + Number(totProfit).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
        if (tfOrders) tfOrders.innerText = totOrders.toLocaleString();
        if (tfUnits) tfUnits.innerText = totUnits.toLocaleString();
        if (tfMargin) tfMargin.innerText = avgMargin + '%';
    }
    
    // Render Horizontal Bar Chart
    var canvas = document.getElementById('chartProfitOrderLeaderboard');
    var wrapper = document.getElementById('chartHzWrapper');
    if (canvas && wrapper) {
        if (window.chartHzInstance) {
            window.chartHzInstance.destroy();
        }
        
        if (displayItems.length === 0) {
            wrapper.style.height = '320px';
            return;
        }
        
        var dynamicHeight = Math.max(340, displayItems.length * 24 + 60);
        wrapper.style.height = dynamicHeight + 'px';
        
        var chartItems = displayItems.slice();
        var labels = chartItems.map(function(item, idx) {
            var n = item.name;
            var rankPrefix = '#' + (idx + 1) + ' ';
            return rankPrefix + (n.length > 25 ? n.substring(0, 23) + '...' : n);
        });
        
        var values = chartItems.map(function(item) {
            return item[metric] || 0;
        });
        
        var bgColors = chartItems.map(function(item, idx) {
            if (idx === 0) return 'rgba(245, 158, 11, 0.95)'; // Gold
            if (idx === 1) return 'rgba(148, 163, 184, 0.95)'; // Silver
            if (idx === 2) return 'rgba(217, 119, 6, 0.95)';  // Bronze
            if (metric === 'profit') return 'rgba(16, 185, 129, 0.85)'; // Emerald Green
            if (metric === 'orders') return 'rgba(2, 132, 199, 0.85)';  // Blue
            if (metric === 'units') return 'rgba(139, 92, 246, 0.85)';  // Purple
            return 'rgba(14, 165, 233, 0.85)';
        });
        
        var metricConfig = {
            'profit': { label: 'Realized Gross Profit', prefix: '₱', suffix: '', decimals: 2 },
            'orders': { label: 'Distinct Orders Count', prefix: '', suffix: ' orders', decimals: 0 },
            'units': { label: 'Units Sold', prefix: '', suffix: ' pcs', decimals: 0 },
            'revenue': { label: 'Gross Sales Revenue', prefix: '₱', suffix: '', decimals: 2 }
        };
        var activeCfg = metricConfig[metric] || metricConfig['profit'];
        
        var ctx = canvas.getContext('2d');
        window.chartHzInstance = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: activeCfg.label,
                    data: values,
                    backgroundColor: bgColors,
                    borderRadius: 4,
                    borderWidth: 1,
                    borderColor: 'rgba(0,0,0,0.05)'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: 'y',
                onClick: function(evt, activeEls) {
                    if (activeEls && activeEls.length > 0) {
                        var idx = activeEls[0].index;
                        var it = chartItems[idx];
                        if (it && it.name) {
                            selectProductFromLeaderboard(it.name, true);
                        }
                    }
                },
                onHover: function(evt, activeEls) {
                    if (evt.native && evt.native.target) {
                        evt.native.target.style.cursor = (activeEls && activeEls.length > 0) ? 'pointer' : 'default';
                    }
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            title: function(context) {
                                var idx = context[0].dataIndex;
                                var it = chartItems[idx];
                                return '#' + (idx + 1) + ' ' + it.name;
                            },
                            label: function(context) {
                                var idx = context.dataIndex;
                                var it = chartItems[idx];
                                var val = context.parsed.x;
                                var valStr = activeCfg.prefix + Number(val).toLocaleString(undefined, {minimumFractionDigits: activeCfg.decimals, maximumFractionDigits: activeCfg.decimals}) + activeCfg.suffix;
                                return activeCfg.label + ': ' + valStr;
                            },
                            afterBody: function(context) {
                                var idx = context[0].dataIndex;
                                var it = chartItems[idx];
                                return [
                                    'Category: ' + (it.category || 'General'),
                                    'Realized Profit: +₱' + Number(it.profit || 0).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' (' + Number(it.margin || 0).toFixed(1) + '% margin)',
                                    'Distinct Orders: ' + (it.orders || 0).toLocaleString() + ' orders',
                                    'Units Sold: ' + (it.units || 0).toLocaleString() + ' pcs',
                                    'Gross Sales: ₱' + Number(it.revenue || 0).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}),
                                    '💡 Click bar to view 1-Year Trend Deep Dive above'
                                ];
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(val) {
                                return activeCfg.prefix + val.toLocaleString() + (activeCfg.suffix ? ' ' + activeCfg.suffix.trim() : '');
                            }
                        }
                    },
                    y: {
                        ticks: {
                            font: { size: 11, weight: '600' }
                        },
                        grid: { display: false }
                    }
                }
            }
        });
    }
}

function filterHzTable(query) {
    var q = (query || '').toLowerCase().trim();
    var rows = document.querySelectorAll('#tableProfitOrderLeaderboard tbody tr.hz-row');
    for (var i = 0; i < rows.length; i++) {
        var text = rows[i].innerText.toLowerCase();
        if (q === '' || text.indexOf(q) !== -1) {
            rows[i].style.display = '';
        } else {
            rows[i].style.display = 'none';
        }
    }
}

document.addEventListener("DOMContentLoaded", function() {
    // Check initial hash on load (e.g. #products, #profit, #cashier, #orders)
    var initHash = window.location.hash;
    if (initHash && TAB_ID_MAP[initHash]) {
        switchReportTab(TAB_ID_MAP[initHash], false);
    }

    // Initialize Monthly Leaderboard in descending order
    switchLbMetric(window.currentLbMetric || 'orders');

    // Auto-load top #1 item into 1-Year Trend Deep Dive if available
    var topP = null;
    if (window.monthlyLeaderboardData && window.monthlyLeaderboardData['10'] && window.monthlyLeaderboardData['10']['orders'] && window.monthlyLeaderboardData['10']['orders'].length > 0) {
        topP = window.monthlyLeaderboardData['10']['orders'][0].name;
    }
    loadProductAnnualTrend(topP);

    // Initialize Top Profit & Order Velocity Horizon Report
    initHzControls();
    renderHzReport();

    // Initialize Daily Cash Inventory & Cashier End-of-Day Balancing Report
    initCashierControls();
    renderDailyCashierInventory();
});

// =============================================================
// Daily Cash Inventory & Cashier End-of-Day Balancing Controller
// =============================================================
window.dailyCashierData = <?php echo $daily_cashier_inventory_json; ?>;
window.chartDailyCashierInstance = null;
window.currentCashierFilter = 'all';
window.currentRosterMode = 'all'; // 'all' = show full roster, 'active' = on-duty only

function initCashierControls() {
    if (!window.dailyCashierData) return;
    var meta = window.dailyCashierData;
    var dateInput = document.getElementById('cashierDateInput');
    if (dateInput) {
        dateInput.value = meta.latest_date || meta.today_date;
        if (meta.available_dates && meta.available_dates.length > 0) {
            dateInput.min = meta.available_dates[0];
            dateInput.max = meta.today_date;
        }
    }

    // Populate Cashier Filter Dropdown with ALL cashiers/staff
    var cSelect = document.getElementById('cashierSelectFilter');
    if (cSelect && meta.all_cashiers) {
        cSelect.innerHTML = '<option value="all">👤 All Cashiers (Store Roster &amp; Combined)</option>';
        for (var i = 0; i < meta.all_cashiers.length; i++) {
            var c = meta.all_cashiers[i];
            var opt = document.createElement('option');
            opt.value = c.name;
            var roleSuffix = c.role ? ' (' + c.role + ')' : '';
            opt.textContent = '👤 ' + c.name + roleSuffix;
            cSelect.appendChild(opt);
        }
    }
}

function switchSelectedCashier(cashierName) {
    window.currentCashierFilter = cashierName || 'all';
    var cSelect = document.getElementById('cashierSelectFilter');
    if (cSelect && cSelect.value !== window.currentCashierFilter) {
        cSelect.value = window.currentCashierFilter;
    }
    renderDailyCashierInventory();
}

function selectCashierDrilldown(cashierName) {
    switchReportTab('tab-cashier', true);
    switchSelectedCashier(cashierName);
    setTimeout(function() {
        var el = document.getElementById('dailyCashierInventorySection');
        if (el) {
            el.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }, 100);
}

function switchRosterMode(mode) {
    window.currentRosterMode = mode || 'all';
    var btnAll = document.getElementById('btnRosterAll');
    var btnActive = document.getElementById('btnRosterActive');
    if (btnAll) btnAll.className = 'btn ' + (mode === 'all' ? 'btn-primary active' : 'btn-default');
    if (btnActive) btnActive.className = 'btn ' + (mode === 'active' ? 'btn-primary active' : 'btn-default');
    renderDailyCashierInventory();
}

function navCashierDate(offset) {
    var dateInput = document.getElementById('cashierDateInput');
    if (!dateInput || !dateInput.value) return;
    
    var parts = dateInput.value.split('-');
    if (parts.length === 3) {
        var d = new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10));
        d.setDate(d.getDate() + offset);
        
        var y = d.getFullYear();
        var m = String(d.getMonth() + 1).padStart(2, '0');
        var day = String(d.getDate()).padStart(2, '0');
        dateInput.value = y + '-' + m + '-' + day;
        renderDailyCashierInventory();
    }
}

function setCashierDateToday() {
    var dateInput = document.getElementById('cashierDateInput');
    if (!dateInput) return;
    var meta = window.dailyCashierData;
    dateInput.value = meta.latest_date || meta.today_date;
    renderDailyCashierInventory();
}

function renderDailyCashierInventory(customDate) {
    var dateInput = document.getElementById('cashierDateInput');
    var selectedDate = customDate || (dateInput ? dateInput.value : '');
    if (!selectedDate && window.dailyCashierData) {
        selectedDate = window.dailyCashierData.latest_date || window.dailyCashierData.today_date;
        if (dateInput) dateInput.value = selectedDate;
    }

    var cashierFilter = window.currentCashierFilter || 'all';
    var rosterMode = window.currentRosterMode || 'all';

    var dayObj = (window.dailyCashierData && window.dailyCashierData.days && window.dailyCashierData.days[selectedDate]) 
                 ? window.dailyCashierData.days[selectedDate] 
                 : null;

    // Update Active Date Badge
    var badge = document.getElementById('cashierActiveDateBadge');
    if (badge) {
        if (dayObj && dayObj.formatted_date) {
            badge.innerHTML = '<i class="fa fa-calendar-check-o"></i> ' + dayObj.day_name + ', ' + dayObj.formatted_date;
        } else {
            badge.innerHTML = '<i class="fa fa-calendar-times-o"></i> ' + selectedDate + ' (No Sales)';
        }
    }

    // Determine working list of cashiers for this day
    var masterRoster = (dayObj && dayObj.roster) ? dayObj.roster : [];
    if (masterRoster.length === 0 && window.dailyCashierData && window.dailyCashierData.all_cashiers) {
        // Fallback for dates with no sales
        masterRoster = window.dailyCashierData.all_cashiers.map(function(c) {
            return {
                cashier_name: c.name,
                status: 'Off Duty',
                role: c.role,
                employee_id: c.employee_id,
                orders: 0,
                total_sales: 0,
                cash_sales: 0,
                digital_sales: 0,
                terms_po_sales: 0,
                refunds: 0,
                net_cash_drawer: 0,
                first_sale: '―',
                last_sale: '―',
                methods: { cash: 0, gcash: 0, maya: 0, bank: 0, terms: 0, po: 0, card: 0, other: 0 }
            };
        });
    }

    // Filter by single cashier if selected
    var displayCashiers = masterRoster;
    if (cashierFilter !== 'all') {
        displayCashiers = masterRoster.filter(function(c) {
            return c.cashier_name === cashierFilter;
        });
    } else if (rosterMode === 'active') {
        displayCashiers = masterRoster.filter(function(c) {
            return c.status === 'On Duty' || c.total_sales > 0 || c.orders > 0;
        });
    }

    // Compute active day totals or filtered cashier totals
    var totals = {
        total_sales: 0,
        cash_sales: 0,
        digital_sales: 0,
        terms_po_sales: 0,
        refunds: 0,
        net_cash_drawer: 0,
        orders_count: 0,
        cashiers_count: 0
    };

    if (cashierFilter === 'all') {
        if (dayObj) {
            totals = Object.assign({}, dayObj.totals);
        }
    } else {
        for (var i = 0; i < displayCashiers.length; i++) {
            var dc = displayCashiers[i];
            totals.total_sales += dc.total_sales;
            totals.cash_sales += dc.cash_sales;
            totals.digital_sales += dc.digital_sales;
            totals.terms_po_sales += dc.terms_po_sales;
            totals.net_cash_drawer += dc.net_cash_drawer;
            totals.orders_count += dc.orders;
            if (dc.orders > 0) totals.cashiers_count += 1;
        }
    }

    // Update Topline KPIs
    var lblTotal = document.getElementById('lblKpiDailyTotal');
    var kpiTotal = document.getElementById('kpiDailyTotalSales');
    var kpiOrders = document.getElementById('kpiDailyTotalOrders');
    var kpiCash = document.getElementById('kpiDailyCashSales');
    var kpiCashShare = document.getElementById('kpiDailyCashShare');
    var kpiDigital = document.getElementById('kpiDailyDigitalSales');
    var kpiDigitalBreakdown = document.getElementById('kpiDailyDigitalBreakdown');
    var kpiTerms = document.getElementById('kpiDailyTermsSales');

    if (lblTotal) {
        lblTotal.innerText = cashierFilter === 'all' ? 'Total Daily Collections' : (cashierFilter + ' Collections');
    }
    if (kpiTotal) kpiTotal.innerHTML = '&#8369;' + Number(totals.total_sales).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
    if (kpiOrders) {
        if (cashierFilter === 'all') {
            kpiOrders.innerText = totals.orders_count.toLocaleString() + ' completed orders • ' + totals.cashiers_count + ' active cashier' + (totals.cashiers_count === 1 ? '' : 's');
        } else {
            kpiOrders.innerText = totals.orders_count.toLocaleString() + ' completed orders (' + (totals.orders_count > 0 ? 'On Duty' : 'Off Duty') + ')';
        }
    }
    
    if (kpiCash) kpiCash.innerHTML = '&#8369;' + Number(totals.cash_sales).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
    if (kpiCashShare) {
        var cPct = totals.total_sales > 0 ? ((totals.cash_sales / totals.total_sales) * 100).toFixed(1) : '0.0';
        kpiCashShare.innerHTML = '<strong>' + cPct + '%</strong> of daily revenue in drawer';
    }

    if (kpiDigital) kpiDigital.innerHTML = '&#8369;' + Number(totals.digital_sales).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
    if (kpiDigitalBreakdown) {
        var dPct = totals.total_sales > 0 ? ((totals.digital_sales / totals.total_sales) * 100).toFixed(1) : '0.0';
        kpiDigitalBreakdown.innerHTML = '<strong>' + dPct + '%</strong> via GCash, Maya, Bank';
    }

    if (kpiTerms) kpiTerms.innerHTML = '&#8369;' + Number(totals.terms_po_sales).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});

    // Update Badge Count for Cards
    var cardsBadge = document.getElementById('cashierCardsCountBadge');
    if (cardsBadge) {
        cardsBadge.innerText = displayCashiers.length + ' Cashier' + (displayCashiers.length === 1 ? '' : 's');
    }

    // Render Cashier Cash Drawer Cards (Shows all cashiers in display list)
    var cardsContainer = document.getElementById('cashierDrawerCardsContainer');
    if (cardsContainer) {
        if (displayCashiers.length === 0) {
            cardsContainer.innerHTML = '<div class="text-center text-muted" style="padding: 40px 10px;">' +
                '<i class="fa fa-info-circle" style="font-size: 28px; color: #94a3b8; margin-bottom: 8px; display: block;"></i>' +
                'No cashier records found for this filter.<br>' +
                '</div>';
        } else {
            var cardsHtml = '';
            for (var i = 0; i < displayCashiers.length; i++) {
                var c = displayCashiers[i];
                var rank = i + 1;
                var isOnDuty = (c.status === 'On Duty' && (c.total_sales > 0 || c.orders > 0));
                
                var statusBadge = isOnDuty 
                    ? '<span class="label label-success" style="background:#10b981; margin-right: 4px;"><i class="fa fa-check-circle"></i> On Duty</span>'
                    : '<span class="label label-default" style="background:#cbd5e1; color:#475569; margin-right: 4px;"><i class="fa fa-moon-o"></i> Off Duty</span>';

                var roleBadge = c.role ? ('<span class="badge" style="background:#64748b; font-size:9.5px; margin-left:4px;">' + escapeHtml(c.role) + '</span>') : '';
                var cashPct = c.total_sales > 0 ? ((c.cash_sales / c.total_sales) * 100).toFixed(1) : 0;
                var shiftDisplay = isOnDuty ? (c.first_sale + ' &rarr; ' + c.last_sale) : 'No Shifts Recorded';

                cardsHtml += '<div style="background: ' + (isOnDuty ? '#f8fafc' : '#ffffff') + '; border: 1px solid ' + (isOnDuty ? '#cbd5e1' : '#e2e8f0') + '; border-radius: 6px; padding: 10px 12px; margin-bottom: 10px; transition: all 0.15s ease;">' +
                    '<div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 6px;">' +
                        '<div>' +
                            statusBadge + '<strong style="color: #1e293b; font-size: 13px;">' + escapeHtml(c.cashier_name) + '</strong>' + roleBadge +
                            '<div style="font-size: 11px; color: #64748b; margin-top: 3px;">' +
                                '<i class="fa fa-clock-o"></i> Shift: ' + shiftDisplay +
                            '</div>' +
                        '</div>' +
                        '<div style="text-align: right;">' +
                            '<span style="font-size: 14px; font-weight: 800; color: ' + (isOnDuty ? '#0284c7' : '#94a3b8') + ';">&#8369;' + Number(c.total_sales).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) + '</span>' +
                            '<div style="font-size: 11px; color: #64748b;">' + c.orders.toLocaleString() + ' orders</div>' +
                        '</div>' +
                    '</div>' +
                    
                    '<div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 4px; padding: 6px 10px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">' +
                        '<div>' +
                            '<span style="font-size: 11px; font-weight: 700; color: #15803d;"><i class="fa fa-money"></i> Cash Drawer (OTC):</span>' +
                            (isOnDuty ? '<span style="font-size: 10px; color: #64748b; margin-left: 4px;">(' + cashPct + '%)</span>' : '') +
                        '</div>' +
                        '<strong style="font-size: 13px; color: ' + (isOnDuty ? '#15803d' : '#94a3b8') + ';">&#8369;' + Number(c.cash_sales).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) + '</strong>' +
                    '</div>' +

                    '<div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 4px;">' +
                        '<div style="display: flex; gap: 4px; font-size: 10px; flex-wrap: wrap;">' +
                            (c.methods.gcash > 0 ? '<span class="label" style="background:#0284c7; color:#fff; padding: 2px 5px;">GCash: &#8369;' + Number(c.methods.gcash).toLocaleString(undefined, {minimumFractionDigits: 2}) + '</span>' : '') +
                            (c.methods.maya > 0 ? '<span class="label" style="background:#059669; color:#fff; padding: 2px 5px;">Maya: &#8369;' + Number(c.methods.maya).toLocaleString(undefined, {minimumFractionDigits: 2}) + '</span>' : '') +
                            (c.methods.bank > 0 ? '<span class="label" style="background:#475569; color:#fff; padding: 2px 5px;">Bank: &#8369;' + Number(c.methods.bank).toLocaleString(undefined, {minimumFractionDigits: 2}) + '</span>' : '') +
                            (c.methods.terms > 0 || c.methods.po > 0 ? '<span class="label" style="background:#7c3aed; color:#fff; padding: 2px 5px;">Terms/PO: &#8369;' + Number((c.methods.terms || 0) + (c.methods.po || 0)).toLocaleString(undefined, {minimumFractionDigits: 2}) + '</span>' : '') +
                        '</div>' +
                        '<button type="button" class="btn btn-xs btn-default no-print" onclick="selectCashierDrilldown(\'' + escapeHtml(c.cashier_name).replace(/'/g, "\\'") + '\')" style="font-size: 10px; padding: 1px 6px;">' +
                            '<i class="fa fa-search"></i> Inspect' +
                        '</button>' +
                    '</div>' +
                '</div>';
            }
            cardsContainer.innerHTML = cardsHtml;
        }
    }

    // Render Detailed Reconciliation Matrix Table
    var tableBody = document.getElementById('tableDailyCashierMatrixBody');
    var tableFoot = document.getElementById('tableDailyCashierMatrixFoot');

    if (tableBody) {
        if (displayCashiers.length === 0) {
            tableBody.innerHTML = '<tr><td colspan="13" class="text-center text-muted" style="padding: 24px;">No cashier records found matching current filter.</td></tr>';
            if (tableFoot) tableFoot.innerHTML = '';
        } else {
            var bodyHtml = '';
            var sumOrders = 0;
            var sumCash = 0;
            var sumGcash = 0;
            var sumMaya = 0;
            var sumBank = 0;
            var sumTerms = 0;
            var sumTotal = 0;
            var sumNet = 0;

            for (var j = 0; j < displayCashiers.length; j++) {
                var c = displayCashiers[j];
                var rk = j + 1;
                var isOnDuty = (c.status === 'On Duty' && (c.total_sales > 0 || c.orders > 0));
                var shiftTime = isOnDuty 
                                ? (c.first_sale && c.last_sale && c.first_sale !== c.last_sale ? (c.first_sale + ' &ndash; ' + c.last_sale) : (c.first_sale || 'N/A'))
                                : '<span class="text-muted">―</span>';

                var statusHtml = isOnDuty 
                    ? '<span class="label label-success" style="background:#10b981;"><i class="fa fa-check-circle"></i> On Duty</span>'
                    : '<span class="label label-default" style="background:#cbd5e1; color:#475569;">Off Duty</span>';

                var roleHtml = c.role ? ('<span class="badge" style="background:#64748b; font-size:10px; margin-left:4px;">' + escapeHtml(c.role) + '</span>') : '';

                var cGcash = c.methods.gcash || 0;
                var cMaya = (c.methods.maya || 0) + (c.methods.card || 0);
                var cBank = c.methods.bank || 0;
                var cTerms = (c.methods.terms || 0) + (c.methods.po || 0);

                sumOrders += c.orders;
                sumCash += c.cash_sales;
                sumGcash += cGcash;
                sumMaya += cMaya;
                sumBank += cBank;
                sumTerms += cTerms;
                sumTotal += c.total_sales;
                sumNet += c.net_cash_drawer;

                var isSelected = (cashierFilter === c.cashier_name);

                bodyHtml += '<tr class="cashier-row ' + (isSelected ? 'info' : '') + '" style="' + (isSelected ? 'background-color:#eff6ff !important; font-weight:600;' : '') + '">' +
                    '<td class="text-center text-muted" style="font-weight: 700;">' + rk + '</td>' +
                    '<td><strong style="color: #1e293b;">' + escapeHtml(c.cashier_name) + '</strong>' + roleHtml + '</td>' +
                    '<td class="text-center">' + statusHtml + '</td>' +
                    '<td class="text-center" style="font-size: 11px; color: #64748b;">' + shiftTime + '</td>' +
                    '<td class="text-center" style="font-weight: 700; color: #0284c7;">' + c.orders.toLocaleString() + '</td>' +
                    '<td class="text-right" style="background: ' + (c.cash_sales > 0 ? '#f0fdf4' : 'transparent') + '; font-weight: 700; color: ' + (c.cash_sales > 0 ? '#15803d' : '#94a3b8') + ';">&#8369;' + Number(c.cash_sales).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) + '</td>' +
                    '<td class="text-right">' + (cGcash > 0 ? ('&#8369;' + Number(cGcash).toLocaleString(undefined, {minimumFractionDigits: 2})) : '<span class="text-muted">-</span>') + '</td>' +
                    '<td class="text-right">' + (cMaya > 0 ? ('&#8369;' + Number(cMaya).toLocaleString(undefined, {minimumFractionDigits: 2})) : '<span class="text-muted">-</span>') + '</td>' +
                    '<td class="text-right">' + (cBank > 0 ? ('&#8369;' + Number(cBank).toLocaleString(undefined, {minimumFractionDigits: 2})) : '<span class="text-muted">-</span>') + '</td>' +
                    '<td class="text-right">' + (cTerms > 0 ? ('&#8369;' + Number(cTerms).toLocaleString(undefined, {minimumFractionDigits: 2})) : '<span class="text-muted">-</span>') + '</td>' +
                    '<td class="text-right" style="font-weight: 800; color: ' + (c.total_sales > 0 ? '#0f172a' : '#94a3b8') + ';">&#8369;' + Number(c.total_sales).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) + '</td>' +
                    '<td class="text-right" style="background: ' + (c.net_cash_drawer > 0 ? '#fefce8' : 'transparent') + '; font-weight: 800; color: ' + (c.net_cash_drawer > 0 ? '#854d0e' : '#94a3b8') + ';">&#8369;' + Number(c.net_cash_drawer).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) + '</td>' +
                    '<td class="text-center no-print">' +
                        '<button type="button" class="btn btn-xs btn-default" onclick="selectCashierDrilldown(\'' + escapeHtml(c.cashier_name).replace(/'/g, "\\'") + '\')" title="Inspect this cashier" style="font-size:10px;">' +
                            '<i class="fa fa-search"></i>' +
                        '</button>' +
                    '</td>' +
                '</tr>';
            }
            tableBody.innerHTML = bodyHtml;

            if (tableFoot) {
                tableFoot.innerHTML = '<tr style="background: #f1f5f9; border-top: 2px solid #cbd5e1;">' +
                    '<td colspan="4" class="text-right" style="font-weight: 800; color: #1e293b; text-transform: uppercase;">' +
                        'Reconciliation Totals (' + displayCashiers.length + ' Cashiers):' +
                    '</td>' +
                    '<td class="text-center" style="font-weight: 800; color: #0284c7;">' + sumOrders.toLocaleString() + '</td>' +
                    '<td class="text-right" style="background: #dcfce7; color: #166534; font-weight: 800;">&#8369;' + Number(sumCash).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) + '</td>' +
                    '<td class="text-right" style="font-weight: 700;">&#8369;' + Number(sumGcash).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) + '</td>' +
                    '<td class="text-right" style="font-weight: 700;">&#8369;' + Number(sumMaya).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) + '</td>' +
                    '<td class="text-right" style="font-weight: 700;">&#8369;' + Number(sumBank).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) + '</td>' +
                    '<td class="text-right" style="font-weight: 700;">&#8369;' + Number(sumTerms).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) + '</td>' +
                    '<td class="text-right" style="font-weight: 800; color: #0f172a; font-size: 13px;">&#8369;' + Number(sumTotal).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) + '</td>' +
                    '<td class="text-right" style="background: #fef08a; color: #854d0e; font-weight: 900; font-size: 13px;">&#8369;' + Number(sumNet).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) + '</td>' +
                    '<td></td>' +
                '</tr>';
            }
        }
    }

    // Render Daily Cashier Chart.js Breakdown
    var chartCanvas = document.getElementById('chartDailyCashierInventory');
    if (chartCanvas) {
        if (window.chartDailyCashierInstance) {
            window.chartDailyCashierInstance.destroy();
            window.chartDailyCashierInstance = null;
        }

        var chartLabels = [];
        var datasetCash = [];
        var datasetDigital = [];
        var datasetTerms = [];

        // In chart, show all active cashiers or filtered cashier
        var chartCashiers = displayCashiers;
        if (chartCashiers.length === 0) {
            chartLabels.push('No Active Cashiers');
            datasetCash.push(0);
            datasetDigital.push(0);
            datasetTerms.push(0);
        } else {
            for (var k = 0; k < chartCashiers.length; k++) {
                var c = chartCashiers[k];
                chartLabels.push(c.cashier_name);
                datasetCash.push(c.cash_sales || 0);
                datasetDigital.push(c.digital_sales || 0);
                datasetTerms.push(c.terms_po_sales || 0);
            }
        }

        window.chartDailyCashierInstance = new Chart(chartCanvas.getContext('2d'), {
            type: 'bar',
            data: {
                labels: chartLabels,
                datasets: [
                    {
                        label: 'Physical Cash (Drawer)',
                        data: datasetCash,
                        backgroundColor: 'rgba(16, 185, 129, 0.85)',
                        borderColor: '#10b981',
                        borderWidth: 1.5,
                        borderRadius: 4
                    },
                    {
                        label: 'Digital (GCash/Maya/Bank)',
                        data: datasetDigital,
                        backgroundColor: 'rgba(59, 130, 246, 0.85)',
                        borderColor: '#3b82f6',
                        borderWidth: 1.5,
                        borderRadius: 4
                    },
                    {
                        label: 'Terms & PO',
                        data: datasetTerms,
                        backgroundColor: 'rgba(168, 85, 247, 0.85)',
                        borderColor: '#a855f7',
                        borderWidth: 1.5,
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
                        stacked: true,
                        beginAtZero: true,
                        ticks: {
                            callback: function(val) { return '₱' + val.toLocaleString(); }
                        }
                    },
                    y: {
                        stacked: true,
                        grid: { display: false },
                        ticks: { font: { size: 11, weight: '600' } }
                    }
                },
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { boxWidth: 12, font: { size: 11 } }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                var val = context.parsed.x || 0;
                                return context.dataset.label + ': ₱' + Number(val).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
                            },
                            afterBody: function(context) {
                                var idx = context[0].dataIndex;
                                var c = chartCashiers[idx];
                                if (c) {
                                    return [
                                        'Status: ' + (c.status || 'Off Duty'),
                                        'Total Revenue: ₱' + Number(c.total_sales).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}),
                                        'Orders Processed: ' + c.orders.toLocaleString() + ' orders',
                                        'Active Shift: ' + (c.first_sale || '―') + ' to ' + (c.last_sale || '―')
                                    ];
                                }
                                return [];
                            }
                        }
                    }
                }
            }
        });
    }

    // Render Granular Transaction Audit Trail
    var txAuditBody = document.getElementById('cashierTxAuditBody');
    var txAuditHeading = document.getElementById('txAuditHeading');
    var txAuditCountBadge = document.getElementById('txAuditCountBadge');
    var txAuditSubtext = document.getElementById('txAuditSubtext');

    if (txAuditBody) {
        var dayTx = (dayObj && dayObj.transactions) ? dayObj.transactions : [];
        if (cashierFilter !== 'all') {
            dayTx = dayTx.filter(function(t) { return t.cashier_name === cashierFilter; });
        }

        if (txAuditHeading) {
            txAuditHeading.innerText = cashierFilter === 'all' 
                ? 'Storewide Itemized Transaction Audit Trail' 
                : (cashierFilter + ' ― Transaction Audit Trail');
        }
        if (txAuditCountBadge) {
            txAuditCountBadge.innerText = dayTx.length + ' Receipt' + (dayTx.length === 1 ? '' : 's');
        }
        if (txAuditSubtext) {
            txAuditSubtext.innerText = 'Showing ' + dayTx.length + ' transaction receipts for ' + selectedDate;
        }

        if (dayTx.length === 0) {
            txAuditBody.innerHTML = '<tr><td colspan="7" class="text-center text-muted" style="padding: 20px;">No transactions recorded for this cashier/date selection.</td></tr>';
        } else {
            var txHtml = '';
            for (var tIdx = 0; tIdx < dayTx.length; tIdx++) {
                var tx = dayTx[tIdx];
                var mColor = tx.method_type === 'cash' ? '#10b981' : (tx.method_type === 'terms' || tx.method_type === 'po' ? '#7c3aed' : '#0284c7');
                
                txHtml += '<tr>' +
                    '<td class="text-center text-muted">' + (tIdx + 1) + '</td>' +
                    '<td><strong style="color:#0284c7;">' + escapeHtml(tx.payment_id) + '</strong></td>' +
                    '<td class="text-center" style="color:#64748b;">' + tx.time + '</td>' +
                    '<td><strong>' + escapeHtml(tx.cashier_name) + '</strong></td>' +
                    '<td>' + escapeHtml(tx.customer_name) + '</td>' +
                    '<td><span class="label" style="background:' + mColor + '; color:#fff;">' + escapeHtml(tx.payment_method) + '</span>' + 
                        (tx.info ? ('<div style="font-size:10px; color:#64748b; margin-top:2px;">' + escapeHtml(tx.info) + '</div>') : '') + 
                    '</td>' +
                    '<td class="text-right" style="font-weight:800; color:#0f172a;">&#8369;' + Number(tx.amount).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) + '</td>' +
                '</tr>';
            }
            txAuditBody.innerHTML = txHtml;
        }
    }
}

function filterCashierMatrixTable(query) {
    var q = (query || '').toLowerCase().trim();
    var rows = document.querySelectorAll('#tableDailyCashierMatrixBody tr.cashier-row');
    for (var i = 0; i < rows.length; i++) {
        var text = rows[i].innerText.toLowerCase();
        if (q === '' || text.indexOf(q) !== -1) {
            rows[i].style.display = '';
        } else {
            rows[i].style.display = 'none';
        }
    }
}

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
