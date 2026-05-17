<?php
$pageTitle = 'Live Class - Teacher Dashboard';
require ROOT_PATH . '/app/Views/employee/layout/header.php';
?>

<style>
    /* Mimic the screenshot exactly */
    .live-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 30px;
        margin-top: 50px; /* Give space for absolute top right profile */
    }

    /* Left form styles */
    .form-group {
        margin-bottom: 20px;
    }
    .form-control {
        width: 100%;
        padding: 15px;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        font-size: 14px;
        color: #334155;
        outline: none;
        transition: border 0.2s;
        background: #ffffff;
    }
    .form-control::placeholder { color: #94a3b8; font-weight: 500;}
    .form-control:focus { border-color: var(--primary); }
    
    .schedule-check {
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 14px;
        color: #475569;
        font-weight: 600;
        margin-bottom: 25px;
        cursor: pointer;
    }
    .schedule-check input {
        width: 16px;
        height: 16px;
        cursor: pointer;
    }

    .char-limit {
        font-size: 11px;
        color: #64748b;
        margin-top: 5px;
        display: block;
    }

    .btn-create {
        width: 100%;
        background: var(--primary);
        color: white;
        font-weight: 700;
        font-size: 14px;
        border: none;
        padding: 15px;
        border-radius: 6px;
        cursor: pointer;
        transition: background 0.2s;
    }
    .btn-create:hover { background: #111827; }

    /* Right column styles */
    .time-banner {
        background: var(--primary); /* Dark blue */
        color: white;
        text-align: center;
        padding: 25px 20px;
        border-radius: 12px;
        margin-bottom: 20px;
        box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
    }
    .time-banner h2 { margin: 0 0 5px 0; font-size: 26px; font-weight: 700; letter-spacing: 0.5px;}
    .time-banner p { margin: 0; font-size: 14px; font-weight: 600; opacity: 0.9;}

    .tabs {
        display: flex;
        gap: 20px;
        border-bottom: 1px solid #e2e8f0;
        margin-bottom: 40px;
        justify-content: space-between;
        padding: 0 10px;
    }
    .tab-link {
        padding: 12px 10px;
        font-size: 11px;
        font-weight: 800;
        color: #64748b;
        cursor: pointer;
        border-bottom: 2px solid transparent;
    }
    .tab-link.active {
        color: var(--primary);
        border-bottom-color: var(--primary);
    }

    .empty-state {
        text-align: center;
        color: #475569;
    }
    .empty-state h3 {
        font-size: 16px;
        font-weight: 700;
        color: #475569;
        margin-bottom: 30px;
    }
    
    /* Illustration mock using CSS & Phosphor */
    .illustration {
        display: flex;
        justify-content: center;
        align-items: center;
        width: 100%;
        height: 250px;
        margin: 0 auto;
        opacity: 0.8;
    }
    .ill-svg {
        max-width: 80%;
        max-height: 100%;
    }
</style>

<div class="live-grid">
    <!-- Left form side -->
    <div style="display:flex; flex-direction:column; gap:20px;">
        <input type="text" class="form-control" placeholder="Meeting Title">
        <input type="text" class="form-control" placeholder="Meeting ID">
        
        <select class="form-control" style="color:#64748b; font-weight:600; appearance:none; background-image: url('data:image/svg+xml;utf8,<svg fill=%22%2364748b%22 height=%2224%22 viewBox=%220 0 24 24%22 width=%2224%22 xmlns=%22http://www.w3.org/2000/svg%22><path d=%22M7 10l5 5 5-5z%22/></svg>'); background-repeat: no-repeat; background-position-x: 98%; background-position-y: 50%;">
            <option value="">Meeting With</option>
            <option value="class_1">Basic 1</option>
            <option value="class_2">Basic 2</option>
        </select>
        
        <label class="schedule-check">
            <input type="checkbox" style="border-radius:4px; border:1px solid #cbd5e1;"> I want to schedule this meeting.
        </label>
        
        <div style="border: 1px solid #e2e8f0; border-radius: 6px; background:white; overflow:hidden;">
            <textarea class="form-control" rows="6" placeholder="Meeting Description" style="resize:none; border:none; box-shadow:none; padding-bottom:5px;"></textarea>
            <div style="padding: 10px 15px; font-size: 11px; color: #94a3b8; font-weight:600; background:white; border-top:1px solid #f8fafc;">0 of 200 max characters</div>
        </div>
        
        <button class="btn-create">Create & Join</button>
    </div>

    <!-- Right time banner and tabs -->
    <div>
        <div class="time-banner">
            <h2 id="live-time">00:00:00 AM</h2>
            <p id="live-date">Sunday, Mar 22, 2026</p>
        </div>

        <div style="background:white; border-radius:12px; padding:20px; box-shadow:0 4px 10px rgba(0,0,0,0.02);">
            <div class="tabs">
                <div class="tab-link active">All Meetings</div>
                <div class="tab-link">Today</div>
                <div class="tab-link">Tomorrow</div>
                <div class="tab-link">Self Hosted</div>
                <div class="tab-link">Invitations</div>
            </div>

            <div class="empty-state">
                <h3>No meeting found.</h3>
                
                <div class="illustration">
                    <svg viewBox="0 0 500 300" class="ill-svg" xmlns="http://www.w3.org/2000/svg">
                        <rect width="200" height="120" x="250" y="50" fill="#e0e7ff" rx="4"/>
                        <rect width="180" height="100" x="260" y="60" fill="#bfdbfe" rx="2"/>
                        <circle cx="310" cy="85" r="15" fill="#3b82f6"/>
                        <circle cx="390" cy="85" r="15" fill="#3b82f6"/>
                        <circle cx="350" cy="130" r="15" fill="#2563eb"/>
                        <path fill="#e2e8f0" d="M120 220 h 300 v 8 h -300 z"/>
                        <path fill="#cbd5e1" d="M150 228 l -20 70 h 8 l 15 -70 z"/>
                        <path fill="#cbd5e1" d="M390 228 l 20 70 h 8 l -15 -70 z"/>
                        <!-- Person 1 -->
                        <circle cx="150" cy="140" r="20" fill="#fb923c"/>
                        <path fill="#2563eb" d="M130 160 c 0 -10, 40 -10, 40 0 v 60 h -40 z"/>
                        <!-- Person 2 -->
                        <circle cx="210" cy="150" r="18" fill="#facc15"/>
                        <path fill="#3b82f6" d="M190 168 c 0 -10, 40 -10, 40 0 v 52 h -40 z"/>
                        
                        <!-- decorative plant -->
                        <path fill="#dcfce7" d="M420 200 c 15 -20, 40 -10, 40 -10 c -10 20, -30 20, -40 10"/>
                        <path fill="#bbf7d0" d="M420 200 c -15 -30, -10 -40, -10 -40 c 25 10, 20 30, 10 40"/>
                        <path fill="#86efac" d="M420 200 c 10 -30, 30 -30, 30 -30 c 10 30, -20 40, -30 30"/>
                        <rect width="20" height="25" x="410" y="200" fill="#cbd5e1" rx="2"/>
                    </svg>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Live ticking clock for the blue banner exactly like the target interface
    function updateClock() {
        const now = new Date();
        
        let hours = now.getHours();
        const ampm = hours >= 12 ? 'PM' : 'AM';
        hours = hours % 12;
        hours = hours ? hours : 12; 
        
        const minutes = now.getMinutes().toString().padStart(2, '0');
        const seconds = now.getSeconds().toString().padStart(2, '0');
        const timeString = `${hours.toString().padStart(2, '0')}:${minutes}:${seconds} ${ampm}`;
        
        const days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        
        const dateString = `${days[now.getDay()]}, ${months[now.getMonth()]} ${now.getDate()}, ${now.getFullYear()}`;
        
        document.getElementById('live-time').innerText = timeString;
        document.getElementById('live-date').innerText = dateString;
    }
    
    updateClock();
    setInterval(updateClock, 1000);
</script>

<?php require ROOT_PATH . '/app/Views/employee/layout/footer.php'; ?>
