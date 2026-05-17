<?php
require_once ROOT_PATH . '/config/database.php';
$schoolId = $_SESSION['school_id'] ?? 101;

// Define Report Parameters
$dateFilter = '';
$params = [];
$yearFilter = $_GET['year'] ?? date('Y');
$monthFilter = $_GET['month'] ?? '';

if ($monthFilter) {
    $dateFilter = "AND transaction_month = ? AND transaction_year = ?";
    $params = [$monthFilter, $yearFilter];
} else {
    $dateFilter = "AND transaction_year = ?";
    $params = [$yearFilter];
}

// Fetch grouped balances
$query = "
    SELECT c.category_name, c.category_type, SUM(t.amount) as total
    FROM account_transactions t
    JOIN account_categories c ON t.category_id = c.id
    WHERE 1=1 $dateFilter
    GROUP BY c.id, c.category_name, c.category_type
";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$ledgers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Macro Grouping logic based on the user's template
$groups = [
    'academic_income' => ['Tuition / School fees', 'Registration / Admission fees', 'Examination fees', 'PTA levies', 'ICT / E-learning fees', 'Laboratory fees', 'Library fees'],
    'service_income' => ['Transport fees', 'Boarding / Hostel fees', 'Feeding / Catering fees', 'After-school / lesson fees', 'Extra-curricular / club fees'],
    'other_income' => ['Uniform sales', 'Books & stationery sales', 'Event income', 'Donations & grants', 'Sponsorship income', 'Fines & penalties', 'Rental income'],
    
    'direct_costs' => ['Teaching staff salaries & wages', 'Part-time / lesson teachers pay', 'Teaching materials & instructional aids', 'Examination materials & printing', 'Feeding costs', 'Transport fuel & driver allowances', 'Student activity materials'],
    
    'operating_staff' => ['Non-teaching staff salaries', 'Allowances & bonuses', 'Pension contributions', 'Staff training & welfare'],
    'operating_utilities' => ['Electricity', 'Water', 'Internet & communication', 'Generator fuel & maintenance', 'Rent / lease', 'Repairs & maintenance'],
    'operating_admin' => ['Office supplies', 'Printing & stationery', 'Software & subscriptions', 'Audit & professional fees', 'Legal fees'],
    'operating_marketing' => ['Advertising & promotions', 'Open day / admission marketing', 'Website & social media management'],
    'operating_transport' => ['Vehicle maintenance', 'Vehicle insurance', 'Transport licensing'],
    
    'depreciation' => ['Buildings depreciation', 'Furniture & fittings depreciation', 'Computers & ICT equipment depreciation', 'Vehicles depreciation'],
    'finance_costs' => ['Bank charges', 'Loan interest'],
    'taxation' => ['Taxes', 'PAYE remittance', 'Pension remittance']
];

$totals = [
    'Total Revenue' => 0,
    'Cost of Services' => 0,
    'Gross Profit' => 0,
    'Operating Expenses' => 0,
    'EBITDA' => 0,
    'Depreciation & Finance' => 0,
    'Profit Before Tax' => 0,
    'Taxation' => 0,
    'Net Profit' => 0
];

$reportData = [];
// Distribute ledgers into groups
foreach ($ledgers as $l) {
    if ($l['category_type'] === 'income') {
        $totals['Total Revenue'] += $l['total'];
        $found = false;
        foreach (['academic_income', 'service_income', 'other_income'] as $k) {
            if (in_array($l['category_name'], $groups[$k])) { $reportData[$k][] = $l; $found=true; break; }
        }
        if (!$found) $reportData['other_income'][] = $l;
    } else {
        $found = false;
        if (in_array($l['category_name'], $groups['direct_costs'])) { 
            $reportData['direct_costs'][] = $l; $totals['Cost of Services'] += $l['total']; $found=true; 
        }
        elseif (in_array($l['category_name'], $groups['depreciation']) || in_array($l['category_name'], $groups['finance_costs'])) {
            $reportData['depreciation_finance'][] = $l; $totals['Depreciation & Finance'] += $l['total']; $found=true;
        }
        elseif (in_array($l['category_name'], $groups['taxation'])) {
            $reportData['taxation'][] = $l; $totals['Taxation'] += $l['total']; $found=true;
        }
        
        if (!$found) {
            // Must be an operating expense
            foreach (['operating_staff', 'operating_utilities', 'operating_admin', 'operating_marketing', 'operating_transport'] as $k) {
                if (in_array($l['category_name'], $groups[$k])) { $reportData['operating'][] = $l; $totals['Operating Expenses'] += $l['total']; $found=true; break; }
            }
            if (!$found) { $reportData['operating'][] = $l; $totals['Operating Expenses'] += $l['total']; }
        }
    }
}

// Calculate the final bottom lines
$totals['Gross Profit'] = $totals['Total Revenue'] - $totals['Cost of Services'];
$totals['EBITDA'] = $totals['Gross Profit'] - $totals['Operating Expenses'];
$totals['Profit Before Tax'] = $totals['EBITDA'] - $totals['Depreciation & Finance'];
$totals['Net Profit'] = $totals['Profit Before Tax'] - $totals['Taxation'];

$currency = '₦';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profit & Loss - Finance</title>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <style>
        :root { --primary: #0f172a; --bg: #f1f5f9; --text: #0f172a; --border: #cbd5e1; }
        * { box-sizing: border-box; font-family: 'Inter', sans-serif; margin: 0; padding: 0; }
        body { background: var(--bg); display: flex; color: var(--text); min-height: 100vh; }
        .sidebar { width: 260px; height: 100vh; background: var(--primary); color: white; padding: 20px; position: fixed; }
        .main { flex: 1; margin-left: 260px; padding: 30px; }
        .nav-link { display: flex; align-items: center; gap: 12px; padding: 12px; color: #94a3b8; text-decoration: none; border-radius: 8px; margin-bottom: 5px; }
        .nav-link:hover, .nav-link.active { background: rgba(255,255,255,0.1); color: white; }
        .header { display: flex; justify-content: space-between; align-items:center; margin-bottom: 30px; border-bottom: 1px solid var(--border); padding-bottom: 15px; }
        
        .panel { background: white; padding: 30px; border-radius: 12px; border: 1px solid var(--border); box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); margin-bottom: 30px; max-width: 900px; margin-left: auto; margin-right: auto; }
        
        /* P&L Table Styles */
        .pl-table { width: 100%; border-collapse: collapse; }
        .pl-table th { padding: 10px 0; border-bottom: 2px solid var(--border); font-size: 13px; text-transform: uppercase; color: #64748b; text-align: right; }
        .pl-table th:first-child { text-align: left; }
        
        .pl-header { background: #f8fafc; font-weight: 700; font-size: 15px; color: #1e293b; }
        .pl-header td { padding: 15px 10px; border-bottom: 1px solid var(--border); }
        
        .pl-item td { padding: 10px 10px 10px 30px; border-bottom: 1px dashed #e2e8f0; font-size: 14px; color: #475569; }
        .pl-item td:last-child { text-align: right; font-family: monospace; }
        
        .pl-subtotal { background: #f1f5f9; font-weight: 600; }
        .pl-subtotal td { padding: 12px 10px; border-top: 1px solid var(--border); text-align: right; }
        .pl-subtotal td:first-child { text-align: right; text-transform: uppercase; font-size: 12px; color: #64748b; }
        
        .pl-grandtotal { background: #1e293b; color: white; font-weight: 800; font-size: 16px; }
        .pl-grandtotal td { padding: 15px 10px; border: none; text-align: right; }
        .pl-grandtotal td:first-child { text-align: left; text-transform: uppercase; }

        .form-row { display: flex; gap: 10px; align-items: center; background: white; padding: 15px; border-radius: 8px; border: 1px solid var(--border); width: fit-content; margin: 0 auto 30px; }
        select, button { padding: 8px 12px; border-radius: 6px; border: 1px solid var(--border); outline: none; }
        button { background: #3b82f6; color: white; border: none; font-weight: 600; cursor: pointer; }
        button:hover { background: #2563eb; }
        
        /* Utility */
        .txt-green { color: #10b981; }
        .txt-red { color: #ef4444; }
    </style>
</head>
<body>
    <?php require ROOT_PATH . '/app/Views/finance/layout/sidebar.php'; ?>

    <div class="main">
        <div class="header">
            <h1 style="font-size: 24px;">Profit & Loss Statement</h1>
        </div>

        <form method="GET" class="form-row">
            <select name="month">
                <option value="">Full Year / Annual</option>
                <?php for($m=1; $m<=12; ++$m): ?>
                    <option value="<?= $m ?>" <?= $m == $monthFilter ? 'selected' : '' ?>><?= date('F', mktime(0,0,0,$m,1)) ?></option>
                <?php endfor; ?>
            </select>
            <select name="year">
                <?php for($y=date('Y'); $y>=date('Y')-5; $y--): ?>
                    <option value="<?= $y ?>" <?= $y == $yearFilter ? 'selected' : '' ?>><?= $y ?></option>
                <?php endfor; ?>
            </select>
            <button type="submit"><i class="ph ph-funnel"></i> Generate Report</button>
            <button type="button" style="background: #10b981;" onclick="window.print()"><i class="ph ph-printer"></i> Export / PDF</button>
        </form>

        <div class="panel">
            <div style="text-align: center; margin-bottom: 30px;">
                <h2 style="font-size: 22px; font-weight: 800;">PROFIT & LOSS STATEMENT</h2>
                <p style="color: #64748b; margin-top: 5px;">For the Period: <?= $monthFilter ? date('F', mktime(0,0,0,$monthFilter,1)) . " " . $yearFilter : "Annual " . $yearFilter ?></p>
            </div>

            <table class="pl-table">
                <thead>
                    <tr>
                        <th>Account</th>
                        <th>Amount (<?= $currency ?>)</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- REVENUE SECTION -->
                    <tr class="pl-header"><td colspan="2">A. TOTAL REVENUE</td></tr>
                    <?php 
                        $revCats = ['academic_income' => 'Academic Income', 'service_income' => 'Service & Ancillary Income', 'other_income' => 'Sales & Other Income'];
                        foreach($revCats as $key => $title): 
                            if (!empty($reportData[$key])):
                    ?>
                        <tr class="pl-item"><td colspan="2" style="font-weight:700; padding-left:15px;"><?= $title ?></td></tr>
                        <?php foreach($reportData[$key] as $item): ?>
                            <tr class="pl-item">
                                <td><?= htmlspecialchars($item['category_name']) ?></td>
                                <td><?= number_format($item['total'], 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; endforeach; ?>
                    <tr class="pl-subtotal">
                        <td>TOTAL REVENUE</td>
                        <td class="txt-green"><?= number_format($totals['Total Revenue'], 2) ?></td>
                    </tr>

                    <!-- COST OF SERVICES -->
                    <tr class="pl-header"><td colspan="2">B. COST OF SERVICES (DIRECT COSTS)</td></tr>
                    <?php if (!empty($reportData['direct_costs'])): foreach($reportData['direct_costs'] as $item): ?>
                        <tr class="pl-item">
                            <td><?= htmlspecialchars($item['category_name']) ?></td>
                            <td><?= number_format($item['total'], 2) ?></td>
                        </tr>
                    <?php endforeach; else: ?>
                        <tr class="pl-item"><td colspan="2" style="text-align:center; color:#94a3b8;">No direct costs recorded</td></tr>
                    <?php endif; ?>
                    <tr class="pl-subtotal">
                        <td>TOTAL COST OF SERVICES</td>
                        <td class="txt-red"><?= number_format($totals['Cost of Services'], 2) ?></td>
                    </tr>

                    <!-- GROSS PROFIT -->
                    <tr class="pl-grandtotal" style="background:#3b82f6;">
                        <td>C. GROSS PROFIT</td>
                        <td><?= number_format($totals['Gross Profit'], 2) ?></td>
                    </tr>

                    <!-- OPERATING EXPENSES -->
                    <tr class="pl-header"><td colspan="2">D. OPERATING EXPENSES</td></tr>
                    <?php if (!empty($reportData['operating'])): foreach($reportData['operating'] as $item): ?>
                        <tr class="pl-item">
                            <td><?= htmlspecialchars($item['category_name']) ?></td>
                            <td><?= number_format($item['total'], 2) ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    <tr class="pl-subtotal">
                        <td>TOTAL OPERATING EXPENSES</td>
                        <td class="txt-red"><?= number_format($totals['Operating Expenses'], 2) ?></td>
                    </tr>

                    <!-- EBITDA -->
                    <tr class="pl-grandtotal" style="background:#475569;">
                        <td>E. EBITDA</td>
                        <td><?= number_format($totals['EBITDA'], 2) ?></td>
                    </tr>

                    <!-- DEPRECIATION & FINANCE -->
                    <tr class="pl-header"><td colspan="2">F. DEPRECIATION & FINANCE COSTS</td></tr>
                    <?php if (!empty($reportData['depreciation_finance'])): foreach($reportData['depreciation_finance'] as $item): ?>
                        <tr class="pl-item">
                            <td><?= htmlspecialchars($item['category_name']) ?></td>
                            <td><?= number_format($item['total'], 2) ?></td>
                        </tr>
                    <?php endforeach; endif; ?>

                    <!-- PBT -->
                    <tr class="pl-grandtotal" style="background:#64748b;">
                        <td>G. PROFIT BEFORE TAX (PBT)</td>
                        <td><?= number_format($totals['Profit Before Tax'], 2) ?></td>
                    </tr>

                    <!-- TAXATION -->
                    <tr class="pl-header"><td colspan="2">H. TAXATION</td></tr>
                    <?php if (!empty($reportData['taxation'])): foreach($reportData['taxation'] as $item): ?>
                        <tr class="pl-item">
                            <td><?= htmlspecialchars($item['category_name']) ?></td>
                            <td><?= number_format($item['total'], 2) ?></td>
                        </tr>
                    <?php endforeach; endif; ?>

                    <!-- NET PROFIT -->
                    <tr class="pl-grandtotal" style="background:<?= $totals['Net Profit'] >= 0 ? '#166534' : '#991b1b' ?>;">
                        <td>I. NET PROFIT / (LOSS)</td>
                        <td><?= number_format($totals['Net Profit'], 2) ?></td>
                    </tr>

                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
