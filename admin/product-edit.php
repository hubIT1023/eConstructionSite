<?php require_once('header.php'); ?>

<?php
if(isset($_POST['form1'])) {
	$valid = 1;

    if(empty($_POST['tcat_id'])) {
        $valid = 0;
        $error_message .= "You must have to select a top level category<br>";
    }

    if(empty($_POST['mcat_id'])) {
        $valid = 0;
        $error_message .= "You must have to select a mid level category<br>";
    }

    if(empty($_POST['ecat_id'])) {
        $valid = 0;
        $error_message .= "You must have to select an end level category<br>";
    }

    if(empty($_POST['p_name'])) {
        $valid = 0;
        $error_message .= "Product name can not be empty<br>";
    }

    if(empty($_POST['p_current_price'])) {
        $valid = 0;
        $error_message .= "Current Price can not be empty<br>";
    }

    if(empty($_POST['p_qty'])) {
        $valid = 0;
        $error_message .= "Quantity can not be empty<br>";
    }

    $path = $_FILES['p_featured_photo']['name'];
    $path_tmp = $_FILES['p_featured_photo']['tmp_name'];

    if($path!='') {
        $ext = pathinfo( $path, PATHINFO_EXTENSION );
        $file_name = basename( $path, '.' . $ext );
        if( $ext!='jpg' && $ext!='png' && $ext!='jpeg' && $ext!='gif' ) {
            $valid = 0;
            $error_message .= 'You must have to upload jpg, jpeg, gif or png file<br>';
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

        	$statement = $pdo->prepare("SHOW TABLE STATUS LIKE 'tbl_product_photo'");
			$statement->execute();
			$result = $statement->fetchAll();
			foreach($result as $row) {
				$next_id1=$row[10];
			}
			$z = $next_id1;

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

        $p_capital_price = isset($_POST['p_capital_price']) ? trim($_POST['p_capital_price']) : '';
        $p_markup = (isset($_POST['p_markup']) && trim($_POST['p_markup']) !== '') ? trim($_POST['p_markup']) : '20';
        $clean_markup = floatval(preg_replace('/[^0-9.]/', '', strval($p_markup)));
        if ($clean_markup <= 0) $clean_markup = 20;

        $submitted_new_price = (isset($_POST['p_new_price']) && $_POST['p_new_price'] !== '') ? $_POST['p_new_price'] : $_POST['p_current_price'];

        if ($p_capital_price === '' || floatval(preg_replace('/[^0-9.]/', '', strval($p_capital_price))) <= 0) {
            $effective_np = !empty($submitted_new_price) ? floatval(preg_replace('/[^0-9.]/', '', strval($submitted_new_price))) : floatval(preg_replace('/[^0-9.]/', '', strval($_POST['p_current_price'])));
            if ($effective_np > 0) {
                $p_capital_price = number_format(round($effective_np / (1 + ($clean_markup / 100)), 2), 2, '.', '');
            } else {
                $p_capital_price = '0.00';
            }
        }

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
        							p_new_price=?,
        							p_new_qty=?,
        							p_s_level=?,
        							p_capital_price=?,
        							p_markup=?

        							WHERE p_id=?");
        	$statement->execute(array(
        							$_POST['p_name'],
        							$_POST['p_old_price'],
        							$_POST['p_current_price'],
        							$_POST['p_qty'],
        							$_POST['p_description'],
        							$_POST['p_short_description'],
        							$_POST['p_feature'],
        							$_POST['p_condition'],
        							$_POST['p_return_policy'],
        							$_POST['p_is_featured'],
        							$_POST['p_is_active'],
        							$_POST['ecat_id'],
        							$submitted_new_price,
        							(isset($_POST['p_new_qty']) && $_POST['p_new_qty'] !== '') ? intval($_POST['p_new_qty']) : 0,
        							(isset($_POST['p_s_level']) && $_POST['p_s_level'] !== '') ? intval($_POST['p_s_level']) : 10,
        							$p_capital_price,
        							$p_markup,
        							$_REQUEST['id']
        						));
        } else {

        	unlink('../assets/uploads/'.$_POST['current_photo']);

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
        							p_new_price=?,
        							p_new_qty=?,
        							p_s_level=?,
        							p_capital_price=?,
        							p_markup=?

        							WHERE p_id=?");
        	$statement->execute(array(
        							$_POST['p_name'],
        							$_POST['p_old_price'],
        							$_POST['p_current_price'],
        							$_POST['p_qty'],
        							$final_name,
        							$_POST['p_description'],
        							$_POST['p_short_description'],
        							$_POST['p_feature'],
        							$_POST['p_condition'],
        							$_POST['p_return_policy'],
        							$_POST['p_is_featured'],
        							$_POST['p_is_active'],
        							$_POST['ecat_id'],
        							$submitted_new_price,
        							(isset($_POST['p_new_qty']) && $_POST['p_new_qty'] !== '') ? intval($_POST['p_new_qty']) : 0,
        							(isset($_POST['p_s_level']) && $_POST['p_s_level'] !== '') ? intval($_POST['p_s_level']) : 10,
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
	
    	$success_message = 'Product is updated successfully.';
    }
}
?>

<?php
if(!isset($_REQUEST['id'])) {
	header('location: logout.php');
	exit;
} else {
	// Check the id is valid or not
	$statement = $pdo->prepare("SELECT * FROM tbl_product WHERE p_id=?");
	$statement->execute(array($_REQUEST['id']));
	$total = $statement->rowCount();
	$result = $statement->fetchAll(PDO::FETCH_ASSOC);
	if( $total == 0 ) {
		header('location: logout.php');
		exit;
	}
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

<?php
$statement = $pdo->prepare("SELECT * FROM tbl_product WHERE p_id=?");
$statement->execute(array($_REQUEST['id']));
$result = $statement->fetchAll(PDO::FETCH_ASSOC);
foreach ($result as $row) {
	$p_name = $row['p_name'];
	$p_old_price = $row['p_old_price'];
	$p_current_price = $row['p_current_price'];
	$p_new_price = isset($row['p_new_price']) ? $row['p_new_price'] : '';
	$p_capital_price = isset($row['p_capital_price']) ? $row['p_capital_price'] : '';
	$p_markup = (isset($row['p_markup']) && $row['p_markup'] !== null && $row['p_markup'] !== '') ? $row['p_markup'] : '20';
	if ($p_markup === '') {
	    $p_markup = '20';
	}
	$clean_mu_calc = floatval(preg_replace('/[^0-9.]/', '', strval($p_markup)));
	if ($clean_mu_calc <= 0) $clean_mu_calc = 20;

	if (empty($p_capital_price) || floatval(preg_replace('/[^0-9.]/', '', strval($p_capital_price))) <= 0) {
	    $eff_np_calc = !empty($p_new_price) ? floatval(preg_replace('/[^0-9.]/', '', strval($p_new_price))) : floatval(preg_replace('/[^0-9.]/', '', strval($p_current_price)));
	    if ($eff_np_calc > 0) {
	        $p_capital_price = number_format(round($eff_np_calc / (1 + ($clean_mu_calc / 100)), 2), 2, '.', '');
	    }
	}
	$p_qty = $row['p_qty'];
	$p_new_qty = isset($row['p_new_qty']) ? $row['p_new_qty'] : 0;
	$p_s_level = isset($row['p_s_level']) ? $row['p_s_level'] : 10;
	$p_featured_photo = $row['p_featured_photo'];
	$p_description = $row['p_description'];
	$p_short_description = $row['p_short_description'];
	$p_feature = $row['p_feature'];
	$p_condition = $row['p_condition'];
	$p_return_policy = $row['p_return_policy'];
	$p_is_featured = $row['p_is_featured'];
	$p_is_active = $row['p_is_active'];
	$ecat_id = $row['ecat_id'];
}

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

$statement = $pdo->prepare("SELECT * FROM tbl_product_size WHERE p_id=?");
$statement->execute(array($_REQUEST['id']));
$result = $statement->fetchAll(PDO::FETCH_ASSOC);							
foreach ($result as $row) {
	$size_id[] = $row['size_id'];
}

$statement = $pdo->prepare("SELECT * FROM tbl_product_color WHERE p_id=?");
$statement->execute(array($_REQUEST['id']));
$result = $statement->fetchAll(PDO::FETCH_ASSOC);							
foreach ($result as $row) {
	$color_id[] = $row['color_id'];
}
?>


<section class="content">

	<div class="row">
		<div class="col-md-12">

			<?php if($error_message): ?>
			<div class="callout callout-danger">
			
			<p>
			<?php echo $error_message; ?>
			</p>
			</div>
			<?php endif; ?>

			<?php if($success_message): ?>
			<div class="callout callout-success">
			
			<p><?php echo $success_message; ?></p>
			</div>
			<?php endif; ?>

			<form class="form-horizontal" action="" method="post" enctype="multipart/form-data">

				<div class="box box-info">
					<div class="box-body">
						<div class="form-group">
							<label for="" class="col-sm-3 control-label">Top Level Category Name <span>*</span></label>
							<div class="col-sm-4">
								<select name="tcat_id" class="form-control select2 top-cat">
		                            <option value="">Select Top Level Category</option>
		                            <?php
		                            $statement = $pdo->prepare("SELECT * FROM tbl_top_category ORDER BY tcat_name ASC");
		                            $statement->execute();
		                            $result = $statement->fetchAll(PDO::FETCH_ASSOC);   
		                            foreach ($result as $row) {
		                                ?>
		                                <option value="<?php echo $row['tcat_id']; ?>" <?php if($row['tcat_id'] == $tcat_id){echo 'selected';} ?>><?php echo $row['tcat_name']; ?></option>
		                                <?php
		                            }
		                            ?>
		                        </select>
							</div>
						</div>
						<div class="form-group">
							<label for="" class="col-sm-3 control-label">Mid Level Category Name <span>*</span></label>
							<div class="col-sm-4">
								<select name="mcat_id" class="form-control select2 mid-cat">
		                            <option value="">Select Mid Level Category</option>
		                            <?php
		                            $statement = $pdo->prepare("SELECT * FROM tbl_mid_category WHERE tcat_id = ? ORDER BY mcat_name ASC");
		                            $statement->execute(array($tcat_id));
		                            $result = $statement->fetchAll(PDO::FETCH_ASSOC);   
		                            foreach ($result as $row) {
		                                ?>
		                                <option value="<?php echo $row['mcat_id']; ?>" <?php if($row['mcat_id'] == $mcat_id){echo 'selected';} ?>><?php echo $row['mcat_name']; ?></option>
		                                <?php
		                            }
		                            ?>
		                        </select>
							</div>
						</div>
						<div class="form-group">
							<label for="" class="col-sm-3 control-label">End Level Category Name <span>*</span></label>
							<div class="col-sm-4">
								<select name="ecat_id" class="form-control select2 end-cat">
		                            <option value="">Select End Level Category</option>
		                            <?php
		                            $statement = $pdo->prepare("SELECT * FROM tbl_end_category WHERE mcat_id = ? ORDER BY ecat_name ASC");
		                            $statement->execute(array($mcat_id));
		                            $result = $statement->fetchAll(PDO::FETCH_ASSOC);   
		                            foreach ($result as $row) {
		                                ?>
		                                <option value="<?php echo $row['ecat_id']; ?>" <?php if($row['ecat_id'] == $ecat_id){echo 'selected';} ?>><?php echo $row['ecat_name']; ?></option>
		                                <?php
		                            }
		                            ?>
		                        </select>
							</div>
						</div>
						<div class="form-group">
							<label for="" class="col-sm-3 control-label">Product Name <span>*</span></label>
							<div class="col-sm-4">
								<input type="text" name="p_name" class="form-control" value="<?php echo $p_name; ?>">
							</div>
						</div>	
						<div class="form-group">
							<label for="" class="col-sm-3 control-label">Old Price<br><span style="font-size:10px;font-weight:normal;">(In PHP)</span></label>
							<div class="col-sm-4">
								<input type="text" name="p_old_price" class="form-control" value="<?php echo $p_old_price; ?>">
							</div>
						</div>	
						<div class="form-group" style="background: #f8fafc; padding: 10px 0; border-top: 1px solid #e2e8f0; border-bottom: 1px solid #e2e8f0;">
							<label for="" class="col-sm-3 control-label" style="color: #0f172a;">(Ca) Price (Capital Price) <br><span style="font-size:10px;font-weight:normal;color:#64748b;">(Capital Cost in PHP)</span></label>
							<div class="col-sm-4">
								<input type="text" name="p_capital_price" id="p_capital_price" class="form-control" value="<?php echo htmlspecialchars($p_capital_price); ?>" placeholder="e.g. 100.00">
								<small class="text-muted"><i class="fa fa-info-circle"></i> Formula: <code>(Ca)Price = (N)Price / (1 + (%)Mark_Up/100)</code></small>
							</div>
						</div>
						<div class="form-group" style="background: #f8fafc; padding: 10px 0; border-bottom: 1px solid #e2e8f0;">
							<label for="" class="col-sm-3 control-label" style="color: #0f172a;">(%) Mark_Up <span>*</span><br><span style="font-size:10px;font-weight:normal;color:#64748b;">(Default: 20%)</span></label>
							<div class="col-sm-4">
								<div class="input-group">
									<input type="text" name="p_markup" id="p_markup" class="form-control" value="<?php echo htmlspecialchars($p_markup); ?>" placeholder="20" required>
									<span class="input-group-addon" style="font-weight: 700;">%</span>
								</div>
								<small class="text-muted"><i class="fa fa-info-circle"></i> Default = 20. Editable per product.</small>
							</div>
						</div>
						<div class="form-group">
							<label for="" class="col-sm-3 control-label">(N) Price (New Price)<br><span style="font-size:10px;font-weight:normal;">(In PHP)</span></label>
							<div class="col-sm-4">
								<input type="text" name="p_new_price" id="p_new_price" class="form-control" value="<?php echo htmlspecialchars($p_new_price); ?>" placeholder="Optional (defaults to (C) Price)">
								<small class="text-muted"><i class="fa fa-calculator"></i> Formula: <code>(N)Price = (Ca)Price &times; (1 + (%)Mark_Up/100)</code></small>
							</div>
						</div>	
						<div class="form-group">
							<label for="" class="col-sm-3 control-label">(C) Price (Current Price) <span>*</span><br><span style="font-size:10px;font-weight:normal;">(In PHP)</span></label>
							<div class="col-sm-4">
								<input type="text" name="p_current_price" id="p_current_price" class="form-control" value="<?php echo $p_current_price; ?>" required>
							</div>
						</div>	
						<div class="form-group">
							<label for="" class="col-sm-3 control-label">Quantity <span>*</span></label>
							<div class="col-sm-4">
								<input type="text" name="p_qty" class="form-control" value="<?php echo $p_qty; ?>">
							</div>
						</div>
						<div class="form-group">
							<label for="" class="col-sm-3 control-label">(N)Quantity (New Quantity)</label>
							<div class="col-sm-4">
								<input type="number" name="p_new_qty" class="form-control" value="<?php echo htmlspecialchars($p_new_qty); ?>">
							</div>
						</div>
						<div class="form-group">
							<label for="" class="col-sm-3 control-label">(S)Level (Safety Stock Level)</label>
							<div class="col-sm-4">
								<input type="number" name="p_s_level" class="form-control" value="<?php echo htmlspecialchars($p_s_level); ?>">
							</div>
						</div>
						<div class="form-group">
							<label for="" class="col-sm-3 control-label">Select Size</label>
							<div class="col-sm-4">
								<select name="size[]" class="form-control select2" multiple="multiple">
									<?php
									$is_select = '';
									$statement = $pdo->prepare("SELECT * FROM tbl_size ORDER BY size_id ASC");
									$statement->execute();
									$result = $statement->fetchAll(PDO::FETCH_ASSOC);			
									foreach ($result as $row) {
										if(isset($size_id)) {
											if(in_array($row['size_id'],$size_id)) {
												$is_select = 'selected';
											} else {
												$is_select = '';
											}
										}
										?>
										<option value="<?php echo $row['size_id']; ?>" <?php echo $is_select; ?>><?php echo $row['size_name']; ?></option>
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
									$is_select = '';
									$statement = $pdo->prepare("SELECT * FROM tbl_color ORDER BY color_id ASC");
									$statement->execute();
									$result = $statement->fetchAll(PDO::FETCH_ASSOC);			
									foreach ($result as $row) {
										if(isset($color_id)) {
											if(in_array($row['color_id'],$color_id)) {
												$is_select = 'selected';
											} else {
												$is_select = '';
											}
										}
										?>
										<option value="<?php echo $row['color_id']; ?>" <?php echo $is_select; ?>><?php echo $row['color_name']; ?></option>
										<?php
									}
									?>
								</select>
							</div>
						</div>
						<div class="form-group">
							<label for="" class="col-sm-3 control-label">Existing Featured Photo</label>
							<div class="col-sm-4" style="padding-top:4px;">
								<img src="../assets/uploads/<?php echo $p_featured_photo; ?>" alt="" style="width:150px;">
								<input type="hidden" name="current_photo" value="<?php echo $p_featured_photo; ?>">
							</div>
						</div>
						<div class="form-group">
							<label for="" class="col-sm-3 control-label">Change Featured Photo </label>
							<div class="col-sm-4" style="padding-top:4px;">
								<input type="file" name="p_featured_photo">
							</div>
						</div>
						<div class="form-group">
							<label for="" class="col-sm-3 control-label">Other Photos</label>
							<div class="col-sm-4" style="padding-top:4px;">
								<table id="ProductTable" style="width:100%;">
			                        <tbody>
			                        	<?php
			                        	$statement = $pdo->prepare("SELECT * FROM tbl_product_photo WHERE p_id=?");
			                        	$statement->execute(array($_REQUEST['id']));
			                        	$result = $statement->fetchAll(PDO::FETCH_ASSOC);
			                        	foreach ($result as $row) {
			                        		?>
											<tr>
				                                <td>
				                                    <img src="../assets/uploads/product_photos/<?php echo $row['photo']; ?>" alt="" style="width:150px;margin-bottom:5px;">
				                                </td>
				                                <td style="width:28px;">
				                                	<a onclick="return confirmDelete();" href="product-other-photo-delete.php?id=<?php echo $row['pp_id']; ?>&id1=<?php echo $_REQUEST['id']; ?>" class="btn btn-danger btn-xs">X</a>
				                                </td>
				                            </tr>
			                        		<?php
			                        	}
			                        	?>
			                        </tbody>
			                    </table>
							</div>
							<div class="col-sm-2">
			                    <input type="button" id="btnAddNew" value="Add Item" style="margin-top: 5px;margin-bottom:10px;border:0;color: #fff;font-size: 14px;border-radius:3px;" class="btn btn-warning btn-xs">
			                </div>
						</div>
						<div class="form-group">
							<label for="" class="col-sm-3 control-label">Description</label>
							<div class="col-sm-8">
								<textarea name="p_description" class="form-control" cols="30" rows="10" id="editor1"><?php echo $p_description; ?></textarea>
							</div>
						</div>
						<div class="form-group">
							<label for="" class="col-sm-3 control-label">Short Description</label>
							<div class="col-sm-8">
								<textarea name="p_short_description" class="form-control" cols="30" rows="10" id="editor1"><?php echo $p_short_description; ?></textarea>
							</div>
						</div>
						<div class="form-group">
							<label for="" class="col-sm-3 control-label">Features</label>
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
							<label for="" class="col-sm-3 control-label">Return Policy</label>
							<div class="col-sm-8">
								<textarea name="p_return_policy" class="form-control" cols="30" rows="10" id="editor5"><?php echo $p_return_policy; ?></textarea>
							</div>
						</div>
						<div class="form-group">
							<label for="" class="col-sm-3 control-label">Is Featured?</label>
							<div class="col-sm-8">
								<select name="p_is_featured" class="form-control" style="width:auto;">
									<option value="0" <?php if($p_is_featured == '0'){echo 'selected';} ?>>No</option>
									<option value="1" <?php if($p_is_featured == '1'){echo 'selected';} ?>>Yes</option>
								</select> 
							</div>
						</div>
						<div class="form-group">
							<label for="" class="col-sm-3 control-label">Is Active?</label>
							<div class="col-sm-8">
								<select name="p_is_active" class="form-control" style="width:auto;">
									<option value="0" <?php if($p_is_active == '0'){echo 'selected';} ?>>No</option>
									<option value="1" <?php if($p_is_active == '1'){echo 'selected';} ?>>Yes</option>
								</select> 
							</div>
						</div>
						<div class="form-group">
							<label for="" class="col-sm-3 control-label"></label>
							<div class="col-sm-6">
								<button type="submit" class="btn btn-success pull-left" name="form1">Update</button>
							</div>
						</div>
					</div>
				</div>

			</form>


		</div>
	</div>

</section>

<script>
$(document).ready(function() {
    function cleanNum(val) {
        if (!val) return 0;
        var clean = val.toString().replace(/[^0-9.]/g, '');
        return parseFloat(clean) || 0;
    }

    // Auto-calculate (N)Price when (Ca)Price changes: (N)Price = (Ca)Price * (1 + markup/100)
    $('#p_capital_price').on('input keyup change', function() {
        var ca = cleanNum($(this).val());
        var markup = cleanNum($('#p_markup').val());
        if (markup <= 0 && $('#p_markup').val() === '') markup = 20;

        if (ca > 0) {
            var nPrice = ca * (1 + (markup / 100));
            $('#p_new_price').val(nPrice.toFixed(2));
            if (!$('#p_current_price').val() || cleanNum($('#p_current_price').val()) === 0) {
                $('#p_current_price').val(nPrice.toFixed(2));
            }
        }
    });

    // Auto-calculate when (%)Mark_Up changes
    $('#p_markup').on('input keyup change', function() {
        var markup = cleanNum($(this).val());
        var ca = cleanNum($('#p_capital_price').val());
        var np = cleanNum($('#p_new_price').val());
        var cp = cleanNum($('#p_current_price').val());

        if (ca > 0) {
            var nPrice = ca * (1 + (markup / 100));
            $('#p_new_price').val(nPrice.toFixed(2));
        } else if (np > 0) {
            var caPrice = np / (1 + (markup / 100));
            $('#p_capital_price').val(caPrice.toFixed(2));
        } else if (cp > 0) {
            var caPrice = cp / (1 + (markup / 100));
            $('#p_capital_price').val(caPrice.toFixed(2));
        }
    });

    // Auto-calculate (Ca)Price when (N)Price changes: (Ca)Price = (N)Price / (1 + markup/100)
    $('#p_new_price').on('input keyup change', function() {
        var np = cleanNum($(this).val());
        var markup = cleanNum($('#p_markup').val());
        if (markup <= 0 && $('#p_markup').val() === '') markup = 20;

        if (np > 0) {
            var caPrice = np / (1 + (markup / 100));
            $('#p_capital_price').val(caPrice.toFixed(2));
        }
    });
});
</script>

<?php require_once('footer.php'); ?>