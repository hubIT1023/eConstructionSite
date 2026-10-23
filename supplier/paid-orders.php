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
        $error_message .= "Message can not be empty\n";
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
        		$payment_details = 'Transaction Id: '.$row['txnid'].'<br>';
        	elseif($row['payment_method'] == 'Stripe'):
				$payment_details = '
Transaction Id: '.$row['txnid'].'<br>
Card number: '.$row['card_number'].'<br>
Card CVV: '.$row['card_cvv'].'<br>
Card Month: '.$row['card_month'].'<br>
Card Year: '.$row['card_year'].'<br>';
        	elseif($row['payment_method'] == 'Bank Deposit'):
				$payment_details = 'Transaction Details: <br>'.$row['bank_transaction_info'];
        	elseif($row['payment_method'] == 'Over the Counter'):
				$payment_details = 'Transaction Details: Over the Counter Payment';
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
Payment Id: '.$row['payment_id'].'<br>';
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
Unit Price: '.$row['unit_price'].'<br>';
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
</body></html>';
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

// Load all supplier staff / cashiers for mapping
$supplier_staff_members = [];
try {
    $stmt_staff = $pdo->prepare("SELECT id, full_name, email, role FROM tbl_supplier_user WHERE supplier_id = ? ORDER BY id ASC");
    $stmt_staff->execute(array($supplier_id));
    $supplier_staff_members = $stmt_staff->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $supplier_staff_members = [];
}

// Helper: Resolve cashier / staff member responsible for order
if (!function_exists('resolve_paid_order_cashier')) {
    function resolve_paid_order_cashier($row, $pdo, &$staff_members = []) {
        // 0. Dedicated cashier_name from tbl_payment
        if (!empty($row['cashier_name'])) {
            $c_name = trim($row['cashier_name']);
            if (strcasecmp($c_name, 'Online Customer') === 0) {
                return ['name' => 'Online Customer', 'type' => 'online', 'badge_class' => 'badge-cashier-online', 'label' => 'Self-Checkout'];
            }
            $c_label = 'POS Cashier';
            foreach ($staff_members as $sm) {
                if ((!empty($row['cashier_id']) && $sm['id'] == $row['cashier_id']) || (!empty($sm['full_name']) && strcasecmp($sm['full_name'], $c_name) === 0)) {
                    $c_label = ucwords(strtolower($sm['role'] ?? 'Cashier'));
                    break;
                }
            }
            return ['name' => $c_name, 'type' => 'pos_cashier', 'badge_class' => 'badge-cashier-pos', 'label' => $c_label];
        }

        $info = !empty($row['bank_transaction_info']) ? $row['bank_transaction_info'] : '';
        
        // 1. Explicit regex checks in bank_transaction_info
        if (preg_match('/(?:Staff|Cashier|User|Handled by|Processed by):\s*([A-Za-z0-9\.\s\-_&]+?)(?:\||\(|\n|\r|$)/i', $info, $m)) {
            $name = trim($m[1]);
            if (!empty($name)) {
                return ['name' => $name, 'type' => 'pos_cashier', 'badge_class' => 'badge-cashier-pos', 'label' => 'POS Cashier'];
            }
        }
        if (preg_match('/Sent by\s+([^:]+):\s*([A-Za-z0-9\.\s\-_&]+?)(?:\)|\||\n|\r|$)/i', $info, $m)) {
            $name = trim($m[2]);
            if (!empty($name)) {
                return ['name' => $name, 'type' => 'pos_cashier', 'badge_class' => 'badge-cashier-pos', 'label' => 'POS Staff'];
            }
        }
        if (preg_match('/by\s+([A-Za-z0-9\.\s\-_&]+?)\s+on\s+[0-9]{4}-[0-9]{2}-[0-9]{2}/i', $info, $m)) {
            $name = trim($m[1]);
            if (!empty($name)) {
                return ['name' => $name, 'type' => 'credit_settler', 'badge_class' => 'badge-cashier-settle', 'label' => 'Settled By'];
            }
        }

        // 2. Check tbl_credit_payments for settlement cashier
        try {
            $stmt_cp = $pdo->prepare("SELECT cashier_name FROM tbl_credit_payments WHERE payment_id = ? AND cashier_name IS NOT NULL AND cashier_name != '' ORDER BY id DESC LIMIT 1");
            $stmt_cp->execute(array($row['payment_id']));
            $cp = $stmt_cp->fetch(PDO::FETCH_ASSOC);
            if (!empty($cp['cashier_name'])) {
                return ['name' => $cp['cashier_name'], 'type' => 'credit_settler', 'badge_class' => 'badge-cashier-settle', 'label' => 'Settled By'];
            }
        } catch (Exception $e) {}

        // 3. Check tbl_discount_requests for cashier who requested/processed discounts
        try {
            $stmt_dr = $pdo->prepare("SELECT cashier_name, requester_role FROM tbl_discount_requests WHERE payment_id = ? AND cashier_name IS NOT NULL AND cashier_name != '' LIMIT 1");
            $stmt_dr->execute(array($row['payment_id']));
            $dr = $stmt_dr->fetch(PDO::FETCH_ASSOC);
            if (!empty($dr['cashier_name'])) {
                return ['name' => $dr['cashier_name'], 'type' => 'pos_cashier', 'badge_class' => 'badge-cashier-pos', 'label' => 'POS Cashier'];
            }
        } catch (Exception $e) {}

        // 4. Match any active supplier staff names found inside transaction string
        foreach ($staff_members as $sm) {
            if (!empty($sm['full_name']) && stripos($info, $sm['full_name']) !== false) {
                return ['name' => $sm['full_name'], 'type' => 'pos_cashier', 'badge_class' => 'badge-cashier-pos', 'label' => ucwords(strtolower($sm['role'] ?? 'Staff'))];
            }
        }

        // 5. Online storefront payments
        $is_online = in_array($row['payment_method'], ['PayPal', 'Stripe', 'Bank Deposit']) && strpos($row['payment_id'], 'POS-') === false && strpos($row['payment_id'], 'PO-') === false;
        if ($is_online) {
            return ['name' => 'Online Customer', 'type' => 'online', 'badge_class' => 'badge-cashier-online', 'label' => 'Self-Checkout'];
        }

        // 6. POS Order default to active counter cashier or primary manager
        if (!empty($staff_members)) {
            foreach ($staff_members as $sm) {
                if (in_array(strtoupper(trim($sm['role'])), ['CASHIER', 'EMPLOYEE', 'OPERATOR'])) {
                    return ['name' => $sm['full_name'], 'type' => 'pos_cashier', 'badge_class' => 'badge-cashier-pos', 'label' => ucwords(strtolower($sm['role']))];
                }
            }
            return ['name' => $staff_members[0]['full_name'], 'type' => 'pos_cashier', 'badge_class' => 'badge-cashier-pos', 'label' => ucwords(strtolower($staff_members[0]['role'] ?? 'Staff'))];
        }

        return ['name' => 'Counter Cashier', 'type' => 'pos_cashier', 'badge_class' => 'badge-cashier-pos', 'label' => 'Cashier'];
    }
}
?>

<style>
/* Modern Elegant Theme for Paid Orders */
.paid-orders-container {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    color: #1e293b;
    padding-bottom: 30px;
}

/* Cashier Badges */
.badge-cashier-pos {
    background: #0284c7;
    color: #ffffff;
    font-size: 11px;
    font-weight: 700;
    padding: 4px 8px;
    border-radius: 6px;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.badge-cashier-settle {
    background: #059669;
    color: #ffffff;
    font-size: 11px;
    font-weight: 700;
    padding: 4px 8px;
    border-radius: 6px;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.badge-cashier-online {
    background: #64748b;
    color: #ffffff;
    font-size: 11px;
    font-weight: 700;
    padding: 4px 8px;
    border-radius: 6px;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.cashier-subtext {
    font-size: 10.5px;
    color: #64748b;
    margin-top: 2px;
    font-weight: 600;
}

/* Header Banner */
.paid-header-banner {
    background: #ffffff;
    border-bottom: 1px solid #e2e8f0;
    padding: 24px 28px;
    margin-bottom: 25px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.02);
}
.paid-header-flex {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
}
.paid-header-title {
    margin: 0;
    font-size: 24px;
    font-weight: 800;
    color: #0f172a;
    letter-spacing: -0.5px;
    display: flex;
    align-items: center;
    gap: 10px;
}
.paid-header-title i {
    color: #059669;
}
.paid-header-sub {
    margin: 4px 0 0 0;
    font-size: 13.5px;
    color: #64748b;
}

.btn-nav-receive {
    background: #0284c7;
    color: #ffffff !important;
    font-weight: 700;
    font-size: 13px;
    padding: 9px 18px;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    text-decoration: none !important;
    box-shadow: 0 2px 6px rgba(2, 132, 199, 0.25);
    transition: all 0.2s;
}
.btn-nav-receive:hover {
    background: #0369a1;
    transform: translateY(-1px);
    box-shadow: 0 4px 10px rgba(2, 132, 199, 0.35);
}

/* KPI Summary Cards */
.paid-metric-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 18px 20px;
    margin-bottom: 20px;
    box-shadow: 0 2px 8px rgba(15, 23, 42, 0.04);
    transition: transform 0.2s, box-shadow 0.2s;
}
.paid-metric-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 14px rgba(15, 23, 42, 0.07);
}
.paid-metric-top {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
}
.paid-metric-icon {
    width: 44px;
    height: 44px;
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
#paidOrdersTable, #example1 {
    width: 100% !important;
    border-collapse: collapse !important;
    border: 1px solid #e2e8f0;
}
#paidOrdersTable thead th, #example1 thead th {
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
#paidOrdersTable tbody td, #example1 tbody td {
    padding: 14px;
    vertical-align: top;
    font-size: 12.5px;
    color: #334155;
    border-bottom: 1px solid #f1f5f9;
    background: #ffffff;
}
#paidOrdersTable tbody tr:hover td, #example1 tbody tr:hover td {
    background-color: #f8fafc;
}
#paidOrdersTable thead th:nth-child(3),
#paidOrdersTable tbody td:nth-child(3) {
    max-width: 280px;
    word-break: break-word;
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
    cursor: pointer;
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
    cursor: pointer;
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
    cursor: pointer;
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
                <table id="paidOrdersTable" class="table table-bordered table-hover table-striped">
                    <thead>
                        <tr>
                            <th style="width: 40px; text-align: center;">#</th>
                            <th style="width: 190px;">Customer</th>
                            <th style="width: 250px;">Product Details</th>
                            <th style="width: 190px;">Payment Information</th>
                            <th style="width: 135px; text-align: center;">Cashier / Staff</th>
                            <th style="width: 110px; text-align: right;">Paid Amount</th>
                            <th style="width: 125px; text-align: center;">Payment Status</th>
                            <th style="width: 125px; text-align: center;">Shipping Status</th>
                            <th style="width: 60px; text-align: center;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $i=0;
                        $statement = $pdo->prepare("SELECT * FROM tbl_payment WHERE supplier_id=? AND (payment_status = 'Paid' OR payment_status = 'Completed') ORDER by id DESC");
                        $statement->execute(array($supplier_id));
                        $result = $statement->fetchAll(PDO::FETCH_ASSOC);

                        // Batch-fetch order items for all paid orders in 1 fast query
                        $all_order_items = [];
                        try {
                            $stmt_all_items = $pdo->prepare("SELECT o.*, p.p_sku FROM tbl_order o LEFT JOIN tbl_product p ON o.product_id = p.p_id JOIN tbl_payment pay ON o.payment_id = pay.payment_id WHERE pay.supplier_id=? AND (pay.payment_status = 'Paid' OR pay.payment_status = 'Completed') ORDER BY o.id ASC");
                            $stmt_all_items->execute(array($supplier_id));
                            while ($it_row = $stmt_all_items->fetch(PDO::FETCH_ASSOC)) {
                                $all_order_items[$it_row['payment_id']][] = $it_row;
                            }
                        } catch (Exception $e_items) {
                            $all_order_items = [];
                        }

                        foreach ($result as $row) {
                            $i++;
                            $cashier_info = resolve_paid_order_cashier($row, $pdo, $supplier_staff_members);
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
                                    
                                    <button type="button" class="btn-action-send-msg" 
                                        data-cust-id="<?php echo htmlspecialchars($row['customer_id']); ?>" 
                                        data-cust-name="<?php echo htmlspecialchars($row['customer_name']); ?>" 
                                        data-payment-id="<?php echo htmlspecialchars($row['payment_id']); ?>" 
                                        onclick="openSharedSendMsg(this)">
                                        <i class="fa fa-envelope-o"></i> Send Message
                                    </button>
                                </td>

                                <!-- 3. Product Details -->
                                <td>
                                   <?php
                                   $order_items_row = isset($all_order_items[$row['payment_id']]) ? $all_order_items[$row['payment_id']] : [];
                                   foreach ($order_items_row as $row1) {
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

                                    <!-- Purchase Order Voucher Button -->
                                    <div style="margin-top: 8px;">
                                        <button type="button" class="btn-action-po-voucher" onclick="openSharedPOVoucher('<?php echo $row['id']; ?>', '<?php echo htmlspecialchars($row['payment_id']); ?>')">
                                            <i class="fa fa-file-text-o"></i> Purchase Order Voucher
                                        </button>
                                    </div>
                                </td>

                                <!-- 5. Cashier / Staff -->
                                <td style="text-align: center; vertical-align: top;">
                                    <span class="badge <?php echo $cashier_info['badge_class']; ?>">
                                        <i class="fa <?php echo ($cashier_info['type'] === 'online') ? 'fa-globe' : (($cashier_info['type'] === 'credit_settler') ? 'fa-id-badge' : 'fa-user'); ?>"></i>
                                        <?php echo htmlspecialchars($cashier_info['name']); ?>
                                    </span>
                                    <div class="cashier-subtext">
                                        <?php echo htmlspecialchars($cashier_info['label']); ?>
                                    </div>
                                </td>

                                <!-- 6. Paid Amount -->
                                <td style="text-align: right; vertical-align: top; font-weight: bold; font-size: 13.5px; color: #059669;">
                                    &#8369;<?php echo number_format(floatval($row['paid_amount']), 2); ?>
                                </td>

                                <!-- 7. Payment Status & View Receipt Button -->
                                <td style="text-align: center; vertical-align: top;">
                                    <span class="badge-status-paid"><i class="fa fa-check-circle"></i> <?php echo htmlspecialchars($row['payment_status']); ?></span>
                                    <br>
                                    <button type="button" class="btn-action-view-receipt" onclick="openSharedReceipt('<?php echo $row['id']; ?>', '<?php echo htmlspecialchars($row['payment_id']); ?>')">
                                        <i class="fa fa-print"></i> View Receipt
                                    </button>
                                </td>

                                <!-- 8. Shipping Status -->
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

                                <!-- 9. Action (Delete) -->
                                <td style="text-align: center; vertical-align: top;">
                                    <button type="button" class="btn btn-danger btn-xs btn-delete-order" data-href="order-delete.php?id=<?php echo $row['id']; ?>" data-id="<?php echo $row['id']; ?>" style="width:100%; border-radius: 4px; padding: 4px 6px;">
                                        <i class="fa fa-trash-o"></i> Delete
                                    </button>
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

<!-- ============================================================= -->
<!-- MODAL 1: Confirmation for Deleting Order                      -->
<!-- ============================================================= -->
<div class="modal fade" id="confirm-delete" tabindex="-1" role="dialog" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-sm">
        <div class="modal-content" style="border-radius: 8px; overflow: hidden; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.2);">
            <div class="modal-header" style="background: #ef4444; color: #ffffff; padding: 14px 18px;">
                <button type="button" class="close" data-dismiss="modal" aria-hidden="true" style="color: #fff; opacity: 0.8;">&times;</button>
                <h4 class="modal-title" id="deleteModalLabel" style="font-weight: 800; font-size: 15px;">Delete Confirmation</h4>
            </div>
            <div class="modal-body" style="padding: 20px; font-size: 13.5px; color: #334155; text-align: center;">
                <i class="fa fa-exclamation-triangle" style="font-size: 32px; color: #ef4444; margin-bottom: 10px; display: block;"></i>
                Sure you want to delete this item?
            </div>
            <div class="modal-footer" style="background: #f8fafc; border-top: 1px solid #e2e8f0; padding: 12px 18px; text-align: center;">
                <button type="button" class="btn btn-default" data-dismiss="modal" style="border-radius: 6px; font-weight: 600;">Cancel</button>
                <a class="btn btn-danger btn-ok" href="#" style="background: #ef4444; border: none; border-radius: 6px; font-weight: 700;">Delete</a>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================= -->
<!-- MODAL 2: Single Shared Send Message to Customer Modal        -->
<!-- ============================================================= -->
<div id="shared-send-msg-modal" class="modal fade" role="dialog" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content" style="border-radius: 8px; overflow: hidden; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.2);">
            <div class="modal-header-modern" style="background: #0f172a; color: #ffffff; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center;">
                <h4 style="margin: 0; font-size: 16px; font-weight: 700; color: #ffffff;"><i class="fa fa-paper-plane-o"></i> Send Message to Customer</h4>
                <button type="button" class="close" data-dismiss="modal" style="color: #ffffff; opacity: 0.8;">&times;</button>
            </div>
            <div class="modal-body" style="font-size: 13.5px; padding: 20px; background: #ffffff;">
                <form action="" method="post" id="sharedSendMsgForm">
                    <input type="hidden" name="cust_id" id="shared_msg_cust_id" value="">
                    <input type="hidden" name="payment_id" id="shared_msg_payment_id" value="">
                    <div class="form-group">
                        <label style="font-weight: bold; color: #334155;">Customer</label>
                        <input type="text" id="shared_msg_cust_name" class="form-control" style="border-radius: 6px;" readonly>
                    </div>
                    <div class="form-group">
                        <label style="font-weight: bold; color: #334155;">Subject</label>
                        <input type="text" name="subject_text" id="shared_msg_subject" class="form-control" style="border-radius: 6px;" value="" required>
                    </div>
                    <div class="form-group">
                        <label style="font-weight: bold; color: #334155;">Message</label>
                        <textarea name="message_text" id="shared_msg_text" class="form-control" rows="8" style="border-radius: 6px; resize: vertical;" placeholder="Type your message here..." required></textarea>
                    </div>
                    <div style="text-align: right; margin-top: 15px;">
                        <button type="button" class="btn btn-default" data-dismiss="modal" style="border-radius: 6px; font-weight: 600;">Close</button>
                        <input type="submit" value="Send Message" name="form1" class="btn btn-primary" style="background: #0284c7; border: none; border-radius: 6px; font-weight: bold; padding: 6px 16px;">
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================= -->
<!-- MODAL 3: Single Shared Customer Purchase Order Voucher Modal -->
<!-- ============================================================= -->
<div id="shared-po-modal" class="modal fade" role="dialog" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg" style="max-width: 720px; margin-top: 30px;">
        <div class="modal-content" style="border-radius: 10px; overflow: hidden; border: none; box-shadow: 0 15px 40px rgba(0,0,0,0.2);">
            
            <!-- Modern Modal Header -->
            <div class="modal-header-modern" style="background: #0284c7; display: flex; justify-content: space-between; align-items: center; padding: 14px 20px; color: #ffffff;">
                <h4 class="modal-title" style="margin: 0; font-size: 16px; font-weight: 700; display: flex; align-items: center; gap: 8px; color: #ffffff;">
                    <i class="fa fa-file-text-o"></i> Customer Purchase Order Voucher — <span id="shared-po-title-txnid" style="font-family: monospace; font-size: 17px;">---</span>
                </h4>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <button type="button" class="btn btn-xs btn-default" onclick="printCurrentPOVoucher()" style="background: #ffffff; color: #0284c7; font-weight: 700; border: none; border-radius: 5px; padding: 6px 14px; font-size: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                        <i class="fa fa-print"></i> Print Voucher
                    </button>
                    <button type="button" class="close" data-dismiss="modal" aria-hidden="true" style="color: #ffffff; opacity: 0.9; font-size: 24px; margin: 0; line-height: 1;">&times;</button>
                </div>
            </div>

            <!-- Modern Modal Body -->
            <div class="modal-body" id="shared-po-body" style="padding: 20px; background: #f8fafc;">
                <!-- Populated dynamically via AJAX -->
            </div>

            <!-- Modal Footer -->
            <div class="modal-footer" style="background: #f1f5f9; border-top: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; padding: 12px 20px;">
                <button type="button" class="btn btn-default btn-sm" data-dismiss="modal" style="font-weight: 700; border-radius: 5px;">Close</button>
                <div style="display: flex; gap: 8px;">
                    <button type="button" class="btn btn-primary btn-sm" onclick="printCurrentPOVoucher()" style="font-weight: 700; background-color: #0284c7; border-color: #0369a1; border-radius: 5px; padding: 6px 16px;" title="Print Voucher on Thermal Printer">
                        <i class="fa fa-print"></i> Print Purchase Order Voucher
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================= -->
<!-- MODAL 4: Single Shared Official Receipt Modal                -->
<!-- ============================================================= -->
<div id="shared-receipt-modal" class="modal fade" role="dialog" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg" style="max-width: 720px; margin-top: 30px;">
        <div class="modal-content" style="border-radius: 10px; overflow: hidden; border: none; box-shadow: 0 15px 40px rgba(0,0,0,0.2);">
            
            <!-- Modern Modal Header -->
            <div class="modal-header-modern" style="background: #059669; display: flex; justify-content: space-between; align-items: center; padding: 14px 20px; color: #ffffff;">
                <h4 class="modal-title" style="margin: 0; font-size: 16px; font-weight: 700; display: flex; align-items: center; gap: 8px; color: #ffffff;">
                    <i class="fa fa-check-circle"></i> P.O.  RECEIPT — <span id="shared-receipt-title-txnid" style="font-family: monospace; font-size: 17px;">---</span>
                </h4>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <button type="button" class="btn btn-xs btn-default" onclick="printCurrentReceipt()" style="background: #ffffff; color: #059669; font-weight: 700; border: none; border-radius: 5px; padding: 6px 14px; font-size: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                        <i class="fa fa-print"></i> Print Receipt
                    </button>
                    <button type="button" class="close" data-dismiss="modal" aria-hidden="true" style="color: #ffffff; opacity: 0.9; font-size: 24px; margin: 0; line-height: 1;">&times;</button>
                </div>
            </div>

            <!-- Modern Modal Body -->
            <div class="modal-body" id="shared-receipt-body" style="padding: 20px; background: #f8fafc;">
                <!-- Populated dynamically via AJAX -->
            </div>

            <!-- Modal Footer -->
            <div class="modal-footer" style="background: #f1f5f9; border-top: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; padding: 12px 20px;">
                <button type="button" class="btn btn-default btn-sm" data-dismiss="modal" style="font-weight: 700; border-radius: 5px;">Close</button>
                <button type="button" class="btn btn-success btn-sm" onclick="printCurrentReceipt()" style="font-weight: 700; background-color: #059669; border-color: #047857; border-radius: 5px; padding: 6px 16px;" title="Print Official Receipt (Thermal)">
                    <i class="fa fa-print"></i> Print Official Receipt
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Cache for fetched order details to prevent repeated network requests
window.paidOrderDetailsCache = {};
window.currentLoadedOrderData = null;

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

function formatMoney(num) {
    return (parseFloat(num) || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

// -------------------------------------------------------------
// 1. Send Message Modal Controller
// -------------------------------------------------------------
function openSharedSendMsg(btn) {
    var custId = $(btn).attr('data-cust-id');
    var custName = $(btn).attr('data-cust-name') || 'Customer';
    var paymentId = $(btn).attr('data-payment-id');
    
    $('#shared_msg_cust_id').val(custId);
    $('#shared_msg_payment_id').val(paymentId);
    $('#shared_msg_cust_name').val(custName + ' (ID: ' + custId + ')');
    $('#shared_msg_subject').val('Update regarding Order #' + paymentId);
    $('#shared_msg_text').val('');
    $('#shared-send-msg-modal').modal('show');
}

// -------------------------------------------------------------
// 2. PO Voucher Modal Controller
// -------------------------------------------------------------
function openSharedPOVoucher(orderId, paymentId) {
    var cacheKey = paymentId || orderId;
    $('#shared-po-title-txnid').text(paymentId || orderId);
    $('#shared-po-modal').modal('show');

    if (window.paidOrderDetailsCache[cacheKey]) {
        renderSharedPOModal(window.paidOrderDetailsCache[cacheKey]);
        return;
    }

    $('#shared-po-body').html(
        '<div style="text-align: center; padding: 45px 20px;">' +
        '<i class="fa fa-spinner fa-spin fa-3x" style="color: #0284c7;"></i>' +
        '<p style="margin-top: 14px; color: #64748b; font-weight: 600; font-size: 14px;">Loading Purchase Order Voucher Details...</p>' +
        '</div>'
    );

    $.ajax({
        url: 'get-paid-order-details-ajax.php',
        type: 'GET',
        data: { id: orderId, payment_id: paymentId },
        dataType: 'json',
        success: function(res) {
            if (res && res.success && res.order) {
                window.paidOrderDetailsCache[cacheKey] = res.order;
                renderSharedPOModal(res.order);
            } else {
                $('#shared-po-body').html(
                    '<div class="alert alert-danger" style="margin: 15px;">' +
                    '<i class="fa fa-exclamation-triangle"></i> ' +
                    (res && res.message ? escapeHtml(res.message) : 'Failed to retrieve order details.') +
                    '</div>'
                );
            }
        },
        error: function() {
            $('#shared-po-body').html(
                '<div class="alert alert-danger" style="margin: 15px;">' +
                '<i class="fa fa-exclamation-triangle"></i> Network error loading order details. Please try again.' +
                '</div>'
            );
        }
    });
}

function renderSharedPOModal(order) {
    window.currentLoadedOrderData = order;
    $('#shared-po-title-txnid').text(order.txnid || order.payment_id);

    var isPaid = (order.payment_status === 'Paid' || order.payment_status === 'Completed');
    var statusBg = isPaid ? '#059669' : '#0284c7';
    var statusText = isPaid ? 'Paid / Settle' : escapeHtml(order.payment_status);
    var statusDesc = isPaid ? 'Purchase Order fulfilled &amp; recorded' : 'Purchase Order pending settlement';

    var items = order.items || [];
    var itemsRows = '';
    var idx = 0;
    items.forEach(function(item) {
        idx++;
        var skuDisplay = item.sku ? item.sku : 'N/A';
        var isSpecial = item.is_special_order;
        var nameHtml = isSpecial ? '<span class="label label-warning" style="background:#d97706; font-size:9.5px; font-weight:bold; padding:2px 5px; border-radius:3px; margin-right:4px;">SPECIAL ORDER</span>' + escapeHtml(item.name) : escapeHtml(item.name);
        
        itemsRows += '<tr>' +
            '<td style="text-align: center; vertical-align: middle; color: #64748b; font-weight: 600;">' + idx + '</td>' +
            '<td style="vertical-align: middle;">' +
                '<div style="font-weight: 700; color: #0f172a;">' + nameHtml + '</div>' +
                (item.details ? '<div style="font-size: 11px; color: #64748b; margin-top: 2px;">' + escapeHtml(item.details) + '</div>' : '') +
            '</td>' +
            '<td style="text-align: center; vertical-align: middle;"><span style="background: #f1f5f9; color: #475569; font-family: monospace; font-size: 11px; padding: 2px 6px; border-radius: 4px; font-weight: 600;">' + escapeHtml(skuDisplay) + '</span></td>' +
            '<td style="vertical-align: middle; font-size: 12px; color: #475569;">' + escapeHtml(item.specs || 'Standard') + '</td>' +
            '<td style="text-align: center; vertical-align: middle; font-weight: 700;">' + (item.qty || 1) + '</td>' +
            '<td style="text-align: right; vertical-align: middle;">PHP ' + formatMoney(item.price) + '</td>' +
            '<td style="text-align: right; vertical-align: middle; font-weight: 700; color: #0284c7;">PHP ' + formatMoney(item.amount) + '</td>' +
        '</tr>';
    });

    var html = '' +
        '<div style="background: #ecfdf5; border: 1.5px solid #a7f3d0; border-radius: 8px; padding: 12px 18px; margin-bottom: 18px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">' +
            '<div style="display: flex; align-items: center; gap: 10px;">' +
                '<span class="label" style="background: ' + statusBg + '; color: #ffffff; font-size: 12px; font-weight: 800; padding: 5px 10px; border-radius: 4px; text-transform: uppercase;">' +
                    '<i class="fa fa-check-circle"></i> ' + statusText +
                '</span>' +
                '<span style="font-size: 12.5px; color: #065f46; font-weight: 600;">' + statusDesc + '</span>' +
            '</div>' +
            '<div style="font-size: 12px; color: #64748b; font-weight: 600;">' +
                '<i class="fa fa-calendar"></i> ' + escapeHtml(order.formatted_date) +
            '</div>' +
        '</div>' +

        '<div class="row" style="margin-bottom: 18px;">' +
            '<div class="col-sm-6 col-xs-12" style="margin-bottom: 10px;">' +
                '<div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px 16px; height: 100%;">' +
                    '<div style="font-size: 11px; font-weight: 800; text-transform: uppercase; color: #64748b; margin-bottom: 8px; letter-spacing: 0.5px;">' +
                        '<i class="fa fa-user"></i> Customer Information' +
                    '</div>' +
                    '<div style="font-size: 14px; font-weight: 700; color: #0f172a; margin-bottom: 3px;">' +
                        escapeHtml(order.customer_name) +
                    '</div>' +
                    (order.customer_phone ? '<div style="font-size: 12px; color: #475569; margin-bottom: 2px;"><i class="fa fa-phone" style="width: 14px; color: #0284c7;"></i> ' + escapeHtml(order.customer_phone) + '</div>' : '') +
                    (order.customer_email ? '<div style="font-size: 12px; color: #475569; margin-bottom: 2px;"><i class="fa fa-envelope" style="width: 14px; color: #0284c7;"></i> ' + escapeHtml(order.customer_email) + '</div>' : '') +
                    (order.customer_address ? '<div style="font-size: 11.5px; color: #64748b;"><i class="fa fa-map-marker" style="width: 14px; color: #0284c7;"></i> ' + escapeHtml(order.customer_address) + '</div>' : '') +
                '</div>' +
            '</div>' +
            '<div class="col-sm-6 col-xs-12" style="margin-bottom: 10px;">' +
                '<div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px 16px; height: 100%;">' +
                    '<div style="font-size: 11px; font-weight: 800; text-transform: uppercase; color: #64748b; margin-bottom: 8px; letter-spacing: 0.5px;">' +
                        '<i class="fa fa-building-o"></i> Store &amp; Fulfillment' +
                    '</div>' +
                    '<div style="font-size: 13.5px; font-weight: 700; color: #0f172a; margin-bottom: 3px;">' +
                        escapeHtml(order.supplier_name) +
                    '</div>' +
                    '<div style="font-size: 12px; color: #475569; margin-bottom: 4px;">' +
                        (order.shipping_status === 'Completed' ? '<span class="label" style="background: #059669; font-size: 10.5px; padding: 3px 7px; border-radius: 3px;"><i class="fa fa-check"></i> Completed</span>' : '<span class="label" style="background: #0284c7; font-size: 10.5px; padding: 3px 7px; border-radius: 3px;"><i class="fa fa-truck"></i> ' + escapeHtml(order.shipping_status) + '</span>') +
                        '<span style="margin-left: 6px; font-weight: 600;">Method: ' + escapeHtml(order.payment_method) + '</span>' +
                    '</div>' +
                    '<div style="font-size: 12px; color: #475569; margin-bottom: 4px;">' +
                        '<i class="fa fa-user" style="width: 14px; color: #0284c7;"></i> <strong>Cashier:</strong> ' + escapeHtml(order.cashier_name) +
                    '</div>' +
                    (order.supplier_phone ? '<div style="font-size: 11.5px; color: #64748b;"><i class="fa fa-phone" style="width: 14px;"></i> Tel: ' + escapeHtml(order.supplier_phone) + '</div>' : '') +
                '</div>' +
            '</div>' +
        '</div>' +

        '<div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; margin-bottom: 18px;">' +
            '<div style="padding: 10px 16px; background: #f1f5f9; border-bottom: 1px solid #e2e8f0; font-size: 12px; font-weight: 800; text-transform: uppercase; color: #475569; letter-spacing: 0.5px;">' +
                '<i class="fa fa-shopping-cart"></i> Ordered Products (' + items.length + ')' +
            '</div>' +
            '<div class="table-responsive" style="margin-bottom: 0;">' +
                '<table class="table table-hover" style="margin-bottom: 0; font-size: 13px;">' +
                    '<thead style="background: #f8fafc;">' +
                        '<tr>' +
                            '<th style="width: 40px; text-align: center; color: #64748b; font-weight: 700; border-bottom: 1px solid #e2e8f0;">#</th>' +
                            '<th style="color: #475569; font-weight: 700; border-bottom: 1px solid #e2e8f0;">Item Description</th>' +
                            '<th style="width: 90px; text-align: center; color: #475569; font-weight: 700; border-bottom: 1px solid #e2e8f0;">SKU</th>' +
                            '<th style="width: 130px; color: #475569; font-weight: 700; border-bottom: 1px solid #e2e8f0;">Specs</th>' +
                            '<th style="width: 60px; text-align: center; color: #475569; font-weight: 700; border-bottom: 1px solid #e2e8f0;">Qty</th>' +
                            '<th style="width: 110px; text-align: right; color: #475569; font-weight: 700; border-bottom: 1px solid #e2e8f0;">Unit Price</th>' +
                            '<th style="width: 120px; text-align: right; color: #475569; font-weight: 700; border-bottom: 1px solid #e2e8f0;">Total</th>' +
                        '</tr>' +
                    '</thead>' +
                    '<tbody>' + itemsRows + '</tbody>' +
                '</table>' +
            '</div>' +
        '</div>' +

        '<div class="row">' +
            '<div class="col-sm-5 col-sm-offset-7 col-xs-12">' +
                '<div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px 16px;">' +
                    '<div style="display: flex; justify-content: space-between; margin-bottom: 6px; font-size: 13px; color: #475569;">' +
                        '<span>Subtotal:</span>' +
                        '<span style="font-weight: 600;">PHP ' + formatMoney(order.subtotal) + '</span>' +
                    '</div>' +
                    '<div style="display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 13px; color: #475569;">' +
                        '<span>Delivery Fee:</span>' +
                        '<span style="font-weight: 600;">PHP ' + formatMoney(order.delivery) + '</span>' +
                    '</div>' +
                    '<div style="display: flex; justify-content: space-between; padding-top: 8px; border-top: 2px solid #e2e8f0; font-size: 15px; font-weight: 800; color: #0284c7;">' +
                        '<span>Total Amount:</span>' +
                        '<span>PHP ' + formatMoney(order.total) + '</span>' +
                    '</div>' +
                '</div>' +
            '</div>' +
        '</div>';

    $('#shared-po-body').html(html);
}

// -------------------------------------------------------------
// 3. Official Receipt Modal Controller
// -------------------------------------------------------------
function openSharedReceipt(orderId, paymentId) {
    var cacheKey = paymentId || orderId;
    $('#shared-receipt-title-txnid').text(paymentId || orderId);
    $('#shared-receipt-modal').modal('show');

    if (window.paidOrderDetailsCache[cacheKey]) {
        renderSharedReceiptModal(window.paidOrderDetailsCache[cacheKey]);
        return;
    }

    $('#shared-receipt-body').html(
        '<div style="text-align: center; padding: 45px 20px;">' +
        '<i class="fa fa-spinner fa-spin fa-3x" style="color: #059669;"></i>' +
        '<p style="margin-top: 14px; color: #64748b; font-weight: 600; font-size: 14px;">Loading Official Receipt Details...</p>' +
        '</div>'
    );

    $.ajax({
        url: 'get-paid-order-details-ajax.php',
        type: 'GET',
        data: { id: orderId, payment_id: paymentId },
        dataType: 'json',
        success: function(res) {
            if (res && res.success && res.order) {
                window.paidOrderDetailsCache[cacheKey] = res.order;
                renderSharedReceiptModal(res.order);
            } else {
                $('#shared-receipt-body').html(
                    '<div class="alert alert-danger" style="margin: 15px;">' +
                    '<i class="fa fa-exclamation-triangle"></i> ' +
                    (res && res.message ? escapeHtml(res.message) : 'Failed to retrieve receipt details.') +
                    '</div>'
                );
            }
        },
        error: function() {
            $('#shared-receipt-body').html(
                '<div class="alert alert-danger" style="margin: 15px;">' +
                '<i class="fa fa-exclamation-triangle"></i> Network error loading receipt details. Please try again.' +
                '</div>'
            );
        }
    });
}

function renderSharedReceiptModal(order) {
    window.currentLoadedOrderData = order;
    $('#shared-receipt-title-txnid').text(order.txnid || order.payment_id);

    var items = order.items || [];
    var itemsRows = '';
    var rThermalItems = '';
    var idx = 0;

    items.forEach(function(item) {
        idx++;
        var qty = parseInt(item.qty, 10) || 1;
        var unitPrice = parseFloat(item.price || 0);
        var itemAmount = parseFloat(item.amount || (unitPrice * qty));
        var isSpecial = item.is_special_order;
        var nameHtml = isSpecial ? '<span class="label label-warning" style="background:#d97706; font-size:9.5px; font-weight:bold; padding:2px 5px; border-radius:3px; margin-right:4px;">SPECIAL ORDER</span>' + escapeHtml(item.name) : escapeHtml(item.name);

        itemsRows += '<tr>' +
            '<td style="text-align: center; vertical-align: middle; color: #64748b; font-weight: 600;">' + idx + '</td>' +
            '<td style="vertical-align: middle;">' +
                '<div style="font-weight: 700; color: #0f172a;">' + nameHtml + '</div>' +
                ((item.size && item.size !== '-') || (item.color && item.color !== '-') ? '<div style="font-size: 11px; color: #64748b;">Size: ' + escapeHtml(item.size) + ' | Color: ' + escapeHtml(item.color) + '</div>' : '') +
            '</td>' +
            '<td style="text-align: center; vertical-align: middle; font-weight: 700;">' + qty + '</td>' +
            '<td style="text-align: right; vertical-align: middle;">PHP ' + formatMoney(unitPrice) + '</td>' +
            '<td style="text-align: right; vertical-align: middle; font-weight: 700; color: #059669;">PHP ' + formatMoney(itemAmount) + '</td>' +
        '</tr>';

        rThermalItems += '<tr>' +
            '<td colspan="2" style="text-align: left; padding-top: 3px; font-weight: bold; word-break: break-word;">' + escapeHtml(item.name) + '</td>' +
        '</tr>' +
        '<tr>' +
            '<td style="text-align: left; padding-left: 8px; padding-bottom: 3px;">' + qty + ' ' + (qty > 1 ? 'pcs' : 'pc') + ' @ ' + formatMoney(unitPrice) + '</td>' +
            '<td style="text-align: right; padding-bottom: 3px; white-space: nowrap; vertical-align: bottom;">' + formatMoney(itemAmount) + '</td>' +
        '</tr>';
    });

    var isCredit = (order.payment_status === 'On Credit' || (order.credit_balance !== undefined && parseFloat(order.credit_balance) > 0));
    var totalVal = parseFloat(order.total || 0);
    var amountPaidVal = parseFloat(order.amount_paid !== undefined ? order.amount_paid : totalVal);
    var creditBalVal = parseFloat(order.credit_balance || 0);

    var html = '' +
        '<div style="background: #ecfdf5; border: 1.5px solid #a7f3d0; border-radius: 8px; padding: 12px 18px; margin-bottom: 18px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">' +
            '<div style="display: flex; align-items: center; gap: 10px;">' +
                '<span class="label" style="background: #059669; color: #ffffff; font-size: 12px; font-weight: 800; padding: 5px 10px; border-radius: 4px; text-transform: uppercase;">' +
                    '<i class="fa fa-check"></i> Paid &amp; Completed' +
                '</span>' +
                '<span style="font-size: 12.5px; color: #065f46; font-weight: 600;">P.O. transaction recorded</span>' +
            '</div>' +
            '<div style="font-size: 12px; color: #64748b; font-weight: 600;">' +
                '<i class="fa fa-calendar"></i> ' + escapeHtml(order.formatted_date) +
            '</div>' +
        '</div>' +

        '<div class="row" style="margin-bottom: 18px;">' +
            '<div class="col-sm-6 col-xs-12" style="margin-bottom: 10px;">' +
                '<div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px 16px; height: 100%;">' +
                    '<div style="font-size: 11px; font-weight: 800; text-transform: uppercase; color: #64748b; margin-bottom: 8px; letter-spacing: 0.5px;">' +
                        '<i class="fa fa-user"></i> Customer Information' +
                    '</div>' +
                    '<div style="font-size: 14px; font-weight: 700; color: #0f172a; margin-bottom: 3px;">' +
                        escapeHtml(order.customer_name) +
                    '</div>' +
                    (order.customer_phone ? '<div style="font-size: 12px; color: #475569; margin-bottom: 2px;"><i class="fa fa-phone" style="width: 14px; color: #059669;"></i> ' + escapeHtml(order.customer_phone) + '</div>' : '') +
                    (order.customer_email ? '<div style="font-size: 12px; color: #475569; margin-bottom: 2px;"><i class="fa fa-envelope" style="width: 14px; color: #059669;"></i> ' + escapeHtml(order.customer_email) + '</div>' : '') +
                    (order.customer_address ? '<div style="font-size: 11.5px; color: #64748b;"><i class="fa fa-map-marker" style="width: 14px; color: #059669;"></i> ' + escapeHtml(order.customer_address) + '</div>' : '') +
                '</div>' +
            '</div>' +
            '<div class="col-sm-6 col-xs-12" style="margin-bottom: 10px;">' +
                '<div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px 16px; height: 100%;">' +
                    '<div style="font-size: 11px; font-weight: 800; text-transform: uppercase; color: #64748b; margin-bottom: 8px; letter-spacing: 0.5px;">' +
                        '<i class="fa fa-building-o"></i> Store &amp; Payment Details' +
                    '</div>' +
                    '<div style="margin-bottom: 6px; font-size: 13px;">' +
                        '<strong>P.O. Receipt No:</strong>' +
                        '<div style="padding-left: 8px; margin-top: 2px;">' +
                            '<span style="font-family: monospace; font-weight: 700; font-size: 13.5px; color: #059669;">' + escapeHtml(order.txnid || order.payment_id) + '</span>' +
                        '</div>' +
                    '</div>' +
                    '<div style="font-size: 13.5px; font-weight: 700; color: #0f172a; margin-bottom: 3px;">' +
                        escapeHtml(order.supplier_name) +
                    '</div>' +
                    '<div style="font-size: 12px; color: #475569; margin-bottom: 4px;">' +
                        '<span class="label" style="background: #059669; font-size: 10.5px; padding: 3px 7px; border-radius: 3px;"><i class="fa fa-credit-card"></i> ' + escapeHtml(order.payment_method) + '</span>' +
                        '<span style="margin-left: 6px; font-weight: 600;">Status: Paid</span>' +
                    '</div>' +
                    '<div style="font-size: 12px; color: #475569; margin-bottom: 4px;">' +
                        '<i class="fa fa-user" style="width: 14px; color: #059669;"></i> <strong>Cashier / Staff:</strong> ' + escapeHtml(order.cashier_name) +
                    '</div>' +
                    (order.supplier_phone ? '<div style="font-size: 11.5px; color: #64748b;"><i class="fa fa-phone" style="width: 14px;"></i> Tel: ' + escapeHtml(order.supplier_phone) + '</div>' : '') +
                '</div>' +
            '</div>' +
        '</div>' +

        '<div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; margin-bottom: 18px;">' +
            '<div style="padding: 10px 16px; background: #f1f5f9; border-bottom: 1px solid #e2e8f0; font-size: 12px; font-weight: 800; text-transform: uppercase; color: #475569; letter-spacing: 0.5px;">' +
                '<i class="fa fa-shopping-cart"></i> Purchased Items (' + items.length + ')' +
            '</div>' +
            '<div class="table-responsive" style="margin-bottom: 0;">' +
                '<table class="table table-hover" style="margin-bottom: 0; font-size: 13px;">' +
                    '<thead style="background: #f8fafc;">' +
                        '<tr>' +
                            '<th style="width: 40px; text-align: center; color: #64748b; font-weight: 700; border-bottom: 1px solid #e2e8f0;">#</th>' +
                            '<th style="color: #475569; font-weight: 700; border-bottom: 1px solid #e2e8f0;">Item Description</th>' +
                            '<th style="width: 80px; text-align: center; color: #475569; font-weight: 700; border-bottom: 1px solid #e2e8f0;">Qty</th>' +
                            '<th style="width: 110px; text-align: right; color: #475569; font-weight: 700; border-bottom: 1px solid #e2e8f0;">Unit Price</th>' +
                            '<th style="width: 120px; text-align: right; color: #475569; font-weight: 700; border-bottom: 1px solid #e2e8f0;">Subtotal</th>' +
                        '</tr>' +
                    '</thead>' +
                    '<tbody>' + itemsRows + '</tbody>' +
                '</table>' +
            '</div>' +
        '</div>' +

        '<div style="background: #ffffff; border: 1px solid #cbd5e1; border-radius: 8px; padding: 20px; margin-top: 15px; box-shadow: inset 0 2px 4px rgba(0,0,0,0.02);">' +
            '<div style="font-weight: 800; color: #475569; font-size: 12.5px; text-transform: uppercase; margin-bottom: 12px; border-bottom: 1px solid #e2e8f0; padding-bottom: 6px;">' +
                '<i class="fa fa-file-text-o"></i> Printable Sales Invoice (Receipt Preview)' +
            '</div>' +
            '<div class="receipt-box-printable" id="shared-receipt-printable-preview" style="max-width: 380px; margin: 0 auto; background: #fff; border: 1px solid #cbd5e1; padding: 15px 18px; font-family: monospace; font-size: 9.5pt; color: #000; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);">' +
                '<div style="text-align: center; font-weight: bold; overflow: hidden; white-space: nowrap;">================================</div>' +
                '<div style="text-align: center; font-weight: bold; font-size: 1.15em;">' + escapeHtml(order.supplier_name) + '</div>' +
                '<div style="text-align: center;">Tel: ' + escapeHtml(order.supplier_phone) + '</div>' +
                '<div style="text-align: center;">P.O. RECEIPT</div>' +
                '<div style="text-align: center; font-weight: bold; font-size: 1em;">' + (isCredit ? '(ON CREDIT)' : '(PAID)') + '</div>' +
                '<div style="text-align: center; font-weight: bold; overflow: hidden; white-space: nowrap;">================================</div>' +
                '<table style="width: 100%; border-collapse: collapse; font-family: inherit; font-size: 10.5pt; margin-bottom: 2px;">' +
                    '<tr><td style="width: 32%;">RECEIPT NO:</td><td style="font-weight: bold;">' + escapeHtml(order.txnid || order.payment_id) + '</td></tr>' +
                    '<tr><td>CUSTOMER :</td><td>' + escapeHtml(order.customer_name) + '</td></tr>' +
                    '<tr><td>PAYMENT :</td><td>' + escapeHtml(order.payment_method) + '</td></tr>' +
                    '<tr><td>STATUS :</td><td style="font-weight: bold;">' + (isCredit ? 'ON CREDIT' : 'PAID') + '</td></tr>' +
                    '<tr><td>DATE :</td><td>' + escapeHtml(order.payment_date) + '</td></tr>' +
                '</table>' +
                '<div style="text-align: center; overflow: hidden; white-space: nowrap;">--------------------------------</div>' +
                '<table style="width: 100%; border-collapse: collapse; font-family: inherit; font-size: 10.5pt;">' +
                    '<thead><tr><th style="text-align: left;">ITEM</th><th style="text-align: right;">AMOUNT</th></tr></thead>' +
                    '<tbody>' + rThermalItems + '</tbody>' +
                '</table>' +
                '<div style="text-align: center; overflow: hidden; white-space: nowrap;">--------------------------------</div>' +
                '<table style="width: 100%; border-collapse: collapse; font-family: inherit; font-size: 10.5pt; margin: 2px 0;">' +
                    '<tr><td>Subtotal:</td><td style="text-align: right;">' + formatMoney(order.subtotal) + '</td></tr>' +
                    (parseFloat(order.delivery) > 0 ? '<tr><td>Delivery:</td><td style="text-align: right;">' + formatMoney(order.delivery) + '</td></tr>' : '') +
                    '<tr style="font-weight: bold;"><td style="font-size: 1.08em;">TOTAL INVOICE:</td><td style="text-align: right; font-size: 1.08em;">PHP ' + formatMoney(totalVal) + '</td></tr>' +
                    (isCredit ?
                        '<tr><td style="font-weight: bold;">UPFRONT PAID:</td><td style="text-align: right; font-weight: bold;">PHP ' + formatMoney(amountPaidVal) + '</td></tr>' +
                        '<tr style="font-weight: 900; border-top: 1.5px dashed #000; border-bottom: 1.5px dashed #000;"><td>BALANCE ON CREDIT:</td><td style="text-align: right;">PHP ' + formatMoney(creditBalVal) + '</td></tr>'
                        :
                        '<tr style="font-weight: bold;"><td style="font-size: 1.08em;">TOTAL PAID:</td><td style="text-align: right; font-size: 1.08em;">PHP ' + formatMoney(totalVal) + '</td></tr>' +
                        '<tr><td>Tendered:</td><td style="text-align: right;">' + formatMoney(order.tendered !== null ? order.tendered : totalVal) + '</td></tr>' +
                        '<tr><td>Change:</td><td style="text-align: right;">' + formatMoney(order.change !== null ? order.change : 0) + '</td></tr>'
                    ) +
                '</table>' +
                '<div style="text-align: center; font-weight: bold; overflow: hidden; white-space: nowrap;">================================</div>' +
                '<div style="text-align: center; line-height: 1.35; padding: 2px 0;">' +
                    '<div style="font-weight: bold;">*** SALES INVOICE ***</div>' +
                    '<div style="margin-top: 3px;">THANK YOU FOR YOUR PURCHASE!</div>' +
                    '<div style="font-size: 9pt; margin-top: 6px;">eConstruction Supply POS</div>' +
                '</div>' +
                '<div style="text-align: center; font-weight: bold; overflow: hidden; white-space: nowrap;">================================</div>' +
            '</div>' +
        '</div>';

    $('#shared-receipt-body').html(html);
}

// -------------------------------------------------------------
// 4. Print Functions
// -------------------------------------------------------------
function printCurrentPOVoucher() {
    if (!window.currentLoadedOrderData) return;
    printPaidOrderThermal(window.currentLoadedOrderData, 80);
}

function printCurrentReceipt() {
    if (!window.currentLoadedOrderData) return;
    printPaidOrderThermal(window.currentLoadedOrderData, 80);
}

// Global POS Print Window Generator
function generatePaidOrderThermalHTML(orderData, requestedWidthMm, requestedContentWidthMm) {
    const s = getPOSPrintSettings();
    let paperWidthMm = requestedWidthMm || s.paperWidthMm || 80;
    if (paperWidthMm > 210) paperWidthMm = 210;
    if (paperWidthMm < 40) paperWidthMm = 40;

    let contentWidthMm = requestedContentWidthMm || (paperWidthMm <= 52 ? 44 : (paperWidthMm <= 65 ? 48 : (paperWidthMm <= 90 ? 72 : 120)));
    contentWidthMm = Math.min(paperWidthMm, Math.max(30, Math.round(parseFloat(contentWidthMm))));

    const is58mm = (paperWidthMm <= 65);
    const fontName = s.thermalFontName || 'Courier New';
    const fontStack = `'${fontName.replace(/'/g, "\\'")}', monospace, 'Courier New', sans-serif`;
    const fontSizePt = is58mm ? 10 : 12;
    const titleFontSizePt = is58mm ? 12 : 14;

    const supplierName = orderData.supplier_name || 'SAM & INRI CONSTRUCTION SUPPLY';
    const supplierPhone = orderData.supplier_phone || '09612735733';
    const orderNo = orderData.txnid || orderData.payment_id || 'POS-ORDER';
    const dateStr = orderData.payment_date || '';
    const customerName = orderData.customer_name || 'Walk-in Customer';
    const paymentMethod = orderData.payment_method || 'Cash';
    const isCredit = (orderData.payment_status === 'On Credit' || (orderData.credit_balance !== undefined && parseFloat(orderData.credit_balance) > 0));

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
    const total = parseFloat(orderData.total || 0).toFixed(2);
    const amountPaid = parseFloat(orderData.amount_paid !== undefined ? orderData.amount_paid : total).toFixed(2);
    const creditBalance = parseFloat(orderData.credit_balance || 0).toFixed(2);
    const tendered = orderData.tendered !== null && orderData.tendered !== undefined ? parseFloat(orderData.tendered).toFixed(2) : null;
    const change = orderData.change !== null && orderData.change !== undefined ? parseFloat(orderData.change).toFixed(2) : null;

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
        * { box-sizing: border-box; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
        @page { size: ${paperWidthMm}mm auto; margin: 0 !important; }
        html, body { margin: 0 !important; padding: 0 !important; background: #ffffff !important; color: #000000 !important; font-family: ${fontStack} !important; font-size: ${fontSizePt}pt !important; line-height: 1.25 !important; width: ${paperWidthMm}mm !important; }
        .thermal-receipt { width: ${contentWidthMm}mm !important; max-width: ${contentWidthMm}mm !important; margin: 0 auto !important; padding: 0 !important; }
        .thermal-divider-double { text-align: center; font-weight: bold; margin: 3px 0; overflow: hidden; white-space: nowrap; }
        .thermal-divider-single { text-align: center; margin: 2px 0; overflow: hidden; white-space: nowrap; }
        .thermal-header { text-align: center; }
        .thermal-title { font-size: ${titleFontSizePt}pt; font-weight: bold; text-transform: uppercase; line-height: 1.2; }
        .thermal-phone { font-size: ${fontSizePt}pt; margin-top: 1px; }
        .thermal-doc-title { font-size: ${fontSizePt}pt; margin-top: 2px; }
        .thermal-meta { margin: 2px 0; font-size: ${fontSizePt}pt; width: 100%; border-collapse: collapse; }
        .thermal-meta td { padding: 1px 0; vertical-align: top; }
        .thermal-meta .meta-label { font-weight: bold; white-space: nowrap; width: 32%; }
        .thermal-table { width: 100% !important; border-collapse: collapse !important; font-size: ${fontSizePt}pt !important; }
        .thermal-table th { padding: 1px 0 !important; font-weight: bold !important; text-transform: uppercase !important; }
        .thermal-totals { width: 100% !important; border-collapse: collapse !important; margin: 2px 0 !important; font-size: ${fontSizePt}pt !important; }
        .thermal-totals td { padding: 1px 0; }
        .thermal-footer { text-align: center; font-weight: bold; padding: 4px 0; text-transform: uppercase; font-size: ${fontSizePt}pt; }
        @media screen {
            body { padding: 15px; background: #334155; display: flex; flex-direction: column; align-items: center; min-height: 100vh; }
            .thermal-preview-toolbar { width: 100%; max-width: ${paperWidthMm}mm; margin-bottom: 12px; background: #0f172a; color: #ffffff; padding: 8px 12px; border-radius: 4px; display: flex; justify-content: space-between; align-items: center; font-family: Arial, sans-serif; font-size: 11px; }
            .thermal-receipt { background: #fff; box-shadow: 0 4px 20px rgba(0,0,0,0.35); border: 1px solid #cbd5e1; border-radius: 2px; padding: 2mm 3mm !important; }
        }
        @media print {
            .thermal-preview-toolbar { display: none !important; }
            html, body { padding: 0 !important; margin: 0 !important; background: #fff !important; }
            .thermal-receipt { box-shadow: none !important; border: none !important; }
        }
    </style>
</head>
<body>
    <div class="thermal-preview-toolbar">
        <span style="font-weight: bold;">🖨️ Purchase Order Receipt (${paperWidthMm}mm)</span>
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
            <tr><td class="meta-label">RECEIPT NO:</td><td>${escapeHtml(orderNo)}</td></tr>
            <tr><td class="meta-label">CUSTOMER  :</td><td>${escapeHtml(customerName)}</td></tr>
            <tr><td class="meta-label">PAYMENT   :</td><td>${escapeHtml(paymentMethod)} ${isCredit ? '(ON CREDIT)' : '(PAID)'}</td></tr>
            <tr><td class="meta-label">STATUS    :</td><td style="font-weight: bold;">${isCredit ? 'ON CREDIT' : 'PAID'}</td></tr>
            <tr><td class="meta-label">DATE      :</td><td>${escapeHtml(dateStr)}</td></tr>
        </table>

        <div class="thermal-divider-single">--------------------------------</div>
        <table class="thermal-table">
            <thead>
                <tr>
                    <th style="text-align: left; width: 68%;">ITEM DESCRIPTION</th>
                    <th style="text-align: right; width: 32%;">AMOUNT</th>
                </tr>
            </thead>
            <tbody>
                ${itemsRows}
            </tbody>
        </table>
        <div class="thermal-divider-single">--------------------------------</div>

        <table class="thermal-totals">
            <tr><td style="text-align: left;">Subtotal:</td><td style="text-align: right;">${subtotal}</td></tr>
            ${parseFloat(delivery) > 0 ? `<tr><td style="text-align: left;">Delivery:</td><td style="text-align: right;">${delivery}</td></tr>` : ''}
            <tr style="font-weight: bold;">
                <td style="text-align: left; font-size: ${titleFontSizePt}pt;">TOTAL INVOICE:</td>
                <td style="text-align: right; font-size: ${titleFontSizePt}pt; white-space: nowrap;">PHP ${total}</td>
            </tr>
            ${isCredit ? `
            <tr><td style="text-align: left; font-weight: bold;">UPFRONT PAID:</td><td style="text-align: right; font-weight: bold;">PHP ${amountPaid}</td></tr>
            <tr style="font-weight: 900; border-top: 1.5px dashed #000; border-bottom: 1.5px dashed #000;"><td style="text-align: left;">BALANCE ON CREDIT:</td><td style="text-align: right;">PHP ${creditBalance}</td></tr>
            ` : `
            <tr style="font-weight: bold;"><td style="text-align: left; font-size: ${titleFontSizePt}pt;">TOTAL PAID:</td><td style="text-align: right; font-size: ${titleFontSizePt}pt;">PHP ${total}</td></tr>
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

// -------------------------------------------------------------
// 5. Delete Button & DataTables Initialization
// -------------------------------------------------------------
$(document).ready(function() {
    // A. Resilient Delete Confirmation Delegated Handler
    $(document).on('click', '.btn-delete-order, [data-target="#confirm-delete"]', function(e) {
        e.preventDefault();
        var href = $(this).attr('data-href');
        if (href) {
            $('#confirm-delete').find('.btn-ok').attr('href', href);
        }
        $('#confirm-delete').modal('show');
    });

    $('#confirm-delete').on('show.bs.modal', function(e) {
        var related = $(e.relatedTarget);
        if (related && related.length && related.attr('data-href')) {
            $(this).find('.btn-ok').attr('href', related.attr('data-href'));
        }
    });

    // B. Fast, Clean DataTables Initialization on paidOrdersTable
    if ($.fn.DataTable && $.fn.DataTable.isDataTable('#paidOrdersTable')) {
        $('#paidOrdersTable').DataTable().destroy();
    }
    
    if ($.fn.DataTable) {
        $('#paidOrdersTable').DataTable({
            "order": [[0, "desc"]],
            "pageLength": 25,
            "responsive": true,
            "autoWidth": false,
            "language": {
                "search": "_INPUT_",
                "searchPlaceholder": "Search orders..."
            }
        });
    }
});
</script>

<?php require_once('footer.php'); ?>
