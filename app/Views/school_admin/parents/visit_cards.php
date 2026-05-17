<?php
require_once ROOT_PATH . '/config/database.php';

try {
    // 1. Fetch School Profile from SUPERVISOR DB
    $schoolStmt = $supervisorPdo->prepare("SELECT * FROM institution_profile WHERE id = ? LIMIT 1");
    $schoolStmt->execute([$instituteId]);
    $school = $schoolStmt->fetch(PDO::FETCH_ASSOC);

    // 2. Fetch Parents with Family Details
    $query = "SELECT p.id as parent_rec_id, u.full_name as parent_name, u.phone, u.photo_url as parent_photo, f.family_name, f.family_no, p.family_id, p.parent_no
              FROM institute_parents p
              JOIN users u ON p.parent_id = u.id
              LEFT JOIN institute_families f ON p.family_id = f.id
              WHERE p.is_deleted = 0
              ORDER BY u.full_name ASC";
    $parents = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);

    // 3. Prepare Student Fetching
    $stStmt = $pdo->prepare("SELECT u.full_name, c.class_name FROM institute_students s JOIN users u ON s.student_id = u.id LEFT JOIN classes c ON s.class_id = c.id WHERE s.family_id = ? AND s.is_deleted = 0");

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

$pageTitle = 'Parent Verification Cards - Rosmon SMS';
require ROOT_PATH . '/app/Views/school_admin/layout/header.php';
require ROOT_PATH . '/app/Views/school_admin/layout/sidebar.php';
?>

<style>
    :root {
        --card-width: 350px;
        --card-height: 220px;
        --card-bg: #ffffff;
        --card-accent: #059669;
    }

    .cards-container {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(var(--card-width), 1fr));
        gap: 30px;
        padding: 20px 0;
    }

    .visit-card {
        width: var(--card-width);
        height: var(--card-height);
        background: var(--card-bg);
        border-radius: 15px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.08);
        position: relative;
        overflow: hidden;
        border: 1px solid #e2e8f0;
        display: flex;
        flex-direction: column;
    }

    .card-header {
        background: linear-gradient(135deg, var(--card-accent), #10b981);
        padding: 12px 15px;
        color: white;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .school-info-mini {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .school-logo-mini {
        width: 24px;
        height: 24px;
        background: white;
        border-radius: 50%;
        padding: 2px;
        object-fit: contain;
    }

    .school-name-mini {
        font-size: 10px;
        font-weight: 800;
        text-transform: uppercase;
        max-width: 180px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .card-body {
        padding: 15px;
        display: flex;
        gap: 15px;
        flex: 1;
    }

    .parent-photo {
        width: 80px;
        height: 80px;
        border-radius: 10px;
        object-fit: cover;
        border: 2px solid #f1f5f9;
    }

    .parent-info {
        flex: 1;
    }

    .parent-name {
        font-size: 16px;
        font-weight: 800;
        color: #1e293b;
        margin-bottom: 2px;
    }

    .family-badge {
        font-size: 10px;
        font-weight: 700;
        background: #ecfdf5;
        color: #059669;
        padding: 2px 8px;
        border-radius: 4px;
        display: inline-block;
        margin-bottom: 8px;
    }

    .ward-list {
        margin-top: 5px;
        font-size: 11px;
    }

    .ward-item {
        display: flex;
        justify-content: space-between;
        color: #64748b;
        margin-bottom: 2px;
    }

    .card-footer {
        background: #f8fafc;
        padding: 8px 15px;
        border-top: 1px solid #f1f5f9;
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 10px;
        color: #94a3b8;
        font-weight: 600;
    }

    @media print {
        body * { visibility: hidden; }
        .printable-area, .printable-area * { visibility: visible; }
        .printable-area { position: absolute; left: 0; top: 0; width: 100%; }
        .visit-card { break-inside: avoid; margin-bottom: 20px; box-shadow: none; border: 1px solid #ddd; }
        .action-header { display: none !important; }
    }
</style>

<div class="main-container">
    <div class="top-header action-header">
        <div class="greeting">Parents / <span style="color:var(--primary)">Parental Visiting Cards</span></div>
        <div class="header-actions">
            <button onclick="window.print()" class="btn-primary" style="background:#1e293b;"><i class="ph ph-printer"></i> Bulk Print All</button>
        </div>
    </div>

    <div class="printable-area cards-container">
        <?php foreach($parents as $p): 
            $stStmt->execute([$p['family_id']]);
            $wards = $stStmt->fetchAll(PDO::FETCH_ASSOC);
            $photo = !empty($p['parent_photo']) ? WEB_ROOT . $p['parent_photo'] : 'https://ui-avatars.com/api/?name='.urlencode((string)$p['parent_name']).'&background=059669&color=fff&size=100';
        ?>
            <div class="visit-card">
                <div class="card-header">
                    <div class="school-info-mini">
                        <?php if(!empty($school['logo_url'])): ?>
                            <img src="<?= WEB_ROOT . $school['logo_url'] ?>" class="school-logo-mini">
                        <?php endif; ?>
                        <div class="school-name-mini"><?= htmlspecialchars((string)$school['institution_name']) ?></div>
                    </div>
                    <div style="font-size: 8px; font-weight: 800; opacity: 0.8;">OFFICIAL VISITING CARD</div>
                </div>

                <div class="card-body">
                    <img src="<?= $photo ?>" class="parent-photo">
                    <div class="parent-info">
                        <div class="family-badge"><?= htmlspecialchars((string)($p['family_no'] ?? 'GUEST')) ?></div>
                        <div class="parent-name"><?= htmlspecialchars((string)$p['parent_name']) ?></div>
                        <div style="font-size: 11px; font-weight: 700; color: #64748b; margin-top: 5px;">REGISTERED WARDS:</div>
                        <div class="ward-list">
                            <?php if(empty($wards)): ?>
                                <div class="ward-item" style="font-style:italic;">No wards linked yet.</div>
                            <?php else: ?>
                                <?php foreach(array_slice($wards, 0, 3) as $w): ?>
                                    <div class="ward-item">
                                        <span>• <?= htmlspecialchars((string)$w['full_name']) ?></span>
                                        <span style="font-weight:700;"><?= htmlspecialchars((string)$w['class_name']) ?></span>
                                    </div>
                                <?php endforeach; ?>
                                <?php if(count($wards) > 3): ?>
                                    <div style="font-size:9px; color:var(--card-accent); margin-top:2px;">+ <?= count($wards) - 3 ?> more children</div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="card-footer">
                    <div>REG NO: <?= htmlspecialchars((string)($p['parent_no'] ?? 'N/A')) ?></div>
                    <div><?= htmlspecialchars((string)$p['phone']) ?></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php require ROOT_PATH . '/app/Views/school_admin/layout/footer.php'; ?>
