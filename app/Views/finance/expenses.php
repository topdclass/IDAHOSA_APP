<?php
require_once ROOT_PATH . '/config/database.php';
$schoolId = $_SESSION['school_id'] ?? 101;

// Handle Form Submission
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_expense') {
    $categoryId = $_POST['category_id'];
    
    // Support Custom Category Creation
    if ($categoryId === 'new' && !empty($_POST['new_category_name'])) {
        $insCat = $pdo->prepare("INSERT INTO account_categories (category_name, category_type) VALUES (?, 'expense')");
        $insCat->execute([$_POST['new_category_name']]);
        $categoryId = $pdo->lastInsertId();
    }
    
    // Ensure category is selected
    if ($categoryId && $categoryId !== 'new') {
        $amount = $_POST['amount'];
        $date = $_POST['transaction_date'];
        $description = $_POST['description'] ?? '';
        
        $month = date('n', strtotime($date));
        $year = date('Y', strtotime($date));

        $stmt = $pdo->prepare("INSERT INTO account_transactions (amount, type, category_id, transaction_date, transaction_month, transaction_year, description) VALUES (?, 'out', ?, ?, ?, ?, ?)");
        if ($stmt->execute([$amount, $categoryId, $date, $month, $year, $description])) {
            $message = "Expense recorded successfully.";
        }
    } else {
        $message = "Please provide a valid category.";
    }
}

// Fetch Categories
$categories = $pdo->query("SELECT * FROM account_categories WHERE category_type='expense' ORDER BY category_name")->fetchAll();

// Fetch Recent Expenses
$recentExpenses = $pdo->query("
    SELECT t.*, c.category_name 
    FROM account_transactions t 
    JOIN account_categories c ON t.category_id = c.id 
    WHERE t.type = 'out' 
    ORDER BY t.transaction_date DESC, t.id DESC 
    LIMIT 20
")->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expense Tracking - Finance</title>
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
        .form-row { display: flex; gap: 20px; margin-bottom: 15px; }
        .form-group { flex: 1; display: flex; flex-direction: column; gap: 8px; }
        label { font-size: 13px; font-weight: 600; color: #64748b; }
        input, select, textarea { padding: 10px; border: 1px solid var(--border); border-radius: 8px; outline: none; font-size: 14px; }
        input:focus, select:focus { border-color: var(--primary); }
        .btn-danger { background: #ef4444; color: white; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; transition: 0.2s; }
        .btn-danger:hover { background: #dc2626; }

        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid var(--border); }
        th { font-size: 12px; text-transform: uppercase; color: #64748b; background: #f8fafc; }
        td { font-size: 14px; font-weight: 500; }
        tbody tr:hover { background: #f8fafc; }
        .success-msg { background: #dcfce3; color: #166534; padding: 10px; border-radius: 8px; margin-bottom: 20px; font-weight: 600; font-size: 14px; }
        .error-msg { background: #fee2e2; color: #991b1b; padding: 10px; border-radius: 8px; margin-bottom: 20px; font-weight: 600; font-size: 14px; }
        .badge-cat { background: #fef2f2; color: #b91c1c; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 600; }
    </style>
    <script>
        function toggleCustomCat(select) {
            var div = document.getElementById('customCatDiv');
            var input = document.getElementById('newCatInput');
            if (select.value === 'new') {
                div.style.display = 'flex';
                input.required = true;
            } else {
                div.style.display = 'none';
                input.required = false;
            }
        }
    </script>
</head>
<body>
    <?php require ROOT_PATH . '/app/Views/finance/layout/sidebar.php'; ?>

    <div class="main">
        <div class="header">
            <h1 style="font-size: 24px;">Expense Tracking</h1>
        </div>

        <?php if($message): ?>
            <div class="<?= strpos($message, 'successfully') !== false ? 'success-msg' : 'error-msg' ?>">
                <i class="ph <?= strpos($message, 'successfully') !== false ? 'ph-check-circle' : 'ph-warning-circle' ?>"></i> <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <div class="panel">
            <h3 style="margin-bottom: 20px;">Log New Expense</h3>
            <form method="POST">
                <input type="hidden" name="action" value="add_expense">
                <div class="form-row">
                    <div class="form-group">
                        <label>Expense Category</label>
                        <select name="category_id" required onchange="toggleCustomCat(this)">
                            <option value="">Select Predefined Category...</option>
                            <?php foreach($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['category_name']) ?></option>
                            <?php endforeach; ?>
                            <option value="new" style="font-weight:bold;color:#2563eb;">+ Create New Category</option>
                        </select>
                    </div>
                    <div class="form-group" id="customCatDiv" style="display:none;">
                        <label>New Category Name</label>
                        <input type="text" name="new_category_name" id="newCatInput" placeholder="e.g. Generator Fuel">
                    </div>
                    <div class="form-group">
                        <label>Amount (₦)</label>
                        <input type="number" step="0.01" name="amount" required placeholder="0.00">
                    </div>
                    <div class="form-group">
                        <label>Date Incurred</label>
                        <input type="date" name="transaction_date" value="<?= date('Y-m-d') ?>" required>
                    </div>
                </div>
                <div class="form-group" style="margin-bottom: 15px;">
                    <label>Description / Notes (Optional)</label>
                    <input type="text" name="description" placeholder="e.g. Purchase of diesel for the main generator">
                </div>
                <button type="submit" class="btn-danger"><i class="ph ph-minus-circle"></i> Record Expense</button>
            </form>
        </div>

        <div class="panel">
            <h3 style="margin-bottom: 20px;">Recent Expenses Recorded</h3>
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Category</th>
                        <th>Amount (₦)</th>
                        <th>Description</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recentExpenses)): ?>
                        <tr><td colspan="5" style="text-align: center; color: #94a3b8;">No expenses recorded yet.</td></tr>
                    <?php else: ?>
                        <?php foreach($recentExpenses as $exp): ?>
                            <tr>
                                <td><?= date('M d, Y', strtotime($exp['transaction_date'])) ?></td>
                                <td><span class="badge-cat"><?= htmlspecialchars($exp['category_name']) ?></span></td>
                                <td style="color:#ef4444; font-weight:700;">-<?= number_format($exp['amount'], 2) ?></td>
                                <td style="color: #64748b;"><?= htmlspecialchars($exp['description']) ?></td>
                                <td>
                                    <?php 
                                        $s = $exp['status'] ?? 'pending';
                                        $clr = '#64748b'; $bg = '#f1f5f9';
                                        if ($s === 'approved') { $clr = '#166534'; $bg = '#dcfce3'; }
                                        if ($s === 'rejected') { $clr = '#991b1b'; $bg = '#fee2e2'; }
                                    ?>
                                    <span style="background: <?= $bg ?>; color: <?= $clr ?>; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 700; text-transform: uppercase;"><?= $s ?></span>
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
