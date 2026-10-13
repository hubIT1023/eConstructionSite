<?php require_once('header.php'); ?>
<?php require_once('inc/erp_admin_helper.php'); ?>

<section class="content-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
	<h1>SaaS Platform Administration Dashboard</h1>
	<div style="display: flex; gap: 8px; flex-wrap: wrap;">
		<a href="../user-manual.html" target="_blank" class="btn btn-default btn-sm" style="font-weight: 700;"><i class="fa fa-book"></i> Interactive User Manual</a>
		<a href="dev-documentation.php" class="btn btn-primary btn-sm" style="font-weight: 700;"><i class="fa fa-code"></i> Developer Documentation</a>
	</div>
</section>

<?php
// SaaS stats
$erp_stats = erp_get_platform_stats($pdo);

$statement = $pdo->prepare("SELECT * FROM tbl_supplier");
$statement->execute();
$total_suppliers = $statement->rowCount();

$statement = $pdo->prepare("SELECT * FROM tbl_product");
$statement->execute();
$total_product = $statement->rowCount();

$statement = $pdo->prepare("SELECT * FROM tbl_customer WHERE cust_status='1'");
$statement->execute();
$total_customers = $statement->rowCount();

$statement = $pdo->prepare("SELECT * FROM tbl_subscriber WHERE subs_active='1'");
$statement->execute();
$total_subscriber = $statement->rowCount();

$statement = $pdo->prepare("SELECT * FROM tbl_payment WHERE payment_status=?");
$statement->execute(array('Completed'));
$total_order_completed = $statement->rowCount();

$statement = $pdo->prepare("SELECT * FROM tbl_payment WHERE payment_status=?");
$statement->execute(array('Pending'));
$total_order_pending = $statement->rowCount();

$statement = $pdo->prepare("SELECT * FROM tbl_quote");
$statement->execute();
$total_quotes = $statement->rowCount();
?>

<section class="content">
<div class="row">
            <div class="col-lg-3 col-xs-6">
              <!-- small box -->
              <div class="small-box bg-primary" style="background-color: #1F2937 !important;">
                <div class="inner">
                  <h3><?php echo $total_suppliers; ?></h3>
                  <p>Registered Suppliers</p>
                </div>
                <div class="icon">
                  <i class="fa fa-industry"></i>
                </div>
              </div>
            </div>

            <div class="col-lg-3 col-xs-6">
              <!-- small box -->
              <div class="small-box bg-aqua">
                <div class="inner">
                  <h3><?php echo $total_product; ?></h3>
                  <p>Total Catalog Products</p>
                </div>
                <div class="icon">
                  <i class="fa fa-cubes"></i>
                </div>
              </div>
            </div>

            <div class="col-lg-3 col-xs-6">
              <!-- small box -->
              <div class="small-box bg-yellow" style="background-color: #F59E0B !important;">
                <div class="inner">
                  <h3><?php echo $total_quotes; ?></h3>
                  <p>B2B Quote Requests</p>
                </div>
                <div class="icon">
                  <i class="fa fa-file-text-o"></i>
                </div>
              </div>
            </div>

            <div class="col-lg-3 col-xs-6">
              <!-- small box -->
              <div class="small-box bg-green">
                <div class="inner">
                  <h3><?php echo $total_order_completed; ?></h3>
                  <p>Completed Orders</p>
                </div>
                <div class="icon">
                  <i class="fa fa-check-circle"></i>
                </div>
              </div>
            </div>

            <div class="col-lg-3 col-xs-6">
              <!-- small box -->
              <div class="small-box bg-maroon">
                <div class="inner">
                  <h3><?php echo $total_order_pending; ?></h3>
                  <p>Pending Orders</p>
                </div>
                <div class="icon">
                  <i class="fa fa-clock-o"></i>
                </div>
              </div>
            </div>

            <div class="col-lg-3 col-xs-6">
              <!-- small box -->
              <div class="small-box bg-red">
                <div class="inner">
                  <h3><?php echo $total_customers; ?></h3>
                  <p>Active Customers</p>
                </div>
                <div class="icon">
                  <i class="fa fa-users"></i>
                </div>
              </div>
            </div>

            <div class="col-lg-3 col-xs-6">
              <!-- small box -->
              <div class="small-box bg-orange">
                <div class="inner">
                  <h3><?php echo $total_subscriber; ?></h3>
                  <p>Subscribers</p>
                </div>
                <div class="icon">
                  <i class="fa fa-envelope"></i>
                </div>
              </div>
            </div>
		  </div>
		  
		  <!-- ERP & Financial Intelligence Overview Row -->
		  <div class="row" style="margin-top: 15px;">
		    <div class="col-md-12">
		      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; border-bottom: 2px solid #cbd5e1; padding-bottom: 6px;">
		        <h4 style="margin: 0; color: #1e293b; font-weight: 700;"><i class="fa fa-pie-chart text-info"></i> ERP & Financial Intelligence Module Controls</h4>
		        <a href="<?php echo ERP_LOCAL_URL; ?>" target="_blank" class="btn btn-info btn-xs" style="font-weight: 600;"><i class="fa fa-external-link"></i> Launch ERP Engine</a>
		      </div>
		    </div>

            <div class="col-lg-3 col-xs-6">
              <div class="small-box" style="background-color: #0284c7 !important; color: white;">
                <div class="inner">
                  <h3><?php echo $erp_stats['total_erp_active']; ?></h3>
                  <p>ERP-Subscribed Tenants</p>
                </div>
                <div class="icon">
                  <i class="fa fa-cubes" style="color: rgba(255,255,255,0.2);"></i>
                </div>
              </div>
            </div>

            <div class="col-lg-3 col-xs-6">
              <div class="small-box" style="background-color: #0d9488 !important; color: white;">
                <div class="inner">
                  <h3><?php echo $erp_stats['total_synced_orders']; ?></h3>
                  <p>Synced POS Orders</p>
                </div>
                <div class="icon">
                  <i class="fa fa-database" style="color: rgba(255,255,255,0.2);"></i>
                </div>
              </div>
            </div>

            <div class="col-lg-3 col-xs-6">
              <div class="small-box" style="background-color: #475569 !important; color: white;">
                <div class="inner">
                  <h3>₱<?php echo number_format($erp_stats['total_bank_fees_tracked'], 2); ?></h3>
                  <p>Tracked Merchant/Bank Fees</p>
                </div>
                <div class="icon">
                  <i class="fa fa-credit-card" style="color: rgba(255,255,255,0.2);"></i>
                </div>
              </div>
            </div>

            <div class="col-lg-3 col-xs-6">
              <div class="small-box" style="background-color: #64748b !important; color: white;">
                <div class="inner">
                  <h3>₱<?php echo number_format($erp_stats['total_opex_tracked'], 2); ?></h3>
                  <p>Tracked Operating Expenses</p>
                </div>
                <div class="icon">
                  <i class="fa fa-money" style="color: rgba(255,255,255,0.2);"></i>
                </div>
              </div>
            </div>
		  </div>

</section>

<?php require_once('footer.php'); ?>