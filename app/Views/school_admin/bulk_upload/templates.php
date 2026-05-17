<?php
/**
 * CSV Template Download Page
 * Generates and downloads CSV templates for bulk upload
 */
define('ROOT_PATH', dirname(dirname(dirname(dirname(dirname(__DIR__))))));

// If a specific type is requested, stream it as a CSV download
$type = $_GET['type'] ?? null;

if ($type) {
    $templates = getCsvTemplates();
    if (isset($templates[$type])) {
        $tpl = $templates[$type];
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $tpl['filename'] . '"');
        header('Pragma: no-cache');

        $out = fopen('php://output', 'w');
        // BOM for Excel UTF-8 compatibility
        fputs($out, "\xEF\xBB\xBF");
        // Header row
        fputcsv($out, $tpl['headers']);
        // Sample rows
        foreach ($tpl['samples'] as $sample) {
            fputcsv($out, $sample);
        }
        fclose($out);
        exit;
    }
}

function getCsvTemplates(): array {
    return [
        'classes' => [
            'filename' => 'rosmon_classes_template.csv',
            'label'    => 'Classes / Sections',
            'headers'  => ['class_name','section','capacity','class_teacher_name','notes'],
            'samples'  => [
                ['JSS 1','A','40','Mr. John Okafor','Junior Secondary'],
                ['JSS 1','B','35','Mrs. Ada Nwosu',''],
                ['JSS 2','A','38','Mr. Emmanuel Eze',''],
                ['SS 1','Science','40','Dr. Amina Bello','Senior Secondary Science'],
                ['SS 2','Arts','35','Mrs. Grace Adeleke',''],
                ['Primary 1','A','30','Miss Mary Olawale',''],
            ],
        ],
        'subjects' => [
            'filename' => 'rosmon_subjects_template.csv',
            'label'    => 'Subjects',
            'headers'  => ['subject_name','subject_code','class_name','notes'],
            'samples'  => [
                ['Mathematics','MATH','','All classes'],
                ['English Language','ENG','',''],
                ['Basic Science','BSC','JSS 1','Junior classes'],
                ['Physics','PHY','SS 1','Senior Science only'],
                ['Chemistry','CHEM','SS 1',''],
                ['Biology','BIO','SS 1',''],
                ['Economics','ECON','SS 2','Arts and Commercial'],
                ['Civic Education','CIV','','All classes'],
                ['Physical Education','PE','',''],
                ['Computer Science','CS','',''],
                ['Agricultural Science','AGRIC','',''],
                ['Islamic Studies','ISL','',''],
                ['Christian Religious Studies','CRS','',''],
                ['Yoruba Language','YOR','',''],
                ['Hausa Language','HAU','',''],
            ],
        ],
        'employees' => [
            'filename' => 'rosmon_employees_template.csv',
            'label'    => 'Employees / Staff',
            'headers'  => [
                'full_name','email','phone','role','department','designation',
                'employee_no','salary','hire_date','gender','dob','religion',
                'blood_group','address','class_assigned','notes'
            ],
            'samples'  => [
                ['Dr. Amina Bello','amina.bello@school.edu','08012345678','principal','Administration','Principal','EMP001','250000','2020-01-15','Female','1975-03-20','Islam','O+','12 School Road, Lagos','','Head of School'],
                ['Mr. John Okafor','john.okafor@school.edu','08023456789','teacher','Sciences','Senior Lecturer','EMP002','150000','2021-09-01','Male','1985-07-10','Christianity','A+','5 Teacher Avenue, Lagos','JSS 1A','Mathematics Teacher'],
                ['Mrs. Ada Nwosu','ada.nwosu@school.edu','08034567890','teacher','Languages','Teacher','EMP003','120000','2022-01-10','Female','1988-12-05','Christianity','B+','8 Palm Close, Lagos','JSS 1B','English Teacher'],
                ['Mr. Emmanuel Eze','emma.eze@school.edu','08045678901','teacher','Sciences','Teacher','EMP004','130000','2021-09-01','Male','1983-05-25','Christianity','AB+','3 Mango Street, Lagos','JSS 2A','Basic Science'],
                ['Mrs. Grace Adeleke','grace.adeleke@school.edu','08056789012','vice_principal','Administration','Vice Principal','EMP005','200000','2019-06-01','Female','1978-11-15','Christianity','O-','22 Garden Close, Lagos','','Academic Affairs'],
                ['Mr. Ibrahim Hassan','ibrahim.hassan@school.edu','08067890123','accountant','Finance','Bursar','EMP006','180000','2020-03-01','Male','1980-09-30','Islam','A-','7 Finance Road, Lagos','','School Bursar'],
                ['Miss Mary Olawale','mary.olawale@school.edu','08078901234','teacher','Primary','Class Teacher','EMP007','100000','2023-01-15','Female','1992-04-18','Christianity','B-','15 Primary Lane, Lagos','Primary 1A','Primary Class Teacher'],
                ['Mr. Chidi Obi','chidi.obi@school.edu','08089012345','support','Support','Security Guard','EMP008','60000','2021-01-01','Male','1990-08-22','Christianity','O+','9 Guard House, Lagos','','Gate Security'],
            ],
        ],
        'students' => [
            'filename' => 'rosmon_students_template.csv',
            'label'    => 'Students',
            'headers'  => [
                'full_name','admission_no','class_name','section','gender','dob',
                'religion','blood_group','address','phone','email','admission_date',
                'state_of_origin','lga','nationality','previous_school','notes'
            ],
            'samples'  => [
                ['Chisom Okafor','ADM2024001','JSS 1','A','Female','2012-03-15','Christianity','O+','10 School Lane, Lagos','08011122233','chisom@email.com','2024-09-01','Lagos','Ikeja','Nigerian','St. Peters Primary',''],
                ['Ibrahim Musa','ADM2024002','JSS 1','A','Male','2011-07-22','Islam','A+','5 North Road, Kano','08022233344','','2024-09-01','Kano','Kano Municipal','Nigerian','Government Primary',''],
                ['Amara Chukwu','ADM2024003','JSS 1','B','Female','2012-01-10','Christianity','B+','3 East Close, Enugu','08033344455','','2024-09-01','Enugu','Enugu East','Nigerian','','New admission'],
                ['Fatima Abdullahi','ADM2024004','SS 1','Science','Female','2009-11-30','Islam','O-','7 North Close, Kaduna','08044455566','','2024-09-01','Kaduna','Kaduna North','Nigerian','','Transfer student'],
                ['Emeka Nwosu','ADM2024005','SS 2','Arts','Male','2008-05-14','Christianity','AB+','12 South Ave, Owerri','08055566677','emeka@email.com','2024-09-01','Imo','Owerri West','Nigerian','Government Secondary',''],
                ['Aisha Bello','ADM2024006','Primary 1','A','Female','2016-08-25','Islam','B-','2 West Road, Abuja','08066677788','','2024-09-01','FCT','Abuja Municipal','Nigerian','',''],
            ],
        ],
        'parents' => [
            'filename' => 'rosmon_parents_template.csv',
            'label'    => 'Parents / Guardians',
            'headers'  => [
                'father_name','mother_name','guardian_name','phone','email',
                'address','occupation','student_admission_no','relationship','notes'
            ],
            'samples'  => [
                ['Mr. Charles Okafor','Mrs. Rose Okafor','Mr. Charles Okafor','08011122233','charles.okafor@email.com','10 School Lane, Lagos','Engineer','ADM2024001','Father',''],
                ['Alhaji Musa Ibrahim','Hajiya Maryam Musa','Alhaji Musa Ibrahim','08022233344','','5 North Road, Kano','Trader','ADM2024002','Father',''],
                ['Mr. Peter Chukwu','Mrs. Ngozi Chukwu','Mrs. Ngozi Chukwu','08033344455','ngozi.chukwu@email.com','3 East Close, Enugu','Teacher','ADM2024003','Mother',''],
                ['Alhaji Abdullahi Sani','Hajiya Fatima Abdullahi','Alhaji Abdullahi Sani','08044455566','abdullahi@email.com','7 North Close, Kaduna','Civil Servant','ADM2024004','Father',''],
                ['Chief Emmanuel Nwosu','Mrs. Blessing Nwosu','Chief Emmanuel Nwosu','08055566677','enwosu@email.com','12 South Ave, Owerri','Businessman','ADM2024005','Father',''],
                ['Alhaji Bello Ahmed','Hajiya Khadija Bello','Hajiya Khadija Bello','08066677788','khadija.bello@email.com','2 West Road, Abuja','Nurse','ADM2024006','Mother',''],
            ],
        ],
        'subject_assignments' => [
            'filename' => 'rosmon_subject_assignments_template.csv',
            'label'    => 'Subject Assignments',
            'headers'  => ['class_name','section','subject_name','teacher_email','notes'],
            'samples'  => [
                ['JSS 1','A','Mathematics','john.okafor@school.edu',''],
                ['JSS 1','A','English Language','ada.nwosu@school.edu',''],
                ['JSS 1','A','Basic Science','emma.eze@school.edu',''],
                ['JSS 1','B','Mathematics','john.okafor@school.edu','Same teacher for both sections'],
                ['JSS 1','B','English Language','ada.nwosu@school.edu',''],
                ['SS 1','Science','Physics','john.okafor@school.edu',''],
                ['SS 1','Science','Chemistry','emma.eze@school.edu',''],
                ['SS 2','Arts','Economics','ada.nwosu@school.edu',''],
            ],
        ],
    ];
}

// Page view — download all templates as a single ZIP or show links
require_once ROOT_PATH . '/config/database.php';
$templates = getCsvTemplates();
$pageTitle = 'CSV Templates — RosmonSMS Bulk Upload';
require APP_PATH . '/Views/school_admin/layout/header.php';
require APP_PATH . '/Views/school_admin/layout/sidebar.php';
?>

<div class="main-container">
  <div class="top-header">
    <div class="greeting">Bulk Upload / <span style="color:var(--primary)">CSV Templates</span></div>
    <div class="header-actions">
      <a href="<?= WEB_ROOT ?>/school-admin/bulk-upload" class="btn-secondary" style="text-decoration:none;">
        <i class="ph ph-arrow-left"></i> Back to Upload
      </a>
    </div>
  </div>

  <div style="background:linear-gradient(135deg,#1e40af,#7c3aed);border-radius:16px;padding:32px;color:#fff;margin-bottom:28px;">
    <h2 style="font-size:22px;font-weight:800;margin-bottom:8px;">&#128196; Download CSV Templates</h2>
    <p style="opacity:0.9;font-size:14px;max-width:600px;">
      Download the pre-formatted CSV templates, fill in your school data using Excel or Google Sheets,
      then upload them to quickly onboard all your staff and students in minutes.
    </p>
  </div>

  <!-- Instructions -->
  <div class="crud-card" style="margin-bottom:24px;padding:24px;">
    <h3 style="font-size:15px;margin-bottom:16px;color:#1e293b;">&#128161; Filling Instructions</h3>
    <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:20px;font-size:13px;color:#374151;line-height:1.8;">
      <div>
        <strong style="color:#2563eb;">General Rules:</strong><br>
        &#9679; Do NOT delete or rename the header row<br>
        &#9679; Save files as CSV (UTF-8 encoded)<br>
        &#9679; Leave optional columns blank — do not delete them<br>
        &#9679; Dates must be in <code>YYYY-MM-DD</code> format<br>
        &#9679; Duplicate records are automatically updated
      </div>
      <div>
        <strong style="color:#7c3aed;">Upload Order (Important):</strong><br>
        &#9312; Classes first (employees reference classes)<br>
        &#9313; Subjects second<br>
        &#9314; Employees / Staff (teachers reference classes)<br>
        &#9315; Students (reference classes by name)<br>
        &#9316; Parents (reference students by admission no.)<br>
        &#9317; Subject Assignments (link teachers to subjects)
      </div>
    </div>
  </div>

  <!-- Template Cards -->
  <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:20px;">
  <?php
  $colors = ['#059669','#d97706','#2563eb','#7c3aed','#0891b2','#be185d'];
  $icons  = ['ph-chalkboard-teacher','ph-book-open','ph-users','ph-student','ph-users-three','ph-link'];
  $i = 0;
  foreach ($templates as $key => $tpl):
    $color = $colors[$i] ?? '#2563eb';
    $icon  = $icons[$i]  ?? 'ph-file-csv';
    $i++;
  ?>
  <div class="crud-card" style="padding:24px;">
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px;">
      <div style="width:48px;height:48px;background:<?= $color ?>1a;color:<?= $color ?>;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:24px;">
        <i class="ph <?= $icon ?>"></i>
      </div>
      <div>
        <div style="font-weight:700;font-size:15px;color:#1e293b;"><?= htmlspecialchars($tpl['label']) ?></div>
        <div style="font-size:12px;color:#64748b;"><?= count($tpl['headers']) ?> columns &middot; <?= count($tpl['samples']) ?> sample rows</div>
      </div>
    </div>

    <!-- Column list -->
    <div style="margin-bottom:16px;">
      <div style="font-size:12px;font-weight:700;color:#374151;margin-bottom:6px;">COLUMNS:</div>
      <div style="display:flex;flex-wrap:wrap;gap:4px;">
        <?php foreach ($tpl['headers'] as $h): ?>
        <span style="background:#f1f5f9;color:#475569;padding:2px 8px;border-radius:4px;font-size:11px;font-family:monospace;">
          <?= htmlspecialchars($h) ?>
        </span>
        <?php endforeach; ?>
      </div>
    </div>

    <a href="<?= WEB_ROOT ?>/school-admin/bulk-upload/templates?type=<?= $key ?>"
       class="btn-primary" style="display:block;text-align:center;text-decoration:none;background:<?= $color ?>;padding:10px 0;border-radius:8px;font-size:13px;font-weight:600;color:#fff;">
      <i class="ph ph-download-simple"></i> Download <?= htmlspecialchars($tpl['label']) ?> Template
    </a>
  </div>
  <?php endforeach; ?>
  </div>

  <!-- Sample data preview -->
  <div class="crud-card" style="margin-top:24px;padding:24px;">
    <h3 style="font-size:15px;margin-bottom:4px;">&#128270; Template Preview</h3>
    <p style="font-size:13px;color:#64748b;margin-bottom:16px;">Sample data included in each template to guide you.</p>

    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px;">
      <?php $i=0; foreach ($templates as $key => $tpl): $color = $colors[$i++] ?? '#2563eb'; ?>
      <button onclick="showPreview('<?= $key ?>')"
              style="padding:7px 14px;border:2px solid <?= $color ?>;background:transparent;color:<?= $color ?>;border-radius:6px;cursor:pointer;font-size:13px;font-weight:600;">
        <?= htmlspecialchars($tpl['label']) ?>
      </button>
      <?php endforeach; ?>
    </div>

    <?php $i=0; foreach ($templates as $key => $tpl): $color = $colors[$i++] ?? '#2563eb'; ?>
    <div id="preview-<?= $key ?>" style="display:none;overflow-x:auto;">
      <table style="width:100%;border-collapse:collapse;font-size:12px;">
        <thead>
          <tr style="background:<?= $color ?>;color:#fff;">
            <?php foreach ($tpl['headers'] as $h): ?>
            <th style="padding:8px 10px;text-align:left;white-space:nowrap;"><?= htmlspecialchars($h) ?></th>
            <?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($tpl['samples'] as $si => $sample): ?>
          <tr style="background:<?= $si%2===0?'#f8fafc':'#fff' ?>;">
            <?php foreach ($sample as $cell): ?>
            <td style="padding:7px 10px;border-bottom:1px solid #e2e8f0;color:#374151;white-space:nowrap;">
              <?= htmlspecialchars($cell) ?: '<span style="color:#cbd5e1">—</span>' ?>
            </td>
            <?php endforeach; ?>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endforeach; ?>
  </div>

</div>

<script>
function showPreview(type) {
  document.querySelectorAll('[id^="preview-"]').forEach(el => el.style.display='none');
  const el = document.getElementById('preview-' + type);
  if (el) el.style.display = 'block';
}
showPreview('classes');
</script>

<?php require APP_PATH . '/Views/school_admin/layout/footer.php'; ?>
