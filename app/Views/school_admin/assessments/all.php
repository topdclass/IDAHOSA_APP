<?php
// Assessment Framework Core Logic

try {
    
    // Auto Migration: Assessment Infrastructure
    $pdo->exec("CREATE TABLE IF NOT EXISTS assessment_groups (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        total_weight DECIMAL(5,2) DEFAULT 100.00,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS grade_assessments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        group_id INT,
        assessment_name VARCHAR(100) NOT NULL,
        max_marks DECIMAL(5,2) NOT NULL,
        pass_marks DECIMAL(5,2),
        weight DECIMAL(5,2) DEFAULT 1.00,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS student_marks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        assessment_id INT NOT NULL,
        marks_obtained DECIMAL(5,2),
        remarks TEXT,
        UNIQUE(student_id, assessment_id)
    )");

    // Seed default groups if empty
    if ($pdo->query("SELECT COUNT(*) FROM assessment_groups")->fetchColumn() == 0) {
        $pdo->exec("INSERT INTO assessment_groups (name, total_weight) VALUES 
            ('Continuous Assessment (CA)', 40.00), ('Main Examinations', 60.00), ('Internal Practical', 10.00)");
    }

    $message = '';

    // Handle Assessment Creation
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_assessment'])) {
        $name = $_POST['a_name'];
        $grid = $_POST['group_id'];
        $max = $_POST['max_marks'];
        $pass = $_POST['pass_marks'];
        $weight = $_POST['weight'] ?? 100;

        $stmt = $pdo->prepare("INSERT INTO grade_assessments (group_id, assessment_name, max_marks, pass_marks, weight) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$grid, $name, $max, $pass, $weight]);
        $message = "Assessment component '$name' defined successfully!";
    }

    // Fetch Groups and Assessments
    $groups = $pdo->query("SELECT * FROM assessment_groups")->fetchAll(PDO::FETCH_ASSOC);
    $all_assessments = $pdo->query("SELECT a.*, ag.name as group_name 
                                     FROM grade_assessments a 
                                     JOIN assessment_groups ag ON a.group_id = ag.id 
                                     ORDER BY ag.name, a.assessment_name")->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Assessment Logic Error: " . $e->getMessage());
}

$pageTitle = 'Assessment Management - Rosmon SMS';
require ROOT_PATH . '/app/Views/school_admin/layout/header.php';
require ROOT_PATH . '/app/Views/school_admin/layout/sidebar.php';
?>

<div class="main-container">
    <div class="top-header">
        <div class="greeting">Academics / <span style="color:var(--primary)">Grading &amp; Assessment Setup</span></div>
        <div class="header-actions">
            <button onclick="document.getElementById('a-modal').style.display='flex'" class="btn-primary"><i class="ph ph-plus-circle"></i> New Assessment Component</button>
        </div>
    </div>

    <div style="display:grid; grid-template-columns: 1fr 2fr; gap:24px;">
        
        <!-- Assessment Groups List (Left) -->
        <div class="crud-card">
            <div class="crud-header"><h2 class="crud-title">Assessment Groups</h2></div>
            <div style="padding:15px;">
                <?php foreach($groups as $g): ?>
                    <div style="display:flex; justify-content:space-between; align-items:center; padding:12px; border-bottom:1px solid #f3f4f6;">
                        <div style="font-weight:700; font-size:13px;"><?= htmlspecialchars((string)($g['group_name'] ?? '')) ?></div>
                        <div style="font-size:11px; background:var(--primary-light); color:var(--primary); padding:3px 8px; border-radius:12px; font-weight:800;"><?= $g['total_weight'] ?>%</div>
                    </div>
                <?php endforeach; ?>
                <button class="btn-primary" style="width:100%; margin-top:20px; background:#f3f4f6; color:var(--text-dark); font-size:11px;">Manage Groups</button>
            </div>
        </div>

        <!-- Master Assessment List (Right) -->
        <div class="crud-card">
            <div class="crud-header">
                <h2 class="crud-title">Defined Assessment Components</h2>
                <div style="font-size:11px; color:var(--primary);"><?= $message ?></div>
            </div>
            <table class="crud-table">
                <thead>
                    <tr>
                        <th>GROUP</th>
                        <th>NAME</th>
                        <th>MAX/PASS</th>
                        <th>WEIGHT</th>
                        <th>ACTION</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($all_assessments)): ?>
                        <tr><td colspan="5" style="text-align:center; padding:40px; color:var(--text-muted);">No assessment components defined.</td></tr>
                    <?php else: ?>
                        <?php foreach($all_assessments as $a): ?>
                            <tr>
                                <td style="font-size:11px; font-weight:800; color:var(--text-muted);"><?= htmlspecialchars((string)($a['group_name'] ?? '')) ?></td>
                                <td style="font-weight:700;"><?= htmlspecialchars((string)($a['assessment_name'] ?? '')) ?></td>
                                <td style="font-weight:700; color:#059669;"><?= $a['max_marks'] ?> / <?= $a['pass_marks'] ?: '-' ?></td>
                                <td style="font-weight:800;"><?= (float)$a['weight'] ?>%</td>
                                <td>
                                    <div style="display:flex; gap:10px;">
                                        <a href="#" style="color:var(--primary);"><i class="ph ph-pencil-simple"></i></a>
                                        <a href="#" style="color:#ef4444;"><i class="ph ph-trash"></i></a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Create Assessment Modal -->
    <div id="a-modal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:1001; align-items:center; justify-content:center;">
        <div class="crud-card" style="width:450px; padding:24px;">
            <div style="display:flex; justify-content:space-between; margin-bottom:20px;">
                <h3 style="margin:0;">Define Assessment Component</h3>
                <button onclick="document.getElementById('a-modal').style.display='none'" style="background:none; border:none; cursor:pointer;"><i class="ph ph-x" style="font-size:20px;"></i></button>
            </div>
            <form method="POST">
                <input type="hidden" name="create_assessment" value="1">
                
                <label style="display:block; font-size:12px; font-weight:700; margin-bottom:5px;">ASSESSMENT NAME</label>
                <input type="text" name="a_name" required placeholder="e.g. Mid-Term Test 1" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:8px; margin-bottom:15px;">

                <label style="display:block; font-size:12px; font-weight:700; margin-bottom:5px;">PARENT GROUP</label>
                <select name="group_id" required style="width:100%; padding:10px; border:1px solid #ddd; border-radius:8px; margin-bottom:15px;">
                    <?php foreach($groups as $g): ?>
                        <option value="<?= $g['id'] ?>"><?= htmlspecialchars((string)($g['group_name'] ?? '')) ?></option>
                    <?php endforeach; ?>
                </select>

                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px; margin-bottom:15px;">
                    <div>
                        <label style="display:block; font-size:12px; font-weight:700; margin-bottom:5px;">MAX MARKS</label>
                        <input type="number" step="0.5" name="max_marks" required style="width:100%; padding:10px; border:1px solid #ddd; border-radius:8px;">
                    </div>
                    <div>
                        <label style="display:block; font-size:12px; font-weight:700; margin-bottom:5px;">PASS MARKS</label>
                        <input type="number" step="0.5" name="pass_marks" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:8px;">
                    </div>
                </div>

                <label style="display:block; font-size:12px; font-weight:700; margin-bottom:5px;">WEIGHT IN GROUP (%)</label>
                <input type="number" step="0.5" name="weight" value="100" required style="width:100%; padding:10px; border:1px solid #ddd; border-radius:8px; margin-bottom:20px;">

                <button type="submit" class="btn-primary" style="width:100%; padding:12px; font-weight:800;"><i class="ph ph-check-square"></i> Save Definition</button>
            </form>
        </div>
    </div>
</div>

<?php require ROOT_PATH . '/app/Views/school_admin/layout/footer.php'; ?>
