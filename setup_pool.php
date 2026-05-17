<?php
/**
 * RosmonSMS — Database Pool Setup
 * =================================
 * Visit: https://yourdomain.com/setup_pool.php?key=ROSMON_POOL_SETUP_2026
 *
 * Run this after creating all 50 tenant databases in cPanel.
 * It registers all derived credential slots into the db_pool table.
 *
 * ⚠️ DELETE THIS FILE after setup is complete!
 */

define('ROOT_PATH', __DIR__);
session_start();

require_once __DIR__ . '/config/tenant_manager.php';

$secretKey = 'ROSMON_POOL_SETUP_2026';
$provided  = $_GET['key'] ?? $_POST['key'] ?? '';
if ($provided !== $secretKey && !(isset($_SESSION['role']) && $_SESSION['role'] === 'super_admin')) {
    die("<div style='font-family:sans-serif;padding:40px;text-align:center;'>
          <h2 style='color:#dc2626;'>&#128274; Access Denied</h2>
          <p>Append <code>?key={$secretKey}</code> to the URL to proceed.</p>
         </div>");
}

$action = $_POST['action'] ?? '';
$result = null;

if ($action === 'populate') {
    $result = TenantManager::populatePool();
}

// Show pool stats
$stats = TenantManager::getPoolStats();
$creds = TenantManager::getSupervisorCredentials();

// Preview all 50 slots
$preview = [];
$poolSize = TenantManager::getPoolSize();
for ($i = 1; $i <= $poolSize; $i++) {
    $preview[$i] = TenantManager::deriveTenantCredentials($i);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>RosmonSMS Pool Setup</title>
<style>
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:'Segoe UI',sans-serif;background:#f1f5f9;padding:32px}
  .box{background:#fff;border-radius:12px;box-shadow:0 2px 16px rgba(0,0,0,.08);padding:32px;max-width:900px;margin:0 auto 24px}
  h1{font-size:22px;color:#1e293b;margin-bottom:4px}
  h2{font-size:17px;color:#1e293b;margin:20px 0 10px}
  .badge{display:inline-block;padding:4px 12px;border-radius:20px;font-size:12px;font-weight:700}
  .badge-free{background:#dcfce7;color:#16a34a}
  .badge-used{background:#fee2e2;color:#dc2626}
  table{width:100%;border-collapse:collapse;font-size:13px}
  th,td{padding:8px 12px;border:1px solid #e2e8f0;text-align:left}
  th{background:#f8fafc;font-weight:700;color:#374151}
  .btn{display:inline-block;padding:11px 24px;background:#2563eb;color:#fff;border:none;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer}
  .btn:hover{background:#1d4ed8}
  .btn-green{background:#16a34a}.btn-green:hover{background:#15803d}
  .stat{display:inline-block;padding:16px 24px;border-radius:10px;margin-right:12px;margin-bottom:12px;text-align:center}
  .stat .num{font-size:28px;font-weight:800}
  .stat .lbl{font-size:12px;font-weight:600;color:#64748b;margin-top:4px}
  .alert{padding:12px 16px;border-radius:8px;margin-bottom:16px;font-size:14px}
  .alert-success{background:#f0fdf4;color:#16a34a;border:1px solid #bbf7d0}
  .alert-info{background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe}
  code{background:#f1f5f9;padding:2px 6px;border-radius:4px;font-size:12px}
  .scroll{max-height:400px;overflow-y:auto;border:1px solid #e2e8f0;border-radius:8px}
</style>
</head>
<body>
<div class="box">
  <h1>&#128197; RosmonSMS — Database Pool Manager</h1>
  <p style="color:#64748b;font-size:14px;margin-top:4px;">Manage the 50-slot tenant database pool.</p>

  <?php if ($result): ?>
  <div class="alert alert-success" style="margin-top:16px;">
    &#10003; Pool populated: <strong><?= $result['added'] ?></strong> slots added,
    <strong><?= $result['skipped'] ?></strong> already existed.
    <?php if (!empty($result['errors'])): ?>
      <br>Errors: <?= implode(', ', $result['errors']) ?>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <div class="alert alert-info" style="margin-top:16px;">
    <b>Supervisor DB:</b> <code><?= htmlspecialchars($creds['dbname']) ?></code>
    on <code><?= htmlspecialchars($creds['host']) ?></code>
    (user: <code><?= htmlspecialchars($creds['user']) ?></code>)
  </div>

  <div style="margin:20px 0;">
    <div class="stat" style="background:#eff6ff;color:#2563eb;">
      <div class="num"><?= $stats['total'] ?>/<?= $poolSize ?></div>
      <div class="lbl">POOL SLOTS</div>
    </div>
    <div class="stat" style="background:#f0fdf4;color:#16a34a;">
      <div class="num"><?= $stats['available'] ?></div>
      <div class="lbl">AVAILABLE</div>
    </div>
    <div class="stat" style="background:#fef2f2;color:#dc2626;">
      <div class="num"><?= $stats['assigned'] ?></div>
      <div class="lbl">ASSIGNED</div>
    </div>
  </div>

  <form method="POST" action="?key=<?= htmlspecialchars($provided) ?>">
    <input type="hidden" name="action" value="populate">
    <button class="btn btn-green">&#43; Register All <?= $poolSize ?> Slots in Pool</button>
    <span style="font-size:13px;color:#64748b;margin-left:12px;">(safe to run multiple times — skips existing)</span>
  </form>
</div>

<div class="box">
  <h2>All <?= $poolSize ?> Derived Tenant Credentials</h2>
  <p style="color:#64748b;font-size:13px;margin-bottom:12px;">
    Create each of these databases in cPanel with user = password = db_name before running the installer.
  </p>
  <div class="scroll">
  <table>
    <thead><tr><th>#</th><th>Database Name</th><th>Username</th><th>Password</th><th>Status</th></tr></thead>
    <tbody>
    <?php
    // Index assigned slots by db_name
    $assignedMap = [];
    foreach ($stats['entries'] as $entry) {
        $assignedMap[$entry['db_name']] = $entry;
    }
    for ($i = 1; $i <= $poolSize; $i++):
        $cred = $preview[$i];
        $inPool   = isset($assignedMap[$cred['db_name']]);
        $assigned = $inPool && $assignedMap[$cred['db_name']]['is_assigned'];
        $school   = $assigned ? ($assignedMap[$cred['db_name']]['institution_name'] ?? 'School #'.$assignedMap[$cred['db_name']]['school_id']) : '';
    ?>
    <tr>
      <td><?= $i ?></td>
      <td><code><?= htmlspecialchars($cred['db_name']) ?></code></td>
      <td><code><?= htmlspecialchars($cred['db_user']) ?></code></td>
      <td><code><?= htmlspecialchars($cred['db_pass']) ?></code></td>
      <td>
        <?php if ($assigned): ?>
          <span class="badge badge-used">Assigned: <?= htmlspecialchars($school) ?></span>
        <?php elseif ($inPool): ?>
          <span class="badge badge-free">Free</span>
        <?php else: ?>
          <span class="badge" style="background:#fefce8;color:#ca8a04;">Not Registered</span>
        <?php endif; ?>
      </td>
    </tr>
    <?php endfor; ?>
    </tbody>
  </table>
  </div>
</div>

<div class="box" style="text-align:center;">
  <p style="color:#dc2626;font-weight:700;">&#9888; Delete this file (setup_pool.php) after setup is complete!</p>
</div>
</body>
</html>
