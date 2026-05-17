<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'Teacher Dashboard - ' . htmlspecialchars($globalSchoolName ?? 'Rosmon International School') ?></title>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root { 
            --primary: #4f46e5; 
            --primary-dark: #1e1b4b;
            --primary-light: #f5f3ff;
            --bg: #f9fafb; 
            --text: #1e293b; 
            --text-muted: #64748b; 
            --sidebar-bg: #ffffff;
            --sidebar-width: 260px;
        }
        * { box-sizing: border-box; font-family: 'Inter', 'Segoe UI', sans-serif; }
        body { margin: 0; background: var(--bg); display: flex; color: var(--text); overflow-x: hidden; }
        
        .sidebar { 
            width: var(--sidebar-width); 
            height: 100vh; 
            background: var(--sidebar-bg); 
            padding: 20px 10px; 
            position: fixed; 
            border-right: 1px dashed #e2e8f0;
            display: flex;
            flex-direction: column;
            overflow-y: auto;
            z-index: 1000;
        }
        
        /* Sidebar Logo */
        .sidebar-logo {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
        }
        .logo-circle {
            width: 42px;
            height: 42px;
            background: var(--primary-dark);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 18px;
        }

        /* Sidebar Profile Card */
        .sidebar-profile {
            background: #f4f4f5;
            border-radius: 10px;
            padding: 10px 12px;
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
        }
        .profile-avatar {
            width: 36px;
            height: 36px;
            background: #e4e4e7;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #a1a1aa;
            font-size: 20px;
        }
        .profile-info { display: flex; flex-direction: column; overflow: hidden; }
        .profile-name { font-weight: 700; font-size: 13px; color: #18181b; white-space: nowrap; text-overflow: ellipsis; overflow: hidden; }
        .profile-role { font-size: 11px; color: #71717a; margin-top:1px; }

        /* Navigation Links */
        .nav-wrapper { margin-bottom: 2px; }
        .nav-link { 
            display: flex; 
            align-items: center; 
            padding: 10px 12px; 
            color: #64748b; 
            text-decoration: none; 
            border-radius: 8px; 
            font-weight: 600; 
            font-size: 13px; 
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1); 
            cursor: pointer;
            user-select: none;
        }
        .nav-link:hover { background: #f8fafc; color: #1e293b; }
        .nav-link.active { 
            background: var(--primary-light); 
            color: var(--primary); 
            font-weight: 700;
        }
        .nav-link i.left-icon { font-size: 18px; margin-right: 12px; width:20px; text-align:center; opacity: 0.9;}
        .nav-link i.right-icon { font-size: 10px; margin-left: auto; transition: transform 0.3s ease; }
        .nav-wrapper.expanded .nav-link i.right-icon { transform: rotate(180deg); }
        
        /* Submenus */
        .submenu {
            display: none;
            overflow: hidden;
            margin-bottom: 5px;
        }
        .nav-wrapper.expanded .submenu { display: block; }
        .submenu a {
            display: block;
            color: #94a3b8;
            text-decoration: none;
            font-size: 11px;
            font-weight: 600;
            padding: 8px 12px 8px 45px;
            transition: all 0.2s;
            border-radius: 6px;
            margin: 1px 0;
        }
        .submenu a:hover {
            color: #1e293b;
            background: #f8fafc;
        }
        .submenu a.active {
            color: var(--primary);
            background: var(--primary-light);
            font-weight: 700;
        }

        /* Deactivated / Blur Submenu State */
        .submenu a.deactivated {
            opacity: 0.35;
            cursor: not-allowed;
            pointer-events: none;
            user-select: none;
        }

        /* Main Content Scaling */
        .main-content { 
            flex: 1; 
            margin-left: var(--sidebar-width); 
            padding: 30px; 
            transition: all 0.3s;
        }
        
        /* Floating Header Actions matching screenshot identically */
        .header-actions { 
            display: flex; 
            align-items: center; 
            gap: 12px; 
            position: absolute; 
            top: 25px; 
            right: 25px;
            z-index: 100;
        }
        .notif-btn {
            position: relative;
            color: #94a3b8;
            font-size: 22px;
            cursor: pointer;
            transition: color 0.2s;
        }
        .notif-btn:hover { color: var(--primary); }
        .notif-badge {
            position: absolute;
            top: -1px;
            right: -1px;
            background: #f43f5e;
            color: white;
            font-size: 9px;
            font-weight: 800;
            width: 15px;
            height: 15px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid var(--bg);
        }
        .h-avatar {
            width: 32px;
            height: 32px;
            background: #e2e8f0;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #64748b;
            font-weight: 700;
            font-size: 12px;
            border: 1px solid #cbd5e1;
        }

        /* Scrollbar styling */
        .sidebar::-webkit-scrollbar { width: 4px; }
        .sidebar::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 10px; }
    </style>
</head>
<body>
<?php
require_once ROOT_PATH . '/config/database.php';
$emp_id = $_SESSION['user_id'] ?? 0;
$emp_name = $_SESSION['username'] ?? 'test';
try {
    $empStmt = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
    $empStmt->execute([$emp_id]);
    $db_name = $empStmt->fetchColumn();
    if ($db_name) $emp_name = $db_name;
    if (strtolower($emp_name) == 'teacher') $emp_name = 'test';
} catch (Exception $e) {}

$uri = $_SERVER['REQUEST_URI'];

$menus = [
    ['title' => 'Dashboard', 'icon' => 'ph-chart-bar', 'url' => '/employee/dashboard'],
    ['title' => 'Classes/Subjects', 'icon' => 'ph-chalkboard', 'submenus' => [
        ['title' => 'My Assigned Subjects', 'url' => '/employee/classes', 'active' => true],
        ['title' => 'Academic Timetable', 'url' => '/employee/timetable', 'active' => true],
        ['title' => 'LMS Progress Tracker', 'url' => '/employee/classes', 'active' => true]
    ]],
    ['title' => 'Lesson Planning', 'icon' => 'ph-notebook', 'submenus' => [
        ['title' => 'Lesson Notes & Bank', 'url' => '/employee/lesson-notes', 'active' => true],
        ['title' => 'Strategic Lesson Plans', 'url' => '/employee/lesson-plans', 'active' => true]
    ]],
    ['title' => 'Students', 'icon' => 'ph-users', 'submenus' => [
        ['title' => 'Classes/Groups', 'url' => '/employee/classes', 'active' => true]
    ]],
    ['title' => 'Exams & Grading', 'icon' => 'ph-exam', 'submenus' => [
        ['title' => 'Record CA/Exam Scores', 'url' => '/employee/grading', 'active' => true],
        ['title' => 'Class Teacher Remarks', 'url' => '/employee/grading?tab=comments', 'active' => true],
        ['title' => 'Manage Mock CBT', 'url' => '/employee/mock-cbt', 'active' => true]
    ]],
    ['title' => 'Attendance', 'icon' => 'ph-calendar-check', 'submenus' => [
        ['title' => 'Mark Student Presence', 'url' => '/employee/attendance', 'active' => true]
    ]],
    ['title' => 'Messaging', 'icon' => 'ph-chat-circle-dots', 'submenus' => [
        ['title' => 'Institute Chat Room', 'url' => '/employee/chat-room', 'active' => true],
        ['title' => 'Direct Messaging', 'url' => '/employee/messages', 'active' => true]
    ]],
    ['title' => 'Remote Learning', 'icon' => 'ph-video-camera', 'submenus' => [
        ['title' => 'Institute Live Class', 'url' => '/employee/live_class', 'active' => true]
    ]],
    ['title' => 'Account Settings', 'icon' => 'ph-user-gear', 'submenus' => [
        ['title' => 'My Profile & My Files', 'url' => '/employee/profile', 'active' => true]
    ]]
];
?>
    <div class="sidebar">
        <div class="sidebar-logo">
            <?php if (!empty($globalSchoolLogo)): ?>
                <img src="<?= WEB_ROOT . $globalSchoolLogo ?>" alt="Logo" style="width:48px; height:48px; border-radius:50%; object-fit:cover; border:2px solid rgba(79, 70, 229, 0.2);">
            <?php else: ?>
                <div class="logo-circle" style="font-weight: 900;"><?= substr($globalSchoolName ?? 'R', 0, 1) ?></div>
            <?php endif; ?>
        </div>

        <div class="sidebar-profile">
            <div class="profile-avatar"><i class="ph-fill ph-user"></i></div>
            <div class="profile-info">
                <div class="profile-name" style="font-size: 10px; color: var(--primary); margin-bottom: 2px;"><?= htmlspecialchars($globalSchoolName ?? 'Rosmon International School') ?></div>
                <div class="profile-name" title="<?= htmlspecialchars($emp_name) ?>"><?= htmlspecialchars($emp_name) ?></div>
                <div class="profile-role"><?= htmlspecialchars($globalSchoolType ?? 'Institute') ?></div>
            </div>
        </div>
        
        <?php foreach ($menus as $m): 
            $isExpanded = false;
            $isParentActive = false;
            
            if (isset($m['submenus'])) {
                foreach ($m['submenus'] as $sm) {
                    if ($sm['url'] !== '#' && strpos($uri, $sm['url']) !== false) {
                        $isExpanded = true;
                        $isParentActive = true;
                    }
                }
            } else {
                if ($m['url'] !== '#' && strpos($uri, $m['url']) !== false) {
                    $isParentActive = true;
                }
            }
        ?>
            <div class="nav-wrapper <?= $isExpanded ? 'expanded' : '' ?>">
                <?php if (isset($m['submenus'])): ?>
                    <div class="nav-link <?= (!$isExpanded && $isParentActive) ? 'active' : '' ?>" onclick="toggleNav(this)">
                        <i class="ph <?= $m['icon'] ?> left-icon"></i>
                        <?= $m['title'] ?>
                        <i class="ph ph-caret-down right-icon"></i>
                    </div>
                    <div class="submenu">
                        <?php foreach ($m['submenus'] as $sm): 
                            $isSubActive = ($sm['url'] !== '#' && strpos($uri, $sm['url']) !== false);
                            $class = $isSubActive ? 'active' : '';
                            if (!$sm['active']) $class .= ' deactivated';
                            
                            $link = ($sm['url'] === '#') ? '#' : WEB_ROOT . $sm['url'];
                        ?>
                            <a href="<?= $link ?>" class="<?= $class ?>">
                                <?= $sm['title'] ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <a href="<?= WEB_ROOT . $m['url'] ?>" class="nav-link <?= $isParentActive ? 'active' : '' ?>">
                        <i class="ph <?= $m['icon'] ?> left-icon"></i>
                        <?= $m['title'] ?>
                    </a>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>

        <div style="margin-top:auto; padding-top:20px;">
            <a href="<?= WEB_ROOT ?>/logout" class="nav-link" style="color:#f43f5e;">
                <i class="ph ph-sign-out left-icon"></i> Logout
            </a>
        </div>
    </div>

    <script>
        function toggleNav(el) {
            el.parentElement.classList.toggle('expanded');
        }
    </script>
    
    <div class="main-content">
        <div class="header-actions">
            <div class="notif-btn">
                <i class="ph-fill ph-bell"></i>
                <div class="notif-badge">1</div>
            </div>
            <div class="h-avatar"><?= strtoupper(substr($emp_name, 0, 1)) ?></div>
        </div>
