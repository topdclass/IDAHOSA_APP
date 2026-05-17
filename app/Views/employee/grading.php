<?php
require_once ROOT_PATH . '/config/database.php';
$teacher_id = $_SESSION['user_id'] ?? 0;
$message = '';

function getGradeScale($total) {
    if($total >= 80) return ['A', 4.0, '#10b981'];
    if($total >= 70) return ['B', 3.0, '#3b82f6'];
    if($total >= 60) return ['C', 2.0, '#f59e0b'];
    if($total >= 50) return ['D', 1.0, '#f97316'];
    return ['F', 0.0, '#ef4444'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_grades'])) {
        $class_id = $_POST['class_id'];
        $subject_id = $_POST['subject_id'];
        $student_ids = $_POST['student_ids'] ?? [];
        $objective_scores = $_POST['objective_scores'] ?? [];
        $theory_scores = $_POST['theory_scores'] ?? [];
        $pdo->beginTransaction();
        try {
            foreach ($student_ids as $sid) {
                $obj = floatval($objective_scores[$sid] ?? 0);
                $thr = floatval($theory_scores[$sid] ?? 0);
                $total = $obj + $thr;
                $scale = getGradeScale($total);
                $grade = $scale[0];
                $point = $scale[1];
                $stmt = $pdo->prepare("INSERT INTO subject_grades (student_id, class_id, subject_id, teacher_id, objective_score, theory_score, total_score, grade, grade_point, status, term, academic_year) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending Approval', 'First Term', '2025/2026') ON DUPLICATE KEY UPDATE objective_score=VALUES(objective_score), theory_score=VALUES(theory_score), total_score=VALUES(total_score), grade=VALUES(grade), grade_point=VALUES(grade_point), status='Pending Approval'");
                $stmt->execute([$sid, $class_id, $subject_id, $teacher_id, $obj, $thr, $total, $grade, $point]);
            }
            $pdo->commit(); $message = "Intelligence synchronized: Academic performance recorded.";
        } catch (Exception $e) { $pdo->rollBack(); $message = "Error: " . $e->getMessage(); }
    }
}

// Data Retrieval
$assignedStmt = $pdo->prepare("SELECT cs.class_id, cs.subject_id, c.class_name, c.arm as section, s.subject_name FROM class_subjects cs JOIN classes c ON cs.class_id = c.id JOIN subjects s ON cs.subject_id = s.id WHERE cs.teacher_id = :tid AND cs.is_deleted = 0");
$assignedStmt->execute([':tid' => $teacher_id]);
$assignments = $assignedStmt->fetchAll(PDO::FETCH_ASSOC);

$selected_combined = $_GET['target'] ?? null;
$students = []; $gradeDist = ['A'=>0, 'B'=>0, 'C'=>0, 'D'=>0, 'F'=>0];
if ($selected_combined) {
    [$cid, $sid] = explode('-', $selected_combined);
    $stmt = $pdo->prepare("SELECT s.id, u.full_name, s.student_no, u.photo_url FROM institute_students s JOIN users u ON s.student_id = u.id WHERE s.class_id = ? AND s.is_deleted = 0 ORDER BY u.full_name ASC");
    $stmt->execute([$cid]);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt = $pdo->prepare("SELECT student_id, objective_score, theory_score, total_score, grade FROM subject_grades WHERE class_id=? AND subject_id=? AND academic_year='2025/2026'");
    $stmt->execute([$cid, $sid]);
    $grades = $stmt->fetchAll(PDO::FETCH_UNIQUE | PDO::FETCH_ASSOC);
    foreach($grades as $g) { if(isset($gradeDist[$g['grade']])) $gradeDist[$g['grade']]++; }
}

$pageTitle = 'Master Records - Exam Grading';
require ROOT_PATH . '/app/Views/employee/layout/header.php';
?>

<style>
    .grading-header { display:flex; justify-content:space-between; align-items:flex-end; margin-bottom:30px; }
    .nav-pills { display:flex; gap:10px; margin-bottom:25px; overflow-x:auto; padding-bottom:10px; }
    .pill-link { white-space:nowrap; padding:12px 20px; border-radius:12px; background:white; border:1px solid #f1f5f9; color:#64748b; font-weight:700; text-decoration:none; font-size:13px; transition:0.2s; }
    .pill-link:hover { color:var(--primary); background:#f8fafc; }
    .pill-link.active { background:var(--primary); color:white; border-color:var(--primary); box-shadow:0 8px 15px -3px rgba(79, 70, 229, 0.3); }

    .grading-grid { display:grid; grid-template-columns: 2fr 1fr; gap:30px; }
    .table-card { background:white; border-radius:24px; padding:30px; border:1px solid #f1f5f9; box-shadow:0 4px 15px rgba(0,0,0,0.02); }
    
    table { width:100%; border-collapse:collapse; }
    th { text-align:left; font-size:11px; color:#94a3b8; font-weight:800; text-transform:uppercase; padding:15px 10px; border-bottom:1px solid #f1f5f9; }
    td { padding:15px 10px; border-bottom:1px solid #f8fafc; font-size:14px; }
    
    .score-input { width:60px; padding:10px; border-radius:8px; border:1px solid #eef2f6; text-align:center; font-weight:700; background:#f8fafc; outline:none; }
    .score-input:focus { border-color:var(--primary); background:white; }
    
    .grade-badge { font-weight:900; padding:4px 10px; border-radius:6px; font-size:12px; }
    
    @media (max-width:1024px) { .grading-grid { grid-template-columns: 1fr; } }
</style>

<div class="grading-header">
    <div>
        <h1 style="font-size:24px; font-weight:900; color:#111827; margin:0 0 5px 0;">Performance Certification</h1>
        <p style="color:#64748b; font-size:14px; margin:0;">Record final session mastery points and certify student outcomes.</p>
    </div>
    <div style="background:white; padding:8px 15px; border-radius:12px; border:1px solid #f1f5f9; font-weight:800; font-size:12px; color:#64748b;">
        Session: 2025/2026 <i class="ph ph-calendar-check" style="margin-left:5px;"></i>
    </div>
</div>

<?php if($message): ?>
    <div style="background:#f0fdf4; color:#166534; padding:18px; border-radius:12px; margin-bottom:25px; border-left:4px solid #22c55e; font-weight:700;">
        <?= $message ?>
    </div>
<?php endif; ?>

<div class="nav-pills">
    <?php foreach($assignments as $a): 
        $comb = "{$a['class_id']}-{$a['subject_id']}";
    ?>
        <a href="?target=<?= $comb ?>" class="pill-link <?= $selected_combined === $comb ? 'active' : '' ?>">
            <?= htmlspecialchars($a['subject_name']) ?> <span style="opacity:0.6;">(<?= htmlspecialchars($a['class_name']) ?>)</span>
        </a>
    <?php endforeach; ?>
</div>

<?php if($selected_combined): ?>
    <div class="grading-grid">
        <div class="col-main">
            <div class="table-card" style="padding:0; overflow:hidden;">
                <div style="padding:25px 30px; border-bottom:1px solid #f1f5f9; display:flex; justify-content:space-between; align-items:center;">
                    <h3 style="margin:0; font-weight:900; font-size:16px;">Student Score Roster</h3>
                    <button type="submit" form="gradingForm" name="save_grades" style="background:var(--primary); color:white; border:none; padding:10px 25px; border-radius:10px; font-weight:800; cursor:pointer;">Push To Certification</button>
                </div>
                <form method="POST" id="gradingForm">
                    <input type="hidden" name="class_id" value="<?= $cid ?>">
                    <input type="hidden" name="subject_id" value="<?= $sid ?>">
                    <table>
                        <thead>
                            <tr>
                                <th>Student Identity</th>
                                <th style="width:100px;">Obj (100)</th>
                                <th style="width:100px;">Theory (100)</th>
                                <th style="width:80px;">Total</th>
                                <th style="width:60px;">Grade</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($students as $s): 
                                $g = $grades[$s['id']] ?? null;
                                $obj = $g['objective_score'] ?? 0;
                                $thr = $g['theory_score'] ?? 0;
                                $tot = $g['total_score'] ?? 0;
                                $scale = getGradeScale($tot);
                            ?>
                                <tr>
                                    <td>
                                        <div style="display:flex; align-items:center; gap:12px;">
                                            <input type="hidden" name="student_ids[]" value="<?= $s['id'] ?>">
                                            <div style="width:32px; height:32px; background:#f1f5f9; border-radius:8px; overflow:hidden;">
                                                <?php if($s['photo_url']): ?>
                                                    <img src="<?= WEB_ROOT . $s['photo_url'] ?>" style="width:100%; height:100%; object-fit:cover;">
                                                <?php else: ?>
                                                    <div style="width:100%; height:100%; display:flex; align-items:center; justify-content:center; color:#94a3b8;"><i class="ph ph-user"></i></div>
                                                <?php endif; ?>
                                            </div>
                                            <div>
                                                <div style="font-weight:700; color:#1e293b;"><?= htmlspecialchars($s['full_name']) ?></div>
                                                <div style="font-size:11px; color:#94a3b8;"><?= htmlspecialchars($s['student_no']) ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td><input type="number" name="objective_scores[<?= $s['id'] ?>]" value="<?= $obj ?>" class="score-input" step="0.1"></td>
                                    <td><input type="number" name="theory_scores[<?= $s['id'] ?>]" value="<?= $thr ?>" class="score-input" step="0.1"></td>
                                    <td style="font-weight:800; color:var(--primary);"><?= $tot ?></td>
                                    <td><span class="grade-badge" style="background:<?= $scale[2] ?>20; color:<?= $scale[2] ?>;"><?= $scale[0] ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </form>
            </div>
        </div>
        <div class="col-side">
            <div class="table-card">
                <h3 style="margin:0 0 25px 0; font-weight:900; font-size:15px;"><i class="ph ph-chart-bar" style="color:var(--primary);"></i> Mastery Curve</h3>
                <canvas id="gradeChart" height="300"></canvas>
                
                <hr style="margin:30px 0; border:0; border-top:1px solid #f1f5f9;">
                
                <div style="padding:20px; border-radius:16px; background:#f8fafc; border:1px solid #eef2f6;">
                    <h4 style="margin:0 0 10px 0; font-size:13px;">Grading Protocol</h4>
                    <ul style="margin:0; padding:0 0 0 15px; font-size:12px; color:#64748b; line-height:1.8;">
                        <li>A (Excellent Mastery): 80 - 100</li>
                        <li>B (V. Good): 70 - 79</li>
                        <li>C (Good Credit): 60 - 69</li>
                        <li>D (Pass): 50 - 59</li>
                        <li>F (Failed): 0 - 49</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
<?php else: ?>
    <div style="text-align:center; padding:100px 20px; background:white; border-radius:24px; border:1px dashed #cbd5e1; margin-top:20px;">
        <i class="ph ph-exam" style="font-size:64px; opacity:0.1; margin-bottom:20px; display:inline-block;"></i>
        <h3 style="margin:0; color:#475569;">Select a Subject to Begin Grading</h3>
        <p style="color:#94a3b8; font-size:14px; margin-top:8px;">Only verified assignments assigned to your credentials are listed.</p>
    </div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
<?php if($selected_combined): ?>
const ctx = document.getElementById('gradeChart').getContext('2d');
new Chart(ctx, {
    type: 'doughnut',
    data: {
        labels: ['A', 'B', 'C', 'D', 'F'],
        datasets: [{
            data: [<?= $gradeDist['A'] ?>, <?= $gradeDist['B'] ?>, <?= $gradeDist['C'] ?>, <?= $gradeDist['D'] ?>, <?= $gradeDist['F'] ?>],
            backgroundColor: ['#10b981', '#3b82f6', '#f59e0b', '#f97316', '#ef4444'],
            borderWidth: 0
        }]
    },
    options: {
        plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 6, font: { weight: '800', size: 11 } } } },
        cutout: '70%'
    }
});
<?php endif; ?>
</script>

<?php require ROOT_PATH . '/app/Views/employee/layout/footer.php'; ?>
