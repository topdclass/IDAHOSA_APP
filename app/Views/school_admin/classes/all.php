<?php
require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/config/database.php';

// Classes Management Logic

try {
    
    $message = '';

    // Handle Soft Delete
    if (isset($_GET['delete'])) {
        $id = $_GET['delete'];
        $stmt = $pdo->prepare("UPDATE classes SET is_deleted = 1 WHERE id = ? AND (institute_id = " . ($instituteId ?? 0) . " OR institute_id IS NULL)");
        $stmt->execute([$id]);
        $message = "Class archived successfully!";
    }

    // Ensure 'arm' column exists
    try {
        $check = $pdo->query("SHOW COLUMNS FROM classes LIKE 'arm'")->fetch();
        if (!$check) {
            $pdo->exec("ALTER TABLE classes ADD COLUMN arm VARCHAR(50) NULL AFTER class_name");
        }
    } catch (Exception $e) {}

    // Fetch All
    $iWhere = $instituteId ? "AND c.institute_id = {$instituteId}" : '';
    $stmt = $pdo->query("
        SELECT c.*, 
               u.full_name as teacher_name,
               (SELECT COUNT(student_id) FROM institute_students WHERE class_id = c.id AND is_deleted = 0) as student_count 
        FROM classes c 
        LEFT JOIN users u ON c.teacher_id = u.id
        WHERE c.is_deleted = 0 {$iWhere}
        ORDER BY c.class_name, c.section ASC
    ");
    $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

$pageTitle = 'All Classes - Rosmon SMS';
require ROOT_PATH . '/app/Views/school_admin/layout/header.php';
require ROOT_PATH . '/app/Views/school_admin/layout/sidebar.php';
?>

<div class="main-container">
    <div class="top-header">
        <div class="greeting">Academics / <span style="color:var(--primary)">All Classes</span></div>
        <div class="header-actions">
            <a href="<?= WEB_ROOT ?>/school-admin/classes/new" class="btn-primary" style="text-decoration:none;"><i class="ph ph-plus"></i> New Class</a>
        </div>
    </div>

    <div class="crud-card">
        <div class="crud-header">
            <h2 class="crud-title">Managed Classes & Sections</h2>
            <?php if ($message): ?>
                <span style="color:#10b981; font-weight:700; font-size:12px;"><?= $message ?></span>
            <?php endif; ?>
        </div>

        <table class="crud-table">
            <thead>
                <tr>
                    <th>CLASS NAME</th>
                    <th>FORM TEACHER</th>
                    <th>MONTHLY FEE</th>
                    <th>STUDENT COUNT</th>
                    <th>ACTIONS</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($classes)): ?>
                    <tr><td colspan="4" style="text-align:center; padding:30px; color:var(--text-muted);">No classes configured yet.</td></tr>
                <?php else: ?>
                    <?php foreach($classes as $c): ?>
                        <tr>
                            <td style="padding: 16px;">
                                <div style="display:flex; align-items:center; gap:12px;">
                                    <div style="width:36px; height:36px; background:#e0f2fe; color:var(--primary); border-radius:10px; display:flex; align-items:center; justify-content:center; font-weight:800; font-size:14px; flex-shrink:0;">
                                        <?= strtoupper(substr($c['class_name'], 0, 1)) ?>
                                    </div>
                                    <div>
                                        <div style="font-weight:800; color:#111827; font-size:14px; letter-spacing:0.3px;"><?= htmlspecialchars((string)($c['class_name'] ?? '')) ?></div>
                                        <?php if (!empty($c['arm'])): ?>
                                            <div style="display:inline-flex; align-items:center; gap:4px; font-size:11px; font-weight:700; color:#6366f1; background:#eef2ff; padding:2px 8px; border-radius:6px; margin-top:4px;">
                                                <i class="ph ph-squares-four" style="font-size:10px;"></i> ARM: <?= htmlspecialchars((string)$c['arm']) ?>
                                            </div>
                                        <?php else: ?>
                                            <div style="font-size:10px; font-weight:500; color:#9ca3af; margin-top:2px;">(No Arm Assigned)</div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <?php if (!empty($c['teacher_name'])): ?>
                                    <div style="display:flex; align-items:center; gap:8px;">
                                        <div style="width:28px; height:28px; background:#f5f3ff; color:#7c3aed; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:12px; font-weight:700;">
                                            <?= strtoupper(substr($c['teacher_name'], 0, 1)) ?>
                                        </div>
                                        <div style="font-size:13px; font-weight:600; color:#1e293b;"><?= htmlspecialchars($c['teacher_name']) ?></div>
                                    </div>
                                <?php else: ?>
                                    <span style="font-size:11px; color:#9ca3af; font-style:italic;">Not Assigned</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="font-weight:800; color:#059669; background:#ecfdf5; padding:6px 12px; border-radius:8px; font-size:12px; display:inline-flex; align-items:center; gap:6px;">
                                    ₦<?= number_format((float)($c['monthly_fee'] ?? 0), 2) ?>
                                </div>
                            </td>
                            <td>
                                <div style="display:flex; align-items:center; gap:8px;">
                                    <div style="height:8px; width:45px; background:#f3f4f6; border-radius:10px; overflow:hidden; position:relative;">
                                        <div style="height:100%; background:#6366f1; border-radius:10px; width: <?= min(100, ($c['student_count'] * 2)) ?>%;"></div>
                                    </div>
                                    <span style="font-weight:800; color:#374151; font-size:12px;"><?= (int)($c['student_count'] ?? 0) ?></span>
                                </div>
                            </td>
                            <td>
                                <div style="display:flex; gap:10px;">
                                    <a href="#" style="width:34px; height:34px; background:#f9fafb; border:1px solid #e5e7eb; color:var(--primary); border-radius:8px; display:flex; align-items:center; justify-content:center; transition:0.2s;" onmouseover="this.style.background='#e0f2fe'; this.style.borderColor='#bae6fd';" onmouseout="this.style.background='#f9fafb'; this.style.borderColor='#e5e7eb';"><i class="ph ph-note-pencil" style="font-size:18px;"></i></a>
                                    <a href="?delete=<?= $c['id'] ?>" onclick="return confirm('Archive this class? Students will remain but class configuration will be hidden.');" style="width:34px; height:34px; background:#f9fafb; border:1px solid #e5e7eb; color:#ef4444; border-radius:8px; display:flex; align-items:center; justify-content:center; transition:0.2s;" onmouseover="this.style.background='#fef2f2'; this.style.borderColor='#fecaca';" onmouseout="this.style.background='#f9fafb'; this.style.borderColor='#e5e7eb';"><i class="ph ph-trash-simple" style="font-size:18px;"></i></a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require ROOT_PATH . '/app/Views/school_admin/layout/footer.php'; ?>
