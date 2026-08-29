<?php require_once('header.php'); ?>

<section class="content-header">
	<h1>SaaS Platform Administration Dashboard</h1>
</section>

<?php
// SaaS stats
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
		  
</section>

<?php require_once('footer.php'); ?>