<?php
/**
 * Student Class Timetable Center
 */
require_once dirname(dirname(dirname(__DIR__))) . '/config/database.php';
$userId = $_SESSION['user_id'] ?? 0;

// 1. Fetch Student/Class Info
$stmt = $pdo->prepare("SELECT u.full_name as name, c.class_name as class_name, s.student_no, s.class_id
                      FROM institute_students s
                      JOIN users u ON s.student_id = u.id
                      LEFT JOIN classes c ON s.class_id = c.id
                      WHERE s.student_id = ? LIMIT 1");
$stmt->execute([$userId]);
$student = $stmt->fetch();

if (!$student) die("Student profile not found.");

$classId = $student['class_id'];
$className = $student['class_name'] ?? 'Your Class';

// 2. Fetch Weekly Structure
$weekdays = $pdo->query("SELECT * FROM tt_weekdays ORDER BY sort_order ASC")->fetchAll();
$periods = $pdo->query("SELECT * FROM tt_periods ORDER BY start_time ASC")->fetchAll();

// 3. Fetch Timetable Entries for THIS class
$stmt = $pdo->prepare("SELECT t.*, s.name as subject_name 
                      FROM timetables t
                      LEFT JOIN class_subjects s ON t.subject_id = s.id
                      WHERE t.class_id = ?");
$stmt->execute([$classId]);
$entries = $stmt->fetchAll();

// Map entries for easy grid display: [day_id][period_id]
$tt_map = [];
foreach($entries as $e) {
    $tt_map[$e['day_id']][$e['period_id']] = $e['subject_name'] ?? 'Undefined';
}

$pageTitle = "$className Timetable - Rosmon SMS";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <style>
        :root {
            --primary: #4f46e5; --primary-light: #eef2ff;
            --text-dark: #1e293b; --text-muted: #64748b;
            --bg: #f8fafc; --border: #e2e8f0;
            --white: #ffffff;
        }
        * { box-sizing: border-box; font-family: 'Outfit', sans-serif; }
        body { margin: 0; background: var(--bg); color: var(--text-dark); display: flex; min-height: 100vh; }

        .sidebar { width: 270px; background: var(--white); border-right: 1px solid var(--border); padding: 30px 20px; position: fixed; height: 100vh; }
        .main { flex: 1; margin-left: 270px; padding: 40px; }

        .nav-item { display:flex; align-items:center; gap:12px; padding:12px 16px; text-decoration:none; color:var(--text-muted); border-radius:10px; margin-bottom:5px; font-weight:600; font-size:14px; transition: 0.2s; }
        .nav-item.active { background: var(--primary-light); color: var(--primary); }
        .nav-item:hover { background: #f1f5f9; }

        .header { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:40px; }
        .tt-container { background: var(--white); border-radius: 20px; border: 1px solid var(--border); box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02); overflow: hidden; }
        
        table { width:100%; border-collapse:collapse; table-layout: fixed; }
        th { background: #F9FAFB; padding:20px; text-align:center; font-size:12px; font-weight:800; color:var(--text-muted); text-transform:uppercase; border-bottom:1px solid var(--border); border-right: 1px solid var(--border); }
        th:last-child { border-right: none; }
        
        td { padding:15px; border-bottom:1px solid #F1F5F9; border-right: 1px solid #F1F5F9; text-align:center; height: 80px; font-weight: 600; font-size: 13px; vertical-align: middle; }
        td:last-child { border-right: none; }
        td:first-child { width: 180px; background: #F9FAFB; font-weight: 800; color: var(--primary); font-size: 11px; }

        .subject-box { background: var(--primary-light); color: var(--primary); padding: 10px; border-radius:8px; border: 1px solid #E0E7FF; line-height: 1.4; transition: 0.2s; cursor: default; }
        .subject-box:hover { transform: scale(1.02); background: var(--primary); color: white; }

        .break-box { background: #F1F5F9; color: #94A3B8; font-style: italic; border-radius: 8px; font-weight: 700; text-transform: uppercase; font-size: 11px; }

        .status-pill { background: #10B981; color: white; padding: 6px 14px; border-radius: 20px; font-size: 11px; font-weight: 800; text-transform: uppercase; display: inline-flex; align-items: center; gap: 6px; }

        @media print {
            .sidebar, .print-btn { display: none; }
            .main { margin: 0; padding: 0; }
            .tt-container { border: 1px solid #000; box-shadow: none; border-radius: 0; }
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <h2 style="font-size: 16px; margin-bottom: 40px; font-weight: 800; color: var(--primary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?= htmlspecialchars($globalSchoolName ?? 'Rosmon International School') ?>"><?= strtoupper(htmlspecialchars($globalSchoolName ?? 'Rosmon International School')) ?></h2>
        <div class="nav-menu">
            <a href="dashboard" class="nav-item">
                <i class="ph ph-chart-line-up"></i> Dashboard
            </a>
            <a href="attendance" class="nav-item">
                <i class="ph ph-fingerprint"></i> Student Clocking
            </a>
            <div class="nav-group">
                <a href="javascript:void(0)" class="nav-item" onclick="toggleSub(this)">
                    <i class="ph ph-desktop"></i> CBT <i class="ph ph-caret-down" style="margin-left:auto; font-size:12px;"></i>
                </a>
                <div class="sub-menu" style="display:none; padding-left:25px;">
                    <a href="#" class="nav-item" style="font-size:12px;">Take Mock CBT</a>
                    <a href="#" class="nav-item" style="font-size:12px;">Take Exam</a>
                    <a href="#" class="nav-item" style="font-size:12px;">View Result</a>
                </div>
            </div>
            <a href="<?= WEB_ROOT ?>/student/messaging" class="nav-item">
                <i class="ph ph-chat-circle-dots"></i> Messaging
            </a>
            <a href="payments" class="nav-item">
                <i class="ph ph-receipt"></i> Payment history
            </a>
            <a href="timetable" class="nav-item active">
                <i class="ph ph-calendar-blank"></i> <?= htmlspecialchars($className) ?> Timetable
            </a>
        </div>
    </div>

    <div class="main">
        <div class="header">
            <div>
                <h1 style="font-size:32px; font-weight:900; margin:0;"><?= htmlspecialchars($className) ?> Schedule</h1>
                <p style="color:var(--text-muted); font-weight:600; margin-top:10px;">Weekly academic routine and period distribution</p>
                <div class="status-pill" style="margin-top:15px;">
                    <i class="ph ph-circle-wavy-check"></i> ACTIVE SESSION: 2025/2026
                </div>
            </div>
            <button onclick="window.print()" class="print-btn" style="background:var(--white); border:1px solid var(--border); padding:12px 25px; border-radius:12px; font-weight:800; color:var(--text-dark); cursor:pointer; display:flex; align-items:center; gap:10px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                <i class="ph ph-printer" style="font-size:20px;"></i> PRINT TIMETABLE
            </button>
        </div>

        <div class="tt-container">
            <table>
                <thead>
                    <tr>
                        <th style="width: 180px;">TIME & PERIOD</th>
                        <?php foreach($weekdays as $day): ?>
                            <th><?= $day['day_name'] ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($periods as $per): ?>
                        <tr>
                            <td>
                                <div style="font-weight: 800; font-size:12px;"><?= $per['period_name'] ?></div>
                                <div style="font-size:10px; color:var(--text-muted);"><?= date('h:i A', strtotime($per['start_time'])) ?> - <?= date('h:i A', strtotime($per['end_time'])) ?></div>
                            </td>
                            <?php foreach($weekdays as $day): 
                                $sub = $tt_map[$day['id']][$per['id']] ?? null;
                            ?>
                                <td>
                                    <?php if($per['is_break']): ?>
                                        <div class="break-box">BREAK</div>
                                    <?php elseif($sub): ?>
                                        <div class="subject-box"><?= htmlspecialchars($sub) ?></div>
                                    <?php else: ?>
                                        <div style="color:#d1d5db; font-size:11px;">-- FREE --</div>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($periods)): ?>
                        <tr><td colspan="<?= count($weekdays) + 1 ?>" style="text-align:center; padding:50px; color:var(--text-muted);">No timetable schedule set for your class yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div style="margin-top:40px; background:var(--white); border-radius:16px; border:1px solid var(--border); padding:25px; display:flex; gap:30px; align-items:center;">
            <div style="font-size:20px; color:var(--primary); font-weight:800; border-right: 1px solid var(--border); padding-right:30px;">
                <i class="ph ph-info"></i> Notes
            </div>
            <div style="font-size:13px; color:var(--text-muted); font-weight:600; line-height:1.6;">
                • All students are expected to be in their respective classrooms 5 minutes before each period.<br>
                • Use of electronic gadgets is strictly prohibited during academic hours unless authorized.<br>
                • Timetable changes will be communicated via the official announcement board.
            </div>
        </div>
    </div>

    <script>
        function toggleSub(el) {
            const group = el.nextElementSibling;
            if (group.style.display === 'none') {
                group.style.display = 'block';
                el.querySelector('.ph-caret-down').style.transform = 'rotate(180deg)';
                el.style.background = '#eef2ff';
            } else {
                group.style.display = 'none';
                el.querySelector('.ph-caret-down').style.transform = 'rotate(0deg)';
                el.style.background = 'transparent';
            }
        }
    </script>
</body>
</html>
