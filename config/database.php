<?php
/**
 * Database Bootstrap
 * ==================
 * Sets up:
 *   $supervisorPdo  — always the central supervisor DB (users, licenses, pool)
 *   $pdo            — the SCHOOL'S private DB if logged in as school/employee/student/parent
 *                     OR supervisor DB if super_admin (or before school is assigned to pool)
 *   $instituteId    — current school's ID from session (used for WHERE institute_id = ?)
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/tenant_manager.php';
require_once __DIR__ . '/auth_guard.php';

// ── SUPERVISOR connection (always available) ──────────────────────────────
$supervisorPdo = TenantManager::getSupervisorConnection();

// ── Determine active institute ────────────────────────────────────────────
$instituteId = null;

if (isset($_SESSION['role']) && $_SESSION['role'] === 'super_admin') {
    // Super admin: no school scope
    $instituteId = null;
} elseif (isset($_SESSION['school_id']) && $_SESSION['school_id']) {
    $instituteId = (int) $_SESSION['school_id'];
} elseif (isset($_SESSION['user_id'])) {
    // Fallback: resolve tenant from users table in supervisor DB
    try {
        $uQ = $supervisorPdo->prepare("SELECT tenant_id FROM users WHERE id = ? LIMIT 1");
        $uQ->execute([$_SESSION['user_id']]);
        $row = $uQ->fetch(PDO::FETCH_ASSOC);
        if ($row && $row['tenant_id']) {
            $instituteId = (int) $row['tenant_id'];
            $_SESSION['school_id'] = $instituteId;
        }
    } catch (PDOException $ignored) {}
}

// ── TENANT connection (school's private DB, or supervisor if super_admin) ─
// This is the main $pdo used by all view files.
// For school_admin/employee/student/parent → connects to their school's own DB
// For super_admin → connects to supervisor DB
$pdo = TenantManager::getTenantConnection($instituteId);

// ── Global school name (for headers/titles) ───────────────────────────────
$globalSchoolName = 'RosmonSMS';
if ($instituteId) {
    try {
        $snQ = $supervisorPdo->prepare("SELECT institution_name FROM institution_profile WHERE id=? LIMIT 1");
        $snQ->execute([$instituteId]);
        $snRow = $snQ->fetch(PDO::FETCH_ASSOC);
        if ($snRow) $globalSchoolName = $snRow['institution_name'];
    } catch (PDOException $ignored) {}
}

// ── Define APP_PATH and WEB_ROOT if not set ───────────────────────────────
if (!defined('APP_PATH')) {
    define('APP_PATH', ROOT_PATH . '/app');
}
if (!defined('WEB_ROOT')) {
    // Auto-detect base URL (works for root install and subdirectory installs)
    $scriptDir = dirname(dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    $webRoot   = rtrim($scriptDir === '/public' ? '' : $scriptDir, '/');
    define('WEB_ROOT', $webRoot);
}
