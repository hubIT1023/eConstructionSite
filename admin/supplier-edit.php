<?php require_once('header.php'); ?>

<?php
if(!isset($_REQUEST['id'])) {
	header('location: supplier.php');
	exit;
}

// Handle updates
if(isset($_POST['form_edit_supplier'])) {
    $plan = $_POST['supplier_plan'];
    $commission = floatval($_POST['supplier_commission']);
    $status = $_POST['supplier_status'];

    $statement = $pdo->prepare("UPDATE tbl_supplier SET supplier_plan = ?, supplier_commission = ?, supplier_status = ? WHERE supplier_id = ?");
    $statement->execute(array($plan, $commission, $status, $_REQUEST['id']));

    // If status is suspended, we also suspend their users
    if ($status == 'Suspended' || $status == 'Pending') {
        $statement_usr = $pdo->prepare("UPDATE tbl_supplier_user SET status = 'Suspended' WHERE supplier_id = ?");
        $statement_usr->execute(array($_REQUEST['id']));
    } else {
        $statement_usr = $pdo->prepare("UPDATE tbl_supplier_user SET status = 'Active' WHERE supplier_id = ?");
        $statement_usr->execute(array($_REQUEST['id']));
    }

    $success_message = "Supplier settings updated successfully!";
}

// Fetch current details
$statement = $pdo->prepare("SELECT * FROM tbl_supplier WHERE supplier_id = ?");
$statement->execute(array($_REQUEST['id']));
$supplier = $statement->fetch(PDO::FETCH_ASSOC);

if (!$supplier) {
    header('location: supplier.php');
    exit;
}
?>

<section class="content-header">
	<div class="content-header-left">
		<h1>Edit Supplier SaaS Details</h1>
	</div>
	<div class="content-header-right">
		<a href="supplier.php" class="btn btn-primary btn-sm">Back to List</a>
	</div>
</section>

<section class="content">
	<div class="row">
		<div class="col-md-8 col-md-offset-2">

			<?php if(isset($error_message) && $error_message != ''): ?>
			<div class="callout callout-danger">
				<p><?php echo $error_message; ?></p>
			</div>
			<?php endif; ?>

			<?php if(isset($success_message) && $success_message != ''): ?>
			<div class="callout callout-success">
				<p><?php echo $success_message; ?></p>
			</div>
			<?php endif; ?>

			<div class="box box-info">
				<div class="box-body">
                    <form class="form-horizontal" action="" method="post">
                        
                        <div class="form-group">
                            <label for="" class="col-sm-3 control-label">Supplier Name</label>
                            <div class="col-sm-8" style="padding-top: 7px;">
                                <strong><?php echo htmlspecialchars($supplier['supplier_name']); ?></strong>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="" class="col-sm-3 control-label">Email / Username</label>
                            <div class="col-sm-8" style="padding-top: 7px;">
                                <?php echo htmlspecialchars($supplier['supplier_email']); ?>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="supplier_plan" class="col-sm-3 control-label">SaaS Subscription Plan *</label>
                            <div class="col-sm-8">
                                <select class="form-control" name="supplier_plan" required>
                                    <option value="Starter" <?php if($supplier['supplier_plan'] == 'Starter') echo 'selected'; ?>>Starter Plan (₱49/mo - 50 products)</option>
                                    <option value="Professional" <?php if($supplier['supplier_plan'] == 'Professional') echo 'selected'; ?>>Professional Plan (₱99/mo - 500 products)</option>
                                    <option value="Enterprise" <?php if($supplier['supplier_plan'] == 'Enterprise') echo 'selected'; ?>>Enterprise Plan (₱249/mo - Unlimited)</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="supplier_commission" class="col-sm-3 control-label">Commission Rate (%) *</label>
                            <div class="col-sm-8">
                                <input type="text" class="form-control" name="supplier_commission" value="<?php echo htmlspecialchars($supplier['supplier_commission']); ?>" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="supplier_status" class="col-sm-3 control-label">Account Status *</label>
                            <div class="col-sm-8">
                                <select class="form-control" name="supplier_status" required>
                                    <option value="Active" <?php if($supplier['supplier_status'] == 'Active') echo 'selected'; ?>>Active</option>
                                    <option value="Pending" <?php if($supplier['supplier_status'] == 'Pending') echo 'selected'; ?>>Pending Review</option>
                                    <option value="Suspended" <?php if($supplier['supplier_status'] == 'Suspended') echo 'selected'; ?>>Suspended</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <div class="col-sm-offset-3 col-sm-8">
                                <button type="submit" class="btn btn-success" name="form_edit_supplier">Save Supplier Configuration</button>
                            </div>
                        </div>

                    </form>
				</div>
			</div>
		</div>
	</div>
</section>

<?php require_once('footer.php'); ?>
