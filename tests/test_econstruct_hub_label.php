<?php
/**
 * Test label replacement: "WebPOS" replaced with "eConstruct Hub"
 * for Supplier / Tenant Admin only, while preserving "WebPOS" for Cashier / POS users.
 */

echo "========================================================\n";
echo "TEST: eConstruct Hub Label Verification (localhost)\n";
echo "========================================================\n\n";

function render_supplier_header($user_data, $script_name) {
    $script = "
        chdir('/var/www/html/supplier');
        session_start();
        \$_SESSION['supplier_user'] = " . var_export($user_data, true) . ";
        \$_SERVER['SCRIPT_NAME'] = " . var_export($script_name, true) . ";
        ob_start();
        include '/var/www/html/supplier/header.php';
        echo ob_get_clean();
    ";
    $cmd = "php -r " . escapeshellarg($script) . " 2>&1";
    return shell_exec($cmd);
}

// 1. Test as Supplier / Tenant Admin
echo "--- 1. Testing as Supplier / Tenant Admin (Role: Admin) ---\n";
$admin_user = [
    'id' => 2,
    'supplier_id' => 2,
    'full_name' => 'Sam & Inri Construction Supply Manager',
    'email' => 'ariel_bonilla33@yahoo.com',
    'role' => 'Admin',
    'status' => 'Active',
    'pos_access' => 1
];
$admin_html = render_supplier_header($admin_user, '/supplier/index.php');

$has_econstruct_hub = (strpos($admin_html, '<span class="logo-lg">eConstruct Hub</span>') !== false);
$has_ech_mini = (strpos($admin_html, '<span class="logo-mini"><b>eC</b>H</span>') !== false);
$has_admin_title = (strpos($admin_html, 'eConstruct Hub - Supplier Admin') !== false);
$has_admin_navbar = (strpos($admin_html, 'eConstruct Hub Admin') !== false);
$no_webpos_in_logo = (strpos($admin_html, '<span class="logo-lg">WebPOS</span>') === false);

echo " - Logo contains 'eConstruct Hub': " . ($has_econstruct_hub ? "PASS [YES]" : "FAIL [NO]") . "\n";
echo " - Mini logo contains '<b>eC</b>H': " . ($has_ech_mini ? "PASS [YES]" : "FAIL [NO]") . "\n";
echo " - Title contains 'eConstruct Hub - Supplier Admin': " . ($has_admin_title ? "PASS [YES]" : "FAIL [NO]") . "\n";
echo " - Navbar contains 'eConstruct Hub Admin': " . ($has_admin_navbar ? "PASS [YES]" : "FAIL [NO]") . "\n";
echo " - Logo does NOT contain 'WebPOS': " . ($no_webpos_in_logo ? "PASS [CONFIRMED]" : "FAIL [FOUND]") . "\n";

assert($has_econstruct_hub, "Admin logo must be eConstruct Hub");
assert($no_webpos_in_logo, "Admin logo must not be WebPOS");
echo ">>> ADMIN ROLE TEST PASSED!\n\n";

// 2. Test as POS Cashier / Staff
echo "--- 2. Testing as POS Cashier / Staff (Role: CASHIER) ---\n";
$cashier_user = [
    'id' => 25,
    'supplier_id' => 5,
    'full_name' => 'Cashier Alpha',
    'email' => 'cashier@alpha.local',
    'role' => 'CASHIER',
    'status' => 'Active',
    'pos_access' => 1
];
$cashier_html = render_supplier_header($cashier_user, '/supplier/pos.php');

$cashier_has_webpos = (strpos($cashier_html, '<span class="logo-lg">WebPOS</span>') !== false);
$cashier_has_pos_mini = (strpos($cashier_html, '<span class="logo-mini"><b>POS</b></span>') !== false);
$cashier_no_ech_logo = (strpos($cashier_html, '<span class="logo-lg">eConstruct Hub</span>') === false);

echo " - Cashier logo contains 'WebPOS': " . ($cashier_has_webpos ? "PASS [YES]" : "FAIL [NO]") . "\n";
echo " - Cashier mini logo contains '<b>POS</b>': " . ($cashier_has_pos_mini ? "PASS [YES]" : "FAIL [NO]") . "\n";
echo " - Cashier logo does NOT contain 'eConstruct Hub': " . ($cashier_no_ech_logo ? "PASS [CONFIRMED]" : "FAIL [FOUND]") . "\n";

assert($cashier_has_webpos, "Cashier logo must remain WebPOS");
assert($cashier_no_ech_logo, "Cashier logo must not be eConstruct Hub");
echo ">>> CASHIER ROLE TEST PASSED!\n\n";

echo "========================================================\n";
echo "ALL TESTS PASSED! 'eConstruct Hub' is active for Supplier Admin only.\n";
echo "========================================================\n";
