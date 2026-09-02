<?php require_once('header.php'); ?>

<section class="content-header">
	<div class="content-header-left">
		<h1>View Products</h1>
	</div>
	<div class="content-header-right">
		<a href="product-add.php" class="btn btn-primary btn-sm">Add Product</a>
	</div>
</section>

<section class="content">
	<div class="row">
		<div class="col-md-12">
			<div class="box box-info">
				<div class="box-body table-responsive">
					<table id="example1" class="table table-bordered table-hover table-striped">
					<thead class="thead-dark">
							<tr>
								<th width="10">#</th>
								<th>Photo</th>
								<th width="160">Product Name</th>
								<th width="60">Old Price</th>
								<th width="60">(C) Price</th>
								<th width="60">(N)Price</th>
								<th width="60">Quantity</th>
								<th width="60">(N)Quantity</th>
								<th width="60">(S)Level</th>
								<th>Featured?</th>
								<th>Active?</th>
								<th>Category</th>
								<th width="80">Action</th>
							</tr>
						</thead>
						<tbody>
							<?php
							// Automatic inventory & price rollover:
							// If "Quantity = 0 or 1" AND "(N)Quantity > 10% of (S)Level", "Quantity = Quantity + (N)Quantity" THEN "(C)Price = (N)Price"
							$pdo->query("UPDATE tbl_product 
								SET p_qty = p_qty + p_new_qty,
								    p_current_price = CASE WHEN (p_new_price IS NOT NULL AND p_new_price != '') THEN p_new_price ELSE p_current_price END,
								    p_new_qty = 0
								WHERE (p_qty = 0 OR p_qty = 1) 
								  AND p_new_qty > (COALESCE(p_s_level, 10) * 0.1)");

							$i=0;
							$statement = $pdo->prepare("SELECT
														
														t1.p_id,
														t1.p_name,
														t1.p_old_price,
														t1.p_current_price,
														t1.p_new_price,
														t1.p_qty,
														t1.p_new_qty,
														t1.p_s_level,
														t1.p_featured_photo,
														t1.p_is_featured,
														t1.p_is_active,
														t1.ecat_id,

														t2.ecat_id,
														t2.ecat_name,

														t3.mcat_id,
														t3.mcat_name,

														t4.tcat_id,
														t4.tcat_name

							                           	FROM tbl_product t1
							                           	JOIN tbl_end_category t2
							                           	ON t1.ecat_id = t2.ecat_id
							                           	JOIN tbl_mid_category t3
							                           	ON t2.mcat_id = t3.mcat_id
							                           	JOIN tbl_top_category t4
							                           	ON t3.tcat_id = t4.tcat_id
							                           	ORDER BY t1.p_id DESC
							                           	");
							$statement->execute();
							$result = $statement->fetchAll(PDO::FETCH_ASSOC);
							foreach ($result as $row) {
								$i++;
								$clean_qty = intval(preg_replace('/[^0-9]/', '', strval($row['p_qty'])));
								$clean_n_qty = (isset($row['p_new_qty']) && $row['p_new_qty'] !== null && $row['p_new_qty'] !== '') ? intval($row['p_new_qty']) : 0;
								$clean_s_level = (isset($row['p_s_level']) && $row['p_s_level'] !== null && $row['p_s_level'] !== '') ? intval($row['p_s_level']) : 10;

								$qty_style = '';
								if ($clean_n_qty == 0) {
									if ($clean_qty < ($clean_s_level * 0.5)) {
										// Quantity < 50% of (S)Level AND (N)Quantity = 0 -> RED
										$qty_style = 'background-color: #ef4444 !important; color: #ffffff !important; font-weight: 800; text-align: center;';
									} elseif ($clean_qty < $clean_s_level) {
										// Quantity < (S)Level AND (N)Quantity = 0 -> YELLOW
										$qty_style = 'background-color: #fef08a !important; color: #854d0e !important; font-weight: 800; text-align: center;';
									}
								}
								?>
								<tr>
									<td><?php echo $i; ?></td>
									<td style="width:82px;"><img src="../assets/uploads/<?php echo $row['p_featured_photo']; ?>" alt="<?php echo $row['p_name']; ?>" style="width:80px;"></td>
									<td><?php echo $row['p_name']; ?></td>
									<td>&#8369;<?php echo $row['p_old_price']; ?></td>
									<td>&#8369;<?php echo $row['p_current_price']; ?></td>
									<td>&#8369;<?php echo !empty($row['p_new_price']) ? $row['p_new_price'] : $row['p_current_price']; ?></td>
									<td style="<?php echo $qty_style; ?>"><?php echo $row['p_qty']; ?></td>
									<td><?php echo $clean_n_qty; ?></td>
									<td><?php echo $clean_s_level; ?></td>
									<td>
										<?php if($row['p_is_featured'] == 1) {echo '<span class="badge badge-success" style="background-color:green;">Yes</span>';} else {echo '<span class="badge badge-success" style="background-color:red;">No</span>';} ?>
									</td>
									<td>
										<?php if($row['p_is_active'] == 1) {echo '<span class="badge badge-success" style="background-color:green;">Yes</span>';} else {echo '<span class="badge badge-danger" style="background-color:red;">No</span>';} ?>
									</td>
									<td><?php echo $row['tcat_name']; ?><br><?php echo $row['mcat_name']; ?><br><?php echo $row['ecat_name']; ?></td>
									<td>										
										<a href="product-edit.php?id=<?php echo $row['p_id']; ?>" class="btn btn-primary btn-xs">Edit</a>
										<a href="#" class="btn btn-danger btn-xs" data-href="product-delete.php?id=<?php echo $row['p_id']; ?>" data-toggle="modal" data-target="#confirm-delete">Delete</a>  
									</td>
								</tr>
								<?php
							}
							?>							
						</tbody>
					</table>
				</div>
			</div>
		</div>
	</div>
</section>


<div class="modal fade" id="confirm-delete" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                <h4 class="modal-title" id="myModalLabel">Delete Confirmation</h4>
            </div>
            <div class="modal-body">
                <p>Are you sure want to delete this item?</p>
                <p style="color:red;">Be careful! This product will be deleted from the order table, payment table, size table, color table and rating table also.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <a class="btn btn-danger btn-ok">Delete</a>
            </div>
        </div>
    </div>
</div>

<?php require_once('footer.php'); ?>