<?php
// Staff Recruitment Logic
// TENANT INTEGRITY GUARD — Must be logged in as school_admin with a valid school_id
require_once ROOT_PATH . '/config/database.php'; // sets $pdo, $supervisorPdo, $instituteId
if (empty($_SESSION['school_id']) || $_SESSION['role'] !== 'school_admin') {
    header('Location: ' . WEB_ROOT . '/');
    exit;
}
$schoolId = (int) $_SESSION['school_id']; // Immutable tenant scope

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name       = trim($_POST['full_name'] ?? '');
    $email           = trim($_POST['email'] ?? '');
    $phone           = trim($_POST['phone'] ?? '');
    $staff_no        = trim($_POST['staff_no'] ?? '');
    $dept_id         = $_POST['dept_id'] ?? 0;
    $designation_id  = $_POST['designation_id'] ?? 0;
    $salary          = $_POST['salary'] ?? 0;
    $hire_date       = $_POST['hire_date'] ?? date('Y-m-d');

    // --- Portal Credentials ---
    $username        = trim($_POST['username'] ?? '');
    $user_role       = trim($_POST['user_role'] ?? 'employee');
    $plain_password  = $_POST['login_password'] ?? '';
    $confirm_pass    = $_POST['confirm_password'] ?? '';

    // Default username = email prefix if not provided
    if (empty($username)) {
        $username = strtolower(explode('@', $email)[0]) . rand(10, 99);
    }

    // Validate password
    if (strlen($plain_password) < 6) {
        $error = "Password must be at least 6 characters.";
    } elseif ($plain_password !== $confirm_pass) {
        $error = "Passwords do not match. Please re-enter.";
    }

    if (!$error) {
        try {
            $pdo->beginTransaction();

            $photo_url = null;
            if (!empty($_FILES['passport']['name'])) {
                $uploadDir = ROOT_PATH . '/public/uploads/staff/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
                $fileName = 'staff_' . time() . '_' . rand(100,999) . '.' . pathinfo($_FILES['passport']['name'], PATHINFO_EXTENSION);
                if (move_uploaded_file($_FILES['passport']['tmp_name'], $uploadDir . $fileName)) {
                    $photo_url = '/uploads/staff/' . $fileName;
                }
            }

            // 1. Create User Account in SUPERVISOR DB (for global login routing)
            $hashed_pass = password_hash($plain_password, PASSWORD_DEFAULT);
            $supStmt = $supervisorPdo->prepare(
                "INSERT INTO users (full_name, email, username, phone, role, tenant_id, password, photo_url)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $supStmt->execute([$full_name, $email, $username, $phone, $user_role, $schoolId, $hashed_pass, $photo_url]);
            $uid = $supervisorPdo->lastInsertId();

            // 2. Mirror in TENANT DB for local joins
            $stmt = $pdo->prepare(
                "INSERT INTO users (id, full_name, email, username, password, role, phone, photo_url)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([$uid, $full_name, $email, $username, $hashed_pass, $user_role, $phone, $photo_url]);

            // 3. Create employee record in tenant DB
            $qr_token = bin2hex(random_bytes(16));
            $stmt = $pdo->prepare(
                "INSERT INTO employees (user_id, employee_number, department_id, designation_id, salary, hire_date, qr_token)
                 VALUES (?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([$uid, $staff_no, $dept_id, $designation_id, $salary, $hire_date, $qr_token]);
            $employee_id = $pdo->lastInsertId();

            // 4. Link to Institute with full employee profile
            $stmt = $pdo->prepare(
                "INSERT INTO institute_employees
                 (employee_id, user_id, employee_no, institute_id, role, gender, dob, religion,
                  blood_group, address, phone, department, designation, salary, hire_date, status, created_at)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'Active',NOW())"
            );
            $gender     = trim($_POST['gender'] ?? 'Male');
            $dob        = trim($_POST['dob'] ?? '') ?: null;
            $religion   = trim($_POST['religion'] ?? '');
            $blood_grp  = trim($_POST['blood_group'] ?? '');
            $address    = trim($_POST['address'] ?? '');
            $dept_name  = trim($_POST['dept_name'] ?? '');
            $desig_name = trim($_POST['designation_name'] ?? '');
            $stmt->execute([$uid, $uid, $staff_no, $schoolId, $user_role,
                            $gender, $dob, $religion, $blood_grp,
                            $address, $phone, $dept_name, $desig_name,
                            $salary, $hire_date]);

            $pdo->commit();

            $portalUrl = 'http://' . $_SERVER['HTTP_HOST'] . WEB_ROOT . '/';
            $message = "success";
            $_SESSION['emp_created'] = [
                'name'       => $full_name,
                'email'      => $email,
                'username'   => $username,
                'password'   => $plain_password,
                'portal_url' => $portalUrl,
                'letter_id'  => $employee_id,
            ];
            header('Location: ' . WEB_ROOT . '/school-admin/employees/add?recruited=1');
            exit;

        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = "Recruitment failed: " . $e->getMessage();
        }
    }
}

// Show success from redirect
$recruited = false;
$createdEmp = null;
if (isset($_GET['recruited']) && isset($_SESSION['emp_created'])) {
    $recruited = true;
    $createdEmp = $_SESSION['emp_created'];
    unset($_SESSION['emp_created']);
}

// Fetch Departments and Designations
$depts        = $pdo->query("SELECT * FROM departments ORDER BY dept_name ASC")->fetchAll(PDO::FETCH_ASSOC);
$designations = $pdo->query("SELECT * FROM designations ORDER BY designation_name ASC")->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Recruit Staff Member - Rosmon SMS';
require ROOT_PATH . '/app/Views/school_admin/layout/header.php';
require ROOT_PATH . '/app/Views/school_admin/layout/sidebar.php';
?>

<div class="main-container">
    <div class="top-header">
        <div class="greeting">HR / <span style="color:var(--primary)">Recruitment Management</span></div>
        <div class="header-actions">
            <a href="<?= WEB_ROOT ?>/school-admin/employees/all" class="btn-primary" style="background:#f3f4f6; color:var(--text-dark); text-decoration:none;"><i class="ph ph-arrow-left"></i> Back to Staff Directory</a>
        </div>
    </div>

    <div class="crud-card" style="max-width: 900px; margin: 30px auto;">
        <div class="crud-header">
            <h2 class="crud-title">Individual Staff Onboarding Form</h2>
        </div>

        <?php if ($recruited && $createdEmp): ?>
            <!-- ✅ SUCCESS PANEL WITH CREDENTIALS -->
            <div style="background: linear-gradient(135deg, #d1fae5, #ecfdf5); border: 1px solid #6ee7b7; border-left: 5px solid #10b981; border-radius: 12px; padding: 24px; margin-bottom: 28px;">
                <div style="display:flex; align-items:center; gap:12px; margin-bottom:16px;">
                    <div style="width:44px;height:44px;background:#10b981;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <i class="ph ph-check-circle" style="color:white;font-size:22px;"></i>
                    </div>
                    <div>
                        <div style="font-size:16px;font-weight:800;color:#065f46;">Staff Successfully Recruited!</div>
                        <div style="font-size:13px;color:#047857;margin-top:2px;"><?= htmlspecialchars($createdEmp['name']) ?> has been added. Share the credentials below.</div>
                    </div>
                </div>

                <!-- Credentials Box -->
                <div style="background:white; border:1px solid #a7f3d0; border-radius:10px; padding:20px; margin-top:8px;">
                    <div style="font-size:12px;font-weight:800;color:#065f46;text-transform:uppercase;letter-spacing:1px;margin-bottom:14px;">
                        🔐 Portal Login Credentials
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                        <div>
                            <div style="font-size:11px;color:#6b7280;font-weight:600;margin-bottom:4px;">PORTAL URL</div>
                            <div style="font-size:13px;font-weight:700;color:#1e293b;background:#f8fafc;padding:8px 12px;border-radius:6px;border:1px solid #e2e8f0;"><?= htmlspecialchars($createdEmp['portal_url']) ?></div>
                        </div>
                        <div>
                            <div style="font-size:11px;color:#6b7280;font-weight:600;margin-bottom:4px;">LOGIN EMAIL</div>
                            <div style="font-size:13px;font-weight:700;color:#1e293b;background:#f8fafc;padding:8px 12px;border-radius:6px;border:1px solid #e2e8f0;"><?= htmlspecialchars($createdEmp['email']) ?></div>
                        </div>
                        <div>
                            <div style="font-size:11px;color:#6b7280;font-weight:600;margin-bottom:4px;">USERNAME</div>
                            <div style="font-size:13px;font-weight:700;color:#4f46e5;background:#eef2ff;padding:8px 12px;border-radius:6px;border:1px solid #e0e7ff;"><?= htmlspecialchars($createdEmp['username']) ?></div>
                        </div>
                        <div>
                            <div style="font-size:11px;color:#6b7280;font-weight:600;margin-bottom:4px;">PASSWORD</div>
                            <div style="font-size:13px;font-weight:700;color:#b45309;background:#fef3c7;padding:8px 12px;border-radius:6px;border:1px solid #fde68a;letter-spacing:2px;"><?= htmlspecialchars($createdEmp['password']) ?></div>
                        </div>
                    </div>
                    <div style="margin-top:14px;padding:10px;background:#fef9c3;border-radius:8px;font-size:12px;color:#854d0e;font-weight:600;">
                        ⚠️ Please share this information securely with the staff member. They should change their password upon first login.
                    </div>
                </div>

                <div style="display:flex;gap:12px;margin-top:16px;">
                    <a href="<?= WEB_ROOT ?>/school-admin/employees/job-letter?id=<?= $createdEmp['letter_id'] ?>" class="btn-primary" style="background:#6366f1;text-decoration:none;padding:10px 20px;font-size:13px;">
                        <i class="ph ph-file-text"></i> View Employment Letter
                    </a>
                    <a href="<?= WEB_ROOT ?>/school-admin/employees/add" class="btn-primary" style="background:#0f172a;text-decoration:none;padding:10px 20px;font-size:13px;">
                        <i class="ph ph-user-plus"></i> Add Another Staff
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div style="background:#fee2e2; color:#991b1b; padding:14px 18px; border-radius:8px; margin-bottom:24px; font-weight:700; font-size:13px; border-left:4px solid #ef4444;">
                <i class="ph ph-warning-circle" style="vertical-align:middle;"></i> <?= $error ?>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <!-- ── PERSONAL INFORMATION ── -->
            <h3 style="font-size:13px;font-weight:800;margin-bottom:20px;color:var(--text-muted);padding-bottom:10px;border-bottom:2px solid #eef2ff;text-transform:uppercase;letter-spacing:1px;">
                Personal Information
            </h3>
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px; margin-bottom:28px;">
                <div>
                    <label style="display:block; font-size:13px; font-weight:600; margin-bottom:8px;">Full Name <span style="color:red;">*</span></label>
                    <input type="text" name="full_name" required placeholder="First Middle Surname"
                           style="width:100%; padding:10px 14px; border:1px solid var(--border); border-radius:8px; outline:none; font-size:14px; box-sizing:border-box;">
                </div>
                <div>
                    <label style="display:block; font-size:13px; font-weight:600; margin-bottom:8px;">Job Reference / Staff ID <span style="color:red;">*</span></label>
                    <input type="text" name="staff_no" required placeholder="EMP/2024/001"
                           style="width:100%; padding:10px 14px; border:1px solid var(--border); border-radius:8px; outline:none; font-size:14px; box-sizing:border-box;">
                </div>
                <div>
                    <label style="display:block; font-size:13px; font-weight:600; margin-bottom:8px;">Email Address <span style="color:red;">*</span></label>
                    <input type="email" name="email" id="email_input" required placeholder="staff@school.com"
                           oninput="suggestUsername(this.value)"
                           style="width:100%; padding:10px 14px; border:1px solid var(--border); border-radius:8px; outline:none; font-size:14px; box-sizing:border-box;">
                </div>
                <div>
                    <label style="display:block; font-size:13px; font-weight:600; margin-bottom:8px;">Contact Number</label>
                    <input type="tel" name="phone" placeholder="+234..."
                           style="width:100%; padding:10px 14px; border:1px solid var(--border); border-radius:8px; outline:none; font-size:14px; box-sizing:border-box;">
                </div>
                <div>
                    <label style="display:block; font-size:13px; font-weight:600; margin-bottom:8px;">Passport Photograph</label>
                    <input type="file" name="passport" accept="image/*" style="width:100%; font-size:12px;">
                </div>
            </div>

            <!-- ── EMPLOYMENT DETAILS ── -->
            <h3 style="font-size:13px;font-weight:800;margin-bottom:20px;color:var(--text-muted);padding-bottom:10px;border-bottom:2px solid #eef2ff;text-transform:uppercase;letter-spacing:1px;">
                Employment Details
            </h3>
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px; margin-bottom:28px;">
                <div>
                    <label style="display:block; font-size:13px; font-weight:600; margin-bottom:8px;">Department <span style="color:red;">*</span></label>
                    <select name="dept_id" required style="width:100%; padding:10px 14px; border:1px solid var(--border); border-radius:8px; font-size:14px;">
                        <option value="">-- Choose Department --</option>
                        <?php foreach($depts as $d): ?>
                            <option value="<?= $d['id'] ?>"><?= htmlspecialchars((string)($d['dept_name'] ?? '')) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label style="display:block; font-size:13px; font-weight:600; margin-bottom:8px;">Official Designation <span style="color:red;">*</span></label>
                    <select name="designation_id" required style="width:100%; padding:10px 14px; border:1px solid var(--border); border-radius:8px; font-size:14px;">
                        <option value="">-- Choose Designation --</option>
                        <?php foreach($designations as $ds): ?>
                            <option value="<?= $ds['id'] ?>"><?= htmlspecialchars((string)($ds['designation_name'] ?? '')) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label style="display:block; font-size:13px; font-weight:600; margin-bottom:8px;">Contract Salary (Monthly)</label>
                    <input type="number" name="salary" placeholder="0.00"
                           style="width:100%; padding:10px 14px; border:1px solid var(--border); border-radius:8px; outline:none; font-size:14px; box-sizing:border-box;">
                </div>
                <div>
                    <label style="display:block; font-size:13px; font-weight:600; margin-bottom:8px;">Commencement Date</label>
                    <input type="date" name="hire_date" value="<?= date('Y-m-d') ?>"
                           style="width:100%; padding:10px 14px; border:1px solid var(--border); border-radius:8px; font-size:14px; box-sizing:border-box;">
                </div>
            </div>

            <!-- ── PORTAL ACCESS CREDENTIALS ── -->
            <div style="background: linear-gradient(135deg, #f0f4ff, #e8eeff); border: 1px solid #c7d2fe; border-radius: 12px; padding: 24px; margin-bottom: 28px;">
                <div style="display:flex;align-items:center;gap:10px;margin-bottom:20px;">
                    <div style="width:36px;height:36px;background:#4f46e5;border-radius:8px;display:flex;align-items:center;justify-content:center;">
                        <i class="ph ph-lock-key" style="color:white;font-size:18px;"></i>
                    </div>
                    <div>
                        <div style="font-size:13px;font-weight:800;color:#1e1b4b;text-transform:uppercase;letter-spacing:1px;">Portal Access Credentials</div>
                        <div style="font-size:12px;color:#6366f1;font-weight:600;margin-top:2px;">Set the login username, password and system role for this member</div>
                    </div>
                </div>

                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px; margin-bottom: 20px;">
                    <div>
                        <label style="font-size: 13px; font-weight: 700; color: #1e1b4b; display: block; margin-bottom: 8px;">Institutional Role (Dashboard Access) <span style="color:red;">*</span></label>
                        <select name="user_role" required style="width:100%; padding:10px 14px; border:2px solid #c7d2fe; border-radius:8px; font-size:14px; background:white; font-weight:600;">
                            <option value="employee">Staff / Teacher Dashboard</option>
                            <option value="finance">Finance Dashboard</option>
                            <option value="principal">Principal Dashboard</option>
                            <option value="vice_principal">Vice Principal Dashboard</option>
                            <option value="audit">Audit Dashboard</option>
                            <option value="pta_chairman">PTA Chairman Dashboard</option>
                        </select>
                    </div>
                    <div>
                        <label style="display:block; font-size:13px; font-weight:700; margin-bottom:8px; color:#1e1b4b;">
                            Username <span style="color:red;">*</span>
                            <span style="font-weight:500; color:#6366f1; font-size:11px;">(auto-filled from email)</span>
                        </label>
                        <input type="text" name="username" id="username_field" required placeholder="e.g. john.doe"
                               style="width:100%; padding:10px 14px; border:2px solid #c7d2fe; border-radius:8px; outline:none; font-size:14px; background:white; box-sizing:border-box; font-weight:600;">
                    </div>
                </div>

                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px;">
                    <div>
                        <label style="display:block; font-size:13px; font-weight:700; margin-bottom:8px; color:#1e1b4b;">Password <span style="color:red;">*</span></label>
                        <div style="position:relative;">
                            <input type="password" name="login_password" id="pwd_field" required minlength="6" placeholder="Min. 6 characters"
                                   style="width:100%; padding:10px 40px 10px 14px; border:2px solid #c7d2fe; border-radius:8px; outline:none; font-size:14px; background:white; box-sizing:border-box;">
                            <span onclick="togglePwd('pwd_field',this)" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);cursor:pointer;color:#6366f1;">
                                <i class="ph ph-eye"></i>
                            </span>
                        </div>
                    </div>
                    <div>
                        <label style="display:block; font-size:13px; font-weight:700; margin-bottom:8px; color:#1e1b4b;">Confirm Password <span style="color:red;">*</span></label>
                        <div style="position:relative;">
                            <input type="password" name="confirm_password" id="cpwd_field" required minlength="6" placeholder="Re-enter password"
                                   style="width:100%; padding:10px 40px 10px 14px; border:2px solid #c7d2fe; border-radius:8px; outline:none; font-size:14px; background:white; box-sizing:border-box;">
                            <span onclick="togglePwd('cpwd_field',this)" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);cursor:pointer;color:#6366f1;">
                                <i class="ph ph-eye"></i>
                            </span>
                        </div>
                    </div>
                </div>

                <div style="margin-top:14px;background:#dbeafe;border-radius:8px;padding:10px 14px;font-size:12px;color:#1e40af;font-weight:600;">
                    <i class="ph ph-info" style="vertical-align:middle;"></i>
                    The staff member will log in at the school portal using their <strong>email or username</strong> and the dashboard corresponding to their assigned role.
                </div>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:16px; padding-top:20px; border-top:1px solid #eee;">
                <button type="submit" class="btn-primary" style="padding:12px 32px; border-radius:12px; font-weight:800; background:linear-gradient(135deg, var(--primary), #4f46e5); font-size:15px;">
                    <i class="ph ph-user-plus"></i> Finalize Recruitment
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function suggestUsername(email) {
    const field = document.getElementById('username_field');
    // Only auto-fill if the admin hasn't manually typed in the field
    if (!field.dataset.manuallyEdited) {
        const prefix = email.split('@')[0].toLowerCase().replace(/[^a-z0-9._]/g, '');
        field.value = prefix;
    }
}

document.getElementById('username_field').addEventListener('input', function() {
    this.dataset.manuallyEdited = 'true';
});

function togglePwd(fieldId, icon) {
    const f = document.getElementById(fieldId);
    if (f.type === 'password') {
        f.type = 'text';
        icon.innerHTML = '<i class="ph ph-eye-slash"></i>';
    } else {
        f.type = 'password';
        icon.innerHTML = '<i class="ph ph-eye"></i>';
    }
}
</script>

<?php require ROOT_PATH . '/app/Views/school_admin/layout/footer.php'; ?>
