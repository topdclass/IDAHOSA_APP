<?php
/**
 * scripts/provision_pending_tenants.php
 * ======================================
 * Provision pending schools by assigning the next available tenant database
 * from the pool and importing the tenant schema.
 *
 * Usage (CLI): php scripts/provision_pending_tenants.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../config/tenant_manager.php';

$supPdo = TenantManager::getSupervisorConnection();
$pending = $supPdo->query("SELECT * FROM institution_profile WHERE status IN ('Pending','pending') ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);

if (empty($pending)) {
    echo "No pending schools found.\n";
    exit(0);
}

foreach ($pending as $school) {
    printf("Processing pending school #%d: %s\n", $school['id'], $school['institution_name']);
    $result = TenantManager::createTenantDatabase((int)$school['id']);
    if ($result['success']) {
        echo "  [OK] Assigned database: {$result['db_name']}\n";
    } else {
        echo "  [FAIL] {$result['message']}\n";
    }
}

echo "Provisioning complete.\n";
