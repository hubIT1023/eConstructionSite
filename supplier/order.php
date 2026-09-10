<?php require_once('header.php'); ?>

<?php
$error_message = '';
$success_message = '';

// -------------------------------------------------------------
// 1. Handle Customer Direct Message Form Submission
// -------------------------------------------------------------
if(isset($_POST['form1'])) {
    $valid = 1;
    if(empty($_POST['subject_text'])) {
        $valid = 0;
        $error_message .= 'Subject cannot be empty.<br>';
    }
    if(empty($_POST['message_text'])) {
        $valid = 0;
        $error_message .= 'Message cannot be empty.<br>';
    }
    if($valid == 1) {
        $subject_text = strip_tags($_POST['subject_text']);
        $message_text = strip_tags($_POST['message_text']);
        $post_cust_id = (int)($_POST['cust_id'] ?? 0);
        $post_payment_id = strip_tags($_POST['payment_id'] ?? '');

        // Getting Customer Email Address
        $cust_email = '';
        if ($post_cust_id > 0) {
            $statement = $pdo->prepare("SELECT cust_email FROM tbl_customer WHERE cust_id=?");
            $statement->execute(array($post_cust_id));
            $cust_row = $statement->fetch(PDO::FETCH_ASSOC);
            if ($cust_row) {
                $cust_email = $cust_row['cust_email'];
            }
        }

        // Getting Admin / Contact Email Address
        $statement = $pdo->prepare("SELECT contact_email FROM tbl_settings WHERE id=1");
        $statement->execute();
        $settings_row = $statement->fetch(PDO::FETCH_ASSOC);
        $admin_email = $settings_row['contact_email'] ?? 'admin@econstruction-supply.site';

        $order_detail = '';
        $statement = $pdo->prepare("SELECT * FROM tbl_payment WHERE payment_id=? AND supplier_id=?");
        $statement->execute(array($post_payment_id, $supplier_id));
        $pay_row = $statement->fetch(PDO::FETCH_ASSOC);
        
        if ($pay_row) {
            if (empty($cust_email)) {
                $cust_email = $pay_row['customer_email'];
            }

            $payment_details = '';
            if($pay_row['payment_method'] == 'PayPal'):
                $payment_details = 'Transaction Id: ' . ($pay_row['txnid'] ?? '') . '<br>';
            elseif($pay_row['payment_method'] == 'Stripe'):
                $payment_details = 'Transaction Id: ' . ($pay_row['txnid'] ?? '') . '<br>';
            elseif($pay_row['payment_method'] == 'Bank Deposit'):
                $payment_details = 'Transaction Details: <br>' . nl2br(htmlspecialchars($pay_row['bank_transaction_info'] ?? ''));
            else:
                $payment_details = 'Transaction Details: ' . htmlspecialchars($pay_row['bank_transaction_info'] ?? 'Purchase Order / Over the Counter');
            endif;

            $order_detail .= '
Customer Name: ' . htmlspecialchars($pay_row['customer_name'] ?? '') . '<br>
Customer Email: ' . htmlspecialchars($pay_row['customer_email'] ?? '') . '<br>
Payment Method: ' . htmlspecialchars($pay_row['payment_method'] ?? '') . '<br>
Payment Date: ' . htmlspecialchars($pay_row['payment_date'] ?? '') . '<br>
Payment Details: <br>' . $payment_details . '<br>
Paid Amount: ₱' . number_format(floatval($pay_row['paid_amount'] ?? 0), 2) . '<br>
Payment Status: ' . htmlspecialchars($pay_row['payment_status'] ?? '') . '<br>
Shipping Status: ' . htmlspecialchars($pay_row['shipping_status'] ?? '') . '<br>
Payment Id / PO Ref: ' . htmlspecialchars($pay_row['payment_id'] ?? '') . '<br>';
        }

        $i = 0;
        $statement_items = $pdo->prepare("SELECT * FROM tbl_order WHERE payment_id=? AND supplier_id=?");
        $statement_items->execute(array($post_payment_id, $supplier_id));
        $order_items = $statement_items->fetchAll(PDO::FETCH_ASSOC);
        foreach ($order_items as $item_row) {
            $i++;
            $order_detail .= '
<br><b><u>Product Item ' . $i . '</u></b><br>
Product Name: ' . htmlspecialchars($item_row['product_name'] ?? '') . '<br>
Size: ' . htmlspecialchars($item_row['size'] ?? '') . '<br>
Color: ' . htmlspecialchars($item_row['color'] ?? '') . '<br>
Quantity: ' . htmlspecialchars($item_row['quantity'] ?? '') . '<br>
Unit Price: ₱' . number_format(floatval($item_row['unit_price'] ?? 0), 2) . '<br>';
        }

        $statement = $pdo->prepare("INSERT INTO tbl_customer_message (subject,message,order_detail,cust_id) VALUES (?,?,?,?)");
        $statement->execute(array($subject_text, $message_text, $order_detail, $post_cust_id));

        // Sending Email to Customer
        if (!empty($cust_email)) {
            $to_customer = $cust_email;
            $email_body = '
<html><body>
<h3>Message: </h3>
<p>' . nl2br(htmlspecialchars($message_text)) . '</p>
<hr>
<h3>Order Details: </h3>
<p>' . $order_detail . '</p>
</body></html>';

            $headers = 'From: ' . $admin_email . "\r\n" .
                       'Reply-To: ' . $admin_email . "\r\n" .
                       'X-Mailer: PHP/' . phpversion() . "\r\n" . 
                       "MIME-Version: 1.0\r\n" . 
                       "Content-Type: text/html; charset=UTF-8\r\n";

            @mail($to_customer, $subject_text, $email_body, $headers);
        }
        
        $success_message = 'Your message to the customer has been sent successfully.';
    }
}

// -------------------------------------------------------------
// 2. Fetch Supplier Store Profile for Printable PO Vouchers
// -------------------------------------------------------------
$statement_s = $pdo->prepare("SELECT * FROM tbl_supplier WHERE supplier_id=?");
$statement_s->execute(array($supplier_id));
$current_supplier_data = $statement_s->fetch(PDO::FETCH_ASSOC);
$store_name = $current_supplier_data['supplier_name'] ?? 'E-Construction Supply Store';
$store_address = $current_supplier_data['supplier_address'] ?? '';
$store_phone = $current_supplier_data['supplier_phone'] ?? '';
$store_email = $current_supplier_data['supplier_email'] ?? '';

// Helper for customer avatar initials
if (!function_exists('get_order_customer_initials')) {
    function get_order_customer_initials($name) {
        $parts = explode(' ', trim($name ?? ''));
        $initials = '';
        foreach ($parts as $p) {
            if (!empty($p)) {
                $initials .= strtoupper(substr($p, 0, 1));
                if (strlen($initials) >= 2) break;
            }
        }
        return $initials ?: 'C';
    }
}

// Helper to extract clean delivery / notes info
if (!function_exists('parse_order_bank_info')) {
    function parse_order_bank_info($raw_info) {
        $info = trim($raw_info ?? '');
        $result = [
            'notes' => '',
            'delivery_text' => '',
            'is_delivery' => false,
            'delivery_cost' => 0.00,
            'summary_lines' => []
        ];
        if (empty($info)) return $result;

        // Split by pipes or linebreaks
        $lines = preg_split('/[|\r\n]+/', $info);
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            $result['summary_lines'][] = $line;

            if (stripos($line, 'Notes:') !== false) {
                $result['notes'] = trim(str_ireplace('Notes:', '', $line));
            } elseif (stripos($line, 'Remarks:') !== false) {
                $result['notes'] = trim(str_ireplace('Remarks:', '', $line));
            } elseif (stripos($line, 'Delivery') !== false || stripos($line, 'Fulfillment') !== false || stripos($line, 'Purchase') !== false || stripos($line, 'Counter') !== false) {
                $result['delivery_text'] = $line;
            }
        }

        if (empty($result['delivery_text']) && !empty($info)) {
            $result['delivery_text'] = $info;
        }

        if (empty($result['notes']) && !empty($info)) {
            $result['notes'] = $info;
        }

        return $result;
    }
}

// -------------------------------------------------------------
// 3. Filter Parameters: Period (Day, Week, Month, All) - STRICTLY Awaiting for Payment
// -------------------------------------------------------------
$period_filter = isset($_GET['period']) ? trim($_GET['period']) : 'all';

$current_year = (int)date('Y');
$current_month = (int)date('m');
$current_week = (int)date('W');
$today_date = date('Y-m-d');

$selected_day = isset($_GET['day_date']) && !empty($_GET['day_date']) ? $_GET['day_date'] : $today_date;
$selected_year = isset($_GET['year']) && !empty($_GET['year']) ? (int)$_GET['year'] : $current_year;
$selected_month = isset($_GET['month']) && !empty($_GET['month']) ? (int)$_GET['month'] : $current_month;
$selected_week = isset($_GET['week']) && !empty($_GET['week']) ? (int)$_GET['week'] : $current_week;

// Build Query: ONLY display those payment status is 'Awaiting for Payment' (Paid / Completed excluded)
$where_clauses = [
    "supplier_id = ?",
    "payment_status = 'Awaiting for Payment'",
    "payment_status != 'Paid'",
    "payment_status != 'Completed'",
    "(shipping_status != 'Completed' OR shipping_status IS NULL)"
];
$query_params = [$supplier_id];

$period_label = "All Time";
$filter_description = "Displaying all receive orders awaiting payment";

if ($period_filter === 'day') {
    $start_dt = $selected_day . ' 00:00:00';
    $end_dt = $selected_day . ' 23:59:59';
    $where_clauses[] = "(payment_date >= ? AND payment_date <= ?)";
    $query_params[] = $start_dt;
    $query_params[] = $end_dt;
    $period_label = "Day: " . date('F d, Y', strtotime($selected_day));
    $filter_description = "Receive orders awaiting payment on " . date('l, F j, Y', strtotime($selected_day));
} elseif ($period_filter === 'week') {
    $dto = new DateTime();
    $dto->setISODate($selected_year, $selected_week);
    $week_start_date = $dto->format('Y-m-d');
    $dto->modify('+6 days');
    $week_end_date = $dto->format('Y-m-d');
    
    $where_clauses[] = "(payment_date >= ? AND payment_date <= ?)";
    $query_params[] = $week_start_date . ' 00:00:00';
    $query_params[] = $week_end_date . ' 23:59:59';
    $period_label = "Week $selected_week, $selected_year (" . date('M d', strtotime($week_start_date)) . " - " . date('M d, Y', strtotime($week_end_date)) . ")";
    $filter_description = "Receive orders awaiting payment during Week $selected_week (" . date('M d', strtotime($week_start_date)) . " - " . date('M d, Y', strtotime($week_end_date)) . ")";
} elseif ($period_filter === 'month') {
    $m_str = str_pad($selected_month, 2, '0', STR_PAD_LEFT);
    $days_in_m = date('t', strtotime("$selected_year-$m_str-01"));
    $month_start_dt = "$selected_year-$m_str-01 00:00:00";
    $month_end_dt = "$selected_year-$m_str-$days_in_m 23:59:59";

    $where_clauses[] = "(payment_date >= ? AND payment_date <= ?)";
    $query_params[] = $month_start_dt;
    $query_params[] = $month_end_dt;
    $period_label = "Month: " . date('F Y', strtotime("$selected_year-$m_str-01"));
    $filter_description = "Receive orders awaiting payment during " . date('F Y', strtotime("$selected_year-$m_str-01"));
}

$where_sql = implode(" AND ", $where_clauses);
// ALWAYS ORDER BY id DESC (descending order of ID)
$sql_query = "SELECT * FROM tbl_payment WHERE $where_sql ORDER BY id DESC";

$stmt_orders = $pdo->prepare($sql_query);
$stmt_orders->execute($query_params);
$orders_list = $stmt_orders->fetchAll(PDO::FETCH_ASSOC);

// -------------------------------------------------------------
// 4. Compute Metrics for Awaiting Payment Orders
// -------------------------------------------------------------
$total_awaiting_count = count($orders_list);
$total_awaiting_amount = 0.00;
$count_otc_orders = 0;
$count_special_orders = 0;

foreach ($orders_list as $o_item) {
    $p_amount = floatval($o_item['paid_amount'] ?? 0);
    $p_method = $o_item['payment_method'] ?? '';
    
    $total_awaiting_amount += $p_amount;

    if ($p_method === 'Over the Counter' || $p_method === 'Purchase Order' || $p_method === 'Purchase Order (PO)' || $p_method === 'Cash (OTC)') {
        $count_otc_orders++;
    }
}

// Count special orders among current awaiting orders
if (!empty($orders_list)) {
    $po_list = array_column($orders_list, 'payment_id');
    $in_placeholders = implode(',', array_fill(0, count($po_list), '?'));
    $stmt_so_cnt = $pdo->prepare("SELECT COUNT(DISTINCT payment_id) as total_so FROM tbl_order WHERE supplier_id = ? AND item_type = 'SPECIAL_ORDER' AND payment_id IN ($in_placeholders)");
    $so_params = array_merge([$supplier_id], $po_list);
    $stmt_so_cnt->execute($so_params);
    $so_res = $stmt_so_cnt->fetch(PDO::FETCH_ASSOC);
    $count_special_orders = $so_res ? (int)$so_res['total_so'] : 0;
}

// Month name helper list
$month_names = [
    1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
    5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
    9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
];
?>

<style>
/* Modern Elegant Theme for Receive Purchase Orders */
.orders-management-wrapper {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    color: #1e293b;
    padding-bottom: 40px;
}

/* Header Banner */
.orders-page-header {
    background: #ffffff;
    border-bottom: 1px solid #e2e8f0;
    padding: 24px 30px 20px 30px;
    margin: -15px -15px 24px -15px;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
}
.header-content-flex {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 16px;
}
.orders-page-title {
    margin: 0;
    font-size: 23px;
    font-weight: 800;
    color: #0f172a;
    letter-spacing: -0.3px;
    display: flex;
    align-items: center;
    gap: 10px;
}
.orders-page-title i {
    color: #0284c7;
    font-size: 22px;
}
.orders-page-subtitle {
    margin: 4px 0 0 0;
    font-size: 13.5px;
    color: #64748b;
    font-weight: 500;
}
.header-actions-group {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}
.btn-header-link {
    background: #f8fafc;
    color: #0284c7 !important;
    border: 1px solid #cbd5e1;
    font-weight: 700;
    font-size: 13px;
    padding: 8px 16px;
    border-radius: 8px;
    transition: all 0.2s ease-in-out;
    display: inline-flex;
    align-items: center;
    gap: 7px;
    text-decoration: none !important;
}
.btn-header-link:hover {
    background: #f0f9ff;
    border-color: #0284c7;
    color: #0369a1 !important;
    transform: translateY(-1px);
    box-shadow: 0 4px 10px rgba(2, 132, 199, 0.12);
}
.btn-header-primary {
    background: #0284c7;
    color: #ffffff !important;
    border: 1px solid #0284c7;
}
.btn-header-primary:hover {
    background: #0369a1;
    color: #ffffff !important;
}

/* Filter Control Panel */
.orders-filter-panel {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 18px 22px;
    margin-bottom: 22px;
    box-shadow: 0 2px 6px rgba(15, 23, 42, 0.03);
}
.filter-period-tabs {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    margin-bottom: 14px;
    border-bottom: 1px solid #f1f5f9;
    padding-bottom: 12px;
}
.period-tab-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 7px 16px;
    font-size: 13px;
    font-weight: 700;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
    background: #ffffff;
    color: #475569;
    text-decoration: none !important;
    transition: all 0.15s ease;
    cursor: pointer;
}
.period-tab-btn:hover {
    background: #f8fafc;
    border-color: #cbd5e1;
    color: #0f172a;
}
.period-tab-btn.active {
    background: #0284c7;
    border-color: #0284c7;
    color: #ffffff;
    box-shadow: 0 2px 8px rgba(2, 132, 199, 0.25);
}

.filter-controls-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 14px;
}
.filter-inline-form {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    margin: 0;
}
.filter-input-label {
    font-size: 12.5px;
    font-weight: 700;
    color: #334155;
    margin: 0;
}
.filter-select-input, .filter-date-input {
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    padding: 6px 12px;
    font-size: 13px;
    color: #1e293b;
    background: #ffffff;
    outline: none;
    transition: border-color 0.15s;
}
.filter-select-input:focus, .filter-date-input:focus {
    border-color: #0284c7;
    box-shadow: 0 0 0 3px rgba(2, 132, 199, 0.15);
}
.btn-filter-apply {
    background: #0284c7;
    color: #ffffff;
    border: none;
    font-size: 12.5px;
    font-weight: 700;
    padding: 7px 14px;
    border-radius: 6px;
    transition: background 0.15s;
    cursor: pointer;
}
.btn-filter-apply:hover {
    background: #0369a1;
}

.active-filter-badge {
    background: #fffbeb;
    color: #b45309;
    border: 1px solid #fde68a;
    font-size: 12px;
    font-weight: 700;
    padding: 4px 10px;
    border-radius: 6px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

/* Metric KPI Cards */
.metric-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 2px 8px rgba(15, 23, 42, 0.04);
    transition: all 0.2s ease;
    height: 100%;
    margin-bottom: 22px;
}
.metric-card:hover {
    border-color: #cbd5e1;
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(15, 23, 42, 0.06);
}
.metric-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 10px;
}
.metric-title {
    font-size: 12.5px;
    font-weight: 700;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 4px;
}
.metric-value {
    font-size: 24px;
    font-weight: 800;
    color: #0f172a;
    line-height: 1.2;
}
.metric-icon-wrap {
    width: 44px;
    height: 44px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
}
.metric-icon-amber  { background: #fef3c7; color: #d97706; }
.metric-icon-blue   { background: #e0f2fe; color: #0284c7; }
.metric-icon-indigo { background: #ede9fe; color: #6366f1; }
.metric-icon-rose   { background: #ffe4e6; color: #e11d48; }
.metric-sub {
    font-size: 12px;
    color: #64748b;
    margin-top: 6px;
}

/* Main Table Container */
.orders-main-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    box-shadow: 0 4px 14px rgba(15, 23, 42, 0.04);
    overflow: hidden;
    margin-bottom: 30px;
}

/* Toolbar & Live Search */
.orders-toolbar {
    padding: 16px 22px;
    background: #ffffff;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 14px;
}
.table-heading-meta {
    font-size: 14px;
    font-weight: 700;
    color: #0f172a;
    display: flex;
    align-items: center;
    gap: 8px;
}
.badge-count {
    background: #0284c7;
    color: #ffffff;
    font-size: 11.5px;
    font-weight: 800;
    padding: 2px 8px;
    border-radius: 12px;
}

.search-input-wrap {
    position: relative;
    min-width: 320px;
    flex-grow: 1;
    max-width: 440px;
}
.search-input-wrap i {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: #94a3b8;
    font-size: 14px;
}
.orders-search-input {
    width: 100%;
    padding: 8px 12px 8px 36px;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    font-size: 13px;
    background: #f8fafc;
    transition: all 0.2s;
    color: #0f172a;
}
.orders-search-input:focus {
    background: #ffffff;
    border-color: #0284c7;
    box-shadow: 0 0 0 3px rgba(2, 132, 199, 0.15);
    outline: none;
}

/* Orders Table Styling */
.orders-table {
    width: 100%;
    border-collapse: collapse;
    margin: 0;
}
.orders-table thead th {
    background: #f8fafc;
    color: #475569;
    font-weight: 700;
    font-size: 11.5px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 12px 14px;
    border-bottom: 1px solid #e2e8f0;
    vertical-align: middle;
}
.orders-table tbody td {
    padding: 14px 14px;
    border-bottom: 1px solid #f1f5f9;
    font-size: 13px;
    color: #334155;
    vertical-align: top;
}
.orders-table tbody tr:hover {
    background-color: #f8fafc;
}

/* PO Number Cell */
.po-number-cell {
    white-space: nowrap;
}
.po-badge-wrap {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: #f0f9ff;
    border: 1px solid #bae6fd;
    padding: 4px 9px;
    border-radius: 6px;
    margin-bottom: 4px;
}
.po-badge-wrap i {
    color: #0284c7;
    font-size: 12px;
}
.po-primary-code {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, monospace;
    font-size: 12.5px;
    font-weight: 800;
    color: #0369a1;
    letter-spacing: 0.3px;
}

/* Customer Cell */
.cust-cell-wrapper {
    display: flex;
    align-items: flex-start;
    gap: 10px;
}
.cust-avatar {
    width: 34px;
    height: 34px;
    border-radius: 50%;
    background: #e2e8f0;
    color: #334155;
    font-weight: 800;
    font-size: 12.5px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.cust-info-block {
    line-height: 1.35;
}
.cust-name {
    font-weight: 700;
    color: #0f172a;
    font-size: 13px;
}
.cust-company {
    font-size: 11.5px;
    color: #0284c7;
    font-weight: 600;
    margin-top: 1px;
}
.cust-email-link {
    font-size: 11px;
    color: #64748b;
    display: block;
    text-decoration: none;
    word-break: break-all;
}
.cust-email-link:hover {
    color: #0284c7;
    text-decoration: underline;
}
.cust-meta-text {
    font-size: 11px;
    color: #64748b;
}
.btn-msg-customer {
    margin-top: 5px;
    background: #f8fafc;
    border: 1px solid #cbd5e1;
    color: #0284c7;
    font-size: 11px;
    font-weight: 700;
    padding: 2px 7px;
    border-radius: 4px;
    cursor: pointer;
    transition: all 0.15s;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.btn-msg-customer:hover {
    background: #f0f9ff;
    border-color: #0284c7;
    color: #0369a1;
}

/* Items & Details */
.order-items-list {
    display: flex;
    flex-direction: column;
    gap: 4px;
}
.order-item-line {
    font-size: 12.5px;
    color: #1e293b;
    line-height: 1.4;
}
.order-item-line strong {
    color: #0f172a;
}
.item-qty-tag {
    background: #f1f5f9;
    color: #334155;
    font-weight: 700;
    padding: 1px 6px;
    border-radius: 4px;
    font-size: 11px;
    margin-left: 4px;
}
.badge-special-tag {
    display: inline-block;
    background: #fef3c7;
    color: #92400e;
    border: 1px solid #fde68a;
    font-size: 10px;
    font-weight: 800;
    padding: 1px 6px;
    border-radius: 4px;
    letter-spacing: 0.3px;
    margin-right: 4px;
}

/* Badges & Action Buttons */
.badge-method {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: 11.5px;
    font-weight: 700;
    padding: 3px 8px;
    border-radius: 6px;
    margin-top: 4px;
}
.badge-method-otc { background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; }
.badge-method-bank{ background: #ede9fe; color: #5b21b6; border: 1px solid #ddd6fe; }
.badge-method-paypal { background: #dbeafe; color: #1e40af; border: 1px solid #bfdbfe; }
.badge-method-stripe { background: #fae8ff; color: #86198f; border: 1px solid #f5d0fe; }

.status-pill {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: 11px;
    font-weight: 700;
    padding: 3px 9px;
    border-radius: 20px;
    text-transform: capitalize;
}
.status-awaiting { background: #fffbeb; color: #b45309; border: 1px solid #fde68a; }
.status-pending  { background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; }

.btn-status-action {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
    font-size: 11.5px;
    font-weight: 700;
    padding: 5px 11px;
    border-radius: 6px;
    text-decoration: none !important;
    transition: all 0.2s ease;
    margin-top: 6px;
    width: 100%;
    max-width: 130px;
}
.btn-action-paid {
    background: #10b981;
    color: #ffffff !important;
    border: none;
    box-shadow: 0 2px 5px rgba(16, 185, 129, 0.2);
}
.btn-action-paid:hover {
    background: #059669;
    box-shadow: 0 4px 8px rgba(16, 185, 129, 0.3);
}

.btn-po-voucher {
    background: #0284c7;
    color: #ffffff !important;
    border: none;
    font-size: 11.5px;
    font-weight: 700;
    padding: 6px 12px;
    border-radius: 6px;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    transition: all 0.2s;
    box-shadow: 0 2px 5px rgba(2, 132, 199, 0.2);
    text-decoration: none !important;
    cursor: pointer;
    white-space: nowrap;
}
.btn-po-voucher:hover {
    background: #0369a1;
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(2, 132, 199, 0.3);
}

.btn-delete-order {
    background: #ffffff;
    color: #ef4444;
    border: 1px solid #fca5a5;
    font-size: 12px;
    padding: 6px 10px;
    border-radius: 6px;
    transition: all 0.15s;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}
.btn-delete-order:hover {
    background: #fef2f2;
    border-color: #ef4444;
    color: #b91c1c;
}

/* Modals Modernization */
.modal-header-modern {
    background: #0f172a;
    color: #ffffff;
    padding: 16px 22px;
    border-top-left-radius: 8px;
    border-top-right-radius: 8px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.modal-header-modern h4 {
    margin: 0;
    font-size: 16px;
    font-weight: 700;
    color: #ffffff;
}
.modal-header-modern .close {
    color: #ffffff;
    opacity: 0.8;
    text-shadow: none;
    font-size: 22px;
}
.modal-header-modern .close:hover {
    opacity: 1;
}

/* Printable PO Voucher Design */
.po-voucher-sheet {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    color: #1e293b;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 24px;
}
.po-header-bar {
    border-bottom: 2px solid #0284c7;
    padding-bottom: 16px;
    margin-bottom: 20px;
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
}
.po-title-brand {
    font-size: 20px;
    font-weight: 800;
    color: #0284c7;
    margin: 0;
    letter-spacing: 0.5px;
}
.po-subtitle-brand {
    font-size: 12.5px;
    color: #64748b;
    margin: 2px 0 0 0;
    font-weight: 600;
}
.po-info-box {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    padding: 12px;
    height: 100%;
}
.po-info-box h5 {
    font-size: 11px;
    font-weight: 800;
    text-transform: uppercase;
    color: #0f172a;
    border-bottom: 1px solid #e2e8f0;
    padding-bottom: 5px;
    margin: 0 0 8px 0;
    letter-spacing: 0.4px;
}
.po-items-table {
    width: 100%;
    margin: 18px 0;
    border-collapse: collapse;
    font-size: 12.5px;
}
.po-items-table th {
    background: #f1f5f9;
    color: #334155;
    font-weight: 700;
    padding: 8px 10px;
    border: 1px solid #e2e8f0;
    text-align: left;
}
.po-items-table td {
    padding: 8px 10px;
    border: 1px solid #e2e8f0;
    color: #334155;
}
.po-total-row td {
    font-weight: 800;
    font-size: 14px;
    color: #0284c7;
    border-top: 2px solid #0284c7;
    background: #f0f9ff;
}

/* Empty State */
.empty-orders-state {
    text-align: center;
    padding: 50px 20px;
    color: #64748b;
}
.empty-orders-icon {
    font-size: 48px;
    color: #cbd5e1;
    margin-bottom: 14px;
}
.empty-orders-state h4 {
    font-weight: 700;
    color: #0f172a;
    margin-bottom: 6px;
}
</style>

<div class="orders-management-wrapper">
    <!-- Header Banner -->
    <div class="orders-page-header">
        <div class="header-content-flex">
            <div>
                <h1 class="orders-page-title">
                    <i class="fa fa-inbox"></i> Receive Orders
                </h1>
                <p class="orders-page-subtitle">
                    Incoming customer orders awaiting payment and receipt &bull; Filter by Day, Week, and Month &bull; Sorted by ID in descending order
                </p>
            </div>
            <div class="header-actions-group">
                <a href="pos.php" class="btn-header-link">
                    <i class="fa fa-calculator"></i> POS Terminal
                </a>
                <a href="paid-orders.php" class="btn-header-link">
                    <i class="fa fa-check-circle-o"></i> View Paid Orders <i class="fa fa-arrow-right" style="font-size: 11px;"></i>
                </a>
            </div>
        </div>
    </div>

    <section class="content" style="padding: 0 15px;">
        <!-- Alerts Feedback -->
        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger" style="border-radius: 8px; font-weight: 600; margin-bottom: 18px;">
                <i class="fa fa-exclamation-circle"></i> <?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success" style="border-radius: 8px; font-weight: 600; margin-bottom: 18px;">
                <i class="fa fa-check-circle"></i> <?php echo $success_message; ?>
            </div>
        <?php endif; ?>

        <!-- Filter Control Panel -->
        <div class="orders-filter-panel">
            <!-- Period Tabs: All, Day, Week, Month -->
            <div class="filter-period-tabs">
                <a href="order.php?period=all" class="period-tab-btn <?php echo ($period_filter === 'all') ? 'active' : ''; ?>">
                    <i class="fa fa-globe"></i> All Time (<?php echo $total_awaiting_count; ?>)
                </a>
                <a href="order.php?period=day&day_date=<?php echo urlencode($selected_day); ?>" class="period-tab-btn <?php echo ($period_filter === 'day') ? 'active' : ''; ?>">
                    <i class="fa fa-calendar-check-o"></i> Filter by Day
                </a>
                <a href="order.php?period=week&year=<?php echo $selected_year; ?>&week=<?php echo $selected_week; ?>" class="period-tab-btn <?php echo ($period_filter === 'week') ? 'active' : ''; ?>">
                    <i class="fa fa-calendar"></i> Filter by Week
                </a>
                <a href="order.php?period=month&year=<?php echo $selected_year; ?>&month=<?php echo $selected_month; ?>" class="period-tab-btn <?php echo ($period_filter === 'month') ? 'active' : ''; ?>">
                    <i class="fa fa-calendar-o"></i> Filter by Month
                </a>
            </div>

            <!-- Dynamic Period Parameters Form -->
            <div class="filter-controls-row">
                <form action="order.php" method="get" class="filter-inline-form">
                    <input type="hidden" name="period" value="<?php echo htmlspecialchars($period_filter); ?>">

                    <?php if ($period_filter === 'day'): ?>
                        <span class="filter-input-label"><i class="fa fa-calendar"></i> Select Date:</span>
                        <input type="date" name="day_date" class="filter-date-input" value="<?php echo htmlspecialchars($selected_day); ?>" onchange="this.form.submit()">
                        <a href="order.php?period=day&day_date=<?php echo date('Y-m-d'); ?>" class="btn btn-xs btn-default" style="font-weight: 600; border-radius: 4px; padding: 5px 9px;">Today</a>
                        <a href="order.php?period=day&day_date=<?php echo date('Y-m-d', strtotime('-1 day')); ?>" class="btn btn-xs btn-default" style="font-weight: 600; border-radius: 4px; padding: 5px 9px;">Yesterday</a>
                        <button type="submit" class="btn-filter-apply"><i class="fa fa-filter"></i> Apply</button>

                    <?php elseif ($period_filter === 'week'): ?>
                        <span class="filter-input-label"><i class="fa fa-calendar"></i> Week:</span>
                        <select name="week" class="filter-select-input" onchange="this.form.submit()">
                            <?php for ($w = 1; $w <= 52; $w++): 
                                $w_dto = new DateTime();
                                $w_dto->setISODate($selected_year, $w);
                                $w_s = $w_dto->format('M d');
                                $w_dto->modify('+6 days');
                                $w_e = $w_dto->format('M d');
                            ?>
                                <option value="<?php echo $w; ?>" <?php echo ($w === $selected_week) ? 'selected' : ''; ?>>
                                    Week <?php echo $w; ?> (<?php echo $w_s; ?> - <?php echo $w_e; ?>)
                                </option>
                            <?php endfor; ?>
                        </select>

                        <span class="filter-input-label">Year:</span>
                        <select name="year" class="filter-select-input" onchange="this.form.submit()">
                            <?php for ($y = $current_year; $y >= $current_year - 5; $y--): ?>
                                <option value="<?php echo $y; ?>" <?php echo ($y === $selected_year) ? 'selected' : ''; ?>><?php echo $y; ?></option>
                            <?php endfor; ?>
                        </select>
                        <button type="submit" class="btn-filter-apply"><i class="fa fa-filter"></i> Apply</button>

                    <?php elseif ($period_filter === 'month'): ?>
                        <span class="filter-input-label"><i class="fa fa-calendar-o"></i> Month:</span>
                        <select name="month" class="filter-select-input" onchange="this.form.submit()">
                            <?php foreach ($month_names as $m_num => $m_name): ?>
                                <option value="<?php echo $m_num; ?>" <?php echo ($m_num === $selected_month) ? 'selected' : ''; ?>>
                                    <?php echo $m_name; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <span class="filter-input-label">Year:</span>
                        <select name="year" class="filter-select-input" onchange="this.form.submit()">
                            <?php for ($y = $current_year; $y >= $current_year - 5; $y--): ?>
                                <option value="<?php echo $y; ?>" <?php echo ($y === $selected_year) ? 'selected' : ''; ?>><?php echo $y; ?></option>
                            <?php endfor; ?>
                        </select>
                        <button type="submit" class="btn-filter-apply"><i class="fa fa-filter"></i> Apply</button>

                    <?php else: ?>
                        <span style="font-size: 13px; color: #64748b; font-weight: 600;">
                            <i class="fa fa-clock-o"></i> Showing all receive orders awaiting payment
                        </span>
                    <?php endif; ?>
                </form>

                <div class="active-filter-badge">
                    <i class="fa fa-clock-o"></i> <?php echo htmlspecialchars($period_label); ?> &bull; Awaiting Payment
                </div>
            </div>
        </div>

        <!-- KPI Metric Cards -->
        <div class="row">
            <!-- Metric 1: Awaiting Payment POs -->
            <div class="col-md-3 col-sm-6">
                <div class="metric-card">
                    <div class="metric-header">
                        <div>
                            <div class="metric-title">Awaiting Payment</div>
                            <div class="metric-value"><?php echo number_format($total_awaiting_count); ?></div>
                        </div>
                        <div class="metric-icon-wrap metric-icon-amber">
                            <i class="fa fa-clock-o"></i>
                        </div>
                    </div>
                    <div class="metric-sub">
                        Orders awaiting payment &amp; receipt
                    </div>
                </div>
            </div>

            <!-- Metric 2: Pending Receivable Amount -->
            <div class="col-md-3 col-sm-6">
                <div class="metric-card">
                    <div class="metric-header">
                        <div>
                            <div class="metric-title">Pending Value</div>
                            <div class="metric-value">&#8369;<?php echo number_format($total_awaiting_amount, 2); ?></div>
                        </div>
                        <div class="metric-icon-wrap metric-icon-blue">
                            <i class="fa fa-money"></i>
                        </div>
                    </div>
                    <div class="metric-sub">
                        Total receivable amount
                    </div>
                </div>
            </div>

            <!-- Metric 3: Special Orders Staged -->
            <div class="col-md-3 col-sm-6">
                <div class="metric-card">
                    <div class="metric-header">
                        <div>
                            <div class="metric-title">Special Orders</div>
                            <div class="metric-value"><?php echo number_format($count_special_orders); ?></div>
                        </div>
                        <div class="metric-icon-wrap metric-icon-indigo">
                            <i class="fa fa-star"></i>
                        </div>
                    </div>
                    <div class="metric-sub">
                        Custom fabrications &amp; requests
                    </div>
                </div>
            </div>

            <!-- Metric 4: OTC / Purchase Orders -->
            <div class="col-md-3 col-sm-6">
                <div class="metric-card">
                    <div class="metric-header">
                        <div>
                            <div class="metric-title">OTC / Purchase Orders</div>
                            <div class="metric-value"><?php echo number_format($count_otc_orders); ?></div>
                        </div>
                        <div class="metric-icon-wrap metric-icon-rose">
                            <i class="fa fa-file-text-o"></i>
                        </div>
                    </div>
                    <div class="metric-sub">
                        Counter pickup &amp; payment
                    </div>
                </div>
            </div>
        </div>

        <!-- Orders Table Card -->
        <div class="orders-main-card">
            <!-- Toolbar: Table Heading & Live Search -->
            <div class="orders-toolbar">
                <div class="table-heading-meta">
                    <i class="fa fa-inbox"></i> Receive Orders <span class="badge-count"><?php echo $total_awaiting_count; ?></span>
                </div>

                <div class="search-input-wrap">
                    <i class="fa fa-search"></i>
                    <input type="text" id="orderSearchInput" class="orders-search-input" placeholder="Search by PO Number, Customer, Item, ID...">
                </div>
            </div>

            <!-- Orders Table: Ordered by ID DESC -->
            <div class="table-responsive">
                <table class="orders-table" id="allOrdersTable">
                    <thead>
                        <tr>
                            <th style="width: 60px; text-align: center;">ID</th>
                            <th style="width: 140px;">PO Number</th>
                            <th style="width: 230px;">Customer</th>
                            <th style="width: 130px;">PO Date</th>
                            <th style="width: 130px; text-align: right;">Total Amount</th>
                            <th style="width: 155px; text-align: center;">Payment Status</th>
                            <th style="width: 125px; text-align: center;">Order Status</th>
                            <th style="width: 115px; text-align: center;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($orders_list)): ?>
                            <tr>
                                <td colspan="8">
                                    <div class="empty-orders-state">
                                        <div class="empty-orders-icon">
                                            <i class="fa fa-inbox"></i>
                                        </div>
                                        <h4>No Receive Orders Found</h4>
                                        <p><?php echo htmlspecialchars($filter_description); ?> did not return any orders awaiting payment.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php
                            foreach ($orders_list as $row):
                                $cust_initials = get_order_customer_initials($row['customer_name'] ?? '');
                                $payment_method = $row['payment_method'] ?? 'Purchase Order (PO)';
                                $payment_status = $row['payment_status'] ?? 'Awaiting for Payment';
                                $shipping_status = $row['shipping_status'] ?? 'Pending';
                                
                                // PO Number is the primary business identifier
                                $po_number = $row['payment_id'] ?? $row['txnid'] ?? ('PO-' . str_pad($row['id'], 6, '0', STR_PAD_LEFT));
                                
                                // Fetch ALL order items for this specific PO Number belonging to the supplier
                                $statement_items = $pdo->prepare("SELECT o.*, p.p_sku FROM tbl_order o LEFT JOIN tbl_product p ON o.product_id = p.p_id WHERE o.payment_id=? AND o.supplier_id=? ORDER BY o.id ASC");
                                $statement_items->execute(array($row['payment_id'], $supplier_id));
                                $items = $statement_items->fetchAll(PDO::FETCH_ASSOC);

                                $has_special_order = false;
                                $searchable_items = '';
                                $total_item_quantity = 0;
                                foreach ($items as $it) {
                                    $total_item_quantity += intval($it['quantity'] ?? 1);
                                    $searchable_items .= ' ' . ($it['product_name'] ?? '') . ' ' . ($it['special_order_reference'] ?? '') . ' ' . ($it['p_sku'] ?? '');
                                    if (isset($it['item_type']) && $it['item_type'] === 'SPECIAL_ORDER') {
                                        $has_special_order = true;
                                    }
                                }

                                // Customer Profile Info (Secondary Information)
                                $c_data = null;
                                if (!empty($row['customer_id'])) {
                                    $statement_c = $pdo->prepare("SELECT * FROM tbl_customer WHERE cust_id=?");
                                    $statement_c->execute(array($row['customer_id']));
                                    $c_data = $statement_c->fetch(PDO::FETCH_ASSOC);
                                }
                                if (!$c_data && !empty($row['customer_email'])) {
                                    $statement_c2 = $pdo->prepare("SELECT * FROM tbl_customer WHERE cust_email=?");
                                    $statement_c2->execute(array($row['customer_email']));
                                    $c_data = $statement_c2->fetch(PDO::FETCH_ASSOC);
                                }

                                $cust_company = $c_data['cust_cname'] ?? $c_data['cust_b_cname'] ?? $c_data['cust_s_cname'] ?? '';
                                $cust_phone = $c_data['cust_phone'] ?? $c_data['cust_b_phone'] ?? $c_data['cust_s_phone'] ?? '';
                                $cust_address = $c_data['cust_s_address'] ?? $c_data['cust_b_address'] ?? $c_data['cust_address'] ?? '';
                                $cust_barangay = $c_data['cust_s_state'] ?? $c_data['cust_b_state'] ?? $c_data['cust_state'] ?? '';
                                $cust_city = $c_data['cust_s_city'] ?? $c_data['cust_b_city'] ?? $c_data['cust_city'] ?? '';
                                
                                if (empty($cust_city) && $c_data) {
                                    $country_id_val = $c_data['cust_s_country'] ?? $c_data['cust_b_country'] ?? $c_data['cust_country'] ?? '';
                                    if (!empty($country_id_val) && is_numeric($country_id_val)) {
                                        $statement_cnt = $pdo->prepare("SELECT country_name FROM tbl_country WHERE country_id=?");
                                        $statement_cnt->execute(array($country_id_val));
                                        $cnt_data = $statement_cnt->fetch(PDO::FETCH_ASSOC);
                                        if ($cnt_data && !empty($cnt_data['country_name'])) {
                                            $cust_city = $cnt_data['country_name'];
                                        }
                                    } elseif (!empty($country_id_val)) {
                                        $cust_city = $country_id_val;
                                    }
                                }

                                $full_customer_address = '';
                                $addr_parts = array_filter([$cust_address, $cust_barangay, $cust_city]);
                                if (!empty($addr_parts)) {
                                    $full_customer_address = implode(', ', $addr_parts);
                                }

                                // Parse Bank info / Delivery info
                                $parsed_bank_info = parse_order_bank_info($row['bank_transaction_info'] ?? '');
                            ?>
                            <tr class="order-data-row" 
                                data-id="<?php echo $row['id']; ?>"
                                data-status="<?php echo strtolower($payment_status); ?>" 
                                data-search="<?php echo htmlspecialchars(strtolower($po_number . ' #' . $row['id'] . ' ' . ($row['customer_name'] ?? '') . ' ' . ($row['customer_email'] ?? '') . ' ' . ($row['txnid'] ?? '') . ' ' . $cust_company . ' ' . $searchable_items)); ?>">
                                
                                <!-- 1. Order ID -->
                                <td style="text-align: center; vertical-align: top; font-weight: 700; color: #64748b; font-family: monospace;">
                                    #<?php echo $row['id']; ?>
                                </td>

                                <!-- 2. PO Number (Primary Business Identifier) -->
                                <td class="po-number-cell" style="vertical-align: top;">
                                    <div class="po-badge-wrap">
                                        <i class="fa fa-file-text-o"></i>
                                        <span class="po-primary-code"><?php echo htmlspecialchars($po_number); ?></span>
                                    </div>
                                </td>

                                <!-- 3. Customer Details (Secondary Information) -->
                                <td>
                                    <div class="cust-cell-wrapper">
                                        <div class="cust-avatar">
                                            <?php echo htmlspecialchars($cust_initials); ?>
                                        </div>
                                        <div class="cust-info-block">
                                            <div class="cust-name"><?php echo htmlspecialchars($row['customer_name'] ?? 'Walk-in Customer'); ?></div>
                                            <?php if(!empty($cust_company)): ?>
                                                <div class="cust-company"><i class="fa fa-building-o" style="font-size: 10.5px;"></i> <?php echo htmlspecialchars($cust_company); ?></div>
                                            <?php endif; ?>
                                            <a href="mailto:<?php echo htmlspecialchars($row['customer_email'] ?? ''); ?>" class="cust-email-link">
                                                <?php echo htmlspecialchars($row['customer_email'] ?? ''); ?>
                                            </a>
                                            <?php if(!empty($cust_phone)): ?>
                                                <div class="cust-meta-text"><i class="fa fa-phone" style="font-size: 10px;"></i> <?php echo htmlspecialchars($cust_phone); ?></div>
                                            <?php endif; ?>
                                            <?php if(!empty($cust_barangay)): ?>
                                                <div class="cust-meta-text"><i class="fa fa-map-marker" style="font-size: 10px;"></i> <?php echo htmlspecialchars($cust_barangay); ?></div>
                                            <?php endif; ?>
                                            
                                            <button type="button" class="btn-msg-customer" data-toggle="modal" data-target="#msg-modal-<?php echo $row['id']; ?>">
                                                <i class="fa fa-paper-plane-o"></i> Message Customer
                                            </button>
                                        </div>
                                    </div>
                                </td>

                                <!-- 4. PO Date -->
                                <td>
                                    <div style="font-size: 12.5px; font-weight: 600; color: #1e293b;">
                                        <i class="fa fa-calendar-o" style="color: #64748b; font-size: 11px;"></i> <?php echo date('M d, Y', strtotime($row['payment_date'])); ?>
                                    </div>
                                    <div style="font-size: 11px; color: #64748b; margin-top: 2px;">
                                        <i class="fa fa-clock-o" style="font-size: 10.5px;"></i> <?php echo date('h:i A', strtotime($row['payment_date'])); ?>
                                    </div>
                                </td>

                                <!-- 6. Total Amount -->
                                <td style="text-align: right; vertical-align: top;">
                                    <div style="font-size: 15px; font-weight: 800; color: #0f172a;">
                                        &#8369;<?php echo number_format(floatval($row['paid_amount'] ?? 0), 2); ?>
                                    </div>
                                    <div>
                                        <?php if($payment_method == 'PayPal'): ?>
                                            <span class="badge-method badge-method-paypal"><i class="fa fa-paypal"></i> PayPal</span>
                                        <?php elseif($payment_method == 'Stripe'): ?>
                                            <span class="badge-method badge-method-stripe"><i class="fa fa-credit-card"></i> Stripe</span>
                                        <?php elseif($payment_method == 'Bank Deposit'): ?>
                                            <span class="badge-method badge-method-bank"><i class="fa fa-university"></i> Bank</span>
                                        <?php else: ?>
                                            <span class="badge-method badge-method-otc"><i class="fa fa-file-text-o"></i> <?php echo htmlspecialchars($payment_method); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </td>

                                <!-- 7. Payment Status -->
                                <td style="text-align: center; vertical-align: top;">
                                    <div>
                                        <span class="status-pill status-awaiting"><i class="fa fa-clock-o"></i> Awaiting Payment</span>
                                    </div>
                                    <div style="margin-top: 6px;">
                                        <a href="pos.php?po_id=<?php echo urlencode($row['payment_id'] ?? $row['txnid']); ?>" class="btn-status-action btn-action-paid" title="Open POS to receive and process payment for this Purchase Order">
                                            <i class="fa fa-check-circle"></i> Receive / Paid
                                        </a>
                                    </div>
                                </td>

                                <!-- 8. Order / Shipping Status -->
                                <td style="text-align: center; vertical-align: top;">
                                    <?php if($shipping_status == 'Completed'): ?>
                                        <span class="status-pill" style="background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0;"><i class="fa fa-check"></i> Shipped</span>
                                    <?php else: ?>
                                        <span class="status-pill status-pending"><i class="fa fa-truck"></i> <?php echo htmlspecialchars($shipping_status); ?></span>
                                    <?php endif; ?>
                                </td>

                                <!-- 9. Action (View Purchase Order Voucher & Delete) -->
                                <td style="text-align: center; vertical-align: top;">
                                    <div>
                                        <button type="button" class="btn-po-voucher" data-toggle="modal" data-target="#po-modal-<?php echo $row['id']; ?>" title="View Purchase Order Details">
                                            <i class="fa fa-file-text-o"></i> View PO
                                        </button>
                                    </div>
                                    <div style="margin-top: 8px;">
                                        <button type="button" class="btn-delete-order" data-href="order-delete.php?id=<?php echo $row['id']; ?>" data-toggle="modal" data-target="#confirm-delete" title="Delete Purchase Order">
                                            <i class="fa fa-trash-o"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ------------------------------------------------------------- -->
        <!-- MODALS (Rendered outside table for valid DOM structure)       -->
        <!-- ------------------------------------------------------------- -->
        <?php if(!empty($orders_list)): ?>
            <?php foreach ($orders_list as $row):
                $po_number = $row['payment_id'] ?? $row['txnid'] ?? ('PO-' . str_pad($row['id'], 6, '0', STR_PAD_LEFT));
                $payment_method = $row['payment_method'] ?? 'Purchase Order (PO)';
                $payment_status = $row['payment_status'] ?? 'Awaiting for Payment';
                $shipping_status = $row['shipping_status'] ?? 'Pending';

                // Fetch ALL order items for this specific PO Number
                $statement_items = $pdo->prepare("SELECT o.*, p.p_sku FROM tbl_order o LEFT JOIN tbl_product p ON o.product_id = p.p_id WHERE o.payment_id=? AND o.supplier_id=? ORDER BY o.id ASC");
                $statement_items->execute(array($row['payment_id'], $supplier_id));
                $items = $statement_items->fetchAll(PDO::FETCH_ASSOC);

                $total_item_quantity = 0;
                foreach ($items as $it) {
                    $total_item_quantity += intval($it['quantity'] ?? 1);
                }

                // Customer Profile Info
                $c_data = null;
                if (!empty($row['customer_id'])) {
                    $statement_c = $pdo->prepare("SELECT * FROM tbl_customer WHERE cust_id=?");
                    $statement_c->execute(array($row['customer_id']));
                    $c_data = $statement_c->fetch(PDO::FETCH_ASSOC);
                }
                if (!$c_data && !empty($row['customer_email'])) {
                    $statement_c2 = $pdo->prepare("SELECT * FROM tbl_customer WHERE cust_email=?");
                    $statement_c2->execute(array($row['customer_email']));
                    $c_data = $statement_c2->fetch(PDO::FETCH_ASSOC);
                }

                $cust_company = $c_data['cust_cname'] ?? $c_data['cust_b_cname'] ?? $c_data['cust_s_cname'] ?? '';
                $cust_phone = $c_data['cust_phone'] ?? $c_data['cust_b_phone'] ?? $c_data['cust_s_phone'] ?? '';
                $cust_address = $c_data['cust_s_address'] ?? $c_data['cust_b_address'] ?? $c_data['cust_address'] ?? '';
                $cust_barangay = $c_data['cust_s_state'] ?? $c_data['cust_b_state'] ?? $c_data['cust_state'] ?? '';
                $cust_city = $c_data['cust_s_city'] ?? $c_data['cust_b_city'] ?? $c_data['cust_city'] ?? '';

                if (empty($cust_city) && $c_data) {
                    $country_id_val = $c_data['cust_s_country'] ?? $c_data['cust_b_country'] ?? $c_data['cust_country'] ?? '';
                    if (!empty($country_id_val) && is_numeric($country_id_val)) {
                        $statement_cnt = $pdo->prepare("SELECT country_name FROM tbl_country WHERE country_id=?");
                        $statement_cnt->execute(array($country_id_val));
                        $cnt_data = $statement_cnt->fetch(PDO::FETCH_ASSOC);
                        if ($cnt_data && !empty($cnt_data['country_name'])) {
                            $cust_city = $cnt_data['country_name'];
                        }
                    } elseif (!empty($country_id_val)) {
                        $cust_city = $country_id_val;
                    }
                }

                $full_customer_address = '';
                $addr_parts = array_filter([$cust_address, $cust_barangay, $cust_city]);
                if (!empty($addr_parts)) {
                    $full_customer_address = implode(', ', $addr_parts);
                }

                $parsed_bank_info = parse_order_bank_info($row['bank_transaction_info'] ?? '');
            ?>
                <!-- --------------------------------------------------- -->
                <!-- MODAL: Send Message to Customer                     -->
                <!-- --------------------------------------------------- -->
                <div id="msg-modal-<?php echo $row['id']; ?>" class="modal fade" role="dialog" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content" style="border-radius: 8px; overflow: hidden; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.2);">
                            <div class="modal-header-modern">
                                <h4><i class="fa fa-paper-plane-o"></i> Message to <?php echo htmlspecialchars($row['customer_name'] ?? 'Customer'); ?></h4>
                                <button type="button" class="close" data-dismiss="modal">&times;</button>
                            </div>
                            <form action="" method="post" style="margin: 0;">
                                <div class="modal-body" style="padding: 20px 24px; font-size: 13.5px; background: #ffffff;">
                                    <input type="hidden" name="cust_id" value="<?php echo htmlspecialchars($row['customer_id'] ?? '0'); ?>">
                                    <input type="hidden" name="payment_id" value="<?php echo htmlspecialchars($row['payment_id'] ?? ''); ?>">
                                    
                                    <div class="form-group" style="margin-bottom: 14px;">
                                        <label style="font-weight: 700; color: #334155; font-size: 12.5px;">Recipient</label>
                                        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 8px 12px; font-weight: 600; color: #0f172a;">
                                            <?php echo htmlspecialchars($row['customer_name'] ?? 'Customer'); ?> &lt;<?php echo htmlspecialchars($row['customer_email'] ?? 'No email'); ?>&gt;
                                        </div>
                                    </div>

                                    <div class="form-group" style="margin-bottom: 14px;">
                                        <label style="font-weight: 700; color: #334155; font-size: 12.5px;">Subject</label>
                                        <input type="text" name="subject_text" class="form-control" style="border-radius: 6px; border: 1px solid #cbd5e1; padding: 8px 12px;" value="Update regarding PO #<?php echo htmlspecialchars($po_number); ?>" required>
                                    </div>

                                    <div class="form-group" style="margin-bottom: 10px;">
                                        <label style="font-weight: 700; color: #334155; font-size: 12.5px;">Message Text</label>
                                        <textarea name="message_text" class="form-control" rows="6" style="border-radius: 6px; border: 1px solid #cbd5e1; padding: 10px 12px; resize: vertical;" placeholder="Type your message to the customer here..." required></textarea>
                                    </div>
                                </div>
                                <div class="modal-footer" style="background: #f8fafc; border-top: 1px solid #e2e8f0; padding: 12px 24px;">
                                    <button type="button" class="btn btn-default" data-dismiss="modal" style="border-radius: 6px; font-weight: 600;">Cancel</button>
                                    <button type="submit" name="form1" class="btn btn-primary" style="background: #0284c7; border: none; border-radius: 6px; font-weight: 700; padding: 6px 16px;">
                                        <i class="fa fa-paper-plane"></i> Send Message
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- --------------------------------------------------- -->
                <!-- MODAL: Customer Purchase Order Voucher (Printable)  -->
                <!-- --------------------------------------------------- -->
                <div id="po-modal-<?php echo $row['id']; ?>" class="modal fade" role="dialog" tabindex="-1">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content" style="border-radius: 8px; overflow: hidden; border: none; box-shadow: 0 10px 35px rgba(0,0,0,0.25);">
                            <div class="modal-header-modern" style="background: #0284c7; display: flex; justify-content: space-between; align-items: center; padding: 14px 20px;">
                                <h4 style="margin: 0; color: #ffffff; font-size: 16px; font-weight: 700;">
                                    <i class="fa fa-file-text-o"></i> Customer Purchase Order Voucher — <?php echo htmlspecialchars($po_number); ?>
                                </h4>
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <button type="button" class="btn btn-xs btn-default" onclick="printReceipt('po-print-area-<?php echo $row['id']; ?>')" style="background: #ffffff; color: #0284c7; font-weight: 700; border: none; border-radius: 5px; padding: 5px 12px; font-size: 12px;">
                                        <i class="fa fa-print"></i> Print Voucher
                                    </button>
                                    <button type="button" class="close" data-dismiss="modal" style="color: #ffffff; opacity: 0.9; font-size: 22px; margin: 0;">&times;</button>
                                </div>
                            </div>
                            <div class="modal-body" style="padding: 24px; background: #f8fafc;">
                                <div class="po-voucher-sheet" id="po-print-area-<?php echo $row['id']; ?>">
                                    
                                    <!-- Brand Header & PO Metadata -->
                                    <div class="po-header-bar">
                                        <div>
                                            <h3 class="po-title-brand">CUSTOMER PURCHASE ORDER VOUCHER</h3>
                                            <p class="po-subtitle-brand">eConstruction Supply Store Fulfillment</p>
                                        </div>
                                        <div style="text-align: right;">
                                            <div style="background: #e0f2fe; color: #0369a1; font-weight: 800; padding: 5px 12px; border-radius: 6px; display: inline-block; font-size: 13px; margin-bottom: 4px; border: 1px solid #bae6fd;">
                                                PO Number: <?php echo htmlspecialchars($po_number); ?>
                                            </div>
                                            <div>
                                                <span class="status-pill status-awaiting">
                                                    Payment: <?php echo htmlspecialchars($payment_status); ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Info Grid: Supplier Info, Customer Info, PO Info -->
                                    <div class="row" style="margin-bottom: 16px;">
                                        <!-- 1. Supplier Information -->
                                        <div class="col-xs-4">
                                            <div class="po-info-box">
                                                <h5><i class="fa fa-building"></i> Supplier Information</h5>
                                                <div style="font-weight: 700; font-size: 12.5px; color: #0f172a;"><?php echo htmlspecialchars($store_name); ?></div>
                                                <?php if(!empty($store_address)): ?>
                                                    <div style="font-size: 11px; color: #475569; margin-top: 2px; line-height: 1.3;">
                                                        <strong>Address:</strong> <?php echo nl2br(htmlspecialchars($store_address)); ?>
                                                    </div>
                                                <?php endif; ?>
                                                <?php if(!empty($store_phone)): ?>
                                                    <div style="font-size: 11px; color: #475569; margin-top: 2px;">
                                                        <strong>Phone:</strong> <?php echo htmlspecialchars($store_phone); ?>
                                                    </div>
                                                <?php endif; ?>
                                                <?php if(!empty($store_email)): ?>
                                                    <div style="font-size: 11px; color: #475569; margin-top: 2px;">
                                                        <strong>Email:</strong> <?php echo htmlspecialchars($store_email); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <!-- 2. Customer Information -->
                                        <div class="col-xs-4">
                                            <div class="po-info-box">
                                                <h5><i class="fa fa-user"></i> Customer Information</h5>
                                                <div style="font-weight: 700; font-size: 12.5px; color: #0f172a;">
                                                    <?php echo htmlspecialchars($row['customer_name'] ?? 'Walk-in Customer'); ?>
                                                </div>
                                                <?php if(!empty($cust_company)): ?>
                                                    <div style="font-size: 11px; color: #475569; margin-top: 2px;">
                                                        <strong>Company:</strong> <?php echo htmlspecialchars($cust_company); ?>
                                                    </div>
                                                <?php endif; ?>
                                                <div style="font-size: 11px; color: #475569; margin-top: 2px;">
                                                    <strong>Email:</strong> <?php echo htmlspecialchars($row['customer_email'] ?? 'N/A'); ?>
                                                </div>
                                                <?php if(!empty($cust_phone)): ?>
                                                    <div style="font-size: 11px; color: #475569; margin-top: 2px;">
                                                        <strong>Contact:</strong> <?php echo htmlspecialchars($cust_phone); ?>
                                                    </div>
                                                <?php endif; ?>
                                                <?php if(!empty($full_customer_address)): ?>
                                                    <div style="font-size: 11px; color: #475569; margin-top: 2px; line-height: 1.3;">
                                                        <strong>Address:</strong> <?php echo htmlspecialchars($full_customer_address); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <!-- 3. Purchase Order Details -->
                                        <div class="col-xs-4">
                                            <div class="po-info-box">
                                                <h5><i class="fa fa-info-circle"></i> Purchase Order Info</h5>
                                                <div style="font-size: 11px; color: #475569;">
                                                    <strong>PO Number:</strong> <span style="font-weight: 700; color: #0284c7;"><?php echo htmlspecialchars($po_number); ?></span>
                                                </div>
                                                <div style="font-size: 11px; color: #475569; margin-top: 2px;">
                                                    <strong>PO Date:</strong> <?php echo date('F j, Y, g:i A', strtotime($row['payment_date'])); ?>
                                                </div>
                                                <div style="font-size: 11px; color: #475569; margin-top: 2px;">
                                                    <strong>Order Status:</strong> <span style="font-weight: 700; color: #0284c7;"><?php echo htmlspecialchars($shipping_status); ?></span>
                                                </div>
                                                <div style="font-size: 11px; color: #475569; margin-top: 2px;">
                                                    <strong>Payment Status:</strong> <span style="font-weight: 700; color: #d97706;"><?php echo htmlspecialchars($payment_status); ?></span>
                                                </div>
                                                <div style="font-size: 11px; color: #475569; margin-top: 2px;">
                                                    <strong>Payment Method:</strong> <?php echo htmlspecialchars($payment_method); ?>
                                                </div>
                                                <?php if(!empty($row['txnid']) && $row['txnid'] !== $po_number): ?>
                                                    <div style="font-size: 10.5px; color: #64748b; margin-top: 2px; font-family: monospace;">
                                                        <strong>Txn ID:</strong> <?php echo htmlspecialchars($row['txnid']); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Order Items Table -->
                                    <div style="margin-bottom: 12px;">
                                        <h5 style="font-size: 12px; font-weight: 800; text-transform: uppercase; color: #0f172a; margin: 0 0 6px 0; letter-spacing: 0.4px;">
                                            <i class="fa fa-list"></i> Order Items (<?php echo count($items); ?> items, <?php echo $total_item_quantity; ?> total qty)
                                        </h5>
                                        <table class="po-items-table">
                                            <thead>
                                                <tr>
                                                    <th style="width: 35px; text-align: center;">#</th>
                                                    <th>Product</th>
                                                    <th style="width: 100px;">SKU / Code</th>
                                                    <th>Description / Specifications</th>
                                                    <th style="width: 55px; text-align: center;">Qty</th>
                                                    <th style="width: 95px; text-align: right;">Unit Price</th>
                                                    <th style="width: 105px; text-align: right;">Subtotal</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php
                                                $po_subtotal = 0;
                                                $po_c = 0;
                                                foreach ($items as $p_item):
                                                    $po_c++;
                                                    $p_qty = intval($p_item['quantity'] ?? 1);
                                                    $p_price = floatval($p_item['unit_price'] ?? 0);
                                                    $p_line = $p_price * $p_qty;
                                                    $po_subtotal += $p_line;
                                                    $is_so_item = (isset($p_item['item_type']) && $p_item['item_type'] === 'SPECIAL_ORDER');
                                                    $sku_code = !empty($p_item['p_sku']) ? $p_item['p_sku'] : ($p_item['product_id'] > 0 ? ('SKU-' . str_pad($p_item['product_id'], 4, '0', STR_PAD_LEFT)) : 'N/A');
                                                ?>
                                                <tr <?php echo $is_so_item ? 'style="background-color: #fffbeb;"' : ''; ?>>
                                                    <td style="text-align: center; font-weight: 600;"><?php echo $po_c; ?></td>
                                                    <td>
                                                        <?php if ($is_so_item): ?>
                                                            <span class="badge-special-tag" style="margin-bottom: 2px;"><i class="fa fa-star"></i> SPECIAL ORDER</span><br>
                                                        <?php endif; ?>
                                                        <strong><?php echo htmlspecialchars($p_item['product_name'] ?? ''); ?></strong>
                                                    </td>
                                                    <td style="font-family: monospace; font-size: 11px; color: #475569;">
                                                        <?php echo htmlspecialchars($sku_code); ?>
                                                    </td>
                                                    <td style="font-size: 11.5px; color: #475569;">
                                                        <?php 
                                                        $specs_arr = [];
                                                        if (!empty($p_item['size']) && $p_item['size'] !== '-') $specs_arr[] = 'Size: ' . $p_item['size'];
                                                        if (!empty($p_item['color']) && $p_item['color'] !== '-') $specs_arr[] = 'Color: ' . $p_item['color'];
                                                        echo htmlspecialchars(implode(' | ', $specs_arr) ?: 'Standard');
                                                        ?>
                                                        <?php if ($is_so_item && !empty($p_item['product_details'])): ?>
                                                            <div style="font-size: 11px; color: #64748b; margin-top: 2px;">
                                                                <strong>Details:</strong> <?php echo htmlspecialchars($p_item['product_details']); ?>
                                                            </div>
                                                        <?php endif; ?>
                                                        <?php if ($is_so_item && !empty($p_item['special_order_reference'])): ?>
                                                            <div style="font-size: 10px; color: #0284c7; font-family: monospace; margin-top: 1px;">
                                                                <strong>Ref:</strong> <?php echo htmlspecialchars($p_item['special_order_reference']); ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td style="text-align: center; font-weight: 700;"><?php echo $p_qty; ?></td>
                                                    <td style="text-align: right;">&#8369;<?php echo number_format($p_price, 2); ?></td>
                                                    <td style="text-align: right; font-weight: 700;">&#8369;<?php echo number_format($p_line, 2); ?></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>

                                    <!-- Delivery Info, Customer Notes & Order Totals -->
                                    <div class="row">
                                        <div class="col-xs-7">
                                            <!-- Delivery Information -->
                                            <div class="po-info-box" style="margin-bottom: 12px;">
                                                <h5><i class="fa fa-truck"></i> Delivery Information</h5>
                                                <?php if(!empty($full_customer_address)): ?>
                                                    <div style="font-size: 11.5px; color: #334155; line-height: 1.4;">
                                                        <strong>Delivery Address:</strong><br>
                                                        <?php echo htmlspecialchars($full_customer_address); ?>
                                                    </div>
                                                <?php else: ?>
                                                    <div style="font-size: 11.5px; color: #334155;">
                                                        <strong>Fulfillment:</strong> Store Pick-up / Over the Counter
                                                    </div>
                                                <?php endif; ?>
                                                <?php if (!empty($parsed_bank_info['delivery_text'])): ?>
                                                    <div style="font-size: 11px; color: #64748b; margin-top: 3px;">
                                                        <strong>Instructions:</strong> <?php echo htmlspecialchars($parsed_bank_info['delivery_text']); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>

                                            <!-- Customer Notes -->
                                            <?php if(!empty($parsed_bank_info['notes'])): ?>
                                            <div class="po-info-box">
                                                <h5><i class="fa fa-sticky-note-o"></i> Customer / Order Notes</h5>
                                                <div style="font-size: 11.5px; color: #334155; line-height: 1.4;">
                                                    <?php echo nl2br(htmlspecialchars($parsed_bank_info['notes'])); ?>
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                        </div>

                                        <div class="col-xs-5">
                                            <!-- Totals Summary Table -->
                                            <table style="width: 100%; font-size: 12.5px; margin-top: 4px;">
                                                <tr>
                                                    <td style="padding: 5px 0; color: #475569;">Subtotal:</td>
                                                    <td class="text-right" style="padding: 5px 0; font-weight: 600;">&#8369;<?php echo number_format($po_subtotal, 2); ?></td>
                                                </tr>
                                                <?php
                                                $grand_total = floatval($row['paid_amount'] ?? 0);
                                                $po_delivery = max(0.00, $grand_total - $po_subtotal);
                                                if($po_delivery > 0):
                                                ?>
                                                <tr>
                                                    <td style="padding: 5px 0; color: #475569;">Delivery Fee:</td>
                                                    <td class="text-right" style="padding: 5px 0; font-weight: 600; color: #0284c7;">+&#8369;<?php echo number_format($po_delivery, 2); ?></td>
                                                </tr>
                                                <?php else: ?>
                                                <tr>
                                                    <td style="padding: 5px 0; color: #475569;">Delivery Fee:</td>
                                                    <td class="text-right" style="padding: 5px 0; color: #16a34a; font-weight: 700;">&#8369;0.00 (Store Pick-up)</td>
                                                </tr>
                                                <?php endif; ?>
                                                <tr class="po-total-row">
                                                    <td style="padding: 8px 10px; border: 1px solid #bae6fd;">Grand Total:</td>
                                                    <td class="text-right" style="padding: 8px 10px; border: 1px solid #bae6fd;">&#8369;<?php echo number_format($grand_total, 2); ?></td>
                                                </tr>
                                            </table>
                                        </div>
                                    </div>

                                    <div style="border-top: 1px dashed #cbd5e1; margin-top: 16px; padding-top: 10px; text-align: center;">
                                        <p style="font-size: 11px; color: #64748b; margin: 0; font-style: italic;">
                                            This official Customer Purchase Order Voucher is issued by <?php echo htmlspecialchars($store_name); ?>. Please present this voucher during pickup or payment settlement.
                                        </p>
                                    </div>

                                </div>
                            </div>
                            <div class="modal-footer" style="background: #f8fafc; border-top: 1px solid #e2e8f0; padding: 14px 24px;">
                                <button type="button" class="btn btn-default" data-dismiss="modal" style="border-radius: 6px; font-weight: 600;">Close</button>
                                <a href="pos.php?po_id=<?php echo urlencode($row['payment_id'] ?? $row['txnid']); ?>" class="btn btn-success" style="background: #16a34a; border: none; border-radius: 6px; font-weight: 700; padding: 7px 18px; margin-right: 6px;">
                                    <i class="fa fa-credit-card"></i> Process POS Payment
                                </a>
                                <button type="button" class="btn btn-primary" onclick="printReceipt('po-print-area-<?php echo $row['id']; ?>')" style="background: #0284c7; border: none; border-radius: 6px; font-weight: 700; padding: 7px 18px;">
                                    <i class="fa fa-print"></i> Print Purchase Order Voucher
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>
</div>

<!-- --------------------------------------------------- -->
<!-- MODAL: Confirmation for Deleting Order               -->
<!-- --------------------------------------------------- -->
<div class="modal fade" id="confirm-delete" tabindex="-1" role="dialog" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-sm">
        <div class="modal-content" style="border-radius: 8px; overflow: hidden; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.2);">
            <div class="modal-header" style="background: #ef4444; color: #ffffff; padding: 14px 18px;">
                <button type="button" class="close" data-dismiss="modal" style="color: #ffffff; opacity: 0.8;">&times;</button>
                <h4 class="modal-title" id="deleteModalLabel" style="font-weight: 800; font-size: 15px;">Delete Order Confirmation</h4>
            </div>
            <div class="modal-body" style="padding: 20px; font-size: 13.5px; color: #334155; text-align: center;">
                <i class="fa fa-exclamation-triangle" style="font-size: 32px; color: #ef4444; margin-bottom: 10px; display: block;"></i>
                Are you sure you want to delete this purchase order? Associated product stock will be returned to store inventory.
            </div>
            <div class="modal-footer" style="background: #f8fafc; border-top: 1px solid #e2e8f0; padding: 12px 18px; text-align: center;">
                <button type="button" class="btn btn-default" data-dismiss="modal" style="border-radius: 6px; font-weight: 600;">Cancel</button>
                <a class="btn btn-danger btn-ok" style="background: #ef4444; border: none; border-radius: 6px; font-weight: 700;">Delete Order</a>
            </div>
        </div>
    </div>
</div>

<script>
// Live Search Logic
document.addEventListener('DOMContentLoaded', function() {
    var searchInput = document.getElementById('orderSearchInput');
    var rows = document.querySelectorAll('.order-data-row');

    if (searchInput) {
        searchInput.addEventListener('keyup', function() {
            var query = this.value.trim().toLowerCase();

            rows.forEach(function(row) {
                var rowSearch = row.getAttribute('data-search') || '';
                if (query === '' || rowSearch.indexOf(query) !== -1) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    }

    // Modal print receipt helper
    window.printReceipt = function(elementId) {
        var printContents = document.getElementById(elementId).innerHTML;
        var printWindow = window.open('', '_blank', 'width=900,height=800');
        printWindow.document.write('<!DOCTYPE html><html><head><title>Customer Purchase Order Voucher</title>');
        printWindow.document.write('<style>');
        printWindow.document.write('*, *:before, *:after { box-sizing: border-box; }');
        printWindow.document.write('body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif; padding: 25px; color: #1e293b; background: #ffffff; font-size: 13px; line-height: 1.45; }');
        printWindow.document.write('.po-voucher-sheet { border: none !important; padding: 0 !important; background: transparent; }');
        printWindow.document.write('.po-header-bar { border-bottom: 2px solid #0284c7; padding-bottom: 12px; margin-bottom: 16px; display: flex; justify-content: space-between; align-items: flex-start; }');
        printWindow.document.write('.po-title-brand { font-size: 18px; font-weight: 800; color: #0f172a; margin: 0; text-transform: uppercase; letter-spacing: 0.5px; }');
        printWindow.document.write('.po-subtitle-brand { font-size: 12px; color: #64748b; margin: 2px 0 0 0; }');
        printWindow.document.write('.row { margin-left: -8px; margin-right: -8px; display: flex; flex-wrap: wrap; }');
        printWindow.document.write('.row:after, .row:before { display: table; content: " "; clear: both; }');
        printWindow.document.write('.col-xs-4 { width: 33.33333333%; float: left; padding-left: 8px; padding-right: 8px; }');
        printWindow.document.write('.col-xs-5 { width: 41.66666667%; float: left; padding-left: 8px; padding-right: 8px; }');
        printWindow.document.write('.col-xs-7 { width: 58.33333333%; float: left; padding-left: 8px; padding-right: 8px; }');
        printWindow.document.write('.po-info-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 10px 12px; margin-bottom: 12px; min-height: 120px; }');
        printWindow.document.write('.po-info-box h5 { margin: 0 0 6px 0; font-size: 12px; font-weight: 800; color: #0284c7; text-transform: uppercase; letter-spacing: 0.3px; border-bottom: 1px solid #e2e8f0; padding-bottom: 4px; }');
        printWindow.document.write('.po-items-table { width: 100%; border-collapse: collapse; margin: 14px 0; }');
        printWindow.document.write('.po-items-table th, .po-items-table td { border: 1px solid #cbd5e1; padding: 8px 10px; font-size: 12px; vertical-align: top; }');
        printWindow.document.write('.po-items-table th { background: #f1f5f9; font-weight: 700; color: #334155; text-transform: uppercase; font-size: 11px; }');
        printWindow.document.write('.po-total-row td { font-weight: 800; background: #f0f9ff !important; color: #0369a1; font-size: 13.5px; border-top: 2px solid #0284c7; }');
        printWindow.document.write('.status-pill { display: inline-block; padding: 3px 8px; font-size: 11px; font-weight: 700; border-radius: 12px; }');
        printWindow.document.write('.status-awaiting { background: #fef3c7; color: #92400e; border: 1px solid #fde68a; }');
        printWindow.document.write('.badge-special-tag { background: #fef3c7; color: #92400e; font-size: 10px; font-weight: 800; padding: 2px 5px; border-radius: 3px; display: inline-block; margin-bottom: 2px; }');
        printWindow.document.write('.text-right { text-align: right; }');
        printWindow.document.write('@media print { @page { margin: 1.2cm; size: auto; } body { padding: 0; background: #ffffff; } }');
        printWindow.document.write('</style>');
        printWindow.document.write('</head><body>');
        printWindow.document.write(printContents);
        printWindow.document.write('</body></html>');
        printWindow.document.close();
        printWindow.focus();
        setTimeout(function() {
            printWindow.print();
            printWindow.close();
        }, 500);
    };

    // Delete confirmation handler
    $('#confirm-delete').on('show.bs.modal', function(e) {
        $(this).find('.btn-ok').attr('href', $(e.relatedTarget).data('href'));
    });
});
</script>

<?php require_once('footer.php'); ?>
