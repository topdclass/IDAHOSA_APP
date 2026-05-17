<?php
require_once ROOT_PATH . '/config/database.php';
$schoolId = $_SESSION['school_id'] ?? 101;
$studentId = $_SESSION['user_id'] ?? 2;

$examId = $_GET['id'] ?? null;
if (!$examId) {
    die("Error: No examination specified.");
}

// Fetch Exam
$stmt = $pdo->prepare("SELECT * FROM assessments WHERE id = ? AND institute_id = ?");
$stmt->execute([$examId, $schoolId]);
$exam = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$exam) {
    die("Examination not found.");
}

// Check if already taken
$stmt = $pdo->prepare("SELECT id, score FROM assessment_results WHERE student_id = ? AND assessment_id = ?");
$stmt->execute([$studentId, $examId]);
$resultLog = $stmt->fetch(PDO::FETCH_ASSOC);

if ($resultLog) {
    die("<h2>Examination Already Completed!</h2><p>Your score: {$resultLog['score']}</p>");
}

// Decode Questions Array
$qIds = json_decode($exam['questions'], true);
if (!is_array($qIds) || empty($qIds)) {
    die("This examination has no questions configured.");
}
$inQuery = implode(',', array_fill(0, count($qIds), '?'));

// Handle Submission & Auto-Grading
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_exam') {
    $score = 0;
    $studentAnswers = [];
    
    // Fetch all correct options for the questions in the exam
    $stmtOpt = $pdo->prepare("
        SELECT o.question_id, o.id, q.marks 
        FROM cbt_options o 
        JOIN cbt_question_bank q ON o.question_id = q.id 
        WHERE o.is_correct = 1 AND o.question_id IN ($inQuery)
    ");
    $stmtOpt->execute($qIds);
    $correctRefs = $stmtOpt->fetchAll(PDO::FETCH_ASSOC);
    
    // Organize correct answers mapping
    $answerKey = [];
    foreach ($correctRefs as $ref) {
        $answerKey[$ref['question_id']] = ['correct_opt' => $ref['id'], 'marks' => $ref['marks']];
    }
    
    foreach ($qIds as $qid) {
        $selectedOpt = $_POST['q_' . $qid] ?? null;
        $studentAnswers[$qid] = $selectedOpt; // Store what they picked
        
        if ($selectedOpt && isset($answerKey[$qid])) {
            if ($selectedOpt == $answerKey[$qid]['correct_opt']) {
                $score += $answerKey[$qid]['marks'];
            }
        }
    }
    
    // Insert Result
    $stmtInsert = $pdo->prepare("
        INSERT INTO assessment_results (student_id, institute_id, assessment_id, score, is_completed, answers) 
        VALUES (?, ?, ?, ?, 1, ?)
    ");
    $stmtInsert->execute([$studentId, $schoolId, $examId, $score, json_encode($studentAnswers)]);
    
    echo "
    <div style='font-family: Arial, sans-serif; text-align: center; margin-top: 100px; color: #0f172a;'>
        <img src='https://cdn-icons-png.flaticon.com/512/190/190411.png' width='80' style='margin-bottom: 20px;'/>
        <h1 style='color: #16a34a;'>Examination Submitted!</h1>
        <p style='font-size: 18px; color: #475569;'>Your test has been auto-graded accurately.</p>
        <p style='font-size: 24px; font-weight: bold; margin: 20px 0;'>Score: $score</p>
        <a href='/student/cbt-dashboard' style='display: inline-block; padding: 12px 24px; background: #0284c7; color: white; text-decoration: none; border-radius: 8px; font-weight: bold;'>Return to Portal</a>
    </div>";
    exit;
}

// Fetch Questions
$stmtQ = $pdo->prepare("SELECT * FROM cbt_question_bank WHERE id IN ($inQuery)");
$stmtQ->execute($qIds);
$questionsRaw = $stmtQ->fetchAll(PDO::FETCH_ASSOC);

// Fetch Options
$stmtO = $pdo->prepare("SELECT * FROM cbt_options WHERE question_id IN ($inQuery)");
$stmtO->execute($qIds);
$optionsRaw = $stmtO->fetchAll(PDO::FETCH_ASSOC);

// Group by Question ID
$quizData = [];
foreach ($questionsRaw as $q) {
    // Collect options for this question
    $opts = array_filter($optionsRaw, function($o) use ($q) { return $o['question_id'] == $q['id']; });
    // Reset keys for JS array predictability
    $optsFormatted = [];
    foreach($opts as $o) {
        $optsFormatted[] = [
            'id' => $o['id'],
            'text' => $o['option_text']
        ];
    }
    
    $quizData[] = [
        'id' => $q['id'],
        'question' => $q['question'],
        'marks' => $q['marks'],
        'options' => $optsFormatted
    ];
}

// Exam Time Config (H:i:s string from DB)
$timeParts = explode(':', $exam['duration']);
$totalSeconds = 0;
if (count($timeParts) == 3) {
    $totalSeconds = ($timeParts[0] * 3600) + ($timeParts[1] * 60) + $timeParts[2];
} else {
    $totalSeconds = 3600; // fallback 1hr
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Examination in Progress - <?= htmlspecialchars($exam['title']) ?></title>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <style>
        :root { --primary: #15803d; --bg: #f8fafc; --text: #0f172a; --border: #cbd5e1; }
        * { box-sizing: border-box; font-family: 'Inter', sans-serif; margin: 0; padding: 0; }
        body { background: #e2e8f0; color: var(--text); height: 100vh; overflow: hidden; display: flex; flex-direction: column; }
        
        .header { background: white; padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid var(--primary); box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); z-index: 10; }
        .school-name { font-size: 20px; font-weight: 800; color: #1e293b; text-transform: uppercase; letter-spacing: 1px; }
        .exam-title { font-size: 15px; color: #64748b; font-weight: 600; }
        
        .timer-box { background: #fee2e2; border: 2px solid #ef4444; color: #b91c1c; padding: 8px 20px; border-radius: 8px; font-size: 24px; font-weight: 800; display: flex; align-items: center; gap: 10px; font-family: monospace; }
        
        .main-workspace { display: flex; flex: 1; overflow: hidden; }
        
        /* Question Area */
        .question-stage { flex: 1; padding: 40px; overflow-y: auto; background: var(--bg); display: flex; flex-direction: column; }
        .question-box { background: white; padding: 30px; border-radius: 12px; border: 1px solid var(--border); box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); margin-bottom: 30px; }
        .question-number { font-size: 14px; font-weight: 700; color: #ef4444; margin-bottom: 15px; text-transform: uppercase; }
        .question-text { font-size: 20px; font-weight: 500; color: #1e293b; line-height: 1.6; margin-bottom: 30px; }
        
        .option-label { display: flex; align-items: flex-start; gap: 15px; padding: 15px; border: 2px solid var(--border); border-radius: 8px; margin-bottom: 15px; cursor: pointer; transition: 0.2s; background: white; font-size: 16px; font-weight: 500; }
        .option-label:hover { background: #f1f5f9; border-color: #94a3b8; }
        .option-label.selected { background: #dcfce3; border-color: var(--primary); }
        .option-label input[type="radio"] { width: 20px; height: 20px; accent-color: var(--primary); margin-top: 2px; }
        
        .control-panel { display: flex; justify-content: space-between; align-items: center; margin-top: auto; padding-top: 20px; }
        .btn { padding: 12px 24px; border-radius: 8px; font-size: 16px; font-weight: 700; cursor: pointer; border: none; transition: 0.2s; display: inline-flex; align-items: center; gap: 8px; }
        .btn-prev { background: #64748b; color: white; }
        .btn-next { background: #3b82f6; color: white; }
        .btn-submit { background: #ef4444; color: white; padding: 12px 30px; }
        .btn:hover { opacity: 0.9; transform: translateY(-2px); }
        .btn:disabled { opacity: 0.5; pointer-events: none; }
        
        /* Nav Grid */
        .nav-sidebar { width: 320px; background: white; border-left: 1px solid var(--border); padding: 20px; overflow-y: auto; display: flex; flex-direction: column; }
        .nav-header { font-size: 15px; font-weight: 700; margin-bottom: 20px; color: #334155; padding-bottom: 15px; border-bottom: 1px solid var(--border); text-align: center; }
        
        .grid-container { display: grid; grid-template-columns: repeat(5, 1fr); gap: 10px; }
        .grid-btn { background: #f1f5f9; border: 2px solid #cbd5e1; border-radius: 6px; padding: 10px 0; text-align: center; font-weight: 700; cursor: pointer; color: #475569; transition: 0.2s; }
        .grid-btn:hover { background: #e2e8f0; border-color: #94a3b8; }
        .grid-btn.active { border-color: var(--primary); background: white; color: var(--primary); box-shadow: 0 0 0 2px rgba(21,128,61,0.2); }
        .grid-btn.answered { background: var(--primary); border-color: var(--primary); color: white; }
        
        .legend { margin-top: auto; padding-top: 20px; border-top: 1px solid var(--border); display: flex; flex-direction: column; gap: 10px; font-size: 12px; font-weight: 600; color: #64748b; }
        .legend-item { display: flex; align-items: center; gap: 10px; }
        .legend-box { width: 16px; height: 16px; border-radius: 4px; }
    </style>
</head>
<body>

    <form id="examForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="submit_exam">
        <!-- Input fields will be dynamically injected here by JS before submit -->
    </form>

    <div class="header">
        <div>
            <div class="school-name">ROSMON ACADEMY CBT</div>
            <div class="exam-title"><?= htmlspecialchars($exam['title']) ?></div>
        </div>
        <div class="timer-box" id="timerDisplay">
            <i class="ph ph-clock"></i> 00:00:00
        </div>
    </div>

    <div class="main-workspace">
        <div class="question-stage">
            <div class="question-box">
                <div class="question-number" id="qNumberBadge">Question 1 of N</div>
                <div class="question-text" id="qText">Loading question content...</div>
                
                <div id="optionsContainer">
                    <!-- Dynamic Options -->
                </div>
            </div>
            
            <div class="control-panel">
                <button class="btn btn-prev" id="btnPrev" onclick="navigate(-1)"><i class="ph ph-caret-left"></i> Previous</button>
                <div style="flex:1; text-align:center;">
                    <button class="btn btn-submit" onclick="confirmSubmit()"><i class="ph ph-paper-plane-tilt"></i> Submit Exam</button>
                </div>
                <button class="btn btn-next" id="btnNext" onclick="navigate(1)">Next <i class="ph ph-caret-right"></i></button>
            </div>
        </div>

        <div class="nav-sidebar">
            <div class="nav-header">EXAM NAVIGATION MAP</div>
            
            <div class="grid-container" id="navGrid">
                <!-- Dynamic Grid -->
            </div>
            
            <div class="legend">
                <div class="legend-item"><div class="legend-box" style="background:var(--primary);"></div> Answered</div>
                <div class="legend-item"><div class="legend-box" style="background:#f1f5f9; border:1px solid #cbd5e1;"></div> Unanswered</div>
                <div class="legend-item"><div class="legend-box" style="background:white; border:2px solid var(--primary);"></div> Current Question</div>
            </div>
        </div>
    </div>

    <script>
        const quizData = <?= json_encode($quizData) ?>;
        const totalQuestions = quizData.length;
        let currentIndex = 0;
        let studentAnswers = {}; // Mapping of question_id -> option_id
        
        let timeLeft = <?= $totalSeconds ?>; // In seconds

        // Initialize UI
        function initExam() {
            buildNavGrid();
            renderQuestion(0);
            startTimer();
        }

        function buildNavGrid() {
            const grid = document.getElementById('navGrid');
            grid.innerHTML = '';
            for(let i = 0; i < totalQuestions; i++) {
                const btn = document.createElement('div');
                btn.className = 'grid-btn';
                btn.id = 'gridBtn_' + i;
                btn.innerText = (i + 1);
                btn.onclick = () => renderQuestion(i);
                grid.appendChild(btn);
            }
        }

        function renderQuestion(index) {
            if (index < 0 || index >= totalQuestions) return;
            
            // Save current choice before moving visually (handled on click, but safe)
            
            currentIndex = index;
            const q = quizData[currentIndex];
            
            // Update UI text
            document.getElementById('qNumberBadge').innerText = `Question ${currentIndex + 1} of ${totalQuestions} [${q.marks} Marks]`;
            document.getElementById('qText').innerText = q.question;
            
            // Build Options
            const optsContainer = document.getElementById('optionsContainer');
            optsContainer.innerHTML = '';
            
            q.options.forEach((opt, idx) => {
                const isSelected = studentAnswers[q.id] == opt.id;
                
                const label = document.createElement('label');
                label.className = `option-label ${isSelected ? 'selected' : ''}`;
                
                const radio = document.createElement('input');
                radio.type = 'radio';
                radio.name = `question_${q.id}`;
                radio.value = opt.id;
                radio.checked = isSelected;
                
                radio.onchange = (e) => {
                    studentAnswers[q.id] = e.target.value;
                    document.querySelectorAll('#optionsContainer .option-label').forEach(l => l.classList.remove('selected'));
                    label.classList.add('selected');
                    updateGridStatus();
                };
                
                const charLabel = String.fromCharCode(65 + idx) + "."; // A, B, C...
                label.appendChild(radio);
                label.append(` ${charLabel} ${opt.text}`);
                
                optsContainer.appendChild(label);
            });
            
            // Update Controls
            document.getElementById('btnPrev').disabled = (currentIndex === 0);
            document.getElementById('btnNext').disabled = (currentIndex === totalQuestions - 1);
            
            updateGridStatus();
        }

        function navigate(dir) {
            renderQuestion(currentIndex + dir);
        }

        function updateGridStatus() {
            for(let i = 0; i < totalQuestions; i++) {
                const qId = quizData[i].id;
                const btn = document.getElementById('gridBtn_' + i);
                
                btn.classList.remove('active', 'answered');
                
                if (studentAnswers[qId]) {
                    btn.classList.add('answered');
                }
                
                if (i === currentIndex) {
                    btn.classList.add('active');
                }
            }
        }

        function confirmSubmit() {
            const answeredCount = Object.keys(studentAnswers).length;
            if (answeredCount < totalQuestions) {
                if(!confirm(`You have only answered ${answeredCount} out of ${totalQuestions} questions. Are you sure you want to submit?`)) return;
            } else {
                if(!confirm("Are you sure you want to finish and submit your exam?")) return;
            }
            executeSubmit();
        }

        function executeSubmit() {
            const form = document.getElementById('examForm');
            // Inject answers
            for (const [qid, optId] of Object.entries(studentAnswers)) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = `q_${qid}`;
                input.value = optId;
                form.appendChild(input);
            }
            form.submit();
        }

        function startTimer() {
            const display = document.getElementById('timerDisplay');
            
            const interval = setInterval(() => {
                if (timeLeft <= 0) {
                    clearInterval(interval);
                    alert("Time is UP! Your answers will be automatically submitted.");
                    executeSubmit();
                    return;
                }
                
                timeLeft--;
                
                const h = Math.floor(timeLeft / 3600).toString().padStart(2, '0');
                const m = Math.floor((timeLeft % 3600) / 60).toString().padStart(2, '0');
                const s = (timeLeft % 60).toString().padStart(2, '0');
                
                display.innerHTML = `<i class="ph ph-clock"></i> ${h}:${m}:${s}`;
                
                // Red warning last 5 minutes
                if (timeLeft < 300) {
                    display.style.animation = "pulse 1s infinite alternate";
                }
            }, 1000);
        }

        // Boot
        window.onload = initExam;
    </script>
    <style>
        @keyframes pulse {
            from { background: #fee2e2; }
            to { background: #fca5a5; }
        }
    </style>
</body>
</html>
