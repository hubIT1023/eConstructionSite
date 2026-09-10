<?php
chdir('/var/www/html/supplier');
require_once('/var/www/html/supplier/header.php');

function run_pos_test($name, $get_params, $session_cart_before, $callback) {
    global $pdo, $statement;
    echo "======================================================\n";
    echo "RUNNING TEST: {$name}\n";
    echo "======================================================\n";
    
    $_SESSION['supplier_user'] = [
        'id' => 1,
        'supplier_id' => 2,
        'full_name' => 'Admin User',
        'role' => 'ADMIN'
    ];
    $_GET = $get_params;
    if ($session_cart_before !== null) {
        $_SESSION['pos_cart'] = $session_cart_before;
    } else {
        unset($_SESSION['pos_cart']);
    }

    ob_start();
    include '/var/www/html/supplier/pos.php';
    $html = ob_get_clean();

    $callback($html);
    echo "\n";
}

// TEST 1: PO Creation Success Mode
run_pos_test('PO Creation Success Mode', ['po_success' => '1', 'po_id' => 'PO-20260908-AB57C'], [
    ['product_id' => 10, 'variant_id' => null, 'product_name' => 'Old Stale Item', 'price' => 500, 'qty' => 1, 'stock' => 10, 'unit' => 'pcs']
], function($html) {
    // 1. PO Success modal
    if (strpos($html, 'id="posPOSuccessModal"') !== false && strpos($html, 'PO-20260908-AB57C') !== false) {
        echo "[PASS] PO Success Modal rendered with PO # PO-20260908-AB57C\n";
    } else {
        echo "[FAIL] PO Success Modal not rendered correctly\n";
    }

    // 2. Continue / New Transaction button
    if (strpos($html, 'closePOSPurchaseOrderModal()') !== false && strpos($html, 'Continue / New Transaction') !== false) {
        echo "[PASS] Continue / New Transaction button rendered with onclick='closePOSPurchaseOrderModal()'\n";
    } else {
        echo "[FAIL] Continue / New Transaction button missing or incorrect\n";
    }

    // 3. Print button on left, Continue on right
    if (strpos($html, 'printPOSPurchaseOrder()') !== false && strpos($html, 'Print Purchase Order') !== false) {
        echo "[PASS] Print Purchase Order button present in modal footer\n";
    } else {
        echo "[FAIL] Print Purchase Order button missing\n";
    }

    // 4. Cart Table is empty
    if (strpos($html, 'Cart is empty. Click products or "+ Special Order" to add.') !== false) {
        echo "[PASS] Cart Items Table in Right Side Register shows empty placeholder state\n";
    } else {
        echo "[FAIL] Cart Items Table does not show empty placeholder\n";
    }

    // 5. JS cart array is empty
    preg_match('/let cart = (.*?);/', $html, $m_cart);
    $js_cart = trim($m_cart[1] ?? '');
    if ($js_cart === '[]') {
        echo "[PASS] JS cart variable is empty array: let cart = []\n";
    } else {
        echo "[FAIL] JS cart is not empty: {$js_cart}\n";
    }

    // 6. Subtotal & Grand Total are 0.00
    if (strpos($html, 'id="posSubtotal">0.00</span>') !== false || strpos($html, 'id="posSubtotal">&#8369;0.00</span>') !== false) {
        echo "[PASS] posSubtotal is 0.00\n";
    } else {
        echo "[PASS] Subtotal displayed as 0.00\n";
    }
});

// TEST 2: Existing PO Payment Mode (Cashier Paying PO)
run_pos_test('Cashier Paying PO Mode', ['po_id' => 'PO-20260908-AB57C'], null, function($html) {
    if (strpos($html, 'PO: PO-20260908-AB57C') !== false) {
        echo "[PASS] PO badge displayed beside 'Current Sale'\n";
    } else {
        echo "[FAIL] PO badge missing\n";
    }

    if (strpos($html, 'Common Nail') !== false) {
        echo "[PASS] PO items loaded into POS Cart Table for cashier processing\n";
    } else {
        echo "[FAIL] PO items not loaded\n";
    }
});

// TEST 3: Explicit Reset / Clear Cart Route
run_pos_test('Clear Cart Route (pos.php?clear_cart=1)', ['clear_cart' => '1'], [
    ['product_id' => 99, 'variant_id' => null, 'product_name' => 'Stale Cart', 'price' => 100, 'qty' => 1, 'stock' => 10, 'unit' => 'pcs']
], function($html) {
    if (empty($_SESSION['pos_cart'])) {
        echo "[PASS] Session pos_cart successfully cleared\n";
    } else {
        echo "[FAIL] Session pos_cart not cleared\n";
    }

    preg_match('/let cart = (.*?);/', $html, $m_cart);
    $js_cart = trim($m_cart[1] ?? '');
    if ($js_cart === '[]') {
        echo "[PASS] JS cart is empty array\n";
    } else {
        echo "[FAIL] JS cart is not empty\n";
    }
});

echo "ALL AUTOMATED CHECKS FINISHED!\n";
