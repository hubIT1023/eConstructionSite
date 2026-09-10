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
	// SECURITY RULE: Direct status bypass prevented.
	// Marking as Paid requires actual POS payment completion.
	if ($_REQUEST['task'] === 'Paid') {
		header('location: pos.php?po_id=' . urlencode($payment['payment_id']));
		exit;
	}

	$statement = $pdo->prepare("UPDATE tbl_payment SET payment_status=? WHERE id=? AND supplier_id=?");
	$statement->execute(array($_REQUEST['task'], $_REQUEST['id'], $supplier_id));

	header('location: order.php');
	exit;
?>
