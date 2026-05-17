<?php
require_once ROOT_PATH . '/config/database.php';
$pageTitle = 'Student Attendance Report - Rosmon SMS';
require ROOT_PATH . '/app/Views/school_admin/layout/header.php';
require ROOT_PATH . '/app/Views/school_admin/layout/sidebar.php';
?>
<div class="main-container">
    <div class="top-header">
        <div class="greeting">Reports / <span style="color:var(--primary)">Students Attendance Metrics</span></div>
    </div>
    <div class="crud-card">
        <div style="padding: 50px; text-align: center; color: var(--text-muted);"><i class="ph ph-student" style="font-size: 48px; margin-bottom: 20px;"></i><p>Select student and date range to generate the report.</p></div>
    </div>
</div>
<?php require ROOT_PATH . '/app/Views/school_admin/layout/footer.php'; ?>
