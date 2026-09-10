<?php require_once('header.php'); ?>

<?php
if(!isset($_REQUEST['id'])) {
	header('location: supplier.php');
	exit;
}

$supplier_id = (int)$_REQUEST['id'];

// Count current POS users for this tenant
$stmt_cnt = $pdo->prepare("SELECT COUNT(*) as pos_count FROM tbl_supplier_user WHERE supplier_id = ? AND UPPER(role) IN ('USER', 'POS_USER', 'CASHIER')");
$stmt_cnt->execute(array($supplier_id));
$current_pos_count = (int)$stmt_cnt->fetch(PDO::FETCH_ASSOC)['pos_count'];

$storage_stats = get_tenant_storage_stats($pdo, $supplier_id);

// Handle updates
if(isset($_POST['form_edit_supplier'])) {
    $plan = trim($_POST['supplier_plan']);
    $commission = floatval($_POST['supplier_commission']);
    $status = trim($_POST['supplier_status']);
    $max_pos_users = isset($_POST['max_pos_users']) ? intval($_POST['max_pos_users']) : 3;
    $max_storage_mb = isset($_POST['max_storage_mb']) ? intval($_POST['max_storage_mb']) : 2048;

    // Determine default limits by plan if not manually set
    $plan_limits = ['Starter' => 3, 'Professional' => 10, 'Enterprise' => 50];
    $plan_storage = ['Starter' => 500, 'Professional' => 2048, 'Enterprise' => 10240];
    
    if ($max_pos_users <= 0) {
        $max_pos_users = isset($plan_limits[$plan]) ? $plan_limits[$plan] : 3;
    }
    if ($max_storage_mb <= 0) {
        $max_storage_mb = isset($plan_storage[$plan]) ? $plan_storage[$plan] : 2048;
    }

    // Downgrade Safety Check: Block downgrade if active users exceed target limit
    if ($max_pos_users < $current_pos_count) {
        $error_message = "<strong>Plan Downgrade Not Available:</strong> This tenant currently has <strong>{$current_pos_count} POS Users</strong>. " .
                         "The requested limit allows only <strong>{$max_pos_users} POS Users</strong>.<br>" .
                         "Please have the supplier reduce or suspend unnecessary POS users before downgrading their plan allowance.";
    } else {
        $statement = $pdo->prepare("UPDATE tbl_supplier SET supplier_plan = ?, max_pos_users = ?, max_storage_mb = ?, supplier_commission = ?, supplier_status = ? WHERE supplier_id = ?");
        $statement->execute(array($plan, $max_pos_users, $max_storage_mb, $commission, $status, $supplier_id));

        // If status is suspended, we also suspend their users
        if ($status == 'Suspended' || $status == 'Pending') {
            $statement_usr = $pdo->prepare("UPDATE tbl_supplier_user SET status = 'Suspended' WHERE supplier_id = ?");
            $statement_usr->execute(array($supplier_id));
        } else {
            $statement_usr = $pdo->prepare("UPDATE tbl_supplier_user SET status = 'Active' WHERE supplier_id = ? AND status = 'Suspended'");
            $statement_usr->execute(array($supplier_id));
        }

        $success_message = "Supplier SaaS configuration, POS user limit, and storage quota updated successfully!";
        // Reload storage stats
        $storage_stats = get_tenant_storage_stats($pdo, $supplier_id);
    }
}

// Fetch current details
$statement = $pdo->prepare("SELECT * FROM tbl_supplier WHERE supplier_id = ?");
$statement->execute(array($supplier_id));
$supplier = $statement->fetch(PDO::FETCH_ASSOC);

if (!$supplier) {
    header('location: supplier.php');
    exit;
}

$current_max_users = isset($supplier['max_pos_users']) && (int)$supplier['max_pos_users'] > 0 ? (int)$supplier['max_pos_users'] : 3;
$current_max_storage = isset($supplier['max_storage_mb']) && (int)$supplier['max_storage_mb'] > 0 ? (int)$supplier['max_storage_mb'] : 2048;
?>

<section class="content-header">
	<div class="content-header-left">
		<h1>Edit Supplier SaaS Details & POS User Allowance</h1>
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
                            <label for="" class="col-sm-3 control-label">Email / Store Slug</label>
                            <div class="col-sm-8" style="padding-top: 7px;">
                                <?php echo htmlspecialchars($supplier['supplier_email']); ?> (<code><?php echo htmlspecialchars($supplier['supplier_slug']); ?></code>)
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="" class="col-sm-3 control-label">Current POS Users</label>
                            <div class="col-sm-8" style="padding-top: 7px;">
                                <span class="badge <?php echo ($current_pos_count >= $current_max_users) ? 'badge-danger' : 'badge-success'; ?>" style="font-size: 13px; background-color: <?php echo ($current_pos_count >= $current_max_users) ? '#ef4444' : '#10b981'; ?>;">
                                    <?php echo $current_pos_count; ?> / <?php echo $current_max_users; ?> Used
                                </span>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="" class="col-sm-3 control-label">Current Storage & Data</label>
                            <div class="col-sm-8" style="padding-top: 7px;">
                                <span class="badge" style="font-size: 13px; background-color: #0284c7;">
                                    <?php echo htmlspecialchars($storage_stats['formatted_usage']); ?> / <?php echo htmlspecialchars($storage_stats['formatted_max']); ?> (<?php echo $storage_stats['used_pct']; ?>% Used)
                                </span>
                                <div style="font-size: 12px; color: #64748b; margin-top: 5px;">
                                    <span><i class="fa fa-file-image-o text-info"></i> Media: <strong><?php echo $storage_stats['formatted_file_usage']; ?></strong> (<?php echo $storage_stats['total_files']; ?> files)</span> &bull; 
                                    <span><i class="fa fa-database text-warning"></i> Data: <strong><?php echo $storage_stats['formatted_db_usage']; ?></strong> (<?php echo $storage_stats['db_records_total']; ?> records)</span>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="supplier_plan" class="col-sm-3 control-label">SaaS Subscription Plan *</label>
                            <div class="col-sm-8">
                                <select class="form-control" name="supplier_plan" id="supplier_plan" onchange="autoFillPlanLimit(this.value)" required>
                                    <option value="Starter" <?php if($supplier['supplier_plan'] == 'Starter') echo 'selected'; ?>>Starter Plan (Default: 3 POS Users, 500 MB Storage)</option>
                                    <option value="Professional" <?php if($supplier['supplier_plan'] == 'Professional') echo 'selected'; ?>>Professional Plan (Default: 10 POS Users, 2 GB Storage)</option>
                                    <option value="Enterprise" <?php if($supplier['supplier_plan'] == 'Enterprise') echo 'selected'; ?>>Enterprise Plan (Default: 50 POS Users, 10 GB Storage)</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="max_pos_users" class="col-sm-3 control-label">Maximum POS Users *</label>
                            <div class="col-sm-8">
                                <input type="number" min="1" max="500" class="form-control" name="max_pos_users" id="max_pos_users" value="<?php echo htmlspecialchars($current_max_users); ?>" required>
                                <small class="text-muted"><i class="fa fa-info-circle"></i> SaaS Admin controls the maximum number of cashier accounts this tenant can create.</small>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="max_storage_mb" class="col-sm-3 control-label">Storage Quota (MB) *</label>
                            <div class="col-sm-8">
                                <input type="number" min="50" max="102400" step="50" class="form-control" name="max_storage_mb" id="max_storage_mb" value="<?php echo htmlspecialchars($current_max_storage); ?>" required>
                                <small class="text-muted"><i class="fa fa-hdd-o"></i> Cloud storage allowance in Megabytes (500 MB = Starter, 2048 MB = 2 GB, 10240 MB = 10 GB).</small>
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

<script>
function autoFillPlanLimit(plan) {
    var userLimits = {
        'Starter': 3,
        'Professional': 10,
        'Enterprise': 50
    };
    var storageLimits = {
        'Starter': 500,
        'Professional': 2048,
        'Enterprise': 10240
    };
    if (userLimits[plan]) {
        document.getElementById('max_pos_users').value = userLimits[plan];
    }
    if (storageLimits[plan]) {
        document.getElementById('max_storage_mb').value = storageLimits[plan];
    }
}
</script>

<?php require_once('footer.php'); ?>
