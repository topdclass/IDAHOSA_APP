<!-- SIDEBAR -->
    <div class="sidebar">
        <!-- Logo -->
        <div class="brand-logo-container">
            <?php if (!empty($globalSchoolLogo)): ?>
                <img src="<?= WEB_ROOT . '/public' . $globalSchoolLogo ?>" alt="Logo" style="width:48px; height:48px; border-radius:50%; object-fit:cover; border:2px solid rgba(255,255,255,0.2);">
            <?php else: ?>
                <div class="logo-circle"><?= substr($globalSchoolName ?? 'R', 0, 1) ?><?= substr($globalSchoolName ?? 'S', -1) ?></div>
            <?php endif; ?>
        </div>

        <!-- School Profile Box -->
        <div class="school-profile-block">
            <div class="profile-icon"><?= substr($globalSchoolName ?? 'R', 0, 1) ?>S</div>
            <div class="profile-texts w-full" style="max-width: 130px;">
                <h3 style="white-space:nowrap; overflow:hidden; text-overflow:ellipsis; font-size:13px;" title="<?= htmlspecialchars($globalSchoolName ?? 'Rosmon International School') ?>"><?= htmlspecialchars($globalSchoolName ?? 'Rosmon International School') ?></h3>
                <p><?= htmlspecialchars($globalSchoolType ?? 'Institute') ?></p>
            </div>
        </div>

        <!-- Navigation Menu -->
        <div class="nav-list">
            <a href="<?= WEB_ROOT ?>/school-admin/dashboard" class="nav-item <?= strpos($_SERVER['REQUEST_URI'] ?? '', 'dashboard') !== false && strpos($_SERVER['REQUEST_URI'] ?? '', 'school-admin/dashboard') !== false ? 'active' : '' ?>">
                <div class="nav-left"><i class="ph ph-trend-up"></i> Dashboard</div>
            </a>
            <div class="nav-item tree-menu" onclick="toggleSubmenu('gen-settings')">
                <div class="nav-link" style="cursor:pointer;"><div class="nav-left"><i class="ph ph-gear"></i> General Settings</div></div>
                <i class="ph ph-caret-down nav-caret"></i>
            </div>
            <div class="submenu" id="gen-settings">
                <a href="<?= WEB_ROOT ?>/school-admin/general-settings/institution-profile" class="submenu-item">Institution Profile</a>
                <a href="<?= WEB_ROOT ?>/school-admin/general-settings/fee-particulars" class="submenu-item">Fee Particulars</a>
                <a href="<?= WEB_ROOT ?>/school-admin/general-settings/fee-challan-settings" class="submenu-item">Details For Fee Challan</a>
                <a href="<?= WEB_ROOT ?>/school-admin/general-settings/rules-regulations" class="submenu-item">Rules &amp; Regulations</a>
                <a href="<?= WEB_ROOT ?>/school-admin/general-settings/marks-grading" class="submenu-item">Marks Grading</a>
                <a href="<?= WEB_ROOT ?>/school-admin/general-settings/academic-settings" class="submenu-item">Academic Settings</a>
                <a href="<?= WEB_ROOT ?>/school-admin/general-settings/account-settings" class="submenu-item">Account Settings</a>
            </div>

            <div class="nav-item tree-menu" onclick="toggleSubmenu('rbac-menu')">
                <div class="nav-link" style="cursor:pointer;"><div class="nav-left"><i class="ph ph-shield-check"></i> Privileges & Guard</div></div>
                <i class="ph ph-caret-down nav-caret"></i>
            </div>
            <div class="submenu" id="rbac-menu">
                <a href="<?= WEB_ROOT ?>/school-admin/privileges/roles" class="submenu-item">Roles & Permissions</a>
                <a href="<?= WEB_ROOT ?>/school-admin/privileges/assign" class="submenu-item">Staff Privilege Assign</a>
            </div>

            <div class="nav-item tree-menu" onclick="toggleSubmenu('portals-menu')">
                <div class="nav-link" style="cursor:pointer;"><div class="nav-left"><i class="ph ph-users-four"></i> Institutional Portals</div></div>
                <i class="ph ph-caret-down nav-caret"></i>
            </div>
            <div class="submenu" id="portals-menu">
                <a href="<?= WEB_ROOT ?>/school-admin/employees/add" class="submenu-item">Create Portal User</a>
                <a href="<?= WEB_ROOT ?>/principal/dashboard" class="submenu-item">Principal Terminal</a>
                <a href="<?= WEB_ROOT ?>/finance/dashboard" class="submenu-item">Finance Terminal</a>
                <a href="<?= WEB_ROOT ?>/audit/dashboard" class="submenu-item">Audit Terminal</a>
                <a href="<?= WEB_ROOT ?>/pta-chairman/dashboard" class="submenu-item">PTA Chairman Terminal</a>
            </div>
            <div class="nav-item tree-menu" onclick="toggleSubmenu('attendance-menu')">
                <div class="nav-link" style="cursor:pointer;"><div class="nav-left"><i class="ph ph-buildings"></i> Attendance</div></div>
                <i class="ph ph-caret-down nav-caret"></i>
            </div>
            <div class="submenu" id="attendance-menu">
                <a href="<?= WEB_ROOT ?>/school-admin/attendance/overview" class="submenu-item">Overview</a>
                <a href="<?= WEB_ROOT ?>/school-admin/attendance/unified" class="submenu-item">Unified Attendance</a>
                <a href="<?= WEB_ROOT ?>/school-admin/attendance/live-monitoring" class="submenu-item">Live Monitoring</a>
                <a href="<?= WEB_ROOT ?>/school-admin/attendance/time-rules" class="submenu-item">Time Rules</a>
                <a href="<?= WEB_ROOT ?>/school-admin/attendance/rule-assignment" class="submenu-item">Rule Assignment</a>
                <a href="<?= WEB_ROOT ?>/school-admin/attendance/mark-students" class="submenu-item">Mark Students Attendance</a>
                <a href="<?= WEB_ROOT ?>/school-admin/attendance/employee-clocking" class="submenu-item">Employee Clocking</a>
                <a href="<?= WEB_ROOT ?>/school-admin/attendance/mark-employees" class="submenu-item">Mark Employees Attendance</a>
                <a href="<?= WEB_ROOT ?>/school-admin/attendance/pin" class="submenu-item">Attendance PIN</a>
                <a href="<?= WEB_ROOT ?>/school-admin/attendance/report-class" class="submenu-item">Class Wise Report</a>
                <a href="<?= WEB_ROOT ?>/school-admin/attendance/report-employee" class="submenu-item">Employees Attendance Report</a>
                <a href="<?= WEB_ROOT ?>/school-admin/attendance/report-student" class="submenu-item">Students Attendance Report</a>
            </div>
            <div class="nav-item tree-menu" onclick="toggleSubmenu('classes-menu')">
                <div class="nav-link" style="cursor:pointer;"><div class="nav-left"><i class="ph ph-chalkboard-teacher"></i> Classes</div></div>
                <i class="ph ph-caret-down nav-caret"></i>
            </div>
            <div class="submenu" id="classes-menu">
                <a href="<?= WEB_ROOT ?>/school-admin/classes/all" class="submenu-item">All Classes</a>
                <a href="<?= WEB_ROOT ?>/school-admin/classes/new" class="submenu-item">New Class</a>
            </div>

            <div class="nav-item tree-menu" onclick="toggleSubmenu('subjects-menu')">
                <div class="nav-link" style="cursor:pointer;"><div class="nav-left"><i class="ph ph-user"></i> Subjects</div></div>
                <i class="ph ph-caret-down nav-caret"></i>
            </div>
            <div class="submenu" id="subjects-menu">
                <a href="<?= WEB_ROOT ?>/school-admin/subjects/manage" class="submenu-item">Classes With Subjects</a>
                <a href="<?= WEB_ROOT ?>/school-admin/subjects/assign" class="submenu-item">Assign Subjects</a>
            </div>

            <div class="nav-item tree-menu" onclick="toggleSubmenu('lessons-menu')">
                <div class="nav-left"><i class="ph ph-chalkboard"></i> Lesson Bank</div>
                <i class="ph ph-caret-down nav-caret"></i>
            </div>
            <div class="submenu" id="lessons-menu">
                <a href="<?= WEB_ROOT ?>/school-admin/academic/lesson-notes" class="submenu-item">Review Submissions</a>
                <a href="<?= WEB_ROOT ?>/school-admin/academic/theory-generator" class="submenu-item">Theory Exam Generator</a>
            </div>

            <div class="nav-item tree-menu" onclick="toggleSubmenu('students-menu')">
                <div class="nav-link" style="cursor:pointer;"><div class="nav-left"><i class="ph ph-student"></i> Students</div></div>
                <i class="ph ph-caret-down nav-caret"></i>
            </div>
            <div class="submenu" id="students-menu">
                <a href="<?= WEB_ROOT ?>/school-admin/students/all" class="submenu-item">All Student</a>
                <a href="<?= WEB_ROOT ?>/school-admin/students/add" class="submenu-item">Add New</a>
                <a href="<?= WEB_ROOT ?>/school-admin/students/families" class="submenu-item">Manage Families</a>
                <a href="<?= WEB_ROOT ?>/school-admin/students/admission-letter" class="submenu-item">Admission Letter</a>
                <a href="<?= WEB_ROOT ?>/school-admin/students/id-cards" class="submenu-item">Student ID Cards</a>
                <a href="<?= WEB_ROOT ?>/school-admin/students/promote" class="submenu-item">Promote Students</a>
            </div>
            <div class="nav-item tree-menu" onclick="toggleSubmenu('parents-menu')">
                <div class="nav-link" style="cursor:pointer;"><div class="nav-left"><i class="ph ph-users-three"></i> Parents</div></div>
                <i class="ph ph-caret-down nav-caret"></i>
            </div>
            <div class="submenu" id="parents-menu">
                <a href="<?= WEB_ROOT ?>/school-admin/parents/all" class="submenu-item">All Parent</a>
                <a href="<?= WEB_ROOT ?>/school-admin/parents/add" class="submenu-item">Add New</a>
                <a href="<?= WEB_ROOT ?>/school-admin/parents/families" class="submenu-item">Manage Families</a>
                <a href="<?= WEB_ROOT ?>/school-admin/parents/visit-cards" class="submenu-item">Parent Visit Cards</a>
            </div>
            <div class="nav-item tree-menu" onclick="toggleSubmenu('employees-menu')">
                <div class="nav-link" style="cursor:pointer;"><div class="nav-left"><i class="ph ph-identification-badge"></i> Employees</div></div>
                <i class="ph ph-caret-down nav-caret"></i>
            </div>
            <div class="submenu" id="employees-menu">
                <a href="<?= WEB_ROOT ?>/school-admin/employees/all" class="submenu-item">All Employee</a>
                <a href="<?= WEB_ROOT ?>/school-admin/employees/add" class="submenu-item">Add New</a>
                <a href="<?= WEB_ROOT ?>/school-admin/employees/hr-settings" class="submenu-item" style="color:var(--primary); font-weight:700;">HR Settings</a>
                <a href="<?= WEB_ROOT ?>/school-admin/employees/id-cards" class="submenu-item">Staff ID Cards</a>
                <a href="<?= WEB_ROOT ?>/school-admin/employees/job-letter" class="submenu-item">Job Letter</a>
            </div>
            <!-- Bulk Upload — Quick Setup -->
            <a href="<?= WEB_ROOT ?>/school-admin/bulk-upload" class="nav-item <?= strpos($_SERVER['REQUEST_URI'] ?? '', 'bulk-upload') !== false ? 'active' : '' ?>" style="text-decoration:none;">
                <div class="nav-link">
                    <div class="nav-left">
                        <i class="ph ph-upload-simple"></i> Bulk Upload
                    </div>
                    <span style="background:#7c3aed;color:#fff;font-size:10px;padding:2px 7px;border-radius:20px;font-weight:700;">NEW</span>
                </div>
            </a>

            <div class="nav-item tree-menu" onclick="toggleSubmenu('accounts-menu')">
                <div class="nav-link" style="cursor:pointer;"><div class="nav-left"><i class="ph ph-coins"></i> Accounts</div></div>
                <i class="ph ph-caret-down nav-caret"></i>
            </div>
            <div class="submenu" id="accounts-menu">
                <a href="<?= WEB_ROOT ?>/school-admin/accounts/chart" class="submenu-item">Chart Of Account</a>
                <a href="<?= WEB_ROOT ?>/school-admin/accounts/income" class="submenu-item">Income Management</a>
                <a href="<?= WEB_ROOT ?>/school-admin/accounts/expense" class="submenu-item">Expense Management</a>
                <a href="<?= WEB_ROOT ?>/school-admin/accounts/fees" class="submenu-item">Student Fees</a>
                <a href="<?= WEB_ROOT ?>/school-admin/accounts/statement" class="submenu-item">Account Statement</a>
            </div>
            <div class="nav-item tree-menu" onclick="toggleSubmenu('assessments-menu')">
                <div class="nav-link" style="cursor:pointer;"><div class="nav-left"><i class="ph ph-clipboard-text"></i> Assessments</div></div>
                <i class="ph ph-caret-down nav-caret"></i>
            </div>
            <div class="submenu" id="assessments-menu">
                <a href="<?= WEB_ROOT ?>/school-admin/assessments/new" class="submenu-item">Create New Assessment</a>
                <a href="<?= WEB_ROOT ?>/school-admin/assessments/all" class="submenu-item">All Assessment</a>
                <a href="<?= WEB_ROOT ?>/school-admin/assessments/groups" class="submenu-item">Add/Update Assessment Group</a>
                <a href="<?= WEB_ROOT ?>/school-admin/assessments/assign" class="submenu-item">Assign Assessment To Group</a>
            </div>

            <div class="nav-item tree-menu" onclick="toggleSubmenu('exams-menu')">
                <div class="nav-link" style="cursor:pointer;"><div class="nav-left"><i class="ph ph-scroll"></i> Exams</div></div>
                <i class="ph ph-caret-down nav-caret"></i>
            </div>
            <div class="submenu" id="exams-menu">
                <a href="<?= WEB_ROOT ?>/school-admin/exams/new" class="submenu-item">Create New Exam</a>
                <a href="<?= WEB_ROOT ?>/school-admin/exams/all" class="submenu-item">All Exam</a>
                <a href="<?= WEB_ROOT ?>/school-admin/exams/groups" class="submenu-item">Add/Update Exam Group</a>
                <a href="<?= WEB_ROOT ?>/school-admin/exams/assign" class="submenu-item">Assign Exam To Group</a>
                <a href="<?= WEB_ROOT ?>/school-admin/exams/results" class="submenu-item">Result Card</a>
                <a href="<?= WEB_ROOT ?>/school-admin/exams/questions" class="submenu-item" style="color:var(--primary); font-weight:700;">Question Bank (CBT)</a>
                <a href="<?= WEB_ROOT ?>/school-admin/exams/cbt" class="submenu-item">Start Computer-Based Test</a>
            </div>

            <div class="nav-item tree-menu" onclick="toggleSubmenu('messaging-menu')">
                <div class="nav-link" style="cursor:pointer;"><div class="nav-left"><i class="ph ph-chat-centered-text"></i> Communication</div></div>
                <i class="ph ph-caret-down nav-caret"></i>
            </div>
            <div class="submenu" id="messaging-menu">
                <a href="<?= WEB_ROOT ?>/school-admin/messaging/chat" class="submenu-item">Institute Chat Room</a>
                <a href="<?= WEB_ROOT ?>/school-admin/messaging/direct" class="submenu-item">Direct Messaging</a>
                <a href="<?= WEB_ROOT ?>/school-admin/messaging/announcements" class="submenu-item">Announcements</a>
                <a href="<?= WEB_ROOT ?>/school-admin/messaging/notifications" class="submenu-item">SMS & Email</a>
                <a href="<?= WEB_ROOT ?>/school-admin/messaging/templates" class="submenu-item">Notification Templates</a>
                <a href="<?= WEB_ROOT ?>/school-admin/messaging/events" class="submenu-item">Events Calendar</a>
            </div>

            <div class="nav-item tree-menu" onclick="toggleSubmenu('liveclass-menu')">
                <div class="nav-link" style="cursor:pointer;"><div class="nav-left"><i class="ph ph-video-camera"></i> Live Class</div></div>
                <i class="ph ph-caret-down nav-caret"></i>
            </div>
            <div class="submenu" id="liveclass-menu">
                <a href="<?= WEB_ROOT ?>/school-admin/live-class" class="submenu-item">Institute Live Class</a>
            </div>

            <div class="nav-item tree-menu" onclick="toggleSubmenu('timetable-menu')">
                <div class="nav-link" style="cursor:pointer;"><div class="nav-left"><i class="ph ph-calendar"></i> Timetable</div></div>
                <i class="ph ph-caret-down nav-caret"></i>
            </div>
            <div class="submenu" id="timetable-menu">
                <a href="<?= WEB_ROOT ?>/school-admin/timetable/weekdays" class="submenu-item">Weekdays</a>
                <a href="<?= WEB_ROOT ?>/school-admin/timetable/periods" class="submenu-item">Time Period</a>
                <a href="<?= WEB_ROOT ?>/school-admin/timetable/rooms" class="submenu-item">Class Rooms</a>
                <a href="<?= WEB_ROOT ?>/school-admin/timetable/create" class="submenu-item">Create Timetable</a>
                <a href="<?= WEB_ROOT ?>/school-admin/timetable/generate-class" class="submenu-item">Generate For Class</a>
                <a href="<?= WEB_ROOT ?>/school-admin/timetable/generate-teacher" class="submenu-item">Generate For Teacher</a>
            </div>

            <div class="nav-item tree-menu" onclick="toggleSubmenu('reports-menu')">
                <div class="nav-link" style="cursor:pointer;"><div class="nav-left"><i class="ph ph-chart-bar"></i> Reports</div></div>
                <i class="ph ph-caret-down nav-caret"></i>
            </div>
            <div class="submenu" id="reports-menu">
                <a href="<?= WEB_ROOT ?>/school-admin/reports/student-card" class="submenu-item">Students Report Card</a>
                <a href="<?= WEB_ROOT ?>/school-admin/reports/progression" class="submenu-item">Student Progression Report (Terms 1-3)</a>
                <a href="<?= WEB_ROOT ?>/school-admin/reports/broadsheet" class="submenu-item">Broadsheet Report</a>
                
                <a href="#" class="submenu-item" style="margin-top:12px; font-weight:700; color:var(--text-dark); border-top:1px dashed #e5e7eb; padding-top:16px;">Financial Reports</a>
                <a href="<?= WEB_ROOT ?>/school-admin/reports/pnl" class="submenu-item">Profit and Loss Summary</a>
                <a href="<?= WEB_ROOT ?>/school-admin/reports/balance-sheet" class="submenu-item">Full Balance Sheet</a>
                <a href="<?= WEB_ROOT ?>/school-admin/reports/transactions" class="submenu-item">Global Transaction Reports</a>
                <a href="<?= WEB_ROOT ?>/school-admin/reports/income-expense" class="submenu-item">Income vs Expense Report</a>
                <a href="<?= WEB_ROOT ?>/school-admin/reports/debtors" class="submenu-item">Outstanding Debtors Report</a>
            </div>
        </div>
    </div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const currentUrl = window.location.href;
    const submenuItems = document.querySelectorAll('.submenu-item');
    
    submenuItems.forEach(item => {
        if (currentUrl.includes(item.getAttribute('href'))) {
            item.style.color = 'var(--primary)';
            item.style.fontWeight = '700';
            
            let parentSubmenu = item.closest('.submenu');
            if (parentSubmenu) {
                parentSubmenu.classList.add('open');
                const caret = parentSubmenu.previousElementSibling.querySelector('.nav-caret');
                if (caret) {
                    caret.classList.remove('ph-caret-down');
                    caret.classList.add('ph-caret-up');
                }
            }
        }
    });
});
</script>
