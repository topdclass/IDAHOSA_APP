<?php
/**
 * Student Admission Form — with custom login credentials
 * Tenant-scoped: only creates data within $_SESSION['school_id'] tenant DB
 */
require_once ROOT_PATH . '/config/database.php';

// TENANT INTEGRITY GUARD
if (empty($_SESSION['school_id']) || $_SESSION['role'] !== 'school_admin') {
    header('Location: ' . WEB_ROOT . '/'); exit;
}
$schoolId = (int) $_SESSION['school_id'];
$message = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name    = trim($_POST['full_name'] ?? '');
    $email        = trim($_POST['email'] ?? '');
    $phone        = trim($_POST['phone'] ?? '');
    $admission_no = trim($_POST['admission_no'] ?? '');
    $class_id     = (int)($_POST['class_id'] ?? 0);
    $username     = trim($_POST['username'] ?? '');
    $plain_pass   = $_POST['login_password'] ?? '';
    $confirm_pass = $_POST['confirm_password'] ?? '';

    // Auto-generate username if blank
    if (empty($username) && !empty($email)) {
        $username = strtolower(explode('@', $email)[0]) . rand(10, 99);
    }

    if (strlen($plain_pass) < 6) {
        $error = "Password must be at least 6 characters.";
    } elseif ($plain_pass !== $confirm_pass) {
        $error = "Passwords do not match. Please try again.";
    }

    if (!$error) {
        try {
            $pdo->beginTransaction();
            $hashed = password_hash($plain_pass, PASSWORD_DEFAULT);

            // 1. Supervisor DB — global login account
            $supStmt = $supervisorPdo->prepare(
                "INSERT INTO users (full_name, email, username, phone, role, tenant_id, password)
                 VALUES (?, ?, ?, ?, 'student', ?, ?)"
            );
            $supStmt->execute([$full_name, $email, $username, $phone, $schoolId, $hashed]);
            $uid = $supervisorPdo->lastInsertId();

            // 2. Tenant DB mirror
            $stmt = $pdo->prepare(
                "INSERT INTO users (id, full_name, email, username, password, role, phone) 
                 VALUES (?, ?, ?, ?, ?, 'student', ?)"
            );
            $stmt->execute([$uid, $full_name, $email, $username, $hashed, $phone]);

            // 3. Student Record
            $stmt = $pdo->prepare(
                "INSERT INTO institute_students (student_id, student_no, class_id, institute_id, created_at)
                 VALUES (?, ?, ?, ?, NOW())"
            );
            $stmt->execute([$uid, $admission_no, $class_id, $schoolId]);

            $pdo->commit();

            $portalUrl = 'http://' . $_SERVER['HTTP_HOST'] . WEB_ROOT . '/';
            $_SESSION['stu_created'] = [
                'name'       => $full_name,
                'email'      => $email,
                'username'   => $username,
                'password'   => $plain_pass,
                'portal_url' => $portalUrl,
            ];
            header('Location: ' . WEB_ROOT . '/school-admin/students/add?enrolled=1');
            exit;

        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = "Enrollment failed: " . $e->getMessage();
        }
    }
}

$enrolled    = false;
$createdStu  = null;
if (isset($_GET['enrolled']) && isset($_SESSION['stu_created'])) {
    $enrolled   = true;
    $createdStu = $_SESSION['stu_created'];
    unset($_SESSION['stu_created']);
}

// Fetch Classes (with arms) for dropdown — tenant-specific
$classes = $pdo->query(
    "SELECT id, class_name, arm FROM classes WHERE is_deleted = 0 ORDER BY class_name ASC, arm ASC"
)->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Enroll New Student - Rosmon SMS';
require ROOT_PATH . '/app/Views/school_admin/layout/header.php';
require ROOT_PATH . '/app/Views/school_admin/layout/sidebar.php';
?>

<div class="main-container">
    <div class="top-header">
        <div class="greeting">Registrar / <span style="color:var(--primary)">Admission Management</span></div>
        <div class="header-actions">
            <a href="<?= WEB_ROOT ?>/school-admin/students/all" class="btn-primary" style="background:#f3f4f6;color:var(--text-dark);text-decoration:none;"><i class="ph ph-arrow-left"></i> All Students</a>
        </div>
    </div>

    <div class="crud-card" style="max-width:860px;margin:30px auto;">
        <div class="crud-header">
            <h2 class="crud-title">New Student Enrollment Form</h2>
        </div>

        <?php if ($enrolled && $createdStu): ?>
        <div style="background:linear-gradient(135deg,#d1fae5,#ecfdf5);border:1px solid #6ee7b7;border-left:5px solid #10b981;border-radius:12px;padding:24px;margin-bottom:28px;">
            <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px;">
                <div style="width:44px;height:44px;background:#10b981;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <i class="ph ph-check-circle" style="color:white;font-size:22px;"></i>
                </div>
                <div>
                    <div style="font-size:16px;font-weight:800;color:#065f46;">Student Successfully Enrolled!</div>
                    <div style="font-size:13px;color:#047857;margin-top:2px;"><?= htmlspecialchars($createdStu['name']) ?> has been admitted. Share portal credentials below.</div>
                </div>
            </div>
            <div style="background:white;border:1px solid #a7f3d0;border-radius:10px;padding:20px;">
                <div style="font-size:12px;font-weight:800;color:#065f46;text-transform:uppercase;letter-spacing:1px;margin-bottom:14px;">🔐 Student Portal Login Credentials</div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div>
                        <div style="font-size:11px;color:#6b7280;font-weight:600;margin-bottom:4px;">PORTAL URL</div>
                        <div style="font-size:13px;font-weight:700;background:#f8fafc;padding:8px 12px;border-radius:6px;border:1px solid #e2e8f0;"><?= htmlspecialchars($createdStu['portal_url']) ?></div>
                    </div>
                    <div>
                        <div style="font-size:11px;color:#6b7280;font-weight:600;margin-bottom:4px;">LOGIN EMAIL</div>
                        <div style="font-size:13px;font-weight:700;background:#f8fafc;padding:8px 12px;border-radius:6px;border:1px solid #e2e8f0;"><?= htmlspecialchars($createdStu['email']) ?></div>
                    </div>
                    <div>
                        <div style="font-size:11px;color:#6b7280;font-weight:600;margin-bottom:4px;">USERNAME</div>
                        <div style="font-size:13px;font-weight:700;color:#4f46e5;background:#eef2ff;padding:8px 12px;border-radius:6px;border:1px solid #e0e7ff;"><?= htmlspecialchars($createdStu['username']) ?></div>
                    </div>
                    <div>
                        <div style="font-size:11px;color:#6b7280;font-weight:600;margin-bottom:4px;">PASSWORD</div>
                        <div style="font-size:13px;font-weight:700;color:#b45309;background:#fef3c7;padding:8px 12px;border-radius:6px;border:1px solid #fde68a;letter-spacing:2px;"><?= htmlspecialchars($createdStu['password']) ?></div>
                    </div>
                </div>
                <div style="margin-top:12px;padding:10px;background:#fef9c3;border-radius:8px;font-size:12px;color:#854d0e;font-weight:600;">
                    ⚠️ Share this with the student/guardian securely. Advise them to change the password after first login.
                </div>
            </div>
            <div style="display:flex;gap:12px;margin-top:16px;">
                <a href="<?= WEB_ROOT ?>/school-admin/students/add" class="btn-primary" style="background:#0f172a;text-decoration:none;padding:10px 20px;font-size:13px;"><i class="ph ph-user-plus"></i> Enroll Another Student</a>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div style="background:#fee2e2;color:#991b1b;padding:14px 18px;border-radius:8px;margin-bottom:24px;font-weight:700;font-size:13px;border-left:4px solid #ef4444;">
            <i class="ph ph-warning-circle"></i> <?= $error ?>
        </div>
        <?php endif; ?>

        <form method="POST">
            <!-- Personal Info -->
            <h3 style="font-size:13px;font-weight:800;margin-bottom:20px;color:var(--text-muted);padding-bottom:10px;border-bottom:2px solid #eef2ff;text-transform:uppercase;letter-spacing:1px;">Personal Information</h3>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:28px;">
                <div>
                    <label style="display:block;font-size:13px;font-weight:600;margin-bottom:8px;">Student Full Name <span style="color:red;">*</span></label>
                    <input type="text" name="full_name" id="sname" required placeholder="First Middle Surname" oninput="suggestUsername()"
                           style="width:100%;padding:10px 14px;border:1px solid var(--border);border-radius:8px;outline:none;font-size:14px;box-sizing:border-box;">
                </div>
                <div>
                    <label style="display:block;font-size:13px;font-weight:600;margin-bottom:8px;">Admission Number <span style="color:red;">*</span></label>
                    <input type="text" name="admission_no" required placeholder="e.g. RIS-2025-001"
                           style="width:100%;padding:10px 14px;border:1px solid var(--border);border-radius:8px;outline:none;font-size:14px;box-sizing:border-box;">
                </div>
                <div>
                    <label style="display:block;font-size:13px;font-weight:600;margin-bottom:8px;">Date of Birth</label>
                    <input type="date" name="dob"
                           style="width:100%;padding:10px 14px;border:1px solid var(--border);border-radius:8px;outline:none;font-size:14px;box-sizing:border-box;">
                </div>
                <div>
                    <label style="display:block;font-size:13px;font-weight:600;margin-bottom:8px;">Assign Class <span style="color:red;">*</span></label>
                    <select name="class_id" required style="width:100%;padding:10px 14px;border:1px solid var(--border);border-radius:8px;font-size:14px;">
                        <option value="">-- Choose Class --</option>
                        <?php foreach($classes as $c): ?>
                            <option value="<?= $c['id'] ?>">
                                <?= htmlspecialchars($c['class_name']) ?>
                                <?= !empty($c['arm']) ? ' — Arm ' . htmlspecialchars($c['arm']) : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label style="display:block;font-size:13px;font-weight:600;margin-bottom:8px;">Student/Guardian Email <span style="color:red;">*</span></label>
                    <input type="email" name="email" id="semail" required placeholder="student@school.com"
                           oninput="suggestUsername()"
                           style="width:100%;padding:10px 14px;border:1px solid var(--border);border-radius:8px;outline:none;font-size:14px;box-sizing:border-box;">
                </div>
                <div>
                    <label style="display:block;font-size:13px;font-weight:600;margin-bottom:8px;">Guardian Phone</label>
                    <input type="tel" name="phone" placeholder="+234..."
                           style="width:100%;padding:10px 14px;border:1px solid var(--border);border-radius:8px;outline:none;font-size:14px;box-sizing:border-box;">
                </div>
            </div>

            <!-- Portal Credentials -->
            <div style="background:linear-gradient(135deg,#f0f4ff,#e8eeff);border:1px solid #c7d2fe;border-radius:12px;padding:24px;margin-bottom:28px;">
                <div style="display:flex;align-items:center;gap:10px;margin-bottom:20px;">
                    <div style="width:36px;height:36px;background:#4f46e5;border-radius:8px;display:flex;align-items:center;justify-content:center;">
                        <i class="ph ph-lock-key" style="color:white;font-size:18px;"></i>
                    </div>
                    <div>
                        <div style="font-size:13px;font-weight:800;color:#1e1b4b;text-transform:uppercase;letter-spacing:1px;">Student Portal Access</div>
                        <div style="font-size:12px;color:#6366f1;font-weight:600;margin-top:2px;">Set the student's login credentials for their dashboard</div>
                    </div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;">
                    <div>
                        <label style="display:block;font-size:13px;font-weight:700;margin-bottom:8px;color:#1e1b4b;">Username <span style="color:red;">*</span> <span style="font-weight:500;color:#6366f1;font-size:11px;">(auto-filled)</span></label>
                        <input type="text" name="username" id="uname_field" required placeholder="e.g. john.doe"
                               style="width:100%;padding:10px 14px;border:2px solid #c7d2fe;border-radius:8px;outline:none;font-size:14px;background:white;box-sizing:border-box;font-weight:600;">
                    </div>
                    <div>
                        <label style="display:block;font-size:13px;font-weight:700;margin-bottom:8px;color:#1e1b4b;">Password <span style="color:red;">*</span></label>
                        <div style="position:relative;">
                            <input type="password" name="login_password" id="spwd" required minlength="6" placeholder="Min. 6 characters"
                                   style="width:100%;padding:10px 40px 10px 14px;border:2px solid #c7d2fe;border-radius:8px;outline:none;font-size:14px;background:white;box-sizing:border-box;">
                            <span onclick="togglePwd('spwd',this)" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);cursor:pointer;color:#6366f1;"><i class="ph ph-eye"></i></span>
                        </div>
                    </div>
                    <div>
                        <label style="display:block;font-size:13px;font-weight:700;margin-bottom:8px;color:#1e1b4b;">Confirm Password <span style="color:red;">*</span></label>
                        <div style="position:relative;">
                            <input type="password" name="confirm_password" id="scpwd" required minlength="6" placeholder="Re-enter password"
                                   style="width:100%;padding:10px 40px 10px 14px;border:2px solid #c7d2fe;border-radius:8px;outline:none;font-size:14px;background:white;box-sizing:border-box;">
                            <span onclick="togglePwd('scpwd',this)" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);cursor:pointer;color:#6366f1;"><i class="ph ph-eye"></i></span>
                        </div>
                    </div>
                </div>
                <div style="margin-top:14px;background:#dbeafe;border-radius:8px;padding:10px 14px;font-size:12px;color:#1e40af;font-weight:600;">
                    <i class="ph ph-info" style="vertical-align:middle;"></i>
                    The student will log in using their <strong>email or username</strong> and the password you set. Advise them to change it after first login.
                </div>
            </div>

            <div style="display:flex;justify-content:flex-end;gap:16px;padding-top:20px;border-top:1px solid #eee;">
                <a href="<?= WEB_ROOT ?>/school-admin/students/all" class="btn-primary" style="background:#f3f4f6;color:var(--text-dark);text-decoration:none;">Discard</a>
                <button type="submit" class="btn-primary" style="padding:12px 32px;font-weight:800;background:linear-gradient(135deg,var(--primary),#4f46e5);">
                    <i class="ph ph-user-plus"></i> Finalize Admission
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function suggestUsername() {
    const f = document.getElementById('uname_field');
    if (f.dataset.manuallyEdited) return;
    const email = document.getElementById('semail').value;
    const name  = document.getElementById('sname').value;
    if (email.includes('@')) {
        f.value = email.split('@')[0].toLowerCase().replace(/[^a-z0-9._]/g,'');
    } else if (name.trim()) {
        f.value = name.trim().split(' ')[0].toLowerCase() + Math.floor(Math.random()*90+10);
    }
}
document.getElementById('uname_field').addEventListener('input', function() { this.dataset.manuallyEdited = '1'; });
function togglePwd(id, el) {
    const f = document.getElementById(id);
    f.type = f.type === 'password' ? 'text' : 'password';
    el.innerHTML = f.type === 'password' ? '<i class="ph ph-eye"></i>' : '<i class="ph ph-eye-slash"></i>';
}
</script>

<?php require ROOT_PATH . '/app/Views/school_admin/layout/footer.php'; ?>
