<?php
// Error Reporting Turn On
//ini_set('error_reporting', E_ALL);

// Setting up the time zone
date_default_timezone_set('America/Los_Angeles');

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


       	