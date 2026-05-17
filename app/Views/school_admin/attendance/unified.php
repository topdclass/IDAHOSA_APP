<?php
// Unified Attendance Monitor

try {
    
    $date = $_GET['date'] ?? date('Y-m-d');

    // Fetch Student Totals
    $stmt = $pdo->prepare("SELECT status, COUNT(*) as count FROM student_attendance WHERE attendance_date = ? GROUP BY status");
    $stmt->execute([$date]);
    $student_stats = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    // Fetch Employee Totals
    $stmt = $pdo->prepare("SELECT status, COUNT(*) as count FROM employee_attendance WHERE attendance_date = ? GROUP BY status");
    $stmt->execute([$date]);
    $employee_stats = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

$pageTitle = 'Unified Attendance - Rosmon SMS';
require ROOT_PATH . '/app/Views/school_admin/layout/header.php';
require ROOT_PATH . '/app/Views/school_admin/layout/sidebar.php';
?>

<div class="main-container">
    <div class="top-header">
        <div class="greeting">Attendance / <span style="color:var(--primary)">Unified Monitor</span></div>
        <div style="display:flex; align-items:center; gap:10px;">
            <form method="GET" style="display:flex; gap:8px;">
                <input type="date" name="date" value="<?= $date ?>" onchange="this.form.submit()" style="padding:6px; border:1px solid #ddd; border-radius:4px; font-size:12px;">
            </form>
        </div>
    </div>

    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:24px;">
        
        <!-- Students Column -->
        <div class="crud-card">
            <div class="crud-header" style="border-bottom: 2px solid #eef2ff;">
                <h2 class="crud-title"><i class="ph ph-student"></i> Student Distribution</h2>
            </div>
            
            <div style="padding:20px;">
                <div style="display:flex; justify-content:space-between; margin-bottom:15px; padding:12px; background:#f0fdf4; border-radius:8px;">
                    <span style="font-weight:700; color:#166534;">Present</span>
                    <span style="font-weight:800;"><?= $student_stats['Present'] ?? 0 ?></span>
                </div>
                <div style="display:flex; justify-content:space-between; margin-bottom:15px; padding:12px; background:#fef2f2; border-radius:8px;">
                    <span style="font-weight:700; color:#991b1b;">Absent</span>
                    <span style="font-weight:800;"><?= $student_stats['Absent'] ?? 0 ?></span>
                </div>
                <div style="display:flex; justify-content:space-between; padding:12px; background:#fffbeb; border-radius:8px;">
                    <span style="font-weight:700; color:#92400e;">Late</span>
                    <span style="font-weight:800;"><?= $student_stats['Late'] ?? 0 ?></span>
                </div>
            </div>
        </div>

        <!-- Employees Column -->
        <div class="crud-card">
            <div class="crud-header" style="border-bottom: 2px solid #fff7ed;">
                <h2 class="crud-title"><i class="ph ph-identification-badge"></i> Staff Distribution</h2>
            </div>
            
            <div style="padding:20px;">
                <div style="display:flex; justify-content:space-between; margin-bottom:15px; padding:12px; background:#f0fdf4; border-radius:8px;">
                    <span style="font-weight:700; color:#166534;">On Duty</span>
                    <span style="font-weight:800;"><?= $employee_stats['Present'] ?? 0 ?></span>
                </div>
                <div style="display:flex; justify-content:space-between; margin-bottom:15px; padding:12px; background:#fef2f2; border-radius:8px;">
                    <span style="font-weight:700; color:#991b1b;">Absent</span>
                    <span style="font-weight:800;"><?= $employee_stats['Absent'] ?? 0 ?></span>
                </div>
                <div style="display:flex; justify-content:space-between; padding:12px; background:#eff6ff; border-radius:8px;">
                    <span style="font-weight:700; color:#1e40af;">On Leave</span>
                    <span style="font-weight:800;"><?= $employee_stats['On Leave'] ?? 0 ?></span>
                </div>
            </div>
        </div>

    </div>

    <!-- Recent Activity Log (Mock/Combined) -->
    <div class="crud-card" style="margin-top:24px;">
        <div class="crud-header">
            <h2 class="crud-title">Unified Attendance Log</h2>
        </div>
        <table class="crud-table">
            <thead>
                <tr>
                    <th>TIME</th>
                    <th>NAME</th>
                    <th>TYPE</th>
                    <th>STATUS</th>
                </tr>
            </thead>
            <tbody>
                <tr><td colspan="4" style="text-align:center; padding:40px; color:var(--text-muted);">Switch to specific reports for detailed logs.</td></tr>
            </tbody>
        </table>
    </div>
</div>

<?php require ROOT_PATH . '/app/Views/school_admin/layout/footer.php'; ?>
