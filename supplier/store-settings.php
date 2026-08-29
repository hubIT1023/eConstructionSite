<?php require_once('header.php'); ?>

<?php
// Handle form updates
if(isset($_POST['form_update'])) {
    $valid = 1;
    $company_name = trim($_POST['company_name']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $description = trim($_POST['description']);
    $delivery_areas = trim($_POST['delivery_areas']);
    $certifications = trim($_POST['certifications']);

    if(empty($company_name)) {
        $valid = 0;
        $error_message = "Company Name cannot be empty.";
    }

    if($valid == 1) {
        $statement = $pdo->prepare("UPDATE tbl_supplier SET supplier_name = ?, supplier_phone = ?, supplier_address = ?, supplier_description = ?, supplier_delivery_areas = ?, supplier_certifications = ? WHERE supplier_id = ?");
        $statement->execute(array($company_name, $phone, $address, $description, $delivery_areas, $certifications, $supplier_id));

        $success_message = "Storefront details updated successfully!";
    }
}

// Fetch current details
$statement = $pdo->prepare("SELECT * FROM tbl_supplier WHERE supplier_id = ?");
$statement->execute(array($supplier_id));
$store = $statement->fetch(PDO::FETCH_ASSOC);
?>

<section class="content-header">
	<div class="content-header-left">
		<h1>Storefront & Company Settings</h1>
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
                            <label for="" class="col-sm-3 control-label">Company Name *</label>
                            <div class="col-sm-8">
                                <input type="text" class="form-control" name="company_name" value="<?php echo htmlspecialchars($store['supplier_name']); ?>" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="" class="col-sm-3 control-label">Business Phone</label>
                            <div class="col-sm-8">
                                <input type="text" class="form-control" name="phone" value="<?php echo htmlspecialchars($store['supplier_phone']); ?>">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="" class="col-sm-3 control-label">Store/Warehouse Address</label>
                            <div class="col-sm-8">
                                <input type="text" class="form-control" name="address" value="<?php echo htmlspecialchars($store['supplier_address']); ?>">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="" class="col-sm-3 control-label">Company Profile Description</label>
                            <div class="col-sm-8">
                                <textarea class="form-control" name="description" rows="4"><?php echo htmlspecialchars($store['supplier_description']); ?></textarea>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="" class="col-sm-3 control-label">Delivery Scope / Areas</label>
                            <div class="col-sm-8">
                                <input type="text" class="form-control" name="delivery_areas" value="<?php echo htmlspecialchars($store['supplier_delivery_areas']); ?>" placeholder="e.g. Nationwide, Regional, State-wide...">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="" class="col-sm-3 control-label">Certifications / Badges</label>
                            <div class="col-sm-8">
                                <input type="text" class="form-control" name="certifications" value="<?php echo htmlspecialchars($store['supplier_certifications']); ?>" placeholder="e.g. ISO 9001, ANSI Approved...">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="" class="col-sm-3 control-label">SaaS Subscription Plan</label>
                            <div class="col-sm-8" style="padding-top: 7px;">
                                <span class="label label-warning" style="background-color: #F59E0B; font-size: 14px;"><?php echo htmlspecialchars($store['supplier_plan']); ?> Plan</span>
                                <span class="text-muted" style="margin-left: 10px;">Commission Rate: <?php echo htmlspecialchars($store['supplier_commission']); ?>%</span>
                            </div>
                        </div>

                        <div class="form-group">
                            <div class="col-sm-offset-3 col-sm-8">
                                <button type="submit" class="btn btn-success" name="form_update">Save Settings</button>
                            </div>
                        </div>

                    </form>
				</div>
			</div>
		</div>
	</div>
</section>

<?php require_once('footer.php'); ?>
