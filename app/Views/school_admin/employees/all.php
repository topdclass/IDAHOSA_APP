<?php
/**
 * All Employees Listing — School Admin
 * Uses institute_employees (per-tenant) for all staff data
 */
require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/config/database.php';

$iWhere = $instituteId ? "AND ie.institute_id = {$instituteId}" : '';

try {
    // Ensure HR support tables exist in tenant DB
    $pdo->exec("CREATE TABLE IF NOT EXISTS `departments` (
      `id` int NOT NULL AUTO_INCREMENT,
      `dept_name` varchar(100) NOT NULL,
      `dept_code` varchar(50) DEFAULT NULL,
      `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `designations` (
      `id` int NOT NULL AUTO_INCREMENT,
      `designation_name` varchar(100) NOT NULL,
      `designation_code` varchar(50) DEFAULT NULL,
      `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Fetch employees from institute_employees joined with users (tenant DB)
    $employees = $pdo->query("
        SELECT ie.id, u.full_name, ie.employee_no, ie.gender, ie.phone,
               ie.department, ie.designation, ie.salary, ie.hire_date,
               ie.status, u.email, u.role as system_role,
               ie.institute_id
        FROM institute_employees ie
        JOIN users u ON ie.employee_id = u.id
        WHERE ie.is_deleted = 0 {$iWhere}
        ORDER BY u.full_name ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Dept/designation counts for filter chips
    $deptList  = $pdo->query("SELECT DISTINCT department FROM institute_employees WHERE is_deleted=0 AND department != '' ORDER BY department")->fetchAll(PDO::FETCH_COLUMN);
    $roleCount = count($employees);

} catch (PDOException $e) {
    $employees = [];
    $deptList  = [];
    $roleCount = 0;
    $dbError   = $e->getMessage();
}

// Department filter
$filterDept = $_GET['dept'] ?? '';

$pageTitle = 'Employee Directory — RosmonSMS';
require APP_PATH . '/Views/school_admin/layout/header.php';
require APP_PATH . '/Views/school_admin/layout/sidebar.php';
?>

<div class="main-container">
    <div class="top-header">
        <div class="greeting">HR Management / <span style="color:var(--primary)">Staff &amp; Faculty Directory</span></div>
        <div class="header-actions">
            <a href="<?= WEB_ROOT ?>/school-admin/bulk-upload" class="btn-primary" style="background:#7c3aed;text-decoration:none;">
                <i class="ph ph-upload-simple"></i> Bulk Import Staff
            </a>
            <a href="<?= WEB_ROOT ?>/school-admin/employees/add" class="btn-primary" style="text-decoration:none;">
                <i class="ph ph-user-plus"></i> Add Staff
            </a>
        </div>
    </div>

    <?php if (isset($dbError)): ?>
    <div style="background:#fef2f2;border:1px solid #fecaca;border-radius:8px;padding:12px 16px;margin-bottom:16px;color:#dc2626;font-size:14px;">
        &#9888; Database error: <?= htmlspecialchars($dbError) ?>
    </div>
    <?php endif; ?>

    <!-- Stats row -->
    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:24px;">
        <?php
        $roles = ['teacher'=>0, 'principal'=>0, 'support_staff'=>0, 'other'=>0];
        foreach ($employees as $emp) {
            $r = $emp['system_role'] ?? 'other';
            if (isset($roles[$r])) $roles[$r]++;
            else $roles['other']++;
        }
        $statItems = [
            ['label'=>'Total Staff',     'value'=>count($employees),   'color'=>'#2563eb', 'icon'=>'ph-users'],
            ['label'=>'Teachers',        'value'=>$roles['teacher'],   'color'=>'#7c3aed', 'icon'=>'ph-chalkboard-teacher'],
            ['label'=>'Management',      'value'=>$roles['principal'], 'color'=>'#059669', 'icon'=>'ph-crown'],
            ['label'=>'Support Staff',   'value'=>$roles['support_staff'],'color'=>'#d97706','icon'=>'ph-wrench'],
        ];
        foreach ($statItems as $si):
        ?>
        <div class="crud-card" style="padding:18px;display:flex;align-items:center;gap:14px;">
            <div style="width:42px;height:42px;background:<?= $si['color'] ?>1a;color:<?= $si['color'] ?>;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0;">
                <i class="ph <?= $si['icon'] ?>"></i>
            </div>
            <div>
                <div style="font-size:11px;font-weight:700;color:#94a3b8;"><?= strtoupper($si['label']) ?></div>
                <div style="font-size:22px;font-weight:800;"><?= $si['value'] ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Search + Filter -->
    <div class="crud-card" style="padding:16px 20px;margin-bottom:16px;display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
        <input type="text" id="staffSearch" placeholder="&#128269;  Search staff by name, email, role..."
               onkeyup="filterStaff()"
               style="flex:1;min-width:200px;padding:9px 14px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px;outline:none;">
        <select id="roleFilter" onchange="filterStaff()"
                style="padding:9px 14px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px;outline:none;">
            <option value="">All Roles</option>
            <option value="teacher">Teacher</option>
            <option value="principal">Principal</option>
            <option value="vice_principal">Vice Principal</option>
            <option value="school_admin">Administrator</option>
            <option value="accountant">Accountant</option>
            <option value="support_staff">Support Staff</option>
        </select>
        <select id="deptFilter" onchange="filterStaff()"
                style="padding:9px 14px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px;outline:none;">
            <option value="">All Departments</option>
            <?php foreach ($deptList as $dept): ?>
            <option value="<?= htmlspecialchars($dept) ?>"><?= htmlspecialchars($dept) ?></option>
            <?php endforeach; ?>
        </select>
        <span id="staffCount" style="font-size:13px;color:#94a3b8;white-space:nowrap;"><?= count($employees) ?> staff found</span>
    </div>

    <div class="crud-card">
        <div class="crud-header">
            <h2 class="crud-title">Active Workforce Matrix</h2>
            <a href="<?= WEB_ROOT ?>/school-admin/employees/id-cards" class="btn-primary" style="text-decoration:none;background:#0891b2;font-size:12px;">
                <i class="ph ph-identification-card"></i> Print ID Cards
            </a>
        </div>

        <table class="crud-table" id="staffTable">
            <thead>
                <tr>
                    <th>STAFF ID</th>
                    <th>FULL NAME</th>
                    <th>SYSTEM ROLE</th>
                    <th>DEPARTMENT</th>
                    <th>DESIGNATION</th>
                    <th>CONTACT</th>
                    <th>SALARY</th>
                    <th>STATUS</th>
                    <th>ACTIONS</th>
                </tr>
            </thead>
            <tbody id="staffBody">
                <?php if (empty($employees)): ?>
                    <tr><td colspan="9" style="text-align:center;padding:48px;color:#94a3b8;">
                        <div style="font-size:32px;margin-bottom:12px;">&#128101;</div>
                        No staff records found.
                        <br><br>
                        <a href="<?= WEB_ROOT ?>/school-admin/bulk-upload" style="color:#7c3aed;font-weight:600;">
                            &#8679; Bulk import your staff from CSV
                        </a>
                        &nbsp;or&nbsp;
                        <a href="<?= WEB_ROOT ?>/school-admin/employees/add" style="color:#2563eb;font-weight:600;">
                            Add individually
                        </a>
                    </td></tr>
                <?php else: ?>
                    <?php foreach ($employees as $emp): ?>
                    <tr data-name="<?= strtolower(htmlspecialchars($emp['full_name'])) ?>"
                        data-email="<?= strtolower(htmlspecialchars($emp['email'])) ?>"
                        data-role="<?= strtolower($emp['system_role'] ?? '') ?>"
                        data-dept="<?= strtolower($emp['department'] ?? '') ?>">
                        <td><code style="font-size:12px;background:#f1f5f9;padding:3px 8px;border-radius:4px;"><?= htmlspecialchars($emp['employee_no'] ?? '—') ?></code></td>
                        <td>
                            <div style="font-weight:600;"><?= htmlspecialchars($emp['full_name']) ?></div>
                            <div style="font-size:12px;color:#94a3b8;"><?= htmlspecialchars($emp['email']) ?></div>
                        </td>
                        <td>
                            <?php
                            $roleColors = [
                                'teacher'=>'#7c3aed','principal'=>'#059669','vice_principal'=>'#0891b2',
                                'school_admin'=>'#2563eb','accountant'=>'#d97706','support_staff'=>'#6b7280',
                            ];
                            $role = $emp['system_role'] ?? 'staff';
                            $rColor = $roleColors[$role] ?? '#6b7280';
                            $rLabel = ucwords(str_replace('_',' ',$role));
                            ?>
                            <span style="background:<?= $rColor ?>1a;color:<?= $rColor ?>;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;">
                                <?= $rLabel ?>
                            </span>
                        </td>
                        <td style="color:#475569;"><?= htmlspecialchars($emp['department'] ?? '—') ?></td>
                        <td style="color:#475569;"><?= htmlspecialchars($emp['designation'] ?? '—') ?></td>
                        <td style="font-size:12px;">
                            <?= htmlspecialchars($emp['phone'] ?? '—') ?>
                        </td>
                        <td style="font-weight:600;color:#059669;">
                            <?= $emp['salary'] > 0 ? '₦'.number_format((float)$emp['salary'],0) : '—' ?>
                        </td>
                        <td>
                            <?php $status = $emp['status'] ?? 'Active'; ?>
                            <span style="background:<?= $status==='Active'?'#dcfce7':'#fee2e2' ?>;color:<?= $status==='Active'?'#16a34a':'#dc2626' ?>;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;">
                                <?= $status ?>
                            </span>
                        </td>
                        <td>
                            <div style="display:flex;gap:6px;align-items:center;">
                                <a href="<?= WEB_ROOT ?>/school-admin/employees/edit?id=<?= $emp['id'] ?>"
                                   style="padding:5px 10px;background:#eff6ff;color:#2563eb;border-radius:6px;text-decoration:none;font-size:12px;font-weight:600;">
                                   Edit
                                </a>
                                <a href="<?= WEB_ROOT ?>/school-admin/employees/id-cards?emp=<?= $emp['id'] ?>"
                                   style="padding:5px 10px;background:#f0fdf4;color:#16a34a;border-radius:6px;text-decoration:none;font-size:12px;font-weight:600;">
                                   ID
                                </a>
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
function filterStaff() {
    const search = document.getElementById('staffSearch').value.toLowerCase();
    const role   = document.getElementById('roleFilter').value.toLowerCase();
    const dept   = document.getElementById('deptFilter').value.toLowerCase();
    const rows   = document.querySelectorAll('#staffBody tr[data-name]');
    let count = 0;

    rows.forEach(row => {
        const name  = row.dataset.name  || '';
        const email = row.dataset.email || '';
        const rRole = row.dataset.role  || '';
        const rDept = row.dataset.dept  || '';

        const matchSearch = !search || name.includes(search) || email.includes(search);
        const matchRole   = !role   || rRole.includes(role);
        const matchDept   = !dept   || rDept.includes(dept);

        if (matchSearch && matchRole && matchDept) {
            row.style.display = '';
            count++;
        } else {
            row.style.display = 'none';
        }
    });
    document.getElementById('staffCount').textContent = count + ' staff found';
}
</script>

<?php require APP_PATH . '/Views/school_admin/layout/footer.php'; ?>
