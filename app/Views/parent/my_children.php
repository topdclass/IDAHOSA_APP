<?php
require_once ROOT_PATH . '/config/database.php';

try {
    $uid = $_SESSION['user_id'];

    // 1. Fetch Parent Details
    $pst = $pdo->prepare("SELECT family_id FROM institute_parents WHERE parent_id = ? LIMIT 1");
    $pst->execute([$uid]);
    $parent = $pst->fetch(PDO::FETCH_ASSOC);

    if (!$parent) die("Parent record not found.");

    // 2. Fetch Children
    $query = "SELECT s.*, u.full_name, u.photo_url, c.class_name, u.email, u.phone
              FROM institute_students s 
              JOIN users u ON s.student_id = u.id 
              LEFT JOIN classes c ON s.class_id = c.id 
              WHERE s.family_id = ? AND s.is_deleted = 0";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$parent['family_id']]);
    $children = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

$pageTitle = 'My Children - Rosmon SMS';
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
        
        .children-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-top: 30px; }
        .child-card { background: white; border-radius: 20px; padding: 25px; border: 1px solid var(--border); display: flex; align-items: center; gap: 20px; }
        .child-photo { width: 80px; height: 80px; border-radius: 15px; background: #f1f5f9; object-fit: cover; }
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
        <a href="my-children" class="nav-link active"><i class="ph ph-baby"></i> My Children</a>
        <a href="visiting-card" class="nav-link"><i class="ph ph-identification-badge"></i> My Visiting Card</a>
        <a href="payments" class="nav-link"><i class="ph ph-receipt"></i> Payment history</a>
        <a href="performance" class="nav-link"><i class="ph ph-chart-line"></i> Performance</a>
        <a href="#" class="nav-link"><i class="ph ph-chat-circle"></i> Teacher Chat</a>
        <div style="margin-top: auto; padding-top: 20px;">
            <a href="<?= WEB_ROOT ?>/logout" class="nav-link" style="color: #fda4af;"><i class="ph ph-sign-out"></i> Logout</a>
        </div>
    </div>

    <div class="main">
        <h1 style="font-size: 24px;">My Children</h1>
        <p style="color: #64748b; margin-top: 5px;">View your wards currently enrolled in <?= htmlspecialchars($globalSchoolName) ?>.</p>

        <div class="children-grid">
            <?php if(empty($children)): ?>
                <div style="background:white; padding:40px; border-radius:20px; text-align:center; grid-column: 1 / -1; color:#64748b;">
                    <div style="font-size:40px; margin-bottom:15px;"><i class="ph ph-ghost"></i></div>
                    <div style="font-weight:700;">No children found.</div>
                    <div style="font-size:13px; margin-top:5px;">Please contact the school admin to link your profile to your wards.</div>
                </div>
            <?php else: ?>
                <?php foreach($children as $c): ?>
                    <div class="child-card">
                        <img src="<?= !empty($c['photo_url']) ? WEB_ROOT . $c['photo_url'] : 'https://ui-avatars.com/api/?name='.urlencode((string)$c['full_name']).'&background=random&size=100' ?>" class="child-photo">
                        <div style="flex:1;">
                            <div style="background:#ecfdf5; color:var(--primary); padding:2px 8px; border-radius:6px; font-size:10px; font-weight:800; display:inline-block; margin-bottom:5px;"><?= htmlspecialchars((string)$c['student_no']) ?></div>
                            <div style="font-size:18px; font-weight:900; color:#1e293b;"><?= htmlspecialchars((string)$c['full_name']) ?></div>
                            <div style="color:var(--primary); font-weight:700; font-size:13px; margin-top:4px;"><?= htmlspecialchars((string)$c['class_name']) ?></div>
                            <div style="display:flex; align-items:center; gap:10px; margin-top:12px; font-size:11px; color:#64748b;">
                                <a href="performance?id=<?= $c['id'] ?>" style="color:var(--primary); text-decoration:none; display:flex; align-items:center; gap:4px; font-weight:700;"><i class="ph ph-chart-line-up"></i> Performance</a>
                                <span>|</span>
                                <a href="payments?id=<?= $c['id'] ?>" style="color:#6366f1; text-decoration:none; display:flex; align-items:center; gap:4px; font-weight:700;"><i class="ph ph-wallet"></i> Fees</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
