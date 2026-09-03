<?php require_once('header.php'); ?>

<?php
// Check if customer is logged in
if(!isset($_SESSION['customer'])) {
    header('location: '.BASE_URL.'login.php');
    exit;
}

$order_ids_input = isset($_GET['order_id']) ? trim($_GET['order_id']) : '';

if(empty($order_ids_input) && isset($_SESSION['last_po_ids']) && is_array($_SESSION['last_po_ids'])) {
    $order_ids_input = implode(',', $_SESSION['last_po_ids']);
}

if(empty($order_ids_input)) {
    header('location: '.BASE_URL.'customer-order.php');
    exit;
}

$arr_order_ids = explode(',', $order_ids_input);
$clean_order_ids = [];
foreach($arr_order_ids as $oid) {
    $oid = trim($oid);
    if(!empty($oid)) {
        $clean_order_ids[] = $oid;
    }
}

if(empty($clean_order_ids)) {
    header('location: '.BASE_URL.'customer-order.php');
    exit;
}
?>

<style>
.receipt-container {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
    padding: 30px;
    margin-bottom: 35px;
}
.receipt-header {
    border-bottom: 2px solid #0284c7;
    padding-bottom: 20px;
    margin-bottom: 25px;
}
.receipt-title {
    font-size: 26px;
    font-weight: 700;
    color: #0f172a;
    margin: 0 0 5px 0;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.txn-badge {
    background: #e0f2fe;
    color: #0369a1;
    font-size: 16px;
    font-weight: 700;
    padding: 6px 14px;
    border-radius: 6px;
    display: inline-block;
    border: 1px solid #bae6fd;
}
.status-badge {
    background: #fef3c7;
    color: #92400e;
    font-size: 13px;
    font-weight: 600;
    padding: 4px 10px;
    border-radius: 4px;
    display: inline-block;
    text-transform: uppercase;
}
.info-card {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    padding: 15px;
    margin-bottom: 20px;
    min-height: 160px;
}
.info-card h4 {
    font-size: 15px;
    font-weight: 700;
    color: #1e293b;
    margin: 0 0 10px 0;
    border-bottom: 1px solid #cbd5e1;
    padding-bottom: 6px;
}
.info-card p {
    margin-bottom: 4px;
    font-size: 13px;
    color: #475569;
    line-height: 1.5;
}
.receipt-table th {
    background: #f1f5f9;
    color: #334155;
    font-weight: 600;
    font-size: 13px;
    border: 1px solid #cbd5e1 !important;
}
.receipt-table td {
    border: 1px solid #e2e8f0 !important;
    font-size: 13px;
    vertical-align: middle !important;
}
.receipt-summary {
    float: right;
    width: 320px;
    margin-top: 10px;
}
.receipt-summary table {
    width: 100%;
}
.receipt-summary td {
    padding: 6px 10px;
    font-size: 14px;
}
.receipt-summary .total-row {
    font-size: 18px;
    font-weight: 700;
    color: #0f172a;
    border-top: 2px solid #0284c7;
    border-bottom: 2px solid #0284c7;
    background: #f8fafc;
}
.alert-success-custom {
    background-color: #ecfdf5;
    border: 1px solid #a7f3d0;
    color: #065f46;
    border-radius: 6px;
    padding: 15px 20px;
    margin-bottom: 25px;
}
@media print {
    body {
        background: #fff !important;
    }
    .header, .top-header, .page-banner, .footer, .no-print, .main-header, .navbar {
        display: none !important;
    }
    .receipt-container {
        border: none !important;
        box-shadow: none !important;
        padding: 0 !important;
        margin: 0 !important;
    }
}
</style>

<div class="page-banner" style="background-image: url(assets/uploads/banner_order.jpg)">
    <div class="overlay"></div>
    <div class="page-banner-inner">
        <h1>Purchase Order Receipt</h1>
    </div>
</div>

<div class="page">
    <div class="container">
        <div class="row">
            <div class="col-md-12">
                
                <div class="alert-success-custom no-print">
                    <h4 style="margin: 0 0 5px 0; font-weight: bold; color: #047857;">
                        <i class="fa fa-check-circle"></i> Purchase Order Created Successfully!
                    </h4>
                    <p style="margin: 0; font-size: 14px;">
                        Thank you for your order. Please present your <strong>Transaction ID</strong> when settling payment and collecting your items at the supplier store.
                    </p>
                </div>

                <div class="text-right no-print" style="margin-bottom: 20px; display: flex; justify-content: flex-end; gap: 10px;">
                    <button onclick="window.print();" class="btn btn-primary" style="background-color: #0284c7; border-color: #0284c7;">
                        <i class="fa fa-print"></i> Print Receipt
                    </button>
                    <a href="customer-order.php" class="btn btn-default">
                        <i class="fa fa-list"></i> View All Orders
                    </a>
                    <a href="index.php" class="btn btn-success">
                        <i class="fa fa-shopping-cart"></i> Continue Shopping
                    </a>
                </div>

                <?php
                foreach($clean_order_ids as $current_pid):
                    $stmt_pay = $pdo->prepare("SELECT * FROM tbl_payment WHERE (payment_id=? OR txnid=?) AND customer_email=?");
                    $stmt_pay->execute(array($current_pid, $current_pid, $_SESSION['customer']['cust_email']));
                    $payment = $stmt_pay->fetch(PDO::FETCH_ASSOC);

                    if(!$payment) {
                        continue;
                    }

                    // Fetch Supplier Details
                    $supplier = null;
                    if(!empty($payment['supplier_id'])) {
                        $stmt_s = $pdo->prepare("SELECT * FROM tbl_supplier WHERE supplier_id=?");
                        $stmt_s->execute(array($payment['supplier_id']));
                        $supplier = $stmt_s->fetch(PDO::FETCH_ASSOC);
                    }

                    // Fetch Order Items
                    $stmt_items = $pdo->prepare("SELECT * FROM tbl_order WHERE payment_id=?");
                    $stmt_items->execute(array($payment['payment_id']));
                    $items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
                ?>

                <div class="receipt-container">
                    
                    <div class="receipt-header">
                        <div class="row">
                            <div class="col-xs-7">
                                <h2 class="receipt-title">eConstruction Supply</h2>
                                <p style="color: #64748b; margin: 0; font-size: 13px;">Official Purchase Order Voucher</p>
                            </div>
                            <div class="col-xs-5 text-right">
                                <div style="margin-bottom: 5px;">
                                    <span class="txn-badge">Transaction ID: <?php echo htmlspecialchars($payment['txnid'] ?: $payment['payment_id']); ?></span>
                                </div>
                                <div>
                                    <span class="status-badge"><?php echo htmlspecialchars($payment['payment_status']); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 col-sm-4">
                            <div class="info-card">
                                <h4><i class="fa fa-user"></i> Customer Information</h4>
                                <p><strong>Name:</strong> <?php echo htmlspecialchars($payment['customer_name']); ?></p>
                                <p><strong>Email:</strong> <?php echo htmlspecialchars($payment['customer_email']); ?></p>
                                <p><strong>Phone:</strong> <?php echo htmlspecialchars($_SESSION['customer']['cust_phone'] ?? $_SESSION['customer']['cust_b_phone'] ?? 'N/A'); ?></p>
                                <p><strong>Barangay / Town:</strong> <?php echo htmlspecialchars($_SESSION['customer']['cust_s_state'] ?? $_SESSION['customer']['cust_state'] ?? ''); ?></p>
                            </div>
                        </div>

                        <div class="col-md-4 col-sm-4">
                            <div class="info-card">
                                <h4><i class="fa fa-building"></i> Store / Supplier Location</h4>
                                <?php if($supplier): ?>
                                    <p><strong>Store:</strong> <?php echo htmlspecialchars($supplier['supplier_name']); ?></p>
                                    <p><strong>Address:</strong> <?php echo nl2br(htmlspecialchars($supplier['supplier_address'])); ?></p>
                                    <p><strong>Phone:</strong> <?php echo htmlspecialchars($supplier['supplier_phone']); ?></p>
                                    <p><strong>Email:</strong> <?php echo htmlspecialchars($supplier['supplier_email']); ?></p>
                                <?php else: ?>
                                    <p><strong>Store:</strong> Main Warehouse / Store</p>
                                    <p><strong>Address:</strong> Santa Barbara, Iloilo</p>
                                    <?php endif; ?>
                            </div>
                        </div>

                        <div class="col-md-4 col-sm-4">
                            <div class="info-card">
                                <h4><i class="fa fa-info-circle"></i> Order Summary Info</h4>
                                <p><strong>Date & Time:</strong> <?php echo date('M d, Y h:i A', strtotime($payment['payment_date'])); ?></p>
                                <p><strong>Payment Method:</strong> <?php echo htmlspecialchars($payment['payment_method']); ?></p>
                                <p><strong>Order Type:</strong> <?php echo htmlspecialchars($payment['bank_transaction_info'] ?? 'Purchase Order'); ?></p>
                                <p><strong>Payment Ref:</strong> <?php echo htmlspecialchars($payment['payment_id']); ?></p>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive" style="margin-top: 15px;">
                        <table class="table receipt-table">
                            <thead>
                                <tr>
                                    <th style="width: 50px;">#</th>
                                    <th>Item Description</th>
                                    <th>Size</th>
                                    <th>Color</th>
                                    <th class="text-center" style="width: 100px;">Quantity</th>
                                    <th class="text-right" style="width: 140px;">Unit Price</th>
                                    <th class="text-right" style="width: 140px;">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $subtotal_calc = 0;
                                $item_count = 0;
                                foreach($items as $item):
                                    $item_count++;
                                    $line_total = $item['unit_price'] * $item['quantity'];
                                    $subtotal_calc += $line_total;
                                ?>
                                <tr>
                                    <td><?php echo $item_count; ?></td>
                                    <td><strong><?php echo htmlspecialchars($item['product_name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($item['size'] ?: 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($item['color'] ?: 'N/A'); ?></td>
                                    <td class="text-center"><?php echo $item['quantity']; ?></td>
                                    <td class="text-right">&#8369;<?php echo number_format($item['unit_price'], 2); ?></td>
                                    <td class="text-right">&#8369;<?php echo number_format($line_total, 2); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="row">
                        <div class="col-md-6 col-sm-6">
                            <div style="background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 6px; padding: 12px; margin-top: 10px;">
                                <p style="margin: 0; font-size: 12px; color: #475569;">
                                    <strong><i class="fa fa-sticky-note-o"></i> Instructions:</strong><br>
                                    1. Present this Purchase Order receipt or Transaction ID at the store counter.<br>
                                    2. Settle payment in cash or via over-the-counter channels.<br>
                                    3. Inspect items upon collection / delivery.
                                </p>
                            </div>
                        </div>
                        <div class="col-md-6 col-sm-6">
                            <div class="receipt-summary">
                                <table class="table">
                                    <tr>
                                        <td>Items Subtotal:</td>
                                        <td class="text-right">&#8369;<?php echo number_format($subtotal_calc, 2); ?></td>
                                    </tr>
                                    <?php
                                    $delivery_fee_calc = $payment['paid_amount'] - $subtotal_calc;
                                    if($delivery_fee_calc > 0):
                                    ?>
                                    <tr>
                                        <td>Delivery Fee:</td>
                                        <td class="text-right">&#8369;<?php echo number_format($delivery_fee_calc, 2); ?></td>
                                    </tr>
                                    <?php else: ?>
                                    <tr>
                                        <td>Delivery Fee:</td>
                                        <td class="text-right" style="color: #16a34a; font-weight: 600;">&#8369;0.00 (Store Pick-up)</td>
                                    </tr>
                                    <?php endif; ?>
                                    <tr class="total-row">
                                        <td>Total Payable:</td>
                                        <td class="text-right">&#8369;<?php echo number_format($payment['paid_amount'], 2); ?></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>

                </div>

                <?php endforeach; ?>

            </div>
        </div>
    </div>
</div>

<?php require_once('footer.php'); ?>
