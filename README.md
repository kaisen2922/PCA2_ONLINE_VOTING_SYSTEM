# ApexVote – College Electoral Management System

[![PHP Version](https://img.shields.io/badge/PHP-8.2%2B-blue.svg)](https://www.php.net/)
[![Database](https://img.shields.io/badge/MySQL-8.0%2B-orange.svg)](https://www.mysql.com/)
[![Location](https://img.shields.io/badge/Location-Kolkata%2C%20West%20Bengal%2C%20India-blue.svg)]()
[![Session](https://img.shields.io/badge/Academic%20Session-2026--2027-purple.svg)]()
[![License](https://img.shields.io/badge/Academic%20Project-Portfolio%20Demo-teal.svg)]()

A modern full-stack academic **College Electoral Management System** set in Kolkata, West Bengal, India, built with pure native web technologies: **PHP 8.2+ (PDO)**, **MySQL 8+**, **HTML5/CSS3 Glassmorphism**, and **Vanilla JavaScript** with interactive **Chart.js** analytics.

Engineered for academic college governance while implementing database-level transaction row-locking (`SELECT ... FOR UPDATE`), tamper-evident cryptographic receipts, and strict secret ballot protection.

---

## 🌟 Visual & Architectural Highlights

- **Aesthetic**: Refined Dark Yellow & Brown design system featuring deep warm espresso chocolate canvas (`#241B16`), rich coffee secondary surfaces (`#2C211B`), standard roasted walnut cards (`#423329`), elevated walnut surfaces (`#514032`), electric mustard & golden yellow accents (`#F4CB45`, `#DDB63F`), warm cream linen typography (`#F6EEDC`), and Swiss-inspired editorial layout.
- **Native Stack**: Pure vanilla PHP and JavaScript without Node.js, npm builds, React, or heavyweight frameworks.
- **Regional Localization**: Configured for Kolkata, West Bengal, India with Indian Standard Time (`Asia/Kolkata`, IST, UTC+05:30), Indian mobile number validation, 8-semester course registrations (BCA, B.Tech, BBA, B.Com, MCA), and INR (`₹`) currency.
- **Secret Ballot Protection**: The `ballots` database table is mathematically decoupled from student identity. No voter IDs are stored in the ballot records.
- **Duplicate Vote Prevention**: Atomic MySQL database transaction with row-level locking (`FOR UPDATE`) guarantees duplicate votes are prevented even under concurrent conditions.
- **Interactive Visualizations**: Real-time certified result charts using Chart.js (horizontal bar charts, doughnut distribution graphs, and percentage tally sheets).

---

## 🗂️ Project Directory Structure

```text
online-voting-system/
│
├── index.php                 # Asymmetric two-column public landing page with live telemetry
├── login.php                 # Student & admin authentication portal (with 1-click demo fill)
├── register.php              # Student voter self-registration with College Roll No & Univ Reg No
├── about.php                 # Institutional mission & secret ballot protection architecture
├── contact.php               # College Election Committee inquiry form & helpdesk details
├── dashboard.php             # Student voter command center (active polls, stats, quick actions)
├── elections.php             # Directory of live, upcoming, and completed college ballots
├── election-details.php      # Election manifesto viewer & candidate dossiers
├── vote.php                  # Official balloting interface with confirmation modal
├── results.php               # Certified electoral results & Chart.js graph dashboard
├── my-profile.php            # Student identity management & password update
├── voting-history.php        # Immutable participation receipts (ballot choice masked)
├── logout.php                # Secure session termination handler
│
├── config/
│   └── database.php          # Singleton PDO connection, Asia/Kolkata timezone & BASE_URL resolver
│
├── includes/
│   ├── auth.php              # Session hardening, role guards, & CSRF synchronizer
│   ├── functions.php         # Indian phone validator, IST date formatter, audit logger, flash messages
│   ├── header.php            # Unified navigation header (public + dashboard sidebar + demo banner)
│   └── footer.php            # Footer with Kolkata academic institutional notice
│
├── admin/
│   ├── dashboard.php         # Admin overview, metrics, turnout tracking, recent audit log
│   ├── manage-elections.php  # Add, edit, delete, start, end, and sync student eligibility
│   ├── manage-candidates.php # Certify nominees, positions, panels, and manifestos
│   ├── manage-voters.php     # Student voter roll directory, status toggle, and batch eligibility
│   ├── manage-results.php    # Audit certification & 1-click publish/unpublish toggle
│   ├── audit-logs.php        # Tamper-evident security trail with action and user filters
│   └── settings.php          # Platform branding, campus location, demo mode toggle, contact info
│
├── assets/
│   ├── css/
│   │   └── style.css         # Complete SaaS design system, glass cards, badges, modals
│   ├── js/
│   │   ├── main.js           # Toast engine, modal controls, counters, table live search
│   │   └── chart-results.js  # Chart.js color palette & responsive bar/doughnut charts
│   └── images/
│       ├── logo.svg          # Modern glowing ballot box platform crest
│       ├── default-avatar.svg# Default user silhouette
│       └── candidate1-8.svg  # Colorful candidate avatars with Indian student initials (RD, PS, AR, SM, etc.)
│
└── database/
    └── online_voting_system.sql # MySQL database schema & localized seed data
```

---

## 🔑 Default Demo Login Credentials

The database includes realistic sample accounts ready for immediate demonstration:

| Role | Name | Email / Roll Number | Password | Department |
| :--- | :--- | :--- | :--- | :--- |
| **Chief Election Officer (Admin)** | Dr. Subhash Mukherjee | `admin@college.edu` | `Admin@123` | College Election Committee |
| **Student Voter 1** | Sourav Ganguly | `voter1@college.edu` (`BCA/2026/041`) | `Voter@123` | Computer Applications (BCA) |
| **Student Voter 2** | Rituparna Sen | `voter2@college.edu` (`CSE/2026/019`) | `Voter@123` | Computer Science & Engineering (B.Tech CSE) |
| **Student Voter 3** | Aritra Bhattacharya | `voter3@college.edu` (`BBA/2026/012`) | `Voter@123` | Business Administration (BBA) |
| **Student Voter 4** | Koyel Roy | `voter4@college.edu` (`COM/2026/088`) | `Voter@123` | Commerce (B.Com) |

> 💡 **Tip:** On the `login.php` screen, click any of the **Quick Demo Accounts** buttons to auto-populate credentials with one click!

---

## 🗳️ Fictional Indian Demo Candidates & Panels

1. **Rahul Das** – *Progress & Development Panel* (Students' Council President)
2. **Priya Sharma** – *Student Welfare Panel* (Students' Council President)
3. **Arjun Roy** – *Campus Reform Panel* (Students' Council President)
4. **Sneha Mukherjee** – *Cultural Development Panel* (BCA Department Representative)
5. **Aditya Banerjee** – *Academic Excellence Panel* (BCA Class Representative)
6. **Ananya Chatterjee** – *Student Voice Panel* (Cultural Secretary)
7. **Rohan Dutta** – *Campus Innovation Panel* (Sports Secretary)
8. **Ishita Ghosh** – *Green Campus Panel* (Alumni Representative)

---

## 🚀 Local Installation & XAMPP Setup Guide

### Option 1: Running with XAMPP (Apache + MySQL)

1. **Locate or Copy Project Folder**:
   Copy the project folder into your XAMPP web root directory:
   ```text
   C:\xampp\htdocs\online-voting-system
   ```
   *(Or keep it at your current folder location and create an Apache VirtualHost or alias).*

2. **Start Services in XAMPP**:
   - Open **XAMPP Control Panel**.
   - Click **Start** for **Apache**.
   - Click **Start** for **MySQL**.

3. **Import Database via phpMyAdmin**:
   - Open your browser and navigate to: [http://localhost/phpmyadmin/](http://localhost/phpmyadmin/)
   - Click the **Import** tab at the top.
   - Choose the file: `database/online_voting_system.sql`.
   - Click **Import** (or **Go**) at the bottom.
   - The database `online_voting_system` and all 7 tables with seed records will be created automatically.

4. **Launch Application in Browser**:
   Open:
   ```text
   http://localhost/online-voting-system/
   ```
   *(If placed in `C:\xampp\htdocs\voting system`, visit `http://localhost/voting%20system/`).*

---

### Option 2: Running with Built-in PHP Development Server

If PHP and MySQL are already installed on your system:

1. Import the database via MySQL CLI:
   ```powershell
   Get-Content "database\online_voting_system.sql" | mysql -u root
   ```

2. Start the local server inside the project root:
   ```powershell
   php -S localhost:8000
   ```

3. Open your browser and navigate to:
   ```text
   http://localhost:8000/
   ```

---

## 🔒 Security Architecture & Ballot Secrecy

### 1. Cryptographic Ballot Secrecy
In compliance with democratic standards, the application decouples **voter identity** from **voter selection**:
- The `voter_eligibility` table stores `user_id`, `election_id`, `has_voted = 1`, and a unique participation receipt token `receipt_token`.
- The `ballots` table stores **only** `election_id`, `candidate_id`, a cryptographic `vote_hash`, and `cast_at`.
- There is **no foreign key or column linking `ballots` back to `users`**. Even an administrator directly querying the database with SQL cannot trace individual voter picks.

### 2. ACID Row-Level Locking (Anti-Double Voting)
During vote submission in `vote.php`, the transaction locks the eligibility row:
```php
$db->beginTransaction();
$lockStmt = $db->prepare("SELECT id, has_voted FROM voter_eligibility WHERE election_id = ? AND user_id = ? FOR UPDATE");
$lockStmt->execute([$electionId, $user['id']]);
$elig = $lockStmt->fetch();

if (!$elig || (int)$elig['has_voted'] === 1) {
    throw new Exception("Duplicate vote prevented.");
}
// Insert anonymized ballot, update candidate count, mark has_voted = 1
$db->commit();
```

### 3. Application Hardening
- **Bcrypt Password Hashes**: Salted `password_hash()` and `password_verify()`.
- **PDO Prepared Statements**: 100% parameterization guarding against SQL Injection attacks.
- **CSRF Token Synchronizer**: Dynamic HMAC session tokens validating every state-changing POST form.
- **XSS Escaping**: Consistent sanitization via `e()` (`htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`).
- **Session Protection**: `session.use_only_cookies`, `session.use_strict_mode`, and `session_regenerate_id(true)`.

---

## 🧪 Testing Verification Checklist

The following workflows have been fully tested and verified:

- [x] **Voter Registration**: Direct registration with validation, uniqueness checks, and automatic cohort enrollment.
- [x] **Voter Authentication**: Secure login, session regeneration, role identification, and invalid credential blocking.
- [x] **Admin Authentication**: Privileged route protection and direct routing to administrative command center.
- [x] **Election Lifecycle**: Creating, editing, starting, ending, and deleting elections.
- [x] **Candidate Certification**: Adding nominees with photos, slates, ballot numbers, and manifestos.
- [x] **Eligibility Assignment**: Bulk-assigning eligible departments to active ballots.
- [x] **Casting a Secret Vote**: Interactive candidate card selection, confirmation modal preview, and anonymous submission.
- [x] **Anti-Double Vote Prevention**: Strict rejection of repeated vote attempts by the same voter.
- [x] **Results Certification & Publishing**: 1-click toggle for public visibility of certified results.
- [x] **Interactive Analytics**: Chart.js horizontal bar charts, doughnut charts, and winner certification banners.
- [x] **Security Audit Trail**: Immutable logging of all authentication events, election changes, and anonymized ballots.

---

## 📜 Academic Project Notice

> **Disclaimer**: This application was developed as a college academic capstone / portfolio demonstration of full-stack engineering, database transactions, and cryptographic UI design. It is designed for educational demonstrations and is not a government-certified national election instrument.
