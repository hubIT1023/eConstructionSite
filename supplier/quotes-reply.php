<?php require_once('header.php'); ?>

<?php
if(!isset($_GET['id'])) {
    header('location: quotes.php');
    exit;
}

// Fetch RFQ details and verify supplier tenancy
$statement = $pdo->prepare("SELECT q.*, c.cust_name, c.cust_email, qi.quantity, qi.unit_price, p.p_name, p.p_id 
                            FROM tbl_quote q
                            JOIN tbl_customer c ON q.cust_id = c.cust_id
                            LEFT JOIN tbl_quote_item qi ON q.quote_id = qi.quote_id
                            LEFT JOIN tbl_product p ON qi.product_id = p.p_id
                            WHERE q.quote_id = ? AND q.supplier_id = ?");
$statement->execute(array($_GET['id'], $supplier_id));
$quote = $statement->fetch(PDO::FETCH_ASSOC);

if (!$quote) {
    header('location: quotes.php');
    exit;
}

// Handle updates / messages
if (isset($_POST['form_reply'])) {
    $status = $_POST['status'];
    $bid_price = floatval($_POST['bid_price']);
    $reply_msg = trim($_POST['reply_msg']);

    // Update quote status
    $statement = $pdo->prepare("UPDATE tbl_quote SET status = ?, updated_at = NOW() WHERE quote_id = ?");
    $statement->execute(array($status, $quote['quote_id']));

    // Update item bid price
    $statement = $pdo->prepare("UPDATE tbl_quote_item SET unit_price = ? WHERE quote_id = ?");
    $statement->execute(array($bid_price, $quote['quote_id']));

    // Save B2B message
    if (!empty($reply_msg)) {
        $statement = $pdo->prepare("INSERT INTO tbl_message (sender_type, sender_id, recipient_type, recipient_id, message_content) VALUES (?, ?, ?, ?, ?)");
        $statement->execute(array('Supplier', $supplier_id, 'Customer', $quote['cust_id'], $reply_msg));
    }

    // Auto notify about bid price update
    $bid_notification = "Bid price updated to ₱" . $bid_price . " per unit. Status: " . $status;
    $statement = $pdo->prepare("INSERT INTO tbl_message (sender_type, sender_id, recipient_type, recipient_id, message_content) VALUES (?, ?, ?, ?, ?)");
    $statement->execute(array('Supplier', $supplier_id, 'Customer', $quote['cust_id'], $bid_notification));

    $success_message = "Your bid and reply were successfully submitted!";
    
    // Refresh data
    $statement = $pdo->prepare("SELECT q.*, c.cust_name, c.cust_email, qi.quantity, qi.unit_price, p.p_name, p.p_id 
                                FROM tbl_quote q
                                JOIN tbl_customer c ON q.cust_id = c.cust_id
                                LEFT JOIN tbl_quote_item qi ON q.quote_id = qi.quote_id
                                LEFT JOIN tbl_product p ON qi.product_id = p.p_id
                                WHERE q.quote_id = ? AND q.supplier_id = ?");
    $statement->execute(array($_GET['id'], $supplier_id));
    $quote = $statement->fetch(PDO::FETCH_ASSOC);
}
?>

<section class="content-header">
	<div class="content-header-left">
		<h1>RFQ Quote Reply & Bid</h1>
	</div>
	<div class="content-header-right">
		<a href="quotes.php" class="btn btn-primary btn-sm">Back to RFQs</a>
	</div>
</section>

<section class="content">
	<div class="row">
		<div class="col-md-6">
			<div class="box box-info">
				<div class="box-header with-border">
					<h3 class="box-title">RFQ details</h3>
				</div>
				<div class="box-body">
                    <table class="table table-bordered">
                        <tr>
                            <td width="150"><strong>Customer Name</strong></td>
                            <td><?php echo htmlspecialchars($quote['cust_name']); ?> (<?php echo htmlspecialchars($quote['cust_email']); ?>)</td>
                        </tr>
                        <tr>
                            <td><strong>Material Requested</strong></td>
                            <td><?php echo htmlspecialchars($quote['p_name'] ?: 'General'); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Quantity</strong></td>
                            <td><?php echo htmlspecialchars($quote['quantity']); ?> units</td>
                        </tr>
                        <tr>
                            <td><strong>Contractor Notes</strong></td>
                            <td><?php echo nl2br(htmlspecialchars($quote['notes'])); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Created Date</strong></td>
                            <td><?php echo date('d M Y, h:i A', strtotime($quote['created_at'])); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Current Status</strong></td>
                            <td>
                                <?php if($quote['status'] == 'Pending'): ?>
                                    <span class="label label-warning">Pending Review</span>
                                <?php elseif($quote['status'] == 'Approved'): ?>
                                    <span class="label label-success">Quoted / Approved</span>
                                <?php else: ?>
                                    <span class="label label-danger">Declined</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </table>
				</div>
			</div>

            <!-- Submit Bid Form -->
			<div class="box box-success">
				<div class="box-header with-border">
					<h3 class="box-title">Prepare B2B Wholesale Proposal / Bid</h3>
				</div>
				<div class="box-body">
                    <?php if (isset($success_message) && $success_message != ''): ?>
                        <div class="alert alert-success"><?php echo $success_message; ?></div>
                    <?php endif; ?>
                    
                    <form action="" method="post">
                        <div class="form-group">
                            <label for="bid_price">Proposed Unit Price Bid (₱ PHP) *</label>
                            <input type="text" class="form-control" name="bid_price" value="<?php echo htmlspecialchars($quote['unit_price']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="status">Action Status *</label>
                            <select class="form-control" name="status" required>
                                <option value="Pending" <?php if($quote['status'] == 'Pending') echo 'selected'; ?>>Keep Pending</option>
                                <option value="Approved" <?php if($quote['status'] == 'Approved') echo 'selected'; ?>>Approve / Send Proposal</option>
                                <option value="Declined" <?php if($quote['status'] == 'Declined') echo 'selected'; ?>>Decline Quote</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="reply_msg">Logistics proposal / Delivery notes to Contractor</label>
                            <textarea class="form-control" name="reply_msg" rows="4" placeholder="Detail shipping dates, credit terms, loading dock coordination..."></textarea>
                        </div>
                        <button type="submit" class="btn btn-success" name="form_reply">Submit Proposal & Reply</button>
                    </form>
				</div>
			</div>
		</div>

        <!-- Messaging History Thread -->
		<div class="col-md-6">
			<div class="box box-warning">
				<div class="box-header with-border">
					<h3 class="box-title">RFQ Message Thread</h3>
				</div>
				<div class="box-body" style="height: 400px; overflow-y: scroll; background: #f4f6f9; padding: 15px; border-radius: 4px; border: 1px solid #ddd;">
                    <?php
                    $statement = $pdo->prepare("SELECT * FROM tbl_message 
                                                WHERE (sender_type = 'Supplier' AND sender_id = ? AND recipient_type = 'Customer' AND recipient_id = ?)
                                                   OR (sender_type = 'Customer' AND sender_id = ? AND recipient_type = 'Supplier' AND recipient_id = ?)
                                                ORDER BY message_id ASC");
                    $statement->execute(array($supplier_id, $quote['cust_id'], $quote['cust_id'], $supplier_id));
                    $messages = $statement->fetchAll(PDO::FETCH_ASSOC);
                    
                    if (count($messages) > 0) {
                        foreach ($messages as $msg) {
                            $is_supplier = ($msg['sender_type'] == 'Supplier');
                            $bubble_style = $is_supplier ? 'margin-left: 20%; background: #00a65a; color: white;' : 'margin-right: 20%; background: white; color: #333;';
                            $alignment = $is_supplier ? 'text-align: right;' : 'text-align: left;';
                            ?>
                            <div style="margin-bottom: 15px; <?php echo $alignment; ?>">
                                <div style="display: inline-block; padding: 10px 15px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); <?php echo $bubble_style; ?>">
                                    <strong><?php echo $is_supplier ? 'You' : htmlspecialchars($quote['cust_name']); ?>:</strong><br>
                                    <?php echo nl2br(htmlspecialchars($msg['message_content'])); ?>
                                    <div style="font-size: 9px; opacity: 0.7; margin-top: 5px;">
                                        <?php echo date('d M Y, h:i A', strtotime($msg['created_at'])); ?>
                                    </div>
                                </div>
                            </div>
                            <?php
                        }
                    } else {
                        echo '<p class="text-muted text-center" style="margin-top: 50px;">No messages in thread yet.</p>';
                    }
                    ?>
				</div>
			</div>
		</div>
	</div>
</section>

<?php require_once('footer.php'); ?>
