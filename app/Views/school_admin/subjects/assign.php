<?php
// Subject Assignment to Classes Logic

try {
    $message = '';
    $selected_class = $_GET['class_id'] ?? null;

    if (!$selected_class) {
        header("Location: " . WEB_ROOT . "/school-admin/subjects/manage"); 
        exit;
    }

    // Insert new subject for this class
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['insert_subject'])) {
            $name = $_POST['new_subject_name'] ?? '';
            $sub_id = $_POST['subject_id'] ?? null;
            $mark = $_POST['total_exam_mark'] ?? 100;
            
            if (!empty($name)) {
                $stmt = $pdo->prepare("INSERT INTO class_subjects (subject_id, class_id, institute_id, created_at) VALUES (?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE subject_id=VALUES(subject_id)");
                $stmt->execute([$sub_id, $selected_class, $instituteId]);
                $message = "Subject '$name' added to curriculum!";
            }
        } elseif (isset($_POST['delete_subject'])) {
            $del_id = $_POST['delete_id'];
            $stmt = $pdo->prepare("UPDATE class_subjects SET is_deleted = 1 WHERE id = ?");
            $stmt->execute([$del_id]);
            $message = "Subject removed from curriculum.";
        }
    }

    // Fetch Class Details
    $stmt = $pdo->prepare("SELECT * FROM classes WHERE id = ?");
    $stmt->execute([$selected_class]);
    $class_info = $stmt->fetch(PDO::FETCH_ASSOC);

    // Fetch Currently Assigned Subjects
    $stmt = $pdo->prepare("SELECT * FROM class_subjects WHERE class_id = ? AND is_deleted = 0 ORDER BY name ASC");
    $stmt->execute([$selected_class]);
    $current_subs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch Subject Bank for dropdown
    $bank_subjects = $pdo->query("SELECT * FROM subjects WHERE is_deleted=0 AND (institute_id IS NULL OR institute_id=0 OR institute_id=' . ($instituteId ?? 0) . ') ORDER BY subject_name ASC")->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

$pageTitle = 'Curriculum Editor - Rosmon SMS';
require ROOT_PATH . '/app/Views/school_admin/layout/header.php';
require ROOT_PATH . '/app/Views/school_admin/layout/sidebar.php';
?>

<div class="main-container">
    <div class="top-header">
        <div class="greeting">Classes / <?= htmlspecialchars($class_info['class_name'] ?? 'Class') ?> / <span style="color:var(--primary)">Curriculum Editor</span></div>
        <div class="header-actions">
            <a href="<?= WEB_ROOT ?>/school-admin/subjects/manage" class="btn-primary" style="background:#f3f4f6; color:var(--text-dark); text-decoration:none;"><i class="ph ph-arrow-left"></i> Back to Classes</a>
        </div>
    </div>

    <div style="display:grid; grid-template-columns: 1fr 2fr; gap:24px;">
        <!-- Add Subject Form -->
        <div class="crud-card" style="height:fit-content;">
            <div class="crud-header">
                <h2 class="crud-title">Add Subject to Class <?= htmlspecialchars($class_info['arm'] ?? '') ?></h2>
            </div>
            <form method="POST" style="padding:15px;">
                <input type="hidden" name="insert_subject" value="1">
                
                <label style="display:block; font-size:12px; font-weight:700; margin-bottom:5px; color:var(--text-dark);">CHOOSE FROM BANK</label>
                <select name="subject_id" onchange="document.getElementsByName('new_subject_name')[0].value=this.options[this.selectedIndex].text" style="width:100%; padding:10px; border:1px solid var(--border); border-radius:6px; margin-bottom:15px; font-size:13px;">
                    <option value="">-- Browse Bank --</option>
                    <?php foreach($bank_subjects as $bs): ?>
                        <option value="<?= $bs['id'] ?>"><?= htmlspecialchars($bs['subject_name']) ?></option>
                    <?php endforeach; ?>
                </select>

                <label style="display:block; font-size:12px; font-weight:700; margin-bottom:5px; color:var(--text-dark);">SUBJECT NAME (OR ADJUST)</label>
                <input type="text" name="new_subject_name" required placeholder="e.g. Mathematics" style="width:100%; padding:10px; border:1px solid var(--border); border-radius:6px; margin-bottom:15px; font-size:13px;">
                
                <label style="display:block; font-size:12px; font-weight:700; margin-bottom:5px; color:var(--text-dark);">TOTAL EXAM MARK</label>
                <input type="number" name="total_exam_mark" value="100" style="width:100%; padding:10px; border:1px solid var(--border); border-radius:6px; margin-bottom:24px; font-size:13px;">
                
                <button type="submit" class="btn-primary" style="width:100%; padding:12px;">Append Subject</button>
            </form>
        </div>

        <!-- Existing Subjects List -->
        <div class="crud-card">
            <div class="crud-header">
                <h2 class="crud-title">Current Curriculum</h2>
                <?php if ($message): ?>
                    <span style="color:#10b981; font-weight:700; font-size:12px; background:#dcfce7; padding:4px 10px; border-radius:20px;"><?= $message ?></span>
                <?php endif; ?>
            </div>
            <table class="crud-table" style="font-size:13px;">
                <thead>
                    <tr>
                        <th>SUBJECT NAME</th>
                        <th>MAX SCORE</th>
                        <th>ACTION</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($current_subs)): ?>
                        <tr><td colspan="3" style="text-align:center; padding:30px; color:var(--text-muted);">No subjects found for this class. Time to build the curriculum!</td></tr>
                    <?php else: ?>
                        <?php foreach($current_subs as $s): ?>
                            <tr>
                                <td style="font-weight:700; color:var(--text-dark);"><?= htmlspecialchars((string)($s['name'] ?? '')) ?></td>
                                <td><span style="font-weight:700; color:var(--primary);"><?= htmlspecialchars((string)($s['total_exam_mark'] ?? 100)) ?></span></td>
                                <td>
                                    <form method="POST" onsubmit="return confirm('Remove subject <?= htmlspecialchars((string)($s['name'] ?? '')) ?> from class?');" style="display:inline;">
                                        <input type="hidden" name="delete_subject" value="1">
                                        <input type="hidden" name="delete_id" value="<?= $s['id'] ?>">
                                        <button class="btn-primary" style="background:#fef2f2; color:#ef4444; border:none; padding:6px 10px; font-size:11px;"><i class="ph ph-trash"></i> Drop</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require ROOT_PATH . '/app/Views/school_admin/layout/footer.php'; ?>
