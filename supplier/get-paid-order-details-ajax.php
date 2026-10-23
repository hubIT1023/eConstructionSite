<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once('inc/config.php');
require_once('inc/functions.php');

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['supplier_user'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized session. Please log in.']);
    exit;
}

$supplier_id = (int)$_SESSION['supplier_user']['supplier_id'];
$id = isset($_GET['id']) ? intval($_GET['id']) : (isset($_POST['id']) ? intval($_POST['id']) : 0);
$payment_id = isset($_GET['payment_id']) ? trim($_GET['payment_id']) : (isset($_POST['payment_id']) ? trim($_POST['payment_id']) : '');

if ($id <= 0 && empty($payment_id)) {
    echo json_encode(['success' => false, 'message' => 'Missing Order or Payment ID.']);
    exit;
}

try {
    // 1. Fetch Payment Record
    if ($id > 0) {
        $stmt_pay = $pdo->prepare("SELECT * FROM tbl_payment WHERE id = ? AND supplier_id = ? LIMIT 1");
        $stmt_pay->execute([$id, $supplier_id]);
    } else {
        $stmt_pay = $pdo->prepare("SELECT * FROM tbl_payment WHERE (payment_id = ? OR txnid = ?) AND supplier_id = ? LIMIT 1");
        $stmt_pay->execute([$payment_id, $payment_id, $supplier_id]);
    }
    $payment = $stmt_pay->fetch(PDO::FETCH_ASSOC);

    if (!$payment) {
        echo json_encode(['success' => false, 'message' => 'Order record not found or unauthorized.']);
        exit;
    }

    $pid = $payment['payment_id'];
    $txnid = !empty($payment['txnid']) ? $payment['txnid'] : $pid;

    // 2. Fetch Supplier Store Profile
    $stmt_sup = $pdo->prepare("SELECT * FROM tbl_supplier WHERE supplier_id = ? LIMIT 1");
    $stmt_sup->execute([$supplier_id]);
    $supplier = $stmt_sup->fetch(PDO::FETCH_ASSOC) ?: [];
    $store_name = !empty($supplier['supplier_name']) ? $supplier['supplier_name'] : 'SAM & INRI CONSTRUCTION SUPPLY';
    $store_address = $supplier['supplier_address'] ?? '';
    $store_phone = !empty($supplier['supplier_phone']) ? $supplier['supplier_phone'] : '09612735733';

    // 3. Fetch Customer Profile
    $cust_id = (int)($payment['customer_id'] ?? 0);
    $c_data = null;
    if ($cust_id > 0) {
        $stmt_c = $pdo->prepare("SELECT * FROM tbl_customer WHERE cust_id = ? LIMIT 1");
        $stmt_c->execute([$cust_id]);
        $c_data = $stmt_c->fetch(PDO::FETCH_ASSOC);
    }
    if (!$c_data && !empty($payment['customer_email'])) {
        $stmt_c2 = $pdo->prepare("SELECT * FROM tbl_customer WHERE cust_email = ? LIMIT 1");
        $stmt_c2->execute([$payment['customer_email']]);
        $c_data = $stmt_c2->fetch(PDO::FETCH_ASSOC);
    }

    $cust_phone = $c_data['cust_phone'] ?? $c_data['cust_b_phone'] ?? $c_data['cust_s_phone'] ?? '';
    $cust_parts = array_filter([
        $c_data['cust_s_address'] ?? $c_data['cust_b_address'] ?? $c_data['cust_address'] ?? '',
        $c_data['cust_s_state'] ?? $c_data['cust_b_state'] ?? $c_data['cust_state'] ?? '',
        $c_data['cust_s_city'] ?? $c_data['cust_b_city'] ?? $c_data['cust_city'] ?? ''
    ]);
    $full_cust_address = implode(', ', $cust_parts);

    // 4. Fetch Order Items
    $stmt_items = $pdo->prepare("SELECT o.*, p.p_sku FROM tbl_order o LEFT JOIN tbl_product p ON o.product_id = p.p_id WHERE o.payment_id = ? ORDER BY o.id ASC");
    $stmt_items->execute([$pid]);
    $order_items_raw = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

    $items_list = [];
    $subtotal_items = 0.0;

    foreach ($order_items_raw as $item) {
        $qty = intval($item['quantity'] ?? 1);
        $price = floatval($item['unit_price'] ?? 0);
        $line_subtotal = $qty * $price;
        $subtotal_items += $line_subtotal;

        $specs_arr = [];
        if (!empty($item['size']) && $item['size'] !== '-') $specs_arr[] = 'Size: ' . $item['size'];
        if (!empty($item['color']) && $item['color'] !== '-') $specs_arr[] = 'Color: ' . $item['color'];
        $sku_code = !empty($item['p_sku']) ? $item['p_sku'] : (($item['product_id'] ?? 0) > 0 ? ('SKU-' . str_pad($item['product_id'], 4, '0', STR_PAD_LEFT)) : 'N/A');

        $items_list[] = [
            'name' => $item['product_name'] ?? 'Product Item',
            'sku' => $sku_code,
            'specs' => !empty($specs_arr) ? implode(' | ', $specs_arr) : 'Standard',
            'size' => $item['size'] ?? '-',
            'color' => $item['color'] ?? '-',
            'qty' => $qty,
            'price' => $price,
            'amount' => $line_subtotal,
            'is_special_order' => (isset($item['item_type']) && $item['item_type'] === 'SPECIAL_ORDER'),
            'details' => $item['product_details'] ?? '',
            'special_order_ref' => $item['special_order_reference'] ?? ''
        ];
    }

    $paid_amount = floatval($payment['paid_amount'] ?? 0);
    $delivery_cost = max(0.00, $paid_amount - $subtotal_items);

    // 5. Cashier Resolution
    $cashier_name = !empty($payment['cashier_name']) ? trim($payment['cashier_name']) : '';
    $info = !empty($payment['bank_transaction_info']) ? $payment['bank_transaction_info'] : '';
    if (empty($cashier_name)) {
        if (preg_match('/(?:Staff|Cashier|User|Handled by|Processed by):\s*([A-Za-z0-9\.\s\-_&]+?)(?:\||\(|\n|\r|$)/i', $info, $m)) {
            $cashier_name = trim($m[1]);
        } elseif (preg_match('/Sent by\s+([^:]+):\s*([A-Za-z0-9\.\s\-_&]+?)(?:\)|\||\n|\r|$)/i', $info, $m)) {
            $cashier_name = trim($m[2]);
        } elseif (preg_match('/by\s+([A-Za-z0-9\.\s\-_&]+?)\s+on\s+[0-9]{4}-[0-9]{2}-[0-9]{2}/i', $info, $m)) {
            $cashier_name = trim($m[1]);
        } else {
            $cashier_name = 'Counter Cashier';
        }
    }

    // 6. Credit terms / Tendered / Change calculation
    $is_credit = (strcasecmp($payment['payment_status'], 'On Credit') === 0 || (!empty($payment['credit_balance']) && floatval($payment['credit_balance']) > 0) || (isset($payment['payment_method']) && (stripos($payment['payment_method'], 'Terms') !== false || stripos($payment['payment_method'], 'Credit') !== false)));
    $tendered = null;
    $change = null;
    if (preg_match('/(?:Tendered|Cash Tendered):\s*(?:₱|PHP)?\s*([0-9,]+(?:\.[0-9]+)?)/i', $info, $tm)) {
        $tendered = floatval(str_replace(',', '', $tm[1]));
    }
    if (preg_match('/(?:Change):\s*(?:₱|PHP)?\s*([0-9,]+(?:\.[0-9]+)?)/i', $info, $cm)) {
        $change = floatval(str_replace(',', '', $cm[1]));
    }
    if ($tendered === null && !$is_credit) {
        $tendered = $paid_amount;
        $change = 0.00;
    }

    $amount_paid_calc = isset($payment['amount_paid']) ? floatval($payment['amount_paid']) : ($is_credit ? floatval($tendered ?? 0) : $paid_amount);
    $credit_balance_calc = isset($payment['credit_balance']) ? floatval($payment['credit_balance']) : ($is_credit ? max(0, $paid_amount - $amount_paid_calc) : 0.00);

    echo json_encode([
        'success' => true,
        'order' => [
            'id' => (int)$payment['id'],
            'payment_id' => $pid,
            'txnid' => $txnid,
            'payment_date' => $payment['payment_date'],
            'formatted_date' => date('M d, Y h:i A', strtotime($payment['payment_date'])),
            'customer_id' => $cust_id,
            'customer_name' => !empty($payment['customer_name']) ? $payment['customer_name'] : 'Walk-in Customer',
            'customer_email' => $payment['customer_email'] ?? '',
            'customer_phone' => $cust_phone,
            'customer_address' => $full_cust_address,
            'supplier_name' => $store_name,
            'supplier_phone' => $store_phone,
            'supplier_address' => $store_address,
            'cashier_name' => $cashier_name,
            'payment_method' => !empty($payment['payment_method']) ? $payment['payment_method'] : 'Cash',
            'payment_status' => $payment['payment_status'],
            'shipping_status' => $payment['shipping_status'],
            'bank_transaction_info' => $info,
            'subtotal' => $subtotal_items,
            'delivery' => $delivery_cost,
            'discount' => 0.00,
            'total' => $paid_amount,
            'amount_paid' => $amount_paid_calc,
            'credit_balance' => $credit_balance_calc,
            'credit_reference_no' => $payment['credit_reference_no'] ?? '',
            'due_date' => $payment['due_date'] ?? '',
            'payment_term_days' => $payment['payment_term_days'] ?? '',
            'tendered' => $tendered,
            'change' => $change,
            'items' => $items_list
        ]
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error querying order details: ' . $e->getMessage()]);
}
