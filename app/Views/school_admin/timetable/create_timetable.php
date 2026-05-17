<?php
// Interactive Timetable Mapping Logic

try {
    
    $message = '';
    $selected_class = $_GET['class_id'] ?? null;

    // Handle Timetable Entry (Single Cell)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_slot'])) {
        $day_id = $_POST['day_id'];
        $period_id = $_POST['period_id'];
        $subject_id = $_POST['subject_id'];
        $employee_id = $_POST['employee_id'];
        $room_id = $_POST['room_id'];

        $stmt = $pdo->prepare("INSERT INTO timetables (class_id, day_id, period_id, subject_id, employee_id, room_id) 
                               VALUES (?, ?, ?, ?, ?, ?) 
                               ON DUPLICATE KEY UPDATE subject_id=VALUES(subject_id), employee_id=VALUES(employee_id), room_id=VALUES(room_id)");
        $stmt->execute([$selected_class, $day_id, $period_id, $subject_id, $employee_id, $room_id]);
        $message = "Slot updated successfully!";
    }

    // Fetch Classes
    $classes = $pdo->query("SELECT * FROM classes")->fetchAll(PDO::FETCH_ASSOC);

    if ($selected_class) {
        $days = $pdo->query("SELECT * FROM tt_weekdays ORDER BY sort_order ASC")->fetchAll(PDO::FETCH_ASSOC);
        $periods = $pdo->query("SELECT * FROM tt_periods ORDER BY start_time ASC")->fetchAll(PDO::FETCH_ASSOC);
        $subjects = $pdo->query("SELECT * FROM subjects ORDER BY subject_name ASC")->fetchAll(PDO::FETCH_ASSOC);
        $employees = $pdo->query("SELECT e.id, u.full_name FROM institute_employees e JOIN users u ON e.employee_id = u.id ORDER BY u.full_name ASC")->fetchAll(PDO::FETCH_ASSOC);
        $rooms = $pdo->query("SELECT * FROM tt_rooms")->fetchAll(PDO::FETCH_ASSOC);

        // Fetch Existing Timetable Map
        $stmt = $pdo->prepare("SELECT * FROM timetables WHERE class_id = ?");
        $stmt->execute([$selected_class]);
        $timetable_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $tt_map = [];
        foreach($timetable_raw as $t) {
            $tt_map[$t['day_id']][$t['period_id']] = $t;
        }
    }

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

$pageTitle = 'Direct Timetable Mapper - Rosmon SMS';
require ROOT_PATH . '/app/Views/school_admin/layout/header.php';
require ROOT_PATH . '/app/Views/school_admin/layout/sidebar.php';
?>

<div class="main-container">
    <div class="top-header">
        <div class="greeting">Academics / <span style="color:var(--primary)">Class Schedule Mapping</span></div>
        <div class="header-actions">
            <a href="<?= WEB_ROOT ?>/school-admin/timetable/weekdays" class="btn-primary" style="background:#f3f4f6; color:var(--text-dark); text-decoration:none;"><i class="ph ph-list"></i> Resource Config</a>
        </div>
    </div>

    <!-- Class Selector -->
    <div class="crud-card" style="margin-bottom:24px;">
        <form method="GET" style="display:flex; gap:16px; align-items:flex-end;">
            <div style="flex:1;">
                <label style="display:block; font-size:12px; font-weight:700; margin-bottom:5px;">SELECT CLASS TO SCHEDULE</label>
                <select name="class_id" onchange="this.form.submit()" style="width:100%; padding:10px; border:1px solid var(--border); border-radius:8px; font-size:13px; outline:none;">
                    <option value="">-- Choose Class/Section --</option>
                    <?php foreach($classes as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $selected_class == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string)($c['class_name'] ?? '')) ?> (<?= $c['section'] ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </div>

    <?php if ($selected_class): 
        $currentClass = null;
        foreach($classes as $c) if($c['id'] == $selected_class) $currentClass = $c;
    ?>
    <div class="crud-card" style="overflow-x:auto;">
        <div class="crud-header">
            <h2 class="crud-title">Interactive Master Schedule - <?= htmlspecialchars($currentClass['class_name']) ?></h2>
            <span style="color:var(--primary); font-weight:700; font-size:11px;"><?= $message ?></span>
        </div>

        <table class="crud-table" style="min-width: 1000px; border-collapse: separate; border-spacing: 4px;">
            <thead>
                <tr>
                    <th style="background:#f9fafb; position:sticky; left:0; z-index:2;">TIME SLOT \ DAY</th>
                    <?php foreach($days as $day): ?>
                        <th style="min-width:180px; text-align:center;"><?= strtoupper($day['day_name']) ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach($periods as $per): ?>
                    <tr>
                        <td style="background:#f9fafb; position:sticky; left:0; z-index:2; font-weight:800; text-align:right; padding-right:15px; border-right:2px solid var(--border);">
                            <?= htmlspecialchars((string)($per['period_name'] ?? '')) ?>
                            <div style="font-size:10px; color:var(--text-muted);"><?= date('h:i', strtotime($per['start_time'])) ?>-<?= date('h:i', strtotime($per['end_time'])) ?></div>
                        </td>
                        
                        <?php foreach($days as $day): 
                            $slot = $tt_map[$day['id']][$per['id']] ?? null;
                            $bg = $per['is_break'] ? '#fff7ed' : '#fcfaff';
                        ?>
                            <td style="background:<?= $bg ?>; padding:8px; border:1px dashed #e2e8f0; border-radius:8px; vertical-align:top;">
                                <?php if ($per['is_break']): ?>
                                    <div style="text-align:center; padding:20px 0; color:#c2410c; font-weight:800; font-size:10px;">-- BREAK --</div>
                                <?php else: ?>
                                    <form method="POST">
                                        <input type="hidden" name="save_slot" value="1">
                                        <input type="hidden" name="day_id" value="<?= $day['id'] ?>">
                                        <input type="hidden" name="period_id" value="<?= $per['id'] ?>">
                                        
                                        <select name="subject_id" onchange="this.form.submit()" style="width:100%; border:none; background:transparent; font-size:12px; font-weight:700; margin-bottom:4px; color:var(--primary); outline:none;">
                                            <option value="">+ Subject</option>
                                            <?php foreach($subjects as $s): ?>
                                                <option value="<?= $s['id'] ?>" <?= ($slot && $slot['subject_id'] == $s['id']) ? 'selected' : '' ?>><?= htmlspecialchars((string)($s['subject_name'] ?? '')) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        
                                        <select name="employee_id" onchange="this.form.submit()" style="width:100%; border:none; background:transparent; font-size:10px; font-weight:600; color:var(--text-muted); outline:none;">
                                            <option value="">Teacher?</option>
                                            <?php foreach($employees as $e): ?>
                                                <option value="<?= $e['id'] ?>" <?= ($slot && $slot['employee_id'] == $e['id']) ? 'selected' : '' ?>><?= htmlspecialchars((string)($e['full_name'] ?? '')) ?></option>
                                            <?php endforeach; ?>
                                        </select>

                                        <select name="room_id" onchange="this.form.submit()" style="width:100%; border:none; background:transparent; font-size:10px; font-weight:500; color:#9ca3af; outline:none;">
                                            <option value="">Room?</option>
                                            <?php foreach($rooms as $r): ?>
                                                <option value="<?= $r['id'] ?>" <?= ($slot && $slot['room_id'] == $r['id']) ? 'selected' : '' ?>><?= htmlspecialchars((string)($r['room_name'] ?? '')) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </form>
                                <?php endif; ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
        <div class="crud-card" style="text-align:center; padding:80px;">
            <i class="ph ph-calendar-plus" style="font-size:48px; color:var(--primary-light);"></i>
            <h3 style="margin-top:20px;">Welcome to the Visual Timetable Builder</h3>
            <p style="color:var(--text-muted);">Please select a class above to begin mapping subjects and teachers to their respective time slots.</p>
        </div>
    <?php endif; ?>
</div>

<?php require ROOT_PATH . '/app/Views/school_admin/layout/footer.php'; ?>
