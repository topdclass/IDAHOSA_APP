<?php
// Rule Assignment Logic

try {
    
    // Auto Migration: Rule Assignments
    $pdo->exec("CREATE TABLE IF NOT EXISTS attendance_rule_assignments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        rule_id INT NOT NULL,
        UNIQUE(user_id)
    )");

    $message = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['action'])) {
            if ($_POST['action'] === 'assign') {
                $uid = $_POST['user_id'] ?? 0;
                $rid = $_POST['rule_id'] ?? 0;

                $stmt = $pdo->prepare("INSERT INTO attendance_rule_assignments (user_id, rule_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE rule_id = VALUES(rule_id)");
                $stmt->execute([$uid, $rid]);
                $message = "Staff assignment updated!";
            }
        }
    }

    // Fetch All Staff
    $stmt = $pdo->query("SELECT id, full_name, role FROM users WHERE role IN ('teacher', 'support_staff', 'finance_officer') ORDER BY full_name ASC");
    $staff = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch All Rules
    $rules = $pdo->query("SELECT * FROM attendance_time_rules")->fetchAll(PDO::FETCH_ASSOC);

    // Fetch Assignments
    $stmt = $pdo->query("SELECT user_id, rule_id FROM attendance_rule_assignments");
    $assignments = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

$pageTitle = 'Assign Attendance Rules';
require ROOT_PATH . '/app/Views/school_admin/layout/header.php';
require ROOT_PATH . '/app/Views/school_admin/layout/sidebar.php';
?>

<div class="main-container">
    <div class="top-header">
        <div class="greeting">Attendance / <span style="color:var(--primary)">Rule Assignments</span></div>
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
            <h2 class="crud-title">Staff Shift Assignments</h2>
            <?php if ($message): ?>
                <span style="color:#10b981; font-weight:700; font-size:12px;"><?= $message ?></span>
            <?php endif; ?>
        </div>

        <table class="crud-table">
            <thead>
                <tr>
                    <th>STAFF NAME</th>
                    <th>ROLE</th>
                    <th>ASSIGNED RULE</th>
                    <th>ACTION</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($staff)): ?>
                    <tr><td colspan="4" style="text-align:center; padding:30px; color:var(--text-muted);">No staff members found on record.</td></tr>
                <?php else: ?>
                    <?php foreach($staff as $s): 
                        $curr_rule = $assignments[$s['id']] ?? 0;
                    ?>
                        <tr>
                            <td style="font-weight:700;"><?= htmlspecialchars((string)($s['full_name'] ?? '')) ?></td>
                            <td style="text-transform: capitalize; font-size:11px; font-weight:600; color:var(--text-muted);"><?= str_replace('_', ' ', $s['role']) ?></td>
                            <td>
                                <form method="POST" id="assign-form-<?= $s['id'] ?>">
                                    <input type="hidden" name="action" value="assign">
                                    <input type="hidden" name="user_id" value="<?= $s['id'] ?>">
                                    <select name="rule_id" onchange="this.form.submit()" style="padding:4px 8px; border:1px solid var(--border); border-radius:4px; font-size:12px; outline:none;">
                                        <option value="0">-- Unassigned --</option>
                                        <?php foreach($rules as $r): ?>
                                            <option value="<?= $r['id'] ?>" <?= $curr_rule == $r['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string)($r['rule_name'] ?? '')) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </form>
                            </td>
                            <td>
                                <span style="font-size:10px; font-weight:700; color:<?= $curr_rule ? '#10b981' : '#f59e0b' ?>;">
                                    <?= $curr_rule ? 'CONFIGURED' : 'PENDING' ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require ROOT_PATH . '/app/Views/school_admin/layout/footer.php'; ?>
