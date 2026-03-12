-- eClass Classroom Management System
-- Database Schema

CREATE DATABASE IF NOT EXISTS eclass_db;
USE eclass_db;

-- ========================================
-- SECTIONS
-- ========================================
CREATE TABLE sections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    section_name VARCHAR(50) NOT NULL UNIQUE,
    description VARCHAR(255) DEFAULT NULL,
    year_level TINYINT DEFAULT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ========================================
-- AUTH TOKENS (Remember Me)
-- ========================================
CREATE TABLE auth_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token_hash VARCHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY (token_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ========================================
-- ACCOUNTS (Class Officers)
-- ========================================
CREATE TABLE accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL UNIQUE,
    full_name VARCHAR(100) NOT NULL,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'teacher', 'officer') DEFAULT 'officer',
    section VARCHAR(20) DEFAULT NULL,
    avatar VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ========================================
-- STUDENTS
-- ========================================
CREATE TABLE students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id VARCHAR(50) NOT NULL UNIQUE,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    email VARCHAR(100) DEFAULT NULL,
    phone VARCHAR(20) DEFAULT NULL,
    student_type ENUM('regular', 'irregular') DEFAULT 'regular',
    year_level TINYINT DEFAULT 1,
    section VARCHAR(20) DEFAULT NULL,
    avatar VARCHAR(255) DEFAULT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ========================================
-- SUBJECTS
-- ========================================
CREATE TABLE subjects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    subject_code VARCHAR(20) NOT NULL,
    subject_name VARCHAR(100) NOT NULL,
    description TEXT DEFAULT NULL,
    schedule_day VARCHAR(50) DEFAULT NULL,
    schedule_time VARCHAR(50) DEFAULT NULL,
    room VARCHAR(50) DEFAULT NULL,
    teacher_id INT DEFAULT NULL,
    created_by INT DEFAULT NULL,
    status ENUM('active', 'archived') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES accounts(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES accounts(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ========================================
-- SUBJECT ENROLLMENTS (Students in Subjects)
-- ========================================
CREATE TABLE subject_enrollments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    subject_id INT NOT NULL,
    student_id INT NOT NULL,
    enrolled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_enrollment (subject_id, student_id),
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ========================================
-- ATTENDANCE
-- ========================================
CREATE TABLE attendance_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    subject_id INT NOT NULL,
    session_date DATE NOT NULL,
    notes TEXT DEFAULT NULL,
    is_locked TINYINT(1) NOT NULL DEFAULT 0,
    locked_by INT DEFAULT NULL,
    locked_at DATETIME DEFAULT NULL,
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES accounts(id) ON DELETE SET NULL,
    FOREIGN KEY (locked_by) REFERENCES accounts(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE attendance_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id INT NOT NULL,
    student_id INT NOT NULL,
    status ENUM('present', 'absent', 'late', 'excused') DEFAULT 'present',
    remarks TEXT DEFAULT NULL,
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_record (session_id, student_id),
    FOREIGN KEY (session_id) REFERENCES attendance_sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ========================================
-- CLASS FUNDS
-- ========================================
CREATE TABLE funds (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fund_name VARCHAR(100) NOT NULL,
    fund_type ENUM('standard', 'voluntary', 'general') NOT NULL DEFAULT 'standard',
    description TEXT DEFAULT NULL,
    amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    frequency ENUM('one-time', 'weekly', 'monthly', 'semestral', 'annual') DEFAULT 'one-time',
    due_date DATE DEFAULT NULL,
    subject_id INT DEFAULT NULL,
    status ENUM('active', 'closed') DEFAULT 'active',
    is_locked TINYINT(1) NOT NULL DEFAULT 0,
    locked_by INT DEFAULT NULL,
    locked_at DATETIME DEFAULT NULL,
    auto_lock_days INT NOT NULL DEFAULT 0,
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES accounts(id) ON DELETE SET NULL,
    FOREIGN KEY (locked_by) REFERENCES accounts(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE fund_assignees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fund_id INT NOT NULL,
    student_id INT NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_assignee (fund_id, student_id),
    FOREIGN KEY (fund_id) REFERENCES funds(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE fund_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fund_id INT NOT NULL,
    billing_period_id INT DEFAULT NULL,
    student_id INT DEFAULT NULL,
    amount_paid DECIMAL(10,2) NOT NULL,
    payment_date DATE NOT NULL,
    payment_method VARCHAR(50) DEFAULT 'cash',
    receipt_no VARCHAR(50) DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    recorded_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (fund_id) REFERENCES funds(id) ON DELETE CASCADE,
    FOREIGN KEY (billing_period_id) REFERENCES fund_billing_periods(id) ON DELETE SET NULL,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (recorded_by) REFERENCES accounts(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE fund_billing_periods (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fund_id INT NOT NULL,
    period_label VARCHAR(50) NOT NULL,
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    due_date DATE DEFAULT NULL,
    status ENUM('active', 'closed') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (fund_id) REFERENCES funds(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE fund_withdrawals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fund_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    withdrawal_date DATE NOT NULL,
    purpose VARCHAR(255) NOT NULL,
    notes TEXT DEFAULT NULL,
    recorded_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (fund_id) REFERENCES funds(id) ON DELETE CASCADE,
    FOREIGN KEY (recorded_by) REFERENCES accounts(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ========================================
-- DEFAULT ADMIN ACCOUNT
-- Password: admin123 (bcrypt hash)
-- ========================================
INSERT INTO accounts (full_name, username, password, role) VALUES
('System Admin', 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');
