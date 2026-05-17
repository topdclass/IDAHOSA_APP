<?php
// Direct Messaging Module - 1-to-1 Private Chat

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
    
    // Auto Migration: Direct Messages Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS direct_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sender_id INT NOT NULL,
        receiver_id INT NOT NULL,
        message_text TEXT NOT NULL,
        is_read BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_conversation (sender_id, receiver_id),
        INDEX idx_receiver (receiver_id)
    )");

    $current_user_id = $_SESSION['user_id'] ?? 1;
    $active_chat = isset($_GET['with']) ? (int)$_GET['with'] : null;

    // Handle sending DM
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_dm'])) {
        $text = trim($_POST['dm_text'] ?? '');
        $to_user = (int)$_POST['to_user'];

        if (!empty($text) && $to_user > 0) {
            $stmt = $pdo->prepare("INSERT INTO direct_messages (sender_id, receiver_id, message_text) VALUES (?, ?, ?)");
            $stmt->execute([$current_user_id, $to_user, $text]);
            header("Location: " . strtok($_SERVER['REQUEST_URI'], '?') . "?with=" . $to_user);
            exit;
        }
    }

    // Mark messages as read
    if ($active_chat) {
        $pdo->prepare("UPDATE direct_messages SET is_read = TRUE WHERE sender_id = ? AND receiver_id = ? AND is_read = FALSE")
            ->execute([$active_chat, $current_user_id]);
    }

    // Fetch all users for contacts list
    $contacts = $pdo->query("SELECT id, full_name, role, email FROM users WHERE id != $current_user_id ORDER BY full_name ASC")->fetchAll(PDO::FETCH_ASSOC);

    // Get unread counts for each contact
    $unread_stmt = $pdo->prepare("SELECT sender_id, COUNT(*) as unread FROM direct_messages WHERE receiver_id = ? AND is_read = FALSE GROUP BY sender_id");
    $unread_stmt->execute([$current_user_id]);
    $unread_counts = [];
    foreach ($unread_stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $unread_counts[$row['sender_id']] = $row['unread'];
    }

    // Fetch conversation messages if active chat
    $dm_messages = [];
    if ($active_chat) {
        $stmt = $pdo->prepare("SELECT dm.*, u.full_name, u.role 
                               FROM direct_messages dm 
                               JOIN users u ON dm.sender_id = u.id 
                               WHERE (dm.sender_id = ? AND dm.receiver_id = ?) 
                                  OR (dm.sender_id = ? AND dm.receiver_id = ?) 
                               ORDER BY dm.created_at ASC LIMIT 100");
        $stmt->execute([$current_user_id, $active_chat, $active_chat, $current_user_id]);
        $dm_messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get active user details
    $active_user = null;
    if ($active_chat) {
        $stmt = $pdo->prepare("SELECT id, full_name, role, email FROM users WHERE id = ?");
        $stmt->execute([$active_chat]);
        $active_user = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Get last message per contact for preview
    $last_msgs = [];
    foreach ($contacts as $c) {
        $stmt = $pdo->prepare("SELECT message_text, created_at FROM direct_messages 
                               WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?) 
                               ORDER BY created_at DESC LIMIT 1");
        $stmt->execute([$current_user_id, $c['id'], $c['id'], $current_user_id]);
        $last_msgs[$c['id']] = $stmt->fetch(PDO::FETCH_ASSOC);
    }

} catch (PDOException $e) {
    die("DM System Error: " . $e->getMessage());
}

$pageTitle = 'Direct Messaging - Rosmon SMS';
require ROOT_PATH . '/app/Views/school_admin/layout/header.php';
require ROOT_PATH . '/app/Views/school_admin/layout/sidebar.php';
?>

<style>
    .dm-layout { display: grid; grid-template-columns: 340px 1fr; height: 660px; border-radius: 12px; overflow: hidden; border: 1px solid var(--border); background: var(--white); box-shadow: 0 2px 10px rgba(0,0,0,0.02); }
    .dm-contacts { border-right: 1px solid var(--border); display: flex; flex-direction: column; background: #fafbfc; }
    .dm-search-bar { padding: 20px; border-bottom: 1px solid var(--border); }
    .dm-search-bar input { width: 100%; padding: 12px 16px 12px 42px; border: 1px solid #e5e7eb; border-radius: 10px; font-size: 13px; outline: none; background: #fff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 256 256'%3E%3Cpath d='M229.66,218.34l-50.07-50.07a88.11,88.11,0,1,0-11.31,11.31l50.06,50.07a8,8,0,0,0,11.32-11.31ZM40,112a72,72,0,1,1,72,72A72.08,72.08,0,0,1,40,112Z' fill='%239ca3af'/%3E%3C/svg%3E") 14px center no-repeat; }
    .dm-contact-list { flex: 1; overflow-y: auto; }
    .dm-contact-item { display: flex; align-items: center; gap: 12px; padding: 16px 20px; cursor: pointer; transition: 0.15s; border-bottom: 1px solid #f5f5f5; position: relative; }
    .dm-contact-item:hover { background: #f0f3ff; }
    .dm-contact-item.active { background: #eef0ff; border-left: 3px solid var(--primary); }
    .dm-avatar { width: 42px; height: 42px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 16px; flex-shrink: 0; }
    .dm-contact-info { flex: 1; min-width: 0; }
    .dm-contact-name { font-size: 13px; font-weight: 700; color: var(--text-dark); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .dm-contact-preview { font-size: 11px; color: var(--text-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-top: 3px; }
    .dm-contact-role { font-size: 9px; font-weight: 700; padding: 2px 8px; border-radius: 20px; text-transform: uppercase; letter-spacing: 0.5px; }
    .dm-unread-badge { width: 20px; height: 20px; border-radius: 50%; background: #ef4444; color: #fff; font-size: 10px; font-weight: 800; display: flex; align-items: center; justify-content: center; }
    .dm-chat-panel { display: flex; flex-direction: column; }
    .dm-chat-header { padding: 18px 24px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; background: #fdfdfd; }
    .dm-messages { flex: 1; overflow-y: auto; padding: 24px; display: flex; flex-direction: column; gap: 16px; background: #f9fafb; }
    .dm-input-area { padding: 18px 24px; border-top: 1px solid var(--border); background: #fff; }
    .dm-input-area form { display: flex; gap: 12px; align-items: center; }
    .dm-input-area input[type="text"] { flex: 1; padding: 14px 20px; border: 1px solid #e5e7eb; border-radius: 30px; outline: none; font-size: 14px; background: #f9fafb; }
    .dm-send-btn { width: 46px; height: 46px; border-radius: 50%; background: var(--primary); color: #fff; border: none; cursor: pointer; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 10px rgba(16,23,142,0.3); transition: 0.2s; }
    .dm-send-btn:hover { transform: scale(1.05); }
    .dm-bubble { max-width: 68%; padding: 12px 18px; border-radius: 20px; font-size: 14px; line-height: 1.5; box-shadow: 0 1px 4px rgba(0,0,0,0.04); }
    .dm-bubble.sent { background: var(--primary); color: #fff; border-radius: 20px 20px 0 20px; align-self: flex-end; }
    .dm-bubble.received { background: #fff; color: var(--text-dark); border: 1px solid #f1f1f1; border-radius: 20px 20px 20px 0; align-self: flex-start; }
    .dm-bubble-time { font-size: 9px; color: #9ca3af; margin-top: 5px; }
    .dm-bubble.sent .dm-bubble-time { color: rgba(255,255,255,0.6); text-align: right; }
    .dm-empty-state { display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; color: #9ca3af; text-align: center; }
    .dm-empty-state i { font-size: 64px; margin-bottom: 16px; opacity: 0.4; }
    .dm-empty-state p { font-size: 14px; }
    .dm-contact-list::-webkit-scrollbar, .dm-messages::-webkit-scrollbar { width: 4px; }
    .dm-contact-list::-webkit-scrollbar-thumb, .dm-messages::-webkit-scrollbar-thumb { background: #e5e7eb; border-radius: 4px; }
</style>

<div class="main-container">
    <div class="top-header">
        <div class="greeting">Communication / <span style="color:var(--primary)">Direct Messaging</span></div>
        <div class="header-actions">
            <div style="font-size:12px; color:var(--text-muted);"><i class="ph ph-shield-check" style="color:#10b981;"></i> End-to-End Private</div>
        </div>
    </div>

    <div class="dm-layout">
        <!-- Contact List -->
        <div class="dm-contacts">
            <div class="dm-search-bar">
                <input type="text" id="dm-search" placeholder="Search contacts..." oninput="filterContacts(this.value)">
            </div>
            <div class="dm-contact-list" id="contact-list">
                <?php if (empty($contacts)): ?>
                    <div style="padding: 40px; text-align:center; color:#9ca3af; font-size:13px;">
                        <i class="ph ph-address-book" style="font-size:32px; display:block; margin-bottom:8px;"></i>
                        No contacts available
                    </div>
                <?php else: ?>
                    <?php foreach($contacts as $c): 
                        $isActive = ($active_chat == $c['id']);
                        $unread = $unread_counts[$c['id']] ?? 0;
                        $lastMsg = $last_msgs[$c['id']] ?? null;
                        $colors = ['Teacher' => '#818cf8', 'Admin' => '#f472b6', 'Parent' => '#34d399', 'Staff' => '#fbbf24'];
                        $bgColor = $colors[$c['role']] ?? '#94a3b8';
                    ?>
                        <a href="?with=<?= $c['id'] ?>" style="text-decoration:none;">
                            <div class="dm-contact-item <?= $isActive ? 'active' : '' ?>" data-name="<?= strtolower(htmlspecialchars((string)($c['full_name'] ?? ''))) ?>">
                                <div class="dm-avatar" style="background:<?= $bgColor ?>20; color:<?= $bgColor ?>;">
                                    <?= strtoupper(substr($c['full_name'], 0, 2)) ?>
                                </div>
                                <div class="dm-contact-info">
                                    <div class="dm-contact-name"><?= htmlspecialchars((string)($c['full_name'] ?? '')) ?></div>
                                    <div class="dm-contact-preview">
                                        <?php if ($lastMsg): ?>
                                            <?= htmlspecialchars(substr($lastMsg['message_text'], 0, 35)) ?><?= strlen($lastMsg['message_text']) > 35 ? '...' : '' ?>
                                        <?php else: ?>
                                            Start a conversation
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div style="display:flex; flex-direction:column; align-items:flex-end; gap:6px;">
                                    <span class="dm-contact-role" style="background:<?= $bgColor ?>18; color:<?= $bgColor ?>;"><?= htmlspecialchars((string)($c['role'] ?? '')) ?></span>
                                    <?php if ($unread > 0): ?>
                                        <div class="dm-unread-badge"><?= $unread ?></div>
                                    <?php elseif ($lastMsg): ?>
                                        <div style="font-size:9px; color:#bbb;"><?= date('h:i A', strtotime($lastMsg['created_at'])) ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Chat Panel -->
        <div class="dm-chat-panel">
            <?php if ($active_user): ?>
                <div class="dm-chat-header">
                    <div style="display:flex; align-items:center; gap:12px;">
                        <div class="dm-avatar" style="background:#818cf820; color:#818cf8; width:40px; height:40px; font-size:14px;">
                            <?= strtoupper(substr($active_user['full_name'], 0, 2)) ?>
                        </div>
                        <div>
                            <div style="font-size:14px; font-weight:700;"><?= htmlspecialchars((string)($active_user['full_name'] ?? '')) ?></div>
                            <div style="font-size:10px; color:#10b981; font-weight:600;"><span style="display:inline-block; width:6px; height:6px; background:#10b981; border-radius:50%; margin-right:4px;"></span>Online</div>
                        </div>
                    </div>
                    <div style="display:flex; gap:18px; color:#9ca3af;">
                        <i class="ph ph-phone" style="font-size:18px; cursor:pointer;" title="Voice Call"></i>
                        <i class="ph ph-video-camera" style="font-size:18px; cursor:pointer;" title="Video Call"></i>
                        <i class="ph ph-info" style="font-size:18px; cursor:pointer;" title="Info"></i>
                    </div>
                </div>

                <div class="dm-messages" id="dm-window">
                    <?php if (empty($dm_messages)): ?>
                        <div class="dm-empty-state">
                            <i class="ph ph-hand-waving"></i>
                            <p>No messages yet. Say hello to <strong><?= htmlspecialchars((string)($active_user['full_name'] ?? '')) ?></strong>!</p>
                        </div>
                    <?php else: ?>
                        <?php 
                        $lastDate = '';
                        foreach($dm_messages as $m): 
                            $isSent = ($m['sender_id'] == $current_user_id);
                            $msgDate = date('M d, Y', strtotime($m['created_at']));
                            if ($msgDate !== $lastDate):
                                $lastDate = $msgDate;
                        ?>
                            <div style="text-align:center; font-size:10px; color:#9ca3af; font-weight:700; padding:8px 0;">
                                <span style="background:#f3f4f6; padding:4px 14px; border-radius:20px;"><?= $msgDate ?></span>
                            </div>
                        <?php endif; ?>
                            <div class="dm-bubble <?= $isSent ? 'sent' : 'received' ?>">
                                <?= htmlspecialchars((string)($m['message_text'] ?? '')) ?>
                                <div class="dm-bubble-time">
                                    <?= date('h:i A', strtotime($m['created_at'])) ?>
                                    <?php if ($isSent): ?>
                                        <i class="ph ph-checks" style="margin-left:4px;"></i>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div class="dm-input-area">
                    <form method="POST">
                        <input type="hidden" name="send_dm" value="1">
                        <input type="hidden" name="to_user" value="<?= $active_chat ?>">
                        <button type="button" style="background:none; border:none; color:#9ca3af; cursor:pointer;"><i class="ph ph-paperclip" style="font-size:22px;"></i></button>
                        <input type="text" name="dm_text" autocomplete="off" placeholder="Type your message..." required>
                        <button type="button" style="background:none; border:none; color:#9ca3af; cursor:pointer;"><i class="ph ph-smiley" style="font-size:22px;"></i></button>
                        <button type="submit" class="dm-send-btn"><i class="ph ph-paper-plane-right-bold" style="font-size:18px;"></i></button>
                    </form>
                </div>
            <?php else: ?>
                <div class="dm-empty-state" style="height:100%;">
                    <i class="ph ph-chat-circle-dots" style="font-size:80px; opacity:0.25;"></i>
                    <p style="margin-top:16px; font-size:16px; font-weight:600;">Select a conversation</p>
                    <p style="font-size:12px; margin-top:4px;">Choose a contact from the left to start messaging</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    // Auto scroll chat to bottom
    const dmWin = document.getElementById('dm-window');
    if (dmWin) dmWin.scrollTop = dmWin.scrollHeight;

    // Contact search filter
    function filterContacts(q) {
        const items = document.querySelectorAll('.dm-contact-item');
        q = q.toLowerCase();
        items.forEach(item => {
            const name = item.getAttribute('data-name');
            item.parentElement.style.display = name.includes(q) ? '' : 'none';
        });
    }
</script>

<?php require ROOT_PATH . '/app/Views/school_admin/layout/footer.php'; ?>
