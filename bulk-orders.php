<?php require_once('header.php'); ?>

<div class="page-banner" style="background-image: url(assets/uploads/about-banner.jpg);">
    <div class="inner">
        <h1>Bulk Orders & Contractor Logistics</h1>
    </div>
</div>

<div class="page">
    <div class="container">
        <div class="row">            
            <div class="col-md-7">
                <h3>Submit a Bulk Order Inquiry</h3>
                <p>For large-scale construction projects, housing developments, or industrial utility infrastructure, we offer customized freight shipping and wholesale pricing. Provide your order details below, and our verified suppliers will prepare pricing and logistics delivery schedules.</p>
                <hr>

                <?php
                if(isset($_POST['form_bulk'])) {
                    $valid = 1;
                    if(empty($_POST['name']) || empty($_POST['email']) || empty($_POST['materials']) || empty($_POST['qty'])) {
                        $valid = 0;
                        $error_message = "All fields are required.";
                    }
                    if($valid == 1) {
                        // In a real application we would store or email this. Let's show a success message!
                        $success_message = "Your bulk order request has been received. Our logistics suppliers will respond with quotes within 24 hours.";
                    }
                }
                ?>

                <?php if(isset($error_message) && $error_message != ''): ?>
                    <div class="alert alert-danger"><?php echo $error_message; ?></div>
                <?php endif; ?>
                <?php if(isset($success_message) && $success_message != ''): ?>
                    <div class="alert alert-success"><?php echo $success_message; ?></div>
                <?php endif; ?>

                <form action="bulk-orders.php" method="post">
                    <div class="form-group">
                        <label for="name">Full Name / Company Name *</label>
                        <input type="text" class="form-control" name="name" required>
                    </div>
                    <div class="form-group">
                        <label for="email">Business Email *</label>
                        <input type="email" class="form-control" name="email" required>
                    </div>
                    <div class="form-group">
                        <label for="materials">Materials Required (e.g. Steel Rebar, Portland Cement, etc.) *</label>
                        <textarea class="form-control" name="materials" rows="4" placeholder="List item names and specifications..." required></textarea>
                    </div>
                    <div class="form-group">
                        <label for="qty">Estimated Quantity (e.g. 500 Tons, 10,000 Bags) *</label>
                        <input type="text" class="form-control" name="qty" required>
                    </div>
                    <div class="form-group">
                        <label for="address">Delivery Location / Construction Site Address</label>
                        <input type="text" class="form-control" name="address">
                    </div>
                    <button type="submit" class="btn btn-warning" name="form_bulk" style="background-color: #F59E0B; border-color: #F59E0B; font-weight: bold;">Submit Bulk RFQ</button>
                </form>
            </div>
            
            <div class="col-md-5">
                <div style="background: #f8f9fa; border: 1px solid #ddd; padding: 25px; border-radius: 5px;">
                    <h4><strong>Marketplace Logistics Benefits:</strong></h4>
                    <hr>
                    <ul style="padding-left: 20px; line-height: 2;">
                        <li><strong>Wholesale Discounts:</strong> Direct-from-factory pricing.</li>
                        <li><strong>Freight Shipping:</strong> Flatbeds, mixers, and bulk container shipping scheduled directly to your build site.</li>
                        <li><strong>Flexible Credit Terms:</strong> Supplier-approved B2B credit lines.</li>
                        <li><strong>Multi-Vendor Sourcing:</strong> Combine materials from multiple suppliers into coordinated delivery dates.</li>
                    </ul>
                    <div style="margin-top: 30px; text-align: center;">
                        <i class="fa fa-truck fa-5x" style="color: #1F2937;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once('footer.php'); ?>
