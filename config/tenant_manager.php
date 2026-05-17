<?php
/**
 * TenantManager — Database Pool Architecture
 * ============================================
 *
 * SUPERVISOR DB CREDENTIALS DERIVE ALL TENANT CREDENTIALS AUTOMATICALLY.
 *
 * Example:
 *   Supervisor DB: middlehi_IDAHOSA / middlehi_IDAHOSA / middlehi_IDAHOSA
 *   Tenant 1:      middlehi_IDAHOSA_1 / middlehi_IDAHOSA_1 / middlehi_IDAHOSA_1
 *   Tenant 50:     middlehi_IDAHOSA_50 / middlehi_IDAHOSA_50 / middlehi_IDAHOSA_50
 */

if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__));
}

class TenantManager {

    private static $host          = 'localhost';
    private static $user          = 'root';
    private static $pass          = '';
    private static $dbname        = 'rosmonsms';
    private static $prefix        = '';
    private static $tenantDbHost  = 'localhost';
    private static $tenantPoolSize = 50;

    private static $configLoaded  = false;
    private static $supervisorPdo = null;

    // ── Load env ──────────────────────────────────────────────────────
    private static function loadConfig(): void {
        if (self::$configLoaded) return;
        $file = __DIR__ . '/env.php';
        if (file_exists($file)) {
            $c = require $file;
            self::$host           = $c['DB_HOST']          ?? self::$host;
            self::$user           = $c['DB_USER']          ?? self::$user;
            self::$pass           = $c['DB_PASS']          ?? self::$pass;
            self::$dbname         = $c['DB_NAME']          ?? self::$dbname;
            self::$prefix         = $c['DB_PREFIX']        ?? self::$prefix;
            self::$tenantDbHost   = $c['TENANT_DB_HOST']   ?? self::$tenantDbHost;
            self::$tenantPoolSize = (int)($c['TENANT_POOL_SIZE'] ?? 50);
        }
        self::$configLoaded = true;
    }

    public static function getSupervisorDbName(): string {
        self::loadConfig();
        return self::$prefix . self::$dbname;
    }

    /**
     * Derive tenant credentials from supervisor credentials.
     * db_name = {supervisor_db}_{index}
     * db_user = {supervisor_db}_{index}
     * db_pass = {supervisor_db}_{index}
     */
    public static function deriveTenantCredentials(int $index): array {
        self::loadConfig();
        $base = self::getSupervisorDbName();
        $name = "{$base}_{$index}";
        return [
            'pool_index' => $index,
            'db_name'    => $name,
            'db_user'    => $name,
            'db_pass'    => $name,
            'db_host'    => self::$tenantDbHost,
        ];
    }

    public static function getPoolSize(): int {
        self::loadConfig();
        return self::$tenantPoolSize;
    }

    // ── SUPERVISOR CONNECTION ─────────────────────────────────────────
    public static function getSupervisorConnection(): PDO {
        if (self::$supervisorPdo) return self::$supervisorPdo;
        self::loadConfig();
        $dbName = self::getSupervisorDbName();
        try {
            self::$supervisorPdo = new PDO(
                "mysql:host=" . self::$host . ";dbname={$dbName};charset=utf8mb4",
                self::$user, self::$pass,
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]
            );
            return self::$supervisorPdo;
        } catch (PDOException $e) {
            error_log("[TenantManager] Supervisor DB error: " . $e->getMessage());
            http_response_code(503);
            die(self::dbErrorHtml($dbName, $e->getMessage()));
        }
    }

    // ── TENANT CONNECTION ─────────────────────────────────────────────
    public static function getTenantConnection($schoolId = null): PDO {
        if (!$schoolId) return self::getSupervisorConnection();
        try {
            $supPdo = self::getSupervisorConnection();
            self::ensurePoolTableExists($supPdo);
            $stmt = $supPdo->prepare(
                "SELECT db_name, db_user, db_pass, db_host FROM db_pool
                 WHERE school_id = ? AND is_assigned = 1 LIMIT 1"
            );
            $stmt->execute([$schoolId]);
            $pool = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$pool) return self::getSupervisorConnection();
            return new PDO(
                "mysql:host={$pool['db_host']};dbname={$pool['db_name']};charset=utf8mb4",
                $pool['db_user'], $pool['db_pass'],
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]
            );
        } catch (PDOException $e) {
            if (stripos($e->getMessage(), 'db_pool') !== false ||
                stripos($e->getMessage(), "doesn't exist") !== false) {
                return self::getSupervisorConnection();
            }
            error_log("[TenantManager] Tenant DB error (school #{$schoolId}): " . $e->getMessage());
            die(self::dbErrorHtml("School DB (ID: {$schoolId})", $e->getMessage()));
        }
    }

    // ── SCHOOL PROVISIONING ───────────────────────────────────────────
    public static function createTenantDatabase($schoolId): array {
        self::loadConfig();
        $supPdo = self::getSupervisorConnection();
        self::ensurePoolTableExists($supPdo);

        // Already provisioned?
        $chk = $supPdo->prepare("SELECT db_name FROM db_pool WHERE school_id=? AND is_assigned=1");
        $chk->execute([$schoolId]);
        if ($existing = $chk->fetch()) {
            return ['success'=>true,'message'=>"Already provisioned: {$existing['db_name']}",
                    'db_name'=>$existing['db_name']];
        }

        // Find next free slot
        $slot = $supPdo->query(
            "SELECT * FROM db_pool WHERE is_assigned=0 ORDER BY pool_index ASC LIMIT 1"
        )->fetch(PDO::FETCH_ASSOC);

        if (!$slot) {
            return [
                'success' => false,
                'message' => "All " . self::$tenantPoolSize . " database slots are in use. Contact support to expand the pool.",
                'db_name' => null,
            ];
        }

        // Connect to tenant DB
        try {
            $tenantPdo = new PDO(
                "mysql:host={$slot['db_host']};dbname={$slot['db_name']};charset=utf8mb4",
                $slot['db_user'], $slot['db_pass'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => "Cannot connect to '{$slot['db_name']}': " . $e->getMessage()
                           . " — Create this DB and user in cPanel with FULL privileges.",
                'db_name' => null,
            ];
        }

        // Import schema
        $import = self::importTenantSchema($tenantPdo);
        if (!$import['success']) {
            return ['success'=>false,'message'=>"Schema import failed: ".$import['message'],'db_name'=>null];
        }

        // Verify
        $verify = $tenantPdo->query("SHOW TABLES LIKE 'academic_sessions'")->fetchAll();
        if (empty($verify)) {
            return ['success'=>false,'message'=>"Schema imported but tables missing in '{$slot['db_name']}'.",'db_name'=>null];
        }

        // Assign slot
        $supPdo->prepare("UPDATE db_pool SET is_assigned=1, school_id=?, assigned_at=NOW() WHERE id=?")
               ->execute([$schoolId, $slot['id']]);

        return [
            'success' => true,
            'message' => "Database '{$slot['db_name']}' provisioned for school #{$schoolId}.",
            'db_name' => $slot['db_name'],
        ];
    }

    private static function importTenantSchema(PDO $pdo): array {
        $f = ROOT_PATH . '/database/tenant_schema.sql';
        if (!file_exists($f)) return ['success'=>false,'message'=>'tenant_schema.sql not found.'];
        try {
            self::runSqlFile($pdo, $f);
            return ['success'=>true,'message'=>'OK'];
        } catch (PDOException $e) {
            return ['success'=>false,'message'=>$e->getMessage()];
        }
    }

    // ── POOL POPULATION ───────────────────────────────────────────────
    /**
     * Register all 50 derived tenant slots into db_pool.
     * Call from install.php / setup_pool.php.
     */
    public static function populatePool(): array {
        self::loadConfig();
        $supPdo = self::getSupervisorConnection();
        self::ensurePoolTableExists($supPdo);

        $added = $skipped = 0; $errors = [];
        for ($i = 1; $i <= self::$tenantPoolSize; $i++) {
            $cred = self::deriveTenantCredentials($i);
            try {
                $stmt = $supPdo->prepare(
                    "INSERT IGNORE INTO db_pool (pool_index, db_name, db_user, db_pass, db_host)
                     VALUES (?,?,?,?,?)"
                );
                $stmt->execute([$i, $cred['db_name'], $cred['db_user'], $cred['db_pass'], $cred['db_host']]);
                $stmt->rowCount() > 0 ? $added++ : $skipped++;
            } catch (PDOException $e) {
                $errors[] = "Slot #{$i}: " . $e->getMessage();
            }
        }
        return ['added'=>$added,'skipped'=>$skipped,'errors'=>$errors,'size'=>self::$tenantPoolSize];
    }

    public static function getPoolStats(): array {
        try {
            $supPdo = self::getSupervisorConnection();
            self::ensurePoolTableExists($supPdo);
            $total    = (int)$supPdo->query("SELECT COUNT(*) FROM db_pool")->fetchColumn();
            $assigned = (int)$supPdo->query("SELECT COUNT(*) FROM db_pool WHERE is_assigned=1")->fetchColumn();
            $entries  = $supPdo->query(
                "SELECT p.*, ip.institution_name
                 FROM db_pool p LEFT JOIN institution_profile ip ON p.school_id=ip.id
                 ORDER BY p.pool_index ASC"
            )->fetchAll(PDO::FETCH_ASSOC);
            return ['total'=>$total,'assigned'=>$assigned,'available'=>$total-$assigned,'entries'=>$entries];
        } catch (PDOException $e) {
            return ['total'=>0,'assigned'=>0,'available'=>0,'entries'=>[]];
        }
    }

    public static function recycleTenantDatabase($schoolId): array {
        try {
            $supPdo = self::getSupervisorConnection();
            $stmt = $supPdo->prepare("SELECT id,db_name,db_user,db_pass,db_host FROM db_pool WHERE school_id=? LIMIT 1");
            $stmt->execute([$schoolId]);
            $slot = $stmt->fetch();
            if ($slot) {
                try {
                    $tp = new PDO(
                        "mysql:host={$slot['db_host']};dbname={$slot['db_name']};charset=utf8mb4",
                        $slot['db_user'], $slot['db_pass'],
                        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
                    );
                    $tables = $tp->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
                    $tp->exec("SET FOREIGN_KEY_CHECKS=0");
                    foreach ($tables as $t) $tp->exec("DROP TABLE IF EXISTS `{$t}`");
                    $tp->exec("SET FOREIGN_KEY_CHECKS=1");
                } catch (PDOException $ignored) {}
                $supPdo->prepare("UPDATE db_pool SET is_assigned=0,school_id=NULL,assigned_at=NULL WHERE id=?")
                       ->execute([$slot['id']]);
            }
            return ['success'=>true];
        } catch (Exception $e) {
            return ['success'=>false,'message'=>$e->getMessage()];
        }
    }

    public static function backupTenantDatabase($schoolId): array {
        try {
            $pdo = self::getTenantConnection($schoolId);
            $dbName = $pdo->query("SELECT DATABASE()")->fetchColumn();
            $sql = "-- RosmonSMS Backup | {$dbName} | " . date('Y-m-d H:i:s') . "\n\n";
            $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
            foreach ($tables as $table) {
                $row = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch(PDO::FETCH_ASSOC);
                $key = array_key_exists('Create Table',$row)?'Create Table':array_keys($row)[1];
                $sql .= "DROP TABLE IF EXISTS `{$table}`;\n{$row[$key]};\n\n";
                $rows = $pdo->query("SELECT * FROM `{$table}`")->fetchAll(PDO::FETCH_ASSOC);
                foreach ($rows as $r) {
                    $cols = '`'.implode('`,`',array_keys($r)).'`';
                    $vals = implode(',',array_map(fn($v)=>$v===null?'NULL':$pdo->quote((string)$v),array_values($r)));
                    $sql .= "INSERT INTO `{$table}` ({$cols}) VALUES ({$vals});\n";
                }
                if (!empty($rows)) $sql .= "\n";
            }
            return ['success'=>true,'sql'=>$sql,'filename'=>"backup_{$dbName}_".date('Ymd_His').".sql"];
        } catch (Exception $e) {
            return ['success'=>false,'message'=>$e->getMessage()];
        }
    }

    public static function resetTenantDatabase($schoolId): array {
        try {
            $pdo = self::getTenantConnection($schoolId);
            $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
            $pdo->exec("SET FOREIGN_KEY_CHECKS=0");
            foreach ($tables as $t) $pdo->exec("DROP TABLE IF EXISTS `{$t}`");
            $pdo->exec("SET FOREIGN_KEY_CHECKS=1");
            $import = self::importTenantSchema($pdo);
            return $import['success']
                ? ['success'=>true,'message'=>'Database reset successfully.']
                : ['success'=>false,'message'=>$import['message']];
        } catch (Exception $e) {
            return ['success'=>false,'message'=>$e->getMessage()];
        }
    }

    public static function purgeSchoolSupervisorData($schoolId): array {
        try {
            $supPdo = self::getSupervisorConnection();
            $supPdo->beginTransaction();
            $supPdo->prepare("DELETE FROM licenses WHERE user_id=?")->execute([$schoolId]);
            $supPdo->prepare("DELETE FROM users WHERE tenant_id=?")->execute([$schoolId]);
            $supPdo->prepare("DELETE FROM institution_profile WHERE id=?")->execute([$schoolId]);
            $supPdo->commit();
            return ['success'=>true];
        } catch (Exception $e) {
            if ($supPdo->inTransaction()) $supPdo->rollBack();
            return ['success'=>false,'message'=>$e->getMessage()];
        }
    }

    public static function ensurePoolTableExists(PDO $pdo): void {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `db_pool` (
              `id`          INT          NOT NULL AUTO_INCREMENT,
              `pool_index`  INT          NOT NULL DEFAULT 0,
              `db_name`     VARCHAR(120) NOT NULL,
              `db_user`     VARCHAR(100) NOT NULL,
              `db_pass`     VARCHAR(100) NOT NULL,
              `db_host`     VARCHAR(100) NOT NULL DEFAULT 'localhost',
              `is_assigned` TINYINT(1)   NOT NULL DEFAULT 0,
              `school_id`   INT          DEFAULT NULL,
              `assigned_at` DATETIME     DEFAULT NULL,
              `created_at`  DATETIME     DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              UNIQUE KEY `uq_db_name` (`db_name`),
              KEY `idx_pool_assign` (`is_assigned`,`pool_index`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public static function runSqlFile(PDO $pdo, string $filePath): void {
        $sql = file_get_contents($filePath);
        $sql = preg_replace('/--[^\n]*\n/m', "\n", $sql);
        $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);
        foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
            try { $pdo->exec($stmt); }
            catch (PDOException $e) {
                if ($e->getCode() != '42S01') throw $e;
            }
        }
    }

    public static function getCurrentInstituteId(): ?int {
        return isset($_SESSION['school_id']) ? (int)$_SESSION['school_id'] : null;
    }

    public static function getSupervisorCredentials(): array {
        self::loadConfig();
        return ['host'=>self::$host,'user'=>self::$user,'pass'=>self::$pass,'dbname'=>self::getSupervisorDbName()];
    }

    private static function dbErrorHtml(string $dbName, string $msg): string {
        return "<div style='font-family:sans-serif;padding:40px;text-align:center;'>"
             . "<h2 style='color:#dc2626;'>&#9888; Database Connection Failed</h2>"
             . "<p>Could not connect to: <code>".htmlspecialchars($dbName)."</code></p>"
             . "<p style='color:#6b7280;font-size:14px;'>".htmlspecialchars($msg)."</p>"
             . "<hr><p style='font-size:13px;'>Check <code>config/env.php</code>.</p></div>";
    }
}
