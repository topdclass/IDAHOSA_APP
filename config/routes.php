<?php

/**
 * Basic Application Routes
 */

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])); // /path/to/public
$appBase = dirname($scriptDir); // /path/to
if ($appBase === '/' || $appBase === '\\') $appBase = '';
if (strpos($uri, $appBase) === 0) {
    $uri = substr($uri, strlen($appBase));
}
if (empty($uri)) $uri = '/';
$method = $_SERVER['REQUEST_METHOD'];

// Get Started Registration page
if ($uri === '/get-started') {
    require_once __DIR__ . '/../app/Views/public/get_started.php';
    exit;
}

if ($uri === '/finance/income') {
    require_once __DIR__ . '/../app/Views/finance/income.php';
    exit;
}

if ($uri === '/finance/expenses') {
    require_once __DIR__ . '/../app/Views/finance/expenses.php';
    exit;
}

if ($uri === '/finance/profit-loss') {
    require_once __DIR__ . '/../app/Views/finance/profit_loss.php';
    exit;
}

if ($uri === '/finance/debtors') {
    require_once __DIR__ . '/../app/Views/finance/debtors.php';
    exit;
}

if ($uri === '/finance/balance-sheet') {
    require_once __DIR__ . '/../app/Views/finance/balance_sheet.php';
    exit;
}

if ($uri === '/academic/results-workflow') {
    require_once __DIR__ . '/../app/Views/academic/results_workflow.php';
    exit;
}

if ($uri === '/academic/cbt-examiner') {
    require_once __DIR__ . '/../app/Views/academic/cbt_examiner.php';
    exit;
}

if ($uri === '/academic/attendance') {
    require_once __DIR__ . '/../app/Views/academic/attendance.php';
    exit;
}

if ($uri === '/student/cbt-dashboard') {
    require_once __DIR__ . '/../app/Views/academic/cbt_student_dashboard.php';
    exit;
}

if ($uri === '/student/cbt-exam') {
    require_once __DIR__ . '/../app/Views/academic/cbt_take_exam.php';
    exit;
}

if ($uri === '/api/register-school' && $method === 'POST') {
    $school_name = $_POST['school_name'] ?? '';
    $admin_name = $_POST['admin_name'] ?? '';
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $package = $_POST['package'] ?? 'Basic';

    require_once __DIR__ . '/tenant_manager.php';
    $pdo = TenantManager::getTenantConnection(); // Supervisor DB
    
    $stmt = $pdo->prepare("INSERT INTO institution_profile (institution_name, admin_name, contact_email, contact_phone, package, status) VALUES (?, ?, ?, ?, ?, 'Pending')");
    $stmt->execute([$school_name, $admin_name, $email, $phone, $package]);

    $base = dirname($_SERVER['SCRIPT_NAME']);
    if ($base === '/' || $base === '\\') $base = '';
    
    header("Location: $base/get-started?success=1");
    exit;
}

// A very naive router to demonstrate wiring the new Controller
if (strpos($uri, '/api/report-cards') === 0) {
    require_once __DIR__ . '/../app/Http/Controllers/ReportCardController.php';
    $controller = new \App\Http\Controllers\ReportCardController();
    
    // Mock user for testing the route natively
    $request = [
        'user' => [
            'id' => 1,
            'role' => ['name' => 'Principal']
        ],
        'body' => json_decode(file_get_contents('php://input'), true)
    ];

    if ($method === 'GET' && $uri === '/api/report-cards') {
        $controller->find($request);
    } 
    // Handle subject teacher update: PUT /api/report-cards/subject-teacher/update/:id
    else if ($method === 'PUT' && preg_match('#^/api/report-cards/subject-teacher/update/(\d+)$#', $uri, $matches)) {
        $controller->subjectTeacherUpdate($request, $matches[1]);
    }
    // Handle principal approve: PUT /api/report-cards/principal/approve/:id
    else if ($method === 'PUT' && preg_match('#^/api/report-cards/principal/approve/(\d+)$#', $uri, $matches)) {
        $controller->principalApprove($request, $matches[1]);
    }
}

// ── MOBILE API ROUTES ────────────────────────────────────────────────
if (strpos($uri, '/api/mobile/') === 0) {
    require_once __DIR__ . '/../app/Http/Controllers/MobileApiController.php';
    $mobileController = new \App\Http\Controllers\MobileApiController();

    if ($uri === '/api/mobile/login' && $method === 'POST') {
        $mobileController->login();
        exit;
    }

    if ($uri === '/api/mobile/dashboard' && $method === 'GET') {
        $mobileController->dashboard();
        exit;
    }

    if ($uri === '/api/mobile/attendance' && $method === 'GET') {
        $mobileController->getAttendance();
        exit;
    }

    if ($uri === '/api/mobile/attendance/scan' && $method === 'POST') {
        $mobileController->scanAttendance();
        exit;
    }
    
    // Add more mobile routes here as needed
}

if ($uri === '/report-card/view') {
    $id = $_GET['id'] ?? null;
    if ($id) {
        require_once __DIR__ . '/../app/Views/report_cards/view.php';
        exit;
    }
}


// QR Attendance Scanning API
if ($uri === '/api/attendance/scan') {
    $token = $_GET['token'] ?? '';
    require_once __DIR__ . '/tenant_manager.php';
    
    $school_id = $_GET['sid'] ?? ($_SESSION['school_id'] ?? null);
    
    if (!$school_id || !$token) {
        die(json_encode(['status' => 'error', 'message' => 'Missing token or school ID']));
    }

    $pdo = TenantManager::getTenantConnection($school_id);
    
    // 1. Try finding student by QR token
    $studStmt = $pdo->prepare("SELECT student_id, student_no FROM institute_students WHERE qr_token = ? AND is_deleted = 0");
    $studStmt->execute([$token]);
    $student = $studStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($student) {
        require_once __DIR__ . '/../app/Domain/AttendanceService.php';
        $attendanceService = new \App\Domain\AttendanceService($school_id);
        
        $date = date('Y-m-d');
        $checkStmt = $pdo->prepare("SELECT clock_in FROM student_attendance_logs WHERE student_id = ? AND attendance_date = ?");
        $checkStmt->execute([$student['student_id'], $date]);
        $log = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$log || empty($log['clock_in']) || $log['clock_in'] == '00:00:00') {
            $attendanceService->markCheckIn($student['student_id']);
            echo json_encode(['status' => 'success', 'message' => 'Check-in Recorded', 'student' => $student['student_no']]);
        } else {
            $attendanceService->markCheckOut($student['student_id']);
            echo json_encode(['status' => 'success', 'message' => 'Check-out Recorded', 'student' => $student['student_no']]);
        }
        exit;
    }

    // 2. Fallback: Find employee
    $empStmt = $pdo->prepare("SELECT e.id, u.full_name FROM employees e JOIN users u ON e.user_id = u.id WHERE e.qr_token = ?");
    $empStmt->execute([$token]);
    $emp = $empStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($emp) {
        $date = date('Y-m-d');
        $time = date('H:i:s');
        $pdo->prepare("INSERT INTO employee_attendance (employee_id, date, time_in) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE time_out = ?")
            ->execute([$emp['id'], $date, $time, $time]);
        echo json_encode(['status' => 'success', 'message' => "Hello {$emp['full_name']}, Scan Recorded"]);
        exit;
    }

    die(json_encode(['status' => 'error', 'message' => 'Invalid or Unknown Token']));
}

if (strpos($uri, '/api/login') !== false && $method === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    // Normalize UI role
    $selectedRole = strtolower($_POST['role'] ?? '');
    if ($selectedRole === 'school') $selectedRole = 'school_admin';

    $base = dirname($_SERVER['SCRIPT_NAME']);
    if ($base === '/' || $base === '\\') $base = '';

    require_once __DIR__ . '/tenant_manager.php';
    $pdo = TenantManager::getTenantConnection(); // Supervisor DB

    // 1. Fetch user by username or email regardless of role first to check existence
    $stmt = $pdo->prepare("SELECT * FROM users WHERE (username = ? OR email = ?)");
    $stmt->execute([$username, $username]);
    $userRecord = $stmt->fetch(PDO::FETCH_ASSOC);

    $validPass = false;
    if ($userRecord) {
        // 2. Verify Password
        if (password_verify($password, $userRecord['password'] ?? '')) {
            $validPass = true;
        } elseif (($userRecord['password'] ?? '') === $password) {
            $validPass = true;
        }

        if ($validPass) {
            // 3. STRICT ROLE VALIDATION
            // Get formal role from DB
            $dbRole = strtolower(str_replace(' ', '_', $userRecord['role'] ?? ''));
            
            // Validate that the selected role matches the DB role
            if ($selectedRole !== '' && $selectedRole !== $dbRole) {
                // Security Alert: User trying to login as a different role
                echo "<script>alert('Unauthorized: Your credentials do not grant access to the selected portal.'); window.location.href='$base/';</script>";
                exit;
            }

            // 4. Standard Session Initiation
            $_SESSION['user_id'] = $userRecord['id'];
            $_SESSION['username'] = $userRecord['username'] ?: $userRecord['full_name'];
            $_SESSION['role'] = $dbRole;

            if (isset($userRecord['tenant_id'])) {
                $_SESSION['school_id'] = $userRecord['tenant_id'];
            }
        }
    } 

    // Legacy License Credentials Fallback (Strictly for School Admin)
    if (!$validPass && $selectedRole === 'school_admin') {
        $licStmt = $pdo->prepare("SELECT * FROM licenses WHERE admin_username = ? AND admin_password = ? AND status = 'Active'");
        $licStmt->execute([$username, $password]);
        $licRecord = $licStmt->fetch(PDO::FETCH_ASSOC);

        if ($licRecord) {
            $uStmt = $pdo->prepare("SELECT * FROM users WHERE tenant_id = ? AND role = 'school_admin'");
            $uStmt->execute([$licRecord['user_id']]);
            $userRecord = $uStmt->fetch(PDO::FETCH_ASSOC);

            if ($userRecord) {
                $_SESSION['user_id'] = $userRecord['id'];
                $_SESSION['username'] = $userRecord['username'] ?: $userRecord['full_name'];
                $_SESSION['role'] = 'school_admin';
                $_SESSION['school_id'] = $userRecord['tenant_id'];
                $validPass = true;
            }
        }
    }

    if ($validPass) {
        // Enforce license check for all non-super-admins
        // licenses table is always in the supervisor DB
        if ($_SESSION['role'] !== 'super_admin' && isset($_SESSION['school_id'])) {
            $licStmt = $pdo->prepare("SELECT status, end_date FROM licenses WHERE user_id = ? AND is_deleted = 0 ORDER BY id DESC LIMIT 1");
            $licStmt->execute([$_SESSION['school_id']]);
            $licenseData = $licStmt->fetch(PDO::FETCH_ASSOC);

            if (!$licenseData || $licenseData['status'] !== 'Active') {
                session_destroy();
                $statusMsg = $licenseData ? $licenseData['status'] : 'Unlicensed';
                echo "<script>alert('Your school account is currently: $statusMsg. Please contact the administrator.'); window.location.href='$base/';</script>";
                exit;
            }
        }

        $dashboardMap = [
            'super_admin'    => '/super-admin/dashboard',
            'school_admin'   => '/school-admin/dashboard',
            'employee'       => '/employee/dashboard',
            'student'        => '/student/dashboard',
            'parent'         => '/parent/dashboard',
            'finance'        => '/finance/dashboard',
            'principal'      => '/principal/dashboard',
            'vice_principal' => '/vice-principal/dashboard',
            'audit'          => '/audit/dashboard',
            'pta_chairman'   => '/pta-chairman/dashboard',
        ];
        
        $target = $dashboardMap[$_SESSION['role']] ?? '/';
        header("Location: $base$target");
        exit;
    } 

    echo "<script>alert('Invalid credentials or unauthorized role access.'); window.location.href='$base/';</script>";
    exit;
}

// ── BULK UPLOAD ROUTES ────────────────────────────────────────────────
if ($uri === '/school-admin/bulk-upload' || $uri === '/school-admin/bulk-upload/') {
    require_once __DIR__ . '/../app/Views/school_admin/bulk_upload/index.php';
    exit;
}

if ($uri === '/school-admin/bulk-upload/templates') {
    require_once __DIR__ . '/../app/Views/school_admin/bulk_upload/templates.php';
    exit;
}

// ── API: POOL STATUS (Super Admin AJAX) ───────────────────────────────
if ($uri === '/api/pool-status' && $method === 'GET') {
    header('Content-Type: application/json');
    require_once __DIR__ . '/tenant_manager.php';
    echo json_encode(TenantManager::getPoolStats());
    exit;
}

// ── API: PROVISION SCHOOL (Super Admin AJAX) ─────────────────────────
if ($uri === '/api/provision-school' && $method === 'POST') {
    header('Content-Type: application/json');
    require_once __DIR__ . '/tenant_manager.php';
    $schoolId = (int)($_POST['school_id'] ?? 0);
    if (!$schoolId) {
        echo json_encode(['success'=>false,'message'=>'school_id required']);
        exit;
    }
    echo json_encode(TenantManager::createTenantDatabase($schoolId));
    exit;
}

// ── API: BACKUP TENANT DB ─────────────────────────────────────────────
if ($uri === '/api/backup-tenant' && $method === 'POST') {
    require_once __DIR__ . '/tenant_manager.php';
    $schoolId = (int)($_POST['school_id'] ?? 0);
    $res = TenantManager::backupTenantDatabase($schoolId);
    if ($res['success']) {
        header('Content-Type: application/sql');
        header('Content-Disposition: attachment; filename="'.$res['filename'].'"');
        echo $res['sql'];
        exit;
    }
    header('Content-Type: application/json');
    echo json_encode($res);
    exit;
}
