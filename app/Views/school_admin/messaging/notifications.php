<?php
// SMS & Email Notification Module - Bulk Communication

try {
    
    // Auto Migration: Users Table (shared by all communication modules)
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        full_name VARCHAR(150) NOT NULL,
        email VARCHAR(255) DEFAULT NULL,
        role VARCHAR(50) NOT NULL DEFAULT 'Staff',
        phone VARCHAR(20) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Seed sample users if empty
    if ($pdo->query("SELECT COUNT(*) FROM users")->fetchColumn() == 0) {
        $pdo->exec("INSERT INTO users (id, full_name, email, role) VALUES 
            (1, 'Admin User', 'admin@rosmon.edu', 'Admin'),
            (2, 'Mrs. Adebayo', 'adebayo@rosmon.edu', 'Teacher'),
            (3, 'Mr. Okonkwo', 'okonkwo@rosmon.edu', 'Teacher'),
            (4, 'Mrs. Ibrahim', 'ibrahim@rosmon.edu', 'Staff'),
            (5, 'Dr. Nwosu', 'nwosu@rosmon.edu', 'Principal'),
            (101, 'School Admin', 'schooladmin@rosmon.edu', 'Admin')
        ");
    }
    
    // Auto Migration: Notification Log Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS notification_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        channel ENUM('sms','email','push') NOT NULL,
        recipient_group VARCHAR(50) NOT NULL,
        recipient_count INT DEFAULT 0,
        subject VARCHAR(255) DEFAULT NULL,
        message_body TEXT NOT NULL,
        status ENUM('sent','failed','queued','draft') NOT NULL DEFAULT 'queued',
        sent_by INT NOT NULL,
        sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_channel (channel),
        INDEX idx_status (status)
    )");

    // Auto Migration: SMS/Email Templates
    $pdo->exec("CREATE TABLE IF NOT EXISTS notification_templates (
        id INT AUTO_INCREMENT PRIMARY KEY,
        template_name VARCHAR(100) NOT NULL,
        channel ENUM('sms','email') NOT NULL,
        subject VARCHAR(255) DEFAULT NULL,
        body TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Seed default templates
    if ($pdo->query("SELECT COUNT(*) FROM notification_templates")->fetchColumn() == 0) {
        $pdo->exec("INSERT INTO notification_templates (template_name, channel, subject, body) VALUES 
            ('Fee Reminder', 'sms', NULL, 'Dear Parent, this is a reminder that school fees for [TERM] are due by [DATE]. Amount: [AMOUNT]. Thank you. - Rosmon International School'),
            ('Exam Notice', 'sms', NULL, 'Dear Parent, [STUDENT] exams commence on [DATE]. Please ensure adequate preparation. - Rosmon International School'),
            ('Absence Alert', 'sms', NULL, 'Dear Parent, [STUDENT] was absent from school today [DATE]. Please contact the school office if this was unexpected. - Rosmon International School'),
            ('General Notice', 'email', 'Important Notice from Rosmon International School', 'Dear Parent/Guardian,\n\nWe would like to inform you about an important update regarding your ward.\n\n[MESSAGE]\n\nThank you for your cooperation.\n\nBest regards,\nRosmon International School'),
            ('Event Invitation', 'email', 'You are Invited! - [EVENT]', 'Dear Parent/Guardian,\n\nWe are pleased to invite you to [EVENT] scheduled for [DATE] at [VENUE].\n\n[DETAILS]\n\nWe look forward to seeing you.\n\nWarm regards,\nRosmon International School')
        ");
    }

    $current_user_id = $_SESSION['user_id'] ?? 1;
    $message = '';
    $msg_type = '';

    // Handle Send Notification
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_notification'])) {
        $channel = $_POST['channel'] ?? 'sms';
        $group = $_POST['recipient_group'] ?? 'all_parents';
        $subject = trim($_POST['subject'] ?? '');
        $body = trim($_POST['message_body'] ?? '');

        if (!empty($body)) {
            // Count mock recipients
            $counts = ['all_parents' => 145, 'all_students' => 312, 'all_employees' => 28, 'class_specific' => 35, 'individual' => 1];
            $count = $counts[$group] ?? 1;

            $stmt = $pdo->prepare("INSERT INTO notification_logs (channel, recipient_group, recipient_count, subject, message_body, status, sent_by) VALUES (?, ?, ?, ?, ?, 'sent', ?)");
            $stmt->execute([$channel, $group, $count, $subject, $body, $current_user_id]);
            $message = "Notification sent to $count recipients successfully!";
            $msg_type = 'success';
        }
    }

    // Handle Save Template
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_template'])) {
        $tpl_name = trim($_POST['tpl_name'] ?? '');
        $tpl_channel = $_POST['tpl_channel'] ?? 'sms';
        $tpl_subject = trim($_POST['tpl_subject'] ?? '');
        $tpl_body = trim($_POST['tpl_body'] ?? '');

        if (!empty($tpl_name) && !empty($tpl_body)) {
            $stmt = $pdo->prepare("INSERT INTO notification_templates (template_name, channel, subject, body) VALUES (?, ?, ?, ?)");
            $stmt->execute([$tpl_name, $tpl_channel, $tpl_subject ?: null, $tpl_body]);
            $message = "Template saved!";
            $msg_type = 'success';
        }
    }

    // Fetch Stats
    $total_sent = $pdo->query("SELECT COUNT(*) FROM notification_logs WHERE status='sent'")->fetchColumn();
    $sms_count = $pdo->query("SELECT COUNT(*) FROM notification_logs WHERE channel='sms' AND status='sent'")->fetchColumn();
    $email_count = $pdo->query("SELECT COUNT(*) FROM notification_logs WHERE channel='email' AND status='sent'")->fetchColumn();
    $total_recipients = $pdo->query("SELECT COALESCE(SUM(recipient_count),0) FROM notification_logs WHERE status='sent'")->fetchColumn();

    // Fetch Recent Logs
    $logs = $pdo->query("SELECT nl.*, u.full_name FROM notification_logs nl LEFT JOIN users u ON nl.sent_by = u.id ORDER BY nl.sent_at DESC LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);

    // Fetch Templates
    $templates = $pdo->query("SELECT * FROM notification_templates ORDER BY template_name")->fetchAll(PDO::FETCH_ASSOC);
    $sms_templates = array_filter($templates, fn($t) => $t['channel'] === 'sms');
    $email_templates = array_filter($templates, fn($t) => $t['channel'] === 'email');

} catch (PDOException $e) {
    die("Notification Error: " . $e->getMessage());
}

$pageTitle = 'SMS & Email Notifications - Rosmon SMS';
require ROOT_PATH . '/app/Views/school_admin/layout/header.php';
require ROOT_PATH . '/app/Views/school_admin/layout/sidebar.php';
?>

<style>
    .notif-stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px; }
    .notif-stat { background: var(--white); border: 1px solid var(--border); border-radius: 12px; padding: 22px; display: flex; align-items: center; gap: 14px; transition: 0.2s; }
    .notif-stat:hover { box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
    .notif-icon { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 22px; }
    .notif-val { font-size: 22px; font-weight: 800; }
    .notif-lbl { font-size: 11px; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; }

    .compose-grid { display: grid; grid-template-columns: 1fr 380px; gap: 20px; margin-bottom: 24px; }
    .compose-card { background: var(--white); border: 1px solid var(--border); border-radius: 12px; padding: 24px; }
    .compose-card h3 { font-size: 15px; font-weight: 800; margin-bottom: 20px; display: flex; align-items: center; gap: 8px; }
    .compose-card label { display:block; font-size:11px; font-weight:700; color:var(--text-muted); margin-bottom:6px; text-transform:uppercase; letter-spacing:0.5px; }
    .compose-card input, .compose-card select, .compose-card textarea { width:100%; padding:11px 14px; border:1px solid #e5e7eb; border-radius:8px; font-size:13px; margin-bottom:14px; outline:none; font-family:'Inter',sans-serif; }
    .compose-card textarea { min-height: 120px; resize: vertical; line-height: 1.6; }
    .compose-card input:focus, .compose-card select:focus, .compose-card textarea:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(16,23,142,0.08); }

    .channel-tabs { display: flex; gap: 8px; margin-bottom: 16px; }
    .channel-tab { flex: 1; padding: 14px; border-radius: 10px; border: 2px solid #e5e7eb; background: #fff; cursor: pointer; text-align: center; transition: 0.15s; }
    .channel-tab:hover { border-color: var(--primary); }
    .channel-tab.active { border-color: var(--primary); background: #f0f3ff; }
    .channel-tab i { font-size: 24px; display: block; margin-bottom: 4px; }
    .channel-tab span { font-size: 11px; font-weight: 700; }

    .template-card { padding: 14px; border: 1px solid #f3f4f6; border-radius: 10px; margin-bottom: 10px; cursor: pointer; transition: 0.15s; }
    .template-card:hover { border-color: var(--primary); background: #fafbff; }
    .template-card .tpl-name { font-size: 13px; font-weight: 700; color: var(--text-dark); }
    .template-card .tpl-preview { font-size: 11px; color: var(--text-muted); margin-top: 4px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .template-card .tpl-badge { font-size: 9px; font-weight: 700; padding: 2px 8px; border-radius: 20px; text-transform: uppercase; }

    .log-row { display: grid; grid-template-columns: 80px 1fr 120px 100px 80px 120px; align-items: center; padding: 14px 0; border-bottom: 1px solid #f5f5f5; font-size: 13px; }
    .log-row:last-child { border-bottom: none; }
</style>

<div class="main-container">
    <div class="top-header">
        <div class="greeting">Communication / <span style="color:var(--primary)">SMS & Email Notifications</span></div>
    </div>

    <?php if ($message): ?>
        <div style="padding:14px 20px; border-radius:10px; margin-bottom:20px; font-size:13px; font-weight:600; display:flex; align-items:center; gap:8px;
            background:<?= $msg_type === 'success' ? '#f0fdf4' : '#fef2f2' ?>; 
            color:<?= $msg_type === 'success' ? '#16a34a' : '#dc2626' ?>; 
            border: 1px solid <?= $msg_type === 'success' ? '#bbf7d0' : '#fecaca' ?>;">
            <i class="ph ph-<?= $msg_type === 'success' ? 'check-circle' : 'warning-circle' ?>"></i>
            <?= $message ?>
        </div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="notif-stats">
        <div class="notif-stat">
            <div class="notif-icon" style="background:#eff6ff; color:#3b82f6;"><i class="ph ph-paper-plane-right"></i></div>
            <div><div class="notif-val"><?= $total_sent ?></div><div class="notif-lbl">Total Sent</div></div>
        </div>
        <div class="notif-stat">
            <div class="notif-icon" style="background:#fef9c3; color:#eab308;"><i class="ph ph-chat-text"></i></div>
            <div><div class="notif-val"><?= $sms_count ?></div><div class="notif-lbl">SMS Sent</div></div>
        </div>
        <div class="notif-stat">
            <div class="notif-icon" style="background:#fce7f3; color:#ec4899;"><i class="ph ph-envelope"></i></div>
            <div><div class="notif-val"><?= $email_count ?></div><div class="notif-lbl">Emails Sent</div></div>
        </div>
        <div class="notif-stat">
            <div class="notif-icon" style="background:#f0fdf4; color:#22c55e;"><i class="ph ph-users-three"></i></div>
            <div><div class="notif-val"><?= number_format($total_recipients) ?></div><div class="notif-lbl">Total Reach</div></div>
        </div>
    </div>

    <!-- Compose + Templates -->
    <div class="compose-grid">
        <div class="compose-card">
            <h3><i class="ph ph-pencil-simple-line" style="color:var(--primary);"></i> Compose Message</h3>
            
            <form method="POST" id="compose-form">
                <input type="hidden" name="send_notification" value="1">
                
                <!-- Channel Selection -->
                <label>SELECT CHANNEL</label>
                <div class="channel-tabs">
                    <div class="channel-tab active" onclick="selectChannel('sms', this)">
                        <i class="ph ph-chat-text" style="color:#eab308;"></i>
                        <span>SMS</span>
                    </div>
                    <div class="channel-tab" onclick="selectChannel('email', this)">
                        <i class="ph ph-envelope" style="color:#ec4899;"></i>
                        <span>Email</span>
                    </div>
                </div>
                <input type="hidden" name="channel" id="channel-input" value="sms">

                <label>RECIPIENT GROUP</label>
                <select name="recipient_group">
                    <option value="all_parents">All Parents</option>
                    <option value="all_students">All Students</option>
                    <option value="all_employees">All Employees</option>
                    <option value="class_specific">Specific Class</option>
                </select>

                <div id="subject-row" style="display:none;">
                    <label>EMAIL SUBJECT</label>
                    <input type="text" name="subject" placeholder="Enter email subject line...">
                </div>

                <label>MESSAGE BODY</label>
                <textarea name="message_body" placeholder="Type your message here... Use [STUDENT], [DATE], [AMOUNT] as placeholders." required></textarea>

                <div style="display:flex; justify-content:space-between; align-items:center; margin-top:4px;">
                    <div style="font-size:11px; color:var(--text-muted);">
                        <span id="char-count">0</span> characters
                        <span id="sms-parts" style="margin-left:10px; color:#eab308; font-weight:700;"></span>
                    </div>
                    <button type="submit" class="btn-primary" style="display:flex; align-items:center; gap:6px; padding:12px 24px;">
                        <i class="ph ph-paper-plane-right"></i> Send Now
                    </button>
                </div>
            </form>
        </div>

        <!-- Templates Panel -->
        <div class="compose-card" style="overflow-y:auto; max-height:600px;">
            <h3><i class="ph ph-lightning" style="color:#f59e0b;"></i> Quick Templates</h3>
            
            <?php if (empty($templates)): ?>
                <div style="text-align:center; padding:30px; color:#9ca3af;">
                    <i class="ph ph-file-text" style="font-size:32px;"></i>
                    <p style="margin-top:8px; font-size:12px;">No templates yet</p>
                </div>
            <?php else: ?>
                <div style="margin-bottom:12px; font-size:11px; font-weight:700; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.5px;">SMS Templates</div>
                <?php foreach($sms_templates as $t): ?>
                    <div class="template-card" onclick="useTemplate('<?= addslashes($t['body']) ?>', '<?= addslashes($t['subject'] ?? '') ?>')">
                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <div class="tpl-name"><?= htmlspecialchars((string)($t['template_name'] ?? '')) ?></div>
                            <span class="tpl-badge" style="background:#fef9c3; color:#a16207;">SMS</span>
                        </div>
                        <div class="tpl-preview"><?= htmlspecialchars(substr($t['body'], 0, 60)) ?>...</div>
                    </div>
                <?php endforeach; ?>

                <div style="margin:16px 0 12px; font-size:11px; font-weight:700; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.5px;">Email Templates</div>
                <?php foreach($email_templates as $t): ?>
                    <div class="template-card" onclick="useTemplate('<?= addslashes($t['body']) ?>', '<?= addslashes($t['subject'] ?? '') ?>')">
                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <div class="tpl-name"><?= htmlspecialchars((string)($t['template_name'] ?? '')) ?></div>
                            <span class="tpl-badge" style="background:#fce7f3; color:#be185d;">EMAIL</span>
                        </div>
                        <div class="tpl-preview"><?= htmlspecialchars(substr($t['body'], 0, 60)) ?>...</div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Notification History -->
    <div class="crud-card">
        <div class="crud-header">
            <h2 class="crud-title">Notification History</h2>
        </div>
        <table class="crud-table">
            <thead>
                <tr>
                    <th>CHANNEL</th>
                    <th>RECIPIENTS</th>
                    <th>MESSAGE</th>
                    <th>STATUS</th>
                    <th>REACH</th>
                    <th>SENT</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                    <tr><td colspan="6" style="text-align:center; padding:40px; color:var(--text-muted);">No notifications sent yet.</td></tr>
                <?php else: ?>
                    <?php foreach($logs as $l): ?>
                        <tr>
                            <td>
                                <span style="display:inline-flex; align-items:center; gap:6px; font-weight:700; font-size:11px; padding:4px 12px; border-radius:20px;
                                    background:<?= $l['channel'] === 'sms' ? '#fef9c3' : '#fce7f3' ?>; 
                                    color:<?= $l['channel'] === 'sms' ? '#a16207' : '#be185d' ?>;">
                                    <i class="ph ph-<?= $l['channel'] === 'sms' ? 'chat-text' : 'envelope' ?>"></i>
                                    <?= strtoupper($l['channel']) ?>
                                </span>
                            </td>
                            <td style="font-weight:600;"><?= ucwords(str_replace('_', ' ', $l['recipient_group'])) ?></td>
                            <td>
                                <div style="font-size:12px; max-width:250px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?= htmlspecialchars((string)($l['message_body'] ?? '')) ?></div>
                            </td>
                            <td>
                                <span style="font-size:10px; font-weight:800; padding:3px 10px; border-radius:20px;
                                    background:<?= $l['status'] === 'sent' ? '#d1fae5; color:#065f46' : '#fee2e2; color:#991b1b' ?>;">
                                    <?= strtoupper($l['status']) ?>
                                </span>
                            </td>
                            <td style="font-weight:800; color:var(--primary);"><?= $l['recipient_count'] ?></td>
                            <td style="font-size:12px; color:var(--text-muted);"><?= date('M d, h:i A', strtotime($l['sent_at'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    function selectChannel(ch, el) {
        document.getElementById('channel-input').value = ch;
        document.querySelectorAll('.channel-tab').forEach(t => t.classList.remove('active'));
        el.classList.add('active');
        document.getElementById('subject-row').style.display = ch === 'email' ? 'block' : 'none';
    }

    function useTemplate(body, subject) {
        document.querySelector('textarea[name="message_body"]').value = body;
        if (subject) {
            document.querySelector('input[name="subject"]').value = subject;
        }
        // Scroll to compose
        document.querySelector('.compose-card').scrollIntoView({ behavior: 'smooth' });
    }

    // Character counter
    const textarea = document.querySelector('textarea[name="message_body"]');
    const charCount = document.getElementById('char-count');
    const smsParts = document.getElementById('sms-parts');
    
    textarea.addEventListener('input', function() {
        const len = this.value.length;
        charCount.textContent = len;
        if (document.getElementById('channel-input').value === 'sms') {
            const parts = Math.ceil(len / 160) || 0;
            smsParts.textContent = parts > 0 ? `(${parts} SMS part${parts > 1 ? 's' : ''})` : '';
        } else {
            smsParts.textContent = '';
        }
    });
</script>

<?php require ROOT_PATH . '/app/Views/school_admin/layout/footer.php'; ?>
