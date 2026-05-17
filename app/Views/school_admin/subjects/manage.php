<?php
require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/config/database.php';

// Subjects Management & Mapping Logic

try {
    $message = '';

    // Handle Subject Creation
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_subject'])) {
        $name = $_POST['subject_name'] ?? '';
        $code = $_POST['subject_code'] ?? '';
        
        $stmt = $pdo->prepare("INSERT INTO subjects (subject_name, subject_code, institute_id) VALUES (?, ?, ?)");
        $stmt->execute([$name, $code, $instituteId]);
        $message = "Subject '$name' added to Global Subject Bank!";
    }

    // Handle Subject Deletion from Bank
    if (isset($_GET['delete_bank_id'])) {
        $del_id = $_GET['delete_bank_id'];
        $pdo->prepare("UPDATE subjects SET is_deleted=1 WHERE id=? AND institute_id=?")->execute([$del_id, $instituteId]);
        $message = "Subject removed from Global Bank.";
    }

    // Fetch Classes and their subject counts from REAL tables
    $stmt = $pdo->query("SELECT c.id, c.class_name, c.arm as section, COUNT(cs.id) as sub_count 
                         FROM classes c 
                         LEFT JOIN class_subjects cs ON c.id = cs.class_id AND cs.is_deleted = 0
                         WHERE c.is_deleted = 0
                         GROUP BY c.id");
    $classes_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch from Global Subject Bank
    $stmt = $pdo->query("SELECT * FROM subjects ORDER BY subject_name ASC");
    $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

$pageTitle = 'Curriculum Management - Rosmon SMS';
require ROOT_PATH . '/app/Views/school_admin/layout/header.php';
require ROOT_PATH . '/app/Views/school_admin/layout/sidebar.php';
?>

<div class="main-container">
    <div class="top-header">
        <div class="greeting">Academics / <span style="color:var(--primary)">Subject Curriculum</span></div>
        <div class="header-actions">
            <button onclick="document.getElementById('sub-modal').style.display='flex'" class="btn-primary"><i class="ph ph-plus-circle"></i> Create Subject</button>
        </div>
    </div>

    <div style="display:grid; grid-template-columns: 1fr 2fr; gap:24px;">
        
        <!-- Subject Master List (Sidebar style) -->
        <div class="crud-card">
            <div class="crud-header">
                <h2 class="crud-title">Subject Bank</h2>
            </div>
            <div style="padding:15px; max-height:500px; overflow-y:auto;">
                <?php if (empty($subjects)): ?>
                    <p style="font-size:12px; color:var(--text-muted); text-align:center;">No subjects registered.</p>
                <?php else: ?>
                    <?php foreach($subjects as $s): ?>
                        <div style="display:flex; justify-content:space-between; align-items:center; padding:10px; border-bottom:1px solid #f9fafb;">
                            <div>
                                <div style="font-size:13px; font-weight:700;"><?= htmlspecialchars((string)($s['subject_name'] ?? '')) ?></div>
                                <div style="font-size:10px; color:var(--text-muted);"><?= htmlspecialchars((string)($s['subject_code'] ?? '')) ?></div>
                            </div>
                            <a href="?delete_bank_id=<?= $s['id'] ?>" onclick="return confirm('Remove this subject from the Global Bank?')" style="color:#ef4444;"><i class="ph ph-trash"></i></a>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Classes Mapping Overview -->
        <div class="crud-card">
            <div class="crud-header">
                <h2 class="crud-title">Class-Subject Pairings</h2>
                <span style="color:var(--primary); font-size:12px; font-weight:700;"><?= $message ?></span>
            </div>
            <table class="crud-table">
                <thead>
                    <tr>
                        <th>CLASS NAME</th>
                        <th>CLASS SECTION</th>
                        <th>SUBJECTS LINKED</th>
                        <th>ACTION</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($classes_list as $cl): ?>
                        <tr>
                            <td style="font-weight:700; color:var(--text-dark);"><?= htmlspecialchars((string)($cl['class_name'] ?? '')) ?></td>
                            <td><span style="background:#f3f4f6; color:var(--text-dark); padding:4px 10px; border-radius:4px; font-weight:700; font-size:11px;"><?= htmlspecialchars((string)($cl['section'] ?? '')) ?></span></td>
                            <td style="font-weight:800; color:<?= ($cl['sub_count'] ?? 0) > 0 ? 'var(--primary)' : '#ef4444' ?>;"><?= (int)($cl['sub_count'] ?? 0) ?> Subjects</td>
                            <td>
                                <a href="<?= WEB_ROOT ?>/school-admin/subjects/assign?class_id=<?= $cl['id'] ?>" class="btn-primary" style="padding:6px 12px; font-size:11px; text-decoration:none;">Manage Pairing</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Simple Add Subject Modal (Overlay) -->
    <div id="sub-modal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:1001; align-items:center; justify-content:center;">
        <div class="crud-card" style="width:400px; margin:0; padding:24px;">
            <h3 style="margin-bottom:20px;">Add New Subject</h3>
            <form method="POST">
                <input type="hidden" name="add_subject" value="1">
                <label style="display:block; font-size:12px; font-weight:700; margin-bottom:5px;">SUBJECT NAME</label>
                <input type="text" name="subject_name" required placeholder="e.g. Mathematics" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:6px; margin-bottom:15px;">
                <label style="display:block; font-size:12px; font-weight:700; margin-bottom:5px;">SUBJECT CODE (UNIQUE)</label>
                <input type="text" name="subject_code" placeholder="e.g. MAT101" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:6px; margin-bottom:20px;">
                <div style="display:flex; gap:10px; justify-content:flex-end;">
                    <button type="button" onclick="document.getElementById('sub-modal').style.display='none'" style="border:none; background:none; font-weight:700; cursor:pointer;">Cancel</button>
                    <button type="submit" class="btn-primary">Register Subject</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require ROOT_PATH . '/app/Views/school_admin/layout/footer.php'; ?>
