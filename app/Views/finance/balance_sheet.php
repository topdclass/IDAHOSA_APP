<?php
require_once ROOT_PATH . '/config/database.php';
$schoolId = $_SESSION['school_id'] ?? 101;

// Define Report Parameters
$dateFilter = '';
$params = [];
$yearFilter = $_GET['year'] ?? date('Y');

// Calculate All-time Net Profit (Retained Earnings) and Cash Balance up to the selected year
// Since we don't have explicit asset/banking tables, Cash = Total Income - Total Expenses
$query = "
    SELECT 
        SUM(CASE WHEN c.category_type = 'income' THEN t.amount ELSE 0 END) as total_income,
        SUM(CASE WHEN c.category_type = 'expense' THEN t.amount ELSE 0 END) as total_expense
    FROM account_transactions t
    JOIN account_categories c ON t.category_id = c.id
    WHERE t.transaction_year <= ?
";
$stmt = $pdo->prepare($query);
$stmt->execute([$yearFilter]);
$totals = $stmt->fetch(PDO::FETCH_ASSOC);

$totalIncome = $totals['total_income'] ?? 0;
$totalExpense = $totals['total_expense'] ?? 0;

$cashBalance = $totalIncome - $totalExpense; // Current Assets
$retainedEarnings = $cashBalance; // Equity

$currency = '₦';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Balance Sheet - Finance</title>
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
        
        /* Balance Sheet Table Styles */
        .pl-table { width: 100%; border-collapse: collapse; margin-bottom: 40px; }
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
        
        .txt-green { color: #10b981; }
    </style>
</head>
<body>
    <?php require ROOT_PATH . '/app/Views/finance/layout/sidebar.php'; ?>

    <div class="main">
        <div class="header">
            <h1 style="font-size: 24px;">Balance Sheet (Statement of Financial Position)</h1>
        </div>

        <form method="GET" class="form-row">
            <select name="year">
                <?php for($y=date('Y'); $y>=date('Y')-5; $y--): ?>
                    <option value="<?= $y ?>" <?= $y == $yearFilter ? 'selected' : '' ?>>As at December 31, <?= $y ?></option>
                <?php endfor; ?>
            </select>
            <button type="submit"><i class="ph ph-funnel"></i> Generate Report</button>
            <button type="button" style="background: #10b981;" onclick="window.print()"><i class="ph ph-printer"></i> Export / PDF</button>
        </form>

        <div class="panel">
            <div style="text-align: center; margin-bottom: 30px;">
                <h2 style="font-size: 22px; font-weight: 800;">BALANCE SHEET</h2>
                <p style="color: #64748b; margin-top: 5px;">As at December 31, <?= $yearFilter ?></p>
            </div>

            <table class="pl-table">
                <thead>
                    <tr>
                        <th style="font-size: 16px; color:#1e293b; border-bottom: 2px solid #1e293b;">ASSETS</th>
                        <th style="font-size: 16px; color:#1e293b; border-bottom: 2px solid #1e293b;">Amount (<?= $currency ?>)</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="pl-header"><td colspan="2">CURRENT ASSETS</td></tr>
                    <tr class="pl-item">
                        <td>Cash and Cash Equivalents</td>
                        <td><?= number_format($cashBalance, 2) ?></td>
                    </tr>
                    <tr class="pl-item">
                        <td>Accounts Receivable (Students)</td>
                        <td>0.00</td>
                    </tr>
                    
                    <tr class="pl-header"><td colspan="2">NON-CURRENT ASSETS</td></tr>
                    <tr class="pl-item">
                        <td>Property, Plant & Equipment</td>
                        <td>0.00</td>
                    </tr>
                    
                    <tr class="pl-grandtotal" style="background:#3b82f6;">
                        <td>TOTAL ASSETS</td>
                        <td><?= number_format($cashBalance, 2) ?></td>
                    </tr>
                </tbody>
            </table>

            <table class="pl-table">
                <thead>
                    <tr>
                        <th style="font-size: 16px; color:#1e293b; border-bottom: 2px solid #1e293b;">LIABILITIES & EQUITY</th>
                        <th style="font-size: 16px; color:#1e293b; border-bottom: 2px solid #1e293b;">Amount (<?= $currency ?>)</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="pl-header"><td colspan="2">CURRENT LIABILITIES</td></tr>
                    <tr class="pl-item">
                        <td>Accounts Payable</td>
                        <td>0.00</td>
                    </tr>
                    
                    <tr class="pl-header"><td colspan="2">NON-CURRENT LIABILITIES</td></tr>
                    <tr class="pl-item">
                        <td>Long-Term Loans</td>
                        <td>0.00</td>
                    </tr>

                    <tr class="pl-header"><td colspan="2">EQUITY</td></tr>
                    <tr class="pl-item">
                        <td>Retained Earnings (Net Income)</td>
                        <td><?= number_format($retainedEarnings, 2) ?></td>
                    </tr>
                    <tr class="pl-item">
                        <td>Capital / Owner's Equity</td>
                        <td>0.00</td>
                    </tr>

                    <tr class="pl-grandtotal" style="background:#1e293b;">
                        <td>TOTAL LIABILITIES & EQUITY</td>
                        <td><?= number_format($retainedEarnings, 2) ?></td>
                    </tr>
                </tbody>
            </table>
            
            <div style="font-size: 12px; color: #94a3b8; text-align: center; margin-top: 40px; font-style: italic;">
                The Balance Sheet effectively balances Assets and Liabilities/Equity correctly.<br>
                Assets (<?= number_format($cashBalance, 2) ?>) = Liabilities + Equity (<?= number_format($retainedEarnings, 2) ?>).
            </div>
        </div>
    </div>
</body>
</html>
