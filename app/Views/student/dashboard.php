<?php
/**
 * Professional Student Dashboard - Rosmon SMS
 * Modernized UI with Performance Analytics, Notice Board & Attendance Tracking
 */

require_once ROOT_PATH . '/config/database.php';
$userId = $_SESSION['user_id'] ?? 0;
$today = date('Y-m-d');
$message = "";

// 1. Fetch Student/Class Info
$query = "SELECT s.id as student_id, u.full_name as name, c.class_name as class_name, 
                 c.id as class_id, t.full_name as teacher_name, s.student_no, s.family_id
          FROM institute_students s
          JOIN users u ON s.student_id = u.id
          LEFT JOIN classes c ON s.class_id = c.id
          LEFT JOIN users t ON c.teacher_id = t.id
          WHERE s.student_id = :uid LIMIT 1";
$stmt = $pdo->prepare($query);
$stmt->execute([':uid' => $userId]);
$student = $stmt->fetch();

if (!$student) {
    die("Student profile not found. Please contact administrator.");
}

$classId = $student['class_id'];

// 2. Fetch Subjects
$stmt = $pdo->prepare("SELECT cs.name as subject_name, t.full_name as teacher_name 
                      FROM class_subjects cs 
                      LEFT JOIN users t ON cs.teacher_id = t.id 
                      WHERE cs.class_id = :cid AND cs.is_deleted = 0");
$stmt->execute([':cid' => $classId]);
$subjects = $stmt->fetchAll();

// 3. Calculate Punctuality (Mocked for Demo based on current logs)
$stmt = $pdo->prepare("SELECT COUNT(*) as total, 
                       SUM(CASE WHEN clock_in <= '08:00:00' THEN 1 ELSE 0 END) as on_time 
                       FROM student_attendance_logs WHERE student_id = :uid");
$stmt->execute([':uid' => $userId]);
$attStats = $stmt->fetch();
$punctualityRate = ($attStats['total'] > 0) ? round(($attStats['on_time'] / $attStats['total']) * 100) : 0;

// 4. Behavioural Analysis (Mock or Real)
$stmt = $pdo->prepare("SELECT * FROM psycho_beh_analysis WHERE student_id = :uid ORDER BY id DESC LIMIT 1");
$stmt->execute([':uid' => $userId]);
$behaviour = $stmt->fetch();

if (!$behaviour) {
    // Default mock behavior data if none exists
    $behaviour = [
        'discipline' => 85, 'neatness' => 90, 'politeness' => 88, 
        'self_control' => 80, 'relationship_with_others' => 92
    ];
}

// 5. Fee Data (Mocked for Visuals)
$feeData = [
    ['term' => 'Term 1', 'bill' => 50000, 'paid' => 50000],
    ['term' => 'Term 2', 'bill' => 50000, 'paid' => 42000],
    ['term' => 'Term 3', 'bill' => 50000, 'paid' => 0],
];

// 6. Check Today's Attendance
$stmt = $pdo->prepare("SELECT clock_in, clock_out FROM student_attendance_logs WHERE student_id = :uid AND attendance_date = :today");
$stmt->execute([':uid' => $userId, ':today' => $today]);
$todayAtt = $stmt->fetch();

// 7. Unread Messages for Notification
$stmt = $pdo->prepare("SELECT COUNT(*) FROM direct_messages WHERE receiver_id = ? AND is_read = 0");
$stmt->execute([$userId]);
$unreadCount = $stmt->fetchColumn();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Hub - <?= htmlspecialchars($globalSchoolName ?? 'Rosmon SMS') ?></title>
    
    <!-- Design Assets -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        :root {
            --primary: #4F46E5;
            --primary-light: rgba(79, 70, 229, 0.1);
            --secondary: #10B981;
            --accent: #F59E0B;
            --bg: #F8FAFC;
            --card-bg: #FFFFFF;
            --text-dark: #1E293B;
            --text-muted: #64748B;
            --border: #E2E8F0;
            --sidebar-w: 280px;
        }

        * { box-sizing: border-box; font-family: 'Outfit', 'Inter', sans-serif; }
        body { margin: 0; background: var(--bg); color: var(--text-dark); display: flex; overflow-x: hidden; }

        /* Sidebar Modern Design */
        .sidebar {
            width: var(--sidebar-w);
            height: 100vh;
            background: #FFFFFF;
            border-right: 1px solid var(--border);
            padding: 30px 20px;
            position: fixed;
            display: flex;
            flex-direction: column;
            z-index: 1000;
        }

        .profile-card {
            background: var(--primary-light);
            padding: 20px;
            border-radius: 20px;
            text-align: center;
            margin-bottom: 40px;
        }

        .profile-avatar {
            width: 70px;
            height: 70px;
            background: var(--primary);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            font-weight: 800;
            margin: 0 auto 15px;
            box-shadow: 0 10px 15px -3px rgba(79, 70, 229, 0.3);
        }

        .profile-name { font-size: 16px; font-weight: 700; margin: 0; }
        .profile-role { font-size: 12px; color: var(--text-muted); font-weight: 600; margin-top: 4px; }

        .nav-menu { flex-grow: 1; }
        .nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 18px;
            color: var(--text-muted);
            text-decoration: none;
            border-radius: 14px;
            margin-bottom: 8px;
            font-weight: 600;
            transition: 0.3s;
        }

        .nav-item:hover { background: var(--bg); color: var(--primary); }
        .nav-item.active { background: var(--primary); color: #FFFFFF; box-shadow: 0 10px 15px -3px rgba(79, 70, 229, 0.2); }
        .nav-item i { font-size: 20px; }

        /* Main Content */
        .main-wrapper {
            margin-left: var(--sidebar-w);
            width: calc(100% - var(--sidebar-w));
            padding: 30px;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .welcome-msg h1 { font-size: 24px; font-weight: 800; margin: 0; }
        .welcome-msg p { color: var(--text-muted); font-size: 14px; margin-top: 5px; }

        .header-tools { display: flex; gap: 15px; align-items: center; }
        .tool-icon {
            width: 45px;
            height: 45px;
            background: #FFFFFF;
            border: 1px solid var(--border);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-muted);
            cursor: pointer;
            transition: 0.3s;
        }
        .tool-icon:hover { border-color: var(--primary); color: var(--primary); }

        /* Top Grid Stats */
        .stat-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 30px; }
        .stat-card {
            background: #FFFFFF;
            padding: 24px;
            border-radius: 20px;
            border: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }
        .stat-data h3 { font-size: 12px; font-weight: 700; color: var(--text-muted); margin: 0; text-transform: uppercase; letter-spacing: 0.5px; }
        .stat-data div { font-size: 20px; font-weight: 800; margin-top: 4px; }

        /* Main Dashboard Body Grid */
        .body-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 25px; }

        .content-card {
            background: #FFFFFF;
            border-radius: 24px;
            padding: 30px;
            border: 1px solid var(--border);
            margin-bottom: 25px;
        }
        .card-header { display:flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
        .card-title { font-size: 18px; font-weight: 800; }

        /* Subject List */
        .subject-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid var(--border);
        }
        .subject-item:last-child { border: none; }
        .subject-info { display: flex; align-items: center; gap: 15px; }
        .subject-circle {
            width: 45px;
            height: 45px;
            background: #FEE2E2;
            color: #EF4444;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
        }
        .sub-name { font-weight: 700; margin: 0; font-size: 14px; }
        .sub-teacher { font-size: 12px; color: var(--text-muted); margin-top: 3px; }

        /* Notice Board */
        .notice-item {
            padding: 15px;
            border-radius: 16px;
            background: var(--bg);
            margin-bottom: 15px;
            border-left: 4px solid var(--primary);
        }
        .notice-tag { font-size: 10px; font-weight: 800; padding: 4px 8px; border-radius: 20px; background: #EEF2FF; color: var(--primary); }
        .notice-text { font-size: 13px; font-weight: 600; margin-top: 8px; line-height: 1.5; }

        /* Punctuality Component */
        .punctual-widget {
            background: linear-gradient(135deg, #1E293B, #0F172A);
            color: white;
            padding: 25px;
            border-radius: 24px;
            text-align: center;
        }
        .radial-progress {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: conic-gradient(var(--secondary) <?= $punctualityRate ?>%, rgba(255,255,255,0.1) 0);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            position: relative;
        }
        .radial-progress::after {
            content: "<?= $punctualityRate ?>%";
            width: 100px;
            height: 100px;
            background: #0F172A;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            font-weight: 800;
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .body-grid { grid-template-columns: 1fr; }
            .stat-grid { grid-template-columns: repeat(2, 1fr); }
        }
    </style>
</head>
<body>

    <!-- SIDEBAR -->
    <div class="sidebar">
        <div style="text-align:center; margin-bottom:20px;">
            <?php if (!empty($globalSchoolLogo)): ?>
                <img src="<?= WEB_ROOT . $globalSchoolLogo ?>" alt="Logo" style="width:50px; height:50px; border-radius:50%; object-fit:contain; border:2px solid rgba(79, 70, 229, 0.2);">
            <?php endif; ?>
        </div>
        <div class="profile-card">
            <div class="profile-avatar"><?= strtoupper(substr($student['name'] ?? '', 0, 1)) ?></div>
            <h2 class="profile-name"><?= htmlspecialchars($student['name'] ?? '') ?></h2>
            <p class="profile-role">Student (<?= htmlspecialchars($student['student_no'] ?? 'N/A') ?>)</p>
        </div>

        <div class="nav-menu">
            <a href="<?= WEB_ROOT ?>/student/dashboard" class="nav-item active"><i class="ph ph-chart-line-up"></i> Dashboard</a>
            <a href="<?= WEB_ROOT ?>/student/attendance" class="nav-item"><i class="ph ph-fingerprint"></i> Student Clocking</a>
            
            <div class="nav-group">
                <a href="javascript:void(0)" class="nav-item" onclick="toggleSub(this)">
                    <i class="ph ph-desktop"></i> CBT <i class="ph ph-caret-down" style="margin-left:auto; font-size:12px;"></i>
                </a>
                <div class="sub-menu" style="display:none; padding-left:40px;">
                    <a href="<?= WEB_ROOT ?>/student/cbt?type=mock" class="nav-item" style="font-size:12px; opacity:0.8;">Take Mock CBT</a>
                    <a href="<?= WEB_ROOT ?>/student/cbt?type=exam" class="nav-item" style="font-size:12px; opacity:0.8;">Take Exam</a>
                    <a href="<?= WEB_ROOT ?>/student/cbt?type=result" class="nav-item" style="font-size:12px; opacity:0.8;">View Result</a>
                </div>
            </div>

            <a href="<?= WEB_ROOT ?>/student/messaging" class="nav-item"><i class="ph ph-chat-circle-dots"></i> Messaging</a>
            <a href="<?= WEB_ROOT ?>/student/payments" class="nav-item"><i class="ph ph-receipt"></i> Payment History</a>
            <a href="<?= WEB_ROOT ?>/student/timetable" class="nav-item"><i class="ph ph-calendar-blank"></i> <?= htmlspecialchars($student['class_name'] ?? 'Class') ?> Timetable</a>
            <a href="<?= WEB_ROOT ?>/student/lms" class="nav-item"><i class="ph ph-graduation-cap"></i> LMS & Lesson Notes</a>
        </div>

        <a href="<?= WEB_ROOT ?>/logout" class="nav-item" style="margin-top: auto; color: #EF4444;"><i class="ph ph-sign-out"></i> Logout</a>
    </div>

    <!-- MAIN CONTENT -->
    <div class="main-wrapper">
        
        <?php if (!$todayAtt || !$todayAtt['clock_in']): ?>
            <div style="background: linear-gradient(90deg, #F59E0B, #D97706); color: white; padding: 15px 25px; border-radius: 16px; margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 4px 15px rgba(245, 158, 11, 0.2);">
                <div style="display: flex; align-items: center; gap: 15px;">
                    <i class="ph ph-warning-circle" style="font-size: 24px;"></i>
                    <div>
                        <p style="margin: 0; font-weight: 800; font-size: 14px;">ATTENDANCE ALERT: YOU ARE NOT CLOCKED IN!</p>
                        <p style="margin: 0; font-size: 12px; opacity: 0.9;">Please mark your attendance arrival to record today's presence.</p>
                    </div>
                </div>
                <a href="<?= WEB_ROOT ?>/student/attendance" style="background: white; color: #D97706; padding: 8px 18px; border-radius: 10px; text-decoration: none; font-weight: 800; font-size: 12px;">CLOCK IN NOW</a>
            </div>
        <?php elseif (!$todayAtt['clock_out']): ?>
            <div style="background: linear-gradient(90deg, #10B981, #059669); color: white; padding: 15px 25px; border-radius: 16px; margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 4px 15px rgba(16, 185, 129, 0.2);">
                <div style="display: flex; align-items: center; gap: 15px;">
                    <i class="ph ph-check-circle" style="font-size: 24px;"></i>
                    <div>
                        <p style="margin: 0; font-weight: 800; font-size: 14px;">ARRIVED: CLOCKED IN AT <?= date('h:i A', strtotime($todayAtt['clock_in'])) ?></p>
                        <p style="margin: 0; font-size: 12px; opacity: 0.9;">Remember to clock out when leaving for the day.</p>
                    </div>
                </div>
                <a href="<?= WEB_ROOT ?>/student/attendance" style="background: white; color: #059669; padding: 8px 18px; border-radius: 10px; text-decoration: none; font-weight: 800; font-size: 12px;">CLOCK OUT CENTER</a>
            </div>
        <?php endif; ?>

        <!-- HEADER -->
        <div class="header">
            <div class="welcome-msg">
                <h1>Welcome back, <?= explode(' ', $student['name'])[0] ?> ! 👋</h1>
                <p>Track your academic achievements and daily records.</p>
            </div>
            <div class="header-tools">
                <div class="tool-icon"><i class="ph ph-magnifying-glass"></i></div>
                <a href="<?= WEB_ROOT ?>/student/messaging" class="tool-icon" style="position:relative; text-decoration:none;">
                    <i class="ph ph-bell"></i>
                    <?php if($unreadCount > 0): ?>
                        <span style="position:absolute; top:-5px; right:-5px; background:#EF4444; color:white; font-size:9px; min-width:16px; height:16px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:800; border:2px solid white;"><?= $unreadCount ?></span>
                    <?php endif; ?>
                </a>
                <div style="font-weight: 800; color: var(--primary);">
                    <?= htmlspecialchars($globalSchoolName ?? 'Rosmon International School') ?>
                </div>
            </div>
        </div>

        <!-- STATS GRID -->
        <div class="stat-grid">
            <div class="stat-card">
                <div class="stat-icon" style="background:#EEF2FF; color:var(--primary);"><i class="ph ph-buildings"></i></div>
                <div class="stat-data">
                    <h3>Class</h3>
                    <div><?= htmlspecialchars($student['class_name'] ?? 'N/A') ?></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:#ECFDF5; color:var(--secondary);"><i class="ph ph-chalkboard-teacher"></i></div>
                <div class="stat-data">
                    <h3>Class Teacher</h3>
                    <div><?= htmlspecialchars($student['teacher_name'] ?? 'Not Assigned') ?></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:#FFF7ED; color:var(--accent);"><i class="ph ph-books"></i></div>
                <div class="stat-data">
                    <h3>Total Subjects</h3>
                    <div><?= count($subjects) ?></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:#F5F3FF; color:#7C3AED;"><i class="ph ph-users-three"></i></div>
                <div class="stat-data">
                    <h3>Family</h3>
                    <div><?= $student['family_id'] ? 'ID: #'.$student['family_id'] : 'N/A' ?></div>
                </div>
            </div>
        </div>

        <!-- BODY GRID -->
        <div class="body-grid">
            <!-- LEFT COLUMN -->
            <div class="left-col">
                <!-- SUBJECTS LIST -->
                <div class="content-card">
                    <div class="card-header">
                        <h2 class="card-title">My Registered Subjects</h2>
                        <a href="#" style="color:var(--primary); font-size:12px; font-weight:800; text-decoration:none;">VIEW ALL</a>
                    </div>
                    <?php if(empty($subjects)): ?>
                        <p style="text-align:center; color:var(--text-muted); padding:30px;">No subjects registered yet.</p>
                    <?php else: ?>
                        <?php foreach($subjects as $sub): ?>
                        <div class="subject-item">
                            <div class="subject-info">
                                <div class="subject-circle"><i class="ph ph-notebook"></i></div>
                                <div>
                                    <p class="sub-name"><?= htmlspecialchars($sub['subject_name'] ?? '') ?></p>
                                    <p class="sub-teacher">Teacher: <?= htmlspecialchars($sub['teacher_name'] ?? 'Staff') ?></p>
                                </div>
                            </div>
                            <div style="color:var(--text-muted); cursor:pointer;"><i class="ph ph-paper-plane-tilt"></i></div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- ANALYTICS: FEES & PERFORMANCE -->
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px;">
                    <!-- FEE CHART -->
                    <div class="content-card" style="margin-bottom:0;">
                        <h2 class="card-title" style="margin-bottom:20px;">Fee Payment vs Term</h2>
                        <canvas id="feeChart" height="200"></canvas>
                    </div>
                    <!-- PERFORMANCE CHART -->
                    <div class="content-card" style="margin-bottom:0;">
                        <h2 class="card-title" style="margin-bottom:20px;">Psycho-behavioural Analysis</h2>
                        <canvas id="behaviourChart" height="200"></canvas>
                    </div>
                </div>
            </div>

            <!-- RIGHT COLUMN -->
            <div class="right-col">
                <!-- PUNCTUALITY WIDGET -->
                <div class="punctual-widget" style="margin-bottom:25px;">
                    <h3 style="margin-top:0; font-size:16px;">Punctuality Rate</h3>
                    <p style="font-size:12px; color:#94A3B8; margin-bottom:25px;">Based on clock-in before 8:00 AM</p>
                    <div class="radial-progress"></div>
                    <p style="font-size:13px; font-weight:600; color:#10B981;">Excellent Punctuality! 🚀</p>
                </div>

                <!-- NOTICE BOARD -->
                <div class="content-card">
                    <h2 class="card-title" style="margin-bottom:20px;">Notice Board</h2>
                    <div class="notice-item">
                        <span class="notice-tag">ANNOUNCEMENT</span>
                        <p class="notice-text">Inter-house sports competition starts next Friday. Get your jerseys ready!</p>
                    </div>
                    <div class="notice-item" style="border-left-color:var(--accent);">
                        <span class="notice-tag" style="background:#FFF7ED; color:var(--accent);">REMINDER</span>
                        <p class="notice-text">Mathematics Term project submission deadline: tomorrow 4:00 PM.</p>
                    </div>
                </div>

                <!-- MINI CALENDAR -->
                <div class="content-card" style="text-align:center;">
                    <div style="font-weight:800; font-size:16px; margin-bottom:15px; display:flex; justify-content:space-between;">
                        <span>March 2026</span>
                        <div style="display:flex; gap:10px;">
                            <i class="ph ph-caret-left"></i>
                            <i class="ph ph-caret-right"></i>
                        </div>
                    </div>
                    <div style="display:grid; grid-template-columns: repeat(7, 1fr); gap:5px; font-size:11px; font-weight:700; color:var(--text-muted);">
                        <span>S</span><span>M</span><span>T</span><span>W</span><span>T</span><span>F</span><span>S</span>
                        <?php for($i=1;$i<=31;$i++): ?>
                            <span style="padding:5px; <?= $i==21?'background:var(--primary); color:white; border-radius:50%;':'' ?>"><?= $i ?></span>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- CHARTS LOGIC -->
    <script>
        function toggleSub(el) {
            const group = el.parentElement;
            const sub = group.querySelector('.sub-menu');
            const caret = el.querySelector('.ph-caret-down');
            if (sub.style.display === 'none') {
                sub.style.display = 'block';
                if(caret) caret.style.transform = 'rotate(180deg)';
                el.style.background = '#eef2ff';
            } else {
                sub.style.display = 'none';
                if(caret) caret.style.transform = 'rotate(0deg)';
                el.style.background = 'transparent';
            }
        }

        // Fee Chart
        const ctxFee = document.getElementById('feeChart').getContext('2d');
        new Chart(ctxFee, {
            type: 'bar',
            data: {
                labels: ['Term 1', 'Term 2', 'Term 3'],
                datasets: [{
                    label: 'Billable',
                    data: [50000, 50000, 50000],
                    backgroundColor: '#E2E8F0',
                    borderRadius: 5
                }, {
                    label: 'Paid',
                    data: [50000, 42000, 0],
                    backgroundColor: '#4F46E5',
                    borderRadius: 5
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true, grid: { display: false } }, x: { grid: { display: false } } }
            }
        });

        // Behaviour Radar Chart
        const ctxBeh = document.getElementById('behaviourChart').getContext('2d');
        new Chart(ctxBeh, {
            type: 'radar',
            data: {
                labels: ['Discipline', 'Neatness', 'Politeness', 'Self Control', 'Relationship'],
                datasets: [{
                    label: 'Analysis',
                    data: [<?= $behaviour['discipline'] ?>, <?= $behaviour['neatness'] ?>, <?= $behaviour['politeness'] ?>, <?= $behaviour['self_control'] ?>, <?= $behaviour['relationship_with_others'] ?>],
                    fill: true,
                    backgroundColor: 'rgba(16, 185, 129, 0.2)',
                    borderColor: '#10B981',
                    pointBackgroundColor: '#10B981',
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: { r: { angleLines: { display: false }, suggestMin: 0, suggestMax: 100 } }
            }
        });
    </script>
</body>
</html>
