<?php
/**
 * Collegiate Elections Directory – Campus Electoral Ballots
 * Refined for ApexVote Editorial Design Language
 */

require_once __DIR__ . '/includes/auth.php';

$pageTitle = 'Campus Electoral Ballots';
$isDashboard = is_logged_in();
$isAdmin = is_admin();
$user = current_user();
$db = getDB();

$filter = $_GET['filter'] ?? 'all';
$userId = $user['id'] ?? 0;

// Query elections with candidate count and voter eligibility
$whereClause = "";
if ($filter === 'active') {
    $whereClause = "WHERE e.status = 'active'";
} elseif ($filter === 'upcoming') {
    $whereClause = "WHERE e.status = 'draft' OR (e.status = 'active' AND e.start_time > NOW())";
} elseif ($filter === 'completed') {
    $whereClause = "WHERE e.status = 'completed'";
}

$sql = "
    SELECT e.*, 
           COUNT(DISTINCT c.id) as candidate_count,
           ve.has_voted,
           ve.voted_at,
           ve.receipt_token,
           ve.eligibility_status
    FROM elections e
    LEFT JOIN candidates c ON e.id = c.election_id
    LEFT JOIN voter_eligibility ve ON e.id = ve.election_id AND ve.user_id = ?
    {$whereClause}
    GROUP BY e.id 
    ORDER BY FIELD(e.status, 'active', 'draft', 'completed', 'cancelled'), e.end_time ASC
";

$stmt = $db->prepare($sql);
$stmt->execute([$userId]);
$elections = $stmt->fetchAll();

// Fetch candidate details for interactive 3D election flip cards
$electionIds = array_column($elections, 'id');
$candidatesByElection = [];
if (!empty($electionIds)) {
    $placeholders = implode(',', array_fill(0, count($electionIds), '?'));
    $cStmt = $db->prepare("
        SELECT id, election_id, full_name, position, party_or_slate 
        FROM candidates 
        WHERE election_id IN ($placeholders) 
        ORDER BY ballot_number ASC, id ASC
    ");
    $cStmt->execute($electionIds);
    while ($cRow = $cStmt->fetch(PDO::FETCH_ASSOC)) {
        $candidatesByElection[$cRow['election_id']][] = $cRow;
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="<?= $isDashboard ? '' : 'container' ?>" style="<?= $isDashboard ? '' : 'padding: 3rem 1.5rem;' ?>">
    <!-- Page Header & Filter Controls -->
    <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 2rem; flex-wrap: wrap; gap: 1.25rem;">
        <div>
            <h2 style="font-size: 1.75rem; font-weight: 800; color: var(--text-primary); margin: 0 0 0.35rem; letter-spacing: -0.02em;">Campus Electoral Ballots</h2>
            <p style="font-size: 0.92rem; color: var(--text-secondary); margin: 0;">College Students' Council, Department Representatives, and Committee Elections.</p>
        </div>

        <div style="display: flex; gap: 0.85rem; flex-wrap: wrap; align-items: center;">
            <div style="position: relative; width: 260px;">
                <input type="text" id="electionsSearchInput" placeholder="Search ballots or departments..." 
                       class="form-control" style="padding-left: 2.25rem; height: 38px; font-size: 0.88rem;">
                <span style="position: absolute; left: 0.75rem; top: 50%; transform: translateY(-50%); color: var(--text-muted); pointer-events: none;">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                </span>
            </div>

            <div style="display: flex; background: var(--surface-input); padding: 0.25rem; border-radius: var(--radius-md); border: 1px solid var(--border-subtle); gap: 2px;">
                <a href="<?= url('elections.php?filter=all') ?>" class="btn btn-sm <?= $filter === 'all' ? 'btn-primary' : 'btn-secondary' ?>" style="font-size: 0.82rem; padding: 0.35rem 0.85rem; border: none;">All</a>
                <a href="<?= url('elections.php?filter=active') ?>" class="btn btn-sm <?= $filter === 'active' ? 'btn-primary' : 'btn-secondary' ?>" style="font-size: 0.82rem; padding: 0.35rem 0.85rem; border: none;">Live Polls</a>
                <a href="<?= url('elections.php?filter=upcoming') ?>" class="btn btn-sm <?= $filter === 'upcoming' ? 'btn-primary' : 'btn-secondary' ?>" style="font-size: 0.82rem; padding: 0.35rem 0.85rem; border: none;">Upcoming</a>
                <a href="<?= url('elections.php?filter=completed') ?>" class="btn btn-sm <?= $filter === 'completed' ? 'btn-primary' : 'btn-secondary' ?>" style="font-size: 0.82rem; padding: 0.35rem 0.85rem; border: none;">Completed</a>
            </div>
        </div>
    </div>

    <?php if (empty($elections)): ?>
        <div class="glass-card" style="text-align: center; padding: 3.5rem 2rem; background: var(--surface-card); border: 1px solid var(--border-subtle); border-radius: var(--radius-lg);">
            <div style="display: inline-flex; align-items: center; justify-content: center; width: 56px; height: 56px; border-radius: 50%; background: var(--surface-input); color: var(--accent-primary); margin-bottom: 1rem;">
                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
            </div>
            <h3 style="font-size: 1.25rem; color: var(--text-primary); margin-bottom: 0.4rem;">No Elections Found</h3>
            <p style="color: var(--text-muted); font-size: 0.92rem; margin: 0 0 1.25rem;">There are no elections matching the specified filter criteria.</p>
            <a href="<?= url('elections.php?filter=all') ?>" class="btn btn-secondary btn-sm">Reset Filter</a>
        </div>
    <?php else: ?>
        <div id="electionsGridContainer" class="elections-grid elections-cards-grid">
            <?php foreach ($elections as $elec): ?>
                <?= render_election_3d_card($elec, $candidatesByElection[$elec['id']] ?? [], $user, true) ?>
            <?php endforeach; ?>
        </div>

        <div id="noSearchResultsMsg" class="glass-card" style="display: none; text-align: center; padding: 3rem 2rem; margin-top: 1.5rem; background: var(--surface-card); border: 1px solid var(--border-subtle); border-radius: var(--radius-lg);">
            <p style="color: var(--text-muted); font-size: 0.95rem; margin: 0;">No electoral ballots match your search term.</p>
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.getElementById('electionsSearchInput');
    const cards = document.querySelectorAll('.election-discovery-card');
    const noResults = document.getElementById('noSearchResultsMsg');

    if (searchInput && cards.length > 0) {
        searchInput.addEventListener('input', () => {
            const query = searchInput.value.toLowerCase().trim();
            let visibleCount = 0;

            cards.forEach(card => {
                const text = card.getAttribute('data-search-text') || '';
                const match = text.includes(query);
                card.style.display = match ? 'flex' : 'none';
                if (match) visibleCount++;
            });

            if (noResults) {
                noResults.style.display = (visibleCount === 0 && query !== '') ? 'block' : 'none';
            }
        });
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
