-- ==============================================================
-- ApexVote OTP Verification System - Database Migration
-- Supports: Mandatory Email OTP + Optional User-Selected SMS OTP
-- Engine: MySQL 8+ / MariaDB (InnoDB, utf8mb4_unicode_ci)
-- ==============================================================

USE `online_voting_system`;

-- 1. Create otp_challenges table
CREATE TABLE IF NOT EXISTS `otp_challenges` (
    `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
    `voter_id` INT NOT NULL,
    `election_id` INT NOT NULL,
    `session_binding_hash` VARCHAR(64) NOT NULL,
    `email_otp_hash` VARCHAR(255) NULL,
    `sms_otp_hash` VARCHAR(255) NULL,
    `sms_provider_ref` VARCHAR(100) NULL,
    `sms_selected` TINYINT(1) NOT NULL DEFAULT 0,
    `sms_destination_masked` VARCHAR(30) NULL,
    `sms_destination_hash` VARCHAR(64) NULL,
    `email_expires_at` DATETIME NULL,
    `sms_expires_at` DATETIME NULL,
    `email_attempts` INT NOT NULL DEFAULT 0,
    `sms_attempts` INT NOT NULL DEFAULT 0,
    `email_verified_at` DATETIME NULL,
    `sms_verified_at` DATETIME NULL,
    `authorization_token_hash` VARCHAR(64) NULL,
    `authorization_expires_at` DATETIME NULL,
    `status` ENUM('pending', 'verified', 'consumed', 'invalidated', 'expired') DEFAULT 'pending',
    `ip_address` VARCHAR(45) NOT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `consumed_at` DATETIME NULL,
    INDEX `idx_voter_election` (`voter_id`, `election_id`, `status`),
    INDEX `idx_auth_token_hash` (`authorization_token_hash`),
    INDEX `idx_session_hash` (`session_binding_hash`),
    INDEX `idx_created_at` (`created_at`),
    CONSTRAINT `fk_otp_voter` FOREIGN KEY (`voter_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_otp_election` FOREIGN KEY (`election_id`) REFERENCES `elections` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Create otp_rate_limits table
CREATE TABLE IF NOT EXISTS `otp_rate_limits` (
    `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
    `rate_key` VARCHAR(120) NOT NULL,
    `action_type` VARCHAR(40) NOT NULL,
    `hit_count` INT NOT NULL DEFAULT 1,
    `window_start` DATETIME NOT NULL,
    `last_hit` DATETIME NOT NULL,
    INDEX `idx_rate_key_window` (`rate_key`, `window_start`),
    INDEX `idx_action_type` (`action_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
