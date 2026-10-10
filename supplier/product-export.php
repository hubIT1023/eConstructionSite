<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_lifetime' => 604800,
        'gc_maxlifetime' => 604800,
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax'
    ]);
}

require_once('inc/config.php');
require_once('inc/functions.php');

// Resolve supplier session with fallbacks
$supplier_id = 0;
if (isset($_SESSION['supplier_user']['supplier_id']) && (int)$_SESSION['supplier_user']['supplier_id'] > 0) {
    $supplier_id = (int)$_SESSION['supplier_user']['supplier_id'];
} elseif (isset($_SESSION['user']['supplier_id']) && (int)$_SESSION['user']['supplier_id'] > 0) {
    $supplier_id = (int)$_SESSION['user']['supplier_id'];
} elseif (isset($_SESSION['user']) || isset($_SESSION['supplier_user'])) {
    $supplier_id = 2; // Fallback default supplier
} else {
    header('location: login.php');
    exit;
}

$filter_ecat_id = isset($_GET['ecat_id']) ? intval($_GET['ecat_id']) : 0;
$format = isset($_GET['format']) ? strtolower(trim($_GET['format'])) : 'excel';

// Build SQL Query
$sql = "SELECT 
            t1.p_id,
            t1.p_name,
            COALESCE(NULLIF(TRIM(t1.p_sku), ''), 'N/A') AS p_sku,
            COALESCE(t4.tcat_name, 'Building Materials') AS top_category,
            COALESCE(t3.mcat_name, 'Building Materials') AS mid_category,
            COALESCE(t2.ecat_name, 'General') AS end_category,
            t1.p_capital_price,
            t1.p_markup,
            t1.p_new_price,
            t1.p_current_price,
            t1.p_qty,
            COALESCE(t1.p_new_qty, 0) AS p_new_qty,
            COALESCE(t1.p_s_level, 10) AS p_s_level,
            t1.p_is_active,
            t1.p_is_featured
        FROM tbl_product t1
        LEFT JOIN tbl_end_category t2 ON t1.ecat_id = t2.ecat_id
        LEFT JOIN tbl_mid_category t3 ON t2.mcat_id = t3.mcat_id
        LEFT JOIN tbl_top_category t4 ON t3.tcat_id = t4.tcat_id
        WHERE t1.supplier_id = ?";

$params = array($supplier_id);
if ($filter_ecat_id > 0) {
    $sql .= " AND t1.ecat_id = ?";
    $params[] = $filter_ecat_id;
}
$sql .= " ORDER BY t2.ecat_name ASC, t1.p_name ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Clean output buffer before sending headers
if (ob_get_level()) {
    ob_end_clean();
}

$timestamp = date('Y-m-d_His');

if ($format === 'csv') {
    // -------------------------------------------------------------
    // CSV EXPORT
    // -------------------------------------------------------------
    $filename = "eConstructionSite_Products_{$timestamp}.csv";
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0, no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');
    fputs($output, "\xEF\xBB\xBF"); // UTF-8 BOM

    $headers = [
        'Product ID', 'Product Name', 'SKU', 'Top Category', 'Mid Category', 'End Category',
        'Capital Cost (PHP)', 'Markup (PHP)', 'New Price (PHP)', 'Current Price (PHP)',
        'Quantity In-Stock (Q)', 'Incoming Qty (NQ)', 'Stock Alert Level (S)', 'Stock Alert Status',
        'Featured?', 'Active Status'
    ];
    fputcsv($output, $headers);

    foreach ($products as $row) {
        $qty = intval(preg_replace('/[^0-9]/', '', strval($row['p_qty'])));
        $s_level = intval(preg_replace('/[^0-9]/', '', strval($row['p_s_level'])));
        $n_qty = intval(preg_replace('/[^0-9]/', '', strval($row['p_new_qty'])));
        
        $markup = floatval(preg_replace('/[^0-9.]/', '', strval($row['p_markup'] ?? '0')));
        if (!empty($row['p_capital_price']) && floatval(preg_replace('/[^0-9.]/', '', strval($row['p_capital_price']))) > 0) {
            $capital = floatval(preg_replace('/[^0-9.]/', '', strval($row['p_capital_price'])));
        } else {
            $eff_price = !empty($row['p_new_price']) ? floatval(preg_replace('/[^0-9.]/', '', strval($row['p_new_price']))) : floatval(preg_replace('/[^0-9.]/', '', strval($row['p_current_price'])));
            $capital = max(0, $eff_price - $markup);
        }
        
        $curr_price = floatval(preg_replace('/[^0-9.]/', '', strval($row['p_current_price'])));
        $new_price = !empty($row['p_new_price']) ? floatval(preg_replace('/[^0-9.]/', '', strval($row['p_new_price']))) : $curr_price;

        if ($qty < (0.50 * $s_level)) {
            $alert_status = 'RED (<50% S)';
        } elseif ($qty < (0.80 * $s_level)) {
            $alert_status = 'ORANGE (<80% S)';
        } else {
            $alert_status = 'NORMAL';
        }

        fputcsv($output, [
            $row['p_id'],
            $row['p_name'],
            $row['p_sku'],
            $row['top_category'],
            $row['mid_category'],
            $row['end_category'],
            number_format($capital, 2, '.', ''),
            number_format($markup, 2, '.', ''),
            number_format($new_price, 2, '.', ''),
            number_format($curr_price, 2, '.', ''),
            $qty,
            $n_qty,
            $s_level,
            $alert_status,
            ($row['p_is_featured'] == 1) ? 'Yes' : 'No',
            ($row['p_is_active'] == 1) ? 'Active' : 'Inactive'
        ]);
    }
    fclose($output);
    exit;

} else {
    // -------------------------------------------------------------
    // EXCEL (.XLS / SPREADSHEET) EXPORT
    // -------------------------------------------------------------
    $filename = "eConstructionSite_Products_{$timestamp}.xls";
    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0, no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');

    echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">' . "\n";
    echo '<head>' . "\n";
    echo '<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">' . "\n";
    echo '<!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet><x:Name>Products Catalog</x:Name><x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions></x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]-->' . "\n";
    echo '<style>' . "\n";
    echo 'body { font-family: Calibri, Arial, sans-serif; font-size: 11pt; }' . "\n";
    echo 'table { border-collapse: collapse; width: 100%; }' . "\n";
    echo 'th { background-color: #0284c7; color: #ffffff; font-weight: bold; border: 1px solid #0369a1; padding: 8px 10px; text-align: center; }' . "\n";
    echo 'td { border: 1px solid #cbd5e1; padding: 6px 10px; vertical-align: middle; }' . "\n";
    echo '.num { mso-number-format:"\#\,\#\#0\.00"; text-align: right; }' . "\n";
    echo '.int { mso-number-format:"\#\,\#\#0"; text-align: right; }' . "\n";
    echo '.center { text-align: center; }' . "\n";
    echo '.zebra { background-color: #f8fafc; }' . "\n";
    echo '.alert-red { background-color: #fee2e2; color: #dc2626; font-weight: bold; text-align: center; }' . "\n";
    echo '.alert-orange { background-color: #ffedd5; color: #ea580c; font-weight: bold; text-align: center; }' . "\n";
    echo '.alert-green { background-color: #dcfce7; color: #16a34a; font-weight: bold; text-align: center; }' . "\n";
    echo '</style>' . "\n";
    echo '</head>' . "\n";
    echo '<body>' . "\n";
    
    echo '<table>' . "\n";
    echo '<thead>' . "\n";
    echo '<tr>' . "\n";
    echo '<th style="width: 70px;">Product ID</th>' . "\n";
    echo '<th style="width: 250px;">Product Name</th>' . "\n";
    echo '<th style="width: 120px;">SKU</th>' . "\n";
    echo '<th style="width: 160px;">Top Category</th>' . "\n";
    echo '<th style="width: 160px;">Mid Category</th>' . "\n";
    echo '<th style="width: 160px;">End Category</th>' . "\n";
    echo '<th style="width: 120px;">Capital Cost (PHP)</th>' . "\n";
    echo '<th style="width: 110px;">Markup (PHP)</th>' . "\n";
    echo '<th style="width: 120px;">New Price (PHP)</th>' . "\n";
    echo '<th style="width: 120px;">Current Price (PHP)</th>' . "\n";
    echo '<th style="width: 110px;">Stock (Q)</th>' . "\n";
    echo '<th style="width: 110px;">Incoming (NQ)</th>' . "\n";
    echo '<th style="width: 100px;">Alert Level (S)</th>' . "\n";
    echo '<th style="width: 120px;">Stock Alert Status</th>' . "\n";
    echo '<th style="width: 80px;">Featured?</th>' . "\n";
    echo '<th style="width: 90px;">Active Status</th>' . "\n";
    echo '</tr>' . "\n";
    echo '</thead>' . "\n";
    echo '<tbody>' . "\n";

    $i = 0;
    foreach ($products as $row) {
        $i++;
        $zebra = ($i % 2 === 0) ? ' class="zebra"' : '';
        $qty = intval(preg_replace('/[^0-9]/', '', strval($row['p_qty'])));
        $s_level = intval(preg_replace('/[^0-9]/', '', strval($row['p_s_level'])));
        $n_qty = intval(preg_replace('/[^0-9]/', '', strval($row['p_new_qty'])));
        
        $markup = floatval(preg_replace('/[^0-9.]/', '', strval($row['p_markup'] ?? '0')));
        if (!empty($row['p_capital_price']) && floatval(preg_replace('/[^0-9.]/', '', strval($row['p_capital_price']))) > 0) {
            $capital = floatval(preg_replace('/[^0-9.]/', '', strval($row['p_capital_price'])));
        } else {
            $eff_price = !empty($row['p_new_price']) ? floatval(preg_replace('/[^0-9.]/', '', strval($row['p_new_price']))) : floatval(preg_replace('/[^0-9.]/', '', strval($row['p_current_price'])));
            $capital = max(0, $eff_price - $markup);
        }
        
        $curr_price = floatval(preg_replace('/[^0-9.]/', '', strval($row['p_current_price'])));
        $new_price = !empty($row['p_new_price']) ? floatval(preg_replace('/[^0-9.]/', '', strval($row['p_new_price']))) : $curr_price;

        if ($qty < (0.50 * $s_level)) {
            $alert_class = 'alert-red';
            $alert_status = 'RED (<50% S)';
        } elseif ($qty < (0.80 * $s_level)) {
            $alert_class = 'alert-orange';
            $alert_status = 'ORANGE (<80% S)';
        } else {
            $alert_class = 'alert-green';
            $alert_status = 'NORMAL';
        }

        echo '<tr' . $zebra . '>' . "\n";
        echo '<td class="center">' . htmlspecialchars($row['p_id']) . '</td>' . "\n";
        echo '<td><b>' . htmlspecialchars($row['p_name']) . '</b></td>' . "\n";
        echo '<td class="center">' . htmlspecialchars($row['p_sku']) . '</td>' . "\n";
        echo '<td>' . htmlspecialchars($row['top_category']) . '</td>' . "\n";
        echo '<td>' . htmlspecialchars($row['mid_category']) . '</td>' . "\n";
        echo '<td>' . htmlspecialchars($row['end_category']) . '</td>' . "\n";
        echo '<td class="num">&#8369;' . number_format($capital, 2) . '</td>' . "\n";
        echo '<td class="num">&#8369;' . number_format($markup, 2) . '</td>' . "\n";
        echo '<td class="num">&#8369;' . number_format($new_price, 2) . '</td>' . "\n";
        echo '<td class="num" style="font-weight:bold; color:#0284c7;">&#8369;' . number_format($curr_price, 2) . '</td>' . "\n";
        echo '<td class="int">' . $qty . '</td>' . "\n";
        echo '<td class="int">' . $n_qty . '</td>' . "\n";
        echo '<td class="int">' . $s_level . '</td>' . "\n";
        echo '<td class="' . $alert_class . '">' . $alert_status . '</td>' . "\n";
        echo '<td class="center">' . (($row['p_is_featured'] == 1) ? '<span style="color:green;font-weight:bold;">Yes</span>' : 'No') . '</td>' . "\n";
        echo '<td class="center">' . (($row['p_is_active'] == 1) ? '<span style="color:green;font-weight:bold;">Active</span>' : '<span style="color:red;font-weight:bold;">Inactive</span>') . '</td>' . "\n";
        echo '</tr>' . "\n";
    }

    echo '</tbody>' . "\n";
    echo '</table>' . "\n";
    echo '</body>' . "\n";
    echo '</html>' . "\n";
    exit;
}
