<?php
ob_start();
session_start();
include("inc/config.php");
include("inc/functions.php");
include("inc/CSRF_Protect.php");
$csrf = new CSRF_Protect();

$error_message = '';
$success_message = '';

// Active view mode: 'select' (default), 'admin_login', 'user_login', 'register'
$mode = isset($_GET['mode']) ? trim($_GET['mode']) : (isset($_POST['mode']) ? trim($_POST['mode']) : 'select');
if (!in_array($mode, ['select', 'admin_login', 'user_login', 'register'])) {
    $mode = 'select';
}

$new_registered_user = null;

// -------------------------------------------------------------
// 1. Process ADMIN LOGIN Form
// -------------------------------------------------------------
if (isset($_POST['form_admin_login'])) {
    $mode = 'admin_login';
    $login_input = isset($_POST['email']) ? trim(strip_tags($_POST['email'])) : '';
    $password = isset($_POST['password']) ? trim(strip_tags($_POST['password'])) : '';

    if (empty($login_input) || empty($password)) {
        $error_message = 'Email address / Employee ID and password are required.';
    } else {
        ensure_supplier_user_schema($pdo);
        $stmt = $pdo->prepare("SELECT u.*, s.supplier_name, s.supplier_status, s.supplier_plan, s.supplier_slug 
                               FROM tbl_supplier_user u
                               JOIN tbl_supplier s ON u.supplier_id = s.supplier_id
                               WHERE (LOWER(u.email) = LOWER(?) OR UPPER(u.employee_id) = UPPER(?))");
        $stmt->execute(array($login_input, $login_input));
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $error_message = 'Invalid credentials. Please verify your Email/Employee ID and password.';
        } elseif ($user['status'] !== 'Active') {
            $error_message = 'Your user account is suspended or inactive. Please contact your administrator.';
        } elseif ($user['supplier_status'] !== 'Active') {
            $error_message = 'Your supplier organization account is currently ' . htmlspecialchars($user['supplier_status']) . '. Access is restricted.';
        } else {
            $role = normalize_supplier_role($user['role']);
            if (!in_array($role, ['ADMIN', 'MANAGER'])) {
                $error_message = 'This account does not have Supplier Admin privileges. Please use the POS / Staff Login option.';
            } elseif (!verify_supplier_password($password, $user['password'])) {
                $error_message = 'Invalid credentials. Please verify your Email/Employee ID and password.';
            } else {
                // Successful Admin Login
                $_SESSION['supplier_user'] = [
                    'id' => $user['id'],
                    'supplier_id' => $user['supplier_id'],
                    'full_name' => $user['full_name'],
                    'first_name' => !empty($user['first_name']) ? $user['first_name'] : '',
                    'last_name' => !empty($user['last_name']) ? $user['last_name'] : '',
                    'phone' => !empty($user['phone']) ? $user['phone'] : '',
                    'email' => $user['email'],
                    'employee_id' => !empty($user['employee_id']) ? $user['employee_id'] : '',
                    'role' => $role,
                    'status' => $user['status'],
                    'pos_access' => isset($user['pos_access']) ? (int)$user['pos_access'] : 1,
                    'date_started' => !empty($user['date_started']) ? $user['date_started'] : '',
                    'supplier_name' => $user['supplier_name'],
                    'supplier_slug' => $user['supplier_slug'],
                    'supplier_plan' => $user['supplier_plan']
                ];
                header("Location: index.php");
                exit;
            }
        }
    }
}

// -------------------------------------------------------------
// 2. Process POS / STAFF USER LOGIN Form
// -------------------------------------------------------------
if (isset($_POST['form_user_login'])) {
    $mode = 'user_login';
    $login_input = isset($_POST['email']) ? trim(strip_tags($_POST['email'])) : '';
    $password = isset($_POST['password']) ? trim(strip_tags($_POST['password'])) : '';

    if (empty($login_input) || empty($password)) {
        $error_message = 'Email address / Employee ID and password are required.';
    } else {
        ensure_supplier_user_schema($pdo);
        $stmt = $pdo->prepare("SELECT u.*, s.supplier_name, s.supplier_status, s.supplier_plan, s.supplier_slug 
                               FROM tbl_supplier_user u
                               JOIN tbl_supplier s ON u.supplier_id = s.supplier_id
                               WHERE (LOWER(u.email) = LOWER(?) OR UPPER(u.employee_id) = UPPER(?))");
        $stmt->execute(array($login_input, $login_input));
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $error_message = 'Invalid credentials. Please verify your Email/Employee ID and password.';
        } elseif ($user['status'] !== 'Active') {
            $error_message = 'Your user account is suspended or inactive. Please contact your administrator.';
        } elseif ($user['supplier_status'] !== 'Active') {
            $error_message = 'Your supplier organization account is currently ' . htmlspecialchars($user['supplier_status']) . '. Access is restricted.';
        } else {
            $role = normalize_supplier_role($user['role']);

            if (!verify_supplier_password($password, $user['password'])) {
                $error_message = 'Invalid credentials. Please verify your Email/Employee ID and password.';
            } else {
                $pos_access = isset($user['pos_access']) ? (int)$user['pos_access'] : 1;
                // Successful POS / Staff User Login
                $_SESSION['supplier_user'] = [
                    'id' => $user['id'],
                    'supplier_id' => $user['supplier_id'],
                    'full_name' => $user['full_name'],
                    'first_name' => !empty($user['first_name']) ? $user['first_name'] : '',
                    'last_name' => !empty($user['last_name']) ? $user['last_name'] : '',
                    'phone' => !empty($user['phone']) ? $user['phone'] : '',
                    'email' => $user['email'],
                    'employee_id' => !empty($user['employee_id']) ? $user['employee_id'] : '',
                    'role' => $role,
                    'status' => $user['status'],
                    'pos_access' => $pos_access,
                    'date_started' => !empty($user['date_started']) ? $user['date_started'] : '',
                    'supplier_name' => $user['supplier_name'],
                    'supplier_slug' => $user['supplier_slug'],
                    'supplier_plan' => $user['supplier_plan']
                ];
                if (in_array($role, ['ADMIN', 'MANAGER'])) {
                    header("Location: index.php");
                } elseif ($pos_access === 1) {
                    header("Location: pos.php");
                } else {
                    header("Location: user-manual.php");
                }
                exit;
            }
        }
    }
}

// -------------------------------------------------------------
// 3. Process SUPPLIER STAFF REGISTRATION Form
// -------------------------------------------------------------
if (isset($_POST['form_register_user'])) {
    $mode = 'register';
    
    // Authorization Check: Must be logged in as Admin, Manager, or Supervisor
    $is_logged_in = is_supplier_logged_in();
    $current_role = $is_logged_in ? normalize_supplier_role($_SESSION['supplier_user']['role']) : '';
    
    if (!$is_logged_in || !can_register_staff($current_role)) {
        $error_message = '<strong>Access Denied:</strong> Only Store Administrators, Managers, and Supervisors can register staff accounts. Please log in with authorized credentials.';
    } else {
        $target_supplier_id = (int)$_SESSION['supplier_user']['supplier_id'];
        
        $first_name = isset($_POST['first_name']) ? trim(strip_tags($_POST['first_name'])) : '';
        $middle_name = isset($_POST['middle_name']) ? trim(strip_tags($_POST['middle_name'])) : '';
        $last_name = isset($_POST['last_name']) ? trim(strip_tags($_POST['last_name'])) : '';
        $phone = isset($_POST['phone']) ? trim(strip_tags($_POST['phone'])) : '';
        $email = isset($_POST['email']) ? trim(strip_tags($_POST['email'])) : '';
        $role_input = isset($_POST['role']) ? trim(strip_tags($_POST['role'])) : 'CASHIER';
        $target_role = normalize_supplier_role($role_input);
        $date_started = isset($_POST['date_started']) ? trim(strip_tags($_POST['date_started'])) : date('Y-m-d');
        if (empty($date_started) || !strtotime($date_started)) {
            $date_started = date('Y-m-d');
        }
        $password = isset($_POST['password']) ? trim(strip_tags($_POST['password'])) : '';
        $confirm_password = isset($_POST['confirm_password']) ? trim(strip_tags($_POST['confirm_password'])) : '';
        $pos_access = isset($_POST['pos_access']) ? 1 : 0;

        // Compose full name
        $name_parts = array_filter([$first_name, $middle_name, $last_name]);
        $full_name = implode(' ', $name_parts);

        if (empty($first_name) || empty($last_name) || empty($email) || empty($password) || empty($confirm_password)) {
            $error_message = 'First Name, Last Name, Start Date, Email, and Passwords are required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_message = 'Please provide a valid email address.';
        } elseif (!can_assign_role($current_role, $target_role)) {
            $error_message = '<strong>Permission Denied:</strong> Your role (' . get_role_display_name($current_role) . ') is not authorized to register an account with the role: ' . get_role_display_name($target_role) . '.';
        } elseif ($password !== $confirm_password) {
            $error_message = 'Passwords do not match. Please re-enter.';
        } elseif (strlen($password) < 6) {
            $error_message = 'Password must be at least 6 characters long.';
        } else {
            // Check Tenant POS User Quota Limits
            $stats = get_tenant_pos_user_stats($pdo, $target_supplier_id);
            if ($stats['is_limit_reached']) {
                $error_message = '<strong>Staff User Limit Reached:</strong> Your store\'s ' . htmlspecialchars($stats['plan_name']) . 
                                 ' Plan allows a maximum of ' . $stats['max_pos_users'] . ' Staff Users (' . $stats['current_pos_users'] . '/' . $stats['max_pos_users'] . ' slots used).<br>' .
                                 'Please contact your SaaS Administrator to increase your user allowance or upgrade your plan.';
            } else {
                // Check Email Uniqueness
                $stmt_chk = $pdo->prepare("SELECT id FROM tbl_supplier_user WHERE LOWER(email) = LOWER(?)");
                $stmt_chk->execute(array($email));
                if ($stmt_chk->rowCount() > 0) {
                    $error_message = 'The email address <strong>' . htmlspecialchars($email) . '</strong> is already registered.';
                } else {
                    try {
                        $pdo->beginTransaction();

                        // Re-verify limit inside transaction with row-lock
                        $stmt_lock = $pdo->prepare("SELECT COUNT(*) as total FROM tbl_supplier_user WHERE supplier_id = ? AND UPPER(role) NOT IN ('ADMIN', 'SUPPLIER_ADMIN', 'SUPERADMIN')");
                        $stmt_lock->execute(array($target_supplier_id));
                        $row_lock = $stmt_lock->fetch(PDO::FETCH_ASSOC);
                        $current_count = (int)$row_lock['total'];

                        if ($current_count >= $stats['max_pos_users']) {
                            $pdo->rollBack();
                            $error_message = 'Staff User quota reached for this store (' . $stats['max_pos_users'] . '/' . $stats['max_pos_users'] . '). Registration blocked.';
                        } else {
                            ensure_supplier_user_schema($pdo);
                            $employee_id = generate_supplier_employee_id($pdo, $target_supplier_id, $date_started);
                            $hashed_pass = hash_supplier_password($password);

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
                                $target_supplier_id, 
                                $first_name,
                                $middle_name,
                                $last_name,
                                $full_name, 
                                $email, 
                                $phone,
                                $hashed_pass, 
                                $target_role, 
                                $employee_id, 
                                $date_started,
                                $pos_access
                            ));
                            $new_user_id = $pdo->lastInsertId();

                            $pdo->commit();

                            $new_registered_user = [
                                'id' => $new_user_id,
                                'full_name' => $full_name,
                                'first_name' => $first_name,
                                'middle_name' => $middle_name,
                                'last_name' => $last_name,
                                'email' => $email,
                                'phone' => $phone,
                                'role' => $target_role,
                                'employee_id' => $employee_id,
                                'date_started' => $date_started,
                                'pos_access' => $pos_access,
                                'supplier_name' => $_SESSION['supplier_user']['supplier_name']
                            ];
                            $success_message = 'Employee registered successfully!';
                        }
                    } catch (Exception $e) {
                        if ($pdo->inTransaction()) {
                            $pdo->rollBack();
                        }
                        $error_message = 'Registration error: ' . $e->getMessage();
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<title>Supplier Portal Authentication - eConstructionSite</title>

	<meta content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" name="viewport">

	<link rel="stylesheet" href="css/bootstrap.min.css">
	<link rel="stylesheet" href="css/font-awesome.min.css">
	<link rel="stylesheet" href="css/ionicons.min.css">
	<link rel="stylesheet" href="css/AdminLTE.min.css">
	<link rel="stylesheet" href="css/_all-skins.min.css">
	<link rel="stylesheet" href="style.css">

    <style>
    body.login-page {
        background: #0f172a !important;
        font-family: 'Source Sans Pro', 'Helvetica Neue', Helvetica, Arial, sans-serif;
    }
    .portal-container {
        max-width: 650px;
        margin: 40px auto;
        padding: 0 15px;
    }
    .portal-header {
        text-align: center;
        margin-bottom: 30px;
    }
    .portal-header h1 {
        font-size: 28px;
        font-weight: 800;
        color: #ffffff;
        margin: 0 0 8px 0;
        letter-spacing: -0.5px;
    }
    .portal-header p {
        color: #94a3b8;
        font-size: 15px;
        margin: 0;
    }
    .portal-card-box {
        background: #1e293b;
        border: 1px solid #334155;
        border-radius: 12px;
        padding: 30px;
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.5), 0 10px 10px -5px rgba(0, 0, 0, 0.4);
    }
    
    /* Selection Cards */
    .role-card {
        background: #0f172a;
        border: 2px solid #334155;
        border-radius: 10px;
        padding: 24px 20px;
        text-align: center;
        transition: all 0.25s ease-in-out;
        margin-bottom: 20px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        height: 100%;
    }
    .role-card:hover {
        border-color: #0284c7;
        transform: translateY(-4px);
        box-shadow: 0 12px 20px -5px rgba(2, 132, 199, 0.25);
    }
    .role-icon {
        font-size: 44px;
        margin-bottom: 14px;
    }
    .role-card-admin .role-icon { color: #38bdf8; }
    .role-card-user .role-icon { color: #34d399; }
    
    .role-title {
        font-size: 20px;
        font-weight: 800;
        color: #f8fafc;
        margin-bottom: 6px;
    }
    .role-subtitle {
        font-size: 13px;
        color: #94a3b8;
        margin-bottom: 16px;
        line-height: 1.4;
    }
    .role-btn {
        width: 100%;
        padding: 10px 15px;
        font-weight: 700;
        border-radius: 6px;
        text-transform: uppercase;
        font-size: 13px;
        letter-spacing: 0.5px;
    }

    /* Forms */
    .form-title-area {
        border-bottom: 1px solid #334155;
        padding-bottom: 15px;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .form-title-area h2 {
        font-size: 20px;
        font-weight: 700;
        color: #f8fafc;
        margin: 0;
    }
    .form-control-custom {
        background: #0f172a;
        border: 1px solid #334155;
        color: #f8fafc;
        border-radius: 6px;
        height: 42px;
        font-size: 14px;
    }
    .form-control-custom:focus {
        border-color: #0284c7;
        box-shadow: 0 0 0 2px rgba(2, 132, 199, 0.2);
        color: #fff;
        background: #0f172a;
    }
    .control-label-custom {
        color: #cbd5e1;
        font-weight: 600;
        font-size: 13px;
        margin-bottom: 6px;
    }
    .btn-portal-primary {
        background: #0284c7;
        border-color: #0284c7;
        color: #fff;
        font-weight: 700;
        height: 44px;
        font-size: 14px;
        border-radius: 6px;
        transition: all 0.2s;
    }
    .btn-portal-primary:hover {
        background: #0369a1;
        border-color: #0369a1;
        color: #fff;
    }
    .btn-portal-success {
        background: #10b981;
        border-color: #10b981;
        color: #fff;
        font-weight: 700;
        height: 44px;
        font-size: 14px;
        border-radius: 6px;
        transition: all 0.2s;
    }
    .btn-portal-success:hover {
        background: #059669;
        border-color: #059669;
        color: #fff;
    }
    .btn-portal-back {
        background: transparent;
        border: 1px solid #475569;
        color: #94a3b8;
        font-size: 13px;
        border-radius: 6px;
        padding: 6px 12px;
        transition: all 0.15s;
    }
    .btn-portal-back:hover {
        background: #334155;
        color: #fff;
        text-decoration: none;
    }
    .alert-custom-error {
        background: rgba(239, 68, 68, 0.15);
        border: 1px solid #ef4444;
        color: #fca5a5;
        border-radius: 6px;
        padding: 12px 15px;
        margin-bottom: 20px;
        font-size: 13px;
    }
    .alert-custom-success {
        background: rgba(16, 185, 129, 0.15);
        border: 1px solid #10b981;
        color: #6ee7b7;
        border-radius: 6px;
        padding: 12px 15px;
        margin-bottom: 20px;
        font-size: 13px;
    }
    </style>
</head>

<body class="hold-transition login-page">

<div class="portal-container">

    <div class="portal-header">
        <h1>eConstruction Supply</h1>
        <p>B2B Multi-Tenant Supplier & POS Portal</p>
    </div>

    <div class="portal-card-box">

        <!-- Error & Success Messages -->
        <?php if (!empty($error_message)): ?>
            <div class="alert-custom-error">
                <i class="fa fa-exclamation-circle" style="margin-right: 6px;"></i> <?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($success_message)): ?>
            <div class="alert-custom-success">
                <i class="fa fa-check-circle" style="margin-right: 6px;"></i> <?php echo $success_message; ?>
            </div>
        <?php endif; ?>

        <!-- ========================================================= -->
        <!-- VIEW 1: ACCOUNT TYPE SELECTION -->
        <!-- ========================================================= -->
        <?php if ($mode === 'select'): ?>
            <div style="text-align: center; margin-bottom: 25px;">
                <h3 style="color: #f8fafc; font-weight: 700; margin: 0 0 6px 0; font-size: 20px;">Select Account Type</h3>
                <p style="color: #94a3b8; font-size: 14px; margin: 0;">Choose your role to access the authorized interface</p>
            </div>

            <div class="row">
                <!-- ADMIN OPTION -->
                <div class="col-sm-6">
                    <div class="role-card role-card-admin">
                        <div>
                            <div class="role-icon"><i class="fa fa-shield"></i></div>
                            <div class="role-title">ADMIN</div>
                            <div class="role-subtitle">
                                <strong>Supplier Administration</strong><br>
                                Full management access to catalog, orders, returns, reports, and store settings.
                            </div>
                        </div>
                        <a href="?mode=admin_login" class="btn btn-primary role-btn" style="background-color: #0284c7; border-color: #0284c7;">
                            <i class="fa fa-lock" style="margin-right: 4px;"></i> Admin Login
                        </a>
                    </div>
                </div>

                <!-- USER OPTION -->
                <div class="col-sm-6">
                    <div class="role-card role-card-user">
                        <div>
                            <div class="role-icon"><i class="fa fa-desktop"></i></div>
                            <div class="role-title">USER</div>
                            <div class="role-subtitle">
                                <strong>POS Cashier & Operator</strong><br>
                                Direct point-of-sale access for billing, over-the-counter orders, and customer returns.
                            </div>
                        </div>
                        <a href="?mode=user_login" class="btn btn-success role-btn" style="background-color: #10b981; border-color: #10b981;">
                            <i class="fa fa-calculator" style="margin-right: 4px;"></i> User Login
                        </a>
                    </div>
                </div>
            </div>

            <div style="text-align: center; margin-top: 15px; border-top: 1px solid #334155; padding-top: 15px;">
                <small style="color: #64748b;">eConstruction Supply Platform &copy; <?php echo date('Y'); ?>. All Rights Reserved.</small>
            </div>

        <!-- ========================================================= -->
        <!-- VIEW 2: ADMIN LOGIN FORM -->
        <!-- ========================================================= -->
        <?php elseif ($mode === 'admin_login'): ?>
            <div class="form-title-area">
                <div>
                    <h2><i class="fa fa-shield" style="color: #38bdf8; margin-right: 6px;"></i> Supplier Admin Login</h2>
                    <span style="font-size: 12px; color: #94a3b8;">Enter credentials for Supplier Administration</span>
                </div>
                <a href="?mode=select" class="btn-portal-back"><i class="fa fa-arrow-left"></i> Back</a>
            </div>

            <form action="" method="post">
                <?php $csrf->echoInputField(); ?>
                <input type="hidden" name="mode" value="admin_login">

                <div class="form-group">
                    <label class="control-label-custom">Email Address or Employee ID</label>
                    <input type="text" name="email" class="form-control form-control-custom" placeholder="admin@store.com or SICS-..." required autocomplete="off" autofocus>
                </div>

                <div class="form-group">
                    <label class="control-label-custom">Password</label>
                    <input type="password" name="password" class="form-control form-control-custom" placeholder="••••••••" required autocomplete="off">
                </div>

                <div style="margin-top: 25px;">
                    <button type="submit" name="form_admin_login" class="btn btn-block btn-portal-primary">
                        <i class="fa fa-sign-in" style="margin-right: 6px;"></i> Login to Admin Panel
                    </button>
                </div>

                <div style="margin-top: 20px; text-align: center;">
                    <a href="?mode=user_login" style="color: #38bdf8; font-size: 13px;">
                        Need POS Register access? Switch to <strong>POS Staff Login</strong> <i class="fa fa-arrow-right"></i>
                    </a>
                </div>
            </form>

        <!-- ========================================================= -->
        <!-- VIEW 3: POS / STAFF USER LOGIN FORM -->
        <!-- ========================================================= -->
        <?php elseif ($mode === 'user_login'): ?>
            <div class="form-title-area">
                <div>
                    <h2><i class="fa fa-calculator" style="color: #34d399; margin-right: 6px;"></i> Supplier Staff Login</h2>
                    <span style="font-size: 12px; color: #94a3b8;">Enter Employee ID or Email to access POS & Staff Terminal</span>
                </div>
                <a href="?mode=select" class="btn-portal-back"><i class="fa fa-arrow-left"></i> Back</a>
            </div>

            <form action="" method="post">
                <?php $csrf->echoInputField(); ?>
                <input type="hidden" name="mode" value="user_login">

                <div class="form-group">
                    <label class="control-label-custom">Employee ID or Email Address</label>
                    <input type="text" name="email" class="form-control form-control-custom" placeholder="e.g. SICS-20260907-A or cashier@store.com" required autocomplete="off" autofocus>
                </div>

                <div class="form-group">
                    <label class="control-label-custom">Password</label>
                    <input type="password" name="password" class="form-control form-control-custom" placeholder="••••••••" required autocomplete="off">
                </div>

                <div style="margin-top: 25px;">
                    <button type="submit" name="form_user_login" class="btn btn-block btn-portal-success">
                        <i class="fa fa-shopping-cart" style="margin-right: 6px;"></i> Login to POS Terminal
                    </button>
                </div>

                <div style="margin-top: 20px; display: flex; justify-content: space-between; align-items: center; border-top: 1px solid #334155; padding-top: 15px;">
                    <a href="?mode=register" style="color: #34d399; font-size: 13px; font-weight: 600;">
                        <i class="fa fa-user-plus"></i> Staff Registration
                    </a>
                    <a href="?mode=admin_login" style="color: #94a3b8; font-size: 13px;">
                        Supplier Admin? <strong>Admin Login</strong>
                    </a>
                </div>
            </form>

        <!-- ========================================================= -->
        <!-- VIEW 4: POS STAFF REGISTRATION FORM -->
        <!-- ========================================================= -->
        <?php elseif ($mode === 'register'): ?>
            <?php
            $is_auth = is_supplier_logged_in();
            $auth_user = current_supplier_user();
            $auth_role = $is_auth ? normalize_supplier_role($auth_user['role']) : '';
            $can_reg = $is_auth && can_register_staff($auth_role);
            ?>

            <div class="form-title-area">
                <div>
                    <h2><i class="fa fa-user-plus" style="color: #34d399; margin-right: 6px;"></i> Register Supplier Employee</h2>
                    <span style="font-size: 12px; color: #94a3b8;">System-Generated Employee ID with Role-Based Provisioning</span>
                </div>
                <a href="<?php echo $is_auth ? 'pos.php' : '?mode=user_login'; ?>" class="btn-portal-back"><i class="fa fa-arrow-left"></i> <?php echo $is_auth ? 'Back to Portal' : 'Back to Login'; ?></a>
            </div>

            <?php if ($new_registered_user): ?>
                <!-- REGISTRATION SUCCESS CARD -->
                <div style="background: #064e3b; border: 1.5px solid #10b981; border-radius: 8px; padding: 20px; margin-bottom: 20px;">
                    <div style="text-align: center; margin-bottom: 15px;">
                        <i class="fa fa-check-circle" style="font-size: 40px; color: #34d399;"></i>
                        <h3 style="color: #fff; margin: 8px 0 4px 0; font-weight: 800;">Employee Registration Successful!</h3>
                        <p style="color: #a7f3d0; font-size: 13px; margin: 0;">Employee credentials have been provisioned.</p>
                    </div>

                    <div style="background: rgba(15, 23, 42, 0.6); border: 1px solid #047857; border-radius: 6px; padding: 14px; margin-bottom: 15px;">
                        <table style="width: 100%; font-size: 13px; color: #f8fafc;">
                            <tr style="border-bottom: 1px solid rgba(255,255,255,0.1);">
                                <td style="padding: 6px 0; color: #94a3b8; width: 40%;">Employee Name:</td>
                                <td style="padding: 6px 0; font-weight: bold;"><?php echo htmlspecialchars($new_registered_user['full_name']); ?></td>
                            </tr>
                            <tr style="border-bottom: 1px solid rgba(255,255,255,0.1);">
                                <td style="padding: 6px 0; color: #94a3b8;">Employee ID:</td>
                                <td style="padding: 6px 0;">
                                    <span class="label label-primary" style="background: #0284c7; font-size: 13px; font-family: monospace; padding: 3px 8px;">
                                        <?php echo htmlspecialchars($new_registered_user['employee_id']); ?>
                                    </span>
                                </td>
                            </tr>
                            <tr style="border-bottom: 1px solid rgba(255,255,255,0.1);">
                                <td style="padding: 6px 0; color: #94a3b8;">Assigned Role:</td>
                                <td style="padding: 6px 0; font-weight: bold; color: #38bdf8;"><?php echo htmlspecialchars(get_role_display_name($new_registered_user['role'])); ?></td>
                            </tr>
                            <tr style="border-bottom: 1px solid rgba(255,255,255,0.1);">
                                <td style="padding: 6px 0; color: #94a3b8;">POS Terminal Access:</td>
                                <td style="padding: 6px 0;">
                                    <?php if (!empty($new_registered_user['pos_access'])): ?>
                                        <span class="label label-success" style="background: #10b981; font-size: 11px;"><i class="fa fa-check"></i> Authorized</span>
                                    <?php else: ?>
                                        <span class="label label-default" style="background: #64748b; font-size: 11px;"><i class="fa fa-ban"></i> Disabled</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr style="border-bottom: 1px solid rgba(255,255,255,0.1);">
                                <td style="padding: 6px 0; color: #94a3b8;">Start Date:</td>
                                <td style="padding: 6px 0;"><?php echo htmlspecialchars($new_registered_user['date_started']); ?></td>
                            </tr>
                            <tr style="border-bottom: 1px solid rgba(255,255,255,0.1);">
                                <td style="padding: 6px 0; color: #94a3b8;">Email Address:</td>
                                <td style="padding: 6px 0; font-family: monospace;"><?php echo htmlspecialchars($new_registered_user['email']); ?></td>
                            </tr>
                            <?php if (!empty($new_registered_user['phone'])): ?>
                            <tr style="border-bottom: 1px solid rgba(255,255,255,0.1);">
                                <td style="padding: 6px 0; color: #94a3b8;">Contact Number:</td>
                                <td style="padding: 6px 0;"><?php echo htmlspecialchars($new_registered_user['phone']); ?></td>
                            </tr>
                            <?php endif; ?>
                            <tr>
                                <td style="padding: 6px 0; color: #94a3b8;">Store / Supplier:</td>
                                <td style="padding: 6px 0;"><?php echo htmlspecialchars($new_registered_user['supplier_name']); ?></td>
                            </tr>
                        </table>
                    </div>

                    <div style="font-size: 12px; color: #cbd5e1; text-align: center; margin-bottom: 15px;">
                        <i class="fa fa-info-circle"></i> The employee can now log in using their <strong>Employee ID</strong> (<code><?php echo htmlspecialchars($new_registered_user['employee_id']); ?></code>) or <strong>Email Address</strong> and password.
                    </div>

                    <div style="display: flex; gap: 10px;">
                        <a href="?mode=register" class="btn btn-default btn-block" style="background: transparent; border: 1px solid #34d399; color: #34d399; font-weight: 700;">
                            <i class="fa fa-user-plus"></i> Register Another Employee
                        </a>
                        <a href="<?php echo is_admin_or_manager_role($auth_role) ? 'users.php' : 'pos.php'; ?>" class="btn btn-portal-success btn-block">
                            <i class="fa fa-arrow-right"></i> Continue to Portal
                        </a>
                    </div>
                </div>

            <?php elseif (!$can_reg): ?>
                <!-- UNAUTHORIZED ACCESS NOTICE -->
                <div style="background: #1e293b; border: 1px solid #475569; border-radius: 8px; padding: 24px; text-align: center;">
                    <div style="font-size: 40px; color: #f59e0b; margin-bottom: 12px;">
                        <i class="fa fa-lock"></i>
                    </div>
                    <h3 style="color: #f8fafc; font-weight: 700; margin: 0 0 8px 0; font-size: 18px;">Staff Registration Authorization Required</h3>
                    <p style="color: #94a3b8; font-size: 13.5px; line-height: 1.5; margin-bottom: 20px;">
                        New supplier staff and employee accounts can only be registered by an authorized <strong>Store Administrator</strong>, <strong>Manager</strong>, or <strong>Supervisor</strong> of your organization.
                    </p>
                    <div style="display: flex; gap: 10px; justify-content: center; margin-bottom: 18px;">
                        <a href="?mode=admin_login" class="btn btn-portal-primary" style="padding: 10px 20px;">
                            <i class="fa fa-shield"></i> Supplier Admin Login
                        </a>
                        <a href="?mode=user_login" class="btn btn-portal-success" style="padding: 10px 20px;">
                            <i class="fa fa-calculator"></i> Staff Login
                        </a>
                    </div>
                    <div style="border-top: 1px solid #334155; padding-top: 12px; font-size: 12.5px; color: #94a3b8;">
                        Looking to register a completely new supplier company storefront? <a href="../supplier-registration.php" style="color: #38bdf8; font-weight: 600;">Register Supplier Company</a>
                    </div>
                </div>

            <?php else: ?>
                <!-- REGISTRATION FORM FOR AUTHORIZED ADMIN / MANAGER / SUPERVISOR -->
                <div style="background: #0f172a; border: 1px solid #334155; border-radius: 6px; padding: 12px 15px; margin-bottom: 18px; display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <span style="font-size: 11px; color: #94a3b8; text-transform: uppercase; font-weight: 700;">Store / Tenant:</span>
                        <div style="font-size: 14px; font-weight: 700; color: #f8fafc;"><?php echo htmlspecialchars($auth_user['supplier_name']); ?></div>
                    </div>
                    <div style="text-align: right;">
                        <span style="font-size: 11px; color: #94a3b8; text-transform: uppercase; font-weight: 700;">Authorized By:</span>
                        <div><span class="label label-primary" style="background: #0284c7;"><?php echo htmlspecialchars($auth_user['full_name']) . ' (' . get_role_display_name($auth_role) . ')'; ?></span></div>
                    </div>
                </div>

                <form action="" method="post">
                    <?php $csrf->echoInputField(); ?>
                    <input type="hidden" name="mode" value="register">

                    <!-- Section: Employee Personal Information -->
                    <div style="font-size: 12px; font-weight: 800; color: #38bdf8; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px; border-bottom: 1px solid #1e293b; padding-bottom: 4px;">
                        <i class="fa fa-id-card-o"></i> 1. Employee Information
                    </div>

                    <div class="row">
                        <div class="col-sm-4">
                            <div class="form-group">
                                <label class="control-label-custom">First Name *</label>
                                <input type="text" name="first_name" class="form-control form-control-custom" placeholder="e.g. Maria" required autocomplete="off" autofocus>
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div class="form-group">
                                <label class="control-label-custom">Middle Name</label>
                                <input type="text" name="middle_name" class="form-control form-control-custom" placeholder="e.g. Clara" autocomplete="off">
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div class="form-group">
                                <label class="control-label-custom">Last Name *</label>
                                <input type="text" name="last_name" class="form-control form-control-custom" placeholder="e.g. Santos" required autocomplete="off">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-sm-4">
                            <div class="form-group">
                                <label class="control-label-custom">Start Date *</label>
                                <input type="date" name="date_started" class="form-control form-control-custom" value="<?php echo date('Y-m-d'); ?>" required>
                                <small style="color: #64748b; font-size: 10.5px;">For ID (<code>SICS-YYYYMMDD-A</code>)</small>
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div class="form-group">
                                <label class="control-label-custom">Email Address *</label>
                                <input type="email" name="email" class="form-control form-control-custom" placeholder="maria@store.com" required autocomplete="off">
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div class="form-group">
                                <label class="control-label-custom">Contact / Phone No.</label>
                                <input type="text" name="phone" class="form-control form-control-custom" placeholder="09171234567" autocomplete="off">
                            </div>
                        </div>
                    </div>

                    <!-- Section: Account & Role -->
                    <div style="font-size: 12px; font-weight: 800; color: #38bdf8; text-transform: uppercase; letter-spacing: 0.5px; margin: 12px 0 8px 0; border-bottom: 1px solid #1e293b; padding-bottom: 4px;">
                        <i class="fa fa-key"></i> 2. Account Credentials & Role
                    </div>

                    <div class="row">
                        <div class="col-sm-4">
                            <div class="form-group">
                                <label class="control-label-custom">Assigned Store Role *</label>
                                <select name="role" class="form-control form-control-custom" required>
                                    <?php 
                                    $assignable = get_assignable_roles($auth_role);
                                    foreach ($assignable as $r_code): 
                                    ?>
                                        <option value="<?php echo $r_code; ?>" <?php echo $r_code === 'CASHIER' ? 'selected' : ''; ?>><?php echo htmlspecialchars(get_role_display_name($r_code)); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div class="form-group">
                                <label class="control-label-custom">Password *</label>
                                <input type="password" name="password" class="form-control form-control-custom" placeholder="Min. 6 characters" required autocomplete="off">
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div class="form-group">
                                <label class="control-label-custom">Confirm Password *</label>
                                <input type="password" name="confirm_password" class="form-control form-control-custom" placeholder="Re-enter password" required autocomplete="off">
                            </div>
                        </div>
                    </div>

                    <!-- Section: Access Authorization -->
                    <div style="font-size: 12px; font-weight: 800; color: #38bdf8; text-transform: uppercase; letter-spacing: 0.5px; margin: 12px 0 8px 0; border-bottom: 1px solid #1e293b; padding-bottom: 4px;">
                        <i class="fa fa-shield"></i> 3. Access Authorization
                    </div>

                    <div style="background: rgba(15, 23, 42, 0.6); border: 1px solid #334155; border-radius: 6px; padding: 10px 14px; margin-bottom: 15px;">
                        <label style="color: #f8fafc; font-size: 13px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 8px; margin-bottom: 0;">
                            <input type="checkbox" name="pos_access" value="1" checked style="width: 16px; height: 16px; cursor: pointer;">
                            <span>Allow access to Supplier POS Terminal (<code>/supplier/pos.php</code>)</span>
                        </label>
                        <small style="color: #94a3b8; display: block; margin-top: 4px; margin-left: 24px; font-size: 11px;">
                            Uncheck if this employee only handles back-office/inventory tasks and should not operate the Point of Sale terminal.
                        </small>
                    </div>

                    <div style="background: #1e293b; border-left: 3px solid #0284c7; padding: 8px 12px; border-radius: 4px; margin-bottom: 15px; font-size: 11.5px; color: #94a3b8;">
                        <i class="fa fa-info-circle text-info"></i> <strong>Employee ID:</strong> A unique immutable ID (format <code>SICS-YYYYMMDD-A</code>) is automatically generated upon registration and assigned as the employee's login identifier.
                    </div>

                    <div style="margin-top: 15px;">
                        <button type="submit" name="form_register_user" class="btn btn-block btn-portal-success">
                            <i class="fa fa-check"></i> Register Employee & Generate ID
                        </button>
                    </div>

                    <div style="margin-top: 15px; text-align: center;">
                        <a href="?mode=user_login" style="color: #94a3b8; font-size: 13px;">
                            Back to <strong>Staff Login</strong>
                        </a>
                    </div>
                </form>
            <?php endif; ?>

        <?php endif; ?>

    </div>
</div>

<script src="js/jquery-2.2.3.min.js"></script>
<script src="js/bootstrap.min.js"></script>
</body>
</html>
