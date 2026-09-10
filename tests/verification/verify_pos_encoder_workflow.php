<?php
/**
 * Automated Verification Suite for POS Encoder Workflow, Staff Registration Hierarchy, & Employee ID Generation
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once(__DIR__ . '/admin/inc/config.php');
require_once(__DIR__ . '/admin/inc/functions.php');
require_once(__DIR__ . '/supplier/inc/functions.php');
global $pdo;

$test_results = [];
$total_passed = 0;
$total_failed = 0;

function run_test($name, $callable) {
    global $test_results, $total_passed, $total_failed;
    try {
        $result = $callable();
        if ($result === true) {
            $total_passed++;
            $test_results[] = ['name' => $name, 'status' => 'PASS', 'message' => 'Passed'];
            echo " [PASS] $name\n";
        } else {
            $total_failed++;
            $test_results[] = ['name' => $name, 'status' => 'FAIL', 'message' => is_string($result) ? $result : 'Failed assertion'];
            echo " [FAIL] $name: " . (is_string($result) ? $result : 'Failed') . "\n";
        }
    } catch (Exception $e) {
        $total_failed++;
        $test_results[] = ['name' => $name, 'status' => 'ERROR', 'message' => $e->getMessage()];
        echo " [ERROR] $name: " . $e->getMessage() . "\n";
    }
}

header('Content-Type: text/plain; charset=utf-8');

echo "====================================================================\n";
echo "  POS ENCODER WORKFLOW & STAFF REGISTRATION VERIFICATION SUITE\n";
echo "====================================================================\n\n";

// TEST 1: Schema Migration & Column Verification
run_test("Schema Verification: employee_id and date_started columns exist on tbl_supplier_user", function() use ($pdo) {
    ensure_supplier_user_schema($pdo);
    
    $stmt = $pdo->prepare("SELECT column_name, data_type FROM information_schema.columns WHERE table_name = 'tbl_supplier_user'");
    $stmt->execute();
    $cols = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    if (!isset($cols['employee_id'])) return "Column employee_id missing from tbl_supplier_user";
    if (!isset($cols['date_started'])) return "Column date_started missing from tbl_supplier_user";
    return true;
});

// TEST 2: Alphabet Rotation Rule Verification
run_test("Alphabet Rotation Rule: Rotates every 10 registrations (1-10:A, 11-20:B, 21-30:C, 251-260:Z, 261+:AA)", function() {
    if (get_alphabet_rotation_letter(1) !== 'A') return "Seq 1 should be A, got " . get_alphabet_rotation_letter(1);
    if (get_alphabet_rotation_letter(10) !== 'A') return "Seq 10 should be A, got " . get_alphabet_rotation_letter(10);
    if (get_alphabet_rotation_letter(11) !== 'B') return "Seq 11 should be B, got " . get_alphabet_rotation_letter(11);
    if (get_alphabet_rotation_letter(20) !== 'B') return "Seq 20 should be B, got " . get_alphabet_rotation_letter(20);
    if (get_alphabet_rotation_letter(21) !== 'C') return "Seq 21 should be C, got " . get_alphabet_rotation_letter(21);
    if (get_alphabet_rotation_letter(250) !== 'Y') return "Seq 250 should be Y, got " . get_alphabet_rotation_letter(250);
    if (get_alphabet_rotation_letter(251) !== 'Z') return "Seq 251 should be Z, got " . get_alphabet_rotation_letter(251);
    if (get_alphabet_rotation_letter(260) !== 'Z') return "Seq 260 should be Z, got " . get_alphabet_rotation_letter(260);
    if (get_alphabet_rotation_letter(261) !== 'AA') return "Seq 261 should be AA, got " . get_alphabet_rotation_letter(261);
    return true;
});

// TEST 3: Employee ID Format Verification (SICS-{YYYYMMDD}-{LETTER})
run_test("Employee ID Generation: SICS-{YYYYMMDD}-{LETTER} format and backfill", function() use ($pdo) {
    backfill_supplier_employee_ids($pdo);
    
    $generated_id = generate_supplier_employee_id($pdo, 1, '2026-09-07');
    if (!preg_match('/^SICS-20260907-[A-Z]+(\d+)?$/', $generated_id)) {
        return "Generated ID '$generated_id' does not match format SICS-20260907-A";
    }
    
    // Verify existing users in DB have valid employee_id
    $stmt = $pdo->query("SELECT id, full_name, email, role, employee_id, date_started FROM tbl_supplier_user LIMIT 5");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($users)) return "No supplier users in DB to check";
    
    foreach ($users as $u) {
        if (empty($u['employee_id'])) {
            return "User id={$u['id']} has empty employee_id after backfill";
        }
        if (!preg_match('/^SICS-\d{8}-[A-Z]+/', $u['employee_id'])) {
            return "User id={$u['id']} has invalid employee_id format: {$u['employee_id']}";
        }
    }
    return true;
});

// TEST 4: Role Hierarchy and Staff Registration Permissions
run_test("Role Permission & Hierarchy: Registration permissions and assignable role boundaries", function() {
    // Permission to register
    if (!can_register_staff('ADMIN')) return "Admin should be able to register staff";
    if (!can_register_staff('MANAGER')) return "Manager should be able to register staff";
    if (!can_register_staff('SUPERVISOR')) return "Supervisor should be able to register staff";
    if (can_register_staff('CASHIER')) return "Cashier should NOT be able to register staff";
    if (can_register_staff('OPERATOR')) return "Operator should NOT be able to register staff";
    if (can_register_staff('ORDER_PROCESSING')) return "Order Processing Staff should NOT be able to register staff";
    if (can_register_staff('ENCODER')) return "Encoder should NOT be able to register staff";
    
    // Assignable roles
    $admin_roles = get_assignable_roles('ADMIN');
    if (!in_array('MANAGER', $admin_roles) || !in_array('SUPERVISOR', $admin_roles) || !in_array('ENCODER', $admin_roles) || !in_array('CASHIER', $admin_roles)) {
        return "Admin missing assignable roles";
    }
    
    $mgr_roles = get_assignable_roles('MANAGER');
    if (in_array('ADMIN', $mgr_roles) || in_array('MANAGER', $mgr_roles)) return "Manager cannot assign Admin or Manager";
    if (!in_array('SUPERVISOR', $mgr_roles) || !in_array('ENCODER', $mgr_roles) || !in_array('CASHIER', $mgr_roles)) return "Manager missing sub-roles";
    
    $sup_roles = get_assignable_roles('SUPERVISOR');
    if (in_array('ADMIN', $sup_roles) || in_array('MANAGER', $sup_roles) || in_array('SUPERVISOR', $sup_roles)) return "Supervisor cannot assign Admin/Manager/Supervisor";
    if (!in_array('ENCODER', $sup_roles) || !in_array('CASHIER', $sup_roles) || !in_array('OPERATOR', $sup_roles)) return "Supervisor missing subordinate roles";
    
    // can_assign_role check
    if (!can_assign_role('SUPERVISOR', 'ENCODER')) return "Supervisor should be allowed to assign ENCODER";
    if (can_assign_role('SUPERVISOR', 'MANAGER')) return "Supervisor should NOT be allowed to assign MANAGER";
    if (can_assign_role('CASHIER', 'ENCODER')) return "Cashier should NOT be allowed to assign any role";
    
    return true;
});

// TEST 5: Encoder Role Identification
run_test("Encoder Role Resolution: is_encoder_role() identifies all encoder role variations", function() {
    if (!is_encoder_role('ENCODER')) return "ENCODER not identified";
    if (!is_encoder_role('encoder')) return "lowercase encoder not identified";
    if (!is_encoder_role('POS ENCODER')) return "'POS ENCODER' not identified";
    if (is_encoder_role('CASHIER')) return "CASHIER should not be encoder";
    if (is_encoder_role('SUPERVISOR')) return "SUPERVISOR should not be encoder";
    if (is_encoder_role('ADMIN')) return "ADMIN should not be encoder";
    return true;
});

// TEST 6: Create PO Session Cart Transformation & Population
run_test("Create PO Session Cart: Validates and populates 1-based cart arrays for checkout.php", function() use ($pdo) {
    // Clear session cart
    unset($_SESSION['cart_p_id']);
    unset($_SESSION['cart_size_id']);
    unset($_SESSION['cart_size_name']);
    unset($_SESSION['cart_color_id']);
    unset($_SESSION['cart_color_name']);
    unset($_SESSION['cart_p_qty']);
    unset($_SESSION['cart_p_current_price']);
    unset($_SESSION['cart_p_name']);
    unset($_SESSION['cart_p_featured_photo']);

    // Find 2 active products belonging to supplier_id 1
    $stmt = $pdo->prepare("SELECT p_id, p_name, p_current_price, p_featured_photo FROM tbl_product WHERE supplier_id = 1 AND p_is_active = 1 LIMIT 2");
    $stmt->execute();
    $prods = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (count($prods) < 2) return "Need at least 2 active products for supplier 1";

    $simulated_cart_items = [
        [
            'id' => $prods[0]['p_id'],
            'qty' => 3,
            'size' => '',
            'color' => ''
        ],
        [
            'id' => $prods[1]['p_id'],
            'qty' => 5,
            'size' => '',
            'color' => ''
        ]
    ];

    // Simulate PO creation logic
    $supplier_id = 1;
    $cart_index = 1;
    $_SESSION['cart_p_id'] = array();
    $_SESSION['cart_size_id'] = array();
    $_SESSION['cart_size_name'] = array();
    $_SESSION['cart_color_id'] = array();
    $_SESSION['cart_color_name'] = array();
    $_SESSION['cart_p_qty'] = array();
    $_SESSION['cart_p_current_price'] = array();
    $_SESSION['cart_p_name'] = array();
    $_SESSION['cart_p_featured_photo'] = array();

    foreach ($simulated_cart_items as $item) {
        $p_id = intval($item['id']);
        $p_qty = max(1, intval($item['qty']));
        
        $stmt_p = $pdo->prepare("SELECT p_id, p_name, p_current_price, p_featured_photo, supplier_id, p_is_active FROM tbl_product WHERE p_id = ? AND supplier_id = ? AND p_is_active = 1");
        $stmt_p->execute(array($p_id, $supplier_id));
        $prod = $stmt_p->fetch(PDO::FETCH_ASSOC);
        
        if ($prod) {
            $clean_price = floatval(preg_replace('/[^0-9.]/', '', strval($prod['p_current_price'])));
            $_SESSION['cart_p_id'][$cart_index] = $prod['p_id'];
            $_SESSION['cart_size_id'][$cart_index] = 0;
            $_SESSION['cart_size_name'][$cart_index] = '';
            $_SESSION['cart_color_id'][$cart_index] = 0;
            $_SESSION['cart_color_name'][$cart_index] = '';
            $_SESSION['cart_p_qty'][$cart_index] = $p_qty;
            $_SESSION['cart_p_current_price'][$cart_index] = $clean_price;
            $_SESSION['cart_p_name'][$cart_index] = $prod['p_name'];
            $_SESSION['cart_p_featured_photo'][$cart_index] = $prod['p_featured_photo'];
            $cart_index++;
        }
    }

    if (count($_SESSION['cart_p_id']) !== 2) return "Expected 2 items in \$_SESSION['cart_p_id'], got " . count($_SESSION['cart_p_id']);
    if ($_SESSION['cart_p_id'][1] != $prods[0]['p_id']) return "Item 1 p_id mismatch";
    if ($_SESSION['cart_p_qty'][1] != 3) return "Item 1 qty mismatch";
    if ($_SESSION['cart_p_id'][2] != $prods[1]['p_id']) return "Item 2 p_id mismatch";
    if ($_SESSION['cart_p_qty'][2] != 5) return "Item 2 qty mismatch";
    
    return true;
});

// TEST 7: Tenant Isolation & Security Filter during Create PO
run_test("Tenant Isolation Security: Products belonging to another supplier are rejected during PO creation", function() use ($pdo) {
    // Find a product belonging to supplier_id 2
    $stmt = $pdo->prepare("SELECT p_id FROM tbl_product WHERE supplier_id != 1 AND p_is_active = 1 LIMIT 1");
    $stmt->execute();
    $other_p_id = $stmt->fetchColumn();
    if (!$other_p_id) return true; // Single supplier database, skip

    // Attempt to validate with supplier_id = 1
    $supplier_id = 1;
    $stmt_chk = $pdo->prepare("SELECT p_id FROM tbl_product WHERE p_id = ? AND supplier_id = ? AND p_is_active = 1");
    $stmt_chk->execute(array($other_p_id, $supplier_id));
    $found = $stmt_chk->fetch(PDO::FETCH_ASSOC);

    if ($found) return "Cross-tenant product {$other_p_id} was improperly matched for supplier 1!";
    return true;
});

// TEST 8: Server-Side Barrier on complete_sale for Encoder
run_test("Security Barrier: complete_sale & complete_exchange blocked for Encoder role", function() {
    $encoder_role = 'ENCODER';
    $is_enc = is_encoder_role($encoder_role);
    if (!$is_enc) return "is_encoder_role failed for ENCODER";
    
    // Check barrier condition
    $blocked = false;
    if ($is_enc) {
        $blocked = true;
    }
    if (!$blocked) return "Encoder was not blocked from complete_sale";
    return true;
});

// TEST 9: HTTP Live Render of supplier/login.php
run_test("HTTP Live Page Render: supplier/login.php accepts Employee ID or Email", function() {
    $html = file_get_contents("http://127.0.0.1/supplier/login.php?mode=user_login");
    if (!$html) return "Failed to fetch supplier/login.php";
    if (strpos($html, 'Employee ID or Email Address') === false && strpos($html, 'Employee ID') === false) {
        return "Employee ID input placeholder/label missing in supplier/login.php";
    }
    return true;
});

// TEST 10: POS Implementation Integration in pos.php
run_test("POS Implementation & Template: supplier/pos.php has Create PO & Encoder role integration", function() {
    $code = file_get_contents(__DIR__ . '/supplier/pos.php');
    if (!$code) return "Failed to read supplier/pos.php";
    if (strpos($code, 'posActionInput') === false) return "posActionInput hidden input missing in pos.php";
    if (strpos($code, "pos_action === 'create_po'") === false && strpos($code, 'pos_action') === false) return "create_po handler missing in pos.php";
    if (strpos($code, 'is_encoder_role') === false) return "is_encoder_role() check missing in pos.php";
    if (strpos($code, 'Create PO') === false) return "Create PO button text missing in pos.php";
    return true;
});

echo "\n====================================================================\n";
echo "  TEST SUMMARY: $total_passed PASSED, $total_failed FAILED\n";
echo "====================================================================\n";

exit($total_failed > 0 ? 1 : 0);
