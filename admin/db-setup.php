<?php require_once('header.php'); ?>
<?php require_once('inc/ExcelProductImporter.php'); ?>

<?php
$importer = new ExcelProductImporter($pdo);
$error_message = '';
$success_message = '';
$preview_data = null;
$import_result = null;

// Scan available server preset / template files
$preset_folders = [
    'D:/projects/pos_image/product_list',
    dirname(__DIR__) . '/scratch',
    dirname(__DIR__) . '/assets/uploads/presets'
];
$staged_files = [];
foreach ($preset_folders as $folder) {
    if (is_dir($folder)) {
        $scanned = scandir($folder);
        foreach ($scanned as $f) {
            if ($f === '.' || $f === '..') continue;
            $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
            if (in_array($ext, ['xls', 'xlsx', 'csv'])) {
                $fullPath = rtrim($folder, '/\\') . DIRECTORY_SEPARATOR . $f;
                $staged_files[] = [
                    'filename' => $f,
                    'path' => $fullPath,
                    'size' => round(filesize($fullPath) / 1024, 1) . ' KB',
                    'folder' => basename($folder)
                ];
            }
        }
    }
}

// Handle Form Submission (Preview or Import)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $target_supplier_id = isset($_POST['target_supplier_id']) ? (int)$_POST['target_supplier_id'] : 0;
    $import_mode = isset($_POST['import_mode']) ? trim($_POST['import_mode']) : 'append_update';
    $file_source_type = isset($_POST['file_source_type']) ? trim($_POST['file_source_type']) : 'upload';
    $selected_staged_file = isset($_POST['staged_file_path']) ? trim($_POST['staged_file_path']) : '';
    $action = isset($_POST['form_action']) ? trim($_POST['form_action']) : 'import';

    $temp_file_to_process = '';
    $cleanup_temp = false;

    if ($target_supplier_id <= 0) {
        $error_message = "Please select a target Customer / Supplier account to dedicate the products to.";
    } else {
        // Resolve target file
        if ($file_source_type === 'preset' && !empty($selected_staged_file) && file_exists($selected_staged_file)) {
            $temp_file_to_process = $selected_staged_file;
        } elseif (!empty($_FILES['product_file']['name']) && $_FILES['product_file']['error'] === UPLOAD_ERR_OK) {
            $up_tmp = $_FILES['product_file']['tmp_name'];
            $up_name = $_FILES['product_file']['name'];
            $ext = strtolower(pathinfo($up_name, PATHINFO_EXTENSION));

            if (!in_array($ext, ['xls', 'xlsx', 'csv', 'txt'])) {
                $error_message = "Invalid file type. Please upload a valid .xls, .xlsx, or .csv spreadsheet.";
            } else {
                $dest = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'db_setup_' . time() . '_' . preg_replace('/[^a-zA-Z0-9_\.-]/', '', $up_name);
                if (move_uploaded_file($up_tmp, $dest)) {
                    $temp_file_to_process = $dest;
                    $cleanup_temp = true;
                } else {
                    $temp_file_to_process = $up_tmp;
                }
            }
        } else {
            $error_message = "Please upload an Excel/CSV file or select a staged template spreadsheet.";
        }

        // Execute action if file is valid
        if (empty($error_message) && !empty($temp_file_to_process) && file_exists($temp_file_to_process)) {
            try {
                if ($action === 'preview') {
                    // Preview only
                    $parsed = $importer->parseSpreadsheet($temp_file_to_process);
                    $preview_data = [
                        'total_rows' => count($parsed),
                        'sample_rows' => array_slice($parsed, 0, 15),
                        'categories' => []
                    ];
                    foreach ($parsed as $pr) {
                        $top = $pr['top_category'];
                        $mid = $pr['mid_category'];
                        $preview_data['categories'][$top][$mid] = ($preview_data['categories'][$top][$mid] ?? 0) + 1;
                    }
                    $success_message = "Spreadsheet successfully analyzed! Found " . count($parsed) . " product rows ready for import.";
                } else {
                    // Full Import Execution
                    $import_result = $importer->importCatalog($target_supplier_id, $temp_file_to_process, $import_mode);
                    $success_message = "Product catalog successfully initialized and saved to database!";
                }
            } catch (Exception $e) {
                $error_message = $e->getMessage();
            }

            if ($cleanup_temp && file_exists($temp_file_to_process)) {
                @unlink($temp_file_to_process);
            }
        }
    }
}

// Fetch Suppliers List with Current Product & User Counts
$suppliers_list = [];
try {
    $stmt_s = $pdo->query("
        SELECT s.supplier_id, s.supplier_name, s.supplier_email, s.supplier_plan, s.supplier_status,
               COALESCE(p_cnt.cnt, 0) as current_products,
               COALESCE(u_cnt.cnt, 0) as pos_users
        FROM tbl_supplier s
        LEFT JOIN (SELECT supplier_id, count(*) as cnt FROM tbl_product GROUP BY supplier_id) p_cnt ON s.supplier_id = p_cnt.supplier_id
        LEFT JOIN (SELECT supplier_id, count(*) as cnt FROM tbl_supplier_user GROUP BY supplier_id) u_cnt ON s.supplier_id = u_cnt.supplier_id
        ORDER BY s.supplier_id ASC
    ");
    $suppliers_list = $stmt_s->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}
?>

<section class="content-header">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap;">
        <h1>
            <i class="fa fa-database" style="color: #f59e0b;"></i> Tenant Product DB Setup &amp; Excel Seeder
            <small>Initialize and upload dedicated product catalogs for SaaS suppliers &amp; customer stores</small>
        </h1>
        <ol class="breadcrumb" style="position: static; padding: 0; background: transparent; margin: 0;">
            <li><a href="index.php"><i class="fa fa-dashboard"></i> Home</a></li>
            <li><a href="supplier.php">Manage Suppliers</a></li>
            <li class="active">DB Setup</li>
        </ol>
    </div>
</section>

<section class="content">

    <?php if (!empty($error_message)): ?>
    <div class="alert alert-danger alert-dismissible" style="border-radius: 6px; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
        <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
        <h4><i class="icon fa fa-ban"></i> Import Error!</h4>
        <?php echo htmlspecialchars($error_message); ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($success_message) && $import_result): ?>
    <div class="alert alert-success alert-dismissible" style="border-radius: 6px; background-color: #059669; border-color: #047857; color: #fff; box-shadow: 0 4px 12px rgba(5, 150, 105, 0.15);">
        <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
        <h4><i class="icon fa fa-check-circle"></i> Setup Complete!</h4>
        <p style="font-size: 14px; margin-bottom: 8px;"><?php echo htmlspecialchars($success_message); ?></p>
        <div style="background: rgba(255,255,255,0.15); padding: 10px 14px; border-radius: 6px; font-size: 13px;">
            <strong>Target Store:</strong> <?php echo htmlspecialchars($import_result['supplier_name']); ?> (Supplier ID #<?php echo $import_result['supplier_id']; ?>)<br>
            <strong>Mode:</strong> <?php echo ($import_result['mode'] === 'fresh_seed') ? '🟡 Fresh Database Seed (Replaced Catalog)' : '🟢 Safe Sync (Appended / Updated)'; ?><br>
            <strong>Total Processed:</strong> <?php echo number_format($import_result['total_processed']); ?> items &nbsp;|&nbsp;
            <strong>Inserted New:</strong> <?php echo number_format($import_result['inserted_count']); ?> &nbsp;|&nbsp;
            <strong>Updated:</strong> <?php echo number_format($import_result['updated_count']); ?><br>
            <strong>Total Physical Stock:</strong> <?php echo number_format($import_result['total_stock']); ?> units &nbsp;|&nbsp;
            <strong>Total Catalog Valuation:</strong> &#8369;<?php echo number_format($import_result['total_valuation'], 2); ?>
        </div>
        <div style="margin-top: 10px;">
            <a href="product.php" class="btn btn-default btn-sm" style="font-weight: 700; color: #065f46; background: #fff; border: none;">
                <i class="fa fa-shopping-bag"></i> View in Product Management
            </a>
        </div>
    </div>
    <?php elseif (!empty($success_message) && $preview_data): ?>
    <div class="alert alert-info alert-dismissible" style="border-radius: 6px; background-color: #0284c7; border-color: #0369a1; color: #fff;">
        <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
        <h4><i class="icon fa fa-info-circle"></i> Spreadsheet Pre-check Passed!</h4>
        <?php echo htmlspecialchars($success_message); ?>
    </div>
    <?php endif; ?>

    <div class="row">
        <!-- Setup Configuration Form -->
        <div class="col-md-7">
            <div class="box box-warning" style="border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.06);">
                <div class="box-header with-border" style="padding: 14px 18px;">
                    <h3 class="box-title" style="font-weight: 700; color: #1e293b; font-size: 16px;">
                        <i class="fa fa-upload text-warning"></i> Product Catalog Importer &amp; DB Pointing
                    </h3>
                </div>
                <form action="db-setup.php" method="post" enctype="multipart/form-data" class="form-horizontal" id="dbSetupForm">
                    <div class="box-body" style="padding: 18px 22px;">
                        
                        <!-- Step 1: Target Account -->
                        <div class="form-group" style="margin-bottom: 20px;">
                            <label class="col-sm-3 control-label" style="text-align: left; font-weight: 700; color: #334155;">
                                <span class="badge" style="background: #f59e0b; margin-right: 4px;">1</span> Target Store Account:
                            </label>
                            <div class="col-sm-9">
                                <select name="target_supplier_id" id="target_supplier_id" class="form-control select2" style="width: 100%;" required>
                                    <option value="">-- Select Customer / Supplier Store --</option>
                                    <?php foreach ($suppliers_list as $sup): ?>
                                    <option value="<?php echo $sup['supplier_id']; ?>" <?php if(isset($_POST['target_supplier_id']) && (int)$_POST['target_supplier_id'] === (int)$sup['supplier_id']) echo 'selected'; ?>>
                                        [ID #<?php echo $sup['supplier_id']; ?>] <?php echo htmlspecialchars($sup['supplier_name']); ?> 
                                        (<?php echo $sup['current_products']; ?> products | <?php echo $sup['supplier_plan']; ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="text-muted" style="display: block; margin-top: 4px;">
                                    All imported products will be dedicated and isolated to this supplier tenant.
                                </small>
                            </div>
                        </div>

                        <!-- Step 2: File Source Selection -->
                        <div class="form-group" style="margin-bottom: 20px;">
                            <label class="col-sm-3 control-label" style="text-align: left; font-weight: 700; color: #334155;">
                                <span class="badge" style="background: #f59e0b; margin-right: 4px;">2</span> Spreadsheet File:
                            </label>
                            <div class="col-sm-9">
                                
                                <div style="margin-bottom: 10px;">
                                    <label style="font-weight: 600; margin-right: 15px; cursor: pointer;">
                                        <input type="radio" name="file_source_type" value="upload" <?php if(!isset($_POST['file_source_type']) || $_POST['file_source_type'] === 'upload') echo 'checked'; ?> onclick="toggleFileSource('upload')">
                                        <i class="fa fa-cloud-upload text-primary"></i> Upload from Computer
                                    </label>
                                    <?php if (!empty($staged_files)): ?>
                                    <label style="font-weight: 600; cursor: pointer;">
                                        <input type="radio" name="file_source_type" value="preset" <?php if(isset($_POST['file_source_type']) && $_POST['file_source_type'] === 'preset') echo 'checked'; ?> onclick="toggleFileSource('preset')">
                                        <i class="fa fa-folder-open text-warning"></i> Pick Staged Template (<?php echo count($staged_files); ?> found)
                                    </label>
                                    <?php endif; ?>
                                </div>

                                <!-- Upload Input Container -->
                                <div id="upload_container" style="<?php echo (isset($_POST['file_source_type']) && $_POST['file_source_type'] === 'preset') ? 'display:none;' : ''; ?>">
                                    <input type="file" name="product_file" id="product_file" class="form-control" accept=".xls,.xlsx,.csv,.txt" style="border: 2px dashed #cbd5e1; padding: 10px; height: auto; background: #f8fafc; border-radius: 6px;">
                                    <small class="text-muted" style="display: block; margin-top: 4px;">
                                        Supported Formats: Excel <code>.xls</code> (SpreadsheetML), <code>.xlsx</code>, or <code>.csv</code>
                                    </small>
                                </div>

                                <!-- Staged Files Dropdown Container -->
                                <?php if (!empty($staged_files)): ?>
                                <div id="preset_container" style="<?php echo (!isset($_POST['file_source_type']) || $_POST['file_source_type'] !== 'preset') ? 'display:none;' : ''; ?>">
                                    <select name="staged_file_path" id="staged_file_path" class="form-control select2" style="width: 100%;">
                                        <?php foreach ($staged_files as $sf): ?>
                                        <option value="<?php echo htmlspecialchars($sf['path']); ?>">
                                            <?php echo htmlspecialchars($sf['filename']); ?> (<?php echo $sf['size']; ?> in /<?php echo $sf['folder']; ?>)
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small class="text-muted" style="display: block; margin-top: 4px;">
                                        Auto-detected template file in project workspace.
                                    </small>
                                </div>
                                <?php endif; ?>

                            </div>
                        </div>

                        <!-- Step 3: Import Mode -->
                        <div class="form-group" style="margin-bottom: 20px;">
                            <label class="col-sm-3 control-label" style="text-align: left; font-weight: 700; color: #334155;">
                                <span class="badge" style="background: #f59e0b; margin-right: 4px;">3</span> Import Mode:
                            </label>
                            <div class="col-sm-9">
                                <div class="radio" style="margin-bottom: 8px;">
                                    <label style="font-weight: 700; color: #047857;">
                                        <input type="radio" name="import_mode" value="append_update" <?php if(!isset($_POST['import_mode']) || $_POST['import_mode'] === 'append_update') echo 'checked'; ?>>
                                        🟢 Safe Sync / Append &amp; Update (Recommended)
                                    </label>
                                    <p class="text-muted" style="margin: 2px 0 0 20px; font-size: 12px;">
                                        Inserts new products and updates prices/stocks for existing SKUs without deleting past POS sales or transaction records.
                                    </p>
                                </div>
                                <div class="radio">
                                    <label style="font-weight: 700; color: #b45309;">
                                        <input type="radio" name="import_mode" value="fresh_seed" <?php if(isset($_POST['import_mode']) && $_POST['import_mode'] === 'fresh_seed') echo 'checked'; ?>>
                                        🟡 Fresh Database Seed (Clean Tenant Onboarding)
                                    </label>
                                    <p class="text-muted" style="margin: 2px 0 0 20px; font-size: 12px;">
                                        Wipes only previous catalog items for this specific supplier and cleanly imports the fresh spreadsheet with clean IDs.
                                    </p>
                                </div>
                            </div>
                        </div>

                    </div>
                    
                    <div class="box-footer" style="background: #f8fafc; border-top: 1px solid #e2e8f0; padding: 14px 22px; display: flex; justify-content: space-between; align-items: center;">
                        <input type="hidden" name="form_action" id="form_action" value="import">
                        
                        <button type="submit" onclick="document.getElementById('form_action').value='preview';" class="btn btn-default" style="font-weight: 700; border-radius: 5px;">
                            <i class="fa fa-eye text-primary"></i> Preview Spreadsheet Data
                        </button>
                        
                        <button type="submit" onclick="document.getElementById('form_action').value='import'; return confirm('Are you sure you want to execute database import for the selected supplier?');" class="btn btn-warning" style="font-weight: 700; background-color: #f59e0b; border-color: #d97706; color: #fff; border-radius: 5px; padding: 7px 20px;">
                            <i class="fa fa-database"></i> Confirm &amp; Seed Database
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Tenant Overview & Instructions Card -->
        <div class="col-md-5">
            <div class="box box-solid" style="border-radius: 8px; border: 1px solid #e2e8f0; box-shadow: 0 2px 8px rgba(0,0,0,0.06);">
                <div class="box-header with-border" style="background: #f8fafc; padding: 12px 16px;">
                    <h3 class="box-title" style="font-size: 14px; font-weight: 700; color: #334155;">
                        <i class="fa fa-info-circle text-primary"></i> Multi-Tenant DB Seeder Guide
                    </h3>
                </div>
                <div class="box-body" style="padding: 16px; font-size: 13px; line-height: 1.6; color: #475569;">
                    <h5 style="font-weight: 800; color: #0f172a; margin-top: 0;">How it works:</h5>
                    <ul style="padding-left: 18px; margin-bottom: 14px;">
                        <li><strong>Tenant Dedication:</strong> Automatically links every row to the chosen <code>supplier_id</code> in PostgreSQL (<code>ecomDB</code>).</li>
                        <li><strong>Automated 3-Tier Taxonomy:</strong> Creates or links <code>Top Category</code> &rarr; <code>Mid Category</code> &rarr; <code>End Category</code> on the fly.</li>
                        <li><strong>Financial Normalization:</strong> Strips currency signs (<code>&#8369;</code>, commas) and computes wholesale capital, retail SRP, and stock quantities cleanly.</li>
                        <li><strong>POS Ready:</strong> Automatically registers standard unit inventory in <code>tbl_product_size</code> so POS cashiers can immediately ring up sales.</li>
                    </ul>

                    <div style="background: #eff6ff; border-left: 3.5px solid #3b82f6; padding: 10px 12px; border-radius: 4px; font-size: 12px; color: #1e40af;">
                        <i class="fa fa-lightbulb-o"></i> <strong>Default Template Path:</strong><br>
                        <code>D:\projects\pos_image\product_list\eConstructionSite_Products_2026-10-10.xls</code> (802 products across 8 categories).
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Preview Table Section (If Preview Triggered) -->
    <?php if (!empty($preview_data)): ?>
    <div class="row">
        <div class="col-md-12">
            <div class="box box-info" style="border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.06);">
                <div class="box-header with-border" style="padding: 14px 18px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap;">
                    <h3 class="box-title" style="font-weight: 700; color: #0f172a; font-size: 15px;">
                        <i class="fa fa-table text-info"></i> Pre-Import Preview: <?php echo number_format($preview_data['total_rows']); ?> Products Detected
                    </h3>
                    <span class="label label-primary" style="font-size: 12px;"><?php echo count($preview_data['categories']); ?> Top Categories</span>
                </div>
                <div class="box-body table-responsive" style="padding: 10px 18px;">
                    <table class="table table-bordered table-striped" style="font-size: 12.5px;">
                        <thead>
                            <tr style="background: #f1f5f9; color: #334155;">
                                <th style="width: 40px; text-align: center;">#</th>
                                <th>Product Name</th>
                                <th>SKU</th>
                                <th>Top Category</th>
                                <th>Mid Category</th>
                                <th>End Category</th>
                                <th class="text-right">Capital Cost</th>
                                <th class="text-right">Selling Price (SRP)</th>
                                <th class="text-center">Stock</th>
                                <th class="text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($preview_data['sample_rows'] as $p_idx => $pr): ?>
                            <tr>
                                <td style="text-align: center; color: #94a3b8; font-weight: 600;"><?php echo $p_idx + 1; ?></td>
                                <td><strong><?php echo htmlspecialchars($pr['name']); ?></strong></td>
                                <td><code><?php echo htmlspecialchars($pr['sku'] ?: 'N/A'); ?></code></td>
                                <td><?php echo htmlspecialchars($pr['top_category']); ?></td>
                                <td><?php echo htmlspecialchars($pr['mid_category']); ?></td>
                                <td><span class="badge" style="background: #e2e8f0; color: #334155; font-size: 11px;"><?php echo htmlspecialchars($pr['end_category']); ?></span></td>
                                <td class="text-right text-muted">&#8369;<?php echo number_format($pr['capital_cost'], 2); ?></td>
                                <td class="text-right" style="font-weight: 700; color: #0284c7;">&#8369;<?php echo number_format($pr['current_price'], 2); ?></td>
                                <td class="text-center font-weight-bold" style="color: #059669;"><?php echo number_format($pr['stock']); ?></td>
                                <td class="text-center">
                                    <span class="label <?php echo $pr['is_active'] ? 'label-success' : 'label-default'; ?>">
                                        <?php echo $pr['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php if ($preview_data['total_rows'] > 15): ?>
                        <div style="text-align: center; padding: 8px; color: #64748b; font-size: 12px; font-style: italic;">
                            Showing first 15 sample rows of <?php echo number_format($preview_data['total_rows']); ?> products in spreadsheet.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

</section>

<script>
function toggleFileSource(type) {
    var uploadBox = document.getElementById('upload_container');
    var presetBox = document.getElementById('preset_container');
    if (type === 'preset') {
        if (uploadBox) uploadBox.style.display = 'none';
        if (presetBox) presetBox.style.display = 'block';
    } else {
        if (uploadBox) uploadBox.style.display = 'block';
        if (presetBox) presetBox.style.display = 'none';
    }
}
</script>

<?php require_once('footer.php'); ?>
