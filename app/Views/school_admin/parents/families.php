<?php
require_once ROOT_PATH . '/config/database.php';
$families = $pdo->query("SELECT * FROM institute_families WHERE is_deleted = 0 ORDER BY family_name ASC")->fetchAll();
$pageTitle = 'Manage Families - Rosmon SMS';
require ROOT_PATH . '/app/Views/school_admin/layout/header.php';
require ROOT_PATH . '/app/Views/school_admin/layout/sidebar.php';
?>
<div class="main-container">
    <div class="top-header">
        <div class="greeting">Families / <span style="color:var(--primary)">Institutional Families Registry</span></div>
    </div>
    <div class="crud-card">
        <div class="crud-header"><h2 class="crud-title">Manage Family Groups</h2></div>
        <table class="crud-table">
            <thead>
                <tr>
                    <th>FAMILY NO.</th>
                    <th>FAMILY NAME</th>
                    <th>MEMBERS</th>
                    <th>DATE CREATED</th>
                    <th>ACTIONS</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($families)): ?>
                    <tr><td colspan="5" style="text-align:center; padding:40px; color:var(--text-muted);">No family records found.</td></tr>
                <?php else: ?>
                    <?php foreach($families as $f): ?>
                        <tr>
                            <td style="font-weight:800;"><?= htmlspecialchars((string)($f['family_no'] ?? '')) ?></td>
                            <td style="font-weight:700;"><?= htmlspecialchars((string)($f['family_name'] ?? '')) ?></td>
                            <td>N/A</td>
                            <td><?= date('Y-m-d', strtotime($f['created_at'])) ?></td>
                            <td>
                                <div style="display:flex; gap:12px;">
                                    <a href="#" class="text-primary"><i class="ph ph-eye"></i></a>
                                    <a href="#" class="text-success"><i class="ph ph-pencil"></i></a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require ROOT_PATH . '/app/Views/school_admin/layout/footer.php'; ?>
