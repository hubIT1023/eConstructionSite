<?php require_once('header.php'); ?>

<div class="page-banner" style="background-image: url(assets/uploads/about-banner.jpg);">
    <div class="inner">
        <h1>Suppliers Directory</h1>
    </div>
</div>

<div class="page">
    <div class="container">
        <div class="row">            
            <div class="col-md-12">
                <h3>Our Certified Construction Materials Suppliers</h3>
                <p class="text-muted">Browse and compare trusted suppliers offering wholesale building materials, concrete, steel, and electrical equipment.</p>
                <hr>
                
                <div class="row">
                    <?php
                    $statement = $pdo->prepare("SELECT * FROM tbl_supplier WHERE supplier_status = 'Active' ORDER BY supplier_name ASC");
                    $statement->execute();
                    $result = $statement->fetchAll(PDO::FETCH_ASSOC);
                    if(count($result) > 0) {
                        foreach ($result as $row) {
                            $logo = $row['supplier_logo'] ? 'assets/uploads/'.$row['supplier_logo'] : 'assets/uploads/default_logo.png';
                            ?>
                            <div class="col-md-4 col-sm-6">
                                <div class="thumbnail" style="padding: 15px; border-radius: 5px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); margin-bottom: 30px;">
                                    <div style="height: 100px; display: flex; align-items: center; justify-content: center; margin-bottom: 15px; background: #f9f9f9; border-radius: 4px;">
                                        <i class="fa fa-industry fa-3x" style="color: #1F2937;"></i>
                                    </div>
                                    <div class="caption" style="padding: 0;">
                                        <h4 style="font-weight: bold; margin-top: 0; color: #1F2937;">
                                            <?php echo htmlspecialchars($row['supplier_name']); ?>
                                            <span class="label label-warning" style="font-size: 10px; vertical-align: middle; background-color: #F59E0B;">Verified</span>
                                        </h4>
                                        <p style="height: 50px; overflow: hidden; text-overflow: ellipsis; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; font-size: 13px;">
                                            <?php echo htmlspecialchars($row['supplier_description'] ?: 'No description provided.'); ?>
                                        </p>
                                        <p style="font-size: 12px; color: #666;"><i class="fa fa-map-marker"></i> <?php echo htmlspecialchars($row['supplier_address'] ?: 'Address not available'); ?></p>
                                        <hr style="margin: 10px 0;">
                                        <div style="display: flex; justify-content: space-between; align-items: center;">
                                            <span class="badge" style="background-color: #1F2937;"><?php echo htmlspecialchars($row['supplier_plan']); ?> Vendor</span>
                                            <a href="store.php?slug=<?php echo $row['supplier_slug']; ?>" class="btn btn-warning btn-xs" style="background-color: #F59E0B; border-color: #F59E0B;">View Storefront</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php
                        }
                    } else {
                        echo '<div class="col-md-12"><div class="alert alert-danger">No suppliers found.</div></div>';
                    }
                    ?>
                </div>

            </div>
        </div>
    </div>
</div>

<?php require_once('footer.php'); ?>
