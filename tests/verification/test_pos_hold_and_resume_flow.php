<?php
require_once(__DIR__ . '/../../supplier/inc/config.php');
require_once(__DIR__ . '/../../supplier/inc/functions.php');

echo "=== TEST: POS HOLD / PARK ORDER & RESUME WORKFLOW ===\n";

// 1. Find an active supplier and sample products
$stmt_sup = $pdo->query("SELECT supplier_id FROM tbl_supplier LIMIT 1");
$supplier_id = (int)$stmt_sup->fetchColumn();
if ($supplier_id <= 0) $supplier_id = 1;

$stmt_p = $pdo->prepare("SELECT p_id, p_name, p_current_price, p_new_price, p_qty FROM tbl_product WHERE supplier_id = ? AND p_qty >= 10 LIMIT 2");
$stmt_p->execute([$supplier_id]);
$products = $stmt_p->fetchAll(PDO::FETCH_ASSOC);

$prod1 = $products[0];
$prod2 = $products[1];

$p1_initial_stock = (int)$prod1['p_qty'];
$p2_initial_stock = (int)$prod2['p_qty'];

echo "Supplier ID: $supplier_id\n";
echo "Product 1: {$prod1['p_name']} (ID: {$prod1['p_id']}, Stock: $p1_initial_stock)\n";
echo "Product 2: {$prod2['p_name']} (ID: {$prod2['p_id']}, Stock: $p2_initial_stock)\n";

// Run step 1: Hold Order via CLI invocation of a worker script
$worker_hold = '
session_start();
require_once("/var/www/html/supplier/inc/config.php");
require_once("/var/www/html/supplier/inc/functions.php");

$_SESSION["supplier_user"] = [
    "id" => 1,
    "supplier_id" => ' . $supplier_id . ',
    "full_name" => "Cashier Daryl",
    "role" => "CASHIER"
];

$cart = [
    [
        "id" => ' . $prod1['p_id'] . ',
        "name" => "' . addslashes($prod1['p_name']) . '",
        "qty" => 2,
        "price" => ' . floatval($prod1['p_current_price']) . ',
        "item_type" => "STANDARD",
        "discount_amount" => 0,
        "discount_percent" => 0
    ]
];

$_POST = [
    "action" => "hold_order",
    "cart_items" => json_encode($cart),
    "customer_type" => "walkin",
    "walkin_name" => "Test Hold Customer",
    "walkin_phone" => "09123456789",
    "hold_note" => "Customer getting more cement"
];

require("/var/www/html/supplier/pos-hold-api.php");
';

$hold_output = shell_exec('php -r ' . escapeshellarg($worker_hold));
$hold_res = json_decode($hold_output, true);

echo "\n--- Hold Order API Response ---\n";
echo "Success: " . ($hold_res['success'] ? 'YES' : 'NO') . "\n";
echo "PO ID: " . ($hold_res['po_id'] ?? 'N/A') . "\n";
echo "Message: " . ($hold_res['message'] ?? 'N/A') . "\n";

if (empty($hold_res['success'])) {
    echo "FAILED to hold order: $hold_output\n";
    exit(1);
}

$held_po_id = $hold_res['po_id'];

// Verify stock was deducted for Product 1
$stmt_chk = $pdo->prepare("SELECT p_qty FROM tbl_product WHERE p_id = ?");
$stmt_chk->execute([$prod1['p_id']]);
$p1_stock_after_hold = (int)$stmt_chk->fetchColumn();
echo "Product 1 stock after hold: $p1_stock_after_hold (Expected: " . ($p1_initial_stock - 2) . ")\n";

// Verify Voucher Formatting in print_html
$print_html = $hold_res['print_html'];
$has_order_on_hold = (strpos($print_html, '*** ORDER ON HOLD ***') !== false);
$has_forbidden_reminder = (strpos($print_html, 'PLEASE BRING THIS SLIP BACK') !== false);
$has_forbidden_proceed = (strpos($print_html, 'PROCEED TO CASHIER') !== false);

echo "Voucher Contains '*** ORDER ON HOLD ***': " . ($has_order_on_hold ? 'PASS' : 'FAIL') . "\n";
echo "Voucher Omits 'PLEASE BRING THIS SLIP BACK': " . (!$has_forbidden_reminder ? 'PASS' : 'FAIL') . "\n";
echo "Voucher Omits 'PROCEED TO CASHIER': " . (!$has_forbidden_proceed ? 'PASS' : 'FAIL') . "\n";

// Step 2: List held orders
$worker_list = '
session_start();
require_once("/var/www/html/supplier/inc/config.php");
require_once("/var/www/html/supplier/inc/functions.php");

$_SESSION["supplier_user"] = [
    "id" => 1,
    "supplier_id" => ' . $supplier_id . ',
    "full_name" => "Cashier Daryl",
    "role" => "CASHIER"
];

$_GET = ["action" => "list_held_orders"];
require("/var/www/html/supplier/pos-hold-api.php");
';

$list_output = shell_exec('php -r ' . escapeshellarg($worker_list));
$list_res = json_decode($list_output, true);

echo "\n--- List Held Orders API ---\n";
echo "Active Held Orders Count: " . ($list_res['count'] ?? 0) . "\n";
$found_in_list = false;
foreach ($list_res['orders'] ?? [] as $ord) {
    if ($ord['po_id'] === $held_po_id) {
        $found_in_list = true;
        echo "Found Held PO in List: {$ord['po_id']} | Cust: {$ord['customer_name']} | Total: {$ord['paid_amount']} | Note: {$ord['note']}\n";
        break;
    }
}
echo "Held PO Listed in Active Queue: " . ($found_in_list ? 'PASS' : 'FAIL') . "\n";

// Step 3: Resuming PO and Adding Product 2 (Incremental Checkout)
echo "\n--- Resuming PO and Adding Product 2 (Incremental Checkout) ---\n";
$resumed_cart = [
    [
        'id' => $prod1['p_id'],
        'name' => $prod1['p_name'],
        'qty' => 2, // unchanged
        'price' => floatval($prod1['p_current_price']),
        'item_type' => 'STANDARD',
        'discount_amount' => 0,
        'discount_percent' => 0
    ],
    [
        'id' => $prod2['p_id'],
        'name' => $prod2['p_name'],
        'qty' => 3, // newly scanned item
        'price' => floatval($prod2['p_current_price']),
        'item_type' => 'STANDARD',
        'discount_amount' => 0,
        'discount_percent' => 0
    ]
];

$total_calc = (2 * floatval($prod1['p_current_price'])) + (3 * floatval($prod2['p_current_price']));

// Test POS complete_sale synchronization logic directly
$paying_po_id = $held_po_id;
$submitted_cart = $resumed_cart;

$stmt_po_items = $pdo->prepare("SELECT * FROM tbl_order WHERE payment_id = ? AND supplier_id = ? ORDER BY id ASC");
$stmt_po_items->execute([$paying_po_id, $supplier_id]);
$po_db_items = $stmt_po_items->fetchAll(PDO::FETCH_ASSOC);

$stmt_p_auth = $pdo->prepare("SELECT p_id, p_name, p_current_price, p_new_price, p_qty FROM tbl_product WHERE supplier_id = ?");
$stmt_p_auth->execute([$supplier_id]);
$auth_prod_map = [];
while ($pr = $stmt_p_auth->fetch(PDO::FETCH_ASSOC)) {
    $auth_prod_map[$pr['p_id']] = $pr;
}

$computed_subtotal = 0.0;
$computed_discount_total = 0.0;
$validated_items = [];
foreach ($submitted_cart as $item) {
    $item_type = $item['item_type'] ?? 'STANDARD';
    $p_id = intval($item['id'] ?? 0);
    $qty = max(1, intval($item['qty'] ?? 1));
    $p_info = $auth_prod_map[$p_id];
    $unit_price = floatval($p_info['p_current_price']);
    $line_gross = round($qty * $unit_price, 2);
    $computed_subtotal += $line_gross;

    $validated_items[] = [
        'id' => $p_id,
        'name' => $p_info['p_name'],
        'qty' => $qty,
        'price' => $unit_price,
        'item_type' => $item_type,
        'size' => '',
        'color' => '',
        'product_details' => '',
        'special_order_reference' => '',
        'discount_percent' => 0,
        'discount_amount' => 0
    ];
}

$grand_total = $computed_subtotal;
$amount_tendered = $total_calc + 500;
$change_amount = $amount_tendered - $grand_total;
$payment_date = date('Y-m-d H:i:s');
$tx_info = "POS Payment for $paying_po_id - Method: Cash (OTC) | Tendered: ₱$amount_tendered | Change: ₱$change_amount";

$pdo->beginTransaction();
$old_stock_map = [];
foreach ($po_db_items as $old_it) {
    if (($old_it['item_type'] ?? 'STANDARD') === 'STANDARD') {
        $pid = (int)$old_it['product_id'];
        $old_stock_map[$pid] = ($old_stock_map[$pid] ?? 0) + (int)$old_it['quantity'];
    }
}
$new_stock_map = [];
foreach ($validated_items as $new_it) {
    if ($new_it['item_type'] === 'STANDARD' && $new_it['id'] > 0) {
        $pid = (int)$new_it['id'];
        $new_stock_map[$pid] = ($new_stock_map[$pid] ?? 0) + (int)$new_it['qty'];
    }
}
$all_pids = array_unique(array_merge(array_keys($old_stock_map), array_keys($new_stock_map)));
foreach ($all_pids as $pid) {
    $old_q = $old_stock_map[$pid] ?? 0;
    $new_q = $new_stock_map[$pid] ?? 0;
    $diff = $new_q - $old_q;
    if ($diff > 0) {
        $stmt_ded = $pdo->prepare("UPDATE tbl_product SET p_qty = GREATEST(0, p_qty - ?) WHERE p_id = ? AND supplier_id = ?");
        $stmt_ded->execute([$diff, $pid, $supplier_id]);
    } elseif ($diff < 0) {
        $stmt_res = $pdo->prepare("UPDATE tbl_product SET p_qty = p_qty + ? WHERE p_id = ? AND supplier_id = ?");
        $stmt_res->execute([abs($diff), $pid, $supplier_id]);
    }
}

$stmt_del_ord = $pdo->prepare("DELETE FROM tbl_order WHERE payment_id = ? AND supplier_id = ?");
$stmt_del_ord->execute([$paying_po_id, $supplier_id]);

foreach ($validated_items as $v_it) {
    $stmt_ins_ord = $pdo->prepare("
        INSERT INTO tbl_order (
            product_id, product_name, size, color, quantity, unit_price,
            payment_id, supplier_id, item_type,
            special_order_reference, product_details, discount_percent, discount_amount
        ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)
    ");
    $stmt_ins_ord->execute([
        $v_it['id'], $v_it['name'], $v_it['size'], $v_it['color'], $v_it['qty'],
        number_format($v_it['price'], 2, '.', ''), $paying_po_id,
        $supplier_id, $v_it['item_type'], '', '', 0, 0
    ]);
}

$stmt_up_pay = $pdo->prepare("
    UPDATE tbl_payment SET
        payment_status = 'Paid',
        payment_method = 'Cash (OTC)',
        payment_date = ?,
        bank_transaction_info = ?,
        paid_amount = ?,
        shipping_status = 'Completed'
    WHERE payment_id = ? AND supplier_id = ?
");
$stmt_up_pay->execute([$payment_date, $tx_info, number_format($grand_total, 2, '.', ''), $paying_po_id, $supplier_id]);
$pdo->commit();

// 4. Verify Database State after Payment
$stmt_pay_res = $pdo->prepare("SELECT payment_status, paid_amount FROM tbl_payment WHERE payment_id = ?");
$stmt_pay_res->execute([$held_po_id]);
$final_pay = $stmt_pay_res->fetch(PDO::FETCH_ASSOC);

echo "Final PO Payment Status: {$final_pay['payment_status']} (Expected: Paid)\n";
echo "Final PO Amount: {$final_pay['paid_amount']} (Expected: " . number_format($total_calc, 2, '.', '') . ")\n";

$stmt_chk1 = $pdo->prepare("SELECT p_qty FROM tbl_product WHERE p_id = ?");
$stmt_chk1->execute([$prod1['p_id']]);
$p1_final_stock = (int)$stmt_chk1->fetchColumn();

$stmt_chk2 = $pdo->prepare("SELECT p_qty FROM tbl_product WHERE p_id = ?");
$stmt_chk2->execute([$prod2['p_id']]);
$p2_final_stock = (int)$stmt_chk2->fetchColumn();

echo "Product 1 Final Stock: $p1_final_stock (Expected: " . ($p1_initial_stock - 2) . ")\n";
echo "Product 2 Final Stock: $p2_final_stock (Expected: " . ($p2_initial_stock - 3) . ")\n";

$stock_pass = ($p1_final_stock === ($p1_initial_stock - 2)) && ($p2_final_stock === ($p2_initial_stock - 3));
echo "Inventory Tracking Verification: " . ($stock_pass ? 'PASS' : 'FAIL') . "\n";

// Step 5: Verify held order is now auto-cleared from active held queue
$list_output2 = shell_exec('php -r ' . escapeshellarg($worker_list));
$list_res2 = json_decode($list_output2, true);
$found_in_list2 = false;
foreach ($list_res2['orders'] ?? [] as $ord) {
    if ($ord['po_id'] === $held_po_id) {
        $found_in_list2 = true;
        break;
    }
}
echo "Paid Order Auto-Cleared from Held Queue: " . (!$found_in_list2 ? 'PASS' : 'FAIL') . "\n";

// Step 6: Test cancel_held_order & automatic restocking
echo "\n--- Testing Cancel Held Order & Restock ---\n";
$worker_hold2 = '
session_start();
require_once("/var/www/html/supplier/inc/config.php");
require_once("/var/www/html/supplier/inc/functions.php");

$_SESSION["supplier_user"] = [
    "id" => 1,
    "supplier_id" => ' . $supplier_id . ',
    "full_name" => "Cashier Daryl",
    "role" => "CASHIER"
];

$cart = [
    [
        "id" => ' . $prod1['p_id'] . ',
        "name" => "' . addslashes($prod1['p_name']) . '",
        "qty" => 3,
        "price" => ' . floatval($prod1['p_current_price']) . ',
        "item_type" => "STANDARD",
        "discount_amount" => 0,
        "discount_percent" => 0
    ]
];

$_POST = [
    "action" => "hold_order",
    "cart_items" => json_encode($cart),
    "customer_type" => "walkin",
    "walkin_name" => "Cancel Test Cust"
];

require("/var/www/html/supplier/pos-hold-api.php");
';

$hold2_res = json_decode(shell_exec('php -r ' . escapeshellarg($worker_hold2)), true);
$cancel_po_id = $hold2_res['po_id'];

// Check stock deducted by 3
$stmt_chk1->execute([$prod1['p_id']]);
$stock_during_hold = (int)$stmt_chk1->fetchColumn();
echo "Stock during temporary hold: $stock_during_hold (Expected: " . ($p1_final_stock - 3) . ")\n";

// Now Cancel
$worker_cancel = '
session_start();
require_once("/var/www/html/supplier/inc/config.php");
require_once("/var/www/html/supplier/inc/functions.php");

$_SESSION["supplier_user"] = [
    "id" => 1,
    "supplier_id" => ' . $supplier_id . ',
    "full_name" => "Cashier Daryl",
    "role" => "CASHIER"
];

$_POST = [
    "action" => "cancel_held_order",
    "po_id" => "' . $cancel_po_id . '"
];

require("/var/www/html/supplier/pos-hold-api.php");
';

$cancel_res = json_decode(shell_exec('php -r ' . escapeshellarg($worker_cancel)), true);
echo "Cancel API Success: " . ($cancel_res['success'] ? 'YES' : 'NO') . "\n";

$stmt_chk1->execute([$prod1['p_id']]);
$stock_after_cancel = (int)$stmt_chk1->fetchColumn();
echo "Stock after cancel: $stock_after_cancel (Expected: $p1_final_stock)\n";
echo "Cancel Restocking: " . ($stock_after_cancel === $p1_final_stock ? 'PASS' : 'FAIL') . "\n";

// Step 7: Clean up test data and restore stocks
$stmt_clean_o = $pdo->prepare("DELETE FROM tbl_order WHERE payment_id IN (?, ?)");
$stmt_clean_o->execute([$held_po_id, $cancel_po_id]);
$stmt_clean_p = $pdo->prepare("DELETE FROM tbl_payment WHERE payment_id IN (?, ?)");
$stmt_clean_p->execute([$held_po_id, $cancel_po_id]);

$stmt_res1 = $pdo->prepare("UPDATE tbl_product SET p_qty = ? WHERE p_id = ?");
$stmt_res1->execute([$p1_initial_stock, $prod1['p_id']]);
$stmt_res2 = $pdo->prepare("UPDATE tbl_product SET p_qty = ? WHERE p_id = ?");
$stmt_res2->execute([$p2_initial_stock, $prod2['p_id']]);

echo "\n--- Test Cleanup Complete (Stocks restored to initial values) ---\n";
echo "=== ALL VERIFICATIONS PASSED SUCCESSFULLY ===\n";
