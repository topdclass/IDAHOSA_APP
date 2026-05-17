<?php
/**
 * Redesigned Student Attendance Digital Hub
 * Matching Screenshot Step 692 + Header Clock Fix
 */
require_once dirname(dirname(dirname(__DIR__))) . '/config/database.php';
$userId = $_SESSION['user_id'] ?? 0;
$today = date('Y-m-d');
$message = "";

// 1. Fetch Student/Class Info
$stmt = $pdo->prepare("SELECT u.full_name as name, c.class_name as class_name, s.student_no
                      FROM institute_students s
                      JOIN users u ON s.student_id = u.id
                      LEFT JOIN classes c ON s.class_id = c.id
                      WHERE s.student_id = ? LIMIT 1");
$stmt->execute([$userId]);
$student = $stmt->fetch();

if (!$student) {
    die("Student profile not found.");
}

// 2. Handle Clock In/Out (Direct or PIN)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $now = date('H:i:s');
    $method = $_POST['clock_method'] ?? 'direct'; // direct, pin, qr
    $pin = $_POST['pin_value'] ?? '';

    $proceed = false;
    if ($method === 'pin') {
        $pinStmt = $pdo->prepare("SELECT id FROM attendance_pins WHERE user_id = ? AND pin = ?");
        $pinStmt->execute([$userId, $pin]);
        if ($pinStmt->fetch()) {
            $proceed = true;
        } else {
            $message = "Invalid PIN. Please try again.";
        }
    } else {
        $proceed = true;
    }

    if ($proceed) {
        if (isset($_POST['clock_in'])) {
            $status = (strtotime($now) <= strtotime('08:00:00')) ? 'Present' : 'Late';
            $stmt = $pdo->prepare("INSERT INTO student_attendance_logs (student_id, attendance_date, clock_in, status) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE clock_in = IF(clock_in IS NULL, VALUES(clock_in), clock_in), status = IF(clock_in IS NULL, VALUES(status), status)");
            $stmt->execute([$userId, $today, $now, $status]);
            $_SESSION['clock_msg'] = "Successfully clocked in as ".strtoupper($status)." at $now!";
            header("Location: " . WEB_ROOT . "/student/attendance"); exit;
        } elseif (isset($_POST['clock_out'])) {
            $stmt = $pdo->prepare("UPDATE student_attendance_logs SET clock_out = ? WHERE student_id = ? AND attendance_date = ?");
            $stmt->execute([$now, $userId, $today]);
            $_SESSION['clock_msg'] = "Successfully clocked out at $now!";
            header("Location: " . WEB_ROOT . "/student/attendance"); exit;
        }
    }
}

$message = $_SESSION['clock_msg'] ?? $message;
unset($_SESSION['clock_msg']);

// 3. Fetch Records
$stmt = $pdo->prepare("SELECT * FROM student_attendance_logs WHERE student_id = ? AND attendance_date = ?");
$stmt->execute([$userId, $today]);
$attStatus = $stmt->fetch();

$recentStmt = $pdo->prepare("SELECT * FROM student_attendance_logs WHERE student_id = ? AND attendance_date = ? ORDER BY id DESC LIMIT 5");
$recentStmt->execute([$userId, $today]);
$todayActivities = $recentStmt->fetchAll();

// 4. Unread Messages for Notification
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM direct_messages WHERE receiver_id = ? AND is_read = 0");
$countStmt->execute([$userId]);
$unreadCount = $countStmt->fetchColumn();

$pageTitle = 'Student Clocking - Rosmon SMS';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <!-- Fonts & Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <style>
        :root {
            --primary: #5a57e6;
            --primary-bg: #eeeffc;
            --text-dark: #1e293b;
            --text-muted: #64748b;
            --bg-page: #f8fafc;
            --border: #e2e8f0;
            --accent-orange: #ff5e3a;
            --accent-green: #10b981;
        }

        * { box-sizing: border-box; font-family: 'Outfit', sans-serif; }
        body { margin: 0; background: var(--bg-page); color: var(--text-dark); display: flex; min-height: 100vh; overflow-x: hidden; }

        /* --- Sidebar Navigation --- */
        .sidebar {
            width: 270px;
            background: #fff;
            border-right: 1px solid var(--border);
            padding: 30px 20px;
            display: flex;
            flex-direction: column;
            position: fixed;
            height: 100vh;
            z-index: 100;
        }
        .logo-circle {
            width: 38px; height: 38px; background: #13198f;
            border-radius: 50%; display: flex; align-items: center; justify-content: center;
            color: white; font-size: 20px; margin-bottom: 25px;
        }
        .profile-card {
            background: #f1f5f9;
            padding: 20px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 30px;
        }
        .profile-avatar {
            width: 45px; height: 45px; background: #cbd5e1; border-radius: 50%;
            display: flex; align-items: center; justify-content: center; font-size: 22px; color: #64748b;
        }
        .profile-info h4 { margin: 0; font-size: 13px; font-weight: 700; color: #1e293b; line-height: 1.2; }
        .profile-info p { margin: 3px 0 0; font-size: 11px; font-weight: 600; color: #64748b; }

        .nav-menu { flex-grow: 1; }
        .nav-item {
            display: flex; align-items: center; gap: 12px; padding: 12px 16px;
            text-decoration: none; color: #64748b; border-radius: 10px;
            margin-bottom: 5px; font-weight: 600; font-size: 14px; transition: 0.2s;
        }
        .nav-item:hover { background: #f8fafc; color: var(--primary); }
        .nav-item.active { background: #eef2ff; color: var(--primary); }
        .nav-item i { font-size: 20px; }

        /* --- Main Context --- */
        .main-content {
            flex: 1;
            margin-left: 270px;
            padding: 0;
            display: flex;
            flex-direction: column;
            width: calc(100% - 270px);
        }

        /* Top Bar Header */
        .top-header {
            height: 80px;
            padding: 0 40px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #fff;
            border-bottom: 1px solid var(--border);
        }
        .header-clock {
            display: flex; align-items: center; gap: 10px;
            font-size: 22px; font-weight: 800; color: #1e1b4b;
        }
        .header-clock i { color: var(--primary); font-size: 30px; }

        .header-actions { display: flex; align-items: center; gap: 15px; }
        .notif-bell {
            position: relative; font-size: 22px; color: #94a3b8; cursor: pointer;
        }
        .notif-badge {
            position: absolute; top: -5px; right: -5px; background: #ff5e3a;
            color: white; font-size: 9px; min-width: 14px; height: 14px;
            border-radius: 50%; display: flex; align-items: center; justify-content: center;
            font-weight: 800; border: 2px solid #fff;
        }
        .user-circle {
            width: 35px; height: 35px; border-radius: 50%; background: #13198f;
            display: flex; align-items: center; justify-content: center;
            font-size: 15px; font-weight: 800; color: white;
        }

        /* Digital Clock Card */
        .clock-container {
            flex: 1;
            display: flex; align-items: flex-start; justify-content: center;
            padding-top: 60px;
        }
        .clock-card {
            background: #fff;
            width: 100%; maxWidth: 520px;
            border-radius: 20px;
            padding: 40px;
            text-align: center;
            box-shadow: 0 10px 25px rgba(0,0,0,0.03);
            border: 1px solid #f1f5f9;
            position: relative;
        }
        .refresh-btn {
            position: absolute; top: 25px; right: 25px;
            color: #94a3b8; font-size: 18px; cursor: pointer;
        }

        .clock-time { font-size: 48px; font-weight: 800; color: #1e1b4b; margin-bottom: 5px; }
        .clock-date { font-size: 14px; color: #64748b; font-weight: 600; margin-bottom: 30px; }

        .status-title { font-size: 13px; font-weight: 700; color: #64748b; margin-bottom: 10px; text-transform: none; }
        .status-badge {
            display: inline-block;
            padding: 10px 25px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: white;
        }
        .badge-out { background: #ff5e3a; }
        .badge-in { background: #10b981; }

        .method-picker {
            margin: 30px 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 15px;
        }
        .method-label { font-size: 12px; font-weight: 600; color: #64748b; }
        .method-tabs {
            display: flex;
            background: #fff;
            border: 1px solid #f1f5f9;
            border-radius: 8px;
            padding: 4px;
        }
        .method-btn {
            padding: 10px 20px;
            border-radius: 6px;
            border: none;
            background: transparent;
            font-size: 13px;
            font-weight: 700;
            color: #64748b;
            cursor: pointer;
            display: flex; align-items: center; gap: 8px;
            transition: 0.2s;
        }
        .method-btn.active {
            background: #eef2ff; color: #13198f;
        }

        .action-row { display: flex; gap: 15px; justify-content: center; margin-bottom: 30px; }
        .btn-action {
            padding: 16px 30px;
            border-radius: 12px;
            border: none;
            font-size: 15px;
            font-weight: 800;
            cursor: pointer;
            transition: 0.2s;
            display: flex; align-items: center; gap: 10px;
            min-width: 150px;
            justify-content: center;
        }
        .btn-in { background: #f1f5f9; color: #94a3b8; }
        .btn-in.available { background: #13198f; color: white; box-shadow: 0 4px 12px rgba(19, 25, 143, 0.2); }
        .btn-out { background: #f1f5f9; color: #94a3b8; }
        .btn-out.available { background: #fff; color: #64748b; border: 1px solid #e2e8f0; }

        .dash-line { border-top: 1px solid #f1f5f9; margin: 30px 0; width: 100%; }
        
        .activity-section { text-align: left; }
        .activity-title { font-size: 14px; font-weight: 800; color: #1e293b; margin-bottom: 20px; }
        .activity-item { padding: 15px 0; font-size: 13px; color: #64748b; font-weight: 600; text-align: center; }

        .pin-container { display: none; margin-top: 15px; }
        .pin-input {
            width: 180px; text-align: center; padding: 15px; font-size: 24px; letter-spacing: 12px;
            border: 2px solid var(--border); border-radius: 12px; outline: none;
            background: #f8fafc; color: var(--primary); font-weight: 800;
        }
        .pin-input:focus { border-color: var(--primary); }
    </style>
</head>
<body>

    <!-- Sidebar -->
    <div class="sidebar">
        <div class="logo-circle"><i class="ph ph-shield-check"></i></div>
        
        <div class="profile-card">
            <div class="profile-avatar"><i class="ph ph-user"></i></div>
            <div class="profile-info">
                <h4><?= htmlspecialchars($student['name']) ?></h4>
                <p>Student</p>
            </div>
        </div>

        <div class="nav-menu">
            <a href="<?= WEB_ROOT ?>/student/dashboard" class="nav-item">
                <i class="ph ph-chart-line-up"></i> Dashboard
            </a>
            <a href="<?= WEB_ROOT ?>/student/attendance" class="nav-item active">
                <i class="ph ph-fingerprint"></i> Student Clocking
            </a>
            
            <div class="nav-group">
                <a href="javascript:void(0)" class="nav-item" onclick="toggleSub(this)">
                    <i class="ph ph-desktop"></i> CBT <i class="ph ph-caret-down" style="margin-left:auto; font-size:12px;"></i>
                </a>
                <div class="sub-menu" style="display:none; padding-left:40px;">
                    <a href="<?= WEB_ROOT ?>/student/cbt?type=mock" class="nav-item" style="font-size:12px; opacity:0.8;">Take Mock CBT</a>
                    <a href="<?= WEB_ROOT ?>/student/cbt?type=exam" class="nav-item" style="font-size:12px; opacity:0.8;">Take Exam</a>
                    <a href="<?= WEB_ROOT ?>/student/cbt?type=result" class="nav-item" style="font-size:12px; opacity:0.8;">View Result</a>
                </div>
            </div>

            <a href="<?= WEB_ROOT ?>/student/messaging" class="nav-item">
                <i class="ph ph-chat-circle-dots"></i> Messaging
            </a>
            <a href="<?= WEB_ROOT ?>/student/payments" class="nav-item">
                <i class="ph ph-receipt"></i> Payment History
            </a>
            <a href="<?= WEB_ROOT ?>/student/timetable" class="nav-item">
                <i class="ph ph-calendar-blank"></i> <?= htmlspecialchars($student['class_name'] ?? 'Class') ?> Timetable
            </a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="top-header">
            <div class="header-clock">
                <i class="ph ph-clock-countdown"></i>
                <span id="top-timer">00:00:00</span>
            </div>
            
            <div class="header-actions">
                <a href="<?= WEB_ROOT ?>/student/messaging" class="notif-bell" style="text-decoration:none;">
                    <i class="ph ph-bell"></i>
                    <?php if($unreadCount > 0): ?>
                        <div class="notif-badge"><?= $unreadCount ?></div>
                    <?php endif; ?>
                </a>
                <div class="user-circle"><?= strtoupper(substr($student['name'], 0, 1)) ?></div>
            </div>
        </div>

        <div class="clock-container">
            <div class="clock-card">
                <i class="ph ph-arrows-clockwise refresh-btn" onclick="location.reload()"></i>
                
                <div id="clock-time" class="clock-time">00:00:00</div>
                <div class="clock-date"><?= date('l, F jS Y') ?></div>

                <div class="status-title">Current Status</div>
                <?php if ($attStatus && $attStatus['clock_in'] && !$attStatus['clock_out']): ?>
                    <div class="status-badge badge-in">CLOCKED IN</div>
                <?php else: ?>
                    <div class="status-badge badge-out">CLOCKED OUT</div>
                <?php endif; ?>

                <?php if($message): ?>
                    <p style="background: #fff1f2; color:#be123c; padding:10px; border-radius:8px; font-size:12px; font-weight:700; margin-top:15px;"><?= $message ?></p>
                <?php endif; ?>

                <div class="method-picker">
                    <div class="method-label">Choose your preferred clocking method</div>
                    <div class="method-tabs">
                        <button class="method-btn active" id="btn-direct">
                            <i class="ph ph-hand-pointing"></i> Direct
                        </button>
                        <button class="method-btn" id="btn-pin">
                            <i class="ph ph-squares-four"></i> PIN
                        </button>
                        <button class="method-btn" id="btn-qr">
                            <i class="ph ph-qr-code"></i> QR Code
                        </button>
                    </div>
                </div>

                <form method="POST" id="clockForm" onsubmit="return validateSubmission()">
                    <input type="hidden" name="clock_method" id="clock_method" value="direct">
                    
                    <div class="pin-container" id="direct-section" style="display:block;">
                        <p style="font-size:14px; color:#1e293b; font-weight:800; margin-bottom:5px;">One-Touch Attendance</p>
                        <p style="font-size:12px; color:#64748b; font-weight:600;">Click the big button below to record your presence instantly.</p>
                    </div>

                    <div class="pin-container" id="pin-section">
                        <input type="password" name="pin_value" id="pin_value" class="pin-input" maxlength="4" placeholder="••••">
                        <p style="font-size:12px; color:#64748b; margin-top:12px; font-weight:600;">Security PIN Authentication Required</p>
                    </div>

                    <div class="pin-container" id="qr-section">
                        <div style="width:140px; height:140px; background:#f8fafc; border:2px dashed var(--border); border-radius:15px; margin:0 auto; display:flex; align-items:center; justify-content:center;">
                            <i class="ph ph-qr-code" style="font-size:70px; color:#cbd5e1;"></i>
                        </div>
                        <p style="font-size:12px; color:#64748b; margin-top:12px; font-weight:600;">ID Card QR Code Scan Required</p>
                    </div>

                    <div class="dash-line"></div>

                    <div class="action-row">
                        <button type="submit" name="clock_in" class="btn-action btn-in <?= (!$attStatus || !$attStatus['clock_in']) ? 'available' : '' ?>" <?= ($attStatus && $attStatus['clock_in']) ? 'disabled' : '' ?>>
                            Clock In
                        </button>
                        <button type="submit" name="clock_out" class="btn-action btn-out <?= ($attStatus && $attStatus['clock_in'] && !$attStatus['clock_out']) ? 'available' : '' ?>" <?= (!$attStatus || ($attStatus && (!$attStatus['clock_in'] || $attStatus['clock_out']))) ? 'disabled' : '' ?>>
                            <i class="ph ph-sign-out"></i> Clock Out
                        </button>
                    </div>
                </form>

                <div class="activity-section">
                    <div class="activity-title">Today's Activity</div>
                    <?php if(empty($todayActivities)): ?>
                        <div class="activity-item">Loading...</div>
                    <?php else: ?>
                        <?php foreach($todayActivities as $act): ?>
                            <div style="font-size:13px; color:#64748b; font-weight:600; display:flex; justify-content:space-between; padding:12px 0; border-bottom:1px solid #f8fafc;">
                                <div style="display:flex; align-items:center; gap:8px;">
                                    <i class="ph ph-arrow-circle-right" style="color:var(--accent-green)"></i>
                                    <span>Arrived (<?= date('h:i A', strtotime($act['clock_in'])) ?>)</span>
                                </div>
                                <span><?= $act['clock_out'] ? 'Departed ('.date('h:i A', strtotime($act['clock_out'])).')' : 'Currently in School' ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        function updateClock() {
            const now = new Date();
            const timeStr = now.toLocaleTimeString('en-GB', { hour12: false });
            document.getElementById('clock-time').innerText = timeStr;
            document.getElementById('top-timer').innerText = timeStr;
        }
        setInterval(updateClock, 1000);
        updateClock();

        // Method Toggling
        const btnDirect = document.getElementById('btn-direct');
        const btnPin = document.getElementById('btn-pin');
        const btnQr = document.getElementById('btn-qr');
        
        const directSec = document.getElementById('direct-section');
        const pinSec = document.getElementById('pin-section');
        const qrSec = document.getElementById('qr-section');
        const methodInput = document.getElementById('clock_method');

        function switchMethod(method) {
            btnDirect.classList.remove('active');
            btnPin.classList.remove('active');
            btnQr.classList.remove('active');
            
            directSec.style.display = 'none';
            pinSec.style.display = 'none';
            qrSec.style.display = 'none';
            
            if (method === 'direct') {
                btnDirect.classList.add('active');
                directSec.style.display = 'block';
                methodInput.value = 'direct';
            } else if (method === 'pin') {
                btnPin.classList.add('active');
                pinSec.style.display = 'block';
                methodInput.value = 'pin';
            } else if (method === 'qr') {
                btnQr.classList.add('active');
                qrSec.style.display = 'block';
                methodInput.value = 'qr';
            }
        }

        btnDirect.addEventListener('click', (e) => { e.preventDefault(); switchMethod('direct'); });
        btnPin.addEventListener('click', (e) => { e.preventDefault(); switchMethod('pin'); });
        btnQr.addEventListener('click', (e) => { e.preventDefault(); switchMethod('qr'); });

        function validateSubmission() {
            if (methodInput.value === 'pin' && document.getElementById('pin_value').value.length < 4) {
                alert('Please enter your full 4-digit PIN');
                return false;
            }
            return true;
        }
    </script>
</body>
</html>
