<?php
// Error Reporting Turn On
ini_set('error_reporting', E_ALL);

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

// Setting up the time zone
date_default_timezone_set('America/Los_Angeles');

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