<?php
function get_ext($pdo,$fname)
{

	$up_filename=$_FILES[$fname]["name"];
	$file_basename = substr($up_filename, 0, strripos($up_filename, '.')); // strip extention
	$file_ext = substr($up_filename, strripos($up_filename, '.')); // strip name
	return $file_ext;
}

function ext_check($pdo,$allowed_ext,$my_ext) 
{

	$arr1 = array();
	$arr1 = explode("|",$allowed_ext);	
	$count_arr1 = count(explode("|",$allowed_ext));	

	for($i=0;$i<$count_arr1;$i++)
	{
		$arr1[$i] = '.'.$arr1[$i];
	}
	

	$str = '';
	$stat = 0;
	for($i=0;$i<$count_arr1;$i++)
	{
		if($my_ext == $arr1[$i])
		{
			$stat = 1;
			break;
		}
	}

	if($stat == 1)
		return true; // file extension match
	else
		return false; // file extension not match
}


function get_ai_id($pdo,$tbl_name) 
{
	$statement = $pdo->prepare("SHOW TABLE STATUS LIKE '$tbl_name'");
	$statement->execute();
	$result = $statement->fetchAll(PDO::FETCH_ASSOC);
	foreach($result as $row)
	{
		$next_id = $row['Auto_increment'];
	}
	return $next_id;
}

function send_email_via_smtp($to, $subject, $message_html, $host, $port, $user, $pass) {
    $errorNumber = "";
    $errorString = "";
    $socket = @fsockopen(($port == 465 ? "ssl://" : "") . $host, $port, $errorNumber, $errorString, 15);
    if (!$socket) {
        return false;
    }
    
    $getResponse = function($socket) {
        $response = "";
        while (($line = fgets($socket, 515)) !== false) {
            $response .= $line;
            if (substr($line, 3, 1) == " ") {
                break;
            }
        }
        return $response;
    };
    
    $getResponse($socket);
    fwrite($socket, "EHLO " . $_SERVER['SERVER_NAME'] . "\r\n");
    $getResponse($socket);
    
    if ($port == 587) {
        fwrite($socket, "STARTTLS\r\n");
        $getResponse($socket);
        if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            fclose($socket);
            return false;
        }
        fwrite($socket, "EHLO " . $_SERVER['SERVER_NAME'] . "\r\n");
        $getResponse($socket);
    }
    
    fwrite($socket, "AUTH LOGIN\r\n");
    $getResponse($socket);
    fwrite($socket, base64_encode($user) . "\r\n");
    $getResponse($socket);
    fwrite($socket, base64_encode($pass) . "\r\n");
    $getResponse($socket);
    
    fwrite($socket, "MAIL FROM: <$user>\r\n");
    $getResponse($socket);
    fwrite($socket, "RCPT TO: <$to>\r\n");
    $getResponse($socket);
    
    fwrite($socket, "DATA\r\n");
    $getResponse($socket);
    
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "To: <$to>\r\n";
    $headers .= "From: eConstruction Supply <$user>\r\n";
    $headers .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
    
    fwrite($socket, $headers . "\r\n" . $message_html . "\r\n.\r\n");
    $getResponse($socket);
    
    fwrite($socket, "QUIT\r\n");
    fclose($socket);
    return true;
}

function send_system_email($to, $subject, $message_html) {
    $smtp_host = getenv('SMTP_HOST') ?: (isset($_ENV['SMTP_HOST']) ? $_ENV['SMTP_HOST'] : '');
    $smtp_port = getenv('SMTP_PORT') ?: (isset($_ENV['SMTP_PORT']) ? $_ENV['SMTP_PORT'] : '');
    $smtp_user = getenv('SMTP_USER') ?: (isset($_ENV['SMTP_USER']) ? $_ENV['SMTP_USER'] : '');
    $smtp_pass = getenv('SMTP_PASS') ?: (isset($_ENV['SMTP_PASS']) ? $_ENV['SMTP_PASS'] : '');

    // Log email to local file for backup and offline verification/testing
    $log_dir = dirname(__DIR__, 2) . '/assets/uploads';
    if (is_writable($log_dir)) {
        $log_file = $log_dir . '/emails.log';
        $log_entry = "[" . date('Y-m-d H:i:s') . "] TO: $to | SUBJECT: $subject\n" . strip_tags($message_html) . "\n-------------------------------------\n";
        @file_put_contents($log_file, $log_entry, FILE_APPEND);
    }

    if (!empty($smtp_host) && !empty($smtp_user) && !empty($smtp_pass)) {
        return send_email_via_smtp($to, $subject, $message_html, $smtp_host, (int)$smtp_port, $smtp_user, $smtp_pass);
    } else {
        // Fallback to PHP mail()
        $headers = "From: noreply@" . $_SERVER['SERVER_NAME'] . "\r\n" .
                   "Reply-To: noreply@" . $_SERVER['SERVER_NAME'] . "\r\n" .
                   "MIME-Version: 1.0\r\n" . 
                   "Content-Type: text/html; charset=UTF-8\r\n";
        return @mail($to, $subject, $message_html, $headers);
    }
}

// -------------------------------------------------------------
// Multi-Tenant Authentication & POS User Quota Helper Functions
// -------------------------------------------------------------

function is_supplier_logged_in() {
    return isset($_SESSION['supplier_user']) && !empty($_SESSION['supplier_user']['id']);
}

function current_supplier_user() {
    return is_supplier_logged_in() ? $_SESSION['supplier_user'] : null;
}

function current_supplier_id() {
    return is_supplier_logged_in() ? (int)$_SESSION['supplier_user']['supplier_id'] : 0;
}

function is_supplier_admin() {
    $u = current_supplier_user();
    if (!$u || empty($u['role'])) return false;
    $r = strtoupper(trim($u['role']));
    return in_array($r, ['ADMIN', 'SUPPLIER_ADMIN', 'SUPERADMIN']);
}

function is_pos_user() {
    $u = current_supplier_user();
    if (!$u || empty($u['role'])) return false;
    $r = strtoupper(trim($u['role']));
    return in_array($r, ['USER', 'POS_USER', 'CASHIER']);
}

function verify_supplier_password($input_password, $stored_hash) {
    if (empty($input_password) || empty($stored_hash)) return false;
    // Check modern password_hash (bcrypt/argon)
    if (password_verify($input_password, $stored_hash)) {
        return true;
    }
    // Fallback to legacy MD5 for existing seeded accounts
    if (md5($input_password) === $stored_hash) {
        return true;
    }
    return false;
}

function hash_supplier_password($password) {
    return password_hash($password, PASSWORD_BCRYPT);
}

function get_tenant_pos_user_stats($pdo, $supplier_id) {
    $supplier_id = (int)$supplier_id;
    
    // Fetch supplier plan and max_pos_users
    $stmt_supp = $pdo->prepare("SELECT supplier_id, supplier_name, supplier_slug, supplier_plan, max_pos_users, supplier_status FROM tbl_supplier WHERE supplier_id = ?");
    $stmt_supp->execute(array($supplier_id));
    $supp = $stmt_supp->fetch(PDO::FETCH_ASSOC);
    
    if (!$supp) {
        return [
            'exists' => false,
            'plan_name' => 'Starter',
            'max_pos_users' => 3,
            'current_pos_users' => 0,
            'remaining_slots' => 0,
            'is_limit_reached' => true,
            'supplier_status' => 'Unknown'
        ];
    }
    
    $plan_name = !empty($supp['supplier_plan']) ? $supp['supplier_plan'] : 'Starter';
    $max_users = isset($supp['max_pos_users']) && (int)$supp['max_pos_users'] > 0 ? (int)$supp['max_pos_users'] : 3;
    
    // Count active and total POS users for this specific tenant (excluding Admin)
    $stmt_cnt = $pdo->prepare("SELECT COUNT(*) as total_pos_users FROM tbl_supplier_user WHERE supplier_id = ? AND UPPER(role) IN ('USER', 'POS_USER', 'CASHIER')");
    $stmt_cnt->execute(array($supplier_id));
    $row_cnt = $stmt_cnt->fetch(PDO::FETCH_ASSOC);
    $current_pos_users = (int)$row_cnt['total_pos_users'];
    
    $remaining_slots = max(0, $max_users - $current_pos_users);
    $is_limit_reached = ($current_pos_users >= $max_users);
    
    return [
        'exists' => true,
        'supplier_id' => $supplier_id,
        'supplier_name' => $supp['supplier_name'],
        'supplier_slug' => $supp['supplier_slug'],
        'plan_name' => $plan_name,
        'max_pos_users' => $max_users,
        'current_pos_users' => $current_pos_users,
        'remaining_slots' => $remaining_slots,
        'is_limit_reached' => $is_limit_reached,
        'supplier_status' => $supp['supplier_status']
    ];
}

function can_tenant_create_pos_user($pdo, $supplier_id) {
    $stats = get_tenant_pos_user_stats($pdo, $supplier_id);
    if (!$stats['exists'] || $stats['supplier_status'] !== 'Active') {
        return false;
    }
    return !$stats['is_limit_reached'];
}