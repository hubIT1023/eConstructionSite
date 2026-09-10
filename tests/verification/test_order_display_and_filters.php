<?php
// Automated Test Suite for supplier/order.php - Display All Orders & Filter by Day, Week, Month, ID DESC
if (file_exists(__DIR__ . '/supplier/inc/config.php')) {
    require_once __DIR__ . '/supplier/inc/config.php';
} elseif (file_exists(__DIR__ . '/../supplier/inc/config.php')) {
    require_once __DIR__ . '/../supplier/inc/config.php';
} else {
    require_once dirname(__DIR__) . '/supplier/inc/config.php';
}

echo "=== STARTING ORDER DISPLAY & FILTERS TEST SUITE ===\n\n";

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
// 2. Setup Multi-Tenant Suppliers & Test Orders with Varied Dates
// -------------------------------------------------------------
echo "\n--- 2. Database Environment Setup & Seed Test Orders ---\n";

// Supplier A
$stmt_sup = $pdo->query("SELECT supplier_id FROM tbl_supplier ORDER BY supplier_id ASC LIMIT 1");
$supplier_a_id = (int)$stmt_sup->fetchColumn();
if (!$supplier_a_id) $supplier_a_id = 1;

// Supplier B
$stmt_sup_b = $pdo->query("SELECT supplier_id FROM tbl_supplier WHERE supplier_id != $supplier_a_id LIMIT 1");
$supplier_b_id = (int)$stmt_sup_b->fetchColumn();
if (!$supplier_b_id) $supplier_b_id = 2;

echo "Supplier A ID: #$supplier_a_id, Supplier B ID: #$supplier_b_id\n";

// Ensure a test customer
$test_cust_email = 'filtertest.customer@econstruction.site';
$stmt_c = $pdo->prepare("SELECT cust_id FROM tbl_customer WHERE cust_email = ?");
$stmt_c->execute([$test_cust_email]);
$test_cust_id = (int)$stmt_c->fetchColumn();
if (!$test_cust_id) {
    $stmt_ins_c = $pdo->prepare("INSERT INTO tbl_customer (cust_name, cust_cname, cust_email, cust_phone, cust_country, cust_address, cust_city, cust_state, cust_zip, cust_b_name, cust_b_cname, cust_b_phone, cust_b_country, cust_b_address, cust_b_city, cust_b_state, cust_b_zip, cust_s_name, cust_s_cname, cust_s_phone, cust_s_country, cust_s_address, cust_s_city, cust_s_state, cust_s_zip, cust_password, cust_token, cust_datetime, cust_timestamp, cust_status) VALUES ('Filter Test Client', 'Filter Test Corp', ?, '09171234567', 1, '100 Filter St', 'Quezon City', 'Metro Manila', '1100', '', '', '', 1, '', '', '', '', '', '', '', 1, '', '', '', '', 'dummy', 'dummy', '2026-09-07 10:00:00', '1757239200', 1)");
    $stmt_ins_c->execute([$test_cust_email]);
    $stmt_c->execute([$test_cust_email]);
    $test_cust_id = (int)$stmt_c->fetchColumn();
}
assert_test("Test Customer Ready (ID: #$test_cust_id)", $test_cust_id > 0);

// Create 4 distinct orders with specific dates for Supplier A
// Order 1: Today (2026-09-07) - Awaiting Payment
$po1_num = 'PO-FILTER-20260907-001-' . substr(md5(uniqid()), 0, 4);
$date1 = '2026-09-07 10:15:00';
$stmt_p1 = $pdo->prepare("INSERT INTO tbl_payment (customer_id, customer_name, customer_email, payment_date, txnid, paid_amount, card_number, card_cvv, card_month, card_year, bank_transaction_info, payment_method, payment_status, shipping_status, payment_id, supplier_id) VALUES (?, 'Filter Test Client', ?, ?, ?, 15000.00, '', '', '', '', 'Pick-up at Store | Notes: Urgent Delivery', 'Purchase Order (PO)', 'Awaiting for Payment', 'Pending', ?, ?)");
$stmt_p1->execute([$test_cust_id, $test_cust_email, $date1, $po1_num, $po1_num, $supplier_a_id]);
$stmt_get1 = $pdo->prepare("SELECT id FROM tbl_payment WHERE payment_id = ?");
$stmt_get1->execute([$po1_num]);
$po1_id = (int)$stmt_get1->fetchColumn();

$stmt_o1 = $pdo->prepare("INSERT INTO tbl_order (product_id, product_name, size, color, quantity, unit_price, payment_id, supplier_id, item_type) VALUES (1, 'Portland Cement Premium', '40kg', 'Gray', '30', '500.00', ?, ?, 'STANDARD')");
$stmt_o1->execute([$po1_num, $supplier_a_id]);

// Order 2: Yesterday / Last Week (2026-09-06) - Paid
$po2_num = 'PO-FILTER-20260906-002-' . substr(md5(uniqid()), 0, 4);
$date2 = '2026-09-06 14:30:00';
$stmt_p2 = $pdo->prepare("INSERT INTO tbl_payment (customer_id, customer_name, customer_email, payment_date, txnid, paid_amount, card_number, card_cvv, card_month, card_year, bank_transaction_info, payment_method, payment_status, shipping_status, payment_id, supplier_id) VALUES (?, 'Filter Test Client', ?, ?, ?, 8500.00, '', '', '', '', 'Over the counter payment', 'Cash (OTC)', 'Paid', 'Completed', ?, ?)");
$stmt_p2->execute([$test_cust_id, $test_cust_email, $date2, $po2_num, $po2_num, $supplier_a_id]);
$stmt_get2 = $pdo->prepare("SELECT id FROM tbl_payment WHERE payment_id = ?");
$stmt_get2->execute([$po2_num]);
$po2_id = (int)$stmt_get2->fetchColumn();

$stmt_o2 = $pdo->prepare("INSERT INTO tbl_order (product_id, product_name, size, color, quantity, unit_price, payment_id, supplier_id, item_type) VALUES (2, 'Deformed Steel Bar 16mm', '6m', 'Standard', '10', '850.00', ?, ?, 'STANDARD')");
$stmt_o2->execute([$po2_num, $supplier_a_id]);

// Order 3: Last Month (2026-08-15) - Paid
$po3_num = 'PO-FILTER-20260815-003-' . substr(md5(uniqid()), 0, 4);
$date3 = '2026-08-15 09:00:00';
$stmt_p3 = $pdo->prepare("INSERT INTO tbl_payment (customer_id, customer_name, customer_email, payment_date, txnid, paid_amount, card_number, card_cvv, card_month, card_year, bank_transaction_info, payment_method, payment_status, shipping_status, payment_id, supplier_id) VALUES (?, 'Filter Test Client', ?, ?, ?, 4200.00, '', '', '', '', '', 'Purchase Order (PO)', 'Paid', 'Completed', ?, ?)");
$stmt_p3->execute([$test_cust_id, $test_cust_email, $date3, $po3_num, $po3_num, $supplier_a_id]);
$stmt_get3 = $pdo->prepare("SELECT id FROM tbl_payment WHERE payment_id = ?");
$stmt_get3->execute([$po3_num]);
$po3_id = (int)$stmt_get3->fetchColumn();

$stmt_o3 = $pdo->prepare("INSERT INTO tbl_order (product_id, product_name, size, color, quantity, unit_price, payment_id, supplier_id, item_type) VALUES (3, 'Marine Plywood 1/2', '4x8ft', 'Natural', '6', '700.00', ?, ?, 'STANDARD')");
$stmt_o3->execute([$po3_num, $supplier_a_id]);

// Order 4: Supplier B Order (2026-09-07) - Multi-tenant check
$po_supb_num = 'PO-FILTER-SUPB-20260907-' . substr(md5(uniqid()), 0, 4);
$stmt_pb = $pdo->prepare("INSERT INTO tbl_payment (customer_id, customer_name, customer_email, payment_date, txnid, paid_amount, card_number, card_cvv, card_month, card_year, bank_transaction_info, payment_method, payment_status, shipping_status, payment_id, supplier_id) VALUES (?, 'Supplier B Client', 'supb.client@test.com', '2026-09-07 11:00:00', ?, 99000.00, '', '', '', '', '', 'Bank Deposit', 'Awaiting for Payment', 'Pending', ?, ?)");
$stmt_pb->execute([$test_cust_id, $po_supb_num, $po_supb_num, $supplier_b_id]);
$stmt_getb = $pdo->prepare("SELECT id FROM tbl_payment WHERE payment_id = ?");
$stmt_getb->execute([$po_supb_num]);
$po_supb_id = (int)$stmt_getb->fetchColumn();

assert_test("PO 1 created (#$po1_id: $po1_num, 2026-09-07)", $po1_id > 0);
assert_test("PO 2 created (#$po2_id: $po2_num, 2026-09-06)", $po2_id > 0);
assert_test("PO 3 created (#$po3_id: $po3_num, 2026-08-15)", $po3_id > 0);
assert_test("Supplier B PO created (#$po_supb_id: $po_supb_num)", $po_supb_id > 0);

// Helper function to render order.php with GET parameters and session
function render_order_page($supplier_id, $get_params = []) {
    global $pdo;
    $_SESSION['supplier'] = [
        'id' => $supplier_id,
        'supplier_id' => $supplier_id,
        'supplier_name' => 'Platform Supplies Co',
        'role' => 'Supplier Admin'
    ];
    $_GET = $get_params;
    
    ob_start();
    $supplier_id = $supplier_id; // local var for script
    $order_file = file_exists(__DIR__ . '/supplier/order.php') ? __DIR__ . '/supplier/order.php' : __DIR__ . '/../supplier/order.php';
    include $order_file;
    return ob_get_clean();
}

// -------------------------------------------------------------
// 3. Test: Display ALL Orders (period=all&status=all) & ID DESC Order
// -------------------------------------------------------------
echo "\n--- 3. Testing All Orders Display & ID Descending Order ---\n";
$html_all = render_order_page($supplier_a_id, ['period' => 'all', 'status' => 'all']);

assert_test("Rendered All Orders HTML is non-empty", !empty($html_all));
assert_test("PO 1 (#$po1_id) is present in All Orders table", strpos($html_all, $po1_num) !== false);
assert_test("PO 2 (#$po2_id) is present in All Orders table", strpos($html_all, $po2_num) !== false);
assert_test("PO 3 (#$po3_id) is present in All Orders table", strpos($html_all, $po3_num) !== false);

// Check that records appear in descending order of ID (Highest ID first)
$pos1 = strpos($html_all, $po1_num);
$pos2 = strpos($html_all, $po2_num);
$pos3 = strpos($html_all, $po3_num);

// Verify descending order: larger ID must appear at a smaller string index in HTML
if ($po3_id > $po2_id) {
    assert_test("Order with higher ID #$po3_id appears before lower ID #$po2_id (DESC order)", $pos3 < $pos2);
}
if ($po2_id > $po1_id) {
    assert_test("Order with higher ID #$po2_id appears before lower ID #$po1_id (DESC order)", $pos2 < $pos1);
}

// Multi-tenant check: Supplier A CANNOT see Supplier B's order
assert_test("Supplier A CANNOT see Supplier B's PO ($po_supb_num)", strpos($html_all, $po_supb_num) === false);

// -------------------------------------------------------------
// 4. Test: Day Filter (period=day)
// -------------------------------------------------------------
echo "\n--- 4. Testing Day Filter (period=day) ---\n";
// Filter for 2026-09-07 (Should contain PO 1, exclude PO 2 & PO 3)
$html_day1 = render_order_page($supplier_a_id, ['period' => 'day', 'day_date' => '2026-09-07', 'status' => 'all']);
assert_test("Day Filter 2026-09-07 includes PO 1 ($po1_num)", strpos($html_day1, $po1_num) !== false);
assert_test("Day Filter 2026-09-07 excludes PO 2 ($po2_num)", strpos($html_day1, $po2_num) === false);
assert_test("Day Filter 2026-09-07 excludes PO 3 ($po3_num)", strpos($html_day1, $po3_num) === false);

// Filter for 2026-09-06 (Should contain PO 2, exclude PO 1 & PO 3)
$html_day2 = render_order_page($supplier_a_id, ['period' => 'day', 'day_date' => '2026-09-06', 'status' => 'all']);
assert_test("Day Filter 2026-09-06 includes PO 2 ($po2_num)", strpos($html_day2, $po2_num) !== false);
assert_test("Day Filter 2026-09-06 excludes PO 1 ($po1_num)", strpos($html_day2, $po1_num) === false);
assert_test("Day Filter 2026-09-06 excludes PO 3 ($po3_num)", strpos($html_day2, $po3_num) === false);

// -------------------------------------------------------------
// 5. Test: Week Filter (period=week)
// -------------------------------------------------------------
echo "\n--- 5. Testing Week Filter (period=week) ---\n";
// 2026-09-07 is Monday of Week 37 (2026-09-07 to 2026-09-13)
$html_week37 = render_order_page($supplier_a_id, ['period' => 'week', 'year' => 2026, 'week' => 37, 'status' => 'all']);
assert_test("Week 37 Filter includes PO 1 ($po1_num on 2026-09-07)", strpos($html_week37, $po1_num) !== false);
assert_test("Week 37 Filter excludes PO 2 ($po2_num on 2026-09-06 in Week 36)", strpos($html_week37, $po2_num) === false);
assert_test("Week 37 Filter excludes PO 3 ($po3_num on 2026-08-15 in Week 33)", strpos($html_week37, $po3_num) === false);

// 2026-09-06 is Sunday of Week 36 (2026-08-31 to 2026-09-06)
$html_week36 = render_order_page($supplier_a_id, ['period' => 'week', 'year' => 2026, 'week' => 36, 'status' => 'all']);
assert_test("Week 36 Filter includes PO 2 ($po2_num on 2026-09-06)", strpos($html_week36, $po2_num) !== false);
assert_test("Week 36 Filter excludes PO 1 ($po1_num in Week 37)", strpos($html_week36, $po1_num) === false);

// -------------------------------------------------------------
// 6. Test: Month Filter (period=month)
// -------------------------------------------------------------
echo "\n--- 6. Testing Month Filter (period=month) ---\n";
// September 2026 (Month 9): Should include PO 1 (Sep 7) & PO 2 (Sep 6), exclude PO 3 (Aug 15)
$html_month9 = render_order_page($supplier_a_id, ['period' => 'month', 'year' => 2026, 'month' => 9, 'status' => 'all']);
assert_test("Month 9 Filter includes PO 1 ($po1_num)", strpos($html_month9, $po1_num) !== false);
assert_test("Month 9 Filter includes PO 2 ($po2_num)", strpos($html_month9, $po2_num) !== false);
assert_test("Month 9 Filter excludes PO 3 ($po3_num from August)", strpos($html_month9, $po3_num) === false);

// August 2026 (Month 8): Should include PO 3 (Aug 15), exclude PO 1 & PO 2
$html_month8 = render_order_page($supplier_a_id, ['period' => 'month', 'year' => 2026, 'month' => 8, 'status' => 'all']);
assert_test("Month 8 Filter includes PO 3 ($po3_num)", strpos($html_month8, $po3_num) !== false);
assert_test("Month 8 Filter excludes PO 1 ($po1_num)", strpos($html_month8, $po1_num) === false);
assert_test("Month 8 Filter excludes PO 2 ($po2_num)", strpos($html_month8, $po2_num) === false);

// -------------------------------------------------------------
// 7. Test: Status Filter (status=awaiting vs status=paid)
// -------------------------------------------------------------
echo "\n--- 7. Testing Status Filter Combined with All Time ---\n";
// Awaiting Payment (Should include PO 1, exclude PO 2 & PO 3 which are Paid)
$html_awaiting = render_order_page($supplier_a_id, ['period' => 'all', 'status' => 'awaiting']);
assert_test("Status Awaiting includes PO 1 ($po1_num)", strpos($html_awaiting, $po1_num) !== false);
assert_test("Status Awaiting excludes PO 2 ($po2_num - Paid)", strpos($html_awaiting, $po2_num) === false);
assert_test("Status Awaiting excludes PO 3 ($po3_num - Paid)", strpos($html_awaiting, $po3_num) === false);

// Paid / Completed (Should include PO 2 & PO 3, exclude PO 1 which is Awaiting Payment)
$html_paid = render_order_page($supplier_a_id, ['period' => 'all', 'status' => 'paid']);
assert_test("Status Paid includes PO 2 ($po2_num)", strpos($html_paid, $po2_num) !== false);
assert_test("Status Paid includes PO 3 ($po3_num)", strpos($html_paid, $po3_num) !== false);
assert_test("Status Paid excludes PO 1 ($po1_num - Awaiting Payment)", strpos($html_paid, $po1_num) === false);

// -------------------------------------------------------------
// 8. Test: Customer Purchase Order Voucher Modal for Filtered PO
// -------------------------------------------------------------
echo "\n--- 8. Testing Customer PO Voucher Modal Population ---\n";
assert_test("Voucher Modal container exists for PO 1 (#po-modal-$po1_id)", strpos($html_all, "id=\"po-modal-$po1_id\"") !== false);
assert_test("PO 1 Item 'Portland Cement Premium' present in modal", strpos($html_all, 'Portland Cement Premium') !== false);
assert_test("PO 1 Grand Total ₱15,000.00 present in modal", strpos($html_all, '15,000.00') !== false);

// -------------------------------------------------------------
// 9. Clean up test records
// -------------------------------------------------------------
$pdo->prepare("DELETE FROM tbl_order WHERE payment_id IN (?, ?, ?, ?)")->execute([$po1_num, $po2_num, $po3_num, $po_supb_num]);
$pdo->prepare("DELETE FROM tbl_payment WHERE id IN (?, ?, ?, ?)")->execute([$po1_id, $po2_id, $po3_id, $po_supb_id]);
$pdo->prepare("DELETE FROM tbl_customer WHERE cust_id = ?")->execute([$test_cust_id]);

echo "\n=======================================================\n";
echo "TEST RESULTS: $passed PASSED, $failed FAILED\n";
echo "=======================================================\n";

if ($failed > 0) {
    exit(1);
}
