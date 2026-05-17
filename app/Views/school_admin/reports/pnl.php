<?php
require_once ROOT_PATH . '/config/database.php';
$pageTitle = 'Profit & Loss Summary - Rosmon SMS';
require ROOT_PATH . '/app/Views/school_admin/layout/header.php';
require ROOT_PATH . '/app/Views/school_admin/layout/sidebar.php';
?>
<div class="main-container">
    <div class="top-header">
        <div class="greeting">Reports / <span style="color:var(--primary)">Profit and Loss Summary</span></div>
    </div>
    <div class="crud-card">
        <div class="crud-header"><h2 class="crud-title">School Financial Health Summary</h2></div>
        <div style="padding: 40px; text-align: center; color: var(--text-muted);">
            <i class="ph ph-trend-up" style="font-size: 50px; margin-bottom: 20px;"></i>
            <p>Summary of all income and expenses for the current academic session.</p>
        </div>
    </div>
</div>
<?php require ROOT_PATH . '/app/Views/school_admin/layout/footer.php'; ?>
