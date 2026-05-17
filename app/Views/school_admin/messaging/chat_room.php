<?php
// Central Communication Hub Logic

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

    // Auto Migration: Communication Infrastructure
    $pdo->exec("CREATE TABLE IF NOT EXISTS chat_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sender_id INT NOT NULL,
        receiver_id INT DEFAULT NULL, -- NULL = Institute Group
        message_text TEXT NOT NULL,
        is_read BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $message_status = '';

    // Handle Group Post
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_msg'])) {
        $text = $_POST['msg_text'] ?? '';
        $sender = $_SESSION['user_id'] ?? 1; // Fallback for admin

        if (!empty($text)) {
            $stmt = $pdo->prepare("INSERT INTO chat_messages (sender_id, message_text) VALUES (?, ?)");
            $stmt->execute([$sender, $text]);
            header("Location: " . $_SERVER['REQUEST_URI']); exit;
        }
    }

    // Fetch Group Messages
    $messages = $pdo->query("SELECT m.*, COALESCE(u.full_name, 'Unknown') as full_name, COALESCE(u.role, 'User') as role 
                              FROM chat_messages m 
                              LEFT JOIN users u ON m.sender_id = u.id 
                              WHERE m.receiver_id IS NULL 
                              ORDER BY m.created_at ASC LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Communication Error: " . $e->getMessage());
}

$pageTitle = 'Institutional Chat - Rosmon SMS';
require ROOT_PATH . '/app/Views/school_admin/layout/header.php';
require ROOT_PATH . '/app/Views/school_admin/layout/sidebar.php';
?>

<div class="main-container">
    <div class="top-header">
        <div class="greeting">Communication / <span style="color:var(--primary)">Institute Presence Room</span></div>
    </div>

    <div class="crud-card" style="height: 650px; display:flex; flex-direction:column; padding:0; overflow:hidden;">
        
        <!-- Chat Header -->
        <div style="padding:20px; border-bottom:1px solid #f3f4f6; display:flex; justify-content:space-between; align-items:center; background:#fafafa;">
            <div style="display:flex; align-items:center; gap:12px;">
                <div style="background:var(--primary); width:44px; height:44px; border-radius:50%; display:flex; align-items:center; justify-content:center; color:#fff;">
                    <i class="ph ph-users-three" style="font-size:24px;"></i>
                </div>
                <div>
                    <h3 style="margin:0; font-size:16px;">Global Staff Room</h3>
                    <div style="font-size:11px; color:#059669; font-weight:700;"><span style="display:inline-block; width:8px; height:8px; background:#10b981; border-radius:50%; margin-right:5px;"></span> Collaborative Environment</div>
                </div>
            </div>
            <div style="display:flex; gap:15px; color:#9ca3af;">
                <i class="ph ph-phone" style="font-size:20px; cursor:pointer;"></i>
                <i class="ph ph-video-camera" style="font-size:20px; cursor:pointer;"></i>
                <i class="ph ph-dots-three-outline-vertical" style="font-size:20px; cursor:pointer;"></i>
            </div>
        </div>

        <!-- Chat Body -->
        <div id="chat-window" style="flex:1; overflow-y:auto; padding:24px; background:#fdfdfd; display:flex; flex-direction:column; gap:20px;">
            <?php if (empty($messages)): ?>
                <div style="text-align:center; padding:100px; color:#9ca3af;">
                    <i class="ph ph-chat-centered-dots" style="font-size:48px;"></i>
                    <p style="margin-top:10px;">The institutional room is empty. Start the conversation!</p>
                </div>
            <?php else: ?>
                <?php foreach($messages as $m): 
                    $isAdmin = ($m['sender_id'] == ($_SESSION['user_id'] ?? 1));
                ?>
                    <div style="display:flex; <?= $isAdmin ? 'flex-direction:row-reverse' : '' ?>; align-items:flex-end; gap:12px;">
                        <div style="width:32px; height:32px; border-radius:10px; background:<?= $isAdmin ? 'var(--primary-light)' : '#f3f4f6' ?>; display:flex; align-items:center; justify-content:center; font-weight:800; font-size:12px; color:<?= $isAdmin ? 'var(--primary)' : '#6b7280' ?>;">
                            <?= substr($m['full_name'], 0, 1) ?>
                        </div>
                        <div style="max-width: 70%;">
                            <div style="font-size:10px; font-weight:800; color:var(--text-muted); margin-bottom:4px; margin-left:<?= $isAdmin ? '0' : '4px' ?>; margin-right:<?= $isAdmin ? '4px' : '0' ?>; text-align: <?= $isAdmin ? 'right' : 'left' ?>;">
                                <?= htmlspecialchars((string)($m['full_name'] ?? '')) ?> <span style="font-weight:500; opacity:0.6;">(<?= strtoupper($m['role']) ?>)</span>
                            </div>
                            <div style="padding:12px 18px; border-radius:<?= $isAdmin ? '20px 20px 0 20px' : '20px 20px 20px 0' ?>; background:<?= $isAdmin ? 'var(--primary)' : '#fff' ?>; color:<?= $isAdmin ? '#fff' : '#1f2937' ?>; box-shadow: 0 4px 12px rgba(0,0,0,0.03); border: <?= $isAdmin ? 'none' : '1px solid #f1f1f1' ?>; font-size:14px; line-height:1.5;">
                                <?= htmlspecialchars((string)($m['message_text'] ?? '')) ?>
                            </div>
                            <div style="font-size:9px; color:#9ca3af; margin-top:5px; text-align: <?= $isAdmin ? 'right' : 'left' ?>;"><?= date('h:i A', strtotime($m['created_at'])) ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Chat Input -->
        <div style="padding:20px; background:#fff; border-top:1px solid #f3f4f6;">
            <form method="POST" style="display:flex; gap:15px; align-items:center;">
                <input type="hidden" name="send_msg" value="1">
                <button type="button" style="background:none; border:none; color:#9ca3af; cursor:pointer;"><i class="ph ph-sparkle" style="font-size:24px;"></i></button>
                <div style="flex:1; position:relative;">
                    <input type="text" name="msg_text" autocomplete="off" placeholder="Share updates with the presence room..." style="width:100%; padding:14px 20px; border:1px solid #e5e7eb; border-radius:30px; outline:none; font-size:14px; background:#f9fafb;">
                </div>
                <button type="submit" style="width:48px; height:48px; border-radius:50%; background:var(--primary); color:#fff; border:none; cursor:pointer; display:flex; align-items:center; justify-content:center; box-shadow: 0 4px 10px rgba(79, 70, 229, 0.3);">
                    <i class="ph ph-paper-plane-right-bold" style="font-size:20px;"></i>
                </button>
            </form>
        </div>
    </div>
</div>

<script>
    // Keep chat scrolled to bottom
    const cw = document.getElementById('chat-window');
    cw.scrollTop = cw.scrollHeight;
</script>

<?php require ROOT_PATH . '/app/Views/school_admin/layout/footer.php'; ?>
