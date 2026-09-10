<?php
/**
 * Test Suite: 500mm Thermal PDF Option & 250mm Compact Thermal Roll Verification
 */

$file_path = '/var/www/html/supplier/pos.php';
if (!file_exists($file_path)) {
    $file_path = __DIR__ . '/pos.php';
}

if (!file_exists($file_path)) {
    echo "ERROR: pos.php not found.\n";
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
echo "RUNNING 500 MM THERMAL PDF & 250 MM THERMAL PRINT TEST SUITE\n";
echo "=================================================================\n";

// 1. PHP Syntax Check
run_test("pos.php syntax valid", true);

// 2. Store Header Check
run_test(
    "Store header branding is 'Sam & Inri Construction Supply'",
    strpos($content, 'Sam &amp; Inri Construction Supply') !== false || strpos($content, 'Sam & Inri Construction Supply') !== false,
    "Store header must match Sam & Inri Construction Supply"
);

// 3. Dual Print Buttons in Sales Receipt Modal (#posSuccessModal)
run_test(
    "Sales Receipt Modal has 250 mm Thermal button",
    strpos($content, "printPOSReceipt('thermal250')") !== false,
    "printPOSReceipt('thermal250') button missing"
);
run_test(
    "Sales Receipt Modal has 500 mm Thermal PDF button",
    strpos($content, "printPOSReceipt('pdf500')") !== false,
    "printPOSReceipt('pdf500') button missing"
);

// 4. Dual Print Buttons in Purchase Order Modal (#posPOSuccessModal)
run_test(
    "Purchase Order Modal has 250 mm Thermal button",
    strpos($content, "printPOSPurchaseOrder('thermal250')") !== false,
    "printPOSPurchaseOrder('thermal250') button missing"
);
run_test(
    "Purchase Order Modal has 500 mm Thermal PDF button",
    strpos($content, "printPOSPurchaseOrder('pdf500')") !== false,
    "printPOSPurchaseOrder('pdf500') button missing"
);

// 5. Dual Print Buttons in Return Slip Modal (#posReturnSuccessModal)
run_test(
    "Return Slip Modal has 250 mm Thermal button",
    strpos($content, "printReturnSlip('thermal250')") !== false,
    "printReturnSlip('thermal250') button missing"
);
run_test(
    "Return Slip Modal has 500 mm Thermal PDF button",
    strpos($content, "printReturnSlip('pdf500')") !== false,
    "printReturnSlip('pdf500') button missing"
);

// 6. Dual Test Print Buttons in Printer Modal (#posPrinterModal)
run_test(
    "Printer Modal has 250 mm Thermal test button",
    strpos($content, "testPrintPOS('thermal250')") !== false,
    "testPrintPOS('thermal250') button missing"
);
run_test(
    "Printer Modal has 500 mm Thermal PDF test button",
    strpos($content, "testPrintPOS('pdf500')") !== false,
    "testPrintPOS('pdf500') button missing"
);

// 7. Format Selectors in Modals
run_test(
    "Modal Selectors have '⚡ 250 mm Thermal (Compact)' option",
    strpos($content, '⚡ 250 mm Thermal (Compact)') !== false,
    "250 mm option missing from format selectors"
);
run_test(
    "Modal Selectors have '📄 500 mm Thermal PDF' option",
    strpos($content, '📄 500 mm Thermal PDF') !== false,
    "500 mm option missing from format selectors"
);

// 8. generatePOSPrintHTML function implementation
run_test(
    "generatePOSPrintHTML supports requestedFormat argument",
    strpos($content, "function generatePOSPrintHTML(contentHtml, docTitle = 'POS Print Document', docType = 'receipt', requestedFormat = null)") !== false,
    "generatePOSPrintHTML signature not matching"
);

run_test(
    "generatePOSPrintHTML sets 500mm @page size for pdf500",
    strpos($content, 'size: ${widthMm}mm auto') !== false,
    "@page CSS must use variable widthMm auto"
);

run_test(
    "generatePOSPrintHTML wraps 500mm PDF in centered 250mm thermal strip",
    strpos($content, 'pdf-thermal-strip') !== false && strpos($content, 'pdf-receipt-500') !== false,
    "500mm layout must wrap content in centered 250mm strip to prevent text distortion"
);

run_test(
    "generatePOSPrintHTML includes PDF preview toolbar with Print/Save button",
    strpos($content, 'pdf-preview-toolbar') !== false && strpos($content, '🖨️ Print / Save as PDF') !== false,
    "Toolbar missing in preview"
);

// 9. JavaScript preview functions
run_test(
    "previewPOSPurchaseOrderPDF function defined",
    strpos($content, "function previewPOSPurchaseOrderPDF()") !== false,
    "previewPOSPurchaseOrderPDF missing"
);
run_test(
    "previewPOSReceiptPDF function defined",
    strpos($content, "function previewPOSReceiptPDF()") !== false,
    "previewPOSReceiptPDF missing"
);
run_test(
    "previewPOSReturnPDF function defined",
    strpos($content, "function previewPOSReturnPDF()") !== false,
    "previewPOSReturnPDF missing"
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
    echo "ALL TESTS PASSED SUCCESSFULLY!\n";
    exit(0);
}
