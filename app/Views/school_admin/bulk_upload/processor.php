<?php
/**
 * BulkUploadProcessor — handles all CSV imports for school setup
 */

class BulkUploadProcessor {
    private PDO $pdo;
    private int $instituteId;
    private array $errors = [];
    private array $stats  = [];

    public function __construct(PDO $pdo, int $instituteId) {
        $this->pdo         = $pdo;
        $this->instituteId = $instituteId;
    }

    public function process(string $type, string $tmpFile): array {
        $this->errors = [];
        $this->stats  = [];

        $rows = $this->parseCsv($tmpFile);
        if (empty($rows)) {
            return ['success'=>false,'errors'=>['CSV file is empty or invalid.']];
        }

        switch ($type) {
            case 'classes':             return $this->importClasses($rows);
            case 'subjects':            return $this->importSubjects($rows);
            case 'employees':           return $this->importEmployees($rows);
            case 'students':            return $this->importStudents($rows);
            case 'parents':             return $this->importParents($rows);
            case 'subject_assignments': return $this->importSubjectAssignments($rows);
            default:
                return ['success'=>false,'errors'=>["Unknown upload type: {$type}"]];
        }
    }

    // ── CSV PARSER ────────────────────────────────────────────────────
    private function parseCsv(string $file): array {
        $rows    = [];
        $handle  = fopen($file, 'r');
        if (!$handle) return [];

        $headers = null;
        $line    = 0;
        while (($row = fgetcsv($handle, 0, ',')) !== false) {
            $line++;
            if ($line === 1) {
                // Normalize headers: lowercase, trim, replace spaces with underscore
                $headers = array_map(fn($h) => strtolower(trim(preg_replace('/\s+/', '_', $h))), $row);
                continue;
            }
            if (array_filter($row, fn($v) => trim($v) !== '') === []) continue; // skip empty rows
            $combined = [];
            foreach ($headers as $i => $h) {
                $combined[$h] = trim($row[$i] ?? '');
            }
            $rows[] = ['_line' => $line, ...$combined];
        }
        fclose($handle);
        return $rows;
    }

    // ── CLASSES ───────────────────────────────────────────────────────
    private function importClasses(array $rows): array {
        $inserted = 0; $skipped = 0;
        $stmt = $this->pdo->prepare(
            "INSERT INTO classes (class_name, section, capacity, institute_id, is_deleted, created_at)
             VALUES (?, ?, ?, ?, 0, NOW())
             ON DUPLICATE KEY UPDATE section=VALUES(section), capacity=VALUES(capacity)"
        );

        foreach ($rows as $row) {
            $name = $row['class_name'] ?? $row['name'] ?? '';
            if (!$name) { $this->errors[] = "Line {$row['_line']}: class_name is required."; $skipped++; continue; }
            try {
                $stmt->execute([
                    $name,
                    $row['section'] ?? '',
                    (int)($row['capacity'] ?? 40),
                    $this->instituteId,
                ]);
                $inserted++;
            } catch (PDOException $e) {
                $this->errors[] = "Line {$row['_line']}: " . $e->getMessage();
                $skipped++;
            }
        }
        return $this->result("Classes imported: {$inserted} added, {$skipped} skipped.",
                             ['inserted'=>$inserted,'skipped'=>$skipped]);
    }

    // ── SUBJECTS ──────────────────────────────────────────────────────
    private function importSubjects(array $rows): array {
        $inserted = 0; $skipped = 0;
        $stmt = $this->pdo->prepare(
            "INSERT INTO subjects (subject_name, subject_code, institute_id, is_deleted, created_at)
             VALUES (?, ?, ?, 0, NOW())
             ON DUPLICATE KEY UPDATE subject_code=VALUES(subject_code)"
        );

        foreach ($rows as $row) {
            $name = $row['subject_name'] ?? $row['name'] ?? '';
            if (!$name) { $this->errors[] = "Line {$row['_line']}: subject_name required."; $skipped++; continue; }
            try {
                $stmt->execute([
                    $name,
                    strtoupper($row['subject_code'] ?? substr($name, 0, 6)),
                    $this->instituteId,
                ]);
                $inserted++;
            } catch (PDOException $e) {
                $this->errors[] = "Line {$row['_line']}: " . $e->getMessage();
                $skipped++;
            }
        }
        return $this->result("Subjects imported: {$inserted} added, {$skipped} skipped.",
                             ['inserted'=>$inserted,'skipped'=>$skipped]);
    }

    // ── EMPLOYEES ─────────────────────────────────────────────────────
    private function importEmployees(array $rows): array {
        $inserted = 0; $skipped = 0;
        $this->ensureHrTables();
        $this->ensureUsersTable();

        foreach ($rows as $row) {
            $fullName = $row['full_name'] ?? $row['name'] ?? '';
            $email    = strtolower($row['email'] ?? '');
            if (!$fullName || !$email) {
                $this->errors[] = "Line {$row['_line']}: full_name and email required.";
                $skipped++; continue;
            }

            try {
                $this->pdo->beginTransaction();

                // Get or create department
                $deptId = null;
                $deptName = $row['department'] ?? '';
                if ($deptName) {
                    $ds = $this->pdo->prepare("SELECT id FROM departments WHERE dept_name=? LIMIT 1");
                    $ds->execute([$deptName]);
                    $dept = $ds->fetchColumn();
                    if (!$dept) {
                        $this->pdo->prepare("INSERT INTO departments (dept_name) VALUES (?)")->execute([$deptName]);
                        $deptId = $this->pdo->lastInsertId();
                    } else {
                        $deptId = $dept;
                    }
                }

                // Get or create designation
                $desigId   = null;
                $desigName = $row['designation'] ?? $row['role'] ?? 'Teacher';
                if ($desigName) {
                    $dss = $this->pdo->prepare("SELECT id FROM designations WHERE designation_name=? LIMIT 1");
                    $dss->execute([$desigName]);
                    $desig = $dss->fetchColumn();
                    if (!$desig) {
                        $this->pdo->prepare("INSERT INTO designations (designation_name) VALUES (?)")->execute([$desigName]);
                        $desigId = $this->pdo->lastInsertId();
                    } else {
                        $desigId = $desig;
                    }
                }

                // Determine system role
                $csvRole  = strtolower($row['role'] ?? 'teacher');
                $sysRole  = $this->mapEmployeeRole($csvRole);

                // Upsert user
                $raw = $row['password'] ?? bin2hex(random_bytes(4));
                $hashed = password_hash($raw, PASSWORD_DEFAULT);
                $us = $this->pdo->prepare("SELECT id FROM users WHERE email=? LIMIT 1");
                $us->execute([$email]);
                $userId = $us->fetchColumn();
                if (!$userId) {
                    $this->pdo->prepare(
                        "INSERT INTO users (full_name, email, username, password, role, tenant_id, phone, created_at)
                         VALUES (?,?,?,?,?,?,?,NOW())"
                    )->execute([$fullName, $email, $email, $hashed, $sysRole, $this->instituteId,
                                $row['phone'] ?? '']);
                    $userId = $this->pdo->lastInsertId();
                } else {
                    $this->pdo->prepare("UPDATE users SET full_name=?,role=?,phone=?,tenant_id=? WHERE id=?")
                              ->execute([$fullName, $sysRole, $row['phone']??'', $this->instituteId, $userId]);
                }

                // Upsert institute_employees
                $empNo = $row['employee_no'] ?? $row['employee_number'] ?? ('EMP' . str_pad($userId, 4, '0', STR_PAD_LEFT));
                $es = $this->pdo->prepare("SELECT id FROM institute_employees WHERE user_id=? AND institute_id=? LIMIT 1");
                $es->execute([$userId, $this->instituteId]);
                if (!$es->fetchColumn()) {
                    $this->pdo->prepare(
                        "INSERT INTO institute_employees
                         (employee_id, user_id, institute_id, employee_no, role, gender, dob, religion, blood_group,
                          address, phone, department, designation, salary, hire_date, is_deleted, created_at)
                         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,0,NOW())"
                    )->execute([
                        $userId, $userId, $this->instituteId, $empNo, $sysRole,
                        $row['gender'] ?? 'Male',
                        $row['dob'] ?? $row['date_of_birth'] ?? null,
                        $row['religion'] ?? '',
                        $row['blood_group'] ?? '',
                        $row['address'] ?? '',
                        $row['phone'] ?? '',
                        $deptName,
                        $desigName,
                        (float)($row['salary'] ?? 0),
                        $row['hire_date'] ?? date('Y-m-d'),
                    ]);
                }

                // HR employees table
                $hr = $this->pdo->prepare("SELECT id FROM employees WHERE user_id=? LIMIT 1");
                $hr->execute([$userId]);
                if (!$hr->fetchColumn()) {
                    $this->pdo->prepare(
                        "INSERT INTO employees (user_id, employee_number, department_id, designation_id, salary, hire_date, status)
                         VALUES (?,?,?,?,?,?,?)"
                    )->execute([$userId, $empNo, $deptId, $desigId,
                                (float)($row['salary']??0),
                                $row['hire_date']??date('Y-m-d'), 'Active']);
                }

                $this->pdo->commit();
                $inserted++;
            } catch (PDOException $e) {
                if ($this->pdo->inTransaction()) $this->pdo->rollBack();
                $this->errors[] = "Line {$row['_line']} ({$email}): " . $e->getMessage();
                $skipped++;
            }
        }

        return $this->result("Employees imported: {$inserted} added, {$skipped} skipped.",
                             ['inserted'=>$inserted,'skipped'=>$skipped]);
    }

    // ── STUDENTS ──────────────────────────────────────────────────────
    private function importStudents(array $rows): array {
        $inserted = 0; $skipped = 0;

        foreach ($rows as $row) {
            $fullName = $row['full_name'] ?? $row['name'] ?? '';
            $admNo    = $row['admission_no'] ?? $row['adm_no'] ?? ('ADM' . rand(10000,99999));
            $className = $row['class_name'] ?? $row['class'] ?? '';
            if (!$fullName) {
                $this->errors[] = "Line {$row['_line']}: full_name required.";
                $skipped++; continue;
            }

            try {
                $this->pdo->beginTransaction();

                // Resolve class_id
                $classId = null;
                if ($className) {
                    $cs = $this->pdo->prepare(
                        "SELECT id FROM classes WHERE class_name=? AND institute_id=? AND is_deleted=0 LIMIT 1"
                    );
                    $cs->execute([$className, $this->instituteId]);
                    $classId = $cs->fetchColumn() ?: null;
                }

                // Upsert student
                $existing = $this->pdo->prepare(
                    "SELECT student_id FROM institute_students WHERE admission_no=? AND institute_id=? LIMIT 1"
                );
                $existing->execute([$admNo, $this->instituteId]);
                $studentId = $existing->fetchColumn();

                if (!$studentId) {
                    $this->pdo->prepare(
                        "INSERT INTO institute_students
                         (institute_id, class_id, student_no, admission_no, full_name, gender, dob,
                          religion, blood_group, address, phone, email, admission_date, is_deleted, created_at)
                         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,0,NOW())"
                    )->execute([
                        $this->instituteId, $classId, $admNo, $admNo, $fullName,
                        $row['gender'] ?? 'Male',
                        $row['dob'] ?? $row['date_of_birth'] ?? null,
                        $row['religion'] ?? '',
                        $row['blood_group'] ?? '',
                        $row['address'] ?? '',
                        $row['phone'] ?? '',
                        $row['email'] ?? '',
                        $row['admission_date'] ?? date('Y-m-d'),
                    ]);
                } else {
                    // Update existing
                    $this->pdo->prepare(
                        "UPDATE institute_students SET class_id=?, full_name=?, gender=?, dob=?,
                         address=?, phone=? WHERE student_id=?"
                    )->execute([$classId, $fullName, $row['gender']??'Male',
                                $row['dob']??null, $row['address']??'', $row['phone']??'', $studentId]);
                }

                $this->pdo->commit();
                $inserted++;
            } catch (PDOException $e) {
                if ($this->pdo->inTransaction()) $this->pdo->rollBack();
                $this->errors[] = "Line {$row['_line']} ({$admNo}): " . $e->getMessage();
                $skipped++;
            }
        }
        return $this->result("Students imported: {$inserted} added, {$skipped} skipped.",
                             ['inserted'=>$inserted,'skipped'=>$skipped]);
    }

    // ── PARENTS ───────────────────────────────────────────────────────
    private function importParents(array $rows): array {
        $inserted = 0; $skipped = 0;

        foreach ($rows as $row) {
            $guardianName = $row['guardian_name'] ?? $row['father_name'] ?? $row['name'] ?? '';
            $email        = strtolower($row['email'] ?? '');
            $phone        = $row['phone'] ?? '';
            $studentAdmNo = $row['student_admission_no'] ?? $row['adm_no'] ?? '';

            if (!$guardianName && !$email) {
                $this->errors[] = "Line {$row['_line']}: guardian_name or email required.";
                $skipped++; continue;
            }

            try {
                $this->pdo->beginTransaction();

                // Find linked student
                $studentId = null;
                if ($studentAdmNo) {
                    $ss = $this->pdo->prepare(
                        "SELECT student_id FROM institute_students WHERE admission_no=? AND institute_id=? LIMIT 1"
                    );
                    $ss->execute([$studentAdmNo, $this->instituteId]);
                    $studentId = $ss->fetchColumn() ?: null;
                }

                // Check existing parent
                $epq = $email
                    ? "SELECT id FROM institute_parents WHERE email=? AND institute_id=? LIMIT 1"
                    : "SELECT id FROM institute_parents WHERE guardian_name=? AND institute_id=? LIMIT 1";
                $ep = $this->pdo->prepare($epq);
                $ep->execute([$email ?: $guardianName, $this->instituteId]);
                $parentId = $ep->fetchColumn();

                if (!$parentId) {
                    $this->pdo->prepare(
                        "INSERT INTO institute_parents
                         (institute_id, father_name, mother_name, guardian_name, phone, email, address,
                          occupation, is_deleted, created_at)
                         VALUES (?,?,?,?,?,?,?,?,0,NOW())"
                    )->execute([
                        $this->instituteId,
                        $row['father_name'] ?? $guardianName,
                        $row['mother_name'] ?? '',
                        $guardianName,
                        $phone,
                        $email,
                        $row['address'] ?? '',
                        $row['occupation'] ?? '',
                    ]);
                    $parentId = $this->pdo->lastInsertId();
                }

                // Link parent to student via institute_families
                if ($studentId && $parentId) {
                    try {
                        $this->pdo->prepare(
                            "INSERT IGNORE INTO institute_families (student_id, parent_id, relation, created_at)
                             VALUES (?,?,'Parent',NOW())"
                        )->execute([$studentId, $parentId]);
                    } catch (PDOException $ignored) {}
                }

                $this->pdo->commit();
                $inserted++;
            } catch (PDOException $e) {
                if ($this->pdo->inTransaction()) $this->pdo->rollBack();
                $this->errors[] = "Line {$row['_line']}: " . $e->getMessage();
                $skipped++;
            }
        }
        return $this->result("Parents imported: {$inserted} processed, {$skipped} skipped.",
                             ['inserted'=>$inserted,'skipped'=>$skipped]);
    }

    // ── SUBJECT ASSIGNMENTS ───────────────────────────────────────────
    private function importSubjectAssignments(array $rows): array {
        $inserted = 0; $skipped = 0;

        foreach ($rows as $row) {
            $className   = $row['class_name'] ?? $row['class'] ?? '';
            $subjectName = $row['subject_name'] ?? $row['subject'] ?? '';
            $teacherEmail = strtolower($row['teacher_email'] ?? '');
            if (!$className || !$subjectName) {
                $this->errors[] = "Line {$row['_line']}: class_name and subject_name required.";
                $skipped++; continue;
            }

            try {
                // Resolve IDs
                $cs = $this->pdo->prepare(
                    "SELECT id FROM classes WHERE class_name=? AND institute_id=? AND is_deleted=0 LIMIT 1"
                );
                $cs->execute([$className, $this->instituteId]);
                $classId = $cs->fetchColumn();
                if (!$classId) { $this->errors[] = "Line {$row['_line']}: Class '{$className}' not found."; $skipped++; continue; }

                $ss = $this->pdo->prepare(
                    "SELECT id FROM subjects WHERE subject_name=? AND institute_id=? AND is_deleted=0 LIMIT 1"
                );
                $ss->execute([$subjectName, $this->instituteId]);
                $subjectId = $ss->fetchColumn();
                if (!$subjectId) { $this->errors[] = "Line {$row['_line']}: Subject '{$subjectName}' not found."; $skipped++; continue; }

                $teacherId = null;
                if ($teacherEmail) {
                    $ts = $this->pdo->prepare("SELECT id FROM users WHERE email=? LIMIT 1");
                    $ts->execute([$teacherEmail]);
                    $teacherId = $ts->fetchColumn() ?: null;
                }

                $this->pdo->prepare(
                    "INSERT INTO class_subjects (class_id, subject_id, teacher_id, institute_id, created_at)
                     VALUES (?,?,?,?,NOW())
                     ON DUPLICATE KEY UPDATE teacher_id=VALUES(teacher_id)"
                )->execute([$classId, $subjectId, $teacherId, $this->instituteId]);
                $inserted++;
            } catch (PDOException $e) {
                $this->errors[] = "Line {$row['_line']}: " . $e->getMessage();
                $skipped++;
            }
        }
        return $this->result("Subject assignments: {$inserted} linked, {$skipped} skipped.",
                             ['inserted'=>$inserted,'skipped'=>$skipped]);
    }

    // ── HELPERS ───────────────────────────────────────────────────────
    private function mapEmployeeRole(string $csvRole): string {
        $map = [
            'teacher'          => 'teacher',
            'class teacher'    => 'teacher',
            'subject teacher'  => 'teacher',
            'principal'        => 'principal',
            'head teacher'     => 'principal',
            'vice principal'   => 'vice_principal',
            'vp'               => 'vice_principal',
            'admin'            => 'school_admin',
            'administrator'    => 'school_admin',
            'accountant'       => 'accountant',
            'bursar'           => 'accountant',
            'librarian'        => 'librarian',
            'counselor'        => 'counselor',
            'support'          => 'support_staff',
            'staff'            => 'support_staff',
            'security'         => 'support_staff',
            'janitor'          => 'support_staff',
            'nurse'            => 'nurse',
            'pta'              => 'pta_chairman',
        ];
        $key = strtolower(trim($csvRole));
        return $map[$key] ?? 'teacher';
    }

    private function ensureHrTables(): void {
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS departments (
          id int NOT NULL AUTO_INCREMENT, dept_name varchar(100) NOT NULL,
          dept_code varchar(50) DEFAULT NULL, created_at timestamp DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS designations (
          id int NOT NULL AUTO_INCREMENT, designation_name varchar(100) NOT NULL,
          designation_code varchar(50) DEFAULT NULL, created_at timestamp DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS employees (
          id int NOT NULL AUTO_INCREMENT, user_id int NOT NULL,
          employee_number varchar(50) NOT NULL, department_id int DEFAULT NULL,
          designation_id int DEFAULT NULL, salary decimal(15,2) DEFAULT 0.00,
          hire_date date DEFAULT NULL, status enum('Active','Resigned','Terminated') DEFAULT 'Active',
          created_at timestamp DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    private function ensureUsersTable(): void {
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS users (
          id int NOT NULL AUTO_INCREMENT, full_name varchar(200) NOT NULL,
          email varchar(200) NOT NULL, username varchar(200) NOT NULL,
          password varchar(255) NOT NULL, role varchar(50) DEFAULT 'teacher',
          tenant_id int DEFAULT NULL, phone varchar(50) DEFAULT NULL,
          is_active tinyint(1) DEFAULT 1, created_at datetime DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (id), UNIQUE KEY uq_email (email)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    private function result(string $message, array $stats): array {
        return [
            'success' => empty($this->errors) || ($stats['inserted'] ?? 0) > 0,
            'message' => $message . (empty($this->errors) ? '' : ' ' . count($this->errors) . ' error(s) — see below.'),
            'stats'   => $stats,
            'errors'  => $this->errors,
        ];
    }
}
