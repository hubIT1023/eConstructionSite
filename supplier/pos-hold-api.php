<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once('inc/config.php');
require_once('inc/functions.php');

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['supplier_user'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized session. Please log in.']);
    exit;
}

$supplier_id = (int)$_SESSION['supplier_user']['supplier_id'];
$current_user_id = (int)($_SESSION['supplier_user']['id'] ?? 0);
$current_user_name = !empty($_SESSION['supplier_user']['full_name']) ? $_SESSION['supplier_user']['full_name'] : 'Supplier Staff';
$current_user_role = !empty($_SESSION['supplier_user']['role']) ? normalize_supplier_role($_SESSION['supplier_user']['role']) : 'CASHIER';

$action = isset($_POST['action']) ? trim($_POST['action']) : (isset($_GET['action']) ? trim($_GET['action']) : '');

// -------------------------------------------------------------
// 1. HOLD / PARK ACTIVE POS ORDER
// -------------------------------------------------------------
if ($action === 'hold_order') {
    $cart_raw = isset($_POST['cart_items']) ? $_POST['cart_items'] : '';
    $cart_items = is_string($cart_raw) ? json_decode($cart_raw, true) : (is_array($cart_raw) ? $cart_raw : []);

    if (empty($cart_items) || !is_array($cart_items)) {
        echo json_encode(['success' => false, 'message' => 'Cannot hold an empty cart. Please add items first.']);
        exit;
    }

    $customer_type = isset($_POST['customer_type']) ? trim($_POST['customer_type']) : 'walkin';
    $walkin_name = isset($_POST['walkin_name']) ? trim($_POST['walkin_name']) : '';
    $walkin_phone = isset($_POST['walkin_phone']) ? trim($_POST['walkin_phone']) : '';
    $reg_cust_id = isset($_POST['registered_cust_id']) ? (int)$_POST['registered_cust_id'] : 0;
    $is_delivery = (!empty($_POST['is_location_delivery']) && $_POST['is_location_delivery'] == 1);
    $location_brgy_id = isset($_POST['location_brgy_id']) ? (int)$_POST['location_brgy_id'] : 0;
    $delivery_cost = isset($_POST['delivery_cost']) ? floatval($_POST['delivery_cost']) : 0.0;
    $hold_note = isset($_POST['hold_note']) ? trim($_POST['hold_note']) : '';

    $final_cust_id = 0;
    $final_cust_name = 'Walk-in Customer';
    $final_cust_phone = '';
    $final_cust_email = '';
    $final_cust_address = '';

    if ($customer_type === 'registered' && $reg_cust_id > 0) {
        $stmt_c = $pdo->prepare("SELECT * FROM tbl_customer WHERE cust_id = ? LIMIT 1");
        $stmt_c->execute([$reg_cust_id]);
        $c_data = $stmt_c->fetch(PDO::FETCH_ASSOC);
        if ($c_data) {
            $final_cust_id = (int)$c_data['cust_id'];
            $final_cust_name = $c_data['cust_name'] ?: 'Registered Customer';
            $final_cust_phone = $c_data['cust_phone'] ?? '';
            $final_cust_email = $c_data['cust_email'] ?? '';
            $final_cust_address = $c_data['cust_address'] ?? '';
        }
    } else {
        $final_cust_name = !empty($walkin_name) ? $walkin_name : 'Walk-in Customer';
        $final_cust_phone = $walkin_phone;
    }

    // Delivery location name resolution
    $brgy_name = '';
    if ($is_delivery && $location_brgy_id > 0) {
        $stmt_b = $pdo->prepare("SELECT brgy_name FROM tbl_brgy WHERE brgy_id = ? LIMIT 1");
        $stmt_b->execute([$location_brgy_id]);
        $b_row = $stmt_b->fetch(PDO::FETCH_ASSOC);
        if ($b_row) {
            $brgy_name = $b_row['brgy_name'];
            if (empty($final_cust_address)) {
                $final_cust_address = 'Brgy. ' . $brgy_name;
            }
        }
    }

    // Load product map for authoritative pricing
    $stmt_p = $pdo->prepare("SELECT p_id, p_name, p_current_price, p_new_price, p_qty FROM tbl_product WHERE supplier_id = ?");
    $stmt_p->execute([$supplier_id]);
    $prod_map = [];
    while ($pr = $stmt_p->fetch(PDO::FETCH_ASSOC)) {
        $prod_map[$pr['p_id']] = $pr;
    }

    $computed_subtotal = 0.0;
    $computed_discount_total = 0.0;
    $validated_items = [];

    foreach ($cart_items as $item) {
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
            if ($p_id > 0 && isset($prod_map[$p_id])) {
                $p_info = $prod_map[$p_id];
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
            'size' => $size,
            'color' => $color,
            'item_type' => $item_type,
            'product_details' => $p_details,
            'special_order_reference' => $sp_ref,
            'discount_percent' => $disc_pct,
            'discount_amount' => $disc_amt,
            'line_gross' => $line_gross,
            'line_net' => $line_net
        ];
    }

    $grand_total = max(0, $computed_subtotal - $computed_discount_total + ($is_delivery ? $delivery_cost : 0.0));

    // Generate unique sequential PO number
    $po_code = 'PO-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));

    $tx_info = 'Held Order from POS Counter';
    if (!empty($hold_note)) {
        $tx_info .= ' | Note: ' . $hold_note;
    }
    $tx_info .= ' | Staff: ' . $current_user_name;
    if ($final_cust_phone) {
        $tx_info .= ' | Phone: ' . $final_cust_phone;
    }
    if ($final_cust_address) {
        $tx_info .= ' | Address: ' . $final_cust_address;
    }

    $payment_date = date('Y-m-d H:i:s');

    $pdo->beginTransaction();
    try {
        // Insert into tbl_payment
        $stmt_pay = $pdo->prepare("
            INSERT INTO tbl_payment (
                customer_id, customer_name, customer_email, payment_date,
                txnid, paid_amount, card_number, card_cvv, card_month, card_year,
                bank_transaction_info, payment_method, payment_status,
                shipping_status, payment_id, supplier_id
            ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?) RETURNING id
        ");
        $stmt_pay->execute([
            $final_cust_id,
            $final_cust_name,
            $final_cust_email,
            $payment_date,
            $po_code,
            number_format($grand_total, 2, '.', ''),
            '', '', '', '',
            $tx_info,
            'Purchase Order (PO)',
            'Awaiting for Payment',
            $is_delivery ? 'Pending' : 'Completed',
            $po_code,
            $supplier_id
        ]);
        $inserted_payment_id = $stmt_pay->fetchColumn();

        // Insert line items into tbl_order and deduct stock for standard items
        foreach ($validated_items as $v_it) {
            $stmt_ord = $pdo->prepare("
                INSERT INTO tbl_order (
                    product_id, product_name, size, color, quantity, unit_price,
                    payment_id, supplier_id, item_type,
                    special_order_reference, product_details, discount_percent, discount_amount
                ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)
            ");
            $stmt_ord->execute([
                $v_it['id'],
                $v_it['name'],
                $v_it['size'],
                $v_it['color'],
                $v_it['qty'],
                number_format($v_it['price'], 2, '.', ''),
                $po_code,
                $supplier_id,
                $v_it['item_type'],
                $v_it['special_order_reference'],
                $v_it['product_details'],
                $v_it['discount_percent'],
                $v_it['discount_amount']
            ]);

            // Soft-hold inventory deduction so items aren't double-sold
            if ($v_it['item_type'] === 'STANDARD' && $v_it['id'] > 0) {
                $stmt_stock = $pdo->prepare("UPDATE tbl_product SET p_qty = GREATEST(0, p_qty - ?) WHERE p_id = ? AND supplier_id = ?");
                $stmt_stock->execute([$v_it['qty'], $v_it['id'], $supplier_id]);
            }
        }

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Failed to save held order: ' . $e->getMessage()]);
        exit;
    }

    // Clear active POS session cart
    $_SESSION['pos_cart'] = [];
    $_SESSION['supplier_checkout_cart'] = [];

    // Fetch store profile for thermal slip
    $stmt_s = $pdo->prepare("SELECT * FROM tbl_supplier WHERE supplier_id = ? LIMIT 1");
    $stmt_s->execute([$supplier_id]);
    $store = $stmt_s->fetch(PDO::FETCH_ASSOC);
    $store_name = $store['supplier_name'] ?? 'E-Construction Supply';
    $store_address = $store['supplier_address'] ?? '';
    $store_phone = $store['supplier_phone'] ?? '09612735733';

    // Build thermal voucher HTML with revised footer: *** ORDER ON HOLD ***
    ob_start();
    ?>
    <div class="thermal-receipt" style="font-family: 'Courier New', Consolas, monospace; font-size: 11pt; line-height: 1.25; color: #000; text-align: left; width: 100%; max-width: 280px; margin: 0 auto; background: #fff; padding: 5px;">
        <div style="text-align: center; font-weight: bold; overflow: hidden; white-space: nowrap;">================================</div>
        <div style="text-align: center;">
            <div style="font-size: 12pt; font-weight: bold; text-transform: uppercase;"><?php echo htmlspecialchars(strtoupper($store_name)); ?></div>
            <?php if (!empty($store_address)): ?>
                <div style="font-size: 9.5pt;"><?php echo htmlspecialchars($store_address); ?></div>
            <?php endif; ?>
            <div style="font-size: 10pt;">Tel: <?php echo htmlspecialchars($store_phone); ?></div>
            <div style="font-size: 11pt; font-weight: bold; text-transform: uppercase; margin-top: 3px;">PURCHASE ORDER VOUCHER</div>
            <div style="font-size: 10pt; font-weight: bold; text-transform: uppercase;">(UNPAID)</div>
        </div>
        <div style="text-align: center; font-weight: bold; overflow: hidden; white-space: nowrap;">================================</div>
        <table style="width: 100%; border-collapse: collapse; font-family: inherit; font-size: 10.5pt; margin-bottom: 2px;">
            <tr><td style="width: 28%; font-weight: bold;">PO NO   :</td><td style="font-weight: bold;"><?php echo htmlspecialchars($po_code); ?></td></tr>
            <tr><td style="font-weight: bold;">CUSTOMER:</td><td><?php echo htmlspecialchars($final_cust_name); ?></td></tr>
            <tr><td style="font-weight: bold;">STATUS  :</td><td style="font-weight: bold;">AWAITING PAYMENT</td></tr>
            <tr><td style="font-weight: bold;">DATE    :</td><td><?php echo date('Y-m-d H:i:s', strtotime($payment_date)); ?></td></tr>
            <?php if (!empty($hold_note)): ?>
            <tr><td style="font-weight: bold;">NOTE    :</td><td><?php echo htmlspecialchars($hold_note); ?></td></tr>
            <?php endif; ?>
        </table>
        <div style="text-align: center; overflow: hidden; white-space: nowrap;">--------------------------------</div>
        <table style="width: 100%; border-collapse: collapse; font-family: inherit; font-size: 10.5pt;">
            <thead>
                <tr>
                    <th style="text-align: left; width: 68%;">ITEM DESCRIPTION</th>
                    <th style="text-align: right; width: 32%;">AMOUNT</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($validated_items as $v_row): 
                    $u_label = ($v_row['qty'] > 1 ? 'pcs' : 'pc');
                ?>
                <tr>
                    <td colspan="2" style="text-align: left; padding-top: 3px; font-weight: bold; word-break: break-word;">
                        <?php if ($v_row['item_type'] === 'SPECIAL_ORDER'): ?>[SPECIAL ORDER] <?php endif; ?>
                        <?php echo htmlspecialchars($v_row['name']); ?>
                        <?php if (!empty($v_row['size']) || !empty($v_row['color'])): ?>
                            <div style="font-size: 9pt; font-weight: normal;">
                                <?php if (!empty($v_row['size']) && $v_row['size'] !== '-') echo 'Size: ' . htmlspecialchars($v_row['size']) . ' '; ?>
                                <?php if (!empty($v_row['color']) && $v_row['color'] !== '-') echo 'Color: ' . htmlspecialchars($v_row['color']); ?>
                            </div>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <td style="text-align: left; padding-left: 8px; padding-bottom: 3px;">
                        <?php echo $v_row['qty']; ?> <?php echo $u_label; ?> @ <?php echo number_format($v_row['price'], 2); ?>
                    </td>
                    <td style="text-align: right; padding-bottom: 3px; white-space: nowrap; vertical-align: bottom;">
                        <?php echo number_format($v_row['line_gross'], 2); ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <div style="text-align: center; overflow: hidden; white-space: nowrap;">--------------------------------</div>
        <table style="width: 100%; border-collapse: collapse; font-family: inherit; font-size: 10.5pt; margin: 2px 0;">
            <tr><td>Subtotal:</td><td style="text-align: right;"><?php echo number_format($computed_subtotal, 2); ?></td></tr>
            <?php if ($computed_discount_total > 0): ?>
            <tr><td>Discount:</td><td style="text-align: right; color: #16a34a;">-<?php echo number_format($computed_discount_total, 2); ?></td></tr>
            <?php endif; ?>
            <?php if ($is_delivery && $delivery_cost > 0): ?>
            <tr><td>Delivery:</td><td style="text-align: right;">+<?php echo number_format($delivery_cost, 2); ?></td></tr>
            <?php endif; ?>
            <tr style="font-weight: bold;"><td style="font-size: 1.08em;">TOTAL DUE:</td><td style="text-align: right; font-size: 1.08em;">PHP <?php echo number_format($grand_total, 2); ?></td></tr>
        </table>
        <div style="text-align: center; font-weight: bold; overflow: hidden; white-space: nowrap;">================================</div>
        <div style="text-align: center; font-weight: bold; padding: 4px 0; font-size: 11pt; letter-spacing: 0.5px;">
            *** ORDER ON HOLD ***
        </div>
        <div style="text-align: center; font-weight: bold; overflow: hidden; white-space: nowrap;">================================</div>
    </div>
    <?php
    $thermal_html = ob_get_clean();

    echo json_encode([
        'success' => true,
        'po_id' => $po_code,
        'customer_name' => $final_cust_name,
        'item_count' => count($validated_items),
        'grand_total' => $grand_total,
        'print_html' => $thermal_html,
        'message' => 'Order successfully held as ' . $po_code
    ]);
    exit;
}

// -------------------------------------------------------------
// 2. LIST CURRENT HELD / PENDING POS FOR THIS SUPPLIER
// -------------------------------------------------------------
elseif ($action === 'list_held_orders') {
    try {
        $stmt_list = $pdo->prepare("
            SELECT p.*, 
                   COUNT(o.id) as item_count, 
                   COALESCE(SUM(CAST(NULLIF(o.quantity, '') AS integer)), 0) as total_units
            FROM tbl_payment p
            LEFT JOIN tbl_order o ON p.payment_id = o.payment_id
            WHERE p.supplier_id = ? 
              AND (p.payment_status = 'Awaiting for Payment' OR p.payment_status = 'Pending' OR p.payment_status = 'UNPAID')
              AND p.payment_status != 'Paid'
              AND p.payment_status != 'Completed'
            GROUP BY p.id
            ORDER BY p.id DESC
            LIMIT 50
        ");
        $stmt_list->execute([$supplier_id]);
        $rows = $stmt_list->fetchAll(PDO::FETCH_ASSOC);

        $results = [];
        $now = time();
        foreach ($rows as $r) {
            $ts = strtotime($r['payment_date']);
            $diff = $now - $ts;
            if ($diff < 60) $rel_time = 'Just now';
            elseif ($diff < 3600) $rel_time = floor($diff / 60) . ' mins ago';
            elseif ($diff < 86400) $rel_time = floor($diff / 3600) . ' hrs ago';
            else $rel_time = date('M d, h:i A', $ts);

            $note = '';
            if (!empty($r['bank_transaction_info']) && preg_match('/Note:\s*([^|]+)/i', $r['bank_transaction_info'], $mn)) {
                $note = trim($mn[1]);
            }

            $results[] = [
                'id' => $r['id'],
                'po_id' => $r['payment_id'],
                'customer_name' => $r['customer_name'] ?: 'Walk-in Customer',
                'customer_phone' => $r['customer_phone'] ?? '',
                'payment_date' => $r['payment_date'],
                'relative_time' => $rel_time,
                'item_count' => (int)$r['item_count'],
                'total_units' => (int)$r['total_units'],
                'paid_amount' => floatval($r['paid_amount']),
                'note' => $note
            ];
        }

        echo json_encode(['success' => true, 'orders' => $results, 'count' => count($results)]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error fetching held orders: ' . $e->getMessage()]);
    }
    exit;
}

// -------------------------------------------------------------
// 3. CANCEL & RESTOCK HELD ORDER
// -------------------------------------------------------------
elseif ($action === 'cancel_held_order') {
    $target_po = isset($_POST['po_id']) ? trim($_POST['po_id']) : '';
    if (empty($target_po)) {
        echo json_encode(['success' => false, 'message' => 'Missing Purchase Order identifier.']);
        exit;
    }

    try {
        $stmt_c = $pdo->prepare("SELECT * FROM tbl_payment WHERE payment_id = ? AND supplier_id = ? LIMIT 1");
        $stmt_c->execute([$target_po, $supplier_id]);
        $pay = $stmt_c->fetch(PDO::FETCH_ASSOC);

        if (!$pay) {
            echo json_encode(['success' => false, 'message' => 'Held order not found.']);
            exit;
        }

        if (strtolower($pay['payment_status']) === 'paid') {
            echo json_encode(['success' => false, 'message' => 'Cannot cancel an order that is already marked Paid.']);
            exit;
        }

        $pdo->beginTransaction();

        // Restock items
        $stmt_o = $pdo->prepare("SELECT product_id, quantity, item_type FROM tbl_order WHERE payment_id = ? AND supplier_id = ?");
        $stmt_o->execute([$target_po, $supplier_id]);
        $items_to_restock = $stmt_o->fetchAll(PDO::FETCH_ASSOC);

        foreach ($items_to_restock as $it) {
            if ($it['item_type'] === 'STANDARD' && (int)$it['product_id'] > 0) {
                $stmt_up_st = $pdo->prepare("UPDATE tbl_product SET p_qty = p_qty + ? WHERE p_id = ? AND supplier_id = ?");
                $stmt_up_st->execute([(int)$it['quantity'], (int)$it['product_id'], $supplier_id]);
            }
        }

        // Mark as Cancelled
        $stmt_up_p = $pdo->prepare("UPDATE tbl_payment SET payment_status = 'Cancelled' WHERE payment_id = ? AND supplier_id = ?");
        $stmt_up_p->execute([$target_po, $supplier_id]);

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Held order ' . $target_po . ' has been cancelled and items restocked.']);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Error cancelling held order: ' . $e->getMessage()]);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action.']);
exit;
