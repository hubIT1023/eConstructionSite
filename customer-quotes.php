<?php require_once('header.php'); ?>

<?php
// Enforce login
if(!isset($_SESSION['customer'])) {
    header('location: login.php');
    exit;
}
$cust_id = $_SESSION['customer']['cust_id'];
?>

<div class="page">
    <div class="container">
        <div class="row">            
            <div class="col-md-12"> 
                <?php require_once('customer-sidebar.php'); ?>
            </div>
            <div class="col-md-12" style="margin-top: 20px;">
                <div class="user-content">
                    <h3 style="font-weight: bold; color: #1F2937;"><i class="fa fa-file-text-o"></i> My RFQ Quote Proposals</h3>
                    <p class="text-muted">Track incoming proposals, negotiate prices with suppliers, and complete wholesale orders.</p>
                    <hr>

                    <div class="table-responsive">
                        <table class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Supplier Name</th>
                                    <th>Requested Material</th>
                                    <th>Qty Requested</th>
                                    <th>Supplier Proposal Bid Price</th>
                                    <th>Status</th>
                                    <th>Date Submitted</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $i = 0;
                                $statement = $pdo->prepare("SELECT q.*, s.supplier_name, qi.quantity, qi.unit_price, p.p_name, p.p_id 
                                                            FROM tbl_quote q
                                                            JOIN tbl_supplier s ON q.supplier_id = s.supplier_id
                                                            LEFT JOIN tbl_quote_item qi ON q.quote_id = qi.quote_id
                                                            LEFT JOIN tbl_product p ON qi.product_id = p.p_id
                                                            WHERE q.cust_id = ?
                                                            ORDER BY q.quote_id DESC");
                                $statement->execute(array($cust_id));
                                $quotes = $statement->fetchAll(PDO::FETCH_ASSOC);

                                if (count($quotes) > 0) {
                                    foreach ($quotes as $row) {
                                        $i++;
                                        ?>
                                        <tr>
                                            <td><?php echo $i; ?></td>
                                            <td><strong><?php echo htmlspecialchars($row['supplier_name']); ?></strong></td>
                                            <td>
                                                <?php if($row['p_name']): ?>
                                                    <a href="product.php?id=<?php echo $row['p_id']; ?>" target="_blank"><?php echo htmlspecialchars($row['p_name']); ?></a>
                                                <?php else: ?>
                                                    General Inquiry
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($row['quantity']); ?></td>
                                            <td>
                                                <strong>
                                                    <?php echo $row['unit_price'] > 0 ? '$' . htmlspecialchars($row['unit_price']) . ' / unit' : '<span class="text-warning">Awaiting Bid</span>'; ?>
                                                </strong>
                                            </td>
                                            <td>
                                                <?php if($row['status'] == 'Pending'): ?>
                                                    <span class="label label-warning" style="background-color: #F59E0B;">Awaiting Supplier Proposal</span>
                                                <?php elseif($row['status'] == 'Approved'): ?>
                                                    <span class="label label-success" style="background-color: #5cb85c;">Proposal Bid Ready</span>
                                                <?php else: ?>
                                                    <span class="label label-danger">Declined / Closed</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo date('d M Y, h:i A', strtotime($row['created_at'])); ?></td>
                                            <td>
                                                <a href="customer-quotes-detail.php?id=<?php echo $row['quote_id']; ?>" class="btn btn-warning btn-xs" style="background-color: #F59E0B; border-color: #F59E0B; font-weight: bold;">View Details & Chat</a>
                                            </td>
                                        </tr>
                                        <?php
                                    }
                                } else {
                                    echo "<tr><td colspan='8' class='text-center'>You have not submitted any B2B RFQs yet.</td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>                
            </div>
        </div>
    </div>
</div>

<?php require_once('footer.php'); ?>
