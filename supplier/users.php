<?php require_once('header.php'); ?>

<?php
// Strict Server-Side Authorization: Admin Only
if (!is_supplier_admin()) {
    header("Location: pos.php");
    exit;
}

$supplier_id = (int)$_SESSION['supplier_user']['supplier_id'];

// -------------------------------------------------------------
// 1. Handle Status Change (Activate / Suspend)
// -------------------------------------------------------------
if (isset($_GET['action']) && isset($_GET['user_id'])) {
    $target_uid = (int)$_GET['user_id'];
    $action = $_GET['action'];

    if ($action === 'toggle_status') {
        // Fetch user and ensure strictly belongs to this tenant and is NOT admin
        $stmt_u = $pdo->prepare("SELECT id, status, role FROM tbl_supplier_user WHERE id = ? AND supplier_id = ?");
        $stmt_u->execute(array($target_uid, $supplier_id));
        $u_row = $stmt_u->fetch(PDO::FETCH_ASSOC);

        if ($u_row && strtoupper(trim($u_row['role'])) !== 'ADMIN') {
            $new_status = ($u_row['status'] === 'Active') ? 'Suspended' : 'Active';
            $stmt_up = $pdo->prepare("UPDATE tbl_supplier_user SET status = ? WHERE id = ? AND supplier_id = ?");
            $stmt_up->execute(array($new_status, $target_uid, $supplier_id));
            $success_message = "POS User status updated to <strong>" . htmlspecialchars($new_status) . "</strong>.";
        }
    } elseif ($action === 'delete') {
        // Delete POS user ensuring strictly belongs to this tenant and is NOT admin
        $stmt_u = $pdo->prepare("SELECT id, full_name, role FROM tbl_supplier_user WHERE id = ? AND supplier_id = ?");
        $stmt_u->execute(array($target_uid, $supplier_id));
        $u_row = $stmt_u->fetch(PDO::FETCH_ASSOC);

        if ($u_row && strtoupper(trim($u_row['role'])) !== 'ADMIN') {
            $stmt_del = $pdo->prepare("DELETE FROM tbl_supplier_user WHERE id = ? AND supplier_id = ?");
            $stmt_del->execute(array($target_uid, $supplier_id));
            $success_message = "POS User <strong>" . htmlspecialchars($u_row['full_name']) . "</strong> was removed.";
        }
    }
}

// -------------------------------------------------------------
// 2. Handle POS User Creation
// -------------------------------------------------------------
if (isset($_POST['form_add_pos_user'])) {
    $full_name = trim(strip_tags($_POST['full_name']));
    $email = trim(strip_tags($_POST['email']));
    $password = trim(strip_tags($_POST['password']));
    $confirm_password = trim(strip_tags($_POST['confirm_password']));

    if (empty($full_name) || empty($email) || empty($password) || empty($confirm_password)) {
        $error_message = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Please provide a valid email address.";
    } elseif ($password !== $confirm_password) {
        $error_message = "Passwords do not match.";
    } elseif (strlen($password) < 6) {
        $error_message = "Password must be at least 6 characters long.";
    } else {
        // Check tenant user quota
        $stats = get_tenant_pos_user_stats($pdo, $supplier_id);
        if ($stats['is_limit_reached']) {
            $error_message = "<strong>POS User Limit Reached:</strong> Your store's " . htmlspecialchars($stats['plan_name']) . 
                             " Plan allows a maximum of " . $stats['max_pos_users'] . " POS Users (" . $stats['current_pos_users'] . "/" . $stats['max_pos_users'] . " used).<br>" .
                             "Please contact the SaaS Administrator to upgrade your plan.";
        } else {
            // Check email uniqueness
            $stmt_chk = $pdo->prepare("SELECT id FROM tbl_supplier_user WHERE email = ?");
            $stmt_chk->execute(array($email));
            if ($stmt_chk->rowCount() > 0) {
                $error_message = "Email address <strong>" . htmlspecialchars($email) . "</strong> is already in use.";
            } else {
                try {
                    $pdo->beginTransaction();

                    // Lock and re-verify count inside transaction
                    $stmt_cnt = $pdo->prepare("SELECT COUNT(*) as total FROM tbl_supplier_user WHERE supplier_id = ? AND UPPER(role) IN ('USER', 'POS_USER', 'CASHIER')");
                    $stmt_cnt->execute(array($supplier_id));
                    $cur = (int)$stmt_cnt->fetch(PDO::FETCH_ASSOC)['total'];

                    if ($cur >= $stats['max_pos_users']) {
                        $pdo->rollBack();
                        $error_message = "POS user limit reached. Cannot add user.";
                    } else {
                        $hashed = hash_supplier_password($password);
                        $stmt_ins = $pdo->prepare("INSERT INTO tbl_supplier_user (supplier_id, full_name, email, password, role, status) VALUES (?, ?, ?, ?, 'USER', 'Active')");
                        $stmt_ins->execute(array($supplier_id, $full_name, $email, $hashed));
                        $pdo->commit();

                        $success_message = "POS User <strong>" . htmlspecialchars($full_name) . "</strong> created successfully!";
                    }
                } catch (Exception $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $error_message = "Error creating user: " . $e->getMessage();
                }
            }
        }
    }
}

// -------------------------------------------------------------
// 3. Load Current Tenant User Quota Stats & User List
// -------------------------------------------------------------
$tenant_stats = get_tenant_pos_user_stats($pdo, $supplier_id);

$stmt_users = $pdo->prepare("SELECT * FROM tbl_supplier_user WHERE supplier_id = ? ORDER BY id ASC");
$stmt_users->execute(array($supplier_id));
$all_users = $stmt_users->fetchAll(PDO::FETCH_ASSOC);

$pos_users = [];
$admin_users = [];
foreach ($all_users as $u) {
    if (strtoupper(trim($u['role'])) === 'ADMIN') {
        $admin_users[] = $u;
    } else {
        $pos_users[] = $u;
    }
}
?>

<section class="content-header">
	<div class="content-header-left">
		<h1><i class="fa fa-users" style="color: #0284c7;"></i> Manage Store POS Users</h1>
	</div>
	<div class="content-header-right">
        <?php if (!$tenant_stats['is_limit_reached']): ?>
		    <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addPosUserModal" style="background-color: #0284c7; border-color: #0284c7;">
                <i class="fa fa-plus"></i> Add New POS User
            </button>
        <?php else: ?>
            <button type="button" class="btn btn-warning disabled" title="Plan limit reached">
                <i class="fa fa-ban"></i> User Limit Reached (<?php echo $tenant_stats['current_pos_users']; ?>/<?php echo $tenant_stats['max_pos_users']; ?>)
            </button>
        <?php endif; ?>
	</div>
</section>

<section class="content">

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

    <!-- Plan Quota Summary Card -->
    <div class="row">
        <div class="col-md-12">
            <div class="box box-solid" style="border-radius: 8px; border-left: 5px solid #0284c7; box-shadow: 0 1px 3px rgba(0,0,0,0.08);">
                <div class="box-body" style="padding: 20px;">
                    <div class="row">
                        <div class="col-md-3 col-sm-6 border-right">
                            <span class="text-muted" style="font-size: 11px; text-transform: uppercase; font-weight: 700;">SaaS Subscription Plan</span>
                            <h3 style="margin: 4px 0 0 0; font-weight: 800; color: #1e293b;">
                                <?php echo htmlspecialchars($tenant_stats['plan_name']); ?> Plan
                            </h3>
                        </div>
                        <div class="col-md-3 col-sm-6 border-right">
                            <span class="text-muted" style="font-size: 11px; text-transform: uppercase; font-weight: 700;">POS User Allowance</span>
                            <h3 style="margin: 4px 0 0 0; font-weight: 800; color: #0284c7;">
                                <?php echo $tenant_stats['max_pos_users']; ?> Maximum Users
                            </h3>
                        </div>
                        <div class="col-md-3 col-sm-6 border-right">
                            <span class="text-muted" style="font-size: 11px; text-transform: uppercase; font-weight: 700;">Current Active Users</span>
                            <h3 style="margin: 4px 0 0 0; font-weight: 800; color: <?php echo $tenant_stats['is_limit_reached'] ? '#ef4444' : '#10b981'; ?>;">
                                <?php echo $tenant_stats['current_pos_users']; ?> / <?php echo $tenant_stats['max_pos_users']; ?> Used
                            </h3>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <span class="text-muted" style="font-size: 11px; text-transform: uppercase; font-weight: 700;">Available User Slots</span>
                            <h3 style="margin: 4px 0 0 0; font-weight: 800; color: <?php echo ($tenant_stats['remaining_slots'] > 0) ? '#10b981' : '#64748b'; ?>;">
                                <?php echo $tenant_stats['remaining_slots']; ?> Remaining
                            </h3>
                        </div>
                    </div>

                    <div style="margin-top: 15px;">
                        <?php 
                        $pct = ($tenant_stats['max_pos_users'] > 0) ? min(100, round(($tenant_stats['current_pos_users'] / $tenant_stats['max_pos_users']) * 100)) : 100;
                        $bar_color = ($pct >= 100) ? 'progress-bar-danger' : (($pct >= 70) ? 'progress-bar-warning' : 'progress-bar-success');
                        ?>
                        <div class="progress progress-xs" style="margin: 0; background: #e2e8f0; height: 8px; border-radius: 4px;">
                            <div class="progress-bar <?php echo $bar_color; ?>" style="width: <?php echo $pct; ?>%"></div>
                        </div>
                    </div>

                    <?php if ($tenant_stats['is_limit_reached']): ?>
                        <div style="margin-top: 12px; font-size: 12px; color: #b91c1c;">
                            <i class="fa fa-info-circle"></i> <strong>User Allocation Full:</strong> Your store has utilized all <?php echo $tenant_stats['max_pos_users']; ?> POS user licenses. To add additional operators, please contact the SaaS Administrator.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- POS Users Table -->
    <div class="row">
        <div class="col-md-12">
            <div class="box box-info" style="border-radius: 8px;">
                <div class="box-header with-border">
                    <h3 class="box-title" style="font-weight: 700; color: #1e293b;">
                        <i class="fa fa-desktop text-primary"></i> POS Cashiers & Operators (<?php echo count($pos_users); ?>)
                    </h3>
                </div>
                <div class="box-body table-responsive">
                    <table class="table table-bordered table-hover table-striped">
                        <thead>
                            <tr style="background: #f8fafc;">
                                <th width="40">#</th>
                                <th>Full Name</th>
                                <th>Email / Login Identifier</th>
                                <th>Role</th>
                                <th>Access Scope</th>
                                <th>Status</th>
                                <th width="150" class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($pos_users)): ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted" style="padding: 30px;">
                                        <i class="fa fa-info-circle" style="font-size: 24px; display: block; margin-bottom: 8px;"></i>
                                        No POS Users registered yet. Click <strong>+ Add New POS User</strong> to create your first cashier account.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php 
                                $idx = 0;
                                foreach ($pos_users as $row): 
                                    $idx++;
                                ?>
                                <tr>
                                    <td><?php echo $idx; ?></td>
                                    <td><strong><?php echo htmlspecialchars($row['full_name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($row['email']); ?></td>
                                    <td>
                                        <span class="label label-info" style="background-color: #0284c7;">POS Operator</span>
                                    </td>
                                    <td>
                                        <span class="label label-default" style="background: #e2e8f0; color: #334155;">POS Register Terminal Only</span>
                                    </td>
                                    <td>
                                        <?php if ($row['status'] === 'Active'): ?>
                                            <span class="label label-success"><i class="fa fa-check"></i> Active</span>
                                        <?php else: ?>
                                            <span class="label label-danger"><i class="fa fa-ban"></i> Suspended</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <a href="?action=toggle_status&user_id=<?php echo $row['id']; ?>" class="btn btn-xs <?php echo ($row['status'] === 'Active') ? 'btn-warning' : 'btn-success'; ?>" title="Change account status">
                                            <?php if ($row['status'] === 'Active'): ?>
                                                <i class="fa fa-pause"></i> Suspend
                                            <?php else: ?>
                                                <i class="fa fa-play"></i> Activate
                                            <?php endif; ?>
                                        </a>
                                        <a href="?action=delete&user_id=<?php echo $row['id']; ?>" class="btn btn-xs btn-danger" onclick="return confirm('Are you sure you want to remove POS User <?php echo addslashes($row['full_name']); ?>?');" title="Remove POS user">
                                            <i class="fa fa-trash"></i> Remove
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Store Administrator Info Box -->
    <div class="row">
        <div class="col-md-12">
            <div class="box box-default" style="border-radius: 8px;">
                <div class="box-header with-border">
                    <h3 class="box-title" style="font-weight: 700; color: #1e293b; font-size: 14px;">
                        <i class="fa fa-shield text-info"></i> Store Administrator Account (Exempt from POS Quota)
                    </h3>
                </div>
                <div class="box-body table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr style="background: #f8fafc;">
                                <th>Administrator Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Access Scope</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($admin_users as $adm): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($adm['full_name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($adm['email']); ?></td>
                                <td><span class="label label-primary" style="background-color: #0284c7;">Supplier Admin</span></td>
                                <td><span class="label label-success">Full Portal & Administration</span></td>
                                <td><span class="label label-success">Active</span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

</section>

<!-- ========================================================= -->
<!-- ADD POS USER MODAL -->
<!-- ========================================================= -->
<div id="addPosUserModal" class="modal fade" role="dialog">
    <div class="modal-dialog">
        <div class="modal-content" style="border-radius: 8px; overflow: hidden;">
            <div class="modal-header" style="background-color: #0284c7; color: #fff;">
                <button type="button" class="close" data-dismiss="modal" style="color: #fff;">&times;</button>
                <h4 class="modal-title" style="font-weight: bold;"><i class="fa fa-user-plus"></i> Add New POS User</h4>
            </div>
            <form action="" method="post" class="form-horizontal">
                <?php $csrf->echoInputField(); ?>
                <div class="modal-body" style="padding: 20px;">
                    <div class="callout callout-info" style="margin-bottom: 20px; font-size: 13px;">
                        <i class="fa fa-info-circle"></i> This will create a POS Cashier account bound to <strong><?php echo htmlspecialchars($tenant_stats['supplier_name']); ?></strong>. The account will have POS-only access.
                    </div>

                    <div class="form-group">
                        <label class="col-sm-4 control-label">Full Name *</label>
                        <div class="col-sm-8">
                            <input type="text" name="full_name" class="form-control" placeholder="e.g. Maria Santos" required autocomplete="off">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="col-sm-4 control-label">Email Address *</label>
                        <div class="col-sm-8">
                            <input type="email" name="email" class="form-control" placeholder="maria@store.com" required autocomplete="off">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="col-sm-4 control-label">Password *</label>
                        <div class="col-sm-8">
                            <input type="password" name="password" class="form-control" placeholder="Min. 6 characters" required autocomplete="off">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="col-sm-4 control-label">Confirm Password *</label>
                        <div class="col-sm-8">
                            <input type="password" name="confirm_password" class="form-control" placeholder="Re-enter password" required autocomplete="off">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                    <button type="submit" name="form_add_pos_user" class="btn btn-primary" style="background-color: #0284c7; border-color: #0284c7;">
                        <i class="fa fa-check"></i> Create POS User
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once('footer.php'); ?>
