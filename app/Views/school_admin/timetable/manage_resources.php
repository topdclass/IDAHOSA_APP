<?php
// Timetable Resources Management Logic

try {
    
    // Auto Migration: Timetable Infrastructure
    $pdo->exec("CREATE TABLE IF NOT EXISTS tt_weekdays (
        id INT AUTO_INCREMENT PRIMARY KEY,
        day_name VARCHAR(20) NOT NULL UNIQUE,
        sort_order INT DEFAULT 0
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS tt_periods (
        id INT AUTO_INCREMENT PRIMARY KEY,
        period_name VARCHAR(50) NOT NULL,
        start_time TIME,
        end_time TIME,
        is_break BOOLEAN DEFAULT FALSE
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS tt_rooms (
        id INT AUTO_INCREMENT PRIMARY KEY,
        room_name VARCHAR(50) NOT NULL UNIQUE,
        capacity INT DEFAULT 0
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS timetables (
        id INT AUTO_INCREMENT PRIMARY KEY,
        class_id INT NOT NULL,
        day_id INT NOT NULL,
        period_id INT NOT NULL,
        subject_id INT,
        employee_id INT,
        room_id INT,
        UNIQUE(class_id, day_id, period_id)
    )");

    // Seed Days if empty
    if ($pdo->query("SELECT COUNT(*) FROM tt_weekdays")->fetchColumn() == 0) {
        $pdo->exec("INSERT INTO tt_weekdays (day_name, sort_order) VALUES ('Monday', 1), ('Tuesday', 2), ('Wednesday', 3), ('Thursday', 4), ('Friday', 5), ('Saturday', 6), ('Sunday', 7)");
    }

    $message = '';
    $currentTab = $_GET['tab'] ?? 'periods';

    // Handle POST for Periods
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['p_name'])) {
        $name = $_POST['p_name'];
        $start = $_POST['start'];
        $end = $_POST['end'];
        $is_break = isset($_POST['is_break']) ? 1 : 0;

        $stmt = $pdo->prepare("INSERT INTO tt_periods (period_name, start_time, end_time, is_break) VALUES (?, ?, ?, ?)");
        $stmt->execute([$name, $start, $end, $is_break]);
        $message = "Time slot registered successfully!";
    }

    // Handle POST for Rooms
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['room_name'])) {
        $name = $_POST['room_name'];
        $cap = $_POST['capacity'] ?? 0;
        $stmt = $pdo->prepare("INSERT INTO tt_rooms (room_name, capacity) VALUES (?, ?)");
        $stmt->execute([$name, $cap]);
        $message = "Room registered successfully!";
    }

    // Handle Delete
    if (isset($_GET['delete_period'])) {
        $pdo->prepare("DELETE FROM tt_periods WHERE id = ?")->execute([$_GET['delete_period']]);
        $message = "Period removed.";
    }
    if (isset($_GET['delete_room'])) {
        $pdo->prepare("DELETE FROM tt_rooms WHERE id = ?")->execute([$_GET['delete_room']]);
        $message = "Room removed.";
    }

} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

$pageTitle = 'Scheduling Resources - Rosmon SMS';
require ROOT_PATH . '/app/Views/school_admin/layout/header.php';
require ROOT_PATH . '/app/Views/school_admin/layout/sidebar.php';
?>

<div class="main-container">
    <div class="top-header">
        <div class="greeting">Academics / <span style="color:var(--primary)">Scheduling Infrastructure</span></div>
    </div>

    <!-- Quick Tabs -->
    <div style="display:flex; gap:12px; margin-bottom:24px;">
        <a href="?tab=periods" class="btn-primary" style="background:<?= $currentTab == 'periods' ? 'var(--primary)' : '#fff' ?>; color:<?= $currentTab == 'periods' ? '#fff' : 'var(--text-dark)' ?>; border:1px solid var(--border); border-radius:20px; padding:10px 24px; text-decoration:none;">Time Periods</a>
        <a href="?tab=rooms" class="btn-primary" style="background:<?= $currentTab == 'rooms' ? 'var(--primary)' : '#fff' ?>; color:<?= $currentTab == 'rooms' ? '#fff' : 'var(--text-dark)' ?>; border:1px solid var(--border); border-radius:20px; padding:10px 24px; text-decoration:none;">Class Rooms</a>
        <a href="?tab=weekdays" class="btn-primary" style="background:<?= $currentTab == 'weekdays' ? 'var(--primary)' : '#fff' ?>; color:<?= $currentTab == 'weekdays' ? '#fff' : 'var(--text-dark)' ?>; border:1px solid var(--border); border-radius:20px; padding:10px 24px; text-decoration:none;">Working Days</a>
    </div>

    <?php if ($message): ?>
        <div style="background:#dcfce7; color:#166534; padding:12px 20px; border-radius:12px; font-weight:700; font-size:13px; margin-bottom:20px;">
            <i class="ph ph-check-circle"></i> <?= $message ?>
        </div>
    <?php endif; ?>

        <!-- Dynamic Content -->
        <?php if ($currentTab == 'periods'): ?>
            <div class="crud-card">
                <div class="crud-header"><h2 class="crud-title">New Time Slot</h2></div>
                <form method="POST" style="padding:20px;">
                    <label style="display:block; font-size:12px; font-weight:700; margin-bottom:5px;">PERIOD TITLE</label>
                    <input type="text" name="p_name" placeholder="e.g. 1st Period" required style="width:100%; padding:10px; border:1px solid var(--border); border-radius:8px; margin-bottom:15px;">
                    
                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px; margin-bottom:15px;">
                        <div>
                            <label style="display:block; font-size:11px; font-weight:700;">START TIME</label>
                            <input type="time" name="start" required style="width:100%; padding:10px; border:1px solid var(--border); border-radius:8px;">
                        </div>
                        <div>
                            <label style="display:block; font-size:11px; font-weight:700;">END TIME</label>
                            <input type="time" name="end" required style="width:100%; padding:10px; border:1px solid var(--border); border-radius:8px;">
                        </div>
                    </div>

                    <label style="display:flex; align-items:center; gap:10px; font-size:12px; font-weight:700;">
                        <input type="checkbox" name="is_break"> Is this a Break/Recess?
                    </label>

                    <button type="submit" class="btn-primary" style="width:100%; margin-top:20px;">Register Slot</button>
                </form>
            </div>

            <div class="crud-card">
                <div class="crud-header"><h2 class="crud-title">Master Daily Schedule</h2></div>
                <table class="crud-table">
                    <thead>
                        <tr>
                            <th>PERIOD</th>
                            <th>TIME RANGE</th>
                            <th>TYPE</th>
                            <th>ACTIONS</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $periods = $pdo->query("SELECT * FROM tt_periods ORDER BY start_time ASC")->fetchAll(PDO::FETCH_ASSOC);
                        if (empty($periods)):
                        ?>
                            <tr><td colspan="4" style="text-align:center; padding:40px; color:var(--text-muted);">No time slots defined.</td></tr>
                        <?php else: ?>
                            <?php foreach($periods as $p): ?>
                                <tr>
                                    <td style="font-weight:700;"><?= htmlspecialchars((string)($p['period_name'] ?? '')) ?></td>
                                    <td><?= date('h:i A', strtotime($p['start_time'])) ?> - <?= date('h:i A', strtotime($p['end_time'])) ?></td>
                                    <td>
                                        <span style="padding:4px 10px; border-radius:20px; font-size:10px; font-weight:800; background:<?= $p['is_break'] ? '#fef3c7; color:#92400e' : 'var(--primary-light); color:var(--primary)' ?>;">
                                            <?= $p['is_break'] ? 'BREAK' : 'LESSON' ?>
                                        </span>
                                    </td>
                                    <td><a href="?tab=periods&delete_period=<?= $p['id'] ?>" onclick="return confirm('Remove?')" style="color:#ef4444;"><i class="ph ph-trash"></i></a></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php elseif ($currentTab == 'rooms'): ?>
            <div class="crud-card">
                <div class="crud-header"><h2 class="crud-title">Add Room</h2></div>
                <form method="POST" style="padding:20px;">
                    <label style="display:block; font-size:12px; font-weight:700; margin-bottom:5px;">ROOM NAME</label>
                    <input type="text" name="room_name" placeholder="e.g. Science Lab 1" required style="width:100%; padding:10px; border:1px solid var(--border); border-radius:8px; margin-bottom:15px;">
                    
                    <label style="display:block; font-size:12px; font-weight:700; margin-bottom:5px;">CAPACITY</label>
                    <input type="number" name="capacity" placeholder="30" style="width:100%; padding:10px; border:1px solid var(--border); border-radius:8px; margin-bottom:15px;">
                    
                    <button type="submit" class="btn-primary" style="width:100%;">Create Room</button>
                </form>
            </div>

            <div class="crud-card">
                <div class="crud-header"><h2 class="crud-title">Physical Assets</h2></div>
                <table class="crud-table">
                    <thead>
                        <tr>
                            <th>ROOM NAME</th>
                            <th>CAPACITY</th>
                            <th>ACTIONS</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $rooms = $pdo->query("SELECT * FROM tt_rooms ORDER BY room_name ASC")->fetchAll(PDO::FETCH_ASSOC);
                        if (empty($rooms)):
                        ?>
                            <tr><td colspan="3" style="text-align:center; padding:40px; color:var(--text-muted);">No rooms registered.</td></tr>
                        <?php else: ?>
                            <?php foreach($rooms as $r): ?>
                                <tr>
                                    <td style="font-weight:700;"><?= htmlspecialchars($r['room_name']) ?></td>
                                    <td><?= (int)$r['capacity'] ?> Students</td>
                                    <td><a href="?tab=rooms&delete_room=<?= $r['id'] ?>" onclick="return confirm('Remove?')" style="color:#ef4444;"><i class="ph ph-trash"></i></a></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
             <div class="crud-card" style="grid-column: span 2;">
                <div class="crud-header"><h2 class="crud-title">Active Days Configuration</h2></div>
                <div style="padding:30px; display:flex; gap:15px; flex-wrap:wrap;">
                    <?php
                    $days = $pdo->query("SELECT * FROM tt_weekdays ORDER BY sort_order ASC")->fetchAll(PDO::FETCH_ASSOC);
                    foreach($days as $d):
                    ?>
                        <div style="background:var(--white); border:2px solid var(--border); padding:20px 30px; border-radius:16px; min-width:140px; text-align:center;">
                            <div style="font-size:24px; color:var(--primary); margin-bottom:10px;"><i class="ph ph-calendar"></i></div>
                            <div style="font-weight:800; font-size:14px;"><?= $d['day_name'] ?></div>
                            <div style="font-size:10px; color:var(--text-muted); margin-top:5px; font-weight:700;">ORDER: #<?= $d['sort_order'] ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
</div>

<?php require ROOT_PATH . '/app/Views/school_admin/layout/footer.php'; ?>
