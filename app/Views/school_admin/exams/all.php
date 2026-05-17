<?php
require_once ROOT_PATH . '/config/database.php';
$exams = $pdo->query("SELECT e.*, c.class_name FROM exams e JOIN classes c ON e.class_id = c.id WHERE e.is_deleted = 0 ORDER BY e.created_at DESC")->fetchAll();
$pageTitle = 'All Exams Registry - Rosmon SMS';
require ROOT_PATH . '/app/Views/school_admin/layout/header.php';
require ROOT_PATH . '/app/Views/school_admin/layout/sidebar.php';
?>
<div class="main-container">
    <div class="top-header">
        <div class="greeting">Exams / <span style="color:var(--primary)">All Created Exams</span></div>
    </div>
    <div class="crud-card">
        <div class="crud-header"><h2 class="crud-title">Institutional Assessment Matrix</h2></div>
        <table class="crud-table">
            <thead>
                <tr>
                    <th>EXAM TITLE</th>
                    <th>CLASS</th>
                    <th>START DATE</th>
                    <th>DURATION</th>
                    <th>STATUS</th>
                    <th>ACTIONS</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($exams)): ?>
                    <tr><td colspan="6" style="text-align:center; padding:40px; color:var(--text-muted);">No exams found.</td></tr>
                <?php else: ?>
                    <?php foreach($exams as $e): ?>
                        <tr>
                            <td style="font-weight:700;"><?= htmlspecialchars((string)($e['title'] ?? '')) ?></td>
                            <td><?= htmlspecialchars((string)($e['class_name'] ?? '')) ?></td>
                            <td><?= date('Y-m-d', strtotime($e['start_date'])) ?></td>
                            <td><?= $e['duration'] ?> min</td>
                            <td><span style="background:#f0f3ff; color:var(--primary); padding:4px 10px; border-radius:20px; font-weight:700; font-size:10px;">PUBLISHED</span></td>
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
