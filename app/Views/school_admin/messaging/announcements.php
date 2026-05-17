<?php
// Announcements / Notice Board Module

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
    
    // Auto Migration: Announcements Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS announcements (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        content TEXT NOT NULL,
        audience ENUM('all','students','parents','employees','class') NOT NULL DEFAULT 'all',
        target_class_id INT DEFAULT NULL,
        priority ENUM('normal','important','urgent') NOT NULL DEFAULT 'normal',
        is_pinned BOOLEAN DEFAULT FALSE,
        attachment_url VARCHAR(500) DEFAULT NULL,
        published_by INT NOT NULL,
        is_active BOOLEAN DEFAULT TRUE,
        publish_date DATETIME DEFAULT CURRENT_TIMESTAMP,
        expiry_date DATE DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_audience (audience),
        INDEX idx_priority (priority)
    )");

    $current_user_id = $_SESSION['user_id'] ?? 1;
    $message = '';
    $msg_type = '';

    // Handle Create Announcement
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_announcement'])) {
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $audience = $_POST['audience'] ?? 'all';
        $priority = $_POST['priority'] ?? 'normal';
        $is_pinned = isset($_POST['is_pinned']) ? 1 : 0;
        $expiry = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null;

        if (!empty($title) && !empty($content)) {
            $stmt = $pdo->prepare("INSERT INTO announcements (title, content, audience, priority, is_pinned, published_by, expiry_date) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$title, $content, $audience, $priority, $is_pinned, $current_user_id, $expiry]);
            $message = "Announcement published successfully!";
            $msg_type = 'success';
            header("Location: " . $_SERVER['REQUEST_URI']);
            exit;
        } else {
            $message = "Title and content are required.";
            $msg_type = 'error';
        }
    }

    // Handle Delete
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_announcement'])) {
        $del_id = (int)$_POST['announcement_id'];
        $pdo->prepare("DELETE FROM announcements WHERE id = ?")->execute([$del_id]);
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
    }

    // Handle Toggle Pin
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_pin'])) {
        $pin_id = (int)$_POST['announcement_id'];
        $pdo->prepare("UPDATE announcements SET is_pinned = NOT is_pinned WHERE id = ?")->execute([$pin_id]);
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
    }

    // Fetch Stats
    $total_count = $pdo->query("SELECT COUNT(*) FROM announcements WHERE is_active = 1")->fetchColumn();
    $urgent_count = $pdo->query("SELECT COUNT(*) FROM announcements WHERE priority = 'urgent' AND is_active = 1")->fetchColumn();
    $pinned_count = $pdo->query("SELECT COUNT(*) FROM announcements WHERE is_pinned = 1 AND is_active = 1")->fetchColumn();

    // Fetch Filter
    $filter = $_GET['filter'] ?? 'all';
    $where = "WHERE a.is_active = 1";
    if ($filter === 'pinned') $where .= " AND a.is_pinned = 1";
    if ($filter === 'urgent') $where .= " AND a.priority = 'urgent'";
    if ($filter === 'important') $where .= " AND a.priority = 'important'";

    // Fetch Announcements
    $announcements = $pdo->query("SELECT a.*, u.full_name 
                                  FROM announcements a 
                                  LEFT JOIN users u ON a.published_by = u.id 
                                  $where 
                                  ORDER BY a.is_pinned DESC, a.created_at DESC 
                                  LIMIT 30")->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Announcements Error: " . $e->getMessage());
}

$pageTitle = 'Announcements - Rosmon SMS';
require ROOT_PATH . '/app/Views/school_admin/layout/header.php';
require ROOT_PATH . '/app/Views/school_admin/layout/sidebar.php';
?>

<style>
    .announce-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px; }
    .stat-pill { background: var(--white); border: 1px solid var(--border); border-radius: 12px; padding: 20px; display: flex; align-items: center; gap: 16px; transition: 0.2s; }
    .stat-pill:hover { box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
    .stat-icon { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 22px; }
    .stat-value { font-size: 22px; font-weight: 800; color: var(--text-dark); }
    .stat-label { font-size: 11px; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; }

    .filter-tabs { display: flex; gap: 8px; margin-bottom: 20px; }
    .filter-tab { padding: 8px 18px; border-radius: 8px; font-size: 12px; font-weight: 600; cursor: pointer; border: 1px solid var(--border); background: var(--white); color: var(--text-muted); text-decoration: none; transition: 0.15s; }
    .filter-tab:hover, .filter-tab.active { background: var(--primary); color: #fff; border-color: var(--primary); }

    .announce-card { background: var(--white); border: 1px solid var(--border); border-radius: 12px; padding: 24px; margin-bottom: 16px; transition: 0.2s; position: relative; }
    .announce-card:hover { box-shadow: 0 4px 20px rgba(0,0,0,0.04); transform: translateY(-1px); }
    .announce-card.pinned { border-left: 4px solid #f59e0b; }
    .announce-card.urgent { border-left: 4px solid #ef4444; }
    .announce-card.important { border-left: 4px solid #3b82f6; }

    .announce-title { font-size: 16px; font-weight: 700; color: var(--text-dark); margin-bottom: 6px; }
    .announce-meta { display: flex; gap: 16px; font-size: 11px; color: var(--text-muted); margin-bottom: 12px; align-items: center; flex-wrap: wrap; }
    .announce-badge { padding: 3px 10px; border-radius: 20px; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.4px; }
    .announce-content { font-size: 14px; line-height: 1.7; color: #4b5563; }
    .announce-actions { display: flex; gap: 10px; margin-top: 16px; padding-top: 16px; border-top: 1px solid #f5f5f5; }
    .announce-action-btn { padding: 6px 14px; border-radius: 6px; font-size: 11px; font-weight: 700; cursor: pointer; border: 1px solid #e5e7eb; background: #fff; color: var(--text-muted); transition: 0.15s; display: flex; align-items: center; gap: 5px; }
    .announce-action-btn:hover { background: #f9fafb; color: var(--primary); }
    .announce-action-btn.danger:hover { background: #fef2f2; color: #ef4444; border-color: #fecaca; }

    /* Modal */
    .modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:1001; align-items:center; justify-content:center; }
    .modal-box { width: 560px; max-height: 90vh; overflow-y: auto; background: var(--white); border-radius: 16px; padding: 28px; box-shadow: 0 20px 60px rgba(0,0,0,0.15); }
    .modal-box label { display:block; font-size:11px; font-weight:700; color:var(--text-muted); margin-bottom:6px; text-transform:uppercase; letter-spacing:0.5px; }
    .modal-box input, .modal-box select, .modal-box textarea { width:100%; padding:11px 14px; border:1px solid #e5e7eb; border-radius:8px; font-size:13px; margin-bottom:16px; outline:none; font-family:'Inter',sans-serif; }
    .modal-box textarea { min-height: 120px; resize: vertical; }
    .modal-box input:focus, .modal-box select:focus, .modal-box textarea:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(16,23,142,0.08); }
</style>

<div class="main-container">
    <div class="top-header">
        <div class="greeting">Communication / <span style="color:var(--primary)">Announcements & Notices</span></div>
        <div class="header-actions">
            <button onclick="document.getElementById('announce-modal').style.display='flex'" class="btn-primary" style="display:flex; align-items:center; gap:6px;">
                <i class="ph ph-megaphone"></i> New Announcement
            </button>
        </div>
    </div>

    <!-- Stats -->
    <div class="announce-grid">
        <div class="stat-pill">
            <div class="stat-icon" style="background:#eff6ff; color:#3b82f6;"><i class="ph ph-megaphone-simple"></i></div>
            <div>
                <div class="stat-value"><?= $total_count ?></div>
                <div class="stat-label">Total Active</div>
            </div>
        </div>
        <div class="stat-pill">
            <div class="stat-icon" style="background:#fef2f2; color:#ef4444;"><i class="ph ph-warning-circle"></i></div>
            <div>
                <div class="stat-value"><?= $urgent_count ?></div>
                <div class="stat-label">Urgent Alerts</div>
            </div>
        </div>
        <div class="stat-pill">
            <div class="stat-icon" style="background:#fefce8; color:#f59e0b;"><i class="ph ph-push-pin"></i></div>
            <div>
                <div class="stat-value"><?= $pinned_count ?></div>
                <div class="stat-label">Pinned Notices</div>
            </div>
        </div>
        <div class="stat-pill">
            <div class="stat-icon" style="background:#f0fdf4; color:#22c55e;"><i class="ph ph-users"></i></div>
            <div>
                <div class="stat-value">All</div>
                <div class="stat-label">Audience Reach</div>
            </div>
        </div>
    </div>

    <!-- Filter Tabs -->
    <div class="filter-tabs">
        <a href="?filter=all" class="filter-tab <?= $filter === 'all' ? 'active' : '' ?>">All Notices</a>
        <a href="?filter=pinned" class="filter-tab <?= $filter === 'pinned' ? 'active' : '' ?>"><i class="ph ph-push-pin"></i> Pinned</a>
        <a href="?filter=urgent" class="filter-tab <?= $filter === 'urgent' ? 'active' : '' ?>"><i class="ph ph-warning"></i> Urgent</a>
        <a href="?filter=important" class="filter-tab <?= $filter === 'important' ? 'active' : '' ?>"><i class="ph ph-star"></i> Important</a>
    </div>

    <!-- Announcements List -->
    <?php if (empty($announcements)): ?>
        <div class="crud-card" style="text-align:center; padding:60px;">
            <i class="ph ph-megaphone" style="font-size:48px; color:#d1d5db;"></i>
            <p style="color:#9ca3af; margin-top:12px;">No announcements found. Create one to get started!</p>
        </div>
    <?php else: ?>
        <?php foreach($announcements as $a): 
            $priorityClass = $a['priority'] === 'urgent' ? 'urgent' : ($a['priority'] === 'important' ? 'important' : '');
            $pinnedClass = $a['is_pinned'] ? 'pinned' : '';
        ?>
            <div class="announce-card <?= $priorityClass ?> <?= $pinnedClass ?>">
                <?php if ($a['is_pinned']): ?>
                    <div style="position:absolute; top:12px; right:16px; font-size:10px; color:#f59e0b; font-weight:700;"><i class="ph ph-push-pin-fill"></i> PINNED</div>
                <?php endif; ?>

                <div class="announce-title"><?= htmlspecialchars((string)($a['title'] ?? '')) ?></div>
                <div class="announce-meta">
                    <span><i class="ph ph-user" style="margin-right:3px;"></i> <?= htmlspecialchars($a['full_name'] ?? 'Admin') ?></span>
                    <span><i class="ph ph-calendar" style="margin-right:3px;"></i> <?= date('M d, Y · h:i A', strtotime($a['created_at'])) ?></span>
                    <span class="announce-badge" style="
                        background: <?= $a['priority'] === 'urgent' ? '#fef2f2' : ($a['priority'] === 'important' ? '#eff6ff' : '#f0fdf4') ?>;
                        color: <?= $a['priority'] === 'urgent' ? '#dc2626' : ($a['priority'] === 'important' ? '#2563eb' : '#16a34a') ?>;
                    "><?= strtoupper($a['priority']) ?></span>
                    <span class="announce-badge" style="background:#f8f9fa; color:#6b7280;">
                        <i class="ph ph-users" style="margin-right:2px;"></i> <?= ucfirst($a['audience']) ?>
                    </span>
                    <?php if ($a['expiry_date']): ?>
                        <span style="color:#ef4444;"><i class="ph ph-clock" style="margin-right:3px;"></i> Expires <?= date('M d', strtotime($a['expiry_date'])) ?></span>
                    <?php endif; ?>
                </div>
                <div class="announce-content"><?= nl2br(htmlspecialchars((string)($a['content'] ?? ''))) ?></div>
                <div class="announce-actions">
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="toggle_pin" value="1">
                        <input type="hidden" name="announcement_id" value="<?= $a['id'] ?>">
                        <button type="submit" class="announce-action-btn">
                            <i class="ph ph-push-pin"></i> <?= $a['is_pinned'] ? 'Unpin' : 'Pin' ?>
                        </button>
                    </form>
                    <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this announcement?')">
                        <input type="hidden" name="delete_announcement" value="1">
                        <input type="hidden" name="announcement_id" value="<?= $a['id'] ?>">
                        <button type="submit" class="announce-action-btn danger">
                            <i class="ph ph-trash"></i> Delete
                        </button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Create Announcement Modal -->
<div class="modal-overlay" id="announce-modal">
    <div class="modal-box">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:24px;">
            <div>
                <h3 style="margin:0; font-size:18px; font-weight:800;">Create Announcement</h3>
                <p style="margin:4px 0 0; font-size:12px; color:var(--text-muted);">Broadcast a message to your institution</p>
            </div>
            <button onclick="document.getElementById('announce-modal').style.display='none'" style="background:none; border:none; cursor:pointer; color:#9ca3af;">
                <i class="ph ph-x" style="font-size:22px;"></i>
            </button>
        </div>
        <form method="POST">
            <input type="hidden" name="create_announcement" value="1">

            <label>ANNOUNCEMENT TITLE</label>
            <input type="text" name="title" placeholder="e.g. School Reopening Notice" required>

            <label>CONTENT / BODY</label>
            <textarea name="content" placeholder="Write the full announcement message here..." required></textarea>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px;">
                <div>
                    <label>AUDIENCE</label>
                    <select name="audience">
                        <option value="all">Everyone</option>
                        <option value="students">Students Only</option>
                        <option value="parents">Parents Only</option>
                        <option value="employees">Employees Only</option>
                    </select>
                </div>
                <div>
                    <label>PRIORITY</label>
                    <select name="priority">
                        <option value="normal">Normal</option>
                        <option value="important">Important</option>
                        <option value="urgent">Urgent</option>
                    </select>
                </div>
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px;">
                <div>
                    <label>EXPIRY DATE (OPTIONAL)</label>
                    <input type="date" name="expiry_date">
                </div>
                <div style="display:flex; align-items:center; gap:8px; padding-top:24px;">
                    <input type="checkbox" name="is_pinned" id="pin-check" style="width:auto; margin:0;">
                    <label for="pin-check" style="margin:0; font-size:13px; font-weight:600; color:var(--text-dark); cursor:pointer;">Pin to top of board</label>
                </div>
            </div>

            <button type="submit" class="btn-primary" style="width:100%; padding:14px; font-weight:800; margin-top:8px; display:flex; align-items:center; justify-content:center; gap:8px; font-size:14px;">
                <i class="ph ph-megaphone"></i> Publish Announcement
            </button>
        </form>
    </div>
</div>

<?php require ROOT_PATH . '/app/Views/school_admin/layout/footer.php'; ?>
