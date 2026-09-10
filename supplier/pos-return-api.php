<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once('inc/config.php');
require_once('inc/functions.php');
global $pdo;

header('Content-Type: application/json; charset=utf-8');

// Supplier Authentication Check
if (!isset($_SESSION['supplier_user']) || empty($_SESSION['supplier_user']['supplier_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access. Please log in to your supplier account.']);
    exit;
}

$supplier_id = (int)$_SESSION['supplier_user']['supplier_id'];
$current_uid = (int)$_SESSION['supplier_user']['id'];
$user_role_raw = isset($_SESSION['supplier_user']['role']) ? $_SESSION['supplier_user']['role'] : 'USER';
$user_role = normalize_supplier_role($user_role_raw);
$is_approver = is_supplier_approver();
$current_user_name = !empty($_SESSION['supplier_user']['full_name']) ? $_SESSION['supplier_user']['full_name'] : 'Supplier Staff';

ensure_return_schema($pdo);

// Fetch Supplier Info for Receipts
$stmt_supp = $pdo->prepare("SELECT supplier_name, supplier_address, supplier_phone, supplier_email FROM tbl_supplier WHERE supplier_id = ?");
$stmt_supp->execute(array($supplier_id));
$supplier_info = $stmt_supp->fetch(PDO::FETCH_ASSOC);
if (!$supplier_info) {
    $supplier_info = [
        'supplier_name' => 'Supplier Store',
        'supplier_address' => '',
        'supplier_phone' => '',
        'supplier_email' => ''
    ];
}

$action = isset($_REQUEST['action']) ? trim($_REQUEST['action']) : '';

// -------------------------------------------------------------------------
// ACTION: SEARCH ORDERS (For Cashier Return Search)
// -------------------------------------------------------------------------
if ($action === 'search_orders') {
    $keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';
    
    try {
        if ($keyword !== '') {
            $search_param = '%' . $keyword . '%';
            $sql = "SELECT DISTINCT p.*, c.cust_phone 
                    FROM tbl_payment p
                    LEFT JOIN tbl_customer c ON p.customer_id = c.cust_id
                    LEFT JOIN tbl_order o ON p.payment_id = o.payment_id
                    LEFT JOIN tbl_product prod ON o.product_id = prod.p_id
                    WHERE p.supplier_id = ?
                      AND (
                          p.payment_id ILIKE ? OR 
                          p.txnid ILIKE ? OR 
                          p.customer_name ILIKE ? OR 
                          c.cust_phone ILIKE ? OR 
                          p.customer_email ILIKE ? OR 
                          o.product_name ILIKE ? OR 
                          o.special_order_reference ILIKE ? OR 
                          prod.p_sku ILIKE ?
                      )
                    ORDER BY p.id DESC LIMIT 30";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(array($supplier_id, $search_param, $search_param, $search_param, $search_param, $search_param, $search_param, $search_param, $search_param));
        } else {
            // Default: Show recent 20 orders for this supplier
            $sql = "SELECT p.*, c.cust_phone 
                    FROM tbl_payment p 
                    LEFT JOIN tbl_customer c ON p.customer_id = c.cust_id 
                    WHERE p.supplier_id = ? ORDER BY p.id DESC LIMIT 20";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(array($supplier_id));
        }

        $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $orders_result = [];

        foreach ($payments as $payment) {
            $p_id_str = $payment['payment_id'];

            // Fetch Items for this payment
            $stmt_items = $pdo->prepare("SELECT o.*, prod.p_sku, prod.p_featured_photo, prod.p_brand 
                                         FROM tbl_order o 
                                         LEFT JOIN tbl_product prod ON o.product_id = prod.p_id 
                                         WHERE o.payment_id = ? AND o.supplier_id = ?
                                         ORDER BY o.id ASC");
            $stmt_items->execute(array($p_id_str, $supplier_id));
            $items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

            $processed_items = [];
            $order_has_returnable = false;
            $total_purchased_units = 0;
            $total_returned_units = 0;
            $total_pending_units = 0;

            foreach ($items as $item) {
                $order_item_id = (int)$item['id'];
                $purchased_qty = max(1, (int)$item['quantity']);
                $unit_price = floatval(preg_replace('/[^0-9.]/', '', strval($item['unit_price'])));

                // Calculate Completed / Approved Returned Quantity
                $stmt_ret = $pdo->prepare("
                    SELECT COALESCE(SUM(ri.quantity_returned), 0) AS total_ret 
                    FROM tbl_return_items ri 
                    JOIN tbl_returns r ON ri.return_id = r.return_id 
                    WHERE ri.order_item_id = ? AND r.supplier_id = ? AND r.status IN ('COMPLETED', 'APPROVED', 'REFUNDED')
                ");
                $stmt_ret->execute(array($order_item_id, $supplier_id));
                $prev_ret_row = $stmt_ret->fetch(PDO::FETCH_ASSOC);
                $previously_returned = $prev_ret_row ? (int)$prev_ret_row['total_ret'] : 0;

                // Calculate Pending Approval Return Quantity
                $stmt_pen = $pdo->prepare("
                    SELECT COALESCE(SUM(ri.quantity_returned), 0) AS total_pen 
                    FROM tbl_return_items ri 
                    JOIN tbl_returns r ON ri.return_id = r.return_id 
                    WHERE ri.order_item_id = ? AND r.supplier_id = ? AND r.status = 'PENDING_APPROVAL'
                ");
                $stmt_pen->execute(array($order_item_id, $supplier_id));
                $prev_pen_row = $stmt_pen->fetch(PDO::FETCH_ASSOC);
                $pending_returned = $prev_pen_row ? (int)$prev_pen_row['total_pen'] : 0;

                $available_to_return = max(0, $purchased_qty - $previously_returned - $pending_returned);
                if ($available_to_return > 0) {
                    $order_has_returnable = true;
                }

                $total_purchased_units += $purchased_qty;
                $total_returned_units += $previously_returned;
                $total_pending_units += $pending_returned;

                $img_src = (!empty($item['p_featured_photo']) && file_exists('../assets/uploads/' . $item['p_featured_photo']))
                    ? '../assets/uploads/' . $item['p_featured_photo']
                    : '../assets/uploads/photo-6.jpg';

                $is_special = ($item['item_type'] === 'SPECIAL_ORDER' || (int)$item['product_id'] === 0);

                $processed_items[] = [
                    'order_item_id' => $order_item_id,
                    'product_id' => (int)$item['product_id'],
                    'product_name' => $item['product_name'],
                    'size' => $item['size'],
                    'color' => $item['color'],
                    'item_type' => $is_special ? 'SPECIAL_ORDER' : 'STANDARD',
                    'special_order_reference' => $item['special_order_reference'],
                    'product_details' => $item['product_details'],
                    'sku' => !empty($item['p_sku']) ? $item['p_sku'] : ($is_special ? ($item['special_order_reference'] ?: 'SO-ITEM') : ('SKU-' . str_pad($item['product_id'], 5, '0', STR_PAD_LEFT))),
                    'brand' => !empty($item['p_brand']) ? $item['p_brand'] : ($is_special ? 'Custom Order' : 'Generic'),
                    'photo' => $img_src,
                    'purchased_qty' => $purchased_qty,
                    'previously_returned' => $previously_returned,
                    'pending_returned' => $pending_returned,
                    'available_to_return' => $available_to_return,
                    'is_fully_returned' => ($available_to_return <= 0),
                    'unit_price' => $unit_price,
                    'line_total' => round($unit_price * $purchased_qty, 2)
                ];
            }

            // Fetch existing return requests for this payment_id
            $stmt_all_ret = $pdo->prepare("SELECT return_id, return_reference, return_date, refund_method, refund_amount, status, requested_by_name, approver_name 
                                           FROM tbl_returns 
                                           WHERE payment_id = ? AND supplier_id = ? 
                                           ORDER BY return_id DESC");
            $stmt_all_ret->execute(array($p_id_str, $supplier_id));
            $existing_returns = $stmt_all_ret->fetchAll(PDO::FETCH_ASSOC);

            $orders_result[] = [
                'payment_id' => $payment['payment_id'],
                'txnid' => $payment['txnid'],
                'payment_date' => $payment['payment_date'],
                'payment_method' => $payment['payment_method'],
                'payment_status' => $payment['payment_status'],
                'paid_amount' => (float)$payment['paid_amount'],
                'customer_id' => (int)$payment['customer_id'],
                'customer_name' => $payment['customer_name'] ?: 'Walk-in Customer',
                'customer_phone' => !empty($payment['cust_phone']) ? $payment['cust_phone'] : 'N/A',
                'customer_email' => $payment['customer_email'] ?: '',
                'items' => $processed_items,
                'items_count' => count($processed_items),
                'total_purchased_units' => $total_purchased_units,
                'total_returned_units' => $total_returned_units,
                'total_pending_units' => $total_pending_units,
                'has_returnable_items' => $order_has_returnable,
                'is_all_fully_returned' => (!$order_has_returnable && count($processed_items) > 0 && ($total_returned_units + $total_pending_units) > 0),
                'existing_returns' => $existing_returns
            ];
        }

        echo json_encode([
            'status' => 'success',
            'count' => count($orders_result),
            'orders' => $orders_result
        ]);
        exit;

    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'Failed to search orders: ' . $e->getMessage()]);
        exit;
    }
}

// -------------------------------------------------------------------------
// ACTION: GET SINGLE ORDER DETAILS
// -------------------------------------------------------------------------
if ($action === 'get_order_details') {
    $payment_id = isset($_GET['payment_id']) ? trim($_GET['payment_id']) : '';
    if (empty($payment_id)) {
        echo json_encode(['status' => 'error', 'message' => 'Payment ID is required.']);
        exit;
    }

    try {
        $stmt_pay = $pdo->prepare("SELECT p.*, c.cust_phone FROM tbl_payment p LEFT JOIN tbl_customer c ON p.customer_id = c.cust_id WHERE p.payment_id = ? AND p.supplier_id = ?");
        $stmt_pay->execute(array($payment_id, $supplier_id));
        $payment = $stmt_pay->fetch(PDO::FETCH_ASSOC);

        if (!$payment) {
            echo json_encode(['status' => 'error', 'message' => 'Order transaction not found or access denied.']);
            exit;
        }

        $stmt_items = $pdo->prepare("SELECT o.*, prod.p_sku, prod.p_featured_photo, prod.p_brand 
                                     FROM tbl_order o 
                                     LEFT JOIN tbl_product prod ON o.product_id = prod.p_id 
                                     WHERE o.payment_id = ? AND o.supplier_id = ?
                                     ORDER BY o.id ASC");
        $stmt_items->execute(array($payment_id, $supplier_id));
        $items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

        $processed_items = [];
        $order_has_returnable = false;

        foreach ($items as $item) {
            $order_item_id = (int)$item['id'];
            $purchased_qty = max(1, (int)$item['quantity']);
            $unit_price = floatval(preg_replace('/[^0-9.]/', '', strval($item['unit_price'])));

            $stmt_ret = $pdo->prepare("
                SELECT COALESCE(SUM(ri.quantity_returned), 0) AS total_ret 
                FROM tbl_return_items ri 
                JOIN tbl_returns r ON ri.return_id = r.return_id 
                WHERE ri.order_item_id = ? AND r.supplier_id = ? AND r.status IN ('COMPLETED', 'APPROVED', 'REFUNDED')
            ");
            $stmt_ret->execute(array($order_item_id, $supplier_id));
            $prev_ret_row = $stmt_ret->fetch(PDO::FETCH_ASSOC);
            $previously_returned = $prev_ret_row ? (int)$prev_ret_row['total_ret'] : 0;

            $stmt_pen = $pdo->prepare("
                SELECT COALESCE(SUM(ri.quantity_returned), 0) AS total_pen 
                FROM tbl_return_items ri 
                JOIN tbl_returns r ON ri.return_id = r.return_id 
                WHERE ri.order_item_id = ? AND r.supplier_id = ? AND r.status = 'PENDING_APPROVAL'
            ");
            $stmt_pen->execute(array($order_item_id, $supplier_id));
            $prev_pen_row = $stmt_pen->fetch(PDO::FETCH_ASSOC);
            $pending_returned = $prev_pen_row ? (int)$prev_pen_row['total_pen'] : 0;

            $available_to_return = max(0, $purchased_qty - $previously_returned - $pending_returned);
            if ($available_to_return > 0) {
                $order_has_returnable = true;
            }

            $img_src = (!empty($item['p_featured_photo']) && file_exists('../assets/uploads/' . $item['p_featured_photo']))
                ? '../assets/uploads/' . $item['p_featured_photo']
                : '../assets/uploads/photo-6.jpg';

            $is_special = ($item['item_type'] === 'SPECIAL_ORDER' || (int)$item['product_id'] === 0);

            $processed_items[] = [
                'order_item_id' => $order_item_id,
                'product_id' => (int)$item['product_id'],
                'product_name' => $item['product_name'],
                'size' => $item['size'],
                'color' => $item['color'],
                'item_type' => $is_special ? 'SPECIAL_ORDER' : 'STANDARD',
                'special_order_reference' => $item['special_order_reference'],
                'product_details' => $item['product_details'],
                'sku' => !empty($item['p_sku']) ? $item['p_sku'] : ($is_special ? ($item['special_order_reference'] ?: 'SO-ITEM') : ('SKU-' . str_pad($item['product_id'], 5, '0', STR_PAD_LEFT))),
                'brand' => !empty($item['p_brand']) ? $item['p_brand'] : ($is_special ? 'Custom Order' : 'Generic'),
                'photo' => $img_src,
                'purchased_qty' => $purchased_qty,
                'previously_returned' => $previously_returned,
                'pending_returned' => $pending_returned,
                'available_to_return' => $available_to_return,
                'is_fully_returned' => ($available_to_return <= 0),
                'unit_price' => $unit_price,
                'line_total' => round($unit_price * $purchased_qty, 2)
            ];
        }

        // Return history for this order
        $stmt_returns = $pdo->prepare("SELECT r.*, ri.product_name, ri.quantity_returned, ri.refund_amount as item_refund, ri.return_reason, ri.condition, ri.restock_status 
                                       FROM tbl_returns r
                                       LEFT JOIN tbl_return_items ri ON r.return_id = ri.return_id
                                       WHERE r.payment_id = ? AND r.supplier_id = ?
                                       ORDER BY r.return_id DESC");
        $stmt_returns->execute(array($payment_id, $supplier_id));
        $returns_history = $stmt_returns->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'status' => 'success',
            'order' => [
                'payment_id' => $payment['payment_id'],
                'txnid' => $payment['txnid'],
                'payment_date' => $payment['payment_date'],
                'payment_method' => $payment['payment_method'],
                'payment_status' => $payment['payment_status'],
                'paid_amount' => (float)$payment['paid_amount'],
                'customer_id' => (int)$payment['customer_id'],
                'customer_name' => $payment['customer_name'] ?: 'Walk-in Customer',
                'customer_phone' => !empty($payment['cust_phone']) ? $payment['cust_phone'] : 'N/A',
                'customer_email' => $payment['customer_email'] ?: '',
                'has_returnable_items' => $order_has_returnable,
                'items' => $processed_items,
                'returns_history' => $returns_history
            ]
        ]);
        exit;

    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'Failed to load order: ' . $e->getMessage()]);
        exit;
    }
}

// -------------------------------------------------------------------------
// ACTION: SUBMIT RETURN REQUEST (Cashier Action -> Sets PENDING_APPROVAL)
// -------------------------------------------------------------------------
if ($action === 'submit_return_request' || $action === 'process_return') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
        exit;
    }

    if (!can_user_request_return($user_role_raw)) {
        echo json_encode(['status' => 'error', 'message' => 'Your user role is not authorized to submit return requests.']);
        exit;
    }

    $order_item_id = isset($_POST['order_item_id']) ? (int)$_POST['order_item_id'] : 0;
    $payment_id = isset($_POST['payment_id']) ? trim($_POST['payment_id']) : '';
    $return_qty = isset($_POST['return_quantity']) ? (int)$_POST['return_quantity'] : 0;
    $return_reason = isset($_POST['return_reason']) ? trim($_POST['return_reason']) : '';
    $reason_notes = isset($_POST['reason_notes']) ? trim($_POST['reason_notes']) : '';
    $condition = isset($_POST['condition']) ? trim($_POST['condition']) : '';
    $refund_method = isset($_POST['refund_method']) ? trim($_POST['refund_method']) : 'Cash';
    $general_notes = isset($_POST['general_notes']) ? trim($_POST['general_notes']) : '';

    // If reason is "Other", append notes
    if ($return_reason === 'Other' && !empty($reason_notes)) {
        $final_reason = 'Other: ' . $reason_notes;
    } else {
        $final_reason = $return_reason;
    }

    // Validation
    if ($order_item_id <= 0 || empty($payment_id)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid order item or transaction reference.']);
        exit;
    }
    if ($return_qty < 1) {
        echo json_encode(['status' => 'error', 'message' => 'Return quantity must be at least 1 unit.']);
        exit;
    }
    if (empty($final_reason)) {
        echo json_encode(['status' => 'error', 'message' => 'Please select a Return Reason.']);
        exit;
    }
    if (empty($condition)) {
        echo json_encode(['status' => 'error', 'message' => 'Please select an Item Condition.']);
        exit;
    }

    try {
        // Fetch Authoritative Order Item & Payment details for current supplier
        $stmt_check = $pdo->prepare("
            SELECT o.*, p.payment_date, p.payment_method as orig_payment_method, p.customer_id, p.customer_name, p.customer_email, c.cust_phone as customer_phone, prod.p_sku
            FROM tbl_order o
            JOIN tbl_payment p ON o.payment_id = p.payment_id
            LEFT JOIN tbl_customer c ON p.customer_id = c.cust_id
            LEFT JOIN tbl_product prod ON o.product_id = prod.p_id
            WHERE o.id = ? AND o.payment_id = ? AND o.supplier_id = ? AND p.supplier_id = ?
        ");
        $stmt_check->execute(array($order_item_id, $payment_id, $supplier_id, $supplier_id));
        $order_row = $stmt_check->fetch(PDO::FETCH_ASSOC);

        if (!$order_row) {
            echo json_encode(['status' => 'error', 'message' => 'Transaction line item not found or unauthorized for this supplier.']);
            exit;
        }

        // Calculate Previously Approved & Pending Returns
        $stmt_prev = $pdo->prepare("
            SELECT COALESCE(SUM(ri.quantity_returned), 0) AS total_ret 
            FROM tbl_return_items ri 
            JOIN tbl_returns r ON ri.return_id = r.return_id 
            WHERE ri.order_item_id = ? AND r.supplier_id = ? AND r.status IN ('COMPLETED', 'APPROVED', 'REFUNDED')
        ");
        $stmt_prev->execute(array($order_item_id, $supplier_id));
        $prev_ret_row = $stmt_prev->fetch(PDO::FETCH_ASSOC);
        $already_returned = $prev_ret_row ? (int)$prev_ret_row['total_ret'] : 0;

        $stmt_pen = $pdo->prepare("
            SELECT COALESCE(SUM(ri.quantity_returned), 0) AS total_pen 
            FROM tbl_return_items ri 
            JOIN tbl_returns r ON ri.return_id = r.return_id 
            WHERE ri.order_item_id = ? AND r.supplier_id = ? AND r.status = 'PENDING_APPROVAL'
        ");
        $stmt_pen->execute(array($order_item_id, $supplier_id));
        $prev_pen_row = $stmt_pen->fetch(PDO::FETCH_ASSOC);
        $pending_returned = $prev_pen_row ? (int)$prev_pen_row['total_pen'] : 0;

        $purchased_qty = max(1, (int)$order_row['quantity']);
        $available_to_return = max(0, $purchased_qty - $already_returned - $pending_returned);

        if ($available_to_return <= 0) {
            echo json_encode([
                'status' => 'error',
                'message' => 'This item has no remaining returnable units (Purchased: ' . $purchased_qty . ', Returned: ' . $already_returned . ', Pending Approval: ' . $pending_returned . ').'
            ]);
            exit;
        }

        if ($return_qty > $available_to_return) {
            echo json_encode([
                'status' => 'error',
                'message' => "Requested return quantity ({$return_qty}) exceeds the available returnable quantity ({$available_to_return})."
            ]);
            exit;
        }

        // Authoritative Unit Price & Refund Amount Calculation
        $unit_price = floatval(preg_replace('/[^0-9.]/', '', strval($order_row['unit_price'])));
        $refund_amount = round($unit_price * $return_qty, 2);

        $is_special_order = ($order_row['item_type'] === 'SPECIAL_ORDER' || (int)$order_row['product_id'] === 0);
        $product_id = (int)$order_row['product_id'];

        // Generate Return Reference and Date
        $return_reference = generate_unique_return_reference($pdo, $supplier_id);
        $return_date = date('Y-m-d H:i:s');

        $customer_id = (int)$order_row['customer_id'];
        $customer_name = $order_row['customer_name'] ?: 'Walk-in Customer';
        $customer_email = $order_row['customer_email'] ?: '';
        $customer_phone = $order_row['customer_phone'] ?: '';
        $sku = !empty($order_row['p_sku']) ? $order_row['p_sku'] : ($is_special_order ? ($order_row['special_order_reference'] ?: 'SO-ITEM') : ('SKU-' . str_pad($product_id, 5, '0', STR_PAD_LEFT)));

        // BEGIN ATOMIC DATABASE TRANSACTION
        $pdo->beginTransaction();

        // 1. Insert into tbl_returns with status PENDING_APPROVAL
        $stmt_ret_insert = $pdo->prepare("
            INSERT INTO tbl_returns (
                return_reference,
                payment_id,
                order_id,
                order_item_id,
                supplier_id,
                customer_id,
                customer_name,
                customer_email,
                customer_phone,
                return_date,
                refund_method,
                refund_amount,
                status,
                requested_by_id,
                requested_by_name,
                requested_by_role,
                processed_by,
                notes,
                created_at,
                updated_at
            ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP) RETURNING return_id
        ");
        $stmt_ret_insert->execute(array(
            $return_reference,
            $payment_id,
            $order_item_id,
            $order_item_id,
            $supplier_id,
            $customer_id,
            $customer_name,
            $customer_email,
            $customer_phone,
            $return_date,
            $refund_method,
            $refund_amount,
            'PENDING_APPROVAL',
            $current_uid,
            $current_user_name,
            $user_role,
            $current_user_name,
            $general_notes
        ));

        $ret_row = $stmt_ret_insert->fetch(PDO::FETCH_ASSOC);
        $return_id = (int)$ret_row['return_id'];

        // 2. Insert into tbl_return_items with restock_status PENDING
        $stmt_item_insert = $pdo->prepare("
            INSERT INTO tbl_return_items (
                return_id,
                return_reference,
                order_item_id,
                product_id,
                product_name,
                sku,
                size,
                color,
                item_type,
                special_order_reference,
                product_details,
                quantity_returned,
                unit_price,
                refund_amount,
                return_reason,
                condition,
                restock_status,
                notes,
                created_at
            ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?, CURRENT_TIMESTAMP)
        ");
        $stmt_item_insert->execute(array(
            $return_id,
            $return_reference,
            $order_item_id,
            $product_id,
            $order_row['product_name'],
            $sku,
            $order_row['size'] ?: '',
            $order_row['color'] ?: '',
            $order_row['item_type'] ?: 'STANDARD',
            $order_row['special_order_reference'] ?: '',
            $order_row['product_details'] ?: '',
            $return_qty,
            $unit_price,
            $refund_amount,
            $final_reason,
            $condition,
            'PENDING',
            $general_notes
        ));

        // COMMIT TRANSACTION - No stock or order alteration done at this stage!
        $pdo->commit();

        echo json_encode([
            'status' => 'success',
            'status_code' => 'PENDING_APPROVAL',
            'message' => 'Return request submitted successfully. It is now awaiting Manager or Administrator review and approval.',
            'return_reference' => $return_reference,
            'return_id' => $return_id,
            'refund_amount' => $refund_amount,
            'product_name' => $order_row['product_name'],
            'quantity_returned' => $return_qty,
            'condition' => $condition,
            'refund_method' => $refund_method,
            'requested_by' => $current_user_name,
            'created_at' => $return_date
        ]);
        exit;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode(['status' => 'error', 'message' => 'Failed to submit return request: ' . $e->getMessage()]);
        exit;
    }
}

// -------------------------------------------------------------------------
// ACTION: GET RETURN DETAILS (For Review Modal)
// -------------------------------------------------------------------------
if ($action === 'get_return_details') {
    $return_id = isset($_GET['return_id']) ? (int)$_GET['return_id'] : 0;
    $return_ref = isset($_GET['return_reference']) ? trim($_GET['return_reference']) : '';

    if ($return_id <= 0 && empty($return_ref)) {
        echo json_encode(['status' => 'error', 'message' => 'Return ID or Reference is required.']);
        exit;
    }

    try {
        if ($return_id > 0) {
            $stmt = $pdo->prepare("SELECT * FROM tbl_returns WHERE return_id = ? AND supplier_id = ?");
            $stmt->execute(array($return_id, $supplier_id));
        } else {
            $stmt = $pdo->prepare("SELECT * FROM tbl_returns WHERE return_reference = ? AND supplier_id = ?");
            $stmt->execute(array($return_ref, $supplier_id));
        }
        $ret = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$ret) {
            echo json_encode(['status' => 'error', 'message' => 'Return request not found or access denied.']);
            exit;
        }

        // Fetch items for this return
        $stmt_items = $pdo->prepare("SELECT * FROM tbl_return_items WHERE return_id = ?");
        $stmt_items->execute(array($ret['return_id']));
        $items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

        // Fetch original payment info
        $stmt_pay = $pdo->prepare("SELECT * FROM tbl_payment WHERE payment_id = ? AND supplier_id = ?");
        $stmt_pay->execute(array($ret['payment_id'], $supplier_id));
        $pay = $stmt_pay->fetch(PDO::FETCH_ASSOC);

        $is_self_request = ((int)$ret['requested_by_id'] === $current_uid);
        $can_approve = can_user_approve_return($user_role_raw, $current_uid, $ret['requested_by_id']);

        echo json_encode([
            'status' => 'success',
            'return' => $ret,
            'items' => $items,
            'payment' => $pay,
            'is_self_request' => $is_self_request,
            'can_approve' => $can_approve,
            'supplier' => $supplier_info
        ]);
        exit;

    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'Failed to load return details: ' . $e->getMessage()]);
        exit;
    }
}

// -------------------------------------------------------------------------
// ACTION: APPROVE RETURN (Manager/Admin Action)
// -------------------------------------------------------------------------
if ($action === 'approve_return') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
        exit;
    }

    if (!is_supplier_approver()) {
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized: Only Managers and Administrators can approve returns.']);
        exit;
    }

    $return_id = isset($_POST['return_id']) ? (int)$_POST['return_id'] : 0;
    $return_ref = isset($_POST['return_reference']) ? trim($_POST['return_reference']) : '';
    $approver_remarks = isset($_POST['approver_remarks']) ? trim($_POST['approver_remarks']) : '';

    if ($return_id <= 0 && empty($return_ref)) {
        echo json_encode(['status' => 'error', 'message' => 'Return ID or Reference is required.']);
        exit;
    }

    try {
        // Fetch Return record
        if ($return_id > 0) {
            $stmt = $pdo->prepare("SELECT * FROM tbl_returns WHERE return_id = ? AND supplier_id = ? FOR UPDATE");
            $stmt->execute(array($return_id, $supplier_id));
        } else {
            $stmt = $pdo->prepare("SELECT * FROM tbl_returns WHERE return_reference = ? AND supplier_id = ? FOR UPDATE");
            $stmt->execute(array($return_ref, $supplier_id));
        }
        $ret = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$ret) {
            echo json_encode(['status' => 'error', 'message' => 'Return request not found or access denied.']);
            exit;
        }

        // Status check
        if ($ret['status'] !== 'PENDING_APPROVAL' && $ret['status'] !== 'Pending') {
            echo json_encode(['status' => 'error', 'message' => 'This return request has already been processed (Current status: ' . $ret['status'] . ').']);
            exit;
        }

        // SELF-APPROVAL RULE: Allowed for Admin supplier only; prohibited for Manager, Supervisor, Cashier
        if ((int)$ret['requested_by_id'] === $current_uid && $user_role !== 'ADMIN') {
            echo json_encode([
                'status' => 'error',
                'error_code' => 'SELF_APPROVAL_PROHIBITED',
                'message' => 'Self-approval is strictly prohibited for ' . ucfirst(strtolower($user_role)) . ' role. An authorized Administrator must review and approve this transaction.'
            ]);
            exit;
        }

        // Fetch return items
        $stmt_items = $pdo->prepare("SELECT * FROM tbl_return_items WHERE return_id = ?");
        $stmt_items->execute(array($ret['return_id']));
        $items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

        if (empty($items)) {
            echo json_encode(['status' => 'error', 'message' => 'No line items found for this return request.']);
            exit;
        }

        $pdo->beginTransaction();

        $restocked_summary = [];

        foreach ($items as $it) {
            $item_id = (int)$it['return_item_id'];
            $p_id = (int)$it['product_id'];
            $qty = max(1, (int)$it['quantity_returned']);
            $cond = trim($it['condition']);
            $is_special = ($it['item_type'] === 'SPECIAL_ORDER' || $p_id === 0);

            // Condition-based Restock Rule
            $is_resellable = in_array($cond, ['Good / Resalable', 'Resellable', 'Unopened'], true);

            if ($is_resellable && !$is_special && $p_id > 0) {
                // Add back to inventory
                $stmt_stock = $pdo->prepare("UPDATE tbl_product SET p_qty = p_qty + ? WHERE p_id = ? AND supplier_id = ?");
                $stmt_stock->execute(array($qty, $p_id, $supplier_id));

                $stmt_it_up = $pdo->prepare("UPDATE tbl_return_items SET restock_status = 'RESTOCKED' WHERE return_item_id = ?");
                $stmt_it_up->execute(array($item_id));

                $restocked_summary[] = "{$it['product_name']} (+{$qty} units restocked)";
            } else {
                // Non-sellable / damaged / special order - do not add to sellable inventory
                $stmt_it_up = $pdo->prepare("UPDATE tbl_return_items SET restock_status = 'NOT_RESTOCKED' WHERE return_item_id = ?");
                $stmt_it_up->execute(array($item_id));

                $restocked_summary[] = "{$it['product_name']} (Non-sellable/Special: Not Restocked)";
            }
        }

        // Update tbl_returns record
        $stmt_ret_up = $pdo->prepare("
            UPDATE tbl_returns SET 
                status = 'COMPLETED',
                approver_id = ?,
                approver_name = ?,
                approver_role = ?,
                approver_remarks = ?,
                approved_at = CURRENT_TIMESTAMP,
                completed_at = CURRENT_TIMESTAMP,
                processed_by = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE return_id = ? AND supplier_id = ?
        ");
        $stmt_ret_up->execute(array(
            $current_uid,
            $current_user_name,
            $user_role,
            $approver_remarks,
            $current_user_name,
            $ret['return_id'],
            $supplier_id
        ));

        $pdo->commit();

        // Build Receipt Data for automatic thermal printing
        $first_item = $items[0];
        $receipt = [
            'return_id' => $ret['return_id'],
            'return_reference' => $ret['return_reference'],
            'return_date' => $ret['return_date'],
            'approved_at' => date('Y-m-d H:i:s'),
            'payment_id' => $ret['payment_id'],
            'customer_name' => $ret['customer_name'] ?: 'Walk-in Customer',
            'customer_phone' => $ret['customer_phone'] ?: '',
            'customer_email' => $ret['customer_email'] ?: '',
            'product_name' => $first_item['product_name'],
            'sku' => $first_item['sku'],
            'size' => $first_item['size'],
            'color' => $first_item['color'],
            'is_special_order' => ($first_item['item_type'] === 'SPECIAL_ORDER' || (int)$first_item['product_id'] === 0),
            'special_order_reference' => $first_item['special_order_reference'],
            'product_details' => $first_item['product_details'],
            'unit_price' => floatval($first_item['unit_price']),
            'quantity_returned' => (int)$first_item['quantity_returned'],
            'refund_amount' => floatval($ret['refund_amount']),
            'refund_method' => $ret['refund_method'],
            'return_reason' => $first_item['return_reason'],
            'condition' => $first_item['condition'],
            'restock_status' => (in_array($first_item['condition'], ['Good / Resalable', 'Resellable', 'Unopened']) && (int)$first_item['product_id'] > 0) ? 'RESTOCKED' : 'NOT_RESTOCKED',
            'requested_by' => $ret['requested_by_name'] ?: 'Cashier',
            'approved_by' => $current_user_name,
            'approver_role' => $user_role,
            'approver_remarks' => $approver_remarks,
            'supplier_name' => $supplier_info['supplier_name'],
            'supplier_address' => $supplier_info['supplier_address'],
            'supplier_phone' => $supplier_info['supplier_phone'],
            'items' => $items
        ];

        echo json_encode([
            'status' => 'success',
            'status_code' => 'COMPLETED',
            'message' => 'Return request approved and finalized successfully. Refund of ₱' . number_format($ret['refund_amount'], 2) . ' authorized.',
            'return_reference' => $ret['return_reference'],
            'restocked_summary' => $restocked_summary,
            'receipt' => $receipt
        ]);
        exit;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode(['status' => 'error', 'message' => 'Failed to approve return: ' . $e->getMessage()]);
        exit;
    }
}

// -------------------------------------------------------------------------
// ACTION: REJECT RETURN (Manager/Admin Action)
// -------------------------------------------------------------------------
if ($action === 'reject_return') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
        exit;
    }

    if (!is_supplier_approver()) {
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized: Only Managers and Administrators can reject returns.']);
        exit;
    }

    $return_id = isset($_POST['return_id']) ? (int)$_POST['return_id'] : 0;
    $return_ref = isset($_POST['return_reference']) ? trim($_POST['return_reference']) : '';
    $rejection_reason = isset($_POST['rejection_reason']) ? trim($_POST['rejection_reason']) : '';

    if (empty($rejection_reason)) {
        echo json_encode(['status' => 'error', 'message' => 'Please provide a reason for rejecting this return request.']);
        exit;
    }

    if ($return_id <= 0 && empty($return_ref)) {
        echo json_encode(['status' => 'error', 'message' => 'Return ID or Reference is required.']);
        exit;
    }

    try {
        if ($return_id > 0) {
            $stmt = $pdo->prepare("SELECT * FROM tbl_returns WHERE return_id = ? AND supplier_id = ? FOR UPDATE");
            $stmt->execute(array($return_id, $supplier_id));
        } else {
            $stmt = $pdo->prepare("SELECT * FROM tbl_returns WHERE return_reference = ? AND supplier_id = ? FOR UPDATE");
            $stmt->execute(array($return_ref, $supplier_id));
        }
        $ret = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$ret) {
            echo json_encode(['status' => 'error', 'message' => 'Return request not found or access denied.']);
            exit;
        }

        if ($ret['status'] !== 'PENDING_APPROVAL' && $ret['status'] !== 'Pending') {
            echo json_encode(['status' => 'error', 'message' => 'This return request is already finalized (Status: ' . $ret['status'] . ').']);
            exit;
        }

        $pdo->beginTransaction();

        // Update tbl_returns
        $stmt_up = $pdo->prepare("
            UPDATE tbl_returns SET 
                status = 'REJECTED',
                rejection_reason = ?,
                approver_id = ?,
                approver_name = ?,
                approver_role = ?,
                approver_remarks = ?,
                rejected_at = CURRENT_TIMESTAMP,
                updated_at = CURRENT_TIMESTAMP
            WHERE return_id = ? AND supplier_id = ?
        ");
        $stmt_up->execute(array(
            $rejection_reason,
            $current_uid,
            $current_user_name,
            $user_role,
            $rejection_reason,
            $ret['return_id'],
            $supplier_id
        ));

        // Mark items as NOT_RESTOCKED
        $stmt_it_up = $pdo->prepare("UPDATE tbl_return_items SET restock_status = 'NOT_RESTOCKED' WHERE return_id = ?");
        $stmt_it_up->execute(array($ret['return_id']));

        $pdo->commit();

        echo json_encode([
            'status' => 'success',
            'message' => 'Return request has been rejected. No refund or inventory adjustments were made.',
            'return_reference' => $ret['return_reference']
        ]);
        exit;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode(['status' => 'error', 'message' => 'Failed to reject return request: ' . $e->getMessage()]);
        exit;
    }
}

// -------------------------------------------------------------------------
// ACTION: GET RETURN RECEIPT (For Reprint / Modal View)
// -------------------------------------------------------------------------
if ($action === 'get_return_receipt') {
    $return_id = isset($_GET['return_id']) ? (int)$_GET['return_id'] : 0;
    $return_ref = isset($_GET['return_reference']) ? trim($_GET['return_reference']) : '';

    if ($return_id <= 0 && empty($return_ref)) {
        echo json_encode(['status' => 'error', 'message' => 'Return ID or Reference is required.']);
        exit;
    }

    try {
        if ($return_id > 0) {
            $stmt = $pdo->prepare("SELECT * FROM tbl_returns WHERE return_id = ? AND supplier_id = ?");
            $stmt->execute(array($return_id, $supplier_id));
        } else {
            $stmt = $pdo->prepare("SELECT * FROM tbl_returns WHERE return_reference = ? AND supplier_id = ?");
            $stmt->execute(array($return_ref, $supplier_id));
        }
        $ret = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$ret) {
            echo json_encode(['status' => 'error', 'message' => 'Return record not found.']);
            exit;
        }

        $stmt_items = $pdo->prepare("SELECT * FROM tbl_return_items WHERE return_id = ?");
        $stmt_items->execute(array($ret['return_id']));
        $items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

        $first_item = !empty($items) ? $items[0] : [];

        $receipt = [
            'return_id' => $ret['return_id'],
            'return_reference' => $ret['return_reference'],
            'return_date' => $ret['return_date'],
            'approved_at' => $ret['approved_at'] ?: $ret['return_date'],
            'payment_id' => $ret['payment_id'],
            'status' => $ret['status'],
            'customer_name' => $ret['customer_name'] ?: 'Walk-in Customer',
            'customer_phone' => $ret['customer_phone'] ?: '',
            'customer_email' => $ret['customer_email'] ?: '',
            'product_name' => $first_item['product_name'] ?? 'Item',
            'sku' => $first_item['sku'] ?? '',
            'size' => $first_item['size'] ?? '',
            'color' => $first_item['color'] ?? '',
            'is_special_order' => (!empty($first_item['item_type']) && $first_item['item_type'] === 'SPECIAL_ORDER'),
            'special_order_reference' => $first_item['special_order_reference'] ?? '',
            'product_details' => $first_item['product_details'] ?? '',
            'unit_price' => floatval($first_item['unit_price'] ?? 0),
            'quantity_returned' => (int)($first_item['quantity_returned'] ?? 1),
            'refund_amount' => floatval($ret['refund_amount']),
            'refund_method' => $ret['refund_method'],
            'return_reason' => $first_item['return_reason'] ?? '',
            'condition' => $first_item['condition'] ?? '',
            'restock_status' => $first_item['restock_status'] ?? '',
            'requested_by' => $ret['requested_by_name'] ?: '',
            'cashier_name' => $ret['requested_by_name'] ?: '',
            'approved_by' => $ret['approver_name'] ?: '',
            'approver_name' => $ret['approver_name'] ?: '',
            'approver_role' => $ret['approver_role'] ?: '',
            'approver_remarks' => $ret['approver_remarks'] ?: '',
            'rejection_reason' => $ret['rejection_reason'] ?: '',
            'supplier_name' => $supplier_info['supplier_name'],
            'supplier_address' => $supplier_info['supplier_address'],
            'supplier_phone' => $supplier_info['supplier_phone'],
            'items' => $items
        ];

        echo json_encode([
            'status' => 'success',
            'receipt' => $receipt
        ]);
        exit;

    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'Failed to load receipt: ' . $e->getMessage()]);
        exit;
    }
}

// -------------------------------------------------------------------------
// ACTION: DELETE RETURN TRANSACTION LOGS (Admin Supplier Only)
// -------------------------------------------------------------------------
if (in_array($action, ['delete_return_logs', 'delete_return', 'delete_return_item', 'bulk_delete_returns'], true)) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
        exit;
    }

    // Role check: Admin supplier ONLY
    if (!can_user_delete_return($user_role_raw)) {
        echo json_encode([
            'status' => 'error',
            'error_code' => 'UNAUTHORIZED_ROLE',
            'message' => 'Unauthorized: Only Admin suppliers are permitted to delete returned transaction logs.'
        ]);
        exit;
    }

    // Parse return_item_ids
    $return_item_ids = [];
    if (isset($_POST['return_item_ids'])) {
        if (is_array($_POST['return_item_ids'])) {
            $return_item_ids = array_filter(array_map('intval', $_POST['return_item_ids']));
        } else {
            $return_item_ids = array_filter(array_map('intval', explode(',', (string)$_POST['return_item_ids'])));
        }
    }
    if (isset($_POST['return_item_id']) && (int)$_POST['return_item_id'] > 0) {
        $return_item_ids[] = (int)$_POST['return_item_id'];
    }
    $return_item_ids = array_values(array_unique(array_filter($return_item_ids, function($v) { return $v > 0; })));

    // Parse return_ids
    $return_ids = [];
    if (isset($_POST['return_ids'])) {
        if (is_array($_POST['return_ids'])) {
            $return_ids = array_filter(array_map('intval', $_POST['return_ids']));
        } else {
            $return_ids = array_filter(array_map('intval', explode(',', (string)$_POST['return_ids'])));
        }
    }
    if (isset($_POST['return_id']) && (int)$_POST['return_id'] > 0) {
        $return_ids[] = (int)$_POST['return_id'];
    }
    $return_ids = array_values(array_unique(array_filter($return_ids, function($v) { return $v > 0; })));

    if (empty($return_item_ids) && empty($return_ids)) {
        echo json_encode(['status' => 'error', 'message' => 'No return transaction logs were selected for deletion.']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        $affected_return_ids = [];
        $deleted_item_count = 0;
        $deleted_return_count = 0;

        // 1. If return_item_ids provided, delete matching return items for this supplier
        if (!empty($return_item_ids)) {
            $in_items = implode(',', array_fill(0, count($return_item_ids), '?'));
            $sql_find = "
                SELECT COALESCE(ri.return_item_id, ri.item_id, 0) AS item_pk, ri.return_id, ri.refund_amount, ri.quantity_returned, r.return_reference
                FROM tbl_return_items ri
                JOIN tbl_returns r ON ri.return_id = r.return_id
                WHERE (ri.return_item_id IN ($in_items) OR ri.item_id IN ($in_items))
                  AND r.supplier_id = ?
            ";
            $find_params = array_merge($return_item_ids, $return_item_ids, [$supplier_id]);
            $stmt_find = $pdo->prepare($sql_find);
            $stmt_find->execute($find_params);
            $matched_items = $stmt_find->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($matched_items)) {
                $matched_pks = [];
                foreach ($matched_items as $mi) {
                    $matched_pks[] = (int)$mi['item_pk'];
                    $affected_return_ids[] = (int)$mi['return_id'];
                }
                $matched_pks = array_unique(array_filter($matched_pks));

                if (!empty($matched_pks)) {
                    $in_pks = implode(',', array_fill(0, count($matched_pks), '?'));
                    $stmt_del_items = $pdo->prepare("DELETE FROM tbl_return_items WHERE return_item_id IN ($in_pks) OR item_id IN ($in_pks)");
                    $stmt_del_items->execute(array_merge($matched_pks, $matched_pks));
                    $deleted_item_count += count($matched_pks);
                }
            }
        }

        // 2. If entire return_ids provided, delete all their items and the return records
        if (!empty($return_ids)) {
            $in_rets = implode(',', array_fill(0, count($return_ids), '?'));
            $stmt_val_ret = $pdo->prepare("SELECT return_id FROM tbl_returns WHERE return_id IN ($in_rets) AND supplier_id = ?");
            $stmt_val_ret->execute(array_merge($return_ids, [$supplier_id]));
            $valid_return_ids = $stmt_val_ret->fetchAll(PDO::FETCH_COLUMN);

            if (!empty($valid_return_ids)) {
                $in_valid = implode(',', array_fill(0, count($valid_return_ids), '?'));
                // Delete child items
                $stmt_del_all_items = $pdo->prepare("DELETE FROM tbl_return_items WHERE return_id IN ($in_valid)");
                $stmt_del_all_items->execute($valid_return_ids);
                // Delete parent return
                $stmt_del_rets = $pdo->prepare("DELETE FROM tbl_returns WHERE return_id IN ($in_valid) AND supplier_id = ?");
                $stmt_del_rets->execute(array_merge($valid_return_ids, [$supplier_id]));
                $deleted_return_count += count($valid_return_ids);
                $affected_return_ids = array_diff($affected_return_ids, $valid_return_ids);
            }
        }

        // 3. For any remaining parent return_ids from item deletion, check if they have items left
        $affected_return_ids = array_values(array_unique(array_filter($affected_return_ids)));
        foreach ($affected_return_ids as $p_ret_id) {
            $stmt_chk_rem = $pdo->prepare("SELECT COUNT(*) as item_cnt, COALESCE(SUM(refund_amount), 0) as sum_refund FROM tbl_return_items WHERE return_id = ?");
            $stmt_chk_rem->execute(array($p_ret_id));
            $rem_data = $stmt_chk_rem->fetch(PDO::FETCH_ASSOC);

            if ((int)$rem_data['item_cnt'] === 0) {
                // Delete orphan parent return
                $stmt_del_parent = $pdo->prepare("DELETE FROM tbl_returns WHERE return_id = ? AND supplier_id = ?");
                $stmt_del_parent->execute(array($p_ret_id, $supplier_id));
                $deleted_return_count++;
            } else {
                // Update parent return refund amount
                $stmt_up_parent = $pdo->prepare("UPDATE tbl_returns SET refund_amount = ?, updated_at = CURRENT_TIMESTAMP WHERE return_id = ? AND supplier_id = ?");
                $stmt_up_parent->execute(array($rem_data['sum_refund'], $p_ret_id, $supplier_id));
            }
        }

        if ($deleted_item_count === 0 && $deleted_return_count === 0) {
            $pdo->rollBack();
            echo json_encode([
                'status' => 'error',
                'message' => 'No matching return log records were found or you do not have permission to delete them.'
            ]);
            exit;
        }

        $pdo->commit();

        echo json_encode([
            'status' => 'success',
            'message' => 'Selected returned transaction log(s) successfully deleted.',
            'deleted_item_count' => $deleted_item_count,
            'deleted_return_count' => $deleted_return_count
        ]);
        exit;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode(['status' => 'error', 'message' => 'Failed to delete return log(s): ' . $e->getMessage()]);
        exit;
    }
}

// Fallback for unrecognized action
echo json_encode(['status' => 'error', 'message' => 'Unrecognized API action: ' . htmlspecialchars($action)]);
exit;

