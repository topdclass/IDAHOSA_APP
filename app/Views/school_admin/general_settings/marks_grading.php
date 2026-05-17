<?php
// DB Connection
try {
    
    // Auto migration
    $pdo->exec("CREATE TABLE IF NOT EXISTS grade_points (
        id INT AUTO_INCREMENT PRIMARY KEY,
        grade_name VARCHAR(10) NOT NULL,
        min_score INT NOT NULL,
        max_score INT NOT NULL,
        grade_point FLOAT NOT NULL DEFAULT 0.0,
        remarks VARCHAR(100),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $message = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['action'])) {
            if ($_POST['action'] === 'add') {
                $name = $_POST['grade_name'] ?? '';
                $min = $_POST['min_score'] ?? 0;
                $max = $_POST['max_score'] ?? 0;
                $gp = $_POST['grade_point'] ?? 0.0;
                $rm = $_POST['remarks'] ?? '';

                $stmt = $pdo->prepare("INSERT INTO grade_points (grade_name, min_score, max_score, grade_point, remarks) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$name, $min, $max, $gp, $rm]);
                $message = "Grade Point set successfully!";
            } elseif ($_POST['action'] === 'delete') {
                $id = $_POST['id'] ?? 0;
                $stmt = $pdo->prepare("DELETE FROM grade_points WHERE id = ?");
                $stmt->execute([$id]);
                $message = "Grade Point deleted.";
            }
        }
    }

    $stmt = $pdo->query("SELECT * FROM grade_points ORDER BY max_score DESC");
    $grades = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

$pageTitle = 'Marks Grading - General Settings';
require ROOT_PATH . '/app/Views/school_admin/layout/header.php';
require ROOT_PATH . '/app/Views/school_admin/layout/sidebar.php';
?>

<div class="main-container">
    <div class="top-header">
        <div class="greeting">General Settings / <span style="color:var(--primary)">Marks Grading Settings</span></div>
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
            <h2 class="crud-title">Add New Grade Level</h2>
        </div>
        
        <?php if ($message): ?>
            <div style="background:#d1fae5; color:#065f46; padding:10px; border-radius:6px; margin-bottom:15px; font-size:13px; font-weight:600;"><?= $message ?></div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="action" value="add">
            <div style="display:grid; grid-template-columns: repeat(5, 1fr); gap:12px; align-items: flex-end;">
                <div>
                    <label style="display:block; font-size:11px; font-weight:700; margin-bottom:5px; color:var(--text-muted);">GRADE NAME</label>
                    <input type="text" name="grade_name" required placeholder="e.g. A1" style="width:100%; padding:8px 12px; border:1px solid var(--border); border-radius:6px; font-size:13px;">
                </div>
                <div>
                    <label style="display:block; font-size:11px; font-weight:700; margin-bottom:5px; color:var(--text-muted);">MIN SCORE</label>
                    <input type="number" name="min_score" required value="0" style="width:100%; padding:8px 12px; border:1px solid var(--border); border-radius:6px; font-size:13px;">
                </div>
                <div>
                    <label style="display:block; font-size:11px; font-weight:700; margin-bottom:5px; color:var(--text-muted);">MAX SCORE</label>
                    <input type="number" name="max_score" required value="100" style="width:100%; padding:8px 12px; border:1px solid var(--border); border-radius:6px; font-size:13px;">
                </div>
                <div>
                    <label style="display:block; font-size:11px; font-weight:700; margin-bottom:5px; color:var(--text-muted);">GP (Point)</label>
                    <input type="number" step="0.1" name="grade_point" required value="4.0" style="width:100%; padding:8px 12px; border:1px solid var(--border); border-radius:6px; font-size:13px;">
                </div>
                <div>
                    <label style="display:block; font-size:11px; font-weight:700; margin-bottom:5px; color:var(--text-muted);">REMARKS</label>
                    <input type="text" name="remarks" required placeholder="Excellent" style="width:100%; padding:8px 12px; border:1px solid var(--border); border-radius:6px; font-size:13px;">
                </div>
            </div>
            <div style="margin-top:16px; display:flex; justify-content:flex-end;">
                <button type="submit" class="btn-primary">Add Grade Point</button>
            </div>
        </form>
    </div>

    <!-- LIST -->
    <div class="crud-card">
        <div class="crud-header">
            <h2 class="crud-title">Grading System Table</h2>
        </div>
        
        <table class="crud-table">
            <thead>
                <tr>
                    <th>GRADE</th>
                    <th>SCORE RANGE</th>
                    <th>GRADE POINT</th>
                    <th>REMARKS</th>
                    <th>ACTIONS</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($grades)): ?>
                    <tr><td colspan="5" style="text-align:center; color:var(--text-muted); padding:30px;">No grading data found.</td></tr>
                <?php else: ?>
                    <?php foreach ($grades as $g): ?>
                        <tr>
                            <td style="font-weight:700; color:var(--primary);"><?= htmlspecialchars((string)($g['grade_name'] ?? '')) ?></td>
                            <td><?= $g['min_score'] ?>% - <?= $g['max_score'] ?>%</td>
                            <td><?= number_format($g['grade_point'], 1) ?></td>
                            <td><?= htmlspecialchars((string)($g['remarks'] ?? '')) ?></td>
                            <td>
                                <form method="POST" onsubmit="return confirm('Delete this grade scale?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $g['id'] ?>">
                                    <button type="submit" style="background:none; border:none; cursor:pointer; color:#ef4444;"><i class="ph ph-trash" style="font-size:18px;"></i></button>
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
