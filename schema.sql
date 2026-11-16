-- CampusFlow School Management System — Full Schema + Seed Data
-- Import into MySQL/MariaDB as database: school_management

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";
SET NAMES utf8mb4;

CREATE DATABASE IF NOT EXISTS `school_management` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `school_management`;

DROP TABLE IF EXISTS `assignment_submissions`;
DROP TABLE IF EXISTS `assignments`;
DROP TABLE IF EXISTS `marks`;
DROP TABLE IF EXISTS `exams`;
DROP TABLE IF EXISTS `timetable`;
DROP TABLE IF EXISTS `leave_requests`;
DROP TABLE IF EXISTS `notifications`;
DROP TABLE IF EXISTS `fee_payments`;
DROP TABLE IF EXISTS `attendance`;
DROP TABLE IF EXISTS `notices`;
DROP TABLE IF EXISTS `subjects`;
DROP TABLE IF EXISTS `students`;
DROP TABLE IF EXISTS `teachers`;
DROP TABLE IF EXISTS `classes`;
DROP TABLE IF EXISTS `settings`;
DROP TABLE IF EXISTS `users`;

CREATE TABLE `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('admin','teacher','student') NOT NULL DEFAULT 'student',
  `status` ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `settings` (
  `setting_key` VARCHAR(100) PRIMARY KEY,
  `setting_value` TEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `classes` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `class_name` VARCHAR(50) NOT NULL,
  `section` VARCHAR(20) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_class_section` (`class_name`, `section`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `teachers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT DEFAULT NULL,
  `first_name` VARCHAR(50) NOT NULL,
  `last_name` VARCHAR(50) NOT NULL,
  `phone` VARCHAR(20) NOT NULL,
  `gender` ENUM('Male','Female','Other') NOT NULL,
  `address` TEXT NOT NULL,
  `joining_date` DATE NOT NULL,
  `status` ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `students` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT DEFAULT NULL,
  `admission_number` VARCHAR(50) NOT NULL UNIQUE,
  `first_name` VARCHAR(50) NOT NULL,
  `last_name` VARCHAR(50) NOT NULL,
  `gender` ENUM('Male','Female','Other') NOT NULL,
  `date_of_birth` DATE NOT NULL,
  `class_id` INT DEFAULT NULL,
  `parent_name` VARCHAR(100) NOT NULL,
  `parent_phone` VARCHAR(20) NOT NULL,
  `address` TEXT NOT NULL,
  `photo` VARCHAR(255) DEFAULT 'default.png',
  `status` ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`class_id`) REFERENCES `classes`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `subjects` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `subject_name` VARCHAR(100) NOT NULL,
  `subject_code` VARCHAR(30) NOT NULL,
  `class_id` INT NOT NULL,
  `teacher_id` INT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_subject_per_class` (`subject_code`, `class_id`),
  FOREIGN KEY (`class_id`) REFERENCES `classes`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`teacher_id`) REFERENCES `teachers`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `attendance` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `student_id` INT NOT NULL,
  `class_id` INT NOT NULL,
  `attendance_date` DATE NOT NULL,
  `status` ENUM('Present','Absent','Late','Leave') NOT NULL DEFAULT 'Present',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_student_date` (`student_id`, `attendance_date`),
  FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`class_id`) REFERENCES `classes`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `fee_payments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `student_id` INT NOT NULL,
  `receipt_no` VARCHAR(50) NOT NULL UNIQUE,
  `fee_title` VARCHAR(100) NOT NULL,
  `total_amount` DECIMAL(10,2) NOT NULL,
  `amount_paid` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `payment_status` ENUM('Paid','Partial','Unpaid') NOT NULL DEFAULT 'Unpaid',
  `payment_date` DATE NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `notices` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(150) NOT NULL,
  `description` TEXT NOT NULL,
  `notice_type` ENUM('Notice','Event','Holiday','Exam') NOT NULL DEFAULT 'Notice',
  `event_date` DATE NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `exams` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `exam_name` VARCHAR(100) NOT NULL,
  `class_id` INT NOT NULL,
  `exam_date` DATE NOT NULL,
  `total_marks` DECIMAL(8,2) NOT NULL DEFAULT 100.00,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`class_id`) REFERENCES `classes`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `marks` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `exam_id` INT NOT NULL,
  `student_id` INT NOT NULL,
  `subject_id` INT NOT NULL,
  `marks_obtained` DECIMAL(8,2) NOT NULL DEFAULT 0.00,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_mark` (`exam_id`, `student_id`, `subject_id`),
  FOREIGN KEY (`exam_id`) REFERENCES `exams`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`subject_id`) REFERENCES `subjects`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `timetable` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `class_id` INT NOT NULL,
  `day_of_week` ENUM('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday') NOT NULL,
  `period_no` INT NOT NULL,
  `subject_id` INT NOT NULL,
  `teacher_id` INT DEFAULT NULL,
  `start_time` TIME NOT NULL,
  `end_time` TIME NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_slot` (`class_id`, `day_of_week`, `period_no`),
  FOREIGN KEY (`class_id`) REFERENCES `classes`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`subject_id`) REFERENCES `subjects`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`teacher_id`) REFERENCES `teachers`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `assignments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `class_id` INT NOT NULL,
  `subject_id` INT NOT NULL,
  `teacher_id` INT NOT NULL,
  `title` VARCHAR(150) NOT NULL,
  `description` TEXT NOT NULL,
  `due_date` DATE NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`class_id`) REFERENCES `classes`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`subject_id`) REFERENCES `subjects`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`teacher_id`) REFERENCES `teachers`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `assignment_submissions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `assignment_id` INT NOT NULL,
  `student_id` INT NOT NULL,
  `status` ENUM('Pending','Submitted') NOT NULL DEFAULT 'Pending',
  `submitted_at` DATETIME DEFAULT NULL,
  `remarks` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_submission` (`assignment_id`, `student_id`),
  FOREIGN KEY (`assignment_id`) REFERENCES `assignments`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `leave_requests` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `teacher_id` INT NOT NULL,
  `from_date` DATE NOT NULL,
  `to_date` DATE NOT NULL,
  `reason` TEXT NOT NULL,
  `status` ENUM('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
  `admin_note` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`teacher_id`) REFERENCES `teachers`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `notifications` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `title` VARCHAR(150) NOT NULL,
  `message` TEXT NOT NULL,
  `link` VARCHAR(255) DEFAULT NULL,
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Password for all demo accounts: password
-- Hash: $2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi

INSERT INTO `users` (`id`, `username`, `email`, `password`, `role`, `status`) VALUES
(1, 'admin', 'admin@school.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'Active'),
(2, 'wardah', 'wardah@school.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'teacher', 'Active'),
(3, 'rameesa', 'rameesa@school.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'teacher', 'Active'),
(4, 'abdullah', 'abdullah@school.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'Active'),
(5, 'sara', 'sara@school.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'Active'),
(6, 'ahsan', 'ahsan@school.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'Active');

INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('school_name', 'CampusFlow Academy'),
('academic_year', '2025-2026'),
('school_address', 'Karachi, Pakistan'),
('school_phone', '021-1234567');

INSERT INTO `classes` (`id`, `class_name`, `section`) VALUES
(1, 'Grade 1', 'Section A'),
(2, 'Grade 2', 'Section A'),
(3, 'Grade 3', 'Section A'),
(4, 'Grade 4', 'Section A'),
(5, 'Grade 1', 'Section B');

INSERT INTO `teachers` (`id`, `user_id`, `first_name`, `last_name`, `phone`, `gender`, `address`, `joining_date`, `status`) VALUES
(1, 2, 'Wardah', 'Azhar', '03322639237', 'Female', 'Naya Nazimabad, Karachi', '2025-01-08', 'Active'),
(2, 3, 'Rameesa', 'Aftab', '03645821781', 'Female', 'North Karachi, Pakistan', '2017-01-10', 'Active');

INSERT INTO `students` (`id`, `user_id`, `admission_number`, `first_name`, `last_name`, `gender`, `date_of_birth`, `class_id`, `parent_name`, `parent_phone`, `address`, `photo`, `status`) VALUES
(1, 4, 'ADM-2026-002', 'Abdullah', 'Usman', 'Male', '2020-06-25', 1, 'Usman Malik', '03467517684', 'Shahrah-e-Faisal, Karachi', 'default.png', 'Active'),
(2, 5, 'ADM-2026-003', 'Sara', 'Siddiqui', 'Female', '2020-05-01', 1, 'Zain Siddiqui', '03217764845', 'Nagan Chowrangi, Karachi', 'default.png', 'Active'),
(3, 6, 'ADM-2026-004', 'Ahsan', 'Atif', 'Male', '2019-06-20', 2, 'Atif Hussain Khan', '03467837488', 'Gulshan-e-Iqbal, Karachi', 'default.png', 'Active'),
(4, NULL, 'ADM-2026-005', 'Areeba', 'Farhan', 'Female', '2019-01-23', 2, 'Farhan Iqbal', '03648276638', 'DHA Phase 6, Karachi', 'default.png', 'Active'),
(5, NULL, 'ADM-2026-006', 'Musa', 'Waqas', 'Male', '2018-10-31', 3, 'Waqas Ahmed', '03652275438', 'Bahria Town, Karachi', 'default.png', 'Active'),
(6, NULL, 'ADM-2026-007', 'Ali', 'Kamran', 'Male', '2018-03-07', 3, 'Kamran Mehmood', '05747274936', 'Saddar, Karachi', 'default.png', 'Active');

INSERT INTO `subjects` (`id`, `subject_name`, `subject_code`, `class_id`, `teacher_id`) VALUES
(1, 'English', 'ENG-101', 1, 1),
(2, 'Mathematics', 'MATH-101', 1, 2),
(3, 'Science', 'SCI-101', 1, 1),
(4, 'English', 'ENG-201', 2, 1),
(5, 'Mathematics', 'MATH-201', 2, 2);

INSERT INTO `attendance` (`student_id`, `class_id`, `attendance_date`, `status`) VALUES
(1, 1, CURDATE(), 'Present'),
(2, 1, CURDATE(), 'Present'),
(3, 2, CURDATE(), 'Absent');

INSERT INTO `fee_payments` (`student_id`, `receipt_no`, `fee_title`, `total_amount`, `amount_paid`, `payment_status`, `payment_date`) VALUES
(1, 'RCP-1001', 'Tuition Fee - November', 5000.00, 5000.00, 'Paid', CURDATE()),
(2, 'RCP-1002', 'Tuition Fee - November', 5000.00, 2500.00, 'Partial', CURDATE()),
(3, 'RCP-1003', 'Tuition Fee - November', 5500.00, 0.00, 'Unpaid', CURDATE());

INSERT INTO `notices` (`title`, `description`, `notice_type`, `event_date`) VALUES
('Mid-Term Examinations Schedule', 'Mid-term assessment sheets and room allocations have been finalized for all sections.', 'Exam', DATE_ADD(CURDATE(), INTERVAL 5 DAY)),
('Annual Parent-Teacher Meeting', 'All faculty members and parents are invited to review student academic progress.', 'Event', DATE_ADD(CURDATE(), INTERVAL 12 DAY)),
('Winter Holiday Notice', 'School will remain closed for winter holidays. Classes resume after the break.', 'Holiday', DATE_ADD(CURDATE(), INTERVAL 20 DAY));

INSERT INTO `exams` (`id`, `exam_name`, `class_id`, `exam_date`, `total_marks`) VALUES
(1, 'Mid-Term Exam', 1, DATE_ADD(CURDATE(), INTERVAL 5 DAY), 100.00);

INSERT INTO `marks` (`exam_id`, `student_id`, `subject_id`, `marks_obtained`) VALUES
(1, 1, 1, 85.00),
(1, 1, 2, 78.00),
(1, 2, 1, 90.00),
(1, 2, 2, 88.00);

INSERT INTO `timetable` (`class_id`, `day_of_week`, `period_no`, `subject_id`, `teacher_id`, `start_time`, `end_time`) VALUES
(1, 'Monday', 1, 1, 1, '08:00:00', '08:45:00'),
(1, 'Monday', 2, 2, 2, '08:45:00', '09:30:00'),
(1, 'Tuesday', 1, 3, 1, '08:00:00', '08:45:00'),
(1, 'Wednesday', 1, 1, 1, '08:00:00', '08:45:00'),
(2, 'Monday', 1, 4, 1, '08:00:00', '08:45:00');

INSERT INTO `assignments` (`id`, `class_id`, `subject_id`, `teacher_id`, `title`, `description`, `due_date`) VALUES
(1, 1, 1, 1, 'Reading Comprehension Worksheet', 'Complete the worksheet on chapter 3 and submit before the due date.', DATE_ADD(CURDATE(), INTERVAL 7 DAY)),
(2, 1, 2, 2, 'Multiplication Practice', 'Solve exercises 1 to 20 from the workbook.', DATE_ADD(CURDATE(), INTERVAL 5 DAY));

INSERT INTO `assignment_submissions` (`assignment_id`, `student_id`, `status`, `submitted_at`) VALUES
(1, 1, 'Submitted', NOW()),
(1, 2, 'Pending', NULL);

INSERT INTO `leave_requests` (`teacher_id`, `from_date`, `to_date`, `reason`, `status`) VALUES
(1, DATE_ADD(CURDATE(), INTERVAL 3 DAY), DATE_ADD(CURDATE(), INTERVAL 4 DAY), 'Family obligation', 'Pending');

INSERT INTO `notifications` (`user_id`, `title`, `message`, `link`, `is_read`) VALUES
(1, 'Pending Leave Request', 'Teacher Wardah Azhar requested leave. Please review.', 'admin/leaves.php', 0),
(2, 'Welcome to CampusFlow', 'Your teacher portal is ready. Check your timetable and classes.', 'teacher/dashboard.php', 0),
(4, 'Fee Receipt Available', 'Your November tuition fee payment was recorded.', 'student/fees.php', 0),
(4, 'New Assignment', 'Reading Comprehension Worksheet has been assigned.', 'student/assignments.php', 0);

COMMIT;
