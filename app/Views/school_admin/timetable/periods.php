<?php
require_once ROOT_PATH . '/config/database.php';
$pageTitle = 'Periods - Rosmon SMS';
require ROOT_PATH . '/app/Views/school_admin/layout/header.php';
require ROOT_PATH . '/app/Views/school_admin/layout/sidebar.php';
?>

<div class="main-container">
    <div class="top-header">
        <div class="greeting">Module / <span style="color:var(--primary)">Periods</span></div>
        <div class="header-actions">
            <button class="btn-primary"><i class="ph ph-plus"></i> Add Record</button>
        </div>
    </div>

    <div class="crud-card">
        <div class="crud-header">
            <h2 class="crud-title">Manage Periods</h2>
        </div>
        <table class="crud-table">
            <thead>
                <tr>
                    <th>NAME</th>
                    <th>STATUS</th>
                    <th>ACTIONS</th>
                </tr>
            </thead>
            <tbody>
                <tr><td colspan="3" style="text-align:center; padding:40px; color:var(--text-muted);">No records have been added yet.</td></tr>
            </tbody>
        </table>
    </div>
</div>

<?php require ROOT_PATH . '/app/Views/school_admin/layout/footer.php'; ?>
