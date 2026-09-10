<?php
/**
 * Automated Verification Test Suite for 250mm Compact PO Thermal Receipt
 */

$pos_file = '/var/www/html/supplier/pos.php';
if (!file_exists($pos_file)) {
    $pos_file = __DIR__ . '/../../supplier/pos.php';
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

echo "=== 250 MM COMPACT PO THERMAL RECEIPT TEST SUITE ===\n\n";

// 1. PO Voucher Container & Class
assert_test("PO Area Container has thermal-receipt-250 class",
    strpos($content, 'class="modal-body thermal-receipt-250" id="posPrintPOArea"') !== false ||
    (strpos($content, 'id="posPrintPOArea"') !== false && strpos($content, 'thermal-receipt-250') !== false),
    "Expected thermal-receipt-250 class on posPrintPOArea"
);

// 2. Store Header & Unpaid Title
assert_test("PO Header contains 'Sam & Inri Construction Supply'",
    strpos($content, 'Sam &amp; Inri Construction Supply') !== false || strpos($content, 'Sam & Inri Construction Supply') !== false,
    "Expected store name in PO header"
);

assert_test("PO Header contains 'PURCHASE ORDER VOUCHER' and '(UNPAID)'",
    strpos($content, 'PURCHASE ORDER VOUCHER') !== false && strpos($content, '(UNPAID)') !== false,
    "Expected PURCHASE ORDER VOUCHER and (UNPAID)"
);

// 3. Compact PO Info Grid (PO NO, CUSTOMER, DATE, STATUS: AWAITING PAYMENT)
assert_test("PO Metadata contains PO NO, CUSTOMER, DATE, and STATUS: AWAITING PAYMENT",
    strpos($content, '<strong>PO NO:</strong>') !== false &&
    strpos($content, '<strong>CUSTOMER:</strong>') !== false &&
    strpos($content, '<strong>DATE:</strong>') !== false &&
    strpos($content, 'AWAITING PAYMENT') !== false,
    "Expected compact metadata grid with PO NO, CUSTOMER, DATE, and AWAITING PAYMENT"
);

// 4. 4-Column Items Table (ITEM | QTY | PRICE | TOTAL)
assert_test("PO Items table has 4 columns: ITEM, QTY, PRICE, TOTAL",
    preg_match('/<th[^>]*>ITEM<\/th>\s*<th[^>]*>QTY<\/th>\s*<th[^>]*>PRICE<\/th>\s*<th[^>]*>TOTAL<\/th>/s', $content) === 1,
    "Expected 4-column compact items header in PO voucher"
);

// 5. Totals: TOTAL DUE is present
assert_test("Totals section has TOTAL DUE:",
    strpos($content, 'TOTAL DUE:') !== false,
    "Expected 'TOTAL DUE:' in totals section"
);

// 6. Cashier Payment Instruction & Thank You
assert_test("Footer contains cashier instruction and thank you message",
    strpos($content, '*** PROCEED TO CASHIER FOR PAYMENT ***') !== false &&
    strpos($content, 'Thank you for your business!') !== false,
    "Expected Cashier instruction and thank you note"
);

// 7. JS generatePOSPrintHTML handles 250mm for docType === 'po'
assert_test("JavaScript generatePOSPrintHTML defaults to 250mm for PO voucher",
    strpos($content, "docType === 'po'") !== false &&
    strpos($content, "widthMm = 250") !== false,
    "Expected generatePOSPrintHTML to set 250mm for PO"
);

// 8. JS Printable container width for 250mm is 240mm
assert_test("JavaScript sets 240mm max width for 250mm roll width",
    strpos($content, "containerMaxMm = (widthMm === 250) ? 240") !== false,
    "Expected containerMaxMm 240mm for 250mm roll"
);

// 9. Paper size selector in PO modal defaults to 250mm
assert_test("PO modal paper selector defaults to 250mm",
    strpos($content, '<option value="250" selected>⚡ 250 mm') !== false,
    "Expected 250mm as default selected option in posPOModalPaperSize"
);

// 10. Thermal printer modal options include 250mm
assert_test("Thermal printer settings modal includes 250mm",
    strpos($content, '<option value="250">250 mm / 25 cm') !== false,
    "Expected 250mm option in posPaperWidth select"
);

echo "\n============================================\n";
echo "TESTS SUMMARY: " . count($tests) . " run, " . (count($tests) - $failures) . " passed, " . $failures . " failed.\n";
if ($failures === 0) {
    echo "ALL 250 MM COMPACT PO RECEIPT TESTS PASSED!\n";
    exit(0);
} else {
    echo "SOME TESTS FAILED!\n";
    exit(1);
}
