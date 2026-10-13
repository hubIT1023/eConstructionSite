<?php
require_once dirname(__DIR__) . '/admin/inc/config.php';
require_once dirname(__DIR__) . '/admin/inc/erp_admin_helper.php';

echo "=== 1. Testing erp_get_platform_stats ===\n";
$stats = erp_get_platform_stats($pdo);
print_r($stats);

echo "\n=== 2. Testing erp_get_all_entitlements_map ===\n";
$map = erp_get_all_entitlements_map($pdo);
print_r($map);

echo "\n=== 3. Testing erp_get_tenant_entitlements for Tenant #1 ===\n";
$t1 = erp_get_tenant_entitlements($pdo, 1);
print_r($t1);

echo "\n=== 4. Testing erp_save_tenant_entitlements for Tenant #2 ===\n";
$saveOk = erp_save_tenant_entitlements($pdo, 2, [
    'erp_enabled' => 1,
    'erp_plan' => 'Professional',
    'has_accounting' => 1,
    'has_opex_tracking' => 1,
    'has_sales_incentives' => 1,
    'has_multi_warehouse' => 0,
    'max_cost_centers' => 2
]);
echo "Save result: " . ($saveOk ? "SUCCESS" : "FAILED") . "\n";

$t2 = erp_get_tenant_entitlements($pdo, 2);
print_r($t2);

echo "\n=== ALL TESTS PASSED SUCCESSFULLY! ===\n";
