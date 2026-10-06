<?php require_once('header.php'); ?>

<?php
$error_message = '';
$success_message = '';

// -------------------------------------------------------------
// 1. Handle Customer Direct Message Form Submission
// -------------------------------------------------------------
if(isset($_POST['form1'])) {
    $valid = 1;
    if(empty($_POST['subject_text'])) {
        $valid = 0;
        $error_message .= "Subject can not be empty\n";
    }
    if(empty($_POST['message_text'])) {
        $valid = 0;
        $error_message .= "Subject can not be empty\n";
    }
    if($valid == 1) {
        $subject_text = strip_tags($_POST['subject_text']);
        $message_text = strip_tags($_POST['message_text']);
        $post_cust_id = (int)$_POST['cust_id'];
        $post_payment_id = strip_tags($_POST['payment_id']);

        // Getting Customer Email Address
        $statement = $pdo->prepare("SELECT * FROM tbl_customer WHERE cust_id=?");
        $statement->execute(array($post_cust_id));
        $result = $statement->fetchAll(PDO::FETCH_ASSOC);                            
        $cust_email = '';
        foreach ($result as $row) {
            $cust_email = $row['cust_email'];
        }

        // Getting Admin Email Address
        $statement = $pdo->prepare("SELECT * FROM tbl_settings WHERE id=1");
        $statement->execute();
        $result = $statement->fetchAll(PDO::FETCH_ASSOC);                            
        $admin_email = '';
        foreach ($result as $row) {
            $admin_email = $row['contact_email'];
        }

        $order_detail = '';
        $statement = $pdo->prepare("SELECT * FROM tbl_payment WHERE payment_id=?");
        $statement->execute(array($post_payment_id));
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
        $statement->execute(array($post_payment_id));
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
        $statement->execute(array($subject_text,$message_text,$order_detail,$post_cust_id));

        // sending email
        if (!empty($cust_email)) {
            $to_customer = $cust_email;
            $message = '
<html><body>
<h3>Message: </h3>
<p>'.nl2br(htmlspecialchars($message_text)).'</p>
<hr>
<h3>Order Details: </h3>
<p>'.$order_detail.'</p>
</body></html>
';
            $headers = 'From: ' . $admin_email . "\r\n" .
                       'Reply-To: ' . $admin_email . "\r\n" .
                       'X-Mailer: PHP/' . phpversion() . "\r\n" . 
                       "MIME-Version: 1.0\r\n" . 
                       "Content-Type: text/html; charset=ISO-8859-1\r\n";

            @mail($to_customer, $subject_text, $message, $headers);
        }
        
        $success_message = 'Your email to customer is sent successfully.';
    }
}
?>

<?php
// Compute summary metrics for paid sales history
$stmt_metrics = $pdo->prepare("SELECT COUNT(*) as total_orders, SUM(paid_amount) as total_amount FROM tbl_payment WHERE supplier_id=? AND (payment_status = 'Paid' OR payment_status = 'Completed')");
$stmt_metrics->execute(array($supplier_id));
$metrics = $stmt_metrics->fetch(PDO::FETCH_ASSOC);
$total_paid_count = $metrics['total_orders'] ? (int)$metrics['total_orders'] : 0;
$total_revenue_val = $metrics['total_amount'] ? floatval($metrics['total_amount']) : 0.00;

$stmt_ship_pending = $pdo->prepare("SELECT COUNT(*) as total_pending FROM tbl_payment WHERE supplier_id=? AND (payment_status = 'Paid' OR payment_status = 'Completed') AND shipping_status='Pending'");
$stmt_ship_pending->execute(array($supplier_id));
$pending_ship_count = (int)$stmt_ship_pending->fetch(PDO::FETCH_ASSOC)['total_pending'];

$stmt_ship_complete = $pdo->prepare("SELECT COUNT(*) as total_complete FROM tbl_payment WHERE supplier_id=? AND (payment_status = 'Paid' OR payment_status = 'Completed') AND shipping_status='Completed'");
$stmt_ship_complete->execute(array($supplier_id));
$complete_ship_count = (int)$stmt_ship_complete->fetch(PDO::FETCH_ASSOC)['total_complete'];
?>

<style>
/* Modern Elegant Theme for Paid Orders */
.paid-orders-container {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    color: #1e293b;
    padding-bottom: 30px;
}

/* Page Header */
.paid-header-banner {
    background: #ffffff;
    border-bottom: 1px solid #e2e8f0;
    padding: 22px 28px 18px 28px;
    margin: -15px -15px 22px -15px;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
}
.paid-header-flex {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 14px;
}
.paid-header-title {
    margin: 0;
    font-size: 22px;
    font-weight: 800;
    color: #0f172a;
    display: flex;
    align-items: center;
    gap: 10px;
    letter-spacing: -0.3px;
}
.paid-header-title i {
    color: #059669;
    font-size: 22px;
}
.paid-header-sub {
    margin: 4px 0 0 0;
    font-size: 13px;
    color: #64748b;
    font-weight: 500;
}
.btn-nav-receive {
    background: #f8fafc;
    color: #0284c7 !important;
    border: 1px solid #cbd5e1;
    font-weight: 700;
    font-size: 13px;
    padding: 8px 16px;
    border-radius: 8px;
    transition: all 0.2s ease-in-out;
    display: inline-flex;
    align-items: center;
    gap: 7px;
    text-decoration: none !important;
}
.btn-nav-receive:hover {
    background: #f0f9ff;
    border-color: #0284c7;
    color: #0369a1 !important;
    transform: translateY(-1px);
    box-shadow: 0 3px 8px rgba(2, 132, 199, 0.15);
}

/* Metric KPI Cards */
.paid-metric-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 18px 20px;
    box-shadow: 0 2px 8px rgba(15, 23, 42, 0.04);
    transition: all 0.2s ease;
    margin-bottom: 20px;
    height: 100%;
}
.paid-metric-card:hover {
    border-color: #cbd5e1;
    box-shadow: 0 4px 14px rgba(15, 23, 42, 0.06);
}
.paid-metric-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}
.paid-metric-icon {
    width: 42px;
    height: 42px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
}
.icon-green { background: #ecfdf5; color: #059669; }
.icon-blue  { background: #e0f2fe; color: #0284c7; }
.icon-amber { background: #fffbeb; color: #d97706; }
.icon-purple{ background: #f3e8ff; color: #7e22ce; }

.paid-metric-label {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #64748b;
    margin-bottom: 2px;
}
.paid-metric-val {
    font-size: 21px;
    font-weight: 800;
    color: #0f172a;
    line-height: 1.2;
}
.paid-metric-sub {
    font-size: 12px;
    font-weight: 600;
    color: #64748b;
    margin-top: 3px;
}

/* Table Container Card */
.paid-table-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(15, 23, 42, 0.04);
    margin-bottom: 25px;
    overflow: hidden;
    padding: 20px;
}

/* Elegant Table Styling */
#example1 {
    width: 100% !important;
    border-collapse: collapse !important;
    border: 1px solid #e2e8f0;
}
#example1 thead th {
    background: #f8fafc;
    color: #475569;
    font-weight: 700;
    font-size: 11.5px;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    padding: 12px 14px;
    border-bottom: 2px solid #e2e8f0;
    border-top: 1px solid #e2e8f0;
    vertical-align: middle;
}
#example1 tbody td {
    padding: 14px;
    vertical-align: top;
    font-size: 12.5px;
    color: #334155;
    border-bottom: 1px solid #f1f5f9;
    background: #ffffff;
}
#example1 tbody tr:hover td {
    background-color: #f8fafc;
}

/* DataTables UI Controls Styling */
.dataTables_wrapper .dataTables_length select {
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    padding: 5px 8px;
    font-size: 12.5px;
    outline: none;
    margin: 0 4px;
}
.dataTables_wrapper .dataTables_filter input {
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    padding: 6px 12px;
    font-size: 12.5px;
    outline: none;
    margin-left: 6px;
}
.dataTables_wrapper .dataTables_filter input:focus {
    border-color: #059669;
    box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.15);
}
.dataTables_wrapper .dataTables_info {
    font-size: 12.5px;
    color: #64748b;
    padding-top: 12px;
}
.dataTables_wrapper .dataTables_paginate {
    padding-top: 10px;
}
.dataTables_wrapper .dataTables_paginate .pagination {
    margin: 0;
}

/* Badges & Buttons */
.badge-status-paid {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    background: #ecfdf5;
    color: #047857;
    border: 1px solid #a7f3d0;
    font-size: 11px;
    font-weight: 700;
    padding: 3px 8px;
    border-radius: 12px;
}
.badge-status-shipped {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    background: #e0f2fe;
    color: #0369a1;
    border: 1px solid #bae6fd;
    font-size: 11px;
    font-weight: 700;
    padding: 3px 8px;
    border-radius: 12px;
}
.badge-status-pending {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    background: #fffbeb;
    color: #b45309;
    border: 1px solid #fde68a;
    font-size: 11px;
    font-weight: 700;
    padding: 3px 8px;
    border-radius: 12px;
}
.btn-action-view-receipt {
    background: #0284c7;
    color: #ffffff !important;
    border: none;
    font-weight: 700;
    font-size: 11px;
    padding: 5px 10px;
    border-radius: 6px;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    margin-top: 6px;
    width: 100%;
    justify-content: center;
    box-shadow: 0 2px 4px rgba(2, 132, 199, 0.2);
    transition: all 0.2s;
    text-decoration: none !important;
}
.btn-action-view-receipt:hover {
    background: #0369a1;
    transform: translateY(-1px);
}
.btn-action-po-voucher {
    background: #0284c7;
    color: #ffffff !important;
    border: none;
    font-weight: 700;
    font-size: 11px;
    padding: 5px 10px;
    border-radius: 6px;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    margin-top: 6px;
    width: 100%;
    justify-content: center;
    box-shadow: 0 2px 4px rgba(2, 132, 199, 0.2);
    transition: all 0.2s;
    text-decoration: none !important;
}
.btn-action-po-voucher:hover {
    background: #0369a1;
}
.btn-action-send-msg {
    background: #f59e0b;
    color: #ffffff !important;
    border: none;
    font-weight: 700;
    font-size: 11px;
    padding: 4px 8px;
    border-radius: 5px;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    width: 100%;
    justify-content: center;
    margin-top: 6px;
    text-decoration: none !important;
    transition: all 0.2s;
}
.btn-action-send-msg:hover {
    background: #d97706;
}
.btn-action-mark-ship {
    background: #f59e0b;
    color: #ffffff !important;
    border: none;
    font-weight: 700;
    font-size: 11px;
    padding: 5px 10px;
    border-radius: 6px;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    width: 100%;
    justify-content: center;
    margin-top: 6px;
    text-decoration: none !important;
}
.btn-action-mark-ship:hover {
    background: #d97706;
}

/* Modals */
.modal-header-modern {
    background: #0f172a;
    color: #ffffff;
    padding: 15px 20px;
    border-top-left-radius: 8px;
    border-top-right-radius: 8px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.modal-header-modern h4 {
    margin: 0;
    font-size: 16px;
    font-weight: 700;
    color: #ffffff;
}
.modal-header-modern .close {
    color: #ffffff;
    opacity: 0.8;
}
.modal-header-modern .close:hover {
    opacity: 1;
}

/* Printable Official Receipt */
.receipt-box-printable {
    font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
    padding: 20px;
    color: #333;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
}
</style>

<div class="paid-orders-container">
    <!-- Header Banner -->
    <div class="paid-header-banner">
        <div class="paid-header-flex">
            <div>
                <h1 class="paid-header-title">
                    <i class="fa fa-check-circle"></i> View Paid Orders
                </h1>
                <p class="paid-header-sub">
                    Comprehensive sales history of all completed and paid customer transactions
                </p>
            </div>
            <div>
                <a href="order.php" class="btn-nav-receive">
                    <i class="fa fa-inbox"></i> View Receive Orders <i class="fa fa-arrow-right" style="font-size: 10px;"></i>
                </a>
            </div>
        </div>
    </div>

    <section class="content" style="padding: 0 15px;">
        <!-- Alerts Feedback -->
        <?php if($error_message != ''): ?>
            <div class="alert alert-danger alert-dismissible" style="border-radius: 8px;">
                <button type="button" class="close" data-dismiss="alert">&times;</button>
                <i class="fa fa-exclamation-circle"></i> <?php echo nl2br(htmlspecialchars($error_message)); ?>
            </div>
        <?php endif; ?>

        <?php if($success_message != ''): ?>
            <div class="alert alert-success alert-dismissible" style="border-radius: 8px;">
                <button type="button" class="close" data-dismiss="alert">&times;</button>
                <i class="fa fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>

        <!-- KPI Summary Cards -->
        <div class="row">
            <div class="col-md-3 col-sm-6">
                <div class="paid-metric-card">
                    <div class="paid-metric-top">
                        <div>
                            <div class="paid-metric-label">Total Paid Orders</div>
                            <div class="paid-metric-val"><?php echo number_format($total_paid_count); ?></div>
                        </div>
                        <div class="paid-metric-icon icon-green">
                            <i class="fa fa-check-square-o"></i>
                        </div>
                    </div>
                    <div class="paid-metric-sub">
                        Settled customer orders
                    </div>
                </div>
            </div>

            <div class="col-md-3 col-sm-6">
                <div class="paid-metric-card">
                    <div class="paid-metric-top">
                        <div>
                            <div class="paid-metric-label">Revenue Collected</div>
                            <div class="paid-metric-val">&#8369;<?php echo number_format($total_revenue_val, 2); ?></div>
                        </div>
                        <div class="paid-metric-icon icon-blue">
                            <i class="fa fa-money"></i>
                        </div>
                    </div>
                    <div class="paid-metric-sub">
                        Gross sales volume
                    </div>
                </div>
            </div>

            <div class="col-md-3 col-sm-6">
                <div class="paid-metric-card">
                    <div class="paid-metric-top">
                        <div>
                            <div class="paid-metric-label">Delivered / Completed</div>
                            <div class="paid-metric-val"><?php echo number_format($complete_ship_count); ?></div>
                        </div>
                        <div class="paid-metric-icon icon-purple">
                            <i class="fa fa-gift"></i>
                        </div>
                    </div>
                    <div class="paid-metric-sub">
                        Orders fully fulfilled
                    </div>
                </div>
            </div>

            <div class="col-md-3 col-sm-6">
                <div class="paid-metric-card">
                    <div class="paid-metric-top">
                        <div>
                            <div class="paid-metric-label">Pending Delivery</div>
                            <div class="paid-metric-val"><?php echo number_format($pending_ship_count); ?></div>
                        </div>
                        <div class="paid-metric-icon icon-amber">
                            <i class="fa fa-truck"></i>
                        </div>
                    </div>
                    <div class="paid-metric-sub">
                        Awaiting dispatch or pickup
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Orders Table Card -->
        <div class="paid-table-card">
            <div class="table-responsive">
                <table id="example1" class="table table-bordered table-hover table-striped">
                    <thead>
                        <tr>
                            <th style="width: 40px; text-align: center;">#</th>
                            <th style="width: 200px;">Customer</th>
                            <th>Product Details</th>
                            <th style="width: 200px;">Payment Information</th>
                            <th style="width: 110px; text-align: right;">Paid Amount</th>
                            <th style="width: 130px; text-align: center;">Payment Status</th>
                            <th style="width: 130px; text-align: center;">Shipping Status</th>
                            <th style="width: 60px; text-align: center;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $i=0;
                        $statement = $pdo->prepare("SELECT * FROM tbl_payment WHERE supplier_id=? AND (payment_status = 'Paid' OR payment_status = 'Completed') ORDER by id DESC");
                        $statement->execute(array($supplier_id));
                        $result = $statement->fetchAll(PDO::FETCH_ASSOC);							
                        foreach ($result as $row) {
                            $i++;
                            ?>
                            <tr class="<?php if($row['payment_status']=='Pending' || $row['payment_status']=='Awaiting for Payment'){echo 'bg-r';}else{echo 'bg-g';} ?>">
                                <!-- 1. Index -->
                                <td style="text-align: center; vertical-align: top; font-weight: bold;">
                                    <?php echo $i; ?>
                                </td>

                                <!-- 2. Customer -->
                                <td>
                                    <b>Id:</b> <?php echo $row['customer_id']; ?><br>
                                    <b>Name:</b><br> <?php echo htmlspecialchars($row['customer_name']); ?><br>
                                    <b>Email:</b><br> <a href="mailto:<?php echo htmlspecialchars($row['customer_email']); ?>"><?php echo htmlspecialchars($row['customer_email']); ?></a><br><br>
                                    
                                    <a href="#" data-toggle="modal" data-target="#model-<?php echo $i; ?>" class="btn-action-send-msg">
                                        <i class="fa fa-envelope-o"></i> Send Message
                                    </a>

                                    <!-- Modal: Send Message -->
                                    <div id="model-<?php echo $i; ?>" class="modal fade" role="dialog" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content" style="border-radius: 8px; overflow: hidden; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.2);">
                                                <div class="modal-header-modern">
                                                    <h4><i class="fa fa-paper-plane-o"></i> Send Message to Customer</h4>
                                                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                                                </div>
                                                <div class="modal-body" style="font-size: 13.5px; padding: 20px;">
                                                    <form action="" method="post">
                                                        <input type="hidden" name="cust_id" value="<?php echo $row['customer_id']; ?>">
                                                        <input type="hidden" name="payment_id" value="<?php echo $row['payment_id']; ?>">
                                                        <div class="form-group">
                                                            <label style="font-weight: bold;">Subject</label>
                                                            <input type="text" name="subject_text" class="form-control" style="border-radius: 6px;" value="Update regarding Order #<?php echo htmlspecialchars($row['payment_id']); ?>" required>
                                                        </div>
                                                        <div class="form-group">
                                                            <label style="font-weight: bold;">Message</label>
                                                            <textarea name="message_text" class="form-control" rows="8" style="border-radius: 6px; resize: vertical;" placeholder="Type your message here..." required></textarea>
                                                        </div>
                                                        <div style="text-align: right; margin-top: 15px;">
                                                            <button type="button" class="btn btn-default" data-dismiss="modal" style="border-radius: 6px;">Close</button>
                                                            <input type="submit" value="Send Message" name="form1" class="btn btn-primary" style="background: #0284c7; border: none; border-radius: 6px; font-weight: bold; padding: 6px 16px;">
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                <!-- 3. Product Details -->
                                <td>
                                   <?php
                                   $statement1 = $pdo->prepare("SELECT * FROM tbl_order WHERE payment_id=?");
                                   $statement1->execute(array($row['payment_id']));
                                   $result1 = $statement1->fetchAll(PDO::FETCH_ASSOC);
                                   foreach ($result1 as $row1) {
                                        if (isset($row1['item_type']) && $row1['item_type'] === 'SPECIAL_ORDER') {
                                            echo '<span class="label label-warning" style="background:#d97706; font-size:10px; font-weight:bold; border-radius:3px;">SPECIAL ORDER</span><br>';
                                            echo '<b>Product:</b> '.htmlspecialchars($row1['product_name']);
                                            if (!empty($row1['product_details'])) {
                                                echo '<br><span style="font-size:11px; color:#475569;"><b>Details:</b> '.htmlspecialchars($row1['product_details']).'</span>';
                                            }
                                            if (!empty($row1['special_order_reference'])) {
                                                echo '<br><span style="font-size:10px; color:#0284c7; font-family:monospace;"><b>Ref:</b> '.htmlspecialchars($row1['special_order_reference']).'</span>';
                                            }
                                            echo '<br>(<b>Quantity:</b> '.$row1['quantity'];
                                            echo ', <b>Unit Price:</b> &#8369;'.number_format(floatval($row1['unit_price']), 2).')';
                                            echo '<br><br>';
                                        } else {
                                            echo '<b>Product:</b> '.htmlspecialchars($row1['product_name']);
                                            echo '<br>(<b>Size:</b> '.htmlspecialchars($row1['size'] ?: '-');
                                            echo ', <b>Color:</b> '.htmlspecialchars($row1['color'] ?: '-').')';
                                            echo '<br>(<b>Quantity:</b> '.$row1['quantity'];
                                            echo ', <b>Unit Price:</b> &#8369;'.number_format(floatval($row1['unit_price']), 2).')';
                                            echo '<br><br>';
                                        }
                                   }
                                   ?>
                                </td>

                                <!-- 4. Payment Information -->
                                <td>
                                    <?php if($row['payment_method'] == 'PayPal'): ?>
                                        <b>Payment Method:</b> <span style="color:#0284c7; font-weight:bold;"><?php echo $row['payment_method']; ?></span><br>
                                        <b>Payment Id:</b> <?php echo $row['payment_id']; ?><br>
                                        <b>Date:</b> <?php echo $row['payment_date']; ?><br>
                                        <b>Transaction Id:</b> <?php echo $row['txnid']; ?><br>
                                    <?php elseif($row['payment_method'] == 'Stripe'): ?>
                                        <b>Payment Method:</b> <span style="color:#7e22ce; font-weight:bold;"><?php echo $row['payment_method']; ?></span><br>
                                        <b>Payment Id:</b> <?php echo $row['payment_id']; ?><br>
                                        <b>Date:</b> <?php echo $row['payment_date']; ?><br>
                                        <b>Transaction Id:</b> <?php echo $row['txnid']; ?><br>
                                        <b>Card Number:</b> <?php echo $row['card_number']; ?><br>
                                        <b>Card CVV:</b> <?php echo $row['card_cvv']; ?><br>
                                        <b>Expire Month:</b> <?php echo $row['card_month']; ?><br>
                                        <b>Expire Year:</b> <?php echo $row['card_year']; ?><br>
                                    <?php elseif($row['payment_method'] == 'Bank Deposit'): ?>
                                        <b>Payment Method:</b> <span style="color:#b45309; font-weight:bold;"><?php echo $row['payment_method']; ?></span><br>
                                        <b>Payment Id:</b> <?php echo $row['payment_id']; ?><br>
                                        <b>Date:</b> <?php echo $row['payment_date']; ?><br>
                                        <b>Transaction Information:</b> <br><?php echo $row['bank_transaction_info']; ?><br>
                                    <?php elseif($row['payment_method'] == 'Over the Counter' || $row['payment_method'] == 'Purchase Order (PO)' || $row['payment_method'] == 'Cash (OTC)' || $row['payment_method'] == 'Cash'): ?>
                                        <b>Payment Method:</b> <span style="color:#059669; font-weight:bold;"><?php echo htmlspecialchars($row['payment_method']); ?></span><br>
                                        <b>Transaction Id:</b> <?php echo htmlspecialchars($row['txnid'] ?: $row['payment_id']); ?><br>
                                        <b>Date:</b> <?php echo $row['payment_date']; ?><br>
                                        <b>Details:</b> <?php echo htmlspecialchars($row['bank_transaction_info'] ?? 'Over the Counter'); ?><br>
                                    <?php else: ?>
                                        <b>Payment Method:</b> <b><?php echo htmlspecialchars($row['payment_method']); ?></b><br>
                                        <b>Payment Id:</b> <?php echo $row['payment_id']; ?><br>
                                        <b>Date:</b> <?php echo $row['payment_date']; ?><br>
                                    <?php endif; ?>

                                    <!-- Purchase Order Voucher Modal Trigger -->
                                    <div style="margin-top: 8px;">
                                        <a href="#" data-toggle="modal" data-target="#po-modal-<?php echo $row['id']; ?>" class="btn-action-po-voucher">
                                            <i class="fa fa-file-text-o"></i> Purchase Order Voucher
                                        </a>
                                    </div>

                                    <!-- Modal: Customer Purchase Order Voucher -->
                                    <?php
                                    $statement_c = $pdo->prepare("SELECT * FROM tbl_customer WHERE cust_id=?");
                                    $statement_c->execute(array($row['customer_id']));
                                    $c_data = $statement_c->fetch(PDO::FETCH_ASSOC);

                                    $statement_s = $pdo->prepare("SELECT * FROM tbl_supplier WHERE supplier_id=?");
                                    $statement_s->execute(array($row['supplier_id']));
                                    $s_data = $statement_s->fetch(PDO::FETCH_ASSOC);

                                    $statement_po_items = $pdo->prepare("SELECT o.*, p.p_sku FROM tbl_order o LEFT JOIN tbl_product p ON o.product_id = p.p_id WHERE o.payment_id=?");
                                    $statement_po_items->execute(array($row['payment_id']));
                                    $po_items = $statement_po_items->fetchAll(PDO::FETCH_ASSOC);

                                    $po_subtotal_calc = 0;
                                    $po_c_calc = 0;
                                    $items_formatted = [];
                                    foreach ($po_items as $p_item) {
                                        $po_c_calc++;
                                        $p_qty = intval($p_item['quantity'] ?? 1);
                                        $p_price = floatval($p_item['unit_price'] ?? 0);
                                        $p_line = $p_price * $p_qty;
                                        $po_subtotal_calc += $p_line;

                                        $specs_arr = [];
                                        if (!empty($p_item['size']) && $p_item['size'] !== '-') $specs_arr[] = 'Size: ' . $p_item['size'];
                                        if (!empty($p_item['color']) && $p_item['color'] !== '-') $specs_arr[] = 'Color: ' . $p_item['color'];
                                        $sku_code = !empty($p_item['p_sku']) ? $p_item['p_sku'] : ($p_item['product_id'] > 0 ? ('SKU-' . str_pad($p_item['product_id'], 4, '0', STR_PAD_LEFT)) : 'N/A');

                                        $items_formatted[] = [
                                            'name' => $p_item['product_name'] ?? '',
                                            'sku' => $sku_code,
                                            'specs' => implode(' | ', $specs_arr) ?: 'Standard',
                                            'qty' => $p_qty,
                                            'price' => $p_price,
                                            'subtotal' => $p_line,
                                            'is_special_order' => (isset($p_item['item_type']) && $p_item['item_type'] === 'SPECIAL_ORDER'),
                                            'details' => $p_item['product_details'] ?? '',
                                            'special_order_ref' => $p_item['special_order_reference'] ?? ''
                                        ];
                                    }
                                    $grand_total_calc = floatval($row['paid_amount'] ?? 0);
                                    $po_delivery_calc = max(0.00, $grand_total_calc - $po_subtotal_calc);

                                    $full_cust_address = '';
                                    if ($c_data) {
                                        $cust_parts = array_filter([$c_data['cust_s_address'] ?? $c_data['cust_b_address'] ?? $c_data['cust_address'] ?? '', $c_data['cust_s_state'] ?? $c_data['cust_state'] ?? '', $c_data['cust_s_city'] ?? $c_data['cust_city'] ?? '']);
                                        $full_cust_address = implode(', ', $cust_parts);
                                    }
                                    ?>
                                    <div id="po-modal-<?php echo $row['id']; ?>" class="modal fade" role="dialog" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog modal-lg" style="max-width: 720px; margin-top: 30px;">
                                            <div class="modal-content" style="border-radius: 10px; overflow: hidden; border: none; box-shadow: 0 15px 40px rgba(0,0,0,0.2);">
                                                
                                                <!-- Modern Modal Header -->
                                                <div class="modal-header-modern" style="background: #0284c7; display: flex; justify-content: space-between; align-items: center; padding: 14px 20px; color: #ffffff;">
                                                    <h4 class="modal-title" style="margin: 0; font-size: 16px; font-weight: 700; display: flex; align-items: center; gap: 8px; color: #ffffff;">
                                                        <i class="fa fa-file-text-o"></i> Customer Purchase Order Voucher — <span style="font-family: monospace; font-size: 17px;"><?php echo htmlspecialchars($row['txnid'] ?: $row['payment_id']); ?></span>
                                                    </h4>
                                                    <div style="display: flex; align-items: center; gap: 8px;">
                                                        <button type="button" class="btn btn-xs btn-default" onclick="printPOVoucher(<?php echo $row['id']; ?>)" style="background: #ffffff; color: #0284c7; font-weight: 700; border: none; border-radius: 5px; padding: 6px 14px; font-size: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                                            <i class="fa fa-print"></i> Print Voucher
                                                        </button>
                                                        <button type="button" class="close" data-dismiss="modal" aria-hidden="true" style="color: #ffffff; opacity: 0.9; font-size: 24px; margin: 0; line-height: 1;">&times;</button>
                                                    </div>
                                                </div>

                                                <!-- Modern Modal Body -->
                                                <div class="modal-body" style="padding: 20px; background: #f8fafc;">
                                                    
                                                    <!-- Status & Overview Bar -->
                                                    <div style="background: #ecfdf5; border: 1.5px solid #a7f3d0; border-radius: 8px; padding: 12px 18px; margin-bottom: 18px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                                                        <div style="display: flex; align-items: center; gap: 10px;">
                                                            <span class="label" style="background: #059669; color: #ffffff; font-size: 12px; font-weight: 800; padding: 5px 10px; border-radius: 4px; text-transform: uppercase;">
                                                                <i class="fa fa-check-circle"></i> Paid / Settle
                                                            </span>
                                                            <span style="font-size: 12.5px; color: #065f46; font-weight: 600;">Purchase Order fulfilled &amp; recorded</span>
                                                        </div>
                                                        <div style="font-size: 12px; color: #64748b; font-weight: 600;">
                                                            <i class="fa fa-calendar"></i> <?php echo date('M d, Y h:i A', strtotime($row['payment_date'])); ?>
                                                        </div>
                                                    </div>

                                                    <!-- 2-Column Info Cards -->
                                                    <div class="row" style="margin-bottom: 18px;">
                                                        <div class="col-sm-6 col-xs-12" style="margin-bottom: 10px;">
                                                            <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px 16px; height: 100%;">
                                                                <div style="font-size: 11px; font-weight: 800; text-transform: uppercase; color: #64748b; margin-bottom: 8px; letter-spacing: 0.5px;">
                                                                    <i class="fa fa-user"></i> Customer Information
                                                                </div>
                                                                <div style="font-size: 14px; font-weight: 700; color: #0f172a; margin-bottom: 3px;">
                                                                    <?php echo htmlspecialchars($row['customer_name'] ?? 'Walk-in Customer'); ?>
                                                                </div>
                                                                <?php if (!empty($c_data['cust_phone'])): ?>
                                                                    <div style="font-size: 12px; color: #475569; margin-bottom: 2px;"><i class="fa fa-phone" style="width: 14px; color: #0284c7;"></i> <?php echo htmlspecialchars($c_data['cust_phone']); ?></div>
                                                                <?php endif; ?>
                                                                <?php if (!empty($row['customer_email'])): ?>
                                                                    <div style="font-size: 12px; color: #475569; margin-bottom: 2px;"><i class="fa fa-envelope" style="width: 14px; color: #0284c7;"></i> <?php echo htmlspecialchars($row['customer_email']); ?></div>
                                                                <?php endif; ?>
                                                                <?php if (!empty($full_cust_address)): ?>
                                                                    <div style="font-size: 11.5px; color: #64748b;"><i class="fa fa-map-marker" style="width: 14px; color: #0284c7;"></i> <?php echo htmlspecialchars($full_cust_address); ?></div>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                        <div class="col-sm-6 col-xs-12" style="margin-bottom: 10px;">
                                                            <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px 16px; height: 100%;">
                                                                <div style="font-size: 11px; font-weight: 800; text-transform: uppercase; color: #64748b; margin-bottom: 8px; letter-spacing: 0.5px;">
                                                                    <i class="fa fa-building-o"></i> Store &amp; Fulfillment
                                                                </div>
                                                                <div style="font-size: 13.5px; font-weight: 700; color: #0f172a; margin-bottom: 3px;">
                                                                    <?php echo htmlspecialchars(!empty($s_data['supplier_name']) ? $s_data['supplier_name'] : 'SAM & INRI CONSTRUCTION SUPPLY'); ?>
                                                                </div>
                                                                <div style="font-size: 12px; color: #475569; margin-bottom: 4px;">
                                                                    <?php if ($row['shipping_status'] === 'Completed'): ?>
                                                                        <span class="label" style="background: #059669; font-size: 10.5px; padding: 3px 7px; border-radius: 3px;"><i class="fa fa-check"></i> Completed</span>
                                                                    <?php else: ?>
                                                                        <span class="label" style="background: #0284c7; font-size: 10.5px; padding: 3px 7px; border-radius: 3px;"><i class="fa fa-truck"></i> <?php echo htmlspecialchars($row['shipping_status'] ?? 'Pending'); ?></span>
                                                                    <?php endif; ?>
                                                                    <span style="margin-left: 6px; font-weight: 600;">Method: <?php echo htmlspecialchars($row['payment_method'] ?? 'Purchase Order (PO)'); ?></span>
                                                                </div>
                                                                <?php if (!empty($s_data['supplier_phone'])): ?>
                                                                    <div style="font-size: 11.5px; color: #64748b;"><i class="fa fa-phone" style="width: 14px;"></i> Tel: <?php echo htmlspecialchars($s_data['supplier_phone']); ?></div>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <!-- Order Line Items Table -->
                                                    <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; margin-bottom: 18px;">
                                                        <div style="padding: 10px 16px; background: #f1f5f9; border-bottom: 1px solid #e2e8f0; font-size: 12px; font-weight: 800; text-transform: uppercase; color: #475569; letter-spacing: 0.5px;">
                                                            <i class="fa fa-shopping-cart"></i> Ordered Items (<?php echo count($po_items); ?>)
                                                        </div>
                                                        <div class="table-responsive" style="margin-bottom: 0;">
                                                            <table class="table table-hover" style="margin-bottom: 0; font-size: 13px;">
                                                                <thead style="background: #f8fafc;">
                                                                    <tr>
                                                                        <th style="width: 40px; text-align: center; color: #64748b; font-weight: 700; border-bottom: 1px solid #e2e8f0;">#</th>
                                                                        <th style="color: #475569; font-weight: 700; border-bottom: 1px solid #e2e8f0;">Item Description</th>
                                                                        <th style="width: 80px; text-align: center; color: #475569; font-weight: 700; border-bottom: 1px solid #e2e8f0;">Qty</th>
                                                                        <th style="width: 110px; text-align: right; color: #475569; font-weight: 700; border-bottom: 1px solid #e2e8f0;">Unit Price</th>
                                                                        <th style="width: 120px; text-align: right; color: #475569; font-weight: 700; border-bottom: 1px solid #e2e8f0;">Subtotal</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody>
                                                                    <?php 
                                                                    $item_idx = 0;
                                                                    foreach ($po_items as $p_item): 
                                                                        $item_idx++;
                                                                        $p_qty = intval($p_item['quantity'] ?? 1);
                                                                        $p_price = floatval($p_item['unit_price'] ?? 0);
                                                                        $p_line = $p_price * $p_qty;
                                                                        $is_sp = (isset($p_item['item_type']) && $p_item['item_type'] === 'SPECIAL_ORDER');
                                                                        $unit_label = ($p_qty > 1 ? 'pcs' : 'pc');
                                                                    ?>
                                                                    <tr>
                                                                        <td style="text-align: center; color: #94a3b8; font-weight: 600; vertical-align: middle;"><?php echo $item_idx; ?></td>
                                                                        <td style="vertical-align: middle;">
                                                                            <div style="font-weight: 700; color: #0f172a;">
                                                                                <?php if ($is_sp): ?>
                                                                                    <span class="label" style="background: #f59e0b; color: #fff; font-size: 10px; padding: 2px 6px; margin-right: 4px;">SPECIAL ORDER</span>
                                                                                <?php endif; ?>
                                                                                <?php echo htmlspecialchars($p_item['product_name'] ?? ''); ?>
                                                                            </div>
                                                                            <?php if (!empty($p_item['size']) || !empty($p_item['color'])): ?>
                                                                                <div style="margin-top: 3px; display: flex; gap: 4px;">
                                                                                    <?php if (!empty($p_item['size']) && $p_item['size'] !== '-'): ?>
                                                                                        <span class="badge" style="background: #f1f5f9; color: #475569; font-size: 10.5px; font-weight: 600;">Size: <?php echo htmlspecialchars($p_item['size']); ?></span>
                                                                                    <?php endif; ?>
                                                                                    <?php if (!empty($p_item['color']) && $p_item['color'] !== '-'): ?>
                                                                                        <span class="badge" style="background: #f1f5f9; color: #475569; font-size: 10.5px; font-weight: 600;">Color: <?php echo htmlspecialchars($p_item['color']); ?></span>
                                                                                    <?php endif; ?>
                                                                                </div>
                                                                            <?php endif; ?>
                                                                        </td>
                                                                        <td style="text-align: center; font-weight: 700; color: #1e293b; vertical-align: middle;"><?php echo $p_qty; ?> <span style="font-size: 11px; color: #64748b; font-weight: normal;"><?php echo $unit_label; ?></span></td>
                                                                        <td style="text-align: right; color: #475569; font-weight: 600; vertical-align: middle;">&#8369;<?php echo number_format($p_price, 2); ?></td>
                                                                        <td style="text-align: right; font-weight: 800; color: #0f172a; vertical-align: middle;">&#8369;<?php echo number_format($p_line, 2); ?></td>
                                                                    </tr>
                                                                    <?php endforeach; ?>
                                                                </tbody>
                                                            </table>
                                                        </div>
                                                    </div>

                                                    <!-- Financial Totals Block -->
                                                    <div style="display: flex; justify-content: flex-end;">
                                                        <div style="width: 100%; max-width: 320px; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px 18px;">
                                                            <div style="display: flex; justify-content: space-between; font-size: 13px; color: #475569; margin-bottom: 6px;">
                                                                <span>Subtotal:</span>
                                                                <span style="font-weight: 700; color: #0f172a;">&#8369;<?php echo number_format($po_subtotal_calc, 2); ?></span>
                                                            </div>
                                                            <?php if ($po_delivery_calc > 0): ?>
                                                            <div style="display: flex; justify-content: space-between; font-size: 13px; color: #475569; margin-bottom: 6px;">
                                                                <span>Delivery Fee:</span>
                                                                <span style="font-weight: 600; color: #0f172a;">&#8369;<?php echo number_format($po_delivery_calc, 2); ?></span>
                                                            </div>
                                                            <?php endif; ?>
                                                            <div style="border-top: 2px solid #e2e8f0; padding-top: 8px; margin-top: 6px; display: flex; justify-content: space-between; align-items: baseline;">
                                                                <span style="font-size: 14px; font-weight: 800; color: #0f172a;">TOTAL DUE:</span>
                                                                <span style="font-size: 18px; font-weight: 800; color: #0284c7;">&#8369;<?php echo number_format($grand_total_calc, 2); ?></span>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <!-- Hidden Silent Thermal Print Element -->
                                                    <div id="po-printable-thermal-<?php echo $row['id']; ?>" style="display: none;">
                                                        <div style="font-family: 'Courier New', Consolas, monospace; font-size: 11pt; line-height: 1.25; color: #000; text-align: left;">
                                                            <div style="text-align: center; font-weight: bold; overflow: hidden; white-space: nowrap;">================================</div>
                                                            <div style="text-align: center;">
                                                                <div style="font-size: 12pt; font-weight: bold; text-transform: uppercase;"><?php echo htmlspecialchars(!empty($s_data['supplier_name']) ? strtoupper($s_data['supplier_name']) : 'SAM & INRI CONSTRUCTION SUPPLY'); ?></div>
                                                                <?php if (!empty($s_data['supplier_address'])): ?>
                                                                    <div style="font-size: 9.5pt;"><?php echo htmlspecialchars($s_data['supplier_address']); ?></div>
                                                                <?php endif; ?>
                                                                <div style="font-size: 10pt;">Tel: <?php echo htmlspecialchars(!empty($s_data['supplier_phone']) ? $s_data['supplier_phone'] : '09612735733'); ?></div>
                                                                <div style="font-size: 11pt; font-weight: bold; text-transform: uppercase; margin-top: 2px;">PURCHASE ORDER VOUCHER</div>
                                                                <div style="font-size: 10pt; font-weight: bold; text-transform: uppercase;">(PAID)</div>
                                                            </div>
                                                            <div style="text-align: center; font-weight: bold; overflow: hidden; white-space: nowrap;">================================</div>
                                                            <table style="width: 100%; border-collapse: collapse; font-family: inherit; font-size: 10.5pt; margin-bottom: 2px;">
                                                                <tr><td style="width: 28%; font-weight: bold;">PO NO   :</td><td style="font-weight: bold;"><?php echo htmlspecialchars($row['txnid'] ?: $row['payment_id']); ?></td></tr>
                                                                <tr><td style="font-weight: bold;">CUSTOMER:</td><td><?php echo htmlspecialchars($row['customer_name'] ?? 'Walk-in Customer'); ?></td></tr>
                                                                <tr><td style="font-weight: bold;">STATUS  :</td><td style="font-weight: bold;">PAID</td></tr>
                                                                <tr><td style="font-weight: bold;">DATE    :</td><td><?php echo date('Y-m-d H:i:s', strtotime($row['payment_date'])); ?></td></tr>
                                                            </table>
                                                            <div style="text-align: center; overflow: hidden; white-space: nowrap;">--------------------------------</div>
                                                            <table style="width: 100%; border-collapse: collapse; font-family: inherit; font-size: 10.5pt;">
                                                                <thead>
                                                                    <tr>
                                                                        <th style="text-align: left; width: 68%;">ITEM DESCRIPTION</th>
                                                                        <th style="text-align: right; width: 32%;">AMOUNT</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody>
                                                                    <?php foreach ($po_items as $p_item): 
                                                                        $p_qty = intval($p_item['quantity'] ?? 1);
                                                                        $p_price = floatval($p_item['unit_price'] ?? 0);
                                                                        $p_line = $p_price * $p_qty;
                                                                        $is_sp = (isset($p_item['item_type']) && $p_item['item_type'] === 'SPECIAL_ORDER');
                                                                        $unit_label = ($p_qty > 1 ? 'pcs' : 'pc');
                                                                    ?>
                                                                    <tr>
                                                                        <td colspan="2" style="text-align: left; padding-top: 3px; font-weight: bold; word-break: break-word;">
                                                                            <?php if ($is_sp): ?>[SPECIAL ORDER] <?php endif; ?>
                                                                            <?php echo htmlspecialchars($p_item['product_name'] ?? ''); ?>
                                                                            <?php if (!empty($p_item['size']) || !empty($p_item['color'])): ?>
                                                                                <div style="font-size: 9pt; font-weight: normal;">
                                                                                    <?php if (!empty($p_item['size']) && $p_item['size'] !== '-') echo 'Size: ' . htmlspecialchars($p_item['size']) . ' '; ?>
                                                                                    <?php if (!empty($p_item['color']) && $p_item['color'] !== '-') echo 'Color: ' . htmlspecialchars($p_item['color']); ?>
                                                                                </div>
                                                                            <?php endif; ?>
                                                                        </td>
                                                                    </tr>
                                                                    <tr>
                                                                        <td style="text-align: left; padding-left: 8px; padding-bottom: 3px;">
                                                                            <?php echo $p_qty; ?> <?php echo $unit_label; ?> @ <?php echo number_format($p_price, 2); ?>
                                                                        </td>
                                                                        <td style="text-align: right; padding-bottom: 3px; white-space: nowrap; vertical-align: bottom;">
                                                                            <?php echo number_format($p_line, 2); ?>
                                                                        </td>
                                                                    </tr>
                                                                    <?php endforeach; ?>
                                                                </tbody>
                                                            </table>
                                                            <div style="text-align: center; overflow: hidden; white-space: nowrap;">--------------------------------</div>
                                                            <table style="width: 100%; border-collapse: collapse; font-family: inherit; font-size: 10.5pt; margin: 2px 0;">
                                                                <tr><td>Subtotal:</td><td style="text-align: right;"><?php echo number_format($po_subtotal_calc, 2); ?></td></tr>
                                                                <?php if ($po_delivery_calc > 0): ?>
                                                                <tr><td>Delivery Fee:</td><td style="text-align: right;"><?php echo number_format($po_delivery_calc, 2); ?></td></tr>
                                                                <?php endif; ?>
                                                                <tr style="font-weight: bold;"><td style="font-size: 1.08em;">TOTAL DUE:</td><td style="text-align: right; font-size: 1.08em;">PHP <?php echo number_format($grand_total_calc, 2); ?></td></tr>
                                                            </table>
                                                            <div style="text-align: center; font-weight: bold; overflow: hidden; white-space: nowrap;">================================</div>
                                                            <div style="text-align: center; line-height: 1.35; padding: 2px 0;">
                                                                <div style="font-weight: bold;">*** OFFICIAL PO VOUCHER ***</div>
                                                                <div style="margin-top: 3px;">Thank you for your business!</div>
                                                                <div style="font-size: 9pt; margin-top: 2px;">eConstruction Supply POS</div>
                                                            </div>
                                                            <div style="text-align: center; font-weight: bold; overflow: hidden; white-space: nowrap;">================================</div>
                                                        </div>
                                                    </div>

                                                </div>
                                                <div class="modal-footer" style="background: #f1f5f9; border-top: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; padding: 12px 20px;">
                                                    <button type="button" class="btn btn-default btn-sm" data-dismiss="modal" style="font-weight: 700; border-radius: 5px;">Close</button>
                                                    <div style="display: flex; gap: 8px;">
                                                        <button type="button" class="btn btn-primary btn-sm" onclick="printPOVoucher(<?php echo $row['id']; ?>)" style="font-weight: 700; background-color: #0284c7; border-color: #0369a1; border-radius: 5px; padding: 6px 16px;" title="Print Voucher on Thermal Printer">
                                                            <i class="fa fa-print"></i> Print Purchase Order Voucher
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                <!-- 5. Paid Amount -->
                                <td style="text-align: right; vertical-align: top; font-weight: bold; font-size: 13.5px; color: #059669;">
                                    &#8369;<?php echo number_format(floatval($row['paid_amount']), 2); ?>
                                </td>

                                <!-- 6. Payment Status & Official Receipt Modal -->
                                <td style="text-align: center; vertical-align: top;">
                                    <span class="badge-status-paid"><i class="fa fa-check-circle"></i> <?php echo htmlspecialchars($row['payment_status']); ?></span>
                                    <br>
                                    <a href="#" data-toggle="modal" data-target="#receipt-modal-<?php echo $row['id']; ?>" class="btn-action-view-receipt">
                                        <i class="fa fa-print"></i> View Receipt
                                    </a>
                                     
                                    <!-- Modal for View Official Receipt (Unified POS Receipt Format) -->
                                    <?php
                                    $statement_s = $pdo->prepare("SELECT * FROM tbl_supplier WHERE supplier_id=?");
                                    $statement_s->execute(array($row['supplier_id']));
                                    $sup_data = $statement_s->fetch(PDO::FETCH_ASSOC);

                                    $statement_c = $pdo->prepare("SELECT * FROM tbl_customer WHERE cust_id=?");
                                    $statement_c->execute(array($row['customer_id']));
                                    $cust_data = $statement_c->fetch(PDO::FETCH_ASSOC);

                                    $statement_o = $pdo->prepare("SELECT * FROM tbl_order WHERE payment_id=?");
                                    $statement_o->execute(array($row['payment_id']));
                                    $order_items = $statement_o->fetchAll(PDO::FETCH_ASSOC);

                                    $subtotal_items = 0;
                                    foreach ($order_items as $item) {
                                        $subtotal_items += floatval($item['unit_price']) * intval($item['quantity']);
                                    }
                                    $delivery_cost = floatval($row['paid_amount']) - $subtotal_items;
                                    if ($delivery_cost < 0) $delivery_cost = 0;
                                    ?>
                                    <div id="receipt-modal-<?php echo $row['id']; ?>" class="modal fade" role="dialog" tabindex="-1" aria-hidden="true">
                                         <div class="modal-dialog modal-lg" style="max-width: 720px; margin-top: 30px;">
                                             <div class="modal-content" style="border-radius: 10px; overflow: hidden; border: none; box-shadow: 0 15px 40px rgba(0,0,0,0.2);">
                                                 
                                                 <!-- Modern Modal Header -->
                                                 <div class="modal-header-modern" style="background: #059669; display: flex; justify-content: space-between; align-items: center; padding: 14px 20px; color: #ffffff;">
                                                     <h4 class="modal-title" style="margin: 0; font-size: 16px; font-weight: 700; display: flex; align-items: center; gap: 8px; color: #ffffff;">
                                                         <i class="fa fa-check-circle"></i> P.O.  RECEIPT — <span style="font-family: monospace; font-size: 17px;"><?php echo htmlspecialchars($row['txnid'] ?: $row['payment_id']); ?></span>
                                                     </h4>
                                                     <div style="display: flex; align-items: center; gap: 8px;">
                                                         <button type="button" class="btn btn-xs btn-default" onclick="printReceiptModal(<?php echo $row['id']; ?>)" style="background: #ffffff; color: #059669; font-weight: 700; border: none; border-radius: 5px; padding: 6px 14px; font-size: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                                             <i class="fa fa-print"></i> Print Receipt
                                                         </button>
                                                         <button type="button" class="close" data-dismiss="modal" aria-hidden="true" style="color: #ffffff; opacity: 0.9; font-size: 24px; margin: 0; line-height: 1;">&times;</button>
                                                     </div>
                                                 </div>

                                                 <!-- Modern Modal Body -->
                                                 <div class="modal-body" style="padding: 20px; background: #f8fafc;">
                                                     
                                                     <!-- Status & Overview Bar -->
                                                     <div style="background: #ecfdf5; border: 1.5px solid #a7f3d0; border-radius: 8px; padding: 12px 18px; margin-bottom: 18px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                                                         <div style="display: flex; align-items: center; gap: 10px;">
                                                             <span class="label" style="background: #059669; color: #ffffff; font-size: 12px; font-weight: 800; padding: 5px 10px; border-radius: 4px; text-transform: uppercase;">
                                                                 <i class="fa fa-check"></i> Paid &amp; Completed
                                                             </span>
                                                             <span style="font-size: 12.5px; color: #065f46; font-weight: 600;">P.O. transaction recorded</span>
                                                         </div>
                                                         <div style="font-size: 12px; color: #64748b; font-weight: 600;">
                                                             <i class="fa fa-calendar"></i> <?php echo date('M d, Y h:i A', strtotime($row['payment_date'])); ?>
                                                         </div>
                                                     </div>

                                                     <!-- 2-Column Info Cards -->
                                                     <div class="row" style="margin-bottom: 18px;">
                                                         <div class="col-sm-6 col-xs-12" style="margin-bottom: 10px;">
                                                             <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px 16px; height: 100%;">
                                                                 <div style="font-size: 11px; font-weight: 800; text-transform: uppercase; color: #64748b; margin-bottom: 8px; letter-spacing: 0.5px;">
                                                                     <i class="fa fa-user"></i> Customer Information
                                                                 </div>
                                                                 <div style="font-size: 14px; font-weight: 700; color: #0f172a; margin-bottom: 3px;">
                                                                     <?php echo htmlspecialchars($row['customer_name'] ?? 'Walk-in Customer'); ?>
                                                                 </div>
                                                                 <?php if (!empty($cust_data['cust_phone'])): ?>
                                                                     <div style="font-size: 12px; color: #475569; margin-bottom: 2px;"><i class="fa fa-phone" style="width: 14px; color: #059669;"></i> <?php echo htmlspecialchars($cust_data['cust_phone']); ?></div>
                                                                 <?php endif; ?>
                                                                 <?php if (!empty($row['customer_email'])): ?>
                                                                     <div style="font-size: 12px; color: #475569; margin-bottom: 2px;"><i class="fa fa-envelope" style="width: 14px; color: #059669;"></i> <?php echo htmlspecialchars($row['customer_email']); ?></div>
                                                                 <?php endif; ?>
                                                                 <?php 
                                                                 $cust_addr_str = '';
                                                                 if ($cust_data) {
                                                                     $c_parts = array_filter([$cust_data['cust_s_address'] ?? $cust_data['cust_address'] ?? '', $cust_data['cust_s_state'] ?? $cust_data['cust_state'] ?? '', $cust_data['cust_s_city'] ?? $cust_data['cust_city'] ?? '']);
                                                                     $cust_addr_str = implode(', ', $c_parts);
                                                                 }
                                                                 if (!empty($cust_addr_str)): ?>
                                                                     <div style="font-size: 11.5px; color: #64748b;"><i class="fa fa-map-marker" style="width: 14px; color: #059669;"></i> <?php echo htmlspecialchars($cust_addr_str); ?></div>
                                                                 <?php endif; ?>
                                                             </div>
                                                         </div>
                                                         <div class="col-sm-6 col-xs-12" style="margin-bottom: 10px;">
                                                             <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px 16px; height: 100%;">
                                                                 <div style="font-size: 11px; font-weight: 800; text-transform: uppercase; color: #64748b; margin-bottom: 8px; letter-spacing: 0.5px;">
                                                                     <i class="fa fa-building-o"></i> Store &amp; Payment Details
                                                                 </div>
                                                                 <div style="margin-bottom: 6px; font-size: 13px;">
                                                                     <strong>P.O. Receipt No:</strong>
                                                                     <div style="padding-left: 8px; margin-top: 2px;">
                                                                         <span style="font-family: monospace; font-weight: 700; font-size: 13.5px; color: #059669;"><?php echo htmlspecialchars($row['txnid'] ?: $row['payment_id']); ?></span>
                                                                     </div>
                                                                 </div>
                                                                 <div style="font-size: 13.5px; font-weight: 700; color: #0f172a; margin-bottom: 3px;">
                                                                     <?php echo htmlspecialchars(!empty($sup_data['supplier_name']) ? $sup_data['supplier_name'] : 'SAM & INRI CONSTRUCTION SUPPLY'); ?>
                                                                 </div>
                                                                 <div style="font-size: 12px; color: #475569; margin-bottom: 4px;">
                                                                     <span class="label" style="background: #059669; font-size: 10.5px; padding: 3px 7px; border-radius: 3px;"><i class="fa fa-credit-card"></i> <?php echo htmlspecialchars($row['payment_method'] ?? 'Cash'); ?></span>
                                                                     <span style="margin-left: 6px; font-weight: 600;">Status: Paid</span>
                                                                 </div>
                                                                 <?php if (!empty($sup_data['supplier_phone'])): ?>
                                                                     <div style="font-size: 11.5px; color: #64748b;"><i class="fa fa-phone" style="width: 14px;"></i> Tel: <?php echo htmlspecialchars($sup_data['supplier_phone']); ?></div>
                                                                 <?php endif; ?>
                                                             </div>
                                                         </div>
                                                     </div>

                                                     <!-- Purchased Items Table -->
                                                     <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; margin-bottom: 18px;">
                                                         <div style="padding: 10px 16px; background: #f1f5f9; border-bottom: 1px solid #e2e8f0; font-size: 12px; font-weight: 800; text-transform: uppercase; color: #475569; letter-spacing: 0.5px;">
                                                             <i class="fa fa-shopping-cart"></i> Purchased Items (<?php echo count($order_items); ?>)
                                                         </div>
                                                         <div class="table-responsive" style="margin-bottom: 0;">
                                                             <table class="table table-hover" style="margin-bottom: 0; font-size: 13px;">
                                                                 <thead style="background: #f8fafc;">
                                                                     <tr>
                                                                         <th style="width: 40px; text-align: center; color: #64748b; font-weight: 700; border-bottom: 1px solid #e2e8f0;">#</th>
                                                                         <th style="color: #475569; font-weight: 700; border-bottom: 1px solid #e2e8f0;">Item Description</th>
                                                                         <th style="width: 80px; text-align: center; color: #475569; font-weight: 700; border-bottom: 1px solid #e2e8f0;">Qty</th>
                                                                         <th style="width: 110px; text-align: right; color: #475569; font-weight: 700; border-bottom: 1px solid #e2e8f0;">Unit Price</th>
                                                                         <th style="width: 120px; text-align: right; color: #475569; font-weight: 700; border-bottom: 1px solid #e2e8f0;">Subtotal</th>
                                                                     </tr>
                                                                 </thead>
                                                                 <tbody>
                                                                     <?php 
                                                                     $r_item_idx = 0;
                                                                     foreach ($order_items as $item): 
                                                                         $r_item_idx++;
                                                                         $item_qty = intval($item['quantity'] ?? 1);
                                                                         $item_price = floatval($item['unit_price'] ?? 0);
                                                                         $item_subtotal = $item_qty * $item_price;
                                                                         $is_sp = (isset($item['item_type']) && $item['item_type'] === 'SPECIAL_ORDER');
                                                                         $unit_label = ($item_qty > 1 ? 'pcs' : 'pc');
                                                                     ?>
                                                                     <tr>
                                                                         <td style="text-align: center; color: #94a3b8; font-weight: 600; vertical-align: middle;"><?php echo $r_item_idx; ?></td>
                                                                         <td style="vertical-align: middle;">
                                                                             <div style="font-weight: 700; color: #0f172a;">
                                                                                 <?php if ($is_sp): ?>
                                                                                     <span class="label" style="background: #f59e0b; color: #fff; font-size: 10px; padding: 2px 6px; margin-right: 4px;">SPECIAL ORDER</span>
                                                                                 <?php endif; ?>
                                                                                 <?php echo htmlspecialchars($item['product_name'] ?? ''); ?>
                                                                             </div>
                                                                             <?php if (!empty($item['size']) || !empty($item['color'])): ?>
                                                                                 <div style="margin-top: 3px; display: flex; gap: 4px;">
                                                                                     <?php if (!empty($item['size']) && $item['size'] !== '-'): ?>
                                                                                         <span class="badge" style="background: #f1f5f9; color: #475569; font-size: 10.5px; font-weight: 600;">Size: <?php echo htmlspecialchars($item['size']); ?></span>
                                                                                     <?php endif; ?>
                                                                                     <?php if (!empty($item['color']) && $item['color'] !== '-'): ?>
                                                                                         <span class="badge" style="background: #f1f5f9; color: #475569; font-size: 10.5px; font-weight: 600;">Color: <?php echo htmlspecialchars($item['color']); ?></span>
                                                                                     <?php endif; ?>
                                                                                 </div>
                                                                             <?php endif; ?>
                                                                         </td>
                                                                         <td style="text-align: center; font-weight: 700; color: #1e293b; vertical-align: middle;"><?php echo $item_qty; ?> <span style="font-size: 11px; color: #64748b; font-weight: normal;"><?php echo $unit_label; ?></span></td>
                                                                         <td style="text-align: right; color: #475569; font-weight: 600; vertical-align: middle;">&#8369;<?php echo number_format($item_price, 2); ?></td>
                                                                         <td style="text-align: right; font-weight: 800; color: #0f172a; vertical-align: middle;">&#8369;<?php echo number_format($item_subtotal, 2); ?></td>
                                                                     </tr>
                                                                     <?php endforeach; ?>
                                                                 </tbody>
                                                             </table>
                                                         </div>
                                                     </div>

                                                     <!-- Financial Totals Block -->
                                                     <div style="display: flex; justify-content: flex-end;">
                                                         <div style="width: 100%; max-width: 320px; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px 18px;">
                                                             <div style="display: flex; justify-content: space-between; font-size: 13px; color: #475569; margin-bottom: 6px;">
                                                                 <span>Subtotal:</span>
                                                                 <span style="font-weight: 700; color: #0f172a;">&#8369;<?php echo number_format($subtotal_items, 2); ?></span>
                                                             </div>
                                                             <?php if ($delivery_cost > 0): ?>
                                                             <div style="display: flex; justify-content: space-between; font-size: 13px; color: #475569; margin-bottom: 6px;">
                                                                 <span>Delivery Fee:</span>
                                                                 <span style="font-weight: 600; color: #0f172a;">&#8369;<?php echo number_format($delivery_cost, 2); ?></span>
                                                             </div>
                                                             <?php endif; ?>
                                                             <div style="border-top: 2px solid #e2e8f0; padding-top: 8px; margin-top: 6px; display: flex; justify-content: space-between; align-items: baseline;">
                                                                 <span style="font-size: 14px; font-weight: 800; color: #0f172a;">TOTAL PAID:</span>
                                                                 <span style="font-size: 18px; font-weight: 800; color: #059669;">&#8369;<?php echo number_format(floatval($row['paid_amount']), 2); ?></span>
                                                             </div>
                                                             <div style="display: flex; justify-content: space-between; font-size: 12.5px; color: #64748b; margin-top: 6px;">
                                                                 <span>Amount Tendered:</span>
                                                                 <span>&#8369;<?php echo number_format(floatval($row['paid_amount']), 2); ?></span>
                                                             </div>
                                                             <div style="display: flex; justify-content: space-between; font-size: 12.5px; color: #64748b; margin-top: 2px;">
                                                                 <span>Change:</span>
                                                                 <span>&#8369;0.00</span>
                                                             </div>
                                                         </div>
                                                     </div>

                                                     <?php
                                                     $is_credit_row = (($row['payment_status'] ?? '') === 'On Credit' || strpos($row['payment_method'] ?? '', 'Terms') !== false || strpos($row['payment_method'] ?? '', 'Credit') !== false);
                                                     $credit_ref_row = $row['card_number'] ?? '';
                                                     $due_date_row = '';
                                                     if (preg_match('/(?:Due Date|Due):\s*([0-9]{4}-[0-9]{2}-[0-9]{2})/i', $row['bank_transaction_info'] ?? '', $m_due)) {
                                                         $due_date_row = $m_due[1];
                                                     }
                                                     $terms_days_row = '15';
                                                     if (preg_match('/([0-9]+)\s*Days/i', ($row['payment_method'] ?? '') . ' ' . ($row['bank_transaction_info'] ?? ''), $m_d)) {
                                                         $terms_days_row = $m_d[1];
                                                     }
                                                     $invoice_total_row = floatval($subtotal_items) + floatval($delivery_cost);
                                                     if (preg_match('/Total:\s*₱?([0-9,.]+)/i', $row['bank_transaction_info'] ?? '', $m_tot)) {
                                                         $p_tot = floatval(str_replace(',', '', $m_tot[1]));
                                                         if ($p_tot > 0) $invoice_total_row = $p_tot;
                                                     }
                                                     $amount_paid_row = floatval($row['paid_amount']);
                                                     $credit_balance_row = max(0, $invoice_total_row - $amount_paid_row);
                                                     ?>
                                                     <!-- Hidden Silent Thermal Print Element -->
                                                     <div id="receipt-printable-thermal-<?php echo $row['id']; ?>" style="display: none;">
                                                         <div style="font-family: 'Courier New', Consolas, monospace; font-size: 11pt; line-height: 1.25; color: #000; text-align: left;">
                                                             <div style="text-align: center; font-weight: bold; overflow: hidden; white-space: nowrap;">================================</div>
                                                             <div style="text-align: center;">
                                                                 <div style="font-size: 12pt; font-weight: bold; text-transform: uppercase;"><?php echo htmlspecialchars(!empty($sup_data['supplier_name']) ? strtoupper($sup_data['supplier_name']) : 'SAM & INRI CONSTRUCTION SUPPLY'); ?></div>
                                                                 <?php if (!empty($sup_data['supplier_address'])): ?>
                                                                     <div style="font-size: 9.5pt;"><?php echo htmlspecialchars($sup_data['supplier_address']); ?></div>
                                                                 <?php endif; ?>
                                                                 <div style="font-size: 10pt;">Tel: <?php echo htmlspecialchars(!empty($sup_data['supplier_phone']) ? $sup_data['supplier_phone'] : '09612735733'); ?></div>
                                                                 <div style="font-size: 11pt; font-weight: bold; text-transform: uppercase; margin-top: 2px;">P.O.  RECEIPT</div>
                                                                 <div style="font-size: 10pt; font-weight: bold; text-transform: uppercase;"><?php echo $is_credit_row ? '(ON CREDIT)' : '(PAID)'; ?></div>
                                                             </div>
                                                             <div style="text-align: center; font-weight: bold; overflow: hidden; white-space: nowrap;">================================</div>
                                                             <table style="width: 100%; border-collapse: collapse; font-family: inherit; font-size: 10.5pt; margin-bottom: 2px;">
                                                                 <tr><td colspan="2" style="font-weight: bold; padding: 1px 0; white-space: nowrap;">P.O. Receipt No:</td></tr>
                                                                 <tr><td colspan="2" style="padding: 0 0 2px 8px; font-weight: bold;">&nbsp;&nbsp;<?php echo htmlspecialchars($row['txnid'] ?: $row['payment_id']); ?></td></tr>
                                                                 <tr><td style="font-weight: bold;">CUSTOMER:</td><td><?php echo htmlspecialchars($row['customer_name'] ?? 'Walk-in Customer'); ?></td></tr>
                                                                 <tr><td style="font-weight: bold;">PAY METH:</td><td><?php echo htmlspecialchars($row['payment_method'] ?? 'Cash'); ?> <?php echo $is_credit_row ? '(ON CREDIT)' : '(PAID)'; ?></td></tr>
                                                                 <?php if (!empty($credit_ref_row)): ?>
                                                                 <tr><td style="font-weight: bold;">CREDIT REF:</td><td style="font-weight: bold;"><?php echo htmlspecialchars($credit_ref_row); ?></td></tr>
                                                                 <?php endif; ?>
                                                                 <?php if (!empty($due_date_row)): ?>
                                                                 <tr><td style="font-weight: bold;">DUE DATE:</td><td style="font-weight: bold;"><?php echo htmlspecialchars($due_date_row); ?> (<?php echo htmlspecialchars($terms_days_row); ?> Days)</td></tr>
                                                                 <?php endif; ?>
                                                                 <tr><td style="font-weight: bold;">STATUS  :</td><td style="font-weight: bold;"><?php echo $is_credit_row ? 'ON CREDIT' : 'PAID'; ?></td></tr>
                                                                 <tr><td style="font-weight: bold;">DATE    :</td><td><?php echo date('Y-m-d H:i:s', strtotime($row['payment_date'])); ?></td></tr>
                                                             </table>
                                                             <div style="text-align: center; overflow: hidden; white-space: nowrap;">--------------------------------</div>
                                                             <table style="width: 100%; border-collapse: collapse; font-family: inherit; font-size: 10.5pt;">
                                                                 <thead>
                                                                     <tr>
                                                                         <th style="text-align: left; width: 68%;">ITEM DESCRIPTION</th>
                                                                         <th style="text-align: right; width: 32%;">AMOUNT</th>
                                                                     </tr>
                                                                 </thead>
                                                                 <tbody>
                                                                     <?php foreach ($order_items as $item): 
                                                                         $item_qty = intval($item['quantity'] ?? 1);
                                                                         $item_price = floatval($item['unit_price'] ?? 0);
                                                                         $item_subtotal = $item_qty * $item_price;
                                                                         $is_sp = (isset($item['item_type']) && $item['item_type'] === 'SPECIAL_ORDER');
                                                                         $unit_label = ($item_qty > 1 ? 'pcs' : 'pc');
                                                                     ?>
                                                                     <tr>
                                                                         <td colspan="2" style="text-align: left; padding-top: 3px; font-weight: bold; word-break: break-word;">
                                                                             <?php if ($is_sp): ?>[SPECIAL ORDER] <?php endif; ?>
                                                                             <?php echo htmlspecialchars($item['product_name'] ?? ''); ?>
                                                                             <?php if (!empty($item['size']) || !empty($item['color'])): ?>
                                                                                 <div style="font-size: 9pt; font-weight: normal;">
                                                                                     <?php if (!empty($item['size']) && $item['size'] !== '-') echo 'Size: ' . htmlspecialchars($item['size']) . ' '; ?>
                                                                                     <?php if (!empty($item['color']) && $item['color'] !== '-') echo 'Color: ' . htmlspecialchars($item['color']); ?>
                                                                                 </div>
                                                                             <?php endif; ?>
                                                                         </td>
                                                                     </tr>
                                                                     <tr>
                                                                         <td style="text-align: left; padding-left: 8px; padding-bottom: 3px;">
                                                                             <?php echo $item_qty; ?> <?php echo $unit_label; ?> @ <?php echo number_format($item_price, 2); ?>
                                                                         </td>
                                                                         <td style="text-align: right; padding-bottom: 3px; white-space: nowrap; vertical-align: bottom;">
                                                                             <?php echo number_format($item_subtotal, 2); ?>
                                                                         </td>
                                                                     </tr>
                                                                     <?php endforeach; ?>
                                                                 </tbody>
                                                             </table>
                                                             <div style="text-align: center; overflow: hidden; white-space: nowrap;">--------------------------------</div>
                                                             <table style="width: 100%; border-collapse: collapse; font-family: inherit; font-size: 10.5pt; margin: 2px 0;">
                                                                 <tr><td>Subtotal:</td><td style="text-align: right;"><?php echo number_format($subtotal_items, 2); ?></td></tr>
                                                                 <?php if ($delivery_cost > 0): ?>
                                                                 <tr><td>Delivery Fee:</td><td style="text-align: right;"><?php echo number_format($delivery_cost, 2); ?></td></tr>
                                                                 <?php endif; ?>
                                                                 <tr style="font-weight: bold;"><td style="font-size: 1.05em;">TOTAL INVOICE:</td><td style="text-align: right; font-size: 1.05em;">PHP <?php echo number_format($invoice_total_row, 2); ?></td></tr>
                                                                 <?php if ($is_credit_row): ?>
                                                                 <tr><td style="font-weight: bold;">UPFRONT PAID (DEPOSIT):</td><td style="text-align: right; font-weight: bold;">PHP <?php echo number_format($amount_paid_row, 2); ?></td></tr>
                                                                 <tr style="font-weight: 900; font-size: 1.1em; border-top: 1.5px dashed #000; border-bottom: 1.5px dashed #000;"><td style="padding: 2px 0;">BALANCE ON CREDIT:</td><td style="text-align: right; white-space: nowrap;">PHP <?php echo number_format($credit_balance_row, 2); ?></td></tr>
                                                                 <?php if (!empty($due_date_row)): ?>
                                                                 <tr><td style="font-weight: bold;">TERMS DUE DATE:</td><td style="text-align: right; font-weight: bold;"><?php echo htmlspecialchars($due_date_row); ?> (<?php echo htmlspecialchars($terms_days_row); ?> Days)</td></tr>
                                                                 <?php endif; ?>
                                                                 <?php else: ?>
                                                                 <tr style="font-weight: bold;"><td style="font-size: 1.08em;">TOTAL PAID:</td><td style="text-align: right; font-size: 1.08em;">PHP <?php echo number_format($amount_paid_row, 2); ?></td></tr>
                                                                 <tr><td>Tendered:</td><td style="text-align: right;"><?php echo number_format($amount_paid_row, 2); ?></td></tr>
                                                                 <tr><td>Change:</td><td style="text-align: right;">0.00</td></tr>
                                                                 <?php endif; ?>
                                                             </table>
                                                             <div style="text-align: center; font-weight: bold; overflow: hidden; white-space: nowrap;">================================</div>
                                                             <div style="text-align: center; line-height: 1.35; padding: 2px 0;">
                                                                 <div style="font-weight: bold;">*** SALES INVOICE ***</div>
                                                                 <div style="margin-top: 3px;">THANK YOU FOR YOUR PURCHASE!</div>
                                                                 <div style="font-size: 9pt; margin-top: 2px;">eConstruction Supply POS</div>
                                                             </div>
                                                             <div style="text-align: center; font-weight: bold; overflow: hidden; white-space: nowrap;">================================</div>
                                                         </div>
                                                     </div>

                                                 </div>
                                                 <div class="modal-footer" style="background: #f1f5f9; border-top: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; padding: 12px 20px;">
                                                     <button type="button" class="btn btn-default btn-sm" data-dismiss="modal" style="font-weight: 700; border-radius: 5px;">Close</button>
                                                     <button type="button" class="btn btn-success btn-sm" onclick="printReceiptModal(<?php echo $row['id']; ?>)" style="font-weight: 700; background-color: #059669; border-color: #047857; border-radius: 5px; padding: 6px 16px;" title="Print Official Receipt (Thermal)">
                                                         <i class="fa fa-print"></i> Print Official Receipt
                                                     </button>
                                                 </div>
                                             </div>
                                         </div>
                                     </div>
                                </td>

                                <!-- 7. Shipping Status -->
                                <td style="text-align: center; vertical-align: top;">
                                    <?php if($row['shipping_status'] == 'Completed'): ?>
                                        <span class="badge-status-shipped"><i class="fa fa-check"></i> Completed</span>
                                    <?php else: ?>
                                        <span class="badge-status-pending"><i class="fa fa-truck"></i> <?php echo htmlspecialchars($row['shipping_status']); ?></span>
                                        <br>
                                        <a href="shipping-change-status.php?id=<?php echo $row['id']; ?>&task=Completed" class="btn-action-mark-ship">
                                            <i class="fa fa-truck"></i> Mark Complete
                                        </a>
                                    <?php endif; ?>
                                </td>

                                <!-- 8. Action (Delete) -->
                                <td style="text-align: center; vertical-align: top;">
                                    <a href="#" class="btn btn-danger btn-xs" data-href="order-delete.php?id=<?php echo $row['id']; ?>" data-toggle="modal" data-target="#confirm-delete" style="width:100%; border-radius: 4px; padding: 4px 6px;">
                                        <i class="fa fa-trash-o"></i> Delete
                                    </a>
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
</div>

<!-- Modal: Confirmation for Deleting Order -->
<div class="modal fade" id="confirm-delete" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-sm">
        <div class="modal-content" style="border-radius: 8px; overflow: hidden; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.2);">
            <div class="modal-header" style="background: #ef4444; color: #ffffff; padding: 14px 18px;">
                <button type="button" class="close" data-dismiss="modal" aria-hidden="true" style="color: #fff; opacity: 0.8;">&times;</button>
                <h4 class="modal-title" id="myModalLabel" style="font-weight: 800; font-size: 15px;">Delete Confirmation</h4>
            </div>
            <div class="modal-body" style="padding: 20px; font-size: 13.5px; color: #334155; text-align: center;">
                <i class="fa fa-exclamation-triangle" style="font-size: 32px; color: #ef4444; margin-bottom: 10px; display: block;"></i>
                Sure you want to delete this item?
            </div>
            <div class="modal-footer" style="background: #f8fafc; border-top: 1px solid #e2e8f0; padding: 12px 18px; text-align: center;">
                <button type="button" class="btn btn-default" data-dismiss="modal" style="border-radius: 6px; font-weight: 600;">Cancel</button>
                <a class="btn btn-danger btn-ok" style="background: #ef4444; border: none; border-radius: 6px; font-weight: 700;">Delete</a>
            </div>
        </div>
    </div>
</div>

<script>
const MAX_THERMAL_WIDTH_MM = 210;
const MIN_THERMAL_FONT_SIZE = 12;

function getPOSPrintSettings() {
    let settings = {
        printerMode: 'thermal',
        printerType: 'thermal',
        paperWidthMm: 80,
        printContentWidthMm: 72,
        autoAdjustContentWidth: true,
        thermalFontName: 'Courier New',
        thermalDefaultFontSize: 12,
        thermalMinFontSize: 12,
        printWidthA4Mm: 80,
        copies: 1
    };
    try {
        const saved = localStorage.getItem('pos_printer_settings');
        if (saved) {
            const parsed = JSON.parse(saved);
            settings = Object.assign(settings, parsed);
        }
    } catch (e) {}
    if (!settings.paperWidthMm || settings.paperWidthMm > 210 || settings.paperWidthMm === 500 || settings.paperWidthMm === 400 || settings.paperWidthMm === 250) {
        settings.paperWidthMm = 210;
    }
    if (!settings.thermalDefaultFontSize || settings.thermalDefaultFontSize < 12) {
        settings.thermalDefaultFontSize = 12;
    }
    if (!settings.printContentWidthMm) {
        settings.printContentWidthMm = (settings.paperWidthMm === 58) ? 48 : ((settings.paperWidthMm === 80) ? 72 : 120);
    }
    settings.printContentWidthMm = Math.min(settings.paperWidthMm, Math.max(30, Math.round(parseFloat(settings.printContentWidthMm))));
    return settings;
}

function escapeHtml(text) {
    if (!text) return '';
    return String(text)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

function generatePaidOrderThermalHTML(orderData, requestedWidthMm, requestedContentWidthMm) {
    const s = getPOSPrintSettings();
    let paperWidthMm = requestedWidthMm || s.paperWidthMm || 58;
    if (requestedWidthMm) paperWidthMm = requestedWidthMm;
    if (paperWidthMm > 210) paperWidthMm = 210;
    if (paperWidthMm < 40) paperWidthMm = 40;

    let contentWidthMm = requestedContentWidthMm || (requestedWidthMm ? ((paperWidthMm <= 52) ? 44 : ((paperWidthMm <= 65) ? 48 : ((paperWidthMm <= 90) ? 72 : 120))) : s.printContentWidthMm);
    if (!contentWidthMm || isNaN(parseFloat(contentWidthMm))) {
        contentWidthMm = (paperWidthMm <= 52) ? 44 : ((paperWidthMm <= 65) ? 48 : ((paperWidthMm <= 90) ? 72 : 120));
    }
    contentWidthMm = Math.min(paperWidthMm, Math.max(30, Math.round(parseFloat(contentWidthMm))));

    const is58mm = (paperWidthMm <= 65);
    const is50mm = (paperWidthMm <= 52);

    const fontName = s.thermalFontName || 'Courier New';
    const fontStack = `'${fontName.replace(/'/g, "\\'")}', 'Courier New', Courier, monospace, 'Lucida Console', Arial, sans-serif`;

    const fontSizePt = is58mm ? 10 : (is50mm ? 9 : Math.max(10, parseFloat(s.thermalDefaultFontSize) || 12));
    const titleFontSizePt = is58mm ? 12.0 : (is50mm ? 11.0 : Math.max(13.0, fontSizePt + 1.0));
    const totalFontSizePt = is58mm ? 11.0 : (is50mm ? 10.0 : Math.max(13.0, fontSizePt + 1.0));

    const supplierName = orderData.supplier_name || 'SAM & INRI CONSTRUCTION SUPPLY';
    const supplierPhone = orderData.supplier_phone || '09612735733';
    const orderNo = orderData.payment_id || 'POS-20260925-01';
    const dateStr = orderData.payment_date || '';
    const customerName = orderData.customer_name || 'Walk-in Customer';
    const paymentMethod = orderData.payment_method || 'Cash';
    const paymentStatus = (orderData.payment_status || 'PAID').toUpperCase();

    const items = orderData.items || [];
    let itemsRows = '';
    items.forEach(function(item) {
        const rawName = item.name || 'Item';
        const formattedName = escapeHtml(rawName).replace(/\n/g, '<br>');
        const itemQty = parseInt(item.qty, 10) || 1;
        const itemPrice = parseFloat(item.price || 0).toFixed(2);
        const itemAmount = parseFloat(item.amount || (parseFloat(item.price || 0) * itemQty)).toFixed(2);
        const unitLabel = itemQty > 1 ? 'pcs' : 'pc';
        
        itemsRows += `
            <tr>
                <td colspan="2" style="text-align: left; padding-top: 3px; font-weight: bold; word-break: break-word;">${formattedName}</td>
            </tr>
            <tr>
                <td style="text-align: left; padding-left: 8px; padding-bottom: 3px;">${itemQty} ${unitLabel} @ ${itemPrice}</td>
                <td style="text-align: right; padding-bottom: 3px; white-space: nowrap; vertical-align: bottom;">${itemAmount}</td>
            </tr>
        `;
    });

    const subtotal = parseFloat(orderData.subtotal || 0).toFixed(2);
    const delivery = parseFloat(orderData.delivery || 0).toFixed(2);
    const discount = parseFloat(orderData.discount || 0).toFixed(2);
    const total = parseFloat(orderData.total || 0).toFixed(2);
    const isCredit = (orderData.payment_status === 'On Credit' || orderData.payment_status === 'ON CREDIT' || (orderData.credit_balance !== undefined && parseFloat(orderData.credit_balance) > 0) || (orderData.payment_method && (orderData.payment_method.indexOf('Terms') > -1 || orderData.payment_method.indexOf('Credit') > -1)));
    const amountPaid = (orderData.amount_paid !== undefined && orderData.amount_paid !== null) ? parseFloat(orderData.amount_paid).toFixed(2) : (isCredit ? (parseFloat(orderData.tendered || orderData.amount_tendered || 0)).toFixed(2) : total);
    const creditBalance = (orderData.credit_balance !== undefined && orderData.credit_balance !== null) ? parseFloat(orderData.credit_balance).toFixed(2) : (isCredit ? Math.max(0, parseFloat(total) - parseFloat(amountPaid)).toFixed(2) : '0.00');
    const tendered = (orderData.tendered !== undefined && orderData.tendered !== null) ? parseFloat(orderData.tendered).toFixed(2) : (orderData.amount_tendered ? parseFloat(orderData.amount_tendered).toFixed(2) : null);
    const change = (orderData.change !== undefined && orderData.change !== null) ? parseFloat(orderData.change).toFixed(2) : (orderData.change_amount ? parseFloat(orderData.change_amount).toFixed(2) : null);

    let tenderedRows = '';
    if (tendered !== null && parseFloat(tendered) > 0) {
        tenderedRows = `
            <tr>
                <td style="text-align: left; padding: 1px 0;">Tendered:</td>
                <td style="text-align: right; padding: 1px 0; white-space: nowrap;">${tendered}</td>
            </tr>
            <tr>
                <td style="text-align: left; padding: 1px 0;">Change:</td>
                <td style="text-align: right; padding: 1px 0; white-space: nowrap;">${change || '0.00'}</td>
            </tr>
        `;
    }

    return `<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Purchase Order Receipt - ${escapeHtml(orderNo)}</title>
    <style>
        * {
            box-sizing: border-box;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
        @page {
            size: ${paperWidthMm}mm auto;
            margin: 0 !important;
        }
        html, body {
            margin: 0 !important;
            padding: 0 !important;
            background: #ffffff !important;
            color: #000000 !important;
            font-family: ${fontStack} !important;
            font-size: ${fontSizePt}pt !important;
            line-height: 1.25 !important;
            width: ${paperWidthMm}mm !important;
            height: auto !important;
        }
        .thermal-receipt {
            width: ${contentWidthMm}mm !important;
            max-width: ${contentWidthMm}mm !important;
            margin: 0 auto !important;
            padding: 0 !important;
            background: #ffffff !important;
            color: #000000 !important;
            box-sizing: border-box !important;
            font-family: ${fontStack} !important;
        }
        .thermal-divider-double {
            text-align: center;
            font-weight: bold;
            letter-spacing: -0.5px;
            margin: 3px 0;
            overflow: hidden;
            white-space: nowrap;
        }
        .thermal-divider-single {
            text-align: center;
            letter-spacing: -0.5px;
            margin: 2px 0;
            overflow: hidden;
            white-space: nowrap;
        }
        .thermal-header {
            text-align: center;
        }
        .thermal-title {
            font-size: ${titleFontSizePt}pt;
            font-weight: bold;
            text-transform: uppercase;
            line-height: 1.2;
            color: #000000;
        }
        .thermal-phone {
            font-size: ${fontSizePt}pt;
            margin-top: 1px;
            color: #000000;
        }
        .thermal-doc-title {
            font-size: ${fontSizePt}pt;
            font-weight: normal;
            margin-top: 2px;
            color: #000000;
        }
        .thermal-meta {
            margin: 2px 0;
            font-size: ${fontSizePt}pt;
            line-height: 1.25;
            width: 100%;
            border-collapse: collapse;
        }
        .thermal-meta td {
            padding: 1px 0;
            vertical-align: top;
        }
        .thermal-meta .meta-label {
            font-weight: bold;
            white-space: nowrap;
            width: 32%;
        }
        .thermal-table {
            width: 100% !important;
            border-collapse: collapse !important;
            margin: 0 !important;
            font-size: ${fontSizePt}pt !important;
            line-height: 1.2 !important;
        }
        .thermal-table th {
            padding: 1px 0 !important;
            font-weight: bold !important;
            text-transform: uppercase !important;
            color: #000000 !important;
        }
        .thermal-table td {
            color: #000000 !important;
        }
        .thermal-totals {
            width: 100% !important;
            border-collapse: collapse !important;
            margin: 2px 0 !important;
            font-size: ${fontSizePt}pt !important;
            line-height: 1.25 !important;
        }
        .thermal-totals td {
            padding: 1px 0;
            color: #000000 !important;
        }
        .thermal-footer {
            text-align: center;
            font-weight: bold;
            padding: 2px 0;
            text-transform: uppercase;
            font-size: ${fontSizePt}pt;
            color: #000000;
        }
        @media screen {
            body {
                padding: 15px;
                background: #334155;
                display: flex;
                flex-direction: column;
                align-items: center;
                min-height: 100vh;
            }
            .thermal-preview-toolbar {
                width: 100%;
                max-width: ${paperWidthMm}mm;
                margin-bottom: 12px;
                background: #0f172a;
                color: #ffffff;
                padding: 8px 12px;
                border-radius: 4px;
                display: flex;
                justify-content: space-between;
                align-items: center;
                font-family: Arial, sans-serif;
                font-size: 11px;
            }
            .thermal-receipt {
                box-shadow: 0 4px 20px rgba(0,0,0,0.35);
                border: 1px solid #cbd5e1;
                border-radius: 2px;
                padding: 2mm 3mm !important;
            }
        }
        @media print {
            .thermal-preview-toolbar, .no-print {
                display: none !important;
            }
            @page {
                size: ${paperWidthMm}mm auto;
                margin: 0 !important;
            }
            html, body {
                padding: 0 !important;
                margin: 0 !important;
                background: #ffffff !important;
                width: ${paperWidthMm}mm !important;
            }
            .thermal-receipt {
                width: ${contentWidthMm}mm !important;
                max-width: ${contentWidthMm}mm !important;
                margin: 0 auto !important;
                padding: 0 !important;
                box-shadow: none !important;
                border: none !important;
            }
        }
    </style>
</head>
<body>
    <div class="no-print thermal-preview-toolbar">
        <span style="font-weight: bold;">🖨️ Purchase Order Receipt (${paperWidthMm}mm / Content: ${contentWidthMm}mm)</span>
        <div style="display: flex; gap: 6px;">
            <button type="button" onclick="window.print()" style="background: #10b981; color: #fff; border: none; padding: 4px 10px; font-weight: bold; border-radius: 3px; cursor: pointer; font-size: 11px;">Print</button>
            <button type="button" onclick="window.close()" style="background: #64748b; color: #fff; border: none; padding: 4px 8px; font-weight: bold; border-radius: 3px; cursor: pointer; font-size: 11px;">✕</button>
        </div>
    </div>

    <div class="thermal-receipt">
        <div class="thermal-divider-double">================================</div>
        <div class="thermal-header">
            <div class="thermal-title">${escapeHtml(supplierName)}</div>
            <div class="thermal-phone">Tel: ${escapeHtml(supplierPhone)}</div>
            <div class="thermal-doc-title">P.O.  RECEIPT</div>
            <div style="font-size: 10pt; font-weight: bold; text-transform: uppercase;">${isCredit ? '(ON CREDIT)' : '(PAID)'}</div>
        </div>
        <div class="thermal-divider-double">================================</div>

        <table class="thermal-meta">
            <tr>
                <td class="meta-label">RECEIPT NO:</td>
                <td>${escapeHtml(orderNo)}</td>
            </tr>
            <tr>
                <td class="meta-label">CUSTOMER  :</td>
                <td>${escapeHtml(customerName)}</td>
            </tr>
            <tr>
                <td class="meta-label">PAYMENT   :</td>
                <td>${escapeHtml(paymentMethod)} ${isCredit ? '(ON CREDIT)' : '(PAID)'}</td>
            </tr>
            ${orderData.credit_reference_no ? `
            <tr>
                <td class="meta-label">CREDIT REF:</td>
                <td style="font-weight: bold;">${escapeHtml(orderData.credit_reference_no)}</td>
            </tr>
            ` : ''}
            ${orderData.due_date ? `
            <tr>
                <td class="meta-label">DUE DATE  :</td>
                <td style="font-weight: bold;">${escapeHtml(orderData.due_date)}${orderData.payment_term_days ? ' (' + orderData.payment_term_days + ' Days)' : ''}</td>
            </tr>
            ` : ''}
            <tr>
                <td class="meta-label">STATUS    :</td>
                <td style="font-weight: bold;">${isCredit ? 'ON CREDIT' : 'PAID'}</td>
            </tr>
            <tr>
                <td class="meta-label">DATE      :</td>
                <td>${escapeHtml(dateStr)}</td>
            </tr>
        </table>

        <div class="thermal-divider-single">--------------------------------</div>
        <table class="thermal-table">
            <thead>
                <tr>
                    <th style="text-align: left; width: 68%;">ITEM DESCRIPTION</th>
                    <th style="text-align: right; width: 32%;">AMOUNT</th>
                </tr>
            </thead>
        </table>
        <div class="thermal-divider-single">--------------------------------</div>
        <table class="thermal-table">
            <tbody>
                ${itemsRows}
            </tbody>
        </table>
        <div class="thermal-divider-single">--------------------------------</div>

        <table class="thermal-totals">
            <tr>
                <td style="text-align: left;">Subtotal:</td>
                <td style="text-align: right; white-space: nowrap;">${subtotal}</td>
            </tr>
            <tr>
                <td style="text-align: left;">Discount:</td>
                <td style="text-align: right; white-space: nowrap;">${discount}</td>
            </tr>
            <tr>
                <td style="text-align: left;">Delivery:</td>
                <td style="text-align: right; white-space: nowrap;">${delivery}</td>
            </tr>
            <tr style="font-weight: bold;">
                <td style="text-align: left; font-size: ${totalFontSizePt}pt;">TOTAL INVOICE:</td>
                <td style="text-align: right; font-size: ${totalFontSizePt}pt; white-space: nowrap;">PHP ${total}</td>
            </tr>
            ${isCredit ? `
            <tr>
                <td style="text-align: left; font-weight: bold;">UPFRONT PAID (DEPOSIT):</td>
                <td style="text-align: right; font-weight: bold; white-space: nowrap;">PHP ${amountPaid}</td>
            </tr>
            <tr style="font-weight: 900; font-size: ${totalFontSizePt}pt; border-top: 1.5px dashed #000; border-bottom: 1.5px dashed #000;">
                <td style="text-align: left; padding: 2px 0;">BALANCE ON CREDIT:</td>
                <td style="text-align: right; padding: 2px 0; white-space: nowrap;">PHP ${creditBalance}</td>
            </tr>
            ${orderData.due_date ? `
            <tr>
                <td style="text-align: left; font-weight: bold;">TERMS DUE DATE:</td>
                <td style="text-align: right; font-weight: bold; white-space: nowrap;">${escapeHtml(orderData.due_date)}${orderData.payment_term_days ? ' (' + orderData.payment_term_days + ' Days)' : ''}</td>
            </tr>
            ` : ''}
            ` : `
            <tr style="font-weight: bold;">
                <td style="text-align: left; font-size: ${totalFontSizePt}pt;">TOTAL PAID:</td>
                <td style="text-align: right; font-size: ${totalFontSizePt}pt; white-space: nowrap;">PHP ${total}</td>
            </tr>
            ${tenderedRows}
            `}
        </table>

        <div class="thermal-divider-double">================================</div>
        <div class="thermal-footer">
            THANK YOU FOR YOUR BUSINESS!
        </div>
        <div class="thermal-divider-double">================================</div>
    </div>
</body>
</html>`;
}

function printPaidOrderThermal(orderData, widthMm) {
    if (typeof orderData === 'string') {
        try {
            orderData = JSON.parse(orderData);
        } catch (e) {
            console.error('Invalid order data for thermal receipt', e);
            return;
        }
    }
    const html = generatePaidOrderThermalHTML(orderData, widthMm);
    const printWindow = window.open('', '_blank', 'width=500,height=700,menubar=no,toolbar=no,location=no,status=no');
    if (!printWindow) {
        alert('Print popup was blocked by browser. Please allow popups for this site.');
        return;
    }
    printWindow.document.open();
    printWindow.document.write(html);
    printWindow.document.close();
    printWindow.focus();
    setTimeout(function() {
        printWindow.print();
    }, 450);
}

</script>

<?php require_once('footer.php'); ?>
