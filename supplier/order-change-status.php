<?php require_once('header.php'); ?>

<?php
if( !isset($_REQUEST['id']) || !isset($_REQUEST['task']) ) {
	header('location: logout.php');
	exit;
} else {
	// Check the id is valid or not and belongs to the supplier
	$statement = $pdo->prepare("SELECT * FROM tbl_payment WHERE id=? AND supplier_id=?");
	$statement->execute(array($_REQUEST['id'], $supplier_id));
	$total = $statement->rowCount();
	if( $total == 0 ) {
		header('location: order.php');
		exit;
	}
	$payment = $statement->fetch(PDO::FETCH_ASSOC);
}
?>

<?php
	$statement = $pdo->prepare("UPDATE tbl_payment SET payment_status=? WHERE id=?");
	$statement->execute(array($_REQUEST['task'], $_REQUEST['id']));

	// If marked as Paid, send system-generated receipt emails
	if ($_REQUEST['task'] == 'Paid') {
		$payment_id = $payment['payment_id'];
		$paid_amount = $payment['paid_amount'];
		$customer_name = $payment['customer_name'];
		$customer_email = $payment['customer_email'];
		$payment_date = $payment['payment_date'];

		// Fetch customer address details
		$statement_c = $pdo->prepare("SELECT * FROM tbl_customer WHERE cust_id=?");
		$statement_c->execute(array($payment['customer_id']));
		$cust_data = $statement_c->fetch(PDO::FETCH_ASSOC);
		$cust_address_text = 'Not Available';
		if ($cust_data) {
			$cust_country_id = $cust_data['cust_country'];
			$statement_cnt = $pdo->prepare("SELECT * FROM tbl_country WHERE country_id=?");
			$statement_cnt->execute(array($cust_country_id));
			$cnt_data = $statement_cnt->fetch(PDO::FETCH_ASSOC);
			$cust_city_town = $cnt_data ? $cnt_data['country_name'] : '';
			$cust_address_text = htmlspecialchars($cust_data['cust_address']) . ', ' . htmlspecialchars($cust_city_town) . ', ' . htmlspecialchars($cust_data['cust_zip']);
		}

		// Fetch supplier info
		$statement_sup = $pdo->prepare("SELECT supplier_name, supplier_email, supplier_address, supplier_phone FROM tbl_supplier WHERE supplier_id=?");
		$statement_sup->execute(array($supplier_id));
		$supplier = $statement_sup->fetch(PDO::FETCH_ASSOC);

		// Fetch order items
		$statement_ord = $pdo->prepare("SELECT * FROM tbl_order WHERE payment_id=?");
		$statement_ord->execute(array($payment_id));
		$items = $statement_ord->fetchAll(PDO::FETCH_ASSOC);

		// Build invoice items HTML table
        $order_list_html = '<table border="1" cellpadding="5" cellspacing="0" style="border-collapse: collapse; width: 100%;">
                                <thead>
                                    <tr style="background: #f9f9f9;">
                                        <th>Product Name</th>
                                        <th>Size</th>
                                        <th>Color</th>
                                        <th>Quantity</th>
                                        <th>Unit Price</th>
                                        <th>Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody>';

		$subtotal_items = 0;
        foreach ($items as $item) {
            $item_subtotal = $item['unit_price'] * $item['quantity'];
			$subtotal_items += $item_subtotal;
            $order_list_html .= '<tr>
                                    <td>' . htmlspecialchars($item['product_name']) . '</td>
                                    <td>' . htmlspecialchars($item['size']) . '</td>
                                    <td>' . htmlspecialchars($item['color']) . '</td>
                                    <td>' . htmlspecialchars($item['quantity']) . '</td>
                                    <td>₱' . number_format($item['unit_price'], 2) . '</td>
                                    <td>₱' . number_format($item_subtotal, 2) . '</td>
                                 </tr>';
        }

		$delivery_cost = $paid_amount - $subtotal_items;
		if ($delivery_cost < 0) {
			$delivery_cost = 0;
		}

        $order_list_html .= '<tr>
                                <th colspan="5" align="right">Subtotal:</th>
                                <th>₱' . number_format($subtotal_items, 2) . '</th>
                             </tr>
                             <tr>
                                <th colspan="5" align="right">Delivery:</th>
                                <th>₱' . number_format($delivery_cost, 2) . '</th>
                             </tr>
                             <tr style="background: #f5f5f5; color: #337ab7;">
                                <th colspan="5" align="right">Total Paid:</th>
                                <th>₱' . number_format($paid_amount, 2) . '</th>
                             </tr>
                             </tbody></table>';

        // Send Email to Customer
        $to_customer = $customer_email;
        $subject_customer = "Payment Receipt - Order #" . $payment_id;
        
        $message_customer = '
        <html>
        <body>
            <h3>Dear ' . htmlspecialchars($customer_name) . ',</h3>
            <p>Thank you! Your payment for order <strong>#' . htmlspecialchars($payment_id) . '</strong> has been received by the supplier.</p>
            <p>Here is your system-generated official payment receipt:</p>
            <hr>
            <p><strong>Payment Date:</strong> ' . htmlspecialchars($payment_date) . '<br>
            <strong>Payment Method:</strong> Over the Counter<br>
            <strong>Status:</strong> Paid</p>
            <hr>
            <p><strong>Customer:</strong> ' . htmlspecialchars($customer_name) . '<br>
            <strong>Email:</strong> ' . htmlspecialchars($customer_email) . '<br>
            <strong>Address:</strong> ' . $cust_address_text . '</p>
            <hr>
            <p><strong>Supplier:</strong> ' . htmlspecialchars($supplier['supplier_name']) . '<br>
            <strong>Address:</strong> ' . nl2br(htmlspecialchars($supplier['supplier_address'])) . '<br>
            <strong>Phone:</strong> ' . htmlspecialchars($supplier['supplier_phone']) . '</p>
            <hr>
            <h4>Receipt Summary:</h4>
            ' . $order_list_html . '
            <br>
            <p>Best regards,<br>eConstruction Supply Team</p>
        </body>
        </html>';

        send_system_email($to_customer, $subject_customer, $message_customer);

        // Send Email to Supplier
        $to_supplier = $supplier['supplier_email'];
        $subject_supplier = "Payment Receipt Confirmation - Order #" . $payment_id;
        
        $message_supplier = '
        <html>
        <body>
            <h3>Dear ' . htmlspecialchars($supplier['supplier_name']) . ' Team,</h3>
            <p>Payment receipt has been generated and sent for order <strong>#' . htmlspecialchars($payment_id) . '</strong>.</p>
            <hr>
            <p><strong>Customer Name:</strong> ' . htmlspecialchars($customer_name) . '<br>
            <strong>Customer Email:</strong> ' . htmlspecialchars($customer_email) . '<br>
            <strong>Customer Address:</strong> ' . $cust_address_text . '</p>
            <hr>
            <h4>Receipt Summary:</h4>
            ' . $order_list_html . '
            <br>
            <p>Best regards,<br>eConstruction Supply Team</p>
        </body>
        </html>';

        send_system_email($to_supplier, $subject_supplier, $message_supplier);
	}

	if ($_REQUEST['task'] == 'Paid') {
		header('location: paid-orders.php');
	} else {
		header('location: order.php');
	}
?>