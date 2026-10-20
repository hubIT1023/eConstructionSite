<?php
require_once(__DIR__ . '/admin/inc/config.php');
require_once(__DIR__ . '/admin/inc/ai_access_guard.php');

header('Content-Type: text/plain; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$global_allowed = isset($pdo) ? get_global_chatgpt_policy($pdo) : false;
$allowed_slugs = array();
$blocked_slugs = array();

if (isset($pdo)) {
    try {
        $stmt = $pdo->query("SELECT supplier_slug, allow_chatgpt_access FROM public.tbl_supplier ORDER BY supplier_id ASC");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $slug = trim((string)($row['supplier_slug'] ?? ''));
            if ($slug === '') continue;
            if ($global_allowed && !empty($row['allow_chatgpt_access']) && (int)$row['allow_chatgpt_access'] === 1) {
                $allowed_slugs[] = $slug;
            } else {
                $blocked_slugs[] = $slug;
            }
        }
    } catch (Exception $e) {
        // Ignore DB error
    }
}

$ai_bots = array('GPTBot', 'ChatGPT-User', 'OAI-SearchBot');

echo "# Dynamic AI & Search Crawler Policy - Managed by eConstruction Supply Global Admin\n\n";

foreach ($ai_bots as $bot) {
    echo "User-agent: {$bot}\n";
    if (!$global_allowed || count($allowed_slugs) === 0) {
        echo "Disallow: /\n";
    } else {
        // Always protect internal admin, supplier POS, and ERP endpoints from public bot crawling
        echo "Disallow: /admin/\n";
        echo "Disallow: /supplier/\n";
        echo "Disallow: /erp/\n";
        echo "Disallow: /webpos\n";
        foreach ($blocked_slugs as $b_slug) {
            echo "Disallow: /store.php?slug=" . $b_slug . "\n";
            echo "Disallow: /store?slug=" . $b_slug . "\n";
        }
        foreach ($allowed_slugs as $a_slug) {
            echo "Allow: /store.php?slug=" . $a_slug . "\n";
            echo "Allow: /store?slug=" . $a_slug . "\n";
        }
    }
    echo "\n";
}

echo "User-agent: *\n";
echo "Disallow: /admin/\n";
echo "Disallow: /supplier/\n";
echo "Disallow: /erp/\n";
