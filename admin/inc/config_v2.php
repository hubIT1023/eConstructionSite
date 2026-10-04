<?php
// Error Reporting Turn On
//ini_set('error_reporting', E_ALL);

// Setting up the time zone (Automatic client PC detection with Asia/Manila fallback)
$detected_tz = !empty($_COOKIE['client_timezone']) ? trim($_COOKIE['client_timezone']) : (!empty($_SESSION['client_timezone']) ? trim($_SESSION['client_timezone']) : null);
if ($detected_tz && @date_default_timezone_set($detected_tz)) {
    // Adopted client PC timezone
} else {
    $fallback_tz = getenv('APP_TIMEZONE') ?: 'Asia/Manila';
    @date_default_timezone_set($fallback_tz);
}

// Host Name
	$serverName ="SMART\SQLEXPRESS";
	// Database Name
	$database = "ecommerceweb";
	// Database Username
	$uid = "tool_admin";
	// Database Password
	$pwd = "T001_OWN3R";

// Defining base url
define("BASE_URL", "");

// Getting Admin url
define("ADMIN_URL", BASE_URL . "admin" . "/");

//Establishes the connection
	try {
		$pdo = new PDO("sqlsrv:server=$serverName ; Database = $database", $uid, $pwd);
		$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	}
	catch( PDOException $exception ) {
		echo "Connection error :" . $exception->getMessage();
	}	


       	