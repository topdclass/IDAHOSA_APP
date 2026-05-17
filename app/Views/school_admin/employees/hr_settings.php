<?php
/**
 * HR Settings - Manage Departments and Official Designations
 */
require_once ROOT_PATH . '/config/database.php';

$message = '';
$error = '';

// ── Handle Form Submissions ────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_department'])) {
        $name = trim($_POST['dept_name'] ?? '');
        $code = trim($_POST['dept_code'] ?? '');
        if ($name) {
            $stmt = $pdo->prepare("INSERT INTO departments (dept_name, dept_code) VALUES (?, ?)");
            if ($stmt->execute([$name, $code])) $message = "Department added successfully!";
        }
    } elseif (isset($_POST['add_designation'])) {
        $name = trim($_POST['designation_name'] ?? '');
        $code = trim($_POST['designation_code'] ?? '');
        if ($name) {
            $stmt = $pdo->prepare("INSERT INTO designations (designation_name, designation_code) VALUES (?, ?)");
            if ($stmt->execute([$name, $code])) $message = "Designation added successfully!";
        }
    } elseif (isset($_POST['delete_dept'])) {
        $id = (int)($_POST['dept_id'] ?? 0);
        $pdo->prepare("DELETE FROM departments WHERE id = ?")->execute([$id]);
        $message = "Department removed!";
    } elseif (isset($_POST['delete_desig'])) {
        $id = (int)($_POST['desig_id'] ?? 0);
        $pdo->prepare("DELETE FROM designations WHERE id = ?")->execute([$id]);
        $message = "Designation removed!";
    }
}

// ── Fetch Current Lists ───────────────────────────────
$departments = $pdo->query("SELECT * FROM departments ORDER BY dept_name ASC")->fetchAll(PDO::FETCH_ASSOC);
$designations = $pdo->query("SELECT * FROM designations ORDER BY designation_name ASC")->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'HR Settings - Rosmon SMS';
require ROOT_PATH . '/app/Views/school_admin/layout/header.php';
require ROOT_PATH . '/app/Views/school_admin/layout/sidebar.php';
?>

<div class="main-container">
    <div class="top-header">
        <div class="greeting">HR / <span style="color:var(--primary)">Institutional Structure</span></div>
        <div class="header-actions">
           <a href="<?= WEB_ROOT ?>/school-admin/employees/add" class="btn-primary" style="background:#f3f4f6; color:var(--text-dark); text-decoration:none;">Back to Recruitment</a>
        </div>
    </div>

    <!-- Stats & Info -->
    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:24px; margin-bottom:30px;">
        <div class="info-card-slim" style="background:var(--white); padding:20px; border-radius:16px; border:1px solid var(--border);">
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <div>
                    <h4 style="font-size:12px; color:var(--text-muted); margin-bottom:5px;">ACTIVE DEPARTMENTS</h4>
                    <span style="font-size:24px; font-weight:700; color:var(--text-dark);"><?= count($departments) ?></span>
                </div>
                <i class="ph ph-buildings" style="font-size:32px; color:var(--primary); opacity:0.2;"></i>
            </div>
        </div>
        <div class="info-card-slim" style="background:var(--white); padding:20px; border-radius:16px; border:1px solid var(--border);">
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <div>
                    <h4 style="font-size:12px; color:var(--text-muted); margin-bottom:5px;">OFFICIAL DESIGNATIONS</h4>
                    <span style="font-size:24px; font-weight:700; color:var(--text-dark);"><?= count($designations) ?></span>
                </div>
                <i class="ph ph-briefcase" style="font-size:32px; color:var(--primary); opacity:0.2;"></i>
            </div>
        </div>
    </div>

    <?php if ($message): ?>
        <div style="background:#d1fae5; color:#065f46; padding:12px; border-radius:8px; margin-bottom:24px; font-weight:700; font-size:13px;">
            <i class="ph ph-check-circle"></i> <?= $message ?>
        </div>
    <?php endif; ?>

    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:30px;">
        
        <!-- Departments Column -->
        <div>
            <div class="crud-card" style="margin-bottom:24px;">
                <div class="crud-header"><h3 class="crud-title">Create New Department</h3></div>
                <form method="POST" style="padding:20px;">
                    <input type="hidden" name="add_department" value="1">
                    <label style="display:block; font-size:12px; font-weight:600; margin-bottom:5px;">DEPARTMENT NAME *</label>
                    <input type="text" name="dept_name" required placeholder="e.g. Science, Administration" style="width:100%; padding:10px; border:1px solid var(--border); border-radius:8px; font-size:13px; margin-bottom:15px; outline:none;">
                    
                    <label style="display:block; font-size:12px; font-weight:600; margin-bottom:5px;">DEPARTMENT CODE (Optional)</label>
                    <input type="text" name="dept_code" placeholder="e.g. SCI-01" style="width:100%; padding:10px; border:1px solid var(--border); border-radius:8px; font-size:13px; margin-bottom:20px; outline:none;">
                    
                    <button type="submit" class="btn-primary" style="width:100%;"><i class="ph ph-plus-circle"></i> Add Department</button>
                </form>
            </div>

            <div class="crud-card">
                <div class="crud-header"><h3 class="crud-title">Existing Departments</h3></div>
                <div style="overflow-x:auto;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Code</th>
                                <th style="text-align:right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($departments as $d): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($d['dept_name']) ?></strong></td>
                                    <td><span class="badge" style="background:#f3f4f6; color:#6b7280;"><?= htmlspecialchars($d['dept_code'] ?: 'N/A') ?></span></td>
                                    <td style="text-align:right;">
                                        <form method="POST" onsubmit="return confirm('Remove Department?');" style="display:inline;">
                                            <input type="hidden" name="delete_dept" value="1">
                                            <input type="hidden" name="dept_id" value="<?= $d['id'] ?>">
                                            <button type="submit" style="background:none; border:none; color:#ef4444; cursor:pointer;"><i class="ph ph-trash" style="font-size:18px;"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; if (empty($departments)): ?>
                                <tr><td colspan="3" style="text-align:center; padding:30px; color:var(--text-muted);">No departments defined yet.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Designations Column -->
        <div>
            <div class="crud-card" style="margin-bottom:24px;">
                <div class="crud-header"><h3 class="crud-title">Create New Designation</h3></div>
                <form method="POST" style="padding:20px;">
                    <input type="hidden" name="add_designation" value="1">
                    <label style="display:block; font-size:12px; font-weight:600; margin-bottom:5px;">OFFICIAL DESIGNATION *</label>
                    <input type="text" name="designation_name" required placeholder="e.g. Principal, Class Teacher" style="width:100%; padding:10px; border:1px solid var(--border); border-radius:8px; font-size:13px; margin-bottom:15px; outline:none;">
                    
                    <label style="display:block; font-size:12px; font-weight:600; margin-bottom:5px;">DESIGNATION CODE (Optional)</label>
                    <input type="text" name="designation_code" placeholder="e.g. PRIN" style="width:100%; padding:10px; border:1px solid var(--border); border-radius:8px; font-size:13px; margin-bottom:20px; outline:none;">
                    
                    <button type="submit" class="btn-primary" style="width:100%; background:linear-gradient(135deg, #6366f1, #4f46e5);"><i class="ph ph-briefcase"></i> Add Official Role</button>
                </form>
            </div>

            <div class="crud-card">
                <div class="crud-header"><h3 class="crud-title">Official Hierarchy</h3></div>
                <div style="overflow-x:auto;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Designation</th>
                                <th>Code</th>
                                <th style="text-align:right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($designations as $ds): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($ds['designation_name']) ?></strong></td>
                                    <td><span class="badge" style="background:#eef2ff; color:#6366f1;"><?= htmlspecialchars($ds['designation_code'] ?: 'N/A') ?></span></td>
                                    <td style="text-align:right;">
                                        <form method="POST" onsubmit="return confirm('Remove Designation?');" style="display:inline;">
                                            <input type="hidden" name="delete_desig" value="1">
                                            <input type="hidden" name="desig_id" value="<?= $ds['id'] ?>">
                                            <button type="submit" style="background:none; border:none; color:#ef4444; cursor:pointer;"><i class="ph ph-trash" style="font-size:18px;"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; if (empty($designations)): ?>
                                <tr><td colspan="3" style="text-align:center; padding:30px; color:var(--text-muted);">No designations defined yet.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>

<?php require ROOT_PATH . '/app/Views/school_admin/layout/footer.php'; ?>
