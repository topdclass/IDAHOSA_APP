<?php
require_once ROOT_PATH . '/config/database.php';
$me = $_SESSION['user_id'] ?? 0;

// Auto-initialize Schema if not exists (Multi-tenant safety)
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS lesson_notes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        subject_id INT NOT NULL, teacher_id INT NOT NULL, week_number INT NOT NULL,
        topic VARCHAR(255), content LONGTEXT, status ENUM('Pending', 'Approved', 'Disapproved') DEFAULT 'Pending',
        admin_remark TEXT, created_by_name VARCHAR(150), updated_by_name VARCHAR(150),
        created_year YEAR, updated_year YEAR, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS theory_questions (
        id INT AUTO_INCREMENT PRIMARY KEY, lesson_note_id INT NOT NULL, subject_id INT NOT NULL,
        question_text TEXT NOT NULL, option_a VARCHAR(255), option_b VARCHAR(255), option_c VARCHAR(255),
        option_d VARCHAR(255), correct_option CHAR(1) DEFAULT 'A', section_label VARCHAR(50) DEFAULT 'A',
        difficulty ENUM('Easy', 'Medium', 'Hard') DEFAULT 'Medium', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS student_subject_progress (
        id INT AUTO_INCREMENT PRIMARY KEY, student_id INT NOT NULL, subject_id INT NOT NULL,
        current_week INT DEFAULT 1, last_completed_at TIMESTAMP NULL,
        UNIQUE KEY `student_subject` (`student_id`, `subject_id`)
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS student_quiz_attempts (
        id INT AUTO_INCREMENT PRIMARY KEY, student_id INT NOT NULL, lesson_note_id INT NOT NULL,
        score INT NOT NULL, total_questions INT NOT NULL, passed TINYINT(1) DEFAULT 0,
        attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
} catch (Exception $e) {}
$class_id = $_GET['class_id'] ?? null;
$subject_id = $_GET['subject_id'] ?? null;

if (!$class_id || !$subject_id) {
    header("Location: " . WEB_ROOT . "/employee/classes");
    exit;
}

// 1. Fetch Class and Subject Info
$stmt = $pdo->prepare("SELECT class_name, arm FROM classes WHERE id = ?");
$stmt->execute([$class_id]);
$class = $stmt->fetch();

$stmt = $pdo->prepare("SELECT subject_name FROM subjects WHERE id = ?");
$stmt->execute([$subject_id]);
$subject_name = $stmt->fetchColumn();

// 2. Fetch Students and their LMS progress
$stmt = $pdo->prepare("
    SELECT s.id as student_id_internal, u.id as user_id, u.full_name, s.student_no,
           p.current_week, p.last_completed_at,
           (SELECT score FROM student_quiz_attempts WHERE student_id = u.id ORDER BY id DESC LIMIT 1) as last_score,
           (SELECT total_questions FROM student_quiz_attempts WHERE student_id = u.id ORDER BY id DESC LIMIT 1) as total_q,
           (SELECT passed FROM student_quiz_attempts WHERE student_id = u.id ORDER BY id DESC LIMIT 1) as has_passed
    FROM institute_students s
    JOIN users u ON s.student_id = u.id
    LEFT JOIN student_subject_progress p ON (p.student_id = u.id AND p.subject_id = ?)
    WHERE s.class_id = ? AND s.is_deleted = 0
    ORDER BY u.full_name ASC
");
$stmt->execute([$subject_id, $class_id]);
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'LMS Monitor - ' . $subject_name;
require ROOT_PATH . '/app/Views/employee/layout/header.php';
?>

<div style="display:flex; justify-content:space-between; align-items:flex-end; margin-bottom:30px;">
    <div>
        <h1 style="font-size:24px; font-weight:900; color:#111827; margin:0 0 8px 0; display:flex; align-items:center; gap:12px;">
            <i class="ph-fill ph-monitor-play" style="color:#e11d48"></i>
            LMS Progress Monitor
        </h1>
        <p style="color:#6b7280; font-size:14px; margin:0;">
            Tracking <strong><?= htmlspecialchars($subject_name) ?></strong> for 
            <strong><?= htmlspecialchars($class['class_name'] ?? '') ?> <?= htmlspecialchars($class['arm'] ?? '') ?></strong>
        </p>
    </div>
    <a href="<?= WEB_ROOT ?>/employee/classes" style="background:#f1f5f9; color:#475569; padding:10px 20px; border-radius:10px; text-decoration:none; font-weight:700; font-size:13px;">
        <i class="ph ph-arrow-left"></i> Back to Hub
    </a>
</div>

<div class="crud-card" style="background:white; border-radius:20px; border:1px solid #f1f5f9; padding:25px;">
    <table class="crud-table" style="width:100%; border-collapse:collapse;">
        <thead>
            <tr style="text-align:left; border-bottom:2px solid #f1f5f9;">
                <th style="padding:15px; font-size:11px; font-weight:800; color:#94a3b8; text-transform:uppercase;">Student Name</th>
                <th style="padding:15px; font-size:11px; font-weight:800; color:#94a3b8; text-transform:uppercase;">Current Week</th>
                <th style="padding:15px; font-size:11px; font-weight:800; color:#94a3b8; text-transform:uppercase;">Last Quiz Score</th>
                <th style="padding:15px; font-size:11px; font-weight:800; color:#94a3b8; text-transform:uppercase;">Last Activity</th>
                <th style="padding:15px; font-size:11px; font-weight:800; color:#94a3b8; text-transform:uppercase; text-align:right;">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($students as $s): 
                $room = 'dm_' . ($me < $s['user_id'] ? $me . '_' . $s['user_id'] : $s['user_id'] . '_' . $me);
            ?>
                <tr style="border-bottom:1px solid #f8fafc;">
                    <td style="padding:15px;">
                        <div style="font-weight:700; color:#1e293b; font-size:14px;"><?= htmlspecialchars($s['full_name']) ?></div>
                        <div style="font-size:11px; color:#94a3b8;"><?= htmlspecialchars($s['student_no']) ?></div>
                    </td>
                    <td style="padding:15px;">
                        <span style="background:#f1f5f9; color:#475569; padding:4px 10px; border-radius:20px; font-size:12px; font-weight:800;">
                            Week <?= $s['current_week'] ?? 1 ?>
                        </span>
                    </td>
                    <td style="padding:15px;">
                        <?php if($s['last_score'] !== null): ?>
                            <span style="font-weight:800; color:<?= $s['has_passed'] ? '#10b981' : '#ef4444' ?>;">
                                <?= $s['last_score'] ?>/<?= $s['total_q'] ?>
                            </span>
                        <?php else: ?>
                            <span style="color:#cbd5e1; font-style:italic; font-size:12px;">No attempt</span>
                        <?php     endif; ?>
                    </td>
                    <td style="padding:15px; color:#64748b; font-size:12px;">
                        <?= $s['last_completed_at'] ? date('M d, Y h:i A', strtotime($s['last_completed_at'])) : 'Never' ?>
                    </td>
                    <td style="padding:15px; text-align:right;">
                        <a href="<?= WEB_ROOT ?>/employee/messages?room=<?= $room ?>" style="color:var(--primary); text-decoration:none; font-weight:800; font-size:12px; display:inline-flex; align-items:center; gap:6px;">
                            <i class="ph ph-chat-circle-dots" style="font-size:18px;"></i> Message
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require ROOT_PATH . '/app/Views/employee/layout/footer.php'; ?>
