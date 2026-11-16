<?php
/**
 * Global Footer Component
 */
$institution = get_setting('institution_name', 'ApexVote College Demonstration Campus');
$academicYear = get_setting('academic_year', '2026–2027');
$contactEmail = get_setting('contact_email', 'election.committee@apexcollege.edu.in');
$location = get_setting('institution_location', 'Kolkata, West Bengal, India');
?>

    <?php if ($isDashboard ?? false): ?>
            </main> <!-- /app-content -->
        </div> <!-- /app-main -->
    </div> <!-- /app-layout -->
    <?php else: ?>
    </main>
    <footer class="site-footer">
        <div class="container">
            <div class="footer-top">
                <div>
                    <div class="brand" style="margin-bottom: 1rem;">
                        <img src="<?= asset('images/logo.png') ?>" alt="ApexVote Logo" class="brand-icon" style="width: 32px; height: 32px; object-fit: contain;">
                        <span class="brand-title"><?= e(get_setting('site_name', 'ApexVote')) ?></span>
                    </div>
                    <p style="font-size: 0.9rem; max-width: 380px;">
                        The institutional college election management platform empowering democratic student representation, 
                        department representative elections, and student council governance with verifiable integrity.
                    </p>
                    <div style="margin-top: 1rem; color: var(--accent-primary); font-weight: 600; font-size: 0.85rem;">
                        <?= e($institution) ?> &bull; <?= e($location) ?> &bull; <?= e($academicYear) ?>
                    </div>
                </div>

                <div>
                    <h4 style="margin-bottom: 1rem; color: var(--text-primary);">Navigation</h4>
                    <ul style="list-style: none; display: flex; flex-direction: column; gap: 0.6rem;">
                        <li><a href="<?= url('index.php') ?>">Home</a></li>
                        <li><a href="<?= url('elections.php') ?>">Elections</a></li>
                        <li><a href="<?= url('results.php') ?>">Certified Results</a></li>
                        <li><a href="<?= url('about.php') ?>">About the Platform</a></li>
                        <li><a href="<?= url('contact.php') ?>">Election Committee Contact</a></li>
                    </ul>
                </div>

                <div>
                    <h4 style="margin-bottom: 1rem; color: var(--text-primary);">Voter Portal</h4>
                    <ul style="list-style: none; display: flex; flex-direction: column; gap: 0.6rem;">
                        <li><a href="<?= url('login.php') ?>">Sign In</a></li>
                        <li><a href="<?= url('register.php') ?>">New Student Registration</a></li>
                        <li><a href="<?= url('dashboard.php') ?>">Voter Dashboard</a></li>
                        <li><a href="<?= url('voting-history.php') ?>">Voting Receipts</a></li>
                    </ul>
                </div>

                <div>
                    <h4 style="margin-bottom: 1rem; color: var(--text-primary);">Security & Integrity</h4>
                    <p style="font-size: 0.85rem; margin-bottom: 0.75rem;">
                        Equipped with database-level row locking, secret ballot dissociation, 
                        and administrative audit trails.
                    </p>
                    <span class="status-badge badge-emerald">
                        <span class="status-indicator-ping"></span> Ballot Privacy Enforced
                    </span>
                </div>
            </div>

            <div class="footer-disclaimer">
                <p>
                    &copy; <?= date('Y') ?> <?= e(trim(str_ireplace('College Demonstration Campus', '', $institution)) ?: 'ApexVote') ?> &bull; <?= e($location) ?> &bull; Academic Session <?= e($academicYear) ?>
                </p>
            </div>
        </div>
    </footer>
    <?php endif; ?>

    <!-- Application JavaScript Engine with Automatic Cache Invalidation -->
    <script src="<?= asset('js/main.js') ?>?v=<?= @filemtime(__DIR__ . '/../assets/js/main.js') ?: time() ?>"></script>
</body>
</html>
