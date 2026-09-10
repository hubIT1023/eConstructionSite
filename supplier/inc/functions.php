<?php
if (!function_exists('get_ext')) {
    function get_ext($pdo,$fname)
    {
        $up_filename=$_FILES[$fname]["name"];
        $file_basename = substr($up_filename, 0, strripos($up_filename, '.')); // strip extention
        $file_ext = substr($up_filename, strripos($up_filename, '.')); // strip name
        return $file_ext;
    }
}

if (!function_exists('ext_check')) {
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
}

if (!function_exists('get_ai_id')) {
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
}

if (!function_exists('send_email_via_smtp')) {
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
}

if (!function_exists('send_system_email')) {
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
}

// -------------------------------------------------------------
// Multi-Tenant Authentication & POS User Quota Helper Functions
// -------------------------------------------------------------

if (!function_exists('is_supplier_logged_in')) {
    function is_supplier_logged_in() {
        return isset($_SESSION['supplier_user']) && !empty($_SESSION['supplier_user']['id']);
    }
}

if (!function_exists('current_supplier_user')) {
    function current_supplier_user() {
        return is_supplier_logged_in() ? $_SESSION['supplier_user'] : null;
    }
}

if (!function_exists('current_supplier_id')) {
    function current_supplier_id() {
        return is_supplier_logged_in() ? (int)$_SESSION['supplier_user']['supplier_id'] : 0;
    }
}

if (!function_exists('normalize_supplier_role')) {
    function normalize_supplier_role($role_raw) {
        $r = strtoupper(trim((string)$role_raw));
        if (in_array($r, ['ADMIN', 'SUPPLIER_ADMIN', 'SUPPLIER ADMIN', 'SUPERADMIN', 'STORE_ADMIN', 'STORE ADMIN'])) {
            return 'ADMIN';
        }
        if (in_array($r, ['MANAGER', 'STORE_MANAGER', 'STORE MANAGER'])) {
            return 'MANAGER';
        }
        if (in_array($r, ['SUPERVISOR', 'STORE_SUPERVISOR', 'STORE SUPERVISOR'])) {
            return 'SUPERVISOR';
        }
        if (in_array($r, ['OPERATOR', 'STORE_OPERATOR', 'STORE OPERATOR'])) {
            return 'OPERATOR';
        }
        if (in_array($r, ['ORDER_PROCESSING', 'ORDER PROCESSING', 'ORDER PROCESSING STAFF', 'ORDER_PROCESSING_STAFF'])) {
            return 'ORDER_PROCESSING';
        }
        if (in_array($r, ['ENCODER', 'PO_ENCODER', 'POS_ENCODER', 'POS ENCODER', 'PURCHASE ORDER ENCODER', 'PURCHASE_ORDER_ENCODER'])) {
            return 'ENCODER';
        }
        if (in_array($r, ['CASHIER', 'USER', 'POS_USER', 'POS USER'])) {
            return 'CASHIER';
        }
        return 'CASHIER';
    }
}

if (!function_exists('get_role_display_name')) {
    function get_role_display_name($role_raw) {
        $norm = normalize_supplier_role($role_raw);
        switch ($norm) {
            case 'ADMIN': return 'Supplier Admin';
            case 'MANAGER': return 'Manager';
            case 'SUPERVISOR': return 'Supervisor';
            case 'CASHIER': return 'Cashier';
            case 'OPERATOR': return 'Operator';
            case 'ORDER_PROCESSING': return 'Order Processing Staff';
            case 'ENCODER': return 'Encoder';
            default: return ucfirst(strtolower($norm));
        }
    }
}

if (!function_exists('is_admin_or_manager_role')) {
    function is_admin_or_manager_role($role_raw) {
        $norm = normalize_supplier_role($role_raw);
        return in_array($norm, ['ADMIN', 'MANAGER']);
    }
}

if (!function_exists('is_supervisor_role')) {
    function is_supervisor_role($role_raw) {
        return normalize_supplier_role($role_raw) === 'SUPERVISOR';
    }
}

if (!function_exists('is_encoder_role')) {
    function is_encoder_role($role_raw) {
        return normalize_supplier_role($role_raw) === 'ENCODER';
    }
}

if (!function_exists('is_cashier_or_operator_role')) {
    function is_cashier_or_operator_role($role_raw) {
        $norm = normalize_supplier_role($role_raw);
        return in_array($norm, ['CASHIER', 'OPERATOR', 'ORDER_PROCESSING', 'ENCODER']);
    }
}

if (!function_exists('is_order_processing_or_operator_role')) {
    function is_order_processing_or_operator_role($role_raw) {
        $norm = normalize_supplier_role($role_raw);
        return in_array($norm, ['ORDER_PROCESSING', 'OPERATOR']);
    }
}

if (!function_exists('can_register_staff')) {
    function can_register_staff($role_raw) {
        $norm = normalize_supplier_role($role_raw);
        return in_array($norm, ['ADMIN', 'MANAGER', 'SUPERVISOR']);
    }
}

if (!function_exists('get_assignable_roles')) {
    function get_assignable_roles($creator_role_raw) {
        $norm = normalize_supplier_role($creator_role_raw);
        if ($norm === 'ADMIN') {
            return ['MANAGER', 'SUPERVISOR', 'ORDER_PROCESSING', 'ENCODER', 'CASHIER', 'OPERATOR'];
        }
        if ($norm === 'MANAGER') {
            return ['SUPERVISOR', 'ORDER_PROCESSING', 'ENCODER', 'CASHIER', 'OPERATOR'];
        }
        if ($norm === 'SUPERVISOR') {
            return ['ORDER_PROCESSING', 'ENCODER', 'CASHIER', 'OPERATOR'];
        }
        return [];
    }
}

if (!function_exists('can_assign_role')) {
    function can_assign_role($creator_role_raw, $target_role_raw) {
        $assignable = get_assignable_roles($creator_role_raw);
        $norm_target = normalize_supplier_role($target_role_raw);
        return in_array($norm_target, $assignable, true);
    }
}

if (!function_exists('is_supplier_admin')) {
    function is_supplier_admin() {
        $u = current_supplier_user();
        if (!$u || empty($u['role'])) return false;
        return is_admin_or_manager_role($u['role']);
    }
}

if (!function_exists('is_supplier_approver')) {
    function is_supplier_approver() {
        $u = current_supplier_user();
        if (!$u || empty($u['role'])) return false;
        $norm = normalize_supplier_role($u['role']);
        return in_array($norm, ['ADMIN', 'MANAGER', 'SUPERVISOR']);
    }
}

if (!function_exists('get_required_approval_role')) {
    function get_required_approval_role($requester_role_raw) {
        $norm = normalize_supplier_role($requester_role_raw);
        if (in_array($norm, ['ADMIN', 'MANAGER', 'SUPERVISOR'])) {
            return 'Admin / Manager';
        }
        return 'Supervisor OR Admin / Manager';
    }
}

if (!function_exists('can_user_approve_discount')) {
    function can_user_approve_discount($requester_user_id, $requester_role_raw, $approver_user_id, $approver_role_raw, $supplier_id_match = true) {
        if (!$supplier_id_match) {
            return false;
        }
        
        $req_norm = normalize_supplier_role($requester_role_raw);
        $app_norm = normalize_supplier_role($approver_role_raw);
        $is_self = ((int)$requester_user_id === (int)$approver_user_id);
        
        // 1. Requester is Admin or Manager
        if (in_array($req_norm, ['ADMIN', 'MANAGER'])) {
            // Only Admin / Manager can approve
            if (!in_array($app_norm, ['ADMIN', 'MANAGER'])) {
                return false;
            }
            // Self-approval is ALLOWED for Admin / Manager per existing policy
            return true;
        }
        
        // 2. Requester is Supervisor
        if ($req_norm === 'SUPERVISOR') {
            // Self-approval is NOT ALLOWED
            if ($is_self) {
                return false;
            }
            // Only Admin / Manager can approve
            if (!in_array($app_norm, ['ADMIN', 'MANAGER'])) {
                return false;
            }
            return true;
        }
        
        // 3. Requester is Cashier, Operator, Order Processing, or Encoder
        // Self-approval is NOT ALLOWED
        if ($is_self) {
            return false;
        }
        // Approver can be Admin, Manager, OR Supervisor
        if (in_array($app_norm, ['ADMIN', 'MANAGER', 'SUPERVISOR'])) {
            return true;
        }
        
        return false;
    }
}

if (!function_exists('is_pos_user')) {
    function is_pos_user() {
        $u = current_supplier_user();
        if (!$u || empty($u['role'])) return false;
        $norm = normalize_supplier_role($u['role']);
        return in_array($norm, ['CASHIER', 'OPERATOR', 'ORDER_PROCESSING', 'ENCODER']);
    }
}

if (!function_exists('ensure_supplier_user_schema')) {
    function ensure_supplier_user_schema($pdo) {
        static $checked = false;
        if ($checked) return;
        try {
            // Ensure tbl_supplier SaaS and discount policy columns
            $pdo->exec("ALTER TABLE tbl_supplier ADD COLUMN IF NOT EXISTS max_pos_users INTEGER DEFAULT 3");
            $pdo->exec("ALTER TABLE tbl_supplier ADD COLUMN IF NOT EXISTS max_storage_mb INTEGER DEFAULT 2048");
            $pdo->exec("ALTER TABLE tbl_supplier ADD COLUMN IF NOT EXISTS supplier_commission NUMERIC(10,2) DEFAULT 0.00");
            $pdo->exec("ALTER TABLE tbl_supplier ADD COLUMN IF NOT EXISTS discount_enabled SMALLINT DEFAULT 1");
            $pdo->exec("ALTER TABLE tbl_supplier ADD COLUMN IF NOT EXISTS discount_normal_max NUMERIC(5,2) DEFAULT 10.00");
            $pdo->exec("ALTER TABLE tbl_supplier ADD COLUMN IF NOT EXISTS discount_special_enabled SMALLINT DEFAULT 1");
            $pdo->exec("ALTER TABLE tbl_supplier ADD COLUMN IF NOT EXISTS discount_absolute_max NUMERIC(5,2) DEFAULT 20.00");
            $pdo->exec("ALTER TABLE tbl_supplier ADD COLUMN IF NOT EXISTS discount_require_admin_approval SMALLINT DEFAULT 1");

            // Ensure tbl_payment schema supports decimal currency and longer status strings
            $pdo->exec("ALTER TABLE tbl_payment ALTER COLUMN paid_amount TYPE NUMERIC(10,2) USING paid_amount::numeric");
            $pdo->exec("ALTER TABLE tbl_payment ALTER COLUMN payment_method TYPE VARCHAR(100)");
            $pdo->exec("ALTER TABLE tbl_payment ALTER COLUMN payment_status TYPE VARCHAR(50)");
            $pdo->exec("ALTER TABLE tbl_payment ALTER COLUMN shipping_status TYPE VARCHAR(50)");

            // Ensure tbl_product pricing and inventory columns
            $pdo->exec("ALTER TABLE tbl_product ADD COLUMN IF NOT EXISTS p_new_price VARCHAR(100)");
            $pdo->exec("ALTER TABLE tbl_product ADD COLUMN IF NOT EXISTS p_new_qty INTEGER DEFAULT 0");
            $pdo->exec("ALTER TABLE tbl_product ADD COLUMN IF NOT EXISTS p_s_level INTEGER DEFAULT 10");
            $pdo->exec("ALTER TABLE tbl_product ADD COLUMN IF NOT EXISTS p_capital_price VARCHAR(100)");
            $pdo->exec("ALTER TABLE tbl_product ADD COLUMN IF NOT EXISTS p_markup VARCHAR(100) DEFAULT '20'");

            // Ensure tbl_supplier_user employee columns
            $pdo->exec("ALTER TABLE tbl_supplier_user ADD COLUMN IF NOT EXISTS first_name VARCHAR(100)");
            $pdo->exec("ALTER TABLE tbl_supplier_user ADD COLUMN IF NOT EXISTS middle_name VARCHAR(100)");
            $pdo->exec("ALTER TABLE tbl_supplier_user ADD COLUMN IF NOT EXISTS last_name VARCHAR(100)");
            $pdo->exec("ALTER TABLE tbl_supplier_user ADD COLUMN IF NOT EXISTS phone VARCHAR(50)");
            $pdo->exec("ALTER TABLE tbl_supplier_user ADD COLUMN IF NOT EXISTS pos_access SMALLINT DEFAULT 1");
            $pdo->exec("ALTER TABLE tbl_supplier_user ADD COLUMN IF NOT EXISTS employee_id VARCHAR(50)");
            $pdo->exec("ALTER TABLE tbl_supplier_user ADD COLUMN IF NOT EXISTS date_started DATE DEFAULT CURRENT_DATE");
            $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_supplier_user_employee_id ON tbl_supplier_user (employee_id) WHERE employee_id IS NOT NULL AND employee_id != ''");
            
            // Create persistent tenant sequence table
            $pdo->exec("CREATE TABLE IF NOT EXISTS tbl_supplier_employee_sequence (
                supplier_id INTEGER PRIMARY KEY,
                last_seq INTEGER NOT NULL DEFAULT 0,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )");

            // Create and seed tbl_brgy table if not exists
            $pdo->exec("CREATE TABLE IF NOT EXISTS tbl_brgy (
                brgy_id SERIAL PRIMARY KEY,
                brgy_name VARCHAR(255) NOT NULL
            )");

            // Create tbl_returns and tbl_return_items if not exists
            $pdo->exec("CREATE TABLE IF NOT EXISTS tbl_returns (
                return_id SERIAL PRIMARY KEY,
                return_reference VARCHAR(100),
                payment_id VARCHAR(100),
                order_item_id INTEGER,
                order_id INTEGER,
                supplier_id INTEGER,
                customer_id INTEGER,
                customer_name VARCHAR(255),
                customer_email VARCHAR(255),
                customer_phone VARCHAR(50),
                return_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                refund_method VARCHAR(50),
                refund_amount NUMERIC(10,2) DEFAULT 0.00,
                status VARCHAR(50) DEFAULT 'Pending',
                processed_by VARCHAR(100),
                notes TEXT
            )");
            $pdo->exec("ALTER TABLE tbl_returns ADD COLUMN IF NOT EXISTS return_reference VARCHAR(100)");
            $pdo->exec("ALTER TABLE tbl_returns ADD COLUMN IF NOT EXISTS payment_id VARCHAR(100)");
            $pdo->exec("ALTER TABLE tbl_returns ADD COLUMN IF NOT EXISTS order_id INTEGER");
            $pdo->exec("ALTER TABLE tbl_returns ADD COLUMN IF NOT EXISTS order_item_id INTEGER");
            $pdo->exec("ALTER TABLE tbl_returns ADD COLUMN IF NOT EXISTS supplier_id INTEGER");
            $pdo->exec("ALTER TABLE tbl_returns ADD COLUMN IF NOT EXISTS customer_id INTEGER");
            $pdo->exec("ALTER TABLE tbl_returns ADD COLUMN IF NOT EXISTS customer_name VARCHAR(255)");
            $pdo->exec("ALTER TABLE tbl_returns ADD COLUMN IF NOT EXISTS customer_email VARCHAR(255)");
            $pdo->exec("ALTER TABLE tbl_returns ADD COLUMN IF NOT EXISTS customer_phone VARCHAR(50)");
            $pdo->exec("ALTER TABLE tbl_returns ADD COLUMN IF NOT EXISTS return_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
            $pdo->exec("ALTER TABLE tbl_returns ADD COLUMN IF NOT EXISTS refund_method VARCHAR(50)");
            $pdo->exec("ALTER TABLE tbl_returns ADD COLUMN IF NOT EXISTS refund_amount NUMERIC(10,2) DEFAULT 0.00");
            $pdo->exec("ALTER TABLE tbl_returns ADD COLUMN IF NOT EXISTS status VARCHAR(50) DEFAULT 'PENDING_APPROVAL'");
            $pdo->exec("ALTER TABLE tbl_returns ADD COLUMN IF NOT EXISTS requested_by_id INTEGER");
            $pdo->exec("ALTER TABLE tbl_returns ADD COLUMN IF NOT EXISTS requested_by_name VARCHAR(255)");
            $pdo->exec("ALTER TABLE tbl_returns ADD COLUMN IF NOT EXISTS requested_by_role VARCHAR(50)");
            $pdo->exec("ALTER TABLE tbl_returns ADD COLUMN IF NOT EXISTS approver_id INTEGER");
            $pdo->exec("ALTER TABLE tbl_returns ADD COLUMN IF NOT EXISTS approver_name VARCHAR(255)");
            $pdo->exec("ALTER TABLE tbl_returns ADD COLUMN IF NOT EXISTS approver_role VARCHAR(50)");
            $pdo->exec("ALTER TABLE tbl_returns ADD COLUMN IF NOT EXISTS approver_remarks TEXT");
            $pdo->exec("ALTER TABLE tbl_returns ADD COLUMN IF NOT EXISTS rejection_reason TEXT");
            $pdo->exec("ALTER TABLE tbl_returns ADD COLUMN IF NOT EXISTS approved_at TIMESTAMP");
            $pdo->exec("ALTER TABLE tbl_returns ADD COLUMN IF NOT EXISTS rejected_at TIMESTAMP");
            $pdo->exec("ALTER TABLE tbl_returns ADD COLUMN IF NOT EXISTS completed_at TIMESTAMP");
            $pdo->exec("ALTER TABLE tbl_returns ADD COLUMN IF NOT EXISTS processed_by VARCHAR(100)");
            $pdo->exec("ALTER TABLE tbl_returns ADD COLUMN IF NOT EXISTS notes TEXT");
            $pdo->exec("ALTER TABLE tbl_returns ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
            $pdo->exec("ALTER TABLE tbl_returns ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");

            $pdo->exec("CREATE TABLE IF NOT EXISTS tbl_return_items (
                return_item_id SERIAL PRIMARY KEY,
                item_id INTEGER,
                return_id INTEGER,
                return_reference VARCHAR(100),
                order_item_id INTEGER,
                product_id INTEGER,
                p_id INTEGER,
                product_name VARCHAR(255),
                sku VARCHAR(100),
                size VARCHAR(100),
                color VARCHAR(100),
                item_type VARCHAR(100),
                special_order_reference VARCHAR(100),
                product_details TEXT,
                quantity_returned INTEGER DEFAULT 1,
                quantity INTEGER DEFAULT 1,
                unit_price NUMERIC(10,2) DEFAULT 0.00,
                refund_amount NUMERIC(10,2) DEFAULT 0.00,
                return_reason TEXT,
                reason TEXT,
                condition VARCHAR(100),
                restock_status VARCHAR(100),
                notes TEXT
            )");
            $pdo->exec("ALTER TABLE tbl_return_items ADD COLUMN IF NOT EXISTS return_item_id SERIAL");
            $pdo->exec("ALTER TABLE tbl_return_items ADD COLUMN IF NOT EXISTS item_id INTEGER");
            $pdo->exec("ALTER TABLE tbl_return_items ADD COLUMN IF NOT EXISTS return_reference VARCHAR(100)");
            $pdo->exec("ALTER TABLE tbl_return_items ADD COLUMN IF NOT EXISTS order_item_id INTEGER");
            $pdo->exec("ALTER TABLE tbl_return_items ADD COLUMN IF NOT EXISTS product_id INTEGER");
            $pdo->exec("ALTER TABLE tbl_return_items ADD COLUMN IF NOT EXISTS product_name VARCHAR(255)");
            $pdo->exec("ALTER TABLE tbl_return_items ADD COLUMN IF NOT EXISTS sku VARCHAR(100)");
            $pdo->exec("ALTER TABLE tbl_return_items ADD COLUMN IF NOT EXISTS size VARCHAR(100)");
            $pdo->exec("ALTER TABLE tbl_return_items ADD COLUMN IF NOT EXISTS color VARCHAR(100)");
            $pdo->exec("ALTER TABLE tbl_return_items ADD COLUMN IF NOT EXISTS item_type VARCHAR(100)");
            $pdo->exec("ALTER TABLE tbl_return_items ADD COLUMN IF NOT EXISTS special_order_reference VARCHAR(100)");
            $pdo->exec("ALTER TABLE tbl_return_items ADD COLUMN IF NOT EXISTS product_details TEXT");
            $pdo->exec("ALTER TABLE tbl_return_items ADD COLUMN IF NOT EXISTS quantity_returned INTEGER DEFAULT 1");
            $pdo->exec("ALTER TABLE tbl_return_items ADD COLUMN IF NOT EXISTS unit_price NUMERIC(10,2) DEFAULT 0.00");
            $pdo->exec("ALTER TABLE tbl_return_items ADD COLUMN IF NOT EXISTS refund_amount NUMERIC(10,2) DEFAULT 0.00");
            $pdo->exec("ALTER TABLE tbl_return_items ADD COLUMN IF NOT EXISTS return_reason TEXT");
            $pdo->exec("ALTER TABLE tbl_return_items ADD COLUMN IF NOT EXISTS condition VARCHAR(100)");
            $pdo->exec("ALTER TABLE tbl_return_items ADD COLUMN IF NOT EXISTS restock_status VARCHAR(100)");
            $pdo->exec("ALTER TABLE tbl_return_items ADD COLUMN IF NOT EXISTS notes TEXT");

            // Create tbl_quote and tbl_quote_item if not exists
            $pdo->exec("CREATE TABLE IF NOT EXISTS tbl_quote (
                quote_id SERIAL PRIMARY KEY,
                supplier_id INTEGER,
                cust_id INTEGER,
                quote_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                status VARCHAR(50) DEFAULT 'Pending'
            )");

            $pdo->exec("CREATE TABLE IF NOT EXISTS tbl_quote_item (
                item_id SERIAL PRIMARY KEY,
                quote_id INTEGER,
                p_id INTEGER,
                quantity INTEGER DEFAULT 1
            )");

            // Create tbl_discount_requests table if not exists
            $pdo->exec("CREATE TABLE IF NOT EXISTS tbl_discount_requests (
                id SERIAL PRIMARY KEY,
                request_id VARCHAR(100) UNIQUE NOT NULL,
                supplier_id INTEGER NOT NULL,
                cashier_id INTEGER,
                cashier_name VARCHAR(255),
                requester_role VARCHAR(50) DEFAULT 'CASHIER',
                required_approval_role VARCHAR(50) DEFAULT 'SUPPLIER_ADMIN',
                product_id INTEGER,
                product_name VARCHAR(255),
                sku VARCHAR(100),
                item_type VARCHAR(50) DEFAULT 'STANDARD',
                special_order_reference VARCHAR(100),
                product_details TEXT,
                quantity INTEGER DEFAULT 1,
                original_unit_price NUMERIC(10,2) DEFAULT 0.00,
                original_item_value NUMERIC(10,2) DEFAULT 0.00,
                requested_discount_percent NUMERIC(5,2) DEFAULT 0.00,
                normal_max_discount_percent NUMERIC(5,2) DEFAULT 10.00,
                absolute_max_discount_percent NUMERIC(5,2) DEFAULT 20.00,
                approved_discount_percent NUMERIC(5,2) DEFAULT 0.00,
                discount_amount NUMERIC(10,2) DEFAULT 0.00,
                final_item_value NUMERIC(10,2) DEFAULT 0.00,
                approval_level VARCHAR(50) DEFAULT 'SUPPLIER_ADMIN',
                status VARCHAR(50) DEFAULT 'PENDING',
                cashier_remarks TEXT,
                approver_id INTEGER,
                approver_name VARCHAR(255),
                approver_role VARCHAR(50),
                approver_remarks TEXT,
                payment_id VARCHAR(100),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_discount_requests_supp_status ON tbl_discount_requests (supplier_id, status)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_discount_requests_req_id ON tbl_discount_requests (request_id)");
            
            // Ensure tbl_order has necessary columns for POS and Special Orders
            $pdo->exec("ALTER TABLE tbl_order ADD COLUMN IF NOT EXISTS supplier_id INTEGER DEFAULT 1");
            $pdo->exec("ALTER TABLE tbl_order ADD COLUMN IF NOT EXISTS item_type VARCHAR(50) DEFAULT 'STANDARD'");
            $pdo->exec("ALTER TABLE tbl_order ADD COLUMN IF NOT EXISTS special_order_reference VARCHAR(100)");
            $pdo->exec("ALTER TABLE tbl_order ADD COLUMN IF NOT EXISTS product_details TEXT");
            
            $chk_brgy = $pdo->query("SELECT COUNT(*) FROM tbl_brgy");
            if ($chk_brgy && (int)$chk_brgy->fetchColumn() === 0) {
                $brgys = [
                    'Agusipan, Santa Barbara', 'Agutayan, Santa Barbara', 'Bagumbayan, Santa Barbara',
                    'Balabag, Santa Barbara', 'Balibagan Este, Santa Barbara', 'Balibagan Oeste, Santa Barbara',
                    'Ban-ag, Santa Barbara', 'Bantay, Santa Barbara', 'Barangay Zone I (Poblacion), Santa Barbara',
                    'Barangay Zone II (Poblacion), Santa Barbara', 'Barangay Zone III (Poblacion), Santa Barbara',
                    'Barangay Zone IV (Poblacion), Santa Barbara', 'Barangay Zone V (Poblacion), Santa Barbara',
                    'Barangay Zone VI (Poblacion), Santa Barbara', 'Barasan Este, Santa Barbara', 'Barasan Oeste, Santa Barbara',
                    'Binangkilan, Santa Barbara', 'Bitaog-Taytay, Santa Barbara', 'Bolong Este, Santa Barbara',
                    'Bolong Oeste, Santa Barbara', 'Buayahon, Santa Barbara', 'Buyo, Santa Barbara',
                    'Cabugao Norte, Santa Barbara', 'Cabugao Sur, Santa Barbara', 'Cadagmayan Norte, Santa Barbara',
                    'Cadagmayan Sur, Santa Barbara', 'Cafe, Santa Barbara', 'Calaboa Este, Santa Barbara',
                    'Calaboa Oeste, Santa Barbara', 'Camambugan, Santa Barbara', 'Canipayan, Santa Barbara',
                    'Conaynay, Santa Barbara', 'Daga, Santa Barbara', 'Dalid, Santa Barbara',
                    'Duyanduyan, Santa Barbara', 'Gen. Martin T. Delgado, Santa Barbara', 'Guno, Santa Barbara',
                    'Inangayan, Santa Barbara', 'Jibao-an, Santa Barbara', 'Lacadon, Santa Barbara',
                    'Lanag, Santa Barbara', 'Lupa, Santa Barbara', 'Magancina, Santa Barbara',
                    'Malawog, Santa Barbara', 'Mambuyo, Santa Barbara', 'Manhayang, Santa Barbara',
                    'Miraga-Guibuangan, Santa Barbara', 'Nasugban, Santa Barbara', 'Omambog, Santa Barbara',
                    'Pal-Agon, Santa Barbara', 'Poblacion, Iloilo City', 'Jaro, Iloilo City', 'Mandurriao, Iloilo City',
                    'Molo, Iloilo City', 'Lapuz, Iloilo City', 'La Paz, Iloilo City', 'Villa Arevalo, Iloilo City'
                ];
                $ins_brgy = $pdo->prepare("INSERT INTO tbl_brgy (brgy_name) VALUES (?)");
                foreach ($brgys as $bname) {
                    $ins_brgy->execute([$bname]);
                }
            }
        } catch (Exception $e) {
            // Ignore if schema already updated
        }
        $checked = true;
    }
}

if (!function_exists('has_pos_access')) {
    function has_pos_access($user_array = null) {
        if ($user_array === null) {
            $user_array = current_supplier_user();
        }
        if (!$user_array || empty($user_array['role'])) return false;
        $norm = normalize_supplier_role($user_array['role']);
        if (in_array($norm, ['ADMIN', 'MANAGER'])) {
            return true;
        }
        return isset($user_array['pos_access']) ? ((int)$user_array['pos_access'] === 1) : true;
    }
}

if (!function_exists('get_alphabet_rotation_letter')) {
    function get_alphabet_rotation_letter($sequence_num) {
        $seq = max(1, (int)$sequence_num);
        $group_idx = (int)floor(($seq - 1) / 10);
        if ($group_idx < 26) {
            return chr(65 + $group_idx);
        }
        // Safe extension for >260 employees (AA, AB, ... ZZ, AAA, etc.)
        $letter = '';
        $temp = $group_idx;
        while ($temp >= 0) {
            $letter = chr(65 + ($temp % 26)) . $letter;
            $temp = (int)floor($temp / 26) - 1;
        }
        return $letter ?: 'A';
    }
}

if (!function_exists('generate_supplier_employee_id')) {
    function generate_supplier_employee_id($pdo, $supplier_id, $start_date = null) {
        $supplier_id = (int)$supplier_id;
        ensure_supplier_user_schema($pdo);
        
        // Format start date as YYYYMMDD
        if (empty($start_date)) {
            $date_str = date('Ymd');
        } else {
            $ts = strtotime($start_date);
            $date_str = $ts ? date('Ymd', $ts) : date('Ymd');
        }
        
        // Atomic & persistent sequence increment using tbl_supplier_employee_sequence
        // Ensure record exists
        $pdo->prepare("INSERT INTO tbl_supplier_employee_sequence (supplier_id, last_seq) VALUES (?, 0) ON CONFLICT (supplier_id) DO NOTHING")
            ->execute(array($supplier_id));
        
        // Lock row for transaction safety
        $stmt_seq = $pdo->prepare("SELECT last_seq FROM tbl_supplier_employee_sequence WHERE supplier_id = ? FOR UPDATE");
        $stmt_seq->execute(array($supplier_id));
        $current_seq = (int)$stmt_seq->fetchColumn();
        
        // If uninitialized (0), sync with existing count
        if ($current_seq === 0) {
            $stmt_cnt = $pdo->prepare("SELECT COUNT(*) FROM tbl_supplier_user WHERE supplier_id = ?");
            $stmt_cnt->execute(array($supplier_id));
            $current_seq = (int)$stmt_cnt->fetchColumn();
        }
        
        $next_seq = $current_seq + 1;
        $letter = get_alphabet_rotation_letter($next_seq);
        $base_emp_id = "SICS-{$date_str}-{$letter}";
        $candidate_id = $base_emp_id;
        
        // Collision safety check across tbl_supplier_user
        $stmt_chk = $pdo->prepare("SELECT id FROM tbl_supplier_user WHERE UPPER(employee_id) = UPPER(?)");
        $stmt_chk->execute(array($candidate_id));
        $suffix = 1;
        while ($stmt_chk->rowCount() > 0) {
            $suffix++;
            $candidate_id = "{$base_emp_id}-" . str_pad($suffix, 2, '0', STR_PAD_LEFT);
            $stmt_chk->execute(array($candidate_id));
        }
        
        // Persist updated sequence
        $stmt_up_seq = $pdo->prepare("UPDATE tbl_supplier_employee_sequence SET last_seq = ?, updated_at = CURRENT_TIMESTAMP WHERE supplier_id = ?");
        $stmt_up_seq->execute(array($next_seq, $supplier_id));
        
        return $candidate_id;
    }
}

if (!function_exists('backfill_supplier_employee_ids')) {
    function backfill_supplier_employee_ids($pdo) {
        ensure_supplier_user_schema($pdo);
        try {
            // Seed sequence table for all active suppliers
            $stmt_supps = $pdo->query("SELECT supplier_id FROM tbl_supplier");
            $supp_ids = $stmt_supps->fetchAll(PDO::FETCH_COLUMN);
            foreach ($supp_ids as $sid) {
                $stmt_c = $pdo->prepare("SELECT COUNT(*) FROM tbl_supplier_user WHERE supplier_id = ?");
                $stmt_c->execute(array($sid));
                $tot = (int)$stmt_c->fetchColumn();
                $pdo->prepare("INSERT INTO tbl_supplier_employee_sequence (supplier_id, last_seq) VALUES (?, ?) ON CONFLICT (supplier_id) DO NOTHING")
                    ->execute(array($sid, $tot));
            }
            
            // Backfill missing fields for existing users
            $stmt = $pdo->query("SELECT id, supplier_id, full_name, email, role, date_started, employee_id, first_name, last_name FROM tbl_supplier_user ORDER BY id ASC");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $r) {
                $start = !empty($r['date_started']) ? substr(strval($r['date_started']), 0, 10) : date('Y-m-d');
                $emp_id = !empty($r['employee_id']) ? $r['employee_id'] : generate_supplier_employee_id($pdo, $r['supplier_id'], $start);
                
                // Parse full_name into first_name and last_name if empty
                $f_name = !empty($r['first_name']) ? $r['first_name'] : '';
                $l_name = !empty($r['last_name']) ? $r['last_name'] : '';
                if (empty($f_name) && !empty($r['full_name'])) {
                    $parts = explode(' ', trim($r['full_name']));
                    if (count($parts) > 1) {
                        $l_name = array_pop($parts);
                        $f_name = implode(' ', $parts);
                    } else {
                        $f_name = $r['full_name'];
                        $l_name = '';
                    }
                }
                
                $up = $pdo->prepare("UPDATE tbl_supplier_user SET 
                    employee_id = ?, 
                    date_started = COALESCE(date_started, CURRENT_DATE),
                    first_name = COALESCE(NULLIF(first_name, ''), ?),
                    last_name = COALESCE(NULLIF(last_name, ''), ?),
                    pos_access = COALESCE(pos_access, 1)
                    WHERE id = ?");
                $up->execute(array($emp_id, $f_name, $l_name, $r['id']));
            }
        } catch (Exception $e) {
            // Ignore error
        }
    }
}

if (!function_exists('verify_supplier_password')) {
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
}

if (!function_exists('hash_supplier_password')) {
    function hash_supplier_password($password) {
        return password_hash($password, PASSWORD_BCRYPT);
    }
}

if (!function_exists('get_tenant_pos_user_stats')) {
    function get_tenant_pos_user_stats($pdo, $supplier_id) {
        $supplier_id = (int)$supplier_id;
        ensure_supplier_user_schema($pdo);
        
        $supp = null;
        try {
            // Fetch supplier plan and max_pos_users
            $stmt_supp = $pdo->prepare("SELECT supplier_id, supplier_name, supplier_slug, supplier_plan, max_pos_users, supplier_status FROM tbl_supplier WHERE supplier_id = ?");
            $stmt_supp->execute(array($supplier_id));
            $supp = $stmt_supp->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            try {
                $stmt_supp = $pdo->prepare("SELECT supplier_id, supplier_name, supplier_slug, supplier_plan, supplier_status FROM tbl_supplier WHERE supplier_id = ?");
                $stmt_supp->execute(array($supplier_id));
                $supp = $stmt_supp->fetch(PDO::FETCH_ASSOC);
            } catch (Exception $e2) {
                $supp = null;
            }
        }
        
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
        $stmt_cnt = $pdo->prepare("SELECT COUNT(*) as total_pos_users FROM tbl_supplier_user WHERE supplier_id = ? AND UPPER(role) NOT IN ('ADMIN', 'SUPPLIER_ADMIN', 'SUPERADMIN')");
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
}

if (!function_exists('can_tenant_create_pos_user')) {
    function can_tenant_create_pos_user($pdo, $supplier_id) {
        $stats = get_tenant_pos_user_stats($pdo, $supplier_id);
        if (!$stats['exists'] || $stats['supplier_status'] !== 'Active') {
            return false;
        }
        return !$stats['is_limit_reached'];
    }
}

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
            elseif (preg_match('/\b([0-9]+\s+[0-9]+\/[0-9]+|[0-9]+\/[0-9]+|[0-9]+(?:\.[0-9]+)?)\s*(?:mm|cm|inch|in|\"|#\d+)?\s*$/i', $name, $matches) && strlen(trim($matches[0])) > 0) {
                $size = trim($matches[0]);
                $name = trim(substr($name, 0, -strlen($matches[0])));
            }
        } else {
            // If size was already set from parentheses (e.g. 4x8), extract any thickness or size prefix from name (e.g. 1/4, 1/2, 3/4, 4.5mm, 6.0mm)
            if (empty($thickness)) {
                if (preg_match('/\b([0-9]+\s+[0-9]+\/[0-9]+|[0-9]+\/[0-9]+|[0-9]+(?:\.[0-9]+)?\s*mm|[0-9]+(?:\.[0-9]+)?\s*\"|[0-9]+(?:\.[0-9]+)?\s*(?:inch|in))\b/i', $name, $matches)) {
                    $thickness = trim($matches[1]);
                    $name = trim(preg_replace('/\b' . preg_quote($matches[0], '/') . '\b/i', '', $name));
                }
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
        if (!empty($thickness)) $specs_parts[] = $thickness;
        if (!empty($diameter)) $specs_parts[] = $diameter;
        if (!empty($size)) $specs_parts[] = $size;
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

if (!function_exists('get_master_product_base_name')) {
    function get_master_product_base_name($prod_name, $ecat_id = 0) {
        $raw = trim($prod_name);
        if (preg_match('/marine\s*plywood/i', $raw) || (preg_match('/^Plywood\b/i', $raw) && preg_match('/marine/i', $raw))) {
            return 'Marine Plywood';
        }
        if (preg_match('/ordinary\s*plywood/i', $raw)) {
            return 'Ordinary Plywood';
        }
        if (preg_match('/phenolic\s*board/i', $raw)) {
            return 'Phenolic Board';
        }
        if (preg_match('/hardiflex/i', $raw)) {
            return 'Hardiflex Board';
        }
        if (preg_match('/flat\s*latex/i', $raw)) {
            return 'Flat Latex Paint White';
        }
        if (preg_match('/portland\s*cement/i', $raw)) {
            return 'Portland Cement';
        }
        if (preg_match('/deformed/i', $raw)) {
            return 'Deformed Steel Rebars';
        }
        if (preg_match('/steel\s*matting/i', $raw)) {
            return 'Steel Matting Wire Mesh';
        }
        if (preg_match('/common\s*nail/i', $raw)) {
            return 'Common Nails';
        }
        if (preg_match('/finishing\s*nail/i', $raw)) {
            return 'Finishing Nails';
        }
        if (preg_match('/tubular/i', $raw)) {
            return 'Tubular Steel';
        }
        if (preg_match('/angle\s*bar/i', $raw)) {
            return 'Angle Bars';
        }
        if (preg_match('/c-?\s*purlin/i', $raw)) {
            return 'C-Purlins';
        }
        if (preg_match('/round\s*bar/i', $raw)) {
            return 'Round Bars';
        }
        if (preg_match('/square\s*bar/i', $raw)) {
            return 'Square Bars';
        }
        if ($ecat_id == 22 || preg_match('/gi\s*pipe/i', $raw) || preg_match('/pipe.*#40/i', $raw)) {
            return 'GI Pipes (Schedule 40)';
        }
        if (preg_match('/pvc.*conduit/i', $raw)) {
            return 'Schedule 40 PVC Conduit Pipe';
        }
        if (preg_match('/rotary\s*hammer/i', $raw)) {
            return 'Rotary Hammer Drill';
        }
        if (preg_match('/steel\s*i-?beam/i', $raw)) {
            return 'Structural Steel I-Beam';
        }
        
        $spec = parseConstructionProductDetails($raw);
        return !empty($spec['base_name']) ? $spec['base_name'] : $raw;
    }
}

if (!function_exists('get_tenant_storage_stats')) {
function get_tenant_storage_stats($pdo, $supplier_id, $base_upload_dir = null) {
    $supplier_id = (int)$supplier_id;
    if ($base_upload_dir === null) {
        $base_upload_dir = dirname(__DIR__, 2) . '/assets/uploads/';
    }
    
    // 1. Fetch supplier info
    $stmt_supp = $pdo->prepare("SELECT supplier_id, supplier_name, supplier_slug, supplier_plan, supplier_logo, supplier_banner, max_storage_mb FROM tbl_supplier WHERE supplier_id = ?");
    $stmt_supp->execute(array($supplier_id));
    $supp = $stmt_supp->fetch(PDO::FETCH_ASSOC);
    
    if (!$supp) {
        return [
            'exists' => false,
            'total_bytes' => 0,
            'total_mb' => 0,
            'max_mb' => 500,
            'used_pct' => 0,
            'file_count' => 0,
            'product_count' => 0,
            'db_records_total' => 0,
            'db_bytes' => 0,
            'file_bytes' => 0,
            'breakdown' => []
        ];
    }
    
    $plan_limits_mb = [
        'Starter' => 500,        // 500 MB
        'Professional' => 2048,  // 2 GB
        'Enterprise' => 10240    // 10 GB
    ];
    $plan = !empty($supp['supplier_plan']) ? $supp['supplier_plan'] : 'Starter';
    $max_mb = isset($supp['max_storage_mb']) && (int)$supp['max_storage_mb'] > 0 
                ? (int)$supp['max_storage_mb'] 
                : (isset($plan_limits_mb[$plan]) ? $plan_limits_mb[$plan] : 2048);
    
    $file_bytes = 0;
    $files_counted = [];
    $breakdown = [
        'product_photos_bytes' => 0,
        'product_photos_count' => 0,
        'gallery_photos_bytes' => 0,
        'gallery_photos_count' => 0,
        'branding_bytes' => 0,
        'branding_count' => 0,
        'db_products_count' => 0,
        'db_product_sizes_count' => 0,
        'db_orders_count' => 0,
        'db_payments_count' => 0,
        'db_returns_count' => 0,
        'db_return_items_count' => 0,
        'db_users_count' => 0,
        'db_quotes_count' => 0,
        'db_quote_items_count' => 0,
        'db_shipping_count' => 0,
        'db_records_total' => 0,
        'db_bytes' => 0
    ];
    
    // Helper to check and add file
    $add_file = function($filename, &$sub_bytes, &$sub_count) use ($base_upload_dir, &$file_bytes, &$files_counted) {
        if (empty($filename)) return;
        $fn = basename($filename);
        if (isset($files_counted[$fn])) return; // Avoid duplicate count
        
        $full_path = rtrim($base_upload_dir, '/\\') . DIRECTORY_SEPARATOR . $fn;
        if (file_exists($full_path) && is_file($full_path)) {
            $sz = filesize($full_path);
            $file_bytes += $sz;
            $sub_bytes += $sz;
            $sub_count++;
            $files_counted[$fn] = $sz;
        }
    };
    
    // A. Branding files (Logo & Banner)
    if (!empty($supp['supplier_logo'])) {
        $add_file($supp['supplier_logo'], $breakdown['branding_bytes'], $breakdown['branding_count']);
    }
    if (!empty($supp['supplier_banner'])) {
        $add_file($supp['supplier_banner'], $breakdown['branding_bytes'], $breakdown['branding_count']);
    }
    
    // B. Product Featured Photos & Product Count
    $stmt_prod = $pdo->prepare("SELECT p_id, p_featured_photo FROM tbl_product WHERE supplier_id = ?");
    $stmt_prod->execute(array($supplier_id));
    $products = $stmt_prod->fetchAll(PDO::FETCH_ASSOC);
    $breakdown['db_products_count'] = count($products);
    
    $p_ids = [];
    foreach ($products as $p) {
        $p_ids[] = (int)$p['p_id'];
        if (!empty($p['p_featured_photo'])) {
            $add_file($p['p_featured_photo'], $breakdown['product_photos_bytes'], $breakdown['product_photos_count']);
        }
    }
    
    // Helper for safe query counting
    $safe_count = function($sql, $params = []) use ($pdo) {
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row && isset($row['cnt']) ? (int)$row['cnt'] : 0;
        } catch (Exception $e) {
            return 0;
        }
    };

    // C. Product Gallery Photos & Sizes
    if (!empty($p_ids)) {
        $in_pids = implode(',', $p_ids);
        
        // Gallery photos
        try {
            $stmt_gal = $pdo->query("SELECT photo FROM tbl_product_photo WHERE p_id IN ($in_pids)");
            if ($stmt_gal) {
                $gal_photos = $stmt_gal->fetchAll(PDO::FETCH_ASSOC);
                foreach ($gal_photos as $gp) {
                    if (!empty($gp['photo'])) {
                        $add_file($gp['photo'], $breakdown['gallery_photos_bytes'], $breakdown['gallery_photos_count']);
                    }
                }
            }
        } catch (Exception $e) {}
        
        // Sizes mapping count
        $breakdown['db_product_sizes_count'] = $safe_count("SELECT COUNT(*) as cnt FROM tbl_product_size WHERE p_id IN ($in_pids)");
    }
    
    // D. Database Records Metrics (Data Used in Postgres)
    // 1. Orders
    $breakdown['db_orders_count'] = $safe_count("SELECT COUNT(*) as cnt FROM tbl_order WHERE supplier_id = ?", array($supplier_id));
    
    // Payments
    $breakdown['db_payments_count'] = $safe_count("SELECT COUNT(*) as cnt FROM tbl_payment WHERE supplier_id = ?", array($supplier_id));
    
    // 2. Returns
    $breakdown['db_returns_count'] = $safe_count("SELECT COUNT(*) as cnt FROM tbl_returns WHERE supplier_id = ?", array($supplier_id));
    
    // Return items
    $breakdown['db_return_items_count'] = $safe_count("SELECT COUNT(*) as cnt FROM tbl_return_items i JOIN tbl_returns r ON i.return_id = r.return_id WHERE r.supplier_id = ?", array($supplier_id));
    
    // 3. POS Users
    $breakdown['db_users_count'] = $safe_count("SELECT COUNT(*) as cnt FROM tbl_supplier_user WHERE supplier_id = ?", array($supplier_id));
    
    // 4. Quotes
    $breakdown['db_quotes_count'] = $safe_count("SELECT COUNT(*) as cnt FROM tbl_quote WHERE supplier_id = ?", array($supplier_id));
    
    // Quote items
    $breakdown['db_quote_items_count'] = $safe_count("SELECT COUNT(*) as cnt FROM tbl_quote_item qi JOIN tbl_quote q ON qi.quote_id = q.quote_id WHERE q.supplier_id = ?", array($supplier_id));
    
    // 5. Shipping Costs
    $breakdown['db_shipping_count'] = $safe_count("SELECT COUNT(*) as cnt FROM tbl_shipping_cost WHERE supplier_id = ?", array($supplier_id));
    
    // Total DB Records
    $breakdown['db_records_total'] = $breakdown['db_products_count'] 
                                   + $breakdown['db_product_sizes_count'] 
                                   + $breakdown['db_orders_count'] 
                                   + $breakdown['db_payments_count'] 
                                   + $breakdown['db_returns_count'] 
                                   + $breakdown['db_return_items_count'] 
                                   + $breakdown['db_users_count'] 
                                   + $breakdown['db_quotes_count'] 
                                   + $breakdown['db_quote_items_count'] 
                                   + $breakdown['db_shipping_count'];
    
    // Estimated Database Data Size (Avg ~1.5 KB per relational row including indexes, audit trails & text)
    $db_bytes = $breakdown['db_records_total'] * 1536;
    $breakdown['db_bytes'] = $db_bytes;
    
    // Combined Total Usage (Disk Media Files + Relational Database Data)
    $total_bytes = $file_bytes + $db_bytes;
    $total_mb = round($total_bytes / (1024 * 1024), 2);
    $total_kb = round($total_bytes / 1024, 2);
    
    $file_mb = round($file_bytes / (1024 * 1024), 2);
    $db_kb = round($db_bytes / 1024, 2);
    
    $used_pct = ($max_mb > 0) ? min(100, round(($total_mb / $max_mb) * 100, 2)) : 0;
    $remaining_mb = max(0, round($max_mb - $total_mb, 2));
    
    // Format helpers
    $format_bytes = function($bytes) {
        if ($bytes >= 1073741824) return round($bytes / 1073741824, 2) . ' GB';
        if ($bytes >= 1048576) return round($bytes / 1048576, 2) . ' MB';
        if ($bytes >= 1024) return round($bytes / 1024, 2) . ' KB';
        return $bytes . ' B';
    };
    
    $formatted_usage = $format_bytes($total_bytes);
    $formatted_file_usage = $format_bytes($file_bytes);
    $formatted_db_usage = $format_bytes($db_bytes);
    $formatted_max = ($max_mb >= 1024) ? round($max_mb / 1024, 1) . ' GB' : $max_mb . ' MB';
    $formatted_remaining = ($remaining_mb >= 1024) ? round($remaining_mb / 1024, 2) . ' GB' : $remaining_mb . ' MB';
    
    return [
        'exists' => true,
        'supplier_id' => $supplier_id,
        'supplier_name' => $supp['supplier_name'],
        'plan_name' => $plan,
        'total_bytes' => $total_bytes,
        'total_kb' => $total_kb,
        'total_mb' => $total_mb,
        'max_mb' => $max_mb,
        'file_bytes' => $file_bytes,
        'file_mb' => $file_mb,
        'db_bytes' => $db_bytes,
        'db_kb' => $db_kb,
        'formatted_usage' => $formatted_usage,
        'formatted_file_usage' => $formatted_file_usage,
        'formatted_db_usage' => $formatted_db_usage,
        'formatted_max' => $formatted_max,
        'formatted_remaining' => $formatted_remaining,
        'remaining_mb' => $remaining_mb,
        'used_pct' => $used_pct,
        'total_files' => count($files_counted),
        'product_count' => $breakdown['db_products_count'],
        'db_records_total' => $breakdown['db_records_total'],
        'breakdown' => $breakdown
    ];
}
}

if (!function_exists('ensure_return_schema')) {
    function ensure_return_schema($pdo) {
        ensure_supplier_user_schema($pdo);
    }
}

if (!function_exists('can_user_request_return')) {
    function can_user_request_return($user_or_role) {
        $role_raw = is_array($user_or_role) ? ($user_or_role['role'] ?? '') : $user_or_role;
        $norm = normalize_supplier_role($role_raw);
        // Cashiers, Operators, Supervisors, Managers, and Admins can create return requests
        return in_array($norm, ['CASHIER', 'OPERATOR', 'ORDER_PROCESSING', 'SUPERVISOR', 'MANAGER', 'ADMIN'], true);
    }
}

if (!function_exists('can_user_approve_return')) {
    function can_user_approve_return($approver_or_role, $approver_id_or_return = null, $requested_by_id = null) {
        if (is_array($approver_or_role)) {
            $approver_role_raw = $approver_or_role['role'] ?? '';
            $approver_id = $approver_or_role['id'] ?? null;
        } else {
            $approver_role_raw = $approver_or_role;
            $approver_id = is_numeric($approver_id_or_return) ? (int)$approver_id_or_return : null;
        }

        if (is_array($approver_id_or_return)) {
            $requested_by_id = $approver_id_or_return['requested_by_id'] ?? null;
        }

        $norm = normalize_supplier_role($approver_role_raw);
        // Only Admin, Manager, or Supervisor can approve returns
        if (!in_array($norm, ['ADMIN', 'MANAGER', 'SUPERVISOR'], true)) {
            return false;
        }

        // Self-Approval Rule:
        // Admin supplier ONLY is allowed to self-request and self-approve
        if ($approver_id !== null && $requested_by_id !== null && (int)$approver_id === (int)$requested_by_id) {
            if ($norm === 'ADMIN') {
                return true; // Admin supplier is allowed to self-approve
            }
            return false; // Non-admin (Manager, Supervisor, Cashier, etc.) cannot self-approve
        }
        return true;
    }
}

if (!function_exists('generate_unique_return_reference')) {
    function generate_unique_return_reference($pdo, $supplier_id = null) {
        $date_prefix = date('Ymd');
        for ($i = 0; $i < 15; $i++) {
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
}

if (!function_exists('can_user_delete_return')) {
    function can_user_delete_return($user_or_role) {
        $role_raw = is_array($user_or_role) ? ($user_or_role['role'] ?? '') : $user_or_role;
        $norm = normalize_supplier_role($role_raw);
        // Only Admin supplier is allowed to delete return transaction logs
        return ($norm === 'ADMIN');
    }
}