<?php
// CBT Question Bank Management Logic

try {
    
    // Auto Migration: CBT Question Infrastructure
    $pdo->exec("CREATE TABLE IF NOT EXISTS cbt_question_bank (
        id INT AUTO_INCREMENT PRIMARY KEY,
        subject_id INT NOT NULL,
        question TEXT NOT NULL,
        marks DECIMAL(5,2) DEFAULT 1.00,
        difficulty ENUM('Easy', 'Medium', 'Hard') DEFAULT 'Medium',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS cbt_options (
        id INT AUTO_INCREMENT PRIMARY KEY,
        question_id INT NOT NULL,
        option_text TEXT NOT NULL,
        is_correct TINYINT(1) DEFAULT 0,
        FOREIGN KEY (question_id) REFERENCES cbt_question_bank(id) ON DELETE CASCADE
    )");

    // Handle Template Download
    if (isset($_GET['download_template'])) {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="cbt_question_template.csv"');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Question Text', 'Image Filename (optional)', 'Option A', 'Option B', 'Option C', 'Option D', 'Correct Option Index (0-3)', 'Difficulty (Easy/Medium/Hard)', 'Marks']);
        fputcsv($output, ['What is the capital of Nigeria?', 'nigeria_map.png', 'Lagos', 'Abuja', 'Kano', 'Enugu', '1', 'Easy', '2.00']);
        fclose($output);
        exit;
    }

    $message = '';

    // Handle Bulk Upload
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['bulk_file'])) {
        $file = $_FILES['bulk_file']['tmp_name'];
        $bulk_subj_id = $_POST['bulk_subject_id'] ?? null;
        if (($handle = fopen($file, "r")) !== FALSE && $bulk_subj_id) {
            fgetcsv($handle); // Skip header
            $pdo->beginTransaction();
            try {
                $count = 0;
                while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                    if (count($data) < 9) continue;
                    $subj = $bulk_subj_id;
                    $qtxt = $data[0];
                    $img_filename = trim($data[1] ?? '');
                    $opts = [$data[2], $data[3], $data[4], $data[5]];
                    $corr = (int)$data[6];
                    $diff = $data[7];
                    $mrks = $data[8];

                    $image_url = null;
                    if (!empty($img_filename) && !empty($_FILES['bulk_images']['name'][0])) {
                        $idx = array_search($img_filename, $_FILES['bulk_images']['name']);
                        if ($idx !== false && $_FILES['bulk_images']['error'][$idx] === 0) {
                            $tmpName = $_FILES['bulk_images']['tmp_name'][$idx];
                            $newName = time() . '_' . basename($_FILES['bulk_images']['name'][$idx]);
                            $dest = ROOT_PATH . '/public/uploads/cbt_images/' . $newName;
                            if (move_uploaded_file($tmpName, $dest)) {
                                $image_url = 'uploads/cbt_images/' . $newName;
                            }
                        }
                    }

                    $stmt = $pdo->prepare("INSERT INTO cbt_question_bank (subject_id, question, image_url, marks, difficulty) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$subj, $qtxt, $image_url, $mrks, $diff]);
                    $qid = $pdo->lastInsertId();

                    foreach($opts as $idx => $txt) {
                        if (empty(trim($txt))) continue;
                        $isc = ($idx == $corr) ? 1 : 0;
                        $stmt = $pdo->prepare("INSERT INTO cbt_options (question_id, option_text, is_correct) VALUES (?, ?, ?)");
                        $stmt->execute([$qid, $txt, $isc]);
                    }
                    $count++;
                }
                $pdo->commit();
                $message = "Successfully bulk imported $count questions!";
            } catch (Exception $e) {
                $pdo->rollBack();
                $message = "Error: " . $e->getMessage();
            }
            fclose($handle);
        }
    }

    // Handle Question and Option Addition
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_question'])) {
        $subject_id = $_POST['subject_id'];
        $question_text = $_POST['question'];
        $marks = $_POST['marks'] ?? 1.00;
        $difficulty = $_POST['difficulty'] ?? 'Medium';
        $options = $_POST['options'] ?? [];
        $correct_idx = $_POST['correct_option_idx'] ?? 0;

        $image_url = null;
        if (!empty($_FILES['question_image']['name'])) {
            if (!is_dir(ROOT_PATH . '/public/uploads/cbt_images')) {
                mkdir(ROOT_PATH . '/public/uploads/cbt_images', 0777, true);
            }
            $newName = time() . '_' . basename($_FILES['question_image']['name']);
            $dest = ROOT_PATH . '/public/uploads/cbt_images/' . $newName;
            if (move_uploaded_file($_FILES['question_image']['tmp_name'], $dest)) {
                $image_url = 'uploads/cbt_images/' . $newName;
            }
        }

        $pdo->beginTransaction();
        try {
            // Save Question
            $stmt = $pdo->prepare("INSERT INTO cbt_question_bank (subject_id, question, image_url, marks, difficulty) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$subject_id, $question_text, $image_url, $marks, $difficulty]);
            $question_id = $pdo->lastInsertId();

            // Save Options
            foreach($options as $idx => $opt) {
                if(empty(trim($opt))) continue;
                $is_correct = ($idx == $correct_idx) ? 1 : 0;
                $stmt = $pdo->prepare("INSERT INTO cbt_options (question_id, option_text, is_correct) VALUES (?, ?, ?)");
                $stmt->execute([$question_id, $opt, $is_correct]);
            }
            $pdo->commit();
            $message = "Complex question and choices saved to the Institutional Question Bank!";
        } catch (Exception $e) {
            $pdo->rollBack();
            die("CBT Error: " . $e->getMessage());
        }
    }

    // Handle Question Deletion
    if (isset($_GET['delete_id'])) {
        $del_id = $_GET['delete_id'];
        $pdo->prepare("DELETE FROM cbt_question_bank WHERE id = ?")->execute([$del_id]);
        $message = "Question removed from Global Repository.";
    }

    // Fetch Subjects
    $subjects = $pdo->query("SELECT * FROM subjects ORDER BY subject_name")->fetchAll(PDO::FETCH_ASSOC);

    // Fetch Questions
    $questions = $pdo->query("SELECT q.*, s.subject_name 
                              FROM cbt_question_bank q 
                              JOIN subjects s ON q.subject_id = s.id 
                              ORDER BY q.created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Global Repository Error: " . $e->getMessage());
}

$pageTitle = 'Institutional Question Bank - Rosmon SMS';
require ROOT_PATH . '/app/Views/school_admin/layout/header.php';
require ROOT_PATH . '/app/Views/school_admin/layout/sidebar.php';
?>

<div class="main-container">
    <div class="top-header">
        <div class="greeting">Exams / <span style="color:var(--primary)">CBT Question Repository</span></div>
        <div class="header-actions">
            <button onclick="document.getElementById('q-modal').style.display='flex'" class="btn-primary" style="background:var(--text-dark);"><i class="ph ph-plus-circle"></i> Add Single Question</button>
            <button onclick="document.getElementById('bulk-modal').style.display='flex'" class="btn-primary" style="background:#f3f4f6; color:var(--text-dark); border:1px solid #ddd;"><i class="ph ph-file-arrow-up"></i> Bulk Import (Excel/CSV)</button>
            <a href="?download_template=1" class="btn-primary" style="background:#fff; color:var(--primary); border:1px solid var(--primary); text-decoration:none;"><i class="ph ph-download-simple"></i> Download Template</a>
        </div>
    </div>

    <!-- Stats Bar -->
    <div style="display:grid; grid-template-columns: repeat(4, 1fr); gap:20px; margin-bottom:24px;">
        <div class="crud-card" style="padding:20px;">
            <div style="font-size:11px; color:var(--text-muted); font-weight:700;">TOTAL QUESTIONS</div>
            <div style="font-size:20px; font-weight:800;"><?= count($questions) ?></div>
        </div>
        <div class="crud-card" style="padding:20px;">
            <div style="font-size:11px; color:var(--text-muted); font-weight:700;">ACTIVE SUBJECTS</div>
            <div style="font-size:20px; font-weight:800;"><?= count($subjects) ?></div>
        </div>
        <div class="crud-card" style="padding:20px;">
            <div style="font-size:11px; color:var(--text-muted); font-weight:700;">DIFFICULTY (AVG)</div>
            <div style="font-size:20px; font-weight:800; color:#fb923c;">MEDIUM</div>
        </div>
        <div class="crud-card" style="padding:20px;">
            <div style="font-size:11px; color:var(--text-muted); font-weight:700;">RELEVANT TO CBT</div>
            <div style="font-size:20px; font-weight:800; color:#10b981;">SYNCED</div>
        </div>
    </div>

    <div class="crud-card">
        <div class="crud-header">
            <h2 class="crud-title">Master Question List</h2>
            <div style="font-size:11px; color:var(--primary); font-weight:700;"><?= $message ?></div>
        </div>

        <table class="crud-table" style="width:100%;">
            <thead>
                <tr>
                    <th>SUBJECT</th>
                    <th>QUESTION FRAGMENT</th>
                    <th style="width:100px;">MARKS</th>
                    <th style="width:150px;">DIFFICULTY</th>
                    <th>ACTION</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($questions)): ?>
                    <tr><td colspan="5" style="text-align:center; padding:60px; color:var(--text-muted);">Question bank is empty. Begin by adding questions manually or via bulk import.</td></tr>
                <?php else: ?>
                    <?php foreach($questions as $q): ?>
                        <tr>
                            <td style="font-weight:700; font-size:11px; color:var(--primary);"><?= strtoupper($q['subject_name']) ?></td>
                            <td style="font-weight:600; font-size:13px;">
                                <?= htmlspecialchars(substr($q['question'], 0, 80)) . (strlen($q['question']) > 80 ? '...' : '') ?>
                                <?php if(!empty($q['image_url'])): ?> <i class="ph ph-image" style="color:#10b981; margin-left:5px; font-size:14px;" title="Has Image"></i> <?php endif; ?>
                            </td>
                            <td style="font-weight:800;"><?= $q['marks'] ?></td>
                            <td>
                                <span style="font-size:9px; font-weight:800; padding:3px 8px; border-radius:10px; background:<?= $q['difficulty'] == 'Hard' ? '#fee2e2; color:#ef4444' : ($q['difficulty'] == 'Medium' ? '#fef3c7; color:#f59e0b' : '#dcfce7; color:#10b981') ?>;">
                                    <?= strtoupper($q['difficulty']) ?>
                                </span>
                            </td>
                            <td>
                                <div style="display:flex; gap:10px;">
                                    <a href="#" style="color:var(--primary);"><i class="ph ph-eye"></i></a>
                                    <a href="?delete_id=<?= $q['id'] ?>" onclick="return confirm('Permanently remove this question?')" style="color:#ef4444;"><i class="ph ph-trash"></i></a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Create Question Modal -->
    <div id="q-modal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:1001; align-items:center; justify-content:center;">
        <div class="crud-card" style="width:650px; padding:32px; border-radius:20px;">
            <div style="display:flex; justify-content:space-between; margin-bottom:25px;">
                <h2 style="margin:0;"><i class="ph ph-notebook" style="color:var(--primary);"></i> Add to Global Bank</h2>
                <button onclick="document.getElementById('q-modal').style.display='none'" style="background:none; border:none; cursor:pointer;"><i class="ph ph-x" style="font-size:24px;"></i></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="save_question" value="1">
                
                <div style="display:grid; grid-template-columns: 2fr 1fr; gap:20px; margin-bottom:20px;">
                    <div>
                        <label style="display:block; font-size:12px; font-weight:700; margin-bottom:5px;">SUBJECT</label>
                        <select name="subject_id" required style="width:100%; padding:12px; border:1px solid #ddd; border-radius:10px;">
                            <?php foreach($subjects as $s): ?>
                                <option value="<?= $s['id'] ?>"><?= htmlspecialchars((string)($s['subject_name'] ?? '')) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label style="display:block; font-size:12px; font-weight:700; margin-bottom:5px;">DIFFICULTY</label>
                        <select name="difficulty" style="width:100%; padding:12px; border:1px solid #ddd; border-radius:10px;">
                            <option>Easy</option>
                            <option selected>Medium</option>
                            <option>Hard</option>
                        </select>
                    </div>
                </div>

                <label style="display:block; font-size:12px; font-weight:700; margin-bottom:5px;">QUESTION TEXT</label>
                <textarea name="question" required placeholder="Type the question content here..." style="width:100%; height:80px; padding:12px; border:1px solid #ddd; border-radius:10px; margin-bottom:15px; font-family:inherit;"></textarea>

                <div style="margin-bottom:20px; display:flex; align-items:center; gap:15px;">
                    <div style="flex:1;">
                        <label style="display:block; font-size:12px; font-weight:700; margin-bottom:5px;">REFERENCE IMAGE (OPTIONAL)</label>
                        <input type="file" name="question_image" accept="image/*" style="width:100%; padding:8px; border:1px solid #ddd; border-radius:10px; font-size:12px;">
                    </div>
                </div>

                <div style="border-top:1px dashed #eee; padding-top:20px; margin-bottom:20px;">
                    <label style="display:block; font-size:12px; font-weight:700; margin-bottom:10px; color:var(--primary);">OPTIONS &amp; ANSWERS</label>
                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px;">
                        <?php for($i=0; $i<4; $i++): ?>
                            <div style="position:relative;">
                                <input type="text" name="options[]" placeholder="Option <?= chr(65+$i) ?>" required style="width:100%; padding:12px 40px 12px 12px; border:1px solid #ddd; border-radius:10px;">
                                <input type="radio" name="correct_option_idx" value="<?= $i ?>" <?= $i == 0 ? 'checked' : '' ?> style="position:absolute; right:15px; top:15px; cursor:pointer;">
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>

                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <div>
                       <label style="font-size:12px; font-weight:700; margin-right:10px;">MARKS:</label>
                       <input type="number" name="marks" value="1.00" step="0.5" style="width:80px; padding:8px; border:1px solid #ddd; border-radius:8px; font-weight:800;">
                    </div>
                    <button type="submit" class="btn-primary" style="padding:12px 40px; border-radius:12px; font-weight:800; background:linear-gradient(135deg, var(--primary), #4f46e5);"><i class="ph ph-check-circle"></i> Commit to Repository</button>
                </div>
            </form>
        </div>
    </div>
    <!-- Bulk Upload Modal -->
    <div id="bulk-modal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:1001; align-items:center; justify-content:center;">
        <div class="crud-card" style="width:450px; padding:32px; border-radius:20px;">
            <div style="display:flex; justify-content:space-between; margin-bottom:25px;">
                <h2 style="margin:0;"><i class="ph ph-file-arrow-up" style="color:var(--primary);"></i> Bulk Import</h2>
                <button onclick="document.getElementById('bulk-modal').style.display='none'" style="background:none; border:none; cursor:pointer;"><i class="ph ph-x" style="font-size:24px;"></i></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div style="margin-bottom:20px;">
                    <label style="display:block; font-size:12px; font-weight:700; margin-bottom:8px;">TARGET SUBJECT</label>
                    <select name="bulk_subject_id" required style="width:100%; padding:12px; border:1px solid #ddd; border-radius:10px; font-size:14px; outline:none;">
                        <option value="">-- Select Target Subject --</option>
                        <?php foreach($subjects as $s): ?>
                            <option value="<?= $s['id'] ?>"><?= htmlspecialchars((string)($s['subject_name'] ?? '')) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="margin-bottom:25px; padding:30px; border:2px dashed #ddd; border-radius:15px; text-align:center;">
                    <i class="ph ph-cloud-arrow-up" style="font-size:40px; color:#94a3b8; margin-bottom:15px; display:block;"></i>
                    <label style="font-size:11px; font-weight:700; margin-bottom:5px; display:block; color:var(--text-dark);">1. UPLOAD CSV/TXT FILE</label>
                    <input type="file" name="bulk_file" required accept=".csv,.txt" style="font-size:12px; width:100%; margin-bottom:15px;">
                    
                    <label style="font-size:11px; font-weight:700; margin-bottom:5px; display:block; color:var(--text-dark);">2. UPLOAD REFERENCED IMAGES (OPTIONAL)</label>
                    <input type="file" name="bulk_images[]" multiple accept="image/*" style="font-size:12px; width:100%;">
                    
                    <p style="font-size:11px; color:var(--text-muted); margin-top:15px;">* Ensure the image filenames you select here exactly match the filenames in your CSV "Image Filename" column.</p>
                </div>
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <a href="?download_template=1" style="font-size:12px; font-weight:700; color:var(--primary); text-decoration:none;">Download Template</a>
                    <button type="submit" class="btn-primary" style="background:var(--text-dark);">Upload & Process</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require ROOT_PATH . '/app/Views/school_admin/layout/footer.php'; ?>
