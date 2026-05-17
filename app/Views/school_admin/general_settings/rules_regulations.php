<?php
// DB Connection
try {
    
    // Auto migration
    $pdo->exec("CREATE TABLE IF NOT EXISTS rules_regulations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        content TEXT NOT NULL,
        status TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $message = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['action'])) {
            if ($_POST['action'] === 'add') {
                $title = $_POST['title'] ?? '';
                $content = $_POST['content'] ?? '';
                $stmt = $pdo->prepare("INSERT INTO rules_regulations (title, content) VALUES (?, ?)");
                $stmt->execute([$title, $content]);
                $message = "New Rule added!";
            } elseif ($_POST['action'] === 'delete') {
                $id = $_POST['id'] ?? 0;
                $stmt = $pdo->prepare("DELETE FROM rules_regulations WHERE id = ?");
                $stmt->execute([$id]);
                $message = "Rule removed successfully.";
            }
        }
    }

    $stmt = $pdo->query("SELECT * FROM rules_regulations ORDER BY id DESC");
    $rules = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

$pageTitle = 'Rules & Regulations - General Settings';
require ROOT_PATH . '/app/Views/school_admin/layout/header.php';
require ROOT_PATH . '/app/Views/school_admin/layout/sidebar.php';
?>

<div class="main-container">
    <div class="top-header">
        <div class="greeting">General Settings / <span style="color:var(--primary)">Rules & Regulations</span></div>
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

    <!-- FORM -->
    <div class="crud-card" style="margin-bottom:24px;">
        <div class="crud-header">
            <h2 class="crud-title">Add School Rule / Regulation</h2>
        </div>
        
        <?php if ($message): ?>
            <div style="background:#d1fae5; color:#065f46; padding:10px; border-radius:6px; margin-bottom:20px; font-size:13px; font-weight:600;"><?= $message ?></div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="action" value="add">
            <div style="margin-bottom:16px;">
                <label style="display:block; font-size:12px; font-weight:600; margin-bottom:5px;">Rule Title</label>
                <input type="text" name="title" required placeholder="e.g. Attendance Policy" style="width:100%; padding:10px; border:1px solid var(--border); border-radius:6px; font-size:13px;">
            </div>
            <div style="margin-bottom:16px;">
                <label style="display:block; font-size:12px; font-weight:600; margin-bottom:5px;">Full Description / Text</label>
                <textarea name="content" required rows="6" placeholder="Explain the rule in detail..." style="width:100%; padding:10px; border:1px solid var(--border); border-radius:6px; font-size:13px; resize:none;"></textarea>
            </div>
            <div style="display:flex; justify-content:flex-end;">
                <button type="submit" class="btn-primary" style="padding:10px 24px;">Publish Rule</button>
            </div>
        </form>
    </div>

    <!-- LIST -->
    <div class="crud-card">
        <div class="crud-header">
            <h2 class="crud-title">Active Regulations</h2>
        </div>
        
        <table class="crud-table">
            <thead>
                <tr>
                    <th style="width:30%;">TITLE</th>
                    <th>DESCRIPTION</th>
                    <th style="width:100px;">ACTIONS</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rules)): ?>
                    <tr><td colspan="3" style="text-align:center; color:var(--text-muted); padding:30px;">No rules found.</td></tr>
                <?php else: ?>
                    <?php foreach ($rules as $r): ?>
                        <tr>
                            <td style="font-weight:700; color:var(--primary);"><?= htmlspecialchars((string)($r['title'] ?? '')) ?></td>
                            <td style="white-space: pre-wrap; line-height:1.5; font-size:12px;"><?= htmlspecialchars((string)($r['content'] ?? '')) ?></td>
                            <td>
                                <form method="POST" onsubmit="return confirm('Delete this rule?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $r['id'] ?>">
                                    <button type="submit" style="background:none; border:none; color:#ef4444; cursor:pointer;"><i class="ph ph-trash" style="font-size:18px;"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require ROOT_PATH . '/app/Views/school_admin/layout/footer.php'; ?>
