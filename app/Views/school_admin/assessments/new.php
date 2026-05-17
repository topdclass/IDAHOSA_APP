<?php
// Gradebook / Mark Entry Core Logic

try {
    
    $message = '';
    $selected_class = $_GET['class_id'] ?? null;
    $selected_subject = $_GET['subject_id'] ?? null;
    $selected_assessment = $_GET['assessment_id'] ?? null;

    // Handle Bulk Mark Entry
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_marks'])) {
        $marks = $_POST['marks'] ?? [];
        $remarks = $_POST['remarks'] ?? [];

        foreach($marks as $student_id => $val) {
            if ($val === '') continue; // Skip empty
            $stmt = $pdo->prepare("INSERT INTO student_marks (student_id, assessment_id, marks_obtained, remarks) 
                                   VALUES (?, ?, ?, ?) 
                                   ON DUPLICATE KEY UPDATE marks_obtained=VALUES(marks_obtained), remarks=VALUES(remarks)");
            $stmt->execute([$student_id, $selected_assessment, $val, $remarks[$student_id] ?? '']);
        }
        $message = "Marksheet synchronized successfully!";
    }

    // Fetch Classes
    $classes = $pdo->query("SELECT * FROM classes")->fetchAll(PDO::FETCH_ASSOC);

    // Fetch Subjects if class selected
    $subjects = [];
    if ($selected_class) {
        $stmt = $pdo->prepare("SELECT s.* FROM subjects s JOIN class_subjects cs ON s.id = cs.subject_id WHERE cs.class_id = ?");
        $stmt->execute([$selected_class]);
        $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Fetch Assessments
    $assessments = $pdo->query("SELECT a.*, ag.name as group_name FROM grade_assessments a JOIN assessment_groups ag ON a.group_id = ag.id ORDER BY ag.name")->fetchAll(PDO::FETCH_ASSOC);

    // Fetch Students & Marks if all selected
    $student_list = [];
    if ($selected_class && $selected_assessment) {
        $stmt = $pdo->prepare("SELECT s.id as student_id, u.full_name, s.student_no as admission_number, sm.marks_obtained, sm.remarks 
                                FROM institute_students s 
                                JOIN users u ON s.student_id = u.id 
                                LEFT JOIN student_marks sm ON s.id = sm.student_id AND sm.assessment_id = ? 
                                WHERE s.class_id = ? 
                                ORDER BY u.full_name ASC");
        $stmt->execute([$selected_assessment, $selected_class]);
        $student_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

} catch (PDOException $e) {
    die("Gradebook Error: " . $e->getMessage());
}

$pageTitle = 'Master Marksheet - Rosmon SMS';
require ROOT_PATH . '/app/Views/school_admin/layout/header.php';
require ROOT_PATH . '/app/Views/school_admin/layout/sidebar.php';
?>

<div class="main-container">
    <div class="top-header">
        <div class="greeting">Academics / <span style="color:var(--primary)">Institutional Gradebook</span></div>
        <div class="header-actions">
            <a href="<?= WEB_ROOT ?>/school-admin/assessments/new" class="btn-primary" style="background:#f3f4f6; color:var(--text-dark); text-decoration:none;"><i class="ph ph-list"></i> Setup Assessments</a>
        </div>
    </div>

    <!-- Filters -->
    <div class="crud-card" style="margin-bottom:24px;">
        <form method="GET" style="display:grid; grid-template-columns: repeat(3, 1fr) auto; gap:16px; align-items:flex-end; padding:20px;">
            <div>
                <label style="display:block; font-size:11px; font-weight:700; margin-bottom:5px;">CLASS</label>
                <select name="class_id" onchange="this.form.submit()" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:8px; font-size:13px;">
                    <option value="">-- Choose Class --</option>
                    <?php foreach($classes as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $selected_class == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string)($c['class_name'] ?? '')) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label style="display:block; font-size:11px; font-weight:700; margin-bottom:5px;">SUBJECT</label>
                <select name="subject_id" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:8px; font-size:13px;">
                    <option value="">-- Choose Subject --</option>
                    <?php foreach($subjects as $s): ?>
                        <option value="<?= $s['id'] ?>" <?= $selected_subject == $s['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string)($s['subject_name'] ?? '')) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label style="display:block; font-size:11px; font-weight:700; margin-bottom:5px;">ASSESSMENT COMPONENT</label>
                <select name="assessment_id" onchange="this.form.submit()" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:8px; font-size:13px;">
                    <option value="">-- Choose Component --</option>
                    <?php foreach($assessments as $a): ?>
                        <option value="<?= $a['id'] ?>" <?= $selected_assessment == $a['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string)($a['group_name'] ?? '')) ?>: <?= htmlspecialchars((string)($a['assessment_name'] ?? '')) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn-primary" style="padding:10px 24px;">Load Marksheet</button>
        </form>
    </div>

    <?php if ($selected_class && $selected_assessment): ?>
    <div class="crud-card">
        <form method="POST">
            <div class="crud-header">
                <h2 class="crud-title">Active Marksheet Mapping</h2>
                <div style="font-size:12px; color:var(--primary); font-weight:800;"><?= $message ?></div>
            </div>

            <table class="crud-table">
                <thead>
                    <tr>
                        <th style="width:100px;">ADMISSION #</th>
                        <th>STUDENT NAME</th>
                        <th style="width:150px;">MARKS OBTAINED</th>
                        <th>REMARKS / FEEDBACK</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($student_list as $student): ?>
                        <tr>
                            <td style="font-weight:700; color:var(--text-muted);"><?= htmlspecialchars((string)($student['admission_number'] ?? '')) ?></td>
                            <td style="font-weight:700;"><?= htmlspecialchars((string)($student['full_name'] ?? '')) ?></td>
                            <td>
                                <input type="number" step="0.5" 
                                    name="marks[<?= $student['student_id'] ?>]" 
                                    value="<?= $student['marks_obtained'] ?>"
                                    placeholder="/100"
                                    style="width:100%; padding:8px; border:1px solid #ddd; border-radius:8px; text-align:center; font-weight:700;">
                            </td>
                            <td>
                                <input type="text" 
                                    name="remarks[<?= $student['student_id'] ?>]" 
                                    value="<?= htmlspecialchars((string)($student['remarks'] ?? '')) ?>"
                                    placeholder="e.g. Excellent performance"
                                    style="width:100%; padding:8px; border:1px solid #ddd; border-radius:8px; font-size:13px;">
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div style="padding:20px; text-align:right; border-top:1px solid #f3f4f6;">
                <input type="hidden" name="save_marks" value="1">
                <button type="submit" class="btn-primary" style="padding:12px 40px; border-radius:12px; font-weight:800; background:linear-gradient(135deg, var(--primary), #4f46e5);"><i class="ph ph-check-circle"></i> Commit Grades to Record</button>
            </div>
        </form>
    </div>
    <?php elseif ($selected_class): ?>
        <div class="crud-card" style="text-align:center; padding:60px;">
            <i class="ph ph-info" style="font-size:48px; color:var(--primary-light);"></i>
            <h3 style="margin-top:20px;">Component Selection Required</h3>
            <p style="color:var(--text-muted);">Please select an Assessment Component from the dropdown above to load the marksheet for this class.</p>
        </div>
    <?php else: ?>
        <div class="crud-card" style="text-align:center; padding:80px;">
            <i class="ph ph-folders" style="font-size:64px; color:var(--primary-light);"></i>
            <h3 style="margin-top:20px;">Virtual Gradebook Loader</h3>
            <p style="color:var(--text-muted);">Synchronize academic results in real-time. Start by selecting a Class and Assessment component from the filters above.</p>
        </div>
    <?php endif; ?>
</div>

<?php require ROOT_PATH . '/app/Views/school_admin/layout/footer.php'; ?>
