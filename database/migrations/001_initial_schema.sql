CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(255) NULL,
    `email` VARCHAR(255) NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `provider` VARCHAR(255) DEFAULT 'local',
    `confirmed` TINYINT(1) DEFAULT 0,
    `blocked` TINYINT(1) DEFAULT 0,
    `role_id` INT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS `academic_holidaies` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(255) NOT NULL,
    `description` VARCHAR(255) NULL,
    `date` DATE NOT NULL,
    `institute_id` INT NOT NULL,
    `academic_session_id` INT NULL,
    `academic_semester_id` INT NULL,
    `isDeleted` TINYINT(1) DEFAULT 0,
    `is_deleted` TINYINT(1) DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS `academic_semesters` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `start` VARCHAR(255) NOT NULL,
    `start_day` VARCHAR(255) NOT NULL,
    `end` VARCHAR(255) NOT NULL,
    `end_day` VARCHAR(255) NOT NULL,
    `institute_id` INT NOT NULL,
    `isActive` TINYINT(1) DEFAULT 0,
    `isDeleted` TINYINT(1) DEFAULT 0,
    `is_deleted` TINYINT(1) DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS `academic_sessions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `description` TEXT NULL,
    `institute_id` INT NOT NULL,
    `isActive` TINYINT(1) DEFAULT 0,
    `isDeleted` TINYINT(1) DEFAULT 0,
    `is_deleted` TINYINT(1) DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS `account_transactions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `description` VARCHAR(255) NOT NULL,
    `amount` DECIMAL(10,2) NOT NULL,
    `type` VARCHAR(255) NOT NULL,
    `chart_id` INT NULL,
    `rollover` DECIMAL(10,2) NULL,
    `balance` DECIMAL(10,2) NULL,
    `transaction_date` DATE NOT NULL,
    `previous_transaction_id` INT NULL,
    `institute_id` INT NULL,
    `academic_session_id` INT NULL,
    `academic_semester_id` INT NULL,
    `is_deleted` TINYINT(1) DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS `chart_of_accounts` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `type` VARCHAR(255) NOT NULL,
    `academic_session_id` INT NULL,
    `academic_semester_id` INT NULL,
    `institute_id` INT NULL,
    `is_deleted` TINYINT(1) DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS `assessments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT NULL,
    `choice_key_type` VARCHAR(255) NULL,
    `hint` TEXT NULL,
    `note` TEXT NULL,
    `class_subject_id` INT NULL,
    `assessment_no` VARCHAR(255) NULL,
    `start_date` DATETIME NOT NULL,
    `duration` TIME NOT NULL,
    `end_date` DATETIME NOT NULL,
    `institute_id` INT NOT NULL,
    `questions` TEXT NULL,
    `class_id` INT NOT NULL,
    `show_result_on_completed` TINYINT(1) DEFAULT 0,
    `assessment_group_id` INT NULL,
    `is_deleted` TINYINT(1) DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS `assessment_groups` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NULL,
    `assessment_group_no` VARCHAR(255) NULL,
    `institute_id` INT NULL,
    `note` TEXT NULL,
    `is_deleted` TINYINT(1) DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS `assessment_results` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `student_id` INT NOT NULL,
    `institute_id` INT NOT NULL,
    `assessment_id` INT NOT NULL,
    `score` INT NULL,
    `start_date` DATETIME NOT NULL,
    `end_date` DATETIME NULL,
    `duration` TIME NOT NULL,
    `is_started` TINYINT(1) DEFAULT 0,
    `is_abandoned` TINYINT(1) DEFAULT 0,
    `is_completed` TINYINT(1) DEFAULT 0,
    `is_marked` TINYINT(1) DEFAULT 0,
    `marked_date` DATETIME NULL,
    `is_published` TINYINT(1) DEFAULT 0,
    `published_date` DATETIME NULL,
    `is_timeout` TINYINT(1) DEFAULT 0,
    `answers` TEXT NULL,
    `is_deleted` TINYINT(1) DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS `employee_attendants` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `attendant_date` DATE NOT NULL,
    `institute_id` INT NOT NULL,
    `academic_session_id` INT NULL,
    `academic_semester_id` INT NULL,
    `attendances` TEXT NOT NULL,
    `is_deleted` TINYINT(1) DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS `student_attendants` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `attendant_date` DATE NOT NULL,
    `institute_id` INT NOT NULL,
    `class_id` INT NOT NULL,
    `attendances` TEXT NOT NULL,
    `academic_session_id` INT NULL,
    `academic_semester_id` INT NULL,
    `is_deleted` TINYINT(1) DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS `chats` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(255) NULL,
    `profileImage` VARCHAR(255) NULL,
    `message` TEXT NOT NULL,
    `room_id` VARCHAR(255) NOT NULL,
    `message_ref` VARCHAR(255) NOT NULL,
    `user_id` INT NOT NULL,
    `is_deleted` TINYINT(1) DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS `dms` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `message` TEXT NOT NULL,
    `message_ref` VARCHAR(255) NOT NULL,
    `sender_id` INT NOT NULL,
    `institute_id` INT NOT NULL,
    `is_deleted` TINYINT(1) DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS `meetings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `meeting_id` VARCHAR(255) NOT NULL,
    `title` VARCHAR(255) NULL,
    `description` TEXT NOT NULL,
    `with` VARCHAR(255) NOT NULL,
    `is_scheduled` TINYINT(1) DEFAULT 0,
    `date` DATETIME NOT NULL,
    `duration` VARCHAR(255) NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `class_id` INT NULL,
    `student_id` INT NULL,
    `parent_id` INT NULL,
    `teacher_id` INT NULL,
    `institute_id` INT NOT NULL,
    `organizer_id` INT NOT NULL,
    `academic_session_id` INT NULL,
    `academic_semester_id` INT NULL,
    `event_object` TEXT NULL,
    `is_deleted` TINYINT(1) DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS `meeting_invites` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `meeting_id` INT NOT NULL,
    `receiver_id` INT NOT NULL,
    `sender_id` INT NOT NULL,
    `academic_session_id` INT NULL,
    `academic_semester_id` INT NULL,
    `is_deleted` TINYINT(1) DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS `class` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `class_no` VARCHAR(255) NULL,
    `monthly_fee` DECIMAL(10,2) NOT NULL,
    `teacher_id` INT NOT NULL,
    `institute_id` INT NOT NULL,
    `isDeleted` TINYINT(1) DEFAULT 0,
    `is_deleted` TINYINT(1) DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS `class_subjects` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `icon_id` INT NULL,
    `total_exam_mark` INT NOT NULL,
    `class_subject_no` VARCHAR(255) NULL,
    `class_id` INT NOT NULL,
    `teacher_id` INT NOT NULL,
    `institute_id` INT NOT NULL,
    `isDeleted` TINYINT(1) DEFAULT 0,
    `is_deleted` TINYINT(1) DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS `configs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `key` VARCHAR(255) NOT NULL,
    `value` TEXT NULL,
    `is_deleted` TINYINT(1) DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS `countries` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `code` VARCHAR(255) NOT NULL,
    `is_deleted` TINYINT(1) DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS `employee_avatars` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `avatar_id` INT NOT NULL,
    `employee_id` INT NOT NULL,
    `is_deleted` TINYINT(1) DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS `exams` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT NULL,
    `choice_key_type` VARCHAR(255) NULL,
    `hint` TEXT NULL,
    `note` TEXT NULL,
    `class_subject_id` INT NULL,
    `exam_no` VARCHAR(255) NULL,
    `start_date` DATETIME NOT NULL,
    `duration` TIME NOT NULL,
    `end_date` DATETIME NOT NULL,
    `institute_id` INT NOT NULL,
    `questions` TEXT NULL,
    `class_id` INT NOT NULL,
    `show_result_on_completed` TINYINT(1) DEFAULT 0,
    `exam_group_id` INT NULL,
    `is_deleted` TINYINT(1) DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS `exam_groups` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NULL,
    `exam_group_no` VARCHAR(255) NULL,
    `institute_id` INT NULL,
    `note` TEXT NULL,
    `is_deleted` TINYINT(1) DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS `exam_results` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `student_id` INT NOT NULL,
    `institute_id` INT NOT NULL,
    `exam_id` INT NOT NULL,
    `score` INT NULL,
    `start_date` DATETIME NOT NULL,
    `end_date` DATETIME NULL,
    `duration` TIME NOT NULL,
    `is_started` TINYINT(1) DEFAULT 0,
    `is_abandoned` TINYINT(1) DEFAULT 0,
    `is_completed` TINYINT(1) DEFAULT 0,
    `is_marked` TINYINT(1) DEFAULT 0,
    `marked_date` DATETIME NULL,
    `is_published` TINYINT(1) DEFAULT 0,
    `published_date` DATETIME NULL,
    `is_timeout` TINYINT(1) DEFAULT 0,
    `answers` TEXT NULL,
    `is_deleted` TINYINT(1) DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS `homeworks` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `title` TEXT NOT NULL,
    `description` TEXT NOT NULL,
    `details` VARCHAR(255) NULL,
    `set_by_id` INT NULL,
    `startdate` DATE NOT NULL,
    `duedate` DATE NOT NULL,
    `institute_id` INT NOT NULL,
    `class_id` INT NOT NULL,
    `class_subject_id` INT NOT NULL,
    `assessment_id` INT NOT NULL,
    `is_deleted` TINYINT(1) DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS `homework_submissions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `content` VARCHAR(255) NOT NULL,
    `examiner_comment` TEXT NULL,
    `score` INT NULL,
    `grade` VARCHAR(255) NULL,
    `examiner_id` INT NULL,
    `status` VARCHAR(255) NULL,
    `homework_id` INT NULL,
    `is_deleted` TINYINT(1) DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS `icons` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `link_id` INT NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `is_deleted` TINYINT(1) DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS `insitute_licenses` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `license_id` INT NULL,
    `institute_id` INT NULL,
    `is_deleted` TINYINT(1) DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS `institute_activities` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(255) NOT NULL,
    `short_description` TEXT NOT NULL,
    `description` VARCHAR(255) NULL,
    `type` VARCHAR(255) NULL,
    `content` TEXT NULL,
    `data` TEXT NULL,
    `institute_id` INT NOT NULL,
    `user_id` INT NULL,
    `icon_id` INT NULL,
    `is_deleted` TINYINT(1) DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS `institute_avatars` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `avatar_id` INT NOT NULL,
    `institute_id` INT NOT NULL,
    `is_deleted` TINYINT(1) DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS `institute_challans` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `bank_name` VARCHAR(255) NULL,
    `bank_branch_address` VARCHAR(255) NULL,
    `account_no` VARCHAR(255) NULL,
    `account_name` VARCHAR(255) NULL,
    `instruction` TEXT NULL,
    `institute_id` INT NOT NULL,
    `avatar_id` INT NULL,
    `is_deleted` TINYINT(1) DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS `institute_class_particular_fees` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `institute_id` INT NOT NULL,
    `class_id` INT NOT NULL,
    `fees` TEXT NULL,
    `is_deleted` TINYINT(1) DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS `institute_employees` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `employee_no` VARCHAR(255) NULL,
    `institute_id` INT NOT NULL,
    `role` VARCHAR(255) NOT NULL,
    `employee_id` INT NOT NULL,
    `isDeleted` TINYINT(1) DEFAULT 0,
    `is_deleted` TINYINT(1) DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS `institute_families` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `avatar_id` INT NULL,
    `family_no` VARCHAR(255) NULL,
    `family_name` VARCHAR(255) NOT NULL,
    `institute_id` INT NOT NULL,
    `isDeleted` TINYINT(1) DEFAULT 0,
    `is_deleted` TINYINT(1) DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS `institute_mark_gradings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `overall_failure_criteria` INT NULL,
    `subject_failure_criteria` INT NULL,
    `no_of_subject_failure_criteria` INT NULL,
    `gradings` TEXT NULL,
    `traits` TEXT NULL,
    `skills` TEXT NULL,
    `institute_id` INT NULL,
    `is_deleted` TINYINT(1) DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS `institute_parents` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `parent_no` VARCHAR(255) NULL,
    `institute_id` INT NOT NULL,
    `parent_id` INT NOT NULL,
    `family_id` INT NULL,
    `isDeleted` TINYINT(1) DEFAULT 0,
    `is_deleted` TINYINT(1) DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS `institute_particular_fees` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `institute_id` INT NOT NULL,
    `fees` TEXT NULL,
    `is_deleted` TINYINT(1) DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS `institute_students` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `student_no` VARCHAR(255) NULL,
    `institute_id` INT NOT NULL,
    `class_id` INT NOT NULL,
    `student_id` INT NOT NULL,
    `family_id` INT NULL,
    `isDeleted` TINYINT(1) DEFAULT 0,
    `is_deleted` TINYINT(1) DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS `institute_student_particular_fees` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `institute_id` INT NOT NULL,
    `student_id` INT NOT NULL,
    `fees` TEXT NULL,
    `is_deleted` TINYINT(1) DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS `lesson_plans` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `class_id` INT NULL,
    `subject_id` INT NULL,
    `institute_id` INT NULL,
    `user_id` INT NULL,
    `grade` VARCHAR(255) NULL,
    `date` DATETIME NULL,
    `topic` VARCHAR(255) NULL,
    `lessonNumber` VARCHAR(255) NULL,
    `focusGoals` VARCHAR(255) NULL,
    `materials` VARCHAR(255) NULL,
    `learningObjectives` VARCHAR(255) NULL,
    `structureActivity` VARCHAR(255) NULL,
    `assessment` VARCHAR(255) NULL,
    `adminApprovalMessage` VARCHAR(255) NULL,
    `adminRejectionMessage` VARCHAR(255) NULL,
    `status` VARCHAR(255) NULL,
    `is_deleted` TINYINT(1) DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS `licenses` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `start_date` DATETIME NULL,
    `end_date` DATETIME NOT NULL,
    `policies` TEXT NOT NULL,
    `license_no` VARCHAR(255) NULL,
    `user_id` INT NULL,
    `activated_at` DATETIME NULL,
    `is_deleted` TINYINT(1) DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS `metas` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NULL,
    `value` TEXT NULL,
    `user_id` INT NULL,
    `is_deleted` TINYINT(1) DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS `notifications` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `content` TEXT NULL,
    `data` TEXT NULL,
    `title` VARCHAR(255) NOT NULL,
    `short_description` TEXT NOT NULL,
    `long_description` TEXT NULL,
    `type` VARCHAR(255) NULL,
    `link` VARCHAR(255) NULL,
    `isUnRead` TINYINT(1) DEFAULT 1,
    `user_id` INT NOT NULL,
    `is_deleted` TINYINT(1) DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS `parent_avatars` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `avatar_id` INT NOT NULL,
    `parent_id` INT NOT NULL,
    `is_deleted` TINYINT(1) DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS `policies` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `enable_parent` TINYINT(1) DEFAULT 1,
    `enable_live_class_room` TINYINT(1) DEFAULT 1,
    `enable_student` TINYINT(1) DEFAULT 1,
    `is_deleted` TINYINT(1) DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS `reports` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `class_id` INT NOT NULL,
    `class_subject_id` INT NOT NULL,
    `items` TEXT NULL,
    `params` TEXT NULL,
    `institute_id` INT NOT NULL,
    `submitedBy_id` INT NOT NULL,
    `submitedAt` DATETIME NULL,
    `academic_session_id` INT NULL,
    `academic_semester_id` INT NULL,
    `isDeleted` TINYINT(1) DEFAULT 0,
    `is_deleted` TINYINT(1) DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS `report_cards` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `clientId` VARCHAR(255) NULL,
    `label` VARCHAR(255) NOT NULL,
    `recipients` VARCHAR(255) NULL,
    `class_id` INT NULL,
    `student_id` INT NULL,
    `institute_id` INT NOT NULL,
    `submitedBy_id` INT NOT NULL,
    `authorizedBy_id` INT NULL,
    `authorizedAt` DATETIME NULL,
    `template` VARCHAR(255) NOT NULL,
    `academic_session_id` INT NULL,
    `academic_semester_id` INT NULL,
    `params` TEXT NULL,
    `isDeleted` TINYINT(1) DEFAULT 0,
    `is_deleted` TINYINT(1) DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS `report_cards` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `student_id` INT NOT NULL,
    `academic_session_id` INT NOT NULL,
    `academic_semester_id` INT NULL,
    `class_id` INT NOT NULL,
    `attendance` INT NULL,
    `total_score` INT NULL,
    `gpa` DECIMAL(10,2) NULL,
    `class_teacher_comment` TEXT NULL,
    `principal_comment` TEXT NULL,
    `status` VARCHAR(255) NULL,
    `rejection_reason` TEXT NULL,
    `published_at` DATETIME NULL,
    `institute_id` INT NOT NULL,
    `academic_performances` TEXT NULL,
    `psycho_behavioral_traits` TEXT NULL,
    `isDeleted` TINYINT(1) DEFAULT 0,
    `is_deleted` TINYINT(1) DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS `salaries` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `note` VARCHAR(255) NULL,
    `start_date` DATE NOT NULL,
    `end_date` DATE NOT NULL,
    `initial_amount` DECIMAL(10,2) NOT NULL,
    `bonus_amount` DECIMAL(10,2) NULL,
    `deducted_amount` DECIMAL(10,2) NULL,
    `total_amount` DECIMAL(10,2) NULL,
    `employee_id` INT NULL,
    `institute_id` INT NULL,
    `is_deleted` TINYINT(1) DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS `student_avatars` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `avatar_id` INT NOT NULL,
    `student_id` INT NOT NULL,
    `is_deleted` TINYINT(1) DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS `tests` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT NULL,
    `choice_key_type` VARCHAR(255) NULL,
    `hint` TEXT NULL,
    `note` TEXT NULL,
    `class_subject_id` INT NULL,
    `test_no` VARCHAR(255) NULL,
    `start_date` DATETIME NOT NULL,
    `duration` TIME NOT NULL,
    `end_date` DATETIME NOT NULL,
    `institute_id` INT NOT NULL,
    `questions` TEXT NULL,
    `class_id` INT NOT NULL,
    `show_result_on_completed` TINYINT(1) DEFAULT 0,
    `test_group_id` INT NULL,
    `is_deleted` TINYINT(1) DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS `test_groups` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NULL,
    `test_group_no` VARCHAR(255) NULL,
    `institute_id` INT NULL,
    `note` TEXT NULL,
    `is_deleted` TINYINT(1) DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS `test_results` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `student_id` INT NOT NULL,
    `institute_id` INT NOT NULL,
    `test_id` INT NOT NULL,
    `score` INT NULL,
    `start_date` DATETIME NOT NULL,
    `end_date` DATETIME NULL,
    `duration` TIME NOT NULL,
    `is_started` TINYINT(1) DEFAULT 0,
    `is_abandoned` TINYINT(1) DEFAULT 0,
    `is_completed` TINYINT(1) DEFAULT 0,
    `is_marked` TINYINT(1) DEFAULT 0,
    `marked_date` DATETIME NULL,
    `is_published` TINYINT(1) DEFAULT 0,
    `published_date` DATETIME NULL,
    `is_timeout` TINYINT(1) DEFAULT 0,
    `answers` TEXT NULL,
    `is_deleted` TINYINT(1) DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS `timetables` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NULL,
    `start_date` DATE NULL,
    `end_date` DATE NULL,
    `institute_id` INT NOT NULL,
    `periods` TEXT NULL,
    `is_deleted` TINYINT(1) DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);