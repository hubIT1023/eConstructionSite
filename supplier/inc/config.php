<?php
// Error Reporting Turn On
ini_set('error_reporting', E_ALL);

// Session Lifetime Configuration (Only apply if session is not active and headers not sent)
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    ini_set('session.gc_maxlifetime', 604800);
    ini_set('session.cookie_lifetime', 604800);
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_samesite', 'Lax');
}

// Load environment variables from .env file if it exists
$env_file = dirname(__DIR__, 2) . '/.env';
if (file_exists($env_file)) {
    $lines = file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0) {
            continue;
        }
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);
            
            // Remove quotes if present
            if (preg_match('/^"(.*)"$/', $value, $matches) || preg_match('/^\'(.*)\'$/', $value, $matches)) {
                $value = $matches[1];
            }
            
            if (getenv($name) === false) {
                putenv("$name=$value");
            }
            if (!isset($_ENV[$name])) {
                $_ENV[$name] = $value;
            }
            if (!isset($_SERVER[$name])) {
                $_SERVER[$name] = $value;
            }
        }
    }
}

// Setting up the time zone (Automatic client PC detection with Asia/Manila fallback)
$detected_tz = !empty($_COOKIE['client_timezone']) ? trim($_COOKIE['client_timezone']) : (!empty($_SESSION['client_timezone']) ? trim($_SESSION['client_timezone']) : null);
if ($detected_tz && @date_default_timezone_set($detected_tz)) {
    // Successfully adopted client PC timezone
} else {
    $fallback_tz = getenv('APP_TIMEZONE') ?: 'Asia/Manila';
    @date_default_timezone_set($fallback_tz);
}

// Host Name
$dbhost = getenv('DB_HOST') ?: 'db';

// Database Name
$dbname = getenv('DB_NAME') ?: 'ecomDB';

// Database Username
$dbuser = getenv('DB_USER') ?: 'ecom_admin';

// Database Password
$dbpass = getenv('DB_PASS') ?: '#Adm1n_0WN3R';

// Defining base url
define("BASE_URL", "");

// Getting Admin url
define("ADMIN_URL", BASE_URL . "admin" . "/");

try {
	$pdo = new PDO("pgsql:host={$dbhost};port=5432;dbname={$dbname}", $dbuser, $dbpass);
	$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
}
catch( PDOException $exception ) {
	echo "Connection error :" . $exception->getMessage();
}