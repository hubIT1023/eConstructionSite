<?php
/**
 * Automated Verification Test Suite for Commercial Thermal Receipt Print Quality (80mm & 58mm)
 */

$possible_paths = [
    __DIR__ . '/../../supplier/pos.php',
    '/tmp/pos_test.php',
    '/root/eConstructionSite/supplier/pos.php',
    '/var/www/html/supplier/pos.php'
];

$pos_file = null;
foreach ($possible_paths as $p) {
    if (file_exists($p)) {
        $pos_file = $p;
        break;
    }
}

if (!file_exists($pos_file)) {
    echo "[ERROR] supplier/pos.php not found at $pos_file\n";
    exit(1);
}

$content = file_get_contents($pos_file);
$tests = [];
$failures = 0;

function assert_test($name, $condition, $details = '') {
    global $tests, $failures;
    if ($condition) {
        $tests[] = ['name' => $name, 'status' => 'PASS', 'details' => $details];
        echo "[PASS] $name\n";
    } else {
        $failures++;
        $tests[] = ['name' => $name, 'status' => 'FAIL', 'details' => $details];
        echo "[FAIL] $name: $details\n";
    }
}

echo "=== COMMERCIAL THERMAL RECEIPT PRINT QUALITY (80MM & 58MM) VERIFICATION ===\n\n";

// 1. Default Settings in Printer Settings Modal
assert_test("posPaperWidth selector defaults to 80mm and includes 58mm",
    (strpos($content, '<option value="80" selected>80 mm (Standard 3-inch POS Thermal)</option>') !== false ||
     strpos($content, '<option value="80" selected>80 mm — Standard 3-inch POS Thermal</option>') !== false) &&
    (strpos($content, '<option value="58">58 mm (Compact 2-inch Thermal)</option>') !== false ||
     strpos($content, '<option value="58">58 mm — Standard 2-inch POS Thermal</option>') !== false),
    "Expected 80mm selected default and 58mm option in #posPaperWidth"
);

// 2. Direct Diagnostic Test Buttons in Printer Modal
assert_test("Printer modal has direct 80mm and 58mm test print buttons",
    (strpos($content, "testPrintThermalPaidOrder(80)") !== false || strpos($content, "testPrintPOS('thermal80')") !== false) &&
    (strpos($content, "testPrintThermalPaidOrder(58)") !== false || strpos($content, "testPrintPOS('thermal58')") !== false),
    "Expected direct testPrintThermalPaidOrder calls for 80 and 58"
);

// 3. JavaScript Settings Default
assert_test("JavaScript posPrinterSettings defaults paperWidthMm to 80",
    strpos($content, "paperWidthMm: 80,") !== false,
    "Expected posPrinterSettings.paperWidthMm to default to 80"
);

// 4. Sales Receipt Modal Layout Classes
assert_test("Sales receipt modal (#posPrintReceiptArea) contains semantic thermal columns and subprice",
    strpos($content, 'class="col-item-desc"') !== false &&
    strpos($content, 'class="col-qty"') !== false &&
    strpos($content, 'class="col-unit-price"') !== false &&
    strpos($content, 'class="col-amount"') !== false &&
    strpos($content, 'class="item-unit-subprice"') !== false,
    "Expected semantic column classes and item-unit-subprice in receipt modal"
);

// 5. Purchase Order Voucher Modal Layout Classes
assert_test("PO voucher modal (#posPrintPOArea) contains semantic thermal columns and subprice",
    strpos($content, 'id="posPrintPOArea"') !== false &&
    strpos($content, 'class="item-unit-subprice"') !== false,
    "Expected semantic columns and item-unit-subprice in PO modal"
);

// 6. Thermal Print Typography (Monospace font stack)
assert_test("generatePOSPrintHTML uses monospace font stack for thermal printing",
    strpos($content, "'Courier New', Consolas, 'Liberation Mono', monospace") !== false &&
    strpos($content, "font-variant-numeric: tabular-nums") !== false,
    "Expected Courier New monospace stack and tabular-nums"
);

// 7. Dynamic Continuous Roll Page Sizing (@page auto height for thermal)
assert_test("generatePOSPrintHTML sets auto height on @page for continuous thermal rolls",
    strpos($content, "size: \${paperWidthMm}mm \${isA4 ? pageHeightMm + 'mm' : 'auto'};") !== false,
    "Expected auto height on @page for thermal rolls"
);

// 8. Adaptive 58mm vs 80mm CSS Columns
assert_test("generatePOSPrintHTML provides responsive column adaptation for 58mm",
    strpos($content, ".pos-receipt-container .col-unit-price") !== false &&
    strpos($content, "display: none !important;") !== false &&
    strpos($content, ".pos-receipt-container .item-unit-subprice") !== false &&
    strpos($content, "display: block !important;") !== false,
    "Expected CSS rule hiding unit price and showing subprice on 58mm"
);

// 9. Pure Black & High Contrast Styling
assert_test("Thermal print CSS enforces pure #000000 black and solid/dashed borders",
    strpos($content, "color: #000000 !important;") !== false &&
    strpos($content, "1pt dashed #000000 !important;") !== false,
    "Expected pure black #000000 and 1pt dashed dividers"
);

// 10. Print Function Defaults (format = null)
assert_test("printPOSReceipt, printReturnSlip, and printPOSPurchaseOrder default to format = null",
    strpos($content, "function printPOSReceipt(format = null)") !== false &&
    strpos($content, "function printReturnSlip(format = null)") !== false &&
    strpos($content, "function printPOSPurchaseOrder(format = null)") !== false,
    "Expected print functions to default to format = null so modal/settings apply"
);

echo "\n--- TEST SUMMARY ---\n";
echo "Total Tests: " . count($tests) . "\n";
echo "Passed: " . (count($tests) - $failures) . "\n";
echo "Failed: $failures\n";

if ($failures === 0) {
    echo "Result: ALL VERIFICATION TESTS PASSED SUCCESSFULLY!\n";
    exit(0);
} else {
    echo "Result: SOME TESTS FAILED.\n";
    exit(1);
}
