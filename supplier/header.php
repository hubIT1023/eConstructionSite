<?php
ob_start();
session_start();
include("inc/config.php");
include("inc/functions.php");
include("inc/CSRF_Protect.php");
$csrf = new CSRF_Protect();
$error_message = '';
$success_message = '';
$error_message1 = '';
$success_message1 = '';

// Check if the supplier user is logged in or not
if(!isset($_SESSION['supplier_user'])) {
	header('location: login.php');
	exit;
}
$supplier_id = (int)$_SESSION['supplier_user']['supplier_id'];
$cur_page = substr($_SERVER["SCRIPT_NAME"], strrpos($_SERVER["SCRIPT_NAME"], "/")+1);

// User Role Resolution
$user_role = isset($_SESSION['supplier_user']['role']) ? strtoupper(trim($_SESSION['supplier_user']['role'])) : 'USER';
$is_admin = in_array($user_role, ['ADMIN', 'SUPPLIER_ADMIN', 'SUPERADMIN']);
$is_pos_user = in_array($user_role, ['USER', 'POS_USER', 'CASHIER']);

// Route Authorization Barrier: Block POS Users from accessing Admin-only pages
$allowed_pos_pages = ['pos.php', 'profile-edit.php', 'logout.php'];
if ($is_pos_user && !in_array($cur_page, $allowed_pos_pages)) {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="utf-8">
        <title>Access Denied - POS User</title>
        <meta content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" name="viewport">
        <link rel="stylesheet" href="css/bootstrap.min.css">
        <link rel="stylesheet" href="css/font-awesome.min.css">
        <link rel="stylesheet" href="css/AdminLTE.min.css">
        <link rel="stylesheet" href="style.css">
    </head>
    <body style="background: #0f172a; color: #fff; font-family: 'Source Sans Pro', sans-serif; display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 20px;">
        <div style="background: #1e293b; border: 1px solid #334155; border-radius: 12px; padding: 40px; max-width: 500px; text-align: center; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.5);">
            <div style="font-size: 55px; color: #ef4444; margin-bottom: 15px;"><i class="fa fa-ban"></i></div>
            <h2 style="font-weight: 800; color: #f8fafc; margin-top: 0;">Access Denied</h2>
            <p style="color: #94a3b8; font-size: 15px; line-height: 1.5; margin-bottom: 25px;">
                Your account is configured with <strong>POS-Only Access</strong>. You do not have authorized privileges to access the Supplier Administration Panel.
            </p>
            <a href="pos.php" class="btn btn-success btn-lg" style="background-color: #10b981; border-color: #10b981; font-weight: bold; border-radius: 6px; padding: 12px 30px;">
                <i class="fa fa-calculator"></i> Return to POS Terminal
            </a>
            <div style="margin-top: 20px;">
                <a href="logout.php" style="color: #64748b; font-size: 13px;">Log out of current account</a>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<title><?php echo $is_admin ? 'Supplier Admin Panel' : 'Supplier POS Terminal'; ?> - eConstruction Supply</title>

	<meta content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" name="viewport">

	<link rel="stylesheet" href="css/bootstrap.min.css">
	<link rel="stylesheet" href="css/font-awesome.min.css">
	<link rel="stylesheet" href="css/ionicons.min.css">
	<link rel="stylesheet" href="css/datepicker3.css">
	<link rel="stylesheet" href="css/all.css">
	<link rel="stylesheet" href="css/select2.min.css">
	<link rel="stylesheet" href="css/dataTables.bootstrap.css">
	<link rel="stylesheet" href="css/jquery.fancybox.css">
	<link rel="stylesheet" href="css/AdminLTE.min.css">
	<link rel="stylesheet" href="css/_all-skins.min.css">
	<link rel="stylesheet" href="css/on-off-switch.css"/>
	<link rel="stylesheet" href="css/summernote.css">
	<link rel="stylesheet" href="style.css">

</head>

<body class="hold-transition fixed skin-blue sidebar-mini">

	<div class="wrapper">

		<header class="main-header">

			<a href="<?php echo $is_admin ? 'index.php' : 'pos.php'; ?>" class="logo">
				<span class="logo-lg">eCommerce PHP</span>
			</a>

			<nav class="navbar navbar-static-top">
				
				<a href="#" class="sidebar-toggle" data-toggle="offcanvas" role="button">
					<span class="sr-only">Toggle navigation</span>
				</a>

				<span style="float:left;line-height:50px;color:#fff;padding-left:15px;font-size:18px;">
                    <?php if ($is_admin): ?>
                        <i class="fa fa-shield" style="color: #38bdf8; margin-right: 4px;"></i> Supplier Admin Panel
                    <?php else: ?>
                        <i class="fa fa-calculator" style="color: #34d399; margin-right: 4px;"></i> POS Terminal
                    <?php endif; ?>
                </span>

                <!-- Top Bar User Information Area -->
				<div class="navbar-custom-menu">
					<ul class="nav navbar-nav">
						<li class="dropdown user user-menu">
							<a href="#" class="dropdown-toggle" data-toggle="dropdown">
								<i class="fa fa-user" style="color: white; float: left; margin-top: 3px; margin-right: 5px;"></i>
								<span class="hidden-xs"><?php echo htmlspecialchars($_SESSION['supplier_user']['full_name']); ?></span>
                                <?php if ($is_admin): ?>
                                    <span class="badge" style="background-color: #0284c7; font-size: 10px; margin-left: 4px;">ADMIN</span>
                                <?php else: ?>
                                    <span class="badge" style="background-color: #10b981; font-size: 10px; margin-left: 4px;">POS CASHIER</span>
                                <?php endif; ?>
							</a>
							<ul class="dropdown-menu">
								<li class="user-footer">
									<div>
										<a href="profile-edit.php" class="btn btn-default btn-flat">Edit Profile</a>
									</div>
									<div>
										<a href="logout.php" class="btn btn-default btn-flat">Log out</a>
									</div>
								</li>
							</ul>
						</li>
					</ul>
				</div>

			</nav>
		</header>

<!-- Side Bar Navigation -->
  		<aside class="main-sidebar">
    		<section class="sidebar">
      
      			<ul class="sidebar-menu">

                <?php if ($is_admin): ?>
                    <!-- ================= ADMIN NAVIGATION ================= -->
			        <li class="treeview <?php if($cur_page == 'index.php') {echo 'active';} ?>">
			          <a href="index.php">
			            <i class="fa fa-dashboard"></i> <span>Dashboard</span>
			          </a>
			        </li>

                    <li class="treeview <?php if($cur_page == 'pos.php') {echo 'active';} ?>">
                        <a href="pos.php">
                            <i class="fa fa-calculator" style="color: #60a5fa;"></i> <span style="font-weight: bold; color: #93c5fd;">Point of Sale (POS)</span>
                        </a>
                    </li>

                    <li class="treeview <?php if( ($cur_page == 'product.php') || ($cur_page == 'product-add.php') || ($cur_page == 'product-edit.php') ) {echo 'active';} ?>">
                        <a href="product.php">
                            <i class="fa fa-shopping-bag"></i> <span>Manage Products</span>
                        </a>
                    </li>

                    <li class="treeview <?php if( ($cur_page == 'order.php') ) {echo 'active';} ?>">
                        <a href="order.php">
                            <i class="fa fa-sticky-note"></i> <span>Receive Orders</span>
                        </a>
                    </li>

                    <li class="treeview <?php if( ($cur_page == 'paid-orders.php') ) {echo 'active';} ?>">
                        <a href="paid-orders.php">
                            <i class="fa fa-check-square-o" style="color: #4ade80;"></i> <span style="color: #bbf7d0; font-weight: bold;">Paid Orders</span>
                        </a>
                    </li>

                    <li class="treeview <?php if( ($cur_page == 'returns.php') ) {echo 'active';} ?>">
                        <a href="returns.php">
                            <i class="fa fa-undo" style="color: #f87171;"></i> <span style="color: #fca5a5; font-weight: bold;">Return History</span>
                        </a>
                    </li>

                    <li class="treeview <?php if( ($cur_page == 'quotes.php') || ($cur_page == 'quotes-reply.php') ) {echo 'active';} ?>">
                        <a href="quotes.php">
                            <i class="fa fa-file-text-o"></i> <span>RFQ Quotations</span>
                        </a>
                    </li>

                    <li class="treeview <?php if( ($cur_page == 'sales-report.php') ) {echo 'active';} ?>">
                        <a href="sales-report.php">
                            <i class="fa fa-line-chart" style="color: #fbbf24;"></i> <span style="color: #fef08a; font-weight: bold;">Sales Report</span>
                        </a>
                    </li>

                    <li class="treeview <?php if( ($cur_page == 'users.php') ) {echo 'active';} ?>">
                        <a href="users.php">
                            <i class="fa fa-users" style="color: #38bdf8;"></i> <span style="color: #bae6fd; font-weight: bold;">Manage POS Users</span>
                        </a>
                    </li>

                    <li class="treeview <?php if( ($cur_page == 'shipping-cost.php') || ($cur_page == 'shipping-cost-edit.php') ) {echo 'active';} ?>">
                        <a href="#">
                            <i class="fa fa-cogs"></i>
                            <span>Shop Settings</span>
                            <span class="pull-right-container">
								<i class="fa fa-angle-left pull-right"></i>
							</span>
                        </a>
                        <ul class="treeview-menu">
                            <li><a href="shipping-cost.php"><i class="fa fa-circle-o"></i> Shipping Cost</a></li>
                        </ul>
                    </li>

                    <li class="treeview <?php if( ($cur_page == 'store-settings.php') ) {echo 'active';} ?>">
                        <a href="store-settings.php">
                            <i class="fa fa-sliders"></i> <span>Store Settings</span>
                        </a>
                    </li>

                <?php else: ?>
                    <!-- ================= POS USER NAVIGATION ================= -->
                    <li class="treeview <?php if($cur_page == 'pos.php') {echo 'active';} ?>">
                        <a href="pos.php">
                            <i class="fa fa-calculator" style="color: #34d399;"></i> <span style="font-weight: bold; color: #a7f3d0; font-size: 15px;">POS Terminal</span>
                        </a>
                    </li>
                    <li class="treeview <?php if($cur_page == 'profile-edit.php') {echo 'active';} ?>">
                        <a href="profile-edit.php">
                            <i class="fa fa-user"></i> <span>My Profile</span>
                        </a>
                    </li>
                    <li>
                        <a href="logout.php">
                            <i class="fa fa-sign-out text-danger"></i> <span>Logout</span>
                        </a>
                    </li>
                <?php endif; ?>

      			</ul>
    		</section>
  		</aside>

  		<div class="content-wrapper">
