-- Architecture Definitions for RosmonSMS PHP Migration
-- This addresses user roles, parent-student relationships, and centralized account categories.

-- 1. Centralized Income Items Under Accounts
CREATE TABLE income_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT NULL,
    is_default BOOLEAN DEFAULT TRUE -- Flag for predefined system categories vs customized
);

-- Pre-populating default Income Categories (as requested via dropdown requirement)
INSERT INTO income_categories (name) VALUES 
('Tuition Fees'), 
('Lesson fees'), 
('Boarding fees'), 
('Special program fees (e.g., music, art, coding, etc.)'), 
('Exam fees'), 
('Excursion & Special Events (Graduation, Children Day, party)'), 
('Sales of school uniform'), 
('Sales of Textbooks & Stationary'), 
('Sales of Assets'), 
('Investments');

-- 2. Centralized Expense Items Under Accounts
CREATE TABLE expense_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT NULL,
    is_default BOOLEAN DEFAULT TRUE 
);

-- Pre-populating default Expense Categories (as requested for Monthly Dashboard Roll-ups)
INSERT INTO expense_categories (name) VALUES 
('Salary and Wages'), 
('Transportation costs'), 
('Motor Vehicle Repairs'), 
('Utilities (Electricity, Water, LAWMA, Internet, ICT)'), 
('Insurance Expenses'), 
('Rent or Mortgage payments'), 
('Maintenance and repairs'), 
('Textbooks and educational resources'), 
('Uniforms & Materials'), 
('Infrastructure'), 
('School Equipment (Computer, Lab, Sports)'), 
('Miscellaneous');

-- 3. Users, Roles, and the Super Admin
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('super_admin', 'school_admin', 'finance_officer', 'teacher', 'parent', 'student', 'support_staff') NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    phone_number VARCHAR(20) NULL,
    full_name VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 4. Linking Every Student to Their Parent on the System
CREATE TABLE students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL, -- Core user account reference for portal login
    admission_number VARCHAR(100) UNIQUE NOT NULL,
    class_id INT NOT NULL,
    -- Other biodata, academic stats...
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE prents_guardians (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL, -- Core user account reference for parent portal login
    device_token VARCHAR(255) NULL, -- Specifically for the stated push notification feature
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- The crucial link joining Parents to Students allowing read-only insight to results & debts
CREATE TABLE parent_student_relations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    parent_id INT NOT NULL,
    student_id INT NOT NULL,
    relation_type ENUM('father', 'mother', 'guardian') NOT NULL,
    FOREIGN KEY (parent_id) REFERENCES prents_guardians(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
);

-- 5. Student Fee Payment Tracking
CREATE TABLE fee_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    income_category_id INT NOT NULL, -- Linking to Centralized Income Accounts structure
    amount_paid DECIMAL(12,2) NOT NULL,
    payment_date DATE NOT NULL,
    receipt_no VARCHAR(100) UNIQUE NOT NULL,
    FOREIGN KEY (student_id) REFERENCES students(id),
    FOREIGN KEY (income_category_id) REFERENCES income_categories(id)
);

-- 6. Super Admin Licensing Structure
CREATE TABLE licenses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    license_key VARCHAR(100) UNIQUE NOT NULL,
    school_name VARCHAR(255) NOT NULL,
    status ENUM('active', 'revoked', 'expired') DEFAULT 'active',
    plan_type ENUM('basic', 'premium') DEFAULT 'basic',
    expires_at DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 7. Report Card Approvals (Parent Portal Publishing)
CREATE TABLE report_cards (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    term VARCHAR(50) NOT NULL,
    academic_session VARCHAR(50) NOT NULL,
    is_published BOOLEAN DEFAULT FALSE, -- Must be TRUE for Parent Portal to gain Read-Only Access
    published_by_admin_id INT NULL,
    FOREIGN KEY (student_id) REFERENCES students(id)
);
