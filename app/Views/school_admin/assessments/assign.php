<?php
require_once ROOT_PATH . '/config/database.php';
$pageTitle = 'Assign Assessment - Rosmon SMS';
require ROOT_PATH . '/app/Views/school_admin/layout/header.php';
require ROOT_PATH . '/app/Views/school_admin/layout/sidebar.php';
?>

<div class="main-container">
    <div class="top-header">
        <div class="greeting">Module / <span style="color:var(--primary)">Assign Assessment</span></div>
        <div class="header-actions">
            <button class="btn-primary" style="text-decoration:none;"><i class="ph ph-plus"></i> Add New Record</button>
        </div>
    </div>

    <div class="crud-card">
        <div class="crud-header">
            <h2 class="crud-title">Manage Assign Assessment</h2>
        </div>

        <table class="crud-table">
            <thead>
                <tr>
                    <th>ID / CODE</th>
                    <th>NAME / DESCRIPTION</th>
                    <th>STATUS</th>
                    <th>DATE CREATED</th>
                    <th>ACTIONS</th>
                </tr>
            </thead>
            <tbody>
                <tr><td colspan="5" style="text-align:center; padding:40px; color:var(--text-muted);">No records have been added to this module yet.</td></tr>
            </tbody>
        </table>
    </div>
</div>

<?php require ROOT_PATH . '/app/Views/school_admin/layout/footer.php'; ?>
