<?php
/**
 * Product-to-Category Canonical Mapping Synchronizer
 *
 * Updates tbl_product.ecat_id on PostgreSQL so that all products match
 * the verified 18-Department taxonomy.
 */

require_once(__DIR__ . '/inc/config.php');

echo "=== STARTING PRODUCT-CATEGORY CANONICAL MAPPING SYNC ===\n";

$mappingFile = dirname(__DIR__) . '/scratch/product_ecat_mapping.json';
if (!file_exists($mappingFile)) {
    $altPaths = [
        __DIR__ . '/product_ecat_mapping.json',
        dirname(__DIR__) . '/product_ecat_mapping.json',
        '/tmp/product_ecat_mapping.json',
        '/root/product_ecat_mapping.json'
    ];
    foreach ($altPaths as $ap) {
        if (file_exists($ap)) {
            $mappingFile = $ap;
            break;
        }
    }
}

if (!file_exists($mappingFile)) {
    die("ERROR: Mapping JSON file not found.\n");
}

echo "Loading mapping: {$mappingFile}\n";
$mapping = json_decode(file_get_contents($mappingFile), true);
if (!is_array($mapping)) {
    die("ERROR: Invalid mapping JSON.\n");
}

try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("UPDATE tbl_product SET ecat_id = ? WHERE p_id = ?");
    $updated = 0;

    foreach ($mapping as $pId => $ecatId) {
        $stmt->execute([(int)$ecatId, (int)$pId]);
        $updated++;
    }

    $pdo->commit();
    echo "=== SYNCED {$updated} PRODUCT CATEGORY MAPPINGS SUCCESSFULLY ===\n";

    $stats = $pdo->query("
        SELECT tcat.tcat_name, count(p.p_id) as prod_count 
        FROM tbl_product p 
        JOIN tbl_end_category ecat ON p.ecat_id = ecat.ecat_id 
        JOIN tbl_mid_category mcat ON ecat.mcat_id = mcat.mcat_id 
        JOIN tbl_top_category tcat ON mcat.tcat_id = tcat.tcat_id 
        WHERE p.supplier_id = 2 
        GROUP BY tcat.tcat_name 
        ORDER BY tcat.tcat_name ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

    echo "\nActive Department Distribution:\n";
    foreach ($stats as $s) {
        echo "  • {$s['tcat_name']}: {$s['prod_count']} products\n";
    }

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
