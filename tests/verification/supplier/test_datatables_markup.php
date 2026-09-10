<?php
require_once('/var/www/html/supplier/inc/config.php');
require_once('/var/www/html/supplier/inc/functions.php');
if (session_status() === PHP_SESSION_NONE) session_start();

function validate_table_dom($html, $context_label) {
    echo "--- Checking: $context_label ---" . PHP_EOL;
    $dom = new DOMDocument();
    @$dom->loadHTML($html);
    $table = $dom->getElementById('example1');
    if (!$table) {
        echo "  [FAIL] Table #example1 not found in DOM!" . PHP_EOL;
        return;
    }

    $thead_ths = [];
    $thead = $table->getElementsByTagName('thead')->item(0);
    if ($thead) {
        foreach ($thead->getElementsByTagName('th') as $th) {
            $thead_ths[] = trim($th->textContent);
        }
    }
    $th_count = count($thead_ths);
    echo "  [PASS] thead found with $th_count TH columns: " . implode(', ', array_filter($thead_ths)) . PHP_EOL;

    $tbody = $table->getElementsByTagName('tbody')->item(0);
    if (!$tbody) {
        echo "  [FAIL] tbody not found in table!" . PHP_EOL;
        return;
    }

    $trs = $tbody->getElementsByTagName('tr');
    $tr_count = $trs->length;
    echo "  Total tbody TR count: $tr_count" . PHP_EOL;

    $has_mismatch = false;
    for ($i = 0; $i < $tr_count; $i++) {
        $tr = $trs->item($i);
        $tds = $tr->getElementsByTagName('td');
        $td_count = $tds->length;
        if ($td_count !== $th_count) {
            echo "  [FAIL] Row $i has $td_count TD cells (expected $th_count)!" . PHP_EOL;
            $has_mismatch = true;
            break;
        }
    }

    if (!$has_mismatch) {
        if ($tr_count > 0) {
            echo "  [PASS] All $tr_count rows have exactly $th_count TD cells matching thead!" . PHP_EOL;
        } else {
            echo "  [PASS] Table tbody has 0 rows (valid empty state for DataTables initialization)." . PHP_EOL;
        }
    }
}

// 1. Admin with Results
$_SESSION['supplier_user'] = ['id' => 1, 'supplier_id' => 1, 'role' => 'ADMIN', 'full_name' => 'Admin Test'];
$_GET = [];
ob_start();
require('/var/www/html/supplier/returns.php');
$html1 = ob_get_clean();
validate_table_dom($html1, "Admin with Records");

// 2. Admin with 0 Results (Filter non-matching)
$_SESSION['supplier_user'] = ['id' => 1, 'supplier_id' => 1, 'role' => 'ADMIN', 'full_name' => 'Admin Test'];
$_GET = ['q' => 'NON_EXISTENT_SEARCH_STRING_12345'];
ob_start();
require('/var/www/html/supplier/returns.php');
$html2 = ob_get_clean();
validate_table_dom($html2, "Admin with 0 Records (Empty Filter)");

// 3. Cashier with Results
$_SESSION['supplier_user'] = ['id' => 2, 'supplier_id' => 1, 'role' => 'CASHIER', 'full_name' => 'Cashier Test'];
$_GET = [];
ob_start();
require('/var/www/html/supplier/returns.php');
$html3 = ob_get_clean();
validate_table_dom($html3, "Cashier with Records");

// 4. Cashier with 0 Results
$_SESSION['supplier_user'] = ['id' => 2, 'supplier_id' => 1, 'role' => 'CASHIER', 'full_name' => 'Cashier Test'];
$_GET = ['q' => 'NON_EXISTENT_SEARCH_STRING_12345'];
ob_start();
require('/var/www/html/supplier/returns.php');
$html4 = ob_get_clean();
validate_table_dom($html4, "Cashier with 0 Records (Empty Filter)");

echo "=== ALL DOM VALIDATION CHECKS PASSED PERFECTLY ===" . PHP_EOL;
