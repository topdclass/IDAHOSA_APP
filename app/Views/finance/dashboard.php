<?php
require_once ROOT_PATH . '/config/database.php';

$roleName = "Finance Officer";
$userAbbr = "FO";

// 1. Fetch Real Stats
$statsQuery = "
    SELECT 
        SUM(CASE WHEN c.category_type = 'income' THEN t.amount ELSE 0 END) as total_income,
        SUM(CASE WHEN c.category_type = 'expense' THEN t.amount ELSE 0 END) as total_expense
    FROM account_transactions t
    JOIN account_categories c ON t.category_id = c.id
";
$stats = $pdo->query($statsQuery)->fetch(PDO::FETCH_ASSOC);
$totalIncome = $stats['total_income'] ?? 0;
$totalExpense = $stats['total_expense'] ?? 0;
$balance = $totalIncome - $totalExpense;

// 2. Fetch Monthly Trends for Chart
$trendQuery = "
    SELECT 
        transaction_month, 
        SUM(CASE WHEN c.category_type = 'income' THEN t.amount ELSE 0 END) as income,
        SUM(CASE WHEN c.category_type = 'expense' THEN t.amount ELSE 0 END) as expense
    FROM account_transactions t
    JOIN account_categories c ON t.category_id = c.id
    WHERE transaction_year = YEAR(CURRENT_DATE)
    GROUP BY transaction_month
    ORDER BY transaction_month ASC
";
$trends = $pdo->query($trendQuery)->fetchAll(PDO::FETCH_ASSOC);

$months = [];
$incomeData = [];
$expenseData = [];
for ($m = 1; $m <= 12; $m++) {
    $months[] = date('F', mktime(0, 0, 0, $m, 1));
    $found = false;
    foreach ($trends as $t) {
        if ($t['transaction_month'] == $m) {
            $incomeData[] = (float)$t['income'];
            $expenseData[] = (float)$t['expense'];
            $found = true;
            break;
        }
    }
    if (!$found) {
        $incomeData[] = 0;
        $expenseData[] = 0;
    }
}

// 3. Fetch Expense Breakdown by Category
$breakdownQuery = "
    SELECT c.category_name, SUM(t.amount) as total
    FROM account_transactions t
    JOIN account_categories c ON t.category_id = c.id
    WHERE c.category_type = 'expense' AND t.transaction_year = YEAR(CURRENT_DATE)
    GROUP BY c.id
    ORDER BY total DESC
";
$breakdown = $pdo->query($breakdownQuery)->fetchAll(PDO::FETCH_ASSOC);
$breakdownLabels = [];
$breakdownData = [];
foreach($breakdown as $b) {
    if ((float)$b['total'] > 0) {
        $breakdownLabels[] = $b['category_name'];
        $breakdownData[] = (float)$b['total'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finance Dashboard - Rosmon SMS</title>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&display=swap" rel="stylesheet">
    <style>
        :root { --primary: #0f172a; --secondary: #3b82f6; --bg: #f8fafc; --text: #0f172a; --border: #e2e8f0; }
        * { box-sizing: border-box; font-family: 'Outfit', sans-serif; }
        body { margin: 0; background: var(--bg); display: flex; color: var(--text); }
        .sidebar { width: 260px; height: 100vh; background: var(--primary); color: white; padding: 20px; position: fixed; }
        .main { flex: 1; margin-left: 260px; padding: 30px; }
        .nav-link { display: flex; align-items: center; gap: 12px; padding: 12px; color: #94a3b8; text-decoration: none; border-radius: 8px; margin-bottom: 5px; }
        .nav-link:hover, .nav-link.active { background: rgba(255,255,255,0.1); color: white; }
        .header { display: flex; justify-content: space-between; align-items:center; margin-bottom: 30px; }
        .card-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 30px; }
        .card { background: white; padding: 25px; border-radius: 20px; border: 1px solid var(--border); box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
        .chart-container { background: white; padding: 30px; border-radius: 20px; border: 1px solid var(--border); margin-top: 30px; }
        .btn-action { background: var(--secondary); color: white; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; }
    </style>
</head>
<body>
    <?php require ROOT_PATH . '/app/Views/finance/layout/sidebar.php'; ?>

    <div class="main">
        <div class="header">
            <div>
                <h1 style="font-size: 28px; margin: 0; font-weight: 800;">Financial Analytics, 👋</h1>
                <p style="color: #64748b; margin-top: 5px;">Officer Terminal | Active Session: <?= date('Y') ?></p>
            </div>
            <div style="display: flex; align-items: center; gap: 15px;">
                <a href="<?= WEB_ROOT ?>/finance/income" class="btn-action"><i class="ph ph-plus-circle"></i> New Entry</a>
                <div style="width: 45px; height: 45px; background: var(--primary); color: white; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-weight: 800;"><?= $userAbbr ?></div>
            </div>
        </div>

        <div class="card-grid">
            <div class="card">
                <div style="color: #10b981; font-size: 24px; margin-bottom: 15px;"><i class="ph ph-arrow-up-right"></i></div>
                <div style="color: #64748b; font-size: 14px; font-weight: 600;">TOTAL REVENUE</div>
                <div style="font-size: 32px; font-weight: 800; margin-top: 5px;">₦<?= number_format($totalIncome, 2) ?></div>
            </div>
            <div class="card">
                <div style="color: #ef4444; font-size: 24px; margin-bottom: 15px;"><i class="ph ph-arrow-down-right"></i></div>
                <div style="color: #64748b; font-size: 14px; font-weight: 600;">TOTAL EXPENDITURE</div>
                <div style="font-size: 32px; font-weight: 800; margin-top: 5px;">₦<?= number_format($totalExpense, 2) ?></div>
            </div>
            <div class="card" style="background: var(--primary); color: white; border: none;">
                <div style="color: #3b82f6; font-size: 24px; margin-bottom: 15px;"><i class="ph ph-wallet"></i></div>
                <div style="color: #94a3b8; font-size: 14px; font-weight: 600;">NET BALANCE</div>
                <div style="font-size: 32px; font-weight: 800; margin-top: 5px;">₦<?= number_format($balance, 2) ?></div>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 30px;">
            <div class="chart-container" style="margin-top: 0; max-height: 500px; overflow-y: auto;">
                <h3 style="margin-top: 0; margin-bottom: 25px; font-weight: 800; font-size: 18px;"><i class="ph ph-chart-bar" style="color:var(--secondary)"></i> Revenue vs Expenditure Trends</h3>
                <div style="height: 250px; margin-bottom: 30px;">
                    <canvas id="financeChart"></canvas>
                </div>
                
                <h4 style="font-size: 14px; font-weight: 800; margin-bottom: 15px; color: #64748b;">Monthly Summary Table</h4>
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                        <thead>
                            <tr style="text-align: left; background: #f8fafc;">
                                <th style="padding: 10px; border-bottom: 1px solid var(--border);">Month</th>
                                <th style="padding: 10px; border-bottom: 1px solid var(--border); text-align: right;">Revenue (₦)</th>
                                <th style="padding: 10px; border-bottom: 1px solid var(--border); text-align: right;">Expenditure (₦)</th>
                                <th style="padding: 10px; border-bottom: 1px solid var(--border); text-align: right;">Net</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php for($i=0; $i<12; $i++): 
                                $net = $incomeData[$i] - $expenseData[$i];
                            ?>
                            <tr style="border-bottom: 1px solid #f1f5f9;">
                                <td style="padding: 8px 10px; font-weight: 600;"><?= $months[$i] ?></td>
                                <td style="padding: 8px 10px; text-align: right; color: #10b981;">₦<?= number_format($incomeData[$i], 2) ?></td>
                                <td style="padding: 8px 10px; text-align: right; color: #ef4444;">₦<?= number_format($expenseData[$i], 2) ?></td>
                                <td style="padding: 8px 10px; text-align: right; font-weight: 700; color: <?= $net >= 0 ? '#10b981' : '#ef4444' ?>;">₦<?= number_format($net, 2) ?></td>
                            </tr>
                            <?php endfor; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div class="chart-container" style="margin-top: 0;">
                <h3 style="margin-top: 0; margin-bottom: 25px; font-weight: 800; font-size: 18px;"><i class="ph ph-chart-pie-slice" style="color:#f59e0b"></i> Expenses Breakdown</h3>
                <div style="position: relative; height: 260px; width: 100%;">
                    <canvas id="breakdownChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        const ctx = document.getElementById('financeChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?= json_encode($months) ?>,
                datasets: [
                    {
                        label: 'Gross Income',
                        data: <?= json_encode($incomeData) ?>,
                        backgroundColor: '#10b981',
                        borderRadius: 8,
                    },
                    {
                        label: 'Total Expenses',
                        data: <?= json_encode($expenseData) ?>,
                        backgroundColor: '#ef4444',
                        borderRadius: 8,
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom' } },
                scales: {
                    y: { beginAtZero: true, grid: { display: false } },
                    x: { grid: { display: false } }
                }
            }
        });

        const ctx2 = document.getElementById('breakdownChart').getContext('2d');
        new Chart(ctx2, {
            type: 'doughnut',
            data: {
                labels: <?= json_encode($breakdownLabels) ?>,
                datasets: [{
                    data: <?= json_encode($breakdownData) ?>,
                    backgroundColor: ['#ef4444', '#f59e0b', '#3b82f6', '#10b981', '#8b5cf6', '#ec4899', '#64748b', '#0ea5e9'],
                    borderWidth: 0,
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { 
                    legend: { position: 'bottom', labels: { boxWidth: 12, font: {family: 'Outfit', size: 11} } } 
                },
                cutout: '70%'
            }
        });
    </script>
</body>
</html>
