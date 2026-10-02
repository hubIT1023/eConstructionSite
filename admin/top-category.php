<?php require_once('header.php'); ?>

<section class="content-header">
	<div class="content-header-left">
		<h1><i class="fa fa-sitemap text-primary"></i> Top Level Categories (Departments)</h1>
	</div>
	<div class="content-header-right">
		<a href="top-category-add.php" class="btn btn-primary btn-sm"><i class="fa fa-plus"></i> Add New Department</a>
	</div>
</section>

<section class="content">
  <div class="row">
    <div class="col-md-12">
      <div class="box box-primary">
        <div class="box-body table-responsive">
          <table id="example1" class="table table-bordered table-hover table-striped">
			<thead>
			    <tr class="bg-gray">
			        <th style="width: 40px;">#</th>
			        <th>Department (Top Category)</th>
			        <th style="width: 140px;" class="text-center">Mid Categories</th>
			        <th style="width: 140px;" class="text-center">End Categories</th>
			        <th style="width: 140px;" class="text-center">Active Products</th>
                    <th style="width: 110px;" class="text-center">Show on Menu?</th>
			        <th style="width: 150px;" class="text-center">Action</th>
			    </tr>
			</thead>
            <tbody>
            	<?php
            	$i=0;
            	$statement = $pdo->prepare("
            		SELECT 
            			t.tcat_id, 
            			t.tcat_name, 
            			t.show_on_menu,
            			COUNT(DISTINCT m.mcat_id) AS mid_count,
            			COUNT(DISTINCT e.ecat_id) AS end_count,
            			COUNT(p.p_id) AS product_count
            		FROM tbl_top_category t
            		LEFT JOIN tbl_mid_category m ON m.tcat_id = t.tcat_id
            		LEFT JOIN tbl_end_category e ON e.mcat_id = m.mcat_id
            		LEFT JOIN tbl_product p ON p.ecat_id = e.ecat_id
            		GROUP BY t.tcat_id, t.tcat_name, t.show_on_menu
            		ORDER BY t.tcat_name ASC
            	");
            	$statement->execute();
            	$result = $statement->fetchAll(PDO::FETCH_ASSOC);							
            	foreach ($result as $row) {
            		$i++;
            		$mid_count = (int)$row['mid_count'];
            		$end_count = (int)$row['end_count'];
            		$prod_count = (int)$row['product_count'];
            		?>
					<tr>
	                    <td><?php echo $i; ?></td>
	                    <td>
	                    	<strong><?php echo htmlspecialchars($row['tcat_name']); ?></strong>
	                    </td>
	                    <td class="text-center">
	                    	<a href="mid-category.php?tcat_id=<?php echo $row['tcat_id']; ?>" class="btn btn-default btn-xs" title="View sub-categories under this department">
	                    		<span class="label <?php echo $mid_count > 0 ? 'label-primary' : 'label-default'; ?>" style="font-size: 11px;">
	                    			<i class="fa fa-folder-open"></i> <?php echo $mid_count; ?> Mid
	                    		</span>
	                    	</a>
	                    </td>
	                    <td class="text-center">
	                    	<a href="end-category.php?tcat_id=<?php echo $row['tcat_id']; ?>" class="btn btn-default btn-xs" title="View base product items">
	                    		<span class="label <?php echo $end_count > 0 ? 'label-info' : 'label-default'; ?>" style="font-size: 11px;">
	                    			<i class="fa fa-cubes"></i> <?php echo $end_count; ?> End
	                    		</span>
	                    	</a>
	                    </td>
	                    <td class="text-center">
	                    	<span class="badge <?php echo $prod_count > 0 ? 'bg-green' : 'bg-gray'; ?>" style="font-size: 12px; padding: 4px 8px;">
	                    		<i class="fa fa-tags"></i> <?php echo number_format($prod_count); ?> items
	                    	</span>
	                    </td>
                        <td class="text-center">
                            <?php if($row['show_on_menu'] == 1): ?>
                                <span class="label label-success"><i class="fa fa-check"></i> Yes</span>
                            <?php else: ?>
                                <span class="label label-danger"><i class="fa fa-times"></i> No</span>
                            <?php endif; ?>
                        </td>
	                    <td class="text-center">
	                        <a href="top-category-edit.php?id=<?php echo $row['tcat_id']; ?>" class="btn btn-primary btn-xs" title="Edit Department"><i class="fa fa-edit"></i> Edit</a>
	                        <?php if ($prod_count > 0): ?>
	                        	<button type="button" class="btn btn-danger btn-xs disabled" title="Cannot delete department with <?php echo $prod_count; ?> active products. Remap products first." style="cursor: not-allowed; opacity: 0.65;">
	                        		<i class="fa fa-lock"></i> Delete
	                        	</button>
	                        <?php else: ?>
	                        	<a href="#" class="btn btn-danger btn-xs" data-href="top-category-delete.php?id=<?php echo $row['tcat_id']; ?>" data-toggle="modal" data-target="#confirm-delete" title="Delete Department"><i class="fa fa-trash"></i> Delete</a>
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
                <p>Are you sure you want to delete this Top Level Department?</p>
                <p style="color:red; font-size:12px;"><strong>Warning:</strong> All associated mid-level and end-level categories must be empty before deletion.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <a class="btn btn-danger btn-ok">Confirm Delete</a>
            </div>
        </div>
    </div>
</div>

<?php require_once('footer.php'); ?>