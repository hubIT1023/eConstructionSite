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
$can_adjust_terms = is_admin_or_manager_role($user_role);
$is_supplier_admin = ($user_role === 'ADMIN');
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

// Helper for parsing credit markup from transaction info
if (!function_exists('parse_credit_markup')) {
    function parse_credit_markup($row) {
        $tx = is_array($row) ? ($row['bank_transaction_info'] ?? '') : strval($row);
        $res = ['amount' => 0.0, 'label' => ''];
        if (preg_match('/Credit Mark[\s-]*Up:\s*\+?(?:[^\d\s]+\s*)?([0-9,.]+)(?:\s*\(([^)]+)\))?/u', $tx, $m)) {
            $res['amount'] = floatval(str_replace(',', '', $m[1]));
            if (!empty($m[2])) {
                $res['label'] = trim($m[2]);
            }
        }
        return $res;
    }
}

// Helper to extract delivery fee from transaction info
if (!function_exists('parse_order_delivery_fee')) {
    function parse_order_delivery_fee($row) {
        $tx = is_array($row) ? ($row['bank_transaction_info'] ?? '') : strval($row);
        if (preg_match('/Delivery (?:Fee|Cost):\s*(?:[^\d\s]+\s*)?([0-9,.]+)/u', $tx, $m)) {
            return floatval(str_replace(',', '', $m[1]));
        }
        return 0.0;
    }
}

// Helper to extract due date from transaction info or calculate from payment date
if (!function_exists('parse_order_due_date')) {
    function parse_order_due_date($tx_info, $payment_date, $payment_method) {
        if (preg_match('/(?:Due Date|Due)[:\s]+([0-9]{4}-[0-9]{2}-[0-9]{2})/i', $tx_info, $m)) {
            return $m[1];
        }
        $days = 15;
        if (preg_match('/([0-9]+)\s*Days/i', $payment_method . ' ' . $tx_info, $m_d)) {
            $days = (int)$m_d[1];
        }
        return date('Y-m-d', strtotime($payment_date . " +$days days"));
    }
}

// Helper to parse authoritative total invoiced amount
if (!function_exists('parse_invoice_total')) {
    function parse_invoice_total($row, $items_subtotal = 0) {
        $tx = is_array($row) ? ($row['bank_transaction_info'] ?? '') : strval($row);
        // 1. Total Payable
        if (preg_match('/Total Payable:\s*(?:[^\d\s]+\s*)?([0-9,.]+)/u', $tx, $m)) {
            $v = floatval(str_replace(',', '', $m[1]));
            if ($v > 0) return $v;
        }
        // 2. Total Invoice or Total Amount
        if (preg_match('/Total (?:Invoice|Amount):\s*(?:[^\d\s]+\s*)?([0-9,.]+)/u', $tx, $m)) {
            $v = floatval(str_replace(',', '', $m[1]));
            if ($v > 0) return $v;
        }
        // 3. Standalone Total (excluding Base Total)
        if (preg_match('/(?:^|\|)\s*(?<!Base )Total:\s*(?:[^\d\s]+\s*)?([0-9,.]+)/u', $tx, $m)) {
            $v = floatval(str_replace(',', '', $m[1]));
            if ($v > 0) return $v;
        }
        // Fallback: items_subtotal + markup + delivery
        $cm = parse_credit_markup($row);
        $df = parse_order_delivery_fee($row);
        $computed = floatval($items_subtotal) + $cm['amount'] + $df;
        $paid = is_array($row) ? floatval($row['paid_amount'] ?? 0) : 0.0;
        return max($paid, $computed);
    }
}

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

        $invoice_grand_total = parse_invoice_total($order_pay, $items_total);

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

        $credit_ref = !empty($order_pay['card_number']) ? $order_pay['card_number'] : ($order_pay['credit_ref_no'] ?? '');
        if (empty($credit_ref) && preg_match('/Credit Ref\s*(?:#|No|Number)?[:\s]+([A-Z0-9-]+)/i', $order_pay['bank_transaction_info'] ?? '', $m_cr)) {
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



// -------------------------------------------------------------
// AJAX Endpoint: Adjust Credit Terms & Due Date
// -------------------------------------------------------------
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'adjust_credit_terms') {
    header('Content-Type: application/json');
    if (!$can_adjust_terms) {
        echo json_encode([
            'success' => false,
            'message' => 'Permission denied. Only Supplier Admin and Store Manager are authorized to adjust credit terms and due dates.'
        ]);
        exit;
    }
    $pay_id = trim($_POST['payment_id'] ?? '');
    $new_due_date = trim($_POST['due_date'] ?? '');
    $new_method = trim($_POST['payment_method'] ?? '');
    $new_check_ref = trim($_POST['card_number'] ?? '');
    $adj_notes = trim($_POST['adjustment_notes'] ?? '');

    if (empty($pay_id)) {
        echo json_encode(['success' => false, 'message' => 'Invalid Payment / Invoice ID.']);
        exit;
    }

    if (empty($new_due_date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $new_due_date)) {
        echo json_encode(['success' => false, 'message' => 'Please provide a valid Due Date (YYYY-MM-DD).']);
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

        $old_tx = $order_pay['bank_transaction_info'] ?? '';
        $old_method = $order_pay['payment_method'] ?? '';
        $old_due_date = parse_order_due_date($old_tx, $order_pay['payment_date'], $old_method);

        // Calculate term days if method name is empty
        $order_base_date = date('Y-m-d', strtotime($order_pay['payment_date']));
        $diff_days = (int)round((strtotime($new_due_date) - strtotime($order_base_date)) / 86400);
        if (empty($new_method)) {
            $new_method = "Check / Terms (" . max(1, $diff_days) . " Days)";
        }

        // Update or inject Due Date into bank_transaction_info
        if (preg_match('/(?:Due Date|Due)[:\s]+[0-9]{4}-[0-9]{2}-[0-9]{2}/i', $old_tx)) {
            $updated_tx = preg_replace('/(?:Due Date|Due)[:\s]+[0-9]{4}-[0-9]{2}-[0-9]{2}/i', 'Due: ' . $new_due_date, $old_tx);
        } else {
            $updated_tx = ($old_tx ? $old_tx . " | " : "") . "Due: " . $new_due_date;
        }

        // Append audit entry to bank_transaction_info
        $log_entry = "\n[Terms Adjusted: " . $old_method . " (Due: " . $old_due_date . ") -> " . $new_method . " (Due: " . $new_due_date . ") by " . $cashier_name . " on " . date('Y-m-d H:i') . (!empty($adj_notes) ? " | Note: " . $adj_notes : "") . "]";
        $updated_tx .= $log_entry;

        $pdo->beginTransaction();

        $stmt_up = $pdo->prepare("
            UPDATE tbl_payment 
            SET payment_method = ?, card_number = ?, bank_transaction_info = ?
            WHERE id = ? AND supplier_id = ?
        ");
        $stmt_up->execute([
            $new_method,
            $new_check_ref,
            $updated_tx,
            $order_pay['id'],
            $supplier_id
        ]);

        // Calculate balance for ledger entry
        $stmt_items_sum = $pdo->prepare("SELECT COALESCE(SUM(NULLIF(regexp_replace(quantity, '[^0-9.]', '', 'g'), '')::numeric * NULLIF(regexp_replace(unit_price, '[^0-9.-]', '', 'g'), '')::numeric), 0) as items_subtotal FROM tbl_order WHERE payment_id = ? AND supplier_id = ?");
        $stmt_items_sum->execute([$order_pay['payment_id'], $supplier_id]);
        $sum_row = $stmt_items_sum->fetch(PDO::FETCH_ASSOC);
        $items_total = floatval($sum_row['items_subtotal'] ?? 0);
        $invoice_grand_total = parse_invoice_total($order_pay, $items_total);
        $current_balance = max(0, $invoice_grand_total - floatval($order_pay['paid_amount']));

        $audit_note = "Terms adjusted from " . $old_method . " (Due: " . date('M d, Y', strtotime($old_due_date)) . ") to " . $new_method . " (Due: " . date('M d, Y', strtotime($new_due_date)) . ")" . (!empty($adj_notes) ? " — " . $adj_notes : "");

        $stmt_cp = $pdo->prepare("
            INSERT INTO tbl_credit_payments (
                payment_id, supplier_id, payment_date, amount_paid, payment_method,
                reference_no, remaining_balance, cashier_name, notes
            ) VALUES (?, ?, CURRENT_TIMESTAMP, 0.00, ?, ?, ?, ?, ?)
        ");
        $stmt_cp->execute([
            $order_pay['payment_id'],
            $supplier_id,
            'Terms Adjustment',
            $new_check_ref,
            $current_balance,
            $cashier_name,
            $audit_note
        ]);

        $pdo->commit();

        $is_settled = ($current_balance <= 0.009);
        $aging_badge = $is_settled ? '<span class="badge" style="background: #d1fae5; color: #065f46;">Fully Paid</span>' : get_credit_aging_badge($new_due_date);

        echo json_encode([
            'success' => true,
            'message' => 'Credit terms and due date successfully updated to ' . $new_method . ' (Due: ' . date('M d, Y', strtotime($new_due_date)) . ')!',
            'payment_id' => $order_pay['payment_id'],
            'payment_method' => $new_method,
            'card_number' => $new_check_ref,
            'due_date' => $new_due_date,
            'formatted_due_date' => date('M d, Y', strtotime($new_due_date)),
            'aging_badge_html' => $aging_badge
        ]);
        exit;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        exit;
    }
}

// -------------------------------------------------------------
// AJAX Endpoint: Update Customer Name (Supplier Admin Only)
// -------------------------------------------------------------
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_customer_name') {
    header('Content-Type: application/json');
    if (!$is_supplier_admin) {
        echo json_encode([
            'success' => false,
            'message' => 'Permission denied. Only Supplier Admin is authorized to edit customer names.'
        ]);
        exit;
    }
    $pay_id = trim($_POST['payment_id'] ?? '');
    $new_name = trim($_POST['customer_name'] ?? '');

    if (empty($pay_id)) {
        echo json_encode(['success' => false, 'message' => 'Invalid Payment / Invoice ID.']);
        exit;
    }

    if (empty($new_name)) {
        $new_name = 'Walk-in Customer';
    }

    try {
        $stmt_chk = $pdo->prepare("SELECT id, payment_id, customer_name, bank_transaction_info FROM tbl_payment WHERE (payment_id = ? OR txnid = ?) AND supplier_id = ?");
        $stmt_chk->execute([$pay_id, $pay_id, $supplier_id]);
        $order_pay = $stmt_chk->fetch(PDO::FETCH_ASSOC);

        if (!$order_pay) {
            echo json_encode(['success' => false, 'message' => 'Credit account / order not found.']);
            exit;
        }

        $old_name = $order_pay['customer_name'];
        $log_entry = "\n[Customer Name Edited: " . $old_name . " -> " . $new_name . " by " . $cashier_name . " (Supplier Admin) on " . date('Y-m-d H:i') . "]";
        $updated_tx = ($order_pay['bank_transaction_info'] ?? '') . $log_entry;

        $stmt_up = $pdo->prepare("UPDATE tbl_payment SET customer_name = ?, bank_transaction_info = ? WHERE id = ? AND supplier_id = ?");
        $stmt_up->execute([$new_name, $updated_tx, $order_pay['id'], $supplier_id]);

        echo json_encode([
            'success' => true,
            'message' => 'Customer name updated to "' . $new_name . '"!',
            'payment_id' => $order_pay['payment_id'],
            'customer_name' => $new_name
        ]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        exit;
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
    function calculate_item_financials($product_id, $unit_price, $quantity, &$products_map, $order_item = null) {
        $qty = (int)$quantity;
        $u_price = (float)$unit_price;
        $p_id = (int)$product_id;
        $subtotal = $qty * $u_price;
        $markup = 20.0;
        $unit_capital = 0.0;

        // 1. Prioritize transaction-time snapshot capital_price / markup_amount stored on tbl_order
        if (is_array($order_item)) {
            if (isset($order_item['capital_price']) && $order_item['capital_price'] !== null && (float)$order_item['capital_price'] > 0) {
                $unit_capital = (float)$order_item['capital_price'];
                $markup = (isset($order_item['markup_amount']) && $order_item['markup_amount'] !== null)
                    ? (float)$order_item['markup_amount']
                    : max(0.0, round($u_price - $unit_capital, 2));
            } elseif (!empty($order_item['product_details']) && preg_match('/Capital:\s*₱?([0-9,.]+)\s*\|\s*Mark-Up:\s*₱?([0-9,.]+)/i', $order_item['product_details'], $ro_m)) {
                $unit_capital = (float)str_replace(',', '', $ro_m[1]);
                $markup = (float)str_replace(',', '', $ro_m[2]);
            }
        }

        // 2. Fallback for legacy rows where snapshot capital_price was not yet populated
        if ($unit_capital <= 0) {
            if ($p_id > 0 && isset($products_map[$p_id])) {
                $p = $products_map[$p_id];
                $m = (isset($p['p_markup']) && $p['p_markup'] !== '') ? (float)preg_replace('/[^0-9.]/', '', strval($p['p_markup'])) : 20.0;
                if ($m <= 0) $m = 20.0;
                $markup = $m;

                $p_cap = isset($p['p_capital_price']) ? (float)preg_replace('/[^0-9.]/', '', strval($p['p_capital_price'])) : 0.0;
                $p_cur = isset($p['p_current_price']) ? (float)preg_replace('/[^0-9.]/', '', strval($p['p_current_price'])) : 0.0;

                if ($p_cap > 0 && abs($u_price - $p_cur) < 0.01) {
                    $unit_capital = $p_cap;
                } elseif ($p_cap > 0 && $p_cur > 0) {
                    // Product price changed after this historical sale; scale capital proportionally to historical unit_price
                    $unit_capital = round($u_price * ($p_cap / $p_cur), 2);
                } else {
                    $unit_capital = round($u_price / (1 + ($markup / 100)), 2);
                }
            } else {
                $markup = 20.0;
                $unit_capital = round($u_price / 1.20, 2);
            }
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
    $cm_info = parse_credit_markup($rec);
    $rec['credit_markup'] = $cm_info['amount'];
    $rec['credit_markup_label'] = $cm_info['label'];
    $rec['delivery_fee'] = parse_order_delivery_fee($rec);

    // Calculate total item capital cost for this credit order using locked transaction-time snapshots
    $c_capital_total = 0.0;
    try {
        $stmt_c_items = $pdo->prepare("SELECT product_id, unit_price, quantity, capital_price, markup_amount, product_details FROM tbl_order WHERE payment_id = ?");
        $stmt_c_items->execute([$rec['payment_id']]);
        $c_items = $stmt_c_items->fetchAll(PDO::FETCH_ASSOC);
        foreach ($c_items as $ci) {
            $c_fin = calculate_item_financials($ci['product_id'], $ci['unit_price'], $ci['quantity'], $products_map, $ci);
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

// Fetch current supplier details for voucher receipts
$stmt_sup = $pdo->prepare("SELECT * FROM tbl_supplier WHERE supplier_id = ?");
$stmt_sup->execute([$supplier_id]);
$current_supplier_data = $stmt_sup->fetch(PDO::FETCH_ASSOC) ?: [];

require_once('header.php');
?>

<style>
.credit-po-trigger {
    text-decoration: none !important;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 3px 8px;
    border-radius: 5px;
    background: #f0f9ff;
    border: 1px solid #bae6fd;
    color: #0369a1 !important;
    transition: all 0.2s ease;
    cursor: pointer;
}
.credit-po-trigger:hover {
    background: #0284c7 !important;
    color: #ffffff !important;
    border-color: #0284c7 !important;
    box-shadow: 0 2px 6px rgba(2, 132, 199, 0.28);
    transform: translateY(-1px);
}
.credit-po-trigger strong {
    font-family: monospace;
    font-size: 13.5px;
    color: inherit;
    font-weight: 800;
}
.credit-po-trigger i {
    font-size: 12px;
    color: inherit;
}
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
.credit-btn-register {
    background: #0284c7;
    color: #fff;
    border: 1px solid #0369a1;
    font-weight: 700;
    font-size: 12px;
    padding: 5px 11px;
    border-radius: 4px;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    text-decoration: none;
    transition: all 0.15s ease;
}
.credit-btn-register:hover, .credit-btn-register:focus {
    background: #0369a1;
    color: #fff;
    text-decoration: none;
    box-shadow: 0 2px 6px rgba(2, 132, 199, 0.35);
}
.credit-terms-pill {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 3px 8px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 800;
    cursor: pointer;
    background: #fef3c7;
    color: #92400e;
    border: 1px solid #fde68a;
    transition: all 0.2s ease;
    text-decoration: none !important;
}
.credit-terms-pill:hover {
    background: #fde68a;
    color: #78350f;
    border-color: #f59e0b;
    box-shadow: 0 1px 4px rgba(245, 158, 11, 0.25);
    transform: translateY(-1px);
}
.term-preset-btn {
    padding: 5px 11px;
    font-size: 11.5px;
    font-weight: 700;
    border-radius: 4px;
    border: 1px solid #cbd5e1;
    background: #f8fafc;
    color: #334155;
    transition: all 0.15s ease;
}
.term-preset-btn:hover {
    background: #e2e8f0;
    color: #0f172a;
    border-color: #94a3b8;
}
.term-preset-btn.active {
    background: #2563eb !important;
    color: #ffffff !important;
    border-color: #1d4ed8 !important;
    box-shadow: 0 1px 3px rgba(37, 99, 235, 0.35);
}
.inline-customer-view:hover .inline-edit-trigger {
    color: #0284c7 !important;
}
.inline-customer-view:hover .cust-name-text {
    color: #0369a1 !important;
    text-decoration: underline dotted #94a3b8;
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

    <div id="creditAlertNoticeArea" style="margin-bottom: 15px;"></div>

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

                        // Fetch order items for Purchase Order Modal
                        $stmt_items = $pdo->prepare("SELECT * FROM tbl_order WHERE payment_id = ? ORDER BY id ASC");
                        $stmt_items->execute([$pay_id]);
                        $order_items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

                        // Extract Option 1 (Rule 1A) Batch Rollover items if present
                        $order_rollover_items = [];
                        foreach ($order_items as $oit_chk) {
                            $raw_det_chk = $oit_chk['product_details'] ?? '';
                            if (preg_match('/\[Option 1 Batch Rollover:\s*Old Q=(\d+)\s*@\s*₱([0-9.]+)\s*->\s*New Batch NQ=(\d+)\s*@\s*₱([0-9.]+)\s*\|\s*Capital:\s*₱([0-9.]+)\s*\|\s*Mark-Up:\s*₱([0-9.]+)\s*\|\s*Profit:\s*₱([0-9.]+)\]/i', $raw_det_chk, $rm_chk)) {
                                $ro_q_ord = max(1, intval($oit_chk['quantity'] ?? 1));
                                $order_rollover_items[] = [
                                    'name' => $oit_chk['product_name'] ?? 'Item',
                                    'qty' => $ro_q_ord,
                                    'old_qty' => intval($rm_chk[1]),
                                    'old_price' => floatval($rm_chk[2]),
                                    'new_qty' => intval($rm_chk[3]),
                                    'new_price' => floatval($rm_chk[4]),
                                    'unit_capital' => floatval($rm_chk[5]),
                                    'unit_markup' => floatval($rm_chk[6]),
                                    'total_profit' => floatval($rm_chk[7]),
                                    'total_capital' => round(floatval($rm_chk[5]) * $ro_q_ord, 2),
                                    'unit_profit' => round(floatval($rm_chk[7]) / $ro_q_ord, 2)
                                ];
                            }
                        }

                        // Extract delivery fee and credit surcharge if recorded
                        $row_delivery_fee = $row['delivery_fee'] ?? parse_order_delivery_fee($row);
                        $row_credit_markup = $row['credit_markup'] ?? (parse_credit_markup($row)['amount'] ?? 0);
                        $row_credit_markup_label = $row['credit_markup_label'] ?? (parse_credit_markup($row)['label'] ?? '');
                    ?>
                    <tr id="creditRow-<?php echo htmlspecialchars($pay_id); ?>">
                        <td style="color: #64748b; font-weight: 700;"><?php echo $row_idx; ?></td>
                        <td>
                            <a href="#" data-toggle="modal" data-target="#po-credit-modal-<?php echo $row['id']; ?>" class="credit-po-trigger" title="Click to view &amp; print Purchase Order Voucher">
                                <i class="fa fa-file-text-o"></i>
                                <strong><?php echo htmlspecialchars($pay_id); ?></strong>
                            </a>
                            <?php if (!$is_settled && !empty($order_rollover_items)): ?>
                                <div style="margin-top: 3px;">
                                    <span class="badge" style="background: #fef3c7; color: #92400e; border: 1px solid #f59e0b; font-size: 9.5px; font-weight: 800; padding: 2px 6px;" title="Option 1 (Rule 1A): Low Stock (&lt; 3) &amp; New Batch Price Rollover Applied">
                                        <i class="fa fa-bell" style="color: #d97706;"></i> RULE 1A ROLLOVER
                                    </span>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($row['card_number'])): ?>
                                <span class="badge" id="checkRefBadge-<?php echo htmlspecialchars($pay_id); ?>" style="background: #e0f2fe; color: #0369a1; font-family: monospace; font-size: 11px; margin-top: 3px; display: inline-block;" title="Credit Reference / Check Number">
                                    <i class="fa fa-tag"></i> <?php echo htmlspecialchars($row['card_number']); ?>
                                </span>
                            <?php else: ?>
                                <span class="badge" id="checkRefBadge-<?php echo htmlspecialchars($pay_id); ?>" style="background: #e0f2fe; color: #0369a1; font-family: monospace; font-size: 11px; margin-top: 3px; display: none;" title="Credit Reference / Check Number">
                                </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div style="font-weight: 700; color: #0f172a; font-size: 12.5px;"><?php echo date('M d, Y', strtotime($row['payment_date'])); ?></div>
                            <div style="margin-top: 3px;">
                                <?php if ($is_settled): ?>
                                    <span class="badge" style="background: #f1f5f9; color: #64748b; font-size: 10.5px; font-weight: 800;">
                                        <?php echo htmlspecialchars($row['payment_method']); ?>
                                    </span>
                                <?php elseif ($can_adjust_terms): ?>
                                    <a href="javascript:void(0)" class="credit-terms-pill" 
                                       id="termsBadge-<?php echo htmlspecialchars($pay_id); ?>"
                                       data-payment-id="<?php echo htmlspecialchars($pay_id, ENT_QUOTES); ?>"
                                       data-customer-name="<?php echo htmlspecialchars($cust_name, ENT_QUOTES); ?>"
                                       data-order-date="<?php echo date('Y-m-d', strtotime($row['payment_date'])); ?>"
                                       data-order-date-formatted="<?php echo date('M d, Y', strtotime($row['payment_date'])); ?>"
                                       data-payment-method="<?php echo htmlspecialchars($row['payment_method'], ENT_QUOTES); ?>"
                                       data-check-ref="<?php echo htmlspecialchars($row['card_number'] ?? '', ENT_QUOTES); ?>"
                                       data-due-date="<?php echo htmlspecialchars($due_date, ENT_QUOTES); ?>"
                                       data-balance="<?php echo (float)$bal_amt; ?>"
                                       onclick="openAdjustTermsFromElement(this)"
                                       title="Click to adjust Credit Terms or Due Date (Admin / Manager)">
                                        <i class="fa fa-pencil" style="font-size: 10px; color: #b45309;"></i>
                                        <span class="term-badge-text"><?php echo htmlspecialchars($row['payment_method']); ?></span>
                                    </a>
                                <?php else: ?>
                                    <span class="badge" id="termsBadge-<?php echo htmlspecialchars($pay_id); ?>" style="background: #fef3c7; color: #92400e; font-size: 10.5px; font-weight: 800; padding: 4px 8px; border: 1px solid #fde68a;" title="Credit Terms: <?php echo htmlspecialchars($row['payment_method']); ?> (Admin/Manager authorization required to adjust)">
                                        <span class="term-badge-text"><?php echo htmlspecialchars($row['payment_method']); ?></span>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <?php if ($is_supplier_admin): ?>
                                <div class="inline-customer-wrap" id="custNameWrap-<?php echo htmlspecialchars($pay_id); ?>">
                                    <div class="inline-customer-view" id="custNameDisplay-<?php echo htmlspecialchars($pay_id); ?>" style="display: flex; align-items: center; gap: 6px;">
                                        <strong class="cust-name-text" style="font-size: 13.5px; color: #0f172a; display: inline-block; cursor: pointer;" onclick="startInlineCustomerEdit('<?php echo htmlspecialchars($pay_id, ENT_QUOTES); ?>')" title="Supplier Admin: Click to edit customer name">
                                            <?php echo htmlspecialchars($cust_name); ?>
                                        </strong>
                                        <button type="button" class="btn btn-link btn-xs inline-edit-trigger" onclick="startInlineCustomerEdit('<?php echo htmlspecialchars($pay_id, ENT_QUOTES); ?>')" title="Supplier Admin: Edit Customer Name" style="padding: 0 4px; color: #94a3b8; font-size: 11px; text-decoration: none; border: none; background: transparent;">
                                            <i class="fa fa-pencil"></i>
                                        </button>
                                    </div>
                                    <div class="inline-customer-edit" id="custNameEdit-<?php echo htmlspecialchars($pay_id); ?>" style="display: none; margin-bottom: 2px;">
                                        <div class="input-group input-group-sm" style="max-width: 220px;">
                                            <input type="text" class="form-control cust-name-input" id="custNameInput-<?php echo htmlspecialchars($pay_id); ?>" value="<?php echo htmlspecialchars($cust_name, ENT_QUOTES); ?>" placeholder="Customer name..." style="height: 28px; font-size: 12.5px; font-weight: 700; padding: 2px 8px; border-radius: 4px 0 0 4px; border: 1.5px solid #0284c7;" onkeydown="handleCustomerNameKeyDown(event, '<?php echo htmlspecialchars($pay_id, ENT_QUOTES); ?>')">
                                            <span class="input-group-btn">
                                                <button type="button" class="btn btn-success btn-xs" id="custNameSaveBtn-<?php echo htmlspecialchars($pay_id); ?>" onclick="saveInlineCustomerName('<?php echo htmlspecialchars($pay_id, ENT_QUOTES); ?>')" style="height: 28px; padding: 2px 8px;" title="Save Customer Name (Enter)">
                                                    <i class="fa fa-check"></i>
                                                </button>
                                                <button type="button" class="btn btn-default btn-xs" onclick="cancelInlineCustomerEdit('<?php echo htmlspecialchars($pay_id, ENT_QUOTES); ?>')" style="height: 28px; padding: 2px 7px;" title="Cancel (Esc)">
                                                    <i class="fa fa-times"></i>
                                                </button>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            <?php else: ?>
                                <strong style="font-size: 13.5px; color: #0f172a; display: block;"><?php echo htmlspecialchars($cust_name); ?></strong>
                            <?php endif; ?>
                            <?php if (!empty($cust_phone)): ?>
                                <div style="font-size: 11.5px; color: #64748b;"><i class="fa fa-phone"></i> <?php echo htmlspecialchars($cust_phone); ?></div>
                            <?php endif; ?>
                            <?php if (!empty($row['customer_address'])): ?>
                                <div style="font-size: 11px; color: #94a3b8; max-width: 200px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?php echo htmlspecialchars($row['customer_address']); ?></div>
                            <?php endif; ?>
                        </td>
                        <td style="text-align: right; font-weight: 700; color: #0f172a;">
                            ₱<?php echo number_format($total_amt, 2); ?>
                            <?php if ($row_credit_markup > 0): ?>
                                <div style="font-size: 10px; color: #b45309; font-weight: 600;" title="Includes Credit Surcharge: +₱<?php echo number_format($row_credit_markup, 2); ?><?php echo !empty($row_credit_markup_label) ? ' (' . htmlspecialchars($row_credit_markup_label) . ')' : ''; ?>">
                                    +₱<?php echo number_format($row_credit_markup, 2); ?> markup
                                </div>
                            <?php endif; ?>
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
                        <td style="text-align: center;" id="dueDateCell-<?php echo htmlspecialchars($pay_id); ?>">
                            <div class="due-date-text" style="font-size: 12px; font-weight: 700; color: #1e293b; margin-bottom: 2px;">
                                Due: <?php echo date('M d, Y', strtotime($due_date)); ?>
                            </div>
                            <div class="aging-badge-container">
                                <?php echo $is_settled ? '<span class="badge" style="background: #d1fae5; color: #065f46;">Fully Paid</span>' : get_credit_aging_badge($due_date); ?>
                            </div>
                        </td>
                        <td style="text-align: right; white-space: nowrap;">
                            <?php if (!$is_settled): ?>
                                <a href="pos.php?settle_credit=1&payment_id=<?php echo urlencode($pay_id); ?>" class="credit-btn-register" title="Send to POS Cash Register to Settle">
                                    <i class="fa fa-shopping-cart"></i> Register
                                </a>
                                <button type="button" class="credit-btn-settle" onclick="openSettleModal('<?php echo htmlspecialchars($pay_id, ENT_QUOTES); ?>', '<?php echo htmlspecialchars($cust_name, ENT_QUOTES); ?>', <?php echo $total_amt; ?>, <?php echo $paid_amt; ?>, <?php echo $bal_amt; ?>, '<?php echo htmlspecialchars($row['card_number'] ?? '', ENT_QUOTES); ?>')" title="Record full balance settlement or installment payment">
                                    <i class="fa fa-money"></i> Settle
                                </button>
                            <?php endif; ?>
                            <button type="button" class="btn btn-default btn-xs" onclick="viewCreditLedger('<?php echo htmlspecialchars($pay_id, ENT_QUOTES); ?>', '<?php echo htmlspecialchars($cust_name, ENT_QUOTES); ?>', <?php echo $total_amt; ?>, <?php echo $bal_amt; ?>)" style="font-weight: 700; padding: 5px 8px;" title="View payment installment history &amp; ledger">
                                <i class="fa fa-list-alt"></i> History
                            </button>

                            <!-- ============================================================== -->
                            <!-- Purchase Order Modal for Credit Account -->
                            <!-- ============================================================== -->
                            <div id="po-credit-modal-<?php echo $row['id']; ?>" class="modal fade" role="dialog" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog modal-lg" style="max-width: 820px; margin-top: 25px; text-align: left;">
                                    <div class="modal-content" style="border-radius: 10px; overflow: hidden; border: none; box-shadow: 0 15px 40px rgba(0,0,0,0.2);">
                                        
                                        <!-- Modern Modal Header -->
                                        <div class="modal-header-modern" style="background: #0284c7; display: flex; justify-content: space-between; align-items: center; padding: 12px 18px; color: #ffffff;">
                                            <h4 class="modal-title" style="margin: 0; font-size: 15.5px; font-weight: 700; display: flex; align-items: center; gap: 8px; color: #ffffff;">
                                                <i class="fa fa-file-text-o"></i> Customer Purchase Order Voucher — <span style="font-family: monospace; font-size: 16.5px;"><?php echo htmlspecialchars($row['txnid'] ?: $pay_id); ?></span>
                                            </h4>
                                            <div style="display: flex; align-items: center; gap: 8px;">
                                                <button type="button" class="btn btn-xs btn-default" onclick="printPOVoucher('<?php echo $row['id']; ?>')" style="background: #ffffff; color: #0284c7; font-weight: 700; border: none; border-radius: 5px; padding: 5px 12px; font-size: 11.5px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                                    <i class="fa fa-print"></i> Print Voucher
                                                </button>
                                                <button type="button" class="close" data-dismiss="modal" aria-hidden="true" style="color: #ffffff; opacity: 0.9; font-size: 22px; margin: 0; line-height: 1;">&times;</button>
                                            </div>
                                        </div>
                                        
                                        <div class="modal-body" style="padding: 14px 18px; background: #f8fafc; max-height: calc(85vh - 120px); overflow-y: auto;">
                                            <!-- Status & Overview Bar -->
                                            <div style="background: <?php echo $is_settled ? '#ecfdf5' : '#fffbeb'; ?>; border: 1.5px solid <?php echo $is_settled ? '#a7f3d0' : '#fde68a'; ?>; border-radius: 6px; padding: 7px 14px; margin-bottom: 10px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
                                                <div style="display: flex; align-items: center; gap: 8px;">
                                                    <span class="label" style="background: <?php echo $is_settled ? '#059669' : '#d97706'; ?>; color: #ffffff; font-size: 11px; font-weight: 800; padding: 4px 8px; border-radius: 3px; text-transform: uppercase;">
                                                        <i class="fa <?php echo $is_settled ? 'fa-check-circle' : 'fa-clock-o'; ?>"></i> <?php echo $is_settled ? 'PAID / SETTLED' : 'ON CREDIT'; ?>
                                                    </span>
                                                    <span style="font-size: 12px; color: <?php echo $is_settled ? '#065f46' : '#78350f'; ?>; font-weight: 600;">
                                                        <?php echo $is_settled ? 'Account fully settled' : 'Credit Account &bull; Terms Due: ' . date('M d, Y', strtotime($due_date)); ?>
                                                    </span>
                                                </div>
                                                <div style="font-size: 11.5px; color: #64748b; font-weight: 600;">
                                                    <i class="fa fa-calendar"></i> <?php echo date('M d, Y h:i A', strtotime($row['payment_date'])); ?>
                                                </div>
                                            </div>

                                            <!-- 2-Column Info Cards (Compact, Zero-Waste Space) -->
                                            <div class="row" style="margin-bottom: 10px;">
                                                <div class="col-sm-6 col-xs-12" style="margin-bottom: 6px;">
                                                    <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 6px; padding: 8px 12px;">
                                                        <div style="font-size: 10px; font-weight: 800; text-transform: uppercase; color: #64748b; margin-bottom: 3px; letter-spacing: 0.5px;">
                                                            <i class="fa fa-user"></i> Customer Information
                                                        </div>
                                                        <div style="font-size: 13px; font-weight: 700; color: #0f172a; line-height: 1.2;">
                                                            <?php echo htmlspecialchars($cust_name); ?>
                                                        </div>
                                                        <div style="font-size: 11px; color: #64748b; margin-top: 3px; display: flex; flex-wrap: wrap; gap: 8px;">
                                                            <?php if (!empty($cust_phone)): ?>
                                                                <span><i class="fa fa-phone" style="color: #0284c7;"></i> <?php echo htmlspecialchars($cust_phone); ?></span>
                                                            <?php endif; ?>
                                                            <?php if (!empty($row['customer_address'])): ?>
                                                                <span><i class="fa fa-map-marker" style="color: #ef4444;"></i> <?php echo htmlspecialchars($row['customer_address']); ?></span>
                                                            <?php endif; ?>
                                                            <?php if (!empty($row['customer_email'])): ?>
                                                                <span><i class="fa fa-envelope" style="color: #0284c7;"></i> <?php echo htmlspecialchars($row['customer_email']); ?></span>
                                                            <?php endif; ?>
                                                            <?php if (empty($cust_phone) && empty($row['customer_address']) && empty($row['customer_email'])): ?>
                                                                <span style="color: #94a3b8; font-style: italic;">Walk-in counter account</span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-sm-6 col-xs-12" style="margin-bottom: 6px;">
                                                    <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 6px; padding: 8px 12px;">
                                                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 3px;">
                                                            <div style="font-size: 10px; font-weight: 800; text-transform: uppercase; color: #64748b; letter-spacing: 0.5px;">
                                                                <i class="fa fa-building-o"></i> Store &amp; Fulfillment
                                                            </div>
                                                            <span class="badge" style="background: #e0f2fe; color: #0369a1; font-size: 10px; font-weight: 700; padding: 2px 6px;"><i class="fa fa-credit-card"></i> <?php echo htmlspecialchars($row['payment_method'] ?? 'On Credit / Terms'); ?></span>
                                                        </div>
                                                        <div style="font-size: 13px; font-weight: 700; color: #0f172a; line-height: 1.2;">
                                                            <?php echo htmlspecialchars(!empty($current_supplier_data['supplier_name']) ? $current_supplier_data['supplier_name'] : 'SAM & INRI CONSTRUCTION SUPPLY'); ?>
                                                        </div>
                                                        <div style="font-size: 11px; color: #64748b; margin-top: 3px; display: flex; flex-wrap: wrap; gap: 8px;">
                                                            <?php if (!empty($current_supplier_data['supplier_phone'])): ?>
                                                                <span><i class="fa fa-phone"></i> Tel: <?php echo htmlspecialchars($current_supplier_data['supplier_phone']); ?></span>
                                                            <?php endif; ?>
                                                            <?php if (!empty($row['card_number'])): ?>
                                                                <span style="color: #0369a1; font-weight: 700;"><i class="fa fa-tag"></i> Ref: <?php echo htmlspecialchars($row['card_number']); ?></span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <?php if (!$is_settled && !empty($order_rollover_items)): ?>
                                            <!-- Option 1 (Rule 1A): Pre-Payment Notification / Alert for Cashier & Admin -->
                                            <div style="background: #fffbeb; border: 1.5px solid #f59e0b; border-left: 5px solid #d97706; border-radius: 6px; padding: 10px 12px; margin-bottom: 10px;">
                                                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 6px; margin-bottom: 6px;">
                                                    <div style="font-size: 11.5px; font-weight: 800; color: #92400e; text-transform: uppercase;">
                                                        <i class="fa fa-bell" style="color: #d97706;"></i> Pre-Payment Alert (Cashier &amp; Admin): Option 1 — Low Stock (&lt; 3) &amp; New Batch Price Rollover
                                                    </div>
                                                    <span class="label" style="background: #d97706; color: #fff; font-size: 9.5px; font-weight: 800; padding: 2px 6px;">INTERNAL COST &amp; PROFIT NOTICE</span>
                                                </div>
                                                <?php foreach ($order_rollover_items as $ori): ?>
                                                <div style="background: #ffffff; border: 1px solid #fde68a; border-radius: 5px; padding: 6px 10px; margin-bottom: 4px; font-size: 11.5px;">
                                                    <div style="font-weight: 800; color: #0f172a; margin-bottom: 3px;">
                                                        <?php echo htmlspecialchars($ori['name']); ?>
                                                        <span style="color: #b45309; font-weight: 700; font-size: 10.5px;">
                                                            (Qty: <?php echo $ori['qty']; ?> pcs | Old Stock: <?php echo $ori['old_qty']; ?> + New Batch: <?php echo $ori['new_qty']; ?> &rarr; Merged: <?php echo ($ori['old_qty'] + $ori['new_qty']); ?> pcs)
                                                        </span>
                                                    </div>
                                                    <div style="display: flex; flex-wrap: wrap; gap: 12px; font-size: 11px;">
                                                        <span>Selling Price: <strike style="color:#94a3b8;">&#8369;<?php echo number_format($ori['old_price'], 2); ?></strike> &rarr; <strong style="color:#1d4ed8;">&#8369;<?php echo number_format($ori['new_price'], 2); ?></strong></span>
                                                        <span>Capital Price (Ca): <strong>&#8369;<?php echo number_format($ori['unit_capital'], 2); ?></strong> (Total: &#8369;<?php echo number_format($ori['total_capital'], 2); ?>)</span>
                                                        <span>Mark-Up Amt: <strong style="color:#b45309;">+&#8369;<?php echo number_format($ori['unit_markup'], 2); ?>/u</strong></span>
                                                        <span>Net Profit Amt: <strong style="color:#059669;">+&#8369;<?php echo number_format($ori['total_profit'], 2); ?></strong> (&#8369;<?php echo number_format($ori['unit_profit'], 2); ?>/u)</span>
                                                    </div>
                                                </div>
                                                <?php endforeach; ?>
                                            </div>
                                            <?php endif; ?>

                                            <!-- Ordered Items Table -->
                                            <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 6px; overflow: hidden; margin-bottom: 10px;">
                                                <div style="padding: 7px 14px; background: #f1f5f9; border-bottom: 1px solid #e2e8f0; font-size: 11px; font-weight: 800; text-transform: uppercase; color: #475569; letter-spacing: 0.5px;">
                                                    <i class="fa fa-shopping-cart"></i> Ordered Items (<?php echo count($order_items); ?>)
                                                </div>
                                                <div class="table-responsive" style="margin-bottom: 0;">
                                                    <table class="table table-hover" style="margin-bottom: 0; font-size: 12.5px;">
                                                        <thead style="background: #f8fafc;">
                                                            <tr>
                                                                <th style="width: 35px; text-align: center; color: #64748b; font-weight: 700; padding: 6px 8px; border-bottom: 1px solid #e2e8f0;">#</th>
                                                                <th style="color: #475569; font-weight: 700; padding: 6px 10px; border-bottom: 1px solid #e2e8f0;">Item Description</th>
                                                                <th style="width: 75px; text-align: center; color: #475569; font-weight: 700; padding: 6px 8px; border-bottom: 1px solid #e2e8f0;">Qty</th>
                                                                <th style="width: 100px; text-align: right; color: #475569; font-weight: 700; padding: 6px 10px; border-bottom: 1px solid #e2e8f0;">Unit Price</th>
                                                                <th style="width: 110px; text-align: right; color: #475569; font-weight: 700; padding: 6px 10px; border-bottom: 1px solid #e2e8f0;">Subtotal</th>
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
                                                                <td style="text-align: center; color: <?php echo $is_oit_return ? '#ea580c' : '#94a3b8'; ?>; font-weight: 600; vertical-align: middle; padding: 5px 8px;"><?php echo $oit_idx; ?></td>
                                                                <td style="vertical-align: middle; padding: 5px 10px;">
                                                                    <div style="font-weight: 700; color: <?php echo $is_oit_return ? '#9a3412' : '#0f172a'; ?>; line-height: 1.25;">
                                                                        <?php if ($is_oit_return): ?>
                                                                            <span class="badge" style="background: #ea580c; color: #fff; font-size: 9px; font-weight: 800; padding: 1px 5px; margin-right: 4px; vertical-align: text-top;"><i class="fa fa-exchange"></i> TRADE-IN CREDIT</span>
                                                                        <?php endif; ?>
                                                                        <?php echo htmlspecialchars($oit['product_name'] ?? ''); ?>
                                                                    </div>
                                                                    <?php if (!empty($oit['size']) || !empty($oit['color'])): ?>
                                                                        <div style="margin-top: 2px; display: flex; gap: 4px;">
                                                                            <?php if (!empty($oit['size']) && $oit['size'] !== '-'): ?>
                                                                                <span class="badge" style="background: <?php echo $is_oit_return ? '#fed7aa; color: #9a3412' : '#f1f5f9; color: #475569'; ?>; font-size: 10px; font-weight: 600; padding: 1px 5px;">Size: <?php echo htmlspecialchars($oit['size']); ?></span>
                                                                            <?php endif; ?>
                                                                            <?php if (!empty($oit['color']) && $oit['color'] !== '-'): ?>
                                                                                <span class="badge" style="background: <?php echo $is_oit_return ? '#fed7aa; color: #9a3412' : '#f1f5f9; color: #475569'; ?>; font-size: 10px; font-weight: 600; padding: 1px 5px;">Color: <?php echo htmlspecialchars($oit['color']); ?></span>
                                                                            <?php endif; ?>
                                                                        </div>
                                                                    <?php endif; ?>
                                                                </td>
                                                                <td style="text-align: center; font-weight: 700; color: <?php echo $is_oit_return ? '#ea580c' : '#1e293b'; ?>; vertical-align: middle; padding: 5px 8px;"><?php echo abs($oit_qty); ?> <span style="font-size: 10.5px; color: #64748b; font-weight: normal;"><?php echo $unit_label; ?></span></td>
                                                                <td style="text-align: right; color: <?php echo $is_oit_return ? '#ea580c' : '#475569'; ?>; font-weight: 600; vertical-align: middle; padding: 5px 10px;"><?php echo $is_oit_return ? '-₱' . number_format(abs($oit_price), 2) : '₱' . number_format($oit_price, 2); ?></td>
                                                                <td style="text-align: right; font-weight: 800; color: <?php echo $is_oit_return ? '#ea580c' : '#0f172a'; ?>; vertical-align: middle; padding: 5px 10px;"><?php echo $is_oit_return ? '-₱' . number_format(abs($oit_line), 2) : '₱' . number_format($oit_line, 2); ?></td>
                                                            </tr>
                                                            <?php endforeach; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>

                                            <!-- Financial Totals Block -->
                                            <div style="display: flex; justify-content: flex-end; margin-bottom: 10px;">
                                                <div style="width: 100%; max-width: 340px; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 6px; padding: 8px 14px;">
                                                    <div style="display: flex; justify-content: space-between; font-size: 12px; color: #475569; margin-bottom: 3px;">
                                                        <span>Items Subtotal:</span>
                                                        <span style="font-weight: 700; color: #0f172a;">₱<?php echo number_format($calc_subtotal, 2); ?></span>
                                                    </div>
                                                    <?php if ($row_credit_markup > 0): ?>
                                                    <div style="display: flex; justify-content: space-between; font-size: 12px; color: #b45309; margin-bottom: 3px;">
                                                        <span><i class="fa fa-percent"></i> Credit Surcharge<?php echo !empty($row_credit_markup_label) ? ' (' . htmlspecialchars($row_credit_markup_label) . ')' : ''; ?>:</span>
                                                        <span style="font-weight: 700; color: #b45309;">+₱<?php echo number_format($row_credit_markup, 2); ?></span>
                                                    </div>
                                                    <?php endif; ?>
                                                    <?php if ($row_delivery_fee > 0): ?>
                                                    <div style="display: flex; justify-content: space-between; font-size: 12px; color: #475569; margin-bottom: 3px;">
                                                        <span>Delivery Fee:</span>
                                                        <span style="font-weight: 600; color: #0f172a;">₱<?php echo number_format($row_delivery_fee, 2); ?></span>
                                                    </div>
                                                    <?php endif; ?>
                                                    <div style="display: flex; justify-content: space-between; font-size: 12px; color: #475569; margin-bottom: 3px; border-top: 1px dashed #e2e8f0; padding-top: 3px;">
                                                        <span>Total Invoice:</span>
                                                        <span style="font-weight: 700; color: #0f172a;">₱<?php echo number_format($total_amt, 2); ?></span>
                                                    </div>
                                                    <div style="display: flex; justify-content: space-between; font-size: 12px; color: #059669; margin-bottom: 3px;">
                                                        <span>Paid to Date:</span>
                                                        <span style="font-weight: 700;">₱<?php echo number_format($paid_amt, 2); ?></span>
                                                    </div>
                                                    <div style="border-top: 2px solid #e2e8f0; padding-top: 5px; margin-top: 3px; display: flex; justify-content: space-between; align-items: baseline;">
                                                        <span style="font-size: 13px; font-weight: 800; color: <?php echo $is_settled ? '#059669' : '#dc2626'; ?>;">OUTSTANDING BALANCE:</span>
                                                        <span style="font-size: 16px; font-weight: 900; color: <?php echo $is_settled ? '#059669' : '#dc2626'; ?>;">₱<?php echo number_format($bal_amt, 2); ?></span>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Customer Acknowledgment Box for Credit Terms -->
                                            <div style="background: #ffffff; border: 1px dashed #cbd5e1; border-radius: 6px; padding: 8px 12px; margin-bottom: 6px; font-size: 11.5px; color: #475569;">
                                                <div style="font-weight: 700; color: #1e293b; margin-bottom: 2px;">Credit Terms &amp; Acknowledgment:</div>
                                                <div>I hereby acknowledge receipt of the above merchandise in good order, and agree to settle the balance of <strong>₱<?php echo number_format($bal_amt, 2); ?></strong> on or before <strong><?php echo date('M d, Y', strtotime($due_date)); ?></strong>.</div>
                                                <div style="margin-top: 12px; display: flex; justify-content: space-between; align-items: flex-end;">
                                                    <div style="border-top: 1px solid #64748b; width: 45%; text-align: center; padding-top: 2px; font-size: 10.5px;">
                                                        Customer Signature / Date
                                                    </div>
                                                    <div style="border-top: 1px solid #64748b; width: 45%; text-align: center; padding-top: 2px; font-size: 10.5px;">
                                                        Authorized Store Representative
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
                                                        <div style="font-size: 10pt; font-weight: bold; text-transform: uppercase;"><?php echo $is_settled ? '(PAID / SETTLED)' : '(ON CREDIT / TERMS)'; ?></div>
                                                    </div>
                                                    <div style="text-align: center; font-weight: bold; overflow: hidden; white-space: nowrap;">================================</div>
                                                    <table style="width: 100%; border-collapse: collapse; font-family: inherit; font-size: 10.5pt; margin-bottom: 2px;">
                                                        <tr><td style="width: 28%; font-weight: bold;">PO NO   :</td><td style="font-weight: bold;"><?php echo htmlspecialchars($row['txnid'] ?: $pay_id); ?></td></tr>
                                                        <tr><td style="font-weight: bold;">CUSTOMER:</td><td><?php echo htmlspecialchars($cust_name); ?></td></tr>
                                                        <?php if (!empty($cust_phone)): ?>
                                                        <tr><td style="font-weight: bold;">PHONE   :</td><td><?php echo htmlspecialchars($cust_phone); ?></td></tr>
                                                        <?php endif; ?>
                                                        <tr><td style="font-weight: bold;">TERMS   :</td><td><?php echo htmlspecialchars($row['payment_method']); ?></td></tr>
                                                        <tr><td style="font-weight: bold;">DUE DATE:</td><td style="font-weight: bold;"><?php echo date('Y-m-d', strtotime($due_date)); ?></td></tr>
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
                                                        </tbody>
                                                    </table>
                                                    <div style="text-align: center; overflow: hidden; white-space: nowrap;">--------------------------------</div>
                                                    <table style="width: 100%; border-collapse: collapse; font-family: inherit; font-size: 10.5pt; margin: 2px 0;">
                                                        <tr><td>Subtotal:</td><td style="text-align: right;"><?php echo number_format($calc_subtotal, 2); ?></td></tr>
                                                        <?php if ($row_credit_markup > 0): ?>
                                                        <tr><td>Credit Surcharge<?php echo !empty($row_credit_markup_label) ? ' (' . htmlspecialchars($row_credit_markup_label) . ')' : ''; ?>:</td><td style="text-align: right;">+<?php echo number_format($row_credit_markup, 2); ?></td></tr>
                                                        <?php endif; ?>
                                                        <?php if ($row_delivery_fee > 0): ?>
                                                        <tr><td>Delivery Fee:</td><td style="text-align: right;"><?php echo number_format($row_delivery_fee, 2); ?></td></tr>
                                                        <?php endif; ?>
                                                        <tr style="font-weight: bold;"><td>Total Invoiced:</td><td style="text-align: right;">PHP <?php echo number_format($total_amt, 2); ?></td></tr>
                                                        <tr><td>Paid to Date:</td><td style="text-align: right;">PHP <?php echo number_format($paid_amt, 2); ?></td></tr>
                                                        <tr style="font-weight: bold; font-size: 1.08em;"><td style="font-size: 1.05em;">BALANCE DUE:</td><td style="text-align: right; font-size: 1.05em;">PHP <?php echo number_format($bal_amt, 2); ?></td></tr>
                                                    </table>
                                                    <div style="text-align: center; font-weight: bold; overflow: hidden; white-space: nowrap;">================================</div>
                                                    <div style="text-align: left; font-size: 9pt; line-height: 1.25; margin: 4px 0;">
                                                        <div>Terms Due: <?php echo date('Y-m-d', strtotime($due_date)); ?></div>
                                                        <div style="margin-top: 15px; border-top: 1px dashed #000; padding-top: 3px; text-align: center;">Customer Signature</div>
                                                    </div>
                                                    <div style="text-align: center; overflow: hidden; white-space: nowrap;">--------------------------------</div>
                                                    <div style="text-align: center; line-height: 1.35; padding: 2px 0;">
                                                        <?php if ($is_settled): ?>
                                                            <div style="font-weight: bold;">*** ACCOUNT SETTLED ***</div>
                                                        <?php else: ?>
                                                            <div style="font-weight: bold; font-size: 10.5pt; letter-spacing: 0.5px;">*** CREDIT PURCHASE ORDER ***</div>
                                                        <?php endif; ?>
                                                        <div style="margin-top: 3px;">Thank you for your business!</div>
                                                        <div style="font-size: 9pt; margin-top: 2px;">eConstruction Supply POS</div>
                                                    </div>
                                                    <div style="text-align: center; font-weight: bold; overflow: hidden; white-space: nowrap;">================================</div>
                                                </div>
                                            </div>

                                        </div>

                                        <div class="modal-footer" style="background: #f1f5f9; border-top: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; padding: 10px 18px;">
                                            <button type="button" class="btn btn-default btn-sm" data-dismiss="modal" style="font-weight: 700; border-radius: 5px;">Close</button>
                                            <div style="display: flex; gap: 8px;">
                                                <button type="button" class="btn btn-primary btn-sm" onclick="printPOVoucher('<?php echo $row['id']; ?>')" style="font-weight: 700; background-color: #0284c7; border-color: #0369a1; border-radius: 5px; padding: 6px 16px;" title="Print Voucher on Thermal Printer">
                                                    <i class="fa fa-print"></i> Print Purchase Order Voucher
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
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
<!-- MODAL: Adjust Credit Terms & Due Date -->
<!-- ============================================================== -->
<div class="modal fade" id="adjustTermsModal" tabindex="-1" role="dialog" aria-labelledby="adjustTermsModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document" style="max-width: 540px;">
        <div class="modal-content" style="border-radius: 10px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.25);">
            <form id="adjustTermsForm" onsubmit="handleAdjustTermsSubmit(event)">
                <input type="hidden" name="action" value="adjust_credit_terms">
                <input type="hidden" name="payment_id" id="adjustTermsPaymentId">

                <div class="modal-header" style="background: #0f172a; color: #fff; padding: 14px 18px;">
                    <button type="button" class="close" data-dismiss="modal" style="color: #fff; opacity: 0.9;">&times;</button>
                    <h4 class="modal-title" id="adjustTermsModalLabel" style="font-weight: 800; font-size: 16px; margin: 0; display: flex; align-items: center; gap: 8px;">
                        <i class="fa fa-calendar-check-o text-warning"></i> Adjust Credit Terms &amp; Due Date
                    </h4>
                </div>

                <div class="modal-body" style="padding: 18px 20px; background: #fff;">
                    <!-- Account Overview Card -->
                    <div style="background: #f8fafc; border: 1.5px solid #e2e8f0; border-radius: 8px; padding: 12px 14px; margin-bottom: 16px;">
                        <div style="display: flex; justify-content: space-between; font-size: 12.5px; margin-bottom: 4px;">
                            <span style="color: #64748b;">Invoice / PO #:</span>
                            <strong style="font-family: monospace; color: #1e3a8a;" id="adjustTermsInvoiceDisplay"></strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; font-size: 12.5px; margin-bottom: 4px;">
                            <span style="color: #64748b;">Customer Name:</span>
                            <strong style="color: #0f172a;" id="adjustTermsCustomerDisplay"></strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; font-size: 12.5px; margin-bottom: 4px;">
                            <span style="color: #64748b;">Order Date:</span>
                            <strong style="color: #0f172a;" id="adjustTermsOrderDateDisplay"></strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; font-size: 13.5px; font-weight: 800; border-top: 1px dashed #cbd5e1; padding-top: 6px; margin-top: 6px;">
                            <span style="color: #dc2626;">Outstanding Balance:</span>
                            <span style="color: #dc2626;" id="adjustTermsBalanceDisplay">₱0.00</span>
                        </div>
                    </div>

                    <!-- Term Presets -->
                    <div class="form-group" style="margin-bottom: 14px;">
                        <label style="font-size: 12.5px; font-weight: 700; color: #334155; margin-bottom: 6px; display: block;">
                            <i class="fa fa-clock-o text-primary"></i> Quick Term Presets:
                        </label>
                        <div style="display: flex; flex-wrap: wrap; gap: 6px;" id="termPresetsContainer">
                            <button type="button" class="btn btn-default btn-sm term-preset-btn" onclick="applyTermPreset(7)">7 Days</button>
                            <button type="button" class="btn btn-default btn-sm term-preset-btn" onclick="applyTermPreset(15)">15 Days</button>
                            <button type="button" class="btn btn-default btn-sm term-preset-btn" onclick="applyTermPreset(21)">21 Days</button>
                            <button type="button" class="btn btn-default btn-sm term-preset-btn" onclick="applyTermPreset(30)">30 Days</button>
                            <button type="button" class="btn btn-default btn-sm term-preset-btn" onclick="applyTermPreset(45)">45 Days</button>
                            <button type="button" class="btn btn-default btn-sm term-preset-btn" onclick="applyTermPreset(60)">60 Days</button>
                            <button type="button" class="btn btn-default btn-sm term-preset-btn" onclick="applyTermPreset(90)">90 Days</button>
                        </div>
                    </div>

                    <!-- Due Date & Terms Label Inputs -->
                    <div class="row">
                        <div class="col-sm-6 col-xs-12">
                            <div class="form-group" style="margin-bottom: 14px;">
                                <label style="font-size: 12.5px; font-weight: 700; color: #334155; margin-bottom: 4px;">
                                    <i class="fa fa-calendar text-danger"></i> Due Date Override:
                                </label>
                                <input type="date" name="due_date" id="adjustTermsDueDate" class="form-control" style="height: 38px; font-weight: 700; color: #0f172a;" required onchange="onDueDateManualChange()">
                            </div>
                        </div>
                        <div class="col-sm-6 col-xs-12">
                            <div class="form-group" style="margin-bottom: 14px;">
                                <label style="font-size: 12.5px; font-weight: 700; color: #334155; margin-bottom: 4px;">
                                    <i class="fa fa-credit-card text-info"></i> Terms / Method Label:
                                </label>
                                <input type="text" name="payment_method" id="adjustTermsMethod" class="form-control" style="height: 38px; font-weight: 600;" placeholder="e.g. Check / Terms (30 Days)" required>
                            </div>
                        </div>
                    </div>

                    <!-- Check / PDC Reference # -->
                    <div class="form-group" style="margin-bottom: 14px;">
                        <label style="font-size: 12.5px; font-weight: 700; color: #334155; margin-bottom: 4px;">
                            <i class="fa fa-tag text-info"></i> Check / PDC Reference # (Optional):
                        </label>
                        <div class="input-group">
                            <span class="input-group-addon"><i class="fa fa-id-card-o"></i></span>
                            <input type="text" name="card_number" id="adjustTermsCheckRef" class="form-control" placeholder="e.g. PDC-20261025-01 / Bank Check #" style="height: 38px;">
                        </div>
                    </div>

                    <!-- Adjustment Reason / Note -->
                    <div class="form-group" style="margin-bottom: 14px;">
                        <label style="font-size: 12.5px; font-weight: 700; color: #334155; margin-bottom: 4px;">
                            <i class="fa fa-pencil-square-o text-muted"></i> Reason for Adjustment / Note (Optional):
                        </label>
                        <textarea name="adjustment_notes" id="adjustTermsNotes" class="form-control" rows="2" placeholder="e.g. Extended payment terms upon customer request approved by Store Manager" style="font-size: 12.5px; resize: vertical;"></textarea>
                    </div>

                    <!-- Live Calculation Preview Card -->
                    <div id="adjustTermsPreviewBox" style="background: #f8fafc; border: 1.5px solid #cbd5e1; border-radius: 8px; padding: 10px 14px; margin-bottom: 0; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
                        <div>
                            <div style="font-size: 11px; text-transform: uppercase; font-weight: 800; color: #64748b;">Terms Preview</div>
                            <div style="font-size: 13.5px; font-weight: 700; color: #0f172a;" id="adjustTermsCalculatedDaysText">--</div>
                        </div>
                        <div style="text-align: right;">
                            <div style="font-size: 11px; text-transform: uppercase; font-weight: 800; color: #64748b;">Aging Status</div>
                            <div id="adjustTermsAgingBadgePreview"></div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer" style="background: #f8fafc; border-top: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
                    <button type="button" class="btn btn-default" data-dismiss="modal" style="font-weight: 600;">Cancel</button>
                    <button type="submit" id="adjustTermsSubmitBtn" class="btn btn-primary" style="font-weight: 800; padding: 8px 18px; background: #2563eb; border-color: #1d4ed8;">
                        <i class="fa fa-save"></i> Save Terms &amp; Due Date
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

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
                    <div style="background: #f8fafc; border: 1.5px solid #e2e8f0; border-radius: 8px; padding: 12px 14px; margin-bottom: 12px;">
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

                    <!-- Settle at POS Register Shortcut Card -->
                    <div style="background: #e0f2fe; border: 1.5px solid #bae6fd; border-radius: 8px; padding: 10px 14px; margin-bottom: 14px; display: flex; justify-content: space-between; align-items: center; gap: 10px;">
                        <div>
                            <strong style="color: #0369a1; font-size: 13px;"><i class="fa fa-desktop"></i> Settle at POS Cash Register</strong>
                            <div style="font-size: 11.5px; color: #0284c7; line-height: 1.3;">Tender cash, calculate change, or print thermal collection receipt via POS register terminal.</div>
                        </div>
                        <button type="button" class="btn btn-primary btn-sm" onclick="sendSettleToPOSRegister()" style="font-weight: 800; background-color: #0284c7; border-color: #0369a1; white-space: nowrap; border-radius: 5px; box-shadow: 0 2px 6px rgba(2, 132, 199, 0.3);">
                            <i class="fa fa-shopping-cart"></i> Send to Register &rarr;
                        </button>
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

                <div class="modal-footer" style="background: #f8fafc; border-top: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
                    <button type="button" class="btn btn-default" data-dismiss="modal" style="font-weight: 600;">Cancel</button>
                    <div style="display: flex; gap: 8px;">
                        <button type="button" class="btn btn-primary" onclick="sendSettleToPOSRegister()" style="font-weight: 800; padding: 8px 16px; background-color: #0284c7; border-color: #0369a1; border-radius: 5px;">
                            <i class="fa fa-shopping-cart"></i> Send to POS Register
                        </button>
                        <button type="submit" id="settleSubmitBtn" class="btn btn-success" style="font-weight: 800; padding: 8px 18px; border-radius: 5px;">
                            <i class="fa fa-check-circle"></i> Quick Record Payment
                        </button>
                    </div>
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

function sendSettleToPOSRegister() {
    const paymentId = document.getElementById('settleModalPaymentId').value;
    const amountInput = document.getElementById('settleModalAmount');
    let amountVal = parseFloat(amountInput ? amountInput.value : currentSettleMaxBalance);

    if (!paymentId) {
        alert('Invalid Payment / Invoice ID.');
        return;
    }
    if (isNaN(amountVal) || amountVal <= 0) {
        amountVal = currentSettleMaxBalance;
    }
    if (amountVal > (currentSettleMaxBalance + 0.01)) {
        alert('Settlement amount (₱' + amountVal.toFixed(2) + ') cannot exceed outstanding balance (₱' + currentSettleMaxBalance.toFixed(2) + ').');
        return;
    }

    const targetUrl = 'pos.php?settle_credit=1&payment_id=' + encodeURIComponent(paymentId) + '&settle_amount=' + encodeURIComponent(amountVal.toFixed(2));
    window.location.href = targetUrl;
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
            const isZeroAdj = (parseFloat(h.amount_paid) === 0);
            html += `
                <tr>
                    <td style="padding: 10px 12px; font-weight: 700; color: #64748b;">${idx + 1}</td>
                    <td style="padding: 10px 12px;">${escapeHtml(h.payment_date)}</td>
                    <td style="padding: 10px 12px;">
                        <strong>${escapeHtml(h.payment_method)}</strong>
                        ${h.reference_no ? `<div style="font-size: 11px; color: #64748b;"><i class="fa fa-tag"></i> Ref: ${escapeHtml(h.reference_no)}</div>` : ''}
                        ${h.notes ? `<div style="font-size: 11px; color: #0369a1; margin-top: 3px; font-style: italic;"><i class="fa fa-info-circle"></i> ${escapeHtml(h.notes)}</div>` : ''}
                    </td>
                    <td style="padding: 10px 12px; text-align: right; font-weight: 800; color: ${!isZeroAdj ? '#059669' : '#64748b'};">
                        ${!isZeroAdj ? '₱' + parseFloat(h.amount_paid).toFixed(2) : '<span class="badge" style="background: #f1f5f9; color: #475569; font-weight: 700;">Terms Update</span>'}
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

// ==============================================================
// Credit Terms & Due Date Adjustment Handlers
// ==============================================================
let currentAdjustTermsOrderDate = '';
let currentAdjustTermsPaymentId = '';

function openAdjustTermsFromElement(el) {
    if (!el) return;
    const data = {
        payment_id: el.getAttribute('data-payment-id') || '',
        customer_name: el.getAttribute('data-customer-name') || '',
        order_date: el.getAttribute('data-order-date') || '',
        order_date_formatted: el.getAttribute('data-order-date-formatted') || '',
        payment_method: el.getAttribute('data-payment-method') || '',
        check_ref: el.getAttribute('data-check-ref') || '',
        due_date: el.getAttribute('data-due-date') || '',
        balance: parseFloat(el.getAttribute('data-balance') || 0)
    };
    openAdjustTermsModal(data);
}

function openAdjustTermsModal(data) {
    const canUserAdjustTerms = <?php echo $can_adjust_terms ? 'true' : 'false'; ?>;
    if (!canUserAdjustTerms) {
        alert('Permission denied. Only Supplier Admin and Store Manager are authorized to adjust credit terms and due dates.');
        return;
    }
    currentAdjustTermsPaymentId = data.payment_id;
    currentAdjustTermsOrderDate = data.order_date;

    document.getElementById('adjustTermsPaymentId').value = data.payment_id;
    document.getElementById('adjustTermsInvoiceDisplay').innerText = data.payment_id;
    document.getElementById('adjustTermsCustomerDisplay').innerText = data.customer_name;
    document.getElementById('adjustTermsOrderDateDisplay').innerText = data.order_date_formatted || data.order_date;
    document.getElementById('adjustTermsBalanceDisplay').innerText = '₱' + (data.balance || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    
    document.getElementById('adjustTermsDueDate').value = data.due_date;
    document.getElementById('adjustTermsMethod').value = data.payment_method;
    document.getElementById('adjustTermsCheckRef').value = data.check_ref || '';
    document.getElementById('adjustTermsNotes').value = '';

    // Calculate diff and highlight matching preset if any
    const diffDays = getDaysDiff(currentAdjustTermsOrderDate, data.due_date);
    highlightPresetButton(diffDays);
    updateTermsPreview();

    $('#adjustTermsModal').modal('show');
}

function applyTermPreset(days) {
    if (!currentAdjustTermsOrderDate) return;
    const newDueDateStr = addDaysToDateStr(currentAdjustTermsOrderDate, days);
    document.getElementById('adjustTermsDueDate').value = newDueDateStr;
    
    const currentMethod = document.getElementById('adjustTermsMethod').value;
    if (!currentMethod || /Check \/ Terms \(\d+\s*Days\)/i.test(currentMethod) || /Terms/i.test(currentMethod)) {
        document.getElementById('adjustTermsMethod').value = `Check / Terms (${days} Days)`;
    }

    highlightPresetButton(days);
    updateTermsPreview();
}

function onDueDateManualChange() {
    const dueVal = document.getElementById('adjustTermsDueDate').value;
    if (!dueVal || !currentAdjustTermsOrderDate) return;

    const diffDays = getDaysDiff(currentAdjustTermsOrderDate, dueVal);

    if (diffDays >= 0) {
        const currentMethod = document.getElementById('adjustTermsMethod').value;
        if (!currentMethod || /Check \/ Terms \(\d+\s*Days\)/i.test(currentMethod)) {
            document.getElementById('adjustTermsMethod').value = `Check / Terms (${diffDays} Days)`;
        }
        highlightPresetButton(diffDays);
    } else {
        highlightPresetButton(null);
    }

    updateTermsPreview();
}

function highlightPresetButton(activeDays) {
    const buttons = document.querySelectorAll('#termPresetsContainer .term-preset-btn');
    buttons.forEach(btn => {
        const text = btn.innerText.replace(/[^0-9]/g, '');
        if (activeDays !== null && activeDays !== undefined && parseInt(text, 10) === parseInt(activeDays, 10)) {
            btn.classList.add('active');
        } else {
            btn.classList.remove('active');
        }
    });
}

function updateTermsPreview() {
    const dueVal = document.getElementById('adjustTermsDueDate').value;
    const previewText = document.getElementById('adjustTermsCalculatedDaysText');
    const previewBadge = document.getElementById('adjustTermsAgingBadgePreview');
    if (!dueVal || !currentAdjustTermsOrderDate || !previewText || !previewBadge) return;

    const diffDays = getDaysDiff(currentAdjustTermsOrderDate, dueVal);
    const agingDays = getAgingFromToday(dueVal);

    const formattedOrderDate = formatShortDate(currentAdjustTermsOrderDate);
    previewText.innerText = `${diffDays} Day${diffDays === 1 ? '' : 's'} from ${formattedOrderDate}`;

    if (agingDays < 0) {
        const absDays = Math.abs(agingDays);
        previewBadge.innerHTML = `<span class="badge" style="background: #ef4444; color: #fff; font-weight: 800;"><i class="fa fa-exclamation-triangle"></i> ${absDays} Day${absDays > 1 ? 's' : ''} Overdue</span>`;
    } else if (agingDays === 0) {
        previewBadge.innerHTML = `<span class="badge" style="background: #f59e0b; color: #fff; font-weight: 800;"><i class="fa fa-clock-o"></i> Due Today</span>`;
    } else if (agingDays <= 7) {
        previewBadge.innerHTML = `<span class="badge" style="background: #f59e0b; color: #fff; font-weight: 700;"><i class="fa fa-calendar"></i> Due in ${agingDays} Day${agingDays > 1 ? 's' : ''}</span>`;
    } else {
        previewBadge.innerHTML = `<span class="badge" style="background: #10b981; color: #fff; font-weight: 700;"><i class="fa fa-calendar-check-o"></i> Due in ${agingDays} Days</span>`;
    }
}

function addDaysToDateStr(dateStr, days) {
    const parts = dateStr.split('-');
    if (parts.length !== 3) return dateStr;
    const d = new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10));
    d.setDate(d.getDate() + parseInt(days, 10));
    const yyyy = d.getFullYear();
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    const dd = String(d.getDate()).padStart(2, '0');
    return `${yyyy}-${mm}-${dd}`;
}

function getDaysDiff(startDateStr, endDateStr) {
    const p1 = startDateStr.split('-');
    const p2 = endDateStr.split('-');
    if (p1.length !== 3 || p2.length !== 3) return 0;
    const d1 = new Date(parseInt(p1[0], 10), parseInt(p1[1], 10) - 1, parseInt(p1[2], 10));
    const d2 = new Date(parseInt(p2[0], 10), parseInt(p2[1], 10) - 1, parseInt(p2[2], 10));
    return Math.round((d2 - d1) / (86400 * 1000));
}

function getAgingFromToday(dueDateStr) {
    const now = new Date();
    const todayStr = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;
    return getDaysDiff(todayStr, dueDateStr);
}

function formatShortDate(dateStr) {
    const parts = dateStr.split('-');
    if (parts.length !== 3) return dateStr;
    const d = new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10));
    return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

function handleAdjustTermsSubmit(event) {
    event.preventDefault();
    const btn = document.getElementById('adjustTermsSubmitBtn');
    const origText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Saving...';

    const formData = new FormData(document.getElementById('adjustTermsForm'));

    fetch('on-credit.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(res => {
        btn.disabled = false;
        btn.innerHTML = origText;

        if (!res.success) {
            alert(res.message || 'Failed to update credit terms.');
            return;
        }

        $('#adjustTermsModal').modal('hide');

        const payId = res.payment_id;

        // 1. Update Terms Badge in Date column
        const badgeEl = document.getElementById('termsBadge-' + payId);
        if (badgeEl) {
            badgeEl.setAttribute('data-payment-method', res.payment_method);
            badgeEl.setAttribute('data-due-date', res.due_date);
            badgeEl.setAttribute('data-check-ref', res.card_number || '');
            const badgeText = badgeEl.querySelector('.term-badge-text');
            if (badgeText) {
                badgeText.innerText = res.payment_method;
            }
            // Flash animation
            badgeEl.style.transition = 'all 0.3s ease';
            badgeEl.style.backgroundColor = '#bbf7d0';
            badgeEl.style.color = '#166534';
            badgeEl.style.borderColor = '#86efac';
            setTimeout(() => {
                badgeEl.style.backgroundColor = '#fef3c7';
                badgeEl.style.color = '#92400e';
                badgeEl.style.borderColor = '#fde68a';
            }, 1800);
        }

        // 2. Update Due Date and Aging badge
        const dueCell = document.getElementById('dueDateCell-' + payId);
        if (dueCell) {
            const dueText = dueCell.querySelector('.due-date-text');
            const agingCont = dueCell.querySelector('.aging-badge-container');
            if (dueText) dueText.innerText = 'Due: ' + res.formatted_due_date;
            if (agingCont) agingCont.innerHTML = res.aging_badge_html;
            
            // Flash animation
            dueCell.style.transition = 'background-color 0.4s ease';
            dueCell.style.backgroundColor = '#f0fdf4';
            setTimeout(() => { dueCell.style.backgroundColor = ''; }, 1800);
        }

        // 3. Update Check Ref badge in Invoice column
        const refEl = document.getElementById('checkRefBadge-' + payId);
        if (refEl) {
            if (res.card_number && res.card_number.trim() !== '') {
                refEl.innerHTML = '<i class="fa fa-tag"></i> ' + escapeHtml(res.card_number);
                refEl.style.display = 'inline-block';
            }
        }

        // 4. Show sleek dismissible banner notice
        showTermsNotice(res.message || 'Credit terms updated successfully!', 'success');
    })
    .catch(err => {
        btn.disabled = false;
        btn.innerHTML = origText;
        alert('Network error: ' + err.message);
    });
}

function showTermsNotice(msg, type) {
    const area = document.getElementById('creditAlertNoticeArea');
    if (!area) return;
    const isSuccess = (type === 'success');
    const alertHtml = `
        <div class="alert alert-${isSuccess ? 'success' : 'danger'} alert-dismissible" style="border-radius: 8px; box-shadow: 0 2px 6px rgba(0,0,0,0.08); font-size: 13.5px; font-weight: 600; display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; padding: 12px 16px;">
            <div style="display: flex; align-items: center; gap: 8px;">
                <i class="fa ${isSuccess ? 'fa-check-circle' : 'fa-exclamation-triangle'}" style="font-size: 17px;"></i>
                <span>${escapeHtml(msg)}</span>
            </div>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close" style="opacity: 0.8; font-size: 20px; line-height: 1;">&times;</button>
        </div>
    `;
    area.innerHTML = alertHtml;
    setTimeout(() => {
        const el = area.querySelector('.alert');
        if (el) {
            $(el).fadeOut(600, function() { area.innerHTML = ''; });
        }
    }, 4500);
}

// ==============================================================
// Inline Customer Name Edit Handlers (Supplier Admin Only)
// ==============================================================
function startInlineCustomerEdit(payId) {
    const isSupplierAdmin = <?php echo $is_supplier_admin ? 'true' : 'false'; ?>;
    if (!isSupplierAdmin) {
        alert('Permission denied. Only Supplier Admin is authorized to edit customer names.');
        return;
    }
    const displayEl = document.getElementById('custNameDisplay-' + payId);
    const editEl = document.getElementById('custNameEdit-' + payId);
    const inputEl = document.getElementById('custNameInput-' + payId);

    if (displayEl && editEl && inputEl) {
        displayEl.style.display = 'none';
        editEl.style.display = 'block';
        inputEl.focus();
        inputEl.select();
    }
}

function cancelInlineCustomerEdit(payId) {
    const displayEl = document.getElementById('custNameDisplay-' + payId);
    const editEl = document.getElementById('custNameEdit-' + payId);
    const inputEl = document.getElementById('custNameInput-' + payId);

    if (displayEl && editEl && inputEl) {
        const textEl = displayEl.querySelector('.cust-name-text');
        if (textEl) {
            inputEl.value = textEl.innerText.trim();
        }
        editEl.style.display = 'none';
        displayEl.style.display = 'flex';
    }
}

function handleCustomerNameKeyDown(event, payId) {
    if (event.key === 'Enter') {
        event.preventDefault();
        saveInlineCustomerName(payId);
    } else if (event.key === 'Escape') {
        event.preventDefault();
        cancelInlineCustomerEdit(payId);
    }
}

function saveInlineCustomerName(payId) {
    const isSupplierAdmin = <?php echo $is_supplier_admin ? 'true' : 'false'; ?>;
    if (!isSupplierAdmin) {
        alert('Permission denied. Only Supplier Admin is authorized to edit customer names.');
        return;
    }

    const inputEl = document.getElementById('custNameInput-' + payId);
    const saveBtn = document.getElementById('custNameSaveBtn-' + payId);
    if (!inputEl) return;

    const newName = inputEl.value.trim();
    if (!newName) {
        alert('Customer name cannot be empty.');
        inputEl.focus();
        return;
    }

    let origBtnContent = '';
    if (saveBtn) {
        origBtnContent = saveBtn.innerHTML;
        saveBtn.disabled = true;
        saveBtn.innerHTML = '<i class="fa fa-spinner fa-spin"></i>';
    }

    const formData = new FormData();
    formData.append('action', 'update_customer_name');
    formData.append('payment_id', payId);
    formData.append('customer_name', newName);

    fetch('on-credit.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(res => {
        if (saveBtn) {
            saveBtn.disabled = false;
            saveBtn.innerHTML = origBtnContent;
        }

        if (!res.success) {
            alert(res.message || 'Failed to update customer name.');
            return;
        }

        const displayEl = document.getElementById('custNameDisplay-' + payId);
        const editEl = document.getElementById('custNameEdit-' + payId);
        if (displayEl && editEl) {
            const textEl = displayEl.querySelector('.cust-name-text');
            if (textEl) {
                textEl.innerText = res.customer_name;
                textEl.style.transition = 'color 0.3s ease';
                textEl.style.color = '#059669';
                setTimeout(() => { textEl.style.color = '#0f172a'; }, 1800);
            }
            editEl.style.display = 'none';
            displayEl.style.display = 'flex';
        }

        // Update data-customer-name attribute on terms badge
        const termsBadge = document.getElementById('termsBadge-' + payId);
        if (termsBadge) {
            termsBadge.setAttribute('data-customer-name', res.customer_name);
        }

        // Update customer name inside PO modal if rendered
        const poModalCustomerEls = document.querySelectorAll('#po-credit-modal-' + payId + ' .po-customer-name, [data-po-customer="' + payId + '"]');
        poModalCustomerEls.forEach(el => { el.innerText = res.customer_name; });

        showTermsNotice(res.message || 'Customer name updated successfully!', 'success');
    })
    .catch(err => {
        if (saveBtn) {
            saveBtn.disabled = false;
            saveBtn.innerHTML = origBtnContent;
        }
        alert('Network error: ' + err.message);
    });
}

if (typeof window.printPOVoucher !== 'function') {
    window.printPOVoucher = function(poId, format) {
        const fmt = format || '58';
        const thermalEl = document.getElementById('po-printable-thermal-' + poId) ||
                          document.getElementById('po-printable-voucher-' + poId);
        if (window.POVoucherRenderer && typeof window.POVoucherRenderer.print === 'function' && thermalEl) {
            window.POVoucherRenderer.print(thermalEl, fmt);
            return;
        }
        if (thermalEl) {
            const printWin = window.open('', '_blank', 'width=450,height=600');
            if (printWin) {
                printWin.document.write('<html><head><title>Print Purchase Order Voucher</title><style>body{font-family:monospace;font-size:12px;padding:15px;}</style></head><body>' + thermalEl.innerHTML + '</body></html>');
                printWin.document.close();
                printWin.focus();
                setTimeout(function() { printWin.print(); printWin.close(); }, 350);
            } else {
                window.print();
            }
        }
    };
}
</script>

<?php require_once('footer.php'); ?>
