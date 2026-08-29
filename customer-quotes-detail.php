<?php require_once('header.php'); ?>

<?php
// Enforce login
if(!isset($_SESSION['customer'])) {
    header('location: login.php');
    exit;
}
$cust_id = $_SESSION['customer']['cust_id'];

if(!isset($_GET['id'])) {
    header('location: customer-quotes.php');
    exit;
}

// Fetch RFQ details and verify customer ownership
$statement = $pdo->prepare("SELECT q.*, s.supplier_name, s.supplier_email, s.supplier_phone, qi.quantity, qi.unit_price, p.p_name, p.p_id 
                            FROM tbl_quote q
                            JOIN tbl_supplier s ON q.supplier_id = s.supplier_id
                            LEFT JOIN tbl_quote_item qi ON q.quote_id = qi.quote_id
                            LEFT JOIN tbl_product p ON qi.product_id = p.p_id
                            WHERE q.quote_id = ? AND q.cust_id = ?");
$statement->execute(array($_GET['id'], $cust_id));
$quote = $statement->fetch(PDO::FETCH_ASSOC);

if (!$quote) {
    header('location: customer-quotes.php');
    exit;
}

// Handle message sending
if (isset($_POST['form_send_msg'])) {
    $reply_msg = trim($_POST['reply_msg']);
    if (!empty($reply_msg)) {
        $statement = $pdo->prepare("INSERT INTO tbl_message (sender_type, sender_id, recipient_type, recipient_id, message_content) VALUES (?, ?, ?, ?, ?)");
        $statement->execute(array('Customer', $cust_id, 'Supplier', $quote['supplier_id'], $reply_msg));
        $success_message = "Message sent successfully!";
    }
}
?>

<div class="page">
    <div class="container">
        <div class="row">            
            <div class="col-md-12"> 
                <?php require_once('customer-sidebar.php'); ?>
            </div>
            
            <div class="col-md-12" style="margin-top: 20px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h3 style="font-weight: bold; color: #1F2937; margin: 0;"><i class="fa fa-comments"></i> Proposal Negotiation & Messages</h3>
                    <a href="customer-quotes.php" class="btn btn-default"><i class="fa fa-arrow-left"></i> Back to list</a>
                </div>
                <hr>

                <div class="row">
                    <!-- Proposal Details Column -->
                    <div class="col-md-5">
                        <div style="background: #f8f9fa; border: 1px solid #ddd; padding: 20px; border-radius: 5px; margin-bottom: 20px;">
                            <h4 style="font-weight: bold; color: #1F2937; margin-top: 0;">Supplier Proposal Specs</h4>
                            <hr style="margin-top: 5px;">
                            <table class="table table-bordered">
                                <tr>
                                    <td><strong>Supplier Company</strong></td>
                                    <td><strong><?php echo htmlspecialchars($quote['supplier_name']); ?></strong></td>
                                </tr>
                                <tr>
                                    <td><strong>Contact Info</strong></td>
                                    <td><?php echo htmlspecialchars($quote['supplier_email']); ?> | <?php echo htmlspecialchars($quote['supplier_phone']); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Material Requested</strong></td>
                                    <td><?php echo htmlspecialchars($quote['p_name'] ?: 'General Inquiry'); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Qty Requested</strong></td>
                                    <td><?php echo htmlspecialchars($quote['quantity']); ?> units</td>
                                </tr>
                                <tr>
                                    <td><strong>Your Specs Notes</strong></td>
                                    <td><?php echo nl2br(htmlspecialchars($quote['notes'])); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Current Proposed Bid</strong></td>
                                    <td>
                                        <h4 style="margin: 0; color: #F59E0B; font-weight: bold;">
                                            <?php echo $quote['unit_price'] > 0 ? '$' . htmlspecialchars($quote['unit_price']) . ' / unit' : 'Awaiting Supplier Proposal'; ?>
                                        </h4>
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>Proposal Status</strong></td>
                                    <td>
                                        <?php if($quote['status'] == 'Pending'): ?>
                                            <span class="label label-warning" style="background-color: #F59E0B;">Awaiting Proposal</span>
                                        <?php elseif($quote['status'] == 'Approved'): ?>
                                            <span class="label label-success" style="background-color: #5cb85c;">Proposal Bid Ready</span>
                                        <?php else: ?>
                                            <span class="label label-danger">Closed / Declined</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            </table>
                            
                            <?php if($quote['status'] == 'Approved' && $quote['unit_price'] > 0): ?>
                                <div style="margin-top: 20px;">
                                    <div class="alert alert-info">The supplier has prepared a wholesale proposal. Please contact them or agree via messages to finalise orders.</div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Chat Message Box Column -->
                    <div class="col-md-7">
                        <div style="background: white; border: 1px solid #ddd; border-radius: 5px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                            <h4 style="font-weight: bold; color: #1F2937; margin-top: 0;"><i class="fa fa-envelope-o"></i> Message History with Supplier</h4>
                            <hr style="margin-top: 5px;">
                            
                            <!-- Messages Window -->
                            <div style="height: 350px; overflow-y: scroll; background: #fafafa; padding: 15px; border-radius: 4px; border: 1px solid #eee; margin-bottom: 20px;">
                                <?php
                                $statement = $pdo->prepare("SELECT * FROM tbl_message 
                                                            WHERE (sender_type = 'Supplier' AND sender_id = ? AND recipient_type = 'Customer' AND recipient_id = ?)
                                                               OR (sender_type = 'Customer' AND sender_id = ? AND recipient_type = 'Supplier' AND recipient_id = ?)
                                                            ORDER BY message_id ASC");
                                $statement->execute(array($quote['supplier_id'], $cust_id, $cust_id, $quote['supplier_id']));
                                $messages = $statement->fetchAll(PDO::FETCH_ASSOC);

                                if (count($messages) > 0) {
                                    foreach ($messages as $msg) {
                                        $is_customer = ($msg['sender_type'] == 'Customer');
                                        $bubble_style = $is_customer ? 'margin-left: 20%; background: #F59E0B; color: white;' : 'margin-right: 20%; background: #eaeaea; color: #333;';
                                        $alignment = $is_customer ? 'text-align: right;' : 'text-align: left;';
                                        ?>
                                        <div style="margin-bottom: 15px; <?php echo $alignment; ?>">
                                            <div style="display: inline-block; padding: 10px 15px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); <?php echo $bubble_style; ?>">
                                                <strong><?php echo $is_customer ? 'You' : htmlspecialchars($quote['supplier_name']); ?>:</strong><br>
                                                <?php echo nl2br(htmlspecialchars($msg['message_content'])); ?>
                                                <div style="font-size: 9px; opacity: 0.7; margin-top: 5px;">
                                                    <?php echo date('d M Y, h:i A', strtotime($msg['created_at'])); ?>
                                                </div>
                                            </div>
                                        </div>
                                        <?php
                                    }
                                } else {
                                    echo '<p class="text-muted text-center" style="margin-top: 100px;">No messages in thread yet.</p>';
                                }
                                ?>
                            </div>

                            <!-- Send Reply Form -->
                            <form action="" method="post">
                                <div class="form-group">
                                    <textarea class="form-control" name="reply_msg" rows="3" placeholder="Type your reply message, counter bid, or questions to the supplier..." required></textarea>
                                </div>
                                <button type="submit" class="btn btn-warning" name="form_send_msg" style="background-color: #F59E0B; border-color: #F59E0B; font-weight: bold;">Send Message to Supplier</button>
                            </form>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<?php require_once('footer.php'); ?>
