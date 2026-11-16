<?php
/**
 * Administrator Electoral Results & Public Certification
 */

require_once __DIR__ . '/../includes/auth.php';
require_admin();

$pageTitle = 'Results Certification';
$isDashboard = true;
$isAdmin = true;
$user = current_user();
$db = getDB();

// Handle Publishing / Unpublishing Toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        set_flash('danger', 'Security session expired. Please refresh and try again.');
    } else {
        $electionId = (int)($_POST['election_id'] ?? 0);
        $action = $_POST['action'] ?? '';

        if ($action === 'publish') {
            $stmt = $db->prepare("UPDATE elections SET results_published = 1 WHERE id = ?");
            $stmt->execute([$electionId]);

            audit_log('RESULTS_CERTIFIED_PUBLISHED', "Admin officially published certified results for Election #{$electionId}", $user['id']);
            set_flash('success', "Results for Election #{$electionId} have been certified and published to the public portal!");
            redirect('admin/manage-results.php');
        } elseif ($action === 'unpublish') {
            $stmt = $db->prepare("UPDATE elections SET results_published = 0 WHERE id = ?");
            $stmt->execute([$electionId]);

            audit_log('RESULTS_UNPUBLISHED', "Admin revoked public visibility for Election #{$electionId}", $user['id']);
            set_flash('info', "Results for Election #{$electionId} have been unpublished from the public portal.");
            redirect('admin/manage-results.php');
        }
    }
}

// Fetch all elections with vote totals and candidate counts
$elections = $db->query("
    SELECT e.*, 
           COUNT(DISTINCT c.id) as candidate_count,
           COUNT(DISTINCT b.id) as total_ballots_cast,
           SUM(c.vote_count) as total_candidate_votes
    FROM elections e
    LEFT JOIN candidates c ON e.id = c.election_id
    LEFT JOIN ballots b ON e.id = b.election_id
    GROUP BY e.id
    ORDER BY FIELD(e.status, 'completed', 'active', 'draft', 'cancelled'), e.end_time DESC
")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Header -->
<div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 2rem; flex-wrap: wrap; gap: 1.25rem;">
    <div>
        <h2>Electoral Results Certification</h2>
        <p>Review recorded ballot tallies, certify winners, and control public transparency release.</p>
    </div>
    <a href="<?= url('results.php') ?>" class="btn btn-secondary" target="_blank">
        Open Public Results Portal ↗
    </a>
</div>

<!-- Elections Certification Table -->
<div class="glass-card" style="padding: 0; overflow: hidden; margin-bottom: 2rem;">
    <div class="table-responsive">
        <table class="custom-table">
            <thead>
                <tr>
                    <th>Election Title</th>
                    <th>Category</th>
                    <th>Status</th>
                    <th>Certified Ballots</th>
                    <th>Publication State</th>
                    <th>Electoral Certification Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($elections)): ?>
                    <tr><td colspan="6" style="text-align:center;padding:2.5rem;">No elections found.</td></tr>
                <?php else: ?>
                    <?php foreach ($elections as $elec): ?>
                        <?php 
                            $effectiveVotes = max((int)$elec['total_ballots_cast'], (int)$elec['total_candidate_votes']);
                        ?>
                        <tr>
                            <td>
                                <strong style="color: var(--text-primary); font-size: 0.95rem; display: block;">
                                    <?= e($elec['title']) ?>
                                </strong>
                                <span style="font-size: 0.78rem; color: var(--text-muted);">
                                    <?= (int)$elec['candidate_count'] ?> Candidates running
                                </span>
                            </td>
                            <td>
                                <span class="status-badge badge-blue"><?= e($elec['category']) ?></span>
                            </td>
                            <td><?= election_status_badge($elec['status']) ?></td>
                            <td>
                                <strong style="color: var(--color-text); font-size: 1.05rem;"><?= number_format($effectiveVotes) ?></strong> Ballots
                            </td>
                            <td>
                                <?php if ($elec['results_published'] == 1): ?>
                                    <span class="status-badge badge-emerald">
                                        <span class="status-indicator-ping"></span> Publicly Certified
                                    </span>
                                <?php else: ?>
                                    <span class="status-badge badge-amber">
                                        Private (Unpublished)
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="display: flex; gap: 0.5rem; align-items: center;">
                                    <?php if ($elec['results_published'] == 1): ?>
                                        <form action="<?= url('admin/manage-results.php') ?>" method="POST" style="display:inline;">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="unpublish">
                                            <input type="hidden" name="election_id" value="<?= (int)$elec['id'] ?>">
                                            <button type="submit" class="btn btn-secondary btn-sm" title="Revoke public results">
                                                Hide Results
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <form action="<?= url('admin/manage-results.php') ?>" method="POST" style="display:inline;">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="publish">
                                            <input type="hidden" name="election_id" value="<?= (int)$elec['id'] ?>">
                                            <button type="submit" class="btn btn-emerald btn-sm" title="Certify and publish immediately">
                                                ✓ Certify & Publish
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                    <a href="<?= url('results.php?election_id=' . $elec['id']) ?>" class="btn btn-secondary btn-sm" target="_blank">
                                        Inspect 📊
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Electoral Commission Integrity Notice -->
<div class="glass-card" style="padding: 1.5rem; border-color: var(--border-subtle); background: var(--surface-card);">
    <div style="display: flex; align-items: center; gap: 1rem;">
        <div style="font-size: 2rem;">🛡️</div>
        <div>
            <strong style="color: var(--text-primary); display: block;">Audit & Certification Protocol</strong>
            <p style="font-size: 0.88rem; color: var(--text-secondary); margin-bottom: 0;">
                Once an election is completed, the Electoral Officer verifies that all ballot hashes are mathematically intact 
                before clicking <em>Certify & Publish</em>. Publishing immediately generates interactive Chart.js graphs 
                for student voters and certifies the winning candidate.
            </p>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
