<?php
require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/config/database.php';

try {
    // 1. Ensure HR Tables & Columns Exist
    $pdo->exec("CREATE TABLE IF NOT EXISTS `employees` (
      `id` int NOT NULL AUTO_INCREMENT,
      `user_id` int NOT NULL,
      `employee_number` varchar(50) NOT NULL,
      `department_id` int DEFAULT NULL,
      `designation_id` int DEFAULT NULL,
      `salary` decimal(15,2) DEFAULT '0.00',
      `hire_date` date DEFAULT NULL,
      `status` enum('Active','Resigned','Terminated') DEFAULT 'Active',
      `qr_token` varchar(255) DEFAULT NULL,
      `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`)
    ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;");

    // Auto-migrate columns if missing
    $tablesToUpdate = [
        'institution_profile' => ['signature_url', 'logo_url'],
        'employees' => ['qr_token']
    ];

    foreach ($tablesToUpdate as $table => $cols) {
        foreach ($cols as $col) {
            $check = $pdo->query("SHOW COLUMNS FROM `$table` LIKE '$col'")->fetch();
            if (!$check) {
                $pdo->exec("ALTER TABLE `$table` ADD `$col` VARCHAR(255) DEFAULT NULL");
            }
        }
    }

    // 2. Fetch School Profile from SUPERVISOR DB
    $schoolStmt = $supervisorPdo->prepare("SELECT * FROM institution_profile WHERE id = ? LIMIT 1");
    $schoolStmt->execute([$instituteId]);
    $school = $schoolStmt->fetch(PDO::FETCH_ASSOC);
    
    if (empty($school)) die("School profile not found.");
    
    // 3. Handle missing QR tokens for existing staff
    $missingTokens = $pdo->query("SELECT id FROM employees WHERE qr_token IS NULL OR qr_token = ''")->fetchAll(PDO::FETCH_ASSOC);
    if ($missingTokens) {
        foreach ($missingTokens as $m) {
            $token = bin2hex(random_bytes(16));
            $pdo->prepare("UPDATE employees SET qr_token = ? WHERE id = ?")->execute([$token, $m['id']]);
        }
    }

    // 4. Fetch Employees
    $query = "SELECT e.*, e.employee_number as employee_no, u.full_name, u.email, u.phone, u.photo_url as user_photo, d.designation_name, de.dept_name 
              FROM employees e 
              JOIN users u ON e.user_id = u.id 
              LEFT JOIN designations d ON e.designation_id = d.id
              LEFT JOIN departments de ON e.department_id = de.id
              WHERE e.status = 'Active'
              ORDER BY u.full_name ASC";
    $employees = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

$pageTitle = 'Print Staff ID Cards - Rosmon SMS';
require APP_PATH . '/Views/school_admin/layout/header.php';
require APP_PATH . '/Views/school_admin/layout/sidebar.php';
?>

<style>
    :root {
        --id-width: 320px;
        --id-height: 500px;
        --id-bg: #ffffff;
        --id-accent: #6366f1;
    }

    .id-card-wrapper {
        display: flex;
        flex-wrap: wrap;
        gap: 40px;
        justify-content: center;
        padding: 40px 0;
    }

    .id-card {
        width: var(--id-width);
        height: var(--id-height);
        background: var(--id-bg);
        border-radius: 20px;
        box-shadow: 0 10px 40px rgba(0,0,0,0.1);
        position: relative;
        overflow: hidden;
        border: 1px solid #eee;
        font-family: 'Inter', sans-serif;
    }

    .id-card-front {
        height: 100%;
        display: flex;
        flex-direction: column;
        align-items: center;
        text-align: center;
    }

    .id-card-back {
        height: 100%;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        padding: 30px;
        background: #f8fafc;
        text-align: center;
    }

    .card-header {
        width: 100%;
        height: 145px;
        background: linear-gradient(135deg, var(--id-accent), #4f46e5);
        clip-path: ellipse(80% 60% at 50% 30%);
        display: flex;
        flex-direction: column;
        align-items: center;
        padding-top: 15px;
        color: white;
    }

    .school-logo {
        width: 45px;
        height: 45px;
        background: white;
        border-radius: 50%;
        padding: 5px;
        object-fit: contain;
        margin-bottom: 5px;
    }

    .school-name-mini {
        font-size: 11px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 1px;
        max-width: 220px;
    }

    .photo-frame {
        width: 130px;
        height: 130px;
        background: white;
        border-radius: 20px;
        padding: 5px;
        margin-top: -55px;
        box-shadow: 0 8px 20px rgba(0,0,0,0.1);
        z-index: 10;
        overflow: hidden;
    }

    .photo-frame img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        border-radius: 15px;
    }

    .staff-info {
        margin-top: 15px;
        padding: 0 20px;
    }

    .staff-name {
        font-size: 20px;
        font-weight: 800;
        color: #1e293b;
        margin-bottom: 4px;
    }

    .staff-role {
        font-size: 12px;
        font-weight: 700;
        color: var(--id-accent);
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .qr-container {
        margin-top: 25px;
        padding: 10px;
        background: white;
        border: 1px solid #f1f5f9;
        border-radius: 12px;
    }

    .card-footer {
        width: 100%;
        height: 40px;
        background: #1e293b;
        position: absolute;
        bottom: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 10px;
        font-weight: 600;
    }

    /* Back Design */
    .back-title {
        font-size: 14px;
        font-weight: 800;
        color: #1e293b;
        margin-bottom: 20px;
    }

    .if-found {
        font-size: 11px;
        color: #64748b;
        line-height: 1.6;
        padding: 0 10px;
    }

    .school-details {
        border-top: 1px dashed #cbd5e1;
        padding-top: 20px;
        margin-top: 20px;
    }

    .proprietor-signature {
        width: 120px;
        height: auto;
        margin: 15px auto;
        opacity: 0.8;
    }

    @media print {
        body * { visibility: hidden; }
        .printable-area, .printable-area * { visibility: visible; }
        .printable-area { position: absolute; left: 0; top: 0; width: 100%; }
        .id-card { break-inside: avoid; margin-bottom: 20px; box-shadow: none; border: 1px solid #ddd; }
        ::-webkit-scrollbar { display: none; }
    }
</style>

<div class="main-container">
    <div class="top-header">
        <div class="greeting">HR Management / <span style="color:var(--primary)">Staff ID Cards Builder</span></div>
        <div class="header-actions">
            <button onclick="window.print()" class="btn-primary" style="background:#1e293b;"><i class="ph ph-printer"></i> Bulk Print All</button>
        </div>
    </div>

    <div class="printable-area id-card-wrapper">
        <?php foreach($employees as $emp): 
            $photo = $emp['user_photo'] ? WEB_ROOT . $emp['user_photo'] : 'https://ui-avatars.com/api/?name='.urlencode((string)$emp['full_name']).'&background=random&size=200';
            $scan_url = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]/api/attendance/scan?sid=".($_SESSION['school_id'] ?? 0)."&token=".$emp['qr_token'];
        ?>
            <!-- FRONT FACE -->
            <div class="id-card">
                <div class="id-card-front">
                    <div class="card-header">
                        <?php if($school['logo_url']): ?>
                            <img src="<?= WEB_ROOT . $school['logo_url'] ?>" class="school-logo">
                        <?php else: ?>
                            <div style="background:var(--primary); color:white; width:40px; height:40px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:900;"><?= substr((string)($school['institution_name'] ?? 'R'), 0, 1) ?></div>
                        <?php endif; ?>
                        <div class="school-name-mini"><?= htmlspecialchars((string)($school['institution_name'] ?? 'Rosmon School')) ?></div>
                    </div>
                    <div class="photo-frame">
                        <img src="<?= $photo ?>" alt="Staff Photo">
                    </div>
                    <div class="staff-info">
                        <div class="staff-name"><?= htmlspecialchars((string)$emp['full_name']) ?></div>
                        <div class="staff-role"><?= htmlspecialchars((string)($emp['designation_name'] ?? 'Staff Member')) ?></div>
                    </div>
                    
                    <div class="qr-container" id="qr-<?= $emp['id'] ?>" data-url="<?= $scan_url ?>"></div>
                    <div style="font-size:9px; color:#94a3b8; margin-top:8px; font-weight:700;">STAFF ID: <?= htmlspecialchars((string)$emp['employee_no']) ?></div>

                    <div class="card-footer">
                        OFFICIAL IDENTIFICATION CARD
                    </div>
                </div>
            </div>

            <!-- BACK FACE -->
            <div class="id-card">
                <div class="id-card-back">
                    <div>
                        <div class="back-title">IMPORTANT NOTICE</div>
                        <p class="if-found">
                            This card is the property of <b><?= htmlspecialchars((string)$school['institution_name']) ?></b>. 
                            If found, please return to the school address or the nearest police station.
                        </p>
                    </div>

                    <div class="school-details">
                        <div style="font-size:12px; font-weight:700; color:#1e293b;"><?= htmlspecialchars((string)$school['institution_name']) ?></div>
                        <div style="font-size:10px; color:#64748b; margin-top:4px;"><?= nl2br(htmlspecialchars((string)($school['address'] ?? 'School Address Not Set'))) ?></div>
                        
                        <?php if($school['signature_url']): ?>
                            <img src="<?= WEB_ROOT . '/public' . $school['signature_url'] ?>" class="proprietor-signature">
                        <?php else: ?>
                            <div style="height:40px; border-bottom:1px solid #ddd; margin:15px auto; width:150px;"></div>
                        <?php endif; ?>
                        <div style="font-size:9px; font-weight:800; color:#1e293b; text-transform:uppercase;">Proprietor Signature</div>
                    </div>

                    <div style="font-size:8px; color:#94a3b8;">
                        Powered by Rosmon SMS - Dynamic Schools SaaS
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
<script>
    document.querySelectorAll('.qr-container').forEach(container => {
        new QRCode(container, {
            text: container.dataset.url,
            width: 90,
            height: 90,
            colorDark : "#000000",
            colorLight : "#ffffff",
            correctLevel : QRCode.CorrectLevel.H
        });
    });
</script>

<?php require APP_PATH . '/Views/school_admin/layout/footer.php'; ?>
