# RosmonSMS — Multi-Tenant School Management System

A production-ready SaaS platform for managing multiple schools from a single installation. Each school gets its own isolated database, provisioned automatically when approved by the Super Admin.

---

## Quick Start (Production)

```
1. Upload files to your server
2. Create supervisor DB + 50 tenant DBs in cPanel
3. Edit config/env.php with your credentials
4. Visit yourdomain.com/install.php
5. Delete install.php after setup
6. Login at yourdomain.com/
```

See **deployment_guide.md** for full step-by-step instructions.

---

## Architecture

### Database Pool Model

```
Supervisor DB: middlehi_IDAHOSA          ← Central hub
Tenant DB  1:  middlehi_IDAHOSA_1       ← School 1 (private)
Tenant DB  2:  middlehi_IDAHOSA_2       ← School 2 (private)
...
Tenant DB 50:  middlehi_IDAHOSA_50      ← School 50 (private)
```

- Each approved school gets its own **private database** with 85+ tables
- All school data is perfectly isolated — no cross-tenant leakage
- Credentials are **auto-derived** from the supervisor DB name (no manual config needed)
- The pool supports **50 schools** out of the box (expandable in `env.php`)

---

## Roles & Portals

| Role | Portal URL | Description |
|------|-----------|-------------|
| `super_admin` | `/super-admin/dashboard` | Platform owner — approves schools, manages pool |
| `school_admin` | `/school-admin/dashboard` | School owner — manages all school data |
| `principal` | `/principal/dashboard` | Academic oversight |
| `vice_principal` | `/vice-principal/dashboard` | Academic support |
| `teacher` | `/employee/dashboard` | Class teaching, marks, attendance |
| `student` | `/student/dashboard` | Results, CBT, timetable |
| `parent` | `/parent/dashboard` | Child progress, fees |
| `finance` / `accountant` | `/finance/dashboard` | Fees, income, expenses |
| `audit` | `/audit/dashboard` | Audit logs |
| `pta_chairman` | `/pta-chairman/dashboard` | PTA access |

---

## Bulk Upload (School Onboarding)

School admins can import all data at once via CSV:

```
Dashboard → Bulk Upload → Download Templates → Fill → Upload
```

**Upload Order:**
1. Classes (required first)
2. Subjects
3. Employees / Staff (with roles)
4. Students (references classes)
5. Parents (references students by admission no.)
6. Subject Assignments (links teachers to subjects)

---

## Key Files

| File | Purpose |
|------|---------|
| `config/env.php` | Your credentials — **keep secure!** |
| `config/tenant_manager.php` | Multi-tenancy engine — pool + provisioning |
| `config/database.php` | DB bootstrap (`$pdo`, `$supervisorPdo`) |
| `database/tenant_schema.sql` | School DB schema (85+ tables) |
| `database/supervisor_schema_utf8.sql` | Supervisor DB schema |
| `install.php` | One-time installer — **delete after use** |
| `setup_pool.php` | Pool registration tool — **delete after use** |

---

## Environment Configuration

Edit `config/env.php`:

```php
return [
    'DB_HOST'          => 'localhost',
    'DB_USER'          => 'middlehi_IDAHOSA',        // Supervisor DB user
    'DB_PASS'          => 'your_secure_password',    // Supervisor DB password
    'DB_NAME'          => 'middlehi_IDAHOSA',        // Supervisor DB name
    'DB_PREFIX'        => '',
    'TENANT_DB_HOST'   => 'localhost',
    'TENANT_POOL_SIZE' => 50,                        // Number of tenant slots
];
```

Tenant credentials are auto-derived:
- DB name: `{DB_NAME}_{index}` (e.g., `middlehi_IDAHOSA_3`)
- Username: same as DB name
- Password: same as DB name

---

## Security

- All config files blocked from web access via `.htaccess`
- Sessions managed server-side with role enforcement
- PDO with prepared statements throughout — SQL injection safe
- License validation on every non-super-admin login
- Delete `install.php` and `setup_pool.php` after use

---

## Requirements

- PHP 7.4+ (8.x recommended)
- MySQL 5.7+ / MariaDB 10.3+
- Extensions: `pdo`, `pdo_mysql`, `mbstring`, `json`, `openssl`
- Apache with `mod_rewrite` enabled (or Nginx equivalent)
