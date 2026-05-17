<?php
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
    $phone        = trim($_POST['phone_number'] ?? '');
    $address      = trim($_POST['address'] ?? '');
    $ward_ids     = $_POST['ward_ids'] ?? [];
    $username     = trim($_POST['username'] ?? '');
    $plain_pass   = $_POST['login_password'] ?? '';
    $confirm_pass = $_POST['confirm_password'] ?? '';

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

            // 1. Supervisor DB
            $supStmt = $supervisorPdo->prepare(
                "INSERT INTO users (full_name, email, username, phone, role, tenant_id, password)
                 VALUES (?, ?, ?, ?, 'parent', ?, ?)"
            );
            $supStmt->execute([$full_name, $email, $username, $phone, $schoolId, $hashed]);
            $uid = $supervisorPdo->lastInsertId();

            // 2. Tenant DB mirror
            $stmt = $pdo->prepare(
                "INSERT INTO users (id, full_name, email, username, password, phone, role) VALUES (?, ?, ?, ?, ?, ?, 'parent')"
            );
            $stmt->execute([$uid, $full_name, $email, $username, $hashed, $phone]);

            // 3. Family logic
            $family_id = null;
            if (!empty($ward_ids)) {
                $family_no = "FAM-" . date('Y') . "-" . rand(1000, 9999);
                $fStmt = $pdo->prepare("INSERT INTO institute_families (family_no, family_name, institute_id, created_at) VALUES (?, ?, ?, NOW())");
                $fStmt->execute([$family_no, $full_name . " Family", $schoolId]);
                $family_id = $pdo->lastInsertId();
                $updateSt  = $pdo->prepare("UPDATE institute_students SET family_id = ? WHERE id = ?");
                foreach ($ward_ids as $sid) {
                    $updateSt->execute([$family_id, (int)$sid]);
                }
            }

            // 4. Parent record
            $parent_no = "PRN-" . date('Y') . "-" . rand(1000, 9999);
            $stmt = $pdo->prepare(
                "INSERT INTO institute_parents (parent_id, institute_id, address, family_id, parent_no, created_at)
                 VALUES (?, ?, ?, ?, ?, NOW())"
            );
            $stmt->execute([$uid, $schoolId, $address, $family_id, $parent_no]);
            $pdo->commit();

            $portalUrl = 'http://' . $_SERVER['HTTP_HOST'] . WEB_ROOT . '/';
            $_SESSION['par_created'] = [
                'name'       => $full_name,
                'email'      => $email,
                'username'   => $username,
                'password'   => $plain_pass,
                'portal_url' => $portalUrl,
            ];
            header('Location: ' . WEB_ROOT . '/school-admin/parents/add?registered=1');
            exit;

        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = "Registration failed: " . $e->getMessage();
        }
    }
}

$registered  = false;
$createdPar  = null;
if (isset($_GET['registered']) && isset($_SESSION['par_created'])) {
    $registered = true;
    $createdPar = $_SESSION['par_created'];
    unset($_SESSION['par_created']);
}

// Fetch students for ward selection (tenant-scoped)
$students = $pdo->query(
    "SELECT s.id, u.full_name, c.class_name, c.arm
     FROM institute_students s
     JOIN users u ON s.student_id = u.id
     LEFT JOIN classes c ON s.class_id = c.id
     WHERE s.is_deleted = 0 ORDER BY c.class_name, c.arm, u.full_name"
)->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Register New Parent - Rosmon SMS';
require ROOT_PATH . '/app/Views/school_admin/layout/header.php';
require ROOT_PATH . '/app/Views/school_admin/layout/sidebar.php';
?>

<div class="main-container">
    <div class="top-header">
        <div class="greeting">Parents / <span style="color:var(--primary)">Enroll New Parent</span></div>
    </div>

    <div class="crud-card" style="max-width:900px;margin:30px auto;">
        <div class="crud-header"><h2 class="crud-title">Parental Enrollment Form</h2></div>

        <?php if ($registered && $createdPar): ?>
        <div style="background:linear-gradient(135deg,#d1fae5,#ecfdf5);border:1px solid #6ee7b7;border-left:5px solid #10b981;border-radius:12px;padding:24px;margin-bottom:28px;">
            <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px;">
                <div style="width:44px;height:44px;background:#10b981;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <i class="ph ph-check-circle" style="color:white;font-size:22px;"></i>
                </div>
                <div>
                    <div style="font-size:16px;font-weight:800;color:#065f46;">Parent Successfully Registered!</div>
                    <div style="font-size:13px;color:#047857;margin-top:2px;"><?= htmlspecialchars($createdPar['name']) ?> can now log into the parent portal.</div>
                </div>
            </div>
            <div style="background:white;border:1px solid #a7f3d0;border-radius:10px;padding:20px;">
                <div style="font-size:12px;font-weight:800;color:#065f46;text-transform:uppercase;letter-spacing:1px;margin-bottom:14px;">🔐 Parent Portal Login Credentials</div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div>
                        <div style="font-size:11px;color:#6b7280;font-weight:600;margin-bottom:4px;">PORTAL URL</div>
                        <div style="font-size:13px;font-weight:700;background:#f8fafc;padding:8px 12px;border-radius:6px;border:1px solid #e2e8f0;"><?= htmlspecialchars($createdPar['portal_url']) ?></div>
                    </div>
                    <div>
                        <div style="font-size:11px;color:#6b7280;font-weight:600;margin-bottom:4px;">LOGIN EMAIL</div>
                        <div style="font-size:13px;font-weight:700;background:#f8fafc;padding:8px 12px;border-radius:6px;border:1px solid #e2e8f0;"><?= htmlspecialchars($createdPar['email']) ?></div>
                    </div>
                    <div>
                        <div style="font-size:11px;color:#6b7280;font-weight:600;margin-bottom:4px;">USERNAME</div>
                        <div style="font-size:13px;font-weight:700;color:#4f46e5;background:#eef2ff;padding:8px 12px;border-radius:6px;border:1px solid #e0e7ff;"><?= htmlspecialchars($createdPar['username']) ?></div>
                    </div>
                    <div>
                        <div style="font-size:11px;color:#6b7280;font-weight:600;margin-bottom:4px;">PASSWORD</div>
                        <div style="font-size:13px;font-weight:700;color:#b45309;background:#fef3c7;padding:8px 12px;border-radius:6px;border:1px solid #fde68a;letter-spacing:2px;"><?= htmlspecialchars($createdPar['password']) ?></div>
                    </div>
                </div>
                <div style="margin-top:12px;padding:10px;background:#fef9c3;border-radius:8px;font-size:12px;color:#854d0e;font-weight:600;">
                    ⚠️ Share this securely with the parent. Advise them to change the password after first login.
                </div>
            </div>
            <div style="margin-top:16px;display:flex;gap:12px;">
                <a href="<?= WEB_ROOT ?>/school-admin/parents/add" class="btn-primary" style="background:#0f172a;text-decoration:none;padding:10px 20px;font-size:13px;"><i class="ph ph-user-plus"></i> Add Another Parent</a>
                <a href="<?= WEB_ROOT ?>/school-admin/parents/all" class="btn-primary" style="background:#6366f1;text-decoration:none;padding:10px 20px;font-size:13px;"><i class="ph ph-users"></i> View All Parents</a>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div style="background:#fee2e2;color:#991b1b;padding:14px 18px;border-radius:8px;margin-bottom:24px;font-weight:700;font-size:13px;border-left:4px solid #ef4444;">
            <i class="ph ph-warning-circle"></i> <?= $error ?>
        </div>
        <?php endif; ?>

        <form method="POST" style="padding:24px;">
            <!-- Personal Info -->
            <h3 style="font-size:13px;font-weight:800;margin-bottom:20px;color:var(--text-muted);padding-bottom:10px;border-bottom:2px solid #eef2ff;text-transform:uppercase;letter-spacing:1px;">Parent Information</h3>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:28px;">
                <div style="grid-column:span 2;">
                    <label style="display:block;font-size:13px;font-weight:600;margin-bottom:8px;">Full Legal Name <span style="color:red;">*</span></label>
                    <input type="text" name="full_name" required placeholder="e.g. John Doe"
                           style="width:100%;padding:10px 14px;border:1px solid var(--border);border-radius:8px;font-size:14px;outline:none;box-sizing:border-box;">
                </div>
                <div>
                    <label style="display:block;font-size:13px;font-weight:600;margin-bottom:8px;">Phone Number</label>
                    <input type="text" name="phone_number" placeholder="e.g. +234 812 345 6789"
                           style="width:100%;padding:10px 14px;border:1px solid var(--border);border-radius:8px;font-size:14px;outline:none;box-sizing:border-box;">
                </div>
                <div>
                    <label style="display:block;font-size:13px;font-weight:600;margin-bottom:8px;">Email Address <span style="color:red;">*</span></label>
                    <input type="email" name="email" id="pemail" required placeholder="e.g. parent@email.com"
                           oninput="suggestParentUsername(this.value)"
                           style="width:100%;padding:10px 14px;border:1px solid var(--border);border-radius:8px;font-size:14px;outline:none;box-sizing:border-box;">
                </div>
                <div style="grid-column:span 2;">
                    <label style="display:block;font-size:13px;font-weight:600;margin-bottom:8px;">Mailing Address</label>
                    <textarea name="address" rows="2" style="width:100%;padding:10px 14px;border:1px solid var(--border);border-radius:8px;font-size:13px;outline:none;box-sizing:border-box;"></textarea>
                </div>
            </div>

            <!-- Portal Credentials -->
            <div style="background:linear-gradient(135deg,#f0f4ff,#e8eeff);border:1px solid #c7d2fe;border-radius:12px;padding:24px;margin-bottom:28px;">
                <div style="display:flex;align-items:center;gap:10px;margin-bottom:20px;">
                    <div style="width:36px;height:36px;background:#4f46e5;border-radius:8px;display:flex;align-items:center;justify-content:center;">
                        <i class="ph ph-lock-key" style="color:white;font-size:18px;"></i>
                    </div>
                    <div>
                        <div style="font-size:13px;font-weight:800;color:#1e1b4b;text-transform:uppercase;letter-spacing:1px;">Parent Portal Access</div>
                        <div style="font-size:12px;color:#6366f1;font-weight:600;margin-top:2px;">Set login credentials for this parent's dashboard</div>
                    </div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;">
                    <div>
                        <label style="display:block;font-size:13px;font-weight:700;margin-bottom:8px;color:#1e1b4b;">Username <span style="color:red;">*</span></label>
                        <input type="text" name="username" id="puname_field" required placeholder="e.g. jane.doe"
                               style="width:100%;padding:10px 14px;border:2px solid #c7d2fe;border-radius:8px;outline:none;font-size:14px;background:white;box-sizing:border-box;font-weight:600;">
                    </div>
                    <div>
                        <label style="display:block;font-size:13px;font-weight:700;margin-bottom:8px;color:#1e1b4b;">Password <span style="color:red;">*</span></label>
                        <div style="position:relative;">
                            <input type="password" name="login_password" id="ppwd" required minlength="6" placeholder="Min. 6 characters"
                                   style="width:100%;padding:10px 40px 10px 14px;border:2px solid #c7d2fe;border-radius:8px;outline:none;font-size:14px;background:white;box-sizing:border-box;">
                            <span onclick="togglePwd('ppwd',this)" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);cursor:pointer;color:#6366f1;"><i class="ph ph-eye"></i></span>
                        </div>
                    </div>
                    <div>
                        <label style="display:block;font-size:13px;font-weight:700;margin-bottom:8px;color:#1e1b4b;">Confirm Password <span style="color:red;">*</span></label>
                        <div style="position:relative;">
                            <input type="password" name="confirm_password" id="pcpwd" required minlength="6" placeholder="Re-enter password"
                                   style="width:100%;padding:10px 40px 10px 14px;border:2px solid #c7d2fe;border-radius:8px;outline:none;font-size:14px;background:white;box-sizing:border-box;">
                            <span onclick="togglePwd('pcpwd',this)" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);cursor:pointer;color:#6366f1;"><i class="ph ph-eye"></i></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Wards -->
            <h3 style="font-size:13px;font-weight:800;margin-bottom:16px;color:var(--primary);text-transform:uppercase;letter-spacing:1px;">Select Wards / Students (Optional)</h3>
            <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:20px;max-height:280px;overflow-y:auto;margin-bottom:28px;display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                <?php if(empty($students)): ?>
                    <div style="grid-column:span 2;color:#64748b;font-style:italic;font-size:13px;">No active students found.</div>
                <?php else:
                    $lastClass = '';
                    foreach($students as $st):
                        $classLabel = $st['class_name'] . (!empty($st['arm']) ? ' — Arm ' . $st['arm'] : '');
                        if ($lastClass !== $classLabel):
                            $lastClass = $classLabel;
                ?>
                    <div style="grid-column:span 2;font-size:11px;font-weight:800;color:var(--primary);margin-top:10px;border-bottom:1px solid #e2e8f0;padding-bottom:5px;text-transform:uppercase;">CLASS: <?= htmlspecialchars($classLabel) ?></div>
                <?php endif; ?>
                    <label style="display:flex;align-items:center;gap:10px;cursor:pointer;background:white;padding:10px;border-radius:8px;border:1px solid #f1f5f9;">
                        <input type="checkbox" name="ward_ids[]" value="<?= $st['id'] ?>" style="width:18px;height:18px;cursor:pointer;accent-color:var(--primary);">
                        <span style="font-size:13px;font-weight:600;"><?= htmlspecialchars($st['full_name']) ?></span>
                    </label>
                <?php endforeach; endif; ?>
            </div>
            <small style="color:#64748b;margin-top:-20px;margin-bottom:20px;display:block;">Linking students will automatically create a <b>Family Record</b> for this parent.</small>

            <div style="display:flex;justify-content:flex-end;gap:16px;padding-top:20px;border-top:1px solid #f1f5f9;">
                <button type="submit" class="btn-primary" style="padding:14px 32px;border:none;border-radius:10px;cursor:pointer;font-weight:800;font-size:15px;background:linear-gradient(135deg,var(--primary),#4f46e5);">
                    <i class="ph ph-user-plus"></i> Register Parent &amp; Link Wards
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function suggestParentUsername(email) {
    const f = document.getElementById('puname_field');
    if (f.dataset.manuallyEdited) return;
    f.value = email.split('@')[0].toLowerCase().replace(/[^a-z0-9._]/g,'');
}
document.getElementById('puname_field').addEventListener('input', function(){ this.dataset.manuallyEdited='1'; });
function togglePwd(id, el) {
    const f = document.getElementById(id);
    f.type = f.type === 'password' ? 'text' : 'password';
    el.innerHTML = f.type === 'password' ? '<i class="ph ph-eye"></i>' : '<i class="ph ph-eye-slash"></i>';
}
</script>

<?php require ROOT_PATH . '/app/Views/school_admin/layout/footer.php'; ?>
