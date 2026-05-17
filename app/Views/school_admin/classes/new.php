<?php
/**
 * Create New Class Configuration
 * Allows defining a Class Name and an optional Arm (Section).
 */
require_once ROOT_PATH . '/config/database.php';

// TENANT INTEGRITY GUARD
if (empty($_SESSION['school_id']) || $_SESSION['role'] !== 'school_admin') {
    header('Location: ' . WEB_ROOT . '/');
    exit;
}

$message = '';
$error = '';

// Ensure 'arm' column exists in classes table if 'section' was the old name
try {
    $check = $pdo->query("SHOW COLUMNS FROM classes LIKE 'arm'")->fetch();
    if (!$check) {
        $pdo->exec("ALTER TABLE classes ADD COLUMN arm VARCHAR(50) NULL AFTER class_name");
    }
} catch (Exception $e) {
    // Column might already exist or table might be locked
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['class_name'] ?? '');
    $arm  = trim($_POST['arm'] ?? '');
    $monthly_fee = $_POST['monthly_fee'] ?? 0;
    $teacher_id = isset($_POST['teacher_id']) && !empty($_POST['teacher_id']) ? (int)$_POST['teacher_id'] : null;

    if (empty($name)) {
        $error = "Class Name is required.";
    } else {
        try {
            // Check if this class+arm combo already exists for this tenant
            $checkStmt = $pdo->prepare("SELECT id FROM classes WHERE class_name = ? AND (arm=? OR section=?) AND is_deleted = 0 AND (institute_id=' . ($instituteId ?? 0) . ' OR institute_id IS NULL)");
            $checkStmt->execute([$name, $arm, $arm]);
            if ($checkStmt->fetch()) {
                $error = "This class " . ($arm ? "with arm '$arm' " : "") . "already exists.";
            } else {
                $stmt = $pdo->prepare("INSERT INTO classes (class_name, arm, section, capacity, teacher_id, monthly_fee, institute_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
                $stmt->execute([$name, $arm, $arm, (int)($_POST['capacity'] ?? 40), $teacher_id, $monthly_fee, $instituteId]);
                $message = "Class '$name'" . ($arm ? " (Arm $arm)" : "") . " initialized successfully!";
            }
        } catch (Exception $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
}

// Fetch Staff/Teachers for dropdown
$staffList = $pdo->query("SELECT id, full_name, role FROM users WHERE role IN ('Staff', 'Employee', 'Teacher', 'school_admin') ORDER BY full_name ASC")->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Add New Class - Rosmon SMS';
require ROOT_PATH . '/app/Views/school_admin/layout/header.php';
require ROOT_PATH . '/app/Views/school_admin/layout/sidebar.php';
?>

<div class="main-container">
    <div class="top-header">
        <div class="greeting">Academics / <span style="color:var(--primary)">Configure New Class</span></div>
        <div class="header-actions">
            <a href="<?= WEB_ROOT ?>/school-admin/classes/all" class="btn-primary" style="background:#f3f4f6; color:var(--text-dark); text-decoration:none;"><i class="ph ph-arrow-left"></i> All Classes</a>
        </div>
    </div>

    <div class="crud-card" style="max-width: 700px; margin: 40px auto; border-radius:16px; overflow:hidden; box-shadow: 0 10px 25px rgba(0,0,0,0.05);">
        <div class="crud-header" style="background: linear-gradient(135deg, var(--primary), #4f46e5); color:white; padding: 25px;">
            <h2 class="crud-title" style="color:white; margin:0; font-size:18px; font-weight:800; letter-spacing:0.5px;">CLASS INFRASTRUCTURE BUILDER</h2>
            <p style="margin:5px 0 0 0; opacity:0.8; font-size:12px; font-weight:500;">Define class names and their respective arms/sections for your institution.</p>
        </div>

        <div style="padding: 35px;">
            <?php if ($message): ?>
                <div style="background:#d1fae5; color:#065f46; padding:16px; border-radius:12px; margin-bottom:24px; font-weight:700; font-size:14px; border-left: 5px solid #10b981; display:flex; align-items:center; gap:12px;">
                    <i class="ph ph-check-circle" style="font-size:20px;"></i> <?= $message ?>
                </div>
            <?php elseif ($error): ?>
                <div style="background:#fee2e2; color:#991b1b; padding:16px; border-radius:12px; margin-bottom:24px; font-weight:700; font-size:14px; border-left: 5px solid #ef4444; display:flex; align-items:center; gap:12px;">
                    <i class="ph ph-warning-circle" style="font-size:20px;"></i> <?= $error ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:25px; margin-bottom:25px;">
                    <div>
                        <label style="display:block; font-size:13px; font-weight:700; margin-bottom:10px; color:#374151; text-transform:uppercase; letter-spacing:0.5px;">Main Class Name <span style="color:red;">*</span></label>
                        <input type="text" name="class_name" required placeholder="e.g. JSS 1 or Primary One" 
                               style="width:100%; padding:14px; border:1px solid #e5e7eb; border-radius:10px; outline:none; font-size:14px; transition:0.2s; background:#f9fafb;"
                               onfocus="this.style.borderColor='var(--primary)'; this.style.background='white'; this.style.boxShadow='0 0 0 4px rgba(79, 70, 229, 0.1)';"
                               onblur="this.style.borderColor='#e5e7eb'; this.style.background='#f9fafb'; this.style.boxShadow='none';">
                    </div>
                    <div>
                        <label style="display:block; font-size:13px; font-weight:700; margin-bottom:10px; color:#374151; text-transform:uppercase; letter-spacing:0.5px;">Class Arm / Section</label>
                        <input type="text" name="arm" placeholder="e.g. A, Gold, or Blue (Optional)" 
                               style="width:100%; padding:14px; border:1px solid #e5e7eb; border-radius:10px; outline:none; font-size:14px; transition:0.2s; background:#f9fafb;"
                               onfocus="this.style.borderColor='var(--primary)'; this.style.background='white'; this.style.boxShadow='0 0 0 4px rgba(79, 70, 229, 0.1)';"
                               onblur="this.style.borderColor='#e5e7eb'; this.style.background='#f9fafb'; this.style.boxShadow='none';">
                        <small style="display:block; margin-top:6px; color:#6b7280; font-size:11px;">Leave blank if the class has no arms.</small>
                    </div>
                </div>

                <div style="margin-bottom:35px; display:grid; grid-template-columns: 1fr 1fr; gap:25px;">
                    <div>
                        <label style="display:block; font-size:13px; font-weight:700; margin-bottom:10px; color:#374151; text-transform:uppercase; letter-spacing:0.5px;">Monthly Tuition Fee (₦)</label>
                        <div style="position:relative;">
                            <span style="position:absolute; left:14px; top:50%; transform:translateY(-50%); font-weight:800; color:#9ca3af;">₦</span>
                            <input type="number" name="monthly_fee" value="0.00" step="0.01" 
                                   style="width:100%; padding:14px 14px 14px 30px; border:1px solid #e5e7eb; border-radius:10px; outline:none; font-size:15px; font-weight:700; background:#f9fafb;"
                                   onfocus="this.style.borderColor='var(--primary)'; this.style.background='white'; this.style.boxShadow='0 0 0 4px rgba(79, 70, 229, 0.1)';"
                                   onblur="this.style.borderColor='#e5e7eb'; this.style.background='#f9fafb'; this.style.boxShadow='none';">
                        </div>
                    </div>
                    <div>
                        <label style="display:block; font-size:13px; font-weight:700; margin-bottom:10px; color:#374151; text-transform:uppercase; letter-spacing:0.5px;">Assign Form Teacher</label>
                        <select name="teacher_id" style="width:100%; padding:14px; border:1px solid #e5e7eb; border-radius:10px; outline:none; font-size:14px; background:#f9fafb;">
                            <option value="">-- Choose Teacher --</option>
                            <?php foreach($staffList as $staff): ?>
                                <option value="<?= $staff['id'] ?>"><?= htmlspecialchars($staff['full_name']) ?> (<?= htmlspecialchars($staff['role']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                        <small style="display:block; margin-top:6px; color:#6b7280; font-size:11px;">The primary teacher responsible for this class.</small>
                    </div>
                </div>

                <div style="display:flex; justify-content:flex-end; gap:16px; padding-top:25px; border-top:1px solid #f3f4f6;">
                    <button type="submit" class="btn-primary" style="padding:15px 45px; border-radius:12px; font-weight:800; letter-spacing:1px; background:linear-gradient(135deg, var(--primary), #4f46e5);">
                        <i class="ph ph-rocket-launch" style="margin-right:8px;"></i> INITIALIZE CLASS
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Alignment Instruction for User -->
    <div style="max-width: 700px; margin: 0 auto; background: #e0f2fe; border: 1px solid #bae6fd; border-radius:12px; padding:20px; display:flex; gap:15px; align-items:flex-start;">
        <i class="ph ph-info" style="font-size:24px; color:#0369a1;"></i>
        <div>
            <h4 style="margin:0 0 5px 0; color:#0c4a6e; font-size:14px; font-weight:800;">Infrastructure Alignment Note</h4>
            <p style="margin:0; font-size:13px; color:#075985; line-height:1.5;">This configuration allows your school to set up individual class-arm pairs. For example, you can create <strong>JSS 1 (Arm A)</strong> and <strong>JSS 1 (Arm B)</strong> separately. Other schools with only one arm can simply create <strong>JSS 1</strong> and leave the Arm field empty.</p>
        </div>
    </div>
</div>

<?php require ROOT_PATH . '/app/Views/school_admin/layout/footer.php'; ?>
