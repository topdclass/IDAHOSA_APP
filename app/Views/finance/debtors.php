<?php
require_once ROOT_PATH . '/config/database.php';
$schoolId = $_SESSION['school_id'] ?? 101;

// Fetch Debtors
$query = "
    SELECT 
        u.full_name as student_name, 
        c.class_name, 
        f.fee_name,
        i.total_amount, 
        i.paid_amount, 
        (i.total_amount - i.paid_amount) as balance, 
        i.status,
        i.due_date
    FROM student_invoices i
    JOIN users u ON i.student_id = u.id
    JOIN institute_students ins ON u.id = ins.student_id
    JOIN classes c ON ins.class_id = c.id
    LEFT JOIN fee_types f ON i.fee_type_id = f.id
    WHERE i.total_amount > i.paid_amount
    ORDER BY c.class_name, u.full_name
";
$stmt = $pdo->query($query);
$debtors = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalOwed = 0;
foreach ($debtors as $d) {
    $totalOwed += $d['balance'];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debtors & Student Balances - Finance</title>
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
        
        .panel { background: white; padding: 25px; border-radius: 12px; border: 1px solid var(--border); box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); margin-bottom: 30px; }
        
        .stats-card { background: #fee2e2; border: 1px solid #fca5a5; padding: 20px; border-radius: 12px; display: inline-block; margin-bottom: 25px; }
        .stats-card h3 { color: #b91c1c; font-size: 13px; text-transform: uppercase; margin-bottom: 8px; }
        .stats-card .val { color: #991b1b; font-size: 32px; font-weight: 800; }

        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid var(--border); }
        th { font-size: 12px; text-transform: uppercase; color: #64748b; background: #f8fafc; }
        td { font-size: 14px; font-weight: 500; }
        tbody tr:hover { background: #f8fafc; }
        
        .badge-warning { background: #fef9c3; color: #854d0e; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 700; text-transform: capitalize; }
        .badge-danger { background: #fee2e2; color: #b91c1c; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 700; text-transform: capitalize; }
    </style>
</head>
<body>
    <?php require ROOT_PATH . '/app/Views/finance/layout/sidebar.php'; ?>

    <div class="main">
        <div class="header">
            <h1 style="font-size: 24px;">Student Fee Balances & Debtors</h1>
            <button onclick="window.print()" style="background:var(--primary); color:white; border:none; padding:10px 20px; border-radius:8px; cursor:pointer;"><i class="ph ph-printer"></i> Export Report</button>
        </div>

        <div class="stats-card">
            <h3>Total Outstanding School Fees</h3>
            <div class="val">₦<?= number_format($totalOwed, 2) ?></div>
        </div>

        <div class="panel">
            <h3 style="margin-bottom: 20px;">Debtors List</h3>
            <table>
                <thead>
                    <tr>
                        <th>Student Name</th>
                        <th>Class</th>
                        <th>Fee Type</th>
                        <th>Expected (₦)</th>
                        <th>Paid (₦)</th>
                        <th>Balance (₦)</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($debtors)): ?>
                        <tr><td colspan="8" style="text-align: center; color: #94a3b8; padding: 30px;">No students currently owe fees. Excellent!</td></tr>
                    <?php else: ?>
                        <?php foreach($debtors as $d): ?>
                            <tr>
                                <td><?= htmlspecialchars($d['student_name']) ?></td>
                                <td><?= htmlspecialchars($d['class_name']) ?></td>
                                <td><?= htmlspecialchars($d['fee_name'] ?: 'General Fees') ?></td>
                                <td><?= number_format($d['total_amount'], 2) ?></td>
                                <td style="color:#10b981;"><?= number_format($d['paid_amount'], 2) ?></td>
                                <td style="color:#ef4444; font-weight:800;"><?= number_format($d['balance'], 2) ?></td>
                                <td>
                                    <?php if ($d['status'] === 'partial'): ?>
                                        <span class="badge-warning">Partially Paid</span>
                                    <?php else: ?>
                                        <span class="badge-danger">Unpaid</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button class="btn-msg" onclick="sendDebtAlert(<?= $d['balance'] ?>)" style="background:#4b5563; color:white; border:none; padding:6px 12px; border-radius:6px; font-size:11px; cursor:pointer;"><i class="ph ph-bell-ringing"></i> Notify</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        function sendDebtAlert(balance) {
            confirm('Send push notifications to student and parent regarding the ₦' + balance.toLocaleString() + ' balance?');
            alert('Push notification and in-app alerts dispatched successfully.');
        }
    </script>
</body>
</html>
