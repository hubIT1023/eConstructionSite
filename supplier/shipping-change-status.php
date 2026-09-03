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
}
?>

<?php
	$statement = $pdo->prepare("UPDATE tbl_payment SET shipping_status=? WHERE id=?");
	$statement->execute(array($_REQUEST['task'],$_REQUEST['id']));

	$redirect_to = (isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], 'paid-orders.php') !== false) ? 'paid-orders.php' : 'order.php';
	header('location: ' . $redirect_to);
?>