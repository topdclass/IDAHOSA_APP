<?php
require_once ROOT_PATH . '/config/database.php';
$message = ''; $error = '';

// 1. Handle Role Assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_role'])) {
    $emp_id = $_POST['employee_id'];
    $role_id = $_POST['role_id'];
    
    // Validate role exists in tenant DB (already linked)
    try {
        $stmt = $pdo->prepare("UPDATE institute_employees SET role_id = ? WHERE id = ?");
        $stmt->execute([$role_id ?: null, $emp_id]);
        $message = "Staff authority successfully updated in the Nexus.";
    } catch(Exception $e) { $error = "Nexus Error: " . $e->getMessage(); }
}

// 2. Data Retrieval
$allRoles = $pdo->query("SELECT id, role_name FROM roles ORDER BY role_name ASC")->fetchAll();

$employees = $pdo->query("
    SELECT e.id, u.full_name, e.role as old_role_label, e.role_id, r.role_name as nexus_role, e.employee_no
    FROM institute_employees e
    JOIN users u ON e.employee_id = u.id
    LEFT JOIN roles r ON e.role_id = r.id
    WHERE e.is_deleted = 0
    ORDER BY u.full_name ASC
")->fetchAll();

$pageTitle = 'Nexus Assignment - Staff Guard';
require ROOT_PATH . '/app/Views/school_admin/layout/header.php';
require ROOT_PATH . '/app/Views/school_admin/layout/sidebar.php';
?>

<style>
    .assign-card { background: white; border-radius: 20px; padding: 25px; border: 1px solid #f1f5f9; transition: 0.2s; display: flex; align-items: center; justify-content: space-between; margin-bottom: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.02); }
    .assign-card:hover { transform: translateY(-2px); box-shadow: 0 10px 25px -5px rgba(0,0,0,0.05); }
    
    .role-select { padding: 8px 15px; border-radius: 10px; border: 1.5px solid #f1f5f9; background: #f8fafc; font-size: 13px; font-weight: 700; color: #1e293b; outline: none; }
    .role-select:focus { border-color: var(--primary); background: white; }
    
    .btn-commit { background: var(--primary); color: white; border: none; padding: 10px 20px; border-radius: 10px; font-weight: 800; cursor: pointer; font-size: 13px; transition: 0.2s; }
    .btn-commit:hover { opacity: 0.9; transform: scale(1.02); }
</style>

<div class="main-container">
    <div class="top-header" style="justify-content: space-between; border-bottom: 1px solid #f1f5f9; margin-bottom: 30px; padding-bottom: 20px;">
        <div>
            <h1 style="font-size: 24px; font-weight: 900; margin: 0;">Faculty Privilege Assignment</h1>
            <p style="font-size: 14px; color: #64748b; margin-top: 5px;">Link registered staff members to their corresponding institutional roles and capabilities.</p>
        </div>
        <div style="background: white; border: 1px solid #f1f5f9; padding: 10px 20px; border-radius: 12px; font-weight: 800; font-size: 12px; color: #94a3b8;">
            Total Registered Faculty: <strong><?= count($employees) ?></strong>
        </div>
    </div>

    <?php if($message): ?>
        <div style="background:#f0fdf4; color:#166534; padding:15px 25px; border-radius:12px; margin-bottom:25px; border-left:4px solid #22c55e; font-weight:700;">
            <i class="ph ph-shield-check"></i> <?= $message ?>
        </div>
    <?php endif; ?>

    <div style="max-width: 900px;">
        <?php foreach($employees as $e): ?>
            <div class="assign-card">
                <div style="display:flex; align-items:center; gap:15px;">
                    <div style="width:45px; height:45px; background:#f0f3ff; border-radius:12px; display:flex; align-items:center; justify-content:center; color:var(--primary); font-size:20px;">
                        <i class="ph ph-user"></i>
                    </div>
                    <div>
                        <h4 style="margin:0; font-size:15px; font-weight:900;"><?= htmlspecialchars($e['full_name']) ?> <span style="font-size:11px; color:#94a3b8; font-weight:500;">(ID: <?= htmlspecialchars($e['employee_no']) ?>)</span></h4>
                        <p style="margin:5px 0 0 0; font-size:11px; color:#64748b; font-weight:700; text-transform:uppercase; letter-spacing:0.5px;">Current Assignment: <span style="color:<?= $e['nexus_role'] ? 'var(--primary)' : '#94a3b8' ?>;"><?= htmlspecialchars($e['nexus_role'] ?: ($e['old_role_label'] ?: 'Unassigned')) ?></span></p>
                    </div>
                </div>
                
                <form method="POST" style="display:flex; gap:10px; align-items:center;">
                    <input type="hidden" name="employee_id" value="<?= $e['id'] ?>">
                    <select name="role_id" class="role-select">
                        <option value="">-- No Privilege Role --</option>
                        <?php foreach($allRoles as $r): ?>
                            <option value="<?= $r['id'] ?>" <?= $e['role_id'] == $r['id'] ? 'selected' : '' ?>><?= htmlspecialchars($r['role_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" name="assign_role" class="btn-commit">Assign</button>
                </form>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php require ROOT_PATH . '/app/Views/school_admin/layout/footer.php'; ?>
