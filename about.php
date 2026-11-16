<?php
/**
 * About the Online Voting System Platform
 */

require_once __DIR__ . '/includes/auth.php';

$pageTitle = 'About the Electoral Platform';
$isDashboard = false;

require_once __DIR__ . '/includes/header.php';
?>

<div class="container container-narrow" style="padding: 4.5rem 1.5rem;">
    <div style="text-align: center; margin-bottom: 3.5rem;">
        <span class="status-badge badge-category" style="margin-bottom: 0.75rem;">Platform Architecture</span>
        <h1 style="margin-bottom: 1rem;">Empowering Campus Democracy</h1>
        <p style="font-size: 1.15rem; max-width: 680px; margin: 0 auto; color: var(--text-secondary);">
            ApexVote is engineered to provide academic institutions with an uncompromised, 
            verifiable, and confidential digital balloting infrastructure.
        </p>
    </div>

    <div class="glass-card" style="margin-bottom: 2.5rem; padding: 2.5rem;">
        <h3 style="margin-bottom: 1.25rem; color: var(--text-primary); display: flex; align-items: center; gap: 0.65rem;">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="var(--accent-primary)" stroke-width="2"><path d="M3 21h18M3 10h18M5 10v11M19 10v11M9 10v11M15 10v11M12 2L2 7h20L12 2z"/></svg>
            Institutional Mission & Governance
        </h3>
        <p style="line-height: 1.8; margin-bottom: 1.25rem; color: var(--text-secondary);">
            Traditional student council and departmental union elections historically face low voter turnout, 
            paper-ballot inefficiencies, and ballot secrecy concerns. <strong>ApexVote</strong> bridges the gap 
            by deploying modern web technologies to ensure every eligible student can exercise 
            their democratic right seamlessly from any desktop or mobile browser.
        </p>
        <p style="line-height: 1.8; color: var(--text-secondary); margin-bottom: 0;">
            Supervised by the College Election Committee, our system guarantees that vote casting is protected, 
            election results are certified in real-time, and student voter preferences remain completely untraceable.
        </p>
    </div>

    <div class="glass-card" style="margin-bottom: 2.5rem; padding: 2.5rem;">
        <h3 style="margin-bottom: 1.5rem; color: var(--text-primary); display: flex; align-items: center; gap: 0.65rem;">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="var(--accent-primary)" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
            The Three Pillars of Ballot Security
        </h3>

        <div style="display: flex; flex-direction: column; gap: 1.5rem;">
            <div style="border-left: 3px solid var(--accent-primary); padding-left: 1.25rem;">
                <h4 style="margin-bottom: 0.35rem; color: var(--text-primary);">1. Absolute Ballot Secrecy</h4>
                <p style="font-size: 0.95rem; line-height: 1.6; color: var(--text-secondary); margin-bottom: 0;">
                    When you cast your vote, the application completely decouples voter identity from the recorded ballot. 
                    The `ballots` table stores only the election ID, chosen candidate ID, and a timestamped submission hash. 
                    No voter identifier is ever stored in the ballot record, guaranteeing absolute secret balloting.
                </p>
            </div>

            <div style="border-left: 3px solid var(--accent-emerald); padding-left: 1.25rem;">
                <h4 style="margin-bottom: 0.35rem; color: var(--text-primary);">2. Atomicity & Anti-Double Vote Prevention</h4>
                <p style="font-size: 0.95rem; line-height: 1.6;">
                    Vote submission executes within an isolated database transaction. The system applies row-level locking 
                    (`FOR UPDATE`) on the voter's eligibility record, ensuring that simultaneous submission attempts are 
                    strictly blocked and duplicate voting is prevented under concurrent load.
                </p>
            </div>

            <div style="border-left: 3px solid var(--accent-primary); padding-left: 1.25rem;">
                <h4 style="margin-bottom: 0.35rem; color: var(--text-primary);">3. Participation Receipt References</h4>
                <p style="font-size: 0.95rem; line-height: 1.6;">
                    Upon casting a vote, the system generates a 64-character SHA-256 participation receipt reference. 
                    Your participation receipt confirms that the system recorded your participation in this election. It does not display your candidate selection.
                </p>
            </div>
        </div>
    </div>

    <div class="glass-card" style="padding: 2.5rem;">
        <h3 style="margin-bottom: 1.25rem; color: var(--accent-amber); display: flex; align-items: center; gap: 0.5rem;">
            <span>💻</span> Pure Native Technology Stack
        </h3>
        <p style="line-height: 1.8; margin-bottom: 1.5rem;">
            Built strictly without bloated node frameworks or heavy dependencies:
        </p>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.25rem;">
            <div style="background: var(--color-surface); padding: 1.25rem; border-radius: var(--radius-md); border: 1px solid var(--color-border-subtle);">
                <div style="font-weight: 700; color: #F4CB45; margin-bottom: 0.35rem;">Backend Core</div>
                <div style="font-size: 0.9rem; color: var(--text-secondary);">PHP 8.2+ with PDO & strict exception safety</div>
            </div>

            <div style="background: var(--color-surface); padding: 1.25rem; border-radius: var(--radius-md); border: 1px solid var(--color-border-subtle);">
                <div style="font-weight: 700; color: #38B982; margin-bottom: 0.35rem;">Database Engine</div>
                <div style="font-size: 0.9rem; color: var(--text-secondary);">MySQL 8+ / MariaDB with InnoDB Transactions</div>
            </div>

            <div style="background: var(--color-surface); padding: 1.25rem; border-radius: var(--radius-md); border: 1px solid var(--color-border-subtle);">
                <div style="font-weight: 700; color: #DDB63F; margin-bottom: 0.35rem;">Frontend Engine</div>
                <div style="font-size: 0.9rem; color: var(--text-secondary);">HTML5, CSS3 Glassmorphism & Vanilla JavaScript</div>
            </div>

            <div style="background: var(--color-surface); padding: 1.25rem; border-radius: var(--radius-md); border: 1px solid var(--color-border-subtle);">
                <div style="font-weight: 700; color: #E8B84A; margin-bottom: 0.35rem;">Analytics & Visuals</div>
                <div style="font-size: 0.9rem; color: var(--text-secondary);">Chart.js Real-time certified result graphs</div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
