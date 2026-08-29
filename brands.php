<?php require_once('header.php'); ?>

<div class="page-banner" style="background-image: url(assets/uploads/about-banner.jpg);">
    <div class="inner">
        <h1>Construction Brands</h1>
    </div>
</div>

<div class="page">
    <div class="container">
        <div class="row">            
            <div class="col-md-12">
                <h3>Shop Materials by Trusted Brand Partner</h3>
                <p class="text-muted">Explore high-quality products sourced from our verified industrial manufacturing partners.</p>
                <hr>
                
                <div class="row">
                    <?php
                    $statement = $pdo->prepare("SELECT DISTINCT p_brand FROM tbl_product WHERE p_brand IS NOT NULL AND p_brand != '' ORDER BY p_brand ASC");
                    $statement->execute();
                    $result = $statement->fetchAll(PDO::FETCH_ASSOC);
                    if(count($result) > 0) {
                        foreach ($result as $row) {
                            $brand_name = $row['p_brand'];
                            ?>
                            <div class="col-md-3 col-sm-4 col-xs-6">
                                <div class="thumbnail" style="padding: 20px; text-align: center; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); margin-bottom: 20px;">
                                    <i class="fa fa-tag fa-2x" style="color: #F59E0B; margin-bottom: 10px;"></i>
                                    <h4 style="font-weight: bold; margin-top: 0; color: #1F2937;"><?php echo htmlspecialchars($brand_name); ?></h4>
                                    <a href="search-result.php?search_text=<?php echo urlencode($brand_name); ?>" class="btn btn-warning btn-xs" style="background-color: #F59E0B; border-color: #F59E0B;">View Products</a>
                                </div>
                            </div>
                            <?php
                        }
                    } else {
                        echo '<div class="col-md-12"><div class="alert alert-info">No brands found.</div></div>';
                    }
                    ?>
                </div>

            </div>
        </div>
    </div>
</div>

<?php require_once('footer.php'); ?>
