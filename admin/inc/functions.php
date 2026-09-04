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