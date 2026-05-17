<?php
// Mark Employee Attendance Logic

try {

    // Auto Migration: Employee Attendance
    $pdo->exec("CREATE TABLE IF NOT EXISTS employee_attendance (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        attendance_date DATE NOT NULL,
        status ENUM('Present', 'Absent', 'On Leave', 'Half Day') DEFAULT 'Present',
        clock_in TIME NULL,
        clock_out TIME NULL,
        remarks TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(user_id, attendance_date)
    )");

    $message = '';
    $date = $_GET['date'] ?? date('Y-m-d');

    // Handle Submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_employee'])) {
        $user_ids = $_POST['user_ids'] ?? [];
        $statuses = $_POST['status'] ?? [];
        $remarks = $_POST['remarks'] ?? [];

        foreach ($user_ids as $uid) {
            $status = $statuses[$uid] ?? 'Present';
            $remark = $remarks[$uid] ?? '';

            $stmt = $pdo->prepare("INSERT INTO employee_attendance (user_id, attendance_date, status, remarks) 
                                   VALUES (?, ?, ?, ?) 
                                   ON DUPLICATE KEY UPDATE status = VALUES(status), remarks = VALUES(remarks)");
            $stmt->execute([$uid, $date, $status, $remark]);
        }
        $message = "Employee attendance for $date saved!";
    }

    // Fetch All Employees (Teachers and Support Staff)
    $stmt = $pdo->query("SELECT id, full_name, role FROM users WHERE role IN ('teacher', 'support_staff', 'finance_officer') ORDER BY full_name ASC");
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch existing attendance
    $stmt = $pdo->prepare("SELECT user_id, status, remarks FROM employee_attendance WHERE attendance_date = ?");
    $stmt->execute([$date]);
    $existing = $stmt->fetchAll(PDO::FETCH_UNIQUE | PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

$pageTitle = 'Mark Employee Attendance - Rosmon SMS';
require ROOT_PATH . '/app/Views/school_admin/layout/header.php';
require ROOT_PATH . '/app/Views/school_admin/layout/sidebar.php';
?>

<div class="main-container">
    <div class="top-header">
        <div class="greeting">Attendance / <span style="color:var(--primary)">Staff Morning Drill</span></div>
        <div class="header-actions">
            <i class="ph ph-bell action-bell"></i>
            <div class="profile-avatar" onclick="toggleProfileDropdown(event)">RI</div>
            <div class="profile-dropdown" id="profileDropdown">
                <a href="<?= WEB_ROOT ?>/school-admin/general-settings/account-settings" class="dropdown-item">
                    <i class="ph ph-user-circle"></i> Account Profile
                </a>
                <a href="<?= WEB_ROOT ?>/logout" class="dropdown-item" style="color:#ef4444;">
                    <i class="ph ph-sign-out" style="color:#ef4444;"></i> Logout
                </a>
            </div>
        </div>
    </div>

    <div class="crud-card" style="margin-bottom:24px;">
        <form method="GET" style="display:flex; gap:16px; align-items:flex-end;">
            <div style="flex:1;">
                <label style="font-size:12px; font-weight:700; color:var(--text-muted);">CURRENT DATE</label>
                <input type="date" name="date" value="<?= $date ?>" style="width:100%; padding:10px; border:1px solid var(--border); border-radius:6px; font-size:13px; margin-top:5px;">
            </div>
            <button type="submit" class="btn-primary" style="height:42px;">Load Roll Call</button>
        </form>
    </div>

    <div class="crud-card">
        <div class="crud-header">
            <h2 class="crud-title">Staff Marking List (<?= $date ?>)</h2>
            <?php if ($message): ?>
                <span style="color:#10b981; font-weight:700; font-size:12px;"><?= $message ?></span>
            <?php endif; ?>
        </div>

        <form method="POST">
            <input type="hidden" name="mark_employee" value="1">
            <table class="crud-table">
                <thead>
                    <tr>
                        <th>STAFF NAME</th>
                        <th>ROLE</th>
                        <th>MARKING STATUS</th>
                        <th>REMARKS</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($employees)): ?>
                        <tr><td colspan="4" style="text-align:center; padding:30px; color:var(--text-muted);">No staff members found on record.</td></tr>
                    <?php else: ?>
                        <?php foreach($employees as $e): 
                            $curr_status = $existing[$e['id']]['status'] ?? 'Present';
                            $curr_remark = $existing[$e['id']]['remarks'] ?? '';
                        ?>
                            <tr>
                                <input type="hidden" name="user_ids[]" value="<?= $e['id'] ?>">
                                <td style="font-weight:700;"><?= htmlspecialchars((string)($e['full_name'] ?? '')) ?></td>
                                <td style="text-transform: capitalize; font-size:11px; font-weight:600; color:var(--text-muted);"><?= str_replace('_', ' ', $e['role']) ?></td>
                                <td>
                                    <div style="display:flex; gap:8px;">
                                        <label style="font-size:11px; cursor:pointer;"><input type="radio" name="status[<?= $e['id'] ?>]" value="Present" <?= $curr_status == 'Present' ? 'checked' : '' ?>> Present</label>
                                        <label style="font-size:11px; cursor:pointer;"><input type="radio" name="status[<?= $e['id'] ?>]" value="Absent" <?= $curr_status == 'Absent' ? 'checked' : '' ?>> Absent</label>
                                        <label style="font-size:11px; cursor:pointer;"><input type="radio" name="status[<?= $e['id'] ?>]" value="On Leave" <?= $curr_status == 'On Leave' ? 'checked' : '' ?>> On Leave</label>
                                    </div>
                                </td>
                                <td>
                                    <input type="text" name="remarks[<?= $e['id'] ?>]" value="<?= htmlspecialchars((string)($curr_remark ?? '')) ?>" placeholder="Optional remark" style="width:100%; border:none; border-bottom:1px solid #eee; font-size:12px; outline:none;">
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <?php if (!empty($employees)): ?>
            <div style="margin-top:24px; display:flex; justify-content:flex-end;">
                <button type="submit" class="btn-primary" style="padding:12px 32px;">Submit Roll Call</button>
            </div>
            <?php endif; ?>
        </form>
    </div>
</div>

<?php require ROOT_PATH . '/app/Views/school_admin/layout/footer.php'; ?>
