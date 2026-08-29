<?php require_once('header.php'); ?>

<?php
// Enforce login for RFQ submissions
if(!isset($_SESSION['customer'])) {
    $_SESSION['redirect_to'] = $_SERVER['REQUEST_URI'];
    ?>
    <script type="text/javascript">
        alert('Please login as a customer to submit a Request for Quotation (RFQ).');
        window.location.href = 'login.php';
    </script>
    <?php
    exit;
}

$product = null;
$supplier_id = 0;

if (isset($_GET['product_id'])) {
    $statement = $pdo->prepare("SELECT * FROM tbl_product WHERE p_id = ?");
    $statement->execute(array($_GET['product_id']));
    $product = $statement->fetch(PDO::FETCH_ASSOC);
    if ($product) {
        $supplier_id = $product['supplier_id'];
    }
}

if (isset($_GET['supplier_id'])) {
    $supplier_id = intval($_GET['supplier_id']);
}
?>

<div class="page-banner" style="background-image: url(assets/uploads/about-banner.jpg);">
    <div class="inner">
        <h1>Request for Quotation (RFQ)</h1>
    </div>
</div>

<div class="page">
    <div class="container">
        <div class="row">            
            <div class="col-md-8 col-md-offset-2">
                <div style="background: #f8f9fa; border: 1px solid #ddd; padding: 30px; border-radius: 5px;">
                    <h3 style="font-weight: bold; color: #1F2937; margin-top: 0;"><i class="fa fa-file-text-o"></i> Submit Your Material RFQ</h3>
                    <p class="text-muted">Fill out the detailed RFQ below. The supplier will prepare a customized B2B quotation including freight discounts and site logistics delivery dates.</p>
                    <hr>

                    <?php
                    if(isset($_POST['form_rfq'])) {
                        $valid = 1;
                        $quantity = intval($_POST['quantity']);
                        $notes = trim($_POST['notes']);
                        $sel_supplier_id = intval($_POST['supplier_id']);
                        $sel_product_id = intval($_POST['product_id']);

                        if ($quantity <= 0) {
                            $valid = 0;
                            $error_message = "Please enter a valid quantity.";
                        }
                        if ($sel_supplier_id <= 0) {
                            $valid = 0;
                            $error_message = "Please select a valid supplier.";
                        }

                        if($valid == 1) {
                            // Insert into tbl_quote
                            $statement = $pdo->prepare("INSERT INTO tbl_quote (cust_id, supplier_id, status, notes) VALUES (?, ?, ?, ?)");
                            $statement->execute(array($_SESSION['customer']['cust_id'], $sel_supplier_id, 'Pending', $notes));
                            
                            $quote_id = $pdo->lastInsertId();

                            // Insert into tbl_quote_item
                            $statement = $pdo->prepare("INSERT INTO tbl_quote_item (quote_id, product_id, quantity, unit_price) VALUES (?, ?, ?, ?)");
                            // Initially unit_price is null or supplier's base price
                            $base_price = 0.00;
                            if ($sel_product_id > 0) {
                                $statement_p = $pdo->prepare("SELECT p_current_price FROM tbl_product WHERE p_id = ?");
                                $statement_p->execute(array($sel_product_id));
                                $p_row = $statement_p->fetch(PDO::FETCH_ASSOC);
                                if ($p_row) {
                                    $base_price = floatval($p_row['p_current_price']);
                                }
                            }
                            $statement->execute(array($quote_id, $sel_product_id, $quantity, $base_price));

                            // Create an automated starting B2B message history thread
                            $msg_content = "RFQ created for product. Requested Quantity: " . $quantity . ". Contractor Notes: " . $notes;
                            $statement_msg = $pdo->prepare("INSERT INTO tbl_message (sender_type, sender_id, recipient_type, recipient_id, message_content) VALUES (?, ?, ?, ?, ?)");
                            $statement_msg->execute(array('Customer', $_SESSION['customer']['cust_id'], 'Supplier', $sel_supplier_id, $msg_content));

                            $success_message = "Your Request for Quotation (RFQ) was successfully submitted to the supplier! You can monitor bids and messages in your Customer Portal.";
                        }
                    }
                    ?>

                    <?php if(isset($error_message) && $error_message != ''): ?>
                        <div class="alert alert-danger"><?php echo $error_message; ?></div>
                    <?php endif; ?>
                    <?php if(isset($success_message) && $success_message != ''): ?>
                        <div class="alert alert-success"><?php echo $success_message; ?></div>
                    <?php endif; ?>

                    <form action="" method="post">
                        <!-- Product Selection details -->
                        <?php if ($product): ?>
                            <div class="form-group" style="background: #e2f0fe; padding: 15px; border-radius: 4px; border: 1px solid #bce0fd;">
                                <label style="margin: 0; color: #1e3a8a;"><strong>Selected Product:</strong></label>
                                <h4 style="margin: 5px 0 0 0; font-weight: bold; color: #1e3a8a;"><?php echo htmlspecialchars($product['p_name']); ?></h4>
                                <p style="margin: 5px 0 0 0; font-size: 13px; color: #1e3a8a;">Brand: <?php echo htmlspecialchars($product['p_brand']); ?> | MOQ constraint: <?php echo htmlspecialchars($product['p_moq']); ?> units</p>
                                <input type="hidden" name="product_id" value="<?php echo $product['p_id']; ?>">
                                <input type="hidden" name="supplier_id" value="<?php echo $supplier_id; ?>">
                            </div>
                        <?php else: ?>
                            <div class="form-group">
                                <label for="product_id">Choose Product for RFQ *</label>
                                <select class="form-control" name="product_id" id="rfq_product_select" required>
                                    <option value="">Select a product...</option>
                                    <?php
                                    $statement_all_p = $pdo->prepare("SELECT * FROM tbl_product WHERE p_is_active = 1 ORDER BY p_name ASC");
                                    $statement_all_p->execute();
                                    $all_p = $statement_all_p->fetchAll(PDO::FETCH_ASSOC);
                                    foreach ($all_p as $p) {
                                        echo "<option value='".$p['p_id']."' data-supplier='".$p['supplier_id']."' data-moq='".$p['p_moq']."'>".htmlspecialchars($p['p_name'])." (MOQ: ".$p['p_moq'].")</option>";
                                    }
                                    ?>
                                </select>
                                <input type="hidden" name="supplier_id" id="rfq_supplier_id" value="">
                            </div>
                            <script type="text/javascript">
                                document.getElementById('rfq_product_select').addEventListener('change', function() {
                                    var selectedOption = this.options[this.selectedIndex];
                                    var supplierId = selectedOption.getAttribute('data-supplier');
                                    document.getElementById('rfq_supplier_id').value = supplierId;
                                });
                            </script>
                        <?php endif; ?>

                        <div class="form-group">
                            <label for="quantity">Requested Quantity *</label>
                            <input type="number" class="form-control" name="quantity" min="<?php echo $product ? $product['p_moq'] : 1; ?>" value="<?php echo $product ? $product['p_moq'] : 1; ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="notes">Notes / Special Logistical Requirements / Site Unloading Instructions *</label>
                            <textarea class="form-control" name="notes" rows="5" placeholder="State your requirements (e.g. need flatbed delivery, crane lift required, specific material grades, desired target unit pricing)..." required></textarea>
                        </div>

                        <button type="submit" class="btn btn-warning btn-block" name="form_rfq" style="background-color: #F59E0B; border-color: #F59E0B; font-weight: bold; font-size: 16px;">Submit B2B RFQ</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once('footer.php'); ?>
