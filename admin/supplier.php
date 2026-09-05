<?php require_once('header.php'); ?>

<section class="content-header">
	<div class="content-header-left">
		<h1>Manage B2B SaaS Suppliers</h1>
	</div>
</section>

<section class="content">
	<div class="row">
		<div class="col-md-12">
			<div class="box box-info">
				<div class="box-body table-responsive">
					<table id="example1" class="table table-bordered table-hover table-striped">
						<thead>
							<tr>
								<th>#</th>
								<th>Supplier Company</th>
								<th>Business Contact</th>
								<th>Address</th>
								<th>SaaS Subscription Plan</th>
								<th>Platform Commission (%)</th>
								<th>Status</th>
								<th>Joined Date</th>
								<th>Action</th>
							</tr>
						</thead>
						<tbody>
							<?php
							$i=0;
							$statement = $pdo->prepare("SELECT * FROM tbl_supplier ORDER BY supplier_id DESC");
							$statement->execute();
							$result = $statement->fetchAll(PDO::FETCH_ASSOC);
							foreach ($result as $row) {
								$i++;
								?>
								<tr>
									<td><?php echo $i; ?></td>
									<td>
                                        <strong><?php echo htmlspecialchars($row['supplier_name']); ?></strong><br>
                                        <small class="text-muted">Slug: <?php echo htmlspecialchars($row['supplier_slug']); ?></small>
                                    </td>
									<td>
										<strong>Email:</strong> <?php echo htmlspecialchars($row['supplier_email']); ?><br>
										<strong>Phone:</strong> <?php echo htmlspecialchars($row['supplier_phone']); ?>
									</td>
									<td><?php echo htmlspecialchars($row['supplier_address'] ?: 'Not Specified'); ?></td>
									<td>
                                        <?php
                                        $stmt_u_cnt = $pdo->prepare("SELECT COUNT(*) as pos_count FROM tbl_supplier_user WHERE supplier_id = ? AND UPPER(role) IN ('USER', 'POS_USER', 'CASHIER')");
                                        $stmt_u_cnt->execute(array($row['supplier_id']));
                                        $pos_count = (int)$stmt_u_cnt->fetch(PDO::FETCH_ASSOC)['pos_count'];
                                        $max_u = isset($row['max_pos_users']) && (int)$row['max_pos_users'] > 0 ? (int)$row['max_pos_users'] : 3;
                                        ?>
                                        <span class="label label-warning" style="background-color: #F59E0B; font-weight: bold;"><?php echo htmlspecialchars($row['supplier_plan']); ?></span><br>
                                        <small style="color: #64748b; font-weight: 600;"><i class="fa fa-users"></i> <?php echo $pos_count; ?> / <?php echo $max_u; ?> POS Users</small>
                                    </td>
									<td><strong><?php echo htmlspecialchars($row['supplier_commission']); ?>%</strong></td>
									<td>
                                        <?php if($row['supplier_status'] == 'Active'): ?>
                                            <span class="label label-success">Active</span>
                                        <?php elseif($row['supplier_status'] == 'Pending'): ?>
                                            <span class="label label-warning">Pending Review</span>
                                        <?php else: ?>
                                            <span class="label label-danger">Suspended</span>
                                        <?php endif; ?>
                                    </td>
									<td><?php echo date('d M Y', strtotime($row['created_at'])); ?></td>
									<td>										
										<a href="supplier-edit.php?id=<?php echo $row['supplier_id']; ?>" class="btn btn-primary btn-xs">Edit / Plan</a>
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

<?php require_once('footer.php'); ?>
