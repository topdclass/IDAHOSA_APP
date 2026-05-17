<?php
/**
 * Student Messaging Hub
 * Connect with Subject Teachers and Colleagues
 */
require_once dirname(dirname(dirname(__DIR__))) . '/config/database.php';
$userId = $_SESSION['user_id'] ?? 0;

// 1. Fetch Student/Class Info
$stmt = $pdo->prepare("SELECT s.class_id, c.class_name as class_name
                      FROM institute_students s
                      LEFT JOIN classes c ON s.class_id = c.id
                      WHERE s.student_id = ? LIMIT 1");
$stmt->execute([$userId]);
$student = $stmt->fetch();
$classId = $student['class_id'] ?? 0;

// 2. Fetch Subject Teachers
$stmt = $pdo->prepare("SELECT DISTINCT u.id, u.full_name, cs.name as subject 
                      FROM class_subjects cs 
                      JOIN users u ON cs.teacher_id = u.id 
                      WHERE cs.class_id = ? AND cs.is_deleted = 0");
$stmt->execute([$classId]);
$teachers = $stmt->fetchAll();

// 3. Fetch Colleagues (Classmates)
$stmt = $pdo->prepare("SELECT u.id, u.full_name 
                      FROM institute_students s 
                      JOIN users u ON s.student_id = u.id 
                      WHERE s.class_id = ? AND s.student_id != ?");
$stmt->execute([$classId, $userId]);
$colleagues = $stmt->fetchAll();

// 4. Handle Message Send
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_msg'])) {
    $receiverId = $_POST['receiver_id'];
    $messageText = $_POST['message_text'];
    if (!empty($messageText)) {
        $stmt = $pdo->prepare("INSERT INTO direct_messages (sender_id, receiver_id, message_text) VALUES (?, ?, ?)");
        $stmt->execute([$userId, $receiverId, $messageText]);
        header("Location: messaging?chat=" . $receiverId);
        exit;
    }
}

// 5. Fetch Active Chat Messages
$activeChatId = $_GET['chat'] ?? 0;
$messages = [];
$activeRecipient = null;
if ($activeChatId) {
    // Mark as read
    $pdo->prepare("UPDATE direct_messages SET is_read = 1 WHERE receiver_id = ? AND sender_id = ?")
        ->execute([$userId, $activeChatId]);

    $stmt = $pdo->prepare("SELECT * FROM direct_messages 
                          WHERE (sender_id = ? AND receiver_id = ?) 
                          OR (sender_id = ? AND receiver_id = ?) 
                          ORDER BY created_at ASC");
    $stmt->execute([$userId, $activeChatId, $activeChatId, $userId]);
    $messages = $stmt->fetchAll();

    $stmt = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
    $stmt->execute([$activeChatId]);
    $activeRecipient = $stmt->fetchColumn();
}

$pageTitle = 'Messaging - Rosmon SMS';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <style>
        :root {
            --primary: #4f46e5; --bg: #f8fafc; --border: #e2e8f0; --white: #ffffff;
        }
        * { box-sizing: border-box; font-family: 'Outfit', sans-serif; }
        body { margin: 0; background: var(--bg); display: flex; height: 100vh; overflow: hidden; }

        .sidebar { width: 270px; background: var(--white); border-right: 1px solid var(--border); padding: 30px 20px; display: flex; flex-direction: column; }
        .nav-item { display:flex; align-items:center; gap:12px; padding:12px 16px; text-decoration:none; color:#64748b; border-radius:10px; margin-bottom:5px; font-weight:600; font-size:14px; }
        .nav-item.active { background: #eef2ff; color: var(--primary); }

        .msg-container { flex: 1; display: flex; }
        .contacts-pane { width: 320px; background: #fff; border-right: 1px solid var(--border); display: flex; flex-direction: column; }
        .chat-pane { flex: 1; display: flex; flex-direction: column; background: #fdfdfd; }

        .pane-header { padding: 25px; border-bottom: 1px solid var(--border); font-weight: 800; font-size: 18px; }
        .contact-list { flex: 1; overflow-y: auto; padding: 15px; }
        .contact-item { display: flex; align-items: center; gap: 12px; padding: 12px; border-radius: 12px; cursor: pointer; text-decoration: none; color: #1e293b; margin-bottom: 8px; transition: 0.2s; }
        .contact-item:hover { background: #f8fafc; }
        .contact-item.active { background: #f1f5f9; }
        .avatar { width: 40px; height: 40px; border-radius: 12px; background: #e2e8f0; display: flex; align-items: center; justify-content: center; font-weight: 800; color: #64748b; }

        .messages-box { flex: 1; overflow-y: auto; padding: 30px; display: flex; flex-direction: column; gap: 15px; }
        .bubble { max-width: 70%; padding: 12px 18px; border-radius: 16px; font-size: 14px; line-height: 1.5; font-weight: 500; }
        .bubble.sent { align-self: flex-end; background: var(--primary); color: white; border-bottom-right-radius: 4px; }
        .bubble.received { align-self: flex-start; background: #f1f5f9; color: #1e293b; border-bottom-left-radius: 4px; }
        .msg-time { font-size: 10px; margin-top: 5px; opacity: 0.7; display: block; }

        .input-area { padding: 20px 30px; background: #fff; border-top: 1px solid var(--border); display: flex; gap: 15px; }
        .msg-input { flex: 1; border: 1px solid var(--border); border-radius: 12px; padding: 12px 20px; outline: none; transition: 0.2s; }
        .msg-input:focus { border-color: var(--primary); }
        .btn-send { background: var(--primary); color: white; border: none; padding: 0 25px; border-radius: 12px; font-weight: 700; cursor: pointer; }

        .section-label { font-size: 11px; font-weight: 800; color: #94a3b8; text-transform: uppercase; margin: 20px 0 10px 10px; letter-spacing: 1px; }
    </style>
</head>
<body>
    <div class="sidebar">
        <h2 style="font-size: 16px; margin-bottom: 40px; font-weight: 800; color: var(--primary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?= htmlspecialchars($globalSchoolName ?? 'Rosmon International School') ?>"><?= strtoupper(htmlspecialchars($globalSchoolName ?? 'Rosmon International School')) ?></h2>
        <a href="dashboard" class="nav-item"><i class="ph ph-chart-line-up"></i> Dashboard</a>
        <a href="attendance" class="nav-item"><i class="ph ph-fingerprint"></i> Clocking</a>
        <a href="messaging" class="nav-item active"><i class="ph ph-chat-circle-dots"></i> Messaging</a>
        <a href="payments" class="nav-item"><i class="ph ph-receipt"></i> Payments</a>
        <a href="timetable" class="nav-item"><i class="ph ph-calendar-blank"></i> Timetable</a>
    </div>

    <div class="msg-container">
        <div class="contacts-pane">
            <div class="pane-header">Inbox</div>
            <div class="contact-list">
                <div class="section-label">Subject Teachers</div>
                <?php foreach($teachers as $t): ?>
                    <a href="?chat=<?= $t['id'] ?>" class="contact-item <?= ($activeChatId == $t['id']) ? 'active' : '' ?>">
                        <div class="avatar" style="background:#e0e7ff; color:#4338ca;"><?= strtoupper(substr($t['full_name'], 0, 1)) ?></div>
                        <div>
                            <div style="font-weight:700; font-size:14px;"><?= htmlspecialchars($t['full_name']) ?></div>
                            <div style="font-size:11px; color:#64748b;"><?= htmlspecialchars($t['subject']) ?> Teacher</div>
                        </div>
                    </a>
                <?php endforeach; ?>

                <div class="section-label">Class Colleague</div>
                <?php foreach($colleagues as $c): ?>
                    <a href="?chat=<?= $c['id'] ?>" class="contact-item <?= ($activeChatId == $c['id']) ? 'active' : '' ?>">
                        <div class="avatar"><?= strtoupper(substr($c['full_name'], 0, 1)) ?></div>
                        <div>
                            <div style="font-weight:700; font-size:14px;"><?= htmlspecialchars($c['full_name']) ?></div>
                            <div style="font-size:11px; color:#64748b;">Classmate</div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="chat-pane">
            <?php if ($activeChatId): ?>
                <div class="pane-header" style="display:flex; align-items:center; gap:15px;">
                    <div class="avatar" style="width:35px; height:35px; font-size:14px;"><?= strtoupper(substr($activeRecipient, 0, 1)) ?></div>
                    <?= htmlspecialchars($activeRecipient) ?>
                </div>
                <div class="messages-box" id="msgBox">
                    <?php if (empty($messages)): ?>
                        <div style="text-align:center; padding-top:100px; color:#94a3b8;">
                            <i class="ph ph-chat-circle-dots" style="font-size:60px; opacity:0.3;"></i>
                            <p>No messages yet. Say hello!</p>
                        </div>
                    <?php else: ?>
                        <?php foreach($messages as $m): ?>
                            <div class="bubble <?= ($m['sender_id'] == $userId) ? 'sent' : 'received' ?>">
                                <?= htmlspecialchars($m['message_text']) ?>
                                <span class="msg-time"><?= date('h:i A', strtotime($m['created_at'])) ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <form class="input-area" method="POST">
                    <input type="hidden" name="receiver_id" value="<?= $activeChatId ?>">
                    <input type="text" name="message_text" class="msg-input" placeholder="Type your message here..." autocomplete="off" required autofocus>
                    <button type="submit" name="send_msg" class="btn-send">Send</button>
                </form>
            <?php else: ?>
                <div style="flex:1; display:flex; flex-direction:column; align-items:center; justify-content:center; color:#94a3b8;">
                    <i class="ph ph-chat-teaser" style="font-size:120px; opacity:0.1;"></i>
                    <h2 style="margin-top:20px; font-weight:800; color:#cbd5e1;">Your Digital Courier</h2>
                    <p style="font-weight:600;">Select a teacher or classmate to start messaging</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Scroll to bottom of message box
        const msgBox = document.getElementById('msgBox');
        if (msgBox) {
            msgBox.scrollTop = msgBox.scrollHeight;
        }
    </script>
</body>
</html>
