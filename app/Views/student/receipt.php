<?php
/**
 * Official Payment Receipt Page
 */
require_once dirname(dirname(dirname(__DIR__))) . '/config/database.php';
$invId = $_GET['id'] ?? 0;

$stmt = $pdo->prepare("SELECT i.*, ft.fee_name, u.full_name as student_name, c.class_name as class_name, s.student_no
                      FROM student_invoices i
                      LEFT JOIN fee_types ft ON i.fee_type_id = ft.id
                      JOIN institute_students s ON i.student_id = s.student_id
                      JOIN users u ON s.student_id = u.id
                      LEFT JOIN classes c ON s.class_id = c.id
                      WHERE i.id = ? LIMIT 1");
$stmt->execute([$invId]);
$receipt = $stmt->fetch();

if (!$receipt) die("Receipt not found.");

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Receipt_<?= $receipt['id'] ?>_RosmonSMS</title>
    <style>
        body { font-family: 'Inter', sans-serif; padding: 40px; color: #1e293b; background: #f8fafc; }
        .receipt-card { background: white; max-width: 800px; margin: 0 auto; padding: 60px; border-radius: 8px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); border-top: 10px solid #13198F; }
        .header { display: flex; justify-content: space-between; border-bottom: 2px solid #f1f5f9; padding-bottom: 30px; margin-bottom: 40px; }
        .logo-box { background: #13198F; color: white; width: 60px; height: 60px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 24px; }
        .title { text-align: right; }
        .row { display: flex; justify-content: space-between; margin-bottom: 20px; }
        .label { color: #64748b; font-size: 14px; font-weight: 600; text-transform: uppercase; }
        .value { font-weight: 700; font-size: 16px; }
        .amount-box { background: #f8fafc; padding: 30px; border-radius: 12px; margin-top: 40px; display: flex; justify-content: space-between; align-items: center; }
        .btn-print { margin-top: 40px; text-align: center; }
        @media print { .btn-print { display: none; } body { background: white; padding: 0; } .receipt-card { box-shadow: none; border: 1px solid #eee; } }
    </style>
</head>
<body>
    <div class="receipt-card">
        <div class="header">
            <div class="logo-box">R</div>
            <div class="title">
                <h1 style="margin: 0; font-size: 24px; color: #13198F;">Official Payment Receipt</h1>
                <p style="margin: 5px 0 0; color: #64748b; font-weight: 600;">Receipt #: ROS-<?= str_pad($receipt['id'], 6, '0', STR_PAD_LEFT) ?></p>
            </div>
        </div>

        <div class="row">
            <div>
                <div class="label">Date Issued</div>
                <div class="value"><?= date('F j, Y', strtotime($receipt['created_at'])) ?></div>
            </div>
            <div style="text-align: right;">
                <div class="label">Payment Status</div>
                <div class="value" style="color: #10b981;"><?= strtoupper($receipt['status']) ?></div>
            </div>
        </div>

        <div style="border: 1px solid #f1f5f9; border-radius: 16px; padding: 30px; margin: 40px 0;">
            <h3 style="margin: 0 0 20px 0; font-size: 14px; text-transform: uppercase; color: #64748b;">Student Details</h3>
            <div class="row">
                <div>
                    <div class="label">Name</div>
                    <div class="value"><?= htmlspecialchars($receipt['student_name']) ?></div>
                </div>
                <div style="text-align: right;">
                    <div class="label">Student ID</div>
                    <div class="value"><?= htmlspecialchars($receipt['student_no'] ?: '#ST-'.str_pad($receipt['student_id'], 4, '0', STR_PAD_LEFT)) ?></div>
                </div>
            </div>
            <div class="row" style="margin-top:20px;">
                <div>
                    <div class="label">Class</div>
                    <div class="value"><?= htmlspecialchars($receipt['class_name'] ?? 'General') ?></div>
                </div>
            </div>
        </div>

        <h3 style="margin: 40px 0 20px 0; font-size: 14px; text-transform: uppercase; color: #64748b;">Transaction Summary</h3>
        <div style="width: 100%; border-bottom: 1px solid #f1f5f9; padding: 15px 0; display: flex; justify-content: space-between;">
            <span style="font-weight: 600;"><?= htmlspecialchars($receipt['fee_name'] ?: 'Tuition Fees') ?></span>
            <span style="font-weight: 800;">₦<?= number_format($receipt['total_amount'], 2) ?></span>
        </div>

        <div class="amount-box">
            <div>
                <div class="label">Total Paid</div>
                <div style="font-size: 32px; font-weight: 800; color: #13198F;">₦<?= number_format($receipt['paid_amount'], 2) ?></div>
            </div>
            <?php if($receipt['total_amount'] > $receipt['paid_amount']): ?>
                <div style="text-align: right;">
                    <div class="label">Outstanding</div>
                    <div style="font-size: 18px; font-weight: 800; color: #ef4444;">₦<?= number_format($receipt['total_amount'] - $receipt['paid_amount'], 2) ?></div>
                </div>
            <?php endif; ?>
        </div>

        <div style="margin-top: 60px; display: flex; justify-content: space-between; align-items: flex-end;">
            <div>
                <div style="width: 200px; border-bottom: 2px solid #1e293b; margin-bottom: 10px;"></div>
                <div class="label">Authorized Signature</div>
            </div>
            <div style="text-align: right; color: #94a3b8; font-size: 12px; font-weight: 600;">
                Generated by RosmonSMS Core on <?= date('M d, Y H:i:s') ?>
            </div>
        </div>
    </div>

    <div class="btn-print">
        <button onclick="window.print()" style="background: #13198F; color: white; border: none; padding: 15px 40px; border-radius: 12px; font-weight: 800; cursor: pointer;">PRINT THIS RECEIPT</button>
        <br><br>
        <a href="javascript:history.back()" style="color: #64748b; text-decoration: none; font-weight: 600; font-size: 14px;">&larr; Back to History</a>
    </div>
</body>
</html>
