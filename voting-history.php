<?php
/**
 * Voter Participation History & Digital Receipt Verification
 * Strictly preserves Ballot Confidentiality (Choice is Never Exposed)
 * Refined for ApexVote Editorial Design Language
 */

require_once __DIR__ . '/includes/auth.php';
require_voter();

$pageTitle = 'Voting Receipts & History';
$isDashboard = true;
$isAdmin = false;
$user = current_user();
$db = getDB();

// Query voter's cast records
$stmt = $db->prepare("
    SELECT e.id as election_id, e.title, e.category, e.status, e.results_published,
           ve.voted_at, ve.receipt_token
    FROM voter_eligibility ve
    JOIN elections e ON ve.election_id = e.id
    WHERE ve.user_id = ? AND ve.has_voted = 1
    ORDER BY ve.voted_at DESC
");
$stmt->execute([$user['id']]);
$history = $stmt->fetchAll();

$totalReceiptsCount = count($history);

require_once __DIR__ . '/includes/header.php';
?>

<!-- Page Header -->
<div style="margin-bottom: 2rem;">
    <h2 style="font-size: 1.75rem; font-weight: 800; color: var(--text-primary); margin: 0 0 0.35rem; letter-spacing: -0.02em;">Voting Receipts &amp; History</h2>
    <p style="font-size: 0.92rem; color: var(--text-secondary); margin: 0;">Review collegiate elections you have participated in, access your cryptographic participation receipts, and verify certified tallies.</p>
</div>

<!-- Participation Summary Metric Strip -->
<div class="stats-grid" style="margin-bottom: 2rem;">
    <div class="stat-card">
        <div class="stat-icon stat-icon-emerald">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
        </div>
        <div class="stat-info">
            <div class="stat-val"><?= $totalReceiptsCount ?></div>
            <div class="stat-label">Official Receipts Issued</div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon stat-icon-yellow">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
        </div>
        <div class="stat-info">
            <div class="stat-val" style="font-size: 1.25rem; color: var(--accent-primary);">SHA-256 Token</div>
            <div class="stat-label">Cryptographic Integrity</div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon stat-icon-emerald">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
        </div>
        <div class="stat-info">
            <div class="stat-val" style="font-size: 1.25rem; color: var(--success);">Dissociated</div>
            <div class="stat-label">Ballot Choice Anonymity</div>
        </div>
    </div>
</div>

<!-- Ballot Privacy & Participation Receipt Card -->
<div class="glass-card" style="background: var(--surface-card); border: 1px solid var(--border-subtle); border-radius: var(--radius-lg); padding: 1.25rem 1.5rem; margin-bottom: 2rem; display: flex; align-items: flex-start; gap: 1rem;">
    <div style="display: inline-flex; align-items: center; justify-content: center; width: 40px; height: 40px; border-radius: 10px; background: rgba(244, 203, 69, 0.12); color: var(--accent-primary); flex-shrink: 0; margin-top: 2px;">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
    </div>
    <div style="flex: 1; font-size: 0.9rem; line-height: 1.6;">
        <strong style="color: var(--accent-primary); display: block; font-size: 0.95rem; margin-bottom: 0.2rem;">Ballot Privacy &amp; Database Separation</strong> 
        <span style="color: var(--text-secondary);">
            To protect voter privacy, student voter records and cast ballots are stored in separate database tables without storing voter identity keys in the ballot table. Your participation receipt confirms that the system recorded your participation in this election. It does not display your candidate selection.
        </span>
    </div>
</div>

<?php if (empty($history)): ?>
    <!-- Refined Compact Empty State -->
    <div class="glass-card" style="text-align: center; padding: 3.5rem 2rem; max-width: 540px; margin: 2rem auto; background: var(--surface-card); border: 1px solid var(--border-subtle); border-radius: var(--radius-lg);">
        <div style="display: inline-flex; align-items: center; justify-content: center; width: 54px; height: 54px; border-radius: 50%; background: var(--surface-input); color: var(--accent-primary); margin-bottom: 1.25rem;">
            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg>
        </div>
        <h3 style="font-size: 1.25rem; font-weight: 700; color: var(--text-primary); margin: 0 0 0.5rem;">No Participation Records Yet</h3>
        <p style="color: var(--text-secondary); font-size: 0.92rem; line-height: 1.55; margin: 0 0 1.5rem;">You have not cast ballots in any election cycles yet. Active ballots for your academic department will appear in the elections catalog.</p>
        <a href="<?= url('elections.php') ?>" class="btn btn-primary btn-sm" style="padding: 0.65rem 1.4rem;">
            Explore Active Elections &rarr;
        </a>
    </div>
<?php else: ?>
    <!-- Populated Participation Records Table -->
    <div class="glass-card" style="padding: 0; overflow: hidden; background: var(--surface-card); border: 1px solid var(--border-subtle); border-radius: var(--radius-lg);">
        <div class="table-responsive">
            <table class="custom-table receipts-table">
                <thead>
                    <tr>
                        <th style="min-width: 220px;">Election</th>
                        <th style="min-width: 170px;">Election Category</th>
                        <th style="min-width: 170px;">Participation Date</th>
                        <th style="min-width: 280px;">Receipt Reference</th>
                        <th style="min-width: 210px;">Status / Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($history as $item): ?>
                        <tr>
                            <td class="receipt-col-election">
                                <strong class="receipt-election-name"><?= e($item['title']) ?></strong>
                                <span class="receipt-election-id">Ballot ID: #<?= (int)$item['election_id'] ?></span>
                            </td>
                            <td class="receipt-col-category">
                                <span class="status-badge badge-category">
                                    <?= e($item['category']) ?>
                                </span>
                            </td>
                            <td class="receipt-col-date">
                                <span class="receipt-date-primary"><?= format_date($item['voted_at'], 'd M Y') ?></span>
                                <span class="receipt-date-secondary"><?= format_date($item['voted_at'], 'h:i A \I\S\T') ?></span>
                            </td>
                            <td class="receipt-col-reference">
                                <div class="receipt-ref-wrap">
                                    <code class="receipt-ref-box" id="ref-<?= (int)$item['election_id'] ?>" title="<?= e($item['receipt_token']) ?>">
                                        <?= substr(e($item['receipt_token']), 0, 14) ?>&hellip;<?= substr(e($item['receipt_token']), -8) ?>
                                    </code>
                                    <button type="button" class="btn btn-secondary btn-xs btn-copy-ref" 
                                            onclick="copyFullReference('<?= e($item['receipt_token']) ?>', this)" 
                                            title="Copy full 64-character receipt reference">
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="14" height="14" x="8" y="8" rx="2" ry="2"></rect><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"></path></svg>
                                        <span>Copy</span>
                                    </button>
                                </div>
                            </td>
                            <td class="receipt-col-action">
                                <div class="receipt-btn-group">
                                    <button type="button" class="btn btn-secondary btn-sm" 
                                            onclick="openReceiptModal(<?= htmlspecialchars(json_encode([
                                                'title' => $item['title'],
                                                'category' => $item['category'],
                                                'election_id' => (int)$item['election_id'],
                                                'voted_at' => format_date($item['voted_at'], 'd M Y, h:i A \I\S\T'),
                                                'reference' => $item['receipt_token'],
                                                'voter_name' => $user['full_name'],
                                                'roll_no' => $user['college_roll_no'] ?: $user['voter_id_number'],
                                                'department' => $user['department']
                                            ]), ENT_QUOTES, 'UTF-8') ?>)">
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                                        <span>View Receipt</span>
                                    </button>

                                    <?php if ($item['results_published'] == 1): ?>
                                        <a href="<?= url('results.php?election_id=' . $item['election_id']) ?>" class="btn btn-emerald btn-sm" title="View Certified Election Results">
                                            <span>Results &rarr;</span>
                                        </a>
                                    <?php else: ?>
                                        <span class="status-badge badge-warning" style="font-size: 0.72rem;">Pending</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<!-- View Receipt Modal with Print-Friendly Layout -->
<div id="receiptModal" class="modal-overlay" style="display: none;" role="dialog" aria-modal="true" aria-labelledby="receiptModalHeading">
    <div class="modal-content receipt-modal-dialog" style="max-width: 600px;">
        <div class="modal-header">
            <h3 id="receiptModalHeading" style="display: flex; align-items: center; gap: 0.6rem; font-size: 1.2rem; margin: 0; color: var(--text-primary);">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="var(--accent-primary)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                Official Participation Receipt
            </h3>
            <button type="button" onclick="closeReceiptModal()" style="background: none; border: none; color: var(--text-muted); font-size: 1.5rem; cursor: pointer; line-height: 1;" aria-label="Close modal">&times;</button>
        </div>

        <div class="modal-body" id="receiptPrintableArea" style="padding: 1.5rem;">
            <!-- Institutional Watermark Header (Printed & Displayed) -->
            <div style="text-align: center; border-bottom: 1.5px solid var(--border-subtle); padding-bottom: 1rem; margin-bottom: 1.25rem;">
                <div style="font-size: 1.1rem; font-weight: 800; color: var(--text-primary); letter-spacing: 0.04em;">
                    ApexVote &bull; College Electoral Management
                </div>
                <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 0.2rem;">
                    Institutional Democratic Ballot Certification Record
                </div>
            </div>

            <!-- Participation Confirmation Box -->
            <div style="background: rgba(244, 203, 69, 0.08); border: 1px solid rgba(244, 203, 69, 0.3); border-radius: var(--radius-md); padding: 0.95rem 1.15rem; margin-bottom: 1.4rem; font-size: 0.85rem; line-height: 1.55; color: var(--text-secondary);">
                <strong style="color: var(--accent-primary); display: block; margin-bottom: 0.25rem;">Participation Confirmation</strong>
                This digital receipt certifies that your official ballot was recorded into the institutional ledger. In compliance with strict secret ballot principles, candidate selection is permanently dissociated.
            </div>

            <!-- Receipt Metadata Grid -->
            <div class="receipt-summary-grid">
                <div class="receipt-summary-item">
                    <span class="summary-label">Election Title</span>
                    <strong class="summary-value" id="modal-election-title">-</strong>
                </div>
                <div class="receipt-summary-item">
                    <span class="summary-label">Category</span>
                    <span class="status-badge badge-category" id="modal-election-category">-</span>
                </div>
                <div class="receipt-summary-item">
                    <span class="summary-label">Participation Timestamp</span>
                    <strong class="summary-value" id="modal-voted-at">-</strong>
                </div>
                <div class="receipt-summary-item">
                    <span class="summary-label">Voter Credentials</span>
                    <span class="summary-value" id="modal-voter-info">-</span>
                </div>
            </div>

            <!-- Monospace Full Reference Display -->
            <div style="margin-top: 1.25rem;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.45rem;">
                    <span style="font-size: 0.78rem; font-weight: 700; text-transform: uppercase; color: var(--text-muted); letter-spacing: 0.05em;">
                        Cryptographic Receipt Reference (SHA-256)
                    </span>
                    <span style="font-size: 0.74rem; color: var(--accent-primary); font-family: var(--font-mono, monospace);">64-character hash</span>
                </div>
                <div style="position: relative;">
                    <textarea id="modal-full-reference" readonly class="form-control" 
                              style="font-family: var(--font-mono, monospace); font-size: 0.82rem; height: 68px; resize: none; background: var(--surface-input); color: var(--accent-primary); line-height: 1.45; word-break: break-all; padding: 0.75rem 0.85rem; border: 1px solid var(--border-subtle);"></textarea>
                </div>
            </div>
        </div>

        <div class="modal-footer" style="padding: 1rem 1.5rem; display: flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--border-subtle);">
            <button type="button" class="btn btn-secondary btn-sm" onclick="printReceiptRecord()">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                <span>Print Receipt</span>
            </button>
            <div style="display: flex; gap: 0.75rem;">
                <button type="button" class="btn btn-secondary btn-sm" onclick="closeReceiptModal()">Close</button>
                <button type="button" class="btn btn-primary btn-sm" id="modal-copy-btn" onclick="copyModalReference()">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="14" height="14" x="8" y="8" rx="2" ry="2"></rect><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"></path></svg>
                    <span>Copy Reference</span>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function copyFullReference(text, btn) {
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

function openReceiptModal(data) {
    document.getElementById('modal-election-title').textContent = data.title;
    document.getElementById('modal-election-category').textContent = data.category;
    document.getElementById('modal-voted-at').textContent = data.voted_at;
    document.getElementById('modal-voter-info').textContent = data.voter_name + ' (' + data.roll_no + ') - ' + data.department;
    document.getElementById('modal-full-reference').value = data.reference;

    const modal = document.getElementById('receiptModal');
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeReceiptModal() {
    const modal = document.getElementById('receiptModal');
    modal.style.display = 'none';
    document.body.style.overflow = '';
}

function copyModalReference() {
    const ref = document.getElementById('modal-full-reference').value;
    const btn = document.getElementById('modal-copy-btn');
    copyFullReference(ref, btn);
}

function printReceiptRecord() {
    window.print();
}

document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('receiptModal');
    if (modal) {
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                closeReceiptModal();
            }
        });
    }
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && modal && modal.style.display === 'flex') {
            closeReceiptModal();
        }
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
