<?php
/**
 * Database Migration Script
 * Adds 'teacher_id' column to all tenant 'classes' tables.
 */
require_once __DIR__ . '/../config/tenant_manager.php';
require_once __DIR__ . '/../config/database.php'; // sets $supervisorPdo

echo "--- RosmonSMS Tenant Classes Table Fix ---\n";

try {
    // Get all databases in the pool
    $stmt = $supervisorPdo->query("SELECT id, db_name, school_id FROM db_pool WHERE is_assigned = 1");
    $pools = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($pools)) {
        echo "No assigned tenant databases found in pool.\n";
        exit;
    }

    foreach ($pools as $pool) {
        $schoolId = $pool['school_id'];
        $dbName = $pool['db_name'];
        echo "Processing School ID: $schoolId (DB: $dbName)... ";

        try {
            $pdo = TenantManager::getTenantConnection($schoolId);

            // Add teacher_id column if NOT exists
            try {
                $pdo->exec("ALTER TABLE classes ADD COLUMN teacher_id INT NULL AFTER arm");
                echo "Added 'teacher_id'. ";
            } catch (PDOException $e) {
                // Ignore if column already exists
                echo "Already exists or skipped. ";
            }

            echo "Done.\n";
        } catch (Exception $e) {
            echo "FAILED: " . $e->getMessage() . "\n";
        }
    }

    echo "--- All tenant databases processed ---\n";

} catch (Exception $e) {
    echo "CRITICAL ERROR: " . $e->getMessage() . "\n";
}
