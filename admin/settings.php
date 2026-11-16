<?php
/**
 * Administrator Electoral System Configuration
 */

require_once __DIR__ . '/../includes/auth.php';
require_admin();

$pageTitle = 'Platform Settings';
$isDashboard = true;
$isAdmin = true;
$user = current_user();
$db = getDB();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        $error = 'Security session expired. Please refresh and try again.';
    } else {
        $settingsToUpdate = [
            'site_name' => trim($_POST['site_name'] ?? 'ApexVote – College Electoral Management System'),
            'institution_name' => trim($_POST['institution_name'] ?? 'ApexVote College Demonstration Campus'),
            'institution_location' => trim($_POST['institution_location'] ?? 'Kolkata, West Bengal, India'),
            'academic_year' => trim($_POST['academic_year'] ?? '2026–2027'),
            'currency_symbol' => trim($_POST['currency_symbol'] ?? '₹'),
            'demo_mode' => isset($_POST['demo_mode']) ? '1' : '0',
            'allow_registration' => isset($_POST['allow_registration']) ? '1' : '0',
            'contact_email' => trim($_POST['contact_email'] ?? 'election.committee@apexcollege.edu.in'),
            'contact_phone' => trim($_POST['contact_phone'] ?? '+91 98300 12345'),
            'voting_mode' => trim($_POST['voting_mode'] ?? 'standard')
        ];

        $stmt = $db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()");
        foreach ($settingsToUpdate as $key => $val) {
            $stmt->execute([$key, $val]);
        }

        audit_log('SETTINGS_UPDATED', 'Admin modified institutional election configurations', $user['id']);
        set_flash('success', 'System configurations updated successfully!');
        redirect('admin/settings.php');
    }
}

// Fetch current settings
$settings = [];
$rows = $db->query("SELECT setting_key, setting_value FROM settings")->fetchAll();
foreach ($rows as $r) {
    $settings[$r['setting_key']] = $r['setting_value'];
}

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Header -->
<div style="margin-bottom: 2rem;">
    <h2>Electoral Platform Configuration</h2>
    <p>Configure institution branding, self-registration rules, and contact information.</p>
</div>

<?php if (!empty($error)): ?>
    <div class="alert-box alert-danger">
        <span>⚠️</span>
        <span><?= e($error) ?></span>
    </div>
<?php endif; ?>

<div class="glass-card" style="max-width: 780px;">
    <form action="<?= url('admin/settings.php') ?>" method="POST">
        <?= csrf_field() ?>

        <h3 style="margin-bottom: 1.25rem; color: var(--accent-cyan); display: flex; align-items: center; gap: 0.5rem;">
            <span>🏛️</span> Institution & Brand Identity
        </h3>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem; margin-bottom: 2rem;">
            <div class="form-group" style="grid-column: span 2;">
                <label class="form-label" for="site_name">Platform Name *</label>
                <input type="text" name="site_name" id="site_name" class="form-control" 
                       value="<?= e($settings['site_name'] ?? 'ApexVote – College Electoral Management System') ?>" required>
            </div>

            <div class="form-group">
                <label class="form-label" for="institution_name">College / Academic Institution Name *</label>
                <input type="text" name="institution_name" id="institution_name" class="form-control" 
                       value="<?= e($settings['institution_name'] ?? 'ApexVote College Demonstration Campus') ?>" required>
            </div>

            <div class="form-group">
                <label class="form-label" for="institution_location">Campus Regional Location *</label>
                <input type="text" name="institution_location" id="institution_location" class="form-control" 
                       value="<?= e($settings['institution_location'] ?? 'Kolkata, West Bengal, India') ?>" required>
            </div>

            <div class="form-group">
                <label class="form-label" for="academic_year">Academic Session / Cycle *</label>
                <input type="text" name="academic_year" id="academic_year" class="form-control" 
                       value="<?= e($settings['academic_year'] ?? '2026–2027') ?>" required>
            </div>

            <div class="form-group">
                <label class="form-label" for="currency_symbol">Currency Symbol</label>
                <input type="text" name="currency_symbol" id="currency_symbol" class="form-control" 
                       value="<?= e($settings['currency_symbol'] ?? '₹') ?>" required>
            </div>
        </div>

        <h3 style="margin-bottom: 1.25rem; color: var(--accent-indigo); display: flex; align-items: center; gap: 0.5rem; border-top: 1px solid var(--border-subtle); padding-top: 1.5rem;">
            <span>🗳️</span> Voter Registration & Balloting Policy
        </h3>

        <div style="margin-bottom: 2rem;">
            <div class="form-group">
                <label style="display: flex; align-items: center; gap: 0.75rem; cursor: pointer; background: rgba(255,255,255,0.03); padding: 1rem; border-radius: var(--radius-md); border: 1px solid var(--border-subtle); margin-bottom: 1rem;">
                    <input type="checkbox" name="allow_registration" value="1" 
                           <?= (($settings['allow_registration'] ?? '1') === '1') ? 'checked' : '' ?> 
                           style="width: 20px; height: 20px; accent-color: var(--color-primary);">
                    <div>
                        <strong style="color: var(--text-primary); display: block;">Allow Open Student Self-Registration</strong>
                        <span style="font-size: 0.85rem; color: var(--text-secondary);">When enabled, students can register directly from the public portal without prior administrator entry.</span>
                    </div>
                </label>
            </div>

            <div class="form-group">
                <label style="display: flex; align-items: center; gap: 0.75rem; cursor: pointer; background: rgba(255,255,255,0.03); padding: 1rem; border-radius: var(--radius-md); border: 1px solid var(--border-subtle); margin-bottom: 1rem;">
                    <input type="checkbox" name="demo_mode" value="1" 
                           <?= (($settings['demo_mode'] ?? '1') === '1') ? 'checked' : '' ?> 
                           style="width: 20px; height: 20px; accent-color: var(--color-primary);">
                    <div>
                        <strong style="color: var(--text-primary); display: block;">Demonstration Mode Banner</strong>
                        <span style="font-size: 0.85rem; color: var(--text-secondary);">Displays the academic demonstration notice banner at the top of the portal.</span>
                    </div>
                </label>
            </div>

            <div class="form-group">
                <label class="form-label" for="voting_mode">Ballot Security Protocol</label>
                <select name="voting_mode" id="voting_mode" class="form-select">
                    <option value="standard" <?= (($settings['voting_mode'] ?? '') === 'standard') ? 'selected' : '' ?>>Standard ACID Row-Locked Anonymity (Recommended)</option>
                    <option value="high-security" <?= (($settings['voting_mode'] ?? '') === 'high-security') ? 'selected' : '' ?>>High Security (Double SHA-256 Hashes)</option>
                </select>
            </div>
        </div>

        <h3 style="margin-bottom: 1.25rem; color: var(--accent-emerald); display: flex; align-items: center; gap: 0.5rem; border-top: 1px solid var(--border-subtle); padding-top: 1.5rem;">
            <span>📞</span> College Election Committee Helpdesk Information
        </h3>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem; margin-bottom: 2rem;">
            <div class="form-group">
                <label class="form-label" for="contact_email">Election Committee Official Email</label>
                <input type="email" name="contact_email" id="contact_email" class="form-control" 
                       value="<?= e($settings['contact_email'] ?? 'election.committee@apexcollege.edu.in') ?>" required>
            </div>

            <div class="form-group">
                <label class="form-label" for="contact_phone">Direct Support Helpline (India)</label>
                <input type="text" name="contact_phone" id="contact_phone" class="form-control" 
                       value="<?= e($settings['contact_phone'] ?? '+91 98300 12345') ?>" required>
            </div>
        </div>

        <button type="submit" class="btn btn-primary btn-lg">
            Save System Configurations &rarr;
        </button>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
