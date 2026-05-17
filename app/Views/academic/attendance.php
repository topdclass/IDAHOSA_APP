<?php
require_once ROOT_PATH . '/config/database.php';
$schoolId = $_SESSION['school_id'] ?? 101;

$message = '';
// Handle Attendance Clock-in/Clock-out
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $studentId = $_POST['student_id'];
    $date = date('Y-m-d');
    $time = date('H:i:s');
    
    // Check if entry exists for today
    $stmt = $pdo->prepare("SELECT id, clock_in, clock_out FROM student_attendance_logs WHERE student_id = ? AND attendance_date = ?");
    $stmt->execute([$studentId, $date]);
    $log = $stmt->fetch(PDO::FETCH_ASSOC);

    // Fetch Student & Parent details for notification
    $getDetails = $pdo->prepare("
        SELECT u.full_name as student_name, p_u.email as parent_email, p_u.full_name as parent_name
        FROM users u 
        JOIN institute_students s ON u.id = s.student_id
        LEFT JOIN institute_parents p ON s.family_id = p.family_id
        LEFT JOIN users p_u ON p.parent_id = p_u.id
        WHERE u.id = ? AND u.role = 'student'
        LIMIT 1
    ");
    $getDetails->execute([$studentId]);
    $details = $getDetails->fetch(PDO::FETCH_ASSOC);

    if ($_POST['action'] === 'clock_in') {
        if (!$log) {
            $insert = $pdo->prepare("INSERT INTO student_attendance_logs (student_id, attendance_date, clock_in) VALUES (?, ?, ?)");
            $insert->execute([$studentId, $date, $time]);
            $message = "Clock-IN Recorded Successfully at $time.";

            if ($details && $details['parent_email']) {
                $subject = "[RosmonSMS] Attendance Update: Departure to School";
                $body = "Dear {$details['parent_name']},\n\nYour ward {$details['student_name']} has safely clocked IN at the school premises at $time today.\n\nThank you.";
                @mail($details['parent_email'], $subject, $body, "From: no-reply@rosmonsms.com\r\n");
            }
        } else {
            $message = "Student has already clocked in today.";
        }
    } elseif ($_POST['action'] === 'clock_out') {
        if ($log && !$log['clock_out']) {
            $update = $pdo->prepare("UPDATE student_attendance_logs SET clock_out = ? WHERE id = ?");
            $update->execute([$time, $log['id']]);
            $message = "Clock-OUT Recorded Successfully at $time.";

            if ($details && $details['parent_email']) {
                $subject = "[RosmonSMS] Attendance Update: Departure from School";
                $body = "Dear {$details['parent_name']},\n\nYour ward {$details['student_name']} has safely clocked OUT from the school premises at $time today.\n\nThank you.";
                @mail($details['parent_email'], $subject, $body, "From: no-reply@rosmonsms.com\r\n");
            }
        } else {
            $message = "Student hasn't clocked in or already clocked out.";
        }
    }
}

// Fetch Today's Logs
$todayLogs = $pdo->query("
    SELECT l.*, u.full_name 
    FROM student_attendance_logs l 
    JOIN users u ON l.student_id = u.id 
    WHERE l.attendance_date = CURDATE() 
    ORDER BY l.id DESC LIMIT 20
")->fetchAll(PDO::FETCH_ASSOC);

// Fetch All Students for Selection
$students = $pdo->query("
    SELECT u.id, u.full_name, c.class_name 
    FROM users u 
    JOIN institute_students ins ON u.id = ins.student_id 
    JOIN classes c ON ins.class_id = c.id
    WHERE u.role = 'student'
")->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Kiosk - Academic</title>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <style>
        :root { --primary: #059669; --bg: #f8fafc; --text: #0f172a; --border: #e2e8f0; }
        * { box-sizing: border-box; font-family: 'Inter', sans-serif; margin: 0; padding: 0; }
        body { background: var(--bg); display: flex; color: var(--text); min-height: 100vh; }
        .sidebar { width: 260px; height: 100vh; background: #064e3b; color: white; padding: 20px; position: fixed; }
        .main { flex: 1; margin-left: 260px; padding: 30px; }
        .nav-link { display: flex; align-items: center; gap: 12px; padding: 12px; color: #a7f3d0; text-decoration: none; border-radius: 8px; margin-bottom: 5px; }
        .nav-link:hover, .nav-link.active { background: rgba(255,255,255,0.1); color: white; }
        .header { margin-bottom: 30px; border-bottom: 1px solid var(--border); padding-bottom: 15px; }
        
        .panel { background: white; padding: 25px; border-radius: 12px; border: 1px solid var(--border); box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); margin-bottom: 30px; }
        .btn-in { background: #3b82f6; color: white; border: none; padding: 12px 24px; border-radius: 6px; font-weight: 700; cursor: pointer; transition: 0.2s; font-size: 15px; }
        .btn-out { background: #ef4444; color: white; border: none; padding: 12px 24px; border-radius: 6px; font-weight: 700; cursor: pointer; transition: 0.2s; font-size: 15px; }
        
        .form-group { margin-bottom: 15px; text-align: center; }
        select { width: 100%; max-width: 400px; padding: 12px; border: 2px solid var(--border); border-radius: 8px; font-size: 16px; outline: none; margin: 0 auto; display: block; }
        select:focus { border-color: var(--primary); }
        
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid var(--border); }
        th { font-size: 12px; text-transform: uppercase; color: #64748b; background: #f8fafc; }
        td { font-size: 14px; font-weight: 500; }
        .success-msg { background: #dcfce3; color: #166534; padding: 12px; border-radius: 8px; margin-bottom: 20px; font-weight: 600; font-size: 14px; text-align: center; border: 1px solid #bbf7d0; }
        .badge { padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 700; text-transform: uppercase; }
        .badge-in { background: #dbeafe; color: #1e40af; }
        .badge-out { background: #fee2e2; color: #991b1b; }
    </style>
</head>
<body>
    <div class="sidebar">
        <h2 style="font-size: 20px; margin-bottom: 30px;">CAMPUS KIOSK</h2>
        <a href="/academic/attendance" class="nav-link active"><i class="ph ph-hand-swipe-right"></i> Digital Attendance</a>
        <a href="#" class="nav-link"><i class="ph ph-bell-ringing"></i> Dispatch Logs</a>
        <div style="margin-top: auto; padding-top: 20px;">
            <a href="/" class="nav-link" style="color: #fca5a5;"><i class="ph ph-sign-out"></i> Leave Kiosk</a>
        </div>
    </div>

    <div class="main">
        <div class="header" style="text-align: center;">
            <h1 style="font-size: 32px; font-weight: 800;">Student Check-In / Check-Out</h1>
            <p style="color: #64748b; margin-top: 5px;">Parents will be notified immediately via Email / Push Notification.</p>
        </div>

        <?php if($message): ?>
            <div class="success-msg"><i class="ph ph-bell-ringing"></i> <?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <div class="panel" style="text-align: center; max-width: 600px; margin: 0 auto 40px;">
            <h3 style="margin-bottom: 20px;">Scan ID or Select Student</h3>
            <form method="POST" style="margin-bottom: 30px;">
                <div class="form-group">
                    <select name="student_id" required>
                        <option value="">-- Choose Student --</option>
                        <?php foreach($students as $s): ?>
                            <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['full_name']) ?> (<?= htmlspecialchars($s['class_name']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div style="display: flex; gap: 20px; justify-content: center; margin-top: 20px;">
                    <button type="submit" name="action" value="clock_in" class="btn-in" style="flex: 1;"><i class="ph ph-sign-in"></i> Register IN</button>
                    <button type="submit" name="action" value="clock_out" class="btn-out" style="flex: 1;"><i class="ph ph-sign-out"></i> Register OUT</button>
                </div>
            </form>
        </div>

        <div class="panel">
            <h3 style="margin-bottom: 20px;">Today's Activity Log (<?= date('M d, Y') ?>)</h3>
            <table>
                <thead>
                    <tr>
                        <th>Student Name</th>
                        <th>Clock IN Time</th>
                        <th>Clock OUT Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($todayLogs)): ?>
                        <tr><td colspan="3" style="text-align: center; color: #94a3b8; padding: 20px;">No movement recorded today yet.</td></tr>
                    <?php else: ?>
                        <?php foreach($todayLogs as $log): ?>
                            <tr>
                                <td style="font-weight: 700;"><?= htmlspecialchars($log['full_name']) ?></td>
                                <td>
                                    <?php if ($log['clock_in']): ?>
                                        <span class="badge badge-in"><?= date('h:i A', strtotime($log['clock_in'])) ?></span>
                                    <?php else: ?>
                                        <span style="color:#cbd5e1;">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($log['clock_out']): ?>
                                        <span class="badge badge-out"><?= date('h:i A', strtotime($log['clock_out'])) ?></span>
                                    <?php else: ?>
                                        <span style="color:#cbd5e1; font-weight:700;">Still on Campus</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
