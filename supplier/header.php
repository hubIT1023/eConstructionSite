<?php
ob_start();
session_start();
include("inc/config.php");
include("inc/functions.php");
ensure_supplier_user_schema($pdo);
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

// User Role & POS Access Resolution
$user_role_raw = isset($_SESSION['supplier_user']['role']) ? $_SESSION['supplier_user']['role'] : 'USER';
$user_role = normalize_supplier_role($user_role_raw);
$is_admin = is_admin_or_manager_role($user_role);
$is_approver = is_supplier_approver();
$is_pos_user = is_cashier_or_operator_role($user_role);
$user_has_pos = has_pos_access($_SESSION['supplier_user']);

// Query pending created Purchase Orders for current tenant
$sidebar_pending_pos = [];
if (isset($supplier_id) && $supplier_id > 0) {
    try {
        $stmt_sidebar_po = $pdo->prepare("
            SELECT payment_id, payment_date, paid_amount, customer_name, customer_email, payment_status, txnid
            FROM tbl_payment 
            WHERE supplier_id = ? 
              AND (payment_status = 'Awaiting for Payment' OR payment_status = 'Pending' OR payment_status = 'UNPAID')
              AND payment_status != 'Paid'
              AND payment_status != 'Completed'
            ORDER BY id DESC
            LIMIT 50
        ");
        $stmt_sidebar_po->execute([$supplier_id]);
        $sidebar_pending_pos = $stmt_sidebar_po->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $sidebar_pending_pos = [];
    }
}


// POS Terminal Route Barrier for Non-POS Authorized Users
if ($cur_page === 'pos.php' && !$user_has_pos) {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="utf-8">
        <title>Access Denied - POS Terminal Not Authorized</title>
        <meta content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" name="viewport">
        <link rel="stylesheet" href="css/bootstrap.min.css">
        <link rel="stylesheet" href="css/font-awesome.min.css">
        <link rel="stylesheet" href="css/AdminLTE.min.css">
        <link rel="stylesheet" href="style.css">
    </head>
    <body style="background: #0f172a; color: #fff; font-family: 'Source Sans Pro', sans-serif; display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 20px;">
        <div style="background: #1e293b; border: 1px solid #334155; border-radius: 12px; padding: 40px; max-width: 500px; text-align: center; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.5);">
            <div style="font-size: 55px; color: #f59e0b; margin-bottom: 15px;"><i class="fa fa-lock"></i></div>
            <h2 style="font-weight: 800; color: #f8fafc; margin-top: 0;">POS Terminal Access Disabled</h2>
            <p style="color: #94a3b8; font-size: 14.5px; line-height: 1.5; margin-bottom: 25px;">
                Your staff account (<strong><?php echo htmlspecialchars($_SESSION['supplier_user']['full_name']); ?></strong>) does not currently have permission to operate the Point of Sale terminal. Please contact your Store Administrator or Manager to request POS authorization.
            </p>
            <div style="display: flex; gap: 10px; justify-content: center;">
                <a href="user-manual.php" class="btn btn-default" style="font-weight: 600;">
                    <i class="fa fa-book"></i> User Manual
                </a>
                <a href="logout.php" class="btn btn-danger" style="font-weight: 600;">
                    <i class="fa fa-sign-out"></i> Log Out
                </a>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Route Authorization Barrier: Block unauthorized users from admin-only pages
$allowed_pos_pages = ['profile-edit.php', 'logout.php', 'user-manual.php'];
if ($user_has_pos) {
    $allowed_pos_pages[] = 'pos.php';
}
if ($user_role === 'SUPERVISOR') {
    $allowed_pos_pages[] = 'discount-approvals.php';
}
if (can_register_staff($user_role)) {
    $allowed_pos_pages[] = 'users.php';
}
if (is_encoder_role($user_role)) {
    $allowed_pos_pages[] = 'product.php';
    $allowed_pos_pages[] = 'product-add.php';
    $allowed_pos_pages[] = 'product-edit.php';
}
if (is_order_processing_or_operator_role($user_role) || is_encoder_role($user_role) || $user_role === 'SUPERVISOR') {
    $allowed_pos_pages[] = 'checkout.php';
    $allowed_pos_pages[] = 'po-created.php';
}
// Allow Cashiers and Supervisors to access order management pages (Order Processing Staff & Operators are restricted)
if ($user_role === 'CASHIER' || $user_role === 'SUPERVISOR') {
    $allowed_pos_pages[] = 'order.php';
    $allowed_pos_pages[] = 'order-change-status.php';
    $allowed_pos_pages[] = 'shipping-change-status.php';
    $allowed_pos_pages[] = 'paid-orders.php';
    $allowed_pos_pages[] = 'returns.php';
}
if (!$is_admin && !in_array($cur_page, $allowed_pos_pages)) {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="utf-8">
        <title>Access Denied - Supplier Staff</title>
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
                Your account is configured with <strong>Staff Access</strong>. You do not have authorized privileges to access the Supplier Administration Panel.
            </p>
            <?php if ($user_has_pos): ?>
                <a href="pos.php" class="btn btn-success btn-lg" style="background-color: #10b981; border-color: #10b981; font-weight: bold; border-radius: 6px; padding: 12px 30px;">
                    <i class="fa fa-calculator"></i> Return to POS Terminal
                </a>
            <?php else: ?>
                <a href="user-manual.php" class="btn btn-primary btn-lg" style="font-weight: bold; border-radius: 6px; padding: 12px 30px;">
                    <i class="fa fa-book"></i> View User Manual
                </a>
            <?php endif; ?>
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
                                <?php
                                $role_badge_bg = '#10b981';
                                if ($user_role === 'ADMIN' || $user_role === 'MANAGER') {
                                    $role_badge_bg = '#0284c7';
                                } elseif ($user_role === 'SUPERVISOR') {
                                    $role_badge_bg = '#9333ea';
                                } elseif ($user_role === 'ENCODER') {
                                    $role_badge_bg = '#d97706';
                                } elseif ($user_role === 'ORDER_PROCESSING') {
                                    $role_badge_bg = '#2563eb';
                                } elseif ($user_role === 'OPERATOR') {
                                    $role_badge_bg = '#0d9488';
                                }
                                ?>
                                <span class="badge" style="background-color: <?php echo $role_badge_bg; ?>; font-size: 10px; margin-left: 4px;"><?php echo htmlspecialchars(get_role_display_name($user_role)); ?></span>
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

                    <li class="treeview <?php if($cur_page == 'po-created.php') {echo 'active';} ?>">
                        <a href="po-created.php">
                            <i class="fa fa-file-text-o" style="color: #f59e0b;"></i> <span style="color: #fef08a; font-weight: bold;">PO Created</span>
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

                    <li class="treeview <?php if( ($cur_page == 'discount-approvals.php') ) {echo 'active';} ?>">
                        <a href="discount-approvals.php">
                            <i class="fa fa-check-square-o" style="color: #a855f7;"></i> <span style="color: #e9d5ff; font-weight: bold;">Discount Approvals</span>
                        </a>
                    </li>

                    <li class="treeview <?php if( ($cur_page == 'return-approvals.php') ) {echo 'active';} ?>">
                        <a href="return-approvals.php">
                            <i class="fa fa-check-circle" style="color: #f43f5e;"></i> <span style="color: #fecdd3; font-weight: bold;">Return Approval</span>
                        </a>
                    </li>

                    <li class="treeview <?php if( ($cur_page == 'shipping-cost.php') || ($cur_page == 'shipping-cost-edit.php') || ($cur_page == 'discount-settings.php') ) {echo 'active';} ?>">
                        <a href="#">
                            <i class="fa fa-cogs"></i>
                            <span>Shop Settings</span>
                            <span class="pull-right-container">
								<i class="fa fa-angle-left pull-right"></i>
							</span>
                        </a>
                        <ul class="treeview-menu">
                            <li class="<?php if($cur_page == 'shipping-cost.php') {echo 'active';} ?>"><a href="shipping-cost.php"><i class="fa fa-circle-o"></i> Shipping Cost</a></li>
                            <li class="<?php if($cur_page == 'discount-settings.php') {echo 'active';} ?>"><a href="discount-settings.php"><i class="fa fa-percent"></i> Discount Settings</a></li>
                        </ul>
                    </li>

                    <li class="treeview <?php if( ($cur_page == 'store-settings.php') ) {echo 'active';} ?>">
                        <a href="store-settings.php">
                            <i class="fa fa-sliders"></i> <span>Store Settings</span>
                        </a>
                    </li>

                    <li class="treeview <?php if( ($cur_page == 'user-manual.php') ) {echo 'active';} ?>">
                        <a href="user-manual.php">
                            <i class="fa fa-book" style="color: #38bdf8;"></i> <span style="color: #bae6fd; font-weight: bold;">Users' Manual</span>
                        </a>
                    </li>

                <?php else: ?>
                    <!-- ================= POS USER NAVIGATION ================= -->
                    <li class="treeview <?php if($cur_page == 'pos.php') {echo 'active';} ?>">
                        <a href="pos.php">
                            <i class="fa fa-calculator" style="color: #34d399;"></i> <span style="font-weight: bold; color: #a7f3d0; font-size: 15px;">POS Terminal</span>
                        </a>
                    </li>
                    
                    <?php if ($user_role === 'CASHIER' || $user_role === 'SUPERVISOR'): ?>
                    <li class="treeview <?php if($cur_page == 'order.php') {echo 'active';} ?>">
                        <a href="order.php">
                            <i class="fa fa-sticky-note" style="color: #38bdf8;"></i> <span style="color: #bae6fd; font-weight: bold;">Receive Orders</span>
                        </a>
                    </li>
                    <li class="treeview <?php if($cur_page == 'paid-orders.php') {echo 'active';} ?>">
                        <a href="paid-orders.php">
                            <i class="fa fa-check-square-o" style="color: #4ade80;"></i> <span style="color: #bbf7d0; font-weight: bold;">Paid Orders</span>
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php if (is_encoder_role($user_role)): ?>
                    <li class="treeview <?php if( ($cur_page == 'product.php') || ($cur_page == 'product-add.php') || ($cur_page == 'product-edit.php') ) {echo 'active';} ?>">
                        <a href="product.php">
                            <i class="fa fa-shopping-bag"></i> <span>Manage Products</span>
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php if ($user_role === 'SUPERVISOR'): ?>
                    <li class="treeview <?php if($cur_page == 'discount-approvals.php') {echo 'active';} ?>">
                        <a href="discount-approvals.php">
                            <i class="fa fa-check-square-o" style="color: #a855f7;"></i> <span style="color: #e9d5ff; font-weight: bold;">Discount Approvals</span>
                        </a>
                    </li>
                    <li class="treeview <?php if($cur_page == 'return-approvals.php') {echo 'active';} ?>">
                        <a href="return-approvals.php">
                            <i class="fa fa-check-circle" style="color: #f43f5e;"></i> <span style="color: #fecdd3; font-weight: bold;">Return Approval</span>
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php if (can_register_staff($user_role)): ?>
                    <li class="treeview <?php if($cur_page == 'users.php') {echo 'active';} ?>">
                        <a href="users.php">
                            <i class="fa fa-users" style="color: #38bdf8;"></i> <span style="color: #bae6fd; font-weight: bold;">Manage POS Users</span>
                        </a>
                    </li>
                    <?php endif; ?>
                    <li class="treeview <?php if($cur_page == 'user-manual.php') {echo 'active';} ?>">
                        <a href="user-manual.php">
                            <i class="fa fa-book" style="color: #38bdf8;"></i> <span style="color: #bae6fd; font-weight: bold;">Users' Manual</span>
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

                <?php if (isset($_SESSION['supplier_user'])): ?>
                    <!-- CREATED PURCHASE ORDERS (ORDER PROCESSING QUEUE) -->
                    <li class="header" style="color: #f59e0b; font-weight: 800; font-size: 11px; text-transform: uppercase; letter-spacing: 0.6px; padding: 14px 15px 8px 15px; border-top: 1px solid rgba(255,255,255,0.08); margin-top: 10px; background: rgba(0,0,0,0.25);">
                        <i class="fa fa-file-text-o" style="margin-right: 6px; color: #fbbf24;"></i> CREATED PURCHASE ORDERS
                        <span class="pull-right badge bg-yellow" id="sidebar_po_badge_count" style="font-size: 10px; padding: 2px 6px; font-weight: 700;"><?= count($sidebar_pending_pos); ?></span>
                    </li>
                    <li style="padding: 6px 12px 15px 12px;">
                        <?php if (empty($sidebar_pending_pos)): ?>
                            <div style="background: rgba(15,23,42,0.6); border: 1px dashed #334155; border-radius: 8px; padding: 12px 10px; text-align: center; color: #64748b; font-size: 12px;">
                                <i class="fa fa-check-circle-o" style="font-size: 18px; color: #10b981; display: block; margin-bottom: 4px;"></i>
                                No pending POs
                            </div>
                        <?php else: ?>
                            <div class="sidebar-po-list-container" style="max-height: 380px; overflow-y: auto; display: flex; flex-direction: column; gap: 8px; padding-right: 4px;">
                                <?php foreach ($sidebar_pending_pos as $po_item): 
                                    $po_num = htmlspecialchars($po_item['payment_id'] ?? $po_item['txnid']);
                                    $po_date_raw = $po_item['payment_date'] ?? 'now';
                                    $po_formatted_date = date('d/m/Y', strtotime($po_date_raw));
                                ?>
                                    <button type="button" 
                                            class="btn btn-sidebar-po" 
                                            onclick="openSidebarPODetailModal('<?= addslashes($po_num); ?>')"
                                            style="width: 100%; text-align: left; background: #1e293b; border: 1.5px solid #334155; border-radius: 8px; padding: 8px 12px; color: #f8fafc; transition: all 0.2s ease; cursor: pointer; display: block; box-shadow: 0 2px 4px rgba(0,0,0,0.25);"
                                            onmouseover="this.style.background='#334155'; this.style.borderColor='#f59e0b'; this.style.transform='translateY(-1px)';" 
                                            onmouseout="this.style.background='#1e293b'; this.style.borderColor='#334155'; this.style.transform='translateY(0)';"
                                            title="Click to view read-only PO details">
                                        <div style="font-family: monospace, Consolas, sans-serif; font-size: 12.5px; font-weight: 700; color: #fef08a; letter-spacing: 0.5px; line-height: 1.3; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                            <i class="fa fa-file-text-o" style="color: #f59e0b; margin-right: 5px; font-size: 11px;"></i><?= $po_num; ?>
                                        </div>
                                        <div style="font-size: 11.5px; color: #94a3b8; font-weight: 600; margin-top: 3px; display: flex; justify-content: space-between; align-items: center;">
                                            <span><i class="fa fa-calendar-o" style="margin-right: 4px; font-size: 10px;"></i><?= $po_formatted_date; ?></span>
                                            <span class="label label-warning" style="font-size: 9px; padding: 1px 5px; border-radius: 4px; background-color: #d97706 !important;">UNPAID</span>
                                        </div>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </li>
                <?php endif; ?>

      			</ul>
    		</section>
  		</aside>

  		<div class="content-wrapper">

<!-- ==========================================
     READ-ONLY PO DETAILS MODAL & REPRINT SYSTEM
     ========================================== -->
<div class="modal fade" id="modalSidebarPODetails" tabindex="-1" role="dialog" aria-labelledby="modalSidebarPOLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document" style="max-width: 850px; margin: 30px auto;">
        <div class="modal-content" style="border-radius: 12px; overflow: hidden; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5); border: 1px solid #334155;">
            <!-- Modal Header -->
            <div class="modal-header" style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color: #fff; padding: 18px 24px; border-bottom: 2px solid #f59e0b; display: flex; justify-content: space-between; align-items: center;">
                <div style="display: flex; align-items: center; gap: 12px;">
                    <div style="background: rgba(245, 158, 11, 0.2); border: 1px solid #f59e0b; border-radius: 8px; width: 42px; height: 42px; display: flex; align-items: center; justify-content: center; color: #f59e0b; font-size: 20px;">
                        <i class="fa fa-file-text-o"></i>
                    </div>
                    <div>
                        <h4 class="modal-title" id="modalSidebarPOLabel" style="font-weight: 800; font-size: 18px; margin: 0; color: #f8fafc; letter-spacing: 0.3px;">
                            Purchase Order Details
                        </h4>
                        <div style="font-size: 12px; color: #94a3b8; margin-top: 2px;">
                            <span id="modal_po_num_subtitle" style="font-family: monospace; font-weight: 700; color: #fef08a;">PO-XXXXX</span>
                            &nbsp;•&nbsp; <span id="modal_po_date_subtitle">--/--/----</span>
                        </div>
                    </div>
                </div>
                <div style="display: flex; align-items: center; gap: 10px;">
                    <span class="badge" style="background: #f59e0b; color: #000; font-weight: 800; font-size: 11px; padding: 5px 10px; border-radius: 20px; text-transform: uppercase; letter-spacing: 0.5px;">
                        <i class="fa fa-lock"></i> Read-Only Mode
                    </span>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="color: #fff; opacity: 0.8; font-size: 26px; text-shadow: none; line-height: 1; padding: 0 5px;">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            </div>

            <!-- Modal Body -->
            <div class="modal-body" style="background: #f8fafc; padding: 24px; max-height: calc(85vh - 160px); overflow-y: auto;">
                <!-- Loading State -->
                <div id="modal_po_loading" style="text-align: center; padding: 40px 20px; display: none;">
                    <i class="fa fa-circle-o-notch fa-spin fa-3x" style="color: #f59e0b;"></i>
                    <p style="margin-top: 15px; color: #64748b; font-weight: 600;">Loading Purchase Order details...</p>
                </div>

                <!-- Error State -->
                <div id="modal_po_error" class="alert alert-danger" style="display: none; border-radius: 8px;">
                    <i class="fa fa-exclamation-triangle"></i> <span id="modal_po_error_msg">Failed to load PO details.</span>
                </div>

                <!-- PO Voucher Content Container -->
                <div id="modal_po_content">
                    <!-- Status Banner -->
                    <div style="background: #fef3c7; border: 1.5px solid #f59e0b; border-radius: 8px; padding: 12px 18px; margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between;">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <i class="fa fa-clock-o" style="font-size: 22px; color: #d97706;"></i>
                            <div>
                                <div style="font-weight: 800; color: #92400e; font-size: 14px; text-transform: uppercase; letter-spacing: 0.5px;">
                                    PAYMENT STATUS: <span id="modal_po_status_text">Awaiting for Payment (UNPAID)</span>
                                </div>
                                <div style="font-size: 12px; color: #b45309;">
                                    This purchase order is currently queued for cashier payment processing.
                                </div>
                            </div>
                        </div>
                        <span class="label label-warning" style="font-size: 12px; padding: 5px 12px; font-weight: 700; background-color: #d97706 !important;">
                            PENDING PAYMENT
                        </span>
                    </div>

                    <!-- Voucher Paper Card -->
                    <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 24px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">
                        <!-- Store & PO Top Info -->
                        <div class="row" style="border-bottom: 2px dashed #cbd5e1; padding-bottom: 18px; margin-bottom: 18px;">
                            <div class="col-sm-6">
                                <h4 id="modal_store_name" style="font-weight: 800; color: #0f172a; margin: 0 0 6px 0; font-size: 18px;">E-Construction Supply Store</h4>
                                <div id="modal_store_address" style="color: #64748b; font-size: 12.5px; line-height: 1.4;">--</div>
                                <div id="modal_store_contact" style="color: #64748b; font-size: 12.5px; margin-top: 3px;">--</div>
                            </div>
                            <div class="col-sm-6 text-right" style="text-align: right;">
                                <div style="font-size: 12px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px;">PURCHASE ORDER VOUCHER</div>
                                <div id="modal_po_number_display" style="font-family: monospace; font-size: 20px; font-weight: 800; color: #1e293b; margin: 3px 0;">PO-XXXXXXXX</div>
                                <div style="font-size: 13px; color: #475569;">Date Created: <strong id="modal_po_datetime_display">--/--/----</strong></div>
                            </div>
                        </div>

                        <!-- Customer Info -->
                        <div class="row" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px 16px; margin: 0 0 20px 0;">
                            <div class="col-sm-6" style="padding-left: 0;">
                                <div style="font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px;">Customer Name</div>
                                <div id="modal_cust_name" style="font-weight: 700; color: #0f172a; font-size: 14.5px;">Walk-in Customer</div>
                                <div id="modal_cust_contact" style="color: #64748b; font-size: 12px; margin-top: 2px;">--</div>
                            </div>
                            <div class="col-sm-6" style="padding-right: 0;">
                                <div style="font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px;">Delivery / Service Location</div>
                                <div id="modal_cust_address" style="color: #334155; font-size: 12.5px; line-height: 1.4;">Store Counter Pickup</div>
                            </div>
                        </div>

                        <!-- Items Table -->
                        <div class="table-responsive" style="margin-bottom: 20px;">
                            <table class="table table-bordered table-striped" style="margin-bottom: 0; background: #fff; font-size: 13px;">
                                <thead style="background: #0f172a; color: #fff;">
                                    <tr>
                                        <th style="width: 50px; text-align: center; vertical-align: middle;">#</th>
                                        <th style="vertical-align: middle;">Product Item & Specifications</th>
                                        <th style="width: 80px; text-align: center; vertical-align: middle;">Qty</th>
                                        <th style="width: 120px; text-align: right; vertical-align: middle;">Unit Price</th>
                                        <th style="width: 130px; text-align: right; vertical-align: middle;">Total (₱)</th>
                                    </tr>
                                </thead>
                                <tbody id="modal_po_items_tbody">
                                    <!-- Populated dynamically via JS -->
                                </tbody>
                            </table>
                        </div>

                        <!-- Totals Breakdown -->
                        <div class="row">
                            <div class="col-sm-6">
                                <div style="background: #f1f5f9; border-left: 4px solid #3b82f6; padding: 10px 14px; border-radius: 4px; font-size: 12px; color: #475569;">
                                    <strong><i class="fa fa-info-circle"></i> Note for Order Processing:</strong><br>
                                    Review order items carefully. Present this PO voucher or PO Number to the Cashier for payment collection and official receipt issuance.
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px 18px;">
                                    <div style="display: flex; justify-content: space-between; font-size: 13px; color: #64748b; margin-bottom: 6px;">
                                        <span>Gross Subtotal:</span>
                                        <span id="modal_summary_subtotal" style="font-weight: 600; color: #1e293b;">₱0.00</span>
                                    </div>
                                    <div id="modal_summary_discount_row" style="display: flex; justify-content: space-between; font-size: 13px; color: #dc2626; margin-bottom: 6px; display: none;">
                                        <span>Item Discount(s):</span>
                                        <span id="modal_summary_discount" style="font-weight: 600;">-₱0.00</span>
                                    </div>
                                    <div style="display: flex; justify-content: space-between; font-size: 17px; font-weight: 800; color: #0f172a; border-top: 2px dashed #cbd5e1; padding-top: 10px; margin-top: 6px;">
                                        <span>Total Amount Due:</span>
                                        <span id="modal_summary_total" style="color: #059669; font-size: 20px;">₱0.00</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="modal-footer" style="background: #0f172a; padding: 14px 24px; border-top: 1px solid #334155; display: flex; justify-content: space-between; align-items: center;">
                <div style="color: #94a3b8; font-size: 12.5px; display: flex; align-items: center; gap: 6px;">
                    <i class="fa fa-shield" style="color: #f59e0b;"></i>
                    <span>Read-only view • This PO will automatically disappear from the sidebar once marked Paid.</span>
                </div>
                <div style="display: flex; gap: 10px;">
                    <button type="button" class="btn btn-default" data-dismiss="modal" style="font-weight: 600; padding: 8px 18px; border-radius: 6px;">
                        <i class="fa fa-times"></i> Close
                    </button>
                    <button type="button" class="btn btn-warning" onclick="reprintSidebarPOVoucher()" style="font-weight: 800; padding: 8px 22px; border-radius: 6px; background-color: #f59e0b; border-color: #d97706; color: #000; box-shadow: 0 4px 6px rgba(0,0,0,0.2);">
                        <i class="fa fa-print"></i> 🖨️ Reprint PO
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Global PO Details Cache
var currentSidebarPOData = null;

function openSidebarPODetailModal(paymentId) {
    if (!paymentId) return;

    $('#modalSidebarPODetails').modal('show');
    $('#modal_po_loading').show();
    $('#modal_po_content').hide();
    $('#modal_po_error').hide();

    // Fetch PO details via AJAX
    $.ajax({
        url: 'get-po-details-ajax.php',
        type: 'GET',
        data: { payment_id: paymentId },
        dataType: 'json',
        success: function(res) {
            $('#modal_po_loading').hide();
            if (res && res.success) {
                currentSidebarPOData = res;
                populateSidebarPOModal(res);
                $('#modal_po_content').fadeIn(200);
            } else {
                $('#modal_po_error_msg').text(res && res.message ? res.message : 'Error loading Purchase Order data.');
                $('#modal_po_error').show();
            }
        },
        error: function(xhr, status, err) {
            $('#modal_po_loading').hide();
            $('#modal_po_error_msg').text('Network or server error while fetching PO details. Status: ' + status);
            $('#modal_po_error').show();
        }
    });
}

function populateSidebarPOModal(data) {
    // Header & Subtitles
    $('#modal_po_num_subtitle').text(data.po_number || 'PO-XXXXX');
    $('#modal_po_date_subtitle').text(data.date || '');
    $('#modal_po_status_text').text(data.status + ' (UNPAID)');
    $('#modal_po_number_display').text(data.po_number || '');
    $('#modal_po_datetime_display').text(data.date || '');

    // Store Info
    if (data.supplier) {
        $('#modal_store_name').text(data.supplier.store_name || 'E-Construction Supply Store');
        $('#modal_store_address').text(data.supplier.address || '');
        var contactText = '';
        if (data.supplier.phone) contactText += 'Phone: ' + data.supplier.phone + ' ';
        if (data.supplier.email) contactText += '• Email: ' + data.supplier.email;
        $('#modal_store_contact').text(contactText || '');
    }

    // Customer Info
    if (data.customer) {
        $('#modal_cust_name').text(data.customer.name || 'Walk-in Customer');
        var custContact = '';
        if (data.customer.phone) custContact += 'Phone: ' + data.customer.phone + ' ';
        if (data.customer.email) custContact += '• Email: ' + data.customer.email;
        $('#modal_cust_contact').text(custContact || 'No direct phone/email provided');
        $('#modal_cust_address').text(data.customer.address || 'Store Counter Pickup / Local Delivery');
    }

    // Line Items Table
    var itemsTbody = '';
    if (data.items && data.items.length > 0) {
        data.items.forEach(function(item, idx) {
            var specBadges = '';
            if (item.size) specBadges += '<span class="badge" style="background:#e2e8f0; color:#334155; font-size:11px; margin-right:4px;">Size: ' + $('<div>').text(item.size).html() + '</span>';
            if (item.color) specBadges += '<span class="badge" style="background:#e2e8f0; color:#334155; font-size:11px; margin-right:4px;">Color: ' + $('<div>').text(item.color).html() + '</span>';
            if (item.item_type) specBadges += '<span class="badge" style="background:#e0f2fe; color:#0369a1; font-size:11px; margin-right:4px;">Type: ' + $('<div>').text(item.item_type).html() + '</span>';
            if (item.special_order_reference) specBadges += '<span class="badge" style="background:#fef3c7; color:#92400e; font-size:11px;">Ref: ' + $('<div>').text(item.special_order_reference).html() + '</span>';

            var discText = '';
            if (item.discount_amount > 0) {
                discText = '<div style="font-size:11px; color:#dc2626; font-weight:600;">Discount applied: -₱' + parseFloat(item.discount_amount).toFixed(2) + '</div>';
            }

            itemsTbody += '<tr>' +
                '<td style="text-align:center; vertical-align:middle; font-weight:700; color:#64748b;">' + (idx + 1) + '</td>' +
                '<td>' +
                    '<div style="font-weight:700; color:#0f172a; font-size:13.5px;">' + $('<div>').text(item.product_name).html() + '</div>' +
                    (specBadges ? '<div style="margin-top:4px;">' + specBadges + '</div>' : '') +
                    discText +
                '</td>' +
                '<td style="text-align:center; vertical-align:middle; font-weight:700; font-size:14px; color:#1e293b;">' + item.quantity + '</td>' +
                '<td style="text-align:right; vertical-align:middle; font-weight:600; color:#475569;">₱' + parseFloat(item.unit_price).toFixed(2) + '</td>' +
                '<td style="text-align:right; vertical-align:middle; font-weight:700; font-size:14px; color:#0f172a;">₱' + parseFloat(item.line_net).toFixed(2) + '</td>' +
            '</tr>';
        });
    } else {
        itemsTbody = '<tr><td colspan="5" style="text-align:center; padding:20px; color:#94a3b8;">No items found for this Purchase Order.</td></tr>';
    }
    $('#modal_po_items_tbody').html(itemsTbody);

    // Summary Totals
    var subtotal = data.summary ? data.summary.subtotal : 0;
    var discountTotal = data.summary ? data.summary.discount_total : 0;
    var grandTotal = data.summary ? data.summary.total_amount : 0;

    $('#modal_summary_subtotal').text('₱' + parseFloat(subtotal).toFixed(2));
    if (discountTotal > 0) {
        $('#modal_summary_discount').text('-₱' + parseFloat(discountTotal).toFixed(2));
        $('#modal_summary_discount_row').show();
    } else {
        $('#modal_summary_discount_row').hide();
    }
    $('#modal_summary_total').text('₱' + parseFloat(grandTotal).toFixed(2));
}

function reprintSidebarPOVoucher() {
    if (!currentSidebarPOData) {
        alert('Please select a Purchase Order first.');
        return;
    }

    var data = currentSidebarPOData;
    var storeName = (data.supplier && data.supplier.store_name) ? data.supplier.store_name : 'E-Construction Supply Store';
    var storeAddress = (data.supplier && data.supplier.address) ? data.supplier.address : '';
    var storePhone = (data.supplier && data.supplier.phone) ? data.supplier.phone : '';
    var custName = (data.customer && data.customer.name) ? data.customer.name : 'Walk-in Customer';
    var poNum = data.po_number || 'PO-XXXXX';
    var poDate = data.date || '';
    var totalAmt = data.summary ? parseFloat(data.summary.total_amount).toFixed(2) : '0.00';
    var subtotalAmt = data.summary ? parseFloat(data.summary.subtotal).toFixed(2) : '0.00';
    var discAmt = data.summary ? parseFloat(data.summary.discount_total).toFixed(2) : '0.00';

    // Build print HTML document (supports 500mm / 80mm / A4 thermal layout)
    var itemsRows = '';
    if (data.items && data.items.length > 0) {
        data.items.forEach(function(it, i) {
            itemsRows += '<tr>' +
                '<td style="padding:6px 4px; border-bottom:1px dashed #ccc; font-size:12px;">' +
                    '<strong>' + it.product_name + '</strong>' +
                    (it.size || it.color ? '<div style="font-size:10px; color:#555;">' + (it.size ? 'Size: ' + it.size + ' ' : '') + (it.color ? 'Color: ' + it.color : '') + '</div>' : '') +
                '</td>' +
                '<td style="padding:6px 4px; text-align:center; border-bottom:1px dashed #ccc; font-size:12px;">' + it.quantity + '</td>' +
                '<td style="padding:6px 4px; text-align:right; border-bottom:1px dashed #ccc; font-size:12px;">₱' + parseFloat(it.unit_price).toFixed(2) + '</td>' +
                '<td style="padding:6px 4px; text-align:right; border-bottom:1px dashed #ccc; font-size:12px; font-weight:bold;">₱' + parseFloat(it.line_net).toFixed(2) + '</td>' +
            '</tr>';
        });
    }

    var printContent = '<!DOCTYPE html>' +
        '<html>' +
        '<head>' +
        '<title>Purchase Order - ' + poNum + '</title>' +
        '<style>' +
        '@media print {' +
            '@page { size: auto; margin: 5mm; }' +
            'body { margin: 0; font-family: "Courier New", Courier, monospace, sans-serif; font-size: 12px; color: #000; }' +
        '}' +
        'body { font-family: "Courier New", Courier, monospace, sans-serif; font-size: 12px; line-height: 1.3; margin: 15px auto; max-width: 500mm; }' +
        '.voucher-box { border: 1px solid #000; padding: 15px; }' +
        '.text-center { text-align: center; }' +
        '.text-right { text-align: right; }' +
        '.title { font-size: 16px; font-weight: bold; }' +
        '.po-tag { display: inline-block; background: #eee; border: 1px solid #999; padding: 2px 8px; font-weight: bold; margin: 4px 0; }' +
        'table { width: 100%; border-collapse: collapse; margin-top: 10px; }' +
        'th { border-bottom: 2px solid #000; text-align: left; padding: 5px 4px; font-size: 12px; }' +
        '</style>' +
        '</head>' +
        '<body>' +
        '<div class="voucher-box">' +
            '<div class="text-center">' +
                '<div class="title">' + storeName + '</div>' +
                (storeAddress ? '<div>' + storeAddress + '</div>' : '') +
                (storePhone ? '<div>Tel: ' + storePhone + '</div>' : '') +
                '<div style="margin: 8px 0; border-top: 1px dashed #000; border-bottom: 1px dashed #000; padding: 4px 0;">' +
                    '<strong>PURCHASE ORDER VOUCHER (UNPAID)</strong>' +
                '</div>' +
            '</div>' +
            '<div style="display: flex; justify-content: space-between; margin: 8px 0; font-size: 11px;">' +
                '<div>' +
                    '<strong>PO NO:</strong> <span class="po-tag">' + poNum + '</span><br>' +
                    '<strong>Date:</strong> ' + poDate + '<br>' +
                    '<strong>Customer:</strong> ' + custName +
                '</div>' +
                '<div class="text-right">' +
                    '<strong>STATUS:</strong><br><span style="font-weight:bold; color:#000;">AWAITING PAYMENT</span><br>' +
                    '<span style="font-size:10px;">(Queue to Cashier)</span>' +
                '</div>' +
            '</div>' +
            '<table>' +
                '<thead>' +
                    '<tr>' +
                        '<th>Item Description</th>' +
                        '<th style="text-align:center;">Qty</th>' +
                        '<th style="text-align:right;">Price</th>' +
                        '<th style="text-align:right;">Total</th>' +
                    '</tr>' +
                '</thead>' +
                '<tbody>' +
                    itemsRows +
                '</tbody>' +
            '</table>' +
            '<div style="margin-top: 12px; border-top: 1px solid #000; padding-top: 8px;">' +
                '<div style="display:flex; justify-content:space-between; font-size:11px;"><span>Subtotal:</span><span>₱' + subtotalAmt + '</span></div>' +
                (parseFloat(discAmt) > 0 ? '<div style="display:flex; justify-content:space-between; font-size:11px; color:#900;"><span>Discounts:</span><span>-₱' + discAmt + '</span></div>' : '') +
                '<div style="display:flex; justify-content:space-between; font-size:15px; font-weight:bold; margin-top:4px; border-top:1px dashed #000; padding-top:4px;">' +
                    '<span>TOTAL DUE:</span><span>₱' + totalAmt + '</span>' +
                '</div>' +
            '</div>' +
            '<div class="text-center" style="margin-top: 15px; font-size: 10px; border-top: 1px dashed #999; padding-top: 8px;">' +
                '*** PLEASE PROCEED TO CASHIER FOR PAYMENT ***<br>' +
                'Thank you for your business!' +
            '</div>' +
        '</div>' +
        '</body>' +
        '</html>';

    var printWin = window.open('', '_blank', 'width=800,height=700');
    if (printWin) {
        printWin.document.open();
        printWin.document.write(printContent);
        printWin.document.close();
        printWin.focus();
        setTimeout(function() {
            printWin.print();
        }, 300);
    } else {
        alert('Popup blocked. Please allow popups to print Purchase Order vouchers.');
    }
}
</script>
