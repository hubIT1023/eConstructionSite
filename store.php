<?php
require_once('header.php');

// 1. DYNAMIC SERVER-SIDE SUPPLIER RESOLUTION
if (!isset($_GET['slug']) || empty(trim($_GET['slug']))) {
    header('location: suppliers.php');
    exit;
}

$slug = trim($_GET['slug']);
$statement = $pdo->prepare("SELECT * FROM tbl_supplier WHERE supplier_slug = ? AND supplier_status = 'Active'");
$statement->execute([$slug]);
$supplier = $statement->fetch(PDO::FETCH_ASSOC);

if (!$supplier) {
    header('location: suppliers.php');
    exit;
}

$supplier_id = intval($supplier['supplier_id']);
$supplier_name = $supplier['supplier_name'];
$supplier_slug = $supplier['supplier_slug'];

// Handle Add to Cart from Variant Selection Modal
handle_add_to_cart_submission($pdo, $error_message1, $success_message1, $supplier_id);

// Display user alerts if any
if(!empty($error_message1)) {
    echo "<script>alert('".addslashes($error_message1)."');</script>";
}
if(!empty($success_message1)) {
    echo "<script>alert('".addslashes($success_message1)."');</script>";
}

// Banner & Logo Assets
$store_banner = (!empty($supplier['supplier_banner']) && file_exists('assets/uploads/' . $supplier['supplier_banner'])) 
    ? 'assets/uploads/' . $supplier['supplier_banner'] 
    : 'assets/uploads/about-banner.jpg';

$store_logo = (!empty($supplier['supplier_logo']) && file_exists('assets/uploads/' . $supplier['supplier_logo'])) 
    ? 'assets/uploads/' . $supplier['supplier_logo'] 
    : '';

// 2. QUERY SUPPLIER-SCOPED CATEGORIES (For Sidebar Navigation)
$stmt_cats = $pdo->prepare("SELECT mc.mcat_id, mc.mcat_name, ec.ecat_id, ec.ecat_name, COUNT(p.p_id) as prod_count
    FROM tbl_product p
    JOIN tbl_end_category ec ON p.ecat_id = ec.ecat_id
    JOIN tbl_mid_category mc ON ec.mcat_id = mc.mcat_id
    WHERE p.supplier_id = ? AND p.p_is_active = 1
    GROUP BY mc.mcat_id, mc.mcat_name, ec.ecat_id, ec.ecat_name
    ORDER BY mc.mcat_name ASC, ec.ecat_name ASC");
$stmt_cats->execute([$supplier_id]);
$raw_supplier_cats = $stmt_cats->fetchAll(PDO::FETCH_ASSOC);

$structured_categories = [];
$total_supplier_variants = 0;
foreach ($raw_supplier_cats as $rcat) {
    $m_id = $rcat['mcat_id'];
    $m_name = $rcat['mcat_name'];
    if (!isset($structured_categories[$m_id])) {
        $structured_categories[$m_id] = [
            'mcat_id' => $m_id,
            'mcat_name' => $m_name,
            'total_count' => 0,
            'end_cats' => []
        ];
    }
    $structured_categories[$m_id]['total_count'] += intval($rcat['prod_count']);
    $structured_categories[$m_id]['end_cats'][] = [
        'ecat_id' => intval($rcat['ecat_id']),
        'ecat_name' => $rcat['ecat_name'],
        'count' => intval($rcat['prod_count'])
    ];
    $total_supplier_variants += intval($rcat['prod_count']);
}

// 3. QUERY ALL ACTIVE PRODUCTS BELONGING TO THIS SUPPLIER (Server-Side Initial Render)
$stmt_prod = $pdo->prepare("SELECT p.*, ec.ecat_name, mc.mcat_id, mc.mcat_name, tc.tcat_id, tc.tcat_name
    FROM tbl_product p
    LEFT JOIN tbl_end_category ec ON p.ecat_id = ec.ecat_id
    LEFT JOIN tbl_mid_category mc ON ec.mcat_id = mc.mcat_id
    LEFT JOIN tbl_top_category tc ON mc.tcat_id = tc.tcat_id
    WHERE p.supplier_id = ? AND p.p_is_active = 1
    ORDER BY p.p_total_view DESC, p.p_id DESC");
$stmt_prod->execute([$supplier_id]);
$supplier_products_raw = $stmt_prod->fetchAll(PDO::FETCH_ASSOC);

// 4. GROUP INTO PARENT PRODUCTS ("One Parent Product = One Product Card")
$supplier_grouped_products = [];
foreach ($supplier_products_raw as $prod) {
    $clean_price = floatval(preg_replace('/[^0-9.]/', '', strval($prod['p_current_price'])));
    $clean_old_price = !empty($prod['p_old_price']) ? floatval(preg_replace('/[^0-9.]/', '', strval($prod['p_old_price']))) : 0;
    $clean_stock = intval(preg_replace('/[^0-9]/', '', strval($prod['p_qty'])));
    $img_src = (!empty($prod['p_featured_photo']) && file_exists('assets/uploads/' . $prod['p_featured_photo'])) 
        ? $prod['p_featured_photo'] 
        : 'photo-6.jpg';

    $parsed_spec = parseConstructionProductDetails($prod['p_name']);
    $base_name = get_master_product_base_name($prod['p_name'], $prod['ecat_id']);

    // Grouping Key
    $group_key = $supplier_id . '_' . $prod['ecat_id'] . '_' . strtolower(preg_replace('/[^a-z0-9]/', '', $base_name));

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
        'supplier_id' => $supplier_id,
        'supplier_name' => $supplier_name,
        'supplier_slug' => $supplier_slug
    ];

    if (!isset($supplier_grouped_products[$group_key])) {
        $supplier_grouped_products[$group_key] = [
            'group_key' => $group_key,
            'base_name' => $base_name,
            'ecat_id' => intval($prod['ecat_id']),
            'ecat_name' => !empty($prod['ecat_name']) ? $prod['ecat_name'] : 'General',
            'mcat_id' => intval($prod['mcat_id']),
            'mcat_name' => !empty($prod['mcat_name']) ? $prod['mcat_name'] : '',
            'tcat_id' => intval($prod['tcat_id']),
            'tcat_name' => !empty($prod['tcat_name']) ? $prod['tcat_name'] : '',
            'brand' => !empty($prod['p_brand']) ? $prod['p_brand'] : 'Generic',
            'supplier_id' => $supplier_id,
            'supplier_name' => $supplier_name,
            'supplier_slug' => $supplier_slug,
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
        $supplier_grouped_products[$group_key]['variants'][] = $variant_item;
        $supplier_grouped_products[$group_key]['total_stock'] += $clean_stock;
        $supplier_grouped_products[$group_key]['min_price'] = min($supplier_grouped_products[$group_key]['min_price'], $clean_price);
        $supplier_grouped_products[$group_key]['max_price'] = max($supplier_grouped_products[$group_key]['max_price'], $clean_price);
        if ($clean_old_price > $clean_price) {
            $supplier_grouped_products[$group_key]['has_discount'] = true;
            $supplier_grouped_products[$group_key]['discount_pct'] = max($supplier_grouped_products[$group_key]['discount_pct'], round((($clean_old_price - $clean_price) / $clean_old_price) * 100));
        }
        if ($supplier_grouped_products[$group_key]['photo'] === 'photo-6.jpg' && $img_src !== 'photo-6.jpg') {
            $supplier_grouped_products[$group_key]['photo'] = $img_src;
        }
    }
}
$supplier_grouped_list = array_values($supplier_grouped_products);
?>

<!-- ========================================================================= -->
<!-- STANDARDIZED SUPPLIER STORE STYLING (Shopee/AliExpress UX + Industrial)   -->
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

/* Breadcrumb Bar */
.store-breadcrumb-bar {
    background: #ffffff;
    border-bottom: 1px solid var(--mkt-card-border);
    padding: 10px 0;
    margin-bottom: 15px;
}
.store-breadcrumb {
    margin: 0;
    padding: 0;
    list-style: none;
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
}
.store-breadcrumb li a {
    color: #2563eb;
    text-decoration: none;
    font-weight: 600;
}
.store-breadcrumb li a:hover {
    text-decoration: underline;
}
.store-breadcrumb li.active {
    color: #64748b;
    font-weight: 600;
}

/* Storefront Hero Banner */
.store-hero-header {
    background: linear-gradient(135deg, rgba(15,23,42,0.92) 0%, rgba(30,58,138,0.92) 100%), url('<?php echo $store_banner; ?>');
    background-size: cover;
    background-position: center;
    color: #fff;
    border-radius: 8px;
    padding: 24px 28px;
    margin-bottom: 20px;
    box-shadow: 0 4px 16px rgba(15,23,42,0.12);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 20px;
}

.store-profile-main {
    display: flex;
    align-items: center;
    gap: 20px;
}
.store-logo-box {
    width: 84px;
    height: 84px;
    border-radius: 10px;
    background: #ffffff;
    border: 3px solid #3b82f6;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    flex-shrink: 0;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}
.store-logo-box img {
    max-width: 90%;
    max-height: 90%;
    object-fit: contain;
}

.store-header-title {
    font-size: 24px;
    font-weight: 900;
    margin: 0 0 6px 0;
    letter-spacing: -0.3px;
    color: #ffffff;
}
.store-badge-verified {
    background: #059669;
    color: #fff;
    font-size: 11px;
    font-weight: 700;
    padding: 3px 8px;
    border-radius: 4px;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.store-meta-line {
    font-size: 13px;
    color: #cbd5e1;
    margin-top: 6px;
    display: flex;
    align-items: center;
    gap: 14px;
    flex-wrap: wrap;
}

/* Store Navigation & Locked Filter Bar */
.store-filter-bar {
    background: #0f172a;
    border-radius: 8px;
    padding: 14px 18px;
    margin-bottom: 20px;
    box-shadow: 0 2px 8px rgba(15,23,42,0.1);
}
.store-search-box {
    position: relative;
    max-width: 440px;
    width: 100%;
}
.store-search-input {
    width: 100%;
    height: 38px;
    padding: 6px 36px 6px 14px;
    font-size: 13.5px;
    border: 1.5px solid #334155;
    background: #1e293b;
    color: #fff;
    border-radius: 6px;
    outline: none;
    transition: all 0.2s;
}
.store-search-input:focus {
    border-color: #2563eb;
    background: #0f172a;
    box-shadow: 0 0 0 3px rgba(37,99,235,0.25);
}
.store-search-btn {
    position: absolute;
    right: 4px;
    top: 4px;
    height: 30px;
    width: 32px;
    background: transparent;
    border: none;
    color: #94a3b8;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
}
.store-search-btn:hover {
    color: #38bdf8;
}

/* Autocomplete Suggestion Box */
.store-search-suggestions {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: #ffffff;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    box-shadow: 0 10px 25px rgba(0,0,0,0.15);
    margin-top: 6px;
    z-index: 1000;
    max-height: 400px;
    overflow-y: auto;
}
.store-sugg-header {
    font-size: 11px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #64748b;
    padding: 8px 14px 4px;
    background: #f8fafc;
    border-bottom: 1px solid #f1f5f9;
}
.store-sugg-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 8px 14px;
    color: #1e293b;
    text-decoration: none !important;
    border-bottom: 1px solid #f8fafc;
    transition: background 0.15s;
    cursor: pointer;
}
.store-sugg-item:hover {
    background: #eff6ff;
    color: #2563eb;
}
.store-sugg-img {
    width: 38px;
    height: 38px;
    border-radius: 4px;
    object-fit: contain;
    background: #fff;
    border: 1px solid #e2e8f0;
    flex-shrink: 0;
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

/* Sidebar Styling */
.store-sidebar-card {
    background: #ffffff;
    border: 1px solid var(--mkt-card-border);
    border-radius: 8px;
    padding: 16px;
    margin-bottom: 20px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.03);
}
.store-sidebar-title {
    font-size: 15px;
    font-weight: 800;
    color: var(--mkt-primary);
    margin: 0 0 12px 0;
    padding-bottom: 8px;
    border-bottom: 2px solid #f1f5f9;
    display: flex;
    align-items: center;
    gap: 6px;
}
.store-category-list {
    list-style: none;
    margin: 0;
    padding: 0;
}
.store-category-item {
    margin-bottom: 4px;
}
.store-cat-link {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 10px;
    border-radius: 6px;
    color: #334155;
    font-size: 13px;
    font-weight: 600;
    text-decoration: none !important;
    transition: all 0.15s;
}
.store-cat-link:hover, .store-cat-link.active {
    background: #eff6ff;
    color: #2563eb;
    font-weight: 700;
    padding-left: 14px;
}
.store-cat-link .badge-count {
    background: #f1f5f9;
    color: #64748b;
    font-size: 11px;
    padding: 2px 7px;
    border-radius: 10px;
    font-weight: 700;
}
.store-cat-link.active .badge-count {
    background: #2563eb;
    color: #fff;
}

/* Category Quick Filter Pills Bar (For Mobile & Fast Filtering) */
.store-quick-pills {
    display: flex;
    gap: 6px;
    overflow-x: auto;
    padding-bottom: 6px;
    margin-bottom: 16px;
    white-space: nowrap;
}
.store-pill-btn {
    background: #fff;
    color: #334155;
    border: 1.5px solid #cbd5e1;
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
.store-pill-btn:hover {
    background: #eff6ff;
    color: #2563eb;
    border-color: #93c5fd;
}
.store-pill-btn.active {
    background: #2563eb;
    color: #fff;
    border-color: #2563eb;
    font-weight: 800;
}

/* Standardized Marketplace Product Card ("One Parent Product = One Product Card") */
.store-product-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 16px;
}
@media (max-width: 768px) {
    .store-product-grid {
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
.mkt-prod-body {
    padding: 12px;
    display: flex;
    flex-direction: column;
    flex-grow: 1;
    justify-content: space-between;
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

/* Variant Modal Chips */
.store-variant-chip {
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
.store-variant-chip:hover {
    border-color: #2563eb;
    background: #eff6ff;
    color: #2563eb;
}
.store-variant-chip.active {
    border-color: #2563eb;
    background: #2563eb;
    color: #fff;
}
.store-variant-chip.disabled {
    opacity: 0.5;
    cursor: not-allowed;
}
</style>

<!-- Breadcrumb Navigation -->
<div class="store-breadcrumb-bar">
    <div class="container">
        <ul class="store-breadcrumb">
            <li><a href="index.php"><i class="fa fa-home"></i> Home</a></li>
            <li><i class="fa fa-angle-right text-muted"></i></li>
            <li><a href="suppliers.php">Suppliers</a></li>
            <li><i class="fa fa-angle-right text-muted"></i></li>
            <li class="active"><?php echo htmlspecialchars($supplier_name); ?></li>
        </ul>
    </div>
</div>

<div class="container" style="margin-bottom: 40px;">

    <!-- ========================================================================= -->
    <!-- 1. SUPPLIER STOREFRONT HERO BANNER & PROFILE HEADER                       -->
    <!-- ========================================================================= -->
    <div class="store-hero-header">
        <div class="store-profile-main">
            <div class="store-logo-box">
                <?php if ($store_logo): ?>
                    <img src="<?php echo $store_logo; ?>" alt="<?php echo htmlspecialchars($supplier_name); ?>">
                <?php else: ?>
                    <i class="fa fa-industry fa-3x" style="color: #1e3a8a;"></i>
                <?php endif; ?>
            </div>
            <div>
                <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                    <h1 class="store-header-title"><?php echo htmlspecialchars($supplier_name); ?></h1>
                    <span class="store-badge-verified"><i class="fa fa-check-circle"></i> Verified Supplier</span>
                </div>
                <div class="store-meta-line">
                    <span><i class="fa fa-cubes text-warning"></i> <strong><?php echo $total_supplier_variants; ?></strong> Products Listed</span>
                    <?php if (!empty($supplier['supplier_address'])): ?>
                        <span><i class="fa fa-map-marker text-danger"></i> <?php echo htmlspecialchars($supplier['supplier_address']); ?></span>
                    <?php endif; ?>
                    <?php if (!empty($supplier['supplier_phone'])): ?>
                        <span><i class="fa fa-phone text-success"></i> <?php echo htmlspecialchars($supplier['supplier_phone']); ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div>
            <a href="request-quote.php?supplier_id=<?php echo $supplier_id; ?>" class="btn btn-warning" style="background-color: #f59e0b; border-color: #f59e0b; font-weight: 800; padding: 10px 20px; font-size: 14px; box-shadow: 0 4px 12px rgba(245,158,11,0.3);">
                <i class="fa fa-file-text-o"></i> Request Custom Quote (RFQ)
            </a>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- 2. STORE SEARCH, SORT & LOCKED ACTIVE FILTERS BAR                         -->
    <!-- ========================================================================= -->
    <div class="store-filter-bar">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px;">
            
            <!-- Supplier Scoped Search Box -->
            <div class="store-search-box">
                <input type="text" id="storeSearchInput" class="store-search-input" placeholder="Search products from <?php echo htmlspecialchars($supplier_name); ?>..." autocomplete="off">
                <button type="button" class="store-search-btn" onclick="applyStoreSearch()">
                    <i class="fa fa-search"></i>
                </button>
                <div id="storeSearchSuggestionsBox" class="store-search-suggestions" style="display: none;"></div>
            </div>

            <!-- Sorting Selector -->
            <div style="display: flex; align-items: center; gap: 8px;">
                <label style="font-size: 13px; margin: 0; color: #cbd5e1; font-weight: normal;">Sort by:</label>
                <select id="storeSortSelect" class="form-control input-sm" style="background: #1e293b; color: #fff; border: 1px solid #475569; width: 160px; font-weight: 600;" onchange="applyStoreSorting(this.value)">
                    <option value="popularity">Popularity</option>
                    <option value="newest">Newest Arrival</option>
                    <option value="price_low">Price: Low to High</option>
                    <option value="price_high">Price: High to Low</option>
                </select>
            </div>
        </div>

        <!-- LOCKED Active Filters Bar -->
        <div class="active-filter-chips" id="storeActiveFiltersContainer">
            <span style="font-size: 12px; color: #94a3b8; font-weight: 600;">Active Scope & Filters:</span>
            
            <!-- Mandatory Locked Supplier Chip (Non-Removable) -->
            <span class="filter-chip-locked" id="lockedSupplierChip" title="Products permanently scoped to this supplier">
                <i class="fa fa-lock text-warning"></i> Supplier: <?php echo htmlspecialchars($supplier_name); ?>
            </span>

            <!-- Removable Customer Filter Badges -->
            <span id="storeActiveCategoryChip" style="display: none;"></span>
            <span id="storeActiveSearchChip" style="display: none;"></span>

            <a href="javascript:void(0)" id="storeClearFiltersBtn" onclick="clearStoreFilters()" style="color: #f87171; font-size: 12px; font-weight: 700; margin-left: 6px; text-decoration: underline; display: none;">
                Clear Filters
            </a>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- 3. STORE MAIN BODY (Sidebar Categories + Standard Product Rail)           -->
    <!-- ========================================================================= -->
    <div class="row">
        
        <!-- Left Sidebar: Supplier Info & Scoped Categories -->
        <div class="col-md-3 col-sm-4">
            
            <!-- Category Navigation Filter -->
            <div class="store-sidebar-card">
                <h4 class="store-sidebar-title">
                    <i class="fa fa-th-list text-primary"></i> Supplier Categories
                </h4>
                
                <ul class="store-category-list">
                    <li class="store-category-item">
                        <a href="javascript:void(0)" class="store-cat-link active" id="storeCat_all" onclick="applyStoreCategoryFilter(0, 'All Products', 0)">
                            <span><i class="fa fa-angle-right"></i> All Products</span>
                            <span class="badge-count"><?php echo count($supplier_grouped_list); ?></span>
                        </a>
                    </li>
                    <?php foreach ($structured_categories as $mcat): ?>
                        <li class="store-category-item">
                            <a href="javascript:void(0)" class="store-cat-link" id="storeMcat_<?php echo $mcat['mcat_id']; ?>" onclick="applyStoreCategoryFilter(<?php echo $mcat['mcat_id']; ?>, '<?php echo htmlspecialchars(addslashes($mcat['mcat_name'])); ?>', 0)">
                                <span><i class="fa fa-folder-open-o"></i> <?php echo htmlspecialchars($mcat['mcat_name']); ?></span>
                                <span class="badge-count"><?php echo $mcat['total_count']; ?></span>
                            </a>
                            <?php if (count($mcat['end_cats']) > 1): ?>
                                <ul style="list-style: none; padding-left: 18px; margin: 4px 0 8px 0;">
                                    <?php foreach ($mcat['end_cats'] as $ecat): ?>
                                        <li style="margin-bottom: 2px;">
                                            <a href="javascript:void(0)" class="store-cat-link" id="storeEcat_<?php echo $ecat['ecat_id']; ?>" style="font-size: 12px; padding: 4px 8px;" onclick="applyStoreCategoryFilter(0, '<?php echo htmlspecialchars(addslashes($ecat['ecat_name'])); ?>', <?php echo $ecat['ecat_id']; ?>)">
                                                <span><i class="fa fa-caret-right text-muted"></i> <?php echo htmlspecialchars($ecat['ecat_name']); ?></span>
                                                <span class="badge-count" style="font-size: 10px;"><?php echo $ecat['count']; ?></span>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <!-- Supplier Profile Info Sidebar -->
            <div class="store-sidebar-card">
                <h4 class="store-sidebar-title">
                    <i class="fa fa-building text-warning"></i> Store Details
                </h4>
                
                <p style="font-size: 13px; color: #475569; line-height: 1.5; margin-bottom: 12px;">
                    <?php echo htmlspecialchars($supplier['supplier_description'] ?: 'Leading authorized supplier of certified construction materials and industrial supplies.'); ?>
                </p>

                <hr style="margin: 10px 0;">

                <ul class="list-unstyled" style="font-size: 12.5px; line-height: 2; color: #334155; margin: 0; padding: 0;">
                    <?php if (!empty($supplier['supplier_address'])): ?>
                        <li><i class="fa fa-map-marker text-primary" style="width: 18px;"></i> <strong>Address:</strong><br><span style="color: #64748b;"><?php echo htmlspecialchars($supplier['supplier_address']); ?></span></li>
                    <?php endif; ?>
                    <?php if (!empty($supplier['supplier_delivery_areas'])): ?>
                        <li><i class="fa fa-truck text-success" style="width: 18px;"></i> <strong>Delivery Areas:</strong><br><span style="color: #64748b;"><?php echo htmlspecialchars($supplier['supplier_delivery_areas']); ?></span></li>
                    <?php endif; ?>
                    <?php if (!empty($supplier['supplier_certifications'])): ?>
                        <li><i class="fa fa-certificate text-warning" style="width: 18px;"></i> <strong>Certifications:</strong><br><span style="color: #64748b;"><?php echo htmlspecialchars($supplier['supplier_certifications']); ?></span></li>
                    <?php endif; ?>
                    <li><i class="fa fa-calendar text-muted" style="width: 18px;"></i> <strong>Partner Since:</strong><br><span style="color: #64748b;"><?php echo date('M Y', strtotime($supplier['created_at'])); ?></span></li>
                </ul>
            </div>

        </div>

        <!-- Right Column: Standardized Supplier Product Rail -->
        <div class="col-md-9 col-sm-8">
            
            <!-- Category Quick-Pills for Fast Mobile/Tablet Discovery -->
            <div class="store-quick-pills">
                <button type="button" class="store-pill-btn active" id="pill_cat_0" onclick="applyStoreCategoryFilter(0, 'All Products', 0)">
                    <i class="fa fa-list"></i> All Categories
                </button>
                <?php foreach ($structured_categories as $mcat): ?>
                    <button type="button" class="store-pill-btn" id="pill_mcat_<?php echo $mcat['mcat_id']; ?>" onclick="applyStoreCategoryFilter(<?php echo $mcat['mcat_id']; ?>, '<?php echo htmlspecialchars(addslashes($mcat['mcat_name'])); ?>', 0)">
                        <?php echo htmlspecialchars($mcat['mcat_name']); ?> (<?php echo $mcat['total_count']; ?>)
                    </button>
                <?php endforeach; ?>
            </div>

            <!-- Header Title -->
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; border-bottom: 2px solid #e2e8f0; padding-bottom: 8px;">
                <h3 style="font-size: 18px; font-weight: 800; color: #0f172a; margin: 0;">
                    <i class="fa fa-cubes text-primary"></i> <span id="storeProductsTitle">Products from <?php echo htmlspecialchars($supplier_name); ?></span>
                </h3>
                <span id="storeProductsCountBadge" style="font-size: 13px; font-weight: 700; color: #64748b;">
                    Showing <strong><?php echo count($supplier_grouped_list); ?></strong> parent products (<?php echo $total_supplier_variants; ?> variants)
                </span>
            </div>

            <!-- Standard Product Rail Container -->
            <div class="store-product-grid" id="storeProductsContainer">
                <?php if (count($supplier_grouped_list) > 0): ?>
                    <?php foreach ($supplier_grouped_list as $prod): ?>
                        <?php
                        $is_out = ($prod['total_stock'] <= 0);
                        $var_count = count($prod['variants']);
                        $price_str = ($prod['min_price'] === $prod['max_price']) 
                            ? '₱' . number_format($prod['min_price'], 2) 
                            : '₱' . number_format($prod['min_price'], 2) . ' - ₱' . number_format($prod['max_price'], 2);
                        $group_json = htmlspecialchars(json_encode($prod, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
                        ?>
                        <div class="mkt-prod-card">
                            <div class="mkt-prod-thumb" style="background-image: url('assets/uploads/<?php echo htmlspecialchars($prod['photo']); ?>');">
                                <span class="mkt-badge-cat"><?php echo htmlspecialchars($prod['ecat_name']); ?></span>
                                <?php if ($is_out): ?>
                                    <span class="label label-danger" style="position: absolute; top: 8px; right: 8px;">Out of Stock</span>
                                <?php endif; ?>
                            </div>
                            <div class="mkt-prod-body">
                                <div>
                                    <h4 class="mkt-prod-title" title="<?php echo htmlspecialchars($prod['base_name']); ?>">
                                        <a href="product.php?id=<?php echo $prod['primary_id']; ?>" style="color: inherit; text-decoration: none;"><?php echo htmlspecialchars($prod['base_name']); ?></a>
                                    </h4>
                                    <?php if ($var_count > 1): ?>
                                        <span class="mkt-prod-variant-tag"><i class="fa fa-tags"></i> <?php echo $var_count; ?> Variants Available</span>
                                    <?php else: ?>
                                        <span style="font-size: 11px; color: #64748b; margin-bottom: 8px; display: block;">Standard Size</span>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <div class="mkt-prod-price">
                                        <span class="mkt-prod-price-from">From </span><?php echo $price_str; ?>
                                    </div>
                                    <?php if ($is_out): ?>
                                        <button type="button" class="mkt-prod-btn btn-out" disabled><i class="fa fa-ban"></i> Out of Stock</button>
                                    <?php else: ?>
                                        <button type="button" class="mkt-prod-btn" onclick="handleStoreProductClick(this)" data-group="<?php echo $group_json; ?>">
                                            <i class="fa fa-shopping-cart"></i> <?php echo ($var_count > 1) ? '+ Add / Select' : '+ Add to Cart'; ?>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-md-12 text-center" style="padding: 50px 20px; background: #fff; border-radius: 8px; border: 1px solid #e2e8f0; grid-column: 1 / -1;">
                        <i class="fa fa-cubes fa-3x text-muted"></i>
                        <h4 style="font-weight: bold; margin-top: 14px; color: #0f172a;">No products available from this supplier</h4>
                        <p style="color: #64748b;">Please check back later or contact the supplier directly for inquiries.</p>
                    </div>
                <?php endif; ?>
            </div>

        </div>

    </div>

</div>

<!-- ========================================================================= -->
<!-- 4. STANDARDIZED SUPPLIER VARIANT SELECTION MODAL                          -->
<!-- ========================================================================= -->
<div class="modal fade" id="storeVariantModal" tabindex="-1" role="dialog" aria-labelledby="storeVariantModalLabel" aria-hidden="true" style="z-index: 10050;">
    <div class="modal-dialog" role="document" style="max-width: 540px; margin-top: 60px;">
        <div class="modal-content" style="border-radius: 10px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.3); border: none;">
            
            <div class="modal-header" style="background: #0f172a; color: #fff; padding: 14px 18px; border-bottom: none;">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="color: #fff; opacity: 0.9; font-size: 24px; text-shadow: none;">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title" id="storeVariantModalLabel" style="font-weight: 800; display: flex; align-items: center; gap: 8px; font-size: 18px; margin: 0; color: #fff;">
                    <i class="fa fa-cubes text-warning"></i> <span id="storeModalTitle">Select Variant</span>
                </h4>
                <div style="margin-top: 4px; font-size: 12px; color: #94a3b8;">
                    Category: <strong id="storeModalCategory" style="color: #fff;">-</strong>
                    <span style="margin-left: 10px;">| Supplier: <strong style="color: #60a5fa;"><?php echo htmlspecialchars($supplier_name); ?></strong></span>
                </div>
            </div>
            
            <form method="POST" action="" id="storeAddToCartForm">
                <?php $csrf->echoInputField(); ?>
                <input type="hidden" name="form_add_to_cart" value="1">
                <input type="hidden" name="p_id" id="storeHiddenPId" value="">
                <input type="hidden" name="p_name" id="storeHiddenPName" value="">
                <input type="hidden" name="p_current_price" id="storeHiddenPrice" value="">
                <input type="hidden" name="p_featured_photo" id="storeHiddenPhoto" value="">
                <input type="hidden" name="size_id" id="storeHiddenSizeId" value="0">
                <input type="hidden" name="size_name" id="storeHiddenSizeName" value="">
                <input type="hidden" name="color_id" id="storeHiddenColorId" value="0">
                <input type="hidden" name="color_name" id="storeHiddenColorName" value="">

                <div class="modal-body" style="padding: 20px;">
                    
                    <!-- Selected Variant Details Preview -->
                    <div style="background: #f8fafc; border: 1.5px solid #e2e8f0; border-radius: 8px; padding: 14px; margin-bottom: 16px; display: flex; gap: 14px; align-items: center;">
                        <div id="storeModalImg" style="width: 85px; height: 85px; min-width: 85px; border-radius: 6px; background-size: contain; background-repeat: no-repeat; background-position: center; background-color: #fff; border: 1px solid #cbd5e1;"></div>
                        <div style="flex-grow: 1;">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 8px;">
                                <h4 id="storeModalSelectedName" style="margin: 0 0 4px 0; font-size: 15px; font-weight: 800; color: #0f172a; line-height: 1.3;">-</h4>
                                <span id="storeModalStockBadge" class="label label-success" style="font-size: 11px; padding: 4px 8px; white-space: nowrap;">In Stock</span>
                            </div>
                            <div style="font-size: 12px; color: #64748b; margin-bottom: 6px;">
                                SKU: <strong id="storeModalSku" style="color: #334155; font-family: monospace; font-size: 13px;">-</strong>
                            </div>
                            <div style="display: flex; justify-content: space-between; align-items: baseline; flex-wrap: wrap; gap: 6px;">
                                <div style="font-size: 20px; font-weight: 900; color: #e11d48;">
                                    &#8369;<span id="storeModalPrice">0.00</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Variant Select Dropdown -->
                    <div class="form-group" style="margin-bottom: 15px;">
                        <label style="font-size: 13px; font-weight: 700; color: #1e293b; margin-bottom: 6px; display: block;">
                            <i class="fa fa-list-ul text-primary"></i> Select Specification / Variant:
                        </label>
                        <select id="storeModalSelect" class="form-control input-lg" style="height: 42px; font-size: 14px; font-weight: 600;" onchange="onStoreVariantSelectChange(this.value)">
                        </select>
                    </div>

                    <!-- Clickable Variant Chips -->
                    <div id="storeModalChipsContainer" style="margin-bottom: 16px;">
                        <label style="font-size: 12px; font-weight: 700; color: #64748b; margin-bottom: 6px; display: block; text-transform: uppercase;">
                            Quick Variant Selector:
                        </label>
                        <div id="storeModalChipsList" style="display: flex; flex-wrap: wrap; gap: 6px; max-height: 120px; overflow-y: auto; padding: 2px;"></div>
                    </div>

                    <!-- Quantity & Live Subtotal -->
                    <div style="background: #f1f5f9; border-radius: 8px; padding: 14px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
                        <div>
                            <label style="font-size: 13px; font-weight: 700; color: #1e293b; margin-bottom: 6px; display: block;">Quantity:</label>
                            <div class="input-group" style="width: 140px;">
                                <span class="input-group-btn">
                                    <button type="button" class="btn btn-default" style="font-weight: bold; font-size: 16px; padding: 6px 12px;" onclick="changeStoreModalQty(-1)">-</button>
                                </span>
                                <input type="number" name="p_qty" id="storeModalQtyInput" class="form-control text-center" style="font-size: 16px; font-weight: 800; height: 38px;" value="1" min="1" oninput="onStoreModalQtyChange()">
                                <span class="input-group-btn">
                                    <button type="button" class="btn btn-default" style="font-weight: bold; font-size: 16px; padding: 6px 12px;" onclick="changeStoreModalQty(1)">+</button>
                                </span>
                            </div>
                        </div>
                        
                        <div style="text-align: right;">
                            <span style="font-size: 12px; color: #64748b; display: block; font-weight: 600;">Total Amount:</span>
                            <span style="font-size: 22px; font-weight: 900; color: #059669;">&#8369;<span id="storeModalItemSubtotal">0.00</span></span>
                        </div>
                    </div>

                </div>

                <div class="modal-footer" style="background: #f8fafc; border-top: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
                    <a id="storeModalViewDetailsLink" href="#" class="btn btn-default" style="font-weight: 600;">
                        <i class="fa fa-info-circle"></i> View Details
                    </a>
                    <div>
                        <button type="button" class="btn btn-default" data-dismiss="modal" style="font-weight: 600; margin-right: 6px;">Close</button>
                        <button type="submit" id="storeModalAddBtn" class="btn btn-primary" style="font-weight: 800; padding: 8px 22px; font-size: 15px; background: #2563eb; border-color: #2563eb;">
                            <i class="fa fa-shopping-cart"></i> Add to Cart
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- 5. CLIENT-SIDE SUPPLIER STORE INTERACTIONS                                -->
<!-- ========================================================================= -->
<script>
// Mandatory Locked Supplier Scope (Cannot be overridden)
const storeSupplierId = <?php echo $supplier_id; ?>;
const storeSupplierName = <?php echo json_encode($supplier_name); ?>;

let currentActiveEcatId = 0;
let currentActiveMcatId = 0;
let currentActiveCatName = '';
let currentActiveSort = 'popularity';
let currentActiveSearch = '';

let currentStoreGroup = null;
let currentSelectedStoreVariant = null;

// Product Card Click Handler
function handleStoreProductClick(element) {
    const rawData = element.getAttribute('data-group');
    if (!rawData) return;
    try {
        const group = JSON.parse(rawData);
        if (!group || !group.variants || group.variants.length === 0) return;
        if (group.total_stock <= 0) {
            alert('This product is currently out of stock.');
            return;
        }
        openStoreVariantModal(group);
    } catch (e) {
        console.error('Failed to parse group data', e);
    }
}

function openStoreVariantModal(group) {
    currentStoreGroup = group;
    
    document.getElementById('storeModalTitle').innerText = group.base_name;
    document.getElementById('storeModalCategory').innerText = group.ecat_name || 'General';
    
    const select = document.getElementById('storeModalSelect');
    const chipsList = document.getElementById('storeModalChipsList');
    
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
        chip.className = `store-variant-chip ${isOutOfStock ? 'disabled' : ''}`;
        chip.id = `storeChip_${v.id}`;
        chip.title = isOutOfStock ? 'Out of stock' : `${v.stock} in stock`;
        chip.innerHTML = `<i class="fa ${isOutOfStock ? 'fa-ban text-danger' : 'fa-check-circle'}"></i> ${escapeHtml(v.spec_label)} <span style="font-weight: 800; margin-left: 2px;">₱${v.price.toFixed(0)}</span>`;
        if (!isOutOfStock) {
            chip.onclick = function() { onStoreVariantSelectChange(v.id); };
        }
        chipsList.appendChild(chip);
    });
    
    const selectedVariant = firstInStock || group.variants[0];
    document.getElementById('storeModalQtyInput').value = 1;
    onStoreVariantSelectChange(selectedVariant.id);
    
    $('#storeVariantModal').modal('show');
}

function onStoreVariantSelectChange(variantId) {
    variantId = parseInt(variantId);
    if (!currentStoreGroup || !currentStoreGroup.variants) return;
    
    const variant = currentStoreGroup.variants.find(v => v.id === variantId);
    if (!variant) return;
    
    currentSelectedStoreVariant = variant;
    
    // Sync Select Dropdown
    document.getElementById('storeModalSelect').value = variant.id;
    
    // Sync Active Chip
    document.querySelectorAll('.store-variant-chip').forEach(c => c.classList.remove('active'));
    const activeChip = document.getElementById(`storeChip_${variant.id}`);
    if (activeChip) activeChip.classList.add('active');
    
    // Update Hidden Form Inputs
    document.getElementById('storeHiddenPId').value = variant.id;
    document.getElementById('storeHiddenPName').value = variant.name;
    document.getElementById('storeHiddenPrice').value = variant.price;
    document.getElementById('storeHiddenPhoto').value = variant.photo;
    document.getElementById('storeHiddenSizeName').value = variant.size || variant.spec_label || '';
    document.getElementById('storeHiddenColorName').value = variant.color || '';
    
    // Update View Details link
    document.getElementById('storeModalViewDetailsLink').href = `product.php?id=${variant.id}`;
    
    // Update Preview
    document.getElementById('storeModalImg').style.backgroundImage = `url('assets/uploads/${variant.photo}')`;
    document.getElementById('storeModalSelectedName').innerText = variant.name;
    document.getElementById('storeModalSku').innerText = variant.sku;
    document.getElementById('storeModalPrice').innerText = variant.price.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    
    // Update Stock Badge & Button State
    const stockBadge = document.getElementById('storeModalStockBadge');
    const addBtn = document.getElementById('storeModalAddBtn');
    const qtyInput = document.getElementById('storeModalQtyInput');
    
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
    
    onStoreModalQtyChange();
}

function changeStoreModalQty(delta) {
    const qtyInput = document.getElementById('storeModalQtyInput');
    let currentVal = parseInt(qtyInput.value) || 1;
    let newVal = currentVal + delta;
    if (newVal < 1) newVal = 1;
    if (currentSelectedStoreVariant && currentSelectedStoreVariant.stock > 0 && newVal > currentSelectedStoreVariant.stock) {
        newVal = currentSelectedStoreVariant.stock;
    }
    qtyInput.value = newVal;
    onStoreModalQtyChange();
}

function onStoreModalQtyChange() {
    if (!currentSelectedStoreVariant) return;
    const qtyInput = document.getElementById('storeModalQtyInput');
    let qty = parseInt(qtyInput.value) || 1;
    if (qty < 1) qty = 1;
    if (currentSelectedStoreVariant.stock > 0 && qty > currentSelectedStoreVariant.stock) {
        qty = currentSelectedStoreVariant.stock;
        qtyInput.value = qty;
    }
    const total = qty * currentSelectedStoreVariant.price;
    document.getElementById('storeModalItemSubtotal').innerText = total.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
}

function escapeHtml(text) {
    if (!text) return '';
    return text.toString().replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
}

// Category Filtering within Supplier Scope
function applyStoreCategoryFilter(mcatId, catName, ecatId = 0) {
    currentActiveMcatId = parseInt(mcatId);
    currentActiveEcatId = parseInt(ecatId);
    currentActiveCatName = (mcatId === 0 && ecatId === 0) ? '' : catName;
    
    // Update Sidebar Active Class
    document.querySelectorAll('.store-cat-link').forEach(l => l.classList.remove('active'));
    if (ecatId > 0) {
        const ecatLink = document.getElementById(`storeEcat_${ecatId}`);
        if (ecatLink) ecatLink.classList.add('active');
    } else if (mcatId > 0) {
        const mcatLink = document.getElementById(`storeMcat_${mcatId}`);
        if (mcatLink) mcatLink.classList.add('active');
    } else {
        const allLink = document.getElementById('storeCat_all');
        if (allLink) allLink.classList.add('active');
    }

    // Update Quick Pills
    document.querySelectorAll('.store-pill-btn').forEach(p => p.classList.remove('active'));
    if (mcatId > 0) {
        const pill = document.getElementById(`pill_mcat_${mcatId}`);
        if (pill) pill.classList.add('active');
    } else {
        const allPill = document.getElementById('pill_cat_0');
        if (allPill) allPill.classList.add('active');
    }
    
    updateStoreFilterChips();
    executeStoreFilterRequest();
}

function applyStoreSorting(sortVal) {
    currentActiveSort = sortVal;
    executeStoreFilterRequest();
}

function applyStoreSearch() {
    const input = document.getElementById('storeSearchInput');
    currentActiveSearch = input ? input.value.trim() : '';
    const suggBox = document.getElementById('storeSearchSuggestionsBox');
    if (suggBox) suggBox.style.display = 'none';
    updateStoreFilterChips();
    executeStoreFilterRequest();
}

// Handle Enter key on search input
document.getElementById('storeSearchInput').addEventListener('keydown', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        applyStoreSearch();
    }
});

// Live Autocomplete for Store Search
const storeSearchInput = document.getElementById('storeSearchInput');
const storeSuggestionsBox = document.getElementById('storeSearchSuggestionsBox');

if (storeSearchInput && storeSuggestionsBox) {
    let debounceTimer = null;
    storeSearchInput.addEventListener('input', function() {
        clearTimeout(debounceTimer);
        const query = this.value.trim();
        if (query.length < 2) {
            storeSuggestionsBox.style.display = 'none';
            return;
        }
        debounceTimer = setTimeout(() => {
            fetch(`search-suggestions.php?q=${encodeURIComponent(query)}&supplier_id=${storeSupplierId}`)
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        renderStoreSuggestions(data);
                    }
                })
                .catch(err => console.error('Store suggestions error', err));
        }, 200);
    });

    document.addEventListener('click', function(e) {
        if (!storeSearchInput.contains(e.target) && !storeSuggestionsBox.contains(e.target)) {
            storeSuggestionsBox.style.display = 'none';
        }
    });
}

function renderStoreSuggestions(data) {
    const box = document.getElementById('storeSearchSuggestionsBox');
    if (!box) return;
    
    let html = '';
    
    if (data.products && data.products.length > 0) {
        html += '<div class="store-sugg-header"><i class="fa fa-cube text-primary"></i> Matching Products</div>';
        data.products.forEach(p => {
            html += `
            <a href="${p.url}" class="store-sugg-item">
                <img src="${p.photo}" class="store-sugg-img" alt="">
                <div style="flex-grow: 1; min-width: 0;">
                    <div style="font-weight: 700; font-size: 13px; color: #0f172a; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">${escapeHtml(p.name)}</div>
                    <div style="font-size: 11.5px; color: #64748b;">${escapeHtml(p.category)}</div>
                </div>
                <div style="font-weight: 800; font-size: 13px; color: #e11d48; white-space: nowrap;">₱${p.price}</div>
            </a>`;
        });
    }
    
    if (data.categories && data.categories.length > 0) {
        html += '<div class="store-sugg-header"><i class="fa fa-folder-open text-warning"></i> Categories</div>';
        data.categories.forEach(c => {
            html += `
            <a href="${c.url}" class="store-sugg-item" onclick="document.getElementById('storeSearchSuggestionsBox').style.display='none'">
                <i class="fa fa-tag text-muted"></i>
                <div style="font-weight: 600; font-size: 13px; color: #334155;">${escapeHtml(c.name)} <span style="font-size: 11px; color: #94a3b8;">(${c.type})</span></div>
            </a>`;
        });
    }
    
    if (!html) {
        html = '<div style="padding: 14px; text-align: center; color: #64748b; font-size: 13px;">No matching items found in this store.</div>';
    }
    
    box.innerHTML = html;
    box.style.display = 'block';
}

function updateStoreFilterChips() {
    const catChip = document.getElementById('storeActiveCategoryChip');
    const searchChip = document.getElementById('storeActiveSearchChip');
    const clearBtn = document.getElementById('storeClearFiltersBtn');
    
    let hasRemovable = false;
    
    if (currentActiveCatName !== '') {
        catChip.innerHTML = `<span class="filter-chip">Category: ${escapeHtml(currentActiveCatName)} <span class="chip-remove" onclick="clearStoreCategoryFilter()">&times;</span></span>`;
        catChip.style.display = 'inline';
        hasRemovable = true;
    } else {
        catChip.style.display = 'none';
    }

    if (currentActiveSearch !== '') {
        searchChip.innerHTML = `<span class="filter-chip">Search: "${escapeHtml(currentActiveSearch)}" <span class="chip-remove" onclick="clearStoreSearchFilter()">&times;</span></span>`;
        searchChip.style.display = 'inline';
        hasRemovable = true;
    } else {
        searchChip.style.display = 'none';
    }
    
    clearBtn.style.display = hasRemovable ? 'inline' : 'none';
}

function clearStoreCategoryFilter() {
    applyStoreCategoryFilter(0, 'All Products', 0);
}

function clearStoreSearchFilter() {
    const input = document.getElementById('storeSearchInput');
    if (input) input.value = '';
    currentActiveSearch = '';
    updateStoreFilterChips();
    executeStoreFilterRequest();
}

// Clear all customer filters while strictly keeping the locked supplier filter
function clearStoreFilters() {
    const input = document.getElementById('storeSearchInput');
    if (input) input.value = '';
    currentActiveSearch = '';
    applyStoreCategoryFilter(0, 'All Products', 0);
}

function executeStoreFilterRequest() {
    const container = document.getElementById('storeProductsContainer');
    const countBadge = document.getElementById('storeProductsCountBadge');
    if (!container) return;
    
    container.innerHTML = '<div class="col-md-12 text-center" style="padding: 40px; background: #fff; border-radius: 8px;"><i class="fa fa-spinner fa-spin fa-2x text-primary"></i><p style="margin-top: 10px; color: #64748b;">Filtering products...</p></div>';
    
    const params = new URLSearchParams({
        supplier_id: storeSupplierId,
        mcat_id: currentActiveMcatId,
        ecat_id: currentActiveEcatId,
        sort: currentActiveSort,
        search: currentActiveSearch
    });
    
    fetch(`api-homepage-products.php?${params.toString()}`)
        .then(res => res.json())
        .then(data => {
            if (data.success && data.products) {
                renderStoreProducts(data.products, data.total_groups, data.total_variants);
            }
        })
        .catch(err => {
            console.error('Store filter fetch error', err);
        });
}

function renderStoreProducts(products, totalGroups, totalVariants) {
    const container = document.getElementById('storeProductsContainer');
    const countBadge = document.getElementById('storeProductsCountBadge');
    if (!container) return;
    
    if (countBadge) {
        countBadge.innerHTML = `Showing <strong>${totalGroups || products.length}</strong> parent products (${totalVariants || 0} variants)`;
    }
    
    if (products.length === 0) {
        container.innerHTML = `
            <div class="col-md-12 text-center" style="padding: 50px 20px; background: #fff; border-radius: 8px; border: 1px solid #e2e8f0; grid-column: 1 / -1;">
                <i class="fa fa-cubes fa-3x text-muted"></i>
                <h4 style="font-weight: bold; margin-top: 14px; color: #0f172a;">No products match your selected filters</h4>
                <p style="color: #64748b;">Try adjusting or clearing your category or search filter.</p>
                <button type="button" class="btn btn-default btn-sm" onclick="clearStoreFilters()" style="font-weight: 700;">
                    <i class="fa fa-refresh"></i> Clear Filters
                </button>
            </div>
        `;
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
                        <button type="button" class="mkt-prod-btn" onclick="handleStoreProductClick(this)" data-group="${groupJson}">
                            <i class="fa fa-shopping-cart"></i> ${variantCount > 1 ? '+ Add / Select' : '+ Add to Cart'}
                        </button>
                    `}
                </div>
            </div>
        </div>
        `;
    });
    
    container.innerHTML = html;
}
</script>

<?php require_once('footer.php'); ?>
