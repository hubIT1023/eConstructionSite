<?php require_once('header.php'); ?>
<?php require_once('inc/erp_admin_helper.php'); ?>
<?php require_once('inc/ai_access_guard.php'); ?>
<?php
ensure_chatgpt_access_schema($pdo);

// Handle Per-Supplier ChatGPT Access Toggle (Block / Allow)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_supplier_chatgpt'])) {
    $csrf->verifyRequest();
    $target_supplier_id = isset($_POST['supplier_id']) ? (int)$_POST['supplier_id'] : 0;
    $new_chatgpt_status = (isset($_POST['allow_chatgpt_access']) && (int)$_POST['allow_chatgpt_access'] === 1) ? 1 : 0;

    if ($target_supplier_id > 0) {
        $stmt_toggle = $pdo->prepare("UPDATE tbl_supplier SET allow_chatgpt_access = ?, chatgpt_access_updated_at = CURRENT_TIMESTAMP WHERE supplier_id = ?");
        $stmt_toggle->execute(array($new_chatgpt_status, $target_supplier_id));

        $stmt_sname = $pdo->prepare("SELECT supplier_name FROM tbl_supplier WHERE supplier_id = ?");
        $stmt_sname->execute(array($target_supplier_id));
        $sname = $stmt_sname->fetchColumn() ?: ('Supplier #' . $target_supplier_id);

        if ($new_chatgpt_status === 1) {
            $success_message = "ChatGPT access to Supplier Database, POS, and ERP is now <strong>ALLOWED</strong> for <strong>" . htmlspecialchars($sname) . "</strong>.";
        } else {
            $success_message = "ChatGPT access to Supplier Database, POS, and ERP is now <strong>BLOCKED</strong> for <strong>" . htmlspecialchars($sname) . "</strong>.";
        }
    }
}

// Handle Global Master Policy Toggle (Global Kill-Switch vs. Per-Supplier Policy)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_global_chatgpt'])) {
    $csrf->verifyRequest();
    $new_global_policy = (isset($_POST['global_allow_chatgpt']) && (int)$_POST['global_allow_chatgpt'] === 1) ? 1 : 0;
    $stmt_g = $pdo->prepare("UPDATE tbl_settings SET global_allow_chatgpt = ? WHERE id = 1");
    $stmt_g->execute(array($new_global_policy));

    if ($new_global_policy === 0) {
        $success_message = "<strong>Global Master Block Activated:</strong> ChatGPT / OpenAI is now blocked from accessing ALL Supplier Databases, POS, and ERP modules platform-wide.";
    } else {
        $success_message = "<strong>Per-Supplier ChatGPT Policy Activated:</strong> ChatGPT access now follows each individual supplier's Block/Allow setting below.";
    }
}

// Handle Bulk Block All / Allow All Suppliers
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_chatgpt_action'])) {
    $csrf->verifyRequest();
    $bulk_val = ($_POST['bulk_chatgpt_action'] === 'allow_all') ? 1 : 0;
    $stmt_b = $pdo->prepare("UPDATE tbl_supplier SET allow_chatgpt_access = ?, chatgpt_access_updated_at = CURRENT_TIMESTAMP");
    $stmt_b->execute(array($bulk_val));

    if ($bulk_val === 0) {
        $success_message = "ChatGPT access has been set to <strong>BLOCKED</strong> for all suppliers.";
    } else {
        $stmt_g = $pdo->prepare("UPDATE tbl_settings SET global_allow_chatgpt = 1 WHERE id = 1");
        $stmt_g->execute();
        $success_message = "ChatGPT access has been set to <strong>ALLOWED</strong> for all suppliers.";
    }
}

$erp_map = erp_get_all_entitlements_map($pdo);
$global_chatgpt_policy = get_global_chatgpt_policy($pdo);

$statement = $pdo->prepare("SELECT * FROM tbl_supplier ORDER BY supplier_id DESC");
$statement->execute();
$result = $statement->fetchAll(PDO::FETCH_ASSOC);

$total_suppliers_cnt = count($result);
$allowed_chatgpt_cnt = 0;
foreach ($result as $r_chk) {
    if (!empty($r_chk['allow_chatgpt_access']) && (int)$r_chk['allow_chatgpt_access'] === 1) {
        $allowed_chatgpt_cnt++;
    }
}
$blocked_chatgpt_cnt = $total_suppliers_cnt - $allowed_chatgpt_cnt;
?>

<section class="content-header">
	<div class="content-header-left">
		<h1>Manage B2B SaaS Suppliers</h1>
	</div>
</section>

<section class="content">
	<div class="row">
		<div class="col-md-12">

			<?php if (!empty($error_message)): ?>
			<div class="callout callout-danger">
				<p><?php echo $error_message; ?></p>
			</div>
			<?php endif; ?>

			<?php if (!empty($success_message)): ?>
			<div class="callout callout-success">
				<p><?php echo $success_message; ?></p>
			</div>
			<?php endif; ?>

			<!-- Global Admin ChatGPT / AI Access Control Bar -->
			<div class="box box-default" style="border-top: 3px solid <?php echo $global_chatgpt_policy ? '#0284c7' : '#dc2626'; ?>; margin-bottom: 15px;">
				<div class="box-body" style="padding: 12px 15px; background: #f8fafc;">
					<div class="row" style="display: flex; align-items: center; flex-wrap: wrap;">
						<div class="col-md-7 col-sm-12" style="margin-bottom: 5px;">
							<div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
								<span style="font-size: 15px; font-weight: 700; color: #0f172a;">
									<i class="fa fa-shield" style="color: <?php echo $global_chatgpt_policy ? '#0284c7' : '#dc2626'; ?>;"></i>
									ChatGPT / OpenAI Access Control (Supplier DB &bull; POS &bull; ERP)
								</span>
								<?php if (!$global_chatgpt_policy): ?>
									<span class="label label-danger" style="background-color: #dc2626; font-size: 11px; padding: 4px 8px;">
										<i class="fa fa-lock"></i> GLOBAL MASTER BLOCK ACTIVE
									</span>
								<?php else: ?>
									<span class="label label-info" style="background-color: #0284c7; font-size: 11px; padding: 4px 8px;">
										<i class="fa fa-sliders"></i> PER-SUPPLIER POLICY ACTIVE
									</span>
								<?php endif; ?>
								<span class="badge" style="background-color: #dc2626;"><?php echo !$global_chatgpt_policy ? $total_suppliers_cnt : $blocked_chatgpt_cnt; ?> Blocked</span>
								<span class="badge" style="background-color: #059669;"><?php echo !$global_chatgpt_policy ? 0 : $allowed_chatgpt_cnt; ?> Allowed</span>
							</div>
							<div style="font-size: 12px; color: #475569; margin-top: 4px;">
								Controls whether ChatGPT bots (<code>GPTBot</code>, <code>ChatGPT-User</code>, <code>OAI-SearchBot</code>) and OpenAI integrations can access Supplier Databases, POS endpoints, and ERP data.
							</div>
						</div>
						<div class="col-md-5 col-sm-12 text-right" style="margin-top: 4px;">
							<form method="post" action="supplier.php" style="display: inline-block; margin-right: 6px;">
								<?php $csrf->echoInputField(); ?>
								<input type="hidden" name="toggle_global_chatgpt" value="1">
								<?php if ($global_chatgpt_policy): ?>
									<input type="hidden" name="global_allow_chatgpt" value="0">
									<button type="submit" class="btn btn-danger btn-xs" style="font-weight: 600; padding: 4px 10px;" onclick="return confirm('Activate Global Master Block? This will immediately block ChatGPT from accessing ALL suppliers\' databases, POS, and ERP.');">
										<i class="fa fa-ban"></i> Master Block All ChatGPT
									</button>
								<?php else: ?>
									<input type="hidden" name="global_allow_chatgpt" value="1">
									<button type="submit" class="btn btn-primary btn-xs" style="font-weight: 600; padding: 4px 10px;">
										<i class="fa fa-unlock"></i> Enable Per-Supplier Policy
									</button>
								<?php endif; ?>
							</form>

							<form method="post" action="supplier.php" style="display: inline-block;">
								<?php $csrf->echoInputField(); ?>
								<button type="submit" name="bulk_chatgpt_action" value="block_all" class="btn btn-default btn-xs" style="font-weight: 600; padding: 4px 8px; color: #b91c1c; border-color: #fca5a5;" onclick="return confirm('Set all suppliers to BLOCK ChatGPT access?');">
									<i class="fa fa-lock"></i> Block All Suppliers
								</button>
								<button type="submit" name="bulk_chatgpt_action" value="allow_all" class="btn btn-default btn-xs" style="font-weight: 600; padding: 4px 8px; color: #047857; border-color: #6ee7b7;" onclick="return confirm('Set all suppliers to ALLOW ChatGPT access?');">
									<i class="fa fa-check"></i> Allow All Suppliers
								</button>
							</form>
						</div>
					</div>
				</div>
			</div>

			<div class="box box-info">
				<div class="box-body table-responsive">
					<table id="example1" class="table table-bordered table-hover table-striped">
						<thead>
							<tr>
								<th>#</th>
								<th>Supplier Company</th>
								<th>Business Contact</th>
								<th>Address</th>
								<th>SaaS Subscription Plan</th>
								<th>Storage & Data Usage</th>
								<th>ChatGPT Access (DB / POS / ERP)</th>
								<th>Platform Commission (%)</th>
								<th>Status</th>
								<th>Joined Date</th>
								<th>Action</th>
							</tr>
						</thead>
						<tbody>
							<?php
							$i=0;
							foreach ($result as $row) {
								$i++;
                                $storage = get_tenant_storage_stats($pdo, $row['supplier_id']);
                                $s_pct = $storage['used_pct'];
                                $s_bar_color = ($s_pct >= 90) ? 'progress-bar-danger' : (($s_pct >= 70) ? 'progress-bar-warning' : 'progress-bar-success');
                                $row_chatgpt_allowed = !empty($row['allow_chatgpt_access']) && (int)$row['allow_chatgpt_access'] === 1;
                                $effective_chatgpt_allowed = $global_chatgpt_policy && $row_chatgpt_allowed;
								?>
								<tr>
									<td><?php echo $i; ?></td>
									<td>
                                        <strong><?php echo htmlspecialchars($row['supplier_name']); ?></strong><br>
                                        <small class="text-muted">Slug: <?php echo htmlspecialchars($row['supplier_slug']); ?></small>
                                    </td>
									<td>
										<strong>Email:</strong> <?php echo htmlspecialchars($row['supplier_email']); ?><br>
										<strong>Phone:</strong> <?php echo htmlspecialchars($row['supplier_phone']); ?>
									</td>
									<td><?php echo htmlspecialchars($row['supplier_address'] ?: 'Not Specified'); ?></td>
									<td>
                                        <?php
                                        $stmt_u_cnt = $pdo->prepare("SELECT COUNT(*) as pos_count FROM tbl_supplier_user WHERE supplier_id = ? AND UPPER(role) IN ('USER', 'POS_USER', 'CASHIER')");
                                        $stmt_u_cnt->execute(array($row['supplier_id']));
                                        $pos_count = (int)$stmt_u_cnt->fetch(PDO::FETCH_ASSOC)['pos_count'];
                                        $max_u = isset($row['max_pos_users']) && (int)$row['max_pos_users'] > 0 ? (int)$row['max_pos_users'] : 3;
                                        ?>
                                        <span class="label label-warning" style="background-color: #F59E0B; font-weight: bold;"><?php echo htmlspecialchars($row['supplier_plan']); ?></span><br>
                                        <small style="color: #64748b; font-weight: 600;"><i class="fa fa-users"></i> <?php echo $pos_count; ?> / <?php echo $max_u; ?> POS Users</small>
                                        <div style="margin-top: 4px;">
                                            <?php if (!empty($erp_map[$row['supplier_id']]['enabled'])): ?>
                                                <span class="label" style="background-color: #0284c7; font-weight: bold;"><i class="fa fa-cubes"></i> ERP: <?php echo htmlspecialchars($erp_map[$row['supplier_id']]['plan']); ?></span>
                                            <?php else: ?>
                                                <span class="label" style="background-color: #94a3b8;"><i class="fa fa-ban"></i> ERP: Inactive</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
									<td style="min-width: 170px;">
                                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 3px;">
                                            <strong style="color: #1e293b; font-size: 13px;"><?php echo htmlspecialchars($storage['formatted_usage']); ?></strong>
                                            <span class="label <?php echo ($s_pct >= 90) ? 'label-danger' : (($s_pct >= 70) ? 'label-warning' : 'label-success'); ?>" style="font-size: 10px; padding: 2px 5px;">
                                                <?php echo $s_pct; ?>%
                                            </span>
                                        </div>
                                        <div class="progress progress-xs" style="margin: 0 0 4px 0; background: #e2e8f0; height: 6px; border-radius: 3px;">
                                            <div class="progress-bar <?php echo $s_bar_color; ?>" style="width: <?php echo max(2, $s_pct); ?>%"></div>
                                        </div>
                                        <div style="font-size: 11px; color: #64748b; line-height: 1.3;">
                                            <div><i class="fa fa-file-image-o text-info"></i> Media: <strong><?php echo $storage['formatted_file_usage']; ?></strong> (<?php echo $storage['total_files']; ?> files)</div>
                                            <div><i class="fa fa-database text-warning"></i> Data: <strong><?php echo $storage['formatted_db_usage']; ?></strong> (<?php echo $storage['db_records_total']; ?> rows)</div>
                                            <div style="color: #059669; font-weight: 600;"><i class="fa fa-hdd-o"></i> Quota: <?php echo htmlspecialchars($storage['formatted_max']); ?></div>
                                        </div>
                                    </td>
									<td style="min-width: 155px;">
                                        <?php if (!$global_chatgpt_policy): ?>
                                            <span class="label label-danger" style="background-color: #991b1b; font-weight: bold;">
                                                <i class="fa fa-lock"></i> Globally Blocked
                                            </span>
                                            <div style="font-size: 10.5px; color: #64748b; margin-top: 3px;">
                                                Supplier Setting: <strong><?php echo $row_chatgpt_allowed ? 'Allowed' : 'Blocked'; ?></strong>
                                            </div>
                                        <?php elseif ($row_chatgpt_allowed): ?>
                                            <span class="label label-success" style="background-color: #059669; font-weight: bold;">
                                                <i class="fa fa-check-circle"></i> Allowed
                                            </span>
                                            <div style="font-size: 10.5px; color: #059669; margin-top: 3px; font-weight: 600;">
                                                DB &bull; POS &bull; ERP Open
                                            </div>
                                        <?php else: ?>
                                            <span class="label label-danger" style="background-color: #dc2626; font-weight: bold;">
                                                <i class="fa fa-ban"></i> Blocked
                                            </span>
                                            <div style="font-size: 10.5px; color: #dc2626; margin-top: 3px; font-weight: 600;">
                                                DB &bull; POS &bull; ERP Protected
                                            </div>
                                        <?php endif; ?>

                                        <form method="post" action="supplier.php" style="margin-top: 5px;">
                                            <?php $csrf->echoInputField(); ?>
                                            <input type="hidden" name="toggle_supplier_chatgpt" value="1">
                                            <input type="hidden" name="supplier_id" value="<?php echo (int)$row['supplier_id']; ?>">
                                            <?php if ($row_chatgpt_allowed): ?>
                                                <input type="hidden" name="allow_chatgpt_access" value="0">
                                                <button type="submit" class="btn btn-danger btn-xs" style="width: 100%; font-weight: 600;" title="Block ChatGPT from accessing this supplier's Database, POS, and ERP">
                                                    <i class="fa fa-ban"></i> Block ChatGPT
                                                </button>
                                            <?php else: ?>
                                                <input type="hidden" name="allow_chatgpt_access" value="1">
                                                <button type="submit" class="btn btn-success btn-xs" style="width: 100%; font-weight: 600;" title="Allow ChatGPT to access this supplier's Database, POS, and ERP">
                                                    <i class="fa fa-check"></i> Allow ChatGPT
                                                </button>
                                            <?php endif; ?>
                                        </form>
                                    </td>
									<td><strong><?php echo htmlspecialchars($row['supplier_commission']); ?>%</strong></td>
									<td>
                                        <?php if($row['supplier_status'] == 'Active'): ?>
                                             <span class="label label-success">Active</span>
                                        <?php elseif($row['supplier_status'] == 'Pending'): ?>
                                            <span class="label label-warning">Pending Review</span>
                                        <?php else: ?>
                                            <span class="label label-danger">Suspended</span>
                                        <?php endif; ?>
                                    </td>
									<td><?php echo date('d M Y', strtotime($row['created_at'])); ?></td>
									<td>										
										<a href="supplier-edit.php?id=<?php echo $row['supplier_id']; ?>" class="btn btn-primary btn-xs">Edit / Plan</a>
                                        <?php if (!empty($erp_map[$row['supplier_id']]['enabled'])): ?>
                                            <a href="<?php echo ERP_LOCAL_URL; ?>/?supplier_id=<?php echo $row['supplier_id']; ?>" target="_blank" class="btn btn-info btn-xs" title="Launch Tenant ERP Dashboard"><i class="fa fa-external-link"></i> ERP</a>
                                        <?php endif; ?>
									</td>
								</tr>
								<?php
							}
							?>							
						</tbody>
					</table>
				</div>
			</div>
		</div>
	</div>
</section>

<?php require_once('footer.php'); ?>
