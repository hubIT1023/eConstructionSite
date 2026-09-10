<?php require_once('header.php'); ?>

<section class="content-header">
	<div class="content-header-left">
		<h1>View Products</h1>
	</div>
	<div class="content-header-right">
		<a href="product-add.php" class="btn btn-primary btn-sm"><i class="fa fa-plus"></i> Add Product</a>
	</div>
</section>

<?php
$filter_ecat_id = isset($_GET['ecat_id']) ? intval($_GET['ecat_id']) : 0;
$filter_mcat_id = isset($_GET['mcat_id']) ? intval($_GET['mcat_id']) : 0;

// Fetch all available end categories for filter dropdown
$stmt_cats = $pdo->prepare("SELECT ec.ecat_id, ec.ecat_name, mc.mcat_name, tc.tcat_name, COUNT(p.p_id) as prod_count
    FROM tbl_end_category ec
    JOIN tbl_mid_category mc ON ec.mcat_id = mc.mcat_id
    JOIN tbl_top_category tc ON mc.tcat_id = tc.tcat_id
    JOIN tbl_product p ON ec.ecat_id = p.ecat_id AND p.supplier_id = ?
    GROUP BY ec.ecat_id, ec.ecat_name, mc.mcat_name, tc.tcat_name
    ORDER BY tc.tcat_name ASC, mc.mcat_name ASC, ec.ecat_name ASC");
$stmt_cats->execute(array($supplier_id));
$available_categories = $stmt_cats->fetchAll(PDO::FETCH_ASSOC);
?>

<section class="content">
	<div class="row">
		<div class="col-md-12">
			
			<!-- Category Filter Bar -->
			<div class="box box-default" style="margin-bottom: 12px; border-radius: 6px;">
				<div class="box-body" style="padding: 10px 15px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
					<div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
						<span style="font-weight: 700; color: #334155;"><i class="fa fa-filter text-primary"></i> Filter by Category:</span>
						<select class="form-control input-sm" style="width: 280px; font-weight: 600;" onchange="if(this.value){window.location.href='product.php?ecat_id='+this.value;}else{window.location.href='product.php';}">
							<option value="">-- All Categories (View All) --</option>
							<?php foreach($available_categories as $c): ?>
								<option value="<?php echo $c['ecat_id']; ?>" <?php if($filter_ecat_id == $c['ecat_id']) echo 'selected'; ?>>
									<?php echo htmlspecialchars($c['ecat_name']); ?> (<?php echo $c['prod_count']; ?>)
								</option>
							<?php endforeach; ?>
						</select>
						<?php if($filter_ecat_id > 0): ?>
							<a href="product.php" class="btn btn-default btn-sm"><i class="fa fa-times"></i> Clear Filter</a>
						<?php endif; ?>
					</div>
					<div style="font-size: 13px; color: #64748b;">
						<a href="pos.php" class="btn btn-info btn-sm" style="font-weight: 700;"><i class="fa fa-calculator"></i> Open POS Terminal</a>
					</div>
				</div>
			</div>

			<div class="box box-info">
				<div class="box-body table-responsive">
					<table id="example1" class="table table-bordered table-hover table-striped">
					<thead class="thead-dark">
							<tr>
								<th width="10">#</th>
								<th>Photo</th>
								<th width="180">Product Name</th>
								<th width="65">(Ca)Price</th>
								<th width="65">(₱)Mark_Up</th>
								<th width="65">(N)Price</th>
								<th width="65">(C) Price</th>
								<th width="70">Quantity (Q)</th>
								<th width="65">(NQ)Quantity</th>
								<th width="60">(S)Level</th>
								<th width="90">Stock Alert</th>
								<th>Featured?</th>
								<th>Active?</th>
								<th>Category</th>
								<th width="80">Action</th>
							</tr>
						</thead>
						<tbody>
							<?php
							// Automatic inventory & price rollover rule:
							// Trigger 1: Q < 2 AND NQ > 1 (Quantity addition rule)
							// Trigger 2: Q < 6 AND NQ > 5 (Low stock replenishment updates price & adds stock)
							$pdo->query("UPDATE tbl_product 
								SET p_qty = p_qty + p_new_qty,
								    p_current_price = CASE 
								        WHEN (p_qty < 6 AND p_new_qty > 5 AND p_new_price IS NOT NULL AND p_new_price != '') 
								        THEN p_new_price 
								        ELSE p_current_price 
								    END,
								    p_new_qty = 0
								WHERE (p_qty < 2 AND p_new_qty > 1) 
								   OR (p_qty < 6 AND p_new_qty > 5)");

							$i=0;
							$sql = "SELECT
										t1.p_id,
										t1.p_name,
										t1.p_old_price,
										t1.p_current_price,
										t1.p_new_price,
										t1.p_capital_price,
										t1.p_markup,
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
							        JOIN tbl_end_category t2 ON t1.ecat_id = t2.ecat_id
							        JOIN tbl_mid_category t3 ON t2.mcat_id = t3.mcat_id
							        JOIN tbl_top_category t4 ON t3.tcat_id = t4.tcat_id
									WHERE t1.supplier_id = ?";
							$params = array($supplier_id);
							if ($filter_ecat_id > 0) {
								$sql .= " AND t1.ecat_id = ?";
								$params[] = $filter_ecat_id;
							}
							$sql .= " ORDER BY t2.ecat_name ASC, t1.p_name ASC";

							$statement = $pdo->prepare($sql);
							$statement->execute($params);
							$result = $statement->fetchAll(PDO::FETCH_ASSOC);
							foreach ($result as $row) {
								$i++;
								$clean_qty = intval(preg_replace('/[^0-9]/', '', strval($row['p_qty'])));
								$clean_n_qty = (isset($row['p_new_qty']) && $row['p_new_qty'] !== null && $row['p_new_qty'] !== '') ? intval($row['p_new_qty']) : 0;
								$clean_s_level = (isset($row['p_s_level']) && $row['p_s_level'] !== null && $row['p_s_level'] !== '') ? intval($row['p_s_level']) : 10;
								$markup_val = (isset($row['p_markup']) && $row['p_markup'] !== null && $row['p_markup'] !== '') ? $row['p_markup'] : '20';
								$clean_markup = floatval(preg_replace('/[^0-9.]/', '', strval($markup_val)));

								if (isset($row['p_capital_price']) && $row['p_capital_price'] !== null && $row['p_capital_price'] !== '' && floatval(preg_replace('/[^0-9.]/', '', strval($row['p_capital_price']))) > 0) {
									$clean_ca_price = floatval(preg_replace('/[^0-9.]/', '', strval($row['p_capital_price'])));
								} else {
									$eff_n_price = !empty($row['p_new_price']) ? floatval(preg_replace('/[^0-9.]/', '', strval($row['p_new_price']))) : floatval(preg_replace('/[^0-9.]/', '', strval($row['p_current_price'])));
									$clean_ca_price = max(0, $eff_n_price - $clean_markup);
								}

								// Stock Alert Evaluation: Priority 1: RED (< 50% S), Priority 2: ORANGE (50% - <80% S), Priority 3: NORMAL (>= 80% S)
								$half_s = 0.50 * $clean_s_level;
								$eighty_s = 0.80 * $clean_s_level;
								if ($clean_qty < $half_s) {
									$qty_style = 'background-color: #ef4444 !important; color: #ffffff !important; font-weight: 800; text-align: center; border-radius: 4px;';
									$stock_alert_badge = '<span class="badge" style="background-color: #ef4444; color: #fff; font-weight: 700; font-size: 10px; padding: 4px 7px;"><i class="fa fa-exclamation-triangle"></i> RED (&lt;50%)</span>';
								} elseif ($clean_qty < $eighty_s) {
									$qty_style = 'background-color: #f97316 !important; color: #ffffff !important; font-weight: 800; text-align: center; border-radius: 4px;';
									$stock_alert_badge = '<span class="badge" style="background-color: #f97316; color: #fff; font-weight: 700; font-size: 10px; padding: 4px 7px;"><i class="fa fa-warning"></i> ORANGE</span>';
								} else {
									$qty_style = 'text-align: center; font-weight: 700; color: #0f172a;';
									$stock_alert_badge = '<span class="badge" style="background-color: #10b981; color: #fff; font-weight: 600; font-size: 10px; padding: 4px 7px;"><i class="fa fa-check"></i> NORMAL</span>';
								}
								?>
								<tr>
									<td><?php echo $i; ?></td>
									<td style="width:82px;"><img src="../assets/uploads/<?php echo $row['p_featured_photo']; ?>" alt="<?php echo $row['p_name']; ?>" style="width:80px;"></td>
									<td><?php echo $row['p_name']; ?></td>
									<td>&#8369;<?php echo number_format($clean_ca_price, 2); ?></td>
									<td>&#8369;<?php echo number_format($clean_markup, 2); ?></td>
									<td>&#8369;<?php echo !empty($row['p_new_price']) ? $row['p_new_price'] : $row['p_current_price']; ?></td>
									<td style="font-weight: 800; color: #1e40af;">&#8369;<?php echo $row['p_current_price']; ?></td>
									<td style="<?php echo $qty_style; ?>"><?php echo $row['p_qty']; ?></td>
									<td style="text-align: center; font-weight: 600;"><?php echo $clean_n_qty; ?></td>
									<td style="text-align: center;"><?php echo $clean_s_level; ?></td>
									<td style="text-align: center;"><?php echo $stock_alert_badge; ?></td>
									<td>
										<?php if($row['p_is_featured'] == 1) {echo '<span class="badge badge-success" style="background-color:green;">Yes</span>';} else {echo '<span class="badge badge-success" style="background-color:red;">No</span>';} ?>
									</td>
									<td>
										<?php if($row['p_is_active'] == 1) {echo '<span class="badge badge-success" style="background-color:green;">Yes</span>';} else {echo '<span class="badge badge-danger" style="background-color:red;">No</span>';} ?>
									</td>
									<td>
										<small class="text-muted" style="font-size: 11px;"><?php echo htmlspecialchars($row['tcat_name']); ?></small><br>
										<span class="label label-default" style="font-size: 10px; background: #e2e8f0; color: #334155;"><?php echo htmlspecialchars($row['mcat_name']); ?></span><br>
										<a href="product.php?ecat_id=<?php echo $row['ecat_id']; ?>" style="color: #2563eb; font-weight: 700; font-size: 12px;" title="Filter by this category">
											<?php echo htmlspecialchars($row['ecat_name']); ?>
										</a>
									</td>
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