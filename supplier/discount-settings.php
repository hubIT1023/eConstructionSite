<?php require_once('header.php'); ?>

<?php
// Strict Server-Side Authorization: Admin Only
if (!is_supplier_admin()) {
    header("Location: pos.php");
    exit;
}

$supplier_id = (int)$_SESSION['supplier_user']['supplier_id'];

// Handle Form Submission
if (isset($_POST['form_discount_settings'])) {
    $discount_enabled = isset($_POST['discount_enabled']) ? 1 : 0;
    $discount_normal_max = isset($_POST['discount_normal_max']) ? floatval($_POST['discount_normal_max']) : 10.00;
    $discount_special_enabled = isset($_POST['discount_special_enabled']) ? 1 : 0;
    $discount_absolute_max = isset($_POST['discount_absolute_max']) ? floatval($_POST['discount_absolute_max']) : 20.00;
    $discount_require_admin_approval = isset($_POST['discount_require_admin_approval']) ? 1 : 0;

    $valid = 1;

    if ($discount_normal_max < 0 || $discount_normal_max > 100) {
        $valid = 0;
        $error_message = "Normal Maximum Discount must be between 0.00% and 100.00%.";
    } elseif ($discount_absolute_max < 0 || $discount_absolute_max > 100) {
        $valid = 0;
        $error_message = "Absolute Maximum Discount must be between 0.00% and 100.00%.";
    } elseif ($discount_special_enabled && $discount_absolute_max < $discount_normal_max) {
        $valid = 0;
        $error_message = "Absolute Maximum Discount (" . number_format($discount_absolute_max, 2) . "%) cannot be less than Normal Maximum Discount (" . number_format($discount_normal_max, 2) . "%).";
    }

    if ($valid == 1) {
        $stmt_up = $pdo->prepare("
            UPDATE tbl_supplier SET 
                discount_enabled = ?,
                discount_normal_max = ?,
                discount_special_enabled = ?,
                discount_absolute_max = ?,
                discount_require_admin_approval = ?
            WHERE supplier_id = ?
        ");
        $stmt_up->execute(array(
            $discount_enabled,
            $discount_normal_max,
            $discount_special_enabled,
            $discount_absolute_max,
            $discount_require_admin_approval,
            $supplier_id
        ));

        $success_message = "Discount configurations have been updated successfully!";
    }
}

// Fetch Current Settings
ensure_supplier_user_schema($pdo);
$store = [];
try {
    $stmt_s = $pdo->prepare("SELECT supplier_name, discount_enabled, discount_normal_max, discount_special_enabled, discount_absolute_max, discount_require_admin_approval FROM tbl_supplier WHERE supplier_id = ?");
    $stmt_s->execute(array($supplier_id));
    $store = $stmt_s->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

$d_enabled = isset($store['discount_enabled']) ? (int)$store['discount_enabled'] : 1;
$d_normal_max = isset($store['discount_normal_max']) ? floatval($store['discount_normal_max']) : 10.00;
$d_special_enabled = isset($store['discount_special_enabled']) ? (int)$store['discount_special_enabled'] : 1;
$d_abs_max = isset($store['discount_absolute_max']) ? floatval($store['discount_absolute_max']) : 20.00;
$d_req_admin = isset($store['discount_require_admin_approval']) ? (int)$store['discount_require_admin_approval'] : 1;
$store_name = isset($store['supplier_name']) ? $store['supplier_name'] : 'Store';
?>

<section class="content-header">
    <div class="content-header-left">
        <h1><i class="fa fa-percent text-primary"></i> POS Item Discount Settings</h1>
    </div>
    <div class="content-header-right">
        <a href="discount-approvals.php" class="btn btn-primary btn-sm"><i class="fa fa-check-square-o"></i> View Discount Approval Queue</a>
        <a href="pos.php" class="btn btn-default btn-sm"><i class="fa fa-calculator"></i> Return to POS</a>
    </div>
</section>

<section class="content">
    <div class="row">
        <div class="col-md-8 col-md-offset-2">

            <?php if (!empty($error_message)): ?>
                <div class="callout callout-danger" style="border-radius: 6px;">
                    <h4><i class="fa fa-ban"></i> Validation Error</h4>
                    <p><?php echo $error_message; ?></p>
                </div>
            <?php endif; ?>

            <?php if (!empty($success_message)): ?>
                <div class="callout callout-success" style="border-radius: 6px;">
                    <h4><i class="fa fa-check-circle"></i> Success</h4>
                    <p><?php echo $success_message; ?></p>
                </div>
            <?php endif; ?>

            <div class="box box-primary" style="border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.06);">
                <div class="box-header with-border" style="background: #f8fafc; border-radius: 8px 8px 0 0;">
                    <h3 class="box-title" style="font-weight: 700; color: #1e293b;">
                        <i class="fa fa-sliders text-primary"></i> Discount Approval Policy for <?php echo htmlspecialchars($store['supplier_name']); ?>
                    </h3>
                </div>

                <div class="box-body" style="padding: 25px 30px;">
                    <form class="form-horizontal" action="" method="post">
                        
                        <!-- 1. Enable Item Discount -->
                        <div class="form-group" style="padding-bottom: 15px; border-bottom: 1px solid #f1f5f9;">
                            <label class="col-sm-4 control-label" style="text-align: left; font-size: 14px;">
                                Enable Item Discount:
                            </label>
                            <div class="col-sm-8">
                                <label style="cursor: pointer; font-size: 14px; font-weight: 600; display: inline-flex; align-items: center; gap: 8px; margin-top: 5px;">
                                    <input type="checkbox" name="discount_enabled" value="1" <?php if ($d_enabled == 1) echo 'checked'; ?> style="width: 18px; height: 18px;">
                                    <span class="text-success"><i class="fa fa-toggle-on"></i> Allow cashiers to request discounts on Current Sale items</span>
                                </label>
                                <p class="text-muted" style="font-size: 12px; margin-top: 4px;">When disabled, the [ DISCOUNT ] button is hidden in the POS interface.</p>
                            </div>
                        </div>

                        <!-- 2. Normal Maximum Discount -->
                        <div class="form-group" style="padding-bottom: 15px; border-bottom: 1px solid #f1f5f9;">
                            <label class="col-sm-4 control-label" style="text-align: left; font-size: 14px;">
                                Normal Maximum Discount (%):
                            </label>
                            <div class="col-sm-8">
                                <div class="input-group" style="max-width: 220px;">
                                    <input type="number" step="0.01" min="0" max="100" class="form-control" name="discount_normal_max" value="<?php echo number_format($d_normal_max, 2, '.', ''); ?>" required style="font-size: 15px; font-weight: bold; color: #0284c7;">
                                    <span class="input-group-addon" style="font-weight: bold; background: #e0f2fe; color: #0369a1;">%</span>
                                </div>
                                <p class="text-muted" style="font-size: 12px; margin-top: 6px;">
                                    Standard discount requests up to this percentage can be approved directly by the <strong>Supplier Admin</strong>.
                                </p>
                            </div>
                        </div>

                        <!-- 3. Enable Special Discount Approval -->
                        <div class="form-group" style="padding-bottom: 15px; border-bottom: 1px solid #f1f5f9;">
                            <label class="col-sm-4 control-label" style="text-align: left; font-size: 14px;">
                                Enable Special Discount Approval:
                            </label>
                            <div class="col-sm-8">
                                <label style="cursor: pointer; font-size: 14px; font-weight: 600; display: inline-flex; align-items: center; gap: 8px; margin-top: 5px;">
                                    <input type="checkbox" name="discount_special_enabled" value="1" <?php if ($d_special_enabled == 1) echo 'checked'; ?> style="width: 18px; height: 18px;">
                                    <span class="text-warning" style="color: #d97706;"><i class="fa fa-shield"></i> Allow escalated special approval for discounts exceeding normal maximum</span>
                                </label>
                                <p class="text-muted" style="font-size: 12px; margin-top: 4px;">
                                    If disabled, any discount request above the Normal Maximum is automatically rejected.
                                </p>
                            </div>
                        </div>

                        <!-- 4. Absolute Maximum Discount -->
                        <div class="form-group" style="padding-bottom: 15px; border-bottom: 1px solid #f1f5f9;">
                            <label class="col-sm-4 control-label" style="text-align: left; font-size: 14px;">
                                Absolute Maximum Discount (%):
                            </label>
                            <div class="col-sm-8">
                                <div class="input-group" style="max-width: 220px;">
                                    <input type="number" step="0.01" min="0" max="100" class="form-control" name="discount_absolute_max" value="<?php echo number_format($d_abs_max, 2, '.', ''); ?>" required style="font-size: 15px; font-weight: bold; color: #dc2626;">
                                    <span class="input-group-addon" style="font-weight: bold; background: #fee2e2; color: #991b1b;">%</span>
                                </div>
                                <p class="text-muted" style="font-size: 12px; margin-top: 6px;">
                                    The hard ceiling limit. Any request exceeding this percentage is <strong>strictly prohibited</strong> and blocked by the system.
                                </p>
                            </div>
                        </div>

                        <!-- 5. Require Admin Approval -->
                        <div class="form-group" style="padding-bottom: 15px; border-bottom: 1px solid #f1f5f9;">
                            <label class="col-sm-4 control-label" style="text-align: left; font-size: 14px;">
                                Require Admin Approval:
                            </label>
                            <div class="col-sm-8">
                                <label style="cursor: pointer; font-size: 14px; font-weight: 600; display: inline-flex; align-items: center; gap: 8px; margin-top: 5px;">
                                    <input type="checkbox" name="discount_require_admin_approval" value="1" <?php if ($d_req_admin == 1) echo 'checked'; ?> style="width: 18px; height: 18px;">
                                    <span class="text-primary"><i class="fa fa-lock"></i> All discount requests require authorization before checkout is unlocked</span>
                                </label>
                                <p class="text-muted" style="font-size: 12px; margin-top: 4px;">
                                    When checked, cashiers cannot complete payments on sales with pending discount requests.
                                </p>
                            </div>
                        </div>

                        <!-- Policy Hierarchy Summary Box -->
                        <div style="background: #f8fafc; border: 1.5px solid #e2e8f0; border-radius: 8px; padding: 15px 18px; margin-bottom: 20px;">
                            <h5 style="margin: 0 0 10px 0; font-weight: 800; color: #0f172a; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px;">
                                <i class="fa fa-info-circle text-info"></i> Effective Approval Hierarchy Rules
                            </h5>
                            <table class="table table-bordered table-condensed" style="background: #fff; margin-bottom: 0; font-size: 12px;">
                                <thead>
                                    <tr style="background: #f1f5f9;">
                                        <th style="width: 30%;">Discount Range</th>
                                        <th style="width: 35%;">Classification</th>
                                        <th style="width: 35%;">Required Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><strong>0.00% &ndash; <?php echo number_format($d_normal_max, 2); ?>%</strong></td>
                                        <td><span class="label label-success">LEVEL 1: NORMAL DISCOUNT</span></td>
                                        <td>Supplier Admin Approval / Modification</td>
                                    </tr>
                                    <tr>
                                        <td><strong><?php echo number_format($d_normal_max + 0.01, 2); ?>% &ndash; <?php echo number_format($d_abs_max, 2); ?>%</strong></td>
                                        <td><span class="label label-warning" style="background-color: #d97706;">LEVEL 2: SPECIAL DISCOUNT</span></td>
                                        <td><?php echo $d_special_enabled ? 'Escalated Special Approval Required' : '<span class="text-danger font-weight-bold">Rejected (Special Disabled)</span>'; ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>&gt; <?php echo number_format($d_abs_max, 2); ?>%</strong></td>
                                        <td><span class="label label-danger">LEVEL 3: PROHIBITED</span></td>
                                        <td><strong class="text-danger">DISCOUNT NOT ALLOWED (Blocked)</strong></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="form-group" style="margin-bottom: 0;">
                            <div class="col-sm-offset-4 col-sm-8">
                                <button type="submit" class="btn btn-success btn-lg" name="form_discount_settings" style="font-weight: bold; border-radius: 6px; padding: 10px 25px;">
                                    <i class="fa fa-save"></i> Save Discount Settings
                                </button>
                            </div>
                        </div>

                    </form>
                </div>
            </div>

        </div>
    </div>
</section>

<?php require_once('footer.php'); ?>
