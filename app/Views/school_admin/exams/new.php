<?php
require_once ROOT_PATH . '/config/database.php';
// Each tenant has their own DB — $pdo is already scoped to this school only.
$classes  = $pdo->query("SELECT id, class_name, section FROM classes WHERE is_deleted = 0 ORDER BY class_name ASC")->fetchAll(PDO::FETCH_ASSOC);
$subjects = $pdo->query("SELECT id, name FROM class_subjects WHERE is_deleted = 0 ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Create New Exam - Rosmon SMS';
require ROOT_PATH . '/app/Views/school_admin/layout/header.php';
require ROOT_PATH . '/app/Views/school_admin/layout/sidebar.php';
?>
<div class="main-container">
    <div class="top-header">
        <div class="greeting">Exams / <span style="color:var(--primary)">Set New Examination</span></div>
    </div>
    <div class="crud-card">
        <div class="crud-header"><h2 class="crud-title">Exam Blueprint Entry</h2></div>
        <form method="POST" style="padding: 24px; display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <div style="grid-column: span 2;">
                <label style="display:block; font-size:12px; font-weight:700; margin-bottom:5px;">EXAM TITLE</label>
                <input type="text" name="title" placeholder="e.g. First Term Mathematics Exam" style="width:100%; padding:10px; border:1px solid var(--border); border-radius:8px; font-size:13px; outline:none;">
            </div>
            <div>
                <label style="display:block; font-size:12px; font-weight:700; margin-bottom:5px;">CLASS</label>
                <select name="class_id" required style="width:100%; padding:10px; border:1px solid var(--border); border-radius:8px; font-size:13px; outline:none;">
                    <option value="">-- Select Class --</option>
                    <?php foreach($classes as $c): ?>
                        <option value="<?= $c['id'] ?>">
                            <?= htmlspecialchars((string)($c['class_name'] ?? 'N/A')) ?>
                            <?php if(!empty($c['section'])): ?> (<?= htmlspecialchars((string)$c['section']) ?>)<?php endif; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label style="display:block; font-size:12px; font-weight:700; margin-bottom:5px;">SUBJECT</label>
                <select name="subject_id" required style="width:100%; padding:10px; border:1px solid var(--border); border-radius:8px; font-size:13px; outline:none;">
                    <option value="">-- Select Subject --</option>
                    <?php foreach($subjects as $s): ?>
                        <option value="<?= $s['id'] ?>"><?= htmlspecialchars((string)($s['name'] ?? '')) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label style="display:block; font-size:12px; font-weight:700; margin-bottom:5px;">START DATE</label>
                <input type="date" name="start_date" style="width:100%; padding:10px; border:1px solid var(--border); border-radius:8px; font-size:13px; outline:none;">
            </div>
            <div>
                <label style="display:block; font-size:12px; font-weight:700; margin-bottom:5px;">DURATION (MINUTES)</label>
                <input type="number" name="duration" placeholder="e.g. 120" style="width:100%; padding:10px; border:1px solid var(--border); border-radius:8px; font-size:13px; outline:none;">
            </div>
            <div style="grid-column: span 2; text-align: right;">
                <button type="submit" class="btn-primary" style="padding:12px 24px; border:none; border-radius:8px; cursor:pointer;"><i class="ph ph-save"></i> Save & Publish Exam</button>
            </div>
        </form>
    </div>
</div>
<?php require ROOT_PATH . '/app/Views/school_admin/layout/footer.php'; ?>
