<?php
/**
 * Administrator Authorised Student Verification Roster Management
 * Phase 1: Student Identity Verification System
 */

require_once __DIR__ . '/../includes/auth.php';
require_admin();

$pageTitle = 'Authorised Student Roster';
$isDashboard = true;
$isAdmin = true;
$user = current_user();
$db = getDB();

$error = '';
$success = '';

// Handle CSV Sample Download
if (isset($_GET['download_sample']) && $_GET['download_sample'] === 'csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="apexvote_authorized_students_sample.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Full Name', 'College Roll No', 'University Reg No', 'Institutional Email', 'Department', 'Course', 'Semester', 'Academic Session']);
    fputcsv($out, ['Sourav Ganguly', 'BCA/2026/041', '12623010041', 'voter1@college.edu', 'Computer Applications', 'BCA', '6', '2026–2027']);
    fputcsv($out, ['Aniket Mukherjee', 'BCA/2026/042', '12623010042', 'aniket.m@college.edu', 'Computer Applications', 'BCA', '6', '2026–2027']);
    fputcsv($out, ['Priya Dasgupta', 'CSE/2026/020', '12623010020', 'priya.d@college.edu', 'Computer Science & Engineering', 'BTech CSE', '4', '2026–2027']);
    fclose($out);
    exit;
}

// Handle Form Submissions (Add, Edit, Toggle Status, Import CSV)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        $error = 'Security session expired. Please refresh and try again.';
    } else {
        $action = $_POST['action'] ?? '';

        // 1. ADD STUDENT MANUALLY
        if ($action === 'add_student') {
            $fullName = trim($_POST['full_name'] ?? '');
            $rollNo = trim($_POST['college_roll_no'] ?? '');
            $regNo = trim($_POST['university_reg_no'] ?? '');
            $email = trim(filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL));
            $department = trim($_POST['department'] ?? 'Computer Applications');
            $course = trim($_POST['course'] ?? 'BCA');
            $semester = max(1, min(8, (int)($_POST['semester'] ?? 1)));
            $session = trim($_POST['academic_session'] ?? get_setting('academic_year', '2026–2027'));
            $status = in_array($_POST['status'] ?? '', ['active', 'inactive']) ? $_POST['status'] : 'active';

            if (empty($fullName) || empty($rollNo) || empty($regNo) || empty($email)) {
                $error = 'All required fields marked with * must be provided.';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Please enter a valid institutional email address.';
            } else {
                // Check uniqueness in authorized_students
                $chk = $db->prepare("SELECT id, college_roll_no, university_reg_no, email FROM authorized_students WHERE college_roll_no = ? OR university_reg_no = ? OR email = ? LIMIT 1");
                $chk->execute([$rollNo, $regNo, $email]);
                $dup = $chk->fetch();

                if ($dup) {
                    if (strcasecmp($dup['college_roll_no'], $rollNo) === 0) {
                        $error = 'A student with this College Roll Number already exists in the roster.';
                    } elseif (strcasecmp($dup['university_reg_no'], $regNo) === 0) {
                        $error = 'A student with this University Registration Number already exists in the roster.';
                    } else {
                        $error = 'A student with this institutional email already exists in the roster.';
                    }
                } else {
                    $ins = $db->prepare("
                        INSERT INTO authorized_students 
                        (full_name, college_roll_no, university_reg_no, email, department, course, semester, academic_session, status, verification_status, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'unregistered', NOW())
                    ");
                    $ins->execute([$fullName, $rollNo, $regNo, $email, $department, $course, $semester, $session, $status]);
                    $newId = (int)$db->lastInsertId();

                    audit_log('STUDENT_ROSTER_ADDED', "Admin added student to roster: {$fullName} ({$rollNo})", $user['id']);
                    set_flash('success', "Student '{$fullName}' ({$rollNo}) has been added to the authorised roster.");
                    redirect('admin/authorized-students.php');
                }
            }

        // 2. EDIT STUDENT RECORD
        } elseif ($action === 'edit_student') {
            $studentId = (int)($_POST['student_id'] ?? 0);
            $fullName = trim($_POST['full_name'] ?? '');
            $rollNo = trim($_POST['college_roll_no'] ?? '');
            $regNo = trim($_POST['university_reg_no'] ?? '');
            $email = trim(filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL));
            $department = trim($_POST['department'] ?? 'Computer Applications');
            $course = trim($_POST['course'] ?? 'BCA');
            $semester = max(1, min(8, (int)($_POST['semester'] ?? 1)));
            $session = trim($_POST['academic_session'] ?? '2026–2027');
            $status = in_array($_POST['status'] ?? '', ['active', 'inactive']) ? $_POST['status'] : 'active';

            if ($studentId <= 0 || empty($fullName) || empty($rollNo) || empty($regNo) || empty($email)) {
                $error = 'All required fields must be filled for editing.';
            } else {
                // Check uniqueness excluding current record
                $chk = $db->prepare("SELECT id FROM authorized_students WHERE (college_roll_no = ? OR university_reg_no = ? OR email = ?) AND id != ? LIMIT 1");
                $chk->execute([$rollNo, $regNo, $email, $studentId]);
                if ($chk->fetch()) {
                    $error = 'Another student in the roster already uses this Roll No, Reg No, or Email.';
                } else {
                    $upd = $db->prepare("
                        UPDATE authorized_students 
                        SET full_name = ?, college_roll_no = ?, university_reg_no = ?, email = ?, department = ?, course = ?, semester = ?, academic_session = ?, status = ?
                        WHERE id = ?
                    ");
                    $upd->execute([$fullName, $rollNo, $regNo, $email, $department, $course, $semester, $session, $status, $studentId]);

                    audit_log('STUDENT_ROSTER_EDITED', "Admin modified roster record #{$studentId}: {$fullName}", $user['id']);
                    set_flash('success', "Roster record for '{$fullName}' updated successfully.");
                    redirect('admin/authorized-students.php');
                }
            }

        // 3. TOGGLE ACTIVE / INACTIVE STATUS
        } elseif ($action === 'toggle_status') {
            $studentId = (int)($_POST['student_id'] ?? 0);
            $currentStatus = $_POST['current_status'] ?? 'active';
            $newStatus = ($currentStatus === 'active') ? 'inactive' : 'active';

            if ($studentId > 0) {
                $stmt = $db->prepare("UPDATE authorized_students SET status = ? WHERE id = ?");
                $stmt->execute([$newStatus, $studentId]);

                audit_log('STUDENT_ROSTER_STATUS_CHANGED', "Changed roster student #{$studentId} status to {$newStatus}", $user['id']);
                set_flash('success', "Student record status updated to " . strtoupper($newStatus));
                redirect('admin/authorized-students.php');
            }

        // 4. BULK CSV IMPORT
        } elseif ($action === 'import_csv') {
            if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
                $error = 'Please select a valid CSV file to upload.';
            } else {
                $filePath = $_FILES['csv_file']['tmp_name'];
                $handle = fopen($filePath, 'r');
                if (!$handle) {
                    $error = 'Could not open uploaded CSV file.';
                } else {
                    $header = fgetcsv($handle);
                    if (!$header) {
                        $error = 'Uploaded CSV file appears to be empty.';
                    } else {
                        // Normalize headers
                        $normHeader = array_map(fn($h) => strtolower(trim(preg_replace('/[^a-zA-Z0-9]/', '', $h))), $header);
                        
                        $idxName = array_search('fullname', $normHeader) !== false ? array_search('fullname', $normHeader) : array_search('name', $normHeader);
                        $idxRoll = array_search('collegerollno', $normHeader) !== false ? array_search('collegerollno', $normHeader) : array_search('rollno', $normHeader);
                        $idxReg = array_search('universityregno', $normHeader) !== false ? array_search('universityregno', $normHeader) : array_search('regno', $normHeader);
                        $idxEmail = array_search('institutionalemail', $normHeader) !== false ? array_search('institutionalemail', $normHeader) : array_search('email', $normHeader);
                        $idxDept = array_search('department', $normHeader);
                        $idxCourse = array_search('course', $normHeader);
                        $idxSem = array_search('semester', $normHeader);
                        $idxSession = array_search('academicsession', $normHeader) !== false ? array_search('academicsession', $normHeader) : array_search('session', $normHeader);

                        if ($idxName === false || $idxRoll === false || $idxReg === false || $idxEmail === false) {
                            $error = 'CSV header missing required columns. Ensure headers include: Full Name, College Roll No, University Reg No, Institutional Email.';
                        } else {
                            $importedCount = 0;
                            $updatedCount = 0;
                            $skippedCount = 0;
                            $rowNum = 1;

                            $stmtInsert = $db->prepare("
                                INSERT INTO authorized_students 
                                (full_name, college_roll_no, university_reg_no, email, department, course, semester, academic_session, status, verification_status, created_at)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active', 'unregistered', NOW())
                                ON DUPLICATE KEY UPDATE
                                full_name = VALUES(full_name),
                                university_reg_no = VALUES(university_reg_no),
                                email = VALUES(email),
                                department = VALUES(department),
                                course = VALUES(course),
                                semester = VALUES(semester),
                                academic_session = VALUES(academic_session)
                            ");

                            while (($row = fgetcsv($handle)) !== false) {
                                $rowNum++;
                                $name = trim($row[$idxName] ?? '');
                                $roll = trim($row[$idxRoll] ?? '');
                                $reg = trim($row[$idxReg] ?? '');
                                $email = trim(filter_var($row[$idxEmail] ?? '', FILTER_SANITIZE_EMAIL));
                                $dept = ($idxDept !== false && !empty($row[$idxDept])) ? trim($row[$idxDept]) : 'Computer Applications';
                                $course = ($idxCourse !== false && !empty($row[$idxCourse])) ? trim($row[$idxCourse]) : 'BCA';
                                $sem = ($idxSem !== false && !empty($row[$idxSem])) ? max(1, min(8, (int)$row[$idxSem])) : 1;
                                $session = ($idxSession !== false && !empty($row[$idxSession])) ? trim($row[$idxSession]) : '2026–2027';

                                if (empty($name) || empty($roll) || empty($reg) || empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                                    $skippedCount++;
                                    continue;
                                }

                                try {
                                    $stmtInsert->execute([$name, $roll, $reg, $email, $dept, $course, $sem, $session]);
                                    $importedCount++;
                                } catch (Exception $e) {
                                    $skippedCount++;
                                }
                            }
                            fclose($handle);

                            audit_log('CSV_ROSTER_IMPORTED', "Admin imported {$importedCount} student records from CSV", $user['id']);
                            set_flash('success', "CSV Import Complete: Successfully processed {$importedCount} student records ({$skippedCount} invalid/skipped).");
                            redirect('admin/authorized-students.php');
                        }
                    }
                }
            }
        }
    }
}

// Search and Filter Handling
$searchQuery = trim($_GET['search'] ?? '');
$filterDept = trim($_GET['department'] ?? 'all');
$filterStatus = trim($_GET['status'] ?? 'all');
$filterVerif = trim($_GET['verification_status'] ?? 'all');

$whereClauses = [];
$params = [];

if (!empty($searchQuery)) {
    $whereClauses[] = "(full_name LIKE ? OR college_roll_no LIKE ? OR university_reg_no LIKE ? OR email LIKE ?)";
    $like = "%{$searchQuery}%";
    $params = array_merge($params, [$like, $like, $like, $like]);
}

if ($filterDept !== 'all' && !empty($filterDept)) {
    $whereClauses[] = "department = ?";
    $params[] = $filterDept;
}

if ($filterStatus !== 'all' && in_array($filterStatus, ['active', 'inactive'])) {
    $whereClauses[] = "status = ?";
    $params[] = $filterStatus;
}

if ($filterVerif !== 'all' && in_array($filterVerif, ['unregistered', 'verified'])) {
    $whereClauses[] = "verification_status = ?";
    $params[] = $filterVerif;
}

$whereSQL = !empty($whereClauses) ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

// Stats
$totalStudents = (int)$db->query("SELECT COUNT(*) FROM authorized_students")->fetchColumn();
$activeStudents = (int)$db->query("SELECT COUNT(*) FROM authorized_students WHERE status = 'active'")->fetchColumn();
$verifiedStudents = (int)$db->query("SELECT COUNT(*) FROM authorized_students WHERE verification_status = 'verified'")->fetchColumn();
$unregisteredStudents = (int)$db->query("SELECT COUNT(*) FROM authorized_students WHERE verification_status = 'unregistered'")->fetchColumn();

// Fetch Roster Records with pagination
$recordsPerPage = 20;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $recordsPerPage;

$countStmt = $db->prepare("SELECT COUNT(*) FROM authorized_students {$whereSQL}");
$countStmt->execute($params);
$totalFiltered = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalFiltered / $recordsPerPage));

$dataStmt = $db->prepare("
    SELECT a.*, u.id as registered_uid, u.status as user_account_status
    FROM authorized_students a
    LEFT JOIN users u ON a.registered_user_id = u.id
    {$whereSQL}
    ORDER BY a.id DESC
    LIMIT {$recordsPerPage} OFFSET {$offset}
");
$dataStmt->execute($params);
$students = $dataStmt->fetchAll();

// Distinct Departments for filter
$depts = $db->query("SELECT DISTINCT department FROM authorized_students ORDER BY department ASC")->fetchAll(PDO::FETCH_COLUMN);

$flashMsg = get_flash();

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Header -->
<div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
    <div>
        <div style="font-size: 0.75rem; text-transform: uppercase; color: var(--accent-primary); font-weight: 700; letter-spacing: 0.08em; margin-bottom: 0.35rem;">
            Collegiate Registrar Roster &bull; Phase 1 Identity Verification
        </div>
        <h2 style="margin: 0; font-size: 1.75rem; color: var(--text-primary); font-weight: 800;">Authorised Student Roster</h2>
        <p style="margin: 0.4rem 0 0; color: var(--text-secondary); font-size: 0.92rem;">
            Official college verification database. Students must match this authoritative registry during self-registration.
        </p>
    </div>
    <div style="display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap;">
        <a href="<?= url('admin/authorized-students.php?download_sample=csv') ?>" class="btn btn-secondary btn-sm" title="Download sample CSV template">
            📥 Download Sample CSV
        </a>
        <button type="button" class="btn btn-secondary btn-sm" onclick="document.getElementById('modal-import-csv').style.display='flex'">
            📁 Import CSV Roster
        </button>
        <button type="button" class="btn btn-primary btn-sm" onclick="openAddStudentModal()">
            ➕ Add Student Record
        </button>
    </div>
</div>

<?php if ($flashMsg): ?>
    <div class="alert-box alert-<?= e($flashMsg['type'] ?? 'info') ?>" style="margin-bottom: 1.5rem;">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
        <span><?= e($flashMsg['message']) ?></span>
    </div>
<?php endif; ?>

<?php if (!empty($error)): ?>
    <div class="alert-box alert-danger" style="margin-bottom: 1.5rem;">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
        <span><?= e($error) ?></span>
    </div>
<?php endif; ?>

<!-- Summary Stat Cards -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 1.25rem; margin-bottom: 2rem;">
    <div class="glass-card" style="padding: 1.25rem; background: var(--surface-card); border: 1px solid var(--border-subtle); border-radius: var(--radius-lg);">
        <div style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700; margin-bottom: 0.4rem;">Total Authorised Students</div>
        <div style="font-size: 1.85rem; font-weight: 800; color: var(--text-primary);"><?= $totalStudents ?></div>
        <div style="font-size: 0.78rem; color: var(--accent-primary); margin-top: 0.25rem;">Official Registrar Count</div>
    </div>
    <div class="glass-card" style="padding: 1.25rem; background: var(--surface-card); border: 1px solid var(--border-subtle); border-radius: var(--radius-lg);">
        <div style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700; margin-bottom: 0.4rem;">Active Eligible Roster</div>
        <div style="font-size: 1.85rem; font-weight: 800; color: var(--success);"><?= $activeStudents ?></div>
        <div style="font-size: 0.78rem; color: var(--text-secondary); margin-top: 0.25rem;">Permitted to register & vote</div>
    </div>
    <div class="glass-card" style="padding: 1.25rem; background: var(--surface-card); border: 1px solid var(--border-subtle); border-radius: var(--radius-lg);">
        <div style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700; margin-bottom: 0.4rem;">Registered & Verified</div>
        <div style="font-size: 1.85rem; font-weight: 800; color: var(--accent-primary);"><?= $verifiedStudents ?></div>
        <div style="font-size: 0.78rem; color: var(--text-secondary); margin-top: 0.25rem;">Account verified & linked</div>
    </div>
    <div class="glass-card" style="padding: 1.25rem; background: var(--surface-card); border: 1px solid var(--border-subtle); border-radius: var(--radius-lg);">
        <div style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700; margin-bottom: 0.4rem;">Pending Registration</div>
        <div style="font-size: 1.85rem; font-weight: 800; color: #E8A838;"><?= $unregisteredStudents ?></div>
        <div style="font-size: 0.78rem; color: var(--text-secondary); margin-top: 0.25rem;">Awaiting voter sign-up</div>
    </div>
</div>

<!-- Search & Filter Controls -->
<div class="glass-card" style="padding: 1.25rem; background: var(--surface-card); border: 1px solid var(--border-subtle); border-radius: var(--radius-lg); margin-bottom: 2rem;">
    <form action="<?= url('admin/authorized-students.php') ?>" method="GET" style="display: grid; grid-template-columns: 2fr 1.2fr 1fr 1fr auto; gap: 1rem; align-items: end;">
        <div class="form-group" style="margin-bottom: 0;">
            <label class="form-label" for="search" style="font-size: 0.78rem;">Search Student Roster</label>
            <input type="text" name="search" id="search" class="form-control" placeholder="Search by name, roll no, reg no, email..." value="<?= e($searchQuery) ?>">
        </div>

        <div class="form-group" style="margin-bottom: 0;">
            <label class="form-label" for="department" style="font-size: 0.78rem;">Department</label>
            <select name="department" id="department" class="form-select">
                <option value="all">All Departments</option>
                <?php foreach ($depts as $d): ?>
                    <option value="<?= e($d) ?>" <?= ($filterDept === $d) ? 'selected' : '' ?>><?= e($d) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group" style="margin-bottom: 0;">
            <label class="form-label" for="status" style="font-size: 0.78rem;">Account Status</label>
            <select name="status" id="status" class="form-select">
                <option value="all" <?= ($filterStatus === 'all') ? 'selected' : '' ?>>All Status</option>
                <option value="active" <?= ($filterStatus === 'active') ? 'selected' : '' ?>>Active Only</option>
                <option value="inactive" <?= ($filterStatus === 'inactive') ? 'selected' : '' ?>>Inactive Only</option>
            </select>
        </div>

        <div class="form-group" style="margin-bottom: 0;">
            <label class="form-label" for="verification_status" style="font-size: 0.78rem;">Verification</label>
            <select name="verification_status" id="verification_status" class="form-select">
                <option value="all" <?= ($filterVerif === 'all') ? 'selected' : '' ?>>All Students</option>
                <option value="verified" <?= ($filterVerif === 'verified') ? 'selected' : '' ?>>Verified Only</option>
                <option value="unregistered" <?= ($filterVerif === 'unregistered') ? 'selected' : '' ?>>Unregistered</option>
            </select>
        </div>

        <div style="display: flex; gap: 0.5rem;">
            <button type="submit" class="btn btn-primary" style="height: 42px;">Filter</button>
            <?php if (!empty($searchQuery) || $filterDept !== 'all' || $filterStatus !== 'all' || $filterVerif !== 'all'): ?>
                <a href="<?= url('admin/authorized-students.php') ?>" class="btn btn-secondary" style="height: 42px; display: inline-flex; align-items: center;" title="Reset filters">✕</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Student Roster Table -->
<div class="glass-card" style="padding: 1.5rem; background: var(--surface-card); border: 1px solid var(--border-subtle); border-radius: var(--radius-lg); overflow-x: auto;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
        <span style="font-size: 0.85rem; color: var(--text-muted);">
            Showing <?= count($students) ?> of <?= $totalFiltered ?> matching student records
        </span>
    </div>

    <table class="receipt-table" style="width: 100%; border-collapse: collapse;">
        <thead>
            <tr style="border-bottom: 2px solid var(--border-subtle); text-align: left;">
                <th style="padding: 0.85rem 0.75rem; font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted);">Student Details</th>
                <th style="padding: 0.85rem 0.75rem; font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted);">Roll & Reg. No</th>
                <th style="padding: 0.85rem 0.75rem; font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted);">Academic Profile</th>
                <th style="padding: 0.85rem 0.75rem; font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted);">Status</th>
                <th style="padding: 0.85rem 0.75rem; font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted);">Verification</th>
                <th style="padding: 0.85rem 0.75rem; font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); text-align: right;">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($students)): ?>
                <tr>
                    <td colspan="6" style="padding: 3rem; text-align: center; color: var(--text-muted);">
                        No authorised students match your search criteria.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($students as $s): ?>
                    <tr style="border-bottom: 1px solid rgba(255,255,255,0.06);">
                        <td style="padding: 0.85rem 0.75rem;">
                            <div style="font-weight: 700; color: var(--text-primary); font-size: 0.95rem;">
                                <?= e($s['full_name']) ?>
                            </div>
                            <div style="font-size: 0.78rem; color: var(--text-muted);">
                                <?= e($s['email']) ?>
                            </div>
                        </td>
                        <td style="padding: 0.85rem 0.75rem;">
                            <div style="font-family: 'Consolas', monospace; color: var(--accent-primary); font-weight: 600; font-size: 0.85rem;">
                                <?= e($s['college_roll_no']) ?>
                            </div>
                            <div style="font-size: 0.78rem; color: var(--text-muted);">
                                Reg: <?= e($s['university_reg_no']) ?>
                            </div>
                        </td>
                        <td style="padding: 0.85rem 0.75rem;">
                            <div style="font-size: 0.85rem; color: var(--text-primary); font-weight: 600;">
                                <?= e($s['department']) ?>
                            </div>
                            <div style="font-size: 0.76rem; color: var(--text-muted);">
                                <?= e($s['course']) ?> &bull; Sem <?= (int)$s['semester'] ?> (<?= e($s['academic_session']) ?>)
                            </div>
                        </td>
                        <td style="padding: 0.85rem 0.75rem;">
                            <?php if ($s['status'] === 'active'): ?>
                                <span class="status-badge badge-emerald" style="font-size: 0.72rem;">Active</span>
                            <?php else: ?>
                                <span class="status-badge badge-danger" style="font-size: 0.72rem;">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 0.85rem 0.75rem;">
                            <?php if ($s['verification_status'] === 'verified' && !empty($s['registered_user_id'])): ?>
                                <span class="status-badge badge-emerald" style="font-size: 0.72rem;">
                                    ✓ Registered (#<?= (int)$s['registered_user_id'] ?>)
                                </span>
                            <?php else: ?>
                                <span class="status-badge badge-slate" style="font-size: 0.72rem;">
                                    ○ Unregistered
                                </span>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 0.85rem 0.75rem; text-align: right;">
                            <div style="display: inline-flex; gap: 0.4rem;">
                                <button type="button" class="btn btn-secondary btn-xs" 
                                        onclick="openEditModal(<?= htmlspecialchars(json_encode($s), ENT_QUOTES, 'UTF-8') ?>)" 
                                        title="Edit Student Record">
                                    ✏️ Edit
                                </button>
                                <form action="<?= url('admin/authorized-students.php') ?>" method="POST" style="display: inline;" 
                                      onsubmit="return confirm('Change status for student <?= e($s['full_name']) ?> to <?= $s['status'] === 'active' ? 'INACTIVE' : 'ACTIVE' ?>?');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="toggle_status">
                                    <input type="hidden" name="student_id" value="<?= (int)$s['id'] ?>">
                                    <input type="hidden" name="current_status" value="<?= e($s['status']) ?>">
                                    <button type="submit" class="btn btn-secondary btn-xs" title="Toggle Status">
                                        <?= $s['status'] === 'active' ? 'Deactivate' : 'Activate' ?>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
        <div style="display: flex; justify-content: center; gap: 0.5rem; margin-top: 1.5rem;">
            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                <a href="<?= url('admin/authorized-students.php?page=' . $p . '&search=' . urlencode($searchQuery) . '&department=' . urlencode($filterDept) . '&status=' . urlencode($filterStatus) . '&verification_status=' . urlencode($filterVerif)) ?>" 
                   class="btn <?= ($p === $page) ? 'btn-primary' : 'btn-secondary' ?> btn-xs">
                    <?= $p ?>
                </a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
</div>

<!-- ===================================================================== -->
<!-- MODAL: ADD STUDENT MANUALLY                                          -->
<!-- ===================================================================== -->
<div id="modal-add-student" class="modal-overlay" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.7); z-index: 1050; align-items: center; justify-content: center; padding: 1.5rem;">
    <div class="glass-card" style="width: 100%; max-width: 600px; max-height: 90vh; overflow-y: auto; padding: 2rem; background: var(--surface-card); border: 1px solid var(--border-subtle); border-radius: var(--radius-lg);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <h3 style="margin: 0; color: var(--text-primary); font-size: 1.25rem;">➕ Add Authorised Student to Roster</h3>
            <button type="button" onclick="document.getElementById('modal-add-student').style.display='none'" style="background: none; border: none; color: var(--text-muted); font-size: 1.5rem; cursor: pointer;">&times;</button>
        </div>

        <form action="<?= url('admin/authorized-students.php') ?>" method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add_student">

            <div class="form-group">
                <label class="form-label" for="add_full_name">Full Legal Name *</label>
                <input type="text" name="full_name" id="add_full_name" class="form-control" placeholder="e.g. Aniket Mukherjee" required>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                    <label class="form-label" for="add_college_roll_no">College Roll Number *</label>
                    <input type="text" name="college_roll_no" id="add_college_roll_no" class="form-control" placeholder="e.g. BCA/2026/042" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="add_university_reg_no">University Reg. No. *</label>
                    <input type="text" name="university_reg_no" id="add_university_reg_no" class="form-control" placeholder="e.g. 12623010042" required>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="add_email">Institutional Email Address *</label>
                <input type="email" name="email" id="add_email" class="form-control" placeholder="student@college.edu" required>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                    <label class="form-label" for="add_department">Department *</label>
                    <select name="department" id="add_department" class="form-select" required>
                        <option value="Computer Applications">Computer Applications</option>
                        <option value="Computer Science & Engineering">Computer Science & Engineering</option>
                        <option value="Information Technology">Information Technology</option>
                        <option value="Business Administration">Business Administration</option>
                        <option value="Commerce">Commerce</option>
                        <option value="Electronics & Communication">Electronics & Communication</option>
                        <option value="Basic Sciences & Humanities">Basic Sciences & Humanities</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="add_course">Course *</label>
                    <input type="text" name="course" id="add_course" class="form-control" value="BCA" required>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                    <label class="form-label" for="add_semester">Semester *</label>
                    <input type="number" name="semester" id="add_semester" class="form-control" min="1" max="8" value="1" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="add_academic_session">Academic Session</label>
                    <input type="text" name="academic_session" id="add_academic_session" class="form-control" value="2026–2027" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="add_status">Status</label>
                    <select name="status" id="add_status" class="form-select">
                        <option value="active" selected>Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
            </div>

            <div style="display: flex; gap: 0.75rem; justify-content: flex-end; margin-top: 1.5rem;">
                <button type="button" onclick="document.getElementById('modal-add-student').style.display='none'" class="btn btn-secondary">Cancel</button>
                <button type="submit" class="btn btn-primary">Save to Student Roster</button>
            </div>
        </form>
    </div>
</div>

<!-- ===================================================================== -->
<!-- MODAL: EDIT STUDENT RECORD                                            -->
<!-- ===================================================================== -->
<div id="modal-edit-student" class="modal-overlay" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.7); z-index: 1050; align-items: center; justify-content: center; padding: 1.5rem;">
    <div class="glass-card" style="width: 100%; max-width: 600px; max-height: 90vh; overflow-y: auto; padding: 2rem; background: var(--surface-card); border: 1px solid var(--border-subtle); border-radius: var(--radius-lg);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <h3 style="margin: 0; color: var(--text-primary); font-size: 1.25rem;">✏️ Edit Authorised Student Record</h3>
            <button type="button" onclick="document.getElementById('modal-edit-student').style.display='none'" style="background: none; border: none; color: var(--text-muted); font-size: 1.5rem; cursor: pointer;">&times;</button>
        </div>

        <form action="<?= url('admin/authorized-students.php') ?>" method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="edit_student">
            <input type="hidden" name="student_id" id="edit_student_id" value="">

            <div class="form-group">
                <label class="form-label" for="edit_full_name">Full Legal Name *</label>
                <input type="text" name="full_name" id="edit_full_name" class="form-control" required>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                    <label class="form-label" for="edit_college_roll_no">College Roll Number *</label>
                    <input type="text" name="college_roll_no" id="edit_college_roll_no" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="edit_university_reg_no">University Reg. No. *</label>
                    <input type="text" name="university_reg_no" id="edit_university_reg_no" class="form-control" required>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="edit_email">Institutional Email Address *</label>
                <input type="email" name="email" id="edit_email" class="form-control" required>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                    <label class="form-label" for="edit_department">Department *</label>
                    <input type="text" name="department" id="edit_department" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="edit_course">Course *</label>
                    <input type="text" name="course" id="edit_course" class="form-control" required>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                    <label class="form-label" for="edit_semester">Semester *</label>
                    <input type="number" name="semester" id="edit_semester" class="form-control" min="1" max="8" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="edit_academic_session">Academic Session</label>
                    <input type="text" name="academic_session" id="edit_academic_session" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="edit_status">Status</label>
                    <select name="status" id="edit_status" class="form-select">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
            </div>

            <div style="display: flex; gap: 0.75rem; justify-content: flex-end; margin-top: 1.5rem;">
                <button type="button" onclick="document.getElementById('modal-edit-student').style.display='none'" class="btn btn-secondary">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Student Record</button>
            </div>
        </form>
    </div>
</div>

<!-- ===================================================================== -->
<!-- MODAL: IMPORT ROSTER VIA CSV                                          -->
<!-- ===================================================================== -->
<div id="modal-import-csv" class="modal-overlay" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.7); z-index: 1050; align-items: center; justify-content: center; padding: 1.5rem;">
    <div class="glass-card" style="width: 100%; max-width: 540px; padding: 2rem; background: var(--surface-card); border: 1px solid var(--border-subtle); border-radius: var(--radius-lg);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <h3 style="margin: 0; color: var(--text-primary); font-size: 1.25rem;">📁 Bulk Import Student Roster (CSV)</h3>
            <button type="button" onclick="document.getElementById('modal-import-csv').style.display='none'" style="background: none; border: none; color: var(--text-muted); font-size: 1.5rem; cursor: pointer;">&times;</button>
        </div>

        <p style="font-size: 0.88rem; color: var(--text-secondary); line-height: 1.5; margin-bottom: 1.25rem;">
            Upload an official CSV document exported from your college ERP or registrar database. Existing records with matching roll numbers will be automatically updated.
        </p>

        <div style="background: rgba(244, 203, 69, 0.08); border: 1px dashed rgba(244, 203, 69, 0.35); border-radius: var(--radius-md); padding: 0.85rem; margin-bottom: 1.5rem; font-size: 0.8rem; color: var(--text-cream);">
            <strong>Required CSV Columns:</strong><br>
            <code>Full Name, College Roll No, University Reg No, Institutional Email, Department, Course, Semester, Academic Session</code>
        </div>

        <form action="<?= url('admin/authorized-students.php') ?>" method="POST" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="import_csv">

            <div class="form-group">
                <label class="form-label" for="csv_file">Select CSV File (.csv) *</label>
                <input type="file" name="csv_file" id="csv_file" class="form-control" accept=".csv,text/csv" required>
            </div>

            <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 1.5rem;">
                <a href="<?= url('admin/authorized-students.php?download_sample=csv') ?>" style="font-size: 0.82rem; color: var(--accent-primary);">
                    📥 Download CSV Template
                </a>
                <div style="display: flex; gap: 0.5rem;">
                    <button type="button" onclick="document.getElementById('modal-import-csv').style.display='none'" class="btn btn-secondary">Cancel</button>
                    <button type="submit" class="btn btn-primary">Start Import</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
function openAddStudentModal() {
    document.getElementById('modal-add-student').style.display = 'flex';
}

function openEditModal(student) {
    document.getElementById('edit_student_id').value = student.id;
    document.getElementById('edit_full_name').value = student.full_name;
    document.getElementById('edit_college_roll_no').value = student.college_roll_no;
    document.getElementById('edit_university_reg_no').value = student.university_reg_no;
    document.getElementById('edit_email').value = student.email;
    document.getElementById('edit_department').value = student.department;
    document.getElementById('edit_course').value = student.course;
    document.getElementById('edit_semester').value = student.semester;
    document.getElementById('edit_academic_session').value = student.academic_session;
    document.getElementById('edit_status').value = student.status;
    document.getElementById('modal-edit-student').style.display = 'flex';
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
