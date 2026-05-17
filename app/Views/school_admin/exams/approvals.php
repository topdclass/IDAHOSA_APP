<?php
require_once ROOT_PATH . '/config/database.php';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_results'])) {
    $class_id = $_POST['class_id'];
    $student_ids = $_POST['student_ids'] ?? [];
    $principal_comments = $_POST['principal_comments'] ?? [];

    $pdo->beginTransaction();
    try {
        foreach ($student_ids as $sid) {
            $cmt = trim($principal_comments[$sid] ?? '');
            
            // Update Report Card Comments (Principal Comment & Status)
            $stmt = $pdo->prepare("UPDATE report_card_comments SET principal_comment = ?, status = 'Published' WHERE student_id = ? AND class_id = ? AND term = 'First Term'");
            $stmt->execute([$cmt, $sid, $class_id]);

            // Update all subject grades to Approved
            $gStmt = $pdo->prepare("UPDATE subject_grades SET status = 'Approved' WHERE student_id = ? AND class_id = ? AND term = 'First Term' AND status = 'Pending Approval'");
            $gStmt->execute([$sid, $class_id]);
        }
        $pdo->commit();
        $message = "<div style='color:#10b981; font-weight:700;'>Results formally certified & published. They are now officially visible on Parent & Student portlets!</div>";
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = "<div style='color:#ef4444; font-weight:700;'>Error: " . $e->getMessage() . "</div>";
    }
}

// Fetch all classes for the filter
$classesStmt = $pdo->query("SELECT * FROM classes WHERE is_deleted = 0 ORDER BY numeric_value ASC");
$classes = $classesStmt->fetchAll(PDO::FETCH_ASSOC);
$selected_class = $_GET['class_id'] ?? ($classes[0]['id'] ?? null);

$students = [];
if ($selected_class) {
    // Determine students in this class who have pending or published results
    $stmt = $pdo->prepare("
        SELECT st.id, st.student_no, u.full_name as name, rc.class_teacher_comment, rc.principal_comment, rc.status as rc_status
        FROM institute_students st
        LEFT JOIN users u ON st.id = u.id
        LEFT JOIN report_card_comments rc ON rc.student_id = st.id AND rc.class_id = st.class_id AND rc.term = 'First Term'
        WHERE st.class_id = ? AND st.is_deleted = 0
    ");
    $stmt->execute([$selected_class]);
    $stData = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach($stData as $st) {
        $sid = $st['id'];

        // Get grades counts
        $gChk = $pdo->prepare("SELECT COUNT(*) as tot, SUM(CASE WHEN status='Pending Approval' THEN 1 ELSE 0 END) as pending FROM subject_grades WHERE student_id=?");
        $gChk->execute([$sid]);
        $sc = $gChk->fetch(PDO::FETCH_ASSOC);

        $st['total_subjects'] = $sc['tot'] ?? 0;
        $st['pending_subjects'] = $sc['pending'] ?? 0;
        
        $students[] = $st;
    }
}

$pageTitle = 'Result Certification - Rosmon SMS';
require ROOT_PATH . '/app/Views/school_admin/layout/header.php';
require ROOT_PATH . '/app/Views/school_admin/layout/sidebar.php';
?>

<div class="main-container">
    <div class="top-header">
        <div class="greeting">Exams / <span style="color:var(--primary)">Result Certification & Publishing</span></div>
        <div class="header-actions">
            <!-- Actions -->
        </div>
    </div>

    <div class="crud-card" style="margin-bottom:20px; border-left:4px solid #f59e0b;">
        <h2 style="margin:0 0 10px 0; color:var(--text);"><i class="ph ph-certificate"></i> Principal Authentication Node</h2>
        <p style="color:var(--text-muted); font-size:13px; line-height:1.6;">Review incoming aggregated grades from Subject Teachers. Submit your official Principal's Comment and officially certify the results. Publishing results cascades them permanently to the respective student and parent portals.</p>
    </div>

    <?php if($message) echo "<div style='margin-bottom:20px; padding:15px; background:white; border-radius:10px; border-left:4px solid var(--primary); box-shadow:0 2px 5px rgba(0,0,0,0.05);'>$message</div>"; ?>

    <div style="background:white; padding:20px; border-radius:15px; margin-bottom:20px; border:1px solid #e2e8f0;">
        <form method="GET" style="display:flex; gap:15px; align-items:flex-end;">
            <div style="flex:1;">
                <label style="font-size:11px; font-weight:700; color:var(--text-muted); margin-bottom:5px; display:block;">TARGET CLASS</label>
                <select name="class_id" style="width:100%; padding:10px; border-radius:8px; border:1px solid #ddd; font-weight:600;">
                    <?php foreach($classes as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $selected_class == $c['id'] ? 'selected' : '' ?>><?= $c['class_name'] ?> <?= !empty($c['section']) ? '('.$c['section'].')' : '' ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn-primary" style="padding:10px 20px; border-radius:8px; border:none; background:var(--primary); color:white; font-weight:700; cursor:pointer;">Load Roster</button>
        </form>
    </div>

    <form method="POST">
        <input type="hidden" name="class_id" value="<?= $selected_class ?>">
        <table class="crud-table" style="width:100%; background:white; margin-bottom:20px;">
            <thead>
                <tr>
                    <th style="width:15%;">STUDENT NAME</th>
                    <th style="width:15%;">SUBJECT LOG</th>
                    <th style="width:30%;">CLASS TEACHER NOTE</th>
                    <th style="width:30%;">PRINCIPAL REMARK</th>
                    <th style="width:10%;">STATUS</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($students)): ?>
                    <tr><td colspan="5" style="text-align:center; padding:30px; color:var(--text-muted);">No students in this class.</td></tr>
                <?php else: ?>
                    <?php foreach($students as $st): 
                        $isPub = ($st['rc_status'] === 'Published');
                    ?>
                    <tr>
                        <td style="font-weight:700; font-size:13px; color:var(--primary);">
                            <?= htmlspecialchars($st['name']) ?><br>
                            <span style="font-size:10px; color:var(--text-muted); font-weight:600;">ID: <?= $st['student_no'] ?? $st['id'] ?></span>
                            <input type="hidden" name="student_ids[]" value="<?= $st['id'] ?>">
                        </td>
                        <td>
                            <div style="font-size:11px; font-weight:700;"><span style="color:var(--primary);"><?= $st['total_subjects'] ?></span> SUBMITTED</div>
                            <?php if ($st['pending_subjects'] > 0): ?>
                                <div style="font-size:10px; color:#f59e0b; font-weight:800; background:#fef3c7; display:inline-block; padding:2px 6px; border-radius:4px; margin-top:5px;"><?= $st['pending_subjects'] ?> PENDING APPROVAL</div>
                            <?php else: ?>
                                <div style="font-size:10px; color:#10b981; font-weight:800; background:#dcfce7; display:inline-block; padding:2px 6px; border-radius:4px; margin-top:5px;">ALL CERTIFIED</div>
                            <?php endif; ?>
                        </td>
                        <td style="font-size:12px; color:var(--text-dark); background:#f8fafc; font-style:italic;">
                            <?= !empty($st['class_teacher_comment']) ? '"'.htmlspecialchars($st['class_teacher_comment']).'"' : '<span style="color:#94a3b8;">Awaiting entry...</span>' ?>
                        </td>
                        <td>
                            <textarea name="principal_comments[<?= $st['id'] ?>]" rows="2" <?= $isPub ? 'readonly' : '' ?> placeholder="Official principal remarks..." style="width:100%; padding:8px; border-radius:6px; border:1px solid #ddd; font-family:inherit; font-size:12px;"><?= htmlspecialchars($st['principal_comment'] ?? '') ?></textarea>
                        </td>
                        <td>
                            <span style="font-size:10px; font-weight:800; padding:4px 8px; border-radius:6px; background:<?= $isPub ? '#dcfce7; color:#10b981;' : '#fef3c7; color:#f59e0b;' ?>">
                                <?= strtoupper($st['rc_status'] ?? 'Draft') ?>
                            </span>
                            <?php if($isPub): ?>
                                <a href="<?= WEB_ROOT ?>/school-admin/exams/result-card?student_id=<?= $st['id'] ?>&class_id=<?= $selected_class ?>" target="_blank" style="display:block; margin-top:10px; font-size:11px; font-weight:700; color:white; background:var(--primary); text-align:center; padding:5px; border-radius:5px; text-decoration:none;"><i class="ph ph-file-pdf"></i> View PDF</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <?php if(!empty($students)): ?>
        <div style="text-align:right;">
            <button type="submit" name="approve_results" value="1" class="btn-primary" style="padding:15px 30px; border-radius:10px; background:#10b981; color:white; font-weight:800; border:none; cursor:pointer;" onclick="return confirm('WARNING: Publishing results locks all teacher edits and cascades the final report cards to Parents. Confirm integration?');"><i class="ph ph-seal-check"></i> Certify & Publish Batch</button>
        </div>
        <?php endif; ?>
    </form>
</div>

<?php require ROOT_PATH . '/app/Views/school_admin/layout/footer.php'; ?>
