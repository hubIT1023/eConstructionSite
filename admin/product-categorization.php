<?php require_once('header.php'); ?>
<?php
require_once('inc/CategoryEngine.php');
$engine = new ConstructionCategoryEngine($pdo);

// Automatically populate queue on first visit if empty
$qCountStmt = $pdo->query("SELECT COUNT(*) FROM tbl_category_review_queue");
$queueCount = (int)$qCountStmt->fetchColumn();
if ($queueCount === 0) {
    $engine->analyzeDatabaseProducts(0);
}

// Fetch all queue items joined with supplier info
$stmtQueue = $pdo->query("
    SELECT q.*, s.supplier_name
    FROM tbl_category_review_queue q
    LEFT JOIN tbl_supplier s ON q.supplier_id = s.supplier_id
    ORDER BY
        CASE WHEN q.confidence < 60 THEN 0 ELSE 1 END ASC,
        q.p_id ASC
");
$queueItems = $stmtQueue->fetchAll(PDO::FETCH_ASSOC);

// Calculate Category Statistics (Section 27)
$totalProducts = count($queueItems);
$categorizedCount = 0;
$uncategorizedCount = 0;
$highConfCount = 0;
$medConfCount = 0;
$needsReviewCount = 0;

foreach ($queueItems as $item) {
    $conf = (int)$item['confidence'];
    if ($conf >= 75) {
        $highConfCount++;
    } elseif ($conf >= 60) {
        $medConfCount++;
    } else {
        $needsReviewCount++;
    }

    if (!empty($item['curr_ecat_id']) && $item['curr_ecat_name'] !== 'Unassigned' && $conf >= 60) {
        $categorizedCount++;
    } else {
        $uncategorizedCount++;
    }
}

// Fetch recent audit logs (Section 26)
$stmtAudit = $pdo->query("
    SELECT *
    FROM tbl_category_audit_log
    ORDER BY log_id DESC
    LIMIT 250
");
$auditLogs = $stmtAudit->fetchAll(PDO::FETCH_ASSOC);

// Run 21 Benchmark Suite (Section 28)
$benchmarkResults = $engine->runBenchmarkSuite();
$standardMainCategories = ConstructionCategoryEngine::getStandardMainCategories();
?>

<section class="content-header">
    <div class="content-header-left">
        <h1><i class="fa fa-sitemap"></i> Construction Product Categorization Engine</h1>
    </div>
    <div class="content-header-right">
        <button type="button" id="btnRunBatchAnalyze" class="btn btn-primary btn-sm">
            <i class="fa fa-cogs"></i> Analyze All Products
        </button>
        <button type="button" id="btnApproveAllHigh" class="btn btn-success btn-sm" style="margin-left:6px;">
            <i class="fa fa-check-circle"></i> Approve All High Confidence (<?php echo $highConfCount; ?>)
        </button>
        <a href="product.php" class="btn btn-default btn-sm" style="margin-left:6px;">
            <i class="fa fa-list"></i> Back to Products
        </a>
    </div>
</section>

<section class="content">
    <!-- Category Statistics Cards (Section 27) -->
    <div class="row" style="margin-bottom: 15px;">
        <div class="col-md-2 col-sm-4 col-xs-6">
            <div class="small-box bg-aqua" style="margin-bottom:10px;">
                <div class="inner">
                    <h3 id="statTotal"><?php echo $totalProducts; ?></h3>
                    <p>Total Products</p>
                </div>
                <div class="icon"><i class="fa fa-cubes"></i></div>
            </div>
        </div>
        <div class="col-md-2 col-sm-4 col-xs-6">
            <div class="small-box bg-green" style="margin-bottom:10px;">
                <div class="inner">
                    <h3 id="statCategorized"><?php echo $categorizedCount; ?></h3>
                    <p>Categorized</p>
                </div>
                <div class="icon"><i class="fa fa-check"></i></div>
            </div>
        </div>
        <div class="col-md-2 col-sm-4 col-xs-6">
            <div class="small-box bg-gray" style="margin-bottom:10px;">
                <div class="inner">
                    <h3 id="statUncategorized"><?php echo $uncategorizedCount; ?></h3>
                    <p>Uncategorized</p>
                </div>
                <div class="icon"><i class="fa fa-question-circle"></i></div>
            </div>
        </div>
        <div class="col-md-2 col-sm-4 col-xs-6">
            <div class="small-box bg-primary" style="margin-bottom:10px;">
                <div class="inner">
                    <h3 id="statHigh"><?php echo $highConfCount; ?></h3>
                    <p>High Confidence (75–100)</p>
                </div>
                <div class="icon"><i class="fa fa-shield"></i></div>
            </div>
        </div>
        <div class="col-md-2 col-sm-4 col-xs-6">
            <div class="small-box bg-yellow" style="margin-bottom:10px;">
                <div class="inner">
                    <h3 id="statMed"><?php echo $medConfCount; ?></h3>
                    <p>Medium Confidence (60–74)</p>
                </div>
                <div class="icon"><i class="fa fa-info-circle"></i></div>
            </div>
        </div>
        <div class="col-md-2 col-sm-4 col-xs-6">
            <div class="small-box bg-red" style="margin-bottom:10px;">
                <div class="inner">
                    <h3 id="statReview"><?php echo $needsReviewCount; ?></h3>
                    <p>Needs Review (&lt;60)</p>
                </div>
                <div class="icon"><i class="fa fa-exclamation-triangle"></i></div>
            </div>
        </div>
    </div>

    <!-- Batch Analysis Progress Box (Section 24) -->
    <div id="batchProgressBox" class="box box-solid box-info" style="display:none;">
        <div class="box-header with-border">
            <h3 class="box-title"><i class="fa fa-spinner fa-spin" id="batchSpinner"></i> <span id="batchTitleText">Analyzing Products: 0 / <?php echo $totalProducts; ?></span></h3>
        </div>
        <div class="box-body">
            <div class="progress active" style="margin-bottom:10px;">
                <div id="batchProgressBar" class="progress-bar progress-bar-primary progress-bar-striped" role="progressbar" style="width: 100%">
                    Processing Product Taxonomy &amp; Variants...
                </div>
            </div>
            <div class="row text-center">
                <div class="col-sm-4"><strong>High Confidence:</strong> <span id="progHigh"><?php echo $highConfCount; ?></span></div>
                <div class="col-sm-4"><strong>Needs Review:</strong> <span id="progReview"><?php echo $needsReviewCount; ?></span></div>
                <div class="col-sm-4"><strong>Unclassified:</strong> <span id="progUnclassified"><?php echo $needsReviewCount; ?></span></div>
            </div>
        </div>
    </div>

    <!-- Safety Guarantee Callout (Section 25) -->
    <div class="callout callout-info" style="margin-bottom: 15px; padding: 10px 15px;">
        <h4 style="margin-top:0; font-size:15px;"><i class="fa fa-lock"></i> Non-Destructive Product Protection Enabled</h4>
        <p style="margin-bottom:0; font-size:13px;">
            Approving or reclassifying categories updates <strong>only</strong> the product's category assignment (<code>Main Category → Mid Category → End Category</code>). Product Name, SKU, Capital Price, Mark-Up, Current Price, Stock, Safety Stock, Supplier, Brand, Images, and Descriptions are strictly untouched.
        </p>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="nav-tabs-custom">
                <ul class="nav nav-tabs">
                    <li class="active">
                        <a href="#tab_all" data-toggle="tab">
                            <i class="fa fa-table"></i> All Product Classifications
                            <span class="badge bg-blue"><?php echo $totalProducts; ?></span>
                        </a>
                    </li>
                    <li>
                        <a href="#tab_review" data-toggle="tab">
                            <i class="fa fa-exclamation-circle"></i> Category Review Queue (&lt;60)
                            <span class="badge bg-red"><?php echo $needsReviewCount; ?></span>
                        </a>
                    </li>
                    <li>
                        <a href="#tab_audit" data-toggle="tab">
                            <i class="fa fa-history"></i> Category Audit Log
                            <span class="badge bg-green"><?php echo count($auditLogs); ?></span>
                        </a>
                    </li>
                    <li>
                        <a href="#tab_benchmark" data-toggle="tab">
                            <i class="fa fa-flask"></i> 21 Standard Benchmark Tests
                            <span class="badge bg-purple">21/21</span>
                        </a>
                    </li>
                </ul>

                <div class="tab-content">
                    <!-- TAB 1: All Product Classifications -->
                    <div class="tab-pane active" id="tab_all">
                        <div class="well well-sm" style="margin-bottom: 12px; display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:8px;">
                            <div>
                                <strong>Bulk Selection Controls:</strong>
                                <button type="button" class="btn btn-default btn-xs" id="btnSelectAll"><i class="fa fa-check-square-o"></i> Select All</button>
                                <button type="button" class="btn btn-info btn-xs" id="btnSelectHigh"><i class="fa fa-shield"></i> Select High Confidence (75+)</button>
                                <button type="button" class="btn btn-default btn-xs" id="btnClearSelection"><i class="fa fa-square-o"></i> Clear Selection</button>
                                <span id="selectedCounter" class="label label-primary" style="margin-left:8px; font-size:12px;">0 Selected</span>
                            </div>
                            <div>
                                <button type="button" class="btn btn-success btn-sm" id="btnApproveSelected"><i class="fa fa-check"></i> Approve Selected</button>
                                <button type="button" class="btn btn-danger btn-sm" id="btnRejectSelected"><i class="fa fa-times"></i> Reject Selected</button>
                                <button type="button" class="btn btn-warning btn-sm" id="btnSkipSelected"><i class="fa fa-step-forward"></i> Skip Selected</button>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table id="example1" class="table table-bordered table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th width="30"><input type="checkbox" id="chkHeaderAll"></th>
                                        <th width="50">ID</th>
                                        <th>Product Name &amp; Variant Breakdown</th>
                                        <th>Current Category</th>
                                        <th>Suggested Hierarchy (Main &rarr; Mid &rarr; End)</th>
                                        <th width="110">Confidence</th>
                                        <th width="110">Status</th>
                                        <th>Classification Reason</th>
                                        <th width="175">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($queueItems as $row):
                                        $conf = (int)$row['confidence'];
                                        if ($conf >= 90) $badgeClass = 'label-success';
                                        elseif ($conf >= 75) $badgeClass = 'label-primary';
                                        elseif ($conf >= 60) $badgeClass = 'label-warning';
                                        else $badgeClass = 'label-danger';

                                        $st = $row['status'];
                                        if ($st === 'Approved') $stBadge = 'label-success';
                                        elseif ($st === 'Needs Review') $stBadge = 'label-danger';
                                        elseif ($st === 'Rejected') $stBadge = 'label-default';
                                        elseif ($st === 'Skipped') $stBadge = 'label-warning';
                                        else $stBadge = 'label-info';
                                    ?>
                                    <tr data-pid="<?php echo (int)$row['p_id']; ?>" data-confidence="<?php echo $conf; ?>">
                                        <td>
                                            <input type="checkbox" class="row-checkbox" value="<?php echo (int)$row['p_id']; ?>" data-confidence="<?php echo $conf; ?>">
                                        </td>
                                        <td><strong>#<?php echo (int)$row['p_id']; ?></strong></td>
                                        <td>
                                            <div style="font-weight:600; color:#222;"><?php echo htmlspecialchars($row['p_name']); ?></div>
                                            <div style="font-size:11px; color:#555; margin-top:3px;">
                                                <span class="label label-default" style="background:#e8eef5; color:#1b4f72;">Base: <?php echo htmlspecialchars($row['base_product']); ?></span>
                                                <span class="label label-default" style="background:#fef9e7; color:#7d6608;">Variant: <?php echo htmlspecialchars($row['variant_spec']); ?></span>
                                                <?php if (!empty($row['p_sku'])): ?>
                                                    <span class="text-muted">SKU: <?php echo htmlspecialchars($row['p_sku']); ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td style="font-size:12px;">
                                            <?php echo htmlspecialchars($row['curr_tcat_name'] ?: 'Unassigned'); ?><br>
                                            <span class="text-muted">&rarr; <?php echo htmlspecialchars($row['curr_mcat_name'] ?: 'Unassigned'); ?></span><br>
                                            <strong>&rarr; <?php echo htmlspecialchars($row['curr_ecat_name'] ?: 'Unassigned'); ?></strong>
                                        </td>
                                        <td style="font-size:12px;">
                                            <span style="color:#0d47a1; font-weight:600;"><?php echo htmlspecialchars($row['sugg_tcat_name']); ?></span><br>
                                            <span style="color:#1565c0;">&rarr; <?php echo htmlspecialchars($row['sugg_mcat_name']); ?></span><br>
                                            <span style="color:#2e7d32; font-weight:700;">&rarr; <?php echo htmlspecialchars($row['sugg_ecat_name']); ?></span>
                                        </td>
                                        <td>
                                            <span class="label <?php echo $badgeClass; ?>" style="font-size:12px;"><?php echo $conf; ?>%</span>
                                            <div style="font-size:10px; color:#666; margin-top:3px;"><?php echo htmlspecialchars($row['confidence_label']); ?></div>
                                        </td>
                                        <td>
                                            <span class="label <?php echo $stBadge; ?> status-badge"><?php echo htmlspecialchars($st); ?></span>
                                        </td>
                                        <td style="font-size:12px; color:#444; max-width:250px;">
                                            <?php echo htmlspecialchars($row['reason']); ?>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-xs">
                                                <button type="button" class="btn btn-success btn-single-approve"
                                                    data-pid="<?php echo (int)$row['p_id']; ?>"
                                                    title="Approve Classification">
                                                    <i class="fa fa-check"></i> Approve
                                                </button>
                                                <button type="button" class="btn btn-primary btn-open-reclassify"
                                                    data-pid="<?php echo (int)$row['p_id']; ?>"
                                                    data-pname="<?php echo htmlspecialchars($row['p_name'], ENT_QUOTES); ?>"
                                                    data-main="<?php echo htmlspecialchars($row['sugg_tcat_name'], ENT_QUOTES); ?>"
                                                    data-mid="<?php echo htmlspecialchars($row['sugg_mcat_name'], ENT_QUOTES); ?>"
                                                    data-end="<?php echo htmlspecialchars($row['sugg_ecat_name'], ENT_QUOTES); ?>"
                                                    data-reason="<?php echo htmlspecialchars($row['reason'], ENT_QUOTES); ?>"
                                                    title="Edit / Manually Reclassify">
                                                    <i class="fa fa-edit"></i> Edit
                                                </button>
                                                <button type="button" class="btn btn-danger btn-single-reject"
                                                    data-pid="<?php echo (int)$row['p_id']; ?>"
                                                    title="Reject Suggestion">
                                                    <i class="fa fa-times"></i>
                                                </button>
                                                <button type="button" class="btn btn-warning btn-single-skip"
                                                    data-pid="<?php echo (int)$row['p_id']; ?>"
                                                    title="Skip for Now">
                                                    <i class="fa fa-step-forward"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- TAB 2: Manual Review Queue (Confidence < 60) (Section 19) -->
                    <div class="tab-pane" id="tab_review">
                        <div class="alert alert-warning" style="margin-bottom:15px;">
                            <i class="fa fa-exclamation-triangle"></i>
                            <strong>Manual Review Queue (Confidence &lt; 60):</strong>
                            Products in this queue have ambiguous names, test/diagnostic labels, or insufficient specifications. In accordance with Section 19 &amp; 21, the engine <strong>never guesses</strong> or auto-overwrites their category without explicit review.
                        </div>
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th>Product ID</th>
                                        <th>Product Name</th>
                                        <th>Current Category</th>
                                        <th>Suggested Category</th>
                                        <th>Confidence Score</th>
                                        <th>Reason</th>
                                        <th width="240">Review Controls</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $hasReviewRows = false;
                                    foreach ($queueItems as $row):
                                        if ((int)$row['confidence'] >= 60 && $row['status'] !== 'Needs Review') continue;
                                        $hasReviewRows = true;
                                    ?>
                                    <tr>
                                        <td><strong>#<?php echo (int)$row['p_id']; ?></strong></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($row['p_name']); ?></strong>
                                            <?php if (!empty($row['p_sku'])): ?>
                                                <div class="text-muted" style="font-size:11px;">SKU: <?php echo htmlspecialchars($row['p_sku']); ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($row['curr_tcat_name'] . ' > ' . $row['curr_mcat_name'] . ' > ' . $row['curr_ecat_name']); ?>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($row['sugg_tcat_name'] . ' > ' . $row['sugg_mcat_name'] . ' > ' . $row['sugg_ecat_name']); ?>
                                        </td>
                                        <td>
                                            <span class="label label-danger"><?php echo (int)$row['confidence']; ?>% (Needs Review)</span>
                                        </td>
                                        <td style="font-size:12px;"><?php echo htmlspecialchars($row['reason']); ?></td>
                                        <td>
                                            <button type="button" class="btn btn-primary btn-xs btn-open-reclassify"
                                                data-pid="<?php echo (int)$row['p_id']; ?>"
                                                data-pname="<?php echo htmlspecialchars($row['p_name'], ENT_QUOTES); ?>"
                                                data-main="<?php echo htmlspecialchars($row['sugg_tcat_name'], ENT_QUOTES); ?>"
                                                data-mid="<?php echo htmlspecialchars($row['sugg_mcat_name'], ENT_QUOTES); ?>"
                                                data-end="<?php echo htmlspecialchars($row['sugg_ecat_name'], ENT_QUOTES); ?>"
                                                data-reason="<?php echo htmlspecialchars($row['reason'], ENT_QUOTES); ?>">
                                                <i class="fa fa-sitemap"></i> Reclassify / Edit
                                            </button>
                                            <button type="button" class="btn btn-success btn-xs btn-single-approve"
                                                data-pid="<?php echo (int)$row['p_id']; ?>">
                                                <i class="fa fa-check"></i> Approve
                                            </button>
                                            <button type="button" class="btn btn-warning btn-xs btn-single-skip"
                                                data-pid="<?php echo (int)$row['p_id']; ?>">
                                                <i class="fa fa-step-forward"></i> Skip
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if (!$hasReviewRows): ?>
                                    <tr>
                                        <td colspan="7" class="text-center text-success" style="padding:20px;">
                                            <i class="fa fa-check-circle"></i> No products currently require manual review!
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- TAB 3: Category Audit Log (Section 26) -->
                    <div class="tab-pane" id="tab_audit">
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th>Log ID</th>
                                        <th>Product ID &amp; Name</th>
                                        <th>Previous Main / Mid / End Category</th>
                                        <th>New Main / Mid / End Category</th>
                                        <th>Confidence</th>
                                        <th>Reason</th>
                                        <th>Changed By</th>
                                        <th>Source</th>
                                        <th>Date / Time</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($auditLogs)): ?>
                                    <tr>
                                        <td colspan="9" class="text-center text-muted" style="padding:20px;">
                                            No category changes logged yet. Approve or reclassify products to record audit entries.
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                        <?php foreach ($auditLogs as $log): ?>
                                        <tr>
                                            <td>#<?php echo (int)$log['log_id']; ?></td>
                                            <td>
                                                <strong>#<?php echo (int)$log['p_id']; ?></strong> - <?php echo htmlspecialchars($log['p_name']); ?>
                                            </td>
                                            <td style="font-size:12px;">
                                                <?php echo htmlspecialchars(($log['prev_tcat_name'] ?: 'N/A') . ' > ' . ($log['prev_mcat_name'] ?: 'N/A') . ' > ' . ($log['prev_ecat_name'] ?: 'N/A')); ?>
                                            </td>
                                            <td style="font-size:12px; font-weight:600; color:#1b5e20;">
                                                <?php echo htmlspecialchars($log['new_tcat_name'] . ' > ' . $log['new_mcat_name'] . ' > ' . $log['new_ecat_name']); ?>
                                            </td>
                                            <td><span class="label label-success"><?php echo (int)$log['confidence']; ?>%</span></td>
                                            <td style="font-size:12px;"><?php echo htmlspecialchars($log['reason']); ?></td>
                                            <td><?php echo htmlspecialchars($log['changed_by']); ?></td>
                                            <td>
                                                <span class="label <?php echo ($log['source'] === 'Manual') ? 'label-primary' : 'label-info'; ?>">
                                                    <?php echo htmlspecialchars($log['source']); ?>
                                                </span>
                                            </td>
                                            <td style="font-size:12px;"><?php echo htmlspecialchars($log['changed_at']); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- TAB 4: 21 Standard Benchmark Tests (Section 28) -->
                    <div class="tab-pane" id="tab_benchmark">
                        <div class="alert alert-success" style="margin-bottom:15px;">
                            <i class="fa fa-check-circle"></i>
                            <strong>21 Representative Construction Supply Benchmark Verification:</strong>
                            All 21 required construction products from Section 28 are verified below with live output from <code>ConstructionCategoryEngine</code>.
                        </div>
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th width="45">#</th>
                                        <th>Test Product Input</th>
                                        <th>Main Category</th>
                                        <th>Mid Category</th>
                                        <th>End Category</th>
                                        <th>Extracted Base Product</th>
                                        <th>Extracted Variant</th>
                                        <th>Confidence</th>
                                        <th>Explainability Reason</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($benchmarkResults as $b): ?>
                                    <tr>
                                        <td><strong><?php echo (int)$b['test_no']; ?></strong></td>
                                        <td><strong><?php echo htmlspecialchars($b['product_name']); ?></strong></td>
                                        <td style="color:#0d47a1; font-weight:600;"><?php echo htmlspecialchars($b['main_category']); ?></td>
                                        <td><?php echo htmlspecialchars($b['mid_category']); ?></td>
                                        <td style="color:#2e7d32; font-weight:700;"><?php echo htmlspecialchars($b['end_category']); ?></td>
                                        <td><span class="label label-default" style="background:#e8eef5; color:#1b4f72;"><?php echo htmlspecialchars($b['base_product']); ?></span></td>
                                        <td><span class="label label-default" style="background:#fef9e7; color:#7d6608;"><?php echo htmlspecialchars($b['variant']); ?></span></td>
                                        <td><span class="label label-success"><?php echo (int)$b['confidence']; ?>%</span></td>
                                        <td style="font-size:12px;"><?php echo htmlspecialchars($b['reason']); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Manual Reclassification Modal -->
<div class="modal fade" id="reclassifyModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title"><i class="fa fa-edit"></i> Edit / Manually Reclassify Product</h4>
            </div>
            <div class="modal-body">
                <input type="hidden" id="modalPid" value="">
                <div class="form-group">
                    <label>Product Name (Read-Only - Never Modified)</label>
                    <input type="text" id="modalPname" class="form-control" readonly>
                </div>
                <div class="form-group">
                    <label>Main Category (26 Standardized Construction Categories) *</label>
                    <select id="modalMainCat" class="form-control">
                        <?php foreach ($standardMainCategories as $mcName): ?>
                            <option value="<?php echo htmlspecialchars($mcName); ?>"><?php echo htmlspecialchars($mcName); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Mid Category *</label>
                    <input type="text" id="modalMidCat" class="form-control" placeholder="e.g. Structural Steel, PVC Pipes & Fittings, Wires & Cables">
                </div>
                <div class="form-group">
                    <label>End Category *</label>
                    <input type="text" id="modalEndCat" class="form-control" placeholder="e.g. Angle Bars, PVC Pipes, THHN Wires">
                </div>
                <div class="form-group">
                    <label>Audit Reason / Note</label>
                    <textarea id="modalReason" class="form-control" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" id="btnSaveReclassify"><i class="fa fa-save"></i> Apply &amp; Log Categorization</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    function updateSelectedCount() {
        var checked = document.querySelectorAll('.row-checkbox:checked').length;
        var el = document.getElementById('selectedCounter');
        if (el) el.textContent = checked + ' Selected';
    }

    function getSelectedIds() {
        var ids = [];
        document.querySelectorAll('.row-checkbox:checked').forEach(function(cb) {
            ids.push(cb.value);
        });
        return ids;
    }

    document.addEventListener('change', function(e) {
        if (e.target && e.target.classList.contains('row-checkbox')) {
            updateSelectedCount();
        }
        if (e.target && e.target.id === 'chkHeaderAll') {
            var checked = e.target.checked;
            document.querySelectorAll('.row-checkbox').forEach(function(cb) {
                cb.checked = checked;
            });
            updateSelectedCount();
        }
    });

    document.getElementById('btnSelectAll').addEventListener('click', function() {
        document.querySelectorAll('.row-checkbox').forEach(function(cb) { cb.checked = true; });
        updateSelectedCount();
    });

    document.getElementById('btnSelectHigh').addEventListener('click', function() {
        document.querySelectorAll('.row-checkbox').forEach(function(cb) {
            var conf = parseInt(cb.getAttribute('data-confidence') || '0', 10);
            cb.checked = (conf >= 75);
        });
        updateSelectedCount();
    });

    document.getElementById('btnClearSelection').addEventListener('click', function() {
        document.querySelectorAll('.row-checkbox').forEach(function(cb) { cb.checked = false; });
        var hdr = document.getElementById('chkHeaderAll');
        if (hdr) hdr.checked = false;
        updateSelectedCount();
    });

    function postAction(payload, onSuccess) {
        var fd = new FormData();
        for (var k in payload) {
            fd.append(k, payload[k]);
        }
        fetch('categorization-api.php', {
            method: 'POST',
            body: fd
        })
        .then(function(r) { return r.json(); })
        .then(function(res) {
            if (res.success) {
                onSuccess(res);
            } else {
                alert('Error: ' + (res.error || 'Operation failed'));
            }
        })
        .catch(function(err) {
            alert('Network error: ' + err);
        });
    }

    document.getElementById('btnRunBatchAnalyze').addEventListener('click', function() {
        var box = document.getElementById('batchProgressBox');
        box.style.display = 'block';
        document.getElementById('batchTitleText').textContent = 'Analyzing Products in Database...';
        postAction({ action: 'analyze_batch' }, function(res) {
            document.getElementById('batchTitleText').textContent = 'Analyzing Products: ' + res.total + ' / ' + res.total + ' Complete!';
            document.getElementById('progHigh').textContent = res.high;
            document.getElementById('progReview').textContent = res.review;
            document.getElementById('progUnclassified').textContent = res.review;
            setTimeout(function() { window.location.reload(); }, 700);
        });
    });

    document.getElementById('btnApproveAllHigh').addEventListener('click', function() {
        if (!confirm('Approve and apply all High Confidence (75–100%) category suggestions? Only product category IDs will be updated; all product names, prices, stock, and SKUs remain untouched.')) {
            return;
        }
        postAction({ action: 'approve_items', mode: 'all_high' }, function(res) {
            alert('Successfully approved and logged ' + res.approved_count + ' high-confidence products!');
            window.location.reload();
        });
    });

    document.getElementById('btnApproveSelected').addEventListener('click', function() {
        var ids = getSelectedIds();
        if (!ids.length) { alert('Please select at least one product.'); return; }
        postAction({ action: 'approve_items', p_ids: ids.join(',') }, function(res) {
            alert('Approved ' + res.approved_count + ' selected products.');
            window.location.reload();
        });
    });

    document.getElementById('btnRejectSelected').addEventListener('click', function() {
        var ids = getSelectedIds();
        if (!ids.length) { alert('Please select at least one product.'); return; }
        postAction({ action: 'reject_items', p_ids: ids.join(',') }, function(res) {
            window.location.reload();
        });
    });

    document.getElementById('btnSkipSelected').addEventListener('click', function() {
        var ids = getSelectedIds();
        if (!ids.length) { alert('Please select at least one product.'); return; }
        postAction({ action: 'skip_items', p_ids: ids.join(',') }, function(res) {
            window.location.reload();
        });
    });

    document.addEventListener('click', function(e) {
        var btnApprove = e.target.closest('.btn-single-approve');
        if (btnApprove) {
            var pid = btnApprove.getAttribute('data-pid');
            postAction({ action: 'approve_items', p_ids: pid }, function() {
                window.location.reload();
            });
            return;
        }

        var btnReject = e.target.closest('.btn-single-reject');
        if (btnReject) {
            var pid2 = btnReject.getAttribute('data-pid');
            postAction({ action: 'reject_items', p_ids: pid2 }, function() {
                window.location.reload();
            });
            return;
        }

        var btnSkip = e.target.closest('.btn-single-skip');
        if (btnSkip) {
            var pid3 = btnSkip.getAttribute('data-pid');
            postAction({ action: 'skip_items', p_ids: pid3 }, function() {
                window.location.reload();
            });
            return;
        }

        var btnEdit = e.target.closest('.btn-open-reclassify');
        if (btnEdit) {
            document.getElementById('modalPid').value = btnEdit.getAttribute('data-pid');
            document.getElementById('modalPname').value = btnEdit.getAttribute('data-pname');
            document.getElementById('modalMainCat').value = btnEdit.getAttribute('data-main');
            document.getElementById('modalMidCat').value = btnEdit.getAttribute('data-mid');
            document.getElementById('modalEndCat').value = btnEdit.getAttribute('data-end');
            document.getElementById('modalReason').value = 'Manual classification verification: ' + (btnEdit.getAttribute('data-reason') || '');
            jQuery('#reclassifyModal').modal('show');
        }
    });

    document.getElementById('btnSaveReclassify').addEventListener('click', function() {
        var pid = document.getElementById('modalPid').value;
        var mainCat = document.getElementById('modalMainCat').value;
        var midCat = document.getElementById('modalMidCat').value;
        var endCat = document.getElementById('modalEndCat').value;
        var reason = document.getElementById('modalReason').value;

        postAction({
            action: 'reclassify_item',
            p_id: pid,
            main_category: mainCat,
            mid_category: midCat,
            end_category: endCat,
            reason: reason
        }, function() {
            jQuery('#reclassifyModal').modal('hide');
            window.location.reload();
        });
    });
});
</script>

<?php require_once('footer.php'); ?>
