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
                                    <div id="po-modal-<?php echo $row['id']; ?>" class="modal fade" role="dialog" tabindex="-1">
                                        <div class="modal-dialog modal-lg">
                                            <div class="modal-content" style="border-radius: 8px; overflow: hidden; border: none; box-shadow: 0 10px 35px rgba(0,0,0,0.25);">
                                                <div class="modal-header" style="background-color: #0284c7; color: #fff; text-align: left; padding: 15px 20px;">
                                                    <button type="button" class="close" data-dismiss="modal" style="color: #fff; opacity: 1;">&times;</button>
                                                    <h4 class="modal-title" style="font-weight: bold; margin: 0;"><i class="fa fa-file-text-o"></i> Customer Purchase Order</h4>
                                                </div>
                                                <div class="modal-body" id="po-print-area-<?php echo $row['id']; ?>" style="text-align: left; padding: 20px; background: #fff;">
                                                    <div style="font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; padding: 15px; color: #333; border: 1px solid #e2e8f0; border-radius: 6px;">
                                                        
                                                        <!-- Header -->
                                                        <div style="border-bottom: 2px solid #0284c7; padding-bottom: 15px; margin-bottom: 20px;">
                                                            <div class="row">
                                                                <div class="col-xs-7">
                                                                    <h3 style="margin: 0; color: #0284c7; font-weight: bold; letter-spacing: 0.5px;">E-CONSTRUCTION SUPPLY</h3>
                                                                    <p style="margin: 3px 0 0 0; font-size: 13px; color: #64748b;">Customer Purchase Order Voucher</p>
                                                                </div>
                                                                <div class="col-xs-5 text-right">
                                                                    <div style="background: #e0f2fe; color: #0369a1; font-weight: bold; padding: 5px 10px; border-radius: 4px; display: inline-block; font-size: 13px; margin-bottom: 5px;">
                                                                        Transaction ID: <?php echo htmlspecialchars($row['txnid'] ?: $row['payment_id']); ?>
                                                                    </div>
                                                                    <div>
                                                                        <span class="label label-success" style="font-size: 11px;">
                                                                            <?php echo htmlspecialchars($row['payment_status']); ?>
                                                                        </span>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <!-- Info Grid -->
                                                        <div class="row" style="margin-bottom: 15px;">
                                                            <div class="col-xs-4">
                                                                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 4px; padding: 10px; min-height: 140px;">
                                                                    <h5 style="margin: 0 0 6px 0; font-weight: bold; color: #1e293b; border-bottom: 1px solid #cbd5e1; padding-bottom: 4px; font-size: 12px; text-transform: uppercase;">
                                                                        <i class="fa fa-user"></i> Customer Info
                                                                    </h5>
                                                                    <p style="margin: 0; font-size: 12px; font-weight: bold;"><?php echo htmlspecialchars($row['customer_name']); ?></p>
                                                                    <p style="margin: 2px 0 0 0; font-size: 11px; color: #555;">Email: <?php echo htmlspecialchars($row['customer_email']); ?></p>
                                                                    <?php
                                                                    $statement_c = $pdo->prepare("SELECT * FROM tbl_customer WHERE cust_id=?");
                                                                    $statement_c->execute(array($row['customer_id']));
                                                                    $c_data = $statement_c->fetch(PDO::FETCH_ASSOC);
                                                                    if($c_data):
                                                                    ?>
                                                                        <p style="margin: 2px 0 0 0; font-size: 11px; color: #555;">Phone: <?php echo htmlspecialchars($c_data['cust_phone'] ?? $c_data['cust_b_phone'] ?? 'N/A'); ?></p>
                                                                        <p style="margin: 2px 0 0 0; font-size: 11px; color: #555;">Barangay: <?php echo htmlspecialchars($c_data['cust_s_state'] ?? $c_data['cust_state'] ?? ''); ?></p>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </div>

                                                            <div class="col-xs-4">
                                                                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 4px; padding: 10px; min-height: 140px;">
                                                                    <h5 style="margin: 0 0 6px 0; font-weight: bold; color: #1e293b; border-bottom: 1px solid #cbd5e1; padding-bottom: 4px; font-size: 12px; text-transform: uppercase;">
                                                                        <i class="fa fa-building"></i> Store / Supplier
                                                                    </h5>
                                                                    <?php
                                                                    $statement_s = $pdo->prepare("SELECT * FROM tbl_supplier WHERE supplier_id=?");
                                                                    $statement_s->execute(array($row['supplier_id']));
                                                                    $s_data = $statement_s->fetch(PDO::FETCH_ASSOC);
                                                                    if($s_data):
                                                                    ?>
                                                                        <p style="margin: 0; font-size: 12px; font-weight: bold;"><?php echo htmlspecialchars($s_data['supplier_name']); ?></p>
                                                                        <p style="margin: 2px 0 0 0; font-size: 11px; color: #555;"><?php echo nl2br(htmlspecialchars($s_data['supplier_address'])); ?></p>
                                                                        <p style="margin: 2px 0 0 0; font-size: 11px; color: #555;">Phone: <?php echo htmlspecialchars($s_data['supplier_phone']); ?></p>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </div>

                                                            <div class="col-xs-4">
                                                                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 4px; padding: 10px; min-height: 140px;">
                                                                    <h5 style="margin: 0 0 6px 0; font-weight: bold; color: #1e293b; border-bottom: 1px solid #cbd5e1; padding-bottom: 4px; font-size: 12px; text-transform: uppercase;">
                                                                        <i class="fa fa-info-circle"></i> Order Details
                                                                    </h5>
                                                                    <p style="margin: 0; font-size: 11px;"><strong>Date:</strong> <?php echo date('M d, Y h:i A', strtotime($row['payment_date'])); ?></p>
                                                                    <p style="margin: 2px 0 0 0; font-size: 11px;"><strong>Method:</strong> <?php echo htmlspecialchars($row['payment_method']); ?></p>
                                                                    <p style="margin: 2px 0 0 0; font-size: 11px;"><strong>Type:</strong> <?php echo htmlspecialchars($row['bank_transaction_info'] ?? 'Purchase Order'); ?></p>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <!-- Items Table -->
                                                        <table class="table table-bordered table-striped" style="margin-bottom: 15px; font-size: 12px;">
                                                            <thead>
                                                                <tr style="background: #f1f5f9;">
                                                                    <th>#</th>
                                                                    <th>Item Description</th>
                                                                    <th>Size</th>
                                                                    <th>Color</th>
                                                                    <th class="text-center" style="width: 70px;">Qty</th>
                                                                    <th class="text-right" style="width: 100px;">Unit Price</th>
                                                                    <th class="text-right" style="width: 110px;">Total</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                <?php
                                                                $statement_po_items = $pdo->prepare("SELECT * FROM tbl_order WHERE payment_id=?");
                                                                $statement_po_items->execute(array($row['payment_id']));
                                                                $po_items = $statement_po_items->fetchAll(PDO::FETCH_ASSOC);
                                                                $po_subtotal = 0;
                                                                $po_c = 0;
                                                                foreach($po_items as $p_item):
                                                                    $po_c++;
                                                                    $p_line = floatval($p_item['unit_price']) * intval($p_item['quantity']);
                                                                    $po_subtotal += $p_line;
                                                                    $is_so = (isset($p_item['item_type']) && $p_item['item_type'] === 'SPECIAL_ORDER');
                                                                ?>
                                                                <tr <?php echo $is_so ? 'style="background-color: #fffbeb;"' : ''; ?>>
                                                                    <td><?php echo $po_c; ?></td>
                                                                    <td>
                                                                        <?php if ($is_so): ?>
                                                                            <span class="label label-warning" style="background:#d97706; font-size:9px; font-weight:bold;">SPECIAL ORDER</span><br>
                                                                        <?php endif; ?>
                                                                        <strong><?php echo htmlspecialchars($p_item['product_name']); ?></strong>
                                                                        <?php if ($is_so && !empty($p_item['product_details'])): ?>
                                                                            <div style="font-size:11px; color:#64748b;"><?php echo htmlspecialchars($p_item['product_details']); ?></div>
                                                                        <?php endif; ?>
                                                                        <?php if ($is_so && !empty($p_item['special_order_reference'])): ?>
                                                                            <div style="font-size:10px; color:#0284c7; font-family:monospace;">Ref: <?php echo htmlspecialchars($p_item['special_order_reference']); ?></div>
                                                                        <?php endif; ?>
                                                                    </td>
                                                                    <td><?php echo htmlspecialchars($p_item['size'] ?: '-'); ?></td>
                                                                    <td><?php echo htmlspecialchars($p_item['color'] ?: '-'); ?></td>
                                                                    <td class="text-center"><?php echo htmlspecialchars($p_item['quantity']); ?></td>
                                                                    <td class="text-right">&#8369;<?php echo number_format(floatval($p_item['unit_price']), 2); ?></td>
                                                                    <td class="text-right">&#8369;<?php echo number_format($p_line, 2); ?></td>
                                                                </tr>
                                                                <?php endforeach; ?>
                                                            </tbody>
                                                        </table>

                                                        <!-- Totals -->
                                                        <div class="row">
                                                            <div class="col-xs-7">
                                                                <p style="font-size: 11px; color: #64748b; margin-top: 10px;">
                                                                    <em>This document serves as the customer's official Purchase Order voucher upon presenting at the store.</em>
                                                                </p>
                                                            </div>
                                                            <div class="col-xs-5">
                                                                <table style="width: 100%; font-size: 12px;">
                                                                    <tr>
                                                                        <td style="padding: 3px 0;">Items Subtotal:</td>
                                                                        <td class="text-right" style="padding: 3px 0;">&#8369;<?php echo number_format($po_subtotal, 2); ?></td>
                                                                    </tr>
                                                                    <?php
                                                                    $po_delivery = floatval($row['paid_amount']) - $po_subtotal;
                                                                    if($po_delivery > 0):
                                                                    ?>
                                                                    <tr>
                                                                        <td style="padding: 3px 0;">Delivery Fee:</td>
                                                                        <td class="text-right" style="padding: 3px 0;">&#8369;<?php echo number_format($po_delivery, 2); ?></td>
                                                                    </tr>
                                                                    <?php else: ?>
                                                                    <tr>
                                                                        <td style="padding: 3px 0;">Delivery Fee:</td>
                                                                        <td class="text-right" style="padding: 3px 0; color: #16a34a;">&#8369;0.00 (Store Pick-up)</td>
                                                                    </tr>
                                                                    <?php endif; ?>
                                                                    <tr style="border-top: 2px solid #0284c7; font-weight: bold; font-size: 14px; color: #0284c7;">
                                                                        <td style="padding: 6px 0;">Total Payable:</td>
                                                                        <td class="text-right" style="padding: 6px 0;">&#8369;<?php echo number_format(floatval($row['paid_amount']), 2); ?></td>
                                                                    </tr>
                                                                </table>
                                                            </div>
                                                        </div>

                                                    </div>
                                                </div>
                                                <div class="modal-footer" style="background: #f8fafc; border-top: 1px solid #e2e8f0; padding: 12px 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
                                                    <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                                                        <button type="button" class="btn btn-primary" onclick="printReceipt('po-print-area-<?php echo $row['id']; ?>', 'a4')" style="font-weight: 700; background-color: #0284c7; border-color: #0284c7;">
                                                            <i class="fa fa-print"></i> Print Purchase Order (A4 / Standard)
                                                        </button>
                                                        <button type="button" class="btn btn-default" onclick="printReceipt('po-print-area-<?php echo $row['id']; ?>', 'pdf')" style="font-weight: 600; background: #fff; border-color: #cbd5e1; color: #334155;">
                                                            <i class="fa fa-file-pdf-o text-danger"></i> PDF Document
                                                        </button>
                                                    </div>
                                                    <button type="button" class="btn btn-default" data-dismiss="modal" style="font-weight: 700;">Close</button>
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
                                     
                                    <!-- Modal for View Official Receipt -->
                                    <div id="receipt-modal-<?php echo $row['id']; ?>" class="modal fade" role="dialog" tabindex="-1">
                                         <div class="modal-dialog modal-md">
                                             <div class="modal-content" style="border-radius: 8px; overflow: hidden; border: none; box-shadow: 0 10px 35px rgba(0,0,0,0.25);">
                                                 <div class="modal-header" style="text-align: left; background: #0284c7; color: #fff; padding: 15px 20px;">
                                                     <button type="button" class="close" data-dismiss="modal" style="color: #fff; opacity: 1;">&times;</button>
                                                     <h4 class="modal-title" style="font-weight: bold; margin: 0;"><i class="fa fa-file-text-o"></i> Official Sales Receipt</h4>
                                                 </div>
                                                 <div class="modal-body" id="receipt-print-area-<?php echo $row['id']; ?>" style="text-align: left; padding: 20px; background: #fff;">
                                                     <div class="receipt-box-printable">
                                                         <!-- Header -->
                                                         <div style="text-align: center; margin-bottom: 20px;">
                                                             <h2 style="margin: 0; color: #337ab7; font-weight: bold; letter-spacing: 1px;">E-CONSTRUCTION SUPPLY</h2>
                                                             <p style="margin: 5px 0 0 0; font-size: 14px; color: #777;">Online Construction Supply</p>
                                                             <hr style="margin: 15px 0; border: 0; border-top: 2px dashed #ddd;">
                                                             <h3 style="margin: 0; font-size: 17px; font-weight: bold; color: #555; text-transform: uppercase;">Official Sales Receipt</h3>
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
                                                                     <p style="margin: 3px 0 0 0; font-size: 12px;"><strong>Payment:</strong> <?php echo htmlspecialchars($row['payment_method']); ?></p>
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
                                                             <?php
                                                             $statement_c = $pdo->prepare("SELECT * FROM tbl_customer WHERE cust_id=?");
                                                             $statement_c->execute(array($row['customer_id']));
                                                             $cust_data = $statement_c->fetch(PDO::FETCH_ASSOC);
                                                             if ($cust_data) {
                                                                 $cust_country_id = $cust_data['cust_country'];
                                                                 $statement_cnt = $pdo->prepare("SELECT * FROM tbl_country WHERE country_id=?");
                                                                 $statement_cnt->execute(array($cust_country_id));
                                                                 $cnt_data = $statement_cnt->fetch(PDO::FETCH_ASSOC);
                                                                 $cust_city_town = $cnt_data ? $cnt_data['country_name'] : '';
                                                                 ?>
                                                                 <p style="margin: 3px 0 0 0; font-size: 12px; color: #555;">Address: <?php echo htmlspecialchars($cust_data['cust_address']); ?>, <?php echo htmlspecialchars($cust_city_town); ?>, <?php echo htmlspecialchars($cust_data['cust_zip']); ?></p>
                                                                 <?php
                                                             }
                                                             ?>
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
                                                                 $subtotal_items = 0;
                                                                 foreach ($order_items as $item):
                                                                     $item_subtotal = floatval($item['unit_price']) * intval($item['quantity']);
                                                                     $subtotal_items += $item_subtotal;
                                                                 ?>
                                                                 <tr>
                                                                     <td style="border-top: 1px solid #eee; padding: 8px 5px; text-align: left;">
                                                                         <strong><?php echo htmlspecialchars($item['product_name']); ?></strong>
                                                                         <?php if(!empty($item['size']) || !empty($item['color'])): ?>
                                                                             <br><span style="font-size: 11px; color: #777;">(Size: <?php echo htmlspecialchars($item['size']); ?>, Color: <?php echo htmlspecialchars($item['color']); ?>)</span>
                                                                         <?php endif; ?>
                                                                     </td>
                                                                     <td style="border-top: 1px solid #eee; text-align: center; padding: 8px 5px;"><?php echo htmlspecialchars($item['quantity']); ?></td>
                                                                     <td style="border-top: 1px solid #eee; text-align: right; padding: 8px 5px;">&#8369;<?php echo number_format(floatval($item['unit_price']), 2); ?></td>
                                                                     <td style="border-top: 1px solid #eee; text-align: right; padding: 8px 5px;">&#8369;<?php echo number_format($item_subtotal, 2); ?></td>
                                                                 </tr>
                                                                 <?php endforeach; ?>
                                                                 
                                                                 <?php
                                                                 $delivery_cost = floatval($row['paid_amount']) - $subtotal_items;
                                                                 if ($delivery_cost < 0) {
                                                                     $delivery_cost = 0;
                                                                 }
                                                                 ?>
                                                                 <!-- Totals -->
                                                                 <tr>
                                                                     <td colspan="2" style="border-top: 2px solid #ddd;"></td>
                                                                     <td style="border-top: 2px solid #ddd; text-align: right; font-weight: bold; padding: 8px 5px;">Subtotal:</td>
                                                                     <td style="border-top: 2px solid #ddd; text-align: right; font-weight: bold; padding: 8px 5px;">&#8369;<?php echo number_format($subtotal_items, 2); ?></td>
                                                                 </tr>
                                                                 <tr>
                                                                     <td colspan="2" style="border: none;"></td>
                                                                     <td style="border: none; text-align: right; font-weight: bold; padding: 4px 5px; color: #777;">Delivery:</td>
                                                                     <td style="border: none; text-align: right; font-weight: bold; padding: 4px 5px; color: #777;">&#8369;<?php echo number_format($delivery_cost, 2); ?></td>
                                                                 </tr>
                                                                 <tr style="font-size: 15px; background: #f5f5f5;">
                                                                     <td colspan="2" style="border-top: 1px solid #ddd;"></td>
                                                                     <td style="border-top: 1px solid #ddd; text-align: right; font-weight: bold; padding: 8px 5px; color: #337ab7;">Total Paid:</td>
                                                                     <td style="border-top: 1px solid #ddd; text-align: right; font-weight: bold; padding: 8px 5px; color: #337ab7;">&#8369;<?php echo number_format(floatval($row['paid_amount']), 2); ?></td>
                                                                 </tr>
                                                             </tbody>
                                                         </table>

                                                         <!-- Footer Message -->
                                                         <div style="text-align: center; margin-top: 25px; font-size: 12px; color: #999;">
                                                             <p style="margin: 0; font-weight: bold;">Thank you for your order!</p>
                                                             <p style="margin: 5px 0 0 0; font-size: 11px; color: #aaa;">We appreciate your trust in us for your construction needs.</p>
                                                             <p style="margin: 5px 0 0 0;">This is a system-generated official sales receipt.</p>
                                                         </div>
                                                     </div>
                                                 </div>
                                                 <?php
                                                 $po_order_thermal_data = [
                                                     'supplier_name' => !empty($sup_data['supplier_name']) ? $sup_data['supplier_name'] : 'SAM & INRI CONSTRUCTION SUPPLY',
                                                     'supplier_phone' => !empty($sup_data['supplier_phone']) ? $sup_data['supplier_phone'] : '09612735733',
                                                     'payment_id' => $row['payment_id'],
                                                     'payment_date' => date('d M Y', strtotime($row['payment_date'])),
                                                     'customer_name' => $row['customer_name'],
                                                     'payment_method' => $row['payment_method'],
                                                     'payment_status' => $row['payment_status'],
                                                     'items' => array_map(function($it) {
                                                         return [
                                                             'name' => $it['product_name'],
                                                             'qty' => (int)$it['quantity'],
                                                             'price' => (float)$it['unit_price'],
                                                             'amount' => (float)$it['unit_price'] * (int)$it['quantity']
                                                         ];
                                                     }, $order_items),
                                                     'subtotal' => (float)$subtotal_items,
                                                     'delivery' => (float)$delivery_cost,
                                                     'total' => (float)$row['paid_amount']
                                                 ];
                                                 $po_thermal_json = htmlspecialchars(json_encode($po_order_thermal_data), ENT_QUOTES, 'UTF-8');
                                                 ?>
                                                  <div class="modal-footer" style="background: #f8fafc; border-top: 1px solid #e2e8f0; padding: 12px 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
                                                      <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                                                          <!-- MODE 1: THERMAL PRINTER (PRIMARY / DEFAULT / PRODUCTION: 210mm Wide Roll) -->
                                                        <button type="button" class="btn btn-primary" onclick="printPaidOrderThermal(<?php echo $po_thermal_json; ?>, 210)" style="font-weight: 700; background-color: #0284c7; border-color: #0284c7;" title="Print on 210mm Wide Thermal Roll (Primary Default Standard)">
                                                            <i class="fa fa-print"></i> Thermal Receipt (210mm Default)
                                                        </button>
                                                          <!-- Compact Thermal Fallback (80mm / 58mm) -->
                                                          <button type="button" class="btn btn-default" onclick="printPaidOrderThermal(<?php echo $po_thermal_json; ?>, 80)" style="font-weight: 600; background: #fff; border-color: #cbd5e1; color: #334155;" title="Print on 80mm or 58mm Thermal Roll">
                                                              <i class="fa fa-print"></i> 80mm / 58mm
                                                          </button>
                                                          <!-- MODE 2: PDF PRINTER / MODE 3: STANDARD A4 PRINTER -->
                                                          <button type="button" class="btn btn-default" onclick="printReceipt('receipt-print-area-<?php echo $row['id']; ?>', 'pdf')" style="font-weight: 600; background: #fff; border-color: #cbd5e1; color: #334155;" title="Standard A4 or PDF Document">
                                                              <i class="fa fa-file-pdf-o text-danger"></i> PDF / A4 Receipt
                                                          </button>
                                                      </div>
                                                      <button type="button" class="btn btn-default" data-dismiss="modal" style="font-weight: 700;">Close</button>
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

function generatePaidOrderThermalHTML(orderData, requestedWidthMm, requestedContentWidthMm, isPdfPreview = false) {
    const s = getPOSPrintSettings();
    let paperWidthMm = requestedWidthMm || s.paperWidthMm || 80;
    if (requestedWidthMm) paperWidthMm = requestedWidthMm;
    if (paperWidthMm > 210) paperWidthMm = 210;
    if (paperWidthMm < 40) paperWidthMm = 40;

    let contentWidthMm = requestedContentWidthMm || s.printContentWidthMm;
    if (!contentWidthMm || isNaN(parseFloat(contentWidthMm))) {
        contentWidthMm = (paperWidthMm <= 52) ? 44 : ((paperWidthMm <= 65) ? 48 : ((paperWidthMm <= 90) ? 72 : 120));
    }
    contentWidthMm = Math.min(paperWidthMm, Math.max(30, Math.round(parseFloat(contentWidthMm))));

    const fontName = s.thermalFontName || 'Courier New';
    const fontStack = `'${fontName.replace(/'/g, "\\'")}', 'Courier New', Courier, monospace, 'Lucida Console', Arial, sans-serif`;

    const fontSizePt = Math.max(12, parseFloat(s.thermalDefaultFontSize) || 12);
    const titleFontSizePt = Math.max(13.0, fontSizePt + 1.0);
    const totalFontSizePt = Math.max(13.0, fontSizePt + 1.0);

    const supplierName = orderData.supplier_name || 'SAM & INRI CONSTRUCTION SUPPLY';
    const orderNo = orderData.payment_id || 'PO-000123';
    const dateStr = orderData.payment_date || '';
    const customerName = orderData.customer_name || 'Juan Dela Cruz';
    const paymentMethod = orderData.payment_method || 'Cash';
    const paymentStatus = (orderData.payment_status || 'PAID').toUpperCase();

    const items = orderData.items || [];
    let itemsRows = '';
    items.forEach(function(item) {
        const rawName = item.name || 'Item';
        const formattedName = escapeHtml(rawName).replace(/\n/g, '<br>');
        const itemQty = parseInt(item.qty, 10) || 1;
        const itemAmount = parseFloat(item.amount || (parseFloat(item.price || 0) * itemQty)).toFixed(2);
        
        itemsRows += `
            <tr>
                <td style="text-align: left; padding: 2.5px 0; vertical-align: top; word-break: break-word; overflow-wrap: break-word; line-height: 1.25;">${formattedName}</td>
                <td style="text-align: center; padding: 2.5px 4px; vertical-align: top; white-space: nowrap;">${itemQty}</td>
                <td style="text-align: right; padding: 2.5px 0; vertical-align: top; white-space: nowrap;">${itemAmount}</td>
            </tr>
        `;
    });

    const subtotal = parseFloat(orderData.subtotal || 0).toFixed(2);
    const delivery = parseFloat(orderData.delivery || 0);
    const discount = parseFloat(orderData.discount || 0);
    const total = parseFloat(orderData.total || 0).toFixed(2);

    let deliveryRow = '';
    if (delivery > 0) {
        deliveryRow = `
            <tr>
                <td colspan="2" style="text-align: left; padding: 1.5px 0;">Delivery Fee:</td>
                <td style="text-align: right; padding: 1.5px 0; white-space: nowrap;">${delivery.toFixed(2)}</td>
            </tr>
        `;
    }

    let discountRow = '';
    if (discount > 0) {
        discountRow = `
            <tr>
                <td colspan="2" style="text-align: left; padding: 1.5px 0;">Discount:</td>
                <td style="text-align: right; padding: 1.5px 0; white-space: nowrap;">-${discount.toFixed(2)}</td>
            </tr>
        `;
    }

    const docPageTitle = isPdfPreview 
        ? `Thermal Receipt PDF Preview - ${escapeHtml(orderNo)}` 
        : `Paid Order Thermal Receipt - ${escapeHtml(orderNo)}`;

    return `<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>${docPageTitle}</title>
    <style>
        * {
            box-sizing: border-box;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
        @page {
            size: ${paperWidthMm}mm auto;
            margin: 0;
        }
        html, body {
            margin: 0 !important;
            padding: 0 !important;
            background: #ffffff !important;
            color: #000000 !important;
            font-family: ${fontStack} !important;
            font-size: ${fontSizePt}pt !important;
            line-height: 1.3 !important;
            width: 100% !important;
            height: auto !important;
        }
        .thermal-receipt {
            width: ${contentWidthMm}mm !important;
            max-width: ${contentWidthMm}mm !important;
            min-width: ${contentWidthMm}mm !important;
            margin: 0 auto !important;
            padding: 2mm 3mm !important;
            background: #ffffff !important;
            color: #000000 !important;
            box-sizing: border-box !important;
        }
        .thermal-header {
            text-align: center;
            margin-bottom: 6px;
        }
        .thermal-title {
            font-size: ${titleFontSizePt}pt;
            font-weight: bold;
            text-transform: uppercase;
            line-height: 1.25;
            color: #000000;
        }
        .thermal-subtitle {
            font-size: ${fontSizePt}pt;
            font-weight: bold;
            text-transform: uppercase;
            margin-top: 2px;
            color: #000000;
        }
        .thermal-meta {
            margin: 6px 0;
            font-size: ${fontSizePt}pt;
            line-height: 1.35;
        }
        .thermal-divider {
            border-top: 1pt dashed #000000;
            margin: 5px 0;
        }
        .thermal-table {
            width: 100% !important;
            border-collapse: collapse !important;
            table-layout: fixed !important;
            margin: 4px 0 !important;
            font-size: ${fontSizePt}pt !important;
        }
        .thermal-table th {
            padding: 3px 0 !important;
            border-top: 1pt dashed #000000 !important;
            border-bottom: 1pt dashed #000000 !important;
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
            margin: 4px 0 !important;
            font-size: ${fontSizePt}pt !important;
        }
        .thermal-totals td {
            color: #000000 !important;
        }
        .thermal-footer {
            text-align: center;
            margin-top: 8px;
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
                max-width: ${Math.max(paperWidthMm, 80)}mm;
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
                width: ${contentWidthMm}mm !important;
                max-width: ${contentWidthMm}mm !important;
                min-width: ${contentWidthMm}mm !important;
                box-shadow: 0 4px 20px rgba(0,0,0,0.35);
                border: 1px solid #cbd5e1;
                border-radius: 2px;
                margin: 0 auto !important;
            }
        }
        @media print {
            .thermal-preview-toolbar, .no-print {
                display: none !important;
            }
            html, body {
                padding: 0 !important;
                margin: 0 !important;
                background: #ffffff !important;
                width: 100% !important;
            }
            .thermal-receipt {
                width: ${contentWidthMm}mm !important;
                max-width: ${contentWidthMm}mm !important;
                min-width: ${contentWidthMm}mm !important;
                margin: 0 auto !important;
                box-shadow: none !important;
                border: none !important;
            }
        }
    </style>
</head>
<body>
    <div class="no-print thermal-preview-toolbar">
        <span style="font-weight: bold;">🖨️ ${isPdfPreview ? 'Thermal Receipt (PDF Preview)' : 'Thermal Receipt'} (${paperWidthMm}mm / Content: ${contentWidthMm}mm)</span>
        <div style="display: flex; gap: 6px;">
            <button type="button" onclick="window.print()" style="background: #10b981; color: #fff; border: none; padding: 4px 10px; font-weight: bold; border-radius: 3px; cursor: pointer; font-size: 11px;">🖨️ ${isPdfPreview ? 'Print / Save as PDF' : 'Print'}</button>
            <button type="button" onclick="window.close()" style="background: #64748b; color: #fff; border: none; padding: 4px 8px; font-weight: bold; border-radius: 3px; cursor: pointer; font-size: 11px;">✕</button>
        </div>
    </div>

    <div class="thermal-receipt">
        <div class="thermal-header">
            <div class="thermal-title">${escapeHtml(supplierName)}</div>
            <div class="thermal-subtitle">PAID ORDER</div>
        </div>

        <div class="thermal-meta">
            <div>Order No: ${escapeHtml(orderNo)}</div>
            <div>Date: ${escapeHtml(dateStr)}</div>
            <div>Customer: ${escapeHtml(customerName)}</div>
        </div>

        <table class="thermal-table">
            <thead>
                <tr>
                    <th style="text-align: left; width: 54%;">ITEM</th>
                    <th style="text-align: center; width: 18%;">QTY</th>
                    <th style="text-align: right; width: 28%;">AMOUNT</th>
                </tr>
            </thead>
            <tbody>
                ${itemsRows}
            </tbody>
        </table>

        <div class="thermal-divider"></div>

        <table class="thermal-totals">
            <tr>
                <td colspan="2" style="text-align: left; padding: 1.5px 0;">Subtotal</td>
                <td style="text-align: right; padding: 1.5px 0; white-space: nowrap;">${subtotal}</td>
            </tr>
            ${deliveryRow}
            ${discountRow}
            <tr style="font-weight: bold;">
                <td colspan="2" style="text-align: left; padding: 3px 0; border-top: 1pt dashed #000; border-bottom: 1pt dashed #000;">TOTAL</td>
                <td style="text-align: right; padding: 3px 0; border-top: 1pt dashed #000; border-bottom: 1pt dashed #000; font-size: ${totalFontSizePt}pt; white-space: nowrap;">${total}</td>
            </tr>
        </table>

        <div class="thermal-divider"></div>

        <div class="thermal-meta" style="margin-top: 4px;">
            <div><strong>PAYMENT STATUS:</strong> ${escapeHtml(paymentStatus)}</div>
            <div><strong>Payment Method:</strong> ${escapeHtml(paymentMethod)}</div>
        </div>

        <div class="thermal-footer">
            <div>Thank you</div>
        </div>
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

// Print Utility for PO Voucher and Official Receipt (PDF / Standard Print)
function printReceipt(divId, format) {
    var printContents = document.getElementById(divId);
    if (!printContents) return;

    var frame = document.createElement('iframe');
    frame.style.position = 'fixed';
    frame.style.right = '0';
    frame.style.bottom = '0';
    frame.style.width = '0';
    frame.style.height = '0';
    frame.style.border = '0';
    document.body.appendChild(frame);

    var doc = frame.contentWindow.document;
    doc.open();
    doc.write('<!DOCTYPE html><html><head><title>Print Document</title>');
    doc.write('<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">');
    doc.write('<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">');
    doc.write('<style>');
    doc.write('body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif; padding: 20px; color: #1e293b; background: #fff; }');
    doc.write('@media print { body { padding: 0; } }');
    doc.write('</style>');
    doc.write('</head><body>');
    doc.write(printContents.innerHTML);
    doc.write('</body></html>');
    doc.close();

    setTimeout(function() {
        frame.contentWindow.focus();
        frame.contentWindow.print();
        setTimeout(function() {
            document.body.removeChild(frame);
        }, 1000);
    }, 500);
}
</script>

<?php require_once('footer.php'); ?>
