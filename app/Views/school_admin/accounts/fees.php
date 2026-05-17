<?php
// Student Fee & Revenue Lifecycle Logic

try {
    
    // Auto Migration: Fee Infrastructure
    $pdo->exec("CREATE TABLE IF NOT EXISTS fee_types (
        id INT AUTO_INCREMENT PRIMARY KEY,
        fee_name VARCHAR(100) NOT NULL,
        default_amount DECIMAL(15,2),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS student_invoices (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        fee_type_id INT NOT NULL,
        total_amount DECIMAL(15,2) NOT NULL,
        paid_amount DECIMAL(15,2) DEFAULT 0,
        due_date DATE,
        status ENUM('unpaid', 'partial', 'paid') DEFAULT 'unpaid',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Seed Fee Types if empty
    if ($pdo->query("SELECT COUNT(*) FROM fee_types")->fetchColumn() == 0) {
        $pdo->exec("INSERT INTO fee_types (fee_name, default_amount) VALUES 
            ('First Term Tuition', 25000.00), ('Annual Sports Fee', 2000.00), ('Maintenance Levy', 5000.00)");
    }

    $message = '';

    // Handle Fee Payment Recording
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pay_fee'])) {
        $invoice_id = $_POST['invoice_id'];
        $p_amount = $_POST['payment_amount'];
        $method = $_POST['method'] ?? 'Cash';
        
        $pdo->beginTransaction();
        try {
            // Update Invoice
            $stmt = $pdo->prepare("UPDATE student_invoices SET paid_amount = paid_amount + ? WHERE id = ?");
            $stmt->execute([$p_amount, $invoice_id]);

            // check new status
            $stmt = $pdo->prepare("SELECT total_amount, paid_amount FROM student_invoices WHERE id = ?");
            $stmt->execute([$invoice_id]);
            $inv = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $new_status = ($inv['paid_amount'] >= $inv['total_amount']) ? 'paid' : 'partial';
            $stmt = $pdo->prepare("UPDATE student_invoices SET status = ? WHERE id = ?");
            $stmt->execute([$new_status, $invoice_id]);

            // Add to Ledger (Category: Tuition Fees)
            $cat_id = $pdo->query("SELECT id FROM account_categories WHERE category_name = 'Tuition Fees'")->fetchColumn() ?: 1;
            $stmt = $pdo->prepare("INSERT INTO transactions (category_id, amount, type, transaction_date, description, reference_no) VALUES (?, ?, 'in', CURRENT_DATE, ?, ?)");
            $stmt->execute([$cat_id, $p_amount, "Fee payment for INV-#$invoice_id", "REF-".time()]);

            $pdo->commit();
            $message = "Payment of ₦".number_format($p_amount, 2)." processed successfully!";
        } catch (Exception $e) {
            $pdo->rollBack();
            die("Payment error: " . $e->getMessage());
        }
    }

    // Fetch Invoices
    $query = "SELECT si.*, ft.fee_name, u.full_name, s.student_no as admission_number 
              FROM student_invoices si 
              JOIN institute_students s ON si.student_id = s.id 
              JOIN users u ON s.student_id = u.id 
              JOIN fee_types ft ON si.fee_type_id = ft.id 
              ORDER BY si.created_at DESC";
    $invoices = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Billing Error: " . $e->getMessage());
}

$pageTitle = 'Revenue Management - Rosmon SMS';
require ROOT_PATH . '/app/Views/school_admin/layout/header.php';
require ROOT_PATH . '/app/Views/school_admin/layout/sidebar.php';
?>

<div class="main-container">
    <div class="top-header">
        <div class="greeting">Accounts / <span style="color:var(--primary)">Fee &amp; Collections</span></div>
        <div class="header-actions">
            <button class="btn-primary" style="background:var(--text-dark); border-radius:20px; font-size:11px;"><i class="ph ph-receipt"></i> Generate Bulk Invoices</button>
        </div>
    </div>

    <!-- Outstanding Summary -->
    <div class="crud-card" style="margin-bottom:24px; padding:20px; display:flex; justify-content:space-between; align-items:center;">
        <div>
            <div style="font-size:11px; font-weight:700; color:var(--text-muted);">UNPAID REVENUE</div>
            <div style="font-size:20px; font-weight:800; color:#ef4444;">₦6,250,000.00</div>
        </div>
        <div style="text-align:right;">
            <div style="font-size:11px; font-weight:700; color:var(--text-muted);">TOTAL COLLECTIONS (CURRENT TERM)</div>
            <div style="font-size:20px; font-weight:800; color:#059669;">₦18,400,000.00</div>
        </div>
    </div>

    <div class="crud-card">
        <div class="crud-header">
            <h2 class="crud-title">Active Billing Statement</h2>
            <div style="font-size:12px; color:var(--primary); font-weight:700;"><?= $message ?></div>
        </div>

        <table class="crud-table">
            <thead>
                <tr>
                    <th>INVOICE</th>
                    <th>STUDENT / ID</th>
                    <th>FEE COMPONENT</th>
                    <th>BILLABLE</th>
                    <th>PAID</th>
                    <th>STATUS</th>
                    <th>ACTIONS</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($invoices)): ?>
                    <tr><td colspan="7" style="text-align:center; padding:40px; color:var(--text-muted);">No billing data found. Use 'Generate Invoices' to begin academic billing.</td></tr>
                <?php else: ?>
                    <?php foreach($invoices as $inv): ?>
                        <tr>
                            <td style="font-weight:800; color:var(--text-muted);">#00<?= $inv['id'] ?></td>
                            <td>
                                <div style="font-weight:700;"><?= htmlspecialchars((string)($inv['full_name'] ?? '')) ?></div>
                                <div style="font-size:10px; color:var(--text-muted);"><?= htmlspecialchars((string)($inv['admission_number'] ?? '')) ?></div>
                            </td>
                            <td style="font-weight:700;"><?= htmlspecialchars((string)($inv['fee_name'] ?? '')) ?></td>
                            <td style="font-weight:800;">₦<?= number_format($inv['total_amount'], 2) ?></td>
                            <td style="color:#059669; font-weight:800;">₦<?= number_format($inv['paid_amount'], 2) ?></td>
                            <td>
                                <span style="font-weight:800; font-size:10px; padding:4px 10px; border-radius:20px; 
                                    background:<?= $inv['status'] == 'paid' ? '#d1fae5; color:#065f46' : ($inv['status'] == 'partial' ? '#fef3c7; color:#92400e' : '#fee2e2; color:#991b1b') ?>;">
                                    <?= strtoupper($inv['status']) ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($inv['status'] !== 'paid'): ?>
                                    <button onclick="openPayModal(<?= $inv['id'] ?>, '<?= htmlspecialchars((string)($inv['full_name'] ?? '')) ?>', <?= $inv['total_amount'] - $inv['paid_amount'] ?>)" class="btn-primary" style="padding:4px 12px; font-size:10px;">Record Payment</button>
                                <?php else: ?>
                                    <i class="ph ph-check-circle" style="color:#059669; font-size:20px;"></i>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Quick Pay Modal -->
    <div id="pay-modal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:1001; align-items:center; justify-content:center;">
        <div class="crud-card" style="width:400px; padding:24px;">
            <h3 id="m-student" style="margin-bottom:15px;">Pay Fee</h3>
            <form method="POST">
                <input type="hidden" name="pay_fee" value="1">
                <input type="hidden" name="invoice_id" id="m-inv-id">
                
                <label style="display:block; font-size:12px; font-weight:700; margin-bottom:5px;">PAYMENT AMOUNT (₦)</label>
                <input type="number" id="m-amount" name="payment_amount" step="0.01" required style="width:100%; padding:10px; border:1px solid #ddd; border-radius:8px; margin-bottom:20px;">
                
                <div style="display:flex; gap:10px; justify-content:flex-end;">
                    <button type="button" onclick="document.getElementById('pay-modal').style.display='none'" style="border:none; background:none; cursor:pointer; font-weight:700;">Cancel</button>
                    <button type="submit" class="btn-primary">Post Payment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openPayModal(invId, name, balance) {
    document.getElementById('m-inv-id').value = invId;
    document.getElementById('m-student').innerText = "Record Payment for " + name;
    document.getElementById('m-amount').value = balance;
    document.getElementById('pay-modal').style.display = 'flex';
}
</script>

<?php require ROOT_PATH . '/app/Views/school_admin/layout/footer.php'; ?>
