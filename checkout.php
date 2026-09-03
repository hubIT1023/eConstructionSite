<?php require_once('header.php'); ?>

<?php
$statement = $pdo->prepare("SELECT * FROM tbl_settings WHERE id=1");
$statement->execute();
$result = $statement->fetchAll(PDO::FETCH_ASSOC);                            
foreach ($result as $row) {
    $banner_checkout = $row['banner_checkout'];
}
?>

<?php
if(!isset($_SESSION['cart_p_id'])) {
    header('location: cart.php');
    exit;
}
?>

<div class="page-banner" style="background-image: url(assets/uploads/<?php echo $banner_checkout; ?>)">
    <div class="overlay"></div>
    <div class="page-banner-inner">
        <h1><?php echo LANG_VALUE_22; ?></h1>
    </div>
</div>

<div class="page">
    <div class="container">
        <div class="row">
            <div class="col-md-12">
                
                <?php if(!isset($_SESSION['customer'])): ?>
                    <p>
                        <a href="login.php" class="btn btn-md btn-danger"><?php echo LANG_VALUE_160; ?></a>
                    </p>
                <?php else: ?>

                <h3 class="special"><?php echo LANG_VALUE_26; ?></h3>
                <div class="cart">
                    <table class="table table-responsive table-hover table-bordered">
                        <tr>
                            <th><?php echo '#' ?></th>
                            <th><?php echo LANG_VALUE_8; ?></th>
                            <th><?php echo LANG_VALUE_47; ?></th>
                            <th><?php echo LANG_VALUE_157; ?></th>
                            <th><?php echo LANG_VALUE_158; ?></th>
                            <th><?php echo LANG_VALUE_159; ?></th>
                            <th><?php echo LANG_VALUE_55; ?></th>
                            <th class="text-right"><?php echo LANG_VALUE_82; ?></th>
                        </tr>
                         <?php
                        $table_total_price = 0;

                        $i=0;
                        foreach($_SESSION['cart_p_id'] as $key => $value) 
                        {
                            $i++;
                            $arr_cart_p_id[$i] = $value;
                        }

                        $i=0;
                        foreach($_SESSION['cart_size_id'] as $key => $value) 
                        {
                            $i++;
                            $arr_cart_size_id[$i] = $value;
                        }

                        $i=0;
                        foreach($_SESSION['cart_size_name'] as $key => $value) 
                        {
                            $i++;
                            $arr_cart_size_name[$i] = $value;
                        }

                        $i=0;
                        foreach($_SESSION['cart_color_id'] as $key => $value) 
                        {
                            $i++;
                            $arr_cart_color_id[$i] = $value;
                        }

                        $i=0;
                        foreach($_SESSION['cart_color_name'] as $key => $value) 
                        {
                            $i++;
                            $arr_cart_color_name[$i] = $value;
                        }

                        $i=0;
                        foreach($_SESSION['cart_p_qty'] as $key => $value) 
                        {
                            $i++;
                            $arr_cart_p_qty[$i] = $value;
                        }

                        $i=0;
                        foreach($_SESSION['cart_p_current_price'] as $key => $value) 
                        {
                            $i++;
                            $arr_cart_p_current_price[$i] = $value;
                        }

                        $i=0;
                        foreach($_SESSION['cart_p_name'] as $key => $value) 
                        {
                            $i++;
                            $arr_cart_p_name[$i] = $value;
                        }

                        $i=0;
                        foreach($_SESSION['cart_p_featured_photo'] as $key => $value) 
                        {
                            $i++;
                            $arr_cart_p_featured_photo[$i] = $value;
                        }
                        ?>
                        <?php for($i=1;$i<=count($arr_cart_p_id);$i++): ?>
                        <tr>
                            <td><?php echo $i; ?></td>
                            <td>
                                <img src="assets/uploads/<?php echo $arr_cart_p_featured_photo[$i]; ?>" alt="">
                            </td>
                            <td><?php echo $arr_cart_p_name[$i]; ?></td>
                            <td><?php echo $arr_cart_size_name[$i]; ?></td>
                            <td><?php echo $arr_cart_color_name[$i]; ?></td>
                            <td><?php echo LANG_VALUE_1; ?><?php echo $arr_cart_p_current_price[$i]; ?></td>
                            <td><?php echo $arr_cart_p_qty[$i]; ?></td>
                            <td class="text-right">
                                <?php
                                $row_total_price = $arr_cart_p_current_price[$i]*$arr_cart_p_qty[$i];
                                $table_total_price = $table_total_price + $row_total_price;
                                ?>
                                <?php echo LANG_VALUE_1; ?><?php echo $row_total_price; ?>
                            </td>
                        </tr>
                        <?php endfor; ?>           
                        <tr>
                            <th colspan="7" class="total-text"><?php echo LANG_VALUE_81; ?></th>
                            <th class="total-amount"><?php echo LANG_VALUE_1; ?><?php echo $table_total_price; ?></th>
                        </tr>
                        <?php
                        // Resolve customer's delivery Barangay
                        $shipping_brgy = '';
                        if (!empty($_SESSION['customer']['cust_s_state'])) {
                            $shipping_brgy = $_SESSION['customer']['cust_s_state'];
                        } elseif (!empty($_SESSION['customer']['cust_state'])) {
                            $shipping_brgy = $_SESSION['customer']['cust_state'];
                        } elseif (!empty($_SESSION['customer']['cust_b_state'])) {
                            $shipping_brgy = $_SESSION['customer']['cust_b_state'];
                        }

                        $target_brgy_id = 0;
                        $target_brgy_name = '';

                        if (is_numeric($shipping_brgy) && (int)$shipping_brgy > 0) {
                            $target_brgy_id = (int)$shipping_brgy;
                            $stmt_b = $pdo->prepare("SELECT brgy_name FROM tbl_brgy WHERE brgy_id = ?");
                            $stmt_b->execute(array($target_brgy_id));
                            $row_b = $stmt_b->fetch(PDO::FETCH_ASSOC);
                            if ($row_b) {
                                $target_brgy_name = $row_b['brgy_name'];
                            }
                        } elseif (!empty($shipping_brgy)) {
                            $stmt_b = $pdo->prepare("SELECT brgy_id, brgy_name FROM tbl_brgy WHERE LOWER(TRIM(brgy_name)) = LOWER(TRIM(?))");
                            $stmt_b->execute(array(trim($shipping_brgy)));
                            $row_b = $stmt_b->fetch(PDO::FETCH_ASSOC);
                            if ($row_b) {
                                $target_brgy_id = (int)$row_b['brgy_id'];
                                $target_brgy_name = $row_b['brgy_name'];
                            } else {
                                $target_brgy_name = $shipping_brgy;
                            }
                        }

                        // Collect unique supplier IDs for all items in the cart
                        $cart_supplier_ids = [];
                        for ($m = 1; $m <= count($arr_cart_p_id); $m++) {
                            $p_id = $arr_cart_p_id[$m];
                            $statement_sup = $pdo->prepare("SELECT supplier_id FROM tbl_product WHERE p_id = ?");
                            $statement_sup->execute(array($p_id));
                            $p_row = $statement_sup->fetch(PDO::FETCH_ASSOC);
                            if ($p_row && !in_array($p_row['supplier_id'], $cart_supplier_ids)) {
                                $cart_supplier_ids[] = $p_row['supplier_id'];
                            }
                        }
                        if (empty($cart_supplier_ids)) {
                            $cart_supplier_ids = [1];
                        }

                        // Calculate total delivery cost across suppliers
                        $shipping_cost = 0;
                        foreach ($cart_supplier_ids as $sup_id) {
                            $sup_shipping = null;

                            // 1. Check supplier-specific cost for this Barangay
                            if ($target_brgy_id > 0) {
                                $stmt_sc = $pdo->prepare("SELECT amount FROM tbl_shipping_cost WHERE country_id = ? AND supplier_id = ?");
                                $stmt_sc->execute(array($target_brgy_id, $sup_id));
                                $sc_row = $stmt_sc->fetch(PDO::FETCH_ASSOC);
                                if ($sc_row) {
                                    $sup_shipping = (float)$sc_row['amount'];
                                }
                            }

                            // 2. Check general shipping cost for this Barangay
                            if ($sup_shipping === null && $target_brgy_id > 0) {
                                $stmt_sc = $pdo->prepare("SELECT amount FROM tbl_shipping_cost WHERE country_id = ?");
                                $stmt_sc->execute(array($target_brgy_id));
                                $sc_row = $stmt_sc->fetch(PDO::FETCH_ASSOC);
                                if ($sc_row) {
                                    $sup_shipping = (float)$sc_row['amount'];
                                }
                            }

                            // 3. Fallback: Default delivery cost (All the Towns)
                            if ($sup_shipping === null) {
                                $stmt_all = $pdo->prepare("SELECT amount FROM tbl_shipping_cost_all WHERE sca_id = 1");
                                $stmt_all->execute();
                                $all_row = $stmt_all->fetch(PDO::FETCH_ASSOC);
                                if ($all_row) {
                                    $sup_shipping = (float)$all_row['amount'];
                                } else {
                                    $sup_shipping = 0;
                                }
                            }

                            $shipping_cost += $sup_shipping;
                        }
                        ?>
                        <tr>
                            <td colspan="7" class="total-text" style="vertical-align: middle;">
                                <div style="display: flex; flex-direction: column; gap: 6px;">
                                    <div style="font-weight: bold;">
                                        <?php echo LANG_VALUE_84; ?>:
                                        <?php if (!empty($target_brgy_name)): ?>
                                            <span style="font-size:13px;font-weight:normal;color:#475569;">(Location: <?php echo htmlspecialchars($target_brgy_name); ?>)</span>
                                        <?php endif; ?>
                                    </div>
                                    <div style="display: flex; flex-wrap: wrap; gap: 15px; font-weight: normal; font-size: 14px; margin-top: 4px;">
                                        <label style="cursor: pointer; margin-bottom: 0; display: inline-flex; align-items: center; gap: 6px;">
                                            <input type="radio" name="global_delivery_option" value="include" checked onchange="updateDeliveryOption()">
                                            <span>Deliver to Address (<strong>&#8369;<?php echo number_format($shipping_cost, 2); ?></strong>)</span>
                                        </label>
                                        <label style="cursor: pointer; margin-bottom: 0; display: inline-flex; align-items: center; gap: 6px;">
                                            <input type="radio" name="global_delivery_option" value="exclude" onchange="updateDeliveryOption()">
                                            <span>Store Pick-up (<strong>No Delivery Fee - &#8369;0.00</strong>)</span>
                                        </label>
                                    </div>
                                </div>
                            </td>
                            <td class="total-amount" style="vertical-align: middle;">
                                <span id="order_delivery_display">&#8369;<?php echo number_format($shipping_cost, 2); ?></span>
                            </td>
                        </tr>
                        <tr>
                            <th colspan="7" class="total-text"><?php echo LANG_VALUE_82; ?></th>
                            <th class="total-amount">
                                <?php
                                $final_total = $table_total_price + $shipping_cost;
                                ?>
                                <span id="order_final_total_display">&#8369;<?php echo number_format($final_total, 2); ?></span>
                            </th>
                        </tr>
                    </table> 
                </div>

                

                <div class="billing-address">
                    <div class="row">
                        <div class="col-md-6">
                            <h3 class="special"><?php echo LANG_VALUE_161; ?></h3>
                            <table class="table table-responsive table-bordered table-hover table-striped bill-address">
                                <tr>
                                    <td><?php echo LANG_VALUE_102; ?></td>
                                    <td><?php echo $_SESSION['customer']['cust_b_name']; ?></p></td>
                                </tr>
                                <tr>
                                    <td><?php echo LANG_VALUE_103; ?></td>
                                    <td><?php echo $_SESSION['customer']['cust_b_cname']; ?></td>
                                </tr>
                                <tr>
                                    <td><?php echo LANG_VALUE_104; ?></td>
                                    <td><?php echo $_SESSION['customer']['cust_b_phone']; ?></td>
                                </tr>
                                <tr>
                                    <td>Town/City</td>
                                    <td>
                                        <?php
                                        $statement = $pdo->prepare("SELECT * FROM tbl_town WHERE town_id=?");
                                        $statement->execute(array($_SESSION['customer']['cust_b_country']));
                                        $result = $statement->fetchAll(PDO::FETCH_ASSOC);
                                        foreach ($result as $row) {
                                            echo $row['town_name'];
                                        }
                                        ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td>Barangay</td>
                                    <td>
                                        <?php echo isset($_SESSION['customer']['cust_b_state']) ? $_SESSION['customer']['cust_b_state'] : ''; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td><?php echo LANG_VALUE_105; ?></td>
                                    <td>
                                        <?php echo nl2br($_SESSION['customer']['cust_b_address']); ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td><?php echo LANG_VALUE_109; ?></td>
                                    <td><?php echo $_SESSION['customer']['cust_b_zip']; ?></td>
                                </tr>                                
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h3 class="special"><?php echo LANG_VALUE_162; ?></h3>
                            <table class="table table-responsive table-bordered table-hover table-striped bill-address">
                                <tr>
                                    <td><?php echo LANG_VALUE_102; ?></td>
                                    <td><?php echo $_SESSION['customer']['cust_s_name']; ?></p></td>
                                </tr>
                                <tr>
                                    <td><?php echo LANG_VALUE_103; ?></td>
                                    <td><?php echo $_SESSION['customer']['cust_s_cname']; ?></td>
                                </tr>
                                <tr>
                                    <td><?php echo LANG_VALUE_104; ?></td>
                                    <td><?php echo $_SESSION['customer']['cust_s_phone']; ?></td>
                                </tr>
                                <tr>
                                    <td>Town/City</td>
                                    <td>
                                        <?php
                                        $statement = $pdo->prepare("SELECT * FROM tbl_town WHERE town_id=?");
                                        $statement->execute(array($_SESSION['customer']['cust_s_country']));
                                        $result = $statement->fetchAll(PDO::FETCH_ASSOC);
                                        foreach ($result as $row) {
                                            echo $row['town_name'];
                                        }
                                        ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td>Barangay</td>
                                    <td>
                                        <?php echo isset($_SESSION['customer']['cust_s_state']) ? $_SESSION['customer']['cust_s_state'] : ''; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td><?php echo LANG_VALUE_105; ?></td>
                                    <td>
                                        <?php echo nl2br($_SESSION['customer']['cust_s_address']); ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td><?php echo LANG_VALUE_109; ?></td>
                                    <td><?php echo $_SESSION['customer']['cust_s_zip']; ?></td>
                                </tr> 
                            </table>
                        </div>
                    </div>                    
                </div>

                

                <div class="cart-buttons">
                    <ul>
                        <li><a href="cart.php" class="btn btn-primary"><?php echo LANG_VALUE_21; ?></a></li>
                    </ul>
                </div>

				<div class="clear"></div>
                <h3 class="special"><?php echo LANG_VALUE_33; ?></h3>
                <div class="row">
                    
                    	<?php
		                $checkout_access = 1;
		                if(
		                    ($_SESSION['customer']['cust_b_name']=='') ||
		                    ($_SESSION['customer']['cust_b_cname']=='') ||
		                    ($_SESSION['customer']['cust_b_phone']=='') ||
		                    ($_SESSION['customer']['cust_b_country']=='') || ($_SESSION['customer']['cust_b_country']=='0') ||
		                    ($_SESSION['customer']['cust_b_address']=='') ||
		                    ($_SESSION['customer']['cust_b_zip']=='') ||
		                    ($_SESSION['customer']['cust_s_name']=='') ||
		                    ($_SESSION['customer']['cust_s_cname']=='') ||
		                    ($_SESSION['customer']['cust_s_phone']=='') ||
		                    ($_SESSION['customer']['cust_s_country']=='') || ($_SESSION['customer']['cust_s_country']=='0') ||
		                    ($_SESSION['customer']['cust_s_address']=='') ||
		                    ($_SESSION['customer']['cust_s_zip']=='')
		                ) {
		                    $checkout_access = 0;
		                }
		                ?>
		                <?php if($checkout_access == 0): ?>
		                	<div class="col-md-12">
				                <div style="color:red;font-size:22px;margin-bottom:50px;">
			                        You must have to fill up all the billing and shipping information from your dashboard panel in order to checkout the order. Please fill up the information to <a href="customer-billing-shipping-update.php" style="color:#007bff;font-weight:bold;text-decoration:underline;">this LINK</a>.
			                    </div>
	                    	</div>
	                	<?php else: ?>
		                	<div class="col-md-4">
		                		
                                <?php
                                $cart_supplier_ids = [];
                                for($m=1; $m<=count($arr_cart_p_id); $m++) {
                                    $p_id = $arr_cart_p_id[$m];
                                    $statement_sup = $pdo->prepare("SELECT supplier_id FROM tbl_product WHERE p_id=?");
                                    $statement_sup->execute(array($p_id));
                                    $p_row = $statement_sup->fetch(PDO::FETCH_ASSOC);
                                    if ($p_row && !in_array($p_row['supplier_id'], $cart_supplier_ids)) {
                                        $cart_supplier_ids[] = $p_row['supplier_id'];
                                    }
                                }

                                $cart_suppliers = [];
                                foreach ($cart_supplier_ids as $sup_id) {
                                    $statement_sup2 = $pdo->prepare("SELECT supplier_name, supplier_address, supplier_phone FROM tbl_supplier WHERE supplier_id=?");
                                    $statement_sup2->execute(array($sup_id));
                                    $sup_row = $statement_sup2->fetch(PDO::FETCH_ASSOC);
                                    if ($sup_row) {
                                        $cart_suppliers[] = $sup_row;
                                    }
                                }
                                ?>
	                            <div class="row">

	                                <div class="col-md-12 form-group">
	                                    <label for=""><?php echo LANG_VALUE_34; ?> *</label>
	                                    <select name="payment_method" class="form-control select2" id="advFieldsStatus">
	                                        <option value=""><?php echo LANG_VALUE_35; ?></option>
	                                        <option value="PayPal"><?php echo LANG_VALUE_36; ?></option>
	                                        <option value="Bank Deposit"><?php echo LANG_VALUE_38; ?></option>
	                                        <option value="Over the Counter">Over the Counter</option>
	                                    </select>
	                                </div>

                                     <form class="paypal" action="<?php echo BASE_URL; ?>payment/paypal/payment_process.php" method="post" id="paypal_form" target="_blank">
                                         <input type="hidden" name="cmd" value="_xclick" />
                                         <input type="hidden" name="no_note" value="1" />
                                         <input type="hidden" name="lc" value="UK" />
                                         <input type="hidden" name="currency_code" value="USD" />
                                         <input type="hidden" name="bn" value="PP-BuyNowBF:btn_buynow_LG.gif:NonHostedGuest" />

                                         <input type="hidden" name="final_total" value="<?php echo $final_total; ?>">
                                         <div class="col-md-12 form-group">
                                             <input type="submit" class="btn btn-primary" value="<?php echo LANG_VALUE_46; ?>" name="form1">
                                         </div>
                                     </form>



                                     <form action="payment/bank/init.php" method="post" id="bank_form">
                                         <input type="hidden" name="amount" value="<?php echo $final_total; ?>">
                                         <div class="col-md-12 form-group">
                                             <label for=""><?php echo LANG_VALUE_43; ?></span></label><br>
                                             <?php
                                             $statement = $pdo->prepare("SELECT * FROM tbl_settings WHERE id=1");
                                             $statement->execute();
                                             $result = $statement->fetchAll(PDO::FETCH_ASSOC);
                                             foreach ($result as $row) {
                                                 echo nl2br($row['bank_detail']);
                                             }
                                             ?>
                                         </div>
                                         <div class="col-md-12 form-group">
                                             <label for=""><?php echo LANG_VALUE_44; ?> <br><span style="font-size:12px;font-weight:normal;">(<?php echo LANG_VALUE_45; ?>)</span></label>
                                             <textarea name="transaction_info" class="form-control" cols="30" rows="10"></textarea>
                                         </div>
                                         <div class="col-md-12 form-group">
                                             <input type="submit" class="btn btn-primary" value="<?php echo LANG_VALUE_46; ?>" name="form3">
                                         </div>
                                     </form>

                                     <form action="payment/otc/init.php" method="post" id="otc_form">
                                         <input type="hidden" name="amount" id="otc_amount" value="<?php echo $final_total; ?>">
                                         <input type="hidden" name="delivery_cost" id="otc_delivery_cost" value="<?php echo $shipping_cost; ?>">
                                         <input type="hidden" name="otc_delivery_option" id="otc_delivery_option" value="include">

                                         <div class="col-md-12 form-group" style="padding-top: 10px;">
                                             <label for=""><strong>Over the Counter Payment:</strong></label><br>
                                             <p style="margin-top: 5px; color: #555;">Please present this Purchase Order upon payment and pickup at the store address:</p>
                                             <?php foreach ($cart_suppliers as $sup): ?>
                                                 <div style="border: 1px solid #e2e8f0; padding: 10px; margin-bottom: 10px; border-radius: 4px; background: #fafafa;">
                                                     <strong>Store / Supplier:</strong> <?php echo htmlspecialchars($sup['supplier_name']); ?><br>
                                                     <strong>Address:</strong> <?php echo nl2br(htmlspecialchars($sup['supplier_address'])); ?><br>
                                                     <strong>Phone:</strong> <?php echo htmlspecialchars($sup['supplier_phone']); ?>
                                                 </div>
                                             <?php endforeach; ?>
                                         </div>

                                         <div class="col-md-12 form-group" style="margin-top: 10px;">
                                             <input type="submit" class="btn btn-primary" value="Send Purchase Order" name="form_otc" style="background-color: #0284c7; border-color: #0284c7; font-size: 16px; padding: 10px 20px;">
                                         </div>
                                     </form>

                                     <script>
                                     function updateDeliveryOption() {
                                         var subtotal = <?php echo (float)$table_total_price; ?>;
                                         var shipping = <?php echo (float)$shipping_cost; ?>;
                                         var selected = document.querySelector('input[name="global_delivery_option"]:checked').value;
                                         var currentDelivery = (selected === 'include') ? shipping : 0;
                                         var finalTotal = subtotal + currentDelivery;

                                         // Update Table Displays
                                         document.getElementById('order_delivery_display').innerHTML = '&#8369;' + currentDelivery.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                                         document.getElementById('order_final_total_display').innerHTML = '&#8369;' + finalTotal.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});

                                         // Sync with PayPal form
                                         var paypalAmt = document.querySelector('#paypal_form input[name="final_total"]');
                                         if (paypalAmt) paypalAmt.value = finalTotal;

                                         // Sync with Bank form
                                         var bankAmt = document.querySelector('#bank_form input[name="amount"]');
                                         if (bankAmt) bankAmt.value = finalTotal;

                                         // Sync with OTC form
                                         var otcAmt = document.getElementById('otc_amount');
                                         if (otcAmt) otcAmt.value = finalTotal;
                                         var otcDel = document.getElementById('otc_delivery_cost');
                                         if (otcDel) otcDel.value = currentDelivery;
                                         var otcOpt = document.getElementById('otc_delivery_option');
                                         if (otcOpt) otcOpt.value = selected;
                                     }
                                     </script>
	                                
	                            </div>
		                            
		                        
		                    </div>
		                <?php endif; ?>
                        
                </div>
                

                <?php endif; ?>

            </div>
        </div>
    </div>
</div>


<?php require_once('footer.php'); ?>