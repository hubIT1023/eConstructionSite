<?php
/**
 * ERP Admin Helper Module
 * Safe, isolated helper for managing ERP SaaS subscriptions from Global Admin.
 * Wraps all ERP queries in try-catch so legacy POS operations never fail.
 */

if (!defined('ERP_LOCAL_URL')) {
    $req_h = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $h_only = explode(':', $req_h)[0];
    $erp_h = in_array($h_only, ['localhost', '127.0.0.1']) ? 'localhost' : $h_only;
    $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') 
        || ($_SERVER['SERVER_PORT'] ?? 80) == 443 
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    if ($is_https && !in_array($h_only, ['localhost', '127.0.0.1'])) {
        define('ERP_LOCAL_URL', 'https://' . $erp_h . '/erp');
    } else {
        define('ERP_LOCAL_URL', 'http://' . $erp_h . ':4000');
    }
}

/**
 * Fetch ERP subscription and module entitlements for a tenant.
 */
function erp_get_tenant_entitlements($pdo, $supplier_id) {
    $default = [
        'supplier_id' => (int)$supplier_id,
        'erp_enabled' => false,
        'erp_plan' => 'Standard',
        'has_accounting' => true,
        'has_opex_tracking' => true,
        'has_sales_incentives' => true,
        'has_multi_warehouse' => false,
        'max_warehouses' => 1,
        'max_cost_centers' => 1,
        'status' => 'ACTIVE'
    ];

    try {
        $stmt = $pdo->prepare("SELECT * FROM erp.subscription_entitlements WHERE supplier_id = ?");
        $stmt->execute([(int)$supplier_id]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($res) {
            $res['erp_enabled'] = (bool)$res['erp_enabled'];
            $res['has_accounting'] = (bool)$res['has_accounting'];
            $res['has_opex_tracking'] = (bool)$res['has_opex_tracking'];
            $res['has_sales_incentives'] = (bool)$res['has_sales_incentives'];
            $res['has_multi_warehouse'] = (bool)$res['has_multi_warehouse'];
            return $res;
        }
    } catch (Exception $e) {
        error_log('[ERP Admin Helper] Failed to read entitlements: ' . $e->getMessage());
    }

    return $default;
}

/**
 * Save ERP subscription and module entitlements for a tenant.
 */
function erp_save_tenant_entitlements($pdo, $supplier_id, $data) {
    try {
        $erp_enabled = !empty($data['erp_enabled']) ? 'true' : 'false';
        $erp_plan = !empty($data['erp_plan']) ? trim($data['erp_plan']) : 'Standard';
        $has_accounting = !empty($data['has_accounting']) ? 'true' : 'false';
        $has_opex_tracking = !empty($data['has_opex_tracking']) ? 'true' : 'false';
        $has_sales_incentives = !empty($data['has_sales_incentives']) ? 'true' : 'false';
        $has_multi_warehouse = !empty($data['has_multi_warehouse']) ? 'true' : 'false';
        $max_warehouses = isset($data['max_warehouses']) ? max(1, (int)$data['max_warehouses']) : 1;
        $max_cost_centers = isset($data['max_cost_centers']) ? max(1, (int)$data['max_cost_centers']) : 1;

        $sql = "INSERT INTO erp.subscription_entitlements 
                (supplier_id, erp_enabled, erp_plan, has_accounting, has_opex_tracking, has_sales_incentives, has_multi_warehouse, max_warehouses, max_cost_centers, status, updated_at)
                VALUES (?, ?::boolean, ?, ?::boolean, ?::boolean, ?::boolean, ?::boolean, ?, ?, 'ACTIVE', NOW())
                ON CONFLICT (supplier_id) 
                DO UPDATE SET 
                    erp_enabled = EXCLUDED.erp_enabled,
                    erp_plan = EXCLUDED.erp_plan,
                    has_accounting = EXCLUDED.has_accounting,
                    has_opex_tracking = EXCLUDED.has_opex_tracking,
                    has_sales_incentives = EXCLUDED.has_sales_incentives,
                    has_multi_warehouse = EXCLUDED.has_multi_warehouse,
                    max_warehouses = EXCLUDED.max_warehouses,
                    max_cost_centers = EXCLUDED.max_cost_centers,
                    status = 'ACTIVE',
                    updated_at = NOW()";

        $stmt = $pdo->prepare($sql);
        return $stmt->execute([
            (int)$supplier_id,
            $erp_enabled,
            $erp_plan,
            $has_accounting,
            $has_opex_tracking,
            $has_sales_incentives,
            $has_multi_warehouse,
            $max_warehouses,
            $max_cost_centers
        ]);
    } catch (Exception $e) {
        error_log('[ERP Admin Helper] Failed to save entitlements: ' . $e->getMessage());
        return false;
    }
}

/**
 * Fetch all tenant ERP subscription statuses as a map [supplier_id => erp_enabled].
 */
function erp_get_all_entitlements_map($pdo) {
    $map = [];
    try {
        $stmt = $pdo->query("SELECT supplier_id, erp_enabled, erp_plan FROM erp.subscription_entitlements");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $map[$row['supplier_id']] = [
                'enabled' => (bool)$row['erp_enabled'],
                'plan' => $row['erp_plan']
            ];
        }
    } catch (Exception $e) {
        // Return empty map gracefully
    }
    return $map;
}

/**
 * Get ERP platform stats for index.php dashboard.
 */
function erp_get_platform_stats($pdo) {
    $stats = [
        'total_erp_active' => 0,
        'total_synced_orders' => 0,
        'total_bank_fees_tracked' => 0.00,
        'total_opex_tracked' => 0.00
    ];
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM erp.subscription_entitlements WHERE erp_enabled = true");
        $stats['total_erp_active'] = (int)$stmt->fetchColumn();

        $stmt = $pdo->query("SELECT COUNT(*) FROM erp.payment_fee_transactions");
        $stats['total_synced_orders'] = (int)$stmt->fetchColumn();

        $stmt = $pdo->query("SELECT COALESCE(SUM(calculated_fee), 0) FROM erp.payment_fee_transactions");
        $stats['total_bank_fees_tracked'] = (float)$stmt->fetchColumn();

        $stmt = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM erp.operating_expenses");
        $stats['total_opex_tracked'] = (float)$stmt->fetchColumn();
    } catch (Exception $e) {
        // Return empty stats gracefully
    }
    return $stats;
}
