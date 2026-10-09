<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_lifetime' => 604800,
        'gc_maxlifetime' => 604800,
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax'
    ]);
}
// This is main configuration File
include("admin/inc/config.php");
include("admin/inc/functions.php");
include("admin/inc/CSRF_Protect.php");
$csrf = new CSRF_Protect();
$error_message = '';
$success_message = '';
$error_message1 = '';
$success_message1 = '';

// Getting all language variables into array as global variable
$i=1;
$statement = $pdo->prepare("SELECT * FROM tbl_language ORDER BY lang_id ASC");
$statement->execute();
$result = $statement->fetchAll(PDO::FETCH_ASSOC);							
foreach ($result as $row) {
	define('LANG_VALUE_'.$i,$row['lang_value']);
	$i++;
}

$statement = $pdo->prepare("SELECT * FROM tbl_settings WHERE id=1");
$statement->execute();
$result = $statement->fetchAll(PDO::FETCH_ASSOC);
foreach ($result as $row)
{
	$logo = $row['logo'];
	$favicon = $row['favicon'];
	$contact_email = $row['contact_email'];
	$contact_phone = $row['contact_phone'];
	$meta_title_home = $row['meta_title_home'];
    $meta_keyword_home = $row['meta_keyword_home'];
    $meta_description_home = $row['meta_description_home'];
    $before_head = $row['before_head'];
    $after_body = $row['after_body'];
}

// Checking the order table and removing the pending transaction that are 24 hours+ old. Very important
$current_date_time = date('Y-m-d H:i:s');
$statement = $pdo->prepare("SELECT * FROM tbl_payment WHERE payment_status=?");
$statement->execute(array('Pending'));
$result = $statement->fetchAll(PDO::FETCH_ASSOC);							
foreach ($result as $row) {
	$ts1 = strtotime($row['payment_date']);
	$ts2 = strtotime($current_date_time);     
	$diff = $ts2 - $ts1;
	$time = $diff/(3600);
	if($time>24) {

		// Return back the stock amount
		$statement1 = $pdo->prepare("SELECT * FROM tbl_order WHERE payment_id=?");
		$statement1->execute(array($row['payment_id']));
		$result1 = $statement1->fetchAll(PDO::FETCH_ASSOC);
		foreach ($result1 as $row1) {
			$statement2 = $pdo->prepare("SELECT * FROM tbl_product WHERE p_id=?");
			$statement2->execute(array($row1['product_id']));
			$result2 = $statement2->fetchAll(PDO::FETCH_ASSOC);							
			foreach ($result2 as $row2) {
				$p_qty = $row2['p_qty'];
			}
			$final = $p_qty+$row1['quantity'];

			$statement = $pdo->prepare("UPDATE tbl_product SET p_qty=? WHERE p_id=?");
			$statement->execute(array($final,$row1['product_id']));
		}
		
		// Deleting data from table
		$statement1 = $pdo->prepare("DELETE FROM tbl_order WHERE payment_id=?");
		$statement1->execute(array($row['payment_id']));

		$statement1 = $pdo->prepare("DELETE FROM tbl_payment WHERE id=?");
		$statement1->execute(array($row['id']));
	}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>

	<!-- Meta Tags -->
	<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
	<meta http-equiv="content-type" content="text/html; charset=UTF-8"/>

	<script>
		// Automatic Client PC Timezone Detection
		(function() {
			try {
				var tz = Intl.DateTimeFormat().resolvedOptions().timeZone;
				if (tz && document.cookie.indexOf('client_timezone=' + encodeURIComponent(tz)) === -1) {
					document.cookie = 'client_timezone=' + encodeURIComponent(tz) + '; path=/; max-age=31536000; SameSite=Lax';
				}
			} catch(e) {}
		})();
	</script>

	<!-- Favicon -->
	<link rel="icon" type="image/png" href="assets/uploads/<?php echo $favicon; ?>">

	<!-- Stylesheets -->
	<link rel="stylesheet" href="assets/css/bootstrap.min.css">
	<link rel="stylesheet" href="assets/css/font-awesome.min.css">
	<link rel="stylesheet" href="assets/css/owl.carousel.min.css">
	<link rel="stylesheet" href="assets/css/owl.theme.default.min.css">
	<link rel="stylesheet" href="assets/css/jquery.bxslider.min.css">
    <link rel="stylesheet" href="assets/css/magnific-popup.css">
    <link rel="stylesheet" href="assets/css/rating.css">
	<link rel="stylesheet" href="assets/css/spacing.css">
	<link rel="stylesheet" href="assets/css/bootstrap-touch-slider.css">
	<link rel="stylesheet" href="assets/css/animate.min.css">
	<link rel="stylesheet" href="assets/css/tree-menu.css">
	<link rel="stylesheet" href="assets/css/select2.min.css">
	<link rel="stylesheet" href="assets/css/main.css">
	<link rel="stylesheet" href="assets/css/responsive.css">

	<?php

	$statement = $pdo->prepare("SELECT * FROM tbl_page WHERE id=1");
	$statement->execute();
	$result = $statement->fetchAll(PDO::FETCH_ASSOC);							
	foreach ($result as $row) {
		$about_meta_title = $row['about_meta_title'];
		$about_meta_keyword = $row['about_meta_keyword'];
		$about_meta_description = $row['about_meta_description'];
		$faq_meta_title = $row['faq_meta_title'];
		$faq_meta_keyword = $row['faq_meta_keyword'];
		$faq_meta_description = $row['faq_meta_description'];
		$blog_meta_title = $row['blog_meta_title'];
		$blog_meta_keyword = $row['blog_meta_keyword'];
		$blog_meta_description = $row['blog_meta_description'];
		$contact_meta_title = $row['contact_meta_title'];
		$contact_meta_keyword = $row['contact_meta_keyword'];
		$contact_meta_description = $row['contact_meta_description'];
		$pgallery_meta_title = $row['pgallery_meta_title'];
		$pgallery_meta_keyword = $row['pgallery_meta_keyword'];
		$pgallery_meta_description = $row['pgallery_meta_description'];
		$vgallery_meta_title = $row['vgallery_meta_title'];
		$vgallery_meta_keyword = $row['vgallery_meta_keyword'];
		$vgallery_meta_description = $row['vgallery_meta_description'];
	}

	$cur_page = substr($_SERVER["SCRIPT_NAME"],strrpos($_SERVER["SCRIPT_NAME"],"/")+1);
	
	if($cur_page == 'index.php' || $cur_page == 'login.php' || $cur_page == 'registration.php' || $cur_page == 'cart.php' || $cur_page == 'checkout.php' || $cur_page == 'forget-password.php' || $cur_page == 'reset-password.php' || $cur_page == 'product-category.php' || $cur_page == 'product.php') {
		?>
		<title><?php echo $meta_title_home; ?></title>
		<meta name="keywords" content="<?php echo $meta_keyword_home; ?>">
		<meta name="description" content="<?php echo $meta_description_home; ?>">
		<?php
	}

	if($cur_page == 'about.php') {
		?>
		<title><?php echo $about_meta_title; ?></title>
		<meta name="keywords" content="<?php echo $about_meta_keyword; ?>">
		<meta name="description" content="<?php echo $about_meta_description; ?>">
		<?php
	}
	if($cur_page == 'faq.php') {
		?>
		<title><?php echo $faq_meta_title; ?></title>
		<meta name="keywords" content="<?php echo $faq_meta_keyword; ?>">
		<meta name="description" content="<?php echo $faq_meta_description; ?>">
		<?php
	}
	if($cur_page == 'contact.php') {
		?>
		<title><?php echo $contact_meta_title; ?></title>
		<meta name="keywords" content="<?php echo $contact_meta_keyword; ?>">
		<meta name="description" content="<?php echo $contact_meta_description; ?>">
		<?php
	}
	if($cur_page == 'product.php')
	{
		$statement = $pdo->prepare("SELECT * FROM tbl_product WHERE p_id=?");
		$statement->execute(array($_REQUEST['id']));
		$result = $statement->fetchAll(PDO::FETCH_ASSOC);							
		foreach ($result as $row) 
		{
		    $og_photo = $row['p_featured_photo'];
		    $og_title = $row['p_name'];
		    $og_slug = 'product.php?id='.$_REQUEST['id'];
			$og_description = substr(strip_tags($row['p_description']),0,200).'...';
		}
	}

	if($cur_page == 'dashboard.php') {
		?>
		<title>Dashboard - <?php echo $meta_title_home; ?></title>
		<meta name="keywords" content="<?php echo $meta_keyword_home; ?>">
		<meta name="description" content="<?php echo $meta_description_home; ?>">
		<?php
	}
	if($cur_page == 'customer-profile-update.php') {
		?>
		<title>Update Profile - <?php echo $meta_title_home; ?></title>
		<meta name="keywords" content="<?php echo $meta_keyword_home; ?>">
		<meta name="description" content="<?php echo $meta_description_home; ?>">
		<?php
	}
	if($cur_page == 'customer-billing-shipping-update.php') {
		?>
		<title>Update Billing and Shipping Info - <?php echo $meta_title_home; ?></title>
		<meta name="keywords" content="<?php echo $meta_keyword_home; ?>">
		<meta name="description" content="<?php echo $meta_description_home; ?>">
		<?php
	}
	if($cur_page == 'customer-password-update.php') {
		?>
		<title>Update Password - <?php echo $meta_title_home; ?></title>
		<meta name="keywords" content="<?php echo $meta_keyword_home; ?>">
		<meta name="description" content="<?php echo $meta_description_home; ?>">
		<?php
	}
	if($cur_page == 'customer-order.php') {
		?>
		<title>Orders - <?php echo $meta_title_home; ?></title>
		<meta name="keywords" content="<?php echo $meta_keyword_home; ?>">
		<meta name="description" content="<?php echo $meta_description_home; ?>">
		<?php
	}
	?>
	
	<?php if($cur_page == 'blog-single.php'): ?>
		<meta property="og:title" content="<?php echo $og_title; ?>">
		<meta property="og:type" content="website">
		<meta property="og:url" content="<?php echo BASE_URL.$og_slug; ?>">
		<meta property="og:description" content="<?php echo $og_description; ?>">
		<meta property="og:image" content="assets/uploads/<?php echo $og_photo; ?>">
	<?php endif; ?>

	<?php if($cur_page == 'product.php'): ?>
		<meta property="og:title" content="<?php echo $og_title; ?>">
		<meta property="og:type" content="website">
		<meta property="og:url" content="<?php echo BASE_URL.$og_slug; ?>">
		<meta property="og:description" content="<?php echo $og_description; ?>">
		<meta property="og:image" content="assets/uploads/<?php echo $og_photo; ?>">
	<?php endif; ?>

	<script src="https://cdnjs.cloudflare.com/ajax/libs/modernizr/2.8.3/modernizr.min.js"></script>

	<script type="text/javascript" src="//platform-api.sharethis.com/js/sharethis.js#property=5993ef01e2587a001253a261&product=inline-share-buttons"></script>

<?php echo $before_head; ?>

</head>
<body>

<?php echo $after_body; ?>
<!--
<div id="preloader">
	<div id="status"></div>
</div>-->




<style>
.header {
	background: #ffffff;
	padding: 12px 0;
	border-bottom: 1px solid #e2e8f0;
}
.header-action-btn {
	display: inline-flex;
	align-items: center;
	gap: 6px;
	font-size: 13.5px;
	font-weight: 700;
	color: #1e293b;
	text-decoration: none;
	padding: 8px 16px;
	border: 1.5px solid #cbd5e1;
	border-radius: 6px;
	background: #ffffff;
	box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
	transition: all 0.2s ease;
	white-space: nowrap;
	line-height: 1.2;
}
.header-action-btn:hover,
.header-action-btn:focus {
	color: #2563eb;
	border-color: #93c5fd;
	background: #f8fafc;
	box-shadow: 0 2px 5px rgba(37, 99, 235, 0.08);
	transform: translateY(-1px);
	text-decoration: none;
}
.header-action-btn.cart-btn {
	color: #0f172a;
	border-color: #cbd5e1;
}
.header-action-btn.cart-btn:hover {
	color: #e11d48;
	border-color: #fca5a5;
	background: #fff1f2;
}
.header-action-btn.cart-btn strong {
	color: #e11d48;
	margin-left: 2px;
}
.header-user-chip {
	display: inline-flex;
	align-items: center;
	gap: 6px;
	padding: 7px 14px;
	font-size: 13px;
	font-weight: 600;
	color: #334155;
	background: #f1f5f9;
	border: 1.5px solid #e2e8f0;
	border-radius: 6px;
	white-space: nowrap;
	line-height: 1.2;
}
.header-user-chip i {
	color: #2563eb;
	font-size: 14px;
}
.header-user-name {
	max-width: 140px;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
	font-weight: 700;
	color: #0f172a;
	display: inline-block;
	vertical-align: bottom;
}
@media (max-width: 991px) {
	.header .inner {
		display: flex !important;
		flex-direction: column !important;
		align-items: flex-start !important;
		gap: 12px !important;
	}
	.header-links {
		display: flex !important;
		flex-wrap: wrap !important;
		justify-content: flex-start !important;
		gap: 8px !important;
		width: 100% !important;
	}
}
</style>

<div class="header">
	<div class="container">
		<div class="row inner" style="display: flex; align-items: center; justify-content: space-between; width: 100%;">
			<div class="logo" style="text-align: left;">
				<a href="index.php"><img src="assets/uploads/<?php echo $logo; ?>" alt="logo image" style="display: inline-block; margin-left: 0;"></a>
			</div>
			<div class="header-links" style="display: flex; align-items: center; justify-content: flex-end; gap: 8px; flex-wrap: wrap;">
				<a href="about.php" class="header-action-btn"><i class="fa fa-info-circle text-primary"></i> About</a>
				<a href="contact.php" class="header-action-btn"><i class="fa fa-envelope-o text-primary"></i> Contact</a>
				
				<?php
				$is_authenticated_customer = isset($_SESSION['customer']) && !empty($_SESSION['customer']['cust_id']) && ((int)$_SESSION['customer']['cust_id'] > 0);
				if($is_authenticated_customer):
				?>
					<div class="header-user-chip" title="Logged in as <?php echo htmlspecialchars($_SESSION['customer']['cust_name']); ?>">
						<i class="fa fa-user"></i>
						<span>Logged in as <strong class="header-user-name"><?php echo htmlspecialchars($_SESSION['customer']['cust_name']); ?></strong></span>
					</div>
					<a href="dashboard.php" class="header-action-btn"><i class="fa fa-home text-primary"></i> Dashboard</a>
				<?php else: ?>
					<a href="login.php" class="header-action-btn"><i class="fa fa-sign-in text-primary"></i> Login</a>
					<a href="registration.php" class="header-action-btn"><i class="fa fa-user-plus text-primary"></i> Register</a>
				<?php endif; ?>

				<?php
				$header_cart_total = 0.0;
				if(isset($_SESSION['cart_p_id']) && is_array($_SESSION['cart_p_id'])) {
					foreach($_SESSION['cart_p_id'] as $k => $pid) {
						$c_q = isset($_SESSION['cart_p_qty'][$k]) ? floatval($_SESSION['cart_p_qty'][$k]) : 1;
						$c_p = isset($_SESSION['cart_p_current_price'][$k]) ? floatval($_SESSION['cart_p_current_price'][$k]) : 0;
						$header_cart_total += ($c_q * $c_p);
					}
				}
				?>
				<a href="cart.php" class="header-action-btn cart-btn">
					<i class="fa fa-shopping-cart text-primary"></i>
					<span>Cart: <strong>&#8369;<?php echo number_format($header_cart_total, 2); ?></strong></span>
				</a>
			</div>
		</div>
	</div>
</div>

<div class="top-utility-bar" style="background: #0f172a; color: #94a3b8; font-size: 12px; padding: 6px 0; border-bottom: 1px solid rgba(255,255,255,0.08);">
    <div class="container" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
        <div style="display: flex; align-items: center; gap: 18px; flex-wrap: wrap;">
            <div class="left" style="float: none;">
                <ul style="margin: 0; padding: 0; list-style: none;">
                    <li style="margin-right: 0; list-style: none;"><i class="fa fa-envelope-o"></i> support@econstructionsite.com</li>
                </ul>
            </div>
            <div class="right" style="float: none;">
                <ul style="margin: 0; padding: 0; list-style: none; display: flex; align-items: center; gap: 6px;">
                    <?php
                    $statement = $pdo->prepare("SELECT * FROM tbl_social");
                    $statement->execute();
                    $result = $statement->fetchAll(PDO::FETCH_ASSOC);
                    foreach ($result as $row) {
                        if(!empty($row['social_url'])) {
                            ?>
                            <li style="margin: 0; list-style: none;"><a href="<?php echo htmlspecialchars($row['social_url']); ?>" style="color: #94a3b8; width: 22px; height: 22px; line-height: 22px; text-align: center; display: inline-block;"><i class="<?php echo htmlspecialchars($row['social_icon']); ?>"></i></a></li>
                            <?php
                        }
                    }
                    ?>
                </ul>
            </div>
        </div>
        <div style="display: flex; gap: 16px; align-items: center;">
            <a href="request-quote.php" style="color: #f59e0b; font-weight: 700; text-decoration: none;"><i class="fa fa-file-text-o"></i> Request Quote (RFQ)</a>
            <a href="bulk-orders.php" style="color: #94a3b8; text-decoration: none;"><i class="fa fa-truck"></i> Bulk Freight</a>
            <a href="supplier/login.php" style="color: #94a3b8; text-decoration: none;"><i class="fa fa-store"></i> Supplier Portal</a>
        </div>
    </div>
</div>

<div class="nav">
	<div class="container">
		<div class="row">
			<div class="col-md-12 pl_0 pr_0">
				<div class="menu-container">
					<div class="menu">
						<ul>
							<li><a href="index.php">Home</a></li>
							<li><a href="#">Products <i class="fa fa-caret-down"></i></a>
								<ul>
									<?php
									$statement = $pdo->prepare("SELECT * FROM tbl_top_category WHERE show_on_menu=1");
									$statement->execute();
									$result = $statement->fetchAll(PDO::FETCH_ASSOC);
									foreach ($result as $row) {
										?>
										<li><a href="product-category.php?id=<?php echo $row['tcat_id']; ?>&type=top-category"><?php echo $row['tcat_name']; ?></a>
											<ul>
												<?php
												$statement1 = $pdo->prepare("SELECT * FROM tbl_mid_category WHERE tcat_id=?");
												$statement1->execute(array($row['tcat_id']));
												$result1 = $statement1->fetchAll(PDO::FETCH_ASSOC);
												foreach ($result1 as $row1) {
													?>
													<li><a href="product-category.php?id=<?php echo $row1['mcat_id']; ?>&type=mid-category"><?php echo $row1['mcat_name']; ?></a>
														<ul>
															<?php
															$statement2 = $pdo->prepare("SELECT * FROM tbl_end_category WHERE mcat_id=?");
															$statement2->execute(array($row1['mcat_id']));
															$result2 = $statement2->fetchAll(PDO::FETCH_ASSOC);
															foreach ($result2 as $row2) {
																?>
																<li><a href="product-category.php?id=<?php echo $row2['ecat_id']; ?>&type=end-category"><?php echo $row2['ecat_name']; ?></a></li>
																<?php
															}
															?>
														</ul>
													</li>
													<?php
												}
												?>
											</ul>
										</li>
										<?php
									}
									?>
								</ul>
							</li>
							<li><a href="suppliers.php">Suppliers</a></li>
							<li><a href="brands.php">Brands</a></li>
							<li><a href="deals.php">Deals</a></li>
							<li><a href="request-quote.php">Request Quote</a></li>
							<li><a href="bulk-orders.php">Bulk Orders</a></li>
							<?php if(isset($_SESSION['customer']) && !empty($_SESSION['customer']['cust_id']) && ((int)$_SESSION['customer']['cust_id'] > 0)): ?>
								<li><a href="dashboard.php">Dashboard</a></li>
							<?php else: ?>
								<li><a href="login.php">Login</a></li>
							<?php endif; ?>
							<li><a href="#" style="color: #F59E0B; font-weight: bold;">Supplier Portal <i class="fa fa-caret-down"></i></a>
								<ul>
									<li><a href="webpos.php"><i class="fa fa-calculator" style="margin-right: 4px;"></i> WebPOS Overview</a></li>
									<li><a href="supplier/login.php"><i class="fa fa-sign-in" style="margin-right: 4px;"></i> Supplier POS Login</a></li>
									<li><a href="supplier-registration.php"><i class="fa fa-user-plus" style="margin-right: 4px;"></i> Supplier Registration</a></li>
								</ul>
							</li>
						</ul>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>