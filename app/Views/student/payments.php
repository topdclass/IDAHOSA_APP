<?php
/**
 * Student Payment History & Receipt Center
 */
require_once dirname(dirname(dirname(__DIR__))) . '/config/database.php';
$userId = $_SESSION['user_id'] ?? 0;
$today = date('Y-m-d');

// 1. Fetch Student/Class Info
$stmt = $pdo->prepare("SELECT u.full_name as name, c.class_name as class_name, s.student_no
                      FROM institute_students s
                      JOIN users u ON s.student_id = u.id
                      LEFT JOIN classes c ON s.class_id = c.id
                      WHERE s.student_id = ? LIMIT 1");
$stmt->execute([$userId]);
$student = $stmt->fetch();

if (!$student) die("Student profile not found.");

// 2. Fetch Invoices
$stmt = $pdo->prepare("SELECT i.*, ft.fee_name 
                      FROM student_invoices i 
                      LEFT JOIN fee_types ft ON i.fee_type_id = ft.id
                      WHERE i.student_id = ? ORDER BY i.created_at DESC");
$stmt->execute([$userId]);
$invoices = $stmt->fetchAll();

// 3. Calculate Outstanding
$totalOutstanding = 0;
foreach($invoices as $inv) {
    if ($inv['status'] !== 'paid') {
        $totalOutstanding += ($inv['total_amount'] - $inv['paid_amount']);
    }
}

$pageTitle = 'Payment history - Rosmon SMS';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <style>
        :root {
            --primary: #5A57E6; --primary-light: #EEF2FF;
            --text-dark: #1E293B; --text-muted: #64748B;
            --bg: #F8FAFC; --border: #E2E8F0;
            --green: #10B981; --red: #EF4444; --orange: #F59E0B;
        }
        * { box-sizing: border-box; font-family: 'Outfit', sans-serif; }
        body { margin: 0; background: var(--bg); display: flex; color: var(--text-dark); min-height: 100vh; }

        .sidebar { width: 270px; background: #fff; border-right: 1px solid var(--border); padding: 30px 20px; position: fixed; height: 100vh; }
        .main { flex: 1; margin-left: 270px; padding: 40px; }

        .nav-item { display:flex; align-items:center; gap:12px; padding:12px 16px; text-decoration:none; color:var(--text-muted); border-radius:10px; margin-bottom:5px; font-weight:600; font-size:14px; }
        .nav-item.active { background: var(--primary-light); color: var(--primary); }
        .nav-item i { font-size: 20px; }

        .header { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:40px; }
        .balance-card { background: linear-gradient(135deg, #1E1B4B, #4338CA); color:white; padding:30px; border-radius:24px; display:flex; justify-content:space-between; align-items:center; margin-bottom:40px; box-shadow: 0 10px 20px rgba(79, 70, 229, 0.15); border: 1px solid rgba(255,255,255,0.1); }
        
        .table-card { background:#fff; border-radius:20px; border:1px solid var(--border); overflow:hidden; }
        table { width:100%; border-collapse:collapse; }
        th { background:#F8FAFC; text-align:left; padding:18px 25px; font-size:12px; font-weight:700; color:var(--text-muted); text-transform:uppercase; border-bottom:1px solid var(--border); }
        td { padding:20px 25px; border-bottom:1px solid #F1F5F9; font-size:14px; font-weight:600; }
        
        .status-pill { padding:6px 14px; border-radius:12px; font-size:11px; font-weight:800; display:inline-block; }
        .pill-paid { background:#DCFCE7; color:#166534; }
        .pill-partial { background:#FEF3C7; color:#92400E; }
        .pill-unpaid { background:#FEE2E2; color:#B91C1C; }

        .btn-receipt { background: #EEF2FF; color:var(--primary); padding:8px 15px; border-radius:10px; text-decoration:none; font-size:12px; font-weight:800; display:inline-flex; align-items:center; gap:8px; border: 1px solid #E0E7FF; }
        .btn-receipt:hover { background: var(--primary); color:white; }

        .sub-menu { padding-left: 30px; }
    </style>
</head>
<body>
    <div class="sidebar">
        <h2 style="font-size: 16px; margin-bottom: 40px; font-weight: 800; color: var(--primary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?= htmlspecialchars($globalSchoolName ?? 'Rosmon International School') ?>"><?= strtoupper(htmlspecialchars($globalSchoolName ?? 'Rosmon International School')) ?></h2>
        <div class="nav-menu">
            <a href="dashboard" class="nav-item">
                <i class="ph ph-chart-line-up"></i> Dashboard
            </a>
            <a href="attendance" class="nav-item">
                <i class="ph ph-fingerprint"></i> Student Clocking
            </a>
            
            <div class="nav-group">
                <a href="javascript:void(0)" class="nav-item" onclick="toggleSub(this)">
                    <i class="ph ph-desktop"></i> CBT <i class="ph ph-caret-down" style="margin-left:auto; font-size:12px;"></i>
                </a>
                <div class="sub-menu" style="display:none;">
                    <a href="#" class="nav-item" style="font-size:12px;">Take Mock CBT</a>
                    <a href="#" class="nav-item" style="font-size:12px;">Take Exam</a>
                    <a href="#" class="nav-item" style="font-size:12px;">View Result</a>
                </div>
            </div>

            <a href="<?= WEB_ROOT ?>/student/messaging" class="nav-item">
                <i class="ph ph-chat-circle-dots"></i> Messaging
            </a>
            <a href="<?= WEB_ROOT ?>/student/payments" class="nav-item active">
                <i class="ph ph-receipt"></i> Payment history
            </a>
            <a href="<?= WEB_ROOT ?>/student/timetable" class="nav-item">
                <i class="ph ph-calendar-blank"></i> <?= htmlspecialchars($student['class_name'] ?? 'Class') ?> Timetable
            </a>
        </div>
    </div>

    <div class="main">
        <div class="header">
            <div>
                <h1 style="font-size:32px; font-weight:800; margin:0;">Payment Ledger</h1>
                <p style="color:var(--text-muted); font-weight:600; margin-top:10px;">View receipts and manage outstanding student fees</p>
            </div>
            <div style="background:#fff; padding:15px; border-radius:16px; border:1px solid var(--border); box-shadow:0 4px 6px rgba(0,0,0,0.02);">
                <div style="font-size:12px; color:var(--text-muted); font-weight:700; text-transform:uppercase;">ID: <?= htmlspecialchars($student['student_no'] ?? '#STU-001') ?></div>
                <div style="font-size:16px; font-weight:800; margin-top:5px;"><?= htmlspecialchars($student['name']) ?></div>
            </div>
        </div>

        <div class="balance-card">
            <div>
                <p style="font-size:14px; font-weight:600; color:#A5B4FC; margin:0;">Total Outstanding Balance</p>
                <h2 style="font-size:48px; font-weight:800; margin:10px 0;">₦<?= number_format($totalOutstanding, 2) ?></h2>
                <div style="background:rgba(255,255,255,0.2); padding:6px 15px; border-radius:8px; font-size:12px; font-weight:700; display:inline-block;">
                    Status: <?= $totalOutstanding > 0 ? 'Payment Required' : 'Fully Paid' ?>
                </div>
            </div>
            <div style="width:120px; height:120px; background:rgba(255,255,255,0.1); border-radius:50%; display:flex; align-items:center; justify-content:center; border: 1px solid rgba(255,255,255,0.2);">
                <i class="ph ph-bank" style="font-size:60px; color:white;"></i>
            </div>
        </div>

        <div class="table-card">
            <table>
                <thead>
                    <tr>
                        <th>Fee Type</th>
                        <th>Billed On</th>
                        <th>Total Amount</th>
                        <th>Paid Amount</th>
                        <th>Balance</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($invoices)): ?>
                        <tr><td colspan="7" style="text-align:center; padding:50px; color:var(--text-muted);">No records found.</td></tr>
                    <?php else: ?>
                        <?php foreach($invoices as $inv): ?>
                        <tr>
                            <td><?= htmlspecialchars($inv['fee_name'] ?: 'Miscellaneous') ?></td>
                            <td><?= date('M d, Y', strtotime($inv['created_at'])) ?></td>
                            <td>₦<?= number_format($inv['total_amount'], 2) ?></td>
                            <td style="color:var(--green);">₦<?= number_format($inv['paid_amount'], 2) ?></td>
                            <td style="color:<?= ($inv['total_amount'] - $inv['paid_amount'] > 0) ? 'var(--red)' : 'var(--text-muted)' ?>;">
                                ₦<?= number_format($inv['total_amount'] - $inv['paid_amount'], 2) ?>
                            </td>
                            <td>
                                <span class="status-pill pill-<?= $inv['status'] ?>">
                                    <?= strtoupper($inv['status']) ?>
                                </span>
                            </td>
                            <td>
                                <?php if($inv['paid_amount'] > 0): ?>
                                    <a href="receipt?id=<?= $inv['id'] ?>" class="btn-receipt">
                                        <i class="ph ph-printer"></i> Receipt
                                    </a>
                                <?php else: ?>
                                    <span style="font-size:11px; color:var(--text-muted);">No Receipt</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        function toggleSub(el) {
            const group = el.nextElementSibling;
            if (group.style.display === 'none') {
                group.style.display = 'block';
                el.querySelector('.ph-caret-down').style.transform = 'rotate(180deg)';
                el.style.background = '#EEF2FF';
            } else {
                group.style.display = 'none';
                el.querySelector('.ph-caret-down').style.transform = 'rotate(0deg)';
                el.style.background = 'transparent';
            }
        }
    </script>
</body>
</html>
