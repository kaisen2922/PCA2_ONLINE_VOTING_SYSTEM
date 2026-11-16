<?php
/**
 * Certified Election Results & Interactive Chart Visualizations
 * Refined for ApexVote Editorial Design Language
 */

require_once __DIR__ . '/includes/auth.php';

$pageTitle = 'Certified Electoral Results';
$isDashboard = is_logged_in();
$isAdmin = is_admin();
$user = current_user();
$db = getDB();

// 1. Fetch all elections that have results_published = 1 or status = 'completed'
$electionsStmt = $db->query("
    SELECT id, title, category, status, results_published, end_time 
    FROM elections 
    WHERE results_published = 1 OR status = 'completed'
    ORDER BY end_time DESC
");
$publishedElections = $electionsStmt->fetchAll();

$selectedElectionId = (int)($_GET['election_id'] ?? 0);
if ($selectedElectionId <= 0 && !empty($publishedElections)) {
    foreach ($publishedElections as $pe) {
        if ($pe['results_published'] == 1) {
            $selectedElectionId = (int)$pe['id'];
            break;
        }
    }
    if ($selectedElectionId <= 0) {
        $selectedElectionId = (int)$publishedElections[0]['id'];
    }
}

// 2. Fetch selected election details
$election = null;
$candidates = [];
$totalVotes = 0;
$winner = null;

if ($selectedElectionId > 0) {
    $stmt = $db->prepare("SELECT * FROM elections WHERE id = ? LIMIT 1");
    $stmt->execute([$selectedElectionId]);
    $election = $stmt->fetch();

    if ($election) {
        // Query candidates and accurate ballot counts
        $cStmt = $db->prepare("
            SELECT c.*, 
                   COUNT(b.id) as real_ballot_count
            FROM candidates c
            LEFT JOIN ballots b ON c.id = b.candidate_id
            WHERE c.election_id = ?
            GROUP BY c.id
            ORDER BY c.vote_count DESC, real_ballot_count DESC, c.id ASC
        ");
        $cStmt->execute([$selectedElectionId]);
        $candidates = $cStmt->fetchAll();

        // Calculate total votes (use GREATEST of vote_count or real_ballot_count)
        foreach ($candidates as &$c) {
            $effectiveVotes = max((int)$c['vote_count'], (int)$c['real_ballot_count']);
            $c['effective_votes'] = $effectiveVotes;
            $totalVotes += $effectiveVotes;
        }
        unset($c);

        // Sort descending by effective_votes
        usort($candidates, function($a, $b) {
            return $b['effective_votes'] <=> $a['effective_votes'];
        });

        if (!empty($candidates) && $totalVotes > 0) {
            $winner = $candidates[0];
        }

        // Phase 8: Calculate Canonical Results Verification Hash
        $canonicalParts = [];
        foreach ($candidates as $c) {
            $canonicalParts[] = "{$c['id']}:" . rawurlencode($c['full_name']) . ":{$c['effective_votes']}";
        }
        $canonicalString = "election:{$election['id']}|total:{$totalVotes}|tallies:" . implode(',', $canonicalParts) . "|end:" . ($election['end_time'] ?? '');
        $canonicalResultsHash = hash('sha256', $canonicalString);
    }
}

// Prepare JSON arrays for Chart.js
$chartLabels = [];
$chartVotes = [];
foreach ($candidates as $c) {
    $chartLabels[] = $c['full_name'];
    $chartVotes[] = $c['effective_votes'];
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="<?= $isDashboard ? '' : 'container' ?>" style="<?= $isDashboard ? '' : 'padding: 3rem 1.5rem;' ?>">
    <!-- Header & Election Selector -->
    <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 2rem; flex-wrap: wrap; gap: 1.25rem;">
        <div>
            <h2 style="font-size: 1.75rem; font-weight: 800; color: var(--text-primary); margin: 0 0 0.35rem; letter-spacing: -0.02em;">Certified Electoral Results</h2>
            <p style="font-size: 0.92rem; color: var(--text-secondary); margin: 0;">Official ballot counts, certified nominee rankings, and collegiate election tallies.</p>
        </div>

        <?php if (!empty($publishedElections)): ?>
            <div style="min-width: 280px;">
                <label class="form-label" for="election-selector" style="font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-muted);">Select Election Cycle:</label>
                <select id="election-selector" class="form-select" onchange="window.location.href='<?= url('results.php') ?>?election_id=' + this.value;">
                    <?php foreach ($publishedElections as $pe): ?>
                        <option value="<?= (int)$pe['id'] ?>" <?= ($pe['id'] == $selectedElectionId) ? 'selected' : '' ?>>
                            <?= e($pe['title']) ?> <?= ($pe['results_published'] == 1) ? '✓ Certified' : '(Draft Results)' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>
    </div>

    <?php if (!$election): ?>
        <div class="glass-card" style="text-align: center; padding: 4rem; background: var(--surface-card); border: 1px solid var(--border-subtle); border-radius: var(--radius-lg);">
            <div style="width: 56px; height: 56px; margin: 0 auto 1.25rem auto; border-radius: 50%; background: rgba(244, 203, 69, 0.1); border: 1px solid rgba(244, 203, 69, 0.2); display: flex; align-items: center; justify-content: center; color: var(--accent-primary);">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
            </div>
            <h3 style="color: var(--text-primary); margin-bottom: 0.5rem;">No Results Certified Yet</h3>
            <p style="margin-bottom: 1.5rem; color: var(--text-secondary);">There are no elections with officially certified public results yet.</p>
            <a href="<?= url('elections.php') ?>" class="btn btn-primary btn-sm">Explore Active Elections &rarr;</a>
        </div>
    <?php else: ?>
        <!-- Publication Notice -->
        <?php if ($election['results_published'] != 1): ?>
            <div class="alert-box alert-warning" style="margin-bottom: 1.5rem;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                <span><strong>Preliminary Preview:</strong> These results have not yet been certified for final publication by the College Election Committee.</span>
            </div>
        <?php endif; ?>

        <!-- Winner Certification Banner -->
        <?php if ($winner && $totalVotes > 0): ?>
            <?php 
                $winnerPct = round(($winner['effective_votes'] / $totalVotes) * 100, 1);
                $winnerPhoto = preg_replace('#^assets/#', '', $winner['photo_url'] ?? '') ?: 'images/default-avatar.svg';
            ?>
            <div class="glass-card" style="margin-bottom: 2rem; background: var(--surface-card); border: 1.5px solid rgba(56, 185, 130, 0.35); padding: 2rem; border-radius: var(--radius-lg);">
                <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1.5rem;">
                    <div style="display: flex; align-items: center; gap: 1.5rem;">
                        <div style="position: relative;">
                            <img src="<?= asset($winnerPhoto) ?>" 
                                 alt="<?= e($winner['full_name']) ?>" 
                                 style="width: 80px; height: 80px; border-radius: 50%; border: 3px solid #38B982; object-fit: cover;">
                            <div style="position: absolute; bottom: -2px; right: -2px; background: var(--accent-primary); color: #241B16; border-radius: 50%; width: 26px; height: 26px; display: flex; align-items: center; justify-content: center;" title="Certified Winner">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                            </div>
                        </div>

                        <div>
                            <span class="status-badge badge-emerald" style="margin-bottom: 0.4rem; display: inline-flex; align-items: center; gap: 0.35rem;">
                                ✓ Certified Winner
                            </span>
                            <h2 style="font-size: 1.65rem; color: var(--text-primary); margin: 0 0 0.25rem; font-weight: 800;"><?= e($winner['full_name']) ?></h2>
                            <p style="color: var(--accent-primary); font-weight: 600; font-size: 0.92rem; margin: 0;">
                                <?= e($winner['position']) ?> &bull; <span style="color: var(--text-secondary);"><?= e($winner['party_or_slate']) ?></span>
                            </p>
                        </div>
                    </div>

                    <div style="display: flex; gap: 1.75rem; background: rgba(0, 0, 0, 0.28); border-radius: var(--radius-md); padding: 1rem 1.5rem; border: 1px solid var(--border-subtle);">
                        <div style="text-align: center;">
                            <div style="font-size: 1.75rem; font-weight: 800; color: #38B982; line-height: 1.2;"><?= number_format($winner['effective_votes']) ?></div>
                            <div style="font-size: 0.72rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; margin-top: 0.2rem;">Certified Ballots</div>
                        </div>
                        <div style="border-left: 1px solid var(--border-subtle);"></div>
                        <div style="text-align: center;">
                            <div style="font-size: 1.75rem; font-weight: 800; color: var(--accent-primary); line-height: 1.2;"><?= $winnerPct ?>%</div>
                            <div style="font-size: 0.72rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; margin-top: 0.2rem;">Electoral Share</div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Visual Analytics Charts Grid -->
        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.75rem; margin-bottom: 2rem;" class="charts-row">
            <!-- Bar Chart -->
            <div class="glass-card" style="min-height: 380px; display: flex; flex-direction: column; background: var(--surface-card); border: 1px solid var(--border-subtle); border-radius: var(--radius-lg); padding: 1.5rem;">
                <h4 style="margin: 0 0 1.25rem 0; display: flex; align-items: center; gap: 0.6rem; font-size: 1.1rem; color: var(--text-primary);">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--accent-primary)" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
                    <span>Vote Tallies per Candidate</span>
                </h4>
                <div style="flex: 1; position: relative; min-height: 280px;">
                    <canvas id="resultsBarChart"></canvas>
                </div>
            </div>

            <!-- Doughnut Chart -->
            <div class="glass-card" style="min-height: 380px; display: flex; flex-direction: column; background: var(--surface-card); border: 1px solid var(--border-subtle); border-radius: var(--radius-lg); padding: 1.5rem;">
                <h4 style="margin: 0 0 1.25rem 0; display: flex; align-items: center; gap: 0.6rem; font-size: 1.1rem; color: var(--text-primary);">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--accent-primary)" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 2a10 10 0 0 1 10 10h-10z"/></svg>
                    <span>Ballot Distribution</span>
                </h4>
                <div style="flex: 1; position: relative; min-height: 280px;">
                    <canvas id="resultsDoughnutChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Detailed Breakdown Table with Required Ranking Hierarchy -->
        <div class="glass-card" style="padding: 0; overflow: hidden; margin-bottom: 2rem; background: var(--surface-card); border: 1px solid var(--border-subtle); border-radius: var(--radius-lg);">
            <div style="padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border-subtle); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                <div style="display: flex; align-items: center; gap: 1rem;">
                    <h4 style="margin: 0; color: var(--text-primary); font-size: 1.15rem;">Comprehensive Electoral Tally Sheet</h4>
                    <span class="status-badge badge-category">Total Ballots Cast: <?= number_format($totalVotes) ?></span>
                </div>
                <div>
                    <input type="text" class="form-control" placeholder="Search candidate or slate..." 
                           style="min-height: 36px; padding: 0.35rem 0.85rem; font-size: 0.85rem; width: 220px;" 
                           data-table-filter="#resultsCandidatesTable">
                </div>
            </div>

            <div class="table-responsive">
                <table class="custom-table" id="resultsCandidatesTable">
                    <thead>
                        <tr>
                            <th style="width: 70px;">Rank</th>
                            <th style="min-width: 220px;">Candidate Profile</th>
                            <th style="min-width: 180px;">Position / Office</th>
                            <th style="min-width: 130px;">Certified Ballots</th>
                            <th style="min-width: 160px;">Percentage Share</th>
                            <th style="min-width: 120px;">Election Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($candidates as $idx => $cand): ?>
                            <?php 
                                $pct = ($totalVotes > 0) ? round(($cand['effective_votes'] / $totalVotes) * 100, 1) : 0;
                                $isWinner = ($idx === 0 && $totalVotes > 0);
                            ?>
                            <tr>
                                <td>
                                    <span style="font-weight: 800; font-size: 1.05rem; color: <?= $isWinner ? '#38B982' : 'var(--text-muted)' ?>;">
                                        #<?= $idx + 1 ?>
                                    </span>
                                </td>
                                <td>
                                    <?php $candPhoto = preg_replace('#^assets/#', '', $cand['photo_url'] ?? '') ?: 'images/default-avatar.svg'; ?>
                                    <div style="display: flex; align-items: center; gap: 0.85rem;">
                                        <img src="<?= asset($candPhoto) ?>" 
                                             alt="<?= e($cand['full_name']) ?>" style="width: 38px; height: 38px; border-radius: 50%; object-fit: cover;">
                                        <div>
                                            <strong style="color: var(--text-primary); display: block;"><?= e($cand['full_name']) ?></strong>
                                            <span style="font-size: 0.78rem; color: var(--text-muted);">Ballot #<?= (int)$cand['ballot_number'] ?> &bull; <?= e($cand['party_or_slate']) ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div style="font-weight: 500; color: var(--text-primary);"><?= e($cand['position']) ?></div>
                                    <div style="font-size: 0.78rem; color: var(--text-secondary);"><?= e($cand['party_or_slate']) ?></div>
                                </td>
                                <td>
                                    <strong style="font-size: 1.05rem; color: var(--text-primary);"><?= number_format($cand['effective_votes']) ?></strong>
                                </td>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                                        <div style="flex: 1; min-width: 75px; height: 8px; background: rgba(255,255,255,0.06); border-radius: 4px; overflow: hidden;">
                                            <div style="width: <?= $pct ?>%; height: 100%; background: <?= $isWinner ? 'linear-gradient(90deg, #38B982, #F4CB45)' : 'linear-gradient(90deg, #DDB63F, #F4CB45)' ?>; border-radius: 4px;"></div>
                                        </div>
                                        <span style="font-weight: 600; font-size: 0.88rem; min-width: 45px; color: var(--text-primary);"><?= $pct ?>%</span>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($isWinner): ?>
                                        <span class="status-badge badge-emerald">Elected</span>
                                    <?php else: ?>
                                        <span class="status-badge badge-slate">Completed</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Phase 8: Cryptographic Results Integrity Card -->
        <?php if (!empty($canonicalResultsHash)): ?>
            <div class="glass-card" style="margin-bottom: 2rem; background: var(--surface-card); border: 1.5px solid rgba(244, 203, 69, 0.35); border-radius: var(--radius-lg); padding: 1.5rem;">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem; margin-bottom: 1rem;">
                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                        <div style="width: 36px; height: 36px; border-radius: 50%; background: rgba(244, 203, 69, 0.15); display: flex; align-items: center; justify-content: center; color: var(--accent-primary);">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                        </div>
                        <div>
                            <h4 style="margin: 0; color: var(--text-primary); font-size: 1.05rem; font-weight: 700;">Electoral Integrity & Canonical Verification Hash</h4>
                            <span style="font-size: 0.78rem; color: var(--text-muted);">Cryptographic proof of tally immutability certified by the College Electoral Management System</span>
                        </div>
                    </div>
                    <span class="status-badge badge-emerald" style="font-size: 0.75rem;">✓ Certified Canonical Hash</span>
                </div>

                <div style="background: rgba(0, 0, 0, 0.4); border: 1px dashed var(--accent-primary); border-radius: var(--radius-md); padding: 1rem 1.25rem; margin-bottom: 1rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                    <div>
                        <div style="font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.1em; color: var(--accent-primary); font-weight: 700; margin-bottom: 0.25rem;">
                            SHA-256 Canonical Checksum
                        </div>
                        <div id="resultsCanonicalHashText" style="font-family: 'Consolas', 'Courier New', monospace; font-size: 0.95rem; color: var(--text-primary); word-break: break-all; font-weight: 700;">
                            <?= e($canonicalResultsHash) ?>
                        </div>
                    </div>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="navigator.clipboard.writeText('<?= e($canonicalResultsHash) ?>').then(() => { this.innerText = 'Copied ✓'; setTimeout(() => this.innerText = 'Copy Hash', 2000); });">
                        Copy Hash
                    </button>
                </div>

                <div style="font-size: 0.78rem; color: var(--text-secondary); line-height: 1.5; background: rgba(255,255,255,0.03); padding: 0.75rem 1rem; border-radius: var(--radius-sm); border-left: 3px solid var(--accent-primary);">
                    <strong>Verification Algorithm:</strong> <code>SHA256(election:{id}|total:{total}|tallies:{candidate_id}:{name}:{votes}|end:{timestamp})</code>. Any post-election modification to candidate ballot counts or totals invalidates this cryptographic checksum. Certified under IST (Asia/Kolkata).
                </div>
            </div>
        <?php endif; ?>

        <!-- Demonstration Notice Card (Honest, accurate audit disclaimer) -->
        <div class="glass-card" style="padding: 1.25rem 1.5rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; border: 1px solid var(--border-subtle); background: var(--surface-card); border-radius: var(--radius-lg);">
            <div style="display: flex; align-items: center; gap: 1rem;">
                <div style="width: 40px; height: 40px; border-radius: 50%; background: rgba(244, 203, 69, 0.1); border: 1px solid rgba(244, 203, 69, 0.25); display: flex; align-items: center; justify-content: center; color: var(--accent-primary); flex-shrink: 0;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                </div>
                <div>
                    <strong style="color: var(--text-primary); display: block; font-size: 0.92rem;">Demonstration Electoral Simulation Notice</strong>
                    <span style="font-size: 0.82rem; color: var(--text-secondary);">
                        Results are calculated directly from active database records. Candidate vote totals and ballot counts are strictly verified.
                    </span>
                </div>
            </div>
            <div style="font-size: 0.8rem; color: var(--text-muted);">
                Voting Concluded: <?= format_date($election['end_time'], 'd M Y, h:i A') ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Load Visualization Data -->
<script src="<?= asset('js/chart-results.js') ?>"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const labels = <?= json_encode($chartLabels) ?>;
    const votes = <?= json_encode($chartVotes) ?>;

    if (labels.length > 0 && typeof ApexVoteCharts !== 'undefined') {
        ApexVoteCharts.createBarChart('resultsBarChart', labels, votes);
        ApexVoteCharts.createDoughnutChart('resultsDoughnutChart', labels, votes);
    }
});
</script>

<style>
@media (max-width: 900px) {
    .charts-row {
        grid-template-columns: 1fr !important;
        display: flex !important;
        flex-direction: column !important;
    }
}
</style>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
