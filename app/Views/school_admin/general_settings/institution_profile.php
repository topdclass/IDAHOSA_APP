<?php
// Core logic connecting to the database and saving institution configurations seamlessly.

require_once ROOT_PATH . '/config/database.php';
    
try {
    // In a multi-tenant DB, institution_profile is local to this school. 
    // It shouldn't strictly require tenant_id if it's the only record.
    $pdo->exec("CREATE TABLE IF NOT EXISTS institution_profile (
        id INT AUTO_INCREMENT PRIMARY KEY,
        institution_name VARCHAR(255) NOT NULL,
        institution_code VARCHAR(100),
        address TEXT,
        contact_phone VARCHAR(50),
        contact_email VARCHAR(100),
        website VARCHAR(100),
        established_year YEAR,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");

    // Auto-migrate columns if missing
    $tablesToUpdate = [
        'institution_profile' => ['signature_url', 'logo_url']
    ];

    foreach ($tablesToUpdate as $table => $cols) {
        foreach ($cols as $col) {
            $check = $pdo->query("SHOW COLUMNS FROM `$table` LIKE '$col'")->fetch();
            if (!$check) {
                $pdo->exec("ALTER TABLE `$table` ADD `$col` VARCHAR(255) DEFAULT NULL");
            }
        }
    }

    $message = '';

    // Multi-tenant logic: Use school_id from session
    $tenant_id = $_SESSION['school_id'] ?? 0;

    // Handle Form submission for CRUD (Update/Create) logic
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tenant_id > 0) {
        $name = $_POST['institution_name'] ?? 'Rosmon International School';
        $code = $_POST['institution_code'] ?? '';
        $address = $_POST['address'] ?? 'Institute';
        $phone = $_POST['contact_phone'] ?? '';
        $email = $_POST['contact_email'] ?? '';
        $website = $_POST['website'] ?? '';
        $year = $_POST['established_year'] ?? '';

        // Check if a profile object already exists (Tenant DB has only one record)
        $stmt = $pdo->query("SELECT * FROM institution_profile LIMIT 1");
        $profile = $stmt->fetch(PDO::FETCH_ASSOC);

        $logo_url = $profile['logo_url'] ?? null;
        $sig_url = $profile['signature_url'] ?? null;

        // Handle File Uploads
        $uploadDir = ROOT_PATH . '/public/uploads/branding/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

        if (!empty($_FILES['logo']['name'])) {
            $logoName = 'logo_' . $tenant_id . '_' . time() . '.' . pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION);
            if (move_uploaded_file($_FILES['logo']['tmp_name'], $uploadDir . $logoName)) {
                $logo_url = '/uploads/branding/' . $logoName;
            }
        }

        if (!empty($_FILES['signature']['name'])) {
            $sigName = 'sig_' . $tenant_id . '_' . time() . '.' . pathinfo($_FILES['signature']['name'], PATHINFO_EXTENSION);
            if (move_uploaded_file($_FILES['signature']['tmp_name'], $uploadDir . $sigName)) {
                $sig_url = '/uploads/branding/' . $sigName;
            }
        }

        if ($profile) {
            $update = $pdo->prepare("UPDATE institution_profile SET institution_name=?, institution_code=?, address=?, contact_phone=?, contact_email=?, website=?, established_year=?, logo_url=?, signature_url=? WHERE id=?");
            $update->execute([$name, $code, $address, $phone, $email, $website, $year, $logo_url, $sig_url, $profile['id']]);
            $message = 'Institution Profile and Branding updated successfully!';
        } else {
            $insert = $pdo->prepare("INSERT INTO institution_profile (institution_name, institution_code, address, contact_phone, contact_email, website, established_year, logo_url, signature_url) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $insert->execute([$name, $code, $address, $phone, $email, $website, $year, $logo_url, $sig_url]);
            $message = 'Institution Profile and Branding created successfully!';
        }

        // AGENTIC TASK: Also update the Supervisor DB for global branding consistency
        try {
            require_once ROOT_PATH . '/config/tenant_manager.php';
            $supervisorPdo = TenantManager::getTenantConnection(null);
            
            $checkSu = $supervisorPdo->query("SHOW COLUMNS FROM `institution_profile` LIKE 'logo_url'")->fetch();
            if (!$checkSu) {
                $supervisorPdo->exec("ALTER TABLE `institution_profile` ADD `logo_url` VARCHAR(255) DEFAULT NULL");
            }
            
            $suUpdate = $supervisorPdo->prepare("UPDATE institution_profile SET institution_name=?, address=?, logo_url=? WHERE id=?");
            $suUpdate->execute([$name, $address, $logo_url, $tenant_id]);
        } catch(Exception $e) { /* silent sync fail */ }
    }

    // Fetch Profile state cleanly for this tenant (Tenant DB only has one record)
    $stmt = $pdo->query("SELECT * FROM institution_profile LIMIT 1");
    $profile = $stmt->fetch(PDO::FETCH_ASSOC);

    // Default Fallbacks
    if (!$profile) {
        $profile = [
            'institution_name' => 'Rosmon International School',
            'address' => 'Institute'
        ];
    }

} catch (PDOException $e) {
    die("Database Error Framework: " . $e->getMessage());
}

// Pass variables seamlessly to the layout header
$pageTitle = 'Institution Profile - General Settings';
require ROOT_PATH . '/app/Views/school_admin/layout/header.php';
require ROOT_PATH . '/app/Views/school_admin/layout/sidebar.php';
?>

<!-- MAIN DASHBOARD CONTENT BLOCK -->
<div class="main-container">
    <div class="top-header">
        <div class="greeting">General Settings / <span style="color:var(--primary)">Institution Profile</span></div>
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

    <!-- Active Form Display Card -->
    <div class="crud-card" style="max-width: 800px; margin:0 auto; margin-top:20px;">
        <div class="crud-header">
            <h2 class="crud-title"><i class="ph ph-buildings" style="vertical-align:middle; font-size:22px; margin-right:8px;"></i> Manage Institution Profile</h2>
        </div>

        <?php if ($message): ?>
            <div style="background:#d1fae5; color:#065f46; padding:12px; border-radius:6px; margin-bottom:20px; font-weight:600; font-size:13px; border-left:4px solid #10b981;">
                <?= htmlspecialchars((string)($message ?? '')) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="" enctype="multipart/form-data">
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:24px; margin-bottom:24px;">
                <!-- Institution Name -->
                <div>
                    <label style="display:block; font-size:13px; font-weight:600; margin-bottom:8px; color:var(--text-dark);">Institution Name <span style="color:red">*</span></label>
                    <input type="text" name="institution_name" required value="<?= htmlspecialchars($profile['institution_name'] ?? '') ?>" placeholder="e.g. Rosmon International School" style="width:100%; padding:10px 14px; border:1px solid var(--border); border-radius:6px; font-size:14px; outline:none;">
                </div>
                <!-- Institution Code -->
                <div>
                    <label style="display:block; font-size:13px; font-weight:600; margin-bottom:8px; color:var(--text-dark);">Institution Code</label>
                    <input type="text" name="institution_code" value="<?= htmlspecialchars($profile['institution_code'] ?? '') ?>" placeholder="e.g. RIS-001" style="width:100%; padding:10px 14px; border:1px solid var(--border); border-radius:6px; font-size:14px; outline:none;">
                </div>

                <!-- Branding: Logo -->
                <div>
                    <label style="display:block; font-size:13px; font-weight:600; margin-bottom:8px; color:var(--text-dark);">School Logo</label>
                    <input type="file" name="logo" accept="image/*" style="width:100%; font-size:12px;">
                    <?php if(!empty($profile['logo_url'])): ?>
                        <div style="margin-top:8px;"><img src="<?= WEB_ROOT . '/public' . $profile['logo_url'] ?>" style="height:40px; border-radius:4px; border:1px solid #eee;"></div>
                    <?php endif; ?>
                </div>
                <!-- Branding: Signature -->
                <div>
                    <label style="display:block; font-size:13px; font-weight:600; margin-bottom:8px; color:var(--text-dark);">Proprietor Signature</label>
                    <input type="file" name="signature" accept="image/*" style="width:100%; font-size:12px;">
                    <?php if(!empty($profile['signature_url'])): ?>
                        <div style="margin-top:8px;"><img src="<?= WEB_ROOT . '/public' . $profile['signature_url'] ?>" style="height:40px; border-radius:4px; border:1px solid #eee;"></div>
                    <?php endif; ?>
                </div>

                <!-- Contact Phone -->
                <div>
                    <label style="display:block; font-size:13px; font-weight:600; margin-bottom:8px; color:var(--text-dark);">Contact Phone</label>
                    <input type="tel" name="contact_phone" value="<?= htmlspecialchars($profile['contact_phone'] ?? '') ?>" placeholder="+234..." style="width:100%; padding:10px 14px; border:1px solid var(--border); border-radius:6px; font-size:14px; outline:none;">
                </div>
                <!-- Contact Email -->
                <div>
                    <label style="display:block; font-size:13px; font-weight:600; margin-bottom:8px; color:var(--text-dark);">Contact Email</label>
                    <input type="email" name="contact_email" value="<?= htmlspecialchars($profile['contact_email'] ?? '') ?>" placeholder="admin@rosmon.edu" style="width:100%; padding:10px 14px; border:1px solid var(--border); border-radius:6px; font-size:14px; outline:none;">
                </div>
                <!-- Website -->
                <div>
                    <label style="display:block; font-size:13px; font-weight:600; margin-bottom:8px; color:var(--text-dark);">Website URL</label>
                    <input type="url" name="website" value="<?= htmlspecialchars($profile['website'] ?? '') ?>" placeholder="https://..." style="width:100%; padding:10px 14px; border:1px solid var(--border); border-radius:6px; font-size:14px; outline:none;">
                </div>
                <!-- Established Year -->
                <div>
                    <label style="display:block; font-size:13px; font-weight:600; margin-bottom:8px; color:var(--text-dark);">Established Year</label>
                    <input type="number" name="established_year" min="1800" max="2099" value="<?= htmlspecialchars($profile['established_year'] ?? '') ?>" placeholder="e.g. 2005" style="width:100%; padding:10px 14px; border:1px solid var(--border); border-radius:6px; font-size:14px; outline:none;">
                </div>
                <!-- Address -->
                <div style="grid-column: 1 / -1;">
                    <label style="display:block; font-size:13px; font-weight:600; margin-bottom:8px; color:var(--text-dark);">Full Address</label>
                    <textarea name="address" rows="3" placeholder="Enter full physical address or P.O Box..." style="width:100%; padding:10px 14px; border:1px solid var(--border); border-radius:6px; font-size:14px; outline:none; resize:none;"><?= htmlspecialchars($profile['address'] ?? '') ?></textarea>
                </div>
            </div>

            <div style="display:flex; justify-content:flex-end;">
                <button type="submit" class="btn-primary" style="padding:12px 24px; font-size:14px;">Save Institution Profile</button>
            </div>
        </form>
    </div>
</div>

<?php require ROOT_PATH . '/app/Views/school_admin/layout/footer.php'; ?>
