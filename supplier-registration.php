<?php require_once('header.php'); ?>

<div class="page-banner" style="background-image: url(assets/uploads/about-banner.jpg);">
    <div class="inner">
        <h1>Supplier Registration</h1>
    </div>
</div>

<div class="page">
    <div class="container">
        <div class="row">            
            <div class="col-md-8 col-md-offset-2">
                <div class="login-container" style="background: #f8f9fa; border: 1px solid #ddd; padding: 30px; border-radius: 5px;">
                    <h3 style="font-weight: bold; color: #1F2937; margin-top: 0;">Register Your Building Supply Company</h3>
                    <p class="text-muted">Join the marketplace, set up your supplier storefront, and start receiving purchase orders and RFQs.</p>
                    <hr>

                    <?php
                    if(isset($_POST['form_register_supplier'])) {
                        $valid = 1;
                        
                        $company_name = trim($_POST['company_name']);
                        $email = trim($_POST['email']);
                        $phone = trim($_POST['phone']);
                        $address = trim($_POST['address']);
                        $description = trim($_POST['description']);
                        $plan = $_POST['plan'];
                        $password = $_POST['password'];
                        
                        // Check if fields are empty
                        if(empty($company_name) || empty($email) || empty($password)) {
                            $valid = 0;
                            $error_message = "Company Name, Email, and Password are required.";
                        }
                        
                        // Check email uniqueness in suppliers and supplier users
                        if($valid == 1) {
                            $statement = $pdo->prepare("SELECT * FROM tbl_supplier WHERE supplier_email=?");
                            $statement->execute(array($email));
                            if($statement->rowCount() > 0) {
                                $valid = 0;
                                $error_message = "This email is already registered.";
                            }
                        }

                        if($valid == 1) {
                            $statement = $pdo->prepare("SELECT * FROM tbl_supplier_user WHERE email=?");
                            $statement->execute(array($email));
                            if($statement->rowCount() > 0) {
                                $valid = 0;
                                $error_message = "This email is already registered to a supplier account.";
                            }
                        }

                        if($valid == 1) {
                            // Generate unique slug
                            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $company_name)));
                            $statement = $pdo->prepare("SELECT * FROM tbl_supplier WHERE supplier_slug=?");
                            $statement->execute(array($slug));
                            if($statement->rowCount() > 0) {
                                $slug = $slug . '-' . rand(100, 999);
                            }

                            // Insert into tbl_supplier
                            $statement = $pdo->prepare("INSERT INTO tbl_supplier (supplier_name, supplier_slug, supplier_description, supplier_address, supplier_email, supplier_phone, supplier_status, supplier_plan, supplier_commission) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                            $statement->execute(array($company_name, $slug, $description, $address, $email, $phone, 'Active', $plan, 5.00));
                            
                            $supplier_id = $pdo->lastInsertId();

                            // Insert into tbl_supplier_user
                            $hashed_pass = md5($password);
                            $statement2 = $pdo->prepare("INSERT INTO tbl_supplier_user (supplier_id, full_name, email, password, role, status) VALUES (?, ?, ?, ?, ?, ?)");
                            $statement2->execute(array($supplier_id, $company_name . ' Manager', $email, $hashed_pass, 'Admin', 'Active'));

                            $success_message = "Your supplier storefront has been successfully registered! You can now log into the <a href='supplier/login.php' style='color: #F59E0B; font-weight: bold;'>Supplier Portal</a> to add products and configure your storefront.";
                        }
                    }
                    ?>

                    <?php if(isset($error_message) && $error_message != ''): ?>
                        <div class="alert alert-danger"><?php echo $error_message; ?></div>
                    <?php endif; ?>
                    <?php if(isset($success_message) && $success_message != ''): ?>
                        <div class="alert alert-success"><?php echo $success_message; ?></div>
                    <?php endif; ?>

                    <form action="supplier-registration.php" method="post">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="company_name">Company Name / Supplier Name *</label>
                                    <input type="text" class="form-control" name="company_name" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="email">Business Email Address *</label>
                                    <input type="email" class="form-control" name="email" required>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="password">Password *</label>
                                    <input type="password" class="form-control" name="password" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="phone">Business Phone Number</label>
                                    <input type="text" class="form-control" name="phone">
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="plan">Choose B2B SaaS Plan *</label>
                            <select class="form-control" name="plan" required>
                                <option value="Starter">Starter Plan (₱49/mo - Up to 50 Products)</option>
                                <option value="Professional" selected>Professional Plan (₱99/mo - Up to 500 Products)</option>
                                <option value="Enterprise">Enterprise Plan (₱249/mo - Unlimited Products)</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="address">Corporate / Warehouse Address</label>
                            <input type="text" class="form-control" name="address">
                        </div>

                        <div class="form-group">
                            <label for="description">Company Description / Scope of Supplies</label>
                            <textarea class="form-control" name="description" rows="3" placeholder="Briefly describe what materials you supply (e.g., steel distributor, masonry plant)..."></textarea>
                        </div>

                        <button type="submit" class="btn btn-warning btn-block" name="form_register_supplier" style="background-color: #F59E0B; border-color: #F59E0B; font-weight: bold; font-size: 16px;">Complete Registration</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once('footer.php'); ?>
