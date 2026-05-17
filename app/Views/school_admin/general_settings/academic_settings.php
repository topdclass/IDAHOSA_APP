<?php
// DB Connection
try {
    $message = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['action'])) {
            if ($_POST['action'] === 'set_active_session') {
                $id = $_POST['session_id'] ?? 0;
                $pdo->exec("UPDATE academic_sessions SET isActive = 0");
                $stmt = $pdo->prepare("UPDATE academic_sessions SET isActive = 1 WHERE id = ?");
                $stmt->execute([$id]);
                $message = "Active Session updated!";
            } elseif ($_POST['action'] === 'set_active_term') {
                $id = $_POST['term_id'] ?? 0;
                $pdo->exec("UPDATE academic_semesters SET isActive = 0");
                $stmt = $pdo->prepare("UPDATE academic_semesters SET isActive = 1 WHERE id = ?");
                $stmt->execute([$id]);
                $message = "Active Term updated!";
            } elseif ($_POST['action'] === 'add_session') {
                $name = $_POST['session_name'] ?? '';
                $stmt = $pdo->prepare("INSERT INTO academic_sessions (name, created_at) VALUES (?, NOW())");
                $stmt->execute([$name]);
                $message = "New Session added!";
            } elseif ($_POST['action'] === 'add_term') {
                $name = $_POST['term_name'] ?? '';
                $stmt = $pdo->prepare("INSERT INTO academic_semesters (name, created_at) VALUES (?, NOW())");
                $stmt->execute([$name]);
                $message = "New Term added!";
            }
        }
    }

    $stmt = $pdo->query("SELECT * FROM academic_sessions WHERE is_deleted = 0 ORDER BY name DESC");
    $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->query("SELECT * FROM academic_semesters WHERE is_deleted = 0 ORDER BY id ASC");
    $terms = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

$pageTitle = 'Academic Settings - General Settings';
require ROOT_PATH . '/app/Views/school_admin/layout/header.php';
require ROOT_PATH . '/app/Views/school_admin/layout/sidebar.php';
?>

<div class="main-container">
    <div class="top-header">
        <div class="greeting">General Settings / <span style="color:var(--primary)">Academic Session & Term</span></div>
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

    <?php if ($message): ?>
        <div style="background:#d1fae5; color:#065f46; padding:10px; border-radius:6px; margin-bottom:15px; font-size:13px; font-weight:600;"><?= $message ?></div>
    <?php endif; ?>

    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:24px;">
        
        <!-- SECTION 1: SESSIONS -->
        <div class="crud-card">
            <div class="crud-header">
                <h2 class="crud-title">Academic Sessions</h2>
            </div>
            
            <form method="POST" style="display:flex; gap:8px; margin-bottom:20px;">
                <input type="hidden" name="action" value="add_session">
                <input type="text" name="session_name" placeholder="e.g. 2023/2024" required style="flex:1; padding:10px; border:1px solid var(--border); border-radius:6px; font-size:13px;">
                <button type="submit" class="btn-primary" style="padding:10px 16px;">Add</button>
            </form>

            <table class="crud-table" style="font-size:12px;">
                <thead>
                    <tr>
                        <th>SESSION</th>
                        <th>STATUS</th>
                        <th>ACTION</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sessions as $s): ?>
                        <tr>
                            <td style="font-weight:700;"><?= htmlspecialchars((string)($s['name'] ?? '')) ?></td>
                            <td>
                                <span style="padding:4px 8px; border-radius:20px; font-size:10px; font-weight:700; background:<?= $s['isActive'] ? '#dcfce7; color:#166534;' : '#f3f4f6; color:#4b5563;' ?>">
                                    <?= $s['isActive'] ? 'ACTIVE' : 'INACTIVE' ?>
                                </span>
                            </td>
                            <td>
                                <?php if (!$s['isActive']): ?>
                                    <form method="POST">
                                        <input type="hidden" name="action" value="set_active_session">
                                        <input type="hidden" name="session_id" value="<?= $s['id'] ?>">
                                        <button type="submit" style="background:none; border:none; color:var(--primary); font-size:11px; font-weight:700; cursor:pointer; text-decoration:underline;">Set Active</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- SECTION 2: TERMS -->
        <div class="crud-card">
            <div class="crud-header">
                <h2 class="crud-title">Academic Terms</h2>
            </div>
            
            <form method="POST" style="display:flex; gap:8px; margin-bottom:20px;">
                <input type="hidden" name="action" value="add_term">
                <input type="text" name="term_name" placeholder="e.g. First Term" required style="flex:1; padding:10px; border:1px solid var(--border); border-radius:6px; font-size:13px;">
                <button type="submit" class="btn-primary" style="padding:10px 16px;">Add</button>
            </form>

            <table class="crud-table" style="font-size:12px;">
                <thead>
                    <tr>
                        <th>TERM NAME</th>
                        <th>STATUS</th>
                        <th>ACTION</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($terms as $t): ?>
                        <tr>
                            <td style="font-weight:700;"><?= htmlspecialchars((string)($t['name'] ?? '')) ?></td>
                            <td>
                                <span style="padding:4px 8px; border-radius:20px; font-size:10px; font-weight:700; background:<?= $t['isActive'] ? '#dcfce7; color:#166534;' : '#f3f4f6; color:#4b5563;' ?>">
                                    <?= $t['isActive'] ? 'ACTIVE' : 'INACTIVE' ?>
                                </span>
                            </td>
                            <td>
                                <?php if (!$t['isActive']): ?>
                                    <form method="POST">
                                        <input type="hidden" name="action" value="set_active_term">
                                        <input type="hidden" name="term_id" value="<?= $t['id'] ?>">
                                        <button type="submit" style="background:none; border:none; color:var(--primary); font-size:11px; font-weight:700; cursor:pointer; text-decoration:underline;">Activate</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    </div>
</div>

<?php require ROOT_PATH . '/app/Views/school_admin/layout/footer.php'; ?>
