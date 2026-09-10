<?php require_once('header.php'); ?>

<?php
if(!isset($_REQUEST['id'])) {
	header('location: product.php');
	exit;
}

// Fetch product details and verify supplier tenancy
$statement = $pdo->prepare("SELECT * FROM tbl_product WHERE p_id=?");
$statement->execute(array($_REQUEST['id']));
$product = $statement->fetch(PDO::FETCH_ASSOC);

if (!$product || $product['supplier_id'] != $supplier_id) {
    header('location: product.php');
    exit;
}

if(isset($_POST['form1'])) {
	$valid = 1;
    $error_message = "";

    if(empty($_POST['tcat_id'])) {
        $valid = 0;
        $error_message .= "You must select a top level category<br>";
    }

    if(empty($_POST['mcat_id'])) {
        $valid = 0;
        $error_message .= "You must select a mid level category<br>";
    }

    $final_ecat_id = 0;
    if(empty($_POST['ecat_id'])) {
        $valid = 0;
        $error_message .= "You must select an end level category<br>";
    } elseif($_POST['ecat_id'] == 'other_new') {
        if(empty(trim($_POST['new_ecat_name']))) {
            $valid = 0;
            $error_message .= "Please enter the new end level category name<br>";
        } else {
            $new_ecat_name = trim($_POST['new_ecat_name']);
            $mcat_id = intval($_POST['mcat_id']);
            
            // Check if it already exists
            $statement_ecat_check = $pdo->prepare("SELECT ecat_id FROM tbl_end_category WHERE LOWER(ecat_name) = LOWER(?) AND mcat_id = ?");
            $statement_ecat_check->execute(array($new_ecat_name, $mcat_id));
            $existing_ecat = $statement_ecat_check->fetch(PDO::FETCH_ASSOC);
            
            if($existing_ecat) {
                $final_ecat_id = $existing_ecat['ecat_id'];
            } else {
                $statement_new_ecat = $pdo->prepare("INSERT INTO tbl_end_category (ecat_name, mcat_id) VALUES (?, ?) RETURNING ecat_id");
                $statement_new_ecat->execute(array($new_ecat_name, $mcat_id));
                $new_row = $statement_new_ecat->fetch(PDO::FETCH_ASSOC);
                $final_ecat_id = $new_row['ecat_id'];
            }
        }
    } else {
        $final_ecat_id = intval($_POST['ecat_id']);
    }

    if(empty($_POST['p_name'])) {
        $valid = 0;
        $error_message .= "Product name cannot be empty<br>";
    }

    if(empty($_POST['p_current_price'])) {
        $valid = 0;
        $error_message .= "Current Price cannot be empty<br>";
    }

    if(empty($_POST['p_qty'])) {
        $valid = 0;
        $error_message .= "Quantity cannot be empty<br>";
    }

    $path = $_FILES['p_featured_photo']['name'];
    $path_tmp = $_FILES['p_featured_photo']['tmp_name'];

    if($path!='') {
        $ext = pathinfo( $path, PATHINFO_EXTENSION );
        $file_name = basename( $path, '.' . $ext );
        if( $ext!='jpg' && $ext!='png' && $ext!='jpeg' && $ext!='gif' ) {
            $valid = 0;
            $error_message .= 'You must upload jpg, jpeg, gif or png file<br>';
        }
    }

    if($valid == 1) {
    	if( isset($_FILES['photo']["name"]) && isset($_FILES['photo']["tmp_name"]) )
        {
        	$photo = array();
            $photo = $_FILES['photo']["name"];
            $photo = array_values(array_filter($photo));

        	$photo_temp = array();
            $photo_temp = $_FILES['photo']["tmp_name"];
            $photo_temp = array_values(array_filter($photo_temp));

            $statement = $pdo->prepare("SELECT nextval('tbl_product_photo_pp_id_seq') AS next_id");
			$statement->execute();
			$row_photo = $statement->fetch();
			$z = $row_photo['next_id'];

            $m=0;
            for($i=0;$i<count($photo);$i++)
            {
                $my_ext1 = pathinfo( $photo[$i], PATHINFO_EXTENSION );
		        if( $my_ext1=='jpg' || $my_ext1=='png' || $my_ext1=='jpeg' || $my_ext1=='gif' ) {
		            $final_name1[$m] = $z.'.'.$my_ext1;
                    move_uploaded_file($photo_temp[$i],"../assets/uploads/product_photos/".$final_name1[$m]);
                    $m++;
                    $z++;
		        }
            }

            if(isset($final_name1)) {
            	for($i=0;$i<count($final_name1);$i++)
		        {
		        	$statement = $pdo->prepare("INSERT INTO tbl_product_photo (photo,p_id) VALUES (?,?)");
		        	$statement->execute(array($final_name1[$i],$_REQUEST['id']));
		        }
            }            
        }

        // 1. Authoritative existing values from DB record & inputs
        $existing_c_price = floatval(preg_replace('/[^0-9.]/', '', strval($product['p_current_price'])));
        $orig_q = max(0, intval(preg_replace('/[^0-9]/', '', strval($_POST['p_qty']))));
        $orig_nq = isset($_POST['p_new_qty']) && $_POST['p_new_qty'] !== '' ? max(0, intval($_POST['p_new_qty'])) : 0;
        $s_level = isset($_POST['p_s_level']) && $_POST['p_s_level'] !== '' ? max(0, intval($_POST['p_s_level'])) : 10;

        // 2. Parse & sanitize Capital Price (Ca) and Mark-Up (₱)
        $ca_input = isset($_POST['p_capital_price']) ? trim($_POST['p_capital_price']) : '0';
        $p_capital_price_val = max(0, floatval(preg_replace('/[^0-9.]/', '', strval($ca_input))));

        $mu_input = isset($_POST['p_markup']) ? trim($_POST['p_markup']) : '0';
        $p_markup_val = max(0, floatval(preg_replace('/[^0-9.]/', '', strval($mu_input))));

        // 3. New Price (N): Ca + Mark-Up, or manual custom price if provided
        $default_n_price = round($p_capital_price_val + $p_markup_val, 2);
        $np_input = isset($_POST['p_new_price']) ? trim($_POST['p_new_price']) : '';
        if ($np_input !== '' && is_numeric(preg_replace('/[^0-9.]/', '', $np_input))) {
            $submitted_np = max(0, floatval(preg_replace('/[^0-9.]/', '', $np_input)));
            $n_price_val = ($submitted_np > 0) ? $submitted_np : $default_n_price;
        } else {
            $n_price_val = $default_n_price;
        }

        // 4. Evaluate Price Rules (using original Q and original NQ)
        // Priority 1: IF Q < 6 AND NQ > 5 -> C = N
        // Priority 2: ELSE IF C < N AND Q < 5 AND NQ > 5 -> C = N
        // Priority 3: ELSE IF C > N AND Q > 5 -> C remains unchanged (C = C)
        // Default: ELSE -> C remains unchanged (or initial N if C <= 0)
        if ($orig_q < 6 && $orig_nq > 5) {
            $final_current_price_val = $n_price_val;
        } elseif ($existing_c_price < $n_price_val && $orig_q < 5 && $orig_nq > 5) {
            $final_current_price_val = $n_price_val;
        } elseif ($existing_c_price > $n_price_val && $orig_q > 5) {
            $final_current_price_val = $existing_c_price;
        } else {
            $final_current_price_val = ($existing_c_price > 0) ? $existing_c_price : $n_price_val;
        }

        // 5. Evaluate Inventory Quantity Update Rule
        // Strictly: IF Q < 2 AND NQ > 1 -> Q = Q + NQ, NQ = 0
        // Otherwise (Q >= 2 or NQ <= 1) -> Q = Q, NQ = NQ
        if ($orig_q < 2 && $orig_nq > 1) {
            $final_q_val = $orig_q + $orig_nq;
            $final_nq_val = 0;
        } else {
            $final_q_val = $orig_q;
            $final_nq_val = $orig_nq;
        }

        // Format for database storage
        $p_capital_price = number_format($p_capital_price_val, 2, '.', '');
        $p_markup = number_format($p_markup_val, 2, '.', '');
        $p_new_price = number_format($n_price_val, 2, '.', '');
        $p_current_price = number_format($final_current_price_val, 2, '.', '');
        $p_qty_save = strval($final_q_val);
        $p_new_qty_save = $final_nq_val;
        $p_s_level_save = $s_level;

        if($path == '') {
        	$statement = $pdo->prepare("UPDATE tbl_product SET 
        							p_name=?, 
        							p_old_price=?, 
        							p_current_price=?, 
        							p_qty=?,
        							p_description=?,
        							p_short_description=?,
        							p_feature=?,
        							p_condition=?,
        							p_return_policy=?,
        							p_is_featured=?,
        							p_is_active=?,
        							ecat_id=?,
                                    p_moq=?,
                                    p_brand=?,
                                    p_specs=?,
                                    p_delivery_estimate=?,
                                    p_pdf=?,
                                    p_sku=?,
                                    p_new_price=?,
                                    p_new_qty=?,
                                    p_s_level=?,
                                    p_capital_price=?,
                                    p_markup=?

        							WHERE p_id=?");
        	$statement->execute(array(
        							$_POST['p_name'],
        							$_POST['p_old_price'],
        							$p_current_price,
        							$p_qty_save,
        							$_POST['p_description'],
        							$_POST['p_short_description'],
        							$_POST['p_feature'],
        							$_POST['p_condition'],
        							$_POST['p_return_policy'],
        							$_POST['p_is_featured'],
        							$_POST['p_is_active'],
        							$final_ecat_id,
                                    intval($_POST['p_moq']),
                                    $_POST['p_brand'],
                                    $_POST['p_specs'],
                                    $_POST['p_delivery_estimate'],
                                    $_POST['p_pdf'],
                                    $_POST['p_sku'],
                                    $p_new_price,
                                    $p_new_qty_save,
                                    $p_s_level_save,
                                    $p_capital_price,
                                    $p_markup,
        							$_REQUEST['id']
        						));
        } else {
        	if(file_exists('../assets/uploads/'.$_POST['current_photo'])) {
                unlink('../assets/uploads/'.$_POST['current_photo']);
            }
			$final_name = 'product-featured-'.$_REQUEST['id'].'.'.$ext;
        	move_uploaded_file( $path_tmp, '../assets/uploads/'.$final_name );

        	$statement = $pdo->prepare("UPDATE tbl_product SET 
        							p_name=?, 
        							p_old_price=?, 
        							p_current_price=?, 
        							p_qty=?,
        							p_featured_photo=?,
        							p_description=?,
        							p_short_description=?,
        							p_feature=?,
        							p_condition=?,
        							p_return_policy=?,
        							p_is_featured=?,
        							p_is_active=?,
        							ecat_id=?,
                                    p_moq=?,
                                    p_brand=?,
                                    p_specs=?,
                                    p_delivery_estimate=?,
                                    p_pdf=?,
                                    p_sku=?,
                                    p_new_price=?,
                                    p_new_qty=?,
                                    p_s_level=?,
                                    p_capital_price=?,
                                    p_markup=?

        							WHERE p_id=?");
        	$statement->execute(array(
        							$_POST['p_name'],
        							$_POST['p_old_price'],
        							$p_current_price,
        							$p_qty_save,
        							$final_name,
        							$_POST['p_description'],
        							$_POST['p_short_description'],
        							$_POST['p_feature'],
        							$_POST['p_condition'],
        							$_POST['p_return_policy'],
        							$_POST['p_is_featured'],
        							$_POST['p_is_active'],
        							$final_ecat_id,
                                    intval($_POST['p_moq']),
                                    $_POST['p_brand'],
                                    $_POST['p_specs'],
                                    $_POST['p_delivery_estimate'],
                                    $_POST['p_pdf'],
                                    $_POST['p_sku'],
                                    $p_new_price,
                                    $p_new_qty_save,
                                    $p_s_level_save,
                                    $p_capital_price,
                                    $p_markup,
        							$_REQUEST['id']
        						));
        }

        if(isset($_POST['size'])) {
        	$statement = $pdo->prepare("DELETE FROM tbl_product_size WHERE p_id=?");
        	$statement->execute(array($_REQUEST['id']));
			foreach($_POST['size'] as $value) {
				$statement = $pdo->prepare("INSERT INTO tbl_product_size (size_id,p_id) VALUES (?,?)");
				$statement->execute(array($value,$_REQUEST['id']));
			}
		} else {
			$statement = $pdo->prepare("DELETE FROM tbl_product_size WHERE p_id=?");
        	$statement->execute(array($_REQUEST['id']));
		}

		if(isset($_POST['color'])) {
			$statement = $pdo->prepare("DELETE FROM tbl_product_color WHERE p_id=?");
        	$statement->execute(array($_REQUEST['id']));
			foreach($_POST['color'] as $value) {
				$statement = $pdo->prepare("INSERT INTO tbl_product_color (color_id,p_id) VALUES (?,?)");
				$statement->execute(array($value,$_REQUEST['id']));
			}
		} else {
			$statement = $pdo->prepare("DELETE FROM tbl_product_color WHERE p_id=?");
        	$statement->execute(array($_REQUEST['id']));
		}
	
    	$success_message = 'Product updated successfully.';
        
        // Refresh local details
        $statement = $pdo->prepare("SELECT * FROM tbl_product WHERE p_id=?");
        $statement->execute(array($_REQUEST['id']));
        $product = $statement->fetch(PDO::FETCH_ASSOC);
    }
}
?>

<?php
$p_name = $product['p_name'];
$p_old_price = $product['p_old_price'];
$p_current_price = $product['p_current_price'];
$p_capital_price = isset($product['p_capital_price']) ? $product['p_capital_price'] : '';
$p_markup = (isset($product['p_markup']) && $product['p_markup'] !== null && $product['p_markup'] !== '') ? $product['p_markup'] : '';

$clean_cp = floatval(preg_replace('/[^0-9.]/', '', strval($p_current_price)));
$clean_ca = ($p_capital_price !== '') ? floatval(preg_replace('/[^0-9.]/', '', strval($p_capital_price))) : 0;
$clean_mu = ($p_markup !== '') ? floatval(preg_replace('/[^0-9.]/', '', strval($p_markup))) : 0;

if ($clean_ca <= 0 && $clean_cp > 0) {
    $clean_ca = round($clean_cp * 0.8, 2);
    $clean_mu = round($clean_cp - $clean_ca, 2);
    $p_capital_price = number_format($clean_ca, 2, '.', '');
    $p_markup = number_format($clean_mu, 2, '.', '');
} elseif ($clean_mu <= 0 && $clean_ca > 0 && $clean_cp >= $clean_ca) {
    $clean_mu = round($clean_cp - $clean_ca, 2);
    $p_markup = number_format($clean_mu, 2, '.', '');
}

$clean_np = round($clean_ca + $clean_mu, 2);
$p_new_price = (isset($product['p_new_price']) && floatval(preg_replace('/[^0-9.]/', '', strval($product['p_new_price']))) > 0) ? number_format(floatval(preg_replace('/[^0-9.]/', '', strval($product['p_new_price']))), 2, '.', '') : number_format($clean_np, 2, '.', '');
$p_current_price = number_format($clean_cp, 2, '.', '');
$p_qty = $product['p_qty'];
$p_new_qty = isset($product['p_new_qty']) ? $product['p_new_qty'] : 0;
$p_s_level = isset($product['p_s_level']) ? $product['p_s_level'] : 10;
$p_featured_photo = $product['p_featured_photo'];
$p_description = $product['p_description'];
$p_short_description = $product['p_short_description'];
$p_feature = $product['p_feature'];
$p_condition = $product['p_condition'];
$p_return_policy = $product['p_return_policy'];
$p_is_featured = $product['p_is_featured'];
$p_is_active = $product['p_is_active'];
$ecat_id = $product['ecat_id'];

// B2B attributes
$p_moq = $product['p_moq'];
$p_brand = $product['p_brand'];
$p_specs = $product['p_specs'];
$p_delivery_estimate = $product['p_delivery_estimate'];
$p_pdf = $product['p_pdf'];
$p_sku = $product['p_sku'];

$statement = $pdo->prepare("SELECT * 
                        FROM tbl_end_category t1
                        JOIN tbl_mid_category t2
                        ON t1.mcat_id = t2.mcat_id
                        JOIN tbl_top_category t3
                        ON t2.tcat_id = t3.tcat_id
                        WHERE t1.ecat_id=?");
$statement->execute(array($ecat_id));
$result = $statement->fetchAll(PDO::FETCH_ASSOC);
foreach ($result as $row) {
	$ecat_name = $row['ecat_name'];
    $mcat_id = $row['mcat_id'];
    $tcat_id = $row['tcat_id'];
}
?>

<section class="content-header">
	<div class="content-header-left">
		<h1>Edit Product</h1>
	</div>
	<div class="content-header-right">
		<a href="product.php" class="btn btn-primary btn-sm">View All</a>
	</div>
</section>

<section class="content">
	<div class="row">
		<div class="col-md-12">

			<?php if(isset($error_message) && $error_message != ''): ?>
			<div class="callout callout-danger">
				<p><?php echo $error_message; ?></p>
			</div>
			<?php endif; ?>

			<?php if(isset($success_message) && $success_message != ''): ?>
			<div class="callout callout-success">
				<p><?php echo $success_message; ?></p>
			</div>
			<?php endif; ?>

			<form class="form-horizontal" action="" method="post" enctype="multipart/form-data">
                <input type="hidden" name="current_photo" value="<?php echo $p_featured_photo; ?>">
				<div class="box box-info">
					<div class="box-body">
						<div class="form-group">
							<label for="" class="col-sm-3 control-label">Top Level Category Name <span>*</span></label>
							<div class="col-sm-4">
								<select name="tcat_id" class="form-control select2 top-cat" required>
									<option value="">Select Top Level Category</option>
									<?php
									$statement = $pdo->prepare("SELECT * FROM tbl_top_category ORDER BY tcat_name ASC");
									$statement->execute();
									$result = $statement->fetchAll(PDO::FETCH_ASSOC);	
									foreach ($result as $row) {
										?>
										<option value="<?php echo $row['tcat_id']; ?>" <?php if($row['tcat_id'] == $tcat_id) {echo 'selected';} ?>><?php echo $row['tcat_name']; ?></option>
										<?php
									}
									?>
								</select>
							</div>
						</div>
						<div class="form-group">
							<label for="" class="col-sm-3 control-label">Mid Level Category Name <span>*</span></label>
							<div class="col-sm-4">
								<select name="mcat_id" class="form-control select2 mid-cat" required>
									<option value="">Select Mid Level Category</option>
                                    <?php
									$statement = $pdo->prepare("SELECT * FROM tbl_mid_category WHERE tcat_id = ? ORDER BY mcat_name ASC");
									$statement->execute(array($tcat_id));
									$result = $statement->fetchAll(PDO::FETCH_ASSOC);	
									foreach ($result as $row) {
										?>
										<option value="<?php echo $row['mcat_id']; ?>" <?php if($row['mcat_id'] == $mcat_id) {echo 'selected';} ?>><?php echo $row['mcat_name']; ?></option>
										<?php
									}
									?>
								</select>
							</div>
						</div>
						<div class="form-group">
							<label for="" class="col-sm-3 control-label">End Level Category Name <span>*</span></label>
							<div class="col-sm-4">
								<select name="ecat_id" id="ecat_id" class="form-control select2 end-cat" required>
									<option value="">Select End Level Category</option>
                                    <?php
									$statement = $pdo->prepare("SELECT * FROM tbl_end_category WHERE mcat_id = ? ORDER BY ecat_name ASC");
									$statement->execute(array($mcat_id));
									$result = $statement->fetchAll(PDO::FETCH_ASSOC);	
									foreach ($result as $row) {
										?>
										<option value="<?php echo $row['ecat_id']; ?>" <?php if($row['ecat_id'] == $ecat_id) {echo 'selected';} ?>><?php echo $row['ecat_name']; ?></option>
										<?php
									}
									?>
									<option value="other_new" style="font-weight: bold; color: #2563eb;">+ Add New Category (Not Found in List)</option>
								</select>
							</div>
							<div class="col-sm-4" style="padding-top: 4px;">
								<button type="button" class="btn btn-info btn-sm" onclick="enableNewCategoryInput()"><i class="fa fa-plus"></i> New Category</button>
							</div>
						</div>
						<div class="form-group" id="newCategoryWrapper" style="display: none; background: #f0f7ff; padding: 12px 0; border: 1px dashed #3b82f6; border-radius: 6px; margin-bottom: 15px;">
							<label for="" class="col-sm-3 control-label" style="color: #1d4ed8;">New End Category Name <span>*</span></label>
							<div class="col-sm-4">
								<input type="text" name="new_ecat_name" id="new_ecat_name" class="form-control" placeholder="Enter new category name (e.g. Deformed Rebars)">
								<small class="text-muted">Will automatically create and link this new category under the selected Mid Level Category.</small>
							</div>
							<div class="col-sm-4" style="padding-top: 4px;">
								<button type="button" class="btn btn-default btn-sm" onclick="cancelNewCategoryInput()"><i class="fa fa-times"></i> Cancel</button>
							</div>
						</div>
						<div class="form-group">
							<label for="" class="col-sm-3 control-label">Product Name <span>*</span></label>
							<div class="col-sm-4">
								<input type="text" name="p_name" class="form-control" value="<?php echo htmlspecialchars($p_name); ?>" required>
							</div>
						</div>	
						<div class="form-group">
							<label for="" class="col-sm-3 control-label">SKU / Code</label>
							<div class="col-sm-4">
								<input type="text" name="p_sku" class="form-control" value="<?php echo htmlspecialchars($p_sku); ?>">
							</div>
						</div>
						<div class="form-group">
							<label for="" class="col-sm-3 control-label">Brand</label>
							<div class="col-sm-4">
								<input type="text" name="p_brand" class="form-control" value="<?php echo htmlspecialchars($p_brand); ?>">
							</div>
						</div>
						<div class="form-group">
							<label for="" class="col-sm-3 control-label">Minimum Order Quantity (MOQ)</label>
							<div class="col-sm-4">
								<input type="number" name="p_moq" class="form-control" value="<?php echo htmlspecialchars($p_moq); ?>">
							</div>
						</div>
						<div class="form-group">
							<label for="" class="col-sm-3 control-label">Estimated Delivery Time</label>
							<div class="col-sm-4">
								<input type="text" name="p_delivery_estimate" class="form-control" value="<?php echo htmlspecialchars($p_delivery_estimate); ?>">
							</div>
						</div>
						<div class="form-group">
							<label for="" class="col-sm-3 control-label">Specification Sheet (PDF Name)</label>
							<div class="col-sm-4">
								<input type="text" name="p_pdf" class="form-control" value="<?php echo htmlspecialchars($p_pdf); ?>">
							</div>
						</div>
						<div class="form-group">
							<label for="" class="col-sm-3 control-label">Old Price <br><span style="font-size:10px;font-weight:normal;">(In PHP)</span></label>
							<div class="col-sm-4">
								<input type="text" name="p_old_price" class="form-control" value="<?php echo htmlspecialchars($p_old_price); ?>">
							</div>
						</div>
						<!-- Authoritative Existing Current Price for Client Comparison -->
						<input type="hidden" id="p_existing_current_price" value="<?php echo htmlspecialchars($p_current_price); ?>">

						<!-- Capital Price (Ca) -->
						<div class="form-group" style="background: #f8fafc; padding: 12px 0; border-top: 1.5px solid #e2e8f0; border-bottom: 1px solid #e2e8f0;">
							<label for="p_capital_price" class="col-sm-3 control-label" style="color: #0f172a;">Capital Price (Ca) <span>*</span><br><span style="font-size:10px;font-weight:normal;color:#64748b;">(Original / Base Capital Cost)</span></label>
							<div class="col-sm-4">
								<div class="input-group">
									<span class="input-group-addon" style="font-weight: 700; background: #eff6ff; color: #1d4ed8; font-size: 15px;">₱</span>
									<input type="number" step="0.01" min="0" name="p_capital_price" id="p_capital_price" class="form-control" value="<?php echo htmlspecialchars($p_capital_price); ?>" placeholder="e.g. 100.00" required style="font-weight: 600;">
								</div>
								<small class="text-muted" style="font-size: 11px;"><i class="fa fa-info-circle"></i> Original / base capital cost (Ca) of the product per unit.</small>
							</div>
						</div>

						<!-- Mark-Up (₱) (Fixed Peso Amount) -->
						<div class="form-group" style="background: #f8fafc; padding: 12px 0; border-bottom: 1.5px solid #e2e8f0;">
							<label for="p_markup" class="col-sm-3 control-label" style="color: #0f172a;">Mark-Up (₱) <span>*</span><br><span style="font-size:10px;font-weight:normal;color:#64748b;">(Fixed Peso Amount)</span></label>
							<div class="col-sm-4">
								<div class="input-group">
									<span class="input-group-addon" style="font-weight: 700; background: #eff6ff; color: #1d4ed8; font-size: 15px;">₱</span>
									<input type="number" step="0.01" min="0" name="p_markup" id="p_markup" class="form-control" value="<?php echo htmlspecialchars($p_markup); ?>" placeholder="e.g. 20.00" required style="font-weight: 600;">
								</div>
								<small class="text-muted" style="font-size: 11px;"><i class="fa fa-info-circle"></i> Fixed peso amount (₱) added to Capital Price (not a percentage).</small>
							</div>
						</div>

						<!-- New Price (N) - Automatically Calculated or Manually Editable -->
						<div class="form-group" style="background: #f0fdf4; padding: 12px 0; border-bottom: 1px solid #bbf7d0;">
							<label for="p_new_price" class="col-sm-3 control-label" style="color: #166534;">New Price (N) <span>*</span><br><span style="font-size:10px;font-weight:normal;color:#15803d;">(Calculated or Manual Edit)</span></label>
							<div class="col-sm-4">
								<div class="input-group">
									<span class="input-group-addon" style="font-weight: 700; background: #dcfce7; color: #166534; font-size: 15px;">₱</span>
									<input type="number" step="0.01" min="0" name="p_new_price" id="p_new_price" class="form-control" value="<?php echo htmlspecialchars($p_new_price); ?>" required style="font-weight: 700; font-size: 15px; color: #166534;">
								</div>
								<small style="color: #15803d; font-size: 11px;"><i class="fa fa-calculator"></i> Auto-calculates as <code>N = Ca + ₱ Mark-Up</code>, or enter custom manual price.</small>
							</div>
						</div>	

						<!-- Current Price (C) - Result after Price Rule -->
						<div class="form-group" style="padding: 12px 0; border-bottom: 1px solid #e2e8f0;">
							<label for="p_current_price" class="col-sm-3 control-label" style="color: #0f172a;">Current Price (C) <span>*</span><br><span style="font-size:10px;font-weight:normal;color:#64748b;">(Result after Price Rule)</span></label>
							<div class="col-sm-4">
								<div class="input-group">
									<span class="input-group-addon" style="font-weight: 700; background: #eff6ff; color: #1e40af; font-size: 15px;">₱</span>
									<input type="text" name="p_current_price" id="p_current_price" class="form-control" value="<?php echo htmlspecialchars($p_current_price); ?>" required style="font-weight: 800; font-size: 16px; color: #1e40af; background-color: #f8fafc;" readonly>
								</div>
								<div id="p_price_rule_notice" style="font-size: 11.5px; margin-top: 6px; font-weight: 600;"></div>
							</div>
						</div>	

						<!-- Quantity in Stock (Current) (Q) -->
						<div class="form-group" style="padding: 12px 0; border-bottom: 1px solid #f1f5f9;">
							<label for="p_qty" class="col-sm-3 control-label">Quantity in Stock (Current) (Q) <span>*</span></label>
							<div class="col-sm-4">
								<div class="input-group">
									<input type="number" min="0" name="p_qty" id="p_qty" class="form-control" value="<?php echo htmlspecialchars($p_qty); ?>" required style="font-weight: 700; font-size: 15px;">
									<span class="input-group-addon" id="p_stock_alert_badge" style="font-weight: 700; font-size: 12px; border-radius: 0 4px 4px 0;"></span>
								</div>
								<div id="p_stock_alert_desc" style="font-size: 11.5px; margin-top: 5px; font-weight: 600;"></div>
							</div>
						</div>

						<!-- (N)Quantity (New Quantity) (NQ) -->
						<div class="form-group" style="padding: 12px 0; border-bottom: 1px solid #f1f5f9; background: #fafafa;">
							<label for="p_new_qty" class="col-sm-3 control-label">(N)Quantity (New Quantity) (NQ)</label>
							<div class="col-sm-4">
								<input type="number" min="0" name="p_new_qty" id="p_new_qty" class="form-control" value="<?php echo htmlspecialchars($p_new_qty); ?>" style="font-weight: 600;">
								<small class="text-muted" style="font-size: 11px;"><i class="fa fa-cubes"></i> Incoming replenishment stock. Trigger: <code>Q &lt; 2 AND NQ &gt; 1</code> or <code>Q &lt; 6 AND NQ &gt; 5</code> rolls NQ into Q.</small>
								<div id="p_qty_rule_notice" style="font-size: 11.5px; margin-top: 4px; font-weight: 600;"></div>
							</div>
						</div>

						<!-- Safety Stock Level (S) -->
						<div class="form-group" style="padding: 12px 0; border-bottom: 1px solid #f1f5f9;">
							<label for="p_s_level" class="col-sm-3 control-label">Safety Stock Level (S) <span>*</span></label>
							<div class="col-sm-4">
								<input type="number" min="0" name="p_s_level" id="p_s_level" class="form-control" value="<?php echo htmlspecialchars($p_s_level); ?>" style="font-weight: 600;">
								<small class="text-muted" style="font-size: 11px;"><i class="fa fa-shield"></i> Threshold buffer: RED if <code>Q &lt; 50% of S</code>, ORANGE if <code>50% &le; Q &lt; 80% of S</code>, NORMAL if <code>Q &ge; 80% of S</code>.</small>
							</div>
						</div>
						<div class="form-group">
							<label for="" class="col-sm-3 control-label">Select Size</label>
							<div class="col-sm-4">
								<select name="size[]" class="form-control select2" multiple="multiple">
									<?php
                                    $statement = $pdo->prepare("SELECT * FROM tbl_product_size WHERE p_id=?");
                                    $statement->execute(array($_REQUEST['id']));
                                    $current_sizes = array();
                                    $res_sizes = $statement->fetchAll(PDO::FETCH_ASSOC);
                                    foreach($res_sizes as $s) {
                                        $current_sizes[] = $s['size_id'];
                                    }

									$statement = $pdo->prepare("SELECT * FROM tbl_size ORDER BY size_id ASC");
									$statement->execute();
									$result = $statement->fetchAll(PDO::FETCH_ASSOC);			
									foreach ($result as $row) {
										?>
										<option value="<?php echo $row['size_id']; ?>" <?php if(in_array($row['size_id'], $current_sizes)) {echo 'selected';} ?>><?php echo $row['size_name']; ?></option>
										<?php
									}
									?>
								</select>
							</div>
						</div>
						<div class="form-group">
							<label for="" class="col-sm-3 control-label">Select Color</label>
							<div class="col-sm-4">
								<select name="color[]" class="form-control select2" multiple="multiple">
									<?php
                                    $statement = $pdo->prepare("SELECT * FROM tbl_product_color WHERE p_id=?");
                                    $statement->execute(array($_REQUEST['id']));
                                    $current_colors = array();
                                    $res_colors = $statement->fetchAll(PDO::FETCH_ASSOC);
                                    foreach($res_colors as $c) {
                                        $current_colors[] = $c['color_id'];
                                    }

									$statement = $pdo->prepare("SELECT * FROM tbl_color ORDER BY color_id ASC");
									$statement->execute();
									$result = $statement->fetchAll(PDO::FETCH_ASSOC);			
									foreach ($result as $row) {
										?>
										<option value="<?php echo $row['color_id']; ?>" <?php if(in_array($row['color_id'], $current_colors)) {echo 'selected';} ?>><?php echo $row['color_name']; ?></option>
										<?php
									}
									?>
								</select>
							</div>
						</div>
						<div class="form-group">
							<label for="" class="col-sm-3 control-label">Featured Photo</label>
							<div class="col-sm-4" style="padding-top:4px;">
								<input type="file" name="p_featured_photo">
                                <br>
                                <img src="../assets/uploads/<?php echo $p_featured_photo; ?>" style="width:120px;">
							</div>
						</div>
						<div class="form-group">
							<label for="" class="col-sm-3 control-label">Description</label>
							<div class="col-sm-8">
								<textarea name="p_description" class="form-control" cols="30" rows="10" id="editor1"><?php echo $p_description; ?></textarea>
							</div>
						</div>
						<div class="form-group">
							<label for="" class="col-sm-3 control-label">Technical Specifications</label>
							<div class="col-sm-8">
								<textarea name="p_specs" class="form-control" cols="30" rows="5"><?php echo htmlspecialchars($p_specs); ?></textarea>
							</div>
						</div>
						<div class="form-group">
							<label for="" class="col-sm-3 control-label">Short Description</label>
							<div class="col-sm-8">
								<textarea name="p_short_description" class="form-control" cols="30" rows="10" id="editor2"><?php echo $p_short_description; ?></textarea>
							</div>
						</div>
						<div class="form-group">
							<label for="" class="col-sm-3 control-label">Highlights</label>
							<div class="col-sm-8">
								<textarea name="p_feature" class="form-control" cols="30" rows="10" id="editor3"><?php echo $p_feature; ?></textarea>
							</div>
						</div>
						<div class="form-group">
							<label for="" class="col-sm-3 control-label">Conditions</label>
							<div class="col-sm-8">
								<textarea name="p_condition" class="form-control" cols="30" rows="10" id="editor4"><?php echo $p_condition; ?></textarea>
							</div>
						</div>
						<div class="form-group">
							<label for="" class="col-sm-3 control-label">Delivery & Return Policy</label>
							<div class="col-sm-8">
								<textarea name="p_return_policy" class="form-control" cols="30" rows="10" id="editor5"><?php echo $p_return_policy; ?></textarea>
							</div>
						</div>
						<div class="form-group">
							<label for="" class="col-sm-3 control-label">Is Featured?</label>
							<div class="col-sm-8">
								<select name="p_is_featured" class="form-control" style="width:auto;">
									<option value="0" <?php if($p_is_featured == 0) {echo 'selected';} ?>>No</option>
									<option value="1" <?php if($p_is_featured == 1) {echo 'selected';} ?>>Yes</option>
								</select> 
							</div>
						</div>
						<div class="form-group">
							<label for="" class="col-sm-3 control-label">Is Active?</label>
							<div class="col-sm-8">
								<select name="p_is_active" class="form-control" style="width:auto;">
									<option value="0" <?php if($p_is_active == 0) {echo 'selected';} ?>>No</option>
									<option value="1" <?php if($p_is_active == 1) {echo 'selected';} ?>>Yes</option>
								</select> 
							</div>
						</div>
						<div class="form-group">
							<label for="" class="col-sm-3 control-label"></label>
							<div class="col-sm-6">
								<button type="submit" class="btn btn-success pull-left" name="form1">Update Product</button>
							</div>
						</div>
					</div>
				</div>
			</form>
		</div>
	</div>
<script>
function checkNewCategoryOption(val) {
    if (val === 'other_new') {
        $('#newCategoryWrapper').slideDown(200);
        $('#new_ecat_name').focus();
    } else {
        $('#newCategoryWrapper').slideUp(200);
        $('#new_ecat_name').val('');
    }
}

function enableNewCategoryInput() {
    var midCatVal = $('.mid-cat').val();
    if (!midCatVal) {
        alert('Please select a Top Level Category and Mid Level Category first.');
        $('.top-cat').focus();
        return;
    }
    
    // Add option if not present
    if ($('.end-cat option[value="other_new"]').length === 0) {
        $('.end-cat').append('<option value="other_new">+ Add New Category (Not Found in List)</option>');
    }
    
    $('.end-cat').val('other_new').trigger('change');
    $('#newCategoryWrapper').slideDown(200);
    $('#new_ecat_name').focus();
}

function cancelNewCategoryInput() {
    $('.end-cat').val('').trigger('change');
    $('#newCategoryWrapper').slideUp(200);
    $('#new_ecat_name').val('');
}

$(document).ready(function() {
    $(document).on('change', '.end-cat', function() {
        checkNewCategoryOption($(this).val());
    });

    function cleanNum(val) {
        if (!val) return 0;
        var clean = val.toString().replace(/[^0-9.]/g, '');
        return parseFloat(clean) || 0;
    }

    function cleanInt(val) {
        if (!val) return 0;
        var clean = val.toString().replace(/[^0-9]/g, '');
        return parseInt(clean, 10) || 0;
    }

    function evaluateRulesLive() {
        var ca = cleanNum($('#p_capital_price').val());
        var markup = cleanNum($('#p_markup').val());
        var existingC = cleanNum($('#p_existing_current_price').val());
        var manualN = cleanNum($('#p_new_price').val());
        var q = cleanInt($('#p_qty').val());
        var nq = cleanInt($('#p_new_qty').val());
        var s = cleanInt($('#p_s_level').val());
        if (s <= 0) s = 10;

        // 1. New Price (N)
        var calcN = Math.round((ca + markup) * 100) / 100;
        var nPrice = (manualN > 0) ? manualN : calcN;
        if (document.activeElement && document.activeElement.id !== 'p_new_price' && manualN <= 0) {
            $('#p_new_price').val(calcN.toFixed(2));
            nPrice = calcN;
        }

        // 2. Price Rule evaluation (using original Q and NQ)
        var resultingC = existingC;
        var priceNotice = $('#p_price_rule_notice');

        if (q < 6 && nq > 5) {
            resultingC = nPrice;
            priceNotice.html('<span style="color: #047857;"><i class="fa fa-check-circle"></i> <strong>Low Stock Replenishment Rule (Q &lt; 6 &amp; NQ &gt; 5):</strong> Current Price will update to New Price (<strong>₱' + resultingC.toFixed(2) + '</strong>).</span>');
        } else if (existingC < nPrice && q < 5 && nq > 5) {
            resultingC = nPrice;
            priceNotice.html('<span style="color: #047857;"><i class="fa fa-check-circle"></i> <strong>Low Stock Rule (C &lt; N &amp; Q &lt; 5 &amp; NQ &gt; 5):</strong> Current Price will update to New Price (<strong>₱' + resultingC.toFixed(2) + '</strong>).</span>');
        } else if (existingC > nPrice && q > 5) {
            resultingC = existingC;
            priceNotice.html('<span style="color: #d97706;"><i class="fa fa-shield"></i> <strong>Price Protected (C &gt; N &amp; Q &gt; 5):</strong> Current Price retained unchanged at <strong>₱' + existingC.toFixed(2) + '</strong>.</span>');
        } else {
            if (existingC > 0) {
                resultingC = existingC;
                priceNotice.html('<span class="text-muted"><i class="fa fa-info-circle"></i> Current Price retained at ₱' + existingC.toFixed(2) + '. Updates to New Price (₱' + nPrice.toFixed(2) + ') upon low-stock replenishment (Q &lt; 6 &amp; NQ &gt; 5).</span>');
            } else {
                resultingC = nPrice;
                priceNotice.html('<span class="text-success"><i class="fa fa-info-circle"></i> Initial Price set to New Price ₱' + resultingC.toFixed(2) + '.</span>');
            }
        }
        $('#p_current_price').val(resultingC.toFixed(2));

        // 3. Quantity Rule evaluation
        var qtyNotice = $('#p_qty_rule_notice');
        if (q < 2 && nq > 1) {
            var expectedQ = q + nq;
            qtyNotice.html('<span style="color: #047857;"><i class="fa fa-refresh"></i> <strong>Inventory Transfer Triggered (Q &lt; 2 &amp; NQ &gt; 1):</strong> Upon saving, ' + nq + ' units will be transferred into stock (Q: ' + q + ' &rarr; <strong>' + expectedQ + '</strong>, NQ &rarr; <strong>0</strong>).</span>');
        } else if (q >= 2 && nq > 0) {
            qtyNotice.html('<span class="text-muted"><i class="fa fa-info-circle"></i> ' + nq + ' units staged as New Quantity (Quantity transfer does NOT trigger because Q &ge; 2).</span>');
        } else if (nq > 0) {
            qtyNotice.html('<span class="text-muted"><i class="fa fa-info-circle"></i> ' + nq + ' units staged as New Quantity.</span>');
        } else {
            qtyNotice.html('');
        }

        // 4. Stock Alert calculation (evaluated on expected final Q against S)
        var alertQ = (q < 2 && nq > 1) ? (q + nq) : q;
        var halfS = 0.50 * s;
        var eightyS = 0.80 * s;
        var badge = $('#p_stock_alert_badge');
        var desc = $('#p_stock_alert_desc');

        if (alertQ < halfS) {
            // RED: Q < 50% of S (Priority 1)
            badge.css({'background-color': '#ef4444', 'color': '#ffffff'}).html('<i class="fa fa-exclamation-triangle"></i> RED ALERT');
            desc.html('<span style="color: #dc2626; font-weight: 700;"><i class="fa fa-exclamation-circle"></i> Critical Depletion (Q &lt; 50% &times; S): Stock (' + alertQ + ') &lt; 50% of Safety Level (' + s + '). Replenishment urgently required.</span>');
            $('#p_qty').css({'border-color': '#ef4444', 'background-color': '#fef2f2'});
        } else if (alertQ < eightyS) {
            // ORANGE: 50% <= Q < 80% of S (Priority 2)
            badge.css({'background-color': '#f97316', 'color': '#ffffff'}).html('<i class="fa fa-exclamation-circle"></i> ORANGE ALERT');
            desc.html('<span style="color: #ea580c; font-weight: 700;"><i class="fa fa-warning"></i> Low Stock Warning (50% &le; Q &lt; 80% &times; S): Stock (' + alertQ + ') is between 50% and 80% of Safety Level (' + s + ').</span>');
            $('#p_qty').css({'border-color': '#f97316', 'background-color': '#fff7ed'});
        } else {
            // NORMAL: Q >= 80% of S (Priority 3)
            badge.css({'background-color': '#10b981', 'color': '#ffffff'}).html('<i class="fa fa-check"></i> NORMAL');
            desc.html('<span style="color: #059669; font-weight: 600;"><i class="fa fa-check-circle"></i> Acceptable Stock (Q &ge; 80% &times; S): Stock (' + alertQ + ') &ge; 80% of Safety Level (' + s + ').</span>');
            $('#p_qty').css({'border-color': '#cbd5e1', 'background-color': '#ffffff'});
        }
    }

    // Event listeners
    $('#p_capital_price, #p_markup').on('input keyup change', function() {
        var ca = cleanNum($('#p_capital_price').val());
        var markup = cleanNum($('#p_markup').val());
        var calcN = Math.round((ca + markup) * 100) / 100;
        $('#p_new_price').val(calcN.toFixed(2));
        evaluateRulesLive();
    });

    $('#p_new_price, #p_qty, #p_new_qty, #p_s_level').on('input keyup change', function() {
        evaluateRulesLive();
    });

    // Initial evaluation on load
    evaluateRulesLive();
});
</script>

<?php require_once('footer.php'); ?>