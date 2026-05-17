<?php
// Attendance Overview Data Logic

try {
    $today = date('Y-m-d');
    
    // Stats Fetch for the Summary Cards
    $stmt = $pdo->prepare("SELECT status, COUNT(*) as count FROM student_attendance WHERE attendance_date = ? GROUP BY status");
    $stmt->execute([$today]);
    $attendance_today = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

$pageTitle = 'Attendance Overview - Rosmon SMS';
require ROOT_PATH . '/app/Views/school_admin/layout/header.php';
require ROOT_PATH . '/app/Views/school_admin/layout/sidebar.php';
?>

<div class="main-container">
    <div class="top-header">
        <div class="greeting">Attendance / <span style="color:var(--primary)">Overview Today</span></div>
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

    <!-- Summary Metrics -->
    <div style="display:grid; grid-template-columns: repeat(4, 1fr); gap:20px; margin-bottom: 24px;">
        <div class="crud-card" style="border-left: 4px solid #10b981;">
            <div style="font-size:12px; font-weight:700; color:var(--text-muted); margin-bottom:4px;">PRESENT STUDENTS</div>
            <div style="font-size:24px; font-weight:800; color:#064e3b;"><?= $attendance_today['Present'] ?? 0 ?></div>
        </div>
        <div class="crud-card" style="border-left: 4px solid #ef4444;">
            <div style="font-size:12px; font-weight:700; color:var(--text-muted); margin-bottom:4px;">ABSENT STUDENTS</div>
            <div style="font-size:24px; font-weight:800; color:#7f1d1d;"><?= $attendance_today['Absent'] ?? 0 ?></div>
        </div>
        <div class="crud-card" style="border-left: 4px solid #f59e0b;">
            <div style="font-size:12px; font-weight:700; color:var(--text-muted); margin-bottom:4px;">LATE ARRIVALS</div>
            <div style="font-size:24px; font-weight:800; color:#78350f;"><?= $attendance_today['Late'] ?? 0 ?></div>
        </div>
        <div class="crud-card" style="border-left: 4px solid #3b82f6;">
            <div style="font-size:12px; font-weight:700; color:var(--text-muted); margin-bottom:4px;">STAFF ON DUTY</div>
            <div style="font-size:24px; font-weight:800; color:#1e3a8a;">0</div>
        </div>
    </div>

    <!-- Main Lists -->
    <div style="display:grid; grid-template-columns: 2fr 1fr; gap:24px;">
        
        <div class="crud-card">
            <div class="crud-header">
                <h2 class="crud-title">Class-Wise Attendance (Today)</h2>
            </div>
            
            <table class="crud-table">
                <thead>
                    <tr>
                        <th>CLASS UNIT</th>
                        <th>TOTAL STUDENTS</th>
                        <th>PRESENT</th>
                        <th>ABSENT</th>
                        <th>ACTION</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Grouped Logic: Use the correct classes and institute_students schema
                    $stmt = $pdo->prepare("
                        SELECT 
                            c.class_name, 
                            c.section, 
                            COUNT(ins.id) as total,
                            SUM(CASE WHEN sa.status = 'Present' THEN 1 ELSE 0 END) as present_count,
                            SUM(CASE WHEN sa.status = 'Absent' THEN 1 ELSE 0 END) as absent_count
                        FROM classes c
                        LEFT JOIN institute_students ins ON ins.class_id = c.id AND ins.is_deleted = 0
                        LEFT JOIN student_attendance sa ON sa.student_id = ins.student_id AND sa.attendance_date = ?
                        WHERE c.is_deleted = 0 OR c.isDeleted = 0
                        GROUP BY c.id
                    ");
                    $stmt->execute([$today]);
                    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    foreach($rows as $r): ?>
                        <tr>
                            <td style="font-weight:700;"><?= htmlspecialchars((string)($r['class_name'] ?? '')) ?> (<?= $r['section'] ?>)</td>
                            <td><?= $r['total'] ?></td>
                            <td style="color:#10b981; font-weight:700;"><?= $r['present_count'] ?: 0 ?></td>
                            <td style="color:#ef4444; font-weight:700;"><?= $r['absent_count'] ?: 0 ?></td>
                            <td>
                                <a href="<?= WEB_ROOT ?>/school-admin/attendance/mark-students" class="btn-primary" style="font-size:10px; padding:6px 12px;">Mark Now</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="crud-card">
            <div class="crud-header">
                <h2 class="crud-title">Today's Timeline</h2>
            </div>
            <div style="padding:20px; text-align:center; color:var(--text-muted); font-size:13px;">
                <i class="ph ph-clock-countdown" style="font-size:48px; opacity:0.2; margin-bottom:12px; display:block; margin:0 auto;"></i>
                No check-in logs recorded yet today.
            </div>
        </div>

    </div>
</div>

<?php require ROOT_PATH . '/app/Views/school_admin/layout/footer.php'; ?>
