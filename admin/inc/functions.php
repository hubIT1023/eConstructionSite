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

if (!function_exists('ensure_supplier_user_schema')) {
    function ensure_supplier_user_schema($pdo) {
        static $checked = false;
        if ($checked) return;
        try {
            // Ensure tbl_payment schema supports decimal currency and longer status strings
            $pdo->exec("ALTER TABLE tbl_payment ALTER COLUMN paid_amount TYPE NUMERIC(10,2) USING paid_amount::numeric");
            $pdo->exec("ALTER TABLE tbl_payment ALTER COLUMN payment_method TYPE VARCHAR(100)");
            $pdo->exec("ALTER TABLE tbl_payment ALTER COLUMN payment_status TYPE VARCHAR(50)");
            $pdo->exec("ALTER TABLE tbl_payment ALTER COLUMN shipping_status TYPE VARCHAR(50)");

            // Ensure tbl_supplier SaaS and discount policy columns
            $pdo->exec("ALTER TABLE tbl_supplier ADD COLUMN IF NOT EXISTS max_pos_users INTEGER DEFAULT 3");
            $pdo->exec("ALTER TABLE tbl_supplier ADD COLUMN IF NOT EXISTS max_storage_mb INTEGER DEFAULT 2048");
            $pdo->exec("ALTER TABLE tbl_supplier ADD COLUMN IF NOT EXISTS supplier_commission NUMERIC(10,2) DEFAULT 0.00");
            $pdo->exec("ALTER TABLE tbl_supplier ADD COLUMN IF NOT EXISTS discount_enabled SMALLINT DEFAULT 1");
            $pdo->exec("ALTER TABLE tbl_supplier ADD COLUMN IF NOT EXISTS discount_normal_max NUMERIC(5,2) DEFAULT 10.00");
            $pdo->exec("ALTER TABLE tbl_supplier ADD COLUMN IF NOT EXISTS discount_special_enabled SMALLINT DEFAULT 1");
            $pdo->exec("ALTER TABLE tbl_supplier ADD COLUMN IF NOT EXISTS discount_absolute_max NUMERIC(5,2) DEFAULT 20.00");
            $pdo->exec("ALTER TABLE tbl_supplier ADD COLUMN IF NOT EXISTS discount_require_admin_approval SMALLINT DEFAULT 1");

            // Ensure tbl_product pricing and inventory columns
            $pdo->exec("ALTER TABLE tbl_product ADD COLUMN IF NOT EXISTS p_new_price VARCHAR(100)");
            $pdo->exec("ALTER TABLE tbl_product ADD COLUMN IF NOT EXISTS p_new_qty INTEGER DEFAULT 0");
            $pdo->exec("ALTER TABLE tbl_product ADD COLUMN IF NOT EXISTS p_s_level INTEGER DEFAULT 10");
            $pdo->exec("ALTER TABLE tbl_product ADD COLUMN IF NOT EXISTS p_capital_price VARCHAR(100)");
            $pdo->exec("ALTER TABLE tbl_product ADD COLUMN IF NOT EXISTS p_markup VARCHAR(100) DEFAULT '20'");

            // Ensure tbl_order schema columns
            $pdo->exec("ALTER TABLE tbl_order ADD COLUMN IF NOT EXISTS supplier_id INTEGER DEFAULT 1");
            $pdo->exec("ALTER TABLE tbl_order ADD COLUMN IF NOT EXISTS item_type VARCHAR(50) DEFAULT 'STANDARD'");
            $pdo->exec("ALTER TABLE tbl_order ADD COLUMN IF NOT EXISTS special_order_reference VARCHAR(100)");
            $pdo->exec("ALTER TABLE tbl_order ADD COLUMN IF NOT EXISTS product_details TEXT");
        } catch (Exception $e) {}
        $checked = true;
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

if (!function_exists('get_tenant_pos_user_stats')) {
    function get_tenant_pos_user_stats($pdo, $supplier_id) {
        $supplier_id = (int)$supplier_id;
        
        $stmt_supp = $pdo->prepare("SELECT supplier_id, supplier_name, supplier_slug, supplier_plan, max_pos_users, supplier_status, max_storage_mb FROM tbl_supplier WHERE supplier_id = ?");
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

if (!function_exists('handle_add_to_cart_submission')) {
    /**
     * Standardized Add to Cart Session Handler
     * Used across Homepage (index.php), Supplier Stores (store.php), and Category listings.
     */
    function handle_add_to_cart_submission($pdo, &$error_message1, &$success_message1, $expected_supplier_id = 0) {
        if (!isset($_POST['form_add_to_cart'])) {
            return;
        }

        $p_id = intval($_POST['p_id']);
        $p_qty = intval($_POST['p_qty']);
        $size_id = isset($_POST['size_id']) ? intval($_POST['size_id']) : 0;
        $size_name = isset($_POST['size_name']) ? trim($_POST['size_name']) : '';
        $color_id = isset($_POST['color_id']) ? intval($_POST['color_id']) : 0;
        $color_name = isset($_POST['color_name']) ? trim($_POST['color_name']) : '';

        // Verify stock & fetch product details from DB
        $stmt_chk = $pdo->prepare("SELECT supplier_id, p_qty, p_name, p_current_price, p_featured_photo FROM tbl_product WHERE p_id=?");
        $stmt_chk->execute([$p_id]);
        $prod_chk = $stmt_chk->fetch(PDO::FETCH_ASSOC);

        if (!$prod_chk) {
            $error_message1 = 'Selected product could not be found.';
            return;
        }

        // Validate supplier isolation if expected_supplier_id > 0
        if ($expected_supplier_id > 0 && intval($prod_chk['supplier_id']) !== $expected_supplier_id) {
            $error_message1 = 'Selected product does not belong to this supplier.';
            return;
        }

        $current_stock = intval($prod_chk['p_qty']);
        $clean_price = floatval(preg_replace('/[^0-9.]/', '', strval($prod_chk['p_current_price'])));
        $p_current_price = $clean_price;
        $p_name = $prod_chk['p_name'];
        $p_featured_photo = $prod_chk['p_featured_photo'];

        if ($p_qty <= 0) {
            $p_qty = 1;
        }

        if ($p_qty > $current_stock) {
            $error_message1 = 'Sorry! There are only ' . $current_stock . ' item(s) in stock for ' . htmlspecialchars($p_name) . '.';
            return;
        }

        if (isset($_SESSION['cart_p_id'])) {
            $arr_cart_p_id = array();
            $arr_cart_size_id = array();
            $arr_cart_size_name = array();
            $arr_cart_color_id = array();
            $arr_cart_color_name = array();
            $arr_cart_p_qty = array();
            $arr_cart_p_current_price = array();
            $arr_cart_p_name = array();
            $arr_cart_p_featured_photo = array();

            $i = 0;
            foreach ($_SESSION['cart_p_id'] as $key => $value) {
                $i++;
                $arr_cart_p_id[$i] = $value;
                $arr_cart_size_id[$i] = isset($_SESSION['cart_size_id'][$key]) ? $_SESSION['cart_size_id'][$key] : 0;
                $arr_cart_size_name[$i] = isset($_SESSION['cart_size_name'][$key]) ? $_SESSION['cart_size_name'][$key] : '';
                $arr_cart_color_id[$i] = isset($_SESSION['cart_color_id'][$key]) ? $_SESSION['cart_color_id'][$key] : 0;
                $arr_cart_color_name[$i] = isset($_SESSION['cart_color_name'][$key]) ? $_SESSION['cart_color_name'][$key] : '';
                $arr_cart_p_qty[$i] = isset($_SESSION['cart_p_qty'][$key]) ? $_SESSION['cart_p_qty'][$key] : 1;
                $arr_cart_p_current_price[$i] = isset($_SESSION['cart_p_current_price'][$key]) ? $_SESSION['cart_p_current_price'][$key] : 0;
                $arr_cart_p_name[$i] = isset($_SESSION['cart_p_name'][$key]) ? $_SESSION['cart_p_name'][$key] : '';
                $arr_cart_p_featured_photo[$i] = isset($_SESSION['cart_p_featured_photo'][$key]) ? $_SESSION['cart_p_featured_photo'][$key] : '';
            }

            $added = 0;
            $matched_key = 0;
            for ($i = 1; $i <= count($arr_cart_p_id); $i++) {
                if ($arr_cart_p_id[$i] == $p_id && $arr_cart_size_id[$i] == $size_id && $arr_cart_color_id[$i] == $color_id) {
                    $added = 1;
                    $matched_key = $i;
                    break;
                }
            }

            if ($added == 1) {
                $new_qty = $arr_cart_p_qty[$matched_key] + $p_qty;
                if ($new_qty > $current_stock) {
                    $new_qty = $current_stock;
                    $error_message1 = 'Product is already in cart. Quantity updated to maximum available stock (' . $current_stock . ').';
                } else {
                    $success_message1 = 'Cart updated successfully!';
                }
                $_SESSION['cart_p_qty'][$matched_key] = $new_qty;
            } else {
                $new_key = count($arr_cart_p_id) + 1;
                $_SESSION['cart_p_id'][$new_key] = $p_id;
                $_SESSION['cart_size_id'][$new_key] = $size_id;
                $_SESSION['cart_size_name'][$new_key] = $size_name;
                $_SESSION['cart_color_id'][$new_key] = $color_id;
                $_SESSION['cart_color_name'][$new_key] = $color_name;
                $_SESSION['cart_p_qty'][$new_key] = $p_qty;
                $_SESSION['cart_p_current_price'][$new_key] = $p_current_price;
                $_SESSION['cart_p_name'][$new_key] = $p_name;
                $_SESSION['cart_p_featured_photo'][$new_key] = $p_featured_photo;
                $success_message1 = 'Product is added to the cart successfully!';
            }
        } else {
            $_SESSION['cart_p_id'][1] = $p_id;
            $_SESSION['cart_size_id'][1] = $size_id;
            $_SESSION['cart_size_name'][1] = $size_name;
            $_SESSION['cart_color_id'][1] = $color_id;
            $_SESSION['cart_color_name'][1] = $color_name;
            $_SESSION['cart_p_qty'][1] = $p_qty;
            $_SESSION['cart_p_current_price'][1] = $p_current_price;
            $_SESSION['cart_p_name'][1] = $p_name;
            $_SESSION['cart_p_featured_photo'][1] = $p_featured_photo;
            $success_message1 = 'Product is added to the cart successfully!';
        }
    }
}