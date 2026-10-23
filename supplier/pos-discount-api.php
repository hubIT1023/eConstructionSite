<?php
ob_start();
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

header('Content-Type: application/json; charset=utf-8');

// Supplier Authentication Check
if (!isset($_SESSION['supplier_user']) || empty($_SESSION['supplier_user']['supplier_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access. Please log in.']);
    exit;
}

$supplier_id = (int)$_SESSION['supplier_user']['supplier_id'];
$current_user_id = (int)$_SESSION['supplier_user']['id'];
$current_user_name = !empty($_SESSION['supplier_user']['full_name']) ? $_SESSION['supplier_user']['full_name'] : 'Supplier User';
$user_role_raw = isset($_SESSION['supplier_user']['role']) ? $_SESSION['supplier_user']['role'] : 'USER';
$user_role = normalize_supplier_role($user_role_raw);
$is_admin = is_admin_or_manager_role($user_role);
$is_approver = is_supplier_approver();

$action = isset($_REQUEST['action']) ? trim($_REQUEST['action']) : '';

// Fetch Store Discount Policy
$stmt_policy = $pdo->prepare("SELECT discount_enabled, discount_normal_max, discount_special_enabled, discount_absolute_max, discount_require_admin_approval FROM tbl_supplier WHERE supplier_id = ?");
$stmt_policy->execute(array($supplier_id));
$policy = $stmt_policy->fetch(PDO::FETCH_ASSOC);

$discount_enabled = isset($policy['discount_enabled']) ? (int)$policy['discount_enabled'] : 1;
$normal_max = isset($policy['discount_normal_max']) ? floatval($policy['discount_normal_max']) : 10.00;
$special_enabled = isset($policy['discount_special_enabled']) ? (int)$policy['discount_special_enabled'] : 1;
$absolute_max = isset($policy['discount_absolute_max']) ? floatval($policy['discount_absolute_max']) : 20.00;
$require_approval = isset($policy['discount_require_admin_approval']) ? (int)$policy['discount_require_admin_approval'] : 1;

// Helper: Generate Unique Discount Request ID
function generateDiscountRequestId($pdo) {
    $date_prefix = date('Ymd');
    for ($i = 0; $i < 10; $i++) {
        $rand_suffix = strtoupper(substr(uniqid(), -5));
        $ref = 'DR-' . $date_prefix . '-' . $rand_suffix;
        $check = $pdo->prepare("SELECT id FROM tbl_discount_requests WHERE request_id = ?");
        $check->execute(array($ref));
        if (!$check->fetch()) {
            return $ref;
        }
    }
    return 'DR-' . $date_prefix . '-' . rand(10000, 99999);
}

// -------------------------------------------------------------------------
// 1. ACTION: REQUEST DISCOUNT
// -------------------------------------------------------------------------
if ($action === 'request_discount') {
    if (!$discount_enabled) {
        echo json_encode([
            'status' => 'error',
            'code' => 'DISCOUNT_DISABLED',
            'message' => 'Item discounts are currently disabled in Store Settings.'
        ]);
        exit;
    }

    $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
    $item_type = (isset($_POST['item_type']) && $_POST['item_type'] === 'SPECIAL_ORDER') ? 'SPECIAL_ORDER' : 'STANDARD';
    $raw_name = isset($_POST['name']) ? trim($_POST['name']) : '';
    $sku = isset($_POST['sku']) ? trim($_POST['sku']) : '';
    $product_details = isset($_POST['product_details']) ? trim($_POST['product_details']) : '';
    $special_order_reference = isset($_POST['special_order_reference']) ? trim($_POST['special_order_reference']) : '';
    $qty = max(1, isset($_POST['quantity']) ? intval($_POST['quantity']) : 1);
    $cashier_remarks = isset($_POST['cashier_remarks']) ? trim($_POST['cashier_remarks']) : '';

    // Authoritative Price Retrieval
    $authoritative_unit_price = 0.00;
    $authoritative_product_name = $raw_name;

    if ($item_type === 'STANDARD') {
        $stmt_prod = $pdo->prepare("SELECT p_id, p_name, p_current_price, p_sku FROM tbl_product WHERE p_id = ? AND supplier_id = ? AND p_is_active = 1");
        $stmt_prod->execute(array($product_id, $supplier_id));
        $prod_row = $stmt_prod->fetch(PDO::FETCH_ASSOC);

        if (!$prod_row) {
            echo json_encode(['status' => 'error', 'message' => 'Product not found or inactive in catalog.']);
            exit;
        }

        $authoritative_product_name = $prod_row['p_name'];
        $authoritative_unit_price = floatval(preg_replace('/[^0-9.]/', '', $prod_row['p_current_price']));
        if (!empty($prod_row['p_sku'])) {
            $sku = $prod_row['p_sku'];
        }
    } else {
        // Special order unit price from input
        $authoritative_unit_price = isset($_POST['unit_price']) ? max(0, floatval($_POST['unit_price'])) : 0.00;
        if (empty($authoritative_product_name)) {
            $authoritative_product_name = 'Custom Special Order';
        }
    }

    $original_item_value = round($authoritative_unit_price * $qty, 2);

    if ($original_item_value <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Item gross value must be greater than ₱0.00.']);
        exit;
    }

    // Process Requested Discount Amount (Strictly Amount-Based in ₱)
    $requested_amt_input = isset($_POST['requested_discount_amount']) ? trim($_POST['requested_discount_amount']) : '';

    if ($requested_amt_input === '' || !is_numeric($requested_amt_input)) {
        echo json_encode(['status' => 'error', 'message' => 'Please enter a valid discount amount greater than ₱0.00.']);
        exit;
    }

    $discount_amount = round(floatval($requested_amt_input), 2);
    if ($discount_amount <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Please enter a valid discount amount greater than ₱0.00.']);
        exit;
    }
    if ($discount_amount >= $original_item_value) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Discount amount (₱' . number_format($discount_amount, 2) . ') cannot equal or exceed item gross total (₱' . number_format($original_item_value, 2) . ').'
        ]);
        exit;
    }

    // Determine Requester Role & Required Approval Role
    $requester_role = normalize_supplier_role($user_role);
    $required_approval_role = get_required_approval_role($requester_role);

    // Classification & Approval (Amount-Based)
    $approval_level = 'SUPPLIER_ADMIN';
    $status = 'PENDING';
    $requested_pct = 0.00;
    $approved_pct = 0.00;
    $normal_max_pct = isset($normal_max) ? floatval($normal_max) : 0.00;
    $absolute_max_pct = isset($absolute_max) ? floatval($absolute_max) : 0.00;
    $final_value = round($original_item_value - $discount_amount, 2);

    if (!$require_approval) {
        $status = 'APPROVED';
    }

    $request_id = generateDiscountRequestId($pdo);

    $stmt_ins = $pdo->prepare("
        INSERT INTO tbl_discount_requests (
            request_id,
            supplier_id,
            cashier_id,
            cashier_name,
            requester_role,
            required_approval_role,
            product_id,
            product_name,
            sku,
            item_type,
            special_order_reference,
            product_details,
            quantity,
            original_unit_price,
            original_item_value,
            requested_discount_percent,
            normal_max_discount_percent,
            absolute_max_discount_percent,
            approved_discount_percent,
            discount_amount,
            final_item_value,
            approval_level,
            status,
            cashier_remarks
        ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?) RETURNING id
    ");

    try {
        $stmt_ins->execute(array(
            $request_id,
            $supplier_id,
            $current_user_id,
            $current_user_name,
            $requester_role,
            $required_approval_role,
            $product_id,
            $authoritative_product_name,
            $sku,
            $item_type,
            $special_order_reference,
            $product_details,
            $qty,
            $authoritative_unit_price,
            $original_item_value,
            $requested_pct,
            $normal_max_pct,
            $absolute_max_pct,
            $approved_pct,
            $discount_amount,
            $final_value,
            $approval_level,
            $status,
            $cashier_remarks
        ));
        $new_id = (int)$stmt_ins->fetchColumn();
    } catch (PDOException $e) {
        error_log('Error inserting discount request: ' . $e->getMessage());
        echo json_encode([
            'status' => 'error',
            'message' => 'Database error recording discount request: ' . $e->getMessage()
        ]);
        exit;
    }

    // Calculations for display
    $preview_discount_amt = $discount_amount;
    $preview_final_val = $final_value;

    echo json_encode([
        'status' => 'success',
        'request_id' => $request_id,
        'db_id' => $new_id,
        'discount_status' => $status,
        'approval_level' => $approval_level,
        'requester_role' => $requester_role,
        'required_approval_role' => $required_approval_role,
        'product_name' => $authoritative_product_name,
        'original_unit_price' => $authoritative_unit_price,
        'original_item_value' => $original_item_value,
        'requested_discount_percent' => $requested_pct,
        'approved_discount_percent' => $approved_pct,
        'discount_amount' => $discount_amount,
        'final_item_value' => $final_value,
        'normal_max' => $normal_max_pct,
        'absolute_max' => $absolute_max_pct,
        'message' => ($status === 'APPROVED') 
            ? 'Discount auto-approved.' 
            : 'Discount request submitted. Awaiting approval from ' . $required_approval_role . '.'
    ]);
    exit;
}

// -------------------------------------------------------------------------
// 2. ACTION: POLL STATUS (FOR CASHIER REAL-TIME CART UPDATE)
// -------------------------------------------------------------------------
if ($action === 'poll_status') {
    $raw_reqs = isset($_REQUEST['request_ids']) ? $_REQUEST['request_ids'] : '';
    $req_ids = [];
    if (is_array($raw_reqs)) {
        $req_ids = array_filter(array_map('trim', $raw_reqs));
    } elseif (!empty($raw_reqs)) {
        $req_ids = array_filter(array_map('trim', explode(',', $raw_reqs)));
    }

    if (empty($req_ids)) {
        echo json_encode(['status' => 'success', 'requests' => []]);
        exit;
    }

    $placeholders = implode(',', array_fill(0, count($req_ids), '?'));
    $params = array_merge([$supplier_id], $req_ids);

    $stmt_poll = $pdo->prepare("
        SELECT 
            request_id,
            status,
            approval_level,
            requester_role,
            required_approval_role,
            requested_discount_percent,
            approved_discount_percent,
            discount_amount,
            final_item_value,
            original_unit_price,
            original_item_value,
            quantity,
            approver_name,
            approver_role,
            approver_remarks,
            updated_at
        FROM tbl_discount_requests 
        WHERE supplier_id = ? AND request_id IN ($placeholders)
    ");
    $stmt_poll->execute($params);
    $rows = $stmt_poll->fetchAll(PDO::FETCH_ASSOC);

    $result_map = [];
    foreach ($rows as $r) {
        $result_map[$r['request_id']] = [
            'request_id' => $r['request_id'],
            'status' => $r['status'],
            'approval_level' => $r['approval_level'],
            'requester_role' => $r['requester_role'] ?: 'CASHIER',
            'required_approval_role' => $r['required_approval_role'] ?: 'Supervisor OR Admin / Manager',
            'requested_discount_percent' => floatval($r['requested_discount_percent']),
            'approved_discount_percent' => ($r['approved_discount_percent'] !== null) ? floatval($r['approved_discount_percent']) : null,
            'discount_amount' => ($r['discount_amount'] !== null) ? floatval($r['discount_amount']) : null,
            'final_item_value' => ($r['final_item_value'] !== null) ? floatval($r['final_item_value']) : null,
            'original_unit_price' => floatval($r['original_unit_price']),
            'original_item_value' => floatval($r['original_item_value']),
            'quantity' => intval($r['quantity']),
            'approver_name' => $r['approver_name'] ?: '',
            'approver_role' => $r['approver_role'] ?: '',
            'approver_remarks' => $r['approver_remarks'] ?: '',
            'updated_at' => $r['updated_at']
        ];
    }

    echo json_encode([
        'status' => 'success',
        'requests' => $result_map
    ]);
    exit;
}

// -------------------------------------------------------------------------
// 3. ACTION: GET PENDING REQUESTS (FOR APPROVAL QUEUE MODAL & HEADER BADGE)
// -------------------------------------------------------------------------
if ($action === 'get_pending_requests') {
    if (!$is_approver) {
        echo json_encode(['status' => 'error', 'message' => 'Approver authorization required.']);
        exit;
    }

    $stmt_queue = $pdo->prepare("
        SELECT * FROM tbl_discount_requests 
        WHERE supplier_id = ? AND status IN ('PENDING', 'ESCALATED') 
        ORDER BY id DESC LIMIT 50
    ");
    $stmt_queue->execute(array($supplier_id));
    $pending_list = $stmt_queue->fetchAll(PDO::FETCH_ASSOC);

    $annotated_list = [];
    $approvable_count = 0;

    foreach ($pending_list as $r) {
        $req_role = !empty($r['requester_role']) ? $r['requester_role'] : 'CASHIER';
        $can_approve = can_user_approve_discount($r['cashier_id'], $req_role, $current_user_id, $user_role, true);
        
        $r['can_approve'] = $can_approve;
        $r['is_self'] = ((int)$r['cashier_id'] === $current_user_id);
        $r['requester_role'] = $req_role;
        $r['required_approval_role'] = !empty($r['required_approval_role']) ? $r['required_approval_role'] : get_required_approval_role($req_role);
        
        if ($can_approve) {
            $approvable_count++;
        }
        $annotated_list[] = $r;
    }

    echo json_encode([
        'status' => 'success',
        'count' => count($annotated_list),
        'approvable_count' => $approvable_count,
        'requests' => $annotated_list
    ]);
    exit;
}

// -------------------------------------------------------------------------
// 4. ACTION: APPROVE DISCOUNT
// -------------------------------------------------------------------------
if ($action === 'approve_discount') {
    if (!$is_approver) {
        echo json_encode(['status' => 'error', 'message' => 'Only authorized approvers can approve discounts.']);
        exit;
    }

    $req_id = isset($_POST['request_id']) ? trim($_POST['request_id']) : '';
    $remarks = isset($_POST['approver_remarks']) ? trim($_POST['approver_remarks']) : '';

    $stmt_f = $pdo->prepare("SELECT * FROM tbl_discount_requests WHERE request_id = ? AND supplier_id = ?");
    $stmt_f->execute(array($req_id, $supplier_id));
    $req = $stmt_f->fetch(PDO::FETCH_ASSOC);

    if (!$req) {
        echo json_encode(['status' => 'error', 'message' => 'Discount request not found.']);
        exit;
    }

    if (!in_array($req['status'], ['PENDING', 'ESCALATED'])) {
        echo json_encode(['status' => 'error', 'message' => 'Request is already ' . strtolower($req['status']) . '.']);
        exit;
    }

    $req_role = !empty($req['requester_role']) ? $req['requester_role'] : 'CASHIER';
    
    // Authoritative Role-Based Hierarchy & Self-Approval Check
    $is_allowed = can_user_approve_discount($req['cashier_id'], $req_role, $current_user_id, $user_role, ($req['supplier_id'] == $supplier_id));
    
    if (!$is_allowed) {
        if ((int)$req['cashier_id'] === $current_user_id) {
            echo json_encode([
                'status' => 'error',
                'code' => 'SELF_APPROVAL_FORBIDDEN',
                'message' => 'Self-approval is prohibited for ' . ucfirst(strtolower($req_role)) . ' role. An authorized manager must approve your discount request.'
            ]);
        } else {
            echo json_encode([
                'status' => 'error',
                'code' => 'UNAUTHORIZED_APPROVER',
                'message' => 'You are not authorized to approve discount requests created by a ' . ucfirst(strtolower($req_role)) . '. Requires ' . get_required_approval_role($req_role) . '.'
            ]);
        }
        exit;
    }

    $orig_val = floatval($req['original_item_value']);
    $discount_amt = floatval($req['discount_amount']);
    if ($discount_amt <= 0 || $discount_amt >= $orig_val) {
        $discount_amt = min(max(0, $discount_amt), $orig_val);
    }
    $final_val = round($orig_val - $discount_amt, 2);

    $stmt_up = $pdo->prepare("
        UPDATE tbl_discount_requests SET 
            status = 'APPROVED',
            approved_discount_percent = 0.00,
            discount_amount = ?,
            final_item_value = ?,
            approver_id = ?,
            approver_name = ?,
            approver_role = ?,
            approver_remarks = ?,
            updated_at = NOW()
        WHERE request_id = ? AND supplier_id = ?
    ");
    $stmt_up->execute(array(
        $discount_amt,
        $final_val,
        $current_user_id,
        $current_user_name,
        $user_role,
        $remarks,
        $req_id,
        $supplier_id
    ));

    echo json_encode([
        'status' => 'success',
        'message' => 'Discount request approved successfully.',
        'request_id' => $req_id,
        'approved_discount_percent' => 0.00,
        'discount_amount' => $discount_amt,
        'final_item_value' => $final_val,
        'approver_role' => $user_role
    ]);
    exit;
}

// -------------------------------------------------------------------------
// 5. ACTION: MODIFY DISCOUNT
// -------------------------------------------------------------------------
if ($action === 'modify_discount') {
    if (!$is_approver) {
        echo json_encode(['status' => 'error', 'message' => 'Only authorized approvers can modify discounts.']);
        exit;
    }

    $req_id = isset($_POST['request_id']) ? trim($_POST['request_id']) : '';
    $remarks = isset($_POST['approver_remarks']) ? trim($_POST['approver_remarks']) : '';

    $stmt_f = $pdo->prepare("SELECT * FROM tbl_discount_requests WHERE request_id = ? AND supplier_id = ?");
    $stmt_f->execute(array($req_id, $supplier_id));
    $req = $stmt_f->fetch(PDO::FETCH_ASSOC);

    if (!$req) {
        echo json_encode(['status' => 'error', 'message' => 'Discount request not found.']);
        exit;
    }

    if (!in_array($req['status'], ['PENDING', 'ESCALATED'])) {
        echo json_encode(['status' => 'error', 'message' => 'Request is already ' . strtolower($req['status']) . '.']);
        exit;
    }

    $req_role = !empty($req['requester_role']) ? $req['requester_role'] : 'CASHIER';
    
    // Authoritative Role-Based Hierarchy & Self-Approval Check
    $is_allowed = can_user_approve_discount($req['cashier_id'], $req_role, $current_user_id, $user_role, ($req['supplier_id'] == $supplier_id));
    
    if (!$is_allowed) {
        if ((int)$req['cashier_id'] === $current_user_id) {
            echo json_encode([
                'status' => 'error',
                'code' => 'SELF_APPROVAL_FORBIDDEN',
                'message' => 'Self-approval is prohibited for ' . ucfirst(strtolower($req_role)) . ' role. An authorized manager must modify this discount request.'
            ]);
        } else {
            echo json_encode([
                'status' => 'error',
                'code' => 'UNAUTHORIZED_APPROVER',
                'message' => 'You are not authorized to modify discount requests created by a ' . ucfirst(strtolower($req_role)) . '. Requires ' . get_required_approval_role($req_role) . '.'
            ]);
        }
        exit;
    }

    $orig_val = floatval($req['original_item_value']);
    $mod_amt_input = isset($_POST['modified_discount_amount']) ? trim($_POST['modified_discount_amount']) : '';

    if ($mod_amt_input === '' || !is_numeric($mod_amt_input)) {
        echo json_encode(['status' => 'error', 'message' => 'Please provide a valid modified discount amount in ₱.']);
        exit;
    }

    $discount_amt = round(floatval($mod_amt_input), 2);
    if ($discount_amt <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Modified discount amount must be greater than ₱0.00.']);
        exit;
    }
    if ($discount_amt >= $orig_val) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Modified discount amount (₱' . number_format($discount_amt, 2) . ') cannot equal or exceed item gross total (₱' . number_format($orig_val, 2) . ').'
        ]);
        exit;
    }

    $final_val = round($orig_val - $discount_amt, 2);

    $stmt_up = $pdo->prepare("
        UPDATE tbl_discount_requests SET 
            status = 'APPROVED_MODIFIED',
            approved_discount_percent = 0.00,
            discount_amount = ?,
            final_item_value = ?,
            approver_id = ?,
            approver_name = ?,
            approver_role = ?,
            approver_remarks = ?,
            updated_at = NOW()
        WHERE request_id = ? AND supplier_id = ?
    ");
    $stmt_up->execute(array(
        $discount_amt,
        $final_val,
        $current_user_id,
        $current_user_name,
        $user_role,
        $remarks,
        $req_id,
        $supplier_id
    ));

    echo json_encode([
        'status' => 'success',
        'message' => 'Discount modified to ₱' . number_format($discount_amt, 2) . ' and approved.',
        'request_id' => $req_id,
        'approved_discount_percent' => 0.00,
        'discount_amount' => $discount_amt,
        'final_item_value' => $final_val,
        'approver_role' => $user_role
    ]);
    exit;
}

// -------------------------------------------------------------------------
// 6. ACTION: REJECT DISCOUNT
// -------------------------------------------------------------------------
if ($action === 'reject_discount') {
    if (!$is_approver) {
        echo json_encode(['status' => 'error', 'message' => 'Only authorized approvers can reject discounts.']);
        exit;
    }

    $req_id = isset($_POST['request_id']) ? trim($_POST['request_id']) : '';
    $remarks = isset($_POST['approver_remarks']) ? trim($_POST['approver_remarks']) : '';

    $stmt_f = $pdo->prepare("SELECT * FROM tbl_discount_requests WHERE request_id = ? AND supplier_id = ?");
    $stmt_f->execute(array($req_id, $supplier_id));
    $req = $stmt_f->fetch(PDO::FETCH_ASSOC);

    if (!$req) {
        echo json_encode(['status' => 'error', 'message' => 'Discount request not found.']);
        exit;
    }

    $req_role = !empty($req['requester_role']) ? $req['requester_role'] : 'CASHIER';
    
    // Authoritative Role-Based Hierarchy & Self-Approval Check
    $is_allowed = can_user_approve_discount($req['cashier_id'], $req_role, $current_user_id, $user_role, ($req['supplier_id'] == $supplier_id));
    
    if (!$is_allowed) {
        if ((int)$req['cashier_id'] === $current_user_id) {
            echo json_encode([
                'status' => 'error',
                'code' => 'SELF_APPROVAL_FORBIDDEN',
                'message' => 'Self-approval is prohibited for ' . ucfirst(strtolower($req_role)) . ' role. An authorized manager must reject this discount request.'
            ]);
        } else {
            echo json_encode([
                'status' => 'error',
                'code' => 'UNAUTHORIZED_APPROVER',
                'message' => 'You are not authorized to reject discount requests created by a ' . ucfirst(strtolower($req_role)) . '. Requires ' . get_required_approval_role($req_role) . '.'
            ]);
        }
        exit;
    }

    $stmt_up = $pdo->prepare("
        UPDATE tbl_discount_requests SET 
            status = 'REJECTED',
            approved_discount_percent = 0.00,
            discount_amount = 0.00,
            final_item_value = original_item_value,
            approver_id = ?,
            approver_name = ?,
            approver_role = ?,
            approver_remarks = ?,
            updated_at = NOW()
        WHERE request_id = ? AND supplier_id = ?
    ");
    $stmt_up->execute(array(
        $current_user_id,
        $current_user_name,
        $user_role,
        $remarks,
        $req_id,
        $supplier_id
    ));

    echo json_encode([
        'status' => 'success',
        'message' => 'Discount request has been rejected.',
        'request_id' => $req_id,
        'approver_role' => $user_role,
        'approver_remarks' => $remarks
    ]);
    exit;
}

// -------------------------------------------------------------------------
// 7. ACTION: CANCEL REQUEST (BY CASHIER/REQUESTER)
// -------------------------------------------------------------------------
if ($action === 'cancel_request') {
    $req_id = isset($_POST['request_id']) ? trim($_POST['request_id']) : '';

    $stmt_f = $pdo->prepare("SELECT * FROM tbl_discount_requests WHERE request_id = ? AND supplier_id = ?");
    $stmt_f->execute(array($req_id, $supplier_id));
    $req = $stmt_f->fetch(PDO::FETCH_ASSOC);

    if ($req && in_array($req['status'], ['PENDING', 'ESCALATED'])) {
        $stmt_up = $pdo->prepare("UPDATE tbl_discount_requests SET status = 'CANCELLED', updated_at = NOW() WHERE request_id = ? AND supplier_id = ?");
        $stmt_up->execute(array($req_id, $supplier_id));
    }

    echo json_encode([
        'status' => 'success',
        'message' => 'Discount request cancelled.',
        'request_id' => $req_id
    ]);
    exit;
}

// Default fallback
echo json_encode(['status' => 'error', 'message' => 'Invalid action requested.']);
exit;
