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

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

if (!isset($_SESSION['supplier_user']) || empty($_SESSION['supplier_user']['id'])) {
    echo json_encode([
        'status' => 'expired',
        'authenticated' => false,
        'message' => 'Session expired'
    ]);
    exit;
}

// Touch session timestamp to keep session file active on server disk
$_SESSION['last_activity'] = time();

if (!empty($_GET['tz'])) {
    $tz_candidate = trim($_GET['tz']);
    if (in_array($tz_candidate, timezone_identifiers_list(), true)) {
        $_SESSION['client_timezone'] = $tz_candidate;
        @date_default_timezone_set($tz_candidate);
    }
}

echo json_encode([
    'status' => 'active',
    'authenticated' => true,
    'user_id' => (int)$_SESSION['supplier_user']['id'],
    'supplier_id' => (int)$_SESSION['supplier_user']['supplier_id'],
    'timestamp' => time()
]);
exit;
