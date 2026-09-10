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
    } else {
    	$valid = 0;
        $error_message .= 'You must have to select a featured photo<br>';
    }


    if($valid == 1) {

    	$statement = $pdo->prepare("SELECT nextval('tbl_product_p_id_seq') AS next_id");
		$statement->execute();
		$row = $statement->fetch();
		$ai_id = $row['next_id'];

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
		        	$statement->execute(array($final_name1[$i],$ai_id));
		        }
            }            
        }

		$final_name = 'product-featured-'.$ai_id.'.'.$ext;
        move_uploaded_file( $path_tmp, '../assets/uploads/'.$final_name );

        $ca_input = isset($_POST['p_capital_price']) ? trim($_POST['p_capital_price']) : '0';
        $p_capital_price_val = max(0, floatval(preg_replace('/[^0-9.]/', '', strval($ca_input))));

        $mu_input = isset($_POST['p_markup']) ? trim($_POST['p_markup']) : '0';
        $p_markup_val = max(0, floatval(preg_replace('/[^0-9.]/', '', strval($mu_input))));

        $n_price_val = round($p_capital_price_val + $p_markup_val, 2);
        $final_current_price_val = $n_price_val;

        $p_capital_price = number_format($p_capital_price_val, 2, '.', '');
        $p_markup = number_format($p_markup_val, 2, '.', '');
        $p_new_price = number_format($n_price_val, 2, '.', '');
        $p_current_price = number_format($final_current_price_val, 2, '.', '');

		// Saving data into the main table tbl_product
		$statement = $pdo->prepare("INSERT INTO tbl_product(
										p_id,
										p_name,
										p_old_price,
										p_current_price,
										p_qty,
										p_featured_photo,
										p_description,
										p_short_description,
										p_feature,
										p_condition,
										p_return_policy,
										p_total_view,
										p_is_featured,
										p_is_active,
										ecat_id,
										p_new_price,
										p_new_qty,
										p_s_level,
										p_capital_price,
										p_markup
									) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
		$statement->execute(array(
                                        $ai_id,
										$_POST['p_name'],
										$_POST['p_old_price'],
										$p_current_price,
										$_POST['p_qty'],
										$final_name,
										$_POST['p_description'],
										$_POST['p_short_description'],
										$_POST['p_feature'],
										$_POST['p_condition'],
										$_POST['p_return_policy'],
										0,
										$_POST['p_is_featured'],
										$_POST['p_is_active'],
										$_POST['ecat_id'],
										$p_new_price,
										(isset($_POST['p_new_qty']) && $_POST['p_new_qty'] !== '') ? intval($_POST['p_new_qty']) : 0,
										(isset($_POST['p_s_level']) && $_POST['p_s_level'] !== '') ? intval($_POST['p_s_level']) : 10,
										$p_capital_price,
										$p_markup
									));

		

        if(isset($_POST['size'])) {
			foreach($_POST['size'] as $value) {
				$statement = $pdo->prepare("INSERT INTO tbl_product_size (size_id,p_id) VALUES (?,?)");
				$statement->execute(array($value,$ai_id));
			}
		}

		if(isset($_POST['color'])) {
			foreach($_POST['color'] as $value) {
				$statement = $pdo->prepare("INSERT INTO tbl_product_color (color_id,p_id) VALUES (?,?)");
				$statement->execute(array($value,$ai_id));
			}
		}
	
    	$success_message = 'Product is added successfully.';
    }
}
?>

<section class="content-header">
	<div class="content-header-left">
		<h1>Add Product</h1>
	</div>
	<div class="content-header-right">
		<a href="product.php" class="btn btn-primary btn-sm">View All</a>
	</div>
</section>


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
										<option value="<?php echo $row['tcat_id']; ?>"><?php echo $row['tcat_name']; ?></option>
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
								</select>
							</div>
						</div>
						<div class="form-group">
							<label for="" class="col-sm-3 control-label">End Level Category Name <span>*</span></label>
							<div class="col-sm-4">
								<select name="ecat_id" class="form-control select2 end-cat">
									<option value="">Select End Level Category</option>
								</select>
							</div>
						</div>
						<div class="form-group">
							<label for="" class="col-sm-3 control-label">Product Name <span>*</span></label>
							<div class="col-sm-4">
								<input type="text" name="p_name" class="form-control">
							</div>
						</div>	
						<div class="form-group">
							<label for="" class="col-sm-3 control-label">Old Price <br><span style="font-size:10px;font-weight:normal;">(In PHP)</span></label>
							<div class="col-sm-4">
								<input type="text" name="p_old_price" class="form-control">
							</div>
						</div>
						<!-- Capital Price (Ca) -->
						<div class="form-group" style="background: #f8fafc; padding: 12px 0; border-top: 1.5px solid #e2e8f0; border-bottom: 1px solid #e2e8f0;">
							<label for="p_capital_price" class="col-sm-3 control-label" style="color: #0f172a;">Capital Price (Ca) <span>*</span><br><span style="font-size:10px;font-weight:normal;color:#64748b;">(Original / Base Capital Cost)</span></label>
							<div class="col-sm-4">
								<div class="input-group">
									<span class="input-group-addon" style="font-weight: 700; background: #eff6ff; color: #1d4ed8; font-size: 15px;">₱</span>
									<input type="number" step="0.01" min="0" name="p_capital_price" id="p_capital_price" class="form-control" placeholder="e.g. 100.00" required style="font-weight: 600;">
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
									<input type="number" step="0.01" min="0" name="p_markup" id="p_markup" class="form-control" value="20.00" placeholder="e.g. 20.00" required style="font-weight: 600;">
								</div>
								<small class="text-muted" style="font-size: 11px;"><i class="fa fa-info-circle"></i> Fixed peso amount (₱) added to Capital Price (not a percentage).</small>
							</div>
						</div>

						<!-- New Price (N) - Automatically Calculated -->
						<div class="form-group" style="background: #f0fdf4; padding: 12px 0; border-bottom: 1px solid #bbf7d0;">
							<label for="p_new_price" class="col-sm-3 control-label" style="color: #166534;">New Price (N) <span>*</span><br><span style="font-size:10px;font-weight:normal;color:#15803d;">(Calculated or Manual Edit)</span></label>
							<div class="col-sm-4">
								<div class="input-group">
									<span class="input-group-addon" style="font-weight: 700; background: #dcfce7; color: #166534; font-size: 15px;">₱</span>
									<input type="number" step="0.01" min="0" name="p_new_price" id="p_new_price" class="form-control" placeholder="0.00" required style="font-weight: 700; font-size: 15px; color: #166534;">
								</div>
								<small style="color: #15803d; font-size: 11px;"><i class="fa fa-calculator"></i> Auto-calculates as <code>N = Ca + ₱ Mark-Up</code>, or enter custom manual price.</small>
							</div>
						</div>	

						<!-- Current Price (C) - Initialized to New Price -->
						<div class="form-group" style="padding: 12px 0; border-bottom: 1px solid #e2e8f0;">
							<label for="p_current_price" class="col-sm-3 control-label" style="color: #0f172a;">Current Price (C) <span>*</span><br><span style="font-size:10px;font-weight:normal;color:#64748b;">(Result after Price Rule)</span></label>
							<div class="col-sm-4">
								<div class="input-group">
									<span class="input-group-addon" style="font-weight: 700; background: #eff6ff; color: #1e40af; font-size: 15px;">₱</span>
									<input type="text" name="p_current_price" id="p_current_price" class="form-control" required style="font-weight: 800; font-size: 16px; color: #1e40af; background-color: #f8fafc;" readonly>
								</div>
								<small class="text-muted" style="font-size: 11px;"><i class="fa fa-info-circle"></i> Initial published price equals New Price.</small>
							</div>
						</div>	
						<div class="form-group">
							<label for="" class="col-sm-3 control-label">Quantity in Stock (Current) (Q) <span>*</span></label>
							<div class="col-sm-4">
								<input type="number" min="0" name="p_qty" class="form-control" placeholder="e.g. 20" required style="font-weight: 700;">
							</div>
						</div>
						<div class="form-group">
							<label for="" class="col-sm-3 control-label">(N)Quantity (New Quantity) (NQ)</label>
							<div class="col-sm-4">
								<input type="number" min="0" name="p_new_qty" class="form-control" value="0">
								<small class="text-muted" style="font-size: 11px;"><i class="fa fa-cubes"></i> Incoming / replenishment stock staged in reserve.</small>
							</div>
						</div>
						<div class="form-group">
							<label for="" class="col-sm-3 control-label">Safety Stock Level (S) <span>*</span></label>
							<div class="col-sm-4">
								<input type="number" min="0" name="p_s_level" class="form-control" value="10">
								<small class="text-muted" style="font-size: 11px;"><i class="fa fa-shield"></i> Threshold buffer: RED if <code>Q &lt; 50% S</code>, ORANGE if <code>50% &le; Q &lt; 80% S</code>, NORMAL if <code>Q &ge; 80% S</code>.</small>
							</div>
						</div>
						<div class="form-group">
							<label for="" class="col-sm-3 control-label">Select Size</label>
							<div class="col-sm-4">
								<select name="size[]" class="form-control select2" multiple="multiple">
									<?php
									$statement = $pdo->prepare("SELECT * FROM tbl_size ORDER BY size_id ASC");
									$statement->execute();
									$result = $statement->fetchAll(PDO::FETCH_ASSOC);			
									foreach ($result as $row) {
										?>
										<option value="<?php echo $row['size_id']; ?>"><?php echo $row['size_name']; ?></option>
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
									$statement = $pdo->prepare("SELECT * FROM tbl_color ORDER BY color_id ASC");
									$statement->execute();
									$result = $statement->fetchAll(PDO::FETCH_ASSOC);			
									foreach ($result as $row) {
										?>
										<option value="<?php echo $row['color_id']; ?>"><?php echo $row['color_name']; ?></option>
										<?php
									}
									?>
								</select>
							</div>
						</div>
						<div class="form-group">
							<label for="" class="col-sm-3 control-label">Featured Photo <span>*</span></label>
							<div class="col-sm-4" style="padding-top:4px;">
								<input type="file" name="p_featured_photo">
							</div>
						</div>
						<div class="form-group">
							<label for="" class="col-sm-3 control-label">Other Photos</label>
							<div class="col-sm-4" style="padding-top:4px;">
								<table id="ProductTable" style="width:100%;">
			                        <tbody>
			                            <tr>
			                                <td>
			                                    <div class="upload-btn">
			                                        <input type="file" name="photo[]" style="margin-bottom:5px;">
			                                    </div>
			                                </td>
			                                <td style="width:28px;"><a href="javascript:void()" class="Delete btn btn-danger btn-xs">X</a></td>
			                            </tr>
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
								<textarea name="p_description" class="form-control" cols="30" rows="10" id="editor1"></textarea>
							</div>
						</div>
						<div class="form-group">
							<label for="" class="col-sm-3 control-label">Short Description</label>
							<div class="col-sm-8">
								<textarea name="p_short_description" class="form-control" cols="30" rows="10" id="editor2"></textarea>
							</div>
						</div>
						<div class="form-group">
							<label for="" class="col-sm-3 control-label">Features</label>
							<div class="col-sm-8">
								<textarea name="p_feature" class="form-control" cols="30" rows="10" id="editor3"></textarea>
							</div>
						</div>
						<div class="form-group">
							<label for="" class="col-sm-3 control-label">Conditions</label>
							<div class="col-sm-8">
								<textarea name="p_condition" class="form-control" cols="30" rows="10" id="editor4"></textarea>
							</div>
						</div>
						<div class="form-group">
							<label for="" class="col-sm-3 control-label">Return Policy</label>
							<div class="col-sm-8">
								<textarea name="p_return_policy" class="form-control" cols="30" rows="10" id="editor5"></textarea>
							</div>
						</div>
						<div class="form-group">
							<label for="" class="col-sm-3 control-label">Is Featured?</label>
							<div class="col-sm-8">
								<select name="p_is_featured" class="form-control" style="width:auto;">
									<option value="0">No</option>
									<option value="1">Yes</option>
								</select> 
							</div>
						</div>
						<div class="form-group">
							<label for="" class="col-sm-3 control-label">Is Active?</label>
							<div class="col-sm-8">
								<select name="p_is_active" class="form-control" style="width:auto;">
									<option value="0">No</option>
									<option value="1">Yes</option>
								</select> 
							</div>
						</div>
						<div class="form-group">
							<label for="" class="col-sm-3 control-label"></label>
							<div class="col-sm-6">
								<button type="submit" class="btn btn-success pull-left" name="form1">Add Product</button>
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

    function updateAddPricing() {
        var ca = cleanNum($('#p_capital_price').val());
        var markup = cleanNum($('#p_markup').val());
        var nPrice = Math.round((ca + markup) * 100) / 100;
        $('#p_new_price').val(nPrice.toFixed(2));
        $('#p_current_price').val(nPrice.toFixed(2));
    }

    $('#p_capital_price, #p_markup').on('input keyup change', function() {
        updateAddPricing();
    });
});
</script>

<?php require_once('footer.php'); ?>