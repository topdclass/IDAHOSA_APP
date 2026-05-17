<?php
require_once ROOT_PATH . '/config/database.php';

$teacher_id = $_SESSION['user_id'] ?? 0;
$teacher_name = $_SESSION['username'] ?? 'Faculty';
$message = '';

// 1. Handle creating/updating a lesson plan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_plan'])) {
    $plan_id = $_POST['plan_id'] ?? null;
    $class_id = $_POST['class_id'];
    $subject_id = $_POST['subject_id'];
    $topic = $_POST['topic'];
    $date = $_POST['date'] ?? date('Y-m-d H:i:s');
    $learningObjectives = $_POST['learningObjectives'] ?? '';
    $materials = $_POST['materials'] ?? '';
    $structureActivity = $_POST['structureActivity'] ?? '';
    $assessment = $_POST['assessment'] ?? '';
    $status = 'Pending';
    
    if (!empty($plan_id)) {
        $chk = $pdo->prepare("SELECT status FROM lesson_plans WHERE id = ? AND user_id = ?");
        $chk->execute([$plan_id, $teacher_id]);
        if ($chk->fetchColumn() === 'Approved') {
            $message = "<div style='color:#ef4444;'>Error: Approved plans are locked.</div>";
        } else {
            $stmt = $pdo->prepare("UPDATE lesson_plans SET class_id=?, subject_id=?, topic=?, date=?, learningObjectives=?, materials=?, structureActivity=?, assessment=?, status=? WHERE id=? AND user_id=?");
            $stmt->execute([$class_id, $subject_id, $topic, $date, $learningObjectives, $materials, $structureActivity, $assessment, $status, $plan_id, $teacher_id]);
            $message = "Strategic update dispatched for approval!";
        }
    } else {
        $stmt = $pdo->prepare("INSERT INTO lesson_plans (user_id, class_id, subject_id, topic, date, learningObjectives, materials, structureActivity, assessment, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$teacher_id, $class_id, $subject_id, $topic, $date, $learningObjectives, $materials, $structureActivity, $assessment, $status]);
        $message = "New strategic outline created successfully!";
    }
}

// 2. Data Retrieval
$assignedStmt = $pdo->prepare("SELECT cs.class_id, cs.subject_id, c.class_name, c.arm as section, s.subject_name FROM class_subjects cs JOIN classes c ON cs.class_id = c.id JOIN subjects s ON cs.subject_id = s.id WHERE cs.teacher_id = ? AND cs.is_deleted = 0");
$assignedStmt->execute([$teacher_id]);
$assignments = $assignedStmt->fetchAll(PDO::FETCH_ASSOC);

$myPlansStmt = $pdo->prepare("SELECT lp.*, c.class_name, s.subject_name FROM lesson_plans lp LEFT JOIN classes c ON lp.class_id = c.id LEFT JOIN subjects s ON lp.subject_id = s.id WHERE lp.user_id = ? AND lp.is_deleted = 0 ORDER BY lp.created_at DESC");
$myPlansStmt->execute([$teacher_id]);
$myPlans = $myPlansStmt->fetchAll(PDO::FETCH_ASSOC);

$repoPlansStmt = $pdo->prepare("SELECT lp.*, c.class_name, s.subject_name, u.full_name as author_name FROM lesson_plans lp LEFT JOIN classes c ON lp.class_id = c.id LEFT JOIN subjects s ON lp.subject_id = s.id LEFT JOIN users u ON lp.user_id = u.id WHERE lp.status = 'Approved' AND lp.is_deleted = 0 AND lp.user_id != ? ORDER BY lp.created_at DESC LIMIT 50");
$repoPlansStmt->execute([$teacher_id]);
$repoPlans = $repoPlansStmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Syllabus Intelligence - Lesson Planning';
require ROOT_PATH . '/app/Views/employee/layout/header.php';
?>

<style>
    .plan-header { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 30px; }
    .tab-bar { display: flex; gap: 8px; margin-bottom: 25px; background: #fff; padding: 6px; border-radius: 12px; border: 1px solid #f1f5f9; width: fit-content; }
    .tab-btn { border: none; background: transparent; padding: 10px 20px; font-size: 13px; font-weight: 700; color: #64748b; cursor: pointer; border-radius: 8px; transition: 0.2s; }
    .tab-btn.active { background: var(--primary); color: white; box-shadow: 0 4px 12px rgba(79, 70, 229, 0.2); }

    .plan-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 20px; }
    .plan-card { background: white; border-radius: 20px; border: 1px solid #f1f5f9; padding: 25px; transition: 0.2s; display: flex; flex-direction: column; box-shadow: 0 4px 15px rgba(0,0,0,0.02); }
    .plan-card:hover { transform: translateY(-3px); box-shadow: 0 10px 25px -5px rgba(0,0,0,0.05); }

    .status-badge { font-size: 10px; font-weight: 900; padding: 5px 12px; border-radius: 20px; text-transform: uppercase; }
    .status-Pending { background: #fef3c7; color: #92400e; }
    .status-Approved { background: #dcfce7; color: #15803d; }
    .status-Disapproved { background: #fee2e2; color: #991b1b; }

    .btn-create { background: var(--primary); color: white; padding: 12px 25px; border-radius: 12px; font-weight: 800; border: none; cursor: pointer; box-shadow: 0 10px 15px -3px rgba(79, 70, 229, 0.4); }

    /* Modal Styling */
    #planModal { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center; padding: 20px; backdrop-filter: blur(4px); }
    .modal-paper { background:white; border-radius:24px; width:100%; max-width:900px; max-height:90vh; overflow-y:auto; padding:40px; position:relative; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); }
    
    input, select, textarea { width: 100%; padding: 12px; border: 1px solid #eef2f6; border-radius: 10px; font-size: 14px; background: #f8fafc; outline: none; margin-bottom: 15px; }
    input:focus, select:focus, textarea:focus { border-color: var(--primary); background: #fff; }
    label { font-size: 11px; font-weight: 800; color: #94a3b8; display: block; margin-bottom: 8px; text-transform: uppercase; }
</style>

<div class="plan-header">
    <div>
        <h1 style="font-size:24px; font-weight:900; color:#111827; margin:0 0 5px 0;">Syllabus Repository</h1>
        <p style="color:#64748b; font-size:14px; margin:0;">Strategize your academic delivery and access globally approved outlines.</p>
    </div>
    <button onclick="openPlanModal()" class="btn-create"><i class="ph ph-plus"></i> Compose Strategic Plan</button>
</div>

<?php if ($message): ?>
    <div style="background:#dcfce7; color:#15803d; padding:15px 25px; border-radius:12px; margin-bottom:25px; font-weight:700; display:flex; align-items:center; gap:10px;">
        <i class="ph-fill ph-shield-check" style="font-size:20px;"></i> <?= $message ?>
    </div>
<?php endif; ?>

<div class="tab-bar">
    <button onclick="switchTab('my_plans')" class="tab-btn active" id="btn_my_plans">My Active Plans</button>
    <button onclick="switchTab('repo_plans')" class="tab-btn" id="btn_repo_plans">Global Curriculum</button>
</div>

<div id="tab_my_plans" class="tab-content">
    <div class="plan-grid">
        <?php foreach ($myPlans as $p): ?>
            <div class="plan-card">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
                    <span class="status-badge status-<?= $p['status'] ?>"><?= $p['status'] ?></span>
                    <span style="font-size:11px; color:#94a3b8; font-weight:800;"><?= date('M d', strtotime($p['created_at'])) ?></span>
                </div>
                <div style="font-size:12px; font-weight:800; color:var(--primary); margin-bottom:8px;"><?= htmlspecialchars($p['subject_name']) ?></div>
                <h3 style="margin:0 0 15px 0; font-size:16px; font-weight:900;"><?= htmlspecialchars($p['topic']) ?></h3>
                
                <div style="display:flex; gap:10px; margin-top:auto; padding-top:20px; border-top:1px solid #f8fafc;">
                    <button onclick="viewPlan(<?= htmlspecialchars(json_encode($p)) ?>)" style="flex:1; border:none; background:#f1f5f9; color:#475569; padding:10px; border-radius:10px; font-weight:700; cursor:pointer;"><i class="ph ph-eye"></i> Details</button>
                    <?php if($p['status'] !== 'Approved'): ?>
                        <button onclick="editPlan(<?= htmlspecialchars(json_encode($p)) ?>)" style="flex:1; border:none; background:var(--primary-light); color:var(--primary); padding:10px; border-radius:10px; font-weight:700; cursor:pointer;"><i class="ph ph-pencil"></i> Adjust</button>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<div id="tab_repo_plans" class="tab-content" style="display:none;">
    <div class="plan-grid">
        <?php foreach ($repoPlans as $rp): ?>
            <div class="plan-card">
                <div style="font-size:12px; font-weight:800; color:#10b981; margin-bottom:8px; display:flex; justify-content:space-between;">
                    <span><?= htmlspecialchars($rp['subject_name']) ?></span>
                    <i class="ph-fill ph-seal-check"></i>
                </div>
                <h3 style="margin:0 0 10px 0; font-size:16px;"><?= htmlspecialchars($rp['topic']) ?></h3>
                <p style="font-size:13px; color:#64748b; line-height:1.6;"><?= mb_strimwidth(strip_tags($rp['learningObjectives']), 0, 120, '...') ?></p>
                <div style="margin-top:auto; padding-top:15px; border-top:1px solid #f8fafc; display:flex; justify-content:space-between; align-items:center;">
                    <span style="font-size:11px; color:#94a3b8; font-weight:700;">By <?= htmlspecialchars($rp['author_name']) ?></span>
                    <button onclick="clonePlan(<?= htmlspecialchars(json_encode($rp)) ?>)" style="background:var(--primary-light); border:none; color:var(--primary); padding:6px 12px; border-radius:8px; font-size:11px; font-weight:800; cursor:pointer;">Adopt Plan</button>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Modal Form -->
<div id="planModal" onclick="if(event.target == this) closePlanModal()">
    <div class="modal-paper">
        <h2 id="modalTitle" style="margin:0 0 30px 0; font-weight:900;">Compose Strategic Plan</h2>
        <form method="POST">
            <input type="hidden" name="plan_id" id="f_plan_id">
            <div style="display:grid; grid-template-columns: 1.5fr 1fr; gap:20px;">
                <div>
                    <label>Assignment Class & Subject</label>
                    <select name="class_subject_combined" onchange="splitCombined(this.value)" required>
                        <option value="">Select Target Assignment</option>
                        <?php foreach($assignments as $a): ?>
                            <option value="<?= $a['class_id'] ?>-<?= $a['subject_id'] ?>"><?= $a['class_name'] ?> - <?= $a['subject_name'] ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="hidden" name="class_id" id="f_class_id">
                    <input type="hidden" name="subject_id" id="f_subject_id">

                    <label>Strategic Topic</label>
                    <input type="text" name="topic" id="f_topic" required placeholder="e.g. Advanced Quantum Mechanics foundations">

                    <label>Main Learning Objectives</label>
                    <textarea name="learningObjectives" id="f_objectives" rows="5" placeholder="What should students master?"></textarea>

                    <label>Structural Activities & Methodology</label>
                    <textarea name="structureActivity" id="f_structure" rows="5" placeholder="How will the lesson flow?"></textarea>
                </div>
                <div style="background:#f8fafc; padding:25px; border-radius:20px; border:1px solid #f1f5f9;">
                    <label>Lesson Date / Session</label>
                    <input type="datetime-local" name="date" id="f_date" value="<?= date('Y-m-d\TH:i') ?>">

                    <label>Required Materials</label>
                    <textarea name="materials" id="f_materials" rows="4" placeholder="Books, Tools, Digital Resources..."></textarea>

                    <label>Assessment Strategy</label>
                    <textarea name="assessment" id="f_assessment" rows="4" placeholder="How will you measure mastery?"></textarea>
                    
                    <button type="submit" name="save_plan" class="btn-create" style="width:100%; margin-top:20px; padding:18px;">Publish Strategic Plan</button>
                    <p style="text-align:center; font-size:11px; color:#94a3b8; margin-top:15px;">Pending admin review upon submission.</p>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
function splitCombined(val) {
    if(!val) return;
    const [cid, sid] = val.split('-');
    document.getElementById('f_class_id').value = cid;
    document.getElementById('f_subject_id').value = sid;
}

function switchTab(tabId) {
    document.querySelectorAll('.tab-content').forEach(t => t.style.display = 'none');
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('tab_' + tabId).style.display = 'block';
    document.getElementById('btn_' + tabId).classList.add('active');
}

function openPlanModal() { resetModal(); document.getElementById('planModal').style.display = 'flex'; }
function closePlanModal() { document.getElementById('planModal').style.display = 'none'; }
function resetModal() {
    document.getElementById('modalTitle').innerText = "Compose Strategic Plan";
    document.getElementById('f_plan_id').value = '';
    document.getElementById('f_topic').value = '';
    document.getElementById('f_objectives').value = '';
    document.getElementById('f_materials').value = '';
    document.getElementById('f_structure').value = '';
    document.getElementById('f_assessment').value = '';
}

function editPlan(plan) {
    openPlanModal();
    document.getElementById('modalTitle').innerText = "Adjust Strategic Plan";
    document.getElementById('f_plan_id').value = plan.id;
    document.getElementById('f_topic').value = plan.topic;
    document.getElementById('f_objectives').value = plan.learningObjectives;
    document.getElementById('f_materials').value = plan.materials;
    document.getElementById('f_structure').value = plan.structureActivity;
    document.getElementById('f_assessment').value = plan.assessment;
}

function clonePlan(plan) {
    openPlanModal();
    document.getElementById('f_topic').value = plan.topic + " (Clone)";
    document.getElementById('f_objectives').value = plan.learningObjectives;
    document.getElementById('f_materials').value = plan.materials;
    document.getElementById('f_structure').value = plan.structureActivity;
    document.getElementById('f_assessment').value = plan.assessment;
}

function viewPlan(plan) {
    // Simple popup view for now
    alert("TITLE: " + plan.topic + "\n\nOBJECTIVES:\n" + plan.learningObjectives);
}
</script>

<?php require ROOT_PATH . '/app/Views/employee/layout/footer.php'; ?>
