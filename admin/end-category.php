<?php require_once('header.php'); ?>

<?php
$filter_tcat_id = isset($_GET['tcat_id']) ? (int)$_GET['tcat_id'] : 0;
$filter_mcat_id = isset($_GET['mcat_id']) ? (int)$_GET['mcat_id'] : 0;

// Fetch all top categories for filter dropdown
$stmt_tcat = $pdo->prepare("SELECT * FROM tbl_top_category ORDER BY tcat_name ASC");
$stmt_tcat->execute();
$all_top_cats = $stmt_tcat->fetchAll(PDO::FETCH_ASSOC);

// Fetch mid categories based on selected top category (or all)
if ($filter_tcat_id > 0) {
    $stmt_mcat = $pdo->prepare("SELECT * FROM tbl_mid_category WHERE tcat_id = ? ORDER BY mcat_name ASC");
    $stmt_mcat->execute([$filter_tcat_id]);
} else {
    $stmt_mcat = $pdo->prepare("SELECT * FROM tbl_mid_category ORDER BY mcat_name ASC");
    $stmt_mcat->execute();
}
$all_mid_cats = $stmt_mcat->fetchAll(PDO::FETCH_ASSOC);
?>

<section class="content-header">
	<div class="content-header-left">
		<h1><i class="fa fa-cubes text-primary"></i> View End Level Categories (Base Products)</h1>
	</div>
	<div class="content-header-right">
		<a href="end-category-add.php" class="btn btn-primary btn-sm"><i class="fa fa-plus"></i> Add New Base Category</a>
	</div>
</section>

<section class="content">
  <!-- Filter Toolbar -->
  <div class="row" style="margin-bottom: 15px;">
    <div class="col-md-12">
      <div class="box box-solid" style="margin-bottom: 0; background: #fdfdfd; border-left: 4px solid #3c8dbc;">
        <div class="box-body" style="padding: 10px 15px;">
          <form method="get" action="end-category.php" class="form-inline" style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
            <label for="filter_tcat"><i class="fa fa-filter"></i> Department:</label>
            <select name="tcat_id" id="filter_tcat" class="form-control input-sm select2" onchange="this.form.submit()" style="min-width: 220px;">
              <option value="0">All Departments</option>
              <?php foreach ($all_top_cats as $tc): ?>
                <option value="<?php echo $tc['tcat_id']; ?>" <?php echo $filter_tcat_id == $tc['tcat_id'] ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($tc['tcat_name']); ?>
                </option>
              <?php endforeach; ?>
            </select>

            <label for="filter_mcat">Sub-System:</label>
            <select name="mcat_id" id="filter_mcat" class="form-control input-sm select2" onchange="this.form.submit()" style="min-width: 240px;">
              <option value="0">All Sub-Systems</option>
              <?php foreach ($all_mid_cats as $mc): ?>
                <option value="<?php echo $mc['mcat_id']; ?>" <?php echo $filter_mcat_id == $mc['mcat_id'] ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($mc['mcat_name']); ?>
                </option>
              <?php endforeach; ?>
            </select>

            <?php if ($filter_tcat_id > 0 || $filter_mcat_id > 0): ?>
              <a href="end-category.php" class="btn btn-default btn-sm"><i class="fa fa-times"></i> Clear Filters</a>
            <?php endif; ?>
          </form>
        </div>
      </div>
    </div>
  </div>

  <div class="row">
    <div class="col-md-12">
      <div class="box box-info">
        <div class="box-body table-responsive">
          <table id="example1" class="table table-bordered table-hover table-striped">
			<thead>
			    <tr class="bg-gray">
			        <th style="width: 40px;">#</th>
			        <th>End Category Name (Base Item)</th>
                    <th style="width: 380px;">Category Hierarchy (Breadcrumbs)</th>
			        <th style="width: 140px;" class="text-center">Active Products</th>
			        <th style="width: 150px;" class="text-center">Action</th>
			    </tr>
			</thead>
            <tbody>
            	<?php
            	$i=0;
            	$sql = "
            		SELECT 
            			t1.ecat_id,
            			t1.ecat_name,
            			t2.mcat_id,
            			t2.mcat_name,
            			t3.tcat_id,
            			t3.tcat_name,
            			COUNT(p.p_id) AS product_count
            		FROM tbl_end_category t1
            		JOIN tbl_mid_category t2 ON t1.mcat_id = t2.mcat_id
            		JOIN tbl_top_category t3 ON t2.tcat_id = t3.tcat_id
            		LEFT JOIN tbl_product p ON p.ecat_id = t1.ecat_id
            	";

            	$where = [];
            	if ($filter_tcat_id > 0) {
            		$where[] = "t3.tcat_id = " . (int)$filter_tcat_id;
            	}
            	if ($filter_mcat_id > 0) {
            		$where[] = "t2.mcat_id = " . (int)$filter_mcat_id;
            	}
            	if (!empty($where)) {
            		$sql .= " WHERE " . implode(' AND ', $where);
            	}

            	$sql .= " GROUP BY t1.ecat_id, t1.ecat_name, t2.mcat_id, t2.mcat_name, t3.tcat_id, t3.tcat_name ORDER BY t3.tcat_name ASC, t2.mcat_name ASC, t1.ecat_name ASC";

            	$statement = $pdo->prepare($sql);
            	$statement->execute();
            	$result = $statement->fetchAll(PDO::FETCH_ASSOC);							
            	foreach ($result as $row) {
            		$i++;
            		$prod_count = (int)$row['product_count'];
            		?>
					<tr>
	                    <td><?php echo $i; ?></td>
	                    <td>
	                    	<strong><?php echo htmlspecialchars($row['ecat_name']); ?></strong>
	                    </td>
                        <td>
                        	<span class="label label-primary" style="font-size: 10px;">
                        		<i class="fa fa-sitemap"></i> <?php echo htmlspecialchars($row['tcat_name']); ?>
                        	</span>
                        	<i class="fa fa-angle-right text-muted" style="margin: 0 4px;"></i>
                        	<span class="label label-info" style="font-size: 10px;">
                        		<i class="fa fa-folder-o"></i> <?php echo htmlspecialchars($row['mcat_name']); ?>
                        	</span>
                        </td>
	                    <td class="text-center">
	                    	<span class="badge <?php echo $prod_count > 0 ? 'bg-green' : 'bg-gray'; ?>" style="font-size: 12px; padding: 4px 8px;">
	                    		<i class="fa fa-tags"></i> <?php echo number_format($prod_count); ?> items
	                    	</span>
	                    </td>
	                    <td class="text-center">
	                        <a href="end-category-edit.php?id=<?php echo $row['ecat_id']; ?>" class="btn btn-primary btn-xs" title="Edit Base Category"><i class="fa fa-edit"></i> Edit</a>
	                        <?php if ($prod_count > 0): ?>
	                        	<button type="button" class="btn btn-danger btn-xs disabled" title="Cannot delete end category with <?php echo $prod_count; ?> active products. Remap products first." style="cursor: not-allowed; opacity: 0.65;">
	                        		<i class="fa fa-lock"></i> Delete
	                        	</button>
	                        <?php else: ?>
	                        	<a href="#" class="btn btn-danger btn-xs" data-href="end-category-delete.php?id=<?php echo $row['ecat_id']; ?>" data-toggle="modal" data-target="#confirm-delete" title="Delete Base Category"><i class="fa fa-trash"></i> Delete</a>
	                        <?php endif; ?>
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
                <h4 class="modal-title" id="myModalLabel"><i class="fa fa-exclamation-triangle text-danger"></i> Delete Confirmation</h4>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this End Level Base Category?</p>
                <p style="color:red; font-size: 12px;"><strong>Warning:</strong> All products under this end category must be deleted or remapped first.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <a class="btn btn-danger btn-ok">Confirm Delete</a>
            </div>
        </div>
    </div>
</div>

<?php require_once('footer.php'); ?>