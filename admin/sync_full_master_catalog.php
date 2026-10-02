<?php
/**
 * Master Catalog Importer & Synchronizer
 *
 * Synchronizes all 807 products from eConstructionSite_Products_List.csv
 * into PostgreSQL tbl_product and ensures full 3-tier category hierarchy resolution.
 *
 * 100% Idempotent, safe, and updates tbl_product_p_id_seq upon completion.
 */

require_once(__DIR__ . '/inc/config.php');

$csvPath = dirname(__DIR__) . '/eConstructionSite_Products_List.csv';
if (!file_exists($csvPath)) {
    die("Error: CSV file not found at: {$csvPath}\n");
}

echo "=== STARTING MASTER CATALOG SYNCHRONIZATION (807 PRODUCTS) ===\n";
echo "Loading CSV: {$csvPath}\n";

$file = fopen($csvPath, 'r');
if (!$file) {
    die("Error opening CSV file.\n");
}

function cleanUtf8($str) {
    if ($str === null || $str === '') {
        return '';
    }
    // Strip UTF-8 BOM and invisible zero-width spaces
    $str = preg_replace('/^\xEF\xBB\xBF/', '', $str);
    $str = preg_replace('/^\x{FEFF}/u', '', $str);
    if (!mb_check_encoding($str, 'UTF-8')) {
        $str = mb_convert_encoding($str, 'UTF-8', 'Windows-1252, ISO-8859-1, UTF-8');
    }
    return trim($str);
}

// Auto-detect delimiter
$firstLine = fgets($file);
$delimiter = (strpos($firstLine, "\t") !== false) ? "\t" : ",";
rewind($file);

// Read Header
$rawHeader = fgetcsv($file, 0, $delimiter);
$header = array_map(function($h) {
    $h = cleanUtf8($h);
    // Remove any remaining non-printable characters from column names
    $h = preg_replace('/[\x00-\x1F\x7F\xEF\xBB\xBF]/', '', $h);
    return trim($h);
}, $rawHeader);
$headerMap = array_flip($header);

$pdo->beginTransaction();

$inserted = 0;
$updated = 0;
$categoryCache = []; // Cache "top|mid|end" -> ecat_id

// Pre-load supplier map
$supplierStmt = $pdo->query("SELECT supplier_id, supplier_name FROM tbl_supplier");
$suppliers = $supplierStmt->fetchAll(PDO::FETCH_ASSOC);

function resolveSupplierId($name, $suppliers) {
    $n = strtolower(trim($name));
    if (strpos($n, 'sam') !== false || strpos($n, 'inri') !== false) {
        return 2;
    }
    if (strpos($n, 'platform') !== false) {
        return 1;
    }
    foreach ($suppliers as $s) {
        if (stripos($s['supplier_name'], $name) !== false) {
            return (int)$s['supplier_id'];
        }
    }
    return 2; // Default to main supplier
}

function resolveEndCategoryId($pdo, $topName, $midName, $endName, &$cache) {
    $topName = cleanUtf8($topName);
    $midName = cleanUtf8($midName);
    $endName = cleanUtf8($endName);

    if (empty($topName)) $topName = 'General Building Materials';
    if (empty($midName)) $midName = 'General';
    if (empty($endName)) $endName = 'Unclassified';

    $key = strtolower("{$topName}|{$midName}|{$endName}");
    if (isset($cache[$key])) {
        return $cache[$key];
    }

    // 1. Resolve / Create Top Category
    $stT = $pdo->prepare("SELECT tcat_id FROM tbl_top_category WHERE LOWER(TRIM(tcat_name)) = LOWER(?)");
    $stT->execute([$topName]);
    $tcat_id = $stT->fetchColumn();
    if (!$tcat_id) {
        $insT = $pdo->prepare("INSERT INTO tbl_top_category (tcat_name, show_on_menu) VALUES (?, 1) RETURNING tcat_id");
        $insT->execute([$topName]);
        $tcat_id = $insT->fetchColumn();
    }

    // 2. Resolve / Create Mid Category
    $stM = $pdo->prepare("SELECT mcat_id FROM tbl_mid_category WHERE LOWER(TRIM(mcat_name)) = LOWER(?) AND tcat_id = ?");
    $stM->execute([$midName, $tcat_id]);
    $mcat_id = $stM->fetchColumn();
    if (!$mcat_id) {
        $insM = $pdo->prepare("INSERT INTO tbl_mid_category (mcat_name, tcat_id) VALUES (?, ?) RETURNING mcat_id");
        $insM->execute([$midName, $tcat_id]);
        $mcat_id = $insM->fetchColumn();
    }

    // 3. Resolve / Create End Category
    $stE = $pdo->prepare("SELECT ecat_id FROM tbl_end_category WHERE LOWER(TRIM(ecat_name)) = LOWER(?) AND mcat_id = ?");
    $stE->execute([$endName, $mcat_id]);
    $ecat_id = $stE->fetchColumn();
    if (!$ecat_id) {
        $insE = $pdo->prepare("INSERT INTO tbl_end_category (ecat_name, mcat_id) VALUES (?, ?) RETURNING ecat_id");
        $insE->execute([$endName, $mcat_id]);
        $ecat_id = $insE->fetchColumn();
    }

    $cache[$key] = (int)$ecat_id;
    return (int)$ecat_id;
}

try {
    $checkStmt = $pdo->prepare("SELECT p_id FROM tbl_product WHERE p_id = ?");

    $updateStmt = $pdo->prepare("
        UPDATE tbl_product 
        SET p_name = ?, 
            p_old_price = ?, 
            p_current_price = ?, 
            p_capital_price = ?, 
            p_markup = ?, 
            p_new_price = ?, 
            p_qty = ?, 
            p_s_level = ?, 
            p_moq = ?, 
            p_is_active = ?, 
            p_is_featured = ?, 
            p_delivery_estimate = ?, 
            p_featured_photo = ?, 
            p_brand = ?, 
            p_sku = ?, 
            p_short_description = ?, 
            p_description = ?, 
            p_specs = ?, 
            supplier_id = ?, 
            ecat_id = ?
        WHERE p_id = ?
    ");

    $insertStmt = $pdo->prepare("
        INSERT INTO tbl_product (
            p_id, p_name, p_old_price, p_current_price, p_qty, p_featured_photo,
            p_description, p_short_description, p_feature, p_condition, p_return_policy,
            p_total_view, p_is_featured, p_is_active, ecat_id, supplier_id,
            p_moq, p_brand, p_specs, p_delivery_estimate, p_pdf, p_sku,
            p_new_price, p_new_qty, p_s_level, p_capital_price, p_markup
        ) VALUES (
            ?, ?, ?, ?, ?, ?,
            ?, ?, '', '', '',
            ?, ?, ?, ?, ?,
            ?, ?, ?, ?, '', ?,
            ?, 0, ?, ?, ?
        )
    ");

    $rowCount = 0;
    while (($rawRow = fgetcsv($file, 0, $delimiter)) !== false) {
        if (empty($rawRow) || count($rawRow) < 5) continue;
        $row = array_map('cleanUtf8', $rawRow);
        $rowCount++;

        $pId = (int)$row[$headerMap['ID']];
        $pName = cleanUtf8($row[$headerMap['Product Name']]);
        if ($pId <= 0 || empty($pName)) continue;

        $topCat = cleanUtf8($row[$headerMap['Top Category']] ?? '');
        $midCat = cleanUtf8($row[$headerMap['Mid Category']] ?? '');
        $endCat = cleanUtf8($row[$headerMap['End Category']] ?? '');
        $supplierName = cleanUtf8($row[$headerMap['Supplier']] ?? '');

        $pSku = cleanUtf8($row[$headerMap['SKU']] ?? '');
        if (strtoupper($pSku) === 'N/A') $pSku = '';

        $pBrand = cleanUtf8($row[$headerMap['Brand']] ?? '') ?: 'Generic';

        $rawCurrentPrice = preg_replace('/[^0-9.]/', '', strval($row[$headerMap['Current Price (PHP)']] ?? '0'));
        $pCurrentPrice = floatval($rawCurrentPrice);

        $rawOldPrice = preg_replace('/[^0-9.]/', '', strval($row[$headerMap['Old Price (PHP)']] ?? '0'));
        $pOldPrice = floatval($rawOldPrice);

        $rawCapitalPrice = preg_replace('/[^0-9.]/', '', strval($row[$headerMap['Capital Price (PHP)']] ?? '0'));
        $pCapitalPrice = floatval($rawCapitalPrice);

        $rawMarkup = preg_replace('/[^0-9.]/', '', strval($row[$headerMap['Markup (%)']] ?? '20'));
        $pMarkup = floatval($rawMarkup);

        $pNewPrice = number_format($pCurrentPrice, 2, '.', '');
        $pQty = max(0, intval(preg_replace('/[^0-9]/', '', strval($row[$headerMap['Stock Qty']] ?? '0'))));
        $pSLevel = max(0, intval(preg_replace('/[^0-9]/', '', strval($row[$headerMap['Reorder Level']] ?? '10'))));
        $pMoq = max(1, intval(preg_replace('/[^0-9]/', '', strval($row[$headerMap['MOQ']] ?? '1'))));

        $pStatus = strtolower(cleanUtf8($row[$headerMap['Status']] ?? 'active'));
        $pIsActive = ($pStatus === 'inactive' || $pStatus === '0') ? 0 : 1;

        $pFeatured = strtolower(cleanUtf8($row[$headerMap['Featured']] ?? 'no'));
        $pIsFeatured = ($pFeatured === 'yes' || $pFeatured === '1') ? 1 : 0;

        $pTotalViews = max(1, intval(preg_replace('/[^0-9]/', '', strval($row[$headerMap['Total Views']] ?? '1'))));
        $pDeliveryEst = cleanUtf8($row[$headerMap['Delivery Est.']] ?? '') ?: '3-5 days';
        $pPhoto = cleanUtf8($row[$headerMap['Photo File']] ?? '') ?: 'general_products.png';
        $pShortDesc = cleanUtf8($row[$headerMap['Short Description']] ?? '');
        $pSpecs = cleanUtf8($row[$headerMap['Specifications']] ?? '');

        // Resolve IDs
        $supplierId = resolveSupplierId($supplierName, $suppliers);
        $ecatId = resolveEndCategoryId($pdo, $topCat, $midCat, $endCat, $categoryCache);

        $checkStmt->execute([$pId]);
        $exists = $checkStmt->fetchColumn();

        if ($exists) {
            $updateStmt->execute([
                $pName, $pOldPrice, $pCurrentPrice, $pCapitalPrice, $pMarkup, $pNewPrice,
                $pQty, $pSLevel, $pMoq, $pIsActive, $pIsFeatured,
                $pDeliveryEst, $pPhoto, $pBrand, $pSku,
                $pShortDesc, $pShortDesc, $pSpecs,
                $supplierId, $ecatId, $pId
            ]);
            $updated++;
        } else {
            $insertStmt->execute([
                $pId, $pName, $pOldPrice, $pCurrentPrice, $pQty, $pPhoto,
                $pShortDesc, $pShortDesc,
                $pTotalViews, $pIsFeatured, $pIsActive, $ecatId, $supplierId,
                $pMoq, $pBrand, $pSpecs, $pDeliveryEst, $pSku,
                $pNewPrice, $pSLevel, $pCapitalPrice, $pMarkup
            ]);
            $inserted++;
        }

        if ($rowCount % 100 === 0) {
            echo "  ... Processed {$rowCount} products ({$inserted} inserted, {$updated} updated)\n";
        }
    }

    fclose($file);

    // Sync Sequence
    $pdo->exec("SELECT setval('tbl_product_p_id_seq', (SELECT MAX(p_id) FROM tbl_product))");

    $pdo->commit();

    echo "\n=== MASTER CATALOG SYNCHRONIZATION COMPLETED ===\n";
    echo "Total CSV Records Processed: {$rowCount}\n";
    echo "Newly Inserted Products:      {$inserted}\n";
    echo "Updated Products:             {$updated}\n";

    $totalInDb = $pdo->query("SELECT COUNT(*) FROM tbl_product")->fetchColumn();
    $maxIdInDb = $pdo->query("SELECT MAX(p_id) FROM tbl_product")->fetchColumn();
    $seqVal = $pdo->query("SELECT last_value FROM tbl_product_p_id_seq")->fetchColumn();
    echo "Total Products in Database:  {$totalInDb}\n";
    echo "Max Product ID in Database:  {$maxIdInDb}\n";
    echo "Current Sequence Value:      {$seqVal}\n";

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
