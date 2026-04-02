-- Create Database
CREATE DATABASE IF NOT EXISTS coaching_db;
USE coaching_db;

-- =====================================================
-- Table: users (Authentication & Role Management)
-- =====================================================
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    role ENUM('admin', 'teacher', 'student') DEFAULT 'student',
    status TINYINT DEFAULT 1,
    last_login DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- =====================================================
-- Table: classes
-- =====================================================
CREATE TABLE classes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    class_name VARCHAR(50) NOT NULL,
    section VARCHAR(10),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- =====================================================
-- Table: subjects
-- =====================================================
CREATE TABLE subjects (
    id INT PRIMARY KEY AUTO_INCREMENT,
    subject_name VARCHAR(100) NOT NULL,
    subject_code VARCHAR(20),
    class_id INT,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE
);

-- =====================================================
-- Table: teachers
-- =====================================================
CREATE TABLE teachers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT UNIQUE,
    teacher_id VARCHAR(20) UNIQUE NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    email VARCHAR(100) UNIQUE,
    phone VARCHAR(15),
    qualification TEXT,
    interested_subjects TEXT,
    address TEXT,
    photo VARCHAR(255),
    joining_date DATE,
    status TINYINT DEFAULT 1,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- =====================================================
-- Table: teacher_subjects (Assignment)
-- =====================================================
CREATE TABLE teacher_subjects (
    id INT PRIMARY KEY AUTO_INCREMENT,
    teacher_id INT,
    subject_id INT,
    class_id INT,
    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE
);

-- =====================================================
-- Table: students
-- =====================================================
CREATE TABLE students (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT UNIQUE,
    student_id VARCHAR(20) UNIQUE NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    father_name VARCHAR(100),
    mother_name VARCHAR(100),
    email VARCHAR(100) UNIQUE,
    phone VARCHAR(15),
    dob DATE,
    gender ENUM('Male', 'Female', 'Other'),
    address TEXT,
    photo VARCHAR(255),
    class_id INT,
    batch_no VARCHAR(20),
    roll_number VARCHAR(20),
    admission_date DATE,
    status TINYINT DEFAULT 1,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE SET NULL
);

-- =====================================================
-- Table: attendance
-- =====================================================
CREATE TABLE attendance (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT,
    class_id INT,
    date DATE,
    status ENUM('Present', 'Absent', 'Late') DEFAULT 'Absent',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE
);

-- =====================================================
-- Table: exam_types
-- =====================================================
CREATE TABLE exam_types (
    id INT PRIMARY KEY AUTO_INCREMENT,
    exam_name VARCHAR(50) NOT NULL,
    description TEXT
);

-- =====================================================
-- Table: exam_grades
-- =====================================================
CREATE TABLE exam_grades (
    id INT PRIMARY KEY AUTO_INCREMENT,
    grade VARCHAR(5) NOT NULL,
    min_percentage DECIMAL(5,2),
    max_percentage DECIMAL(5,2),
    points DECIMAL(3,2)
);

-- =====================================================
-- Table: exam_routine
-- =====================================================
CREATE TABLE exam_routine (
    id INT PRIMARY KEY AUTO_INCREMENT,
    exam_type_id INT,
    class_id INT,
    subject_id INT,
    exam_date DATE,
    start_time TIME,
    end_time TIME,
    file_path VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (exam_type_id) REFERENCES exam_types(id),
    FOREIGN KEY (class_id) REFERENCES classes(id),
    FOREIGN KEY (subject_id) REFERENCES subjects(id)
);

-- =====================================================
-- Table: results
-- =====================================================
CREATE TABLE results (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT,
    exam_type_id INT,
    subject_id INT,
    marks_obtained DECIMAL(5,2),
    total_marks DECIMAL(5,2),
    percentage DECIMAL(5,2),
    grade VARCHAR(5),
    points DECIMAL(3,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id),
    FOREIGN KEY (exam_type_id) REFERENCES exam_types(id),
    FOREIGN KEY (subject_id) REFERENCES subjects(id)
);

-- =====================================================
-- Table: fees_head
-- =====================================================
CREATE TABLE fees_head (
    id INT PRIMARY KEY AUTO_INCREMENT,
    fee_name VARCHAR(100) NOT NULL,
    description TEXT,
    is_mandatory TINYINT DEFAULT 1
);

-- =====================================================
-- Table: class_fees
-- =====================================================
CREATE TABLE class_fees (
    id INT PRIMARY KEY AUTO_INCREMENT,
    class_id INT,
    fee_head_id INT,
    amount DECIMAL(10,2) NOT NULL,
    due_date DATE,
    FOREIGN KEY (class_id) REFERENCES classes(id),
    FOREIGN KEY (fee_head_id) REFERENCES fees_head(id)
);

-- =====================================================
-- Table: fee_collections
-- =====================================================
CREATE TABLE fee_collections (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT,
    fee_head_id INT,
    amount DECIMAL(10,2) NOT NULL,
    paid_amount DECIMAL(10,2),
    due_amount DECIMAL(10,2),
    payment_date DATE,
    payment_method ENUM('Cash', 'Card', 'Bank Transfer', 'Online'),
    receipt_no VARCHAR(50) UNIQUE,
    status ENUM('Paid', 'Partial', 'Unpaid') DEFAULT 'Unpaid',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id),
    FOREIGN KEY (fee_head_id) REFERENCES fees_head(id)
);

-- =====================================================
-- Table: monthly_fees (Recurring Monthly Fees Tracking)
-- =====================================================
CREATE TABLE monthly_fees (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    class_id INT NOT NULL,
    month VARCHAR(20) NOT NULL,
    year INT NOT NULL,
    tuition_fee DECIMAL(10,2) NOT NULL,
    paid_amount DECIMAL(10,2) DEFAULT 0,
    due_amount DECIMAL(10,2) NOT NULL,
    status ENUM('Paid', 'Partial', 'Unpaid') DEFAULT 'Unpaid',
    payment_date DATE,
    payment_method VARCHAR(50),
    receipt_no VARCHAR(50),
    transaction_id VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    UNIQUE KEY unique_month (student_id, month, year)
);

-- =====================================================
-- Table: expense_head
-- =====================================================
CREATE TABLE expense_head (
    id INT PRIMARY KEY AUTO_INCREMENT,
    expense_name VARCHAR(100) NOT NULL,
    description TEXT
);

-- =====================================================
-- Table: expenses
-- =====================================================
CREATE TABLE expenses (
    id INT PRIMARY KEY AUTO_INCREMENT,
    expense_head_id INT,
    amount DECIMAL(10,2) NOT NULL,
    expense_date DATE,
    description TEXT,
    bill_no VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (expense_head_id) REFERENCES expense_head(id)
);

-- =====================================================
-- Table: sms_logs
-- =====================================================
CREATE TABLE sms_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    mobile_number VARCHAR(15),
    message TEXT,
    type ENUM('Student', 'Teacher', 'Bulk'),
    status ENUM('Sent', 'Failed') DEFAULT 'Sent',
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- =====================================================
-- Table: syllabus
-- =====================================================
CREATE TABLE syllabus (
    id INT PRIMARY KEY AUTO_INCREMENT,
    class_id INT,
    subject_id INT,
    title VARCHAR(255),
    file_path VARCHAR(255),
    upload_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (class_id) REFERENCES classes(id),
    FOREIGN KEY (subject_id) REFERENCES subjects(id)
);

-- =====================================================
-- Table: admission_applications
-- =====================================================
CREATE TABLE admission_applications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    full_name VARCHAR(100) NOT NULL,
    gender ENUM('Male', 'Female', 'Other'),
    mobile VARCHAR(15) NOT NULL,
    email VARCHAR(100) NOT NULL,
    address TEXT,
    program VARCHAR(50) NOT NULL,
    `group` VARCHAR(50),
    parent_name VARCHAR(100) NOT NULL,
    parent_email VARCHAR(100) NOT NULL,
    parent_phone VARCHAR(15) NOT NULL,
    monthly_fee DECIMAL(10,2),
    transaction_id VARCHAR(100),
    payment_method VARCHAR(50),
    application_fee DECIMAL(10,2),
    status ENUM('Pending', 'Approved', 'Rejected') DEFAULT 'Pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- =====================================================
-- Insert Default Data
-- =====================================================

-- Insert Admin User (password: admin123 - you'll hash it later)
INSERT INTO users (username, password, email, role) VALUES 
('admin', '$2y$10$YourHashedPasswordHere', 'admin@coaching.com', 'admin');

-- Insert Sample Classes
INSERT INTO classes (class_name, section) VALUES 
('Class 9', 'A'),
('Class 9', 'B'),
('Class 10', 'A'),
('Class 10', 'B'),
('Class 11', 'Science'),
('Class 11', 'Commerce');

-- Insert Exam Types
INSERT INTO exam_types (exam_name, description) VALUES 
('Mid Term', 'Mid Term Examination'),
('Final Term', 'Final Term Examination'),
('Weekly Test', 'Weekly Class Test'),
('Monthly Test', 'Monthly Assessment');

-- Insert Fee Heads
INSERT INTO fees_head (fee_name, description) VALUES 
('Tuition Fee', 'Monthly Tuition Fee'),
('Admission Fee', 'One Time Admission Fee'),
('Exam Fee', 'Examination Fee'),
('Library Fee', 'Library Usage Fee'),
('Sports Fee', 'Sports Activities Fee');

-- Insert Expense Heads
INSERT INTO expense_head (expense_name, description) VALUES 
('Salary', 'Staff and Teacher Salaries'),
('Electricity', 'Electricity Bills'),
('Rent', 'Building Rent'),
('Maintenance', 'Center Maintenance'),
('Stationery', 'Office Stationery');

-- Insert Grades
INSERT INTO exam_grades (grade, min_percentage, max_percentage, points) VALUES 
('A+', 80, 100, 4.00),
('A', 70, 79.99, 3.50),
('A-', 60, 69.99, 3.00),
('B', 50, 59.99, 2.50),
('C', 40, 49.99, 2.00),
('D', 33, 39.99, 1.50),
('F', 0, 32.99, 0.00);