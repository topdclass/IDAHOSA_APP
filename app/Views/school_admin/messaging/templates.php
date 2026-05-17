<?php
require_once ROOT_PATH . '/config/database.php';
$schoolId = $_SESSION['school_id'] ?? 101;

$message = '';
$error = '';

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create' || $action === 'edit') {
        $name = trim($_POST['template_name']);
        $channel = $_POST['channel']; // 'sms' or 'email'
        $subject = trim($_POST['subject'] ?? '');
        $body = trim($_POST['body']);
        
        if ($channel === 'sms') $subject = null; // SMS doesn't use subject

        if (empty($name) || empty($channel) || empty($body)) {
            $error = "Name, channel, and body are required.";
        } else {
            if ($action === 'create') {
                $stmt = $pdo->prepare("INSERT INTO notification_templates (template_name, channel, subject, body) VALUES (?, ?, ?, ?)");
                if ($stmt->execute([$name, $channel, $subject, $body])) {
                    $message = "Template created successfully.";
                } else {
                    $error = "Failed to create template.";
                }
            } else {
                $id = (int) $_POST['template_id'];
                $stmt = $pdo->prepare("UPDATE notification_templates SET template_name=?, channel=?, subject=?, body=? WHERE id=?");
                if ($stmt->execute([$name, $channel, $subject, $body, $id])) {
                    $message = "Template updated successfully.";
                } else {
                    $error = "Failed to update template.";
                }
            }
        }
    } elseif ($action === 'delete') {
        $id = (int) $_POST['template_id'];
        if ($pdo->prepare("DELETE FROM notification_templates WHERE id=?")->execute([$id])) {
            $message = "Template deleted successfully.";
        } else {
            $error = "Failed to delete template.";
        }
    }
}

// Fetch Templates
$templates = $pdo->query("SELECT * FROM notification_templates ORDER BY template_name")->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Notification Templates';
require ROOT_PATH . '/app/Views/school_admin/layout/header.php';
require ROOT_PATH . '/app/Views/school_admin/layout/sidebar.php';
?>
<style>
    .panel-card { background: white; padding: 25px; border-radius: 12px; border: 1px solid var(--border); margin-bottom: 25px; }
    .form-group { margin-bottom: 15px; }
    .form-group label { display: block; font-size: 13px; font-weight: 600; color: #64748b; margin-bottom: 5px; }
    .form-control { width: 100%; padding: 10px; border: 1px solid var(--border); border-radius: 8px; font-size: 14px; outline: none; }
    .form-control:focus { border-color: var(--primary); }
    .btn-submit { background: #3b82f6; color: white; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; transition: 0.2s; }
    .btn-submit:hover { background: #2563eb; }
    
    .data-table { width: 100%; border-collapse: collapse; margin-top: 15px; }
    .data-table th, .data-table td { padding: 12px; text-align: left; border-bottom: 1px solid var(--border); }
    .data-table th { background: #f8fafc; font-size: 12px; color: #64748b; text-transform: uppercase; }
    .data-table td { font-size: 14px; }
    .data-table tbody tr:hover { background: #f8fafc; }
    
    .badge { padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 600; }
    .badge-email { background: #e0e7ff; color: #4338ca; }
    .badge-sms { background: #fef3c7; color: #b45309; }
    
    .btn-edit { background: #f59e0b; color: white; padding: 6px 10px; border-radius: 6px; text-decoration: none; font-size: 12px; border: none; cursor: pointer;}
    .btn-delete { background: #ef4444; color: white; padding: 6px 10px; border-radius: 6px; text-decoration: none; font-size: 12px; border: none; cursor: pointer;}
    
    /* Placeholders container */
    .placeholders-box { background: #f1f5f9; padding: 15px; border-radius: 8px; margin-bottom: 15px; font-size: 12px; border: 1px dashed #cbd5e1; }
    .placeholders-box code { background: white; padding: 2px 6px; border-radius: 4px; border: 1px solid #e2e8f0; color: #db2777; font-weight: 600; margin-right: 5px; display: inline-block; margin-bottom: 5px;}
    
    /* Modal */
    .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); align-items: center; justify-content: center; z-index: 1000; }
    .modal.active { display: flex; }
    .modal-content { background: white; padding: 25px; border-radius: 12px; width: 100%; max-width: 600px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); max-height: 90vh; overflow-y: auto;}
    .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
    .close-modal { cursor: pointer; font-size: 20px; color: #64748b; }
    .close-modal:hover { color: #0f172a; }
</style>

<div class="main-container">
    <div class="header" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
        <h1 style="font-size: 24px; font-weight:800;">Notification Templates</h1>
        <button class="btn-submit" onclick="openModal('create')"><i class="ph ph-plus"></i> New Template</button>
    </div>

    <?php if ($message): ?>
        <div style="background: #dcfce3; color: #166534; padding: 10px 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 600;"><i class="ph ph-check-circle"></i> <?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div style="background: #fee2e2; color: #991b1b; padding: 10px 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 600;"><i class="ph ph-warning-circle"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="panel-card">
        <h3>Existing Templates</h3>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Channel</th>
                    <th>Subject</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($templates)): ?>
                    <tr><td colspan="4" style="text-align: center; color: #64748b; padding: 20px;">No templates found.</td></tr>
                <?php else: ?>
                    <?php foreach ($templates as $t): ?>
                        <tr>
                            <td style="font-weight:600;"><?= htmlspecialchars($t['template_name']) ?></td>
                            <td><span class="badge <?= $t['channel'] == 'email' ? 'badge-email' : 'badge-sms' ?>"><?= strtoupper($t['channel']) ?></span></td>
                            <td><?= $t['channel'] == 'email' ? htmlspecialchars($t['subject'] ?: '(No Subject)') : '<i style="color:#94a3b8;">N/A</i>' ?></td>
                            <td style="display:flex; gap:8px;">
                                <button type="button" class="btn-edit" 
                                    onclick='openModal("edit", <?= json_encode($t) ?>)'><i class="ph ph-pencil"></i></button>
                                <form method="POST" onsubmit="return confirm('Are you sure you want to delete this template?');" style="display:inline;">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="template_id" value="<?= $t['id'] ?>">
                                    <button type="submit" class="btn-delete"><i class="ph ph-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal -->
<div class="modal" id="templateModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="modalTitle">Create Template</h2>
            <i class="ph ph-x close-modal" onclick="closeModal()"></i>
        </div>
        
        <div class="placeholders-box">
            <strong>Supported Placeholders:</strong><br>
            <code>{student_name}</code> <code>{parent_name}</code> <code>{date}</code> <code>{time}</code> <code>{status}</code> <code>{school_name}</code>
            <p style="margin-top:5px; color:#64748b;">Placeholders are automatically replaced when the notification is sent.</p>
        </div>

        <form method="POST" id="templateForm">
            <input type="hidden" name="action" id="formAction" value="create">
            <input type="hidden" name="template_id" id="formId" value="">
            
            <div class="form-group">
                <label>Template Reference Name</label>
                <input type="text" name="template_name" id="formName" class="form-control" required placeholder="e.g. attendance_alert">
                <small style="color:#64748b;">Use standard names like 'attendance_alert' for system triggers.</small>
            </div>
            
            <div class="form-group">
                <label>Delivery Channel</label>
                <select name="channel" id="formChannel" class="form-control" onchange="toggleSubject()" required>
                    <option value="email">Email</option>
                    <option value="sms">SMS</option>
                </select>
            </div>
            
            <div class="form-group" id="subjectGroup">
                <label>Email Subject</label>
                <input type="text" name="subject" id="formSubject" class="form-control" placeholder="e.g. Attendance Alert from {school_name}">
            </div>
            
            <div class="form-group">
                <label>Message Body</label>
                <textarea name="body" id="formBody" class="form-control" rows="6" required placeholder="Dear {parent_name}, your child {student_name} has arrived at {time}."></textarea>
            </div>
            
            <button type="submit" class="btn-submit" style="width:100%;">Save Template</button>
        </form>
    </div>
</div>

<script>
    function openModal(mode, data = null) {
        document.getElementById('templateModal').classList.add('active');
        
        if (mode === 'edit' && data) {
            document.getElementById('modalTitle').textContent = 'Edit Template';
            document.getElementById('formAction').value = 'edit';
            document.getElementById('formId').value = data.id;
            document.getElementById('formName').value = data.template_name;
            document.getElementById('formChannel').value = data.channel;
            document.getElementById('formSubject').value = data.subject || '';
            document.getElementById('formBody').value = data.body;
        } else {
            document.getElementById('modalTitle').textContent = 'Create Template';
            document.getElementById('formAction').value = 'create';
            document.getElementById('formId').value = '';
            document.getElementById('templateForm').reset();
            document.getElementById('formChannel').value = 'email';
        }
        toggleSubject();
    }
    
    function closeModal() {
        document.getElementById('templateModal').classList.remove('active');
    }
    
    function toggleSubject() {
        const channel = document.getElementById('formChannel').value;
        const subjGroup = document.getElementById('subjectGroup');
        const subjInput = document.getElementById('formSubject');
        
        if (channel === 'sms') {
            subjGroup.style.display = 'none';
            subjInput.removeAttribute('required');
        } else {
            subjGroup.style.display = 'block';
            subjInput.setAttribute('required', 'required');
        }
    }
</script>

<?php require ROOT_PATH . '/app/Views/school_admin/layout/footer.php'; ?>
