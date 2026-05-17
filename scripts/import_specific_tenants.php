<?php
/**
 * scripts/import_specific_tenants.php
 * =====================================
 * This script imports the tenant schema into specific databases provided
 * and registers them in the system database pool.
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set CLI header
if (php_sapi_name() !== 'cli') {
    echo "<pre>";
}

// Load TenantManager for pool registration
require_once __DIR__ . '/../config/tenant_manager.php';

$tenantPatterns = TenantManager::getTenantDetectionPatterns();
$tenantHost = TenantManager::getTenantPoolHost();
$maxSchools = 5;
$basePattern = $tenantPatterns[0] ?? 'Middlehi_IDAHOSA_%d';
$schools = [];
for ($i = 1; $i <= $maxSchools; $i++) {
    $dbName = sprintf($basePattern, $i);
    $schools[] = [
        'host' => $tenantHost,
        'user' => $dbName,
        'pass' => $dbName,
        'name' => $dbName
    ];
}

$schemaPath = __DIR__ . '/../database/tenant_schema.sql';
if (!file_exists($schemaPath)) {
    die("FATAL ERROR: tenant_schema.sql not found at $schemaPath\n");
}

echo "========================================\n";
echo "TENANT DATABASE PROVISIONING SYSTEM\n";
echo "========================================\n\n";

foreach ($schools as $index => $school) {
    echo "--- [" . ($index + 1) . "] Processing: {$school['name']} ---\n";
    
    try {
        // 1. Connection Detection
        $pdo = new PDO(
            "mysql:host={$school['host']};dbname={$school['name']};charset=utf8mb4",
            $school['user'],
            $school['pass'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 5
            ]
        );
        echo "   [✓] Connection established successfully.\n";

        // 2. Schema Detection
        $tableExists = false;
        try {
            $check = $pdo->query("SHOW TABLES LIKE 'academic_sessions'");
            if ($check->rowCount() > 0) {
                $tableExists = true;
            }
        } catch (Exception $e) {
            $tableExists = false;
        }

        if ($tableExists) {
            echo "   [!] Schema already detected. Skipping import to prevent data loss.\n";
        } else {
            echo "   [>] Schema missing. Initializing 'tenant_schema.sql'...\n";
            $sql = file_get_contents($schemaPath);
            
            // Clean/Strip and Split
            $sql = preg_replace('/--.*$/m', '', $sql);
            $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);
            $queries = array_filter(array_map('trim', explode(';', $sql)));
            
            $count = 0;
            foreach ($queries as $query) {
                if (!empty($query)) {
                    try {
                        $pdo->exec($query);
                        $count++;
                    } catch (PDOException $e) {
                        // Table exist or non-standard SQL errors - just log
                        if (strpos($e->getMessage(), "already exists") === false) {
                            echo "     ! Error in query: " . substr($e->getMessage(), 0, 80) . "...\n";
                        }
                    }
                }
            }
            echo "   [✓] Imported $count queries.\n";
        }

        // 3. System Pool Registration
        echo "   [>] Registering in system database pool...\n";
        try {
            $res = TenantManager::addToPool($school['name'], $school['user'], $school['pass'], $school['host']);
            if ($res) {
                echo "   [✓] SUCCESS: Database registered and ready for assignment.\n";
            } else {
                echo "   [i] Database already present in pool.\n";
            }
        } catch (Exception $e) {
            echo "   [!] WARNING: Pool registration failed: " . $e->getMessage() . "\n";
        }

    } catch (PDOException $e) {
        echo "   [✘] FAILURE: Could not connect. Check credentials or firewall.\n";
        echo "       Error: " . $e->getMessage() . "\n";
    }
    echo "\n";
}

echo "========================================\n";
echo "PROVISIONING COMPLETE\n";
echo "========================================\n";
if (php_sapi_name() !== 'cli') {
    echo "</pre>";
}
