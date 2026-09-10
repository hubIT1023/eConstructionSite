<?php require_once('header.php'); ?>

<?php
// Strict Server-Side Authorization: Admin, Manager, and Supervisor Only
$current_user_role = isset($_SESSION['supplier_user']['role']) ? normalize_supplier_role($_SESSION['supplier_user']['role']) : '';
if (!can_register_staff($current_user_role)) {
    header("Location: pos.php");
    exit;
}

$supplier_id = (int)$_SESSION['supplier_user']['supplier_id'];
$assignable_roles = get_assignable_roles($current_user_role);
ensure_supplier_user_schema($pdo);
backfill_supplier_employee_ids($pdo);

// Helper for initials
if (!function_exists('get_staff_initials')) {
    function get_staff_initials($name) {
        $parts = explode(' ', trim($name));
        $initials = '';
        foreach ($parts as $p) {
            if (!empty($p)) {
                $initials .= strtoupper(substr($p, 0, 1));
                if (strlen($initials) >= 2) break;
            }
        }
        return $initials ?: 'U';
    }
}

// -------------------------------------------------------------
// 1. Handle Status Change (Activate / Suspend) & POS Access Toggle
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
            $success_message = "Staff User status updated to <strong>" . htmlspecialchars($new_status) . "</strong>.";
        }
    } elseif ($action === 'toggle_pos_access') {
        // Toggle POS access for staff
        $stmt_u = $pdo->prepare("SELECT id, full_name, role, pos_access FROM tbl_supplier_user WHERE id = ? AND supplier_id = ?");
        $stmt_u->execute(array($target_uid, $supplier_id));
        $u_row = $stmt_u->fetch(PDO::FETCH_ASSOC);

        if ($u_row && strtoupper(trim($u_row['role'])) !== 'ADMIN') {
            $cur_pos = isset($u_row['pos_access']) ? (int)$u_row['pos_access'] : 1;
            $new_pos = ($cur_pos === 1) ? 0 : 1;
            $stmt_up = $pdo->prepare("UPDATE tbl_supplier_user SET pos_access = ? WHERE id = ? AND supplier_id = ?");
            $stmt_up->execute(array($new_pos, $target_uid, $supplier_id));
            $success_message = "POS Access for <strong>" . htmlspecialchars($u_row['full_name']) . "</strong> updated to <strong>" . ($new_pos === 1 ? 'Authorized' : 'Disabled') . "</strong>.";
        }
    } elseif ($action === 'delete') {
        // Delete POS user ensuring strictly belongs to this tenant and is NOT admin
        $stmt_u = $pdo->prepare("SELECT id, full_name, role FROM tbl_supplier_user WHERE id = ? AND supplier_id = ?");
        $stmt_u->execute(array($target_uid, $supplier_id));
        $u_row = $stmt_u->fetch(PDO::FETCH_ASSOC);

        if ($u_row && strtoupper(trim($u_row['role'])) !== 'ADMIN') {
            $stmt_del = $pdo->prepare("DELETE FROM tbl_supplier_user WHERE id = ? AND supplier_id = ?");
            $stmt_del->execute(array($target_uid, $supplier_id));
            $success_message = "Staff User <strong>" . htmlspecialchars($u_row['full_name']) . "</strong> was removed.";
        }
    }
}

// -------------------------------------------------------------
// 2. Handle Staff User Creation
// -------------------------------------------------------------
if (isset($_POST['form_add_pos_user'])) {
    $first_name = isset($_POST['first_name']) ? trim(strip_tags($_POST['first_name'])) : '';
    $middle_name = isset($_POST['middle_name']) ? trim(strip_tags($_POST['middle_name'])) : '';
    $last_name = isset($_POST['last_name']) ? trim(strip_tags($_POST['last_name'])) : '';
    $phone = isset($_POST['phone']) ? trim(strip_tags($_POST['phone'])) : '';
    $email = trim(strip_tags($_POST['email']));
    $password = trim(strip_tags($_POST['password']));
    $confirm_password = trim(strip_tags($_POST['confirm_password']));
    $role_input = isset($_POST['role']) ? trim($_POST['role']) : 'CASHIER';
    $role = normalize_supplier_role($role_input);
    $date_started = !empty($_POST['date_started']) ? trim(strip_tags($_POST['date_started'])) : date('Y-m-d');
    if (empty($date_started) || !strtotime($date_started)) {
        $date_started = date('Y-m-d');
    }
    $pos_access = isset($_POST['pos_access']) ? 1 : 0;

    // Compose full name
    $name_parts = array_filter([$first_name, $middle_name, $last_name]);
    $full_name = implode(' ', $name_parts);

    if (empty($first_name) || empty($last_name) || empty($email) || empty($password) || empty($confirm_password)) {
        $error_message = "First Name, Last Name, Start Date, Email, and Passwords are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Please provide a valid email address.";
    } elseif (!can_assign_role($current_user_role, $role)) {
        $error_message = "Permission Denied: Your role is not authorized to register an account with the role " . get_role_display_name($role) . ".";
    } elseif ($password !== $confirm_password) {
        $error_message = "Passwords do not match.";
    } elseif (strlen($password) < 6) {
        $error_message = "Password must be at least 6 characters long.";
    } else {
        // Check tenant user quota
        $stats = get_tenant_pos_user_stats($pdo, $supplier_id);
        if ($stats['is_limit_reached']) {
            $error_message = "<strong>Staff User Limit Reached:</strong> Your store's " . htmlspecialchars($stats['plan_name']) . 
                             " Plan allows a maximum of " . $stats['max_pos_users'] . " Staff Users (" . $stats['current_pos_users'] . "/" . $stats['max_pos_users'] . " used).<br>" .
                             "Please contact the SaaS Administrator to upgrade your plan.";
        } else {
            // Check email uniqueness
            $stmt_chk = $pdo->prepare("SELECT id FROM tbl_supplier_user WHERE LOWER(email) = LOWER(?)");
            $stmt_chk->execute(array($email));
            if ($stmt_chk->rowCount() > 0) {
                $error_message = "Email address <strong>" . htmlspecialchars($email) . "</strong> is already in use.";
            } else {
                try {
                    $pdo->beginTransaction();

                    // Lock and re-verify count inside transaction
                    $stmt_cnt = $pdo->prepare("SELECT COUNT(*) as total FROM tbl_supplier_user WHERE supplier_id = ? AND UPPER(role) NOT IN ('ADMIN', 'SUPPLIER_ADMIN', 'SUPERADMIN')");
                    $stmt_cnt->execute(array($supplier_id));
                    $cur = (int)$stmt_cnt->fetch(PDO::FETCH_ASSOC)['total'];

                    if ($cur >= $stats['max_pos_users']) {
                        $pdo->rollBack();
                        $error_message = "Staff user limit reached. Cannot add user.";
                    } else {
                        $employee_id = generate_supplier_employee_id($pdo, $supplier_id, $date_started);
                        $hashed = hash_supplier_password($password);

                        $stmt_ins = $pdo->prepare("INSERT INTO tbl_supplier_user (
                            supplier_id, 
                            first_name,
                            middle_name,
                            last_name,
                            full_name, 
                            email, 
                            phone,
                            password, 
                            role, 
                            status, 
                            employee_id, 
                            date_started,
                            pos_access
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Active', ?, ?, ?)");
                        $stmt_ins->execute(array(
                            $supplier_id, 
                            $first_name,
                            $middle_name,
                            $last_name,
                            $full_name, 
                            $email, 
                            $phone,
                            $hashed, 
                            $role, 
                            $employee_id, 
                            $date_started,
                            $pos_access
                        ));
                        $pdo->commit();

                        $success_message = "Staff Member <strong>" . htmlspecialchars($full_name) . "</strong> (" . get_role_display_name($role) . ") created successfully! Employee ID: <strong>" . htmlspecialchars($employee_id) . "</strong>";
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
// 3. Handle Staff User Role Edit
// -------------------------------------------------------------
if (isset($_POST['form_edit_pos_user_role'])) {
    $edit_user_id = (int)$_POST['edit_user_id'];
    $new_role_input = isset($_POST['edit_role']) ? trim($_POST['edit_role']) : 'CASHIER';
    $new_role = normalize_supplier_role($new_role_input);

    if (can_assign_role($current_user_role, $new_role)) {
        $stmt_chk = $pdo->prepare("SELECT id, full_name, role FROM tbl_supplier_user WHERE id = ? AND supplier_id = ?");
        $stmt_chk->execute(array($edit_user_id, $supplier_id));
        $u_row = $stmt_chk->fetch(PDO::FETCH_ASSOC);

        if ($u_row && strtoupper(trim($u_row['role'])) !== 'ADMIN') {
            $stmt_up = $pdo->prepare("UPDATE tbl_supplier_user SET role = ? WHERE id = ? AND supplier_id = ?");
            $stmt_up->execute(array($new_role, $edit_user_id, $supplier_id));
            $success_message = "Role for <strong>" . htmlspecialchars($u_row['full_name']) . "</strong> updated to <strong>" . get_role_display_name($new_role) . "</strong>.";
        } else {
            $error_message = "Unauthorized or cannot modify administrator role.";
        }
    } else {
        $error_message = "Invalid or unauthorized role selected.";
    }
}

// -------------------------------------------------------------
// 4. Load Current Tenant User Quota Stats, Storage Stats & User List
// -------------------------------------------------------------
$tenant_stats = get_tenant_pos_user_stats($pdo, $supplier_id);
$storage_stats = get_tenant_storage_stats($pdo, $supplier_id);

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

<style>
/* =========================================================
   SIMPLE & ELEGANT STAFF MANAGEMENT STYLES
   ========================================================= */
.users-page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
    margin-bottom: 20px;
}
.users-page-title {
    margin: 0;
    font-size: 22px;
    font-weight: 800;
    color: #0f172a;
    letter-spacing: -0.3px;
    display: flex;
    align-items: center;
    gap: 10px;
}
.users-page-subtitle {
    margin: 4px 0 0 0;
    font-size: 13px;
    color: #64748b;
    font-weight: 500;
}
.btn-add-staff {
    background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%);
    color: #ffffff !important;
    border: none;
    font-weight: 700;
    font-size: 14px;
    padding: 10px 20px;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(2, 132, 199, 0.25);
    transition: all 0.2s ease-in-out;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}
.btn-add-staff:hover {
    background: linear-gradient(135deg, #0369a1 0%, #075985 100%);
    box-shadow: 0 6px 16px rgba(2, 132, 199, 0.35);
    transform: translateY(-1px);
}

/* Overview Metric Cards */
.metric-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 2px 8px rgba(15, 23, 42, 0.04);
    transition: all 0.2s ease;
    height: 100%;
    margin-bottom: 20px;
}
.metric-card:hover {
    border-color: #cbd5e1;
    box-shadow: 0 4px 16px rgba(15, 23, 42, 0.06);
}
.metric-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 14px;
}
.metric-icon-wrap {
    width: 42px;
    height: 42px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
}
.metric-icon-blue { background: #e0f2fe; color: #0284c7; }
.metric-icon-green { background: #ecfdf5; color: #059669; }

.metric-title {
    font-size: 11.5px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #64748b;
    margin-bottom: 3px;
}
.metric-value {
    font-size: 22px;
    font-weight: 800;
    color: #0f172a;
    line-height: 1.2;
}
.metric-sub {
    font-size: 13px;
    font-weight: 600;
    color: #64748b;
}

/* Elegant Table Styles */
.staff-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(15, 23, 42, 0.04);
    margin-bottom: 25px;
    overflow: hidden;
}
.staff-card-header {
    padding: 16px 20px;
    border-bottom: 1px solid #f1f5f9;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
}
.staff-table {
    margin-bottom: 0;
    width: 100%;
}
.staff-table > thead > tr > th {
    background: #f8fafc;
    color: #475569;
    font-size: 11.5px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 12px 16px;
    border-bottom: 1px solid #e2e8f0;
    border-top: none;
}
.staff-table > tbody > tr > td {
    padding: 14px 16px;
    vertical-align: middle;
    border-top: 1px solid #f1f5f9;
    font-size: 13px;
    color: #334155;
}
.staff-table > tbody > tr:hover {
    background-color: #f8fafc;
}

/* Avatar Circle */
.user-avatar-badge {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: linear-gradient(135deg, #e0f2fe 0%, #bae6fd 100%);
    color: #0369a1;
    font-weight: 800;
    font-size: 13px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    border: 1.5px solid #ffffff;
    box-shadow: 0 1px 3px rgba(0,0,0,0.08);
}
.user-info-flex {
    display: flex;
    align-items: center;
    gap: 12px;
}

/* Elegant Pill Badges */
.badge-role {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: 11px;
    font-weight: 700;
    padding: 4px 10px;
    border-radius: 20px;
    text-transform: capitalize;
}
.role-manager { background: #f3e8ff; color: #7e22ce; border: 1px solid #e9d5ff; }
.role-supervisor { background: #fef3c7; color: #b45309; border: 1px solid #fde68a; }
.role-cashier { background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; }
.role-order_processing { background: #ecfeff; color: #0e7490; border: 1px solid #a5f3fc; }
.role-operator { background: #f1f5f9; color: #334155; border: 1px solid #cbd5e1; }
.role-encoder { background: #fff7ed; color: #c2410c; border: 1px solid #ffedd5; }
.role-admin { background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; }

/* Status & POS Pills */
.status-pill {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: 11px;
    font-weight: 700;
    padding: 3px 9px;
    border-radius: 20px;
}
.status-active { background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; }
.status-suspended { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
.pos-granted { background: #ecfdf5; color: #059669; border: 1px solid #a7f3d0; }
.pos-disabled { background: #f1f5f9; color: #64748b; border: 1px solid #cbd5e1; }

.emp-id-pill {
    font-family: SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", monospace;
    font-size: 11px;
    font-weight: 700;
    background: #f1f5f9;
    color: #0f172a;
    padding: 3px 8px;
    border-radius: 6px;
    border: 1px solid #e2e8f0;
}

/* Action Buttons */
.action-btn-group {
    display: inline-flex;
    gap: 4px;
    align-items: center;
}
.btn-action-light {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    color: #475569;
    border-radius: 6px;
    padding: 4px 8px;
    font-size: 11.5px;
    font-weight: 600;
    transition: all 0.15s ease;
}
.btn-action-light:hover {
    background: #0284c7;
    border-color: #0284c7;
    color: #ffffff;
}
.btn-action-delete {
    background: #fff;
    border: 1px solid #fee2e2;
    color: #ef4444;
    border-radius: 6px;
    padding: 4px 8px;
    font-size: 11.5px;
    transition: all 0.15s ease;
}
.btn-action-delete:hover {
    background: #ef4444;
    border-color: #ef4444;
    color: #ffffff;
}
</style>

<section class="content-header" style="padding-top: 15px;">
    <div class="users-page-header">
        <div>
            <h1 class="users-page-title">
                <i class="fa fa-users" style="color: #0284c7;"></i> Store Staff & POS Team
            </h1>
            <p class="users-page-subtitle">Manage employee accounts, role authorizations, POS terminal permissions, and SaaS quotas.</p>
        </div>
        <div>
            <?php if (!$tenant_stats['is_limit_reached']): ?>
                <button type="button" class="btn-add-staff" data-toggle="modal" data-target="#addPosUserModal">
                    <i class="fa fa-plus-circle"></i> Add Staff Member
                </button>
            <?php else: ?>
                <button type="button" class="btn btn-warning disabled" style="font-weight: 700; border-radius: 8px; padding: 10px 18px;" title="Plan limit reached">
                    <i class="fa fa-ban"></i> Staff Limit Reached (<?php echo $tenant_stats['current_pos_users']; ?>/<?php echo $tenant_stats['max_pos_users']; ?>)
                </button>
            <?php endif; ?>
        </div>
    </div>
</section>

<section class="content" style="padding-top: 5px;">

    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger alert-dismissible" style="border-radius: 8px; font-weight: 600; margin-bottom: 20px;">
            <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
            <i class="fa fa-exclamation-circle" style="margin-right: 6px;"></i> <?php echo $error_message; ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($success_message)): ?>
        <div class="alert alert-success alert-dismissible" style="border-radius: 8px; font-weight: 600; margin-bottom: 20px; background-color: #ecfdf5; border-color: #a7f3d0; color: #065f46;">
            <button type="button" class="close" data-dismiss="alert" aria-hidden="true" style="color: #065f46;">&times;</button>
            <i class="fa fa-check-circle" style="margin-right: 6px;"></i> <?php echo $success_message; ?>
        </div>
    <?php endif; ?>

    <!-- Overview Metric Cards -->
    <div class="row">
        <!-- 1. POS User License Quota Card -->
        <div class="col-md-6">
            <div class="metric-card">
                <div class="metric-header">
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <div class="metric-icon-wrap metric-icon-blue">
                            <i class="fa fa-id-badge"></i>
                        </div>
                        <div>
                            <div class="metric-title"><?php echo htmlspecialchars($tenant_stats['plan_name']); ?> Subscription Plan</div>
                            <div class="metric-value">
                                <?php echo $tenant_stats['current_pos_users']; ?> <span class="metric-sub">/ <?php echo $tenant_stats['max_pos_users']; ?> Staff Users</span>
                            </div>
                        </div>
                    </div>
                    <span class="status-pill <?php echo $tenant_stats['is_limit_reached'] ? 'status-suspended' : 'status-active'; ?>">
                        ● <?php echo $tenant_stats['is_limit_reached'] ? 'Quota Full' : 'Available (' . $tenant_stats['remaining_slots'] . ' left)'; ?>
                    </span>
                </div>

                <?php 
                $pct_u = ($tenant_stats['max_pos_users'] > 0) ? min(100, round(($tenant_stats['current_pos_users'] / $tenant_stats['max_pos_users']) * 100)) : 100;
                $bar_u_color = ($pct_u >= 100) ? '#ef4444' : (($pct_u >= 70) ? '#f59e0b' : '#0284c7');
                ?>
                <div style="background: #f1f5f9; height: 7px; border-radius: 4px; overflow: hidden; margin: 15px 0 6px 0;">
                    <div style="background: <?php echo $bar_u_color; ?>; width: <?php echo $pct_u; ?>%; height: 100%; border-radius: 4px; transition: width 0.4s ease;"></div>
                </div>
                <div style="display: flex; justify-content: space-between; font-size: 11.5px; color: #64748b; font-weight: 500;">
                    <span><?php echo $pct_u; ?>% staff user capacity utilized</span>
                    <span>Remaining: <strong><?php echo $tenant_stats['remaining_slots']; ?></strong> slots</span>
                </div>
            </div>
        </div>

        <!-- 2. Store Cloud Storage & Data Used Card -->
        <div class="col-md-6">
            <div class="metric-card">
                <div class="metric-header">
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <div class="metric-icon-wrap metric-icon-green">
                            <i class="fa fa-database"></i>
                        </div>
                        <div>
                            <div class="metric-title">Cloud Storage & Data Volume</div>
                            <div class="metric-value">
                                <?php echo htmlspecialchars($storage_stats['formatted_usage']); ?> <span class="metric-sub">/ <?php echo htmlspecialchars($storage_stats['formatted_max']); ?></span>
                            </div>
                        </div>
                    </div>
                    <span class="status-pill status-active">
                        ● <?php echo $storage_stats['used_pct']; ?>% Used
                    </span>
                </div>

                <?php 
                $pct_s = $storage_stats['used_pct'];
                $bar_s_color = ($pct_s >= 90) ? '#ef4444' : (($pct_s >= 70) ? '#f59e0b' : '#10b981');
                ?>
                <div style="background: #f1f5f9; height: 7px; border-radius: 4px; overflow: hidden; margin: 15px 0 6px 0;">
                    <div style="background: <?php echo $bar_s_color; ?>; width: <?php echo max(1, $pct_s); ?>%; height: 100%; border-radius: 4px; transition: width 0.4s ease;"></div>
                </div>
                <div style="display: flex; justify-content: space-between; font-size: 11.5px; color: #64748b; font-weight: 500;">
                    <span><i class="fa fa-file-image-o text-info"></i> <?php echo $storage_stats['total_files']; ?> Media Files | <i class="fa fa-table text-warning"></i> <?php echo $storage_stats['db_records_total']; ?> DB Rows</span>
                    <span>Free: <strong><?php echo $storage_stats['formatted_remaining']; ?></strong></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Registered Store Staff Table Card -->
    <div class="staff-card">
        <div class="staff-card-header">
            <div style="display: flex; align-items: center; gap: 8px;">
                <h3 style="margin: 0; font-size: 16px; font-weight: 800; color: #1e293b;">
                    <i class="fa fa-id-card text-primary" style="margin-right: 4px;"></i> Store Staff Directory
                </h3>
                <span class="label label-default" style="background: #f1f5f9; color: #475569; font-size: 11px; font-weight: 700; border-radius: 12px; padding: 3px 8px;">
                    <?php echo count($pos_users); ?> Staff Members
                </span>
            </div>
            <div>
                <input type="text" id="staffSearchInput" class="form-control input-sm" style="width: 220px; border-radius: 6px;" placeholder="Search staff by name, email, ID..." onkeyup="filterStaffTable()">
            </div>
        </div>

        <div class="table-responsive">
            <table class="staff-table" id="staffDirectoryTable">
                <thead>
                    <tr>
                        <th style="width: 40px;">#</th>
                        <th>Staff Member</th>
                        <th style="width: 140px;">Employee ID</th>
                        <th style="width: 140px;">Store Role</th>
                        <th style="width: 110px;">POS Terminal</th>
                        <th style="width: 90px;">Status</th>
                        <th style="width: 200px; text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($pos_users)): ?>
                        <tr>
                            <td colspan="7" class="text-center" style="padding: 45px 20px;">
                                <div style="font-size: 40px; color: #cbd5e1; margin-bottom: 10px;"><i class="fa fa-users"></i></div>
                                <h4 style="font-weight: 800; color: #334155; margin-top: 0;">No Staff Members Registered Yet</h4>
                                <p style="color: #64748b; font-size: 13.5px; max-width: 450px; margin: 0 auto 15px auto;">
                                    Add your cashiers, supervisors, operators, and order processing team to grant them role-based access to the POS.
                                </p>
                                <?php if (!$tenant_stats['is_limit_reached']): ?>
                                    <button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#addPosUserModal" style="background-color: #0284c7; border-color: #0284c7; font-weight: 700;">
                                        <i class="fa fa-plus"></i> Add First Staff Member
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php 
                        $idx = 0;
                        foreach ($pos_users as $row): 
                            $idx++;
                            $r_norm = normalize_supplier_role($row['role']);
                            $emp_id = !empty($row['employee_id']) ? $row['employee_id'] : '-';
                            $start_date = !empty($row['date_started']) ? $row['date_started'] : '-';
                            $pos_acc = isset($row['pos_access']) ? (int)$row['pos_access'] : 1;
                            $initials = get_staff_initials($row['full_name']);
                        ?>
                        <tr class="staff-row">
                            <td style="color: #94a3b8; font-weight: 600;"><?php echo $idx; ?></td>
                            <td>
                                <div class="user-info-flex">
                                    <div class="user-avatar-badge"><?php echo htmlspecialchars($initials); ?></div>
                                    <div>
                                        <div style="font-weight: 800; color: #0f172a; font-size: 14px;"><?php echo htmlspecialchars($row['full_name']); ?></div>
                                        <div style="font-size: 11.5px; color: #64748b; display: flex; align-items: center; gap: 8px;">
                                            <span><i class="fa fa-envelope-o"></i> <?php echo htmlspecialchars($row['email']); ?></span>
                                            <?php if (!empty($row['phone'])): ?>
                                                <span>• <i class="fa fa-phone"></i> <?php echo htmlspecialchars($row['phone']); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="emp-id-pill"><?php echo htmlspecialchars($emp_id); ?></span>
                            </td>
                            <td>
                                <?php if ($r_norm === 'MANAGER'): ?>
                                    <span class="badge-role role-manager"><i class="fa fa-briefcase"></i> Manager</span>
                                <?php elseif ($r_norm === 'SUPERVISOR'): ?>
                                    <span class="badge-role role-supervisor"><i class="fa fa-shield"></i> Supervisor</span>
                                <?php elseif ($r_norm === 'ENCODER'): ?>
                                    <span class="badge-role role-encoder"><i class="fa fa-keyboard-o"></i> Encoder</span>
                                <?php elseif ($r_norm === 'ORDER_PROCESSING'): ?>
                                    <span class="badge-role role-order_processing"><i class="fa fa-cubes"></i> Order Processing</span>
                                <?php elseif ($r_norm === 'OPERATOR'): ?>
                                    <span class="badge-role role-operator"><i class="fa fa-desktop"></i> Operator</span>
                                <?php else: ?>
                                    <span class="badge-role role-cashier"><i class="fa fa-calculator"></i> Cashier</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($pos_acc === 1): ?>
                                    <span class="status-pill pos-granted"><i class="fa fa-check-circle"></i> Allowed</span>
                                <?php else: ?>
                                    <span class="status-pill pos-disabled"><i class="fa fa-ban"></i> Disabled</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($row['status'] === 'Active'): ?>
                                    <span class="status-pill status-active">● Active</span>
                                <?php else: ?>
                                    <span class="status-pill status-suspended">● Suspended</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <div class="action-btn-group">
                                    <button type="button" class="btn-action-light" onclick="openEditRoleModal(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars(addslashes($row['full_name'])); ?>', '<?php echo htmlspecialchars($r_norm); ?>')" title="Change Role">
                                        <i class="fa fa-pencil text-primary"></i> Role
                                    </button>
                                    <a href="?action=toggle_pos_access&user_id=<?php echo $row['id']; ?>" class="btn-action-light" title="<?php echo ($pos_acc === 1) ? 'Disable POS Access' : 'Grant POS Access'; ?>">
                                        <i class="fa fa-calculator <?php echo ($pos_acc === 1) ? 'text-success' : 'text-muted'; ?>"></i> POS
                                    </a>
                                    <a href="?action=toggle_status&user_id=<?php echo $row['id']; ?>" class="btn-action-light" title="<?php echo ($row['status'] === 'Active') ? 'Suspend Account' : 'Activate Account'; ?>">
                                        <?php if ($row['status'] === 'Active'): ?>
                                            <i class="fa fa-pause text-warning"></i>
                                        <?php else: ?>
                                            <i class="fa fa-play text-success"></i>
                                        <?php endif; ?>
                                    </a>
                                    <a href="?action=delete&user_id=<?php echo $row['id']; ?>" class="btn-action-delete" onclick="return confirm('Are you sure you want to remove Staff User <?php echo addslashes($row['full_name']); ?>?');" title="Remove Staff Member">
                                        <i class="fa fa-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Store Administrator Info Card -->
    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 16px 20px; margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
        <div style="display: flex; align-items: center; gap: 12px;">
            <div style="width: 36px; height: 36px; border-radius: 8px; background: #eff6ff; color: #0284c7; display: flex; align-items: center; justify-content: center; font-size: 16px;">
                <i class="fa fa-shield"></i>
            </div>
            <div>
                <div style="font-weight: 800; font-size: 13.5px; color: #1e293b;">Store Administrator (Owner Account)</div>
                <div style="font-size: 12px; color: #64748b;">
                    <?php foreach ($admin_users as $adm): ?>
                        <strong><?php echo htmlspecialchars($adm['full_name']); ?></strong> (<?php echo htmlspecialchars($adm['email']); ?>)
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <div>
            <span class="badge-role role-admin"><i class="fa fa-star"></i> Full Store Owner & Quota-Exempt</span>
        </div>
    </div>

</section>

<!-- ========================================================= -->
<!-- ADD STAFF USER MODAL -->
<!-- ========================================================= -->
<div id="addPosUserModal" class="modal fade" role="dialog" tabindex="-1">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content" style="border-radius: 12px; overflow: hidden; box-shadow: 0 15px 40px rgba(0,0,0,0.25); border: none;">
            <div class="modal-header" style="background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%); color: #fff; padding: 16px 22px;">
                <button type="button" class="close" data-dismiss="modal" style="color: #fff; opacity: 0.9; font-size: 24px;">&times;</button>
                <h4 class="modal-title" style="font-weight: 800; font-size: 17px; display: flex; align-items: center; gap: 8px;">
                    <i class="fa fa-user-plus"></i> Register New Staff Member
                </h4>
            </div>
            <form action="" method="post">
                <?php $csrf->echoInputField(); ?>
                <div class="modal-body" style="padding: 22px;">
                    
                    <div style="background: #f0f9ff; border: 1px solid #bae6fd; border-radius: 8px; padding: 10px 14px; font-size: 12.5px; color: #0369a1; margin-bottom: 18px; display: flex; align-items: center; gap: 8px;">
                        <i class="fa fa-info-circle fa-lg"></i>
                        <span>This account will be bound to <strong><?php echo htmlspecialchars($tenant_stats['supplier_name']); ?></strong> with auto-generated Employee ID.</span>
                    </div>

                    <!-- 1. Personal Information -->
                    <div style="font-size: 11px; font-weight: 800; color: #0284c7; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 12px;">
                        <i class="fa fa-id-card-o"></i> 1. Personal Details
                    </div>
                    <div class="row">
                        <div class="col-sm-4">
                            <div class="form-group" style="margin-bottom: 12px;">
                                <label style="font-size: 12px; font-weight: 700; color: #334155;">First Name *</label>
                                <input type="text" name="first_name" class="form-control" placeholder="e.g. Maria" required autocomplete="off" style="border-radius: 6px;">
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div class="form-group" style="margin-bottom: 12px;">
                                <label style="font-size: 12px; font-weight: 700; color: #334155;">Middle Name</label>
                                <input type="text" name="middle_name" class="form-control" placeholder="e.g. Clara" autocomplete="off" style="border-radius: 6px;">
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div class="form-group" style="margin-bottom: 12px;">
                                <label style="font-size: 12px; font-weight: 700; color: #334155;">Last Name *</label>
                                <input type="text" name="last_name" class="form-control" placeholder="e.g. Santos" required autocomplete="off" style="border-radius: 6px;">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-sm-4">
                            <div class="form-group" style="margin-bottom: 12px;">
                                <label style="font-size: 12px; font-weight: 700; color: #334155;">Employment Start Date *</label>
                                <input type="date" name="date_started" class="form-control" value="<?php echo date('Y-m-d'); ?>" required style="border-radius: 6px;">
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div class="form-group" style="margin-bottom: 12px;">
                                <label style="font-size: 12px; font-weight: 700; color: #334155;">Email Address (Login) *</label>
                                <input type="email" name="email" class="form-control" placeholder="maria@store.com" required autocomplete="off" style="border-radius: 6px;">
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div class="form-group" style="margin-bottom: 12px;">
                                <label style="font-size: 12px; font-weight: 700; color: #334155;">Contact / Phone No.</label>
                                <input type="text" name="phone" class="form-control" placeholder="09171234567" autocomplete="off" style="border-radius: 6px;">
                            </div>
                        </div>
                    </div>

                    <!-- 2. Account Credentials & Role -->
                    <div style="font-size: 11px; font-weight: 800; color: #0284c7; text-transform: uppercase; letter-spacing: 0.5px; margin: 16px 0 12px 0; border-top: 1px solid #f1f5f9; padding-top: 14px;">
                        <i class="fa fa-key"></i> 2. Store Role & Security
                    </div>
                    <div class="row">
                        <div class="col-sm-4">
                            <div class="form-group" style="margin-bottom: 12px;">
                                <label style="font-size: 12px; font-weight: 700; color: #334155;">Store Role *</label>
                                <select name="role" class="form-control" required style="border-radius: 6px; font-weight: 600;">
                                    <?php 
                                    $assignable_roles = get_assignable_roles($current_user_role);
                                    foreach ($assignable_roles as $r_opt): 
                                    ?>
                                        <option value="<?php echo $r_opt; ?>" <?php echo $r_opt === 'CASHIER' ? 'selected' : ''; ?>><?php echo htmlspecialchars(get_role_display_name($r_opt)); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div class="form-group" style="margin-bottom: 12px;">
                                <label style="font-size: 12px; font-weight: 700; color: #334155;">Password *</label>
                                <input type="password" name="password" class="form-control" placeholder="Min. 6 characters" required autocomplete="off" style="border-radius: 6px;">
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div class="form-group" style="margin-bottom: 12px;">
                                <label style="font-size: 12px; font-weight: 700; color: #334155;">Confirm Password *</label>
                                <input type="password" name="confirm_password" class="form-control" placeholder="Re-enter password" required autocomplete="off" style="border-radius: 6px;">
                            </div>
                        </div>
                    </div>

                    <!-- 3. Access Authorization -->
                    <div style="font-size: 11px; font-weight: 800; color: #0284c7; text-transform: uppercase; letter-spacing: 0.5px; margin: 16px 0 10px 0; border-top: 1px solid #f1f5f9; padding-top: 14px;">
                        <i class="fa fa-shield"></i> 3. POS Terminal Authorization
                    </div>
                    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px 16px;">
                        <label style="font-size: 13px; font-weight: 700; color: #1e293b; margin-bottom: 0; cursor: pointer; display: flex; align-items: center; gap: 8px;">
                            <input type="checkbox" name="pos_access" value="1" checked style="width: 16px; height: 16px;">
                            <span>Grant access to Point of Sale Terminal (<code>/supplier/pos.php</code>)</span>
                        </label>
                    </div>

                </div>
                <div class="modal-footer" style="background: #f8fafc; border-top: 1px solid #e2e8f0; padding: 14px 22px;">
                    <button type="button" class="btn btn-default" data-dismiss="modal" style="font-weight: 600; border-radius: 6px;">Cancel</button>
                    <button type="submit" name="form_add_pos_user" class="btn btn-primary" style="background-color: #0284c7; border-color: #0284c7; font-weight: 700; border-radius: 6px; padding: 8px 20px;">
                        <i class="fa fa-check"></i> Register Staff Member
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ========================================================= -->
<!-- EDIT POS USER ROLE MODAL -->
<!-- ========================================================= -->
<div id="editPosUserRoleModal" class="modal fade" role="dialog" tabindex="-1">
    <div class="modal-dialog modal-sm" role="document">
        <div class="modal-content" style="border-radius: 12px; overflow: hidden; box-shadow: 0 15px 40px rgba(0,0,0,0.25); border: none;">
            <div class="modal-header" style="background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%); color: #fff; padding: 14px 18px;">
                <button type="button" class="close" data-dismiss="modal" style="color: #fff; opacity: 0.9;">&times;</button>
                <h4 class="modal-title" style="font-weight: 800; font-size: 15px;"><i class="fa fa-pencil"></i> Modify Staff Role</h4>
            </div>
            <form action="" method="post">
                <?php $csrf->echoInputField(); ?>
                <input type="hidden" name="form_edit_pos_user_role" value="1">
                <input type="hidden" name="edit_user_id" id="editRoleId">
                <div class="modal-body" style="padding: 18px;">
                    <p style="font-size: 13px; color: #1e293b; margin-bottom: 12px;">Select new role for <strong id="editRoleUserName" style="color: #0284c7;"></strong>:</p>
                    
                    <div class="form-group" style="margin-bottom: 0;">
                        <label style="font-size: 12px; font-weight: 700; color: #334155;">New Store Role:</label>
                        <select name="edit_role" id="editRoleSelect" class="form-control" style="font-weight: 600; border-radius: 6px;">
                            <?php 
                            $modal_assignable_roles = !empty($assignable_roles) ? $assignable_roles : ['MANAGER', 'SUPERVISOR', 'CASHIER', 'OPERATOR', 'ORDER_PROCESSING', 'ENCODER'];
                            foreach ($modal_assignable_roles as $r_opt): 
                            ?>
                                <option value="<?php echo $r_opt; ?>"><?php echo htmlspecialchars(get_role_display_name($r_opt)); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer" style="background: #f8fafc; border-top: 1px solid #e2e8f0; padding: 12px 18px;">
                    <button type="button" class="btn btn-default btn-sm" data-dismiss="modal" style="font-weight: 600; border-radius: 6px;">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm" style="background-color: #0284c7; border-color: #0284c7; font-weight: 700; border-radius: 6px; padding: 6px 16px;">
                        <i class="fa fa-save"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openEditRoleModal(userId, userName, currentRole) {
    document.getElementById('editRoleId').value = userId;
    document.getElementById('editRoleUserName').innerText = userName;
    document.getElementById('editRoleSelect').value = currentRole;
    $('#editPosUserRoleModal').modal('show');
}

function filterStaffTable() {
    const input = document.getElementById('staffSearchInput');
    const filter = input.value.toLowerCase();
    const rows = document.querySelectorAll('#staffDirectoryTable tbody tr.staff-row');
    
    rows.forEach(row => {
        const text = row.innerText.toLowerCase();
        row.style.display = text.includes(filter) ? '' : 'none';
    });
}
</script>

<?php require_once('footer.php'); ?>
