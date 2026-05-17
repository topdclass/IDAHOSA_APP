<?php
/**
 * Advanced Student Performance & Result Hub
 * Supporting Termly and Progressive Session Reports
 */
require_once dirname(dirname(dirname(__DIR__))) . '/config/database.php';
$userId = $_SESSION['user_id'] ?? 0;

// 1. Fetch Student/Class Info
$stmt = $pdo->prepare("SELECT s.id as student_id, u.full_name as name, c.class_name as class_name, 
                 c.id as class_id, s.student_no, s.family_id
          FROM institute_students s
          JOIN users u ON s.student_id = u.id
          LEFT JOIN classes c ON s.class_id = c.id
          WHERE s.student_id = ? LIMIT 1");
$stmt->execute([$userId]);
$student = $stmt->fetch();

if (!$student) { die("Profile not found."); }

$selected_term = $_GET['term'] ?? 'First Term';
$allowed_terms = ['First Term', 'Second Term', 'Third Term'];

// 2. Fetch Published Results for Selected Term
$stmt = $pdo->prepare("
    SELECT g.*, s.subject_name 
    FROM subject_grades g
    JOIN subjects s ON g.subject_id = s.id
    WHERE g.student_id = ? AND g.term = ? AND g.status = 'Approved'
");
$stmt->execute([$student['student_id'], $selected_term]);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 3. Dynamic Summary Stats
$total_score = 0;
$max_possible = count($results) * 100;
foreach($results as $r) {
    $total_score += ($r['objective_score'] + $r['theory_score']);
}
$average = $max_possible > 0 ? round(($total_score / $max_possible) * 100, 1) : 0;

// 4. Fetch Principal/Class Teacher Comments
$stmt = $pdo->prepare("SELECT * FROM report_card_comments WHERE student_id = ? AND term = ? LIMIT 1");
$stmt->execute([$student['student_id'], $selected_term]);
$comments = $stmt->fetch();

// 4. Progressive Data (Summary across all terms)
$progressive = [];
foreach ($allowed_terms as $t) {
    $pStmt = $pdo->prepare("SELECT SUM(objective_score + theory_score) as total FROM subject_grades WHERE student_id = ? AND term = ? AND status = 'Approved'");
    $pStmt->execute([$student['student_id'], $t]);
    $progressive[$t] = $pStmt->fetchColumn() ?: 0;
}

$pageTitle = 'Academic Transcript - Rosmon SMS';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <style>
        :root { --primary: #4F46E5; --bg: #F8FAFC; --border: #E2E8F0; --glass: rgba(255, 255, 255, 0.9); }
        body { margin: 0; background: var(--bg); font-family: 'Outfit', sans-serif; display: flex; color: #1E293B; }
        .sidebar { width: 280px; height: 100vh; background: #fff; border-right: 1px solid var(--border); padding: 30px 20px; position: fixed; box-shadow: 10px 0 30px rgba(0,0,0,0.02); }
        .main { flex: 1; margin-left: 280px; padding: 40px; }
        
        .nav-link { display:flex; align-items:center; gap:12px; padding:15px; text-decoration:none; color:#64748B; border-radius:12px; font-weight:600; margin-bottom:10px; transition:0.2s; }
        .nav-link:hover { background: #f1f5f9; color: var(--primary); }
        .nav-link.active { background: var(--primary); color:white; }

        .header-card { 
            background: linear-gradient(135deg, #4F46E5 0%, #7C3AED 100%); color: white; 
            padding: 40px; border-radius: 30px; margin-bottom: 30px; display: flex; justify-content: space-between; align-items: center;
            box-shadow: 0 20px 40px -15px rgba(79, 70, 229, 0.3);
        }
        
        .term-selector { display: flex; gap: 10px; margin-bottom: 30px; }
        .term-btn { 
            padding: 12px 25px; border-radius: 12px; border: 1px solid var(--border); 
            background: white; font-weight: 800; cursor: pointer; color: #64748B; text-decoration: none; 
            font-size: 14px; transition: 0.2s;
        }
        .term-btn.active { background: var(--primary); border-color: var(--primary); color: white; }

        .result-card { background: white; border-radius: 24px; padding: 30px; border: 1px solid var(--border); margin-bottom: 30px; }
        
        table { width:100%; border-collapse:collapse; }
        th { text-align:left; font-size:11px; font-weight:800; color:#94A3B8; text-transform:uppercase; padding:15px; border-bottom:1px solid #F1F5F9; }
        td { padding:18px 15px; font-weight:700; border-bottom:1px solid #F1F5F9; font-size:14px; }
        
        .grade-badge { padding:4px 10px; border-radius:8px; font-size:10px; font-weight:800; }
        .badge-success { background:#DCFCE7; color:#166534; }
        .badge-warning { background:#FEF3C7; color:#92400E; }
        
        .comment-box { display: grid; grid-template-columns: 1fr 1fr; gap: 25px; }
        .c-item { background: #F8FAFC; border: 1px solid #E2E8F0; padding: 20px; border-radius: 15px; }

        .progressive-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 30px; }
        .p-stat { background: white; padding: 20px; border-radius: 20px; border: 1px solid var(--border); text-align: center; }
    </style>
</head>
<body>
    <div class="sidebar">
        <h2 style="font-size: 16px; margin-bottom: 40px; font-weight: 800; color: var(--primary); text-align:center;">ROSMON ACADEMICS</h2>
        <a href="<?= WEB_ROOT ?>/student/dashboard" class="nav-link"><i class="ph ph-chart-line-up"></i> Dashboard</a>
        <a href="<?= WEB_ROOT ?>/student/attendance" class="nav-link"><i class="ph ph-fingerprint"></i> Attendance</a>
        <a href="<?= WEB_ROOT ?>/student/messaging" class="nav-link"><i class="ph ph-chat-circle-dots"></i> Messaging</a>
        <a href="#" class="nav-link active"><i class="ph ph-scroll"></i> Transcripts</a>
        <a href="<?= WEB_ROOT ?>/student/cbt?type=mock" class="nav-link"><i class="ph ph-exam"></i> CBT Center</a>
        <a href="<?= WEB_ROOT ?>/logout" class="nav-link" style="margin-top:auto; color:#EF4444;"><i class="ph ph-sign-out"></i> Logout</a>
    </div>

    <div class="main">
        <div class="header-card">
            <div>
                <h1 style="margin: 0; font-weight: 800;"><?= htmlspecialchars($student['name']) ?></h1>
                <p style="margin: 10px 0 0; opacity: 0.8; font-weight: 600;">Reg No: <?= $student['student_no'] ?> | Class: <?= $student['class_name'] ?></p>
            </div>
            <div style="text-align: right;">
                <span style="font-size: 12px; font-weight: 800; background: rgba(255,255,255,0.2); padding: 8px 15px; border-radius: 30px;">SESSION: 2025/2026</span>
            </div>
        </div>

        <div class="term-selector">
            <?php foreach($allowed_terms as $t): ?>
                <a href="?term=<?= urlencode($t) ?>" class="term-btn <?= $selected_term === $t ? 'active' : '' ?>"><?= $t ?></a>
            <?php endforeach; ?>
        </div>

        <?php if (empty($results)): ?>
            <div style="background:white; padding:100px 40px; border-radius:24px; text-align:center; border:1px solid var(--border);">
                <i class="ph ph-seal-warning" style="font-size:80px; color:#CBD5E1;"></i>
                <h2 style="margin-top:20px;">Termly Result Not Yet Published</h2>
                <p style="color:#64748B;">Official academic records for <strong><?= $selected_term ?></strong> have not been formally certified by the Principal.</p>
                <a href="<?= WEB_ROOT ?>/student/dashboard" style="color:var(--primary); font-weight:700; text-decoration:none;">Go back to Dashboard</a>
            </div>
        <?php else: ?>
            <div class="progressive-grid">
                <div class="p-stat">
                    <p style="margin:0; font-size:12px; font-weight:800; color:#94A3B8;">TERMLY AVERAGE</p>
                    <h2 style="margin:5px 0 0; font-weight:900; color:var(--primary);"><?= $average ?>%</h2>
                </div>
                <div class="p-stat">
                    <p style="margin:0; font-size:12px; font-weight:800; color:#94A3B8;">TOTAL AGGREGATE</p>
                    <h2 style="margin:5px 0 0; font-weight:900; color:var(--primary);"><?= $total_score ?> / <?= $max_possible ?></h2>
                </div>
                <div class="p-stat">
                    <p style="margin:0; font-size:12px; font-weight:800; color:#94A3B8;">OFFICIAL STATUS</p>
                    <h2 style="margin:5px 0 0; font-weight:900; color:#10B981;">CERTIFIED <i class="ph ph-seal-check"></i></h2>
                </div>
            </div>

            <div class="result-card">
                <h3 style="margin:0 0 20px 0; font-weight:800; display:flex; align-items:center; gap:10px;">
                    <i class="ph ph-table" style="color:var(--primary);"></i> Subject-Wise Breakdown
                </h3>
                <table>
                    <thead>
                        <tr>
                            <th>Subject</th>
                            <th>Objective</th>
                            <th>Theory</th>
                            <th>Total (100)</th>
                            <th>Grade</th>
                            <th>Remark</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($results as $r): 
                            $total = $r['objective_score'] + $r['theory_score'];
                            $grade = ($total >= 70) ? 'A' : (($total >= 60) ? 'B' : (($total >= 50) ? 'C' : 'F'));
                            $remark = ($total >= 70) ? 'Excellent' : (($total >= 60) ? 'Very Good' : (($total >= 50) ? 'Credit' : 'Fail'));
                        ?>
                            <tr>
                                <td style="color:var(--primary);"><?= htmlspecialchars($r['subject_name']) ?></td>
                                <td><?= $r['objective_score'] ?></td>
                                <td><?= $r['theory_score'] ?></td>
                                <td><?= $total ?></td>
                                <td><span class="grade-badge badge-success"><?= $grade ?></span></td>
                                <td><span style="font-size:12px; opacity:0.7;"><?= $remark ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="comment-box">
                <div class="c-item">
                    <label style="font-size:11px; font-weight:800; color:var(--primary); text-transform:uppercase; display:block; margin-bottom:10px;">Class Teacher Remark</label>
                    <p style="margin:0; font-style:italic; font-weight:600;"><?= !empty($comments['class_teacher_comment']) ? '"'.htmlspecialchars($comments['class_teacher_comment']).'"' : 'No comment from class teacher.' ?></p>
                </div>
                <div class="c-item" style="border-left: 4px solid var(--primary);">
                    <label style="font-size:11px; font-weight:800; color:var(--primary); text-transform:uppercase; display:block; margin-bottom:10px;">Principal's Final Verdict</label>
                    <p style="margin:0; font-style:italic; font-weight:600;"><?= !empty($comments['principal_comment']) ? '"'.htmlspecialchars($comments['principal_comment']).'"' : 'Official certification pending.' ?></p>
                </div>
            </div>
            
            <div style="margin-top:40px; text-align:center;">
                <button onclick="window.print()" class="term-btn" style="background:#1E293B; color:white; border:none; display:inline-flex; align-items:center; gap:8px;">
                    <i class="ph ph-printer"></i> PRINT OFFICIAL STATEMENT OF RESULT
                </button>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
