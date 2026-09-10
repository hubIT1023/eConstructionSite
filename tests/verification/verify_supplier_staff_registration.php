<?php
/**
 * Comprehensive Verification Suite for Supplier Staff Registration Form & Backend Logic Update
 */
require_once __DIR__ . '/supplier/inc/config.php';
require_once __DIR__ . '/supplier/inc/functions.php';

$results = [];

function record_test($name, $passed, $details = '') {
    global $results;
    $results[] = [
        'test' => $name,
        'passed' => (bool)$passed,
        'details' => $details
    ];
    $status = $passed ? "[PASS]" : "[FAIL]";
    echo "{$status} - {$name}\n";
    if (!empty($details)) {
        echo "  Details: {$details}\n";
    }
}

echo "=== STARTING SUPPLIER STAFF REGISTRATION VERIFICATION ===\n\n";

try {
    // 1. Schema Check
    ensure_supplier_user_schema($pdo);
    
    // Check columns in tbl_supplier_user
    $stmt = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'tbl_supplier_user'");
    $cols = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $required_cols = ['first_name', 'middle_name', 'last_name', 'phone', 'employee_id', 'date_started', 'pos_access', 'status', 'role'];
    $missing_cols = array_diff($required_cols, $cols);
    record_test("Schema Check: tbl_supplier_user columns", empty($missing_cols), "Columns present: " . implode(', ', $cols));

    // Check tbl_supplier_employee_sequence
    $stmt = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'tbl_supplier_employee_sequence'");
    $seq_cols = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $has_seq_table = in_array('supplier_id', $seq_cols) && in_array('last_seq', $seq_cols);
    record_test("Schema Check: tbl_supplier_employee_sequence table", $has_seq_table, "Columns: " . implode(', ', $seq_cols));

    // 2. Alphabet Rotation Rule Check
    // Sequence 1..10 -> A, 11..20 -> B, 21..30 -> C, ..., 251..260 -> Z, 261..270 -> AA, 271..280 -> AB
    $r1 = get_alphabet_rotation_letter(1);
    $r10 = get_alphabet_rotation_letter(10);
    $r11 = get_alphabet_rotation_letter(11);
    $r20 = get_alphabet_rotation_letter(20);
    $r251 = get_alphabet_rotation_letter(251);
    $r260 = get_alphabet_rotation_letter(260);
    $r261 = get_alphabet_rotation_letter(261);
    $r270 = get_alphabet_rotation_letter(270);
    $r271 = get_alphabet_rotation_letter(271);

    $rotation_ok = ($r1 === 'A' && $r10 === 'A' && $r11 === 'B' && $r20 === 'B' && $r251 === 'Z' && $r260 === 'Z' && $r261 === 'AA' && $r270 === 'AA' && $r271 === 'AB');
    record_test("Alphabet Rotation Logic (1-10:A, 11-20:B, 251-260:Z, 261-270:AA, 271-280:AB)", $rotation_ok, 
        "1: $r1, 10: $r10, 11: $r11, 20: $r20, 251: $r251, 260: $r260, 261: $r261, 270: $r270, 271: $r271");

    // 3. Employee ID Generator Format & Independence
    // Format: SICS-{YYYYMMDD}-{LETTER}
    // Test with a mock tenant ID 99999
    $test_supplier_id = 99999;
    $pdo->prepare("DELETE FROM tbl_supplier_user WHERE supplier_id = ?")->execute([$test_supplier_id]);
    $pdo->prepare("DELETE FROM tbl_supplier_employee_sequence WHERE supplier_id = ?")->execute([$test_supplier_id]);

    $id1 = generate_supplier_employee_id($pdo, $test_supplier_id, '2026-09-07');
    $id2 = generate_supplier_employee_id($pdo, $test_supplier_id, '2026-09-07');
    // Change start date for 3rd employee: sequence should advance to 3 (still A), date should reflect 20261001
    $id3 = generate_supplier_employee_id($pdo, $test_supplier_id, '2026-10-01');

    $id_format_ok = ($id1 === 'SICS-20260907-A' && $id2 === 'SICS-20260907-A' && $id3 === 'SICS-20261001-A');
    record_test("Employee ID Generator Format & Date Independence", $id_format_ok, "id1: $id1, id2: $id2, id3: $id3");

    // Advance sequence to 11 to test transition to B
    $pdo->prepare("UPDATE tbl_supplier_employee_sequence SET last_seq = 10 WHERE supplier_id = ?")->execute([$test_supplier_id]);
    $id11 = generate_supplier_employee_id($pdo, $test_supplier_id, '2026-09-07');
    $rotation_adv_ok = ($id11 === 'SICS-20260907-B');
    record_test("Employee ID Generation Sequence Transition to B on 11th registration", $rotation_adv_ok, "id11: $id11");

    // Clean up mock sequence
    $pdo->prepare("DELETE FROM tbl_supplier_employee_sequence WHERE supplier_id = ?")->execute([$test_supplier_id]);

    // 4. Role Hierarchy Check
    $admin_roles = get_assignable_roles('Admin');
    $mgr_roles = get_assignable_roles('Manager');
    $sup_roles = get_assignable_roles('Supervisor');
    $cashier_roles = get_assignable_roles('Cashier');
    $encoder_roles = get_assignable_roles('Encoder');

    $admin_ok = count($admin_roles) === 6 && in_array('MANAGER', $admin_roles) && in_array('SUPERVISOR', $admin_roles) && in_array('CASHIER', $admin_roles) && in_array('OPERATOR', $admin_roles) && in_array('ORDER_PROCESSING', $admin_roles) && in_array('ENCODER', $admin_roles);
    $mgr_ok = count($mgr_roles) === 5 && !in_array('MANAGER', $mgr_roles) && in_array('SUPERVISOR', $mgr_roles) && in_array('ENCODER', $mgr_roles);
    $sup_ok = count($sup_roles) === 4 && !in_array('MANAGER', $sup_roles) && !in_array('SUPERVISOR', $sup_roles) && in_array('CASHIER', $sup_roles) && in_array('ENCODER', $sup_roles);
    $cashier_blocked = empty($cashier_roles) && !can_register_staff('Cashier');
    $encoder_blocked = empty($encoder_roles) && !can_register_staff('Encoder');

    $hierarchy_ok = $admin_ok && $mgr_ok && $sup_ok && $cashier_blocked && $encoder_blocked;
    record_test("Role Assignment Hierarchy (Admin, Manager, Supervisor, Cashier/Encoder blocked)", $hierarchy_ok, 
        "Admin: " . implode(',', $admin_roles) . " | Manager: " . implode(',', $mgr_roles) . " | Supervisor: " . implode(',', $sup_roles) . " | Cashier: " . (empty($cashier_roles)?'blocked':'allowed'));

    // 5. POS Access Check Function
    $user_with_pos = ['role' => 'Cashier', 'pos_access' => 1];
    $user_without_pos = ['role' => 'Cashier', 'pos_access' => 0];
    $user_admin = ['role' => 'Admin', 'pos_access' => 0]; // Admin has bypass

    $pos_check_ok = (has_pos_access($user_with_pos) === true && has_pos_access($user_without_pos) === false && has_pos_access($user_admin) === true);
    record_test("POS Access Barrier Evaluation (has_pos_access)", $pos_check_ok, 
        "user_with_pos: " . (has_pos_access($user_with_pos)?'true':'false') . ", user_without_pos: " . (has_pos_access($user_without_pos)?'true':'false') . ", admin_override: " . (has_pos_access($user_admin)?'true':'false'));

    // 6. Inspect login.php content for form fields and structure
    $login_content = file_get_contents(__DIR__ . '/supplier/login.php');
    $has_fn = strpos($login_content, 'name="first_name"') !== false;
    $has_mn = strpos($login_content, 'name="middle_name"') !== false;
    $has_ln = strpos($login_content, 'name="last_name"') !== false;
    $has_date = strpos($login_content, 'name="date_started"') !== false;
    $has_pos_cb = strpos($login_content, 'name="pos_access"') !== false;
    $has_readonly_note = strpos($login_content, 'SICS-YYYYMMDD-A') !== false;
    $has_new_supplier_link = strpos($login_content, 'supplier-registration.php') !== false;

    $form_fields_ok = $has_fn && $has_mn && $has_ln && $has_date && $has_pos_cb && $has_readonly_note && $has_new_supplier_link;
    record_test("Staff Registration Form UI & Fields in supplier/login.php", $form_fields_ok, 
        "first_name:$has_fn, middle_name:$has_mn, last_name:$has_ln, date_started:$has_date, pos_access:$has_pos_cb, note:$has_readonly_note, new_supplier_link:$has_new_supplier_link");

    // 7. Inspect users.php content for POS toggle and fields
    $users_content = file_get_contents(__DIR__ . '/supplier/users.php');
    $has_pos_col = strpos($users_content, 'POS Access') !== false;
    $has_pos_toggle = strpos($users_content, 'toggle_pos_access') !== false;
    $has_users_pos_input = strpos($users_content, 'name="pos_access"') !== false;
    $has_users_date_input = strpos($users_content, 'name="date_started"') !== false;

    $users_ui_ok = $has_pos_col && $has_pos_toggle && $has_users_pos_input && $has_users_date_input;
    record_test("Supplier Users Management UI & POS Toggle in supplier/users.php", $users_ui_ok, 
        "pos_col:$has_pos_col, toggle_action:$has_pos_toggle, pos_input:$has_users_pos_input, date_input:$has_users_date_input");

    // 8. Public Supplier Registration File Isolation
    $public_supp_reg = file_get_contents(__DIR__ . '/supplier-registration.php');
    $is_public_supp_distinct = strpos($public_supp_reg, 'form_register_supplier') !== false && strpos($public_supp_reg, 'tbl_supplier') !== false;
    record_test("Public Supplier Registration Independence (supplier-registration.php)", $is_public_supp_distinct, 
        "Distinct tenant registration logic preserved");

} catch (Exception $e) {
    record_test("Execution Exception", false, $e->getMessage());
}

echo "\n=== VERIFICATION SUMMARY ===\n";
$total = count($results);
$passed_cnt = count(array_filter($results, function($r) { return $r['passed']; }));
echo "Total Tests: {$total} | Passed: {$passed_cnt} | Failed: " . ($total - $passed_cnt) . "\n";

if ($passed_cnt === $total) {
    echo "\n>>> ALL VERIFICATION CHECKS PASSED SUCCESSFULLY! <<<\n";
    exit(0);
} else {
    echo "\n>>> SOME VERIFICATION CHECKS FAILED! <<<\n";
    exit(1);
}
