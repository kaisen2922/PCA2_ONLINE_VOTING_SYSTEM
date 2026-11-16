-- ==============================================================
-- Online Voting System - Database Schema & Initial Seed Data
-- Localized for: ApexVote – College Electoral Management System
-- Campus: Kolkata, West Bengal, India | Session: 2026–2027
-- Database Engine: MySQL 8+ / MariaDB (InnoDB, UTF-8 MB4)
-- ==============================================================

CREATE DATABASE IF NOT EXISTS `online_voting_system` 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE `online_voting_system`;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `audit_logs`;
DROP TABLE IF EXISTS `ballots`;
DROP TABLE IF EXISTS `voter_eligibility`;
DROP TABLE IF EXISTS `candidates`;
DROP TABLE IF EXISTS `elections`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `settings`;
SET FOREIGN_KEY_CHECKS = 1;

-- -------------------------------------------------------------
-- 1. System Settings Table
-- -------------------------------------------------------------
CREATE TABLE `settings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `setting_key` VARCHAR(100) NOT NULL UNIQUE,
    `setting_value` TEXT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- 2. Users Table (Admins and Student Voters)
-- -------------------------------------------------------------
CREATE TABLE `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `full_name` VARCHAR(150) NOT NULL,
    `email` VARCHAR(150) NOT NULL UNIQUE,
    `voter_id_number` VARCHAR(50) NOT NULL UNIQUE,
    `college_roll_no` VARCHAR(60) NULL,
    `university_reg_no` VARCHAR(60) NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `role` ENUM('voter', 'admin') NOT NULL DEFAULT 'voter',
    `department` VARCHAR(100) NOT NULL DEFAULT 'Computer Applications',
    `course` VARCHAR(100) NULL DEFAULT 'BCA',
    `semester` INT NULL DEFAULT 1,
    `academic_session` VARCHAR(30) NULL DEFAULT '2026–2027',
    `phone` VARCHAR(30) NULL,
    `status` ENUM('active', 'pending', 'suspended') NOT NULL DEFAULT 'active',
    `avatar_url` VARCHAR(255) NULL DEFAULT 'default-avatar.svg',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_users_role` (`role`),
    INDEX `idx_users_status` (`status`),
    INDEX `idx_users_roll` (`college_roll_no`),
    INDEX `idx_users_reg` (`university_reg_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- 3. Elections Table
-- -------------------------------------------------------------
CREATE TABLE `elections` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT NOT NULL,
    `category` VARCHAR(100) NOT NULL DEFAULT "College Students' Council",
    `start_time` DATETIME NOT NULL,
    `end_time` DATETIME NOT NULL,
    `status` ENUM('draft', 'active', 'completed', 'cancelled') NOT NULL DEFAULT 'draft',
    `results_published` TINYINT(1) NOT NULL DEFAULT 0,
    `banner_image` VARCHAR(255) NULL,
    `eligibility_type` ENUM('all', 'department') NOT NULL DEFAULT 'all',
    `target_department` VARCHAR(100) NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_elections_status` (`status`),
    INDEX `idx_elections_timing` (`start_time`, `end_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- 4. Candidates Table
-- -------------------------------------------------------------
CREATE TABLE `candidates` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `election_id` INT NOT NULL,
    `full_name` VARCHAR(150) NOT NULL,
    `position` VARCHAR(150) NOT NULL,
    `party_or_slate` VARCHAR(100) NOT NULL DEFAULT 'Independent',
    `manifesto` TEXT NOT NULL,
    `photo_url` VARCHAR(255) NULL,
    `ballot_number` INT NOT NULL DEFAULT 1,
    `vote_count` INT NOT NULL DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_candidates_election` (`election_id`),
    CONSTRAINT `fk_candidates_election` FOREIGN KEY (`election_id`) 
        REFERENCES `elections` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- 5. Voter Eligibility Table (Tracks Participation, Not Choice)
-- -------------------------------------------------------------
CREATE TABLE `voter_eligibility` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `election_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `has_voted` TINYINT(1) NOT NULL DEFAULT 0,
    `voted_at` DATETIME NULL,
    `receipt_token` VARCHAR(64) NULL,
    `eligibility_status` ENUM('eligible', 'revoked') NOT NULL DEFAULT 'eligible',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_election_voter` (`election_id`, `user_id`),
    INDEX `idx_eligibility_has_voted` (`has_voted`),
    CONSTRAINT `fk_eligibility_election` FOREIGN KEY (`election_id`) 
        REFERENCES `elections` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_eligibility_user` FOREIGN KEY (`user_id`) 
        REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- 6. Ballots Table (Strict Ballot Secrecy: NO voter id stored)
-- -------------------------------------------------------------
CREATE TABLE `ballots` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `election_id` INT NOT NULL,
    `candidate_id` INT NOT NULL,
    `vote_hash` VARCHAR(64) NOT NULL,
    `cast_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_ballots_election` (`election_id`),
    INDEX `idx_ballots_candidate` (`candidate_id`),
    CONSTRAINT `fk_ballots_election` FOREIGN KEY (`election_id`) 
        REFERENCES `elections` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ballots_candidate` FOREIGN KEY (`candidate_id`) 
        REFERENCES `candidates` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- 7. Audit Logs Table
-- -------------------------------------------------------------
CREATE TABLE `audit_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NULL,
    `action` VARCHAR(100) NOT NULL,
    `ip_address` VARCHAR(45) NOT NULL,
    `user_agent` TEXT NULL,
    `details` TEXT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_audit_user` (`user_id`),
    INDEX `idx_audit_action` (`action`),
    INDEX `idx_audit_created` (`created_at`),
    CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) 
        REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==============================================================
-- INITIAL SAMPLE DATA SEEDING (KOLKATA, WEST BENGAL, INDIA)
-- ==============================================================

-- 1. System Settings
INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('site_name', 'ApexVote – College Electoral Management System'),
('institution_name', 'ApexVote College Demonstration Campus'),
('institution_location', 'Kolkata, West Bengal, India'),
('academic_year', '2026–2027'),
('currency_symbol', '₹'),
('allow_registration', '1'),
('contact_email', 'election.committee@apexcollege.edu.in'),
('contact_phone', '+91 98300 12345'),
('voting_mode', 'standard'),
('demo_mode', '1'),
('timezone', 'Asia/Kolkata');

-- 2. Users (Admin: Admin@123, Voters: Voter@123)
INSERT INTO `users` (`id`, `full_name`, `email`, `voter_id_number`, `college_roll_no`, `university_reg_no`, `password_hash`, `role`, `department`, `course`, `semester`, `academic_session`, `phone`, `status`) VALUES
(1, 'Dr. Subhash Mukherjee', 'admin@college.edu', 'ADM-KOL-2026-001', 'FAC-CS-001', 'WB-UNIV-FAC-2015', '$2y$10$qU/wX.kplQEtZe9QrPTcTuo5M6X8MMU.0aVYdIl60bh9nqAKxfSqu', 'admin', 'College Election Committee', 'Faculty Administration', 0, '2026–2027', '+91 98300 12345', 'active'),
(2, 'Sourav Ganguly', 'voter1@college.edu', 'BCA/2026/041', 'BCA/2026/041', '12623010041', '$2y$10$d6UncSifP57uW7yyxemqrO1n9/BfyNiVzGRwFVLc1bE.uXh4Nc696', 'voter', 'Computer Applications', 'BCA', 6, '2026–2027', '+91 98301 23456', 'active'),
(3, 'Rituparna Sen', 'voter2@college.edu', 'CSE/2026/019', 'CSE/2026/019', '12623010019', '$2y$10$d6UncSifP57uW7yyxemqrO1n9/BfyNiVzGRwFVLc1bE.uXh4Nc696', 'voter', 'Computer Science & Engineering', 'B.Tech CSE', 4, '2026–2027', '+91 98302 34567', 'active'),
(4, 'Aritra Bhattacharya', 'voter3@college.edu', 'BBA/2026/012', 'BBA/2026/012', '12623010012', '$2y$10$d6UncSifP57uW7yyxemqrO1n9/BfyNiVzGRwFVLc1bE.uXh4Nc696', 'voter', 'Business Administration', 'BBA', 4, '2026–2027', '+91 98303 45678', 'active'),
(5, 'Koyel Roy', 'voter4@college.edu', 'COM/2026/088', 'COM/2026/088', '12623010088', '$2y$10$d6UncSifP57uW7yyxemqrO1n9/BfyNiVzGRwFVLc1bE.uXh4Nc696', 'voter', 'Commerce', 'B.Com (Hons.)', 2, '2026–2027', '+91 98304 56789', 'active'),
(6, 'Bikramjit Paul', 'voter5@college.edu', 'MCA/2026/007', 'MCA/2026/007', '12623010007', '$2y$10$d6UncSifP57uW7yyxemqrO1n9/BfyNiVzGRwFVLc1bE.uXh4Nc696', 'voter', 'Computer Applications', 'MCA', 2, '2026–2027', '+91 98305 67890', 'active'),
(7, 'Tanushree Sarkar', 'voter6@college.edu', 'IT/2026/024', 'IT/2026/024', '12623010024', '$2y$10$d6UncSifP57uW7yyxemqrO1n9/BfyNiVzGRwFVLc1bE.uXh4Nc696', 'voter', 'Information Technology', 'B.Tech IT', 6, '2026–2027', '+91 98306 78901', 'active');

-- 3. Elections
INSERT INTO `elections` (`id`, `title`, `description`, `category`, `start_time`, `end_time`, `status`, `results_published`, `eligibility_type`, `target_department`) VALUES
(1, "College Students' Council Election 2026–2027", "Annual democratic election to elect the executive student leadership of the College Students' Council for the 2026–2027 academic session. All registered undergraduate and postgraduate students across all campus departments are eligible to vote.", "College Students' Council", DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_ADD(NOW(), INTERVAL 5 DAY), 'active', 0, 'all', NULL),
(2, "BCA Department Representative Election 2026–2027", "Election for the Department Representative representing the Department of Computer Applications (BCA) on the Academic Council and faculty-student liaison committee.", 'Department Representative', DATE_SUB(NOW(), INTERVAL 2 DAY), DATE_ADD(NOW(), INTERVAL 4 DAY), 'active', 0, 'department', 'Computer Applications'),
(3, "Cultural Committee Election 2026–2027", "Annual election to appoint the Cultural Secretary and Event Coordinators responsible for organizing inter-college fests, youth festivals, and cultural activities.", 'Cultural Committee', DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_ADD(NOW(), INTERVAL 6 DAY), 'active', 0, 'all', NULL),
(4, "Sports Committee Election 2026–2027", "Democratic election to elect the Sports Secretary and Coordinators overseeing the annual college sports meet, inter-college football, cricket tournaments, and indoor games.", 'Sports Committee', DATE_SUB(NOW(), INTERVAL 3 DAY), DATE_ADD(NOW(), INTERVAL 3 DAY), 'active', 0, 'all', NULL),
(5, "College Alumni Association Election", "Completed election for the institutional alumni representatives coordinating graduate mentorship circles, scholarship endowments, and career placement linkages.", 'Alumni Association', DATE_SUB(NOW(), INTERVAL 10 DAY), DATE_SUB(NOW(), INTERVAL 1 DAY), 'completed', 1, 'all', NULL);

-- 4. Candidates (Fictional Indian Candidates with Realistic Kolkata College Manifestos)
INSERT INTO `candidates` (`id`, `election_id`, `full_name`, `position`, `party_or_slate`, `manifesto`, `photo_url`, `ballot_number`, `vote_count`) VALUES
(1, 1, 'Rahul Das', "Students' Council President", 'Progress & Development Panel', 'Dedicated to expanding 24/7 college library access with digital journals, setting up modern high-speed Wi-Fi across campus, organizing regular technical workshops, and establishing an active placement cell mentorship circle.', 'assets/images/candidate1.svg', 1, 0),
(2, 1, 'Priya Sharma', "Students' Council President", 'Student Welfare Panel', 'Committed to overhauling college canteen hygiene with subsidized nutritious meals, upgrading the girls\' common room facilities, creating a swift grievance redressal cell, and introducing free mental health counseling seminars.', 'assets/images/candidate2.svg', 2, 0),
(3, 1, 'Arjun Roy', "Students' Council President", 'Campus Reform Panel', 'Pledging hardware lab modernization with dedicated GPU systems for project work, transparent student council fund audits, inter-college coding hackathons, and corporate internship partnerships with Kolkata IT leaders.', 'assets/images/candidate3.svg', 3, 0),
(4, 2, 'Sneha Mukherjee', 'Department Representative', 'Cultural Development Panel', 'Advocating for extended computer lab timings during semester project weeks, hands-on cloud computing workshops, industry guest lectures from Sector V tech veterans, and peer study groups.', 'assets/images/candidate4.svg', 1, 0),
(5, 2, 'Aditya Banerjee', 'Class Representative', 'Academic Excellence Panel', 'Focusing on peer mentoring sessions, faculty-student bridge meetings, structured placement interview prep with mock coding tests, and digital access to syllabus study guides.', 'assets/images/candidate5.svg', 2, 0),
(6, 3, 'Ananya Chatterjee', 'Cultural Secretary', 'Student Voice Panel', 'Revitalizing the annual college cultural festival, organizing Rabindra Jayanti and inter-college drama competitions, procuring state-of-the-art music club equipment, and launching a creative arts college magazine.', 'assets/images/candidate6.svg', 1, 0),
(7, 4, 'Rohan Dutta', 'Sports Secretary', 'Campus Innovation Panel', 'Upgrading the college gymnasium, resurfacing badminton and basketball courts, organizing the annual football and cricket tournaments, and sponsoring student athletes for inter-university sports meets.', 'assets/images/candidate7.svg', 1, 0),
(8, 5, 'Ishita Ghosh', 'Alumni Representative', 'Green Campus Panel', 'Active mentor connecting Kolkata tech alumni to graduating students, financing merit-cum-means semester tuition stipends, arranging tech company internship referrals, and leading solar energy campus green drives.', 'assets/images/candidate8.svg', 1, 142),
(9, 5, 'Debashis Sen', 'Alumni Committee Member', 'Alumni Heritage Panel', 'Promoting international student exchange endowments, annual alumni reunions in Kolkata, entrepreneurship incubator angel funds, and campus infrastructure grants.', 'assets/images/candidate7.svg', 2, 98),
(10, 5, 'Soumya Chatterjee', 'Alumni Committee Member', 'Student First Alumni', 'Advocating for student placement emergency relief funds, subsidized laptop schemes for underprivileged students, and venture incubation demo days.', 'assets/images/candidate6.svg', 3, 64);

-- 5. Voter Eligibility
-- Elections 1, 3, 4: All student voters (users 2 through 7)
INSERT INTO `voter_eligibility` (`election_id`, `user_id`, `has_voted`, `voted_at`, `receipt_token`, `eligibility_status`) VALUES
(1, 2, 0, NULL, NULL, 'eligible'),
(1, 3, 0, NULL, NULL, 'eligible'),
(1, 4, 0, NULL, NULL, 'eligible'),
(1, 5, 0, NULL, NULL, 'eligible'),
(1, 6, 0, NULL, NULL, 'eligible'),
(1, 7, 0, NULL, NULL, 'eligible'),
(3, 2, 0, NULL, NULL, 'eligible'),
(3, 3, 0, NULL, NULL, 'eligible'),
(3, 4, 0, NULL, NULL, 'eligible'),
(3, 5, 0, NULL, NULL, 'eligible'),
(3, 6, 0, NULL, NULL, 'eligible'),
(3, 7, 0, NULL, NULL, 'eligible'),
(4, 2, 0, NULL, NULL, 'eligible'),
(4, 3, 0, NULL, NULL, 'eligible'),
(4, 4, 0, NULL, NULL, 'eligible'),
(4, 5, 0, NULL, NULL, 'eligible'),
(4, 6, 0, NULL, NULL, 'eligible'),
(4, 7, 0, NULL, NULL, 'eligible'),
-- Election 2: Department-specific (BCA/MCA Computer Applications students)
(2, 2, 0, NULL, NULL, 'eligible'),
(2, 6, 0, NULL, NULL, 'eligible'),
-- Election 5: Completed election with recorded votes and receipt tokens
(5, 2, 1, DATE_SUB(NOW(), INTERVAL 2 DAY), 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855', 'eligible'),
(5, 3, 1, DATE_SUB(NOW(), INTERVAL 2 DAY), 'a1b2c3d4e5f67890123456789abcdef0123456789abcdef0123456789abcdef0', 'eligible'),
(5, 4, 1, DATE_SUB(NOW(), INTERVAL 2 DAY), 'f7c3bc1d808e04732adf679965ccc34ca7ae3441e4649b934ca495991b7852c1', 'eligible'),
(5, 5, 1, DATE_SUB(NOW(), INTERVAL 2 DAY), 'b8e9d0123456789abcdef0123456789abcdef0123456789abcdef0123456789a', 'eligible'),
(5, 6, 1, DATE_SUB(NOW(), INTERVAL 2 DAY), 'c9f0a123456789abcdef0123456789abcdef0123456789abcdef0123456789b1', 'eligible');

-- 6. Ballots for Completed Election 5 (Sample ballots matching candidates)
INSERT INTO `ballots` (`election_id`, `candidate_id`, `vote_hash`, `cast_at`) VALUES
(5, 8, SHA2(CONCAT(RAND(), UUID(), NOW()), 256), DATE_SUB(NOW(), INTERVAL 2 DAY)),
(5, 8, SHA2(CONCAT(RAND(), UUID(), NOW()), 256), DATE_SUB(NOW(), INTERVAL 2 DAY)),
(5, 8, SHA2(CONCAT(RAND(), UUID(), NOW()), 256), DATE_SUB(NOW(), INTERVAL 2 DAY)),
(5, 9, SHA2(CONCAT(RAND(), UUID(), NOW()), 256), DATE_SUB(NOW(), INTERVAL 2 DAY)),
(5, 9, SHA2(CONCAT(RAND(), UUID(), NOW()), 256), DATE_SUB(NOW(), INTERVAL 2 DAY)),
(5, 10, SHA2(CONCAT(RAND(), UUID(), NOW()), 256), DATE_SUB(NOW(), INTERVAL 2 DAY));

-- 7. Audit Logs
INSERT INTO `audit_logs` (`user_id`, `action`, `ip_address`, `user_agent`, `details`, `created_at`) VALUES
(1, 'SYSTEM_INIT', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', 'College Electoral Management System database initialized for Kolkata campus demonstration.', DATE_SUB(NOW(), INTERVAL 12 DAY)),
(1, 'ELECTION_CREATED', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', "Created Election: College Students' Council Election 2026–2027", DATE_SUB(NOW(), INTERVAL 1 DAY)),
(1, 'ELECTION_CREATED', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', "Created Election: BCA Department Representative Election 2026–2027", DATE_SUB(NOW(), INTERVAL 2 DAY)),
(1, 'RESULTS_PUBLISHED', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', "Certified and published results for Election: College Alumni Association Election", DATE_SUB(NOW(), INTERVAL 1 DAY));
