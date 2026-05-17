<?php
require_once ROOT_PATH . '/config/database.php';

// Auto-initialize Schema if not exists (Multi-tenant safety)
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS theory_questions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        lesson_note_id INT NOT NULL,
        subject_id INT NOT NULL,
        question_text TEXT NOT NULL,
        section_label VARCHAR(50) DEFAULT 'A',
        difficulty ENUM('Easy', 'Medium', 'Hard') DEFAULT 'Medium',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
} catch (Exception $e) {}

$subjects = $pdo->query("SELECT id, subject_name FROM subjects ORDER BY subject_name ASC")->fetchAll(PDO::FETCH_ASSOC);

$generatedExam = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'generate') {
    $subject_id = (int)$_POST['subject_id'];
    $exam_title = $_POST['exam_title'] ?? 'Term Examination';
    $sections = $_POST['section_labels'] ?? []; // ['A', 'B']
    $counts = $_POST['section_counts'] ?? [];   // [5, 2]
    $instructions = $_POST['section_instructions'] ?? [];

    $allQuestions = [];
    $error = '';

    try {
        // Fetch ALL approved questions for this subject
        $stmt = $pdo->prepare("SELECT question_text FROM theory_questions WHERE subject_id = ? ORDER BY RAND()");
        $stmt->execute([$subject_id]);
        $pool = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (count($pool) < array_sum($counts)) {
            $error = "Not enough approved questions in the bank. Need " . array_sum($counts) . ", but only " . count($pool) . " available.";
        } else {
            $offset = 0;
            foreach ($sections as $i => $label) {
                $count = (int)$counts[$i];
                $allQuestions[] = [
                    'label' => $label,
                    'instruction' => $instructions[$i] ?? '',
                    'questions' => array_slice($pool, $offset, $count)
                ];
                $offset += $count;
            }
            $generatedExam = [
                'title' => $exam_title,
                'subject' => $pdo->query("SELECT subject_name FROM subjects WHERE id = $subject_id")->fetchColumn(),
                'sections' => $allQuestions
            ];
        }
    } catch (Exception $e) {
        $error = "Generation failed: " . $e->getMessage();
    }
}

$pageTitle = 'Theory Generator - ' . $globalSchoolName;
require ROOT_PATH . '/app/Views/school_admin/layout/header.php';
require ROOT_PATH . '/app/Views/school_admin/layout/sidebar.php';
?>

<div class="main-container no-print">
    <div class="top-header">
        <div class="greeting">Academic / <span style="color:var(--primary)">Theory Exam Generator</span></div>
    </div>

    <?php if (isset($error) && $error): ?>
        <div style="background:#fee2e2; color:#991b1b; padding:15px; border-radius:8px; margin-bottom:20px; border-left:4px solid #ef4444;">
            <i class="ph ph-warning-circle" style="vertical-align:middle; margin-right:8px;"></i> <?= $error ?>
        </div>
    <?php endif; ?>

    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:30px;">
        <!-- Config Section -->
        <div class="crud-card">
            <div class="crud-header"><h2 class="crud-title">Exam Configuration</h2></div>
            <form method="POST" style="padding:20px;">
                <input type="hidden" name="action" value="generate">
                
                <div style="margin-bottom:20px;">
                    <label class="form-label">Exam Title</label>
                    <input type="text" name="exam_title" required class="form-control" style="width:100%; border:1px solid var(--border); border-radius:6px; padding:10px;" placeholder="e.g. 2026 First Term Examination">
                </div>

                <div style="margin-bottom:20px;">
                    <label class="form-label">Subject</label>
                    <select name="subject_id" required class="form-control" style="width:100%; border:1px solid var(--border); border-radius:6px; padding:10px;">
                        <option value="">Select Subject</option>
                        <?php foreach ($subjects as $s): ?>
                            <option value="<?= $s['id'] ?>"><?= $s['subject_name'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <hr style="border:0; border-top:1px solid #f3f4f6; margin:20px 0;">
                <h4 style="margin-bottom:15px; font-size:14px; font-weight:800; color:var(--primary);">SECTIONING</h4>
                
                <div id="sections_container">
                    <div style="background:#f9fafb; padding:15px; border-radius:8px; margin-bottom:15px; position:relative;">
                        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px; margin-bottom:10px;">
                            <div>
                                <label style="font-size:11px; font-weight:700;">LABEL (e.g. SECTION A)</label>
                                <input type="text" name="section_labels[]" required value="SECTION A" class="form-control" style="width:100%; padding:8px; border-radius:4px; border:1px solid var(--border);">
                            </div>
                            <div>
                                <label style="font-size:11px; font-weight:700;">TOTAL QUESTIONS</label>
                                <input type="number" name="section_counts[]" required value="5" class="form-control" style="width:100%; padding:8px; border-radius:4px; border:1px solid var(--border);">
                            </div>
                        </div>
                        <label style="font-size:11px; font-weight:700;">INSTRUCTIONS</label>
                        <input type="text" name="section_instructions[]" required value="Answer all questions in this section." class="form-control" style="width:100%; padding:8px; border-radius:4px; border:1px solid var(--border);">
                    </div>
                </div>

                <button type="button" onclick="addSection()" style="width:100%; padding:10px; background:white; border:1px dashed var(--primary); color:var(--primary); font-weight:700; border-radius:8px; cursor:pointer; margin-bottom:20px;">+ Add Another Section</button>

                <button type="submit" class="btn-primary" style="width:100%; padding:15px; font-size:16px;">Generate Theory Paper</button>
            </form>
        </div>

        <!-- Preview Section -->
        <div>
            <div class="crud-card" style="min-height:400px; display:flex; flex-direction:column; justify-content:center; align-items:center;">
                <?php if ($generatedExam): ?>
                    <i class="ph ph-check-circle" style="font-size:64px; color:#10b981; margin-bottom:15px;"></i>
                    <h2 style="font-size:24px; font-weight:800;">Exam Generated!</h2>
                    <p style="color:var(--text-muted); margin-bottom:30px;">Your branded theory paper is ready for printing.</p>
                    <button onclick="window.print()" class="btn-primary" style="padding:15px 40px; background:#1e293b; border:none; border-radius:30px; display:flex; align-items:center; gap:10px;">
                        <i class="ph ph-printer"></i> Print / Save as PDF
                    </button>
                    <div style="margin-top:20px; font-size:12px; color:var(--text-muted);">Scroll down to preview below the fold</div>
                <?php else: ?>
                    <i class="ph ph-file-pdf" style="font-size:80px; opacity:0.1; margin-bottom:20px;"></i>
                    <p style="color:var(--text-muted); font-size:14px; width:60%; text-align:center;">Configure your exam sections and subject on the left to generate the randomized theory paper.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- PRINTABLE AREA (Only shows during print or if generated) -->
<?php if ($generatedExam): ?>
<div class="print-preview" style="background:white; padding:50px; margin-top:40px; border:1px solid #ddd; max-width:900px; margin-left:auto; margin-right:auto; box-shadow:0 10px 15px -3px rgba(0,0,0,0.1);">
    <div style="text-align:center; margin-bottom:40px;">
        <h1 style="font-size:24px; font-weight:900; text-transform:uppercase; margin-bottom:5px; color:black;"><?= htmlspecialchars($globalSchoolName) ?></h1>
        <div style="width:100px; height:2px; background:black; margin:10px auto;"></div>
        <h3 style="font-size:18px; font-weight:700; margin-bottom:5px; color:black;"><?= htmlspecialchars($generatedExam['title']) ?></h3>
        <h2 style="font-size:20px; font-weight:800; text-transform:uppercase; color:black;"><?= htmlspecialchars($generatedExam['subject']) ?> (THEORY)</h2>
    </div>

    <div style="display:flex; justify-content:space-between; font-weight:800; border-bottom:2px solid black; padding-bottom:10px; margin-bottom:30px; color:black;">
        <span>NAME: _________________________________________________</span>
        <span>CLASS: _______________</span>
    </div>

    <?php foreach ($generatedExam['sections'] as $sec): ?>
        <div style="margin-bottom:40px; color:black;">
            <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid black; padding-bottom:5px; margin-bottom:15px;">
                <h2 style="font-size:18px; font-weight:900; margin:0;"><?= htmlspecialchars($sec['label']) ?></h2>
                <span style="font-size:12px; font-style:italic; font-weight:700;"><?= htmlspecialchars($sec['instruction']) ?></span>
            </div>
            
            <ol style="padding-left:25px; line-height:2;">
                <?php foreach ($sec['questions'] as $q): ?>
                    <li style="margin-bottom:15px; font-size:15px; font-weight:600;"><?= htmlspecialchars($q) ?></li>
                <?php endforeach; ?>
            </ol>
        </div>
    <?php endforeach; ?>

    <div style="text-align:center; font-size:12px; font-style:italic; border-top:1px dashed #ccc; padding-top:20px; color:#666; margin-top:100px;">
        Best of Luck! <br> Powered by Rosmon SMS (Global Digital Identity)
    </div>
</div>
<?php endif; ?>

<script>
function addSection() {
    const container = document.getElementById('sections_container');
    const div = document.createElement('div');
    const count = container.children.length + 1;
    const label = String.fromCharCode(65 + (count - 1)); // B, C, D...
    
    div.style.background = '#f9fafb';
    div.style.padding = '15px';
    div.style.borderRadius = '8px';
    div.style.marginBottom = '15px';
    div.style.position = 'relative';
    div.innerHTML = `
        <button type="button" onclick="this.parentElement.remove()" style="position:absolute; right:10px; top:10px; background:none; border:none; color:#ef4444; font-weight:800; cursor:pointer;">&times;</button>
        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px; margin-bottom:10px;">
            <div>
                <label style="font-size:11px; font-weight:700;">LABEL (e.g. SECTION ${label})</label>
                <input type="text" name="section_labels[]" required value="SECTION ${label}" class="form-control" style="width:100%; padding:8px; border-radius:4px; border:1px solid var(--border);">
            </div>
            <div>
                <label style="font-size:11px; font-weight:700;">TOTAL QUESTIONS</label>
                <input type="number" name="section_counts[]" required value="3" class="form-control" style="width:100%; padding:8px; border-radius:4px; border:1px solid var(--border);">
            </div>
        </div>
        <label style="font-size:11px; font-weight:700;">INSTRUCTIONS</label>
        <input type="text" name="section_instructions[]" required value="Answer any 2 questions." class="form-control" style="width:100%; padding:8px; border-radius:4px; border:1px solid var(--border);">
    `;
    container.appendChild(div);
}
</script>

<style>
@media print {
    body * { visibility: hidden; }
    .print-preview, .print-preview * { visibility: visible; }
    .print-preview { position: fixed; left: 0; top: 0; width: 100%; margin:0; padding:40px; box-shadow:none; border:none; }
    .no-print { display:none !important; }
}
.form-label { display:block; font-size:13px; font-weight:700; margin-bottom:8px; }
</style>

<?php require ROOT_PATH . '/app/Views/school_admin/layout/footer.php'; ?>
