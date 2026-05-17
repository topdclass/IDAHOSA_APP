<?php

namespace App\Http\Controllers;

use PDO;
use Exception;
use TenantManager;

require_once __DIR__ . '/../../../config/tenant_manager.php';

class MobileApiController {

    private $supervisorPdo;

    public function __construct() {
        $this->supervisorPdo = TenantManager::getSupervisorConnection();
    }

    /**
     * POST /api/mobile/login
     */
    public function login() {
        header('Content-Type: application/json');
        
        $input = json_decode(file_get_contents('php://input'), true);
        $username = trim($input['username'] ?? '');
        $password = $input['password'] ?? '';
        $role = strtolower($input['role'] ?? '');

        if (empty($username) || empty($password)) {
            echo json_encode(['success' => false, 'message' => 'Username and password required']);
            return;
        }

        try {
            $stmt = $this->supervisorPdo->prepare("SELECT * FROM users WHERE (username = ? OR email = ?)");
            $stmt->execute([$username, $username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && (password_verify($password, $user['password']) || $user['password'] === $password)) {
                
                $dbRole = strtolower(str_replace(' ', '_', $user['role']));
                if (!empty($role) && $role !== $dbRole) {
                    echo json_encode(['success' => false, 'message' => 'Unauthorized role access']);
                    return;
                }

                // Generate a simple token (in production use JWT)
                $token = base64_encode(json_encode([
                    'uid' => $user['id'],
                    'tid' => $user['tenant_id'],
                    'role' => $dbRole,
                    'exp' => time() + (86400 * 30) // 30 days
                ]));

                // Get School Info
                $schoolName = "RosmonSMS";
                if ($user['tenant_id']) {
                    $instQ = $this->supervisorPdo->prepare("SELECT institution_name FROM institution_profile WHERE id = ?");
                    $instQ->execute([$user['tenant_id']]);
                    $inst = $instQ->fetch(PDO::FETCH_ASSOC);
                    if ($inst) $schoolName = $inst['institution_name'];
                }

                echo json_encode([
                    'success' => true,
                    'token' => $token,
                    'user' => [
                        'id' => $user['id'],
                        'name' => $user['full_name'],
                        'username' => $user['username'],
                        'role' => $dbRole,
                        'school_id' => $user['tenant_id'],
                        'school_name' => $schoolName
                    ]
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
        }
    }

    /**
     * Authenticate and get Tenant PDO
     */
    private function authenticate() {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        
        if (strpos($authHeader, 'Bearer ') === 0) {
            $token = substr($authHeader, 7);
            $data = json_decode(base64_decode($token), true);
            
            if ($data && isset($data['uid']) && $data['exp'] > time()) {
                return $data;
            }
        }
        
        header('HTTP/1.0 401 Unauthorized');
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }

    /**
     * GET /api/mobile/dashboard
     */
    public function dashboard() {
        header('Content-Type: application/json');
        $auth = $this->authenticate();
        $pdo = TenantManager::getTenantConnection($auth['tid']);

        $role = $auth['role'];
        $stats = [];

        try {
            if ($role === 'student') {
                $stats = $this->getStudentStats($pdo, $auth['uid']);
            } elseif ($role === 'teacher') {
                $stats = $this->getTeacherStats($pdo, $auth['uid']);
            } elseif ($role === 'school_admin' || $role === 'admin') {
                $stats = $this->getAdminStats($pdo);
            }

            echo json_encode(['success' => true, 'stats' => $stats]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * GET /api/mobile/attendance
     */
    public function getAttendance() {
        header('Content-Type: application/json');
        $auth = $this->authenticate();
        $pdo = TenantManager::getTenantConnection($auth['tid']);

        try {
            $stmt = $pdo->prepare("SELECT * FROM student_attendance_logs WHERE student_id = (SELECT id FROM institute_students WHERE user_id = ? LIMIT 1) ORDER BY attendance_date DESC LIMIT 30");
            $stmt->execute([$auth['uid']]);
            $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['success' => true, 'attendance' => $logs]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * POST /api/mobile/attendance/scan
     */
    public function scanAttendance() {
        header('Content-Type: application/json');
        $auth = $this->authenticate();
        $input = json_decode(file_get_contents('php://input'), true);
        $token = $input['qr_token'] ?? '';

        if (empty($token)) {
            echo json_encode(['success' => false, 'message' => 'QR Token required']);
            return;
        }

        $pdo = TenantManager::getTenantConnection($auth['tid']);
        
        try {
            // Verify token (simplified logic)
            $stmt = $pdo->prepare("SELECT * FROM institute_students WHERE qr_token = ? AND is_deleted = 0");
            $stmt->execute([$token]);
            $student = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($student) {
                // Log attendance
                $logStmt = $pdo->prepare("INSERT INTO student_attendance_logs (student_id, attendance_date, clock_in) VALUES (?, CURDATE(), CURTIME()) ON DUPLICATE KEY UPDATE clock_out = CURTIME()");
                $logStmt->execute([$student['id']]);
                echo json_encode(['success' => true, 'message' => 'Attendance recorded successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid QR Code']);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    private function getStudentStats($pdo, $uid) {
        // Resolve student_id from users.id
        // (Assuming institute_students has a mapping or student_id matches)
        // Check student attendance logs
        $attend = $pdo->prepare("SELECT COUNT(*) FROM student_attendance_logs WHERE student_id = (SELECT id FROM institute_students WHERE user_id = ? LIMIT 1)");
        $attend->execute([$uid]);
        $attendCount = $attend->fetchColumn();

        return [
            ['label' => 'Attendance', 'value' => $attendCount, 'icon' => 'calendar_today'],
            ['label' => 'Subjects', 'value' => 8, 'icon' => 'book'],
            ['label' => 'Fee Balance', 'value' => '₦0.00', 'icon' => 'account_balance_wallet'],
        ];
    }

    private function getTeacherStats($pdo, $uid) {
        return [
            ['label' => 'Total Classes', 'value' => 4, 'icon' => 'class'],
            ['label' => 'Total Students', 'value' => 120, 'icon' => 'people'],
            ['label' => 'Attendance Today', 'value' => '95%', 'icon' => 'check_circle'],
        ];
    }

    private function getAdminStats($pdo) {
        $studentCount = $pdo->query("SELECT COUNT(*) FROM institute_students WHERE is_deleted = 0")->fetchColumn();
        $teacherCount = $pdo->query("SELECT COUNT(*) FROM institute_employees WHERE is_deleted = 0")->fetchColumn();

        return [
            ['label' => 'Total Students', 'value' => $studentCount, 'icon' => 'school'],
            ['label' => 'Total Teachers', 'value' => $teacherCount, 'icon' => 'people'],
            ['label' => 'Revenue (Term)', 'value' => '₦2.4M', 'icon' => 'payments'],
        ];
    }
}
