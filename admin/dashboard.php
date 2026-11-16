<?php
/**
 * Electoral Administration Command Center
 */

require_once __DIR__ . '/../includes/auth.php';
require_admin();

$pageTitle = 'Administrator Command Hub';
$isDashboard = true;
$isAdmin = true;
$user = current_user();
$db = getDB();

// 1. Core Platform Metrics
$totalVoters = (int)$db->query("SELECT COUNT(*) FROM users WHERE role = 'voter'")->fetchColumn();
$activeVoters = (int)$db->query("SELECT COUNT(*) FROM users WHERE role = 'voter' AND status = 'active'")->fetchColumn();
$totalElections = (int)$db->query("SELECT COUNT(*) FROM elections")->fetchColumn();
$activeElections = (int)$db->query("SELECT COUNT(*) FROM elections WHERE status = 'active'")->fetchColumn();
$totalBallots = (int)$db->query("SELECT COUNT(*) FROM ballots")->fetchColumn();
$totalCandidates = (int)$db->query("SELECT COUNT(*) FROM candidates")->fetchColumn();

// 2. Active Elections Overview
$stmt = $db->query("
    SELECT e.*, 
           COUNT(DISTINCT c.id) as candidate_count,
           COUNT(DISTINCT ve.id) as eligible_voter_count,
           SUM(CASE WHEN ve.has_voted = 1 THEN 1 ELSE 0 END) as votes_cast
    FROM elections e
    LEFT JOIN candidates c ON e.id = c.election_id
    LEFT JOIN voter_eligibility ve ON e.id = ve.election_id
    GROUP BY e.id
    ORDER BY FIELD(e.status, 'active', 'draft', 'completed', 'cancelled'), e.end_time ASC
    LIMIT 6
");
$recentElections = $stmt->fetchAll();

// 3. Recent Security Audit Log Stream
$logStmt = $db->query("
    SELECT a.*, u.full_name, u.email 
    FROM audit_logs a
    LEFT JOIN users u ON a.user_id = u.id
    ORDER BY a.created_at DESC
    LIMIT 6
");
$recentLogs = $logStmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Welcome & Fast Action Bar -->
<div class="glass-card" style="margin-bottom: 2rem; background: #423329; border: 1px solid #5A4738;">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1.5rem;">
        <div>
            <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.4rem;">
                <h2 style="font-size: 1.75rem;">College Election Control Panel</h2>
                <span class="status-badge badge-category">Chief Election Officer</span>
            </div>
            <p style="font-size: 0.92rem; color: var(--text-secondary);">
                Logged in as <strong><?= e($user['full_name']) ?></strong> &bull; <?= e(get_setting('institution_name', 'ApexVote College Demonstration Campus')) ?> &bull; <?= e(get_setting('institution_location', 'Kolkata, West Bengal, India')) ?>
            </p>
        </div>

        <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
            <a href="<?= url('admin/manage-elections.php?action=new') ?>" class="btn btn-primary">
                + Create Election
            </a>
            <a href="<?= url('admin/manage-candidates.php?action=new') ?>" class="btn btn-secondary">
                + Add Candidate
            </a>
            <a href="<?= url('admin/manage-voters.php?action=new') ?>" class="btn btn-secondary">
                + Enroll Student
            </a>
            <a href="<?= url('admin/manage-results.php') ?>" class="btn btn-emerald">
                Publish Results 🏆
            </a>
        </div>
    </div>
</div>

<!-- Metrics Overview Grid -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon" style="background: rgba(244, 203, 69, 0.15); color: #F4CB45;">🪪</div>
        <div>
            <div class="stat-val stat-counter" data-target="<?= $totalVoters ?>"><?= $totalVoters ?></div>
            <div class="stat-label">Enrolled Students (<?= $activeVoters ?> Active)</div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon" style="background: rgba(56, 185, 130, 0.15); color: #38B982;">🗳️</div>
        <div>
            <div class="stat-val stat-counter" data-target="<?= $activeElections ?>"><?= $activeElections ?></div>
            <div class="stat-label">Active Elections Live (<?= $totalElections ?> Total)</div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon" style="background: rgba(221, 182, 63, 0.15); color: #DDB63F;">⚡</div>
        <div>
            <div class="stat-val stat-counter" data-target="<?= $totalBallots ?>"><?= $totalBallots ?></div>
            <div class="stat-label">Secret Votes Recorded</div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon" style="background: rgba(232, 184, 74, 0.15); color: #E8B84A;">👥</div>
        <div>
            <div class="stat-val stat-counter" data-target="<?= $totalCandidates ?>"><?= $totalCandidates ?></div>
            <div class="stat-label">Certified Candidates</div>
        </div>
    </div>
</div>

<!-- Elections Quick Management Table -->
<div class="glass-card" style="padding: 0; overflow: hidden; margin-bottom: 2.5rem;">
    <div style="padding: 1.5rem 1.75rem; border-bottom: 1px solid var(--border-subtle); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h4>Collegiate Elections Status</h4>
            <p style="font-size: 0.85rem; margin-top: 0.2rem;">Monitor real-time participation turnout and polling lifecycles.</p>
        </div>
        <a href="<?= url('admin/manage-elections.php') ?>" class="btn btn-secondary btn-sm">Manage All Elections &rarr;</a>
    </div>

    <div class="table-responsive">
        <table class="custom-table">
            <thead>
                <tr>
                    <th>Election Title</th>
                    <th>Category</th>
                    <th>Turnout / Votes</th>
                    <th>Candidates</th>
                    <th>Timing Window</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recentElections)): ?>
                    <tr><td colspan="7" style="text-align:center;padding:2rem;">No elections created yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($recentElections as $eRow): ?>
                        <?php 
                            $eligible = (int)$eRow['eligible_voter_count'];
                            $voted = (int)$eRow['votes_cast'];
                            $pct = ($eligible > 0) ? round(($voted / $eligible) * 100, 1) : 0;
                        ?>
                        <tr>
                            <td>
                                <strong style="color: var(--text-primary); display: block; font-size: 0.95rem;"><?= e($eRow['title']) ?></strong>
                                <span style="font-size: 0.78rem; color: var(--text-muted);">
                                    Scope: <?= e($eRow['eligibility_type'] === 'all' ? 'All Voters' : 'Dept: ' . $eRow['target_department']) ?>
                                </span>
                            </td>
                            <td><span class="status-badge badge-blue"><?= e($eRow['category']) ?></span></td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 0.5rem;">
                                    <strong style="color: var(--color-text); font-size: 0.95rem;"><?= $voted ?> / <?= $eligible ?></strong>
                                    <span style="font-size: 0.8rem; color: var(--accent-cyan);">(<?= $pct ?>%)</span>
                                </div>
                            </td>
                            <td>
                                <span style="font-weight: 600;"><?= (int)$eRow['candidate_count'] ?></span>
                            </td>
                            <td>
                                <span style="font-size: 0.85rem;"><?= format_date($eRow['end_time'], 'M d, Y') ?></span>
                                <span style="display: block; font-size: 0.75rem; color: var(--text-muted);"><?= time_ago($eRow['end_time']) ?></span>
                            </td>
                            <td><?= election_status_badge($eRow['status']) ?></td>
                            <td>
                                <div style="display: flex; gap: 0.4rem;">
                                    <a href="<?= url('admin/manage-candidates.php?election_id=' . $eRow['id']) ?>" class="btn btn-secondary btn-sm" title="Manage Candidates">
                                        👥
                                    </a>
                                    <a href="<?= url('admin/manage-elections.php?edit=' . $eRow['id']) ?>" class="btn btn-secondary btn-sm" title="Edit Election">
                                        ✏️
                                    </a>
                                    <?php if ($eRow['results_published'] == 1): ?>
                                        <a href="<?= url('results.php?election_id=' . $eRow['id']) ?>" class="btn btn-emerald btn-sm" title="View Results">
                                            📊
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Recent Security Audit Log Stream -->
<div class="glass-card" style="padding: 0; overflow: hidden;">
    <div style="padding: 1.5rem 1.75rem; border-bottom: 1px solid var(--border-subtle); display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h4>Live System Activity &amp; Audit Log</h4>
            <p style="font-size: 0.85rem; margin-top: 0.2rem;">All administrative changes, ballots, and user authorizations recorded in audit logs.</p>
        </div>
        <a href="<?= url('admin/audit-logs.php') ?>" class="btn btn-secondary btn-sm">Full Security Audit &rarr;</a>
    </div>

    <div class="table-responsive">
        <table class="custom-table">
            <thead>
                <tr>
                    <th>Timestamp</th>
                    <th>Action</th>
                    <th>User / Identity</th>
                    <th>IP Address</th>
                    <th>Details</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentLogs as $log): ?>
                    <tr>
                        <td style="font-size: 0.85rem; color: var(--text-muted);">
                            <?= format_date($log['created_at']) ?>
                        </td>
                        <td>
                            <code style="color: var(--color-primary); font-weight: 700; background: rgba(244, 203, 69, 0.12); border: 1px solid rgba(244, 203, 69, 0.25); padding: 0.2rem 0.5rem; border-radius: 4px; font-size: 0.8rem;">
                                <?= e($log['action']) ?>
                            </code>
                        </td>
                        <td>
                            <?= e($log['full_name'] ? ($log['full_name'] . ' (' . $log['email'] . ')') : 'System / Guest') ?>
                        </td>
                        <td style="font-family: monospace; font-size: 0.82rem; color: var(--text-secondary);">
                            <?= e($log['ip_address']) ?>
                        </td>
                        <td style="font-size: 0.88rem; color: var(--text-secondary);">
                            <?= e($log['details']) ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
