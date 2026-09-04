<?php require_once('header.php'); ?>

<?php
$supplier_id = $_SESSION['supplier_user']['supplier_id'];

function parseConstructionProductDetails($raw_name) {
    $name = trim($raw_name);
    $color = "";
    $thickness = "";
    $diameter = "";
    $size = "";
    $base_name = $name;

    // 1. Detect Color / Material keyword
    $colors = ["Orange", "Green", "Blue", "Yellow", "Red", "Black", "White", "Brown", "Tan", "Pink", "Stainless", "Galvanized", "GI", "BI"];
    foreach ($colors as $c) {
        if (preg_match('/\b' . preg_quote($c, '/') . '\b/i', $name)) {
            $color = $c;
            $name = trim(preg_replace('/\b' . preg_quote($c, '/') . '\b/i', '', $name));
            break;
        }
    }

    // 2. Detect Diameter (e.g. (D = 10 mm), (D = 16 mm), (D = 1.7 mm))
    if (preg_match('/\(\s*([dD]\s*=\s*[^,\)]+)(?:,\s*([^)]+))?\s*\)/i', $name, $matches)) {
        $diameter = trim($matches[1]);
        if (empty($color) && !empty($matches[2])) {
            $color = trim($matches[2]);
        }
        $name = trim(str_replace($matches[0], '', $name));
    }
    // 3. Detect Thickness (e.g., ( t = 1/4 " ), (t = 1.2), (t = 3/16))
    elseif (preg_match('/\(\s*([tT]\s*=\s*[^,\)]+)(?:,\s*([^)]+))?\s*\)/i', $name, $matches)) {
        $thickness = trim($matches[1]);
        if (empty($color) && !empty($matches[2])) {
            $color = trim($matches[2]);
        }
        $name = trim(str_replace($matches[0], '', $name));
    }
    // Check if there is other content in parentheses (e.g. (3-inch x 10ft) or (HEB 200))
    elseif (preg_match('/\(\s*([^\)]+)\s*\)/i', $name, $matches)) {
        $size = trim($matches[1]);
        $name = trim(str_replace($matches[0], '', $name));
    }

    // 4. Detect Size from remaining string if not yet found
    if (empty($size)) {
        // Pattern A: Hyphen followed by dimensions (e.g. - 1 1/2" x 1 1/2" or - 2" x 2" or - 2 x 3)
        if (preg_match('/-\s*([0-9\s\/\.\"]+\s*x\s*[0-9\s\/\.\"]+(?:\s*(?:inch|in|mm|cm|ft|\"))?)/i', $name, $matches)) {
            $size = trim($matches[1]);
            $name = trim(str_replace($matches[0], '', $name));
        }
        // Pattern B: Dimensions with x (e.g. 1 1/2" x 1 1/2" or 2 x 3)
        elseif (preg_match('/([0-9\s\/\.\"]+\s*x\s*[0-9\s\/\.\"]+(?:\s*(?:inch|in|mm|cm|ft|\"))?)/i', $name, $matches)) {
            $size = trim($matches[1]);
            $name = trim(str_replace($matches[0], '', $name));
        }
        // Pattern C: Common Nail 4", Finishing Nail 2", Square Bar 16mm, Round Bar 12mm
        elseif (preg_match('/\b([0-9]+(?:\.[0-9]+)?\s*(?:mm|cm|m|inch|in|ft|\"|#\d+))\b/i', $name, $matches)) {
            $size = trim($matches[1]);
            $name = trim(str_replace($matches[0], '', $name));
        }
    }

    $base_name = trim(trim($name), "- \t\n\r\0\x0B");
    if (empty($base_name)) {
        $base_name = $raw_name;
    }

    return [
        'base_name' => $base_name,
        'size' => $size,
        'thickness' => $thickness,
        'diameter' => $diameter,
        'color' => $color
    ];
}

// Fetch Supplier Info
$statement = $pdo->prepare("SELECT * FROM tbl_supplier WHERE supplier_id=?");
$statement->execute(array($supplier_id));
$supplier_info = $statement->fetch(PDO::FETCH_ASSOC);

$pos_success_receipt = null;

// Handle POS Order Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pos_action']) && $_POST['pos_action'] === 'complete_sale') {
    $cart_data_json = isset($_POST['cart_items']) ? $_POST['cart_items'] : '[]';
    $cart_items = json_decode($cart_data_json, true);

    if (!empty($cart_items)) {
        $customer_type = isset($_POST['customer_type']) ? $_POST['customer_type'] : 'walkin';
        $customer_id = 0;
        $customer_name = 'Walk-in Customer';
        $customer_email = 'walkin@pos.local';
        $customer_phone = '';
        $customer_address = 'Over the Counter';

        $is_location_active = isset($_POST['is_location_delivery']) && $_POST['is_location_delivery'] == '1';
        $location_brgy_id = isset($_POST['location_brgy_id']) ? intval($_POST['location_brgy_id']) : 0;
        $brgy_name = '';

        if ($is_location_active && $location_brgy_id > 0) {
            $stmt_b = $pdo->prepare("SELECT brgy_name FROM tbl_brgy WHERE brgy_id=?");
            $stmt_b->execute(array($location_brgy_id));
            $brgy_row = $stmt_b->fetch(PDO::FETCH_ASSOC);
            if ($brgy_row) {
                $brgy_name = $brgy_row['brgy_name'];
            }
        }

        if ($customer_type === 'registered' && !empty($_POST['registered_cust_id'])) {
            $c_id = intval($_POST['registered_cust_id']);
            $statement_c = $pdo->prepare("SELECT * FROM tbl_customer WHERE cust_id=?");
            $statement_c->execute(array($c_id));
            $cust_row = $statement_c->fetch(PDO::FETCH_ASSOC);
            if ($cust_row) {
                $customer_id = $cust_row['cust_id'];
                $customer_name = $cust_row['cust_name'];
                $customer_email = $cust_row['cust_email'];
                $customer_phone = $cust_row['cust_phone'];
                $customer_address = $cust_row['cust_address'] ?: 'Registered Address';
                if ($brgy_name) {
                    $customer_address .= ' (Delivery: Brgy. ' . $brgy_name . ')';
                }
            }
        } else {
            if (!empty($_POST['walkin_name'])) {
                $customer_name = trim($_POST['walkin_name']);
            }
            if (!empty($_POST['walkin_phone'])) {
                $customer_phone = trim($_POST['walkin_phone']);
            }
            if (!empty($_POST['walkin_email'])) {
                $customer_email = trim($_POST['walkin_email']);
            }
            if ($brgy_name) {
                $customer_address = 'Barangay ' . $brgy_name . ', Santa Barbara, Iloilo';
            } elseif (!empty($_POST['walkin_address'])) {
                $customer_address = trim($_POST['walkin_address']);
            } else {
                $customer_address = 'Over the Counter';
            }
        }

        $payment_method = isset($_POST['payment_method']) ? $_POST['payment_method'] : 'Cash (OTC)';
        $delivery_type = $is_location_active ? 'delivery' : 'pickup';
        $delivery_cost = ($is_location_active && isset($_POST['delivery_cost'])) ? floatval($_POST['delivery_cost']) : 0.00;
        $amount_tendered = isset($_POST['amount_tendered']) ? floatval($_POST['amount_tendered']) : 0.00;

        // Calculate Subtotal
        $subtotal = 0;
        foreach ($cart_items as $item) {
            $subtotal += floatval($item['price']) * intval($item['qty']);
        }
        $grand_total = $subtotal + $delivery_cost;
        $change_amount = max(0, $amount_tendered - $grand_total);

        $payment_id = 'POS-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));
        $payment_date = date('Y-m-d H:i:s');

        // Insert into tbl_payment
        $statement_p = $pdo->prepare("INSERT INTO tbl_payment (
            customer_id,
            customer_name,
            customer_email,
            payment_date,
            txnid,
            paid_amount,
            card_number,
            card_cvv,
            card_month,
            card_year,
            bank_transaction_info,
            payment_method,
            payment_status,
            shipping_status,
            payment_id,
            supplier_id
        ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?) RETURNING id");

        $statement_p->execute(array(
            $customer_id,
            $customer_name,
            $customer_email,
            $payment_date,
            $payment_id,
            intval(round($grand_total)),
            '',
            '',
            '',
            '',
            'POS Terminal - Method: ' . $payment_method . ' | Tendered: ₱' . number_format($amount_tendered, 2) . ' | Change: ₱' . number_format($change_amount, 2),
            $payment_method,
            'Paid',
            ($delivery_type === 'delivery') ? 'Pending' : 'Completed',
            $payment_id,
            $supplier_id
        ));

        // Insert items into tbl_order and update stock
        foreach ($cart_items as $item) {
            $p_id = intval($item['id']);
            $p_name = $item['name'];
            $p_qty = intval($item['qty']);
            $p_price = floatval($item['price']);
            $p_size = isset($item['size']) ? $item['size'] : '';
            $p_color = isset($item['color']) ? $item['color'] : '';

            $statement_o = $pdo->prepare("INSERT INTO tbl_order (
                product_id,
                product_name,
                size,
                color,
                quantity,
                unit_price,
                payment_id,
                supplier_id
            ) VALUES (?,?,?,?,?,?,?,?)");
            $statement_o->execute(array(
                $p_id,
                $p_name,
                $p_size,
                $p_color,
                strval($p_qty),
                strval($p_price),
                $payment_id,
                $supplier_id
            ));

            // Decrement Stock
            $statement_stock = $pdo->prepare("UPDATE tbl_product SET p_qty = GREATEST(0, p_qty - ?) WHERE p_id = ? AND supplier_id = ?");
            $statement_stock->execute(array($p_qty, $p_id, $supplier_id));

            // Automatic inventory & price rollover if stock reached 0 or 1 and (N)Quantity > 10% of (S)Level:
            // "Quantity = Quantity + (N)Quantity", "(N)Quantity = 0"
            // If "(C)Price < (N)Price" THEN "(C)Price = (N)Price", If "(C)Price > (N)Price" THEN "(C)Price = (C)Price"
            $statement_rollover = $pdo->prepare("UPDATE tbl_product 
                SET p_qty = p_qty + p_new_qty,
                    p_current_price = CASE 
                        WHEN (p_new_price IS NOT NULL AND p_new_price != '' AND NULLIF(regexp_replace(p_new_price, '[^0-9.]', '', 'g'), '')::numeric > NULLIF(regexp_replace(p_current_price, '[^0-9.]', '', 'g'), '')::numeric) 
                        THEN p_new_price 
                        ELSE p_current_price 
                    END,
                    p_new_qty = 0
                WHERE p_id = ? 
                  AND (p_qty = 0 OR p_qty = 1) 
                  AND p_new_qty > (COALESCE(p_s_level, 10) * 0.1)");
            $statement_rollover->execute(array($p_id));
        }

        // Store receipt details for instant modal popup
        $pos_success_receipt = array(
            'payment_id' => $payment_id,
            'payment_date' => $payment_date,
            'payment_method' => $payment_method,
            'customer_name' => $customer_name,
            'customer_phone' => $customer_phone,
            'customer_email' => $customer_email,
            'customer_address' => $customer_address,
            'items' => $cart_items,
            'subtotal' => $subtotal,
            'delivery_cost' => $delivery_cost,
            'grand_total' => $grand_total,
            'amount_tendered' => $amount_tendered,
            'change_amount' => $change_amount,
            'supplier_name' => $supplier_info['supplier_name'],
            'supplier_address' => $supplier_info['supplier_address'],
            'supplier_phone' => $supplier_info['supplier_phone']
        );
    }
}

// Fetch Active Products for Supplier
$statement_prod = $pdo->prepare("SELECT p.*, ec.ecat_name FROM tbl_product p 
    LEFT JOIN tbl_end_category ec ON p.ecat_id = ec.ecat_id 
    WHERE p.supplier_id = ? AND p.p_is_active = 1 
    ORDER BY p.p_name ASC");
$statement_prod->execute(array($supplier_id));
$product_list = $statement_prod->fetchAll(PDO::FETCH_ASSOC);

// Fetch Categories for Filtering
$categories = array();
foreach ($product_list as $prod) {
    if (!empty($prod['ecat_name']) && !in_array($prod['ecat_name'], $categories)) {
        $categories[] = $prod['ecat_name'];
    }
}

// Fetch Registered Customers
$statement_cust = $pdo->prepare("SELECT cust_id, cust_name, cust_email, cust_phone, cust_address FROM tbl_customer ORDER BY cust_name ASC");
$statement_cust->execute();
$registered_customers = $statement_cust->fetchAll(PDO::FETCH_ASSOC);

// Fetch Barangays for Location selection
$statement_brgy = $pdo->prepare("SELECT brgy_id, brgy_name FROM tbl_brgy ORDER BY brgy_name ASC");
$statement_brgy->execute();
$brgy_list = $statement_brgy->fetchAll(PDO::FETCH_ASSOC);

// Fetch Supplier Shipping Costs
$statement_sc = $pdo->prepare("SELECT country_id, amount FROM tbl_shipping_cost WHERE supplier_id=?");
$statement_sc->execute(array($supplier_id));
$supplier_shipping_rates = $statement_sc->fetchAll(PDO::FETCH_KEY_PAIR);

$statement_all = $pdo->prepare("SELECT amount FROM tbl_shipping_cost_all WHERE sca_id=1");
$statement_all->execute();
$default_shipping_rate = (float)($statement_all->fetchColumn() ?: 0);
?>

<style>
.pos-wrapper {
    margin-top: 10px;
}
#posProductGrid {
    display: grid !important;
    grid-template-columns: repeat(4, 1fr) !important;
    gap: 12px !important;
    max-height: 650px;
    overflow-y: auto;
    padding: 6px;
    margin-left: 0 !important;
    margin-right: 0 !important;
}
@media (max-width: 991px) {
    #posProductGrid {
        grid-template-columns: repeat(3, 1fr) !important;
    }
}
@media (max-width: 600px) {
    #posProductGrid {
        grid-template-columns: repeat(2, 1fr) !important;
    }
}
.pos-product-item {
    width: 100% !important;
    padding: 0 !important;
    float: none !important;
}
.pos-product-card {
    background: #fff;
    border: 1.5px solid #e2e8f0;
    border-radius: 8px;
    padding: 8px 10px;
    margin-bottom: 0 !important;
    cursor: pointer;
    transition: all 0.2s ease-in-out;
    position: relative;
    box-shadow: 0 1px 4px rgba(0,0,0,0.04);
    height: 100%;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}
.pos-product-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.15);
    border-color: #2563eb;
}
.pos-product-card.out-of-stock {
    opacity: 0.6;
    cursor: not-allowed;
    background: #f8fafc;
}
.pos-product-img {
    height: 95px;
    width: 100%;
    background-size: contain;
    background-repeat: no-repeat;
    background-position: center;
    background-color: #fff;
    border-radius: 5px;
    margin-bottom: 8px;
    border: 1px solid #f1f5f9;
}
.pos-product-details-box {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    padding: 6px 8px;
    margin-bottom: 6px;
    min-height: 85px;
    display: flex;
    flex-direction: column;
    justify-content: center;
}
.pos-spec-row {
    display: flex;
    align-items: baseline;
    margin-bottom: 2px;
    line-height: 1.3;
}
.pos-spec-row:last-child {
    margin-bottom: 0;
}
.pos-spec-label {
    width: 68px;
    flex-shrink: 0;
    font-size: 11px;
    font-weight: 700;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}
.pos-spec-val {
    font-size: 13px;
    font-weight: 600;
    color: #1e293b;
    word-break: break-word;
}
.pos-spec-name {
    font-size: 14px;
    font-weight: 800;
    color: #0f172a;
}
.pos-spec-size {
    font-weight: 700;
    color: #0369a1;
}
.pos-spec-thick {
    font-weight: 700;
    color: #047857;
}
.pos-color-badge {
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
.pos-color-orange { background: #fff7ed; color: #c2410c; border-color: #fed7aa; }
.pos-color-green { background: #f0fdf4; color: #15803d; border-color: #bbf7d0; }
.pos-color-blue { background: #eff6ff; color: #1d4ed8; border-color: #bfdbfe; }
.pos-color-yellow { background: #fefce8; color: #a16207; border-color: #fef08a; }
.pos-color-red { background: #fef2f2; color: #b91c1c; border-color: #fecaca; }
.pos-color-black { background: #1e293b; color: #f8fafc; border-color: #0f172a; }
.pos-color-brown { background: #fdf8f6; color: #7c2d12; border-color: #fed7aa; }
.pos-color-stainless { background: #f1f5f9; color: #475569; border-color: #cbd5e1; }
.pos-color-galvanized { background: #f0fdfa; color: #0f766e; border-color: #99f6e4; }
.pos-product-price {
    font-size: 17px;
    font-weight: 800;
    color: #1d4ed8;
    margin-top: 2px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.pos-stock-badge {
    position: absolute;
    top: 6px;
    right: 6px;
    font-size: 11px;
    font-weight: 700;
    padding: 3px 6px;
    border-radius: 4px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.12);
}
.pos-cart-panel {
    background: #fff;
    border: 2px solid #e2e8f0;
    border-radius: 10px;
    box-shadow: 0 4px 14px rgba(0,0,0,0.08);
    padding: 18px;
    position: sticky;
    top: 15px;
}
.pos-cart-table th {
    font-size: 13px;
    font-weight: 700;
    text-transform: uppercase;
    color: #475569;
    border-bottom: 2px solid #e2e8f0;
    padding: 8px 4px;
}
.pos-cart-table td {
    vertical-align: middle !important;
    font-size: 14px;
}
.pos-qty-btn {
    padding: 4px 10px;
    font-size: 14px;
    font-weight: 800;
    min-width: 30px;
}
.pos-preset-btn {
    margin-right: 5px;
    margin-bottom: 5px;
    padding: 6px 12px;
    font-size: 13px;
    font-weight: 700;
}
.pos-cat-pill {
    margin-right: 6px;
    margin-bottom: 6px;
    border-radius: 20px;
    padding: 6px 16px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
}
.pos-cat-pill.active {
    background-color: #2563eb !important;
    color: #fff !important;
    border-color: #2563eb !important;
}
</style>

<section class="content-header">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap;">
        <h1>
            <i class="fa fa-calculator" style="color: #2563eb;"></i> Point of Sale (POS)
            <small>Over-the-Counter Sales & Direct Billing</small>
        </h1>
        <div>
            <a href="order.php" class="btn btn-default btn-sm"><i class="fa fa-list"></i> Order History</a>
            <a href="index.php" class="btn btn-default btn-sm"><i class="fa fa-dashboard"></i> Dashboard</a>
        </div>
    </div>
</section>

<section class="content">
    <div class="row pos-wrapper">
        
        <!-- Left Side: Product Search & Catalog Grid -->
        <div class="col-md-7 col-lg-8">
            <div class="box box-primary" style="border-radius: 8px;">
                <div class="box-body">
                    
                    <!-- Search and Filters -->
                    <div class="row" style="margin-bottom: 12px;">
                        <div class="col-sm-7">
                            <div class="input-group">
                                <span class="input-group-addon" style="font-size: 16px;"><i class="fa fa-search"></i></span>
                                <input type="text" id="posSearchInput" class="form-control input-lg" style="height: 42px; font-size: 15px;" placeholder="Search product by name, brand, or SKU..." onkeyup="filterPOSProducts()">
                                <span class="input-group-btn">
                                    <button class="btn btn-default input-lg" type="button" onclick="clearPOSSearch()" style="height: 42px;"><i class="fa fa-times"></i></button>
                                </span>
                            </div>
                        </div>
                        <div class="col-sm-5 text-right">
                            <span class="text-muted" style="line-height: 34px; font-size: 13px;">
                                Products Available: <strong id="productCount"><?php echo count($product_list); ?></strong>
                            </span>
                        </div>
                    </div>

                    <!-- Category Filter Pills -->
                    <?php if (!empty($categories)): ?>
                    <div style="margin-bottom: 15px; overflow-x: auto; white-space: nowrap; padding-bottom: 5px;">
                        <button type="button" class="btn btn-default btn-sm pos-cat-pill active" onclick="filterCategory('all', this)">All Categories</button>
                        <?php foreach ($categories as $cat): ?>
                            <button type="button" class="btn btn-default btn-sm pos-cat-pill" onclick="filterCategory('<?php echo htmlspecialchars(addslashes($cat)); ?>', this)"><?php echo htmlspecialchars($cat); ?></button>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <!-- Products Grid -->
                    <div id="posProductGrid">
                        <?php if (count($product_list) > 0): ?>
                            <?php foreach ($product_list as $prod): 
                                $clean_price = floatval(preg_replace('/[^0-9.]/', '', strval($prod['p_current_price'])));
                                $clean_stock = intval(preg_replace('/[^0-9]/', '', strval($prod['p_qty'])));
                                $is_out_of_stock = ($clean_stock <= 0);
                                $img_src = (!empty($prod['p_featured_photo']) && file_exists('../assets/uploads/'.$prod['p_featured_photo'])) 
                                    ? '../assets/uploads/'.$prod['p_featured_photo'] 
                                    : '../assets/uploads/photo-6.jpg';

                                $parsed_spec = parseConstructionProductDetails($prod['p_name']);

                                $prod_data = array(
                                    'id' => intval($prod['p_id']),
                                    'name' => $prod['p_name'],
                                    'base_name' => $parsed_spec['base_name'],
                                    'size' => $parsed_spec['size'],
                                    'thickness' => $parsed_spec['thickness'],
                                    'diameter' => $parsed_spec['diameter'],
                                    'color' => $parsed_spec['color'],
                                    'price' => $clean_price,
                                    'stock' => $clean_stock,
                                    'photo' => $img_src
                                );
                            ?>
                            <div class="pos-product-item" 
                                 data-name="<?php echo strtolower(htmlspecialchars($prod['p_name'] . ' ' . $prod['p_brand'])); ?>"
                                 data-category="<?php echo htmlspecialchars($prod['ecat_name']); ?>"
                                 data-product='<?php echo htmlspecialchars(json_encode($prod_data), ENT_QUOTES, 'UTF-8'); ?>'
                                 onclick="<?php echo $is_out_of_stock ? 'void(0);' : 'handleProductCardClick(this);'; ?>">
                                
                                <div class="pos-product-card <?php echo $is_out_of_stock ? 'out-of-stock' : ''; ?>">
                                    <span class="label pos-stock-badge <?php echo $is_out_of_stock ? 'label-danger' : ($clean_stock < 10 ? 'label-warning' : 'label-success'); ?>">
                                        <?php echo $is_out_of_stock ? 'Out of Stock' : $clean_stock . ' in stock'; ?>
                                    </span>
                                    <div class="pos-product-img" style="background-image: url('<?php echo $img_src; ?>');"></div>
                                    
                                    <div class="pos-product-details-box">
                                        <div class="pos-spec-row">
                                            <span class="pos-spec-label">Name:</span>
                                            <span class="pos-spec-val pos-spec-name"><?php echo htmlspecialchars($parsed_spec['base_name']); ?></span>
                                        </div>
                                        <?php if (!empty($parsed_spec['size'])): ?>
                                        <div class="pos-spec-row">
                                            <span class="pos-spec-label">Size:</span>
                                            <span class="pos-spec-val pos-spec-size"><?php echo htmlspecialchars($parsed_spec['size']); ?></span>
                                        </div>
                                        <?php endif; ?>
                                        <?php if (!empty($parsed_spec['thickness'])): ?>
                                        <div class="pos-spec-row">
                                            <span class="pos-spec-label">Thickness:</span>
                                            <span class="pos-spec-val pos-spec-thick"><?php echo htmlspecialchars($parsed_spec['thickness']); ?></span>
                                        </div>
                                        <?php endif; ?>
                                        <?php if (!empty($parsed_spec['diameter'])): ?>
                                        <div class="pos-spec-row">
                                            <span class="pos-spec-label">Diameter:</span>
                                            <span class="pos-spec-val pos-spec-thick"><?php echo htmlspecialchars($parsed_spec['diameter']); ?></span>
                                        </div>
                                        <?php endif; ?>
                                        <?php if (!empty($parsed_spec['color'])): ?>
                                        <div class="pos-spec-row">
                                            <span class="pos-spec-label">Color:</span>
                                            <span class="pos-spec-val">
                                                <span class="pos-color-badge pos-color-<?php echo strtolower($parsed_spec['color']); ?>">
                                                    <?php echo htmlspecialchars($parsed_spec['color']); ?>
                                                </span>
                                            </span>
                                        </div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="pos-product-price">
                                        <span>&#8369;<?php echo number_format($clean_price, 2); ?></span>
                                        <span class="text-primary" style="font-size: 13px; font-weight: 700;">
                                            <i class="fa fa-plus-circle"></i> Add
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="col-md-12 text-center" style="padding: 40px 20px;">
                                <i class="fa fa-cubes fa-3x" style="color: #cbd5e1;"></i>
                                <p class="text-muted" style="margin-top: 10px;">No active products found in your inventory. <a href="product-add.php">Add products</a></p>
                            </div>
                        <?php endif; ?>
                    </div>

                </div>
            </div>
        </div>

        <!-- Right Side: POS Cart & Register Panel -->
        <div class="col-md-5 col-lg-4">
            <div class="pos-cart-panel">
                
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; padding-bottom: 8px; border-bottom: 1px solid #f1f5f9;">
                    <h4 style="margin: 0; font-weight: 700; color: #1e293b;"><i class="fa fa-shopping-cart text-primary"></i> Current Sale</h4>
                    <button type="button" class="btn btn-default btn-xs text-danger" onclick="clearCart()"><i class="fa fa-trash"></i> Clear</button>
                </div>

                <form id="posCheckoutForm" method="POST" action="pos.php" onsubmit="return validatePOSForm()">
                    <input type="hidden" name="pos_action" value="complete_sale">
                    <input type="hidden" id="cartItemsInput" name="cart_items" value="[]">

                    <!-- Customer Selection & Independent Location Toggle -->
                    <div style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 10px; border-radius: 6px; margin-bottom: 12px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 6px; margin-bottom: 8px; border-bottom: 1px solid #e2e8f0; padding-bottom: 6px;">
                            <!-- Customer Type Selection (Mutually Exclusive) -->
                            <div style="display: inline-flex; align-items: center; gap: 12px;">
                                <label style="font-size: 11px; font-weight: 600; margin-bottom: 0; cursor: pointer; display: inline-flex; align-items: center; gap: 3px;">
                                    <input type="radio" name="customer_type" value="walkin" checked onchange="toggleCustomerType()"> Walk-in (OTC)
                                </label>
                                <label style="font-size: 11px; font-weight: 600; margin-bottom: 0; cursor: pointer; display: inline-flex; align-items: center; gap: 3px;">
                                    <input type="radio" name="customer_type" value="registered" onchange="toggleCustomerType()"> Registered User
                                </label>
                            </div>

                            <!-- Independent Location Toggle Option -->
                            <div>
                                <label style="font-size: 11px; font-weight: 700; margin-bottom: 0; cursor: pointer; display: inline-flex; align-items: center; gap: 4px; color: #0284c7; background: #e0f2fe; padding: 2px 7px; border-radius: 4px; border: 1px solid #bae6fd;" title="Toggle to add or exclude location delivery fee">
                                    <input type="checkbox" id="locationRadio" name="is_location_delivery" value="1" onchange="toggleLocationOption()"> 
                                    <i class="fa fa-map-marker"></i> Location
                                </label>
                            </div>
                        </div>

                        <!-- Walk-in fields -->
                        <div id="walkinFields">
                            <div class="row">
                                <div class="col-xs-7" style="padding-right: 4px;">
                                    <input type="text" name="walkin_name" class="form-control input-sm" placeholder="Customer Name (e.g. Juan Cruz)">
                                </div>
                                <div class="col-xs-5" style="padding-left: 4px;">
                                    <input type="text" name="walkin_phone" class="form-control input-sm" placeholder="Phone No.">
                                </div>
                            </div>
                        </div>

                        <!-- Registered Customer Select -->
                        <div id="registeredFields" style="display: none;">
                            <select name="registered_cust_id" class="form-control select2 input-sm" style="width: 100%;">
                                <option value="">-- Select Registered Customer --</option>
                                <?php foreach ($registered_customers as $cust): ?>
                                    <option value="<?php echo $cust['cust_id']; ?>">
                                        <?php echo htmlspecialchars($cust['cust_name']) . ' (' . htmlspecialchars($cust['cust_email']) . ')'; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Location Select (tbl_brgy) - shown only when Location radio/checkbox is active -->
                        <div id="locationFields" style="display: none; margin-top: 8px; padding-top: 8px; border-top: 1px dashed #cbd5e1;">
                            <div style="font-size: 11px; font-weight: 700; color: #0284c7; margin-bottom: 4px;">
                                <i class="fa fa-truck"></i> Select Delivery Location (Barangay):
                            </div>
                            <select name="location_brgy_id" id="locationBrgySelect" class="form-control select2 input-sm" style="width: 100%;" onchange="handleLocationChange()">
                                <option value="" data-shipping="0">-- Select Barangay Location --</option>
                                <?php foreach ($brgy_list as $brgy): 
                                    $b_id = $brgy['brgy_id'];
                                    $b_shipping = isset($supplier_shipping_rates[$b_id]) ? floatval($supplier_shipping_rates[$b_id]) : $default_shipping_rate;
                                ?>
                                    <option value="<?php echo $b_id; ?>" data-name="<?php echo htmlspecialchars($brgy['brgy_name']); ?>" data-shipping="<?php echo $b_shipping; ?>">
                                        <?php echo htmlspecialchars($brgy['brgy_name']); ?> (+&#8369;<?php echo number_format($b_shipping, 2); ?> delivery fee)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Cart Items Table -->
                    <div style="max-height: 220px; overflow-y: auto; margin-bottom: 12px; border: 1px solid #f1f5f9; border-radius: 4px;">
                        <table class="table table-condensed pos-cart-table" style="margin-bottom: 0;">
                            <thead>
                                <tr>
                                    <th style="width: 45%;">Item</th>
                                    <th style="width: 25%; text-align: center;">Qty</th>
                                    <th style="width: 20%; text-align: right;">Total</th>
                                    <th style="width: 10%;"></th>
                                </tr>
                            </thead>
                            <tbody id="posCartTableBody">
                                <tr>
                                    <td colspan="4" class="text-center text-muted" style="padding: 25px 10px;">
                                        <i class="fa fa-shopping-basket fa-2x" style="color: #cbd5e1;"></i>
                                        <div style="margin-top: 5px; font-size: 12px;">Cart is empty. Click products to add.</div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pricing & Fulfillment Summary -->
                    <div style="background: #f8fafc; border: 2px solid #e2e8f0; border-radius: 8px; padding: 14px; margin-bottom: 15px;">
                        <input type="hidden" name="delivery_type" id="posDeliveryType" value="pickup">
                        <input type="hidden" name="delivery_cost" id="posDeliveryCost" value="0.00">

                        <div style="display: flex; justify-content: space-between; font-size: 15px; font-weight: 600; margin-bottom: 8px;">
                            <span class="text-muted">Subtotal:</span>
                            <span style="color: #1e293b;">&#8369;<span id="posSubtotal">0.00</span></span>
                        </div>

                        <div style="display: flex; justify-content: space-between; align-items: center; font-size: 14px; margin-bottom: 8px;">
                            <span class="text-muted" style="font-weight: 600;">Fulfillment:</span>
                            <span id="posFulfillmentBadge" style="font-weight: 700; color: #059669; font-size: 13px;">
                                <i class="fa fa-shopping-bag"></i> Store Pickup (₱0)
                            </span>
                        </div>

                        <div id="deliveryFeeRow" style="display: none; justify-content: space-between; align-items: center; font-size: 14px; margin-bottom: 8px;">
                            <span class="text-muted" style="font-weight: 600;">Delivery Cost:</span>
                            <span style="font-weight: 800; color: #0284c7;">+&#8369;<span id="posDeliveryFeeDisplay">0.00</span></span>
                        </div>

                        <div style="background: #eff6ff; border: 2px solid #bfdbfe; border-radius: 8px; padding: 10px 14px; margin-top: 10px; display: flex; justify-content: space-between; align-items: center;">
                            <span style="font-size: 18px; font-weight: 800; color: #1e3a8a;">Grand Total:</span>
                            <span style="font-size: 24px; font-weight: 900; color: #1d4ed8;">&#8369;<span id="posGrandTotal">0.00</span></span>
                        </div>
                    </div>

                    <!-- Payment Method & Tendered Calculator -->
                    <div style="margin-bottom: 18px;">
                        <div class="form-group" style="margin-bottom: 12px;">
                            <label style="font-size: 14px; font-weight: 700; color: #1e293b; margin-bottom: 6px;">Payment Method:</label>
                            <select name="payment_method" id="posPaymentMethod" class="form-control input-lg" style="height: 42px; font-size: 15px; font-weight: 600;" onchange="handlePaymentMethodChange()">
                                <option value="Cash (OTC)">💵 Cash (Over the Counter)</option>
                                <option value="GCash / Maya">📱 GCash / Maya E-Wallet</option>
                                <option value="Debit/Credit Card">💳 Debit / Credit Card</option>
                                <option value="Bank Transfer">🏦 Bank Transfer</option>
                                <option value="Check / Terms">📄 Check / Terms</option>
                            </select>
                        </div>

                        <div id="cashCalculatorSection">
                            <div class="form-group" style="margin-bottom: 8px;">
                                <label style="font-size: 14px; font-weight: 700; color: #1e293b; margin-bottom: 6px;">Amount Tendered (Cash Received):</label>
                                <div class="input-group">
                                    <span class="input-group-addon" style="font-size: 20px; font-weight: bold;">&#8369;</span>
                                    <input type="number" step="any" id="posAmountTendered" name="amount_tendered" class="form-control input-lg" placeholder="0.00" onkeyup="updatePOSCalculations()" style="font-size: 22px; font-weight: 900; height: 48px; color: #0f172a;">
                                </div>
                            </div>

                            <!-- Quick Cash Presets -->
                            <div style="margin-bottom: 12px;">
                                <button type="button" class="btn btn-default pos-preset-btn" onclick="setExactAmount()">Exact</button>
                                <button type="button" class="btn btn-default pos-preset-btn" onclick="setCashPreset(100)">₱100</button>
                                <button type="button" class="btn btn-default pos-preset-btn" onclick="setCashPreset(500)">₱500</button>
                                <button type="button" class="btn btn-default pos-preset-btn" onclick="setCashPreset(1000)">₱1,000</button>
                                <button type="button" class="btn btn-default pos-preset-btn" onclick="setCashPreset(5000)">₱5,000</button>
                            </div>

                            <div style="background: #ecfdf5; border: 2px solid #6ee7b7; padding: 12px 16px; border-radius: 8px; display: flex; justify-content: space-between; align-items: center;">
                                <span style="font-size: 17px; font-weight: 800; color: #065f46;">Change Due:</span>
                                <span style="font-size: 24px; font-weight: 900; color: #047857;">&#8369;<span id="posChangeAmount">0.00</span></span>
                            </div>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <button type="submit" id="posCompleteBtn" class="btn btn-success btn-block btn-lg" style="font-size: 18px; font-weight: 800; border-radius: 8px; padding: 14px 20px; box-shadow: 0 4px 10px rgba(16, 185, 129, 0.3);" disabled>
                        <i class="fa fa-check-circle"></i> Complete Sale & Print Receipt
                    </button>

                </form>

            </div>
        </div>

    </div>
</section>

<!-- Success Receipt Modal -->
<?php if ($pos_success_receipt): ?>
<div class="modal fade in" id="posSuccessModal" tabindex="-1" role="dialog" style="display: block; background: rgba(0,0,0,0.6);">
    <div class="modal-dialog" role="document" style="max-width: 520px;">
        <div class="modal-content" style="border-radius: 8px; overflow: hidden;">
            <div class="modal-header bg-green" style="background-color: #10b981 !important; color: #fff;">
                <button type="button" class="close" data-dismiss="modal" onclick="closeReceiptModal()" style="color: #fff; opacity: 0.9;">&times;</button>
                <h4 class="modal-title" style="font-weight: bold;"><i class="fa fa-check-circle"></i> Sale Completed Successfully!</h4>
            </div>
            
            <div class="modal-body" id="posPrintReceiptArea" style="padding: 20px; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; color: #333;">
                
                <!-- Receipt Header -->
                <div style="text-align: center; margin-bottom: 15px;">
                    <h3 style="margin: 0; color: #1e3a8a; font-weight: bold; letter-spacing: 0.5px;">E-CONSTRUCTION SUPPLY</h3>
                    <p style="margin: 3px 0 0 0; font-size: 13px; color: #64748b;">Online Construction Supply</p>
                    <hr style="margin: 12px 0; border: 0; border-top: 1px dashed #cbd5e1;">
                    <h4 style="margin: 0; font-size: 16px; font-weight: bold; color: #334155; text-transform: uppercase;">Official Point of Sale Receipt</h4>
                </div>

                <!-- Info Grid -->
                <table style="width: 100%; font-size: 12px; margin-bottom: 15px;">
                    <tr>
                        <td style="width: 55%; vertical-align: top; line-height: 1.4;">
                            <strong style="color: #475569; font-size: 10px; text-transform: uppercase;">Supplier Store:</strong><br>
                            <strong><?php echo htmlspecialchars($pos_success_receipt['supplier_name']); ?></strong><br>
                            <?php echo nl2br(htmlspecialchars($pos_success_receipt['supplier_address'])); ?><br>
                            Tel: <?php echo htmlspecialchars($pos_success_receipt['supplier_phone']); ?>
                        </td>
                        <td style="width: 45%; vertical-align: top; text-align: right; line-height: 1.4;">
                            <strong style="color: #475569; font-size: 10px; text-transform: uppercase;">Transaction Info:</strong><br>
                            <strong>Receipt #:</strong> <?php echo htmlspecialchars($pos_success_receipt['payment_id']); ?><br>
                            <strong>Date:</strong> <?php echo htmlspecialchars($pos_success_receipt['payment_date']); ?><br>
                            <strong>Method:</strong> <?php echo htmlspecialchars($pos_success_receipt['payment_method']); ?><br>
                            <span class="label label-success" style="font-size: 10px; padding: 2px 6px;">PAID</span>
                        </td>
                    </tr>
                </table>

                <div style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 8px 10px; border-radius: 4px; font-size: 12px; margin-bottom: 15px;">
                    <strong>Customer:</strong> <?php echo htmlspecialchars($pos_success_receipt['customer_name']); ?>
                    <?php if (!empty($pos_success_receipt['customer_phone'])): ?>
                        | <strong>Phone:</strong> <?php echo htmlspecialchars($pos_success_receipt['customer_phone']); ?>
                    <?php endif; ?>
                </div>

                <!-- Items Table -->
                <table style="width: 100%; border-collapse: collapse; font-size: 12px; margin-bottom: 15px;">
                    <thead>
                        <tr style="background: #f1f5f9; border-top: 1px solid #cbd5e1; border-bottom: 1px solid #cbd5e1;">
                            <th style="padding: 6px 4px; text-align: left;">Item</th>
                            <th style="padding: 6px 4px; text-align: center; width: 40px;">Qty</th>
                            <th style="padding: 6px 4px; text-align: right; width: 70px;">Price</th>
                            <th style="padding: 6px 4px; text-align: right; width: 80px;">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pos_success_receipt['items'] as $item): 
                            $item_total = floatval($item['price']) * intval($item['qty']);
                        ?>
                        <tr style="border-bottom: 1px solid #f1f5f9;">
                            <td style="padding: 6px 4px; font-weight: 500;"><?php echo htmlspecialchars($item['name']); ?></td>
                            <td style="padding: 6px 4px; text-align: center;"><?php echo intval($item['qty']); ?></td>
                            <td style="padding: 6px 4px; text-align: right;">&#8369;<?php echo number_format($item['price'], 2); ?></td>
                            <td style="padding: 6px 4px; text-align: right; font-weight: 600;">&#8369;<?php echo number_format($item_total, 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="2" style="border-top: 1px solid #cbd5e1;"></td>
                            <td style="border-top: 1px solid #cbd5e1; padding: 6px 4px; text-align: right; font-weight: 600;">Subtotal:</td>
                            <td style="border-top: 1px solid #cbd5e1; padding: 6px 4px; text-align: right; font-weight: 600;">&#8369;<?php echo number_format($pos_success_receipt['subtotal'], 2); ?></td>
                        </tr>
                        <?php if ($pos_success_receipt['delivery_cost'] > 0): ?>
                        <tr>
                            <td colspan="2"></td>
                            <td style="padding: 4px; text-align: right; color: #64748b;">Delivery:</td>
                            <td style="padding: 4px; text-align: right; font-weight: 600;">&#8369;<?php echo number_format($pos_success_receipt['delivery_cost'], 2); ?></td>
                        </tr>
                        <?php endif; ?>
                        <tr style="background: #f8fafc; font-size: 14px;">
                            <td colspan="2" style="border-top: 2px solid #cbd5e1;"></td>
                            <td style="border-top: 2px solid #cbd5e1; padding: 8px 4px; text-align: right; font-weight: 700; color: #1e3a8a;">Total Paid:</td>
                            <td style="border-top: 2px solid #cbd5e1; padding: 8px 4px; text-align: right; font-weight: 700; color: #1e3a8a;">&#8369;<?php echo number_format($pos_success_receipt['grand_total'], 2); ?></td>
                        </tr>
                        <?php if ($pos_success_receipt['amount_tendered'] > 0): ?>
                        <tr>
                            <td colspan="2"></td>
                            <td style="padding: 4px; text-align: right; font-size: 11px; color: #64748b;">Tendered:</td>
                            <td style="padding: 4px; text-align: right; font-size: 11px;">&#8369;<?php echo number_format($pos_success_receipt['amount_tendered'], 2); ?></td>
                        </tr>
                        <tr>
                            <td colspan="2"></td>
                            <td style="padding: 4px; text-align: right; font-size: 11px; color: #047857; font-weight: bold;">Change:</td>
                            <td style="padding: 4px; text-align: right; font-size: 11px; color: #047857; font-weight: bold;">&#8369;<?php echo number_format($pos_success_receipt['change_amount'], 2); ?></td>
                        </tr>
                        <?php endif; ?>
                    </tfoot>
                </table>

                <div style="text-align: center; margin-top: 20px; font-size: 12px; color: #64748b;">
                    <p style="margin: 0; font-weight: bold; color: #334155;">Thank you for your order!</p>
                    <p style="margin: 3px 0 0 0; font-size: 11px;">We appreciate your trust in us for your construction needs.</p>
                    <p style="margin: 4px 0 0 0; font-size: 10px; color: #94a3b8;">This is a system-generated official sales receipt.</p>
                </div>

            </div>

            <div class="modal-footer" style="background: #f8fafc; border-top: 1px solid #e2e8f0; display: flex; justify-content: space-between;">
                <button type="button" class="btn btn-default" onclick="closeReceiptModal()"><i class="fa fa-plus"></i> New Sale</button>
                <button type="button" class="btn btn-primary" onclick="printPOSReceipt()"><i class="fa fa-print"></i> Print Receipt</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
let cart = [];

function handleProductCardClick(element) {
    const rawData = element.getAttribute('data-product');
    if (rawData) {
        try {
            const product = JSON.parse(rawData);
            addToCart(product);
        } catch (e) {
            console.error('Failed to parse product data', e);
        }
    }
}

function addToCart(product) {
    const existingIndex = cart.findIndex(item => item.id === product.id);
    if (existingIndex > -1) {
        if (cart[existingIndex].qty < product.stock) {
            cart[existingIndex].qty++;
        } else {
            alert('Cannot add more than available inventory (' + product.stock + ' units).');
            return;
        }
    } else {
        if (product.stock <= 0) {
            alert('Item is out of stock.');
            return;
        }
        cart.push({
            id: product.id,
            name: product.name,
            base_name: product.base_name || product.name,
            size: product.size || '',
            thickness: product.thickness || '',
            diameter: product.diameter || '',
            color: product.color || '',
            price: product.price,
            stock: product.stock,
            qty: 1
        });
    }
    renderCart();
}

function updateCartQty(productId, newQty) {
    const item = cart.find(i => i.id === productId);
    if (item) {
        newQty = parseInt(newQty);
        if (newQty <= 0) {
            removeFromCart(productId);
            return;
        }
        if (newQty > item.stock) {
            alert('Maximum available inventory is ' + item.stock);
            item.qty = item.stock;
        } else {
            item.qty = newQty;
        }
        renderCart();
    }
}

function removeFromCart(productId) {
    cart = cart.filter(i => i.id !== productId);
    renderCart();
}

function clearCart() {
    if (cart.length > 0 && confirm('Clear all items from the current sale?')) {
        cart = [];
        renderCart();
    }
}

function renderCart() {
    const tbody = document.getElementById('posCartTableBody');
    const completeBtn = document.getElementById('posCompleteBtn');
    const cartItemsInput = document.getElementById('cartItemsInput');

    if (cart.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="4" class="text-center text-muted" style="padding: 25px 10px;">
                    <i class="fa fa-shopping-basket fa-2x" style="color: #cbd5e1;"></i>
                    <div style="margin-top: 5px; font-size: 12px;">Cart is empty. Click products to add.</div>
                </td>
            </tr>`;
        completeBtn.disabled = true;
        cartItemsInput.value = '[]';
    } else {
        let html = '';
        cart.forEach(item => {
            const lineTotal = (item.price * item.qty).toFixed(2);
            let specsArr = [];
            if (item.size) specsArr.push(`<span style="color: #0369a1; font-weight: 700;">${escapeHtml(item.size)}</span>`);
            if (item.thickness) specsArr.push(`<span style="color: #047857; font-weight: 700;">${escapeHtml(item.thickness)}</span>`);
            if (item.diameter) specsArr.push(`<span style="color: #047857; font-weight: 700;">Diameter: ${escapeHtml(item.diameter)}</span>`);
            if (item.color) specsArr.push(`<span class="pos-color-badge pos-color-${item.color.toLowerCase()}">${escapeHtml(item.color)}</span>`);
            let specsHtml = specsArr.length > 0 ? `<div style="font-size: 12px; margin-top: 3px; display: flex; flex-wrap: wrap; gap: 4px;">${specsArr.join(' ')}</div>` : '';

            html += `
                <tr>
                    <td style="padding: 8px 4px;">
                        <strong style="color: #0f172a; font-size: 14px; display: block; line-height: 1.3;">${escapeHtml(item.base_name || item.name)}</strong>
                        ${specsHtml}
                        <div style="font-size: 13px; font-weight: 600; color: #64748b; margin-top: 2px;">&#8369;${item.price.toFixed(2)} each</div>
                    </td>
                    <td style="padding: 8px 4px; text-align: center;">
                        <div class="btn-group" style="display: inline-flex; align-items: center;">
                            <button type="button" class="btn btn-default pos-qty-btn" onclick="updateCartQty(${item.id}, ${item.qty - 1})">-</button>
                            <span style="display: inline-block; width: 30px; text-align: center; font-weight: 800; font-size: 15px; color: #0f172a;">${item.qty}</span>
                            <button type="button" class="btn btn-default pos-qty-btn" onclick="updateCartQty(${item.id}, ${item.qty + 1})">+</button>
                        </div>
                    </td>
                    <td style="padding: 8px 4px; text-align: right; font-weight: 800; font-size: 16px; color: #1e40af;">&#8369;${lineTotal}</td>
                    <td style="padding: 8px 2px; text-align: center;">
                        <button type="button" class="btn btn-link text-danger" onclick="removeFromCart(${item.id})" style="padding: 2px 4px; font-size: 16px;" title="Remove item"><i class="fa fa-times-circle"></i></button>
                    </td>
                </tr>`;
        });
        tbody.innerHTML = html;
        completeBtn.disabled = false;
        cartItemsInput.value = JSON.stringify(cart);
    }

    updatePOSCalculations();
}

function updatePOSCalculations() {
    let subtotal = 0;
    cart.forEach(item => {
        subtotal += item.price * item.qty;
    });

    const isLocationActive = document.getElementById('locationRadio').checked;
    const deliveryFeeRow = document.getElementById('deliveryFeeRow');
    const posDeliveryType = document.getElementById('posDeliveryType');
    const posFulfillmentBadge = document.getElementById('posFulfillmentBadge');
    const posDeliveryFeeDisplay = document.getElementById('posDeliveryFeeDisplay');
    const locationSelect = document.getElementById('locationBrgySelect');
    
    let deliveryCost = 0;

    if (isLocationActive) {
        deliveryCost = parseFloat(document.getElementById('posDeliveryCost').value) || 0;
        posDeliveryType.value = 'delivery';
        
        const selectedOption = locationSelect.options[locationSelect.selectedIndex];
        const brgyName = (selectedOption && selectedOption.dataset.name) ? selectedOption.dataset.name : '';
        
        if (brgyName) {
            posFulfillmentBadge.innerHTML = `<i class="fa fa-truck text-info"></i> Delivery: Brgy. ${escapeHtml(brgyName)}`;
            posFulfillmentBadge.style.color = '#0284c7';
        } else {
            posFulfillmentBadge.innerHTML = `<i class="fa fa-truck text-info"></i> Delivery (Select Brgy)`;
            posFulfillmentBadge.style.color = '#0284c7';
        }
        
        posDeliveryFeeDisplay.innerText = deliveryCost.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        deliveryFeeRow.style.display = 'flex';
    } else {
        deliveryCost = 0;
        document.getElementById('posDeliveryCost').value = '0.00';
        posDeliveryType.value = 'pickup';
        posFulfillmentBadge.innerHTML = `<i class="fa fa-shopping-bag"></i> Store Pickup (₱0)`;
        posFulfillmentBadge.style.color = '#059669';
        deliveryFeeRow.style.display = 'none';
    }

    const grandTotal = subtotal + deliveryCost;
    document.getElementById('posSubtotal').innerText = subtotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    document.getElementById('posGrandTotal').innerText = grandTotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    // Update Change
    const tendered = parseFloat(document.getElementById('posAmountTendered').value) || 0;
    const change = Math.max(0, tendered - grandTotal);
    document.getElementById('posChangeAmount').innerText = change.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function setExactAmount() {
    let subtotal = 0;
    cart.forEach(item => subtotal += item.price * item.qty);
    const isLocationActive = document.getElementById('locationRadio').checked;
    const deliveryCost = isLocationActive ? (parseFloat(document.getElementById('posDeliveryCost').value) || 0) : 0;
    const grandTotal = subtotal + deliveryCost;
    document.getElementById('posAmountTendered').value = grandTotal.toFixed(2);
    updatePOSCalculations();
}

function setCashPreset(amount) {
    document.getElementById('posAmountTendered').value = amount.toFixed(2);
    updatePOSCalculations();
}

function handlePaymentMethodChange() {
    const method = document.getElementById('posPaymentMethod').value;
    const cashSection = document.getElementById('cashCalculatorSection');
    if (method.indexOf('Cash') > -1) {
        cashSection.style.display = 'block';
    } else {
        setExactAmount();
    }
}

function toggleCustomerType() {
    const type = document.querySelector('input[name="customer_type"]:checked').value;
    const walkinFields = document.getElementById('walkinFields');
    const registeredFields = document.getElementById('registeredFields');
    
    if (type === 'walkin') {
        walkinFields.style.display = 'block';
        registeredFields.style.display = 'none';
    } else if (type === 'registered') {
        walkinFields.style.display = 'none';
        registeredFields.style.display = 'block';
    }
}

function toggleLocationOption() {
    const isLocationActive = document.getElementById('locationRadio').checked;
    const locationFields = document.getElementById('locationFields');
    
    if (isLocationActive) {
        locationFields.style.display = 'block';
        handleLocationChange();
    } else {
        locationFields.style.display = 'none';
        document.getElementById('posDeliveryCost').value = '0.00';
        updatePOSCalculations();
    }
}

function handleLocationChange() {
    const isLocationActive = document.getElementById('locationRadio').checked;
    if (!isLocationActive) {
        document.getElementById('posDeliveryCost').value = '0.00';
        updatePOSCalculations();
        return;
    }

    const select = document.getElementById('locationBrgySelect');
    const selectedOption = select.options[select.selectedIndex];
    let shippingRate = 0;
    
    if (selectedOption && selectedOption.dataset.shipping !== undefined && select.value !== '') {
        shippingRate = parseFloat(selectedOption.dataset.shipping) || 0;
    }
    
    document.getElementById('posDeliveryCost').value = shippingRate.toFixed(2);
    updatePOSCalculations();
}

function filterPOSProducts() {
    const query = document.getElementById('posSearchInput').value.toLowerCase().trim();
    const items = document.querySelectorAll('.pos-product-item');
    let visibleCount = 0;

    items.forEach(item => {
        const name = item.getAttribute('data-name');
        const activeCat = document.querySelector('.pos-cat-pill.active').innerText.trim();

        const matchesQuery = (name.indexOf(query) > -1);
        const matchesCat = (activeCat === 'All Categories' || item.getAttribute('data-category') === activeCat);

        if (matchesQuery && matchesCat) {
            item.style.display = '';
            visibleCount++;
        } else {
            item.style.display = 'none';
        }
    });

    document.getElementById('productCount').innerText = visibleCount;
}

function clearPOSSearch() {
    document.getElementById('posSearchInput').value = '';
    filterPOSProducts();
}

function filterCategory(catName, btn) {
    document.querySelectorAll('.pos-cat-pill').forEach(pill => pill.classList.remove('active'));
    btn.classList.add('active');
    filterPOSProducts();
}

function validatePOSForm() {
    if (cart.length === 0) {
        alert('Please add at least one item to the cart.');
        return false;
    }
    const type = document.querySelector('input[name="customer_type"]:checked').value;
    if (type === 'registered') {
        const regCust = document.querySelector('select[name="registered_cust_id"]').value;
        if (!regCust) {
            alert('Please select a registered customer.');
            return false;
        }
    }
    const isLocationActive = document.getElementById('locationRadio').checked;
    if (isLocationActive) {
        const brgy = document.getElementById('locationBrgySelect').value;
        if (!brgy) {
            alert('Please select a Barangay location or uncheck the Location option.');
            return false;
        }
    }
    return true;
}

function closeReceiptModal() {
    window.location.href = 'pos.php';
}

function printPOSReceipt() {
    const printContent = document.getElementById('posPrintReceiptArea').innerHTML;
    const printWindow = window.open('', '_blank', 'width=750,height=800');
    printWindow.document.write(`
        <html>
        <head>
            <title>POS Receipt</title>
            <style>
                body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; padding: 20px; color: #333; margin: 0; }
                table { width: 100%; border-collapse: collapse; }
                @media print {
                    body { padding: 0; }
                    @page { margin: 10mm; }
                }
            </style>
        </head>
        <body>
            ${printContent}
        </body>
        </html>
    `);
    printWindow.document.close();
    printWindow.focus();
    setTimeout(() => {
        printWindow.print();
        printWindow.close();
    }, 350);
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.innerText = text;
    return div.innerHTML;
}
</script>

<?php require_once('footer.php'); ?>
