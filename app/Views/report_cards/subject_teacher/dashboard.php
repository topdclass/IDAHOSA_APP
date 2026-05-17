<?php
require_once ROOT_PATH . '/config/database.php';

$teacherId = $_SESSION['user_id'] ?? 0;

// Fetch subjects assigned to this teacher
$query = "
    SELECT 
        cs.subject_id as id, 
        s.subject_name as name, 
        c.class_name as class_level,
        c.id as class_id,
        (SELECT COUNT(*) FROM institute_students is_stud WHERE is_stud.class_id = c.id AND is_stud.is_deleted = 0) as student_count,
        (SELECT COUNT(*) FROM subject_grades sg WHERE sg.subject_id = cs.subject_id AND sg.class_id = c.id AND sg.teacher_id = ?) as graded_count
    FROM class_subjects cs
    JOIN subjects s ON cs.subject_id = s.id
    JOIN classes c ON cs.class_id = c.id
    WHERE cs.teacher_id = ? AND cs.is_deleted = 0
";
$stmt = $pdo->prepare($query);
$stmt->execute([$teacherId, $teacherId]);
$dbSubjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

$subjects = [];
foreach ($dbSubjects as $s) {
    $percentage = $s['student_count'] > 0 ? ($s['graded_count'] / $s['student_count']) * 100 : 0;
    $status = 'not_started';
    if ($percentage == 100) $status = 'completed';
    elseif ($percentage > 0) $status = 'pending';

    $subjects[] = [
        'id' => $s['id'],
        'name' => $s['name'],
        'class' => ['name' => $s['class_level']],
        'class_id' => $s['class_id'],
        'student_count' => $s['student_count'],
        'graded_count' => $s['graded_count'],
        'percentage' => $percentage,
        'status' => $status
    ];
}

function getCompletionStatus($subject) {
    return [
        'status' => $subject['status'],
        'percentage' => $subject['percentage']
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subject Teacher Dashboard</title>
    <!-- Using Google Fonts and a minimal stylesheet to emulate Material UI -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Roboto', sans-serif;
            background-color: #f5f5f5;
            margin: 0;
            padding: 24px;
            color: #1a1a1a;
        }
        .header {
            margin-bottom: 32px;
        }
        .header h1 {
            font-size: 2.125rem;
            font-weight: 600;
            margin: 0 0 8px 0;
        }
        .header p {
            color: #666;
            margin: 0;
        }
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 24px;
        }
        .card {
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 24px;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 16px rgba(0,0,0,0.2);
        }
        .card-header {
            display: flex;
            align-items: center;
            margin-bottom: 16px;
        }
        .card-header svg {
            color: #6366f1;
            width: 24px;
            height: 24px;
            margin-right: 8px;
        }
        .card-header h2 {
            font-size: 1.25rem;
            margin: 0;
            font-weight: 600;
        }
        .card-meta {
            color: #666;
            font-size: 0.875rem;
            margin-bottom: 16px;
        }
        .progress-container {
            margin-bottom: 16px;
        }
        .progress-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
            font-size: 0.875rem;
            color: #666;
        }
        .chip {
            padding: 4px 8px;
            border-radius: 16px;
            font-size: 0.75rem;
            font-weight: 500;
            text-transform: capitalize;
        }
        .chip.completed { background: #e8f5e8; color: #2e7d32; }
        .chip.pending { background: #fff3e0; color: #f57c00; }
        .chip.not_started { background: #ffebee; color: #d32f2f; }
        
        .progress-bar-bg {
            height: 8px;
            background: #f0f0f0;
            border-radius: 4px;
            overflow: hidden;
        }
        .progress-bar-fill {
            height: 100%;
            border-radius: 4px;
            transition: width 0.3s;
        }
        .btn {
            display: block;
            width: 100%;
            padding: 8px 16px;
            background: #1976d2;
            color: #fff;
            border: none;
            border-radius: 4px;
            text-align: center;
            text-decoration: none;
            font-weight: 500;
            box-sizing: border-box;
            cursor: pointer;
            text-transform: uppercase;
            font-size: 0.875rem;
            letter-spacing: 0.02857em;
        }
        .btn:hover { background: #1565c0; }
    </style>
</head>
<body>

<div class="header">
    <h1>Subject Teacher Dashboard</h1>
    <p>Manage and enter results for your assigned subjects</p>
</div>

<?php if (empty($subjects)): ?>
    <div style="text-align: center; padding: 64px 0;">
        <h2 style="color: #666;">No subjects assigned to you yet</h2>
        <p style="color: #999;">Contact your administrator to get subjects assigned</p>
    </div>
<?php else: ?>
    <div class="grid">
        <?php foreach ($subjects as $subject): 
            $completion = getCompletionStatus($subject);
            $statusStr = str_replace('_', ' ', $completion['status']);
            
            $fillColor = '#f44336';
            if ($completion['status'] === 'completed') $fillColor = '#4caf50';
            else if ($completion['status'] === 'pending') $fillColor = '#ff9800';
        ?>
            <div class="card">
                <div class="card-header">
                    <svg viewBox="0 0 256 256" fill="currentColor">
                        <path d="M216,40H40A16,16,0,0,0,24,56V200a16,16,0,0,0,16,16H216a16,16,0,0,0,16-16V56A16,16,0,0,0,216,40Zm-96,160H40V56h80V200Zm96,0H136V56h80V200Z"></path>
                    </svg>
                    <h2><?= htmlspecialchars($subject['name']) ?></h2>
                </div>
                <div class="card-meta">
                    Class: <?= htmlspecialchars($subject['class']['name'] ?? '') ?> | Students: <?= count($subject['students'] ?? []) ?>
                </div>
                <div class="progress-container">
                    <div class="progress-header">
                        <span>Progress</span>
                        <span class="chip <?= $completion['status'] ?>"><?= $statusStr ?></span>
                    </div>
                    <div class="progress-bar-bg">
                        <div class="progress-bar-fill" style="width: <?= $completion['percentage'] ?>%; background-color: <?= $fillColor ?>;"></div>
                    </div>
                    <div style="margin-top: 8px; font-size: 0.75rem; color: #666;">
                        <?= round($completion['percentage']) ?>% Complete
                    </div>
                </div>
                <a href="?enter_results=<?= $subject['id'] ?>" class="btn">Enter Results</a>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

</body>
</html>
