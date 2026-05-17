<?php
require_once ROOT_PATH . '/config/database.php';
$message = ''; $error = '';

// 1. Handle Role Creation / Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_role'])) {
    $role_id = $_POST['role_id'] ?? null;
    $role_name = $_POST['role_name'];
    $desc = $_POST['description'] ?? '';
    $perms = $_POST['perms'] ?? [];

    try {
        $pdo->beginTransaction();
        
        if ($role_id) {
            $stmt = $pdo->prepare("UPDATE roles SET role_name = ?, description = ? WHERE id = ?");
            $stmt->execute([$role_name, $desc, $role_id]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO roles (role_name, description) VALUES (?, ?)");
            $stmt->execute([$role_name, $desc]);
            $role_id = $pdo->lastInsertId();
        }

        // Sync Permissions
        $pdo->prepare("DELETE FROM role_permissions WHERE role_id = ?")->execute([$role_id]);
        $ins = $pdo->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)");
        foreach($perms as $pid) $ins->execute([$role_id, $pid]);

        $pdo->commit();
        $message = "Nexus Role '$role_name' configured successfully!";
    } catch(Exception $e) { $pdo->rollBack(); $error = "System Conflict: " . $e->getMessage(); }
}

// 2. Fetch All Roles & All Permissions
$allRoles = $pdo->query("SELECT * FROM roles ORDER BY role_name ASC")->fetchAll();
$allPerms = $pdo->query("SELECT * FROM permissions ORDER BY perm_name ASC")->fetchAll();

// Mapping for checkboxes
$rolePermsMap = [];
$rpRaw = $pdo->query("SELECT role_id, permission_id FROM role_permissions")->fetchAll();
foreach($rpRaw as $rp) $rolePermsMap[$rp['role_id']][] = $rp['permission_id'];

$pageTitle = 'Role Nexus - Privileges & Guard';
require ROOT_PATH . '/app/Views/school_admin/layout/header.php';
require ROOT_PATH . '/app/Views/school_admin/layout/sidebar.php';
?>

<style>
    .rbac-grid { display: grid; grid-template-columns: 1fr 2fr; gap: 30px; }
    .card { background: white; border-radius: 20px; padding: 30px; border: 1px solid #f1f5f9; box-shadow: 0 4px 15px rgba(0,0,0,0.02); }
    .perm-chip { display: inline-flex; align-items: center; gap: 8px; background: #f8fafc; border: 1px solid #eef2f6; padding: 6px 14px; border-radius: 12px; font-size: 11px; font-weight: 700; color: #475569; margin: 4px; }
    
    input[type="checkbox"] { width: auto; margin-right: 8px; cursor: pointer; }
    .perm-item { display: flex; align-items: center; padding: 12px; border-bottom: 1px solid #f8fafc; transition: 0.2s; }
    .perm-item:hover { background: #f0f3ff; color: var(--primary); }
    
    .btn-save { background: var(--primary); color: white; border: none; padding: 14px 25px; border-radius: 12px; font-weight: 800; cursor: pointer; display: flex; align-items: center; gap: 10px; box-shadow: 0 10px 15px -3px rgba(16, 115, 229, 0.3); }
</style>

<div class="main-container">
    <div class="top-header" style="justify-content: space-between; border-bottom: 1px solid #f1f5f9; margin-bottom: 30px; padding-bottom: 20px;">
        <div>
            <h1 style="font-size: 24px; font-weight: 900; margin: 0;">Institutional Security Nexus</h1>
            <p style="font-size: 14px; color: #64748b; margin-top: 5px;">Define discrete roles and grant targeted capabilities for your school faculty.</p>
        </div>
        <button onclick="newRole()" class="btn-save"><i class="ph ph-plus"></i> Initialize New Role</button>
    </div>

    <?php if($message): ?>
        <div style="background:#f0fdf4; color:#166534; padding:15px 25px; border-radius:12px; margin-bottom:25px; border-left:4px solid #22c55e; font-weight:700;">
            <i class="ph ph-shield-check"></i> <?= $message ?>
        </div>
    <?php endif; ?>

    <div class="rbac-grid">
        <div class="col-roles">
            <h3 style="font-size: 15px; font-weight: 800; color: #1e293b; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                <i class="ph ph-users-four" style="color:var(--primary);"></i> Defined Roles
            </h3>
            <div style="display: flex; flex-direction: column; gap: 15px;">
                <?php foreach($allRoles as $r): ?>
                    <div class="card" style="padding: 20px; cursor: pointer;" onclick="editRole(<?= htmlspecialchars(json_encode($r)) ?>, <?= htmlspecialchars(json_encode($rolePermsMap[$r['id']] ?? [])) ?>)">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                            <h4 style="margin: 0; font-size: 16px; font-weight: 900; color: #1e293b;"><?= htmlspecialchars($r['role_name']) ?></h4>
                            <i class="ph ph-lock-key" style="color:#cbd5e1;"></i>
                        </div>
                        <p style="font-size: 12px; color: #64748b; margin-bottom: 15px;"><?= htmlspecialchars($r['description']) ?></p>
                        <div style="display: flex; flex-wrap: wrap;">
                            <span style="font-size: 10px; font-weight: 800; color: var(--primary); background: #f0f3ff; padding: 4px 10px; border-radius: 8px;">
                                <?= count($rolePermsMap[$r['id']] ?? []) ?> Privileges Assigned
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="col-configure">
            <div class="card" id="roleEditCard" style="display: none;">
                <h3 id="formTitle" style="font-size: 18px; font-weight: 900; margin-bottom: 25px; color: #1e293b;">Role Identity Configuration</h3>
                <form method="POST">
                    <input type="hidden" name="role_id" id="f_role_id">
                    <div style="display: grid; grid-template-columns: 1fr 1.5fr; gap: 30px;">
                        <div>
                            <label style="font-size: 11px; font-weight: 800; color: #94a3b8; display: block; margin-bottom: 8px; text-transform: uppercase;">Role Label</label>
                            <input type="text" name="role_name" id="f_role_name" required placeholder="e.g. Finance Officer" style="width:100%; padding:14px; border:1.5px solid #f1f5f9; border-radius:12px; margin-bottom:20px; background:#f8fafc; font-weight:700;">
                            
                            <label style="font-size: 11px; font-weight: 800; color: #94a3b8; display: block; margin-bottom: 8px; text-transform: uppercase;">Functional Scope</label>
                            <textarea name="description" id="f_description" rows="4" placeholder="Describe the responsibilities..." style="width:100%; padding:14px; border:1.5px solid #f1f5f9; border-radius:12px; margin-bottom:20px; background:#f8fafc; font-weight:500;"></textarea>
                            
                            <button type="submit" name="save_role" class="btn-save" style="width: 100%;">
                                <i class="ph-fill ph-shield-plus"></i> Persist Configuration
                            </button>
                        </div>
                        <div>
                            <label style="font-size: 11px; font-weight: 800; color: #94a3b8; display: block; margin-bottom: 12px; text-transform: uppercase;">Capability Nexus (Privileges)</label>
                            <div style="background: #f8fafc; border: 1.5px solid #f1f5f9; border-radius: 16px; overflow: hidden; max-height: 400px; overflow-y: auto;">
                                <?php foreach($allPerms as $p): ?>
                                    <label class="perm-item" style="cursor: pointer;">
                                        <input type="checkbox" name="perms[]" value="<?= $p['id'] ?>" class="perm-checkbox">
                                        <div>
                                            <div style="font-weight: 800; font-size: 13px;"><?= htmlspecialchars($p['perm_name']) ?></div>
                                            <div style="font-size: 10px; color: #94a3b8; font-weight: 700;"><?= strtoupper($p['perm_key']) ?></div>
                                        </div>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            
            <div id="noSelection" style="border: 2px dashed #f1f5f9; border-radius: 24px; padding: 100px 40px; text-align: center; color: #94a3b8;">
                <i class="ph ph-shield-slash" style="font-size: 64px; opacity: 0.1; margin-bottom: 20px;"></i>
                <h3 style="margin:0; font-weight: 900;">Nexus Dormant</h3>
                <p style="font-size: 14px; margin-top: 10px;">Select a role from the list or click "Initialize New Role" to configure staff capabilities.</p>
            </div>
        </div>
    </div>
</div>

<script>
function newRole() {
    document.getElementById('noSelection').style.display = 'none';
    document.getElementById('roleEditCard').style.display = 'block';
    document.getElementById('formTitle').innerText = 'Initialize Strategic Role';
    document.getElementById('f_role_id').value = '';
    document.getElementById('f_role_name').value = '';
    document.getElementById('f_description').value = '';
    document.querySelectorAll('.perm-checkbox').forEach(cb => cb.checked = false);
}

function editRole(role, perms) {
    document.getElementById('noSelection').style.display = 'none';
    document.getElementById('roleEditCard').style.display = 'block';
    document.getElementById('formTitle').innerText = 'Adjust Capability Nexus: ' + role.role_name;
    document.getElementById('f_role_id').value = role.id;
    document.getElementById('f_role_name').value = role.role_name;
    document.getElementById('f_description').value = role.description;
    
    document.querySelectorAll('.perm-checkbox').forEach(cb => {
        cb.checked = perms.includes(parseInt(cb.value));
    });
}
</script>

<?php require ROOT_PATH . '/app/Views/school_admin/layout/footer.php'; ?>
