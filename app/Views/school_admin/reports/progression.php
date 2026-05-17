<?php
require_once ROOT_PATH . '/config/database.php';
$pageTitle = 'Student Progression Report - Rosmon SMS';
require ROOT_PATH . '/app/Views/school_admin/layout/header.php';
require ROOT_PATH . '/app/Views/school_admin/layout/sidebar.php';
?>
<div class="main-container">
    <div class="top-header">
        <div class="greeting">Reports / <span style="color:var(--primary)">Progression Tracking (Terms 1-3)</span></div>
    </div>
    <div class="crud-card">
        <div style="padding: 40px; text-align: center; color: var(--text-muted);">
            <i class="ph ph-chart-line-up" style="font-size: 50px; margin-bottom: 20px;"></i>
            <p>Annual progression analytics and terminal comparison charts.</p>
        </div>
    </div>
</div>
<?php require ROOT_PATH . '/app/Views/school_admin/layout/footer.php'; ?>
