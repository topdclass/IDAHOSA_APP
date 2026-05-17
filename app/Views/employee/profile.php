<?php
require_once ROOT_PATH . '/config/database.php';

$me = $_SESSION['user_id'] ?? 0;
$message = '';
$error = '';

// 1. Fetch User Data from Supervisor DB
$profile = [];
try {
    $stmt = $supervisorPdo->prepare("SELECT full_name, username, email, phone, photo_url FROM users WHERE id = ?");
    $stmt->execute([$me]);
    $userData = $stmt->fetch(PDO::FETCH_ASSOC);

    // 2. Fetch Employee Data from Tenant DB
    $stmt = $pdo->prepare("SELECT employee_no, qualification_doc FROM institute_employees WHERE employee_id = ?");
    $stmt->execute([$me]);
    $empData = $stmt->fetch(PDO::FETCH_ASSOC);

    // Merge
    $profile = array_merge($userData ?: [], $empData ?: []);
} catch (Exception $e) { 
    $error = "DATABASE ERROR: " . $e->getMessage(); 
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_password') {
        $new_pass = $_POST['new_password'];
        $confirm = $_POST['confirm_password'];
        if ($new_pass === $confirm && !empty($new_pass)) {
            $hashed = password_hash($new_pass, PASSWORD_DEFAULT);
            $stmt = $supervisorPdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            if ($stmt->execute([$hashed, $me])) {
                $message = "Your password has been changed successfully!";
            }
        } else {
            $error = "The passwords you entered do not match.";
        }
    }

    if ($action === 'upload_files') {
        // Handle Photo
        if (!empty($_FILES['photo']['name'])) {
            $ext = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
            $photo_name = '/uploads/profiles/emp_' . $me . '_' . time() . '.' . $ext;
            $target = ROOT_PATH . '/public' . $photo_name;
            if (move_uploaded_file($_FILES['photo']['tmp_name'], $target)) {
                $supervisorPdo->prepare("UPDATE users SET photo_url = ? WHERE id = ?")->execute([$photo_name, $me]);
                $profile['photo_url'] = $photo_name;
                $message = "Profile picture updated successfully!";
            }
        }
        
        // Handle Qualification
        if (!empty($_FILES['qualification']['name'])) {
            $ext = pathinfo($_FILES['qualification']['name'], PATHINFO_EXTENSION);
            $qual_name = '/uploads/qualifications/qual_' . $me . '_' . time() . '.' . $ext;
            $target = ROOT_PATH . '/public' . $qual_name;
            if (move_uploaded_file($_FILES['qualification']['tmp_name'], $target)) {
                $pdo->prepare("UPDATE institute_employees SET qualification_doc = ? WHERE employee_id = ?")->execute([$qual_name, $me]);
                $profile['qualification_doc'] = $qual_name;
                $message = "Qualification document uploaded for promotion review!";
            }
        }
    }
}

$pageTitle = 'Profile & Settings';
require ROOT_PATH . '/app/Views/employee/layout/header.php';
?>

<style>
    .profile-container { max-width: 1100px; margin: 0 auto; padding: 20px; }
    .page-head { margin-bottom: 40px; display: flex; align-items: flex-end; justify-content: space-between; gap: 20px; }
    .page-head-title { font-size: 28px; font-weight: 900; color: #111827; margin: 0; letter-spacing: -0.5px; }
    .page-head-subtitle { color: #64748b; font-size: 15px; margin-top: 5px; }

    .grid-main { display: grid; grid-template-columns: 350px 1fr; gap: 40px; }
    
    .glass-card { background: white; border-radius: 24px; border: 1px solid #f1f5f9; padding: 35px; margin-bottom: 30px; box-shadow: 0 4px 20px -2px rgba(0,0,0,0.03), 0 2px 10px -2px rgba(0,0,0,0.02); transition: 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
    .glass-card:hover { box-shadow: 0 15px 35px -5px rgba(0,0,0,0.06); transform: translateY(-2px); border-color: #cbd5e1; }

    .avatar-stack { position: relative; width: 160px; height: 160px; margin: 0 auto 25px; }
    .avatar-circle { width: 160px; height: 160px; border-radius: 50%; overflow: hidden; border: 4px solid #fff; box-shadow: 0 10px 25px rgba(0,0,0,0.1); background: #f8fafc; display:flex; align-items:center; justify-content:center; }
    .avatar-circle img { width: 100%; height: 100%; object-fit: cover; }
    .avatar-edit-btn { position: absolute; bottom: 8px; right: 8px; background: var(--primary); color: white; width: 38px; height: 38px; border-radius: 50%; display: flex; align-items: center; justify-content: center; border: 3px solid #fff; cursor: pointer; transition: 0.2s; box-shadow: 0 4px 10px rgba(79, 70, 229, 0.3); }
    .avatar-edit-btn:hover { transform: scale(1.1); background: var(--primary-dark); }

    .section-label { display: flex; align-items: center; gap: 10px; margin-bottom: 30px; }
    .section-label h2 { font-size: 18px; font-weight: 800; color: #1e293b; margin: 0; }
    .section-label .dot { width: 8px; height: 8px; border-radius: 50%; background: var(--primary); }

    .form-group { margin-bottom: 25px; }
    .form-group label { display: block; font-size: 11px; font-weight: 800; color: #64748b; margin-bottom: 10px; text-transform: uppercase; letter-spacing: 0.5px; opacity: 0.7; }
    .input-premium { width: 100%; padding: 14px 16px; border-radius: 12px; border: 1.5px solid #eef2f6; background: #f8fafc; font-size: 14px; font-weight: 600; color: #1e293b; outline: none; transition: 0.2s; }
    .input-premium:focus { border-color: var(--primary); background: #fff; box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1); }
    .input-premium:disabled { background: #f1f5f9; color: #94a3af; border-style: dashed; cursor: not-allowed; }

    .btn-action { width: 100%; padding: 16px; border-radius: 16px; border: none; font-weight: 800; font-size: 15px; cursor: pointer; transition: 0.3s; display: flex; align-items: center; justify-content: center; gap: 10px; }
    .btn-primary { background: var(--primary); color: white; box-shadow: 0 8px 15px -3px rgba(79, 70, 229, 0.4); }
    .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 12px 20px -5px rgba(79, 70, 229, 0.5); }
    
    .badge-ro { background: #f1f5f9; color: #64748b; font-size: 10px; font-weight: 800; padding: 5px 12px; border-radius: 20px; border: 1px solid #e2e8f0; }
    
    @media (max-width: 900px) {
        .grid-main { grid-template-columns: 1fr; }
    }
</style>

<div class="profile-container">
    <div class="page-head">
        <div>
            <h1 class="page-head-title">Account Excellence</h1>
            <p class="page-head-subtitle">The centralized hub for your institutional professional profile.</p>
        </div>
        <div style="background: #fff; padding: 6px 15px; border-radius: 12px; border: 1px solid #f1f5f9; font-size: 12px; font-weight: 700; color: #64748b; display: flex; align-items: center; gap: 8px;">
            <i class="ph-fill ph-shield-check" style="color:#10b981; font-size:18px;"></i> Security: High
        </div>
    </div>

    <?php if ($message): ?>
        <div style="background:#f0fdf4; color:#166534; padding:18px 25px; border-radius:16px; margin-bottom:30px; font-weight:700; border-left:5px solid #22c55e; display:flex; align-items:center; gap:12px;">
            <i class="ph ph-check-circle" style="font-size:20px;"></i> <?= $message ?>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div style="background:#fef2f2; color:#991b1b; padding:18px 25px; border-radius:16px; margin-bottom:30px; font-weight:700; border-left:5px solid #ef4444; display:flex; align-items:center; gap:12px;">
            <i class="ph ph-warning-circle" style="font-size:20px;"></i> <?= $error ?>
        </div>
    <?php endif; ?>

    <div class="grid-main">
        <!-- Profile Column -->
        <div class="col-side">
            <div class="glass-card" style="text-align: center;">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="upload_files">
                    <div class="avatar-stack">
                        <div class="avatar-circle">
                            <?php if ($profile['photo_url']): ?>
                                <img src="<?= WEB_ROOT . $profile['photo_url'] ?>" alt="Profile">
                            <?php else: ?>
                                <i class="ph ph-user" style="font-size: 64px; color: #cbd5e1;"></i>
                            <?php endif; ?>
                        </div>
                        <label class="avatar-edit-btn">
                            <i class="ph-fill ph-camera"></i>
                            <input type="file" name="photo" style="display: none;" onchange="this.form.submit()">
                        </label>
                    </div>
                    <h3 style="margin: 0; font-size: 18px; font-weight: 900;"><?= htmlspecialchars($profile['full_name'] ?? 'Faculty Member') ?></h3>
                    <p style="color:#64748b; font-size:13px; margin: 8px 0 0 0;"><?= htmlspecialchars($profile['employee_no'] ?? 'EMP-000') ?></p>
                </form>

                <hr style="margin: 35px 0; border: 0; border-top: 1px solid #f1f5f9;">

                <form method="POST" enctype="multipart/form-data" style="text-align: left;">
                    <input type="hidden" name="action" value="upload_files">
                    <div class="form-group">
                        <label style="display: flex; gap: 8px; align-items: center;">
                            <i class="ph ph-newspaper" style="font-size:16px; color:var(--primary);"></i>
                            Professional Credentials
                        </label>
                        <?php if ($profile['qualification_doc']): ?>
                            <div style="padding: 12px 15px; background: #f0fdf4; border-radius: 12px; margin-bottom: 20px; font-size: 13px; color: #166534; display: flex; align-items: center; gap: 10px; border: 1px solid #bbf7d0;">
                                <i class="ph-fill ph-file-pdf" style="font-size:20px;"></i>
                                Current Document
                                <a href="<?= WEB_ROOT . $profile['qualification_doc'] ?>" target="_blank" style="margin-left: auto; color: var(--primary); font-weight: 800; text-decoration: none;">View</a>
                            </div>
                        <?php else: ?>
                            <div style="text-align:center; padding:20px; border:2px dashed #f1f5f9; border-radius:12px; color:#94a3b8; font-size:12px; margin-bottom:20px;">
                                No certification uploaded yet.
                            </div>
                        <?php endif; ?>
                        <div style="background: #f8fafc; border: 1.5px solid #eef2f6; border-radius: 12px; padding: 15px;">
                            <input type="file" name="qualification" style="font-size: 12px; width: 100%;">
                        </div>
                    </div>
                    <button type="submit" class="btn-action" style="background:#1e293b; color: white;">
                        <i class="ph-fill ph-cloud-arrow-up"></i> Update Qualifications
                    </button>
                    <p style="text-align: center; font-size: 11px; color:#94a3b8; margin-top: 15px;">This file will be used for promotion evaluation.</p>
                </form>
            </div>
        </div>

        <!-- Settings Column -->
        <div class="col-main">
            <!-- Personal Info (Read Only) -->
            <div class="glass-card">
                <div class="section-label">
                    <div class="dot"></div>
                    <h2>Personal Information</h2>
                    <span class="badge-ro" style="margin-left: auto;">LOCKED</span>
                </div>
                
                <div class="form-group">
                    <label>Identity / Employee Number</label>
                    <div style="position: relative;">
                        <input type="text" value="<?= htmlspecialchars($profile['employee_no'] ?? '') ?>" disabled class="input-premium">
                        <i class="ph-fill ph-lock-key" style="position: absolute; right: 15px; top: 15px; color: #cbd5e1;"></i>
                    </div>
                </div>

                <div class="form-group">
                    <label>Official Registered Name</label>
                    <input type="text" value="<?= htmlspecialchars($profile['full_name'] ?? '') ?>" disabled class="input-premium">
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div class="form-group">
                        <label>Correspondence Email</label>
                        <input type="email" value="<?= htmlspecialchars($profile['email'] ?? '') ?>" disabled class="input-premium">
                    </div>
                    <div class="form-group">
                        <label>Verified Telephone</label>
                        <input type="text" value="<?= htmlspecialchars($profile['phone'] ?? '') ?>" disabled class="input-premium">
                    </div>
                </div>
                
                <div style="display: flex; align-items: center; gap: 10px; background: #fffbeb; padding: 12px 15px; border-radius: 10px; border: 1px solid #fdf6b2; color: #92400e; font-size: 12px;">
                    <i class="ph ph-info" style="font-size: 18px;"></i>
                    Identity details are verified. Contact Administration for any required updates.
                </div>
            </div>

            <!-- Security Section -->
            <div class="glass-card">
                <div class="section-label">
                    <div class="dot" style="background:#ef4444;"></div>
                    <h2>Security & Account Control</h2>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="update_password">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        <div class="form-group">
                            <label>Designate New Password</label>
                            <input type="password" name="new_password" required placeholder="••••••••" class="input-premium" style="background: #fff; border-style: solid;">
                        </div>
                        <div class="form-group">
                            <label>Confirm Access Key</label>
                            <input type="password" name="confirm_password" required placeholder="••••••••" class="input-premium" style="background: #fff; border-style: solid;">
                        </div>
                    </div>
                    <button type="submit" class="btn-action btn-primary" style="background:#ef4444; box-shadow: 0 8px 15px -3px rgba(239, 68, 68, 0.4);">
                        <i class="ph ph-password"></i> Finalize Password Update
                    </button>
                    <p style="text-align:center; font-size: 11px; color:#94a3b8; margin-top: 15px;">We recommend a minimum of 8 characters with symbols.</p>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require ROOT_PATH . '/app/Views/employee/layout/header.php'; ?>
