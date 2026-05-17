<?php
/**
 * Parents / Guardians Directory — School Admin (fixed schema)
 */
require_once ROOT_PATH . '/config/database.php';

$iWhere = $instituteId ? "AND p.institute_id = {$instituteId}" : '';

try {
    $parents = $pdo->query("
        SELECT p.id, p.father_name, p.mother_name, p.guardian_name,
               p.phone, p.email, p.address, p.occupation, p.created_at,
               GROUP_CONCAT(CONCAT(s.full_name,' (',c.class_name,')') SEPARATOR ' | ') AS children
        FROM institute_parents p
        LEFT JOIN institute_families f ON p.id = f.parent_id
        LEFT JOIN institute_students s ON f.student_id = s.student_id
        LEFT JOIN classes c ON s.class_id = c.id
        WHERE p.is_deleted = 0 {$iWhere}
        GROUP BY p.id
        ORDER BY COALESCE(p.guardian_name, p.father_name) ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $parents  = [];
    $dbError  = $e->getMessage();
}

$pageTitle = 'Parent Directory — RosmonSMS';
require APP_PATH . '/Views/school_admin/layout/header.php';
require APP_PATH . '/Views/school_admin/layout/sidebar.php';
?>
<div class="main-container">
    <div class="top-header">
        <div class="greeting">Parents / <span style="color:var(--primary)">Guardian Registry</span></div>
        <div class="header-actions">
            <a href="<?= WEB_ROOT ?>/school-admin/bulk-upload" class="btn-primary" style="background:#7c3aed;text-decoration:none;">
                <i class="ph ph-upload-simple"></i> Bulk Import
            </a>
            <a href="<?= WEB_ROOT ?>/school-admin/parents/add" class="btn-primary" style="text-decoration:none;">
                <i class="ph ph-plus"></i> Add Parent
            </a>
        </div>
    </div>

    <?php if (isset($dbError)): ?>
    <div style="background:#fef2f2;border:1px solid #fecaca;border-radius:8px;padding:12px;margin-bottom:16px;color:#dc2626;font-size:14px;">
        &#9888; <?= htmlspecialchars($dbError) ?>
    </div>
    <?php endif; ?>

    <!-- Search -->
    <div class="crud-card" style="padding:14px 18px;margin-bottom:16px;display:flex;gap:12px;align-items:center;">
        <input type="text" id="parentSearch" placeholder="&#128269;  Search by name, phone or email..."
               onkeyup="filterParents()"
               style="flex:1;padding:9px 14px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px;outline:none;">
        <span id="parentCount" style="font-size:13px;color:#94a3b8;"><?= count($parents) ?> parents</span>
    </div>

    <div class="crud-card">
        <div class="crud-header">
            <h2 class="crud-title">Parental Contact Registry</h2>
            <span style="font-size:13px;color:#94a3b8;"><?= count($parents) ?> registered</span>
        </div>
        <table class="crud-table" id="parentTable">
            <thead>
                <tr>
                    <th>GUARDIAN / FATHER</th>
                    <th>MOTHER</th>
                    <th>PHONE</th>
                    <th>EMAIL</th>
                    <th>OCCUPATION</th>
                    <th>CHILDREN</th>
                    <th>ACTIONS</th>
                </tr>
            </thead>
            <tbody id="parentBody">
                <?php if (empty($parents)): ?>
                <tr><td colspan="7" style="text-align:center;padding:48px;color:#94a3b8;">
                    <div style="font-size:32px;margin-bottom:12px;">&#128106;</div>
                    No parents registered yet.
                    <br><br>
                    <a href="<?= WEB_ROOT ?>/school-admin/bulk-upload" style="color:#7c3aed;font-weight:600;">&#8679; Bulk import parents</a>
                    &nbsp;or&nbsp;
                    <a href="<?= WEB_ROOT ?>/school-admin/parents/add" style="color:#2563eb;font-weight:600;">Add individually</a>
                </td></tr>
                <?php else: ?>
                <?php foreach ($parents as $p): ?>
                <?php
                    $displayName = $p['guardian_name'] ?: ($p['father_name'] ?: '—');
                ?>
                <tr data-search="<?= strtolower(htmlspecialchars($displayName . ' ' . ($p['phone']??'') . ' ' . ($p['email']??''))) ?>">
                    <td>
                        <div style="font-weight:600;"><?= htmlspecialchars($displayName) ?></div>
                        <?php if ($p['father_name'] && $p['guardian_name'] !== $p['father_name']): ?>
                        <div style="font-size:12px;color:#94a3b8;">Father: <?= htmlspecialchars($p['father_name']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td style="color:#64748b;"><?= htmlspecialchars($p['mother_name'] ?: '—') ?></td>
                    <td><?= htmlspecialchars($p['phone'] ?: '—') ?></td>
                    <td style="font-size:12px;"><?= htmlspecialchars($p['email'] ?: '—') ?></td>
                    <td style="color:#64748b;"><?= htmlspecialchars($p['occupation'] ?: '—') ?></td>
                    <td style="font-size:12px;color:#059669;">
                        <?= $p['children'] ? nl2br(htmlspecialchars($p['children'])) : '<span style="color:#94a3b8;">None linked</span>' ?>
                    </td>
                    <td>
                        <div style="display:flex;gap:6px;">
                            <a href="<?= WEB_ROOT ?>/school-admin/parents/edit?id=<?= $p['id'] ?>"
                               style="padding:5px 10px;background:#eff6ff;color:#2563eb;border-radius:6px;text-decoration:none;font-size:12px;font-weight:600;">Edit</a>
                            <a href="<?= WEB_ROOT ?>/school-admin/parents/families?pid=<?= $p['id'] ?>"
                               style="padding:5px 10px;background:#f0fdf4;color:#16a34a;border-radius:6px;text-decoration:none;font-size:12px;font-weight:600;">Family</a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<script>
function filterParents() {
    const q = document.getElementById('parentSearch').value.toLowerCase();
    const rows = document.querySelectorAll('#parentBody tr[data-search]');
    let count = 0;
    rows.forEach(r => {
        const show = !q || r.dataset.search.includes(q);
        r.style.display = show ? '' : 'none';
        if (show) count++;
    });
    document.getElementById('parentCount').textContent = count + ' parents';
}
</script>
<?php require APP_PATH . '/Views/school_admin/layout/footer.php'; ?>
