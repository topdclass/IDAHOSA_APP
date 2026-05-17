<?php
/**
 * Bulk Upload Dashboard — School Admin
 * Upload all school data at once via CSV templates
 */
require_once dirname(dirname(dirname(dirname(dirname(__DIR__))))) . '/config/database.php';

$uploadErrors   = [];
$uploadSuccess  = [];
$uploadStats    = [];

// ── Handle CSV upload ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_type'])) {
    $type = $_POST['upload_type'];
    $file = $_FILES['csv_file'] ?? null;

    if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
        $uploadErrors[] = "No file uploaded or upload error occurred.";
    } elseif (strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) !== 'csv') {
        $uploadErrors[] = "Only CSV files are accepted.";
    } else {
        require_once ROOT_PATH . '/app/Views/school_admin/bulk_upload/processor.php';
        $processor = new BulkUploadProcessor($pdo, $instituteId);
        $result = $processor->process($type, $file['tmp_name']);
        if ($result['success']) {
            $uploadSuccess[] = $result['message'];
            $uploadStats = $result['stats'] ?? [];
        } else {
            $uploadErrors = array_merge($uploadErrors, (array)$result['errors']);
        }
    }
}

// ── Stats ─────────────────────────────────────────────────────────────
try {
    $stats = [
        'employees' => $pdo->query("SELECT COUNT(*) FROM institute_employees WHERE institute_id={$instituteId} AND is_deleted=0")->fetchColumn(),
        'students'  => $pdo->query("SELECT COUNT(*) FROM institute_students WHERE institute_id={$instituteId} AND is_deleted=0")->fetchColumn(),
        'parents'   => $pdo->query("SELECT COUNT(*) FROM institute_parents WHERE institute_id={$instituteId} AND is_deleted=0")->fetchColumn(),
        'classes'   => $pdo->query("SELECT COUNT(*) FROM classes WHERE institute_id={$instituteId} AND is_deleted=0")->fetchColumn(),
        'subjects'  => $pdo->query("SELECT COUNT(*) FROM subjects WHERE institute_id={$instituteId} AND is_deleted=0")->fetchColumn(),
    ];
} catch (PDOException $e) {
    $stats = array_fill_keys(['employees','students','parents','classes','subjects'], 0);
}

$pageTitle = 'Bulk Data Upload — RosmonSMS';
require APP_PATH . '/Views/school_admin/layout/header.php';
require APP_PATH . '/Views/school_admin/layout/sidebar.php';
?>

<div class="main-container">
  <div class="top-header">
    <div class="greeting">Bulk Upload / <span style="color:var(--primary)">School Data Importer</span></div>
    <div class="header-actions">
      <a href="<?= WEB_ROOT ?>/school-admin/bulk-upload/templates" class="btn-primary" style="text-decoration:none;background:#8b5cf6;">
        <i class="ph ph-file-csv"></i> Download CSV Templates
      </a>
    </div>
  </div>

  <!-- Alerts -->
  <?php foreach ($uploadErrors as $err): ?>
  <div style="background:#fef2f2;border:1px solid #fecaca;border-radius:8px;padding:12px 16px;margin-bottom:12px;color:#dc2626;font-size:14px;">
    &#9888; <?= htmlspecialchars($err) ?>
  </div>
  <?php endforeach; ?>
  <?php foreach ($uploadSuccess as $msg): ?>
  <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:12px 16px;margin-bottom:12px;color:#16a34a;font-size:14px;">
    &#10003; <?= htmlspecialchars($msg) ?>
    <?php if (!empty($uploadStats)): ?>
      <br><small><?php foreach ($uploadStats as $k=>$v) echo ucfirst($k).": {$v} &nbsp; "; ?></small>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>

  <!-- Current Data Stats -->
  <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:16px;margin-bottom:28px;">
    <?php
    $statItems = [
      ['label'=>'Employees','icon'=>'ph-users','count'=>$stats['employees'],'color'=>'#2563eb'],
      ['label'=>'Students','icon'=>'ph-student','count'=>$stats['students'],'color'=>'#7c3aed'],
      ['label'=>'Parents','icon'=>'ph-users-three','count'=>$stats['parents'],'color'=>'#0891b2'],
      ['label'=>'Classes','icon'=>'ph-chalkboard-teacher','count'=>$stats['classes'],'color'=>'#059669'],
      ['label'=>'Subjects','icon'=>'ph-book-open','count'=>$stats['subjects'],'color'=>'#d97706'],
    ];
    foreach ($statItems as $s):
    ?>
    <div class="crud-card" style="padding:20px;text-align:center;">
      <div style="font-size:28px;color:<?= $s['color'] ?>;margin-bottom:6px;"><i class="ph <?= $s['icon'] ?>"></i></div>
      <div style="font-size:26px;font-weight:800;color:#1e293b;"><?= number_format($s['count']) ?></div>
      <div style="font-size:12px;color:#64748b;font-weight:600;"><?= $s['label'] ?></div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- How to use -->
  <div class="crud-card" style="margin-bottom:24px;padding:24px;background:linear-gradient(135deg,#eff6ff,#f8faff);">
    <h3 style="font-size:16px;margin-bottom:12px;color:#1e293b;"><i class="ph ph-info" style="color:#2563eb;"></i> How to Bulk Upload Your School Data</h3>
    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:16px;font-size:13px;color:#374151;">
      <div style="display:flex;gap:10px;align-items:flex-start;">
        <div style="width:24px;height:24px;background:#2563eb;color:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;flex-shrink:0;">1</div>
        <div>Download the CSV template for the data you want to upload</div>
      </div>
      <div style="display:flex;gap:10px;align-items:flex-start;">
        <div style="width:24px;height:24px;background:#7c3aed;color:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;flex-shrink:0;">2</div>
        <div>Fill in all the required columns in Excel or Google Sheets</div>
      </div>
      <div style="display:flex;gap:10px;align-items:flex-start;">
        <div style="width:24px;height:24px;background:#059669;color:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;flex-shrink:0;">3</div>
        <div>Save as CSV (UTF-8) and upload using the form below</div>
      </div>
      <div style="display:flex;gap:10px;align-items:flex-start;">
        <div style="width:24px;height:24px;background:#d97706;color:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;flex-shrink:0;">4</div>
        <div>Review the import summary and check for errors</div>
      </div>
    </div>
    <div style="margin-top:16px;padding:12px;background:#fff;border-radius:8px;border:1px solid #bfdbfe;font-size:13px;color:#1d4ed8;">
      <strong>&#128161; Recommended Order:</strong> Upload Classes &rarr; Subjects &rarr; Employees &rarr; Students &rarr; Parents &rarr; Subject Assignments
    </div>
  </div>

  <!-- Upload Sections -->
  <?php
  $uploadTypes = [
    [
      'id'      => 'classes',
      'label'   => 'Classes / Sections',
      'icon'    => 'ph-chalkboard-teacher',
      'color'   => '#059669',
      'desc'    => 'Import all class names, sections, and assign class teachers.',
      'fields'  => 'class_name, section, capacity, class_teacher_name (optional)',
      'order'   => 1,
    ],
    [
      'id'      => 'subjects',
      'label'   => 'Subjects',
      'icon'    => 'ph-book-open',
      'color'   => '#d97706',
      'desc'    => 'Import all subjects with their codes and assigned class.',
      'fields'  => 'subject_name, subject_code, class_name (optional)',
      'order'   => 2,
    ],
    [
      'id'      => 'employees',
      'label'   => 'Employees / Staff',
      'icon'    => 'ph-users',
      'color'   => '#2563eb',
      'desc'    => 'Import all staff: teachers, admin, support with their roles and assignments.',
      'fields'  => 'full_name, email, phone, role, department, designation, employee_no, salary, hire_date, gender, class_assigned (for teachers)',
      'order'   => 3,
    ],
    [
      'id'      => 'students',
      'label'   => 'Students',
      'icon'    => 'ph-student',
      'color'   => '#7c3aed',
      'desc'    => 'Import all students with their class, admission details, and personal info.',
      'fields'  => 'full_name, admission_no, class_name, section, gender, dob, religion, blood_group, address, phone, email, admission_date',
      'order'   => 4,
    ],
    [
      'id'      => 'parents',
      'label'   => 'Parents / Guardians',
      'icon'    => 'ph-users-three',
      'color'   => '#0891b2',
      'desc'    => 'Import parent/guardian records linked to students.',
      'fields'  => "father_name, mother_name, guardian_name, phone, email, address, student_admission_no (to link)",
      'order'   => 5,
    ],
    [
      'id'      => 'subject_assignments',
      'label'   => 'Subject Assignments',
      'icon'    => 'ph-link',
      'color'   => '#be185d',
      'desc'    => 'Assign subjects to classes and link teachers to subjects.',
      'fields'  => 'class_name, subject_name, teacher_email',
      'order'   => 6,
    ],
  ];
  ?>

  <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:20px;">
  <?php foreach ($uploadTypes as $ut): ?>
  <div class="crud-card" style="padding:24px;">
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:14px;">
      <div style="width:44px;height:44px;background:<?= $ut['color'] ?>1a;color:<?= $ut['color'] ?>;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:22px;">
        <i class="ph <?= $ut['icon'] ?>"></i>
      </div>
      <div>
        <div style="font-weight:700;font-size:15px;color:#1e293b;">
          <span style="background:<?= $ut['color'] ?>;color:#fff;border-radius:50%;width:20px;height:20px;display:inline-flex;align-items:center;justify-content:center;font-size:11px;font-weight:800;margin-right:6px;"><?= $ut['order'] ?></span>
          <?= $ut['label'] ?>
        </div>
        <div style="font-size:12px;color:#64748b;margin-top:2px;"><?= $ut['desc'] ?></div>
      </div>
    </div>

    <div style="background:#f8fafc;border-radius:6px;padding:10px;font-size:12px;color:#475569;margin-bottom:14px;line-height:1.6;">
      <strong>Columns:</strong> <?= $ut['fields'] ?>
    </div>

    <form method="POST" enctype="multipart/form-data" action="<?= WEB_ROOT ?>/school-admin/bulk-upload">
      <input type="hidden" name="upload_type" value="<?= $ut['id'] ?>">
      <div style="display:flex;gap:10px;align-items:center;">
        <input type="file" name="csv_file" accept=".csv"
               style="flex:1;padding:8px;border:1.5px dashed #d1d5db;border-radius:8px;font-size:13px;background:#f8fafc;"
               required>
        <button type="submit" style="padding:9px 18px;background:<?= $ut['color'] ?>;color:#fff;border:none;border-radius:8px;font-weight:600;cursor:pointer;font-size:13px;white-space:nowrap;">
          Upload
        </button>
      </div>
    </form>

    <div style="margin-top:10px;text-align:right;">
      <a href="<?= WEB_ROOT ?>/school-admin/bulk-upload/templates?type=<?= $ut['id'] ?>"
         style="font-size:12px;color:#2563eb;text-decoration:none;">
        <i class="ph ph-download-simple"></i> Download <?= $ut['label'] ?> Template
      </a>
    </div>
  </div>
  <?php endforeach; ?>
  </div>

</div>

<?php require APP_PATH . '/Views/school_admin/layout/footer.php'; ?>
