# RosmonSMS — Complete Production Deployment Guide

## Architecture Overview

```
Live Server (cPanel / writecode.com.ng)
│
├── Supervisor Database: middlehi_IDAHOSA
│   ├── users           (all logins — super admin, school admins)
│   ├── institution_profile  (registered schools + status)
│   ├── licenses        (active license keys)
│   └── db_pool         (50 tenant DB slots registry)
│
└── Tenant Databases: middlehi_IDAHOSA_1 → middlehi_IDAHOSA_50
    ├── Each school gets their own private DB
    ├── Auto-provisioned when Super Admin approves a school
    └── All school data isolated: students, staff, classes, marks, etc.
```

---

## Step 1 — Create Databases in cPanel

### 1a. Create the Supervisor Database
In cPanel → MySQL Databases:
- Database name: `middlehi_IDAHOSA`
- Create user: `middlehi_IDAHOSA` with a **strong password**
- Grant ALL PRIVILEGES to this user on this database

### 1b. Create All 50 Tenant Databases
Create each of the following in cPanel MySQL Databases:

| # | Database Name | Username | Password |
|---|--------------|----------|----------|
| 1 | middlehi_IDAHOSA_1 | middlehi_IDAHOSA_1 | middlehi_IDAHOSA_1 |
| 2 | middlehi_IDAHOSA_2 | middlehi_IDAHOSA_2 | middlehi_IDAHOSA_2 |
| 3 | middlehi_IDAHOSA_3 | middlehi_IDAHOSA_3 | middlehi_IDAHOSA_3 |
| ... | ... | ... | ... |
| 50 | middlehi_IDAHOSA_50 | middlehi_IDAHOSA_50 | middlehi_IDAHOSA_50 |

> **Tip:** Use cPanel's "MySQL Databases Wizard" or phpMyAdmin's batch tool.
> Each tenant DB user's password = the database name itself.

---

## Step 2 — Upload Files

1. Upload all project files to your server root:
   - Via FTP/SFTP: `public_html/` or your chosen subdirectory
   - Via cPanel File Manager

2. Set permissions:
   ```bash
   chmod 755 config/
   chmod 755 storage/
   chmod 755 storage/sessions/
   chmod 755 storage/logs/
   chmod 755 storage/cache/
   chmod 755 public/uploads/
   ```

---

## Step 3 — Configure env.php

1. Rename `config/env.production.php` to `config/env.php`
2. Edit with your actual credentials:

```php
return [
    'DB_HOST'          => 'localhost',
    'DB_USER'          => 'middlehi_IDAHOSA',
    'DB_PASS'          => 'YOUR_SECURE_PASSWORD',   // the supervisor DB password
    'DB_NAME'          => 'middlehi_IDAHOSA',
    'DB_PREFIX'        => '',
    'TENANT_DB_HOST'   => 'localhost',
    'TENANT_POOL_SIZE' => 50,
];
```

---

## Step 4 — Run the Installer

Visit: `https://yourdomain.com/install.php`

1. **Step 1** — Requirements check (all should be green)
2. **Step 2** — Verify DB credentials (auto-filled from env.php)
3. **Step 3** — Import schema + create Super Admin:
   - Set your super admin email and password
   - System will import supervisor schema and register all 50 pool slots
4. **Complete** — Delete `install.php` from server

---

## Step 5 — Verify Pool Registration

Visit: `https://yourdomain.com/setup_pool.php?key=ROSMON_POOL_SETUP_2026`

- You should see all 50 slots listed
- Click "Register All 50 Slots" if not already done
- **Delete setup_pool.php after completion!**

---

## Step 6 — Login as Super Admin

- URL: `https://yourdomain.com/`
- Email: (what you set in installer)
- Role: Super Admin

---

## School Approval Flow

When a school submits the Get Started form:

1. Super Admin sees "Pending Approval" in dashboard
2. Super Admin clicks **Approve**
3. System automatically:
   - Picks the next free DB pool slot (e.g., `middlehi_IDAHOSA_3`)
   - Imports the complete tenant schema (85+ tables)
   - Creates a school_admin user in the supervisor DB
   - Generates a license key with expiry date
   - Emails login credentials to the school admin
4. School admin logs in and sees the **Bulk Upload onboarding banner**

---

## School Admin — Bulk Data Upload

School admins can onboard all data in one go:

### Download Templates
`Dashboard → Bulk Upload → Download CSV Templates`

Available templates:
1. **Classes** — class_name, section, capacity
2. **Subjects** — subject_name, subject_code
3. **Employees** — full_name, email, phone, role, department, salary...
4. **Students** — full_name, admission_no, class_name, gender, dob...
5. **Parents** — guardian_name, email, phone, student_admission_no...
6. **Subject Assignments** — class_name, subject_name, teacher_email

### Upload Order (Important!)
```
Classes → Subjects → Employees → Students → Parents → Subject Assignments
```

---

## File Structure

```
/
├── config/
│   ├── env.php              ← Your credentials (keep secure!)
│   ├── tenant_manager.php   ← Core multi-tenancy engine
│   ├── database.php         ← DB bootstrap (sets $pdo, $supervisorPdo)
│   ├── routes.php           ← URL routing
│   └── auth_guard.php       ← Session/auth middleware
├── database/
│   ├── supervisor_schema_utf8.sql  ← Supervisor DB tables
│   └── tenant_schema.sql          ← School DB tables (85+ tables)
├── app/
│   └── Views/
│       ├── super_admin/dashboard.php
│       ├── school_admin/
│       │   ├── bulk_upload/
│       │   │   ├── index.php      ← Upload form
│       │   │   ├── templates.php  ← CSV template downloads
│       │   │   └── processor.php  ← Import logic
│       │   └── dashboard.php
│       └── ...
├── public/
│   └── index.php    ← Front controller
├── install.php      ← Installer (DELETE after use!)
└── setup_pool.php   ← Pool manager (DELETE after use!)
```

---

## Security Checklist

- [ ] Delete `install.php` after installation
- [ ] Delete `setup_pool.php` after pool setup
- [ ] Keep `config/env.php` out of public access (`.htaccess` already blocks it)
- [ ] Use HTTPS (SSL certificate)
- [ ] Set strong supervisor DB password
- [ ] Change the secret key in `setup_pool.php` before deploying
- [ ] Enable PHP error logging (not display) in production

---

## Troubleshooting

**"No free database slots available"**
→ Run `setup_pool.php` to register all 50 slots in the pool table.

**"Cannot connect to tenant DB"**
→ Ensure the tenant DB exists in cPanel with matching user + password = db_name.

**"Schema import failed"**
→ Check that the tenant DB user has ALL PRIVILEGES (not just SELECT).

**Blank page / 500 error**
→ Check `storage/logs/` for PHP errors. Ensure PHP ≥ 7.4 with PDO and pdo_mysql.
