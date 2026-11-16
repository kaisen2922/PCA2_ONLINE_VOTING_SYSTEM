/**
 * ApexVote Two-Factor Voting Verification Script
 * Supports Mandatory Institutional Email OTP + Accessible 6-Digit Code Input
 * Multi-Step Journey: Review Candidate -> Email OTP -> Final Confirmation -> Receipt Generation
 */

(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', () => {
        const voteForm = document.getElementById('voting-ballot-form');
        if (!voteForm) return;

        // State variables
        let challengeId = null;
        let authorizationToken = null;
        let selectedChannel = 'email';
        let timerInterval = null;
        let selectedCandidateName = '';
        let selectedCandidatePosition = '';

        // Elements - Candidates & Modal Open
        const candidateCards = document.querySelectorAll('.candidate-card-selectable');
        const openModalBtn = document.getElementById('btn-open-vote-modal');
        const modal = document.getElementById('confirm-vote-modal');
        const modalChosenName = document.getElementById('modal-chosen-candidate-name');
        const modalChosenPosition = document.getElementById('modal-chosen-candidate-position');

        // Modal Views
        const modalPhaseReview = document.getElementById('modal-phase-review');
        const modalPhaseVerification = document.getElementById('modal-phase-verification');
        const modalPhaseConfirmBallot = document.getElementById('modal-phase-confirm-ballot');
        const modalPhaseSuccess = document.getElementById('modal-phase-success');

        // Method Cards & Inputs
        const methodEmailCard = document.getElementById('method-card-email');
        const methodSmsCard = document.getElementById('method-card-sms');
        const channelRadios = document.querySelectorAll('input[name="otp_channel"]');
        const smsPhoneContainer = document.getElementById('sms-phone-container');
        const smsPhoneInput = document.getElementById('sms-phone-number');
        const requestOtpError = document.getElementById('request-otp-error');
        const btnSendVerificationCode = document.getElementById('btn-send-verification-code');

        // Verification Inputs & Controls
        const dispAuthDestination = document.getElementById('disp-auth-destination');
        const otpDigits = document.querySelectorAll('.otp-digit');
        const otpInput = document.getElementById('verification-otp-input');
        const otpTimerWrap = document.getElementById('otp-timer-wrap');
        const otpTimerVal = document.getElementById('otp-timer-val');
        const btnResendOtp = document.getElementById('btn-resend-otp');
        const otpFeedback = document.getElementById('otp-feedback-msg');
        const btnBackToReview = document.getElementById('btn-back-to-review');
        const btnVerifyOtp = document.getElementById('btn-verify-otp');

        // Final Confirmation & Submission Controls
        const confirmCandidateName = document.getElementById('confirm-candidate-name');
        const confirmCandidatePosition = document.getElementById('confirm-candidate-position');
        const btnConfirmSubmitBallot = document.getElementById('btn-confirm-submit-ballot');
        const finalSubmissionError = document.getElementById('final-submission-error');
        const successReceiptToken = document.getElementById('success-receipt-token');

        // Metadata
        const electionId = voteForm.getAttribute('data-election-id') || document.querySelector('input[name="election_id"]')?.value;
        const csrfToken = document.querySelector('input[name="csrf_token"]')?.value;

        // -------------------------------------------------------------
        // 1. Candidate Selection Handling
        // -------------------------------------------------------------
        candidateCards.forEach(card => {
            card.addEventListener('click', () => {
                candidateCards.forEach(c => {
                    c.classList.remove('selected');
                    const btn = c.querySelector('.candidate-select-btn button');
                    if (btn) {
                        btn.className = 'btn btn-secondary btn-block btn-sm';
                        btn.innerHTML = 'Click to Choose Candidate';
                    }
                });

                card.classList.add('selected');
                const radio = card.querySelector('input[type="radio"][name="candidate_id"]');
                if (radio) {
                    radio.checked = true;
                }

                const btn = card.querySelector('.candidate-select-btn button');
                if (btn) {
                    btn.className = 'btn btn-primary btn-block btn-sm';
                    btn.innerHTML = 'Selected Nominee &check;';
                }

                selectedCandidateName = card.getAttribute('data-candidate-name') || 'Selected Nominee';
                selectedCandidatePosition = card.getAttribute('data-candidate-position') || '';

                if (modalChosenName) modalChosenName.innerText = selectedCandidateName;
                if (modalChosenPosition) modalChosenPosition.innerText = selectedCandidatePosition;
                if (confirmCandidateName) confirmCandidateName.innerText = selectedCandidateName;
                if (confirmCandidatePosition) confirmCandidatePosition.innerText = selectedCandidatePosition;

                if (openModalBtn) {
                    openModalBtn.removeAttribute('disabled');
                    openModalBtn.classList.remove('btn-secondary');
                    openModalBtn.classList.add('btn-primary');
                }
            });
        });

        // Initialize candidate if pre-selected
        const initiallyChecked = document.querySelector('input[type="radio"][name="candidate_id"]:checked');
        if (initiallyChecked) {
            const card = initiallyChecked.closest('.candidate-card-selectable');
            if (card) {
                card.classList.add('selected');
                selectedCandidateName = card.getAttribute('data-candidate-name') || 'Selected Nominee';
                selectedCandidatePosition = card.getAttribute('data-candidate-position') || '';

                if (modalChosenName) modalChosenName.innerText = selectedCandidateName;
                if (modalChosenPosition) modalChosenPosition.innerText = selectedCandidatePosition;
                if (confirmCandidateName) confirmCandidateName.innerText = selectedCandidateName;
                if (confirmCandidatePosition) confirmCandidatePosition.innerText = selectedCandidatePosition;

                if (openModalBtn) {
                    openModalBtn.removeAttribute('disabled');
                    openModalBtn.classList.remove('btn-secondary');
                    openModalBtn.classList.add('btn-primary');
                }
            }
        }

        // Modal Open trigger
        if (openModalBtn) {
            openModalBtn.addEventListener('click', () => {
                if (!modal) return;
                modal.classList.add('show');
                modal.style.display = 'flex';
                // Reset views to Phase 1 Review
                if (modalPhaseReview) modalPhaseReview.style.display = 'block';
                if (modalPhaseVerification) modalPhaseVerification.style.display = 'none';
                if (modalPhaseConfirmBallot) modalPhaseConfirmBallot.style.display = 'none';
                if (modalPhaseSuccess) modalPhaseSuccess.style.display = 'none';
            });
        }

        // Modal Close handlers
        document.querySelectorAll('[data-close-modal]').forEach(closeBtn => {
            closeBtn.addEventListener('click', () => {
                if (!modal) return;
                modal.classList.remove('show');
                modal.style.display = 'none';
            });
        });

        // -------------------------------------------------------------
        // 2. Verification Method Selection Handling
        // -------------------------------------------------------------
        function updateMethodSelection(channel) {
            selectedChannel = channel;
            if (requestOtpError) {
                requestOtpError.style.display = 'none';
                requestOtpError.innerText = '';
            }

            if (channel === 'email') {
                methodEmailCard?.classList.add('selected');
                methodSmsCard?.classList.remove('selected');
                if (smsPhoneContainer) smsPhoneContainer.style.display = 'none';
            } else if (channel === 'sms') {
                methodSmsCard?.classList.add('selected');
                methodEmailCard?.classList.remove('selected');
                if (smsPhoneContainer) {
                    smsPhoneContainer.style.display = 'block';
                    setTimeout(() => smsPhoneInput?.focus(), 150);
                }
            }
        }

        channelRadios.forEach(radio => {
            radio.addEventListener('change', () => {
                if (radio.checked) {
                    updateMethodSelection(radio.value);
                }
            });
        });

        if (methodEmailCard) {
            methodEmailCard.addEventListener('click', () => {
                const radio = methodEmailCard.querySelector('input[type="radio"]');
                if (radio && !radio.disabled) {
                    radio.checked = true;
                    updateMethodSelection('email');
                }
            });
        }

        // -------------------------------------------------------------
        // 3. Cooldown Timer Management & Feedback Display
        // -------------------------------------------------------------
        function startCooldownTimer(seconds = 60) {
            clearInterval(timerInterval);
            let remaining = seconds;
            if (btnResendOtp) btnResendOtp.style.display = 'none';
            if (otpTimerWrap) otpTimerWrap.style.display = 'inline';
            if (otpTimerVal) otpTimerVal.innerText = remaining;

            timerInterval = setInterval(() => {
                remaining -= 1;
                if (otpTimerVal) otpTimerVal.innerText = remaining;
                if (remaining <= 0) {
                    clearInterval(timerInterval);
                    if (otpTimerWrap) otpTimerWrap.style.display = 'none';
                    if (btnResendOtp) btnResendOtp.style.display = 'inline-block';
                }
            }, 1000);
        }

        function showFeedback(text, type = 'error') {
            if (!otpFeedback) return;
            otpFeedback.className = `feedback-msg show ${type}`;
            otpFeedback.innerHTML = text;
        }

        function clearFeedback() {
            if (!otpFeedback) return;
            otpFeedback.className = 'feedback-msg';
            otpFeedback.innerHTML = '';
        }

        // -------------------------------------------------------------
        // 4. Six-Digit Accessible Input Handling (Focus, Backspace, Paste)
        // -------------------------------------------------------------
        function getOtpCode() {
            return Array.from(otpDigits).map(input => input.value.trim()).join('');
        }

        function clearOtpDigits() {
            otpDigits.forEach(d => {
                d.value = '';
                d.classList.remove('filled');
            });
            if (otpInput) otpInput.value = '';
            if (btnVerifyOtp) btnVerifyOtp.setAttribute('disabled', 'disabled');
        }

        function updateOtpState() {
            const code = getOtpCode();
            if (otpInput) otpInput.value = code;

            if (btnVerifyOtp) {
                if (code.length === 6) {
                    btnVerifyOtp.removeAttribute('disabled');
                } else {
                    btnVerifyOtp.setAttribute('disabled', 'disabled');
                }
            }
        }

        otpDigits.forEach((digitInput, index) => {
            // Digit input & automatic advance
            digitInput.addEventListener('input', (e) => {
                const val = e.target.value.replace(/\D/g, '');
                e.target.value = val ? val.slice(-1) : '';

                if (e.target.value) {
                    e.target.classList.add('filled');
                    if (index < otpDigits.length - 1) {
                        otpDigits[index + 1].focus();
                        otpDigits[index + 1].select();
                    }
                } else {
                    e.target.classList.remove('filled');
                }

                updateOtpState();

                // If all 6 digits completed, automatically verify
                if (getOtpCode().length === 6) {
                    verifySubmittedOtp();
                }
            });

            // Backspace navigation & Arrow navigation
            digitInput.addEventListener('keydown', (e) => {
                if (e.key === 'Backspace') {
                    if (!e.target.value && index > 0) {
                        e.preventDefault();
                        otpDigits[index - 1].focus();
                        otpDigits[index - 1].value = '';
                        otpDigits[index - 1].classList.remove('filled');
                        updateOtpState();
                    } else if (e.target.value) {
                        e.target.value = '';
                        e.target.classList.remove('filled');
                        updateOtpState();
                        e.preventDefault();
                    }
                } else if (e.key === 'ArrowLeft' && index > 0) {
                    e.preventDefault();
                    otpDigits[index - 1].focus();
                } else if (e.key === 'ArrowRight' && index < otpDigits.length - 1) {
                    e.preventDefault();
                    otpDigits[index + 1].focus();
                } else if (e.key === 'Enter') {
                    e.preventDefault();
                    if (getOtpCode().length === 6) {
                        verifySubmittedOtp();
                    }
                }
            });

            // Paste handler for 6-digit codes
            digitInput.addEventListener('paste', (e) => {
                e.preventDefault();
                const pasteData = (e.clipboardData || window.clipboardData).getData('text');
                const cleanDigits = pasteData.replace(/\D/g, '').slice(0, 6);

                if (!cleanDigits) return;

                cleanDigits.split('').forEach((char, i) => {
                    if (i < otpDigits.length) {
                        otpDigits[i].value = char;
                        otpDigits[i].classList.add('filled');
                    }
                });

                updateOtpState();

                const focusIndex = Math.min(cleanDigits.length, otpDigits.length - 1);
                otpDigits[focusIndex].focus();

                if (cleanDigits.length === 6) {
                    verifySubmittedOtp();
                }
            });
        });

        // -------------------------------------------------------------
        // 5. Phase 1 -> Phase 2: Dispatch Email Verification Code
        // -------------------------------------------------------------
        async function dispatchOtpRequest() {
            clearFeedback();
            if (requestOtpError) {
                requestOtpError.style.display = 'none';
                requestOtpError.innerText = '';
            }

            const candidateRadio = document.querySelector('input[name="candidate_id"]:checked');
            if (!candidateRadio) {
                alert('Please select a candidate before proceeding.');
                return false;
            }

            let endpoint = 'request_email_otp.php';
            const params = new URLSearchParams({
                election_id: electionId,
                csrf_token: csrfToken
            });

            if (selectedChannel === 'sms') {
                const phone = smsPhoneInput?.value.trim().replace(/\D/g, '') || '';
                if (phone.length !== 10 || !['6', '7', '8', '9'].includes(phone.charAt(0))) {
                    if (requestOtpError) {
                        requestOtpError.style.display = 'block';
                        requestOtpError.innerText = 'Please enter a valid 10-digit Indian mobile number starting with 6, 7, 8, or 9.';
                    }
                    smsPhoneInput?.focus();
                    return false;
                }
                endpoint = 'request_sms_otp.php';
                params.append('country_code', '+91');
                params.append('mobile_number', phone);
            }

            if (btnSendVerificationCode) {
                btnSendVerificationCode.setAttribute('disabled', 'disabled');
                btnSendVerificationCode.innerHTML = '<span class="status-indicator-ping"></span> Sending Verification Code...';
            }

            try {
                const resp = await fetch(endpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: params
                });

                const data = await resp.json();

                if (!data.success) {
                    if (requestOtpError) {
                        requestOtpError.style.display = 'block';
                        requestOtpError.innerText = data.error || 'Failed to dispatch verification code. Please retry.';
                    } else {
                        alert(data.error || 'Failed to dispatch verification code.');
                    }
                    if (btnSendVerificationCode) {
                        btnSendVerificationCode.removeAttribute('disabled');
                        btnSendVerificationCode.innerHTML = 'Confirm &amp; Verify Email &rarr;';
                    }
                    return false;
                }

                // Success!
                challengeId = data.challenge_id;
                if (dispAuthDestination) {
                    dispAuthDestination.innerText = data.masked_destination || data.masked_email || (selectedChannel === 'sms' ? '+91 *******' : 'Registered College Email');
                }

                // Transition to Phase 2 (Dedicated OTP Screen)
                if (modalPhaseReview) modalPhaseReview.style.display = 'none';
                if (modalPhaseVerification) modalPhaseVerification.style.display = 'block';
                if (modalPhaseConfirmBallot) modalPhaseConfirmBallot.style.display = 'none';
                if (modalPhaseSuccess) modalPhaseSuccess.style.display = 'none';

                if (btnSendVerificationCode) {
                    btnSendVerificationCode.removeAttribute('disabled');
                    btnSendVerificationCode.innerHTML = 'Confirm &amp; Verify Email &rarr;';
                }

                clearOtpDigits();
                setTimeout(() => {
                    if (otpDigits[0]) {
                        otpDigits[0].focus();
                    }
                }, 200);

                startCooldownTimer(data.cooldown_seconds || 60);
                return true;

            } catch (err) {
                if (requestOtpError) {
                    requestOtpError.style.display = 'block';
                    requestOtpError.innerText = 'Network communication error. Please check your connection and retry.';
                }
                if (btnSendVerificationCode) {
                    btnSendVerificationCode.removeAttribute('disabled');
                    btnSendVerificationCode.innerHTML = 'Confirm &amp; Verify Email &rarr;';
                }
                return false;
            }
        }

        if (btnSendVerificationCode) {
            btnSendVerificationCode.addEventListener('click', dispatchOtpRequest);
        }

        // Resend Code in Phase 2
        if (btnResendOtp) {
            btnResendOtp.addEventListener('click', async () => {
                clearFeedback();
                btnResendOtp.setAttribute('disabled', 'disabled');
                btnResendOtp.innerHTML = 'Sending...';

                const success = await dispatchOtpRequest();
                if (!success) {
                    btnResendOtp.removeAttribute('disabled');
                    btnResendOtp.innerHTML = 'Resend Code';
                }
            });
        }

        // Back to Review / Selection
        if (btnBackToReview) {
            btnBackToReview.addEventListener('click', () => {
                clearInterval(timerInterval);
                if (modalPhaseVerification) modalPhaseVerification.style.display = 'none';
                if (modalPhaseReview) modalPhaseReview.style.display = 'block';
                clearFeedback();
                if (finalSubmissionError) {
                    finalSubmissionError.style.display = 'none';
                    finalSubmissionError.innerText = '';
                }
            });
        }

        // -------------------------------------------------------------
        // 6. Phase 2 -> Phase 2.5: Verify Code & Obtain Authorization
        // -------------------------------------------------------------
        async function verifySubmittedOtp() {
            clearFeedback();
            const code = getOtpCode();

            if (code.length !== 6) {
                showFeedback('Please enter the complete 6-digit numeric verification code.', 'error');
                if (otpDigits[0]) otpDigits[0].focus();
                return;
            }

            if (btnVerifyOtp) {
                btnVerifyOtp.setAttribute('disabled', 'disabled');
                btnVerifyOtp.innerHTML = '<span class="status-indicator-ping"></span> Verifying Code...';
            }

            const verifyEndpoint = selectedChannel === 'sms' ? 'verify_sms_otp.php' : 'verify_email_otp.php';

            try {
                const verifyResp = await fetch(verifyEndpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: new URLSearchParams({
                        challenge_id: challengeId,
                        otp: code,
                        csrf_token: csrfToken
                    })
                });

                const verifyData = await verifyResp.json();

                if (!verifyData.success || !verifyData.authorized || !verifyData.authorization_token) {
                    showFeedback(verifyData.error || 'Invalid verification code. Please check and retry.', 'error');
                    if (btnVerifyOtp) {
                        btnVerifyOtp.removeAttribute('disabled');
                        btnVerifyOtp.innerHTML = 'Verify Code &rarr;';
                    }
                    if (otpDigits[0]) {
                        otpDigits[0].focus();
                        otpDigits[0].select();
                    }
                    return;
                }

                // Code successfully verified! Store cryptographic single-use authorization token
                authorizationToken = verifyData.authorization_token;
                const modalAuthInput = document.getElementById('modal-auth-token');
                if (modalAuthInput) {
                    modalAuthInput.value = authorizationToken;
                }

                // Stop cooldown timer
                clearInterval(timerInterval);

                // Transition to Phase 2.5: Final Ballot Review & Confirmation Screen
                if (modalPhaseVerification) modalPhaseVerification.style.display = 'none';
                if (modalPhaseConfirmBallot) {
                    modalPhaseConfirmBallot.style.display = 'block';
                    if (confirmCandidateName) confirmCandidateName.innerText = selectedCandidateName;
                    if (confirmCandidatePosition) confirmCandidatePosition.innerText = selectedCandidatePosition;
                }

                if (btnVerifyOtp) {
                    btnVerifyOtp.removeAttribute('disabled');
                    btnVerifyOtp.innerHTML = 'Verify Code &rarr;';
                }

            } catch (err) {
                showFeedback('Network communication error during verification. Please retry.', 'error');
                if (btnVerifyOtp) {
                    btnVerifyOtp.removeAttribute('disabled');
                    btnVerifyOtp.innerHTML = 'Verify Code &rarr;';
                }
            }
        }

        if (btnVerifyOtp) {
            btnVerifyOtp.addEventListener('click', verifySubmittedOtp);
        }

        // -------------------------------------------------------------
        // 7. Phase 2.5 -> Phase 3: Final Ballot Submission & Receipt
        // -------------------------------------------------------------
        async function submitFinalBallot() {
            if (finalSubmissionError) {
                finalSubmissionError.style.display = 'none';
                finalSubmissionError.innerText = '';
            }

            const candidateRadio = document.querySelector('input[name="candidate_id"]:checked');
            if (!candidateRadio) {
                alert('No candidate selected. Please return and make a selection.');
                return;
            }

            if (!authorizationToken) {
                alert('Verification token missing. Please re-verify your identity.');
                if (modalPhaseConfirmBallot) modalPhaseConfirmBallot.style.display = 'none';
                if (modalPhaseVerification) modalPhaseVerification.style.display = 'block';
                return;
            }

            if (btnConfirmSubmitBallot) {
                btnConfirmSubmitBallot.setAttribute('disabled', 'disabled');
                btnConfirmSubmitBallot.innerHTML = '<span class="status-indicator-ping"></span> Recording Anonymized Ballot...';
            }

            try {
                const ballotResp = await fetch('cast_vote.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: new URLSearchParams({
                        election_id: electionId,
                        candidate_id: candidateRadio.value,
                        authorization_token: authorizationToken,
                        csrf_token: csrfToken,
                        format: 'json'
                    })
                });

                const ballotData = await ballotResp.json();

                if (!ballotData.success) {
                    if (finalSubmissionError) {
                        finalSubmissionError.style.display = 'block';
                        finalSubmissionError.innerText = ballotData.message || 'Ballot recording failed. Please try again.';
                    } else {
                        alert(ballotData.message || 'Ballot recording failed.');
                    }
                    if (btnConfirmSubmitBallot) {
                        btnConfirmSubmitBallot.removeAttribute('disabled');
                        btnConfirmSubmitBallot.innerHTML = 'Confirm &amp; Cast Official Ballot &rarr;';
                    }
                    return;
                }

                // Database transaction committed! Switch to Phase 3 Receipt View
                if (modalPhaseConfirmBallot) modalPhaseConfirmBallot.style.display = 'none';
                if (modalPhaseSuccess) {
                    modalPhaseSuccess.style.display = 'block';
                    if (successReceiptToken) {
                        successReceiptToken.innerText = ballotData.receipt_token || 'Receipt generated successfully';
                    }
                } else {
                    window.location.href = ballotData.redirect || 'voting-history.php';
                }

                // Disable page buttons
                if (openModalBtn) {
                    openModalBtn.setAttribute('disabled', 'disabled');
                    openModalBtn.innerText = 'Ballot Cast (Participation Recorded)';
                }

            } catch (err) {
                if (finalSubmissionError) {
                    finalSubmissionError.style.display = 'block';
                    finalSubmissionError.innerText = 'Network communication error during ballot submission. Please retry.';
                }
                if (btnConfirmSubmitBallot) {
                    btnConfirmSubmitBallot.removeAttribute('disabled');
                    btnConfirmSubmitBallot.innerHTML = 'Confirm &amp; Cast Official Ballot &rarr;';
                }
            }
        }

        if (btnConfirmSubmitBallot) {
            btnConfirmSubmitBallot.addEventListener('click', submitFinalBallot);
        }
    });
})();
