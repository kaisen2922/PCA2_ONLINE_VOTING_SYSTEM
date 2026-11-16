-- ApexVote Database Backup
-- Generated: 2026-11-10 21:30:53

SET FOREIGN_KEY_CHECKS=0;

DROP TABLE IF EXISTS `audit_logs`;
CREATE TABLE `audit_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` text DEFAULT NULL,
  `details` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_audit_user` (`user_id`),
  KEY `idx_audit_action` (`action`),
  KEY `idx_audit_created` (`created_at`),
  CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=36 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `ip_address`, `user_agent`, `details`, `created_at`) VALUES
('1', '1', 'SYSTEM_INIT', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', 'College Electoral Management System database initialized for Kolkata campus demonstration.', '2026-10-28 20:59:17'),
('2', '1', 'ELECTION_CREATED', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', 'Created Election: College Students\' Council Election 2026–2027', '2026-11-08 20:59:17'),
('3', '1', 'ELECTION_CREATED', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', 'Created Election: BCA Department Representative Election 2026–2027', '2026-11-07 20:59:17'),
('4', '1', 'RESULTS_PUBLISHED', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', 'Certified and published results for Election: College Alumni Association Election', '2026-11-08 20:59:17'),
('5', '2', 'USER_LOGIN', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36', 'User logged in: voter1@college.edu', '2026-11-09 21:02:10'),
('6', '2', 'BALLOT_CAST', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36', 'Voter participated in Election #2 (\'2\'). Receipt generated.', '2026-11-09 21:38:07'),
('7', '2', 'OTP_EMAIL_REQUESTED', '127.0.0.1', 'PHPUnit-Automated-Test-Runner/1.0', 'Email OTP challenge #1 generated for Election #1', '2026-11-09 22:07:47'),
('8', '2', 'OTP_EMAIL_VERIFIED', '127.0.0.1', 'PHPUnit-Automated-Test-Runner/1.0', 'Email OTP verified for challenge #1', '2026-11-09 22:07:47'),
('9', '2', 'OTP_EMAIL_REQUESTED', '127.0.0.1', 'PHPUnit-Automated-Test-Runner/1.0', 'Email OTP challenge #2 generated for Election #1', '2026-11-09 22:08:23'),
('10', '2', 'OTP_EMAIL_VERIFIED', '127.0.0.1', 'PHPUnit-Automated-Test-Runner/1.0', 'Email OTP verified for challenge #2', '2026-11-09 22:08:23'),
('11', '2', 'OTP_EMAIL_REQUESTED', '127.0.0.1', 'PHPUnit-Automated-Test-Runner/1.0', 'Email OTP challenge #3 generated for Election #1', '2026-11-09 22:08:23'),
('12', '2', 'OTP_EMAIL_REQUESTED', '127.0.0.1', 'PHPUnit-Automated-Test-Runner/1.0', 'Email OTP challenge #4 generated for Election #1', '2026-11-09 22:08:24'),
('13', '2', 'OTP_SMS_REQUESTED', '127.0.0.1', 'PHPUnit-Automated-Test-Runner/1.0', 'SMS OTP challenge sent to +919 *****3456 for challenge #4', '2026-11-09 22:08:24'),
('14', '2', 'OTP_EMAIL_VERIFIED', '127.0.0.1', 'PHPUnit-Automated-Test-Runner/1.0', 'Email OTP verified for challenge #4', '2026-11-09 22:08:24'),
('15', '2', 'OTP_SMS_VERIFIED', '127.0.0.1', 'PHPUnit-Automated-Test-Runner/1.0', 'SMS OTP verified for challenge #4', '2026-11-09 22:08:24'),
('16', '2', 'OTP_EMAIL_REQUESTED', '127.0.0.1', 'PHPUnit-Automated-Test-Runner/1.0', 'Email OTP challenge #5 generated for Election #1', '2026-11-09 22:08:24'),
('17', '2', 'OTP_SMS_REQUESTED', '127.0.0.1', 'PHPUnit-Automated-Test-Runner/1.0', 'SMS OTP challenge sent to +919 *****3456 for challenge #5', '2026-11-09 22:08:24'),
('18', '2', 'OTP_EMAIL_VERIFIED', '127.0.0.1', 'PHPUnit-Automated-Test-Runner/1.0', 'Email OTP verified for challenge #5', '2026-11-09 22:08:24'),
('19', '2', 'OTP_EMAIL_REQUESTED', '127.0.0.1', 'PHPUnit-Automated-Test-Runner/1.0', 'Email OTP challenge #6 generated for Election #1', '2026-11-09 22:08:24'),
('20', '2', 'OTP_SMS_REQUESTED', '127.0.0.1', 'PHPUnit-Automated-Test-Runner/1.0', 'SMS OTP challenge sent to +919 *****3456 for challenge #6', '2026-11-09 22:08:25'),
('21', '2', 'OTP_SMS_VERIFIED', '127.0.0.1', 'PHPUnit-Automated-Test-Runner/1.0', 'SMS OTP verified for challenge #6', '2026-11-09 22:08:25'),
('22', '2', 'OTP_EMAIL_REQUESTED', '127.0.0.1', 'PHPUnit-Automated-Test-Runner/1.0', 'Email OTP challenge #7 generated for Election #1', '2026-11-09 22:08:25'),
('23', '2', 'OTP_SMS_REQUESTED', '127.0.0.1', 'PHPUnit-Automated-Test-Runner/1.0', 'SMS OTP challenge sent to +919 *****3456 for challenge #7', '2026-11-09 22:08:25'),
('24', '2', 'OTP_EMAIL_VERIFIED', '127.0.0.1', 'PHPUnit-Automated-Test-Runner/1.0', 'Email OTP verified for challenge #7', '2026-11-09 22:08:25'),
('25', '2', 'OTP_EMAIL_REQUESTED', '127.0.0.1', 'PHPUnit-Automated-Test-Runner/1.0', 'Email OTP challenge #8 generated for Election #1', '2026-11-09 22:08:25'),
('26', '2', 'OTP_EMAIL_VERIFIED', '127.0.0.1', 'PHPUnit-Automated-Test-Runner/1.0', 'Email OTP verified for challenge #8', '2026-11-09 22:08:25'),
('27', '2', 'OTP_EMAIL_REQUESTED', '127.0.0.1', 'PHPUnit-Automated-Test-Runner/1.0', 'Email OTP challenge #9 generated for Election #1', '2026-11-09 22:08:25'),
('28', '2', 'OTP_EMAIL_REQUESTED', '127.0.0.1', 'PHPUnit-Automated-Test-Runner/1.0', 'Email OTP challenge #10 generated for Election #1', '2026-11-09 22:08:25'),
('29', '2', 'OTP_SMS_REQUESTED', '127.0.0.1', 'PHPUnit-Automated-Test-Runner/1.0', 'SMS OTP challenge sent to +919 *****1111 for challenge #10', '2026-11-09 22:08:26'),
('30', '2', 'OTP_EMAIL_REQUESTED', '127.0.0.1', 'PHPUnit-Automated-Test-Runner/1.0', 'Email OTP challenge #11 generated for Election #1', '2026-11-09 22:08:26'),
('31', '4', 'OTP_EMAIL_REQUESTED', '127.0.0.1', 'PHPUnit-Automated-Test-Runner/1.0', 'Email OTP challenge #12 generated for Election #4', '2026-11-09 22:08:45'),
('32', '4', 'OTP_EMAIL_VERIFIED', '127.0.0.1', 'PHPUnit-Automated-Test-Runner/1.0', 'Email OTP verified for challenge #12', '2026-11-09 22:08:45'),
('33', '4', 'BALLOT_CAST', '127.0.0.1', 'PHPUnit-Automated-Test-Runner/1.0', 'Ballot cast in Election #4 with dual OTP authorization. Receipt generated.', '2026-11-09 22:08:45'),
('34', '2', 'OTP_EMAIL_REQUESTED', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36', 'Email OTP challenge #13 generated for Election #1', '2026-11-09 22:13:06'),
('35', '2', 'USER_LOGIN', '::1', 'Unknown', 'User logged in: voter1@college.edu', '2026-11-10 18:11:43');

DROP TABLE IF EXISTS `ballots`;
CREATE TABLE `ballots` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `election_id` int(11) NOT NULL,
  `candidate_id` int(11) NOT NULL,
  `vote_hash` varchar(64) NOT NULL,
  `cast_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ballots_election` (`election_id`),
  KEY `idx_ballots_candidate` (`candidate_id`),
  CONSTRAINT `fk_ballots_candidate` FOREIGN KEY (`candidate_id`) REFERENCES `candidates` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ballots_election` FOREIGN KEY (`election_id`) REFERENCES `elections` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `ballots` (`id`, `election_id`, `candidate_id`, `vote_hash`, `cast_at`) VALUES
('1', '5', '8', 'ac01704f104af012a5af66f0902728e384a02c143e6d27639c29d6084230877d', '2026-11-07 20:59:17'),
('2', '5', '8', '9861a1080eaf793816ce0facd6e6810f91a136f55921cad426421798829ec03a', '2026-11-07 20:59:17'),
('3', '5', '8', 'df0a343879a925059fb950a1c4665fe3b16522410e9f12abb0b962042da09e5a', '2026-11-07 20:59:17'),
('4', '5', '9', '3bf53fa71e112ce67f6f6b7395c6fead48c00964c022dfc6714765dd8d39edb2', '2026-11-07 20:59:17'),
('5', '5', '9', 'c611141be89dba33e2d0e6e1862e0aae3381c78f36bf0bde0077c85a6a98a475', '2026-11-07 20:59:17'),
('6', '5', '10', '3b1a8c27564931a0c1e72ae04080130e8d72db027eb09c37691c5f020d206b21', '2026-11-07 20:59:17'),
('7', '2', '4', '57f1d6a3c641e814cbfca7bc961c830ae7333b66c10a257554f1dd83570e620b', '2026-11-09 21:38:07');

DROP TABLE IF EXISTS `candidates`;
CREATE TABLE `candidates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `election_id` int(11) NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `position` varchar(150) NOT NULL,
  `party_or_slate` varchar(100) NOT NULL DEFAULT 'Independent',
  `manifesto` text NOT NULL,
  `photo_url` varchar(255) DEFAULT NULL,
  `ballot_number` int(11) NOT NULL DEFAULT 1,
  `vote_count` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_candidates_election` (`election_id`),
  CONSTRAINT `fk_candidates_election` FOREIGN KEY (`election_id`) REFERENCES `elections` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `candidates` (`id`, `election_id`, `full_name`, `position`, `party_or_slate`, `manifesto`, `photo_url`, `ballot_number`, `vote_count`, `created_at`) VALUES
('1', '1', 'Rahul Das', 'Students\' Council President', 'Progress & Development Panel', 'Dedicated to expanding 24/7 college library access with digital journals, setting up modern high-speed Wi-Fi across campus, organizing regular technical workshops, and establishing an active placement cell mentorship circle.', 'assets/images/candidate1.svg', '1', '0', '2026-11-09 20:59:17'),
('2', '1', 'Priya Sharma', 'Students\' Council President', 'Student Welfare Panel', 'Committed to overhauling college canteen hygiene with subsidized nutritious meals, upgrading the girls\' common room facilities, creating a swift grievance redressal cell, and introducing free mental health counseling seminars.', 'assets/images/candidate2.svg', '2', '0', '2026-11-09 20:59:17'),
('3', '1', 'Arjun Roy', 'Students\' Council President', 'Campus Reform Panel', 'Pledging hardware lab modernization with dedicated GPU systems for project work, transparent student council fund audits, inter-college coding hackathons, and corporate internship partnerships with Kolkata IT leaders.', 'assets/images/candidate3.svg', '3', '0', '2026-11-09 20:59:17'),
('4', '2', 'Sneha Mukherjee', 'Department Representative', 'Cultural Development Panel', 'Advocating for extended computer lab timings during semester project weeks, hands-on cloud computing workshops, industry guest lectures from Sector V tech veterans, and peer study groups.', 'assets/images/candidate4.svg', '1', '1', '2026-11-09 20:59:17'),
('5', '2', 'Aditya Banerjee', 'Class Representative', 'Academic Excellence Panel', 'Focusing on peer mentoring sessions, faculty-student bridge meetings, structured placement interview prep with mock coding tests, and digital access to syllabus study guides.', 'assets/images/candidate5.svg', '2', '0', '2026-11-09 20:59:17'),
('6', '3', 'Ananya Chatterjee', 'Cultural Secretary', 'Student Voice Panel', 'Revitalizing the annual college cultural festival, organizing Rabindra Jayanti and inter-college drama competitions, procuring state-of-the-art music club equipment, and launching a creative arts college magazine.', 'assets/images/candidate6.svg', '1', '0', '2026-11-09 20:59:17'),
('7', '4', 'Rohan Dutta', 'Sports Secretary', 'Campus Innovation Panel', 'Upgrading the college gymnasium, resurfacing badminton and basketball courts, organizing the annual football and cricket tournaments, and sponsoring student athletes for inter-university sports meets.', 'assets/images/candidate7.svg', '1', '0', '2026-11-09 20:59:17'),
('8', '5', 'Ishita Ghosh', 'Alumni Representative', 'Green Campus Panel', 'Active mentor connecting Kolkata tech alumni to graduating students, financing merit-cum-means semester tuition stipends, arranging tech company internship referrals, and leading solar energy campus green drives.', 'assets/images/candidate8.svg', '1', '142', '2026-11-09 20:59:17'),
('9', '5', 'Debashis Sen', 'Alumni Committee Member', 'Alumni Heritage Panel', 'Promoting international student exchange endowments, annual alumni reunions in Kolkata, entrepreneurship incubator angel funds, and campus infrastructure grants.', 'assets/images/candidate7.svg', '2', '98', '2026-11-09 20:59:17'),
('10', '5', 'Soumya Chatterjee', 'Alumni Committee Member', 'Student First Alumni', 'Advocating for student placement emergency relief funds, subsidized laptop schemes for underprivileged students, and venture incubation demo days.', 'assets/images/candidate6.svg', '3', '64', '2026-11-09 20:59:17');

DROP TABLE IF EXISTS `elections`;
CREATE TABLE `elections` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `category` varchar(100) NOT NULL DEFAULT 'College Students'' Council',
  `start_time` datetime NOT NULL,
  `end_time` datetime NOT NULL,
  `status` enum('draft','active','completed','cancelled') NOT NULL DEFAULT 'draft',
  `results_published` tinyint(1) NOT NULL DEFAULT 0,
  `banner_image` varchar(255) DEFAULT NULL,
  `eligibility_type` enum('all','department') NOT NULL DEFAULT 'all',
  `target_department` varchar(100) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_elections_status` (`status`),
  KEY `idx_elections_timing` (`start_time`,`end_time`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `elections` (`id`, `title`, `description`, `category`, `start_time`, `end_time`, `status`, `results_published`, `banner_image`, `eligibility_type`, `target_department`, `created_at`, `updated_at`) VALUES
('1', 'College Students\' Council Election 2026–2027', 'Annual democratic election to elect the executive student leadership of the College Students\' Council for the 2026–2027 academic session. All registered undergraduate and postgraduate students across all campus departments are eligible to vote.', 'College Students\' Council', '2026-11-08 20:59:17', '2026-11-14 20:59:17', 'active', '0', NULL, 'all', NULL, '2026-11-09 20:59:17', '2026-11-09 20:59:17'),
('2', 'BCA Department Representative Election 2026–2027', 'Election for the Department Representative representing the Department of Computer Applications (BCA) on the Academic Council and faculty-student liaison committee.', 'Department Representative', '2026-11-07 20:59:17', '2026-11-13 20:59:17', 'active', '0', NULL, 'department', 'Computer Applications', '2026-11-09 20:59:17', '2026-11-09 20:59:17'),
('3', 'Cultural Committee Election 2026–2027', 'Annual election to appoint the Cultural Secretary and Event Coordinators responsible for organizing inter-college fests, youth festivals, and cultural activities.', 'Cultural Committee', '2026-11-08 20:59:17', '2026-11-15 20:59:17', 'active', '0', NULL, 'all', NULL, '2026-11-09 20:59:17', '2026-11-09 20:59:17'),
('4', 'Sports Committee Election 2026–2027', 'Democratic election to elect the Sports Secretary and Coordinators overseeing the annual college sports meet, inter-college football, cricket tournaments, and indoor games.', 'Sports Committee', '2026-11-06 20:59:17', '2026-11-12 20:59:17', 'active', '0', NULL, 'all', NULL, '2026-11-09 20:59:17', '2026-11-09 20:59:17'),
('5', 'College Alumni Association Election', 'Completed election for the institutional alumni representatives coordinating graduate mentorship circles, scholarship endowments, and career placement linkages.', 'Alumni Association', '2026-10-30 20:59:17', '2026-11-08 20:59:17', 'completed', '1', NULL, 'all', NULL, '2026-11-09 20:59:17', '2026-11-09 20:59:17');

DROP TABLE IF EXISTS `otp_challenges`;
CREATE TABLE `otp_challenges` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `voter_id` int(11) NOT NULL,
  `election_id` int(11) NOT NULL,
  `session_binding_hash` varchar(64) NOT NULL,
  `email_otp_hash` varchar(255) DEFAULT NULL,
  `sms_otp_hash` varchar(255) DEFAULT NULL,
  `sms_provider_ref` varchar(100) DEFAULT NULL,
  `sms_selected` tinyint(1) NOT NULL DEFAULT 0,
  `sms_destination_masked` varchar(30) DEFAULT NULL,
  `sms_destination_hash` varchar(64) DEFAULT NULL,
  `email_expires_at` datetime DEFAULT NULL,
  `sms_expires_at` datetime DEFAULT NULL,
  `email_attempts` int(11) NOT NULL DEFAULT 0,
  `sms_attempts` int(11) NOT NULL DEFAULT 0,
  `email_verified_at` datetime DEFAULT NULL,
  `sms_verified_at` datetime DEFAULT NULL,
  `authorization_token_hash` varchar(64) DEFAULT NULL,
  `authorization_expires_at` datetime DEFAULT NULL,
  `status` enum('pending','verified','consumed','invalidated','expired') DEFAULT 'pending',
  `ip_address` varchar(45) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `consumed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_voter_election` (`voter_id`,`election_id`,`status`),
  KEY `idx_auth_token_hash` (`authorization_token_hash`),
  KEY `idx_session_hash` (`session_binding_hash`),
  KEY `idx_created_at` (`created_at`),
  KEY `fk_otp_election` (`election_id`),
  CONSTRAINT `fk_otp_election` FOREIGN KEY (`election_id`) REFERENCES `elections` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_otp_voter` FOREIGN KEY (`voter_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `otp_challenges` (`id`, `voter_id`, `election_id`, `session_binding_hash`, `email_otp_hash`, `sms_otp_hash`, `sms_provider_ref`, `sms_selected`, `sms_destination_masked`, `sms_destination_hash`, `email_expires_at`, `sms_expires_at`, `email_attempts`, `sms_attempts`, `email_verified_at`, `sms_verified_at`, `authorization_token_hash`, `authorization_expires_at`, `status`, `ip_address`, `created_at`, `updated_at`, `consumed_at`) VALUES
('13', '2', '1', '82cc785bddc0754bf82c02423280f347730ed37afb5a157e3916c66173e020d9', '$2y$10$bFrtgTORKBeJEBi8aTKo5OGCOtYtfscIAr0VgJH0T4hQgUPPhq52m', NULL, NULL, '0', NULL, NULL, '2026-11-09 22:18:06', NULL, '0', '0', NULL, NULL, NULL, NULL, 'pending', '::1', '2026-11-09 22:13:06', '2026-11-09 22:13:06', NULL);

DROP TABLE IF EXISTS `otp_rate_limits`;
CREATE TABLE `otp_rate_limits` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `rate_key` varchar(120) NOT NULL,
  `action_type` varchar(40) NOT NULL,
  `hit_count` int(11) NOT NULL DEFAULT 1,
  `window_start` datetime NOT NULL,
  `last_hit` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_rate_key_window` (`rate_key`,`window_start`),
  KEY `idx_action_type` (`action_type`)
) ENGINE=InnoDB AUTO_INCREMENT=36 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `otp_rate_limits` (`id`, `rate_key`, `action_type`, `hit_count`, `window_start`, `last_hit`) VALUES
('34', 'email_voter_2', 'email_send', '1', '2026-11-09 22:13:06', '2026-11-09 22:13:06'),
('35', 'email_ip_::1', 'email_send', '1', '2026-11-09 22:13:06', '2026-11-09 22:13:06');

DROP TABLE IF EXISTS `settings`;
CREATE TABLE `settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `settings` (`id`, `setting_key`, `setting_value`, `created_at`, `updated_at`) VALUES
('1', 'site_name', 'ApexVote – College Electoral Management System', '2026-11-09 20:59:17', '2026-11-09 20:59:17'),
('2', 'institution_name', 'ApexVote College Demonstration Campus', '2026-11-09 20:59:17', '2026-11-09 20:59:17'),
('3', 'institution_location', 'Kolkata, West Bengal, India', '2026-11-09 20:59:17', '2026-11-09 20:59:17'),
('4', 'academic_year', '2026–2027', '2026-11-09 20:59:17', '2026-11-09 20:59:17'),
('5', 'currency_symbol', '₹', '2026-11-09 20:59:17', '2026-11-09 20:59:17'),
('6', 'allow_registration', '1', '2026-11-09 20:59:17', '2026-11-09 20:59:17'),
('7', 'contact_email', 'election.committee@apexcollege.edu.in', '2026-11-09 20:59:17', '2026-11-09 20:59:17'),
('8', 'contact_phone', '+91 98300 12345', '2026-11-09 20:59:17', '2026-11-09 20:59:17'),
('9', 'voting_mode', 'standard', '2026-11-09 20:59:17', '2026-11-09 20:59:17'),
('10', 'demo_mode', '1', '2026-11-09 20:59:17', '2026-11-09 20:59:17'),
('11', 'timezone', 'Asia/Kolkata', '2026-11-09 20:59:17', '2026-11-09 20:59:17');

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `full_name` varchar(150) NOT NULL,
  `email` varchar(150) NOT NULL,
  `voter_id_number` varchar(50) NOT NULL,
  `college_roll_no` varchar(60) DEFAULT NULL,
  `university_reg_no` varchar(60) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('voter','admin') NOT NULL DEFAULT 'voter',
  `department` varchar(100) NOT NULL DEFAULT 'Computer Applications',
  `course` varchar(100) DEFAULT 'BCA',
  `semester` int(11) DEFAULT 1,
  `academic_session` varchar(30) DEFAULT '2026–2027',
  `phone` varchar(30) DEFAULT NULL,
  `status` enum('active','pending','suspended') NOT NULL DEFAULT 'active',
  `avatar_url` varchar(255) DEFAULT 'default-avatar.svg',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `voter_id_number` (`voter_id_number`),
  KEY `idx_users_role` (`role`),
  KEY `idx_users_status` (`status`),
  KEY `idx_users_roll` (`college_roll_no`),
  KEY `idx_users_reg` (`university_reg_no`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `users` (`id`, `full_name`, `email`, `voter_id_number`, `college_roll_no`, `university_reg_no`, `password_hash`, `role`, `department`, `course`, `semester`, `academic_session`, `phone`, `status`, `avatar_url`, `created_at`, `updated_at`) VALUES
('1', 'Dr. Subhash Mukherjee', 'admin@college.edu', 'ADM-KOL-2026-001', 'FAC-CS-001', 'WB-UNIV-FAC-2015', '$2y$10$qU/wX.kplQEtZe9QrPTcTuo5M6X8MMU.0aVYdIl60bh9nqAKxfSqu', 'admin', 'College Election Committee', 'Faculty Administration', '0', '2026–2027', '+91 98300 12345', 'active', 'default-avatar.svg', '2026-11-09 20:59:17', '2026-11-09 20:59:17'),
('2', 'Sourav Ganguly', 'voter1@college.edu', 'BCA/2026/041', 'BCA/2026/041', '12623010041', '$2y$10$d6UncSifP57uW7yyxemqrO1n9/BfyNiVzGRwFVLc1bE.uXh4Nc696', 'voter', 'Computer Applications', 'BCA', '6', '2026–2027', '+91 98301 23456', 'active', 'default-avatar.svg', '2026-11-09 20:59:17', '2026-11-09 20:59:17'),
('3', 'Rituparna Sen', 'voter2@college.edu', 'CSE/2026/019', 'CSE/2026/019', '12623010019', '$2y$10$d6UncSifP57uW7yyxemqrO1n9/BfyNiVzGRwFVLc1bE.uXh4Nc696', 'voter', 'Computer Science & Engineering', 'B.Tech CSE', '4', '2026–2027', '+91 98302 34567', 'active', 'default-avatar.svg', '2026-11-09 20:59:17', '2026-11-09 20:59:17'),
('4', 'Aritra Bhattacharya', 'voter3@college.edu', 'BBA/2026/012', 'BBA/2026/012', '12623010012', '$2y$10$d6UncSifP57uW7yyxemqrO1n9/BfyNiVzGRwFVLc1bE.uXh4Nc696', 'voter', 'Business Administration', 'BBA', '4', '2026–2027', '+91 98303 45678', 'active', 'default-avatar.svg', '2026-11-09 20:59:17', '2026-11-09 20:59:17'),
('5', 'Koyel Roy', 'voter4@college.edu', 'COM/2026/088', 'COM/2026/088', '12623010088', '$2y$10$d6UncSifP57uW7yyxemqrO1n9/BfyNiVzGRwFVLc1bE.uXh4Nc696', 'voter', 'Commerce', 'B.Com (Hons.)', '2', '2026–2027', '+91 98304 56789', 'active', 'default-avatar.svg', '2026-11-09 20:59:17', '2026-11-09 20:59:17'),
('6', 'Bikramjit Paul', 'voter5@college.edu', 'MCA/2026/007', 'MCA/2026/007', '12623010007', '$2y$10$d6UncSifP57uW7yyxemqrO1n9/BfyNiVzGRwFVLc1bE.uXh4Nc696', 'voter', 'Computer Applications', 'MCA', '2', '2026–2027', '+91 98305 67890', 'active', 'default-avatar.svg', '2026-11-09 20:59:17', '2026-11-09 20:59:17'),
('7', 'Tanushree Sarkar', 'voter6@college.edu', 'IT/2026/024', 'IT/2026/024', '12623010024', '$2y$10$d6UncSifP57uW7yyxemqrO1n9/BfyNiVzGRwFVLc1bE.uXh4Nc696', 'voter', 'Information Technology', 'B.Tech IT', '6', '2026–2027', '+91 98306 78901', 'active', 'default-avatar.svg', '2026-11-09 20:59:17', '2026-11-09 20:59:17');

DROP TABLE IF EXISTS `voter_eligibility`;
CREATE TABLE `voter_eligibility` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `election_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `has_voted` tinyint(1) NOT NULL DEFAULT 0,
  `voted_at` datetime DEFAULT NULL,
  `receipt_token` varchar(64) DEFAULT NULL,
  `eligibility_status` enum('eligible','revoked') NOT NULL DEFAULT 'eligible',
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_election_voter` (`election_id`,`user_id`),
  KEY `idx_eligibility_has_voted` (`has_voted`),
  KEY `fk_eligibility_user` (`user_id`),
  CONSTRAINT `fk_eligibility_election` FOREIGN KEY (`election_id`) REFERENCES `elections` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_eligibility_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `voter_eligibility` (`id`, `election_id`, `user_id`, `has_voted`, `voted_at`, `receipt_token`, `eligibility_status`, `created_at`) VALUES
('1', '1', '2', '0', NULL, NULL, 'eligible', '2026-11-09 20:59:17'),
('2', '1', '3', '0', NULL, NULL, 'eligible', '2026-11-09 20:59:17'),
('3', '1', '4', '0', NULL, NULL, 'eligible', '2026-11-09 20:59:17'),
('4', '1', '5', '0', NULL, NULL, 'eligible', '2026-11-09 20:59:17'),
('5', '1', '6', '0', NULL, NULL, 'eligible', '2026-11-09 20:59:17'),
('6', '1', '7', '0', NULL, NULL, 'eligible', '2026-11-09 20:59:17'),
('7', '3', '2', '0', NULL, NULL, 'eligible', '2026-11-09 20:59:17'),
('8', '3', '3', '0', NULL, NULL, 'eligible', '2026-11-09 20:59:17'),
('9', '3', '4', '0', NULL, NULL, 'eligible', '2026-11-09 20:59:17'),
('10', '3', '5', '0', NULL, NULL, 'eligible', '2026-11-09 20:59:17'),
('11', '3', '6', '0', NULL, NULL, 'eligible', '2026-11-09 20:59:17'),
('12', '3', '7', '0', NULL, NULL, 'eligible', '2026-11-09 20:59:17'),
('13', '4', '2', '0', NULL, NULL, 'eligible', '2026-11-09 20:59:17'),
('14', '4', '3', '0', NULL, NULL, 'eligible', '2026-11-09 20:59:17'),
('15', '4', '4', '0', NULL, NULL, 'eligible', '2026-11-09 20:59:17'),
('16', '4', '5', '0', NULL, NULL, 'eligible', '2026-11-09 20:59:17'),
('17', '4', '6', '0', NULL, NULL, 'eligible', '2026-11-09 20:59:17'),
('18', '4', '7', '0', NULL, NULL, 'eligible', '2026-11-09 20:59:17'),
('19', '2', '2', '1', '2026-11-09 21:38:07', 'ea9218f754dd3a841f29d69834cae1c0a86711a7732a3c1eb97db233093c7a24', 'eligible', '2026-11-09 20:59:17'),
('20', '2', '6', '0', NULL, NULL, 'eligible', '2026-11-09 20:59:17'),
('21', '5', '2', '1', '2026-11-07 20:59:17', 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855', 'eligible', '2026-11-09 20:59:17'),
('22', '5', '3', '1', '2026-11-07 20:59:17', 'a1b2c3d4e5f67890123456789abcdef0123456789abcdef0123456789abcdef0', 'eligible', '2026-11-09 20:59:17'),
('23', '5', '4', '1', '2026-11-07 20:59:17', 'f7c3bc1d808e04732adf679965ccc34ca7ae3441e4649b934ca495991b7852c1', 'eligible', '2026-11-09 20:59:17'),
('24', '5', '5', '1', '2026-11-07 20:59:17', 'b8e9d0123456789abcdef0123456789abcdef0123456789abcdef0123456789a', 'eligible', '2026-11-09 20:59:17'),
('25', '5', '6', '1', '2026-11-07 20:59:17', 'c9f0a123456789abcdef0123456789abcdef0123456789abcdef0123456789b1', 'eligible', '2026-11-09 20:59:17');

SET FOREIGN_KEY_CHECKS=1;
