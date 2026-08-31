<?php require_once('header.php'); ?>

<!-- Hero Search Banner -->
<div class="hero-section" style="background-image: linear-gradient(rgba(31, 41, 55, 0.85), rgba(31, 41, 55, 0.85)), url('assets/uploads/about-banner.jpg'); background-size: cover; background-position: center; padding: 80px 0; text-align: center; color: white;">
    <div class="container">
        <h1 style="font-weight: 800; font-size: 38px; margin-bottom: 15px; text-transform: uppercase; letter-spacing: 1px;">
            Find Construction Materials from <span style="color: #F59E0B;">Trusted Suppliers</span>
        </h1>
        <p style="font-size: 18px; color: #e5e7eb; max-width: 700px; margin: 0 auto 30px auto;">
            SaaS multi-vendor marketplace connecting building contractors with wholesale suppliers. Steel, concrete, plumbing, safety equipment, and power tools.
        </p>
        
        <!-- Large Search Bar -->
        <div class="search-container" style="max-width: 750px; margin: 0 auto;">
            <form action="search-result.php" method="get" style="display: flex; gap: 10px; background: white; padding: 6px; border-radius: 5px; box-shadow: 0 4px 10px rgba(0,0,0,0.15);">
                <?php $csrf->echoInputField(); ?>
                <input type="text" class="form-control" style="border: none; box-shadow: none; font-size: 16px; height: 50px; flex-grow: 1;" placeholder="Search cement, steel, PVC pipes, drills, safety vests..." name="search_text" required>
                <button type="submit" class="btn btn-warning" style="background-color: #F59E0B; border-color: #F59E0B; font-weight: bold; padding: 0 30px; font-size: 16px; height: 50px;">Search Materials</button>
            </form>
        </div>
    </div>
</div>

<!-- B2B Quick Actions -->
<div class="page" style="padding: 40px 0 20px 0; background: #fafafa;">
    <div class="container">
        <div class="row">
            <div class="col-md-6" style="margin-bottom: 20px;">
                <div style="background: white; border: 1px solid #e5e7eb; border-radius: 5px; padding: 30px; display: flex; gap: 20px; align-items: center; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                    <div style="background: #FEF3C7; width: 60px; height: 60px; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                        <i class="fa fa-file-text-o fa-2x" style="color: #F59E0B;"></i>
                    </div>
                    <div>
                        <h4 style="font-weight: bold; margin-top: 0; color: #1F2937;">Request a Custom Quote (RFQ)</h4>
                        <p style="font-size: 13px; color: #6b7280; margin-bottom: 15px;">Submit your material specifications and receive competitive bids from multiple suppliers.</p>
                        <a href="request-quote.php" class="btn btn-warning btn-sm" style="background-color: #F59E0B; border-color: #F59E0B; font-weight: bold;">Request Quotes</a>
                    </div>
                </div>
            </div>
            <div class="col-md-6" style="margin-bottom: 20px;">
                <div style="background: white; border: 1px solid #e5e7eb; border-radius: 5px; padding: 30px; display: flex; gap: 20px; align-items: center; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                    <div style="background: #E0F2FE; width: 60px; height: 60px; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                        <i class="fa fa-truck fa-2x" style="color: #0284c7;"></i>
                    </div>
                    <div>
                        <h4 style="font-weight: bold; margin-top: 0; color: #1F2937;">Contractor Bulk Orders</h4>
                        <p style="font-size: 13px; color: #6b7280; margin-bottom: 15px;">Order truckloads of aggregate, rebar shipments, or large electrical cable rolls directly to your build site.</p>
                        <a href="bulk-orders.php" class="btn btn-warning btn-sm" style="background-color: #F59E0B; border-color: #F59E0B; font-weight: bold;">Order Bulk Freight</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Shop By Category -->
<div class="page" style="padding: 40px 0;">
    <div class="container">
        <h3 style="font-weight: bold; text-align: center; color: #1F2937; margin-bottom: 10px;">Shop By Material Category</h3>
        <p style="text-align: center; color: #6b7280; margin-bottom: 30px;">Direct access to major construction supply categories</p>
        <div class="row">
            <?php
            $statement = $pdo->prepare("SELECT * FROM tbl_mid_category LIMIT 6");
            $statement->execute();
            $result = $statement->fetchAll(PDO::FETCH_ASSOC);
            foreach ($result as $row) {
                ?>
                <div class="col-md-2 col-sm-4 col-xs-6">
                    <div style="background: #f8f9fa; border: 1px solid #e5e7eb; border-radius: 5px; padding: 20px; text-align: center; margin-bottom: 20px; transition: transform 0.2s;">
                        <i class="fa fa-cubes fa-2x" style="color: #F59E0B; margin-bottom: 10px;"></i>
                        <h5 style="font-weight: bold; margin: 0; height: 32px; overflow: hidden; font-size: 13px;">
                            <a href="product-category.php?id=<?php echo $row['mcat_id']; ?>&type=mid-category" style="color: #1F2937;"><?php echo htmlspecialchars($row['mcat_name']); ?></a>
                        </h5>
                    </div>
                </div>
                <?php
            }
            ?>
        </div>
    </div>
</div>

<!-- Featured Suppliers -->
<div class="page" style="padding: 40px 0; background: #fafafa;">
    <div class="container">
        <h3 style="font-weight: bold; text-align: center; color: #1F2937; margin-bottom: 10px;">Featured Material Suppliers</h3>
        <p style="text-align: center; color: #6b7280; margin-bottom: 30px;">Buy directly from verified manufacturers and regional distributors</p>
        
        <div class="row">
            <?php
            $statement = $pdo->prepare("SELECT * FROM tbl_supplier WHERE supplier_status = 'Active' LIMIT 3");
            $statement->execute();
            $result = $statement->fetchAll(PDO::FETCH_ASSOC);
            foreach ($result as $row) {
                ?>
                <div class="col-md-4 col-sm-6">
                    <div class="thumbnail" style="padding: 20px; background: white; border-radius: 5px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); margin-bottom: 25px;">
                        <div style="height: 80px; display: flex; align-items: center; justify-content: center; margin-bottom: 15px; background: #f3f4f6; border-radius: 4px;">
                            <i class="fa fa-industry fa-2x" style="color: #1F2937;"></i>
                        </div>
                        <h4 style="font-weight: bold; color: #1F2937; text-align: center; margin-top: 0;">
                            <?php echo htmlspecialchars($row['supplier_name']); ?>
                            <span class="label label-warning" style="font-size: 10px; background-color: #F59E0B;">Verified</span>
                        </h4>
                        <p style="font-size: 13px; text-align: center; color: #6b7280; height: 40px; overflow: hidden;">
                            <?php echo htmlspecialchars($row['supplier_description'] ?: 'No description available.'); ?>
                        </p>
                        <hr style="margin: 10px 0;">
                        <div style="text-align: center;">
                            <a href="store.php?slug=<?php echo $row['supplier_slug']; ?>" class="btn btn-warning btn-xs" style="background-color: #F59E0B; border-color: #F59E0B; font-weight: bold; padding: 5px 15px;">View Storefront</a>
                        </div>
                    </div>
                </div>
                <?php
            }
            ?>
        </div>
    </div>
</div>

<!-- Featured Products -->
<div class="page" style="padding: 40px 0;">
    <div class="container">
        <h3 style="font-weight: bold; text-align: center; color: #1F2937; margin-bottom: 10px;">Featured Building Materials</h3>
        <p style="text-align: center; color: #6b7280; margin-bottom: 30px;">Sourced from verified marketplace suppliers</p>
        
        <div class="row">
            <?php
            $statement = $pdo->prepare("SELECT * FROM tbl_product WHERE p_is_active = 1 LIMIT 4");
            $statement->execute();
            $result = $statement->fetchAll(PDO::FETCH_ASSOC);
            foreach ($result as $row) {
                ?>
                <div class="col-md-3 col-sm-6">
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
                                <strong>MOQ:</strong> <?php echo htmlspecialchars($row['p_moq']); ?> units | <strong>Brand:</strong> <?php echo htmlspecialchars($row['p_brand']); ?>
                            </p>
                            <hr style="margin: 10px 0;">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <a href="request-quote.php?product_id=<?php echo $row['p_id']; ?>" class="btn btn-default btn-xs" style="font-weight: bold;"><i class="fa fa-file-text-o"></i> RFQ</a>
                                <a href="product.php?id=<?php echo $row['p_id']; ?>" class="btn btn-warning btn-xs" style="background-color: #F59E0B; border-color: #F59E0B; font-weight: bold;">Details</a>
                            </div>
                        </div>
                    </div>
                </div>
                <?php
            }
            ?>
        </div>
    </div>
</div>

<!-- Why Choose Us -->
<div class="page" style="padding: 40px 0; background: #fafafa; border-top: 1px solid #e5e7eb;">
    <div class="container">
        <h3 style="font-weight: bold; text-align: center; color: #1F2937; margin-bottom: 40px;">Industrial Sourcing Solutions</h3>
        <div class="row">
            <div class="col-md-4" style="text-align: center; margin-bottom: 30px;">
                <i class="fa fa-shield fa-3x" style="color: #F59E0B; margin-bottom: 15px;"></i>
                <h4 style="font-weight: bold; color: #1F2937;">Verified B2B Suppliers</h4>
                <p style="font-size: 13px; color: #6b7280; padding: 0 15px;">We vet every manufacturer, warehouse, and supplier registration to guarantee quality standards and genuine certifications.</p>
            </div>
            <div class="col-md-4" style="text-align: center; margin-bottom: 30px;">
                <i class="fa fa-file-text-o fa-3x" style="color: #F59E0B; margin-bottom: 15px;"></i>
                <h4 style="font-weight: bold; color: #1F2937;">RFQ Quotation Workflows</h4>
                <p style="font-size: 13px; color: #6b7280; padding: 0 15px;">Submit item lists once, receive competitive pricing from multiple regional suppliers, compare, and accept bids.</p>
            </div>
            <div class="col-md-4" style="text-align: center; margin-bottom: 30px;">
                <i class="fa fa-road fa-3x" style="color: #F59E0B; margin-bottom: 15px;"></i>
                <h4 style="font-weight: bold; color: #1F2937;">Site Delivery Logistics</h4>
                <p style="font-size: 13px; color: #6b7280; padding: 0 15px;">Coordinated logistics for heavy materials, aggregate transport, flatbed deliveries, and site-unloading cranes.</p>
            </div>
        </div>
    </div>
</div>

<?php require_once('footer.php'); ?>