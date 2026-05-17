<?php
require_once ROOT_PATH . '/config/database.php';
$teacher_id = $_SESSION['user_id'] ?? 0;
$teacher_name = $_SESSION['username'] ?? 'Faculty';

// 1. Clock In/Out Logic
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['clock_in'])) {
        $stmt = $pdo->prepare("INSERT INTO employee_attendance (user_id, attendance_date, status, clock_in) VALUES (?, CURDATE(), 'Present', CURTIME()) ON DUPLICATE KEY UPDATE clock_in = IF(clock_in IS NULL, CURTIME(), clock_in)");
        $stmt->execute([$teacher_id]);
        header("Location: " . WEB_ROOT . "/employee/dashboard"); exit;
    } elseif (isset($_POST['clock_out'])) {
        $stmt = $pdo->prepare("UPDATE employee_attendance SET clock_out = CURTIME() WHERE user_id = ? AND attendance_date = CURDATE()");
        $stmt->execute([$teacher_id]);
        header("Location: " . WEB_ROOT . "/employee/dashboard"); exit;
    }
}

$stmt = $pdo->prepare("SELECT clock_in, clock_out FROM employee_attendance WHERE user_id = ? AND attendance_date = CURDATE() LIMIT 1");
$stmt->execute([$teacher_id]);
$todayAtt = $stmt->fetch(PDO::FETCH_ASSOC);

// 2. Fetch Assignments & Students Performance
$stmt = $pdo->prepare("
    SELECT cs.class_id, cs.subject_id, s.subject_name, c.class_name, c.arm 
    FROM class_subjects cs
    JOIN classes c ON cs.class_id = c.id
    JOIN subjects s ON cs.subject_id = s.id
    WHERE cs.teacher_id = ? AND cs.is_deleted = 0
");
$stmt->execute([$teacher_id]);
$assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalSubjects = count($assignments);
$subjectIds = array_column($assignments, 'subject_id');
$classIds = array_column($assignments, 'class_id');

// --- ANALYTICS ---
// A. Average Quiz Score for My Subjects
$avgScore = 0; $totalQuizzes = 0;
if (!empty($subjectIds)) {
    $placeholders = str_repeat('?,', count($subjectIds) - 1) . '?';
    $qStats = $pdo->prepare("
        SELECT AVG(score) as avg_score, COUNT(*) as total_attempts 
        FROM student_quiz_attempts qa
        JOIN lesson_notes ln ON qa.lesson_note_id = ln.id
        WHERE ln.subject_id IN ($placeholders)
    ");
    $qStats->execute($subjectIds);
    $stats = $qStats->fetch();
    $avgScore = round($stats['avg_score'] ?? 0, 1);
    $totalQuizzes = $stats['total_attempts'] ?? 0;
}

// B. Class Attendance Trend (last 7 days)
$attLabels = []; $attData = [];
for($i=6; $i>=0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $attLabels[] = date('D', strtotime($date));
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM student_attendance WHERE attendance_date = ? AND status = 'Present'");
    $stmt->execute([$date]);
    $attData[] = $stmt->fetchColumn();
}

// C. Recent Performance Data for Chart (Last 5 Quizzes)
$recentQuizLabels = []; $recentQuizData = [];
if (!empty($subjectIds)) {
    $placeholders = str_repeat('?,', count($subjectIds) - 1) . '?';
    $stmt = $pdo->prepare("
        SELECT ln.topic, AVG(qa.score) as avg_s
        FROM student_quiz_attempts qa
        JOIN lesson_notes ln ON qa.lesson_note_id = ln.id
        WHERE ln.subject_id IN ($placeholders)
        GROUP BY ln.id ORDER BY ln.id DESC LIMIT 5
    ");
    $stmt->execute($subjectIds);
    $res = array_reverse($stmt->fetchAll());
    foreach($res as $r) {
        $recentQuizLabels[] = mb_strimwidth($r['topic'], 0, 10, '...');
        $recentQuizData[] = $r['avg_s'];
    }
}

$pageTitle = 'Intelligence Hub - Teacher Dashboard';
require ROOT_PATH . '/app/Views/employee/layout/header.php';
?>

<style>
    .db-container { max-width: 1300px; margin: 0 auto; padding: 10px; }
    .hero-section { background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%); border-radius: 24px; padding: 40px; color: white; display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; box-shadow: 0 20px 40px -15px rgba(79, 70, 229, 0.4); }
    .stat-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 30px; }
    .stat-card { background: white; padding: 25px; border-radius: 20px; border: 1px solid #f1f5f9; box-shadow: 0 4px 15px -1px rgba(0,0,0,0.02); display: flex; align-items: center; gap: 20px; transition: 0.2s; }
    .stat-card:hover { transform: translateY(-3px); box-shadow: 0 12px 25px -5px rgba(0,0,0,0.05); }
    .stat-icon { width: 56px; height: 56px; border-radius: 16px; display: flex; align-items: center; justify-content: center; font-size: 24px; }
    
    .grid-main { display: grid; grid-template-columns: 1.8fr 1fr; gap: 30px; }
    .content-card { background: white; border-radius: 24px; padding: 30px; border: 1px solid #f1f5f9; box-shadow: 0 4px 15px -1px rgba(0,0,0,0.02); height: 100%; }
    
    .btn-clock { padding: 12px 25px; border-radius: 12px; font-weight: 800; font-size: 14px; border: none; cursor: pointer; transition: 0.2s; display: flex; align-items: center; gap: 8px; }
    .btn-clock-in { background: #10b981; color: white; box-shadow: 0 8px 15px -3px rgba(16, 185, 129, 0.3); }
    .btn-clock-out { background: #ef4444; color: white; box-shadow: 0 8px 15px -3px rgba(239, 68, 68, 0.3); }
    
    .activity-row { display: flex; align-items: center; gap: 15px; padding: 15px 0; border-bottom: 1px solid #f8fafc; }
    
    @media (max-width: 1024px) { .grid-main { grid-template-columns: 1fr; } }
</style>

<div class="db-container">
    <!-- Hero Section -->
    <div class="hero-section">
        <div>
            <span style="font-weight: 800; font-size: 12px; opacity: 0.8; text-transform: uppercase; letter-spacing: 1px;">Academic Intelligence Hub</span>
            <h1 style="margin: 10px 0; font-size: 32px; font-weight: 900;">Welcome back, <?= htmlspecialchars($teacher_name) ?>!</h1>
            <p style="opacity: 0.9; font-size: 15px; max-width: 500px;">Your automated insights are ready. You have <?= $totalSubjects ?> active subjects scheduled for this week.</p>
        </div>
        <div style="background: rgba(255,255,255,0.1); padding: 10px; border-radius: 20px; border: 1px solid rgba(255,255,255,0.2);">
            <form method="POST">
                <?php if (!$todayAtt || !$todayAtt['clock_in']): ?>
                    <button type="submit" name="clock_in" class="btn-clock btn-clock-in"><i class="ph ph-fingerprint"></i> Clock-In Now</button>
                <?php elseif (!$todayAtt['clock_out']): ?>
                    <div style="display:flex; align-items:center; gap:15px; padding: 10px;">
                        <span style="font-size:12px; font-weight:700;"><i class="ph ph-clock"></i> In: <?= date('h:i A', strtotime($todayAtt['clock_in'])) ?></span>
                        <button type="submit" name="clock_out" class="btn-clock btn-clock-out"><i class="ph ph-sign-out"></i> Clock-Out</button>
                    </div>
                <?php else: ?>
                    <div style="background: rgba(255,255,255,0.15); padding: 15px; border-radius: 12px; font-weight:700; font-size:12px;">
                        Attendance Completed Today <i class="ph ph-check-circle"></i>
                    </div>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- Quick Stats -->
    <div class="stat-row">
        <div class="stat-card">
            <div class="stat-icon" style="background: #eef2ff; color: #4f46e5;"><i class="ph-fill ph-books"></i></div>
            <div>
                <p style="color:#64748b; font-size:12px; font-weight:700; margin:0 0 5px 0; text-transform:uppercase;">Subjects</p>
                <h2 style="margin:0; font-weight:900;"><?= $totalSubjects ?></h2>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: #fff7ed; color: #f97316;"><i class="ph-fill ph-users-three"></i></div>
            <div>
                <p style="color:#64748b; font-size:12px; font-weight:700; margin:0 0 5px 0; text-transform:uppercase;">Student Avg</p>
                <h2 style="margin:0; font-weight:900;"><?= $avgScore ?>/5</h2>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: #f0fdf4; color: #10b981;"><i class="ph-fill ph-chart-line-up"></i></div>
            <div>
                <p style="color:#64748b; font-size:12px; font-weight:700; margin:0 0 5px 0; text-transform:uppercase;">Quiz Vol</p>
                <h2 style="margin:0; font-weight:900;"><?= $totalQuizzes ?></h2>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: #fdf2f8; color: #db2777;"><i class="ph-fill ph-calendar-check"></i></div>
            <div>
                <p style="color:#64748b; font-size:12px; font-weight:700; margin:0 0 5px 0; text-transform:uppercase;">Status</p>
                <h2 style="margin:0; font-weight:900; color:#10b981;">Online</h2>
            </div>
        </div>
    </div>

    <!-- Charts & Lists -->
    <div class="grid-main">
        <div class="col-left">
            <div class="content-card">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:30px;">
                    <h3 style="margin:0; font-weight:900;"><i class="ph-fill ph-chart-pie" style="color:var(--primary);"></i> Student Mastery Index</h3>
                    <span style="font-size:12px; color:#64748b;">Subject-wise Average Scores</span>
                </div>
                <canvas id="performanceChart" height="280"></canvas>
            </div>
        </div>
        <div class="col-right">
            <div class="content-card">
                <h3 style="margin:0 0 25px 0; font-weight:900;"><i class="ph-fill ph-pulse" style="color:#f43f5e;"></i> Real-time Participation</h3>
                <canvas id="attendanceChart" height="200"></canvas>
                
                <hr style="margin:30px 0; border:0; border-top:1px solid #f1f5f9;">
                
                <h3 style="margin:0 0 15px 0; font-size:15px; font-weight:900;">My Assigned Departments</h3>
                <div style="display:flex; flex-wrap:wrap; gap:8px;">
                    <?php if (empty($assignments)): ?>
                        <p style="font-size:12px; color:#94a3b8; font-style:italic;">No active assignments found.</p>
                    <?php endif; ?>
                    <?php foreach($assignments as $a): ?>
                        <span style="background:#f8fafc; border:1px solid #eef2f6; padding:8px 12px; border-radius:12px; font-size:12px; font-weight:700; color:#475569;">
                            <?= htmlspecialchars($a['subject_name']) ?> <span style="color:#cbd5e1; margin:0 5px;">|</span> <?= htmlspecialchars($a['class_name']) ?>
                        </span>
                    <?php endforeach; ?>
                </div>
                
                <a href="<?= WEB_ROOT ?>/employee/lms-monitor" style="display:block; margin-top:25px; text-align:center; padding:15px; background:var(--primary-light); color:var(--primary); font-weight:800; border-radius:12px; text-decoration:none; transition:0.2s;">
                    View Detailed LMS Insights
                </a>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// A. Performance Chart (Subject/Quiz Avg)
const ctxPerf = document.getElementById('performanceChart').getContext('2d');
new Chart(ctxPerf, {
    type: 'bar',
    data: {
        labels: <?= json_encode($recentQuizLabels) ?>,
        datasets: [{
            label: 'Avg Score (Out of 5)',
            data: <?= json_encode($recentQuizData) ?>,
            backgroundColor: '#4f46e5',
            borderRadius: 8,
            barThickness: 30
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: { 
            y: { beginAtZero: true, max: 5 },
            x: { grid: { display: false } }
        }
    }
});

// B. Attendance Chart
const ctxAtt = document.getElementById('attendanceChart').getContext('2d');
new Chart(ctxAtt, {
    type: 'line',
    data: {
        labels: <?= json_encode($attLabels) ?>,
        datasets: [{
            label: 'Participation',
            data: <?= json_encode($attData) ?>,
            borderColor: '#f43f5e',
            backgroundColor: 'rgba(244, 63, 94, 0.1)',
            fill: true,
            tension: 0.4
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: { 
            y: { beginAtZero: true, grid: { color: '#f8fafc' } },
            x: { grid: { display: false } }
        }
    }
});
</script>

<?php require ROOT_PATH . '/app/Views/employee/layout/footer.php'; ?>
