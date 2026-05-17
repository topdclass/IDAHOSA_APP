<?php
// DB Connection
try {
    
    // Auto migration: Create fee_challan_settings table
    $pdo->exec("CREATE TABLE IF NOT EXISTS fee_challan_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        bank_name VARCHAR(255),
        account_name VARCHAR(255),
        account_number VARCHAR(100),
        branch_name VARCHAR(255),
        additional_notes TEXT,
        last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");

    $message = '';
    
    // Handle POST Actions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $bank = $_POST['bank_name'] ?? '';
        $acc_name = $_POST['account_name'] ?? '';
        $acc_num = $_POST['account_number'] ?? '';
        $branch = $_POST['branch_name'] ?? '';
        $notes = $_POST['additional_notes'] ?? '';

        $stmt = $pdo->query("SELECT id FROM fee_challan_settings LIMIT 1");
        $exists = $stmt->fetch();

        if ($exists) {
            $update = $pdo->prepare("UPDATE fee_challan_settings SET bank_name=?, account_name=?, account_number=?, branch_name=?, additional_notes=? WHERE id=?");
            $update->execute([$bank, $acc_name, $acc_num, $branch, $notes, $exists['id']]);
        } else {
            $insert = $pdo->prepare("INSERT INTO fee_challan_settings (bank_name, account_name, account_number, branch_name, additional_notes) VALUES (?, ?, ?, ?, ?)");
            $insert->execute([$bank, $acc_name, $acc_num, $branch, $notes]);
        }
        $message = "Challan settings saved successfully!";
    }

    // Fetch Details
    $stmt = $pdo->query("SELECT * FROM fee_challan_settings LIMIT 1");
    $settings = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

$pageTitle = 'Challan Settings - General Settings';
require ROOT_PATH . '/app/Views/school_admin/layout/header.php';
require ROOT_PATH . '/app/Views/school_admin/layout/sidebar.php';
?>

<div class="main-container">
    <div class="top-header">
        <div class="greeting">General Settings / <span style="color:var(--primary)">Fee Challan Details</span></div>
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

    <div class="crud-card" style="max-width: 700px; margin: 20px auto;">
        <div class="crud-header">
            <h2 class="crud-title">Bank Details For Fee Slips</h2>
        </div>

        <?php if ($message): ?>
            <div style="background:#d1fae5; color:#065f46; padding:10px; border-radius:6px; margin-bottom:20px; font-size:13px; font-weight:600;"><?= $message ?></div>
        <?php endif; ?>

        <form method="POST">
            <div style="display:grid; grid-template-columns: 1fr; gap:20px;">
                <div>
                    <label style="display:block; font-size:12px; font-weight:600; margin-bottom:5px;">Bank Name</label>
                    <input type="text" name="bank_name" value="<?= htmlspecialchars($settings['bank_name'] ?? '') ?>" placeholder="e.g. Zenith Bank PLC" style="width:100%; padding:10px; border:1px solid var(--border); border-radius:6px; font-size:14px;">
                </div>
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px;">
                    <div>
                        <label style="display:block; font-size:12px; font-weight:600; margin-bottom:5px;">Account Name</label>
                        <input type="text" name="account_name" value="<?= htmlspecialchars($settings['account_name'] ?? '') ?>" placeholder="School Name" style="width:100%; padding:10px; border:1px solid var(--border); border-radius:6px; font-size:14px;">
                    </div>
                    <div>
                        <label style="display:block; font-size:12px; font-weight:600; margin-bottom:5px;">Account Number</label>
                        <input type="text" name="account_number" value="<?= htmlspecialchars($settings['account_number'] ?? '') ?>" placeholder="10-digit number" style="width:100%; padding:10px; border:1px solid var(--border); border-radius:6px; font-size:14px;">
                    </div>
                </div>
                <div>
                    <label style="display:block; font-size:12px; font-weight:600; margin-bottom:5px;">Branch / Other Info</label>
                    <input type="text" name="branch_name" value="<?= htmlspecialchars($settings['branch_name'] ?? '') ?>" placeholder="Main Branch, Lagos" style="width:100%; padding:10px; border:1px solid var(--border); border-radius:6px; font-size:14px;">
                </div>
                <div>
                    <label style="display:block; font-size:12px; font-weight:600; margin-bottom:5px;">Additional Instructions (Shown on Slip)</label>
                    <textarea name="additional_notes" rows="4" placeholder="e.g. Please present this slip at the teller counter..." style="width:100%; padding:10px; border:1px solid var(--border); border-radius:6px; font-size:14px; resize:none;"><?= htmlspecialchars($settings['additional_notes'] ?? '') ?></textarea>
                </div>
            </div>

            <div style="margin-top:24px; display:flex; justify-content:flex-end;">
                <button type="submit" class="btn-primary" style="padding:12px 24px;">Save Challan Settings</button>
            </div>
        </form>
    </div>
</div>

<?php require ROOT_PATH . '/app/Views/school_admin/layout/footer.php'; ?>
