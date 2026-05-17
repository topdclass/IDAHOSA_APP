<?php
// DB Connection
try {
    
    // Auto migration: Create fee_particulars table
    $pdo->exec("CREATE TABLE IF NOT EXISTS fee_particulars (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        description TEXT,
        default_amount DECIMAL(15, 2) DEFAULT 0.00,
        is_mandatory TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $message = '';
    $error = '';

    // Handle POST Actions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['action'])) {
            if ($_POST['action'] === 'add') {
                $name = $_POST['name'] ?? '';
                $desc = $_POST['description'] ?? '';
                $amt = $_POST['default_amount'] ?? 0;
                $mand = isset($_POST['is_mandatory']) ? 1 : 0;

                $stmt = $pdo->prepare("INSERT INTO fee_particulars (name, description, default_amount, is_mandatory) VALUES (?, ?, ?, ?)");
                $stmt->execute([$name, $desc, $amt, $mand]);
                $message = "Fee Particular added successfully!";
            } elseif ($_POST['action'] === 'delete') {
                $id = $_POST['id'] ?? 0;
                $stmt = $pdo->prepare("DELETE FROM fee_particulars WHERE id = ?");
                $stmt->execute([$id]);
                $message = "Fee Particular deleted!";
            }
        }
    }

    // Fetch All
    $stmt = $pdo->query("SELECT * FROM fee_particulars ORDER BY id DESC");
    $fees = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error = "Database Error: " . $e->getMessage();
}

$pageTitle = 'Fee Particulars - General Settings';
require ROOT_PATH . '/app/Views/school_admin/layout/header.php';
require ROOT_PATH . '/app/Views/school_admin/layout/sidebar.php';
?>

<div class="main-container">
    <div class="top-header">
        <div class="greeting">General Settings / <span style="color:var(--primary)">Fee Particulars</span></div>
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

    <!-- Add New Section -->
    <div class="crud-card" style="margin-bottom: 24px;">
        <div class="crud-header">
            <h2 class="crud-title">Add New Fee Particular</h2>
        </div>
        
        <?php if ($message): ?>
            <div style="background:#d1fae5; color:#065f46; padding:10px; border-radius:6px; margin-bottom:15px; font-size:13px;"><?= $message ?></div>
        <?php endif; ?>

        <form method="POST" style="display:flex; gap:16px; align-items: flex-end; flex-wrap: wrap;">
            <input type="hidden" name="action" value="add">
            <div style="flex:1; min-width:200px;">
                <label style="display:block; font-size:12px; font-weight:600; margin-bottom:4px;">Fee Name</label>
                <input type="text" name="name" required placeholder="e.g. Tuition Fee" style="width:100%; padding:8px 12px; border:1px solid var(--border); border-radius:6px; font-size:13px;">
            </div>
            <div style="width:150px;">
                <label style="display:block; font-size:12px; font-weight:600; margin-bottom:4px;">Default Amnt (₦)</label>
                <input type="number" step="0.01" name="default_amount" required value="0" style="width:100%; padding:8px 12px; border:1px solid var(--border); border-radius:6px; font-size:13px;">
            </div>
            <div style="display:flex; align-items:center; gap:8px; padding-bottom:10px;">
                <input type="checkbox" name="is_mandatory" checked id="mand">
                <label for="mand" style="font-size:12px; font-weight:600; cursor:pointer;">Mandatory</label>
            </div>
            <button type="submit" class="btn-primary" style="height:38px;">Save Fee</button>
        </form>
    </div>

    <!-- Table Section -->
    <div class="crud-card">
        <div class="crud-header">
            <h2 class="crud-title">Existing Fee Particulars</h2>
        </div>
        
        <table class="crud-table">
            <thead>
                <tr>
                    <th>NAME</th>
                    <th>DEFAULT AMOUNT</th>
                    <th>MANDATORY</th>
                    <th>ACTIONS</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($fees)): ?>
                    <tr><td colspan="4" style="text-align:center; color:var(--text-muted);">No fee particulars found.</td></tr>
                <?php else: ?>
                    <?php foreach ($fees as $f): ?>
                        <tr>
                            <td><?= htmlspecialchars((string)($f['name'] ?? '')) ?></td>
                            <td>₦<?= number_format($f['default_amount'], 2) ?></td>
                            <td>
                                <span style="padding:4px 8px; border-radius:20px; font-size:10px; font-weight:700; background:<?= $f['is_mandatory'] ? '#dcfce7; color:#166534;' : '#f3f4f6; color:#4b5563;' ?>">
                                    <?= $f['is_mandatory'] ? 'YES' : 'NO' ?>
                                </span>
                            </td>
                            <td>
                                <form method="POST" onsubmit="return confirm('Delete this fee?');" style="display:inline;">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $f['id'] ?>">
                                    <button type="submit" style="background:none; border:none; color:#ef4444; cursor:pointer;"><i class="ph ph-trash"></i></button>
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
