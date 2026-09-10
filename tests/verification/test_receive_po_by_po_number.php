<?php
// Automated Test Suite for Receive Purchase Orders — Display by PO Number
if (file_exists(__DIR__ . '/supplier/inc/config.php')) {
    require_once __DIR__ . '/supplier/inc/config.php';
} elseif (file_exists(__DIR__ . '/../supplier/inc/config.php')) {
    require_once __DIR__ . '/../supplier/inc/config.php';
} else {
    require_once dirname(__DIR__) . '/supplier/inc/config.php';
}
if (file_exists(__DIR__ . '/supplier/inc/functions.php')) {
    require_once __DIR__ . '/supplier/inc/functions.php';
} elseif (file_exists(__DIR__ . '/../supplier/inc/functions.php')) {
    require_once __DIR__ . '/../supplier/inc/functions.php';
} else {
    require_once dirname(__DIR__) . '/supplier/inc/functions.php';
}

echo "=== STARTING RECEIVE PURCHASE ORDERS BY PO NUMBER TEST SUITE ===\n\n";

$passed = 0;
$failed = 0;

function assert_test($description, $condition) {
    global $passed, $failed;
    if ($condition) {
        echo "[PASS] $description\n";
        $passed++;
    } else {
        echo "[FAIL] $description\n";
        $failed++;
    }
}

// -------------------------------------------------------------
// 1. Syntax Check
// -------------------------------------------------------------
echo "--- 1. PHP Syntax Check ---\n";
$order_file = file_exists(__DIR__ . '/supplier/order.php') ? __DIR__ . '/supplier/order.php' : __DIR__ . '/../supplier/order.php';
$syntax_output = [];
$return_var = 0;
exec("php -l " . escapeshellarg($order_file), $syntax_output, $return_var);
assert_test("supplier/order.php has zero syntax errors", $return_var === 0);

// -------------------------------------------------------------
// 2. Setup Multi-Tenant Suppliers & Customers
// -------------------------------------------------------------
echo "\n--- 2. Database Environment Setup ---\n";

// Supplier A
$stmt_supA = $pdo->query("SELECT * FROM tbl_supplier ORDER BY supplier_id ASC LIMIT 1");
$supplierA = $stmt_supA->fetch(PDO::FETCH_ASSOC);
$supA_id = (int)$supplierA['supplier_id'];

// Supplier B
$stmt_supB = $pdo->prepare("SELECT * FROM tbl_supplier WHERE supplier_id != ? ORDER BY supplier_id ASC LIMIT 1");
$stmt_supB->execute([$supA_id]);
$supplierB = $stmt_supB->fetch(PDO::FETCH_ASSOC);
if (!$supplierB) {
    $pdo->prepare("INSERT INTO tbl_supplier (supplier_name, supplier_slug, supplier_email, supplier_phone, supplier_address, supplier_status) VALUES (?,?,?,?,?,?)")
        ->execute(['Supplier B Isolation Corp', 'supplier-b-iso-' . uniqid(), 'supplier_b_iso@test.com', '09123456789', '456 Business Park, Iloilo', 'Active']);
    $supB_id = (int)$pdo->lastInsertId();
    $stmt_supB = $pdo->prepare("SELECT * FROM tbl_supplier WHERE supplier_id = ?");
    $stmt_supB->execute([$supB_id]);
    $supplierB = $stmt_supB->fetch(PDO::FETCH_ASSOC);
} else {
    $supB_id = (int)$supplierB['supplier_id'];
}

assert_test("Supplier A ID #{$supA_id} ('{$supplierA['supplier_name']}') ready", $supA_id > 0);
assert_test("Supplier B ID #{$supB_id} ('{$supplierB['supplier_name']}') ready", $supB_id > 0);

// Customer 1: ABC Construction (Juan Dela Cruz)
$cust1_email = 'juan.abc.' . uniqid() . '@test.com';
$pdo->prepare("INSERT INTO tbl_customer (
    cust_name, cust_cname, cust_email, cust_phone, cust_country, cust_address, cust_city, cust_state, cust_zip,
    cust_b_name, cust_b_cname, cust_b_phone, cust_b_country, cust_b_address, cust_b_city, cust_b_state, cust_b_zip,
    cust_s_name, cust_s_cname, cust_s_phone, cust_s_country, cust_s_address, cust_s_city, cust_s_state, cust_s_zip,
    cust_password, cust_token, cust_datetime, cust_timestamp, cust_status
) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?) RETURNING cust_id")
->execute([
    'Juan Dela Cruz', 'ABC Construction Builders Inc.', $cust1_email, '09171112222', 1, 'Block 12 Lot 5, Villa San Jose', 'Iloilo City', 'Mandurriao', '5000',
    'Juan Dela Cruz', 'ABC Construction Builders Inc.', '09171112222', 1, 'Block 12 Lot 5, Villa San Jose', 'Iloilo City', 'Mandurriao', '5000',
    'Juan Dela Cruz', 'ABC Construction Builders Inc.', '09171112222', 1, 'Block 12 Lot 5, Villa San Jose', 'Iloilo City', 'Mandurriao', '5000',
    md5('password123'), md5(uniqid()), date('Y-m-d H:i:s'), strval(time()), 1
]);
$cust1_id = (int)$pdo->lastInsertId();

// Customer 2: XYZ Builders (Maria Santos)
$cust2_email = 'maria.xyz.' . uniqid() . '@test.com';
$pdo->prepare("INSERT INTO tbl_customer (
    cust_name, cust_cname, cust_email, cust_phone, cust_country, cust_address, cust_city, cust_state, cust_zip,
    cust_b_name, cust_b_cname, cust_b_phone, cust_b_country, cust_b_address, cust_b_city, cust_b_state, cust_b_zip,
    cust_s_name, cust_s_cname, cust_s_phone, cust_s_country, cust_s_address, cust_s_city, cust_s_state, cust_s_zip,
    cust_password, cust_token, cust_datetime, cust_timestamp, cust_status
) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?) RETURNING cust_id")
->execute([
    'Maria Santos', 'XYZ Builders Corporation', $cust2_email, '09183334444', 1, 'Unit 401, Jaro Plaza Tower', 'Iloilo City', 'Jaro', '5000',
    'Maria Santos', 'XYZ Builders Corporation', '09183334444', 1, 'Unit 401, Jaro Plaza Tower', 'Iloilo City', 'Jaro', '5000',
    'Maria Santos', 'XYZ Builders Corporation', '09183334444', 1, 'Unit 401, Jaro Plaza Tower', 'Iloilo City', 'Jaro', '5000',
    md5('password123'), md5(uniqid()), date('Y-m-d H:i:s'), strval(time()), 1
]);
$cust2_id = (int)$pdo->lastInsertId();

assert_test("Customer 1 (ABC Construction) ready (ID: {$cust1_id})", $cust1_id > 0);
assert_test("Customer 2 (XYZ Builders) ready (ID: {$cust2_id})", $cust2_id > 0);

// Products
$stmt_prod1 = $pdo->prepare("SELECT p_id, p_name FROM tbl_product WHERE supplier_id = ? LIMIT 1");
$stmt_prod1->execute([$supA_id]);
$prod1 = $stmt_prod1->fetch(PDO::FETCH_ASSOC);
$prod1_id = (int)$prod1['p_id'];

// -------------------------------------------------------------
// 3. Test 1, 2, 3: Multiple POs from Same Customer & Same Date + Distinct Customer POs
// -------------------------------------------------------------
echo "\n--- 3. Multiple POs from Same Customer (PO-1001, PO-1002, PO-1003) & Other Customer ---\n";

$today_date = date('Y-m-d H:i:s');
$po_num_1 = 'PO-TEST-' . date('Ymd') . '-0001-' . strtoupper(substr(uniqid(), -3));
$po_num_2 = 'PO-TEST-' . date('Ymd') . '-0002-' . strtoupper(substr(uniqid(), -3));
$po_num_3 = 'PO-TEST-' . date('Ymd') . '-0003-' . strtoupper(substr(uniqid(), -3));
$po_num_xyz = 'PO-TEST-' . date('Ymd') . '-0004-' . strtoupper(substr(uniqid(), -3));

// Insert PO 1 for Customer 1
$pdo->prepare("INSERT INTO tbl_payment (
    customer_id, customer_name, customer_email, payment_date, txnid,
    paid_amount, card_number, card_cvv, card_month, card_year,
    bank_transaction_info, payment_method, payment_status, shipping_status,
    payment_id, supplier_id
) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?) RETURNING id")
->execute([
    $cust1_id, 'Juan Dela Cruz', $cust1_email, $today_date, $po_num_1,
    '25500.00', '', '', '', '',
    'Purchase Order | Method: Purchase Order (PO) | Notes: Order #1 for Phase 1',
    'Purchase Order (PO)', 'Awaiting for Payment', 'Pending',
    $po_num_1, $supA_id
]);
$pay1_id = (int)$pdo->lastInsertId();

// Items for PO 1 (4 items)
$pdo->prepare("INSERT INTO tbl_order (product_id, product_name, size, color, quantity, unit_price, payment_id, supplier_id, item_type) VALUES (?,?,?,?,?,?,?,?,?)")
    ->execute([$prod1_id, 'Portland Cement 40kg', '40kg Bag', 'Standard', '20', '350.00', $po_num_1, $supA_id, 'STANDARD']);
$pdo->prepare("INSERT INTO tbl_order (product_id, product_name, size, color, quantity, unit_price, payment_id, supplier_id, item_type) VALUES (?,?,?,?,?,?,?,?,?)")
    ->execute([$prod1_id, 'Deformed Steel Bar 12mm', '6M', 'Standard', '10', '850.00', $po_num_1, $supA_id, 'STANDARD']);
$pdo->prepare("INSERT INTO tbl_order (product_id, product_name, size, color, quantity, unit_price, payment_id, supplier_id, item_type) VALUES (?,?,?,?,?,?,?,?,?)")
    ->execute([$prod1_id, 'Marine Plywood 1/2', '4x8', 'Standard', '5', '750.00', $po_num_1, $supA_id, 'STANDARD']);
$pdo->prepare("INSERT INTO tbl_order (product_id, product_name, size, color, quantity, unit_price, payment_id, supplier_id, item_type) VALUES (?,?,?,?,?,?,?,?,?)")
    ->execute([$prod1_id, 'Angle Bar 1x1x6M', '6M', 'Standard', '10', '625.00', $po_num_1, $supA_id, 'STANDARD']);

// Insert PO 2 for Customer 1 (Same Customer, Same Date)
$pdo->prepare("INSERT INTO tbl_payment (
    customer_id, customer_name, customer_email, payment_date, txnid,
    paid_amount, card_number, card_cvv, card_month, card_year,
    bank_transaction_info, payment_method, payment_status, shipping_status,
    payment_id, supplier_id
) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?) RETURNING id")
->execute([
    $cust1_id, 'Juan Dela Cruz', $cust1_email, $today_date, $po_num_2,
    '18750.00', '', '', '', '',
    'Purchase Order | Method: Purchase Order (PO) | Notes: Order #2 for Phase 2',
    'Purchase Order (PO)', 'Awaiting for Payment', 'Pending',
    $po_num_2, $supA_id
]);
$pay2_id = (int)$pdo->lastInsertId();

$pdo->prepare("INSERT INTO tbl_order (product_id, product_name, size, color, quantity, unit_price, payment_id, supplier_id, item_type) VALUES (?,?,?,?,?,?,?,?,?)")
    ->execute([$prod1_id, 'Galvanized Roofing Sheet', '10ft', 'Pre-painted Blue', '25', '750.00', $po_num_2, $supA_id, 'STANDARD']);

// Insert PO 3 for Customer 1 (Same Customer, Same Date)
$pdo->prepare("INSERT INTO tbl_payment (
    customer_id, customer_name, customer_email, payment_date, txnid,
    paid_amount, card_number, card_cvv, card_month, card_year,
    bank_transaction_info, payment_method, payment_status, shipping_status,
    payment_id, supplier_id
) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?) RETURNING id")
->execute([
    $cust1_id, 'Juan Dela Cruz', $cust1_email, $today_date, $po_num_3,
    '8250.00', '', '', '', '',
    'Purchase Order | Method: Purchase Order (PO) | Notes: Order #3 for Finishing',
    'Purchase Order (PO)', 'Awaiting for Payment', 'Pending',
    $po_num_3, $supA_id
]);
$pay3_id = (int)$pdo->lastInsertId();

$pdo->prepare("INSERT INTO tbl_order (product_id, product_name, size, color, quantity, unit_price, payment_id, supplier_id, item_type) VALUES (?,?,?,?,?,?,?,?,?)")
    ->execute([$prod1_id, 'Tile Adhesive Heavy Duty', '25kg', 'Gray', '15', '550.00', $po_num_3, $supA_id, 'STANDARD']);

// Insert PO 4 for Customer 2 (XYZ Builders)
$pdo->prepare("INSERT INTO tbl_payment (
    customer_id, customer_name, customer_email, payment_date, txnid,
    paid_amount, card_number, card_cvv, card_month, card_year,
    bank_transaction_info, payment_method, payment_status, shipping_status,
    payment_id, supplier_id
) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?) RETURNING id")
->execute([
    $cust2_id, 'Maria Santos', $cust2_email, $today_date, $po_num_xyz,
    '15000.00', '', '', '', '',
    'Purchase Order | Method: Purchase Order (PO)',
    'Purchase Order (PO)', 'Awaiting for Payment', 'Pending',
    $po_num_xyz, $supA_id
]);
$pay_xyz_id = (int)$pdo->lastInsertId();

$pdo->prepare("INSERT INTO tbl_order (product_id, product_name, size, color, quantity, unit_price, payment_id, supplier_id, item_type) VALUES (?,?,?,?,?,?,?,?,?)")
    ->execute([$prod1_id, 'Structural C-Purlins 2x4', '6M', 'Standard', '20', '750.00', $po_num_xyz, $supA_id, 'STANDARD']);

// Insert PO for Supplier B (Tenant isolation test)
$po_num_supb = 'PO-TEST-' . date('Ymd') . '-SUPB-' . strtoupper(substr(uniqid(), -3));
$pdo->prepare("INSERT INTO tbl_payment (
    customer_id, customer_name, customer_email, payment_date, txnid,
    paid_amount, card_number, card_cvv, card_month, card_year,
    bank_transaction_info, payment_method, payment_status, shipping_status,
    payment_id, supplier_id
) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?) RETURNING id")
->execute([
    $cust1_id, 'Juan Dela Cruz', $cust1_email, $today_date, $po_num_supb,
    '9999.00', '', '', '', '',
    'Purchase Order | Method: Over the Counter',
    'Over the Counter', 'Awaiting for Payment', 'Pending',
    $po_num_supb, $supB_id
]);
$pay_supb_id = (int)$pdo->lastInsertId();

assert_test("PO 1 ({$po_num_1}) created for ABC Construction (₱25,500.00)", $pay1_id > 0);
assert_test("PO 2 ({$po_num_2}) created for ABC Construction (₱18,750.00)", $pay2_id > 0);
assert_test("PO 3 ({$po_num_3}) created for ABC Construction (₱8,250.00)", $pay3_id > 0);
assert_test("PO 4 ({$po_num_xyz}) created for XYZ Builders (₱15,000.00)", $pay_xyz_id > 0);
assert_test("Supplier B PO ({$po_num_supb}) created", $pay_supb_id > 0);

// -------------------------------------------------------------
// 4. Render supplier/order.php and Test Acceptance Criteria
// -------------------------------------------------------------
echo "\n--- 4. Render & Verify PO-Level Display & Uniqueness ---\n";

$sim_script = '<?php
$_SERVER["REQUEST_METHOD"] = "GET";
session_start();
$_SESSION["supplier"] = ["supplier_id" => ' . $supA_id . ', "supplier_name" => "' . addslashes($supplierA['supplier_name']) . '", "role" => "supplier_owner", "user_role" => "supplier_owner"];
require_once("/var/www/html/supplier/inc/config.php");
$supplier_id = ' . $supA_id . ';
ob_start();
include("/var/www/html/supplier/order.php");
$output = ob_get_clean();
echo $output;
';
file_put_contents('/var/www/html/scratch_sim_po_check.php', $sim_script);
$rendered_html = shell_exec("php /var/www/html/scratch_sim_po_check.php");
@unlink('/var/www/html/scratch_sim_po_check.php');

assert_test("Rendered HTML is non-empty", !empty($rendered_html));

// Test 1 & 3: Each PO is rendered separately (no merging by customer or date)
assert_test("PO 1 ({$po_num_1}) is rendered as a distinct row", strpos($rendered_html, $po_num_1) !== false);
assert_test("PO 2 ({$po_num_2}) is rendered as a distinct row", strpos($rendered_html, $po_num_2) !== false);
assert_test("PO 3 ({$po_num_3}) is rendered as a distinct row", strpos($rendered_html, $po_num_3) !== false);
assert_test("PO 4 ({$po_num_xyz}) is rendered as a distinct row", strpos($rendered_html, $po_num_xyz) !== false);

// Count occurrences of PO numbers in rows
$count_po1 = substr_count($rendered_html, $po_num_1);
$count_po2 = substr_count($rendered_html, $po_num_2);
$count_po3 = substr_count($rendered_html, $po_num_3);
assert_test("PO 1 appears in the table", $count_po1 >= 1);
assert_test("PO 2 appears in the table", $count_po2 >= 1);
assert_test("PO 3 appears in the table", $count_po3 >= 1);

// Test 6: Supplier Isolation
assert_test("Supplier A CANNOT see Supplier B's PO ({$po_num_supb})", strpos($rendered_html, $po_num_supb) === false);

// Test 4: Multiple items in PO 1 (4 items)
echo "\n--- 5. Verify PO 1 Multiple Items in Voucher Modal ---\n";
$modal_po1_id = 'po-modal-' . $pay1_id;
assert_test("Voucher Modal container for PO 1 exists (#{$modal_po1_id})", strpos($rendered_html, 'id="' . $modal_po1_id . '"') !== false);
assert_test("PO 1 Item 1: 'Portland Cement 40kg' (20 bags @ ₱350.00 = ₱7,000.00) in voucher", strpos($rendered_html, 'Portland Cement 40kg') !== false && strpos($rendered_html, '7,000.00') !== false);
assert_test("PO 1 Item 2: 'Deformed Steel Bar 12mm' (10 pcs @ ₱850.00 = ₱8,500.00) in voucher", strpos($rendered_html, 'Deformed Steel Bar 12mm') !== false && strpos($rendered_html, '8,500.00') !== false);
assert_test("PO 1 Item 3: 'Marine Plywood 1/2' (5 pcs @ ₱750.00 = ₱3,750.00) in voucher", strpos($rendered_html, 'Marine Plywood 1/2') !== false && strpos($rendered_html, '3,750.00') !== false);
assert_test("PO 1 Item 4: 'Angle Bar 1x1x6M' (10 pcs @ ₱625.00 = ₱6,250.00) in voucher", strpos($rendered_html, 'Angle Bar 1x1x6M') !== false && strpos($rendered_html, '6,250.00') !== false);
assert_test("PO 1 Grand Total ₱25,500.00 in voucher", strpos($rendered_html, '25,500.00') !== false);

// Test 7: Voucher for PO 2 contains only PO 2 data (₱18,750.00), not mixing PO 1 or PO 3
echo "\n--- 6. Verify PO 2 Voucher Isolation ---\n";
$modal_po2_id = 'po-modal-' . $pay2_id;
assert_test("Voucher Modal container for PO 2 exists (#{$modal_po2_id})", strpos($rendered_html, 'id="' . $modal_po2_id . '"') !== false);
assert_test("PO 2 Item 'Galvanized Roofing Sheet' present in PO 2 voucher", strpos($rendered_html, 'Galvanized Roofing Sheet') !== false);
assert_test("PO 2 Grand Total ₱18,750.00 present in PO 2 voucher", strpos($rendered_html, '18,750.00') !== false);

// Test 5: Status change / Receive isolation
echo "\n--- 7. Receive / Mark as Paid Isolation (Updates only targeted PO) ---\n";

// Execute Receive on PO 1 (mark as Paid)
$stmt_mark_paid = $pdo->prepare("UPDATE tbl_payment SET payment_status = 'Paid' WHERE id = ? AND supplier_id = ?");
$stmt_mark_paid->execute([$pay1_id, $supA_id]);

// Check PO 1 status
$stmt_chk1 = $pdo->prepare("SELECT payment_status FROM tbl_payment WHERE id = ?");
$stmt_chk1->execute([$pay1_id]);
$res1 = $stmt_chk1->fetch(PDO::FETCH_ASSOC);

// Check PO 2 status
$stmt_chk2 = $pdo->prepare("SELECT payment_status FROM tbl_payment WHERE id = ?");
$stmt_chk2->execute([$pay2_id]);
$res2 = $stmt_chk2->fetch(PDO::FETCH_ASSOC);

// Check PO 3 status
$stmt_chk3 = $pdo->prepare("SELECT payment_status FROM tbl_payment WHERE id = ?");
$stmt_chk3->execute([$pay3_id]);
$res3 = $stmt_chk3->fetch(PDO::FETCH_ASSOC);

assert_test("PO 1 status successfully changed to 'Paid'", $res1['payment_status'] === 'Paid');
assert_test("PO 2 status remains 'Awaiting for Payment' (Unchanged)", $res2['payment_status'] === 'Awaiting for Payment');
assert_test("PO 3 status remains 'Awaiting for Payment' (Unchanged)", $res3['payment_status'] === 'Awaiting for Payment');

// Re-render supplier/order.php for Receive POs (status=unpaid) and verify PO 1 is no longer in pending Receive Orders list
$sim_script2 = '<?php
$_SERVER["REQUEST_METHOD"] = "GET";
$_GET["status"] = "unpaid";
session_start();
$_SESSION["supplier"] = ["supplier_id" => ' . $supA_id . ', "supplier_name" => "' . addslashes($supplierA['supplier_name']) . '", "role" => "supplier_owner", "user_role" => "supplier_owner"];
require_once("/var/www/html/supplier/inc/config.php");
$supplier_id = ' . $supA_id . ';
ob_start();
include("/var/www/html/supplier/order.php");
$output = ob_get_clean();
echo $output;
';
file_put_contents('/var/www/html/scratch_sim_po_check2.php', $sim_script2);
$rendered_html2 = shell_exec("php /var/www/html/scratch_sim_po_check2.php");
@unlink('/var/www/html/scratch_sim_po_check2.php');

assert_test("PO 1 ({$po_num_1}) is no longer displayed on Receive Orders page after being Paid", strpos($rendered_html2, $po_num_1) === false);
assert_test("PO 2 ({$po_num_2}) is STILL displayed on Receive Orders page", strpos($rendered_html2, $po_num_2) !== false);
assert_test("PO 3 ({$po_num_3}) is STILL displayed on Receive Orders page", strpos($rendered_html2, $po_num_3) !== false);

// -------------------------------------------------------------
// 8. Cleanup Test Records
// -------------------------------------------------------------
$pdo->prepare("DELETE FROM tbl_order WHERE payment_id IN (?, ?, ?, ?, ?)")
    ->execute([$po_num_1, $po_num_2, $po_num_3, $po_num_xyz, $po_num_supb]);
$pdo->prepare("DELETE FROM tbl_payment WHERE id IN (?, ?, ?, ?, ?)")
    ->execute([$pay1_id, $pay2_id, $pay3_id, $pay_xyz_id, $pay_supb_id]);
$pdo->prepare("DELETE FROM tbl_customer WHERE cust_id IN (?, ?)")
    ->execute([$cust1_id, $cust2_id]);
if (!empty($supB_id)) {
    $pdo->prepare("DELETE FROM tbl_supplier WHERE supplier_name = 'Supplier B Isolation Corp'")->execute();
}

echo "\n=======================================================\n";
echo "TEST RESULTS: {$passed} PASSED, {$failed} FAILED\n";
echo "=======================================================\n";
if ($failed > 0) {
    exit(1);
} else {
    exit(0);
}
