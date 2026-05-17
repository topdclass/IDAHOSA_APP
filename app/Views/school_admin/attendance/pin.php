<?php
// Attendance PIN Management Logic

try {
    
    // Auto Migration: Attendance PINs
    $pdo->exec("CREATE TABLE IF NOT EXISTS attendance_pins (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        pin VARCHAR(10) NOT NULL,
        UNIQUE(user_id)
    )");

    $message = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['action'])) {
            if ($_POST['action'] === 'set_pin') {
                $uid = $_POST['user_id'] ?? 0;
                $pin = $_POST['pin'] ?? '';

                if(strlen($pin) < 4) {
                    $error = "PIN must be at least 4 digits!";
                } else {
                    $stmt = $pdo->prepare("INSERT INTO attendance_pins (user_id, pin) VALUES (?, ?) ON DUPLICATE KEY UPDATE pin = VALUES(pin)");
                    $stmt->execute([$uid, $pin]);
                    $message = "Staff PIN successfully updated!";
                }
            }
        }
    }

    // Fetch All Staff
    $stmt = $pdo->query("SELECT id, full_name, role FROM users WHERE role IN ('teacher', 'support_staff', 'finance_officer') ORDER BY full_name ASC");
    $staff = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch Existing PINs (showing as masked or binary)
    $stmt = $pdo->query("SELECT user_id, pin FROM attendance_pins");
    $pins = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

$pageTitle = 'Manage Attendance PINs';
require ROOT_PATH . '/app/Views/school_admin/layout/header.php';
require ROOT_PATH . '/app/Views/school_admin/layout/sidebar.php';
?>

<div class="main-container">
    <div class="top-header">
        <div class="greeting">Attendance / <span style="color:var(--primary)">Staff Digital Keys (PIN)</span></div>
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

    <div class="crud-card">
        <div class="crud-header">
            <h2 class="crud-title">Shared Device Clock-in PINs</h2>
            <?php if ($message): ?>
                <span style="color:#10b981; font-weight:700; font-size:12px;"><?= $message ?></span>
            <?php endif; ?>
        </div>

        <table class="crud-table">
            <thead>
                <tr>
                    <th>STAFF NAME</th>
                    <th>ROLE</th>
                    <th>CONFIGURE PIN (4-6 Digits)</th>
                    <th>STATE</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($staff as $s): 
                    $curr_pin = $pins[$s['id']] ?? '';
                ?>
                    <tr>
                        <td style="font-weight:700;"><?= htmlspecialchars((string)($s['full_name'] ?? '')) ?></td>
                        <td style="text-transform: capitalize; font-size:11px; font-weight:600; color:var(--text-muted);"><?= str_replace('_', ' ', $s['role']) ?></td>
                        <td>
                            <form method="POST" style="display:flex; gap:10px; align-items:center;">
                                <input type="hidden" name="action" value="set_pin">
                                <input type="hidden" name="user_id" value="<?= $s['id'] ?>">
                                <input type="password" name="pin" placeholder="<?= $curr_pin ? '••••••' : 'Set New PIN' ?>" style="padding:6px 12px; border:1px solid var(--border); border-radius:6px; font-size:13px; width:120px;">
                                <button type="submit" style="background:var(--primary); color:white; border:none; border-radius:6px; padding:6px 12px; font-size:11px; font-weight:700; cursor:pointer;">Set</button>
                            </form>
                        </td>
                        <td>
                            <i class="ph ph-shield-check" style="font-size:20px; color:<?= $curr_pin ? '#10b981' : '#f59e0b' ?>;"></i>
                            <span style="font-size:10px; font-weight:700;"><?= $curr_pin ? 'SECURED' : 'PENDING' ?></span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require ROOT_PATH . '/app/Views/school_admin/layout/footer.php'; ?>
