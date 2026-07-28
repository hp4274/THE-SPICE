// admin/js/ws_client.js - The Spice Admin Live WebSocket Notification Client

(function() {
    let ws = null;
    let reconnectTimer = null;
    const wsPort = 8080;

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

    function initWebSocket() {
        const host = window.location.hostname || 'localhost';
        const wsUrl = `ws://${host}:${wsPort}`;

        try {
            ws = new WebSocket(wsUrl);

            ws.onopen = function() {
                console.log('[Ratchet WS] Connected to Admin Live Notification Server on ' + wsUrl);
                if (reconnectTimer) {
                    clearTimeout(reconnectTimer);
                    reconnectTimer = null;
                }
            };

            ws.onmessage = function(e) {
                try {
                    const payload = JSON.parse(e.data);
                    handleServerEvent(payload);
                } catch (err) {
                    console.error('[Ratchet WS] Error parsing message:', err);
                }
            };

            ws.onclose = function() {
                console.warn('[Ratchet WS] Disconnected. Reconnecting in 5 seconds...');
                scheduleReconnect();
            };

            ws.onerror = function() {
                console.warn('[Ratchet WS] Socket encountered error.');
                ws.close();
            };
        } catch (err) {
            console.error('[Ratchet WS] Connection error:', err);
            scheduleReconnect();
        }
    }

    function scheduleReconnect() {
        if (!reconnectTimer) {
            reconnectTimer = setTimeout(initWebSocket, 5000);
        }
    }

    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function handleServerEvent(payload) {
        if (!payload || !payload.event) return;

        console.log('[Ratchet WS Event]', payload.event, payload.data);

        if (payload.event === 'new_lead') {
            const data = payload.data || {};
            const customerName = data.customer_name || 'New Customer';
            const guestCountStr = data.guest_count ? ` (${data.guest_count} Guests)` : '';
            const bookingDate = data.booking_date || '';
            const bookingTime = data.booking_time || '';
            const timeInfo = bookingDate + (bookingTime ? ' at ' + bookingTime : '');

            // 1. Play Audio Chime Sound
            playChimeSound();

            // 2. Show Glass Toast Alert
            if (typeof showToast === 'function') {
                showToast(`🎉 New Booking Request! ${customerName}${guestCountStr}`, 'success');
            }

            // 3. Highlight Topbar Notification Dot
            const dot = document.querySelector('.badge-dot');
            if (dot) {
                dot.style.display = 'block';
                dot.classList.add('pulse');
            }

            // 4. Prepend item to Header Notification Dropdown List
            const notifContainer = document.getElementById('notifListContainer');
            if (notifContainer) {
                const noNotif = document.getElementById('noNotifPlaceholder');
                if (noNotif) {
                    noNotif.remove();
                }

                const newNotifHtml = `
                    <a href="leads.php" class="notif-item" style="display: block; text-decoration: none; padding: 14px 18px; border-bottom: 1px solid rgba(0,0,0,0.04); background: rgba(229,57,53,0.06); transition: background 0.2s;" onmouseover="this.style.background='rgba(229,57,53,0.1)'" onmouseout="this.style.background='rgba(229,57,53,0.06)'">
                        <div style="display: flex; align-items: flex-start; gap: 12px;">
                            <div style="background: var(--light-pink); color: var(--primary-red); width: 34px; height: 34px; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                <i data-lucide="users" style="width: 16px;"></i>
                            </div>
                            <div style="flex: 1;">
                                <div style="font-size: 13px; font-weight: 700; color: var(--text-primary);">New Lead Request <span style="font-size:10px; background:var(--primary-red); color:#fff; padding:2px 6px; border-radius:10px; margin-left:4px;">NEW</span></div>
                                <div style="font-size: 12px; color: var(--text-secondary); margin-top: 2px;">${escapeHtml(customerName)}${escapeHtml(guestCountStr)}</div>
                                <div style="font-size: 11px; color: var(--primary-red); margin-top: 4px; font-weight: 600;">${escapeHtml(timeInfo)}</div>
                            </div>
                        </div>
                    </a>
                `;

                notifContainer.insertAdjacentHTML('afterbegin', newNotifHtml);

                if (window.lucide && typeof window.lucide.createIcons === 'function') {
                    window.lucide.createIcons();
                }
            }

            // 5. Auto Refresh Leads Table if on leads.php page
            if (window.location.pathname.includes('leads.php')) {
                setTimeout(() => {
                    location.reload();
                }, 1200);
            }
        }
    }

    // Initialize Ratchet Client on DOM Load
    if (document.readyState === 'complete' || document.readyState === 'interactive') {
        initWebSocket();
    } else {
        document.addEventListener('DOMContentLoaded', initWebSocket);
    }
})();
