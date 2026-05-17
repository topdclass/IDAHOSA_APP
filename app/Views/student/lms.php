<?php
require_once ROOT_PATH . '/config/database.php';
$me = $_SESSION['user_id'] ?? 0;

// Auto-initialize Schema if not exists (Multi-tenant safety)
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS lesson_notes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        subject_id INT NOT NULL,
        teacher_id INT NOT NULL,
        week_number INT NOT NULL,
        topic VARCHAR(255),
        content LONGTEXT,
        status ENUM('Pending', 'Approved', 'Disapproved') DEFAULT 'Pending',
        admin_remark TEXT,
        created_by_name VARCHAR(150),
        updated_by_name VARCHAR(150),
        created_year YEAR,
        updated_year YEAR,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS theory_questions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        lesson_note_id INT NOT NULL,
        subject_id INT NOT NULL,
        question_text TEXT NOT NULL,
        option_a VARCHAR(255),
        option_b VARCHAR(255),
        option_c VARCHAR(255),
        option_d VARCHAR(255),
        correct_option CHAR(1) DEFAULT 'A',
        section_label VARCHAR(50) DEFAULT 'A',
        difficulty ENUM('Easy', 'Medium', 'Hard') DEFAULT 'Medium',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS student_subject_progress (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        subject_id INT NOT NULL,
        current_week INT DEFAULT 1,
        last_completed_at TIMESTAMP NULL,
        UNIQUE KEY `student_subject` (`student_id`, `subject_id`)
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS student_quiz_attempts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        lesson_note_id INT NOT NULL,
        score INT NOT NULL,
        total_questions INT NOT NULL,
        passed TINYINT(1) DEFAULT 0,
        attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
} catch (Exception $e) {}

// 1. Fetch Student/Class Info
$stmt = $pdo->prepare("SELECT id, class_id FROM institute_students WHERE student_id = ? LIMIT 1");
$stmt->execute([$me]);
$student = $stmt->fetch();
$class_id = $student['class_id'] ?? 0;

$message = '';
$error = '';
$quiz_result = null;

// 2. Handle Quiz Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_quiz'])) {
    $note_id = (int)$_POST['note_id'];
    $subject_id = (int)$_POST['subject_id'];
    $answers = $_POST['answers'] ?? [];
    $total = count($answers);
    
    // Fetch correct answers
    $q_ids = array_keys($answers);
    $placeholders = str_repeat('?,', count($q_ids) - 1) . '?';
    $qStmt = $pdo->prepare("SELECT id, correct_option FROM theory_questions WHERE id IN ($placeholders)");
    $qStmt->execute($q_ids);
    $correct_map = $qStmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    $score = 0;
    foreach ($answers as $qid => $choice) {
        if (isset($correct_map[$qid]) && $correct_map[$qid] === $choice) {
            $score++;
        }
    }
    
    $passThreshold = ($total > 0) ? ceil(0.8 * $total) : 0;
    $passed = ($total > 0 && $score >= $passThreshold);
    
    // Save attempt
    $stmt = $pdo->prepare("INSERT INTO student_quiz_attempts (student_id, lesson_note_id, score, total_questions, passed) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$me, $note_id, $score, $total, $passed ? 1 : 0]);
    
    if ($passed) {
        // Move to next week
        $pdo->prepare("INSERT INTO student_subject_progress (student_id, subject_id, current_week, last_completed_at) 
                       VALUES (?, ?, 2, CURRENT_TIMESTAMP) 
                       ON DUPLICATE KEY UPDATE current_week = current_week + 1, last_completed_at = CURRENT_TIMESTAMP")
            ->execute([$me, $subject_id]);
        $message = "Congratulations! You passed with $score/$total. Week unlocked.";
    } else {
        $error = "You scored $score/$total. You need at least $passThreshold/$total to progress to the next week. Please review the material and try again.";
    }
    $quiz_result = ['score' => $score, 'passed' => $passed, 'total' => $total, 'threshold' => $passThreshold];
}

// 3. Fetch Subjects and Progress
$stmt = $pdo->prepare("
    SELECT s.id, s.subject_name, p.current_week 
    FROM class_subjects cs
    JOIN subjects s ON cs.subject_id = s.id
    LEFT JOIN student_subject_progress p ON (p.student_id = ? AND p.subject_id = s.id)
    WHERE cs.class_id = ? AND cs.is_deleted = 0
");
$stmt->execute([$me, $class_id]);
$subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

$selected_subject = $_GET['subject'] ?? null;
$lesson = null;
$questions = [];

if ($selected_subject) {
    // Find current week for this subject
    $curr_week = 1;
    foreach ($subjects as $s) if ($s['id'] == $selected_subject) $curr_week = $s['current_week'] ?? 1;
    
    // Fetch approved lesson for this week
    $lStmt = $pdo->prepare("SELECT * FROM lesson_notes WHERE subject_id = ? AND week_number = ? AND status = 'Approved' LIMIT 1");
    $lStmt->execute([$selected_subject, $curr_week]);
    $lesson = $lStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($lesson && isset($_GET['take_quiz'])) {
        // Fetch 5 random questions
        $qStmt = $pdo->prepare("SELECT * FROM theory_questions WHERE lesson_note_id = ? ORDER BY RAND() LIMIT 5");
        $qStmt->execute([$lesson['id']]);
        $questions = $qStmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

$pageTitle = 'LMS - Learning Management';
// Note: We'll include the header logic directly since we don't have a layout file
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <style>
        :root { --primary: #4F46E5; --bg: #F8FAFC; --card: #FFFFFF; --text: #1E293B; --border: #E2E8F0; }
        body { margin: 0; font-family: 'Outfit', sans-serif; background: var(--bg); color: var(--text); padding: 40px; }
        .grid { display: grid; grid-template-columns: 280px 1fr; gap: 30px; max-width: 1200px; margin: 0 auto; }
        .card { background: var(--card); border-radius: 20px; padding: 25px; border: 1px solid var(--border); box-shadow: 0 4px 15px rgba(0,0,0,0.02); }
        .sub-btn { display: flex; align-items: center; gap: 12px; padding: 15px; border-radius: 12px; text-decoration: none; color: #64748B; font-weight: 600; margin-bottom: 10px; transition: 0.2s; border: 1px solid transparent; }
        .sub-btn:hover { background: #EEF2FF; color: var(--primary); }
        .sub-btn.active { background: var(--primary); color: white; }
        .week-badge { background: #F1F5F9; padding: 4px 10px; border-radius: 20px; font-size: 10px; margin-left: auto; color: #475569; }
        .btn-lms { background: var(--primary); color: white; border: none; padding: 12px 25px; border-radius: 10px; font-weight: 700; cursor: pointer; transition: 0.2s; text-decoration: none; display: inline-block; }
        .btn-lms:hover { opacity: 0.9; transform: translateY(-1px); }
        .q-block { margin-bottom: 25px; border-bottom: 1px solid #f1f5f9; padding-bottom: 15px; }
        .opt { display: block; margin: 10px 0; cursor: pointer; font-size: 14px; }
        #note-viewer { line-height: 1.7; font-size: 15px; }
    </style>
</head>
<body>

<div style="margin-bottom: 30px; display:flex; justify-content:space-between; align-items:center;">
    <div>
        <h1 style="margin:0; font-weight:800;">LMS Learning Hub</h1>
        <p style="color:#64748B; margin:5px 0 0 0;">Master your subjects week-by-week.</p>
    </div>
    <a href="<?= WEB_ROOT ?>/student/dashboard" style="color:var(--primary); font-weight:700; text-decoration:none;"><i class="ph ph-arrow-left"></i> Return to Dashboard</a>
</div>

<div class="grid">
    <!-- Left: Subject List -->
    <div class="card">
        <h3 style="margin-top:0;">My Subjects</h3>
        <?php foreach($subjects as $s): ?>
            <a href="?subject=<?= $s['id'] ?>" class="sub-btn <?= $selected_subject == $s['id'] ? 'active' : '' ?>">
                <i class="ph ph-book-open"></i>
                <?= htmlspecialchars($s['subject_name']) ?>
                <span class="week-badge">Wk <?= $s['current_week'] ?? 1 ?></span>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- Right: Content Area -->
    <div class="card">
        <?php if ($message): ?>
            <div style="background:#DCFCE7; color:#15803D; padding:15px; border-radius:12px; margin-bottom:20px; border-left:4px solid #10B981;"><?= $message ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div style="background:#FEE2E2; color:#B91C1C; padding:15px; border-radius:12px; margin-bottom:20px; border-left:4px solid #EF4444;"><?= $error ?></div>
        <?php endif; ?>

        <?php if (!$selected_subject): ?>
            <div style="text-align:center; padding:60px 20px; color:#94A3B8;">
                <i class="ph ph-student" style="font-size:64px; opacity:0.2; margin-bottom:20px;"></i>
                <p>Select a subject on the left to view your lesson notes and take assessments.</p>
            </div>
        <?php elseif (!$lesson): ?>
            <div style="text-align:center; padding:60px 20px; color:#94A3B8;">
                <i class="ph ph-clock" style="font-size:64px; opacity:0.2; margin-bottom:20px;"></i>
                <h3>No Content Published Yet</h3>
                <p>The lesson note for Week <?= $curr_week ?> is not yet published or approved by the admin.</p>
            </div>
        <?php elseif (isset($_GET['take_quiz']) && !empty($questions)): ?>
            <!-- Quiz View -->
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:25px;">
                <h2 style="margin:0;">Weekly Assessment (Week <?= $lesson['week_number'] ?>)</h2>
                <div style="background:#FEF3C7; color:#B45309; padding:5px 12px; border-radius:8px; font-size:12px; font-weight:800;">PASS SCORE: <?= ceil(0.8 * count($questions)) ?>/<?= count($questions) ?></div>
            </div>
            <form method="POST">
                <input type="hidden" name="note_id" value="<?= $lesson['id'] ?>">
                <input type="hidden" name="subject_id" value="<?= $lesson['subject_id'] ?>">
                <?php foreach($questions as $index => $q): ?>
                    <div class="q-block">
                        <p style="font-weight:700;"><?= $index+1 ?>. <?= htmlspecialchars($q['question_text']) ?></p>
                        <label class="opt"><input type="radio" name="answers[<?= $q['id'] ?>]" value="A" required> A) <?= htmlspecialchars($q['option_a']) ?></label>
                        <label class="opt"><input type="radio" name="answers[<?= $q['id'] ?>]" value="B"> B) <?= htmlspecialchars($q['option_b']) ?></label>
                        <label class="opt"><input type="radio" name="answers[<?= $q['id'] ?>]" value="C"> C) <?= htmlspecialchars($q['option_c']) ?></label>
                        <label class="opt"><input type="radio" name="answers[<?= $q['id'] ?>]" value="D"> D) <?= htmlspecialchars($q['option_d']) ?></label>
                    </div>
                <?php endforeach; ?>
                <div style="margin-top:30px;">
                    <button type="submit" name="submit_quiz" class="btn-lms" style="width:100%; padding:18px;">Submit Assessment</button>
                    <p style="text-align:center; font-size:12px; color:#94A3B8; margin-top:15px;">Warning: You cannot undo your answers after submission.</p>
                </div>
            </form>
        <?php else: ?>
            <!-- Lesson View -->
            <div style="display:flex; justify-content:space-between; align-items:flex-end; margin-bottom:30px; border-bottom:1px solid #f1f5f9; padding-bottom:20px;">
                <div>
                    <span style="color:var(--primary); font-weight:800; font-size:12px; text-transform:uppercase;">Week <?= $lesson['week_number'] ?></span>
                    <h1 style="margin:8px 0 0 0;"><?= htmlspecialchars($lesson['topic']) ?></h1>
                </div>
                <div style="display:flex; gap:10px;">
                    <button class="btn-lms" style="background:#F1F5F9; color:#475569;" onclick="window.print()"><i class="ph ph-download"></i> Download Note</button>
                    <a href="?subject=<?= $selected_subject ?>&take_quiz=1" class="btn-lms"><i class="ph ph-pencil"></i> Take Assessment</a>
                </div>
            </div>
            <div id="note-viewer">
                <?= nl2br(htmlspecialchars($lesson['content'])) ?>
            </div>
        <?php endif; ?>
    </div>
</div>

</body>
</html>
