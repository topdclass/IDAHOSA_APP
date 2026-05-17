<?php
$pageTitle = 'Attendance Intelligence - Student Engagement';
require ROOT_PATH . '/app/Views/employee/layout/header.php';

$teacher_id = $_SESSION['user_id'] ?? 0;
$message = '';
$selected_class = $_GET['class_id'] ?? null;
$date = $_GET['date'] ?? date('Y-m-d');

// 1. Handle Attendance Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_attendance'])) {
    $class_id = $_POST['class_id'];
    $student_ids = $_POST['student_ids'] ?? [];
    $statuses = $_POST['status'] ?? [];
    
    $pdo->beginTransaction();
    try {
        foreach ($student_ids as $sid) {
            $status = $statuses[$sid] ?? 'Absent';
            $stmt = $pdo->prepare("INSERT INTO student_attendance_logs (student_id, attendance_date, status) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE status = VALUES(status)");
            $stmt->execute([$sid, $date, $status]);
        }
        $pdo->commit();
        $message = "Engagement data synchronized for " . date('M d, Y', strtotime($date));
    } catch (Exception $e) { $pdo->rollBack(); $message = "Sync Error: " . $e->getMessage(); }
}

// 2. Fetch Classes
$stmt = $pdo->prepare("SELECT id, class_name, arm FROM classes WHERE (teacher_id = ? OR id IN (SELECT class_id FROM class_subjects WHERE teacher_id = ? AND is_deleted = 0)) AND is_deleted = 0 ORDER BY class_name ASC");
$stmt->execute([$teacher_id, $teacher_id]);
$my_classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 3. Fetch Students & Stats
$students = []; $stats = ['Present' => 0, 'Absent' => 0, 'Late' => 0];
if ($selected_class) {
    $stmt = $pdo->prepare("SELECT s.id, u.full_name, s.student_no, u.photo_url FROM institute_students s JOIN users u ON s.student_id = u.id WHERE s.class_id = ? AND s.is_deleted = 0 ORDER BY u.full_name ASC");
    $stmt->execute([$selected_class]);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt = $pdo->prepare("SELECT student_id, status FROM student_attendance_logs WHERE attendance_date = ? AND student_id IN (SELECT id FROM institute_students WHERE class_id = ?)");
    $stmt->execute([$date, $selected_class]);
    $existing = $stmt->fetchAll(PDO::FETCH_UNIQUE | PDO::FETCH_ASSOC);
    foreach($existing as $e) { if(isset($stats[$e['status']])) $stats[$e['status']]++; }
}
?>

<style>
    .att-header { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 30px; }
    .class-selector { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 30px; background: white; padding: 20px; border-radius: 20px; border: 1px solid #f1f5f9; box-shadow: 0 4px 15px rgba(0,0,0,0.02); }
    .class-btn { padding: 12px 20px; border-radius: 12px; font-weight: 700; font-size: 13px; text-decoration: none; color: #64748b; background: #f8fafc; border: 1px solid #eef2f6; transition: 0.2s; }
    .class-btn:hover { background: #eef2ff; color: var(--primary); }
    .class-btn.active { background: var(--primary); color: white; border-color: var(--primary); box-shadow: 0 8px 15px -3px rgba(79, 70, 229, 0.3); }

    .stat-bar { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 30px; }
    .mini-stat { background: white; padding: 15px 25px; border-radius: 16px; border: 1px solid #f1f5f9; display: flex; align-items: center; gap: 15px; }

    .attendance-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 20px; }
    .student-card { background: white; border-radius: 20px; padding: 20px; border: 1px solid #f1f5f9; transition: 0.2s; display: flex; flex-direction: column; gap: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.02); }
    .student-card:hover { transform: translateY(-3px); box-shadow: 0 10px 25px -5px rgba(0,0,0,0.05); }
    .st-photo { width: 48px; height: 48px; border-radius: 12px; background: #eff6ff; display: flex; align-items: center; justify-content: center; font-size: 20px; color: #3b82f6; overflow: hidden; }
    
    .status-group { display: flex; gap: 5px; background: #f8fafc; padding: 5px; border-radius: 12px; border: 1px solid #f1f5f9; }
    .status-btn { flex: 1; text-align: center; padding: 8px; font-size: 11px; font-weight: 800; border-radius: 8px; cursor: pointer; border: 1px solid transparent; transition: 0.2s; color: #94a3af; }
    
    input[type="radio"] { display: none; }
    input[type="radio"]:checked + .st-present { background: #dcfce7; color: #15803d; border-color: #10b981; }
    input[type="radio"]:checked + .st-absent { background: #fee2e2; color: #b91c1c; border-color: #ef4444; }
    input[type="radio"]:checked + .st-late { background: #fef3c7; color: #92400e; border-color: #f59e0b; }
</style>

<div class="att-header">
    <div>
        <h1 style="font-size:24px; font-weight:900; color:#111827; margin:0 0 5px 0;">Engagement Monitoring</h1>
        <p style="color:#64748b; font-size:14px; margin:0;">Track student participation and physical presence for the current module.</p>
    </div>
    <div style="background:white; padding:8px 15px; border-radius:12px; border:1px solid #f1f5f9; font-weight:800; font-size:13px; color:var(--primary); display:flex; align-items:center; gap:8px;">
        <i class="ph ph-calendar"></i> <?= date('F d, Y', strtotime($date)) ?>
    </div>
</div>

<?php if ($message): ?>
    <div style="background:#dcfce7; color:#15803d; padding:15px 25px; border-radius:12px; margin-bottom:25px; font-weight:700; display:flex; align-items:center; gap:10px;">
        <i class="ph ph-check-circle"></i> <?= $message ?>
    </div>
<?php endif; ?>

<div class="class-selector">
    <?php foreach($my_classes as $mc): ?>
        <a href="?class_id=<?= $mc['id'] ?>&date=<?= $date ?>" class="class-btn <?= $selected_class == $mc['id'] ? 'active' : '' ?>">
            <?= htmlspecialchars($mc['class_name']) ?> <?= htmlspecialchars($mc['arm']) ?>
        </a>
    <?php endforeach; ?>
</div>

<?php if ($selected_class): ?>
    <div class="stat-bar">
        <div class="mini-stat">
            <div style="width:10px; height:24px; background:#10b981; border-radius:4px;"></div>
            <div>
                <p style="margin:0; font-size:10px; color:#94a3b8; font-weight:800;">PRESENT</p>
                <div style="font-size:18px; font-weight:900;"><?= $stats['Present'] ?></div>
            </div>
        </div>
        <div class="mini-stat">
            <div style="width:10px; height:24px; background:#ef4444; border-radius:4px;"></div>
            <div>
                <p style="margin:0; font-size:10px; color:#94a3b8; font-weight:800;">ABSENT</p>
                <div style="font-size:18px; font-weight:900;"><?= $stats['Absent'] ?></div>
            </div>
        </div>
        <div class="mini-stat">
            <div style="width:10px; height:24px; background:#f59e0b; border-radius:4px;"></div>
            <div>
                <p style="margin:0; font-size:10px; color:#94a3b8; font-weight:800;">LATE</p>
                <div style="font-size:18px; font-weight:900;"><?= $stats['Late'] ?></div>
            </div>
        </div>
    </div>

    <form method="POST">
        <input type="hidden" name="class_id" value="<?= $selected_class ?>">
        <div class="attendance-grid">
            <?php foreach($students as $s): 
                $status = $existing[$s['id']]['status'] ?? null;
            ?>
                <div class="student-card">
                    <div style="display:flex; align-items:center; gap:15px;">
                        <input type="hidden" name="student_ids[]" value="<?= $s['id'] ?>">
                        <div class="st-photo">
                            <?php if ($s['photo_url']): ?>
                                <img src="<?= WEB_ROOT . $s['photo_url'] ?>" style="width:100%; height:100%; object-fit:cover;">
                            <?php else: ?>
                                <i class="ph ph-user"></i>
                            <?php endif; ?>
                        </div>
                        <div style="overflow:hidden;">
                            <div style="font-weight:800; color:#1e293b; font-size:15px; white-space:nowrap; text-overflow:ellipsis; overflow:hidden;" title="<?= htmlspecialchars($s['full_name']) ?>">
                                <?= htmlspecialchars($s['full_name']) ?>
                            </div>
                            <div style="font-size:11px; color:#94a3b8; font-weight:600;"><?= htmlspecialchars($s['student_no']) ?></div>
                        </div>
                    </div>
                    
                    <div class="status-group">
                        <label style="flex:1;">
                            <input type="radio" name="status[<?= $s['id'] ?>]" value="Present" <?= $status === 'Present' ? 'checked' : '' ?>>
                            <div class="status-btn st-present">PRESENT</div>
                        </label>
                        <label style="flex:1;">
                            <input type="radio" name="status[<?= $s['id'] ?>]" value="Absent" <?= ($status === 'Absent' || !$status) ? 'checked' : '' ?>>
                            <div class="status-btn st-absent">ABSENT</div>
                        </label>
                        <label style="flex:1;">
                            <input type="radio" name="status[<?= $s['id'] ?>]" value="Late" <?= $status === 'Late' ? 'checked' : '' ?>>
                            <div class="status-btn st-late">LATE</div>
                        </label>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div style="margin-top:40px; padding:30px; background:white; border-radius:24px; border:1px solid #f1f5f9; display:flex; justify-content:space-between; align-items:center;">
            <div>
                <h4 style="margin:0; font-size:16px;">Finalize Attendance?</h4>
                <p style="margin:5px 0 0 0; font-size:12px; color:#94a3b8;">Once saved, engagement stats will update globally.</p>
            </div>
            <button type="submit" name="save_attendance" style="background:var(--primary); color:white; border:none; padding:15px 40px; border-radius:12px; font-weight:800; font-size:15px; cursor:pointer; box-shadow: 0 10px 15px -3px rgba(79, 70, 229, 0.4);">
                Commit Engagement Data
            </button>
        </div>
    </form>
<?php else: ?>
    <div style="text-align:center; padding:100px 20px; background:white; border-radius:24px; border:1px dashed #cbd5e1; margin-top:20px;">
        <i class="ph ph-hand-fingers-closed" style="font-size:64px; opacity:0.1; margin-bottom:20px; display:inline-block;"></i>
        <h3 style="margin:0; color:#475569;">Select a class to begin</h3>
        <p style="color:#94a3b8; font-size:14px; margin-top:8px;">Choose one of your assigned departments from the list above.</p>
    </div>
<?php endif; ?>

<?php require ROOT_PATH . '/app/Views/employee/layout/footer.php'; ?>
