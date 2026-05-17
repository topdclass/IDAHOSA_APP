<?php
$pageTitle = 'My Classes & Subjects - Rosmon SMS';
require ROOT_PATH . '/app/Views/employee/layout/header.php';

// Authentication is already handled by public/index.php
$teacher_id = $_SESSION['user_id'] ?? 0;

try {
    // 1. Fetch Subjects assigned to this teacher
    $stmt = $pdo->prepare("
        SELECT cs.id as class_subject_id, c.id as class_id, c.class_name, c.arm, s.subject_name, s.subject_code,
               (SELECT COUNT(id) FROM institute_students WHERE class_id = c.id AND is_deleted = 0) as student_count
        FROM class_subjects cs
        JOIN classes c ON cs.class_id = c.id
        JOIN subjects s ON cs.subject_id = s.id
        WHERE cs.teacher_id = :tid AND cs.is_deleted = 0
        ORDER BY c.class_name ASC, s.subject_name ASC
    ");
    $stmt->execute([':tid' => $teacher_id]);
    $assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Fetch Form Teacher (Class Teacher) roles
    $stmt = $pdo->prepare("
        SELECT c.*, 
               (SELECT COUNT(id) FROM institute_students WHERE class_id = c.id AND is_deleted = 0) as student_count
        FROM classes c
        WHERE c.teacher_id = :tid AND c.is_deleted = 0
    ");
    $stmt->execute([':tid' => $teacher_id]);
    $form_classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    echo "Database Error: " . $e->getMessage();
}
?>

<style>
    .classes-container {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
        gap: 25px;
        margin-top: 30px;
    }

    .subject-card {
        background: white;
        border-radius: 16px;
        padding: 24px;
        border: 1px solid #f1f5f9;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -2px rgba(0, 0, 0, 0.05);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        position: relative;
        overflow: hidden;
    }

    .subject-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
        border-color: var(--primary-light);
    }

    .card-icon {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
        margin-bottom: 20px;
    }

    .icon-subject { background: #eef2ff; color: #4f46e5; }
    .icon-form { background: #ecfdf5; color: #10b981; }

    .card-class {
        font-size: 11px;
        font-weight: 800;
        text-transform: uppercase;
        color: var(--text-muted);
        letter-spacing: 1px;
        margin-bottom: 4px;
    }

    .card-title {
        font-size: 18px;
        font-weight: 800;
        color: var(--text);
        margin-bottom: 15px;
        line-height: 1.2;
    }

    .card-stats {
        display: flex;
        gap: 15px;
        margin-bottom: 20px;
        padding-top: 15px;
        border-top: 1px dashed #e2e8f0;
    }

    .stat-item {
        display: flex;
        flex-direction: column;
    }

    .stat-value {
        font-size: 16px;
        font-weight: 700;
        color: var(--text);
    }

    .stat-label {
        font-size: 10px;
        font-weight: 600;
        color: var(--text-muted);
        text-transform: uppercase;
    }

    .action-btn {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 10px;
        border-radius: 10px;
        font-size: 13px;
        font-weight: 700;
        text-decoration: none;
        transition: all 0.2s;
        border: none;
        cursor: pointer;
        width: 100%;
    }

    .btn-view { background: var(--primary); color: white; }
    .btn-view:hover { background: var(--primary-dark); }

    .badge-form {
        position: absolute;
        top: 20px;
        right: -30px;
        background: #10b981;
        color: white;
        padding: 5px 35px;
        font-size: 10px;
        font-weight: 800;
        transform: rotate(45deg);
        box-shadow: 0 2px 4px rgba(16, 185, 129, 0.2);
    }

    .empty-state {
        text-align: center;
        padding: 60px 20px;
        background: white;
        border-radius: 16px;
        border: 1px dashed #e2e8f0;
        margin-top: 20px;
    }

    .page-header {
        margin-bottom: 30px;
    }

    .page-title {
        font-size: 24px;
        font-weight: 900;
        color: #111827;
        margin-bottom: 10px;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .page-subtitle {
        color: #6b7280;
        font-size: 14px;
        max-width: 600px;
        line-height: 1.6;
    }
</style>

<div class="page-header">
    <h1 class="page-title">
        <i class="ph-fill ph-books" style="color:var(--primary)"></i>
        My Academic Assignments
    </h1>
    <p class="page-subtitle">Welcome back! Manage your assigned classes, track student progress, and access curriculum tools for the current term.</p>
</div>

<?php if (empty($assignments) && empty($form_classes)): ?>
    <div class="empty-state">
        <i class="ph ph-mask-sad" style="font-size: 64px; color: #cbd5e1; margin-bottom: 20px; display: inline-block;"></i>
        <h3 style="margin: 0 0 10px 0; color: #1e293b;">No Assignments Found</h3>
        <p style="margin: 0; color: #64748b; font-size: 14px;">You haven't been assigned to any classes or subjects yet. <br>Please contact the school administrator for assistance.</p>
    </div>
<?php else: ?>

    <!-- Form Teacher Section -->
    <?php if (!empty($form_classes)): ?>
        <h2 style="font-size: 16px; font-weight: 800; color: var(--text); margin: 40px 0 20px 0; display:flex; align-items:center; gap:10px;">
            <i class="ph ph-users-three" style="color:#10b981;"></i> Form Teacher Roles
        </h2>
        <div class="classes-container" style="margin-top:0;">
            <?php foreach($form_classes as $fc): ?>
                <div class="subject-card">
                    <div class="badge-form">FORM TEACHER</div>
                    <div class="card-icon icon-form">
                        <i class="ph-fill ph-identification-card"></i>
                    </div>
                    <div class="card-class">Class Overseer</div>
                    <div class="card-title"><?= htmlspecialchars($fc['class_name']) ?> <?= htmlspecialchars($fc['arm']) ?></div>
                    
                    <div class="card-stats">
                        <div class="stat-item">
                            <span class="stat-value"><?= number_format($fc['student_count']) ?></span>
                            <span class="stat-label">Students</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-value"><?= htmlspecialchars($fc['numeric_value'] ?? '1') ?></span>
                            <span class="stat-label">Level</span>
                        </div>
                    </div>

                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px;">
                        <a href="<?= WEB_ROOT ?>/employee/attendance?class_id=<?= $fc['id'] ?>" class="action-btn" style="background:#f0fdf4; color:#16a34a;">
                            <i class="ph-fill ph-calendar-check"></i> Attendance
                        </a>
                        <a href="<?= WEB_ROOT ?>/employee/grading?class_id=<?= $fc['id'] ?>&tab=comments" class="action-btn" style="background:#fff7ed; color:#ea580c;">
                            <i class="ph-fill ph-chat-centered-text"></i> Remarks
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Subject Teacher Section -->
    <h2 style="font-size: 16px; font-weight: 800; color: var(--text); margin: 40px 0 20px 0; display:flex; align-items:center; gap:10px;">
        <i class="ph ph-chalkboard-teacher" style="color:var(--primary);"></i> Subject Specializations
    </h2>
    <div class="classes-container" style="margin-top:0;">
        <?php foreach($assignments as $a): ?>
            <div class="subject-card">
                <div class="card-icon icon-subject">
                    <i class="ph-fill ph-book-open-text"></i>
                </div>
                <div class="card-class"><?= htmlspecialchars($a['class_name']) ?> &bull; Section <?= htmlspecialchars($a['arm']) ?></div>
                <div class="card-title"><?= htmlspecialchars($a['subject_name']) ?></div>
                
                <div class="card-stats">
                    <div class="stat-item">
                        <span class="stat-value"><?= number_format($a['student_count']) ?></span>
                        <span class="stat-label">Students</span>
                    </div>
                    <?php if(!empty($a['subject_code'])): ?>
                    <div class="stat-item">
                        <span class="stat-value"><?= htmlspecialchars($a['subject_code']) ?></span>
                        <span class="stat-label">Code</span>
                    </div>
                    <?php endif; ?>
                </div>

                <div style="display:flex; flex-direction:column; gap:10px;">
                    <a href="<?= WEB_ROOT ?>/employee/grading?class_id=<?= $a['class_id'] ?>&subject_id=<?= $a['class_subject_id'] ?>" class="action-btn btn-view">
                        Manage Scores & Grading
                    </a>
                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px;">
                        <a href="<?= WEB_ROOT ?>/employee/lesson-plans?class_id=<?= $a['class_id'] ?>&subject_id=<?= $a['class_subject_id'] ?>" class="action-btn" style="background:#f8fafc; border:1px solid #e2e8f0; color:#475569;">
                            <i class="ph ph-notebook"></i> Plan
                        </a>
                        <a href="<?= WEB_ROOT ?>/employee/timetable" class="action-btn" style="background:#f8fafc; border:1px solid #e2e8f0; color:#475569;">
                            <i class="ph ph-calendar"></i> Schedule
                        </a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

<?php endif; ?>

<?php require ROOT_PATH . '/app/Views/employee/layout/footer.php'; ?>
