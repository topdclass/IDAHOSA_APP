<?php
require_once ROOT_PATH . '/config/database.php';
$pageTitle = 'Transaction Reports - Rosmon SMS';
require ROOT_PATH . '/app/Views/school_admin/layout/header.php';
require ROOT_PATH . '/app/Views/school_admin/layout/sidebar.php';
?>
<div class="main-container">
    <div class="top-header">
        <div class="greeting">Reports / <span style="color:var(--primary)">Global Transaction Log</span></div>
    </div>
    <div class="crud-card">
        <div style="padding: 40px; text-align: center; color: var(--text-muted);"><i class="ph ph-list-numbers" style="font-size: 50px; margin-bottom: 20px;"></i><p>Search and filter all financial transactions recorded by the system.</p></div>
    </div>
</div>
<?php require ROOT_PATH . '/app/Views/school_admin/layout/footer.php'; ?>
