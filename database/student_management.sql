-- =====================================================================
-- Student Management System
-- Database: student_management_db
-- Import this file through phpMyAdmin or:
--   mysql -u root < database/student_management.sql
-- Default admin login: admin / admin123
-- =====================================================================

DROP DATABASE IF EXISTS student_management_db;
CREATE DATABASE student_management_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE student_management_db;

-- ---------------------------------------------------------------------
-- Admin users
-- ---------------------------------------------------------------------
CREATE TABLE admins (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(120) DEFAULT NULL,
    last_login DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Courses
-- ---------------------------------------------------------------------
CREATE TABLE courses (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) NOT NULL UNIQUE,
    name VARCHAR(120) NOT NULL,
    duration_years TINYINT UNSIGNED NOT NULL DEFAULT 3,
    total_fee DECIMAL(10,2) NOT NULL DEFAULT 0,
    description TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Teachers
-- ---------------------------------------------------------------------
CREATE TABLE teachers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_id VARCHAR(30) NOT NULL UNIQUE,
    full_name VARCHAR(120) NOT NULL,
    email VARCHAR(120) NOT NULL UNIQUE,
    phone VARCHAR(20) DEFAULT NULL,
    gender ENUM('Male','Female','Other') DEFAULT NULL,
    dob DATE DEFAULT NULL,
    qualification VARCHAR(120) DEFAULT NULL,
    address VARCHAR(255) DEFAULT NULL,
    photo VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Subjects  (belong to a course + semester, optionally a teacher)
-- ---------------------------------------------------------------------
CREATE TABLE subjects (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    course_id INT UNSIGNED NOT NULL,
    semester TINYINT UNSIGNED NOT NULL DEFAULT 1,
    code VARCHAR(30) NOT NULL,
    name VARCHAR(120) NOT NULL,
    teacher_id INT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_subject (course_id, code),
    CONSTRAINT fk_subjects_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    CONSTRAINT fk_subjects_teacher FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Students
-- ---------------------------------------------------------------------
CREATE TABLE students (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id VARCHAR(30) NOT NULL UNIQUE,
    full_name VARCHAR(120) NOT NULL,
    email VARCHAR(120) NOT NULL UNIQUE,
    phone VARCHAR(20) DEFAULT NULL,
    gender ENUM('Male','Female','Other') DEFAULT NULL,
    dob DATE DEFAULT NULL,
    address VARCHAR(255) DEFAULT NULL,
    course_id INT UNSIGNED NOT NULL,
    semester TINYINT UNSIGNED NOT NULL DEFAULT 1,
    admission_date DATE NOT NULL,
    photo VARCHAR(255) DEFAULT NULL,
    status ENUM('Active','Inactive','Graduated') NOT NULL DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_students_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Attendance (one record per student per day)
-- ---------------------------------------------------------------------
CREATE TABLE attendance (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id INT UNSIGNED NOT NULL,
    course_id INT UNSIGNED NOT NULL,
    semester TINYINT UNSIGNED NOT NULL,
    attendance_date DATE NOT NULL,
    status ENUM('Present','Absent','Late') NOT NULL DEFAULT 'Present',
    marked_by INT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_attendance (student_id, attendance_date),
    CONSTRAINT fk_attendance_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    CONSTRAINT fk_attendance_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    CONSTRAINT fk_attendance_admin FOREIGN KEY (marked_by) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Fee payments
-- ---------------------------------------------------------------------
CREATE TABLE fees (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id INT UNSIGNED NOT NULL,
    course_id INT UNSIGNED NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_date DATE NOT NULL,
    payment_method ENUM('Cash','Card','Bank Transfer','Online') NOT NULL DEFAULT 'Cash',
    receipt_no VARCHAR(40) NOT NULL UNIQUE,
    remarks VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_fees_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    CONSTRAINT fk_fees_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Examinations
-- ---------------------------------------------------------------------
CREATE TABLE examinations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    course_id INT UNSIGNED NOT NULL,
    semester TINYINT UNSIGNED NOT NULL,
    exam_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_exam_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Results / marks
-- ---------------------------------------------------------------------
CREATE TABLE results (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    examination_id INT UNSIGNED NOT NULL,
    student_id INT UNSIGNED NOT NULL,
    subject_id INT UNSIGNED NOT NULL,
    marks_obtained DECIMAL(6,2) NOT NULL DEFAULT 0,
    max_marks DECIMAL(6,2) NOT NULL DEFAULT 100,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_result (examination_id, student_id, subject_id),
    CONSTRAINT fk_results_exam FOREIGN KEY (examination_id) REFERENCES examinations(id) ON DELETE CASCADE,
    CONSTRAINT fk_results_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    CONSTRAINT fk_results_subject FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Notices
-- ---------------------------------------------------------------------
CREATE TABLE notices (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    message TEXT NOT NULL,
    posted_by INT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_notices_admin FOREIGN KEY (posted_by) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- =====================================================================
-- Seed data
-- =====================================================================

-- Password: admin123
INSERT INTO admins (username, password, full_name, email) VALUES
('admin', '$2y$10$KIImF2wv8QXUHVti3tXeH.8Ex8TSRplFsoF0VtWI9iX8Pr5PFJ64S', 'System Administrator', 'admin@school.edu');

INSERT INTO courses (code, name, duration_years, total_fee, description) VALUES
('BCA',    'Bachelor of Computer Applications', 3, 60000.00, 'Undergraduate program in computer applications and software development.'),
('BSCIT',  'B.Sc. Information Technology',      3, 55000.00, 'Undergraduate program focused on information technology and networking.'),
('BCOM',   'Bachelor of Commerce',              3, 30000.00, 'Undergraduate program in accounting, finance and business.'),
('BBA',    'Bachelor of Business Administration', 3, 45000.00, 'Undergraduate program in business management.'),
('MCA',    'Master of Computer Applications',   2, 80000.00, 'Postgraduate program in advanced computing.');

INSERT INTO teachers (employee_id, full_name, email, phone, gender, dob, qualification, address) VALUES
('TCH-001', 'Dr. Anita Sharma',  'anita.sharma@school.edu',  '9800000001', 'Female', '1980-04-12', 'Ph.D. Computer Science', '12 Park Street'),
('TCH-002', 'Mr. Rajesh Verma',  'rajesh.verma@school.edu',  '9800000002', 'Male',   '1983-09-23', 'M.Sc. Mathematics',      '45 Lake Road'),
('TCH-003', 'Ms. Priya Nair',    'priya.nair@school.edu',    '9800000003', 'Female', '1986-01-15', 'M.Com., MBA',            '8 Hill View'),
('TCH-004', 'Mr. David Fernandes','david.fernandes@school.edu','9800000004','Male',   '1979-11-30', 'M.Tech. IT',             '77 Green Avenue'),
('TCH-005', 'Mrs. Sunita Rao',   'sunita.rao@school.edu',    '9800000005', 'Female', '1988-06-08', 'M.A. English',           '23 Rose Villa');

INSERT INTO subjects (course_id, semester, code, name, teacher_id) VALUES
(1, 1, 'BCA101', 'Programming Fundamentals', 1),
(1, 1, 'BCA102', 'Mathematics I',            2),
(1, 1, 'BCA103', 'Computer Organization',    4),
(1, 2, 'BCA201', 'Data Structures',          1),
(1, 2, 'BCA202', 'Database Management',      4),
(2, 1, 'IT101',  'Introduction to IT',       4),
(2, 1, 'IT102',  'Programming in C',         1),
(2, 2, 'IT201',  'Web Technologies',         4),
(3, 1, 'COM101', 'Financial Accounting',     3),
(3, 1, 'COM102', 'Business Communication',   5),
(4, 1, 'BBA101', 'Principles of Management', 3),
(4, 1, 'BBA102', 'Business Economics',       2),
(5, 1, 'MCA101', 'Advanced Algorithms',      1),
(5, 1, 'MCA102', 'Software Engineering',     4);

INSERT INTO students (student_id, full_name, email, phone, gender, dob, address, course_id, semester, admission_date, status) VALUES
('STU-2024-001', 'Aarav Mehta',    'aarav.mehta@student.edu',    '9100000001', 'Male',   '2004-02-14', '21 Maple Street', 1, 1, '2024-06-01', 'Active'),
('STU-2024-002', 'Isha Kapoor',    'isha.kapoor@student.edu',    '9100000002', 'Female', '2003-07-19', '9 Palm Avenue',   1, 1, '2024-06-01', 'Active'),
('STU-2024-003', 'Rohan Gupta',    'rohan.gupta@student.edu',    '9100000003', 'Male',   '2004-11-05', '56 Oak Lane',     1, 2, '2024-06-01', 'Active'),
('STU-2024-004', 'Sneha Iyer',     'sneha.iyer@student.edu',     '9100000004', 'Female', '2003-03-27', '3 Lake Drive',    2, 1, '2024-06-03', 'Active'),
('STU-2024-005', 'Kabir Singh',    'kabir.singh@student.edu',    '9100000005', 'Male',   '2004-08-11', '18 River Road',   2, 1, '2024-06-03', 'Active'),
('STU-2024-006', 'Meera Joshi',    'meera.joshi@student.edu',    '9100000006', 'Female', '2003-12-01', '7 Garden Close',  3, 1, '2024-06-05', 'Active'),
('STU-2024-007', 'Aditya Rao',     'aditya.rao@student.edu',     '9100000007', 'Male',   '2004-05-22', '90 Church Street',3, 1, '2024-06-05', 'Active'),
('STU-2024-008', 'Tanvi Desai',    'tanvi.desai@student.edu',    '9100000008', 'Female', '2003-09-16', '11 Sunset Blvd',  4, 1, '2024-06-07', 'Active'),
('STU-2024-009', 'Arjun Malhotra', 'arjun.malhotra@student.edu', '9100000009', 'Male',   '2004-01-09', '64 Market Road',  4, 1, '2024-06-07', 'Active'),
('STU-2024-010', 'Nisha Patel',    'nisha.patel@student.edu',    '9100000010', 'Female', '2002-10-30', '29 Elm Street',   5, 1, '2024-07-01', 'Active'),
('STU-2024-011', 'Vikram Shah',    'vikram.shah@student.edu',    '9100000011', 'Male',   '2002-04-18', '5 Cedar Court',   5, 1, '2024-07-01', 'Active'),
('STU-2024-012', 'Riya Sen',       'riya.sen@student.edu',       '9100000012', 'Female', '2004-06-25', '32 Pine Place',   1, 2, '2024-06-01', 'Active');

-- Attendance for the last 10 days (weekdays only) for first 6 students
INSERT INTO attendance (student_id, course_id, semester, attendance_date, status, marked_by)
SELECT s.id, s.course_id, s.semester, d.dt,
       CASE WHEN (s.id + DAYOFMONTH(d.dt)) % 6 = 0 THEN 'Absent'
            WHEN (s.id + DAYOFMONTH(d.dt)) % 7 = 0 THEN 'Late'
            ELSE 'Present' END,
       1
FROM students s
JOIN (
    SELECT CURDATE() - INTERVAL n DAY AS dt
    FROM (SELECT 0 n UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4
          UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) days
) d ON DAYOFWEEK(d.dt) NOT IN (1, 7)
WHERE s.id <= 6;

-- Fee payments
INSERT INTO fees (student_id, course_id, amount, payment_date, payment_method, receipt_no, remarks) VALUES
(1, 1, 30000.00, '2024-06-05', 'Bank Transfer', 'RCPT-100001', 'First installment'),
(2, 1, 60000.00, '2024-06-06', 'Online',        'RCPT-100002', 'Full year fee'),
(3, 1, 20000.00, '2024-06-10', 'Cash',          'RCPT-100003', 'First installment'),
(4, 2, 27500.00, '2024-06-12', 'Card',          'RCPT-100004', 'First installment'),
(5, 2, 55000.00, '2024-06-15', 'Bank Transfer', 'RCPT-100005', 'Full year fee'),
(6, 3, 15000.00, '2024-06-18', 'Cash',          'RCPT-100006', 'First installment'),
(8, 4, 22500.00, '2024-06-20', 'Online',        'RCPT-100007', 'First installment'),
(10, 5, 40000.00, '2024-07-02', 'Bank Transfer','RCPT-100008', 'First installment'),
(11, 5, 80000.00, '2024-07-05', 'Card',          'RCPT-100009', 'Full course fee');

-- Examinations + results
INSERT INTO examinations (name, course_id, semester, exam_date) VALUES
('Mid-Term Examination 2024', 1, 1, '2024-09-15'),
('Mid-Term Examination 2024', 1, 2, '2024-09-16'),
('Mid-Term Examination 2024', 2, 1, '2024-09-17'),
('Mid-Term Examination 2024', 3, 1, '2024-09-18'),
('Mid-Term Examination 2024', 4, 1, '2024-09-19'),
('Mid-Term Examination 2024', 5, 1, '2024-09-20');

INSERT INTO results (examination_id, student_id, subject_id, marks_obtained, max_marks) VALUES
(1, 1, 1, 78.00, 100), (1, 1, 2, 65.00, 100), (1, 1, 3, 82.00, 100),
(1, 2, 1, 91.00, 100), (1, 2, 2, 88.00, 100), (1, 2, 3, 79.00, 100),
(2, 3, 4, 74.00, 100), (2, 3, 5, 69.00, 100),
(2, 12, 4, 86.00, 100), (2, 12, 5, 90.00, 100),
(3, 4, 6, 72.00, 100), (3, 4, 7, 81.00, 100),
(3, 5, 6, 64.00, 100), (3, 5, 7, 58.00, 100),
(4, 6, 9, 77.00, 100), (4, 6, 10, 84.00, 100),
(4, 7, 9, 55.00, 100), (4, 7, 10, 61.00, 100),
(5, 8, 11, 89.00, 100), (5, 8, 12, 76.00, 100),
(5, 9, 11, 68.00, 100), (5, 9, 12, 71.00, 100),
(6, 10, 13, 83.00, 100), (6, 10, 14, 79.00, 100),
(6, 11, 13, 92.00, 100), (6, 11, 14, 87.00, 100);

INSERT INTO notices (title, message, posted_by) VALUES
('Welcome to the new academic year', 'All students are requested to complete their fee payment before the end of the month.', 1),
('Mid-term examination schedule', 'The mid-term examination schedule has been published. Please check the examination section for details.', 1),
('Library timings update', 'The library will remain open from 8:00 AM to 8:00 PM on weekdays starting next Monday.', 1);
