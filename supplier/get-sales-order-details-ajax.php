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
        echo json_encode(['success' => false, 'message' => 'Order payment record not found or unauthorized.']);
        exit;
    }

    $pid = $payment['payment_id'];
    $txnid = !empty($payment['txnid']) ? $payment['txnid'] : $pid;

    // 2. Fetch Supplier Store Profile
    $stmt_sup = $pdo->prepare("SELECT * FROM tbl_supplier WHERE supplier_id = ? LIMIT 1");
    $stmt_sup->execute([$supplier_id]);
    $supplier = $stmt_sup->fetch(PDO::FETCH_ASSOC) ?: [];
    $store_name = $supplier['supplier_name'] ?? 'SAM & INRI CONSTRUCTION SUPPLY';
    $store_address = $supplier['supplier_address'] ?? '';
    $store_phone = $supplier['supplier_phone'] ?? '09612735733';

    // 3. Load Product Map for Capital / Markup Calculations
    $products_map = [];
    try {
        $stmt_prod_map = $pdo->prepare("SELECT p_id, p_name, p_current_price, p_new_price, p_capital_price, p_markup FROM tbl_product WHERE supplier_id = ?");
        $stmt_prod_map->execute([$supplier_id]);
        while ($prow = $stmt_prod_map->fetch(PDO::FETCH_ASSOC)) {
            $products_map[$prow['p_id']] = $prow;
        }
    } catch (Exception $e_prod) {}

    // 4. Fetch Order Items
    $stmt_items = $pdo->prepare("SELECT * FROM tbl_order WHERE payment_id = ? AND supplier_id = ? ORDER BY id ASC");
    $stmt_items->execute([$pid, $supplier_id]);
    $order_items_raw = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

    $items_list = [];
    $calc_subtotal = 0.0;
    $order_total_cost = 0.0;
    $order_total_profit = 0.0;

    foreach ($order_items_raw as $it) {
        $qty = intval($it['quantity'] ?? 1);
        $u_price = floatval($it['unit_price'] ?? 0);
        $item_type = $it['item_type'] ?? 'STANDARD';
        $p_id = intval($it['product_id'] ?? 0);
        $is_return_credit = ($item_type === 'RETURN_CREDIT' || $u_price < 0);
        $abs_unit_price = abs($u_price);
        $line_subtotal = $qty * $u_price;
        $calc_subtotal += $line_subtotal;

        // Resolve unit capital and markup
        $unit_capital = 0.0;
        $has_order_snapshot = false;

        if (isset($it['capital_price']) && $it['capital_price'] !== null && $it['capital_price'] !== '' && floatval($it['capital_price']) > 0) {
            $unit_capital = round(floatval($it['capital_price']), 2);
            $has_order_snapshot = true;
        } elseif (!empty($it['product_details']) && preg_match('/Capital:\s*(?:₱|PHP)?\s*([0-9,]+(?:\.[0-9]+)?)/i', (string)$it['product_details'], $cap_m)) {
            $parsed_cap = floatval(str_replace(',', '', $cap_m[1]));
            if ($parsed_cap > 0) {
                $unit_capital = round($parsed_cap, 2);
                $has_order_snapshot = true;
            }
        }

        if (!$has_order_snapshot) {
            if ($p_id > 0 && isset($products_map[$p_id])) {
                $p = $products_map[$p_id];
                $live_sell = isset($p['p_current_price']) ? floatval($p['p_current_price']) : 0.0;
                $live_cap = isset($p['p_capital_price']) ? floatval($p['p_capital_price']) : 0.0;
                $m = (isset($p['p_markup']) && $p['p_markup'] !== '') ? floatval($p['p_markup']) : 20.0;
                if ($m <= 0) $m = 20.0;

                if ($live_cap > 0 && $live_sell > 0 && abs($abs_unit_price - $live_sell) < 0.01) {
                    $unit_capital = $live_cap;
                } elseif ($live_cap > 0 && $live_sell > 0 && $abs_unit_price > 0) {
                    $ratio = $live_cap / $live_sell;
                    if ($ratio > 0 && $ratio < 1.0) {
                        $unit_capital = round($abs_unit_price * $ratio, 2);
                    } else {
                        $unit_capital = round($abs_unit_price / (1 + ($m / 100)), 2);
                    }
                } elseif ($live_cap > 0 && $live_cap <= $abs_unit_price) {
                    $unit_capital = $live_cap;
                } else {
                    $unit_capital = round($abs_unit_price / (1 + ($m / 100)), 2);
                }
            } else {
                $unit_capital = round($abs_unit_price / 1.20, 2);
            }
        }

        $total_item_capital = $unit_capital * $qty;
        if ($is_return_credit) {
            $item_profit = -abs(max(0.0, abs($line_subtotal) - $total_item_capital));
            $order_total_cost -= $total_item_capital;
        } else {
            $item_profit = max(0.0, $line_subtotal - $total_item_capital);
            $order_total_cost += $total_item_capital;
        }
        $order_total_profit += $item_profit;

        $items_list[] = [
            'product_name' => $it['product_name'] ?? 'Product Item',
            'size' => $it['size'] ?? '-',
            'color' => $it['color'] ?? '-',
            'quantity' => $qty,
            'unit_price' => $u_price,
            'unit_capital' => $unit_capital,
            'subtotal' => $line_subtotal,
            'profit' => $item_profit,
            'is_return_credit' => $is_return_credit
        ];
    }

    // 5. Fetch Associated Standalone Returns
    $associated_returns = [];
    try {
        $stmt_ret = $pdo->prepare("SELECT * FROM tbl_returns WHERE (payment_id = ? OR original_payment_id = ?) AND supplier_id = ? AND return_type = 'REFUND' ORDER BY id ASC");
        $stmt_ret->execute([$pid, $pid, $supplier_id]);
        $associated_returns = $stmt_ret->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Exception $e_ret) {}

    $paid_amount = floatval($payment['paid_amount'] ?? 0);
    $delivery_fee = floatval($payment['delivery_fee'] ?? 0);
    $status = $payment['payment_status'] ?? 'Paid';
    $is_paid = ($status === 'Paid' || $status === 'Completed');
    $margin_pct = ($paid_amount > 0) ? round(($order_total_profit / $paid_amount) * 100, 1) : 0.0;

    echo json_encode([
        'success' => true,
        'order' => [
            'id' => (int)$payment['id'],
            'payment_id' => $pid,
            'txnid' => $txnid,
            'payment_date' => $payment['payment_date'],
            'formatted_date' => date('M d, Y h:i A', strtotime($payment['payment_date'])),
            'raw_datetime' => date('Y-m-d H:i:s', strtotime($payment['payment_date'])),
            'payment_status' => $status,
            'is_paid' => $is_paid,
            'payment_method' => $payment['payment_method'] ?? 'Cash',
            'customer_name' => !empty($payment['customer_name']) ? $payment['customer_name'] : 'Walk-in Customer',
            'customer_email' => $payment['customer_email'] ?? '',
            'delivery_fee' => $delivery_fee,
            'paid_amount' => $paid_amount,
            'calc_subtotal' => $calc_subtotal,
            'order_cost' => $order_total_cost,
            'order_profit' => $order_total_profit,
            'order_margin' => $margin_pct,
            'store_name' => $store_name,
            'store_address' => $store_address,
            'store_phone' => $store_phone,
            'items' => $items_list,
            'associated_returns' => $associated_returns
        ]
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error querying order details: ' . $e->getMessage()]);
}
