<?php
$roleName = "Parent";
$userAbbr = "PR";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parent Dashboard - <?= htmlspecialchars($globalSchoolName ?? 'Rosmon SMS') ?></title>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <style>
        :root { --primary: #059669; --bg: #f0fdf4; --text: #064e3b; --border: #d1fae5; }
        * { box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { margin: 0; background: var(--bg); display: flex; color: var(--text); }
        .sidebar { width: 260px; height: 100vh; background: var(--primary); color: white; padding: 20px; position: fixed; }
        .main { flex: 1; margin-left: 260px; padding: 30px; }
        .nav-link { display: flex; align-items: center; gap: 12px; padding: 12px; color: #d1fae5; text-decoration: none; border-radius: 8px; margin-bottom: 5px; }
        .nav-link:hover, .nav-link.active { background: rgba(255,255,255,0.1); color: white; }
        .header { display: flex; justify-content: space-between; align-items:center; margin-bottom: 30px; }
        .card-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; }
        .card { background: white; padding: 25px; border-radius: 16px; border: 1px solid var(--border); box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
        .countdown-banner { 
            background: linear-gradient(135deg, #1e293b, #0f172a); 
            color: white; padding: 20px; border-radius: 16px; margin-bottom: 30px;
            display: flex; justify-content: space-between; align-items: center;
        }
        .days-box { background: #ef4444; padding: 10px 20px; border-radius: 12px; font-weight: 800; font-size: 20px; }
        <?php if ($isExpired): ?>
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.8); backdrop-filter: blur(8px); z-index: 1000; display: flex; align-items: center; justify-content: center; }
        .modal { background: white; padding: 40px; border-radius: 24px; text-align: center; max-width: 450px; }
        <?php endif; ?>
    </style>
</head>
<body>
    <div class="sidebar">
        <div style="text-align:center; margin-bottom:10px;">
            <?php if (!empty($globalSchoolLogo)): ?>
                <img src="<?= WEB_ROOT . '/public' . $globalSchoolLogo ?>" alt="Logo" style="width:48px; height:48px; border-radius:50%; object-fit:contain; border:2px solid rgba(255,255,255,0.2);">
            <?php endif; ?>
        </div>
        <h2 style="font-size: 16px; margin-bottom: 30px; font-weight: 800; color: var(--primary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; text-align:center;" title="<?= htmlspecialchars($globalSchoolName ?? 'Rosmon International School') ?>"><?= strtoupper(htmlspecialchars($globalSchoolName ?? 'Rosmon International School')) ?></h2>
        <a href="dashboard" class="nav-link active"><i class="ph ph-house"></i> Home</a>
        <a href="my-children" class="nav-link"><i class="ph ph-baby"></i> My Children</a>
        <a href="visiting-card" class="nav-link"><i class="ph ph-identification-badge"></i> My Visiting Card</a>
        <a href="payments" class="nav-link"><i class="ph ph-receipt"></i> Payment history</a>
        <a href="performance" class="nav-link"><i class="ph ph-chart-line"></i> Performance</a>
        <a href="#" class="nav-link"><i class="ph ph-chat-circle"></i> Teacher Chat</a>
        <div style="margin-top: auto; padding-top: 20px;">
            <a href="<?= WEB_ROOT ?>/logout" class="nav-link" style="color: #fda4af;"><i class="ph ph-sign-out"></i> Logout</a>
        </div>
    </div>

    <div class="main">
        <div class="header">
            <div>
                <h1 style="font-size: 24px; margin: 0;">Hello, <?= $_SESSION['username'] ?>! 👋</h1>
                <p style="color: #64748b; margin-top: 5px;">Parental Control Panel</p>
            </div>
            <div style="display: flex; align-items: center; gap: 10px;">
                <div style="text-align: right;">
                    <div style="font-weight: 700; font-size: 14px;"><?= $roleName ?></div>
                    <div style="font-size: 12px; color: #64748b;">Guardian</div>
                </div>
                <div style="width: 40px; height: 40px; background: #e2e8f0; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 800;"><?= $userAbbr ?></div>
            </div>
        </div>

        <div class="countdown-banner">
            <div>
                <h3 style="margin:0; font-size: 18px;">Institutional License Lifecycle</h3>
                <p style="margin:5px 0 0; color: #94a3b8; font-size: 14px;">Institutional license valid until <?= date('M d, Y', strtotime($expirationDate)) ?></p>
            </div>
            <div style="text-align: right;">
                <div style="font-size: 12px; color: #94a3b8; margin-bottom: 5px; text-transform: uppercase;">Days Remaining</div>
                <div class="days-box"><?= $daysRemaining ?> Days</div>
            </div>
        </div>

        <div class="card-grid">
            <div class="card">
                <div style="color: #059669; font-size: 24px; margin-bottom: 10px;"><i class="ph ph-baby"></i></div>
                <div style="font-size: 28px; font-weight: 800;">3</div>
                <div style="color: #64748b; font-size: 14px;">Enrolled Children</div>
            </div>
            <div class="card">
                <div style="color: #ef4444; font-size: 24px; margin-bottom: 10px;"><i class="ph ph-wallet"></i></div>
                <div style="font-size: 28px; font-weight: 800;">₦14.2k</div>
                <div style="color: #64748b; font-size: 14px;">Outstanding Fees</div>
            </div>
            <div class="card">
                <div style="color: #3b82f6; font-size: 24px; margin-bottom: 10px;"><i class="ph ph-certificate"></i></div>
                <div style="font-size: 28px; font-weight: 800;">A+</div>
                <div style="color: #64748b; font-size: 14px;">Avg Grade</div>
            </div>
        </div>
    </div>

    <?php if ($isExpired): ?>
    <div class="modal-overlay">
        <div class="modal">
            <div style="font-size: 50px; color: #ef4444; margin-bottom: 20px;"><i class="ph ph-warning-circle"></i></div>
            <h2 style="margin:0; font-size: 24px;">School Service Suspended</h2>
            <p style="color: #64748b; margin: 15px 0 30px; line-height: 1.6;">Sorry, the school's digital portal subscription has expired. <br>Please contact the school office to resolve this.</p>
            <a href="/Rosmonsmsphp/" style="display: block; width: 100%; padding: 15px; background: var(--primary); color: white; text-decoration: none; border-radius: 12px; font-weight: 700;">Back to Login</a>
        </div>
    </div>
    <?php endif; ?>
</body>
</html>
