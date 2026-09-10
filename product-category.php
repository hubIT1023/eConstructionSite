<?php
require_once('header.php');

// 1. ADD TO CART HANDLER (Standardized Cart Engine)
handle_add_to_cart_submission($pdo, $error_message1, $success_message1, 0);

// Display user alerts if any
if(!empty($error_message1)) {
    echo "<script>alert('".addslashes($error_message1)."');</script>";
}
if(!empty($success_message1)) {
    echo "<script>alert('".addslashes($success_message1)."');</script>";
}

// 2. CATEGORY RESOLUTION & HIERARCHY VALIDATION
if(!isset($_GET['id']) || !isset($_GET['type'])) {
    header('location: index.php');
    exit;
}

$cat_id = intval($_GET['id']);
$cat_type = trim($_GET['type']);

if(!in_array($cat_type, ['top-category', 'mid-category', 'end-category'])) {
    header('location: index.php');
    exit;
}

// Banner Asset
$statement = $pdo->prepare("SELECT * FROM tbl_settings WHERE id=1");
$statement->execute();
$settings = $statement->fetch(PDO::FETCH_ASSOC);
$banner_product_category = (!empty($settings['banner_product_category']) && file_exists('assets/uploads/'.$settings['banner_product_category'])) 
    ? 'assets/uploads/'.$settings['banner_product_category'] 
    : 'assets/uploads/about-banner.jpg';

// Initialize Category Hierarchy Details
$top_cat = null;
$mid_cat = null;
$end_cat = null;
$current_title = '';
$current_type_label = '';
$final_ecat_ids = [];
$subcategories = [];
$tcat_param = 0;
$mcat_param = 0;
$ecat_param = 0;

if ($cat_type === 'top-category') {
    $tcat_param = $cat_id;
    $stmt = $pdo->prepare("SELECT * FROM tbl_top_category WHERE tcat_id = ?");
    $stmt->execute([$cat_id]);
    $top_cat = $stmt->fetch(PDO::FETCH_ASSOC);
    if(!$top_cat) {
        header('location: index.php');
        exit;
    }
    $current_title = $top_cat['tcat_name'];
    $current_type_label = 'Top-Level Department';

    // Find all mid and end categories under this top category
    $stmt_mids = $pdo->prepare("SELECT * FROM tbl_mid_category WHERE tcat_id = ? ORDER BY mcat_name ASC");
    $stmt_mids->execute([$cat_id]);
    $mids = $stmt_mids->fetchAll(PDO::FETCH_ASSOC);

    foreach ($mids as $m) {
        $stmt_ends = $pdo->prepare("SELECT e.*, COUNT(p.p_id) as prod_count 
            FROM tbl_end_category e 
            LEFT JOIN tbl_product p ON e.ecat_id = p.ecat_id AND p.p_is_active = 1
            WHERE e.mcat_id = ?
            GROUP BY e.ecat_id, e.ecat_name, e.mcat_id
            ORDER BY e.ecat_name ASC");
        $stmt_ends->execute([$m['mcat_id']]);
        $ends = $stmt_ends->fetchAll(PDO::FETCH_ASSOC);
        
        $subcategories[] = [
            'mcat_id' => $m['mcat_id'],
            'mcat_name' => $m['mcat_name'],
            'end_cats' => $ends
        ];

        foreach ($ends as $e) {
            $final_ecat_ids[] = intval($e['ecat_id']);
        }
    }
} elseif ($cat_type === 'mid-category') {
    $mcat_param = $cat_id;
    $stmt = $pdo->prepare("SELECT m.*, t.tcat_name FROM tbl_mid_category m JOIN tbl_top_category t ON m.tcat_id = t.tcat_id WHERE m.mcat_id = ?");
    $stmt->execute([$cat_id]);
    $mid_cat = $stmt->fetch(PDO::FETCH_ASSOC);
    if(!$mid_cat) {
        header('location: index.php');
        exit;
    }
    $tcat_param = intval($mid_cat['tcat_id']);
    $top_cat = ['tcat_id' => $mid_cat['tcat_id'], 'tcat_name' => $mid_cat['tcat_name']];
    $current_title = $mid_cat['mcat_name'];
    $current_type_label = 'Category Division';

    // Find all end categories under this mid category
    $stmt_ends = $pdo->prepare("SELECT e.*, COUNT(p.p_id) as prod_count 
        FROM tbl_end_category e 
        LEFT JOIN tbl_product p ON e.ecat_id = p.ecat_id AND p.p_is_active = 1
        WHERE e.mcat_id = ?
        GROUP BY e.ecat_id, e.ecat_name, e.mcat_id
        ORDER BY e.ecat_name ASC");
    $stmt_ends->execute([$cat_id]);
    $ends = $stmt_ends->fetchAll(PDO::FETCH_ASSOC);

    $subcategories[] = [
        'mcat_id' => $mid_cat['mcat_id'],
        'mcat_name' => $mid_cat['mcat_name'],
        'end_cats' => $ends
    ];

    foreach ($ends as $e) {
        $final_ecat_ids[] = intval($e['ecat_id']);
    }
} elseif ($cat_type === 'end-category') {
    $ecat_param = $cat_id;
    $stmt = $pdo->prepare("SELECT e.*, m.mcat_id, m.mcat_name, m.tcat_id, t.tcat_name 
        FROM tbl_end_category e 
        JOIN tbl_mid_category m ON e.mcat_id = m.mcat_id 
        JOIN tbl_top_category t ON m.tcat_id = t.tcat_id 
        WHERE e.ecat_id = ?");
    $stmt->execute([$cat_id]);
    $end_cat = $stmt->fetch(PDO::FETCH_ASSOC);
    if(!$end_cat) {
        header('location: index.php');
        exit;
    }
    $tcat_param = intval($end_cat['tcat_id']);
    $mcat_param = intval($end_cat['mcat_id']);
    $top_cat = ['tcat_id' => $end_cat['tcat_id'], 'tcat_name' => $end_cat['tcat_name']];
    $mid_cat = ['mcat_id' => $end_cat['mcat_id'], 'mcat_name' => $end_cat['mcat_name']];
    $current_title = $end_cat['ecat_name'];
    $current_type_label = 'Product Class';
    $final_ecat_ids = [intval($cat_id)];

    // Fetch sibling end categories for quick navigation
    $stmt_ends = $pdo->prepare("SELECT e.*, COUNT(p.p_id) as prod_count 
        FROM tbl_end_category e 
        LEFT JOIN tbl_product p ON e.ecat_id = p.ecat_id AND p.p_is_active = 1
        WHERE e.mcat_id = ?
        GROUP BY e.ecat_id, e.ecat_name, e.mcat_id
        ORDER BY e.ecat_name ASC");
    $stmt_ends->execute([$end_cat['mcat_id']]);
    $ends = $stmt_ends->fetchAll(PDO::FETCH_ASSOC);

    $subcategories[] = [
        'mcat_id' => $end_cat['mcat_id'],
        'mcat_name' => $end_cat['mcat_name'],
        'end_cats' => $ends
    ];
}

// 3. DYNAMICALLY QUERY ACTIVE SUPPLIERS WITH PRODUCTS IN THIS CATEGORY SCOPE
$active_category_suppliers = [];
if (!empty($final_ecat_ids)) {
    $in_clause = implode(',', array_fill(0, count($final_ecat_ids), '?'));
    $stmt_supp = $pdo->prepare("SELECT s.supplier_id, s.supplier_name, s.supplier_slug, COUNT(p.p_id) as prod_count 
        FROM tbl_supplier s 
        JOIN tbl_product p ON s.supplier_id = p.supplier_id 
        WHERE p.ecat_id IN ($in_clause) AND p.p_is_active = 1 AND s.supplier_status = 'Active'
        GROUP BY s.supplier_id, s.supplier_name, s.supplier_slug
        ORDER BY prod_count DESC, s.supplier_name ASC");
    $stmt_supp->execute($final_ecat_ids);
    $active_category_suppliers = $stmt_supp->fetchAll(PDO::FETCH_ASSOC);
}

// 4. QUERY INITIAL ACTIVE PRODUCTS IN THIS CATEGORY SCOPE (Parent Product Grouping)
$initial_supplier_id = isset($_GET['supplier_id']) ? intval($_GET['supplier_id']) : 0;
$initial_search = isset($_GET['search']) ? trim($_GET['search']) : '';
$initial_sort = isset($_GET['sort']) ? trim($_GET['sort']) : 'popularity';

$grouped_category_products = [];
$total_matching_variants = 0;

if (!empty($final_ecat_ids)) {
    $where_sql = "p.ecat_id IN (" . implode(',', array_fill(0, count($final_ecat_ids), '?')) . ") AND p.p_is_active = 1 AND s.supplier_status = 'Active'";
    $query_params = $final_ecat_ids;

    if ($initial_supplier_id > 0) {
        $where_sql .= " AND p.supplier_id = ?";
        $query_params[] = $initial_supplier_id;
    }

    if (!empty($initial_search)) {
        $like = '%' . $initial_search . '%';
        $where_sql .= " AND (p.p_name ILIKE ? OR p.p_sku ILIKE ? OR p.p_brand ILIKE ? OR ec.ecat_name ILIKE ? OR s.supplier_name ILIKE ?)";
        $query_params = array_merge($query_params, [$like, $like, $like, $like, $like]);
    }

    $order_by = "ec.ecat_name ASC, p.p_name ASC";
    if ($initial_sort === 'price_low') {
        $order_by = "NULLIF(regexp_replace(p.p_current_price, '[^0-9.]', '', 'g'), '')::numeric ASC";
    } elseif ($initial_sort === 'price_high') {
        $order_by = "NULLIF(regexp_replace(p.p_current_price, '[^0-9.]', '', 'g'), '')::numeric DESC";
    } elseif ($initial_sort === 'newest') {
        $order_by = "p.p_id DESC";
    } elseif ($initial_sort === 'popularity') {
        $order_by = "p.p_total_view DESC, p.p_id DESC";
    }

    $stmt_prod = $pdo->prepare("SELECT p.*, ec.ecat_name, mc.mcat_name, s.supplier_name, s.supplier_slug 
        FROM tbl_product p 
        LEFT JOIN tbl_end_category ec ON p.ecat_id = ec.ecat_id
        LEFT JOIN tbl_mid_category mc ON ec.mcat_id = mc.mcat_id
        LEFT JOIN tbl_supplier s ON p.supplier_id = s.supplier_id
        WHERE $where_sql
        ORDER BY $order_by");
    $stmt_prod->execute($query_params);
    $raw_products = $stmt_prod->fetchAll(PDO::FETCH_ASSOC);
    $total_matching_variants = count($raw_products);

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

        if (!isset($grouped_category_products[$group_key])) {
            $grouped_category_products[$group_key] = [
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
                'has_discount' => ($clean_old_price > $clean_price),
                'discount_pct' => ($clean_old_price > $clean_price) ? round((($clean_old_price - $clean_price) / $clean_old_price) * 100) : 0,
                'variants' => [$variant_item]
            ];
        } else {
            $grouped_category_products[$group_key]['variants'][] = $variant_item;
            $grouped_category_products[$group_key]['total_stock'] += $clean_stock;
            $grouped_category_products[$group_key]['min_price'] = min($grouped_category_products[$group_key]['min_price'], $clean_price);
            $grouped_category_products[$group_key]['max_price'] = max($grouped_category_products[$group_key]['max_price'], $clean_price);
            if ($clean_old_price > $clean_price) {
                $grouped_category_products[$group_key]['has_discount'] = true;
                $grouped_category_products[$group_key]['discount_pct'] = max($grouped_category_products[$group_key]['discount_pct'], round((($clean_old_price - $clean_price) / $clean_old_price) * 100));
            }
            if ($grouped_category_products[$group_key]['photo'] === 'photo-6.jpg' && $img_src !== 'photo-6.jpg') {
                $grouped_category_products[$group_key]['photo'] = $img_src;
            }
        }
    }
}
$grouped_product_list = array_values($grouped_category_products);
?>

<!-- ========================================================================= -->
<!-- STANDARDIZED CATEGORY PAGE STYLING (Shopee/AliExpress UX + Industrial)     -->
<!-- ========================================================================= -->
<style>
:root {
    --mkt-primary: #0f172a;       /* Dark Industrial Navy */
    --mkt-accent: #2563eb;        /* Construction Blue */
    --mkt-amber: #f59e0b;         /* Caution / Safety Amber */
    --mkt-red: #e11d48;           /* Deal Red */
    --mkt-green: #059669;         /* Verified Green */
    --mkt-bg: #f8fafc;            /* Crisp Body Background */
    --mkt-card-border: #e2e8f0;
    --mkt-text-main: #1e293b;
    --mkt-text-muted: #64748b;
}

body {
    background-color: var(--mkt-bg);
    color: var(--mkt-text-main);
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
}

/* Category Breadcrumb Bar */
.cat-breadcrumb-bar {
    background: #ffffff;
    border-bottom: 1px solid var(--mkt-card-border);
    padding: 10px 0;
    margin-bottom: 15px;
}
.cat-breadcrumb {
    margin: 0;
    padding: 0;
    list-style: none;
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
    flex-wrap: wrap;
}
.cat-breadcrumb li a {
    color: #2563eb;
    text-decoration: none;
    font-weight: 600;
}
.cat-breadcrumb li a:hover {
    text-decoration: underline;
}
.cat-breadcrumb li.active {
    color: #64748b;
    font-weight: 600;
}
.cat-breadcrumb-sep {
    color: #94a3b8;
    font-size: 11px;
}

/* Category Hero Banner & Summary Card */
.cat-hero-card {
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
    color: #ffffff;
    border-radius: 10px;
    padding: 24px 28px;
    margin-bottom: 20px;
    box-shadow: 0 4px 15px rgba(15,23,42,0.12);
    position: relative;
    overflow: hidden;
    border-left: 5px solid #2563eb;
}
.cat-hero-badge {
    background: rgba(37,99,235,0.25);
    color: #93c5fd;
    border: 1px solid rgba(59,130,246,0.4);
    font-size: 11px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 3px 10px;
    border-radius: 20px;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    margin-bottom: 8px;
}
.cat-hero-title {
    font-size: 26px;
    font-weight: 900;
    color: #ffffff;
    margin: 0 0 6px 0;
    letter-spacing: -0.3px;
}
.cat-hero-subtitle {
    font-size: 14px;
    color: #94a3b8;
    margin: 0;
    line-height: 1.4;
}
.cat-hero-stats {
    display: flex;
    gap: 16px;
    margin-top: 14px;
    flex-wrap: wrap;
}
.cat-stat-chip {
    background: rgba(255,255,255,0.08);
    border: 1px solid rgba(255,255,255,0.15);
    padding: 4px 12px;
    border-radius: 6px;
    font-size: 12.5px;
    font-weight: 600;
    color: #e2e8f0;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.cat-stat-chip i {
    color: #38bdf8;
}

/* Category Discovery Controls Bar */
.cat-discovery-bar {
    background: #1e293b;
    border-radius: 8px;
    padding: 12px 16px;
    margin-bottom: 16px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    box-shadow: 0 2px 6px rgba(0,0,0,0.08);
}
.cat-search-box {
    position: relative;
    flex-grow: 1;
    min-width: 240px;
    max-width: 420px;
}
.cat-search-box input {
    width: 100%;
    padding: 9px 12px 9px 36px;
    border-radius: 6px;
    border: 1px solid #334155;
    background: #0f172a;
    color: #fff;
    font-size: 13.5px;
    outline: none;
    transition: all 0.2s;
}
.cat-search-box input:focus {
    border-color: #3b82f6;
    background: #020617;
    box-shadow: 0 0 0 2px rgba(59,130,246,0.3);
}
.cat-search-box i {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: #94a3b8;
    font-size: 14px;
}
.cat-sort-wrap {
    display: flex;
    align-items: center;
    gap: 8px;
}
.cat-sort-wrap label {
    margin: 0;
    color: #cbd5e1;
    font-size: 12.5px;
    font-weight: 600;
    white-space: nowrap;
}
.cat-sort-wrap select {
    background: #0f172a;
    color: #fff;
    border: 1px solid #334155;
    border-radius: 6px;
    padding: 8px 12px;
    font-size: 13px;
    font-weight: 600;
    outline: none;
}

/* Locked Active Filters Component */
.active-filter-chips {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    margin-top: 10px;
    padding-top: 10px;
    border-top: 1px solid #334155;
    width: 100%;
}
.filter-chip-locked {
    background: #1e3a8a;
    color: #bfdbfe;
    border: 1px solid #3b82f6;
    padding: 4px 12px;
    border-radius: 14px;
    font-size: 12px;
    font-weight: 800;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.2);
}
.filter-chip {
    background: #e0f2fe;
    color: #0369a1;
    border: 1px solid #bae6fd;
    padding: 4px 10px;
    border-radius: 14px;
    font-size: 12px;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.filter-chip .chip-remove {
    cursor: pointer;
    color: #0284c7;
    font-weight: bold;
    margin-left: 2px;
}
.filter-chip .chip-remove:hover {
    color: #b91c1c;
}

/* Sidebar Navigation */
.cat-sidebar-card {
    background: #ffffff;
    border: 1px solid var(--mkt-card-border);
    border-radius: 8px;
    padding: 16px;
    margin-bottom: 20px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.03);
}
.cat-sidebar-title {
    font-size: 14.5px;
    font-weight: 800;
    color: var(--mkt-primary);
    margin: 0 0 12px 0;
    padding-bottom: 8px;
    border-bottom: 2px solid #f1f5f9;
    display: flex;
    align-items: center;
    gap: 6px;
}
.cat-nav-list {
    list-style: none;
    margin: 0;
    padding: 0;
}
.cat-nav-item {
    margin-bottom: 4px;
}
.cat-nav-link {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 7px 10px;
    border-radius: 6px;
    color: #334155;
    font-size: 13px;
    font-weight: 600;
    text-decoration: none !important;
    transition: all 0.15s;
}
.cat-nav-link:hover, .cat-nav-link.active {
    background: #eff6ff;
    color: #2563eb;
    font-weight: 700;
    padding-left: 12px;
}
.cat-nav-link .badge-count {
    background: #f1f5f9;
    color: #64748b;
    font-size: 11px;
    padding: 2px 7px;
    border-radius: 10px;
    font-weight: 700;
}
.cat-nav-link.active .badge-count {
    background: #2563eb;
    color: #fff;
}

/* Standardized Marketplace Product Card ("One Parent Product = One Product Card") */
.cat-product-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 16px;
}
@media (max-width: 768px) {
    .cat-product-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 10px;
    }
}

.mkt-prod-card {
    background: #ffffff;
    border: 1px solid var(--mkt-card-border);
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 1px 4px rgba(0,0,0,0.03);
    transition: all 0.2s ease-in-out;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    position: relative;
}
.mkt-prod-card:hover {
    border-color: #2563eb;
    box-shadow: 0 8px 20px rgba(37,99,235,0.12);
    transform: translateY(-3px);
}
.mkt-prod-thumb {
    height: 180px;
    position: relative;
    background: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    border-bottom: 1px solid #f1f5f9;
}
.mkt-prod-thumb img {
    max-height: 160px;
    max-width: 90%;
    object-fit: contain;
    transition: transform 0.3s ease;
}
.mkt-prod-card:hover .mkt-prod-thumb img {
    transform: scale(1.05);
}
.mkt-badge-cat {
    position: absolute;
    top: 8px;
    left: 8px;
    background: rgba(15, 23, 42, 0.85);
    color: #fff;
    font-size: 10px;
    font-weight: 700;
    padding: 2px 7px;
    border-radius: 4px;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    max-width: 140px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.mkt-badge-stock {
    position: absolute;
    top: 8px;
    right: 8px;
    font-size: 10.5px;
    font-weight: 700;
    padding: 2px 7px;
    border-radius: 4px;
}
.mkt-prod-body {
    padding: 12px;
    display: flex;
    flex-direction: column;
    flex-grow: 1;
}
.mkt-prod-supplier-pill {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    color: #0369a1;
    background: #e0f2fe;
    font-size: 10.5px;
    font-weight: 700;
    padding: 2px 6px;
    border-radius: 4px;
    margin-bottom: 6px;
    text-decoration: none !important;
    width: fit-content;
}
.mkt-prod-supplier-pill:hover {
    background: #bae6fd;
}
.mkt-prod-title {
    font-size: 14px;
    font-weight: 700;
    line-height: 1.35;
    color: #0f172a;
    margin: 0 0 6px 0;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    min-height: 38px;
}
.mkt-prod-title a {
    color: #0f172a;
    text-decoration: none;
}
.mkt-prod-title a:hover {
    color: #2563eb;
}
.mkt-variant-pill {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    background: #f1f5f9;
    color: #475569;
    font-size: 11px;
    font-weight: 700;
    padding: 3px 8px;
    border-radius: 4px;
    margin-bottom: 8px;
    width: fit-content;
}
.mkt-prod-price {
    font-size: 17px;
    font-weight: 900;
    color: #e11d48;
    margin-bottom: 10px;
    line-height: 1.2;
}
.mkt-btn-add {
    margin-top: auto;
    width: 100%;
    background: #2563eb;
    color: #ffffff;
    border: none;
    border-radius: 6px;
    padding: 8px 12px;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
    transition: background 0.15s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    text-decoration: none !important;
}
.mkt-btn-add:hover {
    background: #1d4ed8;
    color: #ffffff;
}
.mkt-btn-add.disabled {
    background: #94a3b8;
    cursor: not-allowed;
}

/* Variant Modal Chips */
.cat-variant-chip {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 6px 10px;
    border: 1.5px solid #cbd5e1;
    background: #fff;
    color: #1e293b;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.15s ease-in-out;
    text-decoration: none !important;
}
.cat-variant-chip:hover {
    border-color: #2563eb;
    background: #eff6ff;
    color: #2563eb;
}
.cat-variant-chip.active {
    border-color: #2563eb;
    background: #2563eb;
    color: #fff;
}
.cat-variant-chip.disabled {
    opacity: 0.5;
    cursor: not-allowed;
    background: #f1f5f9;
    border-color: #e2e8f0;
}
.cat-color-badge {
    display: inline-block;
    padding: 1px 7px;
    border-radius: 10px;
    font-size: 11px;
    font-weight: 800;
    border: 1px solid #cbd5e1;
    background: #fff;
    color: #334155;
    text-transform: uppercase;
}
</style>

<!-- BREADCRUMB NAVIGATION -->
<div class="cat-breadcrumb-bar">
    <div class="container">
        <ul class="cat-breadcrumb">
            <li><a href="index.php"><i class="fa fa-home"></i> Home</a></li>
            <li class="cat-breadcrumb-sep"><i class="fa fa-angle-right"></i></li>
            <li><a href="product-category.php?id=1&type=top-category">All Categories</a></li>
            
            <?php if($top_cat): ?>
                <li class="cat-breadcrumb-sep"><i class="fa fa-angle-right"></i></li>
                <?php if($cat_type === 'top-category'): ?>
                    <li class="active"><?php echo htmlspecialchars($top_cat['tcat_name']); ?></li>
                <?php else: ?>
                    <li><a href="product-category.php?id=<?php echo $top_cat['tcat_id']; ?>&type=top-category"><?php echo htmlspecialchars($top_cat['tcat_name']); ?></a></li>
                <?php endif; ?>
            <?php endif; ?>

            <?php if($mid_cat): ?>
                <li class="cat-breadcrumb-sep"><i class="fa fa-angle-right"></i></li>
                <?php if($cat_type === 'mid-category'): ?>
                    <li class="active"><?php echo htmlspecialchars($mid_cat['mcat_name']); ?></li>
                <?php else: ?>
                    <li><a href="product-category.php?id=<?php echo $mid_cat['mcat_id']; ?>&type=mid-category"><?php echo htmlspecialchars($mid_cat['mcat_name']); ?></a></li>
                <?php endif; ?>
            <?php endif; ?>

            <?php if($end_cat): ?>
                <li class="cat-breadcrumb-sep"><i class="fa fa-angle-right"></i></li>
                <li class="active"><?php echo htmlspecialchars($end_cat['ecat_name']); ?></li>
            <?php endif; ?>
        </ul>
    </div>
</div>

<div class="container" style="margin-bottom: 50px;">
    
    <!-- CATEGORY HERO BANNER -->
    <div class="cat-hero-card">
        <div class="cat-hero-badge">
            <i class="fa fa-th-large"></i> <?php echo htmlspecialchars($current_type_label); ?>
        </div>
        <h1 class="cat-hero-title"><?php echo htmlspecialchars($current_title); ?></h1>
        <p class="cat-hero-subtitle">
            Browse verified commercial and residential construction supplies under <?php echo htmlspecialchars($current_title); ?> with real-time stock and competitive wholesale rates.
        </p>
        <div class="cat-hero-stats">
            <div class="cat-stat-chip">
                <i class="fa fa-cubes"></i> <strong><span id="catTotalProdCount"><?php echo count($grouped_product_list); ?></span></strong> Products Available
            </div>
            <div class="cat-stat-chip">
                <i class="fa fa-tags"></i> <strong><span id="catTotalVarCount"><?php echo $total_matching_variants; ?></span></strong> Total Specifications
            </div>
            <div class="cat-stat-chip">
                <i class="fa fa-truck"></i> <strong><?php echo count($active_category_suppliers); ?></strong> Verified Suppliers
            </div>
        </div>
    </div>

    <div class="row">
        
        <!-- SIDEBAR: SUBCATEGORIES & SUPPLIERS -->
        <div class="col-md-3 col-sm-4">
            
            <!-- Subcategories Widget -->
            <?php if(!empty($subcategories)): ?>
            <div class="cat-sidebar-card">
                <h3 class="cat-sidebar-title">
                    <i class="fa fa-sitemap text-primary"></i> Subcategories
                </h3>
                <ul class="cat-nav-list">
                    <?php foreach($subcategories as $sub): ?>
                        <?php if($cat_type === 'top-category'): ?>
                            <li class="cat-nav-item" style="margin-top: 8px;">
                                <a href="product-category.php?id=<?php echo $sub['mcat_id']; ?>&type=mid-category" class="cat-nav-link" style="font-weight: 800; color: #1e3a8a; background: #f8fafc;">
                                    <span><i class="fa fa-folder-open text-primary"></i> <?php echo htmlspecialchars($sub['mcat_name']); ?></span>
                                    <span class="badge-count"><?php echo count($sub['end_cats']); ?></span>
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <?php foreach($sub['end_cats'] as $ec): 
                            $is_active_ecat = ($cat_type === 'end-category' && $cat_id == $ec['ecat_id']);
                        ?>
                            <li class="cat-nav-item" style="<?php echo ($cat_type === 'top-category') ? 'margin-left: 12px;' : ''; ?>">
                                <a href="product-category.php?id=<?php echo $ec['ecat_id']; ?>&type=end-category" class="cat-nav-link <?php echo $is_active_ecat ? 'active' : ''; ?>">
                                    <span><i class="fa fa-angle-right" style="color: #94a3b8; margin-right: 4px;"></i> <?php echo htmlspecialchars($ec['ecat_name']); ?></span>
                                    <span class="badge-count"><?php echo intval($ec['prod_count']); ?></span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <!-- Category-Scoped Suppliers Widget -->
            <?php if(!empty($active_category_suppliers)): ?>
            <div class="cat-sidebar-card">
                <h3 class="cat-sidebar-title">
                    <i class="fa fa-building text-primary"></i> Filter By Supplier
                </h3>
                <ul class="cat-nav-list">
                    <li class="cat-nav-item">
                        <a href="javascript:void(0)" onclick="applySupplierFilter(0, 'All Suppliers')" class="cat-nav-link <?php echo ($initial_supplier_id == 0) ? 'active' : ''; ?>" id="suppLink_0">
                            <span><i class="fa fa-globe text-muted"></i> All Suppliers</span>
                            <span class="badge-count"><?php echo count($grouped_product_list); ?></span>
                        </a>
                    </li>
                    <?php foreach($active_category_suppliers as $asup): 
                        $is_active_sup = ($initial_supplier_id == $asup['supplier_id']);
                    ?>
                    <li class="cat-nav-item">
                        <a href="javascript:void(0)" onclick="applySupplierFilter(<?php echo $asup['supplier_id']; ?>, '<?php echo htmlspecialchars(addslashes($asup['supplier_name'])); ?>')" class="cat-nav-link <?php echo $is_active_sup ? 'active' : ''; ?>" id="suppLink_<?php echo $asup['supplier_id']; ?>">
                            <span><i class="fa fa-check-circle text-success"></i> <?php echo htmlspecialchars($asup['supplier_name']); ?></span>
                            <span class="badge-count"><?php echo intval($asup['prod_count']); ?></span>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

        </div>

        <!-- MAIN PRODUCT DISCOVERY COLUMN -->
        <div class="col-md-9 col-sm-8">
            
            <!-- DISCOVERY CONTROLS & LOCKED ACTIVE FILTERS BAR -->
            <div class="cat-discovery-bar">
                <div class="cat-search-box">
                    <i class="fa fa-search"></i>
                    <input type="text" id="catSearchInput" placeholder="Search within <?php echo htmlspecialchars($current_title); ?>..." value="<?php echo htmlspecialchars($initial_search); ?>" onkeyup="onCategorySearchInput(this.value)">
                </div>

                <div class="cat-sort-wrap">
                    <label for="catSortSelect"><i class="fa fa-sort-amount-desc"></i> Sort:</label>
                    <select id="catSortSelect" onchange="onCategorySortChange(this.value)">
                        <option value="popularity" <?php echo ($initial_sort === 'popularity') ? 'selected' : ''; ?>>Popularity / Most Viewed</option>
                        <option value="price_low" <?php echo ($initial_sort === 'price_low') ? 'selected' : ''; ?>>Price: Low to High</option>
                        <option value="price_high" <?php echo ($initial_sort === 'price_high') ? 'selected' : ''; ?>>Price: High to Low</option>
                        <option value="newest" <?php echo ($initial_sort === 'newest') ? 'selected' : ''; ?>>Newest Arrivals</option>
                    </select>
                </div>

                <!-- STANDARDIZED ACTIVE FILTERS (Locked Category + Optional Supplier & Search Chips) -->
                <div class="active-filter-chips" id="catActiveFilterChips">
                    <!-- LOCKED CATEGORY CHIP -->
                    <span class="filter-chip-locked" title="Category filter is locked for this discovery view">
                        <i class="fa fa-lock"></i> Category: <?php echo htmlspecialchars($current_title); ?>
                    </span>

                    <!-- DYNAMIC REMOVABLE SUPPLIER CHIP -->
                    <span class="filter-chip" id="chipSupplier" style="<?php echo ($initial_supplier_id > 0) ? 'display:inline-flex;' : 'display:none;'; ?>">
                        <i class="fa fa-building"></i> Supplier: <strong id="chipSupplierName" style="margin-left: 3px;">-</strong>
                        <span class="chip-remove" onclick="removeSupplierFilter()" title="Remove supplier filter">&times;</span>
                    </span>

                    <!-- DYNAMIC REMOVABLE SEARCH CHIP -->
                    <span class="filter-chip" id="chipSearch" style="<?php echo (!empty($initial_search)) ? 'display:inline-flex;' : 'display:none;'; ?>">
                        <i class="fa fa-search"></i> Search: "<span id="chipSearchQuery"><?php echo htmlspecialchars($initial_search); ?></span>"
                        <span class="chip-remove" onclick="removeSearchFilter()" title="Clear search">&times;</span>
                    </span>

                    <!-- CLEAR FILTERS BUTTON -->
                    <button type="button" class="btn btn-xs btn-default" id="btnClearFilters" style="<?php echo ($initial_supplier_id > 0 || !empty($initial_search)) ? 'display:inline-block;' : 'display:none;'; ?> font-weight:700; color:#cbd5e1; background:#0f172a; border-color:#334155; margin-left:auto; border-radius:12px; padding:2px 10px;" onclick="clearAllFilters()">
                        <i class="fa fa-times-circle"></i> Clear Filters
                    </button>
                </div>
            </div>

            <!-- PRODUCT GRID / CONTAINER -->
            <div id="catProductGrid" class="cat-product-grid">
                <?php if (empty($grouped_product_list)): ?>
                    <div class="col-md-12 text-center" style="grid-column: 1 / -1; padding: 60px 20px; background: #fff; border-radius: 8px; border: 1px solid #e2e8f0;">
                        <i class="fa fa-cubes fa-3x" style="color: #cbd5e1; margin-bottom: 12px;"></i>
                        <h4 style="font-weight: 700; color: #334155; margin: 0 0 6px 0;">No Products Found</h4>
                        <p class="text-muted" style="font-size: 13.5px; margin-bottom: 16px;">There are no products matching your selected filters in <?php echo htmlspecialchars($current_title); ?>.</p>
                        <button type="button" class="btn btn-primary btn-sm" onclick="clearAllFilters()" style="background: #2563eb; border-color: #2563eb; font-weight: 700;">Reset Filters</button>
                    </div>
                <?php else: ?>
                    <?php foreach ($grouped_product_list as $grp): 
                        $is_out_of_stock = ($grp['total_stock'] <= 0);
                        $variant_count = count($grp['variants']);
                        $card_json = htmlspecialchars(json_encode($grp), ENT_QUOTES, 'UTF-8');
                    ?>
                    <div class="mkt-prod-card" data-group-key="<?php echo htmlspecialchars($grp['group_key']); ?>">
                        <div class="mkt-prod-thumb">
                            <span class="mkt-badge-cat"><?php echo htmlspecialchars($grp['ecat_name']); ?></span>
                            <?php if ($is_out_of_stock): ?>
                                <span class="mkt-badge-stock label label-danger">Out of Stock</span>
                            <?php elseif ($grp['total_stock'] < 10): ?>
                                <span class="mkt-badge-stock label label-warning"><?php echo $grp['total_stock']; ?> Left</span>
                            <?php else: ?>
                                <span class="mkt-badge-stock label label-success">In Stock</span>
                            <?php endif; ?>

                            <a href="javascript:void(0)" onclick="handleCategoryProductClick(this)" data-group='<?php echo $card_json; ?>' style="display: flex; align-items: center; justify-content: center; width: 100%; height: 100%;">
                                <img src="assets/uploads/<?php echo htmlspecialchars($grp['photo']); ?>" alt="<?php echo htmlspecialchars($grp['base_name']); ?>" loading="lazy">
                            </a>
                        </div>

                        <div class="mkt-prod-body">
                            <!-- Supplier Pill -->
                            <a href="store.php?slug=<?php echo htmlspecialchars($grp['supplier_slug']); ?>" class="mkt-prod-supplier-pill" title="View <?php echo htmlspecialchars($grp['supplier_name']); ?> Store">
                                <i class="fa fa-building-o"></i> <?php echo htmlspecialchars($grp['supplier_name']); ?>
                            </a>

                            <!-- Product Base Name -->
                            <h3 class="mkt-prod-title">
                                <a href="javascript:void(0)" onclick="handleCategoryProductClick(this)" data-group='<?php echo $card_json; ?>'>
                                    <?php echo htmlspecialchars($grp['base_name']); ?>
                                </a>
                            </h3>

                            <!-- Variant Count / Specification Pill -->
                            <?php if ($variant_count > 1): ?>
                                <div class="mkt-variant-pill">
                                    <i class="fa fa-th-list text-primary"></i> <?php echo $variant_count; ?> Variants / Sizes
                                </div>
                            <?php else: ?>
                                <div class="mkt-variant-pill">
                                    <i class="fa fa-cube text-muted"></i> <?php echo htmlspecialchars($grp['variants'][0]['spec_label']); ?>
                                </div>
                            <?php endif; ?>

                            <!-- Price Range or Single Price -->
                            <div class="mkt-prod-price">
                                <?php if ($grp['min_price'] == $grp['max_price']): ?>
                                    &#8369;<?php echo number_format($grp['min_price'], 2); ?>
                                <?php else: ?>
                                    &#8369;<?php echo number_format($grp['min_price'], 2); ?> - &#8369;<?php echo number_format($grp['max_price'], 2); ?>
                                <?php endif; ?>
                            </div>

                            <!-- Add Button -->
                            <?php if ($is_out_of_stock): ?>
                                <button type="button" class="mkt-btn-add disabled" disabled>
                                    <i class="fa fa-ban"></i> Out of Stock
                                </button>
                            <?php else: ?>
                                <button type="button" class="mkt-btn-add" onclick="handleCategoryProductClick(this)" data-group='<?php echo $card_json; ?>'>
                                    <i class="fa fa-shopping-cart"></i> <?php echo ($variant_count > 1) ? '+ Add / Select Variant' : '+ Add to Cart'; ?>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

        </div>

    </div>

</div>

<!-- ========================================================================= -->
<!-- STANDARDIZED SPECIFICATION & VARIANT SELECTION MODAL                      -->
<!-- ========================================================================= -->
<div class="modal fade" id="categoryVariantModal" tabindex="-1" role="dialog" aria-labelledby="catVariantModalLabel" aria-hidden="true" style="z-index: 10050;">
    <div class="modal-dialog" role="document" style="max-width: 540px; margin-top: 60px;">
        <div class="modal-content" style="border-radius: 10px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.3); border: none;">
            
            <div class="modal-header" style="background: #1e3a8a; color: #fff; padding: 14px 18px; border-bottom: none;">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="color: #fff; opacity: 0.9; font-size: 24px; text-shadow: none;">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title" id="catVariantModalLabel" style="font-weight: 700; display: flex; align-items: center; gap: 8px; font-size: 18px; margin: 0; color: #fff;">
                    <i class="fa fa-cubes text-info"></i> <span id="catModalTitle">Select Variant</span>
                </h4>
                <div style="margin-top: 4px; font-size: 12px; color: #bfdbfe;">
                    Category: <strong id="catModalCategory" style="color: #fff;">-</strong>
                    <span id="catModalSupplierWrap" style="margin-left: 10px;">| Sold By: <strong id="catModalSupplier" style="color: #fff;">-</strong></span>
                    <span id="catModalBrandWrap" style="margin-left: 10px;">| Brand: <strong id="catModalBrand" style="color: #fff;">-</strong></span>
                </div>
            </div>
            
            <form method="POST" action="" id="catAddToCartForm">
                <?php $csrf->echoInputField(); ?>
                <input type="hidden" name="form_add_to_cart" value="1">
                <input type="hidden" name="p_id" id="catHiddenPId" value="">
                <input type="hidden" name="p_name" id="catHiddenPName" value="">
                <input type="hidden" name="p_current_price" id="catHiddenPrice" value="">
                <input type="hidden" name="p_featured_photo" id="catHiddenPhoto" value="">
                <input type="hidden" name="size_id" id="catHiddenSizeId" value="0">
                <input type="hidden" name="size_name" id="catHiddenSizeName" value="">
                <input type="hidden" name="color_id" id="catHiddenColorId" value="0">
                <input type="hidden" name="color_name" id="catHiddenColorName" value="">

                <div class="modal-body" style="padding: 20px;">
                    
                    <!-- Selected Variant Live Details Card -->
                    <div style="background: #f8fafc; border: 1.5px solid #e2e8f0; border-radius: 8px; padding: 14px; margin-bottom: 16px; display: flex; gap: 14px; align-items: center;">
                        <div id="catModalImg" style="width: 85px; height: 85px; min-width: 85px; border-radius: 6px; background-size: contain; background-repeat: no-repeat; background-position: center; background-color: #fff; border: 1px solid #cbd5e1;"></div>
                        <div style="flex-grow: 1;">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 8px;">
                                <h4 id="catModalSelectedName" style="margin: 0 0 4px 0; font-size: 15px; font-weight: 800; color: #0f172a; line-height: 1.3;">-</h4>
                                <span id="catModalStockBadge" class="label label-success" style="font-size: 11px; padding: 4px 8px; white-space: nowrap;">In Stock</span>
                            </div>
                            <div style="font-size: 12px; color: #64748b; margin-bottom: 6px;">
                                SKU: <strong id="catModalSku" style="color: #334155; font-family: monospace; font-size: 13px;">-</strong>
                            </div>
                            <div style="display: flex; justify-content: space-between; align-items: baseline; flex-wrap: wrap; gap: 6px;">
                                <div style="font-size: 20px; font-weight: 900; color: #e11d48;">
                                    &#8369;<span id="catModalPrice">0.00</span>
                                </div>
                                <div id="catModalSpecTags" style="display: flex; flex-wrap: wrap; gap: 4px;"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Variant Dropdown Selection -->
                    <div class="form-group" style="margin-bottom: 15px;">
                        <label style="font-size: 13px; font-weight: 700; color: #1e293b; margin-bottom: 6px; display: block;">
                            <i class="fa fa-list-ul text-primary"></i> Select Specification / Variant:
                        </label>
                        <select id="catModalSelect" class="form-control input-lg" style="height: 42px; font-size: 14px; font-weight: 600;" onchange="onCategoryVariantSelectChange(this.value)">
                        </select>
                    </div>

                    <!-- Clickable Variant Chips -->
                    <div id="catModalChipsContainer" style="margin-bottom: 16px;">
                        <label style="font-size: 12px; font-weight: 700; color: #64748b; margin-bottom: 6px; display: block; text-transform: uppercase;">
                            Quick Variant Selector:
                        </label>
                        <div id="catModalChipsList" style="display: flex; flex-wrap: wrap; gap: 6px; max-height: 120px; overflow-y: auto; padding: 2px;"></div>
                    </div>

                    <!-- Quantity & Live Subtotal -->
                    <div style="background: #f1f5f9; border-radius: 8px; padding: 14px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
                        <div>
                            <label style="font-size: 13px; font-weight: 700; color: #1e293b; margin-bottom: 6px; display: block;">Quantity:</label>
                            <div class="input-group" style="width: 140px;">
                                <span class="input-group-btn">
                                    <button type="button" class="btn btn-default" style="font-weight: bold; font-size: 16px; padding: 6px 12px;" onclick="changeCatModalQty(-1)">-</button>
                                </span>
                                <input type="number" name="p_qty" id="catModalQtyInput" class="form-control text-center" style="font-size: 16px; font-weight: 800; height: 38px;" value="1" min="1" oninput="onCatModalQtyChange()">
                                <span class="input-group-btn">
                                    <button type="button" class="btn btn-default" style="font-weight: bold; font-size: 16px; padding: 6px 12px;" onclick="changeCatModalQty(1)">+</button>
                                </span>
                            </div>
                        </div>
                        
                        <div style="text-align: right;">
                            <span style="font-size: 12px; color: #64748b; display: block; font-weight: 600;">Total Amount:</span>
                            <span style="font-size: 22px; font-weight: 900; color: #059669;">&#8369;<span id="catModalItemSubtotal">0.00</span></span>
                        </div>
                    </div>

                </div>

                <div class="modal-footer" style="background: #f8fafc; border-top: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
                    <a id="catModalViewDetailsLink" href="#" class="btn btn-default" style="font-weight: 600;">
                        <i class="fa fa-info-circle"></i> View Details
                    </a>
                    <div>
                        <button type="button" class="btn btn-default" data-dismiss="modal" style="font-weight: 600; margin-right: 6px;">Close</button>
                        <button type="submit" id="catModalAddBtn" class="btn btn-primary" style="font-weight: 800; padding: 8px 22px; font-size: 15px; background: #2563eb; border-color: #2563eb;">
                            <i class="fa fa-shopping-cart"></i> Add to Cart
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- JAVASCRIPT: FILTERING, SEARCH, SORT, AND VARIANT MODAL CONTROLLER         -->
<!-- ========================================================================= -->
<script>
const CAT_STATE = {
    tcat_id: <?php echo $tcat_param; ?>,
    mcat_id: <?php echo $mcat_param; ?>,
    ecat_id: <?php echo $ecat_param; ?>,
    cat_type: '<?php echo $cat_type; ?>',
    cat_title: '<?php echo addslashes($current_title); ?>',
    supplier_id: <?php echo $initial_supplier_id; ?>,
    supplier_name: '',
    search: '<?php echo addslashes($initial_search); ?>',
    sort: '<?php echo addslashes($initial_sort); ?>'
};

let currentCategoryGroup = null;
let currentSelectedCatVariant = null;
let searchDebounceTimer = null;

function handleCategoryProductClick(element) {
    const rawData = element.getAttribute('data-group');
    if (!rawData) return;
    try {
        const group = JSON.parse(rawData);
        if (!group || !group.variants || group.variants.length === 0) return;
        if (group.total_stock <= 0) {
            alert('This product is currently out of stock.');
            return;
        }
        openCategoryVariantModal(group);
    } catch (e) {
        console.error('Failed to parse group data', e);
    }
}

function openCategoryVariantModal(group) {
    currentCategoryGroup = group;
    
    document.getElementById('catModalTitle').innerText = group.base_name;
    document.getElementById('catModalCategory').innerText = group.ecat_name || 'General';
    document.getElementById('catModalSupplier').innerText = group.supplier_name || 'Supplier';
    document.getElementById('catModalBrand').innerText = group.brand || 'Generic';
    
    const select = document.getElementById('catModalSelect');
    const chipsList = document.getElementById('catModalChipsList');
    
    select.innerHTML = '';
    chipsList.innerHTML = '';
    
    let firstInStock = null;
    
    group.variants.forEach((v) => {
        const isOutOfStock = (v.stock <= 0);
        if (!firstInStock && !isOutOfStock) {
            firstInStock = v;
        }
        
        const opt = document.createElement('option');
        opt.value = v.id;
        opt.disabled = isOutOfStock;
        opt.innerText = `${v.spec_label} - ₱${v.price.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})} (${isOutOfStock ? 'Out of stock' : v.stock + ' in stock'})`;
        select.appendChild(opt);
        
        const chip = document.createElement('button');
        chip.type = 'button';
        chip.className = `cat-variant-chip ${isOutOfStock ? 'disabled' : ''}`;
        chip.id = `catChip_${v.id}`;
        chip.title = isOutOfStock ? 'Out of stock' : `${v.stock} in stock`;
        chip.innerHTML = `<i class="fa ${isOutOfStock ? 'fa-ban text-danger' : 'fa-check-circle'}"></i> ${escapeHtml(v.spec_label)} <span style="font-weight: 800; margin-left: 2px;">₱${v.price.toFixed(0)}</span>`;
        if (!isOutOfStock) {
            chip.onclick = function() { onCategoryVariantSelectChange(v.id); };
        }
        chipsList.appendChild(chip);
    });
    
    const selectedVariant = firstInStock || group.variants[0];
    document.getElementById('catModalQtyInput').value = 1;
    onCategoryVariantSelectChange(selectedVariant.id);
    
    $('#categoryVariantModal').modal('show');
}

function onCategoryVariantSelectChange(variantId) {
    variantId = parseInt(variantId);
    if (!currentCategoryGroup || !currentCategoryGroup.variants) return;
    
    const variant = currentCategoryGroup.variants.find(v => v.id === variantId);
    if (!variant) return;
    
    currentSelectedCatVariant = variant;
    
    // Sync Select Dropdown
    document.getElementById('catModalSelect').value = variant.id;
    
    // Sync Active Chip
    document.querySelectorAll('.cat-variant-chip').forEach(c => c.classList.remove('active'));
    const activeChip = document.getElementById(`catChip_${variant.id}`);
    if (activeChip) activeChip.classList.add('active');
    
    // Update Hidden Form Inputs
    document.getElementById('catHiddenPId').value = variant.id;
    document.getElementById('catHiddenPName').value = variant.name;
    document.getElementById('catHiddenPrice').value = variant.price;
    document.getElementById('catHiddenPhoto').value = variant.photo;
    document.getElementById('catHiddenSizeName').value = variant.size || variant.spec_label || '';
    document.getElementById('catHiddenColorName').value = variant.color || '';
    
    // Update View Details link
    document.getElementById('catModalViewDetailsLink').href = `product.php?id=${variant.id}`;
    
    // Update Preview
    document.getElementById('catModalImg').style.backgroundImage = `url('assets/uploads/${variant.photo}')`;
    document.getElementById('catModalSelectedName').innerText = variant.name;
    document.getElementById('catModalSku').innerText = variant.sku;
    document.getElementById('catModalPrice').innerText = variant.price.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    
    // Update Stock Badge & Button State
    const stockBadge = document.getElementById('catModalStockBadge');
    const addBtn = document.getElementById('catModalAddBtn');
    const qtyInput = document.getElementById('catModalQtyInput');
    
    if (variant.stock <= 0) {
        stockBadge.className = 'label label-danger';
        stockBadge.innerText = 'Out of Stock';
        addBtn.disabled = true;
        qtyInput.disabled = true;
    } else {
        stockBadge.className = (variant.stock < 10) ? 'label label-warning' : 'label label-success';
        stockBadge.innerText = `${variant.stock} in stock`;
        addBtn.disabled = false;
        qtyInput.disabled = false;
        qtyInput.max = variant.stock;
        
        let currentQty = parseInt(qtyInput.value) || 1;
        if (currentQty > variant.stock) {
            qtyInput.value = variant.stock;
        } else if (currentQty < 1) {
            qtyInput.value = 1;
        }
    }
    
    // Render Specification Tags
    const specTagsContainer = document.getElementById('catModalSpecTags');
    let tagsHtml = [];
    if (variant.size) tagsHtml.push(`<span style="color: #0369a1; font-weight: 700; background: #e0f2fe; padding: 2px 6px; border-radius: 4px; font-size: 11px;">Size: ${escapeHtml(variant.size)}</span>`);
    if (variant.thickness) tagsHtml.push(`<span style="color: #047857; font-weight: 700; background: #dcfce7; padding: 2px 6px; border-radius: 4px; font-size: 11px;">Thick: ${escapeHtml(variant.thickness)}</span>`);
    if (variant.diameter) tagsHtml.push(`<span style="color: #047857; font-weight: 700; background: #dcfce7; padding: 2px 6px; border-radius: 4px; font-size: 11px;">Dia: ${escapeHtml(variant.diameter)}</span>`);
    if (variant.color) tagsHtml.push(`<span class="cat-color-badge">${escapeHtml(variant.color)}</span>`);
    if (variant.material) tagsHtml.push(`<span style="color: #475569; font-weight: 700; background: #f1f5f9; padding: 2px 6px; border-radius: 4px; font-size: 11px;">${escapeHtml(variant.material)}</span>`);
    if (variant.voltage) tagsHtml.push(`<span style="color: #b45309; font-weight: 700; background: #fef3c7; padding: 2px 6px; border-radius: 4px; font-size: 11px;">${escapeHtml(variant.voltage)}</span>`);
    if (variant.length) tagsHtml.push(`<span style="color: #4338ca; font-weight: 700; background: #e0e7ff; padding: 2px 6px; border-radius: 4px; font-size: 11px;">Len: ${escapeHtml(variant.length)}</span>`);
    specTagsContainer.innerHTML = tagsHtml.join(' ');
    
    calcCatModalSubtotal();
}

function changeCatModalQty(delta) {
    if (!currentSelectedCatVariant) return;
    const qtyInput = document.getElementById('catModalQtyInput');
    let currentVal = parseInt(qtyInput.value) || 1;
    let newVal = currentVal + delta;
    if (newVal < 1) newVal = 1;
    if (newVal > currentSelectedCatVariant.stock) {
        alert(`Cannot exceed available inventory (${currentSelectedCatVariant.stock} units).`);
        newVal = currentSelectedCatVariant.stock;
    }
    qtyInput.value = newVal;
    calcCatModalSubtotal();
}

function onCatModalQtyChange() {
    if (!currentSelectedCatVariant) return;
    const qtyInput = document.getElementById('catModalQtyInput');
    let val = parseInt(qtyInput.value) || 1;
    if (val < 1) val = 1;
    if (val > currentSelectedCatVariant.stock) {
        alert(`Maximum available inventory is ${currentSelectedCatVariant.stock} units.`);
        val = currentSelectedCatVariant.stock;
    }
    qtyInput.value = val;
    calcCatModalSubtotal();
}

function calcCatModalSubtotal() {
    if (!currentSelectedCatVariant) return;
    const qty = parseInt(document.getElementById('catModalQtyInput').value) || 1;
    const subtotal = currentSelectedCatVariant.price * qty;
    document.getElementById('catModalItemSubtotal').innerText = subtotal.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
}

// --------------------------------------------------------------------------
// DYNAMIC FILTERING & AJAX CONTROLLER
// --------------------------------------------------------------------------
function applySupplierFilter(supplierId, supplierName) {
    CAT_STATE.supplier_id = parseInt(supplierId) || 0;
    CAT_STATE.supplier_name = supplierName || '';
    
    // Update sidebar active classes
    document.querySelectorAll('.cat-nav-link').forEach(el => {
        if (el.id.startsWith('suppLink_')) el.classList.remove('active');
    });
    const activeLink = document.getElementById(`suppLink_${CAT_STATE.supplier_id}`);
    if (activeLink) activeLink.classList.add('active');
    
    updateFilterUI();
    fetchFilteredCategoryProducts();
}

function removeSupplierFilter() {
    applySupplierFilter(0, 'All Suppliers');
}

function onCategorySearchInput(query) {
    clearTimeout(searchDebounceTimer);
    searchDebounceTimer = setTimeout(() => {
        CAT_STATE.search = query.trim();
        updateFilterUI();
        fetchFilteredCategoryProducts();
    }, 300);
}

function removeSearchFilter() {
    document.getElementById('catSearchInput').value = '';
    CAT_STATE.search = '';
    updateFilterUI();
    fetchFilteredCategoryProducts();
}

function onCategorySortChange(sortVal) {
    CAT_STATE.sort = sortVal;
    fetchFilteredCategoryProducts();
}

function clearAllFilters() {
    document.getElementById('catSearchInput').value = '';
    CAT_STATE.search = '';
    CAT_STATE.supplier_id = 0;
    CAT_STATE.supplier_name = '';
    CAT_STATE.sort = 'popularity';
    document.getElementById('catSortSelect').value = 'popularity';
    
    document.querySelectorAll('.cat-nav-link').forEach(el => {
        if (el.id.startsWith('suppLink_')) el.classList.remove('active');
    });
    const defaultSuppLink = document.getElementById('suppLink_0');
    if (defaultSuppLink) defaultSuppLink.classList.add('active');
    
    updateFilterUI();
    fetchFilteredCategoryProducts();
}

function updateFilterUI() {
    // 1. Supplier Chip
    const chipSup = document.getElementById('chipSupplier');
    const chipSupName = document.getElementById('chipSupplierName');
    if (CAT_STATE.supplier_id > 0 && CAT_STATE.supplier_name) {
        chipSupName.innerText = CAT_STATE.supplier_name;
        chipSup.style.display = 'inline-flex';
    } else {
        chipSup.style.display = 'none';
    }
    
    // 2. Search Chip
    const chipSearch = document.getElementById('chipSearch');
    const chipSearchQuery = document.getElementById('chipSearchQuery');
    if (CAT_STATE.search) {
        chipSearchQuery.innerText = CAT_STATE.search;
        chipSearch.style.display = 'inline-flex';
    } else {
        chipSearch.style.display = 'none';
    }
    
    // 3. Clear Filters Button
    const btnClear = document.getElementById('btnClearFilters');
    if (CAT_STATE.supplier_id > 0 || CAT_STATE.search) {
        btnClear.style.display = 'inline-block';
    } else {
        btnClear.style.display = 'none';
    }
}

function fetchFilteredCategoryProducts() {
    const grid = document.getElementById('catProductGrid');
    grid.style.opacity = '0.5';
    
    let apiUrl = 'api-homepage-products.php?';
    const params = [];
    
    if (CAT_STATE.ecat_id > 0) {
        params.push(`ecat_id=${CAT_STATE.ecat_id}`);
    } else if (CAT_STATE.mcat_id > 0) {
        params.push(`mcat_id=${CAT_STATE.mcat_id}`);
    } else if (CAT_STATE.tcat_id > 0) {
        params.push(`tcat_id=${CAT_STATE.tcat_id}`);
    }
    
    if (CAT_STATE.supplier_id > 0) {
        params.push(`supplier_id=${CAT_STATE.supplier_id}`);
    }
    if (CAT_STATE.search) {
        params.push(`search=${encodeURIComponent(CAT_STATE.search)}`);
    }
    if (CAT_STATE.sort) {
        params.push(`sort=${encodeURIComponent(CAT_STATE.sort)}`);
    }
    
    apiUrl += params.join('&');
    
    fetch(apiUrl)
        .then(res => res.json())
        .then(data => {
            grid.style.opacity = '1';
            if (!data.success || !data.products) {
                renderEmptyCategoryGrid();
                return;
            }
            
            // Update stats
            const totalProds = data.total_groups || data.products.length;
            const totalVars = data.total_variants || 0;
            const prodCountEl = document.getElementById('catTotalProdCount');
            const varCountEl = document.getElementById('catTotalVarCount');
            if (prodCountEl) prodCountEl.innerText = totalProds;
            if (varCountEl) varCountEl.innerText = totalVars;
            
            if (data.products.length === 0) {
                renderEmptyCategoryGrid();
            } else {
                renderCategoryProductCards(data.products);
            }
        })
        .catch(err => {
            grid.style.opacity = '1';
            console.error('Category products fetch failed', err);
        });
}

function renderEmptyCategoryGrid() {
    const grid = document.getElementById('catProductGrid');
    grid.innerHTML = `
        <div class="col-md-12 text-center" style="grid-column: 1 / -1; padding: 60px 20px; background: #fff; border-radius: 8px; border: 1px solid #e2e8f0;">
            <i class="fa fa-cubes fa-3x" style="color: #cbd5e1; margin-bottom: 12px;"></i>
            <h4 style="font-weight: 700; color: #334155; margin: 0 0 6px 0;">No Products Found</h4>
            <p class="text-muted" style="font-size: 13.5px; margin-bottom: 16px;">There are no products matching your selected filters in ${escapeHtml(CAT_STATE.cat_title)}.</p>
            <button type="button" class="btn btn-primary btn-sm" onclick="clearAllFilters()" style="background: #2563eb; border-color: #2563eb; font-weight: 700;">Reset Filters</button>
        </div>
    `;
}

function renderCategoryProductCards(products) {
    const grid = document.getElementById('catProductGrid');
    let html = '';
    
    products.forEach(grp => {
        const isOutOfStock = (grp.total_stock <= 0);
        const variantCount = grp.variants ? grp.variants.length : 1;
        const cardJson = escapeHtml(JSON.stringify(grp));
        
        let stockBadge = '';
        if (isOutOfStock) {
            stockBadge = '<span class="mkt-badge-stock label label-danger">Out of Stock</span>';
        } else if (grp.total_stock < 10) {
            stockBadge = `<span class="mkt-badge-stock label label-warning">${grp.total_stock} Left</span>`;
        } else {
            stockBadge = '<span class="mkt-badge-stock label label-success">In Stock</span>';
        }
        
        let priceText = '';
        if (grp.min_price === grp.max_price) {
            priceText = `&#8369;${grp.min_price.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
        } else {
            priceText = `&#8369;${grp.min_price.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})} - &#8369;${grp.max_price.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
        }
        
        let variantPill = '';
        if (variantCount > 1) {
            variantPill = `<div class="mkt-variant-pill"><i class="fa fa-th-list text-primary"></i> ${variantCount} Variants / Sizes</div>`;
        } else {
            const specLabel = (grp.variants && grp.variants[0]) ? grp.variants[0].spec_label : 'Standard';
            variantPill = `<div class="mkt-variant-pill"><i class="fa fa-cube text-muted"></i> ${escapeHtml(specLabel)}</div>`;
        }
        
        let addBtn = '';
        if (isOutOfStock) {
            addBtn = `<button type="button" class="mkt-btn-add disabled" disabled><i class="fa fa-ban"></i> Out of Stock</button>`;
        } else {
            const btnText = (variantCount > 1) ? '+ Add / Select Variant' : '+ Add to Cart';
            addBtn = `<button type="button" class="mkt-btn-add" onclick="handleCategoryProductClick(this)" data-group="${cardJson}"><i class="fa fa-shopping-cart"></i> ${btnText}</button>`;
        }
        
        html += `
            <div class="mkt-prod-card" data-group-key="${escapeHtml(grp.group_key)}">
                <div class="mkt-prod-thumb">
                    <span class="mkt-badge-cat">${escapeHtml(grp.ecat_name)}</span>
                    ${stockBadge}
                    <a href="javascript:void(0)" onclick="handleCategoryProductClick(this)" data-group="${cardJson}" style="display: flex; align-items: center; justify-content: center; width: 100%; height: 100%;">
                        <img src="assets/uploads/${escapeHtml(grp.photo)}" alt="${escapeHtml(grp.base_name)}" loading="lazy">
                    </a>
                </div>
                <div class="mkt-prod-body">
                    <a href="store.php?slug=${escapeHtml(grp.supplier_slug)}" class="mkt-prod-supplier-pill" title="View ${escapeHtml(grp.supplier_name)} Store">
                        <i class="fa fa-building-o"></i> ${escapeHtml(grp.supplier_name)}
                    </a>
                    <h3 class="mkt-prod-title">
                        <a href="javascript:void(0)" onclick="handleCategoryProductClick(this)" data-group="${cardJson}">
                            ${escapeHtml(grp.base_name)}
                        </a>
                    </h3>
                    ${variantPill}
                    <div class="mkt-prod-price">
                        ${priceText}
                    </div>
                    ${addBtn}
                </div>
            </div>
        `;
    });
    
    grid.innerHTML = html;
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.innerText = text;
    return div.innerHTML;
}
</script>

<?php require_once('footer.php'); ?>
