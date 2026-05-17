<?php
require_once __DIR__ . '/../../Http/Controllers/ReportCardController.php';
$controller = new \App\Http\Controllers\ReportCardController();

// Mock request for the controller
$request = [
    'user' => [
        'id' => $_SESSION['user_id'],
        'role' => ['name' => ucfirst($_SESSION['role'] ?? 'Staff')]
    ]
];

// Normally the controller returns JSON, but we can capture it or call a dedicated method
// For simplicity in this core PHP setup, we'll fetch data using the controller's logic but direct DB if needed
// Or I can modify the controller to have a getData method.
// Let's just use the controller's findOne logic here for consistency.

$id = $_GET['id'] ?? null;
// We'll use a direct PDO fetch here to get data for the view easily
require_once __DIR__ . '/../../../config/database.php';

$stmt = $pdo->prepare("
    SELECT rc.*, s.student_no, u.full_name as student_name, c.class_name, ins.institution_name, ins.logo_url, ins.signature_url
    FROM report_cards rc
    JOIN institute_students s ON rc.student_id = s.student_id
    JOIN users u ON s.student_id = u.id
    JOIN classes c ON rc.class_id = c.id
    JOIN institution_profile ins ON rc.institute_id = ins.id
    WHERE rc.id = ?
");
$stmt->execute([$id]);
$data = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$data) {
    die("Report Card Not Found.");
}

// Fetch Grades
$gradeStmt = $pdo->prepare("
    SELECT sg.*, sub.subject_name 
    FROM subject_grades sg 
    JOIN subjects sub ON sg.subject_id = sub.id 
    WHERE sg.student_id = ? AND sg.class_id = ? AND sg.term = ?
");
$gradeStmt->execute([$data['student_id'], $data['class_id'], $data['term']]);
$grades = $gradeStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Traits
$traitStmt = $pdo->prepare("SELECT * FROM psycho_beh_analysis WHERE student_id = ? ORDER BY id DESC LIMIT 1");
$traitStmt->execute([$data['student_id']]);
$traits = $traitStmt->fetch(PDO::FETCH_ASSOC);

// Fetch Comments
$commentStmt = $pdo->prepare("SELECT * FROM report_card_comments WHERE student_id = ? AND class_id = ? AND term = ?");
$commentStmt->execute([$data['student_id'], $data['class_id'], $data['term']]);
$comments = $commentStmt->fetch(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report Card - <?= htmlspecialchars($data['student_name']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <style>
        :root {
            --primary: #1e293b;
            --secondary: #3b82f6;
            --accent: #f59e0b;
            --bg: #ffffff;
            --text: #1e293b;
            --muted: #64748b;
            --border: #e2e8f0;
        }

        * { box-sizing: border-box; -webkit-print-color-adjust: exact; }
        body { 
            font-family: 'Outfit', sans-serif; 
            background: #f1f5f9; 
            margin: 0; 
            padding: 40px; 
            color: var(--text);
            display: flex;
            justify-content: center;
        }

        .report-card {
            background: white;
            width: 100%;
            max-width: 900px;
            padding: 60px;
            border-radius: 0;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.1);
            position: relative;
            overflow: hidden;
        }

        /* Decorative Background */
        .report-card::before {
            content: '';
            position: absolute;
            top: 0; right: 0; width: 300px; height: 300px;
            background: radial-gradient(circle at top right, rgba(59, 130, 246, 0.05), transparent);
            z-index: 0;
        }

        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid var(--primary);
            padding-bottom: 30px;
            margin-bottom: 40px;
            position: relative;
            z-index: 1;
        }

        .school-info h1 {
            font-size: 28px;
            font-weight: 800;
            margin: 0;
            color: var(--primary);
            letter-spacing: -0.5px;
        }

        .school-logo {
            width: 80px;
            height: 80px;
            background: #f8fafc;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .school-logo img {
            max-width: 100%;
            height: auto;
        }

        .student-hero {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            margin-bottom: 50px;
        }

        .hero-label {
            font-size: 12px;
            text-transform: uppercase;
            font-weight: 600;
            color: var(--muted);
            letter-spacing: 1px;
            margin-bottom: 4px;
        }

        .hero-value {
            font-size: 18px;
            font-weight: 600;
            color: var(--primary);
        }

        /* Academic Performance Table */
        .section-title {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: var(--secondary);
            margin-bottom: 20px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 40px;
        }

        th {
            text-align: left;
            padding: 15px;
            background: #f8fafc;
            font-size: 12px;
            text-transform: uppercase;
            color: var(--muted);
            border-bottom: 1px solid var(--border);
        }

        td {
            padding: 15px;
            font-size: 14px;
            font-weight: 500;
            border-bottom: 1px solid #f1f5f9;
        }

        .grade-badge {
            background: #eff6ff;
            color: #1d4ed8;
            padding: 4px 10px;
            border-radius: 6px;
            font-weight: 700;
            font-size: 12px;
        }

        /* Behavioral Analysis */
        .trait-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 40px;
        }

        .trait-card {
            background: #f8fafc;
            padding: 15px;
            border-radius: 12px;
            border: 1px solid var(--border);
        }

        .trait-name {
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 10px;
            color: var(--muted);
        }

        .trait-stars {
            color: #f59e0b;
            display: flex;
            gap: 4px;
        }

        /* Comments & Signatures */
        .footer-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            margin-top: 50px;
        }

        .comment-box {
            background: #fffbeb;
            border-left: 4px solid #f59e0b;
            padding: 20px;
            border-radius: 0 12px 12px 0;
        }

        .signature-box {
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-end;
        }

        .signature-line {
            width: 200px;
            border-top: 1px solid var(--muted);
            margin-top: 10px;
            padding-top: 5px;
            font-size: 12px;
            color: var(--muted);
        }

        .signature-img {
            max-height: 60px;
            margin-bottom: 5px;
            mix-blend-mode: multiply;
        }

        @media print {
            body { background: white; padding: 0; }
            .report-card { box-shadow: none; border: none; padding: 0; max-width: 100%; }
            .no-print { display: none; }
        }

        .print-btn {
            position: fixed;
            bottom: 40px;
            right: 40px;
            background: var(--secondary);
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 30px;
            font-weight: 700;
            cursor: pointer;
            box-shadow: 0 10px 15px -3px rgba(59, 130, 246, 0.5);
            display: flex;
            align-items: center;
            gap: 10px;
            transition: 0.3s;
            z-index: 100;
        }

        .print-btn:hover { transform: translateY(-2px); box-shadow: 0 20px 25px -5px rgba(59, 130, 246, 0.4); }
    </style>
</head>
<body>

    <button class="print-btn no-print" onclick="window.print()">
        <i class="ph ph-printer"></i> PRINT REPORT CARD
    </button>

    <div class="report-card">
        <header>
            <div class="school-info">
                <h1><?= htmlspecialchars($data['institution_name']) ?></h1>
                <p style="color: var(--muted); font-size: 14px; margin-top: 5px;">Academic Session: <?= htmlspecialchars($data['academic_year'] ?? '2025/2026') ?> | <?= htmlspecialchars($data['term'] ?? 'First Term') ?></p>
            </div>
            <div class="school-logo">
                <?php if($data['logo_url']): ?>
                    <img src="<?= htmlspecialchars($data['logo_url']) ?>" alt="Logo">
                <?php else: ?>
                    <i class="ph ph-graduation-cap" style="font-size: 40px; color: var(--secondary);"></i>
                <?php endif; ?>
            </div>
        </header>

        <div class="student-hero">
            <div>
                <div class="hero-label">Student Name</div>
                <div class="hero-value"><?= htmlspecialchars($data['student_name']) ?></div>
            </div>
            <div>
                <div class="hero-label">Admission Number</div>
                <div class="hero-value"><?= htmlspecialchars($data['student_no']) ?></div>
            </div>
            <div>
                <div class="hero-label">Class / Level</div>
                <div class="hero-value"><?= htmlspecialchars($data['class_name']) ?></div>
            </div>
            <div>
                <div class="hero-label">Result Status</div>
                <div class="hero-value" style="color: #10b981;">Official Approved</div>
            </div>
        </div>

        <div class="section-title"><i class="ph ph-scroll"></i> Academic Performance</div>
        <table>
            <thead>
                <tr>
                    <th>Subject</th>
                    <th>Obj Score</th>
                    <th>Theory</th>
                    <th>Total</th>
                    <th>Grade</th>
                    <th>Remarks</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($grades as $g): ?>
                <tr>
                    <td><?= htmlspecialchars($g['subject_name']) ?></td>
                    <td><?= $g['objective_score'] ?></td>
                    <td><?= $g['theory_score'] ?></td>
                    <td><strong style="color: var(--primary);"><?= $g['total_score'] ?></strong></td>
                    <td><span class="grade-badge"><?= $g['grade'] ?></span></td>
                    <td style="font-size: 12px; color: var(--muted);"><?= $g['remarks'] ?? ($g['total_score'] >= 50 ? 'Satisfactory' : 'Needs Improvement') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if($traits): ?>
        <div class="section-title"><i class="ph ph-brain"></i> Psycho-Behavioral Traits</div>
        <div class="trait-grid">
            <?php 
            $traitMap = [
                'Discipline' => $traits['discipline'],
                'Neatness' => $traits['neatness'],
                'Politeness' => $traits['politeness'],
                'Self Control' => $traits['self_control'],
                'Human Relations' => $traits['relationship_with_others']
            ];
            foreach($traitMap as $label => $val): ?>
            <div class="trait-card">
                <div class="trait-name"><?= $label ?></div>
                <div class="trait-stars">
                    <?php for($i=1; $i<=5; $i++): ?>
                        <i class="ph-fill ph-star" style="opacity: <?= $i <= $val ? '1' : '0.2' ?>;"></i>
                    <?php endfor; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="footer-grid">
            <div>
                <div class="section-title"><i class="ph ph-chat-text"></i> Teacher's Remark</div>
                <div class="comment-box">
                    <p style="margin: 0; font-size: 14px; font-style: italic; color: #92400e;">
                        "<?= htmlspecialchars($comments['class_teacher_comment'] ?? 'No comment provided.') ?>"
                    </p>
                    <div style="margin-top: 15px; text-align: right;">
                        <div class="signature-box">
                            <span style="font-size: 12px; font-weight: 600;">Class Teacher</span>
                        </div>
                    </div>
                </div>
            </div>
            <div>
                <div class="section-title"><i class="ph ph-seal-check"></i> Principal's Review</div>
                <div class="comment-box" style="background: #e0f2fe; border-left-color: #0ea5e9;">
                    <p style="margin: 0; font-size: 14px; font-style: italic; color: #075985;">
                        "<?= htmlspecialchars($comments['principal_comment'] ?? 'Signed and Approved.') ?>"
                    </p>
                    <div style="margin-top: 15px; text-align: right;">
                        <div class="signature-box">
                            <?php if($data['signature_url']): ?>
                                <img src="<?= htmlspecialchars($data['signature_url']) ?>" alt="Signature" class="signature-img">
                            <?php endif; ?>
                            <div class="signature-line">Principal / Head of School</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div style="margin-top: 60px; text-align: center; font-size: 11px; color: var(--muted); border-top: 1px dashed var(--border); padding-top: 20px;">
            This is a computer-generated report card from Rosmon SMS. No unauthorized alterations permitted.
        </div>
    </div>

</body>
</html>
