<?php
// Events Calendar & Scheduling Module

try {
    
    // Auto Migration: Events Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS school_events (
        id INT AUTO_INCREMENT PRIMARY KEY,
        event_title VARCHAR(255) NOT NULL,
        event_description TEXT,
        event_type ENUM('academic','sports','cultural','meeting','holiday','other') NOT NULL DEFAULT 'academic',
        event_date DATE NOT NULL,
        start_time TIME DEFAULT NULL,
        end_time TIME DEFAULT NULL,
        venue VARCHAR(255) DEFAULT NULL,
        audience ENUM('all','students','parents','employees') NOT NULL DEFAULT 'all',
        color VARCHAR(7) DEFAULT '#3b82f6',
        is_recurring BOOLEAN DEFAULT FALSE,
        created_by INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_date (event_date),
        INDEX idx_type (event_type)
    )");

    $current_user_id = $_SESSION['user_id'] ?? 1;
    $message = '';

    // Handle Create Event
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_event'])) {
        $title = trim($_POST['event_title'] ?? '');
        $desc = trim($_POST['event_description'] ?? '');
        $type = $_POST['event_type'] ?? 'academic';
        $date = $_POST['event_date'] ?? date('Y-m-d');
        $start = $_POST['start_time'] ?: null;
        $end = $_POST['end_time'] ?: null;
        $venue = trim($_POST['venue'] ?? '');
        $audience = $_POST['audience'] ?? 'all';
        
        $colors = ['academic' => '#3b82f6', 'sports' => '#22c55e', 'cultural' => '#a855f7', 'meeting' => '#f59e0b', 'holiday' => '#ef4444', 'other' => '#6b7280'];
        $color = $colors[$type] ?? '#3b82f6';

        if (!empty($title) && !empty($date)) {
            $stmt = $pdo->prepare("INSERT INTO school_events (event_title, event_description, event_type, event_date, start_time, end_time, venue, audience, color, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$title, $desc, $type, $date, $start, $end, $venue, $audience, $color, $current_user_id]);
            header("Location: " . $_SERVER['REQUEST_URI']);
            exit;
        }
    }

    // Handle Delete Event
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_event'])) {
        $del_id = (int)$_POST['event_id'];
        $pdo->prepare("DELETE FROM school_events WHERE id = ?")->execute([$del_id]);
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
    }

    // Calendar navigation
    $month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('n');
    $year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
    
    if ($month < 1) { $month = 12; $year--; }
    if ($month > 12) { $month = 1; $year++; }

    $firstDay = mktime(0, 0, 0, $month, 1, $year);
    $daysInMonth = date('t', $firstDay);
    $startDayOfWeek = date('w', $firstDay); // 0=Sun, 6=Sat
    $monthName = date('F', $firstDay);

    // Fetch events for this month
    $stmt = $pdo->prepare("SELECT * FROM school_events WHERE MONTH(event_date) = ? AND YEAR(event_date) = ? ORDER BY event_date, start_time");
    $stmt->execute([$month, $year]);
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Group events by day
    $eventsByDay = [];
    foreach ($events as $ev) {
        $day = (int)date('j', strtotime($ev['event_date']));
        $eventsByDay[$day][] = $ev;
    }

    // Fetch upcoming events (next 30 days)
    $upcoming = $pdo->query("SELECT * FROM school_events WHERE event_date >= CURDATE() AND event_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) ORDER BY event_date, start_time LIMIT 8")->fetchAll(PDO::FETCH_ASSOC);

    // Stats
    $total_events = $pdo->query("SELECT COUNT(*) FROM school_events WHERE MONTH(event_date) = $month AND YEAR(event_date) = $year")->fetchColumn();
    $upcoming_count = $pdo->query("SELECT COUNT(*) FROM school_events WHERE event_date >= CURDATE() AND event_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)")->fetchColumn();

    $prevMonth = $month - 1; $prevYear = $year;
    $nextMonth = $month + 1; $nextYear = $year;
    if ($prevMonth < 1) { $prevMonth = 12; $prevYear--; }
    if ($nextMonth > 12) { $nextMonth = 1; $nextYear++; }

} catch (PDOException $e) {
    die("Events Error: " . $e->getMessage());
}

$pageTitle = 'Events Calendar - Rosmon SMS';
require ROOT_PATH . '/app/Views/school_admin/layout/header.php';
require ROOT_PATH . '/app/Views/school_admin/layout/sidebar.php';
?>

<style>
    .cal-layout { display: grid; grid-template-columns: 1fr 340px; gap: 20px; }
    .cal-card { background: var(--white); border: 1px solid var(--border); border-radius: 12px; overflow:hidden; }
    .cal-nav { display: flex; justify-content: space-between; align-items: center; padding: 20px 24px; border-bottom: 1px solid var(--border); }
    .cal-nav h2 { font-size: 18px; font-weight: 800; }
    .cal-nav-btn { width: 36px; height: 36px; border-radius: 8px; border: 1px solid #e5e7eb; background: #fff; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: 0.15s; }
    .cal-nav-btn:hover { background: var(--primary); color: #fff; border-color: var(--primary); }

    .cal-grid { display: grid; grid-template-columns: repeat(7, 1fr); }
    .cal-day-header { padding: 12px; text-align: center; font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; background: #fafbfc; border-bottom: 1px solid var(--border); }
    .cal-cell { min-height: 95px; padding: 8px; border-bottom: 1px solid #f5f5f5; border-right: 1px solid #f5f5f5; position: relative; transition: 0.1s; cursor: pointer; }
    .cal-cell:hover { background: #f9fafb; }
    .cal-cell.today { background: #f0f3ff; }
    .cal-cell.empty { background: #fafbfc; cursor: default; }
    .cal-cell .day-num { font-size: 13px; font-weight: 700; color: var(--text-dark); margin-bottom: 4px; }
    .cal-cell.today .day-num { color: var(--primary); background: #e0e7ff; width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px; }
    .cal-event-dot { font-size: 9px; padding: 2px 6px; border-radius: 4px; color: #fff; margin-bottom: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display: block; font-weight: 600; line-height: 1.3; }

    .upcoming-panel { display: flex; flex-direction: column; }
    .upcoming-header { padding: 20px 24px; border-bottom: 1px solid var(--border); }
    .upcoming-header h3 { font-size: 15px; font-weight: 800; margin: 0; }
    .upcoming-list { flex: 1; overflow-y: auto; padding: 16px; }
    .upcoming-item { display: flex; gap: 14px; padding: 14px; border-radius: 10px; margin-bottom: 8px; transition: 0.15s; cursor: pointer; }
    .upcoming-item:hover { background: #f9fafb; }
    .upcoming-date-box { width: 48px; height: 52px; border-radius: 10px; display: flex; flex-direction: column; align-items: center; justify-content: center; flex-shrink: 0; }
    .upcoming-date-box .month { font-size: 9px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; }
    .upcoming-date-box .day { font-size: 20px; font-weight: 800; }
    .upcoming-info h4 { font-size: 13px; font-weight: 700; color: var(--text-dark); margin: 0 0 4px; }
    .upcoming-info p { font-size: 11px; color: var(--text-muted); margin: 0; }
    .upcoming-type-badge { font-size: 9px; font-weight: 700; padding: 2px 8px; border-radius: 20px; text-transform: uppercase; letter-spacing: 0.3px; }

    .event-legend { display: flex; gap: 12px; flex-wrap: wrap; padding: 16px 24px; border-top: 1px solid var(--border); }
    .legend-item { display: flex; align-items: center; gap: 5px; font-size: 10px; font-weight: 600; color: var(--text-muted); }
    .legend-dot { width: 8px; height: 8px; border-radius: 2px; }

    /* Modal */
    .event-modal { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:1001; align-items:center; justify-content:center; }
    .event-modal-box { width: 520px; background: var(--white); border-radius: 16px; padding: 28px; box-shadow: 0 20px 60px rgba(0,0,0,0.15); }
    .event-modal-box label { display:block; font-size:11px; font-weight:700; color:var(--text-muted); margin-bottom:6px; text-transform:uppercase; letter-spacing:0.5px; }
    .event-modal-box input, .event-modal-box select, .event-modal-box textarea { width:100%; padding:11px 14px; border:1px solid #e5e7eb; border-radius:8px; font-size:13px; margin-bottom:14px; outline:none; font-family:'Inter',sans-serif; }
    .event-modal-box textarea { min-height: 80px; resize: vertical; }
    .event-modal-box input:focus, .event-modal-box select:focus, .event-modal-box textarea:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(16,23,142,0.08); }
</style>

<div class="main-container">
    <div class="top-header">
        <div class="greeting">Communication / <span style="color:var(--primary)">Events Calendar</span></div>
        <div class="header-actions">
            <button onclick="document.getElementById('event-modal').style.display='flex'" class="btn-primary" style="display:flex; align-items:center; gap:6px;">
                <i class="ph ph-plus-circle"></i> New Event
            </button>
        </div>
    </div>

    <!-- Quick Stats -->
    <div style="display:grid; grid-template-columns: repeat(3, 1fr); gap:16px; margin-bottom:24px;">
        <div class="cal-card" style="padding:20px; display:flex; align-items:center; gap:14px;">
            <div style="width:44px; height:44px; border-radius:10px; background:#eff6ff; color:#3b82f6; display:flex; align-items:center; justify-content:center; font-size:20px;">
                <i class="ph ph-calendar-blank"></i>
            </div>
            <div><div style="font-size:20px; font-weight:800;"><?= $total_events ?></div><div style="font-size:11px; color:var(--text-muted); font-weight:600;">This Month</div></div>
        </div>
        <div class="cal-card" style="padding:20px; display:flex; align-items:center; gap:14px;">
            <div style="width:44px; height:44px; border-radius:10px; background:#fef2f2; color:#ef4444; display:flex; align-items:center; justify-content:center; font-size:20px;">
                <i class="ph ph-clock-countdown"></i>
            </div>
            <div><div style="font-size:20px; font-weight:800;"><?= $upcoming_count ?></div><div style="font-size:11px; color:var(--text-muted); font-weight:600;">This Week</div></div>
        </div>
        <div class="cal-card" style="padding:20px; display:flex; align-items:center; gap:14px;">
            <div style="width:44px; height:44px; border-radius:10px; background:#f0fdf4; color:#22c55e; display:flex; align-items:center; justify-content:center; font-size:20px;">
                <i class="ph ph-check-circle"></i>
            </div>
            <div><div style="font-size:20px; font-weight:800;"><?= date('d M') ?></div><div style="font-size:11px; color:var(--text-muted); font-weight:600;">Today</div></div>
        </div>
    </div>

    <div class="cal-layout">
        <!-- Calendar Grid -->
        <div class="cal-card">
            <div class="cal-nav">
                <a href="?month=<?= $prevMonth ?>&year=<?= $prevYear ?>" class="cal-nav-btn"><i class="ph ph-caret-left"></i></a>
                <h2><?= $monthName ?> <?= $year ?></h2>
                <a href="?month=<?= $nextMonth ?>&year=<?= $nextYear ?>" class="cal-nav-btn"><i class="ph ph-caret-right"></i></a>
            </div>

            <div class="cal-grid">
                <?php 
                $dayNames = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
                foreach($dayNames as $dn): ?>
                    <div class="cal-day-header"><?= $dn ?></div>
                <?php endforeach; ?>

                <?php 
                // Empty cells before first day
                for ($i = 0; $i < $startDayOfWeek; $i++): ?>
                    <div class="cal-cell empty"></div>
                <?php endfor; ?>

                <?php for ($day = 1; $day <= $daysInMonth; $day++): 
                    $isToday = ($day == date('j') && $month == date('n') && $year == date('Y'));
                    $dayEvents = $eventsByDay[$day] ?? [];
                ?>
                    <div class="cal-cell <?= $isToday ? 'today' : '' ?>">
                        <div class="day-num"><?= $day ?></div>
                        <?php foreach(array_slice($dayEvents, 0, 2) as $ev): ?>
                            <span class="cal-event-dot" style="background:<?= $ev['color'] ?>;"><?= htmlspecialchars(substr($ev['event_title'], 0, 15)) ?></span>
                        <?php endforeach; ?>
                        <?php if (count($dayEvents) > 2): ?>
                            <span style="font-size:9px; color:var(--text-muted); font-weight:700;">+<?= count($dayEvents) - 2 ?> more</span>
                        <?php endif; ?>
                    </div>
                <?php endfor; ?>

                <?php 
                // Fill remaining cells
                $totalCells = $startDayOfWeek + $daysInMonth;
                $remaining = (7 - ($totalCells % 7)) % 7;
                for ($i = 0; $i < $remaining; $i++): ?>
                    <div class="cal-cell empty"></div>
                <?php endfor; ?>
            </div>

            <!-- Legend -->
            <div class="event-legend">
                <div class="legend-item"><div class="legend-dot" style="background:#3b82f6;"></div>Academic</div>
                <div class="legend-item"><div class="legend-dot" style="background:#22c55e;"></div>Sports</div>
                <div class="legend-item"><div class="legend-dot" style="background:#a855f7;"></div>Cultural</div>
                <div class="legend-item"><div class="legend-dot" style="background:#f59e0b;"></div>Meeting</div>
                <div class="legend-item"><div class="legend-dot" style="background:#ef4444;"></div>Holiday</div>
                <div class="legend-item"><div class="legend-dot" style="background:#6b7280;"></div>Other</div>
            </div>
        </div>

        <!-- Upcoming Events Sidebar -->
        <div class="cal-card upcoming-panel">
            <div class="upcoming-header">
                <h3><i class="ph ph-clock" style="color:var(--primary); margin-right:6px;"></i>Upcoming Events</h3>
            </div>
            <div class="upcoming-list">
                <?php if (empty($upcoming)): ?>
                    <div style="text-align:center; padding:40px; color:#9ca3af;">
                        <i class="ph ph-calendar-x" style="font-size:36px;"></i>
                        <p style="margin-top:8px; font-size:12px;">No upcoming events</p>
                    </div>
                <?php else: ?>
                    <?php foreach($upcoming as $ev): 
                        $typeColors = ['academic' => '#3b82f6', 'sports' => '#22c55e', 'cultural' => '#a855f7', 'meeting' => '#f59e0b', 'holiday' => '#ef4444', 'other' => '#6b7280'];
                        $tc = $typeColors[$ev['event_type']] ?? '#6b7280';
                    ?>
                        <div class="upcoming-item" style="position:relative;">
                            <div class="upcoming-date-box" style="background:<?= $tc ?>12; color:<?= $tc ?>;">
                                <div class="month"><?= date('M', strtotime($ev['event_date'])) ?></div>
                                <div class="day"><?= date('d', strtotime($ev['event_date'])) ?></div>
                            </div>
                            <div class="upcoming-info" style="flex:1;">
                                <h4><?= htmlspecialchars((string)($ev['event_title'] ?? '')) ?></h4>
                                <p>
                                    <?php if ($ev['start_time']): ?>
                                        <i class="ph ph-clock" style="margin-right:2px;"></i><?= date('h:i A', strtotime($ev['start_time'])) ?>
                                    <?php endif; ?>
                                    <?php if ($ev['venue']): ?>
                                        · <i class="ph ph-map-pin" style="margin-right:2px;"></i><?= htmlspecialchars((string)($ev['venue'] ?? '')) ?>
                                    <?php endif; ?>
                                </p>
                                <span class="upcoming-type-badge" style="background:<?= $tc ?>15; color:<?= $tc ?>; margin-top:4px; display:inline-block;"><?= ucfirst($ev['event_type']) ?></span>
                            </div>
                            <form method="POST" style="position:absolute; top:14px; right:14px;">
                                <input type="hidden" name="delete_event" value="1">
                                <input type="hidden" name="event_id" value="<?= $ev['id'] ?>">
                                <button type="submit" onclick="return confirm('Delete this event?')" style="background:none; border:none; cursor:pointer; color:#cbd5e1; font-size:14px;" title="Delete">
                                    <i class="ph ph-trash"></i>
                                </button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Create Event Modal -->
<div class="event-modal" id="event-modal">
    <div class="event-modal-box">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:24px;">
            <div>
                <h3 style="margin:0; font-size:18px; font-weight:800;">Schedule Event</h3>
                <p style="margin:4px 0 0; font-size:12px; color:var(--text-muted);">Add an event to the institutional calendar</p>
            </div>
            <button onclick="document.getElementById('event-modal').style.display='none'" style="background:none; border:none; cursor:pointer; color:#9ca3af;">
                <i class="ph ph-x" style="font-size:22px;"></i>
            </button>
        </div>
        <form method="POST">
            <input type="hidden" name="create_event" value="1">

            <label>EVENT TITLE</label>
            <input type="text" name="event_title" placeholder="e.g. Inter-House Sports Day" required>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:14px;">
                <div>
                    <label>EVENT TYPE</label>
                    <select name="event_type">
                        <option value="academic">Academic</option>
                        <option value="sports">Sports</option>
                        <option value="cultural">Cultural</option>
                        <option value="meeting">Meeting</option>
                        <option value="holiday">Holiday</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div>
                    <label>EVENT DATE</label>
                    <input type="date" name="event_date" value="<?= date('Y-m-d') ?>" required>
                </div>
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:14px;">
                <div>
                    <label>START TIME</label>
                    <input type="time" name="start_time">
                </div>
                <div>
                    <label>END TIME</label>
                    <input type="time" name="end_time">
                </div>
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:14px;">
                <div>
                    <label>VENUE</label>
                    <input type="text" name="venue" placeholder="e.g. School Auditorium">
                </div>
                <div>
                    <label>AUDIENCE</label>
                    <select name="audience">
                        <option value="all">Everyone</option>
                        <option value="students">Students</option>
                        <option value="parents">Parents</option>
                        <option value="employees">Staff Only</option>
                    </select>
                </div>
            </div>

            <label>DESCRIPTION (OPTIONAL)</label>
            <textarea name="event_description" placeholder="Additional details about the event..."></textarea>

            <button type="submit" class="btn-primary" style="width:100%; padding:14px; font-weight:800; display:flex; align-items:center; justify-content:center; gap:8px; font-size:14px;">
                <i class="ph ph-calendar-plus"></i> Schedule Event
            </button>
        </form>
    </div>
</div>

<?php require ROOT_PATH . '/app/Views/school_admin/layout/footer.php'; ?>
