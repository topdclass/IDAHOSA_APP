<?php

/**
 * Front Controller for RosmonSMS PHP Core
 */
session_start();

define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');
define('CONFIG_PATH', ROOT_PATH . '/config');
$base = trim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
define('WEB_ROOT', $base === '' ? '' : '/' . $base);

// Autoloader fallback (since we are using core PHP)
spl_autoload_register(function ($class) {
    // Convert namespace to full file path
    $class = str_replace('\\', '/', $class);
    $file = ROOT_PATH . '/' . $class . '.php';
    if (file_exists($file)) {
        require $file;
    }
});

// Simple router
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])); // /path/to/public
$appBase = dirname($scriptDir); // /path/to
if ($appBase === '/' || $appBase === '\\') $appBase = '';
if (strpos($requestUri, $appBase) === 0) {
    $requestUri = substr($requestUri, strlen($appBase));
}
if (empty($requestUri)) $requestUri = '/';
$method = $_SERVER['REQUEST_METHOD'];

// Custom defined routes (API & Standalone Pages)
require CONFIG_PATH . '/routes.php';
// Note: matched routes within routes.php call `exit;`, unmatched routes fall through to dashboard routing below.

// Logout Utility
if (strpos($requestUri, '/logout') !== false) {
    session_destroy();
    header("Location: " . WEB_ROOT . "/");
    exit;
}

// Global License & Session Context
$isExpired = false;
$isRevoked = false;
$expirationDate = 'N/A';
$daysRemaining = 0;

if (isset($_SESSION['user_id'])) {
    require_once ROOT_PATH . '/config/database.php';
    // $supervisorPdo and $pdo are now set by database.php
    // $supervisorPdo = always the central supervisor DB
    // $pdo           = school's private DB (or supervisor if super_admin)

    $uid    = $_SESSION['user_id'];
    $instId = $_SESSION['school_id'] ?? null;

    // If school_id not in session, resolve from supervisor users table
    if (!$instId) {
        try {
            $uQ = $supervisorPdo->prepare("SELECT tenant_id FROM users WHERE id = ? LIMIT 1");
            $uQ->execute([$uid]);
            $row = $uQ->fetch(PDO::FETCH_ASSOC);
            if ($row && $row['tenant_id']) {
                $instId = (int) $row['tenant_id'];
                $_SESSION['school_id'] = $instId;
            }
        } catch (PDOException $ignored) {}
    }

    // Fetch institution branding from SUPERVISOR DB
    $globalSchoolName = 'Rosmon International School';
    $globalSchoolType = 'Institute';
    $globalSchoolLogo = null;
    if ($instId) {
        try {
            $checkSu = $supervisorPdo->query("SHOW COLUMNS FROM `institution_profile` LIKE 'logo_url'")->fetch();
            if (!$checkSu) {
                $supervisorPdo->exec("ALTER TABLE `institution_profile` ADD `logo_url` VARCHAR(255) DEFAULT NULL");
            }

            $instQ = $supervisorPdo->prepare(
                "SELECT institution_name, address as institution_type, logo_url FROM institution_profile WHERE id = ?"
            );
            $instQ->execute([$instId]);
            $instData = $instQ->fetch(PDO::FETCH_ASSOC);
            if ($instData) {
                $globalSchoolName = !empty($instData['institution_name']) ? $instData['institution_name'] : $globalSchoolName;
                $globalSchoolType = !empty($instData['institution_type']) ? $instData['institution_type'] : $globalSchoolType;
                $globalSchoolLogo = !empty($instData['logo_url']) ? $instData['logo_url'] : null;
            }
        } catch (PDOException $ignored) {}
    }

    // Check license from SUPERVISOR DB
    // licenses.user_id = institution_profile.id (the school's ID)
    try {
        $stmt = $supervisorPdo->prepare(
            "SELECT status, end_date FROM licenses 
             WHERE (user_id = :uid OR user_id = :instId) AND is_deleted = 0 
             ORDER BY end_date DESC LIMIT 1"
        );
        $stmt->execute([':uid' => $uid, ':instId' => $instId]);
        $lic = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($lic && !empty($lic['end_date']) && $lic['end_date'] !== '0000-00-00') {
            $expirationDate = $lic['end_date'];
            $expTimestamp   = strtotime($lic['end_date'] . ' 23:59:59');
            $daysRemaining  = (int) ceil(($expTimestamp - time()) / (60 * 60 * 24));
            if ($daysRemaining < 0) $daysRemaining = 0;
            if ($expTimestamp < time()) $isExpired = true;
            if (($lic['status'] ?? 'Active') === 'Revoked') $isRevoked = true;
        }
    } catch (PDOException $ignored) {}
}

// Redirect if expired or revoked (for non-super-admins and non-api)
$isBlocked = ($isExpired || $isRevoked);
if ($isBlocked && isset($_SESSION['role']) && $_SESSION['role'] !== 'super_admin' && strpos($requestUri, '/api/') === false) {
     // Display Suspension/Expiration Notice
     echo "<div style='height:100vh; display:flex; flex-direction:column; align-items:center; justify-content:center; font-family:sans-serif; background:#f9fafb; color:#111827; text-align:center; padding:20px;'>";
     echo "<div style='width:80px; height:80px; background:#fee2e2; color:#ef4444; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:40px; margin-bottom:24px;'>!</div>";
     echo "<h1 style='font-size:24px; font-weight:800; margin-bottom:12px;'>".($isRevoked ? "School Access Suspended" : "License Expired")."</h1>";
     echo "<p style='color:#6b7280; max-width:400px; line-height:1.6;'>".($isRevoked ? "Your school's access license has been revoked by the system administrator. Please contact your administrator to resolve this." : "Your trial or subscription period has ended. Access to dashboard is restricted until renewal.")."</p>";
     echo "<a href='".WEB_ROOT."/logout' style='margin-top:24px; color:#13198f; font-weight:700; text-decoration:none;'>&larr; Back to Login</a>";
     echo "</div>";
     exit;
}

// Route mapping
if (strpos($requestUri, '/subject-teacher/dashboard') !== false) {
    require APP_PATH . '/Views/report_cards/subject_teacher/dashboard.php';
} else if (strpos($requestUri, '/class-teacher/dashboard') !== false) {
    require APP_PATH . '/Views/report_cards/class_teacher/dashboard.php';
} else if (strpos($requestUri, '/super-admin/login') !== false || $requestUri === '/super-admin/login') {
    require APP_PATH . '/Views/auth/super_admin_login.php';
} else if (strpos($requestUri, '/super-admin/') !== false) {
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin') { $base = dirname($_SERVER['SCRIPT_NAME']); header("Location: $base/"); exit; }
    
    if (strpos($requestUri, '/super-admin/schools/view') !== false) {
        require APP_PATH . '/Views/super_admin/schools/view.php';
    } else {
        require APP_PATH . '/Views/super_admin/dashboard.php';
    }
} else if (strpos($requestUri, '/school-admin/') !== false) {
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'school_admin') { $base = dirname($_SERVER['SCRIPT_NAME']); header("Location: $base/"); exit; }
    
    // Dynamic Routing for School Admin
    $baseAdmin = '/school-admin/';
    $pos = strpos($requestUri, $baseAdmin);
    $route = substr($requestUri, $pos + strlen($baseAdmin));
    $route = trim($route, '/');
    
    $viewPath = APP_PATH . '/Views/school_admin/';
    
    if (empty($route) || $route === 'dashboard') {
        require $viewPath . 'dashboard.php';
    } else {
        $parts = explode('/', $route);
        $file = null;
        if ($parts[0] === 'bulk-upload' || $parts[0] === 'bulk_upload') {
            if (!isset($parts[1]) || $parts[1] === '') {
                $file = $viewPath . 'bulk_upload/index.php';
            } else if ($parts[1] === 'templates') {
                $file = $viewPath . 'bulk_upload/templates.php';
            } else {
                $file = $viewPath . 'bulk_upload/index.php';
            }
        } else if ($parts[0] === 'messaging') {
            if (isset($parts[1])) {
                if ($parts[1] === 'chat') $file = $viewPath . 'messaging/chat_room.php';
                else if ($parts[1] === 'direct') $file = $viewPath . 'messaging/direct_messaging.php';
                else if ($parts[1] === 'events') $file = $viewPath . 'messaging/events_calendar.php';
            }
        } else if ($parts[0] === 'timetable') {
            if (isset($parts[1])) {
                if (in_array($parts[1], ['weekdays', 'periods', 'rooms'])) $file = $viewPath . 'timetable/manage_resources.php';
                else if ($parts[1] === 'create') $file = $viewPath . 'timetable/create_timetable.php';
            }
        } else if ($parts[0] === 'exams' && isset($parts[1]) && $parts[1] === 'results') {
            $file = $viewPath . 'exams/result_card.php';
        }

        if (!$file) {
            $potentialFile = $viewPath . str_replace('-', '_', implode('/', $parts)) . '.php';
            if (file_exists($potentialFile)) $file = $potentialFile;
            else if (count($parts) == 2) {
                $altFile = $viewPath . $parts[0] . '/' . $parts[1] . '_' . rtrim($parts[0], 's') . '.php';
                if (file_exists($altFile)) $file = $altFile;
                else { $simpleFile = $viewPath . str_replace('-', '_', $route) . '.php'; if (file_exists($simpleFile)) $file = $simpleFile; }
            } else { $simpleFile = $viewPath . str_replace('-', '_', $route) . '.php'; if (file_exists($simpleFile)) $file = $simpleFile; }
        }

        if ($file && file_exists($file)) require $file;
        else require $viewPath . 'dashboard.php';
    }
} else if (strpos($requestUri, '/employee/') !== false) {
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'employee') { $base = dirname($_SERVER['SCRIPT_NAME']); header("Location: $base/"); exit; }
    
    $baseEmp = '/employee/';
    $pos = strpos($requestUri, $baseEmp);
    $route = substr($requestUri, $pos + strlen($baseEmp));
    $route = trim($route, '/');
    
    $viewPath = APP_PATH . '/Views/employee/';
    
    if (empty($route) || $route === 'dashboard') {
        require $viewPath . 'dashboard.php';
    } else {
        $parts = explode('/', $route);
        $file = null;
        
        $potentialFile = $viewPath . str_replace('-', '_', implode('/', $parts)) . '.php';
        if (file_exists($potentialFile)) {
            $file = $potentialFile;
        } else if (count($parts) == 1) {
            $simpleFile = $viewPath . $parts[0] . '.php'; 
            if (file_exists($simpleFile)) $file = $simpleFile;
        } else if (count($parts) == 2) {
            $altFile = $viewPath . $parts[0] . '/' . $parts[1] . '.php';
            if (file_exists($altFile)) $file = $altFile;
        }

        if ($file && file_exists($file)) require $file;
        else require $viewPath . 'dashboard.php';
    }
} else if (strpos($requestUri, '/student/') !== false) {
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'student') { $base = dirname($_SERVER['SCRIPT_NAME']); header("Location: $base/"); exit; }
    
    $baseSt = '/student/';
    $pos = strpos($requestUri, $baseSt);
    $route = substr($requestUri, $pos + strlen($baseSt));
    $route = trim($route, '/');
    
    $viewPath = APP_PATH . '/Views/student/';
    
    if (empty($route) || $route === 'dashboard') {
        require $viewPath . 'dashboard.php';
    } else {
        $file = $viewPath . str_replace('-', '_', $route) . '.php';
        if (file_exists($file)) require $file;
        else require $viewPath . 'dashboard.php';
    }
} else if (strpos($requestUri, '/parent/') !== false) {
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'parent') { $base = dirname($_SERVER['SCRIPT_NAME']); header("Location: $base/"); exit; }
    
    $basePr = '/parent/';
    $pos = strpos($requestUri, $basePr);
    $route = substr($requestUri, $pos + strlen($basePr));
    $route = trim($route, '/');
    
    $viewPath = APP_PATH . '/Views/parent/';
    
    if (empty($route) || $route === 'dashboard') {
        require $viewPath . 'dashboard.php';
    } else {
        $file = $viewPath . str_replace('-', '_', $route) . '.php';
        if (file_exists($file)) require $file;
        else require $viewPath . 'dashboard.php';
    }
} else if (strpos($requestUri, '/principal/') !== false || strpos($requestUri, '/vice-principal/') !== false) {
    $r = $_SESSION['role'] ?? '';
    if ($r !== 'principal' && $r !== 'vice_principal') { $base = dirname($_SERVER['SCRIPT_NAME']); header("Location: $base/"); exit; }
    
    $viewPath = APP_PATH . '/Views/principal/';
    if ($r === 'vice_principal') $viewPath = APP_PATH . '/Views/vice_principal/';
    
    // Ensure directories exist or fallback to principal
    if (!is_dir($viewPath)) $viewPath = APP_PATH . '/Views/principal/';

    if (strpos($requestUri, 'dashboard') !== false || substr($requestUri, -1) === '/') {
        require $viewPath . 'dashboard.php';
    } else {
        // Simple routing for other principal pages
        $parts = explode('/', trim($requestUri, '/'));
        $file = end($parts) . '.php';
        if (file_exists($viewPath . $file)) require $viewPath . $file;
        else require $viewPath . 'dashboard.php';
    }
} else if (strpos($requestUri, '/audit/') !== false) {
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'audit') { $base = dirname($_SERVER['SCRIPT_NAME']); header("Location: $base/"); exit; }
    require APP_PATH . '/Views/audit/dashboard.php';
} else if (strpos($requestUri, '/pta-chairman/') !== false) {
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'pta_chairman') { $base = dirname($_SERVER['SCRIPT_NAME']); header("Location: $base/"); exit; }
    require APP_PATH . '/Views/pta_chairman/dashboard.php';
} else if (strpos($requestUri, '/finance/') !== false) {
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'finance') { $base = dirname($_SERVER['SCRIPT_NAME']); header("Location: $base/"); exit; }
    
    $viewPath = APP_PATH . '/Views/finance/';
    if (strpos($requestUri, 'dashboard') !== false || substr($requestUri, -1) === '/') {
        require $viewPath . 'dashboard.php';
    } else {
        // Simple routing for other finance pages
        $parts = explode('/', trim($requestUri, '/'));
        $file = str_replace('-', '_', end($parts)) . '.php';
        if (file_exists($viewPath . $file)) require $viewPath . $file;
        else require $viewPath . 'dashboard.php';
    }
} else if (strpos($requestUri, '/login') !== false || $requestUri === '/' || $requestUri === '' || str_ends_with($requestUri, '/public/') || str_ends_with($requestUri, 'index.php')) {
    require APP_PATH . '/Views/auth/login.php';
} else {
    require APP_PATH . '/Views/auth/login.php';
}
