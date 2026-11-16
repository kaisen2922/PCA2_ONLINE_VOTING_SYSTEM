<?php
/**
 * ApexVote Collegiate Electoral Platform
 * Redesigned Homepage Hero & Collegiate Broad-Sheet Experience
 */

require_once __DIR__ . '/includes/auth.php';

$pageTitle = 'Democratic Collegiate Electoral Platform';
$isDashboard = false;

$db = getDB();

// 1. Fetch real platform statistics strictly from database
$totalVoters = (int)$db->query("SELECT COUNT(*) FROM users WHERE role = 'voter'")->fetchColumn();
$activeElections = (int)$db->query("SELECT COUNT(*) FROM elections WHERE status = 'active'")->fetchColumn();
$totalBallots = (int)$db->query("SELECT COUNT(*) FROM ballots")->fetchColumn();
$totalAuditLogs = (int)$db->query("SELECT COUNT(*) FROM audit_logs")->fetchColumn();

// 2. Fetch primary active election for the Hero Live Election Preview Panel
$primaryElectionStmt = $db->query("
    SELECT * FROM elections 
    WHERE status = 'active' 
    ORDER BY (category = 'College Students\' Council' OR category = 'Student Council') DESC, end_time ASC 
    LIMIT 1
");
$primaryElection = $primaryElectionStmt->fetch();

// Fallback to most recent election if no active election is running
if (!$primaryElection) {
    $primaryElectionStmt = $db->query("SELECT * FROM elections ORDER BY id DESC LIMIT 1");
    $primaryElection = $primaryElectionStmt->fetch();
}

$heroStats = [
    'candidate_count' => 0,
    'votes_cast' => 0,
    'eligible_count' => 0,
    'turnout_pct' => 0,
    'candidates' => [],
    'has_real_closing_date' => false,
    'end_timestamp_iso' => '',
    'user_has_voted' => false
];

if ($primaryElection) {
    $eId = (int)$primaryElection['id'];
    
    // Real candidate count
    $heroStats['candidate_count'] = (int)$db->query("SELECT COUNT(*) FROM candidates WHERE election_id = {$eId}")->fetchColumn();
    
    // Real votes cast in this election
    $heroStats['votes_cast'] = (int)$db->query("SELECT COUNT(*) FROM ballots WHERE election_id = {$eId}")->fetchColumn();
    
    // Real eligible voter count
    $elig = (int)$db->query("SELECT COUNT(*) FROM voter_eligibility WHERE election_id = {$eId}")->fetchColumn();
    $heroStats['eligible_count'] = $elig > 0 ? $elig : max($totalVoters, 1);
    
    // Real turnout calculation
    $heroStats['turnout_pct'] = $heroStats['eligible_count'] > 0 
        ? round(($heroStats['votes_cast'] / $heroStats['eligible_count']) * 100, 1) 
        : 0;
        
    // Real candidate previews (up to 3 for ballot card)
    $candStmt = $db->prepare("SELECT * FROM candidates WHERE election_id = ? ORDER BY ballot_number ASC LIMIT 3");
    $candStmt->execute([$eId]);
    $heroStats['candidates'] = $candStmt->fetchAll();
    
    // Real closing countdown verification (only if date is in the future)
    if (!empty($primaryElection['end_time']) && $primaryElection['end_time'] !== '0000-00-00 00:00:00') {
        $endTime = strtotime($primaryElection['end_time']);
        if ($endTime > time()) {
            $heroStats['has_real_closing_date'] = true;
            $heroStats['end_timestamp_iso'] = date('c', $endTime);
        }
    }
    
    // Check if current logged-in user has already participated
    if (is_logged_in()) {
        $currentUser = current_user();
        $votedCheck = $db->prepare("SELECT has_voted FROM voter_eligibility WHERE election_id = ? AND user_id = ?");
        $votedCheck->execute([$eId, $currentUser['id']]);
        $heroStats['user_has_voted'] = (bool)$votedCheck->fetchColumn();
    }
}

// 3. Fetch active & featured elections for the "Democracy starts on campus" section
$allElectionsStmt = $db->query("
    SELECT e.*, 
        (SELECT COUNT(*) FROM candidates WHERE election_id = e.id) AS cand_count,
        (SELECT COUNT(*) FROM ballots WHERE election_id = e.id) AS ballot_count
    FROM elections e 
    WHERE e.status IN ('active', 'draft', 'completed')
    ORDER BY (e.status = 'active') DESC, (e.status = 'draft') DESC, e.end_time DESC 
    LIMIT 3
");
$campusElections = $allElectionsStmt->fetchAll();

// 4. Fetch featured candidates across active elections
$featuredCandidatesStmt = $db->query("
    SELECT c.*, e.title AS election_title, e.category AS election_category 
    FROM candidates c
    JOIN elections e ON c.election_id = e.id
    WHERE e.status = 'active'
    ORDER BY c.election_id ASC, c.ballot_number ASC
    LIMIT 3
");
$featuredCandidates = $featuredCandidatesStmt->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<!-- ================= HERO SECTION (ASYMMETRIC TWO-COLUMN COMPOSITION) ================= -->
<section class="editorial-hero">
    <!-- Subtle Animated Architectural Orbital Backdrop (Thin hairline vectors, subtle background detail) -->
    <div class="hero-orbital-backdrop" aria-hidden="true">
        <svg class="orbital-svg" viewBox="0 0 1000 800" fill="none" xmlns="http://www.w3.org/2000/svg">
            <defs>
                <linearGradient id="orbitalGrad1" x1="0%" y1="0%" x2="100%" y2="100%">
                    <stop offset="0%" stop-color="#F4CB45" stop-opacity="0.10"/>
                    <stop offset="50%" stop-color="#DDB63F" stop-opacity="0.04"/>
                    <stop offset="100%" stop-color="#E8B84A" stop-opacity="0"/>
                </linearGradient>
                <linearGradient id="orbitalGrad2" x1="100%" y1="100%" x2="0%" y2="0%">
                    <stop offset="0%" stop-color="#F4CB45" stop-opacity="0.08"/>
                    <stop offset="100%" stop-color="#DDB63F" stop-opacity="0.02"/>
                </linearGradient>
            </defs>
            <!-- Outer Elliptical Orbital Ring -->
            <ellipse cx="660" cy="380" rx="360" ry="240" stroke="url(#orbitalGrad1)" stroke-width="1" stroke-dasharray="6 10" class="orbit-rotate-slow"/>
            <!-- Inner Counter-rotating Precision Orbit -->
            <ellipse cx="660" cy="380" rx="260" ry="170" stroke="url(#orbitalGrad2)" stroke-width="1" class="orbit-rotate-reverse"/>
            <!-- Abstract Coordinate Wireframe -->
            <g class="orbit-geometry" transform="translate(660, 380)">
                <line x1="-380" y1="0" x2="380" y2="0" stroke="rgba(246, 238, 220, 0.02)" stroke-width="1"/>
                <line x1="0" y1="-260" x2="0" y2="260" stroke="rgba(246, 238, 220, 0.02)" stroke-width="1"/>
                <circle cx="0" cy="0" r="2.5" fill="rgba(244, 203, 69, 0.25)"/>
                <circle cx="260" cy="0" r="2.5" fill="#F4CB45" opacity="0.45" class="orbiting-node"/>
                <circle cx="-180" cy="120" r="2" fill="#DDB63F" opacity="0.35" class="orbiting-node-alt"/>
                <path d="M-40 -35 L40 -35 L30 35 L-30 35 Z" stroke="rgba(244, 203, 69, 0.06)" stroke-width="1" fill="none"/>
                <line x1="-20" y1="-35" x2="20" y2="-35" stroke="rgba(244, 203, 69, 0.16)" stroke-width="1.5"/>
            </g>
        </svg>
    </div>

    <div class="container">
        <div class="hero-grid">
            
            <!-- LEFT COLUMN: EDITORIAL STATEMENT & CTAS (First in DOM & Mobile) -->
            <div class="hero-editorial-col">
                
                <!-- Eyebrow: Crisp Swiss Micro-badge -->
                <div class="hero-eyebrow">
                    <span class="eyebrow-ping" aria-hidden="true"></span>
                    <span class="eyebrow-label">YOUR CAMPUS. YOUR VOICE. YOUR DECISION.</span>
                    <span class="eyebrow-rule" aria-hidden="true"></span>
                </div>

                <!-- Editorial Headline: Selected distinctive words without entire gradient -->
                <h1 class="hero-editorial-title">
                    The <span class="editorial-serif">future</span> of your campus is on the <span class="ballot-highlight">ballot</span>.
                </h1>

                <!-- Supporting Copy -->
                <p class="hero-editorial-desc">
                    A smarter, transparent and accessible way for students to shape their campus.
                </p>

                <!-- Primary & Secondary CTAs -->
                <div class="hero-editorial-actions">
                    <a href="#campus-elections" class="btn btn-hero-primary">
                        <span>Explore Elections</span>
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M5 12h14M12 5l7 7-7 7"/>
                        </svg>
                    </a>

                    <a href="#how-it-works" class="btn btn-hero-secondary">
                        <span>How It Works</span>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M19 9l-7 7-7-7"/>
                        </svg>
                    </a>
                </div>

                <!-- Small Editorial Trust Indicators -->
                <div class="hero-trust-indicators">
                    <div class="trust-pill">
                        <div class="trust-icon" aria-hidden="true">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#F4CB45" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                                <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                            </svg>
                        </div>
                        <span>Secret Ballot Protection</span>
                    </div>

                    <div class="trust-pill">
                        <div class="trust-icon" aria-hidden="true">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#F4CB45" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                                <circle cx="12" cy="7" r="4"/>
                            </svg>
                        </div>
                        <span>Verified Student Access</span>
                    </div>

                    <div class="trust-pill">
                        <div class="trust-icon" aria-hidden="true">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#F4CB45" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                                <polyline points="22 4 12 14.01 9 11.01"/>
                            </svg>
                        </div>
                        <span>Duplicate Vote Prevention</span>
                    </div>
                </div>

                <!-- ================= EDITORIAL VOTING PROCESS (HERO BALANCING TIMELINE) ================= -->
                <div class="hero-process-section" id="hero-voting-process">
                    <!-- Subtle Oversized Typographic Watermark -->
                    <span class="hero-process-watermark" aria-hidden="true">2026 / 27</span>

                    <!-- Process Header -->
                    <div class="hero-process-header">
                        <div class="hero-process-eyebrow">
                            <span class="process-eyebrow-dash" aria-hidden="true"></span>
                            <span>THE VOTING PROCESS</span>
                        </div>
                        <h2 class="hero-process-title">Your voice, in three simple steps.</h2>
                        <p class="hero-process-desc">Participating in your college election is straightforward.</p>
                    </div>

                    <!-- Editorial Timeline Track / Steps -->
                    <div class="hero-process-timeline" role="list">
                        
                        <!-- Step 01 -->
                        <div class="hero-process-step" role="listitem">
                            <div class="process-step-indicator">
                                <span class="process-step-num">01</span>
                                <span class="process-connector-line" aria-hidden="true"></span>
                            </div>
                            <div class="process-step-content">
                                <h3 class="process-step-name">Verify Your Student Identity</h3>
                                <p class="process-step-text">Sign in using your registered college credentials.</p>
                            </div>
                        </div>

                        <!-- Step 02 -->
                        <div class="hero-process-step" role="listitem">
                            <div class="process-step-indicator">
                                <span class="process-step-num">02</span>
                                <span class="process-connector-line" aria-hidden="true"></span>
                            </div>
                            <div class="process-step-content">
                                <h3 class="process-step-name">Explore Candidates</h3>
                                <p class="process-step-text">Review candidate profiles, manifestos and election details.</p>
                            </div>
                        </div>

                        <!-- Step 03 -->
                        <div class="hero-process-step" role="listitem">
                            <div class="process-step-indicator">
                                <span class="process-step-num">03</span>
                            </div>
                            <div class="process-step-content">
                                <h3 class="process-step-name">Cast Your Vote</h3>
                                <p class="process-step-text">Confirm your selection and receive a voting confirmation.</p>
                            </div>
                        </div>

                    </div> <!-- /hero-process-timeline -->
                </div> <!-- /hero-process-section -->

            </div> <!-- /hero-editorial-col -->

            <!-- RIGHT COLUMN: REFINED LIVE ELECTION PREVIEW PANEL -->
            <div class="hero-preview-col">
                <div class="live-election-panel">
                    
                    <!-- Architectural Corner Crosshairs -->
                    <span class="corner-crosshair top-left" aria-hidden="true">+</span>
                    <span class="corner-crosshair top-right" aria-hidden="true">+</span>
                    <span class="corner-crosshair bottom-left" aria-hidden="true">+</span>
                    <span class="corner-crosshair bottom-right" aria-hidden="true">+</span>

                    <!-- Panel Header -->
                    <div class="panel-header">
                        <div class="panel-meta-badges">
                            <?php if ($primaryElection && $primaryElection['status'] === 'active'): ?>
                                <span class="badge-live-pulse">
                                     <span class="live-dot" aria-hidden="true"></span>
                                     <span>LIVE SESSION</span>
                                </span>
                            <?php else: ?>
                                <span class="badge-status-upcoming">UPCOMING BALLOT</span>
                            <?php endif; ?>
                            <span class="badge-category"><?= e($primaryElection['category'] ?? 'Campus Ballot') ?></span>
                        </div>
                        
                        <div class="panel-protocol-seal" title="Election Committee Certified">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                            </svg>
                            <span>COMMITTEE CERTIFIED</span>
                        </div>
                    </div>

                    <!-- Election Title & Info -->
                    <div class="panel-title-block">
                        <h2 class="panel-election-title">
                            <?= e($primaryElection['title'] ?? 'College General Election') ?>
                        </h2>
                        <p class="panel-election-dept">
                            <?= e($primaryElection['eligibility_type'] === 'department' ? 'Department of ' . ($primaryElection['target_department'] ?? 'Specialized Studies') : 'College-wide Student Voting') ?>
                        </p>
                    </div>

                    <!-- Subtle Internal Election Timeline -->
                    <div class="panel-micro-timeline" aria-label="Electoral Milestone Timeline">
                        <div class="micro-timeline-step is-complete">
                            <span class="micro-step-dot">✓</span>
                            <span class="micro-step-name">Nominations</span>
                        </div>
                        <span class="micro-timeline-bar is-complete" aria-hidden="true"></span>
                        <div class="micro-timeline-step is-complete">
                            <span class="micro-step-dot">✓</span>
                            <span class="micro-step-name">Manifestos</span>
                        </div>
                        <span class="micro-timeline-bar is-active" aria-hidden="true"></span>
                        <div class="micro-timeline-step is-active">
                            <span class="micro-step-dot live-ping"></span>
                            <span class="micro-step-name">Voting Open</span>
                        </div>
                        <span class="micro-timeline-bar" aria-hidden="true"></span>
                        <div class="micro-timeline-step is-upcoming">
                            <span class="micro-step-dot">○</span>
                            <span class="micro-step-name">Audit</span>
                        </div>
                    </div>

                    <!-- Closing Countdown Ticker (Compact & Real DB Time) -->
                    <?php if ($heroStats['has_real_closing_date']): ?>
                        <div class="panel-countdown-bar" 
                             id="electionCountdownModule" 
                             data-end-time="<?= e($heroStats['end_timestamp_iso']) ?>"
                             aria-label="Election closing countdown timer">
                            <div class="countdown-badge-label">
                                <span class="countdown-live-icon" aria-hidden="true">⏱️</span>
                                <span>POLLING CLOSES IN:</span>
                            </div>
                            <div class="countdown-compact-ticker">
                                <span class="cd-digit" id="cdDays">00</span><span class="cd-unit">d</span>
                                <span class="cd-sep">:</span>
                                <span class="cd-digit" id="cdHours">00</span><span class="cd-unit">h</span>
                                <span class="cd-sep">:</span>
                                <span class="cd-digit" id="cdMinutes">00</span><span class="cd-unit">m</span>
                                <span class="cd-sep">:</span>
                                <span class="cd-digit" id="cdSeconds">00</span><span class="cd-unit">s</span>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="panel-countdown-bar is-static">
                            <span class="countdown-badge-label">POLLING SCHEDULE:</span>
                            <span class="cd-static-val"><?= $primaryElection ? 'Closes: ' . format_date($primaryElection['end_time'], 'M j, Y') : 'Session Scheduled' ?></span>
                        </div>
                    <?php endif; ?>

                    <!-- Circular Participation Progress Indicator & Metrics -->
                    <?php
                        $turnoutVal = min(max((float)$heroStats['turnout_pct'], 0), 100);
                        $circumference = 163.36; // 2 * pi * 26
                        $dashOffset = round($circumference * (1 - ($turnoutVal / 100)), 1);
                    ?>
                    <div class="panel-participation-row">
                        <div class="circular-gauge-wrap">
                            <svg width="56" height="56" viewBox="0 0 64 64" class="circular-turnout-gauge" aria-hidden="true">
                                <circle cx="32" cy="32" r="26" fill="none" stroke="rgba(255, 255, 255, 0.08)" stroke-width="4.5"/>
                                <circle cx="32" cy="32" r="26" fill="none" stroke="url(#turnoutGaugeGrad)" stroke-width="4.5"
                                        stroke-dasharray="163.36" stroke-dashoffset="<?= $dashOffset ?>"
                                        stroke-linecap="round" transform="rotate(-90 32 32)"/>
                                <defs>
                                    <linearGradient id="turnoutGaugeGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                                        <stop offset="0%" stop-color="#DDB63F"/>
                                        <stop offset="100%" stop-color="#F4CB45"/>
                                    </linearGradient>
                                </defs>
                                <text x="32" y="37" text-anchor="middle" font-family="'JetBrains Mono', monospace" font-size="12" font-weight="700" fill="#F6EEDC">
                                    <?= $heroStats['turnout_pct'] ?>%
                                </text>
                            </svg>
                        </div>
                        <div class="participation-meta">
                            <div class="participation-title">VOTER PARTICIPATION</div>
                            <div class="participation-stats">
                                <strong><?= (int)$heroStats['votes_cast'] ?></strong> of <strong><?= (int)$heroStats['eligible_count'] ?></strong> ballots recorded
                            </div>
                            <div class="participation-sub">
                                <span><?= (int)$heroStats['candidate_count'] ?> Candidates Running</span> &bull; <span class="concurrency-pill">Row Lock Protected</span>
                            </div>
                        </div>
                    </div>

                    <!-- Subtle Interactive Ballot Card Visual with Real Candidate Photos -->
                    <div class="interactive-ballot-card" id="heroInteractiveBallot">
                        <div class="ballot-perforation-edge" aria-hidden="true"></div>
                        
                        <div class="ballot-card-header">
                            <div class="ballot-card-id">
                                <span class="ballot-chip-icon">🏷️</span>
                                <span>OFFICIAL DIGITAL BALLOT #<?= e($primaryElection['id'] ?? '1') ?></span>
                            </div>
                            <span class="ballot-security-tag">SECRET BALLOT</span>
                        </div>

                        <div class="ballot-candidate-preview-list">
                            <div class="ballot-preview-instructions">
                                <span>Candidate List Preview</span>
                                <span class="micro-stamp">CLICK TO PREVIEW SELECTION</span>
                            </div>

                            <?php if (!empty($heroStats['candidates'])): ?>
                                <?php foreach ($heroStats['candidates'] as $idx => $cand): ?>
                                    <?php 
                                        $cleanPhoto = preg_replace('#^assets/#', '', $cand['photo_url'] ?? '') ?: 'images/default-avatar.svg';
                                    ?>
                                    <div class="ballot-candidate-item <?= $idx === 0 ? 'is-selected' : '' ?>" 
                                         data-candidate-id="<?= (int)$cand['id'] ?>"
                                         data-candidate-name="<?= e($cand['full_name']) ?>"
                                         role="button"
                                         tabindex="0"
                                         aria-label="Select candidate <?= e($cand['full_name']) ?>">
                                        <div class="ballot-radio-emblem">
                                            <span class="radio-check-symbol">✓</span>
                                        </div>
                                        <img src="<?= asset($cleanPhoto) ?>" 
                                             alt="<?= e($cand['full_name']) ?>" 
                                             class="ballot-cand-avatar">
                                        <div class="ballot-candidate-details">
                                            <div class="ballot-candidate-name"><?= e($cand['full_name']) ?></div>
                                            <div class="ballot-candidate-slate"><?= e($cand['party_or_slate']) ?></div>
                                        </div>
                                        <span class="ballot-number-stamp">#0<?= (int)$cand['ballot_number'] ?></span>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="ballot-candidate-empty">
                                    <span>Candidates slate under final election commission review.</span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Card Action & Receipt Simulation -->
                        <div class="ballot-card-footer">
                            <div class="ballot-token-receipt">
                                <span class="receipt-label">SHA-256 Token Preview:</span>
                                <code class="receipt-hash">8f3c7...9a21b</code>
                            </div>

                            <?php if ($primaryElection && $primaryElection['status'] === 'active'): ?>
                                <?php if (is_logged_in()): ?>
                                    <a href="<?= url('vote.php?id=' . $primaryElection['id']) ?>" class="btn-ballot-vote">
                                        <span>Cast Official Ballot</span>
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                                    </a>
                                <?php else: ?>
                                    <a href="<?= url('login.php?redirect=' . urlencode('vote.php?id=' . $primaryElection['id'])) ?>" class="btn-ballot-vote">
                                        <span>Authenticate ID to Vote</span>
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                                    </a>
                                <?php endif; ?>
                            <?php else: ?>
                                <a href="<?= url('elections.php') ?>" class="btn-ballot-vote">
                                    <span>View All Elections</span>
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                                </a>
                            <?php endif; ?>
                        </div>

                    </div> <!-- /interactive-ballot-card -->

                </div> <!-- /live-election-panel -->
            </div> <!-- /hero-preview-col -->

        </div> <!-- /hero-grid -->
    </div> <!-- /container -->
</section>

<!-- ================= COMPACT ELECTION INFORMATION STRIP (SECTION TRANSITION) ================= -->
<div class="campus-governance-strip">
    <div class="container">
        <div class="governance-strip-inner">
            <div class="governance-strip-item">
                <span class="strip-icon" aria-hidden="true">🏛️</span>
                <div class="strip-content">
                    <span class="strip-label">Electoral Authority</span>
                    <span class="strip-val">College Election Committee</span>
                </div>
            </div>
            
            <div class="strip-divider" aria-hidden="true"></div>

            <div class="governance-strip-item">
                <span class="strip-icon" aria-hidden="true">🗳️</span>
                <div class="strip-content">
                    <span class="strip-label">Current Cycle</span>
                    <span class="strip-val">Academic Session <?= e(get_setting('academic_year', '2026–2027')) ?></span>
                </div>
            </div>

            <div class="strip-divider" aria-hidden="true"></div>

            <div class="governance-strip-item">
                <span class="strip-icon" aria-hidden="true">🔒</span>
                <div class="strip-content">
                    <span class="strip-label">Privacy Assurance</span>
                    <span class="strip-val">Anonymous Ballot Decoupling</span>
                </div>
            </div>

            <div class="strip-divider" aria-hidden="true"></div>

            <div class="governance-strip-item">
                <span class="strip-icon" aria-hidden="true">📜</span>
                <div class="strip-content">
                    <span class="strip-label">Audit Protocol</span>
                    <span class="strip-val">SHA-256 Participation Receipts</span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ================= THE KOLKATA COLLEGE NETWORK: 3D RUBIK'S CUBE INSTALLATION ================= -->
<section class="kolkata-network-section" id="kolkata-network">
    <!-- Atmospheric Ambient Visuals -->
    <div class="network-ambient-backdrop" aria-hidden="true">
        <div class="network-radial-glow"></div>
        <div class="network-grid-rules"></div>
    </div>

    <div class="container">
        <!-- Section Broad-sheet Editorial Header -->
        <div class="network-header-wrap">
            <div class="network-eyebrow">
                <span class="network-eyebrow-dot" aria-hidden="true"></span>
                <span>THE KOLKATA COLLEGE NETWORK</span>
            </div>
            <h2 class="network-title">
                One City. Many Campuses. <span class="network-title-serif">Countless Voices.</span>
            </h2>
            <p class="network-desc">
                Explore the diverse educational landscape of Kolkata through the institutions that shape its next generation.
            </p>
        </div>

        <!-- 3D Cube Stage Container -->
        <div class="cube-stage-wrapper" id="cubeStageWrapper">
            <!-- Stage Status Indicator -->
            <div class="cube-status-pill">
                <span class="cube-status-indicator" aria-hidden="true"></span>
                <span>WebGL 3D &bull; 54 Campus Tiles</span>
            </div>

            <!-- Floating Stage HUD Controls -->
            <div class="cube-hud-controls">
                <button type="button" class="cube-btn" id="btnShuffleCube" title="Shuffle Cube">
                    <span class="cube-btn-icon" aria-hidden="true">🔀</span>
                    <span class="cube-btn-label">Shuffle</span>
                </button>
                <button type="button" class="cube-btn" id="btnResetCube" title="Reset Camera View">
                    <span class="cube-btn-icon" aria-hidden="true">🎯</span>
                    <span class="cube-btn-label">Reset View</span>
                </button>
                <button type="button" class="cube-btn" id="btnToggleAutoRotate" title="Toggle Idle Drift">
                    <span class="cube-btn-icon" aria-hidden="true">🔄</span>
                    <span class="cube-btn-label">Pause Drift</span>
                </button>
            </div>

            <!-- WebGL Canvas Container -->
            <div class="cube-canvas-container" id="kolkataCubeContainer" tabindex="0" role="region" aria-label="Interactive 3D Kolkata College Rubik's Cube"></div>

            <!-- Dynamic Floating Tile Tooltip -->
            <div class="cube-tile-tooltip" id="cubeTileTooltip" role="tooltip" aria-hidden="true"></div>

            <!-- Graceful CSS 3D Isometric Fallback if WebGL unavailable -->
            <div class="cube-fallback-wrapper" id="cubeFallbackView">
                <div class="css-isometric-cube">
                    <div class="css-cube-face front">
                        <div class="css-face-tile">CU</div><div class="css-face-tile">JU</div><div class="css-face-tile">PU</div>
                        <div class="css-face-tile">SXCC</div><div class="css-face-tile">IIMC</div><div class="css-face-tile">BESC</div>
                        <div class="css-face-tile">HITK</div><div class="css-face-tile">TIU</div><div class="css-face-tile">BC</div>
                    </div>
                    <div class="css-cube-face back">
                        <div class="css-face-tile">AC</div><div class="css-face-tile">SCC</div><div class="css-face-tile">MAC</div>
                        <div class="css-face-tile">LC</div><div class="css-face-tile">LBC</div><div class="css-face-tile">GCCBA</div>
                        <div class="css-face-tile">SSC</div><div class="css-face-tile">AU</div><div class="css-face-tile">SNU</div>
                    </div>
                    <div class="css-cube-face right">
                        <div class="css-face-tile">JIS</div><div class="css-face-tile">IEM</div><div class="css-face-tile">CU</div>
                        <div class="css-face-tile">JU</div><div class="css-face-tile">PU</div><div class="css-face-tile">SXCC</div>
                        <div class="css-face-tile">BESC</div><div class="css-face-tile">HITK</div><div class="css-face-tile">IIMC</div>
                    </div>
                    <div class="css-cube-face left">
                        <div class="css-face-tile">BC</div><div class="css-face-tile">LC</div><div class="css-face-tile">LBC</div>
                        <div class="css-face-tile">SCC</div><div class="css-face-tile">MAC</div><div class="css-face-tile">AC</div>
                        <div class="css-face-tile">TIU</div><div class="css-face-tile">SNU</div><div class="css-face-tile">JIS</div>
                    </div>
                    <div class="css-cube-face top">
                        <div class="css-face-tile">CU</div><div class="css-face-tile">PU</div><div class="css-face-tile">JU</div>
                        <div class="css-face-tile">SXCC</div><div class="css-face-tile">IIMC</div><div class="css-face-tile">AC</div>
                        <div class="css-face-tile">BESC</div><div class="css-face-tile">SCC</div><div class="css-face-tile">HITK</div>
                    </div>
                    <div class="css-cube-face bottom">
                        <div class="css-face-tile">TIU</div><div class="css-face-tile">AU</div><div class="css-face-tile">SNU</div>
                        <div class="css-face-tile">JIS</div><div class="css-face-tile">IEM</div><div class="css-face-tile">GCCBA</div>
                        <div class="css-face-tile">SSC</div><div class="css-face-tile">LBC</div><div class="css-face-tile">LC</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stage Bottom Interaction Instructions & Modal Trigger -->
        <div class="cube-stage-footer">
            <div class="cube-interaction-hint">
                <span>Drag to rotate</span>
                <span class="hint-dot" aria-hidden="true">&bull;</span>
                <span>Hover to explore</span>
                <span class="hint-dot" aria-hidden="true">&bull;</span>
                <span>Click to discover</span>
            </div>
            <button type="button" class="btn-explore-directory" id="btnOpenCollegeDirectory">
                <span aria-hidden="true">🏛️</span>
                <span>Explore College Directory</span>
            </button>
        </div>
    </div>
</section>

<!-- College Info Modal / Popover -->
<div class="college-drawer-backdrop" id="collegeDrawerBackdrop" aria-hidden="true"></div>
<div class="college-info-drawer" id="collegeInfoDrawer" role="dialog" aria-modal="true" aria-labelledby="drawerFullName">
    <div class="drawer-top-bar">
        <div class="drawer-badge-group">
            <span class="drawer-abbr-badge" id="drawerAbbr">CU</span>
            <span class="drawer-verify-tag">
                <span aria-hidden="true">✓</span>
                <span>Demonstration Entry</span>
            </span>
        </div>
        <button type="button" class="drawer-close-btn" id="drawerCloseBtn" aria-label="Close dialog">&times;</button>
    </div>
    <h3 class="drawer-college-name" id="drawerFullName">University of Calcutta</h3>
    <div class="drawer-meta-grid">
        <div class="drawer-meta-item">
            <span class="drawer-meta-label">Category</span>
            <span class="drawer-meta-val" id="drawerCategory">Public State University</span>
        </div>
        <div class="drawer-meta-item">
            <span class="drawer-meta-label">Campus Location</span>
            <span class="drawer-meta-val" id="drawerLocation">College Street, Central Kolkata</span>
        </div>
        <div class="drawer-meta-item">
            <span class="drawer-meta-label">Establishment</span>
            <span class="drawer-meta-val" id="drawerEst">Est. 1857</span>
        </div>
        <div class="drawer-meta-item">
            <span class="drawer-meta-label">Region</span>
            <span class="drawer-meta-val">Kolkata, West Bengal</span>
        </div>
    </div>
    <p class="drawer-desc-box" id="drawerDesc"></p>
    <div class="drawer-actions-row">
        <a href="#" target="_blank" rel="noopener noreferrer" class="btn-drawer-website" id="drawerWebsiteBtn">
            <span>Official Website</span>
            <span aria-hidden="true">&nearr;</span>
        </a>
        <p class="drawer-disclaimer-note">Collegiate demonstration profile for academic election platform simulation. No administrative endorsement implied.</p>
    </div>
</div>

<!-- Full College Directory Modal -->
<div class="directory-modal-backdrop" id="directoryModalBackdrop" aria-hidden="true"></div>
<div class="directory-modal-content" id="directoryModalContent" role="dialog" aria-modal="true" aria-labelledby="directoryModalTitle">
    <div class="directory-modal-header">
        <h3 class="directory-modal-title" id="directoryModalTitle">Kolkata Collegiate Directory (20 Institutions)</h3>
        <button type="button" class="drawer-close-btn" id="directoryModalClose" aria-label="Close directory">&times;</button>
    </div>
    <div class="directory-search-bar">
        <input type="text" class="directory-search-input" id="directorySearchInput" placeholder="Search institutions by name, abbreviation or campus..." autocomplete="off">
    </div>
    <div class="directory-cards-scroll" id="directoryCardsContainer">
        <!-- Populated dynamically via JS -->
    </div>
</div>

<!-- ================= BELOW THE HERO: "DEMOCRACY STARTS ON CAMPUS" ================= -->
<section class="section-democracy-campus" id="campus-elections">
    <div class="container">
        
        <!-- Section Broad-sheet Editorial Header -->
        <div class="section-editorial-header">
            <div class="editorial-kicker">
                <span class="kicker-line" aria-hidden="true"></span>
                <span>COLLEGE ELECTORAL GOVERNANCE</span>
                <span class="kicker-line" aria-hidden="true"></span>
            </div>
            <h2 class="section-editorial-title">Democracy starts on campus.</h2>
            <p class="section-editorial-subtitle">
                Explore verified active elections, review candidate panels and manifestos, and verify the transparent mechanics of student representation.
            </p>
        </div>

        <!-- 1. Featured Upcoming or Live Elections Grid -->
        <div class="campus-elections-grid">
            <?php if (empty($campusElections)): ?>
                <div class="editorial-card text-center" style="grid-column: 1 / -1; padding: 3rem;">
                    <p style="color: var(--text-secondary);">No elections currently open for balloting. Check back soon or view certified past results.</p>
                </div>
            <?php else: ?>
                <?php foreach ($campusElections as $elec): ?>
                    <article class="editorial-election-card">
                        <div class="election-card-header">
                            <span class="election-category-pill">
                                <span class="category-text"><?= e($elec['category']) ?></span>
                            </span>
                            <?= election_status_badge($elec['status']) ?>
                        </div>

                        <h3 class="election-card-title"><?= e($elec['title']) ?></h3>

                        <p class="election-card-desc">
                            <?= e(mb_strimwidth($elec['description'], 0, 130, '...')) ?>
                        </p>

                        <div class="election-card-meta">
                            <div class="meta-row">
                                <span class="meta-icon" aria-hidden="true">👥</span>
                                <span><?= (int)$elec['cand_count'] ?> Candidates Registered</span>
                            </div>
                            <div class="meta-row">
                                <span class="meta-icon" aria-hidden="true">🗓️</span>
                                <span>
                                    <?= ($elec['status'] === 'active') ? 'Closes: ' . format_date($elec['end_time'], 'M d, Y') : 'Scheduled: ' . format_date($elec['start_time'], 'M d, Y') ?>
                                </span>
                            </div>
                        </div>

                        <div class="election-card-footer">
                            <a href="<?= url('election-details.php?id=' . $elec['id']) ?>" class="btn btn-card-outline">
                                <span>Inspect Candidates</span>
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                            </a>
                            <?php if ($elec['status'] === 'active'): ?>
                                <a href="<?= url('vote.php?id=' . $elec['id']) ?>" class="btn btn-card-action">
                                    <span>Vote Now</span>
                                </a>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- 2. Distinctive Visual Element: Campus Election Timeline -->
        <div class="editorial-timeline-wrapper">
            <div class="timeline-header">
                <span class="timeline-kicker">DEMOCRATIC SCHEDULE</span>
                <h3 class="timeline-title">Campus Election Milestone Architecture</h3>
                <p class="timeline-subtitle">Every election follows a strict institutional calendar overseen by the College Election Committee.</p>
            </div>

            <div class="timeline-track">
                
                <div class="timeline-step step-completed">
                    <div class="step-marker">
                        <span class="marker-number">01</span>
                        <span class="marker-check">✓</span>
                    </div>
                    <div class="step-content">
                        <span class="step-badge">COMPLETED</span>
                        <h4 class="step-heading">Candidate Nominations</h4>
                        <p class="step-text">Student petition collection, credential auditing, and academic standing clearance verified.</p>
                    </div>
                </div>

                <div class="timeline-step step-active">
                    <div class="step-marker">
                        <span class="marker-number">02</span>
                        <span class="marker-pulse" aria-hidden="true"></span>
                    </div>
                    <div class="step-content">
                        <span class="step-badge badge-active">CURRENT STAGE</span>
                        <h4 class="step-heading">Manifestos & Debates</h4>
                        <p class="step-text">Public candidate platforms, student panel declarations, and institutional forum debates published.</p>
                    </div>
                </div>

                <div class="timeline-step step-live">
                    <div class="step-marker">
                        <span class="marker-number">03</span>
                        <span class="marker-pulse live" aria-hidden="true"></span>
                    </div>
                    <div class="step-content">
                        <span class="step-badge badge-live">POLLS OPEN</span>
                        <h4 class="step-heading">Secret Ballot Voting</h4>
                        <p class="step-text">Students cast anonymized, protected votes with instant verifiable receipt token generation.</p>
                    </div>
                </div>

                <div class="timeline-step step-scheduled">
                    <div class="step-marker">
                        <span class="marker-number">04</span>
                    </div>
                    <div class="step-content">
                        <span class="step-badge">SCHEDULED</span>
                        <h4 class="step-heading">Audit & Certification</h4>
                        <p class="step-text">College Election Committee verification, audit log reconciliation, and certified results publication.</p>
                    </div>
                </div>

            </div> <!-- /timeline-track -->
        </div>

        <!-- 3. Real Candidate Previews Spotlight -->
        <?php if (!empty($featuredCandidates)): ?>
            <div class="candidates-spotlight-wrapper">
                <div class="spotlight-header">
                    <div>
                        <span class="spotlight-kicker">ON THE BALLOT</span>
                        <h3 class="spotlight-title">Featured Candidate Voices</h3>
                    </div>
                    <a href="<?= url('elections.php') ?>" class="btn btn-ghost btn-sm">
                        <span>View All Panels</span> &rarr;
                    </a>
                </div>

                <div class="candidates-spotlight-grid">
                    <?php foreach ($featuredCandidates as $cand): ?>
                        <div class="candidate-spotlight-card">
                            <div class="candidate-card-top">
                                <img src="<?= asset($cand['photo_url'] ?: 'images/default-avatar.svg') ?>" 
                                     alt="<?= e($cand['full_name']) ?>" 
                                     class="candidate-portrait">
                                <div>
                                    <h4 class="candidate-display-name"><?= e($cand['full_name']) ?></h4>
                                    <span class="candidate-slate-pill"><?= e($cand['party_or_slate']) ?></span>
                                </div>
                            </div>
                            <div class="candidate-position-tag"><?= e($cand['position']) ?></div>
                            <p class="candidate-manifesto-excerpt">
                                &ldquo;<?= e(mb_strimwidth($cand['manifesto'], 0, 110, '...')) ?>&rdquo;
                            </p>
                            <div class="candidate-card-bottom">
                                <span class="candidate-election-ref"><?= e($cand['election_title']) ?></span>
                                <a href="<?= url('election-details.php?id=' . $cand['election_id']) ?>" class="candidate-link">Manifesto &rarr;</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- 4. Compact Platform Statistics Section (Connected to Real DB Values) -->
        <div class="compact-stats-editorial">
            <div class="compact-stats-header">
                <span class="stats-kicker">REAL-TIME PLATFORM TELEMETRY</span>
                <h4 class="stats-title">Institutional Electoral Metrics</h4>
            </div>

            <div class="compact-stats-grid">
                
                <div class="compact-stat-card">
                    <div class="stat-meta">
                        <span class="stat-label">REGISTERED STUDENTS</span>
                        <span class="stat-symbol">🪪</span>
                    </div>
                    <div class="stat-number stat-counter" data-target="<?= (int)$totalVoters ?>"><?= (int)$totalVoters ?></div>
                    <span class="stat-sub">Verified institutional student records</span>
                </div>

                <div class="compact-stat-card">
                    <div class="stat-meta">
                        <span class="stat-label">ACTIVE ELECTIONS</span>
                        <span class="stat-symbol">🗳️</span>
                    </div>
                    <div class="stat-number stat-counter" data-target="<?= (int)$activeElections ?>"><?= (int)$activeElections ?></div>
                    <span class="stat-sub">Live ballots currently accepting votes</span>
                </div>

                <div class="compact-stat-card">
                    <div class="stat-meta">
                        <span class="stat-label">VOTES RECORDED</span>
                        <span class="stat-symbol">⚡</span>
                    </div>
                    <div class="stat-number stat-counter" data-target="<?= (int)$totalBallots ?>"><?= (int)$totalBallots ?></div>
                    <span class="stat-sub">Anonymized secret ballot submissions</span>
                </div>

                <div class="compact-stat-card">
                    <div class="stat-meta">
                        <span class="stat-label">AUDIT LOG ENTRIES</span>
                        <span class="stat-symbol">🛡️</span>
                    </div>
                    <div class="stat-number stat-counter" data-target="<?= (int)$totalAuditLogs ?>"><?= (int)$totalAuditLogs ?></div>
                    <span class="stat-sub">Administrative &amp; voting events logged</span>
                </div>

            </div> <!-- /compact-stats-grid -->
        </div>

    </div> <!-- /container -->
</section>

<!-- ================= HOW VOTING WORKS SECTION ================= -->
<section class="section-how-it-works" id="how-it-works">
    <div class="container">
        
        <div class="section-editorial-header text-center">
            <span class="editorial-kicker">DEMOCRATIC PROTOCOL</span>
            <h2 class="section-editorial-title">How Campus Voting Works</h2>
            <p class="section-editorial-subtitle" style="margin: 0 auto;">
                A simple three-step journey ensuring high voter turnout with uncompromised ballot privacy.
            </p>
        </div>

        <div class="protocol-steps-grid">
            
            <div class="protocol-step-card">
                <div class="step-order-badge">01</div>
                <div class="step-icon-wrap" aria-hidden="true">
                    <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#F4CB45" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                        <circle cx="8.5" cy="7" r="4"/>
                        <polyline points="17 11 19 13 23 9"/>
                    </svg>
                </div>
                <h3 class="step-card-title">Authenticate Securely</h3>
                <p class="step-card-text">
                    Log in using your verified student credential or registered College Roll Number with salted bcrypt hashing and session hardening.
                </p>
                <div class="step-card-meta">
                    <span>Identity verified &bull; Ballot separate</span>
                </div>
            </div>

            <div class="protocol-step-card">
                <div class="step-order-badge">02</div>
                <div class="step-icon-wrap" aria-hidden="true">
                    <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#F4CB45" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                        <polyline points="14 2 14 8 20 8"/>
                        <line x1="16" y1="13" x2="8" y2="13"/>
                        <line x1="16" y1="17" x2="8" y2="17"/>
                        <polyline points="10 9 9 9 8 9"/>
                    </svg>
                </div>
                <h3 class="step-card-title">Review Manifestos</h3>
                <p class="step-card-text">
                    Read detailed candidate positions, student council panels, and policy manifestos before confirming your selection.
                </p>
                <div class="step-card-meta">
                    <span>Informed democratic decision</span>
                </div>
            </div>

            <div class="protocol-step-card">
                <div class="step-order-badge">03</div>
                <div class="step-icon-wrap" aria-hidden="true">
                    <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#F4CB45" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                    </svg>
                </div>
                <h3 class="step-card-title">Cast Secret Ballot</h3>
                <p class="step-card-text">
                    Your vote is recorded anonymously into decoupled tables. Receive a unique SHA-256 participation token to confirm your submission anytime.
                </p>
                <div class="step-card-meta">
                    <span>Duplicate vote prevention guaranteed</span>
                </div>
            </div>

        </div> <!-- /protocol-steps-grid -->

    </div> <!-- /container -->
</section>

<!-- ================= SECURITY ARCHITECTURE SHOWCASE ================= -->
<section class="section-security-matrix">
    <div class="container">
        <div class="security-layout-grid">
            <div>
                <span class="editorial-kicker" style="margin-bottom: 0.75rem;">SECURITY ARCHITECTURE</span>
                <h2 style="font-size: 2.15rem; margin-bottom: 1rem; letter-spacing: -0.02em;">
                    Engineered for Strict Ballot Secrecy &amp; Duplicate Vote Prevention
                </h2>
                <p style="margin-bottom: 1.5rem; line-height: 1.7; color: var(--text-secondary);">
                    Unlike naive voting applications that link voter IDs directly to candidate picks, 
                    <strong>ApexVote</strong> strictly isolates voter eligibility records from ballot tallies using a database-level transaction lock (<code style="color: var(--accent-primary);">SELECT ... FOR UPDATE</code>).
                </p>
                <ul class="security-guarantees-list">
                    <li>
                        <span class="guarantee-check">✓</span>
                        <span><strong>Strict Ballot Isolation:</strong> Ballot records store candidate choices without voter identifiers.</span>
                    </li>
                    <li>
                        <span class="guarantee-check">✓</span>
                        <span><strong>ACID Concurrency:</strong> Database transactions prevent race conditions and duplicate voting attempts.</span>
                    </li>
                    <li>
                        <span class="guarantee-check">✓</span>
                        <span><strong>Administrative Audit Trail:</strong> Administrative, voting, and authentication actions recorded in audit logs.</span>
                    </li>
                </ul>
            </div>

            <div class="security-spec-card">
                <div class="spec-card-header">
                    <span class="spec-shield-icon" aria-hidden="true">🛡️</span>
                    <h4>ApexVote Platform Security Matrix</h4>
                </div>
                <div class="spec-matrix-rows">
                    <div class="spec-row">
                        <span class="spec-key">Password Security</span>
                        <strong class="spec-val" style="color: #F4CB45;">Bcrypt (Blowfish 2y, Salted)</strong>
                    </div>
                    <div class="spec-row">
                        <span class="spec-key">Ballot Secrecy Guarantee</span>
                        <strong class="spec-val" style="color: #38B982;">Decoupled Tables &amp; SHA-256 Receipts</strong>
                    </div>
                    <div class="spec-row">
                        <span class="spec-key">Injection Defense</span>
                        <strong class="spec-val" style="color: #DDB63F;">PDO Prepared Parameterized Statements</strong>
                    </div>
                    <div class="spec-row">
                        <span class="spec-key">CSRF Defense</span>
                        <strong class="spec-val" style="color: #E8B84A;">HMAC Synchronizer Session Tokens</strong>
                    </div>
                    <div class="spec-row">
                        <span class="spec-key">Double-Voting Prevention</span>
                        <strong class="spec-val" style="color: #38B982;">InnoDB Row-Level Lock (FOR UPDATE)</strong>
                    </div>
                    <div class="spec-row">
                        <span class="spec-key">System Audit Logging</span>
                        <strong class="spec-val" style="color: #F4CB45;">Structured Database Audit Trail</strong>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Kolkata College Directory & 3D Cube Controller Scripts -->
<script src="<?= asset('js/college-directory.js') ?>"></script>
<script src="<?= asset('js/kolkata-cube.js') ?>"></script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
