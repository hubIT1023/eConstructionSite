<?php require_once('header.php'); ?>

<?php
$supplier_id = $_SESSION['supplier_user']['supplier_id'];

if (!function_exists('parseConstructionProductDetails')) {
function parseConstructionProductDetails($raw_name) {
    $name = trim($raw_name);
    $color = "";
    $thickness = "";
    $diameter = "";
    $size = "";
    $weight_pack = "";
    $material = "";
    $voltage = "";
    $power = "";
    $rated_current = "";
    $length = "";

    // 1. Detect Color keyword
    $colors = ["Orange", "Green", "Blue", "Yellow", "Red", "Black", "White", "Brown", "Tan", "Pink"];
    foreach ($colors as $c) {
        if (preg_match('/\b' . preg_quote($c, '/') . '\b/i', $name)) {
            $color = $c;
            $name = trim(preg_replace('/\b' . preg_quote($c, '/') . '\b/i', '', $name));
            break;
        }
    }

    // 2. Detect Material / Finish keywords (Stainless, Galvanized, GI, BI, Marine local, Mar china, Ord china, Ord local)
    if (preg_match('/\b(Stainless|Galvanized|GI|BI)\b/i', $name, $matches)) {
        $material = trim($matches[1]);
        $name = trim(preg_replace('/\b' . preg_quote($matches[0], '/') . '\b/i', '', $name));
    } elseif (preg_match('/\b(marine\s*local|mar\s*china|marine\s*china|ord\s*china|ord\s*local|marine)\b/i', $name, $matches)) {
        $material = ucwords(trim($matches[1]));
        $name = trim(preg_replace('/\b' . preg_quote($matches[0], '/') . '\b/i', '', $name));
    }

    // 3. Detect Weight / Packaging (e.g. (1/4kg), (1/2kg), (1kg), 1L, 1G, (1 Kg))
    if (preg_match('/\(\s*([0-9\/\.]+\s*(?:kg|g|l|lbs|gal|kg\b))\s*\)/i', $name, $matches)) {
        $weight_pack = trim($matches[1]);
        $name = trim(str_replace($matches[0], '', $name));
    } elseif (preg_match('/\b([0-9\/\.]+\s*(?:kg|g|l|lbs|gal))\b/i', $name, $matches)) {
        $weight_pack = trim($matches[1]);
        $name = trim(preg_replace('/\b' . preg_quote($matches[0], '/') . '\b/i', '', $name));
    }

    // 4. Detect Voltage / Power / Current (e.g. 220V, 110V, 850W, 1000W, 10A, 20A)
    if (preg_match('/\b([0-9]+(?:\.[0-9]+)?\s*(?:V|VAC|VDC))\b/i', $name, $matches)) {
        $voltage = trim($matches[1]);
        $name = trim(str_replace($matches[0], '', $name));
    }
    if (preg_match('/\b([0-9]+(?:\.[0-9]+)?\s*(?:W|kW|HP))\b/i', $name, $matches)) {
        $power = trim($matches[1]);
        $name = trim(str_replace($matches[0], '', $name));
    }
    if (preg_match('/\b([0-9]+(?:\.[0-9]+)?\s*(?:A|Amp|Amps))\b/i', $name, $matches)) {
        $rated_current = trim($matches[1]);
        $name = trim(str_replace($matches[0], '', $name));
    }

    // 5. Detect Diameter in parentheses: (D = 10 mm), (D = 16 mm)
    if (preg_match('/\(\s*([dD]\s*=\s*[^,\)]+)(?:,\s*([^)]+))?\s*\)/i', $name, $matches)) {
        $diameter = trim($matches[1]);
        if (empty($color) && !empty($matches[2])) {
            $color = trim($matches[2]);
        }
        $name = trim(str_replace($matches[0], '', $name));
    }
    // 6. Detect Thickness in parentheses: (t = 1.2), (t = 1/4"), (t = 3/16)
    elseif (preg_match('/\(\s*([tT]\s*=\s*[^,\)]+)(?:,\s*([^)]+))?\s*\)/i', $name, $matches)) {
        $thickness = trim($matches[1]);
        if (empty($color) && !empty($matches[2])) {
            $color = trim($matches[2]);
        }
        $name = trim(str_replace($matches[0], '', $name));
    }
    // Check remaining parenthesized text (e.g. (3-inch x 10ft) or (APO Brand))
    elseif (preg_match('/\(\s*([^\)]+)\s*\)/i', $name, $matches)) {
        $inside = trim($matches[1]);
        if (preg_match('/brand/i', $inside)) {
            $material = $inside;
        } else {
            $size = $inside;
        }
        $name = trim(str_replace($matches[0], '', $name));
    }

    // 7. Detect Length (e.g. 10ft, 20ft, 6m)
    if (preg_match('/\b([0-9]+(?:\.[0-9]+)?\s*(?:ft|m|meters|feet))\b/i', $name, $matches)) {
        $length = trim($matches[1]);
        $name = trim(str_replace($matches[0], '', $name));
    }

    // 8. Detect Size / Dimensions
    if (empty($size)) {
        // Pattern A: Hyphen with dimensions: Tubular - 2" x 3", Angle Bar - 1 1/2" x 1 1/2"
        if (preg_match('/-\s*([0-9\s\/\.\"]+\s*x\s*[0-9\s\/\.\"]+(?:\s*(?:inch|in|mm|cm|ft|\"))?)/i', $name, $matches)) {
            $size = trim($matches[1]);
            $name = trim(str_replace($matches[0], '', $name));
        }
        // Pattern B: Dimensions with x: 2 x 3, 2" x 4", 1" x 1"
        elseif (preg_match('/([0-9\s\/\.\"]+\s*x\s*[0-9\s\/\.\"]+(?:\s*(?:inch|in|mm|cm|ft|\"))?)/i', $name, $matches)) {
            $size = trim($matches[1]);
            $name = trim(str_replace($matches[0], '', $name));
        }
        // Pattern C: Single dimension sizes: 16mm, 12mm, 4", 2", 1 1/4, 1 1/2, 2 1/2, 3/4, 1/2, 4.5, 3.5, #4, #6, #8, #40
        elseif (preg_match('/\b([0-9]+(?:\s+[0-9]+\/[0-9]+|\/[0-9]+|\.[0-9]+)?\s*(?:mm|cm|inch|in|\"|#\d+)?)\s*$/i', $name, $matches) && strlen(trim($matches[1])) > 0) {
            $size = trim($matches[1]);
            $name = trim(substr($name, 0, -strlen($matches[0])));
        }
    }

    // Clean up base_name
    $base_name = trim(trim($name), "- \t\n\r\0\x0B");
    $base_name = preg_replace('/\s+/', ' ', $base_name);
    if (empty($base_name)) {
        $base_name = $raw_name;
    }

    // Build readable spec_label
    $specs_parts = [];
    if (!empty($diameter)) $specs_parts[] = $diameter;
    if (!empty($size)) $specs_parts[] = $size;
    if (!empty($thickness)) $specs_parts[] = $thickness;
    if (!empty($weight_pack)) $specs_parts[] = $weight_pack;
    if (!empty($voltage)) $specs_parts[] = $voltage;
    if (!empty($power)) $specs_parts[] = $power;
    if (!empty($rated_current)) $specs_parts[] = $rated_current;
    if (!empty($length)) $specs_parts[] = $length;
    if (!empty($material)) $specs_parts[] = $material;
    if (!empty($color)) $specs_parts[] = $color;

    $spec_label = !empty($specs_parts) ? implode(' | ', $specs_parts) : 'Standard';

    return [
        'base_name' => $base_name,
        'size' => $size,
        'thickness' => $thickness,
        'diameter' => $diameter,
        'color' => $color,
        'material' => $material,
        'weight_pack' => $weight_pack,
        'voltage' => $voltage,
        'power' => $power,
        'rated_current' => $rated_current,
        'length' => $length,
        'spec_label' => $spec_label
    ];
}
}

// Ensure schema
ensure_supplier_user_schema($pdo);

// Fetch Supplier Info & Discount Policy
$statement = $pdo->prepare("SELECT * FROM tbl_supplier WHERE supplier_id=?");
$statement->execute(array($supplier_id));
$supplier_info = $statement->fetch(PDO::FETCH_ASSOC);

$discount_enabled = isset($supplier_info['discount_enabled']) ? (int)$supplier_info['discount_enabled'] : 1;
$discount_normal_max = isset($supplier_info['discount_normal_max']) ? floatval($supplier_info['discount_normal_max']) : 10.00;
$discount_special_enabled = isset($supplier_info['discount_special_enabled']) ? (int)$supplier_info['discount_special_enabled'] : 1;
$discount_absolute_max = isset($supplier_info['discount_absolute_max']) ? floatval($supplier_info['discount_absolute_max']) : 20.00;
$discount_require_admin_approval = isset($supplier_info['discount_require_admin_approval']) ? (int)$supplier_info['discount_require_admin_approval'] : 1;

$pos_user_id = (int)$_SESSION['supplier_user']['id'];
$pos_user_name = !empty($_SESSION['supplier_user']['full_name']) ? $_SESSION['supplier_user']['full_name'] : 'Supplier User';
$pos_user_role = normalize_supplier_role(isset($_SESSION['supplier_user']['role']) ? $_SESSION['supplier_user']['role'] : 'USER');
$pos_is_approver = is_supplier_approver();
$pos_role_display = get_role_display_name($pos_user_role);
$is_order_processing_or_operator = is_order_processing_or_operator_role($pos_user_role);

$pos_success_receipt = null;
$pos_order_error = null;

// Explicit cart clear / reset parameter handling
if (isset($_GET['clear_cart']) || isset($_GET['new_sale']) || isset($_GET['reset_pos'])) {
    unset($_SESSION['pos_cart']);
    unset($_SESSION['supplier_checkout_cart']);
    unset($_SESSION['pos_po_success']);
    $active_pos_cart = [];
}

// Active Held / Parked PO Count for Toolbar Badge
$held_pos_count = 0;
try {
    $stmt_held_cnt = $pdo->prepare("
        SELECT COUNT(DISTINCT payment_id) 
        FROM tbl_payment 
        WHERE supplier_id = ? 
          AND (payment_status = 'Awaiting for Payment' OR payment_status = 'Pending' OR payment_status = 'UNPAID')
          AND payment_status != 'Paid'
          AND payment_status != 'Completed'
    ");
    $stmt_held_cnt->execute([$supplier_id]);
    $held_pos_count = (int)$stmt_held_cnt->fetchColumn();
} catch (Exception $e) {
    $held_pos_count = 0;
}

// Existing Purchase Order Payment Detection & Authorization (Only when NOT in PO Creation Success View)
$paying_existing_po = false;
$existing_po_data = null;
$po_load_error = null;

if ((!empty($_GET['po_id']) || !empty($_GET['payment_id'])) && empty($_GET['po_success']) && empty($_SESSION['pos_po_success'])) {
    $target_po_param = trim(!empty($_GET['po_id']) ? $_GET['po_id'] : $_GET['payment_id']);
    try {
        $stmt_po_check = $pdo->prepare("SELECT * FROM tbl_payment WHERE (payment_id = ? OR txnid = ? OR id = ?) AND supplier_id = ? LIMIT 1");
        $stmt_po_check->execute([$target_po_param, $target_po_param, is_numeric($target_po_param) ? (int)$target_po_param : 0, $supplier_id]);
        $po_found = $stmt_po_check->fetch(PDO::FETCH_ASSOC);

        if (!$po_found) {
            $po_load_error = "Purchase Order (" . htmlspecialchars($target_po_param) . ") was not found or you are not authorized to view it.";
        } elseif (strtolower($po_found['payment_status'] ?? '') === 'paid') {
            $po_load_error = "This Purchase Order (" . htmlspecialchars($po_found['payment_id']) . ") has already been paid and settled. Please view it in Paid Orders.";
        } else {
            $paying_existing_po = true;
            $existing_po_data = $po_found;

            // Fetch line items from tbl_order for this PO
            $stmt_po_items = $pdo->prepare("
                SELECT o.*, p.p_qty as current_stock, p.p_featured_photo, p.p_sku 
                FROM tbl_order o 
                LEFT JOIN tbl_product p ON o.product_id = p.p_id 
                WHERE o.payment_id = ? AND o.supplier_id = ? 
                ORDER BY o.id ASC
            ");
            $stmt_po_items->execute([$po_found['payment_id'], $supplier_id]);
            $po_raw_items = $stmt_po_items->fetchAll(PDO::FETCH_ASSOC);

            $po_cart = [];
            $po_items_subtotal = 0.0;
            foreach ($po_raw_items as $idx => $it) {
                $p_type = (isset($it['item_type']) && $it['item_type'] === 'SPECIAL_ORDER') ? 'SPECIAL_ORDER' : 'STANDARD';
                $q = max(1, intval($it['quantity']));
                $u = max(0, floatval($it['unit_price']));
                $disc_pct = floatval($it['discount_percent'] ?? 0);
                $disc_amt = floatval($it['discount_amount'] ?? 0);
                $line_gross = round($q * $u, 2);
                if ($disc_amt <= 0 && $disc_pct > 0) {
                    $disc_amt = round($line_gross * ($disc_pct / 100), 2);
                }
                $line_net = max(0, $line_gross - $disc_amt);
                $po_items_subtotal += $line_net;

                $po_cart[] = [
                    'id' => intval($it['product_id']),
                    'name' => $it['product_name'],
                    'base_name' => $it['product_name'],
                    'qty' => $q,
                    'price' => $u,
                    'stock' => ($p_type === 'STANDARD') ? intval($it['current_stock'] ?? 100) : 999999,
                    'item_type' => $p_type,
                    'product_details' => $it['product_details'] ?? '',
                    'size' => $it['size'] ?? '',
                    'color' => $it['color'] ?? '',
                    'special_order_reference' => $it['special_order_reference'] ?? '',
                    'discount_percent' => $disc_pct,
                    'discount_amount' => $disc_amt,
                    'discount_status' => ($disc_amt > 0 || $disc_pct > 0) ? 'APPROVED' : null,
                    'line_net' => $line_net,
                    'sku' => $it['p_sku'] ?? ('SKU-' . $it['product_id'])
                ];
            }

            // Customer details resolution
            $po_cust_id = intval($po_found['customer_id'] ?? 0);
            $po_cust_name = $po_found['customer_name'] ?? '';
            $po_cust_email = $po_found['customer_email'] ?? '';
            $po_cust_phone = '';
            $po_cust_address = '';

            // Check if registered customer exists in tbl_customer
            if ($po_cust_id > 0) {
                $stmt_c = $pdo->prepare("SELECT * FROM tbl_customer WHERE cust_id = ?");
                $stmt_c->execute([$po_cust_id]);
                $c_row = $stmt_c->fetch(PDO::FETCH_ASSOC);
                if ($c_row) {
                    $po_cust_phone = $c_row['cust_phone'] ?? '';
                    $po_cust_address = $c_row['cust_address'] ?? '';
                }
            } elseif (!empty($po_cust_email)) {
                $stmt_c = $pdo->prepare("SELECT * FROM tbl_customer WHERE cust_email = ? LIMIT 1");
                $stmt_c->execute([$po_cust_email]);
                $c_row = $stmt_c->fetch(PDO::FETCH_ASSOC);
                if ($c_row) {
                    $po_cust_id = intval($c_row['cust_id']);
                    $po_cust_phone = $c_row['cust_phone'] ?? '';
                    $po_cust_address = $c_row['cust_address'] ?? '';
                }
            }

            // Parse bank_transaction_info for delivery and contact details
            $bank_info_raw = $po_found['bank_transaction_info'] ?? '';
            if (empty($po_cust_phone) && preg_match('/(?:Phone|Tel|Mobile):s*([^
|]+)/i', $bank_info_raw, $m_ph)) {
                $po_cust_phone = trim($m_ph[1]);
            }
            if (empty($po_cust_address) && preg_match('/(?:Address|Delivery):s*([^
|]+)/i', $bank_info_raw, $m_ad)) {
                $po_cust_address = trim($m_ad[1]);
            }

            // Check for delivery fee
            $po_paid_amt = floatval($po_found['paid_amount'] ?? 0);
            $po_diff_delivery = max(0, $po_paid_amt - $po_items_subtotal);
            $is_po_delivery = ($po_diff_delivery > 0 || stripos($bank_info_raw, 'delivery') !== false);
            $po_brgy_id = 0;

            if ($is_po_delivery) {
                // Attempt to match barangay from tbl_brgy
                $stmt_all_b = $pdo->query("SELECT brgy_id, brgy_name FROM tbl_brgy");
                $all_brgys = $stmt_all_b->fetchAll(PDO::FETCH_ASSOC);
                foreach ($all_brgys as $b) {
                    if (stripos($bank_info_raw, $b['brgy_name']) !== false || (!empty($po_cust_address) && stripos($po_cust_address, $b['brgy_name']) !== false)) {
                        $po_brgy_id = (int)$b['brgy_id'];
                        break;
                    }
                }
            }

            // Set active POS cart to PO items
            $active_pos_cart = $po_cart;
            $_SESSION['pos_cart'] = $po_cart;
            $_SESSION['supplier_checkout_cart'] = [
                'items' => $po_cart,
                'customer' => [
                    'customer_type' => ($po_cust_id > 0) ? 'registered' : 'walkin',
                    'customer_id' => $po_cust_id,
                    'customer_name' => $po_cust_name ?: 'Walk-in Customer',
                    'customer_phone' => $po_cust_phone,
                    'customer_email' => $po_cust_email,
                    'customer_address' => $po_cust_address,
                    'is_location_active' => $is_po_delivery ? 1 : 0,
                    'location_brgy_id' => $po_brgy_id
                ],
                'fulfillment' => [
                    'delivery_type' => $is_po_delivery ? 'delivery' : 'pickup',
                    'delivery_cost' => $po_diff_delivery
                ],
                'payment_method' => $po_found['payment_method'] ?? 'Cash (OTC)'
            ];
        }
    } catch (Exception $e) {
        $po_load_error = "Database error while loading Purchase Order: " . $e->getMessage();
    }
}


// Retrieve PO Success / Failure state for POS Workflow Confirmation
$pos_po_success_data = null;
if (!empty($_SESSION['pos_po_success'])) {
    $pos_po_success_data = $_SESSION['pos_po_success'];
    unset($_SESSION['pos_po_success']);
    unset($_SESSION['pos_cart']);
    unset($_SESSION['supplier_checkout_cart']);
    $active_pos_cart = [];
} elseif (!empty($_GET['po_success']) && !empty($_GET['po_id'])) {
    unset($_SESSION['pos_cart']);
    unset($_SESSION['supplier_checkout_cart']);
    $active_pos_cart = [];
    $po_id_param = trim($_GET['po_id']);
    try {
        $stmt_pop = $pdo->prepare("SELECT * FROM tbl_payment WHERE (payment_id = ? OR txnid = ?) AND supplier_id = ?");
        $stmt_pop->execute(array($po_id_param, $po_id_param, $supplier_id));
        $po_p_row = $stmt_pop->fetch(PDO::FETCH_ASSOC);
        if ($po_p_row) {
            $stmt_poo = $pdo->prepare("SELECT * FROM tbl_order WHERE payment_id = ? AND supplier_id = ?");
            $stmt_poo->execute(array($po_p_row['payment_id'], $supplier_id));
            $po_orders = $stmt_poo->fetchAll(PDO::FETCH_ASSOC);
            $items_formatted = [];
            $subtotal_calc = 0;
            foreach ($po_orders as $po_o) {
                $q = intval($po_o['quantity']);
                $u = floatval($po_o['unit_price']);
                $subtotal_calc += ($q * $u);
                $items_formatted[] = [
                    'id' => $po_o['product_id'],
                    'name' => $po_o['product_name'],
                    'qty' => $q,
                    'price' => $u,
                    'line_net' => ($q * $u),
                    'item_type' => $po_o['item_type'],
                    'product_details' => $po_o['product_details'],
                    'special_order_reference' => $po_o['special_order_reference'] ?? ''
                ];
            }
            $pos_po_success_data = [
                'po_id' => $po_p_row['payment_id'],
                'payment_date' => $po_p_row['payment_date'],
                'paid_amount' => floatval($po_p_row['paid_amount']),
                'customer_name' => $po_p_row['customer_name'],
                'customer_phone' => '',
                'customer_email' => $po_p_row['customer_email'],
                'customer_address' => '',
                'payment_method' => $po_p_row['payment_method'],
                'items' => $items_formatted,
                'gross_subtotal' => $subtotal_calc,
                'total_discount_savings' => 0.00,
                'net_subtotal' => $subtotal_calc,
                'delivery_cost' => max(0, floatval($po_p_row['paid_amount']) - $subtotal_calc),
                'grand_total' => floatval($po_p_row['paid_amount']),
                'supplier_name' => !empty($supplier_info['supplier_name']) ? $supplier_info['supplier_name'] : 'eConstruction Supplier Store',
                'supplier_phone' => !empty($supplier_info['supplier_phone']) ? $supplier_info['supplier_phone'] : '',
                'supplier_address' => !empty($supplier_info['supplier_address']) ? $supplier_info['supplier_address'] : '',
                'cashier_name' => (!empty($_SESSION['supplier_user']['full_name']) ? $_SESSION['supplier_user']['full_name'] : (!empty($_SESSION['supplier_user']['username']) ? $_SESSION['supplier_user']['username'] : 'Cashier'))
            ];
        }
    } catch (Exception $e) {}
}

$pos_po_error_msg = null;
if (!empty($_SESSION['pos_po_error'])) {
    $pos_po_error_msg = $_SESSION['pos_po_error'];
    unset($_SESSION['pos_po_error']);
} elseif (!empty($_GET['po_error'])) {
    $pos_po_error_msg = "Purchase Order submission was unsuccessful. Your current order has been retained. Please review the order and try again.";
}

// Handle POS Cart Synchronization for Checkout (Order Processing Staff / Operator)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pos_action']) && $_POST['pos_action'] === 'sync_checkout_cart') {
    header('Content-Type: application/json');
    $cart_data_json = isset($_POST['cart_items']) ? $_POST['cart_items'] : '[]';
    $cart_items = json_decode($cart_data_json, true);

    if (empty($cart_items)) {
        echo json_encode(['status' => 'error', 'message' => 'Cart is empty.']);
        exit;
    }

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

    $payment_method = isset($_POST['payment_method']) ? $_POST['payment_method'] : 'Cash (OTC)';
    $delivery_type = $is_location_active ? 'delivery' : 'pickup';
    $delivery_cost = ($is_location_active && isset($_POST['delivery_cost'])) ? floatval($_POST['delivery_cost']) : 0.00;

    // Validate discounts and tenant items
    $validated_items = [];
    $has_pending_discount = false;
    $gross_subtotal = 0.00;
    $total_discount_savings = 0.00;
    $total_return_credits = 0.00;
    $net_subtotal = 0.00;

    foreach ($cart_items as $item) {
        $item_type = isset($item['item_type']) ? $item['item_type'] : 'STANDARD';
        $p_qty = max(1, intval($item['qty']));

        if ($item_type === 'RETURN_CREDIT') {
            $p_price = -abs(floatval($item['price']));
            $credit_val = round(abs($p_price) * $p_qty, 2);
            $total_return_credits += $credit_val;

            $item['price'] = $p_price;
            $item['authoritative_unit_price'] = $p_price;
            $item['line_gross'] = -$credit_val;
            $item['discount_amount'] = 0.00;
            $item['discount_percent'] = 0.00;
            $item['line_net'] = -$credit_val;
            $item['discount_request_id'] = null;
            $item['discount_status'] = null;
            $item['stock'] = 999999;
            $validated_items[] = $item;
            continue;
        }

        $p_price = max(0, floatval($item['price']));

        if ($item_type === 'STANDARD') {
            $p_id = intval($item['id']);
            $stmt_pcheck = $pdo->prepare("SELECT p_name, p_qty, p_current_price, p_featured_photo FROM tbl_product WHERE p_id = ? AND supplier_id = ?");
            $stmt_pcheck->execute(array($p_id, $supplier_id));
            $p_row = $stmt_pcheck->fetch(PDO::FETCH_ASSOC);
            if ($p_row) {
                $p_price = floatval(preg_replace('/[^0-9.]/', '', $p_row['p_current_price']));
                $item['price'] = $p_price;
                $item['stock'] = intval($p_row['p_qty']);
                $item['photo'] = !empty($p_row['p_featured_photo']) ? $p_row['p_featured_photo'] : '';
            }
        } else {
            $item['stock'] = 999999;
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

    if ($has_pending_discount) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Payment locked: One or more item discount requests are pending approval. Please complete or cancel pending discount requests before proceeding to checkout.'
        ]);
        exit;
    }

    $_SESSION['pos_cart'] = $validated_items;
    $_SESSION['supplier_checkout_cart'] = [
        'supplier_id' => $supplier_id,
        'created_at' => date('Y-m-d H:i:s'),
        'customer' => [
            'customer_type' => $customer_type,
            'customer_id' => $customer_id,
            'customer_name' => $customer_name,
            'customer_email' => $customer_email,
            'customer_phone' => $customer_phone,
            'customer_address' => $customer_address,
            'location_brgy_id' => $location_brgy_id,
            'brgy_name' => $brgy_name,
            'is_location_active' => $is_location_active
        ],
        'fulfillment' => [
            'delivery_type' => $delivery_type,
            'delivery_cost' => $delivery_cost
        ],
        'payment_method' => $payment_method,
        'items' => $validated_items,
        'gross_subtotal' => $gross_subtotal,
        'total_discount_savings' => $total_discount_savings,
        'total_return_credits' => $total_return_credits,
        'net_subtotal' => $net_subtotal,
        'grand_total' => max(0, ($net_subtotal + $delivery_cost) - $total_return_credits)
    ];

    echo json_encode(['status' => 'success', 'redirect' => 'checkout.php']);
    exit;
}

// Handle POS Session Cart Clear
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pos_action']) && $_POST['pos_action'] === 'clear_session_cart') {
    header('Content-Type: application/json');
    unset($_SESSION['pos_cart']);
    unset($_SESSION['supplier_checkout_cart']);
    echo json_encode(['status' => 'success']);
    exit;
}

// Handle POS Order Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pos_action']) && $_POST['pos_action'] === 'complete_sale') {
    $paying_po_id = isset($_POST['paying_po_id']) ? trim($_POST['paying_po_id']) : '';
if (empty($paying_po_id) && !empty($_GET['po_id'])) {
    $paying_po_id = trim($_GET['po_id']);
} elseif (empty($paying_po_id) && !empty($_GET['payment_id'])) {
    $paying_po_id = trim($_GET['payment_id']);
}

    if (!empty($paying_po_id)) {
        // -------------------------------------------------------------
        // EXISTING PURCHASE ORDER PAYMENT PROCESSING
        // -------------------------------------------------------------
        $stmt_pay_check = $pdo->prepare("SELECT * FROM tbl_payment WHERE payment_id = ? AND supplier_id = ? LIMIT 1");
        $stmt_pay_check->execute([$paying_po_id, $supplier_id]);
        $existing_payment = $stmt_pay_check->fetch(PDO::FETCH_ASSOC);

        if (!$existing_payment) {
            $pos_order_error = "Purchase Order (" . htmlspecialchars($paying_po_id) . ") not found or unauthorized.";
        } elseif (strtolower($existing_payment['payment_status'] ?? '') === 'paid') {
            $pos_order_error = "This Purchase Order has already been paid and settled.";
        } else {
            // Authoritative line items & calculations (supporting added items upon resumption)
            $cart_submitted_raw = isset($_POST['cart_items']) ? $_POST['cart_items'] : '';
            $submitted_cart = is_string($cart_submitted_raw) ? json_decode($cart_submitted_raw, true) : (is_array($cart_submitted_raw) ? $cart_submitted_raw : []);

            $stmt_po_items = $pdo->prepare("SELECT * FROM tbl_order WHERE payment_id = ? AND supplier_id = ? ORDER BY id ASC");
            $stmt_po_items->execute([$paying_po_id, $supplier_id]);
            $po_db_items = $stmt_po_items->fetchAll(PDO::FETCH_ASSOC);

            // Load product catalog for authoritative price & stock validation
            $stmt_p_auth = $pdo->prepare("SELECT p_id, p_name, p_current_price, p_new_price, p_qty FROM tbl_product WHERE supplier_id = ?");
            $stmt_p_auth->execute([$supplier_id]);
            $auth_prod_map = [];
            while ($pr = $stmt_p_auth->fetch(PDO::FETCH_ASSOC)) {
                $auth_prod_map[$pr['p_id']] = $pr;
            }

            $computed_subtotal = 0.0;
            $computed_discount_total = 0.0;
            $validated_items = [];
            $items_were_modified = false;

            if (!empty($submitted_cart) && is_array($submitted_cart)) {
                $items_were_modified = true;
                foreach ($submitted_cart as $item) {
                    $item_type = isset($item['item_type']) ? $item['item_type'] : 'STANDARD';
                    $p_id = intval($item['id'] ?? ($item['product_id'] ?? 0));
                    $qty = max(1, intval($item['qty'] ?? ($item['quantity'] ?? 1)));
                    $size = isset($item['size']) ? trim($item['size']) : '';
                    $color = isset($item['color']) ? trim($item['color']) : '';
                    $p_details = isset($item['product_details']) ? trim($item['product_details']) : '';
                    $sp_ref = isset($item['special_order_reference']) ? trim($item['special_order_reference']) : '';

                    if ($item_type === 'SPECIAL_ORDER') {
                        $p_name = !empty($item['name']) ? trim($item['name']) : 'Special Custom Item';
                        $unit_price = max(0, floatval($item['price'] ?? 0));
                    } else {
                        if ($p_id > 0 && isset($auth_prod_map[$p_id])) {
                            $p_info = $auth_prod_map[$p_id];
                            $p_name = $p_info['p_name'];
                            $unit_price = (floatval($p_info['p_new_price'] ?? 0) > 0) ? floatval($p_info['p_new_price']) : floatval($p_info['p_current_price'] ?? ($item['price'] ?? 0));
                        } else {
                            $p_name = !empty($item['name']) ? trim($item['name']) : 'Store Catalog Item';
                            $unit_price = max(0, floatval($item['price'] ?? 0));
                        }
                    }

                    $disc_pct = floatval($item['discount_percent'] ?? 0);
                    $disc_amt = floatval($item['discount_amount'] ?? 0);
                    $line_gross = round($qty * $unit_price, 2);
                    if ($disc_amt <= 0 && $disc_pct > 0) {
                        $disc_amt = round($line_gross * ($disc_pct / 100), 2);
                    }
                    $line_net = max(0, $line_gross - $disc_amt);

                    $computed_subtotal += $line_gross;
                    $computed_discount_total += $disc_amt;

                    $validated_items[] = [
                        'id' => $p_id,
                        'name' => $p_name,
                        'qty' => $qty,
                        'price' => $unit_price,
                        'authoritative_unit_price' => $unit_price,
                        'size' => $size,
                        'color' => $color,
                        'item_type' => $item_type,
                        'product_details' => $p_details,
                        'special_order_reference' => $sp_ref,
                        'discount_percent' => $disc_pct,
                        'discount_amount' => $disc_amt,
                        'line_net' => $line_net
                    ];
                }
            } else {
                // Fallback: parse original DB items
                foreach ($po_db_items as $p_it) {
                    $qty = max(1, intval($p_it['quantity']));
                    $price = max(0, floatval($p_it['unit_price']));
                    $disc_pct = floatval($p_it['discount_percent'] ?? 0);
                    $disc_amt = floatval($p_it['discount_amount'] ?? 0);
                    $line_gross = round($qty * $price, 2);
                    if ($disc_amt <= 0 && $disc_pct > 0) {
                        $disc_amt = round($line_gross * ($disc_pct / 100), 2);
                    }
                    $line_net = max(0, $line_gross - $disc_amt);

                    $computed_subtotal += $line_gross;
                    $computed_discount_total += $disc_amt;

                    $validated_items[] = [
                        'id' => $p_it['product_id'],
                        'name' => $p_it['product_name'],
                        'qty' => $qty,
                        'price' => $price,
                        'authoritative_unit_price' => $price,
                        'size' => $p_it['size'],
                        'color' => $p_it['color'],
                        'item_type' => $p_it['item_type'],
                        'product_details' => $p_it['product_details'],
                        'special_order_reference' => $p_it['special_order_reference'],
                        'discount_percent' => $disc_pct,
                        'discount_amount' => $disc_amt,
                        'line_net' => $line_net
                    ];
                }
            }

            $is_location_active = (!empty($_POST['is_location_delivery']) && $_POST['is_location_delivery'] == 1);
            $delivery_cost = 0.0;
            if ($is_location_active && isset($_POST['delivery_cost'])) {
                $delivery_cost = floatval($_POST['delivery_cost']);
            } elseif (!$items_were_modified) {
                $delivery_cost = max(0, floatval($existing_payment['paid_amount']) - ($computed_subtotal - $computed_discount_total));
            }

            $grand_total = max(0, $computed_subtotal - $computed_discount_total + $delivery_cost);

            $payment_method = isset($_POST['payment_method']) ? trim($_POST['payment_method']) : 'Cash (OTC)';
            $amount_tendered = isset($_POST['amount_tendered']) ? floatval($_POST['amount_tendered']) : 0.00;

            if (stripos($payment_method, 'Cash') !== false && $amount_tendered < $grand_total) {
                $pos_order_error = "Payment failed: Amount tendered (₱" . number_format($amount_tendered, 2) . ") is less than the total amount due (₱" . number_format($grand_total, 2) . ").";
            } else {
                if (stripos($payment_method, 'Cash') === false && $amount_tendered <= 0) {
                    $amount_tendered = $grand_total;
                }
                $change_amount = max(0, $amount_tendered - $grand_total);
                $payment_date = date('Y-m-d H:i:s');

                $tx_info = 'POS Payment for ' . $paying_po_id . ' - Method: ' . $payment_method . ' | Tendered: ₱' . number_format($amount_tendered, 2) . ' | Change: ₱' . number_format($change_amount, 2);
                if ($computed_discount_total > 0) {
                    $tx_info .= ' | Total Discounts: -₱' . number_format($computed_discount_total, 2);
                }

                // Atomic transaction update to tbl_payment & tbl_order
                $pdo->beginTransaction();
                try {
                    if ($items_were_modified) {
                        // Calculate stock difference
                        $old_stock_map = [];
                        foreach ($po_db_items as $old_it) {
                            if (($old_it['item_type'] ?? 'STANDARD') === 'STANDARD') {
                                $pid = (int)$old_it['product_id'];
                                $old_stock_map[$pid] = ($old_stock_map[$pid] ?? 0) + (int)$old_it['quantity'];
                            }
                        }
                        $new_stock_map = [];
                        foreach ($validated_items as $new_it) {
                            if ($new_it['item_type'] === 'STANDARD' && $new_it['id'] > 0) {
                                $pid = (int)$new_it['id'];
                                $new_stock_map[$pid] = ($new_stock_map[$pid] ?? 0) + (int)$new_it['qty'];
                            }
                        }
                        $all_pids = array_unique(array_merge(array_keys($old_stock_map), array_keys($new_stock_map)));
                        foreach ($all_pids as $pid) {
                            $old_q = $old_stock_map[$pid] ?? 0;
                            $new_q = $new_stock_map[$pid] ?? 0;
                            $diff = $new_q - $old_q;
                            if ($diff > 0) {
                                // More items added -> deduct additional stock
                                $stmt_ded = $pdo->prepare("UPDATE tbl_product SET p_qty = GREATEST(0, p_qty - ?) WHERE p_id = ? AND supplier_id = ?");
                                $stmt_ded->execute([$diff, $pid, $supplier_id]);
                            } elseif ($diff < 0) {
                                // Items removed -> restore stock
                                $stmt_res = $pdo->prepare("UPDATE tbl_product SET p_qty = p_qty + ? WHERE p_id = ? AND supplier_id = ?");
                                $stmt_res->execute([abs($diff), $pid, $supplier_id]);
                            }
                        }

                        // Re-sync tbl_order
                        $stmt_del_ord = $pdo->prepare("DELETE FROM tbl_order WHERE payment_id = ? AND supplier_id = ?");
                        $stmt_del_ord->execute([$paying_po_id, $supplier_id]);

                        foreach ($validated_items as $v_it) {
                            $stmt_ins_ord = $pdo->prepare("
                                INSERT INTO tbl_order (
                                    product_id, product_name, size, color, quantity, unit_price,
                                    payment_id, supplier_id, item_type,
                                    special_order_reference, product_details, discount_percent, discount_amount
                                ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)
                            ");
                            $stmt_ins_ord->execute([
                                $v_it['id'],
                                $v_it['name'],
                                $v_it['size'],
                                $v_it['color'],
                                $v_it['qty'],
                                number_format($v_it['price'], 2, '.', ''),
                                $paying_po_id,
                                $supplier_id,
                                $v_it['item_type'],
                                $v_it['special_order_reference'],
                                $v_it['product_details'],
                                $v_it['discount_percent'],
                                $v_it['discount_amount']
                            ]);
                        }
                    }

                    $stmt_up_pay = $pdo->prepare("
                        UPDATE tbl_payment SET
                            payment_status = 'Paid',
                            payment_method = ?,
                            payment_date = ?,
                            bank_transaction_info = ?,
                            paid_amount = ?,
                            shipping_status = CASE WHEN shipping_status = 'Pending' THEN 'Completed' ELSE shipping_status END
                        WHERE (payment_id = ? OR txnid = ?) AND supplier_id = ?
                    ");
                    $stmt_up_pay->execute([
                        $payment_method,
                        $payment_date,
                        $tx_info,
                        number_format($grand_total, 2, '.', ''),
                        $paying_po_id,
                        $paying_po_id,
                        $supplier_id
                    ]);

                    $pdo->commit();
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $pos_order_error = "Database transaction failed while recording payment: " . $e->getMessage();
                }

                if (empty($pos_order_error)) {
                    // Send notification email
                    $cust_email = $existing_payment['customer_email'];
                    $cust_name = $existing_payment['customer_name'] ?: 'Customer';
                    if (!empty($cust_email)) {
                        $subject_customer = "Payment Receipt - " . $paying_po_id;
                        $email_body = "<html><body><h3>Dear " . htmlspecialchars($cust_name) . ",</h3><p>Your payment of <strong>₱" . number_format($grand_total, 2) . "</strong> for Purchase Order <strong>" . htmlspecialchars($paying_po_id) . "</strong> has been confirmed and marked as <strong>PAID</strong>.</p><p>Thank you for your business!</p></body></html>";
                        send_system_email($cust_email, $subject_customer, $email_body);
                    }

                    // Prepare Receipt for modal & 200mm thermal printing
                    $pos_success_receipt = array(
                        'payment_id' => $paying_po_id,
                        'payment_date' => $payment_date,
                        'payment_method' => $payment_method,
                        'customer_name' => $cust_name,
                        'customer_phone' => $existing_payment['customer_phone'] ?? '',
                        'customer_email' => $cust_email,
                        'customer_address' => $existing_payment['customer_address'] ?? 'Store Pick-up / Over the Counter',
                        'items' => $validated_items,
                        'gross_subtotal' => $computed_subtotal,
                        'total_discount_savings' => $computed_discount_total,
                        'subtotal' => ($computed_subtotal - $computed_discount_total),
                        'delivery_cost' => $delivery_cost,
                        'grand_total' => $grand_total,
                        'amount_tendered' => $amount_tendered,
                        'change_amount' => $change_amount,
                        'supplier_name' => $supplier_info['supplier_name'],
                        'supplier_address' => $supplier_info['supplier_address'],
                        'supplier_phone' => $supplier_info['supplier_phone'],
                        'cashier_name' => (!empty($_SESSION['supplier_user']['full_name']) ? $_SESSION['supplier_user']['full_name'] : (!empty($_SESSION['supplier_user']['username']) ? $_SESSION['supplier_user']['username'] : 'Cashier'))
                    );

                    // Clear temporary session carts
                    unset($_SESSION['pos_cart']);
                    unset($_SESSION['supplier_checkout_cart']);
                }
            }
        }
    } else {
        // Standard normal POS sale
        $cart_data_json = isset($_POST['cart_items']) ? $_POST['cart_items'] : '[]';
        $cart_items = json_decode($cart_data_json, true);

        if (!empty($cart_items)) {
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

            $payment_method = isset($_POST['payment_method']) ? $_POST['payment_method'] : 'Cash (OTC)';
            $delivery_type = $is_location_active ? 'delivery' : 'pickup';
            $delivery_cost = ($is_location_active && isset($_POST['delivery_cost'])) ? floatval($_POST['delivery_cost']) : 0.00;
            $amount_tendered = isset($_POST['amount_tendered']) ? floatval($_POST['amount_tendered']) : 0.00;

            // Authoritative Cart Validation & Discount Verification
            $has_pending_discount = false;
            $validated_items = [];
            $gross_subtotal = 0.00;
            $total_discount_savings = 0.00;
            $total_return_credits = 0.00;
            $net_subtotal = 0.00;

            foreach ($cart_items as $item) {
                $item_type = isset($item['item_type']) ? $item['item_type'] : 'STANDARD';
                $p_qty = max(1, intval($item['qty']));

                if ($item_type === 'RETURN_CREDIT') {
                    $p_price = -abs(floatval($item['price']));
                    $credit_val = round(abs($p_price) * $p_qty, 2);
                    $total_return_credits += $credit_val;

                    $item['price'] = $p_price;
                    $item['authoritative_unit_price'] = $p_price;
                    $item['line_gross'] = -$credit_val;
                    $item['discount_amount'] = 0.00;
                    $item['discount_percent'] = 0.00;
                    $item['line_net'] = -$credit_val;
                    $item['discount_request_id'] = null;
                    $item['discount_status'] = null;
                    $validated_items[] = $item;
                    continue;
                }

                $p_price = max(0, floatval($item['price']));

                if ($item_type === 'STANDARD') {
                    $p_id = intval($item['id']);
                    $stmt_pcheck = $pdo->prepare("SELECT p_name, p_current_price FROM tbl_product WHERE p_id = ? AND supplier_id = ?");
                    $stmt_pcheck->execute(array($p_id, $supplier_id));
                    $p_row = $stmt_pcheck->fetch(PDO::FETCH_ASSOC);
                    if ($p_row) {
                        $p_price = floatval(preg_replace('/[^0-9.]/', '', $p_row['p_current_price']));
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

            if ($has_pending_discount) {
                $pos_order_error = "Payment locked: One or more item discount requests are pending approval. Please complete or cancel pending discount requests before completing the sale.";
            } else {
                $subtotal = $net_subtotal;
                $net_payable = ($subtotal + $delivery_cost) - $total_return_credits;
                $grand_total = max(0, $net_payable);
                $refund_due = ($net_payable < 0) ? abs($net_payable) : 0.00;
                $change_amount = max(0, $amount_tendered - $grand_total);

                $payment_id = 'POS-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));
                $payment_date = date('Y-m-d H:i:s');

                $tx_info = 'POS Terminal - Method: ' . $payment_method . ' | Tendered: ₱' . number_format($amount_tendered, 2) . ' | Change: ₱' . number_format($change_amount, 2);
                if ($total_discount_savings > 0) {
                    $tx_info .= ' | Total Discounts: -₱' . number_format($total_discount_savings, 2);
                }
                if ($total_return_credits > 0) {
                    $tx_info .= ' | Trade-In Return Credits: -₱' . number_format($total_return_credits, 2);
                }
                if ($refund_due > 0) {
                    $tx_info .= ' | Refund Due to Customer: ₱' . number_format($refund_due, 2);
                }

                // Insert into tbl_payment
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
                    $payment_method,
                    'Paid',
                    ($delivery_type === 'delivery') ? 'Pending' : 'Completed',
                    $payment_id,
                    $supplier_id
                ));

                // Insert items into tbl_order and update stock & link discount requests & returns
                foreach ($validated_items as $item) {
                    $item_type = isset($item['item_type']) ? $item['item_type'] : 'STANDARD';
                    $p_qty = max(1, intval($item['qty']));
                    $p_orig_unit_price = floatval($item['authoritative_unit_price']);
                    $p_line_net = floatval($item['line_net']);
                    $p_effective_unit_price = ($p_qty > 0) ? round($p_line_net / $p_qty, 2) : $p_orig_unit_price;

                    if ($item_type === 'RETURN_CREDIT') {
                        $orig_payment_id = !empty($item['original_payment_id']) ? trim($item['original_payment_id']) : (!empty($item['payment_id']) ? trim($item['payment_id']) : '');
                        $orig_order_id = !empty($item['original_order_id']) ? (int)$item['original_order_id'] : (!empty($item['order_item_id']) ? (int)$item['order_item_id'] : 0);
                        $orig_p_id = !empty($item['product_id']) ? (int)$item['product_id'] : 0;
                        $abs_unit_price = abs($p_orig_unit_price);
                        $credit_amount = round($abs_unit_price * $p_qty, 2);
                        $condition = !empty($item['condition']) ? trim($item['condition']) : 'Good (Restock)';
                        $ret_reason = !empty($item['return_reason']) ? trim($item['return_reason']) : 'Store Exchange';
                        
                        $mgr_override_name = '';
                        if (!empty($item['manager_override'])) {
                            if (is_array($item['manager_override']) && !empty($item['manager_override']['manager_name'])) {
                                $mgr_override_name = $item['manager_override']['manager_name'];
                            } elseif (is_string($item['manager_override'])) {
                                $mgr_override_name = $item['manager_override'];
                            }
                        }
                        if (empty($mgr_override_name) && !empty($item['manager_override_name'])) {
                            $mgr_override_name = trim($item['manager_override_name']);
                        }

                        $ret_notes = 'In-Register Trade-In / Exchange on POS Sale ' . $payment_id . ($orig_payment_id ? ' (Original Inv: ' . $orig_payment_id . ')' : '');
                        if (!empty($mgr_override_name)) {
                            $ret_notes .= ' | Overridden by Manager: ' . $mgr_override_name;
                        }

                        ensure_return_schema($pdo);
                        $return_ref = generate_unique_return_reference($pdo, $supplier_id);
                        $ret_date = date('Y-m-d H:i:s');
                        $current_uid = !empty($_SESSION['supplier_user']['id']) ? (int)$_SESSION['supplier_user']['id'] : null;
                        $current_uname = !empty($_SESSION['supplier_user']['full_name']) ? $_SESSION['supplier_user']['full_name'] : 'Supplier Staff';
                        $current_urole = !empty($_SESSION['supplier_user']['role']) ? normalize_supplier_role($_SESSION['supplier_user']['role']) : 'CASHIER';

                        // 1. Insert into tbl_returns
                        $stmt_ins_ret = $pdo->prepare("
                            INSERT INTO tbl_returns (
                                return_reference, payment_id, order_id, order_item_id, supplier_id,
                                customer_id, customer_name, customer_email, customer_phone,
                                return_date, refund_method, refund_amount, status,
                                requested_by_id, requested_by_name, requested_by_role,
                                approver_id, approver_name, approver_role, approver_remarks,
                                approved_at, completed_at, processed_by, notes,
                                created_at, updated_at
                            ) VALUES (
                                ?, ?, ?, ?, ?,
                                ?, ?, ?, ?,
                                ?, ?, ?, 'COMPLETED',
                                ?, ?, ?,
                                ?, ?, ?, ?,
                                CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, ?, ?,
                                CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
                            ) RETURNING return_id
                        ");
                        $stmt_ins_ret->execute([
                            $return_ref,
                            $orig_payment_id ?: $payment_id,
                            $orig_order_id,
                            $orig_order_id,
                            $supplier_id,
                            $customer_id,
                            $customer_name,
                            $customer_email,
                            $customer_phone,
                            $ret_date,
                            'Store Credit / Exchange',
                            $credit_amount,
                            $current_uid,
                            $current_uname,
                            $current_urole,
                            $current_uid,
                            $current_uname,
                            $current_urole,
                            $ret_notes,
                            $current_uname,
                            $ret_notes
                        ]);
                        $new_return_id_row = $stmt_ins_ret->fetch(PDO::FETCH_ASSOC);
                        $new_return_id = $new_return_id_row ? (int)$new_return_id_row['return_id'] : 0;

                        // 2. Insert into tbl_return_items
                        if ($new_return_id > 0) {
                            $stmt_ins_ri = $pdo->prepare("
                                INSERT INTO tbl_return_items (
                                    return_id, return_reference, order_item_id, product_id, product_name,
                                    sku, size, color, item_type, special_order_reference, product_details,
                                    quantity_returned, unit_price, refund_amount, return_reason,
                                    condition, restock_status, notes, created_at
                                ) VALUES (
                                    ?, ?, ?, ?, ?,
                                    ?, ?, ?, ?, ?, ?,
                                    ?, ?, ?, ?,
                                    ?, ?, ?, CURRENT_TIMESTAMP
                                )
                            ");
                            $stmt_ins_ri->execute([
                                $new_return_id,
                                $return_ref,
                                $orig_order_id,
                                $orig_p_id,
                                $item['name'],
                                !empty($item['sku']) ? $item['sku'] : '',
                                !empty($item['size']) ? $item['size'] : '',
                                !empty($item['color']) ? $item['color'] : '',
                                !empty($item['item_type']) ? $item['item_type'] : 'RETURN_CREDIT',
                                !empty($item['special_order_reference']) ? $item['special_order_reference'] : '',
                                !empty($item['product_details']) ? $item['product_details'] : (!empty($item['spec_label']) ? $item['spec_label'] : ''),
                                $p_qty,
                                $abs_unit_price,
                                $credit_amount,
                                $ret_reason,
                                $condition,
                                in_array($condition, ['Good (Restock)', 'Resellable', 'Unopened', 'Good / Resalable']) ? 'RESTOCKED' : 'DEFECTIVE_HELD',
                                $ret_notes
                            ]);
                        }

                        // 3. Restock inventory if condition is Resellable/Good/Unopened
                        if (in_array($condition, ['Good (Restock)', 'Resellable', 'Unopened', 'Good / Resalable']) && $orig_p_id > 0) {
                            $stmt_restock = $pdo->prepare("UPDATE tbl_product SET p_qty = p_qty + ? WHERE p_id = ? AND supplier_id = ?");
                            $stmt_restock->execute([$p_qty, $orig_p_id, $supplier_id]);
                        }

                        // 4. Insert negative audit credit line into tbl_order
                        $p_size = 'Exchange Credit';
                        $p_color = 'Exchange Credit';
                        $p_details = 'Exchange Return from ' . ($orig_payment_id ?: 'Previous Invoice') . ' (Ref: ' . $return_ref . ')';
                        if (!empty($ret_reason)) {
                            $p_details .= ' | Reason: ' . $ret_reason;
                        }

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
                            $orig_p_id,
                            $item['name'],
                            $p_size,
                            $p_color,
                            strval($p_qty),
                            strval(-$abs_unit_price),
                            $payment_id,
                            $supplier_id,
                            'RETURN_CREDIT',
                            $return_ref,
                            $p_details
                        ));

                        continue;
                    }

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
                        $p_name = $item['name'];
                        $p_size = isset($item['size']) ? $item['size'] : '';
                        $p_color = isset($item['color']) ? $item['color'] : '';
                        $p_details = '';
                        if (!empty($item['discount_amount']) && $item['discount_amount'] > 0) {
                            $p_details = 'Discount: -₱' . number_format($item['discount_amount'], 2) . ' (' . number_format($item['discount_percent'], 1) . '%)';
                        }

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
                            'STANDARD',
                            '',
                            $p_details
                        ));

                        // Decrement Stock only for standard catalogue products
                        $statement_stock = $pdo->prepare("UPDATE tbl_product SET p_qty = GREATEST(0, p_qty - ?) WHERE p_id = ? AND supplier_id = ?");
                        $statement_stock->execute(array($p_qty, $p_id, $supplier_id));

                        // Automatic inventory & price rollover if stock reached 0 or 1
                        $statement_rollover = $pdo->prepare("UPDATE tbl_product 
                            SET p_qty = p_qty + p_new_qty,
                                p_current_price = CASE 
                                    WHEN (p_new_price IS NOT NULL AND p_new_price != '' AND NULLIF(regexp_replace(p_new_price, '[^0-9.]', '', 'g'), '')::numeric > NULLIF(regexp_replace(p_current_price, '[^0-9.]', '', 'g'), '')::numeric) 
                                    THEN p_new_price 
                                    ELSE p_current_price 
                                END,
                                p_new_qty = 0
                            WHERE p_id = ? 
                              AND (p_qty = 0 OR p_qty = 1) 
                              AND p_new_qty > (COALESCE(p_s_level, 10) * 0.1)");
                        $statement_rollover->execute(array($p_id));
                    }

                    // Update tbl_discount_requests with payment_id
                    if (!empty($item['discount_request_id'])) {
                        $stmt_link_dr = $pdo->prepare("UPDATE tbl_discount_requests SET payment_id = ?, final_item_value = ? WHERE request_id = ? AND supplier_id = ?");
                        $stmt_link_dr->execute(array($payment_id, $item['line_net'], $item['discount_request_id'], $supplier_id));
                    }
                }

                // Store receipt details for instant modal popup
                $pos_success_receipt = array(
                    'payment_id' => $payment_id,
                    'payment_date' => $payment_date,
                    'payment_method' => $payment_method,
                    'customer_name' => $customer_name,
                    'customer_phone' => $customer_phone,
                    'customer_email' => $customer_email,
                    'customer_address' => $customer_address,
                    'items' => $validated_items,
                    'gross_subtotal' => $gross_subtotal,
                    'total_discount_savings' => $total_discount_savings,
                    'total_return_credits' => $total_return_credits,
                    'refund_due' => $refund_due,
                    'subtotal' => $subtotal,
                    'delivery_cost' => $delivery_cost,
                    'grand_total' => $grand_total,
                    'amount_tendered' => $amount_tendered,
                    'change_amount' => $change_amount,
                    'supplier_name' => $supplier_info['supplier_name'],
                    'supplier_address' => $supplier_info['supplier_address'],
                    'supplier_phone' => $supplier_info['supplier_phone'],
                    'cashier_name' => (!empty($_SESSION['supplier_user']['full_name']) ? $_SESSION['supplier_user']['full_name'] : (!empty($_SESSION['supplier_user']['username']) ? $_SESSION['supplier_user']['username'] : 'Cashier'))
                );

                // Clear session cart on complete sale
                unset($_SESSION['pos_cart']);
                unset($_SESSION['supplier_checkout_cart']);
            }
        }
    }
}

// Active POS Cart & Customer Context Persistence
$active_pos_cart = [];
if (!empty($_SESSION['pos_cart']) && empty($pos_po_success_data) && empty($pos_success_receipt)) {
    $active_pos_cart = $_SESSION['pos_cart'];
} elseif (!empty($_SESSION['supplier_checkout_cart']['items']) && empty($pos_po_success_data) && empty($pos_success_receipt)) {
    $active_pos_cart = $_SESSION['supplier_checkout_cart']['items'];
}

// Compute initial cart totals for immediate server-side rendering
$init_gross_subtotal = 0.0;
$init_discount_total = 0.0;
$init_return_credit_total = 0.0;
$init_net_subtotal = 0.0;

if (!empty($active_pos_cart)) {
    foreach ($active_pos_cart as $ci) {
        $ci_q = max(1, intval($ci['qty'] ?? 1));
        $ci_item_type = $ci['item_type'] ?? 'STANDARD';

        if ($ci_item_type === 'RETURN_CREDIT') {
            $ci_p = abs(floatval($ci['price'] ?? 0));
            $ci_credit = round($ci_q * $ci_p, 2);
            $init_return_credit_total += $ci_credit;
        } else {
            $ci_p = max(0, floatval($ci['price'] ?? 0));
            $ci_gross = round($ci_q * $ci_p, 2);
            $ci_disc = floatval($ci['discount_amount'] ?? 0);
            if ($ci_disc <= 0 && !empty($ci['discount_percent'])) {
                $ci_disc = round($ci_gross * (floatval($ci['discount_percent']) / 100), 2);
            }
            $ci_net = max(0, $ci_gross - $ci_disc);

            $init_gross_subtotal += $ci_gross;
            $init_discount_total += $ci_disc;
            $init_net_subtotal += $ci_net;
        }
    }
}

$init_delivery_cost = (!empty($saved_is_location) && !empty($saved_fulfillment['delivery_cost'])) ? floatval($saved_fulfillment['delivery_cost']) : 0.00;
$init_grand_total = max(0, ($init_net_subtotal + $init_delivery_cost) - $init_return_credit_total);
$init_refund_due = max(0, $init_return_credit_total - ($init_net_subtotal + $init_delivery_cost));

// Normalize items in active POS cart to ensure all JS/UI fields exist
if (!empty($active_pos_cart)) {
    foreach ($active_pos_cart as &$pi) {
        $p_type = isset($pi['item_type']) ? $pi['item_type'] : 'STANDARD';
        $pi['item_type'] = $p_type;
        $pi['qty'] = max(1, intval($pi['qty']));

        if ($p_type === 'RETURN_CREDIT') {
            $pi['price'] = -abs(floatval($pi['price']));
            $pi['stock'] = 999999;
            if (!isset($pi['base_name']) || empty($pi['base_name'])) {
                $pi['base_name'] = isset($pi['name']) ? $pi['name'] : 'Return Credit';
            }
        } elseif ($p_type === 'SPECIAL_ORDER') {
            $pi['price'] = max(0, floatval($pi['price']));
            $pi['stock'] = 999999;
            if (!isset($pi['base_name']) || empty($pi['base_name'])) {
                $pi['base_name'] = isset($pi['name']) ? $pi['name'] : 'Product';
            }
        } else {
            $pi['price'] = max(0, floatval($pi['price']));
            if (!isset($pi['base_name']) || empty($pi['base_name'])) {
                $pi['base_name'] = isset($pi['name']) ? $pi['name'] : 'Product';
            }
            $p_id = intval($pi['id']);
            $stmt_pk = $pdo->prepare("SELECT p_name, p_qty, p_current_price FROM tbl_product WHERE p_id = ? AND supplier_id = ?");
            $stmt_pk->execute(array($p_id, $supplier_id));
            $pk_row = $stmt_pk->fetch(PDO::FETCH_ASSOC);
            if ($pk_row) {
                $pi['stock'] = intval($pk_row['p_qty']);
            } elseif (!isset($pi['stock'])) {
                $pi['stock'] = 0;
            }
        }
    }
    unset($pi);
    $_SESSION['pos_cart'] = array_values($active_pos_cart);
}

// Read saved customer & fulfillment info from session if present
$saved_checkout = !empty($_SESSION['supplier_checkout_cart']) ? $_SESSION['supplier_checkout_cart'] : null;
$saved_cust = !empty($saved_checkout['customer']) ? $saved_checkout['customer'] : null;
$saved_fulfillment = !empty($saved_checkout['fulfillment']) ? $saved_checkout['fulfillment'] : null;
$saved_payment_method = !empty($saved_checkout['payment_method']) ? $saved_checkout['payment_method'] : 'Cash (OTC)';

$saved_customer_type = (!empty($saved_cust['customer_type']) && in_array($saved_cust['customer_type'], ['walkin', 'registered'])) ? $saved_cust['customer_type'] : 'walkin';
$saved_customer_id = !empty($saved_cust['customer_id']) ? (int)$saved_cust['customer_id'] : 0;
$saved_customer_name = (!empty($saved_cust['customer_name']) && $saved_cust['customer_name'] !== 'Walk-in Customer') ? $saved_cust['customer_name'] : '';
$saved_customer_phone = !empty($saved_cust['customer_phone']) ? $saved_cust['customer_phone'] : '';
$saved_customer_email = (!empty($saved_cust['customer_email']) && $saved_cust['customer_email'] !== 'walkin@pos.local') ? $saved_cust['customer_email'] : '';
$saved_is_location = !empty($saved_cust['is_location_active']);
$saved_brgy_id = !empty($saved_cust['location_brgy_id']) ? (int)$saved_cust['location_brgy_id'] : 0;

// Fetch Active Products for Supplier with Category Details
$statement_prod = $pdo->prepare("SELECT p.*, ec.ecat_name, mc.mcat_name, tc.tcat_name 
    FROM tbl_product p 
    LEFT JOIN tbl_end_category ec ON p.ecat_id = ec.ecat_id 
    LEFT JOIN tbl_mid_category mc ON ec.mcat_id = mc.mcat_id
    LEFT JOIN tbl_top_category tc ON mc.tcat_id = tc.tcat_id
    WHERE p.supplier_id = ? AND p.p_is_active = 1 
    ORDER BY ec.ecat_name ASC, p.p_name ASC");
$statement_prod->execute(array($supplier_id));
$raw_products = $statement_prod->fetchAll(PDO::FETCH_ASSOC);

// Group Products by End Level Category (tbl_end_category)
$grouped_products = array();
$categories = array();
$parent_departments = array();
$mid_categories_list = array();
$categories_with_parent = array();
$total_inventory_items = count($raw_products);

foreach ($raw_products as $prod) {
    $has_ecat = (!empty($prod['ecat_id']) && intval($prod['ecat_id']) > 0 && !empty($prod['ecat_name']));
    $ecat_name = $has_ecat ? trim($prod['ecat_name']) : 'Uncategorized';
    $parent_dept = !empty($prod['tcat_name']) ? trim($prod['tcat_name']) : (!empty($prod['mcat_name']) ? trim($prod['mcat_name']) : 'General Hardware');
    $mcat_name = !empty($prod['mcat_name']) ? trim($prod['mcat_name']) : 'General Sub-System';
    
    // Grouping key: Relational End Level Category ID (ecat_id) creates 1 single card per category
    if ($has_ecat) {
        $group_key = 'ecat_' . intval($prod['ecat_id']);
        $card_title = $ecat_name;
    } else {
        $parsed_spec = parseConstructionProductDetails($prod['p_name']);
        $base_name = !empty($parsed_spec['base_name']) ? $parsed_spec['base_name'] : $prod['p_name'];
        $group_key = 'uncat_' . strtolower(preg_replace('/[^a-z0-9]/', '', $base_name));
        $card_title = $base_name;
    }

    if (!in_array($ecat_name, $categories)) {
        $categories[] = $ecat_name;
    }
    
    // Parent departments tracking
    if (!isset($parent_departments[$parent_dept])) {
        $parent_departments[$parent_dept] = array(
            'count' => 0,
            'mid_cats' => array()
        );
    }
    
    // Mid categories tracking
    $mid_key = $parent_dept . '___' . $mcat_name;
    if (!isset($mid_categories_list[$mid_key])) {
        $mid_categories_list[$mid_key] = array(
            'name' => $mcat_name,
            'dept' => $parent_dept,
            'count' => 0
        );
    }

    if (!in_array($ecat_name, $categories_with_parent)) {
        $categories_with_parent[] = array('name' => $ecat_name, 'dept' => $parent_dept, 'mcat' => $mcat_name);
    }

    $clean_price = floatval(preg_replace('/[^0-9.]/', '', strval($prod['p_current_price'])));
    $clean_stock = intval(preg_replace('/[^0-9]/', '', strval($prod['p_qty'])));
    $img_src = (!empty($prod['p_featured_photo']) && file_exists('../assets/uploads/'.$prod['p_featured_photo'])) 
        ? '../assets/uploads/'.$prod['p_featured_photo'] 
        : '../assets/uploads/photo-6.jpg';

    $parsed_spec = parseConstructionProductDetails($prod['p_name']);
    $spec_label = $parsed_spec['spec_label'];
    
    // If spec_label is default 'Standard' or empty, derive clean variant specification label
    if (empty($spec_label) || $spec_label === 'Standard') {
        $stripped = trim(preg_replace('/^' . preg_quote($card_title, '/') . '[\s\-\:\,]*/i', '', $prod['p_name']));
        $spec_label = !empty($stripped) ? $stripped : $prod['p_name'];
    }

    $variant_item = array(
        'id' => intval($prod['p_id']),
        'sku' => !empty($prod['p_sku']) ? $prod['p_sku'] : ('SKU-' . str_pad($prod['p_id'], 5, '0', STR_PAD_LEFT)),
        'name' => $prod['p_name'],
        'base_name' => $card_title,
        'spec_label' => $spec_label,
        'size' => $parsed_spec['size'],
        'thickness' => $parsed_spec['thickness'],
        'diameter' => $parsed_spec['diameter'],
        'color' => $parsed_spec['color'],
        'material' => $parsed_spec['material'],
        'weight_pack' => $parsed_spec['weight_pack'],
        'voltage' => $parsed_spec['voltage'],
        'power' => $parsed_spec['power'],
        'rated_current' => $parsed_spec['rated_current'],
        'length' => $parsed_spec['length'],
        'price' => $clean_price,
        'stock' => $clean_stock,
        'photo' => $img_src,
        'brand' => !empty($prod['p_brand']) ? $prod['p_brand'] : 'Generic'
    );

    if (!isset($grouped_products[$group_key])) {
        $parent_departments[$parent_dept]['count']++;
        $mid_categories_list[$mid_key]['count']++;
        $parent_departments[$parent_dept]['mid_cats'][$mcat_name] = true;

        $grouped_products[$group_key] = array(
            'group_key' => $group_key,
            'base_name' => $card_title,
            'ecat_id' => $prod['ecat_id'],
            'ecat_name' => $ecat_name,
            'mcat_name' => $mcat_name,
            'tcat_name' => $parent_dept,
            'brand' => !empty($prod['p_brand']) ? $prod['p_brand'] : 'Generic',
            'brands' => array(!empty($prod['p_brand']) ? $prod['p_brand'] : 'Generic'),
            'photo' => $img_src,
            'min_price' => $clean_price,
            'max_price' => $clean_price,
            'total_stock' => $clean_stock,
            'variants' => array($variant_item)
        );
    } else {
        $grouped_products[$group_key]['variants'][] = $variant_item;
        $grouped_products[$group_key]['total_stock'] += $clean_stock;
        if ($clean_price < $grouped_products[$group_key]['min_price']) {
            $grouped_products[$group_key]['min_price'] = $clean_price;
        }
        if ($clean_price > $grouped_products[$group_key]['max_price']) {
            $grouped_products[$group_key]['max_price'] = $clean_price;
        }
        // If current group photo is placeholder but variant has real image, use it
        if (strpos($grouped_products[$group_key]['photo'], 'photo-6.jpg') !== false && strpos($img_src, 'photo-6.jpg') === false) {
            $grouped_products[$group_key]['photo'] = $img_src;
        }
        $b = !empty($prod['p_brand']) ? $prod['p_brand'] : 'Generic';
        if (!in_array($b, $grouped_products[$group_key]['brands'])) {
            $grouped_products[$group_key]['brands'][] = $b;
        }
        if (count($grouped_products[$group_key]['brands']) > 1) {
            $grouped_products[$group_key]['brand'] = 'Multi-Brand';
        }
    }
}

// Fetch Registered Customers
$statement_cust = $pdo->prepare("SELECT cust_id, cust_name, cust_email, cust_phone, cust_address FROM tbl_customer ORDER BY cust_name ASC");
$statement_cust->execute();
$registered_customers = $statement_cust->fetchAll(PDO::FETCH_ASSOC);

// Fetch Barangays for Location selection
$brgy_list = [];
ensure_supplier_user_schema($pdo);
try {
    $statement_brgy = $pdo->prepare("SELECT brgy_id, brgy_name FROM tbl_brgy ORDER BY brgy_name ASC");
    $statement_brgy->execute();
    $brgy_list = $statement_brgy->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $brgy_list = [];
}

// Fetch Supplier Shipping Costs
$statement_sc = $pdo->prepare("SELECT country_id, amount FROM tbl_shipping_cost WHERE supplier_id=?");
$statement_sc->execute(array($supplier_id));
$supplier_shipping_rates = $statement_sc->fetchAll(PDO::FETCH_KEY_PAIR);

$statement_all = $pdo->prepare("SELECT amount FROM tbl_shipping_cost_all WHERE sca_id=1");
$statement_all->execute();
$default_shipping_rate = (float)($statement_all->fetchColumn() ?: 0);
?>

<style>
.pos-wrapper {
    margin-top: 10px;
}
#posProductGrid {
    display: grid !important;
    grid-template-columns: repeat(4, 1fr) !important;
    gap: 12px !important;
    max-height: 650px;
    overflow-y: auto;
    padding: 6px;
    margin-left: 0 !important;
    margin-right: 0 !important;
}
@media (max-width: 1199px) {
    #posProductGrid {
        grid-template-columns: repeat(3, 1fr) !important;
    }
}
@media (max-width: 767px) {
    #posProductGrid {
        grid-template-columns: repeat(2, 1fr) !important;
    }
}
@media (max-width: 480px) {
    #posProductGrid {
        grid-template-columns: repeat(1, 1fr) !important;
    }
}
.pos-product-item {
    width: 100% !important;
    padding: 0 !important;
    float: none !important;
}
.pos-product-card {
    background: #fff;
    border: 1.5px solid #e2e8f0;
    border-radius: 8px;
    padding: 10px;
    cursor: pointer;
    transition: all 0.2s ease-in-out;
    position: relative;
    box-shadow: 0 1px 4px rgba(0,0,0,0.04);
    height: 100%;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}
.pos-product-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(37, 99, 235, 0.18);
    border-color: #2563eb;
}
.pos-product-card.out-of-stock {
    opacity: 0.65;
    background: #f8fafc;
}
.pos-product-img {
    height: 105px;
    width: 100%;
    background-size: contain;
    background-repeat: no-repeat;
    background-position: center;
    background-color: #fff;
    border-radius: 6px;
    margin-bottom: 8px;
    border: 1px solid #f1f5f9;
}
.pos-cat-badge {
    position: absolute;
    top: 6px;
    left: 6px;
    background: rgba(30, 41, 59, 0.88);
    color: #fff;
    font-size: 10px;
    font-weight: 700;
    padding: 2px 7px;
    border-radius: 4px;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    max-width: 120px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    z-index: 2;
}
.pos-stock-badge {
    position: absolute;
    top: 6px;
    right: 6px;
    font-size: 10px;
    font-weight: 700;
    padding: 2px 7px;
    border-radius: 4px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.12);
    z-index: 2;
}
.pos-parent-info {
    margin-bottom: 6px;
    flex-grow: 1;
}
.pos-parent-name {
    font-size: 14px;
    font-weight: 800;
    color: #0f172a;
    line-height: 1.3;
    margin-bottom: 3px;
}
.pos-parent-meta {
    font-size: 11px;
    color: #64748b;
    margin-bottom: 6px;
}
.pos-variant-count-badge {
    display: inline-block;
    padding: 2px 7px;
    background: #eff6ff;
    color: #1d4ed8;
    border: 1px solid #bfdbfe;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 700;
    margin-bottom: 4px;
}
.pos-variant-count-badge.single {
    background: #f1f5f9;
    color: #475569;
    border-color: #cbd5e1;
}
.pos-product-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-top: 1px solid #f1f5f9;
    padding-top: 8px;
    margin-top: 4px;
}
.pos-price-val {
    font-size: 14px;
    font-weight: 800;
    color: #1d4ed8;
}
.pos-card-add-btn {
    font-size: 12px;
    font-weight: 700;
    padding: 4px 10px;
    border-radius: 4px;
}
.pos-variant-chip {
    width: 100%;
    min-width: 0;
    min-height: 52px;
    height: auto;
    padding: 8px 14px;
    font-size: 14px;
    font-weight: 700;
    color: #0f172a;
    background: #ffffff;
    border: 2px solid #cbd5e1;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    cursor: pointer;
    transition: all 0.15s ease-in-out;
    box-shadow: 0 1px 3px rgba(0,0,0,0.06);
    user-select: none;
    text-decoration: none !important;
}
@media (max-width: 640px) {
    #vModalChipsList {
        grid-template-columns: 1fr !important;
    }
}
.pos-variant-chip:hover {
    border-color: #0284c7;
    background: #f0f9ff;
    color: #0284c7;
    transform: translateY(-1px);
    box-shadow: 0 3px 8px rgba(2, 132, 199, 0.18);
}
.pos-variant-chip.active {
    background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%) !important;
    border-color: #0284c7 !important;
    color: #ffffff !important;
    box-shadow: 0 4px 10px rgba(2, 132, 199, 0.35) !important;
}
.pos-variant-chip.active .chip-price-badge {
    background: #ffffff !important;
    color: #0369a1 !important;
    border-color: #ffffff !important;
}
.pos-variant-chip.disabled {
    opacity: 0.55;
    cursor: not-allowed;
    background: #f1f5f9;
    border-color: #e2e8f0;
    color: #94a3b8;
}
.chip-price-badge {
    font-size: 14px;
    font-weight: 800;
    padding: 3px 8px;
    background: #ecfdf5;
    color: #047857;
    border-radius: 4px;
    border: 1px solid #a7f3d0;
    display: inline-block;
    letter-spacing: -0.2px;
}
.pos-color-badge {
    display: inline-block;
    padding: 1px 7px;
    border-radius: 10px;
    font-size: 11px;
    font-weight: 800;
    border: 1px solid #cbd5e1;
    background: #fff;
    color: #334155;
    text-transform: uppercase;
}
.pos-color-orange { background: #fff7ed; color: #c2410c; border-color: #fed7aa; }
.pos-color-green { background: #f0fdf4; color: #15803d; border-color: #bbf7d0; }
.pos-color-blue { background: #eff6ff; color: #1d4ed8; border-color: #bfdbfe; }
.pos-color-yellow { background: #fefce8; color: #a16207; border-color: #fef08a; }
.pos-color-red { background: #fef2f2; color: #b91c1c; border-color: #fecaca; }
.pos-color-black { background: #1e293b; color: #f8fafc; border-color: #0f172a; }
.pos-color-brown { background: #fdf8f6; color: #7c2d12; border-color: #fed7aa; }
.pos-color-stainless { background: #f1f5f9; color: #475569; border-color: #cbd5e1; }
.pos-color-galvanized { background: #f0fdfa; color: #0f766e; border-color: #99f6e4; }

.pos-cart-panel {
    background: #fff;
    border: 2px solid #e2e8f0;
    border-radius: 10px;
    box-shadow: 0 4px 14px rgba(0,0,0,0.08);
    padding: 18px;
    position: sticky;
    top: 15px;
}
.pos-cart-table th {
    font-size: 13px;
    font-weight: 700;
    text-transform: uppercase;
    color: #475569;
    border-bottom: 2px solid #e2e8f0;
    padding: 8px 4px;
}
.pos-cart-table td {
    vertical-align: middle !important;
    font-size: 14px;
}
.pos-qty-btn {
    padding: 4px 10px;
    font-size: 14px;
    font-weight: 800;
    min-width: 30px;
}
.pos-preset-btn {
    margin-right: 5px;
    margin-bottom: 5px;
    padding: 6px 12px;
    font-size: 13px;
    font-weight: 700;
}
.pos-category-accordion-wrapper {
    background: #ffffff;
    border: 1.5px solid #e2e8f0;
    border-radius: 8px;
    margin-bottom: 14px;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0,0,0,0.03);
    transition: border-color 0.2s ease;
}
.pos-cat-toggle-bar {
    padding: 7px 12px;
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
    cursor: pointer;
    user-select: none;
    transition: background 0.15s ease;
}
.pos-cat-toggle-bar:hover {
    background: #f1f5f9;
}
.pos-cat-collapsible-body {
    padding: 10px 10px 10px;
    transition: max-height 0.25s ease-in-out, opacity 0.2s ease, padding 0.2s ease;
    max-height: 400px;
    opacity: 1;
    overflow: hidden;
}
.pos-category-accordion-wrapper.collapsed .pos-cat-collapsible-body {
    max-height: 0 !important;
    opacity: 0 !important;
    padding: 0 10px !important;
}
.pos-category-accordion-wrapper.collapsed .pos-cat-toggle-bar {
    border-bottom: none;
}
.pos-dept-carousel-wrapper {
    position: relative;
    display: flex;
    align-items: center;
    margin-bottom: 8px;
    width: 100%;
}
.pos-dept-nav-btn {
    width: 32px;
    height: 38px;
    background: #ffffff;
    border: 1.5px solid #cbd5e1;
    border-radius: 6px;
    color: #475569;
    font-size: 13px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    flex-shrink: 0;
    transition: all 0.15s ease;
    box-shadow: 0 1px 3px rgba(0,0,0,0.06);
    z-index: 2;
}
.pos-dept-nav-btn:hover:not(:disabled) {
    background: #0284c7;
    color: #ffffff;
    border-color: #0284c7;
    box-shadow: 0 2px 6px rgba(2,132,199,0.3);
}
.pos-dept-nav-btn:disabled,
.pos-dept-nav-btn.disabled {
    opacity: 0.35;
    cursor: not-allowed;
    background: #f1f5f9;
    color: #94a3b8;
    border-color: #e2e8f0;
    box-shadow: none;
}
.pos-dept-tabs-bar {
    display: flex;
    flex-wrap: nowrap;
    overflow-x: auto;
    overflow-y: hidden;
    scroll-behavior: smooth;
    -webkit-overflow-scrolling: touch;
    gap: 6px;
    padding: 2px 6px;
    margin: 0;
    flex-grow: 1;
    scrollbar-width: thin;
    scrollbar-color: #cbd5e1 transparent;
}
.pos-dept-tabs-bar::-webkit-scrollbar {
    height: 4px;
}
.pos-dept-tabs-bar::-webkit-scrollbar-track {
    background: transparent;
}
.pos-dept-tabs-bar::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 4px;
}
.pos-dept-tabs-bar::-webkit-scrollbar-thumb:hover {
    background: #94a3b8;
}
.pos-dept-tab {
    height: 38px;
    min-height: 38px;
    padding: 0 14px;
    font-size: 12.5px;
    font-weight: 700;
    color: #475569;
    background: #ffffff;
    border: 1.5px solid #cbd5e1;
    border-radius: 6px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    cursor: pointer;
    transition: all 0.15s ease;
    box-shadow: 0 1px 2px rgba(0,0,0,0.03);
    white-space: nowrap;
    flex-shrink: 0;
    text-decoration: none !important;
}
.pos-dept-tab:hover {
    background: #f1f5f9;
    border-color: #0284c7;
    color: #0284c7;
    transform: translateY(-1px);
}
.pos-dept-tab.active {
    background: #0f172a !important;
    color: #ffffff !important;
    border-color: #0f172a !important;
    box-shadow: 0 2px 6px rgba(15,23,42,0.3) !important;
}
.pos-dept-tab.active i {
    color: #38bdf8;
}
.pos-dept-tab.active .badge {
    background: #334155 !important;
    color: #f8fafc !important;
}
.pos-category-filter-box {
    margin-bottom: 0;
    background: #f8fafc;
    border: 1.5px solid #e2e8f0;
    border-radius: 8px;
    padding: 8px 10px;
    max-height: 138px; /* Constrains to 3 clean visible rows */
    overflow-y: auto;
    overflow-x: hidden; /* Completely eliminates horizontal scroll */
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    align-content: flex-start;
}
.pos-category-filter-box::-webkit-scrollbar {
    width: 6px;
}
.pos-category-filter-box::-webkit-scrollbar-track {
    background: #edf2f7;
    border-radius: 4px;
}
.pos-category-filter-box::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 4px;
}
.pos-category-filter-box::-webkit-scrollbar-thumb:hover {
    background: #94a3b8;
}
.pos-cat-pill {
    height: 38px;
    min-height: 38px;
    padding: 0 14px;
    font-size: 12.5px;
    font-weight: 700;
    color: #334155;
    background: #ffffff;
    border: 1.5px solid #cbd5e1;
    border-radius: 6px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    cursor: pointer;
    transition: all 0.15s ease-in-out;
    box-shadow: 0 1px 2px rgba(0,0,0,0.03);
    white-space: nowrap;
    text-decoration: none !important;
}
.pos-cat-pill:hover {
    background: #f1f5f9;
    border-color: #0284c7;
    color: #0284c7;
    transform: translateY(-1px);
}
.pos-cat-pill.active {
    background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%) !important;
    color: #ffffff !important;
    border-color: #0284c7 !important;
    box-shadow: 0 2px 5px rgba(2, 132, 199, 0.3) !important;
}
.pos-cat-pill.active i {
    color: #bae6fd;
}

/* Compact POS Content Header & Content Area */
.content-header {
    padding: 6px 15px 2px 15px !important;
}
.content {
    padding-top: 6px !important;
}
.pos-compact-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
    min-height: 30px;
}
.pos-header-title {
    font-size: 16px !important;
    font-weight: 800 !important;
    color: #0f172a !important;
    margin: 0 !important;
    line-height: 1 !important;
    display: inline-flex !important;
    align-items: center !important;
    gap: 7px;
}
.pos-header-subtitle {
    font-size: 11.5px !important;
    color: #64748b !important;
    font-weight: 500 !important;
    border-left: 1.5px solid #cbd5e1;
    padding-left: 8px;
    margin-left: 4px;
    display: inline-block;
}
.pos-hdr-btn {
    height: 28px !important;
    padding: 3px 10px !important;
    font-size: 11.5px !important;
    font-weight: 600 !important;
    border-radius: 4px !important;
    display: inline-flex !important;
    align-items: center !important;
    gap: 5px !important;
    line-height: 1.2 !important;
    box-shadow: 0 1px 2px rgba(0,0,0,0.04);
}

/* POS Product Dual-View & List Mode */
.pos-product-list-container {
    background: #ffffff;
    border: 1.5px solid #e2e8f0;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0,0,0,0.03);
    margin-bottom: 14px;
}
.pos-product-list-table th {
    font-size: 11.5px;
    font-weight: 700;
    text-transform: uppercase;
    color: #475569;
    padding: 8px 10px;
    background: #f8fafc;
    border-bottom: 2px solid #cbd5e1 !important;
}
.pos-product-list-table td {
    vertical-align: middle !important;
    padding: 7px 10px;
    border-bottom: 1px solid #f1f5f9;
}
.pos-list-row {
    transition: background-color 0.15s ease;
    cursor: pointer;
}
.pos-list-row:hover {
    background-color: #f0f9ff !important;
}
.pos-list-row.out-of-stock {
    opacity: 0.6;
    background-color: #f8fafc;
}
.pos-view-btn-group .btn {
    height: 38px;
    padding: 6px 12px;
    font-size: 13px;
    font-weight: 700;
}
.pos-view-btn-group .btn.active {
    background-color: #0f172a !important;
    color: #ffffff !important;
    border-color: #0f172a !important;
}
.pos-search-kbd-badge {
    font-size: 10px;
    font-weight: 700;
    color: #64748b;
    background: #e2e8f0;
    padding: 2px 5px;
    border-radius: 3px;
    border: 1px solid #cbd5e1;
    margin-left: 4px;
}
.pos-search-hl {
    background-color: #fef08a !important;
    color: #0f172a !important;
    padding: 0 2px !important;
    border-radius: 2px !important;
    font-weight: 800 !important;
    text-decoration: none !important;
}
</style>

<section class="content-header">
    <div class="pos-compact-header">
        <div style="display: flex; align-items: center; flex-wrap: wrap; gap: 6px;">
            <h1 class="pos-header-title">
                <i class="fa fa-calculator" style="color: #2563eb; font-size: 15px;"></i> Point of Sale (POS)
            </h1>
            <span class="pos-header-subtitle hidden-xs">Over-the-Counter Sales &amp; Direct Billing</span>
        </div>
        <div style="display: flex; gap: 5px; align-items: center; flex-wrap: wrap;">
            <a href="user-manual.php" target="_blank" class="btn btn-info btn-xs pos-hdr-btn" style="background-color: #0284c7; border-color: #0369a1; font-weight: 700;">
                <i class="fa fa-book"></i> User Manual &amp; SOP
            </a>
            <?php if (is_admin_or_manager_role($pos_user_role) || is_supervisor_role($pos_user_role) || $pos_user_role === 'CASHIER'): ?>
                <a href="returns.php" class="btn btn-default btn-xs pos-hdr-btn">
                    <i class="fa fa-undo"></i> Return History
                </a>
                <a href="order.php" class="btn btn-default btn-xs pos-hdr-btn">
                    <i class="fa fa-list"></i> Order History
                </a>
            <?php endif; ?>
            <?php if (is_admin_or_manager_role($pos_user_role)): ?>
                <a href="index.php" class="btn btn-default btn-xs pos-hdr-btn">
                    <i class="fa fa-dashboard"></i> Dashboard
                </a>
            <?php endif; ?>
        </div>
    </div>
</section>

<section class="content">
    <?php if (!empty($pos_po_error_msg)): ?>
    <div class="alert alert-danger alert-dismissible" style="font-size: 14px; font-weight: bold; border-radius: 6px; margin-bottom: 15px;">
        <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
        <i class="fa fa-exclamation-triangle"></i> <?php echo htmlspecialchars($pos_po_error_msg); ?>
    </div>
    <?php endif; ?>

    <div class="row pos-wrapper">
        
        <!-- Left Side: Product Search & Catalog Grid -->
        <div class="col-md-7 col-lg-8">
            <div class="box box-primary" style="border-radius: 8px;">
                <div class="box-body">
                    
                    <!-- Search and Filters Toolbar -->
                    <div class="row" style="margin-bottom: 12px;">
                        <div class="col-md-5 col-sm-6 col-xs-12" style="margin-bottom: 6px;">
                            <div class="input-group">
                                <span class="input-group-addon" style="font-size: 15px; background-color: #f8fafc; border-color: #cbd5e1;"><i class="fa fa-search text-primary"></i></span>
                                <input type="search" 
                                       id="posSearchInput" 
                                       name="pos_catalog_search_term" 
                                       class="form-control input-lg" 
                                       style="height: 42px; font-size: 14px; font-weight: 500;" 
                                       placeholder="Search product by name, brand, spec, or SKU... (F2)" 
                                       autocomplete="off" 
                                       autocorrect="off" 
                                       autocapitalize="off" 
                                       spellcheck="false" 
                                       data-lpignore="true" 
                                       data-form-type="other"
                                       oninput="filterPOSProducts()"
                                       onkeyup="filterPOSProducts()"
                                       onkeydown="if(event.key === 'Enter') handlePOSSearchEnter(event);">
                                <span class="input-group-btn">
                                    <button class="btn btn-default input-lg" type="button" onclick="clearPOSSearch()" style="height: 42px;" title="Clear Search (Esc)"><i class="fa fa-times text-muted"></i></button>
                                </span>
                            </div>
                        </div>
                        <div class="col-md-7 col-sm-6 col-xs-12 text-right" style="display: flex; justify-content: flex-end; align-items: center; gap: 6px; flex-wrap: wrap;">
                            <button type="button" class="btn btn-primary input-lg" onclick="openHeldOrdersModal()" id="posHeldOrdersBtn" style="height: 38px; font-size: 12.5px; font-weight: 800; background-color: #4f46e5; border-color: #4338ca; color: #fff; padding: 6px 12px; border-radius: 4px; box-shadow: 0 1px 3px rgba(79,70,229,0.25); display: inline-flex; align-items: center; gap: 5px;" title="View & Resume Held / Parked Orders">
                                <i class="fa fa-pause-circle"></i> <span>Held Orders</span> <span class="badge" id="posHeldCountBadge" style="background:#fff; color:#4f46e5; font-weight:800; font-size:11px; margin-left:2px;"><?php echo $held_pos_count; ?></span>
                            </button>
                            <a href="product-add.php" class="btn btn-primary btn-sm" style="height: 38px; font-size: 12.5px; font-weight: 800; padding: 6px 12px; border-radius: 4px; display: inline-flex; align-items: center; gap: 4px;" title="Add New Product">
                                <i class="fa fa-plus"></i> New Product
                            </a>
                            <button type="button" class="btn btn-danger input-lg" onclick="openReturnModal()" style="height: 38px; font-size: 12.5px; font-weight: 800; background-color: #dc2626; border-color: #b91c1c; color: #fff; padding: 6px 12px; border-radius: 4px; box-shadow: 0 1px 3px rgba(220,38,38,0.25); display: inline-flex; align-items: center; gap: 4px;" title="Search completed orders and process item returns & refunds">
                                <i class="fa fa-undo"></i> RETURN ITEM(s)
                            </button>
                            <span class="text-muted" style="font-size: 12px; font-weight: 700; white-space: nowrap;">
                                <strong id="productCount" style="color: #0f172a; font-size: 13px;"><?php echo count($grouped_products); ?></strong> items
                            </span>
                        </div>
                    </div>

                    <!-- Hierarchical 2-Tier Category Navigation (Dedicated Collapsible Accordion Header with Persistent Memory) -->
                    <?php if (!empty($parent_departments)): ?>
                    <div class="pos-category-accordion-wrapper" id="posCategoryAccordion">
                        <!-- Dedicated Accordion Toggle Bar -->
                        <div class="pos-cat-toggle-bar" onclick="togglePOSCategories()">
                            <div class="pos-cat-toggle-left" style="display: flex; align-items: center; gap: 8px;">
                                <span style="display: inline-flex; align-items: center; justify-content: center; width: 22px; height: 22px; border-radius: 4px; background: #e0f2fe; color: #0284c7; font-size: 11px;">
                                    <i class="fa fa-sitemap"></i>
                                </span>
                                <span style="font-weight: 800; font-size: 13px; color: #1e293b;">Category Filters</span>
                                <span id="posActiveFilterBreadcrumb" class="label label-info" style="font-size: 11px; font-weight: 700; background-color: #0284c7; border-radius: 4px; padding: 2px 8px;">
                                    Top Categories &bull; All in Selected
                                </span>
                            </div>
                            <button type="button" class="btn btn-xs btn-default pos-cat-toggle-btn" id="posCatToggleBtn" style="font-weight: 700; font-size: 11px; border-radius: 4px; display: inline-flex; align-items: center; gap: 4px;" title="Toggle category panel">
                                <i class="fa fa-chevron-up" id="posCatToggleIcon"></i> <span id="posCatToggleText">Collapse</span>
                            </button>
                        </div>

                        <!-- Collapsible Body (Tier 1 + Tier 2) -->
                        <div class="pos-cat-collapsible-body" id="posCatCollapsibleBody">
                            <!-- Tier 1: Main Department Tabs (Single-Row Horizontal Carousel with Left/Right Nav Chevrons) -->
                            <div class="pos-dept-carousel-wrapper">
                                <button type="button" class="pos-dept-nav-btn" id="posDeptNavPrev" onclick="scrollDeptCarousel(-260)" title="Scroll Left" aria-label="Scroll Left" style="margin-right: 4px;">
                                    <i class="fa fa-chevron-left"></i>
                                </button>
                                <div class="pos-dept-tabs-bar" id="posDeptTabsBar" onscroll="updateDeptNavState()">
                                    <button type="button" class="pos-dept-tab active" data-dept="all" onclick="filterDepartment('all', this)">
                                        <i class="fa fa-th-large"></i> Top Categories (<?php echo count($grouped_products); ?>)
                                    </button>
                                    <?php foreach ($parent_departments as $dept_name => $dept_info): ?>
                                        <button type="button" class="pos-dept-tab" data-dept="<?php echo htmlspecialchars($dept_name); ?>" onclick="filterDepartment('<?php echo htmlspecialchars(addslashes($dept_name)); ?>', this)">
                                            <i class="fa fa-folder-open-o"></i> <?php echo htmlspecialchars($dept_name); ?> <span class="badge" style="background:#e2e8f0; color:#334155; margin-left:3px;"><?php echo is_array($dept_info) ? $dept_info['count'] : count($dept_info); ?></span>
                                        </button>
                                    <?php endforeach; ?>
                                </div>
                                <button type="button" class="pos-dept-nav-btn" id="posDeptNavNext" onclick="scrollDeptCarousel(260)" title="Scroll Right" aria-label="Scroll Right" style="margin-left: 4px;">
                                    <i class="fa fa-chevron-right"></i>
                                </button>
                            </div>

                            <!-- Tier 2: Sub-Category Pills (Mid Categories: 2-3 Clean Rows/Single Row) -->
                            <div class="pos-category-filter-box" id="posCategoryFilterBox">
                                <button type="button" class="pos-cat-pill active" data-dept="all" data-cat="all" onclick="filterCategory('all', this)">
                                    <i class="fa fa-th-list"></i> <span class="pos-all-label">All in Selected</span>
                                </button>
                                <?php foreach ($mid_categories_list as $item): ?>
                                    <button type="button" class="pos-cat-pill" 
                                            data-dept="<?php echo htmlspecialchars($item['dept']); ?>" 
                                            data-cat="<?php echo htmlspecialchars($item['name']); ?>" 
                                            data-mcat="<?php echo htmlspecialchars($item['name']); ?>" 
                                            onclick="filterCategory('<?php echo htmlspecialchars(addslashes($item['name'])); ?>', this)">
                                        <?php echo htmlspecialchars($item['name']); ?> <span class="badge" style="background:#f1f5f9; color:#475569; font-size:10.5px; margin-left:3px; font-weight:700;"><?php echo $item['count']; ?></span>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- VIEW 1: Products Grid (Visual Cards) -->
                    <div id="posProductGrid">
                        <?php if (count($grouped_products) > 0): ?>
                            <?php 
                            if (!function_exists('get_pos_product_search_index')) {
                                function get_pos_product_search_index($group) {
                                    $search_terms = array(
                                        $group['base_name'],
                                        $group['brand'],
                                        $group['ecat_name'],
                                        $group['mcat_name'],
                                        $group['tcat_name']
                                    );
                                    foreach ($group['variants'] as $v) {
                                        $search_terms[] = $v['name'];
                                        $search_terms[] = $v['sku'];
                                        $search_terms[] = $v['spec_label'];
                                        if (!empty($v['size'])) $search_terms[] = $v['size'];
                                        if (!empty($v['thickness'])) $search_terms[] = $v['thickness'];
                                        if (!empty($v['diameter'])) $search_terms[] = $v['diameter'];
                                        if (!empty($v['color'])) $search_terms[] = $v['color'];
                                        if (!empty($v['material'])) $search_terms[] = $v['material'];
                                        if (!empty($v['weight_pack'])) $search_terms[] = $v['weight_pack'];
                                        if (!empty($v['voltage'])) $search_terms[] = $v['voltage'];
                                        if (!empty($v['power'])) $search_terms[] = $v['power'];
                                    }
                                    
                                    $combined = strtolower(implode(' ', array_filter($search_terms)));
                                    
                                    // Dimension variations: 2x3 -> 2 x 3, 2 x 3 -> 2x3
                                    if (preg_match_all('/\b(\d+(?:\.\d+)?)\s*x\s*(\d+(?:\.\d+)?)\b/i', $combined, $matches, PREG_SET_ORDER)) {
                                        foreach ($matches as $m) {
                                            $search_terms[] = $m[1] . 'x' . $m[2];
                                            $search_terms[] = $m[1] . ' x ' . $m[2];
                                            $search_terms[] = $m[1] . '" x ' . $m[2] . '"';
                                            $search_terms[] = $m[1] . 'in x ' . $m[2] . 'in';
                                        }
                                    }
                                    
                                    // Units: 40kg -> 40 kg, 10mm -> 10 mm
                                    if (preg_match_all('/\b(\d+(?:\.\d+)?)\s*(kg|mm|cm|m|in|pcs|pc|ft|gal|ltr|l|w|v|hp)\b/i', $combined, $matches, PREG_SET_ORDER)) {
                                        foreach ($matches as $m) {
                                            $search_terms[] = $m[1] . $m[2];
                                            $search_terms[] = $m[1] . ' ' . $m[2];
                                        }
                                    }
                                    
                                    // Fractions
                                    if (strpos($combined, '1/2') !== false) { $search_terms[] = '0.5'; $search_terms[] = '1/2"'; $search_terms[] = '1/2 in'; }
                                    if (strpos($combined, '1/4') !== false) { $search_terms[] = '0.25'; $search_terms[] = '1/4"'; $search_terms[] = '1/4 in'; }
                                    if (strpos($combined, '3/4') !== false) { $search_terms[] = '0.75'; $search_terms[] = '3/4"'; $search_terms[] = '3/4 in'; }
                                    if (strpos($combined, '3/8') !== false) { $search_terms[] = '0.375'; $search_terms[] = '3/8"'; $search_terms[] = '3/8 in'; }
                                    if (strpos($combined, '5/16') !== false) { $search_terms[] = '0.3125'; $search_terms[] = '5/16"'; $search_terms[] = '5/16 in'; }
                                    
                                    // Construction Trade Synonyms & Aliases
                                    if (strpos($combined, 'galvanized') !== false || strpos($combined, 'corrugated') !== false) {
                                        $search_terms[] = 'gi'; $search_terms[] = 'g.i.'; $search_terms[] = 'yero';
                                    }
                                    if (strpos($combined, 'hollow block') !== false) {
                                        $search_terms[] = 'chb'; $search_terms[] = 'block';
                                    }
                                    if (strpos($combined, 'deformed') !== false || strpos($combined, 'steel bar') !== false || strpos($combined, 'rebar') !== false) {
                                        $search_terms[] = 'rsb'; $search_terms[] = 'deformed'; $search_terms[] = 'rebar'; $search_terms[] = 'bakal';
                                    }
                                    if (strpos($combined, 'wire nail') !== false || strpos($combined, 'common nail') !== false) {
                                        $search_terms[] = 'cwn'; $search_terms[] = 'pako'; $search_terms[] = 'nail';
                                    }
                                    if (strpos($combined, 'pvc') !== false) {
                                        $search_terms[] = 'tubo'; $search_terms[] = 'sanitary'; $search_terms[] = 'neltex'; $search_terms[] = 'polyvinyl';
                                    }
                                    if (strpos($combined, 'ppr') !== false) {
                                        $search_terms[] = 'green pipe'; $search_terms[] = 'polypropylene';
                                    }
                                    if (strpos($combined, 'purlin') !== false) {
                                        $search_terms[] = 'c-purlin'; $search_terms[] = 'c channel'; $search_terms[] = 'c-channel';
                                    }
                                    if (strpos($combined, 'plywood') !== false) {
                                        $search_terms[] = 'ply'; $search_terms[] = 'marine'; $search_terms[] = 'kahoy';
                                    }
                                    if (strpos($combined, 'wire') !== false || strpos($combined, 'cable') !== false) {
                                        $search_terms[] = 'thhn'; $search_terms[] = 'thwn'; $search_terms[] = 'kuryente';
                                    }
                                    if (strpos($combined, 'cement') !== false) {
                                        $search_terms[] = 'semento'; $search_terms[] = 'portland'; $search_terms[] = 'pozolan';
                                    }
                                    if (strpos($combined, 'paint') !== false) {
                                        $search_terms[] = 'pintura'; $search_terms[] = 'boysen'; $search_terms[] = 'davies';
                                    }
                                    if (strpos($combined, 'sandpaper') !== false || strpos($combined, 'sand paper') !== false || strpos($combined, 'abrasive') !== false || strpos($combined, 'lija') !== false) {
                                        $search_terms[] = 'lija'; $search_terms[] = 'sandpaper'; $search_terms[] = 'sand paper'; $search_terms[] = 'abrasive'; $search_terms[] = 'waterproof';
                                    }

                                    return strtolower(implode(' ', array_unique($search_terms)));
                                }
                            }
                            ?>
                            <?php foreach ($grouped_products as $group_key => $group): 
                                $is_out_of_stock = ($group['total_stock'] <= 0);
                                $variant_count = count($group['variants']);
                                $group_dept = !empty($group['tcat_name']) ? $group['tcat_name'] : (!empty($group['mcat_name']) ? $group['mcat_name'] : 'General Hardware');
                                $search_index = get_pos_product_search_index($group);
                            ?>
                            <div class="pos-product-item" 
                                 data-name="<?php echo htmlspecialchars($search_index, ENT_QUOTES, 'UTF-8'); ?>"
                                 data-base-name="<?php echo htmlspecialchars($group['base_name'], ENT_QUOTES, 'UTF-8'); ?>"
                                 data-brand="<?php echo htmlspecialchars($group['brand'], ENT_QUOTES, 'UTF-8'); ?>"
                                 data-category="<?php echo htmlspecialchars($group['ecat_name'], ENT_QUOTES, 'UTF-8'); ?>"
                                 data-mcat="<?php echo htmlspecialchars($group['mcat_name'], ENT_QUOTES, 'UTF-8'); ?>"
                                 data-dept="<?php echo htmlspecialchars($group_dept, ENT_QUOTES, 'UTF-8'); ?>"
                                 data-stock="<?php echo (int)$group['total_stock']; ?>"
                                 data-group='<?php echo htmlspecialchars(json_encode($group), ENT_QUOTES, 'UTF-8'); ?>'
                                 onclick="<?php echo $is_out_of_stock ? 'void(0);' : 'handleGroupCardClick(this);'; ?>">
                                
                                <div class="pos-product-card <?php echo $is_out_of_stock ? 'out-of-stock' : ''; ?>">
                                    <!-- Stock Badge -->
                                    <span class="label pos-stock-badge <?php echo $is_out_of_stock ? 'label-danger' : ($group['total_stock'] < 10 ? 'label-warning' : 'label-success'); ?>">
                                        <?php echo $is_out_of_stock ? 'Out of Stock' : $group['total_stock'] . ' in stock'; ?>
                                    </span>
                                    
                                    <!-- Product Image -->
                                    <div class="pos-product-img" style="background-image: url('<?php echo htmlspecialchars($group['photo']); ?>');"></div>
                                    
                                    <!-- Parent Product Info -->
                                    <div class="pos-parent-info">
                                        <div class="pos-parent-name" data-orig-title="<?php echo htmlspecialchars($group['base_name'], ENT_QUOTES, 'UTF-8'); ?>" title="<?php echo htmlspecialchars($group['base_name']); ?>">
                                            <?php echo htmlspecialchars($group['base_name']); ?>
                                        </div>
                                        
                                        <!-- Variant Indicator Badge -->
                                        <?php if ($variant_count > 1): ?>
                                            <span class="pos-variant-count-badge">
                                                <i class="fa fa-th-list"></i> <?php echo $variant_count; ?> Variants / Sizes
                                            </span>
                                        <?php else: ?>
                                            <span class="pos-variant-count-badge single">
                                                <i class="fa fa-cube"></i> <?php echo htmlspecialchars($group['variants'][0]['spec_label']); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Price Range & Action -->
                                    <div class="pos-product-footer">
                                        <div class="pos-price-val">
                                            <?php if ($group['min_price'] == $group['max_price']): ?>
                                                &#8369;<?php echo number_format($group['min_price'], 2); ?>
                                            <?php else: ?>
                                                &#8369;<?php echo number_format($group['min_price'], 2); ?> - &#8369;<?php echo number_format($group['max_price'], 2); ?>
                                            <?php endif; ?>
                                        </div>
                                        <button type="button" class="btn btn-primary btn-xs pos-card-add-btn">
                                            <i class="fa fa-plus-circle"></i> <?php echo ($variant_count > 1) ? 'Select' : '+ Add'; ?>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="col-md-12 text-center" style="padding: 40px 20px;">
                                <i class="fa fa-cubes fa-3x" style="color: #cbd5e1;"></i>
                                <p class="text-muted" style="margin-top: 10px;">No active products found in your inventory. <a href="product-add.php">Add products</a></p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- VIEW 2: High-Density Cashier List / Table View -->
                    <div id="posProductListContainer" class="pos-product-list-container" style="display: none;">
                        <div class="table-responsive" style="max-height: 560px; overflow-y: auto; margin-bottom: 0;">
                            <table class="table table-hover pos-product-list-table" style="margin-bottom: 0;">
                                <thead style="background: #f8fafc; border-bottom: 2px solid #cbd5e1; position: sticky; top: 0; z-index: 5;">
                                    <tr>
                                        <th style="width: 48px; text-align: center;">Photo</th>
                                        <th>Product Name &amp; Specifications</th>
                                        <th style="width: 140px;">Category</th>
                                        <th style="width: 110px;">SKU / Brand</th>
                                        <th style="width: 90px; text-align: center;">Stock</th>
                                        <th style="width: 115px; text-align: right;">Unit Price</th>
                                        <th style="width: 90px; text-align: center;">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($grouped_products) > 0): ?>
                                        <?php foreach ($grouped_products as $group_key => $group): 
                                            $is_out_of_stock = ($group['total_stock'] <= 0);
                                            $variant_count = count($group['variants']);
                                            $group_dept = !empty($group['tcat_name']) ? $group['tcat_name'] : (!empty($group['mcat_name']) ? $group['mcat_name'] : 'General Hardware');
                                            $search_index = get_pos_product_search_index($group);
                                        ?>
                                        <tr class="pos-product-item pos-list-row <?php echo $is_out_of_stock ? 'out-of-stock' : ''; ?>"
                                            data-name="<?php echo htmlspecialchars($search_index, ENT_QUOTES, 'UTF-8'); ?>"
                                            data-base-name="<?php echo htmlspecialchars($group['base_name'], ENT_QUOTES, 'UTF-8'); ?>"
                                            data-brand="<?php echo htmlspecialchars($group['brand'], ENT_QUOTES, 'UTF-8'); ?>"
                                            data-category="<?php echo htmlspecialchars($group['ecat_name'], ENT_QUOTES, 'UTF-8'); ?>"
                                            data-mcat="<?php echo htmlspecialchars($group['mcat_name'], ENT_QUOTES, 'UTF-8'); ?>"
                                            data-dept="<?php echo htmlspecialchars($group_dept, ENT_QUOTES, 'UTF-8'); ?>"
                                            data-stock="<?php echo (int)$group['total_stock']; ?>"
                                            data-group='<?php echo htmlspecialchars(json_encode($group), ENT_QUOTES, 'UTF-8'); ?>'
                                            onclick="<?php echo $is_out_of_stock ? 'void(0);' : 'handleGroupCardClick(this);'; ?>">
                                            <td style="text-align: center; vertical-align: middle; padding: 6px;">
                                                <div style="width: 36px; height: 36px; background-image: url('<?php echo htmlspecialchars($group['photo']); ?>'); background-size: contain; background-repeat: no-repeat; background-position: center; border-radius: 4px; border: 1px solid #e2e8f0; background-color: #fff; margin: 0 auto;"></div>
                                            </td>
                                            <td style="vertical-align: middle; padding: 6px 8px;">
                                                <strong class="pos-list-row-title" data-orig-title="<?php echo htmlspecialchars($group['base_name'], ENT_QUOTES, 'UTF-8'); ?>" style="color: #0f172a; font-size: 13px; display: block;"><?php echo htmlspecialchars($group['base_name']); ?></strong>
                                                <?php if ($variant_count > 1): ?>
                                                    <span class="pos-variant-count-badge" style="font-size: 10px; margin-top: 2px;">
                                                        <i class="fa fa-th-list"></i> <?php echo $variant_count; ?> Variants / Sizes
                                                    </span>
                                                <?php else: ?>
                                                    <span style="font-size: 11.5px; color: #64748b;"><?php echo htmlspecialchars($group['variants'][0]['spec_label']); ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td style="vertical-align: middle; padding: 6px 8px; font-size: 11.5px; color: #475569;">
                                                <span class="label label-default" style="background: #f1f5f9; color: #334155; border: 1px solid #e2e8f0; font-size: 10.5px;"><?php echo htmlspecialchars($group['ecat_name']); ?></span>
                                            </td>
                                            <td style="vertical-align: middle; padding: 6px 8px; font-size: 11.5px; color: #64748b; font-family: monospace;">
                                                <div><strong><?php echo htmlspecialchars($group['brand']); ?></strong></div>
                                                <div style="font-size: 10.5px; color: #94a3b8;"><?php echo htmlspecialchars($group['variants'][0]['sku'] ?? ''); ?></div>
                                            </td>
                                            <td style="vertical-align: middle; padding: 6px 8px; text-align: center;">
                                                <span class="label <?php echo $is_out_of_stock ? 'label-danger' : ($group['total_stock'] < 10 ? 'label-warning' : 'label-success'); ?>" style="font-size: 11px; padding: 3px 6px;">
                                                    <?php echo $is_out_of_stock ? 'Out of Stock' : $group['total_stock']; ?>
                                                </span>
                                            </td>
                                            <td style="vertical-align: middle; padding: 6px 8px; text-align: right; font-weight: 800; font-size: 13.5px; color: #1d4ed8;">
                                                <?php if ($group['min_price'] == $group['max_price']): ?>
                                                    &#8369;<?php echo number_format($group['min_price'], 2); ?>
                                                <?php else: ?>
                                                    &#8369;<?php echo number_format($group['min_price'], 2); ?>+
                                                <?php endif; ?>
                                            </td>
                                            <td style="vertical-align: middle; padding: 6px 8px; text-align: center;">
                                                <button type="button" class="btn btn-primary btn-xs" style="font-weight: 700; border-radius: 4px; padding: 3px 8px; font-size: 11px;">
                                                    <i class="fa fa-plus-circle"></i> <?php echo ($variant_count > 1) ? 'Select' : '+ Add'; ?>
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <!-- Right Side: POS Cart & Register Panel -->
        <div class="col-md-5 col-lg-4">
            <div class="pos-cart-panel">
                
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; padding-bottom: 8px; border-bottom: 1px solid #f1f5f9;">
                    <h4 style="margin: 0; font-weight: 700; color: #1e293b; display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                        <span><i class="fa fa-shopping-cart text-primary"></i> Current Sale</span>
                        <?php if (!empty($paying_existing_po) && !empty($existing_po_data)): ?>
                            <span class="badge" style="background: #f59e0b; color: #000; font-size: 13px; font-family: monospace, Consolas, sans-serif; font-weight: 800; padding: 4px 10px; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.2);">
                                <i class="fa fa-file-text-o"></i> PO: <?php echo htmlspecialchars($existing_po_data['payment_id'] ?? $existing_po_data['txnid']); ?>
                            </span>
                        <?php endif; ?>
                    </h4>
                    <div style="display: flex; align-items: center; gap: 6px;">
                        <?php if ($pos_is_approver): ?>
                        <button type="button" id="posApprovalQueueBtn" class="btn btn-warning btn-xs" onclick="openApprovalQueueModal()" style="display: inline-flex; align-items: center; gap: 4px; font-weight: 700; background-color: #f59e0b; border-color: #d97706; color: #fff; padding: 3px 8px; border-radius: 4px;" title="View and manage discount approval requests">
                            <i class="fa fa-clock-o"></i> Approvals <span id="posPendingBadge" class="badge" style="background-color: #dc2626; font-size: 10px; margin-left: 2px; display: none;">0</span>
                        </button>
                        <?php endif; ?>
                        <button type="button" class="btn btn-warning btn-xs" onclick="openHoldOrderModal()" id="posHoldCartBtn" style="font-weight: 700; background-color: #d97706; border-color: #b45309; color: #fff; border-radius: 4px; padding: 3px 8px;" title="Hold / Park Current Cart (F8)"><i class="fa fa-pause"></i> Hold (F8)</button>
                        <button type="button" class="btn btn-default btn-xs text-danger" onclick="clearCart()"><i class="fa fa-trash"></i> Clear</button>
                    </div>
                </div>

                <?php if (!empty($paying_existing_po) && !empty($existing_po_data)): ?>
                <div class="alert alert-warning" style="margin-bottom: 12px; padding: 9px 12px; font-size: 12.5px; border-radius: 6px; border-left: 4px solid #f59e0b; display: flex; justify-content: space-between; align-items: center; background: #fffbeb; color: #92400e;">
                    <div>
                        <strong style="color: #b45309;"><i class="fa fa-pause-circle"></i> Resumed Held Order:</strong> <span style="font-family: monospace; font-weight: bold;"><?php echo htmlspecialchars($existing_po_data['payment_id'] ?? $existing_po_data['txnid']); ?></span><br>
                        <span style="font-size: 11.5px; color: #78350f;">Customer: <strong><?php echo htmlspecialchars($existing_po_data['customer_name'] ?: 'Walk-in Customer'); ?></strong> &bull; Add items or proceed to pay.</span>
                    </div>
                    <a href="pos.php?clear_cart=1" class="btn btn-xs btn-default" style="font-weight: 700; border-color: #cbd5e1;" title="Exit Held PO & start fresh sale"><i class="fa fa-times"></i> Exit</a>
                </div>
                <?php endif; ?>

                <?php if (!empty($pos_order_error)): ?>
                <div class="alert alert-danger" style="margin-bottom: 12px; font-size: 13px; font-weight: bold; border-radius: 6px;">
                    <i class="fa fa-exclamation-circle"></i> <?php echo htmlspecialchars($pos_order_error); ?>
                </div>
                <?php endif; ?>

                <form id="posCheckoutForm" method="POST" action="pos.php<?php echo (!empty($paying_existing_po) && !empty($existing_po_data)) ? ('?po_id=' . urlencode($existing_po_data['payment_id'] ?? $existing_po_data['txnid'])) : ''; ?>" onsubmit="return validatePOSForm()">
                    <input type="hidden" name="pos_action" value="complete_sale">
                    <input type="hidden" id="cartItemsInput" name="cart_items" value="<?php echo htmlspecialchars(json_encode(array_values($active_pos_cart))); ?>">
                    <?php if (!empty($paying_existing_po) && !empty($existing_po_data)): ?>
                        <input type="hidden" name="paying_po_id" id="posPayingPoIdInput" value="<?php echo htmlspecialchars($existing_po_data['payment_id'] ?? $existing_po_data['txnid']); ?>">
                    <?php endif; ?>

                    <!-- Customer Selection & Independent Location Toggle -->
                    <div style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 10px; border-radius: 6px; margin-bottom: 12px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 6px; margin-bottom: 8px; border-bottom: 1px solid #e2e8f0; padding-bottom: 6px;">
                            <!-- Customer Type Selection (Mutually Exclusive) -->
                            <div style="display: inline-flex; align-items: center; gap: 12px;">
                                <label style="font-size: 11px; font-weight: 600; margin-bottom: 0; cursor: pointer; display: inline-flex; align-items: center; gap: 3px;">
                                    <input type="radio" name="customer_type" value="walkin" <?php echo ($saved_customer_type === 'walkin') ? 'checked' : ''; ?> onchange="toggleCustomerType()"> Walk-in (OTC)
                                </label>
                                <label style="font-size: 11px; font-weight: 600; margin-bottom: 0; cursor: pointer; display: inline-flex; align-items: center; gap: 3px;">
                                    <input type="radio" name="customer_type" value="registered" <?php echo ($saved_customer_type === 'registered') ? 'checked' : ''; ?> onchange="toggleCustomerType()"> Registered User
                                </label>
                            </div>

                            <!-- Independent Location Toggle Option -->
                            <div>
                                <label style="font-size: 11px; font-weight: 700; margin-bottom: 0; cursor: pointer; display: inline-flex; align-items: center; gap: 4px; color: #0284c7; background: #e0f2fe; padding: 2px 7px; border-radius: 4px; border: 1px solid #bae6fd;" title="Toggle to add or exclude location delivery fee">
                                    <input type="checkbox" id="locationRadio" name="is_location_delivery" value="1" <?php echo $saved_is_location ? 'checked' : ''; ?> onchange="toggleLocationOption()"> 
                                    <i class="fa fa-map-marker"></i> Location
                                </label>
                            </div>
                        </div>

                        <!-- Walk-in fields -->
                        <div id="walkinFields" style="<?php echo ($saved_customer_type === 'registered') ? 'display: none;' : ''; ?>">
                            <div class="row">
                                <div class="col-xs-7" style="padding-right: 4px;">
                                    <input type="text" name="walkin_name" class="form-control input-sm" value="<?php echo htmlspecialchars($saved_customer_name); ?>" placeholder="Customer Name (e.g. Juan Cruz)">
                                </div>
                                <div class="col-xs-5" style="padding-left: 4px;">
                                    <input type="text" name="walkin_phone" class="form-control input-sm" value="<?php echo htmlspecialchars($saved_customer_phone); ?>" placeholder="Phone No.">
                                </div>
                            </div>
                        </div>

                        <!-- Registered Customer Select -->
                        <div id="registeredFields" style="<?php echo ($saved_customer_type === 'registered') ? '' : 'display: none;'; ?>">
                            <select name="registered_cust_id" class="form-control select2 input-sm" style="width: 100%;">
                                <option value="">-- Select Registered Customer --</option>
                                <?php foreach ($registered_customers as $cust): ?>
                                    <option value="<?php echo $cust['cust_id']; ?>" <?php echo ($saved_customer_id === (int)$cust['cust_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cust['cust_name']) . ' (' . htmlspecialchars($cust['cust_email']) . ')'; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Location Select (tbl_brgy) - shown only when Location radio/checkbox is active -->
                        <div id="locationFields" style="<?php echo $saved_is_location ? '' : 'display: none;'; ?> margin-top: 8px; padding-top: 8px; border-top: 1px dashed #cbd5e1;">
                            <div style="font-size: 11px; font-weight: 700; color: #0284c7; margin-bottom: 4px;">
                                <i class="fa fa-truck"></i> Select Delivery Location (Barangay):
                            </div>
                            <select name="location_brgy_id" id="locationBrgySelect" class="form-control select2 input-sm" style="width: 100%;" onchange="handleLocationChange()">
                                <option value="" data-shipping="0">-- Select Barangay Location --</option>
                                <?php foreach ($brgy_list as $brgy): 
                                    $b_id = $brgy['brgy_id'];
                                    $b_shipping = isset($supplier_shipping_rates[$b_id]) ? floatval($supplier_shipping_rates[$b_id]) : $default_shipping_rate;
                                ?>
                                    <option value="<?php echo $b_id; ?>" data-name="<?php echo htmlspecialchars($brgy['brgy_name']); ?>" data-shipping="<?php echo $b_shipping; ?>" <?php echo ($saved_brgy_id === (int)$b_id) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($brgy['brgy_name']); ?> (+&#8369;<?php echo number_format($b_shipping, 2); ?> delivery fee)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Cart Items Table -->
                    <div style="max-height: 250px; overflow-y: auto; margin-bottom: 12px; border: 1px solid #f1f5f9; border-radius: 4px;">
                        <table class="table table-condensed pos-cart-table" style="margin-bottom: 0;">
                            <thead>
                                <tr>
                                    <th style="width: 44%;">Item / Variant</th>
                                    <th style="width: 24%; text-align: center;">Qty</th>
                                    <th style="width: 22%; text-align: right;">Total</th>
                                    <th style="width: 10%; text-align: center;"></th>
                                </tr>
                            </thead>
                            <tbody id="posCartTableBody">
                                <?php if (empty($active_pos_cart)): ?>
                                <tr>
                                    <td colspan="4" class="text-center text-muted" style="padding: 25px 10px;">
                                        <i class="fa fa-shopping-basket fa-2x" style="color: #cbd5e1;"></i>
                                        <div style="margin-top: 5px; font-size: 12px;">Cart is empty. Click products or "+ Special Order" to add.</div>
                                    </td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach ($active_pos_cart as $item): 
                                        $isSpecialOrder = (isset($item['item_type']) && $item['item_type'] === 'SPECIAL_ORDER');
                                        $isReturnCredit = (isset($item['item_type']) && $item['item_type'] === 'RETURN_CREDIT');
                                        $grossLineTotal = floatval($item['price']) * intval($item['qty']);
                                        $itemId = $item['id'];
                                        $itemIdEsc = htmlspecialchars(json_encode($itemId));
                                        
                                        $dAmt = floatval($item['discount_amount'] ?? 0);
                                        $dPct = floatval($item['discount_percent'] ?? 0);
                                        $dStatus = $item['discount_status'] ?? null;
                                        if ($dAmt <= 0 && $dPct > 0) {
                                            $dAmt = round($grossLineTotal * ($dPct / 100), 2);
                                        }
                                        $netLine = max(0, $grossLineTotal - $dAmt);
                                        
                                        $specsArr = [];
                                        if (!empty($item['spec_label']) && $item['spec_label'] !== 'Standard') {
                                            $specsArr[] = '<span style="color: #0369a1; font-weight: 700; font-size: 11px;">' . htmlspecialchars($item['spec_label']) . '</span>';
                                        } else {
                                            if (!empty($item['size'])) $specsArr[] = '<span style="color: #0369a1; font-weight: 700; font-size: 11px;">' . htmlspecialchars($item['size']) . '</span>';
                                            if (!empty($item['thickness'])) $specsArr[] = '<span style="color: #047857; font-weight: 700; font-size: 11px;">' . htmlspecialchars($item['thickness']) . '</span>';
                                            if (!empty($item['diameter'])) $specsArr[] = '<span style="color: #047857; font-weight: 700; font-size: 11px;">' . htmlspecialchars($item['diameter']) . '</span>';
                                            if (!empty($item['color'])) $specsArr[] = '<span class="pos-color-badge pos-color-' . strtolower(htmlspecialchars($item['color'])) . '">' . htmlspecialchars($item['color']) . '</span>';
                                        }
                                        if (empty($specsArr) && !empty($item['product_details']) && !$isSpecialOrder && !$isReturnCredit) {
                                            $specsArr[] = '<span style="color: #64748b; font-size: 11px;">' . htmlspecialchars($item['product_details']) . '</span>';
                                        }
                                        $refOrSku = $isSpecialOrder
                                            ? (!empty($item['special_order_reference']) ? 'Ref: ' . htmlspecialchars($item['special_order_reference']) . ' | ' : '')
                                            : (!empty($item['sku']) ? 'SKU: ' . htmlspecialchars($item['sku']) . ' | ' : '');
                                    ?>
                                        <tr <?php echo $isReturnCredit ? 'style="background-color: #fef2f2;"' : ($isSpecialOrder ? 'style="background-color: #fffbeb;"' : ''); ?>>
                                            <td style="padding: 8px 4px;">
                                                <?php if ($isReturnCredit): ?>
                                                    <span class="label label-danger" style="background-color: #dc2626; font-size: 10px; font-weight: 800; padding: 2px 6px; text-transform: uppercase; margin-bottom: 2px; display: inline-block;">
                                                        <i class="fa fa-undo"></i> RETURN CREDIT
                                                    </span>
                                                    <?php if (!empty($item['product_details'])): ?>
                                                        <div style="font-size: 11px; color: #991b1b; background: #fee2e2; border: 1px solid #fecaca; padding: 3px 6px; border-radius: 4px; margin-top: 3px; line-height: 1.3;">
                                                            <?php echo htmlspecialchars($item['product_details']); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                <?php elseif ($isSpecialOrder): ?>
                                                    <span class="label label-warning" style="background-color: #d97706; font-size: 10px; font-weight: 800; padding: 2px 6px; text-transform: uppercase; margin-bottom: 2px; display: inline-block;">
                                                        <i class="fa fa-star"></i> SPECIAL ORDER
                                                    </span>
                                                    <?php if (!empty($item['product_details'])): ?>
                                                        <div style="font-size: 11px; color: #475569; background: #fef3c7; border: 1px solid #fde68a; padding: 3px 6px; border-radius: 4px; margin-top: 3px; line-height: 1.3;">
                                                            <?php echo htmlspecialchars($item['product_details']); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                                <strong style="color: #0f172a; font-size: 13px; display: block; line-height: 1.3;"><?php echo htmlspecialchars($item['base_name'] ?? $item['name']); ?></strong>
                                                <?php if (!empty($specsArr)): ?>
                                                    <div style="margin-top: 2px; display: flex; flex-wrap: wrap; gap: 4px;">
                                                        <?php echo implode(' ', $specsArr); ?>
                                                    </div>
                                                <?php endif; ?>
                                                <div style="font-size: 11px; color: #64748b; font-family: monospace; margin-top: 2px;">
                                                    <?php echo $refOrSku; ?>&#8369;<?php echo number_format(abs($item['price']), 2); ?> each
                                                </div>
                                                <?php if ($dStatus === 'APPROVED' || $dStatus === 'APPROVED_MODIFIED' || $dAmt > 0): ?>
                                                    <div style="margin-top: 4px;">
                                                        <span class="label label-success" style="background: #10b981; font-size: 10px; font-weight: 700; padding: 2px 6px;">
                                                            <i class="fa fa-check-circle"></i> Discount -&#8369;<?php echo number_format($dAmt, 2); ?> (<?php echo number_format($dPct, 1); ?>%)
                                                        </span>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td style="padding: 8px 4px; text-align: center;">
                                                <div class="btn-group" style="display: inline-flex; align-items: center;">
                                                    <button type="button" class="btn btn-default pos-qty-btn" onclick="updateCartQty(<?php echo $itemIdEsc; ?>, <?php echo $item['qty'] - 1; ?>)">-</button>
                                                    <span style="display: inline-block; width: 28px; text-align: center; font-weight: 800; font-size: 14px; color: #0f172a;"><?php echo $item['qty']; ?></span>
                                                    <button type="button" class="btn btn-default pos-qty-btn" onclick="updateCartQty(<?php echo $itemIdEsc; ?>, <?php echo $item['qty'] + 1; ?>)">+</button>
                                                </div>
                                            </td>
                                            <td style="padding: 8px 4px; text-align: right;">
                                                <?php if ($isReturnCredit): ?>
                                                    <span style="font-weight: 800; font-size: 15px; color: #dc2626;">-&#8369;<?php echo number_format(abs($grossLineTotal), 2); ?></span>
                                                <?php elseif ($dAmt > 0): ?>
                                                    <span style="text-decoration: line-through; color: #94a3b8; font-size: 11px; display: block;">&#8369;<?php echo number_format($grossLineTotal, 2); ?></span>
                                                    <span style="font-weight: 800; font-size: 15px; color: #047857;">&#8369;<?php echo number_format($netLine, 2); ?></span>
                                                <?php else: ?>
                                                    <span style="font-weight: 800; font-size: 15px; color: <?php echo $isSpecialOrder ? '#b45309' : '#1e40af'; ?>;">&#8369;<?php echo number_format($grossLineTotal, 2); ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td style="padding: 8px 2px; text-align: center;">
                                                <button type="button" class="btn btn-link text-danger" onclick="removeFromCart(<?php echo $itemIdEsc; ?>)" style="padding: 2px 4px; font-size: 16px;" title="Remove item"><i class="fa fa-times-circle"></i></button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Down-Trade Exchange Notification Banner (shown when replacement item is cheaper than returned item) -->
                    <div id="posDownTradeAlert" style="<?php echo ($init_refund_due > 0) ? 'display: block;' : 'display: none;'; ?> background: #fffbeb; border: 1.5px solid #f59e0b; border-radius: 8px; padding: 12px 14px; margin-bottom: 14px; color: #92400e;">
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 4px;">
                            <span class="label label-warning" style="background: #d97706; font-size: 10px; font-weight: 800; padding: 2px 6px; text-transform: uppercase;">
                                <i class="fa fa-info-circle"></i> DOWN-TRADE EXCHANGE
                            </span>
                            <strong style="font-size: 13px; color: #b45309;" id="posDownTradeBadgeAmt">Refund Due: &#8369;<?php echo number_format($init_refund_due, 2); ?></strong>
                        </div>
                        <div style="font-size: 12.5px; font-weight: 700; line-height: 1.35; margin-top: 4px;">
                            Replacement item(s) cost less than the returned item(s).
                        </div>
                        <div style="font-size: 11.5px; color: #78350f; margin-top: 3px;" id="posDownTradeFormula">
                            Return credit (&#8369;<?php echo number_format($init_return_credit_total, 2); ?>) exceeds new items (&#8369;<?php echo number_format($init_net_subtotal + $init_delivery_cost, 2); ?>). <strong>Pay out &#8369;<?php echo number_format($init_refund_due, 2); ?></strong> from cash drawer to customer.
                        </div>
                    </div>

                    <!-- Pricing & Fulfillment Summary -->
                    <div id="posPricingSummaryBox" style="background: #f8fafc; border: 2px solid #e2e8f0; border-radius: 8px; padding: 14px; margin-bottom: 15px;">
                        <input type="hidden" name="delivery_type" id="posDeliveryType" value="pickup">
                        <input type="hidden" name="delivery_cost" id="posDeliveryCost" value="0.00">

                        <div style="display: flex; justify-content: space-between; font-size: 15px; font-weight: 600; margin-bottom: 8px;">
                            <span class="text-muted">Gross Subtotal:</span>
                            <span style="color: #1e293b;">&#8369;<span id="posSubtotal"><?php echo number_format($init_gross_subtotal, 2); ?></span></span>
                        </div>

                        <div id="posDiscountSavingsRow" style="<?php echo ($init_discount_total > 0) ? 'display: flex;' : 'display: none;'; ?> justify-content: space-between; align-items: center; font-size: 14px; margin-bottom: 8px;">
                            <span class="text-muted" style="font-weight: 600;">Total Discounts:</span>
                            <span style="font-weight: 800; color: #16a34a;">-&#8369;<span id="posDiscountSavingsDisplay"><?php echo number_format($init_discount_total, 2); ?></span></span>
                        </div>

                        <div id="posReturnCreditRow" style="<?php echo ($init_return_credit_total > 0) ? 'display: flex;' : 'display: none;'; ?> justify-content: space-between; align-items: center; font-size: 14px; margin-bottom: 8px;">
                            <span class="text-muted" style="font-weight: 600;"><i class="fa fa-undo text-danger"></i> Return Credit:</span>
                            <span style="font-weight: 800; color: #dc2626;">-&#8369;<span id="posReturnCreditDisplay"><?php echo number_format($init_return_credit_total, 2); ?></span></span>
                        </div>

                        <div style="display: flex; justify-content: space-between; align-items: center; font-size: 14px; margin-bottom: 8px;">
                            <span class="text-muted" style="font-weight: 600;">Fulfillment:</span>
                            <span id="posFulfillmentBadge" style="font-weight: 700; color: #059669; font-size: 13px;">
                                <i class="fa fa-shopping-bag"></i> Store Pickup (₱0)
                            </span>
                        </div>

                        <div id="deliveryFeeRow" style="display: none; justify-content: space-between; align-items: center; font-size: 14px; margin-bottom: 8px;">
                            <span class="text-muted" style="font-weight: 600;">Delivery Cost:</span>
                            <span style="font-weight: 800; color: #0284c7;">+&#8369;<span id="posDeliveryFeeDisplay">0.00</span></span>
                        </div>

                        <div style="background: #eff6ff; border: 2px solid #bfdbfe; border-radius: 8px; padding: 10px 14px; margin-top: 10px; display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <span style="font-size: 18px; font-weight: 800; color: #1e3a8a;">Grand Total:</span>
                                <div id="posGrandTotalSubtext" style="font-size: 11px; color: #0284c7; font-weight: 700;"><?php echo ($init_refund_due > 0) ? '(No Payment Required)' : ''; ?></div>
                            </div>
                            <span style="font-size: 24px; font-weight: 900; color: #1d4ed8;">&#8369;<span id="posGrandTotal"><?php echo number_format($init_grand_total, 2); ?></span></span>
                        </div>

                        <!-- Dedicated Refund Due Row in Summary Box -->
                        <div id="posRefundDueRow" style="<?php echo ($init_refund_due > 0) ? 'display: flex;' : 'display: none;'; ?> background: #ecfdf5; border: 2px solid #10b981; border-radius: 8px; padding: 10px 14px; margin-top: 8px; justify-content: space-between; align-items: center;">
                            <div>
                                <div style="font-size: 14px; font-weight: 800; color: #065f46; text-transform: uppercase;">
                                    <i class="fa fa-hand-holding-usd"></i> Refund Due:
                                </div>
                                <div style="font-size: 11px; color: #047857;">(Store pays out to customer)</div>
                            </div>
                            <span style="font-size: 22px; font-weight: 900; color: #059669;">&#8369;<span id="posRefundDueDisplay"><?php echo number_format($init_refund_due, 2); ?></span></span>
                        </div>
                    </div>

                    <!-- Payment Lock Notice (shown when discount is pending/escalated) -->
                    <div id="posPaymentLockedNotice" class="alert alert-warning" style="display: none; margin-bottom: 14px; padding: 10px 12px; font-size: 13px; font-weight: 700; background: #fffbeb; border: 1.5px solid #f59e0b; color: #92400e; border-radius: 6px;">
                        <i class="fa fa-lock fa-lg" style="margin-right: 4px;"></i> Payment Locked: One or more item discount requests are pending/escalated. Complete or cancel discount requests to proceed with payment.
                    </div>

                    <!-- Payment Method & Tendered Calculator -->
                    <div style="margin-bottom: 18px;">
                        <div class="form-group" style="margin-bottom: 12px;">
                            <label style="font-size: 14px; font-weight: 700; color: #1e293b; margin-bottom: 6px;">Payment Method:</label>
                            <select name="payment_method" id="posPaymentMethod" class="form-control input-lg" style="height: 42px; font-size: 15px; font-weight: 600;" onchange="handlePaymentMethodChange()">
                                <option value="Cash (OTC)" <?php echo ($saved_payment_method === 'Cash (OTC)') ? 'selected' : ''; ?>>💵 Cash (Over the Counter)</option>
                                <option value="GCash / Maya" <?php echo ($saved_payment_method === 'GCash / Maya') ? 'selected' : ''; ?>>📱 GCash / Maya E-Wallet</option>
                                <option value="Debit/Credit Card" <?php echo ($saved_payment_method === 'Debit/Credit Card') ? 'selected' : ''; ?>>💳 Debit / Credit Card</option>
                                <option value="Bank Transfer" <?php echo ($saved_payment_method === 'Bank Transfer') ? 'selected' : ''; ?>>🏦 Bank Transfer</option>
                                <option value="Check / Terms" <?php echo ($saved_payment_method === 'Check / Terms') ? 'selected' : ''; ?>>📄 Check / Terms</option>
                            </select>
                        </div>

                        <div id="cashCalculatorSection">
                            <div class="form-group" style="margin-bottom: 8px;">
                                <label style="font-size: 14px; font-weight: 700; color: #1e293b; margin-bottom: 6px;">Amount Tendered (Cash Received):</label>
                                <div class="input-group">
                                    <span class="input-group-addon" style="font-size: 20px; font-weight: bold;">&#8369;</span>
                                    <input type="number" step="any" id="posAmountTendered" name="amount_tendered" class="form-control input-lg" placeholder="0.00" onkeyup="updatePOSCalculations()" style="font-size: 22px; font-weight: 900; height: 48px; color: #0f172a;">
                                </div>
                            </div>

                            <!-- Quick Cash Presets -->
                            <div style="margin-bottom: 12px;">
                                <button type="button" class="btn btn-default pos-preset-btn" onclick="setExactAmount()">Exact</button>
                                <button type="button" class="btn btn-default pos-preset-btn" onclick="setCashPreset(100)">₱100</button>
                                <button type="button" class="btn btn-default pos-preset-btn" onclick="setCashPreset(500)">₱500</button>
                                <button type="button" class="btn btn-default pos-preset-btn" onclick="setCashPreset(1000)">₱1,000</button>
                                <button type="button" class="btn btn-default pos-preset-btn" onclick="setCashPreset(5000)">₱5,000</button>
                            </div>

                            <div style="background: #ecfdf5; border: 2px solid #6ee7b7; padding: 12px 16px; border-radius: 8px; display: flex; justify-content: space-between; align-items: center;">
                                <span style="font-size: 17px; font-weight: 800; color: #065f46;">Change Due:</span>
                                <span style="font-size: 24px; font-weight: 900; color: #047857;">&#8369;<span id="posChangeAmount">0.00</span></span>
                            </div>
                        </div>
                    </div>

                    <!-- Submit / Checkout Action Button -->
                    <?php if ($is_order_processing_or_operator): ?>
                        <a href="checkout.php" id="posProceedCheckoutBtn" class="btn btn-primary btn-block btn-lg" style="font-size: 18px; font-weight: 800; border-radius: 8px; padding: 14px 20px; box-shadow: 0 4px 10px rgba(37, 99, 235, 0.3);" onclick="handleProceedToCheckout(event)">
                            <i class="fa fa-shopping-cart"></i> Proceed to Checkout
                        </a>
                    <?php else: ?>
                        <button type="submit" id="posCompleteBtn" class="btn btn-success btn-block btn-lg" style="font-size: 18px; font-weight: 800; border-radius: 8px; padding: 14px 20px; box-shadow: 0 4px 10px rgba(16, 185, 129, 0.3);" <?php echo empty($active_pos_cart) ? 'disabled' : ''; ?>>
                            <?php if ($init_refund_due > 0): ?>
                                <i class="fa fa-hand-holding-usd"></i> Complete Exchange &amp; Pay Out &#8369;<?php echo number_format($init_refund_due, 2); ?>
                            <?php else: ?>
                                <i class="fa fa-check-circle"></i> Complete Sale &amp; Print Receipt
                            <?php endif; ?>
                        </button>
                    <?php endif; ?>

                </form>

            </div>
        </div>

    </div>
</section>

<!-- Interactive Product / Variant Selection Modal -->
<div class="modal fade" id="posVariantModal" tabindex="-1" role="dialog" aria-labelledby="posVariantModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document" style="max-width: 760px; width: 95%; margin: 30px auto;">
        <div class="modal-content" style="border-radius: 10px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.3);">
            <div class="modal-header" style="background: #1e3a8a; color: #fff; padding: 14px 18px;">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="color: #fff; opacity: 0.9; font-size: 24px;">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title" id="posVariantModalLabel" style="font-weight: 700; display: flex; align-items: center; gap: 8px; font-size: 17px;">
                    <i class="fa fa-cubes text-info"></i> <span id="vModalTitle">Select Variant</span>
                </h4>
                <div style="margin-top: 4px; font-size: 12px; color: #bfdbfe;">
                    Category: <strong id="vModalCategory" style="color: #fff;">-</strong> 
                    <span id="vModalBrandWrap" style="margin-left: 10px;">| Brand: <strong id="vModalBrand" style="color: #fff;">-</strong></span>
                </div>
            </div>
            
            <div class="modal-body" style="padding: 18px;">
                <!-- Selected Variant Live Details Card -->
                <div style="background: #f8fafc; border: 1.5px solid #e2e8f0; border-radius: 8px; padding: 12px; margin-bottom: 16px; display: flex; gap: 14px; align-items: center;">
                    <div id="vModalImg" style="width: 85px; height: 85px; min-width: 85px; border-radius: 6px; background-size: contain; background-repeat: no-repeat; background-position: center; background-color: #fff; border: 1px solid #cbd5e1;"></div>
                    <div style="flex-grow: 1;">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 8px;">
                            <h4 id="vModalSelectedName" style="margin: 0 0 4px 0; font-size: 15px; font-weight: 800; color: #0f172a; line-height: 1.3;">-</h4>
                            <span id="vModalStockBadge" class="label label-success" style="font-size: 12px; padding: 4px 8px; white-space: nowrap;">In Stock</span>
                        </div>
                        <div style="font-size: 12px; color: #64748b; margin-bottom: 6px;">
                            SKU: <strong id="vModalSku" style="color: #334155; font-family: monospace; font-size: 13px;">-</strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; align-items: baseline; flex-wrap: wrap; gap: 6px;">
                            <div style="font-size: 20px; font-weight: 900; color: #1d4ed8;">
                                &#8369;<span id="vModalPrice">0.00</span>
                            </div>
                            <div id="vModalSpecTags" style="display: flex; flex-wrap: wrap; gap: 4px;"></div>
                        </div>
                    </div>
                </div>

                <!-- Variant Dropdown Selection -->
                <div class="form-group" style="margin-bottom: 15px;">
                    <label style="font-size: 13px; font-weight: 700; color: #1e293b; margin-bottom: 6px; display: block;">
                        <i class="fa fa-list-ul text-primary"></i> Select Specification / Variant:
                    </label>
                    <select id="vModalSelect" class="form-control input-lg" style="height: 42px; font-size: 14px; font-weight: 600;" onchange="onVariantSelectChange(this.value)">
                    </select>
                </div>

                <!-- Clickable Variant Chips -->
                <div id="vModalChipsContainer" style="margin-bottom: 16px;">
                    <label style="font-size: 12px; font-weight: 700; color: #64748b; margin-bottom: 6px; display: block; text-transform: uppercase;">
                        Quick Variant Selector:
                    </label>
                    <div id="vModalChipsList" style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; max-height: 240px; overflow-y: auto; padding: 4px;"></div>
                </div>

                <!-- Quantity & Live Subtotal -->
                <div style="background: #f1f5f9; border-radius: 8px; padding: 14px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
                    <div>
                        <label style="font-size: 13px; font-weight: 700; color: #1e293b; margin-bottom: 6px; display: block;">Quantity to Add:</label>
                        <div class="input-group" style="width: 140px;">
                            <span class="input-group-btn">
                                <button type="button" class="btn btn-default" style="font-weight: bold; font-size: 16px; padding: 6px 12px;" onclick="changeModalQty(-1)">-</button>
                            </span>
                            <input type="number" id="vModalQtyInput" class="form-control text-center" style="font-size: 16px; font-weight: 800; height: 38px;" value="1" min="1" oninput="onModalQtyChange()">
                            <span class="input-group-btn">
                                <button type="button" class="btn btn-default" style="font-weight: bold; font-size: 16px; padding: 6px 12px;" onclick="changeModalQty(1)">+</button>
                            </span>
                        </div>
                    </div>
                    
                    <div style="text-align: right;">
                        <span style="font-size: 12px; color: #64748b; display: block; font-weight: 600;">Item Subtotal:</span>
                        <span style="font-size: 22px; font-weight: 900; color: #047857;">&#8369;<span id="vModalItemSubtotal">0.00</span></span>
                    </div>
                </div>

            </div>

            <div class="modal-footer" style="background: #f8fafc; border-top: 1px solid #e2e8f0; display: flex; justify-content: space-between;">
                <button type="button" class="btn btn-default" data-dismiss="modal" style="font-weight: 600;">Cancel</button>
                <button type="button" id="vModalAddBtn" class="btn btn-primary" onclick="submitVariantToCart()" style="font-weight: 800; padding: 8px 20px; font-size: 15px;">
                    <i class="fa fa-cart-plus"></i> Add to Current Sale
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Special Order Modal -->
<div class="modal fade" id="specialOrderModal" tabindex="-1" role="dialog" aria-labelledby="soModalLabel" aria-hidden="true" style="z-index: 10060;">
    <div class="modal-dialog modal-md" role="document">
        <div class="modal-content" style="border-radius: 8px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.25);">
            
            <!-- Modal Header -->
            <div class="modal-header" style="background: #0f172a; color: #fff; border-top-left-radius: 8px; border-top-right-radius: 8px; padding: 15px 20px;">
                <button type="button" class="close" data-dismiss="modal" style="color: #fff; opacity: 0.85; font-size: 24px;">&times;</button>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <span class="label label-warning" style="background: #d97706; font-size: 11px; font-weight: 800; padding: 4px 8px; text-transform: uppercase; letter-spacing: 0.5px;">
                        <i class="fa fa-star"></i> SPECIAL ORDER
                    </span>
                    <h4 class="modal-title" style="font-weight: 800; font-size: 18px; margin: 0; color: #fff;">
                        Special Order
                    </h4>
                </div>
                <div style="font-size: 12px; color: #94a3b8; margin-top: 4px;">
                    <i class="fa fa-pencil-square-o"></i> Manual Product / Customer Request (Not in Catalogue)
                </div>
            </div>

            <!-- Modal Body -->
            <div class="modal-body" style="padding: 20px 22px; background: #fff;">
                
                <!-- Inline Error Alert -->
                <div id="soModalError" class="alert alert-danger" style="display: none; padding: 10px 14px; margin-bottom: 15px; font-size: 13px; font-weight: 600; border-radius: 6px;">
                </div>

                <!-- Product Name -->
                <div class="form-group" style="margin-bottom: 14px;">
                    <label style="font-size: 13px; font-weight: 700; color: #1e293b; margin-bottom: 5px; display: block;">
                        Product Name <span class="text-danger">*</span>
                    </label>
                    <input type="text" id="soProductName" class="form-control input-lg" style="height: 42px; font-size: 15px; font-weight: 600; border-radius: 6px;" placeholder="e.g. Stainless Steel Pipe, Custom Steel Plate, Special Bracket..." oninput="updateSOTotal(); clearSOError();">
                </div>

                <!-- Product Details / Specifications -->
                <div class="form-group" style="margin-bottom: 14px;">
                    <label style="font-size: 13px; font-weight: 700; color: #1e293b; margin-bottom: 5px; display: block;">
                        Product Details / Specification <span class="text-muted" style="font-weight: normal; font-size: 11px;">(Size, Dimension, Material, Brand, Model, Customer Requirements)</span>
                    </label>
                    <textarea id="soProductDetails" class="form-control" rows="3" style="font-size: 13.5px; border-radius: 6px; resize: vertical;" placeholder="e.g. 304 Stainless Steel Pipe, 2 inch diameter, 2mm thickness, 6 meter length"></textarea>
                </div>

                <!-- Unit Price & Quantity Row -->
                <div class="row">
                    <div class="col-xs-12 col-sm-6">
                        <div class="form-group" style="margin-bottom: 14px;">
                            <label style="font-size: 13px; font-weight: 700; color: #1e293b; margin-bottom: 5px; display: block;">
                                Unit Price (&#8369;) <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-addon" style="font-weight: 800; font-size: 16px; background: #f8fafc; color: #334155;">&#8369;</span>
                                <input type="number" step="0.01" min="0" id="soUnitPrice" class="form-control input-lg" style="height: 42px; font-size: 16px; font-weight: 800; color: #1d4ed8;" placeholder="0.00" oninput="updateSOTotal(); clearSOError();">
                            </div>
                        </div>
                    </div>
                    <div class="col-xs-12 col-sm-6">
                        <div class="form-group" style="margin-bottom: 14px;">
                            <label style="font-size: 13px; font-weight: 700; color: #1e293b; margin-bottom: 5px; display: block;">
                                Quantity <span class="text-danger">*</span>
                            </label>
                            <div class="input-group" style="width: 100%;">
                                <span class="input-group-btn">
                                    <button type="button" class="btn btn-default" style="font-weight: bold; font-size: 16px; height: 42px; padding: 6px 14px;" onclick="changeSOQty(-1)">-</button>
                                </span>
                                <input type="number" id="soQuantity" class="form-control text-center input-lg" style="font-size: 16px; font-weight: 800; height: 42px;" value="1" min="1" oninput="updateSOTotal(); clearSOError();">
                                <span class="input-group-btn">
                                    <button type="button" class="btn btn-default" style="font-weight: bold; font-size: 16px; height: 42px; padding: 6px 14px;" onclick="changeSOQty(1)">+</button>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Live Total & Reference Box -->
                <div style="background: #f8fafc; border: 1.5px solid #cbd5e1; border-radius: 8px; padding: 12px 16px; margin-top: 5px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
                    <div>
                        <span style="font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; display: block;">Special Order Reference:</span>
                        <strong id="soReferencePreview" style="font-family: monospace; font-size: 13px; color: #0284c7;">SO-...</strong>
                    </div>
                    <div style="text-align: right;">
                        <span style="font-size: 12px; color: #64748b; font-weight: 600; display: block;">Total Amount:</span>
                        <span style="font-size: 22px; font-weight: 900; color: #047857;">&#8369;<span id="soTotalAmount">0.00</span></span>
                    </div>
                </div>

            </div>

            <!-- Modal Footer -->
            <div class="modal-footer" style="background: #f8fafc; border-top: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
                <button type="button" class="btn btn-default" data-dismiss="modal" style="font-weight: 600;">Close</button>
                <button type="button" class="btn btn-warning" onclick="submitSpecialOrderToCart()" style="background-color: #d97706; border-color: #b45309; color: #fff; font-weight: 800; padding: 8px 22px; font-size: 15px; border-radius: 4px; box-shadow: 0 2px 6px rgba(217,119,6,0.3);">
                    <i class="fa fa-cart-plus"></i> Add to Cart
                </button>
            </div>
        </div>
    </div>
</div>

<!-- POS Return Items Modal -->
<div class="modal fade" id="posReturnModal" tabindex="-1" role="dialog" aria-labelledby="posReturnModalLabel" aria-hidden="true" style="z-index: 10050;">
    <div class="modal-dialog modal-lg" role="document" style="max-width: 900px;">
        <div class="modal-content" style="border-radius: 8px; overflow: hidden; box-shadow: 0 10px 35px rgba(0,0,0,0.35);">
            
            <!-- Modal Header -->
            <div class="modal-header" style="background: #0f172a; color: #fff; border-top-left-radius: 8px; border-top-right-radius: 8px; padding: 16px 22px;">
                <button type="button" class="close" data-dismiss="modal" style="color: #fff; opacity: 0.85; font-size: 24px;">&times;</button>
                <div style="display: flex; align-items: center; gap: 10px;">
                    <span class="label label-danger" style="background: #dc2626; font-size: 11px; font-weight: 800; padding: 4px 8px; text-transform: uppercase; letter-spacing: 0.5px;">
                        <i class="fa fa-undo"></i> RETURN ITEM
                    </span>
                    <h4 class="modal-title" style="font-weight: 800; font-size: 18px; margin: 0; color: #fff;">
                        Return Item & Customer Refund
                    </h4>
                </div>
                <div style="font-size: 12px; color: #94a3b8; margin-top: 4px;">
                    Search completed order, select item to return, specify quantity, condition, and refund method
                </div>
            </div>

            <div class="modal-body" style="padding: 20px 24px; background: #fff; max-height: 75vh; overflow-y: auto;">
                
                <!-- Alert Area -->
                <div id="retModalAlert" class="alert" style="display: none; padding: 10px 14px; margin-bottom: 15px; font-size: 13.5px; font-weight: 600; border-radius: 6px;"></div>

                <!-- VIEW 1: SEARCH & ORDERS LIST -->
                <div id="retSearchSection">
                    
                    <!-- Search Input Bar -->
                    <div style="background: #f8fafc; border: 1.5px solid #e2e8f0; border-radius: 8px; padding: 14px 16px; margin-bottom: 18px;">
                        <label style="font-size: 13px; font-weight: 700; color: #1e293b; margin-bottom: 6px; display: block;">
                            <i class="fa fa-search text-primary"></i> Search Original Order:
                        </label>
                        <div class="input-group">
                            <input type="text" id="retOrderSearchInput" class="form-control input-lg" style="height: 44px; font-size: 15px;" placeholder="Enter Invoice # (POS-...), Customer Name, Phone, Email, SKU, or Date..." onkeyup="if(event.key === 'Enter') executeOrderReturnSearch();">
                            <span class="input-group-btn">
                                <button class="btn btn-primary input-lg" type="button" onclick="executeOrderReturnSearch()" style="height: 44px; font-weight: 700; padding: 6px 18px;">
                                    <i class="fa fa-search"></i> Search
                                </button>
                                <button class="btn btn-default input-lg" type="button" onclick="resetOrderReturnSearch()" style="height: 44px;" title="Reset / Show Recent Orders">
                                    <i class="fa fa-refresh"></i> Recent
                                </button>
                            </span>
                        </div>
                        <div style="font-size: 11.5px; color: #64748b; margin-top: 6px;">
                            <i class="fa fa-info-circle"></i> Supports search by receipt/invoice ID (e.g. <code>POS-20260904-AA89B</code>), customer name, phone number, or product SKU.
                        </div>
                    </div>

                    <!-- Search Results Header -->
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                        <h5 style="margin: 0; font-weight: 700; color: #334155; font-size: 14px;">
                            <i class="fa fa-list-alt text-primary"></i> <span id="retOrdersListTitle">Recent Completed Orders</span>
                        </h5>
                        <span id="retOrdersCountBadge" class="badge" style="background: #2563eb; font-size: 12px;">0 orders</span>
                    </div>

                    <!-- Orders Container -->
                    <div id="retOrdersContainer" style="display: flex; flex-direction: column; gap: 12px;">
                        <div class="text-center text-muted" style="padding: 40px 20px;">
                            <i class="fa fa-spinner fa-spin fa-2x text-primary"></i>
                            <div style="margin-top: 8px; font-size: 13px;">Loading orders...</div>
                        </div>
                    </div>

                </div>

                <!-- VIEW 2: CONFIGURE RETURN FOR SELECTED ITEM -->
                <div id="retItemConfigSection" style="display: none;">
                    
                    <!-- Back button -->
                    <div style="margin-bottom: 14px;">
                        <button type="button" class="btn btn-default btn-sm" onclick="backToReturnOrdersList()" style="font-weight: 700;">
                            <i class="fa fa-arrow-left"></i> Back to Orders List
                        </button>
                    </div>

                    <!-- 7-Day Return Policy Dynamic Status Banner -->
                    <div id="ret7DayStatusAlert" style="margin-bottom: 14px;"></div>

                    <!-- Order Header Info Card -->
                    <div style="background: #f1f5f9; border: 1px solid #cbd5e1; border-radius: 6px; padding: 10px 14px; margin-bottom: 16px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
                        <div>
                            <span style="font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase;">Original Invoice:</span>
                            <strong id="retCfgOrderRef" style="font-family: monospace; font-size: 14px; color: #0f172a; margin-left: 4px;">POS-...</strong>
                            <span id="retCfgOrderDate" style="font-size: 12px; color: #475569; margin-left: 10px;">-</span>
                        </div>
                        <div style="font-size: 12px; color: #334155;">
                            Customer: <strong id="retCfgCustomerName">-</strong> 
                            <span id="retCfgCustomerPhone" style="color: #64748b; margin-left: 6px;"></span>
                        </div>
                    </div>

                    <!-- Selected Product Card -->
                    <div style="background: #fff; border: 2px solid #3b82f6; border-radius: 8px; padding: 14px; margin-bottom: 18px; display: flex; gap: 14px; align-items: center;">
                        <div id="retCfgItemImg" style="width: 75px; height: 75px; min-width: 75px; border-radius: 6px; background-size: contain; background-repeat: no-repeat; background-position: center; background-color: #f8fafc; border: 1px solid #e2e8f0;"></div>
                        <div style="flex-grow: 1;">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 6px;">
                                <div>
                                    <span id="retCfgSpecialBadge" class="label label-warning" style="display: none; font-size: 10px; font-weight: 800; background: #d97706; padding: 2px 6px; margin-bottom: 3px;">SPECIAL ORDER</span>
                                    <h4 id="retCfgItemName" style="margin: 0 0 3px 0; font-size: 16px; font-weight: 800; color: #0f172a;">-</h4>
                                </div>
                                <div style="text-align: right;">
                                    <span style="font-size: 11px; color: #64748b; display: block;">Original Unit Price</span>
                                    <strong style="font-size: 18px; color: #1d4ed8;">&#8369;<span id="retCfgUnitPrice">0.00</span></strong>
                                </div>
                            </div>
                            <div id="retCfgItemDetails" style="font-size: 12px; color: #475569; margin-bottom: 4px;"></div>
                            <div style="font-size: 11.5px; color: #64748b; font-family: monospace;">
                                SKU / Ref: <strong id="retCfgSku" style="color: #334155;">-</strong>
                            </div>

                            <!-- Quantities Breakdown Badges -->
                            <div style="display: flex; gap: 8px; margin-top: 8px; flex-wrap: wrap;">
                                <span class="label label-default" style="font-size: 11.5px; padding: 4px 8px; background: #e2e8f0; color: #334155;">
                                    Purchased: <strong id="retCfgPurchasedQty">0</strong>
                                </span>
                                <span class="label label-default" style="font-size: 11.5px; padding: 4px 8px; background: #fee2e2; color: #991b1b;">
                                    Previously Returned: <strong id="retCfgPrevReturnedQty">0</strong>
                                </span>
                                <span class="label label-success" style="font-size: 11.5px; padding: 4px 8px; background: #dcfce7; color: #166534; font-weight: 800;">
                                    Available to Return: <strong id="retCfgAvailableQty">0</strong>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Return Configuration Form -->
                    <form id="posReturnItemForm" onsubmit="event.preventDefault(); confirmAndSubmitReturn();">
                        <input type="hidden" id="retSubmitOrderItemId" value="">
                        <input type="hidden" id="retSubmitPaymentId" value="">

                        <div class="row">
                            <!-- Quantity Stepper -->
                            <div class="col-sm-6 col-xs-12">
                                <div class="form-group" style="margin-bottom: 16px;">
                                    <label style="font-size: 13px; font-weight: 700; color: #1e293b; margin-bottom: 6px; display: block;">
                                        Return Quantity <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group" style="width: 100%;">
                                        <span class="input-group-btn">
                                            <button type="button" class="btn btn-default input-lg" style="font-weight: bold; font-size: 18px; height: 44px; padding: 6px 16px;" onclick="changeReturnQty(-1)">-</button>
                                        </span>
                                        <input type="number" id="retQuantityInput" class="form-control text-center input-lg" style="font-size: 18px; font-weight: 800; height: 44px; color: #0f172a;" value="1" min="1" oninput="onReturnQtyInput()">
                                        <span class="input-group-btn">
                                            <button type="button" class="btn btn-default input-lg" style="font-weight: bold; font-size: 18px; height: 44px; padding: 6px 16px;" onclick="changeReturnQty(1)">+</button>
                                        </span>
                                    </div>
                                    <div style="font-size: 11px; color: #64748b; margin-top: 3px;">
                                        Maximum allowed return: <strong id="retMaxQtyNotice">0</strong> units
                                    </div>
                                </div>
                            </div>

                            <!-- Refund Method -->
                            <div class="col-sm-6 col-xs-12">
                                <div class="form-group" style="margin-bottom: 16px;">
                                    <label style="font-size: 13px; font-weight: 700; color: #1e293b; margin-bottom: 6px; display: block;">
                                        Refund Method <span class="text-danger">*</span>
                                    </label>
                                    <select id="retRefundMethod" class="form-control input-lg" style="height: 44px; font-size: 14px; font-weight: 600;">
                                        <option value="Cash">💵 Cash (Over the Counter)</option>
                                        <option value="Original Payment Method">🔄 Original Payment Method</option>
                                        <option value="Store Credit / Account Credit">🏬 Store Credit / Account Credit</option>
                                        <option value="GCash / Maya">📱 GCash / Maya E-Wallet</option>
                                        <option value="Bank Transfer">🏦 Bank Transfer</option>
                                        <option value="Replacement">🔁 Replacement Item Issued</option>
                                        <option value="Other">📄 Other</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <!-- Return Reason -->
                            <div class="col-sm-6 col-xs-12">
                                <div class="form-group" style="margin-bottom: 16px;">
                                    <label style="font-size: 13px; font-weight: 700; color: #1e293b; margin-bottom: 6px; display: block;">
                                        Return Reason <span class="text-danger">*</span>
                                    </label>
                                    <select id="retReasonSelect" class="form-control input-lg" style="height: 44px; font-size: 14px; font-weight: 600;" onchange="onReturnReasonChange()">
                                        <option value="Customer changed mind">Customer changed mind</option>
                                        <option value="Wrong item supplied">Wrong item supplied</option>
                                        <option value="Wrong size/specification">Wrong size/specification</option>
                                        <option value="Incorrect quantity">Incorrect quantity</option>
                                        <option value="Damaged item">Damaged item</option>
                                        <option value="Defective item">Defective item</option>
                                        <option value="Delivery damage">Delivery damage</option>
                                        <option value="Supplier error">Supplier error</option>
                                        <option value="Product quality issue">Product quality issue</option>
                                        <option value="Other">Other (Specify below)</option>
                                    </select>
                                    
                                    <div id="retReasonNotesWrap" style="display: none; margin-top: 8px;">
                                        <input type="text" id="retReasonNotes" class="form-control" placeholder="Specify custom return reason..." style="font-size: 13px;">
                                    </div>
                                </div>
                            </div>

                            <!-- Item Condition -->
                            <div class="col-sm-6 col-xs-12">
                                <div class="form-group" style="margin-bottom: 16px;">
                                    <label style="font-size: 13px; font-weight: 700; color: #1e293b; margin-bottom: 6px; display: block;">
                                        Item Condition <span class="text-danger">*</span>
                                    </label>
                                    <select id="retConditionSelect" class="form-control input-lg" style="height: 44px; font-size: 14px; font-weight: 600;" onchange="updateRestockBadge()">
                                        <option value="Resellable">🟢 Resellable (Restock to sellable inventory)</option>
                                        <option value="Unopened">🟢 Unopened (Restock to sellable inventory)</option>
                                        <option value="Opened">🟡 Opened (Non-sellable / Inspection - Do NOT restock)</option>
                                        <option value="Used">🟡 Used (Non-sellable - Do NOT restock)</option>
                                        <option value="Damaged">🔴 Damaged (Damaged stock - Do NOT restock)</option>
                                        <option value="Defective">🔴 Defective (Defective stock - Do NOT restock)</option>
                                        <option value="Needs Inspection">🟡 Needs Inspection (Quarantine - Do NOT restock)</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Dynamic Restock Status Alert -->
                        <div id="retRestockAlert" style="margin-bottom: 16px;"></div>

                        <!-- General Notes -->
                        <div class="form-group" style="margin-bottom: 16px;">
                            <label style="font-size: 12.5px; font-weight: 600; color: #475569; margin-bottom: 4px; display: block;">
                                Additional Notes / Audit Remarks (Optional):
                            </label>
                            <input type="text" id="retGeneralNotes" class="form-control" placeholder="e.g. Customer receipt presented, item verified by cashier..." style="font-size: 13px;">
                        </div>

                        <!-- Live Refund Calculation Box -->
                        <div style="background: #eff6ff; border: 2px solid #93c5fd; border-radius: 8px; padding: 14px 18px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                            <div>
                                <span style="font-size: 12px; color: #1e40af; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; display: block;">Total Refund Amount Due:</span>
                                <span style="font-size: 12px; color: #64748b;">Formula: <span id="retCalculationFormula">0 × ₱0.00</span></span>
                            </div>
                            <div style="text-align: right;">
                                <span style="font-size: 26px; font-weight: 900; color: #1d4ed8;">&#8369;<span id="retTotalRefundDisplay">0.00</span></span>
                            </div>
                        </div>

                        <!-- Approval Notice Alert -->
                        <div class="alert alert-warning" style="margin-bottom: 16px; font-size: 12.5px; border-radius: 6px; padding: 10px 14px;">
                            <i class="fa fa-info-circle"></i> <strong>Audit Policy:</strong> Direct Refund will create a <strong>PENDING APPROVAL</strong> request for manager review. <strong>Exchange (Apply to Cart)</strong> adds instant return credit directly to your active sale.
                        </div>

                        <!-- Confirmation / Action Buttons -->
                        <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid #e2e8f0; padding-top: 15px; flex-wrap: wrap; gap: 8px;">
                            <button type="button" class="btn btn-default" onclick="backToReturnOrdersList()" style="font-weight: 600;">
                                <i class="fa fa-arrow-left"></i> Cancel / Back
                            </button>
                            <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                                <button type="button" id="retExchangeCartBtn" class="btn btn-warning btn-lg" onclick="applyCurrentReturnToCart()" style="background: #d97706; border-color: #b45309; color: #fff; font-weight: 800; padding: 10px 18px; font-size: 15px; border-radius: 6px; box-shadow: 0 4px 10px rgba(217,119,6,0.3);" title="Apply this return item as negative store credit to current sale">
                                    <i class="fa fa-cart-arrow-down"></i> Exchange (Apply to Cart)
                                </button>
                                <button type="submit" id="retProcessSubmitBtn" class="btn btn-danger btn-lg" style="background: #dc2626; border-color: #b91c1c; font-weight: 800; padding: 10px 20px; font-size: 15px; border-radius: 6px; box-shadow: 0 4px 10px rgba(220,38,38,0.3);" title="Submit return for direct customer refund approval">
                                    <i class="fa fa-paper-plane"></i> Direct Refund
                                </button>
                            </div>
                        </div>

                    </form>

                </div>

            </div>

            <!-- Modal Footer (for View 1) -->
            <div class="modal-footer" id="retModalFooterView1" style="background: #f8fafc; border-top: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
                <a href="returns.php" class="btn btn-link" style="color: #2563eb; font-weight: 600; padding-left: 0;">
                    <i class="fa fa-history"></i> View Complete Returns History
                </a>
                <button type="button" class="btn btn-default" data-dismiss="modal" style="font-weight: 600;">Close</button>
            </div>

        </div>
    </div>
</div>

<!-- Return Request Submitted / Confirmation Modal -->
<div class="modal fade" id="posReturnSuccessModal" tabindex="-1" role="dialog" style="z-index: 10070;">
    <div class="modal-dialog" role="document" style="max-width: 550px;">
        <div class="modal-content" style="border-radius: 8px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.3);">
            
            <div class="modal-header" style="background-color: #f59e0b; color: #fff; padding: 15px 20px;">
                <button type="button" class="close" data-dismiss="modal" onclick="closeReturnSuccessModal()" style="color: #fff; opacity: 0.9;">&times;</button>
                <h4 class="modal-title" style="font-weight: bold; font-size: 17px;">
                    <i class="fa fa-clock-o"></i> Return Request Submitted Successfully!
                </h4>
            </div>

            <div class="modal-body" id="posPrintReturnSlipArea" style="padding: 20px; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; color: #333;">
                <!-- Filled dynamically by renderReturnSubmissionSuccessModal(res) -->
            </div>

            <div class="modal-footer" style="background: #f8fafc; border-top: 1px solid #e2e8f0; padding: 12px 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
                <div style="display: flex; gap: 8px; align-items: center;">
                    <button type="button" class="btn btn-primary" onclick="printReturnSlip('thermal200')" style="font-weight: 700; border-radius: 6px; padding: 6px 14px; font-size: 13px; background-color: #0284c7; border-color: #0369a1;" title="Print Return Slip on Thermal Roll">
                        <i class="fa fa-print"></i> Print Slip (Thermal)
                    </button>
                    <button type="button" class="btn btn-info" onclick="printReturnSlip('pdf')" style="font-weight: 700; border-radius: 6px; padding: 6px 14px; font-size: 13px; background-color: #0e7490; border-color: #0891b2;" title="Preview Return Slip in PDF layout">
                        <i class="fa fa-file-pdf-o"></i> PDF Preview
                    </button>
                </div>
                <button type="button" class="btn btn-primary" onclick="closeReturnSuccessModal()" style="font-weight: 700; border-radius: 6px; padding: 6px 20px; font-size: 13px;">
                    <i class="fa fa-check"></i> OK, Got It
                </button>
            </div>

        </div>
    </div>
</div>

<!-- Manager PIN Authorization Modal for 7-Day Return Policy Override -->
<div class="modal fade" id="posManagerPinModal" tabindex="-1" role="dialog" aria-labelledby="posManagerPinModalLabel" aria-hidden="true" style="z-index: 10090;">
    <div class="modal-dialog modal-sm" role="document" style="max-width: 420px; margin: 60px auto;">
        <div class="modal-content" style="border-radius: 8px; overflow: hidden; box-shadow: 0 15px 40px rgba(0,0,0,0.4);">
            <div class="modal-header" style="background: #b91c1c; color: #fff; padding: 14px 18px;">
                <button type="button" class="close" data-dismiss="modal" style="color: #fff; opacity: 0.9; font-size: 22px;">&times;</button>
                <h4 class="modal-title" id="posManagerPinModalLabel" style="font-weight: 800; font-size: 16px; margin: 0; display: flex; align-items: center; gap: 6px;">
                    <i class="fa fa-shield"></i> Manager PIN Required
                </h4>
            </div>
            <div class="modal-body" style="padding: 18px 20px; background: #fff;">
                <div class="alert alert-danger" style="font-size: 12px; margin-bottom: 14px; padding: 8px 10px; border-radius: 6px;">
                    <i class="fa fa-exclamation-circle"></i> <strong>7-Day Return Policy Expired:</strong> This purchase exceeds the 7-day return window. Authorization by an Admin or Store Manager is required.
                </div>
                <form autocomplete="off" onsubmit="executeManagerPinVerify(); return false;" data-lpignore="true" style="margin: 0;">
                    <div class="form-group" style="margin-bottom: 12px;">
                        <label style="font-size: 13px; font-weight: 700; color: #1e293b; margin-bottom: 6px; display: block;">
                            Manager PIN / Password:
                        </label>
                        <div class="input-group">
                            <span class="input-group-addon"><i class="fa fa-key text-danger"></i></span>
                            <input type="password" 
                                   id="posManagerPinInput" 
                                   name="pos_mgr_pin_code"
                                   class="form-control input-lg" 
                                   placeholder="Enter PIN (e.g. 1234)..." 
                                   style="font-size: 16px; font-weight: 700;" 
                                   autocomplete="one-time-code" 
                                   data-lpignore="true"
                                   onkeyup="if(event.key === 'Enter') executeManagerPinVerify();">
                        </div>
                    </div>
                </form>
                <div id="posManagerPinAlert" class="alert alert-danger" style="display: none; font-size: 12px; padding: 6px 10px; margin-bottom: 0;"></div>
            </div>
            <div class="modal-footer" style="background: #f8fafc; border-top: 1px solid #e2e8f0; padding: 10px 18px; display: flex; justify-content: space-between;">
                <button type="button" class="btn btn-default btn-sm" data-dismiss="modal" style="font-weight: 600;">Cancel</button>
                <button type="button" class="btn btn-danger btn-sm" onclick="executeManagerPinVerify()" id="posManagerPinSubmitBtn" style="font-weight: 800; background: #dc2626; border-color: #b91c1c; padding: 6px 16px;">
                    <i class="fa fa-unlock-alt"></i> Authorize Override
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ========================================== -->
<!-- PRINTER CONFIGURATION / SETUP MODAL        -->
<!-- ========================================== -->
<style>
#posPrinterModal .printer-dialog { max-width: 1040px; width: 96%; margin: 28px auto; }
#posPrinterModal .printer-dialog-content { border-radius: 12px; overflow: hidden; box-shadow: 0 25px 70px rgba(0,0,0,0.4); border: none; background: #ffffff; display: flex; flex-direction: column; }
#posPrinterModal .cfg-header { background: linear-gradient(135deg, #0f1b33 0%, #162b4a 100%); color: #ffffff; padding: 16px 22px; display: flex; justify-content: space-between; align-items: center; border-bottom: none; }
#posPrinterModal .cfg-header h1 { font-size: 20px; font-weight: 700; margin: 0; color: #ffffff; letter-spacing: -0.2px; }
#posPrinterModal .cfg-header .sub { font-size: 12px; color: #cbd5e1; margin-top: 3px; font-weight: 400; }
#posPrinterModal .cfg-header .close-btn { background: none; border: 0; color: #ffffff; font-size: 28px; line-height: 1; cursor: pointer; opacity: 0.85; transition: opacity 0.15s; padding: 0; }
#posPrinterModal .cfg-header .close-btn:hover { opacity: 1; color: #ffffff; }
#posPrinterModal .cfg-body { padding: 18px 20px; overflow-y: auto; max-height: calc(92vh - 125px); background: #ffffff; color: #334155; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif; }
#posPrinterModal .cfg-section { border: 1px solid #dbe4ef; border-radius: 8px; padding: 14px; margin-bottom: 14px; background: #ffffff; }
#posPrinterModal .cfg-title { font-size: 13.5px; font-weight: 700; text-transform: uppercase; color: #1668b3; margin: 0 0 13px; display: flex; align-items: center; gap: 6px; letter-spacing: 0.3px; }
#posPrinterModal .cfg-title.green { color: #16834a; }
#posPrinterModal .cfg-title.purple { color: #7250c8; }
#posPrinterModal .cfg-detect { display: grid; grid-template-columns: 1fr auto 175px; gap: 18px; align-items: center; background: #f4faff; border-color: #9ed5ff; }
#posPrinterModal .cfg-label { font-size: 11px; font-weight: 700; color: #1769aa; text-transform: uppercase; letter-spacing: 0.5px; }
#posPrinterModal .cfg-status { font-size: 15px; font-weight: 700; color: #16834a; }
#posPrinterModal .cfg-desc { font-size: 11.5px; color: #64748b; line-height: 1.4; margin-top: 4px; }
#posPrinterModal .cfg-stat { border-left: 1px solid #d6e1ed; padding-left: 16px; }
#posPrinterModal .cfg-btn { border: 1px solid #cbd5e1; background: #ffffff; color: #334155; border-radius: 6px; padding: 9px 13px; font-size: 12px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; gap: 6px; transition: all 0.15s ease; box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
#posPrinterModal .cfg-btn:hover { background: #f8fafc; border-color: #94a3b8; }
#posPrinterModal .cfg-btn.primary { background: #087bc1; color: #ffffff; border-color: #087bc1; }
#posPrinterModal .cfg-btn.primary:hover { background: #076ca9; border-color: #076ca9; }
#posPrinterModal .cfg-btn.success { background: #13a36b; color: #ffffff; border-color: #13a36b; }
#posPrinterModal .cfg-btn.success:hover { background: #108f5d; border-color: #108f5d; }
#posPrinterModal .cfg-btn.orange { background: #f97316; color: #ffffff; border-color: #f97316; }
#posPrinterModal .cfg-btn.orange:hover { background: #ea580c; border-color: #ea580c; }
#posPrinterModal .cfg-active-card { background: #f3fff8; border-color: #a7e4c2; }
#posPrinterModal .cfg-grid6 { display: grid; grid-template-columns: repeat(6, 1fr); }
#posPrinterModal .cfg-item { padding: 3px 12px; border-right: 1px solid #d9e8df; }
#posPrinterModal .cfg-item:last-child { border-right: 0; }
#posPrinterModal .cfg-small { font-size: 10.5px; color: #475569; font-weight: 600; text-transform: uppercase; letter-spacing: 0.3px; }
#posPrinterModal .cfg-value { font-size: 12.5px; color: #16834a; font-weight: 700; margin-top: 3px; }
#posPrinterModal .cfg-main-grid { display: grid; grid-template-columns: 1.4fr 0.9fr; gap: 14px; }
#posPrinterModal .cfg-group { margin-bottom: 13px; }
#posPrinterModal label.cfg-input-lbl { display: block; font-size: 12px; font-weight: 700; margin-bottom: 6px; color: #1e293b; }
#posPrinterModal select.cfg-ctrl, #posPrinterModal input.cfg-ctrl { width: 100%; height: 39px; border: 1px solid #cbd5e1; border-radius: 6px; background: #ffffff; padding: 7px 10px; font-size: 12.5px; color: #334155; box-sizing: border-box; }
#posPrinterModal select.cfg-ctrl:focus, #posPrinterModal input.cfg-ctrl:focus { border-color: #087bc1; outline: none; box-shadow: 0 0 0 2px rgba(8,123,193,0.18); }
#posPrinterModal .cfg-typography { border-top: 1px solid #e2e8f0; padding-top: 14px; margin-top: 17px; }
#posPrinterModal .cfg-fontrow { display: grid; grid-template-columns: 1fr 45px; gap: 7px; }
#posPrinterModal .cfg-sizerow { display: flex; align-items: center; gap: 6px; }
#posPrinterModal .cfg-size-input { width: 75px; height: 39px; text-align: center; font-weight: 700; font-size: 13px; color: #0369a1; border: 1px solid #cbd5e1; border-radius: 6px; padding: 4px; }
#posPrinterModal .cfg-stepper-btn { width: 39px; height: 39px; font-size: 16px; font-weight: 700; border: 1px solid #cbd5e1; background: #ffffff; border-radius: 6px; cursor: pointer; color: #334155; display: flex; align-items: center; justify-content: center; }
#posPrinterModal .cfg-stepper-btn:hover { background: #f1f5f9; }
#posPrinterModal .cfg-quick { display: flex; gap: 6px; margin-top: 8px; }
#posPrinterModal .cfg-q { width: 38px; height: 30px; border: 1px solid #cbd5e1; background: #ffffff; border-radius: 5px; cursor: pointer; font-size: 12px; font-weight: 600; color: #334155; transition: all 0.12s; }
#posPrinterModal .cfg-q:hover { background: #f1f5f9; border-color: #94a3b8; }
#posPrinterModal .cfg-q.sel { background: #087bc1; color: #ffffff; border-color: #087bc1; font-weight: 700; }
#posPrinterModal .cfg-check { display: flex; gap: 8px; margin-top: 12px; font-size: 11.5px; color: #334155; cursor: pointer; align-items: flex-start; }
#posPrinterModal .cfg-check input[type="checkbox"] { width: 16px; height: 16px; margin: 2px 0 0 0; cursor: pointer; }
#posPrinterModal .cfg-orient { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
#posPrinterModal .cfg-orient button { height: 39px; font-size: 12px; font-weight: 600; cursor: pointer; border-radius: 6px; border: 1px solid #cbd5e1; background: #ffffff; color: #334155; transition: all 0.15s; }
#posPrinterModal .cfg-orient button:hover { background: #f8fafc; }
#posPrinterModal .cfg-orient button.sel { background: #087bc1; color: #ffffff; border-color: #087bc1; font-weight: 700; }
#posPrinterModal .cfg-tests { display: flex; flex-wrap: wrap; gap: 8px; }
#posPrinterModal .cfg-tests button { min-width: 98px; }
#posPrinterModal .cfg-pdf { background: #fcfaff; border-color: #ded3f5; }
#posPrinterModal .cfg-copy { display: flex; width: 175px; }
#posPrinterModal .cfg-copy button { width: 39px; height: 39px; font-size: 16px; font-weight: 700; border: 1px solid #cbd5e1; background: #ffffff; cursor: pointer; }
#posPrinterModal .cfg-copy button.minus { border-radius: 6px 0 0 6px; }
#posPrinterModal .cfg-copy button.plus { border-radius: 0 6px 6px 0; }
#posPrinterModal .cfg-copy input { width: 65px; height: 39px; text-align: center; border-radius: 0; border-left: 0; border-right: 0; border-top: 1px solid #cbd5e1; border-bottom: 1px solid #cbd5e1; font-weight: 700; font-size: 13px; color: #334155; }
#posPrinterModal .cfg-safe { display: flex; gap: 10px; background: #f5faff; border: 1px solid #c7e3fa; border-radius: 8px; padding: 12px 14px; font-size: 11.5px; line-height: 1.45; color: #334155; margin-bottom: 0; }
#posPrinterModal .cfg-footer { border-top: 1px solid #e2e8f0; background: #f8fafc; padding: 14px 20px; display: flex; justify-content: space-between; align-items: center; border-bottom-left-radius: 12px; border-bottom-right-radius: 12px; }
@media(max-width:850px){
    #posPrinterModal .cfg-main-grid { grid-template-columns: 1fr; }
    #posPrinterModal .cfg-grid6 { grid-template-columns: repeat(3, 1fr); gap: 8px 0; }
    #posPrinterModal .cfg-detect { grid-template-columns: 1fr; }
    #posPrinterModal .cfg-stat { border-left: 0; border-top: 1px solid #d6e1ed; padding: 9px 0 0; }
}
@media(max-width:600px){
    #posPrinterModal .printer-dialog { margin: 0; width: 100%; max-width: 100%; }
    #posPrinterModal .printer-dialog-content { border-radius: 0; height: 100%; max-height: 100%; }
    #posPrinterModal .cfg-grid6 { grid-template-columns: repeat(2, 1fr); }
    #posPrinterModal .cfg-item { border-right: 0; }
}
</style>

<div class="modal fade" id="posPrinterModal" tabindex="-1" role="dialog" aria-labelledby="posPrinterModalLabel" aria-hidden="true">
    <div class="modal-dialog printer-dialog" style="max-width: 740px;">
        <div class="modal-content printer-dialog-content" style="border-radius: 12px; overflow: hidden; box-shadow: 0 20px 50px rgba(0,0,0,0.35); border: none;">
            <!-- HEADER -->
            <div class="cfg-header" style="background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%); color: #fff; padding: 16px 22px; display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h1 id="posPrinterModalLabel" style="margin: 0; font-size: 17px; font-weight: 700; color: #ffffff; display: flex; align-items: center; gap: 8px;">
                        <span>🖨️</span> Universal POS Printer &amp; ESC/POS Configuration
                    </h1>
                    <div class="sub" style="font-size: 12px; color: #bae6fd; margin-top: 3px;">Configure ESC/POS Hardware Streaming, Auto-Detection (JK-5802H, Epson, Xprinter, A4), Presets &amp; Thermal Density</div>
                </div>
                <button type="button" class="close-btn" data-dismiss="modal" aria-hidden="true" title="Close" style="color: #ffffff; opacity: 0.9; font-size: 24px; background: none; border: none; cursor: pointer;">&times;</button>
            </div>

            <!-- BODY -->
            <div class="cfg-body" style="padding: 20px; background: #f8fafc; max-height: calc(100vh - 160px); overflow-y: auto;">
                
                <!-- 1. LIVE AUTO-DETECTION & PROBING BANNER -->
                <div class="cfg-section cfg-detect" id="posPrinterStatusBox" style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px 16px; margin-bottom: 14px; display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap;">
                    <div style="flex: 1; min-width: 260px;">
                        <div class="cfg-label" style="font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase;">Hardware Probing &amp; Auto-Detection</div>
                        <div class="cfg-status" id="posPrinterStatusText" style="font-weight: 700; color: #0284c7; font-size: 13px; margin-top: 2px;">
                            <i class="fa fa-info-circle"></i> Auto Detect Active &bull; Ready (JK-5802H / System Spooler)
                        </div>
                        <div class="cfg-desc" style="font-size: 11.5px; color: #64748b; margin-top: 2px;">Probes connected USB thermal printers, local ESC/POS daemons, and system drivers.</div>
                    </div>
                    <div>
                        <button type="button" class="cfg-btn primary" onclick="refreshPOSPrinters()" style="font-weight: 700; padding: 7px 16px; font-size: 12px; background: #0284c7; color: #fff; border: none; border-radius: 6px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);" title="Re-probe connected USB &amp; ESC/POS hardware">
                            <i class="fa fa-refresh"></i> Re-probe Hardware
                        </button>
                    </div>
                </div>

                <!-- 2. ACTIVE CONFIGURATION DIAGNOSTIC CARD -->
                <div class="cfg-section cfg-active-card" id="posCompatibilityBadge" style="background: #f0fdf4; border: 1.5px solid #86efac; border-radius: 8px; padding: 12px 16px; margin-bottom: 16px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                        <h3 class="cfg-title green" style="margin: 0; font-size: 12px; font-weight: 800; color: #166534; text-transform: uppercase; letter-spacing: 0.5px;">🔖 Active Print Profile Diagnostic</h3>
                        <span id="posEngineBadge" style="font-size: 11px; font-weight: 800; color: #166534; background: #dcfce7; padding: 2px 8px; border-radius: 4px; border: 1px solid #86efac;">Browser Dialog Mode</span>
                    </div>
                    <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px;">
                        <div style="background: #fff; border: 1px solid #bbf7d0; border-radius: 6px; padding: 8px 10px;">
                            <div style="font-size: 10.5px; color: #64748b; font-weight: 700;">🖨️ Target Device</div>
                            <div style="font-size: 12.5px; font-weight: 800; color: #166534; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" id="posActivePrinterVal">JK-5802H 58mm</div>
                        </div>
                        <div style="background: #fff; border: 1px solid #bbf7d0; border-radius: 6px; padding: 8px 10px;">
                            <div style="font-size: 10.5px; color: #64748b; font-weight: 700;">📏 Roll &amp; Content</div>
                            <div style="font-size: 12.5px; font-weight: 800; color: #0284c7;" id="posActiveWidthVal">58mm (53mm)</div>
                            <span id="aw" style="display:none;">58 mm</span>
                            <span id="posActiveContentWidthVal" style="display:none;">53 mm</span>
                        </div>
                        <div style="background: #fff; border: 1px solid #bbf7d0; border-radius: 6px; padding: 8px 10px;">
                            <div style="font-size: 10.5px; color: #64748b; font-weight: 700;">🖋️ Font &amp; Size</div>
                            <div style="font-size: 12.5px; font-weight: 800; color: #0f172a;" id="posActiveFontCombinedVal"><span id="posActiveFontNameVal">Courier</span> <span id="posActiveFontVal">10.5 pt</span></div>
                            <span id="af" style="display:none;">Courier New</span>
                            <span id="as" style="display:none;">10.5 pt</span>
                        </div>
                        <div style="background: #fff; border: 1px solid #bbf7d0; border-radius: 6px; padding: 8px 10px;">
                            <div style="font-size: 10.5px; color: #64748b; font-weight: 700;">🔥 Density / Heat</div>
                            <div style="font-size: 12.5px; font-weight: 800; color: #d97706;" id="posActiveDensityVal">120% Dark</div>
                            <span id="posActiveLineHeightVal" style="display:none;">1.25</span>
                            <span id="posActiveCopiesVal" style="display:none;">1 Copy</span>
                            <span id="posActiveA4Val" style="display:none;">A4 Portrait</span>
                            <span id="posActivePdfVal" style="display:none;">Preview Only</span>
                        </div>
                    </div>
                </div>

                <form id="posPrinterForm">
                    <!-- SECTION 1: CONNECTED PRINTER SELECTION & AUTO-DETECT -->
                    <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px; margin-bottom: 14px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                            <label class="cfg-input-lbl" style="font-size: 12px; font-weight: 800; color: #1e293b; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 0;">
                                1. Connected Printer / Driver Profile
                            </label>
                            <span style="font-size: 11px; color: #64748b;">Select or let system auto-detect</span>
                        </div>
                        <select id="posPrinterSelect" class="form-control" onchange="handlePrinterSelectChange()" style="height: 38px; font-weight: 700; font-size: 13px; color: #0369a1; border-color: #cbd5e1;">
                            <optgroup label="Auto-Detected &amp; System Printers" id="posDetectedGroup">
                                <option value="system_default" selected>AUTO DETECT (System Default / OS Print Spooler)</option>
                            </optgroup>
                            <optgroup label="Popular 58mm Thermal Printers (POS-58)">
                                <option value="jk_5802h">JK-5802H 58mm Thermal (USB / Bluetooth / COM)</option>
                                <option value="xprinter_58">Xprinter XP-58 / POS-58 (58mm Roll)</option>
                                <option value="generic_58">Generic ESC/POS Thermal 58mm (32 Columns)</option>
                            </optgroup>
                            <optgroup label="Popular 80mm Thermal Printers (POS-80)">
                                <option value="epson_tmt20">Epson TM-T20 / TM-T82 / TM-T88 (80mm ESC/POS)</option>
                                <option value="xprinter_80">Xprinter XP-80 / POS-80 (80mm Roll)</option>
                                <option value="generic_80">Generic ESC/POS Thermal 80mm (48 Columns)</option>
                                <option value="network_escpos">Network LAN / WiFi ESC/POS Thermal (Port 9100)</option>
                            </optgroup>
                            <optgroup label="Standard Document Printers">
                                <option value="generic_a4">Standard Office / Inkjet / Laser (A4 Sheet)</option>
                            </optgroup>
                        </select>
                    </div>

                    <!-- SECTION 2: DUAL PRINT TRANSMISSION ENGINE -->
                    <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px; margin-bottom: 14px;">
                        <div style="font-size: 12px; font-weight: 800; color: #1e293b; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 10px;">
                            2. Print Transmission Engine
                        </div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                            <label style="background: #f8fafc; border: 1.5px solid #cbd5e1; border-radius: 8px; padding: 12px 14px; cursor: pointer; display: flex; gap: 10px; align-items: flex-start; transition: all 0.2s;" id="posEngineCardBrowser">
                                <input type="radio" name="posPrintEngine" id="posEngineBrowser" value="browser" checked onchange="handlePrintEngineChange('browser')" style="margin-top: 2px;">
                                <div>
                                    <div style="font-weight: 800; color: #0284c7; font-size: 13px;">🌐 Browser / OS Print Dialog</div>
                                    <div style="font-size: 11.5px; color: #64748b; line-height: 1.35; margin-top: 3px;">High-contrast 1-bit thermal HTML with print preview. Universal compatibility across all PCs &amp; browsers.</div>
                                </div>
                            </label>

                            <label style="background: #f8fafc; border: 1.5px solid #cbd5e1; border-radius: 8px; padding: 12px 14px; cursor: pointer; display: flex; gap: 10px; align-items: flex-start; transition: all 0.2s;" id="posEngineCardEscpos">
                                <input type="radio" name="posPrintEngine" id="posEngineEscpos" value="escpos" onchange="handlePrintEngineChange('escpos')" style="margin-top: 2px;">
                                <div>
                                    <div style="font-weight: 800; color: #059669; font-size: 13px;">⚡ Direct ESC/POS Hardware Stream</div>
                                    <div style="font-size: 11.5px; color: #64748b; line-height: 1.35; margin-top: 3px;">Instant zero-dialog print, pitch-black hardware ROM fonts, auto-cutter &amp; cash drawer pulse (Local Daemon / USB).</div>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- SECTION 3: PAPER ROLL SIZE & PRINTABLE WIDTH PRESETS -->
                    <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px; margin-bottom: 14px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                            <div style="font-size: 12px; font-weight: 800; color: #1e293b; text-transform: uppercase; letter-spacing: 0.5px;">
                                3. Paper Size &amp; Width Presets
                            </div>
                            <span style="font-size: 11.5px; color: #64748b;">1-Click Quick Configuration</span>
                        </div>

                        <!-- 1-Click Preset Buttons -->
                        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 14px;">
                            <button type="button" class="btn btn-default" id="posPresetBtn58" onclick="setPaperPreset('58')" style="text-align: left; padding: 10px 12px; border: 1.5px solid #0284c7; background: #f0f9ff; border-radius: 6px;">
                                <div style="font-weight: 800; color: #0284c7; font-size: 12.5px;">🟢 58 mm Roll</div>
                                <div style="font-size: 11px; color: #64748b; margin-top: 2px;">53mm Content &bull; 32 Columns<br><strong>(JK-5802H / POS-58)</strong></div>
                            </button>
                            <button type="button" class="btn btn-default" id="posPresetBtn80" onclick="setPaperPreset('80')" style="text-align: left; padding: 10px 12px; border: 1px solid #cbd5e1; background: #ffffff; border-radius: 6px;">
                                <div style="font-weight: 800; color: #334155; font-size: 12.5px;">🔵 80 mm Roll</div>
                                <div style="font-size: 11px; color: #64748b; margin-top: 2px;">72mm Content &bull; 48 Columns<br><strong>(Epson / POS-80)</strong></div>
                            </button>
                            <button type="button" class="btn btn-default" id="posPresetBtnA4" onclick="setPaperPreset('a4')" style="text-align: left; padding: 10px 12px; border: 1px solid #cbd5e1; background: #ffffff; border-radius: 6px;">
                                <div style="font-weight: 800; color: #334155; font-size: 12.5px;">⚪ A4 Sheet</div>
                                <div style="font-size: 11px; color: #64748b; margin-top: 2px;">195mm Content &bull; Laser/Inkjet<br><strong>(Standard Invoice)</strong></div>
                            </button>
                        </div>

                        <!-- Manual Fine-Tuning Grid -->
                        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 10px 12px;">
                            <div>
                                <label style="font-size: 11px; font-weight: 700; color: #475569; display: block; margin-bottom: 4px;">Paper Roll Width</label>
                                <div style="display: flex; align-items: center; gap: 6px;">
                                    <input type="number" id="posPaperWidth" class="form-control input-sm" style="font-weight: 700; text-align: center;" min="40" max="210" value="58" onchange="handlePaperWidthChange(this.value)">
                                    <span style="font-size: 12px; font-weight: 700; color: #64748b;">mm</span>
                                </div>
                            </div>
                            <div>
                                <label style="font-size: 11px; font-weight: 700; color: #475569; display: block; margin-bottom: 4px;">Printable Content Width</label>
                                <div style="display: flex; align-items: center; gap: 6px;">
                                    <input type="number" id="posPrintContentWidth" class="form-control input-sm" style="font-weight: 700; text-align: center; color: #0284c7;" min="35" max="210" value="53" oninput="handlePrintContentWidthInput(this.value)">
                                    <span style="font-size: 12px; font-weight: 700; color: #64748b;">mm</span>
                                </div>
                            </div>
                            <div>
                                <label style="font-size: 11px; font-weight: 700; color: #475569; display: block; margin-bottom: 4px;">Character Columns</label>
                                <div style="display: flex; align-items: center; gap: 6px;">
                                    <input type="number" id="posPrintColumns" class="form-control input-sm" style="font-weight: 700; text-align: center;" min="24" max="80" value="32" onchange="updateCompatibilityBadge()">
                                    <span style="font-size: 12px; font-weight: 700; color: #64748b;">cols</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- SECTION 4: THERMAL CLARITY, HEAD DENSITY & TYPOGRAPHY -->
                    <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px; margin-bottom: 14px;">
                        <div style="font-size: 12px; font-weight: 800; color: #1e293b; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 12px;">
                            4. Thermal Darkness, Head Heat &amp; Typography
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 14px;">
                            <!-- Hardware ROM Font -->
                            <div>
                                <label class="cfg-input-lbl" style="font-size: 11.5px; font-weight: 700; color: #334155; margin-bottom: 4px;">ESC/POS Hardware ROM Font</label>
                                <select id="posHardwareFont" class="form-control input-sm" onchange="handleHardwareFontChange()">
                                    <option value="font_a" selected>Font A (12×24 Standard - Sharp &amp; Bold)</option>
                                    <option value="font_b">Font B (9×17 Condensed - High Density)</option>
                                    <option value="font_a_bold">Font A + Double Strike (Ultra Bold)</option>
                                </select>
                            </div>

                            <!-- Thermal Head Density / Heat -->
                            <div>
                                <label class="cfg-input-lbl" style="font-size: 11.5px; font-weight: 700; color: #334155; margin-bottom: 4px;">Thermal Head Heat / Darkness</label>
                                <select id="posThermalDensity" class="form-control input-sm" onchange="handleThermalDensityChange()">
                                    <option value="100">100% Normal Heat</option>
                                    <option value="120" selected>120% Dark / High Contrast (Recommended)</option>
                                    <option value="140">140% Ultra Dark / Deep Pitch Black</option>
                                </select>
                            </div>
                        </div>

                        <!-- Browser Font Family -->
                        <div class="cfg-group" style="margin-bottom: 14px;">
                            <label class="cfg-input-lbl" style="font-size: 11.5px; font-weight: 700; color: #334155; margin-bottom: 4px;">Browser Print Font Family (font-family)</label>
                            <select id="posThermalFontName" class="form-control input-sm" onchange="handleThermalFontChange()">
                                <option value="Courier New" selected>Courier New (Standard POS Monospace)</option>
                                <option value="Consolas">Consolas</option>
                                <option value="Lucida Console">Lucida Console</option>
                                <option value="Liberation Mono">Liberation Mono</option>
                                <option value="Arial">Arial (Clean Sans-Serif)</option>
                                <option value="Tahoma">Tahoma</option>
                            </select>
                        </div>

                        <!-- Font Size & Steppers -->
                        <div class="cfg-group" style="margin-bottom: 14px;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                                <label class="cfg-input-lbl" style="font-size: 11.5px; font-weight: 700; color: #334155; margin-bottom: 0;">Thermal Font Size (font-size)</label>
                                <span id="posThermalFontSizeBadge" style="font-size: 11px; font-weight: 800; color: #0369a1; background: #e0f2fe; padding: 2px 8px; border-radius: 4px; border: 1px solid #bae6fd;">10.5 pt</span>
                            </div>
                            <div style="display: flex; gap: 8px; align-items: center; margin-bottom: 6px;">
                                <button type="button" class="btn btn-default btn-sm" onclick="adjustThermalFontSize(-0.5)" style="font-weight: 800; width: 34px;">−</button>
                                <input type="number" id="posThermalCustomFontSize" class="form-control input-sm" style="width: 100px; text-align: center; font-weight: 700;" min="8" max="24" step="0.5" value="10.5" oninput="syncFontSizeFromCustom(this.value)">
                                <button type="button" class="btn btn-default btn-sm" onclick="adjustThermalFontSize(0.5)" style="font-weight: 800; width: 34px;">+</button>
                                <b style="font-size: 13px; color: #334155;">pt</b>
                            </div>
                            <div class="cfg-quick" style="display: flex; gap: 6px; flex-wrap: wrap;">
                                <button type="button" class="btn btn-xs btn-default" onclick="setThermalFontSize(9.5)">9.5 pt</button>
                                <button type="button" class="btn btn-xs btn-primary" onclick="setThermalFontSize(10.5)">10.5 pt (Default)</button>
                                <button type="button" class="btn btn-xs btn-default" onclick="setThermalFontSize(11)">11 pt</button>
                                <button type="button" class="btn btn-xs btn-default" onclick="setThermalFontSize(12)">12 pt</button>
                                <button type="button" class="btn btn-xs btn-default" onclick="setThermalFontSize(14)">14 pt</button>
                            </div>
                        </div>

                        <!-- Line Height & Steppers -->
                        <div class="cfg-group" style="margin-bottom: 0;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                                <label class="cfg-input-lbl" style="font-size: 11.5px; font-weight: 700; color: #334155; margin-bottom: 0;">Line Spacing / Height (line-height)</label>
                                <span id="posThermalLineHeightBadge" style="font-size: 11px; font-weight: 800; color: #d97706; background: #fef3c7; padding: 2px 8px; border-radius: 4px; border: 1px solid #fde68a;">1.25</span>
                            </div>
                            <div style="display: flex; gap: 8px; align-items: center; margin-bottom: 6px;">
                                <button type="button" class="btn btn-default btn-sm" onclick="adjustThermalLineHeight(-0.05)" style="font-weight: 800; width: 34px;">−</button>
                                <input type="number" id="posThermalLineHeight" class="form-control input-sm" style="width: 100px; text-align: center; font-weight: 700;" min="1.0" max="2.0" step="0.05" value="1.25" oninput="syncLineHeightFromCustom(this.value)">
                                <button type="button" class="btn btn-default btn-sm" onclick="adjustThermalLineHeight(0.05)" style="font-weight: 800; width: 34px;">+</button>
                                <span style="font-size: 11.5px; color: #64748b;">(Vertical spacing between receipt items)</span>
                            </div>
                            <div class="cfg-quick" style="display: flex; gap: 6px; flex-wrap: wrap;">
                                <button type="button" class="btn btn-xs btn-default" onclick="setThermalLineHeight(1.15)">1.15 (Compact)</button>
                                <button type="button" class="btn btn-xs btn-primary" onclick="setThermalLineHeight(1.25)">1.25 (Default)</button>
                                <button type="button" class="btn btn-xs btn-default" onclick="setThermalLineHeight(1.35)">1.35</button>
                                <button type="button" class="btn btn-xs btn-default" onclick="setThermalLineHeight(1.50)">1.50 (Relaxed)</button>
                            </div>
                        </div>
                    </div>

                    <!-- SECTION 5: HARDWARE PERIPHERAL AUTOMATION & COPIES -->
                    <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px; margin-bottom: 14px;">
                        <div style="font-size: 12px; font-weight: 800; color: #1e293b; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 12px;">
                            5. Hardware Peripheral Automation &amp; Copies
                        </div>

                        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-bottom: 14px;">
                            <label style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 10px 12px; cursor: pointer; display: flex; align-items: center; gap: 8px; font-size: 12px; font-weight: 700; color: #334155; margin-bottom: 0;">
                                <input type="checkbox" id="posAutoCashDrawer" checked style="width: 16px; height: 16px; cursor: pointer;">
                                <span>💵 Auto Cash Drawer (ESC p)</span>
                            </label>

                            <label style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 10px 12px; cursor: pointer; display: flex; align-items: center; gap: 8px; font-size: 12px; font-weight: 700; color: #334155; margin-bottom: 0;">
                                <input type="checkbox" id="posAutoCutter" checked style="width: 16px; height: 16px; cursor: pointer;">
                                <span>✂️ Auto Paper Cut (GS V)</span>
                            </label>

                            <label style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 10px 12px; cursor: pointer; display: flex; align-items: center; gap: 8px; font-size: 12px; font-weight: 700; color: #334155; margin-bottom: 0;">
                                <input type="checkbox" id="posBuzzerBeep" style="width: 16px; height: 16px; cursor: pointer;">
                                <span>🔔 Buzzer Beep (ESC B)</span>
                            </label>
                        </div>

                        <div style="display: flex; justify-content: space-between; align-items: center; padding-top: 10px; border-top: 1px solid #f1f5f9;">
                            <div style="font-size: 12px; font-weight: 700; color: #334155;">Print Copies:</div>
                            <div style="display: flex; gap: 6px; align-items: center;">
                                <button type="button" class="btn btn-default btn-xs" onclick="adjustPrintCopies(-1)" style="font-weight: 800; width: 28px; height: 26px;">−</button>
                                <input type="number" id="posPrintCopies" class="form-control input-sm" style="width: 60px; text-align: center; font-weight: 800; height: 26px;" min="1" max="5" value="1">
                                <button type="button" class="btn btn-default btn-xs" onclick="adjustPrintCopies(1)" style="font-weight: 800; width: 28px; height: 26px;">+</button>
                            </div>
                        </div>
                    </div>

                    <!-- Hidden Compatibility Inputs -->
                    <input type="hidden" id="posPrinterType" value="thermal">
                    <input type="hidden" id="posThermalBoldImportant" value="1">
                    <input type="hidden" id="posA4Orientation" value="portrait">
                </form>

                <!-- 6. TEST PRINT TRIGGER BOX -->
                <div style="background: #f0f9ff; border: 1.5px solid #bae6fd; border-radius: 8px; padding: 12px 16px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                    <div>
                        <div style="font-weight: 800; color: #0369a1; font-size: 13px;">🧪 Alignment &amp; Clarity Test</div>
                        <div style="font-size: 11.5px; color: #0284c7;">Verify column bounds, double-strike density, and typography on your printer:</div>
                    </div>
                    <button type="button" class="btn btn-info" onclick="testPrintActiveThermalProfile()" style="font-weight: 800; background: #0284c7; border-color: #0369a1; padding: 7px 18px; font-size: 13px; box-shadow: 0 2px 6px rgba(2,132,199,0.25);">
                        <i class="fa fa-print"></i> Test Print Active Profile
                    </button>
                </div>

            </div>

            <!-- FOOTER -->
            <div class="cfg-footer" style="background: #ffffff; border-top: 1px solid #e2e8f0; padding: 14px 20px; display: flex; justify-content: flex-end; align-items: center; gap: 10px;">
                <button type="button" class="btn btn-default" data-dismiss="modal" style="padding: 8px 18px; font-weight: 600; border-radius: 6px;">Cancel</button>
                <button type="button" class="btn btn-success" onclick="savePOSPrinterSettings()" style="padding: 8px 24px; font-weight: 800; background-color: #059669; border-color: #047857; border-radius: 6px; box-shadow: 0 2px 6px rgba(5,150,105,0.25);">
                    <i class="fa fa-save"></i> Save Configuration
                </button>
            </div>
        </div>
    </div>
</div>

<?php if ($pos_success_receipt): 
    $cashier_name = !empty($_SESSION['supplier_user']['full_name']) ? $_SESSION['supplier_user']['full_name'] : (!empty($_SESSION['supplier_user']['username']) ? $_SESSION['supplier_user']['username'] : 'Cashier');
    $pos_success_receipt['cashier_name'] = $cashier_name;
    $receipt_id = htmlspecialchars($pos_success_receipt['payment_id']);
?>
<script>
window.posReceiptSuccessData = <?php echo json_encode($pos_success_receipt); ?>;
</script>
<div class="modal fade in" id="posSuccessModal" tabindex="-1" role="dialog" style="display: block; background: rgba(0,0,0,0.65); z-index: 10080;">
    <div class="modal-dialog modal-lg" role="document" style="max-width: 720px; margin: 30px auto;">
        <div class="modal-content" style="border-radius: 10px; overflow: hidden; box-shadow: 0 15px 40px rgba(0,0,0,0.35); border: none;">
            
            <!-- 1. Modal Header -->
            <div class="modal-header" style="background: linear-gradient(135deg, #059669 0%, #047857 100%); color: #fff; padding: 14px 20px; display: flex; justify-content: space-between; align-items: center;">
                <h4 class="modal-title" style="font-weight: 700; font-size: 16px; margin: 0; display: flex; align-items: center; gap: 8px;">
                    <i class="fa fa-check-circle" style="font-size: 18px;"></i> P.O.  RECEIPT &bull; <?php echo $receipt_id; ?>
                </h4>
                <button type="button" class="close" data-dismiss="modal" onclick="closeReceiptModal()" style="color: #fff; opacity: 0.9; font-size: 24px; text-shadow: none; line-height: 1; padding: 0 4px;">&times;</button>
            </div>
            
            <!-- 2. Scrollable Clean HTML Screen View -->
            <div class="modal-body" style="background: #f8fafc; padding: 20px; max-height: calc(100vh - 200px); overflow-y: auto;">
                
                <!-- Status Banner -->
                <div style="background: #ecfdf5; border: 1.5px solid #10b981; border-radius: 8px; padding: 12px 16px; margin-bottom: 16px; display: flex; justify-content: space-between; align-items: center;">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <i class="fa fa-check-circle" style="font-size: 22px; color: #059669;"></i>
                        <div>
                            <div style="font-weight: 800; color: #065f46; font-size: 14px; text-transform: uppercase;">
                                PAYMENT STATUS: PAID / SETTLED
                            </div>
                            <div style="font-size: 12px; color: #047857;">
                                Transaction recorded and P.O. receipt generated.
                            </div>
                        </div>
                    </div>
                    <span class="label label-success" style="font-size: 12px; padding: 5px 12px; font-weight: 700; background-color: #059669 !important;">
                        COMPLETED
                    </span>
                </div>

                <!-- 2-Column Metadata Cards -->
                <div class="row" style="margin-bottom: 16px;">
                    <div class="col-sm-6">
                        <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px 14px; height: 100%;">
                            <div style="font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 6px;">Transaction Details</div>
                            <div style="font-size: 13px; line-height: 1.5; color: #1e293b;">
                                <div style="margin-bottom: 6px;">
                                    <strong>P.O. Receipt No:</strong>
                                    <div style="padding-left: 8px; margin-top: 2px;">
                                        <span style="font-family: monospace; font-weight: 700; font-size: 13.5px; color: #059669;"><?php echo $receipt_id; ?></span>
                                    </div>
                                </div>
                                <div style="margin-bottom: 4px;"><strong>Cashier:</strong> <?php echo htmlspecialchars($cashier_name); ?></div>
                                <div><strong>Date &amp; Time:</strong> <?php echo htmlspecialchars($pos_success_receipt['payment_date']); ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px 14px; height: 100%;">
                            <div style="font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 6px;">Customer &amp; Payment</div>
                            <div style="font-size: 13px; line-height: 1.6; color: #1e293b;">
                                <div><strong>Customer:</strong> <?php echo htmlspecialchars($pos_success_receipt['customer_name'] ?? 'Walk-in Customer'); ?></div>
                                <div><strong>Payment Method:</strong> <?php echo htmlspecialchars($pos_success_receipt['payment_method'] ?? 'Cash'); ?></div>
                                <div><strong>Store:</strong> <?php echo htmlspecialchars($pos_success_receipt['supplier_name'] ?? 'SAM & INRI CONSTRUCTION SUPPLY'); ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Line Items Table -->
                <div class="table-responsive" style="margin-bottom: 16px; background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden;">
                    <table class="table table-bordered table-striped" style="margin-bottom: 0; font-size: 13px;">
                        <thead style="background: #0f172a; color: #fff;">
                            <tr>
                                <th style="width: 40px; text-align: center;">#</th>
                                <th>Item Description &amp; Specifications</th>
                                <th style="width: 70px; text-align: center;">Qty</th>
                                <th style="width: 110px; text-align: right;">Unit Price</th>
                                <th style="width: 120px; text-align: right;">Amount (₱)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pos_success_receipt['items'] as $idx => $item): 
                                $is_ret_credit = (($item['item_type'] ?? '') === 'RETURN_CREDIT');
                                $item_qty = intval($item['qty']);
                                $item_price = floatval($item['price']);
                                $item_gross = $item_price * $item_qty;
                                $item_disc = floatval($item['discount_amount'] ?? 0);
                                $item_net = floatval($item['line_net'] ?? ($item_gross - $item_disc));
                            ?>
                            <tr <?php echo $is_ret_credit ? 'style="background-color: #fef2f2;"' : ''; ?>>
                                <td style="text-align: center; color: #64748b;"><?php echo ($idx + 1); ?></td>
                                <td>
                                    <?php if ($is_ret_credit): ?>
                                        <span class="label label-danger" style="background-color: #dc2626; font-size: 10px; font-weight: 800; padding: 1px 5px; margin-right: 4px;"><i class="fa fa-undo"></i> RETURN CREDIT</span>
                                    <?php endif; ?>
                                    <span style="font-weight: 700; color: #0f172a;"><?php echo htmlspecialchars($item['name']); ?></span>
                                    <?php if (!empty($item['product_details'])): ?>
                                        <div class="badge" style="background: <?php echo $is_ret_credit ? '#fee2e2; color: #991b1b' : '#e2e8f0; color: #334155'; ?>; font-size: 11px; margin-top: 2px; white-space: normal; text-align: left;"><?php echo htmlspecialchars($item['product_details']); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: center; font-weight: 900; font-size: 14px; color: #000000; -webkit-print-color-adjust: exact;"><strong style="font-weight: 900; color: #000000;"><?php echo $item_qty; ?></strong></td>
                                <td style="text-align: right; color: #475569;">₱<?php echo number_format(abs($item_price), 2); ?></td>
                                <td style="text-align: right; font-weight: 700; color: <?php echo $is_ret_credit ? '#dc2626' : '#0f172a'; ?>;">
                                    <?php echo $is_ret_credit ? '-₱' . number_format(abs($item_net), 2) : '₱' . number_format($item_net, 2); ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Financial Totals Box -->
                <div class="row">
                    <div class="col-sm-6"></div>
                    <div class="col-sm-6">
                        <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px 16px;">
                            <div style="display: flex; justify-content: space-between; font-size: 13px; margin-bottom: 6px; color: #64748b;">
                                <span>Subtotal:</span>
                                <span style="font-weight: 600; color: #1e293b;">₱<?php echo number_format($pos_success_receipt['gross_subtotal'] ?? $pos_success_receipt['subtotal'], 2); ?></span>
                            </div>
                            <?php if (!empty($pos_success_receipt['total_discount_savings']) && $pos_success_receipt['total_discount_savings'] > 0): ?>
                            <div style="display: flex; justify-content: space-between; font-size: 13px; margin-bottom: 6px; color: #dc2626;">
                                <span>Discount:</span>
                                <span style="font-weight: 600;">-₱<?php echo number_format($pos_success_receipt['total_discount_savings'], 2); ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($pos_success_receipt['total_return_credits']) && $pos_success_receipt['total_return_credits'] > 0): ?>
                            <div style="display: flex; justify-content: space-between; font-size: 13px; margin-bottom: 6px; color: #dc2626;">
                                <span><i class="fa fa-undo"></i> Return Credit:</span>
                                <span style="font-weight: 700;">-₱<?php echo number_format($pos_success_receipt['total_return_credits'], 2); ?></span>
                            </div>
                            <?php endif; ?>
                            <div style="display: flex; justify-content: space-between; font-size: 16px; font-weight: 800; color: #0f172a; border-top: 2px dashed #cbd5e1; padding-top: 8px; margin-top: 6px;">
                                <span>TOTAL PAID:</span>
                                <span style="color: #059669; font-size: 18px;">₱<?php echo number_format($pos_success_receipt['grand_total'], 2); ?></span>
                            </div>
                            <?php if (!empty($pos_success_receipt['refund_due']) && $pos_success_receipt['refund_due'] > 0): ?>
                            <div style="display: flex; justify-content: space-between; font-size: 13px; margin-top: 6px; color: #d97706; font-weight: 700;">
                                <span>Refund Paid Out:</span>
                                <span style="font-weight: 800; color: #d97706;">₱<?php echo number_format($pos_success_receipt['refund_due'], 2); ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if (isset($pos_success_receipt['amount_tendered']) && $pos_success_receipt['amount_tendered'] > 0): ?>
                            <div style="display: flex; justify-content: space-between; font-size: 12.5px; margin-top: 6px; color: #64748b;">
                                <span>Amount Tendered:</span>
                                <span>₱<?php echo number_format($pos_success_receipt['amount_tendered'], 2); ?></span>
                            </div>
                            <div style="display: flex; justify-content: space-between; font-size: 12.5px; margin-top: 3px; color: #64748b;">
                                <span>Change:</span>
                                <span>₱<?php echo number_format($pos_success_receipt['change_amount'], 2); ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Hidden 32-Column Thermal Print Template for Direct Streaming (53mm Dynamic Roll Height) -->
                <div id="receipt-printable-thermal-pos" style="display: none;">
                    <div style="text-align: center; letter-spacing: -0.5px; font-weight: bold; margin-bottom: 3px; overflow: hidden; white-space: nowrap;">================================</div>
                    <div style="text-align: center;">
                        <div style="font-size: 12pt; font-weight: bold; text-transform: uppercase; color: #000; line-height: 1.2;"><?php echo htmlspecialchars(strtoupper($pos_success_receipt['supplier_name'] ?? 'SAM & INRI CONSTRUCTION SUPPLY')); ?></div>
                        <div style="font-size: 10pt; margin-top: 2px; color: #000;">Tel: <?php echo !empty($pos_success_receipt['supplier_phone']) ? htmlspecialchars($pos_success_receipt['supplier_phone']) : '09612735733'; ?></div>
                        <div style="font-size: 11pt; font-weight: bold; text-transform: uppercase; margin-top: 2px; color: #000;">P.O.  RECEIPT</div>
                        <div style="font-size: 10pt; font-weight: bold; text-transform: uppercase; color: #000;">(PAID)</div>
                    </div>
                    <div style="text-align: center; letter-spacing: -0.5px; font-weight: bold; margin-top: 3px; margin-bottom: 4px; overflow: hidden; white-space: nowrap;">================================</div>

                    <table style="width: 100%; font-family: 'Courier New', Consolas, monospace; font-size: 10.5pt; line-height: 1.25; margin-bottom: 2px; border-collapse: collapse;">
                        <tr>
                            <td colspan="2" style="font-weight: bold; padding: 1px 0; vertical-align: top; white-space: nowrap;">P.O. Receipt No:</td>
                        </tr>
                        <tr>
                            <td colspan="2" style="padding: 0 0 2px 8px; vertical-align: top; font-weight: bold;">&nbsp;&nbsp;<?php echo $receipt_id; ?></td>
                        </tr>
                        <tr>
                            <td style="width: 32%; font-weight: bold; padding: 1px 0; vertical-align: top; white-space: nowrap;">CASHIER   :</td>
                            <td style="padding: 1px 0; vertical-align: top;"><?php echo htmlspecialchars($cashier_name); ?></td>
                        </tr>
                        <tr>
                            <td style="font-weight: bold; padding: 1px 0; vertical-align: top; white-space: nowrap;">CUSTOMER  :</td>
                            <td style="padding: 1px 0; vertical-align: top;"><?php echo htmlspecialchars($pos_success_receipt['customer_name'] ?? 'Walk-in Customer'); ?></td>
                        </tr>
                        <tr>
                            <td style="font-weight: bold; padding: 1px 0; vertical-align: top; white-space: nowrap;">PAYMENT   :</td>
                            <td style="padding: 1px 0; vertical-align: top;"><?php echo htmlspecialchars($pos_success_receipt['payment_method'] ?? 'Cash'); ?> (PAID)</td>
                        </tr>
                        <tr>
                            <td style="font-weight: bold; padding: 1px 0; vertical-align: top; white-space: nowrap;">DATE      :</td>
                            <td style="padding: 1px 0; vertical-align: top;"><?php echo htmlspecialchars($pos_success_receipt['payment_date']); ?></td>
                        </tr>
                    </table>

                    <div style="text-align: center; letter-spacing: -0.5px; margin: 2px 0; overflow: hidden; white-space: nowrap;">--------------------------------</div>
                    <table style="width: 100%; border-collapse: collapse; font-family: 'Courier New', Consolas, monospace; font-size: 10.5pt; line-height: 1.2; margin: 0;">
                        <thead>
                            <tr>
                                <th style="text-align: left; padding: 1px 0; font-weight: bold; width: 68%;">ITEM DESCRIPTION</th>
                                <th style="text-align: right; padding: 1px 0; font-weight: bold; width: 32%;">AMOUNT</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pos_success_receipt['items'] as $item): 
                                $is_ret_credit = (($item['item_type'] ?? '') === 'RETURN_CREDIT');
                                $item_qty = intval($item['qty']);
                                $item_price = floatval($item['price']);
                                $item_gross = $item_price * $item_qty;
                                $item_disc = floatval($item['discount_amount'] ?? 0);
                                $item_net = floatval($item['line_net'] ?? ($item_gross - $item_disc));
                                $unit_label = ($item_qty > 1 ? 'pcs' : 'pc');
                            ?>
                            <tr>
                                <td colspan="2" style="text-align: left; padding-top: 3px; font-weight: bold; word-break: break-word;">
                                    <?php echo $is_ret_credit ? '[RETURN CREDIT] ' : ''; ?><?php echo htmlspecialchars($item['name']); ?>
                                </td>
                            </tr>
                            <tr>
                                <td style="text-align: left; padding-left: 8px; padding-bottom: 3px;">
                                    <strong style="font-weight: 900; font-size: 11pt; color: #000;"><?php echo $item_qty; ?> <?php echo $unit_label; ?></strong> @ <?php echo number_format(abs($item_price), 2); ?>
                                </td>
                                <td style="text-align: right; padding-bottom: 3px; white-space: nowrap; vertical-align: bottom; font-weight: <?php echo $is_ret_credit ? 'bold' : 'normal'; ?>;">
                                    <?php echo $is_ret_credit ? '-' . number_format(abs($item_net), 2) : number_format($item_net, 2); ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <div style="text-align: center; letter-spacing: -0.5px; margin: 2px 0; overflow: hidden; white-space: nowrap;">--------------------------------</div>

                    <table style="width: 100%; border-collapse: collapse; font-family: 'Courier New', Consolas, monospace; font-size: 10.5pt; line-height: 1.25; margin: 2px 0;">
                        <tr>
                            <td style="text-align: left; padding: 1px 0;">Subtotal:</td>
                            <td style="text-align: right; padding: 1px 0; white-space: nowrap;"><?php echo number_format($pos_success_receipt['gross_subtotal'] ?? $pos_success_receipt['subtotal'], 2); ?></td>
                        </tr>
                        <?php if (!empty($pos_success_receipt['total_discount_savings']) && $pos_success_receipt['total_discount_savings'] > 0): ?>
                        <tr>
                            <td style="text-align: left; padding: 1px 0;">Discount:</td>
                            <td style="text-align: right; padding: 1px 0; white-space: nowrap;">-<?php echo number_format($pos_success_receipt['total_discount_savings'], 2); ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if (!empty($pos_success_receipt['total_return_credits']) && $pos_success_receipt['total_return_credits'] > 0): ?>
                        <tr>
                            <td style="text-align: left; padding: 1px 0;">Return Credit:</td>
                            <td style="text-align: right; padding: 1px 0; white-space: nowrap;">-<?php echo number_format($pos_success_receipt['total_return_credits'], 2); ?></td>
                        </tr>
                        <?php endif; ?>
                        <tr style="font-weight: bold;">
                            <td style="text-align: left; padding: 2px 0; font-size: 1.08em;">TOTAL PAID:</td>
                            <td style="text-align: right; padding: 2px 0; font-size: 1.08em; white-space: nowrap;">PHP <?php echo number_format($pos_success_receipt['grand_total'], 2); ?></td>
                        </tr>
                        <?php if (!empty($pos_success_receipt['refund_due']) && $pos_success_receipt['refund_due'] > 0): ?>
                        <tr style="font-weight: bold;">
                            <td style="text-align: left; padding: 1px 0;">Refund Due:</td>
                            <td style="text-align: right; padding: 1px 0; white-space: nowrap;">PHP <?php echo number_format($pos_success_receipt['refund_due'], 2); ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if (isset($pos_success_receipt['amount_tendered']) && $pos_success_receipt['amount_tendered'] > 0): ?>
                        <tr>
                            <td style="text-align: left; padding: 1px 0;">Tendered:</td>
                            <td style="text-align: right; padding: 1px 0; white-space: nowrap;"><?php echo number_format($pos_success_receipt['amount_tendered'], 2); ?></td>
                        </tr>
                        <tr>
                            <td style="text-align: left; padding: 1px 0;">Change:</td>
                            <td style="text-align: right; padding: 1px 0; white-space: nowrap;"><?php echo number_format($pos_success_receipt['change_amount'], 2); ?></td>
                        </tr>
                        <?php endif; ?>
                    </table>

                    <div style="text-align: center; letter-spacing: -0.5px; font-weight: bold; margin: 3px 0; overflow: hidden; white-space: nowrap;">================================</div>
                    <div style="text-align: center; font-weight: bold; padding: 2px 0; text-transform: uppercase;">
                        THANK YOU FOR YOUR BUSINESS!
                    </div>
                    <div style="text-align: center; letter-spacing: -0.5px; font-weight: bold; margin: 3px 0; overflow: hidden; white-space: nowrap;">================================</div>
                </div>

            </div>

            <!-- 3. Clean Modal Footer (Action Buttons Only) -->
            <div class="modal-footer" style="background: #ffffff; border-top: 1px solid #e2e8f0; padding: 14px 20px; display: flex; justify-content: flex-end; align-items: center; gap: 10px;">
                <button type="button" class="btn btn-default" onclick="closeReceiptModal()" style="font-weight: 700; height: 38px; padding: 6px 18px; font-size: 13px; border-radius: 6px; border-color: #cbd5e1;">
                    <i class="fa fa-plus"></i> New Sale
                </button>
                <button type="button" class="btn btn-success" onclick="printPOSReceipt()" style="font-weight: 800; height: 38px; padding: 6px 22px; font-size: 13px; background-color: #059669; border-color: #047857; border-radius: 6px; box-shadow: 0 2px 6px rgba(5,150,105,0.25);">
                    <i class="fa fa-print"></i> Print Receipt
                </button>
            </div>
            
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($pos_po_success_data): 
    $po_cashier_name = !empty($pos_po_success_data['cashier_name']) ? $pos_po_success_data['cashier_name'] : (!empty($_SESSION['supplier_user']['full_name']) ? $_SESSION['supplier_user']['full_name'] : (!empty($_SESSION['supplier_user']['username']) ? $_SESSION['supplier_user']['username'] : 'Cashier'));
    $pos_po_success_data['cashier_name'] = $po_cashier_name;
?>
<script>
window.posPOSuccessData = <?php echo json_encode($pos_po_success_data); ?>;
</script>
<div class="modal fade in" id="posPOSuccessModal" tabindex="-1" role="dialog" style="display: block; background: rgba(0,0,0,0.65); z-index: 10080;">
    <div class="modal-dialog" role="document" style="max-width: 680px;">
        <div class="modal-content" style="border-radius: 10px; overflow: hidden; box-shadow: 0 15px 40px rgba(0,0,0,0.35);">
            <div class="modal-header" style="background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%); color: #fff; padding: 14px 18px;">
                <button type="button" class="close" data-dismiss="modal" onclick="closePOSPurchaseOrderModal()" style="color: #fff; opacity: 0.9; font-size: 24px;">&times;</button>
                <h4 class="modal-title" style="font-weight: 800; font-size: 16px; display: flex; align-items: center; gap: 8px;">
                    <i class="fa fa-file-text-o"></i> Customer Purchase Order Confirmed
                </h4>
            </div>
            
            <div class="modal-body" style="padding: 16px; background: #f1f5f9; text-align: center; max-height: 70vh; overflow-y: auto;">
                <div id="posPrintPOArea" style="display: inline-block; text-align: left; background: #fff; box-shadow: 0 4px 15px rgba(0,0,0,0.12); border-radius: 4px; padding: 12px; margin: 0 auto;">
                    <div style="text-align: center;">
                        <div class="pos-company-header" style="font-size: 12pt; font-weight: bold; text-transform: uppercase; color: #000; line-height: 1.2;">SAM &amp; INRI CONSTRUCTION SUPPLY</div>
                        <div style="font-size: 10pt; margin-top: 2px; color: #000;">Tel: <?php echo !empty($pos_po_success_data['supplier_phone']) ? htmlspecialchars($pos_po_success_data['supplier_phone']) : '09612735733'; ?></div>
                        <div style="font-size: 11pt; font-weight: bold; text-transform: uppercase; margin-top: 2px; color: #000;">PURCHASE ORDER VOUCHER</div>
                        <div style="font-size: 10pt; font-weight: bold; text-transform: uppercase; color: #000;">(UNPAID)</div>
                    </div>
                    <div style="text-align: center; letter-spacing: -0.5px; font-weight: bold; margin-top: 3px; margin-bottom: 4px; overflow: hidden; white-space: nowrap;">================================</div>

                    <!-- 2. PO Metadata -->
                    <table style="width: 100%; font-family: 'Courier New', Courier, monospace; font-size: 10.5pt; line-height: 1.25; margin-bottom: 2px; border-collapse: collapse;">
                        <tr>
                            <td style="width: 28%; font-weight: bold; padding: 1px 0; vertical-align: top; white-space: nowrap;">PO NO   :</td>
                            <td style="padding: 1px 0; vertical-align: top; font-weight: bold;"><?php echo htmlspecialchars($pos_po_success_data['po_id']); ?></td>
                        </tr>
                        <tr>
                            <td style="font-weight: bold; padding: 1px 0; vertical-align: top; white-space: nowrap;">CASHIER :</td>
                            <td style="padding: 1px 0; vertical-align: top;"><?php echo htmlspecialchars($po_cashier_name); ?></td>
                        </tr>
                        <tr>
                            <td style="font-weight: bold; padding: 1px 0; vertical-align: top; white-space: nowrap;">CUSTOMER:</td>
                            <td style="padding: 1px 0; vertical-align: top;"><?php echo htmlspecialchars($pos_po_success_data['customer_name'] ?? 'Walk-in Customer'); ?></td>
                        </tr>
                        <tr>
                            <td style="font-weight: bold; padding: 1px 0; vertical-align: top; white-space: nowrap;">STATUS  :</td>
                            <td style="padding: 1px 0; vertical-align: top; font-weight: bold;">AWAITING PAYMENT</td>
                        </tr>
                        <tr>
                            <td style="font-weight: bold; padding: 1px 0; vertical-align: top; white-space: nowrap;">DATE    :</td>
                            <td style="padding: 1px 0; vertical-align: top;"><?php echo htmlspecialchars($pos_po_success_data['payment_date']); ?></td>
                        </tr>
                    </table>

                    <!-- 3. Items Table (2-Line Standard Format) -->
                    <div style="text-align: center; letter-spacing: -0.5px; margin: 2px 0; overflow: hidden; white-space: nowrap;">--------------------------------</div>
                    <table style="width: 100%; border-collapse: collapse; font-family: 'Courier New', Courier, monospace; font-size: 10.5pt; line-height: 1.2; margin: 0;">
                        <thead>
                            <tr>
                                <th style="text-align: left; padding: 1px 0; font-weight: bold; width: 68%;">ITEM DESCRIPTION</th>
                                <th style="text-align: right; padding: 1px 0; font-weight: bold; width: 32%;">AMOUNT</th>
                            </tr>
                        </thead>
                    </table>
                    <div style="text-align: center; letter-spacing: -0.5px; margin: 2px 0; overflow: hidden; white-space: nowrap;">--------------------------------</div>
                    <table style="width: 100%; border-collapse: collapse; font-family: 'Courier New', Courier, monospace; font-size: 10.5pt; line-height: 1.2; margin: 0;">
                        <tbody>
                            <?php foreach ($pos_po_success_data['items'] as $item): 
                                $item_qty = intval($item['qty']);
                                $item_unit_price = floatval($item['price']);
                                $item_gross = $item_unit_price * $item_qty;
                                $item_disc = isset($item['discount_amount']) ? floatval($item['discount_amount']) : 0.00;
                                $item_net = isset($item['line_net']) ? floatval($item['line_net']) : max(0, $item_gross - $item_disc);
                                $is_special = isset($item['item_type']) && $item['item_type'] === 'SPECIAL_ORDER';
                                $unit_label = ($item_qty > 1 ? 'pcs' : 'pc');
                            ?>
                            <tr>
                                <td colspan="2" style="text-align: left; padding-top: 3px; font-weight: bold; word-break: break-word;">
                                    <?php if ($is_special): ?>[SPECIAL ORDER] <?php endif; ?>
                                    <?php echo htmlspecialchars($item['name']); ?>
                                    <?php if (!empty($item['product_details'])): ?>
                                        <div style="font-size: 9pt; font-weight: normal; margin-top: 1px;"><?php echo htmlspecialchars($item['product_details']); ?></div>
                                    <?php elseif (!empty($item['variant_details'])): ?>
                                        <div style="font-size: 9pt; font-weight: normal; margin-top: 1px;"><?php echo htmlspecialchars($item['variant_details']); ?></div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td style="text-align: left; padding-left: 8px; padding-bottom: 3px;">
                                    <?php echo $item_qty; ?> <?php echo $unit_label; ?> @ <?php echo number_format($item_unit_price, 2); ?>
                                </td>
                                <td style="text-align: right; padding-bottom: 3px; white-space: nowrap; vertical-align: bottom;">
                                    <?php echo number_format($item_net, 2); ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <div style="text-align: center; letter-spacing: -0.5px; margin: 2px 0; overflow: hidden; white-space: nowrap;">--------------------------------</div>

                    <!-- 4. Totals -->
                    <table style="width: 100%; border-collapse: collapse; font-family: 'Courier New', Courier, monospace; font-size: 10.5pt; line-height: 1.25; margin: 2px 0;">
                        <tr>
                            <td style="text-align: left; padding: 1px 0;">Subtotal:</td>
                            <td style="text-align: right; padding: 1px 0; white-space: nowrap;"><?php echo number_format($pos_po_success_data['gross_subtotal'] ?? $pos_po_success_data['net_subtotal'] ?? 0, 2); ?></td>
                        </tr>
                        <tr>
                            <td style="text-align: left; padding: 1px 0;">Discount:</td>
                            <td style="text-align: right; padding: 1px 0; white-space: nowrap;"><?php echo number_format($pos_po_success_data['total_discount_savings'] ?? 0, 2); ?></td>
                        </tr>
                        <?php if (isset($pos_po_success_data['delivery_cost']) && $pos_po_success_data['delivery_cost'] > 0): ?>
                        <tr>
                            <td style="text-align: left; padding: 1px 0;">Delivery:</td>
                            <td style="text-align: right; padding: 1px 0; white-space: nowrap;"><?php echo number_format($pos_po_success_data['delivery_cost'], 2); ?></td>
                        </tr>
                        <?php endif; ?>
                        <tr style="font-weight: bold;">
                            <td style="text-align: left; padding: 2px 0; font-size: 1.08em;">TOTAL DUE:</td>
                            <td style="text-align: right; padding: 2px 0; font-size: 1.08em; white-space: nowrap;"><?php echo number_format($pos_po_success_data['grand_total'], 2); ?></td>
                        </tr>
                    </table>

                    <!-- 5. Footer -->
                    <div style="text-align: center; letter-spacing: -0.5px; font-weight: bold; margin: 3px 0; overflow: hidden; white-space: nowrap;">================================</div>
                    <div style="text-align: center; line-height: 1.35; padding: 4px 0; font-weight: bold; font-size: 11pt; letter-spacing: 0.5px;">
                        *** ORDER ON HOLD ***
                    </div>
                    <div style="text-align: center; letter-spacing: -0.5px; font-weight: bold; margin: 3px 0; overflow: hidden; white-space: nowrap;">================================</div>
                </div>

            </div>

            <div class="modal-footer" style="background: #f8fafc; border-top: 1px solid #e2e8f0; padding: 12px 18px;">
                <!-- Row 1: Thermal Paper Selector & Settings -->
                <div style="display: flex; justify-content: space-between; align-items: center; width: 100%; margin-bottom: 10px;">
                    <div style="display: inline-flex; align-items: center; background: #fff; border: 1.5px solid #cbd5e1; border-radius: 6px; padding: 3px 8px; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">
                        <label for="posPOModalPaperSize" style="margin: 0; font-size: 11.5px; font-weight: 700; color: #334155; margin-right: 6px; display: inline-flex; align-items: center; gap: 4px;">
                            <i class="fa fa-sliders text-primary"></i> Print Format:
                        </label>
                        <select id="posPOModalPaperSize" class="form-control input-sm" onchange="handleModalPaperSizeChange(this.value)" style="height: 30px; font-size: 12px; font-weight: 700; color: #0369a1; border: 1px solid #0284c7; border-radius: 4px; padding: 2px 8px; width: auto; background-color: #f0f9ff; cursor: pointer;" title="Select print format (Thermal 58mm / Thermal 80mm / PDF Preview)">
                            <option value="58" selected>🖨️ 58 mm POS Thermal Roll (JK-5802H)</option>
                            <option value="80">🖨️ 80 mm POS Thermal Roll</option>
                            <option value="pdf">📄 PDF Preview / Export</option>
                        </select>
                    </div>
                    <div>
                        <button type="button" class="btn btn-default" onclick="openPOSPrinterModal()" title="Printer Setup & Auto-Detection" style="height: 34px; padding: 5px 12px; font-weight: 600; border-color: #cbd5e1; border-radius: 6px;">
                            <i class="fa fa-cog text-muted"></i> Settings
                        </button>
                    </div>
                </div>

                <!-- Row 2: Action Buttons (Print, PDF, Continue) -->
                <div class="pos-success-actions" style="display: flex; align-items: center; justify-content: space-between; width: 100%; gap: 8px; flex-wrap: wrap;">
                    <div style="display: flex; gap: 8px; align-items: center;">
                        <button type="button" class="btn btn-primary" onclick="printPOSPurchaseOrder()" style="background-color: #0284c7; border-color: #0284c7; font-weight: 800; border-radius: 6px; height: 36px; padding: 6px 14px; font-size: 13px; box-shadow: 0 2px 6px rgba(2,132,199,0.3);" title="Direct print to physical thermal printer">
                            <i class="fa fa-print"></i> Print Purchase Order (Thermal)
                        </button>
                        <button type="button" class="btn btn-info" onclick="printPOSPurchaseOrder('pdf')" style="background-color: #0e7490; border-color: #0891b2; font-weight: 800; border-radius: 6px; height: 36px; padding: 6px 14px; font-size: 13px; box-shadow: 0 2px 6px rgba(14,116,144,0.3);" title="Preview thermal layout via PDF">
                            <i class="fa fa-file-pdf-o"></i> PDF Preview
                        </button>
                    </div>

                    <button type="button" class="btn btn-default" onclick="closePOSPurchaseOrderModal()" style="font-weight: 700; border-radius: 6px; height: 36px; padding: 6px 16px; font-size: 13px; border-color: #cbd5e1; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                        <i class="fa fa-arrow-right"></i> Continue / New
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- POS Hold / Park Order Confirmation Modal -->
<div class="modal fade" id="posHoldOrderModal" tabindex="-1" role="dialog" aria-labelledby="posHoldOrderModalLabel" aria-hidden="true" style="z-index: 10075;">
    <div class="modal-dialog modal-md" role="document">
        <div class="modal-content" style="border-radius: 10px; overflow: hidden; box-shadow: 0 12px 35px rgba(0,0,0,0.3);">
            <div class="modal-header" style="background: linear-gradient(135deg, #d97706 0%, #b45309 100%); color: #fff; padding: 14px 20px;">
                <button type="button" class="close" data-dismiss="modal" style="color: #fff; opacity: 0.9; font-size: 24px;">&times;</button>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <span class="label" style="background: rgba(255,255,255,0.25); font-size: 11px; font-weight: 800; padding: 3px 8px; text-transform: uppercase;">
                        <i class="fa fa-pause-circle"></i> PARK ORDER
                    </span>
                    <h4 class="modal-title" id="posHoldOrderModalLabel" style="font-weight: 800; font-size: 17px; margin: 0; color: #fff;">
                        Hold / Park Current Cart
                    </h4>
                </div>
                <div style="font-size: 12px; color: #fef3c7; margin-top: 3px;">
                    Saves cart as an unpaid Purchase Order so customer can pick more items while keeping counter moving.
                </div>
            </div>

            <div class="modal-body" style="padding: 18px 22px; background: #fff;">
                <!-- Cart Live Summary Card -->
                <div style="background: #fffbeb; border: 1.5px solid #fde68a; border-radius: 8px; padding: 12px 14px; margin-bottom: 14px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                    <div>
                        <div style="font-size: 12px; font-weight: 700; color: #92400e; text-transform: uppercase; letter-spacing: 0.5px;">Cart Summary</div>
                        <div style="font-size: 15px; font-weight: 800; color: #78350f; margin-top: 2px;">
                            <span id="holdModalItemCount">0</span> items &bull; <span id="holdModalUnitCount">0</span> units
                        </div>
                    </div>
                    <div style="text-align: right;">
                        <span style="font-size: 11px; color: #92400e; text-transform: uppercase; font-weight: 700; display: block;">Total Due</span>
                        <strong style="font-size: 20px; color: #b45309;">&#8369;<span id="holdModalTotalAmount">0.00</span></strong>
                    </div>
                </div>

                <div id="holdModalAlert" class="alert alert-danger" style="display: none; padding: 8px 12px; font-size: 12.5px; font-weight: 600; margin-bottom: 14px; border-radius: 6px;"></div>

                <div class="form-group" style="margin-bottom: 12px;">
                    <label style="font-size: 12.5px; font-weight: 700; color: #1e293b; margin-bottom: 4px;">Customer Name / Identifier:</label>
                    <input type="text" id="holdOrderCustName" class="form-control input-sm" placeholder="e.g. John Doe / Walk-in" style="font-weight: 600; height: 36px;">
                </div>

                <div class="form-group" style="margin-bottom: 6px;">
                    <label style="font-size: 12.5px; font-weight: 700; color: #1e293b; margin-bottom: 4px;">Hold Note / Reason (Optional):</label>
                    <input type="text" id="holdOrderNote" class="form-control input-sm" placeholder="e.g. Customer getting more cement / tiles" style="font-weight: 500; height: 36px;">
                </div>
            </div>

            <div class="modal-footer" style="background: #f8fafc; border-top: 1px solid #e2e8f0; padding: 12px 18px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
                <button type="button" class="btn btn-default" data-dismiss="modal" style="font-weight: 700; border-radius: 6px;">Cancel / Keep Cart</button>
                <div style="display: flex; gap: 8px;">
                    <button type="button" class="btn btn-default" onclick="submitHoldOrder(false)" id="btnHoldSilent" style="font-weight: 700; border-radius: 6px; background-color: #f1f5f9; border-color: #cbd5e1; color: #334155;" title="Hold cart and clear register without printing">
                        <i class="fa fa-pause"></i> Hold (No Print)
                    </button>
                    <button type="button" class="btn btn-warning" onclick="submitHoldOrder(true)" id="btnHoldPrint" style="font-weight: 800; border-radius: 6px; background-color: #d97706; border-color: #b45309; color: #fff; box-shadow: 0 2px 6px rgba(217,119,6,0.3);" title="Hold cart, print thermal slip with *** ORDER ON HOLD *** and clear register">
                        <i class="fa fa-print"></i> Hold &amp; Print Slip
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- POS Active Held Orders Drawer / Modal -->
<div class="modal fade" id="posHeldOrdersModal" tabindex="-1" role="dialog" aria-labelledby="posHeldOrdersModalLabel" aria-hidden="true" style="z-index: 10070;">
    <div class="modal-dialog modal-lg" role="document" style="max-width: 900px; width: 95%;">
        <div class="modal-content" style="border-radius: 10px; overflow: hidden; box-shadow: 0 15px 45px rgba(0,0,0,0.35);">
            <div class="modal-header" style="background: linear-gradient(135deg, #312e81 0%, #4338ca 100%); color: #fff; padding: 14px 20px;">
                <button type="button" class="close" data-dismiss="modal" style="color: #fff; opacity: 0.9; font-size: 24px;">&times;</button>
                <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px;">
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <span class="label" style="background: rgba(255,255,255,0.25); font-size: 11px; font-weight: 800; padding: 3px 8px; text-transform: uppercase;">
                            <i class="fa fa-list"></i> PARKED QUEUE
                        </span>
                        <h4 class="modal-title" id="posHeldOrdersModalLabel" style="font-weight: 800; font-size: 17px; margin: 0; color: #fff;">
                            Active Held Orders
                        </h4>
                    </div>
                    <button type="button" class="btn btn-xs btn-default" onclick="loadHeldOrdersList()" style="font-weight: 700; border-radius: 4px; padding: 3px 10px;">
                        <i class="fa fa-refresh"></i> Refresh
                    </button>
                </div>
            </div>

            <div class="modal-body" style="padding: 16px; background: #f8fafc; max-height: 70vh; overflow-y: auto;">
                <div id="heldOrdersListContainer">
                    <div style="text-align: center; padding: 30px 10px; color: #64748b;">
                        <i class="fa fa-spinner fa-spin fa-2x"></i>
                        <p style="margin-top: 10px; font-weight: 600;">Loading held orders...</p>
                    </div>
                </div>
            </div>

            <div class="modal-footer" style="background: #ffffff; border-top: 1px solid #e2e8f0; padding: 12px 18px; display: flex; justify-content: space-between; align-items: center;">
                <span class="text-muted" style="font-size: 12px;">
                    <i class="fa fa-info-circle text-primary"></i> Resuming an order loads its items into the POS cart where you can scan new items or proceed to checkout.
                </span>
                <button type="button" class="btn btn-default" data-dismiss="modal" style="font-weight: 700; border-radius: 6px;">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- POS Item Discount Request Modal (₱ Amount-Based) -->
<div class="modal fade" id="posDiscountModal" tabindex="-1" role="dialog" aria-labelledby="posDiscountModalLabel" aria-hidden="true" style="z-index: 10065;">
    <div class="modal-dialog modal-md" role="document">
        <div class="modal-content" style="border-radius: 10px; overflow: hidden; box-shadow: 0 12px 35px rgba(0,0,0,0.3);">
            
            <div class="modal-header" style="background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 100%); color: #fff; padding: 14px 20px;">
                <button type="button" class="close" data-dismiss="modal" style="color: #fff; opacity: 0.9; font-size: 24px;">&times;</button>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <span class="label" style="background: rgba(255,255,255,0.2); font-size: 11px; font-weight: 800; padding: 3px 8px; text-transform: uppercase; letter-spacing: 0.5px;">
                        <i class="fa fa-tag"></i> ITEM DISCOUNT
                    </span>
                    <h4 class="modal-title" id="posDiscountModalLabel" style="font-weight: 800; font-size: 17px; margin: 0; color: #fff;">
                        Request Item Discount (₱)
                    </h4>
                </div>
                <div style="font-size: 12px; color: #bfdbfe; margin-top: 3px;">
                    Enter discount in Philippine Pesos (₱) for manager review & approval
                </div>
            </div>

            <div class="modal-body" style="padding: 18px 22px; background: #fff;">
                
                <!-- Target Item Live Summary Card -->
                <div style="background: #f8fafc; border: 1.5px solid #e2e8f0; border-radius: 8px; padding: 12px 14px; margin-bottom: 14px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                    <input type="hidden" id="discModalCartItemId" value="">
                    <div>
                        <span id="discModalSpecialTag" class="label label-warning" style="display: none; font-size: 9px; padding: 1px 5px; text-transform: uppercase;">SPECIAL ORDER</span>
                        <h4 id="discModalItemName" style="margin: 2px 0 3px 0; font-size: 15px; font-weight: 800; color: #0f172a;">-</h4>
                        <div id="discModalItemMeta" style="font-size: 12px; color: #64748b; font-family: monospace;">-</div>
                    </div>
                    <div style="text-align: right;">
                        <span style="font-size: 11px; color: #64748b; text-transform: uppercase; font-weight: 700; display: block;">Gross Total</span>
                        <strong style="font-size: 18px; color: #1e3a8a;">&#8369;<span id="discModalGrossDisplay">0.00</span></strong>
                    </div>
                </div>

                <!-- Store Discount Policy Guidelines -->
                <div style="background: #f1f5f9; border: 1px solid #cbd5e1; border-radius: 6px; padding: 8px 12px; margin-bottom: 14px; font-size: 11.5px; color: #334155; display: flex; justify-content: space-between; flex-wrap: wrap; gap: 6px;">
                    <div>
                        <i class="fa fa-info-circle text-primary"></i> <strong>Normal Max:</strong> <span id="discModalNormalMaxLabel">10.00%</span> | <strong>Absolute Max:</strong> <span id="discModalAbsoluteMaxLabel">20.00%</span>
                    </div>
                    <div>
                        <strong>Approver:</strong> <span id="discModalRequiredApproverLabel" class="text-primary font-weight-bold">Supervisor or Admin</span>
                    </div>
                </div>

                <!-- Alert box for errors / validation -->
                <div id="discModalAlert" class="alert" style="display: none; padding: 8px 12px; font-size: 12.5px; font-weight: 600; margin-bottom: 14px; border-radius: 6px;"></div>

                <!-- Discount Amount Input Field (Primary Entry in ₱) -->
                <div class="form-group" style="margin-bottom: 14px;">
                    <label style="font-size: 13px; font-weight: 700; color: #1e293b; margin-bottom: 5px; display: block;">
                        Requested Discount Amount (&#8369;) <span class="text-danger">*</span>
                    </label>
                    <div class="input-group">
                        <span class="input-group-addon" style="font-weight: 800; font-size: 18px; color: #1e3a8a; background: #f8fafc;">&#8369;</span>
                        <input type="number" step="0.01" min="0" id="discModalAmountInput" class="form-control input-lg" style="height: 46px; font-size: 20px; font-weight: 900; color: #0f172a;" placeholder="0.00" oninput="calcDiscountModalPreview()">
                    </div>
                    <div style="font-size: 11.5px; color: #64748b; margin-top: 4px;">
                        Enter fixed peso discount amount. Equivalent percentage and tier are computed automatically.
                    </div>
                </div>

                <!-- Live Computed Breakdown Box -->
                <div style="background: #eff6ff; border: 2px solid #bfdbfe; border-radius: 8px; padding: 12px 16px; margin-bottom: 14px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                        <span style="font-size: 12.5px; color: #1e40af; font-weight: 700;">Equivalent Percentage:</span>
                        <strong style="font-size: 15px; color: #1e3a8a;" id="discModalPctDisplay">0.00%</strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                        <span style="font-size: 12.5px; color: #1e40af; font-weight: 700;">Total Customer Savings:</span>
                        <strong style="font-size: 15px; color: #16a34a;">-&#8369;<span id="discModalSavingsDisplay">0.00</span></strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px dashed #93c5fd; padding-top: 6px; margin-top: 4px;">
                        <span style="font-size: 14px; color: #1e3a8a; font-weight: 800;">Net Item Price:</span>
                        <strong style="font-size: 20px; color: #1d4ed8;">&#8369;<span id="discModalNetDisplay">0.00</span></strong>
                    </div>
                </div>

                <!-- Classification / Tier Banner -->
                <div id="discModalTierBox" style="padding: 8px 12px; border-radius: 6px; font-size: 12px; font-weight: 600; margin-bottom: 14px; display: none;"></div>

                <!-- Reason / Cashier Remarks -->
                <div class="form-group" style="margin-bottom: 0;">
                    <label style="font-size: 12.5px; font-weight: 700; color: #1e293b; margin-bottom: 4px; display: block;">
                        Discount Reason / Remarks (Optional):
                    </label>
                    <textarea id="discModalRemarks" class="form-control" rows="2" style="font-size: 12.5px; border-radius: 6px; resize: vertical;" placeholder="e.g. Bulk purchase discount, promotional price match, loyal customer..."></textarea>
                </div>

            </div>

            <div class="modal-footer" style="background: #f8fafc; border-top: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
                <button type="button" class="btn btn-default" data-dismiss="modal" style="font-weight: 600;">Cancel</button>
                <button type="button" id="discModalSubmitBtn" class="btn btn-primary" onclick="submitDiscountRequest()" style="font-weight: 800; padding: 8px 22px; font-size: 14px; border-radius: 6px; background-color: #2563eb; border-color: #1d4ed8;">
                    <i class="fa fa-paper-plane"></i> Submit Discount Request
                </button>
            </div>

        </div>
    </div>
</div>

<!-- POS Approval Queue Modal (for Managers / Supervisors / Admins) -->
<div class="modal fade" id="posApprovalQueueModal" tabindex="-1" role="dialog" aria-labelledby="posQueueModalLabel" aria-hidden="true" style="z-index: 10075;">
    <div class="modal-dialog modal-lg" role="document" style="max-width: 850px;">
        <div class="modal-content" style="border-radius: 10px; overflow: hidden; box-shadow: 0 12px 35px rgba(0,0,0,0.3);">
            
            <div class="modal-header" style="background: #0f172a; color: #fff; padding: 14px 20px;">
                <button type="button" class="close" data-dismiss="modal" style="color: #fff; opacity: 0.9; font-size: 24px;">&times;</button>
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <span class="label label-warning" style="background: #f59e0b; font-size: 11px; font-weight: 800; padding: 3px 8px; text-transform: uppercase;">
                            <i class="fa fa-clock-o"></i> APPROVAL QUEUE
                        </span>
                        <h4 class="modal-title" id="posQueueModalLabel" style="font-weight: 800; font-size: 17px; margin: 0; color: #fff;">
                            Active Discount Approval Requests
                        </h4>
                    </div>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <button type="button" class="btn btn-default btn-xs" onclick="refreshApprovalQueue()" style="font-weight: 700;">
                            <i class="fa fa-refresh"></i> Refresh
                        </button>
                    </div>
                </div>
            </div>

            <div class="modal-body" style="padding: 18px 20px; background: #fff; max-height: 70vh; overflow-y: auto;">
                
                <div id="queueModalAlert" class="alert" style="display: none; padding: 8px 12px; font-size: 12.5px; font-weight: 600; margin-bottom: 12px; border-radius: 6px;"></div>

                <div id="queueRequestsContainer" style="display: flex; flex-direction: column; gap: 10px;">
                    <div class="text-center text-muted" style="padding: 30px 10px;">
                        <i class="fa fa-spinner fa-spin fa-2x text-primary"></i>
                        <div style="margin-top: 6px; font-size: 13px;">Loading pending requests...</div>
                    </div>
                </div>

            </div>

            <div class="modal-footer" style="background: #f8fafc; border-top: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
                <a href="discount-approvals.php" class="btn btn-link" style="color: #2563eb; font-weight: 600; padding-left: 0;">
                    <i class="fa fa-external-link"></i> Open Full Approvals & Audit History
                </a>
                <button type="button" class="btn btn-default" data-dismiss="modal" style="font-weight: 600;">Close</button>
            </div>

        </div>
    </div>
</div>

<script>
let cart = <?php echo (!empty($active_pos_cart) && empty($pos_po_success_data)) ? json_encode(array_values($active_pos_cart), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) : '[]'; ?>;
const isPayingExistingPO = <?php echo !empty($paying_existing_po) ? 'true' : 'false'; ?>;
let currentModalGroup = null;
let currentSelectedVariant = null;
let currentSORef = '';

const storeDiscountConfig = {
    enabled: <?php echo $discount_enabled ? 'true' : 'false'; ?>,
    normalMax: <?php echo $discount_normal_max; ?>,
    specialEnabled: <?php echo $discount_special_enabled ? 'true' : 'false'; ?>,
    absoluteMax: <?php echo $discount_absolute_max; ?>,
    requireApproval: <?php echo $discount_require_admin_approval ? 'true' : 'false'; ?>,
    userId: <?php echo $pos_user_id; ?>,
    userName: <?php echo json_encode($pos_user_name); ?>,
    userRole: <?php echo json_encode($pos_user_role); ?>,
    isApprover: <?php echo $pos_is_approver ? 'true' : 'false'; ?>
};

function getRequiredApproverText(requesterRole) {
    const role = (requesterRole || '').toUpperCase();
    if (['ADMIN', 'MANAGER', 'SUPERVISOR'].includes(role)) {
        return 'Admin / Manager';
    }
    return 'Supervisor OR Admin / Manager';
}

function round2(num) {
    return Math.round((num + Number.EPSILON) * 100) / 100;
}

// ==========================================
// SPECIAL ORDER FUNCTIONS
// ==========================================

function generateSpecialOrderReference() {
    const now = new Date();
    const y = now.getFullYear();
    const m = String(now.getMonth() + 1).padStart(2, '0');
    const d = String(now.getDate()).padStart(2, '0');
    const rand = Math.floor(1000 + Math.random() * 9000);
    return `SO-${y}${m}${d}-${rand}`;
}

function openSpecialOrderModal() {
    currentSORef = generateSpecialOrderReference();
    document.getElementById('soReferencePreview').innerText = currentSORef;
    document.getElementById('soProductName').value = '';
    document.getElementById('soProductDetails').value = '';
    document.getElementById('soUnitPrice').value = '';
    document.getElementById('soQuantity').value = '1';
    document.getElementById('soTotalAmount').innerText = '0.00';
    clearSOError();
    
    $('#specialOrderModal').modal('show');
    setTimeout(() => {
        document.getElementById('soProductName').focus();
    }, 350);
}

function clearSOError() {
    const err = document.getElementById('soModalError');
    if (err) {
        err.style.display = 'none';
        err.innerText = '';
    }
}

function showSOError(msg) {
    const err = document.getElementById('soModalError');
    if (err) {
        err.innerText = msg;
        err.style.display = 'block';
    }
}

function changeSOQty(delta) {
    const input = document.getElementById('soQuantity');
    let val = parseInt(input.value) || 1;
    val += delta;
    if (val < 1) val = 1;
    input.value = val;
    updateSOTotal();
}

function updateSOTotal() {
    const priceInput = document.getElementById('soUnitPrice');
    const qtyInput = document.getElementById('soQuantity');
    
    const price = parseFloat(priceInput.value) || 0;
    let qty = parseInt(qtyInput.value) || 1;
    if (qty < 1) qty = 1;
    
    const total = Math.max(0, price * qty);
    document.getElementById('soTotalAmount').innerText = total.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function submitSpecialOrderToCart() {
    const nameInput = document.getElementById('soProductName');
    const detailsInput = document.getElementById('soProductDetails');
    const priceInput = document.getElementById('soUnitPrice');
    const qtyInput = document.getElementById('soQuantity');
    
    const name = nameInput.value.trim();
    const details = detailsInput.value.trim();
    const priceVal = priceInput.value.trim();
    const price = parseFloat(priceVal);
    const qty = parseInt(qtyInput.value);
    
    // Validations:
    if (!name) {
        showSOError('Product Name is required.');
        nameInput.focus();
        return;
    }
    
    if (name.length > 250) {
        showSOError('Product Name is too long (maximum 250 characters).');
        nameInput.focus();
        return;
    }
    
    if (priceVal === '' || isNaN(price) || price < 0) {
        showSOError('Please enter a valid Unit Price (must be 0 or greater).');
        priceInput.focus();
        return;
    }
    
    if (isNaN(qty) || qty < 1) {
        showSOError('Quantity must be at least 1.');
        qtyInput.focus();
        return;
    }
    
    const specialOrderItem = {
        id: 'so_' + Date.now() + '_' + Math.floor(Math.random() * 1000),
        item_type: 'SPECIAL_ORDER',
        special_order_reference: currentSORef || generateSpecialOrderReference(),
        name: name,
        base_name: name,
        product_details: details,
        spec_label: details || 'Special Order',
        price: price,
        qty: qty,
        stock: 999999
    };
    
    cart.push(specialOrderItem);
    renderCart();
    
    $('#specialOrderModal').modal('hide');
}

// ==========================================
// CATALOGUE VARIANT PRODUCT FUNCTIONS
// ==========================================

// Handle Clicking on a Parent Product Card
function handleGroupCardClick(element) {
    const rawData = element.getAttribute('data-group');
    if (!rawData) return;
    
    try {
        const group = JSON.parse(rawData);
        if (!group || !group.variants || group.variants.length === 0) return;
        
        if (group.total_stock <= 0) {
            alert('This product is currently out of stock.');
            return;
        }
        
        openVariantModal(group);
    } catch (e) {
        console.error('Failed to parse group data', e);
    }
}

// Open Variant Selection Modal
function openVariantModal(group) {
    currentModalGroup = group;
    
    document.getElementById('vModalTitle').innerText = group.base_name;
    document.getElementById('vModalCategory').innerText = group.ecat_name || 'General';
    document.getElementById('vModalBrand').innerText = group.brand || 'Generic';
    
    const select = document.getElementById('vModalSelect');
    const chipsList = document.getElementById('vModalChipsList');
    
    select.innerHTML = '';
    chipsList.innerHTML = '';
    
    let firstInStockVariant = null;
    
    group.variants.forEach((v, index) => {
        const isOutOfStock = (v.stock <= 0);
        if (!firstInStockVariant && !isOutOfStock) {
            firstInStockVariant = v;
        }
        
        // Populate Select Option (Full Product Title + Price + Stock)
        const opt = document.createElement('option');
        opt.value = v.id;
        opt.disabled = isOutOfStock;
        opt.innerText = `${v.name} - ₱${v.price.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})} (${isOutOfStock ? 'Out of stock' : v.stock + ' in stock'})`;
        select.appendChild(opt);
        
        // Populate Quick Variant Chip (Two-Line Stacked Chip with Product Name + Spec & Price)
        const chip = document.createElement('button');
        chip.type = 'button';
        chip.className = `pos-variant-chip ${isOutOfStock ? 'disabled' : ''}`;
        chip.id = `vChip_${v.id}`;
        chip.setAttribute('data-id', v.id);
        chip.title = isOutOfStock ? 'Out of stock' : `${v.stock} units available in stock`;
        chip.innerHTML = `
            <div style="display: flex; flex-direction: column; align-items: flex-start; text-align: left; overflow: hidden; max-width: calc(100% - 85px);">
                <div style="font-size: 13.5px; font-weight: 800; line-height: 1.25; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 100%;">
                    ${escapeHtml(v.name)}
                </div>
                <div style="display: flex; align-items: center; gap: 6px; margin-top: 3px; font-size: 12.5px; opacity: 0.9;">
                    <i class="fa ${isOutOfStock ? 'fa-ban text-danger' : 'fa-check-circle'}" style="font-size: 13px;"></i>
                    <span style="font-weight: 700;">${escapeHtml(v.spec_label)}</span>
                </div>
            </div>
            <span class="chip-price-badge" style="align-self: center; margin-left: 8px; flex-shrink: 0;">₱${v.price.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span>
        `;
        if (!isOutOfStock) {
            chip.onclick = function() { onVariantSelectChange(v.id); };
        }
        chipsList.appendChild(chip);
    });
    
    // Default to first in-stock variant or first variant
    const selectedVariant = firstInStockVariant || group.variants[0];
    document.getElementById('vModalQtyInput').value = 1;
    onVariantSelectChange(selectedVariant.id);
    
    $('#posVariantModal').modal('show');
}

// When a Variant is selected (via Select or Chip)
function onVariantSelectChange(variantId) {
    variantId = parseInt(variantId);
    if (!currentModalGroup || !currentModalGroup.variants) return;
    
    const variant = currentModalGroup.variants.find(v => v.id === variantId);
    if (!variant) return;
    
    currentSelectedVariant = variant;
    
    // Sync Select Dropdown
    document.getElementById('vModalSelect').value = variant.id;
    
    // Sync Active Chip
    document.querySelectorAll('.pos-variant-chip').forEach(c => c.classList.remove('active'));
    const activeChip = document.getElementById(`vChip_${variant.id}`);
    if (activeChip) activeChip.classList.add('active');
    
    // Update Image Preview
    document.getElementById('vModalImg').style.backgroundImage = `url('${variant.photo}')`;
    
    // Update Info
    document.getElementById('vModalSelectedName').innerText = variant.name;
    document.getElementById('vModalSku').innerText = variant.sku || ('SKU-' + variant.id);
    document.getElementById('vModalPrice').innerText = variant.price.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    
    // Update Stock Badge & Button State
    const stockBadge = document.getElementById('vModalStockBadge');
    const addBtn = document.getElementById('vModalAddBtn');
    const qtyInput = document.getElementById('vModalQtyInput');
    
    if (variant.stock <= 0) {
        stockBadge.className = 'label label-danger';
        stockBadge.innerText = 'Out of Stock';
        addBtn.disabled = true;
        qtyInput.disabled = true;
    } else {
        stockBadge.className = (variant.stock < 10) ? 'label label-warning' : 'label label-success';
        stockBadge.innerText = `${variant.stock} in stock`;
        addBtn.disabled = false;
        qtyInput.disabled = false;
        qtyInput.max = variant.stock;
        
        let currentQty = parseInt(qtyInput.value) || 1;
        if (currentQty > variant.stock) {
            qtyInput.value = variant.stock;
        } else if (currentQty < 1) {
            qtyInput.value = 1;
        }
    }
    
    // Render Specification Tags
    const specTagsContainer = document.getElementById('vModalSpecTags');
    let tagsHtml = [];
    if (variant.size) tagsHtml.push(`<span style="color: #0369a1; font-weight: 700; background: #e0f2fe; padding: 2px 6px; border-radius: 4px; font-size: 11px;">Size: ${escapeHtml(variant.size)}</span>`);
    if (variant.thickness) tagsHtml.push(`<span style="color: #047857; font-weight: 700; background: #dcfce7; padding: 2px 6px; border-radius: 4px; font-size: 11px;">Thick: ${escapeHtml(variant.thickness)}</span>`);
    if (variant.diameter) tagsHtml.push(`<span style="color: #047857; font-weight: 700; background: #dcfce7; padding: 2px 6px; border-radius: 4px; font-size: 11px;">Dia: ${escapeHtml(variant.diameter)}</span>`);
    if (variant.color) tagsHtml.push(`<span class="pos-color-badge pos-color-${variant.color.toLowerCase()}">${escapeHtml(variant.color)}</span>`);
    if (variant.material) tagsHtml.push(`<span style="color: #475569; font-weight: 700; background: #f1f5f9; padding: 2px 6px; border-radius: 4px; font-size: 11px;">${escapeHtml(variant.material)}</span>`);
    if (variant.voltage) tagsHtml.push(`<span style="color: #b45309; font-weight: 700; background: #fef3c7; padding: 2px 6px; border-radius: 4px; font-size: 11px;">${escapeHtml(variant.voltage)}</span>`);
    if (variant.power) tagsHtml.push(`<span style="color: #b45309; font-weight: 700; background: #fef3c7; padding: 2px 6px; border-radius: 4px; font-size: 11px;">${escapeHtml(variant.power)}</span>`);
    if (variant.length) tagsHtml.push(`<span style="color: #4338ca; font-weight: 700; background: #e0e7ff; padding: 2px 6px; border-radius: 4px; font-size: 11px;">Len: ${escapeHtml(variant.length)}</span>`);
    specTagsContainer.innerHTML = tagsHtml.join(' ');
    
    calcModalSubtotal();
}

// Modal Quantity Stepper
function changeModalQty(delta) {
    if (!currentSelectedVariant) return;
    const qtyInput = document.getElementById('vModalQtyInput');
    let currentVal = parseInt(qtyInput.value) || 1;
    let newVal = currentVal + delta;
    
    if (newVal < 1) newVal = 1;
    if (newVal > currentSelectedVariant.stock) {
        alert(`Cannot exceed available inventory (${currentSelectedVariant.stock} units).`);
        newVal = currentSelectedVariant.stock;
    }
    
    qtyInput.value = newVal;
    calcModalSubtotal();
}

function onModalQtyChange() {
    if (!currentSelectedVariant) return;
    const qtyInput = document.getElementById('vModalQtyInput');
    let val = parseInt(qtyInput.value) || 1;
    if (val < 1) val = 1;
    if (val > currentSelectedVariant.stock) {
        alert(`Maximum available inventory is ${currentSelectedVariant.stock} units.`);
        val = currentSelectedVariant.stock;
    }
    qtyInput.value = val;
    calcModalSubtotal();
}

function calcModalSubtotal() {
    if (!currentSelectedVariant) return;
    const qty = parseInt(document.getElementById('vModalQtyInput').value) || 1;
    const subtotal = currentSelectedVariant.price * qty;
    document.getElementById('vModalItemSubtotal').innerText = subtotal.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
}

// Submit Variant from Modal to Cart
function submitVariantToCart() {
    if (!currentSelectedVariant) return;
    if (currentSelectedVariant.stock <= 0) {
        alert('Selected variant is out of stock.');
        return;
    }
    
    const qty = parseInt(document.getElementById('vModalQtyInput').value) || 1;
    
    addVariantToCart(currentSelectedVariant, qty);
    $('#posVariantModal').modal('hide');
}

// Add Variant to Cart with Specific Qty
function addVariantToCart(variant, qty) {
    const existingIndex = cart.findIndex(item => (item.id === variant.id || String(item.id) === String(variant.id)) && item.item_type !== 'SPECIAL_ORDER');
    if (existingIndex > -1) {
        const potentialQty = cart[existingIndex].qty + qty;
        if (potentialQty <= variant.stock) {
            cart[existingIndex].qty = potentialQty;
        } else {
            alert(`Cannot add more than available inventory (${variant.stock} units). Currently in cart: ${cart[existingIndex].qty}`);
            cart[existingIndex].qty = variant.stock;
        }
    } else {
        if (qty > variant.stock) {
            qty = variant.stock;
        }
        cart.push({
            id: variant.id,
            item_type: 'STANDARD',
            sku: variant.sku,
            name: variant.name,
            base_name: variant.base_name || variant.name,
            spec_label: variant.spec_label,
            size: variant.size || '',
            thickness: variant.thickness || '',
            diameter: variant.diameter || '',
            color: variant.color || '',
            material: variant.material || '',
            price: variant.price,
            stock: variant.stock,
            qty: qty
        });
    }
    renderCart();
}

// ==========================================
// CART & REGISTER FUNCTIONS
// ==========================================

function updateCartQty(itemId, newQty) {
    const item = cart.find(i => i.id === itemId || String(i.id) === String(itemId));
    if (item) {
        newQty = parseInt(newQty);
        if (newQty <= 0) {
            removeFromCart(itemId);
            return;
        }
        if (item.item_type === 'RETURN_CREDIT') {
            const maxRet = item.max_return_qty || 999;
            if (newQty > maxRet) {
                alert('Maximum returnable quantity for this item is ' + maxRet);
                item.qty = maxRet;
            } else {
                item.qty = newQty;
            }
        } else if (item.item_type !== 'SPECIAL_ORDER' && newQty > item.stock) {
            alert('Maximum available inventory is ' + item.stock);
            item.qty = item.stock;
        } else {
            item.qty = newQty;
        }

        // If item has an active discount, update amount proportionally based on discount_percent
        if (item.discount_percent && item.discount_percent > 0) {
            item.discount_amount = round2((item.discount_percent / 100) * (item.price * item.qty));
        }

        renderCart();
    }
}

function removeFromCart(itemId) {
    const item = cart.find(i => i.id === itemId || String(i.id) === String(itemId));
    if (item && item.discount_request_id && (item.discount_status === 'PENDING' || item.discount_status === 'ESCALATED')) {
        cancelItemDiscount(itemId);
    }
    cart = cart.filter(i => i.id !== itemId && String(i.id) !== String(itemId));
    renderCart();
}

function clearCart() {
    if (cart.length > 0 && confirm('Clear all items from the current sale?')) {
        cart.forEach(item => {
            if (item.discount_request_id && (item.discount_status === 'PENDING' || item.discount_status === 'ESCALATED')) {
                cancelItemDiscount(item.id);
            }
        });
        cart = [];
        renderCart();
        const fd = new FormData();
        fd.append('pos_action', 'clear_session_cart');
        fetch('pos.php', { method: 'POST', body: fd }).catch(() => {});
    }
}

function renderCart() {
    const tbody = document.getElementById('posCartTableBody');
    const completeBtn = document.getElementById('posCompleteBtn');
    const proceedBtn = document.getElementById('posProceedCheckoutBtn');
    const cartItemsInput = document.getElementById('cartItemsInput');

    if (cart.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="4" class="text-center text-muted" style="padding: 25px 10px;">
                    <i class="fa fa-shopping-basket fa-2x" style="color: #cbd5e1;"></i>
                    <div style="margin-top: 5px; font-size: 12px;">Cart is empty. Click products or "+ Special Order" to add.</div>
                </td>
            </tr>`;
        if (completeBtn) completeBtn.disabled = true;
        if (proceedBtn) {
            proceedBtn.classList.add('disabled');
            proceedBtn.style.pointerEvents = 'none';
            proceedBtn.style.opacity = '0.65';
        }
        cartItemsInput.value = '[]';
    } else {
        let html = '';
        cart.forEach(item => {
            const isSpecialOrder = (item.item_type === 'SPECIAL_ORDER');
            const isReturnCredit = (item.item_type === 'RETURN_CREDIT');
            const grossLineTotal = item.price * item.qty;
            const itemIdStr = typeof item.id === 'string' ? `'${item.id}'` : item.id;
            
            let itemBadge = '';
            let detailsHtml = '';
            
            if (isReturnCredit) {
                itemBadge = `<span class="label label-danger" style="background-color: #dc2626; font-size: 10px; font-weight: 800; padding: 2px 6px; text-transform: uppercase; margin-bottom: 2px; display: inline-block;"><i class="fa fa-undo"></i> RETURN CREDIT</span>`;
                if (item.product_details) {
                    detailsHtml = `<div style="font-size: 11px; color: #991b1b; background: #fee2e2; border: 1px solid #fecaca; padding: 3px 6px; border-radius: 4px; margin-top: 3px; line-height: 1.3;">${escapeHtml(item.product_details)}</div>`;
                }
            } else if (isSpecialOrder) {
                itemBadge = `<span class="label label-warning" style="background-color: #d97706; font-size: 10px; font-weight: 800; padding: 2px 6px; text-transform: uppercase; margin-bottom: 2px; display: inline-block;"><i class="fa fa-star"></i> SPECIAL ORDER</span>`;
                if (item.product_details) {
                    detailsHtml = `<div style="font-size: 11px; color: #475569; background: #fef3c7; border: 1px solid #fde68a; padding: 3px 6px; border-radius: 4px; margin-top: 3px; line-height: 1.3;">${escapeHtml(item.product_details)}</div>`;
                }
            } else {
                let specsArr = [];
                if (item.spec_label && item.spec_label !== 'Standard') {
                    specsArr.push(`<span style="color: #0369a1; font-weight: 700; font-size: 11px;">${escapeHtml(item.spec_label)}</span>`);
                } else {
                    if (item.size) specsArr.push(`<span style="color: #0369a1; font-weight: 700; font-size: 11px;">${escapeHtml(item.size)}</span>`);
                    if (item.thickness) specsArr.push(`<span style="color: #047857; font-weight: 700; font-size: 11px;">${escapeHtml(item.thickness)}</span>`);
                    if (item.diameter) specsArr.push(`<span style="color: #047857; font-weight: 700; font-size: 11px;">${escapeHtml(item.diameter)}</span>`);
                    if (item.color) specsArr.push(`<span class="pos-color-badge pos-color-${item.color.toLowerCase()}">${escapeHtml(item.color)}</span>`);
                }
                detailsHtml = specsArr.length > 0 ? `<div style="margin-top: 2px; display: flex; flex-wrap: wrap; gap: 4px;">${specsArr.join(' ')}</div>` : '';
            }

            const refOrSku = isSpecialOrder 
                ? (item.special_order_reference ? `Ref: ${escapeHtml(item.special_order_reference)} | ` : '')
                : (item.sku ? `SKU: ${escapeHtml(item.sku)} | ` : '');

            // Discount Badge & Actions per item
            let discountSectionHtml = '';
            let linePriceHtml = '';

            const dStatus = item.discount_status;
            const dAmt = item.discount_amount || 0;
            const dPct = item.discount_percent || 0;

            if (isReturnCredit) {
                linePriceHtml = `<span style="font-weight: 800; font-size: 15px; color: #dc2626;">-&#8369;${Math.abs(grossLineTotal).toFixed(2)}</span>`;
            } else if (dStatus === 'PENDING') {
                discountSectionHtml = `
                    <div style="margin-top: 4px;">
                        <span class="label label-warning" style="background: #f59e0b; font-size: 10px; font-weight: 700; padding: 2px 6px;">
                            <i class="fa fa-clock-o"></i> ₱${dAmt.toFixed(2)} (${dPct.toFixed(1)}%) Pending Approval
                        </span>
                        <a href="javascript:void(0)" onclick="cancelItemDiscount(${itemIdStr})" class="text-danger" style="font-weight: bold; font-size: 13px; margin-left: 4px; text-decoration: none;" title="Cancel discount request">&times;</a>
                    </div>
                `;
                linePriceHtml = `
                    <span style="font-weight: 800; font-size: 15px; color: #d97706;">&#8369;${grossLineTotal.toFixed(2)}</span>
                    <div style="font-size: 10px; color: #b45309; font-weight: bold;">(Pending -₱${dAmt.toFixed(2)})</div>
                `;
            } else if (dStatus === 'ESCALATED') {
                discountSectionHtml = `
                    <div style="margin-top: 4px;">
                        <span class="label label-warning" style="background: #d97706; font-size: 10px; font-weight: 800; padding: 2px 6px;">
                            <i class="fa fa-shield"></i> Special ₱${dAmt.toFixed(2)} (${dPct.toFixed(1)}%) Escalated
                        </span>
                        <a href="javascript:void(0)" onclick="cancelItemDiscount(${itemIdStr})" class="text-danger" style="font-weight: bold; font-size: 13px; margin-left: 4px; text-decoration: none;" title="Cancel discount request">&times;</a>
                    </div>
                `;
                linePriceHtml = `
                    <span style="font-weight: 800; font-size: 15px; color: #d97706;">&#8369;${grossLineTotal.toFixed(2)}</span>
                    <div style="font-size: 10px; color: #b45309; font-weight: bold;">(Escalated -₱${dAmt.toFixed(2)})</div>
                `;
            } else if (dStatus === 'APPROVED' || dStatus === 'APPROVED_MODIFIED') {
                const netLine = Math.max(0, grossLineTotal - dAmt);
                discountSectionHtml = `
                    <div style="margin-top: 4px;">
                        <span class="label label-success" style="background: #10b981; font-size: 10px; font-weight: 700; padding: 2px 6px;">
                            <i class="fa fa-check-circle"></i> Discount -₱${dAmt.toFixed(2)} (${dPct.toFixed(1)}%) Approved
                        </span>
                        <a href="javascript:void(0)" onclick="cancelItemDiscount(${itemIdStr})" class="text-danger" style="font-weight: bold; font-size: 13px; margin-left: 4px; text-decoration: none;" title="Remove discount">&times;</a>
                    </div>
                `;
                linePriceHtml = `
                    <span style="text-decoration: line-through; color: #94a3b8; font-size: 11px; display: block;">&#8369;${grossLineTotal.toFixed(2)}</span>
                    <span style="font-weight: 800; font-size: 15px; color: #047857;">&#8369;${netLine.toFixed(2)}</span>
                `;
            } else if (dStatus === 'REJECTED') {
                discountSectionHtml = `
                    <div style="margin-top: 4px;">
                        <span class="label label-danger" style="background: #ef4444; font-size: 10px; font-weight: 700; padding: 2px 6px;">
                            <i class="fa fa-times-circle"></i> Discount Rejected
                        </span>
                        <a href="javascript:void(0)" onclick="cancelItemDiscount(${itemIdStr})" class="text-muted" style="font-weight: bold; font-size: 13px; margin-left: 4px; text-decoration: none;" title="Dismiss">&times;</a>
                    </div>
                `;
                linePriceHtml = `<span style="font-weight: 800; font-size: 15px; color: ${isSpecialOrder ? '#b45309' : '#1e40af'};">&#8369;${grossLineTotal.toFixed(2)}</span>`;
            } else {
                // No active discount
                if (storeDiscountConfig.enabled) {
                    discountSectionHtml = `
                        <div style="margin-top: 4px;">
                            <button type="button" class="btn btn-default btn-xs" onclick="openDiscountModal(${itemIdStr})" style="font-size: 11px; font-weight: 700; color: #0284c7; background: #f0f9ff; border: 1px solid #bae6fd; padding: 1px 7px; border-radius: 3px;">
                                <i class="fa fa-tag"></i> ₱ Discount
                            </button>
                        </div>
                    `;
                }
                linePriceHtml = `<span style="font-weight: 800; font-size: 15px; color: ${isSpecialOrder ? '#b45309' : '#1e40af'};">&#8369;${grossLineTotal.toFixed(2)}</span>`;
            }

            html += `
                <tr ${isReturnCredit ? 'style="background-color: #fef2f2;"' : (isSpecialOrder ? 'style="background-color: #fffbeb;"' : '')}>
                    <td style="padding: 8px 4px;">
                        ${itemBadge}
                        <strong style="color: #0f172a; font-size: 13px; display: block; line-height: 1.3;">${escapeHtml(item.base_name || item.name)}</strong>
                        ${detailsHtml}
                        <div style="font-size: 11px; color: #64748b; font-family: monospace; margin-top: 2px;">
                            ${refOrSku}&#8369;${Math.abs(item.price).toFixed(2)} each
                        </div>
                        ${discountSectionHtml}
                    </td>
                    <td style="padding: 8px 4px; text-align: center;">
                        <div class="btn-group" style="display: inline-flex; align-items: center;">
                            <button type="button" class="btn btn-default pos-qty-btn" onclick="updateCartQty(${itemIdStr}, ${item.qty - 1})">-</button>
                            <span style="display: inline-block; width: 28px; text-align: center; font-weight: 800; font-size: 14px; color: #0f172a;">${item.qty}</span>
                            <button type="button" class="btn btn-default pos-qty-btn" onclick="updateCartQty(${itemIdStr}, ${item.qty + 1})">+</button>
                        </div>
                    </td>
                    <td style="padding: 8px 4px; text-align: right;">
                        ${linePriceHtml}
                    </td>
                    <td style="padding: 8px 2px; text-align: center;">
                        <button type="button" class="btn btn-link text-danger" onclick="removeFromCart(${itemIdStr})" style="padding: 2px 4px; font-size: 16px;" title="Remove item"><i class="fa fa-times-circle"></i></button>
                    </td>
                </tr>`;
        });
        tbody.innerHTML = html;
        if (completeBtn) completeBtn.disabled = false;
        if (proceedBtn) {
            proceedBtn.classList.remove('disabled');
            proceedBtn.style.pointerEvents = 'auto';
            proceedBtn.style.opacity = '1';
        }
        cartItemsInput.value = JSON.stringify(cart);
    }

    updatePOSCalculations();
}

function updatePOSCalculations() {
    let grossSubtotal = 0;
    let totalDiscountSavings = 0;
    let totalReturnCredits = 0;
    let hasPendingDiscounts = false;

    cart.forEach(item => {
        if (item.item_type === 'RETURN_CREDIT') {
            totalReturnCredits += Math.abs(item.price) * item.qty;
            return;
        }
        const itemGross = item.price * item.qty;
        grossSubtotal += itemGross;

        if (item.discount_status === 'APPROVED' || item.discount_status === 'APPROVED_MODIFIED') {
            const discAmt = item.discount_amount !== undefined ? item.discount_amount : (itemGross * (item.discount_percent / 100));
            totalDiscountSavings += discAmt;
        } else if (item.discount_status === 'PENDING' || item.discount_status === 'ESCALATED') {
            hasPendingDiscounts = true;
        }
    });

    const isLocationActive = document.getElementById('locationRadio').checked;
    const deliveryFeeRow = document.getElementById('deliveryFeeRow');
    const posDeliveryType = document.getElementById('posDeliveryType');
    const posFulfillmentBadge = document.getElementById('posFulfillmentBadge');
    const posDeliveryFeeDisplay = document.getElementById('posDeliveryFeeDisplay');
    const locationSelect = document.getElementById('locationBrgySelect');
    
    let deliveryCost = 0;

    if (isLocationActive) {
        deliveryCost = parseFloat(document.getElementById('posDeliveryCost').value) || 0;
        posDeliveryType.value = 'delivery';
        
        const selectedOption = locationSelect.options[locationSelect.selectedIndex];
        const brgyName = (selectedOption && selectedOption.dataset.name) ? selectedOption.dataset.name : '';
        
        if (brgyName) {
            posFulfillmentBadge.innerHTML = `<i class="fa fa-truck text-info"></i> Delivery: Brgy. ${escapeHtml(brgyName)}`;
            posFulfillmentBadge.style.color = '#0284c7';
        } else {
            posFulfillmentBadge.innerHTML = `<i class="fa fa-truck text-info"></i> Delivery (Select Brgy)`;
            posFulfillmentBadge.style.color = '#0284c7';
        }
        
        posDeliveryFeeDisplay.innerText = deliveryCost.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        deliveryFeeRow.style.display = 'flex';
    } else {
        deliveryCost = 0;
        document.getElementById('posDeliveryCost').value = '0.00';
        posDeliveryType.value = 'pickup';
        posFulfillmentBadge.innerHTML = `<i class="fa fa-shopping-bag"></i> Store Pickup (₱0)`;
        posFulfillmentBadge.style.color = '#059669';
        deliveryFeeRow.style.display = 'none';
    }

    const netSubtotal = Math.max(0, grossSubtotal - totalDiscountSavings);
    const netPayable = (netSubtotal + deliveryCost) - totalReturnCredits;
    const grandTotal = Math.max(0, netPayable);
    const refundDue = (netPayable < 0) ? Math.abs(netPayable) : 0;

    document.getElementById('posSubtotal').innerText = grossSubtotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    
    const discRow = document.getElementById('posDiscountSavingsRow');
    const discDisplay = document.getElementById('posDiscountSavingsDisplay');
    if (totalDiscountSavings > 0) {
        discDisplay.innerText = totalDiscountSavings.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        discRow.style.display = 'flex';
    } else {
        discRow.style.display = 'none';
    }

    const retRow = document.getElementById('posReturnCreditRow');
    const retDisplay = document.getElementById('posReturnCreditDisplay');
    if (retRow && retDisplay) {
        if (totalReturnCredits > 0) {
            retDisplay.innerText = totalReturnCredits.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            retRow.style.display = 'flex';
        } else {
            retRow.style.display = 'none';
        }
    }

    // Down-Trade Alert Banner & Refund Due Breakdown Row
    const downTradeAlert = document.getElementById('posDownTradeAlert');
    const downTradeBadgeAmt = document.getElementById('posDownTradeBadgeAmt');
    const downTradeFormula = document.getElementById('posDownTradeFormula');
    const refundDueRow = document.getElementById('posRefundDueRow');
    const refundDueDisplay = document.getElementById('posRefundDueDisplay');
    const grandTotalSubtext = document.getElementById('posGrandTotalSubtext');

    if (refundDue > 0) {
        if (downTradeAlert) downTradeAlert.style.display = 'block';
        if (downTradeBadgeAmt) downTradeBadgeAmt.innerText = `Refund Due: ₱${refundDue.toFixed(2)}`;
        if (downTradeFormula) {
            downTradeFormula.innerHTML = `Return credit (₱${totalReturnCredits.toFixed(2)}) exceeds new items (₱${(netSubtotal + deliveryCost).toFixed(2)}). <strong>Pay out ₱${refundDue.toFixed(2)}</strong> from cash drawer to customer.`;
        }
        if (refundDueRow) {
            refundDueRow.style.display = 'flex';
            if (refundDueDisplay) refundDueDisplay.innerText = refundDue.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }
        if (grandTotalSubtext) grandTotalSubtext.innerText = '(No Payment Required)';
    } else {
        if (downTradeAlert) downTradeAlert.style.display = 'none';
        if (refundDueRow) refundDueRow.style.display = 'none';
        if (grandTotalSubtext) grandTotalSubtext.innerText = '';
    }

    document.getElementById('posGrandTotal').innerText = grandTotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    // Update Change
    const tendered = parseFloat(document.getElementById('posAmountTendered').value) || 0;
    const change = (grandTotal === 0 && refundDue > 0) ? (tendered + refundDue) : Math.max(0, tendered - grandTotal);
    document.getElementById('posChangeAmount').innerText = change.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    // Payment Locking Logic
    const lockNotice = document.getElementById('posPaymentLockedNotice');
    const completeBtn = document.getElementById('posCompleteBtn');
    const proceedBtn = document.getElementById('posProceedCheckoutBtn');

    if (hasPendingDiscounts) {
        if (lockNotice) lockNotice.style.display = 'block';
        if (completeBtn) {
            completeBtn.disabled = true;
            completeBtn.className = 'btn btn-warning btn-block btn-lg';
            completeBtn.innerHTML = '<i class="fa fa-lock"></i> Payment Locked (Discounts Pending Approval)';
        }
        if (proceedBtn) {
            proceedBtn.classList.add('disabled');
            proceedBtn.style.pointerEvents = 'none';
            proceedBtn.style.opacity = '0.65';
            proceedBtn.className = 'btn btn-warning btn-block btn-lg';
            proceedBtn.innerHTML = '<i class="fa fa-lock"></i> Checkout Locked (Discounts Pending Approval)';
        }
    } else {
        if (lockNotice) lockNotice.style.display = 'none';
        if (completeBtn) {
            completeBtn.disabled = (cart.length === 0);
            completeBtn.className = 'btn btn-success btn-block btn-lg';
            if (refundDue > 0 && grandTotal === 0) {
                completeBtn.innerHTML = `<i class="fa fa-hand-holding-usd"></i> Complete Exchange &amp; Pay Out ₱${refundDue.toFixed(2)}`;
            } else {
                completeBtn.innerHTML = '<i class="fa fa-check-circle"></i> Complete Sale &amp; Print Receipt';
            }
        }
        if (proceedBtn) {
            if (cart.length === 0) {
                proceedBtn.classList.add('disabled');
                proceedBtn.style.pointerEvents = 'none';
                proceedBtn.style.opacity = '0.65';
            } else {
                proceedBtn.classList.remove('disabled');
                proceedBtn.style.pointerEvents = 'auto';
                proceedBtn.style.opacity = '1';
            }
            proceedBtn.className = 'btn btn-primary btn-block btn-lg';
            proceedBtn.innerHTML = '<i class="fa fa-shopping-cart"></i> Proceed to Checkout';
        }
    }
}

function handleProceedToCheckout(e) {
    if (e) e.preventDefault();

    if (!cart || cart.length === 0) {
        alert('Your POS cart is empty. Please select products to add before proceeding to checkout.');
        return false;
    }

    let hasPending = false;
    cart.forEach(item => {
        if (item.discount_status === 'PENDING' || item.discount_status === 'ESCALATED') {
            hasPending = true;
        }
    });

    if (hasPending) {
        alert('Payment locked: One or more item discount requests are pending approval. Please complete or cancel pending discount requests before proceeding to checkout.');
        return false;
    }

    const typeEl = document.querySelector('input[name="customer_type"]:checked');
    const type = typeEl ? typeEl.value : 'walkin';
    if (type === 'registered') {
        const regCust = document.querySelector('select[name="registered_cust_id"]').value;
        if (!regCust) {
            alert('Please select a registered customer or switch to Walk-in.');
            return false;
        }
    }
    const isLocationActive = document.getElementById('locationRadio').checked;
    if (isLocationActive) {
        const brgy = document.getElementById('locationBrgySelect').value;
        if (!brgy) {
            alert('Please select a Barangay location or uncheck the Location option.');
            return false;
        }
    }

    // Prepare form data
    const form = document.getElementById('posCheckoutForm');
    const formData = new FormData(form);
    formData.set('pos_action', 'sync_checkout_cart');
    formData.set('cart_items', JSON.stringify(cart));

    const proceedBtn = document.getElementById('posProceedCheckoutBtn');
    if (proceedBtn) {
        proceedBtn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Preparing Checkout...';
        proceedBtn.style.pointerEvents = 'none';
    }

    fetch('pos.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            window.location.href = 'checkout.php';
        } else {
            alert(data.message || 'Error preparing checkout cart. Please try again.');
            if (proceedBtn) {
                proceedBtn.innerHTML = '<i class="fa fa-shopping-cart"></i> Proceed to Checkout';
                proceedBtn.style.pointerEvents = 'auto';
            }
        }
    })
    .catch(err => {
        console.error(err);
        window.location.href = 'checkout.php';
    });

    return false;
}

// Open Discount Request Modal
function openDiscountModal(itemId) {
    const item = cart.find(i => i.id === itemId || String(i.id) === String(itemId));
    if (!item) return;

    document.getElementById('discModalCartItemId').value = item.id;
    document.getElementById('discModalItemName').innerText = item.base_name || item.name;
    
    const isSpecial = (item.item_type === 'SPECIAL_ORDER');
    document.getElementById('discModalSpecialTag').style.display = isSpecial ? 'inline-block' : 'none';
    
    const metaParts = [`Qty: ${item.qty}`, `Unit: ₱${item.price.toFixed(2)}`];
    if (isSpecial && item.special_order_reference) {
        metaParts.push(`Ref: ${item.special_order_reference}`);
    } else if (item.sku) {
        metaParts.push(`SKU: ${item.sku}`);
    }
    document.getElementById('discModalItemMeta').innerText = metaParts.join(' | ');

    const gross = item.price * item.qty;
    document.getElementById('discModalGrossDisplay').innerText = gross.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    // Policy labels
    const normMaxAmt = gross * (storeDiscountConfig.normalMax / 100);
    const absMaxAmt = gross * (storeDiscountConfig.absoluteMax / 100);
    document.getElementById('discModalNormalMaxLabel').innerText = `${storeDiscountConfig.normalMax.toFixed(2)}% (₱${normMaxAmt.toFixed(2)})`;
    document.getElementById('discModalAbsoluteMaxLabel').innerText = `${storeDiscountConfig.absoluteMax.toFixed(2)}% (₱${absMaxAmt.toFixed(2)})`;
    document.getElementById('discModalRequiredApproverLabel').innerText = getRequiredApproverText(storeDiscountConfig.userRole);

    // Amount input prefill
    const input = document.getElementById('discModalAmountInput');
    if (item.discount_amount && item.discount_amount > 0) {
        input.value = item.discount_amount.toFixed(2);
    } else {
        input.value = '';
    }

    document.getElementById('discModalRemarks').value = '';
    clearDiscModalAlert();
    calcDiscountModalPreview();

    $('#posDiscountModal').modal('show');
    setTimeout(() => {
        input.focus();
        input.select();
    }, 350);
}

function clearDiscModalAlert() {
    const alertBox = document.getElementById('discModalAlert');
    if (alertBox) {
        alertBox.style.display = 'none';
        alertBox.className = 'alert';
        alertBox.innerText = '';
    }
}

function showDiscModalAlert(type, msg) {
    const alertBox = document.getElementById('discModalAlert');
    if (alertBox) {
        alertBox.className = `alert alert-${type}`;
        alertBox.innerHTML = msg;
        alertBox.style.display = 'block';
    }
}

function calcDiscountModalPreview() {
    const itemId = document.getElementById('discModalCartItemId').value;
    const item = cart.find(i => i.id === itemId || String(i.id) === String(itemId));
    if (!item) return;

    const gross = item.price * item.qty;
    const inputVal = document.getElementById('discModalAmountInput').value.trim();
    const amt = parseFloat(inputVal) || 0;
    const pct = (gross > 0) ? (amt / gross) * 100 : 0;
    const net = Math.max(0, gross - amt);

    document.getElementById('discModalPctDisplay').innerText = pct.toFixed(2) + '%';
    document.getElementById('discModalSavingsDisplay').innerText = amt.toFixed(2);
    document.getElementById('discModalNetDisplay').innerText = net.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    const tierBox = document.getElementById('discModalTierBox');
    const submitBtn = document.getElementById('discModalSubmitBtn');

    if (!inputVal || amt <= 0) {
        tierBox.style.display = 'none';
        submitBtn.disabled = true;
        submitBtn.innerHTML = `<i class="fa fa-paper-plane"></i> Submit Discount Request`;
        return;
    }

    const normalMaxPct = storeDiscountConfig.normalMax;
    const absoluteMaxPct = storeDiscountConfig.absoluteMax;
    const normalMaxAmt = round2(gross * (normalMaxPct / 100));
    const absoluteMaxAmt = round2(gross * (absoluteMaxPct / 100));

    if (amt >= gross) {
        tierBox.style.display = 'block';
        tierBox.style.background = '#fef2f2';
        tierBox.style.border = '1px solid #fecaca';
        tierBox.style.color = '#b91c1c';
        tierBox.innerHTML = `<i class="fa fa-ban"></i> <strong>PROHIBITED:</strong> Discount (₱${amt.toFixed(2)}) cannot equal or exceed item gross total (₱${gross.toFixed(2)}).`;
        submitBtn.disabled = true;
    } else if (pct > absoluteMaxPct + 0.0001 || amt > absoluteMaxAmt + 0.005) {
        tierBox.style.display = 'block';
        tierBox.style.background = '#fef2f2';
        tierBox.style.border = '1px solid #fecaca';
        tierBox.style.color = '#b91c1c';
        tierBox.innerHTML = `<i class="fa fa-ban"></i> <strong>DISCOUNT PROHIBITED:</strong> ₱${amt.toFixed(2)} (${pct.toFixed(2)}%) exceeds the absolute maximum allowed (${absoluteMaxPct.toFixed(2)}% / ₱${absoluteMaxAmt.toFixed(2)}).`;
        submitBtn.disabled = true;
    } else if (pct > normalMaxPct + 0.0001 || amt > normalMaxAmt + 0.005) {
        if (!storeDiscountConfig.specialEnabled) {
            tierBox.style.display = 'block';
            tierBox.style.background = '#fef2f2';
            tierBox.style.border = '1px solid #fecaca';
            tierBox.style.color = '#b91c1c';
            tierBox.innerHTML = `<i class="fa fa-ban"></i> <strong>SPECIAL DISCOUNT DISABLED:</strong> Discounts exceeding ${normalMaxPct.toFixed(2)}% (₱${normalMaxAmt.toFixed(2)}) are disabled.`;
            submitBtn.disabled = true;
        } else {
            tierBox.style.display = 'block';
            tierBox.style.background = '#fffbeb';
            tierBox.style.border = '1px solid #fde68a';
            tierBox.style.color = '#b45309';
            tierBox.innerHTML = `<i class="fa fa-shield"></i> <strong>SPECIAL DISCOUNT (${pct.toFixed(2)}%):</strong> Exceeds normal max (₱${normalMaxAmt.toFixed(2)}). Requires escalated approval from ${getRequiredApproverText(storeDiscountConfig.userRole)}.`;
            submitBtn.disabled = false;
            submitBtn.innerHTML = `<i class="fa fa-shield"></i> Submit Special Request (₱${amt.toFixed(2)})`;
        }
    } else {
        tierBox.style.display = 'block';
        tierBox.style.background = '#f0fdf4';
        tierBox.style.border = '1px solid #bbf7d0';
        tierBox.style.color = '#15803d';
        tierBox.innerHTML = `<i class="fa fa-check-circle"></i> <strong>NORMAL DISCOUNT (${pct.toFixed(2)}%):</strong> Within normal limit (≤ ${normalMaxPct.toFixed(2)}%). Request will be submitted for approval.`;
        submitBtn.disabled = false;
        submitBtn.innerHTML = `<i class="fa fa-paper-plane"></i> Submit Request (₱${amt.toFixed(2)})`;
    }
}

function submitDiscountRequest() {
    const itemId = document.getElementById('discModalCartItemId').value;
    const item = cart.find(i => i.id === itemId || String(i.id) === String(itemId));
    if (!item) return;

    const amtInput = document.getElementById('discModalAmountInput').value.trim();
    const amt = parseFloat(amtInput);
    const remarks = document.getElementById('discModalRemarks').value.trim();

    if (isNaN(amt) || amt <= 0) {
        showDiscModalAlert('danger', 'Please enter a valid discount amount greater than ₱0.00.');
        return;
    }

    const gross = item.price * item.qty;
    if (amt >= gross) {
        showDiscModalAlert('danger', 'Discount amount cannot equal or exceed item gross total.');
        return;
    }

    const submitBtn = document.getElementById('discModalSubmitBtn');
    submitBtn.disabled = true;
    submitBtn.innerHTML = `<i class="fa fa-spinner fa-spin"></i> Submitting...`;

    const formData = new FormData();
    formData.append('product_id', (item.item_type === 'SPECIAL_ORDER') ? 0 : item.id);
    formData.append('item_type', item.item_type || 'STANDARD');
    formData.append('name', item.base_name || item.name);
    formData.append('sku', item.sku || '');
    formData.append('product_details', item.product_details || item.spec_label || '');
    formData.append('special_order_reference', item.special_order_reference || '');
    formData.append('quantity', item.qty);
    formData.append('unit_price', item.price);
    formData.append('requested_discount_amount', amt);
    formData.append('cashier_remarks', remarks);

    fetch('pos-discount-api.php?action=request_discount', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        submitBtn.disabled = false;
        submitBtn.innerHTML = `<i class="fa fa-paper-plane"></i> Submit Discount Request`;

        if (data.status === 'success') {
            item.discount_request_id = data.request_id;
            item.discount_amount = parseFloat(data.discount_amount);
            item.discount_percent = parseFloat(data.requested_discount_percent);
            item.discount_status = data.discount_status; // 'PENDING', 'ESCALATED', or 'APPROVED'
            item.approval_level = data.approval_level;

            $('#posDiscountModal').modal('hide');
            renderCart();

            if (storeDiscountConfig.isApprover) {
                pollPendingQueueBadge();
            }
        } else {
            showDiscModalAlert('danger', `<i class="fa fa-exclamation-triangle"></i> ${escapeHtml(data.message || 'Failed to submit discount request.')}`);
        }
    })
    .catch(err => {
        submitBtn.disabled = false;
        submitBtn.innerHTML = `<i class="fa fa-paper-plane"></i> Submit Discount Request`;
        console.error('Discount request failed:', err);
        showDiscModalAlert('danger', 'Network or server error while submitting discount request.');
    });
}

function cancelItemDiscount(itemId) {
    const item = cart.find(i => i.id === itemId || String(i.id) === String(itemId));
    if (!item) return;

    if (item.discount_request_id && (item.discount_status === 'PENDING' || item.discount_status === 'ESCALATED')) {
        const formData = new FormData();
        formData.append('request_id', item.discount_request_id);
        fetch('pos-discount-api.php?action=cancel_request', {
            method: 'POST',
            body: formData
        }).catch(err => console.error('Cancel request error:', err));
    }

    delete item.discount_request_id;
    delete item.discount_amount;
    delete item.discount_percent;
    delete item.discount_status;
    delete item.approval_level;

    renderCart();
}

function pollDiscountStatuses() {
    // 1. Poll cart active discount statuses
    const pendingReqIds = cart
        .filter(i => i.discount_request_id && (i.discount_status === 'PENDING' || i.discount_status === 'ESCALATED'))
        .map(i => i.discount_request_id);

    if (pendingReqIds.length > 0) {
        fetch(`pos-discount-api.php?action=poll_status&request_ids=${encodeURIComponent(pendingReqIds.join(','))}`)
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success' && data.requests) {
                    let changed = false;
                    cart.forEach(item => {
                        if (item.discount_request_id && data.requests[item.discount_request_id]) {
                            const updated = data.requests[item.discount_request_id];
                            if (updated.status !== item.discount_status || (updated.discount_amount !== null && updated.discount_amount !== item.discount_amount)) {
                                item.discount_status = updated.status;
                                if (updated.status === 'APPROVED' || updated.status === 'APPROVED_MODIFIED') {
                                    item.discount_percent = parseFloat(updated.approved_discount_percent);
                                    item.discount_amount = parseFloat(updated.discount_amount);
                                } else if (updated.status === 'REJECTED') {
                                    item.discount_percent = 0;
                                    item.discount_amount = 0;
                                }
                                changed = true;
                            }
                        }
                    });
                    if (changed) {
                        renderCart();
                    }
                }
            })
            .catch(err => console.error('Error polling discount status:', err));
    }

    // 2. Poll approver badge count if approver
    if (storeDiscountConfig.isApprover) {
        pollPendingQueueBadge();
    }
}

function pollPendingQueueBadge() {
    fetch('pos-discount-api.php?action=get_pending_requests')
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                const badge = document.getElementById('posPendingBadge');
                if (badge) {
                    const count = data.approvable_count !== undefined ? data.approvable_count : data.count;
                    badge.innerText = count;
                    badge.style.display = (count > 0) ? 'inline-block' : 'none';
                }
            }
        })
        .catch(err => console.error('Error polling queue badge:', err));
}

// ==========================================
// APPROVAL QUEUE MODAL FUNCTIONS
// ==========================================

function openApprovalQueueModal() {
    $('#posApprovalQueueModal').modal('show');
    refreshApprovalQueue();
}

function refreshApprovalQueue() {
    const container = document.getElementById('queueRequestsContainer');
    const alertBox = document.getElementById('queueModalAlert');
    if (alertBox) alertBox.style.display = 'none';

    container.innerHTML = `
        <div class="text-center text-muted" style="padding: 30px 10px;">
            <i class="fa fa-spinner fa-spin fa-2x text-primary"></i>
            <div style="margin-top: 6px; font-size: 13px;">Loading pending requests...</div>
        </div>
    `;

    fetch('pos-discount-api.php?action=get_pending_requests')
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                renderApprovalQueueList(data.requests || []);
            } else {
                container.innerHTML = `<div class="alert alert-danger">${escapeHtml(data.message || 'Failed to load requests.')}</div>`;
            }
        })
        .catch(err => {
            console.error('Error loading approval queue:', err);
            container.innerHTML = `<div class="alert alert-danger">Network error loading approval requests.</div>`;
        });
}

function renderApprovalQueueList(requests) {
    const container = document.getElementById('queueRequestsContainer');
    if (!requests || requests.length === 0) {
        container.innerHTML = `
            <div class="text-center text-muted" style="padding: 35px 10px; background: #f8fafc; border-radius: 8px; border: 1px dashed #cbd5e1;">
                <i class="fa fa-check-circle fa-3x" style="color: #10b981;"></i>
                <h4 style="margin: 10px 0 4px 0; font-weight: 700; color: #334155;">No Pending Discount Requests</h4>
                <p style="font-size: 12.5px; color: #64748b;">All requested item discounts have been processed.</p>
            </div>
        `;
        return;
    }

    let html = '';
    requests.forEach(r => {
        const isEscalated = (r.status === 'ESCALATED' || r.approval_level === 'HIGHER_APPROVER');
        const origVal = parseFloat(r.original_item_value);
        const reqAmt = parseFloat(r.discount_amount || (origVal * (parseFloat(r.requested_discount_percent) / 100)));
        const reqPct = parseFloat(r.requested_discount_percent);
        const canApprove = r.can_approve;
        const isSelf = r.is_self;

        let statusBadge = isEscalated
            ? `<span class="label label-warning" style="background: #d97706; font-size: 10px; font-weight: 800; padding: 2px 6px;"><i class="fa fa-shield"></i> SPECIAL ESCALATED</span>`
            : `<span class="label label-warning" style="background: #f59e0b; font-size: 10px; font-weight: 800; padding: 2px 6px;"><i class="fa fa-clock-o"></i> PENDING NORMAL</span>`;

        let actionHtml = '';
        if (canApprove) {
            actionHtml = `
                <div style="display: flex; gap: 6px; align-items: center; justify-content: flex-end; flex-wrap: wrap;">
                    <button type="button" class="btn btn-success btn-xs" onclick="approveQueueDiscount('${escapeHtml(r.request_id)}')" style="font-weight: 700; padding: 4px 10px; background: #16a34a; border-color: #15803d;">
                        <i class="fa fa-check"></i> Approve ₱${reqAmt.toFixed(2)}
                    </button>
                    <button type="button" class="btn btn-warning btn-xs" onclick="modifyQueueDiscount('${escapeHtml(r.request_id)}', ${origVal}, ${reqPct}, ${reqAmt})" style="font-weight: 700; padding: 4px 10px; background: #d97706; border-color: #b45309; color: #fff;">
                        <i class="fa fa-edit"></i> Modify ₱
                    </button>
                    <button type="button" class="btn btn-danger btn-xs" onclick="rejectQueueDiscount('${escapeHtml(r.request_id)}')" style="font-weight: 700; padding: 4px 10px;">
                        <i class="fa fa-times"></i> Reject
                    </button>
                </div>
            `;
        } else if (isSelf) {
            actionHtml = `<span class="label label-danger" style="font-size: 10.5px; padding: 3px 6px;"><i class="fa fa-lock"></i> Self-Approval Prohibited (${escapeHtml(r.requester_role)})</span>`;
        } else {
            actionHtml = `<span class="label label-default" style="font-size: 10.5px; padding: 3px 6px;">Requires ${escapeHtml(r.required_approval_role)}</span>`;
        }

        html += `
            <div style="background: #f8fafc; border: 1.5px solid #e2e8f0; border-radius: 8px; padding: 12px 14px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; flex-wrap: wrap; gap: 6px;">
                    <div>
                        <strong style="font-family: monospace; font-size: 13px; color: #1e3a8a;">${escapeHtml(r.request_id)}</strong>
                        <span style="font-size: 11px; color: #64748b; margin-left: 8px;"><i class="fa fa-user"></i> ${escapeHtml(r.cashier_name)} <span class="label label-default" style="font-size: 9.5px;">${escapeHtml(r.requester_role)}</span></span>
                    </div>
                    <div>
                        ${statusBadge}
                    </div>
                </div>

                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; flex-wrap: wrap; gap: 6px;">
                    <div>
                        <strong style="font-size: 13.5px; color: #0f172a;">${escapeHtml(r.product_name)}</strong>
                        <div style="font-size: 11.5px; color: #64748b;">
                            Qty: <strong>${r.quantity}</strong> | Gross Total: <strong>₱${origVal.toFixed(2)}</strong>
                            ${r.sku ? ' | SKU: ' + escapeHtml(r.sku) : ''}
                        </div>
                        ${r.cashier_remarks ? `<div style="font-size: 11px; color: #475569; margin-top: 2px; font-style: italic;">Reason: "${escapeHtml(r.cashier_remarks)}"</div>` : ''}
                    </div>
                    <div style="text-align: right;">
                        <span style="font-size: 11px; color: #64748b; text-transform: uppercase; font-weight: 700; display: block;">Requested Discount</span>
                        <strong style="font-size: 16px; color: #2563eb;">₱${reqAmt.toFixed(2)} (${reqPct.toFixed(1)}%)</strong>
                    </div>
                </div>

                <div style="border-top: 1px solid #e2e8f0; padding-top: 8px; margin-top: 6px;">
                    ${actionHtml}
                </div>
            </div>
        `;
    });

    container.innerHTML = html;
}

function approveQueueDiscount(requestId) {
    const remarks = prompt('Approver remarks / notes (Optional):', '');
    if (remarks === null) return; // Cancelled

    const formData = new FormData();
    formData.append('request_id', requestId);
    formData.append('approver_remarks', remarks.trim());

    fetch('pos-discount-api.php?action=approve_discount', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            refreshApprovalQueue();
            pollDiscountStatuses();
        } else {
            alert('Approval error: ' + (data.message || 'Failed to approve.'));
        }
    })
    .catch(err => {
        console.error('Approve error:', err);
        alert('Network error approving discount.');
    });
}

function modifyQueueDiscount(requestId, origVal, currPct, currAmt) {
    const input = prompt(`Enter MODIFIED discount amount in Philippine Pesos (₱):\n(Gross: ₱${origVal.toFixed(2)} | Current: ₱${currAmt.toFixed(2)} / ${currPct.toFixed(1)}%):`, currAmt.toFixed(2));
    if (input === null) return;

    const newAmt = parseFloat(input);
    if (isNaN(newAmt) || newAmt <= 0) {
        alert('Please enter a valid discount amount greater than ₱0.00.');
        return;
    }
    if (newAmt >= origVal) {
        alert(`Discount cannot equal or exceed item gross total (₱${origVal.toFixed(2)}).`);
        return;
    }

    const remarks = prompt('Enter modification remarks / rationale (Required):', 'Modified per manager assessment');
    if (!remarks || !remarks.trim()) {
        alert('Remarks are required when modifying a discount amount.');
        return;
    }

    const formData = new FormData();
    formData.append('request_id', requestId);
    formData.append('modified_discount_amount', newAmt);
    formData.append('approver_remarks', remarks.trim());

    fetch('pos-discount-api.php?action=modify_discount', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            refreshApprovalQueue();
            pollDiscountStatuses();
        } else {
            alert('Modification error: ' + (data.message || 'Failed to modify.'));
        }
    })
    .catch(err => {
        console.error('Modify error:', err);
        alert('Network error modifying discount.');
    });
}

function rejectQueueDiscount(requestId) {
    const remarks = prompt('Enter reason for rejection (Required):', 'Discount request not approved');
    if (!remarks || !remarks.trim()) {
        alert('Reason is required to reject a discount request.');
        return;
    }

    const formData = new FormData();
    formData.append('request_id', requestId);
    formData.append('approver_remarks', remarks.trim());

    fetch('pos-discount-api.php?action=reject_discount', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            refreshApprovalQueue();
            pollDiscountStatuses();
        } else {
            alert('Rejection error: ' + (data.message || 'Failed to reject.'));
        }
    })
    .catch(err => {
        console.error('Reject error:', err);
        alert('Network error rejecting discount.');
    });
}

// Start polling on page load
setInterval(pollDiscountStatuses, 3000);
setTimeout(pollDiscountStatuses, 500);

function setExactAmount() {
    let grossSubtotal = 0;
    let totalDiscountSavings = 0;
    let totalReturnCredits = 0;
    cart.forEach(item => {
        if (item.item_type === 'RETURN_CREDIT') {
            totalReturnCredits += Math.abs(item.price) * item.qty;
            return;
        }
        const itemGross = item.price * item.qty;
        grossSubtotal += itemGross;
        if (item.discount_status === 'APPROVED' || item.discount_status === 'APPROVED_MODIFIED') {
            const discAmt = item.discount_amount !== undefined ? item.discount_amount : (itemGross * (item.discount_percent / 100));
            totalDiscountSavings += discAmt;
        }
    });
    const isLocationActive = document.getElementById('locationRadio').checked;
    const deliveryCost = isLocationActive ? (parseFloat(document.getElementById('posDeliveryCost').value) || 0) : 0;
    const netSubtotal = Math.max(0, grossSubtotal - totalDiscountSavings);
    const netPayable = (netSubtotal + deliveryCost) - totalReturnCredits;
    const grandTotal = Math.max(0, netPayable);
    document.getElementById('posAmountTendered').value = grandTotal.toFixed(2);
    updatePOSCalculations();
}

function setCashPreset(amount) {
    document.getElementById('posAmountTendered').value = amount.toFixed(2);
    updatePOSCalculations();
}

function handlePaymentMethodChange() {
    const method = document.getElementById('posPaymentMethod').value;
    const cashSection = document.getElementById('cashCalculatorSection');
    if (method.indexOf('Cash') > -1) {
        cashSection.style.display = 'block';
    } else {
        setExactAmount();
    }
}

function toggleCustomerType() {
    const type = document.querySelector('input[name="customer_type"]:checked').value;
    const walkinFields = document.getElementById('walkinFields');
    const registeredFields = document.getElementById('registeredFields');
    
    if (type === 'walkin') {
        walkinFields.style.display = 'block';
        registeredFields.style.display = 'none';
    } else if (type === 'registered') {
        walkinFields.style.display = 'none';
        registeredFields.style.display = 'block';
    }
}

function toggleLocationOption() {
    const isLocationActive = document.getElementById('locationRadio').checked;
    const locationFields = document.getElementById('locationFields');
    
    if (isLocationActive) {
        locationFields.style.display = 'block';
        handleLocationChange();
    } else {
        locationFields.style.display = 'none';
        document.getElementById('posDeliveryCost').value = '0.00';
        updatePOSCalculations();
    }
}

function handleLocationChange() {
    const isLocationActive = document.getElementById('locationRadio').checked;
    if (!isLocationActive) {
        document.getElementById('posDeliveryCost').value = '0.00';
        updatePOSCalculations();
        return;
    }

    const select = document.getElementById('locationBrgySelect');
    const selectedOption = select.options[select.selectedIndex];
    let shippingRate = 0;
    
    if (selectedOption && selectedOption.dataset.shipping !== undefined && select.value !== '') {
        shippingRate = parseFloat(selectedOption.dataset.shipping) || 0;
    }
    
    document.getElementById('posDeliveryCost').value = shippingRate.toFixed(2);
    updatePOSCalculations();
}

let currentActiveDept = 'all';
let currentActiveCat = 'all';

function togglePOSCategories() {
    const wrapper = document.getElementById('posCategoryAccordion');
    const icon = document.getElementById('posCatToggleIcon');
    const text = document.getElementById('posCatToggleText');
    if (!wrapper) return;

    wrapper.classList.toggle('collapsed');
    const isCollapsed = wrapper.classList.contains('collapsed');

    if (isCollapsed) {
        if (icon) icon.className = 'fa fa-chevron-down';
        if (text) text.innerText = 'Expand';
        try { localStorage.setItem('pos_categories_collapsed', '1'); } catch (e) {}
    } else {
        if (icon) icon.className = 'fa fa-chevron-up';
        if (text) text.innerText = 'Collapse';
        try { localStorage.setItem('pos_categories_collapsed', '0'); } catch (e) {}
    }
}

function updateCategoryBreadcrumb() {
    const badge = document.getElementById('posActiveFilterBreadcrumb');
    if (!badge) return;
    const deptText = (currentActiveDept === 'all') ? 'All Departments' : currentActiveDept;
    const catText = (currentActiveCat === 'all') ? 'All Sub-Systems' : currentActiveCat;
    badge.innerHTML = `${escapeHtml(deptText)} &bull; ${escapeHtml(catText)}`;
}

function scrollDeptCarousel(offset) {
    const bar = document.getElementById('posDeptTabsBar');
    if (bar) {
        bar.scrollBy({ left: offset, behavior: 'smooth' });
        setTimeout(updateDeptNavState, 280);
    }
}

function updateDeptNavState() {
    const bar = document.getElementById('posDeptTabsBar');
    const prevBtn = document.getElementById('posDeptNavPrev');
    const nextBtn = document.getElementById('posDeptNavNext');
    if (!bar || !prevBtn || !nextBtn) return;

    const maxScrollLeft = bar.scrollWidth - bar.clientWidth;
    prevBtn.disabled = (bar.scrollLeft <= 2);
    nextBtn.disabled = (bar.scrollLeft >= maxScrollLeft - 2);
}

function filterDepartment(deptName, btn) {
    currentActiveDept = deptName || 'all';
    currentActiveCat = 'all';

    // Update active state on Department Tabs
    document.querySelectorAll('.pos-dept-tab').forEach(t => t.classList.remove('active'));
    if (btn) {
        btn.classList.add('active');
        try {
            btn.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
        } catch(e) {}
    }

    // Show/Hide Mid-Category pills in Tier 2
    document.querySelectorAll('.pos-cat-pill').forEach(pill => {
        const pDept = pill.getAttribute('data-dept');
        const pCat = pill.getAttribute('data-cat');

        if (pCat === 'all') {
            pill.style.display = '';
            pill.classList.add('active');
            const allLabel = pill.querySelector('.pos-all-label');
            if (allLabel) {
                allLabel.innerText = (currentActiveDept === 'all') ? 'All in Selected' : ('All in ' + currentActiveDept);
            }
        } else if (currentActiveDept === 'all' || pDept === currentActiveDept) {
            pill.style.display = '';
            pill.classList.remove('active');
        } else {
            pill.style.display = 'none';
            pill.classList.remove('active');
        }
    });

    updateCategoryBreadcrumb();
    filterPOSProducts();
    setTimeout(updateDeptNavState, 350);
}

function filterCategory(catName, btn) {
    currentActiveCat = catName || 'all';
    document.querySelectorAll('.pos-cat-pill').forEach(pill => pill.classList.remove('active'));
    if (btn) btn.classList.add('active');
    updateCategoryBreadcrumb();
    filterPOSProducts();
}

function setPOSViewMode(mode) {
    const gridView = document.getElementById('posProductGrid');
    const listView = document.getElementById('posProductListContainer');
    const gridBtn = document.getElementById('posViewGridBtn');
    const listBtn = document.getElementById('posViewListBtn');
    const emptyMsg = document.getElementById('posProductGridEmptyMsg');

    if (mode === 'list') {
        if (listBtn) listBtn.classList.add('active');
        if (gridBtn) gridBtn.classList.remove('active');
        if (!emptyMsg || emptyMsg.style.display === 'none') {
            if (gridView) gridView.style.display = 'none';
            if (listView) listView.style.display = 'block';
        }
    } else {
        if (gridBtn) gridBtn.classList.add('active');
        if (listBtn) listBtn.classList.remove('active');
        if (!emptyMsg || emptyMsg.style.display === 'none') {
            if (listView) listView.style.display = 'none';
            if (gridView) gridView.style.display = 'grid';
        }
    }
    try {
        localStorage.setItem('pos_product_view_mode', mode);
    } catch(e) {}
}

function openSpecialOrderWithPrefill(namePrefill) {
    openSpecialOrderModal();
    const nameInput = document.getElementById('soProductName');
    if (nameInput && namePrefill) {
        nameInput.value = namePrefill;
        setTimeout(() => {
            const priceInput = document.getElementById('soUnitPrice');
            if (priceInput) priceInput.focus();
        }, 360);
    }
}

function escapeRegex(string) {
    return (string || '').replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

const POS_HARDWARE_SYNONYMS = {
    'gi': ['galvanized', 'corrugated', 'yero', 'gi sheet', 'gi pipe', 'gi wire'],
    'g.i.': ['galvanized', 'corrugated', 'yero', 'gi sheet'],
    'chb': ['hollow block', 'concrete hollow block', 'masonry block'],
    'rsb': ['deformed', 'steel bar', 'rebar', 'bakal'],
    'cwn': ['wire nail', 'common wire nail', 'pako', 'nail'],
    'pvc': ['polyvinyl', 'tubo', 'neltex', 'emerald', 'sanitary pipe', 'blue pipe', 'orange pipe'],
    'ppr': ['polypropylene', 'green pipe', 'fusion'],
    'purlin': ['c channel', 'c-channel', 'c purlin', 'c-purlin', 'purlins'],
    'c-purlin': ['c channel', 'c-channel', 'purlin', 'purlins'],
    'ply': ['plywood', 'marine plywood', 'marine ply', 'ordinary plywood', 'kahoy'],
    'thhn': ['electrical wire', 'stranded wire', 'building wire', 'kuryente'],
    'thwn': ['electrical wire', 'stranded wire', 'building wire', 'kuryente'],
    'cement': ['semento', 'portland', 'pozolan'],
    'paint': ['pintura', 'boysen', 'davies'],
    'sandpaper': ['lija', 'abrasive', 'silicon carbide', 'sand paper'],
    'sand paper': ['lija', 'abrasive', 'sandpaper'],
    'lija': ['sandpaper', 'abrasive', 'sand paper', 'waterproof']
};

function normalizeSearchString(str) {
    if (!str) return '';
    let s = str.toLowerCase();
    s = s.replace(/(\d+(?:\.\d+)?)\s*["']?\s*x\s*["']?\s*(\d+(?:\.\d+)?)/gi, '$1x$2 $1 x $2');
    s = s.replace(/(\d+(?:\.\d+)?)\s+(kg|mm|cm|m|in|pcs|pc|ft|gal|ltr|l|w|v|hp)\b/gi, '$1$2 $1 $2');
    s = s.replace(/["']/g, '');
    return s;
}

function isFuzzyMatch(token, word) {
    if (!token || !word || token.length < 4 || word.length < 4) return false;
    if (Math.abs(token.length - word.length) > 1) return false;
    
    let i = 0, j = 0, diffCount = 0;
    while (i < token.length && j < word.length) {
        if (token[i] !== word[j]) {
            diffCount++;
            if (diffCount > 1) return false;
            if (token.length > word.length) i++;
            else if (word.length > token.length) j++;
            else { i++; j++; }
        } else {
            i++; j++;
        }
    }
    return true;
}

function calculatePOSMatchScore(tokens, baseName, brand, ecat, searchData) {
    let totalScore = 0;
    const baseNameLower = (baseName || '').toLowerCase();
    const brandLower = (brand || '').toLowerCase();
    const ecatLower = (ecat || '').toLowerCase();
    const searchDataLower = (searchData || '').toLowerCase();
    const searchDataNormalized = normalizeSearchString(searchDataLower);
    const searchDataWords = searchDataLower.split(/[\s,;|\-]+/).filter(w => w.length > 0);

    for (let i = 0; i < tokens.length; i++) {
        const rawToken = tokens[i];
        const token = rawToken.toLowerCase();
        const tokenEscaped = escapeRegex(token);
        const wordBoundaryRegex = new RegExp('\\b' + tokenEscaped, 'i');

        let tokenScore = 0;

        // 1. Direct Prefix: Product Title starts with token (e.g. "cem" -> "Cement...")
        if (baseNameLower.startsWith(token)) {
            tokenScore = Math.max(tokenScore, 100);
        }
        // 2. Word Boundary in Product Title: Any word in Title starts with token (e.g. "cem" -> "Holcim Cement")
        else if (wordBoundaryRegex.test(baseNameLower)) {
            tokenScore = Math.max(tokenScore, 85);
        }
        // 3. Brand starts with token or word in Brand starts with token (e.g. "cem" -> "CEMEX")
        else if (brandLower.startsWith(token) || wordBoundaryRegex.test(brandLower)) {
            tokenScore = Math.max(tokenScore, 75);
        }
        // 4. Category starts with token (e.g. "plu" -> "Plumbing")
        else if (ecatLower.startsWith(token) || wordBoundaryRegex.test(ecatLower)) {
            tokenScore = Math.max(tokenScore, 65);
        }
        // 5. SKU, size, or spec word-boundary prefix match anywhere in searchData
        else if (wordBoundaryRegex.test(searchDataLower) || wordBoundaryRegex.test(searchDataNormalized)) {
            tokenScore = Math.max(tokenScore, 50);
        }
        // 6. Substring match fallback anywhere in searchData or normalized dimensions
        else if (searchDataLower.indexOf(token) !== -1 || searchDataNormalized.indexOf(token) !== -1) {
            tokenScore = Math.max(tokenScore, 30);
        }
        // 7. Hardware Acronym / Trade Synonym match (e.g. "gi", "chb", "rsb", "cwn", "pvc", "ply")
        else if (POS_HARDWARE_SYNONYMS[token]) {
            const synList = POS_HARDWARE_SYNONYMS[token];
            for (let s = 0; s < synList.length; s++) {
                if (searchDataLower.indexOf(synList[s]) !== -1 || searchDataNormalized.indexOf(synList[s]) !== -1) {
                    tokenScore = Math.max(tokenScore, 45);
                    break;
                }
            }
        }

        // 8. 1-Character Typo / Fuzzy tolerance (if token >= 4 chars and no match yet)
        if (tokenScore === 0 && token.length >= 4) {
            for (let w = 0; w < searchDataWords.length; w++) {
                if (isFuzzyMatch(token, searchDataWords[w])) {
                    tokenScore = Math.max(tokenScore, 25);
                    break;
                }
            }
        }

        // If after all checks this token still has 0 score -> reject this item
        if (tokenScore === 0) {
            return { matches: false, score: 0 };
        }

        totalScore += tokenScore;
    }

    return { matches: true, score: totalScore };
}

function highlightSearchTokens(element, origText, tokens) {
    if (!tokens || tokens.length === 0) {
        element.textContent = origText;
        return;
    }
    const safeOrig = escapeHtml(origText);
    const pattern = tokens.map(t => escapeRegex(escapeHtml(t))).filter(t => t.length > 0).join('|');
    if (!pattern) {
        element.textContent = origText;
        return;
    }
    const regex = new RegExp('(' + pattern + ')', 'gi');
    element.innerHTML = safeOrig.replace(regex, '<mark class="pos-search-hl">$1</mark>');
}

function initializePOSSearchIndices() {
    document.querySelectorAll('#posProductGrid .pos-product-item').forEach((item, idx) => {
        if (!item.hasAttribute('data-orig-index')) {
            item.setAttribute('data-orig-index', idx);
        }
        const titleEl = item.querySelector('.pos-parent-name');
        if (titleEl && !titleEl.hasAttribute('data-orig-title')) {
            titleEl.setAttribute('data-orig-title', titleEl.innerText.trim());
        }
    });

    document.querySelectorAll('#posProductListContainer tbody .pos-product-item').forEach((item, idx) => {
        if (!item.hasAttribute('data-orig-index')) {
            item.setAttribute('data-orig-index', idx);
        }
        const titleEl = item.querySelector('.pos-list-row-title');
        if (titleEl && !titleEl.hasAttribute('data-orig-title')) {
            titleEl.setAttribute('data-orig-title', titleEl.innerText.trim());
        }
    });
}

function handlePOSSearchEnter(event) {
    if (event) event.preventDefault();
    const savedViewMode = (typeof localStorage !== 'undefined' && localStorage.getItem('pos_product_view_mode')) || 'grid';
    const containerSelector = (savedViewMode === 'list') ? '#posProductListContainer' : '#posProductGrid';
    const visibleItems = Array.from(document.querySelectorAll(containerSelector + ' .pos-product-item')).filter(el => el.style.display !== 'none');
    if (visibleItems.length === 1) {
        // Trigger click on the single matched item (fast-add / open variant modal)
        visibleItems[0].click();
    } else if (visibleItems.length > 1) {
        const topScore = parseInt(visibleItems[0].getAttribute('data-search-score') || '0', 10);
        if (topScore >= 100) {
            visibleItems[0].click();
        }
    }
}

function filterPOSProducts() {
    initializePOSSearchIndices();

    const rawQuery = document.getElementById('posSearchInput')?.value.trim() || '';
    const query = rawQuery.toLowerCase();
    const tokens = query.length > 0 ? query.split(/\s+/).filter(t => t.length > 0) : [];
    const isSearching = tokens.length > 0;
    const inStockOnly = document.getElementById('posInStockToggle')?.checked || false;

    const gridItems = Array.from(document.querySelectorAll('#posProductGrid .pos-product-item'));
    const listItems = Array.from(document.querySelectorAll('#posProductListContainer tbody .pos-product-item'));

    let matchingItemIds = new Set();
    let visibleGridItems = [];
    let visibleListItems = [];

    // Helper for matching, scoring, and highlighting
    function processItemList(items, isList) {
        items.forEach(item => {
            const searchData = (item.getAttribute('data-name') || '').toLowerCase();
            const baseName = item.getAttribute('data-base-name') || '';
            const brand = item.getAttribute('data-brand') || '';
            const itemCat = item.getAttribute('data-category') || '';
            const itemMcat = item.getAttribute('data-mcat') || '';
            const itemDept = item.getAttribute('data-dept') || '';
            const rawStock = parseInt(item.getAttribute('data-stock'), 10);
            const stock = isNaN(rawStock) ? 999 : rawStock;

            // In-stock check
            if (inStockOnly && stock <= 0) {
                item.style.display = 'none';
                return;
            }

            // Category & Department filter check
            let matchesDept = (currentActiveDept === 'all' || itemDept === currentActiveDept);
            let matchesCat = (currentActiveCat === 'all' || itemMcat === currentActiveCat || itemCat === currentActiveCat);
            if (!matchesDept || !matchesCat) {
                item.style.display = 'none';
                return;
            }

            // 3-to-5 char prefix & token scoring check
            let score = 100;
            if (isSearching) {
                const matchResult = calculatePOSMatchScore(tokens, baseName, brand, itemCat, searchData);
                if (!matchResult.matches) {
                    item.style.display = 'none';
                    return;
                }
                score = matchResult.score;
            }

            item.style.display = '';
            item.setAttribute('data-search-score', score);

            // Title Highlighting
            const titleEl = isList ? item.querySelector('.pos-list-row-title') : item.querySelector('.pos-parent-name');
            if (titleEl) {
                const origTitle = titleEl.getAttribute('data-orig-title') || titleEl.innerText.trim();
                if (isSearching && query.length >= 2) {
                    highlightSearchTokens(titleEl, origTitle, tokens);
                } else {
                    titleEl.textContent = origTitle;
                }
            }

            // Unique counting
            const rawGroup = item.getAttribute('data-group');
            if (rawGroup) {
                try {
                    const g = JSON.parse(rawGroup);
                    matchingItemIds.add(g.base_name + '_' + (g.ecat_id || ''));
                } catch(e) {
                    matchingItemIds.add(item);
                }
            } else {
                matchingItemIds.add(item);
            }

            const itemPayload = { 
                el: item, 
                score: score, 
                origIndex: parseInt(item.getAttribute('data-orig-index') || '0', 10) 
            };
            if (isList) {
                visibleListItems.push(itemPayload);
            } else {
                visibleGridItems.push(itemPayload);
            }
        });
    }

    processItemList(gridItems, false);
    processItemList(listItems, true);

    // Re-order DOM elements by score descending (or original index if not searching)
    if (isSearching) {
        const gridContainer = document.getElementById('posProductGrid');
        if (gridContainer) {
            visibleGridItems.sort((a, b) => (b.score - a.score) || (a.origIndex - b.origIndex));
            visibleGridItems.forEach(itemObj => gridContainer.appendChild(itemObj.el));
        }
        const listTbody = document.querySelector('#posProductListContainer tbody');
        if (listTbody) {
            visibleListItems.sort((a, b) => (b.score - a.score) || (a.origIndex - b.origIndex));
            visibleListItems.forEach(itemObj => listTbody.appendChild(itemObj.el));
        }
    } else {
        // Restore original order
        const gridContainer = document.getElementById('posProductGrid');
        if (gridContainer) {
            gridItems.sort((a, b) => parseInt(a.getAttribute('data-orig-index') || '0', 10) - parseInt(b.getAttribute('data-orig-index') || '0', 10));
            gridItems.forEach(item => gridContainer.appendChild(item));
        }
        const listTbody = document.querySelector('#posProductListContainer tbody');
        if (listTbody) {
            listItems.sort((a, b) => parseInt(a.getAttribute('data-orig-index') || '0', 10) - parseInt(b.getAttribute('data-orig-index') || '0', 10));
            listItems.forEach(item => listTbody.appendChild(item));
        }
    }

    const visibleCount = matchingItemIds.size;
    const productCountEl = document.getElementById('productCount');
    if (productCountEl) productCountEl.innerText = visibleCount;

    // Show friendly zero-match guidance
    let emptyMsg = document.getElementById('posProductGridEmptyMsg');
    const gridView = document.getElementById('posProductGrid');
    const listView = document.getElementById('posProductListContainer');

    if (visibleCount === 0) {
        if (!emptyMsg) {
            emptyMsg = document.createElement('div');
            emptyMsg.id = 'posProductGridEmptyMsg';
            emptyMsg.style.cssText = 'text-align: center; padding: 35px 20px; background: #ffffff; border: 1.5px dashed #cbd5e1; border-radius: 8px; margin: 10px 0; width: 100%; box-shadow: 0 1px 3px rgba(0,0,0,0.05);';
            const productBody = document.querySelector('.pos-product-body');
            if (productBody) productBody.appendChild(emptyMsg);
        }
        emptyMsg.innerHTML = `
            <div style="font-size: 26px; color: #94a3b8; margin-bottom: 6px;"><i class="fa fa-search"></i></div>
            <div style="font-size: 14px; font-weight: 700; color: #334155;">No products found matching "<em>${escapeHtml(rawQuery)}</em>"</div>
            <div style="font-size: 12px; color: #64748b; margin-top: 4px;">Looking for a custom product, invoice, or return?</div>
            <div style="margin-top: 14px; display: flex; justify-content: center; gap: 8px; flex-wrap: wrap;">
                ${rawQuery ? `
                    <button type="button" class="btn btn-warning btn-sm" onclick="openSpecialOrderWithPrefill('${escapeHtml(rawQuery).replace(/'/g, "\\'")}')" style="font-weight: 700; background-color: #d97706; border-color: #b45309; color: #fff;">
                        <i class="fa fa-plus-circle"></i> + Custom Order "${escapeHtml(rawQuery.length > 20 ? rawQuery.substring(0, 20) + '...' : rawQuery)}"
                    </button>
                    <button type="button" class="btn btn-danger btn-sm" onclick="openReturnModal('${escapeHtml(rawQuery).replace(/'/g, "\\'")}')" style="font-weight: 700; background-color: #dc2626; border-color: #b91c1c;">
                        <i class="fa fa-undo"></i> Search in Returns &amp; Refunds
                    </button>
                ` : ''}
                <button type="button" class="btn btn-default btn-sm" onclick="clearPOSSearch()" style="font-weight: 700;">
                    <i class="fa fa-times"></i> Reset Filters
                </button>
            </div>
        `;
        emptyMsg.style.display = 'block';
        if (gridView) gridView.style.display = 'none';
        if (listView) listView.style.display = 'none';
    } else {
        if (emptyMsg) {
            emptyMsg.style.display = 'none';
        }
        const savedViewMode = (typeof localStorage !== 'undefined' && localStorage.getItem('pos_product_view_mode')) || 'grid';
        if (savedViewMode === 'list') {
            if (gridView) gridView.style.display = 'none';
            if (listView) listView.style.display = 'block';
        } else {
            if (listView) listView.style.display = 'none';
            if (gridView) gridView.style.display = 'grid';
        }
    }
}

function clearPOSSearch() {
    const input = document.getElementById('posSearchInput');
    if (input) input.value = '';
    filterPOSProducts();
}

function validatePOSForm() {
    if (cart.length === 0) {
        alert('Please add at least one item to the cart.');
        return false;
    }
    const type = document.querySelector('input[name="customer_type"]:checked')?.value || 'walkin';
    if (type === 'registered') {
        const regCust = document.querySelector('select[name="registered_cust_id"]')?.value;
        if (!regCust) {
            alert('Please select a registered customer.');
            return false;
        }
    }
    const locRadio = document.getElementById('locationRadio');
    if (locRadio && locRadio.checked) {
        const brgy = document.getElementById('locationBrgySelect')?.value;
        if (!brgy) {
            alert('Please select a Barangay location or uncheck the Location option.');
            return false;
        }
    }

    // Check if down-trade refund due or payment validation
    let grossSubtotal = 0;
    let totalDiscountSavings = 0;
    let totalReturnCredits = 0;
    cart.forEach(item => {
        if (item.item_type === 'RETURN_CREDIT') {
            totalReturnCredits += Math.abs(item.price) * item.qty;
            return;
        }
        const itemGross = item.price * item.qty;
        grossSubtotal += itemGross;
        if (item.discount_status === 'APPROVED' || item.discount_status === 'APPROVED_MODIFIED') {
            const discAmt = item.discount_amount !== undefined ? item.discount_amount : (itemGross * (item.discount_percent / 100));
            totalDiscountSavings += discAmt;
        }
    });
    const isLocationActive = document.getElementById('locationRadio')?.checked;
    const deliveryCost = isLocationActive ? (parseFloat(document.getElementById('posDeliveryCost')?.value) || 0) : 0;
    const netSubtotal = Math.max(0, grossSubtotal - totalDiscountSavings);
    const netPayable = (netSubtotal + deliveryCost) - totalReturnCredits;
    const grandTotal = Math.max(0, netPayable);
    const refundDue = (netPayable < 0) ? Math.abs(netPayable) : 0;

    const payMethod = document.getElementById('posPaymentMethod')?.value || 'Cash (OTC)';
    if (payMethod.toLowerCase().includes('cash')) {
        const tendered = parseFloat(document.getElementById('posAmountTendered')?.value) || 0;
        if (grandTotal > 0 && tendered < grandTotal) {
            alert('Amount tendered (₱' + tendered.toFixed(2) + ') is less than the Grand Total (₱' + grandTotal.toFixed(2) + '). Please enter sufficient cash.');
            document.getElementById('posAmountTendered')?.focus();
            return false;
        }
    }

    if (refundDue > 0) {
        const confirmMsg = `Down-Trade Exchange Confirmation:\n\nReturn credit (₱${totalReturnCredits.toFixed(2)}) exceeds new purchases (₱${(netSubtotal + deliveryCost).toFixed(2)}).\n\nExcess Refund Due: ₱${refundDue.toFixed(2)}\n\nPlease ensure ₱${refundDue.toFixed(2)} is paid out to the customer from the cash drawer.\n\nProceed with completing this exchange?`;
        if (!confirm(confirmMsg)) {
            return false;
        }
    }

    return true;
}

function closeReceiptModal() {
    cart = [];
    const fd = new FormData();
    fd.append('pos_action', 'clear_session_cart');
    fetch('pos.php', { method: 'POST', body: fd })
        .then(() => {
            window.location.href = 'pos.php?clear_cart=1';
        })
        .catch(() => {
            window.location.href = 'pos.php?clear_cart=1';
        });
}

function closePOSPurchaseOrderModal() {
    cart = [];
    const fd = new FormData();
    fd.append('pos_action', 'clear_session_cart');
    fetch('pos.php', { method: 'POST', body: fd })
        .then(() => {
            window.location.href = 'pos.php?clear_cart=1';
        })
        .catch(() => {
            window.location.href = 'pos.php?clear_cart=1';
        });
}


// ============================================================================
// POS UNIVERSAL MULTI-PRINTER ENGINE & ESC/POS CONFIGURATION JAVASCRIPT
// ============================================================================

let posDetectedPrinters = [];
const MAX_THERMAL_WIDTH_MM = 210;
const MIN_THERMAL_FONT_SIZE = 8;

function getRecommendedContentWidth(paperWidthMm) {
    const w = parseInt(paperWidthMm, 10);
    if (isNaN(w) || w <= 0) return 53;
    if (w <= 52) return 44;
    if (w <= 65) return 53; // 58 mm roll -> 53 mm content width
    if (w <= 90) return 72; // 80 mm roll -> 72 mm content width
    if (w >= 180) return 195; // 210 mm A4 sheet -> 195 mm content width
    return Math.min(w, Math.max(30, Math.round(w * 0.9)));
}

let posPrinterSettings = {
    printerId: 'jk_5802h',
    printerName: 'JK-5802H 58mm Thermal (USB / Bluetooth / COM)',
    printEngine: 'browser',
    printerType: 'thermal',
    paperWidthMm: 58,
    printContentWidthMm: 53,
    printColumns: 32,
    hardwareFont: 'font_a',
    thermalDensity: 120,
    thermalFontName: 'Courier New',
    thermalMinFontSize: 8,
    thermalDefaultFontSize: 10.5,
    thermalLineHeight: 1.25,
    thermalBoldImportant: true,
    autoCashDrawer: true,
    autoCutter: true,
    buzzerBeep: false,
    a4Orientation: 'portrait',
    printWidthA4Mm: 80,
    copies: 1
};

function getPOSPrintSettings() {
    try {
        const saved = localStorage.getItem('pos_printer_settings');
        if (saved) {
            const parsed = JSON.parse(saved);
            posPrinterSettings = Object.assign(posPrinterSettings, parsed);
        }
    } catch (e) {
        console.warn('Could not read saved POS printer settings', e);
    }
    
    // Normalization & Defaults
    if (!posPrinterSettings.paperWidthMm || isNaN(parseInt(posPrinterSettings.paperWidthMm, 10))) {
        posPrinterSettings.paperWidthMm = 58;
    }
    if (!posPrinterSettings.printContentWidthMm || isNaN(parseFloat(posPrinterSettings.printContentWidthMm))) {
        posPrinterSettings.printContentWidthMm = getRecommendedContentWidth(posPrinterSettings.paperWidthMm);
    }
    if (!posPrinterSettings.printColumns || isNaN(parseInt(posPrinterSettings.printColumns, 10))) {
        posPrinterSettings.printColumns = (posPrinterSettings.paperWidthMm === 80) ? 48 : 32;
    }
    if (!posPrinterSettings.printEngine) {
        posPrinterSettings.printEngine = 'browser';
    }
    if (!posPrinterSettings.hardwareFont) {
        posPrinterSettings.hardwareFont = 'font_a';
    }
    if (!posPrinterSettings.thermalDensity) {
        posPrinterSettings.thermalDensity = 120;
    }
    if (!posPrinterSettings.thermalDefaultFontSize || isNaN(parseFloat(posPrinterSettings.thermalDefaultFontSize))) {
        posPrinterSettings.thermalDefaultFontSize = (posPrinterSettings.paperWidthMm === 80) ? 11.5 : 10.5;
    }
    if (!posPrinterSettings.thermalLineHeight || isNaN(parseFloat(posPrinterSettings.thermalLineHeight))) {
        posPrinterSettings.thermalLineHeight = 1.25;
    }
    if (!posPrinterSettings.thermalFontName || typeof posPrinterSettings.thermalFontName !== 'string' || !posPrinterSettings.thermalFontName.trim()) {
        posPrinterSettings.thermalFontName = 'Courier New';
    }
    return posPrinterSettings;
}

function setPaperPreset(preset) {
    const s = getPOSPrintSettings();
    const pBtn58 = document.getElementById('posPresetBtn58');
    const pBtn80 = document.getElementById('posPresetBtn80');
    const pBtnA4 = document.getElementById('posPresetBtnA4');

    if (pBtn58) { pBtn58.style.borderColor = '#cbd5e1'; pBtn58.style.background = '#ffffff'; }
    if (pBtn80) { pBtn80.style.borderColor = '#cbd5e1'; pBtn80.style.background = '#ffffff'; }
    if (pBtnA4) { pBtnA4.style.borderColor = '#cbd5e1'; pBtnA4.style.background = '#ffffff'; }

    let paperW = 58, contentW = 53, cols = 32, fontPt = 10.5, lineH = 1.25;

    if (preset === '80') {
        paperW = 80; contentW = 72; cols = 48; fontPt = 11.5; lineH = 1.25;
        if (pBtn80) { pBtn80.style.borderColor = '#0284c7'; pBtn80.style.background = '#f0f9ff'; }
    } else if (preset === 'a4') {
        paperW = 210; contentW = 195; cols = 80; fontPt = 12; lineH = 1.30;
        if (pBtnA4) { pBtnA4.style.borderColor = '#0284c7'; pBtnA4.style.background = '#f0f9ff'; }
    } else {
        paperW = 58; contentW = 53; cols = 32; fontPt = 10.5; lineH = 1.25;
        if (pBtn58) { pBtn58.style.borderColor = '#0284c7'; pBtn58.style.background = '#f0f9ff'; }
    }

    const paperInput = document.getElementById('posPaperWidth');
    if (paperInput) paperInput.value = paperW;

    const contentInput = document.getElementById('posPrintContentWidth');
    if (contentInput) contentInput.value = contentW;

    const colsInput = document.getElementById('posPrintColumns');
    if (colsInput) colsInput.value = cols;

    setThermalFontSize(fontPt);
    setThermalLineHeight(lineH);

    updateCompatibilityBadge();
}

function handlePrintEngineChange(engine) {
    const cardBrowser = document.getElementById('posEngineCardBrowser');
    const cardEscpos = document.getElementById('posEngineCardEscpos');
    const badge = document.getElementById('posEngineBadge');

    if (engine === 'escpos') {
        if (cardEscpos) { cardEscpos.style.borderColor = '#059669'; cardEscpos.style.background = '#ecfdf5'; }
        if (cardBrowser) { cardBrowser.style.borderColor = '#cbd5e1'; cardBrowser.style.background = '#f8fafc'; }
        if (badge) {
            badge.innerText = '⚡ ESC/POS Stream Mode';
            badge.style.color = '#065f46';
            badge.style.background = '#d1fae5';
            badge.style.borderColor = '#6ee7b7';
        }
    } else {
        if (cardBrowser) { cardBrowser.style.borderColor = '#0284c7'; cardBrowser.style.background = '#f0f9ff'; }
        if (cardEscpos) { cardEscpos.style.borderColor = '#cbd5e1'; cardEscpos.style.background = '#f8fafc'; }
        if (badge) {
            badge.innerText = '🌐 Browser Dialog Mode';
            badge.style.color = '#075985';
            badge.style.background = '#e0f2fe';
            badge.style.borderColor = '#7dd3fc';
        }
    }
    updateCompatibilityBadge();
}

function handlePrinterSelectChange() {
    const select = document.getElementById('posPrinterSelect');
    const val = select ? select.value : 'jk_5802h';

    if (val === 'epson_tmt20' || val === 'xprinter_80' || val === 'generic_80' || val === 'network_escpos') {
        setPaperPreset('80');
    } else if (val === 'generic_a4') {
        setPaperPreset('a4');
    } else if (val === 'jk_5802h' || val === 'xprinter_58' || val === 'generic_58') {
        setPaperPreset('58');
    }

    const activePrinterEl = document.getElementById('posActivePrinterVal');
    if (activePrinterEl && select && select.selectedOptions[0]) {
        activePrinterEl.innerText = select.selectedOptions[0].text.split('(')[0].trim();
    }
    updateCompatibilityBadge();
}

function handleHardwareFontChange() {
    updateCompatibilityBadge();
}

function handleThermalDensityChange() {
    updateCompatibilityBadge();
}

function handleThermalFontChange() {
    const fontNameInput = document.getElementById('posThermalFontName');
    const fontName = (fontNameInput && fontNameInput.value.trim()) ? fontNameInput.value.trim() : 'Courier New';
    const activeFontNameEl = document.getElementById('posActiveFontNameVal');
    const afEl = document.getElementById('af');
    if (activeFontNameEl) activeFontNameEl.innerText = fontName;
    if (afEl) afEl.innerText = fontName;
    updateCompatibilityBadge();
}

function handlePrintContentWidthInput(val) {
    let contentW = parseFloat(val);
    if (isNaN(contentW)) contentW = 53;
    contentW = Math.min(210, Math.max(35, Math.round(contentW)));
    const activeContentWidthEl = document.getElementById('posActiveContentWidthVal');
    if (activeContentWidthEl) activeContentWidthEl.innerText = contentW + ' mm';
    updateCompatibilityBadge();
}

function handlePaperWidthChange(val) {
    let paperW = parseInt(val, 10);
    if (isNaN(paperW) || paperW < 40) paperW = 58;
    const contentInput = document.getElementById('posPrintContentWidth');
    if (contentInput) {
        contentInput.value = getRecommendedContentWidth(paperW);
    }
    const colsInput = document.getElementById('posPrintColumns');
    if (colsInput) {
        colsInput.value = (paperW >= 180) ? 80 : ((paperW === 80) ? 48 : 32);
    }
    updateCompatibilityBadge();
}

function adjustThermalFontSize(delta) {
    const input = document.getElementById('posThermalCustomFontSize');
    let current = parseFloat(input?.value) || 10.5;
    let newVal = Math.max(8, Math.min(24, Math.round((current + delta) * 10) / 10));
    if (input) input.value = newVal;
    syncFontSizeFromCustom(newVal);
}

function setThermalFontSize(pt) {
    const v = Math.max(8, Math.min(24, parseFloat(pt) || 10.5));
    const input = document.getElementById('posThermalCustomFontSize');
    if (input) input.value = v;
    syncFontSizeFromCustom(v);
}

function syncFontSizeFromCustom(val) {
    const pt = Math.max(8, Math.min(24, parseFloat(val) || 10.5));
    const badge = document.getElementById('posThermalFontSizeBadge');
    const activeFontVal = document.getElementById('posActiveFontVal');
    const asEl = document.getElementById('as');
    if (badge) badge.innerText = pt + ' pt';
    if (activeFontVal) activeFontVal.innerText = pt + ' pt';
    if (asEl) asEl.innerText = pt + ' pt';

    // Highlight active quick button
    document.querySelectorAll('#posPrinterModal button').forEach(btn => {
        const oc = btn.getAttribute('onclick');
        if (oc && oc.includes('setThermalFontSize')) {
            const btnVal = parseFloat(oc.replace(/[^0-9.]/g, ''));
            if (btnVal === pt) {
                btn.className = 'btn btn-xs btn-primary';
            } else {
                btn.className = 'btn btn-xs btn-default';
            }
        }
    });
    updateCompatibilityBadge();
}

function adjustThermalLineHeight(delta) {
    const input = document.getElementById('posThermalLineHeight');
    let current = parseFloat(input?.value) || 1.25;
    let newVal = Math.max(1.0, Math.min(2.0, Math.round((current + delta) * 100) / 100));
    if (input) input.value = newVal.toFixed(2);
    syncLineHeightFromCustom(newVal);
}

function setThermalLineHeight(lh) {
    const v = Math.max(1.0, Math.min(2.0, parseFloat(lh) || 1.25));
    const input = document.getElementById('posThermalLineHeight');
    if (input) input.value = (Math.round(v * 100) / 100).toFixed(2);
    syncLineHeightFromCustom(v);
}

function syncLineHeightFromCustom(val) {
    const lh = Math.max(1.0, Math.min(2.0, parseFloat(val) || 1.25));
    const formattedLh = (Math.round(lh * 100) / 100).toFixed(2);
    const badge = document.getElementById('posThermalLineHeightBadge');
    const activeLhVal = document.getElementById('posActiveLineHeightVal');
    if (badge) badge.innerText = formattedLh;
    if (activeLhVal) activeLhVal.innerText = formattedLh;

    // Highlight active quick button
    document.querySelectorAll('#posPrinterModal button').forEach(btn => {
        const oc = btn.getAttribute('onclick');
        if (oc && oc.includes('setThermalLineHeight')) {
            const btnVal = parseFloat(oc.replace(/[^0-9.]/g, ''));
            if (Math.abs(btnVal - lh) < 0.01) {
                btn.className = 'btn btn-xs btn-primary';
            } else {
                btn.className = 'btn btn-xs btn-default';
            }
        }
    });
    updateCompatibilityBadge();
}

function adjustPrintCopies(delta) {
    const input = document.getElementById('posPrintCopies');
    let current = parseInt(input?.value, 10) || 1;
    let newVal = Math.max(1, Math.min(5, current + (delta || 0)));
    if (input) input.value = newVal;
    updateCompatibilityBadge();
}

function savePOSPrinterSettings() {
    posPrinterSettings.printerId = document.getElementById('posPrinterSelect')?.value || 'jk_5802h';
    const selectedOption = document.getElementById('posPrinterSelect')?.selectedOptions[0];
    posPrinterSettings.printerName = selectedOption ? selectedOption.text : 'JK-5802H 58mm Thermal';
    posPrinterSettings.printEngine = document.querySelector('input[name="posPrintEngine"]:checked')?.value || 'browser';
    posPrinterSettings.printerType = 'thermal';

    // Paper width
    const paperWidthInput = document.getElementById('posPaperWidth');
    let paperVal = parseInt(paperWidthInput?.value, 10) || 58;
    posPrinterSettings.paperWidthMm = Math.min(210, Math.max(40, paperVal));

    // Content width
    const contentWidthInput = document.getElementById('posPrintContentWidth');
    let contentVal = parseFloat(contentWidthInput?.value) || 53;
    posPrinterSettings.printContentWidthMm = Math.min(posPrinterSettings.paperWidthMm, Math.max(35, Math.round(contentVal)));

    // Columns
    const colsInput = document.getElementById('posPrintColumns');
    posPrinterSettings.printColumns = parseInt(colsInput?.value, 10) || (posPrinterSettings.paperWidthMm === 80 ? 48 : 32);

    // Hardware font & Density
    posPrinterSettings.hardwareFont = document.getElementById('posHardwareFont')?.value || 'font_a';
    posPrinterSettings.thermalDensity = parseInt(document.getElementById('posThermalDensity')?.value, 10) || 120;

    // Font Name
    const fontNameInput = document.getElementById('posThermalFontName');
    posPrinterSettings.thermalFontName = (fontNameInput && fontNameInput.value.trim()) ? fontNameInput.value.trim() : 'Courier New';

    // Font Size
    const customSizeInput = document.getElementById('posThermalCustomFontSize');
    let fontSize = parseFloat(customSizeInput?.value) || 10.5;
    posPrinterSettings.thermalDefaultFontSize = Math.max(8, Math.min(24, fontSize));

    // Line Height
    const lineHeightInput = document.getElementById('posThermalLineHeight');
    let lineHeight = parseFloat(lineHeightInput?.value) || 1.25;
    posPrinterSettings.thermalLineHeight = Math.max(1.0, Math.min(2.0, Math.round(lineHeight * 100) / 100));

    // Peripherals
    posPrinterSettings.autoCashDrawer = document.getElementById('posAutoCashDrawer')?.checked ?? true;
    posPrinterSettings.autoCutter = document.getElementById('posAutoCutter')?.checked ?? true;
    posPrinterSettings.buzzerBeep = document.getElementById('posBuzzerBeep')?.checked ?? false;
    posPrinterSettings.copies = parseInt(document.getElementById('posPrintCopies')?.value, 10) || 1;

    try {
        localStorage.setItem('pos_printer_settings', JSON.stringify(posPrinterSettings));
        localStorage.setItem('pos_printer_font_size', String(posPrinterSettings.thermalDefaultFontSize));
        localStorage.setItem('pos_printer_line_height', String(posPrinterSettings.thermalLineHeight));
        localStorage.setItem('pos_printer_content_width', String(posPrinterSettings.printContentWidthMm));
        localStorage.setItem('pos_printer_font_name', posPrinterSettings.thermalFontName);
        localStorage.setItem('pos_preferred_thermal_format', String(posPrinterSettings.paperWidthMm));
    } catch (e) {
        console.warn('Could not persist POS printer settings', e);
    }

    updatePOSPrinterBadge();
    syncModalPaperSizeSelects();
    $('#posPrinterModal').modal('hide');
    
    if (window.POVoucherRenderer && typeof window.POVoucherRenderer.showToast === 'function') {
        window.POVoucherRenderer.showToast('💾 Printer settings saved successfully!', 'success');
    }
}

function updatePOSPrinterBadge() {
    const s = getPOSPrintSettings();
    const btnLabel = document.getElementById('posPrinterBtnLabel');
    const btn = document.getElementById('posPrinterStatusBtn');
    const modeLabel = (s.printEngine === 'escpos') ? 'ESC/POS' : 'Thermal';
    if (btnLabel) {
        btnLabel.innerText = 'Printer Setup';
    }
    if (btn) {
        btn.title = 'Printer Setup (' + (s.printerName || 'Universal') + ' • ' + (s.paperWidthMm || 58) + 'mm • ' + modeLabel + ')';
    }
}

function syncModalPaperSizeSelects() {
    const s = getPOSPrintSettings();
    const effectiveThermalWidth = s.paperWidthMm || 58;
    const effectiveContentWidth = s.printContentWidthMm || 53;
    const summaryModes = document.querySelectorAll('.receipt-summary-mode');
    summaryModes.forEach(el => {
        el.innerText = (s.printEngine === 'escpos') ? 'ESC/POS Direct Stream' : 'Thermal Print Dialog';
    });
    const summaryWidths = document.querySelectorAll('.receipt-summary-width');
    summaryWidths.forEach(el => {
        el.innerText = effectiveContentWidth + ' mm';
    });
    const summaryPapers = document.querySelectorAll('.receipt-summary-paper');
    summaryPapers.forEach(el => {
        el.innerText = effectiveThermalWidth + ' mm Roll';
    });
    const summaryFonts = document.querySelectorAll('.receipt-summary-font');
    summaryFonts.forEach(el => {
        el.innerText = (s.thermalFontName || 'Courier New') + ' ' + (s.thermalDefaultFontSize || 10.5) + 'pt';
    });
}

function updatePOVoucherModalPreview(val) {
    const area = document.getElementById('posPrintPOArea');
    if (!area || !window.posPOSuccessData || !window.POVoucherRenderer) return;
    
    let fmt = val || (getPOSPrintSettings().paperWidthMm ? String(getPOSPrintSettings().paperWidthMm) : '58');
    area.style.width = (fmt === '80' ? '72mm' : (fmt === 'a4' ? '195mm' : '53mm'));
    area.style.maxWidth = (fmt === '80' ? '72mm' : (fmt === 'a4' ? '195mm' : '53mm'));
    area.style.padding = '10px 4px';
    area.innerHTML = window.POVoucherRenderer.render(window.posPOSuccessData, fmt);
}

function handleModalPaperSizeChange(val) {
    const s = getPOSPrintSettings();
    if (val === '80') {
        s.paperWidthMm = 80;
        s.printContentWidthMm = 72;
    } else if (val === 'pdf' || val === 'a4') {
        s.paperWidthMm = 210;
        s.printContentWidthMm = 195;
    } else {
        s.paperWidthMm = 58;
        s.printContentWidthMm = 53;
    }
    localStorage.setItem('pos_printer_settings', JSON.stringify(s));
    updatePOSPrinterBadge();
    syncModalPaperSizeSelects();
    updatePOVoucherModalPreview(val);
}

function openPOSPrinterModal() {
    const s = getPOSPrintSettings();

    const select = document.getElementById('posPrinterSelect');
    if (select && s.printerId) {
        select.value = s.printerId;
    }

    if (s.printEngine === 'escpos') {
        const rEsc = document.getElementById('posEngineEscpos');
        if (rEsc) rEsc.checked = true;
        handlePrintEngineChange('escpos');
    } else {
        const rBro = document.getElementById('posEngineBrowser');
        if (rBro) rBro.checked = true;
        handlePrintEngineChange('browser');
    }

    const paperWidthInput = document.getElementById('posPaperWidth');
    if (paperWidthInput) paperWidthInput.value = s.paperWidthMm || 58;

    const contentWidthInput = document.getElementById('posPrintContentWidth');
    if (contentWidthInput) contentWidthInput.value = s.printContentWidthMm || 53;

    const colsInput = document.getElementById('posPrintColumns');
    if (colsInput) colsInput.value = s.printColumns || (s.paperWidthMm === 80 ? 48 : 32);

    const hwFont = document.getElementById('posHardwareFont');
    if (hwFont) hwFont.value = s.hardwareFont || 'font_a';

    const dens = document.getElementById('posThermalDensity');
    if (dens) dens.value = s.thermalDensity || 120;

    const fontNameInput = document.getElementById('posThermalFontName');
    if (fontNameInput) fontNameInput.value = s.thermalFontName || 'Courier New';

    const customSizeInput = document.getElementById('posThermalCustomFontSize');
    if (customSizeInput) {
        customSizeInput.value = s.thermalDefaultFontSize || 10.5;
        syncFontSizeFromCustom(customSizeInput.value);
    }

    const lineHeightInput = document.getElementById('posThermalLineHeight');
    if (lineHeightInput) {
        lineHeightInput.value = (s.thermalLineHeight || 1.25).toFixed(2);
        syncLineHeightFromCustom(lineHeightInput.value);
    }

    const drawerChk = document.getElementById('posAutoCashDrawer');
    if (drawerChk) drawerChk.checked = (s.autoCashDrawer !== false);

    const cutChk = document.getElementById('posAutoCutter');
    if (cutChk) cutChk.checked = (s.autoCutter !== false);

    const buzzChk = document.getElementById('posBuzzerBeep');
    if (buzzChk) buzzChk.checked = (s.buzzerBeep === true);

    const copiesInput = document.getElementById('posPrintCopies');
    if (copiesInput) copiesInput.value = s.copies || 1;

    updateCompatibilityBadge();
    $('#posPrinterModal').modal('show');
    initPOSPrinterDetection();
}

function refreshPOSPrinters() {
    initPOSPrinterDetection(true);
}

function updateCompatibilityBadge() {
    const s = getPOSPrintSettings();

    const paperInput = document.getElementById('posPaperWidth');
    let paperWidthMm = paperInput ? parseInt(paperInput.value, 10) : (s.paperWidthMm || 58);

    const contentInput = document.getElementById('posPrintContentWidth');
    let contentWidthMm = contentInput ? parseFloat(contentInput.value) : (s.printContentWidthMm || 53);
    if (isNaN(contentWidthMm) || contentWidthMm <= 0) contentWidthMm = 53;

    const select = document.getElementById('posPrinterSelect');
    const selectedText = select?.selectedOptions[0]?.text || s.printerName || 'JK-5802H 58mm';
    const shortName = selectedText.split('(')[0].trim();

    const fontNameInput = document.getElementById('posThermalFontName');
    const fontName = (fontNameInput && fontNameInput.value.trim()) ? fontNameInput.value.trim() : (s.thermalFontName || 'Courier New');

    const customSizeInput = document.getElementById('posThermalCustomFontSize');
    let fontPt = customSizeInput ? parseFloat(customSizeInput.value) : (s.thermalDefaultFontSize || 10.5);

    const densitySelect = document.getElementById('posThermalDensity');
    let densityVal = densitySelect ? densitySelect.value : (s.thermalDensity || 120);

    const activePrinterEl = document.getElementById('posActivePrinterVal');
    if (activePrinterEl) activePrinterEl.innerText = shortName;

    const activeWidthEl = document.getElementById('posActiveWidthVal');
    if (activeWidthEl) activeWidthEl.innerText = paperWidthMm + 'mm (' + Math.round(contentWidthMm) + 'mm)';

    const activeFontNameEl = document.getElementById('posActiveFontNameVal');
    if (activeFontNameEl) activeFontNameEl.innerText = fontName.substring(0, 10);

    const activeFontEl = document.getElementById('posActiveFontVal');
    if (activeFontEl) activeFontEl.innerText = fontPt + ' pt';

    const activeDensityEl = document.getElementById('posActiveDensityVal');
    if (activeDensityEl) activeDensityEl.innerText = densityVal + '% Dark';

    const fontBadge = document.getElementById('posThermalFontSizeBadge');
    if (fontBadge) fontBadge.innerText = fontPt + ' pt';

    const lineHeightInput = document.getElementById('posThermalLineHeight');
    let lineHeight = lineHeightInput ? parseFloat(lineHeightInput.value) : (s.thermalLineHeight || 1.25);
    const lineHeightBadge = document.getElementById('posThermalLineHeightBadge');
    if (lineHeightBadge) lineHeightBadge.innerText = (Math.round(lineHeight * 100) / 100).toFixed(2);
}

function testPrintActiveThermalProfile() {
    const s = getPOSPrintSettings();
    const contentWidthInput = document.getElementById('posPrintContentWidth');
    const contentWidthMm = contentWidthInput ? (parseFloat(contentWidthInput.value) || 53) : (s.printContentWidthMm || 53);
    const fontNameInput = document.getElementById('posThermalFontName');
    const fontName = fontNameInput ? (fontNameInput.value.trim() || 'Courier New') : (s.thermalFontName || 'Courier New');
    const customSizeInput = document.getElementById('posThermalCustomFontSize');
    const fontSizePt = customSizeInput ? (parseFloat(customSizeInput.value) || 10.5) : (s.thermalDefaultFontSize || 10.5);
    const lineHeightInput = document.getElementById('posThermalLineHeight');
    const lineHeight = lineHeightInput ? (parseFloat(lineHeightInput.value) || 1.25) : (s.thermalLineHeight || 1.25);
    const engine = document.querySelector('input[name="posPrintEngine"]:checked')?.value || s.printEngine || 'browser';
    const paperWidth = parseInt(document.getElementById('posPaperWidth')?.value || s.paperWidthMm || 58, 10);
    const columns = parseInt(document.getElementById('posPrintColumns')?.value || s.printColumns || (paperWidth === 80 ? 48 : 32), 10);
    const hardwareFont = document.getElementById('posHardwareFont')?.value || s.hardwareFont || 'font_a';
    const density = parseInt(document.getElementById('posThermalDensity')?.value || s.thermalDensity || 120, 10);

    const now = new Date();
    const dateStr = now.toLocaleDateString('en-CA') + ' ' + now.toLocaleTimeString('en-US', { hour12: false });
    const receiptNum = 'TEST-' + now.toISOString().slice(0, 10).replace(/-/g, '') + '-01';

    const sampleReceiptData = {
        supplier_name: 'SAM & INRI CONSTRUCTION SUPPLY',
        supplier_phone: '09612735733',
        payment_id: receiptNum,
        payment_date: dateStr,
        customer_name: 'POS System Self-Test',
        payment_method: 'Cash / Alignment Test',
        payment_status: 'PAID',
        cashier_name: 'Hardware Diagnostic',
        items: [
            { name: 'Portland Cement 40kg Type 1', qty: 2, price: 270.00, line_net: 540.00 },
            { name: '1/2" Blue PVC Electrical Pipe 3m', qty: 5, price: 85.00, line_net: 425.00 }
        ],
        gross_subtotal: 965.00,
        subtotal: 965.00,
        delivery_cost: 0.00,
        total_discount_savings: 0.00,
        grand_total: 965.00,
        amount_tendered: 1000.00,
        change_amount: 35.00
    };

    const activeOpts = {
        printerId: document.getElementById('posPrinterSelect')?.value || s.printerId,
        printerName: document.getElementById('posPrinterSelect')?.selectedOptions[0]?.text || s.printerName,
        printEngine: engine,
        paperWidthMm: paperWidth,
        printContentWidthMm: contentWidthMm,
        printColumns: columns,
        hardwareFont: hardwareFont,
        thermalDensity: density,
        thermalFontName: fontName,
        thermalDefaultFontSize: fontSizePt,
        thermalLineHeight: lineHeight,
        autoCashDrawer: document.getElementById('posAutoCashDrawer')?.checked ?? true,
        autoCutter: document.getElementById('posAutoCutter')?.checked ?? true,
        buzzerBeep: document.getElementById('posBuzzerBeep')?.checked ?? false,
        copies: 1
    };

    if (window.POVoucherRenderer && typeof window.POVoucherRenderer.printReceiptData === 'function') {
        window.POVoucherRenderer.printReceiptData(sampleReceiptData, String(paperWidth), activeOpts);
    } else {
        testPrintThermalPaidOrder(paperWidth, contentWidthMm);
    }
}

function initPOSPrinterDetection(forceRefresh = false) {
    const statusBox = document.getElementById('posPrinterStatusText');
    const select = document.getElementById('posPrinterSelect');

    if (statusBox) {
        statusBox.innerHTML = '<i class="fa fa-circle-o-notch fa-spin text-info"></i> Probing connected USB printers &amp; ESC/POS print services...';
    }

    posDetectedPrinters = [];

    // Probe 1: Local ESC/POS Daemon (localhost:9100)
    const probeLocalAgent = new Promise((resolve) => {
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 1200);

        fetch('http://127.0.0.1:9100/status', { signal: controller.signal, mode: 'no-cors' })
            .then(() => {
                clearTimeout(timeoutId);
                posDetectedPrinters.push({
                    id: 'local_escpos_9100',
                    name: 'Local ESC/POS Thermal Agent (127.0.0.1:9100)',
                    type: 'thermal',
                    connection: 'Direct ESC/POS Daemon',
                    paperWidth: 58
                });
                resolve();
            })
            .catch(() => {
                clearTimeout(timeoutId);
                resolve();
            });
    });

    // Probe 2: WebUSB Connected Hardware
    const probeWebUSB = new Promise((resolve) => {
        if (navigator.usb && typeof navigator.usb.getDevices === 'function') {
            navigator.usb.getDevices()
                .then((devices) => {
                    devices.forEach((d) => {
                        posDetectedPrinters.push({
                            id: 'usb_' + (d.vendorId || 'dev'),
                            name: (d.productName || 'USB Thermal POS Device') + ' (USB Direct)',
                            type: 'thermal',
                            connection: 'USB Hardware',
                            paperWidth: 58
                        });
                    });
                    resolve();
                })
                .catch(() => resolve());
        } else {
            resolve();
        }
    });

    // Probe 3: Chromium Local Printers API (if flags enabled)
    const probeChromiumPrinters = new Promise((resolve) => {
        if (typeof window.queryLocalPrinters === 'function') {
            window.queryLocalPrinters()
                .then((printers) => {
                    printers.forEach((p) => {
                        const isThermal = /pos|thermal|receipt|epson|star|tm-|jk-|xp-/i.test(p.name);
                        posDetectedPrinters.push({
                            id: 'sys_' + p.name.replace(/[^a-zA-Z0-9]/g, '_'),
                            name: p.name + (p.isDefault ? ' (System Default)' : ''),
                            type: isThermal ? 'thermal' : 'normal',
                            connection: 'System Spooler',
                            paperWidth: isThermal ? 58 : 210,
                            isDefault: p.isDefault
                        });
                    });
                    resolve();
                })
                .catch(() => resolve());
        } else {
            resolve();
        }
    });

    Promise.all([probeLocalAgent, probeWebUSB, probeChromiumPrinters]).then(() => {
        if (select) {
            const detectedGroup = document.getElementById('posDetectedGroup');
            if (detectedGroup) {
                detectedGroup.innerHTML = '<option value="system_default">AUTO DETECT (System Default / OS Print Spooler)</option>';
                if (posDetectedPrinters.length > 0) {
                    posDetectedPrinters.forEach((p) => {
                        const opt = document.createElement('option');
                        opt.value = p.id;
                        opt.text = p.name + ' [' + p.connection + ']';
                        detectedGroup.appendChild(opt);
                    });
                }
            }
            if (posPrinterSettings.printerId) {
                select.value = posPrinterSettings.printerId;
            }
        }

        if (statusBox) {
            if (posDetectedPrinters.length > 0) {
                statusBox.innerHTML = '<span style="color: #16a34a;"><i class="fa fa-check-circle"></i> ' + posDetectedPrinters.length + ' printer/service(s) connected via direct hardware bridge.</span>';
            } else {
                statusBox.innerHTML = '<span style="color: #0284c7;"><i class="fa fa-info-circle"></i> Auto Detect Active &bull; Ready (JK-5802H / Windows Driver / Browser Print).</span>';
            }
        }
    });
}

function generatePOSPrintHTML(contentHtml, docTitle = 'POS Print Document', docType = 'receipt', requestedFormat = null) {
    const s = getPOSPrintSettings();
    let defaultWidth = s.paperWidthMm || 58;
    let paperWidthMm = defaultWidth;
    let contentWidthMm = s.printContentWidthMm || getRecommendedContentWidth(defaultWidth);
    let isA4 = false;
    let isPdfPreview = false;

    let effectiveFormat = requestedFormat;
    if (!effectiveFormat) {
        if (docType === 'po') {
            const poModalSel = document.getElementById('posPOModalPaperSize');
            if (poModalSel && poModalSel.value) effectiveFormat = poModalSel.value;
        } else if (docType === 'receipt') {
            const recModalSel = document.getElementById('posReceiptModalPaperSize');
            if (recModalSel && recModalSel.value) effectiveFormat = recModalSel.value;
        } else if (docType === 'return') {
            const retModalSel = document.getElementById('posReturnModalPaperSize');
            if (retModalSel && retModalSel.value) effectiveFormat = retModalSel.value;
        }
    }

    if (effectiveFormat === 'pdfA4' || effectiveFormat === 'a4') {
        paperWidthMm = 210;
        contentWidthMm = getA4PrintWidthMm();
        isA4 = true;
        isPdfPreview = true;
    } else if (effectiveFormat === 'pdf' || effectiveFormat === 'pdf200' || effectiveFormat === 'pdf500') {
        isPdfPreview = true;
        if (effectiveFormat === 'pdf200' || effectiveFormat === 'pdf500') {
            paperWidthMm = 210;
            contentWidthMm = 195;
        } else {
            paperWidthMm = defaultWidth;
            contentWidthMm = (paperWidthMm === 58) ? 53 : ((paperWidthMm === 80) ? 72 : getRecommendedContentWidth(paperWidthMm));
        }
    } else if (effectiveFormat === '58' || effectiveFormat === 'thermal58') {
        paperWidthMm = 58;
        contentWidthMm = 53;
        isA4 = false;
    } else if (effectiveFormat === '80' || effectiveFormat === 'thermal80' || effectiveFormat === 'thermal') {
        paperWidthMm = 80;
        contentWidthMm = 72;
        isA4 = false;
    } else if (effectiveFormat === '50' || effectiveFormat === 'thermal50') {
        paperWidthMm = 50;
        contentWidthMm = 44;
        isA4 = false;
    } else if (effectiveFormat === '200' || effectiveFormat === 'thermal200' || effectiveFormat === '210' || effectiveFormat === 'thermal210') {
        paperWidthMm = 210;
        contentWidthMm = 195;
        isA4 = false;
    } else if (effectiveFormat && !isNaN(parseInt(effectiveFormat, 10))) {
        let reqW = parseInt(effectiveFormat, 10);
        if (reqW === 58) {
            paperWidthMm = 58;
            contentWidthMm = 53;
        } else if (reqW === 80) {
            paperWidthMm = 80;
            contentWidthMm = 72;
        } else if (reqW <= 52) {
            paperWidthMm = 50;
            contentWidthMm = 44;
        } else if (reqW >= 180) {
            paperWidthMm = 210;
            contentWidthMm = 195;
        } else {
            paperWidthMm = reqW;
            contentWidthMm = getRecommendedContentWidth(reqW);
        }
    } else {
        paperWidthMm = defaultWidth;
        contentWidthMm = (paperWidthMm === 58) ? 53 : ((paperWidthMm === 80) ? 72 : getRecommendedContentWidth(paperWidthMm));
    }

    const isThermal = !isA4;
    const is58mm = isThermal && (paperWidthMm <= 65);
    const is50mm = isThermal && (paperWidthMm <= 52);
    const is80mm = isThermal && (!is58mm && paperWidthMm <= 90);
    const is200mm = isThermal && (paperWidthMm >= 180);

    // Font stack: User-configured font name with high-contrast Monospace fallback for thermal rolls, Arial for A4
    const fontName = (s.thermalFontName && s.thermalFontName.trim()) ? s.thermalFontName.replace(/['"<>;]/g, '').trim() : 'Courier New';
    let fontStack = isThermal 
        ? `'${fontName}', 'Courier New', Consolas, 'Liberation Mono', monospace` 
        : "Arial, Helvetica, sans-serif";
    if (isThermal && s.thermalFontStrategy === 'monospace') {
        fontStack = `'${fontName}', 'Courier New', Courier, monospace`;
    } else if (isThermal && s.thermalFontStrategy === 'custom_ttf') {
        fontStack = `'${fontName}', 'Merchant Copy', 'Receipt Font', 'Courier New', monospace`;
    }

    // Dynamic Header & Body Font Size
    let headerFontSizePt = 12.0;
    let bodyFontSizePt = '10pt';
    let totalFontSizePt = '11pt';

    if (isThermal) {
        if (is58mm) {
            bodyFontSizePt = '10pt';
            headerFontSizePt = 12.0;
            totalFontSizePt = '11pt';
        } else if (is50mm) {
            bodyFontSizePt = '9pt';
            headerFontSizePt = 11.0;
            totalFontSizePt = '10pt';
        } else if (is80mm) {
            const defPt = Math.max(10, Number(s.thermalDefaultFontSize) || 12);
            bodyFontSizePt = defPt + 'pt';
            headerFontSizePt = Math.max(14.0, defPt + 2.5);
            totalFontSizePt = (defPt + 1.5) + 'pt';
        } else {
            const defPt = Math.max(10, Number(s.thermalDefaultFontSize) || 12);
            bodyFontSizePt = defPt + 'pt';
            headerFontSizePt = Math.max(15.0, defPt + 3.0);
            totalFontSizePt = (defPt + 2.0) + 'pt';
        }
    }

    // Dynamic height calculation for valid @page size
    let pageHeightMm = isA4 ? 297 : 260;
    const rowMatches = contentHtml.match(/<tr/gi);
    const rowCount = rowMatches ? rowMatches.length : 3;
    if (isA4 && rowCount > 10) {
        pageHeightMm = Math.max(297, 200 + (rowCount * 18));
    }

    // Build repeated copies if copies > 1
    let copiesHtml = '';
    for (let c = 0; c < (s.copies || 1); c++) {
        copiesHtml += '<div class="pos-print-page' + (c > 0 ? ' pos-page-break' : '') + '">' + contentHtml + '</div>';
    }

    // Container margin & padding:
    const containerMargin = 'margin: 0 auto !important;';
    const containerPadding = is200mm
        ? '4mm 6mm'
        : (is50mm
            ? '0 0.5mm'
            : (is58mm 
                ? '0 0.5mm' 
                : (isThermal ? '1mm 1.5mm' : '3mm 4mm')));

    let wrappedContent = `
        <div class="pos-print-container pos-receipt-container" style="width: ${contentWidthMm}mm; max-width: ${contentWidthMm}mm; ${containerMargin} padding: ${containerPadding}; text-align: left; box-sizing: border-box; overflow: hidden;">
            ${copiesHtml}
        </div>
    `;

    return `<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>${docTitle}</title>
    <style>
        * {
            box-sizing: border-box !important;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
            ${isThermal ? 'color: #000000 !important; text-shadow: none !important;' : ''}
        }
        ${(isThermal && s.thermalBoldImportant !== false) ? 'strong, b, th, .pos-receipt-total, .receipt-total-val, .pos-company-header { font-weight: 800 !important; }' : ''}
        @page {
            size: ${isA4 ? '210mm ' + pageHeightMm + 'mm' : paperWidthMm + 'mm auto'};
            margin: 0;
        }
        html, body {
            margin: 0 !important;
            padding: 0 !important;
            background: #ffffff !important;
            font-family: ${fontStack} !important;
            font-size: ${bodyFontSizePt} !important;
            line-height: ${isThermal ? '1.2' : '1.25'} !important;
            color: #000000 !important;
            width: ${paperWidthMm}mm !important;
            height: auto !important;
            min-height: 0 !important;
            text-align: left !important;
            -webkit-font-smoothing: antialiased;
        }
        table {
            width: 100% !important;
            border-collapse: collapse !important;
            table-layout: fixed !important;
            font-family: ${fontStack} !important;
            font-size: ${bodyFontSizePt} !important;
            line-height: ${isThermal ? '1.2' : '1.25'} !important;
            color: #000000 !important;
            font-variant-numeric: tabular-nums !important;
        }
        th, td {
            vertical-align: top !important;
            font-family: ${fontStack} !important;
            word-break: break-word !important;
            overflow-wrap: break-word !important;
            color: #000000 !important;
            font-variant-numeric: tabular-nums !important;
        }
        
        .pos-company-header {
            font-family: ${fontStack} !important;
            font-size: ${headerFontSizePt}pt !important;
            font-weight: bold !important;
            text-transform: uppercase !important;
            white-space: ${is58mm ? 'normal' : 'nowrap'} !important;
            overflow: visible !important;
            text-align: ${isThermal ? 'center' : 'left'} !important;
            line-height: 1.2 !important;
            letter-spacing: ${isThermal ? '0' : '0.2px'} !important;
            color: #000000 !important;
            display: block !important;
        }

        .pos-page-break {
            page-break-before: always;
            margin-top: 15mm;
        }

        .pos-print-container, .pos-receipt-container {
            width: ${contentWidthMm}mm !important;
            max-width: ${contentWidthMm}mm !important;
            box-sizing: border-box !important;
            ${containerMargin}
            padding: ${containerPadding} !important;
            font-family: ${fontStack} !important;
            font-size: ${bodyFontSizePt} !important;
            line-height: ${isThermal ? '1.2' : '1.25'} !important;
            color: #000 !important;
            background: #fff !important;
            height: auto !important;
            min-height: 0 !important;
            text-align: left !important;
            overflow: hidden !important;
        }

        ${isThermal ? `
        .thermal-center {
            text-align: center !important;
        }
        .pos-receipt-container div, .pos-receipt-container span, .pos-receipt-container p, .pos-receipt-container strong {
            color: #000000 !important;
        }
        .pos-receipt-container tr {
            font-size: ${bodyFontSizePt} !important;
            border-color: #000000 !important;
        }
        .pos-receipt-container td, .pos-receipt-container th {
            font-size: ${bodyFontSizePt} !important;
            border-color: #000000 !important;
        }
        .pos-receipt-container .pos-total-row, .pos-receipt-container tr[style*="font-size: 12pt"], .pos-receipt-container tr[style*="font-size: 13pt"], .pos-receipt-container tr[style*="font-size: 14pt"], .pos-receipt-container tr[style*="font-size: 15pt"] {
            font-size: ${totalFontSizePt} !important;
            font-weight: bold !important;
        }
        .pos-receipt-container .pos-total-row td, .pos-receipt-container tr[style*="font-size: 12pt"] td, .pos-receipt-container tr[style*="font-size: 13pt"] td, .pos-receipt-container tr[style*="font-size: 14pt"] td, .pos-receipt-container tr[style*="font-size: 15pt"] td {
            font-size: ${totalFontSizePt} !important;
            font-weight: bold !important;
            border-top: 1pt dashed #000000 !important;
            border-bottom: 1pt dashed #000000 !important;
        }
        ${is58mm ? `
        .pos-receipt-container .col-unit-price {
            display: none !important;
        }
        .pos-receipt-container .col-item-desc {
            width: 54% !important;
        }
        .pos-receipt-container .col-qty {
            width: 16% !important;
            text-align: center !important;
        }
        .pos-receipt-container .col-amount {
            width: 30% !important;
            text-align: right !important;
        }
        .pos-receipt-container .item-unit-subprice {
            display: block !important;
            font-size: 8.2pt !important;
            line-height: 1.15 !important;
            color: #000000 !important;
        }
        .pos-receipt-container .col-foot-spacer {
            display: none !important;
        }
        .pos-receipt-container .col-foot-label {
            width: 70% !important;
            text-align: right !important;
        }
        ` : (is200mm ? `
        .pos-receipt-container .col-item-desc {
            width: 52% !important;
        }
        .pos-receipt-container .col-qty {
            width: 12% !important;
            text-align: center !important;
        }
        .pos-receipt-container .col-unit-price {
            width: 18% !important;
            text-align: right !important;
        }
        .pos-receipt-container .col-amount {
            width: 18% !important;
            text-align: right !important;
        }
        .pos-receipt-container .item-unit-subprice {
            display: none !important;
        }
        .pos-receipt-container .col-foot-spacer {
            width: 52% !important;
        }
        .pos-receipt-container .col-foot-label {
            width: 30% !important;
            text-align: right !important;
        }
        ` : `
        .pos-receipt-container .col-item-desc {
            width: 46% !important;
        }
        .pos-receipt-container .col-qty {
            width: 14% !important;
            text-align: center !important;
        }
        .pos-receipt-container .col-unit-price {
            width: 20% !important;
            text-align: right !important;
        }
        .pos-receipt-container .col-amount {
            width: 20% !important;
            text-align: right !important;
        }
        .pos-receipt-container .item-unit-subprice {
            display: none !important;
        }
        .pos-receipt-container .col-foot-spacer {
            width: 46% !important;
        }
        .pos-receipt-container .col-foot-label {
            width: 20% !important;
            text-align: right !important;
        }
        `)}
        ` : ''}

        @media screen {
            body {
                padding: 20px;
                background: #334155;
                display: flex;
                flex-direction: column;
                align-items: flex-start;
                min-height: 100vh;
                width: auto;
            }
            .pdf-preview-toolbar {
                width: 100%;
                max-width: ${Math.max(210, contentWidthMm)}mm;
                margin: 0 0 16px 0;
                background: #0f172a;
                color: #ffffff;
                padding: 10px 18px;
                border-radius: 6px;
                display: flex;
                justify-content: space-between;
                align-items: center;
                box-shadow: 0 4px 14px rgba(0,0,0,0.35);
                font-family: Arial, sans-serif;
            }
            .pos-print-container, .pos-receipt-container {
                width: ${contentWidthMm}mm !important;
                max-width: ${contentWidthMm}mm !important;
                background: #ffffff !important;
                padding: ${containerPadding} !important;
                box-shadow: 0 8px 30px rgba(0,0,0,0.3);
                border: 1px solid #cbd5e1;
                border-radius: 4px;
                height: auto;
                min-height: 0;
                text-align: left !important;
                ${containerMargin}
                overflow: hidden !important;
            }
        }

        @media print {
            @page {
                size: ${isA4 ? '210mm ' + pageHeightMm + 'mm' : paperWidthMm + 'mm auto'};
                margin: 0 !important;
            }
            .no-print, .pdf-preview-toolbar {
                display: none !important;
            }
            html, body {
                margin: 0 !important;
                padding: 0 !important;
                width: ${paperWidthMm}mm !important;
                background: #ffffff !important;
                font-family: ${fontStack} !important;
                font-size: ${bodyFontSizePt} !important;
                line-height: ${isThermal ? '1.2' : '1.25'} !important;
                color: #000000 !important;
                height: auto !important;
                min-height: 0 !important;
                text-align: left !important;
            }
            .pos-print-container, .pos-receipt-container, .thermal-receipt {
                width: ${contentWidthMm}mm !important;
                max-width: ${contentWidthMm}mm !important;
                margin: 0 auto !important;
                padding: 0 !important;
                box-sizing: border-box !important;
                box-shadow: none !important;
                border: none !important;
                border-radius: 0 !important;
                height: auto !important;
                min-height: 0 !important;
                text-align: left !important;
                overflow: hidden !important;
            }
            /* Neutralize screen modal inner border and padding */
            .pos-receipt-container > div,
            .pos-print-container > div,
            .pos-receipt-container div[style*="border"],
            .pos-receipt-container div[style*="padding"] {
                border: none !important;
                padding: 0 !important;
                margin: 0 !important;
                box-shadow: none !important;
                background: transparent !important;
                width: 100% !important;
            }
        }
    </style>
</head>
<body>
    ${wrappedContent}
</body>
</html>`;
}

function executePOSPrintJob(htmlContent, autoTriggerPrint = true) {
    let iframe = document.getElementById('pos-print-frame');
    if (iframe) {
        iframe.parentNode.removeChild(iframe);
    }
    iframe = document.createElement('iframe');
    iframe.id = 'pos-print-frame';
    iframe.style.position = 'fixed';
    iframe.style.right = '0';
    iframe.style.bottom = '0';
    iframe.style.width = '0';
    iframe.style.height = '0';
    iframe.style.border = '0';
    iframe.style.zIndex = '-9999';
    document.body.appendChild(iframe);

    const doc = iframe.contentWindow.document;
    doc.open();
    doc.write(htmlContent);
    doc.close();

    iframe.contentWindow.focus();
    if (autoTriggerPrint) {
        setTimeout(() => {
            try {
                iframe.contentWindow.print();
            } catch (e) {
                console.error('POS Print error:', e);
            }
        }, 250);
    }
}

function generatePaidOrderThermalHTML(orderData, requestedWidthMm, requestedContentWidthMm) {
    const s = getPOSPrintSettings();
    let paperWidthMm = requestedWidthMm || s.paperWidthMm || 58;
    if (requestedWidthMm) paperWidthMm = requestedWidthMm;
    if (paperWidthMm > MAX_THERMAL_WIDTH_MM) paperWidthMm = MAX_THERMAL_WIDTH_MM;
    if (paperWidthMm < 40) paperWidthMm = 40;

    let contentWidthMm = requestedContentWidthMm || (requestedWidthMm ? getRecommendedContentWidth(paperWidthMm) : s.printContentWidthMm);
    if (!contentWidthMm || isNaN(parseFloat(contentWidthMm))) {
        contentWidthMm = getRecommendedContentWidth(paperWidthMm);
    }
    // Strict rule: Content Width <= Paper Width
    contentWidthMm = Math.min(paperWidthMm, Math.max(30, Math.round(parseFloat(contentWidthMm))));

    const is58mm = (paperWidthMm <= 65);
    const is50mm = (paperWidthMm <= 52);

    // Thermal Font Selection
    const fontName = s.thermalFontName || 'Courier New';
    const fontStack = `'${fontName.replace(/'/g, "\\'")}', 'Courier New', Courier, monospace, 'Lucida Console', Arial, sans-serif`;

    const fontSizePt = is58mm ? 10 : (is50mm ? 9 : Math.max(10, parseFloat(s.thermalDefaultFontSize) || 12));
    const titleFontSizePt = is58mm ? 12.0 : (is50mm ? 11.0 : Math.max(13.0, fontSizePt + 1.0));
    const totalFontSizePt = is58mm ? 11.0 : (is50mm ? 10.0 : Math.max(13.0, fontSizePt + 1.0));

    const supplierName = orderData.supplier_name || 'SAM & INRI CONSTRUCTION SUPPLY';
    const supplierPhone = orderData.supplier_phone || '09612735733';
    const orderNo = orderData.payment_id || 'POS-20260925-01';
    const dateStr = orderData.payment_date || '';
    const customerName = orderData.customer_name || 'Walk-in Customer';
    const paymentMethod = orderData.payment_method || 'Cash';
    const paymentStatus = (orderData.payment_status || 'PAID').toUpperCase();

    const items = orderData.items || [];
    let itemsRows = '';

    const purchaseItems = items.filter(it => it.item_type !== 'RETURN_CREDIT' && (parseFloat(it.price || 0) >= 0));
    const returnItems = items.filter(it => it.item_type === 'RETURN_CREDIT' || (parseFloat(it.price || 0) < 0));

    if (purchaseItems.length > 0) {
        if (returnItems.length > 0) {
            itemsRows += `
                <tr>
                    <td colspan="2" style="text-align: left; padding: 2px 0; font-weight: bold; font-size: ${fontSizePt}pt; border-bottom: 1px dashed #000;">[PURCHASED ITEMS]</td>
                </tr>
            `;
        }
        purchaseItems.forEach(function(item) {
            const rawName = item.name || 'Item';
            const formattedName = escapeHtml(rawName).replace(/\n/g, '<br>');
            const itemQty = parseInt(item.qty, 10) || 1;
            const itemPrice = parseFloat(item.price || 0).toFixed(2);
            const itemAmount = parseFloat(item.amount || (parseFloat(item.price || 0) * itemQty)).toFixed(2);
            const unitLabel = itemQty > 1 ? 'pcs' : 'pc';
            
            itemsRows += `
                <tr>
                    <td colspan="2" style="text-align: left; padding-top: 3px; font-weight: bold; word-break: break-word;">${formattedName}</td>
                </tr>
                <tr>
                    <td style="text-align: left; padding-left: 8px; padding-bottom: 3px;">${itemQty} ${unitLabel} @ ${itemPrice}</td>
                    <td style="text-align: right; padding-bottom: 3px; white-space: nowrap; vertical-align: bottom;">${itemAmount}</td>
                </tr>
            `;
        });
    }

    if (returnItems.length > 0) {
        itemsRows += `
            <tr>
                <td colspan="2" style="text-align: left; padding-top: 6px; padding-bottom: 2px; font-weight: bold; font-size: ${fontSizePt}pt; border-bottom: 1px dashed #000;">[RETURN / EXCHANGE CREDIT]</td>
            </tr>
        `;
        returnItems.forEach(function(item) {
            const rawName = (item.name || 'Return Credit').replace(/^\[RETURN CREDIT\]\s*/i, '');
            const formattedName = escapeHtml(rawName).replace(/\n/g, '<br>');
            const itemQty = parseInt(item.qty, 10) || 1;
            const unitPrice = Math.abs(parseFloat(item.price || 0)).toFixed(2);
            const itemAmount = '-' + Math.abs(parseFloat(item.amount || (parseFloat(item.price || 0) * itemQty))).toFixed(2);
            const unitLabel = itemQty > 1 ? 'pcs' : 'pc';
            
            itemsRows += `
                <tr>
                    <td colspan="2" style="text-align: left; padding-top: 3px; font-weight: bold; word-break: break-word;">[RETURN] ${formattedName}</td>
                </tr>
                <tr>
                    <td style="text-align: left; padding-left: 8px; padding-bottom: 3px;">${itemQty} ${unitLabel} @ -${unitPrice}</td>
                    <td style="text-align: right; padding-bottom: 3px; white-space: nowrap; vertical-align: bottom;">${itemAmount}</td>
                </tr>
            `;
        });
    }

    const subtotal = parseFloat(orderData.subtotal || 0).toFixed(2);
    const delivery = parseFloat(orderData.delivery || 0).toFixed(2);
    const discount = parseFloat(orderData.discount || 0).toFixed(2);
    const returnCredits = parseFloat(orderData.return_credits || orderData.total_return_credits || 0).toFixed(2);
    const total = parseFloat(orderData.total || 0).toFixed(2);
    const refundDue = parseFloat(orderData.refund_due || 0);
    const tendered = (orderData.tendered !== undefined && orderData.tendered !== null) ? parseFloat(orderData.tendered).toFixed(2) : (orderData.amount_tendered ? parseFloat(orderData.amount_tendered).toFixed(2) : null);
    const change = (orderData.change !== undefined && orderData.change !== null) ? parseFloat(orderData.change).toFixed(2) : (orderData.change_amount ? parseFloat(orderData.change_amount).toFixed(2) : null);

    let returnCreditRow = '';
    if (parseFloat(returnCredits) > 0) {
        returnCreditRow = `
            <tr style="color: #000;">
                <td style="text-align: left;">Return Credit:</td>
                <td style="text-align: right; white-space: nowrap;">-${returnCredits}</td>
            </tr>
        `;
    }

    let refundDueRow = '';
    if (refundDue > 0) {
        refundDueRow = `
            <tr style="font-weight: bold;">
                <td style="text-align: left; font-size: ${totalFontSizePt}pt;">REFUND DUE:</td>
                <td style="text-align: right; font-size: ${totalFontSizePt}pt; white-space: nowrap;">${refundDue.toFixed(2)}</td>
            </tr>
        `;
    }

    let tenderedRows = '';
    if (tendered !== null && parseFloat(tendered) > 0) {
        tenderedRows = `
            <tr>
                <td style="text-align: left; padding: 1px 0;">Tendered:</td>
                <td style="text-align: right; padding: 1px 0; white-space: nowrap;">${tendered}</td>
            </tr>
            <tr>
                <td style="text-align: left; padding: 1px 0;">Change:</td>
                <td style="text-align: right; padding: 1px 0; white-space: nowrap;">${change || '0.00'}</td>
            </tr>
        `;
    }

    return `<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Purchase Order Receipt - ${escapeHtml(orderNo)}</title>
    <style>
        * {
            box-sizing: border-box;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
        @page {
            size: ${paperWidthMm}mm auto;
            margin: 0 !important;
        }
        html, body {
            margin: 0 !important;
            padding: 0 !important;
            background: #ffffff !important;
            color: #000000 !important;
            font-family: ${fontStack} !important;
            font-size: ${fontSizePt}pt !important;
            line-height: 1.25 !important;
            width: ${paperWidthMm}mm !important;
            height: auto !important;
        }
        .thermal-receipt {
            width: ${contentWidthMm}mm !important;
            max-width: ${contentWidthMm}mm !important;
            margin: 0 auto !important;
            padding: 0 !important;
            background: #ffffff !important;
            color: #000000 !important;
            box-sizing: border-box !important;
            font-family: ${fontStack} !important;
        }
        .thermal-divider-double {
            text-align: center;
            font-weight: bold;
            letter-spacing: -0.5px;
            margin: 3px 0;
            overflow: hidden;
            white-space: nowrap;
        }
        .thermal-divider-single {
            text-align: center;
            letter-spacing: -0.5px;
            margin: 2px 0;
            overflow: hidden;
            white-space: nowrap;
        }
        .thermal-header {
            text-align: center;
        }
        .thermal-title {
            font-size: ${titleFontSizePt}pt;
            font-weight: bold;
            text-transform: uppercase;
            line-height: 1.2;
            color: #000000;
        }
        .thermal-phone {
            font-size: ${fontSizePt}pt;
            margin-top: 1px;
            color: #000000;
        }
        .thermal-doc-title {
            font-size: ${fontSizePt}pt;
            font-weight: normal;
            margin-top: 2px;
            color: #000000;
        }
        .thermal-meta {
            margin: 2px 0;
            font-size: ${fontSizePt}pt;
            line-height: 1.25;
            width: 100%;
            border-collapse: collapse;
        }
        .thermal-meta td {
            padding: 1px 0;
            vertical-align: top;
        }
        .thermal-meta .meta-label {
            font-weight: bold;
            white-space: nowrap;
            width: 32%;
        }
        .thermal-table {
            width: 100% !important;
            border-collapse: collapse !important;
            margin: 0 !important;
            font-size: ${fontSizePt}pt !important;
            line-height: 1.2 !important;
        }
        .thermal-table th {
            padding: 1px 0 !important;
            font-weight: bold !important;
            text-transform: uppercase !important;
            color: #000000 !important;
        }
        .thermal-table td {
            color: #000000 !important;
        }
        .thermal-totals {
            width: 100% !important;
            border-collapse: collapse !important;
            margin: 2px 0 !important;
            font-size: ${fontSizePt}pt !important;
            line-height: 1.25 !important;
        }
        .thermal-totals td {
            padding: 1px 0;
            color: #000000 !important;
        }
        .thermal-footer {
            text-align: center;
            font-weight: bold;
            padding: 2px 0;
            text-transform: uppercase;
            font-size: ${fontSizePt}pt;
            color: #000000;
        }
        @media print {
            body {
                padding: 0 !important;
                background: #ffffff !important;
            }
            .thermal-receipt {
                box-shadow: none !important;
                border: none !important;
                padding: 0 !important;
            }
        }
    </style>
</head>
<body>
    <div class="thermal-receipt">
        <div class="thermal-divider-double">================================</div>
        <div class="thermal-header">
            <div class="thermal-title">${escapeHtml(supplierName)}</div>
            <div class="thermal-phone">Tel: ${escapeHtml(supplierPhone)}</div>
            <div class="thermal-doc-title">Purchase Order Receipt</div>
        </div>
        <div class="thermal-divider-double">================================</div>

        <table class="thermal-meta">
            <tr>
                <td class="meta-label">RECEIPT NO:</td>
                <td>${escapeHtml(orderNo)}</td>
            </tr>
            <tr>
                <td class="meta-label">CUSTOMER  :</td>
                <td>${escapeHtml(customerName)}</td>
            </tr>
            <tr>
                <td class="meta-label">PAYMENT   :</td>
                <td>${escapeHtml(paymentMethod)} (${escapeHtml(paymentStatus)})</td>
            </tr>
            <tr>
                <td class="meta-label">DATE      :</td>
                <td>${escapeHtml(dateStr)}</td>
            </tr>
        </table>

        <div class="thermal-divider-single">--------------------------------</div>
        <table class="thermal-table">
            <thead>
                <tr>
                    <th style="text-align: left; width: 68%;">ITEM DESCRIPTION</th>
                    <th style="text-align: right; width: 32%;">AMOUNT</th>
                </tr>
            </thead>
        </table>
        <div class="thermal-divider-single">--------------------------------</div>
        <table class="thermal-table">
            <tbody>
                ${itemsRows}
            </tbody>
        </table>
        <div class="thermal-divider-single">--------------------------------</div>

        <table class="thermal-totals">
            <tr>
                <td style="text-align: left;">Subtotal:</td>
                <td style="text-align: right; white-space: nowrap;">${subtotal}</td>
            </tr>
            <tr>
                <td style="text-align: left;">Discount:</td>
                <td style="text-align: right; white-space: nowrap;">${discount}</td>
            </tr>
            <tr>
                <td style="text-align: left;">Delivery:</td>
                <td style="text-align: right; white-space: nowrap;">${delivery}</td>
            </tr>
            ${returnCreditRow}
            <tr style="font-weight: bold;">
                <td style="text-align: left; font-size: ${totalFontSizePt}pt;">TOTAL:</td>
                <td style="text-align: right; font-size: ${totalFontSizePt}pt; white-space: nowrap;">${total}</td>
            </tr>
            ${refundDueRow}
            ${tenderedRows}
        </table>

        <div class="thermal-divider-double">================================</div>
        <div class="thermal-footer">
            THANK YOU FOR YOUR BUSINESS!
        </div>
        <div class="thermal-divider-double">================================</div>
    </div>
</body>
</html>`;
}

function testPrintThermalPaidOrder(widthMm = 58, contentWidthMm = null) {
    const s = getPOSPrintSettings();
    let targetWidth = widthMm || s.paperWidthMm || 58;
    if (targetWidth > MAX_THERMAL_WIDTH_MM) targetWidth = MAX_THERMAL_WIDTH_MM;
    if (targetWidth === 200) targetWidth = 210;

    let targetContentWidth = contentWidthMm || (widthMm ? getRecommendedContentWidth(targetWidth) : s.printContentWidthMm);
    if (!targetContentWidth) targetContentWidth = getRecommendedContentWidth(targetWidth);
    targetContentWidth = Math.min(targetWidth, targetContentWidth);
    
    const sampleOrderData = {
        supplier_name: 'SAM & INRI CONSTRUCTION SUPPLY',
        supplier_phone: '09612735733',
        payment_id: 'POS-20260925-01',
        payment_date: '2026-09-25 20:40:00',
        customer_name: 'Walk-in Customer',
        payment_method: 'Cash',
        payment_status: 'PAID',
        items: [
            { name: 'Portland Cement 40kg', qty: 2, price: 270.00, amount: 540.00 },
            { name: 'PVC Pipe 1/2 Blue', qty: 5, price: 85.00, amount: 425.00 }
        ],
        subtotal: 965.00,
        delivery: 0.00,
        discount: 0.00,
        total: 965.00,
        tendered: 1000.00,
        change: 35.00
    };
    const html = generatePaidOrderThermalHTML(sampleOrderData, targetWidth, targetContentWidth);
    executePOSPrintJob(html, true);
}

function testPrintPOS(format = 'thermal58') {
    if (format === 'thermal50') {
        testPrintThermalPaidOrder(50, 44);
        return;
    } else if (format === 'thermal58') {
        testPrintThermalPaidOrder(58, 48);
        return;
    } else if (format === 'thermal' || format === 'thermal80' || format === 'thermalPaidOrder') {
        testPrintThermalPaidOrder(80, 72);
        return;
    } else if (format === 'thermal210' || format === 'thermal200' || format === '210' || format === '200') {
        testPrintThermalPaidOrder(210, 120);
        return;
    } else if (format === 'custom') {
        const s = getPOSPrintSettings();
        testPrintThermalPaidOrder(s.paperWidthMm || 58, s.printContentWidthMm || 48);
        return;
    }
    const s = getPOSPrintSettings();
    let isA4 = (format === 'pdfA4' || format === 'a4');
    let isPdfPreview = (format === 'pdf' || format === 'pdfA4' || format === 'a4' || format === 'pdf_preview');
    let widthMm = 48;
    let rollMm = 58;

    if (isA4) {
        rollMm = 210;
        widthMm = getA4PrintWidthMm();
    } else if (format === 'custom') {
        rollMm = Math.min(MAX_THERMAL_WIDTH_MM, s.paperWidthMm || 58);
        if (rollMm === 210) {
            isA4 = false;
            widthMm = 195;
        } else {
            widthMm = (rollMm === 58) ? 48 : ((rollMm === 80) ? 72 : Math.max(40, rollMm - 8));
        }
    } else {
        rollMm = 58;
        widthMm = 48;
    }

    let headerFontSizePt = (widthMm >= 180) ? '16pt' : ((widthMm < 60) ? '12pt' : ((widthMm < 75) ? '12pt' : ((widthMm < 90) ? '13pt' : '14pt')));

    const testContent = `
        <!-- 1. Store Header & Title -->
        <div style="text-align: left; margin-bottom: 8px; border-bottom: 1.5pt solid #000; padding-bottom: 6px; width: 100%;">
            <div class="pos-company-header" style="font-size: ${headerFontSizePt}; font-weight: bold; text-transform: uppercase; white-space: nowrap; overflow: visible; color: #000;">SAM &amp; INRI CONSTRUCTION SUPPLY</div>
            <div style="font-size: 10pt; margin-top: 2px; color: #000;">Tel: 09612735733</div>
            <div style="font-size: 11pt; font-weight: bold; text-transform: uppercase; margin-top: 4px; letter-spacing: 0.3px; color: #000;">${isPdfPreview && rollMm === 210 ? '210 MM THERMAL PDF PREVIEW TEST (' + widthMm + ' MM WIDTH / ' + rollMm + ' MM ROLL)' : (isA4 ? 'PDF PRINT TEST (' + widthMm + ' MM WIDTH)' : 'THERMAL TEST (' + widthMm + ' MM / ' + rollMm + ' MM ROLL)')}</div>
            <div style="font-size: 10pt; font-weight: bold; text-transform: uppercase; color: #000;">(CALIBRATED &bull; LEFT ALIGNED)</div>
        </div>

        <!-- 2. Test Info Grid -->
        <table style="width: 100%; font-size: 10pt; line-height: 1.25; margin-bottom: 8px; border-collapse: collapse; border-bottom: 1.5pt solid #000; padding-bottom: 6px;">
            <tr>
                <td style="width: 52%; vertical-align: top; padding: 2px 4px 4px 0; text-align: left;">
                    <div><strong>TEST REF:</strong> <span style="font-weight: bold;">TEST-${widthMm}MM-${Date.now().toString().slice(-5)}</span></div>
                    <div><strong>TARGET:</strong> ${escapeHtml(s.printerName)}</div>
                    <div><strong>ALIGNMENT:</strong> Left-Aligned (0mm Margin)</div>
                </td>
                <td style="width: 48%; vertical-align: top; padding: 2px 0 4px 4px; text-align: right;">
                    <div><strong>DATE:</strong> ${new Date().toLocaleDateString()}</div>
                    <div><strong>PAPER:</strong> <span style="font-weight: bold;">${isA4 ? 'PDF / A4 (210 mm)' : rollMm + ' mm Roll'}</span></div>
                    <div><strong>PRINT WIDTH:</strong> <span style="font-weight: bold; color: #0f766e;">${widthMm} mm</span></div>
                </td>
            </tr>
        </table>

        <!-- 3. Items Table -->
        <table style="width: 100%; border-collapse: collapse; table-layout: fixed; font-size: 10pt; line-height: 1.25; margin-bottom: 8px;">
            <thead>
                <tr style="border-top: 1.5pt solid #000; border-bottom: 1.5pt solid #000;">
                    <th style="padding: 4pt 2pt; text-align: left; font-weight: bold; width: 46%;">ITEM</th>
                    <th style="padding: 4pt 2pt; text-align: center; font-weight: bold; width: 14%;">QTY</th>
                    <th style="padding: 4pt 2pt; text-align: right; font-weight: bold; width: 20%;">PRICE</th>
                    <th style="padding: 4pt 2pt; text-align: right; font-weight: bold; width: 20%;">TOTAL</th>
                </tr>
            </thead>
            <tbody>
                <tr style="border-bottom: 0.5pt solid #e5e7eb;">
                    <td style="padding: 4pt 2pt; text-align: left; vertical-align: top; word-break: break-word;">
                        <strong>Deformed Steel Bar 16mm</strong>
                        <div style="font-size: 9pt; color: #333; margin-top: 1px;">Grade 40, Length: 6.0m</div>
                    </td>
                    <td style="padding: 4pt 2pt; text-align: center; vertical-align: top;">10</td>
                    <td style="padding: 4pt 2pt; text-align: right; vertical-align: top;">₱485.00</td>
                    <td style="padding: 4pt 2pt; text-align: right; vertical-align: top; font-weight: bold;">₱4,850.00</td>
                </tr>
                <tr style="border-bottom: 0.5pt solid #e5e7eb;">
                    <td style="padding: 4pt 2pt; text-align: left; vertical-align: top; word-break: break-word;">
                        <strong>Portland Cement 40kg</strong>
                        <div style="font-size: 9pt; color: #333; margin-top: 1px;">Type 1P Premium Hydraulic Cement</div>
                    </td>
                    <td style="padding: 4pt 2pt; text-align: center; vertical-align: top;">20</td>
                    <td style="padding: 4pt 2pt; text-align: right; vertical-align: top;">₱245.00</td>
                    <td style="padding: 4pt 2pt; text-align: right; vertical-align: top; font-weight: bold;">₱4,900.00</td>
                </tr>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="2" style="border-top: 1.5pt solid #000; padding: 4pt 0;"></td>
                    <td style="border-top: 1.5pt solid #000; padding: 4pt 2pt; text-align: right;">Subtotal:</td>
                    <td style="border-top: 1.5pt solid #000; padding: 4pt 2pt; text-align: right; font-weight: bold;">₱9,750.00</td>
                </tr>
                <tr style="border-top: 1.5pt solid #000; border-bottom: 1.5pt solid #000;">
                    <td colspan="2" style="padding: 4pt 0;"></td>
                    <td style="padding: 4pt 2pt; text-align: right; font-size: 11pt; font-weight: bold;">TOTAL DUE:</td>
                    <td style="padding: 4pt 2pt; text-align: right; font-size: 11pt; font-weight: bold;">₱9,750.00</td>
                </tr>
            </tfoot>
        </table>

        <!-- 4. Footer -->
        <div style="text-align: left; margin-top: 8px; border-top: 1pt dashed #000; padding-top: 6px; font-size: 10pt; line-height: 1.25; width: 100%;">
            <div style="font-weight: bold; text-transform: uppercase; margin-bottom: 2px;">*** ${isPdfPreview && rollMm === 210 ? '210 MM THERMAL PDF PREVIEW (' + widthMm + ' MM WIDTH / ' + rollMm + ' MM ROLL)' : (isA4 ? 'PDF PRINT WIDTH CALIBRATED (' + widthMm + ' MM)' : widthMm + ' MM THERMAL PRINT CALIBRATED (' + rollMm + ' MM ROLL)')} ***</div>
            <div>Print layout is strictly read-only.</div>
            <div style="font-size: 9pt; color: #555; margin-top: 2px;">eConstruction Supply SaaS &bull; Point of Sale Print Engine</div>
        </div>
    `;

    const title = (format === 'pdf' || (isPdfPreview && rollMm === 210))
        ? `POS 210mm Thermal PDF Test (${widthMm}mm Width • Roll Preview)`
        : (isA4 ? `POS PDF Test (${widthMm}mm Width • Left-Aligned)` : `POS ${widthMm}mm Thermal Test (${rollMm}mm Roll)`);
    const html = generatePOSPrintHTML(testContent, title, 'test', format);
    executePOSPrintJob(html, true);
}

function testPrintA4PDF() {
    testPrintPOS('pdfA4');
}

function printPOSReceipt(format = null) {
    const s = getPOSPrintSettings();
    const modalSel = document.getElementById('posReceiptModalPaperSize')?.value;
    const effectiveFormat = format || modalSel || (s.paperWidthMm ? String(s.paperWidthMm) : '58');

    if (window.posReceiptSuccessData && window.POVoucherRenderer) {
        window.POVoucherRenderer.printReceiptData(window.posReceiptSuccessData, effectiveFormat);
        return;
    }
    const thermalEl = document.getElementById('receipt-printable-thermal-pos');
    if (thermalEl && window.POVoucherRenderer) {
        window.POVoucherRenderer.printReceiptData(thermalEl, effectiveFormat);
        return;
    }
    const printArea = document.getElementById('posPrintReceiptArea');
    if (!printArea) {
        alert('Receipt print area not found.');
        return;
    }
    const isPdf = (effectiveFormat === 'pdf' || effectiveFormat === 'pdf200' || effectiveFormat === 'pdf500' || effectiveFormat === 'pdfA4');
    const title = isPdf ? 'Purchase Order Receipt (PDF)' : 'Purchase Order Receipt (Thermal)';
    const html = generatePOSPrintHTML(printArea.innerHTML, title, 'receipt', effectiveFormat);
    executePOSPrintJob(html, true);
}

function previewPOSReceiptPDF() {
    printPOSReceipt('pdf');
}

function printReturnSlip(format = null) {
    const printArea = document.getElementById('posPrintReturnSlipArea');
    if (!printArea) {
        alert('Return slip print area not found.');
        return;
    }
    const modalSel = document.getElementById('posReturnModalPaperSize')?.value;
    const effectiveFormat = format || modalSel || null;
    const isPdf = (effectiveFormat === 'pdf' || effectiveFormat === 'pdf200' || effectiveFormat === 'pdf500' || effectiveFormat === 'pdfA4');
    const title = isPdf ? 'Official Return Slip (PDF)' : 'Official Return Slip (Thermal)';
    const html = generatePOSPrintHTML(printArea.innerHTML, title, 'return', effectiveFormat);
    executePOSPrintJob(html, true);
}

function previewPOSReturnPDF() {
    printReturnSlip('pdf');
}

function printPOSPurchaseOrder(format = null) {
    const modalSel = document.getElementById('posPOModalPaperSize')?.value;
    const effectiveFormat = format || modalSel || '58';
    if (window.posPOSuccessData && window.POVoucherRenderer) {
        window.POVoucherRenderer.print(window.posPOSuccessData, effectiveFormat);
        return;
    }
    const printArea = document.getElementById('posPrintPOArea');
    if (!printArea) {
        alert('Purchase Order print area not found.');
        return;
    }
    const isPdf = (effectiveFormat === 'pdf' || effectiveFormat === 'pdf200' || effectiveFormat === 'pdf500' || effectiveFormat === 'pdfA4');
    const title = isPdf ? 'Purchase Order Voucher (PDF)' : 'Purchase Order Voucher (Thermal)';
    const html = generatePOSPrintHTML(printArea.innerHTML, title, 'po', effectiveFormat);
    executePOSPrintJob(html, true);
}

function previewPOSPurchaseOrderPDF() {
    printPOSPurchaseOrder('pdf');
}


// ============================================================================
// POS RETURN WORKFLOW JAVASCRIPT ENGINE
// ============================================================================

let currentReturnOrders = [];
let selectedReturnItem = null;
let selectedReturnOrder = null;
let pendingReturnAction = null; // 'exchange' or 'refund'
let managerOverrideAuthorized = false;
let managerOverrideDetails = null;

function cleanupModalBackdrops() {
    if ($('.modal.in').length === 0) {
        $('.modal-backdrop').remove();
        $('body').removeClass('modal-open').css('padding-right', '');
    }
}
$(document).on('hidden.bs.modal', '.modal', function() {
    cleanupModalBackdrops();
});

function openReturnModal(initialQuery = null) {
    const mainPosSearchVal = document.getElementById('posSearchInput')?.value.trim() || '';
    let queryToSearch = (initialQuery !== null) ? initialQuery : (mainPosSearchVal ? mainPosSearchVal : '');

    // If cashier typed an invoice/email/order search into the main search box,
    // transfer it into the Return modal and reset main search so catalog grid is 100% visible!
    if (mainPosSearchVal) {
        clearPOSSearch();
    }

    $('#retOrderSearchInput').val(queryToSearch);
    $('#retModalAlert').hide();
    $('#retSearchSection').show();
    $('#retItemConfigSection').hide();
    $('#retModalFooterView1').show();
    managerOverrideAuthorized = false;
    managerOverrideDetails = null;
    $('#posReturnModal').modal('show');
    executeOrderReturnSearch(queryToSearch);
}

function executeOrderReturnSearch(keyword) {
    if (typeof keyword === 'undefined') {
        keyword = $('#retOrderSearchInput').val().trim();
    }
    
    $('#retOrdersContainer').html('<div class="text-center text-muted" style="padding: 30px 10px;"><i class="fa fa-spinner fa-spin fa-2x text-primary"></i><div style="margin-top: 8px; font-size: 13px;">Searching completed orders...</div></div>');

    $.get('pos-return-api.php', {
        action: 'search_orders',
        keyword: keyword
    }, function(res) {
        if (res.status === 'success') {
            currentReturnOrders = res.orders || [];
            renderReturnOrdersList(currentReturnOrders, keyword);
        } else {
            $('#retOrdersContainer').html('<div class="alert alert-danger" style="margin-top: 10px;">' + escapeHtml(res.message) + '</div>');
        }
    }, 'json').fail(function() {
        $('#retOrdersContainer').html('<div class="alert alert-danger" style="margin-top: 10px;">Failed to connect to Return API.</div>');
    });
}

function resetOrderReturnSearch() {
    $('#retOrderSearchInput').val('');
    executeOrderReturnSearch('');
}

function renderReturnOrdersList(orders, query) {
    $('#retOrdersListTitle').text(query ? `Search Results for "${query}"` : 'Recent Completed Orders (Within 7 Days)');

    if (!orders || orders.length === 0) {
        $('#retOrdersCountBadge').text('0 orders');
        $('#retOrdersContainer').html(`
            <div class="text-center text-muted" style="padding: 40px 15px; background: #f8fafc; border: 1.5px dashed #cbd5e1; border-radius: 8px;">
                <i class="fa fa-inbox fa-3x" style="color: #cbd5e1;"></i>
                <div style="margin-top: 10px; font-size: 14px; font-weight: bold; color: #64748b;">No eligible returnable items found within the 7-day policy window</div>
                <div style="font-size: 12px; color: #94a3b8; margin-top: 4px;">Only paid orders from the last 7 days with remaining returnable items are displayed.</div>
            </div>
        `);
        return;
    }

    let html = '';
    let visibleOrdersCount = 0;

    orders.forEach((ord) => {
        let itemsHtml = '';
        let visibleItemsCount = 0;
        const isWithin7Days = ord.is_within_7_days;
        const daysElapsed = ord.days_elapsed || 0;
        const daysRemaining = ord.days_remaining !== undefined ? ord.days_remaining : Math.max(0, 7 - daysElapsed);

        let policyBadge = isWithin7Days
            ? `<span class="label label-success" style="font-size: 11px; padding: 2px 7px; background-color: #10b981;"><i class="fa fa-calendar-check-o"></i> ${daysElapsed}d ago (${daysRemaining}d left)</span>`
            : `<span class="label label-danger" style="font-size: 11px; padding: 2px 7px; background-color: #ef4444;"><i class="fa fa-exclamation-circle"></i> Policy Expired (${daysElapsed}d ago - PIN Req)</span>`;

        ord.items.forEach((it) => {
            // Check active POS cart for already staged return credit
            let inCartQty = 0;
            if (typeof cart !== 'undefined' && Array.isArray(cart)) {
                cart.forEach(cItem => {
                    if (cItem.item_type === 'RETURN_CREDIT' && (String(cItem.order_item_id) === String(it.order_item_id) || String(cItem.id) === 'RET_' + it.order_item_id)) {
                        inCartQty += parseInt(cItem.qty) || 0;
                    }
                });
            }

            const effectiveAvailable = Math.max(0, (parseInt(it.available_to_return) || 0) - inCartQty);
            
            // If item has no remaining available quantity to return, completely omit/remove from modal
            if (effectiveAvailable <= 0) {
                return;
            }

            visibleItemsCount++;
            
            // Clone item with effective available quantity
            const itemForSelect = Object.assign({}, it, { available_to_return: effectiveAvailable });

            const specialBadge = (it.item_type === 'SPECIAL_ORDER') ? '<span class="label label-warning" style="background-color: #d97706; font-size: 9px; padding: 1px 4px;">SPECIAL ORDER</span> ' : '';
            
            itemsHtml += `
                <div style="display: flex; justify-content: space-between; align-items: center; padding: 8px 12px; border-bottom: 1px solid #f1f5f9; gap: 10px;">
                    <div style="flex-grow: 1;">
                        <div style="font-weight: 700; color: #1e293b; font-size: 13px;">
                            ${specialBadge}${escapeHtml(it.product_name)}
                        </div>
                        ${it.product_details ? `<div style="font-size: 11px; color: #64748b;">${escapeHtml(it.product_details)}</div>` : ''}
                        <div style="font-size: 11.5px; color: #64748b; font-family: monospace; margin-top: 2px;">
                            ${it.sku ? 'SKU: ' + escapeHtml(it.sku) + ' | ' : ''}
                            ₱${parseFloat(it.unit_price).toFixed(2)} × ${it.purchased_qty} unit(s)
                            ${it.previously_returned > 0 ? `<span style="color: #991b1b; font-weight: bold;"> (${it.previously_returned} returned)</span>` : ''}
                            ${it.pending_returned > 0 ? `<span style="color: #d97706; font-weight: bold;"> (${it.pending_returned} pending)</span>` : ''}
                            ${inCartQty > 0 ? `<span style="color: #0284c7; font-weight: bold;"> (${inCartQty} in active cart)</span>` : ''}
                        </div>
                    </div>
                    <div style="text-align: right; min-width: 140px;">
                        <button type="button" class="btn btn-danger btn-xs" onclick='selectReturnItem(${JSON.stringify(ord).replace(/'/g, "&apos;")}, ${JSON.stringify(itemForSelect).replace(/'/g, "&apos;")})' style="font-weight: 700; background: #dc2626; border-color: #b91c1c; padding: 4px 10px; border-radius: 4px;">
                            <i class="fa fa-undo"></i> Return (${effectiveAvailable} left)
                        </button>
                    </div>
                </div>
            `;
        });

        // Only render the order container if there is at least 1 returnable item visible
        if (visibleItemsCount > 0) {
            visibleOrdersCount++;
            html += `
                <div style="border: 1.5px solid #e2e8f0; border-radius: 8px; background: #fff; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.04); margin-bottom: 8px;">
                    <div style="background: #f8fafc; border-bottom: 1px solid #e2e8f0; padding: 10px 14px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
                        <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                            <span style="font-size: 10.5px; font-weight: 700; color: #64748b; text-transform: uppercase;">Invoice:</span>
                            <strong style="font-family: monospace; color: #0284c7; font-size: 13px;">${escapeHtml(ord.payment_id)}</strong>
                            <span style="font-size: 11.5px; color: #64748b;">${new Date(ord.payment_date).toLocaleDateString()} ${new Date(ord.payment_date).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}</span>
                            ${policyBadge}
                        </div>
                        <div style="font-size: 12px; color: #334155;">
                            <i class="fa fa-user"></i> <strong>${escapeHtml(ord.customer_name)}</strong>
                            ${ord.customer_phone && ord.customer_phone !== 'N/A' ? `<span style="color: #64748b; margin-left: 4px;">(${escapeHtml(ord.customer_phone)})</span>` : ''}
                        </div>
                    </div>
                    <div>
                        ${itemsHtml}
                    </div>
                </div>
            `;
        }
    });

    $('#retOrdersCountBadge').text(visibleOrdersCount + (visibleOrdersCount === 1 ? ' order' : ' orders'));

    if (visibleOrdersCount === 0) {
        $('#retOrdersContainer').html(`
            <div class="text-center text-muted" style="padding: 40px 15px; background: #f8fafc; border: 1.5px dashed #cbd5e1; border-radius: 8px;">
                <i class="fa fa-inbox fa-3x" style="color: #cbd5e1;"></i>
                <div style="margin-top: 10px; font-size: 14px; font-weight: bold; color: #64748b;">No eligible returnable items found within the 7-day policy window</div>
                <div style="font-size: 12px; color: #94a3b8; margin-top: 4px;">All items from recent matching orders have already been fully returned or staged in your active cart.</div>
            </div>
        `);
        return;
    }

    $('#retOrdersContainer').html(html);
}

function selectReturnItem(order, item) {
    selectedReturnOrder = order;
    selectedReturnItem = item;
    managerOverrideAuthorized = false;
    managerOverrideDetails = null;

    $('#retSubmitOrderItemId').val(item.order_item_id);
    $('#retSubmitPaymentId').val(order.payment_id);

    $('#retCfgOrderRef').text(order.payment_id);
    $('#retCfgOrderDate').text(new Date(order.payment_date).toLocaleString());
    $('#retCfgCustomerName').text(order.customer_name || 'Walk-in Customer');
    $('#retCfgCustomerPhone').text(order.customer_phone && order.customer_phone !== 'N/A' ? '(' + order.customer_phone + ')' : '');

    $('#retCfgItemName').text(item.product_name);
    $('#retCfgUnitPrice').text(parseFloat(item.unit_price).toFixed(2));
    $('#retCfgSku').text(item.sku || '-');
    $('#retCfgPurchasedQty').text(item.purchased_qty);
    $('#retCfgPrevReturnedQty').text(item.previously_returned + (item.pending_returned > 0 ? ` (+${item.pending_returned} pending)` : ''));
    $('#retCfgAvailableQty').text(item.available_to_return);
    $('#retMaxQtyNotice').text(item.available_to_return);

    if (item.product_details) {
        $('#retCfgItemDetails').text(item.product_details).show();
    } else {
        $('#retCfgItemDetails').hide();
    }

    if (item.item_type === 'SPECIAL_ORDER') {
        $('#retCfgSpecialBadge').show();
    } else {
        $('#retCfgSpecialBadge').hide();
    }

    if (item.photo) {
        $('#retCfgItemImg').css('background-image', `url('${item.photo}')`);
    } else {
        $('#retCfgItemImg').css('background-image', 'none');
    }

    $('#retQuantityInput').attr('max', item.available_to_return);
    $('#retQuantityInput').val(1);

    $('#retReasonSelect').val('Customer changed mind');
    $('#retReasonNotes').val('');
    $('#retReasonNotesWrap').hide();
    $('#retConditionSelect').val('Resellable');
    $('#retRefundMethod').val('Cash');
    $('#retGeneralNotes').val('');

    render7DayPolicyBanner();
    updateRestockBadge();
    calculateReturnTotal();

    $('#retSearchSection').hide();
    $('#retItemConfigSection').show();
    $('#retModalFooterView1').hide();
}

function render7DayPolicyBanner() {
    if (!selectedReturnOrder) return;
    const isWithin = selectedReturnOrder.is_within_7_days;
    const daysElapsed = selectedReturnOrder.days_elapsed || 0;
    const daysRemaining = selectedReturnOrder.days_remaining !== undefined ? selectedReturnOrder.days_remaining : Math.max(0, 7 - daysElapsed);
    const banner = $('#ret7DayStatusAlert');

    if (isWithin) {
        banner.html(`
            <div class="alert alert-success" style="margin-bottom: 14px; font-size: 12.5px; padding: 9px 13px; border-left: 4px solid #10b981; background: #f0fdf4; color: #166534;">
                <i class="fa fa-check-circle"></i> <strong>7-Day Return Policy Compliant:</strong> Purchased ${daysElapsed} day(s) ago (${daysRemaining} day(s) remaining). Eligible for immediate in-store exchange or refund request.
            </div>
        `).show();
    } else {
        banner.html(`
            <div class="alert alert-warning" style="margin-bottom: 14px; font-size: 12.5px; padding: 9px 13px; border-left: 4px solid #f59e0b; background: #fffbeb; color: #92400e;">
                <i class="fa fa-exclamation-triangle"></i> <strong>7-Day Policy Expired (${daysElapsed} days ago):</strong> Standard return window has closed. Proceeding requires <strong>Manager PIN Authorization Override</strong>.
            </div>
        `).show();
    }
}

function promptManagerOverrideForReturn(actionType) {
    pendingReturnAction = actionType;
    $('#posManagerPinInput').val('');
    $('#posManagerPinAlert').hide().text('');
    $('#posManagerPinSubmitBtn').prop('disabled', false).html('<i class="fa fa-unlock-alt"></i> Authorize Override');
    $('#posManagerPinModal').modal('show');
    setTimeout(() => {
        $('#posManagerPinInput').focus();
    }, 400);
}

function executeManagerPinVerify() {
    const pin = $('#posManagerPinInput').val().trim();
    if (!pin) {
        $('#posManagerPinAlert').text('Please enter Manager PIN or Password.').show();
        return;
    }

    $('#posManagerPinSubmitBtn').prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Verifying...');
    $('#posManagerPinAlert').hide();

    $.post('pos-return-api.php', {
        action: 'verify_manager_pin',
        pin: pin
    }, function(res) {
        $('#posManagerPinSubmitBtn').prop('disabled', false).html('<i class="fa fa-unlock-alt"></i> Authorize Override');
        if (res.status === 'success') {
            managerOverrideAuthorized = true;
            managerOverrideDetails = {
                manager_id: res.manager_id,
                manager_name: res.manager_name,
                timestamp: new Date().toISOString()
            };
            $('#posManagerPinModal').modal('hide');
            cleanupModalBackdrops();

            if (pendingReturnAction === 'exchange') {
                proceedApplyReturnToCart();
            } else if (pendingReturnAction === 'refund') {
                proceedSubmitReturnRequest();
            }
        } else {
            $('#posManagerPinAlert').text(res.message || 'Invalid Manager PIN. Access denied.').show();
            $('#posManagerPinInput').select().focus();
        }
    }, 'json').fail(function() {
        $('#posManagerPinSubmitBtn').prop('disabled', false).html('<i class="fa fa-unlock-alt"></i> Authorize Override');
        $('#posManagerPinAlert').text('Connection error verifying Manager PIN.').show();
    });
}

function backToReturnOrdersList() {
    $('#retItemConfigSection').hide();
    $('#retSearchSection').show();
    $('#retModalFooterView1').show();
}

function changeReturnQty(delta) {
    if (!selectedReturnItem) return;
    let curr = parseInt($('#retQuantityInput').val()) || 1;
    let max = selectedReturnItem.available_to_return;
    let next = Math.max(1, Math.min(max, curr + delta));
    $('#retQuantityInput').val(next);
    calculateReturnTotal();
}

function onReturnQtyInput() {
    if (!selectedReturnItem) return;
    let val = parseInt($('#retQuantityInput').val()) || 1;
    let max = selectedReturnItem.available_to_return;
    if (val < 1) val = 1;
    if (val > max) val = max;
    $('#retQuantityInput').val(val);
    calculateReturnTotal();
}

function calculateReturnTotal() {
    if (!selectedReturnItem) return;
    let qty = parseInt($('#retQuantityInput').val()) || 1;
    let price = parseFloat(selectedReturnItem.unit_price) || 0;
    let total = (qty * price).toFixed(2);
    $('#retCalculationFormula').text(`${qty} × ₱${price.toFixed(2)}`);
    $('#retTotalRefundDisplay').text(total);
}

function onReturnReasonChange() {
    const val = $('#retReasonSelect').val();
    if (val === 'Other') {
        $('#retReasonNotesWrap').show();
        $('#retReasonNotes').focus();
    } else {
        $('#retReasonNotesWrap').hide();
    }
}

function updateRestockBadge() {
    const cond = $('#retConditionSelect').val();
    const isSpecial = selectedReturnItem && (selectedReturnItem.item_type === 'SPECIAL_ORDER' || selectedReturnItem.product_id === 0);
    const alertBox = $('#retRestockAlert');

    if (['Good / Resalable', 'Resellable', 'Unopened'].includes(cond)) {
        if (isSpecial) {
            alertBox.html('<div class="alert alert-info" style="margin-bottom: 0; font-size: 12px; padding: 8px 12px;"><i class="fa fa-info-circle"></i> Special order item — will not modify catalog stock, but refund will be issued upon approval.</div>');
        } else {
            alertBox.html('<div class="alert alert-success" style="margin-bottom: 0; font-size: 12px; padding: 8px 12px;"><i class="fa fa-check-circle"></i> <strong>Good Condition:</strong> If approved by Manager, returned units will be automatically added back to active sellable inventory.</div>');
        }
    } else {
        alertBox.html('<div class="alert alert-warning" style="margin-bottom: 0; font-size: 12px; padding: 8px 12px;"><i class="fa fa-exclamation-triangle"></i> <strong>Damaged / Non-sellable:</strong> Units will NOT be restocked to inventory. Quarantined for scrap or supplier return.</div>');
    }
}

function applyCurrentReturnToCart() {
    if (!selectedReturnItem || !selectedReturnOrder) {
        alert('No item selected for return.');
        return;
    }

    const qty = parseInt($('#retQuantityInput').val()) || 1;
    if (qty < 1 || qty > selectedReturnItem.available_to_return) {
        alert('Invalid return quantity.');
        return;
    }

    // 7-day policy check
    if (!selectedReturnOrder.is_within_7_days && !managerOverrideAuthorized) {
        promptManagerOverrideForReturn('exchange');
        return;
    }

    proceedApplyReturnToCart();
}

function proceedApplyReturnToCart() {
    if (!selectedReturnItem || !selectedReturnOrder) return;

    const qty = parseInt($('#retQuantityInput').val()) || 1;
    const unitPrice = parseFloat(selectedReturnItem.unit_price) || 0;
    const reason = $('#retReasonSelect').val();
    const reasonNotes = $('#retReasonNotes').val().trim();
    const condition = $('#retConditionSelect').val();
    const generalNotes = $('#retGeneralNotes').val().trim();

    // Push or update negative RETURN_CREDIT item in cart
    const returnCreditItemId = 'RET_' + selectedReturnItem.order_item_id;
    const existingIndex = cart.findIndex(it => it.id === returnCreditItemId || (it.item_type === 'RETURN_CREDIT' && it.order_item_id === selectedReturnItem.order_item_id));

    const returnItemPayload = {
        id: returnCreditItemId,
        original_order_id: selectedReturnItem.order_item_id,
        order_item_id: selectedReturnItem.order_item_id,
        original_payment_id: selectedReturnOrder.payment_id,
        payment_id: selectedReturnOrder.payment_id,
        product_id: selectedReturnItem.product_id || 0,
        item_type: 'RETURN_CREDIT',
        sku: selectedReturnItem.sku || '',
        name: selectedReturnItem.product_name,
        base_name: selectedReturnItem.product_name,
        spec_label: selectedReturnItem.product_details || '',
        product_details: selectedReturnItem.product_details || '',
        price: -Math.abs(unitPrice),
        unit_price: unitPrice,
        qty: qty,
        max_return_qty: selectedReturnItem.available_to_return,
        return_reason: reason,
        reason_notes: reasonNotes,
        condition: condition,
        general_notes: generalNotes,
        manager_override: managerOverrideAuthorized ? managerOverrideDetails : null
    };

    if (existingIndex > -1) {
        cart[existingIndex] = returnItemPayload;
    } else {
        cart.push(returnItemPayload);
    }

    // Close Return Modal cleanly without affecting page layout or freezing
    $('#posReturnModal').modal('hide');
    cleanupModalBackdrops();

    // Clear any temporary catalog search filter so products are immediately visible
    clearPOSSearch();

    // Render cart and update calculations
    renderCart();
    updatePOSCalculations();

    // Reset return selections
    selectedReturnItem = null;
    selectedReturnOrder = null;
    managerOverrideAuthorized = false;
    managerOverrideDetails = null;
}

function confirmAndSubmitReturn() {
    if (!selectedReturnItem || !selectedReturnOrder) {
        alert('No item selected for return.');
        return;
    }

    const qty = parseInt($('#retQuantityInput').val()) || 1;
    const reason = $('#retReasonSelect').val();
    const reasonNotes = $('#retReasonNotes').val().trim();

    if (qty < 1 || qty > selectedReturnItem.available_to_return) {
        alert('Invalid return quantity.');
        return;
    }

    if (reason === 'Other' && !reasonNotes) {
        alert('Please specify the custom return reason.');
        $('#retReasonNotes').focus();
        return;
    }

    // 7-day policy check
    if (!selectedReturnOrder.is_within_7_days && !managerOverrideAuthorized) {
        promptManagerOverrideForReturn('refund');
        return;
    }

    proceedSubmitReturnRequest();
}

function proceedSubmitReturnRequest() {
    const orderItemId = $('#retSubmitOrderItemId').val();
    const paymentId = $('#retSubmitPaymentId').val();
    const qty = parseInt($('#retQuantityInput').val()) || 1;
    const reason = $('#retReasonSelect').val();
    const reasonNotes = $('#retReasonNotes').val().trim();
    const condition = $('#retConditionSelect').val();
    const refundMethod = $('#retRefundMethod').val();
    const generalNotes = $('#retGeneralNotes').val().trim();
    const refundTotal = (qty * parseFloat(selectedReturnItem.unit_price)).toFixed(2);

    if (!confirm(`Submit return request for ${qty} unit(s) of "${selectedReturnItem.product_name}" (Refund: ₱${refundTotal}) for Manager / Admin Approval?`)) {
        return;
    }

    $('#retProcessSubmitBtn').prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Submitting...');

    $.post('pos-return-api.php', {
        action: 'submit_return_request',
        order_item_id: orderItemId,
        payment_id: paymentId,
        return_quantity: qty,
        return_reason: reason,
        reason_notes: reasonNotes,
        condition: condition,
        refund_method: refundMethod,
        general_notes: generalNotes,
        manager_override: managerOverrideAuthorized ? 1 : 0,
        manager_id: managerOverrideDetails ? managerOverrideDetails.manager_id : ''
    }, function(res) {
        $('#retProcessSubmitBtn').prop('disabled', false).html('<i class="fa fa-paper-plane"></i> Direct Refund');

        if (res.status === 'success') {
            $('#posReturnModal').modal('hide');
            cleanupModalBackdrops();
            renderReturnSubmissionSuccessModal(res);
        } else {
            alert('Error: ' + res.message);
        }
    }, 'json').fail(function() {
        $('#retProcessSubmitBtn').prop('disabled', false).html('<i class="fa fa-paper-plane"></i> Direct Refund');
        alert('Server error while submitting return request.');
    });
}

function renderReturnSubmissionSuccessModal(res) {
    const html = `
        <div style="text-align: left; margin-bottom: 12px; border-bottom: 1.5pt solid #000; padding-bottom: 8px;">
            <div class="pos-company-header" style="font-size: 12pt; font-weight: bold; text-transform: uppercase; white-space: nowrap; overflow: visible; color: #000; line-height: 1.2;">SAM &amp; INRI CONSTRUCTION SUPPLY</div>
            <div style="font-size: 10pt; font-weight: normal; margin-top: 2px; color: #000;">Online Construction Supply Platform</div>
            <div style="font-size: 11pt; font-weight: bold; text-transform: uppercase; margin-top: 4px; letter-spacing: 0.5px; color: #000;">OFFICIAL RETURN &amp; REFUND SLIP</div>
        </div>

        <div style="border: 1.5pt solid #000; padding: 8px 12px; margin-bottom: 12px; text-align: center; font-size: 12pt; line-height: 1.25; background: #fff;">
            <div style="font-weight: bold; text-transform: uppercase; font-size: 13pt;">*** RETURN REQUEST QUEUED (PENDING APPROVAL) ***</div>
            <div style="margin-top: 2px;">This return request has been submitted and is awaiting Manager / Administrator review.</div>
        </div>

        <table style="width: 100%; font-size: 12pt; line-height: 1.25; margin-bottom: 12px; border-collapse: collapse;">
            <tr>
                <td style="width: 50%; vertical-align: top; padding: 2px 8px 2px 0;">
                    <div><strong>Return Reference:</strong> <span style="font-family: monospace; font-size: 13pt; font-weight: bold;">${escapeHtml(res.return_reference)}</span></div>
                    <div><strong>Original Invoice #:</strong> ${escapeHtml(res.payment_id || '')}</div>
                    <div><strong>Refund Method:</strong> ${escapeHtml(res.refund_method)}</div>
                </td>
                <td style="width: 50%; vertical-align: top; padding: 2px 0 2px 8px; text-align: right;">
                    <div><strong>Submission Date:</strong> ${new Date().toLocaleString()}</div>
                    <div><strong>Approval Status:</strong> <span style="font-weight: bold; border: 1pt solid #000; padding: 1px 6px; font-size: 11pt; text-transform: uppercase;">PENDING</span></div>
                </td>
            </tr>
        </table>

        <table style="width: 100%; border-collapse: collapse; table-layout: fixed; font-size: 12pt; line-height: 1.25; margin-bottom: 12px;">
            <thead>
                <tr style="border-top: 1.5pt solid #000; border-bottom: 1.5pt solid #000;">
                    <th style="padding: 5pt 4pt; text-align: left; font-weight: bold; width: 45%;">RETURNED ITEM</th>
                    <th style="padding: 5pt 4pt; text-align: left; font-weight: bold; width: 20%;">SKU</th>
                    <th style="padding: 5pt 4pt; text-align: center; font-weight: bold; width: 15%;">QTY</th>
                    <th style="padding: 5pt 4pt; text-align: right; font-weight: bold; width: 20%;">REFUND AMOUNT</th>
                </tr>
            </thead>
            <tbody>
                <tr style="border-bottom: 1.5pt solid #000;">
                    <td style="padding: 5pt 4pt; text-align: left; vertical-align: top; word-break: break-word;">
                        <strong>${escapeHtml(res.product_name)}</strong>
                    </td>
                    <td style="padding: 5pt 4pt; text-align: left; vertical-align: top;">${escapeHtml(res.sku || '-')}</td>
                    <td style="padding: 5pt 4pt; text-align: center; vertical-align: top;">${parseInt(res.quantity_returned)} unit(s)</td>
                    <td style="padding: 5pt 4pt; text-align: right; vertical-align: top; font-weight: bold;">₱${parseFloat(res.refund_amount).toFixed(2)}</td>
                </tr>
            </tbody>
            <tfoot>
                <tr style="border-bottom: 1.5pt solid #000;">
                    <td colspan="2" style="padding: 5pt 4pt;"></td>
                    <td style="padding: 5pt 4pt; text-align: right; font-weight: bold;">TOTAL REFUND:</td>
                    <td style="padding: 5pt 4pt; text-align: right; font-size: 14pt; font-weight: bold; color: #000;">₱${parseFloat(res.refund_amount).toFixed(2)}</td>
                </tr>
            </tfoot>
        </table>

        <div style="text-align: center; margin-top: 14px; border-top: 1pt dashed #000; padding-top: 10px; font-size: 12pt; line-height: 1.25;">
            <div style="font-weight: bold;">RETURN AUDIT RECORD</div>
            <div style="font-size: 11pt; margin-top: 2px;">Keep this slip until the return approval is processed by the store manager.</div>
            <div style="font-size: 10pt; color: #555; margin-top: 3px;">System-Generated Return Slip &bull; eConstruction Supply SaaS POS</div>
        </div>
    `;

    $('#posPrintReturnSlipArea').html(html);
    $('#posReturnSuccessModal').modal('show');
}

function closeReturnSuccessModal() {
    $('#posReturnSuccessModal').modal('hide');
    cleanupModalBackdrops();
    clearPOSSearch();
    selectedReturnItem = null;
    selectedReturnOrder = null;
    managerOverrideAuthorized = false;
    managerOverrideDetails = null;
}

$(document).ready(function() {
    initPOSPrinterDetection();
    updatePOSPrinterBadge();
    syncModalPaperSizeSelects();
    if (typeof cart !== 'undefined' && cart && cart.length > 0) {
        renderCart();
    }
    toggleCustomerType();
    toggleLocationOption();
    handlePaymentMethodChange();
    updatePOSCalculations();
    try {
        if (localStorage.getItem('pos_categories_collapsed') === '1') {
            const wrapper = document.getElementById('posCategoryAccordion');
            if (wrapper) wrapper.classList.add('collapsed');
            const icon = document.getElementById('posCatToggleIcon');
            const text = document.getElementById('posCatToggleText');
            if (icon) icon.className = 'fa fa-chevron-down';
            if (text) text.innerText = 'Expand';
        }
    } catch (e) {}

    // Category Carousel Mousewheel & Resize Listeners
    const deptBar = document.getElementById('posDeptTabsBar');
    if (deptBar) {
        deptBar.addEventListener('wheel', function(e) {
            if (e.deltaY !== 0) {
                e.preventDefault();
                deptBar.scrollLeft += (e.deltaY * 1.5);
                updateDeptNavState();
            }
        }, { passive: false });
        window.addEventListener('resize', updateDeptNavState);
        setTimeout(updateDeptNavState, 150);
    }

    // Sanitize search bar from stray browser email autofill
    const posSearchBox = document.getElementById('posSearchInput');
    if (posSearchBox) {
        if (posSearchBox.value.includes('@') || posSearchBox.value.includes('.com') || posSearchBox.value.includes('.ph')) {
            posSearchBox.value = '';
        }
    }

    // Restore saved product view mode (grid vs list)
    try {
        const savedViewMode = localStorage.getItem('pos_product_view_mode') || 'grid';
        setPOSViewMode(savedViewMode);
    } catch (e) {}

    // Global Keyboard Shortcuts (F2 / Ctrl+K focus search, F8 hold order, Esc clears search)
    $(document).on('keydown', function(e) {
        if (e.key === 'F2' || ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k')) {
            if ($('.modal.in').length > 0) return; // Don't interrupt open modal
            e.preventDefault();
            const input = document.getElementById('posSearchInput');
            if (input) {
                input.focus();
                input.select();
            }
        }
        if (e.key === 'F8') {
            if ($('.modal.in').length > 0) return;
            e.preventDefault();
            openHoldOrderModal();
        }
        if (e.key === 'Escape' && document.activeElement && document.activeElement.id === 'posSearchInput') {
            clearPOSSearch();
        }
    });

    initializePOSSearchIndices();
    filterPOSProducts();

    <?php if ($pos_po_success_data): ?>
    $('#posPOSuccessModal').modal({ backdrop: 'static', keyboard: false });
    if (typeof updatePOVoucherModalPreview === 'function') {
        const s = getPOSPrintSettings();
        const initFmt = (s.printerType === 'normal' || s.paperWidthMm === 210) ? '210' : (s.paperWidthMm === 80 ? '80' : '58');
        const sel = document.getElementById('posPOModalPaperSize');
        if (sel) sel.value = initFmt;
        updatePOVoucherModalPreview(initFmt);
    }
    <?php endif; ?>
});

// ==========================================
// POS HOLD / PARK ORDER WORKFLOW JAVASCRIPT
// ==========================================

function openHoldOrderModal() {
    if (!cart || cart.length === 0) {
        alert('Cannot hold an empty cart. Please add items to cart first.');
        return;
    }

    // Determine current customer name
    let custName = 'Walk-in Customer';
    const custTypeRadio = document.querySelector('input[name="customer_type"]:checked');
    if (custTypeRadio && custTypeRadio.value === 'registered') {
        const regSelect = document.getElementById('registeredCustSelect') || document.querySelector('select[name="registered_cust_id"]');
        if (regSelect && regSelect.selectedIndex >= 0 && regSelect.options[regSelect.selectedIndex].text) {
            custName = regSelect.options[regSelect.selectedIndex].text.replace(/\s*\(ID:.*?\)/i, '').trim();
        }
    } else {
        const walkinInput = document.getElementById('walkinName') || document.querySelector('input[name="walkin_name"]');
        if (walkinInput && walkinInput.value.trim()) {
            custName = walkinInput.value.trim();
        }
    }

    // Calculate item count & unit count & total
    let totalItems = cart.length;
    let totalUnits = 0;
    let totalAmount = 0;

    cart.forEach(item => {
        const q = parseInt(item.qty || 1);
        const p = parseFloat(item.price || 0);
        const discAmt = parseFloat(item.discount_amount || 0);
        const discPct = parseFloat(item.discount_percent || 0);
        let lineGross = q * p;
        let lineDisc = discAmt > 0 ? discAmt : (discPct > 0 ? (lineGross * (discPct / 100)) : 0);
        let lineNet = Math.max(0, lineGross - lineDisc);

        totalUnits += q;
        totalAmount += lineNet;
    });

    const isDelivery = document.getElementById('isLocationDelivery')?.checked;
    const deliveryCost = isDelivery ? (parseFloat(document.getElementById('posDeliveryCost')?.value || 0)) : 0;
    totalAmount += deliveryCost;

    document.getElementById('holdModalItemCount').innerText = totalItems;
    document.getElementById('holdModalUnitCount').innerText = totalUnits;
    document.getElementById('holdModalTotalAmount').innerText = round2(totalAmount).toFixed(2);
    document.getElementById('holdOrderCustName').value = custName;
    document.getElementById('holdOrderNote').value = '';

    const alertBox = document.getElementById('holdModalAlert');
    if (alertBox) {
        alertBox.style.display = 'none';
        alertBox.innerText = '';
    }

    $('#posHoldOrderModal').modal('show');
}

function submitHoldOrder(printSlip = false) {
    if (!cart || cart.length === 0) {
        alert('Cart is empty.');
        return;
    }

    const btnSilent = document.getElementById('btnHoldSilent');
    const btnPrint = document.getElementById('btnHoldPrint');
    const alertBox = document.getElementById('holdModalAlert');

    if (btnSilent) btnSilent.disabled = true;
    if (btnPrint) btnPrint.disabled = true;
    if (alertBox) alertBox.style.display = 'none';

    const custTypeRadio = document.querySelector('input[name="customer_type"]:checked');
    const custType = custTypeRadio ? custTypeRadio.value : 'walkin';
    const regCustId = document.querySelector('select[name="registered_cust_id"]')?.value || 0;
    const walkinName = document.getElementById('holdOrderCustName')?.value.trim() || 'Walk-in Customer';
    const walkinPhone = document.querySelector('input[name="walkin_phone"]')?.value || '';
    const isDelivery = document.getElementById('isLocationDelivery')?.checked ? 1 : 0;
    const locationBrgyId = document.querySelector('select[name="location_brgy_id"]')?.value || 0;
    const deliveryCost = isDelivery ? (parseFloat(document.getElementById('posDeliveryCost')?.value || 0)) : 0;
    const holdNote = document.getElementById('holdOrderNote')?.value.trim() || '';

    const payload = new URLSearchParams();
    payload.append('action', 'hold_order');
    payload.append('cart_items', JSON.stringify(cart));
    payload.append('customer_type', custType);
    payload.append('walkin_name', walkinName);
    payload.append('walkin_phone', walkinPhone);
    payload.append('registered_cust_id', regCustId);
    payload.append('is_location_delivery', isDelivery);
    payload.append('location_brgy_id', locationBrgyId);
    payload.append('delivery_cost', deliveryCost);
    payload.append('hold_note', holdNote);

    fetch('pos-hold-api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: payload.toString()
    })
    .then(r => r.json())
    .then(res => {
        if (btnSilent) btnSilent.disabled = false;
        if (btnPrint) btnPrint.disabled = false;

        if (!res.success) {
            if (alertBox) {
                alertBox.innerText = res.message || 'Failed to hold order.';
                alertBox.style.display = 'block';
            } else {
                alert(res.message || 'Failed to hold order.');
            }
            return;
        }

        // Print thermal voucher if requested
        if (printSlip && res.print_html) {
            const s = getPOSPrintSettings();
            const fmt = (s.paperWidthMm ? String(s.paperWidthMm) : '58');
            const printDoc = generatePOSPrintHTML(res.print_html, 'Purchase Order Voucher (Held)', 'po', fmt);
            executePOSPrintJob(printDoc, true);
        }

        // Clear active POS cart
        cart = [];
        renderCart();

        // Update badge count
        const badge = document.getElementById('posHeldCountBadge');
        if (badge) {
            const currentCount = parseInt(badge.innerText || '0', 10);
            badge.innerText = currentCount + 1;
        }

        $('#posHoldOrderModal').modal('hide');

        // Reset URL if currently in a resumed PO session
        if (window.location.search.includes('po_id') || window.location.search.includes('payment_id')) {
            window.history.replaceState({}, document.title, 'pos.php');
        }

        alert('Order successfully parked and held as ' + res.po_id + ' for ' + res.customer_name + '.\nRegister is now cleared for the next customer.');
    })
    .catch(err => {
        if (btnSilent) btnSilent.disabled = false;
        if (btnPrint) btnPrint.disabled = false;
        if (alertBox) {
            alertBox.innerText = 'Network error: ' + err.message;
            alertBox.style.display = 'block';
        } else {
            alert('Network error: ' + err.message);
        }
    });
}

function openHeldOrdersModal() {
    $('#posHeldOrdersModal').modal('show');
    loadHeldOrdersList();
}

function loadHeldOrdersList() {
    const container = document.getElementById('heldOrdersListContainer');
    if (!container) return;

    container.innerHTML = `
        <div style="text-align: center; padding: 35px 10px; color: #64748b;">
            <i class="fa fa-spinner fa-spin fa-2x text-primary"></i>
            <p style="margin-top: 10px; font-weight: 600;">Loading held orders...</p>
        </div>
    `;

    fetch('pos-hold-api.php?action=list_held_orders')
    .then(r => r.json())
    .then(res => {
        if (!res.success) {
            container.innerHTML = `
                <div class="alert alert-danger" style="margin: 10px 0;">
                    <i class="fa fa-exclamation-triangle"></i> Failed to load held orders: ${escapeHtml(res.message || 'Unknown error')}
                </div>
            `;
            return;
        }

        // Update header badge
        const badge = document.getElementById('posHeldCountBadge');
        if (badge) {
            badge.innerText = res.count || 0;
        }

        if (!res.orders || res.orders.length === 0) {
            container.innerHTML = `
                <div style="text-align: center; padding: 40px 15px; background: #ffffff; border: 1.5px dashed #cbd5e1; border-radius: 8px;">
                    <i class="fa fa-pause-circle text-muted" style="font-size: 40px; color: #94a3b8; margin-bottom: 12px;"></i>
                    <h4 style="margin: 0 0 6px 0; font-weight: 800; color: #334155;">No Active Held Orders</h4>
                    <p style="margin: 0; color: #64748b; font-size: 13px;">When a customer steps away to pick more items, click <strong>"Hold Order (F8)"</strong> to park their cart and keep the line moving.</p>
                </div>
            `;
            return;
        }

        let html = `
            <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                <table class="table table-hover" style="margin-bottom: 0; font-size: 13px;">
                    <thead style="background: #f1f5f9; color: #334155; font-weight: 700; font-size: 12px; text-transform: uppercase;">
                        <tr>
                            <th style="padding: 10px 14px;">PO Code & Time</th>
                            <th style="padding: 10px 14px;">Customer & Note</th>
                            <th style="padding: 10px 14px; text-align: center;">Items / Units</th>
                            <th style="padding: 10px 14px; text-align: right;">Total Due</th>
                            <th style="padding: 10px 14px; text-align: right; width: 220px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
        `;

        res.orders.forEach(o => {
            html += `
                <tr style="border-bottom: 1px solid #f1f5f9; vertical-align: middle;">
                    <td style="padding: 12px 14px;">
                        <span style="font-family: monospace; font-weight: 800; font-size: 13.5px; color: #1e3a8a; display: block;">
                            ${escapeHtml(o.po_id)}
                        </span>
                        <span style="font-size: 11.5px; color: #64748b;">
                            <i class="fa fa-clock-o"></i> ${escapeHtml(o.relative_time)}
                        </span>
                    </td>
                    <td style="padding: 12px 14px;">
                        <div style="font-weight: 700; color: #0f172a; font-size: 13.5px;">${escapeHtml(o.customer_name)}</div>
                        ${o.customer_phone ? `<div style="font-size: 11.5px; color: #64748b;"><i class="fa fa-phone"></i> ${escapeHtml(o.customer_phone)}</div>` : ''}
                        ${o.note ? `<div style="font-size: 11.5px; color: #b45309; font-style: italic; margin-top: 2px;"><i class="fa fa-sticky-note-o"></i> ${escapeHtml(o.note)}</div>` : ''}
                    </td>
                    <td style="padding: 12px 14px; text-align: center;">
                        <span class="badge" style="background: #e0e7ff; color: #3730a3; font-weight: 700; font-size: 12px; padding: 4px 8px;">
                            ${o.item_count} items (${o.total_units} pcs)
                        </span>
                    </td>
                    <td style="padding: 12px 14px; text-align: right; font-weight: 800; font-size: 14px; color: #0f172a;">
                        PHP ${round2(o.paid_amount).toFixed(2)}
                    </td>
                    <td style="padding: 12px 14px; text-align: right; white-space: nowrap;">
                        <button type="button" class="btn btn-success btn-xs" onclick="resumeHeldOrder('${escapeHtml(o.po_id)}')" style="font-weight: 800; padding: 5px 10px; background-color: #16a34a; border-color: #15803d; border-radius: 4px;" title="Load this order into POS cart to add items or complete payment">
                            <i class="fa fa-play"></i> Resume
                        </button>
                        <button type="button" class="btn btn-danger btn-xs" onclick="cancelHeldOrder('${escapeHtml(o.po_id)}')" style="font-weight: 700; padding: 5px 8px; margin-left: 4px; border-radius: 4px;" title="Cancel order and restore items to stock">
                            <i class="fa fa-times"></i>
                        </button>
                    </td>
                </tr>
            `;
        });

        html += `
                    </tbody>
                </table>
            </div>
        `;

        container.innerHTML = html;
    })
    .catch(err => {
        container.innerHTML = `
            <div class="alert alert-danger" style="margin: 10px 0;">
                <i class="fa fa-exclamation-triangle"></i> Network error: ${escapeHtml(err.message)}
            </div>
        `;
    });
}

function resumeHeldOrder(poId) {
    if (cart && cart.length > 0) {
        if (!confirm('Loading this held order will replace any unsaved items currently in the POS cart. Proceed?')) {
            return;
        }
    }
    window.location.href = 'pos.php?po_id=' + encodeURIComponent(poId);
}

function cancelHeldOrder(poId) {
    if (!confirm('Are you sure you want to cancel held order ' + poId + '?\nAll held items will be immediately returned to available stock.')) {
        return;
    }

    const payload = new URLSearchParams();
    payload.append('action', 'cancel_held_order');
    payload.append('po_id', poId);

    fetch('pos-hold-api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: payload.toString()
    })
    .then(r => r.json())
    .then(res => {
        if (!res.success) {
            alert(res.message || 'Failed to cancel held order.');
            return;
        }
        loadHeldOrdersList();
    })
    .catch(err => {
        alert('Network error: ' + err.message);
    });
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.innerText = text;
    return div.innerHTML;
}
</script>

<?php require_once('footer.php'); ?>
