<div class="sidebar">
    <div style="text-align:center; margin-bottom:15px;">
        <?php if (!empty($globalSchoolLogo)): ?>
            <img src="<?= WEB_ROOT . '/public' . $globalSchoolLogo ?>" alt="Logo" style="width:50px; height:50px; border-radius:50%; object-fit:contain; border:2px solid rgba(255,255,255,0.2);">
        <?php endif; ?>
    </div>
    <h2 style="font-size: 16px; margin-bottom: 40px; font-weight: 800; color: white; text-align:center; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="<?= htmlspecialchars($globalSchoolName ?? 'Rosmon SMS Finance') ?>">
        <?= strtoupper(htmlspecialchars($globalSchoolName ?? 'Rosmon Finance')) ?>
    </h2>
    
    <?php
    $currentUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    function isActive($path) {
        global $currentUri;
        return (strpos($currentUri, $path) !== false) ? 'active' : '';
    }
    ?>

    <a href="<?= WEB_ROOT ?>/finance/dashboard" class="nav-link <?= isActive('dashboard') ?>"><i class="ph ph-house"></i> Overview</a>
    <a href="<?= WEB_ROOT ?>/finance/income" class="nav-link <?= isActive('income') ?>"><i class="ph ph-trend-up"></i> Income Registry</a>
    <a href="<?= WEB_ROOT ?>/finance/expenses" class="nav-link <?= isActive('expenses') ?>"><i class="ph ph-receipt"></i> Expense Logs</a>
    <a href="<?= WEB_ROOT ?>/finance/debtors" class="nav-link <?= isActive('debtors') ?>"><i class="ph ph-users"></i> Student Balances</a>
    <a href="<?= WEB_ROOT ?>/finance/profit-loss" class="nav-link <?= isActive('profit-loss') ?>"><i class="ph ph-chart-pie"></i> Profit & Loss</a>
    <a href="<?= WEB_ROOT ?>/finance/balance-sheet" class="nav-link <?= isActive('balance-sheet') ?>"><i class="ph ph-bank"></i> Balance Sheet</a>
    
    <div style="margin-top: auto; padding-top: 20px;">
        <a href="<?= WEB_ROOT ?>/logout" class="nav-link" style="color: #fda4af;"><i class="ph ph-sign-out"></i> Logout</a>
    </div>
</div>
