<?php
require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/config/tenant_manager.php';

// $supervisorPdo is the central DB
// $pdo is the tenant DB context (initialized by database.php, but we will override it here for the specific school)

$schoolId = isset($_GET['id']) ? (int)$_GET['id'] : null;

if (!$schoolId) {
    echo "No school ID provided.";
    exit;
}

// 1. Fetch School Details from Supervisor DB
$stmt = $supervisorPdo->prepare("SELECT * FROM institution_profile WHERE id = ?");
$stmt->execute([$schoolId]);
$school = $stmt->fetch();

if (!$school) {
    echo "School not found.";
    exit;
}

// 2. Connect to Tenant DB
try {
    $tenantPdo = TenantManager::getTenantConnection($schoolId);
    // Note: if the school has no pool entry, this returns supervisor DB, which is fine for error handling
} catch (Exception $e) {
    die("Error connecting to school database: " . $e->getMessage());
}

$message = '';
$error = '';

// 3. Handle Deletion/Revocation of User
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['revoke_user_id'])) {
    $uid = (int)$_POST['revoke_user_id'];
    
    try {
        // Start transactions on BOTH DBs
        $supervisorPdo->beginTransaction();
        $tenantPdo->beginTransaction();

        // Delete from Supervisor (prevents login)
        $stmt = $supervisorPdo->prepare("DELETE FROM users WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$uid, $schoolId]);

        // Delete from Tenant (removes local record)
        $stmt = $tenantPdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$uid]);

        // Note: Dependent records (students, employees) might need cleanup if there are no FKs
        // Usually, the app uses soft deletes or cascade, but here we do direct deletion as requested "revoke various users"
        
        $tenantPdo->commit();
        $supervisorPdo->commit();
        $message = "User revoked successfully from both global and local records.";
    } catch (Exception $e) {
        if ($tenantPdo->inTransaction()) $tenantPdo->rollBack();
        if ($supervisorPdo->inTransaction()) $supervisorPdo->rollBack();
        $error = "Failed to revoke user: " . $e->getMessage();
    }
}

// 4. Fetch Roster from Tenant DB
// We categorize them by role
$users = $tenantPdo->query("SELECT * FROM users ORDER BY role, full_name")->fetchAll(PDO::FETCH_ASSOC);

$roster = [
    'student'  => [],
    'employee' => [],
    'parent'   => [],
    'other'    => []
];

foreach ($users as $u) {
    $role = strtolower($u['role'] ?? 'other');
    if (isset($roster[$role])) {
        $roster[$role][] = $u;
    } else {
        $roster['other'][] = $u;
    }
}

// Additional info from mapped tables
$studentsCount = count($roster['student']);
$staffCount    = count($roster['employee']);
$parentsCount  = count($roster['parent']);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Roster - <?= htmlspecialchars($school['institution_name']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary: #4f46e5; --bg: #f8fafc; --text: #1e293b; --border: #e2e8f0; }
        body { font-family: 'Inter', sans-serif; background: var(--bg); color: var(--text); margin: 0; padding: 40px; }
        .container { max-width: 1100px; margin: 0 auto; }
        .header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 30px; }
        .school-info { display: flex; align-items: center; gap: 15px; }
        .school-logo { width: 60px; height: 60px; background: #eef2ff; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 24px; color: var(--primary); font-weight: 800; border: 1px solid var(--border); }
        .back-btn { text-decoration: none; color: #64748b; font-weight: 600; font-size: 14px; display: flex; align-items: center; gap: 8px; }
        
        .stat-bar { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 30px; }
        .stat-item { background: white; padding: 20px; border-radius: 16px; border: 1px solid var(--border); text-align: center; }
        .stat-item .val { font-size: 24px; font-weight: 800; color: var(--primary); }
        .stat-item .lab { font-size: 12px; color: #64748b; text-transform: uppercase; margin-top: 5px; }

        .role-section { background: white; border-radius: 16px; border: 1px solid var(--border); overflow: hidden; margin-bottom: 30px; }
        .role-header { background: #f1f5f9; padding: 15px 25px; font-weight: 700; font-size: 14px; text-transform: uppercase; color: #475569; display: flex; justify-content: space-between; }
        
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 15px 25px; text-align: left; border-bottom: 1px solid var(--border); }
        th { font-size: 12px; color: #64748b; font-weight: 600; }
        td { font-size: 14px; }
        .btn-revoke { background: #fee2e2; color: #b91c1c; border: none; padding: 6px 12px; border-radius: 6px; font-weight: 700; cursor: pointer; font-size: 11px; }
        .btn-revoke:hover { background: #fecaca; }
        
        .alert { padding: 15px 20px; border-radius: 12px; margin-bottom: 20px; font-weight: 600; font-size: 14px; }
        .alert-success { background: #dcfce3; color: #166534; border: 1px solid #bbf7d0; }
        .alert-error { background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; }
    </style>
</head>
<body>

    <div class="container">
        <a href="<?= WEB_ROOT ?>/super-admin/dashboard" class="back-btn"><i class="fa-solid fa-arrow-left"></i> Back to Super Admin Fleet</a>
        
        <div class="header">
            <div class="school-info">
                <div class="school-logo"><?= substr($school['institution_name'], 0, 1) ?></div>
                <div>
                    <h1 style="margin:0; font-size:24px; font-weight:800;"><?= htmlspecialchars($school['institution_name']) ?></h1>
                    <p style="margin:5px 0 0 0; color:#64748b; font-size:14px;"><i class="fa-solid fa-location-dot"></i> <?= htmlspecialchars($school['address'] ?: 'No address set') ?></p>
                </div>
            </div>
            <div>
                <span style="background:#dcfce3; color:#166534; padding:8px 16px; border-radius:30px; font-size:12px; font-weight:800;">ACTIVE SAAS</span>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success"><?= $message ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error"><?= $error ?></div>
        <?php endif; ?>

        <div class="stat-bar">
            <div class="stat-item">
                <div class="val"><?= $studentsCount ?></div>
                <div class="lab">Enrolled Students</div>
            </div>
            <div class="stat-item">
                <div class="val"><?= $staffCount ?></div>
                <div class="lab">Teaching & Staff</div>
            </div>
            <div class="stat-item">
                <div class="val"><?= $parentsCount ?></div>
                <div class="lab">Registered Parents</div>
            </div>
        </div>

        <?php 
        $roleMap = [
            'student'  => '<i class="fa-solid fa-user-graduate"></i> Student Roster',
            'employee' => '<i class="fa-solid fa-chalkboard-user"></i> Staff & Employee Directory',
            'parent'   => '<i class="fa-solid fa-users"></i> Parent Network',
            'other'    => '<i class="fa-solid fa-user-shield"></i> Other Access Types'
        ];

        foreach ($roster as $roleKey => $members): 
            if (empty($members)) continue;
        ?>
        <div class="role-section">
            <div class="role-header">
                <span><?= $roleMap[$roleKey] ?></span>
                <span><?= count($members) ?> Total</span>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Full Name</th>
                        <th>Email / Username</th>
                        <th>Phone</th>
                        <th>Joined</th>
                        <th style="text-align:right;">Access Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($members as $user): ?>
                    <tr>
                        <td>
                            <div style="font-weight:700; color:#0f172a;"><?= htmlspecialchars($user['full_name']) ?></div>
                            <div style="font-size:11px; color:#94a3b8; text-transform:uppercase; letter-spacing:0.5px;"><?= htmlspecialchars($user['role']) ?></div>
                        </td>
                        <td>
                            <div style="font-weight:600;"><?= htmlspecialchars($user['email'] ?: 'No Email') ?></div>
                            <div style="font-size:12px; color:#6366f1;">@<?= htmlspecialchars($user['username'] ?: 'no_username') ?></div>
                        </td>
                        <td style="color:#64748b; font-size:13px;"><?= htmlspecialchars($user['phone'] ?: '—') ?></td>
                        <td style="color:#64748b; font-size:13px;"><?= date('M d, Y', strtotime($user['created_at'])) ?></td>
                        <td style="text-align:right;">
                            <?php if (strtolower($user['role']) === 'school_admin' && count($roster['employee']) == 0): ?>
                                <!-- Protection for the main admin if they are the only employee? Or skip protection for Super Admin -->
                            <?php endif; ?>
                            <form method="POST" onsubmit="return confirm('REVOKE ACCESS?\n\nThis will permanently delete this user from both the school database and the central login system.\n\nContinue?')">
                                <input type="hidden" name="revoke_user_id" value="<?= $user['id'] ?>">
                                <button type="submit" class="btn-revoke"><i class="fa-solid fa-user-minus"></i> Revoke User</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endforeach; ?>

    </div>

</body>
</html>
