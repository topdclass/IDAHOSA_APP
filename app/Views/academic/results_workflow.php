<?php
require_once ROOT_PATH . '/config/database.php';
$schoolId = $_SESSION['school_id'] ?? 101;
$role = $_SESSION['role'] ?? 'teacher'; // mock default
$userId = $_SESSION['user_id'] ?? 1;

// Handle Actions (Subject -> Class Teacher -> Principal -> Published)
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'subject_submit') {
        // Subject teacher submits their grades for a class, changing status from Draft to Submitted
        $subjectId = $_POST['subject_id'];
        $classId = $_POST['class_id'];
        $stmt = $pdo->prepare("UPDATE subject_grades SET status = 'Submitted' WHERE subject_id = ? AND class_id = ? AND status = 'Draft'");
        $stmt->execute([$subjectId, $classId]);
        $message = "Grades submitted to the Class Teacher successfully.";
    } 
    elseif ($action === 'class_teacher_approve') {
        // Class teacher approves the term's compilation and forwards to Principal
        $classId = $_POST['class_id'];
        // Update all submitted subjects for this class to 'Class_Approved'
        $stmt = $pdo->prepare("UPDATE subject_grades SET status = 'Class_Approved' WHERE class_id = ? AND status = 'Submitted'");
        $stmt->execute([$classId]);
        
        // Also update report_card_comments to 'Pending_Principal'
        $stmt = $pdo->prepare("UPDATE report_card_comments SET status = 'Pending_Principal' WHERE class_id = ?");
        $stmt->execute([$classId]);
        
        $message = "Class results compiled, commented, and forwarded to Principal.";
    }
    elseif ($action === 'principal_approve') {
        // Principal approves everything for a class (Published)
        $classId = $_POST['class_id'];
        $stmt = $pdo->prepare("UPDATE subject_grades SET status = 'Published' WHERE class_id = ? AND status = 'Class_Approved'");
        $stmt->execute([$classId]);

        $stmt = $pdo->prepare("UPDATE report_card_comments SET status = 'Published' WHERE class_id = ?");
        $stmt->execute([$classId]);
        
        $message = "Results have been officially Published to Students and Parents.";
    }
}

// Fetch Data based on Role
$pendingTasks = [];
if ($role === 'teacher') {
    // Subject teacher: show their subjects that are in Draft
    $pendingTasks = $pdo->query("
        SELECT c.class_name, s.subject_name, g.class_id, g.subject_id, COUNT(*) as student_count
        FROM subject_grades g
        JOIN classes c ON g.class_id = c.id
        JOIN subjects s ON g.subject_id = s.id
        WHERE g.status = 'Draft'
        GROUP BY c.class_name, s.subject_name, g.class_id, g.subject_id
    ")->fetchAll(PDO::FETCH_ASSOC);
} elseif ($role === 'class_teacher' || $role === 'teacher') { 
    // Allowing 'teacher' to see class teacher view for demo if they are assigned a class
    $pendingTasks = $pdo->query("
        SELECT c.class_name, c.id as class_id, COUNT(DISTINCT g.student_id) as student_count, COUNT(DISTINCT g.subject_id) as subject_count
        FROM classes c
        JOIN subject_grades g ON c.id = g.class_id
        WHERE g.status = 'Submitted'
        GROUP BY c.class_name, c.id
    ")->fetchAll(PDO::FETCH_ASSOC);
} 

$principalTasks = [];
if ($role === 'principal' || $role === 'school_admin' || $role === 'super_admin') {
    $principalTasks = $pdo->query("
        SELECT c.class_name, c.id as class_id, COUNT(DISTINCT g.student_id) as student_count
        FROM classes c
        JOIN subject_grades g ON c.id = g.class_id
        WHERE g.status = 'Class_Approved'
        GROUP BY c.class_name, c.id
    ")->fetchAll(PDO::FETCH_ASSOC);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Result Workflow - Academic</title>
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
        .btn-primary { background: var(--primary); color: white; border: none; padding: 8px 16px; border-radius: 6px; font-weight: 600; cursor: pointer; transition: 0.2s; font-size: 13px; }
        .btn-primary:hover { background: #3730a3; }
        .btn-success { background: #10b981; color: white; border: none; padding: 8px 16px; border-radius: 6px; font-weight: 600; cursor: pointer; font-size: 13px; }
        
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid var(--border); }
        th { font-size: 12px; text-transform: uppercase; color: #64748b; background: #f8fafc; }
        td { font-size: 14px; font-weight: 500; }
        .success-msg { background: #dcfce3; color: #166534; padding: 12px; border-radius: 8px; margin-bottom: 20px; font-weight: 600; font-size: 14px; border: 1px solid #bbf7d0; }
        
        .pipeline { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; background: white; padding: 20px; border-radius: 12px; border: 1px solid var(--border); }
        .step { flex: 1; text-align: center; position: relative; }
        .step::after { content: ''; position: absolute; width: 100%; height: 2px; background: var(--border); top: 15px; left: 50%; z-index: 1; }
        .step:last-child::after { display: none; }
        .step-icon { width: 32px; height: 32px; border-radius: 50%; background: #e0e7ff; color: var(--primary); display: inline-flex; align-items: center; justify-content: center; position: relative; z-index: 2; margin-bottom: 10px; font-weight: 700; border: 2px solid white; }
        .step h4 { font-size: 13px; color: #475569; }
    </style>
</head>
<body>
    <div class="sidebar">
        <h2 style="font-size: 20px; margin-bottom: 30px;">ACADEMICS</h2>
        <a href="#" class="nav-link"><i class="ph ph-chalkboard-teacher"></i> Classes & Subjects</a>
        <a href="/academic/results-workflow" class="nav-link active"><i class="ph ph-exam"></i> Result Approval</a>
        <a href="#" class="nav-link"><i class="ph ph-desktop"></i> CBT Examiner</a>
        <div style="margin-top: auto; padding-top: 20px;">
            <a href="/" class="nav-link" style="color: #fda4af;"><i class="ph ph-sign-out"></i> Logout</a>
        </div>
    </div>

    <div class="main">
        <div class="header">
            <h1 style="font-size: 24px;">Result Card Approval Pipeline</h1>
            <p style="color: #64748b; margin-top: 5px;">Manage the publication lifecycle of student terminal reports.</p>
        </div>

        <?php if($message): ?>
            <div class="success-msg"><i class="ph ph-check-circle"></i> <?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <div class="pipeline">
            <div class="step">
                <div class="step-icon"><i class="ph ph-pencil-simple"></i></div><h4>Subject Teacher Entry</h4>
            </div>
            <div class="step">
                <div class="step-icon"><i class="ph ph-users"></i></div><h4>Class Teacher Review</h4>
            </div>
            <div class="step">
                <div class="step-icon"><i class="ph ph-seal-check"></i></div><h4>Principal Approval</h4>
            </div>
            <div class="step">
                <div class="step-icon bg-green-100" style="background:#dcfce3; color:#166534;"><i class="ph ph-check-fat"></i></div><h4>Published to Parents</h4>
            </div>
        </div>

        <!-- SUBJECT & CLASS TEACHER VIEW -->
        <?php if ($role === 'teacher' || $role === 'class_teacher'): ?>
            <div class="panel">
                <h3 style="margin-bottom: 20px;">My Pending Submissions (Subject & Class Level)</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Class / Section</th>
                            <th>Subject (if applicable)</th>
                            <th>Data Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($pendingTasks)): ?>
                            <tr><td colspan="4" style="text-align: center; color: #94a3b8; padding: 20px;">No pending grades to forward.</td></tr>
                        <?php else: ?>
                            <?php foreach($pendingTasks as $task): ?>
                                <tr>
                                    <td><?= htmlspecialchars($task['class_name']) ?></td>
                                    <td><?= isset($task['subject_name']) ? htmlspecialchars($task['subject_name']) : 'All Subjects Compiled' ?></td>
                                    <td><span style="background: #fef9c3; color: #854d0e; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 700;">Needs Forwarding</span></td>
                                    <td>
                                        <form method="POST">
                                            <?php if (isset($task['subject_id'])): ?>
                                                <input type="hidden" name="action" value="subject_submit">
                                                <input type="hidden" name="subject_id" value="<?= $task['subject_id'] ?>">
                                                <input type="hidden" name="class_id" value="<?= $task['class_id'] ?>">
                                                <button type="submit" class="btn-primary">Submit to Class Teacher</button>
                                            <?php else: ?>
                                                <input type="hidden" name="action" value="class_teacher_approve">
                                                <input type="hidden" name="class_id" value="<?= $task['class_id'] ?>">
                                                <button type="submit" class="btn-primary" style="background:#0ea5e9;">Forward to Principal</button>
                                            <?php endif; ?>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <!-- PRINCIPAL VIEW -->
        <?php if ($role === 'principal' || $role === 'school_admin' || $role === 'super_admin'): ?>
            <div class="panel" style="border-top: 4px solid #10b981;">
                <h3 style="margin-bottom: 20px;">Principal's Final Approval Queue</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Class / Section</th>
                            <th>Students Count</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($principalTasks)): ?>
                            <tr><td colspan="4" style="text-align: center; color: #94a3b8; padding: 20px;">No classes are awaiting your final approval.</td></tr>
                        <?php else: ?>
                            <?php foreach($principalTasks as $task): ?>
                                <tr>
                                    <td style="font-weight: 700;"><?= htmlspecialchars($task['class_name']) ?></td>
                                    <td><?= $task['student_count'] ?> Students compiled</td>
                                    <td><span style="background: #e0e7ff; color: #4338ca; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 700;">Awaiting Principal</span></td>
                                    <td>
                                        <form method="POST" onsubmit="return confirm('Are you sure you want to officially publish these results? Parents will be notified.')">
                                            <input type="hidden" name="action" value="principal_approve">
                                            <input type="hidden" name="class_id" value="<?= $task['class_id'] ?>">
                                            <button type="submit" class="btn-success"><i class="ph ph-check-square"></i> Approve & Publish</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

    </div>
</body>
</html>
