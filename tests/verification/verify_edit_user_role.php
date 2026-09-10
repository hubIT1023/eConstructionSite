<?php
/**
 * Test Edit User Role functionality for Order Processing Staff and Encoder
 */
require_once __DIR__ . '/supplier/inc/config.php';
require_once __DIR__ . '/supplier/inc/functions.php';

echo "=== VERIFYING EDIT USER ROLE MODAL & ACTIONS ===\n";

$stmt = $pdo->query("SELECT supplier_id FROM tbl_supplier LIMIT 1");
$test_supplier_id = (int)$stmt->fetchColumn();
if (!$test_supplier_id) {
    die("No supplier found in tbl_supplier\n");
}

// Insert test cashier user
$pwd = hash_supplier_password('Secret123!');
$stmt = $pdo->prepare("INSERT INTO tbl_supplier_user (supplier_id, first_name, last_name, full_name, email, password, role, status, employee_id, date_started, pos_access) VALUES (?, 'Test', 'Staff', 'Test Staff', 'teststaff_edit_role@test.com', ?, 'CASHIER', 'Active', 'SICS-20260907-Z99', '2026-09-07', 1) RETURNING id");
$stmt->execute([$test_supplier_id, $pwd]);
$uid = $stmt->fetchColumn();

// 1. Test Admin changing role to ORDER_PROCESSING
assert(can_assign_role('ADMIN', 'ORDER_PROCESSING') === true, "Admin should be able to assign ORDER_PROCESSING");
$pdo->prepare("UPDATE tbl_supplier_user SET role = ? WHERE id = ? AND supplier_id = ?")->execute(['ORDER_PROCESSING', $uid, $test_supplier_id]);
$stmt = $pdo->prepare("SELECT role FROM tbl_supplier_user WHERE id = ?");
$stmt->execute([$uid]);
$cur_role = $stmt->fetchColumn();
echo "[PASS] Admin changed role to ORDER_PROCESSING: stored as $cur_role, display as " . get_role_display_name($cur_role) . "\n";
assert($cur_role === 'ORDER_PROCESSING');
assert(get_role_display_name($cur_role) === 'Order Processing Staff');

// 2. Test Admin changing role to ENCODER
assert(can_assign_role('ADMIN', 'ENCODER') === true, "Admin should be able to assign ENCODER");
$pdo->prepare("UPDATE tbl_supplier_user SET role = ? WHERE id = ? AND supplier_id = ?")->execute(['ENCODER', $uid, $test_supplier_id]);
$stmt = $pdo->prepare("SELECT role FROM tbl_supplier_user WHERE id = ?");
$stmt->execute([$uid]);
$cur_role = $stmt->fetchColumn();
echo "[PASS] Admin changed role to ENCODER: stored as $cur_role, display as " . get_role_display_name($cur_role) . "\n";
assert($cur_role === 'ENCODER');
assert(get_role_display_name($cur_role) === 'Encoder');

// 3. Test Manager and Supervisor permission checks
assert(can_assign_role('MANAGER', 'ORDER_PROCESSING') === true);
assert(can_assign_role('MANAGER', 'ENCODER') === true);
assert(can_assign_role('SUPERVISOR', 'ORDER_PROCESSING') === true);
assert(can_assign_role('SUPERVISOR', 'ENCODER') === true);
echo "[PASS] Manager & Supervisor have permissions to assign Order Processing Staff and Encoder\n";

// 4. Verify users.php dropdown options contain both roles
$users_content = file_get_contents(__DIR__ . '/supplier/users.php');
$has_edit_modal = strpos($users_content, 'id="editPosUserRoleModal"') !== false;
$has_edit_role_select = strpos($users_content, 'name="edit_role" id="editRoleSelect"') !== false;
$has_assignable_roles_loop = strpos($users_content, 'foreach ($assignable_roles as $r_opt)') !== false;
assert($has_edit_modal && $has_edit_role_select && $has_assignable_roles_loop);
echo "[PASS] supplier/users.php has edit role modal with dynamic assignable roles dropdown\n";

// Clean up
$pdo->prepare("DELETE FROM tbl_supplier_user WHERE id = ?")->execute([$uid]);

echo "=== ALL EDIT USER ROLE TESTS PASSED ===\n";
