<?php
/**
 * Detailed Election Information & Candidate Manifestos
 */

require_once __DIR__ . '/includes/auth.php';

$electionId = (int)($_GET['id'] ?? 0);
if ($electionId <= 0) {
    set_flash('danger', 'Invalid election specified.');
    redirect('elections.php');
}

$db = getDB();
$stmt = $db->prepare("SELECT * FROM elections WHERE id = ? LIMIT 1");
$stmt->execute([$electionId]);
$election = $stmt->fetch();

if (!$election) {
    set_flash('danger', 'Election not found.');
    redirect('elections.php');
}

$pageTitle = $election['title'];
$isDashboard = is_logged_in();
$isAdmin = is_admin();
$user = current_user();

// Fetch candidates
$cStmt = $db->prepare("SELECT * FROM candidates WHERE election_id = ? ORDER BY ballot_number ASC, id ASC");
$cStmt->execute([$electionId]);
$candidates = $cStmt->fetchAll();

// Check user eligibility if logged in
$eligibility = null;
if ($user) {
    $eStmt = $db->prepare("SELECT * FROM voter_eligibility WHERE election_id = ? AND user_id = ? LIMIT 1");
    $eStmt->execute([$electionId, $user['id']]);
    $eligibility = $eStmt->fetch();
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="<?= $isDashboard ? '' : 'container' ?>" style="<?= $isDashboard ? '' : 'padding: 4rem 1.5rem;' ?>">
    <!-- Breadcrumb -->
    <div style="margin-bottom: 1.5rem; font-size: 0.9rem; color: var(--text-muted);">
        <a href="<?= url('elections.php') ?>">&larr; Back to All Ballots</a>
    </div>

    <!-- Election Header Glass Card -->
    <div class="glass-card" style="margin-bottom: 2.5rem; padding: 2.25rem; border: 1px solid var(--border-subtle);">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.25rem;">
            <div>
                <span class="status-badge badge-category" style="margin-bottom: 0.5rem;"><?= e($election['category']) ?></span>
                <h1 style="margin-top: 0.25rem; font-size: 2rem;"><?= e($election['title']) ?></h1>
            </div>
            <div>
                <?= election_status_badge($election['status']) ?>
            </div>
        </div>

        <p style="font-size: 1.05rem; line-height: 1.7; color: var(--text-secondary); margin-bottom: 2rem;">
            <?= nl2br(e($election['description'])) ?>
        </p>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.25rem; background: rgba(0,0,0,0.25); border-radius: var(--radius-md); padding: 1.25rem; font-size: 0.9rem; border: 1px solid var(--border-subtle);">
            <div>
                <span style="color: var(--text-muted); display: block;">Voting Starts:</span>
                <strong style="color: var(--text-primary);"><?= format_date($election['start_time']) ?></strong>
            </div>
            <div>
                <span style="color: var(--text-muted); display: block;">Voting Closes:</span>
                <strong style="color: var(--text-primary);"><?= format_date($election['end_time']) ?></strong>
            </div>
            <div>
                <span style="color: var(--text-muted); display: block;">Electoral Scope:</span>
                <strong style="color: var(--accent-primary);"><?= e($election['eligibility_type'] === 'all' ? 'All Enrolled College Students' : 'Department of ' . $election['target_department']) ?></strong>
            </div>
            <div>
                <span style="color: var(--text-muted); display: block;">Certified Nominees:</span>
                <strong style="color: var(--text-primary);"><?= count($candidates) ?> Candidates</strong>
            </div>
        </div>

        <!-- Voter Participation Status Banner -->
        <?php if ($user): ?>
            <div style="margin-top: 1.75rem;">
                <?php if ($eligibility && $eligibility['has_voted'] == 1): ?>
                    <div class="alert-box alert-success" style="margin-bottom: 0;">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                        <div>
                            <strong>You have successfully participated in this ballot!</strong><br>
                            <span style="font-size: 0.85rem;">Vote recorded at <?= format_date($eligibility['voted_at']) ?>. 
                            Participation Receipt Reference: <code style="color: var(--accent-primary); font-family: monospace;"><?= e($eligibility['receipt_token']) ?></code></span>
                        </div>
                    </div>
                <?php elseif ($eligibility && $eligibility['eligibility_status'] === 'eligible' && $election['status'] === 'active'): ?>
                    <div class="alert-box alert-info" style="margin-bottom: 0; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                        <div style="display: flex; align-items: center; gap: 0.75rem;">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                            <div>
                                <strong>You are eligible to vote in this election!</strong> 
                                Review candidate manifestos below before casting your ballot.
                            </div>
                        </div>
                        <a href="<?= url('vote.php?id=' . $election['id']) ?>" class="btn btn-primary btn-sm">
                            Proceed to Ballot &rarr;
                        </a>
                    </div>
                <?php elseif (!$eligibility): ?>
                    <div class="alert-box alert-warning" style="margin-bottom: 0;">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                        <span>You are not on the voter roll for this departmental ballot.</span>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="alert-box alert-info" style="margin-top: 1.5rem; margin-bottom: 0;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                <span>You must <a href="<?= url('login.php') ?>" style="font-weight: 700; text-decoration: underline; color: var(--accent-primary);">sign in</a> to cast your vote in this election.</span>
            </div>
        <?php endif; ?>
    </div>

    <!-- Candidate List Header -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h2>Certified Candidates & Manifestos</h2>
            <p>Official candidate profiles, platforms, and policy declarations.</p>
        </div>
        <?php if ($election['results_published'] == 1): ?>
            <a href="<?= url('results.php?election_id=' . $election['id']) ?>" class="btn btn-emerald" style="display: flex; align-items: center; gap: 0.5rem;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
                View Certified Results
            </a>
        <?php endif; ?>
    </div>

    <?php if (empty($candidates)): ?>
        <div class="glass-card" style="text-align: center; padding: 3rem;">
            <p>No candidates have been certified for this election yet.</p>
        </div>
    <?php else: ?>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(360px, 1fr)); gap: 2rem;">
            <?php foreach ($candidates as $c): ?>
                <?php $candPhoto = preg_replace('#^assets/#', '', $c['photo_url'] ?? '') ?: 'images/default-avatar.svg'; ?>
                <div class="glass-card" style="display: flex; flex-direction: column;">
                    <div style="display: flex; gap: 1.25rem; align-items: center; margin-bottom: 1.25rem;">
                        <img src="<?= asset($candPhoto) ?>" alt="<?= e($c['full_name']) ?>" class="candidate-avatar">
                        <div>
                            <span class="status-badge badge-slate" style="font-size: 0.72rem; margin-bottom: 0.25rem;">Ballot #<?= (int)$c['ballot_number'] ?></span>
                            <div class="candidate-name"><?= e($c['full_name']) ?></div>
                            <div class="candidate-slate"><?= e($c['party_or_slate']) ?></div>
                        </div>
                    </div>

                    <div style="margin-bottom: 1.25rem;">
                        <span style="font-size: 0.8rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">Running For Office:</span>
                        <div style="font-weight: 600; color: var(--color-primary); font-size: 0.95rem;"><?= e($c['position']) ?></div>
                    </div>

                    <div style="margin-bottom: 1.5rem; flex: 1;">
                        <span style="font-size: 0.8rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; display: block; margin-bottom: 0.4rem;">Official Platform Manifesto:</span>
                        <p class="candidate-manifesto" style="margin-bottom: 0;">
                            <?= nl2br(e($c['manifesto'])) ?>
                        </p>
                    </div>

                    <?php if ($election['status'] === 'active' && $eligibility && $eligibility['has_voted'] == 0): ?>
                        <div style="border-top: 1px solid var(--border-subtle); padding-top: 1rem; margin-top: auto;">
                            <a href="<?= url('vote.php?id=' . $election['id'] . '&candidate_id=' . (int)$c['id']) ?>" class="btn btn-primary btn-block">
                                Vote for <?= e($c['full_name']) ?> &rarr;
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
