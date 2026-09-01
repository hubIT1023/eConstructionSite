<?php require_once('header.php'); ?>

<section class="content-header">
	<h1>Supplier Dashboard</h1>
</section>

<?php
// Tenancy counts
$statement = $pdo->prepare("SELECT * FROM tbl_product WHERE supplier_id=?");
$statement->execute(array($supplier_id));
$total_product = $statement->rowCount();

$statement = $pdo->prepare("SELECT * FROM tbl_payment WHERE supplier_id=? AND payment_status=?");
$statement->execute(array($supplier_id, 'Pending'));
$total_order_pending = $statement->rowCount();

$statement = $pdo->prepare("SELECT * FROM tbl_payment WHERE supplier_id=? AND payment_status=?");
$statement->execute(array($supplier_id, 'Completed'));
$total_order_completed = $statement->rowCount();

$statement = $pdo->prepare("SELECT * FROM tbl_quote WHERE supplier_id=? AND status='Pending'");
$statement->execute(array($supplier_id));
$total_quotes_pending = $statement->rowCount();

$statement = $pdo->prepare("SELECT * FROM tbl_quote WHERE supplier_id=?");
$statement->execute(array($supplier_id));
$total_quotes = $statement->rowCount();
?>

<section class="content">
<div class="row" style="margin-bottom: 20px;">
    <div class="col-md-12">
        <div style="background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 100%); color: #fff; padding: 22px 25px; border-radius: 8px; box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap;">
            <div>
                <h3 style="margin: 0 0 6px 0; font-weight: 700; color: #fff; font-size: 22px;">
                    <i class="fa fa-calculator" style="margin-right: 8px;"></i> Supplier Point of Sale (POS) Terminal
                </h3>
                <p style="margin: 0; color: #bfdbfe; font-size: 14px;">
                    Fast cashier billing, over-the-counter sales, real-time inventory deduction, and instant receipt printing.
                </p>
            </div>
            <div style="margin-top: 10px;">
                <a href="pos.php" class="btn btn-warning btn-lg" style="background-color: #f59e0b; border-color: #f59e0b; color: #111827; font-weight: 700; border-radius: 6px; box-shadow: 0 2px 6px rgba(0,0,0,0.2); padding: 10px 22px;">
                    <i class="fa fa-shopping-cart"></i> Launch POS Register
                </a>
            </div>
        </div>
    </div>
</div>

<div class="row">
            <div class="col-lg-3 col-xs-6">
              <!-- small box -->
              <div class="small-box bg-primary" style="background-color: #1F2937 !important;">
                <div class="inner">
                  <h3><?php echo $total_product; ?></h3>
                  <p>My Listed Products</p>
                </div>
                <div class="icon">
                  <i class="fa fa-cubes"></i>
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
              <div class="small-box bg-yellow" style="background-color: #F59E0B !important;">
                <div class="inner">
                  <h3><?php echo $total_quotes_pending; ?></h3>
                  <p>Pending RFQ Quotes</p>
                </div>
                <div class="icon">
                  <i class="fa fa-file-text-o"></i>
                </div>
              </div>
            </div>
            
            <div class="col-lg-3 col-xs-6">
              <!-- small box -->
              <div class="small-box bg-blue">
                <div class="inner">
                  <h3><?php echo $total_quotes; ?></h3>
                  <p>Total RFQs Received</p>
                </div>
                <div class="icon">
                  <i class="fa fa-folder-open"></i>
                </div>
              </div>
            </div>
		  </div>
		  
</section>

<?php require_once('footer.php'); ?>