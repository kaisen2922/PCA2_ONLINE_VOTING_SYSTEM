<?php
/**
 * ApexVote – Voter Command Center & Dashboard
 * Conforms to ApexVote Editorial Design Language
 */

require_once __DIR__ . '/includes/auth.php';
require_voter();

$pageTitle = 'Voter Command Center';
$isDashboard = true;
$isAdmin = false;
$user = current_user();
$db = getDB();

// 1. Fetch Voter's Participation Metrics (Real Database Queries)
$stmt = $db->prepare("
    SELECT 
        COUNT(*) as total_eligible,
        SUM(CASE WHEN ve.has_voted = 1 THEN 1 ELSE 0 END) as total_voted,
        SUM(CASE WHEN ve.has_voted = 0 THEN 1 ELSE 0 END) as pending_votes
    FROM voter_eligibility ve
    JOIN elections e ON ve.election_id = e.id
    WHERE ve.user_id = ? AND e.status = 'active' AND ve.eligibility_status = 'eligible'
");
$stmt->execute([$user['id']]);
$metrics = $stmt->fetch();
$totalEligibleActive = (int)($metrics['total_eligible'] ?? 0);
$totalVotedActive = (int)($metrics['total_voted'] ?? 0);
$pendingActive = (int)($metrics['pending_votes'] ?? 0);

// Total lifetime votes cast by this student
$stmt = $db->prepare("SELECT COUNT(*) FROM voter_eligibility WHERE user_id = ? AND has_voted = 1");
$stmt->execute([$user['id']]);
$lifetimeVotes = (int)$stmt->fetchColumn();

// 2. Fetch Active Elections for this Voter
$stmt = $db->prepare("
    SELECT e.*, 
           COUNT(DISTINCT c.id) as candidate_count,
           ve.has_voted, ve.voted_at, ve.receipt_token, ve.eligibility_status
    FROM elections e
    LEFT JOIN candidates c ON e.id = c.election_id
    LEFT JOIN voter_eligibility ve ON e.id = ve.election_id AND ve.user_id = ?
    WHERE e.status = 'active'
    GROUP BY e.id
    ORDER BY e.end_time ASC
");
$stmt->execute([$user['id']]);
$activeElections = $stmt->fetchAll();

// Fetch candidate details for interactive 3D election flip cards
$activeElectionIds = array_column($activeElections, 'id');
$candidatesByElection = [];
if (!empty($activeElectionIds)) {
    $placeholders = implode(',', array_fill(0, count($activeElectionIds), '?'));
    $cStmt = $db->prepare("
        SELECT id, election_id, full_name, position, party_or_slate 
        FROM candidates 
        WHERE election_id IN ($placeholders) 
        ORDER BY ballot_number ASC, id ASC
    ");
    $cStmt->execute($activeElectionIds);
    while ($cRow = $cStmt->fetch(PDO::FETCH_ASSOC)) {
        $candidatesByElection[$cRow['election_id']][] = $cRow;
    }
}

// 3. Identify Nearest Pending Eligible Election for "Your Next Vote"
$nextPendingElection = null;
foreach ($activeElections as $ae) {
    $hasVoted = (int)($ae['has_voted'] ?? 0) === 1;
    $isEligible = ($ae['eligibility_status'] ?? '') === 'eligible';
    if (!$hasVoted && $isEligible) {
        $nextPendingElection = $ae;
        break; // Nearest ending active pending election
    }
}

// 4. Fetch Recent Participation Receipts (Ballot privacy: NO candidate choice)
$stmt = $db->prepare("
    SELECT e.id as election_id, e.title, e.category, ve.voted_at, ve.receipt_token
    FROM voter_eligibility ve
    JOIN elections e ON ve.election_id = e.id
    WHERE ve.user_id = ? AND ve.has_voted = 1
    ORDER BY ve.voted_at DESC
    LIMIT 3
");
$stmt->execute([$user['id']]);
$recentReceipts = $stmt->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<!-- ============================================================== -->
<!-- SECTION 1 – Welcome Banner                                    -->
<!-- ============================================================== -->
<div class="voter-welcome-card" style="margin-bottom: 2rem;">
    <div class="voter-welcome-inner">
        <div class="voter-welcome-content">
            <div class="voter-welcome-heading-row">
                <h2 class="voter-welcome-title">Welcome, <?= e($user['full_name']) ?></h2>
                <span class="status-badge badge-emerald" style="display: inline-flex; align-items: center; gap: 0.4rem;">
                    <span class="status-indicator-ping"></span> Verified Voter
                </span>
            </div>
            <div class="voter-welcome-meta">
                <span class="meta-item">
                    <span class="meta-label">Department:</span> 
                    <strong class="meta-highlight"><?= e($user['department']) ?></strong>
                </span>
                <span class="meta-sep" aria-hidden="true">&bull;</span>
                <span class="meta-item">
                    <span class="meta-label">Course:</span> 
                    <strong><?= e($user['course'] ?? 'BCA') ?> (Sem <?= (int)($user['semester'] ?? 1) ?>)</strong>
                </span>
                <span class="meta-sep" aria-hidden="true">&bull;</span>
                <span class="meta-item">
                    <span class="meta-label">College Roll No:</span> 
                    <code class="voter-roll-badge"><?= e($user['college_roll_no'] ?: $user['voter_id_number']) ?></code>
                </span>
            </div>
        </div>
        <div class="voter-welcome-actions">
            <a href="<?= url('elections.php') ?>" class="btn btn-primary btn-sm">
                <span>Explore All Ballots</span>
                <span aria-hidden="true">&rarr;</span>
            </a>
            <a href="<?= url('results.php') ?>" class="btn btn-secondary btn-sm">
                <span>Certified Tallies</span>
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="8" r="6"/><path d="M15.477 12.89 17 22l-5-3-5 3 1.523-9.11"/></svg>
            </a>
        </div>
    </div>
</div>

<!-- ============================================================== -->
<!-- SECTION 2 – Next Action ("Your Next Vote" Panel)              -->
<!-- ============================================================== -->
<section class="next-vote-section" style="margin-bottom: 2rem;">
    <?php if ($nextPendingElection): ?>
        <?php 
            $closesSoon = (strtotime($nextPendingElection['end_time']) - time() < 86400);
            $candidateCount = (int)$nextPendingElection['candidate_count'];
        ?>
        <div class="glass-card next-vote-card" style="background: linear-gradient(135deg, rgba(66, 51, 41, 0.95) 0%, rgba(48, 37, 30, 0.98) 100%); border: 1.5px solid rgba(244, 203, 69, 0.4); border-radius: var(--radius-lg); padding: 1.75rem 2rem; position: relative; overflow: hidden; box-shadow: 0 8px 24px rgba(0, 0, 0, 0.25);">
            <div style="position: absolute; right: -20px; bottom: -20px; opacity: 0.05; pointer-events: none;" aria-hidden="true">
                <svg width="220" height="220" viewBox="0 0 24 24" fill="none" stroke="#F4CB45" stroke-width="1.5"><path d="m9 12 2 2 4-4"></path><path d="M5 7c0-1.1.9-2 2-2h10a2 2 0 0 1 2 2v12H5V7Z"></path><path d="M22 19H2"></path></svg>
            </div>

            <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1.25rem; position: relative; z-index: 2;">
                <div style="flex: 1; min-width: 280px;">
                    <div style="display: flex; align-items: center; gap: 0.65rem; margin-bottom: 0.65rem; flex-wrap: wrap;">
                        <span class="status-badge badge-amber" style="font-weight: 700; letter-spacing: 0.04em;">
                            <span class="status-indicator-ping"></span> Priority Action: Your Next Ballot
                        </span>
                        <span class="status-badge badge-category">
                            <?= e($nextPendingElection['category']) ?>
                        </span>
                    </div>

                    <h3 style="font-size: 1.45rem; font-weight: 800; color: var(--text-primary); margin: 0 0 0.5rem; letter-spacing: -0.01em;">
                        <?= e($nextPendingElection['title']) ?>
                    </h3>
                    <p style="font-size: 0.9rem; color: var(--text-secondary); margin: 0 0 1rem; max-width: 680px; line-height: 1.55;">
                        <?= e(strip_tags($nextPendingElection['description'])) ?>
                    </p>

                    <div style="display: flex; align-items: center; gap: 1.25rem; font-size: 0.85rem; color: var(--text-secondary); flex-wrap: wrap;">
                        <span>Certified Candidates: <strong style="color: var(--accent-primary);"><?= $candidateCount ?> Nominees</strong></span>
                        <span>&bull;</span>
                        <span>Poll Closure: <strong style="color: var(--text-primary);"><?= format_date($nextPendingElection['end_time'], 'd M, h:i A') ?></strong></span>
                        <span>&bull;</span>
                        <span style="color: <?= $closesSoon ? 'var(--danger)' : 'var(--accent-primary)' ?>; font-weight: 600;">
                            ⏳ Closes <?= time_ago($nextPendingElection['end_time']) ?>
                        </span>
                    </div>
                </div>

                <div style="display: flex; flex-direction: column; align-items: flex-end; justify-content: center; min-width: 220px; align-self: center;">
                    <a href="<?= url('vote.php?id=' . $nextPendingElection['id']) ?>" class="btn btn-primary btn-lg" style="box-shadow: 0 4px 16px rgba(244, 203, 69, 0.35); width: 100%; justify-content: center; padding: 0.85rem 1.75rem; font-size: 0.95rem;">
                        <span>Review Candidates &amp; Vote</span>
                        <span aria-hidden="true">&rarr;</span>
                    </a>
                    <span style="font-size: 0.78rem; color: var(--text-muted); margin-top: 0.5rem; text-align: center; width: 100%;">
                        🔒 Encrypted ballot &bull; Single-use verification
                    </span>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="glass-card" style="background: var(--surface-card); border: 1px solid var(--border-subtle); border-radius: var(--radius-lg); padding: 1.5rem 2rem; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem;">
            <div style="display: flex; align-items: center; gap: 1.25rem;">
                <div style="width: 44px; height: 44px; border-radius: 50%; background: rgba(56, 185, 130, 0.12); border: 1.5px solid rgba(56, 185, 130, 0.3); display: flex; align-items: center; justify-content: center; color: var(--success); flex-shrink: 0;">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                </div>
                <div>
                    <h4 style="margin: 0 0 0.2rem; font-size: 1.1rem; color: var(--text-primary);">All Eligible Ballots Cast</h4>
                    <p style="margin: 0; font-size: 0.88rem; color: var(--text-secondary);">You have exercised your democratic vote in all active elections available to your roll. Thank you for your participation!</p>
                </div>
            </div>
            <a href="<?= url('voting-history.php') ?>" class="btn btn-secondary btn-sm">
                <span>View Participation Receipts &rarr;</span>
            </a>
        </div>
    <?php endif; ?>
</section>

<!-- ============================================================== -->
<!-- SECTION 3 – Voting Overview (4 Stat Cards)                     -->
<!-- ============================================================== -->
<div class="stats-grid" style="margin-bottom: 2.25rem;">
    <!-- Stat 1: Pending Ballots -->
    <div class="stat-card">
        <div class="stat-icon stat-icon-amber">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        </div>
        <div class="stat-info">
            <div class="stat-val stat-counter" data-target="<?= $pendingActive ?>"><?= $pendingActive ?></div>
            <div class="stat-label">Pending Ballots to Cast</div>
        </div>
    </div>

    <!-- Stat 2: Votes Recorded -->
    <div class="stat-card">
        <div class="stat-icon stat-icon-emerald">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
        </div>
        <div class="stat-info">
            <div class="stat-val stat-counter" data-target="<?= $lifetimeVotes ?>"><?= $lifetimeVotes ?></div>
            <div class="stat-label">Lifetime Votes Recorded</div>
        </div>
    </div>

    <!-- Stat 3: Available Elections -->
    <div class="stat-card">
        <div class="stat-icon stat-icon-yellow">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="m9 12 2 2 4-4"/></svg>
        </div>
        <div class="stat-info">
            <div class="stat-val stat-counter" data-target="<?= count($activeElections) ?>"><?= count($activeElections) ?></div>
            <div class="stat-label">Current Live Elections</div>
        </div>
    </div>

    <!-- Stat 4: Participation Status -->
    <div class="stat-card">
        <div class="stat-icon <?= $pendingActive === 0 ? 'stat-icon-emerald' : 'stat-icon-amber' ?>">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
        </div>
        <div class="stat-info">
            <div class="stat-val stat-val-text" style="color: <?= $pendingActive === 0 ? 'var(--success)' : 'var(--accent-primary)' ?>; font-size: 1.25rem;">
                <?= $pendingActive === 0 ? 'Ballots Complete' : 'Active Voter' ?>
            </div>
            <div class="stat-label"><?= $pendingActive === 0 ? 'All Eligible Votes Cast' : 'Ballot Action Needed' ?></div>
        </div>
    </div>
</div>

<!-- ============================================================== -->
<!-- SECTION 4 – Active College Elections                           -->
<!-- ============================================================== -->
<section class="dashboard-elections-section" style="margin-bottom: 2.5rem;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; flex-wrap: wrap; gap: 0.75rem;">
        <div>
            <h3 style="font-size: 1.35rem; color: var(--text-primary); margin: 0 0 0.25rem;">Active College Elections</h3>
            <p style="font-size: 0.88rem; color: var(--text-secondary); margin: 0;">Cast your democratic vote before official poll closures.</p>
        </div>
        <a href="<?= url('elections.php') ?>" class="btn btn-secondary btn-sm">View Complete List &rarr;</a>
    </div>

    <?php if (empty($activeElections)): ?>
        <div class="glass-card" style="text-align: center; padding: 2.5rem; background: var(--surface-card); border: 1px solid var(--border-subtle); border-radius: var(--radius-lg);">
            <p style="color: var(--text-secondary); font-size: 0.95rem; margin: 0;">There are no active elections currently in progress for your voter roll.</p>
        </div>
    <?php else: ?>
        <div class="elections-grid elections-cards-grid">
            <?php foreach ($activeElections as $elec): ?>
                <?= render_election_3d_card($elec, $candidatesByElection[$elec['id']] ?? [], $user, false) ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<!-- ============================================================== -->
<!-- SECTION 5 – Recent Activity & Participation Receipts           -->
<!-- ============================================================== -->
<div class="glass-card" style="background: var(--surface-card); border: 1px solid var(--border-subtle); padding: 1.5rem; border-radius: var(--radius-lg);">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; flex-wrap: wrap; gap: 0.5rem;">
        <h4 style="display: flex; align-items: center; gap: 0.5rem; color: var(--text-primary); margin: 0; font-size: 1.15rem;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--accent-primary);"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg>
            <span>My Recent Participation Receipts</span>
        </h4>
        <a href="<?= url('voting-history.php') ?>" style="font-size: 0.85rem; color: var(--accent-primary); font-weight: 600;">View Complete History &rarr;</a>
    </div>

    <?php if (empty($recentReceipts)): ?>
        <p style="font-size: 0.9rem; color: var(--text-muted); margin: 0;">You have not cast ballots in any election cycles yet. Active ballots will appear above.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="custom-table receipts-table">
                <thead>
                    <tr>
                        <th style="min-width: 220px;">Election Title</th>
                        <th style="min-width: 160px;">Category</th>
                        <th style="min-width: 170px;">Timestamp Cast</th>
                        <th style="min-width: 260px;">Receipt Reference</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentReceipts as $rec): ?>
                        <tr>
                            <td style="font-weight: 600; color: var(--text-primary);"><?= e($rec['title']) ?></td>
                            <td>
                                <span class="status-badge badge-category">
                                    <?= e($rec['category']) ?>
                                </span>
                            </td>
                            <td style="color: var(--text-secondary); font-size: 0.88rem; white-space: nowrap;"><?= format_date($rec['voted_at'], 'd M Y, h:i A') ?></td>
                            <td>
                                <div class="receipt-ref-wrap">
                                    <code class="receipt-ref-box" title="<?= e($rec['receipt_token']) ?>">
                                        <?= substr(e($rec['receipt_token'] ?? ''), 0, 14) ?>&hellip;<?= substr(e($rec['receipt_token'] ?? ''), -8) ?>
                                    </code>
                                    <button type="button" class="btn btn-secondary btn-xs" 
                                            onclick="copyDashboardToken('<?= e($rec['receipt_token']) ?>', this)" 
                                            title="Copy full reference">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="14" height="14" x="8" y="8" rx="2" ry="2"></rect><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"></path></svg>
                                        <span>Copy</span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<script>
function copyDashboardToken(text, btn) {
    if (!text) return;
    navigator.clipboard.writeText(text).then(() => {
        showToast('success', 'Participation receipt reference copied to clipboard!');
        if (btn) {
            const original = btn.innerHTML;
            btn.innerHTML = '<span>Copied!</span>';
            setTimeout(() => { btn.innerHTML = original; }, 2000);
        }
    }).catch(() => {
        showToast('info', 'Reference: ' + text.substring(0, 16) + '...');
    });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
