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

async function apiRequest(action, data = {}) {
    try {
        const response = await fetch(`api/router.php?action=${action}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        const result = await response.json();
        if (!result.success) {
            showToast(result.message || 'Operation failed', 'error');
        } else {
            showToast(result.message || 'Operation completed successfully!');
        }
        return result;
    } catch(err) {
        showToast('Network request failed', 'error');
        return { success: false };
    }
}

async function markContacted(leadId) {
    if (!confirm("Mark this lead as contacted?")) return;
    const res = await apiRequest('leads.contacted', { lead_id: leadId });
    if (res.success) {
        setTimeout(() => location.reload(), 800);
    }
}

async function cancelLead(leadId) {
    if (!confirm("Cancel this lead?")) return;
    const res = await apiRequest('leads.cancel', { lead_id: leadId });
    if (res.success) {
        setTimeout(() => location.reload(), 800);
    }
}

async function deleteLead(leadId) {
    if (!confirm("Are you sure you want to delete this lead? This cannot be undone.")) return;
    const res = await apiRequest('leads.delete', { lead_id: leadId });
    if (res.success) {
        setTimeout(() => location.reload(), 800);
    }
}
