<?php
// Employee Self-Service Clocking Page

try {
    
    // Auto Migration: Employee Attendance Infrastructure
    $pdo->exec("CREATE TABLE IF NOT EXISTS employee_attendance (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        attendance_date DATE NOT NULL,
        status ENUM('Present', 'Absent', 'Late', 'Half-Day') DEFAULT 'Present',
        clock_in TIME,
        clock_out TIME,
        remarks TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(user_id, attendance_date)
    )");

    $message = '';
    $user_id = 2; // Mocking logged-in staff for demo
    $today = date('Y-m-d');

    // Handle Clock In/Out
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $now = date('H:i:s');
        if (isset($_POST['clock_in'])) {
            $stmt = $pdo->prepare("INSERT INTO employee_attendance (user_id, attendance_date, status, clock_in) 
                                   VALUES (?, ?, 'Present', ?) 
                                   ON DUPLICATE KEY UPDATE clock_in = IF(clock_in IS NULL, VALUES(clock_in), clock_in)");
            $stmt->execute([$user_id, $today, $now]);
            $message = "Clocked in at $now";
        } elseif (isset($_POST['clock_out'])) {
            $stmt = $pdo->prepare("UPDATE employee_attendance SET clock_out = ? WHERE user_id = ? AND attendance_date = ?");
            $stmt->execute([$now, $user_id, $today]);
            $message = "Clocked out at $now";
        }
    }

    // Current State
    $stmt = $pdo->prepare("SELECT * FROM employee_attendance WHERE user_id = ? AND attendance_date = ?");
    $stmt->execute([$user_id, $today]);
    $status = $stmt->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

$pageTitle = 'Digital Clocking - Rosmon SMS';
require ROOT_PATH . '/app/Views/school_admin/layout/header.php';
require ROOT_PATH . '/app/Views/school_admin/layout/sidebar.php';
?>

<div class="main-container">
    <div style="max-width:500px; margin: 60px auto;">
        <div class="crud-card" style="text-align:center; padding:40px;">
            <div style="font-size:14px; font-weight:700; color:var(--text-muted); margin-bottom:10px;">GOOD DAY, STAFF MEMBER</div>
            <h1 id="live-clock" style="font-size:64px; font-weight:800; color:var(--text-dark); margin:0;">00:00:00</h1>
            <div style="font-size:16px; color:var(--primary); margin-bottom:40px; font-weight:600;"><?= date('l, jS F Y') ?></div>

            <?php if ($message): ?>
                <div style="background:#dcfce7; color:#166534; padding:12px; border-radius:8px; margin-bottom:30px; font-weight:700; font-size:14px;"><?= $message ?></div>
            <?php endif; ?>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px;">
                <form method="POST">
                    <button type="submit" name="clock_in" <?= ($status && $status['clock_in']) ? 'disabled' : '' ?> 
                            style="width:100%; padding:20px; border-radius:12px; border:none; background:<?= ($status && $status['clock_in']) ? '#e5e7eb' : 'var(--primary)' ?>; color:white; font-weight:800; cursor:pointer; font-size:16px;">
                        <i class="ph ph-sign-in" style="font-size:24px; display:block; margin:0 auto 8px;"></i>
                        CLOCK IN
                    </button>
                    <small style="display:block; margin-top:8px; color:#10b981; font-weight:700;"><?= ($status && $status['clock_in']) ? 'Logged: '.date('h:i A', strtotime($status['clock_in'])) : '' ?></small>
                </form>

                <form method="POST">
                    <button type="submit" name="clock_out" <?= (!$status || ($status && $status['clock_out'])) ? 'disabled' : '' ?> 
                            style="width:100%; padding:20px; border-radius:12px; border:none; background:<?= (!$status || ($status && $status['clock_out'])) ? '#e5e7eb' : '#ef4444' ?>; color:white; font-weight:800; cursor:pointer; font-size:16px;">
                        <i class="ph ph-sign-out" style="font-size:24px; display:block; margin:0 auto 8px;"></i>
                        CLOCK OUT
                    </button>
                    <small style="display:block; margin-top:8px; color:#ef4444; font-weight:700;"><?= ($status && $status['clock_out']) ? 'Logged: '.date('h:i A', strtotime($status['clock_out'])) : '' ?></small>
                </form>
            </div>

            <div style="margin-top:40px; padding-top:40px; border-top:1px dashed #eee;">
                <div style="font-size:12px; color:var(--text-muted);">Assigned Shift: 08:00 AM - 04:00 PM</div>
                <div style="font-size:11px; margin-top:5px; color:var(--text-muted);">IP Address tracked for security.</div>
            </div>
        </div>
    </div>
</div>

<script>
    function updateClock() {
        const now = new Date();
        document.getElementById('live-clock').innerText = now.toLocaleTimeString('en-GB');
    }
    setInterval(updateClock, 1000);
    updateClock();
</script>

<?php require ROOT_PATH . '/app/Views/school_admin/layout/footer.php'; ?>
