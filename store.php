<?php require_once('header.php'); ?>

<?php
if (!isset($_GET['slug'])) {
    header('location: suppliers.php');
    exit;
}

$statement = $pdo->prepare("SELECT * FROM tbl_supplier WHERE supplier_slug = ? AND supplier_status = 'Active'");
$statement->execute(array($_GET['slug']));
$supplier = $statement->fetch(PDO::FETCH_ASSOC);

if (!$supplier) {
    header('location: suppliers.php');
    exit;
}

$supplier_id = $supplier['supplier_id'];
?>

<!-- Storefront Banner -->
<div class="page-banner" style="background-image: url(assets/uploads/about-banner.jpg); background-color: #1F2937;">
    <div class="inner">
        <h1><?php echo htmlspecialchars($supplier['supplier_name']); ?> Storefront</h1>
        <p style="color: #fff; font-size: 16px;"><i class="fa fa-envelope"></i> <?php echo htmlspecialchars($supplier['supplier_email']); ?> | <i class="fa fa-phone"></i> <?php echo htmlspecialchars($supplier['supplier_phone']); ?></p>
    </div>
</div>

<div class="page">
    <div class="container">
        <div class="row">            
            
            <!-- Store Profile Sidebar -->
            <div class="col-md-3">
                <div style="background: #f8f9fa; border: 1px solid #ddd; padding: 20px; border-radius: 5px; margin-bottom: 20px;">
                    <div style="text-align: center; margin-bottom: 15px;">
                        <i class="fa fa-industry fa-4x" style="color: #1F2937;"></i>
                        <h4 style="font-weight: bold; margin-top: 15px;"><?php echo htmlspecialchars($supplier['supplier_name']); ?></h4>
                        <span class="label label-warning" style="background-color: #F59E0B;">Verified Supplier</span>
                    </div>
                    
                    <hr>
                    
                    <h5><strong>Company Profile:</strong></h5>
                    <p style="font-size: 13px; text-align: justify;"><?php echo htmlspecialchars($supplier['supplier_description'] ?: 'No description available.'); ?></p>
                    
                    <hr>
                    
                    <h5><strong>Store Details:</strong></h5>
                    <ul class="list-unstyled" style="font-size: 13px; padding-left: 0; line-height: 2;">
                        <li><i class="fa fa-map-marker"></i> <strong>Address:</strong><br><?php echo htmlspecialchars($supplier['supplier_address'] ?: 'Not specified'); ?></li>
                        <li><i class="fa fa-truck"></i> <strong>Delivery Areas:</strong><br><?php echo htmlspecialchars($supplier['supplier_delivery_areas'] ?: 'Regional Delivery Available'); ?></li>
                        <li><i class="fa fa-certificate"></i> <strong>Certifications:</strong><br><?php echo htmlspecialchars($supplier['supplier_certifications'] ?: 'ISO 9001, Quality Assured'); ?></li>
                        <li><i class="fa fa-calendar"></i> <strong>Member Since:</strong><br><?php echo date('M Y', strtotime($supplier['created_at'])); ?></li>
                    </ul>
                </div>
            </div>

            <!-- Store Products Grid -->
            <div class="col-md-9">
                <h3 style="font-weight: bold; margin-top: 0; color: #1F2937; display: flex; justify-content: space-between; align-items: center;">
                    Supplier Products
                    <a href="request-quote.php?supplier_id=<?php echo $supplier_id; ?>" class="btn btn-warning btn-sm" style="background-color: #F59E0B; border-color: #F59E0B; font-weight: bold;"><i class="fa fa-file-text-o"></i> Request Custom Quote (RFQ)</a>
                </h3>
                <hr style="margin-top: 10px;">

                <div class="row">
                    <?php
                    $statement1 = $pdo->prepare("SELECT * FROM tbl_product WHERE supplier_id = ? AND p_is_active = 1 ORDER BY p_id DESC");
                    $statement1->execute(array($supplier_id));
                    $products = $statement1->fetchAll(PDO::FETCH_ASSOC);

                    if (count($products) > 0) {
                        foreach ($products as $row) {
                            ?>
                            <div class="col-md-4 col-sm-6">
                                <div class="thumbnail" style="padding: 10px; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); margin-bottom: 25px;">
                                    <div style="height: 150px; display: flex; align-items: center; justify-content: center; background: #fafafa; border-radius: 3px; overflow: hidden; margin-bottom: 10px;">
                                        <i class="fa fa-cubes fa-3x" style="color: #ccc;"></i>
                                    </div>
                                    <div class="caption" style="padding: 0;">
                                        <h5 style="font-weight: bold; margin: 0 0 5px 0; height: 36px; overflow: hidden; text-overflow: ellipsis; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; line-height: 1.2;">
                                            <a href="product.php?id=<?php echo $row['p_id']; ?>" style="color: #1F2937;"><?php echo htmlspecialchars($row['p_name']); ?></a>
                                        </h5>
                                        <p style="margin: 0; font-size: 15px; color: #1F2937; font-weight: bold;">
                                            &#8369;<?php echo htmlspecialchars($row['p_current_price']); ?>
                                            <span style="font-size: 12px; font-weight: normal; color: #777; text-decoration: line-through;">&#8369;<?php echo htmlspecialchars($row['p_old_price']); ?></span>
                                        </p>
                                        <p style="font-size: 11px; color: #666; margin: 5px 0 0 0;">
                                            <strong>MOQ:</strong> <?php echo htmlspecialchars($row['p_moq']); ?> units | <strong>Est. Delivery:</strong> <?php echo htmlspecialchars($row['p_delivery_estimate']); ?>
                                        </p>
                                        <hr style="margin: 10px 0;">
                                        <div style="display: flex; justify-content: space-between; align-items: center;">
                                            <span style="font-size: 11px; font-weight: bold; color: #F59E0B; text-transform: uppercase;"><i class="fa fa-tag"></i> <?php echo htmlspecialchars($row['p_brand']); ?></span>
                                            <a href="product.php?id=<?php echo $row['p_id']; ?>" class="btn btn-warning btn-xs" style="background-color: #F59E0B; border-color: #F59E0B;">Details</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php
                        }
                    } else {
                        echo '<div class="col-md-12"><div class="alert alert-info">This supplier has no active products listed.</div></div>';
                    }
                    ?>
                </div>
            </div>

        </div>
    </div>
</div>

<?php require_once('footer.php'); ?>
