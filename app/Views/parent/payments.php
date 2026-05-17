<?php
/**
 * Parent Payment Monitoring Center
 * Displays payment history for all children in family
 */
require_once dirname(dirname(dirname(__DIR__))) . '/config/database.php';
$parentUserId = $_SESSION['user_id'] ?? 0;

// 1. Fetch Children
$stmt = $pdo->prepare("SELECT s.student_id as id, u.full_name as name, c.class_name as class_name, s.student_no, s.family_id
                      FROM institute_students s
                      JOIN users u ON s.student_id = u.id
                      LEFT JOIN classes c ON s.class_id = c.id
                      JOIN institute_parents p ON s.family_id = p.family_id
                      WHERE p.parent_id = ?");
$stmt->execute([$parentUserId]);
$children = $stmt->fetchAll();

if (empty($children)) {
    die("No children associated with this parent profile found.");
}

$selectedStudentId = $_GET['student_id'] ?? $children[0]['id'];

// 2. Fetch Invoices for selected child
$stmt = $pdo->prepare("SELECT i.*, ft.fee_name 
                      FROM student_invoices i 
                      LEFT JOIN fee_types ft ON i.fee_type_id = ft.id
                      WHERE i.student_id = ? ORDER BY i.created_at DESC");
$stmt->execute([$selectedStudentId]);
$invoices = $stmt->fetchAll();

// 3. Totals
$totalPaid = 0; $totalDue = 0;
foreach($invoices as $inv) {
    if($inv['status'] !== 'paid') $totalDue += ($inv['total_amount'] - $inv['paid_amount']);
    $totalPaid += $inv['paid_amount'];
}

$pageTitle = 'Children Payments - Rosmon SMS';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <style>
        :root { --primary: #059669; --bg: #F0FDF4; --text: #064E3B; --border: #D1FAE5; }
        * { box-sizing: border-box; font-family: 'Outfit', sans-serif; }
        body { margin: 0; background: var(--bg); display: flex; color: var(--text); min-height: 100vh; }

        .sidebar { width: 260px; height: 100vh; background: var(--primary); color: white; padding: 25px; position: fixed; }
        .main { flex: 1; margin-left: 260px; padding: 40px; }

        .nav-link { display:flex; align-items:center; gap:12px; padding:12px; color:#D1FAE5; text-decoration:none; border-radius:8px; margin-bottom:5px; font-weight:600; }
        .nav-link.active, .nav-link:hover { background: rgba(255,255,255,0.1); color:white; }

        .child-selector { display:flex; gap:15px; margin-bottom:40px; }
        .child-btn { background:white; padding:15px 25px; border-radius:16px; border:2px solid var(--border); cursor:pointer; text-decoration:none; color:var(--text); transition:0.2s; display:flex; align-items:center; gap:12px; }
        .child-btn.active { border-color: var(--primary); background: #f0fdf4; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }

        .balance-pill { background: white; padding: 20px 30px; border-radius: 20px; border: 1px solid var(--border); display: flex; align-items: center; gap: 20px; margin-bottom: 40px; }
        
        .ledger-card { background:white; border-radius:24px; border:1px solid var(--border); overflow:hidden; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.03); }
        table { width:100%; border-collapse:collapse; }
        th { text-align:left; background:#F9FAFB; padding:18px 25px; font-size:12px; font-weight:700; color:#6B7280; text-transform:uppercase; border-bottom:1px solid #F1F5F9; }
        td { padding:20px 25px; border-bottom:1px solid #F1F5F9; font-size:14px; font-weight:600; }

        .status-badge { padding:6px 12px; border-radius:10px; font-size:11px; font-weight:800; display:inline-block; }
        .status-paid { background:#D1FAE5; color:#065F46; }
        .status-unpaid { background:#FEE2E2; color:#991B1B; }

        .btn-view { background:#059669; color:white; padding:8px 16px; border-radius:8px; text-decoration:none; font-size:12px; font-weight:700; display:inline-flex; align-items:center; gap:8px; }
    </style>
</head>
<body>
    <div class="sidebar">
        <div style="text-align:center; margin-bottom:10px;">
            <?php if (!empty($globalSchoolLogo)): ?>
                <img src="<?= WEB_ROOT . '/public' . $globalSchoolLogo ?>" alt="Logo" style="width:48px; height:48px; border-radius:50%; object-fit:contain; border:2px solid rgba(255,255,255,0.2);">
            <?php endif; ?>
        </div>
        <h2 style="font-size: 16px; margin-bottom: 40px; font-weight: 800; color: var(--primary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; text-align:center;" title="<?= htmlspecialchars($globalSchoolName ?? 'Rosmon International School') ?>"><?= strtoupper(htmlspecialchars($globalSchoolName ?? 'Rosmon International School')) ?></h2>
        <a href="dashboard" class="nav-link"><i class="ph ph-house"></i> Home</a>
        <a href="my-children" class="nav-link"><i class="ph ph-baby"></i> My Children</a>
        <a href="visiting-card" class="nav-link"><i class="ph ph-identification-badge"></i> My Visiting Card</a>
        <a href="payments" class="nav-link active"><i class="ph ph-receipt"></i> Payment history</a>
        <a href="performance" class="nav-link"><i class="ph ph-chart-line"></i> Performance</a>
        <div style="margin-top: auto; padding-top: 20px;">
            <a href="<?= WEB_ROOT ?>/logout" class="nav-link" style="color: #fda4af;"><i class="ph ph-sign-out"></i> Logout</a>
        </div>
    </div>

    <div class="main">
        <h1 style="font-size:28px; font-weight:800; margin-bottom:30px;">Payment Monitor</h1>

        <div class="child-selector">
            <?php foreach($children as $child): ?>
                <a href="?student_id=<?= $child['id'] ?>" class="child-btn <?= ($selectedStudentId == $child['id']) ? 'active' : '' ?>">
                    <div style="width:40px; height:40px; background:#e2e8f0; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:800; color:#475569;"><?= strtoupper(substr($child['name'], 0, 1)) ?></div>
                    <div>
                        <div style="font-size:14px; font-weight:800;"><?= htmlspecialchars($child['name']) ?></div>
                        <div style="font-size:11px; color:#64748b; margin-top:2px;">Grade: <?= htmlspecialchars($child['class_name'] ?? 'N/A') ?></div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>

        <div class="balance-pill">
            <div style="border-right:1px solid #D1FAE5; padding-right:40px;">
                <div style="font-size:12px; color:#64748b; font-weight:700; text-transform:uppercase;">Total Outstanding</div>
                <div style="font-size:32px; font-weight:900; color:#EF4444; margin-top:5px;">₦<?= number_format($totalDue, 2) ?></div>
            </div>
            <div>
                <div style="font-size:12px; color:#64748b; font-weight:700; text-transform:uppercase;">Paid This Term</div>
                <div style="font-size:32px; font-weight:900; color:#059669; margin-top:5px;">₦<?= number_format($totalPaid, 2) ?></div>
            </div>
            <div style="margin-left:auto;">
                <button style="background:var(--primary); color:white; border:none; padding:12px 25px; border-radius:12px; font-weight:800; cursor:pointer;"><i class="ph ph-credit-card"></i> PAY NOW</button>
            </div>
        </div>

        <div class="ledger-card">
            <table>
                <thead>
                    <tr>
                        <th>BILL DESCRIPTION</th>
                        <th>DATE BILLED</th>
                        <th>TOTAL AMOUNT</th>
                        <th>PAID</th>
                        <th>BALANCE</th>
                        <th>STATUS</th>
                        <th>ACTION</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($invoices)): ?>
                        <tr><td colspan="7" style="text-align:center; padding:50px; color:#64748b;">No billing records for selected child.</td></tr>
                    <?php else: ?>
                        <?php foreach($invoices as $inv): ?>
                            <tr>
                                <td><?= htmlspecialchars($inv['fee_name'] ?: 'General Fees') ?></td>
                                <td><?= date('M d, Y', strtotime($inv['created_at'])) ?></td>
                                <td>₦<?= number_format($inv['total_amount'], 2) ?></td>
                                <td style="color:#059669;">₦<?= number_format($inv['paid_amount'], 2) ?></td>
                                <td style="color:#EF4444;">₦<?= number_format($inv['total_amount'] - $inv['paid_amount'], 2) ?></td>
                                <td>
                                    <span class="status-badge status-<?= $inv['status'] === 'paid' ? 'paid' : 'unpaid' ?>">
                                        <?= strtoupper($inv['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if($inv['paid_amount'] > 0): ?>
                                        <a href="<?= WEB_ROOT ?>/parent/receipt?id=<?= $inv['id'] ?>" class="btn-view" target="_blank">
                                            <i class="ph ph-printer"></i> Receipt
                                        </a>
                                    <?php else: ?>
                                        <span style="font-size:11px; color:#94A3B8;">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
