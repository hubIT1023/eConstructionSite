<?php require_once('header.php'); ?>

<?php
$supplier_id = $_SESSION['supplier_user']['supplier_id'];

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
            if (!empty($_POST['walkin_address'])) {
                $customer_address = trim($_POST['walkin_address']);
            }
        }

        $payment_method = isset($_POST['payment_method']) ? $_POST['payment_method'] : 'Cash (OTC)';
        $delivery_type = isset($_POST['delivery_type']) ? $_POST['delivery_type'] : 'pickup';
        $delivery_cost = ($delivery_type === 'delivery') ? floatval($_POST['delivery_cost']) : 0.00;
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
?>

<style>
.pos-wrapper {
    margin-top: 10px;
}
.pos-product-card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 10px;
    margin-bottom: 15px;
    cursor: pointer;
    transition: all 0.2s ease-in-out;
    position: relative;
    box-shadow: 0 1px 3px rgba(0,0,0,0.04);
}
.pos-product-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 10px rgba(0,0,0,0.08);
    border-color: #3b82f6;
}
.pos-product-card.out-of-stock {
    opacity: 0.6;
    cursor: not-allowed;
    background: #f8fafc;
}
.pos-product-img {
    height: 110px;
    width: 100%;
    background-size: contain;
    background-repeat: no-repeat;
    background-position: center;
    background-color: #fff;
    border-radius: 4px;
    margin-bottom: 8px;
}
.pos-product-title {
    font-size: 13px;
    font-weight: 600;
    color: #1f2937;
    height: 34px;
    overflow: hidden;
    line-height: 1.3;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
}
.pos-product-price {
    font-size: 14px;
    font-weight: 700;
    color: #2563eb;
    margin-top: 4px;
}
.pos-stock-badge {
    position: absolute;
    top: 6px;
    right: 6px;
    font-size: 10px;
    padding: 2px 6px;
    border-radius: 4px;
}
.pos-cart-panel {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.06);
    padding: 15px;
    position: sticky;
    top: 15px;
}
.pos-cart-table th {
    font-size: 12px;
    text-transform: uppercase;
    color: #64748b;
    border-bottom: 2px solid #f1f5f9;
}
.pos-cart-table td {
    vertical-align: middle !important;
    font-size: 13px;
}
.pos-qty-btn {
    padding: 2px 7px;
    font-size: 11px;
    font-weight: bold;
}
.pos-preset-btn {
    margin-right: 4px;
    margin-bottom: 4px;
    padding: 4px 8px;
    font-size: 12px;
}
.pos-cat-pill {
    margin-right: 5px;
    margin-bottom: 5px;
    border-radius: 20px;
    padding: 4px 12px;
    font-size: 12px;
    font-weight: 500;
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
                                <span class="input-group-addon"><i class="fa fa-search"></i></span>
                                <input type="text" id="posSearchInput" class="form-control" placeholder="Search product by name, brand, or SKU..." onkeyup="filterPOSProducts()">
                                <span class="input-group-btn">
                                    <button class="btn btn-default" type="button" onclick="clearPOSSearch()"><i class="fa fa-times"></i></button>
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
                    <div class="row" id="posProductGrid" style="max-height: 620px; overflow-y: auto; padding: 4px;">
                        <?php if (count($product_list) > 0): ?>
                            <?php foreach ($product_list as $prod): 
                                $clean_price = floatval(preg_replace('/[^0-9.]/', '', strval($prod['p_current_price'])));
                                $clean_stock = intval(preg_replace('/[^0-9]/', '', strval($prod['p_qty'])));
                                $is_out_of_stock = ($clean_stock <= 0);
                                $img_src = (!empty($prod['p_featured_photo']) && file_exists('../assets/uploads/'.$prod['p_featured_photo'])) 
                                    ? '../assets/uploads/'.$prod['p_featured_photo'] 
                                    : '../assets/uploads/photo-6.jpg';

                                $prod_data = array(
                                    'id' => intval($prod['p_id']),
                                    'name' => $prod['p_name'],
                                    'price' => $clean_price,
                                    'stock' => $clean_stock,
                                    'photo' => $img_src
                                );
                            ?>
                            <div class="col-xs-6 col-sm-4 col-md-4 col-lg-3 pos-product-item" 
                                 data-name="<?php echo strtolower(htmlspecialchars($prod['p_name'] . ' ' . $prod['p_brand'])); ?>"
                                 data-category="<?php echo htmlspecialchars($prod['ecat_name']); ?>"
                                 data-product='<?php echo htmlspecialchars(json_encode($prod_data), ENT_QUOTES, 'UTF-8'); ?>'
                                 onclick="<?php echo $is_out_of_stock ? 'void(0);' : 'handleProductCardClick(this);'; ?>">
                                
                                <div class="pos-product-card <?php echo $is_out_of_stock ? 'out-of-stock' : ''; ?>">
                                    <span class="label pos-stock-badge <?php echo $is_out_of_stock ? 'label-danger' : ($clean_stock < 10 ? 'label-warning' : 'label-success'); ?>">
                                        <?php echo $is_out_of_stock ? 'Out of Stock' : $clean_stock . ' in stock'; ?>
                                    </span>
                                    <div class="pos-product-img" style="background-image: url('<?php echo $img_src; ?>');"></div>
                                    <div class="pos-product-title" title="<?php echo htmlspecialchars($prod['p_name']); ?>">
                                        <?php echo htmlspecialchars($prod['p_name']); ?>
                                    </div>
                                    <div class="pos-product-price">
                                        &#8369;<?php echo number_format($clean_price, 2); ?>
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

                    <!-- Customer Selection -->
                    <div style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 10px; border-radius: 6px; margin-bottom: 12px;">
                        <div class="row" style="margin-bottom: 8px;">
                            <div class="col-xs-6">
                                <label style="font-size: 11px; font-weight: 600; margin-bottom: 0;">
                                    <input type="radio" name="customer_type" value="walkin" checked onchange="toggleCustomerType()"> Walk-in (OTC)
                                </label>
                            </div>
                            <div class="col-xs-6 text-right">
                                <label style="font-size: 11px; font-weight: 600; margin-bottom: 0;">
                                    <input type="radio" name="customer_type" value="registered" onchange="toggleCustomerType()"> Registered User
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
                    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 10px; margin-bottom: 12px;">
                        <div style="display: flex; justify-content: space-between; font-size: 13px; margin-bottom: 6px;">
                            <span class="text-muted">Subtotal:</span>
                            <span style="font-weight: 600;">&#8369;<span id="posSubtotal">0.00</span></span>
                        </div>

                        <div style="display: flex; justify-content: space-between; align-items: center; font-size: 13px; margin-bottom: 6px;">
                            <span class="text-muted">Fulfillment:</span>
                            <select name="delivery_type" id="posDeliveryType" class="form-control input-sm" style="width: 140px; height: 26px; padding: 2px 6px; font-size: 12px;" onchange="updatePOSCalculations()">
                                <option value="pickup">Store Pickup (₱0)</option>
                                <option value="delivery">Delivery</option>
                            </select>
                        </div>

                        <div id="deliveryFeeRow" style="display: none; justify-content: space-between; align-items: center; font-size: 13px; margin-bottom: 6px;">
                            <span class="text-muted">Delivery Fee:</span>
                            <div class="input-group" style="width: 110px;">
                                <span class="input-group-addon" style="padding: 2px 6px; font-size: 11px;">&#8369;</span>
                                <input type="number" step="any" name="delivery_cost" id="posDeliveryCost" class="form-control input-sm" value="0.00" style="height: 26px; padding: 2px 6px; font-size: 12px; text-align: right;" onkeyup="updatePOSCalculations()">
                            </div>
                        </div>

                        <hr style="margin: 8px 0; border-top: 1px dashed #cbd5e1;">

                        <div style="display: flex; justify-content: space-between; font-size: 16px; font-weight: 700; color: #1e293b;">
                            <span>Grand Total:</span>
                            <span style="color: #2563eb;">&#8369;<span id="posGrandTotal">0.00</span></span>
                        </div>
                    </div>

                    <!-- Payment Method & Tendered Calculator -->
                    <div style="margin-bottom: 15px;">
                        <div class="form-group" style="margin-bottom: 8px;">
                            <label style="font-size: 12px; margin-bottom: 4px;">Payment Method:</label>
                            <select name="payment_method" id="posPaymentMethod" class="form-control input-sm" onchange="handlePaymentMethodChange()">
                                <option value="Cash (OTC)">💵 Cash (Over the Counter)</option>
                                <option value="GCash / Maya">📱 GCash / Maya E-Wallet</option>
                                <option value="Debit/Credit Card">💳 Debit / Credit Card</option>
                                <option value="Bank Transfer">🏦 Bank Transfer</option>
                                <option value="Check / Terms">📄 Check / Terms</option>
                            </select>
                        </div>

                        <div id="cashCalculatorSection">
                            <div class="form-group" style="margin-bottom: 6px;">
                                <label style="font-size: 12px; margin-bottom: 4px;">Amount Tendered:</label>
                                <div class="input-group">
                                    <span class="input-group-addon">&#8369;</span>
                                    <input type="number" step="any" id="posAmountTendered" name="amount_tendered" class="form-control" placeholder="0.00" onkeyup="updatePOSCalculations()" style="font-size: 16px; font-weight: bold;">
                                </div>
                            </div>

                            <!-- Quick Cash Presets -->
                            <div style="margin-bottom: 8px;">
                                <button type="button" class="btn btn-default btn-xs pos-preset-btn" onclick="setExactAmount()">Exact</button>
                                <button type="button" class="btn btn-default btn-xs pos-preset-btn" onclick="setCashPreset(100)">₱100</button>
                                <button type="button" class="btn btn-default btn-xs pos-preset-btn" onclick="setCashPreset(500)">₱500</button>
                                <button type="button" class="btn btn-default btn-xs pos-preset-btn" onclick="setCashPreset(1000)">₱1,000</button>
                                <button type="button" class="btn btn-default btn-xs pos-preset-btn" onclick="setCashPreset(5000)">₱5,000</button>
                            </div>

                            <div style="background: #ecfdf5; border: 1px solid #a7f3d0; padding: 8px 12px; border-radius: 6px; display: flex; justify-content: space-between; align-items: center;">
                                <span style="font-size: 13px; font-weight: 600; color: #065f46;">Change Due:</span>
                                <span style="font-size: 16px; font-weight: 700; color: #047857;">&#8369;<span id="posChangeAmount">0.00</span></span>
                            </div>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <button type="submit" id="posCompleteBtn" class="btn btn-success btn-block btn-lg" style="font-weight: bold; border-radius: 6px;" disabled>
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
            html += `
                <tr>
                    <td style="padding: 6px 4px;">
                        <strong style="color: #1e293b; font-size: 12px;">${escapeHtml(item.name)}</strong>
                        <div class="text-muted" style="font-size: 11px;">₱${item.price.toFixed(2)}</div>
                    </td>
                    <td style="padding: 6px 4px; text-align: center;">
                        <div class="btn-group btn-group-xs" style="display: inline-flex; align-items: center;">
                            <button type="button" class="btn btn-default pos-qty-btn" onclick="updateCartQty(${item.id}, ${item.qty - 1})">-</button>
                            <span style="display: inline-block; width: 26px; text-align: center; font-weight: 600; font-size: 12px;">${item.qty}</span>
                            <button type="button" class="btn btn-default pos-qty-btn" onclick="updateCartQty(${item.id}, ${item.qty + 1})">+</button>
                        </div>
                    </td>
                    <td style="padding: 6px 4px; text-align: right; font-weight: 600;">₱${lineTotal}</td>
                    <td style="padding: 6px 2px; text-align: center;">
                        <button type="button" class="btn btn-link btn-xs text-danger" onclick="removeFromCart(${item.id})" style="padding: 0;"><i class="fa fa-times"></i></button>
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

    const deliveryType = document.getElementById('posDeliveryType').value;
    const deliveryFeeRow = document.getElementById('deliveryFeeRow');
    let deliveryCost = 0;

    if (deliveryType === 'delivery') {
        deliveryFeeRow.style.display = 'flex';
        deliveryCost = parseFloat(document.getElementById('posDeliveryCost').value) || 0;
    } else {
        deliveryFeeRow.style.display = 'none';
        deliveryCost = 0;
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
    const deliveryType = document.getElementById('posDeliveryType').value;
    const deliveryCost = (deliveryType === 'delivery') ? (parseFloat(document.getElementById('posDeliveryCost').value) || 0) : 0;
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
    } else {
        walkinFields.style.display = 'none';
        registeredFields.style.display = 'block';
    }
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
