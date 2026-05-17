<?php
// Chart of Accounts & Financial Core Logic

$message = '';

    // Handle Transaction Entry
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_transaction'])) {
        $cat_id = $_POST['category_id'];
        $amount = $_POST['amount'];
        $date = $_POST['t_date'];
        $desc = $_POST['desc'] ?? '';
        $ref = $_POST['ref'] ?? '';
        
        // Determine type based on category
        $stmt = $pdo->prepare("SELECT category_type FROM account_categories WHERE id = ?");
        $stmt->execute([$cat_id]);
        $c_type = $stmt->fetchColumn();
        $t_type = ($c_type == 'income') ? 'in' : 'out';

        $month = date('n', strtotime($date));
        $year = date('Y', strtotime($date));

        $stmt = $pdo->prepare("INSERT INTO account_transactions (category_id, amount, type, transaction_date, transaction_month, transaction_year, description, reference_no) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$cat_id, $amount, $t_type, $date, $month, $year, $desc, $ref]);
        $message = "Transaction recorded successfully!";
    }

    // Fetch Summaries
    $income_total = $pdo->query("SELECT SUM(amount) FROM account_transactions WHERE type='in'")->fetchColumn() ?: 0;
    $expense_total = $pdo->query("SELECT SUM(amount) FROM account_transactions WHERE type='out'")->fetchColumn() ?: 0;
    $balance = $income_total - $expense_total;

    // Fetch Recent Transactions
    $transactions = $pdo->query("SELECT t.*, c.category_name 
                                 FROM account_transactions t 
                                 JOIN account_categories c ON t.category_id = c.id 
                                 ORDER BY t.transaction_date DESC LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);

    // Fetch Categories
    $categories = $pdo->query("SELECT * FROM account_categories ORDER BY category_type, category_name")->fetchAll(PDO::FETCH_ASSOC);


$pageTitle = 'Financial Ledger - Rosmon SMS';
require ROOT_PATH . '/app/Views/school_admin/layout/header.php';
require ROOT_PATH . '/app/Views/school_admin/layout/sidebar.php';
?>

<div class="main-container">
    <div class="top-header">
        <div class="greeting">Accounts / <span style="color:var(--primary)">Institutional Ledger</span></div>
        <div class="header-actions">
            <button onclick="document.getElementById('trans-modal').style.display='flex'" class="btn-primary" style="background:#10b981;"><i class="ph ph-plus-circle"></i> New Transaction</button>
        </div>
    </div>

    <!-- Financial Pulse Cards -->
    <div style="display:grid; grid-template-columns: repeat(3, 1fr); gap:20px; margin-bottom:24px;">
        <div class="crud-card" style="padding:24px; background:linear-gradient(135deg, #059669, #10b981); color:#fff;">
            <div style="font-size:12px; font-weight:700; opacity:0.8;">TOTAL INCOME</div>
            <div style="font-size:24px; font-weight:800; margin-top:8px;">₦<?= number_format($income_total, 2) ?></div>
        </div>
        <div class="crud-card" style="padding:24px; background:linear-gradient(135deg, #dc2626, #ef4444); color:#fff;">
            <div style="font-size:12px; font-weight:700; opacity:0.8;">TOTAL EXPENSE</div>
            <div style="font-size:24px; font-weight:800; margin-top:8px;">₦<?= number_format($expense_total, 2) ?></div>
        </div>
        <div class="crud-card" style="padding:24px; border-left:4px solid var(--primary);">
            <div style="font-size:12px; font-weight:700; color:var(--text-muted);">CURRENT BALANCE</div>
            <div style="font-size:24px; font-weight:800; margin-top:8px; color:<?= $balance >= 0 ? '#059669' : '#dc2626' ?>;">₦<?= number_format($balance, 2) ?></div>
        </div>
    </div>

    <div class="crud-card">
        <div class="crud-header">
            <h2 class="crud-title">Master Transaction Ledger</h2>
            <div style="font-size:12px; color:var(--primary); font-weight:700;"><?= $message ?></div>
        </div>
        <table class="crud-table">
            <thead>
                <tr>
                    <th>DATE</th>
                    <th>ACCOUNT CATEGORY</th>
                    <th>DESCRIPTION / REF</th>
                    <th>TYPE</th>
                    <th>AMOUNT</th>
                    <th>ACTIONS</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($transactions)): ?>
                    <tr><td colspan="6" style="text-align:center; padding:40px; color:var(--text-muted);">No transactions recorded in the current period.</td></tr>
                <?php else: ?>
                    <?php foreach($transactions as $t): ?>
                        <tr>
                            <td style="font-weight:700;"><?= date('M d, Y', strtotime($t['transaction_date'])) ?></td>
                            <td><span style="font-weight:700;"><?= htmlspecialchars((string)($t['category_name'] ?? '')) ?></span></td>
                            <td>
                                <div style="font-size:13px;"><?= htmlspecialchars((string)($t['description'] ?? '')) ?: 'N/A' ?></div>
                                <div style="font-size:10px; color:var(--text-muted);"><?= htmlspecialchars((string)($t['reference_no'] ?? '')) ?></div>
                            </td>
                            <td>
                                <span style="font-weight:800; font-size:10px; padding:4px 10px; border-radius:20px; background:<?= $t['type'] == 'in' ? '#d1fae5; color:#065f46' : '#fee2e2; color:#991b1b' ?>;">
                                    <?= $t['type'] == 'in' ? 'INCOME' : 'EXPENSE' ?>
                                </span>
                            </td>
                            <td style="font-weight:800; font-family:'Courier New', monospace; color:<?= $t['type'] == 'in' ? '#059669' : '#dc2626' ?>;">
                                <?= $t['type'] == 'in' ? '+' : '-' ?> ₦<?= number_format($t['amount'], 2) ?>
                            </td>
                            <td><i class="ph ph-dots-three-vertical" style="cursor:pointer; color:#9ca3af;"></i></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Transaction Modal -->
    <div id="trans-modal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:1001; align-items:center; justify-content:center;">
        <div class="crud-card" style="width:500px; margin:0; padding:24px;">
            <div style="display:flex; justify-content:space-between; margin-bottom:20px;">
                <h3 style="margin:0;">Record New Transaction</h3>
                <button onclick="document.getElementById('trans-modal').style.display='none'" style="background:none; border:none; cursor:pointer;"><i class="ph ph-x" style="font-size:20px;"></i></button>
            </div>
            <form method="POST">
                <input type="hidden" name="add_transaction" value="1">
                
                <label style="display:block; font-size:12px; font-weight:700; margin-bottom:5px;">LEDGER CATEGORY</label>
                <select name="category_id" required style="width:100%; padding:10px; border:1px solid #ddd; border-radius:8px; margin-bottom:15px; font-size:14px;">
                    <option value="">-- Choose Category --</option>
                    <?php foreach($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>"><?= strtoupper($cat['category_type']) ?>: <?= htmlspecialchars((string)($cat['category_name'] ?? '')) ?></option>
                    <?php endforeach; ?>
                </select>

                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px; margin-bottom:15px;">
                    <div>
                        <label style="display:block; font-size:12px; font-weight:700; margin-bottom:5px;">AMOUNT (₦)</label>
                        <input type="number" step="0.01" name="amount" required style="width:100%; padding:10px; border:1px solid #ddd; border-radius:8px; font-size:14px;">
                    </div>
                    <div>
                        <label style="display:block; font-size:12px; font-weight:700; margin-bottom:5px;">DATE</label>
                        <input type="date" name="t_date" value="<?= date('Y-m-d') ?>" required style="width:100%; padding:10px; border:1px solid #ddd; border-radius:8px; font-size:14px;">
                    </div>
                </div>

                <label style="display:block; font-size:12px; font-weight:700; margin-bottom:5px;">DESCRIPTION</label>
                <input type="text" name="desc" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:8px; margin-bottom:15px; font-size:14px;">

                <label style="display:block; font-size:12px; font-weight:700; margin-bottom:5px;">REFERENCE NO. (OPTIONAL)</label>
                <input type="text" name="ref" placeholder="REC-001 or CHQ-..." style="width:100%; padding:10px; border:1px solid #ddd; border-radius:8px; margin-bottom:20px; font-size:14px;">

                <button type="submit" class="btn-primary" style="width:100%; padding:12px; font-weight:800; background:linear-gradient(135deg, #10b981, #059669);"><i class="ph ph-check-circle"></i> Commit Transaction</button>
            </form>
        </div>
    </div>
</div>

<?php require ROOT_PATH . '/app/Views/school_admin/layout/footer.php'; ?>
