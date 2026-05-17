<?php
/**
 * Mock CBT Management Hub - Employee Side
 * Create and manage practice questions for students
 */
require_once ROOT_PATH . '/config/database.php';
$teacher_id = $_SESSION['user_id'] ?? 0;

// AUTO-INITIALIZE CBT TABLES IF MISSING
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS mock_questions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        subject_id INT NOT NULL,
        class_id INT NOT NULL,
        teacher_id INT NOT NULL,
        question_text TEXT NOT NULL,
        option_a VARCHAR(255) NOT NULL,
        option_b VARCHAR(255) NOT NULL,
        option_c VARCHAR(255) NOT NULL,
        option_d VARCHAR(255) NOT NULL,
        correct_option CHAR(1) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS cbt_exams (
        id INT AUTO_INCREMENT PRIMARY KEY,
        subject_id INT NOT NULL,
        class_id INT NOT NULL,
        teacher_id INT NOT NULL,
        exam_date DATE NOT NULL,
        start_time TIME NOT NULL,
        end_time TIME NOT NULL,
        duration_minutes INT DEFAULT 40,
        status ENUM('Pending', 'Active', 'Completed') DEFAULT 'Pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS cbt_questions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        exam_id INT NOT NULL,
        question_text TEXT NOT NULL,
        option_a VARCHAR(255) NOT NULL,
        option_b VARCHAR(255) NOT NULL,
        option_c VARCHAR(255) NOT NULL,
        option_d VARCHAR(255) NOT NULL,
        correct_option CHAR(1) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
} catch (Exception $e) {}
$pageTitle = 'Mock CBT Manager';

// 1. Handle Form Submissions
$message = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_question'])) {
        $stmt = $pdo->prepare("INSERT INTO mock_questions (subject_id, class_id, teacher_id, question_text, option_a, option_b, option_c, option_d, correct_option) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $_POST['subject_id'],
            $_POST['class_id'],
            $teacher_id,
            $_POST['question_text'],
            $_POST['option_a'],
            $_POST['option_b'],
            $_POST['option_c'],
            $_POST['option_d'],
            $_POST['correct_option']
        ]);
        $message = "Question added successfully!";
    } elseif (isset($_POST['delete_id'])) {
        $stmt = $pdo->prepare("DELETE FROM mock_questions WHERE id = ? AND teacher_id = ?");
        $stmt->execute([$_POST['delete_id'], $teacher_id]);
        $message = "Question removed.";
    }
}

// 2. Fetch Teacher's Assignments
$stmt = $pdo->prepare("
    SELECT cs.class_id, cs.subject_id, s.subject_name, c.class_name, c.arm 
    FROM class_subjects cs
    JOIN classes c ON cs.class_id = c.id
    JOIN subjects s ON cs.subject_id = s.id
    WHERE cs.teacher_id = ? AND cs.is_deleted = 0
");
$stmt->execute([$teacher_id]);
$assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 3. Fetch Existing Questions
$questions = $pdo->prepare("
    SELECT q.*, s.subject_name, c.class_name 
    FROM mock_questions q
    JOIN subjects s ON q.subject_id = s.id
    JOIN classes c ON q.class_id = c.id
    WHERE q.teacher_id = ?
    ORDER BY q.id DESC
");
$questions->execute([$teacher_id]);
$existingQuestions = $questions->fetchAll(PDO::FETCH_ASSOC);

require ROOT_PATH . '/app/Views/employee/layout/header.php';
?>

<div style="max-width: 1000px; margin: 0 auto;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:30px;">
        <div>
            <h2 style="margin:0; font-weight:800;">Mock CBT Manager</h2>
            <p style="color:#64748b; margin:5px 0 0 0;">Create practice questions for your students to replace dummy data.</p>
        </div>
        <button onclick="toggleForm()" style="background:var(--primary); color:white; border:none; padding:12px 20px; border-radius:10px; font-weight:800; cursor:pointer;">
            <i class="ph ph-plus"></i> Add New Question
        </button>
    </div>

    <?php if ($message): ?>
        <div style="background:#f0fdf4; color:#166534; padding:15px; border-radius:12px; margin-bottom:25px; border:1px solid #bbf7d0; font-weight:600;">
            <i class="ph ph-check-circle"></i> <?= $message ?>
        </div>
    <?php endif; ?>

    <!-- Add Form -->
    <div id="addForm" style="display:none; background:white; padding:30px; border-radius:20px; border:1px solid #e2e8f0; margin-bottom:30px;">
        <form method="POST">
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:20px;">
                <div>
                    <label style="display:block; font-size:12px; font-weight:800; color:#64748b; margin-bottom:8px;">TARGET CLASS & SUBJECT</label>
                    <select name="mapping" id="mapping" onchange="updateMapping(this)" style="width:100%; padding:12px; border-radius:8px; border:1px solid #e2e8f0; outline:none;" required>
                        <option value="">Select a class/subject</option>
                        <?php foreach($assignments as $a): ?>
                            <option value="<?= $a['class_id'] ?>|<?= $a['subject_id'] ?>"><?= $a['class_name'] ?> - <?= $a['subject_name'] ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="hidden" name="class_id" id="class_id">
                    <input type="hidden" name="subject_id" id="subject_id">
                </div>
                <div>
                    <label style="display:block; font-size:12px; font-weight:800; color:#64748b; margin-bottom:8px;">CORRECT OPTION</label>
                    <select name="correct_option" style="width:100%; padding:12px; border-radius:8px; border:1px solid #e2e8f0; outline:none;" required>
                        <option value="A">Option A</option>
                        <option value="B">Option B</option>
                        <option value="C">Option C</option>
                        <option value="D">Option D</option>
                    </select>
                </div>
            </div>

            <div style="margin-bottom:20px;">
                <label style="display:block; font-size:12px; font-weight:800; color:#64748b; margin-bottom:8px;">QUESTION TEXT</label>
                <textarea name="question_text" rows="3" style="width:100%; padding:12px; border-radius:8px; border:1px solid #e2e8f0; outline:none; resize:none;" required></textarea>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px;">
                <div>
                    <label style="display:block; font-size:11px; font-weight:800; color:#94a3b8; margin-bottom:5px;">OPTION A</label>
                    <input type="text" name="option_a" style="width:100%; padding:10px; border-radius:8px; border:1px solid #e2e8f0;" required>
                </div>
                <div>
                    <label style="display:block; font-size:11px; font-weight:800; color:#94a3b8; margin-bottom:5px;">OPTION B</label>
                    <input type="text" name="option_b" style="width:100%; padding:10px; border-radius:8px; border:1px solid #e2e8f0;" required>
                </div>
                <div>
                    <label style="display:block; font-size:11px; font-weight:800; color:#94a3b8; margin-bottom:5px;">OPTION C</label>
                    <input type="text" name="option_c" style="width:100%; padding:10px; border-radius:8px; border:1px solid #e2e8f0;" required>
                </div>
                <div>
                    <label style="display:block; font-size:11px; font-weight:800; color:#94a3b8; margin-bottom:5px;">OPTION D</label>
                    <input type="text" name="option_d" style="width:100%; padding:10px; border-radius:8px; border:1px solid #e2e8f0;" required>
                </div>
            </div>

            <div style="margin-top:25px; display:flex; gap:10px;">
                <button type="submit" name="add_question" style="background:var(--primary); color:white; border:none; padding:12px 25px; border-radius:10px; font-weight:800; cursor:pointer;">Save Question</button>
                <button type="button" onclick="toggleForm()" style="background:#f1f5f9; color:#64748b; border:none; padding:12px 25px; border-radius:10px; font-weight:800; cursor:pointer;">Cancel</button>
            </div>
        </form>
    </div>

    <!-- Questions Table -->
    <div style="background:white; border-radius:20px; border:1px solid #e2e8f0; overflow:hidden;">
        <table style="width:100%; border-collapse:collapse; font-size:14px;">
            <thead>
                <tr style="background:#f8fafc; text-align:left; border-bottom:1px solid #e2e8f0;">
                    <th style="padding:15px;">Question</th>
                    <th style="padding:15px;">Class/Subject</th>
                    <th style="padding:15px;">Answer</th>
                    <th style="padding:15px;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($existingQuestions)): ?>
                    <tr>
                        <td colspan="4" style="padding:40px; text-align:center; color:#94a3b8;">
                            <i class="ph ph-folder-open" style="font-size:40px; opacity:0.3;"></i>
                            <p>No questions uploaded yet.</p>
                        </td>
                    </tr>
                <?php endif; ?>
                <?php foreach($existingQuestions as $q): ?>
                    <tr style="border-bottom:1px solid #f1f5f9;">
                        <td style="padding:15px; max-width:300px;">
                            <div style="font-weight:700;"><?= htmlspecialchars($q['question_text']) ?></div>
                            <div style="font-size:11px; color:#94a3b8; margin-top:5px;">A: <?= $q['option_a'] ?> | B: <?= $q['option_b'] ?></div>
                        </td>
                        <td style="padding:15px;">
                            <div style="font-weight:700;"><?= $q['class_name'] ?></div>
                            <div style="font-size:11px; color:var(--primary);"><?= $q['subject_name'] ?></div>
                        </td>
                        <td style="padding:15px;"><span style="background:#e0e7ff; color:var(--primary); padding:4px 8px; border-radius:6px; font-weight:800;"><?= $q['correct_option'] ?></span></td>
                        <td style="padding:15px;">
                            <form method="POST" style="margin:0;">
                                <input type="hidden" name="delete_id" value="<?= $q['id'] ?>">
                                <button type="submit" style="color:#ef4444; background:none; border:none; cursor:pointer; font-size:18px;" onclick="return confirm('Delete this question?')">
                                    <i class="ph ph-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    function toggleForm() {
        const form = document.getElementById('addForm');
        form.style.display = (form.style.display === 'none') ? 'block' : 'none';
    }

    function updateMapping(el) {
        if (!el.value) return;
        const [classId, subjectId] = el.value.split('|');
        document.getElementById('class_id').value = classId;
        document.getElementById('subject_id').value = subjectId;
    }
</script>

<?php require ROOT_PATH . '/app/Views/employee/layout/footer.php'; ?>
