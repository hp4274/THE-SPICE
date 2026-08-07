// admin/js/realtime.js
document.addEventListener('DOMContentLoaded', function() {
    // Inject Toast Container if not existing
    if (!document.getElementById('toast-container')) {
        const toastContainer = document.createElement('div');
        toastContainer.id = 'toast-container';
        document.body.appendChild(toastContainer);
    }
});

function showToast(message, type = 'success') {
    let container = document.getElementById('toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    toast.className = `toast ${type === 'error' ? 'toast-error' : ''}`;
    
    const iconName = type === 'error' ? 'alert-circle' : 'check-circle';
    const iconColor = type === 'error' ? '#E53935' : '#10B981';
    const titleText = type === 'error' ? 'Error' : 'Success';

    toast.innerHTML = `
        <div style="background: ${type === 'error' ? 'rgba(229, 57, 53, 0.1)' : 'rgba(16, 185, 129, 0.1)'}; width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
            <i data-lucide="${iconName}" style="color: ${iconColor}; width: 20px; height: 20px;"></i>
        </div>
        <div>
            <div class="toast-title" style="color: ${iconColor};">${titleText}</div>
            <div style="font-size: 13px; font-weight: 600; color: var(--text-primary); margin-top: 2px;">
                ${message}
            </div>
        </div>
    `;
    
    container.appendChild(toast);
    if (window.lucide) { lucide.createIcons(); }
    
    setTimeout(() => {
        toast.classList.add('show');
    }, 10);
    
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 400);
    }, 3500);
}

// Timestamp of the last mutation this tab made. ws_client.js uses it to re-baseline
// instead of firing a second reload on top of the one we already scheduled.
window.__lastLocalMutation = 0;

// Global busy state: blocks double-submits and drives the page progress bar
let activeRequests = 0;

function setPageBusy(busy) {
    activeRequests = Math.max(0, activeRequests + (busy ? 1 : -1));
    document.body.classList.toggle('is-busy', activeRequests > 0);
}

async function apiRequest(action, data = {}) {
    setPageBusy(true);
    try {
        const response = await fetch(`api/router.php?action=${action}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        const result = await response.json();
        window.__lastLocalMutation = Date.now();
        if (!result.success) {
            showToast(result.message || 'Operation failed', 'error');
        } else if (result.message) {
            showToast(result.message);
        }
        return result;
    } catch(err) {
        showToast('Network request failed', 'error');
        return { success: false };
    } finally {
        setPageBusy(false);
    }
}

// Fade the page out before reloading so the refresh doesn't flash.
function smoothReload(delay = 450) {
    document.body.classList.add('is-refreshing');
    setTimeout(() => location.reload(), delay);
}
window.smoothReload = smoothReload;

// Wrap a click handler so its button shows a spinner and can't be double-fired.
async function withBusy(el, fn) {
    const btn = (el && el.closest) ? el.closest('button, .btn, .action-btn') : null;
    if (btn) {
        if (btn.dataset.busy === '1') return;
        btn.dataset.busy = '1';
        btn.classList.add('is-loading');
        btn.disabled = true;
    }
    try {
        return await fn();
    } finally {
        if (btn) {
            delete btn.dataset.busy;
            btn.classList.remove('is-loading');
            btn.disabled = false;
        }
    }
}
window.withBusy = withBusy;

// Global Table Capacity Warning Modal Controller
let pendingCapacityAssignment = null;

function showCapacityWarningModal({
    tableId = null,
    tableName,
    tableCapacity,
    partySize,
    availableTables = [],
    onSelectTable,
    onMergeTables,
    onProceedAnyway
}) {
    pendingCapacityAssignment = {
        tableId,
        onSelectTable,
        onMergeTables,
        onProceedAnyway
    };

    const headline = document.getElementById('cap_warning_headline');
    if (headline) {
        headline.innerHTML = `⚠️ ${tableName} seats ${tableCapacity} guests, but party has ${partySize} guests!`;
    }
    
    const countSpan = document.getElementById('cap_warning_count');
    if (countSpan) {
        countSpan.innerText = partySize;
    }

    const listContainer = document.getElementById('cap_capable_tables_list');
    if (listContainer) {
        listContainer.innerHTML = '';

        const capableTables = availableTables.filter(t => parseInt(t.capacity) >= partySize);

        if (capableTables.length > 0) {
            capableTables.forEach(t => {
                const tName = t.table_name || `Table ${t.table_number}`;
                const item = document.createElement('div');
                item.style.cssText = 'display: flex; align-items: center; justify-content: space-between; background: rgba(16,185,129,0.06); border: 1px solid rgba(16,185,129,0.25); padding: 10px 14px; border-radius: 10px; cursor: pointer; transition: background 0.2s;';
                item.onmouseover = () => item.style.background = 'rgba(16,185,129,0.12)';
                item.onmouseout = () => item.style.background = 'rgba(16,185,129,0.06)';
                item.innerHTML = `
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <i data-lucide="check-circle-2" style="color: #10B981; width: 16px; height: 16px;"></i>
                        <span style="font-weight: 800; font-size: 0.85rem; color: var(--text-primary);">${tName}</span>
                    </div>
                    <span style="font-size: 0.72rem; font-weight: 800; background: #10B981; color: #fff; padding: 3px 10px; border-radius: 12px;">
                        ${t.capacity} Seats Available
                    </span>
                `;
                item.onclick = () => {
                    closeModal('capacityWarningModal');
                    if (pendingCapacityAssignment && pendingCapacityAssignment.onSelectTable) {
                        pendingCapacityAssignment.onSelectTable(t.id);
                    }
                };
                listContainer.appendChild(item);
            });
        } else {
            listContainer.innerHTML = `
                <div style="text-align: center; padding: 14px 10px; color: var(--text-muted); font-size: 0.8rem; background: rgba(0,0,0,0.03); border-radius: 8px; font-weight: 600;">
                    No single available table fits ${partySize} guests. Please click "Merge Tables" below to combine two tables!
                </div>
            `;
        }

        if (window.lucide && typeof window.lucide.createIcons === 'function') {
            window.lucide.createIcons();
        }
    }

    openModal('capacityWarningModal');
}

function proceedWithForceAssignment() {
    closeModal('capacityWarningModal');
    if (pendingCapacityAssignment && pendingCapacityAssignment.onProceedAnyway) {
        pendingCapacityAssignment.onProceedAnyway();
    }
}

function goToMergeModalFromWarning() {
    closeModal('capacityWarningModal');
    if (pendingCapacityAssignment && pendingCapacityAssignment.onMergeTables) {
        pendingCapacityAssignment.onMergeTables();
        return;
    }
    // Fall back to the floor plan, pre-selecting the table that was too small
    const primaryId = pendingCapacityAssignment && pendingCapacityAssignment.tableId;
    if (typeof openMergeModal === 'function') {
        openMergeModal(primaryId || null);
    } else {
        window.location.href = 'tables.php?action=merge' + (primaryId ? '&primary=' + primaryId : '');
    }
}

window.showCapacityWarningModal = showCapacityWarningModal;
window.proceedWithForceAssignment = proceedWithForceAssignment;
window.goToMergeModalFromWarning = goToMergeModalFromWarning;
