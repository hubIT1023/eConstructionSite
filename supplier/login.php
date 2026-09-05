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

// -------------------------------------------------------------
// 1. Process ADMIN LOGIN Form
// -------------------------------------------------------------
if (isset($_POST['form_admin_login'])) {
    $mode = 'admin_login';
    $email = isset($_POST['email']) ? trim(strip_tags($_POST['email'])) : '';
    $password = isset($_POST['password']) ? trim(strip_tags($_POST['password'])) : '';

    if (empty($email) || empty($password)) {
        $error_message = 'Email address and password are required.';
    } else {
        $stmt = $pdo->prepare("SELECT u.*, s.supplier_name, s.supplier_status, s.supplier_plan 
                               FROM tbl_supplier_user u
                               JOIN tbl_supplier s ON u.supplier_id = s.supplier_id
                               WHERE u.email = ?");
        $stmt->execute(array($email));
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $error_message = 'Invalid email or password.';
        } elseif ($user['status'] !== 'Active') {
            $error_message = 'Your user account is suspended or inactive. Please contact your administrator.';
        } elseif ($user['supplier_status'] !== 'Active') {
            $error_message = 'Your supplier organization account is currently ' . htmlspecialchars($user['supplier_status']) . '. Access is restricted.';
        } else {
            $role = strtoupper(trim($user['role']));
            if (!in_array($role, ['ADMIN', 'SUPPLIER_ADMIN', 'SUPERADMIN'])) {
                $error_message = 'This account does not have Supplier Admin privileges. Please use the POS User Login option.';
            } elseif (!verify_supplier_password($password, $user['password'])) {
                $error_message = 'Invalid email or password.';
            } else {
                // Successful Admin Login
                $_SESSION['supplier_user'] = [
                    'id' => $user['id'],
                    'supplier_id' => $user['supplier_id'],
                    'full_name' => $user['full_name'],
                    'email' => $user['email'],
                    'role' => 'ADMIN',
                    'status' => $user['status'],
                    'supplier_name' => $user['supplier_name'],
                    'supplier_plan' => $user['supplier_plan']
                ];
                header("Location: index.php");
                exit;
            }
        }
    }
}

// -------------------------------------------------------------
// 2. Process POS USER LOGIN Form
// -------------------------------------------------------------
if (isset($_POST['form_user_login'])) {
    $mode = 'user_login';
    $email = isset($_POST['email']) ? trim(strip_tags($_POST['email'])) : '';
    $password = isset($_POST['password']) ? trim(strip_tags($_POST['password'])) : '';

    if (empty($email) || empty($password)) {
        $error_message = 'Email address and password are required.';
    } else {
        $stmt = $pdo->prepare("SELECT u.*, s.supplier_name, s.supplier_status, s.supplier_plan 
                               FROM tbl_supplier_user u
                               JOIN tbl_supplier s ON u.supplier_id = s.supplier_id
                               WHERE u.email = ?");
        $stmt->execute(array($email));
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $error_message = 'Invalid email or password.';
        } elseif ($user['status'] !== 'Active') {
            $error_message = 'Your user account is suspended or inactive. Please contact your administrator.';
        } elseif ($user['supplier_status'] !== 'Active') {
            $error_message = 'Your supplier organization account is currently ' . htmlspecialchars($user['supplier_status']) . '. Access is restricted.';
        } else {
            $role = strtoupper(trim($user['role']));
            // If admin logs in through user login, allow or normalize, but POS user role must be USER
            $assigned_role = in_array($role, ['ADMIN', 'SUPPLIER_ADMIN', 'SUPERADMIN']) ? 'ADMIN' : 'USER';

            if (!verify_supplier_password($password, $user['password'])) {
                $error_message = 'Invalid email or password.';
            } else {
                // Successful POS User Login -> Redirects directly to POS Terminal
                $_SESSION['supplier_user'] = [
                    'id' => $user['id'],
                    'supplier_id' => $user['supplier_id'],
                    'full_name' => $user['full_name'],
                    'email' => $user['email'],
                    'role' => $assigned_role,
                    'status' => $user['status'],
                    'supplier_name' => $user['supplier_name'],
                    'supplier_plan' => $user['supplier_plan']
                ];
                header("Location: pos.php");
                exit;
            }
        }
    }
}

// -------------------------------------------------------------
// 3. Process POS USER REGISTRATION Form
// -------------------------------------------------------------
if (isset($_POST['form_register_user'])) {
    $mode = 'register';
    $full_name = isset($_POST['full_name']) ? trim(strip_tags($_POST['full_name'])) : '';
    $email = isset($_POST['email']) ? trim(strip_tags($_POST['email'])) : '';
    $password = isset($_POST['password']) ? trim(strip_tags($_POST['password'])) : '';
    $confirm_password = isset($_POST['confirm_password']) ? trim(strip_tags($_POST['confirm_password'])) : '';
    $store_code = isset($_POST['store_code']) ? trim(strip_tags($_POST['store_code'])) : '';

    if (empty($full_name) || empty($email) || empty($password) || empty($confirm_password) || empty($store_code)) {
        $error_message = 'All fields including Store Code are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Please provide a valid email address.';
    } elseif ($password !== $confirm_password) {
        $error_message = 'Passwords do not match. Please re-enter.';
    } elseif (strlen($password) < 6) {
        $error_message = 'Password must be at least 6 characters long.';
    } else {
        // Step 1: Identify Tenant / Supplier by store code (slug, email, or id)
        $stmt_s = $pdo->prepare("SELECT * FROM tbl_supplier WHERE supplier_slug = ? OR supplier_email = ? OR CAST(supplier_id AS TEXT) = ?");
        $stmt_s->execute(array($store_code, $store_code, $store_code));
        $supplier = $stmt_s->fetch(PDO::FETCH_ASSOC);

        if (!$supplier) {
            $error_message = 'Invalid Store Code / Identifier. Please check with your Supplier Store Administrator.';
        } elseif ($supplier['supplier_status'] !== 'Active') {
            $error_message = 'This supplier store account is currently ' . htmlspecialchars($supplier['supplier_status']) . '. New registrations are disabled.';
        } else {
            $target_supplier_id = (int)$supplier['supplier_id'];

            // Step 2: Check Tenant POS User Quota Limits
            $stats = get_tenant_pos_user_stats($pdo, $target_supplier_id);
            if ($stats['is_limit_reached']) {
                $error_message = '<strong>POS User Limit Reached:</strong> Your store\'s ' . htmlspecialchars($stats['plan_name']) . 
                                 ' Plan allows a maximum of ' . $stats['max_pos_users'] . ' POS Users (' . $stats['current_pos_users'] . '/' . $stats['max_pos_users'] . ' slots used).<br>' .
                                 'Please contact your SaaS Administrator to increase your user allowance or upgrade your plan.';
            } else {
                // Step 3: Check Email Uniqueness
                $stmt_chk = $pdo->prepare("SELECT id FROM tbl_supplier_user WHERE email = ?");
                $stmt_chk->execute(array($email));
                if ($stmt_chk->rowCount() > 0) {
                    $error_message = 'The email address <strong>' . htmlspecialchars($email) . '</strong> is already registered. Please log in.';
                } else {
                    // Step 4: Create User with Transaction Safety
                    try {
                        $pdo->beginTransaction();

                        // Re-verify limit inside transaction with FOR UPDATE
                        $stmt_lock = $pdo->prepare("SELECT COUNT(*) as total FROM tbl_supplier_user WHERE supplier_id = ? AND UPPER(role) IN ('USER', 'POS_USER', 'CASHIER')");
                        $stmt_lock->execute(array($target_supplier_id));
                        $row_lock = $stmt_lock->fetch(PDO::FETCH_ASSOC);
                        $current_count = (int)$row_lock['total'];

                        if ($current_count >= $stats['max_pos_users']) {
                            $pdo->rollBack();
                            $error_message = 'POS User quota reached for this store (' . $stats['max_pos_users'] . '/' . $stats['max_pos_users'] . '). Registration blocked.';
                        } else {
                            $hashed_pass = hash_supplier_password($password);
                            $stmt_ins = $pdo->prepare("INSERT INTO tbl_supplier_user (supplier_id, full_name, email, password, role, status) VALUES (?, ?, ?, ?, 'USER', 'Active')");
                            $stmt_ins->execute(array($target_supplier_id, $full_name, $email, $hashed_pass));
                            $new_user_id = $pdo->lastInsertId();

                            $pdo->commit();

                            $success_message = 'Account created successfully for <strong>' . htmlspecialchars($supplier['supplier_name']) . '</strong>! You can now log in with your credentials.';
                            $mode = 'user_login';
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
                    <label class="control-label-custom">Email Address</label>
                    <input type="email" name="email" class="form-control form-control-custom" placeholder="admin@supplier.com" required autocomplete="off" autofocus>
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
                        Need POS Register access? Switch to <strong>POS User Login</strong> <i class="fa fa-arrow-right"></i>
                    </a>
                </div>
            </form>

        <!-- ========================================================= -->
        <!-- VIEW 3: POS USER LOGIN FORM -->
        <!-- ========================================================= -->
        <?php elseif ($mode === 'user_login'): ?>
            <div class="form-title-area">
                <div>
                    <h2><i class="fa fa-calculator" style="color: #34d399; margin-right: 6px;"></i> POS User Login</h2>
                    <span style="font-size: 12px; color: #94a3b8;">Enter credentials for POS Register terminal</span>
                </div>
                <a href="?mode=select" class="btn-portal-back"><i class="fa fa-arrow-left"></i> Back</a>
            </div>

            <form action="" method="post">
                <?php $csrf->echoInputField(); ?>
                <input type="hidden" name="mode" value="user_login">

                <div class="form-group">
                    <label class="control-label-custom">Email Address</label>
                    <input type="email" name="email" class="form-control form-control-custom" placeholder="cashier@supplier.com" required autocomplete="off" autofocus>
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
                        <i class="fa fa-user-plus"></i> Don't have an account? <strong>Register</strong>
                    </a>
                    <a href="?mode=admin_login" style="color: #94a3b8; font-size: 13px;">
                        Supplier Admin? <strong>Admin Login</strong>
                    </a>
                </div>
            </form>

        <!-- ========================================================= -->
        <!-- VIEW 4: POS USER REGISTRATION FORM -->
        <!-- ========================================================= -->
        <?php elseif ($mode === 'register'): ?>
            <div class="form-title-area">
                <div>
                    <h2><i class="fa fa-user-plus" style="color: #34d399; margin-right: 6px;"></i> Create POS User Account</h2>
                    <span style="font-size: 12px; color: #94a3b8;">Register as a Cashier / POS Operator</span>
                </div>
                <a href="?mode=user_login" class="btn-portal-back"><i class="fa fa-arrow-left"></i> Back to Login</a>
            </div>

            <form action="" method="post">
                <?php $csrf->echoInputField(); ?>
                <input type="hidden" name="mode" value="register">

                <div class="form-group">
                    <label class="control-label-custom">Full Name *</label>
                    <input type="text" name="full_name" class="form-control form-control-custom" placeholder="e.g. Juan Dela Cruz" required autocomplete="off" autofocus>
                </div>

                <div class="form-group">
                    <label class="control-label-custom">Email Address *</label>
                    <input type="email" name="email" class="form-control form-control-custom" placeholder="juan@store.com" required autocomplete="off">
                </div>

                <div class="form-group">
                    <label class="control-label-custom">
                        Store Code / Identifier * 
                        <span style="font-size: 11px; color: #94a3b8; font-weight: normal;">(Provided by your Supplier Store Admin)</span>
                    </label>
                    <input type="text" name="store_code" class="form-control form-control-custom" placeholder="e.g. sam-inri-construction-supply or store email" required autocomplete="off">
                    <small style="color: #64748b; font-size: 11px;"><i class="fa fa-info-circle"></i> Binds your account strictly to your store's tenant allocation.</small>
                </div>

                <div class="row">
                    <div class="col-sm-6">
                        <div class="form-group">
                            <label class="control-label-custom">Password *</label>
                            <input type="password" name="password" class="form-control form-control-custom" placeholder="Min. 6 characters" required autocomplete="off">
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="form-group">
                            <label class="control-label-custom">Confirm Password *</label>
                            <input type="password" name="confirm_password" class="form-control form-control-custom" placeholder="Re-enter password" required autocomplete="off">
                        </div>
                    </div>
                </div>

                <div style="margin-top: 20px;">
                    <button type="submit" name="form_register_user" class="btn btn-block btn-portal-success">
                        <i class="fa fa-check" style="margin-right: 6px;"></i> Complete POS User Registration
                    </button>
                </div>

                <div style="margin-top: 15px; text-align: center;">
                    <a href="?mode=user_login" style="color: #94a3b8; font-size: 13px;">
                        Already registered? <strong>Log in here</strong>
                    </a>
                </div>
            </form>

        <?php endif; ?>

    </div>
</div>

<script src="js/jquery-2.2.3.min.js"></script>
<script src="js/bootstrap.min.js"></script>
</body>
</html>
