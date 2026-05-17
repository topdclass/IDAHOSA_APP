<?php
require_once ROOT_PATH . '/config/database.php';

// Handle Audit Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'approve_expense') {
        $stmt = $pdo->prepare("UPDATE account_transactions SET status = 'approved' WHERE id = ?");
        $stmt->execute([$_POST['id']]);
    } elseif ($_POST['action'] === 'reject_expense') {
        $stmt = $pdo->prepare("UPDATE account_transactions SET status = 'rejected' WHERE id = ?");
        $stmt->execute([$_POST['id']]);
    }
    header("Location: " . $_SERVER['REQUEST_URI']);
    exit;
}

// 1. Fetch Stats
$debtorsCount = $pdo->query("SELECT COUNT(DISTINCT student_id) FROM student_invoices WHERE total_amount > paid_amount")->fetchColumn() ?: 0;
$paidCount    = $pdo->query("SELECT COUNT(DISTINCT student_id) FROM student_invoices WHERE total_amount <= paid_amount")->fetchColumn() ?: 0;
$pendingExpenses = $pdo->query("SELECT COUNT(*) FROM account_transactions WHERE type = 'expense' AND status = 'pending'")->fetchColumn() ?: 0;

// 2. Fetch Recent Expenses
$expenses = $pdo->query("
    SELECT t.*, c.category_name 
    FROM account_transactions t
    JOIN account_categories c ON t.category_id = c.id
    WHERE t.type = 'expense'
    ORDER BY t.transaction_date DESC LIMIT 15
")->fetchAll(PDO::FETCH_ASSOC);

// 3. Fetch Staff Salaries
$staffSalaries = $pdo->query("
    SELECT u.full_name, e.employee_number, e.salary, s.status, s.payment_date
    FROM institute_employees e
    JOIN users u ON e.employee_id = u.id
    LEFT JOIN salaries s ON e.id = s.employee_id AND s.month = MONTH(CURRENT_DATE()) AND s.year = YEAR(CURRENT_DATE())
")->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audit Dashboard - Rosmon SMS</title>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
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
        .section-title { font-size: 18px; font-weight: 800; margin: 30px 0 20px 0; display: flex; align-items: center; gap: 10px; }
        .table-container { background: white; border-radius: 20px; border: 1px solid var(--border); overflow: hidden; margin-bottom: 30px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 15px 20px; text-align: left; border-bottom: 1px solid var(--border); }
        th { background: #f8fafc; font-size: 11px; text-transform: uppercase; color: #64748b; font-weight: 800; }
        .btn-sm { padding: 6px 12px; border-radius: 6px; font-weight: 700; font-size: 11px; cursor: pointer; text-decoration: none; border: none; }
        .btn-approve { background: #dcfce3; color: #166534; }
        .btn-print { background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; }
    </style>
</head>
<body>
    <div class="sidebar">
        <h2 style="font-size: 16px; margin-bottom: 40px; font-weight: 800; color: white; text-align:center;">ROSMON AUDIT</h2>
        <a href="#" class="nav-link active"><i class="ph ph-shield-check"></i> Audit Overview</a>
        <a href="#" class="nav-link"><i class="ph ph-receipt"></i> Expense Approval</a>
        <a href="#" class="nav-link"><i class="ph ph-money"></i> Salary Audit</a>
        <a href="#" class="nav-link"><i class="ph ph-users"></i> Fee Compliance</a>
        <div style="margin-top: auto; padding-top: 20px;">
            <a href="<?= WEB_ROOT ?>/logout" class="nav-link" style="color: #fda4af;"><i class="ph ph-sign-out"></i> Logout</a>
        </div>
    </div>

    <div class="main">
        <div class="header">
            <div>
                <h1 style="font-size: 28px; margin: 0; font-weight: 800;">Internal Audit Terminal</h1>
                <p style="color: #64748b; margin-top: 5px;">Compliance Monitoring & Financial Verification.</p>
            </div>
            <div style="display:flex; gap:10px;">
                <button onclick="window.print()" class="btn-print" style="padding:10px 20px; border-radius:10px; font-weight:700;"><i class="ph ph-file-pdf"></i> Export Debtors List</button>
            </div>
        </div>

        <div class="card-grid">
            <div class="card">
                <div style="color: #ef4444; font-size: 24px; margin-bottom: 15px;"><i class="ph ph-warning-diamond"></i></div>
                <div style="color: #64748b; font-size: 14px; font-weight: 600;">ACTIVE DEBTORS</div>
                <div style="font-size: 32px; font-weight: 800; margin-top: 5px;"><?= $debtorsCount ?></div>
            </div>
            <div class="card">
                <div style="color: #10b981; font-size: 24px; margin-bottom: 15px;"><i class="ph ph-check-circle"></i></div>
                <div style="color: #64748b; font-size: 14px; font-weight: 600;">FULLY PAID ENROLLMENT</div>
                <div style="font-size: 32px; font-weight: 800; margin-top: 5px;"><?= $paidCount ?></div>
            </div>
            <div class="card">
                <div style="color: #3b82f6; font-size: 24px; margin-bottom: 15px;"><i class="ph ph-stamp"></i></div>
                <div style="color: #64748b; font-size: 14px; font-weight: 600;">PENDING EXPENSE VOUCHERS</div>
                <div style="font-size: 32px; font-weight: 800; margin-top: 5px;"><?= $pendingExpenses ?></div>
            </div>
        </div>

        <h3 class="section-title"><i class="ph ph-receipt" style="color:#ef4444"></i> Recent Expense Postings (Verification Required)</h3>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Category</th>
                        <th>Description</th>
                        <th>Amount (₦)</th>
                        <th>Audit Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($expenses as $ex): ?>
                    <tr>
                        <td style="font-size:13px;"><?= date('d M Y', strtotime($ex['transaction_date'])) ?></td>
                        <td><span style="font-weight:700; font-size:12px; color:#475569;"><?= htmlspecialchars($ex['category_name']) ?></span></td>
                        <td style="color:#64748b; font-size:13px;"><?= htmlspecialchars($ex['description']) ?></td>
                        <td style="font-weight:800;">₦<?= number_format($ex['amount'], 2) ?></td>
                        <td>
                            <?php 
                                $s = $ex['status'] ?? 'pending';
                                $clr = '#f59e0b';
                                if ($s === 'approved') { $clr = '#10b981'; }
                                if ($s === 'rejected') { $clr = '#ef4444'; }
                            ?>
                            <span style="color:<?= $clr ?>; font-weight:800; font-size:11px; text-transform:uppercase;"><?= $s === 'approved' ? 'VERIFIED' : $s ?></span>
                        </td>
                        <td style="display:flex; gap:5px;">
                            <?php if(($ex['status'] ?? 'pending') === 'pending'): ?>
                                <button class="btn-sm btn-approve" onclick="auditAction(<?= $ex['id'] ?>, 'approve_expense')">Approve</button>
                                <button class="btn-sm" style="background:#fee2e2; color:#991b1b;" onclick="auditAction(<?= $ex['id'] ?>, 'reject_expense')">Reject</button>
                            <?php else: ?>
                                <span style="font-size:10px; color:#94a3b8;">No Actions</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <form id="auditForm" method="POST" style="display:none;">
            <input type="hidden" name="id" id="auditId">
            <input type="hidden" name="action" id="auditAction">
        </form>

        <h3 class="section-title"><i class="ph ph-users-three" style="color:#3b82f6"></i> Payroll & Staff Remuneration Audit</h3>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Staff Name</th>
                        <th>Designation</th>
                        <th>Base Salary (₦)</th>
                        <th>Payment Status</th>
                        <th>Payslip</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($staffSalaries as $s): ?>
                    <tr>
                        <td style="font-weight:700;"><?= htmlspecialchars($s['full_name']) ?></td>
                        <td><span style="font-size:12px; color:#64748b;"><?= htmlspecialchars($s['employee_number']) ?></span></td>
                        <td style="font-weight:700;">₦<?= number_format($s['salary'], 2) ?></td>
                        <td>
                            <?php if(($s['status']??'') === 'Paid'): ?>
                                <span style="background:#dcfce3; color:#166534; padding:4px 10px; border-radius:6px; font-size:11px; font-weight:800;">PAID (<?= date('M', strtotime($s['payment_date'])) ?>)</span>
                            <?php else: ?>
                                <span style="background:#fee2e2; color:#991b1b; padding:4px 10px; border-radius:6px; font-size:11px; font-weight:800;">UNPAID</span>
                            <?php endif; ?>
                        </td>
                        <td><button class="btn-sm btn-print" onclick="window.print()"><i class="ph ph-printer"></i> Print Payslip</button></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        function auditAction(id, action) {
            const verb = action === 'approve_expense' ? 'verify and approve' : 'reject';
            if(confirm('Are you sure you want to ' + verb + ' this expense voucher?')) {
                document.getElementById('auditId').value = id;
                document.getElementById('auditAction').value = action;
                document.getElementById('auditForm').submit();
            }
        }
    </script>
</body>
</html>
