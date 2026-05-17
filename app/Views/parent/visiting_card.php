<?php
require_once ROOT_PATH . '/config/database.php';

try {
    $uid = $_SESSION['user_id'];

    // 1. Fetch School Profile from SUPERVISOR DB
    $schoolStmt = $supervisorPdo->prepare("SELECT * FROM institution_profile WHERE id = ? LIMIT 1");
    $schoolStmt->execute([$instituteId ?? 0]);
    $school = $schoolStmt->fetch(PDO::FETCH_ASSOC);

    // 2. Fetch Parent Details
    $query = "SELECT p.*, u.full_name as parent_name, u.phone, u.photo_url as parent_photo, f.family_name, f.family_no
              FROM institute_parents p
              JOIN users u ON p.parent_id = u.id
              LEFT JOIN institute_families f ON p.family_id = f.id
              WHERE p.parent_id = ? LIMIT 1";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$uid]);
    $parent = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$parent) die("Parent record not found locally.");

    // 3. Fetch Children
    $stStmt = $pdo->prepare("SELECT u.full_name, c.class_name 
                             FROM institute_students s 
                             JOIN users u ON s.student_id = u.id 
                             LEFT JOIN classes c ON s.class_id = c.id 
                             WHERE s.family_id = ? AND s.is_deleted = 0");
    $stStmt->execute([$parent['family_id']]);
    $wards = $stStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

$pageTitle = 'My Visiting Card - Rosmon SMS';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= $pageTitle ?></title>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <style>
        :root { --primary: #059669; --bg: #f0fdf4; --text: #064e3b; --border: #d1fae5; }
        * { box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { margin: 0; background: var(--bg); display: flex; color: var(--text); }
        .sidebar { width: 260px; height: 100vh; background: var(--primary); color: white; padding: 20px; position: fixed; }
        .main { flex: 1; margin-left: 260px; padding: 30px; }
        .nav-link { display: flex; align-items: center; gap: 12px; padding: 12px; color: #d1fae5; text-decoration: none; border-radius: 8px; margin-bottom: 5px; }
        .nav-link:hover, .nav-link.active { background: rgba(255,255,255,0.1); color: white; }
        
        /* CARD DESIGN */
        .visit-card {
            width: 400px;
            background: white;
            border-radius: 20px;
            box-shadow: 0 15px 40px rgba(0,0,0,0.1);
            overflow: hidden;
            margin: 40px auto;
            border: 1px solid var(--border);
        }
        .card-header {
            background: var(--primary);
            padding: 20px;
            color: white;
            text-align: center;
        }
        .card-body {
            padding: 25px;
            display: flex;
            gap: 20px;
            align-items: center;
        }
        .parent-photo {
            width: 100px;
            height: 100px;
            border-radius: 15px;
            object-fit: cover;
            border: 3px solid #f0fdf4;
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
        }
        .card-footer {
            background: #f8fafc;
            padding: 15px 25px;
            border-top: 1px solid #f1f5f9;
            font-size: 12px;
            text-align: center;
            color: #64748b;
        }
        
        @media print {
            body * { visibility: hidden; }
            .visit-card, .visit-card * { visibility: visible; }
            .visit-card { 
                position: absolute; 
                left: 50%; 
                top: 50%; 
                transform: translate(-50%, -50%); 
                box-shadow: none; 
                border: 1px solid #eee; 
            }
            .sidebar, .btn-print { display: none !important; }
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div style="text-align:center; margin-bottom:10px;">
            <?php if (!empty($globalSchoolLogo)): ?>
                <img src="<?= WEB_ROOT . '/public' . $globalSchoolLogo ?>" alt="Logo" style="width:48px; height:48px; border-radius:50%; object-fit:contain; border:2px solid rgba(255,255,255,0.2);">
            <?php endif; ?>
        </div>
        <h2 style="font-size: 14px; margin-bottom: 30px; font-weight: 800; color: white; text-align:center;"><?= strtoupper(htmlspecialchars($globalSchoolName ?? 'Rosmon SMS')) ?></h2>
        <a href="dashboard" class="nav-link"><i class="ph ph-house"></i> Home</a>
        <a href="my-children" class="nav-link"><i class="ph ph-baby"></i> My Children</a>
        <a href="visiting-card" class="nav-link active"><i class="ph ph-identification-badge"></i> My Visiting Card</a>
        <a href="payments" class="nav-link"><i class="ph ph-receipt"></i> Payment history</a>
        <a href="performance" class="nav-link"><i class="ph ph-chart-line"></i> Performance</a>
        <a href="#" class="nav-link"><i class="ph ph-chat-circle"></i> Teacher Chat</a>
        <div style="margin-top: auto; padding-top: 20px;">
            <a href="<?= WEB_ROOT ?>/logout" class="nav-link" style="color: #fda4af;"><i class="ph ph-sign-out"></i> Logout</a>
        </div>
    </div>

    <div class="main">
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <h1>My Digital ID Card</h1>
            <button onclick="window.print()" class="btn-print" style="padding:10px 20px; background:var(--primary); color:white; border:none; border-radius:8px; cursor:pointer; font-weight:700;"><i class="ph ph-printer"></i> Print / Save PDF</button>
        </div>

        <div class="visit-card">
            <div class="card-header">
                <div style="font-size:10px; font-weight:800; opacity:0.8; letter-spacing:1px;">OFFICIAL GUARDIAN IDENTIFICATION</div>
                <div style="font-size:15px; font-weight:900; margin-top:5px;"><?= strtoupper(htmlspecialchars((string)$school['institution_name'])) ?></div>
            </div>
            <div class="card-body">
                <img src="<?= !empty($parent['parent_photo']) ? WEB_ROOT . $parent['parent_photo'] : 'https://ui-avatars.com/api/?name='.urlencode((string)$parent['parent_name']).'&background=059669&color=fff&size=100' ?>" class="parent-photo">
                <div>
                    <div style="background:#ecfdf5; color:var(--primary); padding:2px 10px; border-radius:100px; font-size:10px; font-weight:800; display:inline-block; margin-bottom:8px;"><?= htmlspecialchars((string)($parent['family_no'] ?? 'N/A')) ?></div>
                    <div style="font-size:20px; font-weight:900; color:#1e293b;"><?= htmlspecialchars((string)$parent['parent_name']) ?></div>
                    <div style="margin-top:10px; font-size:11px; color:#64748b; font-weight:700;">REGISTERED CHILDREN:</div>
                    <div style="margin-top:5px; font-size:12px;">
                        <?php foreach($wards as $w): ?>
                            <div style="padding-bottom:2px;">• <?= htmlspecialchars($w['full_name']) ?> (<?= htmlspecialchars($w['class_name']) ?>)</div>
                        <?php endforeach; ?>
                        <?php if(empty($wards)): ?>
                            <div style="color:#ef4444; font-size:11px; font-style:italic;">No wards linked. Contact Admin.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="card-footer">
                <b>Valid for session <?= date('Y') ?>/<?= date('Y')+1 ?></b> | Guardian Ref: <?= htmlspecialchars((string)($parent['parent_no'] ?? 'N/A')) ?>
            </div>
        </div>

        <p style="text-align:center; color:#64748b; font-size:13px; max-width:500px; margin: 20px auto;">
            This digital ID serves as your verification for school entrance and child pickup. Please present it at the security station when requested.
        </p>
    </div>
</body>
</html>
