<?php
/**
 * Live Class Room Management
 */
require_once ROOT_PATH . '/config/database.php';

$pageTitle = 'Live Classroom - Rosmon SMS';
require ROOT_PATH . '/app/Views/school_admin/layout/header.php';
require ROOT_PATH . '/app/Views/school_admin/layout/sidebar.php';
?>

<div class="main-container">
    <div class="top-header">
        <div class="greeting">Academics / <span style="color:var(--primary)">Live Classroom Hub</span></div>
        <div class="header-actions">
            <button class="btn-primary" style="padding:10px 20px; border-radius:30px;"><i class="ph ph-video-camera"></i> Start New Session</button>
        </div>
    </div>

    <!-- Live Sessions Grid -->
    <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap:24px; margin-top:24px;">
        <!-- Virtual Room Card -->
        <div class="crud-card" style="padding:0; overflow:hidden;">
            <div style="background: linear-gradient(135deg, #4f46e5, #6366f1); height:120px; position:relative; display:flex; align-items:center; justify-content:center; color:#fff;">
                <i class="ph ph-broadcast" style="font-size:48px; opacity:0.3; position:absolute; right:10px; bottom:10px;"></i>
                <h2 style="font-size:20px; font-weight:800; text-shadow:0 2px 4px rgba(0,0,0,0.1);">Global Staff Room</h2>
            </div>
            <div style="padding:20px;">
                <div style="display:flex; justify-content:space-between; margin-bottom:15px; align-items:center;">
                    <span style="font-size:11px; font-weight:800; color:var(--text-muted); text-transform:uppercase;">ACTIVE ROOM</span>
                    <span style="background:#def7ec; color:#03543f; padding:4px 10px; border-radius:20px; font-size:10px; font-weight:800;">OPEN FOR ALL</span>
                </div>
                <p style="font-size:13px; color:var(--text-dark); margin-bottom:20px;">The central virtual meeting point for all staff members to discuss logistics and coordination.</p>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                    <button class="btn-primary" style="background:var(--primary); color:#fff; width:100%; border:none; padding:12px; border-radius:10px; font-weight:700; cursor:pointer;">Join Meeting</button>
                    <button class="btn-primary" style="background:#fff; color:var(--text-dark); border:1px solid var(--border); width:100%; padding:12px; border-radius:10px; font-weight:700; cursor:pointer;">Copy Link</button>
                </div>
            </div>
        </div>

        <!-- Class Room Card -->
        <div class="crud-card" style="padding:0; overflow:hidden; opacity:0.7;">
            <div style="background: linear-gradient(135deg, #10b981, #34d399); height:120px; position:relative; display:flex; align-items:center; justify-content:center; color:#fff;">
                <i class="ph ph-chalkboard-teacher" style="font-size:48px; opacity:0.3; position:absolute; right:10px; bottom:10px;"></i>
                <h2 style="font-size:20px; font-weight:800; text-shadow:0 2px 4px rgba(0,0,0,0.1);">Grade 10 - Mathematics</h2>
            </div>
            <div style="padding:20px;">
                <div style="display:flex; justify-content:space-between; margin-bottom:15px; align-items:center;">
                    <span style="font-size:11px; font-weight:800; color:var(--text-muted); text-transform:uppercase;">SCHEDULED</span>
                    <span style="background:#f3f4f6; color:#1f2937; padding:4px 10px; border-radius:20px; font-size:10px; font-weight:800;">STARTS IN 45M</span>
                </div>
                <p style="font-size:13px; color:var(--text-dark); margin-bottom:20px;">Calculus Introduction session. Ensure all students have their graph sheets ready.</p>
                <button class="btn-primary" style="background:#fff; color:var(--text-muted); border:1px solid var(--border); width:100%; padding:12px; border-radius:10px; font-weight:700; cursor:not-allowed;">Waiting for Host...</button>
            </div>
        </div>
    </div>

    <!-- Integration Settings -->
    <div class="crud-card" style="margin-top:30px;">
        <div class="crud-header">
            <h2 class="crud-title"><i class="ph ph-gear-six"></i> Interface & Server Settings</h2>
        </div>
        <div style="padding:20px; display:grid; grid-template-columns: 1fr 1fr; gap:40px;">
            <div>
                <h3 style="font-size:15px; font-weight:700; margin-bottom:10px; color:var(--primary);">Meeting Provider</h3>
                <p style="font-size:13px; color:var(--text-muted); margin-bottom:20px;">Select your preferred infrastructure for high-definition video delivery.</p>
                <div style="display:flex; flex-direction:column; gap:12px;">
                    <label style="display:flex; align-items:center; gap:12px; padding:15px; border:1px solid var(--primary); background:rgba(79, 70, 229, 0.05); border-radius:12px; cursor:pointer;">
                        <input type="radio" name="provider" checked>
                        <div style="display:flex; align-items:center; gap:10px;">
                            <img src="https://upload.wikimedia.org/wikipedia/commons/e/e4/Google_Meet_icon_%282020%29.svg" style="width:24px;">
                            <span style="font-weight:700;">Google Meet Integration</span>
                        </div>
                    </label>
                    <label style="display:flex; align-items:center; gap:12px; padding:15px; border:1px solid var(--border); border-radius:12px; cursor:pointer;">
                        <input type="radio" name="provider">
                        <div style="display:flex; align-items:center; gap:10px;">
                            <img src="https://upload.wikimedia.org/wikipedia/commons/9/9b/Jitsi_logo.svg" style="width:24px;">
                            <span style="font-weight:700;">Jitsi Meet (Self-Hosted)</span>
                        </div>
                    </label>
                </div>
            </div>
            <div>
                <h3 style="font-size:15px; font-weight:700; margin-bottom:10px; color:var(--primary);">Recording & Auto-Post</h3>
                <p style="font-size:13px; color:var(--text-muted); margin-bottom:20px;">Configure how sessions are archived for student review.</p>
                <div style="display:flex; flex-direction:column; gap:15px;">
                    <label style="display:flex; justify-content:space-between; align-items:center; background:#f9fafb; padding:15px; border-radius:12px;">
                        <span style="font-weight:600; font-size:13px;">Auto-Record Sessions</span>
                        <input type="checkbox" checked style="width:18px; height:18px;">
                    </label>
                    <label style="display:flex; justify-content:space-between; align-items:center; background:#f9fafb; padding:15px; border-radius:12px;">
                        <span style="font-weight:600; font-size:13px;">Notify Students on Start</span>
                        <input type="checkbox" checked style="width:18px; height:18px;">
                    </label>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require ROOT_PATH . '/app/Views/school_admin/layout/footer.php'; ?>
