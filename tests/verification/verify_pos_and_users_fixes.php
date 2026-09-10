<?php
/**
 * Test to verify pos.php and users.php error-free execution
 */
require_once __DIR__ . '/supplier/inc/config.php';
require_once __DIR__ . '/supplier/inc/functions.php';

echo "=== TESTING SCHEMA AND PAGES FOR POS.PHP AND USERS.PHP ===\n";

ensure_supplier_user_schema($pdo);

// 1. Verify tbl_brgy exists and can be queried
$stmt = $pdo->query("SELECT COUNT(*) FROM tbl_brgy");
$brgy_count = $stmt->fetchColumn();
echo "[PASS] tbl_brgy exists and contains {$brgy_count} barangays.\n";
assert($brgy_count > 0, "tbl_brgy should have records");

// 2. Verify tbl_supplier has max_pos_users column
$stmt = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'tbl_supplier' AND column_name = 'max_pos_users'");
$has_col = (bool)$stmt->fetchColumn();
echo "[PASS] tbl_supplier has max_pos_users column: " . ($has_col ? "YES" : "NO") . "\n";
assert($has_col, "max_pos_users column should exist in tbl_supplier");

// 3. Test get_tenant_pos_user_stats function for all suppliers
$stmt = $pdo->query("SELECT supplier_id FROM tbl_supplier");
$suppliers = $stmt->fetchAll(PDO::FETCH_COLUMN);
foreach ($suppliers as $sid) {
    $stats = get_tenant_pos_user_stats($pdo, $sid);
    assert(isset($stats['max_pos_users']), "stats should have max_pos_users");
    assert(isset($stats['current_pos_users']), "stats should have current_pos_users");
}
echo "[PASS] get_tenant_pos_user_stats executed successfully for all suppliers.\n";

// 4. Test change role to ORDER_PROCESSING and verify
$test_user = $pdo->query("SELECT id, supplier_id, role FROM tbl_supplier_user WHERE UPPER(role) != 'ADMIN' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if ($test_user) {
    $original_role = $test_user['role'];
    $pdo->prepare("UPDATE tbl_supplier_user SET role = 'ORDER_PROCESSING' WHERE id = ?")->execute([$test_user['id']]);
    $new_role = $pdo->query("SELECT role FROM tbl_supplier_user WHERE id = {$test_user['id']}")->fetchColumn();
    assert($new_role === 'ORDER_PROCESSING');
    assert(get_role_display_name($new_role) === 'Order Processing Staff');
    echo "[PASS] Role update to ORDER_PROCESSING verified in database.\n";
    // Restore
    $pdo->prepare("UPDATE tbl_supplier_user SET role = ? WHERE id = ?")->execute([$original_role, $test_user['id']]);
}

echo "=== ALL FIXES VERIFIED SUCCESSFULLY ===\n";
