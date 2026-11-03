
-- Clear-Pass Database Schema
CREATE DATABASE IF NOT EXISTS system_heyjude;
USE system_heyjude;

-- Users Table (Students, Staff, Admin)
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id VARCHAR(50) UNIQUE NOT NULL, -- Student ID or Staff ID
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role ENUM('student', 'department_head', 'admin') DEFAULT 'student',
    department_id INT DEFAULT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Departments Table with Sequencing
CREATE TABLE departments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    sequence_order INT NOT NULL UNIQUE, -- 1, 2, 3, etc.
    icon VARCHAR(50) DEFAULT 'building-2',
    color VARCHAR(20) DEFAULT 'blue',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Clearance Requests
CREATE TABLE clearances (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    semester VARCHAR(20) NOT NULL,
    school_year VARCHAR(20) NOT NULL,
    status ENUM('pending', 'in_progress', 'completed', 'cancelled') DEFAULT 'pending',
    progress_percent INT DEFAULT 0,
    permit_issued BOOLEAN DEFAULT FALSE,
    permit_issued_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Clearance Items (Per Department)
CREATE TABLE clearance_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    clearance_id INT NOT NULL,
    department_id INT NOT NULL,
    status ENUM('locked', 'pending', 'approved', 'rejected', 'on_hold') DEFAULT 'locked',
    remarks TEXT,
    hold_reason TEXT,
    approved_by INT,
    approved_at TIMESTAMP NULL,
    sequence_number INT NOT NULL,
    FOREIGN KEY (clearance_id) REFERENCES clearances(id) ON DELETE CASCADE,
    FOREIGN KEY (department_id) REFERENCES departments(id),
    FOREIGN KEY (approved_by) REFERENCES users(id)
);

-- Activity Logs
CREATE TABLE activity_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    details TEXT,
    ip_address VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Insert Default Admin
INSERT INTO users (user_id, email, password, full_name, role) VALUES 
('ADMIN001', 'admin@clearpass.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', 'admin');

-- Insert Default Departments (Sequential)
INSERT INTO departments (name, description, sequence_order, icon, color) VALUES
('Library', 'Check for unreturned books and outstanding fines', 1, 'book-open', 'blue'),
('Laboratory', 'Equipment return and breakage fees', 2, 'flask-conical', 'green'),
('Guidance Office', 'Counseling clearance and disciplinary records', 3, 'users', 'purple'),
('Clinic', 'Medical clearance and health records', 4, 'heart-pulse', 'red'),
('Accounting', 'Tuition fee balance and other financial obligations', 5, 'calculator', 'orange'),
('Registrar', 'Final academic records verification', 6, 'file-text', 'indigo');