<?php
ob_start();
session_start();
include("inc/config.php");
include("inc/functions.php");
include("inc/CSRF_Protect.php");
require_once("inc/CategoryEngine.php");

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized: Admin session required.']);
    exit;
}

$adminName = $_SESSION['user']['full_name'] ?? $_SESSION['user']['email'] ?? 'Admin';
$engine = new ConstructionCategoryEngine($pdo);
$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'analyze_batch':
            $stats = $engine->analyzeDatabaseProducts(0);
            echo json_encode([
                'success' => true,
                'total'   => $stats['total'],
                'high'    => $stats['high'],
                'medium'  => $stats['medium'],
                'review'  => $stats['review'],
            ]);
            break;

        case 'classify_single':
            $pName  = trim($_POST['p_name'] ?? '');
            $pSku   = trim($_POST['p_sku'] ?? '');
            $pBrand = trim($_POST['p_brand'] ?? '');
            $pDesc  = trim($_POST['p_description'] ?? '');
            $pShort = trim($_POST['p_short_description'] ?? '');
            $pSpecs = trim($_POST['p_specs'] ?? '');
            $resolveIds = !empty($_POST['resolve_ids']);

            $res = $engine->classifyProduct([
                'p_name'              => $pName,
                'p_sku'               => $pSku,
                'p_brand'             => $pBrand,
                'p_description'       => $pDesc,
                'p_short_description' => $pShort,
                'p_specs'             => $pSpecs,
            ]);

            $ids = ['tcat_id' => 0, 'mcat_id' => 0, 'ecat_id' => 0];
            $midOptions = [];
            $endOptions = [];

            if ($resolveIds && $res['confidence'] >= 60) {
                $ids = $engine->resolveOrCreateCategoryIds(
                    $res['main_category'],
                    $res['mid_category'],
                    $res['end_category']
                );
                if ($ids['tcat_id'] > 0) {
                    $stM = $pdo->prepare("SELECT mcat_id, mcat_name FROM tbl_mid_category WHERE tcat_id = ? ORDER BY mcat_name ASC");
                    $stM->execute([$ids['tcat_id']]);
                    $midOptions = $stM->fetchAll(PDO::FETCH_ASSOC);
                }
                if ($ids['mcat_id'] > 0) {
                    $stE = $pdo->prepare("SELECT ecat_id, ecat_name FROM tbl_end_category WHERE mcat_id = ? ORDER BY ecat_name ASC");
                    $stE->execute([$ids['mcat_id']]);
                    $endOptions = $stE->fetchAll(PDO::FETCH_ASSOC);
                }
            }

            echo json_encode([
                'success'        => true,
                'classification' => $res,
                'category_ids'   => $ids,
                'mid_options'    => $midOptions,
                'end_options'    => $endOptions,
            ]);
            break;

        case 'approve_items':
            $mode = $_POST['mode'] ?? 'selected';
            $pIds = [];
            if ($mode === 'all_high') {
                $st = $pdo->query("SELECT p_id FROM tbl_category_review_queue WHERE confidence >= 75 AND status != 'Approved'");
                $pIds = $st->fetchAll(PDO::FETCH_COLUMN);
            } else {
                $rawIds = $_POST['p_ids'] ?? [];
                if (is_string($rawIds)) {
                    $rawIds = array_filter(explode(',', $rawIds));
                }
                foreach ((array)$rawIds as $id) {
                    if ((int)$id > 0) {
                        $pIds[] = (int)$id;
                    }
                }
            }

            if (empty($pIds)) {
                echo json_encode(['success' => false, 'error' => 'No products selected for approval.']);
                break;
            }

            $approvedCount = 0;
            $qStmt = $pdo->prepare("SELECT * FROM tbl_category_review_queue WHERE p_id = ?");
            foreach ($pIds as $pid) {
                $qStmt->execute([$pid]);
                $qRow = $qStmt->fetch(PDO::FETCH_ASSOC);
                if (!$qRow) {
                    continue;
                }
                $ok = $engine->applyProductCategorization(
                    $pid,
                    $qRow['sugg_tcat_name'],
                    $qRow['sugg_mcat_name'],
                    $qRow['sugg_ecat_name'],
                    (int)$qRow['confidence'],
                    $qRow['reason'],
                    $adminName,
                    'AI',
                    0
                );
                if ($ok) {
                    $approvedCount++;
                }
            }

            echo json_encode([
                'success'        => true,
                'approved_count' => $approvedCount,
            ]);
            break;

        case 'reject_items':
        case 'skip_items':
            $newStatus = ($action === 'reject_items') ? 'Rejected' : 'Skipped';
            $rawIds = $_POST['p_ids'] ?? [];
            if (is_string($rawIds)) {
                $rawIds = array_filter(explode(',', $rawIds));
            }
            $updated = 0;
            $st = $pdo->prepare("UPDATE tbl_category_review_queue SET status = ?, reviewed_by = ?, updated_at = CURRENT_TIMESTAMP WHERE p_id = ?");
            foreach ((array)$rawIds as $id) {
                $pid = (int)$id;
                if ($pid > 0) {
                    $st->execute([$newStatus, $adminName, $pid]);
                    $updated++;
                }
            }
            echo json_encode(['success' => true, 'updated_count' => $updated, 'status' => $newStatus]);
            break;

        case 'reclassify_item':
            $pId     = (int)($_POST['p_id'] ?? 0);
            $mainCat = trim($_POST['main_category'] ?? '');
            $midCat  = trim($_POST['mid_category'] ?? '');
            $endCat  = trim($_POST['end_category'] ?? '');
            $reason  = trim($_POST['reason'] ?? 'Manual reclassification by administrator.');

            if ($pId <= 0 || $mainCat === '' || $midCat === '' || $endCat === '') {
                echo json_encode(['success' => false, 'error' => 'Product ID, Main Category, Mid Category, and End Category are required.']);
                break;
            }

            $ok = $engine->applyProductCategorization(
                $pId,
                $mainCat,
                $midCat,
                $endCat,
                100,
                $reason,
                $adminName,
                'Manual',
                0
            );

            echo json_encode(['success' => $ok]);
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Unknown action: ' . htmlspecialchars($action)]);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
