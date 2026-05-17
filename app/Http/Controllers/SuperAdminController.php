<?php
/**
 * SuperAdminController — Platform Management
 * ============================================
 * Handles school approval, DB provisioning, license management, user management,
 * and global settings for the RosmonSMS SaaS platform.
 */

if (!defined('ROOT_PATH')) define('ROOT_PATH', dirname(dirname(dirname(dirname(__FILE__)))));
require_once ROOT_PATH . '/config/tenant_manager.php';

class SuperAdminController {

    private PDO $sup;

    public function __construct() {
        $this->sup = TenantManager::getSupervisorConnection();
    }

    // ── SCHOOL APPROVAL ────────────────────────────────────────────────

    /**
     * Approve a school's registration and provision its private database.
     * Called when Super Admin clicks "Approve" on a pending school.
     *
     * @param  int   $schoolId  institution_profile.id
     * @return array ['success', 'message', 'db_name']
     */
    public function approveSchool(int $schoolId): array {
        // 1. Get school details
        $stmt = $this->sup->prepare(
            "SELECT id, institution_name, email, phone FROM institution_profile WHERE id = ? LIMIT 1"
        );
        $stmt->execute([$schoolId]);
        $school = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$school) {
            return ['success' => false, 'message' => "School #{$schoolId} not found."];
        }

        // 2. Provision tenant database
        $provision = TenantManager::createTenantDatabase($schoolId);
        if (!$provision['success']) {
            return $provision;
        }
        $dbName = $provision['db_name'];

        // 3. Mark school as approved in supervisor DB
        $this->sup->prepare(
            "UPDATE institution_profile SET status='active', approved_at=NOW(), tenant_db=? WHERE id=?"
        )->execute([$dbName, $schoolId]);

        // 4. Generate + attach license key
        $licenseResult = $this->generateLicense([
            'school_id'   => $schoolId,
            'school_name' => $school['institution_name'],
            'plan_type'   => 'standard',
        ]);

        // 5. Create school_admin user in supervisor DB if not already created
        $this->ensureSchoolAdminUser($schoolId, $school);

        return [
            'success' => true,
            'message' => "School '{$school['institution_name']}' approved. DB: {$dbName}. License: {$licenseResult['license_key']}",
            'db_name' => $dbName,
            'license' => $licenseResult['license_key'] ?? null,
        ];
    }

    /**
     * Reject a school application.
     */
    public function rejectSchool(int $schoolId, string $reason = ''): array {
        $this->sup->prepare(
            "UPDATE institution_profile SET status='rejected', rejection_reason=?, rejected_at=NOW() WHERE id=?"
        )->execute([$reason, $schoolId]);
        return ['success' => true, 'message' => "School #{$schoolId} rejected."];
    }

    /**
     * Suspend a school (blocks login but keeps data).
     */
    public function suspendSchool(int $schoolId): array {
        $this->sup->prepare(
            "UPDATE institution_profile SET status='suspended', suspended_at=NOW() WHERE id=?"
        )->execute([$schoolId]);
        // Revoke license
        $this->sup->prepare(
            "UPDATE licenses SET status='suspended', updated_at=NOW() WHERE user_id=?"
        )->execute([$schoolId]);
        return ['success' => true, 'message' => "School #{$schoolId} suspended."];
    }

    /**
     * Reactivate a suspended school.
     */
    public function reactivateSchool(int $schoolId): array {
        $this->sup->prepare(
            "UPDATE institution_profile SET status='active', suspended_at=NULL WHERE id=?"
        )->execute([$schoolId]);
        $this->sup->prepare(
            "UPDATE licenses SET status='active', updated_at=NOW() WHERE user_id=?"
        )->execute([$schoolId]);
        return ['success' => true, 'message' => "School #{$schoolId} reactivated."];
    }

    // ── LICENSE MANAGEMENT ─────────────────────────────────────────────

    /**
     * Generate a license key for a school.
     */
    public function generateLicense(array $params): array {
        $schoolId   = (int)($params['school_id'] ?? 0);
        $planType   = $params['plan_type'] ?? 'standard';
        $months     = (int)($params['months'] ?? 12);
        $expiryDate = date('Y-m-d', strtotime("+{$months} months"));
        $licenseKey = strtoupper(implode('-', str_split(bin2hex(random_bytes(8)), 4)));

        // Check for existing license
        $existing = $this->sup->prepare("SELECT id FROM licenses WHERE user_id=? LIMIT 1");
        $existing->execute([$schoolId]);

        if ($existing->fetch()) {
            $this->sup->prepare(
                "UPDATE licenses SET license_key=?, plan=?, status='active', expires_at=?, updated_at=NOW()
                 WHERE user_id=?"
            )->execute([$licenseKey, $planType, $expiryDate, $schoolId]);
        } else {
            $this->sup->prepare(
                "INSERT INTO licenses (user_id, license_key, plan, status, expires_at, created_at)
                 VALUES (?,?,?,'active',?,NOW())"
            )->execute([$schoolId, $licenseKey, $planType, $expiryDate]);
        }

        return [
            'success'     => true,
            'license_key' => $licenseKey,
            'expires_at'  => $expiryDate,
            'plan'        => $planType,
        ];
    }

    /**
     * Revoke a school's license (blocks access).
     */
    public function revokeLicense(int $schoolId): array {
        $this->sup->prepare(
            "UPDATE licenses SET status='revoked', updated_at=NOW() WHERE user_id=?"
        )->execute([$schoolId]);
        return ['success' => true, 'message' => "License revoked for school #{$schoolId}."];
    }

    /**
     * Extend a license's expiry.
     */
    public function extendLicense(int $schoolId, int $months = 12): array {
        $stmt = $this->sup->prepare("SELECT expires_at FROM licenses WHERE user_id=? LIMIT 1");
        $stmt->execute([$schoolId]);
        $current = $stmt->fetchColumn();
        $base       = $current && $current > date('Y-m-d') ? $current : date('Y-m-d');
        $newExpiry  = date('Y-m-d', strtotime("+{$months} months", strtotime($base)));
        $this->sup->prepare(
            "UPDATE licenses SET expires_at=?, status='active', updated_at=NOW() WHERE user_id=?"
        )->execute([$newExpiry, $schoolId]);
        return ['success' => true, 'new_expiry' => $newExpiry];
    }

    /**
     * Get all licenses with school info.
     */
    public function getAllLicenses(): array {
        return $this->sup->query("
            SELECT l.*, ip.institution_name, ip.email as school_email, ip.status as school_status
            FROM licenses l
            LEFT JOIN institution_profile ip ON l.user_id = ip.id
            ORDER BY l.created_at DESC
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── USER MANAGEMENT ────────────────────────────────────────────────

    /**
     * List all users (all schools and super admins).
     */
    public function listAllUsers(): array {
        return $this->sup->query("
            SELECT u.id, u.full_name, u.email, u.role, u.phone, u.is_active, u.created_at,
                   ip.institution_name
            FROM users u
            LEFT JOIN institution_profile ip ON u.tenant_id = ip.id
            ORDER BY u.created_at DESC
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Toggle user active/inactive.
     */
    public function toggleUserStatus(int $userId): array {
        $stmt = $this->sup->prepare("SELECT is_active FROM users WHERE id=? LIMIT 1");
        $stmt->execute([$userId]);
        $current = (int)$stmt->fetchColumn();
        $newStatus = $current ? 0 : 1;
        $this->sup->prepare("UPDATE users SET is_active=? WHERE id=?")->execute([$newStatus, $userId]);
        return ['success' => true, 'is_active' => $newStatus];
    }

    /**
     * Reset a user's password.
     */
    public function resetUserPassword(int $userId, string $newPassword): array {
        if (strlen($newPassword) < 6) {
            return ['success' => false, 'message' => 'Password too short (min 6 chars).'];
        }
        $hashed = password_hash($newPassword, PASSWORD_DEFAULT);
        $this->sup->prepare("UPDATE users SET password=? WHERE id=?")->execute([$hashed, $userId]);
        return ['success' => true, 'message' => 'Password reset successfully.'];
    }

    // ── SCHOOL MANAGEMENT ──────────────────────────────────────────────

    /**
     * Get all schools with their DB pool status and license info.
     */
    public function getAllSchools(): array {
        return $this->sup->query("
            SELECT ip.*,
                   l.license_key, l.plan, l.status as license_status, l.expires_at,
                   dp.db_name as tenant_db_pool, dp.is_assigned,
                   u.full_name as admin_name, u.email as admin_email
            FROM institution_profile ip
            LEFT JOIN licenses l ON ip.id = l.user_id
            LEFT JOIN db_pool dp ON ip.id = dp.school_id
            LEFT JOIN users u ON u.tenant_id = ip.id AND u.role = 'school_admin'
            ORDER BY ip.created_at DESC
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get pending (not yet approved) schools.
     */
    public function getPendingSchools(): array {
        return $this->sup->query("
            SELECT ip.*, u.email as contact_email, u.phone as contact_phone
            FROM institution_profile ip
            LEFT JOIN users u ON u.tenant_id = ip.id AND u.role = 'school_admin'
            WHERE ip.status IN ('pending', 'inactive', '')
               OR ip.status IS NULL
            ORDER BY ip.created_at DESC
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Delete a school entirely (wipe DB + supervisor records).
     */
    public function deleteSchool(int $schoolId): array {
        $recycle = TenantManager::recycleTenantDatabase($schoolId);
        $purge   = TenantManager::purgeSchoolSupervisorData($schoolId);
        return [
            'success' => $recycle['success'] && $purge['success'],
            'message' => "School #{$schoolId} deleted and DB slot recycled.",
        ];
    }

    /**
     * Backup a school's database.
     */
    public function backupSchool(int $schoolId): array {
        return TenantManager::backupTenantDatabase($schoolId);
    }

    // ── GLOBAL STATS ───────────────────────────────────────────────────

    /**
     * Get platform-wide statistics for super admin dashboard.
     */
    public function getDashboardStats(): array {
        try {
            $totalSchools   = (int)$this->sup->query("SELECT COUNT(*) FROM institution_profile")->fetchColumn();
            $activeSchools  = (int)$this->sup->query("SELECT COUNT(*) FROM institution_profile WHERE status='active'")->fetchColumn();
            $pendingSchools = (int)$this->sup->query("SELECT COUNT(*) FROM institution_profile WHERE status IN ('pending','inactive') OR status IS NULL OR status=''")->fetchColumn();
            $totalUsers     = (int)$this->sup->query("SELECT COUNT(*) FROM users WHERE role != 'super_admin'")->fetchColumn();
            $activeLicenses = (int)$this->sup->query("SELECT COUNT(*) FROM licenses WHERE status='active' AND expires_at >= CURDATE()")->fetchColumn();
            $expiredLicenses= (int)$this->sup->query("SELECT COUNT(*) FROM licenses WHERE status='active' AND expires_at < CURDATE()")->fetchColumn();

            $poolStats = TenantManager::getPoolStats();

            return [
                'total_schools'    => $totalSchools,
                'active_schools'   => $activeSchools,
                'pending_schools'  => $pendingSchools,
                'total_users'      => $totalUsers,
                'active_licenses'  => $activeLicenses,
                'expired_licenses' => $expiredLicenses,
                'pool_total'       => $poolStats['total'],
                'pool_available'   => $poolStats['available'],
                'pool_assigned'    => $poolStats['assigned'],
            ];
        } catch (PDOException $e) {
            return array_fill_keys(['total_schools','active_schools','pending_schools',
                                    'total_users','active_licenses','expired_licenses',
                                    'pool_total','pool_available','pool_assigned'], 0);
        }
    }

    // ── HELPERS ────────────────────────────────────────────────────────

    private function ensureSchoolAdminUser(int $schoolId, array $school): void {
        // Check if school_admin user already exists for this school
        $chk = $this->sup->prepare(
            "SELECT id FROM users WHERE tenant_id=? AND role='school_admin' LIMIT 1"
        );
        $chk->execute([$schoolId]);
        if ($chk->fetch()) return;

        // Create one with the school's email
        $email    = $school['email'] ?? "admin_{$schoolId}@rosmonsms.local";
        $rawPass  = bin2hex(random_bytes(4)); // 8-char random password
        $hashed   = password_hash($rawPass, PASSWORD_DEFAULT);

        try {
            $this->sup->prepare(
                "INSERT INTO users (full_name, email, username, password, role, tenant_id, phone, created_at)
                 VALUES (?,?,?,?,\'school_admin\',?,?,NOW())"
            )->execute([
                $school['institution_name'] . ' Admin',
                $email, $email, $hashed, $schoolId,
                $school['phone'] ?? '',
            ]);

            // Store generated password for display/email (in session or log)
            $_SESSION['new_school_admin_pass_' . $schoolId] = $rawPass;
        } catch (PDOException $e) {
            // Email might already exist — not critical
            error_log("[SuperAdmin] ensureSchoolAdminUser failed: " . $e->getMessage());
        }
    }
}
