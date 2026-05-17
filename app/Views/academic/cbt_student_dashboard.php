<?php
require_once ROOT_PATH . '/config/database.php';
$schoolId = $_SESSION['school_id'] ?? 101;
// Mock student ID & Class ID for demo if not logged in
$studentId = $_SESSION['user_id'] ?? 2; 

// Find the student's class
$stmt = $pdo->prepare("SELECT class_id FROM institute_students WHERE student_id = ?");
$stmt->execute([$studentId]);
$classData = $stmt->fetch(PDO::FETCH_ASSOC);
$classId = $classData['class_id'] ?? 1;

// Fetch Available Exams (where current date is between start_date and end_date)
$now = date('Y-m-d H:i:s');
$stmt = $pdo->prepare("
    SELECT a.*, 
           (SELECT id FROM assessment_results r WHERE r.student_id = ? AND r.assessment_id = a.id) as is_taken
    FROM assessments a
    WHERE a.class_id = ?
    ORDER BY a.start_date DESC
");
$stmt->execute([$studentId, $classId]);
$exams = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student CBT Portal</title>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <style>
        :root { --primary: #0284c7; --bg: #f0f9ff; --text: #0f172a; --border: #bae6fd; }
        * { box-sizing: border-box; font-family: 'Inter', sans-serif; margin: 0; padding: 0; }
        body { background: var(--bg); display: flex; color: var(--text); min-height: 100vh; }
        .sidebar { width: 260px; height: 100vh; background: #0c4a6e; color: white; padding: 20px; position: fixed; }
        .main { flex: 1; margin-left: 260px; padding: 30px; }
        .nav-link { display: flex; align-items: center; gap: 12px; padding: 12px; color: #7dd3fc; text-decoration: none; border-radius: 8px; margin-bottom: 5px; }
        .nav-link:hover, .nav-link.active { background: rgba(255,255,255,0.1); color: white; }
        .header { margin-bottom: 30px; border-bottom: 1px solid var(--border); padding-bottom: 15px; }
        
        .exam-card { background: white; padding: 25px; border-radius: 12px; border: 1px solid var(--border); box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); transition: 0.2s; position: relative; overflow: hidden; }
        .exam-card:hover { transform: translateY(-3px); box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); }
        .exam-card::before { content: ''; position: absolute; top: 0; left: 0; width: 4px; height: 100%; background: var(--primary); }
        
        .exam-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 20px; }
        
        .btn { display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px; border-radius: 6px; font-weight: 600; cursor: pointer; text-decoration: none; font-size: 14px; transition: 0.2s; }
        .btn-primary { background: var(--primary); color: white; border: none; }
        .btn-primary:hover { background: #0369a1; }
        .btn-disabled { background: #e2e8f0; color: #94a3b8; pointer-events: none; }
        .btn-success { background: #10b981; color: white; }
        
        .meta-row { display: flex; gap: 15px; font-size: 13px; color: #64748b; margin: 15px 0 20px; }
        .meta-row span { display: flex; align-items: center; gap: 5px; }
        
        .status-badge { position: absolute; top: 20px; right: 20px; font-size: 11px; font-weight: 700; padding: 4px 8px; border-radius: 4px; text-transform: uppercase; }
        .status-active { background: #dcfce3; color: #166534; }
        .status-upcoming { background: #fef9c3; color: #854d0e; }
        .status-closed { background: #fee2e2; color: #991b1b; }
        .status-completed { background: #e0e7ff; color: #3730a3; }
    </style>
</head>
<body>
    <div class="sidebar">
        <h2 style="font-size: 20px; margin-bottom: 30px;">STUDENT ORBIT</h2>
        <a href="/student/dashboard" class="nav-link"><i class="ph ph-squares-four"></i> Dashboard</a>
        <a href="/student/classes" class="nav-link"><i class="ph ph-books"></i> My Classes</a>
        <a href="/student/cbt-dashboard" class="nav-link active"><i class="ph ph-desktop"></i> CBT Portal</a>
        <div style="margin-top: auto; padding-top: 20px;">
            <a href="/" class="nav-link" style="color: #fda4af;"><i class="ph ph-sign-out"></i> Logout</a>
        </div>
    </div>

    <div class="main">
        <div class="header">
            <h1 style="font-size: 24px;">Computer Based Testing</h1>
            <p style="color: #64748b; margin-top: 5px;">Manage your objective examinations online.</p>
        </div>

        <div class="exam-grid">
            <?php if (empty($exams)): ?>
                <div style="grid-column: 1/-1; padding: 40px; text-align: center; color: #64748b; background: white; border-radius: 12px;">
                    <i class="ph ph-folder-dashed" style="font-size: 48px; color: #cbd5e1; margin-bottom: 10px;"></i>
                    <p>No exams have been scheduled for your class yet.</p>
                </div>
            <?php else: ?>
                <?php foreach($exams as $exam): 
                    $isTaken = $exam['is_taken'] ? true : false;
                    $start = new DateTime($exam['start_date']);
                    $end = new DateTime($exam['end_date']);
                    $current = new DateTime($now);
                    
                    if ($isTaken) {
                        $status = 'Completed';
                        $statusClass = 'status-completed';
                        $btnClass = 'btn-success';
                        $btnText = 'Review Score';
                        $btnLink = '#'; // In a real app, link to score view if allowed
                    } elseif ($current < $start) {
                        $status = 'Upcoming';
                        $statusClass = 'status-upcoming';
                        $btnClass = 'btn-disabled';
                        $btnText = 'Not Available Yet';
                        $btnLink = '#';
                    } elseif ($current > $end) {
                        $status = 'Closed';
                        $statusClass = 'status-closed';
                        $btnClass = 'btn-disabled';
                        $btnText = 'Exam Closed';
                        $btnLink = '#';
                    } else {
                        $status = 'Active';
                        $statusClass = 'status-active';
                        $btnClass = 'btn-primary';
                        $btnText = '<i class="ph ph-play-circle"></i> Start Exam';
                        $btnLink = '/student/cbt-exam?id=' . $exam['id'];
                    }
                    
                    // Parse question count
                    $qCount = 0;
                    if (!empty($exam['questions'])) {
                        $qArr = json_decode($exam['questions'], true);
                        if(is_array($qArr)) $qCount = count($qArr);
                    }
                ?>
                    <div class="exam-card">
                        <div class="<?= $statusClass ?> status-badge"><?= $status ?></div>
                        <h3 style="font-size: 18px; margin-bottom: 5px; padding-right: 70px;"><?= htmlspecialchars($exam['title']) ?></h3>
                        
                        <div class="meta-row">
                            <span><i class="ph ph-timer"></i> <?= htmlspecialchars($exam['duration']) ?></span>
                            <span><i class="ph ph-list-numbers"></i> <?= $qCount ?> Questions</span>
                        </div>
                        
                        <div style="font-size: 13px; color: #475569; margin-bottom: 20px; line-height: 1.5;">
                            <strong>Window Opens:</strong> <?= $start->format('d M, Y h:i A') ?><br>
                            <strong>Window Closes:</strong> <?= $end->format('d M, Y h:i A') ?>
                        </div>
                        
                        <a href="<?= $btnLink ?>" class="btn <?= $btnClass ?>" style="width: 100%; justify-content: center;">
                            <?= $btnText ?>
                        </a>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
