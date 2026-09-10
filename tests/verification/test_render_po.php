<?php
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = '127.0.0.1';
$_SERVER['REQUEST_URI'] = '/supplier/pos.php?po_success=1&po_id=PO-20260910-B7777';
$_SERVER['SCRIPT_NAME'] = '/supplier/pos.php';
$_GET['po_success'] = '1';
$_GET['po_id'] = 'PO-20260910-B7777';

chdir('/var/www/html/supplier');
@session_start();
$_SESSION['supplier_user'] = ['supplier_id' => 2, 'email' => 'supplier2@example.com', 'role' => 'supplier'];

ob_start();
include '/var/www/html/supplier/pos.php';
$html = ob_get_clean();

echo "=== PURCHASE ORDER CONFIRMED 500MM THERMAL RENDER TEST ===\n";
echo "1. Modal posPOSuccessModal rendered: " . (strpos($html, 'posPOSuccessModal') !== false ? "PASS" : "FAIL") . "\n";
echo "2. PO Number PO-20260910-B7777 present: " . (strpos($html, 'PO-20260910-B7777') !== false ? "PASS" : "FAIL") . "\n";
echo "3. Item 'GI Pipe' rendered: " . (strpos($html, 'GI Pipe') !== false ? "PASS" : "FAIL") . "\n";
echo "4. Format select has 500 mm Default: " . (strpos($html, '500 mm Thermal Roll (Default)') !== false ? "PASS" : "FAIL") . "\n";
echo "5. Print PO 500mm Thermal button present: " . (strpos($html, 'Print Purchase Order (500mm Thermal)') !== false ? "PASS" : "FAIL") . "\n";
echo "6. PDF Test / Preview 500mm button present: " . (strpos($html, 'PDF Test / Preview (500mm)') !== false ? "PASS" : "FAIL") . "\n";
echo "7. Bold 'TOTAL DUE:' rendered: " . (strpos($html, 'TOTAL DUE:') !== false ? "PASS" : "FAIL") . "\n";
echo "8. 4-column headers (ITEM DESCRIPTION, QTY, UNIT PRICE, AMOUNT): " . ((strpos($html, 'ITEM DESCRIPTION') !== false && strpos($html, 'UNIT PRICE') !== false) ? "PASS" : "FAIL") . "\n\n";

if (preg_match('/<div class="modal-body pos-receipt-400" id="posPrintPOArea"[\s\S]*?<\/div>\s*<div class="modal-footer"/i', $html, $m)) {
    echo "--- RENDERED #posPrintPOArea SNIPPET ---\n";
    echo substr($m[0], 0, 1500) . "\n...\n";
}
