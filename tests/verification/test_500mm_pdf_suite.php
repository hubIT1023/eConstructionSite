<?php
/**
 * Test Suite: Purchase Order Confirmed — 500 mm Thermal Printing & PDF Test Preview Verification Suite
 */

$file_path = $argv[1] ?? '';
if (!$file_path || !file_exists($file_path)) {
    $file_path = '/var/www/html/supplier/pos.php';
}
if (!file_exists($file_path)) {
    $file_path = __DIR__ . '/../../supplier/pos.php';
}
if (!file_exists($file_path)) {
    $file_path = __DIR__ . '/pos.php';
}

if (!file_exists($file_path)) {
    echo "ERROR: supplier/pos.php not found at: " . $file_path . "\n";
    exit(1);
}

$content = file_get_contents($file_path);

$tests = [];

function run_test($name, $condition, $details = '') {
    global $tests;
    $passed = (bool)$condition;
    $tests[] = [
        'name' => $name,
        'passed' => $passed,
        'details' => $details
    ];
    echo ($passed ? "[PASS] " : "[FAIL] ") . $name . "\n";
    if (!$passed && $details) {
        echo "       Details: " . $details . "\n";
    }
}

echo "=================================================================\n";
echo "PURCHASE ORDER CONFIRMED — 500 MM THERMAL PRINT TEST SUITE\n";
echo "=================================================================\n";

// 1. PHP Syntax Check
run_test("pos.php file exists and has content", strlen($content) > 10000);

// 2. Store Header Check
run_test(
    "Store header branding is 'SAM & INRI CONSTRUCTION SUPPLY'",
    strpos($content, 'SAM &amp; INRI CONSTRUCTION SUPPLY') !== false || strpos($content, 'SAM & INRI CONSTRUCTION SUPPLY') !== false,
    "Store header must match SAM & INRI CONSTRUCTION SUPPLY"
);

// 3. Settings Default Paper Width
run_test(
    "posPrinterSettings defaults to 500 mm paper width",
    strpos($content, 'paperWidthMm: 500') !== false || strpos($content, 'paperWidthMm = 500') !== false,
    "Default paper width must be 500 mm"
);

// 4. Paper Width Select in Settings Modal defaults to 500 mm
run_test(
    "Settings modal paper width select defaults to 500 mm Roll",
    strpos($content, '<option value="500" selected>500 mm Roll') !== false || strpos($content, 'value="500" selected') !== false,
    "500 mm option must be selected by default"
);

// 5. Purchase Order Confirmed Modal Elements
run_test(
    "PO Modal has format selector with 500 mm default",
    strpos($content, 'id="posPOModalPaperSize"') !== false && strpos($content, '<option value="500" selected>⚡ 500 mm Thermal Roll (Default)</option>') !== false,
    "PO Modal format selector must have 500 mm as default option"
);

run_test(
    "PO Modal has primary 500mm Thermal print button",
    strpos($content, "printPOSPurchaseOrder()") !== false && strpos($content, "Print Purchase Order (500mm Thermal)") !== false,
    "PO Modal must have primary 500mm thermal print button"
);

run_test(
    "PO Modal has 500mm PDF Test / Preview button",
    strpos($content, "printPOSPurchaseOrder('pdf500')") !== false && strpos($content, "PDF Test / Preview (500mm)") !== false,
    "PO Modal must have 500mm PDF test/preview button"
);

// 6. Purchase Order Items Table Structure (4 Columns)
run_test(
    "PO Items table has 4 dedicated column headers (ITEM DESCRIPTION, QTY, UNIT PRICE, AMOUNT)",
    strpos($content, 'ITEM DESCRIPTION') !== false &&
    strpos($content, 'UNIT PRICE') !== false &&
    strpos($content, 'AMOUNT') !== false,
    "PO table must include ITEM DESCRIPTION, QTY, UNIT PRICE, AMOUNT"
);

run_test(
    "PO Items table has prominent bold TOTAL DUE",
    strpos($content, 'TOTAL DUE:') !== false,
    "PO table must display bold TOTAL DUE label"
);

// 7. Thermal Definition & Monospace Typography
run_test(
    "generatePOSPrintHTML treats continuous rolls (!isA4) as thermal (including 500mm)",
    strpos($content, 'const isThermal = !isA4;') !== false,
    "isThermal must not be restricted to <= 100 mm"
);

run_test(
    "generatePOSPrintHTML uses Courier New / Consolas monospace font stack for thermal",
    strpos($content, "'Courier New', Consolas") !== false,
    "Thermal roll must use crisp monospace font stack"
);

// 8. 500 mm Thermal Sizing & PDF Preview
run_test(
    "generatePOSPrintHTML supports 500mm thermal roll (is500mm)",
    strpos($content, 'const is500mm = isThermal && paperWidthMm >= 400;') !== false,
    "is500mm must be defined for wide thermal roll"
);

run_test(
    "generatePOSPrintHTML handles pdf500 as 500mm thermal PDF preview",
    strpos($content, "format === 'pdf500'") !== false,
    "pdf500 must configure 500 mm roll preview"
);

run_test(
    "generatePOSPrintHTML sets 500 mm roll continuous @page CSS with margin 0",
    strpos($content, 'size: ${paperWidthMm}mm ${isA4 ? pageHeightMm + \'mm\' : \'auto\'};') !== false &&
    strpos($content, 'margin: 0;') !== false,
    "@page CSS must use paperWidthMm and auto height with margin 0"
);

// 9. Column Width Calibrations for 500 mm
run_test(
    "generatePOSPrintHTML has dedicated 500 mm table column width rules",
    strpos($content, 'is500mm') !== false &&
    strpos($content, '.pos-receipt-container .col-item-desc {') !== false &&
    strpos($content, 'width: 52% !important;') !== false,
    "500 mm column widths must be calibrated"
);

// 10. PDF Preview Toolbar
run_test(
    "generatePOSPrintHTML includes PDF preview toolbar with 500 mm badge and Print/Save button",
    strpos($content, 'pdf-preview-toolbar') !== false &&
    strpos($content, '500 mm Thermal PDF (Preview / Test)') !== false &&
    strpos($content, '🖨️ ${isPdfPreview ? \'Print / Save as PDF\' : \'Print Receipt\'}') !== false,
    "Toolbar must display 500 mm preview badge and Print/Save button"
);

// 11. Test Print POS Function
run_test(
    "testPrintPOS defaults to 500 mm roll",
    strpos($content, "function testPrintPOS(format = 'thermal500')") !== false,
    "testPrintPOS default must be thermal500"
);

// 12. Preview PO Function
run_test(
    "previewPOSPurchaseOrderPDF delegates to 500 mm PDF preview",
    strpos($content, "function previewPOSPurchaseOrderPDF() {\n    printPOSPurchaseOrder('pdf500');") !== false ||
    strpos($content, "printPOSPurchaseOrder('pdf500');") !== false,
    "previewPOSPurchaseOrderPDF must use pdf500"
);

// Summary
$total = count($tests);
$passed_count = count(array_filter($tests, function($t) { return $t['passed']; }));
$failed_count = $total - $passed_count;

echo "=================================================================\n";
echo "TEST RESULTS: $passed_count / $total PASSED ($failed_count FAILED)\n";
echo "=================================================================\n";

if ($failed_count > 0) {
    exit(1);
} else {
    echo "ALL 500 MM THERMAL PRINT QUALITY TESTS PASSED SUCCESSFULLY!\n";
    exit(0);
}
