<?php
/**
 * Administrator Security Audit Trail Viewer
 * Comprehensive platform activity monitoring
 */

require_once __DIR__ . '/../includes/auth.php';
require_admin();

$pageTitle = 'Security Audit Logs';
$isDashboard = true;
$isAdmin = true;
$user = current_user();
$db = getDB();

$actionFilter = $_GET['action_filter'] ?? '';
$search = trim($_GET['search'] ?? '');

$sql = "
    SELECT a.*, u.full_name, u.email, u.voter_id_number 
    FROM audit_logs a
    LEFT JOIN users u ON a.user_id = u.id
    WHERE 1=1
";
$params = [];

if (!empty($actionFilter)) {
    $sql .= " AND a.action = ?";
    $params[] = $actionFilter;
}

if (!empty($search)) {
    $sql .= " AND (a.details LIKE ? OR a.ip_address LIKE ? OR u.full_name LIKE ? OR u.email LIKE ?)";
    $like = "%{$search}%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$sql .= " ORDER BY a.created_at DESC LIMIT 100";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Distinct action types for filter
$actionsList = $db->query("SELECT DISTINCT action FROM audit_logs ORDER BY action ASC")->fetchAll(PDO::FETCH_COLUMN);

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Header -->
<div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 2rem; flex-wrap: wrap; gap: 1.25rem;">
    <div>
        <h2>Security &amp; Governance Audit Logs</h2>
        <p>Detailed system logs of authentications, administrative changes, and anonymous ballot submissions.</p>
    </div>
    <span class="status-badge badge-emerald">
        <span class="status-indicator-ping"></span> Live Auditing Engine Active
    </span>
</div>

<!-- Filters Bar -->
<div class="glass-card" style="padding: 1.25rem; margin-bottom: 1.5rem;">
    <form action="<?= url('admin/audit-logs.php') ?>" method="GET" style="display: flex; gap: 1rem; align-items: center; flex-wrap: wrap;">
        <div style="flex: 1; min-width: 240px;">
            <input type="text" name="search" class="form-control" placeholder="Search by detail keyword, user, or IP address..." value="<?= e($search) ?>">
        </div>

        <div style="min-width: 200px;">
            <select name="action_filter" class="form-select">
                <option value="">All Action Types</option>
                <?php foreach ($actionsList as $act): ?>
                    <option value="<?= e($act) ?>" <?= ($actionFilter === $act) ? 'selected' : '' ?>>
                        <?= e($act) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <button type="submit" class="btn btn-primary">
            Filter Logs
        </button>
        <?php if (!empty($search) || !empty($actionFilter)): ?>
            <a href="<?= url('admin/audit-logs.php') ?>" class="btn btn-secondary">
                Reset
            </a>
        <?php endif; ?>
    </form>
</div>

<!-- Audit Log Table -->
<div class="glass-card" style="padding: 0; overflow: hidden;">
    <div class="table-responsive">
        <table class="custom-table">
            <thead>
                <tr>
                    <th>Log ID</th>
                    <th>Timestamp</th>
                    <th>Action Event</th>
                    <th>Authenticated User</th>
                    <th>IP Address & Client</th>
                    <th>Event Details</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                    <tr><td colspan="6" style="text-align:center;padding:2.5rem;">No audit records matching criteria.</td></tr>
                <?php else: ?>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td style="color: var(--text-muted); font-size: 0.82rem;">
                                #<?= (int)$log['id'] ?>
                            </td>
                            <td style="font-size: 0.85rem; color: var(--text-muted); white-space: nowrap;">
                                <?= format_date($log['created_at']) ?>
                            </td>
                            <td>
                                <code style="color: var(--color-primary); font-weight: 700; background: rgba(244, 203, 69, 0.12); border: 1px solid rgba(244, 203, 69, 0.25); padding: 0.25rem 0.55rem; border-radius: 4px; font-size: 0.8rem;">
                                    <?= e($log['action']) ?>
                                </code>
                            </td>
                            <td>
                                <?php if ($log['full_name']): ?>
                                    <strong style="color: var(--text-primary); font-size: 0.9rem; display: block;"><?= e($log['full_name']) ?></strong>
                                    <span style="font-size: 0.78rem; color: var(--text-muted);"><?= e($log['email']) ?></span>
                                <?php else: ?>
                                    <span style="color: var(--text-muted); font-style: italic;">Anonymous / System</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="font-family: monospace; font-size: 0.85rem; color: var(--color-text);"><?= e($log['ip_address']) ?></div>
                                <span style="font-size: 0.72rem; color: var(--text-muted); display: block; max-width: 180px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?= e($log['user_agent']) ?>">
                                    <?= e($log['user_agent']) ?>
                                </span>
                            </td>
                            <td style="font-size: 0.88rem; color: var(--text-secondary); max-width: 320px;">
                                <?= e($log['details']) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
