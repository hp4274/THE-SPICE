            </div>
            <!-- Page Content Ends -->
        </main>
        <!-- Main Content Wrapper Ends -->
    </div>
    <!-- App Wrapper Ends -->

    <!-- Floating Action Button (FAB) -->
    <button class="fab-btn" title="Quick Action" onclick="openQuickActionModal()">
        <i data-lucide="plus" style="width: 26px; height: 26px;"></i>
    </button>

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

                <button class="btn btn-secondary" style="flex-direction: column; padding: 20px 14px; gap: 10px; border-radius: var(--radius-md);" onclick="closeModal('quickActionModal'); window.location.href='tables.php';">
                    <div style="background: rgba(249,115,22,0.12); color: #F97316; width: 44px; height: 44px; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                        <i data-lucide="user-check" style="width: 22px;"></i>
                    </div>
                    <span style="font-size: 0.875rem; font-weight: 700;">Seat Walk-in</span>
                </button>

                <button class="btn btn-secondary" style="flex-direction: column; padding: 20px 14px; gap: 10px; border-radius: var(--radius-md);" onclick="closeModal('quickActionModal'); window.location.href='bills.php';">
                    <div style="background: rgba(16,185,129,0.12); color: #10B981; width: 44px; height: 44px; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                        <i data-lucide="credit-card" style="width: 22px;"></i>
                    </div>
                    <span style="font-size: 0.875rem; font-weight: 700;">Generate Bill</span>
                </button>

                <button class="btn btn-secondary" style="flex-direction: column; padding: 20px 14px; gap: 10px; border-radius: var(--radius-md);" onclick="closeModal('quickActionModal'); window.location.href='sales.php';">
                    <div style="background: rgba(139,92,246,0.12); color: #8B5CF6; width: 44px; height: 44px; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                        <i data-lucide="file-text" style="width: 22px;"></i>
                    </div>
                    <span style="font-size: 0.875rem; font-weight: 700;">Sales Report</span>
                </button>
            </div>
        </div>
    </div>

    <!-- JavaScript Files -->
    <script src="js/admin-script.js"></script>
    <script src="js/ws_client.js"></script>

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

    function handleGlobalSearch(query) {
        const resultsDiv = document.getElementById('globalSearchResults');
        if (!query.trim()) {
            resultsDiv.innerHTML = '<div style="text-align: center; color: var(--text-muted); padding: 24px; font-size: 0.875rem;">Type to search reservations, active tables, leads, or bills...</div>';
            return;
        }

        query = query.toLowerCase();
        let html = '<div style="display: flex; flex-direction: column; gap: 8px;">';
        
        // Dynamic search match simulation
        html += `
            <a href="reservations.php" style="display: flex; align-items: center; justify-content: space-between; padding: 12px 16px; background: rgba(255,255,255,0.7); border-radius: var(--radius-sm); text-decoration: none;">
                <div>
                    <div style="font-weight: 700; color: var(--text-primary);">Search Match: "${query}"</div>
                    <div style="font-size: 0.75rem; color: var(--text-muted);">Click to jump to Reservations & Leads</div>
                </div>
                <span class="status-badge status-confirmed">Jump →</span>
            </a>
        `;
        html += '</div>';
        resultsDiv.innerHTML = html;
    }
    </script>

</body>
</html>
