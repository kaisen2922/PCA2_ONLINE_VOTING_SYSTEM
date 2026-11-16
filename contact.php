<?php
/**
 * Electoral Board Contact & Helpdesk Portal
 */

require_once __DIR__ . '/includes/auth.php';

$pageTitle = 'Contact the Electoral Commission';
$isDashboard = false;
$submitted = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        $error = 'Session expired. Please try again.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $subject = trim($_POST['subject'] ?? '');
        $message = trim($_POST['message'] ?? '');

        if (empty($name) || empty($email) || empty($subject) || empty($message)) {
            $error = 'Please complete all required fields.';
        } else {
            // Log inquiry in audit logs
            audit_log('CONTACT_INQUIRY', "Inquiry from {$name} ({$email}): {$subject}");
            $submitted = true;
            set_flash('success', 'Your inquiry has been submitted to the Electoral Board. An officer will reply within 24 hours.');
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="container container-narrow" style="padding: 4.5rem 1.5rem;">
    <div style="text-align: center; margin-bottom: 3rem;">
        <span class="status-badge badge-category" style="margin-bottom: 0.75rem;">Official Helpdesk</span>
        <h1 style="margin-bottom: 1rem;">College Election Committee Assistance</h1>
        <p style="font-size: 1.15rem; max-width: 600px; margin: 0 auto; color: var(--text-secondary);">
            Need help with student voter registration, eligibility confirmation, or ballot inquiries? 
            Reach out to our college election officers in Kolkata.
        </p>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 2rem;">
        <!-- Contact Information Card -->
        <div class="glass-card">
            <h3 style="margin-bottom: 1.5rem; color: var(--text-primary); display: flex; align-items: center; gap: 0.65rem;">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="var(--accent-primary)" stroke-width="2"><path d="M3 21h18M3 10h18M5 10v11M19 10v11M9 10v11M15 10v11M12 2L2 7h20L12 2z"/></svg>
                Committee Office
            </h3>

            <div style="display: flex; flex-direction: column; gap: 1.5rem; font-size: 0.95rem;">
                <div>
                    <strong style="color: var(--text-primary); display: block; margin-bottom: 0.25rem;">Physical Location</strong>
                    <span style="color: var(--text-secondary);">
                        Administrative Building, 2nd Floor, Room 204<br>
                        <?= e(get_setting('institution_name', 'ApexVote College Demonstration Campus')) ?><br>
                        <?= e(get_setting('institution_location', 'Kolkata, West Bengal, India')) ?>
                    </span>
                </div>

                <div>
                    <strong style="color: var(--text-primary); display: block; margin-bottom: 0.25rem;">Direct Electoral Helpline</strong>
                    <span style="color: var(--accent-primary); font-weight: 600;"><?= e(get_setting('contact_phone', '+91 98300 12345')) ?></span>
                </div>

                <div>
                    <strong style="color: var(--text-primary); display: block; margin-bottom: 0.25rem;">Official Inquiries Email</strong>
                    <span style="color: var(--accent-primary);"><?= e(get_setting('contact_email', 'election.committee@apexcollege.edu.in')) ?></span>
                </div>

                <div>
                    <strong style="color: var(--text-primary); display: block; margin-bottom: 0.25rem;">Desk Hours during Polling</strong>
                    <span style="color: var(--text-secondary);">Monday &ndash; Friday: 10:00 AM &ndash; 05:00 PM IST<br>Polling days: 08:00 AM &ndash; 08:00 PM IST</span>
                </div>
            </div>
        </div>

        <!-- Support Request Form -->
        <div class="glass-card">
            <h3 style="margin-bottom: 1.5rem;">Submit an Inquiry</h3>

            <?php if (!empty($error)): ?>
                <div class="alert-box alert-danger">
                    <span>⚠️</span>
                    <span><?= e($error) ?></span>
                </div>
            <?php endif; ?>

            <?php if ($submitted): ?>
                <div class="alert-box alert-success">
                    <span>✅</span>
                    <span>Inquiry logged successfully. Reference ID: <strong>#REQ-<?= rand(1000, 9999) ?></strong></span>
                </div>
            <?php endif; ?>

            <form action="<?= url('contact.php') ?>" method="POST">
                <?= csrf_field() ?>

                <div class="form-group">
                    <label class="form-label" for="name">Your Name *</label>
                    <input type="text" name="name" id="name" class="form-control" 
                           value="<?= e($user['full_name'] ?? ($_POST['name'] ?? '')) ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="email">Institutional Email *</label>
                    <input type="email" name="email" id="email" class="form-control" 
                           value="<?= e($user['email'] ?? ($_POST['email'] ?? '')) ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="subject">Inquiry Subject *</label>
                    <select name="subject" id="subject" class="form-select" required>
                        <option value="Eligibility Inquiry">Eligibility Verification Question</option>
                        <option value="Password or Login Issue">Account Access or Password Reset</option>
                        <option value="Voter ID Card Discrepancy">Student / Voter ID Discrepancy</option>
                        <option value="Election Dispute or Feedback">Electoral Rule Clarification</option>
                        <option value="General Question">General Assistance</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="message">Detailed Explanation *</label>
                    <textarea name="message" id="message" rows="4" class="form-control" 
                              placeholder="Please describe your question or issue in detail..." required><?= e($_POST['message'] ?? '') ?></textarea>
                </div>

                <button type="submit" class="btn btn-primary btn-block">
                    Transmit Message &rarr;
                </button>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
