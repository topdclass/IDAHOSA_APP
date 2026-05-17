<?php
require_once ROOT_PATH . '/config/database.php';

// Super Admin always works with the central Supervisor DB
// $supervisorPdo is set by database.php — alias it as $pdo for this view
$pdo = $supervisorPdo;

// Ensure licenses table has the admin credentials columns
try {
    $pdo->exec("ALTER TABLE licenses ADD COLUMN admin_username VARCHAR(100) NULL AFTER user_id, ADD COLUMN admin_password VARCHAR(100) NULL AFTER admin_username");
} catch(PDOException $e) {}

// Ensure db_pool table exists for the pool status panel
require_once ROOT_PATH . '/config/tenant_manager.php';
TenantManager::ensurePoolTableExists($pdo);


// Handle License Status Toggles (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $licenseId = $_POST['license_id'] ?? null;
        $newStatus = $_POST['action'] === 'revoke' ? 'Revoked' : 'Active';

        if ($licenseId) {
            $stmt = $pdo->prepare("UPDATE licenses SET status = :status WHERE id = :id");
            $stmt->execute([':status' => $newStatus, ':id' => $licenseId]);
            $message = "License " . ($newStatus === 'Revoked' ? 'revoked' : 'reactivated') . " successfully.";
        }
    }

    if (isset($_POST['license_delete_id'])) {
        $stmt = $pdo->prepare("DELETE FROM licenses WHERE id = :id");
        $stmt->execute([':id' => $_POST['license_delete_id']]);
        $message = "License record deleted successfully.";
    }
    
    // Handle User Delete
    if (isset($_POST['user_delete_id'])) {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = :id");
        $stmt->execute([':id' => $_POST['user_delete_id']]);
        $message = "User deleted successfully.";
    }
    
    // Handle User Edit
    if (isset($_POST['user_edit_id'])) {
        $stmt = $pdo->prepare("UPDATE users SET full_name = :name, email = :email, role = :role, phone = :phone WHERE id = :id");
        $stmt->execute([
            ':name' => $_POST['full_name'],
            ':email' => $_POST['email'],
            ':role' => $_POST['role'],
            ':phone' => $_POST['phone'],
            ':id' => $_POST['user_edit_id']
        ]);
        $message = "User updated successfully.";
    }

    // Handle School Approval and Provisioning
    if (isset($_POST['approve_school_id'])) {
        $schoolId = (int)$_POST['approve_school_id'];

        // 1. Fetch School Details (always from supervisor DB)
        $stmt = $pdo->prepare("SELECT * FROM institution_profile WHERE id = :id");
        $stmt->execute([':id' => $schoolId]);
        $school = $stmt->fetch();

        if ($school && in_array(strtolower($school['status'] ?? ''), ['pending','inactive','','0',null], true)) {
            // 2. Generate License Key and Expiration
            $licenseKey     = strtoupper(bin2hex(random_bytes(16)));
            $plan           = $school['package'] ?? 'Basic';
            $durationString = stripos($plan, 'Lifetime') !== false ? '+100 years' : '+1 year';
            $expirationDate = date('Y-m-d', strtotime($durationString));

            // 3. Create raw password and insert school_admin user in SUPERVISOR DB
            $adminEmail  = $school['contact_email'];
            $adminName   = $school['admin_name'] ?: 'School Admin';
            $rawPassword = bin2hex(random_bytes(4)); // 8-char plain text (emailed)
            $hashedPass  = password_hash($rawPassword, PASSWORD_DEFAULT);

            // Insert license record
            $stmt = $pdo->prepare("INSERT INTO licenses 
                (user_id, admin_username, admin_password, license_key, status, start_date, end_date) 
                VALUES (?, ?, ?, ?, 'Active', ?, ?)");
            $stmt->execute([$schoolId, $adminEmail, $rawPassword, $licenseKey, date('Y-m-d'), $expirationDate]);

            // Insert school_admin user  
            $stmt = $pdo->prepare("INSERT INTO users 
                (full_name, email, username, password, role, tenant_id) 
                VALUES (?, ?, ?, ?, 'school_admin', ?)");
            $stmt->execute([$adminName, $adminEmail, $adminEmail, $hashedPass, $schoolId]);

            // 4. PROVISION FROM POOL — assign a private database to this school
            require_once ROOT_PATH . '/config/tenant_manager.php';
            $provision = TenantManager::createTenantDatabase($schoolId);

            // 5. Update institution status
            $stmt = $pdo->prepare("UPDATE institution_profile SET status = 'Approved' WHERE id = :id");
            $stmt->execute([':id' => $schoolId]);

            // 6. Send Welcome Email
            $base     = "http://" . $_SERVER['HTTP_HOST'] . rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/\\');
            $subject  = "Welcome to RosmonSMS: School Onboarding Complete";
            $dbNote   = $provision['success']
                ? "Your school's private database has been provisioned: {$provision['db_name']}\n"
                : "Note: Database provisioning pending — please contact support.\n";
            $emailBody = "Dear $adminName,\n\n"
                       . "Welcome to RosmonSMS! Your school has been approved.\n\n"
                       . "License Key: $licenseKey\n"
                       . "Valid Until: $expirationDate\n"
                       . "$dbNote"
                       . "\nLogin Details:\n"
                       . "  Username: $adminEmail\n"
                       . "  Password: $rawPassword\n"
                       . "  Login URL: $base/\n\n"
                       . "Please login to configure your school, add teachers and students.\n\n"
                       . "Warm regards,\nRosmonSMS Administration";

            $headers = "From: no-reply@rosmonsms.com\r\n";
            @mail($adminEmail, $subject, $emailBody, $headers);

            if ($provision['success']) {
                $message = "&#10003; School approved! Database <strong>{$provision['db_name']}</strong> has been provisioned and assigned. Login credentials emailed to $adminEmail.";
            } else {
                $message = "&#9888; School approved &amp; credentials emailed, but <strong>database provisioning failed</strong>: {$provision['message']} — Please run setup_pool.php to add more databases to the pool.";
            }
        } else {
            $message = "Warning: School already approved or not found.";
        }
    }
    
    // Handle School Rejection
    if (isset($_POST['reject_school_id'])) {
        $stmt = $pdo->prepare("UPDATE institution_profile SET status = 'Rejected' WHERE id = :id");
        $stmt->execute([':id' => $_POST['reject_school_id']]);
        $message = "School registration rejected.";
    }

    // Handle Tenant Backup
    if (isset($_POST['backup_school_id'])) {
        require_once ROOT_PATH . '/config/tenant_manager.php';
        $res = TenantManager::backupTenantDatabase($_POST['backup_school_id']);
        if ($res['success']) {
            header('Content-Type: application/sql');
            header('Content-Disposition: attachment; filename="' . $res['filename'] . '"');
            echo $res['sql'];
            exit;
        } else {
            $message = "&#9888; Backup failed: " . $res['message'];
        }
    }

    // Handle Tenant RESET (Empty DB)
    if (isset($_POST['reset_school_id'])) {
        require_once ROOT_PATH . '/config/tenant_manager.php';
        $res = TenantManager::resetTenantDatabase($_POST['reset_school_id']);
        if ($res['success']) {
            $message = "&#10003; Database reset to zero-state successfully.";
        } else {
            $message = "&#9888; Reset failed: " . $res['message'];
        }
    }

    // Handle PERMANENT DELETE
    if (isset($_POST['permanent_delete_school_id'])) {
        require_once ROOT_PATH . '/config/tenant_manager.php';
        $schoolId = $_POST['permanent_delete_school_id'];
        
        // 1. Recycle the database (wipe + unassign)
        $recycle = TenantManager::recycleTenantDatabase($schoolId);
        
        if ($recycle['success']) {
            // 2. Purge Supervisor records
            $purge = TenantManager::purgeSchoolSupervisorData($schoolId);
            
            if ($purge['success']) {
                $message = "&#10003; School permanently deleted and database recycled successfully.";
            } else {
                $message = "&#9888; Database recycled, but Supervisor record purge failed: " . $purge['message'];
            }
        } else {
            $message = "&#9888; Permanent deletion failed at database recycling: " . $recycle['message'];
        }
    }
}

// Fetch Global Activities (Pending Schools + Recent Licenses)
$recentActivities = $pdo->query("
    (SELECT 'New School Enrollment' as action, institution_name as entity, updated_at as created_at, status, id as ref_id 
     FROM institution_profile 
     ORDER BY updated_at DESC LIMIT 5)
    UNION ALL
    (SELECT 'License Generated' as action, p.institution_name as entity, l.created_at, 'Success' as status, l.id as ref_id 
     FROM licenses l 
     JOIN institution_profile p ON l.user_id = p.id 
     ORDER BY l.created_at DESC LIMIT 5)
    ORDER BY created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Fetch Real-Time Stats
$totalSchools = $pdo->query("SELECT COUNT(*) FROM institution_profile")->fetchColumn() ?: 0;
$activeSaaS = $pdo->query("SELECT COUNT(*) FROM licenses WHERE status = 'Active'")->fetchColumn() ?: 0;
$totalCoreUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn() ?: 0;

// Fetch All Licenses with School info
$licensesList = $pdo->query("
    SELECT l.*, p.institution_name, u.full_name as admin_name 
    FROM licenses l 
    LEFT JOIN institution_profile p ON l.user_id = p.id
    LEFT JOIN users u ON l.user_id = u.tenant_id AND u.role = 'school_admin'
    WHERE l.is_deleted = 0 
    GROUP BY l.id
    ORDER BY l.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Fetch All Users for Management Logic
// We map users back to their schools via institute_employees/students/parents or direct institution_profile links
$usersList = $pdo->query("
    SELECT u.*, p.institution_name 
    FROM users u 
    LEFT JOIN (
        SELECT employee_id as uid, institute_id FROM institute_employees
        UNION
        SELECT student_id as uid, institute_id FROM institute_students
        UNION
        SELECT parent_id as uid, institute_id FROM institute_parents
        UNION
        SELECT id as uid, id as institute_id FROM institution_profile
    ) m ON u.id = m.uid
    LEFT JOIN institution_profile p ON m.institute_id = p.id
    GROUP BY u.id
    ORDER BY CASE WHEN u.role = 'super_admin' THEN 1 ELSE 2 END, u.full_name
")->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin Dashboard - Rosmon SMS</title>
    <!-- Modern Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --primary: #13198f;
            --primary-hover: #0e1268;
            --secondary: #b4bce1;
            --bg-color: #f8fafc;
            --white: #ffffff;
            --text-dark: #1f2937;
            --text-muted: #6b7280;
            --border: #e5e7eb;
            --sidebar-width: 280px;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Inter', sans-serif;
        }

        body {
            background-color: var(--bg-color);
            color: var(--text-dark);
            display: flex;
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* --- Sidebar Navigation --- */
        .sidebar {
            width: var(--sidebar-width);
            background-color: var(--primary);
            color: var(--white);
            display: flex;
            flex-direction: column;
            position: fixed;
            height: 100vh;
            left: 0;
            top: 0;
            z-index: 10;
        }

        .sidebar-header {
            padding: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .sidebar-header .logo {
            width: 40px;
            height: 40px;
            background-color: var(--white);
            color: var(--primary);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            font-weight: 800;
        }

        .sidebar-header h2 {
            font-size: 16px;
            font-weight: 700;
            letter-spacing: 0.5px;
        }

        .nav-links {
            padding: 24px 16px;
            flex: 1;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 16px;
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            border-radius: 8px;
            margin-bottom: 8px;
            transition: all 0.2s;
            font-weight: 500;
            cursor: pointer;
        }

        .nav-link:hover, .nav-link.active {
            background-color: rgba(255,255,255,0.1);
            color: var(--white);
        }

        .sidebar-footer {
            padding: 24px 16px;
            border-top: 1px solid rgba(255,255,255,0.1);
        }

        .logout-btn {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 12px 16px;
            color: #fb7185;
            text-decoration: none;
            font-weight: 600;
            border-radius: 8px;
            transition: 0.2s;
        }

        .logout-btn:hover {
            background-color: rgba(251, 113, 133, 0.1);
        }

        /* --- Main Content Area --- */
        .main-content {
            flex: 1;
            margin-left: var(--sidebar-width);
            display: flex;
            flex-direction: column;
            width: calc(100% - var(--sidebar-width));
        }

        /* Header */
        .top-header {
            height: 72px;
            background-color: var(--white);
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 32px;
            position: sticky;
            top: 0;
            z-index: 5;
            box-shadow: 0 1px 2px rgba(0,0,0,0.02);
        }

        .page-title {
            font-size: 20px;
            font-weight: 700;
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 600;
            color: var(--text-dark);
            cursor: pointer;
        }

        .user-avatar {
            width: 36px;
            height: 36px;
            background-color: #e0e7ff;
            color: #1d4ed8;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
        }

        /* Dashboard Views Controller */
        .view-section {
            display: none;
            padding: 32px;
            animation: fadeIn 0.3s ease-in-out;
        }

        .view-section.active {
            display: block;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* --- UI Components --- */
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 24px;
            margin-bottom: 32px;
        }

        .stat-card {
            background-color: var(--white);
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
            display: flex;
            align-items: center;
            justify-content: space-between;
            border: 1px solid var(--border);
        }

        .stat-details h3 { font-size: 14px; color: var(--text-muted); font-weight: 500; margin-bottom: 8px; }
        .stat-details .value { font-size: 32px; font-weight: 800; color: #111827; }
        .stat-icon {
            width: 56px; height: 56px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center; font-size: 24px;
        }
        .bg-blue-light { background-color: #e0e7ff; color: #4338ca; }
        .bg-green-light { background-color: #dcfce3; color: #166534; }
        .bg-purple-light { background-color: #f3e8ff; color: #7e22ce; }

        /* Tables and Panels */
        .panel {
            background-color: var(--white);
            border-radius: 16px;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
            border: 1px solid var(--border);
            overflow: hidden;
            margin-bottom: 32px;
        }

        .panel-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .panel-header h3 { font-size: 16px; font-weight: 700; }

        .btn-primary {
            background-color: var(--primary); color: var(--white);
            border: none; padding: 10px 20px; border-radius: 8px;
            font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 8px;
            transition: 0.2s; font-size: 14px;
        }
        .btn-primary:hover { background-color: var(--primary-hover); transform: translateY(-1px); }

        .btn-danger {
            background-color: #fee2e2; color: #b91c1c;
            border: none; padding: 8px 12px; border-radius: 6px;
            font-weight: 600; cursor: pointer; font-size: 12px;
        }

        .btn-edit {
            background-color: #f3f4f6; color: #374151;
            border: none; padding: 8px 12px; border-radius: 6px;
            font-weight: 600; cursor: pointer; font-size: 12px; margin-right: 8px;
        }

        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 16px 24px; text-align: left; border-bottom: 1px solid var(--border); }
        th { background-color: #f9fafb; font-size: 12px; text-transform: uppercase; color: var(--text-muted); font-weight: 600; }
        td { font-size: 14px; color: var(--text-dark); font-weight: 500; }
        tr:hover { background-color: #f8fafc; }

        .badge-active { background-color: #dcfce3; color: #166534; padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 700; }
        .badge-pending { background-color: #fef9c3; color: #854d0e; padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 700; }
        .badge-admin { background-color: #e0e7ff; color: #4338ca; padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 700; }

        /* Forms inside Panels */
        .form-row {
            display: flex; gap: 20px; padding: 24px;
        }
        .form-group {
            flex: 1; display: flex; flex-direction: column; gap: 8px;
        }
        .form-group label {
            font-size: 13px; font-weight: 600; color: var(--text-muted);
        }
        .form-group input, .form-group select {
            padding: 12px; border: 1px solid var(--border); border-radius: 8px;
            outline: none; font-size: 14px;
        }
        .form-group input:focus, .form-group select:focus {
            border-color: var(--primary);
        }

        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); transition: 0.3s; }
            .sidebar.open { transform: translateX(0); }
            .main-content { margin-left: 0; width: 100%; }
            .top-header { padding: 0 16px; }
            .form-row { flex-direction: column; }
        }
    </style>
</head>
<body>

    <!-- Sidebar Menu -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="logo"><i class="fa-solid fa-book-open"></i></div>
            <h2>ROSMON SMS</h2>
        </div>
        
        <div class="nav-links">
            <div class="nav-link active" onclick="switchTab('dashboard', this)">
                <i class="fa-solid fa-gauge"></i> Overview
            </div>
            <div class="nav-link" onclick="switchTab('licenses', this)">
                <i class="fa-solid fa-file-shield"></i> License Manager
            </div>
            <div class="nav-link" onclick="switchTab('users', this)">
                <i class="fa-solid fa-users-gear"></i> User Management
            </div>
            <div class="nav-link" onclick="switchTab('dbpool', this)">
                <i class="fa-solid fa-database"></i> DB Pool
            </div>
            <div class="nav-link" onclick="switchTab('notifications', this)">
                <i class="fa-solid fa-bell"></i> Push Broadcast
            </div>
            <div class="nav-link" onclick="switchTab('settings', this)">
                <i class="fa-solid fa-sliders"></i> Global Settings
            </div>
        </div>

        <div class="sidebar-footer">
            <a href="<?= WEB_ROOT ?>/logout" class="logout-btn">
                <i class="fa-solid fa-arrow-right-from-bracket"></i> Sign Out
            </a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Header Navigation -->
        <div class="top-header">
            <div class="page-title" id="pageTitle">Super Admin Overview</div>
            <div class="user-profile">
                <span>Super Admin</span>
                <div class="user-avatar">SA</div>
            </div>
        </div>

        <?php if (isset($message)): ?>
            <div style="margin: 20px 32px 0 32px; padding: 16px; background: #dcfce3; color: #166534; border: 1px solid #b9f6ca; border-radius: 12px; font-weight: 600;">
                <?= $message ?>
            </div>
        <?php endif; ?>

        <!-- TAB 1: Dashboard Overview -->
        <div class="view-section active" id="view-dashboard">
            <div class="stats-grid">
                <div class="stat-card" onclick="switchTab('licenses', document.querySelector('.nav-link[onclick*=\'licenses\']'))" style="cursor:pointer; transition: transform 0.2s;" onmouseover="this.style.transform='translateY(-5px)'" onmouseout="this.style.transform='translateY(0)'">
                    <div class="stat-details">
                        <h3>Registered Schools</h3>
                        <div class="value"><?= number_format($totalSchools) ?></div>
                    </div>
                    <div class="stat-icon bg-blue-light"><i class="fa-solid fa-school"></i></div>
                </div>
                <div class="stat-card" onclick="switchTab('licenses', document.querySelector('.nav-link[onclick*=\'licenses\']'))" style="cursor:pointer; transition: transform 0.2s;" onmouseover="this.style.transform='translateY(-5px)'" onmouseout="this.style.transform='translateY(0)'">
                    <div class="stat-details">
                        <h3>Active SaaS Licenses</h3>
                        <div class="value"><?= number_format($activeSaaS) ?></div>
                    </div>
                    <div class="stat-icon bg-green-light"><i class="fa-solid fa-file-shield"></i></div>
                </div>
                <div class="stat-card" onclick="switchTab('users', document.querySelector('.nav-link[onclick*=\'users\']'))" style="cursor:pointer; transition: transform 0.2s;" onmouseover="this.style.transform='translateY(-5px)'" onmouseout="this.style.transform='translateY(0)'">
                    <div class="stat-details">
                        <h3>Total Core Users</h3>
                        <div class="value"><?= number_format($totalCoreUsers) ?></div>
                    </div>
                    <div class="stat-icon bg-purple-light"><i class="fa-solid fa-users"></i></div>
                </div>
            </div>

            <div class="panel">
                <div class="panel-header">
                    <h3>Recent Global Activities</h3>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>Action</th>
                            <th>Entity / School</th>
                            <th>Time</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentActivities)): ?>
                            <tr><td colspan="4" style="text-align:center; padding:24px; color:var(--text-muted);">No recent activities found.</td></tr>
                        <?php else: ?>
                            <?php foreach($recentActivities as $act): ?>
                                <tr>
                                    <td><?= htmlspecialchars($act['action']) ?></td>
                                    <td><?= htmlspecialchars($act['entity']) ?></td>
                                    <td><?= date('M d, H:i', strtotime($act['created_at'])) ?></td>
                                    <td>
                                        <?php if ($act['status'] === 'Pending'): ?>
                                            <div style="display:flex; align-items:center; gap:8px;">
                                                <span class="badge-pending">Pending Approval</span>
                                                <form method="POST" style="display:inline;">
                                                    <input type="hidden" name="approve_school_id" value="<?= $act['ref_id'] ?>">
                                                    <button type="submit" class="btn-primary" style="padding:4px 8px; font-size:10px; background:#10b981;">Approve</button>
                                                </form>
                                                <form method="POST" style="display:inline;">
                                                    <input type="hidden" name="reject_school_id" value="<?= $act['ref_id'] ?>">
                                                    <button type="submit" class="btn-danger" style="padding:4px 8px; font-size:10px;">Disapprove</button>
                                                </form>
                                            </div>
                                        <?php else: ?>
                                            <span class="badge-active"><?= htmlspecialchars($act['status']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- TAB 2: License Manager -->
        <div class="view-section" id="view-licenses">
            
            <div class="panel">
                <div class="panel-header">
                    <h3>Generate New SaaS License</h3>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>School Name</label>
                        <input type="text" placeholder="Enter full school name...">
                    </div>
                    <div class="form-group">
                        <label>License Plan Type</label>
                        <select>
                            <option>Basic Plan (1 Year)</option>
                            <option>Premium Plan (1 Year)</option>
                            <option>Enterprise (Lifetime)</option>
                        </select>
                    </div>
                    <div class="form-group" style="justify-content: flex-end;">
                        <button class="btn-primary" onclick="alert('License generated successfully!')">
                            <i class="fa-solid fa-key"></i> Generate Key
                        </button>
                    </div>
                </div>
            </div>

            <div class="panel">
                <div class="panel-header">
                    <h3>Active Provisioned Licenses</h3>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>School Name</th>
                            <th>License Key</th>
                            <th>Admin Credentials</th>
                            <th>Plan Type</th>
                            <th>Expiration</th>
                            <th>Maintenance</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($licensesList)): ?>
                            <tr><td colspan="5" style="text-align:center; padding:24px; color:var(--text-muted);">No licenses provisioned.</td></tr>
                        <?php else: ?>
                            <?php foreach($licensesList as $lic): ?>
                                <tr>
                                    <td><?= htmlspecialchars($lic['institution_name'] ?: ($lic['admin_name'] ?: 'School Admin '.$lic['user_id'])) ?></td>
                                    <td><code style="background-color: #f3f4f6; padding: 4px; border-radius: 4px; color: #1d4ed8; font-weight:700;"><?= htmlspecialchars($lic['license_key']) ?></code></td>
                                    <td>
                                        <div style="font-size:12px; background:#f8fafc; padding:8px; border:1px solid #e2e8f0; border-radius:6px; display:inline-block;">
                                            <div style="margin-bottom:4px; color:#1e293b;"><i class="fa-solid fa-user" style="color:#94a3b8; width:16px;"></i> <b><?= htmlspecialchars($lic['admin_username'] ?? 'N/A') ?></b></div>
                                            <div style="color:#1e293b;"><i class="fa-solid fa-lock" style="color:#94a3b8; width:16px;"></i> <span style="font-family:monospace;"><?= htmlspecialchars($lic['admin_password'] ?? 'N/A') ?></span></div>
                                        </div>
                                    </td>
                                    <td><span class="<?= $lic['status'] === 'Active' ? 'badge-active' : 'badge-danger' ?>" style="<?= $lic['status'] === 'Revoked' ? 'background:#fee2e2; color:#b91c1c; padding:4px 10px; border-radius:20px; font-size:12px; font-weight:700;' : '' ?>"><?= htmlspecialchars($lic['status']) ?></span></td>
                                    <td><?= date('M d, Y', strtotime($lic['end_date'])) ?></td>
                                    <td>
                                        <div style="display:flex; gap:8px;">
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="backup_school_id" value="<?= $lic['user_id'] ?>">
                                                <button type="submit" class="btn-edit" title="Backup Database" style="background:#dcfce3; color:#166534;"><i class="fa-solid fa-download"></i> Backup</button>
                                            </form>
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('WARNING: This will PERMANENTLY WIPE all data in this school database (except logins). Continue?')">
                                                <input type="hidden" name="reset_school_id" value="<?= $lic['user_id'] ?>">
                                                <button type="submit" class="btn-danger" title="Deep Clean DB / Reset" style="padding:8px 12px; font-size:11px;"><i class="fa-solid fa-broom"></i> Reset</button>
                                            </form>
                                        </div>
                                    </td>
                                    <td>
                                        <div style="display:flex; gap:8px;">
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to <?= $lic['status'] === 'Active' ? 'revoke' : 'reopen' ?> this school?')">
                                                <input type="hidden" name="license_id" value="<?= $lic['id'] ?>">
                                                <?php if ($lic['status'] === 'Active'): ?>
                                                    <button type="submit" class="btn-danger" style="background:#fff7ed; color:#c2410c; border:1px solid #fdba74;" title="Revoke License"><i class="fa-solid fa-ban"></i></button>
                                                <?php else: ?>
                                                    <button type="submit" class="btn-edit" style="background:#dcfce7; color:#15803d;" title="Reopen License"><i class="fa-solid fa-rotate-left"></i></button>
                                                <?php endif; ?>
                                            </form>
                                            <a href="<?= WEB_ROOT ?>/super-admin/schools/view?id=<?= $lic['user_id'] ?>" class="btn-primary" style="padding:8px 10px; background:#4f46e5; display:flex; align-items:center; justify-content:center; text-decoration:none;" title="Manage Local Users">
                                                <i class="fa-solid fa-users-gear"></i>
                                            </a>
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this specific license record?')">
                                                <input type="hidden" name="license_delete_id" value="<?= $lic['id'] ?>">
                                                <button type="submit" class="btn-danger" style="background:#fff7ed; color:#b91c1c; border:1px solid #fee2e2;" title="Delete License Record Only"><i class="fa-solid fa-file-circle-minus"></i></button>
                                            </form>
                                            
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('CRITICAL WARNING:\n\nThis will PERMANENTLY DELETE the school registration, all license records, and ALL DATA in their private database.\n\nThe database will be recycled for a new school.\n\nTHIS ACTION CANNOT BE UNDONE. Proceed?')">
                                                <input type="hidden" name="permanent_delete_school_id" value="<?= $lic['user_id'] ?>">
                                                <button type="submit" class="btn-danger" style="background:#fee2e2; color:#b91c1c;" title="Permanent Delete / Recycle"><i class="fa-solid fa-trash-can"></i></button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- TAB 3: User Management -->
        <div class="view-section" id="view-users">
            
            <div class="panel">
                <div class="panel-header">
                    <h3>System Admins & Privileged Users</h3>
                    <button class="btn-primary"><i class="fa-solid fa-user-plus"></i> Add User</button>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>Full Name</th>
                            <th>Email Address</th>
                            <th>School</th>
                            <th>Global Role</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($usersList as $user): ?>
                        <tr>
                            <td><?= htmlspecialchars($user['full_name']) ?></td>
                            <td><?= htmlspecialchars($user['email']) ?></td>
                            <td><span style="font-weight:600; color:var(--text-muted); font-size:12px;"><?= htmlspecialchars($user['institution_name'] ?: 'Global / System') ?></span></td>
                            <td><span class="<?= in_array(strtolower($user['role']), ['super_admin', 'admin']) ? 'badge-admin' : 'badge-pending' ?>"><?= htmlspecialchars($user['role']) ?></span></td>
                            <td><span class="badge-active">Active</span></td>
                            <td>
                                <?php if (strtolower($user['role']) === 'super_admin' || true): // Allowing all for super admin to manage ?>
                                    <button class="btn-edit" onclick="openEditUserModal(<?= htmlspecialchars(json_encode($user)) ?>)" title="Edit User"><i class="fa-solid fa-pen"></i></button>
                                <?php endif; ?>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Permanently delete this user?')">
                                    <input type="hidden" name="user_delete_id" value="<?= $user['id'] ?>">
                                    <button type="submit" class="btn-danger" title="Delete Account"><i class="fa-solid fa-trash-can"></i></button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- TAB 4: Push Notifications -->
        <div class="view-section" id="view-notifications">
            <div class="panel">
                <div class="panel-header">
                    <h3>Broadcast Push Notification</h3>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Target Audience</label>
                        <select>
                            <option>All Registered Users (Global)</option>
                            <option>All School Administrators</option>
                            <option>All Finance Officers</option>
                            <option>All Parents App Accounts</option>
                            <option>Specific School Users...</option>
                        </select>
                    </div>
                </div>
                <div class="form-row" style="padding-top: 0;">
                    <div class="form-group">
                        <label>Notification Title</label>
                        <input type="text" placeholder="e.g., System Maintenance Alert">
                    </div>
                </div>
                <div class="form-row" style="padding-top: 0;">
                    <div class="form-group">
                        <label>Message Content</label>
                        <textarea rows="4" style="padding: 12px; border: 1px solid var(--border); border-radius: 8px; outline: none; font-size: 14px; resize: vertical;" placeholder="Type your broadcast message..."></textarea>
                    </div>
                </div>
                <div class="form-row" style="padding-top: 0; justify-content: flex-end;">
                    <button class="btn-primary" onclick="alert('Push notification broadcast successfully queued for delivery!')">
                        <i class="fa-solid fa-paper-plane"></i> Send Push Broadcast
                    </button>
                </div>
            </div>
        </div>


        <!-- TAB 4b: DB Pool Manager -->
        <div class="view-section" id="view-dbpool">
            <?php
            $poolStats = TenantManager::getPoolStats();
            $supCreds  = TenantManager::getSupervisorCredentials();
            $poolSize  = TenantManager::getPoolSize();
            ?>
            <div class="panel" style="margin-bottom:20px;">
                <div class="panel-header">
                    <h3><i class="fa-solid fa-database"></i> Database Pool &mdash; <?= $poolSize ?>-Slot Tenant Pool</h3>
                </div>
                <div style="padding:24px;">
                    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:24px;">
                        <div style="background:#eff6ff;border-radius:10px;padding:16px;text-align:center;">
                            <div style="font-size:26px;font-weight:800;color:#2563eb;"><?= $poolStats['total'] ?>/<?= $poolSize ?></div>
                            <div style="font-size:11px;color:#64748b;font-weight:600;margin-top:4px;">REGISTERED</div>
                        </div>
                        <div style="background:#f0fdf4;border-radius:10px;padding:16px;text-align:center;">
                            <div style="font-size:26px;font-weight:800;color:#16a34a;"><?= $poolStats['available'] ?></div>
                            <div style="font-size:11px;color:#64748b;font-weight:600;margin-top:4px;">AVAILABLE</div>
                        </div>
                        <div style="background:#fef2f2;border-radius:10px;padding:16px;text-align:center;">
                            <div style="font-size:26px;font-weight:800;color:#dc2626;"><?= $poolStats['assigned'] ?></div>
                            <div style="font-size:11px;color:#64748b;font-weight:600;margin-top:4px;">ASSIGNED</div>
                        </div>
                        <div style="background:#fefce8;border-radius:10px;padding:16px;text-align:center;">
                            <div style="font-size:26px;font-weight:800;color:#ca8a04;"><?= $poolSize - $poolStats['total'] ?></div>
                            <div style="font-size:11px;color:#64748b;font-weight:600;margin-top:4px;">NOT IN POOL</div>
                        </div>
                    </div>
                    <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:16px;margin-bottom:20px;font-size:13px;line-height:1.8;">
                        <strong>Supervisor DB:</strong> <code><?= htmlspecialchars($supCreds['dbname']) ?></code> &nbsp;|&nbsp;
                        <strong>Host:</strong> <code><?= htmlspecialchars($supCreds['host']) ?></code> &nbsp;|&nbsp;
                        <strong>User:</strong> <code><?= htmlspecialchars($supCreds['user']) ?></code><br>
                        <strong>Tenant pattern:</strong>
                        <code><?= htmlspecialchars($supCreds['dbname']) ?>_1</code> &rarr;
                        <code><?= htmlspecialchars($supCreds['dbname']) ?>_<?= $poolSize ?></code>
                        (username = password = db_name)
                    </div>
                    <?php if (!empty($poolStats['entries'])): ?>
                    <div style="max-height:440px;overflow-y:auto;border:1px solid #e2e8f0;border-radius:8px;">
                    <table style="width:100%;border-collapse:collapse;font-size:13px;">
                        <thead><tr style="background:#f8fafc;position:sticky;top:0;">
                            <th style="padding:10px 12px;border-bottom:2px solid #e2e8f0;">#</th>
                            <th style="padding:10px 12px;border-bottom:2px solid #e2e8f0;">Database</th>
                            <th style="padding:10px 12px;border-bottom:2px solid #e2e8f0;">School</th>
                            <th style="padding:10px 12px;border-bottom:2px solid #e2e8f0;">Status</th>
                            <th style="padding:10px 12px;border-bottom:2px solid #e2e8f0;">Assigned On</th>
                            <th style="padding:10px 12px;border-bottom:2px solid #e2e8f0;text-align:center;">Actions</th>
                        </tr></thead>
                        <tbody>
                        <?php foreach ($poolStats['entries'] as $pe): ?>
                        <tr style="border-bottom:1px solid #f1f5f9;">
                            <td style="padding:8px 12px;color:#94a3b8;"><?= (int)$pe['pool_index'] ?></td>
                            <td style="padding:8px 12px;"><code style="font-size:11px;"><?= htmlspecialchars($pe['db_name']) ?></code></td>
                            <td style="padding:8px 12px;font-weight:600;"><?= $pe['is_assigned'] ? htmlspecialchars($pe['institution_name'] ?? 'School #'.$pe['school_id']) : '<span style="color:#94a3b8;">Free</span>' ?></td>
                            <td style="padding:8px 12px;">
                                <?php if ($pe['is_assigned']): ?>
                                <span style="background:#dcfce7;color:#16a34a;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;">ASSIGNED</span>
                                <?php else: ?>
                                <span style="background:#f0fdf4;color:#15803d;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;">FREE</span>
                                <?php endif; ?>
                            </td>
                            <td style="padding:8px 12px;color:#64748b;font-size:12px;"><?= $pe['assigned_at'] ? date('M d, Y', strtotime($pe['assigned_at'])) : '&mdash;' ?></td>
                            <td style="padding:8px 12px;text-align:center;">
                                <?php if ($pe['is_assigned'] && $pe['school_id']): ?>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('WIPE this school DB and recycle the slot? CANNOT BE UNDONE.')">
                                    <input type="hidden" name="reset_school_id" value="<?= $pe['school_id'] ?>">
                                    <button type="submit" style="padding:4px 10px;background:#fef2f2;color:#dc2626;border:1px solid #fecaca;border-radius:6px;cursor:pointer;font-size:11px;">&#x21bb; Recycle</button>
                                </form>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="backup_school_id" value="<?= $pe['school_id'] ?>">
                                    <button type="submit" style="padding:4px 10px;background:#f0fdf4;color:#16a34a;border:1px solid #bbf7d0;border-radius:6px;cursor:pointer;font-size:11px;">&#11015; Backup</button>
                                </form>
                                <?php else: ?>
                                <span style="color:#cbd5e1;font-size:11px;">Available</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                    <?php else: ?>
                    <div style="text-align:center;padding:40px;color:#94a3b8;">
                        <i class="fa-solid fa-database" style="font-size:32px;margin-bottom:12px;display:block;"></i>
                        Pool not populated yet.
                        <br><br>Run <code>setup_pool.php</code> or visit the installer to register all 50 slots.
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- TAB 5: Global Settings -->
        <div class="view-section" id="view-settings">
            <div class="panel">
                <div class="panel-header">
                    <h3>System Configuration Options</h3>
                </div>
                <div style="padding: 24px;">
                    <p style="color: var(--text-muted); line-height: 1.6;">
                        <i class="fa-solid fa-triangle-exclamation" style="color: #f59e0b; margin-right: 8px;"></i>
                        Careful! Modifying server variables affects all tenant schools simultaneously. Only change application endpoints naturally during maintenance windows.
                    </p>
                    <br>
                    <button class="btn-primary"><i class="fa-solid fa-database"></i> Trigger Database Backup</button>
                </div>
            </div>
        </div>

    </div>

    <!-- Edit User Modal -->
    <div id="editUserModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000; align-items:center; justify-content:center;">
        <div class="panel" style="width:100%; max-width:500px; padding:32px; box-shadow:0 20px 50px rgba(0,0,0,0.2) !important; background:white;">
            <div class="panel-header" style="margin-bottom:24px; display:flex; justify-content:space-between; align-items:center;">
                <h3 style="margin:0;">Edit User Details</h3>
                <i class="fa-solid fa-xmark" style="cursor:pointer; font-size:20px; color:var(--text-muted);" onclick="document.getElementById('editUserModal').style.display='none'"></i>
            </div>
            <form method="POST">
                <input type="hidden" name="user_edit_id" id="edit_user_id">
                <div class="form-group" style="margin-bottom:16px;">
                    <label style="display:block; margin-bottom:8px; font-weight:600; font-size:13px;">Full Name</label>
                    <input type="text" name="full_name" id="edit_full_name" required style="width:100%; padding:10px; border:1px solid var(--border); border-radius:8px;">
                </div>
                <div class="form-group" style="margin-bottom:16px;">
                    <label style="display:block; margin-bottom:8px; font-weight:600; font-size:13px;">Email Address</label>
                    <input type="email" name="email" id="edit_email" required style="width:100%; padding:10px; border:1px solid var(--border); border-radius:8px;">
                </div>
                <div class="form-group" style="margin-bottom:16px;">
                    <label style="display:block; margin-bottom:8px; font-weight:600; font-size:13px;">Phone Number</label>
                    <input type="text" name="phone" id="edit_phone" required style="width:100%; padding:10px; border:1px solid var(--border); border-radius:8px;">
                </div>
                <div class="form-group" style="margin-bottom:24px;">
                    <label style="display:block; margin-bottom:8px; font-weight:600; font-size:13px;">Role</label>
                    <select name="role" id="edit_role" style="width:100%; padding:10px; border:1px solid var(--border); border-radius:8px;">
                        <option value="super_admin">Super Admin</option>
                        <option value="school_admin">School Admin</option>
                        <option value="teacher">Teacher</option>
                        <option value="student">Student</option>
                        <option value="finance">Finance</option>
                    </select>
                </div>
                <div style="display:flex; justify-content:flex-end; gap:12px;">
                    <button type="button" class="btn-secondary" style="padding:10px 20px; border:1px solid var(--border); background:white; cursor:pointer; border-radius:8px;" onclick="document.getElementById('editUserModal').style.display='none'">Cancel</button>
                    <button type="submit" class="btn-primary" style="padding:10px 20px; background:var(--primary); color:white; border:none; border-radius:8px; cursor:pointer;">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- JavaScript to toggle views organically -->
    <script>
        function switchTab(tabId, element) {
            // Remove active style from Nav 
            document.querySelectorAll('.nav-link').forEach(el => el.classList.remove('active'));
            element.classList.add('active');

            // Hide all views
            document.querySelectorAll('.view-section').forEach(view => {
                view.classList.remove('active');
            });

            // Show selected view
            document.getElementById('view-' + tabId).classList.add('active');

            // Update top bar text
            const titleElement = document.getElementById('pageTitle');
            if(tabId === 'dashboard') titleElement.innerText = 'Super Admin Overview';
            if(tabId === 'licenses') titleElement.innerText = 'SaaS License Manager';
            if(tabId === 'users') titleElement.innerText = 'Global User Management';
            if(tabId === 'notifications') titleElement.innerText = 'Push Notification Broadcasts';
            if(tabId === 'settings') titleElement.innerText = 'System Configuration';
            if(tabId === 'dbpool') titleElement.innerText = 'Database Pool Manager';
        }

        function openEditUserModal(user) {
            document.getElementById('edit_user_id').value = user.id;
            document.getElementById('edit_full_name').value = user.full_name;
            document.getElementById('edit_email').value = user.email;
            document.getElementById('edit_phone').value = user.phone || '';
            document.getElementById('edit_role').value = user.role;
            document.getElementById('editUserModal').style.display = 'flex';
        }
    </script>
</body>
</html>
