<?php
ob_start();
session_start();
require_once('inc/config.php');

header('Content-Type: application/json; charset=utf-8');

// Supplier Authentication
if (!isset($_SESSION['supplier_user']) || empty($_SESSION['supplier_user']['supplier_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access. Please log in to your supplier account.']);
    exit;
}

$supplier_id = (int)$_SESSION['supplier_user']['supplier_id'];
$cashier_name = !empty($_SESSION['supplier_user']['full_name']) ? $_SESSION['supplier_user']['full_name'] : 'Supplier Cashier';

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

// Helper: Generate Unique Return Reference
function generateUniqueReturnReference($pdo) {
    $date_prefix = date('Ymd');
    for ($i = 0; $i < 10; $i++) {
        $rand_suffix = strtoupper(substr(uniqid(), -4));
        $ref = 'RET-' . $date_prefix . '-' . $rand_suffix;
        $check = $pdo->prepare("SELECT return_id FROM tbl_returns WHERE return_reference = ?");
        $check->execute(array($ref));
        if (!$check->fetch()) {
            return $ref;
        }
    }
    return 'RET-' . $date_prefix . '-' . rand(1000, 9999);
}

// -------------------------------------------------------------------------
// ACTION: SEARCH ORDERS
// -------------------------------------------------------------------------
if ($action === 'search_orders') {
    $keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';
    
    try {
        if ($keyword !== '') {
            $search_param = '%' . $keyword . '%';
            $sql = "SELECT DISTINCT p.* 
                    FROM tbl_payment p
                    LEFT JOIN tbl_order o ON p.payment_id = o.payment_id
                    LEFT JOIN tbl_product prod ON o.product_id = prod.p_id
                    WHERE p.supplier_id = ?
                      AND (
                          p.payment_id ILIKE ? OR 
                          p.txnid ILIKE ? OR 
                          p.customer_name ILIKE ? OR 
                          p.customer_phone ILIKE ? OR 
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
            $sql = "SELECT * FROM tbl_payment WHERE supplier_id = ? ORDER BY id DESC LIMIT 20";
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

            foreach ($items as $item) {
                $order_item_id = (int)$item['id'];
                $purchased_qty = max(1, (int)$item['quantity']);
                $unit_price = floatval(preg_replace('/[^0-9.]/', '', strval($item['unit_price'])));

                // Calculate Previously Returned Quantity for this line item
                $stmt_ret = $pdo->prepare("SELECT COALESCE(SUM(quantity_returned), 0) AS total_ret FROM tbl_return_items WHERE order_item_id = ?");
                $stmt_ret->execute(array($order_item_id));
                $prev_ret_row = $stmt_ret->fetch(PDO::FETCH_ASSOC);
                $previously_returned = $prev_ret_row ? (int)$prev_ret_row['total_ret'] : 0;

                $available_to_return = max(0, $purchased_qty - $previously_returned);
                if ($available_to_return > 0) {
                    $order_has_returnable = true;
                }

                $total_purchased_units += $purchased_qty;
                $total_returned_units += $previously_returned;

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
                    'available_to_return' => $available_to_return,
                    'is_fully_returned' => ($available_to_return <= 0),
                    'unit_price' => $unit_price,
                    'line_total' => round($unit_price * $purchased_qty, 2)
                ];
            }

            // Fetch any existing returns for this payment_id
            $stmt_all_ret = $pdo->prepare("SELECT return_id, return_reference, return_date, refund_method, refund_amount, status 
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
                'customer_phone' => $payment['customer_phone'] ?: 'N/A',
                'customer_email' => $payment['customer_email'] ?: '',
                'items' => $processed_items,
                'items_count' => count($processed_items),
                'total_purchased_units' => $total_purchased_units,
                'total_returned_units' => $total_returned_units,
                'has_returnable_items' => $order_has_returnable,
                'is_all_fully_returned' => (!$order_has_returnable && count($processed_items) > 0 && $total_returned_units > 0),
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
        $stmt_pay = $pdo->prepare("SELECT * FROM tbl_payment WHERE payment_id = ? AND supplier_id = ?");
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

            $stmt_ret = $pdo->prepare("SELECT COALESCE(SUM(quantity_returned), 0) AS total_ret FROM tbl_return_items WHERE order_item_id = ?");
            $stmt_ret->execute(array($order_item_id));
            $prev_ret_row = $stmt_ret->fetch(PDO::FETCH_ASSOC);
            $previously_returned = $prev_ret_row ? (int)$prev_ret_row['total_ret'] : 0;

            $available_to_return = max(0, $purchased_qty - $previously_returned);
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
                'customer_phone' => $payment['customer_phone'] ?: 'N/A',
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
// ACTION: PROCESS RETURN
// -------------------------------------------------------------------------
if ($action === 'process_return') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
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

    // Basic Form Validation
    if ($order_item_id <= 0 || empty($payment_id)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid order item or transaction reference.']);
        exit;
    }
    if ($return_qty < 1) {
        echo json_encode(['status' => 'error', 'message' => 'Return quantity must be at least 1.']);
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
            SELECT o.*, p.payment_date, p.payment_method as orig_payment_method, p.customer_id, p.customer_name, p.customer_email, p.customer_phone, prod.p_sku
            FROM tbl_order o
            JOIN tbl_payment p ON o.payment_id = p.payment_id
            LEFT JOIN tbl_product prod ON o.product_id = prod.p_id
            WHERE o.id = ? AND o.payment_id = ? AND o.supplier_id = ? AND p.supplier_id = ?
        ");
        $stmt_check->execute(array($order_item_id, $payment_id, $supplier_id, $supplier_id));
        $order_row = $stmt_check->fetch(PDO::FETCH_ASSOC);

        if (!$order_row) {
            echo json_encode(['status' => 'error', 'message' => 'Transaction line item not found or unauthorized for this supplier.']);
            exit;
        }

        // Calculate Previously Returned Quantity
        $stmt_prev = $pdo->prepare("SELECT COALESCE(SUM(quantity_returned), 0) AS total_ret FROM tbl_return_items WHERE order_item_id = ?");
        $stmt_prev->execute(array($order_item_id));
        $prev_ret_row = $stmt_prev->fetch(PDO::FETCH_ASSOC);
        $already_returned = $prev_ret_row ? (int)$prev_ret_row['total_ret'] : 0;

        $purchased_qty = max(1, (int)$order_row['quantity']);
        $available_to_return = max(0, $purchased_qty - $already_returned);

        if ($available_to_return <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'This item has already been fully returned. No further returns are allowed.']);
            exit;
        }

        if ($return_qty > $available_to_return) {
            echo json_encode([
                'status' => 'error',
                'message' => "Return quantity ({$return_qty}) exceeds the available returnable quantity ({$available_to_return})."
            ]);
            exit;
        }

        // Authoritative Unit Price & Refund Amount Calculation
        $unit_price = floatval(preg_replace('/[^0-9.]/', '', strval($order_row['unit_price'])));
        $refund_amount = round($unit_price * $return_qty, 2);

        // Determine Restock Status & Inventory Rules
        $is_resellable = in_array($condition, ['Resellable', 'Unopened'], true);
        $is_special_order = ($order_row['item_type'] === 'SPECIAL_ORDER' || (int)$order_row['product_id'] === 0);
        $product_id = (int)$order_row['product_id'];

        if ($is_resellable && !$is_special_order && $product_id > 0) {
            $restock_status = 'RESTOCKED';
        } else {
            $restock_status = 'NOT_RESTOCKED';
        }

        // Generate Return Reference and Date
        $return_reference = generateUniqueReturnReference($pdo);
        $return_date = date('Y-m-d H:i:s');

        $customer_id = (int)$order_row['customer_id'];
        $customer_name = $order_row['customer_name'] ?: 'Walk-in Customer';
        $customer_email = $order_row['customer_email'] ?: '';
        $customer_phone = $order_row['customer_phone'] ?: '';
        $sku = !empty($order_row['p_sku']) ? $order_row['p_sku'] : ($is_special_order ? ($order_row['special_order_reference'] ?: 'SO-ITEM') : ('SKU-' . str_pad($product_id, 5, '0', STR_PAD_LEFT)));

        // BEGIN ATOMIC DATABASE TRANSACTION
        $pdo->beginTransaction();

        // 1. Insert into tbl_returns
        $stmt_ret_insert = $pdo->prepare("
            INSERT INTO tbl_returns (
                return_reference,
                payment_id,
                order_id,
                supplier_id,
                customer_id,
                customer_name,
                customer_email,
                customer_phone,
                return_date,
                refund_method,
                refund_amount,
                status,
                processed_by,
                notes
            ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?) RETURNING return_id
        ");
        $stmt_ret_insert->execute(array(
            $return_reference,
            $payment_id,
            $order_item_id,
            $supplier_id,
            $customer_id,
            $customer_name,
            $customer_email,
            $customer_phone,
            $return_date,
            $refund_method,
            $refund_amount,
            'COMPLETED',
            $cashier_name,
            $general_notes
        ));

        $ret_row = $stmt_ret_insert->fetch(PDO::FETCH_ASSOC);
        $return_id = (int)$ret_row['return_id'];

        // 2. Insert into tbl_return_items
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
                notes
            ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
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
            $restock_status,
            $general_notes
        ));

        // 3. Update Inventory for Resellable Catalogue Products
        if ($restock_status === 'RESTOCKED' && $product_id > 0) {
            $stmt_stock = $pdo->prepare("UPDATE tbl_product SET p_qty = p_qty + ? WHERE p_id = ? AND supplier_id = ?");
            $stmt_stock->execute(array($return_qty, $product_id, $supplier_id));
        }

        // COMMIT TRANSACTION
        $pdo->commit();

        // Calculate remaining returnable after this transaction
        $new_total_returned = $already_returned + $return_qty;
        $new_remaining = max(0, $purchased_qty - $new_total_returned);

        // Prepare Receipt Data
        $receipt = [
            'return_id' => $return_id,
            'return_reference' => $return_reference,
            'return_date' => $return_date,
            'payment_id' => $payment_id,
            'customer_name' => $customer_name,
            'customer_phone' => $customer_phone,
            'customer_email' => $customer_email,
            'product_name' => $order_row['product_name'],
            'sku' => $sku,
            'size' => $order_row['size'],
            'color' => $order_row['color'],
            'is_special_order' => $is_special_order,
            'special_order_reference' => $order_row['special_order_reference'],
            'product_details' => $order_row['product_details'],
            'unit_price' => $unit_price,
            'quantity_returned' => $return_qty,
            'refund_amount' => $refund_amount,
            'refund_method' => $refund_method,
            'return_reason' => $final_reason,
            'condition' => $condition,
            'restock_status' => $restock_status,
            'processed_by' => $cashier_name,
            'supplier_name' => $supplier_info['supplier_name'],
            'supplier_address' => $supplier_info['supplier_address'],
            'supplier_phone' => $supplier_info['supplier_phone'],
            'purchased_qty' => $purchased_qty,
            'previous_returned' => $already_returned,
            'total_returned' => $new_total_returned,
            'remaining_returnable' => $new_remaining
        ];

        echo json_encode([
            'status' => 'success',
            'message' => 'Return processed successfully.',
            'return_reference' => $return_reference,
            'receipt' => $receipt
        ]);
        exit;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode(['status' => 'error', 'message' => 'Failed to process return: ' . $e->getMessage()]);
        exit;
    }
}

// Fallback for unrecognized action
echo json_encode(['status' => 'error', 'message' => 'Unrecognized API action.']);
exit;
