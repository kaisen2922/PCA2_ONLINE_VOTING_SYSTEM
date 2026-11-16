<?php
/**
 * Administrator Candidate Management
 * Add, edit, remove certified electoral candidates
 */

require_once __DIR__ . '/../includes/auth.php';
require_admin();

$pageTitle = 'Manage Candidates';
$isDashboard = true;
$isAdmin = true;
$user = current_user();
$db = getDB();

$error = '';
$selectedElectionId = (int)($_GET['election_id'] ?? 0);
$editCandidate = null;

// Fetch all elections for dropdown
$elections = $db->query("SELECT id, title, category, status FROM elections ORDER BY id DESC")->fetchAll();

if ($selectedElectionId <= 0 && !empty($elections)) {
    $selectedElectionId = (int)$elections[0]['id'];
}

// Check if editing a candidate
$editId = (int)($_GET['edit'] ?? 0);
if ($editId > 0) {
    $stmt = $db->prepare("SELECT * FROM candidates WHERE id = ? LIMIT 1");
    $stmt->execute([$editId]);
    $editCandidate = $stmt->fetch();
    if ($editCandidate) {
        $selectedElectionId = (int)$editCandidate['election_id'];
    }
}

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        $error = 'Security session expired. Please refresh and try again.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'create' || $action === 'update') {
            $electionId = (int)($_POST['election_id'] ?? 0);
            $fullName = trim($_POST['full_name'] ?? '');
            $position = trim($_POST['position'] ?? '');
            $partyOrSlate = trim($_POST['party_or_slate'] ?? 'Independent');
            $ballotNumber = (int)($_POST['ballot_number'] ?? 1);
            $manifesto = trim($_POST['manifesto'] ?? '');
            $photoUrl = trim($_POST['photo_url'] ?? 'assets/images/default-avatar.svg');

            if ($electionId <= 0 || empty($fullName) || empty($position) || empty($manifesto)) {
                $error = 'Please fill out all required candidate details.';
            } else {
                if ($action === 'create') {
                    $stmt = $db->prepare("
                        INSERT INTO candidates (election_id, full_name, position, party_or_slate, ballot_number, manifesto, photo_url, vote_count, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, 0, NOW())
                    ");
                    $stmt->execute([$electionId, $fullName, $position, $partyOrSlate, $ballotNumber, $manifesto, $photoUrl]);
                    $newCandId = (int)$db->lastInsertId();

                    audit_log('CANDIDATE_ADDED', "Added candidate #{$newCandId}: {$fullName} to election #{$electionId}", $user['id']);
                    set_flash('success', "Candidate '{$fullName}' has been certified for the ballot!");
                    redirect('admin/manage-candidates.php?election_id=' . $electionId);
                } else {
                    $candidateId = (int)($_POST['candidate_id'] ?? 0);
                    $stmt = $db->prepare("
                        UPDATE candidates 
                        SET election_id = ?, full_name = ?, position = ?, party_or_slate = ?, ballot_number = ?, manifesto = ?, photo_url = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$electionId, $fullName, $position, $partyOrSlate, $ballotNumber, $manifesto, $photoUrl, $candidateId]);

                    audit_log('CANDIDATE_UPDATED', "Updated candidate #{$candidateId}: {$fullName}", $user['id']);
                    set_flash('success', "Candidate profile updated successfully!");
                    redirect('admin/manage-candidates.php?election_id=' . $electionId);
                }
            }
        } elseif ($action === 'delete') {
            $candidateId = (int)$_POST['candidate_id'];
            $stmt = $db->prepare("DELETE FROM candidates WHERE id = ?");
            $stmt->execute([$candidateId]);

            audit_log('CANDIDATE_DELETED', "Deleted candidate #{$candidateId}", $user['id']);
            set_flash('success', "Candidate removed from election ballot.");
            redirect('admin/manage-candidates.php?election_id=' . $selectedElectionId);
        }
    }
}

// Fetch candidates for selected election
$candidates = [];
if ($selectedElectionId > 0) {
    $cStmt = $db->prepare("
        SELECT c.*, 
               COUNT(b.id) as counted_ballots
        FROM candidates c
        LEFT JOIN ballots b ON c.id = b.candidate_id
        WHERE c.election_id = ?
        GROUP BY c.id
        ORDER BY c.ballot_number ASC, c.id ASC
    ");
    $cStmt->execute([$selectedElectionId]);
    $candidates = $cStmt->fetchAll();
}

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Header -->
<div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 2rem; flex-wrap: wrap; gap: 1.25rem;">
    <div>
        <h2>Candidate & Student Panel Roster</h2>
        <p>Certify, organize, and manage candidate manifestos across collegiate elections.</p>
    </div>

    <div style="display: flex; gap: 1rem; align-items: flex-end; flex-wrap: wrap;">
        <div>
            <label class="form-label" for="filter-election">Filter by Election:</label>
            <select id="filter-election" class="form-select" onchange="window.location.href='<?= url('admin/manage-candidates.php') ?>?election_id=' + this.value;">
                <?php foreach ($elections as $el): ?>
                    <option value="<?= (int)$el['id'] ?>" <?= ($el['id'] == $selectedElectionId) ? 'selected' : '' ?>>
                        <?= e($el['title']) ?> (<?= e($el['status']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <button type="button" class="btn btn-primary" data-modal-target="#candidate-form-modal">
            + Certify New Candidate
        </button>
    </div>
</div>

<?php if (!empty($error)): ?>
    <div class="alert-box alert-danger">
        <span>⚠️</span>
        <span><?= e($error) ?></span>
    </div>
<?php endif; ?>

<!-- Candidate Roster Table -->
<div class="glass-card" style="padding: 0; overflow: hidden;">
    <div class="table-responsive">
        <table class="custom-table">
            <thead>
                <tr>
                    <th>Ballot #</th>
                    <th>Nominee Name & Photo</th>
                    <th>Running Office</th>
                    <th>Student Panel / Slate</th>
                    <th>Manifesto Preview</th>
                    <th>Tally Count</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($candidates)): ?>
                    <tr><td colspan="7" style="text-align:center;padding:2.5rem;">No candidates registered for this election yet. Click "Certify New Candidate" above.</td></tr>
                <?php else: ?>
                    <?php foreach ($candidates as $cand): ?>
                        <tr>
                            <td>
                                <span class="status-badge badge-slate" style="font-weight: 800;">#<?= (int)$cand['ballot_number'] ?></span>
                            </td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 0.85rem;">
                                    <img src="<?= asset('images/' . ($cand['photo_url'] ?: 'default-avatar.svg')) ?>" 
                                         alt="<?= e($cand['full_name']) ?>" style="width: 44px; height: 44px; border-radius: 50%;">
                                    <strong style="color: var(--text-primary); font-size: 0.95rem;"><?= e($cand['full_name']) ?></strong>
                                </div>
                            </td>
                            <td>
                                <div style="color: var(--color-primary); font-weight: 600;"><?= e($cand['position']) ?></div>
                            </td>
                            <td>
                                <span class="status-badge badge-blue"><?= e($cand['party_or_slate']) ?></span>
                            </td>
                            <td>
                                <span style="font-size: 0.85rem; color: var(--text-secondary);">
                                    <?= e(mb_strimwidth($cand['manifesto'], 0, 70, '...')) ?>
                                </span>
                            </td>
                            <td>
                                <strong style="color: var(--color-text); font-size: 1rem;"><?= max((int)$cand['vote_count'], (int)$cand['counted_ballots']) ?></strong>
                            </td>
                            <td>
                                <div style="display: flex; gap: 0.4rem;">
                                    <a href="<?= url('admin/manage-candidates.php?edit=' . $cand['id']) ?>" class="btn btn-secondary btn-sm" title="Edit Candidate">
                                        ✏️ Edit
                                    </a>
                                    <form action="<?= url('admin/manage-candidates.php?election_id=' . $selectedElectionId) ?>" method="POST" style="display:inline;" onsubmit="return confirm('Remove candidate <?= addslashes($cand['full_name']) ?> from the ballot?');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="candidate_id" value="<?= (int)$cand['id'] ?>">
                                        <button type="submit" class="btn btn-danger btn-sm" title="Delete Candidate">🗑️</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Candidate Modal -->
<div id="candidate-form-modal" class="modal-overlay <?= ($editCandidate || isset($_GET['action'])) ? 'open' : '' ?>">
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-header">
            <h3><?= $editCandidate ? 'Edit Candidate Profile' : 'Certify Candidate for Ballot' ?></h3>
            <button type="button" data-close-modal style="background:none;border:none;color:var(--text-muted);font-size:1.5rem;cursor:pointer;">&times;</button>
        </div>

        <form action="<?= url('admin/manage-candidates.php?election_id=' . $selectedElectionId) ?>" method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="<?= $editCandidate ? 'update' : 'create' ?>">
            <?php if ($editCandidate): ?>
                <input type="hidden" name="candidate_id" value="<?= (int)$editCandidate['id'] ?>">
            <?php endif; ?>

            <div class="modal-body" style="max-height: 75vh; overflow-y: auto;">
                <div class="form-group">
                    <label class="form-label" for="election_id">Target Election *</label>
                    <select name="election_id" id="election_id" class="form-select" required>
                        <?php foreach ($elections as $el): ?>
                            <option value="<?= (int)$el['id'] ?>" <?= (($editCandidate['election_id'] ?? $selectedElectionId) == $el['id']) ? 'selected' : '' ?>>
                                <?= e($el['title']) ?> (<?= e($el['category']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="full_name">Candidate Full Name *</label>
                    <input type="text" name="full_name" id="full_name" class="form-control" 
                           placeholder="e.g. Maya Lin" value="<?= e($editCandidate['full_name'] ?? '') ?>" required>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label class="form-label" for="position">Office / Position *</label>
                        <input type="text" name="position" id="position" class="form-control" 
                               placeholder="e.g. President" value="<?= e($editCandidate['position'] ?? '') ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="party_or_slate">Student Panel or Slate *</label>
                        <input type="text" name="party_or_slate" id="party_or_slate" class="form-control" 
                               placeholder="e.g. Progress & Development Panel or Independent" value="<?= e($editCandidate['party_or_slate'] ?? 'Independent') ?>" required>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label class="form-label" for="ballot_number">Ballot Number Position</label>
                        <input type="number" name="ballot_number" id="ballot_number" class="form-control" 
                               min="1" max="99" value="<?= (int)($editCandidate['ballot_number'] ?? 1) ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="photo_url">Avatar Graphic</label>
                        <select name="photo_url" id="photo_url" class="form-select">
                            <?php for ($i = 1; $i <= 8; $i++): ?>
                                <option value="candidate<?= $i ?>.svg" <?= (($editCandidate['photo_url'] ?? '') === "candidate{$i}.svg") ? 'selected' : '' ?>>Avatar <?= $i ?></option>
                            <?php endfor; ?>
                            <option value="default-avatar.svg">Default Silhouette</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="manifesto">Platform Manifesto Declaration *</label>
                    <textarea name="manifesto" id="manifesto" rows="4" class="form-control" 
                              placeholder="Detail the candidate's agenda, campaign pledges, and goals..." required><?= e($editCandidate['manifesto'] ?? '') ?></textarea>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" data-close-modal class="btn btn-secondary">Cancel</button>
                <button type="submit" class="btn btn-primary">
                    <?= $editCandidate ? 'Save Candidate' : 'Certify Candidate' ?> &rarr;
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
