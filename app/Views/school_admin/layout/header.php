<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'Dashboard' ?> - <?= htmlspecialchars($globalSchoolName ?? 'Rosmon SMS') ?></title>
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Phosphor Icons -->
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <style>
        :root {
            --primary: #10178e;
            --bg-page: #f9fbfd;
            --white: #ffffff;
            --text-dark: #111827;
            --text-muted: #6b7280;
            --border: #f3f4f6;
            --orange-card: #fc8d47;
            --sidebar-width: 250px;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { background-color: var(--bg-page); color: var(--text-dark); display: flex; height: 100vh; overflow: hidden; }

        /* Generic Table Overrides for CRUDs */
        .crud-card { background: var(--white); border-radius: 12px; padding: 24px; box-shadow: 0 2px 10px rgba(0,0,0,0.02); border: 1px solid var(--border); }
        .crud-header { display:flex; justify-content:space-between; align-items:center; margin-bottom: 24px;}
        .crud-title { font-size: 16px; font-weight: 700; color: var(--text-dark); }
        .btn-primary { background: var(--primary); color: white; padding: 10px 16px; border-radius: 6px; border:none; font-weight: 600; font-size: 13px; cursor: pointer; text-decoration: none;}
        table.crud-table { width: 100%; border-collapse: collapse; text-align: left; }
        table.crud-table th { font-size: 12px; color: var(--text-muted); font-weight: 600; padding: 12px 16px; border-bottom: 1px solid var(--border); }
        table.crud-table td { font-size: 13px; color: var(--text-dark); font-weight: 500; padding: 16px; border-bottom: 1px solid var(--border); }
    </style>
    <!-- Sidebar Styling -->
    <style>
        .sidebar { width: var(--sidebar-width); background-color: var(--white); border-right: 1px solid var(--border); display: flex; flex-direction: column; overflow-y: auto; position: relative; }
        .brand-logo-container { padding: 24px 20px 0; }
        .logo-circle { width: 44px; height: 44px; background-color: var(--primary); color: var(--white); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 20px; font-weight: 800; font-family:serif; letter-spacing: -1px; box-shadow: 0 4px 10px rgba(16,23,142,0.3); margin-bottom: 20px; }
        .school-profile-block { background-color: #f3f4f6; border-radius: 12px; padding: 16px; margin: 0 20px 24px; display: flex; align-items: center; gap: 12px; }
        .profile-icon { width: 36px; height: 36px; background-color: var(--white); border-radius: 8px; color: var(--primary); display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 18px; font-family:serif; }
        .profile-texts h3 { font-size: 13px; font-weight: 700; color: var(--text-dark); line-height: 1.2; }
        .profile-texts p { font-size: 11px; font-weight: 500; color: var(--text-muted); margin-top: 4px; }
        .nav-list { padding: 0 16px; flex: 1; }
        .nav-item { display: flex; align-items: center; justify-content: space-between; padding: 12px 16px; border-radius: 8px; color: #4b5563; text-decoration: none; font-size: 13px; font-weight: 500; margin-bottom: 4px; cursor: pointer; transition: 0.2s; }
        .nav-item:hover { background-color: #f9fafb; color: var(--primary); }
        .nav-item.active { background-color: #f0f3ff; color: var(--primary); font-weight: 600; }
        .nav-left { display: flex; align-items: center; gap: 12px; }
        .nav-left i { font-size: 18px; }
        .nav-caret { font-size: 12px; color: #9ca3af; }
        .submenu { display: none; flex-direction: column; gap: 20px; padding: 20px 0 20px 48px; margin-bottom: 8px; border-top: 1px solid var(--border); }
        .submenu.open { display: flex; }
        .submenu-item { text-decoration: none; color: #64748b; font-size: 13px; font-weight: 500; transition: color 0.1s; letter-spacing: 0.2px; }
        .submenu-item:hover { color: var(--primary); }
        .sidebar::-webkit-scrollbar { width: 0; }
        .nav-link { text-decoration: none; color: inherit; flex: 1; display: flex; align-items: center; justify-content: space-between; }
    </style>
    <!-- Main Content Styling -->
    <style>
        .main-container { flex: 1; display: flex; flex-direction: column; overflow-y: auto; padding: 0 32px 32px; }
        .top-header { display: flex; justify-content: space-between; align-items: center; padding: 24px 0; background-color: var(--bg-page); position: sticky; top: 0; z-index: 10; }
        .greeting { font-size: 22px; font-weight: 700; color: var(--text-dark); }
        .header-actions { display: flex; align-items: center; gap: 16px; }
        .action-bell { font-size: 20px; color: #9ca3af; cursor: pointer; }
        .profile-avatar { width: 38px; height: 38px; background-color: var(--white); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 16px; font-weight: 800; font-family:serif; color: var(--primary); box-shadow: 0 2px 5px rgba(0,0,0,0.05); cursor:pointer; }
        
        /* Dropdown Styles */
        .header-actions { position: relative; }
        .profile-dropdown { 
            position: absolute; top: calc(100% + 10px); right: 0; 
            background: white; border-radius: 12px; min-width: 180px; 
            box-shadow: 0 10px 25px rgba(0,0,0,0.1); border: 1px solid var(--border); 
            display: none; flex-direction: column; overflow: hidden; z-index: 100;
        }
        .profile-dropdown.show { display: flex; }
        .dropdown-item { 
            display: flex; align-items: center; gap: 10px; padding: 12px 16px; 
            color: var(--text-dark); text-decoration: none; font-size: 13px; font-weight: 500; 
            transition: 0.2s; border-bottom: 1px solid #f9fafb;
        }
        .dropdown-item:last-child { border-bottom: none; }
        .dropdown-item:hover { background: #f9fafb; color: var(--primary); }
        .dropdown-item i { font-size: 18px; color: #9ca3af; }
    </style>
    <script>
        function toggleProfileDropdown(e) {
            e.stopPropagation();
            const dropdown = document.getElementById('profileDropdown');
            dropdown.classList.toggle('show');
        }
        
        window.onclick = function(event) {
            const dropdown = document.getElementById('profileDropdown');
            if (dropdown && dropdown.classList.contains('show')) {
                dropdown.classList.remove('show');
            }
        }
    </script>
</head>
<body>
