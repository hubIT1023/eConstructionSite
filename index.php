<?php require_once('header.php'); ?>

<?php
// Handle Add to Cart from Homepage Variant Selection Modal
handle_add_to_cart_submission($pdo, $error_message1, $success_message1, 0);

// Display user alerts if any
if($error_message1 != '') {
    echo "<script>alert('".addslashes($error_message1)."');</script>";
}
if($success_message1 != '') {
    echo "<script>alert('".addslashes($success_message1)."');</script>";
}
?>

<?php
// Fetch Active Suppliers for Marketplace Discovery
$stmt_suppliers = $pdo->query("SELECT s.supplier_id, s.supplier_name, s.supplier_slug, s.supplier_logo, s.supplier_description,
    (SELECT COUNT(*) FROM tbl_product WHERE supplier_id = s.supplier_id AND p_is_active = 1) as active_products
    FROM tbl_supplier s 
    WHERE s.supplier_status = 'Active' 
    ORDER BY s.supplier_name ASC");
$all_active_suppliers = $stmt_suppliers->fetchAll(PDO::FETCH_ASSOC);

// Fetch All Top & Mid Categories for Navigation & Mega Menu
$stmt_nav_cats = $pdo->query("SELECT tc.tcat_id, tc.tcat_name, mc.mcat_id, mc.mcat_name, ec.ecat_id, ec.ecat_name,
    (SELECT COUNT(*) FROM tbl_product WHERE ecat_id = ec.ecat_id AND p_is_active = 1) as prod_count
    FROM tbl_top_category tc
    LEFT JOIN tbl_mid_category mc ON tc.tcat_id = mc.tcat_id
    LEFT JOIN tbl_end_category ec ON mc.mcat_id = ec.mcat_id
    ORDER BY tc.tcat_id ASC, mc.mcat_id ASC, ec.ecat_name ASC");
$raw_nav = $stmt_nav_cats->fetchAll(PDO::FETCH_ASSOC);

$mega_categories = [];
foreach ($raw_nav as $row) {
    $t_id = $row['tcat_id'];
    $m_id = $row['mcat_id'];
    if (!isset($mega_categories[$t_id])) {
        $mega_categories[$t_id] = [
            'tcat_id' => $t_id,
            'tcat_name' => $row['tcat_name'],
            'mid_cats' => []
        ];
    }
    if ($m_id && !isset($mega_categories[$t_id]['mid_cats'][$m_id])) {
        $mega_categories[$t_id]['mid_cats'][$m_id] = [
            'mcat_id' => $m_id,
            'mcat_name' => $row['mcat_name'],
            'end_cats' => []
        ];
    }
    if (!empty($row['ecat_id'])) {
        $mega_categories[$t_id]['mid_cats'][$m_id]['end_cats'][] = [
            'ecat_id' => $row['ecat_id'],
            'ecat_name' => $row['ecat_name'],
            'prod_count' => intval($row['prod_count'])
        ];
    }
}

// Fetch All Active Products and Group by Parent Product (Supplier + ECAT + Base Name)
$stmt_all_prods = $pdo->query("SELECT p.*, ec.ecat_name, mc.mcat_name, mc.mcat_id, tc.tcat_name, tc.tcat_id, s.supplier_name, s.supplier_slug, s.supplier_id 
    FROM tbl_product p
    LEFT JOIN tbl_end_category ec ON p.ecat_id = ec.ecat_id
    LEFT JOIN tbl_mid_category mc ON ec.mcat_id = mc.mcat_id
    LEFT JOIN tbl_top_category tc ON mc.tcat_id = tc.tcat_id
    LEFT JOIN tbl_supplier s ON p.supplier_id = s.supplier_id
    WHERE p.p_is_active = 1 AND s.supplier_status = 'Active'
    ORDER BY p.p_total_view DESC, p.p_id DESC");
$all_raw_products = $stmt_all_prods->fetchAll(PDO::FETCH_ASSOC);

$all_grouped_products = [];
$deals_products = [];
$category_grouped_products = [];

foreach ($all_raw_products as $prod) {
    $clean_price = floatval(preg_replace('/[^0-9.]/', '', strval($prod['p_current_price'])));
    $clean_old_price = !empty($prod['p_old_price']) ? floatval(preg_replace('/[^0-9.]/', '', strval($prod['p_old_price']))) : 0;
    $clean_stock = intval(preg_replace('/[^0-9]/', '', strval($prod['p_qty'])));
    $img_src = (!empty($prod['p_featured_photo']) && file_exists('assets/uploads/'.$prod['p_featured_photo'])) 
        ? $prod['p_featured_photo'] 
        : 'photo-6.jpg';

    $parsed_spec = parseConstructionProductDetails($prod['p_name']);
    $base_name = get_master_product_base_name($prod['p_name'], $prod['ecat_id']);

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

    if (!isset($all_grouped_products[$group_key])) {
        $all_grouped_products[$group_key] = [
            'group_key' => $group_key,
            'base_name' => $base_name,
            'ecat_id' => intval($prod['ecat_id']),
            'ecat_name' => !empty($prod['ecat_name']) ? $prod['ecat_name'] : 'General',
            'mcat_id' => intval($prod['mcat_id']),
            'mcat_name' => !empty($prod['mcat_name']) ? $prod['mcat_name'] : '',
            'tcat_id' => intval($prod['tcat_id']),
            'tcat_name' => !empty($prod['tcat_name']) ? $prod['tcat_name'] : '',
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
            'discount_pct' => ($clean_old_price > $clean_price) ? round((($clean_old_price - $clean_price)/$clean_old_price)*100) : 0,
            'variants' => [$variant_item]
        ];
    } else {
        $all_grouped_products[$group_key]['variants'][] = $variant_item;
        $all_grouped_products[$group_key]['total_stock'] += $clean_stock;
        $all_grouped_products[$group_key]['min_price'] = min($all_grouped_products[$group_key]['min_price'], $clean_price);
        $all_grouped_products[$group_key]['max_price'] = max($all_grouped_products[$group_key]['max_price'], $clean_price);
        if ($clean_old_price > $clean_price) {
            $all_grouped_products[$group_key]['has_discount'] = true;
            $all_grouped_products[$group_key]['discount_pct'] = max($all_grouped_products[$group_key]['discount_pct'], round((($clean_old_price - $clean_price)/$clean_old_price)*100));
        }
        if ($all_grouped_products[$group_key]['photo'] === 'photo-6.jpg' && $img_src !== 'photo-6.jpg') {
            $all_grouped_products[$group_key]['photo'] = $img_src;
        }
    }
}

// Separate deals and categories
foreach ($all_grouped_products as $g) {
    if ($g['has_discount'] && $g['discount_pct'] > 0) {
        $deals_products[] = $g;
    }
    $m_name = $g['mcat_name'] ?: 'Other Building Supplies';
    if (!isset($category_grouped_products[$m_name])) {
        $category_grouped_products[$m_name] = [
            'mcat_id' => $g['mcat_id'],
            'mcat_name' => $m_name,
            'products' => []
        ];
    }
    $category_grouped_products[$m_name]['products'][] = $g;
}
?>

<!-- ========================================================================= -->
<!-- ADVANCED MARKETPLACE STYLING (Shopee/AliExpress UX + Construction Theme)  -->
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

/* Marketplace Section Card Wrappers */
.mkt-section-box {
    background: #ffffff;
    border: 1px solid var(--mkt-card-border);
    border-radius: 8px;
    padding: 20px 24px;
    margin-bottom: 24px;
    box-shadow: 0 1px 4px rgba(0,0,0,0.03);
}

.mkt-section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 18px;
    border-bottom: 2px solid #f1f5f9;
    padding-bottom: 12px;
}

.mkt-section-title {
    font-size: 19px;
    font-weight: 800;
    color: var(--mkt-primary);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 8px;
}

.mkt-section-link {
    font-size: 13px;
    font-weight: 700;
    color: var(--mkt-accent);
    text-decoration: none !important;
    display: flex;
    align-items: center;
    gap: 4px;
    transition: transform 0.2s;
}
.mkt-section-link:hover {
    transform: translateX(3px);
    color: #1d4ed8;
}

/* Prominent Search Bar & Autocomplete */
.mkt-search-wrapper {
    position: relative;
    max-width: 680px;
    width: 100%;
    margin: 0 auto;
}
.mkt-search-bar {
    display: flex;
    align-items: center;
    background: #fff;
    border: 2px solid #2563eb;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 4px 14px rgba(37,99,235,0.15);
    height: 48px;
}
.mkt-search-input {
    flex-grow: 1;
    border: none !important;
    box-shadow: none !important;
    font-size: 15px;
    padding: 10px 16px;
    height: 100%;
    color: #0f172a;
    font-weight: 500;
}
.mkt-search-btn {
    background: #2563eb;
    color: #fff;
    border: none;
    height: 100%;
    padding: 0 24px;
    font-weight: 700;
    font-size: 15px;
    display: flex;
    align-items: center;
    gap: 6px;
    cursor: pointer;
    transition: background 0.15s;
}
.mkt-search-btn:hover {
    background: #1d4ed8;
}

/* Autocomplete Floating Dropdown */
#searchSuggestionsBox {
    position: absolute;
    top: 52px;
    left: 0;
    right: 0;
    background: #fff;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    box-shadow: 0 10px 25px rgba(0,0,0,0.15);
    z-index: 10000;
    display: none;
    max-height: 420px;
    overflow-y: auto;
}
.suggestion-group-title {
    background: #f8fafc;
    padding: 8px 14px;
    font-size: 11px;
    font-weight: 800;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-top: 1px solid #e2e8f0;
    border-bottom: 1px solid #e2e8f0;
}
.suggestion-group-title:first-child { border-top: none; }
.suggestion-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 14px;
    color: #0f172a;
    text-decoration: none !important;
    border-bottom: 1px solid #f1f5f9;
    transition: background 0.15s;
}
.suggestion-item:hover {
    background: #eff6ff;
    color: #1d4ed8;
}

/* Supplier Discovery & Filter Controls */
.mkt-filter-bar {
    background: #0f172a;
    color: #fff;
    border-radius: 8px;
    padding: 14px 20px;
    margin-bottom: 24px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 14px;
    box-shadow: 0 4px 12px rgba(15,23,42,0.12);
}
.mkt-supplier-pills {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}
.supplier-pill-btn {
    background: rgba(255,255,255,0.1);
    color: #f8fafc;
    border: 1px solid rgba(255,255,255,0.2);
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 12.5px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.15s;
    text-decoration: none !important;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.supplier-pill-btn:hover {
    background: rgba(255,255,255,0.2);
    color: #fff;
    border-color: #fff;
}
.supplier-pill-btn.active {
    background: #2563eb;
    color: #fff;
    border-color: #3b82f6;
    font-weight: 800;
    box-shadow: 0 2px 8px rgba(37,99,235,0.4);
}

.active-filter-chips {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    margin-top: 10px;
    width: 100%;
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
}
.filter-chip .chip-remove:hover { color: #b91c1c; }

/* Category Mega Navigation */
.mkt-category-nav {
    background: #ffffff;
    border-bottom: 2px solid #2563eb;
    box-shadow: 0 2px 4px rgba(0,0,0,0.03);
    margin-bottom: 20px;
}
.mkt-cat-menu {
    display: flex;
    align-items: center;
    gap: 4px;
    list-style: none;
    margin: 0;
    padding: 0;
    overflow-x: auto;
    white-space: nowrap;
}
.mkt-cat-menu > li > a {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 12px 16px;
    font-size: 13.5px;
    font-weight: 700;
    color: #334155;
    text-decoration: none;
    transition: all 0.15s;
    border-bottom: 3px solid transparent;
}
.mkt-cat-menu > li > a:hover, .mkt-cat-menu > li.active > a {
    color: #2563eb;
    border-bottom-color: #2563eb;
    background: #eff6ff;
}

/* Hero Section Layout */
.hero-category-drawer {
    background: #fff;
    border: 1px solid var(--mkt-card-border);
    border-radius: 8px;
    overflow: hidden;
    height: 100%;
}
.hero-cat-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px 14px;
    color: #334155;
    font-size: 13px;
    font-weight: 600;
    text-decoration: none !important;
    border-bottom: 1px solid #f1f5f9;
    transition: all 0.15s;
}
.hero-cat-item:hover {
    background: #eff6ff;
    color: #2563eb;
    padding-left: 18px;
}

.mkt-hero-banner {
    background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 100%);
    color: #fff;
    border-radius: 8px;
    padding: 36px 32px;
    position: relative;
    overflow: hidden;
    min-height: 280px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    box-shadow: 0 6px 18px rgba(15,23,42,0.15);
}
.mkt-hero-banner::after {
    content: "";
    position: absolute;
    right: -20px;
    bottom: -20px;
    width: 260px;
    height: 260px;
    background: radial-gradient(circle, rgba(245,158,11,0.15) 0%, transparent 70%);
    pointer-events: none;
}

.mkt-quick-card {
    background: #fff;
    border: 1px solid var(--mkt-card-border);
    border-radius: 8px;
    padding: 16px;
    margin-bottom: 14px;
    display: flex;
    align-items: center;
    gap: 14px;
    box-shadow: 0 2px 6px rgba(0,0,0,0.02);
    transition: all 0.2s;
    text-decoration: none !important;
    color: inherit;
}
.mkt-quick-card:hover {
    border-color: #2563eb;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(37,99,235,0.08);
}

/* Category Grid Tiles */
.cat-tile-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
    gap: 12px;
}
.cat-tile {
    background: #f8fafc;
    border: 1.5px solid #e2e8f0;
    border-radius: 8px;
    padding: 14px 10px;
    text-align: center;
    text-decoration: none !important;
    color: #0f172a;
    transition: all 0.2s;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
}
.cat-tile:hover {
    background: #eff6ff;
    border-color: #3b82f6;
    transform: translateY(-3px);
    box-shadow: 0 4px 12px rgba(59,130,246,0.1);
}
.cat-tile-icon {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    background: #eff6ff;
    color: #2563eb;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    margin-bottom: 8px;
}

/* Standardized Marketplace Product Card (Shopee/AliExpress Density + Construction Identity) */
.product-rail {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(210px, 1fr));
    gap: 16px;
}
@media (max-width: 768px) {
    .product-rail {
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
    background-size: contain;
    background-repeat: no-repeat;
    background-position: center;
    background-color: #fff;
    border-bottom: 1px solid #f1f5f9;
}
.mkt-badge-cat {
    position: absolute;
    top: 8px;
    left: 8px;
    background: rgba(15,23,42,0.85);
    color: #fff;
    font-size: 10px;
    font-weight: 700;
    padding: 2px 7px;
    border-radius: 4px;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    max-width: 120px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.mkt-badge-discount {
    position: absolute;
    top: 8px;
    right: 8px;
    background: #e11d48;
    color: #fff;
    font-size: 11px;
    font-weight: 800;
    padding: 2px 6px;
    border-radius: 4px;
    box-shadow: 0 1px 4px rgba(225,29,72,0.3);
}

.mkt-prod-body {
    padding: 12px;
    display: flex;
    flex-direction: column;
    flex-grow: 1;
    justify-content: space-between;
}
.mkt-prod-supplier {
    font-size: 11px;
    color: #64748b;
    margin-bottom: 4px;
    display: flex;
    align-items: center;
    gap: 4px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.mkt-prod-supplier a {
    color: #475569;
    text-decoration: none;
    font-weight: 600;
}
.mkt-prod-supplier a:hover {
    color: #2563eb;
    text-decoration: underline;
}
.mkt-prod-title {
    font-size: 14px;
    font-weight: 700;
    color: #0f172a;
    line-height: 1.35;
    margin: 0 0 6px 0;
    height: 38px;
    overflow: hidden;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
}
.mkt-prod-variant-tag {
    font-size: 11px;
    font-weight: 700;
    color: #0369a1;
    background: #e0f2fe;
    display: inline-block;
    padding: 2px 6px;
    border-radius: 4px;
    margin-bottom: 8px;
}
.mkt-prod-price {
    font-size: 17px;
    font-weight: 900;
    color: #e11d48;
    margin-bottom: 8px;
    line-height: 1;
}
.mkt-prod-price-from {
    font-size: 11px;
    font-weight: normal;
    color: #64748b;
}
.mkt-prod-btn {
    background: #2563eb;
    color: #fff;
    border: none;
    border-radius: 6px;
    padding: 8px 12px;
    font-size: 13px;
    font-weight: 700;
    width: 100%;
    cursor: pointer;
    transition: background 0.15s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
}
.mkt-prod-btn:hover {
    background: #1d4ed8;
    color: #fff;
}
.mkt-prod-btn.btn-out {
    background: #e2e8f0;
    color: #94a3b8;
    cursor: not-allowed;
}

/* Supplier Store Cards */
.supplier-card-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 16px;
}
.supplier-store-card {
    background: #fff;
    border: 1.5px solid #e2e8f0;
    border-radius: 8px;
    padding: 16px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    transition: all 0.2s;
}
.supplier-store-card:hover {
    border-color: #2563eb;
    box-shadow: 0 4px 14px rgba(37,99,235,0.08);
    transform: translateY(-2px);
}

/* Variant Chips in Modal */
.home-variant-chip {
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
}
.home-variant-chip:hover {
    border-color: #2563eb;
    background: #eff6ff;
    color: #2563eb;
}
.home-variant-chip.active {
    border-color: #2563eb;
    background: #2563eb;
    color: #fff;
}
.home-variant-chip.disabled {
    opacity: 0.5;
    cursor: not-allowed;
    background: #f1f5f9;
}
</style>

<!-- ========================================================================= -->
<!-- 1. TOP UTILITY BAR                                                        -->
<!-- ========================================================================= -->
<div style="background: #0f172a; color: #94a3b8; font-size: 12px; padding: 6px 0; border-bottom: 1px solid rgba(255,255,255,0.08);">
    <div class="container" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
        <div style="display: flex; gap: 16px; align-items: center;">
            <span><i class="fa fa-phone" style="color: #f59e0b;"></i> Customer Care: <strong>(02) 8888-BUILD</strong></span>
            <span class="hidden-xs"><i class="fa fa-shield" style="color: #10b981;"></i> 100% Genuine Certified Building Materials</span>
        </div>
        <div style="display: flex; gap: 16px; align-items: center;">
            <a href="request-quote.php" style="color: #f59e0b; font-weight: 700; text-decoration: none;"><i class="fa fa-file-text-o"></i> Request Quote (RFQ)</a>
            <a href="bulk-orders.php" style="color: #94a3b8; text-decoration: none;"><i class="fa fa-truck"></i> Bulk Freight</a>
            <a href="supplier/login.php" style="color: #94a3b8; text-decoration: none;"><i class="fa fa-store"></i> Supplier Portal</a>
            <?php if(isset($_SESSION['customer'])): ?>
                <a href="dashboard.php" style="color: #60a5fa; font-weight: 700; text-decoration: none;"><i class="fa fa-user"></i> My Account</a>
            <?php else: ?>
                <a href="login.php" style="color: #94a3b8; text-decoration: none;"><i class="fa fa-sign-in"></i> Sign In</a>
                <a href="registration.php" style="color: #94a3b8; text-decoration: none;"><i class="fa fa-user-plus"></i> Register</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- 2. MAIN MARKETPLACE SEARCH HEADER                                         -->
<!-- ========================================================================= -->
<div style="background: #ffffff; padding: 16px 0; border-bottom: 1px solid #e2e8f0;">
    <div class="container">
        <div class="row" style="display: flex; align-items: center; flex-wrap: wrap; gap: 12px;">
            
            <!-- Logo -->
            <div class="col-md-3 col-sm-12" style="text-align: left;">
                <a href="index.php" style="display: inline-block; text-decoration: none;">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <div style="background: #2563eb; color: #fff; width: 42px; height: 42px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-weight: 900; font-size: 22px; box-shadow: 0 2px 6px rgba(37,99,235,0.3);">
                            <i class="fa fa-building"></i>
                        </div>
                        <div>
                            <span style="font-size: 20px; font-weight: 900; color: #0f172a; letter-spacing: -0.5px; display: block; line-height: 1;">eConstruction</span>
                            <span style="font-size: 11px; font-weight: 700; color: #f59e0b; text-transform: uppercase; letter-spacing: 1px;">Marketplace</span>
                        </div>
                    </div>
                </a>
            </div>

            <!-- Prominent Search Bar with Autocomplete -->
            <div class="col-md-6 col-sm-12">
                <div class="mkt-search-wrapper">
                    <form action="search-result.php" method="GET" class="mkt-search-bar" id="homepageSearchForm" autocomplete="off">
                        <?php $csrf->echoInputField(); ?>
                        <input type="text" name="search_text" id="mktSearchInput" class="form-control mkt-search-input" placeholder="Search cement, deformed rebars, tubular steel, angle bars, paints..." required>
                        <button type="submit" class="mkt-search-btn">
                            <i class="fa fa-search"></i> <span class="hidden-xs">Search</span>
                        </button>
                    </form>

                    <!-- Floating Suggestions Dropdown -->
                    <div id="searchSuggestionsBox"></div>
                </div>
            </div>

            <!-- Cart & Quick Account CTA -->
            <div class="col-md-3 col-sm-12 text-right" style="display: flex; justify-content: flex-end; align-items: center; gap: 14px;">
                <a href="cart.php" class="btn btn-default" style="border: 1.5px solid #cbd5e1; border-radius: 8px; padding: 8px 16px; font-weight: 700; display: flex; align-items: center; gap: 8px; color: #0f172a;">
                    <i class="fa fa-shopping-cart text-primary" style="font-size: 16px;"></i>
                    <span>Cart: <strong style="color: #e11d48;">&#8369;<?php 
                        $cart_total = 0;
                        if(isset($_SESSION['cart_p_id'])) {
                            for($i=1;$i<=count($_SESSION['cart_p_id']);$i++) {
                                $c_qty = isset($_SESSION['cart_p_qty'][$i]) ? $_SESSION['cart_p_qty'][$i] : 1;
                                $c_prc = isset($_SESSION['cart_p_current_price'][$i]) ? $_SESSION['cart_p_current_price'][$i] : 0;
                                $cart_total += ($c_qty * $c_prc);
                            }
                        }
                        echo number_format($cart_total, 2);
                    ?></strong></span>
                </a>
            </div>

        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- 3. CATEGORY NAVIGATION BAR (Direct Links to Categories)                    -->
<!-- ========================================================================= -->
<div class="mkt-category-nav hidden-xs">
    <div class="container">
        <ul class="mkt-cat-menu">
            <li class="active"><a href="index.php"><i class="fa fa-home"></i> All Categories</a></li>
            <li><a href="product-category.php?id=1&type=mid-category"><i class="fa fa-cubes"></i> Steel & Metal</a></li>
            <li><a href="product-category.php?id=2&type=mid-category"><i class="fa fa-industry"></i> Concrete & Cement</a></li>
            <li><a href="product-category.php?id=3&type=mid-category"><i class="fa fa-home"></i> Roofing & Wall</a></li>
            <li><a href="product-category.php?id=5&type=mid-category"><i class="fa fa-wrench"></i> Plumbing & Pipes</a></li>
            <li><a href="product-category.php?id=6&type=mid-category"><i class="fa fa-bolt"></i> Power Tools</a></li>
            <li><a href="product-category.php?id=20&type=end-category"><i class="fa fa-paint-brush"></i> Paints</a></li>
            <li><a href="product-category.php?id=21&type=end-category"><i class="fa fa-square"></i> Plywood & Hardiflex</a></li>
            <li><a href="suppliers.php"><i class="fa fa-store"></i> All Suppliers</a></li>
        </ul>
    </div>
</div>

<div class="container" style="margin-top: 15px;">

    <!-- ========================================================================= -->
    <!-- 4. HERO SECTION & PROMOTIONAL DISCOVERY                                   -->
    <!-- ========================================================================= -->
    <div class="row" style="margin-bottom: 24px;">
        
        <!-- Left: Category Quick Drawer (Desktop) -->
        <div class="col-md-3 hidden-sm hidden-xs">
            <div class="hero-category-drawer">
                <div style="background: #0f172a; color: #fff; padding: 12px 16px; font-weight: 800; font-size: 13px; display: flex; align-items: center; gap: 8px;">
                    <i class="fa fa-bars text-warning"></i> Major Categories
                </div>
                <div style="max-height: 310px; overflow-y: auto;">
                    <a href="product-category.php?id=1&type=mid-category" class="hero-cat-item">
                        <span><i class="fa fa-cube text-primary" style="margin-right: 6px;"></i> Steel & Metal</span>
                        <i class="fa fa-chevron-right text-muted" style="font-size: 10px;"></i>
                    </a>
                    <a href="product-category.php?id=2&type=mid-category" class="hero-cat-item">
                        <span><i class="fa fa-industry text-primary" style="margin-right: 6px;"></i> Concrete & Cement</span>
                        <i class="fa fa-chevron-right text-muted" style="font-size: 10px;"></i>
                    </a>
                    <a href="product-category.php?id=3&type=mid-category" class="hero-cat-item">
                        <span><i class="fa fa-shield text-primary" style="margin-right: 6px;"></i> Roofing & Wall</span>
                        <i class="fa fa-chevron-right text-muted" style="font-size: 10px;"></i>
                    </a>
                    <a href="product-category.php?id=5&type=mid-category" class="hero-cat-item">
                        <span><i class="fa fa-wrench text-primary" style="margin-right: 6px;"></i> Plumbing & Pipes</span>
                        <i class="fa fa-chevron-right text-muted" style="font-size: 10px;"></i>
                    </a>
                    <a href="product-category.php?id=6&type=mid-category" class="hero-cat-item">
                        <span><i class="fa fa-bolt text-primary" style="margin-right: 6px;"></i> Power Tools</span>
                        <i class="fa fa-chevron-right text-muted" style="font-size: 10px;"></i>
                    </a>
                    <a href="product-category.php?id=21&type=end-category" class="hero-cat-item">
                        <span><i class="fa fa-th-large text-primary" style="margin-right: 6px;"></i> Plywood & Boards</span>
                        <i class="fa fa-chevron-right text-muted" style="font-size: 10px;"></i>
                    </a>
                    <a href="product-category.php?id=20&type=end-category" class="hero-cat-item">
                        <span><i class="fa fa-paint-brush text-primary" style="margin-right: 6px;"></i> Paints & Coatings</span>
                        <i class="fa fa-chevron-right text-muted" style="font-size: 10px;"></i>
                    </a>
                </div>
            </div>
        </div>

        <!-- Center: Hero Promotional Banner -->
        <div class="col-md-6 col-sm-8">
            <div class="mkt-hero-banner">
                <span class="label label-warning" style="background-color: #f59e0b; width: fit-content; font-size: 11px; margin-bottom: 12px; font-weight: 800;">
                    ONLINE CONSTRUCTION SUPPLIES MARKETPLACE
                </span>
                <h1 style="font-size: 28px; font-weight: 900; color: #fff; margin: 0 0 10px 0; line-height: 1.2;">
                    Everything You Need to Build. <span style="color: #60a5fa;">Direct from Verified Suppliers.</span>
                </h1>
                <p style="font-size: 14px; color: #cbd5e1; margin-bottom: 20px; line-height: 1.5; max-width: 480px;">
                    Source structural steel, Portland cement, Schedule 40 pipes, marine plywood, and industrial power tools with instant variant selection and competitive wholesale pricing.
                </p>
                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                    <a href="#discovery-section" class="btn btn-primary btn-sm" style="background: #2563eb; border-color: #2563eb; font-weight: 800; padding: 8px 18px;">
                        <i class="fa fa-shopping-bag"></i> Browse Products
                    </a>
                    <a href="request-quote.php" class="btn btn-default btn-sm" style="font-weight: 700; padding: 8px 16px;">
                        <i class="fa fa-file-text-o"></i> Request Custom Quote
                    </a>
                </div>
            </div>
        </div>

        <!-- Right: Quick Action Cards -->
        <div class="col-md-3 col-sm-4">
            <a href="request-quote.php" class="mkt-quick-card">
                <div style="background: #fef3c7; color: #d97706; width: 44px; height: 44px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 20px; flex-shrink: 0;">
                    <i class="fa fa-file-text-o"></i>
                </div>
                <div>
                    <h5 style="margin: 0 0 2px 0; font-weight: 800; color: #0f172a; font-size: 13.5px;">B2B RFQ Bidding</h5>
                    <p style="margin: 0; font-size: 11.5px; color: #64748b;">Get wholesale quotes from multiple regional suppliers.</p>
                </div>
            </a>

            <a href="bulk-orders.php" class="mkt-quick-card">
                <div style="background: #e0f2fe; color: #0284c7; width: 44px; height: 44px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 20px; flex-shrink: 0;">
                    <i class="fa fa-truck"></i>
                </div>
                <div>
                    <h5 style="margin: 0 0 2px 0; font-weight: 800; color: #0f172a; font-size: 13.5px;">Bulk Freight Shipping</h5>
                    <p style="margin: 0; font-size: 11.5px; color: #64748b;">Flatbed, boom truck, and aggregate site delivery.</p>
                </div>
            </a>

            <a href="supplier/login.php" class="mkt-quick-card" style="margin-bottom: 0;">
                <div style="background: #dcfce7; color: #16a34a; width: 44px; height: 44px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 20px; flex-shrink: 0;">
                    <i class="fa fa-store"></i>
                </div>
                <div>
                    <h5 style="margin: 0 0 2px 0; font-weight: 800; color: #0f172a; font-size: 13.5px;">Supplier Portal</h5>
                    <p style="margin: 0; font-size: 11.5px; color: #64748b;">Manage products, POS sales, and approvals.</p>
                </div>
            </a>
        </div>

    </div>

    <!-- ========================================================================= -->
    <!-- 5. SHOP BY CATEGORY TILES                                                 -->
    <!-- ========================================================================= -->
    <div class="mkt-section-box">
        <div class="mkt-section-header">
            <h3 class="mkt-section-title">
                <i class="fa fa-th-large text-primary"></i> Shop By Material Category
            </h3>
            <a href="product-category.php?id=1&type=top-category" class="mkt-section-link">
                View All Categories <i class="fa fa-arrow-right"></i>
            </a>
        </div>
        
        <div class="cat-tile-grid">
            <a href="javascript:void(0)" class="cat-tile" onclick="applyCategoryFilter(1, 'Steel & Metal')">
                <div class="cat-tile-icon"><i class="fa fa-cubes"></i></div>
                <strong style="font-size: 12.5px;">Steel & Metal</strong>
                <span style="font-size: 11px; color: #64748b;">97 Items</span>
            </a>
            <a href="javascript:void(0)" class="cat-tile" onclick="applyCategoryFilter(2, 'Concrete & Cement')">
                <div class="cat-tile-icon"><i class="fa fa-industry"></i></div>
                <strong style="font-size: 12.5px;">Cement</strong>
                <span style="font-size: 11px; color: #64748b;">2 Items</span>
            </a>
            <a href="javascript:void(0)" class="cat-tile" onclick="applyCategoryFilter(3, 'Roofing & Wall')">
                <div class="cat-tile-icon"><i class="fa fa-home"></i></div>
                <strong style="font-size: 12.5px;">Roof & Wall</strong>
                <span style="font-size: 11px; color: #64748b;">15 Items</span>
            </a>
            <a href="javascript:void(0)" class="cat-tile" onclick="applyCategoryFilter(5, 'Plumbing & Pipes')">
                <div class="cat-tile-icon"><i class="fa fa-wrench"></i></div>
                <strong style="font-size: 12.5px;">Plumbing</strong>
                <span style="font-size: 11px; color: #64748b;">1 Item</span>
            </a>
            <a href="javascript:void(0)" class="cat-tile" onclick="applyCategoryFilter(6, 'Power Tools')">
                <div class="cat-tile-icon"><i class="fa fa-bolt"></i></div>
                <strong style="font-size: 12.5px;">Power Tools</strong>
                <span style="font-size: 11px; color: #64748b;">1 Item</span>
            </a>
            <a href="javascript:void(0)" class="cat-tile" onclick="applyCategoryFilter(0, 'Nails & Fasteners', 14)">
                <div class="cat-tile-icon"><i class="fa fa-crosshairs"></i></div>
                <strong style="font-size: 12.5px;">Nails</strong>
                <span style="font-size: 11px; color: #64748b;">35 Items</span>
            </a>
            <a href="javascript:void(0)" class="cat-tile" onclick="applyCategoryFilter(0, 'Plywood & Boards', 21)">
                <div class="cat-tile-icon"><i class="fa fa-square"></i></div>
                <strong style="font-size: 12.5px;">Plywood</strong>
                <span style="font-size: 11px; color: #64748b;">13 Items</span>
            </a>
            <a href="javascript:void(0)" class="cat-tile" onclick="applyCategoryFilter(0, 'Paints', 20)">
                <div class="cat-tile-icon"><i class="fa fa-paint-brush"></i></div>
                <strong style="font-size: 12.5px;">Paints</strong>
                <span style="font-size: 11px; color: #64748b;">2 Items</span>
            </a>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- 6. MARKETPLACE DISCOVERY BAR & SUPPLIER FILTER CONTROLS                   -->
    <!-- ========================================================================= -->
    <div id="discovery-section" class="mkt-filter-bar">
        <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
            <div style="font-size: 14px; font-weight: 800; color: #f59e0b; display: flex; align-items: center; gap: 6px;">
                <i class="fa fa-filter"></i> Filter by Supplier:
            </div>

            <!-- Supplier Pills -->
            <div class="mkt-supplier-pills">
                <button type="button" class="supplier-pill-btn active" id="pill_supplier_0" onclick="filterBySupplier(0, 'All Suppliers')">
                    <i class="fa fa-globe"></i> All Suppliers
                </button>
                <?php foreach ($all_active_suppliers as $s): ?>
                    <button type="button" class="supplier-pill-btn" id="pill_supplier_<?php echo $s['supplier_id']; ?>" onclick="filterBySupplier(<?php echo $s['supplier_id']; ?>, '<?php echo htmlspecialchars(addslashes($s['supplier_name'])); ?>')">
                        <i class="fa fa-store"></i> <?php echo htmlspecialchars($s['supplier_name']); ?> 
                        <span style="opacity: 0.7; font-size: 11px;">(<?php echo $s['active_products']; ?>)</span>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Sorting Selector -->
        <div style="display: flex; align-items: center; gap: 8px;">
            <label style="font-size: 12.5px; margin: 0; color: #cbd5e1; font-weight: normal;">Sort by:</label>
            <select id="mktSortSelect" class="form-control input-sm" style="background: #1e293b; color: #fff; border: 1px solid #475569; width: 140px; font-weight: 600;" onchange="applySorting(this.value)">
                <option value="popularity">Popularity</option>
                <option value="newest">Newest Arrival</option>
                <option value="price_low">Price: Low to High</option>
                <option value="price_high">Price: High to Low</option>
            </select>
        </div>

        <!-- Active Filter Indicator Badges -->
        <div class="active-filter-chips" id="activeFilterChipsContainer" style="display: none;">
            <span style="font-size: 11.5px; color: #94a3b8; font-weight: 600;">Active Filters:</span>
            <span id="activeSupplierChip" style="display: none;"></span>
            <span id="activeCategoryChip" style="display: none;"></span>
            <span id="activeSearchChip" style="display: none;"></span>
            <a href="javascript:void(0)" onclick="clearAllFilters()" style="color: #f87171; font-size: 11.5px; font-weight: 700; margin-left: 6px; text-decoration: underline;">Clear All</a>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- 7. SPECIAL / FLASH DEALS (Promotional Pricing)                            -->
    <!-- ========================================================================= -->
    <?php if (count($deals_products) > 0): ?>
    <div id="section_special_deals" class="mkt-section-box" style="border-left: 4px solid #e11d48;">
        <div class="mkt-section-header">
            <h3 class="mkt-section-title" style="color: #e11d48;">
                <i class="fa fa-bolt" style="color: #e11d48;"></i> Special Deals & Promotional Offers
            </h3>
            <span class="label label-danger" style="font-size: 11px; padding: 4px 8px;">Limited Time Wholesale Deals</span>
        </div>
        
        <div class="product-rail">
            <?php foreach (array_slice($deals_products, 0, 4) as $prod): ?>
                <?php echo renderMarketplaceProductCard($prod); ?>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- ========================================================================= -->
    <!-- 8. FEATURED BUILDING MATERIALS                                            -->
    <!-- ========================================================================= -->
    <div id="section_featured_products" class="mkt-section-box">
        <div class="mkt-section-header">
            <h3 class="mkt-section-title">
                <i class="fa fa-star text-warning"></i> Featured Building Materials
            </h3>
            <span class="text-muted" style="font-size: 13px;">
                Verified Quality & Direct Mill Delivery
            </span>
        </div>

        <div class="product-rail" id="featuredProductsContainer">
            <?php foreach (array_slice($all_grouped_products, 0, 8) as $prod): ?>
                <?php echo renderMarketplaceProductCard($prod); ?>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- 9. PRODUCTS BY CATEGORY RAILS                                             -->
    <!-- ========================================================================= -->
    <?php foreach ($category_grouped_products as $cat_title => $cat_group): ?>
    <div class="mkt-section-box category-product-section" data-mcat-id="<?php echo $cat_group['mcat_id']; ?>">
        <div class="mkt-section-header">
            <h3 class="mkt-section-title">
                <i class="fa fa-cube text-primary"></i> Popular in <?php echo htmlspecialchars($cat_title); ?>
            </h3>
            <a href="product-category.php?id=<?php echo $cat_group['mcat_id']; ?>&type=mid-category" class="mkt-section-link">
                View All <?php echo htmlspecialchars($cat_title); ?> <i class="fa fa-arrow-right"></i>
            </a>
        </div>

        <div class="product-rail">
            <?php foreach (array_slice($cat_group['products'], 0, 4) as $prod): ?>
                <?php echo renderMarketplaceProductCard($prod); ?>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>

    <!-- ========================================================================= -->
    <!-- 10. FEATURED SUPPLIERS / STOREFRONTS                                      -->
    <!-- ========================================================================= -->
    <div class="mkt-section-box">
        <div class="mkt-section-header">
            <h3 class="mkt-section-title">
                <i class="fa fa-store text-success"></i> Featured Material Suppliers
            </h3>
            <a href="suppliers.php" class="mkt-section-link">
                View All Suppliers <i class="fa fa-arrow-right"></i>
            </a>
        </div>

        <div class="supplier-card-grid">
            <?php foreach ($all_active_suppliers as $s): ?>
                <div class="supplier-store-card">
                    <div style="display: flex; gap: 14px; align-items: center; margin-bottom: 12px;">
                        <div style="width: 55px; height: 55px; border-radius: 50%; background: #eff6ff; color: #2563eb; display: flex; align-items: center; justify-content: center; font-size: 22px; flex-shrink: 0; border: 1.5px solid #bfdbfe;">
                            <?php if (!empty($s['supplier_logo']) && file_exists('assets/uploads/'.$s['supplier_logo'])): ?>
                                <img src="assets/uploads/<?php echo $s['supplier_logo']; ?>" alt="" style="width: 100%; height: 100%; object-fit: contain; border-radius: 50%;">
                            <?php else: ?>
                                <i class="fa fa-industry"></i>
                            <?php endif; ?>
                        </div>
                        <div>
                            <h4 style="margin: 0 0 4px 0; font-size: 15px; font-weight: 800; color: #0f172a;">
                                <?php echo htmlspecialchars($s['supplier_name']); ?>
                            </h4>
                            <span class="label label-success" style="font-size: 10px; background-color: #059669;">Verified Supplier</span>
                        </div>
                    </div>
                    
                    <p style="font-size: 12px; color: #64748b; line-height: 1.4; height: 34px; overflow: hidden; margin-bottom: 14px;">
                        <?php echo htmlspecialchars($s['supplier_description'] ?: 'Leading distributor of construction supplies, structural metals, and hardware.'); ?>
                    </p>

                    <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid #f1f5f9; padding-top: 10px;">
                        <span style="font-size: 12px; font-weight: 700; color: #0369a1;">
                            <strong><?php echo $s['active_products']; ?></strong> Products Listed
                        </span>
                        <a href="store.php?slug=<?php echo $s['supplier_slug']; ?>" class="btn btn-default btn-xs" style="font-weight: 700; color: #2563eb; border-color: #bfdbfe;">
                            Visit Storefront <i class="fa fa-chevron-right"></i>
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- 11. RECENTLY VIEWED MATERIALS                                             -->
    <!-- ========================================================================= -->
    <div id="section_recently_viewed" class="mkt-section-box" style="display: none;">
        <div class="mkt-section-header">
            <h3 class="mkt-section-title">
                <i class="fa fa-history text-muted"></i> Recently Viewed Materials
            </h3>
            <button type="button" class="btn btn-default btn-xs" onclick="clearRecentlyViewed()" style="font-weight: 600;">Clear History</button>
        </div>
        <div class="product-rail" id="recentlyViewedContainer"></div>
    </div>

</div>

<!-- ========================================================================= -->
<!-- 12. STANDARDIZED HOMEPAGE VARIANT SELECTION MODAL                         -->
<!-- ========================================================================= -->
<div class="modal fade" id="homepageVariantModal" tabindex="-1" role="dialog" aria-labelledby="homeVariantModalLabel" aria-hidden="true" style="z-index: 10050;">
    <div class="modal-dialog" role="document" style="max-width: 540px; margin-top: 60px;">
        <div class="modal-content" style="border-radius: 10px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.3); border: none;">
            
            <div class="modal-header" style="background: #0f172a; color: #fff; padding: 14px 18px; border-bottom: none;">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="color: #fff; opacity: 0.9; font-size: 24px; text-shadow: none;">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title" id="homeVariantModalLabel" style="font-weight: 800; display: flex; align-items: center; gap: 8px; font-size: 18px; margin: 0; color: #fff;">
                    <i class="fa fa-cubes text-warning"></i> <span id="homeModalTitle">Select Variant</span>
                </h4>
                <div style="margin-top: 4px; font-size: 12px; color: #94a3b8;">
                    Category: <strong id="homeModalCategory" style="color: #fff;">-</strong>
                    <span style="margin-left: 10px;">| Supplier: <strong id="homeModalSupplier" style="color: #60a5fa;">-</strong></span>
                </div>
            </div>
            
            <form method="POST" action="" id="homeAddToCartForm">
                <?php $csrf->echoInputField(); ?>
                <input type="hidden" name="form_add_to_cart" value="1">
                <input type="hidden" name="p_id" id="homeHiddenPId" value="">
                <input type="hidden" name="p_name" id="homeHiddenPName" value="">
                <input type="hidden" name="p_current_price" id="homeHiddenPrice" value="">
                <input type="hidden" name="p_featured_photo" id="homeHiddenPhoto" value="">
                <input type="hidden" name="size_id" id="homeHiddenSizeId" value="0">
                <input type="hidden" name="size_name" id="homeHiddenSizeName" value="">
                <input type="hidden" name="color_id" id="homeHiddenColorId" value="0">
                <input type="hidden" name="color_name" id="homeHiddenColorName" value="">

                <div class="modal-body" style="padding: 20px;">
                    
                    <!-- Selected Variant Details Preview -->
                    <div style="background: #f8fafc; border: 1.5px solid #e2e8f0; border-radius: 8px; padding: 14px; margin-bottom: 16px; display: flex; gap: 14px; align-items: center;">
                        <div id="homeModalImg" style="width: 85px; height: 85px; min-width: 85px; border-radius: 6px; background-size: contain; background-repeat: no-repeat; background-position: center; background-color: #fff; border: 1px solid #cbd5e1;"></div>
                        <div style="flex-grow: 1;">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 8px;">
                                <h4 id="homeModalSelectedName" style="margin: 0 0 4px 0; font-size: 15px; font-weight: 800; color: #0f172a; line-height: 1.3;">-</h4>
                                <span id="homeModalStockBadge" class="label label-success" style="font-size: 11px; padding: 4px 8px; white-space: nowrap;">In Stock</span>
                            </div>
                            <div style="font-size: 12px; color: #64748b; margin-bottom: 6px;">
                                SKU: <strong id="homeModalSku" style="color: #334155; font-family: monospace; font-size: 13px;">-</strong>
                            </div>
                            <div style="display: flex; justify-content: space-between; align-items: baseline; flex-wrap: wrap; gap: 6px;">
                                <div style="font-size: 20px; font-weight: 900; color: #e11d48;">
                                    &#8369;<span id="homeModalPrice">0.00</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Variant Select Dropdown -->
                    <div class="form-group" style="margin-bottom: 15px;">
                        <label style="font-size: 13px; font-weight: 700; color: #1e293b; margin-bottom: 6px; display: block;">
                            <i class="fa fa-list-ul text-primary"></i> Select Specification / Variant:
                        </label>
                        <select id="homeModalSelect" class="form-control input-lg" style="height: 42px; font-size: 14px; font-weight: 600;" onchange="onHomeVariantSelectChange(this.value)">
                        </select>
                    </div>

                    <!-- Clickable Variant Chips -->
                    <div id="homeModalChipsContainer" style="margin-bottom: 16px;">
                        <label style="font-size: 12px; font-weight: 700; color: #64748b; margin-bottom: 6px; display: block; text-transform: uppercase;">
                            Quick Variant Selector:
                        </label>
                        <div id="homeModalChipsList" style="display: flex; flex-wrap: wrap; gap: 6px; max-height: 120px; overflow-y: auto; padding: 2px;"></div>
                    </div>

                    <!-- Quantity & Live Subtotal -->
                    <div style="background: #f1f5f9; border-radius: 8px; padding: 14px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
                        <div>
                            <label style="font-size: 13px; font-weight: 700; color: #1e293b; margin-bottom: 6px; display: block;">Quantity:</label>
                            <div class="input-group" style="width: 140px;">
                                <span class="input-group-btn">
                                    <button type="button" class="btn btn-default" style="font-weight: bold; font-size: 16px; padding: 6px 12px;" onclick="changeHomeModalQty(-1)">-</button>
                                </span>
                                <input type="number" name="p_qty" id="homeModalQtyInput" class="form-control text-center" style="font-size: 16px; font-weight: 800; height: 38px;" value="1" min="1" oninput="onHomeModalQtyChange()">
                                <span class="input-group-btn">
                                    <button type="button" class="btn btn-default" style="font-weight: bold; font-size: 16px; padding: 6px 12px;" onclick="changeHomeModalQty(1)">+</button>
                                </span>
                            </div>
                        </div>
                        
                        <div style="text-align: right;">
                            <span style="font-size: 12px; color: #64748b; display: block; font-weight: 600;">Total Amount:</span>
                            <span style="font-size: 22px; font-weight: 900; color: #059669;">&#8369;<span id="homeModalItemSubtotal">0.00</span></span>
                        </div>
                    </div>

                </div>

                <div class="modal-footer" style="background: #f8fafc; border-top: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
                    <a id="homeModalViewDetailsLink" href="#" class="btn btn-default" style="font-weight: 600;">
                        <i class="fa fa-info-circle"></i> View Details
                    </a>
                    <div>
                        <button type="button" class="btn btn-default" data-dismiss="modal" style="font-weight: 600; margin-right: 6px;">Close</button>
                        <button type="submit" id="homeModalAddBtn" class="btn btn-primary" style="font-weight: 800; padding: 8px 22px; font-size: 15px; background: #2563eb; border-color: #2563eb;">
                            <i class="fa fa-shopping-cart"></i> Add to Cart
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- 13. CLIENT-SIDE MARKETPLACE SCRIPTS & INTERACTIONS                         -->
<!-- ========================================================================= -->
<script>
let currentActiveSupplierId = 0;
let currentActiveEcatId = 0;
let currentActiveMcatId = 0;
let currentActiveSort = 'popularity';
let currentActiveSearch = '';

let currentHomeGroup = null;
let currentSelectedHomeVariant = null;

// Product Card Click Handler
function handleHomeProductClick(element) {
    const rawData = element.getAttribute('data-group');
    if (!rawData) return;
    try {
        const group = JSON.parse(rawData);
        if (!group || !group.variants || group.variants.length === 0) return;
        if (group.total_stock <= 0) {
            alert('This product is currently out of stock.');
            return;
        }
        recordRecentlyViewed(group);
        openHomeVariantModal(group);
    } catch (e) {
        console.error('Failed to parse group data', e);
    }
}

function openHomeVariantModal(group) {
    currentHomeGroup = group;
    
    document.getElementById('homeModalTitle').innerText = group.base_name;
    document.getElementById('homeModalCategory').innerText = group.ecat_name || 'General';
    document.getElementById('homeModalSupplier').innerText = group.supplier_name || 'Supplier';
    
    const select = document.getElementById('homeModalSelect');
    const chipsList = document.getElementById('homeModalChipsList');
    
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
        chip.className = `home-variant-chip ${isOutOfStock ? 'disabled' : ''}`;
        chip.id = `homeChip_${v.id}`;
        chip.title = isOutOfStock ? 'Out of stock' : `${v.stock} in stock`;
        chip.innerHTML = `<i class="fa ${isOutOfStock ? 'fa-ban text-danger' : 'fa-check-circle'}"></i> ${escapeHtml(v.spec_label)} <span style="font-weight: 800; margin-left: 2px;">₱${v.price.toFixed(0)}</span>`;
        if (!isOutOfStock) {
            chip.onclick = function() { onHomeVariantSelectChange(v.id); };
        }
        chipsList.appendChild(chip);
    });
    
    const selectedVariant = firstInStock || group.variants[0];
    document.getElementById('homeModalQtyInput').value = 1;
    onHomeVariantSelectChange(selectedVariant.id);
    
    $('#homepageVariantModal').modal('show');
}

function onHomeVariantSelectChange(variantId) {
    variantId = parseInt(variantId);
    if (!currentHomeGroup || !currentHomeGroup.variants) return;
    
    const variant = currentHomeGroup.variants.find(v => v.id === variantId);
    if (!variant) return;
    
    currentSelectedHomeVariant = variant;
    
    // Sync Select Dropdown
    document.getElementById('homeModalSelect').value = variant.id;
    
    // Sync Active Chip
    document.querySelectorAll('.home-variant-chip').forEach(c => c.classList.remove('active'));
    const activeChip = document.getElementById(`homeChip_${variant.id}`);
    if (activeChip) activeChip.classList.add('active');
    
    // Update Hidden Form Inputs
    document.getElementById('homeHiddenPId').value = variant.id;
    document.getElementById('homeHiddenPName').value = variant.name;
    document.getElementById('homeHiddenPrice').value = variant.price;
    document.getElementById('homeHiddenPhoto').value = variant.photo;
    document.getElementById('homeHiddenSizeName').value = variant.size || variant.spec_label || '';
    document.getElementById('homeHiddenColorName').value = variant.color || '';
    
    // Update View Details link
    document.getElementById('homeModalViewDetailsLink').href = `product.php?id=${variant.id}`;
    
    // Update Preview
    document.getElementById('homeModalImg').style.backgroundImage = `url('assets/uploads/${variant.photo}')`;
    document.getElementById('homeModalSelectedName').innerText = variant.name;
    document.getElementById('homeModalSku').innerText = variant.sku;
    document.getElementById('homeModalPrice').innerText = variant.price.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    
    // Update Stock Badge & Button State
    const stockBadge = document.getElementById('homeModalStockBadge');
    const addBtn = document.getElementById('homeModalAddBtn');
    const qtyInput = document.getElementById('homeModalQtyInput');
    
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
    }
    
    onHomeModalQtyChange();
}

function changeHomeModalQty(delta) {
    const qtyInput = document.getElementById('homeModalQtyInput');
    let currentVal = parseInt(qtyInput.value) || 1;
    let newVal = currentVal + delta;
    if (newVal < 1) newVal = 1;
    if (currentSelectedHomeVariant && currentSelectedHomeVariant.stock > 0 && newVal > currentSelectedHomeVariant.stock) {
        newVal = currentSelectedHomeVariant.stock;
    }
    qtyInput.value = newVal;
    onHomeModalQtyChange();
}

function onHomeModalQtyChange() {
    if (!currentSelectedHomeVariant) return;
    const qtyInput = document.getElementById('homeModalQtyInput');
    let qty = parseInt(qtyInput.value) || 1;
    if (qty < 1) qty = 1;
    if (currentSelectedHomeVariant.stock > 0 && qty > currentSelectedHomeVariant.stock) {
        qty = currentSelectedHomeVariant.stock;
        qtyInput.value = qty;
    }
    const total = qty * currentSelectedHomeVariant.price;
    document.getElementById('homeModalItemSubtotal').innerText = total.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
}

function escapeHtml(text) {
    if (!text) return '';
    return text.toString().replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
}

// Supplier & Category Filtering
function filterBySupplier(supplierId, supplierName) {
    currentActiveSupplierId = parseInt(supplierId);
    
    // Update Pills
    document.querySelectorAll('.supplier-pill-btn').forEach(btn => btn.classList.remove('active'));
    const activeBtn = document.getElementById(`pill_supplier_${supplierId}`);
    if (activeBtn) activeBtn.classList.add('active');
    
    updateFilterChips();
    executeFilterRequest();
}

function applyCategoryFilter(mcatId, catName, ecatId = 0) {
    currentActiveMcatId = parseInt(mcatId);
    currentActiveEcatId = parseInt(ecatId);
    updateFilterChips(catName);
    executeFilterRequest();
    
    // Scroll smoothly to discovery section
    document.getElementById('discovery-section').scrollIntoView({ behavior: 'smooth' });
}

function applySorting(sortVal) {
    currentActiveSort = sortVal;
    executeFilterRequest();
}

function updateFilterChips(customCatName = '') {
    const container = document.getElementById('activeFilterChipsContainer');
    const suppChip = document.getElementById('activeSupplierChip');
    const catChip = document.getElementById('activeCategoryChip');
    
    let hasActive = false;
    
    if (currentActiveSupplierId > 0) {
        const activeBtn = document.getElementById(`pill_supplier_${currentActiveSupplierId}`);
        const suppName = activeBtn ? activeBtn.innerText.replace(/\(\d+\)/, '').trim() : 'Supplier';
        suppChip.innerHTML = `<span class="filter-chip">Supplier: ${suppName} <span class="chip-remove" onclick="filterBySupplier(0, 'All Suppliers')">&times;</span></span>`;
        suppChip.style.display = 'inline';
        hasActive = true;
    } else {
        suppChip.style.display = 'none';
    }
    
    if (currentActiveMcatId > 0 || currentActiveEcatId > 0 || customCatName !== '') {
        const name = customCatName || 'Category Filter';
        catChip.innerHTML = `<span class="filter-chip">Category: ${name} <span class="chip-remove" onclick="clearCategoryFilter()">&times;</span></span>`;
        catChip.style.display = 'inline';
        hasActive = true;
    } else {
        catChip.style.display = 'none';
    }
    
    container.style.display = hasActive ? 'flex' : 'none';
}

function clearCategoryFilter() {
    currentActiveMcatId = 0;
    currentActiveEcatId = 0;
    updateFilterChips();
    executeFilterRequest();
}

function clearAllFilters() {
    currentActiveSupplierId = 0;
    currentActiveMcatId = 0;
    currentActiveEcatId = 0;
    currentActiveSearch = '';
    
    document.querySelectorAll('.supplier-pill-btn').forEach(btn => btn.classList.remove('active'));
    document.getElementById('pill_supplier_0').classList.add('active');
    
    updateFilterChips();
    executeFilterRequest();
}

function executeFilterRequest() {
    const featuredContainer = document.getElementById('featuredProductsContainer');
    if (!featuredContainer) return;
    
    // Show skeleton / loading
    featuredContainer.innerHTML = '<div class="col-md-12 text-center" style="padding: 40px;"><i class="fa fa-spinner fa-spin fa-2x text-primary"></i><p style="margin-top: 10px; color: #64748b;">Loading matching products...</p></div>';
    
    const params = new URLSearchParams({
        supplier_id: currentActiveSupplierId,
        mcat_id: currentActiveMcatId,
        ecat_id: currentActiveEcatId,
        sort: currentActiveSort,
        search: currentActiveSearch
    });
    
    fetch(`api-homepage-products.php?${params.toString()}`)
        .then(res => res.json())
        .then(data => {
            if (data.success && data.products) {
                renderFilteredProducts(data.products);
            }
        })
        .catch(err => {
            console.error('Filter fetch error', err);
        });
}

function renderFilteredProducts(products) {
    const featuredContainer = document.getElementById('featuredProductsContainer');
    if (!featuredContainer) return;
    
    if (products.length === 0) {
        featuredContainer.innerHTML = '<div class="col-md-12 text-center" style="padding: 40px; background: #fff; border-radius: 8px;"><i class="fa fa-cubes fa-3x text-muted"></i><h4 style="font-weight: bold; margin-top: 12px; color: #0f172a;">No products found</h4><p style="color: #64748b;">Try adjusting your supplier or category filter.</p><button type="button" class="btn btn-default btn-sm" onclick="clearAllFilters()">Clear Filters</button></div>';
        return;
    }
    
    let html = '';
    products.forEach(p => {
        const isOutOfStock = (p.total_stock <= 0);
        const variantCount = p.variants ? p.variants.length : 1;
        const priceStr = (p.min_price === p.max_price) ? `₱${p.min_price.toFixed(2)}` : `₱${p.min_price.toFixed(2)} - ₱${p.max_price.toFixed(2)}`;
        const groupJson = escapeHtml(JSON.stringify(p));
        
        html += `
        <div class="mkt-prod-card">
            <div class="mkt-prod-thumb" style="background-image: url('assets/uploads/${p.photo}');">
                <span class="mkt-badge-cat">${escapeHtml(p.ecat_name)}</span>
                ${isOutOfStock ? '<span class="label label-danger" style="position: absolute; top: 8px; right: 8px;">Out of Stock</span>' : ''}
            </div>
            <div class="mkt-prod-body">
                <div>
                    <div class="mkt-prod-supplier">
                        <i class="fa fa-store text-muted"></i> <a href="store.php?slug=${p.supplier_slug}">${escapeHtml(p.supplier_name)}</a>
                    </div>
                    <h4 class="mkt-prod-title" title="${escapeHtml(p.base_name)}">
                        <a href="product.php?id=${p.primary_id}" style="color: inherit; text-decoration: none;">${escapeHtml(p.base_name)}</a>
                    </h4>
                    ${variantCount > 1 ? `<span class="mkt-prod-variant-tag"><i class="fa fa-tags"></i> ${variantCount} Variants Available</span>` : '<span style="font-size: 11px; color: #64748b; margin-bottom: 8px; display: block;">Standard Size</span>'}
                </div>
                <div>
                    <div class="mkt-prod-price">
                        <span class="mkt-prod-price-from">From </span>${priceStr}
                    </div>
                    ${isOutOfStock ? `
                        <button type="button" class="mkt-prod-btn btn-out" disabled><i class="fa fa-ban"></i> Out of Stock</button>
                    ` : `
                        <button type="button" class="mkt-prod-btn" onclick="handleHomeProductClick(this)" data-group="${groupJson}">
                            <i class="fa fa-shopping-cart"></i> ${variantCount > 1 ? '+ Add / Select' : '+ Add to Cart'}
                        </button>
                    `}
                </div>
            </div>
        </div>
        `;
    });
    
    featuredContainer.innerHTML = html;
}

// Live Search Autocomplete Box
const searchInput = document.getElementById('mktSearchInput');
const suggestionsBox = document.getElementById('searchSuggestionsBox');

if (searchInput && suggestionsBox) {
    let debounceTimer = null;
    
    searchInput.addEventListener('input', function() {
        clearTimeout(debounceTimer);
        const query = this.value.trim();
        if (query.length < 2) {
            suggestionsBox.style.display = 'none';
            return;
        }
        
        debounceTimer = setTimeout(() => {
            fetch(`search-suggestions.php?q=${encodeURIComponent(query)}`)
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        renderSuggestions(data);
                    }
                })
                .catch(err => console.error('Suggestions error', err));
        }, 200);
    });
    
    document.addEventListener('click', function(e) {
        if (!searchInput.contains(e.target) && !suggestionsBox.contains(e.target)) {
            suggestionsBox.style.display = 'none';
        }
    });
}

function renderSuggestions(data) {
    const box = document.getElementById('searchSuggestionsBox');
    if (!box) return;
    
    let html = '';
    let totalItems = 0;
    
    if (data.products && data.products.length > 0) {
        totalItems += data.products.length;
        html += '<div class="suggestion-group-title"><i class="fa fa-cubes text-primary"></i> Matching Products</div>';
        data.products.forEach(p => {
            html += `
            <a href="${p.url}" class="suggestion-item">
                <div style="width: 36px; height: 36px; background-image: url('${p.photo}'); background-size: contain; background-repeat: no-repeat; background-position: center; border: 1px solid #e2e8f0; border-radius: 4px; flex-shrink: 0; background-color: #fff;"></div>
                <div style="flex-grow: 1;">
                    <div style="font-weight: 700; font-size: 13px;">${escapeHtml(p.name)}</div>
                    <div style="font-size: 11px; color: #64748b;">${escapeHtml(p.category)} | <span style="color: #e11d48; font-weight: bold;">₱${p.price}</span></div>
                </div>
            </a>
            `;
        });
    }
    
    if (data.categories && data.categories.length > 0) {
        totalItems += data.categories.length;
        html += '<div class="suggestion-group-title"><i class="fa fa-th-large text-primary"></i> Categories</div>';
        data.categories.forEach(c => {
            html += `
            <a href="${c.url}" class="suggestion-item">
                <i class="fa fa-folder-open text-warning" style="font-size: 16px;"></i>
                <div style="flex-grow: 1;">
                    <div style="font-weight: 700; font-size: 13px;">${escapeHtml(c.name)}</div>
                    <div style="font-size: 11px; color: #64748b;">${escapeHtml(c.type)}</div>
                </div>
            </a>
            `;
        });
    }
    
    if (data.suppliers && data.suppliers.length > 0) {
        totalItems += data.suppliers.length;
        html += '<div class="suggestion-group-title"><i class="fa fa-store text-success"></i> Suppliers / Stores</div>';
        data.suppliers.forEach(s => {
            html += `
            <a href="${s.url}" class="suggestion-item">
                <i class="fa fa-industry text-success" style="font-size: 16px;"></i>
                <div style="flex-grow: 1;">
                    <div style="font-weight: 700; font-size: 13px;">${escapeHtml(s.name)}</div>
                    <div style="font-size: 11px; color: #64748b;">${s.prod_count} Products Listed</div>
                </div>
            </a>
            `;
        });
    }
    
    if (totalItems === 0) {
        box.style.display = 'none';
        return;
    }
    
    box.innerHTML = html;
    box.style.display = 'block';
}

// Recently Viewed Management (LocalStorage)
function recordRecentlyViewed(group) {
    try {
        let history = JSON.parse(localStorage.getItem('econst_recent_viewed') || '[]');
        history = history.filter(item => item.group_key !== group.group_key);
        history.unshift({
            group_key: group.group_key,
            base_name: group.base_name,
            ecat_name: group.ecat_name,
            photo: group.photo,
            min_price: group.min_price,
            max_price: group.max_price,
            supplier_name: group.supplier_name,
            supplier_slug: group.supplier_slug,
            primary_id: group.primary_id,
            total_stock: group.total_stock,
            variants: group.variants
        });
        if (history.length > 6) history = history.slice(0, 6);
        localStorage.setItem('econst_recent_viewed', JSON.stringify(history));
        renderRecentlyViewedSection();
    } catch(e) {
        console.error('Recently viewed storage error', e);
    }
}

function renderRecentlyViewedSection() {
    const sec = document.getElementById('section_recently_viewed');
    const container = document.getElementById('recentlyViewedContainer');
    if (!sec || !container) return;
    
    try {
        const history = JSON.parse(localStorage.getItem('econst_recent_viewed') || '[]');
        if (history.length === 0) {
            sec.style.display = 'none';
            return;
        }
        
        let html = '';
        history.forEach(p => {
            const isOutOfStock = (p.total_stock <= 0);
            const variantCount = p.variants ? p.variants.length : 1;
            const priceStr = (p.min_price === p.max_price) ? `₱${p.min_price.toFixed(2)}` : `₱${p.min_price.toFixed(2)} - ₱${p.max_price.toFixed(2)}`;
            const groupJson = escapeHtml(JSON.stringify(p));
            
            html += `
            <div class="mkt-prod-card">
                <div class="mkt-prod-thumb" style="background-image: url('assets/uploads/${p.photo}');">
                    <span class="mkt-badge-cat">${escapeHtml(p.ecat_name)}</span>
                </div>
                <div class="mkt-prod-body">
                    <div>
                        <div class="mkt-prod-supplier">
                            <i class="fa fa-store text-muted"></i> ${escapeHtml(p.supplier_name)}
                        </div>
                        <h4 class="mkt-prod-title" title="${escapeHtml(p.base_name)}">
                            <a href="product.php?id=${p.primary_id}" style="color: inherit; text-decoration: none;">${escapeHtml(p.base_name)}</a>
                        </h4>
                    </div>
                    <div>
                        <div class="mkt-prod-price">
                            <span class="mkt-prod-price-from">From </span>${priceStr}
                        </div>
                        <button type="button" class="mkt-prod-btn" onclick="handleHomeProductClick(this)" data-group="${groupJson}">
                            <i class="fa fa-shopping-cart"></i> ${variantCount > 1 ? '+ Add / Select' : '+ Add to Cart'}
                        </button>
                    </div>
                </div>
            </div>
            `;
        });
        
        container.innerHTML = html;
        sec.style.display = 'block';
    } catch(e) {
        sec.style.display = 'none';
    }
}

function clearRecentlyViewed() {
    localStorage.removeItem('econst_recent_viewed');
    renderRecentlyViewedSection();
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    renderRecentlyViewedSection();
});
</script>

<?php
// Helper Function: Render Standardized Marketplace Product Card
function renderMarketplaceProductCard($group) {
    $is_out_of_stock = ($group['total_stock'] <= 0);
    $variant_count = count($group['variants']);
    $price_str = ($group['min_price'] == $group['max_price']) 
        ? '&#8369;' . number_format($group['min_price'], 2) 
        : '&#8369;' . number_format($group['min_price'], 2) . ' - &#8369;' . number_format($group['max_price'], 2);
    
    $group_json = htmlspecialchars(json_encode($group), ENT_QUOTES, 'UTF-8');
    
    ob_start();
    ?>
    <div class="mkt-prod-card" data-supplier-id="<?php echo $group['supplier_id']; ?>" data-ecat-id="<?php echo $group['ecat_id']; ?>" data-mcat-id="<?php echo $group['mcat_id']; ?>">
        <div class="mkt-prod-thumb" style="background-image: url('assets/uploads/<?php echo $group['photo']; ?>');">
            <span class="mkt-badge-cat"><?php echo htmlspecialchars($group['ecat_name']); ?></span>
            <?php if (!empty($group['has_discount']) && $group['discount_pct'] > 0): ?>
                <span class="mkt-badge-discount">-<?php echo $group['discount_pct']; ?>% OFF</span>
            <?php endif; ?>
            <?php if ($is_out_of_stock): ?>
                <span class="label label-danger" style="position: absolute; bottom: 8px; right: 8px; font-size: 10px;">Out of Stock</span>
            <?php endif; ?>
        </div>
        <div class="mkt-prod-body">
            <div>
                <div class="mkt-prod-supplier">
                    <i class="fa fa-store text-muted"></i> 
                    <a href="store.php?slug=<?php echo $group['supplier_slug']; ?>" title="Visit <?php echo htmlspecialchars($group['supplier_name']); ?> Storefront">
                        <?php echo htmlspecialchars($group['supplier_name']); ?>
                    </a>
                </div>
                <h4 class="mkt-prod-title" title="<?php echo htmlspecialchars($group['base_name']); ?>">
                    <a href="product.php?id=<?php echo $group['primary_id']; ?>" style="color: inherit; text-decoration: none;">
                        <?php echo htmlspecialchars($group['base_name']); ?>
                    </a>
                </h4>
                <?php if ($variant_count > 1): ?>
                    <span class="mkt-prod-variant-tag"><i class="fa fa-tags"></i> <?php echo $variant_count; ?> Variants Available</span>
                <?php else: ?>
                    <span style="font-size: 11px; color: #64748b; margin-bottom: 8px; display: block;">Standard Size</span>
                <?php endif; ?>
            </div>
            <div>
                <div class="mkt-prod-price">
                    <span class="mkt-prod-price-from">From </span><?php echo $price_str; ?>
                </div>
                <?php if ($is_out_of_stock): ?>
                    <button type="button" class="mkt-prod-btn btn-out" disabled><i class="fa fa-ban"></i> Out of Stock</button>
                <?php else: ?>
                    <button type="button" class="mkt-prod-btn" onclick="handleHomeProductClick(this)" data-group='<?php echo $group_json; ?>'>
                        <i class="fa fa-shopping-cart"></i> <?php echo ($variant_count > 1) ? '+ Add / Select' : '+ Add to Cart'; ?>
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
?>

<?php require_once('footer.php'); ?>
