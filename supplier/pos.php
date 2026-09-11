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
                'supplier_address' => !empty($supplier_info['supplier_address']) ? $supplier_info['supplier_address'] : ''
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
    $net_subtotal = 0.00;

    foreach ($cart_items as $item) {
        $item_type = (isset($item['item_type']) && $item['item_type'] === 'SPECIAL_ORDER') ? 'SPECIAL_ORDER' : 'STANDARD';
        $p_qty = max(1, intval($item['qty']));
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
        'net_subtotal' => $net_subtotal,
        'grand_total' => $net_subtotal + $delivery_cost
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
            // Authoritative line items & calculations directly from tbl_order
            $stmt_po_items = $pdo->prepare("SELECT * FROM tbl_order WHERE payment_id = ? AND supplier_id = ? ORDER BY id ASC");
            $stmt_po_items->execute([$paying_po_id, $supplier_id]);
            $po_db_items = $stmt_po_items->fetchAll(PDO::FETCH_ASSOC);

            $computed_subtotal = 0.0;
            $computed_discount_total = 0.0;
            $validated_items = [];

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

            $delivery_cost = max(0, floatval($existing_payment['paid_amount']) - ($computed_subtotal - $computed_discount_total));
            $grand_total = floatval($existing_payment['paid_amount']);
            if ($grand_total <= 0) {
                $grand_total = max(0, $computed_subtotal - $computed_discount_total + $delivery_cost);
            }

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

                // Atomic transaction update to tbl_payment
                $pdo->beginTransaction();
                try {
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
                        $grand_total,
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
                        'supplier_phone' => $supplier_info['supplier_phone']
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
            $net_subtotal = 0.00;

            foreach ($cart_items as $item) {
                $item_type = (isset($item['item_type']) && $item['item_type'] === 'SPECIAL_ORDER') ? 'SPECIAL_ORDER' : 'STANDARD';
                $p_qty = max(1, intval($item['qty']));
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
                $grand_total = $subtotal + $delivery_cost;
                $change_amount = max(0, $amount_tendered - $grand_total);

                $payment_id = 'POS-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));
                $payment_date = date('Y-m-d H:i:s');

                $tx_info = 'POS Terminal - Method: ' . $payment_method . ' | Tendered: ₱' . number_format($amount_tendered, 2) . ' | Change: ₱' . number_format($change_amount, 2);
                if ($total_discount_savings > 0) {
                    $tx_info .= ' | Total Discounts: -₱' . number_format($total_discount_savings, 2);
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

                // Insert items into tbl_order and update stock & link discount requests
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
                    'subtotal' => $subtotal,
                    'delivery_cost' => $delivery_cost,
                    'grand_total' => $grand_total,
                    'amount_tendered' => $amount_tendered,
                    'change_amount' => $change_amount,
                    'supplier_name' => $supplier_info['supplier_name'],
                    'supplier_address' => $supplier_info['supplier_address'],
                    'supplier_phone' => $supplier_info['supplier_phone']
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
$init_net_subtotal = 0.0;

if (!empty($active_pos_cart)) {
    foreach ($active_pos_cart as $ci) {
        $ci_q = max(1, intval($ci['qty'] ?? 1));
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

$init_delivery_cost = (!empty($saved_is_location) && !empty($saved_fulfillment['delivery_cost'])) ? floatval($saved_fulfillment['delivery_cost']) : 0.00;
$init_grand_total = $init_net_subtotal + $init_delivery_cost;

// Normalize items in active POS cart to ensure all JS/UI fields exist
if (!empty($active_pos_cart)) {
    foreach ($active_pos_cart as &$pi) {
        $p_type = (isset($pi['item_type']) && $pi['item_type'] === 'SPECIAL_ORDER') ? 'SPECIAL_ORDER' : 'STANDARD';
        $pi['item_type'] = $p_type;
        $pi['qty'] = max(1, intval($pi['qty']));
        $pi['price'] = max(0, floatval($pi['price']));
        if (!isset($pi['base_name']) || empty($pi['base_name'])) {
            $pi['base_name'] = isset($pi['name']) ? $pi['name'] : 'Product';
        }
        if ($p_type === 'STANDARD') {
            $p_id = intval($pi['id']);
            $stmt_pk = $pdo->prepare("SELECT p_name, p_qty, p_current_price FROM tbl_product WHERE p_id = ? AND supplier_id = ?");
            $stmt_pk->execute(array($p_id, $supplier_id));
            $pk_row = $stmt_pk->fetch(PDO::FETCH_ASSOC);
            if ($pk_row) {
                $pi['stock'] = intval($pk_row['p_qty']);
            } elseif (!isset($pi['stock'])) {
                $pi['stock'] = 0;
            }
        } else {
            $pi['stock'] = 999999;
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

// Group Products by End Level Category and Parent Base Name
$grouped_products = array();
$categories = array();
$total_inventory_items = count($raw_products);

foreach ($raw_products as $prod) {
    $ecat_name = !empty($prod['ecat_name']) ? trim($prod['ecat_name']) : 'Uncategorized';
    if (!in_array($ecat_name, $categories)) {
        $categories[] = $ecat_name;
    }

    $clean_price = floatval(preg_replace('/[^0-9.]/', '', strval($prod['p_current_price'])));
    $clean_stock = intval(preg_replace('/[^0-9]/', '', strval($prod['p_qty'])));
    $img_src = (!empty($prod['p_featured_photo']) && file_exists('../assets/uploads/'.$prod['p_featured_photo'])) 
        ? '../assets/uploads/'.$prod['p_featured_photo'] 
        : '../assets/uploads/photo-6.jpg';

    $parsed_spec = parseConstructionProductDetails($prod['p_name']);
    $base_name = $parsed_spec['base_name'];

    // Grouping key: ecat_id + sanitized base_name
    $group_key = $prod['ecat_id'] . '_' . strtolower(preg_replace('/[^a-z0-9]/', '', $base_name));

    $variant_item = array(
        'id' => intval($prod['p_id']),
        'sku' => !empty($prod['p_sku']) ? $prod['p_sku'] : ('SKU-' . str_pad($prod['p_id'], 5, '0', STR_PAD_LEFT)),
        'name' => $prod['p_name'],
        'base_name' => $base_name,
        'spec_label' => $parsed_spec['spec_label'],
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
        $grouped_products[$group_key] = array(
            'group_key' => $group_key,
            'base_name' => $base_name,
            'ecat_id' => $prod['ecat_id'],
            'ecat_name' => $ecat_name,
            'mcat_name' => $prod['mcat_name'] ?: '',
            'tcat_name' => $prod['tcat_name'] ?: '',
            'brand' => !empty($prod['p_brand']) ? $prod['p_brand'] : 'Generic',
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
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 6px 10px;
    border: 1.5px solid #cbd5e1;
    background: #fff;
    color: #1e293b;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.15s ease-in-out;
    text-decoration: none !important;
}
.pos-variant-chip:hover {
    border-color: #2563eb;
    background: #f8fafc;
    color: #2563eb;
}
.pos-variant-chip.active {
    border-color: #2563eb;
    background: #2563eb;
    color: #fff;
}
.pos-variant-chip.disabled {
    opacity: 0.5;
    cursor: not-allowed;
    background: #f1f5f9;
    border-color: #e2e8f0;
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
.pos-cat-pill {
    margin-right: 6px;
    margin-bottom: 6px;
    border-radius: 20px;
    padding: 6px 16px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
}
.pos-cat-pill.active {
    background-color: #2563eb !important;
    color: #fff !important;
    border-color: #2563eb !important;
}
</style>

<section class="content-header">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap;">
        <h1>
            <i class="fa fa-calculator" style="color: #2563eb;"></i> Point of Sale (POS)
            <small>Over-the-Counter Sales & Direct Billing</small>
        </h1>
        <div style="display: flex; gap: 6px; flex-wrap: wrap;">
            <a href="user-manual.php" target="_blank" class="btn btn-info btn-sm" style="font-weight: 700;"><i class="fa fa-book"></i> User Manual & SOP</a>
            <?php if (is_admin_or_manager_role($pos_user_role) || is_supervisor_role($pos_user_role) || $pos_user_role === 'CASHIER'): ?>
                <a href="returns.php" class="btn btn-default btn-sm"><i class="fa fa-undo"></i> Return History</a>
                <a href="order.php" class="btn btn-default btn-sm"><i class="fa fa-list"></i> Order History</a>
            <?php endif; ?>
            <?php if (is_admin_or_manager_role($pos_user_role)): ?>
                <a href="index.php" class="btn btn-default btn-sm"><i class="fa fa-dashboard"></i> Dashboard</a>
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
                    
                    <!-- Search and Filters -->
                    <div class="row" style="margin-bottom: 12px;">
                        <div class="col-sm-5 col-xs-12" style="margin-bottom: 6px;">
                            <div class="input-group">
                                <span class="input-group-addon" style="font-size: 16px;"><i class="fa fa-search"></i></span>
                                <input type="text" id="posSearchInput" class="form-control input-lg" style="height: 42px; font-size: 15px;" placeholder="Search product by name, brand, spec, or SKU..." onkeyup="filterPOSProducts()">
                                <span class="input-group-btn">
                                    <button class="btn btn-default input-lg" type="button" onclick="clearPOSSearch()" style="height: 42px;"><i class="fa fa-times"></i></button>
                                </span>
                            </div>
                        </div>
                        <div class="col-sm-7 col-xs-12 text-right" style="display: flex; justify-content: flex-end; align-items: center; gap: 8px; flex-wrap: wrap;">
                            <button type="button" class="btn btn-info input-lg" onclick="openPOSPrinterModal()" id="posPrinterStatusBtn" style="height: 42px; font-size: 13px; font-weight: 800; background-color: #0284c7; border-color: #0369a1; color: #fff; padding: 6px 14px; border-radius: 4px; box-shadow: 0 2px 5px rgba(2,132,199,0.25); display: inline-flex; align-items: center; gap: 6px;" title="Printer Configuration / Setup (Auto Detect, 210mm / 80mm / 58mm / A4)">
                                <i class="fa fa-print"></i> <span id="posPrinterBtnLabel">Printer: 210mm</span>
                            </button>
                            <button type="button" class="btn btn-warning input-lg" onclick="openSpecialOrderModal()" style="height: 42px; font-size: 13px; font-weight: 800; background-color: #d97706; border-color: #b45309; color: #fff; padding: 6px 12px; border-radius: 4px; box-shadow: 0 2px 5px rgba(217,119,6,0.25); display: inline-flex; align-items: center; gap: 5px;" title="Create custom/manual order item not in catalogue">
                                <i class="fa fa-plus-circle"></i> + Special Order
                            </button>
                            <button type="button" class="btn btn-danger input-lg" onclick="openReturnModal()" style="height: 42px; font-size: 13px; font-weight: 800; background-color: #dc2626; border-color: #b91c1c; color: #fff; padding: 6px 14px; border-radius: 4px; box-shadow: 0 2px 5px rgba(220,38,38,0.25); display: inline-flex; align-items: center; gap: 5px;" title="Search completed orders and process item returns & refunds">
                                <i class="fa fa-undo"></i> RETURN
                            </button>
                            <span class="text-muted" style="line-height: 42px; font-size: 12.5px;">
                                Products: <strong id="productCount"><?php echo count($grouped_products); ?></strong>
                                <span style="font-size: 11px; color: #64748b;">(<?php echo $total_inventory_items; ?> items)</span>
                            </span>
                        </div>
                    </div>

                    <!-- Category Filter Pills (End Level Categories) -->
                    <?php if (!empty($categories)): ?>
                    <div style="margin-bottom: 15px; overflow-x: auto; white-space: nowrap; padding-bottom: 5px;">
                        <button type="button" class="btn btn-default btn-sm pos-cat-pill active" onclick="filterCategory('all', this)">All Categories (<?php echo count($grouped_products); ?>)</button>
                        <?php foreach ($categories as $cat): ?>
                            <button type="button" class="btn btn-default btn-sm pos-cat-pill" onclick="filterCategory('<?php echo htmlspecialchars(addslashes($cat)); ?>', this)"><?php echo htmlspecialchars($cat); ?></button>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <!-- Products Grid (Grouped by End Level Category & Parent Product) -->
                    <div id="posProductGrid">
                        <?php if (count($grouped_products) > 0): ?>
                            <?php foreach ($grouped_products as $group_key => $group): 
                                $is_out_of_stock = ($group['total_stock'] <= 0);
                                $variant_count = count($group['variants']);
                                
                                // Build thorough search index for this parent group
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
                                $search_index = strtolower(implode(' ', array_unique($search_terms)));
                            ?>
                            <div class="pos-product-item" 
                                 data-name="<?php echo htmlspecialchars($search_index, ENT_QUOTES, 'UTF-8'); ?>"
                                 data-category="<?php echo htmlspecialchars($group['ecat_name'], ENT_QUOTES, 'UTF-8'); ?>"
                                 data-group='<?php echo htmlspecialchars(json_encode($group), ENT_QUOTES, 'UTF-8'); ?>'
                                 onclick="<?php echo $is_out_of_stock ? 'void(0);' : 'handleGroupCardClick(this);'; ?>">
                                
                                <div class="pos-product-card <?php echo $is_out_of_stock ? 'out-of-stock' : ''; ?>">
                                    <!-- End Category Badge -->
                                    <span class="pos-cat-badge" title="<?php echo htmlspecialchars($group['ecat_name']); ?>">
                                        <?php echo htmlspecialchars($group['ecat_name']); ?>
                                    </span>

                                    <!-- Stock Badge -->
                                    <span class="label pos-stock-badge <?php echo $is_out_of_stock ? 'label-danger' : ($group['total_stock'] < 10 ? 'label-warning' : 'label-success'); ?>">
                                        <?php echo $is_out_of_stock ? 'Out of Stock' : $group['total_stock'] . ' in stock'; ?>
                                    </span>
                                    
                                    <!-- Product Image -->
                                    <div class="pos-product-img" style="background-image: url('<?php echo htmlspecialchars($group['photo']); ?>');"></div>
                                    
                                    <!-- Parent Product Info -->
                                    <div class="pos-parent-info">
                                        <div class="pos-parent-name" title="<?php echo htmlspecialchars($group['base_name']); ?>">
                                            <?php echo htmlspecialchars($group['base_name']); ?>
                                        </div>
                                        <div class="pos-parent-meta">
                                            <span class="text-muted"><i class="fa fa-tag"></i> <?php echo htmlspecialchars($group['brand']); ?></span>
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
                        <button type="button" class="btn btn-default btn-xs text-danger" onclick="clearCart()"><i class="fa fa-trash"></i> Clear</button>
                    </div>
                </div>

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
                                        if (empty($specsArr) && !empty($item['product_details']) && !$isSpecialOrder) {
                                            $specsArr[] = '<span style="color: #64748b; font-size: 11px;">' . htmlspecialchars($item['product_details']) . '</span>';
                                        }
                                        $refOrSku = $isSpecialOrder
                                            ? (!empty($item['special_order_reference']) ? 'Ref: ' . htmlspecialchars($item['special_order_reference']) . ' | ' : '')
                                            : (!empty($item['sku']) ? 'SKU: ' . htmlspecialchars($item['sku']) . ' | ' : '');
                                    ?>
                                        <tr <?php echo $isSpecialOrder ? 'style="background-color: #fffbeb;"' : ''; ?>>
                                            <td style="padding: 8px 4px;">
                                                <?php if ($isSpecialOrder): ?>
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
                                                    <?php echo $refOrSku; ?>&#8369;<?php echo number_format($item['price'], 2); ?> each
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
                                                <?php if ($dAmt > 0): ?>
                                                    <span style="text-decoration: line-through; color: #94a3b8; font-size: 11px; display: block;">&#8369;<?php echo number_format($grossLineTotal, 2); ?></span>
                                                    <span style="font-weight: 800; font-size: 15px; color: #047857;">&#8369;<?php echo number_format($netLine, 2); ?></span>
                                                <?php else: ?>
                                                    <span style="font-weight: 800; font-size: 15px; color: <?php echo $isSpecialOrder ? '#b45309' : '#1e40af'; ?>;">&#8369;<?php echo number_format($grossLineTotal, 2); ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td style="padding: 8px 2px; text-align: center;">
                                                <?php if (empty($paying_existing_po)): ?>
                                                    <button type="button" class="btn btn-link text-danger" onclick="removeFromCart(<?php echo $itemIdEsc; ?>)" style="padding: 2px 4px; font-size: 16px;" title="Remove item"><i class="fa fa-times-circle"></i></button>
                                                <?php else: ?>
                                                    <span class="text-muted" title="Item locked to Purchase Order" style="font-size: 13px;"><i class="fa fa-lock"></i></span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pricing & Fulfillment Summary -->
                    <div style="background: #f8fafc; border: 2px solid #e2e8f0; border-radius: 8px; padding: 14px; margin-bottom: 15px;">
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
                            <span style="font-size: 18px; font-weight: 800; color: #1e3a8a;">Grand Total:</span>
                            <span style="font-size: 24px; font-weight: 900; color: #1d4ed8;">&#8369;<span id="posGrandTotal"><?php echo number_format($init_grand_total, 2); ?></span></span>
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
                            <i class="fa fa-check-circle"></i> Complete Sale & Print Receipt
                        </button>
                    <?php endif; ?>

                </form>

            </div>
        </div>

    </div>
</section>

<!-- Interactive Product / Variant Selection Modal -->
<div class="modal fade" id="posVariantModal" tabindex="-1" role="dialog" aria-labelledby="posVariantModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-md" role="document">
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
                    <div id="vModalChipsList" style="display: flex; flex-wrap: wrap; gap: 6px; max-height: 120px; overflow-y: auto; padding: 2px;"></div>
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
                            <i class="fa fa-info-circle"></i> <strong>Audit Policy:</strong> Submitting this return will create a <strong>PENDING APPROVAL</strong> request. A Manager or Administrator must review and approve it in the Return Approval portal before the refund is finalized and inventory adjusted. Cashiers cannot self-approve returns.
                        </div>

                        <!-- Confirmation / Action Buttons -->
                        <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid #e2e8f0; padding-top: 15px;">
                            <button type="button" class="btn btn-default" onclick="backToReturnOrdersList()" style="font-weight: 600;">
                                Cancel
                            </button>
                            <button type="submit" id="retProcessSubmitBtn" class="btn btn-danger btn-lg" style="background: #dc2626; border-color: #b91c1c; font-weight: 800; padding: 10px 24px; font-size: 16px; border-radius: 6px; box-shadow: 0 4px 10px rgba(220,38,38,0.3);">
                                <i class="fa fa-paper-plane"></i> Submit Return Request
                            </button>
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

<!-- ========================================== -->
<!-- PRINTER CONFIGURATION / SETUP MODAL        -->
<!-- ========================================== -->
<div class="modal fade" id="posPrinterModal" tabindex="-1" role="dialog" aria-labelledby="posPrinterModalLabel" aria-hidden="true">
    <div class="modal-dialog" style="max-width: 600px; margin-top: 35px;">
        <div class="modal-content" style="border-radius: 8px; overflow: hidden; box-shadow: 0 12px 35px rgba(0,0,0,0.35);">
            <div class="modal-header" style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color: #fff; padding: 16px 22px;">
                <button type="button" class="close" data-dismiss="modal" aria-hidden="true" style="color: #fff; opacity: 0.85; font-size: 24px;">&times;</button>
                <h4 class="modal-title" id="posPrinterModalLabel" style="font-weight: 800; font-size: 18px; display: flex; align-items: center; gap: 8px; margin: 0;">
                    <i class="fa fa-print text-primary"></i> Printer Configuration / Setup
                </h4>
                <div style="font-size: 12px; color: #94a3b8; margin-top: 3px; font-weight: 400;">
                    Configure thermal, A4 and PDF output
                </div>
            </div>
            <div class="modal-body" style="padding: 20px 22px; font-size: 13px; color: #1e293b; max-height: 80vh; overflow-y: auto;">
                
                <!-- Live Detection Status Box -->
                <div id="posPrinterStatusBox" style="background: #f0f9ff; border: 1.5px solid #bae6fd; border-radius: 6px; padding: 12px 16px; margin-bottom: 14px; display: flex; align-items: center; justify-content: space-between;">
                    <div style="flex: 1; padding-right: 10px;">
                        <div style="font-size: 11px; text-transform: uppercase; font-weight: 800; color: #0369a1; letter-spacing: 0.5px;">PRINTER DETECTION</div>
                        <div id="posPrinterStatusText" style="font-size: 13px; font-weight: 700; color: #0c4a6e; margin-top: 2px;">
                            <span style="color: #0284c7;"><i class="fa fa-info-circle"></i> Auto Detect Active &bull; Ready (Browser / OS Print Dialog)</span>
                        </div>
                    </div>
                    <button type="button" class="btn btn-default btn-xs" onclick="refreshPOSPrinters()" style="font-weight: 700; border-radius: 4px; padding: 6px 12px; background: #fff; border-color: #93c5fd;" title="Re-probe local printers, USB, and print daemons">
                        <i class="fa fa-refresh text-primary"></i> Refresh Detection
                    </button>
                </div>

                <!-- ========================================== -->
                <!-- ACTIVE CONFIGURATION CARD (TOP)            -->
                <!-- ========================================== -->
                <div style="background: #f0fdf4; border: 1.5px solid #86efac; border-radius: 8px; padding: 12px 16px; margin-bottom: 14px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; border-bottom: 1px solid #bbf7d0; padding-bottom: 4px;">
                        <span style="font-weight: 800; color: #15803d; font-size: 11.5px; text-transform: uppercase; letter-spacing: 0.5px; display: flex; align-items: center; gap: 6px;">
                            <i class="fa fa-sliders"></i> ACTIVE CONFIGURATION
                        </span>
                        <span class="label label-success" style="font-size: 9.5px; font-weight: 700; background-color: #16a34a; padding: 2px 7px;">ACTIVE</span>
                    </div>
                    <div id="posCompatibilityBadge" style="font-size: 12px; color: #14532d; line-height: 1.6;">
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 6px 12px; margin-bottom: 4px;">
                            <div><strong>Printer:</strong> <span id="posActivePrinterVal" style="color: #0369a1; font-weight: 700;">AUTO DETECT</span></div>
                            <div><strong>Primary Output:</strong> <span style="color: #0284c7; font-weight: 700;">THERMAL PRINTER</span></div>
                            <div><strong>Paper Width:</strong> <span id="posActiveWidthVal" style="font-weight: 700; color: #0f766e;">210 mm (Max)</span></div>
                            <div><strong>Font:</strong> <span id="posActiveFontNameVal" style="font-weight: 700; color: #1e293b;">Courier New</span></div>
                            <div><strong>Font Size:</strong> <span id="posActiveFontVal" style="font-weight: 700; color: #0369a1;">12 pt</span></div>
                            <div><strong>Secondary:</strong> <span id="posActiveA4Val" style="font-weight: 700; color: #475569;">A4 Portrait</span></div>
                            <div><strong>PDF:</strong> <span style="font-weight: 700; color: #c2410c;">Preview / Export Only</span></div>
                            <div><strong>Copies:</strong> <span id="posActiveCopiesVal" style="font-weight: 700; color: #334155;">1 Copy</span></div>
                        </div>
                        <div id="posLayoutDesc" style="font-size: 11px; color: #166534; border-top: 1px dashed #86efac; padding-top: 4px; margin-top: 4px;">Primary: Thermal • 210 mm (Max) • 12 pt min | Secondary: A4 Portrait | PDF: Preview Only</div>
                    </div>
                </div>

                <form id="posPrinterForm">
                    <!-- ========================================== -->
                    <!-- SECTION 1: PRIMARY — THERMAL PRINTER       -->
                    <!-- ========================================== -->
                    <div id="posModeCardThermal" style="border: 1.5px solid #0284c7; background: #f0f9ff; border-radius: 8px; padding: 14px 16px; margin-bottom: 14px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; border-bottom: 1.5px solid #bae6fd; padding-bottom: 6px;">
                            <span style="font-weight: 800; color: #0369a1; font-size: 12.5px; display: flex; align-items: center; gap: 6px;">
                                <i class="fa fa-star text-warning"></i> PRIMARY — THERMAL PRINTER
                            </span>
                            <span class="label label-primary" style="font-size: 9.5px; font-weight: 700; background-color: #0284c7; padding: 2px 7px;">PRIMARY / DEFAULT</span>
                        </div>

                        <!-- Thermal Printer Target Selection -->
                        <div class="form-group" style="margin-bottom: 10px;">
                            <label style="font-weight: 700; color: #0f172a; margin-bottom: 4px; font-size: 12px;">
                                <i class="fa fa-hdd-o text-primary"></i> Printer:
                            </label>
                            <select id="posPrinterSelect" class="form-control input-sm" style="font-size: 12.5px; height: 36px; border-radius: 4px;" onchange="handlePrinterSelectChange()">
                                <option value="system_default">AUTO DETECT (System Default / Browser Print Dialog)</option>
                            </select>
                            <div style="font-size: 11px; color: #0284c7; margin-top: 3px;">
                                <i class="fa fa-check-circle"></i> Printer will be detected automatically
                            </div>
                        </div>

                        <!-- Thermal Paper Width Preset (Hard Maximum: 210 mm) -->
                        <div class="form-group" style="margin-bottom: 10px;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                                <label style="font-weight: 700; color: #0f172a; margin: 0; font-size: 12px;">
                                    <i class="fa fa-file-text-o text-success"></i> Paper Width:
                                </label>
                                <span style="font-size: 11px; font-weight: 700; color: #0369a1;">Maximum: 210 mm</span>
                            </div>
                            <select id="posPaperWidth" class="form-control input-sm" style="font-size: 12.5px; height: 36px; border-radius: 4px; font-weight: 700; color: #0f766e; background-color: #f0fdf4; border-color: #86efac;" onchange="handlePaperWidthChange()">
                                <option value="210" selected>210 mm — Default / Maximum</option>
                                <option value="80">80 mm — Standard 3-inch POS Thermal</option>
                                <option value="58">58 mm — Standard 2-inch POS Thermal</option>
                                <option value="custom">Custom (Max 210 mm)...</option>
                            </select>
                        </div>

                        <!-- Custom Width Input (Shown only when 'custom' is selected) -->
                        <div id="posCustomWidthGroup" class="form-group" style="display: none; margin-bottom: 10px; background: #fffbeb; padding: 10px 14px; border-radius: 6px; border: 1px solid #fde68a;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                                <label style="font-weight: 700; color: #92400e; margin: 0; font-size: 11.5px;">
                                    <i class="fa fa-pencil text-warning"></i> Custom Paper Width (mm):
                                </label>
                                <span style="font-size: 10.5px; font-weight: 700; color: #b45309;">Maximum: 210 mm</span>
                            </div>
                            <div class="input-group">
                                <input type="number" id="posCustomWidthInput" class="form-control input-sm" value="210" min="40" max="210" style="height: 34px; font-weight: 700;" oninput="if(this.value>210)this.value=210; handlePaperWidthChange();">
                                <span class="input-group-addon">mm</span>
                            </div>
                            <div style="font-size: 10.5px; color: #b45309; margin-top: 3px;">Maximum: 210 mm. Thermal roll width cannot exceed 210 mm.</div>
                        </div>

                        <!-- Thermal Typography Section (Editable Font Name & Selectable/Editable Font Size) -->
                        <div style="background: #ffffff; border: 1.5px solid #bae6fd; border-radius: 6px; padding: 12px 14px; margin-top: 10px;">
                            <div style="font-size: 11px; text-transform: uppercase; font-weight: 800; color: #0369a1; letter-spacing: 0.5px; margin-bottom: 8px; display: flex; align-items: center; gap: 6px;">
                                <i class="fa fa-font text-info"></i> THERMAL TYPOGRAPHY &amp; READABILITY
                            </div>
                            
                            <div class="row" style="margin-bottom: 8px;">
                                <!-- Font Name (Select / Editable) -->
                                <div class="col-xs-12 col-sm-6" style="margin-bottom: 6px;">
                                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 3px;">
                                        <label style="font-weight: 700; font-size: 12px; color: #1e293b; margin: 0;">
                                            Font Name:
                                        </label>
                                        <span style="font-size: 10.5px; color: #64748b;">Select or type custom</span>
                                    </div>
                                    <div class="input-group input-group-sm" style="width: 100%;">
                                        <input type="text" id="posThermalFontName" list="posThermalFontList" class="form-control" value="Courier New" style="height: 34px; font-weight: 700; font-size: 12.5px; color: #0f172a;" oninput="updateCompatibilityBadge()" onchange="updateCompatibilityBadge()" placeholder="e.g. Courier New">
                                        <datalist id="posThermalFontList">
                                            <option value="Courier New">Courier New (Monospace &bull; Standard POS)</option>
                                            <option value="Consolas">Consolas (Clear Monospace)</option>
                                            <option value="Lucida Console">Lucida Console</option>
                                            <option value="Arial">Arial (Clean Sans-Serif)</option>
                                            <option value="Tahoma">Tahoma (Compact Sans-Serif)</option>
                                            <option value="Verdana">Verdana (Wide Sans-Serif)</option>
                                            <option value="Liberation Mono">Liberation Mono</option>
                                            <option value="DejaVu Sans Mono">DejaVu Sans Mono</option>
                                        </datalist>
                                    </div>
                                    <div style="font-size: 10.5px; color: #64748b; margin-top: 2px;">Use a clear, readable font suitable for thermal printing.</div>
                                </div>

                                <!-- Font Size (Range 12 pt to 20 pt Selection & Slider) -->
                                <div class="col-xs-12 col-sm-6" style="margin-bottom: 6px; display: block !important;">
                                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 3px;">
                                        <label style="font-weight: 700; font-size: 12px; color: #1e293b; margin: 0;">
                                            Font Size (12 &ndash; 20 pt):
                                        </label>
                                        <span id="posThermalFontSizeBadge" style="font-size: 11px; font-weight: 800; color: #0369a1; background: #e0f2fe; padding: 1px 7px; border-radius: 3px; border: 1px solid #bae6fd;">12 pt</span>
                                    </div>
                                    <div style="display: flex; gap: 6px; align-items: center;">
                                        <select id="posThermalDefaultFontSize" class="form-control input-sm" style="height: 34px; font-size: 12.5px; font-weight: 700; color: #0369a1; flex: 1;" onchange="handleFontSizePresetChange(this.value)">
                                            <optgroup label="Standard Range (12 pt — 20 pt)">
                                                <option value="12" selected>12 pt (Standard / Compact)</option>
                                                <option value="13">13 pt</option>
                                                <option value="14">14 pt (Medium)</option>
                                                <option value="15">15 pt</option>
                                                <option value="16">16 pt (Large)</option>
                                                <option value="17">17 pt</option>
                                                <option value="18">18 pt (Extra Large)</option>
                                                <option value="19">19 pt</option>
                                                <option value="20">20 pt (High-Visibility / Max)</option>
                                            </optgroup>
                                            <option value="custom">Custom pt...</option>
                                        </select>
                                        <div id="posThermalCustomSizeGroup" style="display: block; width: 90px; flex-shrink: 0;">
                                            <div class="input-group input-group-sm">
                                                <input type="number" id="posThermalCustomFontSize" class="form-control" value="12" min="12" max="36" style="height: 34px; font-weight: 700; text-align: center; color: #0369a1;" oninput="if(this.value<12)this.value=12; syncFontSizeFromCustom(this.value); updateCompatibilityBadge();" onchange="if(this.value<12)this.value=12; syncFontSizeFromCustom(this.value); updateCompatibilityBadge();" title="Exact font size in points (pt)">
                                                <span class="input-group-addon" style="font-size: 10.5px; padding: 4px 6px; font-weight: 700;">pt</span>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- Interactive Range Slider (12 pt to 20 pt) -->
                                    <div style="margin-top: 6px; display: flex; align-items: center; gap: 8px;">
                                        <span style="font-size: 10px; font-weight: 700; color: #64748b;">12 pt</span>
                                        <input type="range" id="posThermalFontSizeRange" min="12" max="20" step="1" value="12" style="flex: 1; height: 18px; cursor: pointer; accent-color: #0284c7; width: 100%;" oninput="handleFontSizeRangeInput(this.value)" title="Slide to adjust font size between 12 pt and 20 pt">
                                        <span style="font-size: 10px; font-weight: 700; color: #64748b;">20 pt</span>
                                    </div>
                                    <input type="hidden" id="posThermalMinFontSize" value="12">
                                    <div style="font-size: 10.5px; color: #64748b; margin-top: 3px;">Range selection: 12 pt to 20 pt enterprise thermal standard.</div>
                                </div>
                            </div>

                            <div style="margin-top: 6px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px;">
                                <label style="font-weight: 700; font-size: 11.5px; color: #334155; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; margin: 0;">
                                    <input type="checkbox" id="posThermalBoldImportant" checked style="margin: 0;" onchange="updateCompatibilityBadge()">
                                    <strong>Bold important text</strong> (Item names, totals, receipt no.)
                                </label>
                                
                                <!-- Advanced Font Strategy dropdown preserved as subtle option -->
                                <div style="font-size: 11px; color: #64748b; display: inline-flex; align-items: center; gap: 4px;">
                                    <span>Strategy:</span>
                                    <select id="posThermalFontStrategy" class="form-control input-xs" style="height: 24px; font-size: 10.5px; padding: 1px 4px; display: inline-block; width: auto;" onchange="updateCompatibilityBadge()">
                                        <option value="native" selected>Native / High-Contrast</option>
                                        <option value="monospace">System Monospace</option>
                                        <option value="custom_ttf">Installed / Custom TTF</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ========================================== -->
                    <!-- SECTION: QUICK THERMAL TESTS / PRINTER TEST -->
                    <!-- ========================================== -->
                    <div style="border: 1.5px solid #0284c7; background: #f0f9ff; border-radius: 8px; padding: 12px 16px; margin-bottom: 14px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; border-bottom: 1px solid #bae6fd; padding-bottom: 4px;">
                            <span style="font-weight: 800; color: #0369a1; font-size: 12px; display: flex; align-items: center; gap: 6px;">
                                <i class="fa fa-print"></i> QUICK THERMAL PAPER TEST PROFILES
                            </span>
                            <span style="font-size: 10px; font-weight: 700; color: #0369a1;">ISOLATED TEST &bull; CONFIG SAFE</span>
                        </div>
                        <div style="font-size: 11px; color: #0c4a6e; margin-bottom: 10px; line-height: 1.35;">
                            Test printing instantly using specific roll widths. Tests run isolated and <strong>will not change</strong> your saved paper width.
                        </div>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(115px, 1fr)); gap: 8px;">
                            <button type="button" class="btn btn-primary btn-sm" onclick="testPrintPOS('thermal210')" style="font-weight: 700; background-color: #0284c7; border-color: #0369a1; padding: 7px 8px; font-size: 12px;" title="Test print maximum 210mm thermal layout">
                                <i class="fa fa-print"></i> Test 210 mm
                            </button>
                            <button type="button" class="btn btn-info btn-sm" onclick="testPrintPOS('thermal80')" style="font-weight: 700; background-color: #0ea5e9; border-color: #0284c7; padding: 7px 8px; font-size: 12px;" title="Test print standard 80mm (3-inch) POS receipt">
                                <i class="fa fa-print"></i> Test 80 mm
                            </button>
                            <button type="button" class="btn btn-default btn-sm" onclick="testPrintPOS('thermal58')" style="font-weight: 700; background-color: #ffffff; border-color: #94a3b8; color: #1e293b; padding: 7px 8px; font-size: 12px;" title="Test print compact 58mm (2-inch) thermal receipt">
                                <i class="fa fa-print"></i> Test 58 mm
                            </button>
                            <button type="button" class="btn btn-warning btn-sm" onclick="testPrintPOS('thermal50')" style="font-weight: 700; background-color: #f59e0b; border-color: #d97706; color: #ffffff; padding: 7px 8px; font-size: 12px;" title="Test print 50mm narrow profile without altering saved configuration">
                                <i class="fa fa-wrench"></i> Test 50 mm
                            </button>
                        </div>
                    </div>

                    <!-- ========================================== -->
                    <!-- SECTION 2: SECONDARY PRINTER — STANDARD A4 -->
                    <!-- ========================================== -->
                    <div id="posModeCardNormal" style="border: 1.5px solid #cbd5e1; background: #f8fafc; border-radius: 8px; padding: 12px 16px; margin-bottom: 14px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; border-bottom: 1.5px solid #e2e8f0; padding-bottom: 6px;">
                            <span style="font-weight: 800; color: #334155; font-size: 12px; display: flex; align-items: center; gap: 6px;">
                                <i class="fa fa-print text-muted"></i> SECONDARY — STANDARD A4 PRINTER
                            </span>
                            <span class="label label-default" style="font-size: 9.5px; font-weight: 700; padding: 2px 7px;">SECONDARY / A4</span>
                        </div>

                        <div class="form-group" style="margin-bottom: 8px;">
                            <label style="font-weight: 700; color: #0f172a; margin-bottom: 3px; font-size: 11.5px;">
                                <i class="fa fa-hdd-o text-muted"></i> A4 Printer:
                            </label>
                            <select id="posA4PrinterSelect" class="form-control input-sm" style="font-size: 12px; height: 34px; border-radius: 4px;">
                                <option value="system_a4">AUTO DETECT (Office Printer / Laser / Inkjet via OS Dialog)</option>
                            </select>
                        </div>

                        <div class="row">
                            <div class="col-xs-6">
                                <label style="font-weight: 700; font-size: 11px; color: #334155; margin-bottom: 2px;">Paper:</label>
                                <div style="background: #ffffff; border: 1px solid #cbd5e1; border-radius: 4px; padding: 6px 10px; font-size: 11.5px; font-weight: 700; color: #1e293b;">
                                    A4 (210 &times; 297 mm)
                                </div>
                            </div>
                            <div class="col-xs-6">
                                <label style="font-weight: 700; font-size: 11px; color: #334155; margin-bottom: 2px;">Orientation:</label>
                                <select id="posA4Orientation" class="form-control input-sm" style="height: 32px; font-size: 11.5px; font-weight: 600;" onchange="updateCompatibilityBadge()">
                                    <option value="portrait" selected>Portrait</option>
                                    <option value="landscape">Landscape</option>
                                </select>
                            </div>
                        </div>
                        <div style="font-size: 10.5px; color: #64748b; margin-top: 6px; line-height: 1.35;">
                            <i class="fa fa-info-circle text-info"></i> Thermal roll width and thermal font settings do not alter A4 print layout.
                        </div>

                        <div style="margin-top: 8px; text-align: right;">
                            <button type="button" class="btn btn-default btn-sm" onclick="testPrintA4PDF()" style="font-weight: 700; background: #ffffff; border-color: #cbd5e1; color: #334155;" title="Test print on Standard A4 Sheet">
                                <i class="fa fa-file-text-o text-muted"></i> Test A4 Print
                            </button>
                        </div>
                    </div>

                    <!-- ========================================== -->
                    <!-- SECTION 3: PDF PREVIEW / EXPORT            -->
                    <!-- ========================================== -->
                    <div id="posModeCardPdf" style="border: 1.5px solid #fed7aa; background: #fffaf5; border-radius: 8px; padding: 10px 14px; margin-bottom: 14px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                            <span style="font-weight: 800; color: #c2410c; font-size: 12px; display: flex; align-items: center; gap: 6px;">
                                <i class="fa fa-file-pdf-o text-danger"></i> PDF PREVIEW / EXPORT
                            </span>
                            <span class="label label-warning" style="font-size: 9px; font-weight: 700; background-color: #ea580c; padding: 2px 6px;">PREVIEW ONLY</span>
                        </div>
                        <div style="font-size: 11px; color: #9a3412; line-height: 1.35; margin-bottom: 8px;">
                            PDF is for preview/export only. It is not a physical printer.
                        </div>
                        <button type="button" class="btn btn-default btn-xs" onclick="testPrintPOS('pdf_preview')" style="font-weight: 700; border-color: #fdba74; color: #c2410c; background: #ffffff; padding: 5px 12px;">
                            <i class="fa fa-eye"></i> Preview PDF
                        </button>
                    </div>

                    <!-- ========================================== -->
                    <!-- SECTION 4: PRINT COPIES                    -->
                    <!-- ========================================== -->
                    <div class="form-group" style="margin-bottom: 14px;">
                        <label style="font-weight: 700; color: #1e293b; margin-bottom: 3px; font-size: 11.5px;">
                            <i class="fa fa-copy text-info"></i> PRINT COPIES:
                        </label>
                        <select id="posPrintCopies" class="form-control input-sm" style="height: 34px; border-radius: 4px; font-weight: 700; max-width: 140px;" onchange="updateCompatibilityBadge()">
                            <option value="1" selected>1</option>
                            <option value="2">2</option>
                            <option value="3">3</option>
                            <option value="4">4</option>
                            <option value="5">5</option>
                        </select>
                    </div>

                    <!-- Legacy Compatibility Inputs (Preserved for backward-compatibility with existing JS) -->
                    <input type="hidden" id="posPrinterType" value="thermal">
                    <input type="hidden" id="posA4PrintWidthInput" value="80">
                    <input type="hidden" id="posA4PrintWidthRange" value="80">
                    <span id="posPdfWidthBadgeVal" style="display: none;">80</span>
                    <input type="radio" name="posPrinterModeRadio" id="posModeRadioThermal" value="thermal" checked style="display: none;">
                    <input type="radio" name="posPrinterModeRadio" id="posModeRadioNormal" value="normal" style="display: none;">
                    <input type="radio" name="posPrinterModeRadio" id="posModeRadioPdf" value="pdf" style="display: none;">

                    <!-- Security Notice -->
                    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 10px 14px; font-size: 11.5px; color: #475569; line-height: 1.45;">
                        <strong><i class="fa fa-shield text-primary"></i> Printing Settings:</strong>
                        Thermal printing uses the configured paper width and font settings. For best readability, use a thermal font size of 12 pt or larger. Maximum thermal width: 210 mm.
                    </div>
                </form>

            </div>
            <div class="modal-footer" style="background: #f8fafc; border-top: 1px solid #e2e8f0; padding: 12px 20px; display: flex; justify-content: flex-end; align-items: center; gap: 8px;">
                <button type="button" class="btn btn-default btn-sm" data-dismiss="modal" style="font-weight: 700; padding: 6px 16px;">Cancel</button>
                <button type="button" class="btn btn-primary btn-sm" onclick="savePOSPrinterSettings()" style="font-weight: 700; background-color: #0284c7; border-color: #0284c7; padding: 6px 20px;">
                    <i class="fa fa-save"></i> Save Configuration
                </button>
            </div>
        </div>
    </div>
</div>

<?php if ($pos_success_receipt): ?>
<div class="modal fade in" id="posSuccessModal" tabindex="-1" role="dialog" style="display: block; background: rgba(0,0,0,0.6);">
    <div class="modal-dialog" role="document" style="max-width: 680px;">
        <div class="modal-content" style="border-radius: 8px; overflow: hidden; box-shadow: 0 10px 35px rgba(0,0,0,0.3);">
            <div class="modal-header bg-green" style="background-color: #10b981 !important; color: #fff; padding: 12px 18px;">
                <button type="button" class="close" data-dismiss="modal" onclick="closeReceiptModal()" style="color: #fff; opacity: 0.9; font-size: 22px;">&times;</button>
                <h4 class="modal-title" style="font-weight: 700; font-size: 15px; margin: 0; display: flex; align-items: center; gap: 8px;">
                    <i class="fa fa-check-circle"></i> Print Receipt &bull; Sale Completed
                </h4>
            </div>
            
            <div style="padding: 16px 20px 0 20px;">
                <!-- Simple Information & Print Settings Banner (Screen Only) -->
                <div class="no-print" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 12px 16px; margin-bottom: 14px; font-family: Arial, sans-serif; font-size: 10.5pt; color: #1e293b;">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 12px;">
                        <div style="flex: 1; min-width: 200px;">
                            <div style="font-size: 9.5pt; font-weight: 700; text-transform: uppercase; color: #64748b; margin-bottom: 4px;">Document Details</div>
                            <div style="line-height: 1.45;">
                                <div><strong>Document:</strong> Sales Receipt</div>
                                <div><strong>Order No:</strong> <?php echo htmlspecialchars($pos_success_receipt['payment_id']); ?></div>
                                <div><strong>Customer:</strong> <?php echo htmlspecialchars($pos_success_receipt['customer_name']); ?></div>
                            </div>
                        </div>
                        <div style="flex: 1; min-width: 200px;">
                            <div style="font-size: 9.5pt; font-weight: 700; text-transform: uppercase; color: #64748b; margin-bottom: 4px;">Print Settings</div>
                            <div style="line-height: 1.45;">
                                <div><strong>Output Mode:</strong> <span class="receipt-summary-mode" style="color: #0284c7; font-weight: 700;">Thermal Printer (Primary Default)</span></div>
                                <div><strong>Paper Size:</strong> <span class="receipt-summary-paper">200 mm Roll</span></div>
                                <div><strong>Print Width:</strong> <span class="receipt-summary-width">190 mm</span></div>
                                <div><strong>Typography:</strong> <span class="receipt-summary-font">Enterprise Thermal Monospace</span></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-body" id="posPrintReceiptArea" style="padding: 0 20px 16px 20px; font-family: Arial, Helvetica, sans-serif; color: #000; font-size: 10pt; line-height: 1.3;">
                
                <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 4px; padding: 16px;">
                    <!-- 1. Receipt Header (Single Row Company Name, Left Aligned) -->
                    <div style="text-align: left; margin-bottom: 8px; border-bottom: 1pt solid #000; padding-bottom: 6px; width: 100%;">
                        <div class="pos-company-header" style="font-size: 12pt; font-weight: bold; text-transform: uppercase; white-space: nowrap; overflow: visible; color: #000; line-height: 1.2;">SAM &amp; INRI CONSTRUCTION SUPPLY</div>
                        <div style="font-size: 10pt; margin-top: 2px; color: #000;">Tel: <?php echo !empty($pos_success_receipt['supplier_phone']) ? htmlspecialchars($pos_success_receipt['supplier_phone']) : '09612735733'; ?></div>
                        <div style="font-size: 10pt; font-weight: bold; text-transform: uppercase; margin-top: 3px; color: #000;">OFFICIAL SALES RECEIPT</div>
                    </div>

                    <!-- 2. Transaction Information Grid -->
                    <table style="width: 100%; font-size: 10pt; line-height: 1.3; margin-bottom: 8px; border-collapse: collapse; border-bottom: 1pt solid #000; padding-bottom: 6px;">
                        <tr>
                            <td style="width: 52%; vertical-align: top; padding: 2px 4px 4px 0; text-align: left;">
                                <div><strong>RECEIPT NO:</strong> <?php echo htmlspecialchars($pos_success_receipt['payment_id']); ?></div>
                                <div><strong>CUSTOMER:</strong> <?php echo htmlspecialchars($pos_success_receipt['customer_name']); ?></div>
                                <div><strong>PAYMENT:</strong> <?php echo htmlspecialchars($pos_success_receipt['payment_method']); ?></div>
                            </td>
                            <td style="width: 48%; vertical-align: top; padding: 2px 0 4px 4px; text-align: right;">
                                <div><strong>DATE:</strong> <?php echo htmlspecialchars($pos_success_receipt['payment_date']); ?></div>
                                <div><strong>STATUS:</strong> <span style="font-weight: bold; text-transform: uppercase;">PAID</span></div>
                                <?php if (!empty($pos_success_receipt['txnid'])): ?>
                                    <div><strong>TXN ID:</strong> <?php echo htmlspecialchars($pos_success_receipt['txnid']); ?></div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </table>

                    <!-- 3. Items Table -->
                    <table style="width: 100%; border-collapse: collapse; table-layout: fixed; font-size: 10pt; line-height: 1.25; margin-bottom: 8px;">
                        <thead>
                            <tr style="border-top: 1pt dashed #000; border-bottom: 1pt dashed #000;">
                                <th class="col-item-desc" style="padding: 3pt 2pt; text-align: left; font-weight: bold; width: 48%;">Item Description</th>
                                <th class="col-qty" style="padding: 3pt 2pt; text-align: right; font-weight: bold; width: 14%;">Qty</th>
                                <th class="col-unit-price" style="padding: 3pt 2pt; text-align: right; font-weight: bold; width: 19%;">Price</th>
                                <th class="col-amount" style="padding: 3pt 2pt; text-align: right; font-weight: bold; width: 19%;">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pos_success_receipt['items'] as $item): 
                                $item_qty = intval($item['qty']);
                                $item_unit_price = floatval($item['price']);
                                $item_gross = $item_unit_price * $item_qty;
                                $item_disc = isset($item['discount_amount']) ? floatval($item['discount_amount']) : 0.00;
                                $item_net = isset($item['line_net']) ? floatval($item['line_net']) : max(0, $item_gross - $item_disc);
                                $is_special = isset($item['item_type']) && $item['item_type'] === 'SPECIAL_ORDER';
                            ?>
                            <tr style="border-bottom: 1pt dashed #000;">
                                <td class="col-item-desc" style="padding: 3pt 2pt; text-align: left; vertical-align: top; word-break: break-word;">
                                    <?php if ($is_special): ?>
                                        <span style="font-weight: bold; font-size: 8.5pt; text-transform: uppercase; border: 0.5pt solid #000; padding: 0 2px;">SPECIAL ORDER</span><br>
                                    <?php endif; ?>
                                    <strong><?php echo htmlspecialchars($item['name']); ?></strong>
                                    <?php if (!empty($item['product_details'])): ?>
                                        <div style="font-size: 9pt; color: #000; margin-top: 1px;"><?php echo htmlspecialchars($item['product_details']); ?></div>
                                    <?php elseif (!empty($item['variant_details'])): ?>
                                        <div style="font-size: 9pt; color: #000; margin-top: 1px;"><?php echo htmlspecialchars($item['variant_details']); ?></div>
                                    <?php endif; ?>
                                    <div class="item-unit-subprice" style="display: none; font-size: 8pt; color: #000; margin-top: 1px;">
                                        @ &#8369;<?php echo number_format($item_unit_price, 2); ?><?php if($item_qty > 1) echo ' &times; ' . $item_qty; ?>
                                    </div>
                                </td>
                                <td class="col-qty" style="padding: 3pt 2pt; text-align: right; vertical-align: top;"><?php echo $item_qty; ?></td>
                                <td class="col-unit-price" style="padding: 3pt 2pt; text-align: right; vertical-align: top;">&#8369;<?php echo number_format($item_unit_price, 2); ?></td>
                                <td class="col-amount" style="padding: 3pt 2pt; text-align: right; vertical-align: top;">&#8369;<?php echo number_format($item_net, 2); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td class="col-foot-spacer" style="border-top: 1pt dashed #000; padding: 3pt 0;"></td>
                                <td class="col-unit-price col-foot-spacer2" style="border-top: 1pt dashed #000; padding: 3pt 0;"></td>
                                <td class="col-foot-label" style="border-top: 1pt dashed #000; padding: 3pt 2pt; text-align: right;">Subtotal:</td>
                                <td class="col-amount" style="border-top: 1pt dashed #000; padding: 3pt 2pt; text-align: right; white-space: nowrap;">&#8369;<?php echo number_format($pos_success_receipt['gross_subtotal'] ?? $pos_success_receipt['subtotal'], 2); ?></td>
                            </tr>
                            <?php if (isset($pos_success_receipt['total_discount_savings']) && $pos_success_receipt['total_discount_savings'] > 0): ?>
                            <tr>
                                <td class="col-foot-spacer" style="padding: 2pt 0;"></td>
                                <td class="col-unit-price col-foot-spacer2" style="padding: 2pt 0;"></td>
                                <td class="col-foot-label" style="padding: 2pt 2pt; text-align: right;">Discount:</td>
                                <td class="col-amount" style="padding: 2pt 2pt; text-align: right; white-space: nowrap;">-&#8369;<?php echo number_format($pos_success_receipt['total_discount_savings'], 2); ?></td>
                            </tr>
                            <?php endif; ?>
                            <?php if (isset($pos_success_receipt['delivery_cost']) && $pos_success_receipt['delivery_cost'] > 0): ?>
                            <tr>
                                <td class="col-foot-spacer" style="padding: 2pt 0;"></td>
                                <td class="col-unit-price col-foot-spacer2" style="padding: 2pt 0;"></td>
                                <td class="col-foot-label" style="padding: 2pt 2pt; text-align: right;">Delivery:</td>
                                <td class="col-amount" style="padding: 2pt 2pt; text-align: right; white-space: nowrap;">&#8369;<?php echo number_format($pos_success_receipt['delivery_cost'], 2); ?></td>
                            </tr>
                            <?php endif; ?>
                            <tr class="pos-total-row" style="border-top: 1pt dashed #000; border-bottom: 1pt dashed #000;">
                                <td class="col-foot-spacer" style="padding: 4pt 0;"></td>
                                <td class="col-unit-price col-foot-spacer2" style="padding: 4pt 0;"></td>
                                <td class="col-foot-label" style="padding: 4pt 2pt; text-align: right; font-size: 12pt; font-weight: bold;">TOTAL:</td>
                                <td class="col-amount" style="padding: 4pt 2pt; text-align: right; font-size: 12pt; font-weight: bold; white-space: nowrap;">&#8369;<?php echo number_format($pos_success_receipt['grand_total'], 2); ?></td>
                            </tr>
                            <?php if (isset($pos_success_receipt['amount_tendered']) && $pos_success_receipt['amount_tendered'] > 0): ?>
                            <tr>
                                <td class="col-foot-spacer" style="padding: 2pt 0;"></td>
                                <td class="col-unit-price col-foot-spacer2" style="padding: 2pt 0;"></td>
                                <td class="col-foot-label" style="padding: 2pt 2pt; text-align: right;">Tendered:</td>
                                <td class="col-amount" style="padding: 2pt 2pt; text-align: right; white-space: nowrap;">&#8369;<?php echo number_format($pos_success_receipt['amount_tendered'], 2); ?></td>
                            </tr>
                            <tr>
                                <td class="col-foot-spacer" style="padding: 2pt 0;"></td>
                                <td class="col-unit-price col-foot-spacer2" style="padding: 2pt 0;"></td>
                                <td class="col-foot-label" style="padding: 2pt 2pt; text-align: right;">Change:</td>
                                <td class="col-amount" style="padding: 2pt 2pt; text-align: right; white-space: nowrap;">&#8369;<?php echo number_format($pos_success_receipt['change_amount'], 2); ?></td>
                            </tr>
                            <?php endif; ?>
                        </tfoot>
                    </table>

                    <!-- 4. Footer -->
                    <div style="text-align: left; margin-top: 6px; border-top: 1pt dashed #000; padding-top: 5px; font-size: 10pt; line-height: 1.3; width: 100%;">
                        <div style="font-weight: bold; text-transform: uppercase;">THANK YOU FOR YOUR BUSINESS!</div>
                        <div style="margin-top: 1px;">Items in good condition may be returned within 7 days with this receipt.</div>
                        <div style="font-size: 9pt; color: #000; margin-top: 2px;">Official Sales Receipt &bull; eConstruction Supply</div>
                    </div>
                </div>

            </div>

            <div class="modal-footer" style="background: #f8fafc; border-top: 1px solid #e2e8f0; padding: 12px 18px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
                <!-- Row 1: Thermal Paper Selector & Settings -->
                <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                    <div style="display: inline-flex; align-items: center; background: #fff; border: 1.5px solid #cbd5e1; border-radius: 6px; padding: 3px 8px; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">
                        <label for="posReceiptModalPaperSize" style="margin: 0; font-size: 11.5px; font-weight: 700; color: #334155; margin-right: 6px; display: inline-flex; align-items: center; gap: 4px;">
                            <i class="fa fa-sliders text-primary"></i> Output Format:
                        </label>
                        <select id="posReceiptModalPaperSize" class="form-control input-sm" onchange="handleModalPaperSizeChange(this.value)" style="height: 30px; font-size: 12px; font-weight: 700; color: #0369a1; border: 1px solid #0284c7; border-radius: 4px; padding: 2px 8px; width: auto; background-color: #f0f9ff; cursor: pointer;" title="Select output format (Thermal 200mm / PDF Preview / Thermal 80mm / Thermal 58mm / A4)">
                            <option value="200" selected>⚡ Thermal Printer: 200 mm Roll (Primary Default)</option>
                            <option value="80">🖨️ Thermal Printer: 80 mm POS Roll (Standard)</option>
                            <option value="58">🖨️ Thermal Printer: 58 mm POS Roll (Compact)</option>
                            <option value="210">📄 Standard Printer: A4 Paper Sheet</option>
                            <option value="pdf">📄 PDF Preview / Export</option>
                        </select>
                    </div>
                    <button type="button" class="btn btn-default" onclick="openPOSPrinterModal()" title="Printer Setup & Auto-Detection" style="height: 34px; padding: 5px 12px; font-weight: 600; border-color: #cbd5e1; border-radius: 6px;">
                        <i class="fa fa-cog text-muted"></i> Settings
                    </button>
                </div>

                <!-- Row 2: Action Buttons (Print Thermal, PDF Preview, New Sale) -->
                <div class="pos-success-actions" style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                    <button type="button" class="btn btn-success" onclick="printPOSReceipt('thermal200')" style="font-weight: 800; height: 36px; padding: 6px 16px; font-size: 13px; background-color: #059669; border-color: #047857; border-radius: 6px; box-shadow: 0 2px 5px rgba(5,150,105,0.25);" title="Native physical print to thermal roll (No PDF conversion)">
                        <i class="fa fa-print"></i> Print Receipt (Thermal)
                    </button>
                    <button type="button" class="btn btn-info" onclick="printPOSReceipt('pdf')" style="font-weight: 800; height: 36px; padding: 6px 14px; font-size: 13px; background-color: #0e7490; border-color: #0891b2; border-radius: 6px; box-shadow: 0 2px 5px rgba(14,116,144,0.25);" title="Preview thermal layout in PDF preview window">
                        <i class="fa fa-file-pdf-o"></i> PDF Preview
                    </button>
                    <button type="button" class="btn btn-default" onclick="closeReceiptModal()" style="font-weight: 700; height: 36px; padding: 6px 14px; font-size: 13px; border-radius: 6px; border-color: #cbd5e1;">
                        <i class="fa fa-plus"></i> New Sale
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Purchase Order Confirmation Modal -->
<?php if ($pos_po_success_data): ?>
<div class="modal fade in" id="posPOSuccessModal" tabindex="-1" role="dialog" style="display: block; background: rgba(0,0,0,0.65); z-index: 10080;">
    <div class="modal-dialog" role="document" style="max-width: 620px;">
        <div class="modal-content" style="border-radius: 10px; overflow: hidden; box-shadow: 0 15px 40px rgba(0,0,0,0.35);">
            <div class="modal-header" style="background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%); color: #fff; padding: 14px 18px;">
                <button type="button" class="close" data-dismiss="modal" onclick="closePOSPurchaseOrderModal()" style="color: #fff; opacity: 0.9; font-size: 24px;">&times;</button>
                <h4 class="modal-title" style="font-weight: 800; font-size: 16px; display: flex; align-items: center; gap: 8px;">
                    <i class="fa fa-file-text-o"></i> Purchase Order Confirmed
                </h4>
            </div>
            
            <div class="modal-body pos-receipt-400" id="posPrintPOArea" style="padding: 16px; font-family: Arial, Helvetica, sans-serif; color: #000; font-size: 12pt; line-height: 1.25; background: #fff; height: auto; min-height: 0;">
                
                <!-- 1. Store Header & Title -->
                <div style="text-align: left; margin-bottom: 8px; border-bottom: 1.5pt solid #000; padding-bottom: 6px; width: 100%;">
                    <div class="pos-company-header" style="font-size: 14pt; font-weight: bold; text-transform: uppercase; white-space: nowrap; overflow: visible; color: #000; line-height: 1.2;">SAM &amp; INRI CONSTRUCTION SUPPLY</div>
                    <div style="font-size: 11pt; margin-top: 2px; color: #000;">Tel: <?php echo !empty($pos_po_success_data['supplier_phone']) ? htmlspecialchars($pos_po_success_data['supplier_phone']) : '09612735733'; ?></div>
                    <div style="font-size: 13pt; font-weight: bold; text-transform: uppercase; margin-top: 4px; letter-spacing: 0.5px; color: #000;">PURCHASE ORDER VOUCHER</div>
                    <div style="font-size: 10.5pt; font-weight: bold; text-transform: uppercase; color: #000;">(UNPAID)</div>
                </div>

                <!-- 2. PO Metadata (Compact Info Grid) -->
                <table style="width: 100%; font-size: 12pt; line-height: 1.3; margin-bottom: 8px; border-collapse: collapse; border-bottom: 1.5pt solid #000; padding-bottom: 6px;">
                    <tr>
                        <td style="width: 52%; vertical-align: top; padding: 2px 4px 4px 0;">
                            <div><strong>PO NO:</strong> <span style="font-weight: bold;"><?php echo htmlspecialchars($pos_po_success_data['po_id']); ?></span></div>
                            <div><strong>CUSTOMER:</strong> <?php echo htmlspecialchars($pos_po_success_data['customer_name']); ?></div>
                        </td>
                        <td style="width: 48%; vertical-align: top; padding: 2px 0 4px 4px; text-align: right;">
                            <div><strong>DATE:</strong> <?php echo htmlspecialchars($pos_po_success_data['payment_date']); ?></div>
                            <div><strong>STATUS:</strong> <span style="font-weight: bold; text-transform: uppercase;">AWAITING PAYMENT</span></div>
                        </td>
                    </tr>
                </table>

                <!-- 3. Items Table (4 Essential Columns: ITEM DESCRIPTION | QTY | UNIT PRICE | AMOUNT) -->
                <table style="width: 100%; border-collapse: collapse; table-layout: fixed; font-size: 11.5pt; line-height: 1.25; margin-bottom: 8px;">
                    <thead>
                        <tr style="border-top: 1pt dashed #000; border-bottom: 1pt dashed #000;">
                            <th class="col-item-desc" style="padding: 4pt 2pt; text-align: left; font-weight: bold; width: 52%;">ITEM DESCRIPTION</th>
                            <th class="col-qty" style="padding: 4pt 2pt; text-align: center; font-weight: bold; width: 12%;">QTY</th>
                            <th class="col-unit-price" style="padding: 4pt 2pt; text-align: right; font-weight: bold; width: 18%;">UNIT PRICE</th>
                            <th class="col-amount" style="padding: 4pt 2pt; text-align: right; font-weight: bold; width: 18%;">AMOUNT</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pos_po_success_data['items'] as $item): 
                            $item_qty = intval($item['qty']);
                            $item_unit_price = floatval($item['price']);
                            $item_gross = $item_unit_price * $item_qty;
                            $item_disc = isset($item['discount_amount']) ? floatval($item['discount_amount']) : 0.00;
                            $item_net = isset($item['line_net']) ? floatval($item['line_net']) : max(0, $item_gross - $item_disc);
                            $is_special = isset($item['item_type']) && $item['item_type'] === 'SPECIAL_ORDER';
                        ?>
                        <tr style="border-bottom: 1pt dashed #000;">
                            <td class="col-item-desc" style="padding: 4pt 2pt; text-align: left; vertical-align: top; word-break: break-word;">
                                <?php if ($is_special): ?>
                                    <span style="font-weight: bold; font-size: 9.5pt; text-transform: uppercase; border: 0.5pt solid #000; padding: 0 2px;">SPECIAL ORDER</span><br>
                                <?php endif; ?>
                                <strong><?php echo htmlspecialchars($item['name']); ?></strong>
                                <?php if (!empty($item['product_details'])): ?>
                                    <div style="font-size: 10pt; color: #000; margin-top: 1px;"><?php echo htmlspecialchars($item['product_details']); ?></div>
                                <?php elseif (!empty($item['variant_details'])): ?>
                                    <div style="font-size: 10pt; color: #000; margin-top: 1px;"><?php echo htmlspecialchars($item['variant_details']); ?></div>
                                <?php endif; ?>
                                <div class="item-unit-subprice" style="display: none; font-size: 8pt; color: #000; margin-top: 1px;">
                                    @ &#8369;<?php echo number_format($item_unit_price, 2); ?><?php if($item_qty > 1) echo ' &times; ' . $item_qty; ?>
                                </div>
                            </td>
                            <td class="col-qty" style="padding: 4pt 2pt; text-align: center; vertical-align: top;"><?php echo $item_qty; ?></td>
                            <td class="col-unit-price" style="padding: 4pt 2pt; text-align: right; vertical-align: top;">&#8369;<?php echo number_format($item_unit_price, 2); ?></td>
                            <td class="col-amount" style="padding: 4pt 2pt; text-align: right; vertical-align: top; font-weight: bold;">&#8369;<?php echo number_format($item_net, 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <?php $has_savings = isset($pos_po_success_data['total_discount_savings']) && $pos_po_success_data['total_discount_savings'] > 0; ?>
                        <?php if ($has_savings): ?>
                        <tr>
                            <td class="col-foot-spacer" style="border-top: 1pt dashed #000; padding: 4pt 0;"></td>
                            <td class="col-unit-price col-foot-spacer2" style="border-top: 1pt dashed #000; padding: 4pt 0;"></td>
                            <td class="col-foot-label" style="border-top: 1pt dashed #000; padding: 4pt 2pt; text-align: right;">Subtotal:</td>
                            <td class="col-amount" style="border-top: 1pt dashed #000; padding: 4pt 2pt; text-align: right; font-weight: bold;">&#8369;<?php echo number_format($pos_po_success_data['gross_subtotal'], 2); ?></td>
                        </tr>
                        <tr>
                            <td class="col-foot-spacer" style="padding: 2pt 0;"></td>
                            <td class="col-unit-price col-foot-spacer2" style="padding: 2pt 0;"></td>
                            <td class="col-foot-label" style="padding: 2pt 2pt; text-align: right;">Discount:</td>
                            <td class="col-amount" style="padding: 2pt 2pt; text-align: right; font-weight: bold;">-&#8369;<?php echo number_format($pos_po_success_data['total_discount_savings'], 2); ?></td>
                        </tr>
                        <?php else: ?>
                        <tr>
                            <td class="col-foot-spacer" style="border-top: 1pt dashed #000; padding: 4pt 0;"></td>
                            <td class="col-unit-price col-foot-spacer2" style="border-top: 1pt dashed #000; padding: 4pt 0;"></td>
                            <td class="col-foot-label" style="border-top: 1pt dashed #000; padding: 4pt 2pt; text-align: right;">Subtotal:</td>
                            <td class="col-amount" style="border-top: 1pt dashed #000; padding: 4pt 2pt; text-align: right; font-weight: bold;">&#8369;<?php echo number_format($pos_po_success_data['net_subtotal'], 2); ?></td>
                        </tr>
                        <?php endif; ?>

                        <?php if (isset($pos_po_success_data['delivery_cost']) && $pos_po_success_data['delivery_cost'] > 0): ?>
                        <tr>
                            <td class="col-foot-spacer" style="padding: 2pt 0;"></td>
                            <td class="col-unit-price col-foot-spacer2" style="padding: 2pt 0;"></td>
                            <td class="col-foot-label" style="padding: 2pt 2pt; text-align: right;">Delivery:</td>
                            <td class="col-amount" style="padding: 2pt 2pt; text-align: right; font-weight: bold;">&#8369;<?php echo number_format($pos_po_success_data['delivery_cost'], 2); ?></td>
                        </tr>
                        <?php endif; ?>

                        <tr class="pos-total-row" style="border-top: 1pt dashed #000; border-bottom: 1pt dashed #000;">
                            <td class="col-foot-spacer" style="padding: 4pt 0;"></td>
                            <td class="col-unit-price col-foot-spacer2" style="padding: 4pt 0;"></td>
                            <td class="col-foot-label" style="padding: 4pt 2pt; text-align: right; font-size: 13pt; font-weight: bold;">TOTAL DUE:</td>
                            <td class="col-amount" style="padding: 4pt 2pt; text-align: right; font-size: 13pt; font-weight: bold;">&#8369;<?php echo number_format($pos_po_success_data['grand_total'], 2); ?></td>
                        </tr>
                    </tfoot>
                </table>

                <!-- 4. Footer Notice & Thank You -->
                <div style="text-align: center; margin-top: 8px; border-top: 1pt dashed #000; padding-top: 6px; font-size: 11pt; line-height: 1.25; width: 100%;">
                    <div style="font-weight: bold; text-transform: uppercase; font-size: 11pt; margin-bottom: 2px;">*** PROCEED TO CASHIER FOR PAYMENT ***</div>
                    <div style="font-size: 10pt;">Thank you for your business!</div>
                    <div style="font-size: 9pt; color: #000; margin-top: 2px;">System-Generated Purchase Order Voucher &bull; eConstruction Supply</div>
                </div>

            </div>

            <div class="modal-footer" style="background: #f8fafc; border-top: 1px solid #e2e8f0; padding: 12px 18px;">
                <!-- Row 1: Thermal Paper Selector & Settings -->
                <div style="display: flex; justify-content: space-between; align-items: center; width: 100%; margin-bottom: 10px;">
                    <div style="display: inline-flex; align-items: center; background: #fff; border: 1.5px solid #cbd5e1; border-radius: 6px; padding: 3px 8px; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">
                        <label for="posPOModalPaperSize" style="margin: 0; font-size: 11.5px; font-weight: 700; color: #334155; margin-right: 6px; display: inline-flex; align-items: center; gap: 4px;">
                            <i class="fa fa-sliders text-primary"></i> Print Format:
                        </label>
                        <select id="posPOModalPaperSize" class="form-control input-sm" onchange="handleModalPaperSizeChange(this.value)" style="height: 30px; font-size: 12px; font-weight: 700; color: #0369a1; border: 1px solid #0284c7; border-radius: 4px; padding: 2px 8px; width: auto; background-color: #f0f9ff; cursor: pointer;" title="Select print format (Thermal 200mm / PDF Preview / Thermal 80mm / Thermal 58mm / A4)">
                            <option value="200" selected>⚡ Thermal Printer: 200 mm Roll (Primary Default)</option>
                            <option value="80">🖨️ Thermal Printer: 80 mm POS Roll (Standard)</option>
                            <option value="58">🖨️ Thermal Printer: 58 mm POS Roll (Compact)</option>
                            <option value="210">📄 Standard Printer: A4 Paper Sheet</option>
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
        
        // Populate Select Option
        const opt = document.createElement('option');
        opt.value = v.id;
        opt.disabled = isOutOfStock;
        opt.innerText = `${v.spec_label} - ₱${v.price.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})} (${isOutOfStock ? 'Out of stock' : v.stock + ' in stock'})`;
        select.appendChild(opt);
        
        // Populate Quick Variant Chip
        const chip = document.createElement('button');
        chip.type = 'button';
        chip.className = `pos-variant-chip ${isOutOfStock ? 'disabled' : ''}`;
        chip.id = `vChip_${v.id}`;
        chip.setAttribute('data-id', v.id);
        chip.title = isOutOfStock ? 'Out of stock' : `${v.stock} in stock`;
        chip.innerHTML = `<i class="fa ${isOutOfStock ? 'fa-ban text-danger' : 'fa-check-circle'}"></i> ${escapeHtml(v.spec_label)} <span style="font-weight: 800; margin-left: 2px;">₱${v.price.toFixed(0)}</span>`;
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
        if (item.item_type !== 'SPECIAL_ORDER' && newQty > item.stock) {
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
            const grossLineTotal = item.price * item.qty;
            const itemIdStr = typeof item.id === 'string' ? `'${item.id}'` : item.id;
            
            let itemBadge = '';
            let detailsHtml = '';
            
            if (isSpecialOrder) {
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

            if (dStatus === 'PENDING') {
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
                <tr ${isSpecialOrder ? 'style="background-color: #fffbeb;"' : ''}>
                    <td style="padding: 8px 4px;">
                        ${itemBadge}
                        <strong style="color: #0f172a; font-size: 13px; display: block; line-height: 1.3;">${escapeHtml(item.base_name || item.name)}</strong>
                        ${detailsHtml}
                        <div style="font-size: 11px; color: #64748b; font-family: monospace; margin-top: 2px;">
                            ${refOrSku}&#8369;${item.price.toFixed(2)} each
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
                        ${isPayingExistingPO ? '' : `<button type="button" class="btn btn-link text-danger" onclick="removeFromCart(${itemIdStr})" style="padding: 2px 4px; font-size: 16px;" title="Remove item"><i class="fa fa-times-circle"></i></button>`}
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
    let hasPendingDiscounts = false;

    cart.forEach(item => {
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
    const grandTotal = netSubtotal + deliveryCost;

    document.getElementById('posSubtotal').innerText = grossSubtotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    
    const discRow = document.getElementById('posDiscountSavingsRow');
    const discDisplay = document.getElementById('posDiscountSavingsDisplay');
    if (totalDiscountSavings > 0) {
        discDisplay.innerText = totalDiscountSavings.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        discRow.style.display = 'flex';
    } else {
        discRow.style.display = 'none';
    }

    document.getElementById('posGrandTotal').innerText = grandTotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    // Update Change
    const tendered = parseFloat(document.getElementById('posAmountTendered').value) || 0;
    const change = Math.max(0, tendered - grandTotal);
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
            completeBtn.innerHTML = '<i class="fa fa-check-circle"></i> Complete Sale & Print Receipt';
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
    let subtotal = 0;
    cart.forEach(item => subtotal += item.price * item.qty);
    const isLocationActive = document.getElementById('locationRadio').checked;
    const deliveryCost = isLocationActive ? (parseFloat(document.getElementById('posDeliveryCost').value) || 0) : 0;
    const grandTotal = subtotal + deliveryCost;
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

function filterPOSProducts() {
    const query = document.getElementById('posSearchInput').value.toLowerCase().trim();
    const items = document.querySelectorAll('.pos-product-item');
    const activePill = document.querySelector('.pos-cat-pill.active');
    const activeCat = activePill ? activePill.innerText.replace(/\s*\(\d+\)$/, '').trim() : 'All Categories';
    let visibleCount = 0;

    items.forEach(item => {
        const searchData = item.getAttribute('data-name') || '';
        const itemCat = item.getAttribute('data-category') || '';

        const matchesQuery = (query === '' || searchData.indexOf(query) > -1);
        const matchesCat = (activeCat === 'All Categories' || itemCat === activeCat);

        if (matchesQuery && matchesCat) {
            item.style.display = '';
            visibleCount++;
        } else {
            item.style.display = 'none';
        }
    });

    document.getElementById('productCount').innerText = visibleCount;
}

function clearPOSSearch() {
    document.getElementById('posSearchInput').value = '';
    filterPOSProducts();
}

function filterCategory(catName, btn) {
    document.querySelectorAll('.pos-cat-pill').forEach(pill => pill.classList.remove('active'));
    btn.classList.add('active');
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
    const payMethod = document.getElementById('posPaymentMethod')?.value || 'Cash (OTC)';
    if (payMethod.toLowerCase().includes('cash')) {
        const grandTotalText = document.getElementById('posGrandTotal')?.innerText?.replace(/,/g, '') || '0';
        const grandTotal = parseFloat(grandTotalText) || 0;
        const tendered = parseFloat(document.getElementById('posAmountTendered')?.value) || 0;
        if (tendered < grandTotal) {
            alert('Amount tendered (₱' + tendered.toFixed(2) + ') is less than the Grand Total (₱' + grandTotal.toFixed(2) + '). Please enter sufficient cash.');
            document.getElementById('posAmountTendered')?.focus();
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
// POS AUTOMATIC PRINTER DETECTION & DYNAMIC PRINT FORMATTING ENGINE
// ============================================================================

let posDetectedPrinters = [];
const MAX_THERMAL_WIDTH_MM = 210;
const MIN_THERMAL_FONT_SIZE = 12;

let posPrinterSettings = {
    printerId: 'system_default',
    printerName: 'AUTO DETECT (System Default Printer)',
    printerMode: 'thermal', // 'thermal' (Primary/Default), 'normal' (Standard A4)
    printerType: 'thermal',
    paperWidthMm: 210, // Maximum & default standard thermal width (<= 210mm)
    thermalFontName: 'Courier New', // Configurable thermal font name
    thermalMinFontSize: 12, // Enterprise standard minimum (>= 12 pt)
    thermalDefaultFontSize: 12, // Default thermal font size (>= 12 pt)
    thermalFontStrategy: 'native', // 'native', 'monospace', 'custom_ttf'
    thermalBoldImportant: true, // Bold important fields
    a4Orientation: 'portrait', // 'portrait', 'landscape'
    printWidthA4Mm: 80, // Central PDF / Print Width setting (0 - 210 mm, default 80 mm)
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
    // Strict normalization: thermal paper width must NOT exceed MAX_THERMAL_WIDTH_MM (210 mm)
    if (!posPrinterSettings.paperWidthMm || posPrinterSettings.paperWidthMm > MAX_THERMAL_WIDTH_MM || posPrinterSettings.paperWidthMm === 500 || posPrinterSettings.paperWidthMm === 400 || posPrinterSettings.paperWidthMm === 250) {
        posPrinterSettings.paperWidthMm = MAX_THERMAL_WIDTH_MM;
    }
    if (!posPrinterSettings.printerMode) {
        posPrinterSettings.printerMode = (posPrinterSettings.printerType === 'normal' || posPrinterSettings.paperWidthMm === 210) ? 'normal' : 'thermal';
    }
    // Strict normalization: thermal font size must be >= 12 pt
    if (!posPrinterSettings.thermalMinFontSize || posPrinterSettings.thermalMinFontSize < 12) {
        posPrinterSettings.thermalMinFontSize = 12;
    }
    if (!posPrinterSettings.thermalDefaultFontSize || posPrinterSettings.thermalDefaultFontSize < 12) {
        posPrinterSettings.thermalDefaultFontSize = 12;
    }
    if (!posPrinterSettings.thermalFontName || typeof posPrinterSettings.thermalFontName !== 'string' || !posPrinterSettings.thermalFontName.trim()) {
        posPrinterSettings.thermalFontName = 'Courier New';
    }
    if (!posPrinterSettings.thermalFontStrategy) {
        posPrinterSettings.thermalFontStrategy = 'native';
    }
    if (typeof posPrinterSettings.thermalBoldImportant === 'undefined') {
        posPrinterSettings.thermalBoldImportant = true;
    }
    if (!posPrinterSettings.a4Orientation) {
        posPrinterSettings.a4Orientation = 'portrait';
    }
    if (typeof posPrinterSettings.printWidthA4Mm === 'undefined' || isNaN(parseFloat(posPrinterSettings.printWidthA4Mm))) {
        posPrinterSettings.printWidthA4Mm = 80;
    }
    return posPrinterSettings;
}

function handlePrinterModeRadioChange(mode) {
    posPrinterSettings.printerMode = mode;
    updateCompatibilityBadge();
}

function getA4PrintWidthMm() {
    const s = getPOSPrintSettings();
    let w = parseFloat(s.printWidthA4Mm);
    if (isNaN(w) || w === null || typeof w === 'undefined') {
        w = 80;
    }
    return Math.max(0, Math.min(210, Math.round(w)));
}

function setA4PrintWidthMm(val) {
    let w = parseFloat(val);
    if (isNaN(w)) w = 0;
    w = Math.max(0, Math.min(210, Math.round(w)));
    posPrinterSettings.printWidthA4Mm = w;
    try {
        localStorage.setItem('pos_printer_settings', JSON.stringify(posPrinterSettings));
    } catch (e) {}
    
    const input = document.getElementById('posA4PrintWidthInput');
    if (input && document.activeElement !== input) input.value = w;
    const range = document.getElementById('posA4PrintWidthRange');
    if (range && document.activeElement !== range) range.value = w;
    const badgeVal = document.getElementById('posPdfWidthBadgeVal');
    if (badgeVal) badgeVal.innerText = w;
    const btnLabels = document.querySelectorAll('.pdf-btn-width-label');
    btnLabels.forEach(el => el.innerText = w + 'mm');
    
    updateCompatibilityBadge();
    syncModalPaperSizeSelects();
}

function adjustA4PrintWidth(delta) {
    let current = parseFloat(document.getElementById('posA4PrintWidthInput')?.value || getA4PrintWidthMm());
    if (isNaN(current)) current = 80;
    setA4PrintWidthMm(current + delta);
}

function handleFontSizeRangeInput(val) {
    const pt = Math.min(20, Math.max(12, parseInt(val, 10) || 12));
    const defFontSelect = document.getElementById('posThermalDefaultFontSize');
    const customInput = document.getElementById('posThermalCustomFontSize');
    if (defFontSelect) {
        const matchingOpt = Array.from(defFontSelect.options).find(opt => opt.value == pt);
        defFontSelect.value = matchingOpt ? pt : 'custom';
    }
    if (customInput) {
        customInput.value = pt;
    }
    updateCompatibilityBadge();
}

function syncFontSizeFromCustom(val) {
    const pt = parseInt(val, 10);
    const rangeSlider = document.getElementById('posThermalFontSizeRange');
    const defFontSelect = document.getElementById('posThermalDefaultFontSize');
    if (!isNaN(pt)) {
        if (rangeSlider && pt >= 12 && pt <= 20) {
            rangeSlider.value = pt;
        }
        if (defFontSelect) {
            const matchingOpt = Array.from(defFontSelect.options).find(opt => opt.value == pt);
            defFontSelect.value = matchingOpt ? pt : 'custom';
        }
    }
    updateCompatibilityBadge();
}

function handleFontSizePresetChange(val) {
    const customInput = document.getElementById('posThermalCustomFontSize');
    const rangeSlider = document.getElementById('posThermalFontSizeRange');
    if (val === 'custom') {
        if (customInput && customInput.value < 12) customInput.value = 12;
        if (customInput) customInput.focus();
    } else {
        const pt = parseInt(val, 10);
        if (!isNaN(pt)) {
            if (rangeSlider && pt >= 12 && pt <= 20) rangeSlider.value = pt;
            if (customInput) customInput.value = pt;
        }
    }
    updateCompatibilityBadge();
}

function savePOSPrinterSettings() {
    posPrinterSettings.printerMode = 'thermal';
    posPrinterSettings.printerId = document.getElementById('posPrinterSelect')?.value || 'system_default';
    const selectedOption = document.getElementById('posPrinterSelect')?.selectedOptions[0];
    posPrinterSettings.printerName = selectedOption ? selectedOption.text : 'AUTO DETECT (System Default Printer)';
    posPrinterSettings.printerType = 'thermal';
    
    const widthVal = document.getElementById('posPaperWidth')?.value || '210';
    if (widthVal === 'custom') {
        let customW = parseInt(document.getElementById('posCustomWidthInput')?.value, 10) || 210;
        posPrinterSettings.paperWidthMm = Math.min(MAX_THERMAL_WIDTH_MM, Math.max(40, customW));
    } else {
        let chosenW = parseInt(widthVal, 10) || 210;
        posPrinterSettings.paperWidthMm = Math.min(MAX_THERMAL_WIDTH_MM, chosenW);
    }

    // Thermal Font Name
    const fontNameInput = document.getElementById('posThermalFontName');
    posPrinterSettings.thermalFontName = (fontNameInput && fontNameInput.value.trim()) ? fontNameInput.value.trim() : 'Courier New';

    // Thermal typography validation (strictly enforce >= 12 pt)
    posPrinterSettings.thermalMinFontSize = 12;

    const defFontSelect = document.getElementById('posThermalDefaultFontSize');
    let defFont = 12;
    if (defFontSelect && defFontSelect.value === 'custom') {
        defFont = parseInt(document.getElementById('posThermalCustomFontSize')?.value, 10) || 12;
    } else if (defFontSelect) {
        defFont = parseInt(defFontSelect.value, 10) || 12;
    }
    if (defFont < 12) defFont = 12;
    posPrinterSettings.thermalDefaultFontSize = defFont;

    posPrinterSettings.thermalFontStrategy = document.getElementById('posThermalFontStrategy')?.value || 'native';
    posPrinterSettings.thermalBoldImportant = document.getElementById('posThermalBoldImportant')?.checked ?? true;

    // Secondary A4 settings
    posPrinterSettings.a4Orientation = document.getElementById('posA4Orientation')?.value || 'portrait';

    const a4WidthInput = document.getElementById('posA4PrintWidthInput');
    if (a4WidthInput) {
        let a4W = parseFloat(a4WidthInput.value);
        if (isNaN(a4W)) a4W = 80;
        posPrinterSettings.printWidthA4Mm = Math.max(0, Math.min(210, Math.round(a4W)));
    }
    
    posPrinterSettings.copies = Math.min(5, Math.max(1, parseInt(document.getElementById('posPrintCopies')?.value, 10) || 1));

    try {
        localStorage.setItem('pos_printer_settings', JSON.stringify(posPrinterSettings));
    } catch (e) {
        console.warn('Could not persist POS printer settings', e);
    }

    updatePOSPrinterBadge();
    syncModalPaperSizeSelects();
    $('#posPrinterModal').modal('hide');
}

function updatePOSPrinterBadge() {
    const s = getPOSPrintSettings();
    const btnLabel = document.getElementById('posPrinterBtnLabel');
    if (btnLabel) {
        let modeLabel = '210mm Roll';
        if (s.printerMode === 'pdf') {
            modeLabel = 'PDF Preview';
        } else if (s.printerType === 'normal' || s.paperWidthMm === 210) {
            modeLabel = '210mm';
        } else if (s.paperWidthMm) {
            modeLabel = Math.min(MAX_THERMAL_WIDTH_MM, s.paperWidthMm) + 'mm';
        }
        btnLabel.innerText = 'Printer: ' + modeLabel;
    }
}

function syncModalPaperSizeSelects() {
    const s = getPOSPrintSettings();
    const widthMm = String(Math.min(MAX_THERMAL_WIDTH_MM, s.paperWidthMm || 210));
    const selects = ['posReceiptModalPaperSize', 'posPOModalPaperSize', 'posReturnModalPaperSize'];
    selects.forEach(id => {
        const el = document.getElementById(id);
        if (el) {
            if (s.printerMode === 'pdf') {
                el.value = 'pdf200';
            } else if (s.printerType === 'normal' || s.paperWidthMm === 210) {
                el.value = '210';
            } else {
                el.value = widthMm;
            }
        }
    });

    const a4W = getA4PrintWidthMm();
    const isA4 = (s.printerType === 'normal' || s.paperWidthMm === 210);
    const effectiveThermalWidth = Math.min(MAX_THERMAL_WIDTH_MM, s.paperWidthMm || 210);
    const summaryModes = document.querySelectorAll('.receipt-summary-mode');
    summaryModes.forEach(el => {
        el.innerText = isA4 ? 'Standard Printer (A4)' : 'Thermal Printer (Primary Default)';
    });
    const summaryWidths = document.querySelectorAll('.receipt-summary-width');
    summaryWidths.forEach(el => {
        el.innerText = isA4 ? (a4W + ' mm') : (effectiveThermalWidth >= 200 ? '195 mm' : (effectiveThermalWidth + ' mm'));
    });
    const summaryPapers = document.querySelectorAll('.receipt-summary-paper');
    summaryPapers.forEach(el => {
        el.innerText = isA4 ? 'A4 Paper Sheet (210 mm)' : (effectiveThermalWidth + ' mm Roll');
    });
    const summaryFonts = document.querySelectorAll('.receipt-summary-font');
    summaryFonts.forEach(el => {
        el.innerText = isA4 ? 'Arial Standard Office' : (s.thermalFontName || 'Enterprise Thermal Monospace');
    });
}

function handleModalPaperSizeChange(val) {
    const s = getPOSPrintSettings();
    if (val === 'pdf200' || val === 'pdf500' || val === 'pdf') {
        s.paperWidthMm = 210;
        s.printerMode = 'pdf';
        s.printerType = 'thermal';
    } else if (val === '210') {
        s.paperWidthMm = 210;
        s.printerMode = 'thermal';
        s.printerType = 'thermal';
    } else {
        const widthMm = Math.min(MAX_THERMAL_WIDTH_MM, parseInt(val, 10) || 210);
        s.paperWidthMm = widthMm;
        s.printerMode = 'thermal';
        s.printerType = 'thermal';
    }
    localStorage.setItem('pos_printer_settings', JSON.stringify(s));
    updatePOSPrinterBadge();
    syncModalPaperSizeSelects();
}

function openPOSPrinterModal() {
    const s = getPOSPrintSettings();
    if (s.paperWidthMm > MAX_THERMAL_WIDTH_MM) {
        s.paperWidthMm = MAX_THERMAL_WIDTH_MM;
    }

    const printerSelect = document.getElementById('posPrinterSelect');
    if (printerSelect && s.printerId) {
        printerSelect.value = s.printerId;
    }

    const widthSelect = document.getElementById('posPaperWidth');
    if (widthSelect) {
        const matchingOption = Array.from(widthSelect.options).find(opt => opt.value == s.paperWidthMm);
        if (matchingOption) {
            widthSelect.value = s.paperWidthMm;
            const customGroup = document.getElementById('posCustomWidthGroup');
            if (customGroup) customGroup.style.display = 'none';
        } else {
            widthSelect.value = 'custom';
            const customGroup = document.getElementById('posCustomWidthGroup');
            if (customGroup) customGroup.style.display = 'block';
            const customInput = document.getElementById('posCustomWidthInput');
            if (customInput) customInput.value = Math.min(MAX_THERMAL_WIDTH_MM, s.paperWidthMm);
        }
    }

    // Thermal Font Name
    const fontNameInput = document.getElementById('posThermalFontName');
    if (fontNameInput) {
        fontNameInput.value = s.thermalFontName || 'Courier New';
    }

    // Thermal typography with normalization to >= 12 pt
    const minFontInput = document.getElementById('posThermalMinFontSize');
    if (minFontInput) {
        minFontInput.value = 12;
    }
    const defFontSelect = document.getElementById('posThermalDefaultFontSize');
    const customSizeGroup = document.getElementById('posThermalCustomSizeGroup');
    const customSizeInput = document.getElementById('posThermalCustomFontSize');
    const rangeSlider = document.getElementById('posThermalFontSizeRange');
    const fontBadge = document.getElementById('posThermalFontSizeBadge');
    const curSize = Math.max(12, parseInt(s.thermalDefaultFontSize, 10) || 12);
    if (defFontSelect) {
        const matchingOpt = Array.from(defFontSelect.options).find(opt => opt.value == curSize);
        defFontSelect.value = matchingOpt ? curSize : 'custom';
    }
    if (customSizeInput) {
        customSizeInput.value = curSize;
    }
    if (customSizeGroup) {
        customSizeGroup.style.display = 'block';
    }
    if (rangeSlider) {
        rangeSlider.value = Math.min(20, Math.max(12, curSize));
    }
    if (fontBadge) {
        fontBadge.innerText = curSize + ' pt';
    }

    const fontStratSelect = document.getElementById('posThermalFontStrategy');
    if (fontStratSelect) {
        fontStratSelect.value = s.thermalFontStrategy || 'native';
    }
    const boldCheckbox = document.getElementById('posThermalBoldImportant');
    if (boldCheckbox) {
        boldCheckbox.checked = (s.thermalBoldImportant !== false);
    }

    // A4 Orientation
    const a4OrientSelect = document.getElementById('posA4Orientation');
    if (a4OrientSelect) {
        a4OrientSelect.value = s.a4Orientation || 'portrait';
    }

    const a4W = getA4PrintWidthMm();
    const a4Input = document.getElementById('posA4PrintWidthInput');
    if (a4Input) a4Input.value = a4W;
    const a4Range = document.getElementById('posA4PrintWidthRange');
    if (a4Range) a4Range.value = a4W;
    const badgeVal = document.getElementById('posPdfWidthBadgeVal');
    if (badgeVal) badgeVal.innerText = a4W;

    const copiesEl = document.getElementById('posPrintCopies');
    if (copiesEl) copiesEl.value = s.copies || 1;
    
    updateCompatibilityBadge();
    $('#posPrinterModal').modal('show');
    initPOSPrinterDetection();
}

function refreshPOSPrinters() {
    initPOSPrinterDetection(true);
}

function handlePaperWidthChange() {
    const val = document.getElementById('posPaperWidth').value;
    const customGroup = document.getElementById('posCustomWidthGroup');
    if (val === 'custom') {
        customGroup.style.display = 'block';
    } else {
        customGroup.style.display = 'none';
    }
    updateCompatibilityBadge();
}

function handlePrinterTypeChange() {
    const type = document.getElementById('posPrinterType')?.value;
    if (type === 'thermal') {
        const w = document.getElementById('posPaperWidth');
        if (w) w.value = '210';
        const cg = document.getElementById('posCustomWidthGroup');
        if (cg) cg.style.display = 'none';
    } else if (type === 'normal') {
        const w = document.getElementById('posPaperWidth');
        if (w) w.value = '210';
        const cg = document.getElementById('posCustomWidthGroup');
        if (cg) cg.style.display = 'none';
    }
    updateCompatibilityBadge();
}

function handlePrinterSelectChange() {
    const val = document.getElementById('posPrinterSelect').value;
    const found = posDetectedPrinters.find(p => p.id === val);
    if (found) {
        if (found.paperWidth) {
            const widthSelect = document.getElementById('posPaperWidth');
            const match = Array.from(widthSelect.options).find(o => o.value == Math.min(MAX_THERMAL_WIDTH_MM, found.paperWidth));
            if (match) {
                widthSelect.value = match.value;
            }
        }
    }
    updateCompatibilityBadge();
}

function updateCompatibilityBadge() {
    const s = getPOSPrintSettings();
    const widthVal = document.getElementById('posPaperWidth')?.value || s.paperWidthMm;
    let widthMm = (widthVal === 'custom') ? (parseInt(document.getElementById('posCustomWidthInput')?.value, 10) || 210) : parseInt(widthVal, 10);
    if (isNaN(widthMm) || widthMm <= 0) widthMm = 210;
    if (widthMm > MAX_THERMAL_WIDTH_MM) widthMm = MAX_THERMAL_WIDTH_MM;

    const fontNameInput = document.getElementById('posThermalFontName');
    const fontName = (fontNameInput && fontNameInput.value.trim()) ? fontNameInput.value.trim() : (s.thermalFontName || 'Courier New');

    const defFontSelect = document.getElementById('posThermalDefaultFontSize');
    let fontPt = 12;
    if (defFontSelect && defFontSelect.value === 'custom') {
        fontPt = parseInt(document.getElementById('posThermalCustomFontSize')?.value, 10) || 12;
    } else if (defFontSelect) {
        fontPt = parseInt(defFontSelect.value, 10) || 12;
    }
    if (fontPt < 12) fontPt = 12;

    const fontStrat = document.getElementById('posThermalFontStrategy')?.value || s.thermalFontStrategy || 'native';
    const fontLabel = fontStrat === 'monospace' ? 'Monospace' : (fontStrat === 'custom_ttf' ? 'Custom TTF' : 'Native');

    const orientation = (document.getElementById('posA4Orientation')?.value || s.a4Orientation || 'portrait');
    const orientLabel = orientation === 'landscape' ? 'Landscape' : 'Portrait';

    const copies = parseInt(document.getElementById('posPrintCopies')?.value, 10) || s.copies || 1;

    const selectedPrinterOpt = document.getElementById('posPrinterSelect')?.selectedOptions[0];
    const printerName = selectedPrinterOpt ? selectedPrinterOpt.text : (s.printerName || 'AUTO DETECT');

    // Update live Active Configuration Card badges at top of modal
    const activePrinterEl = document.getElementById('posActivePrinterVal');
    if (activePrinterEl) activePrinterEl.innerText = printerName.split('(')[0].trim() || 'AUTO DETECT';

    const activeWidthEl = document.getElementById('posActiveWidthVal');
    if (activeWidthEl) activeWidthEl.innerText = widthMm + ' mm' + (widthMm === 210 ? ' (Max)' : '');

    const activeFontNameEl = document.getElementById('posActiveFontNameVal');
    if (activeFontNameEl) activeFontNameEl.innerText = fontName;

    const activeFontEl = document.getElementById('posActiveFontVal');
    if (activeFontEl) activeFontEl.innerText = fontPt + ' pt';

    const fontBadge = document.getElementById('posThermalFontSizeBadge');
    if (fontBadge) fontBadge.innerText = fontPt + ' pt';

    const fontRange = document.getElementById('posThermalFontSizeRange');
    if (fontRange && fontPt >= 12 && fontPt <= 20) {
        fontRange.value = fontPt;
    }

    const activeA4El = document.getElementById('posActiveA4Val');
    if (activeA4El) activeA4El.innerText = 'A4 ' + orientLabel;

    const activeCopiesEl = document.getElementById('posActiveCopiesVal');
    if (activeCopiesEl) activeCopiesEl.innerText = copies + (copies === 1 ? ' Copy' : ' Copies');

    const descSpan = document.getElementById('posLayoutDesc');
    if (descSpan) {
        descSpan.innerText = `Primary: Thermal • ${widthMm} mm (Max 210 mm) • ${fontName} ${fontPt} pt min | Secondary: A4 (${orientLabel}) | PDF: Preview Only`;
    }
}

function initPOSPrinterDetection(forceRefresh = false) {
    const statusBox = document.getElementById('posPrinterStatusText');
    const select = document.getElementById('posPrinterSelect');

    if (statusBox) {
        statusBox.innerHTML = '<i class="fa fa-circle-o-notch fa-spin text-info"></i> Probing available printers, USB & print daemons...';
    }

    posDetectedPrinters = [];

    // Probe 1: Local ESC/POS / QZ Tray / Local Daemon (localhost:9100 / localhost:8080)
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
                    connection: 'Local Service',
                    paperWidth: 210
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
                            paperWidth: 210
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
                        const isThermal = /pos|thermal|receipt|epson|star|tm-/i.test(p.name);
                        posDetectedPrinters.push({
                            id: 'sys_' + p.name.replace(/[^a-zA-Z0-9]/g, '_'),
                            name: p.name + (p.isDefault ? ' (System Default)' : ''),
                            type: isThermal ? 'thermal' : 'normal',
                            connection: 'System Spooler',
                            paperWidth: isThermal ? 210 : 210,
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
            select.innerHTML = '<option value="system_default">AUTO DETECT (System Default / Browser Print Dialog)</option>';
            if (posDetectedPrinters.length > 0) {
                posDetectedPrinters.forEach((p) => {
                    const opt = document.createElement('option');
                    opt.value = p.id;
                    opt.text = p.name + ' [' + p.connection + ']';
                    select.appendChild(opt);
                });
            }
            if (posPrinterSettings.printerId) {
                select.value = posPrinterSettings.printerId;
            }
        }

        if (statusBox) {
            if (posDetectedPrinters.length > 0) {
                statusBox.innerHTML = '<span style="color: #16a34a;"><i class="fa fa-check-circle"></i> ' + posDetectedPrinters.length + ' printer/service(s) detected via direct print bridge.</span>';
            } else {
                statusBox.innerHTML = '<span style="color: #0284c7;"><i class="fa fa-info-circle"></i> Auto Detect Active &bull; Ready (Browser / OS Print Dialog).</span>';
            }
        }
    });
}

function generatePOSPrintHTML(contentHtml, docTitle = 'POS Print Document', docType = 'receipt', requestedFormat = null) {
    const s = getPOSPrintSettings();
    let paperWidthMm = (docType === 'po') ? 210 : (s.paperWidthMm || 210);
    // Strict cap at 210mm for thermal
    if (paperWidthMm > MAX_THERMAL_WIDTH_MM && docType !== 'a4' && requestedFormat !== 'pdfA4' && requestedFormat !== 'a4' && requestedFormat !== '210') {
        paperWidthMm = MAX_THERMAL_WIDTH_MM;
    }
    let contentWidthMm = (paperWidthMm >= 180) ? 195 : ((paperWidthMm === 80) ? 72 : ((paperWidthMm === 58) ? 50 : ((paperWidthMm <= 52) ? 44 : Math.max(40, paperWidthMm - 8))));
    let isA4 = false;
    let isPdfPreview = false;

    let isA4PDF = (requestedFormat === 'pdfA4' || requestedFormat === 'a4' || requestedFormat === '210');
    let is210mmThermalPDF = (requestedFormat === 'pdf210' || requestedFormat === 'pdf200' || requestedFormat === 'pdf500' || (requestedFormat === 'pdf' && docType === 'po'));
    let is210mmThermal = (requestedFormat === 'thermal210' || requestedFormat === '210' || requestedFormat === 'thermal200' || requestedFormat === '200' || requestedFormat === 'thermal500' || requestedFormat === '500' || requestedFormat === 'thermal400' || requestedFormat === '400');
    let is80mmThermal = (requestedFormat === 'thermal80' || requestedFormat === '80' || requestedFormat === 'thermal');
    let is58mmThermal = (requestedFormat === 'thermal58' || requestedFormat === '58');
    let is50mmThermal = (requestedFormat === 'thermal50' || requestedFormat === '50');
    let isLegacyWideThermal = (requestedFormat === 'thermal250' || requestedFormat === '250');

    if (is210mmThermalPDF) {
        paperWidthMm = 210;
        contentWidthMm = 195;
        isA4 = false;
        isPdfPreview = true;
    } else if (isA4PDF || (requestedFormat === 'pdf' && docType !== 'po')) {
        paperWidthMm = 210;
        contentWidthMm = getA4PrintWidthMm();
        isA4 = true;
        isPdfPreview = true;
    } else if (is210mmThermal || isLegacyWideThermal) {
        // Remap legacy 500, 400, 250 and current 200/210 to 210mm thermal roll
        paperWidthMm = 210;
        contentWidthMm = 195;
        isA4 = false;
    } else if (is80mmThermal) {
        paperWidthMm = 80;
        contentWidthMm = 72;
        isA4 = false;
    } else if (is58mmThermal) {
        paperWidthMm = 58;
        contentWidthMm = 50;
        isA4 = false;
    } else if (is50mmThermal) {
        paperWidthMm = 50;
        contentWidthMm = 44;
        isA4 = false;
    } else if (requestedFormat && !isNaN(parseInt(requestedFormat, 10))) {
        let reqW = parseInt(requestedFormat, 10);
        if (reqW === 210) {
            paperWidthMm = 210;
            contentWidthMm = 195;
            isA4 = false;
        } else if (reqW === 80) {
            paperWidthMm = 80;
            contentWidthMm = 72;
            isA4 = false;
        } else if (reqW === 58) {
            paperWidthMm = 58;
            contentWidthMm = 50;
            isA4 = false;
        } else if (reqW <= 52) {
            paperWidthMm = 50;
            contentWidthMm = 44;
            isA4 = false;
        } else if (reqW >= 200) {
            // Remap any request >= 200mm to 210mm maximum
            paperWidthMm = 210;
            contentWidthMm = 195;
            isA4 = false;
        } else {
            paperWidthMm = reqW;
            contentWidthMm = Math.max(40, reqW - 8);
            isA4 = false;
        }
    } else {
        // Check document-specific modal selectors
        let selVal = null;
        if (docType === 'po') {
            const poModalSel = document.getElementById('posPOModalPaperSize');
            if (poModalSel && poModalSel.value) selVal = poModalSel.value;
        } else if (docType === 'receipt') {
            const recModalSel = document.getElementById('posReceiptModalPaperSize');
            if (recModalSel && recModalSel.value) selVal = recModalSel.value;
        } else if (docType === 'return') {
            const retModalSel = document.getElementById('posReturnModalPaperSize');
            if (retModalSel && retModalSel.value) selVal = retModalSel.value;
        }

        if (selVal === 'pdf200' || selVal === 'pdf500') {
            paperWidthMm = 210;
            contentWidthMm = 195;
            isA4 = false;
            isPdfPreview = true;
        } else {
            let defaultWidth = (docType === 'po') ? 210 : (s.paperWidthMm || 210);
            let chosenWidth = selVal ? parseInt(selVal, 10) : defaultWidth;
            if (chosenWidth === 210) {
                paperWidthMm = 210;
                contentWidthMm = 195;
                isA4 = false;
            } else if (chosenWidth >= 200) {
                paperWidthMm = 210;
                contentWidthMm = 195;
                isA4 = false;
            } else if (chosenWidth === 80) {
                paperWidthMm = 80;
                contentWidthMm = 72;
                isA4 = false;
            } else if (chosenWidth === 58) {
                paperWidthMm = 58;
                contentWidthMm = 50;
                isA4 = false;
            } else if (chosenWidth <= 52) {
                paperWidthMm = 50;
                contentWidthMm = 44;
                isA4 = false;
            } else {
                paperWidthMm = chosenWidth;
                contentWidthMm = Math.max(40, chosenWidth - 8);
                isA4 = false;
            }
        }
    }

    const isThermal = !isA4;
    const is210mm = isThermal && paperWidthMm >= 180;
    const is50mm = isThermal && paperWidthMm <= 52;
    const is58mm = isThermal && (!is50mm && (paperWidthMm <= 65 || contentWidthMm <= 55));

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

    // Dynamic Header Font Size (Enterprise Commercial POS Standard)
    let headerFontSizePt = 16.0;
    let bodyFontSizePt = '12pt';
    let totalFontSizePt = '15.5pt';

    // Thermal Typography Customization (Strictly enforcing >= 12pt for thermal roll)
    if (isThermal) {
        const minPt = Math.max(12, Number(s.thermalMinFontSize) || 12);
        const defPt = Math.max(minPt, Number(s.thermalDefaultFontSize) || 12);
        bodyFontSizePt = defPt + 'pt';
        headerFontSizePt = Math.max(15.0, defPt + 3.5);
        totalFontSizePt = (defPt + 2.5) + 'pt';
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
    const containerMargin = 'margin: 0 !important; margin-left: 0 !important; margin-right: auto !important;';
    const containerPadding = is210mm
        ? '6mm 10mm'
        : (is50mm
            ? '1mm 1.5mm'
            : (is58mm 
                ? '1.5mm 2mm' 
                : (isThermal ? '2.5mm 3.5mm' : ((contentWidthMm <= 80) ? '2mm 2.5mm' : '4mm 4mm'))));

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
            size: ${paperWidthMm}mm ${isA4 ? pageHeightMm + 'mm' : 'auto'};
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
                margin: ${isA4 ? '12mm 15mm' : '0'};
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
            .pos-print-container, .pos-receipt-container {
                width: ${contentWidthMm}mm !important;
                max-width: ${contentWidthMm}mm !important;
                ${containerMargin}
                padding: ${containerPadding} !important;
                box-sizing: border-box !important;
                box-shadow: none !important;
                border: none !important;
                border-radius: 0 !important;
                height: auto !important;
                min-height: 0 !important;
                text-align: left !important;
                overflow: hidden !important;
            }
        }
    </style>
</head>
<body>
    <div class="no-print pdf-preview-toolbar">
        <div style="display: flex; align-items: center; gap: 10px;">
            <span style="background: #0284c7; color: #fff; font-size: 11px; font-weight: bold; padding: 3px 8px; border-radius: 4px; text-transform: uppercase;">
                ${isPdfPreview && paperWidthMm === 200 ? '200 mm Thermal PDF (Preview / Test)' : (isA4 ? 'PDF Document (' + contentWidthMm + ' mm)' : paperWidthMm + ' mm Thermal Roll')}
            </span>
            <span style="font-size: 14px; font-weight: bold;">
                ${docTitle} (${contentWidthMm}mm &bull; ${isThermal ? 'Thermal Monospace' : 'Left Aligned'})
            </span>
        </div>
        <div style="display: flex; gap: 8px;">
            <button type="button" onclick="window.print()" style="background: #10b981; color: #fff; border: none; padding: 6px 16px; font-weight: bold; border-radius: 4px; cursor: pointer; font-size: 13px; display: inline-flex; align-items: center; gap: 5px;">
                🖨️ ${isPdfPreview ? 'Print / Save as PDF' : 'Print Receipt'}
            </button>
            <button type="button" onclick="window.close()" style="background: #475569; color: #fff; border: none; padding: 6px 12px; font-weight: bold; border-radius: 4px; cursor: pointer; font-size: 13px;">
                ✕ Close
            </button>
        </div>
    </div>
    ${wrappedContent}
</body>
</html>`;
}

function executePOSPrintJob(htmlContent, autoTriggerPrint = true) {
    const printWindow = window.open('', '_blank', 'width=1100,height=900,menubar=no,toolbar=no,location=no,status=no');
    if (!printWindow) {
        alert('Print popup was blocked by browser. Please allow popups for this site to print receipts.');
        return;
    }
    printWindow.document.open();
    printWindow.document.write(htmlContent);
    printWindow.document.close();
    printWindow.focus();
    if (autoTriggerPrint) {
        setTimeout(() => {
            printWindow.print();
        }, 450);
    }
}

function generatePaidOrderThermalHTML(orderData, requestedWidthMm) {
    const s = getPOSPrintSettings();
    let widthMm = requestedWidthMm || s.paperWidthMm || 210;
    if (requestedWidthMm) {
        widthMm = requestedWidthMm;
    }
    if (widthMm > MAX_THERMAL_WIDTH_MM) {
        widthMm = MAX_THERMAL_WIDTH_MM;
    }
    const is210mm = (widthMm >= 180);
    const is50mm = (widthMm <= 52);
    const is58mm = (!is50mm && widthMm <= 65);
    const actualWidthMm = is210mm ? 210 : (is50mm ? 50 : (is58mm ? 58 : 80));
    const contentWidthMm = is210mm ? 195 : (is50mm ? 44 : (is58mm ? 50 : 72));
    
    // Thermal Font Selection
    const fontName = s.thermalFontName || 'Courier New';
    const fontStack = `'${fontName.replace(/'/g, "\\'")}', 'Courier New', Courier, monospace, 'Lucida Console', Arial, sans-serif`;

    // Hard minimum 12 pt for thermal font size
    const baseFontSizePt = Math.max(MIN_THERMAL_FONT_SIZE, parseFloat(s.thermalDefaultFontSize) || MIN_THERMAL_FONT_SIZE);
    const fontSizePt = is210mm ? Math.max(12.0, baseFontSizePt) : (is50mm ? 12.0 : (is58mm ? 12.0 : Math.max(12.0, baseFontSizePt)));
    const titleFontSizePt = is210mm ? Math.max(16.0, baseFontSizePt + 3) : (is50mm ? 13.0 : (is58mm ? 13.5 : Math.max(14.0, baseFontSizePt + 2)));
    const totalFontSizePt = is210mm ? Math.max(15.5, baseFontSizePt + 2.5) : (is50mm ? 12.5 : (is58mm ? 13.0 : Math.max(13.5, baseFontSizePt + 1.5)));
    const padding = is210mm ? '6mm 8mm' : (is50mm ? '1mm 1.5mm' : (is58mm ? '1.5mm 2mm' : '2.5mm 3mm'));

    const supplierName = orderData.supplier_name || 'SAM & INRI CONSTRUCTION SUPPLY';
    const orderNo = orderData.payment_id || 'PO-000000';
    const dateStr = orderData.payment_date || '';
    const customerName = orderData.customer_name || 'Walk-in Customer';
    const paymentMethod = orderData.payment_method || 'Cash';
    const paymentStatus = (orderData.payment_status || 'PAID').toUpperCase();

    const items = orderData.items || [];
    let itemsRows = '';
    items.forEach(function(item) {
        const itemName = item.name || 'Item';
        const itemQty = parseInt(item.qty, 10) || 1;
        const itemAmount = parseFloat(item.amount || (parseFloat(item.price || 0) * itemQty)).toFixed(2);
        
        itemsRows += `
            <tr>
                <td style="text-align: left; padding: 2.5px 0; vertical-align: top; word-break: break-word; overflow-wrap: break-word;">${escapeHtml(itemName)}</td>
                <td style="text-align: center; padding: 2.5px 4px; vertical-align: top; white-space: nowrap;">${itemQty}</td>
                <td style="text-align: right; padding: 2.5px 0; vertical-align: top; white-space: nowrap;">${itemAmount}</td>
            </tr>
        `;
    });

    const subtotal = parseFloat(orderData.subtotal || 0).toFixed(2);
    const delivery = parseFloat(orderData.delivery || 0);
    const discount = parseFloat(orderData.discount || 0);
    const total = parseFloat(orderData.total || 0).toFixed(2);

    let deliveryRow = '';
    if (delivery > 0) {
        deliveryRow = `
            <tr>
                <td colspan="2" style="text-align: left; padding: 1.5px 0;">Delivery Fee:</td>
                <td style="text-align: right; padding: 1.5px 0; white-space: nowrap;">${delivery.toFixed(2)}</td>
            </tr>
        `;
    }

    let discountRow = '';
    if (discount > 0) {
        discountRow = `
            <tr>
                <td colspan="2" style="text-align: left; padding: 1.5px 0;">Discount:</td>
                <td style="text-align: right; padding: 1.5px 0; white-space: nowrap;">-${discount.toFixed(2)}</td>
            </tr>
        `;
    }

    return `<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Paid Order Thermal Receipt - ${escapeHtml(orderNo)}</title>
    <style>
        * {
            box-sizing: border-box;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
        @page {
            size: ${actualWidthMm}mm auto;
            margin: 0;
        }
        html, body {
            margin: 0 !important;
            padding: 0 !important;
            background: #ffffff !important;
            color: #000000 !important;
            font-family: ${fontStack} !important;
            font-size: ${fontSizePt}pt !important;
            line-height: 1.3 !important;
            width: ${actualWidthMm}mm !important;
            height: auto !important;
        }
        .thermal-receipt {
            width: ${contentWidthMm}mm !important;
            max-width: ${contentWidthMm}mm !important;
            padding: ${padding} !important;
            margin: 0 !important;
            background: #ffffff !important;
            color: #000000 !important;
            box-sizing: border-box !important;
        }
        .thermal-header {
            text-align: center;
            margin-bottom: 5px;
        }
        .thermal-title {
            font-size: ${titleFontSizePt}pt;
            font-weight: bold;
            text-transform: uppercase;
            line-height: 1.25;
            color: #000000;
        }
        .thermal-subtitle {
            font-size: ${fontSizePt}pt;
            font-weight: bold;
            text-transform: uppercase;
            margin-top: 2px;
            color: #000000;
        }
        .thermal-meta {
            margin: 6px 0;
            font-size: ${fontSizePt}pt;
            line-height: 1.35;
        }
        .thermal-divider {
            border-top: 1pt dashed #000000;
            margin: 5px 0;
        }
        .thermal-table {
            width: 100% !important;
            border-collapse: collapse !important;
            table-layout: fixed !important;
            margin: 4px 0 !important;
            font-size: ${fontSizePt}pt !important;
        }
        .thermal-table th {
            padding: 3px 0 !important;
            border-top: 1pt dashed #000000 !important;
            border-bottom: 1pt dashed #000000 !important;
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
            margin: 4px 0 !important;
            font-size: ${fontSizePt}pt !important;
        }
        .thermal-totals td {
            color: #000000 !important;
        }
        .thermal-footer {
            text-align: center;
            margin-top: 8px;
            font-size: ${fontSizePt}pt;
            color: #000000;
        }
        @media screen {
            body {
                padding: 15px;
                background: #334155;
                display: flex;
                flex-direction: column;
                align-items: center;
                min-height: 100vh;
            }
            .thermal-preview-toolbar {
                width: 100%;
                max-width: ${actualWidthMm}mm;
                margin-bottom: 12px;
                background: #0f172a;
                color: #ffffff;
                padding: 8px 12px;
                border-radius: 4px;
                display: flex;
                justify-content: space-between;
                align-items: center;
                font-family: Arial, sans-serif;
                font-size: 11px;
            }
            .thermal-receipt {
                box-shadow: 0 4px 20px rgba(0,0,0,0.35);
                border: 1px solid #cbd5e1;
                border-radius: 2px;
            }
        }
        @media print {
            .thermal-preview-toolbar, .no-print {
                display: none !important;
            }
            body {
                padding: 0 !important;
                background: #ffffff !important;
            }
            .thermal-receipt {
                box-shadow: none !important;
                border: none !important;
            }
        }
    </style>
</head>
<body>
    <div class="no-print thermal-preview-toolbar">
        <span style="font-weight: bold;">🖨️ Thermal Receipt (${actualWidthMm}mm)</span>
        <div style="display: flex; gap: 6px;">
            <button type="button" onclick="window.print()" style="background: #10b981; color: #fff; border: none; padding: 4px 10px; font-weight: bold; border-radius: 3px; cursor: pointer; font-size: 11px;">Print</button>
            <button type="button" onclick="window.close()" style="background: #64748b; color: #fff; border: none; padding: 4px 8px; font-weight: bold; border-radius: 3px; cursor: pointer; font-size: 11px;">✕</button>
        </div>
    </div>

    <div class="thermal-receipt">
        <div class="thermal-header">
            <div class="thermal-title">${escapeHtml(supplierName)}</div>
            <div class="thermal-subtitle">PAID ORDER</div>
        </div>

        <div class="thermal-meta">
            <div>Order No: ${escapeHtml(orderNo)}</div>
            <div>Date: ${escapeHtml(dateStr)}</div>
            <div>Customer: ${escapeHtml(customerName)}</div>
        </div>

        <table class="thermal-table">
            <thead>
                <tr>
                    <th style="text-align: left; width: 54%;">ITEM</th>
                    <th style="text-align: center; width: 18%;">QTY</th>
                    <th style="text-align: right; width: 28%;">AMOUNT</th>
                </tr>
            </thead>
            <tbody>
                ${itemsRows}
            </tbody>
        </table>

        <div class="thermal-divider"></div>

        <table class="thermal-totals">
            <tr>
                <td colspan="2" style="text-align: left; padding: 1.5px 0;">Subtotal</td>
                <td style="text-align: right; padding: 1.5px 0; white-space: nowrap;">${subtotal}</td>
            </tr>
            ${deliveryRow}
            ${discountRow}
            <tr style="font-weight: bold;">
                <td colspan="2" style="text-align: left; padding: 3px 0; border-top: 1pt dashed #000;">TOTAL</td>
                <td style="text-align: right; padding: 3px 0; border-top: 1pt dashed #000; font-size: ${totalFontSizePt}pt; white-space: nowrap;">${total}</td>
            </tr>
        </table>

        <div class="thermal-divider"></div>

        <div class="thermal-meta" style="margin-top: 4px;">
            <div><strong>PAYMENT STATUS:</strong> ${escapeHtml(paymentStatus)}</div>
            <div><strong>Payment Method:</strong> ${escapeHtml(paymentMethod)}</div>
        </div>

        <div class="thermal-footer">
            <div>Thank you</div>
        </div>
    </div>
</body>
</html>`;
}

function testPrintThermalPaidOrder(widthMm = 210) {
    const s = getPOSPrintSettings();
    let targetWidth = widthMm || s.paperWidthMm || 210;
    if (targetWidth > MAX_THERMAL_WIDTH_MM) targetWidth = MAX_THERMAL_WIDTH_MM;
    if (targetWidth === 200) targetWidth = 210;
    
    const sampleOrderData = {
        supplier_name: 'SAM & INRI CONSTRUCTION SUPPLY',
        supplier_phone: '09612735733',
        payment_id: 'PO-TEST-' + targetWidth + 'MM',
        payment_date: new Date().toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }),
        customer_name: 'Walk-in Customer (Test)',
        payment_method: 'Cash',
        payment_status: 'PAID',
        items: [
            { name: 'Portland Cement 40kg Type 1P', qty: 10, price: 250.00, amount: 2500.00 },
            { name: 'PVC Electrical Conduit Pipe 20mm x 3.0m', qty: 20, price: 80.00, amount: 1600.00 },
            { name: 'Electrical Cable THHN 2.0mm² (150m)', qty: 2, price: 1250.00, amount: 2500.00 }
        ],
        subtotal: 6600.00,
        delivery: 150.00,
        discount: 0.00,
        total: 6750.00
    };

    const html = generatePaidOrderThermalHTML(sampleOrderData, targetWidth);
    executePOSPrintJob(html, true);
}

function testPrintPOS(format = 'thermal210') {
    if (format === 'thermal50') {
        testPrintThermalPaidOrder(50);
        return;
    } else if (format === 'thermal58') {
        testPrintThermalPaidOrder(58);
        return;
    } else if (format === 'thermal' || format === 'thermal80' || format === 'thermalPaidOrder') {
        testPrintThermalPaidOrder(80);
        return;
    } else if (format === 'thermal210' || format === 'thermal200' || format === '210' || format === '200') {
        testPrintThermalPaidOrder(210);
        return;
    }
    const s = getPOSPrintSettings();
    let isA4 = (format === 'pdfA4' || format === 'a4');
    let isPdfPreview = (format === 'pdf' || format === 'pdfA4' || format === 'a4' || format === 'pdf_preview');
    let widthMm = 195;
    let rollMm = 210;

    if (isA4) {
        rollMm = 210;
        widthMm = getA4PrintWidthMm();
    } else if (format === 'custom') {
        rollMm = Math.min(MAX_THERMAL_WIDTH_MM, s.paperWidthMm || 210);
        if (rollMm === 210) {
            isA4 = false;
            widthMm = 195;
        } else {
            widthMm = Math.max(40, rollMm - 8);
        }
    } else {
        rollMm = 210;
        widthMm = 195;
    }

    let headerFontSizePt = (widthMm >= 180) ? '16pt' : ((widthMm < 60) ? '12pt' : ((widthMm < 75) ? '12pt' : ((widthMm < 90) ? '13pt' : '14pt')));

    const testContent = `
        <!-- 1. Store Header & Title -->
        <div style="text-align: left; margin-bottom: 8px; border-bottom: 1.5pt solid #000; padding-bottom: 6px; width: 100%;">
            <div class="pos-company-header" style="font-size: ${headerFontSizePt}; font-weight: bold; text-transform: uppercase; white-space: nowrap; overflow: visible; color: #000;">SAM &amp; INRI CONSTRUCTION SUPPLY</div>
            <div style="font-size: 12pt; margin-top: 2px; color: #000;">Tel: 09612735733</div>
            <div style="font-size: 12.5pt; font-weight: bold; text-transform: uppercase; margin-top: 4px; letter-spacing: 0.3px; color: #000;">${isPdfPreview && rollMm === 210 ? '210 MM THERMAL PDF PREVIEW TEST (' + widthMm + ' MM WIDTH / ' + rollMm + ' MM ROLL)' : (isA4 ? 'PDF PRINT TEST (' + widthMm + ' MM WIDTH)' : 'THERMAL TEST (' + widthMm + ' MM / ' + rollMm + ' MM ROLL)')}</div>
            <div style="font-size: 12pt; font-weight: bold; text-transform: uppercase; color: #000;">(CALIBRATED &bull; LEFT ALIGNED)</div>
        </div>

        <!-- 2. Test Info Grid -->
        <table style="width: 100%; font-size: 12pt; line-height: 1.25; margin-bottom: 8px; border-collapse: collapse; border-bottom: 1.5pt solid #000; padding-bottom: 6px;">
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
        <table style="width: 100%; border-collapse: collapse; table-layout: fixed; font-size: 12pt; line-height: 1.25; margin-bottom: 8px;">
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
                        <div style="font-size: 12pt; color: #333; margin-top: 1px;">Grade 40, Length: 6.0m</div>
                    </td>
                    <td style="padding: 4pt 2pt; text-align: center; vertical-align: top;">10</td>
                    <td style="padding: 4pt 2pt; text-align: right; vertical-align: top;">₱485.00</td>
                    <td style="padding: 4pt 2pt; text-align: right; vertical-align: top; font-weight: bold;">₱4,850.00</td>
                </tr>
                <tr style="border-bottom: 0.5pt solid #e5e7eb;">
                    <td style="padding: 4pt 2pt; text-align: left; vertical-align: top; word-break: break-word;">
                        <strong>Portland Cement 40kg</strong>
                        <div style="font-size: 12pt; color: #333; margin-top: 1px;">Type 1P Premium Hydraulic Cement</div>
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
                    <td style="padding: 4pt 2pt; text-align: right; font-size: 13pt; font-weight: bold;">TOTAL DUE:</td>
                    <td style="padding: 4pt 2pt; text-align: right; font-size: 13pt; font-weight: bold;">₱9,750.00</td>
                </tr>
            </tfoot>
        </table>

        <!-- 4. Footer -->
        <div style="text-align: left; margin-top: 8px; border-top: 1pt dashed #000; padding-top: 6px; font-size: 12pt; line-height: 1.25; width: 100%;">
            <div style="font-weight: bold; text-transform: uppercase; margin-bottom: 2px;">*** ${isPdfPreview && rollMm === 210 ? '210 MM THERMAL PDF PREVIEW (' + widthMm + ' MM WIDTH / ' + rollMm + ' MM ROLL)' : (isA4 ? 'PDF PRINT WIDTH CALIBRATED (' + widthMm + ' MM)' : widthMm + ' MM THERMAL PRINT CALIBRATED (' + rollMm + ' MM ROLL)')} ***</div>
            <div>Print layout is strictly read-only.</div>
            <div style="font-size: 11pt; color: #555; margin-top: 2px;">eConstruction Supply SaaS &bull; Point of Sale Print Engine</div>
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
    const printArea = document.getElementById('posPrintReceiptArea');
    if (!printArea) {
        alert('Receipt print area not found.');
        return;
    }
    const isPdf = (format === 'pdf' || format === 'pdf200' || format === 'pdf500' || format === 'pdfA4');
    const title = isPdf ? 'Official Sales Receipt (PDF)' : 'Official Sales Receipt (Thermal)';
    const html = generatePOSPrintHTML(printArea.innerHTML, title, 'receipt', format);
    executePOSPrintJob(html, true);
}

function previewPOSReceiptPDF() {
    printPOSReceipt('pdfA4');
}

function printReturnSlip(format = null) {
    const printArea = document.getElementById('posPrintReturnSlipArea');
    if (!printArea) {
        alert('Return slip print area not found.');
        return;
    }
    const isPdf = (format === 'pdf' || format === 'pdf200' || format === 'pdf500' || format === 'pdfA4');
    const title = isPdf ? 'Official Return Slip (PDF)' : 'Official Return Slip (Thermal)';
    const html = generatePOSPrintHTML(printArea.innerHTML, title, 'return', format);
    executePOSPrintJob(html, true);
}

function previewPOSReturnPDF() {
    printReturnSlip('pdfA4');
}

function printPOSPurchaseOrder(format = null) {
    const printArea = document.getElementById('posPrintPOArea');
    if (!printArea) {
        alert('Purchase Order print area not found.');
        return;
    }
    const isPdf = (format === 'pdf' || format === 'pdf200' || format === 'pdf500' || format === 'pdfA4');
    const title = (format === 'pdf200' || format === 'pdf500' || format === 'pdf')
        ? 'Purchase Order Voucher (200mm Thermal PDF Preview)'
        : (isPdf ? 'Purchase Order Voucher (PDF)' : 'Purchase Order Voucher (200mm Thermal Roll)');
    const html = generatePOSPrintHTML(printArea.innerHTML, title, 'po', format || (isPdf ? 'pdf200' : '200'));
    executePOSPrintJob(html, true);
}

function previewPOSPurchaseOrderPDF() {
    printPOSPurchaseOrder('pdf200');
}


// ============================================================================
// POS RETURN WORKFLOW JAVASCRIPT ENGINE
// ============================================================================

let currentReturnOrders = [];
let selectedReturnItem = null;
let selectedReturnOrder = null;

function openReturnModal() {
    $('#retOrderSearchInput').val('');
    $('#retModalAlert').hide();
    $('#retSearchSection').show();
    $('#retItemConfigSection').hide();
    $('#retModalFooterView1').show();
    $('#posReturnModal').modal('show');
    executeOrderReturnSearch('');
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
    $('#retOrdersCountBadge').text(orders.length + (orders.length === 1 ? ' order' : ' orders'));
    $('#retOrdersListTitle').text(query ? `Search Results for "${query}"` : 'Recent Completed Orders');

    if (!orders || orders.length === 0) {
        $('#retOrdersContainer').html(`
            <div class="text-center text-muted" style="padding: 40px 15px; background: #f8fafc; border: 1.5px dashed #cbd5e1; border-radius: 8px;">
                <i class="fa fa-inbox fa-3x" style="color: #cbd5e1;"></i>
                <div style="margin-top: 10px; font-size: 14px; font-weight: bold; color: #64748b;">No matching paid/completed orders found</div>
                <div style="font-size: 12px; color: #94a3b8; margin-top: 4px;">Try searching with a different invoice ID, customer name, phone, or product SKU.</div>
            </div>
        `);
        return;
    }

    let html = '';
    orders.forEach((ord) => {
        let itemsHtml = '';
        ord.items.forEach((it) => {
            const isReturnable = it.available_to_return > 0;
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
                        </div>
                    </div>
                    <div style="text-align: right; min-width: 140px;">
                        ${isReturnable ? `
                            <button type="button" class="btn btn-danger btn-xs" onclick='selectReturnItem(${JSON.stringify(ord).replace(/'/g, "&apos;")}, ${JSON.stringify(it).replace(/'/g, "&apos;")})' style="font-weight: 700; background: #dc2626; border-color: #b91c1c; padding: 4px 10px; border-radius: 4px;">
                                <i class="fa fa-undo"></i> Return (${it.available_to_return} left)
                            </button>
                        ` : `
                            <span class="label label-default" style="font-size: 11px; padding: 3px 8px; background: #e2e8f0; color: #64748b;">Fully Returned</span>
                        `}
                    </div>
                </div>
            `;
        });

        html += `
            <div style="border: 1.5px solid #e2e8f0; border-radius: 8px; background: #fff; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.04); margin-bottom: 8px;">
                <div style="background: #f8fafc; border-bottom: 1px solid #e2e8f0; padding: 10px 14px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
                    <div>
                        <span style="font-size: 10.5px; font-weight: 700; color: #64748b; text-transform: uppercase;">Invoice:</span>
                        <strong style="font-family: monospace; color: #0284c7; font-size: 13px; margin-left: 4px;">${escapeHtml(ord.payment_id)}</strong>
                        <span style="font-size: 11.5px; color: #64748b; margin-left: 8px;">${new Date(ord.payment_date).toLocaleDateString()} ${new Date(ord.payment_date).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}</span>
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
    });

    $('#retOrdersContainer').html(html);
}

function selectReturnItem(order, item) {
    selectedReturnOrder = order;
    selectedReturnItem = item;

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

    updateRestockBadge();
    calculateReturnTotal();

    $('#retSearchSection').hide();
    $('#retItemConfigSection').show();
    $('#retModalFooterView1').hide();
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

function confirmAndSubmitReturn() {
    if (!selectedReturnItem || !selectedReturnOrder) {
        alert('No item selected for return.');
        return;
    }

    const orderItemId = $('#retSubmitOrderItemId').val();
    const paymentId = $('#retSubmitPaymentId').val();
    const qty = parseInt($('#retQuantityInput').val()) || 1;
    const reason = $('#retReasonSelect').val();
    const reasonNotes = $('#retReasonNotes').val().trim();
    const condition = $('#retConditionSelect').val();
    const refundMethod = $('#retRefundMethod').val();
    const generalNotes = $('#retGeneralNotes').val().trim();
    const refundTotal = (qty * parseFloat(selectedReturnItem.unit_price)).toFixed(2);

    if (qty < 1 || qty > selectedReturnItem.available_to_return) {
        alert('Invalid return quantity.');
        return;
    }

    if (reason === 'Other' && !reasonNotes) {
        alert('Please specify the custom return reason.');
        $('#retReasonNotes').focus();
        return;
    }

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
        general_notes: generalNotes
    }, function(res) {
        $('#retProcessSubmitBtn').prop('disabled', false).html('<i class="fa fa-paper-plane"></i> Submit Return Request');

        if (res.status === 'success') {
            $('#posReturnModal').modal('hide');
            renderReturnSubmissionSuccessModal(res);
        } else {
            alert('Error: ' + res.message);
        }
    }, 'json').fail(function() {
        $('#retProcessSubmitBtn').prop('disabled', false).html('<i class="fa fa-paper-plane"></i> Submit Return Request');
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
    selectedReturnItem = null;
    selectedReturnOrder = null;
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
    <?php if ($pos_po_success_data): ?>
    $('#posPOSuccessModal').modal({ backdrop: 'static', keyboard: false });
    <?php endif; ?>
});

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.innerText = text;
    return div.innerHTML;
}
</script>

<?php require_once('footer.php'); ?>
