// admin/js/ws_client.js - The Spice Admin Live Polling Sync Client (100% Hostinger & Shared Hosting Compatible)

(function() {
    let isPolling = false;
    let pollInterval = null;
    let lastAuditId = 0;
    let lastLeadCount = -1;
    let lastTableState = null;
    let pendingRemoteChange = false;

    // Web Audio API Synthesized Chime (Zero External Files Needed)
    function playChimeSound() {
        try {
            const AudioContext = window.AudioContext || window.webkitAudioContext;
            if (!AudioContext) return;
            const ctx = new AudioContext();
            
            // Note 1 (E5)
            const osc1 = ctx.createOscillator();
            const gain1 = ctx.createGain();
            osc1.type = 'sine';
            osc1.frequency.setValueAtTime(659.25, ctx.currentTime);
            gain1.gain.setValueAtTime(0.15, ctx.currentTime);
            gain1.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.6);
            osc1.connect(gain1);
            gain1.connect(ctx.destination);
            osc1.start(ctx.currentTime);
            osc1.stop(ctx.currentTime + 0.6);

            // Note 2 (B5) - Harmonic second note
            setTimeout(() => {
                const osc2 = ctx.createOscillator();
                const gain2 = ctx.createGain();
                osc2.type = 'sine';
                osc2.frequency.setValueAtTime(987.77, ctx.currentTime);
                gain2.gain.setValueAtTime(0.2, ctx.currentTime);
                gain2.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.8);
                osc2.connect(gain2);
                gain2.connect(ctx.destination);
                osc2.start(ctx.currentTime);
                osc2.stop(ctx.currentTime + 0.8);
            }, 120);
        } catch (e) {
            // Audio context blocked or unsupported
        }
    }

    // Never yank the page out from under the user: a reload while a modal is open
    // wipes a half-filled form, and a reload right after our own action double-loads.
    function reloadIsSafe() {
        if (document.hidden) return false;
        if (document.querySelector('.modal-overlay.active')) return false;
        if (document.body.classList.contains('is-refreshing')) return false;
        if (window.__lastLocalMutation && (Date.now() - window.__lastLocalMutation) < 6000) return false;
        return true;
    }

    function refreshPage() {
        if (typeof window.smoothReload === 'function') {
            window.smoothReload(300);
        } else {
            location.reload();
        }
    }

    function isLivePage() {
        const page = window.location.pathname;
        return page.includes('leads.php') || page.includes('reservations.php')
            || page.includes('tables.php') || page.includes('index.php');
    }

    function startPolling() {
        if (isPolling) return;
        isPolling = true;

        // Initial Poll
        pollServer();

        // 5 Second Interval Poll
        pollInterval = setInterval(pollServer, 5000);

        // Catch up immediately when the tab comes back into focus
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) pollServer();
        });
    }

    async function pollServer() {
        // A remote change arrived while a modal was open / an action was in flight —
        // apply it as soon as the user is idle again.
        if (pendingRemoteChange && isLivePage() && reloadIsSafe()) {
            pendingRemoteChange = false;
            refreshPage();
            return;
        }

        try {
            const response = await fetch('api/router.php?action=sync.poll', {
                method: 'GET',
                cache: 'no-store'
            });
            if (!response.ok) return;
            const data = await response.json();
            if (!data || !data.success) return;

            // A. Overdue No-Show Alert
            if (data.no_shows_updated > 0) {
                if (typeof showToast === 'function') {
                    showToast(`⚠️ ${data.no_shows_updated} overdue booking(s) auto-marked No Show`, 'error');
                }
                if (isLivePage() && reloadIsSafe()) {
                    refreshPage();
                    return;
                }
            }

            // B. State Change Detection (Multi-Admin / Web Inquiries)
            if (lastAuditId === 0) {
                // First Baseline Sync
                lastAuditId = data.latest_audit_id || 0;
                lastLeadCount = data.lead_count ?? -1;
                lastTableState = data.table_state || null;
            } else {
                // Detect New Inquiries / Remote Changes
                const auditChanged = data.latest_audit_id > lastAuditId;
                const leadsChanged = lastLeadCount >= 0 && data.lead_count !== lastLeadCount;
                const tablesChanged = lastTableState && data.table_state !== lastTableState;

                if (auditChanged || leadsChanged || tablesChanged) {
                    const newLeadReceived = leadsChanged && data.lead_count > lastLeadCount;

                    lastAuditId = data.latest_audit_id;
                    lastLeadCount = data.lead_count;
                    lastTableState = data.table_state;

                    // Play chime on new lead count increase
                    if (newLeadReceived) {
                        playChimeSound();
                        if (typeof showToast === 'function') {
                            showToast('🎉 New Customer Booking Received!', 'success');
                        }
                    }

                    // Refresh active management pages, unless the user is mid-task
                    if (isLivePage()) {
                        if (reloadIsSafe()) {
                            refreshPage();
                        } else {
                            pendingRemoteChange = true;
                        }
                    }
                }
            }
        } catch (e) {
            // Silent network pass
        }
    }

    // Initialize Polling Engine on DOM Load
    if (document.readyState === 'complete' || document.readyState === 'interactive') {
        startPolling();
    } else {
        document.addEventListener('DOMContentLoaded', startPolling);
    }
})();
