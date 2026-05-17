<?php
require_once ROOT_PATH . '/config/database.php';
$pageTitle = 'Class Wise Attendance Report - Rosmon SMS';
require ROOT_PATH . '/app/Views/school_admin/layout/header.php';
require ROOT_PATH . '/app/Views/school_admin/layout/sidebar.php';
?>
<div class="main-container">
    <div class="top-header">
        <div class="greeting">Reports / <span style="color:var(--primary)">Class Wise Attendance</span></div>
    </div>
    <div class="crud-card">
        <div class="crud-header"><h2 class="crud-title">Attendance Metrics by Class</h2></div>
        <div style="padding: 50px; text-align: center; color: var(--text-muted);"><i class="ph ph-chart-bar" style="font-size: 48px; margin-bottom: 20px;"></i><p>Select a class and date range to generate the attendance report.</p></div>
    </div>
</div>
<?php require ROOT_PATH . '/app/Views/school_admin/layout/footer.php'; ?>
