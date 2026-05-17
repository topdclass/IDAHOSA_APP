<?php
/**
 * Role & Privilege Guard
 * =======================
 * Verifies if the currently logged-in user has the required capability
 * based on their institutional role and assigned permissions.
 */

if (!function_exists('hasPermission')) {
    function hasPermission($perm_key) {
        global $pdo, $supervisorPdo;

        // 1. Authentication Check
        if (!isset($_SESSION['user_id'])) return false;

        // 2. Global Power Checks
        if (isset($_SESSION['role'])) {
            if ($_SESSION['role'] === 'super_admin') return true;
            if ($_SESSION['role'] === 'school_admin' || $_SESSION['role'] === 'institute_admin') return true;
        }

        // 3. Employee Permission Logic (Tenant-Scoped)
        if (isset($_SESSION['role']) && ($_SESSION['role'] === 'employee' || $_SESSION['role'] === 'teacher')) {
            $user_id = $_SESSION['user_id'];
            
            try {
                // Find role_id for this employee in the current school's database
                $stmt = $pdo->prepare("SELECT role_id FROM institute_employees WHERE employee_id = ? AND is_deleted = 0 LIMIT 1");
                $stmt->execute([$user_id]);
                $role_id = $stmt->fetchColumn();

                if (!$role_id) return false; // No custom role assigned yet

                // Check if the permission key is linked to this role
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) 
                    FROM role_permissions rp
                    JOIN permissions p ON rp.permission_id = p.id
                    WHERE rp.role_id = ? AND p.perm_key = ?
                ");
                $stmt->execute([$role_id, $perm_key]);
                return (int)$stmt->fetchColumn() > 0;

            } catch (PDOException $e) {
                // Return false silently or handle error context
                return false;
            }
        }

        return false;
    }
}

/**
 * Access Enforcement Middleware
 * =============================
 * Aborts execution and redirects if the user lacks the required privilege.
 */
if (!function_exists('requirePermission')) {
    function requirePermission($perm_key) {
        if (!hasPermission($perm_key)) {
            // Redirect to unauthorized page or dashboard with warning
            header("Location: " . WEB_ROOT . "/dashboard?error=unauthorized_access");
            exit;
        }
    }
}
