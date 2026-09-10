<?php
require_once('/var/www/html/supplier/inc/config.php');
require_once('/var/www/html/supplier/inc/functions.php');
if (session_status() === PHP_SESSION_NONE) session_start();

$_SESSION['supplier_user'] = ['id' => 1, 'supplier_id' => 1, 'role' => 'ADMIN', 'full_name' => 'Admin Test'];
ob_start();
require('/var/www/html/supplier/returns.php');
$html = ob_get_clean();

preg_match('/<table id="example1".*?<\/table>/s', $html, $m);
if (!empty($m[0])) {
    preg_match_all('/<th[^>]*>.*?<\/th>/si', $m[0], $ths);
    echo "Total TH matched: " . count($ths[0]) . PHP_EOL;
    foreach ($ths[0] as $idx => $th) {
        echo "  TH [$idx]: " . trim(strip_tags($th)) . " -> Raw: " . htmlspecialchars($th) . PHP_EOL;
    }
}
