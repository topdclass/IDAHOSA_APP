<?php
/**
 * Database Migration Script
 * Adds missing columns (username, password, photo_url) to all tenant 'users' tables.
 */
require_once __DIR__ . '/../config/tenant_manager.php';
require_once __DIR__ . '/../config/database.php'; // sets $supervisorPdo

echo "--- RosmonSMS Tenant DB Schema Fix ---\n";

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

            // Add username column if NOT exists
            try {
                $pdo->exec("ALTER TABLE users ADD COLUMN username VARCHAR(100) NULL AFTER email");
                echo "Added 'username'. ";
            } catch (PDOException $e) {
                // Ignore if column already exists
            }

            // Add password column if NOT exists
            try {
                $pdo->exec("ALTER TABLE users ADD COLUMN password VARCHAR(255) NULL AFTER username");
                echo "Added 'password'. ";
            } catch (PDOException $e) {
                // Ignore
            }

            // Add photo_url column if NOT exists
            try {
                $pdo->exec("ALTER TABLE users ADD COLUMN photo_url VARCHAR(255) NULL AFTER phone");
                echo "Added 'photo_url'. ";
            } catch (PDOException $e) {
                // Ignore
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
