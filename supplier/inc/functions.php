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