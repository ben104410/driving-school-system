-- =============================================================
-- ONLINE DRIVING SCHOOL MANAGEMENT SYSTEM
-- Case Study: AA Driving School - Mombasa Branch
-- Developed for: Cynthia Jeruto (Kenya Methodist University)
-- Database: driving_school_db
-- =============================================================

CREATE DATABASE IF NOT EXISTS `driving_school_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `driving_school_db`;

-- -------------------------------------------------------------
-- Table structure for `users`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `driving_test_results`;
DROP TABLE IF EXISTS `payments`;
DROP TABLE IF EXISTS `lesson_schedules`;
DROP TABLE IF EXISTS `vehicles`;
DROP TABLE IF EXISTS `students`;
DROP TABLE IF EXISTS `instructors`;
DROP TABLE IF EXISTS `users`;

CREATE TABLE `users` (
  `user_id` INT(11) NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `full_name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(100) DEFAULT NULL,
  `phone` VARCHAR(20) DEFAULT NULL,
  `role` ENUM('Administrator', 'Receptionist', 'Instructor', 'Student') NOT NULL DEFAULT 'Receptionist',
  `status` ENUM('Active', 'Inactive') NOT NULL DEFAULT 'Active',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------------
-- Table structure for `instructors`
-- -------------------------------------------------------------
CREATE TABLE `instructors` (
  `instructor_id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) DEFAULT NULL,
  `instructor_no` VARCHAR(20) NOT NULL UNIQUE,
  `full_name` VARCHAR(100) NOT NULL,
  `gender` ENUM('Male', 'Female', 'Other') NOT NULL,
  `phone` VARCHAR(20) NOT NULL,
  `email` VARCHAR(100) NOT NULL,
  `license_no` VARCHAR(50) NOT NULL,
  `license_expiry` DATE DEFAULT NULL,
  `specialization` VARCHAR(100) DEFAULT 'Manual & Automatic Light Vehicles',
  `address` VARCHAR(255) DEFAULT 'Mombasa, Kenya',
  `status` ENUM('Active', 'On Leave', 'Inactive') NOT NULL DEFAULT 'Active',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`instructor_id`),
  KEY `fk_instructor_user` (`user_id`),
  CONSTRAINT `fk_instructor_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------------
-- Table structure for `students`
-- -------------------------------------------------------------
CREATE TABLE `students` (
  `student_id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) DEFAULT NULL,
  `student_reg_no` VARCHAR(30) NOT NULL UNIQUE,
  `full_name` VARCHAR(100) NOT NULL,
  `gender` ENUM('Male', 'Female', 'Other') NOT NULL,
  `date_of_birth` DATE DEFAULT NULL,
  `phone` VARCHAR(20) NOT NULL,
  `email` VARCHAR(100) DEFAULT NULL,
  `national_id` VARCHAR(30) DEFAULT NULL,
  `address` VARCHAR(255) DEFAULT 'Mombasa',
  `course_category` VARCHAR(100) NOT NULL DEFAULT 'Category B (Light Vehicle - Manual)',
  `course_fee` DECIMAL(10,2) NOT NULL DEFAULT 15000.00,
  `registration_date` DATE NOT NULL,
  `status` ENUM('Enrolled', 'In Training', 'Test Ready', 'Completed', 'Dropped') NOT NULL DEFAULT 'Enrolled',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`student_id`),
  KEY `fk_student_user` (`user_id`),
  CONSTRAINT `fk_student_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------------
-- Table structure for `vehicles`
-- -------------------------------------------------------------
CREATE TABLE `vehicles` (
  `vehicle_id` INT(11) NOT NULL AUTO_INCREMENT,
  `reg_no` VARCHAR(20) NOT NULL UNIQUE,
  `model` VARCHAR(100) NOT NULL,
  `transmission` ENUM('Manual', 'Automatic') NOT NULL DEFAULT 'Manual',
  `category` VARCHAR(50) NOT NULL DEFAULT 'Class B (Light Vehicle)',
  `status` ENUM('Available', 'In Use', 'Maintenance') NOT NULL DEFAULT 'Available',
  `photo` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`vehicle_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------------
-- Table structure for `lesson_schedules`
-- -------------------------------------------------------------
CREATE TABLE `lesson_schedules` (
  `schedule_id` INT(11) NOT NULL AUTO_INCREMENT,
  `student_id` INT(11) NOT NULL,
  `instructor_id` INT(11) NOT NULL,
  `vehicle_id` INT(11) DEFAULT NULL,
  `lesson_topic` VARCHAR(150) NOT NULL DEFAULT 'Basic Controls & Steering',
  `lesson_date` DATE NOT NULL,
  `lesson_time` TIME NOT NULL,
  `duration_minutes` INT(11) NOT NULL DEFAULT 60,
  `status` ENUM('Scheduled', 'In Progress', 'Completed', 'Cancelled', 'No Show') NOT NULL DEFAULT 'Scheduled',
  `instructor_notes` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`schedule_id`),
  KEY `fk_schedule_student` (`student_id`),
  KEY `fk_schedule_instructor` (`instructor_id`),
  KEY `fk_schedule_vehicle` (`vehicle_id`),
  CONSTRAINT `fk_schedule_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_schedule_instructor` FOREIGN KEY (`instructor_id`) REFERENCES `instructors` (`instructor_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_schedule_vehicle` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`vehicle_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------------
-- Table structure for `payments`
-- -------------------------------------------------------------
CREATE TABLE `payments` (
  `payment_id` INT(11) NOT NULL AUTO_INCREMENT,
  `receipt_no` VARCHAR(30) NOT NULL UNIQUE,
  `student_id` INT(11) NOT NULL,
  `amount` DECIMAL(10,2) NOT NULL,
  `payment_date` DATE NOT NULL,
  `payment_method` ENUM('M-Pesa', 'Cash', 'Bank Transfer') NOT NULL DEFAULT 'M-Pesa',
  `reference_no` VARCHAR(50) DEFAULT NULL,
  `notes` VARCHAR(255) DEFAULT 'Tuition fee installment',
  `recorded_by` INT(11) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`payment_id`),
  KEY `fk_payment_student` (`student_id`),
  KEY `fk_payment_recorder` (`recorded_by`),
  CONSTRAINT `fk_payment_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_payment_recorder` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------------
-- Table structure for `driving_test_results`
-- -------------------------------------------------------------
CREATE TABLE `driving_test_results` (
  `result_id` INT(11) NOT NULL AUTO_INCREMENT,
  `student_id` INT(11) NOT NULL,
  `test_date` DATE NOT NULL,
  `test_type` ENUM('Internal Theory Test', 'Internal Practical Mock Test', 'Final NTSA/Police Driving Test') NOT NULL DEFAULT 'Internal Practical Mock Test',
  `score` INT(11) NOT NULL,
  `status` ENUM('Pass', 'Fail', 'Pending') NOT NULL DEFAULT 'Pass',
  `examiner_name` VARCHAR(100) NOT NULL,
  `remarks` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`result_id`),
  KEY `fk_test_student` (`student_id`),
  CONSTRAINT `fk_test_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------------
-- SAMPLE SEED DATA (AA Driving School, Mombasa)
-- Password for all default accounts is: password123
-- (Hash: $2y$10$4yT00m7WkFp2z7yV7qT3ce3I0kL3w0.1dFk8fKq7QfJ0eQGkU2Riy or plain fallback supported)
-- -------------------------------------------------------------

INSERT INTO `users` (`user_id`, `username`, `password`, `full_name`, `email`, `phone`, `role`, `status`) VALUES
(1, 'admin', '$2y$10$oY7IeJp8w6r1q1iS1kL5pOSXG0kH.5S1f4oT0eFk5eJ9jG2h1iI3q', 'Administrator (Cynthia Jeruto)', 'admin@aadrivingschool.co.ke', '+254712345678', 'Administrator', 'Active'),
(2, 'receptionist', '$2y$10$oY7IeJp8w6r1q1iS1kL5pOSXG0kH.5S1f4oT0eFk5eJ9jG2h1iI3q', 'Fatuma Ali', 'reception@aadrivingschool.co.ke', '+254722334455', 'Receptionist', 'Active'),
(3, 'instructor1', '$2y$10$oY7IeJp8w6r1q1iS1kL5pOSXG0kH.5S1f4oT0eFk5eJ9jG2h1iI3q', 'David Ochieng', 'david.ochieng@aadrivingschool.co.ke', '+254733445566', 'Instructor', 'Active'),
(4, 'instructor2', '$2y$10$oY7IeJp8w6r1q1iS1kL5pOSXG0kH.5S1f4oT0eFk5eJ9jG2h1iI3q', 'Hassan Mwinyi', 'hassan.mwinyi@aadrivingschool.co.ke', '+254744556677', 'Instructor', 'Active'),
(5, 'student1', '$2y$10$oY7IeJp8w6r1q1iS1kL5pOSXG0kH.5S1f4oT0eFk5eJ9jG2h1iI3q', 'Mary Wanjiku', 'mary.wanjiku@gmail.com', '+254755667788', 'Student', 'Active');

INSERT INTO `instructors` (`instructor_id`, `user_id`, `instructor_no`, `full_name`, `gender`, `phone`, `email`, `license_no`, `license_expiry`, `specialization`, `address`, `status`) VALUES
(1, 3, 'AA-INS-001', 'David Ochieng', 'Male', '+254733445566', 'david.ochieng@aadrivingschool.co.ke', 'NTSA/DI/2019/482', '2027-12-31', 'Class B (Light Manual & Automatic)', 'Nyali, Mombasa', 'Active'),
(2, 4, 'AA-INS-002', 'Hassan Mwinyi', 'Male', '+254744556677', 'hassan.mwinyi@aadrivingschool.co.ke', 'NTSA/DI/2021/119', '2028-06-30', 'Class B (Light Vehicle) & Class A (Motorcycle)', 'Tudor, Mombasa', 'Active'),
(3, NULL, 'AA-INS-003', 'Amina Bakari', 'Female', '+254799887766', 'amina.bakari@aadrivingschool.co.ke', 'NTSA/DI/2022/804', '2028-01-15', 'Class B Defensive Driving Specialist', 'Bamburi, Mombasa', 'Active');

INSERT INTO `vehicles` (`vehicle_id`, `reg_no`, `model`, `transmission`, `category`, `status`) VALUES
(1, 'KDE 312M', 'Toyota Vitz (Dual Pedal Control)', 'Manual', 'Class B (Light Vehicle)', 'Available'),
(2, 'KDF 841P', 'Toyota Belta (Dual Pedal Control)', 'Automatic', 'Class B (Light Vehicle)', 'Available'),
(3, 'KDG 109K', 'Isuzu D-Max Pick-up', 'Manual', 'Class C1 (Light Truck)', 'Available'),
(4, 'KMDF 234L', 'TVS Star HLX 125 Motorcycle', 'Manual', 'Class A2 (Motorcycle)', 'Available');

INSERT INTO `students` (`student_id`, `user_id`, `student_reg_no`, `full_name`, `gender`, `date_of_birth`, `phone`, `email`, `national_id`, `address`, `course_category`, `course_fee`, `registration_date`, `status`) VALUES
(1, 5, 'AA/MBA/2026/001', 'Mary Wanjiku', 'Female', '2002-05-14', '+254755667788', 'mary.wanjiku@gmail.com', '38491024', 'Kizingo, Mombasa', 'Category B (Light Vehicle - Manual)', 15000.00, '2026-10-01', 'In Training'),
(2, NULL, 'AA/MBA/2026/002', 'Brian Kipchumba', 'Male', '1999-11-20', '+254711223344', 'brian.k@yahoo.com', '35912840', 'Likoni, Mombasa', 'Category B (Light Vehicle - Automatic)', 16000.00, '2026-10-05', 'In Training'),
(3, NULL, 'AA/MBA/2026/003', 'Salim Omar', 'Male', '2001-03-08', '+254722998811', 'salim.omar@gmail.com', '37812944', 'Old Town, Mombasa', 'Category A (Motorcycle)', 9500.00, '2026-10-12', 'Test Ready'),
(4, NULL, 'AA/MBA/2026/004', 'Grace Moraa', 'Female', '1998-08-25', '+254733123456', 'grace.moraa@outlook.com', '34198273', 'Changamwe, Mombasa', 'Category B (Light Vehicle - Manual)', 15000.00, '2026-09-15', 'Completed'),
(5, NULL, 'AA/MBA/2026/005', 'Ahmed Juma', 'Male', '2003-01-30', '+254788112233', 'ahmed.juma@gmail.com', '39120485', 'Mtwapa, Mombasa', 'Category B (Light Vehicle - Manual)', 15000.00, '2026-11-02', 'Enrolled');

INSERT INTO `lesson_schedules` (`schedule_id`, `student_id`, `instructor_id`, `vehicle_id`, `lesson_topic`, `lesson_date`, `lesson_time`, `duration_minutes`, `status`, `instructor_notes`) VALUES
(1, 1, 1, 1, 'Clutch Control & Hill Starts', '2026-11-17', '09:00:00', 60, 'Scheduled', 'Focus on smooth biting point control along Nyali slopes.'),
(2, 2, 2, 2, 'Three-point Turn & Parallel Parking', '2026-11-17', '10:30:00', 60, 'Scheduled', 'Practicing reverse bay parking at Mombasa showground area.'),
(3, 3, 2, 4, 'Emergency Braking & Slalom Maneuver', '2026-11-18', '08:30:00', 60, 'Scheduled', 'Pre-test motorcycle maneuver assessment.'),
(4, 1, 1, 1, 'Basic Steering & Gear Selection', '2026-11-12', '09:00:00', 60, 'Completed', 'Student demonstrated good mirror checks and gear shifting.'),
(5, 4, 3, 1, 'Pre-Test Driving Assessment Mock', '2026-10-28', '11:00:00', 60, 'Completed', 'Passed mock exam with high confidence. Ready for NTSA test.');

INSERT INTO `payments` (`payment_id`, `receipt_no`, `student_id`, `amount`, `payment_date`, `payment_method`, `reference_no`, `notes`, `recorded_by`) VALUES
(1, 'RCT-2026-001', 1, 10000.00, '2026-10-01', 'M-Pesa', 'QA189KL93Z', 'First installment for Class B Manual', 2),
(2, 'RCT-2026-002', 1, 5000.00, '2026-11-05', 'M-Pesa', 'QB421MN88Y', 'Final balance clearance', 2),
(3, 'RCT-2026-003', 2, 10000.00, '2026-10-05', 'Bank Transfer', 'EKB-8491024', 'NCBA Bank Mombasa Deposit', 1),
(4, 'RCT-2026-004', 3, 9500.00, '2026-10-12', 'M-Pesa', 'QC992OP11X', 'Full payment for Motorcycle training', 2),
(5, 'RCT-2026-005', 4, 15000.00, '2026-09-15', 'Cash', 'CSH-09-15-44', 'Full tuition cash payment at Mombasa desk', 2),
(6, 'RCT-2026-006', 5, 8000.00, '2026-11-02', 'M-Pesa', 'QD512RS77W', 'Registration & initial deposit', 2);

INSERT INTO `driving_test_results` (`result_id`, `student_id`, `test_date`, `test_type`, `score`, `status`, `examiner_name`, `remarks`) VALUES
(1, 4, '2026-10-30', 'Final NTSA/Police Driving Test', 88, 'Pass', 'Senior Insp. J. Mwangi (NTSA Mombasa)', 'Excellently executed three-point turn, roundabouts, and hill-start.'),
(2, 3, '2026-11-10', 'Internal Theory Test', 92, 'Pass', 'David Ochieng (Senior Instructor)', 'Passed Highway Code, road signs, and model town board rules.'),
(3, 1, '2026-11-14', 'Internal Practical Mock Test', 74, 'Pass', 'Amina Bakari', 'Good vehicle control; minor reminder on mirror check sequence.');
