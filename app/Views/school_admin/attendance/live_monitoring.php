<?php
require_once ROOT_PATH . '/config/database.php';
$pageTitle = 'Live Attendance Monitoring - Rosmon SMS';
require ROOT_PATH . '/app/Views/school_admin/layout/header.php';
require ROOT_PATH . '/app/Views/school_admin/layout/sidebar.php';
?>
<div class="main-container">
    <div class="top-header">
        <div class="greeting">Attendance / <span style="color:var(--primary)">Live Monitoring</span></div>
    </div>
    <div class="crud-card">
        <div class="crud-header"><h2 class="crud-title">Real-time Attendance Stream</h2></div>
        <div style="padding: 50px; text-align: center; color: var(--text-muted);">
            <i class="ph ph-broadcast" style="font-size: 48px; margin-bottom: 20px;"></i>
            <p>Live monitoring stream will appear here as students/employees clock in/out.</p>
        </div>
    </div>
</div>
<?php require ROOT_PATH . '/app/Views/school_admin/layout/footer.php'; ?>
