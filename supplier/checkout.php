<?php require_once('header.php'); ?>

<?php
$user_role_raw = isset($_SESSION['supplier_user']['role']) ? $_SESSION['supplier_user']['role'] : 'USER';
$user_role = normalize_supplier_role($user_role_raw);

// Server-side Route Barrier: Restrict to Order Processing Staff, Operator, Admin, and Manager
if (!is_order_processing_or_operator_role($user_role) && !is_admin_or_manager_role($user_role)) {
    header('location: pos.php');
    exit;
}

$supplier_id = (int)$_SESSION['supplier_user']['supplier_id'];
$user_id = (int)$_SESSION['supplier_user']['id'];
$user_name = !empty($_SESSION['supplier_user']['full_name']) ? $_SESSION['supplier_user']['full_name'] : 'Supplier Staff';
$role_display = get_role_display_name($user_role);

$statement_s = $pdo->prepare("SELECT supplier_name, supplier_phone, supplier_email, supplier_address FROM tbl_supplier WHERE supplier_id = ?");
$statement_s->execute(array($supplier_id));
$supplier_info = $statement_s->fetch(PDO::FETCH_ASSOC);
$supplier_name = !empty($supplier_info['supplier_name']) ? $supplier_info['supplier_name'] : 'eConstruction Supplier Store';

// Fetch registered customers and barangays for form selection/modification
$stmt_cust = $pdo->prepare("SELECT cust_id, cust_name, cust_email, cust_phone, cust_address FROM tbl_customer WHERE cust_status = 1 ORDER BY cust_name ASC");
$stmt_cust->execute();
$registered_customers = $stmt_cust->fetchAll(PDO::FETCH_ASSOC);

$stmt_brgy = $pdo->query("SELECT brgy_id, brgy_name FROM tbl_brgy ORDER BY brgy_name ASC");
$brgy_list = $stmt_brgy->fetchAll(PDO::FETCH_ASSOC);

$order_error = '';
$order_success_receipt = null;

if (!function_exists('recalculate_supplier_checkout_cart')) {
    function recalculate_supplier_checkout_cart(&$checkout_cart, $pdo, $supplier_id) {
        if (!isset($checkout_cart['items']) || !is_array($checkout_cart['items']) || empty($checkout_cart['items'])) {
            $checkout_cart['items'] = [];
            $checkout_cart['gross_subtotal'] = 0.00;
            $checkout_cart['total_discount_savings'] = 0.00;
            $checkout_cart['net_subtotal'] = 0.00;
            $delivery_cost = !empty($checkout_cart['fulfillment']['delivery_cost']) ? floatval($checkout_cart['fulfillment']['delivery_cost']) : 0.00;
            $checkout_cart['grand_total'] = $delivery_cost;
            return;
        }

        $gross_sub = 0.00;
        $disc_savings = 0.00;
        $net_sub = 0.00;

        foreach ($checkout_cart['items'] as $idx => &$item) {
            $item_type = (isset($item['item_type']) && $item['item_type'] === 'SPECIAL_ORDER') ? 'SPECIAL_ORDER' : 'STANDARD';
            $p_qty = max(1, intval($item['qty']));
            $item['qty'] = $p_qty;

            if ($item_type === 'STANDARD') {
                $p_id = intval($item['id']);
                $stmt = $pdo->prepare("SELECT p_name, p_qty, p_current_price, p_featured_photo FROM tbl_product WHERE p_id = ? AND supplier_id = ?");
                $stmt->execute(array($p_id, $supplier_id));
                $p_row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($p_row) {
                    $item['price'] = floatval(preg_replace('/[^0-9.]/', '', $p_row['p_current_price']));
                    $item['stock'] = intval($p_row['p_qty']);
                    if (!empty($p_row['p_featured_photo'])) {
                        $item['photo'] = $p_row['p_featured_photo'];
                    }
                } else {
                    $item['stock'] = 0;
                }
            } else {
                $item['price'] = max(0, floatval($item['price']));
                $item['stock'] = 999999;
            }

            $line_gross = round(floatval($item['price']) * $p_qty, 2);
            $item['line_gross'] = $line_gross;
            $gross_sub += $line_gross;

            $disc_amt = 0.00;
            $disc_pct = isset($item['discount_percent']) ? floatval($item['discount_percent']) : 0.00;

            if ($disc_pct > 0) {
                $disc_amt = round($line_gross * ($disc_pct / 100), 2);
            } elseif (isset($item['discount_amount']) && floatval($item['discount_amount']) > 0) {
                $disc_amt = floatval($item['discount_amount']);
            }

            $item['discount_amount'] = $disc_amt;
            $item['discount_percent'] = $disc_pct;
            $disc_savings += $disc_amt;

            $line_net = max(0, $line_gross - $disc_amt);
            $item['line_net'] = $line_net;
            $net_sub += $line_net;
        }
        unset($item);

        $checkout_cart['gross_subtotal'] = $gross_sub;
        $checkout_cart['total_discount_savings'] = $disc_savings;
        $checkout_cart['net_subtotal'] = $net_sub;

        $delivery_cost = !empty($checkout_cart['fulfillment']['delivery_cost']) ? floatval($checkout_cart['fulfillment']['delivery_cost']) : 0.00;
        $checkout_cart['grand_total'] = $net_sub + $delivery_cost;
    }
}

// Read and normalize checkout cart from session
$checkout_cart = isset($_SESSION['supplier_checkout_cart']) ? $_SESSION['supplier_checkout_cart'] : null;

if (!$checkout_cart && isset($_SESSION['pos_cart']) && !empty($_SESSION['pos_cart'])) {
    $pos_items = $_SESSION['pos_cart'];
    $checkout_cart = [
        'supplier_id' => $supplier_id,
        'created_at' => date('Y-m-d H:i:s'),
        'customer' => [
            'customer_type' => 'walkin',
            'customer_id' => 0,
            'customer_name' => 'Walk-in Customer',
            'customer_email' => 'walkin@pos.local',
            'customer_phone' => '',
            'customer_address' => 'Over the Counter',
            'location_brgy_id' => 0,
            'brgy_name' => '',
            'is_location_active' => false
        ],
        'fulfillment' => [
            'delivery_type' => 'pickup',
            'delivery_cost' => 0.00
        ],
        'payment_method' => 'Cash (OTC)',
        'items' => $pos_items,
        'gross_subtotal' => 0,
        'total_discount_savings' => 0,
        'net_subtotal' => 0,
        'grand_total' => 0
    ];
}

if ($checkout_cart) {
    recalculate_supplier_checkout_cart($checkout_cart, $pdo, $supplier_id);
    $_SESSION['supplier_checkout_cart'] = $checkout_cart;
    if (!empty($checkout_cart['items'])) {
        $_SESSION['pos_cart'] = $checkout_cart['items'];
    } else {
        unset($_SESSION['pos_cart']);
    }
}

// Handle AJAX Cart Item Operations (Update Qty, Remove, Edit Special Order, Clear Cart)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && in_array($_POST['action'], ['checkout_update_qty', 'checkout_remove_item', 'checkout_edit_special_order', 'checkout_clear_cart'])) {
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json');

    $ajax_action = $_POST['action'];

    if ($ajax_action === 'checkout_update_qty') {
        $idx = isset($_POST['index']) ? intval($_POST['index']) : -1;
        $new_qty = isset($_POST['qty']) ? intval($_POST['qty']) : 1;

        if ($idx < 0 || !isset($checkout_cart['items'][$idx])) {
            echo json_encode(['status' => 'error', 'message' => 'Cart item not found.']);
            exit;
        }

        if ($new_qty < 1) {
            echo json_encode(['status' => 'error', 'message' => 'Quantity must be at least 1.']);
            exit;
        }

        $item = $checkout_cart['items'][$idx];
        $item_type = (isset($item['item_type']) && $item['item_type'] === 'SPECIAL_ORDER') ? 'SPECIAL_ORDER' : 'STANDARD';

        if ($item_type === 'STANDARD') {
            $p_id = intval($item['id']);
            $stmt = $pdo->prepare("SELECT p_qty FROM tbl_product WHERE p_id = ? AND supplier_id = ?");
            $stmt->execute(array($p_id, $supplier_id));
            $avail_stock = $stmt->fetchColumn();
            if ($avail_stock !== false && $new_qty > intval($avail_stock)) {
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Cannot update quantity: Only ' . intval($avail_stock) . ' units available in stock.'
                ]);
                exit;
            }
        }

        $checkout_cart['items'][$idx]['qty'] = $new_qty;
        recalculate_supplier_checkout_cart($checkout_cart, $pdo, $supplier_id);
        $_SESSION['supplier_checkout_cart'] = $checkout_cart;
        $_SESSION['pos_cart'] = $checkout_cart['items'];

        $updated_item = $checkout_cart['items'][$idx];

        echo json_encode([
            'status' => 'success',
            'message' => 'Quantity updated successfully.',
            'item' => [
                'index' => $idx,
                'qty' => $updated_item['qty'],
                'price' => floatval($updated_item['price']),
                'line_gross' => floatval($updated_item['line_gross']),
                'discount_amount' => floatval($updated_item['discount_amount']),
                'discount_percent' => floatval($updated_item['discount_percent']),
                'line_net' => floatval($updated_item['line_net'])
            ],
            'summary' => [
                'cart_count' => count($checkout_cart['items']),
                'gross_subtotal' => floatval($checkout_cart['gross_subtotal']),
                'total_discount_savings' => floatval($checkout_cart['total_discount_savings']),
                'net_subtotal' => floatval($checkout_cart['net_subtotal']),
                'delivery_cost' => floatval($checkout_cart['fulfillment']['delivery_cost']),
                'grand_total' => floatval($checkout_cart['grand_total'])
            ]
        ]);
        exit;
    }

    if ($ajax_action === 'checkout_remove_item') {
        $idx = isset($_POST['index']) ? intval($_POST['index']) : -1;
        if ($idx < 0 || !isset($checkout_cart['items'][$idx])) {
            echo json_encode(['status' => 'error', 'message' => 'Cart item not found.']);
            exit;
        }

        array_splice($checkout_cart['items'], $idx, 1);
        $checkout_cart['items'] = array_values($checkout_cart['items']);
        recalculate_supplier_checkout_cart($checkout_cart, $pdo, $supplier_id);
        $_SESSION['supplier_checkout_cart'] = $checkout_cart;
        if (!empty($checkout_cart['items'])) {
            $_SESSION['pos_cart'] = $checkout_cart['items'];
        } else {
            unset($_SESSION['pos_cart']);
        }

        echo json_encode([
            'status' => 'success',
            'message' => 'Item removed from checkout cart.',
            'summary' => [
                'cart_count' => count($checkout_cart['items']),
                'gross_subtotal' => floatval($checkout_cart['gross_subtotal']),
                'total_discount_savings' => floatval($checkout_cart['total_discount_savings']),
                'net_subtotal' => floatval($checkout_cart['net_subtotal']),
                'delivery_cost' => floatval($checkout_cart['fulfillment']['delivery_cost']),
                'grand_total' => floatval($checkout_cart['grand_total'])
            ]
        ]);
        exit;
    }

    if ($ajax_action === 'checkout_edit_special_order') {
        $idx = isset($_POST['index']) ? intval($_POST['index']) : -1;
        if ($idx < 0 || !isset($checkout_cart['items'][$idx])) {
            echo json_encode(['status' => 'error', 'message' => 'Special order item not found.']);
            exit;
        }

        $so_name = isset($_POST['name']) ? trim($_POST['name']) : '';
        $so_price = isset($_POST['price']) ? max(0.01, floatval($_POST['price'])) : 0.00;
        $so_qty = isset($_POST['qty']) ? max(1, intval($_POST['qty'])) : 1;
        $so_ref = isset($_POST['special_order_reference']) ? trim($_POST['special_order_reference']) : '';
        $so_details = isset($_POST['product_details']) ? trim($_POST['product_details']) : '';

        if (empty($so_name)) {
            echo json_encode(['status' => 'error', 'message' => 'Item description/name is required.']);
            exit;
        }

        $checkout_cart['items'][$idx]['name'] = $so_name;
        $checkout_cart['items'][$idx]['base_name'] = $so_name;
        $checkout_cart['items'][$idx]['price'] = $so_price;
        $checkout_cart['items'][$idx]['qty'] = $so_qty;
        $checkout_cart['items'][$idx]['special_order_reference'] = $so_ref;
        $checkout_cart['items'][$idx]['product_details'] = $so_details;

        recalculate_supplier_checkout_cart($checkout_cart, $pdo, $supplier_id);
        $_SESSION['supplier_checkout_cart'] = $checkout_cart;
        $_SESSION['pos_cart'] = $checkout_cart['items'];

        echo json_encode([
            'status' => 'success',
            'message' => 'Special order item updated successfully.',
            'item' => $checkout_cart['items'][$idx],
            'summary' => [
                'cart_count' => count($checkout_cart['items']),
                'gross_subtotal' => floatval($checkout_cart['gross_subtotal']),
                'total_discount_savings' => floatval($checkout_cart['total_discount_savings']),
                'net_subtotal' => floatval($checkout_cart['net_subtotal']),
                'delivery_cost' => floatval($checkout_cart['fulfillment']['delivery_cost']),
                'grand_total' => floatval($checkout_cart['grand_total'])
            ]
        ]);
        exit;
    }

    if ($ajax_action === 'checkout_clear_cart') {
        $checkout_cart['items'] = [];
        recalculate_supplier_checkout_cart($checkout_cart, $pdo, $supplier_id);
        unset($_SESSION['supplier_checkout_cart']);
        unset($_SESSION['pos_cart']);

        echo json_encode([
            'status' => 'success',
            'message' => 'Cart has been cleared.',
            'summary' => [
                'cart_count' => 0,
                'gross_subtotal' => 0.00,
                'total_discount_savings' => 0.00,
                'net_subtotal' => 0.00,
                'delivery_cost' => 0.00,
                'grand_total' => 0.00
            ]
        ]);
        exit;
    }
}

// Handle Order / Purchase Order Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['form_otc']) || (isset($_POST['action']) && $_POST['action'] === 'process_checkout'))) {
    $cart_items = isset($checkout_cart['items']) ? $checkout_cart['items'] : [];

    if (empty($cart_items)) {
        $order_error = "Cannot process checkout: Your cart is empty.";
    } else {
        $customer_type = isset($_POST['customer_type']) ? $_POST['customer_type'] : 'walkin';
        $customer_id = 0;
        $customer_name = 'Walk-in Customer';
        $customer_email = 'walkin@pos.local';
        $customer_phone = '';
        $customer_address = 'Over the Counter';

        $is_location_active = isset($_POST['is_location_delivery']) && $_POST['is_location_delivery'] == '1';
        $location_brgy_id = isset($_POST['location_brgy_id']) ? intval($_POST['location_brgy_id']) : 0;
        $brgy_name = '';

        if ($is_location_active && $location_brgy_id > 0) {
            try {
                $stmt_b = $pdo->prepare("SELECT brgy_name FROM tbl_brgy WHERE brgy_id=?");
                $stmt_b->execute(array($location_brgy_id));
                $brgy_row = $stmt_b->fetch(PDO::FETCH_ASSOC);
                if ($brgy_row) {
                    $brgy_name = $brgy_row['brgy_name'];
                }
            } catch (Exception $e) {
                $brgy_name = '';
            }
        }

        $cust_row = null;
        if ($customer_type === 'registered' && !empty($_POST['registered_cust_id'])) {
            $c_id = intval($_POST['registered_cust_id']);
            $statement_c = $pdo->prepare("SELECT * FROM tbl_customer WHERE cust_id=?");
            $statement_c->execute(array($c_id));
            $cust_row = $statement_c->fetch(PDO::FETCH_ASSOC);
            if ($cust_row) {
                $customer_id = $cust_row['cust_id'];
                $customer_name = $cust_row['cust_name'];
                $customer_email = $cust_row['cust_email'];
                $customer_phone = $cust_row['cust_phone'];
                $customer_address = $cust_row['cust_address'] ?: 'Registered Address';
                if ($brgy_name) {
                    $customer_address .= ' (Delivery: Brgy. ' . $brgy_name . ')';
                }
            }
        } else {
            if (!empty($_POST['walkin_name'])) {
                $customer_name = trim($_POST['walkin_name']);
            }
            if (!empty($_POST['walkin_phone'])) {
                $customer_phone = trim($_POST['walkin_phone']);
            }
            if (!empty($_POST['walkin_email'])) {
                $customer_email = trim($_POST['walkin_email']);
            }
            if ($brgy_name) {
                $customer_address = 'Barangay ' . $brgy_name . ', Santa Barbara, Iloilo';
            } elseif (!empty($_POST['walkin_address'])) {
                $customer_address = trim($_POST['walkin_address']);
            } else {
                $customer_address = 'Over the Counter';
            }
        }

        $payment_method = isset($_POST['payment_method']) ? $_POST['payment_method'] : 'Purchase Order (PO)';
        $delivery_type = $is_location_active ? 'delivery' : 'pickup';
        $delivery_cost = ($is_location_active && isset($_POST['delivery_cost'])) ? floatval($_POST['delivery_cost']) : 0.00;
        $order_notes = isset($_POST['order_notes']) ? trim($_POST['order_notes']) : '';
        $po_reference_input = isset($_POST['po_reference']) ? trim($_POST['po_reference']) : '';

        // Authoritatively Validate Cart Items against Tenant Database
        $has_pending_discount = false;
        $validated_items = [];
        $gross_subtotal = 0.00;
        $total_discount_savings = 0.00;
        $net_subtotal = 0.00;

        foreach ($cart_items as $item) {
            $item_type = (isset($item['item_type']) && $item['item_type'] === 'SPECIAL_ORDER') ? 'SPECIAL_ORDER' : 'STANDARD';
            $p_qty = max(1, intval($item['qty']));
            $p_price = max(0, floatval($item['price']));

            if ($item_type === 'STANDARD') {
                $p_id = intval($item['id']);
                // Strict Tenant Isolation: Only select product if it belongs to current supplier
                $stmt_pcheck = $pdo->prepare("SELECT p_name, p_current_price, p_featured_photo FROM tbl_product WHERE p_id = ? AND supplier_id = ?");
                $stmt_pcheck->execute(array($p_id, $supplier_id));
                $p_row = $stmt_pcheck->fetch(PDO::FETCH_ASSOC);
                if ($p_row) {
                    $p_price = floatval(preg_replace('/[^0-9.]/', '', $p_row['p_current_price']));
                    $item['photo'] = !empty($p_row['p_featured_photo']) ? $p_row['p_featured_photo'] : '';
                } else {
                    $order_error = "Security validation error: Product ID #{$p_id} does not belong to your supplier store.";
                    break;
                }
            }

            $line_gross = round($p_price * $p_qty, 2);
            $gross_subtotal += $line_gross;

            $disc_req_id = !empty($item['discount_request_id']) ? trim($item['discount_request_id']) : null;
            $disc_amt = 0.00;
            $disc_pct = 0.00;
            $disc_status = null;

            if ($disc_req_id) {
                $stmt_dr = $pdo->prepare("SELECT * FROM tbl_discount_requests WHERE request_id = ? AND supplier_id = ?");
                $stmt_dr->execute(array($disc_req_id, $supplier_id));
                $dr = $stmt_dr->fetch(PDO::FETCH_ASSOC);

                if (!$dr || in_array($dr['status'], ['PENDING', 'ESCALATED'])) {
                    $has_pending_discount = true;
                    $disc_status = $dr ? $dr['status'] : 'PENDING';
                } elseif (in_array($dr['status'], ['APPROVED', 'APPROVED_MODIFIED'])) {
                    $disc_pct = floatval($dr['approved_discount_percent']);
                    $disc_amt = round($line_gross * ($disc_pct / 100), 2);
                    $total_discount_savings += $disc_amt;
                    $disc_status = $dr['status'];
                }
            }

            $line_net = max(0, $line_gross - $disc_amt);
            $net_subtotal += $line_net;

            $item['authoritative_unit_price'] = $p_price;
            $item['line_gross'] = $line_gross;
            $item['discount_amount'] = $disc_amt;
            $item['discount_percent'] = $disc_pct;
            $item['line_net'] = $line_net;
            $item['discount_request_id'] = $disc_req_id;
            $item['discount_status'] = $disc_status;
            $validated_items[] = $item;
        }

        if (empty($order_error)) {
            if ($has_pending_discount) {
                $order_error = "Order cannot be processed: One or more item discount requests are pending approval. Please approve or cancel pending discounts before completing checkout.";
            } else {
                $grand_total = $net_subtotal + $delivery_cost;
                $payment_id = !empty($po_reference_input) ? $po_reference_input : ('PO-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5)));
                $payment_date = date('Y-m-d H:i:s');

                $tx_info = 'Purchase Order (Sent by ' . $role_display . ': ' . $user_name . ') | Method: ' . $payment_method;
                if ($total_discount_savings > 0) {
                    $tx_info .= ' | Total Discounts: -₱' . number_format($total_discount_savings, 2);
                }
                if (!empty($order_notes)) {
                    $tx_info .= ' | Notes: ' . $order_notes;
                }

                // Insert into tbl_payment with Awaiting for Payment status
                $statement_p = $pdo->prepare("INSERT INTO tbl_payment (
                    customer_id,
                    customer_name,
                    customer_email,
                    payment_date,
                    txnid,
                    paid_amount,
                    card_number,
                    card_cvv,
                    card_month,
                    card_year,
                    bank_transaction_info,
                    payment_method,
                    payment_status,
                    shipping_status,
                    payment_id,
                    supplier_id
                ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?) RETURNING id");

                $statement_p->execute(array(
                    $customer_id,
                    $customer_name,
                    $customer_email,
                    $payment_date,
                    $payment_id,
                    number_format($grand_total, 2, '.', ''),
                    '',
                    '',
                    '',
                    '',
                    $tx_info,
                    'Purchase Order (PO)',
                    'Awaiting for Payment',
                    'Pending',
                    $payment_id,
                    $supplier_id
                ));

                // Insert items into tbl_order and update stock
                foreach ($validated_items as $item) {
                    $item_type = (isset($item['item_type']) && $item['item_type'] === 'SPECIAL_ORDER') ? 'SPECIAL_ORDER' : 'STANDARD';
                    $p_qty = max(1, intval($item['qty']));
                    $p_orig_unit_price = max(0, floatval($item['authoritative_unit_price']));
                    $p_line_net = floatval($item['line_net']);
                    $p_effective_unit_price = ($p_qty > 0) ? round($p_line_net / $p_qty, 2) : $p_orig_unit_price;

                    if ($item_type === 'SPECIAL_ORDER') {
                        $p_id = 0;
                        $p_name = trim($item['name']);
                        $p_details = isset($item['product_details']) ? trim($item['product_details']) : '';
                        if (!empty($item['discount_amount']) && $item['discount_amount'] > 0) {
                            $p_details .= ($p_details ? ' | ' : '') . 'Discount: -₱' . number_format($item['discount_amount'], 2) . ' (' . number_format($item['discount_percent'], 1) . '%)';
                        }
                        $so_ref = !empty($item['special_order_reference']) ? trim($item['special_order_reference']) : ('SO-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4)));
                        $p_size = $p_details ? (strlen($p_details) > 95 ? substr($p_details, 0, 95) . '...' : $p_details) : 'Special Order';
                        $p_color = 'Special Order';

                        $statement_o = $pdo->prepare("INSERT INTO tbl_order (
                            product_id,
                            product_name,
                            size,
                            color,
                            quantity,
                            unit_price,
                            payment_id,
                            supplier_id,
                            item_type,
                            special_order_reference,
                            product_details
                        ) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
                        $statement_o->execute(array(
                            $p_id,
                            $p_name,
                            $p_size,
                            $p_color,
                            strval($p_qty),
                            strval($p_effective_unit_price),
                            $payment_id,
                            $supplier_id,
                            $item_type,
                            $so_ref,
                            $p_details
                        ));
                    } else {
                        $p_id = intval($item['id']);
                        $p_name = trim($item['name']);
                        $p_size = !empty($item['size']) ? $item['size'] : (!empty($item['spec_label']) ? $item['spec_label'] : 'Standard');
                        $p_color = !empty($item['color']) ? $item['color'] : 'Standard';

                        $statement_o = $pdo->prepare("INSERT INTO tbl_order (
                            product_id,
                            product_name,
                            size,
                            color,
                            quantity,
                            unit_price,
                            payment_id,
                            supplier_id,
                            item_type,
                            product_details
                        ) VALUES (?,?,?,?,?,?,?,?,?,?)");
                        $statement_o->execute(array(
                            $p_id,
                            $p_name,
                            $p_size,
                            $p_color,
                            strval($p_qty),
                            strval($p_effective_unit_price),
                            $payment_id,
                            $supplier_id,
                            $item_type,
                            !empty($item['spec_label']) ? $item['spec_label'] : ''
                        ));

                        // Decrement product inventory safely
                        try {
                            $statement_stock = $pdo->prepare("UPDATE tbl_product SET p_qty = GREATEST(0, p_qty - ?) WHERE p_id = ? AND supplier_id = ?");
                            $statement_stock->execute(array($p_qty, $p_id, $supplier_id));
                        } catch (Exception $e) {}
                    }

                    // Link discount request to payment_id
                    if (!empty($item['discount_request_id'])) {
                        try {
                            $stmt_up_dr = $pdo->prepare("UPDATE tbl_discount_requests SET payment_id = ?, updated_at = CURRENT_TIMESTAMP WHERE request_id = ? AND supplier_id = ?");
                            $stmt_up_dr->execute(array($payment_id, $item['discount_request_id'], $supplier_id));
                        } catch (Exception $e) {}
                    }
                }

                // Setup customer checkout session state
                $_SESSION['cart_p_id'] = [];
                $_SESSION['cart_size_id'] = [];
                $_SESSION['cart_size_name'] = [];
                $_SESSION['cart_color_id'] = [];
                $_SESSION['cart_color_name'] = [];
                $_SESSION['cart_p_qty'] = [];
                $_SESSION['cart_p_current_price'] = [];
                $_SESSION['cart_p_name'] = [];
                $_SESSION['cart_p_featured_photo'] = [];

                $idx = 0;
                foreach ($validated_items as $v_item) {
                    $idx++;
                    $v_id = (isset($v_item['item_type']) && $v_item['item_type'] === 'SPECIAL_ORDER') ? 0 : intval($v_item['id']);
                    $v_name = trim($v_item['name']);
                    $v_size = !empty($v_item['size']) ? $v_item['size'] : (!empty($v_item['spec_label']) ? $v_item['spec_label'] : 'Standard');
                    $v_color = !empty($v_item['color']) ? $v_item['color'] : 'Standard';
                    $v_qty = max(1, intval($v_item['qty']));
                    $v_price = floatval($v_item['authoritative_unit_price']);
                    if (!empty($v_item['line_net']) && $v_qty > 0) {
                        $v_price = round(floatval($v_item['line_net']) / $v_qty, 2);
                    }
                    $v_photo = !empty($v_item['photo']) ? $v_item['photo'] : 'no-image.jpg';

                    $_SESSION['cart_p_id'][$idx] = $v_id;
                    $_SESSION['cart_size_id'][$idx] = 0;
                    $_SESSION['cart_size_name'][$idx] = $v_size;
                    $_SESSION['cart_color_id'][$idx] = 0;
                    $_SESSION['cart_color_name'][$idx] = $v_color;
                    $_SESSION['cart_p_qty'][$idx] = $v_qty;
                    $_SESSION['cart_p_current_price'][$idx] = $v_price;
                    $_SESSION['cart_p_name'][$idx] = $v_name;
                    $_SESSION['cart_p_featured_photo'][$idx] = $v_photo;
                }

                if ($customer_type === 'registered' && !empty($cust_row)) {
                    $_SESSION['customer'] = $cust_row;
                    if (empty($_SESSION['customer']['cust_b_name'])) $_SESSION['customer']['cust_b_name'] = $cust_row['cust_name'];
                    if (empty($_SESSION['customer']['cust_b_phone'])) $_SESSION['customer']['cust_b_phone'] = $cust_row['cust_phone'];
                    if (empty($_SESSION['customer']['cust_b_address'])) $_SESSION['customer']['cust_b_address'] = $cust_row['cust_address'];
                    if (empty($_SESSION['customer']['cust_b_country'])) $_SESSION['customer']['cust_b_country'] = 1;
                    if (empty($_SESSION['customer']['cust_b_zip'])) $_SESSION['customer']['cust_b_zip'] = '5000';
                    if (empty($_SESSION['customer']['cust_b_cname'])) $_SESSION['customer']['cust_b_cname'] = 'Registered';
                    if (!empty($brgy_name)) $_SESSION['customer']['cust_b_state'] = $brgy_name;

                    if (empty($_SESSION['customer']['cust_s_name'])) $_SESSION['customer']['cust_s_name'] = $cust_row['cust_name'];
                    if (empty($_SESSION['customer']['cust_s_phone'])) $_SESSION['customer']['cust_s_phone'] = $cust_row['cust_phone'];
                    if (empty($_SESSION['customer']['cust_s_address'])) $_SESSION['customer']['cust_s_address'] = $cust_row['cust_address'];
                    if (empty($_SESSION['customer']['cust_s_country'])) $_SESSION['customer']['cust_s_country'] = 1;
                    if (empty($_SESSION['customer']['cust_s_zip'])) $_SESSION['customer']['cust_s_zip'] = '5000';
                    if (empty($_SESSION['customer']['cust_s_cname'])) $_SESSION['customer']['cust_s_cname'] = 'Registered';
                    if (!empty($brgy_name)) $_SESSION['customer']['cust_s_state'] = $brgy_name;
                } else {
                    $_SESSION['customer'] = [
                        'cust_id' => 0,
                        'cust_name' => $customer_name,
                        'cust_email' => $customer_email ?: 'walkin@pos.local',
                        'cust_phone' => $customer_phone ?: '09000000000',
                        'cust_address' => $customer_address,
                        'cust_country' => 1,
                        'cust_city' => 'Santa Barbara',
                        'cust_state' => $brgy_name,
                        'cust_zip' => '5000',
                        'cust_b_name' => $customer_name,
                        'cust_b_cname' => 'Walk-in',
                        'cust_b_phone' => $customer_phone ?: '09000000000',
                        'cust_b_country' => 1,
                        'cust_b_address' => $customer_address,
                        'cust_b_city' => 'Santa Barbara',
                        'cust_b_state' => $brgy_name,
                        'cust_b_zip' => '5000',
                        'cust_s_name' => $customer_name,
                        'cust_s_cname' => 'Walk-in',
                        'cust_s_phone' => $customer_phone ?: '09000000000',
                        'cust_s_country' => 1,
                        'cust_s_address' => $customer_address,
                        'cust_s_city' => 'Santa Barbara',
                        'cust_s_state' => $brgy_name,
                        'cust_s_zip' => '5000'
                    ];
                }

                // Store PO Success details for POS Modal & Receipt Printing
                $_SESSION['pos_po_success'] = [
                    'po_id' => $payment_id,
                    'payment_date' => $payment_date,
                    'paid_amount' => $grand_total,
                    'customer_name' => $customer_name,
                    'customer_phone' => $customer_phone,
                    'customer_email' => $customer_email,
                    'customer_address' => $customer_address,
                    'payment_method' => $payment_method,
                    'items' => $validated_items,
                    'gross_subtotal' => $gross_subtotal,
                    'total_discount_savings' => $total_discount_savings,
                    'net_subtotal' => $net_subtotal,
                    'delivery_cost' => $delivery_cost,
                    'grand_total' => $grand_total,
                    'supplier_name' => $supplier_name,
                    'supplier_phone' => !empty($supplier_info['supplier_phone']) ? $supplier_info['supplier_phone'] : '',
                    'supplier_address' => !empty($supplier_info['supplier_address']) ? $supplier_info['supplier_address'] : ''
                ];

                // Clear staff POS session cart ONLY on successful PO creation
                unset($_SESSION['supplier_checkout_cart']);
                unset($_SESSION['pos_cart']);

                // Redirect to pos.php (POS Terminal) with success flag
                header('location: pos.php?po_success=1&po_id=' . urlencode($payment_id));
                exit;
            }
        }
    }
}

// Extract cart variables for rendering checkout form
$cart_items = isset($checkout_cart['items']) ? $checkout_cart['items'] : [];
$cust_data = isset($checkout_cart['customer']) ? $checkout_cart['customer'] : [
    'customer_type' => 'walkin',
    'customer_id' => 0,
    'customer_name' => 'Walk-in Customer',
    'customer_phone' => '',
    'customer_email' => '',
    'customer_address' => 'Over the Counter',
    'is_location_active' => false,
    'location_brgy_id' => 0,
    'brgy_name' => ''
];
$fulfillment_data = isset($checkout_cart['fulfillment']) ? $checkout_cart['fulfillment'] : [
    'delivery_type' => 'pickup',
    'delivery_cost' => 0.00
];
$gross_subtotal = isset($checkout_cart['gross_subtotal']) ? floatval($checkout_cart['gross_subtotal']) : 0.00;
$total_discount_savings = isset($checkout_cart['total_discount_savings']) ? floatval($checkout_cart['total_discount_savings']) : 0.00;
$net_subtotal = isset($checkout_cart['net_subtotal']) ? floatval($checkout_cart['net_subtotal']) : 0.00;
$grand_total = isset($checkout_cart['grand_total']) ? floatval($checkout_cart['grand_total']) : 0.00;
?>

<section class="content-header">
    <div class="content-header-left">
        <h1 style="font-weight: 800; color: #1e293b; display: flex; align-items: center; gap: 10px;">
            <i class="fa fa-shopping-cart text-primary"></i> Supplier Order Checkout
            <span class="label label-primary" style="font-size: 13px; font-weight: 700; padding: 4px 10px; border-radius: 4px;">
                <?php echo htmlspecialchars($role_display); ?>
            </span>
        </h1>
        <div style="font-size: 13px; color: #64748b; margin-top: 4px;">
            Store: <strong><?php echo htmlspecialchars($supplier_name); ?></strong> | Staff: <strong><?php echo htmlspecialchars($user_name); ?></strong>
        </div>
    </div>
    <div class="content-header-right">
        <a href="pos.php" class="btn btn-default btn-sm" style="font-weight: 700;">
            <i class="fa fa-calculator"></i> Return to POS Terminal
        </a>
    </div>
</section>

<section class="content" style="padding-top: 10px;">

    <?php if ($order_success_receipt): ?>
        <!-- Success Receipt View -->
        <div class="box box-success" style="border-top-color: #10b981; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.08);">
            <div class="box-body" style="padding: 30px;">
                <div class="text-center" style="margin-bottom: 25px;">
                    <div style="font-size: 60px; color: #10b981; line-height: 1;"><i class="fa fa-check-circle"></i></div>
                    <h2 style="font-weight: 900; color: #065f46; margin-top: 10px;">Order & Purchase Order Processed Successfully!</h2>
                    <p style="font-size: 15px; color: #64748b;">
                        Reference / PO Number: <strong style="font-size: 18px; color: #1e3a8a; font-family: monospace; background: #e0f2fe; padding: 3px 10px; border-radius: 4px;"><?php echo htmlspecialchars($order_success_receipt['payment_id']); ?></strong>
                    </p>
                </div>

                <div id="checkoutPrintArea" style="max-width: 750px; margin: 0 auto; background: #fff; border: 1.5px dashed #cbd5e1; border-radius: 8px; padding: 25px;">
                    <div style="text-align: center; border-bottom: 2px solid #334155; padding-bottom: 12px; margin-bottom: 15px;">
                        <h3 style="margin: 0; font-weight: 900; font-size: 22px; color: #0f172a; text-transform: uppercase;"><?php echo htmlspecialchars($supplier_name); ?></h3>
                        <div style="font-size: 12px; color: #64748b; margin-top: 3px;">PURCHASE ORDER & SALES RECEIPT</div>
                        <div style="font-size: 11px; color: #94a3b8;"><?php echo htmlspecialchars($order_success_receipt['payment_date']); ?> | Staff: <?php echo htmlspecialchars($order_success_receipt['staff_name']); ?> (<?php echo htmlspecialchars($order_success_receipt['staff_role']); ?>)</div>
                    </div>

                    <div style="display: flex; justify-content: space-between; font-size: 13px; margin-bottom: 15px; background: #f8fafc; padding: 10px 14px; border-radius: 6px;">
                        <div>
                            <div><strong>Customer:</strong> <?php echo htmlspecialchars($order_success_receipt['customer_name']); ?></div>
                            <div><strong>Contact:</strong> <?php echo htmlspecialchars($order_success_receipt['customer_phone'] ?: 'N/A'); ?></div>
                        </div>
                        <div style="text-align: right;">
                            <div><strong>Payment Method:</strong> <?php echo htmlspecialchars($order_success_receipt['payment_method']); ?></div>
                            <div><strong>Fulfillment:</strong> <?php echo ucfirst(htmlspecialchars($order_success_receipt['delivery_type'])); ?></div>
                        </div>
                    </div>

                    <table class="table table-bordered table-condensed" style="margin-bottom: 15px;">
                        <thead>
                            <tr style="background: #f1f5f9; font-size: 12px;">
                                <th>Item / Description</th>
                                <th style="text-align: center; width: 60px;">Qty</th>
                                <th style="text-align: right; width: 100px;">Price</th>
                                <th style="text-align: right; width: 120px;">Total</th>
                            </tr>
                        </thead>
                        <tbody style="font-size: 13px;">
                            <?php foreach ($order_success_receipt['items'] as $it): 
                                $it_gross = floatval($it['authoritative_unit_price']) * intval($it['qty']);
                                $it_net = floatval($it['line_net']);
                                $it_disc = floatval($it['discount_amount']);
                            ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($it['name']); ?></strong>
                                        <?php if (!empty($it['spec_label']) && $it['spec_label'] !== 'Standard'): ?>
                                            <div style="font-size: 11px; color: #0284c7;"><?php echo htmlspecialchars($it['spec_label']); ?></div>
                                        <?php endif; ?>
                                        <?php if (!empty($it['product_details'])): ?>
                                            <div style="font-size: 11px; color: #475569;"><?php echo htmlspecialchars($it['product_details']); ?></div>
                                        <?php endif; ?>
                                        <?php if ($it_disc > 0): ?>
                                            <div style="font-size: 11px; color: #16a34a; font-weight: bold;">
                                                <i class="fa fa-tag"></i> Approved Discount -&#8369;<?php echo number_format($it_disc, 2); ?> (<?php echo number_format(floatval($it['discount_percent']), 1); ?>%)
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align: center; font-weight: bold;"><?php echo intval($it['qty']); ?></td>
                                    <td style="text-align: right;">&#8369;<?php echo number_format(floatval($it['authoritative_unit_price']), 2); ?></td>
                                    <td style="text-align: right; font-weight: bold;">&#8369;<?php echo number_format($it_net, 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot style="font-size: 13px;">
                            <tr>
                                <th colspan="3" style="text-align: right;">Gross Subtotal:</th>
                                <th style="text-align: right;">&#8369;<?php echo number_format($order_success_receipt['gross_subtotal'], 2); ?></th>
                            </tr>
                            <?php if ($order_success_receipt['total_discount_savings'] > 0): ?>
                            <tr>
                                <th colspan="3" style="text-align: right; color: #16a34a;">Total Discounts:</th>
                                <th style="text-align: right; color: #16a34a;">-&#8369;<?php echo number_format($order_success_receipt['total_discount_savings'], 2); ?></th>
                            </tr>
                            <?php endif; ?>
                            <?php if ($order_success_receipt['delivery_cost'] > 0): ?>
                            <tr>
                                <th colspan="3" style="text-align: right; color: #0284c7;">Delivery Fee:</th>
                                <th style="text-align: right; color: #0284c7;">+&#8369;<?php echo number_format($order_success_receipt['delivery_cost'], 2); ?></th>
                            </tr>
                            <?php endif; ?>
                            <tr style="background: #eff6ff; font-size: 16px;">
                                <th colspan="3" style="text-align: right; color: #1e3a8a;">Grand Total:</th>
                                <th style="text-align: right; color: #1d4ed8; font-weight: 900;">&#8369;<?php echo number_format($order_success_receipt['grand_total'], 2); ?></th>
                            </tr>
                        </tfoot>
                    </table>

                    <div style="text-align: center; margin-top: 15px; font-size: 11px; color: #94a3b8;">
                        Thank you for your business! | <?php echo htmlspecialchars($supplier_name); ?>
                    </div>
                </div>

                <div style="display: flex; justify-content: center; gap: 12px; margin-top: 25px;">
                    <button type="button" class="btn btn-primary btn-lg" onclick="printCheckoutReceipt()" style="font-weight: 700; border-radius: 6px; padding: 10px 24px;">
                        <i class="fa fa-print"></i> Print Purchase Order / Receipt
                    </button>
                    <a href="pos.php" class="btn btn-success btn-lg" style="font-weight: 700; border-radius: 6px; padding: 10px 24px;">
                        <i class="fa fa-calculator"></i> Return to POS Terminal
                    </a>
                </div>
            </div>
        </div>

    <?php elseif (empty($cart_items)): ?>
        <!-- Empty Cart Notice -->
        <div class="box box-warning" style="border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); text-align: center; padding: 50px 20px;">
            <div style="font-size: 60px; color: #f59e0b; margin-bottom: 15px;"><i class="fa fa-shopping-basket"></i></div>
            <h3 style="font-weight: 800; color: #1e293b; margin-top: 0;">Your Checkout Cart is Empty</h3>
            <p style="color: #64748b; font-size: 15px; max-width: 500px; margin: 0 auto 25px auto;">
                There are currently no items selected for checkout. Please return to the POS Terminal to add products, configure variants, and set customer details.
            </p>
            <a href="pos.php" class="btn btn-primary btn-lg" style="font-weight: 700; border-radius: 6px; padding: 12px 30px;">
                <i class="fa fa-calculator"></i> Open POS Terminal
            </a>
        </div>

    <?php else: ?>

        <?php if (!empty($order_error)): ?>
        <div class="alert alert-danger" style="font-size: 14px; font-weight: bold; border-radius: 6px; margin-bottom: 15px;">
            <i class="fa fa-exclamation-circle"></i> <?php echo htmlspecialchars($order_error); ?>
        </div>
        <?php endif; ?>

        <form method="POST" action="checkout.php" id="supplierCheckoutForm">
            <input type="hidden" name="action" value="process_checkout">

            <div class="row">
                <!-- Left Side: Order Items & Pricing Breakdown -->
                <div class="col-md-7">
                    <div class="box box-primary" style="border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.05);">
                        <div class="box-header with-border" style="background: #f8fafc; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; padding: 12px 15px;">
                            <h3 class="box-title" style="font-weight: 800; font-size: 16px; color: #1e293b; margin: 0;">
                                <i class="fa fa-list-alt text-primary"></i> Order Items & Cart Review (<span id="cartCountBadge"><?php echo count($cart_items); ?></span>)
                            </h3>
                            <div class="box-tools pull-right" style="position: static; display: flex; gap: 8px;">
                                <a href="pos.php" class="btn btn-default btn-xs" style="font-weight: 700; color: #0284c7; border-color: #cbd5e1; padding: 4px 10px;">
                                    <i class="fa fa-plus-circle"></i> Add More Items (POS)
                                </a>
                                <button type="button" class="btn btn-default btn-xs text-danger" onclick="clearCheckoutCart()" style="font-weight: 700; color: #dc2626; border-color: #fecaca; padding: 4px 10px;">
                                    <i class="fa fa-trash"></i> Clear Cart
                                </button>
                            </div>
                        </div>
                        <div class="box-body" style="padding: 0;">
                            <div class="table-responsive">
                                <table class="table table-hover table-striped" id="checkoutCartTable" style="margin-bottom: 0;">
                                    <thead>
                                        <tr style="background: #f1f5f9; font-size: 12px; color: #475569;">
                                            <th style="width: 44%;">Product Details</th>
                                            <th style="width: 14%; text-align: right;">Unit Price</th>
                                            <th style="width: 22%; text-align: center;">Qty</th>
                                            <th style="width: 14%; text-align: right;">Line Total</th>
                                            <th style="width: 6%; text-align: center;">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody id="checkoutCartTableBody">
                                        <?php 
                                        foreach ($cart_items as $idx => $item): 
                                            $is_special = (isset($item['item_type']) && $item['item_type'] === 'SPECIAL_ORDER');
                                            $price = floatval($item['price']);
                                            $qty = intval($item['qty']);
                                            $stock = isset($item['stock']) ? intval($item['stock']) : ($is_special ? 999999 : 0);
                                            
                                            // If stock not set or standard product, ensure we have stock from db
                                            if (!$is_special && (!isset($item['stock']) || $item['stock'] === 0)) {
                                                $stmt_st = $pdo->prepare("SELECT p_qty, p_featured_photo FROM tbl_product WHERE p_id = ? AND supplier_id = ?");
                                                $stmt_st->execute(array(intval($item['id']), $supplier_id));
                                                $st_row = $stmt_st->fetch(PDO::FETCH_ASSOC);
                                                if ($st_row) {
                                                    $stock = intval($st_row['p_qty']);
                                                    if (!empty($st_row['p_featured_photo'])) {
                                                        $item['photo'] = $st_row['p_featured_photo'];
                                                    }
                                                }
                                            }

                                            $line_gross = round($price * $qty, 2);
                                            $disc_amt = isset($item['discount_amount']) ? floatval($item['discount_amount']) : 0.00;
                                            $disc_pct = isset($item['discount_percent']) ? floatval($item['discount_percent']) : 0.00;
                                            $line_net = max(0, $line_gross - $disc_amt);
                                            $photo = !empty($item['photo']) ? ('../assets/uploads/' . htmlspecialchars($item['photo'])) : '';
                                        ?>
                                            <tr id="cartRow-<?php echo $idx; ?>" data-index="<?php echo $idx; ?>" data-is-special="<?php echo $is_special ? '1' : '0'; ?>">
                                                <td style="padding: 10px; vertical-align: middle;">
                                                    <div style="display: flex; gap: 10px; align-items: flex-start;">
                                                        <?php if (!empty($photo)): ?>
                                                            <img src="<?php echo $photo; ?>" alt="" style="width: 42px; height: 42px; object-fit: cover; border-radius: 4px; border: 1px solid #e2e8f0; flex-shrink: 0;" onerror="this.style.display='none'">
                                                        <?php else: ?>
                                                            <div style="width: 42px; height: 42px; background: #e2e8f0; border-radius: 4px; display: flex; align-items: center; justify-content: center; color: #94a3b8; font-size: 18px; flex-shrink: 0;">
                                                                <i class="fa <?php echo $is_special ? 'fa-star' : 'fa-cube'; ?>"></i>
                                                            </div>
                                                        <?php endif; ?>
                                                        <div style="flex-grow: 1;">
                                                            <?php if ($is_special): ?>
                                                                <span class="label label-warning" style="background: #d97706; font-size: 9px; font-weight: 800;">SPECIAL ORDER</span>
                                                                <div style="font-weight: 800; color: #0f172a; font-size: 13.5px; margin-top: 2px;" id="item-name-<?php echo $idx; ?>">
                                                                    <?php echo htmlspecialchars($item['name']); ?>
                                                                </div>
                                                                <?php if (!empty($item['special_order_reference'])): ?>
                                                                    <div style="font-size: 11px; color: #64748b; font-family: monospace;" id="item-ref-<?php echo $idx; ?>">
                                                                        Ref: <?php echo htmlspecialchars($item['special_order_reference']); ?>
                                                                    </div>
                                                                <?php endif; ?>
                                                                <?php if (!empty($item['product_details'])): ?>
                                                                    <div style="font-size: 11px; color: #475569; background: #fef3c7; border: 1px solid #fde68a; padding: 2px 6px; border-radius: 4px; margin-top: 3px;" id="item-details-<?php echo $idx; ?>">
                                                                        <?php echo htmlspecialchars($item['product_details']); ?>
                                                                    </div>
                                                                <?php endif; ?>
                                                                <div style="margin-top: 4px;">
                                                                    <button type="button" class="btn btn-default btn-xs" onclick="openEditSpecialOrderModal(<?php echo $idx; ?>)" style="border-color: #f59e0b; color: #b45309; font-weight: 700; font-size: 10.5px; padding: 1px 6px;">
                                                                        <i class="fa fa-pencil"></i> Edit Special Order
                                                                    </button>
                                                                </div>
                                                            <?php else: ?>
                                                                <div style="font-weight: 800; color: #0f172a; font-size: 13.5px;">
                                                                    <?php echo htmlspecialchars($item['base_name'] ?: $item['name']); ?>
                                                                </div>
                                                                <div style="font-size: 11px; color: #64748b; margin-top: 2px;">
                                                                    <?php if (!empty($item['sku'])): ?>SKU: <strong><?php echo htmlspecialchars($item['sku']); ?></strong> | <?php endif; ?>
                                                                    <?php if (!empty($item['spec_label']) && $item['spec_label'] !== 'Standard'): ?>
                                                                        <span style="color: #0284c7; font-weight: 700;"><?php echo htmlspecialchars($item['spec_label']); ?></span>
                                                                    <?php else: ?>
                                                                        <?php if (!empty($item['size'])): ?><span style="color: #0369a1; font-weight: 600;"><?php echo htmlspecialchars($item['size']); ?></span> <?php endif; ?>
                                                                        <?php if (!empty($item['thickness'])): ?><span style="color: #047857; font-weight: 600;"><?php echo htmlspecialchars($item['thickness']); ?></span> <?php endif; ?>
                                                                        <?php if (!empty($item['color'])): ?><span style="color: #64748b; font-weight: 600;">(<?php echo htmlspecialchars($item['color']); ?>)</span> <?php endif; ?>
                                                                    <?php endif; ?>
                                                                </div>
                                                            <?php endif; ?>

                                                            <?php if ($disc_amt > 0): ?>
                                                                <div style="margin-top: 4px;" id="item-disc-badge-<?php echo $idx; ?>">
                                                                    <span class="label label-success" style="background: #10b981; font-size: 10px; font-weight: 700; padding: 2px 6px;">
                                                                        <i class="fa fa-check-circle"></i> Discount -&#8369;<span id="item-disc-amt-<?php echo $idx; ?>"><?php echo number_format($disc_amt, 2); ?></span> (<?php echo number_format($disc_pct, 1); ?>%) Approved
                                                                    </span>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td style="text-align: right; vertical-align: middle; font-size: 13px; font-weight: 600; color: #334155;">
                                                    &#8369;<span id="item-price-<?php echo $idx; ?>"><?php echo number_format($price, 2); ?></span>
                                                </td>
                                                <td style="text-align: center; vertical-align: middle;">
                                                    <div class="input-group input-group-sm" style="max-width: 105px; margin: 0 auto;">
                                                        <span class="input-group-btn">
                                                            <button class="btn btn-default btn-sm btn-qty-minus" type="button" onclick="adjustItemQty(<?php echo $idx; ?>, -1)" <?php echo ($qty <= 1) ? 'disabled' : ''; ?> style="border-color: #cbd5e1; font-weight: 800; padding: 3px 8px;">
                                                                <i class="fa fa-minus"></i>
                                                            </button>
                                                        </span>
                                                        <input type="number" 
                                                               id="itemQtyInput-<?php echo $idx; ?>"
                                                               min="1" 
                                                               max="<?php echo $is_special ? 999999 : $stock; ?>" 
                                                               value="<?php echo $qty; ?>" 
                                                               class="form-control text-center input-sm item-qty-input" 
                                                               data-index="<?php echo $idx; ?>" 
                                                               data-max="<?php echo $is_special ? 999999 : $stock; ?>" 
                                                               onchange="updateItemQtyInput(<?php echo $idx; ?>, this.value)"
                                                               style="padding: 2px 4px; font-weight: 800; font-size: 13px; border-color: #cbd5e1; -moz-appearance: textfield;">
                                                        <span class="input-group-btn">
                                                            <button class="btn btn-default btn-sm btn-qty-plus" type="button" onclick="adjustItemQty(<?php echo $idx; ?>, 1)" <?php echo (!$is_special && $qty >= $stock) ? 'disabled' : ''; ?> style="border-color: #cbd5e1; font-weight: 800; padding: 3px 8px;">
                                                                <i class="fa fa-plus"></i>
                                                            </button>
                                                        </span>
                                                    </div>
                                                    <?php if (!$is_special): ?>
                                                        <div style="font-size: 10.5px; color: <?php echo ($stock <= 5) ? '#dc2626' : '#64748b'; ?>; margin-top: 3px;">
                                                            Stock: <strong><?php echo $stock; ?></strong>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td style="text-align: right; vertical-align: middle;">
                                                    <div id="item-line-net-wrap-<?php echo $idx; ?>">
                                                        <?php if ($disc_amt > 0): ?>
                                                            <span id="item-line-gross-<?php echo $idx; ?>" style="text-decoration: line-through; color: #94a3b8; font-size: 11px; display: block;">&#8369;<?php echo number_format($line_gross, 2); ?></span>
                                                            <span id="item-line-net-<?php echo $idx; ?>" style="font-weight: 900; font-size: 14px; color: #047857;">&#8369;<?php echo number_format($line_net, 2); ?></span>
                                                        <?php else: ?>
                                                            <span id="item-line-net-<?php echo $idx; ?>" style="font-weight: 900; font-size: 14px; color: #1e3a8a;">&#8369;<?php echo number_format($line_gross, 2); ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td style="text-align: center; vertical-align: middle;">
                                                    <button type="button" class="btn btn-default btn-xs text-danger" onclick="removeCheckoutItem(<?php echo $idx; ?>)" title="Remove item" style="border: 1px solid #fee2e2; background: #fef2f2; border-radius: 4px; padding: 4px 7px;">
                                                        <i class="fa fa-trash"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Side: Customer, Order Details, Payment & Submit -->
                <div class="col-md-5">
                    
                    <!-- Customer Information Box -->
                    <div class="box box-info" style="border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); margin-bottom: 15px;">
                        <div class="box-header with-border" style="background: #f8fafc; border-bottom: 1px solid #e2e8f0;">
                            <h3 class="box-title" style="font-weight: 800; font-size: 15px; color: #1e293b;">
                                <i class="fa fa-user text-info"></i> Customer & Fulfillment Info
                            </h3>
                        </div>
                        <div class="box-body" style="padding: 15px;">
                            <div style="margin-bottom: 10px;">
                                <label style="font-size: 12px; font-weight: 700; color: #475569;">Customer Type:</label>
                                <div>
                                    <label class="radio-inline" style="font-weight: 600;">
                                        <input type="radio" name="customer_type" value="walkin" <?php echo ($cust_data['customer_type'] === 'walkin') ? 'checked' : ''; ?> onchange="toggleCheckoutCustomerType()"> Walk-in (OTC)
                                    </label>
                                    <label class="radio-inline" style="font-weight: 600;">
                                        <input type="radio" name="customer_type" value="registered" <?php echo ($cust_data['customer_type'] === 'registered') ? 'checked' : ''; ?> onchange="toggleCheckoutCustomerType()"> Registered Customer
                                    </label>
                                </div>
                            </div>

                            <div id="checkoutWalkinFields" style="<?php echo ($cust_data['customer_type'] === 'registered') ? 'display: none;' : ''; ?>">
                                <div class="form-group" style="margin-bottom: 8px;">
                                    <label style="font-size: 12px; font-weight: 700; color: #475569;">Customer Name:</label>
                                    <input type="text" name="walkin_name" class="form-control input-sm" value="<?php echo htmlspecialchars($cust_data['customer_name']); ?>" placeholder="Enter Customer Name">
                                </div>
                                <div class="row">
                                    <div class="col-xs-6" style="padding-right: 4px;">
                                        <div class="form-group" style="margin-bottom: 8px;">
                                            <label style="font-size: 12px; font-weight: 700; color: #475569;">Phone:</label>
                                            <input type="text" name="walkin_phone" class="form-control input-sm" value="<?php echo htmlspecialchars($cust_data['customer_phone']); ?>" placeholder="Phone Number">
                                        </div>
                                    </div>
                                    <div class="col-xs-6" style="padding-left: 4px;">
                                        <div class="form-group" style="margin-bottom: 8px;">
                                            <label style="font-size: 12px; font-weight: 700; color: #475569;">Email (Optional):</label>
                                            <input type="text" name="walkin_email" class="form-control input-sm" value="<?php echo htmlspecialchars($cust_data['customer_email']); ?>" placeholder="Email Address">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div id="checkoutRegisteredFields" style="<?php echo ($cust_data['customer_type'] === 'registered') ? '' : 'display: none;'; ?> margin-bottom: 10px;">
                                <label style="font-size: 12px; font-weight: 700; color: #475569;">Select Registered Customer:</label>
                                <select name="registered_cust_id" class="form-control select2 input-sm" style="width: 100%;">
                                    <option value="">-- Choose Customer --</option>
                                    <?php foreach ($registered_customers as $rc): ?>
                                        <option value="<?php echo $rc['cust_id']; ?>" <?php echo ((int)$cust_data['customer_id'] === (int)$rc['cust_id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($rc['cust_name']) . ' (' . htmlspecialchars($rc['cust_email']) . ')'; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Delivery Toggle -->
                            <div style="background: #f1f5f9; border: 1px solid #e2e8f0; border-radius: 6px; padding: 10px; margin-top: 10px;">
                                <label style="font-size: 12px; font-weight: 700; margin-bottom: 6px; display: inline-flex; align-items: center; gap: 6px; color: #0284c7; cursor: pointer;">
                                    <input type="checkbox" id="checkoutLocationCheck" name="is_location_delivery" value="1" <?php echo !empty($cust_data['is_location_active']) ? 'checked' : ''; ?> onchange="toggleCheckoutLocation()">
                                    <i class="fa fa-truck"></i> Delivery to Location (Barangay)
                                </label>

                                <div id="checkoutLocationSelectWrap" style="<?php echo !empty($cust_data['is_location_active']) ? '' : 'display: none;'; ?> margin-top: 6px;">
                                    <select name="location_brgy_id" id="checkoutBrgySelect" class="form-control input-sm" onchange="updateCheckoutDeliveryFee()">
                                        <option value="" data-fee="0">-- Select Delivery Barangay --</option>
                                        <?php foreach ($brgy_list as $b): ?>
                                            <option value="<?php echo $b['brgy_id']; ?>" data-fee="50.00" <?php echo ((int)$cust_data['location_brgy_id'] === (int)$b['brgy_id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($b['brgy_name']); ?> (+&#8369;50.00 Delivery)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Payment & PO Information -->
                    <div class="box box-primary" style="border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); margin-bottom: 15px;">
                        <div class="box-header with-border" style="background: #f8fafc; border-bottom: 1px solid #e2e8f0;">
                            <h3 class="box-title" style="font-weight: 800; font-size: 15px; color: #1e293b;">
                                <i class="fa fa-credit-card text-success"></i> Payment & PO Terms
                            </h3>
                        </div>
                        <div class="box-body" style="padding: 15px;">
                            <div class="form-group" style="margin-bottom: 10px;">
                                <label style="font-size: 12px; font-weight: 700; color: #475569;">Payment / Order Terms:</label>
                                <select name="payment_method" class="form-control input-sm" style="font-weight: 600;">
                                    <option value="Purchase Order (PO)">📄 Purchase Order (PO) / Credit Terms</option>
                                    <option value="Cash (OTC)">💵 Cash (Over the Counter)</option>
                                    <option value="Bank Transfer">🏦 Bank Transfer / Direct Deposit</option>
                                    <option value="GCash / Maya">📱 GCash / Maya E-Wallet</option>
                                    <option value="Check / Terms">📑 Commercial Check / 30-Day Terms</option>
                                </select>
                            </div>

                            <div class="form-group" style="margin-bottom: 10px;">
                                <label style="font-size: 12px; font-weight: 700; color: #475569;">PO / Reference Number (Optional):</label>
                                <input type="text" name="po_reference" class="form-control input-sm" placeholder="e.g. PO-2026-00123 (Auto-generated if blank)">
                            </div>

                            <div class="form-group" style="margin-bottom: 0;">
                                <label style="font-size: 12px; font-weight: 700; color: #475569;">Order Remarks / Special Instructions:</label>
                                <textarea name="order_notes" rows="2" class="form-control input-sm" placeholder="Add delivery notes or PO authorization remarks..."></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Pricing Summary Box -->
                    <div style="background: #f8fafc; border: 2px solid #e2e8f0; border-radius: 8px; padding: 15px; margin-bottom: 15px;">
                        <input type="hidden" name="delivery_cost" id="checkoutDeliveryCostInput" value="<?php echo number_format($fulfillment_data['delivery_cost'], 2, '.', ''); ?>">

                        <div style="display: flex; justify-content: space-between; font-size: 14px; font-weight: 600; margin-bottom: 6px;">
                            <span class="text-muted">Gross Subtotal:</span>
                            <span style="color: #1e293b;">&#8369;<span id="checkoutGrossDisplay"><?php echo number_format($gross_subtotal, 2); ?></span></span>
                        </div>

                        <div id="checkoutDiscountRow" style="display: <?php echo ($total_discount_savings > 0) ? 'flex' : 'none'; ?>; justify-content: space-between; font-size: 14px; font-weight: 700; color: #16a34a; margin-bottom: 6px;">
                            <span>Approved Discounts:</span>
                            <span>-&#8369;<span id="checkoutDiscountDisplay"><?php echo number_format($total_discount_savings, 2); ?></span></span>
                        </div>

                        <div id="checkoutDeliveryRow" style="display: <?php echo ($fulfillment_data['delivery_cost'] > 0) ? 'flex' : 'none'; ?>; justify-content: space-between; font-size: 14px; font-weight: 700; color: #0284c7; margin-bottom: 6px;">
                            <span>Delivery Fee:</span>
                            <span>+&#8369;<span id="checkoutDeliveryFeeDisplay"><?php echo number_format($fulfillment_data['delivery_cost'], 2); ?></span></span>
                        </div>

                        <div style="background: #eff6ff; border: 2px solid #bfdbfe; border-radius: 8px; padding: 12px 16px; margin-top: 10px; display: flex; justify-content: space-between; align-items: center;">
                            <span style="font-size: 17px; font-weight: 800; color: #1e3a8a;">Grand Total:</span>
                            <span style="font-size: 24px; font-weight: 900; color: #1d4ed8;">&#8369;<span id="checkoutGrandTotalDisplay"><?php echo number_format($grand_total, 2); ?></span></span>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="col-md-12 form-group" style="margin-top: 12px; padding: 0;">
                        <input type="submit"
                                id="btnSendPO"
                                class="btn btn-primary btn-block btn-lg"
                                style="background-color: #0284c7;
                                       border-color: #0284c7;
                                       font-size: 17px;
                                       font-weight: 800;
                                       padding: 12px 20px;
                                       border-radius: 8px;
                                       box-shadow: 0 4px 10px rgba(2, 132, 199, 0.3);"
                                value="Send Purchase Order"
                                name="form_otc">
                    </div>

                    <div class="col-md-12 form-group" style="margin-top: 6px; padding: 0;">
                        <a href="pos.php" class="btn btn-default btn-block btn-lg" style="font-size: 15px; font-weight: 700; border-radius: 8px; border: 2px solid #cbd5e1; color: #334155; padding: 10px 20px;">
                            <i class="fa fa-arrow-left"></i> Back to POS / Add More Items
                        </a>
                    </div>

                </div>
            </div>
        </form>

        <!-- Modal: Edit Special Order Item -->
        <div class="modal fade" id="editSpecialOrderModal" tabindex="-1" role="dialog">
            <div class="modal-dialog" role="document" style="max-width: 500px;">
                <div class="modal-content" style="border-radius: 8px; overflow: hidden; box-shadow: 0 10px 25px rgba(0,0,0,0.2);">
                    <div class="modal-header" style="background: #f8fafc; border-bottom: 1px solid #e2e8f0; padding: 12px 18px;">
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                        <h4 class="modal-title" style="font-weight: 800; font-size: 16px; color: #1e293b;">
                            <i class="fa fa-pencil text-warning"></i> Edit Special Order Item
                        </h4>
                    </div>
                    <form id="editSpecialOrderForm" onsubmit="saveSpecialOrderModal(event)">
                        <div class="modal-body" style="padding: 18px;">
                            <input type="hidden" id="soModalIndex" value="">

                            <div class="form-group" style="margin-bottom: 12px;">
                                <label style="font-size: 12px; font-weight: 700; color: #475569;">Item Name / Description <span class="text-danger">*</span>:</label>
                                <input type="text" id="soModalName" class="form-control input-sm" required placeholder="e.g. Custom Steel Plate 10mm">
                            </div>

                            <div class="row">
                                <div class="col-xs-6">
                                    <div class="form-group" style="margin-bottom: 12px;">
                                        <label style="font-size: 12px; font-weight: 700; color: #475569;">Unit Price (₱) <span class="text-danger">*</span>:</label>
                                        <input type="number" step="0.01" min="0.01" id="soModalPrice" class="form-control input-sm" required placeholder="0.00">
                                    </div>
                                </div>
                                <div class="col-xs-6">
                                    <div class="form-group" style="margin-bottom: 12px;">
                                        <label style="font-size: 12px; font-weight: 700; color: #475569;">Quantity <span class="text-danger">*</span>:</label>
                                        <input type="number" min="1" id="soModalQty" class="form-control input-sm" required value="1">
                                    </div>
                                </div>
                            </div>

                            <div class="form-group" style="margin-bottom: 12px;">
                                <label style="font-size: 12px; font-weight: 700; color: #475569;">Special Order Reference (Optional):</label>
                                <input type="text" id="soModalRef" class="form-control input-sm" placeholder="e.g. SO-2026-001">
                            </div>

                            <div class="form-group" style="margin-bottom: 0;">
                                <label style="font-size: 12px; font-weight: 700; color: #475569;">Specifications & Details:</label>
                                <textarea id="soModalDetails" rows="3" class="form-control input-sm" placeholder="Custom cut, color, material, etc."></textarea>
                            </div>
                        </div>
                        <div class="modal-footer" style="background: #f8fafc; border-top: 1px solid #e2e8f0; padding: 10px 18px; display: flex; justify-content: flex-end; gap: 8px;">
                            <button type="button" class="btn btn-default btn-sm" data-dismiss="modal" style="font-weight: 700;">Cancel</button>
                            <button type="submit" id="btnSaveSpecialOrder" class="btn btn-primary btn-sm" style="font-weight: 700; background: #0284c7; border-color: #0284c7;">
                                <i class="fa fa-save"></i> Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

    <?php endif; ?>

</section>

<script>
let currentCheckoutItems = <?php echo json_encode(array_values($cart_items)); ?>;
let baseNetSubtotal = <?php echo json_encode($net_subtotal); ?>;
let baseGrossSubtotal = <?php echo json_encode($gross_subtotal); ?>;
let baseDiscountSavings = <?php echo json_encode($total_discount_savings); ?>;

function toggleCheckoutCustomerType() {
    const type = document.querySelector('input[name="customer_type"]:checked').value;
    document.getElementById('checkoutWalkinFields').style.display = (type === 'walkin') ? 'block' : 'none';
    document.getElementById('checkoutRegisteredFields').style.display = (type === 'registered') ? 'block' : 'none';
}

function toggleCheckoutLocation() {
    const isChecked = document.getElementById('checkoutLocationCheck').checked;
    document.getElementById('checkoutLocationSelectWrap').style.display = isChecked ? 'block' : 'none';
    updateCheckoutDeliveryFee();
}

function updateCheckoutDeliveryFee() {
    const isChecked = document.getElementById('checkoutLocationCheck').checked;
    const select = document.getElementById('checkoutBrgySelect');
    let fee = 0;
    if (isChecked && select && select.value) {
        fee = 50.00;
    }
    const delCostInput = document.getElementById('checkoutDeliveryCostInput');
    if (delCostInput) delCostInput.value = fee.toFixed(2);

    const delFeeDisplay = document.getElementById('checkoutDeliveryFeeDisplay');
    if (delFeeDisplay) delFeeDisplay.innerText = fee.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    const delRow = document.getElementById('checkoutDeliveryRow');
    if (delRow) delRow.style.display = fee > 0 ? 'flex' : 'none';

    const grand = baseNetSubtotal + fee;
    const grandDisplay = document.getElementById('checkoutGrandTotalDisplay');
    if (grandDisplay) grandDisplay.innerText = grand.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function updateCheckoutSummaryDisplay(summary) {
    baseNetSubtotal = summary.net_subtotal;
    baseGrossSubtotal = summary.gross_subtotal;
    baseDiscountSavings = summary.total_discount_savings;
    
    // Update badge count
    const badge = document.getElementById('cartCountBadge');
    if (badge) badge.innerText = summary.cart_count;

    // Update gross subtotal
    const grossDisplay = document.getElementById('checkoutGrossDisplay');
    if (grossDisplay) grossDisplay.innerText = summary.gross_subtotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    // Update discount savings row
    const discRow = document.getElementById('checkoutDiscountRow');
    const discDisplay = document.getElementById('checkoutDiscountDisplay');
    if (discRow && discDisplay) {
        discDisplay.innerText = summary.total_discount_savings.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        discRow.style.display = summary.total_discount_savings > 0 ? 'flex' : 'none';
    }

    // Update delivery fee & grand total
    updateCheckoutDeliveryFee();

    if (summary.cart_count === 0) {
        window.location.reload();
    }
}

function adjustItemQty(index, delta) {
    const input = document.getElementById('itemQtyInput-' + index);
    if (!input) return;
    let currentQty = parseInt(input.value) || 1;
    let maxStock = parseInt(input.getAttribute('data-max')) || 999999;
    let newQty = currentQty + delta;
    if (newQty < 1) newQty = 1;
    if (newQty > maxStock) {
        alert('Cannot exceed available stock limit of ' + maxStock + ' units.');
        return;
    }
    input.value = newQty;
    sendQtyUpdate(index, newQty);
}

function updateItemQtyInput(index, val) {
    const input = document.getElementById('itemQtyInput-' + index);
    let newQty = parseInt(val) || 1;
    let maxStock = parseInt(input ? input.getAttribute('data-max') : 999999) || 999999;
    if (newQty < 1) newQty = 1;
    if (newQty > maxStock) {
        alert('Cannot exceed available stock limit of ' + maxStock + ' units.');
        newQty = maxStock;
    }
    if (input) input.value = newQty;
    sendQtyUpdate(index, newQty);
}

function sendQtyUpdate(index, newQty) {
    const row = document.getElementById('cartRow-' + index);
    if (row) row.style.opacity = '0.6';

    const formData = new FormData();
    formData.append('action', 'checkout_update_qty');
    formData.append('index', index);
    formData.append('qty', newQty);

    fetch('checkout.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (row) row.style.opacity = '1';
        if (data.status === 'success') {
            const lineNet = document.getElementById('item-line-net-' + index);
            if (lineNet) lineNet.innerText = '₱' + data.item.line_net.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            
            const lineGross = document.getElementById('item-line-gross-' + index);
            if (lineGross) lineGross.innerText = '₱' + data.item.line_gross.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

            const discAmt = document.getElementById('item-disc-amt-' + index);
            if (discAmt) discAmt.innerText = data.item.discount_amount.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

            const minusBtn = row.querySelector('.btn-qty-minus');
            const plusBtn = row.querySelector('.btn-qty-plus');
            const maxStock = parseInt(document.getElementById('itemQtyInput-' + index).getAttribute('data-max')) || 999999;
            if (minusBtn) minusBtn.disabled = (data.item.qty <= 1);
            if (plusBtn) plusBtn.disabled = (data.item.qty >= maxStock);

            if (currentCheckoutItems[index]) {
                currentCheckoutItems[index].qty = data.item.qty;
                currentCheckoutItems[index].line_gross = data.item.line_gross;
                currentCheckoutItems[index].line_net = data.item.line_net;
            }

            updateCheckoutSummaryDisplay(data.summary);
        } else {
            alert(data.message || 'Error updating item quantity.');
            window.location.reload();
        }
    })
    .catch(err => {
        if (row) row.style.opacity = '1';
        console.error('Update qty error:', err);
    });
}

function removeCheckoutItem(index) {
    if (!confirm('Are you sure you want to remove this item from your checkout cart?')) {
        return;
    }

    const row = document.getElementById('cartRow-' + index);
    if (row) row.style.opacity = '0.4';

    const formData = new FormData();
    formData.append('action', 'checkout_remove_item');
    formData.append('index', index);

    fetch('checkout.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            window.location.reload();
        } else {
            if (row) row.style.opacity = '1';
            alert(data.message || 'Error removing item.');
        }
    })
    .catch(err => {
        if (row) row.style.opacity = '1';
        console.error('Remove item error:', err);
    });
}

function clearCheckoutCart() {
    if (!confirm('Are you sure you want to clear all items from your checkout cart?')) {
        return;
    }

    const formData = new FormData();
    formData.append('action', 'checkout_clear_cart');

    fetch('checkout.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        window.location.reload();
    })
    .catch(err => {
        console.error('Clear cart error:', err);
        window.location.reload();
    });
}

function openEditSpecialOrderModal(index) {
    const item = currentCheckoutItems[index];
    if (!item) return;

    document.getElementById('soModalIndex').value = index;
    document.getElementById('soModalName').value = item.name || item.base_name || '';
    document.getElementById('soModalPrice').value = parseFloat(item.price || 0).toFixed(2);
    document.getElementById('soModalQty').value = parseInt(item.qty || 1);
    document.getElementById('soModalRef').value = item.special_order_reference || '';
    document.getElementById('soModalDetails').value = item.product_details || '';

    $('#editSpecialOrderModal').modal('show');
}

function saveSpecialOrderModal(e) {
    e.preventDefault();
    const btn = document.getElementById('btnSaveSpecialOrder');
    if (btn) btn.disabled = true;

    const index = document.getElementById('soModalIndex').value;
    const name = document.getElementById('soModalName').value;
    const price = document.getElementById('soModalPrice').value;
    const qty = document.getElementById('soModalQty').value;
    const ref = document.getElementById('soModalRef').value;
    const details = document.getElementById('soModalDetails').value;

    const formData = new FormData();
    formData.append('action', 'checkout_edit_special_order');
    formData.append('index', index);
    formData.append('name', name);
    formData.append('price', price);
    formData.append('qty', qty);
    formData.append('special_order_reference', ref);
    formData.append('product_details', details);

    fetch('checkout.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (btn) btn.disabled = false;
        if (data.status === 'success') {
            $('#editSpecialOrderModal').modal('hide');
            window.location.reload();
        } else {
            alert(data.message || 'Failed to update special order item.');
        }
    })
    .catch(err => {
        if (btn) btn.disabled = false;
        console.error('Save special order error:', err);
    });
}

function printCheckoutReceipt() {
    const content = document.getElementById('checkoutPrintArea').innerHTML;
    let paperWidthMm = 500;
    let copies = 1;
    let isThermal = true;
    try {
        const saved = localStorage.getItem('pos_printer_settings');
        if (saved) {
            const parsed = JSON.parse(saved);
            paperWidthMm = parseInt(parsed.paperWidthMm, 10) || 500;
            copies = Math.min(5, Math.max(1, parseInt(parsed.copies, 10) || 1));
            isThermal = (parsed.printerType !== 'normal');
        }
    } catch (e) {
        console.warn('Could not read pos_printer_settings', e);
    }

    let copiesHtml = '';
    for (let c = 0; c < copies; c++) {
        copiesHtml += '<div class="pos-print-page' + (c > 0 ? ' pos-page-break' : '') + '">' + content + '</div>';
    }

    const printWin = window.open('', '_blank', 'width=850,height=900');
    if (!printWin) {
        alert('Print popup was blocked by browser. Please allow popups to print receipts.');
        return;
    }

    printWin.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <title>Purchase Order & Receipt</title>
            <style>
                * { box-sizing: border-box; }
                body {
                    font-family: ${isThermal ? "'Courier New', Courier, monospace, 'Helvetica Neue', Helvetica, Arial, sans-serif" : "'Helvetica Neue', Helvetica, Arial, sans-serif"};
                    padding: 0;
                    margin: 0;
                    background: #fff;
                    color: #000;
                    font-size: ${paperWidthMm >= 500 ? '14px' : (paperWidthMm <= 80 ? '11px' : '13px')};
                    line-height: 1.5;
                }
                table { width: 100%; border-collapse: collapse; margin-top: 10px; margin-bottom: 10px; }
                th, td { border: 1px solid #cbd5e1; padding: ${paperWidthMm >= 500 ? '8px 12px' : '5px 6px'}; text-align: left; }
                th { background-color: #f1f5f9; }
                .pos-page-break { page-break-before: always; margin-top: 25px; }

                @media screen {
                    body { padding: 25px; background: #f1f5f9; display: flex; justify-content: center; }
                    .checkout-print-container {
                        width: ${paperWidthMm}mm;
                        max-width: 100%;
                        background: #ffffff;
                        padding: ${paperWidthMm >= 500 ? '12mm' : '6mm'};
                        box-shadow: 0 4px 15px rgba(0,0,0,0.2);
                        border-radius: 6px;
                    }
                }

                @media print {
                    @page {
                        size: ${paperWidthMm}mm auto;
                        margin: ${paperWidthMm >= 500 ? '0' : '5mm'};
                    }
                    body {
                        padding: ${paperWidthMm >= 500 ? '12mm' : '2mm'} !important;
                        margin: 0 !important;
                        width: ${paperWidthMm}mm !important;
                        max-width: ${paperWidthMm}mm !important;
                    }
                    .checkout-print-container {
                        width: 100% !important;
                        max-width: ${paperWidthMm}mm !important;
                        box-shadow: none !important;
                        padding: 0 !important;
                    }
                }
            </style>
        </head>
        <body>
            <div class="checkout-print-container">
                ${copiesHtml}
            </div>
        </body>
        </html>
    `);
    printWin.document.close();
    printWin.focus();
    setTimeout(() => {
        printWin.print();
        printWin.close();
    }, 450);
}

document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('supplierCheckoutForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            const btn = document.getElementById('btnSendPO');
            if (btn) {
                if (btn.disabled) {
                    e.preventDefault();
                    return false;
                }
                btn.disabled = true;
                btn.value = 'Submitting Purchase Order...';
                btn.style.opacity = '0.75';
                btn.style.cursor = 'not-allowed';
            }
        });
    }
});
</script>

<?php require_once('footer.php'); ?>

