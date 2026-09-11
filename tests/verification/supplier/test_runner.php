<?php
chdir('/var/www/html/supplier');

// Script to test a specific scenario in a clean process
$testType = $argv[1] ?? 'po_success';

session_start();
$_SESSION['supplier_user'] = [
    'id' => 1,
    'supplier_id' => 2,
    'full_name' => 'Admin User',
    'role' => 'ADMIN'
];

require_once('/var/www/html/admin/inc/config.php');

if ($testType === 'po_success') {
    $stmt = $pdo->prepare("SELECT payment_id FROM tbl_payment WHERE supplier_id = 2 ORDER BY id DESC LIMIT 1");
    $stmt->execute();
    $targetPoId = $stmt->fetchColumn() ?: 'PO-20260908-AB57C';

    $_GET = [
        'po_success' => '1',
        'po_id' => $targetPoId
    ];
    // Put stale items in session to verify they get cleaned
    $_SESSION['pos_cart'] = [
        ['product_id' => 10, 'qty' => 1, 'price' => 50]
    ];
} elseif ($testType === 'po_payment') {
    $stmt = $pdo->prepare("SELECT payment_id FROM tbl_payment WHERE supplier_id = 2 ORDER BY id DESC LIMIT 1");
    $stmt->execute();
    $targetPoId = $stmt->fetchColumn() ?: 'PO-20260908-AB57C';

    $_GET = [
        'po_id' => $targetPoId
    ];
    unset($_SESSION['pos_cart']);
} elseif ($testType === 'clear_cart') {
    $_GET = [
        'clear_cart' => '1'
    ];
    $_SESSION['pos_cart'] = [
        ['product_id' => 99, 'qty' => 2, 'price' => 100]
    ];
}

ob_start();
include '/var/www/html/supplier/pos.php';
$html = ob_get_clean();

if ($testType === 'po_success') {
    echo "=== TEST PO SUCCESS MODE ===\n";
    $modalOk = strpos($html, 'id="posPOSuccessModal"') !== false && strpos($html, $targetPoId) !== false;
    echo "1. PO Modal Rendered: " . ($modalOk ? "[PASS]" : "[FAIL]") . "\n";

    $continueBtnOk = strpos($html, 'closePOSPurchaseOrderModal()') !== false && (strpos($html, 'Continue / New') !== false);
    echo "2. Continue / New Transaction Button: " . ($continueBtnOk ? "[PASS]" : "[FAIL]") . "\n";

    $printBtnOk = strpos($html, 'printPOSPurchaseOrder()') !== false && (strpos($html, 'Print Purchase Order') !== false || strpos($html, 'Print PO') !== false);
    echo "3. Print PO Button: " . ($printBtnOk ? "[PASS]" : "[FAIL]") . "\n";

    $emptyCartTableOk = strpos($html, 'Cart is empty. Click products or "+ Special Order" to add.') !== false;
    echo "4. Register Cart Table Empty Placeholder: " . ($emptyCartTableOk ? "[PASS]" : "[FAIL]") . "\n";

    preg_match('/let cart = (.*?);/', $html, $m_cart);
    $js_cart = trim($m_cart[1] ?? '');
    echo "5. JS Cart is Empty Array: " . ($js_cart === '[]' ? "[PASS]" : "[FAIL] ($js_cart)") . "\n";

    $subtotalOk = strpos($html, 'id="posSubtotal">0.00</span>') !== false || strpos($html, 'id="posSubtotal">&#8369;0.00</span>') !== false;
    echo "6. Register Subtotal 0.00: " . ($subtotalOk ? "[PASS]" : "[FAIL]") . "\n";
} elseif ($testType === 'po_payment') {
    echo "=== TEST PO PAYMENT MODE (Cashier) ===\n";
    $badgeOk = strpos($html, 'PO: PO-20260908-AB57C') !== false;
    echo "1. PO Badge in Current Sale: " . ($badgeOk ? "[PASS]" : "[FAIL]") . "\n";

    $itemOk = strpos($html, 'Common Nail') !== false;
    echo "2. PO Items Loaded in Cart: " . ($itemOk ? "[PASS]" : "[FAIL]") . "\n";
} elseif ($testType === 'clear_cart') {
    echo "=== TEST CLEAR CART ROUTE ===\n";
    $sessionEmpty = empty($_SESSION['pos_cart']);
    echo "1. Session Cart Unset: " . ($sessionEmpty ? "[PASS]" : "[FAIL]") . "\n";

    preg_match('/let cart = (.*?);/', $html, $m_cart);
    $js_cart = trim($m_cart[1] ?? '');
    echo "2. JS Cart Empty Array: " . ($js_cart === '[]' ? "[PASS]" : "[FAIL] ($js_cart)") . "\n";
}
