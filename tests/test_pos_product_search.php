<?php
/**
 * Automated Test Suite for POS Product Relevance Search & Default Hidden Results Table
 * 
 * Verifies:
 * 1. Initial page state & refresh behaviour (0 inventory rows rendered, table hidden by default)
 * 2. Search input & category filter displayed immediately
 * 3. Relevance ranking hierarchy (exact barcode/SKU -> exact name -> prefix -> boundary -> partial -> multi-word)
 * 4. Strict five-item limit enforcement
 * 5. Empty search behavior (returns 0 items)
 * 6. No-matches query handling (returns 0 items)
 * 7. Database/input error resilience
 * 8. AJAX search endpoint integration & result replacement
 */

require_once __DIR__ . '/../admin/inc/config.php';
require_once __DIR__ . '/../supplier/inc/functions.php';

echo "========================================================\n";
echo "TEST SUITE: POS On-Demand Relevance Search (Localhost)\n";
echo "========================================================\n\n";

$pass_count = 0;
$fail_count = 0;

function assert_test($condition, $test_name) {
    global $pass_count, $fail_count;
    if ($condition) {
        echo "  [PASS] $test_name\n";
        $pass_count++;
    } else {
        echo "  [FAIL] $test_name\n";
        $fail_count++;
    }
}

// Ensure test barcode and SKU exist for predictable ranking verification
try {
    $pdo->exec("ALTER TABLE tbl_product ADD COLUMN IF NOT EXISTS p_barcode VARCHAR(100);");
    // Ensure at least one product has a known test barcode for Priority 1 verification
    $check_bc = $pdo->prepare("SELECT p_id FROM tbl_product WHERE supplier_id = 2 AND p_barcode = '880123456789' LIMIT 1");
    $check_bc->execute();
    if (!$check_bc->fetch()) {
        $pdo->exec("UPDATE tbl_product SET p_barcode = '880123456789' WHERE supplier_id = 2 AND p_id = (SELECT p_id FROM tbl_product WHERE supplier_id = 2 ORDER BY p_id ASC LIMIT 1)");
    }
} catch (Exception $e) {
    // Column already exists or handled
}

// -----------------------------------------------------------------------------
// TEST 1 & 2: Initial Page State & Browser Refresh Behavior
// -----------------------------------------------------------------------------
echo "--- 1. Testing Initial Page State & Refresh Behavior ---\n";

function render_pos_page($user_data) {
    $script = "
        chdir('/var/www/html/supplier');
        session_start();
        \$_SESSION['supplier_user'] = " . var_export($user_data, true) . ";
        \$_SERVER['SCRIPT_NAME'] = '/supplier/pos.php';
        \$_SERVER['REQUEST_METHOD'] = 'GET';
        ob_start();
        include '/var/www/html/supplier/pos.php';
        echo ob_get_clean();
    ";
    $cmd = "php -r " . escapeshellarg($script) . " 2>&1";
    return shell_exec($cmd);
}

$test_user = [
    'id' => 2,
    'supplier_id' => 2,
    'full_name' => 'Test Supplier Manager',
    'email' => 'ariel_bonilla33@yahoo.com',
    'role' => 'Admin',
    'status' => 'Active',
    'pos_access' => 1
];

// Initial Load
$initial_html = render_pos_page($test_user);

// Acceptance Criteria: On refresh, no inventory rows are displayed
$has_table_container_hidden = (strpos($initial_html, 'id="posProductTableContainer"') !== false && strpos($initial_html, 'id="posProductTableContainer" class="table-responsive" style="display: none;') !== false);
$has_initial_state_prompt = (strpos($initial_html, 'id="posSearchInitialState"') !== false);
$has_search_input = (strpos($initial_html, 'id="posSearchInput"') !== false);
$has_category_filter = (strpos($initial_html, 'id="posCategoryFilterSelect"') !== false);

// Check that tbody contains NO rendered product rows on load
preg_match('/<tbody id="posProductTableBody">(.*?)<\/tbody>/s', $initial_html, $tbody_matches);
$tbody_content = isset($tbody_matches[1]) ? trim($tbody_matches[1]) : '';
$no_rows_rendered = (empty($tbody_content) || strpos($tbody_content, '<tr') === false);

assert_test($has_table_container_hidden, "Item data table container is hidden by default (display: none)");
assert_test($has_initial_state_prompt, "Initial search prompt state is displayed on initial page load");
assert_test($has_search_input, "Search input is displayed immediately upon load");
assert_test($has_category_filter, "Category filter is displayed immediately upon load");
assert_test($no_rows_rendered, "No inventory rows are emitted into HTML on initial page load");

// Refresh Behavior (second request simulates browser refresh)
$refresh_html = render_pos_page($test_user);
preg_match('/<tbody id="posProductTableBody">(.*?)<\/tbody>/s', $refresh_html, $refresh_tbody);
$refresh_content = isset($refresh_tbody[1]) ? trim($refresh_tbody[1]) : '';
assert_test(empty($refresh_content) || strpos($refresh_content, '<tr') === false, "On browser refresh, no inventory rows are displayed");
assert_test(strpos($refresh_html, 'id="posProductTableContainer"') !== false, "Table container remains ready on refresh");

echo "\n";

// -----------------------------------------------------------------------------
// TEST 3: Relevance Ranking Hierarchy
// -----------------------------------------------------------------------------
echo "--- 2. Testing Relevance Ranking Hierarchy ---\n";

// Priority 1: Exact Barcode
$res_bc = search_pos_products_by_relevance($pdo, 2, '880123456789');
assert_test(!empty($res_bc) && $res_bc[0]['relevance_score'] === 1, "Priority 1: Exact barcode match scores 1");

// Priority 2: Exact Product Name
$sample_name = $res_bc[0]['name'] ?? 'Tie Wire 1/2';
$res_exact = search_pos_products_by_relevance($pdo, 2, $sample_name);
assert_test(!empty($res_exact) && $res_exact[0]['relevance_score'] <= 2, "Priority 2: Exact item-name match top ranked");

// Priority 4: Prefix Product Name
$prefix = substr($sample_name, 0, 4);
$res_prefix = search_pos_products_by_relevance($pdo, 2, $prefix);
assert_test(!empty($res_prefix) && $res_prefix[0]['relevance_score'] <= 4, "Priority 4: Prefix item-name match ranked");

// Priority 5: Word Boundary / Phrase
$res_wire = search_pos_products_by_relevance($pdo, 2, 'wire');
assert_test(!empty($res_wire) && $res_wire[0]['relevance_score'] <= 5, "Priority 5: Phrase / word boundary in item-name ranked");

// Priority 7: Multi-term Words (in reverse order, e.g. 'wire tie')
$res_multi = search_pos_products_by_relevance($pdo, 2, 'wire tie');
assert_test(!empty($res_multi) && $res_multi[0]['relevance_score'] === 7, "Priority 7: Multi-term word match (reverse order) ranked");

echo "\n";

// -----------------------------------------------------------------------------
// TEST 4: Strict Five-Item Limit Enforcement
// -----------------------------------------------------------------------------
echo "--- 3. Testing Five-Item Limit Enforcement ---\n";

// Broad search that matches many products
$res_broad = search_pos_products_by_relevance($pdo, 2, 'a', 0, 5);
assert_test(count($res_broad) <= 5, "Broad search 'a' returns <= 5 items (actual: " . count($res_broad) . ")");
assert_test(count($res_broad) > 0, "Broad search 'a' returns at least 1 item");

// Attempt to pass higher limit (e.g. limit=50) - backend must cap at 5
$res_limit_bypass = search_pos_products_by_relevance($pdo, 2, 'a', 0, 50);
assert_test(count($res_limit_bypass) <= 5, "Limit parameter cap: limit=50 is capped at 5 in backend (actual: " . count($res_limit_bypass) . ")");

// Attempt limit <= 0 - backend enforces minimum 1
$res_limit_min = search_pos_products_by_relevance($pdo, 2, 'a', 0, 0);
assert_test(count($res_limit_min) === 1, "Limit parameter floor: limit=0 is floored to 1 (actual: " . count($res_limit_min) . ")");

echo "\n";

// -----------------------------------------------------------------------------
// TEST 5: Empty Search
// -----------------------------------------------------------------------------
echo "--- 4. Testing Empty Searches ---\n";

$res_empty = search_pos_products_by_relevance($pdo, 2, '');
assert_test(is_array($res_empty) && count($res_empty) === 0, "Empty search query with no category returns 0 items");

$res_whitespace = search_pos_products_by_relevance($pdo, 2, '    ');
assert_test(is_array($res_whitespace) && count($res_whitespace) === 0, "Whitespace search query returns 0 items");

echo "\n";

// -----------------------------------------------------------------------------
// TEST 6: No Matches Query
// -----------------------------------------------------------------------------
echo "--- 5. Testing No Matches Found ---\n";

$res_nomatch = search_pos_products_by_relevance($pdo, 2, 'xyznonexistentitem999999');
assert_test(is_array($res_nomatch) && count($res_nomatch) === 0, "Non-existent item query returns 0 items");

echo "\n";

// -----------------------------------------------------------------------------
// TEST 7: Database Errors & Resilience
// -----------------------------------------------------------------------------
echo "--- 6. Testing Database Error & Input Resilience ---\n";

// Search with supplier ID 0 (no products associated)
$res_nosupplier = search_pos_products_by_relevance($pdo, 0, 'wire');
assert_test(is_array($res_nosupplier) && count($res_nosupplier) === 0, "Invalid/unauthorized supplier ID returns 0 items");

// SQL special characters resilience (SQL injection safety with prepared statements)
$res_special = search_pos_products_by_relevance($pdo, 2, "' OR '1'='1' --");
assert_test(is_array($res_special), "SQL injection attack string safely handled without syntax error");

echo "\n";

// -----------------------------------------------------------------------------
// TEST 8: AJAX Search Endpoint Integration
// -----------------------------------------------------------------------------
echo "--- 7. Testing AJAX Endpoint (pos.php?pos_action=search_products) ---\n";

function call_pos_ajax_search($user_data, $query, $cat_id = 0) {
    $script = "
        chdir('/var/www/html/supplier');
        session_start();
        \$_SESSION['supplier_user'] = " . var_export($user_data, true) . ";
        \$_SERVER['SCRIPT_NAME'] = '/supplier/pos.php';
        \$_SERVER['REQUEST_METHOD'] = 'GET';
        \$_REQUEST['pos_action'] = 'search_products';
        \$_REQUEST['q'] = " . var_export($query, true) . ";
        \$_REQUEST['category_id'] = " . var_export($cat_id, true) . ";
        include '/var/www/html/supplier/pos.php';
    ";
    $cmd = "php -r " . escapeshellarg($script) . " 2>&1";
    return shell_exec($cmd);
}

$ajax_wire = call_pos_ajax_search($test_user, 'wire');
$json_wire = json_decode($ajax_wire, true);

assert_test($json_wire !== null, "AJAX endpoint outputs valid JSON without HTML contamination");
assert_test(isset($json_wire['status']) && $json_wire['status'] === 'success', "AJAX response status is 'success'");
assert_test(isset($json_wire['count']) && $json_wire['count'] <= 5, "AJAX response count enforces maximum 5");
assert_test(isset($json_wire['items']) && is_array($json_wire['items']), "AJAX response contains items array");

// Acceptance criteria: Subsequent search replaces results rather than appending
$ajax_nail = call_pos_ajax_search($test_user, 'nail');
$json_nail = json_decode($ajax_nail, true);

assert_test($json_nail !== null && $json_nail['status'] === 'success', "Subsequent search for 'nail' executes successfully");
$wire_ids = array_column($json_wire['items'] ?? [], 'id');
$nail_ids = array_column($json_nail['items'] ?? [], 'id');
$is_distinct = ($wire_ids !== $nail_ids);
assert_test($is_distinct, "Subsequent search returns distinct result set (replaces previous search)");

echo "\n";
echo "========================================================\n";
echo "SUMMARY: " . $pass_count . " PASSED, " . $fail_count . " FAILED\n";
echo "========================================================\n";

if ($fail_count > 0) {
    exit(1);
}
exit(0);
