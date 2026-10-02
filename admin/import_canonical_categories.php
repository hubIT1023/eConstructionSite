<?php
/**
 * Canonical Categories Importer
 *
 * Synchronizes the complete, verified 3-tier construction taxonomy
 * (28 Top Categories, 91 Mid Categories, 288 End Categories)
 * into PostgreSQL with exact IDs, safe transaction rollback,
 * and sequence updates.
 */

require_once(__DIR__ . '/inc/config.php');

echo "=== STARTING CANONICAL CATEGORIES SYNCHRONIZATION ===\n";

$sqlFile = dirname(__DIR__) . '/scratch/local_categories.sql';
if (!file_exists($sqlFile)) {
    // If running in container where scratch might be different path:
    $altPaths = [
        __DIR__ . '/local_categories.sql',
        dirname(__DIR__) . '/local_categories.sql',
        '/tmp/local_categories.sql',
        '/root/local_categories.sql'
    ];
    foreach ($altPaths as $ap) {
        if (file_exists($ap)) {
            $sqlFile = $ap;
            break;
        }
    }
}

if (!file_exists($sqlFile)) {
    die("ERROR: Category dump SQL file not found.\n");
}

echo "Loading SQL Dump: {$sqlFile}\n";

$sqlContent = file_get_contents($sqlFile);
if (empty($sqlContent)) {
    die("ERROR: SQL Dump file is empty.\n");
}

try {
    $pdo->beginTransaction();

    // 1. Temporarily clear category tables inside transaction
    $pdo->exec("TRUNCATE TABLE tbl_end_category, tbl_mid_category, tbl_top_category RESTART IDENTITY;");

    // 2. Parse COPY blocks from pg_dump
    // COPY public.tbl_top_category ... \.
    // COPY public.tbl_mid_category ... \.
    // COPY public.tbl_end_category ... \.

    // Top Categories
    if (preg_match('/COPY public\.tbl_top_category[^\n]*\n(.*?)\n\\\./s', $sqlContent, $m)) {
        $lines = explode("\n", trim($m[1]));
        $stmt = $pdo->prepare("INSERT INTO tbl_top_category (tcat_id, tcat_name, show_on_menu) VALUES (?, ?, ?)");
        foreach ($lines as $line) {
            $parts = explode("\t", trim($line));
            if (count($parts) >= 3) {
                $stmt->execute([(int)$parts[0], trim($parts[1]), (int)$parts[2]]);
            }
        }
        echo "Inserted " . count($lines) . " Top Categories\n";
    }

    // Mid Categories
    if (preg_match('/COPY public\.tbl_mid_category[^\n]*\n(.*?)\n\\\./s', $sqlContent, $m)) {
        $lines = explode("\n", trim($m[1]));
        $stmt = $pdo->prepare("INSERT INTO tbl_mid_category (mcat_id, mcat_name, tcat_id) VALUES (?, ?, ?)");
        foreach ($lines as $line) {
            $parts = explode("\t", trim($line));
            if (count($parts) >= 3) {
                $stmt->execute([(int)$parts[0], trim($parts[1]), (int)$parts[2]]);
            }
        }
        echo "Inserted " . count($lines) . " Mid Categories\n";
    }

    // End Categories
    if (preg_match('/COPY public\.tbl_end_category[^\n]*\n(.*?)\n\\\./s', $sqlContent, $m)) {
        $lines = explode("\n", trim($m[1]));
        $stmt = $pdo->prepare("INSERT INTO tbl_end_category (ecat_id, ecat_name, mcat_id) VALUES (?, ?, ?)");
        foreach ($lines as $line) {
            $parts = explode("\t", trim($line));
            if (count($parts) >= 3) {
                $stmt->execute([(int)$parts[0], trim($parts[1]), (int)$parts[2]]);
            }
        }
        echo "Inserted " . count($lines) . " End Categories\n";
    }

    // 3. Reset Sequences
    $pdo->exec("SELECT setval('tbl_top_category_tcat_id_seq', COALESCE((SELECT MAX(tcat_id) FROM tbl_top_category), 1))");
    $pdo->exec("SELECT setval('tbl_mid_category_mcat_id_seq', COALESCE((SELECT MAX(mcat_id) FROM tbl_mid_category), 1))");
    $pdo->exec("SELECT setval('tbl_end_category_ecat_id_seq', COALESCE((SELECT MAX(ecat_id) FROM tbl_end_category), 1))");

    $pdo->commit();

    echo "=== CANONICAL CATEGORIES SYNCHRONIZED SUCCESSFULLY ===\n";
    $topCount = $pdo->query("SELECT count(*) FROM tbl_top_category")->fetchColumn();
    $midCount = $pdo->query("SELECT count(*) FROM tbl_mid_category")->fetchColumn();
    $endCount = $pdo->query("SELECT count(*) FROM tbl_end_category")->fetchColumn();
    echo "Top Categories: {$topCount}\n";
    echo "Mid Categories: {$midCount}\n";
    echo "End Categories: {$endCount}\n";

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
