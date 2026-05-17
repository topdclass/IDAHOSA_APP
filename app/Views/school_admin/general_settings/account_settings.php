<?php
// DB Connection
try {
    
    $message = '';
    $error = '';

    // Handle POST Actions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $username = $_POST['username'] ?? '';
        $new_pass = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if (!empty($new_pass) && $new_pass !== $confirm) {
            $error = "Passwords do not match!";
        } else {
            // Mock logic: Update current school admin (assuming one for now as per previous session)
            if (!empty($new_pass)) {
                $stmt = $pdo->prepare("UPDATE users SET username=?, password=? WHERE role='school_admin' LIMIT 1");
                $stmt->execute([$username, $new_pass]);
            } else {
                $stmt = $pdo->prepare("UPDATE users SET username=? WHERE role='school_admin' LIMIT 1");
                $stmt->execute([$username]);
            }
            $message = "Account settings updated!";
        }
    }

    // Fetch Current Account
    $stmt = $pdo->query("SELECT * FROM users WHERE role='school_admin' LIMIT 1");
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['username' => 'schooladmin'];

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

$pageTitle = 'Account Settings - General Settings';
require ROOT_PATH . '/app/Views/school_admin/layout/header.php';
require ROOT_PATH . '/app/Views/school_admin/layout/sidebar.php';
?>

<div class="main-container">
    <div class="top-header">
        <div class="greeting">General Settings / <span style="color:var(--primary)">Account Profile</span></div>
        <div class="header-actions">
            <i class="ph ph-bell action-bell"></i>
            <div class="profile-avatar" onclick="toggleProfileDropdown(event)">RI</div>
            <div class="profile-dropdown" id="profileDropdown">
                <a href="<?= WEB_ROOT ?>/school-admin/general-settings/account-settings" class="dropdown-item">
                    <i class="ph ph-user-circle"></i> Account Profile
                </a>
                <a href="<?= WEB_ROOT ?>/logout" class="dropdown-item" style="color:#ef4444;">
                    <i class="ph ph-sign-out" style="color:#ef4444;"></i> Logout
                </a>
            </div>
        </div>
    </div>

    <div class="crud-card" style="max-width: 600px; margin: 20px auto;">
        <div class="crud-header">
            <h2 class="crud-title">Admin Account Credentials</h2>
        </div>

        <?php if ($message): ?>
            <div style="background:#d1fae5; color:#065f46; padding:10px; border-radius:6px; margin-bottom:20px; font-size:13px; font-weight:600;"><?= $message ?></div>
        <?php elseif ($error): ?>
            <div style="background:#fee2e2; color:#991b1b; padding:10px; border-radius:6px; margin-bottom:20px; font-size:13px; font-weight:600;"><?= $error ?></div>
        <?php endif; ?>

        <form method="POST">
            <div style="margin-bottom:20px;">
                <label style="display:block; font-size:13px; font-weight:600; margin-bottom:8px;">Username</label>
                <input type="text" name="username" required value="<?= htmlspecialchars((string)($user_data['username'] ?? '')) ?>" style="width:100%; padding:10px; border:1px solid var(--border); border-radius:6px;">
            </div>
            
            <div style="margin-bottom:20px;">
                <label style="display:block; font-size:13px; font-weight:600; margin-bottom:8px;">New Password (Leave blank to keep current)</label>
                <input type="password" name="new_password" placeholder="••••••••" style="width:100%; padding:10px; border:1px solid var(--border); border-radius:6px;">
            </div>

            <div style="margin-bottom:20px;">
                <label style="display:block; font-size:13px; font-weight:600; margin-bottom:8px;">Confirm New Password</label>
                <input type="password" name="confirm_password" placeholder="••••••••" style="width:100%; padding:10px; border:1px solid var(--border); border-radius:6px;">
            </div>

            <div style="display:flex; justify-content:flex-end; padding-top:10px; border-top:1px solid var(--border);">
                <button type="submit" class="btn-primary" style="padding:12px 32px;">Update Profile</button>
            </div>
        </form>
    </div>
</div>

<?php require ROOT_PATH . '/app/Views/school_admin/layout/footer.php'; ?>
