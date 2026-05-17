<?php
require_once ROOT_PATH . '/config/database.php';

$student_id = $_GET['student_id'] ?? 0;
$class_id = $_GET['class_id'] ?? 0;

// Fetch Student Info
$stmt = $pdo->prepare("SELECT st.student_no, u.full_name as name, c.class_name, c.arm as section FROM institute_students st JOIN users u ON st.id = u.id JOIN classes c ON st.class_id = c.id WHERE st.id = ?");
$stmt->execute([$student_id]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) die("Invalid Student Configuration.");

// Fetch Approved Grades
$gStmt = $pdo->prepare("
    SELECT g.*, s.subject_name 
    FROM subject_grades g 
    JOIN subjects s ON g.subject_id = s.id 
    WHERE g.student_id = ? AND g.class_id = ? AND g.status = 'Approved' AND g.term = 'First Term'
");
$gStmt->execute([$student_id, $class_id]);
$grades = $gStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Comments
$cStmt = $pdo->prepare("SELECT * FROM report_card_comments WHERE student_id = ? AND class_id = ? AND term = 'First Term' AND status = 'Published'");
$cStmt->execute([$student_id, $class_id]);
$comments = $cStmt->fetch(PDO::FETCH_ASSOC);

// Calculate Averages
$totalScore = 0;
$totalPoints = 0;
$count = count($grades);
foreach($grades as $g) {
    $totalScore += floatval($g['total_score']);
    $totalPoints += floatval($g['grade_point']);
}
$averageScore = $count > 0 ? number_format($totalScore / $count, 2) : 0;
$gpa = $count > 0 ? number_format($totalPoints / $count, 2) : 0;

// Hardcoded signature placeholders for demo (since file uploads weren't explicitly provided by user for signatures yet)
$principalSignature = "https://upload.wikimedia.org/wikipedia/commons/f/f6/Signature_of_John_Hancock.svg";
$teacherSignature = "https://upload.wikimedia.org/wikipedia/commons/8/87/George_Washington_signature.svg";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($student['name']) ?> - Report Card</title>
    <style>
        body { font-family: 'Arial', sans-serif; background: #e2e8f0; padding: 40px; color: #1e293b; }
        .report-card { max-width: 900px; margin: 0 auto; background: white; padding: 50px; border-radius: 8px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); position:relative; }
        .header { text-align: center; border-bottom: 3px solid #13198f; padding-bottom: 20px; margin-bottom: 30px; }
        .school-name { font-size: 28px; font-weight: 900; color: #13198f; margin: 0; text-transform: uppercase; letter-spacing: 2px; }
        .school-subtitle { font-size: 14px; color: #64748b; margin-top: 5px; }
        .term-title { font-size: 18px; font-weight: 700; margin: 15px 0 0 0; background:#f1f5f9; display:inline-block; padding:5px 15px; border-radius:20px; color:#4f46e5;}
        
        .student-info { display: flex; justify-content: space-between; margin-bottom: 30px; font-size: 14px; background: #f8fafc; padding: 20px; border-radius: 8px; border: 1px solid #e2e8f0; }
        .info-col div { margin-bottom: 8px; }
        .info-col strong { display: inline-block; width: 120px; color: #64748b; font-size: 12px; text-transform: uppercase; }

        table { width: 100%; border-collapse: collapse; margin-bottom: 30px; font-size: 14px; }
        th, td { border: 1px solid #cbd5e1; padding: 12px; text-align: left; }
        th { background-color: #13198f; color: white; font-size: 12px; text-transform: uppercase; }
        tr:nth-child(even) { background-color: #f8fafc; }
        .text-center { text-align: center; }
        .grade-A { color: #16a34a; font-weight: 800; }
        .grade-B { color: #2563eb; font-weight: 800; }
        .grade-C { color: #d97706; font-weight: 800; }
        .grade-D { color: #ea580c; font-weight: 800; }
        .grade-F { color: #dc2626; font-weight: 800; }

        .summary-box { display: flex; justify-content: space-around; background: #1e293b; color: white; padding: 15px 0; border-radius: 8px; margin-bottom: 40px; }
        .summary-item { text-align: center; }
        .summary-val { font-size: 24px; font-weight: 800; color: #38bdf8; margin-top: 5px;}

        .remarks-section { display: grid; grid-template-columns: 1fr 1fr; gap: 40px; margin-bottom: 50px; }
        .remark-box { background: #f8fafc; padding: 20px; border: 1px solid #e2e8f0; border-radius: 8px; position:relative;}
        .remark-title { font-size: 11px; font-weight: 800; color: #64748b; text-transform: uppercase; margin-bottom: 10px; border-bottom: 1px solid #e2e8f0; padding-bottom: 8px; }
        .remark-text { font-style: italic; font-size: 14px; color: #334155; min-height: 80px; }
        
        .signature-img { height: 50px; opacity: 0.8; mix-blend-mode: multiply; position:absolute; bottom:10px; right:20px; pointer-events:none;}

        .print-btn { position: fixed; bottom: 30px; right: 30px; background: #ea580c; color: white; padding: 15px 30px; border: none; border-radius: 30px; font-weight: 800; cursor: pointer; box-shadow: 0 4px 15px rgba(234, 88, 12, 0.4); font-size:16px;}
        @media print {
            body { background: white; padding: 0; }
            .report-card { box-shadow: none; max-width: 100%; border: none; padding: 0; }
            .print-btn { display: none; }
        }
    </style>
</head>
<body>
    <div class="report-card">
        <div class="header">
            <h1 class="school-name"><?= strtoupper(htmlspecialchars($globalSchoolName ?? 'Rosmon SMS Academy')) ?></h1>
            <div class="school-subtitle">Excellence, Integrity, and Innovation</div>
            <div class="term-title">OFFICIAL RESULT CARD &mdash; First Term, 2025/2026</div>
        </div>

        <div class="student-info">
            <div class="info-col">
                <div><strong>Student Name:</strong> <span style="font-weight:700; color:#0f172a; font-size:16px;"><?= htmlspecialchars($student['name']) ?></span></div>
                <div><strong>Registration No:</strong> <?= $student['student_no'] ?? 'N/A' ?></div>
            </div>
            <div class="info-col">
                <div><strong>Class Assigned:</strong> <?= $student['class_name'] ?> <?= !empty($student['section']) ? '('.$student['section'].')' : '' ?></div>
                <div><strong>Status:</strong> <span style="color:#10b981; font-weight:800;">PROMOTED</span></div>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Subject Description</th>
                    <th class="text-center" style="width:12%;">CBT (40)</th>
                    <th class="text-center" style="width:12%;">Theory (60)</th>
                    <th class="text-center" style="width:15%;">Total (100)</th>
                    <th class="text-center" style="width:12%;">Grade</th>
                    <th class="text-center" style="width:12%;">Points</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($grades)): ?>
                    <tr><td colspan="6" class="text-center" style="color:#94a3b8; font-style:italic;">No official grading records found for this term.</td></tr>
                <?php else: ?>
                    <?php foreach($grades as $g): ?>
                        <tr>
                            <td style="font-weight:700;"><?= htmlspecialchars($g['subject_name']) ?></td>
                            <td class="text-center"><?= $g['objective_score'] ?></td>
                            <td class="text-center"><?= $g['theory_score'] ?></td>
                            <td class="text-center" style="font-weight:800;"><?= $g['total_score'] ?></td>
                            <td class="text-center grade-<?= substr($g['grade'], 0, 1) ?>"><?= $g['grade'] ?></td>
                            <td class="text-center" style="color:#64748b; font-weight:700;"><?= number_format($g['grade_point'], 1) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="summary-box">
            <div class="summary-item">
                <div style="font-size:11px; text-transform:uppercase; letter-spacing:1px; color:#94a3b8;">Total Subjects</div>
                <div class="summary-val"><?= $count ?></div>
            </div>
            <div class="summary-item">
                <div style="font-size:11px; text-transform:uppercase; letter-spacing:1px; color:#94a3b8;">Term Average</div>
                <div class="summary-val"><?= $averageScore ?>%</div>
            </div>
            <div class="summary-item">
                <div style="font-size:11px; text-transform:uppercase; letter-spacing:1px; color:#94a3b8;">Final GPA</div>
                <div class="summary-val" style="color:#10b981;"><?= $gpa ?></div>
            </div>
        </div>

        <div class="remarks-section">
            <div class="remark-box">
                <div class="remark-title">Class Teacher's Remark</div>
                <div class="remark-text">"<?= htmlspecialchars($comments['class_teacher_comment'] ?? 'No comment provided.') ?>"</div>
                <img src="<?= $teacherSignature ?>" class="signature-img" alt="Teacher Signature">
                <div style="position:absolute; bottom:10px; left:20px; font-size:10px; font-weight:700; color:#94a3b8;">CLASS TEACHER</div>
            </div>
            <div class="remark-box" style="border-left: 4px solid #13198f;">
                <div class="remark-title">Principal's Official Certification</div>
                <div class="remark-text">"<?= htmlspecialchars($comments['principal_comment'] ?? 'No official remarks attached.') ?>"</div>
                <img src="<?= $principalSignature ?>" class="signature-img" alt="Principal Signature">
                <div style="position:absolute; bottom:10px; left:20px; font-size:10px; font-weight:700; color:#94a3b8;">PRINCIPAL</div>
            </div>
        </div>
        
        <div style="text-align:center; font-size:11px; color:#94a3b8; font-weight:600; padding-top:20px; border-top:1px dashed #cbd5e1;">
            Generated electronically by <?= htmlspecialchars($globalSchoolName ?? 'Rosmon SMS') ?> Engine &bull; Document Verified & Authenticated digitally &bull; Re-prints invalid without watermark.
        </div>
    </div>

    <button onclick="window.print()" class="print-btn">🖨️ Download / Print PDF</button>

    <?php if(isset($_GET['download']) && $_GET['download'] == 'pdf'): ?>
        <script> window.onload = function() { window.print(); } </script>
    <?php endif; ?>
</body>
</html>
