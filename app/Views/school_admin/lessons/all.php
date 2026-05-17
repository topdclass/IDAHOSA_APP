<?php
$pageTitle = 'Lessons - Rosmon SMS';
require ROOT_PATH . '/app/Views/school_admin/layout/header.php';
require ROOT_PATH . '/app/Views/school_admin/layout/sidebar.php';
?>
<div class="main-container">
    <div class="top-header">
        <div class="greeting">Academics / <span style="color:var(--primary)">Lesson Planning</span></div>
    </div>
    <div class="crud-card" style="text-align:center; padding:100px 20px;">
        <i class="ph ph-chalkboard" style="font-size:64px; color:var(--primary); margin-bottom:20px;"></i>
        <h2 style="margin-bottom:10px;">Lesson Module</h2>
        <p style="color:var(--text-muted); max-width:500px; margin:0 auto;">The digital lesson planning and tracking module is currently being calibrated. You will soon be able to upload curricula and track syllabus coverage here.</p>
        <button onclick="history.back()" class="btn-primary" style="margin-top:30px;">Go Back</button>
    </div>
</div>
<?php require ROOT_PATH . '/app/Views/school_admin/layout/footer.php'; ?>
