-- =====================================================================
-- ApexVote Security Upgrade Database Migration
-- Phase 1 & Phase 9: Student Identity Roster, Login Throttling & 2FA
-- =====================================================================

SET FOREIGN_KEY_CHECKS=0;

-- 1. Create Authorised Student Roster Table
CREATE TABLE IF NOT EXISTS `authorized_students` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `full_name` VARCHAR(150) NOT NULL,
    `college_roll_no` VARCHAR(60) NOT NULL,
    `university_reg_no` VARCHAR(60) NOT NULL,
    `email` VARCHAR(150) NOT NULL,
    `department` VARCHAR(100) NOT NULL,
    `course` VARCHAR(100) NOT NULL,
    `semester` INT NOT NULL DEFAULT 1,
    `academic_session` VARCHAR(30) NOT NULL DEFAULT '2026–2027',
    `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    `verification_status` ENUM('unregistered', 'registered', 'verified') NOT NULL DEFAULT 'unregistered',
    `registered_user_id` INT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_auth_roll` (`college_roll_no`),
    UNIQUE KEY `uk_auth_reg` (`university_reg_no`),
    UNIQUE KEY `uk_auth_email` (`email`),
    INDEX `idx_auth_dept` (`department`),
    INDEX `idx_auth_status` (`status`),
    INDEX `idx_auth_verif` (`verification_status`),
    INDEX `idx_auth_user` (`registered_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Add verification & security columns to users table (if not exists)
SET @dbname = DATABASE();

-- Add is_verified column
SET @exist := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'users' AND COLUMN_NAME = 'is_verified');
SET @sql := IF(@exist = 0, 'ALTER TABLE `users` ADD COLUMN `is_verified` TINYINT(1) NOT NULL DEFAULT 0 AFTER `status`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Add student_roster_id column
SET @exist := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'users' AND COLUMN_NAME = 'student_roster_id');
SET @sql := IF(@exist = 0, 'ALTER TABLE `users` ADD COLUMN `student_roster_id` INT NULL AFTER `is_verified`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Add totp_secret column (for optional admin MFA)
SET @exist := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'users' AND COLUMN_NAME = 'totp_secret');
SET @sql := IF(@exist = 0, 'ALTER TABLE `users` ADD COLUMN `totp_secret` VARCHAR(64) NULL AFTER `student_roster_id`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Add totp_enabled column
SET @exist := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'users' AND COLUMN_NAME = 'totp_enabled');
SET @sql := IF(@exist = 0, 'ALTER TABLE `users` ADD COLUMN `totp_enabled` TINYINT(1) NOT NULL DEFAULT 0 AFTER `totp_secret`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Add password_changed_at column
SET @exist := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'users' AND COLUMN_NAME = 'password_changed_at');
SET @sql := IF(@exist = 0, 'ALTER TABLE `users` ADD COLUMN `password_changed_at` DATETIME NULL AFTER `totp_enabled`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Add verification_channel column to otp_challenges
SET @exist := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'otp_challenges' AND COLUMN_NAME = 'verification_channel');
SET @sql := IF(@exist = 0, 'ALTER TABLE `otp_challenges` ADD COLUMN `verification_channel` ENUM(\'email\', \'sms\') NOT NULL DEFAULT \'email\' AFTER `sms_destination_hash`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 3. Create Login Rate Limits Table for brute-force protection
CREATE TABLE IF NOT EXISTS `login_rate_limits` (
    `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
    `identifier_hash` VARCHAR(64) NOT NULL,
    `ip_address` VARCHAR(45) NOT NULL,
    `attempt_count` INT NOT NULL DEFAULT 1,
    `locked_until` DATETIME NULL,
    `last_attempt` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_login_rate_ip` (`ip_address`, `last_attempt`),
    INDEX `idx_login_rate_ident` (`identifier_hash`, `locked_until`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Populate authorized_students from existing voters (so existing voters remain valid)
INSERT INTO `authorized_students` 
    (`full_name`, `college_roll_no`, `university_reg_no`, `email`, `department`, `course`, `semester`, `academic_session`, `status`, `verification_status`, `registered_user_id`)
VALUES
    ('Sourav Ganguly', 'BCA/2026/041', '12623010041', 'voter1@college.edu', 'Computer Applications', 'BCA', 6, '2026–2027', 'active', 'verified', 2),
    ('Rituparna Sen', 'CSE/2026/019', '12623010019', 'voter2@college.edu', 'Computer Science & Engineering', 'BTech CSE', 4, '2026–2027', 'active', 'verified', 3),
    ('Aritra Bhattacharya', 'BBA/2026/012', '12623010012', 'voter3@college.edu', 'Business Administration', 'BBA', 4, '2026–2027', 'active', 'verified', 4),
    ('Koyel Roy', 'COM/2026/088', '12623010088', 'voter4@college.edu', 'Commerce', 'BCom (Hons)', 2, '2026–2027', 'active', 'verified', 5),
    ('Bikramjit Paul', 'MCA/2026/007', '12623010007', 'voter5@college.edu', 'Computer Applications', 'MCA', 2, '2026–2027', 'active', 'verified', 6),
    ('Tanushree Sarkar', 'IT/2026/024', '12623010024', 'voter6@college.edu', 'Information Technology', 'BTech IT', 6, '2026–2027', 'active', 'verified', 7),
    -- Additional pre-authorized student roster records eligible to register
    ('Aniket Mukherjee', 'BCA/2026/042', '12623010042', 'aniket.m@college.edu', 'Computer Applications', 'BCA', 6, '2026–2027', 'active', 'unregistered', NULL),
    ('Priya Dasgupta', 'CSE/2026/020', '12623010020', 'priya.d@college.edu', 'Computer Science & Engineering', 'BTech CSE', 4, '2026–2027', 'active', 'unregistered', NULL),
    ('Debmalya Ghosh', 'BBA/2026/015', '12623010015', 'debmalya.g@college.edu', 'Business Administration', 'BBA', 4, '2026–2027', 'active', 'unregistered', NULL),
    ('Sneha Banerjee', 'COM/2026/092', '12623010092', 'sneha.b@college.edu', 'Commerce', 'BCom (Hons)', 2, '2026–2027', 'active', 'unregistered', NULL),
    ('Rahul Majumdar', 'MCA/2026/011', '12623010011', 'rahul.m@college.edu', 'Computer Applications', 'MCA', 2, '2026–2027', 'active', 'unregistered', NULL),
    ('Ishita Chakraborty', 'IT/2026/030', '12623010030', 'ishita.c@college.edu', 'Information Technology', 'BTech IT', 6, '2026–2027', 'active', 'unregistered', NULL),
    ('Inactive Student Test', 'BCA/2026/099', '12623010099', 'inactive@college.edu', 'Computer Applications', 'BCA', 6, '2026–2027', 'inactive', 'unregistered', NULL)
ON DUPLICATE KEY UPDATE 
    `status` = VALUES(`status`),
    `verification_status` = VALUES(`verification_status`),
    `registered_user_id` = VALUES(`registered_user_id`);

-- 5. Mark existing users as verified
UPDATE `users` SET `is_verified` = 1 WHERE `role` = 'voter';
UPDATE `users` SET `is_verified` = 1 WHERE `role` = 'admin';

-- Link existing voters to their student roster id
UPDATE `users` u 
JOIN `authorized_students` a ON u.college_roll_no = a.college_roll_no 
SET u.student_roster_id = a.id;

SET FOREIGN_KEY_CHECKS=1;
