<?php
// Attendance Time Rules & Shift Logic

try {
    
    // Auto Migration: Time Rules
    $pdo->exec("CREATE TABLE IF NOT EXISTS attendance_time_rules (
        id INT AUTO_INCREMENT PRIMARY KEY,
        rule_name VARCHAR(255) NOT NULL,
        start_time TIME NOT NULL,
        late_threshold TIME NOT NULL,
        end_time TIME NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $message = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['action'])) {
            if ($_POST['action'] === 'add') {
                $name = $_POST['rule_name'] ?? '';
                $start = $_POST['start_time'] ?? '08:00';
                $late = $_POST['late_threshold'] ?? '08:15';
                $end = $_POST['end_time'] ?? '16:00';

                $stmt = $pdo->prepare("INSERT INTO attendance_time_rules (rule_name, start_time, late_threshold, end_time) VALUES (?, ?, ?, ?)");
                $stmt->execute([$name, $start, $late, $end]);
                $message = "Shift Rule created!";
            } elseif ($_POST['action'] === 'delete') {
                $id = $_POST['id'] ?? 0;
                $stmt = $pdo->prepare("DELETE FROM attendance_time_rules WHERE id = ?");
                $stmt->execute([$id]);
                $message = "Rule deleted.";
            }
        }
    }

    $stmt = $pdo->query("SELECT * FROM attendance_time_rules ORDER BY id DESC");
    $rules = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

$pageTitle = 'Attendance Time Rules';
require ROOT_PATH . '/app/Views/school_admin/layout/header.php';
require ROOT_PATH . '/app/Views/school_admin/layout/sidebar.php';
?>

<div class="main-container">
    <div class="top-header">
        <div class="greeting">Attendance / <span style="color:var(--primary)">Time Rules & Shifts</span></div>
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

    <div class="crud-card" style="margin-bottom:24px;">
        <div class="crud-header">
            <h2 class="crud-title">Create New Timing Rule</h2>
        </div>
        
        <?php if ($message): ?>
            <div style="background:#d1fae5; color:#065f46; padding:10px; border-radius:6px; margin-bottom:15px; font-size:13px;"><?= $message ?></div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="action" value="add">
            <div style="display:grid; grid-template-columns: 2fr 1fr 1fr 1fr; gap:12px; align-items:flex-end;">
                <div>
                    <label style="display:block; font-size:11px; font-weight:700; margin-bottom:5px;">RULE NAME</label>
                    <input type="text" name="rule_name" required placeholder="e.g. Standard Morning" style="width:100%; padding:8px 12px; border:1px solid var(--border); border-radius:6px; font-size:13px;">
                </div>
                <div>
                    <label style="display:block; font-size:11px; font-weight:700; margin-bottom:5px;">START TIME</label>
                    <input type="time" name="start_time" required value="08:00" style="width:100%; padding:8px 12px; border:1px solid var(--border); border-radius:6px; font-size:13px;">
                </div>
                <div>
                    <label style="display:block; font-size:11px; font-weight:700; margin-bottom:5px;">LATE THRESHOLD</label>
                    <input type="time" name="late_threshold" required value="08:15" style="width:100%; padding:8px 12px; border:1px solid var(--border); border-radius:6px; font-size:13px;">
                </div>
                <div>
                    <label style="display:block; font-size:11px; font-weight:700; margin-bottom:5px;">END TIME</label>
                    <input type="time" name="end_time" required value="16:00" style="width:100%; padding:8px 12px; border:1px solid var(--border); border-radius:6px; font-size:13px;">
                </div>
            </div>
            <div style="margin-top:16px; display:flex; justify-content:flex-end;">
                <button type="submit" class="btn-primary">Create Shift Rule</button>
            </div>
        </form>
    </div>

    <div class="crud-card">
        <table class="crud-table">
            <thead>
                <tr>
                    <th>RULE NAME</th>
                    <th>START</th>
                    <th>LATE AFTER</th>
                    <th>END</th>
                    <th>ACTIONS</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rules)): ?>
                    <tr><td colspan="5" style="text-align:center; padding:30px; color:var(--text-muted);">No time rules defined.</td></tr>
                <?php else: ?>
                    <?php foreach($rules as $r): ?>
                        <tr>
                            <td style="font-weight:700;"><?= htmlspecialchars((string)($r['rule_name'] ?? '')) ?></td>
                            <td><?= date('h:i A', strtotime($r['start_time'])) ?></td>
                            <td style="color:#ef4444; font-weight:700;"><?= date('h:i A', strtotime($r['late_threshold'])) ?></td>
                            <td><?= date('h:i A', strtotime($r['end_time'])) ?></td>
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
