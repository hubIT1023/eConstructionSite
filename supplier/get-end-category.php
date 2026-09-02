<?php
include 'inc/config.php';
if(!empty($_POST['id']))
{
	$id = $_POST['id'];
	
	$statement = $pdo->prepare("SELECT * FROM tbl_end_category WHERE mcat_id=? ORDER BY ecat_name ASC");
	$statement->execute(array($id));
	$result = $statement->fetchAll(PDO::FETCH_ASSOC);
	echo '<option value="">Select End Level Category</option>';
	foreach ($result as $row) {
		echo '<option value="'.$row['ecat_id'].'">'.htmlspecialchars($row['ecat_name']).'</option>';
	}
	echo '<option value="other_new" style="font-weight: bold; color: #2563eb;">+ Add New Category (Not Found in List)</option>';
}
?>