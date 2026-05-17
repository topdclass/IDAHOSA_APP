<?php
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

$message = '';
$error = '';

// Handle Approval/Disapproval (Notes or Plans)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $note_id = (int)$_POST['note_id'];
    $status = $_POST['status']; // Approved or Disapproved/Declined
    $remark = $_POST['admin_remark'] ?? '';
    $type = $_POST['type'] ?? 'note';

    try {
        if ($type === 'plan') {
            $stmt = $pdo->prepare("UPDATE lesson_plans SET status = ?, adminRejectionMessage = ? WHERE id = ?");
        } else {
            $stmt = $pdo->prepare("UPDATE lesson_notes SET status = ?, admin_remark = ? WHERE id = ?");
        }
        $stmt->execute([$status, $remark, $note_id]);
        
        $actionText = ($status === 'Approved') ? 'Approved' : 'Disapproved for Correction';
        $message = ucfirst($type) . " #$note_id has been $actionText.";
    } catch (Exception $e) {
        $error = "Update failed: " . $e->getMessage();
    }
}

// Fetch Stats per Subject
$statsQ = $pdo->query("
    SELECT 
        s.subject_name as subject_name,
        COUNT(n.id) as total_notes,
        SUM(CASE WHEN n.status = 'Approved' THEN 1 ELSE 0 END) as approved_count,
        SUM(CASE WHEN n.status = 'Disapproved' THEN 1 ELSE 0 END) as disapproved_count
    FROM subjects s
    LEFT JOIN lesson_notes n ON n.subject_id = s.id
    GROUP BY s.id
");
$subjectStats = $statsQ->fetchAll(PDO::FETCH_ASSOC);

// Fetch Total Summary
$totalNotes = array_sum(array_column($subjectStats, 'total_notes'));
$totalApproved = array_sum(array_column($subjectStats, 'approved_count'));
$totalDisapproved = array_sum(array_column($subjectStats, 'disapproved_count'));

// Fetch Pending Queue (Notes)
$pendingNotes = $pdo->query("
    SELECT n.*, s.subject_name as subject_name, u.full_name as teacher_name 
    FROM lesson_notes n 
    JOIN subjects s ON n.subject_id = s.id 
    JOIN users u ON n.teacher_id = u.id
    WHERE n.status = 'Pending' 
    ORDER BY n.created_at ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Fetch Pending Queue (Plans)
$pendingPlans = $pdo->query("
    SELECT lp.*, s.subject_name as subject_name, u.full_name as teacher_name, c.class_name
    FROM lesson_plans lp
    JOIN subjects s ON lp.subject_id = s.id
    JOIN users u ON lp.user_id = u.id
    JOIN classes c ON lp.class_id = c.id
    WHERE lp.status = 'Pending' AND lp.is_deleted = 0
    ORDER BY lp.created_at ASC
")->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Lesson Note Review - ' . $globalSchoolName;
require ROOT_PATH . '/app/Views/school_admin/layout/header.php';
require ROOT_PATH . '/app/Views/school_admin/layout/sidebar.php';
?>

<div class="main-container">
    <div class="top-header">
        <div class="greeting">Academic / <span style="color:var(--primary)">Lesson Note Management</span></div>
    </div>

    <!-- Summary Statistics Cards -->
    <div style="display:grid; grid-template-columns: repeat(3, 1fr); gap:20px; margin-bottom: 24px;">
        <div class="crud-card" style="border-left: 4px solid var(--primary);">
            <div style="font-size:12px; font-weight:700; color:var(--text-muted); margin-bottom:4px;">TOTAL SUBMISSIONS</div>
            <div style="font-size:28px; font-weight:800; color:var(--text-dark);"><?= $totalNotes ?></div>
        </div>
        <div class="crud-card" style="border-left: 4px solid #10b981;">
            <div style="font-size:12px; font-weight:700; color:var(--text-muted); margin-bottom:4px;">APPROVED NOTES</div>
            <div style="font-size:28px; font-weight:800; color:#065f46;"><?= $totalApproved ?></div>
        </div>
        <div class="crud-card" style="border-left: 4px solid #ef4444;">
            <div style="font-size:12px; font-weight:700; color:var(--text-muted); margin-bottom:4px;">DISAPPROVED (SENT BACK)</div>
            <div style="font-size:28px; font-weight:800; color:#991b1b;"><?= $totalDisapproved ?></div>
        </div>
    </div>

    <?php if ($message): ?>
        <div style="background:#d1fae5; color:#065f46; padding:15px; border-radius:8px; margin-bottom:20px; border-left:4px solid #10b981;">
            <i class="ph ph-check-circle" style="vertical-align:middle; margin-right:8px;"></i> <?= $message ?>
        </div>
    <?php endif; ?>

    <div style="display:grid; grid-template-columns: 1fr 2fr; gap:24px;">
        
        <!-- Subject Statistics -->
        <div>
            <div class="crud-card">
                <div class="crud-header"><h2 class="crud-title">Subject-Wise Breakdown</h2></div>
                <div style="padding:15px;">
                    <?php foreach ($subjectStats as $s): ?>
                        <div style="margin-bottom:15px; padding-bottom:12px; border-bottom:1px solid #f3f4f6;">
                            <div style="display:flex; justify-content:space-between; margin-bottom:6px;">
                                <span style="font-weight:600; font-size:13px;"><?= $s['subject_name'] ?></span>
                                <span style="font-size:11px; color:var(--text-muted);"><?= $s['total_notes'] ?> Total</span>
                            </div>
                            <div style="display:flex; gap:15px; font-size:11px;">
                                <span style="color:#10b981; font-weight:700;">✅ <?= $s['approved_count'] ?> Approved</span>
                                <span style="color:#ef4444; font-weight:700;">❌ <?= $s['disapproved_count'] ?> Disapproved</span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Pending Review Queue -->
        <div>
            <!-- Tabs -->
            <div style="display:flex; gap:10px; margin-bottom:15px;">
                <button onclick="switchTab('notes')" id="btn_notes" class="tab-btn active">Lesson Notes (<?= count($pendingNotes) ?>)</button>
                <button onclick="switchTab('plans')" id="btn_plans" class="tab-btn">Lesson Plans (<?= count($pendingPlans) ?>)</button>
            </div>

            <div id="tab_notes" class="tab-content">
                <div class="crud-card">
                    <div class="crud-header">
                        <h2 class="crud-title">Lesson Notes Pending Approval</h2>
                    </div>
                    <table class="crud-table">
                        <thead>
                            <tr>
                                <th>TEACHER</th>
                                <th>SUBJECT</th>
                                <th>TOPIC</th>
                                <th>SUBMITTED</th>
                                <th>ACTION</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($pendingNotes)): ?>
                                <tr><td colspan="5" style="text-align:center; padding:40px; color:var(--text-muted);">No notes pending review.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($pendingNotes as $n): ?>
                                <tr>
                                    <td><strong><?= $n['teacher_name'] ?></strong></td>
                                    <td><?= $n['subject_name'] ?> (Week <?= $n['week_number'] ?>)</td>
                                    <td><?= htmlspecialchars($n['topic']) ?></td>
                                    <td style="font-size:11px;"><?= date('M j, Y', strtotime($n['created_at'])) ?></td>
                                    <td>
                                        <button onclick="reviewNote(<?= htmlspecialchars(json_encode($n)) ?>)" class="btn-primary" style="padding:6px 12px; font-size:11px;">Review Note</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div id="tab_plans" class="tab-content" style="display:none;">
                <div class="crud-card">
                    <div class="crud-header">
                        <h2 class="crud-title">Strategic Lesson Plans Pending</h2>
                    </div>
                    <table class="crud-table">
                        <thead>
                            <tr>
                                <th>TEACHER</th>
                                <th>CLASS / SUBJECT</th>
                                <th>TOPIC</th>
                                <th>PLANNED DATE</th>
                                <th>ACTION</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($pendingPlans)): ?>
                                <tr><td colspan="5" style="text-align:center; padding:40px; color:var(--text-muted);">No plans pending review.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($pendingPlans as $p): ?>
                                <tr>
                                    <td><strong><?= $p['teacher_name'] ?></strong></td>
                                    <td><?= $p['class_name'] ?> &bull; <?= $p['subject_name'] ?></td>
                                    <td><?= htmlspecialchars($p['topic']) ?></td>
                                    <td style="font-size:11px;"><?= date('M j, Y', strtotime($p['date'])) ?></td>
                                    <td>
                                        <button onclick="reviewPlan(<?= htmlspecialchars(json_encode($p)) ?>)" class="btn-primary" style="padding:6px 12px; font-size:11px; background:#4f46e5;">Review Plan</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Review Modal -->
<div id="reviewModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:9999; justify-content:center; align-items:center;">
    <div class="crud-card" style="width:70%; max-height:90%; overflow-y:auto; padding:40px; position:relative;">
        <button onclick="closeModal()" style="position:absolute; right:20px; top:20px; border:none; background:none; font-size:24px; cursor:pointer;">&times;</button>
        
        <div id="modalHeader" style="margin-bottom:24px; border-bottom:2px solid #f3f4f6; padding-bottom:15px;"></div>
        
        <div style="display:grid; grid-template-columns: 2fr 1fr; gap:30px;">
            <div id="modalBody" style="background:#f9fafb; padding:20px; border-radius:8px; line-height:1.6; font-size:14px; color:#374151;"></div>
            
            <div>
                <form method="POST" id="reviewForm">
                    <input type="hidden" name="action" id="m_action" value="review_note">
                    <input type="hidden" name="type" id="m_type" value="note">
                    <input type="hidden" name="note_id" id="m_note_id">
                    
                    <label class="form-label">Admin Assessment Note (Visible to Teacher)</label>
                    <textarea name="admin_remark" id="m_remark" rows="5" class="form-control" style="width:100%; border:1px solid var(--border); border-radius:8px; padding:12px; font-size:13px; margin-bottom:20px;" placeholder="Optional: Provide feedback for correction..."></textarea>
                    
                    <button type="submit" name="status" value="Approved" class="btn-primary" style="width:100%; padding:14px; margin-bottom:12px; background:#10b981; border:none;">Approve & Publish</button>
                    <button type="submit" name="status" value="Disapproved" class="btn-primary" style="width:100%; padding:14px; background:#ef4444; border:none;">Disapprove (Send Back)</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function switchTab(tabId) {
    document.querySelectorAll('.tab-content').forEach(t => t.style.display = 'none');
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('tab_' + tabId).style.display = 'block';
    document.getElementById('btn_' + tabId).classList.add('active');
}

function reviewNote(note) {
    document.getElementById('m_note_id').value = note.id;
    document.getElementById('reviewForm').elements['action'].value = 'review_note';
    document.getElementById('reviewForm').elements['type'].value = 'note';
    document.getElementById('modalHeader').innerHTML = `
        <span style="font-size:11px; text-transform:uppercase; font-weight:800; color:var(--primary);">LESSON NOTE | ${note.subject_name} | WEEK ${note.week_number}</span>
        <h2 style="margin-top:5px; font-size:22px;">${note.topic}</h2>
        <p style="font-size:12px; color:var(--text-muted);">Author: ${note.teacher_name}</p>
    `;
    document.getElementById('modalBody').innerHTML = note.content.replace(/\n/g, '<br>');
    document.getElementById('m_remark').value = note.admin_remark || '';
    document.getElementById('reviewModal').style.display = 'flex';
}

function reviewPlan(plan) {
    document.getElementById('m_note_id').value = plan.id;
    document.getElementById('reviewForm').elements['action'].value = 'review_plan';
    document.getElementById('reviewForm').elements['type'].value = 'plan';
    document.getElementById('modalHeader').innerHTML = `
        <span style="font-size:11px; text-transform:uppercase; font-weight:800; color:#4f46e5;">STRATEGIC LESSON PLAN | ${plan.class_name} | ${plan.subject_name}</span>
        <h2 style="margin-top:5px; font-size:22px;">${plan.topic}</h2>
        <p style="font-size:12px; color:var(--text-muted);">Author: ${plan.teacher_name} | Planned Date: ${plan.date}</p>
    `;
    const content = `
        <strong>Learning Objectives:</strong><br>${plan.learningObjectives.replace(/\n/g, '<br>')}<br><br>
        <strong>Materials:</strong><br>${plan.materials.replace(/\n/g, '<br>')}<br><br>
        <strong>Core Activities:</strong><br>${plan.structureActivity.replace(/\n/g, '<br>')}<br><br>
        <strong>Assessment Strategy:</strong><br>${plan.assessment?.replace(/\n/g, '<br>') || 'N/A'}
    `;
    document.getElementById('modalBody').innerHTML = content;
    document.getElementById('m_remark').value = plan.adminRejectionMessage || '';
    document.getElementById('reviewModal').style.display = 'flex';
}

function closeModal() {
    document.getElementById('reviewModal').style.display = 'none';
}

window.onclick = function(event) {
    if (event.target == document.getElementById('reviewModal')) closeModal();
}
</script>

<style>
.tab-btn { background:none; border:none; padding:10px 20px; font-size:13px; font-weight:700; color:var(--text-muted); cursor:pointer; border-radius:6px; transition:0.2s; }
.tab-btn.active { color:var(--primary); background:rgba(79, 70, 229, 0.1); }
.tab-btn:hover:not(.active) { background:#f3f4f6; }
.form-label { display:block; font-size:13px; font-weight:700; margin-bottom:8px; color:var(--text-dark); }
</style>

<?php require ROOT_PATH . '/app/Views/school_admin/layout/footer.php'; ?>
