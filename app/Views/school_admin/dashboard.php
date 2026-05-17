<?php
require_once ROOT_PATH . '/config/database.php';
// $pdo          = school's PRIVATE database (for students, employees, classes, etc.)
// $supervisorPdo = supervisor/central database (for licenses, institution_profile)
// $instituteId  = current school ID from session

// ── Tenant-scoped helpers (for school's private DB queries) ──────────────
$iWhere    = $instituteId ? "AND institute_id = $instituteId" : '';
$iWhereAT  = $instituteId ? "AND institute_id = $instituteId" : '';
$iWhereInv = $instituteId ? "WHERE institute_id = $instituteId" : 'WHERE 1';
$iWhereInst = $instituteId ? "AND institute_id = $instituteId" : '';

// ── Fetch Core Stats from SCHOOL's private DB ────────────────────────────
try {
    $totalStudents        = $pdo->query("SELECT COUNT(*) FROM institute_students WHERE is_deleted = 0 $iWhere")->fetchColumn() ?: 0;
    $newStudentsThisMonth = $pdo->query("SELECT COUNT(*) FROM institute_students WHERE is_deleted = 0 $iWhere AND MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())")->fetchColumn() ?: 0;
    $totalEmployees        = $pdo->query("SELECT COUNT(*) FROM institute_employees WHERE is_deleted = 0 $iWhere")->fetchColumn() ?: 0;
    $newEmployeesThisMonth = $pdo->query("SELECT COUNT(*) FROM institute_employees WHERE is_deleted = 0 $iWhere AND MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())")->fetchColumn() ?: 0;
    $totalParents          = $pdo->query("SELECT COUNT(*) FROM institute_parents WHERE is_deleted = 0 $iWhere")->fetchColumn() ?: 0;
    $totalClasses          = $pdo->query("SELECT COUNT(*) FROM classes WHERE is_deleted = 0 $iWhere")->fetchColumn() ?: 0;
    $newClassesThisMonth   = $pdo->query("SELECT COUNT(*) FROM classes WHERE is_deleted = 0 $iWhere AND MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())")->fetchColumn() ?: 0;
    $totalSubjects         = $pdo->query("SELECT COUNT(*) FROM subjects")->fetchColumn() ?: 0;
    $newSubjectsThisMonth  = $pdo->query("SELECT COUNT(*) FROM subjects WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())")->fetchColumn() ?: 0;
    $totalRevenue          = $pdo->query("SELECT COALESCE(SUM(amount),0) FROM account_transactions WHERE type = 'income' AND is_deleted = 0 $iWhereAT")->fetchColumn() ?: 0;
    $monthlyRevenue        = $pdo->query("SELECT COALESCE(SUM(amount),0) FROM account_transactions WHERE type = 'income' AND is_deleted = 0 $iWhereAT AND MONTH(transaction_date) = MONTH(CURRENT_DATE()) AND YEAR(transaction_date) = YEAR(CURRENT_DATE())")->fetchColumn() ?: 0;
    $totalExpense          = $pdo->query("SELECT COALESCE(SUM(amount),0) FROM account_transactions WHERE type = 'expense' AND is_deleted = 0 $iWhereAT")->fetchColumn() ?: 0;
    $totalProfit           = $totalRevenue - $totalExpense;
    $monthlyExpense        = $pdo->query("SELECT COALESCE(SUM(amount),0) FROM account_transactions WHERE type = 'expense' AND is_deleted = 0 $iWhereAT AND MONTH(transaction_date) = MONTH(CURRENT_DATE()) AND YEAR(transaction_date) = YEAR(CURRENT_DATE())")->fetchColumn() ?: 0;
    $monthlyProfit         = $monthlyRevenue - $monthlyExpense;
    $attendanceToday       = $pdo->query("SELECT COUNT(*) FROM student_attendants WHERE attendant_date = CURRENT_DATE() AND is_deleted = 0 $iWhereInst")->fetchColumn() ?: 0;
} catch (PDOException $e) {
    // School DB may be empty on first login — set safe defaults
    $totalStudents = $newStudentsThisMonth = $totalEmployees = $newEmployeesThisMonth = 0;
    $totalParents = $totalClasses = $newClassesThisMonth = $totalSubjects = $newSubjectsThisMonth = 0;
    $totalRevenue = $monthlyRevenue = $totalExpense = $totalProfit = $monthlyExpense = $monthlyProfit = 0;
    $attendanceToday = 0;
}

// ── License Expiration — MUST query SUPERVISOR DB ────────────────────────
// licenses table lives in the supervisor database, NOT the school's private DB.
// user_id in licenses = institution_profile.id (the schoolId / instituteId)
$expirationDate = 'N/A';
$expirationFormatted = 'N/A';
$isExpired = false;
$daysRemaining = 0;
$licensePackage = 'Basic';

try {
    $licStmt = $supervisorPdo->prepare(
        "SELECT l.end_date, l.start_date, l.status, ip.package
         FROM licenses l
         LEFT JOIN institution_profile ip ON l.user_id = ip.id
         WHERE l.user_id = :schoolId AND l.is_deleted = 0
         ORDER BY l.end_date DESC LIMIT 1"
    );
    $licStmt->execute([':schoolId' => $instituteId]);
    $licenseData = $licStmt->fetch(PDO::FETCH_ASSOC);

    if ($licenseData && !empty($licenseData['end_date']) && $licenseData['end_date'] !== '0000-00-00') {
        $licensePackage      = $licenseData['package'] ?? 'Basic';
        $expirationDate      = $licenseData['end_date'];
        $expirationFormatted = date('d M Y', strtotime($expirationDate));
        $expireTimestamp     = strtotime($expirationDate . ' 23:59:59');
        $nowTimestamp        = time();
        $daysRemaining       = (int) ceil(($expireTimestamp - $nowTimestamp) / (60 * 60 * 24));
        if ($daysRemaining < 0) $daysRemaining = 0;
        if ($expireTimestamp < $nowTimestamp) $isExpired = true;
        if (($licenseData['status'] ?? 'Active') === 'Revoked') $isExpired = true;
    }
} catch (PDOException $e) {
    // supervisorPdo failed — extremely unlikely; safe defaults already set
}


// Formatting functions
if (!function_exists('formatNaira')) {
    function formatNaira($amount) {
        if ($amount >= 1000000) return '₦' . number_format($amount / 1000000, 2) . 'm';
        if ($amount >= 1000) return '₦' . number_format($amount / 1000, 1) . 'k';
        return '₦' . number_format($amount, 2);
    }
}

// 1. Weekly Attendance Data (Last 7 Days)
// 1. Weekly Attendance Data (scoped to this school)
$_iWhereSA = $instituteId ? "AND institute_id = $instituteId" : '';
$weeklyAtt = $pdo->query("
    SELECT attendant_date, COUNT(*) as count 
    FROM student_attendants 
    WHERE attendant_date >= DATE_SUB(CURRENT_DATE(), INTERVAL 6 DAY) AND is_deleted = 0 $_iWhereSA
    GROUP BY attendant_date ORDER BY attendant_date ASC
")->fetchAll(PDO::FETCH_ASSOC);

$attLabels = []; $attValues = [];
for($i=6; $i>=0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $attLabels[] = date('D', strtotime($d));
    $found = false;
    foreach($weeklyAtt as $row) {
        if($row['attendant_date'] == $d) { $attValues[] = (int)$row['count']; $found=true; break; }
    }
    if(!$found) $attValues[] = 0;
}

// 2. Fee Collection Trend (Last 6 Months)
$_iWhereAT2 = $instituteId ? "AND institute_id = $instituteId" : '';
$monthlyFees = $pdo->query("
    SELECT MONTH(transaction_date) as m, YEAR(transaction_date) as y, SUM(amount) as total 
    FROM account_transactions 
    WHERE type = 'income' AND is_deleted = 0 $_iWhereAT2
    AND transaction_date >= DATE_SUB(CURRENT_DATE(), INTERVAL 5 MONTH) 
    GROUP BY y, m ORDER BY y ASC, m ASC
")->fetchAll(PDO::FETCH_ASSOC);

$feeLabels = []; $feeValues = [];
for($i=5; $i>=0; $i--) {
    $m = date('n', strtotime("-$i months"));
    $y = date('Y', strtotime("-$i months"));
    $feeLabels[] = date('M', strtotime("-$y-$m-01"));
    $found = false;
    foreach($monthlyFees as $row) {
        if($row['m'] == $m && $row['y'] == $y) { $feeValues[] = (float)$row['total']; $found=true; break; }
    }
    if(!$found) $feeValues[] = 0;
}

// 3. Quick Stats (Invoices - scoped to school)
$_iWhereInv = $instituteId ? "WHERE institute_id = $instituteId" : 'WHERE 1';
try {
    $statuses = $pdo->query("SELECT status, COUNT(*) as count FROM student_invoices $_iWhereInv GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (PDOException $e) { $statuses = []; }
$totalInvoices = array_sum($statuses) ?: 1;
$paidCount = $statuses['paid'] ?? 0;
$partialCount = $statuses['partial'] ?? 0;
$unpaidCount = $statuses['unpaid'] ?? 0;

$pageTitle = 'School Admin Dashboard';
require ROOT_PATH . '/app/Views/school_admin/layout/header.php';
require ROOT_PATH . '/app/Views/school_admin/layout/sidebar.php';
?>
<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
    /* Dashboard Specific Overrides */
    .stats-row-1 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 20px; }
    .stat-card-main {
        background-color: var(--white); border-radius: 16px; padding: 20px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.02); display: flex; flex-direction: column;
        border: 1px solid var(--border);
    }
    .stat-card-main.orange { 
        background: linear-gradient(135deg, #fb923c, #f97316); color: var(--white); border: none;
        box-shadow: 0 10px 15px -3px rgba(249, 115, 22, 0.3);
    }
    .card-tophalf { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 24px; }
    .card-topleft h4 { font-size: 12px; font-weight: 500; margin-bottom: 8px; color: var(--text-muted); }
    .orange .card-topleft h4 { color: rgba(255,255,255,0.9); }
    .card-icon { font-size: 24px; color: var(--text-dark); }
    .orange .card-icon { color: var(--white); opacity: 0.9; }
    .card-value { font-size: 24px; font-weight: 700; text-align: right; }
    .card-bottom { display: flex; justify-content: space-between; font-size: 11px; font-weight: 500; color: #9ca3af; }
    .orange .card-bottom { color: rgba(255,255,255,0.8); }
    .card-bottom strong { font-weight: 700; color: var(--text-dark); }
    .orange .card-bottom strong { color: var(--white); }

    .stats-row-2 { display: grid; grid-template-columns: 1fr 2fr; gap: 20px; margin-bottom: 20px; }
    .attendance-card { background: var(--white); border-radius: 16px; padding: 20px; border: 1px solid var(--border); }
    .att-content { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 16px; }
    .att-faces { display: flex; }
    .att-faces .face { width: 32px; height: 32px; border-radius: 50%; background-color: #d1d5db; border: 2px solid white; margin-left: -8px; }
    .att-faces .face:first-child { background-color: #a78bfa; }
    .att-value { font-size: 28px; font-weight: 700; color: var(--text-dark); }
    .quick-stats-card { background: var(--white); border-radius: 16px; padding: 20px; border: 1px solid var(--border); }
    .qst-row { display: flex; justify-content: space-between; font-size: 12px; font-weight: 500; color: var(--text-muted); margin-bottom: 16px; }
    .qst-val { font-weight: 600; color: #10b981; }

    .stats-row-3 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
    .chart-card { background: var(--white); border-radius: 16px; padding: 20px; border: 1px solid var(--border); height: 260px;}
    .chart-placeholder { width: 100%; height: 160px; background-image: repeating-linear-gradient(to bottom, transparent, transparent 39px, #f3f4f6 39px, #f3f4f6 40px); display:flex; align-items:flex-end; padding-bottom:10px; }
    .chart-labels { display:flex; justify-content:space-between; width:100%; padding:0 10px; font-size:10px; color:#9ca3af; font-weight:600;}

    .row-4-split { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; }
    .welcome-banner { background: var(--white); border-radius: 16px; padding: 24px; position:relative; overflow:hidden; border: 1px solid var(--border); }
    .wb-img { width: 140px; height: 100%; position:absolute; right:0; top:0; background: url('https://images.pexels.com/photos/3184328/pexels-photo-3184328.jpeg?auto=compress&cs=tinysrgb&w=400') center/cover; opacity: 0.2; }
    .recent-activities { background: var(--white); border-radius: 16px; padding: 24px; border: 1px solid var(--border); }
    .activity-item { display: flex; gap: 16px; align-items: center; margin-bottom: 24px; }
    .act-icon { width: 40px; height: 40px; border-radius: 8px; background: linear-gradient(135deg, #10178e, #a78bfa); }
    .calendar-widget { background: var(--white); border-radius: 16px; padding: 24px; border: 1px solid var(--border); text-align:center; }
    .cal-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 10px 0; font-size: 11px; }
    .cal-grid span.active { border: 1px solid var(--text-dark); font-weight:700; border-radius:50%; width:24px; height:24px; display:flex; align-items:center; justify-content:center; margin:0 auto; }
</style>

<div class="main-container">
    <div class="top-header">
        <div class="greeting">Hi, Welcome back <?= htmlspecialchars($globalSchoolName ?? 'Rosmon International School') ?> 👋<br>
            <span style="font-size: 11px; font-weight: 500; color: <?= $isExpired ? '#ef4444' : '#6b7280' ?>;">
                <?php
                $badgeBg = '#10b981'; // green — safe
                if ($daysRemaining < 30) $badgeBg = '#f59e0b'; // orange — warning
                if ($daysRemaining < 14) $badgeBg = '#ef4444'; // red — urgent
                if ($isExpired)          $badgeBg = '#dc2626'; // dark red — expired
                $badgeLabel = $isExpired ? 'EXPIRED' : $daysRemaining . ' DAYS LEFT';
                $planLabel  = htmlspecialchars($licensePackage ?? 'Basic');
                ?>
                <i class="ph ph-certificate" style="margin-right:4px;"></i>
                <strong><?= $planLabel ?> Plan</strong>
                &nbsp;&middot;&nbsp;
                Expires: <strong style="color: <?= $isExpired ? '#ef4444' : 'var(--primary)' ?>;"><?= $expirationFormatted !== 'N/A' ? $expirationFormatted : $expirationDate ?></strong>
                <span id="licCountdown" style="margin-left:8px; background:<?= $badgeBg ?>; color:white; padding:2px 10px; border-radius:10px; font-weight:800; font-size:10px;"><?= $badgeLabel ?></span>
            </span>
        </div>
        <div class="header-actions">
            <i class="ph ph-bell action-bell"></i>
            <div class="profile-avatar" onclick="toggleProfileDropdown(event)">RI</div>
            <div class="profile-dropdown" id="profileDropdown">
                <a href="<?= WEB_ROOT ?>/school-admin/general-settings/account-settings" class="dropdown-item">
                    <i class="ph ph-user-circle"></i> Account Profile
                </a>
                <a href="<?= WEB_ROOT ?>/logout" class="dropdown-item" style="color:#ef4444;">
                    <i class="ph ph-sign-out" style="color:#ef4444;"></i> Logout
                </a>
            </div>
        </div>
    </div>

    <?php
    // Show onboarding banner if school has no data yet
    $isNewSchool = ($totalStudents == 0 && $totalEmployees == 0 && $totalClasses == 0);
    if ($isNewSchool):
    ?>
    <div style="background:linear-gradient(135deg,#1e40af,#7c3aed);border-radius:16px;padding:28px 32px;margin-bottom:24px;color:#fff;display:flex;align-items:center;justify-content:space-between;gap:24px;">
        <div>
            <div style="font-size:20px;font-weight:800;margin-bottom:6px;">&#127881; Welcome to RosmonSMS! Your school database is ready.</div>
            <div style="opacity:0.9;font-size:14px;line-height:1.7;">
                Your school has been set up. To quickly onboard all your staff, students, classes and subjects at once,
                download the CSV templates, fill them in, and upload everything in one go.
            </div>
            <div style="margin-top:14px;display:flex;gap:10px;flex-wrap:wrap;">
                <a href="<?= WEB_ROOT ?>/school-admin/bulk-upload/templates" style="padding:10px 20px;background:#fff;color:#1e40af;border-radius:8px;font-weight:700;text-decoration:none;font-size:14px;">
                    &#8595; Download CSV Templates
                </a>
                <a href="<?= WEB_ROOT ?>/school-admin/bulk-upload" style="padding:10px 20px;background:rgba(255,255,255,0.15);border:2px solid rgba(255,255,255,0.4);color:#fff;border-radius:8px;font-weight:700;text-decoration:none;font-size:14px;">
                    &#8679; Bulk Upload Data
                </a>
            </div>
        </div>
        <div style="font-size:72px;opacity:0.3;flex-shrink:0;">&#127968;</div>
    </div>
    <?php endif; ?>

    <!-- ROW 1 -->
    <div class="stats-row-1">
        <div class="stat-card-main">
            <div class="card-tophalf"><div class="card-topleft"><h4>Total Student</h4><div class="card-icon"><i class="ph ph-student"></i></div></div><div class="card-value"><?= $totalStudents ?></div></div>
            <div class="card-bottom"><span>This Month</span><strong><?= $newStudentsThisMonth ?></strong></div>
        </div>
        <div class="stat-card-main">
            <div class="card-tophalf"><div class="card-topleft"><h4>Total Employees</h4><div class="card-icon"><i class="ph ph-users"></i></div></div><div class="card-value"><?= $totalEmployees ?></div></div>
            <div class="card-bottom"><span>This Month</span><strong><?= $newEmployeesThisMonth ?></strong></div>
        </div>
        <div class="stat-card-main">
            <div class="card-tophalf"><div class="card-topleft"><h4>Total Classes</h4><div class="card-icon"><i class="ph ph-chalkboard"></i></div></div><div class="card-value"><?= $totalClasses ?></div></div>
            <div class="card-bottom"><span>This Month</span><strong><?= $newClassesThisMonth ?></strong></div>
        </div>
        <div class="stat-card-main">
            <div class="card-tophalf"><div class="card-topleft"><h4>Total Subjects</h4><div class="card-icon"><i class="ph ph-books"></i></div></div><div class="card-value"><?= $totalSubjects ?></div></div>
            <div class="card-bottom"><span>This Month</span><strong><?= $newSubjectsThisMonth ?></strong></div>
        </div>
        <div class="stat-card-main orange">
            <div class="card-tophalf"><div class="card-topleft"><h4>Total Revenue</h4><div class="card-icon"><i class="ph ph-shopping-cart"></i></div></div><div class="card-value"><?= formatNaira($totalRevenue) ?></div></div>
            <div class="card-bottom"><span>This Month</span><strong><?= formatNaira($monthlyRevenue) ?></strong></div>
        </div>
        <div class="stat-card-main">
            <div class="card-tophalf"><div class="card-topleft"><h4>Total Profit</h4><div class="card-icon"><i class="ph ph-shopping-cart" style="color:#fb923c;"></i></div></div><div class="card-value"><?= formatNaira($totalProfit) ?></div></div>
            <div class="card-bottom"><span>This Month</span><strong><?= formatNaira($monthlyProfit) ?></strong></div>
        </div>
    </div>

    <!-- ROW 2 -->
    <div class="stats-row-2">
        <div class="attendance-card">
            <h4>Attendance Today</h4>
            <div class="att-content"><div class="att-faces"><div class="face"></div><div class="face" style="background:#34d399;"></div></div><div class="att-value"><?= $attendanceToday ?></div></div>
            <div class="att-sub"><?= $attendanceToday ?> out of <?= $totalStudents ?> students present.</div>
        </div>
        <div class="quick-stats-card">
            <h4>Fee Payment Overview</h4>
            <div class="qst-row"><div>Fully Paid</div><div class="qst-val"><?= $paidCount ?> (<?= round(($paidCount/$totalInvoices)*100, 1) ?>%)</div></div>
            <div class="qst-row" style="color:#eab308;"><div>Partial Payment</div><div class="qst-val" style="color:#eab308;"><?= $partialCount ?> (<?= round(($partialCount/$totalInvoices)*100, 1) ?>%)</div></div>
            <div class="qst-row" style="color:#ef4444;"><div>Unpaid / Owing</div><div class="qst-val" style="color:#ef4444;"><?= $unpaidCount ?> (<?= round(($unpaidCount/$totalInvoices)*100, 1) ?>%)</div></div>
        </div>
    </div>

    <!-- ROW 3 -->
    <div class="stats-row-3">
        <div class="chart-card"><h4>Weekly Attendance</h4><canvas id="attendanceChart" style="margin-top:20px;"></canvas></div>
        <div class="chart-card"><h4>Fee Collection Trend</h4><canvas id="feeChart" style="margin-top:20px;"></canvas></div>
    </div>

    <!-- ROW 4 -->
    <div class="row-4-split">
        <div class="left-col">
            <div class="welcome-banner"><div class="wb-text"><h3>Admin Control Center</h3><p>Manage your institution seamlessly with Rosmon SMS.</p></div><div class="wb-img"></div></div>
            <div class="recent-activities">
                <h3>Recent Activities</h3>
                <div class="activity-item"><div class="act-icon"></div><div class="act-details"><h4>User Session Started</h4><p>Administrator session initialized from IP 127.0.0.1</p></div></div>
            </div>
        </div>
        <div class="right-col">
            <div class="calendar-widget">
                <div class="cal-grid">
                    <div style="font-weight:700; color:#6b7280;">S</div><div style="font-weight:700; color:#6b7280;">M</div><div style="font-weight:700; color:#6b7280;">T</div><div style="font-weight:700; color:#6b7280;">W</div><div style="font-weight:700; color:#6b7280;">T</div><div style="font-weight:700; color:#6b7280;">F</div><div style="font-weight:700; color:#6b7280;">S</div>
                    <?php for($i=1;$i<=31;$i++): ?>
                        <span class="<?= ($i == date('j')) ? 'active' : '' ?>"><?= $i ?></span>
                    <?php endfor; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Attendance Chart
    const attCtx = document.getElementById('attendanceChart').getContext('2d');
    new Chart(attCtx, {
        type: 'bar',
        data: {
            labels: <?= json_encode($attLabels) ?>,
            datasets: [{
                label: 'Present Students',
                data: <?= json_encode($attValues) ?>,
                backgroundColor: 'rgba(16, 23, 142, 0.8)',
                borderRadius: 8
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, grid: { display: false } }, x: { grid: { display: false } } }
        }
    });

    // Fee Chart
    const feeCtx = document.getElementById('feeChart').getContext('2d');
    new Chart(feeCtx, {
        type: 'line',
        data: {
            labels: <?= json_encode($feeLabels) ?>,
            datasets: [{
                label: 'Collection',
                data: <?= json_encode($feeValues) ?>,
                borderColor: '#fb923c',
                borderWidth: 3,
                tension: 0.4,
                fill: true,
                backgroundColor: 'rgba(251, 146, 60, 0.1)'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, grid: { color: '#f3f4f6' } }, x: { grid: { display: false } } }
        }
    });
</script>

<?php if ($isExpired): ?>
<div class="modal-overlay active">
    <div class="expire-modal">
        <div class="modal-icon-container"><i class="ph ph-warning-circle"></i></div>
        <h2 class="modal-title">Access Restricted</h2>
        <p class="modal-message">Sorry, your subscription has expired. <br>Renew your licenses to continue enjoying the service. <br>Thank you!</p>
        <a href="#" class="btn-renew">Renew Subscription Now</a>
    </div>
</div>
<?php endif; ?>

<?php require ROOT_PATH . '/app/Views/school_admin/layout/footer.php'; ?>
