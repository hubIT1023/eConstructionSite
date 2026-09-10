<?php
/**
 * Automated Subprocess Verification for pos-return-api.php
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/functions.php';

echo "=== STARTING COMPREHENSIVE POS RETURN API VERIFICATION ===\n\n";

$tests_passed = 0;
$tests_total = 0;

function assert_test($description, $condition, $details = '') {
    global $tests_passed, $tests_total;
    $tests_total++;
    if ($condition) {
        $tests_passed++;
        echo "[PASS] {$description}\n";
    } else {
        echo "[FAIL] {$description}";
        if ($details) {
            echo " -> Details: " . $details;
        }
        echo "\n";
    }
}

function run_api_subprocess($user, $get_params = [], $post_params = []) {
    $runner_script = __DIR__ . '/scratch_api_runner.php';
    $payload_file = tempnam(sys_get_temp_dir(), 'api_pay_');

    $payload = [
        'user' => $user,
        'get' => $get_params,
        'post' => $post_params
    ];
    file_put_contents($payload_file, json_encode($payload));

    $runner_code = '<?php
        session_start();
        $payload = json_decode(file_get_contents($argv[1]), true);
        $_SESSION["supplier_user"] = $payload["user"];
        $_SESSION["user"] = ["id" => $payload["user"]["supplier_id"]];
        $_GET = $payload["get"] ?? [];
        $_POST = $payload["post"] ?? [];
        $_REQUEST = array_merge($_GET, $_POST);
        $_SERVER["REQUEST_METHOD"] = !empty($_POST) ? "POST" : "GET";
        chdir(__DIR__);
        include __DIR__ . "/pos-return-api.php";
    ';
    file_put_contents($runner_script, $runner_code);

    $cmd = "php " . escapeshellarg($runner_script) . " " . escapeshellarg($payload_file);
    $output = shell_exec($cmd);

    @unlink($payload_file);
    @unlink($runner_script);

    return json_decode(trim($output), true);
}

try {
    // 1. Setup Test Data
    $stmt = $pdo->query("SELECT supplier_id FROM tbl_supplier ORDER BY supplier_id ASC LIMIT 1");
    $supplier_id = (int)$stmt->fetchColumn();
    if (!$supplier_id) $supplier_id = 1;

    $cashier_user = [
        'id' => 99911,
        'full_name' => 'Jane Cashier',
        'email' => 'cashier@test.com',
        'role' => 'Cashier',
        'supplier_id' => $supplier_id
    ];

    $manager_user = [
        'id' => 99922,
        'full_name' => 'Bob Manager',
        'email' => 'manager@test.com',
        'role' => 'Manager',
        'supplier_id' => $supplier_id
    ];

    $supervisor_user = [
        'id' => 99944,
        'full_name' => 'Sam Supervisor',
        'email' => 'supervisor@test.com',
        'role' => 'Supervisor',
        'supplier_id' => $supplier_id
    ];

    $admin_user = [
        'id' => 99933,
        'full_name' => 'Alice Admin',
        'email' => 'admin@test.com',
        'role' => 'Admin',
        'supplier_id' => $supplier_id
    ];

    // Create a product for testing
    $unit_price = 220.00;
    $initial_stock = 75;
    $stmt = $pdo->prepare("INSERT INTO tbl_product (p_name, p_old_price, p_current_price, p_qty, p_featured_photo, p_description, p_short_description, p_feature, p_condition, p_return_policy, p_total_view, p_is_featured, p_is_active, ecat_id, supplier_id) VALUES (?, ?, ?, ?, 'default.jpg', 'Test', 'Test', 'Test', 'New', 'Returnable', 0, 0, 1, 1, ?) RETURNING p_id");
    $stmt->execute(["API Subprocess Product " . time(), $unit_price, $unit_price, $initial_stock, $supplier_id]);
    $p_id = (int)$stmt->fetchColumn();

    // Create a paid order
    $payment_id = "PAY-SUB-" . time();
    $purchased_qty = 5;
    $order_total = $purchased_qty * $unit_price;

    $stmt = $pdo->prepare("INSERT INTO tbl_payment (customer_id, customer_name, customer_email, payment_date, txnid, paid_amount, card_number, card_cvv, card_month, card_year, bank_transaction_info, payment_method, payment_status, shipping_status, payment_id, supplier_id) VALUES (?, ?, ?, ?, ?, ?, '', '', '', '', '', 'Cash', 'Completed', 'Delivered', ?, ?)");
    $stmt->execute([
        4004,
        'Elena Gomez',
        'elena@example.com',
        date('Y-m-d H:i:s'),
        'TXN-' . time(),
        $order_total,
        $payment_id,
        $supplier_id
    ]);

    $stmt = $pdo->prepare("INSERT INTO tbl_order (product_id, product_name, size, color, quantity, unit_price, payment_id, supplier_id, item_type) VALUES (?, ?, 'Standard', 'Default', ?, ?, ?, ?, 'standard_product') RETURNING id");
    $stmt->execute([
        $p_id,
        "API Subprocess Product",
        $purchased_qty,
        $unit_price,
        $payment_id,
        $supplier_id
    ]);
    $order_item_id = (int)$stmt->fetchColumn();

    // Suite 1: Action = search_orders
    echo "--- Suite 1: Order Lookup & Return Quantity Calculation ---\n";
    $res = run_api_subprocess($cashier_user, [
        'action' => 'search_orders',
        'keyword' => $payment_id
    ]);

    assert_test("API search_orders status is success", ($res['status'] ?? '') === 'success');
    assert_test("API returned matching order", ($res['orders'][0]['payment_id'] ?? '') === $payment_id);
    assert_test("Item quantity returned matches purchased qty (5)", ($res['orders'][0]['items'][0]['purchased_qty'] ?? 0) === $purchased_qty);
    assert_test("Item available_to_return is 5", ($res['orders'][0]['items'][0]['available_to_return'] ?? 0) === $purchased_qty);

    // Suite 2: Action = get_order_details
    echo "\n--- Suite 2: Get Order Details ---\n";
    $res = run_api_subprocess($cashier_user, [
        'action' => 'get_order_details',
        'payment_id' => $payment_id
    ]);
    assert_test("get_order_details status is success", ($res['status'] ?? '') === 'success');
    assert_test("Order has correct customer name 'Elena Gomez'", ($res['order']['customer_name'] ?? '') === 'Elena Gomez');
    assert_test("Items array contains 1 item", count($res['order']['items'] ?? []) === 1);

    // Suite 3: Cashier Submits Return Request
    echo "\n--- Suite 3: Submit Return Request (Cashier) ---\n";
    $return_qty_good = 2;
    $res = run_api_subprocess($cashier_user, [], [
        'action' => 'submit_return_request',
        'order_item_id' => $order_item_id,
        'payment_id' => $payment_id,
        'return_quantity' => $return_qty_good,
        'condition' => 'Good / Resalable',
        'return_reason' => 'Customer Return / Exchange',
        'refund_method' => 'Cash',
        'general_notes' => 'Customer returning 2 units in pristine condition'
    ]);

    assert_test("submit_return_request returned success", ($res['status'] ?? '') === 'success');
    assert_test("Status code is strictly 'PENDING_APPROVAL'", ($res['status_code'] ?? '') === 'PENDING_APPROVAL');
    $return_id = (int)($res['return_id'] ?? 0);
    $return_ref = $res['return_reference'] ?? '';
    assert_test("Return ID generated ({$return_id})", $return_id > 0);
    assert_test("Return reference format valid ({$return_ref})", strpos($return_ref, 'RET-') === 0);

    // Critical check: Product inventory must NOT change while PENDING_APPROVAL
    $stmt = $pdo->prepare("SELECT p_qty FROM tbl_product WHERE p_id = ?");
    $stmt->execute([$p_id]);
    $stock_pending = (int)$stmt->fetchColumn();
    assert_test("Product inventory unchanged during PENDING_APPROVAL ({$stock_pending} === {$initial_stock})", $stock_pending === $initial_stock);

    // Suite 4: Security & Role-Based Self-Approval Enforcement
    echo "\n--- Suite 4: Role-Based Self-Approval Enforcement ---\n";
    // Test A: Cashier attempts to approve -> Blocked by role
    $res = run_api_subprocess($cashier_user, [], [
        'action' => 'approve_return',
        'return_id' => $return_id,
        'approver_remarks' => 'Cashier attempt'
    ]);
    assert_test("Cashier cannot approve return request", ($res['status'] ?? '') === 'error');

    // Test B: Manager who requested return attempts to approve -> Blocked
    $stmt = $pdo->prepare("UPDATE tbl_returns SET requested_by_id = ? WHERE return_id = ?");
    $stmt->execute([$manager_user['id'], $return_id]);

    $res = run_api_subprocess($manager_user, [], [
        'action' => 'approve_return',
        'return_id' => $return_id,
        'approver_remarks' => 'Manager self approval attempt'
    ]);
    assert_test("Manager self-approval is strictly blocked", ($res['status'] ?? '') === 'error');
    assert_test("Manager self-approval returns SELF_APPROVAL_PROHIBITED", ($res['error_code'] ?? '') === 'SELF_APPROVAL_PROHIBITED');

    // Test C: Supervisor who requested return attempts to approve -> Blocked
    $stmt = $pdo->prepare("UPDATE tbl_returns SET requested_by_id = ? WHERE return_id = ?");
    $stmt->execute([$supervisor_user['id'], $return_id]);

    $res = run_api_subprocess($supervisor_user, [], [
        'action' => 'approve_return',
        'return_id' => $return_id,
        'approver_remarks' => 'Supervisor self approval attempt'
    ]);
    assert_test("Supervisor self-approval is strictly blocked", ($res['status'] ?? '') === 'error');
    assert_test("Supervisor self-approval returns SELF_APPROVAL_PROHIBITED", ($res['error_code'] ?? '') === 'SELF_APPROVAL_PROHIBITED');

    // Test D: Admin who requested return attempts to approve -> ALLOWED!
    $stmt = $pdo->prepare("UPDATE tbl_returns SET requested_by_id = ? WHERE return_id = ?");
    $stmt->execute([$admin_user['id'], $return_id]);

    $res = run_api_subprocess($admin_user, [], [
        'action' => 'approve_return',
        'return_id' => $return_id,
        'approver_remarks' => 'Admin self-approved return'
    ]);
    assert_test("Admin supplier self-approval is ALLOWED and succeeds", ($res['status'] ?? '') === 'success');
    assert_test("Admin self-approved return status is 'COMPLETED'", ($res['status_code'] ?? '') === 'COMPLETED');

    // Verify DB inventory restocked by 2
    $stmt = $pdo->prepare("SELECT p_qty FROM tbl_product WHERE p_id = ?");
    $stmt->execute([$p_id]);
    $stock_after_approval = (int)$stmt->fetchColumn();
    $expected_stock_app = $initial_stock + $return_qty_good;
    assert_test("Inventory successfully restocked ({$stock_after_approval} === {$expected_stock_app})", $stock_after_approval === $expected_stock_app);

    // Suite 5: Thermal Return Receipt Generation
    echo "\n--- Suite 5: Thermal Receipt Generation ---\n";
    $res = run_api_subprocess($cashier_user, [
        'action' => 'get_return_receipt',
        'return_id' => $return_id
    ]);

    assert_test("get_return_receipt returns success", ($res['status'] ?? '') === 'success');
    assert_test("Receipt has correct return_reference", ($res['receipt']['return_reference'] ?? '') === $return_ref);
    assert_test("Receipt has correct approver name 'Alice Admin'", ($res['receipt']['approver_name'] ?? '') === 'Alice Admin');
    assert_test("Receipt has total refund amount ₱440.00", ($res['receipt']['refund_amount'] ?? 0) == ($return_qty_good * $unit_price));

    // Suite 6: Damaged Item Rejection Workflow
    echo "\n--- Suite 6: Rejection Workflow (Damaged Item) ---\n";
    $return_qty_dmg = 1;
    $res = run_api_subprocess($cashier_user, [], [
        'action' => 'submit_return_request',
        'order_item_id' => $order_item_id,
        'payment_id' => $payment_id,
        'return_quantity' => $return_qty_dmg,
        'condition' => 'Damaged / Defective',
        'return_reason' => 'Physical Damage',
        'refund_method' => 'Cash',
        'general_notes' => 'Customer dropped item after unboxing'
    ]);

    $return_dmg_id = (int)($res['return_id'] ?? 0);
    assert_test("Damaged return request submitted with ID {$return_dmg_id}", $return_dmg_id > 0);

    // Manager rejects this request with reason
    $rejection_reason = "Physical damage caused after delivery outside warranty coverage";
    $res = run_api_subprocess($manager_user, [], [
        'action' => 'reject_return',
        'return_id' => $return_dmg_id,
        'rejection_reason' => $rejection_reason
    ]);

    assert_test("Manager rejection succeeded", ($res['status'] ?? '') === 'success');

    // Verify in DB status is REJECTED
    $stmt = $pdo->prepare("SELECT status, rejection_reason FROM tbl_returns WHERE return_id = ?");
    $stmt->execute([$return_dmg_id]);
    $rej_db = $stmt->fetch(PDO::FETCH_ASSOC);
    assert_test("Status in DB is 'REJECTED'", ($rej_db['status'] ?? '') === 'REJECTED');
    assert_test("Rejection reason matches", ($rej_db['rejection_reason'] ?? '') === $rejection_reason);

    // Verify stock remains untouched on rejection
    $stmt = $pdo->prepare("SELECT p_qty FROM tbl_product WHERE p_id = ?");
    $stmt->execute([$p_id]);
    $stock_after_rej = (int)$stmt->fetchColumn();
    assert_test("Product inventory unchanged on rejection ({$stock_after_rej} === {$expected_stock_app})", $stock_after_rej === $expected_stock_app);

    // Verify restock_status on tbl_return_items
    $stmt = $pdo->prepare("SELECT restock_status FROM tbl_return_items WHERE return_id = ?");
    $stmt->execute([$return_dmg_id]);
    assert_test("Return item restock_status is 'NOT_RESTOCKED'", $stmt->fetchColumn() === 'NOT_RESTOCKED');

    // Suite 7: Multi-tenant Isolation Test
    echo "\n--- Suite 7: Multi-Tenant Data Isolation ---\n";
    $other_supplier_user = [
        'id' => 99999,
        'full_name' => 'Other Supplier Staff',
        'email' => 'other@test.com',
        'role' => 'Admin',
        'supplier_id' => $supplier_id + 888
    ];

    $res = run_api_subprocess($other_supplier_user, [
        'action' => 'get_return_details',
        'return_id' => $return_id
    ]);
    assert_test("Other supplier cannot access return details", ($res['status'] ?? '') === 'error');

    $res = run_api_subprocess($other_supplier_user, [
        'action' => 'search_orders',
        'keyword' => $payment_id
    ]);
    assert_test("Other supplier cannot search or view orders from Supplier {$supplier_id}", count($res['orders'] ?? []) === 0);

    // Clean up test records
    $pdo->prepare("DELETE FROM tbl_return_items WHERE return_id IN (?, ?)")->execute([$return_id, $return_dmg_id]);
    $pdo->prepare("DELETE FROM tbl_returns WHERE return_id IN (?, ?)")->execute([$return_id, $return_dmg_id]);
    $pdo->prepare("DELETE FROM tbl_order WHERE id = ?")->execute([$order_item_id]);
    $pdo->prepare("DELETE FROM tbl_payment WHERE payment_id = ?")->execute([$payment_id]);
    $pdo->prepare("DELETE FROM tbl_product WHERE p_id = ?")->execute([$p_id]);
    echo "\nSubprocess Verification Cleanup Completed.\n";

} catch (Exception $e) {
    echo "\n[ERROR] " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}

echo "\n=======================================================\n";
echo "SUBPROCESS VERIFICATION SUMMARY: {$tests_passed} / {$tests_total} tests passed.\n";
echo "=======================================================\n";
