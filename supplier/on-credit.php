<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_lifetime' => 604800,
        'gc_maxlifetime' => 604800,
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax'
    ]);
}
require_once('inc/config.php');
require_once('inc/functions.php');
ensure_supplier_user_schema($pdo);

// Check if the supplier user is logged in
if (!isset($_SESSION['supplier_user'])) {
    if (isset($_GET['action']) || isset($_POST['action'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Unauthorized or session expired. Please log in again.']);
        exit;
    }
    header('location: login.php');
    exit;
}

// Multi-tenant isolation & authentication
$supplier_id = (int)$_SESSION['supplier_user']['supplier_id'];
$user_role_raw = isset($_SESSION['supplier_user']['role']) ? $_SESSION['supplier_user']['role'] : 'USER';
$user_role = normalize_supplier_role($user_role_raw);
$cashier_name = !empty($_SESSION['supplier_user']['full_name']) ? $_SESSION['supplier_user']['full_name'] : (!empty($_SESSION['supplier_user']['username']) ? $_SESSION['supplier_user']['username'] : 'Cashier');

// Schema Migration Helper for Credit Payments Ledger
if (!function_exists('ensure_credit_schema')) {
    function ensure_credit_schema($pdo) {
        static $checked = false;
        if ($checked) return;
        try {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS tbl_credit_payments (
                    id SERIAL PRIMARY KEY,
                    payment_id VARCHAR(100) NOT NULL,
                    supplier_id INT NOT NULL,
                    payment_date TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    amount_paid NUMERIC(15,2) NOT NULL DEFAULT 0.00,
                    payment_method VARCHAR(100) NOT NULL DEFAULT 'Cash',
                    reference_no VARCHAR(255) DEFAULT '',
                    remaining_balance NUMERIC(15,2) NOT NULL DEFAULT 0.00,
                    cashier_name VARCHAR(100) DEFAULT 'Cashier',
                    notes TEXT DEFAULT ''
                );
                CREATE INDEX IF NOT EXISTS idx_credit_payments_pid ON tbl_credit_payments (payment_id);
                CREATE INDEX IF NOT EXISTS idx_credit_payments_supplier ON tbl_credit_payments (supplier_id);
            ");
            $checked = true;
        } catch (Exception $e) {
            // Table created or exists
        }
    }
}
ensure_credit_schema($pdo);

// -------------------------------------------------------------
// AJAX Endpoint: Settle / Record Credit Payment
// -------------------------------------------------------------
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'record_credit_payment') {
    header('Content-Type: application/json');
    $pay_id = trim($_POST['payment_id'] ?? '');
    $pay_amount = floatval($_POST['amount_paid'] ?? 0);
    $pay_method = trim($_POST['payment_method'] ?? 'Cash (OTC)');
    $pay_ref = trim($_POST['payment_reference'] ?? '');
    $pay_notes = trim($_POST['payment_notes'] ?? '');

    if (empty($pay_id)) {
        echo json_encode(['success' => false, 'message' => 'Invalid Payment / Invoice ID.']);
        exit;
    }
    if ($pay_amount <= 0) {
        echo json_encode(['success' => false, 'message' => 'Payment amount must be greater than 0.00.']);
        exit;
    }

    try {
        $stmt_chk = $pdo->prepare("SELECT * FROM tbl_payment WHERE (payment_id = ? OR txnid = ?) AND supplier_id = ?");
        $stmt_chk->execute([$pay_id, $pay_id, $supplier_id]);
        $order_pay = $stmt_chk->fetch(PDO::FETCH_ASSOC);

        if (!$order_pay) {
            echo json_encode(['success' => false, 'message' => 'Credit account / order not found.']);
            exit;
        }

        // Calculate authoritative total invoice amount
        $stmt_items_sum = $pdo->prepare("SELECT COALESCE(SUM(NULLIF(regexp_replace(quantity, '[^0-9.]', '', 'g'), '')::numeric * NULLIF(regexp_replace(unit_price, '[^0-9.-]', '', 'g'), '')::numeric), 0) as items_subtotal FROM tbl_order WHERE payment_id = ? AND supplier_id = ?");
        $stmt_items_sum->execute([$order_pay['payment_id'], $supplier_id]);
        $sum_row = $stmt_items_sum->fetch(PDO::FETCH_ASSOC);
        $items_total = floatval($sum_row['items_subtotal'] ?? 0);

        // Check delivery cost in tx info if present
        $delivery_cost = 0;
        if (preg_match('/Delivery (?:Fee|Cost):s*₱?([0-9.]+)/i', $order_pay['bank_transaction_info'] ?? '', $m_del)) {
            $delivery_cost = floatval($m_del[1]);
        }
        $invoice_grand_total = max(floatval($order_pay['paid_amount']), $items_total + $delivery_cost);
        if (preg_match('/Total:s*₱?([0-9,.]+)/i', $order_pay['bank_transaction_info'] ?? '', $m_tot)) {
            $parsed_tot = floatval(str_replace(',', '', $m_tot[1]));
            if ($parsed_tot > 0) $invoice_grand_total = $parsed_tot;
        }

        $current_paid = floatval($order_pay['paid_amount']);
        $current_balance = max(0, $invoice_grand_total - $current_paid);

        if ($pay_amount > ($current_balance + 0.01)) {
            echo json_encode(['success' => false, 'message' => 'Payment amount (₱' . number_format($pay_amount, 2) . ') exceeds current outstanding balance (₱' . number_format($current_balance, 2) . ').']);
            exit;
        }

        $new_paid_total = $current_paid + $pay_amount;
        $new_balance = max(0, $invoice_grand_total - $new_paid_total);
        $new_status = ($new_balance <= 0.009) ? 'Paid' : 'On Credit';

        $tx_append = "\n[Credit Payment: ₱" . number_format($pay_amount, 2) . " via " . $pay_method . (!empty($pay_ref) ? " (Ref: $pay_ref)" : "") . " by " . $cashier_name . " on " . date('Y-m-d H:i') . " | Remaining: ₱" . number_format($new_balance, 2) . "]";
        $updated_tx_info = ($order_pay['bank_transaction_info'] ?? '') . $tx_append;

        $pdo->beginTransaction();

        $stmt_up_p = $pdo->prepare("UPDATE tbl_payment SET paid_amount = ?, payment_status = ?, bank_transaction_info = ? WHERE id = ? AND supplier_id = ?");
        $stmt_up_p->execute([
            number_format($new_paid_total, 2, '.', ''),
            $new_status,
            $updated_tx_info,
            $order_pay['id'],
            $supplier_id
        ]);

        $stmt_ins_cp = $pdo->prepare("
            INSERT INTO tbl_credit_payments (
                payment_id, supplier_id, payment_date, amount_paid, payment_method,
                reference_no, remaining_balance, cashier_name, notes
            ) VALUES (?, ?, CURRENT_TIMESTAMP, ?, ?, ?, ?, ?, ?)
        ");
        $stmt_ins_cp->execute([
            $order_pay['payment_id'],
            $supplier_id,
            $pay_amount,
            $pay_method,
            $pay_ref,
            $new_balance,
            $cashier_name,
            $pay_notes
        ]);

        $pdo->commit();

        $stmt_sup = $pdo->prepare("SELECT * FROM tbl_supplier WHERE supplier_id = ?");
        $stmt_sup->execute([$supplier_id]);
        $sup_row = $stmt_sup->fetch(PDO::FETCH_ASSOC);

        $credit_ref = $order_pay['credit_ref_no'] ?? '';
        if (empty($credit_ref) && preg_match('/Credit Ref:\s*([A-Z0-9-]+)/i', $order_pay['bank_transaction_info'] ?? '', $m_cr)) {
            $credit_ref = $m_cr[1];
        }
        $due_date_val = $order_pay['due_date'] ?? '';
        if (empty($due_date_val) && preg_match('/Due:\s*([0-9]{4}-[0-9]{2}-[0-9]{2})/i', $order_pay['bank_transaction_info'] ?? '', $m_due)) {
            $due_date_val = $m_due[1];
        }
        $terms_days_val = '15';
        if (preg_match('/([0-9]+)\s*Days/i', ($order_pay['payment_method'] ?? '') . ' ' . ($order_pay['bank_transaction_info'] ?? ''), $m_days)) {
            $terms_days_val = $m_days[1];
        }

        echo json_encode([
            'success' => true,
            'message' => 'Payment of ₱' . number_format($pay_amount, 2) . ' successfully recorded!',
            'payment_id' => $order_pay['payment_id'],
            'amount_paid' => $pay_amount,
            'new_paid_total' => $new_paid_total,
            'new_balance' => $new_balance,
            'is_settled' => ($new_status === 'Paid'),
            'receipt_data' => [
                'receipt_type' => 'SETTLEMENT',
                'is_settlement' => true,
                'payment_id' => $order_pay['payment_id'],
                'credit_reference_no' => $credit_ref,
                'customer_name' => $order_pay['customer_name'] ?: 'Valued Customer',
                'customer_phone' => $order_pay['customer_phone'] ?? '',
                'payment_method' => $pay_method,
                'reference_no' => $pay_ref,
                'invoice_total' => $invoice_grand_total,
                'previous_paid' => $current_paid,
                'amount_paid_now' => $pay_amount,
                'total_paid' => $new_paid_total,
                'remaining_balance' => $new_balance,
                'is_settled' => ($new_status === 'Paid'),
                'due_date' => $due_date_val,
                'payment_term_days' => $terms_days_val,
                'payment_date' => date('Y-m-d H:i:s'),
                'supplier_name' => !empty($sup_row['supplier_name']) ? $sup_row['supplier_name'] : 'SAM & INRI CONSTRUCTION SUPPLY',
                'supplier_phone' => !empty($sup_row['supplier_phone']) ? $sup_row['supplier_phone'] : '09612735733',
                'supplier_address' => !empty($sup_row['supplier_address']) ? $sup_row['supplier_address'] : '',
                'cashier_name' => $cashier_name
            ]
        ]);
        exit;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        exit;
    }
}

// -------------------------------------------------------------
// AJAX Endpoint: Retrieve Credit Payment History Ledger
// -------------------------------------------------------------
if (isset($_GET['action']) && $_GET['action'] === 'get_credit_history') {
    header('Content-Type: application/json');
    $pay_id = trim($_GET['payment_id'] ?? '');
    try {
        $stmt_hist = $pdo->prepare("SELECT * FROM tbl_credit_payments WHERE payment_id = ? AND supplier_id = ? ORDER BY id ASC");
        $stmt_hist->execute([$pay_id, $supplier_id]);
        $rows = $stmt_hist->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'history' => $rows]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// -------------------------------------------------------------
// Page Data Queries & Filtering
// -------------------------------------------------------------
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : 'active';
$search_query = isset($_GET['q']) ? trim($_GET['q']) : '';

// Helper for aging badges
if (!function_exists('get_credit_aging_badge')) {
    function get_credit_aging_badge($due_date_str) {
        if (empty($due_date_str)) {
            return '<span class="badge" style="background: #e2e8f0; color: #475569;"><i class="fa fa-clock-o"></i> No Due Date</span>';
        }
        $due_ts = strtotime($due_date_str . ' 23:59:59');
        $today_ts = strtotime(date('Y-m-d') . ' 23:59:59');
        $diff_days = round(($due_ts - $today_ts) / 86400);

        if ($diff_days < 0) {
            $abs_days = abs($diff_days);
            return '<span class="badge" style="background: #ef4444; color: #fff; font-weight: 800;"><i class="fa fa-exclamation-triangle"></i> ' . $abs_days . ' Day' . ($abs_days > 1 ? 's' : '') . ' Overdue</span>';
        } elseif ($diff_days == 0) {
            return '<span class="badge" style="background: #f59e0b; color: #fff; font-weight: 800;"><i class="fa fa-clock-o"></i> Due Today</span>';
        } elseif ($diff_days <= 7) {
            return '<span class="badge" style="background: #f59e0b; color: #fff; font-weight: 700;"><i class="fa fa-calendar"></i> Due in ' . $diff_days . ' Day' . ($diff_days > 1 ? 's' : '') . '</span>';
        } else {
            return '<span class="badge" style="background: #10b981; color: #fff; font-weight: 700;"><i class="fa fa-calendar-check-o"></i> Due in ' . $diff_days . ' Days</span>';
        }
    }
}

// Helper to extract due date from transaction info or calculate from payment date
if (!function_exists('parse_order_due_date')) {
    function parse_order_due_date($tx_info, $payment_date, $payment_method) {
        if (preg_match('/(?:Due Date|Due):s*([0-9]{4}-[0-9]{2}-[0-9]{2})/i', $tx_info, $m)) {
            return $m[1];
        }
        $days = 15;
        if (preg_match('/([0-9]+)s*Days/i', $payment_method . ' ' . $tx_info, $m_d)) {
            $days = (int)$m_d[1];
        }
        return date('Y-m-d', strtotime($payment_date . " +$days days"));
    }
}

// Helper to parse total invoiced amount
if (!function_exists('parse_invoice_total')) {
    function parse_invoice_total($row, $items_subtotal) {
        if (preg_match('/Total:s*₱?([0-9,.]+)/i', $row['bank_transaction_info'] ?? '', $m)) {
            $v = floatval(str_replace(',', '', $m[1]));
            if ($v > 0) return $v;
        }
        $paid = floatval($row['paid_amount']);
        return max($paid, floatval($items_subtotal));
    }
}

// Load catalog product financial map (Capital Price & Markup)
$products_map = [];
try {
    $stmt_prod_map = $pdo->prepare("SELECT p_id, p_name, p_current_price, p_new_price, p_capital_price, p_markup FROM tbl_product WHERE supplier_id = ?");
    $stmt_prod_map->execute(array($supplier_id));
    while ($prow = $stmt_prod_map->fetch(PDO::FETCH_ASSOC)) {
        $products_map[$prow['p_id']] = $prow;
    }
} catch (Exception $e) {
    try {
        $stmt_prod_map = $pdo->prepare("SELECT p_id, p_name, p_current_price FROM tbl_product WHERE supplier_id = ?");
        $stmt_prod_map->execute(array($supplier_id));
        while ($prow = $stmt_prod_map->fetch(PDO::FETCH_ASSOC)) {
            $products_map[$prow['p_id']] = $prow;
        }
    } catch (Exception $e2) {}
}

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

// Fetch all credit accounts for statistics
$stmt_all_credit = $pdo->prepare("
    SELECT p.*, 
           COALESCE((SELECT SUM(NULLIF(regexp_replace(o.quantity, '[^0-9.]', '', 'g'), '')::numeric * NULLIF(regexp_replace(o.unit_price, '[^0-9.-]', '', 'g'), '')::numeric) FROM tbl_order o WHERE o.payment_id = p.payment_id AND o.supplier_id = p.supplier_id), 0) as items_subtotal
    FROM tbl_payment p 
    WHERE p.supplier_id = ? 
      AND (p.payment_status = 'On Credit' OR p.payment_status = 'Partially Paid' OR p.payment_method LIKE 'Check / Terms%')
      AND p.payment_status NOT IN ('Cancelled', 'Void', 'Declined', 'Failed')
    ORDER BY p.id DESC
");
$stmt_all_credit->execute([$supplier_id]);
$all_credit_records = $stmt_all_credit->fetchAll(PDO::FETCH_ASSOC);

$total_outstanding_receivables = 0;
$total_overdue_amount = 0;
$total_due_this_week_amount = 0;
$active_credit_count = 0;
$overdue_count = 0;
$due_this_week_count = 0;

$filtered_records = [];
$today_str = date('Y-m-d');
$week_ahead_str = date('Y-m-d', strtotime('+7 days'));

foreach ($all_credit_records as $rec) {
    $inv_total = parse_invoice_total($rec, $rec['items_subtotal']);
    $paid_so_far = floatval($rec['paid_amount']);
    $balance = max(0, $inv_total - $paid_so_far);
    $due_date = parse_order_due_date($rec['bank_transaction_info'] ?? '', $rec['payment_date'], $rec['payment_method']);

    // Calculate total item capital cost for this credit order
    $c_capital_total = 0.0;
    try {
        $stmt_c_items = $pdo->prepare("SELECT product_id, unit_price, quantity FROM tbl_order WHERE payment_id = ?");
        $stmt_c_items->execute([$rec['payment_id']]);
        $c_items = $stmt_c_items->fetchAll(PDO::FETCH_ASSOC);
        foreach ($c_items as $ci) {
            $c_fin = calculate_item_financials($ci['product_id'], $ci['unit_price'], $ci['quantity'], $products_map);
            $c_capital_total += $c_fin['total_capital'];
        }
    } catch (Exception $e) {}

    // Capital-First Allocation:
    $capital_recovered = min($paid_so_far, $c_capital_total);
    $profit_realized = max(0.0, $paid_so_far - $c_capital_total);
    $capital_remaining = max(0.0, $c_capital_total - $paid_so_far);
    $profit_remaining = max(0.0, $inv_total - $c_capital_total - $profit_realized);
    
    $rec['computed_total'] = $inv_total;
    $rec['computed_paid'] = $paid_so_far;
    $rec['computed_balance'] = $balance;
    $rec['computed_due_date'] = $due_date;
    $rec['capital_total'] = $c_capital_total;
    $rec['capital_recovered'] = $capital_recovered;
    $rec['profit_realized'] = $profit_realized;
    $rec['capital_remaining'] = $capital_remaining;
    $rec['profit_remaining'] = $profit_remaining;

    $is_overdue = ($balance > 0 && $due_date < $today_str);
    $is_due_this_week = ($balance > 0 && $due_date >= $today_str && $due_date <= $week_ahead_str);
    $is_active = ($balance > 0.009);

    if ($is_active) {
        $total_outstanding_receivables += $balance;
        $active_credit_count++;
        if ($is_overdue) {
            $total_overdue_amount += $balance;
            $overdue_count++;
        }
        if ($is_due_this_week) {
            $total_due_this_week_amount += $balance;
            $due_this_week_count++;
        }
    }

    // Apply Tab & Search Filters
    if ($status_filter === 'active' && !$is_active) continue;
    if ($status_filter === 'overdue' && !$is_overdue) continue;
    if ($status_filter === 'week' && !$is_due_this_week) continue;
    if ($status_filter === 'settled' && $is_active) continue;

    if (!empty($search_query)) {
        $q_lower = strtolower($search_query);
        $cust = strtolower($rec['customer_name'] ?? '');
        $pid = strtolower($rec['payment_id'] ?? '');
        $phone = strtolower($rec['customer_phone'] ?? '');
        $tx = strtolower($rec['bank_transaction_info'] ?? '');
        $card = strtolower($rec['card_number'] ?? '');
        if (strpos($cust, $q_lower) === false && strpos($pid, $q_lower) === false && strpos($phone, $q_lower) === false && strpos($tx, $q_lower) === false && strpos($card, $q_lower) === false) {
            continue;
        }
    }

    $filtered_records[] = $rec;
}
require_once('header.php');
?>

<style>
.credit-kpi-card {
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
.credit-kpi-icon {
    width: 48px;
    height: 48px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
}
.credit-kpi-val {
    font-size: 22px;
    font-weight: 800;
    color: #0f172a;
    line-height: 1.2;
}
.credit-kpi-title {
    font-size: 11.5px;
    font-weight: 700;
    text-transform: uppercase;
    color: #64748b;
    letter-spacing: 0.5px;
}
.credit-panel {
    background: #ffffff;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    margin-bottom: 24px;
    overflow: hidden;
}
.credit-table th {
    background: #f8fafc;
    color: #475569;
    font-weight: 700;
    font-size: 12px;
    text-transform: uppercase;
    border-bottom: 2px solid #e2e8f0 !important;
    padding: 12px 14px !important;
}
.credit-table td {
    padding: 12px 14px !important;
    vertical-align: middle !important;
    border-top: 1px solid #f1f5f9 !important;
    font-size: 13px;
}
.credit-filter-pill {
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 700;
    text-decoration: none !important;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all 0.2s;
    color: #475569;
    background: #f1f5f9;
    border: 1px solid #cbd5e1;
}
.credit-filter-pill:hover, .credit-filter-pill.active {
    background: #d97706;
    color: #ffffff;
    border-color: #b45309;
}
.credit-btn-settle {
    background: #16a34a;
    color: #fff;
    border: 1px solid #15803d;
    font-weight: 700;
    font-size: 12px;
    padding: 5px 12px;
    border-radius: 4px;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.credit-btn-settle:hover {
    background: #15803d;
    color: #fff;
}
</style>

<section class="content-header" style="padding-top: 15px; margin-bottom: 15px;">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
        <div>
            <h1 style="margin: 0; font-size: 24px; font-weight: 800; color: #0f172a; display: flex; align-items: center; gap: 8px;">
                <i class="fa fa-calendar-check-o" style="color: #d97706;"></i> Accounts Receivable &amp; On-Credit Ledger
            </h1>
            <p style="margin: 4px 0 0 0; color: #64748b; font-size: 13px;">
                Manage scheduled term payments, customer credit accounts (15 / 21 / 30 Days), and record balance settlements.
            </p>
        </div>
        <div>
            <a href="pos.php" class="btn btn-primary" style="font-weight: 700; border-radius: 6px; box-shadow: 0 2px 4px rgba(37,99,235,0.2);">
                <i class="fa fa-calculator"></i> Open POS Terminal
            </a>
        </div>
    </div>
</section>

<section class="content">

    <!-- KPI Summary Row -->
    <div class="row">
        <div class="col-md-3 col-sm-6 col-xs-12">
            <div class="credit-kpi-card">
                <div>
                    <div class="credit-kpi-title">Total Receivables Outstanding</div>
                    <div class="credit-kpi-val" style="color: #d97706;">₱<?php echo number_format($total_outstanding_receivables, 2); ?></div>
                    <span style="font-size: 11px; color: #64748b; font-weight: 600;"><?php echo $active_credit_count; ?> active credit account<?php echo $active_credit_count !== 1 ? 's' : ''; ?></span>
                </div>
                <div class="credit-kpi-icon" style="background: #fffbeb; color: #d97706;">
                    <i class="fa fa-hourglass-half"></i>
                </div>
            </div>
        </div>

        <div class="col-md-3 col-sm-6 col-xs-12">
            <div class="credit-kpi-card">
                <div>
                    <div class="credit-kpi-title">Overdue Receivables</div>
                    <div class="credit-kpi-val" style="color: #dc2626;">₱<?php echo number_format($total_overdue_amount, 2); ?></div>
                    <span style="font-size: 11px; color: #dc2626; font-weight: 700;"><?php echo $overdue_count; ?> account<?php echo $overdue_count !== 1 ? 's' : ''; ?> past due terms</span>
                </div>
                <div class="credit-kpi-icon" style="background: #fef2f2; color: #dc2626;">
                    <i class="fa fa-exclamation-circle"></i>
                </div>
            </div>
        </div>

        <div class="col-md-3 col-sm-6 col-xs-12">
            <div class="credit-kpi-card">
                <div>
                    <div class="credit-kpi-title">Due in Next 7 Days</div>
                    <div class="credit-kpi-val" style="color: #2563eb;">₱<?php echo number_format($total_due_this_week_amount, 2); ?></div>
                    <span style="font-size: 11px; color: #2563eb; font-weight: 600;"><?php echo $due_this_week_count; ?> account<?php echo $due_this_week_count !== 1 ? 's' : ''; ?> maturing soon</span>
                </div>
                <div class="credit-kpi-icon" style="background: #eff6ff; color: #2563eb;">
                    <i class="fa fa-calendar"></i>
                </div>
            </div>
        </div>

        <div class="col-md-3 col-sm-6 col-xs-12">
            <div class="credit-kpi-card">
                <div>
                    <div class="credit-kpi-title">Credit Customers</div>
                    <div class="credit-kpi-val" style="color: #059669;"><?php echo count($all_credit_records); ?></div>
                    <span style="font-size: 11px; color: #059669; font-weight: 600;">Total lifetime credit sales</span>
                </div>
                <div class="credit-kpi-icon" style="background: #ecfdf5; color: #059669;">
                    <i class="fa fa-users"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter Bar & Search -->
    <div class="credit-panel" style="padding: 14px 18px;">
        <div class="row" style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
            <div class="col-md-7 col-xs-12" style="display: flex; gap: 8px; flex-wrap: wrap;">
                <a href="on-credit.php?status=active<?php echo $search_query ? '&q=' . urlencode($search_query) : ''; ?>" class="credit-filter-pill <?php echo ($status_filter === 'active') ? 'active' : ''; ?>">
                    <i class="fa fa-clock-o"></i> Active On-Credit (<?php echo $active_credit_count; ?>)
                </a>
                <a href="on-credit.php?status=overdue<?php echo $search_query ? '&q=' . urlencode($search_query) : ''; ?>" class="credit-filter-pill <?php echo ($status_filter === 'overdue') ? 'active' : ''; ?>">
                    <i class="fa fa-exclamation-triangle text-danger"></i> Overdue (<?php echo $overdue_count; ?>)
                </a>
                <a href="on-credit.php?status=week<?php echo $search_query ? '&q=' . urlencode($search_query) : ''; ?>" class="credit-filter-pill <?php echo ($status_filter === 'week') ? 'active' : ''; ?>">
                    <i class="fa fa-calendar"></i> Due This Week (<?php echo $due_this_week_count; ?>)
                </a>
                <a href="on-credit.php?status=settled<?php echo $search_query ? '&q=' . urlencode($search_query) : ''; ?>" class="credit-filter-pill <?php echo ($status_filter === 'settled') ? 'active' : ''; ?>">
                    <i class="fa fa-check-circle text-success"></i> Fully Settled
                </a>
            </div>

            <div class="col-md-5 col-xs-12">
                <form method="GET" action="on-credit.php">
                    <input type="hidden" name="status" value="<?php echo htmlspecialchars($status_filter); ?>">
                    <div class="input-group">
                        <input type="text" name="q" class="form-control input-sm" placeholder="Search customer name, phone, or invoice #..." value="<?php echo htmlspecialchars($search_query); ?>" style="border-radius: 4px 0 0 4px; height: 34px;">
                        <span class="input-group-btn">
                            <button type="submit" class="btn btn-default btn-sm" style="height: 34px; font-weight: 700;">
                                <i class="fa fa-search"></i> Search
                            </button>
                            <?php if (!empty($search_query)): ?>
                                <a href="on-credit.php?status=<?php echo htmlspecialchars($status_filter); ?>" class="btn btn-default btn-sm" style="height: 34px;" title="Clear Search">
                                    <i class="fa fa-times text-danger"></i>
                                </a>
                            <?php endif; ?>
                        </span>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Accounts Receivable Table -->
    <div class="credit-panel">
        <?php if (!empty($filtered_records)): ?>
        <div class="table-responsive">
            <table class="table table-hover credit-table">
                <thead>
                    <tr>
                        <th style="width: 40px;">#</th>
                        <th style="width: 170px;">Invoice / PO #</th>
                        <th style="width: 140px;">Date &amp; Terms</th>
                        <th>Customer Details</th>
                        <th style="width: 120px; text-align: right;">Total Amount</th>
                        <th style="width: 120px; text-align: right;">Paid to Date</th>
                        <th style="width: 140px; text-align: right;">Remaining Balance</th>
                        <th style="width: 150px; text-align: center;">Due Date &amp; Status</th>
                        <th style="width: 180px; text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $row_idx = 0;
                    foreach ($filtered_records as $row): 
                        $row_idx++;
                        $pay_id = $row['payment_id'];
                        $cust_name = $row['customer_name'] ?: 'Walk-in Customer';
                        $cust_phone = $row['customer_phone'] ?? '';
                        $total_amt = $row['computed_total'];
                        $paid_amt = $row['computed_paid'];
                        $bal_amt = $row['computed_balance'];
                        $due_date = $row['computed_due_date'];
                        $is_settled = ($bal_amt <= 0.009);
                    ?>
                    <tr id="creditRow-<?php echo htmlspecialchars($pay_id); ?>">
                        <td style="color: #64748b; font-weight: 700;"><?php echo $row_idx; ?></td>
                        <td>
                            <strong style="font-family: monospace; font-size: 13.5px; color: #1e3a8a; display: block;">
                                <?php echo htmlspecialchars($pay_id); ?>
                            </strong>
                            <?php if (!empty($row['card_number'])): ?>
                                <span class="badge" style="background: #e0f2fe; color: #0369a1; font-family: monospace; font-size: 11px; margin-top: 3px; display: inline-block;" title="Credit Reference / Check Number">
                                    <i class="fa fa-tag"></i> <?php echo htmlspecialchars($row['card_number']); ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div style="font-weight: 700; color: #0f172a; font-size: 12.5px;"><?php echo date('M d, Y', strtotime($row['payment_date'])); ?></div>
                            <span class="badge" style="background: #fef3c7; color: #92400e; font-size: 10.5px; font-weight: 800;">
                                <?php echo htmlspecialchars($row['payment_method']); ?>
                            </span>
                        </td>
                        <td>
                            <strong style="font-size: 13.5px; color: #0f172a; display: block;"><?php echo htmlspecialchars($cust_name); ?></strong>
                            <?php if (!empty($cust_phone)): ?>
                                <div style="font-size: 11.5px; color: #64748b;"><i class="fa fa-phone"></i> <?php echo htmlspecialchars($cust_phone); ?></div>
                            <?php endif; ?>
                            <?php if (!empty($row['customer_address'])): ?>
                                <div style="font-size: 11px; color: #94a3b8; max-width: 200px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?php echo htmlspecialchars($row['customer_address']); ?></div>
                            <?php endif; ?>
                        </td>
                        <td style="text-align: right; font-weight: 700; color: #0f172a;">
                            ₱<?php echo number_format($total_amt, 2); ?>
                        </td>
                        <td style="text-align: right; font-weight: 700; color: #059669;">
                            ₱<?php echo number_format($paid_amt, 2); ?>
                            <?php if ($paid_amt > 0): ?>
                                <div style="font-size: 10.5px; color: #047857; font-weight: 600;" title="Capital Recovered: ₱<?php echo number_format($row['capital_recovered'], 2); ?> | Realized Profit: +₱<?php echo number_format($row['profit_realized'], 2); ?>">
                                    Cap: ₱<?php echo number_format($row['capital_recovered'], 2); ?> | Prof: +₱<?php echo number_format($row['profit_realized'], 2); ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td style="text-align: right;">
                            <?php if ($is_settled): ?>
                                <span class="label label-success" style="font-size: 12px; font-weight: 800; padding: 4px 8px; background: #059669;"><i class="fa fa-check"></i> SETTLED</span>
                            <?php else: ?>
                                <strong style="font-size: 15px; font-weight: 900; color: #dc2626;">
                                    ₱<?php echo number_format($bal_amt, 2); ?>
                                </strong>
                                <div style="font-size: 10.5px; color: #b45309; font-weight: 600;" title="Remaining Capital at risk: ₱<?php echo number_format($row['capital_remaining'], 2); ?> | Remaining Profit: ₱<?php echo number_format($row['profit_remaining'], 2); ?>">
                                    Cap: ₱<?php echo number_format($row['capital_remaining'], 2); ?> | Prof: ₱<?php echo number_format($row['profit_remaining'], 2); ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td style="text-align: center;">
                            <div style="font-size: 12px; font-weight: 700; color: #1e293b; margin-bottom: 2px;">
                                Due: <?php echo date('M d, Y', strtotime($due_date)); ?>
                            </div>
                            <?php echo $is_settled ? '<span class="badge" style="background: #d1fae5; color: #065f46;">Fully Paid</span>' : get_credit_aging_badge($due_date); ?>
                        </td>
                        <td style="text-align: right; white-space: nowrap;">
                            <?php if (!$is_settled): ?>
                                <button type="button" class="credit-btn-settle" onclick="openSettleModal('<?php echo htmlspecialchars($pay_id, ENT_QUOTES); ?>', '<?php echo htmlspecialchars($cust_name, ENT_QUOTES); ?>', <?php echo $total_amt; ?>, <?php echo $paid_amt; ?>, <?php echo $bal_amt; ?>, '<?php echo htmlspecialchars($row['card_number'] ?? '', ENT_QUOTES); ?>')" title="Record full balance settlement or installment payment">
                                    <i class="fa fa-money"></i> Settle
                                </button>
                            <?php endif; ?>
                            <button type="button" class="btn btn-default btn-xs" onclick="viewCreditLedger('<?php echo htmlspecialchars($pay_id, ENT_QUOTES); ?>', '<?php echo htmlspecialchars($cust_name, ENT_QUOTES); ?>', <?php echo $total_amt; ?>, <?php echo $bal_amt; ?>)" style="font-weight: 700; padding: 5px 8px; margin-left: 2px;" title="View payment installment history &amp; ledger">
                                <i class="fa fa-list-alt"></i> History
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div style="text-align: center; padding: 45px 15px; color: #64748b;">
            <i class="fa fa-calendar-check-o" style="font-size: 40px; color: #cbd5e1; margin-bottom: 12px;"></i>
            <h4 style="margin: 0 0 6px 0; font-weight: 800; color: #334155;">No Credit Records Found</h4>
            <p style="margin: 0; font-size: 13px;">No accounts match the selected filter or search query.</p>
        </div>
        <?php endif; ?>
    </div>

</section>

<!-- ============================================================== -->
<!-- MODAL: Record Payment / Settle Balance -->
<!-- ============================================================== -->
<div class="modal fade" id="settlePaymentModal" tabindex="-1" role="dialog" aria-labelledby="settleModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document" style="max-width: 520px;">
        <div class="modal-content" style="border-radius: 10px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.25);">
            <form id="settlePaymentForm" onsubmit="handleSettlePaymentSubmit(event)">
                <input type="hidden" name="action" value="record_credit_payment">
                <input type="hidden" name="payment_id" id="settleModalPaymentId">

                <div class="modal-header" style="background: #0f172a; color: #fff; padding: 14px 18px;">
                    <button type="button" class="close" data-dismiss="modal" style="color: #fff; opacity: 0.9;">&times;</button>
                    <h4 class="modal-title" id="settleModalLabel" style="font-weight: 800; font-size: 16px; margin: 0;">
                        <i class="fa fa-money text-success"></i> Record Credit Payment / Settlement
                    </h4>
                </div>

                <div class="modal-body" style="padding: 18px 20px; background: #fff;">
                    <div style="background: #f8fafc; border: 1.5px solid #e2e8f0; border-radius: 8px; padding: 12px 14px; margin-bottom: 16px;">
                        <div style="display: flex; justify-content: space-between; font-size: 12.5px; margin-bottom: 4px;">
                            <span style="color: #64748b;">Account / Invoice:</span>
                            <strong style="font-family: monospace; color: #1e3a8a;" id="settleModalInvoiceDisplay"></strong>
                        </div>
                        <div id="settleModalRefRow" style="display: flex; justify-content: space-between; font-size: 12.5px; margin-bottom: 4px;">
                            <span style="color: #64748b;"><i class="fa fa-tag text-info"></i> Credit Ref #:</span>
                            <strong style="font-family: monospace; color: #0284c7;" id="settleModalRefDisplay"></strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; font-size: 12.5px; margin-bottom: 4px;">
                            <span style="color: #64748b;">Customer Name:</span>
                            <strong style="color: #0f172a;" id="settleModalCustomerDisplay"></strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; font-size: 12.5px; margin-bottom: 4px;">
                            <span style="color: #64748b;">Total Invoice Value:</span>
                            <strong style="color: #0f172a;" id="settleModalTotalDisplay"></strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; font-size: 14px; font-weight: 800; border-top: 1px dashed #cbd5e1; padding-top: 6px; margin-top: 6px;">
                            <span style="color: #dc2626;">Current Balance Due:</span>
                            <span style="color: #dc2626; font-size: 17px;" id="settleModalBalanceDisplay">₱0.00</span>
                        </div>
                    </div>

                    <!-- Payment Method Dropdown -->
                    <div class="form-group" style="margin-bottom: 12px;">
                        <label style="font-size: 13px; font-weight: 700; color: #1e293b; margin-bottom: 4px;">Payment Method:</label>
                        <select name="payment_method" id="settleModalMethod" class="form-control" style="height: 38px; font-weight: 600;">
                            <option value="Cash (OTC)">💵 Cash (Over the Counter)</option>
                            <option value="GCash / Maya">📱 GCash / Maya E-Wallet</option>
                            <option value="Debit/Credit Card">💳 Debit / Credit Card</option>
                            <option value="Bank Transfer">🏦 Bank Transfer</option>
                            <option value="Check / Terms">📄 Commercial Check</option>
                        </select>
                    </div>

                    <!-- Payment Reference Input -->
                    <div class="form-group" style="margin-bottom: 12px;">
                        <label style="font-size: 13px; font-weight: 700; color: #1e293b; margin-bottom: 4px;">Payment Reference / Check # (Optional):</label>
                        <div class="input-group">
                            <span class="input-group-addon"><i class="fa fa-id-card-o"></i></span>
                            <input type="text" name="payment_reference" id="settleModalRef" class="form-control" placeholder="e.g. Check No., GCash Ref #, Bank Txn ID">
                        </div>
                    </div>

                    <!-- Amount to Pay -->
                    <div class="form-group" style="margin-bottom: 8px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                            <label style="font-size: 13px; font-weight: 700; color: #1e293b; margin: 0;">Payment Amount (₱):</label>
                            <div class="btn-group btn-group-xs">
                                <button type="button" class="btn btn-default" onclick="setSettlePreset('full')" style="font-weight: 700;">Full Balance</button>
                                <button type="button" class="btn btn-default" onclick="setSettlePreset('50')" style="font-weight: 700;">50%</button>
                            </div>
                        </div>
                        <div class="input-group">
                            <span class="input-group-addon" style="font-weight: bold; font-size: 16px;">₱</span>
                            <input type="number" step="any" name="amount_paid" id="settleModalAmount" class="form-control input-lg" style="font-size: 20px; font-weight: 800; color: #059669; height: 44px;" required>
                        </div>
                    </div>

                    <!-- Cashier Notes -->
                    <div class="form-group" style="margin-bottom: 0;">
                        <label style="font-size: 12px; font-weight: 700; color: #64748b; margin-bottom: 4px;">Remarks / Payment Notes (Optional):</label>
                        <input type="text" name="payment_notes" id="settleModalNotes" class="form-control input-sm" placeholder="e.g. Cleared 2nd installment at register">
                    </div>
                </div>

                <div class="modal-footer" style="background: #f8fafc; border-top: 1px solid #e2e8f0; display: flex; justify-content: space-between;">
                    <button type="button" class="btn btn-default" data-dismiss="modal" style="font-weight: 600;">Cancel</button>
                    <button type="submit" id="settleSubmitBtn" class="btn btn-success" style="font-weight: 800; padding: 8px 18px;">
                        <i class="fa fa-check-circle"></i> Confirm Payment &amp; Print Receipt
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ============================================================== -->
<!-- MODAL: Credit Installment History Ledger -->
<!-- ============================================================== -->
<div class="modal fade" id="creditHistoryModal" tabindex="-1" role="dialog" aria-labelledby="historyModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document" style="max-width: 720px;">
        <div class="modal-content" style="border-radius: 10px; overflow: hidden;">
            <div class="modal-header" style="background: #0f172a; color: #fff; padding: 14px 18px;">
                <button type="button" class="close" data-dismiss="modal" style="color: #fff; opacity: 0.9;">&times;</button>
                <h4 class="modal-title" id="historyModalLabel" style="font-weight: 800; font-size: 16px; margin: 0;">
                    <i class="fa fa-history text-primary"></i> Payment Installment Ledger &amp; History
                </h4>
            </div>
            <div class="modal-body" style="padding: 18px 20px; background: #fff;">
                <div id="creditHistoryContent">
                    <div class="text-center text-muted" style="padding: 30px;"><i class="fa fa-spinner fa-spin fa-2x"></i> Loading ledger...</div>
                </div>
            </div>
            <div class="modal-footer" style="background: #f8fafc; border-top: 1px solid #e2e8f0;">
                <button type="button" class="btn btn-default" data-dismiss="modal" style="font-weight: 600;">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================== -->
<!-- MODAL: Settlement Collection Receipt Print Preview -->
<!-- ============================================================== -->
<div class="modal fade" id="settleReceiptPreviewModal" tabindex="-1" role="dialog" aria-labelledby="settleReceiptModalLabel" aria-hidden="true" data-backdrop="static">
    <div class="modal-dialog modal-md" role="document" style="max-width: 580px; margin: 30px auto;">
        <div class="modal-content" style="border-radius: 10px; overflow: hidden; box-shadow: 0 15px 40px rgba(0,0,0,0.35); border: none;">
            <div class="modal-header" style="background: linear-gradient(135deg, #059669 0%, #047857 100%); color: #fff; padding: 14px 20px; display: flex; justify-content: space-between; align-items: center;">
                <h4 class="modal-title" id="settleReceiptModalLabel" style="font-weight: 700; font-size: 16px; margin: 0; display: flex; align-items: center; gap: 8px;">
                    <i class="fa fa-print" style="font-size: 18px;"></i> Payment Collection Receipt Preview
                </h4>
                <button type="button" class="close" onclick="closeSettlementPreviewAndReload()" style="color: #fff; opacity: 0.9; font-size: 24px; text-shadow: none; line-height: 1; padding: 0 4px;">&times;</button>
            </div>
            
            <div class="modal-body" style="background: #f8fafc; padding: 16px 20px; max-height: calc(100vh - 210px); overflow-y: auto;">
                
                <!-- Success Alert Banner -->
                <div id="settleReceiptSuccessBanner" style="background: #ecfdf5; border: 1.5px solid #10b981; border-radius: 8px; padding: 10px 14px; margin-bottom: 12px; display: flex; justify-content: space-between; align-items: center;">
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <i class="fa fa-check-circle" style="font-size: 18px; color: #059669;"></i>
                        <span style="font-weight: 700; color: #065f46; font-size: 13px;" id="settleReceiptSuccessMsg">Payment successfully recorded!</span>
                    </div>
                    <span class="label label-success" id="settleReceiptStatusBadge" style="font-size: 11px; padding: 4px 8px; font-weight: 700;">RECORDED</span>
                </div>

                <!-- Paper Size Controls & Tips -->
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; background: #fff; padding: 8px 12px; border-radius: 6px; border: 1px solid #e2e8f0;">
                    <span style="font-size: 12px; font-weight: 700; color: #475569;">
                        <i class="fa fa-sliders"></i> Paper Format:
                    </span>
                    <select id="settleReceiptModalPaperSize" class="form-control input-sm" style="width: 170px; font-weight: 700; display: inline-block;" onchange="switchSettlementReceiptFormat(this.value)">
                        <option value="58" selected>58mm Thermal (Default)</option>
                        <option value="80">80mm Thermal</option>
                        <option value="pdf">A4 / PDF View</option>
                    </select>
                </div>

                <!-- Scrollable Centered Live Receipt Preview Container -->
                <div id="settleReceiptPreviewArea" style="text-align: center; background: #f1f5f9; padding: 10px 0; border-radius: 6px;">
                    <!-- Rendered Receipt Container -->
                </div>
            </div>

            <div class="modal-footer" style="background: #ffffff; border-top: 1px solid #e2e8f0; padding: 12px 20px; display: flex; justify-content: space-between; align-items: center;">
                <button type="button" class="btn btn-default" onclick="closeSettlementPreviewAndReload()" style="font-weight: 700; height: 38px; padding: 6px 18px; font-size: 13px; border-radius: 6px; border-color: #cbd5e1;">
                    <i class="fa fa-check"></i> Done &amp; Refresh
                </button>
                <div style="display: flex; gap: 8px;">
                    <button type="button" class="btn btn-success" onclick="printSettlementReceipt()" style="font-weight: 800; height: 38px; padding: 6px 22px; font-size: 13px; background-color: #059669; border-color: #047857; border-radius: 6px; box-shadow: 0 2px 6px rgba(5,150,105,0.25);">
                        <i class="fa fa-print"></i> Print Receipt
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let currentSettleMaxBalance = 0;
let activeSettlementReceiptData = null;

function openSettleModal(paymentId, customerName, totalAmt, paidAmt, balanceAmt, referenceNo) {
    currentSettleMaxBalance = balanceAmt;
    document.getElementById('settleModalPaymentId').value = paymentId;
    document.getElementById('settleModalInvoiceDisplay').innerText = paymentId;
    
    const refDisplay = document.getElementById('settleModalRefDisplay');
    const refRow = document.getElementById('settleModalRefRow');
    if (referenceNo) {
        if (refDisplay) refDisplay.innerText = referenceNo;
        if (refRow) refRow.style.display = 'flex';
    } else {
        if (refRow) refRow.style.display = 'none';
    }

    document.getElementById('settleModalCustomerDisplay').innerText = customerName;
    document.getElementById('settleModalTotalDisplay').innerText = '₱' + totalAmt.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    document.getElementById('settleModalBalanceDisplay').innerText = '₱' + balanceAmt.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    document.getElementById('settleModalAmount').value = balanceAmt.toFixed(2);
    document.getElementById('settleModalAmount').max = balanceAmt;
    document.getElementById('settleModalRef').value = '';
    document.getElementById('settleModalNotes').value = '';

    $('#settlePaymentModal').modal('show');
    setTimeout(() => {
        document.getElementById('settleModalAmount').focus();
    }, 400);
}

function setSettlePreset(type) {
    if (type === 'full') {
        document.getElementById('settleModalAmount').value = currentSettleMaxBalance.toFixed(2);
    } else if (type === '50') {
        document.getElementById('settleModalAmount').value = (currentSettleMaxBalance * 0.5).toFixed(2);
    }
}

function handleSettlePaymentSubmit(event) {
    event.preventDefault();
    const btn = document.getElementById('settleSubmitBtn');
    const origText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Recording Payment...';

    const formData = new FormData(document.getElementById('settlePaymentForm'));

    fetch('on-credit.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(res => {
        btn.disabled = false;
        btn.innerHTML = origText;
        if (!res.success) {
            alert(res.message || 'Failed to record payment.');
            return;
        }

        // 1. Hide Settle Form Modal
        $('#settlePaymentModal').modal('hide');

        // 2. Store active receipt data
        activeSettlementReceiptData = res.receipt_data || {
            receipt_type: 'SETTLEMENT',
            is_settlement: true,
            payment_id: res.payment_id,
            amount_paid_now: res.amount_paid,
            total_paid: res.new_paid_total,
            remaining_balance: res.new_balance,
            is_settled: res.is_settled
        };

        // 3. Render preview inside modal
        const format = document.getElementById('settleReceiptModalPaperSize')?.value || '58';
        renderSettlementReceiptPreview(activeSettlementReceiptData, format);

        // 4. Update status badges
        const badge = document.getElementById('settleReceiptStatusBadge');
        if (badge) {
            badge.innerText = activeSettlementReceiptData.is_settled ? 'FULLY SETTLED' : 'PARTIAL ON-CREDIT';
            badge.className = activeSettlementReceiptData.is_settled ? 'label label-success' : 'label label-warning';
        }
        const msg = document.getElementById('settleReceiptSuccessMsg');
        if (msg) {
            msg.innerText = res.message || 'Payment successfully recorded!';
        }

        // 5. Open Receipt Preview Modal
        $('#settleReceiptPreviewModal').modal('show');
    })
    .catch(err => {
        btn.disabled = false;
        btn.innerHTML = origText;
        alert('Network error: ' + err.message);
    });
}

function renderSettlementReceiptPreview(receiptData, format) {
    const container = document.getElementById('settleReceiptPreviewArea');
    if (!container) return;
    const fmt = format || '58';

    if (window.POVoucherRenderer && typeof window.POVoucherRenderer.renderReceipt === 'function') {
        container.innerHTML = window.POVoucherRenderer.renderReceipt(receiptData, fmt);
    } else if (window.POVoucherRenderer && typeof window.POVoucherRenderer.renderThermalSettlementReceipt === 'function') {
        container.innerHTML = window.POVoucherRenderer.renderThermalSettlementReceipt(receiptData, fmt);
    }
}

function switchSettlementReceiptFormat(format) {
    if (activeSettlementReceiptData) {
        renderSettlementReceiptPreview(activeSettlementReceiptData, format);
    }
}

function printSettlementReceipt() {
    if (!activeSettlementReceiptData) return;
    const format = document.getElementById('settleReceiptModalPaperSize')?.value || '58';

    if (window.POVoucherRenderer && typeof window.POVoucherRenderer.printReceiptData === 'function') {
        window.POVoucherRenderer.printReceiptData(activeSettlementReceiptData, format);
    } else {
        window.print();
    }
}

function closeSettlementPreviewAndReload() {
    $('#settleReceiptPreviewModal').modal('hide');
    window.location.reload();
}

function viewCreditLedger(paymentId, customerName, totalAmt, balanceAmt) {
    const container = document.getElementById('creditHistoryContent');
    container.innerHTML = '<div class="text-center text-muted" style="padding: 30px;"><i class="fa fa-spinner fa-spin fa-2x"></i> Loading ledger...</div>';
    $('#creditHistoryModal').modal('show');

    fetch('on-credit.php?action=get_credit_history&payment_id=' + encodeURIComponent(paymentId))
    .then(r => r.json())
    .then(res => {
        if (!res.success || !res.history || res.history.length === 0) {
            container.innerHTML = `
                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px; margin-bottom: 14px;">
                    <strong>${escapeHtml(customerName)}</strong> &bull; <span style="font-family: monospace;">${escapeHtml(paymentId)}</span>
                    <div style="margin-top: 4px; font-size: 13px; color: #64748b;">Original Total: ₱${totalAmt.toFixed(2)} | Outstanding Balance: ₱${balanceAmt.toFixed(2)}</div>
                </div>
                <div class="alert alert-info" style="margin: 0;">
                    <i class="fa fa-info-circle"></i> No separate installment ledger entries recorded yet. Initial payment recorded upon order creation.
                </div>
            `;
            return;
        }

        let html = `
            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px; margin-bottom: 14px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
                <div>
                    <strong style="font-size: 15px; color: #0f172a;">${escapeHtml(customerName)}</strong>
                    <div style="font-family: monospace; font-size: 13px; color: #1e3a8a;">${escapeHtml(paymentId)}</div>
                </div>
                <div style="text-align: right;">
                    <div style="font-size: 12px; color: #64748b;">Total: <strong>₱${totalAmt.toFixed(2)}</strong></div>
                    <div style="font-size: 14px; font-weight: 800; color: ${balanceAmt > 0 ? '#dc2626' : '#059669'};">
                        ${balanceAmt > 0 ? 'Balance: ₱' + balanceAmt.toFixed(2) : 'Fully Settled (₱0.00)'}
                    </div>
                </div>
            </div>
            <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden;">
                <table class="table table-striped" style="margin-bottom: 0; font-size: 13px;">
                    <thead style="background: #0f172a; color: #fff;">
                        <tr>
                            <th style="padding: 8px 12px;">#</th>
                            <th style="padding: 8px 12px;">Date &amp; Time</th>
                            <th style="padding: 8px 12px;">Method &amp; Ref</th>
                            <th style="padding: 8px 12px; text-align: right;">Amount Paid</th>
                            <th style="padding: 8px 12px; text-align: right;">Remaining Balance</th>
                            <th style="padding: 8px 12px;">Cashier</th>
                        </tr>
                    </thead>
                    <tbody>
        `;

        res.history.forEach((h, idx) => {
            html += `
                <tr>
                    <td style="padding: 10px 12px; font-weight: 700; color: #64748b;">${idx + 1}</td>
                    <td style="padding: 10px 12px;">${escapeHtml(h.payment_date)}</td>
                    <td style="padding: 10px 12px;">
                        <strong>${escapeHtml(h.payment_method)}</strong>
                        ${h.reference_no ? `<div style="font-size: 11px; color: #64748b;">Ref: ${escapeHtml(h.reference_no)}</div>` : ''}
                    </td>
                    <td style="padding: 10px 12px; text-align: right; font-weight: 800; color: #059669;">
                        ₱${parseFloat(h.amount_paid).toFixed(2)}
                    </td>
                    <td style="padding: 10px 12px; text-align: right; font-weight: 700; color: ${parseFloat(h.remaining_balance) > 0 ? '#dc2626' : '#059669'};">
                        ₱${parseFloat(h.remaining_balance).toFixed(2)}
                    </td>
                    <td style="padding: 10px 12px; color: #475569;">${escapeHtml(h.cashier_name || 'Cashier')}</td>
                </tr>
            `;
        });

        html += `
                    </tbody>
                </table>
            </div>
        `;

        container.innerHTML = html;
    })
    .catch(err => {
        container.innerHTML = `<div class="alert alert-danger"><i class="fa fa-exclamation-triangle"></i> Error loading ledger: ${escapeHtml(err.message)}</div>`;
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
