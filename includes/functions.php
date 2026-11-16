<?php
/**
 * Global Helper Functions & Utilities
 */

require_once __DIR__ . '/../config/database.php';

/**
 * Escape output for HTML (XSS prevention)
 */
function e(?string $value): string {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Extract safe client IP address
 */
function get_client_ip(): string {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($ips[0]);
    }
    return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
}

/**
 * Format timestamp nicely (Default: Indian Standard Time format)
 */
function format_date(?string $datetime, string $format = 'd M Y, h:i A \I\S\T'): string {
    if (!$datetime) return 'N/A';
    try {
        $dt = new DateTime($datetime);
        return $dt->format($format);
    } catch (Exception $e) {
        return $datetime;
    }
}

/**
 * Human-friendly relative time difference
 */
function time_ago(?string $datetime): string {
    if (!$datetime) return 'Never';
    try {
        $time = strtotime($datetime);
        $diff = time() - $time;

        if ($diff < 0) {
            $abs = abs($diff);
            if ($abs < 60) return 'in ' . $abs . 's';
            if ($abs < 3600) return 'in ' . round($abs / 60) . 'm';
            if ($abs < 86400) return 'in ' . round($abs / 3600) . 'h';
            return 'in ' . round($abs / 86400) . 'd';
        }

        if ($diff < 60) return 'Just now';
        if ($diff < 3600) return round($diff / 60) . 'm ago';
        if ($diff < 86400) return round($diff / 3600) . 'h ago';
        if ($diff < 604800) return round($diff / 86400) . 'd ago';
        return date('M j, Y', $time);
    } catch (Exception $e) {
        return $datetime;
    }
}

/**
 * Set session flash message
 */
function set_flash(string $type, string $message): void {
    $_SESSION['flash'] = [
        'type' => $type, // 'success', 'danger', 'warning', 'info'
        'message' => $message
    ];
}

/**
 * Retrieve and clear flash message
 */
function get_flash(): ?array {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Check if flash message exists
 */
function has_flash(): bool {
    return isset($_SESSION['flash']);
}

/**
 * Safe redirect helper
 */
function redirect(string $path): void {
    $target = (strpos($path, 'http://') === 0 || strpos($path, 'https://') === 0) 
        ? $path 
        : url($path);
    header("Location: " . $target);
    exit;
}

/**
 * Insert record into security audit log
 */
function audit_log(string $action, ?string $details = null, ?int $user_id = null): bool {
    try {
        $db = getDB();
        $uid = $user_id ?: ($_SESSION['user_id'] ?? null);
        $ip = get_client_ip();
        $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown', 0, 500);

        $stmt = $db->prepare("INSERT INTO audit_logs (user_id, action, ip_address, user_agent, details, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        return $stmt->execute([$uid, $action, $ip, $ua, $details]);
    } catch (Exception $e) {
        // Fallback fail-safe: don't crash main workflow on audit logging failure
        error_log("Audit log failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Fetch a setting value from database
 */
function get_setting(string $key, string $default = ''): string {
    static $cache = [];
    if (isset($cache[$key])) {
        return $cache[$key];
    }
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1");
        $stmt->execute([$key]);
        $val = $stmt->fetchColumn();
        $cache[$key] = ($val !== false) ? (string)$val : $default;
        return $cache[$key];
    } catch (Exception $e) {
        return $default;
    }
}

/**
 * Generate cryptographically secure receipt token
 */
function generate_receipt_token(int $userId, int $electionId): string {
    return hash('sha256', $userId . '-' . $electionId . '-' . microtime(true) . '-' . bin2hex(random_bytes(16)));
}

/**
 * Generate stylized badge HTML for election status
 */
function election_status_badge(string $status): string {
    $map = [
        'active'    => ['label' => 'Voting Live', 'class' => 'badge-emerald', 'icon' => 'pulse'],
        'draft'     => ['label' => 'Draft',       'class' => 'badge-amber',   'icon' => 'edit'],
        'completed' => ['label' => 'Concluded',   'class' => 'badge-slate',   'icon' => 'check-circle'],
        'cancelled' => ['label' => 'Cancelled',   'class' => 'badge-rose',    'icon' => 'slash']
    ];

    $cfg = $map[$status] ?? ['label' => ucfirst($status), 'class' => 'badge-slate', 'icon' => 'info'];
    $pulse = ($status === 'active') ? '<span class="status-indicator-ping"></span>' : '';
    
    return '<span class="status-badge ' . $cfg['class'] . '">' . $pulse . e($cfg['label']) . '</span>';
}

/**
 * Navigation active state helper
 * Supports single page or multiple pages (as multiple arguments or an array).
 * Checks both script filename and relative path to avoid admin/voter collisions.
 *
 * @param string|array ...$pages One or more page filenames (e.g. 'elections.php', 'vote.php')
 * @return string 'active' if current script matches, empty string otherwise
 */
function is_active_page(...$pages): string {
    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $currentFile = basename($scriptName);
    $isAdminDir = (strpos($scriptName, '/admin/') !== false);

    // Flatten argument list
    $targets = [];
    foreach ($pages as $p) {
        if (is_array($p)) {
            $targets = array_merge($targets, $p);
        } else {
            $targets[] = (string)$p;
        }
    }

    foreach ($targets as $target) {
        $target = trim($target);
        if ($target === '') continue;

        // If target specifies 'admin/something.php'
        if (strpos($target, 'admin/') === 0) {
            $adminTargetFile = basename($target);
            if ($isAdminDir && $currentFile === $adminTargetFile) {
                return 'active';
            }
        } elseif ($isAdminDir) {
            // Script is currently in /admin/
            if ($currentFile === basename($target)) {
                return 'active';
            }
        } else {
            // Script is currently in root (voter or public page)
            if ($currentFile === basename($target)) {
                return 'active';
            }
        }
    }

    return '';
}

/**
 * Validate Indian 10-digit mobile number format
 * Accepts: 9830012345, +91 9830012345, +91-9830012345, 09830012345
 */
function is_valid_indian_phone(?string $phone): bool {
    if (empty($phone)) return false;
    $clean = preg_replace('/[\s\-\(\)]/', '', $phone);
    return (bool)preg_match('/^(\+?91|0)?[6789]\d{9}$/', $clean);
}

/**
 * Standardize Indian phone number to +91 XXXXX XXXXX display
 */
function format_indian_phone(?string $phone): string {
    if (empty($phone)) return 'N/A';
    $digits = preg_replace('/\D/', '', $phone);
    if (strlen($digits) > 10) {
        $digits = substr($digits, -10);
    }
    if (strlen($digits) === 10) {
        return '+91 ' . substr($digits, 0, 5) . ' ' . substr($digits, 5);
    }
    return $phone;
}

/**
 * Check if the application is running in Demonstration Mode
 */
function is_demo_mode(): bool {
    return get_setting('demo_mode', '0') === '1';
}

/**
 * Official academic project demonstration disclaimer
 */
function get_demo_disclaimer(): string {
    $campus = get_setting('institution_name', 'ApexVote College Demonstration Campus');
    $location = get_setting('institution_location', 'Kolkata, West Bengal, India');
    return "DEMO MODE: {$campus} ({$location}). This is an academic demonstration project. Candidate profiles, panels and elections are simulated for demonstration purposes.";
}

/**
 * Format INR Currency
 */
function format_inr(float $amount): string {
    $symbol = get_setting('currency_symbol', '₹');
    return $symbol . ' ' . number_format($amount, 2);
}

/**
 * Render Interactive 3D Election Flip Card
 * Supports front (overview) and back (details) faces with genuine CSS 3D flip.
 * Strict ballot privacy: Never exposes selected candidate choice.
 *
 * @param array $elec Election database row
 * @param array $candidates Array of candidate rows for this election
 * @param array|null $user Current authenticated user or null for guest
 * @param bool $isDiscovery Whether this card is rendered in the searchable electoral ballots directory
 * @return string Rendered HTML markup
 */
function render_election_3d_card(array $elec, array $candidates = [], ?array $user = null, bool $isDiscovery = false): string {
    $isLoggedIn = !empty($user);
    $hasVoted = $isLoggedIn && ((int)($elec['has_voted'] ?? 0) === 1);
    $isEligible = $isLoggedIn && (($elec['eligibility_status'] ?? '') === 'eligible');
    $nomineesCount = (int)($elec['candidate_count'] ?? count($candidates));
    $status = $elec['status'] ?? 'draft';
    $isActive = ($status === 'active');
    $isCompleted = ($status === 'completed');
    $isClosingSoon = ($isActive && (strtotime($elec['end_time']) - time() < 86400));
    $resultsPublished = ((int)($elec['results_published'] ?? 0) === 1);
    $votedAt = $elec['voted_at'] ?? null;

    $scopeText = ($elec['eligibility_type'] === 'all') 
        ? 'All Enrolled Students' 
        : 'Dept of ' . ($elec['target_department'] ?? 'Specific Batch');

    $searchText = strtolower($elec['title'] . ' ' . $elec['category'] . ' ' . ($elec['target_department'] ?? '') . ' ' . $elec['description']);
    $discoveryAttr = $isDiscovery ? ' election-discovery-card" data-search-text="' . e($searchText) . '"' : '"';

    ob_start();
    ?>
    <div class="election-card-flipper-container<?= $discoveryAttr ?> data-card-id="<?= (int)$elec['id'] ?>">
        <div class="election-card-flipper">
            <!-- ================= FRONT FACE: Election Overview ================= -->
            <div class="election-card-face election-card-front election-card-item" aria-hidden="false">
                <div class="election-card-item-top">
                    <!-- Badges Row -->
                    <div class="election-card-badge-row">
                        <span class="status-badge badge-category">
                            <span class="category-text"><?= e($elec['category']) ?></span>
                        </span>
                        <div class="election-card-badge-cluster" style="display: inline-flex; align-items: center; gap: 0.45rem;">
                            <?= election_status_badge($status) ?>
                            <button type="button" class="card-flip-pill-btn" aria-label="Flip card for full details of <?= e($elec['title']) ?>" aria-expanded="false" title="Click to flip card">
                                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M21 12a9 9 0 0 0-9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg>
                                <span>Details ↗</span>
                            </button>
                        </div>
                    </div>

                    <!-- Title & Short Description -->
                    <h4 class="election-card-title-text"><?= e($elec['title']) ?></h4>
                    <p class="election-card-desc-text">
                        <?= e(strip_tags($elec['description'])) ?>
                    </p>

                    <!-- Metadata Panel -->
                    <div class="election-card-meta-panel">
                        <div class="meta-line">
                            <span class="meta-k">Eligibility Scope:</span>
                            <strong class="meta-v"><?= e($scopeText) ?></strong>
                        </div>
                        <div class="meta-line">
                            <span class="meta-k">Certified Nominees:</span>
                            <strong class="meta-v" style="color: var(--accent-primary);"><?= $nomineesCount ?> Candidates</strong>
                        </div>
                        <div class="meta-line">
                            <span class="meta-k">Voting Window:</span>
                            <span class="meta-v"><?= format_date($elec['start_time'], 'd M') ?> &ndash; <?= format_date($elec['end_time'], 'd M Y') ?></span>
                        </div>
                    </div>
                </div>

                <!-- Bottom Section with Timing & Actions -->
                <div class="election-card-item-bottom">
                    <div class="election-card-timing-strip">
                        <?php if ($isActive): ?>
                            <span>Closes: <?= format_date($elec['end_time'], 'd M, h:i A') ?></span>
                            <span style="color: <?= $isClosingSoon ? 'var(--danger)' : 'var(--accent-primary)' ?>; font-weight: 600;">
                                ⏳ <?= time_ago($elec['end_time']) ?>
                            </span>
                        <?php elseif ($isCompleted): ?>
                            <span>Concluded: <?= format_date($elec['end_time'], 'd M Y') ?></span>
                            <span style="color: var(--text-muted);"><?= $resultsPublished ? 'Certified Tallies' : 'Tally Pending' ?></span>
                        <?php else: ?>
                            <span>Scheduled: <?= format_date($elec['start_time'], 'd M Y') ?></span>
                            <span style="color: var(--text-muted);"><?= ucfirst($status) ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="election-card-actions-row">
                        <?php if ($isActive): ?>
                            <?php if ($hasVoted): ?>
                                <span class="front-vote-recorded-badge" title="Your ballot has been officially recorded">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                                    <span>Vote Recorded</span>
                                </span>
                                <a href="<?= url('voting-history.php') ?>" class="btn btn-secondary btn-sm" style="font-size: 0.82rem; padding: 0.5rem 0.75rem;">
                                    Receipt
                                </a>
                            <?php elseif ($isEligible): ?>
                                <a href="<?= url('vote.php?id=' . $elec['id']) ?>" class="btn btn-primary btn-sm" style="flex: 1 1 auto; justify-content: center; font-size: 0.85rem;">
                                    <span>Cast Vote Now</span>
                                    <span aria-hidden="true">&rarr;</span>
                                </a>
                            <?php elseif ($isLoggedIn): ?>
                                <span class="btn btn-secondary btn-sm" style="opacity: 0.7; cursor: not-allowed; font-size: 0.82rem; flex: 1 1 auto; justify-content: center;">
                                    Restricted Dept
                                </span>
                            <?php else: ?>
                                <a href="<?= url('login.php') ?>" class="btn btn-primary btn-sm" style="flex: 1 1 auto; justify-content: center; font-size: 0.85rem;">
                                    <span>Sign In to Vote</span>
                                </a>
                            <?php endif; ?>
                        <?php elseif ($isCompleted && $resultsPublished): ?>
                            <a href="<?= url('results.php?election_id=' . $elec['id']) ?>" class="btn btn-emerald btn-sm" style="font-size: 0.85rem; padding: 0.55rem 0.85rem;">
                                📊 Results
                            </a>
                        <?php endif; ?>

                        <button type="button" class="btn btn-secondary btn-sm election-card-flip-btn" aria-label="View Details and Flip Card for <?= e($elec['title']) ?>" aria-expanded="false" style="font-size: 0.85rem; padding: 0.55rem 0.85rem;">
                            <span>View Details ↗</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- ================= BACK FACE: Redesigned Editorial Details ================= -->
            <div class="election-card-face election-card-back" aria-hidden="true">
                <!-- TOP SECTION: Eyebrow, Title, Badges -->
                <div class="back-card-top">
                    <div class="back-card-eyebrow-row">
                        <span class="back-card-eyebrow">ELECTION DETAILS</span>
                        <button type="button" class="card-flip-back-pill-btn" aria-label="Back to Overview of <?= e($elec['title']) ?>" aria-expanded="true" title="Return to overview">
                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"/><path d="M21 3v5h-5"/></svg>
                            <span>Overview ↺</span>
                        </button>
                    </div>
                    <h4 class="back-card-title"><?= e($elec['title']) ?></h4>
                    <div class="back-card-badges">
                        <span class="status-badge badge-category">
                            <?= e($elec['category']) ?>
                        </span>
                        <?= election_status_badge($status) ?>
                    </div>
                </div>

                <!-- MIDDLE SECTION: Three Compact Editorial Information Panels -->
                <div class="back-card-middle">
                    <!-- A. Voter Eligibility -->
                    <div class="back-compact-section">
                        <div class="back-section-label">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                            <span>Voter Eligibility</span>
                        </div>
                        <div class="back-section-content">
                            <div class="back-info-row">
                                <span class="back-k">Scope:</span>
                                <strong class="back-v"><?= e($scopeText) ?></strong>
                            </div>
                            <div class="back-eligibility-indicator-row">
                                <?php if (!$isLoggedIn): ?>
                                    <span class="back-status-pill pill-guest">
                                        ℹ Sign in to verify your eligibility
                                    </span>
                                <?php elseif ($isEligible): ?>
                                    <span class="back-status-pill pill-eligible">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
                                        <span>Verified Eligible Voter</span>
                                    </span>
                                <?php else: ?>
                                    <span class="back-status-pill pill-ineligible">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                                        <span>Restricted: <?= e($elec['target_department'] ?? 'Department') ?> only</span>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- B. Candidates -->
                    <div class="back-compact-section">
                        <div class="back-section-label">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                            <span>Candidates (<?= $nomineesCount ?> Certified)</span>
                        </div>
                        <div class="back-section-content">
                            <?php if (!empty($candidates)): ?>
                                <div class="back-nominee-names-line" title="<?= e(implode(', ', array_column($candidates, 'full_name'))) ?>">
                                    <span class="back-nominee-text">
                                        <?= e(implode(' &bull; ', array_slice(array_column($candidates, 'full_name'), 0, 3))) ?>
                                        <?php if (count($candidates) > 3): ?>
                                            <span class="back-nominee-more">+<?= count($candidates) - 3 ?> more</span>
                                        <?php endif; ?>
                                    </span>
                                </div>
                            <?php else: ?>
                                <div class="back-nominee-empty">No certified nominees registered yet.</div>
                            <?php endif; ?>
                            <a href="<?= url('election-details.php?id=' . $elec['id']) ?>" class="back-compact-link">
                                <span>View all candidates &amp; manifestos &rarr;</span>
                            </a>
                        </div>
                    </div>

                    <!-- C. Election Schedule -->
                    <div class="back-compact-section">
                        <div class="back-section-label">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                            <span>Election Schedule (IST)</span>
                        </div>
                        <div class="back-section-content">
                            <div class="back-schedule-grid">
                                <div class="back-sched-item">
                                    <span class="back-k">Starts:</span>
                                    <span class="back-v"><?= format_date($elec['start_time'], 'd M Y, h:i A') ?></span>
                                </div>
                                <div class="back-sched-item">
                                    <span class="back-k">Closes:</span>
                                    <strong class="back-v back-highlight"><?= format_date($elec['end_time'], 'd M Y, h:i A \I\S\T') ?></strong>
                                </div>
                            </div>
                            <div class="back-participation-status-line">
                                <?php if ($hasVoted): ?>
                                    <span class="back-part-voted">
                                        ✓ Your participation is recorded<?= !empty($votedAt) ? ' (' . format_date($votedAt, 'd M, h:i A') . ')' : '' ?><?= $isCompleted ? ' &bull; Polling concluded on ' . format_date($elec['end_time'], 'd M Y') : '' ?>
                                    </span>
                                <?php elseif (!$isLoggedIn): ?>
                                    <span class="back-part-guest">Please sign in to participate</span>
                                <?php elseif (!$isEligible): ?>
                                    <span class="back-part-ineligible">Ineligible &ndash; Not registered for this department ballot</span>
                                <?php elseif ($isCompleted): ?>
                                    <span class="back-part-concluded">Polling concluded on <?= format_date($elec['end_time'], 'd M Y') ?></span>
                                <?php else: ?>
                                    <span class="back-part-pending">You haven't voted in this election yet</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- BOTTOM SECTION: Actions -->
                <div class="back-card-bottom">
                    <button type="button" class="btn btn-secondary btn-sm election-card-flip-back-btn" aria-label="Back to Overview of <?= e($elec['title']) ?>">
                        <span>&larr; Back to Overview</span>
                    </button>

                    <?php if ($hasVoted): ?>
                        <a href="<?= url('voting-history.php') ?>" class="btn btn-secondary btn-sm back-action-btn" style="border-color: rgba(56, 185, 130, 0.4); color: var(--success);">
                            <span>View Voting Receipt</span>
                        </a>
                    <?php elseif ($isEligible && $isActive): ?>
                        <a href="<?= url('election-details.php?id=' . $elec['id']) ?>" class="btn btn-primary btn-sm back-action-btn">
                            <span>View Candidates &amp; Manifestos</span>
                        </a>
                    <?php elseif ($isCompleted && $resultsPublished): ?>
                        <a href="<?= url('results.php?election_id=' . $elec['id']) ?>" class="btn btn-emerald btn-sm back-action-btn">
                            <span>View Certified Results</span>
                        </a>
                    <?php elseif ($isCompleted): ?>
                        <a href="<?= url('election-details.php?id=' . $elec['id']) ?>" class="btn btn-secondary btn-sm back-action-btn">
                            <span>View Nominee Archive</span>
                        </a>
                    <?php elseif (!$isLoggedIn): ?>
                        <a href="<?= url('login.php') ?>" class="btn btn-primary btn-sm back-action-btn">
                            <span>Sign In to Vote</span>
                        </a>
                    <?php else: ?>
                        <!-- Ineligible: No voting action, clean explanation provided above -->
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

