<?php require_once('header.php'); ?>

<?php
$error_message = '';
if(isset($_POST['form1'])) {
    $valid = 1;
    if(empty($_POST['subject_text'])) {
        $valid = 0;
        $error_message .= 'Subject can not be empty\n';
    }
    if(empty($_POST['message_text'])) {
        $valid = 0;
        $error_message .= 'Subject can not be empty\n';
    }
    if($valid == 1) {

        $subject_text = strip_tags($_POST['subject_text']);
        $message_text = strip_tags($_POST['message_text']);

        // Getting Customer Email Address
        $statement = $pdo->prepare("SELECT * FROM tbl_customer WHERE cust_id=?");
        $statement->execute(array($_POST['cust_id']));
        $result = $statement->fetchAll(PDO::FETCH_ASSOC);                            
        foreach ($result as $row) {
            $cust_email = $row['cust_email'];
        }

        // Getting Admin Email Address
        $statement = $pdo->prepare("SELECT * FROM tbl_settings WHERE id=1");
        $statement->execute();
        $result = $statement->fetchAll(PDO::FETCH_ASSOC);                            
        foreach ($result as $row) {
            $admin_email = $row['contact_email'];
        }

        $order_detail = '';
        $statement = $pdo->prepare("SELECT * FROM tbl_payment WHERE payment_id=?");
        $statement->execute(array($_POST['payment_id']));
        $result = $statement->fetchAll(PDO::FETCH_ASSOC);                            
        foreach ($result as $row) {
        	$payment_details = '';
        	if($row['payment_method'] == 'PayPal'):
        		$payment_details = '
Transaction Id: '.$row['txnid'].'<br>
        		';
        	elseif($row['payment_method'] == 'Stripe'):
				$payment_details = '
Transaction Id: '.$row['txnid'].'<br>
Card number: '.$row['card_number'].'<br>
Card CVV: '.$row['card_cvv'].'<br>
Card Month: '.$row['card_month'].'<br>
Card Year: '.$row['card_year'].'<br>
        		';
        	elseif($row['payment_method'] == 'Bank Deposit'):
				$payment_details = '
Transaction Details: <br>'.$row['bank_transaction_info'];
        	elseif($row['payment_method'] == 'Over the Counter'):
				$payment_details = '
Transaction Details: Over the Counter Payment';
        	endif;

            $order_detail .= '
Customer Name: '.$row['customer_name'].'<br>
Customer Email: '.$row['customer_email'].'<br>
Payment Method: '.$row['payment_method'].'<br>
Payment Date: '.$row['payment_date'].'<br>
Payment Details: <br>'.$payment_details.'<br>
Paid Amount: '.$row['paid_amount'].'<br>
Payment Status: '.$row['payment_status'].'<br>
Shipping Status: '.$row['shipping_status'].'<br>
Payment Id: '.$row['payment_id'].'<br>
            ';
        }

        $i=0;
        $statement = $pdo->prepare("SELECT * FROM tbl_order WHERE payment_id=?");
        $statement->execute(array($_POST['payment_id']));
        $result = $statement->fetchAll(PDO::FETCH_ASSOC);                            
        foreach ($result as $row) {
            $i++;
            $order_detail .= '
<br><b><u>Product Item '.$i.'</u></b><br>
Product Name: '.$row['product_name'].'<br>
Size: '.$row['size'].'<br>
Color: '.$row['color'].'<br>
Quantity: '.$row['quantity'].'<br>
Unit Price: '.$row['unit_price'].'<br>
            ';
        }

        $statement = $pdo->prepare("INSERT INTO tbl_customer_message (subject,message,order_detail,cust_id) VALUES (?,?,?,?)");
        $statement->execute(array($subject_text,$message_text,$order_detail,$_POST['cust_id']));

        // sending email
        $to_customer = $cust_email;
        $message = '
<html><body>
<h3>Message: </h3>
'.$message_text.'
<h3>Order Details: </h3>
'.$order_detail.'
</body></html>
';
        $headers = 'From: ' . $admin_email . "\r\n" .
                   'Reply-To: ' . $admin_email . "\r\n" .
                   'X-Mailer: PHP/' . phpversion() . "\r\n" . 
                   "MIME-Version: 1.0\r\n" . 
                   "Content-Type: text/html; charset=ISO-8859-1\r\n";

        // Sending email to admin                  
        mail($to_customer, $subject_text, $message, $headers);
        
        $success_message = 'Your email to customer is sent successfully.';

    }
}
?>
<?php
if($error_message != '') {
    echo "<script>alert('".$error_message."')</script>";
}
if($success_message != '') {
    echo "<script>alert('".$success_message."')</script>";
}
?>

<section class="content-header">
	<div class="content-header-left">
		<h1>View Orders</h1>
	</div>
</section>


<section class="content">

  <div class="row">
    <div class="col-md-12">


      <div class="box box-info">
        
        <div class="box-body table-responsive">
          <table id="example1" class="table table-bordered table-hover table-striped">
			<thead>
			    <tr>
			        <th>#</th>
                    <th>Customer</th>
			        <th>Product Details</th>
                    <th>
                    	Payment Information
                    </th>
                    <th>Paid Amount</th>
                    <th>Payment Status</th>
                    <th>Shipping Status</th>
			        <th>Action</th>
			    </tr>
			</thead>
            <tbody>
            	<?php
            	$i=0;
            	$statement = $pdo->prepare("SELECT * FROM tbl_payment WHERE supplier_id=? ORDER by id DESC");
            	$statement->execute(array($supplier_id));
            	$result = $statement->fetchAll(PDO::FETCH_ASSOC);							
            	foreach ($result as $row) {
            		$i++;
            		?>
					<tr class="<?php if($row['payment_status']=='Pending' || $row['payment_status']=='Awaiting for Payment'){echo 'bg-r';}else{echo 'bg-g';} ?>">
	                    <td><?php echo $i; ?></td>
	                    <td>
                            <b>Id:</b> <?php echo $row['customer_id']; ?><br>
                            <b>Name:</b><br> <?php echo $row['customer_name']; ?><br>
                            <b>Email:</b><br> <?php echo $row['customer_email']; ?><br><br>
                            <a href="#" data-toggle="modal" data-target="#model-<?php echo $i; ?>"class="btn btn-warning btn-xs" style="width:100%;margin-bottom:4px;">Send Message</a>
                            <div id="model-<?php echo $i; ?>" class="modal fade" role="dialog">
								<div class="modal-dialog">
									<div class="modal-content">
										<div class="modal-header">
											<button type="button" class="close" data-dismiss="modal">&times;</button>
											<h4 class="modal-title" style="font-weight: bold;">Send Message</h4>
										</div>
										<div class="modal-body" style="font-size: 14px">
											<form action="" method="post">
                                                <input type="hidden" name="cust_id" value="<?php echo $row['customer_id']; ?>">
                                                <input type="hidden" name="payment_id" value="<?php echo $row['payment_id']; ?>">
												<table class="table table-bordered">
													<tr>
														<td>Subject</td>
														<td>
                                                            <input type="text" name="subject_text" class="form-control" style="width: 100%;">
														</td>
													</tr>
                                                    <tr>
                                                        <td>Message</td>
                                                        <td>
                                                            <textarea name="message_text" class="form-control" cols="30" rows="10" style="width:100%;height: 200px;"></textarea>
                                                        </td>
                                                    </tr>
													<tr>
														<td></td>
														<td><input type="submit" value="Send Message" name="form1"></td>
													</tr>
												</table>
											</form>
										</div>
										<div class="modal-footer">
											<button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
										</div>
									</div>
								</div>
							</div>
                        </td>
                        <td>
                           <?php
                           $statement1 = $pdo->prepare("SELECT * FROM tbl_order WHERE payment_id=?");
                           $statement1->execute(array($row['payment_id']));
                           $result1 = $statement1->fetchAll(PDO::FETCH_ASSOC);
                           foreach ($result1 as $row1) {
                                echo '<b>Product:</b> '.$row1['product_name'];
                                echo '<br>(<b>Size:</b> '.$row1['size'];
                                echo ', <b>Color:</b> '.$row1['color'].')';
                                echo '<br>(<b>Quantity:</b> '.$row1['quantity'];
                                echo ', <b>Unit Price:</b> '.$row1['unit_price'].')';
                                echo '<br><br>';
                           }
                           ?>
                        </td>
                        <td>
                        	<?php if($row['payment_method'] == 'PayPal'): ?>
                        		<b>Payment Method:</b> <?php echo '<span style="color:red;"><b>'.$row['payment_method'].'</b></span>'; ?><br>
                        		<b>Payment Id:</b> <?php echo $row['payment_id']; ?><br>
                        		<b>Date:</b> <?php echo $row['payment_date']; ?><br>
                        		<b>Transaction Id:</b> <?php echo $row['txnid']; ?><br>
                        	<?php elseif($row['payment_method'] == 'Stripe'): ?>
                        		<b>Payment Method:</b> <?php echo '<span style="color:red;"><b>'.$row['payment_method'].'</b></span>'; ?><br>
                        		<b>Payment Id:</b> <?php echo $row['payment_id']; ?><br>
								<b>Date:</b> <?php echo $row['payment_date']; ?><br>
                        		<b>Transaction Id:</b> <?php echo $row['txnid']; ?><br>
                        		<b>Card Number:</b> <?php echo $row['card_number']; ?><br>
                        		<b>Card CVV:</b> <?php echo $row['card_cvv']; ?><br>
                        		<b>Expire Month:</b> <?php echo $row['card_month']; ?><br>
                        		<b>Expire Year:</b> <?php echo $row['card_year']; ?><br>
                        	<?php elseif($row['payment_method'] == 'Bank Deposit'): ?>
                        		<b>Payment Method:</b> <?php echo '<span style="color:red;"><b>'.$row['payment_method'].'</b></span>'; ?><br>
                        		<b>Payment Id:</b> <?php echo $row['payment_id']; ?><br>
								<b>Date:</b> <?php echo $row['payment_date']; ?><br>
                        		<b>Transaction Information:</b> <br><?php echo $row['bank_transaction_info']; ?><br>
                        	<?php endif; ?>
                        </td>
                        <td>&#8369;<?php echo $row['paid_amount']; ?></td>
                        <td>
                            <?php echo $row['payment_status']; ?>
                            <br><br>
                             <?php
                                 if($row['payment_status']=='Pending'){
                                     ?>
                                     <a href="order-change-status.php?id=<?php echo $row['id']; ?>&task=Completed" class="btn btn-success btn-xs" style="width:100%;margin-bottom:4px;">Mark Complete</a>
                                     <?php
                                 } elseif($row['payment_status']=='Awaiting for Payment'){
                                     ?>
                                     <a href="order-change-status.php?id=<?php echo $row['id']; ?>&task=Paid" class="btn btn-success btn-xs" style="width:100%;margin-bottom:4px;">Paid</a>
                                     <?php
                                 } elseif($row['payment_status']=='Paid'){
                                     ?>
                                     <a href="#" data-toggle="modal" data-target="#receipt-modal-<?php echo $row['id']; ?>" class="btn btn-primary btn-xs" style="width:100%;margin-bottom:4px;">View Receipt</a>
                                     
                                     <!-- Modal for View Receipt -->
                                     <div id="receipt-modal-<?php echo $row['id']; ?>" class="modal fade" role="dialog">
                                         <div class="modal-dialog modal-md">
                                             <div class="modal-content">
                                                 <div class="modal-header" style="text-align: left;">
                                                     <button type="button" class="close" data-dismiss="modal">&times;</button>
                                                     <h4 class="modal-title" style="font-weight: bold;">Official Receipt</h4>
                                                 </div>
                                                 <div class="modal-body" id="receipt-print-area-<?php echo $row['id']; ?>" style="text-align: left;">
                                                     <div style="font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; padding: 20px; color: #333; background: #fff; border: 1px solid #eee;">
                                                         <!-- Header -->
                                                         <div style="text-align: center; margin-bottom: 20px;">
                                                             <h2 style="margin: 0; color: #337ab7; font-weight: bold; letter-spacing: 1px;">E-CONSTRUCTION SUPPLY</h2>
                                                             <p style="margin: 5px 0 0 0; font-size: 14px; color: #777;">Online B2B Marketplace</p>
                                                             <hr style="margin: 15px 0; border: 0; border-top: 2px dashed #ddd;">
                                                             <h3 style="margin: 0; font-size: 18px; font-weight: bold; color: #555; text-transform: uppercase;">Official Sales Receipt</h3>
                                                         </div>

                                                         <!-- Details Box -->
                                                         <table style="width: 100%; margin-bottom: 20px;">
                                                             <tr>
                                                                 <td style="width: 50%; vertical-align: top; text-align: left;">
                                                                     <h5 style="margin: 0 0 5px 0; font-weight: bold; color: #777; text-transform: uppercase; font-size: 10px;">Supplier / Store:</h5>
                                                                     <p style="margin: 0; font-size: 13px; font-weight: bold;">
                                                                         <?php
                                                                         $statement_s = $pdo->prepare("SELECT * FROM tbl_supplier WHERE supplier_id=?");
                                                                         $statement_s->execute(array($row['supplier_id']));
                                                                         $sup_data = $statement_s->fetch(PDO::FETCH_ASSOC);
                                                                         echo htmlspecialchars($sup_data['supplier_name']);
                                                                         ?>
                                                                     </p>
                                                                     <p style="margin: 3px 0 0 0; font-size: 12px; color: #555; line-height: 1.4;">
                                                                         <?php echo nl2br(htmlspecialchars($sup_data['supplier_address'])); ?><br>
                                                                         Phone: <?php echo htmlspecialchars($sup_data['supplier_phone']); ?>
                                                                     </p>
                                                                 </td>
                                                                 <td style="width: 50%; vertical-align: top; text-align: right;">
                                                                     <h5 style="margin: 0 0 5px 0; font-weight: bold; color: #777; text-transform: uppercase; font-size: 10px;">Receipt Info:</h5>
                                                                     <p style="margin: 0; font-size: 12px;"><strong>Receipt #:</strong> <?php echo htmlspecialchars($row['payment_id']); ?></p>
                                                                     <p style="margin: 3px 0 0 0; font-size: 12px;"><strong>Date:</strong> <?php echo htmlspecialchars($row['payment_date']); ?></p>
                                                                     <p style="margin: 3px 0 0 0; font-size: 12px;"><strong>Payment:</strong> Over the Counter</p>
                                                                     <p style="margin: 3px 0 0 0; font-size: 12px;"><strong>Status:</strong> <span class="label label-success" style="font-size: 10px; padding: 2px 6px;">PAID</span></p>
                                                                 </td>
                                                             </tr>
                                                         </table>

                                                         <hr style="margin: 15px 0; border: 0; border-top: 1px solid #eee;">

                                                         <!-- Bill To Box -->
                                                         <div style="margin-bottom: 20px; text-align: left;">
                                                             <h5 style="margin: 0 0 5px 0; font-weight: bold; color: #777; text-transform: uppercase; font-size: 10px;">Billed To (Customer):</h5>
                                                             <p style="margin: 0; font-size: 13px; font-weight: bold;"><?php echo htmlspecialchars($row['customer_name']); ?></p>
                                                             <p style="margin: 3px 0 0 0; font-size: 12px; color: #555;">Email: <?php echo htmlspecialchars($row['customer_email']); ?></p>
                                                         </div>

                                                         <!-- Items Table -->
                                                         <table class="table table-condensed" style="margin-bottom: 20px; font-size: 13px; width: 100%;">
                                                             <thead>
                                                                 <tr style="background: #f9f9f9;">
                                                                     <th style="border-bottom: 2px solid #ddd; font-weight: bold; text-align: left;">Product Description</th>
                                                                     <th style="border-bottom: 2px solid #ddd; text-align: center; font-weight: bold;">Qty</th>
                                                                     <th style="border-bottom: 2px solid #ddd; text-align: right; font-weight: bold;">Unit Price</th>
                                                                     <th style="border-bottom: 2px solid #ddd; text-align: right; font-weight: bold;">Amount</th>
                                                                 </tr>
                                                             </thead>
                                                             <tbody>
                                                                 <?php
                                                                 $statement_o = $pdo->prepare("SELECT * FROM tbl_order WHERE payment_id=?");
                                                                 $statement_o->execute(array($row['payment_id']));
                                                                 $order_items = $statement_o->fetchAll(PDO::FETCH_ASSOC);
                                                                 foreach ($order_items as $item):
                                                                     $item_subtotal = $item['unit_price'] * $item['quantity'];
                                                                 ?>
                                                                 <tr>
                                                                     <td style="border-top: 1px solid #eee; padding: 8px 5px; text-align: left;">
                                                                         <strong><?php echo htmlspecialchars($item['product_name']); ?></strong>
                                                                         <?php if(!empty($item['size']) || !empty($item['color'])): ?>
                                                                             <br><span style="font-size: 11px; color: #777;">(Size: <?php echo htmlspecialchars($item['size']); ?>, Color: <?php echo htmlspecialchars($item['color']); ?>)</span>
                                                                         <?php endif; ?>
                                                                     </td>
                                                                     <td style="border-top: 1px solid #eee; text-align: center; padding: 8px 5px;"><?php echo htmlspecialchars($item['quantity']); ?></td>
                                                                     <td style="border-top: 1px solid #eee; text-align: right; padding: 8px 5px;">&#8369;<?php echo number_format($item['unit_price'], 2); ?></td>
                                                                     <td style="border-top: 1px solid #eee; text-align: right; padding: 8px 5px;">&#8369;<?php echo number_format($item_subtotal, 2); ?></td>
                                                                 </tr>
                                                                 <?php endforeach; ?>
                                                                 
                                                                 <!-- Totals -->
                                                                 <tr>
                                                                     <td colspan="2" style="border-top: 2px solid #ddd;"></td>
                                                                     <td style="border-top: 2px solid #ddd; text-align: right; font-weight: bold; padding: 8px 5px;">Subtotal:</td>
                                                                     <td style="border-top: 2px solid #ddd; text-align: right; font-weight: bold; padding: 8px 5px;">&#8369;<?php echo number_format($row['paid_amount'], 2); ?></td>
                                                                 </tr>
                                                                 <tr>
                                                                     <td colspan="2" style="border: none;"></td>
                                                                     <td style="border: none; text-align: right; font-weight: bold; padding: 4px 5px; color: #777;">Shipping:</td>
                                                                     <td style="border: none; text-align: right; font-weight: bold; padding: 4px 5px; color: #777;">&#8369;0.00</td>
                                                                 </tr>
                                                                 <tr style="font-size: 15px; background: #f5f5f5;">
                                                                     <td colspan="2" style="border-top: 1px solid #ddd;"></td>
                                                                     <td style="border-top: 1px solid #ddd; text-align: right; font-weight: bold; padding: 8px 5px; color: #337ab7;">Total Paid:</td>
                                                                     <td style="border-top: 1px solid #ddd; text-align: right; font-weight: bold; padding: 8px 5px; color: #337ab7;">&#8369;<?php echo number_format($row['paid_amount'], 2); ?></td>
                                                                 </tr>
                                                             </tbody>
                                                         </table>

                                                         <!-- Footer Message -->
                                                         <div style="text-align: center; margin-top: 30px; font-size: 12px; color: #999;">
                                                             <p style="margin: 0; font-weight: bold;">Thank you for your business!</p>
                                                             <p style="margin: 5px 0 0 0;">This is a system-generated official sales receipt.</p>
                                                         </div>
                                                     </div>
                                                 </div>
                                                 <div class="modal-footer">
                                                     <button type="button" class="btn btn-primary" onclick="printReceipt('receipt-print-area-<?php echo $row['id']; ?>')"><i class="fa fa-print"></i> Print Receipt</button>
                                                     <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                                                 </div>
                                             </div>
                                         </div>
                                     </div>
                                     <?php
                                 }
                             ?>
                         <td>
                             <?php echo $row['shipping_status']; ?>
                             <br><br>
                             <?php
                             if($row['payment_status']=='Completed' || $row['payment_status']=='Paid') {
                                 if($row['shipping_status']=='Pending'){
                                     ?>
                                     <a href="shipping-change-status.php?id=<?php echo $row['id']; ?>&task=Completed" class="btn btn-warning btn-xs" style="width:100%;margin-bottom:4px;">Mark Complete</a>
                                     <?php
                                 }
                             }
                             ?>
                        </td>
	                    <td>
                            <a href="#" class="btn btn-danger btn-xs" data-href="order-delete.php?id=<?php echo $row['id']; ?>" data-toggle="modal" data-target="#confirm-delete" style="width:100%;">Delete</a>
	                    </td>
	                </tr>
            		<?php
            	}
            	?>
            </tbody>
          </table>
        </div>
      </div>
  

</section>


<div class="modal fade" id="confirm-delete" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                <h4 class="modal-title" id="myModalLabel">Delete Confirmation</h4>
            </div>
            <div class="modal-body">
                Sure you want to delete this item?
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <a class="btn btn-danger btn-ok">Delete</a>
            </div>
        </div>
    </div>
</div>


<script>
function printReceipt(divId) {
    var printContents = document.getElementById(divId).innerHTML;
    var originalContents = document.body.innerHTML;
    document.body.innerHTML = printContents;
    window.print();
    document.body.innerHTML = originalContents;
    window.location.reload();
}
</script>

<?php require_once('footer.php'); ?>