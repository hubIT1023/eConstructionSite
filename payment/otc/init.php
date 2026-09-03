<?php
ob_start();
session_start();
include("../../admin/inc/config.php");
include("../../admin/inc/functions.php");

// Getting all language variables into array as global variable
$i=1;
$statement = $pdo->prepare("SELECT * FROM tbl_language ORDER BY lang_id ASC");
$statement->execute();
$result = $statement->fetchAll(PDO::FETCH_ASSOC);							
foreach ($result as $row) {
	define('LANG_VALUE_'.$i,$row['lang_value']);
	$i++;
}

if( !isset($_REQUEST['msg']) ) {
    if (empty($_SESSION['cart_p_id'])) {
        header('location: ../../checkout.php');
        exit;
    }

    $payment_date = date('Y-m-d H:i:s');
    $otc_delivery_option = isset($_POST['otc_delivery_option']) ? $_POST['otc_delivery_option'] : 'exclude';

    // Resolve customer's delivery Barangay
    $shipping_brgy = '';
    if (!empty($_SESSION['customer']['cust_s_state'])) {
        $shipping_brgy = $_SESSION['customer']['cust_s_state'];
    } elseif (!empty($_SESSION['customer']['cust_state'])) {
        $shipping_brgy = $_SESSION['customer']['cust_state'];
    } elseif (!empty($_SESSION['customer']['cust_b_state'])) {
        $shipping_brgy = $_SESSION['customer']['cust_b_state'];
    }

    $target_brgy_id = 0;
    if (is_numeric($shipping_brgy) && (int)$shipping_brgy > 0) {
        $target_brgy_id = (int)$shipping_brgy;
    } elseif (!empty($shipping_brgy)) {
        $stmt_b = $pdo->prepare("SELECT brgy_id FROM tbl_brgy WHERE LOWER(TRIM(brgy_name)) = LOWER(TRIM(?))");
        $stmt_b->execute(array(trim($shipping_brgy)));
        $row_b = $stmt_b->fetch(PDO::FETCH_ASSOC);
        if ($row_b) {
            $target_brgy_id = (int)$row_b['brgy_id'];
        }
    }

    // Group cart items by supplier
    $supplier_cart = [];
    $i = 0;
    foreach($_SESSION['cart_p_id'] as $key => $value) {
        $i++;
        $p_id = $value;
        $statement_sup = $pdo->prepare("SELECT supplier_id FROM tbl_product WHERE p_id=?");
        $statement_sup->execute(array($p_id));
        $p_row = $statement_sup->fetch(PDO::FETCH_ASSOC);
        $sup_id = $p_row ? $p_row['supplier_id'] : 1; // Fallback to 1

        if (!isset($supplier_cart[$sup_id])) {
            $supplier_cart[$sup_id] = [];
        }
        $supplier_cart[$sup_id][] = [
            'p_id' => $p_id,
            'p_name' => $_SESSION['cart_p_name'][$key],
            'size' => $_SESSION['cart_size_name'][$key],
            'color' => $_SESSION['cart_color_name'][$key],
            'qty' => $_SESSION['cart_p_qty'][$key],
            'price' => $_SESSION['cart_p_current_price'][$key]
        ];
    }

    // Fetch all products to update stock later
    $arr_p_id = [];
    $arr_p_qty = [];
    $statement = $pdo->prepare("SELECT * FROM tbl_product");
    $statement->execute();
    $result = $statement->fetchAll(PDO::FETCH_ASSOC);							
    foreach ($result as $row) {
        $arr_p_id[] = $row['p_id'];
        $arr_p_qty[] = $row['p_qty'];
    }

    // Process order for each supplier
    $created_payment_ids = [];
    foreach ($supplier_cart as $sup_id => $items) {
        $payment_id = 'PO-' . date('Ymd') . '-' . rand(1000, 9999);
        $txnid = $payment_id;
        $created_payment_ids[] = $payment_id;
        
        // Calculate subtotal for this supplier
        $sup_subtotal = 0;
        foreach ($items as $item) {
            $sup_subtotal += $item['price'] * $item['qty'];
        }

        $sup_shipping = 0;
        if ($otc_delivery_option === 'include') {
            // Check supplier shipping rate for this barangay
            if ($target_brgy_id > 0) {
                $stmt_sc = $pdo->prepare("SELECT amount FROM tbl_shipping_cost WHERE country_id = ? AND supplier_id = ?");
                $stmt_sc->execute(array($target_brgy_id, $sup_id));
                $sc_row = $stmt_sc->fetch(PDO::FETCH_ASSOC);
                if ($sc_row) {
                    $sup_shipping = (float)$sc_row['amount'];
                }
            }
            if ($sup_shipping === 0 && $target_brgy_id > 0) {
                $stmt_sc = $pdo->prepare("SELECT amount FROM tbl_shipping_cost WHERE country_id = ?");
                $stmt_sc->execute(array($target_brgy_id));
                $sc_row = $stmt_sc->fetch(PDO::FETCH_ASSOC);
                if ($sc_row) {
                    $sup_shipping = (float)$sc_row['amount'];
                }
            }
            if ($sup_shipping === 0) {
                $stmt_all = $pdo->prepare("SELECT amount FROM tbl_shipping_cost_all WHERE sca_id = 1");
                $stmt_all->execute();
                $all_row = $stmt_all->fetch(PDO::FETCH_ASSOC);
                if ($all_row) {
                    $sup_shipping = (float)$all_row['amount'];
                }
            }
        }

        $sup_total = $sup_subtotal + $sup_shipping;
        $order_note = ($otc_delivery_option === 'exclude') 
            ? 'Over the Counter Purchase (Store Pick-up - No Delivery Fee)' 
            : 'Over the Counter Purchase (With Delivery Fee: ₱' . number_format($sup_shipping, 2) . ')';

        // Fetch supplier info
        $statement_sup_info = $pdo->prepare("SELECT supplier_name, supplier_email, supplier_address, supplier_phone FROM tbl_supplier WHERE supplier_id=?");
        $statement_sup_info->execute(array($sup_id));
        $supplier = $statement_sup_info->fetch(PDO::FETCH_ASSOC);

        // Insert into tbl_payment
        $statement = $pdo->prepare("INSERT INTO tbl_payment (   
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
                            ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $statement->execute(array(
                                $_SESSION['customer']['cust_id'],
                                $_SESSION['customer']['cust_name'],
                                $_SESSION['customer']['cust_email'],
                                $payment_date,
                                $txnid,
                                $sup_total,
                                '', 
                                '',
                                '', 
                                '',
                                $order_note,
                                'Over the Counter',
                                'Awaiting for Payment',
                                'Pending',
                                $payment_id,
                                $sup_id
                            ));

        // Insert into tbl_order and update stock
        $order_list_html = '<table border="1" cellpadding="5" cellspacing="0" style="border-collapse: collapse;">
                                <thead>
                                    <tr>
                                        <th>Product Name</th>
                                        <th>Size</th>
                                        <th>Color</th>
                                        <th>Quantity</th>
                                        <th>Unit Price</th>
                                        <th>Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody>';

        foreach ($items as $item) {
            $statement = $pdo->prepare("INSERT INTO tbl_order (
                            product_id,
                            product_name,
                            size, 
                            color,
                            quantity, 
                            unit_price, 
                            payment_id
                            ) 
                            VALUES (?,?,?,?,?,?,?)");
            $statement->execute(array(
                            $item['p_id'],
                            $item['p_name'],
                            $item['size'],
                            $item['color'],
                            $item['qty'],
                            $item['price'],
                            $payment_id
                        ));

            // Update Stock
            $current_qty = 0;
            for($j=0; $j<count($arr_p_id); $j++) {
                if($arr_p_id[$j] == $item['p_id']) {
                    $current_qty = $arr_p_qty[$j];
                    break;
                }
            }
            $final_quantity = $current_qty - $item['qty'];
            $statement = $pdo->prepare("UPDATE tbl_product SET p_qty=? WHERE p_id=?");
            $statement->execute(array($final_quantity, $item['p_id']));

            $item_subtotal = $item['price'] * $item['qty'];
            $order_list_html .= '<tr>
                                    <td>' . htmlspecialchars($item['p_name']) . '</td>
                                    <td>' . htmlspecialchars($item['size']) . '</td>
                                    <td>' . htmlspecialchars($item['color']) . '</td>
                                    <td>' . htmlspecialchars($item['qty']) . '</td>
                                    <td>₱' . number_format($item['price'], 2) . '</td>
                                    <td>₱' . number_format($item_subtotal, 2) . '</td>
                                 </tr>';
        }

        $order_list_html .= '<tr>
                                <th colspan="5" align="right">Total Amount:</th>
                                <th>₱' . number_format($sup_total, 2) . '</th>
                             </tr>
                             </tbody></table>';

        // Send Email to Customer
        $to_customer = $_SESSION['customer']['cust_email'];
        $subject_customer = "Purchase Order Receipt (Over the Counter) - Order #" . $payment_id;
        
        $message_customer = '
        <html>
        <body>
            <h3>Dear ' . htmlspecialchars($_SESSION['customer']['cust_name']) . ',</h3>
            <p>Thank you for your order! You have chosen to pay <strong>Over the Counter</strong>.</p>
            <p>Please proceed to the supplier\'s store address below to settle the payment and claim your items:</p>
            <hr>
            <p><strong>Supplier:</strong> ' . htmlspecialchars($supplier['supplier_name']) . '<br>
            <strong>Address:</strong> ' . nl2br(htmlspecialchars($supplier['supplier_address'])) . '<br>
            <strong>Phone:</strong> ' . htmlspecialchars($supplier['supplier_phone']) . '</p>
            <hr>
            <h4>Order Details:</h4>
            ' . $order_list_html . '
            <br>
            <p>If you have any questions, please contact the supplier directly at the number listed above.</p>
            <p>Best regards,<br>eConstruction Supply Team</p>
        </body>
        </html>';

        send_system_email($to_customer, $subject_customer, $message_customer);

        // Send Email to Supplier
        $to_supplier = $supplier['supplier_email'];
        $subject_supplier = "New Over the Counter Purchase Order - Order #" . $payment_id;
        
        $message_supplier = '
        <html>
        <body>
            <h3>Dear ' . htmlspecialchars($supplier['supplier_name']) . ' Team,</h3>
            <p>You have received a new purchase order with <strong>Over the Counter</strong> payment.</p>
            <p>Please prepare the items below for pickup and payment collection:</p>
            <hr>
            <p><strong>Customer:</strong> ' . htmlspecialchars($_SESSION['customer']['cust_name']) . '<br>
            <strong>Email:</strong> ' . htmlspecialchars($_SESSION['customer']['cust_email']) . '<br>
            <strong>Phone:</strong> ' . htmlspecialchars($_SESSION['customer']['cust_phone']) . '</p>
            <hr>
            <h4>Order Items:</h4>
            ' . $order_list_html . '
            <br>
            <p>Best regards,<br>eConstruction Supply Team</p>
        </body>
        </html>';

        send_system_email($to_supplier, $subject_supplier, $message_supplier);
    }

    // Clear Cart Sessions
    unset($_SESSION['cart_p_id']);
    unset($_SESSION['cart_size_id']);
    unset($_SESSION['cart_size_name']);
    unset($_SESSION['cart_color_id']);
    unset($_SESSION['cart_color_name']);
    unset($_SESSION['cart_p_qty']);
    unset($_SESSION['cart_p_current_price']);
    unset($_SESSION['cart_p_name']);
    unset($_SESSION['cart_p_featured_photo']);

    $_SESSION['last_po_ids'] = $created_payment_ids;
    header('location: ../../purchase-order-receipt.php?order_id=' . urlencode(implode(',', $created_payment_ids)));
    exit;
}
}
?>
