<?php
require_once ROOT_PATH . '/config/database.php';
$schoolId = $_SESSION['school_id'] ?? 101;
$role = $_SESSION['role'] ?? 'teacher'; 

$message = '';

// Handle Adding a new Question to the Bank
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_question') {
    $subjectId = $_POST['subject_id'];
    $questionText = $_POST['question'];
    $marks = $_POST['marks'] ?? 1;
    $difficulty = $_POST['difficulty'] ?? 'Medium';
    
    // Insert Question
    $stmt = $pdo->prepare("INSERT INTO cbt_question_bank (subject_id, question, marks, difficulty) VALUES (?, ?, ?, ?)");
    if ($stmt->execute([$subjectId, $questionText, $marks, $difficulty])) {
        $questionId = $pdo->lastInsertId();
        
        // Insert Options
        $options = $_POST['options'] ?? [];
        $correctIndex = $_POST['correct_option'] ?? 0;
        
        $optStmt = $pdo->prepare("INSERT INTO cbt_options (question_id, option_text, is_correct) VALUES (?, ?, ?)");
        foreach ($options as $index => $optText) {
            if (trim($optText) !== '') {
                $isCorrect = ($index == $correctIndex) ? 1 : 0;
                $optStmt->execute([$questionId, $optText, $isCorrect]);
            }
        }
        $message = "Question added to bank successfully.";
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_exam') {
    // Check if exam builder was submitted
    $title = $_POST['exam_title'];
    $classId = $_POST['class_id'];
    $durationStr = $_POST['duration']; // minutes
    $startDate = $_POST['start_date'];
    $endDate = $_POST['end_date'];
    $selectedQuestions = $_POST['exam_questions'] ?? [];
    
    // Formatting duration for TIME column
    $durationFormatted = gmdate("H:i:s", $durationStr * 60);

    if (empty($selectedQuestions)) {
        $message = "Error: Please select at least one question for the exam.";
    } else {
        $questionsJson = json_encode($selectedQuestions);
        $stmt = $pdo->prepare("INSERT INTO assessments (title, class_id, start_date, end_date, duration, questions, institute_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
        if ($stmt->execute([$title, $classId, $startDate, $endDate, $durationFormatted, $questionsJson, $schoolId])) {
            $message = "Exam built and published successfully!";
        }
    }
}

// Fetch Subjects
$subjects = $pdo->query("SELECT * FROM subjects ORDER BY subject_name")->fetchAll(PDO::FETCH_ASSOC);

// Fetch Classes
$classes = $pdo->query("SELECT * FROM classes ORDER BY numeric_value")->fetchAll(PDO::FETCH_ASSOC);

// Fetch Recent Questions
$recentQs = $pdo->query("
    SELECT q.*, s.subject_name, (SELECT COUNT(*) FROM cbt_options o WHERE o.question_id = q.id) as option_count 
    FROM cbt_question_bank q 
    JOIN subjects s ON q.subject_id = s.id 
    ORDER BY q.id DESC LIMIT 15
")->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CBT Examiner - Academic</title>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <style>
        :root { --primary: #4338ca; --bg: #f8fafc; --text: #0f172a; --border: #e2e8f0; }
        * { box-sizing: border-box; font-family: 'Inter', sans-serif; margin: 0; padding: 0; }
        body { background: var(--bg); display: flex; color: var(--text); min-height: 100vh; }
        .sidebar { width: 260px; height: 100vh; background: #1e1b4b; color: white; padding: 20px; position: fixed; }
        .main { flex: 1; margin-left: 260px; padding: 30px; }
        .nav-link { display: flex; align-items: center; gap: 12px; padding: 12px; color: #a5b4fc; text-decoration: none; border-radius: 8px; margin-bottom: 5px; }
        .nav-link:hover, .nav-link.active { background: rgba(255,255,255,0.1); color: white; }
        .header { margin-bottom: 30px; border-bottom: 1px solid var(--border); padding-bottom: 15px; }
        
        .panel { background: white; padding: 25px; border-radius: 12px; border: 1px solid var(--border); box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); margin-bottom: 30px; }
        .btn-primary { background: var(--primary); color: white; border: none; padding: 10px 20px; border-radius: 6px; font-weight: 600; cursor: pointer; transition: 0.2s; font-size: 14px; }
        .btn-primary:hover { background: #3730a3; }
        
        .form-group { margin-bottom: 15px; }
        label { display: block; font-size: 13px; font-weight: 600; color: #475569; margin-bottom: 6px; }
        input[type="text"], input[type="number"], select, textarea { width: 100%; padding: 10px; border: 1px solid var(--border); border-radius: 6px; font-size: 14px; outline: none; }
        input:focus, select:focus, textarea:focus { border-color: var(--primary); }
        
        .option-row { display: flex; align-items: center; gap: 10px; margin-bottom: 10px; }
        .option-row input[type="radio"] { width: 18px; height: 18px; cursor: pointer; }
        
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid var(--border); }
        th { font-size: 12px; text-transform: uppercase; color: #64748b; background: #f8fafc; }
        td { font-size: 14px; font-weight: 500; }
        .success-msg { background: #dcfce3; color: #166534; padding: 12px; border-radius: 8px; margin-bottom: 20px; font-weight: 600; font-size: 14px; border: 1px solid #bbf7d0; }
        
        .badge { padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 700; text-transform: uppercase; }
        .badge-easy { background: #dcfce3; color: #166534; }
        .badge-medium { background: #fef9c3; color: #854d0e; }
        .badge-hard { background: #fee2e2; color: #991b1b; }
        
        /* Tabs */
        .tabs { display: flex; gap: 15px; margin-bottom: 25px; border-bottom: 2px solid var(--border); }
        .tab { padding: 10px 20px; font-weight: 600; color: #64748b; cursor: pointer; border-bottom: 2px solid transparent; margin-bottom: -2px; transition: 0.2s; }
        .tab:hover { color: var(--primary); }
        .tab.active { color: var(--primary); border-bottom-color: var(--primary); }
        
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        
        input[type="checkbox"] { width: 16px; height: 16px; accent-color: var(--primary); }
    </style>
    <script>
        function switchTab(tabId) {
            document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            
            document.querySelector(`[onclick="switchTab('${tabId}')"]`).classList.add('active');
            document.getElementById(tabId).classList.add('active');
        }
    </script>
</head>
<body>
    <div class="sidebar">
        <h2 style="font-size: 20px; margin-bottom: 30px;">ACADEMICS</h2>
        <a href="#" class="nav-link"><i class="ph ph-chalkboard-teacher"></i> Classes & Subjects</a>
        <a href="/academic/results-workflow" class="nav-link"><i class="ph ph-exam"></i> Result Approval</a>
        <a href="/academic/cbt-examiner" class="nav-link active"><i class="ph ph-desktop"></i> CBT Examiner</a>
        <div style="margin-top: auto; padding-top: 20px;">
            <a href="/" class="nav-link" style="color: #fda4af;"><i class="ph ph-sign-out"></i> Logout</a>
        </div>
    </div>

    <div class="main">
        <div class="header">
            <h1 style="font-size: 24px;">CBT Examination Portal</h1>
            <p style="color: #64748b; margin-top: 5px;">Build robust question banks and configure automated computer-based tests.</p>
        </div>

        <?php if($message): ?>
            <div class="success-msg"><i class="ph ph-check-circle"></i> <?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <div class="tabs">
            <div class="tab active" onclick="switchTab('question-bank')"><i class="ph ph-database"></i> Question Bank</div>
            <div class="tab" onclick="switchTab('exam-builder')"><i class="ph ph-pencil-line"></i> Exam Builder</div>
        </div>

        <div id="question-bank" class="tab-content active">
            <div style="display: flex; gap: 30px;">
                <div class="panel" style="flex: 1;">
                    <h3 style="margin-bottom: 20px;">Add Question to Bank</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="add_question">
                    
                    <div style="display: flex; gap: 15px;">
                        <div class="form-group" style="flex: 2;">
                            <label>Subject Topic</label>
                            <select name="subject_id" required>
                                <option value="">Select Subject...</option>
                                <?php foreach($subjects as $s): ?>
                                    <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['subject_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group" style="flex: 1;">
                            <label>Difficulty</label>
                            <select name="difficulty">
                                <option value="Easy">Easy</option>
                                <option value="Medium" selected>Medium</option>
                                <option value="Hard">Hard</option>
                            </select>
                        </div>
                        <div class="form-group" style="flex: 1;">
                            <label>Marks Weight</label>
                            <input type="number" name="marks" value="1" step="0.5" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Question Content</label>
                        <textarea name="question" rows="4" required placeholder="Type the objective question here..."></textarea>
                    </div>

                    <div class="form-group">
                        <label>Multiple Choice Options (Select the correct radio button)</label>
                        <div class="option-row">
                            <input type="radio" name="correct_option" value="0" required checked>
                            <input type="text" name="options[]" placeholder="Option A" required>
                        </div>
                        <div class="option-row">
                            <input type="radio" name="correct_option" value="1">
                            <input type="text" name="options[]" placeholder="Option B" required>
                        </div>
                        <div class="option-row">
                            <input type="radio" name="correct_option" value="2">
                            <input type="text" name="options[]" placeholder="Option C">
                        </div>
                        <div class="option-row">
                            <input type="radio" name="correct_option" value="3">
                            <input type="text" name="options[]" placeholder="Option D">
                        </div>
                    </div>

                    <button type="submit" class="btn-primary" style="width: 100%;"><i class="ph ph-plus-circle"></i> Save Question</button>
                </form>
            </div>

            <div class="panel" style="flex: 1;">
                <h3 style="margin-bottom: 20px;">Question Bank History</h3>
                <div style="max-height: 500px; overflow-y: auto;">
                    <?php if (empty($recentQs)): ?>
                        <div style="text-align: center; color: #94a3b8; padding: 20px;">Bank is currently empty.</div>
                    <?php else: ?>
                        <div style="display: flex; flex-direction: column; gap: 15px;">
                            <?php foreach($recentQs as $q): ?>
                                <div style="border: 1px solid var(--border); padding: 15px; border-radius: 8px;">
                                    <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                                        <span style="font-size: 12px; font-weight: 700; color: var(--primary);"><i class="ph ph-book-open"></i> <?= htmlspecialchars($q['subject_name']) ?></span>
                                        <span class="badge badge-<?= strtolower($q['difficulty']) ?>"><?= $q['difficulty'] ?> (<?= floatval($q['marks']) ?>m)</span>
                                    </div>
                                    <p style="font-size: 14px; font-weight: 500; color: var(--text); line-height: 1.5;"><?= htmlspecialchars($q['question']) ?></p>
                                    <div style="font-size: 12px; color: #64748b; margin-top: 10px;">
                                        <?= $q['option_count'] ?> Choices defined
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
          </div>
        </div>

        <!-- EXAM BUILDER TAB -->
        <div id="exam-builder" class="tab-content">
            <div class="panel">
                <h3 style="margin-bottom: 20px;">Create WAEC-Style Examination</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="create_exam">
                    
                    <div style="display: flex; gap: 20px;">
                        <div class="form-group" style="flex: 2;">
                            <label>Examination Title</label>
                            <input type="text" name="exam_title" required placeholder="e.g. 1st Term Mathematics Exam">
                        </div>
                        <div class="form-group" style="flex: 1;">
                            <label>Target Class</label>
                            <select name="class_id" required>
                                <option value="">Select Class...</option>
                                <?php foreach($classes as $c): ?>
                                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['class_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group" style="flex: 1;">
                            <label>Duration (Minutes)</label>
                            <input type="number" name="duration" required placeholder="e.g. 45" value="60">
                        </div>
                    </div>

                    <div style="display: flex; gap: 20px;">
                        <div class="form-group" style="flex: 1;">
                            <label>Start Date & Time (Opens)</label>
                            <input type="datetime-local" name="start_date" required>
                        </div>
                        <div class="form-group" style="flex: 1;">
                            <label>End Date & Time (Closes)</label>
                            <input type="datetime-local" name="end_date" required>
                        </div>
                    </div>

                    <div class="form-group" style="margin-top: 20px;">
                        <label style="font-size: 16px; margin-bottom: 10px; border-bottom: 1px solid var(--border); padding-bottom: 10px;">Select Questions from Bank</label>
                        
                        <div style="max-height: 400px; overflow-y: auto; border: 1px solid var(--border); border-radius: 8px; padding: 15px;">
                            <?php if (empty($recentQs)): ?>
                                <p style="color: #64748b; text-align: center;">No questions available in the bank. Please add some first.</p>
                            <?php else: ?>
                                <table style="width: 100%;">
                                    <thead style="position: sticky; top: 0; background: white; z-index: 10;">
                                        <tr>
                                            <th style="width: 40px;">Inc.</th>
                                            <th style="width: 120px;">Subject</th>
                                            <th>Question Preview</th>
                                            <th style="width: 80px;">Marks</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($recentQs as $q): ?>
                                            <tr>
                                                <td><input type="checkbox" name="exam_questions[]" value="<?= $q['id'] ?>"></td>
                                                <td><span class="badge" style="background:var(--primary); color:white;"><?= htmlspecialchars($q['subject_name']) ?></span></td>
                                                <td><?= htmlspecialchars((strlen($q['question']) > 80) ? substr($q['question'], 0, 80) . '...' : $q['question']) ?></td>
                                                <td><?= floatval($q['marks']) ?>m</td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div style="text-align: right; margin-top: 30px;">
                        <button type="submit" class="btn-primary" style="background:#10b981; padding: 14px 28px; font-size: 16px;"><i class="ph ph-check-circle"></i> Publish Exam</button>
                    </div>
                </form>
            </div>
        </div>

    </div>
</body>
</html>
