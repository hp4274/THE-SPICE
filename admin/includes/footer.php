            </div>
            <!-- Page Content Ends -->
        </main>
        <!-- Main Content Wrapper Ends -->
    </div>
    <!-- App Wrapper Ends -->

    <!-- Global Search Modal (Ctrl + K) -->
    <div class="modal-overlay" id="globalSearchModal">
        <div class="modal-glass-content" style="max-width: 600px; padding: 24px;">
            <div class="modal-header" style="margin-bottom: 16px;">
                <div class="modal-title">
                    <i data-lucide="search"></i> Global Search
                </div>
                <button class="modal-close" onclick="closeModal('globalSearchModal')">
                    <i data-lucide="x"></i>
                </button>
            </div>
            <div class="form-group" style="margin-bottom: 16px;">
                <input type="text" id="globalSearchInput" placeholder="Search by customer name, table number, reservation ID..." class="form-control" style="font-size: 1rem; padding: 14px 18px; border-radius: var(--radius-pill);" onkeyup="handleGlobalSearch(this.value)">
            </div>
            <div id="globalSearchResults" style="max-height: 300px; overflow-y: auto;">
                <div style="text-align: center; color: var(--text-muted); padding: 24px; font-size: 0.875rem;">
                    Type to search reservations, active tables, leads, or bills...
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Action Modal -->
    <div class="modal-overlay" id="quickActionModal">
        <div class="modal-glass-content" style="max-width: 480px;">
            <div class="modal-header">
                <div class="modal-title">
                    <i data-lucide="zap"></i> Quick Action
                </div>
                <button class="modal-close" onclick="closeModal('quickActionModal')">
                    <i data-lucide="x"></i>
                </button>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                <button class="btn btn-secondary" style="flex-direction: column; padding: 20px 14px; gap: 10px; border-radius: var(--radius-md);" onclick="closeModal('quickActionModal'); window.location.href='reservations.php?action=new';">
                    <div style="background: var(--light-pink); color: var(--primary-red); width: 44px; height: 44px; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                        <i data-lucide="calendar-plus" style="width: 22px;"></i>
                    </div>
                    <span style="font-size: 0.875rem; font-weight: 700;">New Reservation</span>
                </button>

                <button class="btn btn-secondary" style="flex-direction: column; padding: 20px 14px; gap: 10px; border-radius: var(--radius-md);" onclick="closeModal('quickActionModal'); window.location.href='tables.php?action=walkin';">
                    <div style="background: rgba(249,115,22,0.12); color: #F97316; width: 44px; height: 44px; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                        <i data-lucide="user-check" style="width: 22px;"></i>
                    </div>
                    <span style="font-size: 0.875rem; font-weight: 700;">Seat Walk-in</span>
                </button>

                <button class="btn btn-secondary" style="flex-direction: column; padding: 20px 14px; gap: 10px; border-radius: var(--radius-md);" onclick="closeModal('quickActionModal'); window.location.href='tables.php?action=cleantable';">
                    <div style="background: rgba(229,57,53,0.12); color: #E53935; width: 44px; height: 44px; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                        <i data-lucide="sparkles" style="width: 22px;"></i>
                    </div>
                    <span style="font-size: 0.875rem; font-weight: 700;">Clean Tables</span>
                </button>

                <button class="btn btn-secondary" style="flex-direction: column; padding: 20px 14px; gap: 10px; border-radius: var(--radius-md);" onclick="closeModal('quickActionModal'); window.location.href='tables.php?action=mergetable';">
                    <div style="background: rgba(59,130,246,0.12); color: #3B82F6; width: 44px; height: 44px; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                        <i data-lucide="layers" style="width: 22px;"></i>
                    </div>
                    <span style="font-size: 0.875rem; font-weight: 700;">Merge Tables</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Modal: Table Capacity Warning & Resolution -->
    <div class="modal-overlay" id="capacityWarningModal" style="z-index: 10000;">
        <div class="modal-glass-content" style="max-width: 520px; border: 1px solid rgba(239, 68, 68, 0.4); box-shadow: 0 16px 48px rgba(229, 57, 53, 0.2);">
            <div class="modal-header" style="border-bottom: 1px solid rgba(239, 68, 68, 0.15); padding-bottom: 12px;">
                <div class="modal-title" style="color: var(--primary-red); font-size: 1.1rem; display: flex; align-items: center; gap: 8px;">
                    <i data-lucide="alert-triangle" style="width: 22px; height: 22px;"></i> Table Capacity Exceeded
                </div>
                <button class="modal-close" onclick="closeModal('capacityWarningModal')">
                    <i data-lucide="x"></i>
                </button>
            </div>
            
            <div style="padding: 16px 0;">
                <div style="background: rgba(229,57,53,0.06); border: 1px solid rgba(229,57,53,0.2); padding: 14px 16px; border-radius: 12px; margin-bottom: 18px;">
                    <div style="font-size: 0.88rem; font-weight: 800; color: var(--primary-red);" id="cap_warning_headline">
                        ⚠️ Selected table only seats 4 guests, but party has 6 guests!
                    </div>
                    <div style="font-size: 0.78rem; color: var(--text-secondary); margin-top: 4px;">
                        Please select a larger table below, or merge tables to accommodate all guests cleanly.
                    </div>
                </div>

                <!-- Option 1: Capable Tables List -->
                <div style="font-size: 0.78rem; font-weight: 800; color: var(--text-primary); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 10px;">
                    Option 1: Recommended Available Tables (Fits <span id="cap_warning_count">6</span>+ Guests)
                </div>

                <div id="cap_capable_tables_list" style="display: flex; flex-direction: column; gap: 8px; max-height: 160px; overflow-y: auto; margin-bottom: 18px;">
                    <!-- Populated dynamically -->
                </div>

                <!-- Option 2: Merge Tables Action -->
                <div style="display: flex; gap: 10px; align-items: center; justify-content: space-between; background: rgba(59,130,246,0.06); border: 1px solid rgba(59,130,246,0.2); padding: 12px 16px; border-radius: 12px;">
                    <div>
                        <div style="font-size: 0.85rem; font-weight: 800; color: #3B82F6; display: flex; align-items: center; gap: 6px;">
                            <i data-lucide="layers" style="width: 14px;"></i> Combine Two Tables
                        </div>
                        <div style="font-size: 0.75rem; color: var(--text-secondary); margin-top: 2px;">
                            Merge selected table with an adjacent table to seat this party.
                        </div>
                    </div>
                    <button type="button" class="btn btn-secondary btn-sm" style="color: #3B82F6; border-color: #3B82F6; font-weight: 700; flex-shrink: 0;" onclick="goToMergeModalFromWarning()">
                        <i data-lucide="layers" style="width: 14px;"></i> Merge Tables
                    </button>
                </div>
            </div>

            <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 8px; border-top: 1px solid rgba(0,0,0,0.06); padding-top: 14px;">
                <button type="button" class="btn btn-secondary btn-sm" onclick="closeModal('capacityWarningModal')">Cancel</button>
                <button type="button" class="btn btn-secondary btn-sm" style="opacity: 0.85;" onclick="proceedWithForceAssignment()">Proceed Anyway (Add Chairs)</button>
            </div>
        </div>
    </div>

    <!-- JavaScript Files -->
    <script src="js/admin-script.js?v=<?php echo time(); ?>"></script>
    <script src="js/ws_client.js?v=<?php echo time(); ?>"></script>

    <script>
    function openGlobalSearch() {
        openModal('globalSearchModal');
        setTimeout(() => {
            const input = document.getElementById('globalSearchInput');
            if (input) input.focus();
        }, 150);
    }

    function openQuickActionModal() {
        openModal('quickActionModal');
    }

    // Ctrl + K Keyboard Shortcut
    document.addEventListener('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
            e.preventDefault();
            openGlobalSearch();
        }
    });

    const SEARCH_IDLE_HTML = '<div style="text-align: center; color: var(--text-muted); padding: 24px; font-size: 0.875rem;">Type to search reservations, active tables, leads, or bills...</div>';
    let globalSearchTimer = null;
    let globalSearchSeq = 0;

    function escapeSearchHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, c => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
        })[c]);
    }

    // Debounced live search against api/router.php?action=search.global
    function handleGlobalSearch(query) {
        const resultsDiv = document.getElementById('globalSearchResults');
        if (!resultsDiv) return;

        clearTimeout(globalSearchTimer);
        if (!query.trim()) {
            resultsDiv.innerHTML = SEARCH_IDLE_HTML;
            return;
        }

        resultsDiv.innerHTML = '<div style="text-align: center; color: var(--text-muted); padding: 24px; font-size: 0.875rem;">Searching…</div>';

        globalSearchTimer = setTimeout(async () => {
            const seq = ++globalSearchSeq;
            try {
                const res = await fetch('api/router.php?action=search.global&q=' + encodeURIComponent(query), { cache: 'no-store' })
                    .then(r => r.json());
                if (seq !== globalSearchSeq) return; // a newer keystroke already won

                const rows = (res && res.success && Array.isArray(res.data)) ? res.data : [];
                if (rows.length === 0) {
                    resultsDiv.innerHTML = '<div style="text-align: center; color: var(--text-muted); padding: 24px; font-size: 0.875rem;">No matches for "' + escapeSearchHtml(query) + '"</div>';
                    return;
                }

                const typeIcon = { Reservation: 'calendar-check', Lead: 'inbox', Table: 'grid-2x2', Bill: 'receipt' };
                let html = '<div style="display: flex; flex-direction: column; gap: 8px;">';
                rows.forEach(row => {
                    const badgeClass = 'status-' + String(row.status || '').toLowerCase().replace(/\s+/g, '-');
                    html += `
                        <a href="${escapeSearchHtml(row.page)}" class="search-result-item" style="display: flex; align-items: center; gap: 12px; padding: 12px 16px; background: rgba(255,255,255,0.7); border: 1px solid rgba(0,0,0,0.05); border-radius: var(--radius-sm); text-decoration: none;">
                            <div style="background: var(--light-pink); color: var(--primary-red); width: 34px; height: 34px; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                <i data-lucide="${typeIcon[row.type] || 'search'}" style="width: 16px;"></i>
                            </div>
                            <div style="flex: 1; min-width: 0;">
                                <div style="font-weight: 700; color: var(--text-primary); font-size: 0.875rem;">${escapeSearchHtml(row.title)}</div>
                                <div style="font-size: 0.75rem; color: var(--text-muted);">${escapeSearchHtml(row.type)} · ${escapeSearchHtml(row.ref)} · ${escapeSearchHtml(row.sub)}</div>
                            </div>
                            <span class="status-badge ${badgeClass}">${escapeSearchHtml(row.status)}</span>
                        </a>
                    `;
                });
                html += '</div>';
                resultsDiv.innerHTML = html;
                if (window.lucide) lucide.createIcons();
            } catch (err) {
                if (seq !== globalSearchSeq) return;
                resultsDiv.innerHTML = '<div style="text-align: center; color: #ef4444; padding: 24px; font-size: 0.875rem;">Search failed. Try again.</div>';
            }
        }, 220);
    }

    // Esc closes whatever modal is on top
    document.addEventListener('keydown', function(e) {
        if (e.key !== 'Escape') return;
        const open = Array.from(document.querySelectorAll('.modal-overlay.active'));
        if (open.length === 0) return;
        closeModal(open[open.length - 1].id);
    });
    </script>

</body>
</html>
