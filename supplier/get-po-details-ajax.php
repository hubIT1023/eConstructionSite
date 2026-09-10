<?php
ob_start();
session_start();
require_once('inc/config.php');
require_once('inc/functions.php');

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['supplier_user'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized session. Please log in.']);
    exit;
}

$supplier_id = (int)$_SESSION['supplier_user']['supplier_id'];
$payment_id = isset($_GET['payment_id']) ? trim($_GET['payment_id']) : (isset($_POST['payment_id']) ? trim($_POST['payment_id']) : '');

if (empty($payment_id)) {
    echo json_encode(['success' => false, 'message' => 'Missing Purchase Order / Payment ID.']);
    exit;
}

try {
    // 1. Fetch Payment Record
    $stmt_pay = $pdo->prepare("SELECT * FROM tbl_payment WHERE (payment_id = ? OR txnid = ?) AND supplier_id = ? LIMIT 1");
    $stmt_pay->execute([$payment_id, $payment_id, $supplier_id]);
    $payment = $stmt_pay->fetch(PDO::FETCH_ASSOC);

    if (!$payment) {
        echo json_encode(['success' => false, 'message' => 'Purchase Order not found or unauthorized.']);
        exit;
    }

    // 2. Fetch Supplier Store Profile
    $stmt_sup = $pdo->prepare("SELECT * FROM tbl_supplier WHERE supplier_id = ? LIMIT 1");
    $stmt_sup->execute([$supplier_id]);
    $supplier = $stmt_sup->fetch(PDO::FETCH_ASSOC);
    $store_name = $supplier['supplier_name'] ?? 'E-Construction Supply Store';
    $store_address = $supplier['supplier_address'] ?? '';
    $store_phone = $supplier['supplier_phone'] ?? '';
    $store_email = $supplier['supplier_email'] ?? '';

    // 3. Fetch Order Items
    $stmt_items = $pdo->prepare("SELECT * FROM tbl_order WHERE payment_id = ? AND supplier_id = ? ORDER BY id ASC");
    $stmt_items->execute([$payment['payment_id'], $supplier_id]);
    $items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

    // 4. Calculate Totals & Item Breakdown
    $computed_subtotal = 0.0;
    $computed_discount_total = 0.0;
    $items_data = [];

    foreach ($items as $idx => $it) {
        $qty = floatval($it['quantity'] ?? 1);
        $price = floatval($it['unit_price'] ?? 0);
        $disc_pct = floatval($it['discount_percent'] ?? 0);
        $disc_amt = floatval($it['discount_amount'] ?? 0);
        
        $line_gross = $qty * $price;
        if ($disc_amt > 0) {
            $line_disc = $disc_amt;
        } elseif ($disc_pct > 0) {
            $line_disc = round(($line_gross * ($disc_pct / 100.0)), 2);
        } else {
            $line_disc = 0.0;
        }
        $line_net = max(0.0, $line_gross - $line_disc);

        $computed_subtotal += $line_gross;
        $computed_discount_total += $line_disc;

        $items_data[] = [
            'item_no' => $idx + 1,
            'product_name' => $it['product_name'] ?? 'Custom Order Item',
            'quantity' => $qty,
            'unit_price' => $price,
            'size' => $it['size'] ?? '',
            'color' => $it['color'] ?? '',
            'item_type' => $it['item_type'] ?? '',
            'special_order_reference' => $it['special_order_reference'] ?? '',
            'discount_percent' => $disc_pct,
            'discount_amount' => $line_disc,
            'line_gross' => $line_gross,
            'line_net' => $line_net
        ];
    }

    $paid_amount = floatval($payment['paid_amount'] ?? 0);
    $final_total = ($paid_amount > 0) ? $paid_amount : max(0.0, $computed_subtotal - $computed_discount_total);

    $formatted_date = !empty($payment['payment_date']) ? date('d/m/Y h:i A', strtotime($payment['payment_date'])) : date('d/m/Y h:i A');
    $formatted_date_short = !empty($payment['payment_date']) ? date('d/m/Y', strtotime($payment['payment_date'])) : date('d/m/Y');

    echo json_encode([
        'success' => true,
        'po_number' => $payment['payment_id'],
        'date' => $formatted_date,
        'date_short' => $formatted_date_short,
        'status' => $payment['payment_status'] ?? 'Awaiting for Payment',
        'is_paid' => (strtolower($payment['payment_status'] ?? '') === 'paid'),
        'payment_method' => $payment['payment_method'] ?? 'Over the Counter / POS PO',
        'customer' => [
            'name' => $payment['customer_name'] ?? 'Walk-in Customer',
            'email' => $payment['customer_email'] ?? '',
            'phone' => $payment['customer_phone'] ?? '',
            'address' => $payment['customer_address'] ?? ''
        ],
        'supplier' => [
            'store_name' => $store_name,
            'address' => $store_address,
            'phone' => $store_phone,
            'email' => $store_email
        ],
        'items' => $items_data,
        'summary' => [
            'subtotal' => $computed_subtotal,
            'discount_total' => $computed_discount_total,
            'total_amount' => $final_total,
            'item_count' => count($items_data)
        ]
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
exit;
