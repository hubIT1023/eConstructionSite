<?php
/**
 * Homepage Dynamic Product Filter API (api-homepage-products.php)
 * Delivers filtered and grouped parent products with variant specifications.
 */
header('Content-Type: application/json; charset=utf-8');

require_once('admin/inc/config.php');
require_once('admin/inc/functions.php');
global $pdo;

$supplier_id = isset($_GET['supplier_id']) ? intval($_GET['supplier_id']) : 0;
$ecat_id = isset($_GET['ecat_id']) ? intval($_GET['ecat_id']) : 0;
$mcat_id = isset($_GET['mcat_id']) ? intval($_GET['mcat_id']) : 0;
$tcat_id = isset($_GET['tcat_id']) ? intval($_GET['tcat_id']) : 0;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort = isset($_GET['sort']) ? trim($_GET['sort']) : 'popularity';

$where_clauses = ["p.p_is_active = 1"];
$params = [];

// 1. Supplier Filter
if ($supplier_id > 0) {
    $where_clauses[] = "p.supplier_id = ?";
    $params[] = $supplier_id;
}

// Ensure supplier is active
$where_clauses[] = "s.supplier_status = 'Active'";

// 2. Category Filters
if ($ecat_id > 0) {
    $where_clauses[] = "p.ecat_id = ?";
    $params[] = $ecat_id;
} elseif ($mcat_id > 0) {
    $stmt_ecats = $pdo->prepare("SELECT ecat_id FROM tbl_end_category WHERE mcat_id = ?");
    $stmt_ecats->execute([$mcat_id]);
    $ecat_ids = $stmt_ecats->fetchAll(PDO::FETCH_COLUMN);
    if (!empty($ecat_ids)) {
        $in = implode(',', array_fill(0, count($ecat_ids), '?'));
        $where_clauses[] = "p.ecat_id IN ($in)";
        $params = array_merge($params, $ecat_ids);
    } else {
        $where_clauses[] = "1 = 0";
    }
} elseif ($tcat_id > 0) {
    $stmt_ecats = $pdo->prepare("SELECT ec.ecat_id FROM tbl_end_category ec JOIN tbl_mid_category mc ON ec.mcat_id = mc.mcat_id WHERE mc.tcat_id = ?");
    $stmt_ecats->execute([$tcat_id]);
    $ecat_ids = $stmt_ecats->fetchAll(PDO::FETCH_COLUMN);
    if (!empty($ecat_ids)) {
        $in = implode(',', array_fill(0, count($ecat_ids), '?'));
        $where_clauses[] = "p.ecat_id IN ($in)";
        $params = array_merge($params, $ecat_ids);
    } else {
        $where_clauses[] = "1 = 0";
    }
}

// 3. Search Keyword Filter
if (!empty($search)) {
    $like = '%' . $search . '%';
    $where_clauses[] = "(p.p_name ILIKE ? OR p.p_sku ILIKE ? OR p.p_brand ILIKE ? OR ec.ecat_name ILIKE ? OR s.supplier_name ILIKE ?)";
    $params = array_merge($params, [$like, $like, $like, $like, $like]);
}

$where_sql = implode(' AND ', $where_clauses);

// Determine Order By
$order_by = "ec.ecat_name ASC, p.p_name ASC";
if ($sort === 'price_low') {
    $order_by = "NULLIF(regexp_replace(p.p_current_price, '[^0-9.]', '', 'g'), '')::numeric ASC";
} elseif ($sort === 'price_high') {
    $order_by = "NULLIF(regexp_replace(p.p_current_price, '[^0-9.]', '', 'g'), '')::numeric DESC";
} elseif ($sort === 'newest') {
    $order_by = "p.p_id DESC";
} elseif ($sort === 'popularity') {
    $order_by = "p.p_total_view DESC, p.p_id DESC";
}

$sql = "SELECT p.*, ec.ecat_name, mc.mcat_name, s.supplier_name, s.supplier_slug 
    FROM tbl_product p
    LEFT JOIN tbl_end_category ec ON p.ecat_id = ec.ecat_id
    LEFT JOIN tbl_mid_category mc ON ec.mcat_id = mc.mcat_id
    LEFT JOIN tbl_supplier s ON p.supplier_id = s.supplier_id
    WHERE $where_sql
    ORDER BY $order_by";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$raw_products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group into Parent Products
$groups = [];
foreach ($raw_products as $prod) {
    $clean_price = floatval(preg_replace('/[^0-9.]/', '', strval($prod['p_current_price'])));
    $clean_old_price = !empty($prod['p_old_price']) ? floatval(preg_replace('/[^0-9.]/', '', strval($prod['p_old_price']))) : 0;
    $clean_stock = intval(preg_replace('/[^0-9]/', '', strval($prod['p_qty'])));
    $img_src = (!empty($prod['p_featured_photo']) && file_exists('assets/uploads/' . $prod['p_featured_photo'])) 
        ? $prod['p_featured_photo'] 
        : 'photo-6.jpg';

    $parsed_spec = parseConstructionProductDetails($prod['p_name']);
    $base_name = get_master_product_base_name($prod['p_name'], $prod['ecat_id']);

    // Grouping key: supplier_id + ecat_id + sanitized base_name
    $group_key = $prod['supplier_id'] . '_' . $prod['ecat_id'] . '_' . strtolower(preg_replace('/[^a-z0-9]/', '', $base_name));

    $variant_item = [
        'id' => intval($prod['p_id']),
        'sku' => !empty($prod['p_sku']) ? $prod['p_sku'] : ('SKU-' . str_pad($prod['p_id'], 5, '0', STR_PAD_LEFT)),
        'name' => $prod['p_name'],
        'base_name' => $base_name,
        'spec_label' => $parsed_spec['spec_label'],
        'size' => $parsed_spec['size'],
        'thickness' => $parsed_spec['thickness'],
        'diameter' => $parsed_spec['diameter'],
        'color' => $parsed_spec['color'],
        'material' => $parsed_spec['material'],
        'weight_pack' => $parsed_spec['weight_pack'],
        'voltage' => $parsed_spec['voltage'],
        'power' => $parsed_spec['power'],
        'rated_current' => $parsed_spec['rated_current'],
        'length' => $parsed_spec['length'],
        'price' => $clean_price,
        'old_price' => $clean_old_price,
        'stock' => $clean_stock,
        'photo' => $img_src,
        'brand' => !empty($prod['p_brand']) ? $prod['p_brand'] : 'Generic',
        'supplier_id' => intval($prod['supplier_id']),
        'supplier_name' => !empty($prod['supplier_name']) ? $prod['supplier_name'] : 'Supplier',
        'supplier_slug' => !empty($prod['supplier_slug']) ? $prod['supplier_slug'] : ''
    ];

    if (!isset($groups[$group_key])) {
        $groups[$group_key] = [
            'group_key' => $group_key,
            'base_name' => $base_name,
            'ecat_id' => intval($prod['ecat_id']),
            'ecat_name' => !empty($prod['ecat_name']) ? $prod['ecat_name'] : 'General',
            'mcat_name' => !empty($prod['mcat_name']) ? $prod['mcat_name'] : '',
            'brand' => !empty($prod['p_brand']) ? $prod['p_brand'] : 'Generic',
            'supplier_id' => intval($prod['supplier_id']),
            'supplier_name' => !empty($prod['supplier_name']) ? $prod['supplier_name'] : 'Supplier',
            'supplier_slug' => !empty($prod['supplier_slug']) ? $prod['supplier_slug'] : '',
            'photo' => $img_src,
            'min_price' => $clean_price,
            'max_price' => $clean_price,
            'total_stock' => $clean_stock,
            'primary_id' => intval($prod['p_id']),
            'variants' => [$variant_item]
        ];
    } else {
        $groups[$group_key]['variants'][] = $variant_item;
        $groups[$group_key]['total_stock'] += $clean_stock;
        $groups[$group_key]['min_price'] = min($groups[$group_key]['min_price'], $clean_price);
        $groups[$group_key]['max_price'] = max($groups[$group_key]['max_price'], $clean_price);
        if ($groups[$group_key]['photo'] === 'photo-6.jpg' && $img_src !== 'photo-6.jpg') {
            $groups[$group_key]['photo'] = $img_src;
        }
    }
}

$product_groups = array_values($groups);

echo json_encode([
    'success' => true,
    'total_groups' => count($product_groups),
    'total_variants' => count($raw_products),
    'supplier_id' => $supplier_id,
    'products' => $product_groups
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
