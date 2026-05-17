<?php
/**
 * All Students Listing — School Admin (fixed for institute_students schema)
 */
require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/config/database.php';

$iWhere      = $instituteId ? "AND s.institute_id = {$instituteId}" : '';
$classFilter = $_GET['class_id'] ?? null;

try {
    // Classes for filter dropdown (scoped to institute)
    $classStmt = $pdo->prepare("SELECT id, class_name, section FROM classes WHERE is_deleted=0 " .
        ($instituteId ? "AND institute_id={$instituteId}" : '') . " ORDER BY class_name, section ASC");
    $classStmt->execute();
    $classes = $classStmt->fetchAll(PDO::FETCH_ASSOC);

    // Build query — institute_students has all student info directly
    $sql = "SELECT s.student_id as id, s.full_name, s.student_no, s.admission_no,
                   s.gender, s.dob, s.phone, s.email, s.admission_date, s.status,
                   c.class_name, c.section
            FROM institute_students s
            LEFT JOIN classes c ON s.class_id = c.id
            WHERE s.is_deleted = 0 {$iWhere}";

    if ($classFilter) {
        $sql .= " AND s.class_id = " . (int)$classFilter;
    }
    $sql .= " ORDER BY s.full_name ASC";

    $students = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

    // Gender counts
    $genderCounts = ['Male'=>0,'Female'=>0,'Other'=>0];
    foreach ($students as $st) {
        $g = ucfirst(strtolower($st['gender'] ?? 'other'));
        $genderCounts[$g] = ($genderCounts[$g] ?? 0) + 1;
    }

} catch (PDOException $e) {
    $students = $classes = [];
    $genderCounts = ['Male'=>0,'Female'=>0,'Other'=>0];
    $dbError = $e->getMessage();
}

$pageTitle = 'Student Directory — RosmonSMS';
require APP_PATH . '/Views/school_admin/layout/header.php';
require APP_PATH . '/Views/school_admin/layout/sidebar.php';
?>

<div class="main-container">
    <div class="top-header">
        <div class="greeting">Academics / <span style="color:var(--primary)">Student Directory</span></div>
        <div class="header-actions">
            <a href="<?= WEB_ROOT ?>/school-admin/bulk-upload" class="btn-primary" style="background:#7c3aed;text-decoration:none;">
                <i class="ph ph-upload-simple"></i> Bulk Import
            </a>
            <a href="<?= WEB_ROOT ?>/school-admin/students/add" class="btn-primary" style="text-decoration:none;">
                <i class="ph ph-user-plus"></i> Add Student
            </a>
        </div>
    </div>

    <?php if (isset($dbError)): ?>
    <div style="background:#fef2f2;border:1px solid #fecaca;border-radius:8px;padding:12px;margin-bottom:16px;color:#dc2626;font-size:14px;">
        &#9888; <?= htmlspecialchars($dbError) ?>
    </div>
    <?php endif; ?>

    <!-- Stats -->
    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:24px;">
        <?php
        $cards = [
            ['label'=>'Total Students','value'=>count($students),'color'=>'#7c3aed','icon'=>'ph-student'],
            ['label'=>'Male','value'=>$genderCounts['Male'],'color'=>'#2563eb','icon'=>'ph-gender-male'],
            ['label'=>'Female','value'=>$genderCounts['Female'],'color'=>'#be185d','icon'=>'ph-gender-female'],
            ['label'=>'Classes','value'=>count($classes),'color'=>'#059669','icon'=>'ph-chalkboard-teacher'],
        ];
        foreach ($cards as $c):
        ?>
        <div class="crud-card" style="padding:18px;display:flex;align-items:center;gap:14px;">
            <div style="width:42px;height:42px;background:<?= $c['color'] ?>1a;color:<?= $c['color'] ?>;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:20px;">
                <i class="ph <?= $c['icon'] ?>"></i>
            </div>
            <div>
                <div style="font-size:11px;font-weight:700;color:#94a3b8;"><?= strtoupper($c['label']) ?></div>
                <div style="font-size:22px;font-weight:800;"><?= number_format($c['value']) ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Filter bar -->
    <div class="crud-card" style="padding:14px 18px;margin-bottom:16px;display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
        <input type="text" id="studSearch" placeholder="&#128269;  Search by name, admission no, email..."
               onkeyup="filterStudents()"
               style="flex:1;min-width:200px;padding:9px 14px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px;outline:none;">
        <select id="classFilter" onchange="filterStudents()"
                style="padding:9px 14px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px;outline:none;">
            <option value="">All Classes</option>
            <?php foreach ($classes as $cls): ?>
            <option value="<?= htmlspecialchars($cls['class_name'].($cls['section']?' '.$cls['section']:'')) ?>">
                <?= htmlspecialchars($cls['class_name'] . ($cls['section'] ? ' — '.$cls['section'] : '')) ?>
            </option>
            <?php endforeach; ?>
        </select>
        <select id="genderFilter" onchange="filterStudents()"
                style="padding:9px 14px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px;outline:none;">
            <option value="">All Genders</option>
            <option value="male">Male</option>
            <option value="female">Female</option>
        </select>
        <span id="studCount" style="font-size:13px;color:#94a3b8;white-space:nowrap;"><?= count($students) ?> students</span>
    </div>

    <div class="crud-card">
        <div class="crud-header">
            <h2 class="crud-title">Student Enrollment Register</h2>
            <div style="display:flex;gap:8px;">
                <a href="<?= WEB_ROOT ?>/school-admin/students/id-cards" class="btn-primary" style="text-decoration:none;background:#0891b2;font-size:12px;">
                    <i class="ph ph-identification-card"></i> ID Cards
                </a>
                <a href="<?= WEB_ROOT ?>/school-admin/students/promote" class="btn-primary" style="text-decoration:none;background:#059669;font-size:12px;">
                    <i class="ph ph-arrow-up"></i> Promote
                </a>
            </div>
        </div>

        <table class="crud-table" id="studTable">
            <thead>
                <tr>
                    <th>ADM. NO.</th>
                    <th>FULL NAME</th>
                    <th>CLASS</th>
                    <th>GENDER</th>
                    <th>DATE OF BIRTH</th>
                    <th>PHONE / CONTACT</th>
                    <th>ADMITTED</th>
                    <th>ACTIONS</th>
                </tr>
            </thead>
            <tbody id="studBody">
                <?php if (empty($students)): ?>
                    <tr><td colspan="8" style="text-align:center;padding:48px;color:#94a3b8;">
                        <div style="font-size:32px;margin-bottom:12px;">&#127891;</div>
                        No students enrolled yet.
                        <br><br>
                        <a href="<?= WEB_ROOT ?>/school-admin/bulk-upload" style="color:#7c3aed;font-weight:600;">
                            &#8679; Bulk import students from CSV
                        </a>
                        &nbsp;or&nbsp;
                        <a href="<?= WEB_ROOT ?>/school-admin/students/add" style="color:#2563eb;font-weight:600;">Add individually</a>
                    </td></tr>
                <?php else: ?>
                    <?php foreach ($students as $st): ?>
                    <?php
                        $className = trim(($st['class_name'] ?? '') . ' ' . ($st['section'] ?? ''));
                    ?>
                    <tr data-name="<?= strtolower(htmlspecialchars($st['full_name'])) ?>"
                        data-adm="<?= strtolower($st['admission_no'] ?? '') ?>"
                        data-email="<?= strtolower($st['email'] ?? '') ?>"
                        data-class="<?= strtolower($className) ?>"
                        data-gender="<?= strtolower($st['gender'] ?? '') ?>">
                        <td><code style="background:#f1f5f9;padding:3px 8px;border-radius:4px;font-size:12px;"><?= htmlspecialchars($st['admission_no'] ?? $st['student_no'] ?? '—') ?></code></td>
                        <td>
                            <div style="font-weight:600;"><?= htmlspecialchars($st['full_name']) ?></div>
                            <div style="font-size:12px;color:#94a3b8;"><?= htmlspecialchars($st['email'] ?? '') ?></div>
                        </td>
                        <td>
                            <span style="background:#f0fdf4;color:#15803d;padding:3px 10px;border-radius:20px;font-size:12px;font-weight:600;">
                                <?= htmlspecialchars($className ?: '—') ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars(ucfirst(strtolower($st['gender'] ?? '—'))) ?></td>
                        <td style="color:#64748b;">
                            <?= $st['dob'] ? date('M d, Y', strtotime($st['dob'])) : '—' ?>
                        </td>
                        <td style="font-size:13px;"><?= htmlspecialchars($st['phone'] ?? '—') ?></td>
                        <td style="color:#64748b;font-size:12px;">
                            <?= $st['admission_date'] ? date('M d, Y', strtotime($st['admission_date'])) : '—' ?>
                        </td>
                        <td>
                            <div style="display:flex;gap:6px;">
                                <a href="<?= WEB_ROOT ?>/school-admin/students/view?id=<?= $st['id'] ?>"
                                   style="padding:5px 10px;background:#eff6ff;color:#2563eb;border-radius:6px;text-decoration:none;font-size:12px;font-weight:600;">View</a>
                                <a href="<?= WEB_ROOT ?>/school-admin/students/admission-letter?id=<?= $st['id'] ?>"
                                   style="padding:5px 10px;background:#f0fdf4;color:#16a34a;border-radius:6px;text-decoration:none;font-size:12px;font-weight:600;">Letter</a>
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
function filterStudents() {
    const search  = document.getElementById('studSearch').value.toLowerCase();
    const cls     = document.getElementById('classFilter').value.toLowerCase();
    const gender  = document.getElementById('genderFilter').value.toLowerCase();
    const rows    = document.querySelectorAll('#studBody tr[data-name]');
    let count = 0;

    rows.forEach(row => {
        const matchSearch = !search || row.dataset.name.includes(search) ||
                            (row.dataset.adm||'').includes(search) ||
                            (row.dataset.email||'').includes(search);
        const matchClass  = !cls    || (row.dataset.class||'').includes(cls);
        const matchGender = !gender || (row.dataset.gender||'').includes(gender);

        if (matchSearch && matchClass && matchGender) { row.style.display=''; count++; }
        else row.style.display = 'none';
    });
    document.getElementById('studCount').textContent = count + ' students';
}
</script>

<?php require APP_PATH . '/Views/school_admin/layout/footer.php'; ?>
