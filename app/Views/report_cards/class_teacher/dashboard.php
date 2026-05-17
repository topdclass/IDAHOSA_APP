<?php
require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/config/tenant_manager.php';

$teacherId = $_SESSION['user_id'] ?? 0;
$schoolId = $_SESSION['school_id'] ?? null;

// Use Teacher ID to find the class
$classStmt = $pdo->prepare("SELECT id, class_name FROM classes WHERE teacher_id = ? AND is_deleted = 0 LIMIT 1");
$classStmt->execute([$teacherId]);
$myClass = $classStmt->fetch(PDO::FETCH_ASSOC);

$students = [];
if ($myClass) {
    $classId = $myClass['id'];
    // Fetch students in this class and their report card status
    $query = "
        SELECT 
            u.id, 
            u.full_name as display_name, 
            ins.student_no,
            rc.id as report_card_id,
            rc.status,
            (SELECT COUNT(*) FROM subject_grades sg WHERE sg.student_id = u.id AND sg.class_id = ?) as completed_subjects,
            (SELECT COUNT(*) FROM class_subjects cs WHERE cs.class_id = ?) as total_subjects,
            (SELECT AVG(total_score) FROM subject_grades sg WHERE sg.student_id = u.id AND sg.class_id = ?) as avg_score
        FROM users u
        JOIN institute_students ins ON u.id = ins.student_id
        LEFT JOIN report_cards rc ON u.id = rc.student_id AND rc.class_id = ?
        WHERE ins.class_id = ? AND ins.is_deleted = 0
    ";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$classId, $classId, $classId, $classId, $classId]);
    $dbStudents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($dbStudents as $s) {
        $students[] = [
            'id' => $s['id'],
            'student_no' => $s['student_no'],
            'status' => $s['status'] ?? 'draft',
            'student' => ['display_name' => $s['display_name']],
            'class' => ['name' => $myClass['class_name'], 'total_subjects' => $s['total_subjects']],
            'completed_subjects' => $s['completed_subjects'],
            'avg_score' => round($s['avg_score'] ?? 0)
        ];
    }
}

$pendingReviews = array_filter($students, fn($s) => $s['status'] === 'pending_principal_review');
$rejectedCards = array_filter($students, fn($s) => $s['status'] === 'rejected_by_principal');
$publishedCards = array_filter($students, fn($s) => $s['status'] === 'published');

function getCompletionStatus($student) {
    $total = $student['class']['total_subjects'] ?? 0;
    $completed = $student['completed_subjects'] ?? 0;
    return [
        'total' => $total,
        'completed' => $completed,
        'percentage' => $total > 0 ? ($completed / $total) * 100 : 0
    ];
}

function getAveragePerformance($student) {
    return $student['avg_score'] ?? 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Class Teacher Dashboard</title>
    <!-- Phosphor Icons Script -->
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Roboto', sans-serif;
            background-color: #f5f5f5;
            margin: 0;
            padding: 24px;
            color: #1a1a1a;
        }
        .header { margin-bottom: 32px; }
        .header h1 { font-size: 2.125rem; font-weight: 600; margin: 0 0 8px 0; color: #1a1a1a; }
        .header p { color: #666; margin: 0; font-size: 1rem; }
        
        /* Summary Grid */
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 24px;
            margin-bottom: 32px;
        }
        @media (max-width: 900px) { .summary-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 600px) { .summary-grid { grid-template-columns: 1fr; } }
        
        .summary-card {
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 24px;
            text-align: center;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .summary-card:hover { transform: translateY(-4px); box-shadow: 0 8px 16px rgba(0,0,0,0.2); }
        .summary-card i { font-size: 32px; }
        .summary-card h4 { margin: 8px 0; font-size: 2rem; font-weight: 600; }
        .summary-card p { margin: 0; color: #666; font-size: 0.875rem; }

        /* Tabs */
        .tabs {
            display: flex;
            border-bottom: 1px solid #e0e0e0;
            margin-bottom: 24px;
        }
        .tab {
            padding: 12px 24px;
            cursor: pointer;
            font-size: 0.875rem;
            font-weight: 500;
            color: #666;
            border-bottom: 2px solid transparent;
            text-transform: uppercase;
        }
        .tab:hover { background-color: rgba(0,0,0,0.04); }
        .tab.active { color: #1976d2; border-bottom: 2px solid #1976d2; }

        /* View Containers */
        .tab-content { display: none; min-height: 400px; }
        .tab-content.active { display: block; }

        /* Student Card Grid */
        .student-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 24px;
        }
        .student-card {
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 24px;
            transition: transform 0.2s, box-shadow 0.2s;
            position: relative;
        }
        .student-card:hover { transform: translateY(-4px); box-shadow: 0 8px 16px rgba(0,0,0,0.2); }
        
        .sc-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px; }
        .sc-name { font-size: 1.25rem; font-weight: 600; margin: 0; }
        .sc-no { color: #666; font-size: 0.875rem; margin-top: 4px; }
        
        .chip {
            padding: 4px 8px;
            border-radius: 16px;
            font-size: 0.75rem;
            font-weight: 500;
            text-transform: capitalize;
        }
        .chip.draft { background: #fff3e0; color: #f57c00; }
        .chip.pending_principal_review { background: #e3f2fd; color: #1976d2; }
        .chip.rejected_by_principal { background: #ffebee; color: #d32f2f; }
        .chip.published { background: #e8f5e8; color: #2e7d32; }

        .progress-box { margin-bottom: 16px; }
        .progress-text { font-size: 0.875rem; color: #666; margin-bottom: 8px; }
        .progress-bg { height: 6px; background: #f0f0f0; border-radius: 3px; overflow: hidden; }
        .progress-fill { height: 100%; border-radius: 3px; }

        .avg-score { font-size: 0.875rem; color: #666; margin-bottom: 16px; }
        
        .actions { display: flex; gap: 8px; }
        .icon-btn { 
            background: none; border: none; cursor: pointer; border-radius: 50%; width: 32px; height: 32px; 
            display: flex; align-items: center; justify-content: center; transition: background 0.2s;
        }
        .icon-btn:hover { background: rgba(0,0,0,0.04); }
        .icon-btn.eye i { color: #1976d2; }
        .icon-btn.edit i { color: #2e7d32; }
        .icon-btn.alert i { color: #f57c00; }

        /* Tables for other tabs */
        .table-container {
            background: #fff; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow: hidden; padding-bottom: 8px;
        }
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th { font-size: 0.875rem; color: #1a1a1a; font-weight: 500; padding: 16px; border-bottom: 1px solid #e0e0e0; }
        td { font-size: 0.875rem; color: #1a1a1a; padding: 16px; border-bottom: 1px solid #e0e0e0; }
        tr:hover td { background-color: rgba(0,0,0,0.02); }
    </style>
</head>
<body>

<div class="header">
    <h1>Class Teacher Dashboard</h1>
    <p>Manage report cards for your class students</p>
</div>

<!-- Summary Cards -->
<div class="summary-grid">
    <div class="summary-card">
        <i class="ph-fill ph-users" style="color: #6366f1;"></i>
        <h4><?= count($students) ?></h4>
        <p>Total Students</p>
    </div>
    <div class="summary-card">
        <i class="ph-fill ph-clock" style="color: #f57c00;"></i>
        <h4><?= count($pendingReviews) ?></h4>
        <p>Pending Review</p>
    </div>
    <div class="summary-card">
        <i class="ph-fill ph-warning-circle" style="color: #ef4444;"></i>
        <h4><?= count($rejectedCards) ?></h4>
        <p>Rejected</p>
    </div>
    <div class="summary-card">
        <i class="ph-fill ph-check-circle" style="color: #10b981;"></i>
        <h4><?= count($publishedCards) ?></h4>
        <p>Published</p>
    </div>
</div>

<!-- Tabs Architecture -->
<div class="tabs">
    <div class="tab active" onclick="switchTab(0)">All Students (<?= count($students) ?>)</div>
    <div class="tab" onclick="switchTab(1)">Pending Review (<?= count($pendingReviews) ?>)</div>
    <div class="tab" onclick="switchTab(2)">Rejected (<?= count($rejectedCards) ?>)</div>
    <div class="tab" onclick="switchTab(3)">Published (<?= count($publishedCards) ?>)</div>
</div>

<!-- All Students Grid View -->
<div class="tab-content active" id="tab0">
    <div class="student-grid">
        <?php foreach ($students as $student): 
            $comp = getCompletionStatus($student);
            $avg = getAveragePerformance($student);
            $statusFixed = str_replace('_', ' ', $student['status']);
            $progressColor = $comp['percentage'] == 100 ? '#4caf50' : '#ff9800';
        ?>
        <div class="student-card">
            <div class="sc-header">
                <div>
                    <h2 class="sc-name"><?= htmlspecialchars($student['student']['display_name']) ?></h2>
                    <div class="sc-no"><?= htmlspecialchars($student['student_no']) ?></div>
                </div>
                <div class="chip <?= $student['status'] ?>"><?= htmlspecialchars($statusFixed) ?></div>
            </div>
            
            <div class="progress-box">
                <div class="progress-text">Subject Progress: <?= $comp['completed'] ?>/<?= $comp['total'] ?></div>
                <div class="progress-bg">
                    <div class="progress-fill" style="width: <?= $comp['percentage'] ?>%; background-color: <?= $progressColor ?>;"></div>
                </div>
            </div>
            
            <?php if ($comp['completed'] > 0): ?>
            <div class="avg-score">Average Score: <?= $avg ?>%</div>
            <?php endif; ?>
            
                <a href="/report-card/view?id=<?= $student['report_card_id'] ?>" class="icon-btn eye" title="View Details"><i class="ph ph-eye"></i></a>
                <?php if ($student['status'] === 'draft' && $comp['percentage'] == 100): ?>
                    <button class="icon-btn edit" title="Add Comments & Submit"><i class="ph ph-pencil-simple"></i></button>
                <?php endif; ?>
                <?php if ($student['status'] === 'rejected_by_principal'): ?>
                    <button class="icon-btn alert" title="Address Rejection"><i class="ph ph-warning-circle"></i></button>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Pending Review Table View -->
<div class="tab-content" id="tab1">
   <div class="table-container">
       <table>
           <thead>
               <tr>
                   <th>Student Name</th>
                   <th>Student No</th>
                   <th>Class</th>
                   <th>Subjects Complete</th>
                   <th>Average Score</th>
                   <th>Status</th>
                   <th>Actions</th>
               </tr>
           </thead>
           <tbody>
               <?php foreach($pendingReviews as $student): 
                   $comp = getCompletionStatus($student);
                   $avg = getAveragePerformance($student);
               ?>
               <tr>
                   <td style="font-weight: 500;"><?= htmlspecialchars($student['student']['display_name']) ?></td>
                   <td><?= htmlspecialchars($student['student_no']) ?></td>
                   <td><?= htmlspecialchars($student['class']['name']) ?></td>
                   <td>
                       <div style="display: flex; align-items: center; gap: 8px;">
                           <span><?= $comp['completed'] ?>/<?= $comp['total'] ?></span>
                           <div style="width: 60px; height: 4px; background: #f0f0f0; border-radius: 2px;">
                               <div style="width: <?= $comp['percentage'] ?>%; height: 100%; background: #ff9800; border-radius: 2px;"></div>
                           </div>
                       </div>
                   </td>
                   <td><?= $comp['completed'] > 0 ? "{$avg}%" : "N/A" ?></td>
                   <td><span class="chip <?= $student['status'] ?>"><?= str_replace('_', ' ', $student['status']) ?></span></td>
                   <td class="actions">
                       <button class="icon-btn eye" title="View"><i class="ph ph-eye"></i></button>
                   </td>
               </tr>
               <?php endforeach; ?>
           </tbody>
       </table>
   </div>
</div>

<!-- Repeated Table for Rejected -->
<div class="tab-content" id="tab2">
   <div class="table-container">
       <table>
           <thead>
               <tr>
                   <th>Student Name</th><th>Student No</th><th>Status</th><th>Actions</th>
               </tr>
           </thead>
           <tbody>
               <?php foreach($rejectedCards as $student): ?>
               <tr>
                   <td style="font-weight: 500;"><?= htmlspecialchars($student['student']['display_name']) ?></td>
                   <td><?= htmlspecialchars($student['student_no']) ?></td>
                   <td><span class="chip <?= $student['status'] ?>"><?= str_replace('_', ' ', $student['status']) ?></span></td>
                   <td class="actions"><button class="icon-btn alert"><i class="ph ph-warning-circle"></i></button></td>
               </tr>
               <?php endforeach; ?>
           </tbody>
       </table>
   </div>
</div>

<!-- Repeated Table for Published -->
<div class="tab-content" id="tab3">
   <div class="table-container">
       <table>
           <thead>
               <tr>
                   <th>Student Name</th><th>Student No</th><th>Status</th><th>Actions</th>
               </tr>
           </thead>
           <tbody>
               <?php foreach($publishedCards as $student): ?>
               <tr>
                   <td style="font-weight: 500;"><?= htmlspecialchars($student['student']['display_name']) ?></td>
                   <td><?= htmlspecialchars($student['student_no']) ?></td>
                   <td><span class="chip <?= $student['status'] ?>"><?= str_replace('_', ' ', $student['status']) ?></span></td>
                   <td class="actions"><button class="icon-btn eye"><i class="ph ph-eye"></i></button></td>
               </tr>
               <?php endforeach; ?>
           </tbody>
       </table>
   </div>
</div>

<script>
    function switchTab(index) {
        document.querySelectorAll('.tab').forEach((el, i) => {
            el.classList.toggle('active', i === index);
        });
        document.querySelectorAll('.tab-content').forEach((el, i) => {
            el.classList.toggle('active', i === index);
        });
    }
</script>

</body>
</html>
