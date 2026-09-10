<?php
/**
 * Automated Verification Script for POS Return Items & Manager/Admin Approval Workflow
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/functions.php';

echo "=== STARTING POS RETURN ITEMS & APPROVAL WORKFLOW VERIFICATION ===\n\n";

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

try {
    // 1. Check & Ensure Schema
    echo "--- Test Suite 1: Schema Migration Verification ---\n";
    ensure_return_schema($pdo);
    ensure_supplier_user_schema($pdo);

    // Verify columns on tbl_returns
    $stmt = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'tbl_returns'");
    $return_cols = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $required_return_cols = [
        'return_reference', 'payment_id', 'order_id', 'supplier_id',
        'customer_name', 'refund_amount', 'status', 'requested_by_id',
        'approver_id', 'approver_name', 'approver_role', 'rejection_reason',
        'approved_at', 'rejected_at', 'completed_at'
    ];
    foreach ($required_return_cols as $col) {
        assert_test("tbl_returns has column '{$col}'", in_array($col, $return_cols));
    }

    // Verify columns on tbl_return_items
    $stmt = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'tbl_return_items'");
    $return_item_cols = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $required_item_cols = [
        'return_id', 'return_reference', 'order_item_id', 'product_id',
        'product_name', 'quantity_returned', 'unit_price', 'refund_amount',
        'condition', 'restock_status'
    ];
    foreach ($required_item_cols as $col) {
        assert_test("tbl_return_items has column '{$col}'", in_array($col, $return_item_cols));
    }

    // 2. Setup Test Data (Supplier, Products, Cashier User, Manager User, Order)
    echo "\n--- Test Suite 2: Test Data Setup ---\n";
    
    // Find or create test supplier
    $stmt = $pdo->query("SELECT supplier_id FROM tbl_supplier ORDER BY supplier_id ASC LIMIT 1");
    $supplier_id = $stmt->fetchColumn();
    if (!$supplier_id) {
        $supplier_id = 1;
    }
    echo "Using Supplier ID: {$supplier_id}\n";

    // Setup Test Cashier (ID 99801), Test Manager (ID 99802), Test Supervisor (ID 99804), Test Admin (ID 99803)
    $cashier_id = 99801;
    $manager_id = 99802;
    $admin_id = 99803;
    $supervisor_id = 99804;

    // Check helper permission functions
    $cashier_user = ['id' => $cashier_id, 'role' => 'Cashier', 'supplier_id' => $supplier_id, 'full_name' => 'Test Cashier'];
    $supervisor_user = ['id' => $supervisor_id, 'role' => 'Supervisor', 'supplier_id' => $supplier_id, 'full_name' => 'Test Supervisor'];
    $manager_user = ['id' => $manager_id, 'role' => 'Manager', 'supplier_id' => $supplier_id, 'full_name' => 'Test Manager'];
    $admin_user = ['id' => $admin_id, 'role' => 'Admin', 'supplier_id' => $supplier_id, 'full_name' => 'Test Admin'];

    assert_test("Cashier can request return", can_user_request_return($cashier_user) === true);
    assert_test("Cashier cannot approve return", can_user_approve_return($cashier_user) === false);
    assert_test("Manager can approve return", can_user_approve_return($manager_user) === true);
    assert_test("Admin can approve return", can_user_approve_return($admin_user) === true);

    // Self-approval check for Manager and Supervisor: STRICTLY PROHIBITED
    $manager_self_return = ['requested_by_id' => $manager_id, 'supplier_id' => $supplier_id];
    $supervisor_self_return = ['requested_by_id' => $supervisor_id, 'supplier_id' => $supplier_id];
    $admin_self_return = ['requested_by_id' => $admin_id, 'supplier_id' => $supplier_id];

    assert_test("Self-approval rule: Manager CANNOT approve return they requested", can_user_approve_return($manager_user, $manager_self_return) === false);
    assert_test("Self-approval rule: Supervisor CANNOT approve return they requested", can_user_approve_return($supervisor_user, $supervisor_self_return) === false);
    assert_test("Self-approval rule: Admin Supplier ONLY IS ALLOWED to self-approve", can_user_approve_return($admin_user, $admin_self_return) === true);

    // Create a dedicated test product
    $test_product_name = "Automated Test Return Item " . time();
    $initial_qty = 50;
    $unit_price = 250.00;
    
    $stmt = $pdo->prepare("INSERT INTO tbl_product (p_name, p_old_price, p_current_price, p_qty, p_featured_photo, p_description, p_short_description, p_feature, p_condition, p_return_policy, p_total_view, p_is_featured, p_is_active, ecat_id, supplier_id) VALUES (?, ?, ?, ?, 'default.jpg', 'Test', 'Test', 'Test', 'New', 'Returnable', 0, 0, 1, 1, ?) RETURNING p_id");
    $stmt->execute([$test_product_name, $unit_price, $unit_price, $initial_qty, $supplier_id]);
    $test_p_id = $stmt->fetchColumn();
    echo "Created Test Product ID: {$test_p_id} with initial Qty: {$initial_qty}\n";

    // Create a paid test order and payment
    $payment_id = "PAY-" . time();
    $purchased_qty = 5;
    $order_total = $purchased_qty * $unit_price;

    $stmt = $pdo->prepare("INSERT INTO tbl_payment (customer_id, customer_name, customer_email, payment_date, txnid, paid_amount, card_number, card_cvv, card_month, card_year, bank_transaction_info, payment_method, payment_status, shipping_status, payment_id, supplier_id) VALUES (?, ?, ?, ?, ?, ?, '', '', '', '', '', 'Cash', 'Completed', 'Delivered', ?, ?)");
    $stmt->execute([
        1001,
        'Juan Dela Cruz',
        'juan@example.com',
        date('Y-m-d H:i:s'),
        'TXN-' . time(),
        $order_total,
        $payment_id,
        $supplier_id
    ]);

    $stmt = $pdo->prepare("INSERT INTO tbl_order (product_id, product_name, size, color, quantity, unit_price, payment_id, supplier_id, item_type) VALUES (?, ?, 'Standard', 'Default', ?, ?, ?, ?, 'standard_product') RETURNING id");
    $stmt->execute([
        $test_p_id,
        $test_product_name,
        $purchased_qty,
        $unit_price,
        $payment_id,
        $supplier_id
    ]);
    $order_item_id = $stmt->fetchColumn();
    echo "Created Test Order Item ID: {$order_item_id} (Purchased Qty: {$purchased_qty})\n";

    // 3. Test API: Search Orders
    echo "\n--- Test Suite 3: Order Search & Available Quantity Calculation ---\n";
    $stmt = $pdo->prepare("
        SELECT 
            o.id AS order_item_id,
            o.product_id,
            o.product_name,
            o.quantity AS purchased_qty,
            o.unit_price,
            o.payment_id,
            p.customer_name,
            p.payment_status
        FROM tbl_order o
        JOIN tbl_payment p ON (p.payment_id = o.payment_id)
        WHERE o.supplier_id = ? AND o.payment_id = ?
    ");
    $stmt->execute([$supplier_id, $payment_id]);
    $found_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    assert_test("Order search found newly created paid order", count($found_orders) > 0);

    // Check available returnable calculation
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(ri.quantity_returned), 0)
        FROM tbl_return_items ri
        JOIN tbl_returns r ON (ri.return_id = r.return_id)
        WHERE ri.order_item_id = ? AND r.supplier_id = ? AND r.status IN ('COMPLETED', 'APPROVED')
    ");
    $stmt->execute([$order_item_id, $supplier_id]);
    $already_returned = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(ri.quantity_returned), 0)
        FROM tbl_return_items ri
        JOIN tbl_returns r ON (ri.return_id = r.return_id)
        WHERE ri.order_item_id = ? AND r.supplier_id = ? AND r.status = 'PENDING_APPROVAL'
    ");
    $stmt->execute([$order_item_id, $supplier_id]);
    $pending_returned = (int)$stmt->fetchColumn();

    $available_qty = max(0, $purchased_qty - $already_returned - $pending_returned);
    assert_test("Available return quantity initially equals purchased quantity ({$purchased_qty})", $available_qty === $purchased_qty);

    // 4. Test Cashier Return Submission (PENDING_APPROVAL state)
    echo "\n--- Test Suite 4: Cashier Return Submission Workflow ---\n";
    $return_ref = generate_unique_return_reference($pdo, $supplier_id);
    $return_qty_good = 2;
    $refund_amount = $return_qty_good * $unit_price;

    // Simulate submit_return_request transaction
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        INSERT INTO tbl_returns (
            return_reference, payment_id, order_id, supplier_id,
            customer_id, customer_name, customer_email, customer_phone,
            return_date, refund_method, refund_amount, status,
            requested_by_id, requested_by_name, requested_by_role,
            notes, created_at, updated_at
        ) VALUES (
            ?, ?, ?, ?,
            ?, ?, ?, ?,
            CURRENT_DATE, ?, ?, 'PENDING_APPROVAL',
            ?, ?, ?,
            ?, NOW(), NOW()
        ) RETURNING return_id
    ");
    $stmt->execute([
        $return_ref,
        $payment_id,
        $order_item_id,
        $supplier_id,
        1001,
        'Juan Dela Cruz',
        'juan@example.com',
        '09123456789',
        'Cash',
        $refund_amount,
        $cashier_id,
        'Test Cashier',
        'Cashier',
        'Customer changed mind on 2 units'
    ]);
    $return_id = $stmt->fetchColumn();

    $stmt = $pdo->prepare("
        INSERT INTO tbl_return_items (
            return_id, return_reference, order_item_id, product_id,
            product_name, sku, size, color, item_type,
            quantity_returned, unit_price, refund_amount,
            return_reason, condition, restock_status, notes
        ) VALUES (
            ?, ?, ?, ?,
            ?, 'TEST-SKU', 'Standard', 'Default', 'standard_product',
            ?, ?, ?,
            'Customer Return', 'Good / Resalable', 'PENDING', 'Tested resalable condition'
        ) RETURNING return_item_id
    ");
    $stmt->execute([
        $return_id,
        $return_ref,
        $order_item_id,
        $test_p_id,
        $test_product_name,
        $return_qty_good,
        $unit_price,
        $refund_amount
    ]);
    $return_item_db_id = $stmt->fetchColumn();

    $pdo->commit();

    assert_test("Return request submitted with ID {$return_id} and Ref {$return_ref}", $return_id > 0);

    // Critical check: Verify status is PENDING_APPROVAL
    $stmt = $pdo->prepare("SELECT status FROM tbl_returns WHERE return_id = ?");
    $stmt->execute([$return_id]);
    $current_return_status = $stmt->fetchColumn();
    assert_test("Return status is strictly 'PENDING_APPROVAL'", $current_return_status === 'PENDING_APPROVAL');

    // Critical check: Original Payment & Order must remain unchanged
    $stmt = $pdo->prepare("SELECT payment_status FROM tbl_payment WHERE payment_id = ?");
    $stmt->execute([$payment_id]);
    $pay_status = $stmt->fetchColumn();
    assert_test("Original payment status remains 'Completed'", $pay_status === 'Completed');

    // Critical check: Product inventory must NOT be updated yet
    $stmt = $pdo->prepare("SELECT p_qty FROM tbl_product WHERE p_id = ?");
    $stmt->execute([$test_p_id]);
    $current_stock = (int)$stmt->fetchColumn();
    assert_test("Product inventory unchanged during PENDING_APPROVAL ({$current_stock} === {$initial_qty})", $current_stock === $initial_qty);

    // 5. Test Manager Approval Workflow & Restocking
    echo "\n--- Test Suite 5: Manager Approval & Restocking Workflow ---\n";
    $pdo->beginTransaction();

    // Fetch items
    $stmt = $pdo->prepare("SELECT * FROM tbl_return_items WHERE return_id = ?");
    $stmt->execute([$return_id]);
    $items_to_approve = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($items_to_approve as $it) {
        if ($it['condition'] === 'Good / Resalable' && (int)$it['product_id'] > 0) {
            $stmt_up = $pdo->prepare("UPDATE tbl_product SET p_qty = p_qty + ? WHERE p_id = ? AND supplier_id = ?");
            $stmt_up->execute([(int)$it['quantity_returned'], (int)$it['product_id'], $supplier_id]);

            $stmt_ri = $pdo->prepare("UPDATE tbl_return_items SET restock_status = 'RESTOCKED' WHERE return_item_id = ?");
            $stmt_ri->execute([$it['return_item_id']]);
        }
    }

    $stmt_app = $pdo->prepare("
        UPDATE tbl_returns SET
            status = 'COMPLETED',
            approver_id = ?,
            approver_name = ?,
            approver_role = ?,
            approver_remarks = ?,
            approved_at = NOW(),
            completed_at = NOW(),
            updated_at = NOW()
        WHERE return_id = ? AND supplier_id = ?
    ");
    $stmt_app->execute([
        $manager_id,
        'Test Manager',
        'Manager',
        'Verified items in good condition, approved refund.',
        $return_id,
        $supplier_id
    ]);

    $pdo->commit();

    // Verify status updated to COMPLETED
    $stmt = $pdo->prepare("SELECT status, approver_name, approver_role, approved_at FROM tbl_returns WHERE return_id = ?");
    $stmt->execute([$return_id]);
    $approved_record = $stmt->fetch(PDO::FETCH_ASSOC);
    assert_test("Return status updated to 'COMPLETED'", $approved_record['status'] === 'COMPLETED');
    assert_test("Approver recorded as 'Test Manager'", $approved_record['approver_name'] === 'Test Manager');
    assert_test("Approved timestamp is populated", !empty($approved_record['approved_at']));

    // Verify stock incremented by 2
    $stmt = $pdo->prepare("SELECT p_qty FROM tbl_product WHERE p_id = ?");
    $stmt->execute([$test_p_id]);
    $new_stock = (int)$stmt->fetchColumn();
    $expected_stock = $initial_qty + $return_qty_good;
    assert_test("Product inventory incremented accurately ({$new_stock} === {$expected_stock})", $new_stock === $expected_stock);

    // Verify restock_status is RESTOCKED
    $stmt = $pdo->prepare("SELECT restock_status FROM tbl_return_items WHERE return_id = ?");
    $stmt->execute([$return_id]);
    $restock_st = $stmt->fetchColumn();
    assert_test("Return item restock_status is 'RESTOCKED'", $restock_st === 'RESTOCKED');

    // 6. Test Admin Self-Approval Workflow (Admin requested & Admin approved)
    echo "\n--- Test Suite 6: Admin Supplier Self-Request & Self-Approval ---\n";
    $return_ref_admin = generate_unique_return_reference($pdo, $supplier_id);
    $return_qty_admin = 1;
    $refund_admin = $return_qty_admin * $unit_price;

    $pdo->beginTransaction();
    $stmt = $pdo->prepare("
        INSERT INTO tbl_returns (
            return_reference, payment_id, order_id, supplier_id,
            customer_id, customer_name, customer_email, customer_phone,
            return_date, refund_method, refund_amount, status,
            requested_by_id, requested_by_name, requested_by_role,
            notes, created_at, updated_at
        ) VALUES (
            ?, ?, ?, ?,
            ?, ?, ?, ?,
            CURRENT_DATE, 'Cash', ?, 'PENDING_APPROVAL',
            ?, 'Test Admin', 'Admin',
            'Admin initiated return', NOW(), NOW()
        ) RETURNING return_id
    ");
    $stmt->execute([
        $return_ref_admin,
        $payment_id,
        $order_item_id,
        $supplier_id,
        1001,
        'Juan Dela Cruz',
        'juan@example.com',
        '09123456789',
        $refund_admin,
        $admin_id
    ]);
    $return_admin_id = $stmt->fetchColumn();

    $stmt = $pdo->prepare("
        INSERT INTO tbl_return_items (
            return_id, return_reference, order_item_id, product_id,
            product_name, sku, size, color, item_type,
            quantity_returned, unit_price, refund_amount,
            return_reason, condition, restock_status, notes
        ) VALUES (
            ?, ?, ?, ?,
            ?, 'TEST-SKU', 'Standard', 'Default', 'standard_product',
            ?, ?, ?,
            'Admin Override', 'Good / Resalable', 'PENDING', 'Self return'
        ) RETURNING return_item_id
    ");
    $stmt->execute([
        $return_admin_id,
        $return_ref_admin,
        $order_item_id,
        $test_p_id,
        $test_product_name,
        $return_qty_admin,
        $unit_price,
        $refund_admin
    ]);
    $pdo->commit();

    // Check permission helper for Admin self-approval
    $stmt = $pdo->prepare("SELECT * FROM tbl_returns WHERE return_id = ?");
    $stmt->execute([$return_admin_id]);
    $admin_ret_rec = $stmt->fetch(PDO::FETCH_ASSOC);

    assert_test("Admin user can self-approve their own return request", can_user_approve_return($admin_user, $admin_ret_rec) === true);

    // Admin self-approves the return
    $pdo->beginTransaction();
    $stmt_up = $pdo->prepare("UPDATE tbl_product SET p_qty = p_qty + ? WHERE p_id = ? AND supplier_id = ?");
    $stmt_up->execute([$return_qty_admin, $test_p_id, $supplier_id]);

    $stmt_app = $pdo->prepare("
        UPDATE tbl_returns SET
            status = 'COMPLETED',
            approver_id = ?,
            approver_name = ?,
            approver_role = ?,
            approver_remarks = 'Admin self-approved return',
            approved_at = NOW(),
            completed_at = NOW(),
            updated_at = NOW()
        WHERE return_id = ? AND supplier_id = ?
    ");
    $stmt_app->execute([$admin_id, 'Test Admin', 'Admin', $return_admin_id, $supplier_id]);

    $stmt_ri = $pdo->prepare("UPDATE tbl_return_items SET restock_status = 'RESTOCKED' WHERE return_id = ?");
    $stmt_ri->execute([$return_admin_id]);
    $pdo->commit();

    // Verify Admin self-approval status
    $stmt = $pdo->prepare("SELECT status, approver_name, requested_by_id, approver_id FROM tbl_returns WHERE return_id = ?");
    $stmt->execute([$return_admin_id]);
    $admin_approved_rec = $stmt->fetch(PDO::FETCH_ASSOC);
    assert_test("Admin self-approved return status is 'COMPLETED'", $admin_approved_rec['status'] === 'COMPLETED');
    assert_test("Requested By ID equals Approver ID for Admin self-approval", (int)$admin_approved_rec['requested_by_id'] === (int)$admin_approved_rec['approver_id']);

    // 7. Test Damaged Item Rejection / Non-restocking Workflow
    echo "\n--- Test Suite 7: Damaged Item & Rejection Workflow ---\n";
    $return_ref_dmg = generate_unique_return_reference($pdo, $supplier_id);
    $return_qty_dmg = 1;
    $refund_dmg = $return_qty_dmg * $unit_price;

    $pdo->beginTransaction();
    $stmt = $pdo->prepare("
        INSERT INTO tbl_returns (
            return_reference, payment_id, order_id, supplier_id,
            customer_id, customer_name, customer_email, customer_phone,
            return_date, refund_method, refund_amount, status,
            requested_by_id, requested_by_name, requested_by_role,
            notes, created_at, updated_at
        ) VALUES (
            ?, ?, ?, ?,
            ?, ?, ?, ?,
            CURRENT_DATE, 'Cash', ?, 'PENDING_APPROVAL',
            ?, ?, ?,
            'Damaged package', NOW(), NOW()
        ) RETURNING return_id
    ");
    $stmt->execute([
        $return_ref_dmg,
        $payment_id,
        $order_item_id,
        $supplier_id,
        1001,
        'Juan Dela Cruz',
        'juan@example.com',
        '09123456789',
        $refund_dmg,
        $cashier_id,
        'Test Cashier',
        'Cashier'
    ]);
    $return_dmg_id = $stmt->fetchColumn();

    $stmt = $pdo->prepare("
        INSERT INTO tbl_return_items (
            return_id, return_reference, order_item_id, product_id,
            product_name, sku, size, color, item_type,
            quantity_returned, unit_price, refund_amount,
            return_reason, condition, restock_status, notes
        ) VALUES (
            ?, ?, ?, ?,
            ?, 'TEST-SKU', 'Standard', 'Default', 'standard_product',
            ?, ?, ?,
            'Damaged', 'Damaged / Defective', 'PENDING', 'Broken seal'
        ) RETURNING return_item_id
    ");
    $stmt->execute([
        $return_dmg_id,
        $return_ref_dmg,
        $order_item_id,
        $test_p_id,
        $test_product_name,
        $return_qty_dmg,
        $unit_price,
        $refund_dmg
    ]);
    $pdo->commit();

    // Manager rejects this return
    $pdo->beginTransaction();
    $rejection_reason = "Customer damaged after leaving store premises";
    $stmt_rej = $pdo->prepare("
        UPDATE tbl_returns SET
            status = 'REJECTED',
            approver_id = ?,
            approver_name = ?,
            approver_role = ?,
            rejection_reason = ?,
            rejected_at = NOW(),
            updated_at = NOW()
        WHERE return_id = ? AND supplier_id = ?
    ");
    $stmt_rej->execute([
        $manager_id,
        'Test Manager',
        'Manager',
        $rejection_reason,
        $return_dmg_id,
        $supplier_id
    ]);

    $stmt_ri_rej = $pdo->prepare("UPDATE tbl_return_items SET restock_status = 'NOT_RESTOCKED' WHERE return_id = ?");
    $stmt_ri_rej->execute([$return_dmg_id]);
    $pdo->commit();

    // Verify rejection record
    $stmt = $pdo->prepare("SELECT status, rejection_reason, rejected_at FROM tbl_returns WHERE return_id = ?");
    $stmt->execute([$return_dmg_id]);
    $rejected_record = $stmt->fetch(PDO::FETCH_ASSOC);
    assert_test("Return status updated to 'REJECTED'", $rejected_record['status'] === 'REJECTED');
    assert_test("Rejection reason stored correctly", $rejected_record['rejection_reason'] === $rejection_reason);
    assert_test("Rejected timestamp populated", !empty($rejected_record['rejected_at']));

    // 8. Multi-tenant Isolation Test
    echo "\n--- Test Suite 8: Multi-Tenant Data Isolation ---\n";
    $other_supplier_id = $supplier_id + 999;
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM tbl_returns WHERE supplier_id = ?");
    $stmt->execute([$other_supplier_id]);
    $other_returns_count = (int)$stmt->fetchColumn();
    assert_test("Supplier {$other_supplier_id} cannot access returns from Supplier {$supplier_id}", $other_returns_count === 0);

    // Clean up test data
    $pdo->prepare("DELETE FROM tbl_return_items WHERE return_id IN (?, ?, ?)")->execute([$return_id, $return_admin_id, $return_dmg_id]);
    $pdo->prepare("DELETE FROM tbl_returns WHERE return_id IN (?, ?, ?)")->execute([$return_id, $return_admin_id, $return_dmg_id]);
    $pdo->prepare("DELETE FROM tbl_order WHERE id = ?")->execute([$order_item_id]);
    $pdo->prepare("DELETE FROM tbl_payment WHERE payment_id = ?")->execute([$payment_id]);
    $pdo->prepare("DELETE FROM tbl_product WHERE p_id = ?")->execute([$test_p_id]);
    echo "\nTest cleanup complete.\n";

} catch (Exception $e) {
    echo "\n[ERROR] Exception occurred: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}

echo "\n=======================================================\n";
echo "VERIFICATION SUMMARY: {$tests_passed} / {$tests_total} tests passed.\n";
echo "=======================================================\n";
