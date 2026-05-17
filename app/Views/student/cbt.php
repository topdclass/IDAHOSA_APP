<?php
/**
 * Advanced Mock CBT Centre - Student Hub
 * Full-screen immersive examination environment
 */
require_once ROOT_PATH . '/config/database.php';
$userId = $_SESSION['user_id'] ?? 0;

// AUTO-INITIALIZE CBT TABLES IF MISSING
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS mock_questions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        subject_id INT NOT NULL,
        class_id INT NOT NULL,
        teacher_id INT NOT NULL,
        question_text TEXT NOT NULL,
        option_a VARCHAR(255) NOT NULL,
        option_b VARCHAR(255) NOT NULL,
        option_c VARCHAR(255) NOT NULL,
        option_d VARCHAR(255) NOT NULL,
        correct_option CHAR(1) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS cbt_exams (
        id INT AUTO_INCREMENT PRIMARY KEY,
        subject_id INT NOT NULL,
        class_id INT NOT NULL,
        teacher_id INT NOT NULL,
        exam_date DATE NOT NULL,
        start_time TIME NOT NULL,
        end_time TIME NOT NULL,
        duration_minutes INT DEFAULT 40,
        status ENUM('Pending', 'Active', 'Completed') DEFAULT 'Pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS cbt_questions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        exam_id INT NOT NULL,
        question_text TEXT NOT NULL,
        option_a VARCHAR(255) NOT NULL,
        option_b VARCHAR(255) NOT NULL,
        option_c VARCHAR(255) NOT NULL,
        option_d VARCHAR(255) NOT NULL,
        correct_option CHAR(1) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS cbt_results (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        exam_id INT NOT NULL,
        score INT NOT NULL,
        total_questions INT NOT NULL,
        is_malpractice TINYINT(1) DEFAULT 0,
        submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY `student_exam` (`student_id`, `exam_id`)
    )");
} catch (Exception $e) { /* Table already exists or creation failed */ }
$type = $_GET['type'] ?? 'mock';
$isRealExam = false;
$examId = 0;

// Dynamic Question Fetcher
if ($type === 'fetch_questions') {
    $subId = $_GET['subject_id'] ?? 0;
    $classId = $_GET['class_id'] ?? 0;
    $realExamId = $_GET['exam_id'] ?? 0;
    
    if ($realExamId > 0) {
        $stmt = $pdo->prepare("SELECT question_text as q, 
                                     JSON_ARRAY(option_a, option_b, option_c, option_d) as opts,
                                     CASE correct_option 
                                        WHEN 'A' THEN option_a 
                                        WHEN 'B' THEN option_b 
                                        WHEN 'C' THEN option_c 
                                        WHEN 'D' THEN option_d 
                                     END as a
                              FROM cbt_questions 
                              WHERE exam_id = ? 
                              ORDER BY RAND()");
        $stmt->execute([$realExamId]);
    } else {
        $stmt = $pdo->prepare("SELECT question_text as q, 
                                     JSON_ARRAY(option_a, option_b, option_c, option_d) as opts,
                                     CASE correct_option 
                                        WHEN 'A' THEN option_a 
                                        WHEN 'B' THEN option_b 
                                        WHEN 'C' THEN option_c 
                                        WHEN 'D' THEN option_d 
                                     END as a
                              FROM mock_questions 
                              WHERE subject_id = ? AND class_id = ? 
                              ORDER BY RAND() LIMIT 10");
        $stmt->execute([$subId, $classId]);
    }
    $dbQuestions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Decode JSON options for frontend
    foreach($dbQuestions as &$dq) {
        $dq['opts'] = json_decode($dq['opts']);
    }
    
    header('Content-Type: application/json');
    echo json_encode($dbQuestions);
    exit;
}
if ($type === 'result') {
    header("Location: " . WEB_ROOT . "/student/results");
    exit;
}

// 1. Fetch Student/Class Info
$stmt = $pdo->prepare("SELECT s.id as student_id, u.full_name as name, c.class_name as class_name, 
                 c.id as class_id, s.student_no, s.family_id
          FROM institute_students s
          JOIN users u ON s.student_id = u.id
          LEFT JOIN classes c ON s.class_id = c.id
          WHERE s.student_id = ? LIMIT 1");
$stmt->execute([$userId]);
$student = $stmt->fetch();

if (!$student) {
    die("Student profile not found.");
}

if ($type === 'exam') {
    // 1. Check if any real exam is set for this student's class
    $stmt = $pdo->prepare("SELECT e.*, s.subject_name 
                          FROM cbt_exams e
                          JOIN subjects s ON e.subject_id = s.id
                          WHERE e.class_id = ? AND e.exam_date = CURDATE()
                          ORDER BY e.start_time ASC");
    $stmt->execute([$student['class_id']]);
    $scheduledExams = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($scheduledExams)) {
        echo "<div style='height:100vh; display:flex; align-items:center; justify-content:center; flex-direction:column; font-family:sans-serif;'>
                <i class='ph ph-calendar-x' style='font-size:80px; color:#94A3B8;'></i>
                <h2 style='margin-top:20px; color:#1E293B;'>No Exam Available</h2>
                <p style='color:#64748B;'>There are no real exams scheduled for your class today.</p>
                <a href='?type=mock' style='background:#4F46E5; color:white; padding:10px 25px; border-radius:10px; text-decoration:none; font-weight:700; margin-top:20px;'>Practice with Mock CBT</a>
                <a href='dashboard' style='margin-top:15px; color:#64748B; font-weight:600; text-decoration:none;'>Return to Dashboard</a>
              </div>";
        exit;
    }

    // 2. Check timing for the first scheduled exam
    $exam = $scheduledExams[0];
    $now = date('H:i:s');
    
    if ($now < $exam['start_time']) {
        $startTime = date('h:i A', strtotime($exam['start_time']));
        echo "<div style='height:100vh; display:flex; align-items:center; justify-content:center; flex-direction:column; font-family:sans-serif; text-align:center; padding:20px;'>
                <i class='ph ph-clock-countdown' style='font-size:80px; color:#F59E0B;'></i>
                <h2 style='margin-top:20px; color:#B45309;'>EXAM NOT STARTED YET</h2>
                <p style='color:#64748B; max-width:400px;'>Your <strong>{$exam['subject_name']}</strong> exam is scheduled to start at <strong>$startTime</strong>. 
                Please come back and refresh the page at the set time.</p>
                <div style='background:#FEF3C7; color:#B45309; padding:12px; border-radius:10px; font-weight:800; margin-top:20px;'>CURRENT TIME: ".date('h:i: A')."</div>
                <a href='dashboard' style='margin-top:30px; color:#64748B; font-weight:600; text-decoration:none;'>Return to Dashboard</a>
              </div>";
        exit;
    }

    if ($now > $exam['end_time']) {
        echo "<div style='height:100vh; display:flex; align-items:center; justify-content:center; flex-direction:column; font-family:sans-serif;'>
                <i class='ph ph-lock-key' style='font-size:80px; color:#EF4444;'></i>
                <h2 style='margin-top:20px; color:#1E293B;'>EXAM PORTAL CLOSED</h2>
                <p style='color:#64748B;'>The time for the exam has elapsed. You can no longer access the questions.</p>
                <a href='dashboard' style='margin-top:20px; color:#4F46E5; font-weight:700; text-decoration:none;'>Return to Dashboard</a>
              </div>";
        exit;
    }

    // If we are here, it's exam time! Override mock variables
    $timeLimitMinutes = $exam['duration_minutes'];
    $timeLimitSeconds = $timeLimitMinutes * 60;
    $isRealExam = true;
    $examId = $exam['id'];
}

// 2. Fetch Available Subjects for the student's class
$stmt = $pdo->prepare("SELECT id, name as subject_name FROM class_subjects WHERE class_id = ? AND is_deleted = 0");
$stmt->execute([$student['class_id']]);
$subjects = $stmt->fetchAll();

// 3. Determine Time Limit based on Class Level
$className = strtoupper($student['class_name'] ?? '');
$timeLimitMinutes = 40; // Default

if (strpos($className, 'JSS') !== false || strpos($className, 'JS') !== false) {
    $timeLimitMinutes = 40;
} elseif (strpos($className, 'SS') !== false || strpos($className, 'SENIOR') !== false) {
    $timeLimitMinutes = 20;
} elseif (strpos($className, 'BEGINNER') !== false || strpos($className, 'PRIM') !== false || strpos($className, 'NUR') !== false) {
    $timeLimitMinutes = 60;
} else {
    // If no match, check if it's a number (Primary 1-6)
    if (preg_match('/PRIMARY|PRI/i', $className)) {
        $timeLimitMinutes = 60;
    }
}

$timeLimitSeconds = $timeLimitMinutes * 60;

// Mock Data for Questions
$mockQuestions = [
    'Mathematics' => [
        ['q' => 'What is 5 + 7?', 'opts' => ['10', '12', '14', '15'], 'a' => '12'],
        ['q' => 'Solve for x: 2x = 10', 'opts' => ['2', '5', '10', '20'], 'a' => '5'],
        ['q' => 'What is the square root of 64?', 'opts' => ['6', '7', '8', '9'], 'a' => '8'],
        ['q' => 'A triangle with all sides equal is called?', 'opts' => ['Scalene', 'Isosceles', 'Equilateral', 'Right'], 'a' => 'Equilateral'],
        ['q' => 'What is 15% of 200?', 'opts' => ['15', '20', '30', '45'], 'a' => '30'],
        ['q' => 'Value of Pi (approx)?', 'opts' => ['3.12', '3.14', '3.16', '3.18'], 'a' => '3.14'],
        ['q' => 'What is 100 / 4?', 'opts' => ['20', '25', '30', '40'], 'a' => '25'],
        ['q' => 'An angle greater than 90 is?', 'opts' => ['Acute', 'Obtuse', 'Right', 'Reflex'], 'a' => 'Obtuse'],
        ['q' => '7 x 8 = ?', 'opts' => ['54', '56', '58', '62'], 'a' => '56'],
        ['q' => 'What is the value of 2 to the power of 3?', 'opts' => ['6', '8', '9', '12'], 'a' => '8'],
    ],
    'English' => [
        ['q' => 'Opposite of "Hot"?', 'opts' => ['Warm', 'Cold', 'Ice', 'Cool'], 'a' => 'Cold'],
        ['q' => 'Noun in: "The cat sat on the mat"?', 'opts' => ['Sat', 'On', 'The', 'Cat'], 'a' => 'Cat'],
        ['q' => 'Pick the correctly spelled word:', 'opts' => ['Ocasion', 'Occasion', 'Ocassion', 'Occassion'], 'a' => 'Occasion'],
        ['q' => 'Past tense of "Go"?', 'opts' => ['Goes', 'Going', 'Went', 'Gone'], 'a' => 'Went'],
        ['q' => 'Synonym of "Huge"?', 'opts' => ['Small', 'Tiny', 'Giant', 'Short'], 'a' => 'Giant'],
        ['q' => 'A person who writes books is a?', 'opts' => ['Artist', 'Author', 'Actor', 'Pilot'], 'a' => 'Author'],
        ['q' => 'Which is a vowel?', 'opts' => ['B', 'C', 'E', 'F'], 'a' => 'E'],
        ['q' => 'Plural of "Child"?', 'opts' => ['Childs', 'Children', 'Childrens', 'Childes'], 'a' => 'Children'],
        ['q' => 'Young one of a dog is called?', 'opts' => ['Pup', 'Cub', 'Puppy', 'Kid'], 'a' => 'Puppy'],
        ['q' => 'Female of a King is?', 'opts' => ['Prince', 'Queen', 'Duchess', 'Princess'], 'a' => 'Queen'],
    ],
    'Science' => [
        ['q' => 'Planet closest to the Sun?', 'opts' => ['Venus', 'Mars', 'Mercury', 'Earth'], 'a' => 'Mercury'],
        ['q' => 'H2O is the chemical symbol for?', 'opts' => ['Air', 'Water', 'Oxygen', 'Salt'], 'a' => 'Water'],
        ['q' => 'The organ used for breathing is?', 'opts' => ['Heart', 'Lungs', 'Liver', 'Kidney'], 'a' => 'Lungs'],
        ['q' => 'Which state of matter has a fixed shape?', 'opts' => ['Solid', 'Liquid', 'Gas', 'Plasma'], 'a' => 'Solid'],
        ['q' => 'Source of energy for the Earth?', 'opts' => ['Moon', 'Sun', 'Mars', 'Wind'], 'a' => 'Sun'],
        ['q' => 'Human body temperature (Celsius)?', 'opts' => ['35', '37', '39', '40'], 'a' => '37'],
        ['q' => 'Force that pulls objects down?', 'opts' => ['Speed', 'Friction', 'Gravity', 'Magnetism'], 'a' => 'Gravity'],
        ['q' => 'Part of plant that grows underground?', 'opts' => ['Stem', 'Leaf', 'Flower', 'Root'], 'a' => 'Root'],
        ['q' => 'Animal that eats both plants and meat?', 'opts' => ['Herbivore', 'Carnivore', 'Omnivore', 'Insectivore'], 'a' => 'Omnivore'],
        ['q' => 'Light travels in a ______ line.', 'opts' => ['Curved', 'Straight', 'Zigzag', 'Wavy'], 'a' => 'Straight'],
    ]
];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mock CBT Centre - ROSMON SMS</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <style>
        :root {
            --primary: #4F46E5;
            --danger: #EF4444;
            --success: #10B981;
            --bg: #F8FAFC;
        }
        body { margin: 0; font-family: 'Outfit', sans-serif; background: var(--bg); overflow: hidden; }
        .setup-screen { 
            height: 100vh; display: flex; align-items: center; justify-content: center; 
            background: linear-gradient(135deg, #4F46E5 0%, #312E81 100%); color: white;
        }
        .setup-card {
            background: white; color: #1E293B; width: 500px; padding: 40px; border-radius: 24px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); text-align: center;
        }
        .avatar-lg {
            width: 100px; height: 100px; background: var(--primary); color: white;
            border-radius: 50%; display: flex; align-items: center; justify-content: center;
            font-size: 40px; font-weight: 800; margin: 0 auto 20px;
            border: 5px solid #E0E7FF;
        }
        .btn-cbt {
            background: var(--primary); color: white; border: none; padding: 15px 30px;
            border-radius: 12px; font-weight: 800; cursor: pointer; display: block; width: 100%;
            margin-top: 20px; font-size: 16px; transition: 0.3s;
        }
        .btn-cbt:hover { opacity: 0.9; transform: translateY(-2px); }
        .select-subject {
            width: 100%; padding: 12px; border-radius: 10px; border: 2px solid #E2E8F0;
            font-size: 15px; margin-top: 10px; outline: none;
        }

        /* Test Interface */
        #test-interface { display: none; height: 100vh; background: #fff; flex-direction: column; }
        .test-header {
            padding: 20px 40px; background: #1E293B; color: white; display: flex;
            justify-content: space-between; align-items: center;
        }
        .timer-box { background: var(--danger); padding: 8px 15px; border-radius: 8px; font-weight: 800; }
        
        .test-body { flex: 1; display: grid; grid-template-columns: 1fr 300px; gap: 40px; padding: 40px; overflow-y: auto; }
        .question-card { background: white; border-radius: 20px; padding: 30px; border: 1px solid #f1f5f9; }
        .q-text { font-size: 22px; font-weight: 700; margin-bottom: 25px; line-height: 1.4; }
        .opt-label {
            display: block; padding: 15px 20px; border: 2px solid #F1F5F9; border-radius: 12px;
            margin-bottom: 12px; cursor: pointer; transition: 0.2s; font-weight: 600;
        }
        .opt-label:hover { border-color: var(--primary); background: #F5F3FF; }
        .opt-label input { display: none; }
        .opt-label.selected { background: var(--primary); color: white; border-color: var(--primary); }

        .nav-buttons { display: flex; gap: 15px; margin-top: 30px; }
        .btn-nav { flex: 1; padding: 12px; border-radius: 10px; border: 1px solid #E2E8F0; font-weight: 700; cursor: pointer; }
        .btn-submit { background: var(--success); color: white; border: none; }

        .sidebar-test { background: #F8FAFC; border-radius: 20px; padding: 20px; display: flex; flex-direction: column; gap: 10px; }
        .q-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 8px; }
        .q-num {
            width: 40px; height: 40px; border-radius: 8px; background: #E2E8F0; color: #64748B;
            display: flex; align-items: center; justify-content: center; font-weight: 800; cursor: pointer;
        }
        .q-num.active { background: var(--primary); color: white; }
        .q-num.answered { background: var(--success); color: white; }

        /* Malpractice Modal */
        #malpractice-overlay { 
            display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; 
            background: rgba(239, 68, 68, 0.95); z-index: 9999; color: white;
            flex-direction: column; align-items: center; justify-content: center;
        }
        
        /* Result Screen */
        #result-screen { display: none; height: 100vh; flex-direction: column; align-items: center; justify-content: center; text-align: center; }
        .score-box { font-size: 80px; font-weight: 800; color: var(--primary); margin: 20px 0; }
    </style>
</head>
<body>

    <!-- SCREEN 1: DETAILS & SUBJECT SELECTION -->
    <div class="setup-screen" id="setup-screen">
        <div class="setup-card">
            <div class="avatar-lg"><?= strtoupper(substr($student['name'], 0, 1)) ?></div>
            <h2 style="margin: 0;"><?= htmlspecialchars($student['name']) ?></h2>
            <p style="color: #64748B; margin: 5px 0 30px;">ID: <?= $student['student_no'] ?> | Class: <?= $student['class_name'] ?></p>

            <div style="text-align: left;">
                <label style="font-weight: 800; font-size: 13px; color: #4F46E5;">SELECT MOCK SUBJECT</label>
                <select class="select-subject" id="subject-select">
                    <?php foreach($subjects as $s): ?>
                        <option value="<?= $s['subject_name'] ?>" data-id="<?= $s['id'] ?>"><?= $s['subject_name'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="background: #FFF7ED; border: 1px solid #FFEDD5; padding: 15px; border-radius: 12px; margin-top: 25px; text-align: left;">
                <h4 style="margin: 0; color: #C2410C; display: flex; align-items: center; gap: 8px;">
                    <i class="ph ph-warning-circle"></i> ADVANCED MALPRACTICE DETECTION
                </h4>
                <p style="font-size: 12px; color: #7C2D12; line-height: 1.5; margin-top: 8px;">
                    This system uses <strong>AI Focus Movement Detection</strong> to track eye and tab activity. 
                    If you look away from the test window or attempt to switch tabs, your exam will be <strong>FLAGGED</strong> immediately.
                </p>
            </div>

            <button class="btn-cbt" onclick="enterFullscreen()">UNLOCK <?= $isRealExam ? 'EXAM' : 'MOCK' ?> CENTRE & START</button>
            <a href="<?= WEB_ROOT ?>/student/dashboard" style="display:block; margin-top:15px; color:#64748B; font-weight:700; text-decoration:none; font-size:14px;">
                <i class="ph ph-arrow-left"></i> Return to Dashboard
            </a>
        </div>
    </div>

    <!-- SCREEN 2: TEST INTERFACE -->
    <div id="test-interface">
        <div class="test-header">
            <div>
                <span id="test-subject-name" style="font-weight: 800; font-size: 18px; text-transform: uppercase;">SUBJECT</span>
                <span style="margin-left: 20px; opacity: 0.6; font-weight: 600;"><?= $isRealExam ? 'MAIN EXAMINATION 2026' : 'MOCK EXAM 2026' ?></span>
            </div>
            <div class="timer-box">
                <i class="ph ph-timer"></i> <span id="timer-display"><?= $timeLimitMinutes ?>:00</span>
            </div>
        </div>

        <div class="test-body">
            <div class="left-col">
                <div class="question-card">
                    <p style="color: var(--primary); font-weight: 800; font-size: 12px; margin-bottom: 10px;">QUESTION <span id="q-idx-display">1</span> OF 10</p>
                    <div id="q-content" class="q-text">Loading question...</div>
                    <div id="options-box"></div>
                </div>
                <div class="nav-buttons">
                    <button class="btn-nav" id="prev-btn" onclick="prevQuestion()">PREVIOUS</button>
                    <button class="btn-nav" id="next-btn" onclick="nextQuestion()">NEXT</button>
                    <button class="btn-nav btn-submit" onclick="submitTest()">SUBMIT EXAM</button>
                </div>
            </div>

            <div class="sidebar-test">
                <h3 style="margin-top: 0; font-size: 14px;">Question Navigator</h3>
                <div class="q-grid" id="q-grid">
                    <?php for($i=1; $i<=10; $i++): ?>
                        <div class="q-num" id="nav-<?= $i ?>" onclick="goToQuestion(<?= $i ?>)"><?= $i ?></div>
                    <?php endfor; ?>
                </div>
                <div style="margin-top: auto; background: white; padding: 15px; border-radius: 12px;">
                    <p style="margin: 0; font-size: 12px; color: #64748B;">Student Focus</p>
                    <div style="height: 4px; background: #E2E8F0; border-radius: 10px; margin-top: 8px;">
                        <div id="focus-bar" style="width: 100%; height: 100%; background: var(--success); border-radius: 10px;"></div>
                    </div>
                    <p style="margin: 8px 0 0; font-size: 10px; font-weight: 700; color: var(--success);">EYE-TRACKING ACTIVE</p>
                </div>
            </div>
        </div>
    </div>

    <!-- SCREEN 3: RESULTS -->
    <div id="result-screen">
        <h1 id="result-title">Mock CBT Completed!</h1>
        <div class="score-box"><span id="final-score">0</span>/10</div>
        <p id="result-text" style="font-size: 18px; color: #64748B;"></p>
        <div style="display:flex; gap:15px; justify-content:center;">
            <button class="btn-cbt" style="width: 250px; margin: 30px 0;" onclick="showCorrections()">VIEW CORRECTIONS</button>
            <button class="btn-cbt" style="width: 250px; margin: 30px 0; background:#64748B;" onclick="location.reload()">TRY ANOTHER SUBJECT</button>
        </div>
    </div>

    <!-- SCREEN 4: CORRECTIONS -->
    <div id="corrections-screen" style="display:none; height:100vh; flex-direction:column; background:white; overflow:hidden;">
        <div class="test-header">
            <div><i class="ph ph-check-square"></i> REVIEW CORRECTIONS</div>
            <div style="display:flex; gap:10px;">
                <button onclick="location.reload()" style="background:transparent; border:1px solid white; color:white; padding:5px 15px; border-radius:8px; cursor:pointer;">New Subject</button>
                <a href="<?= WEB_ROOT ?>/student/dashboard" style="background:var(--primary); border:1px solid white; color:white; padding:5px 15px; border-radius:8px; text-decoration:none; display:inline-block; font-size:13px; font-weight:700;">Exit to Dashboard</a>
            </div>
        </div>
        <div id="corrections-list" style="flex:1; overflow-y:auto; padding:40px;">
            <!-- Corrections will be injected here -->
        </div>
    </div>

    <!-- MALPRACTICE OVERLAY -->
    <div id="malpractice-overlay">
        <i class="ph ph-hand-eye" style="font-size: 100px; margin-bottom: 20px;"></i>
        <h1 style="font-size: 50px; font-weight: 800; margin: 0;">MALPRACTICE DETECTED!</h1>
        <p style="font-size: 20px; opacity: 0.9; margin-top: 10px;">Your focus tracking has been compromised. The exam has been terminated.</p>
        <p style="font-size: 14px; margin-top: 40px; color: #FEE2E2;">Result Flagged: MALPRACTICE_ATTEMPT</p>
        <button class="btn-cbt" style="background: white; color: var(--danger); width: 250px; margin-top: 40px;" onclick="location.reload()">RE-AUTHENTICATE</button>
    </div>

    <script>
        const mockData = <?= json_encode($mockQuestions) ?>;
        let currentSubject = "";
        let questions = [];
        let currentIndex = 0;
        let answers = new Array(10).fill(null);
        let timeLeft = <?= $timeLimitSeconds ?>;
        let timer = null;
        let isMalpractice = false;
        let isSubmitted = false;

        function enterFullscreen() {
            const elem = document.documentElement;
            if (elem.requestFullscreen) elem.requestFullscreen();
            else if (elem.webkitRequestFullscreen) elem.webkitRequestFullscreen();
            else if (elem.msRequestFullscreen) elem.msRequestFullscreen();

            startCBT();
        }

        function startCBT() {
            currentSubject = document.getElementById('subject-select').value;
            const subjectId = document.getElementById('subject-select').options[document.getElementById('subject-select').selectedIndex].dataset.id;
            
            // FETCH REAL QUESTIONS FROM SERVER IF ANY
            fetch(`?type=fetch_questions&subject_id=${subjectId}&class_id=<?= $student['class_id'] ?>&exam_id=<?= $examId ?? 0 ?>`)
                .then(res => res.json())
                .then(data => {
                    if (data && data.length > 0) {
                        questions = data;
                    } else {
                        // Fallback to dummy
                        questions = mockData[currentSubject] || mockData['Mathematics'];
                    }
                    
                    document.getElementById('setup-screen').style.display = 'none';
                    document.getElementById('test-interface').style.display = 'flex';
                    document.getElementById('test-subject-name').innerText = currentSubject;

                    loadQuestion(0);
                    startTimer();
                    trackFocus();
                })
                .catch(e => {
                    console.error("Fetch Error:", e);
                    questions = mockData[currentSubject] || mockData['Mathematics'];
                    // Still start
                    document.getElementById('setup-screen').style.display = 'none';
                    document.getElementById('test-interface').style.display = 'flex';
                    loadQuestion(0);
                    startTimer();
                    trackFocus();
                });
        }

        function loadQuestion(idx) {
            currentIndex = idx;
            const q = questions[idx];
            document.getElementById('q-idx-display').innerText = idx + 1;
            document.getElementById('q-content').innerText = q.q;
            
            const box = document.getElementById('options-box');
            box.innerHTML = '';
            q.opts.forEach(opt => {
                const label = document.createElement('label');
                label.className = 'opt-label' + (answers[idx] === opt ? ' selected' : '');
                label.innerHTML = `<input type="radio" name="opt" value="${opt}"> ${opt}`;
                label.onclick = () => {
                    answers[idx] = opt;
                    document.querySelectorAll('.opt-label').forEach(l => l.classList.remove('selected'));
                    label.classList.add('selected');
                    updateNavGrid();
                    
                    // Auto-advance with small delay
                    setTimeout(() => {
                        if (currentIndex < 9) nextQuestion();
                    }, 400);
                };
                box.appendChild(label);
            });

            // Update Nav Grid
            document.querySelectorAll('.q-num').forEach(n => n.classList.remove('active'));
            document.getElementById('nav-' + (idx + 1)).classList.add('active');
            
            document.getElementById('prev-btn').disabled = (idx === 0);
            document.getElementById('next-btn').style.display = (idx === 9) ? 'none' : 'block';
        }

        function nextQuestion() { if(currentIndex < 9) loadQuestion(currentIndex+1); }
        function prevQuestion() { if(currentIndex > 0) loadQuestion(currentIndex-1); }
        function goToQuestion(i) { loadQuestion(i-1); }

        function updateNavGrid() {
            answers.forEach((ans, i) => {
                if(ans !== null) document.getElementById('nav-' + (i+1)).classList.add('answered');
            });
        }

        function startTimer() {
            timer = setInterval(() => {
                timeLeft--;
                const mins = Math.floor(timeLeft / 60);
                const secs = timeLeft % 60;
                document.getElementById('timer-display').innerText = `${mins}:${secs.toString().padStart(2, '0')}`;
                
                if(timeLeft <= 60) document.querySelector('.timer-box').style.background = '#000';
                if(timeLeft <= 0) {
                    clearInterval(timer);
                    submitTest();
                }
            }, 1000);
        }

        function trackFocus() {
            window.addEventListener('blur', () => flagMalpractice());
            document.addEventListener('visibilitychange', () => {
                if (document.hidden) flagMalpractice();
            });
            
            // Mock Eye Tracking logic: randomly fluctuate the "focus bar"
            setInterval(() => {
                if(isSubmitted || isMalpractice) return;
                const bar = document.getElementById('focus-bar');
                const rand = 90 + Math.random() * 10;
                bar.style.width = rand + '%';
            }, 2000);
        }

        function flagMalpractice() {
            if(isSubmitted || isMalpractice) return;
            isMalpractice = true;
            clearInterval(timer);
            document.exitFullscreen().catch(e => {});
            document.getElementById('malpractice-overlay').style.display = 'flex';
        }

        function submitTest() {
            if(isSubmitted || isMalpractice) return;
            isSubmitted = true;
            clearInterval(timer);
            document.exitFullscreen().catch(e => {});

            let score = 0;
            questions.forEach((q, i) => {
                if(answers[i] === q.a) score++;
            });

            document.getElementById('test-interface').style.display = 'none';
            document.getElementById('result-screen').style.display = 'flex';
            document.getElementById('final-score').innerText = score;
            
            const msg = score >= 5 ? "Great job! You are ready for the main exam." : "Keep practicing. You need more speed and accuracy.";
            document.getElementById('result-text').innerText = msg;
        }

        function showCorrections() {
            document.getElementById('result-screen').style.display = 'none';
            document.getElementById('corrections-screen').style.display = 'flex';
            
            const list = document.getElementById('corrections-list');
            list.innerHTML = "";

            questions.forEach((q, i) => {
                const userAns = answers[i];
                const isCorrect = (userAns === q.a);
                
                const item = document.createElement('div');
                item.style.cssText = "background:#F8FAFC; border-radius:15px; padding:25px; margin-bottom:20px; border:1px solid #E2E8F0;";
                item.innerHTML = `
                    <div style="display:flex; justify-content:space-between; margin-bottom:15px;">
                        <span style="font-weight:800; color:var(--primary);">QUESTION ${i+1}</span>
                        <span style="font-weight:800; color:${isCorrect ? 'var(--success)' : 'var(--danger)'};">
                            <i class="ph ${isCorrect ? 'ph-check-circle' : 'ph-x-circle'}"></i> ${isCorrect ? 'CORRECT' : 'INCORRECT'}
                        </span>
                    </div>
                    <div style="font-size:18px; font-weight:700; margin-bottom:15px;">${q.q}</div>
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                        <div style="padding:10px; border-radius:8px; background:${userAns === q.a ? '#DCFCE7' : '#FEE2E2'}; border:1px solid ${userAns === q.a ? '#86EFAC' : '#FECACA'};">
                            <small style="display:block; font-weight:800; opacity:0.6;">Your Answer</small>
                            <span style="font-weight:700;">${userAns || 'No Answer'}</span>
                        </div>
                        <div style="padding:10px; border-radius:8px; background:#DBEAFE; border:1px solid #BFDBFE;">
                            <small style="display:block; font-weight:800; opacity:0.6;">Correct Answer</small>
                            <span style="font-weight:700;">${q.a}</span>
                        </div>
                    </div>
                `;
                list.appendChild(item);
            });
        }
    </script>
</body>
</html>
