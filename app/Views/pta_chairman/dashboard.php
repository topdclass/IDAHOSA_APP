<?php
require_once ROOT_PATH . '/config/database.php';

// 1. Fetch Complaints (Mocking the structure since we'll assume a 'complaints' table or uses messages)
// For now, let's look for messages or assume a table.
try {
    $complaints = $pdo->query("SELECT * FROM chat_messages WHERE receiver_role = 'pta_chairman' ORDER BY created_at DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
} catch(Exception $e) {
    $complaints = []; // Fallback if table doesn't exist yet
}

$pendingComplaintsCount = count($complaints);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PTA Chairman Dashboard - Rosmon SMS</title>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&display=swap" rel="stylesheet">
    <style>
        :root { --primary: #334155; --secondary: #0ea5e9; --bg: #f1f5f9; --text: #1e293b; --border: #e2e8f0; }
        * { box-sizing: border-box; font-family: 'Outfit', sans-serif; }
        body { margin: 0; background: var(--bg); display: flex; color: var(--text); }
        .sidebar { width: 260px; height: 100vh; background: var(--primary); color: white; padding: 20px; position: fixed; }
        .main { flex: 1; margin-left: 260px; padding: 30px; }
        .nav-link { display: flex; align-items: center; gap: 12px; padding: 12px; color: #94a3b8; text-decoration: none; border-radius: 8px; margin-bottom: 5px; }
        .nav-link:hover, .nav-link.active { background: rgba(255,255,255,0.1); color: white; }
        .header { display: flex; justify-content: space-between; align-items:center; margin-bottom: 30px; }
        .card { background: white; padding: 25px; border-radius: 20px; border: 1px solid var(--border); box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
        .complaint-item { background: white; padding: 20px; border-radius: 12px; border: 1px solid var(--border); margin-bottom: 15px; display: flex; flex-direction: column; gap: 10px; }
        .btn-msg { background: var(--secondary); color: white; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; text-decoration: none; font-size: 14px; }
    </style>
</head>
<body>
    <div class="sidebar">
        <h2 style="font-size: 16px; margin-bottom: 40px; font-weight: 800; color: white; text-align:center;">PTA TERMINAL</h2>
        <a href="#" class="nav-link active"><i class="ph ph-chat-teardrop-dots"></i> Complaints</a>
        <a href="#" class="nav-link"><i class="ph ph-envelope-simple"></i> Message School Admin</a>
        <a href="#" class="nav-link"><i class="ph ph-users-three"></i> Parent Directory</a>
        <div style="margin-top: auto; padding-top: 20px;">
            <a href="<?= WEB_ROOT ?>/logout" class="nav-link" style="color: #fda4af;"><i class="ph ph-sign-out"></i> Logout</a>
        </div>
    </div>

    <div class="main">
        <div class="header">
            <div>
                <h1 style="font-size: 28px; margin: 0; font-weight: 800;">Parent-Teacher Association</h1>
                <p style="color: #64748b; margin-top: 5px;">Mediate and resolve parent concerns effectively.</p>
            </div>
            <a href="#" class="btn-msg"><i class="ph ph-paper-plane-tilt"></i> New Msg to Admin</a>
        </div>

        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 30px;">
            <div>
                <h3 style="font-weight: 800; margin-bottom: 20px;">Recent Parent Complaints</h3>
                <?php if (empty($complaints)): ?>
                    <div class="card" style="text-align:center; padding: 50px; color: #94a3b8;">
                        <i class="ph ph-chat-circle-dots" style="font-size: 48px; opacity: 0.3; margin-bottom: 15px;"></i>
                        <p>No active complaints from parents found.</p>
                    </div>
                <?php else: ?>
                    <?php foreach($complaints as $c): ?>
                    <div class="complaint-item">
                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <span style="font-weight: 800; font-size: 15px;">Parent: <?= htmlspecialchars($c['sender_name'] ?? 'Anonymous') ?></span>
                            <span style="font-size: 11px; color: #94a3b8; font-weight: 600;"><?= date('d M, H:i', strtotime($c['created_at'])) ?></span>
                        </div>
                        <p style="font-size: 14px; color: #475569; line-height: 1.5;"><?= htmlspecialchars($c['message']) ?></p>
                        <div style="display:flex; gap:10px; margin-top: 5px;">
                            <button class="btn-msg" style="padding: 6px 12px; font-size: 12px; background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0;"><i class="ph ph-arrow-bend-up-left"></i> Reply Parent</button>
                            <button class="btn-msg" style="padding: 6px 12px; font-size: 12px;"><i class="ph ph-megaphone"></i> Escalate to Admin</button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div>
                <div class="card" style="margin-bottom: 20px;">
                    <h4 style="margin-top: 0; font-weight: 800;">Quick Actions</h4>
                    <p style="font-size: 12px; color: #64748b; margin-bottom: 15px;">Use these shortcuts for frequent tasks.</p>
                    <div style="display:flex; flex-direction:column; gap:10px;">
                        <a href="#" style="text-decoration:none; color: var(--secondary); font-size: 13px; font-weight: 700;">• View Resolution History</a>
                        <a href="#" style="text-decoration:none; color: var(--secondary); font-size: 13px; font-weight: 700;">• Broadast to Parents</a>
                        <a href="#" style="text-decoration:none; color: var(--secondary); font-size: 13px; font-weight: 700;">• Schedule PTA Meeting</a>
                    </div>
                </div>
                
                <div class="card">
                    <h4 style="margin-top: 0; font-weight: 800;">Admin Status</h4>
                    <div style="display:flex; align-items:center; gap:10px; margin-top: 15px;">
                        <div style="width:10px; height:10px; background:#10b981; border-radius:50%;"></div>
                        <span style="font-size: 13px; font-weight: 600;">School Admin Online</span>
                    </div>
                </div>
            <ctrl95></div>
        </div>
    </div>
</body>
</html>
