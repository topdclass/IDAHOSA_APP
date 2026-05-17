<?php
// Teacher Lesson Note & Theory Bank Workflow - Modernized UI
require_once ROOT_PATH . '/config/database.php';

// Auto-initialize Schema if not exists (Multi-tenant safety)
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS lesson_notes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        subject_id INT NOT NULL,
        teacher_id INT NOT NULL,
        week_number INT NOT NULL,
        topic VARCHAR(255),
        content LONGTEXT,
        status ENUM('Pending', 'Approved', 'Disapproved') DEFAULT 'Pending',
        admin_remark TEXT,
        created_by_name VARCHAR(150),
        updated_by_name VARCHAR(150),
        created_year YEAR,
        updated_year YEAR,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS theory_questions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        lesson_note_id INT NOT NULL,
        subject_id INT NOT NULL,
        question_text TEXT NOT NULL,
        option_a VARCHAR(255),
        option_b VARCHAR(255),
        option_c VARCHAR(255),
        option_d VARCHAR(255),
        correct_option CHAR(1) DEFAULT 'A',
        section_label VARCHAR(50) DEFAULT 'A',
        difficulty ENUM('Easy', 'Medium', 'Hard') DEFAULT 'Medium',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
} catch (Exception $e) {}

$teacher_id = $_SESSION['user_id'] ?? 0;
$teacher_name = $_SESSION['username'] ?? 'Teacher';
$message = '';
$error = '';

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'save_note') {
        $subject_id = (int)$_POST['subject_id'];
        $week = (int)$_POST['week_number'];
        $topic = $_POST['topic'] ?? '';
        $content = $_POST['content'] ?? '';
        $questions = $_POST['questions'] ?? [];
        $note_id = isset($_POST['note_id']) ? (int)$_POST['note_id'] : 0;
        $adapting_id = isset($_POST['adapting_id']) ? (int)$_POST['adapting_id'] : 0;

        $valid_questions = array_filter($questions, function($q) { return !empty(trim($q)); });
        if (count($valid_questions) < 10) {
            $error = "You must provide at least 10 assessment questions for this lesson note.";
        } else {
            try {
                $pdo->beginTransaction();
                if ($note_id > 0) {
                    $stmt = $pdo->prepare("UPDATE lesson_notes SET topic=?, content=?, status='Pending', updated_by_name=?, updated_year=? WHERE id=? AND teacher_id=?");
                    $stmt->execute([$topic, $content, $teacher_name, date('Y'), $note_id, $teacher_id]);
                } else {
                    $created_by = $teacher_name;
                    $created_year = date('Y');
                    if ($adapting_id > 0) {
                        $orig = $pdo->prepare("SELECT created_by_name, created_year FROM lesson_notes WHERE id = ?");
                        $orig->execute([$adapting_id]);
                        $oData = $orig->fetch();
                        if ($oData) { $created_by = $oData['created_by_name']; $created_year = $oData['created_year']; }
                    }
                    $stmt = $pdo->prepare("INSERT INTO lesson_notes (subject_id, teacher_id, week_number, topic, content, status, created_by_name, created_year, updated_by_name, updated_year) VALUES (?, ?, ?, ?, ?, 'Pending', ?, ?, ?, ?)");
                    $stmt->execute([$subject_id, $teacher_id, $week, $topic, $content, $created_by, $created_year, $teacher_name, date('Y')]);
                    $note_id = $pdo->lastInsertId();
                }

                $pdo->prepare("DELETE FROM theory_questions WHERE lesson_note_id = ?")->execute([$note_id]);
                $qStmt = $pdo->prepare("INSERT INTO theory_questions (lesson_note_id, subject_id, question_text, option_a, option_b, option_c, option_d, correct_option) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $options_a = $_POST['option_a'] ?? []; $options_b = $_POST['option_b'] ?? []; $options_c = $_POST['option_c'] ?? []; $options_d = $_POST['option_d'] ?? []; $corrects = $_POST['correct_option'] ?? [];

                foreach ($valid_questions as $index => $qText) {
                    $qStmt->execute([$note_id, $subject_id, $qText, $options_a[$index] ?? '', $options_b[$index] ?? '', $options_c[$index] ?? '', $options_d[$index] ?? '', $corrects[$index] ?? 'A']);
                }
                $pdo->commit();
                $message = "Curriculum content submitted for approval!";
            } catch (Exception $e) { $pdo->rollBack(); $error = "Save failed: " . $e->getMessage(); }
        }
    }
}

// Fetch subjects and existing notes
$subjects = $pdo->query("SELECT id, subject_name FROM subjects ORDER BY subject_name ASC")->fetchAll(PDO::FETCH_ASSOC);
$myNotes = $pdo->prepare("SELECT n.*, s.subject_name as subject_name FROM lesson_notes n JOIN subjects s ON n.subject_id = s.id WHERE n.teacher_id = ? ORDER BY n.created_at DESC");
$myNotes->execute([$teacher_id]);
$myNotesList = $myNotes->fetchAll(PDO::FETCH_ASSOC);

$approvedNotes = $pdo->query("SELECT n.*, s.subject_name as subject_name FROM lesson_notes n JOIN subjects s ON n.subject_id = s.id WHERE n.status = 'Approved' ORDER BY n.created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Lesson Planning Hub - Rosmon SMS';
require ROOT_PATH . '/app/Views/employee/layout/header.php';
?>

<style>
    .planning-header { margin-bottom: 30px; }
    .planning-title { font-size: 24px; font-weight: 900; color: #111827; margin: 0 0 8px 0; display: flex; align-items: center; gap: 12px; }
    .planning-subtitle { font-size: 14px; color: #6b7280; margin: 0; }

    /* Modern Tabs */
    .tab-bar { display: flex; gap: 8px; margin-bottom: 25px; background: #fff; padding: 6px; border-radius: 12px; border: 1px solid #f1f5f9; width: fit-content; }
    .tab-btn { border: none; background: transparent; padding: 10px 20px; font-size: 13px; font-weight: 700; color: #64748b; cursor: pointer; border-radius: 8px; transition: 0.2s; }
    .tab-btn:hover { color: var(--primary); background: var(--primary-light); }
    .tab-btn.active { background: var(--primary); color: white; box-shadow: 0 4px 12px rgba(79, 70, 229, 0.2); }

    /* Cards & Grids */
    .note-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 20px; }
    .note-card { background: white; border-radius: 16px; border: 1px solid #f1f5f9; padding: 20px; transition: 0.2s; position: relative; overflow: hidden; display: flex; flex-direction: column; }
    .note-card:hover { border-color: var(--primary-light); box-shadow: 0 10px 25px -5px rgba(0,0,0,0.05); }
    
    .status-badge { font-size: 10px; font-weight: 800; padding: 4px 10px; border-radius: 20px; text-transform: uppercase; }
    .status-Pending { background: #fef3c7; color: #92400e; }
    .status-Approved { background: #d1fae5; color: #065f46; }
    .status-Disapproved { background: #fee2e2; color: #991b1b; }

    .form-section { background: white; border-radius: 20px; border: 1px solid #f1f5f9; padding: 30px; margin-bottom: 30px; }
    .form-grid { display: grid; grid-template-columns: 2fr 1.2fr; gap: 30px; }
    
    .q-item { background: #f8fafc; border: 1px solid #f1f5f9; border-radius: 12px; padding: 20px; margin-bottom: 20px; }
    
    @media (max-width: 1024px) {
        .form-grid { grid-template-columns: 1fr; }
    }

    /* Modal Viewer */
    #viewModal { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center; padding: 20px; }
    .modal-content { background:white; border-radius:24px; width:100%; max-width:800px; max-height:90vh; overflow-y:auto; padding:40px; position:relative; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); }

    input, select, textarea { width: 100%; padding: 12px; border: 1px solid #e2e8f0; border-radius: 10px; font-size: 14px; outline: none; transition: 0.2s; background: #fcfdfe; }
    input:focus, select:focus, textarea:focus { border-color: var(--primary); background: #fff; box-shadow: 0 0 0 4px var(--primary-light); }
</style>

<div class="planning-header">
    <h1 class="planning-title">
        <i class="ph-fill ph-notebook" style="color:var(--primary)"></i>
        Lesson Planning Hub
    </h1>
    <p class="planning-subtitle">Manage your weekly curriculum, prepare lesson notes, and build your theoretical question bank.</p>
</div>

<!-- Modern Tab Bar -->
<div class="tab-bar">
    <button onclick="switchTab('my_notes')" class="tab-btn active" id="btn_my_notes">My Portfolio</button>
    <button onclick="switchTab('create_note')" class="tab-btn" id="btn_create_note">Prepare New Note</button>
    <button onclick="switchTab('browse_notes')" class="tab-btn" id="btn_browse_notes">Global Library</button>
</div>

<?php if ($message): ?>
    <div style="background:#dcfce7; color:#15803d; padding:15px 25px; border-radius:12px; margin-bottom:25px; font-size:14px; font-weight:700; display:flex; align-items:center; gap:10px;">
        <i class="ph ph-check-circle"></i> <?= $message ?>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div style="background:#fee2e2; color:#b91c1c; padding:15px 25px; border-radius:12px; margin-bottom:25px; font-size:14px; font-weight:700; display:flex; align-items:center; gap:10px;">
        <i class="ph ph-warning-circle"></i> <?= $error ?>
    </div>
<?php endif; ?>

<!-- MY NOTES TAB -->
<div id="tab_my_notes" class="tab-content">
    <div class="note-grid">
        <?php if (empty($myNotesList)): ?>
            <div style="grid-column: 1/-1; text-align:center; padding:100px 20px; background:#f8fafc; border-radius:24px; border:1px dashed #cbd5e1;">
                <i class="ph ph-note-blank" style="font-size:64px; opacity:0.1; margin-bottom:20px;"></i>
                <h3 style="margin:0; color:#475569;">No notes found</h3>
                <p style="color:#94a3b8; font-size:14px; margin-top:8px;">Start by preparing your first curriculum note.</p>
            </div>
        <?php endif; ?>
        <?php foreach ($myNotesList as $n): ?>
            <div class="note-card">
                <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:15px;">
                    <span class="status-badge status-<?= $n['status'] ?>"><?= $n['status'] ?></span>
                    <span style="font-size:11px; font-weight:800; color:#94a3b8;">WEEK <?= $n['week_number'] ?></span>
                </div>
                <div style="font-size:12px; font-weight:800; color:var(--primary); margin-bottom:5px; text-transform:uppercase;"><?= $n['subject_name'] ?></div>
                <h3 style="margin:0 0 15px 0; font-size:16px; color:#1e293b; line-height:1.4;"><?= htmlspecialchars($n['topic']) ?></h3>
                
                <p style="font-size:13px; color:#64748b; margin-top:auto; padding-top:15px; border-top:1px solid #f8fafc;">
                    Last activity: <?= date('M d, Y', strtotime($n['updated_at'])) ?>
                </p>
                
                <div style="display:flex; gap:8px; margin-top:15px;">
                    <button onclick="viewNote(<?= htmlspecialchars(json_encode($n)) ?>)" class="tab-btn" style="flex:1; background:#f1f5f9; color:#475569; padding:8px;">View</button>
                    <?php if ($n['status'] === 'Disapproved'): ?>
                        <button onclick="editNote(<?= htmlspecialchars(json_encode($n)) ?>)" class="tab-btn" style="flex:1; background:var(--primary); color:white; padding:8px;">Fix</button>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- CREATE NOTE TAB -->
<form id="tab_create_note" class="tab-content" style="display:none;" method="POST" action="">
    <input type="hidden" name="action" value="save_note">
    <input type="hidden" name="note_id" id="edit_note_id" value="">
    <input type="hidden" name="adapting_id" id="adapting_id" value="">

    <div class="form-grid">
        <div class="form-section">
            <h2 style="margin:0 0 25px 0; font-size:18px;">Curriculum Content</h2>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:20px;">
                <div>
                    <label style="font-size:11px; font-weight:800; color:#64748b; display:block; margin-bottom:8px;">SUBJECT</label>
                    <select name="subject_id" id="f_subject_id" required>
                        <option value="">Select Subject</option>
                        <?php foreach ($subjects as $s): ?><option value="<?= $s['id'] ?>"><?= $s['subject_name'] ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label style="font-size:11px; font-weight:800; color:#64748b; display:block; margin-bottom:8px;">WEEK NUMBER</label>
                    <input type="number" name="week_number" id="f_week" required min="1" max="15">
                </div>
            </div>
            <div style="margin-bottom:20px;">
                <label style="font-size:11px; font-weight:800; color:#64748b; display:block; margin-bottom:8px;">LESSON TOPIC</label>
                <input type="text" name="topic" id="f_topic" required placeholder="e.g. Molecular Biology basics">
            </div>
            <div>
                <label style="font-size:11px; font-weight:800; color:#64748b; display:block; margin-bottom:8px;">DETAILED LESSON CONTENT</label>
                <textarea name="content" id="f_content" required rows="18" placeholder="Break down the topic for your students..."></textarea>
            </div>
        </div>

        <div>
            <div class="form-section">
                <h2 style="margin:0 0 10px 0; font-size:18px;">Assessment Bank</h2>
                <p style="font-size:12px; color:#64748b; margin-bottom:25px;">MCQ questions for weekly LMS testing.</p>
                
                <div id="question_wrapper" style="max-height: 800px; overflow-y: auto; padding-right: 10px;">
                    <?php for($i=0; $i<10; $i++): ?>
                        <div class="q-item">
                            <label style="font-size:10px; font-weight:900; color:var(--primary); margin-bottom:10px; display:block;">QUESTION <?= $i+1 ?></label>
                            <textarea name="questions[]" rows="2" placeholder="Enter Question" style="margin-bottom:12px;"></textarea>
                            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px;">
                                <input type="text" name="option_a[]" placeholder="A">
                                <input type="text" name="option_b[]" placeholder="B">
                                <input type="text" name="option_c[]" placeholder="C">
                                <input type="text" name="option_d[]" placeholder="D">
                            </div>
                            <div style="margin-top:12px; display:flex; align-items:center; gap:10px;">
                                <span style="font-size:11px; font-weight:700;">Correct:</span>
                                <select name="correct_option[]" style="width: auto; padding: 5px;">
                                    <option value="A">A</option><option value="B">B</option><option value="C">C</option><option value="D">D</option>
                                </select>
                            </div>
                        </div>
                    <?php endfor; ?>
                    <div id="more_questions"></div>
                </div>
                
                <button type="button" onclick="addQuestion()" style="width:100%; margin-top:15px; padding:12px; background:#f8fafc; border:2px dashed var(--primary); color:var(--primary); font-weight:700; border-radius:12px; cursor:pointer;">+ Add Question</button>
            </div>

            <button type="submit" style="width:100%; padding:20px; background:var(--primary); color:white; border:none; border-radius:16px; font-weight:800; font-size:15px; cursor:pointer; box-shadow: 0 10px 15px -3px rgba(79, 70, 229, 0.4);">
                Submit for Approval
            </button>
        </div>
    </div>
</form>

<!-- BROWSE TAB -->
<div id="tab_browse_notes" class="tab-content" style="display:none;">
    <div class="note-grid">
        <?php foreach ($approvedNotes as $an): ?>
            <div class="note-card">
                <div style="font-size:12px; font-weight:800; color:var(--primary); margin-bottom:5px;"><?= $an['subject_name'] ?></div>
                <h3 style="margin:0 0 12px 0; font-size:16px;"><?= htmlspecialchars($an['topic']) ?></h3>
                <p style="font-size:13px; color:#64748b; line-height:1.5; height:60px; overflow:hidden;"><?= mb_strimwidth(strip_tags($an['content']), 0, 150, "...") ?></p>
                <div style="margin-top:auto; padding-top:15px; border-top:1px solid #f8fafc; display:flex; justify-content:space-between; align-items:center;">
                    <span style="font-size:11px; color:#94a3b8;">By <?= $an['created_by_name'] ?></span>
                    <button onclick="adaptNote(<?= htmlspecialchars(json_encode($an)) ?>)" class="tab-btn" style="background:var(--primary-light); color:var(--primary); padding:6px 12px; font-size:11px;">Copy & Adapt</button>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Modal Viewer -->
<div id="viewModal" onclick="if(event.target == this) closeModal()">
    <div class="modal-content">
        <button onclick="closeModal()" style="position:absolute; right:25px; top:25px; border:none; background:#f1f5f9; width:40px; height:40px; border-radius:50%; font-size:20px; cursor:pointer;">&times;</button>
        <div id="modalContent"></div>
    </div>
</div>

<script>
function switchTab(tabId) {
    document.querySelectorAll('.tab-content').forEach(t => t.style.display = 'none');
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('tab_' + tabId).style.display = (tabId === 'tab_create_note' ? 'block' : (tabId === 'tab_my_notes' ? 'block' : 'grid'));
    
    // The previous logic I wrote for display types was a bit off, let's fix
    if (tabId === 'my_notes') {
        document.getElementById('tab_my_notes').style.display = 'block';
    } else if (tabId === 'create_note') {
        document.getElementById('tab_create_note').style.display = 'block';
    } else {
        document.getElementById('tab_browse_notes').style.display = 'block';
    }
    
    document.getElementById('btn_' + tabId).classList.add('active');
}

function addQuestion() {
    const wrapper = document.getElementById('more_questions');
    const div = document.createElement('div');
    div.className = 'q-item';
    div.innerHTML = `<label style="font-size:10px; font-weight:900; color:var(--primary); margin-bottom:10px; display:block;">ADDITIONAL QUESTION</label>
                     <textarea name="questions[]" rows="2" placeholder="Enter Question" style="margin-bottom:12px;"></textarea>
                     <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px;">
                        <input type="text" name="option_a[]" placeholder="A"> <input type="text" name="option_b[]" placeholder="B">
                        <input type="text" name="option_c[]" placeholder="C"> <input type="text" name="option_d[]" placeholder="D">
                     </div>
                     <div style="margin-top:12px; display:flex; align-items:center; gap:10px;">
                        <span style="font-size:11px; font-weight:700;">Correct:</span>
                        <select name="correct_option[]" style="width: auto; padding: 5px;"><option value="A">A</option><option value="B">B</option><option value="C">C</option><option value="D">D</option></select>
                     </div>`;
    wrapper.appendChild(div);
}

function adaptNote(note) {
    resetForm();
    document.getElementById('f_subject_id').value = note.subject_id;
    document.getElementById('f_topic').value = note.topic;
    document.getElementById('f_content').value = note.content;
    document.getElementById('adapting_id').value = note.id;
    switchTab('create_note');
    document.getElementById('btn_create_note').innerText = "Adapting Mode";
}

function editNote(note) {
    resetForm();
    document.getElementById('edit_note_id').value = note.id;
    document.getElementById('f_subject_id').value = note.subject_id;
    document.getElementById('f_week').value = note.week_number;
    document.getElementById('f_topic').value = note.topic;
    document.getElementById('f_content').value = note.content;
    if (note.admin_remark) alert("Correction Note: " + note.admin_remark);
    switchTab('create_note');
}

function viewNote(note) {
    const html = `
        <div style="margin-bottom:30px; border-bottom:1px solid #f1f5f9; padding-bottom:20px;">
            <span style="font-size:12px; font-weight:800; color:var(--primary); text-transform:uppercase;">${note.subject_name} \ Week ${note.week_number}</span>
            <h1 style="font-size:28px; font-weight:900; margin-top:10px; color:#1e293b;">${note.topic}</h1>
        </div>
        <div style="background:#f8fafc; padding:30px; border-radius:18px; line-height:1.8; font-size:16px; color:#334155;">
            ${note.content.replace(/\n/g, '<br>')}
        </div>
        <div style="margin-top:30px; padding-top:20px; border-top:1px solid #f1f5f9; color:#94a3b8; font-size:12px;">
            Submitted by ${note.created_by_name} \ Current Status: ${note.status}
        </div>
    `;
    document.getElementById('modalContent').innerHTML = html;
    document.getElementById('viewModal').style.display = 'flex';
}

function closeModal() { document.getElementById('viewModal').style.display = 'none'; }
function resetForm() {
    document.getElementById('edit_note_id').value = '';
    document.getElementById('adapting_id').value = '';
    document.getElementById('f_subject_id').value = '';
    document.getElementById('f_week').value = '';
    document.getElementById('f_topic').value = '';
    document.getElementById('f_content').value = '';
    document.getElementById('more_questions').innerHTML = '';
    document.getElementById('btn_create_note').innerText = "Prepare New Note";
}
</script>

<?php require ROOT_PATH . '/app/Views/employee/layout/footer.php'; ?>
