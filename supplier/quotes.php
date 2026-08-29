<?php require_once('header.php'); ?>

<section class="content-header">
	<div class="content-header-left">
		<h1>RFQ Quotations Received</h1>
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
								<th>Customer Details</th>
								<th>Requested Product</th>
								<th>Requested Qty</th>
								<th>Notes</th>
								<th>Submit Date</th>
								<th>Status</th>
								<th>Action</th>
							</tr>
						</thead>
						<tbody>
							<?php
							$i=0;
							$statement = $pdo->prepare("SELECT q.*, c.cust_name, c.cust_email, qi.quantity, p.p_name, p.p_id 
                                                        FROM tbl_quote q
                                                        JOIN tbl_customer c ON q.cust_id = c.cust_id
                                                        LEFT JOIN tbl_quote_item qi ON q.quote_id = qi.quote_id
                                                        LEFT JOIN tbl_product p ON qi.product_id = p.p_id
                                                        WHERE q.supplier_id = ?
                                                        ORDER BY q.quote_id DESC");
							$statement->execute(array($supplier_id));
							$result = $statement->fetchAll(PDO::FETCH_ASSOC);
							foreach ($result as $row) {
								$i++;
								?>
								<tr>
									<td><?php echo $i; ?></td>
									<td>
										<strong>Name:</strong> <?php echo htmlspecialchars($row['cust_name']); ?><br>
										<strong>Email:</strong> <?php echo htmlspecialchars($row['cust_email']); ?>
									</td>
									<td>
                                        <?php if ($row['p_name']): ?>
                                            <a href="../product.php?id=<?php echo $row['p_id']; ?>" target="_blank"><?php echo htmlspecialchars($row['p_name']); ?></a>
                                        <?php else: ?>
                                            General Inquiry
                                        <?php endif; ?>
                                    </td>
									<td><?php echo htmlspecialchars($row['quantity']); ?></td>
									<td><?php echo nl2br(htmlspecialchars($row['notes'])); ?></td>
									<td><?php echo date('d M Y, h:i A', strtotime($row['created_at'])); ?></td>
									<td>
                                        <?php if($row['status'] == 'Pending'): ?>
                                            <span class="label label-warning">Pending Review</span>
                                        <?php elseif($row['status'] == 'Approved'): ?>
                                            <span class="label label-success">Quoted / Approved</span>
                                        <?php else: ?>
                                            <span class="label label-danger">Declined</span>
                                        <?php endif; ?>
                                    </td>
									<td>										
										<a href="quotes-reply.php?id=<?php echo $row['quote_id']; ?>" class="btn btn-primary btn-xs">View & Bid</a>
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
