<?php
// Mark Students Attendance Logic

try {

    $message = '';
    $selected_class = $_GET['class_id'] ?? null;
    $date = $_GET['date'] ?? date('Y-m-d');

    // Handle Attendance Submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_attendance'])) {
        $student_ids = $_POST['student_ids'] ?? [];
        $statuses = $_POST['status'] ?? [];
        $remarks = $_POST['remarks'] ?? [];

        foreach ($student_ids as $sid) {
            $status = $statuses[$sid] ?? 'Present';
            $remark = $remarks[$sid] ?? '';

            // Upsert Logic
            $stmt = $pdo->prepare("INSERT INTO student_attendants (student_id, attendant_date, status, remarks) 
                                   VALUES (?, ?, ?, ?) 
                                   ON DUPLICATE KEY UPDATE status = VALUES(status), remarks = VALUES(remarks)");
            $stmt->execute([$sid, $date, $status, $remark]);
        }
        $message = "Attendance for $date saved successfully!";
    }

    // Fetch Classes
    $classes = $pdo->query("SELECT * FROM classes")->fetchAll(PDO::FETCH_ASSOC);

    // Fetch Students in selected class
    $students = [];
    if ($selected_class) {
        $stmt = $pdo->prepare("SELECT s.id, u.full_name, s.student_no as admission_number 
                               FROM institute_students s 
                               JOIN users u ON s.student_id = u.id 
                               WHERE s.class_id = ?");
        $stmt->execute([$selected_class]);
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Fetch existing attendance for these students on this date
        $stmt = $pdo->prepare("SELECT student_id, status, remarks FROM student_attendants WHERE attendant_date = ?");
        $stmt->execute([$date]);
        $existing = $stmt->fetchAll(PDO::FETCH_UNIQUE | PDO::FETCH_ASSOC);
    }

} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

$pageTitle = 'Mark Student Attendance - Rosmon SMS';
require ROOT_PATH . '/app/Views/school_admin/layout/header.php';
require ROOT_PATH . '/app/Views/school_admin/layout/sidebar.php';
?>

<div class="main-container">
    <div class="top-header">
        <div class="greeting">Attendance / <span style="color:var(--primary)">Mark Student Attendance</span></div>
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

    <!-- Selection Bar -->
    <div class="crud-card" style="margin-bottom:24px;">
        <form method="GET" style="display:flex; gap:16px; align-items:flex-end;">
            <div style="flex:1;">
                <label style="display:block; font-size:12px; font-weight:700; margin-bottom:5px;">SELECT CLASS</label>
                <select name="class_id" required style="width:100%; padding:10px; border:1px solid var(--border); border-radius:6px; font-size:13px;">
                    <option value="">-- Choose Class --</option>
                    <?php foreach($classes as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $selected_class == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string)($c['class_name'] ?? '')) ?> (<?= htmlspecialchars((string)($c['section'] ?? '')) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label style="display:block; font-size:12px; font-weight:700; margin-bottom:5px;">DATE</label>
                <input type="date" name="date" value="<?= $date ?>" style="padding:9px; border:1px solid var(--border); border-radius:6px; font-size:13px;">
            </div>
            <button type="submit" class="btn-primary" style="height:40px;">Load Students</button>
        </form>
    </div>

    <?php if ($selected_class): ?>
    <div class="crud-card">
        <div class="crud-header">
            <h2 class="crud-title">Marking Sheet - <?= $date ?></h2>
            <?php if ($message): ?>
                <span style="color:#10b981; font-weight:700; font-size:12px;"><?= $message ?></span>
            <?php endif; ?>
        </div>

        <form method="POST">
            <input type="hidden" name="mark_attendance" value="1">
            <table class="crud-table">
                <thead>
                    <tr>
                        <th style="width:150px;">ADMISSION NO.</th>
                        <th>STUDENT NAME</th>
                        <th style="width:250px;">STATUS</th>
                        <th>REMARKS</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($students)): ?>
                        <tr><td colspan="4" style="text-align:center; padding:30px; color:var(--text-muted);">No students found in this class.</td></tr>
                    <?php else: ?>
                        <?php foreach($students as $s): 
                            $curr_status = $existing[$s['id']]['status'] ?? 'Present';
                            $curr_remark = $existing[$s['id']]['remarks'] ?? '';
                        ?>
                            <tr>
                                <input type="hidden" name="student_ids[]" value="<?= $s['id'] ?>">
                                <td style="font-weight:700; color:var(--text-muted);"><?= $s['admission_number'] ?></td>
                                <td style="font-weight:700;"><?= htmlspecialchars((string)($s['full_name'] ?? '')) ?></td>
                                <td>
                                    <div style="display:flex; gap:10px;">
                                        <label style="font-size:11px; cursor:pointer;"><input type="radio" name="status[<?= $s['id'] ?>]" value="Present" <?= $curr_status == 'Present' ? 'checked' : '' ?>> Present</label>
                                        <label style="font-size:11px; cursor:pointer;"><input type="radio" name="status[<?= $s['id'] ?>]" value="Absent" <?= $curr_status == 'Absent' ? 'checked' : '' ?>> Absent</label>
                                        <label style="font-size:11px; cursor:pointer;"><input type="radio" name="status[<?= $s['id'] ?>]" value="Late" <?= $curr_status == 'Late' ? 'checked' : '' ?>> Late</label>
                                    </div>
                                </td>
                                <td>
                                    <input type="text" name="remarks[<?= $s['id'] ?>]" value="<?= htmlspecialchars((string)($curr_remark ?? '')) ?>" placeholder="Optional remark" style="width:100%; border:none; border-bottom:1px solid #eee; font-size:12px; outline:none;">
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <?php if (!empty($students)): ?>
            <div style="margin-top:24px; display:flex; justify-content:flex-end;">
                <button type="submit" class="btn-primary" style="padding:12px 32px;">Save Marking Sheet</button>
            </div>
            <?php endif; ?>
        </form>
    </div>
    <?php endif; ?>

</div>

<?php require ROOT_PATH . '/app/Views/school_admin/layout/footer.php'; ?>
