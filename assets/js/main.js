/**
 * ApexVote Pro - Client Interactivity, Modals, Counters & Toast Engine
 */

document.addEventListener('DOMContentLoaded', () => {
    initToasts();
    initModals();
    initCounters();
    initMobileNav();
    initPublicMobileNav();
    initNavbarScroll();
    initElectionCountdown();
    initHeroBallotInteraction();
    initVotingInteractions();
    initTableSearch();
    initPasswordToggles();
    initElectionCardFlip();
});

/**
 * Toast Notification System
 */
function initToasts() {
    let container = document.getElementById('toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        document.body.appendChild(container);
    }

    window.showToast = function(type, message, duration = 4500) {
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        
        let iconSvg = '';
        if (type === 'success') {
            iconSvg = `<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#38B982" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>`;
        } else if (type === 'danger' || type === 'error') {
            iconSvg = `<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#E16B62" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>`;
        } else if (type === 'warning') {
            iconSvg = `<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#E8B84A" stroke-width="2.5"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>`;
        } else {
            iconSvg = `<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#F4CB45" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>`;
        }

        toast.innerHTML = `
            <div style="flex-shrink:0;">${iconSvg}</div>
            <div style="flex:1;">${message}</div>
            <button type="button" style="background:none;border:none;color:#A9977F;cursor:pointer;font-size:18px;margin-left:8px;" onclick="this.parentElement.remove()">&times;</button>
        `;

        container.appendChild(toast);

        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateX(50px)';
            toast.style.transition = 'all 0.3s ease';
            setTimeout(() => toast.remove(), 300);
        }, duration);
    };
}

/**
 * Accessible Modal Windows
 */
function initModals() {
    // Open trigger
    document.querySelectorAll('[data-modal-target]').forEach(trigger => {
        trigger.addEventListener('click', (e) => {
            e.preventDefault();
            const targetId = trigger.getAttribute('data-modal-target');
            const modal = document.querySelector(targetId);
            if (modal) {
                modal.classList.add('open');
                document.body.style.overflow = 'hidden';
            }
        });
    });

    // Close triggers
    document.querySelectorAll('[data-close-modal]').forEach(btn => {
        btn.addEventListener('click', () => {
            const modal = btn.closest('.modal-overlay');
            if (modal) {
                modal.classList.remove('open');
                document.body.style.overflow = '';
            }
        });
    });

    // Click outside to close
    document.querySelectorAll('.modal-overlay').forEach(overlay => {
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) {
                overlay.classList.remove('open');
                document.body.style.overflow = '';
            }
        });
    });

    // ESC key closes modal
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal-overlay.open').forEach(modal => {
                modal.classList.remove('open');
                document.body.style.overflow = '';
            });
        }
    });
}

/**
 * Animated Numerical Counters
 */
function initCounters() {
    const counters = document.querySelectorAll('.stat-counter');
    counters.forEach(counter => {
        const target = parseInt(counter.getAttribute('data-target') || counter.innerText, 10);
        if (isNaN(target)) return;

        let current = 0;
        const duration = 1200;
        const stepTime = 25;
        const totalSteps = duration / stepTime;
        const increment = target / totalSteps;

        const timer = setInterval(() => {
            current += increment;
            if (current >= target) {
                counter.innerText = target.toLocaleString();
                clearInterval(timer);
            } else {
                counter.innerText = Math.floor(current).toLocaleString();
            }
        }, stepTime);
    });
}

/**
 * Mobile Navigation Drawer Toggle (Dashboard Sidebar)
 */
function initMobileNav() {
    const toggleBtn = document.querySelector('.mobile-toggle');
    const sidebar = document.querySelector('.app-sidebar');
    const backdrop = document.getElementById('sidebarBackdrop');
    
    if (toggleBtn && sidebar) {
        const toggleSidebar = (e) => {
            if (e) e.stopPropagation();
            const isOpen = sidebar.classList.toggle('open');
            if (backdrop) backdrop.classList.toggle('open', isOpen);
            document.body.style.overflow = (isOpen && window.innerWidth <= 1024) ? 'hidden' : '';
        };

        const closeSidebar = () => {
            sidebar.classList.remove('open');
            if (backdrop) backdrop.classList.remove('open');
            document.body.style.overflow = '';
        };

        toggleBtn.addEventListener('click', toggleSidebar);

        if (backdrop) {
            backdrop.addEventListener('click', closeSidebar);
        }

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', (e) => {
            if (!sidebar.contains(e.target) && !toggleBtn.contains(e.target) && sidebar.classList.contains('open')) {
                closeSidebar();
            }
        });

        // Close on link click inside sidebar on mobile
        sidebar.querySelectorAll('.sidebar-link, .sidebar-signout-btn').forEach(link => {
            link.addEventListener('click', () => {
                if (window.innerWidth <= 1024) {
                    closeSidebar();
                }
            });
        });

        // Close on Escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && sidebar.classList.contains('open')) {
                closeSidebar();
            }
        });
    }
}

/**
 * Candidate Voting Interactions & Confirmation Dialog
 */
function initVotingInteractions() {
    const selectableCards = document.querySelectorAll('.candidate-card-selectable');
    const confirmBtn = document.getElementById('btn-open-vote-modal');
    const modalCandidateName = document.getElementById('modal-chosen-candidate-name');
    const modalCandidatePosition = document.getElementById('modal-chosen-candidate-position');

    selectableCards.forEach(card => {
        card.addEventListener('click', () => {
            selectableCards.forEach(c => c.classList.remove('selected'));
            card.classList.add('selected');

            // Find internal radio button and check it
            const radio = card.querySelector('input[type="radio"][name="candidate_id"]');
            if (radio) {
                radio.checked = true;
            }

            // Enable submit confirmation button if present
            if (confirmBtn) {
                confirmBtn.removeAttribute('disabled');
                confirmBtn.classList.remove('btn-secondary');
                confirmBtn.classList.add('btn-primary');
            }

            // Update modal details
            const name = card.getAttribute('data-candidate-name') || 'Selected Candidate';
            const position = card.getAttribute('data-candidate-position') || '';
            if (modalCandidateName) modalCandidateName.innerText = name;
            if (modalCandidatePosition) modalCandidatePosition.innerText = position;
        });
    });
}

/**
 * Instant Client-Side Table Search/Filter
 */
function initTableSearch() {
    const searchInputs = document.querySelectorAll('[data-table-filter]');
    searchInputs.forEach(input => {
        const tableSelector = input.getAttribute('data-table-filter');
        const table = document.querySelector(tableSelector);
        if (!table) return;

        input.addEventListener('input', () => {
            const query = input.value.toLowerCase().trim();
            const rows = table.querySelectorAll('tbody tr');

            rows.forEach(row => {
                const text = row.innerText.toLowerCase();
                row.style.display = text.includes(query) ? '' : 'none';
            });
        });
    });
}

/**
 * Sticky Navbar Scroll Transition
 */
function initNavbarScroll() {
    const header = document.getElementById('siteHeader');
    if (!header) return;

    const checkScroll = () => {
        if (window.scrollY > 20) {
            header.classList.add('scrolled');
        } else {
            header.classList.remove('scrolled');
        }
    };

    window.addEventListener('scroll', checkScroll, { passive: true });
    checkScroll();
}

/**
 * Public Mobile Navigation Drawer
 */
function initPublicMobileNav() {
    const toggleBtn = document.getElementById('publicNavToggle');
    const drawer = document.getElementById('publicMobileDrawer');
    const backdrop = document.getElementById('drawerBackdrop');
    const closeBtn = document.getElementById('drawerCloseBtn');

    if (!toggleBtn || !drawer) return;

    const openDrawer = () => {
        drawer.classList.add('open');
        if (backdrop) backdrop.classList.add('open');
        toggleBtn.classList.add('open');
        toggleBtn.setAttribute('aria-expanded', 'true');
        drawer.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
    };

    const closeDrawer = () => {
        drawer.classList.remove('open');
        if (backdrop) backdrop.classList.remove('open');
        toggleBtn.classList.remove('open');
        toggleBtn.setAttribute('aria-expanded', 'false');
        drawer.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    };

    toggleBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        if (drawer.classList.contains('open')) {
            closeDrawer();
        } else {
            openDrawer();
        }
    });

    if (closeBtn) closeBtn.addEventListener('click', closeDrawer);
    if (backdrop) backdrop.addEventListener('click', closeDrawer);

    // Close on navigation link click inside mobile drawer
    drawer.querySelectorAll('a').forEach(link => {
        link.addEventListener('click', closeDrawer);
    });

    // Close on Escape key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && drawer.classList.contains('open')) {
            closeDrawer();
        }
    });
}

/**
 * Real-Time Election Closing Countdown Engine
 */
function initElectionCountdown() {
    const module = document.getElementById('electionCountdownModule');
    if (!module) return;

    const endTimeStr = module.getAttribute('data-end-time');
    if (!endTimeStr) return;

    const targetTime = new Date(endTimeStr).getTime();
    if (isNaN(targetTime)) return;

    const daysEl = document.getElementById('cdDays');
    const hoursEl = document.getElementById('cdHours');
    const minsEl = document.getElementById('cdMinutes');
    const secsEl = document.getElementById('cdSeconds');

    const updateTicker = () => {
        const now = Date.now();
        const diff = targetTime - now;

        if (diff <= 0) {
            if (daysEl) daysEl.innerText = '00';
            if (hoursEl) hoursEl.innerText = '00';
            if (minsEl) minsEl.innerText = '00';
            if (secsEl) secsEl.innerText = '00';
            return;
        }

        const days = Math.floor(diff / (1000 * 60 * 60 * 24));
        const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
        const mins = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
        const secs = Math.floor((diff % (1000 * 60)) / 1000);

        if (daysEl) daysEl.innerText = String(days).padStart(2, '0');
        if (hoursEl) hoursEl.innerText = String(hours).padStart(2, '0');
        if (minsEl) minsEl.innerText = String(mins).padStart(2, '0');
        if (secsEl) secsEl.innerText = String(secs).padStart(2, '0');
    };

    updateTicker();
    setInterval(updateTicker, 1000);
}

/**
 * Subtle Interactive Hero Ballot Card Simulation
 */
function initHeroBallotInteraction() {
    const ballotCard = document.getElementById('heroInteractiveBallot');
    if (!ballotCard) return;

    const candidateItems = ballotCard.querySelectorAll('.ballot-candidate-item');
    const hashEl = ballotCard.querySelector('.receipt-hash');

    candidateItems.forEach(item => {
        const activateCandidate = () => {
            candidateItems.forEach(c => c.classList.remove('is-selected'));
            item.classList.add('is-selected');

            // Micro simulated cryptographic receipt token feedback
            if (hashEl) {
                const sampleHex = '9b' + Math.random().toString(16).substring(2, 6) + '...' + Math.random().toString(16).substring(2, 7);
                hashEl.innerText = sampleHex;
                hashEl.style.transition = 'color 0.25s ease';
                hashEl.style.color = '#38B982';
                setTimeout(() => {
                    hashEl.style.color = '';
                }, 450);
            }
        };

        item.addEventListener('click', activateCandidate);
        item.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                activateCandidate();
            }
        });
    });
}

/**
 * ApexVote – Interactive 3D Election Card Flip Animation Engine
 * Handles genuine CSS 3D card flips, single-card active state, keyboard navigation, and accessibility
 */
function initElectionCardFlip() {
    const cardContainers = document.querySelectorAll('.election-card-flipper-container');
    if (!cardContainers.length) return;

    function setCardFlipped(container, isFlipped) {
        const frontFace = container.querySelector('.election-card-front');
        const backFace = container.querySelector('.election-card-back');
        const flipBtns = container.querySelectorAll('.election-card-flip-btn, .card-flip-pill-btn');
        const flipBackBtns = container.querySelectorAll('.election-card-flip-back-btn, .card-flip-back-pill-btn');

        if (isFlipped) {
            // Close any other open/flipped card on the page (Only one card flips at a time)
            cardContainers.forEach(other => {
                if (other !== container && other.classList.contains('is-flipped')) {
                    setCardFlipped(other, false);
                }
            });

            container.classList.add('is-flipped');
            if (frontFace) {
                frontFace.setAttribute('aria-hidden', 'true');
                frontFace.setAttribute('inert', '');
            }
            if (backFace) {
                backFace.setAttribute('aria-hidden', 'false');
                backFace.removeAttribute('inert');
            }
            flipBtns.forEach(btn => btn.setAttribute('aria-expanded', 'true'));
            flipBackBtns.forEach(btn => btn.setAttribute('aria-expanded', 'true'));
        } else {
            container.classList.remove('is-flipped');
            if (frontFace) {
                frontFace.setAttribute('aria-hidden', 'false');
                frontFace.removeAttribute('inert');
            }
            if (backFace) {
                backFace.setAttribute('aria-hidden', 'true');
                backFace.setAttribute('inert', '');
            }
            flipBtns.forEach(btn => btn.setAttribute('aria-expanded', 'false'));
            flipBackBtns.forEach(btn => btn.setAttribute('aria-expanded', 'false'));
        }
    }

    cardContainers.forEach(container => {
        // Dedicated flip controls: Front -> Back
        const flipBtns = container.querySelectorAll('.election-card-flip-btn, .card-flip-pill-btn');
        flipBtns.forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                setCardFlipped(container, true);
            });
            btn.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    e.stopPropagation();
                    setCardFlipped(container, true);
                }
            });
        });

        // Dedicated flip controls: Back -> Front
        const flipBackBtns = container.querySelectorAll('.election-card-flip-back-btn, .card-flip-back-pill-btn');
        flipBackBtns.forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                setCardFlipped(container, false);
            });
            btn.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    e.stopPropagation();
                    setCardFlipped(container, false);
                }
            });
        });

        // Ensure all interactive links and non-flip buttons never bubble click to container
        container.querySelectorAll('a, button').forEach(interactiveEl => {
            if (!interactiveEl.matches('.election-card-flip-btn, .card-flip-pill-btn, .election-card-flip-back-btn, .card-flip-back-pill-btn')) {
                interactiveEl.addEventListener('click', (e) => {
                    e.stopPropagation();
                });
            }
        });

        // Allow clicking non-interactive areas of the card to flip
        container.addEventListener('click', (e) => {
            // Guard against null target or non-element
            if (!e.target || !e.target.closest) return;

            // Ignore if clicking interactive controls, links, buttons, inputs, etc.
            if (e.target.closest('a, button, input, select, textarea, label, [role="button"], .no-flip')) {
                return;
            }
            // Ignore if user is highlighting/selecting text
            const selection = window.getSelection ? window.getSelection().toString() : '';
            if (selection && selection.length > 0) {
                return;
            }
            const isCurrentlyFlipped = container.classList.contains('is-flipped');
            setCardFlipped(container, !isCurrentlyFlipped);
        });

        // Initialize default accessibility attributes
        setCardFlipped(container, false);
    });
}


/**
 * Accessible Password Visibility Toggles
 */
function initPasswordToggles() {
    document.querySelectorAll('.password-toggle-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const wrap = btn.closest('.password-input-wrap');
            if (!wrap) return;
            const input = wrap.querySelector('input');
            if (!input) return;

            const isPassword = input.type === 'password';
            input.type = isPassword ? 'text' : 'password';
            btn.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');

            if (isPassword) {
                btn.innerHTML = `<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>`;
            } else {
                btn.innerHTML = `<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>`;
            }
        });
    });
}



