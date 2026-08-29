<?php require_once('header.php'); ?>

<?php
if(!isset($_REQUEST['id'])) {
	header('location: product.php');
	exit;
} else {
	// Check the id is valid and belongs to the supplier
	$statement = $pdo->prepare("SELECT * FROM tbl_product WHERE p_id=? AND supplier_id=?");
	$statement->execute(array($_REQUEST['id'], $supplier_id));
	$total = $statement->rowCount();
	if( $total == 0 ) {
		header('location: product.php');
		exit;
	}
}
?>

<?php
	// Getting photo ID to unlink from folder
	$statement = $pdo->prepare("SELECT * FROM tbl_product WHERE p_id=?");
	$statement->execute(array($_REQUEST['id']));
	$result = $statement->fetchAll(PDO::FETCH_ASSOC);							
	foreach ($result as $row) {
		$p_featured_photo = $row['p_featured_photo'];
        if(file_exists('../assets/uploads/'.$p_featured_photo)) {
            unlink('../assets/uploads/'.$p_featured_photo);
        }
	}

	// Getting other photo ID to unlink from folder
	$statement = $pdo->prepare("SELECT * FROM tbl_product_photo WHERE p_id=?");
	$statement->execute(array($_REQUEST['id']));
	$result = $statement->fetchAll(PDO::FETCH_ASSOC);							
	foreach ($result as $row) {
		$photo = $row['photo'];
        if(file_exists('../assets/uploads/product_photos/'.$photo)) {
            unlink('../assets/uploads/product_photos/'.$photo);
        }
	}

	// Delete from tbl_product
	$statement = $pdo->prepare("DELETE FROM tbl_product WHERE p_id=?");
	$statement->execute(array($_REQUEST['id']));

	// Delete from tbl_product_photo
	$statement = $pdo->prepare("DELETE FROM tbl_product_photo WHERE p_id=?");
	$statement->execute(array($_REQUEST['id']));

	// Delete from tbl_product_size
	$statement = $pdo->prepare("DELETE FROM tbl_product_size WHERE p_id=?");
	$statement->execute(array($_REQUEST['id']));

	// Delete from tbl_product_color
	$statement = $pdo->prepare("DELETE FROM tbl_product_color WHERE p_id=?");
	$statement->execute(array($_REQUEST['id']));

	// Delete from tbl_rating
	$statement = $pdo->prepare("DELETE FROM tbl_rating WHERE p_id=?");
	$statement->execute(array($_REQUEST['id']));

	// Delete from tbl_order
	$statement = $pdo->prepare("DELETE FROM tbl_order WHERE product_id=?");
	$statement->execute(array($_REQUEST['id']));

	header('location: product.php');
?>