<?php
require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/config/database.php';
use app\Support\MailHelper;

$emp_id = $_GET['id'] ?? 'all';
$action = $_GET['action'] ?? 'view';

try {
    // 1. Fetch School Profile from SUPERVISOR DB
    $schoolStmt = $supervisorPdo->prepare("SELECT * FROM institution_profile WHERE id = ? LIMIT 1");
    $schoolStmt->execute([$instituteId]);
    $school = $schoolStmt->fetch(PDO::FETCH_ASSOC);

    if (empty($school)) die("School profile not found in supervisor.");

    // Fetch school login URL
    $schoolId = $_SESSION['school_id'];
    $loginUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . WEB_ROOT . '/login';

    // 2. Fetch Employee(s) Data
    if ($emp_id !== 'all' && $emp_id > 0) {
        $query = "SELECT e.*, u.full_name, u.email, u.phone, u.photo_url, d.designation_name, de.dept_name 
                  FROM employees e 
                  JOIN users u ON e.user_id = u.id 
                  LEFT JOIN designations d ON e.designation_id = d.id
                  LEFT JOIN departments de ON e.department_id = de.id
                  WHERE e.id = ? LIMIT 1";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$emp_id]);
        $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $query = "SELECT e.*, u.full_name, u.email, u.phone, u.photo_url, d.designation_name, de.dept_name 
                  FROM employees e 
                  JOIN users u ON e.user_id = u.id 
                  LEFT JOIN designations d ON e.designation_id = d.id
                  LEFT JOIN departments de ON e.department_id = de.id
                  WHERE e.status = 'Active'
                  ORDER BY u.full_name ASC";
        $employees = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);
    }

    if (empty($employees)) die("No employees found.");

    // Handle Email Action (Only for single employee)
    $emailSent = false;
    if ($action === 'email' && count($employees) === 1) {
        $emp = $employees[0];
        $subject = "EMPLOYMENT LETTER & LOGIN DETAILS - " . htmlspecialchars((string)$school['institution_name']);
        $message = "
        <p>Dear <b>" . htmlspecialchars((string)$emp['full_name']) . "</b>,</p>
        <p>We are pleased to offer you the position of <b>" . htmlspecialchars((string)$emp['designation_name']) . "</b> at <b>" . htmlspecialchars((string)$school['institution_name']) . "</b>.</p>
        <p>Your employment is scheduled to commence on <b>" . date('F j, Y', strtotime((string)$emp['hire_date'])) . "</b>. Your monthly salary will be <b>₦" . number_format((float)$emp['salary'], 2) . "</b>.</p>
        <hr>
        <p><b>Staff Portal Login Details:</b></p>
        <p>URL: <a href='{$loginUrl}'>{$loginUrl}</a><br>
        Email: <b>" . htmlspecialchars((string)$emp['email']) . "</b><br>
        Password: <b>123456</b> (Please change after first login)</p>
        <hr>
        <p>Welcome to the team!</p>
        ";
        $emailSent = MailHelper::send($emp['email'], $subject, $message, (string)$school['institution_name']);
    }

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

$pageTitle = (count($employees) === 1) ? 'Employment Letter - ' . $employees[0]['full_name'] : 'Bulk Employment Letters';
require APP_PATH . '/Views/school_admin/layout/header.php';
require APP_PATH . '/Views/school_admin/layout/sidebar.php';
?>

<style>
    .letter-paper {
        background: white;
        width: 100%;
        max-width: 800px;
        margin: 20px auto;
        padding: 40px 60px;
        box-shadow: 0 10px 40px rgba(0,0,0,0.05);
        border: 1px solid #eee;
        position: relative;
        font-family: 'Times New Roman', serif;
        color: #1e293b;
        line-height: 1.5;
        font-size: 15px;
    }

    .letter-header {
        text-align: center;
        border-bottom: 2px solid #1e293b;
        padding-bottom: 15px;
        margin-bottom: 25px;
    }

    .school-logo {
        height: 60px;
        margin-bottom: 10px;
    }

    .school-name {
        font-size: 20px;
        font-weight: 900;
        text-transform: uppercase;
        margin: 0;
    }

    .school-contact {
        font-size: 12px;
        color: #64748b;
        margin-top: 3px;
    }

    .letter-date {
        margin-bottom: 20px;
        font-weight: 700;
    }

    .recipient-info {
        margin-bottom: 30px;
    }

    .letter-subject {
        text-align: center;
        font-weight: 900;
        text-decoration: underline;
        text-transform: uppercase;
        margin: 20px 0;
        font-size: 16px;
    }

    .letter-body p {
        margin-bottom: 12px;
    }

    .signature-section {
        margin-top: 40px;
    }

    .sig-img {
        height: 55px;
        margin-bottom: 5px;
    }

    @media print {
        body { background: white; }
        body * { visibility: hidden; }
        .letter-paper, .letter-paper * { visibility: visible; }
        .letter-paper { 
            position: absolute; 
            left: 0; 
            top: 0; 
            margin: 0; 
            padding: 20mm 25mm !important; 
            box-shadow: none; 
            border: none; 
            width: 210mm;
            height: 297mm;
            overflow: hidden;
        }
        .action-bar { display: none !important; }
    }
</style>

<div class="main-container">
    <div class="top-header">
        <div class="greeting">HR / <span style="color:var(--primary)">Job Letter Automation</span></div>
        <div class="header-actions action-bar">
            <?php if ($action === 'email' && count($employees) === 1): ?>
                <?php if ($emailSent): ?>
                    <div style="background:#d1fae5; color:#065f46; padding:8px 16px; border-radius:8px; font-weight:700; margin-right:15px;">
                       <i class="ph ph-envelope-simple-check"></i> Letter Sent to <?= htmlspecialchars((string)$employees[0]['email']) ?>
                    </div>
                <?php else: ?>
                    <div style="background:#fee2e2; color:#991b1b; padding:8px 16px; border-radius:8px; font-weight:700; margin-right:15px;">
                       <i class="ph ph-warning-circle"></i> Mail Failed: Check SMTP settings in php.ini
                    </div>
                <?php endif; ?>
            <?php endif; ?>
            
            <?php if(count($employees) === 1): ?>
                <a href="?id=<?= $employees[0]['id'] ?>&action=email" class="btn-primary" style="background:#6366f1; text-decoration:none;"><i class="ph ph-paper-plane-tilt"></i> Send to Email</a>
            <?php endif; ?>
            
            <button onclick="window.print()" class="btn-primary"><i class="ph ph-printer"></i> <?= count($employees) > 1 ? 'Bulk Print All' : 'Print / Download PDF' ?></button>
        </div>
    </div>

    <?php foreach($employees as $emp): ?>
        <div class="letter-paper">
            <div class="letter-header">
                <?php if($school['logo_url']): ?>
                    <img src="<?= WEB_ROOT . $school['logo_url'] ?>" class="school-logo">
                <?php endif; ?>
                <h1 class="school-name"><?= htmlspecialchars((string)$school['institution_name']) ?></h1>
                <div class="school-contact">
                    <?= nl2br(htmlspecialchars((string)$school['address'])) ?><br>
                    Email: <?= htmlspecialchars((string)$school['contact_email']) ?> | Phone: <?= htmlspecialchars((string)$school['contact_phone']) ?>
                </div>
            </div>

            <div class="letter-date">
                Date: <?= date('F j, Y') ?>
            </div>

            <div class="recipient-info">
                <b><?= htmlspecialchars((string)$emp['full_name']) ?></b><br>
                <?= htmlspecialchars((string)$emp['dept_name']) ?> Department<br>
                <?= htmlspecialchars((string)$school['institution_name']) ?>
            </div>

            <div class="letter-subject">
                LETTER OF APPOINTMENT
            </div>

            <div class="letter-body">
                <p>Dear <?= explode(' ', (string)$emp['full_name'])[0] ?>,</p>
                
                <p>Following your successful interview and vetting process, we are pleased to offer you employment as a <b><?= htmlspecialchars((string)$emp['designation_name']) ?></b> at <b><?= htmlspecialchars((string)$school['institution_name']) ?></b>.</p>

                <p>Your appointment is effective from <b><?= date('F j, Y', strtotime((string)$emp['hire_date'])) ?></b>. In this role, you will report directly to the Head of Department or any other designated authority within the school management.</p>

                <p><b>Remuneration:</b> Your monthly consolidated salary is <b>₦<?= number_format((float)$emp['salary'], 2) ?></b>, subject to applicable statutory deductions. Salary reviews are conducted periodically based on performance and school policy.</p>

                <p><b>Professional Conduct:</b> We expect the highest level of professionalism, dedication, and integrity in the discharge of your duties. You are required to abide by the school's code of conduct and staff handbook at all times.</p>

                <p>We look forward to your valuable contribution to our academic community. Please sign and return a duplicate of this letter as a token of your acceptance of this offer.</p>

                <!-- STAFF PORTAL LOGIN CREDENTIALS BOX -->
                <div style="border: 2px solid #1e293b; border-radius: 8px; padding: 16px 20px; margin: 20px 0; background: #f8fafc;">
                    <div style="font-size: 13px; font-weight: 900; text-transform: uppercase; text-align: center; letter-spacing: 1px; border-bottom: 1px solid #cbd5e1; padding-bottom: 8px; margin-bottom: 12px;">
                        🔐 Staff Portal Login Credentials (CONFIDENTIAL)
                    </div>
                    <table style="width:100%; font-size: 13px; border-collapse: collapse;">
                        <tr>
                            <td style="padding: 5px 0; font-weight: 700; width: 130px;">Portal URL:</td>
                            <td style="padding: 5px 0;"><?= $loginUrl ?></td>
                        </tr>
                        <tr>
                            <td style="padding: 5px 0; font-weight: 700;">Login Email:</td>
                            <td style="padding: 5px 0;"><?= htmlspecialchars((string)$emp['email']) ?></td>
                        </tr>
                        <tr>
                            <td style="padding: 5px 0; font-weight: 700;">Password:</td>
                            <td style="padding: 5px 0;"><b>[As set during enrollment]</b> &nbsp;<i style="font-size:11px; color:#64748b;">(Contact Admin if forgotten)</i></td>
                        </tr>
                        <tr>
                            <td style="padding: 5px 0; font-weight: 700;">Your Role:</td>
                            <td style="padding: 5px 0;"><?= htmlspecialchars((string)$emp['designation_name']) ?> — <?= htmlspecialchars((string)($emp['dept_name'] ?? 'General')) ?> Department</td>
                        </tr>
                    </table>
                    <div style="font-size: 11px; color: #64748b; margin-top: 10px; font-style: italic;">
                        Note: Your login grants access to the staff dashboard for this school only. Keep your credentials secure and do not share them.
                    </div>
                </div>

                <p>Yours faithfully,</p>
            </div>

            <div class="signature-section">
                <?php if($school['signature_url']): ?>
                    <img src="<?= WEB_ROOT . $school['signature_url'] ?>" class="sig-img"><br>
                <?php else: ?>
                    <div style="height:60px;"></div>
                <?php endif; ?>
                <b>Management</b><br>
                <?= htmlspecialchars((string)$school['institution_name']) ?>
            </div>
        </div>
        
        <!-- Page break for printing multiple letters -->
        <?php if(count($employees) > 1): ?>
            <div style="page-break-after: always; height: 1px;"></div>
        <?php endif; ?>
    <?php endforeach; ?>
</div>

<?php require APP_PATH . '/Views/school_admin/layout/footer.php'; ?>
