<?php
require_once ROOT_PATH . '/config/database.php';
$pageTitle = 'Full Balance Sheet - Rosmon SMS';
require ROOT_PATH . '/app/Views/school_admin/layout/header.php';
require ROOT_PATH . '/app/Views/school_admin/layout/sidebar.php';
?>
<div class="main-container">
    <div class="top-header">
        <div class="greeting">Reports / <span style="color:var(--primary)">Institutional Balance Sheet</span></div>
    </div>
    <div class="crud-card">
        <div style="padding: 40px; text-align: center; color: var(--text-muted);"><i class="ph ph-bank" style="font-size: 50px; margin-bottom: 20px;"></i><p>A comprehensive summary of assets, liabilities, and institutional equity.</p></div>
    </div>
</div>
<?php require ROOT_PATH . '/app/Views/school_admin/layout/footer.php'; ?>
