<?php require_once('header.php'); ?>

<div class="page-banner" style="background-image: url(assets/uploads/about-banner.jpg);">
    <div class="inner">
        <h1>Special Deals & Offers</h1>
    </div>
</div>

<div class="page">
    <div class="container">
        <div class="row">            
            <div class="col-md-12">
                <h3>Today's Wholesale Discounts</h3>
                <p class="text-muted">Take advantage of discounted contractor rates and promotional pricing on select bulk materials.</p>
                <hr>

                <div class="row">
                    <?php
                    // Cast prices or check if old price is higher than current price
                    $statement = $pdo->prepare("SELECT * FROM tbl_product WHERE p_old_price IS NOT NULL AND p_old_price != '' AND p_is_active = 1 ORDER BY p_id DESC");
                    $statement->execute();
                    $result = $statement->fetchAll(PDO::FETCH_ASSOC);
                    
                    $deals_found = false;
                    foreach ($result as $row) {
                        $old = floatval($row['p_old_price']);
                        $curr = floatval($row['p_current_price']);
                        if ($old > $curr) {
                            $deals_found = true;
                            ?>
                            <div class="col-md-3 col-sm-6">
                                <div class="thumbnail" style="padding: 12px; border-radius: 6px; border: 1px solid #e5e5e5; background: #fff; box-shadow: 0 1px 4px rgba(0,0,0,0.05); margin-bottom: 25px;">
                                    <a href="product.php?id=<?php echo $row['p_id']; ?>" style="display: block; text-decoration: none;">
                                        <div style="height: 180px; width: 100%; display: flex; align-items: center; justify-content: center; background: #fff; border-radius: 4px; overflow: hidden; margin-bottom: 12px; position: relative; border: 1px solid #f0f0f0;">
                                            <?php if (!empty($row['p_featured_photo'])): ?>
                                                <div style="width: 100%; height: 100%; background-image: url('assets/uploads/<?php echo htmlspecialchars($row['p_featured_photo']); ?>'); background-size: contain; background-repeat: no-repeat; background-position: center;"></div>
                                            <?php else: ?>
                                                <i class="fa fa-cubes fa-3x" style="color: #ccc;"></i>
                                            <?php endif; ?>
                                            <span class="label label-danger" style="position: absolute; top: 8px; left: 8px; padding: 4px 7px; font-weight: bold; background-color: #d9534f; border-radius: 3px; font-size: 11px; box-shadow: 0 2px 4px rgba(0,0,0,0.15);">Save <?php echo round((($old - $curr) / $old) * 100); ?>%</span>
                                        </div>
                                    </a>
                                    <div class="caption" style="padding: 0;">
                                        <h5 style="font-weight: bold; margin: 0 0 8px 0; height: 36px; overflow: hidden; text-overflow: ellipsis; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; line-height: 1.2;">
                                            <a href="product.php?id=<?php echo $row['p_id']; ?>" style="color: #1F2937; text-decoration: none;"><?php echo htmlspecialchars($row['p_name']); ?></a>
                                        </h5>
                                        <p style="margin: 0 0 6px 0; font-size: 16px; color: #1F2937; font-weight: bold;">
                                            &#8369;<?php echo htmlspecialchars($row['p_current_price']); ?>
                                            <span style="font-size: 12px; font-weight: normal; color: #888; text-decoration: line-through; margin-left: 5px;">&#8369;<?php echo htmlspecialchars($row['p_old_price']); ?></span>
                                        </p>
                                        <?php if (!empty($row['p_moq'])): ?>
                                        <p style="font-size: 11px; color: #666; margin: 0 0 10px 0;">
                                            <strong>MOQ:</strong> <?php echo htmlspecialchars($row['p_moq']); ?> units
                                        </p>
                                        <?php endif; ?>
                                        <hr style="margin: 10px 0;">
                                        <div style="display: flex; justify-content: space-between; align-items: center;">
                                            <span style="font-size: 11px; font-weight: bold; color: #F59E0B; text-transform: uppercase;"><i class="fa fa-tag"></i> <?php echo !empty($row['p_brand']) ? htmlspecialchars($row['p_brand']) : 'Special Deal'; ?></span>
                                            <a href="product.php?id=<?php echo $row['p_id']; ?>" class="btn btn-warning btn-xs" style="background-color: #F59E0B; border-color: #F59E0B; font-weight: 600; padding: 4px 10px;"><i class="fa fa-shopping-cart"></i> View Deal</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php
                        }
                    }
                    if (!$deals_found) {
                        echo '<div class="col-md-12"><div class="alert alert-info">No active deals found today. Please check back later.</div></div>';
                    }
                    ?>
                </div>

            </div>
        </div>
    </div>
</div>

<?php require_once('footer.php'); ?>
