<?php
$pageTitle = 'My Academic Timetable - Rosmon SMS';
require ROOT_PATH . '/app/Views/employee/layout/header.php';

$me = $_SESSION['user_id'] ?? 10;

// Fetch Weekdays
$days = $pdo->query("SELECT * FROM tt_weekdays ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);

// Fetch Periods
$periods = $pdo->query("SELECT * FROM tt_periods ORDER BY start_time ASC")->fetchAll(PDO::FETCH_ASSOC);

// Fetch Timetable Entries for this teacher
$stmt = $pdo->prepare("
    SELECT t.*, c.class_name, c.arm, s.subject_name 
    FROM timetables t
    JOIN classes c ON t.class_id = c.id
    JOIN subjects s ON t.subject_id = s.id
    WHERE t.employee_id = ?
");
$stmt->execute([$me]);
$entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Organize entries into a map [day_id][period_id]
$schedule = [];
foreach ($entries as $e) {
    if (!isset($schedule[$e['day_id']])) $schedule[$e['day_id']] = [];
    $schedule[$e['day_id']][$e['period_id']] = $e;
}
?>

<style>
    .tt-container {
        background: white;
        border-radius: 16px;
        border: 1px solid #f1f5f9;
        overflow: hidden;
        margin-top: 25px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.03);
    }

    .tt-table {
        width: 100%;
        border-collapse: collapse;
    }

    .tt-table th, .tt-table td {
        border: 1px solid #f1f5f9;
        padding: 15px;
        text-align: center;
        min-width: 150px;
    }

    .tt-table th {
        background: #f8fafc;
        font-size: 11px;
        font-weight: 800;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .tt-header-day {
        background: #fdfdfd;
        font-weight: 700;
        color: #1e293b;
        font-size: 13px;
        text-align: left !important;
        width: 120px;
    }

    .tt-period-info {
        font-size: 10px;
        color: #94a3b8;
        font-weight: 600;
        margin-top: 4px;
    }

    .slot-card {
        background: #f1f5f9;
        border-radius: 8px;
        padding: 12px;
        text-align: left;
        border-left: 4px solid var(--primary);
    }

    .slot-subject {
        font-size: 12px;
        font-weight: 800;
        color: #1e293b;
        margin-bottom: 4px;
    }

    .slot-class {
        font-size: 10px;
        color: #64748b;
        font-weight: 700;
    }

    .empty-slot {
        font-size: 10px;
        color: #cbd5e1;
        font-style: italic;
    }
</style>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
    <div>
        <h1 style="font-size:22px; font-weight:900; color:#111827; margin:0 0 5px 0;">My Weekly Schedule</h1>
        <p style="font-size:14px; color:#64748b; margin:0;">View your assigned lecture periods across all classes.</p>
    </div>
    <button class="btn-primary" onclick="window.print()" style="padding:10px 20px; border-radius:10px; display:flex; align-items:center; gap:8px;">
        <i class="ph ph-printer"></i>
        Print Timetable
    </button>
</div>

<div class="tt-container">
    <table class="tt-table">
        <thead>
            <tr>
                <th style="width:120px;">DAY \ PERIOD</th>
                <?php foreach($periods as $p): ?>
                    <th>
                        <?= htmlspecialchars($p['period_name']) ?>
                        <div class="tt-period-info"><?= date('h:i A', strtotime($p['start_time'])) ?> - <?= date('h:i A', strtotime($p['end_time'])) ?></div>
                    </th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach($days as $d): ?>
                <tr>
                    <td class="tt-header-day"><?= $d['day_name'] ?></td>
                    <?php foreach($periods as $p): ?>
                        <td>
                            <?php if (isset($schedule[$d['id']][$p['id']])): 
                                $e = $schedule[$d['id']][$p['id']];
                            ?>
                                <div class="slot-card">
                                    <div class="slot-subject"><?= htmlspecialchars($e['subject_name']) ?></div>
                                    <div class="slot-class"><?= htmlspecialchars($e['class_name']) ?> <?= htmlspecialchars($e['arm']) ?></div>
                                </div>
                            <?php else: ?>
                                <span class="empty-slot">-</span>
                            <?php endif; ?>
                        </td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require ROOT_PATH . '/app/Views/employee/layout/footer.php'; ?>
