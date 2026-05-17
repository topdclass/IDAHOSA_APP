<?php
$pageTitle = 'Faculty Messaging Center - Rosmon SMS';
require ROOT_PATH . '/app/Views/employee/layout/header.php';

$me = $_SESSION['user_id'] ?? 0;

// Handle sending message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_direct'])) {
    $target_room = $_POST['room_id'];
    $msg = trim($_POST['message_body']);
    if (!empty($msg)) {
        $stmt = $pdo->prepare("INSERT INTO chats (user_id, message, room_id, message_ref) VALUES (?, ?, ?, 'direct')");
        $stmt->execute([$me, $msg, $target_room]);
        header("Location: " . WEB_ROOT . "/employee/messages?room=" . $target_room);
        exit;
    }
}

// 1. Fetch my conversations (Active rooms)
// Join chats group by room_id to find active conversations involving me
// To handle DMs, room_id could be 'dm_x_y'
$stmt = $pdo->prepare("
    SELECT DISTINCT room_id 
    FROM chats 
    WHERE user_id = :me1 OR room_id LIKE CONCAT('dm_', :me2, '_%') OR room_id LIKE CONCAT('dm_%_', :me3)
    ORDER BY created_at DESC
");
$stmt->execute([':me1' => $me, ':me2' => $me, ':me3' => $me]);
$active_rooms = $stmt->fetchAll(PDO::FETCH_COLUMN);

$conversations = [];
foreach ($active_rooms as $rid) {
    if ($rid === 'institute_global') continue;
    
    // Determine the other user in DM
    $other_id = null;
    if (strpos($rid, 'dm_') === 0) {
        $parts = explode('_', $rid);
        $other_id = ($parts[1] == $me) ? $parts[2] : $parts[1];
    }
    
    if ($other_id) {
        $uStmt = $pdo->prepare("SELECT full_name, profile_image FROM users WHERE id = ?");
        $uStmt->execute([$other_id]);
        $u = $uStmt->fetch(PDO::FETCH_ASSOC);
        if ($u) {
            $lastMsg = $pdo->prepare("SELECT message, created_at FROM chats WHERE room_id = ? ORDER BY id DESC LIMIT 1");
            $lastMsg->execute([$rid]);
            $msg = $lastMsg->fetch(PDO::FETCH_ASSOC);
            
            $conversations[] = [
                'room_id' => $rid,
                'name' => $u['full_name'],
                'avatar' => $u['profile_image'] ?: null,
                'last_msg' => $msg['message'] ?? '',
                'time' => $msg['created_at'] ?? ''
            ];
        }
    }
}

// 2. Data for "New Conversation" modal
// Fetch Admin, Students and Parents in my classes
$contacts = [];

// Admin/Staff
$stmt = $pdo->prepare("SELECT id, full_name, role FROM users WHERE role IN ('school_admin', 'employee') AND id != ? LIMIT 30");
$stmt->execute([$me]);
$contacts['Staff'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Students/Parents
$stmt = $pdo->prepare("
    SELECT DISTINCT u.id, u.full_name, u.role
    FROM users u
    JOIN institute_students s ON u.id = s.student_id
    JOIN class_subjects cs ON s.class_id = cs.class_id
    WHERE cs.teacher_id = ? AND s.is_deleted = 0
");
$stmt->execute([$me]);
$contacts['Students'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

$current_room = $_GET['room'] ?? null;
$messages = [];
if ($current_room) {
    $mStmt = $pdo->prepare("
        SELECT c.*, u.full_name 
        FROM chats c
        JOIN users u ON c.user_id = u.id
        WHERE c.room_id = ? AND c.is_deleted = 0
        ORDER BY c.id ASC
    ");
    $mStmt->execute([$current_room]);
    $messages = $mStmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<style>
    .messenger-wrap {
        display: grid;
        grid-template-columns: 320px 1fr;
        height: calc(100vh - 180px);
        background: white;
        border-radius: 20px;
        border: 1px solid #f1f5f9;
        overflow: hidden;
        margin-top: 20px;
        box-shadow: 0 10px 25px -5px rgba(0,0,0,0.05);
    }

    .sidebar {
        border-right: 1px solid #f1f5f9;
        display: flex;
        flex-direction: column;
        background: #fcfdfe;
    }

    .sidebar-header {
        padding: 24px;
        border-bottom: 1px solid #f1f5f9;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .sidebar-header h2 { font-size: 18px; font-weight: 800; margin: 0; color: #1e293b; }

    .new-chat-btn {
        width: 36px;
        height: 36px;
        border-radius: 10px;
        border: none;
        background: var(--primary);
        color: white;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: 0.2s;
    }

    .conv-list {
        flex: 1;
        overflow-y: auto;
    }

    .conv-item {
        padding: 16px 24px;
        display: flex;
        gap: 12px;
        cursor: pointer;
        transition: 0.2s;
        border-bottom: 1px solid #f8fafc;
        text-decoration: none;
    }

    .conv-item:hover { background: #f8fafc; }
    .conv-item.active { background: #eff6ff; border-right: 3px solid var(--primary); }

    .avatar {
        width: 44px;
        height: 44px;
        border-radius: 12px;
        background: #e2e8f0;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 800;
        color: #64748b;
        flex-shrink: 0;
    }

    .conv-info { flex: 1; min-width:0; }
    .conv-name { font-size: 14px; font-weight: 700; color: #1e293b; margin-bottom: 4px; }
    .conv-msg { font-size: 12px; color: #94a3b8; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

    .chat-window { display: flex; flex-direction: column; background: white; }
    .chat-header { padding: 18px 24px; border-bottom: 1px solid #f1f5f9; display: flex; align-items: center; gap: 12px; }
    .chat-body { flex: 1; padding: 24px; overflow-y: auto; background: #fafbfc; display: flex; flex-direction: column; gap: 15px; }

    .msg-wrap { display: flex; flex-direction: column; max-width: 70%; }
    .msg-wrap.me { align-self: flex-end; }
    .bubble { 
        padding: 12px 18px; 
        border-radius: 18px; 
        font-size: 14px; 
        line-height: 1.5;
        box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02);
    }
    .bubble.me { background: var(--primary); color: white; border-bottom-right-radius: 4px; }
    .bubble.them { background: white; color: #334155; border: 1px solid #f1f5f9; border-bottom-left-radius: 4px; }

    .chat-footer { padding: 20px 24px; border-top: 1px solid #f1f5f9; display: flex; gap: 12px; }
    .chat-input { flex: 1; padding: 12px 20px; border-radius: 12px; border: 1px solid #e2e8f0; outline: none; transition: 0.2s; }
    .chat-input:focus { border-color: var(--primary); }

    /* Modal Styling */
    #contact-modal { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:1000; align-items:center; justify-content:center; }
    .modal-card { width: 450px; background:white; border-radius:16px; padding:24px; max-height:80vh; display:flex; flex-direction:column; }
    .modal-list { flex:1; overflow-y:auto; margin-top:15px; }
    .contact-item { padding:12px; border-radius:8px; display:flex; align-items:center; gap:12px; transition:0.2s; cursor:pointer; }
    .contact-item:hover { background:#f1f5f9; }
</style>

<div class="messenger-wrap">
    <!-- Conversations Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h2>Inbox</h2>
            <button class="new-chat-btn" onclick="document.getElementById('contact-modal').style.display='flex'">
                <i class="ph ph-plus"></i>
            </button>
        </div>
        <div class="conv-list">
            <?php if (empty($conversations)): ?>
                <div style="padding:40px; text-align:center; color:#94a3b8; font-size:13px;">No active conversations yet.</div>
            <?php endif; ?>
            <?php foreach($conversations as $c): ?>
                <a href="?room=<?= $c['room_id'] ?>" class="conv-item <?= $current_room == $c['room_id'] ? 'active' : '' ?>">
                    <div class="avatar"><?= strtoupper(substr($c['name'], 0, 1)) ?></div>
                    <div class="conv-info">
                        <div class="conv-name"><?= htmlspecialchars($c['name']) ?></div>
                        <div class="conv-msg"><?= htmlspecialchars($c['last_msg']) ?></div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Active Chat Window -->
    <div class="chat-window">
        <?php if ($current_room): ?>
            <?php 
                $parts = explode('_', $current_room);
                $other_id = ($parts[1] == $me) ? $parts[2] : $parts[1];
                $uStmt = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
                $uStmt->execute([$other_id]);
                $other_name = $uStmt->fetchColumn();
            ?>
            <div class="chat-header">
                <div class="avatar" style="width:36px; height:36px;"><?= strtoupper(substr($other_name, 0, 1)) ?></div>
                <div style="font-weight:800; color:#1e293b;"><?= htmlspecialchars($other_name) ?></div>
            </div>
            <div class="chat-body" id="chat-body">
                <?php foreach($messages as $m): $itMe = ($m['user_id'] == $me); ?>
                    <div class="msg-wrap <?= $itMe ? 'me' : 'them' ?>">
                        <div class="bubble <?= $itMe ? 'me' : 'them' ?>">
                            <?= nl2br(htmlspecialchars($m['message'])) ?>
                        </div>
                        <div style="font-size:10px; color:#94a3b8; margin-top:4px; text-align:<?= $itMe ? 'right' : 'left' ?>">
                            <?= date('h:i A', strtotime($m['created_at'])) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <form method="POST" class="chat-footer">
                <input type="hidden" name="room_id" value="<?= $current_room ?>">
                <input type="text" name="message_body" class="chat-input" placeholder="Aa" required autocomplete="off">
                <button type="submit" name="send_direct" class="btn-primary" style="padding:10px 24px; border-radius:12px;">Send</button>
            </form>
        <?php else: ?>
            <div style="flex:1; display:flex; flex-direction:column; align-items:center; justify-content:center; color:#94a3b8;">
                <i class="ph ph-chat-circle-dots" style="font-size:64px; margin-bottom:20px; opacity:0.3;"></i>
                <h3>Select a conversation to start messaging</h3>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Contacts Picker Modal -->
<div id="contact-modal" onclick="if(event.target == this) this.style.display='none'">
    <div class="modal-card">
        <h3 style="margin:0;">Start Conversation</h3>
        <div class="modal-list">
            <?php foreach($contacts as $group => $list): ?>
                <div style="font-size:11px; font-weight:800; color:#94a3b8; margin:15px 0 8px 0; text-transform:uppercase;"><?= $group ?></div>
                <?php foreach($list as $con): ?>
                    <div class="contact-item" onclick="startDM(<?= $con['id'] ?>)">
                        <div class="avatar" style="width:32px; height:32px;"><?= strtoupper(substr($con['full_name'], 0, 1)) ?></div>
                        <div style="font-size:14px; font-weight:700; color:#1e293b;"><?= htmlspecialchars($con['full_name']) ?></div>
                    </div>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script>
    function startDM(otherId) {
        const me = <?= $me ?>;
        const room = 'dm_' + (me < otherId ? me + '_' + otherId : otherId + '_' + me);
        window.location.href = '?room=' + room;
    }

    // Scroll chat to bottom
    const body = document.getElementById('chat-body');
    if (body) body.scrollTop = body.scrollHeight;
</script>

<?php require ROOT_PATH . '/app/Views/employee/layout/footer.php'; ?>
