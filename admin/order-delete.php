<?php require_once('header.php'); ?>

<?php
if(!isset($_REQUEST['id'])) {
	header('location: logout.php');
	exit;
} else {
	// Check the id is valid or not
	$statement = $pdo->prepare("SELECT * FROM tbl_payment WHERE id=?");
	$statement->execute(array($_REQUEST['id']));
	$total = $statement->rowCount();
	if( $total == 0 ) {
		header('location: logout.php');
		exit;
	} else {
		$result = $statement->fetchAll(PDO::FETCH_ASSOC);							
		foreach ($result as $row) {
			$payment_id = $row['payment_id'];
			$payment_status = $row['payment_status'];
			$shipping_status = $row['shipping_status'];
		}
	}
}
?>

<?php
	
	if( ($payment_status == 'Completed') && ($shipping_status == 'Completed') ):
		// No return to stock
	else:
		// Return the stock for non-cancelled/non-returned items
		$statement = $pdo->prepare("SELECT * FROM tbl_order WHERE payment_id=?");
		$statement->execute(array($payment_id));
		$result = $statement->fetchAll(PDO::FETCH_ASSOC);							
		foreach ($result as $row) {
			$p_id = (int)$row['product_id'];
			$item_type = isset($row['item_type']) ? $row['item_type'] : 'STANDARD';
			$order_item_id = (int)$row['id'];

			// Skip return credits (exchange lines) or special orders
			if ($item_type === 'RETURN_CREDIT' || $p_id <= 0) {
				continue;
			}

			// Check how many units were already restocked via tbl_returns
			$already_restocked = 0;
			try {
				$stmt_ret = $pdo->prepare("
					SELECT COALESCE(SUM(ri.quantity_returned), 0) AS total_restocked
					FROM tbl_return_items ri
					JOIN tbl_returns r ON ri.return_id = r.return_id
					WHERE ri.order_item_id = ? AND r.status IN ('COMPLETED', 'APPROVED', 'REFUNDED') AND ri.restock_status = 'RESTOCKED'
				");
				$stmt_ret->execute(array($order_item_id));
				$ret_row = $stmt_ret->fetch(PDO::FETCH_ASSOC);
				if ($ret_row) {
					$already_restocked = (int)$ret_row['total_restocked'];
				}
			} catch (Exception $e) {}

			$orig_qty = max(0, (int)$row['quantity']);
			$qty_to_restore = max(0, $orig_qty - $already_restocked);

			if ($qty_to_restore > 0) {
				$statement1 = $pdo->prepare("UPDATE tbl_product SET p_qty = p_qty + ? WHERE p_id = ?");
				$statement1->execute(array($qty_to_restore, $p_id));
			}
		}	
	endif;	

	// Delete from tbl_order
	$statement = $pdo->prepare("DELETE FROM tbl_order WHERE payment_id=?");
	$statement->execute(array($payment_id));

	// Delete from tbl_payment
	$statement = $pdo->prepare("DELETE FROM tbl_payment WHERE id=?");
	$statement->execute(array($_REQUEST['id']));

	header('location: order.php');
?>