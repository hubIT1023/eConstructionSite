<?php
/**
 * Search Suggestions Autocomplete API (search-suggestions.php)
 * Returns matching products, categories, and active suppliers in JSON format.
 * Supports optional supplier_id scoping for supplier storefronts.
 */
header('Content-Type: application/json; charset=utf-8');

require_once('admin/inc/config.php');
require_once('admin/inc/functions.php');
global $pdo;

$query = isset($_GET['q']) ? trim($_GET['q']) : '';
$supplier_id = isset($_GET['supplier_id']) ? intval($_GET['supplier_id']) : 0;

if (strlen($query) < 2) {
    echo json_encode([
        'success' => true,
        'query' => $query,
        'supplier_id' => $supplier_id,
        'products' => [],
        'categories' => [],
        'suppliers' => []
    ]);
    exit;
}

$like_term = '%' . $query . '%';

// 1. Match Active Products (Limit 6)
if ($supplier_id > 0) {
    $stmt_prod = $pdo->prepare("SELECT p.p_id, p.p_name, p.p_current_price, p.p_featured_photo, p.p_brand, ec.ecat_name, s.supplier_name, s.supplier_slug 
        FROM tbl_product p
        LEFT JOIN tbl_end_category ec ON p.ecat_id = ec.ecat_id
        LEFT JOIN tbl_supplier s ON p.supplier_id = s.supplier_id
        WHERE p.p_is_active = 1 
          AND p.supplier_id = ?
          AND (p.p_name ILIKE ? OR p.p_sku ILIKE ? OR p.p_brand ILIKE ? OR ec.ecat_name ILIKE ?)
        ORDER BY p.p_total_view DESC, p.p_id DESC
        LIMIT 6");
    $stmt_prod->execute([$supplier_id, $like_term, $like_term, $like_term, $like_term]);
} else {
    $stmt_prod = $pdo->prepare("SELECT p.p_id, p.p_name, p.p_current_price, p.p_featured_photo, p.p_brand, ec.ecat_name, s.supplier_name, s.supplier_slug 
        FROM tbl_product p
        LEFT JOIN tbl_end_category ec ON p.ecat_id = ec.ecat_id
        LEFT JOIN tbl_supplier s ON p.supplier_id = s.supplier_id
        WHERE p.p_is_active = 1 
          AND (p.p_name ILIKE ? OR p.p_sku ILIKE ? OR p.p_brand ILIKE ? OR ec.ecat_name ILIKE ?)
        ORDER BY p.p_total_view DESC, p.p_id DESC
        LIMIT 6");
    $stmt_prod->execute([$like_term, $like_term, $like_term, $like_term]);
}
$raw_prods = $stmt_prod->fetchAll(PDO::FETCH_ASSOC);

$products = [];
foreach ($raw_prods as $p) {
    $photo = (!empty($p['p_featured_photo']) && file_exists('assets/uploads/' . $p['p_featured_photo'])) 
        ? 'assets/uploads/' . $p['p_featured_photo'] 
        : 'assets/uploads/photo-6.jpg';
    
    $clean_price = floatval(preg_replace('/[^0-9.]/', '', strval($p['p_current_price'])));
    
    $products[] = [
        'id' => intval($p['p_id']),
        'name' => $p['p_name'],
        'price' => number_format($clean_price, 2),
        'photo' => $photo,
        'brand' => $p['p_brand'] ?: 'Generic',
        'category' => $p['ecat_name'] ?: 'General',
        'supplier' => $p['supplier_name'] ?: '',
        'url' => 'product.php?id=' . $p['p_id']
    ];
}

// 2. Match Categories (Limit 4)
$categories = [];

if ($supplier_id > 0) {
    // Only categories that belong to this supplier's active products
    $stmt_mid = $pdo->prepare("SELECT DISTINCT mc.mcat_id, mc.mcat_name 
        FROM tbl_mid_category mc
        JOIN tbl_end_category ec ON mc.mcat_id = ec.mcat_id
        JOIN tbl_product p ON ec.ecat_id = p.ecat_id
        WHERE p.supplier_id = ? AND p.p_is_active = 1 AND mc.mcat_name ILIKE ? 
        LIMIT 2");
    $stmt_mid->execute([$supplier_id, $like_term]);
    while ($m = $stmt_mid->fetch(PDO::FETCH_ASSOC)) {
        $categories[] = [
            'id' => intval($m['mcat_id']),
            'name' => $m['mcat_name'],
            'type' => 'Mid Category',
            'url' => 'javascript:void(0)" onclick="applyStoreCategoryFilter(' . $m['mcat_id'] . ', \'' . htmlspecialchars(addslashes($m['mcat_name'])) . '\', 0)'
        ];
    }

    $stmt_end = $pdo->prepare("SELECT DISTINCT ec.ecat_id, ec.ecat_name 
        FROM tbl_end_category ec
        JOIN tbl_product p ON ec.ecat_id = p.ecat_id
        WHERE p.supplier_id = ? AND p.p_is_active = 1 AND ec.ecat_name ILIKE ? 
        LIMIT 3");
    $stmt_end->execute([$supplier_id, $like_term]);
    while ($e = $stmt_end->fetch(PDO::FETCH_ASSOC)) {
        $categories[] = [
            'id' => intval($e['ecat_id']),
            'name' => $e['ecat_name'],
            'type' => 'End Category',
            'url' => 'javascript:void(0)" onclick="applyStoreCategoryFilter(0, \'' . htmlspecialchars(addslashes($e['ecat_name'])) . '\', ' . $e['ecat_id'] . ')'
        ];
    }
} else {
    // Global Categories
    $stmt_mid = $pdo->prepare("SELECT mcat_id, mcat_name FROM tbl_mid_category WHERE mcat_name ILIKE ? LIMIT 2");
    $stmt_mid->execute([$like_term]);
    while ($m = $stmt_mid->fetch(PDO::FETCH_ASSOC)) {
        $categories[] = [
            'id' => intval($m['mcat_id']),
            'name' => $m['mcat_name'],
            'type' => 'Mid Category',
            'url' => 'product-category.php?id=' . $m['mcat_id'] . '&type=mid-category'
        ];
    }

    $stmt_end = $pdo->prepare("SELECT ecat_id, ecat_name FROM tbl_end_category WHERE ecat_name ILIKE ? LIMIT 3");
    $stmt_end->execute([$like_term]);
    while ($e = $stmt_end->fetch(PDO::FETCH_ASSOC)) {
        $categories[] = [
            'id' => intval($e['ecat_id']),
            'name' => $e['ecat_name'],
            'type' => 'End Category',
            'url' => 'product-category.php?id=' . $e['ecat_id'] . '&type=end-category'
        ];
    }
}

// 3. Match Active Suppliers (Limit 3, only when global search)
$suppliers = [];
if ($supplier_id == 0) {
    $stmt_supp = $pdo->prepare("SELECT supplier_id, supplier_name, supplier_slug, supplier_logo, 
        (SELECT COUNT(*) FROM tbl_product WHERE supplier_id = s.supplier_id AND p_is_active = 1) as prod_count
        FROM tbl_supplier s 
        WHERE supplier_status = 'Active' AND supplier_name ILIKE ? 
        LIMIT 3");
    $stmt_supp->execute([$like_term]);
    $raw_supp = $stmt_supp->fetchAll(PDO::FETCH_ASSOC);

    foreach ($raw_supp as $s) {
        $suppliers[] = [
            'id' => intval($s['supplier_id']),
            'name' => $s['supplier_name'],
            'slug' => $s['supplier_slug'],
            'prod_count' => intval($s['prod_count']),
            'url' => 'store.php?slug=' . $s['supplier_slug']
        ];
    }
}

echo json_encode([
    'success' => true,
    'query' => $query,
    'supplier_id' => $supplier_id,
    'products' => $products,
    'categories' => $categories,
    'suppliers' => $suppliers
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
