<?php
// admin/leads.php
require_once 'includes/db.php';
require_once 'models/Lead.php';
require_once 'models/Table.php';

include 'includes/header.php';

// Handle Direct POST Action (if submitted via standard HTML form)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'confirm_lead') {
        $leadId = (int)$_POST['lead_id'];
        $tableId = (int)$_POST['table_id'];
        SyncService::confirmLead($leadId, $tableId);
        header('Location: leads.php?msg=confirmed');
        exit();
    }
}

// Fetch all leads
$leads = Lead::getAll();
$available_tables = Table::getAvailable();
?>

<!-- Filter Chips & Search Bar -->
<div class="glass-card" style="padding: 16px 24px; margin-bottom: 4px;">
    <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 14px;">
        <div class="filter-chips" style="margin: 0;">
            <button class="chip-btn active" onclick="filterLeads('all')">All Leads (<?php echo count($leads); ?>)</button>
            <button class="chip-btn" onclick="filterLeads('new')">New</button>
            <button class="chip-btn" onclick="filterLeads('contacted')">Contacted</button>
            <button class="chip-btn" onclick="filterLeads('confirmed')">Confirmed</button>
            <button class="chip-btn" onclick="filterLeads('rejected')">Rejected</button>
            <button class="chip-btn" onclick="filterLeads('cancelled')">Cancelled</button>
        </div>
        <div style="display: flex; gap: 10px;">
            <input type="text" placeholder="Search customer or phone..." class="form-control" style="padding: 8px 16px; border-radius: var(--radius-pill); font-size: 0.825rem; width: 220px;" onkeyup="searchLeads(this.value)">
        </div>
    </div>
</div>

<!-- Leads Glass Table -->
<div class="glass-card">
    <div class="card-header">
        <h2 class="card-title"><i data-lucide="users"></i> Customer Leads & Inquiries</h2>
        <span class="badge-pill" style="font-size: 0.8rem; font-weight: 700; color: var(--primary-red); background: var(--light-pink); padding: 4px 12px; border-radius: var(--radius-pill);"><?php echo count($leads); ?> Enquiries</span>
    </div>

    <div class="table-responsive">
        <table id="leadsTable">
            <thead>
                <tr>
                    <th>BOOKING ID</th>
                    <th>CUSTOMER</th>
                    <th>CONTACT</th>
                    <th>PAX</th>
                    <th>DATE & TIME</th>
                    <th>SOURCE</th>
                    <th>STATUS</th>
                    <th>TABLE</th>
                    <th>ACTIONS</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($leads) > 0): ?>
                    <?php foreach ($leads as $lead): 
                        $status = htmlspecialchars($lead['status']);
                        $badgeClass = strtolower(str_replace(' ', '-', $status));
                        $assignedTable = $lead['assigned_table_id'] ? Table::getById($lead['assigned_table_id']) : null;
                    ?>
                    <tr class="lead-row" data-status="<?php echo $badgeClass; ?>" id="lead-row-<?php echo $lead['id']; ?>">
                        <td style="font-weight: 800; color: var(--primary-red); font-size: 0.8rem; white-space: nowrap;"><?php echo htmlspecialchars($lead['booking_id']); ?></td>
                        <td>
                            <div class="customer-col-box">
                                <div style="font-weight: 700; color: var(--text-primary); font-size: 0.825rem; white-space: nowrap;"><?php echo htmlspecialchars($lead['customer_name']); ?></div>
                                <div class="customer-email-text" title="<?php echo htmlspecialchars($lead['email'] ?? ''); ?>"><?php echo htmlspecialchars($lead['email'] ?? 'No Email'); ?></div>
                            </div>
                        </td>
                        <td style="font-weight: 600; font-size: 0.8rem; white-space: nowrap;"><?php echo htmlspecialchars($lead['phone']); ?></td>
                        <td style="white-space: nowrap; font-weight: 700; font-size: 0.85rem; color: var(--text-primary);">
                            <?php echo htmlspecialchars($lead['guest_count']); ?>
                        </td>
                        <td style="white-space: nowrap;">
                            <div style="font-weight: 700; font-size: 0.8rem; color: var(--text-primary);"><?php echo htmlspecialchars($lead['booking_date']); ?></div>
                            <div style="font-size: 0.725rem; color: var(--primary-red); font-weight: 700;"><?php echo date('g:i A', strtotime($lead['booking_time'])); ?></div>
                        </td>
                        <td style="white-space: nowrap;"><span class="badge-pill" style="font-size: 0.725rem; background: rgba(0,0,0,0.04); border-radius: 4px; padding: 2px 6px;"><?php echo htmlspecialchars($lead['booking_source'] ?? 'Online'); ?></span></td>
                        <td id="status-cell-<?php echo $lead['id']; ?>" style="white-space: nowrap;">
                            <span class="status-badge status-<?php echo $badgeClass; ?>"><?php echo $status; ?></span>
                        </td>
                        <td style="white-space: nowrap;">
                            <?php if ($assignedTable): ?>
                                <span style="font-weight: 800; color: var(--primary-red); font-size: 0.825rem;">T-<?php echo htmlspecialchars($assignedTable['table_number']); ?></span>
                            <?php else: ?>
                                <span style="color: var(--text-muted); font-style: italic; font-size: 0.75rem;">Unassigned</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="action-btns">
                                <?php if ($status === 'New'): ?>
                                <button type="button" class="action-btn action-btn-contact" title="Mark Contacted" onclick="markContacted(<?php echo $lead['id']; ?>)">
                                    <i data-lucide="phone"></i>
                                </button>
                                <?php endif; ?>
                                
                                <?php if ($status === 'New' || $status === 'Waiting List' || $status === 'Contacted'): ?>
                                <button type="button" class="action-btn action-btn-confirm" title="Confirm & Assign Table" onclick="openAcceptModal(<?php echo $lead['id']; ?>, '<?php echo $lead['booking_date']; ?>', '<?php echo $lead['booking_time']; ?>')">
                                    <i data-lucide="check"></i>
                                </button>
                                <button type="button" class="action-btn action-btn-reject" title="Reject Lead" onclick="rejectLead(<?php echo $lead['id']; ?>)">
                                    <i data-lucide="minus"></i>
                                </button>
                                <?php endif; ?>
                                
                                <?php if ($status !== 'Cancelled' && $status !== 'Completed' && $status !== 'Rejected' && $status !== 'Confirmed'): ?>
                                <button type="button" class="action-btn action-btn-cancel" title="Cancel" onclick="cancelLead(<?php echo $lead['id']; ?>)">
                                    <i data-lucide="x"></i>
                                </button>
                                <?php endif; ?>

                                <?php if ($status === 'Confirmed'): ?>
                                <button type="button" class="action-btn action-btn-noshow" title="Mark No Show" onclick="noShowLead(<?php echo $lead['id']; ?>)">
                                    <i data-lucide="user-x"></i>
                                </button>
                                <?php endif; ?>

                                <button type="button" class="action-btn action-btn-delete" title="Delete Lead" onclick="deleteLead(<?php echo $lead['id']; ?>)">
                                    <i data-lucide="trash-2"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="9">
                            <div class="empty-state">
                                <div class="empty-state-icon"><i data-lucide="inbox"></i></div>
                                <div class="empty-state-title">No Leads Found</div>
                                <div class="empty-state-desc">There are currently no customer booking requests or leads in the system.</div>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Confirm Lead & Assign Table -->
<div class="modal-overlay" id="acceptLeadModal">
    <div class="modal-glass-content">
        <div class="modal-header">
            <div class="modal-title">
                <i data-lucide="check-circle" style="color: #10B981;"></i> Confirm Lead & Assign Table
            </div>
            <button class="modal-close" onclick="closeModal('acceptLeadModal')">
                <i data-lucide="x"></i>
            </button>
        </div>
        <form id="acceptLeadForm" onsubmit="submitAcceptLead(event)">
            <input type="hidden" id="accept_lead_id" name="lead_id">
            
            <div class="form-group">
                <label class="form-label" style="font-weight: 700; color: var(--text-primary);">Select Table to Assign</label>
                <input type="hidden" id="accept_table_id" name="table_id" required>
                
                <div id="table_selection_container" class="table-selection-grid">
                    <div style="grid-column: 1/-1; text-align: center; color: var(--text-muted); padding: 20px;">
                        Loading available tables...
                    </div>
                </div>
                <div style="font-size: 0.725rem; color: var(--text-muted); margin-top: 6px;">
                    <i data-lucide="clock" style="width: 12px; height: 12px; vertical-align: middle;"></i> Smart 2-hour window buffer. Early-freed tables unlock automatically!
                </div>
            </div>

            <div style="margin-top: 24px; display: flex; gap: 12px; justify-content: flex-end;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('acceptLeadModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Confirm & Seat</button>
            </div>
        </form>
    </div>
</div>

<script>
async function openAcceptModal(leadId, date, time) {
    document.getElementById('accept_lead_id').value = leadId;
    document.getElementById('accept_table_id').value = '';
    const container = document.getElementById('table_selection_container');
    container.innerHTML = '<div style="grid-column: 1/-1; text-align: center; color: var(--text-muted); padding: 20px;">Checking available tables...</div>';
    openModal('acceptLeadModal');
    
    try {
        const res = await fetch(`api/router.php?action=tables.available_for_time&date=${date}&time=${time}`).then(r => r.json());
        if (res.success && res.data.length > 0) {
            container.innerHTML = '';
            res.data.forEach(t => {
                const tableName = t.table_name || `Table ${t.table_number}`;
                const card = document.createElement('div');
                card.className = 'table-select-card';
                card.dataset.id = t.id;
                card.innerHTML = `
                    <div class="table-name-label">${tableName}</div>
                    <div class="table-capacity-badge">${t.capacity} Seats</div>
                `;
                card.onclick = function() {
                    document.querySelectorAll('.table-select-card').forEach(c => c.classList.remove('selected'));
                    card.classList.add('selected');
                    document.getElementById('accept_table_id').value = t.id;
                };
                container.appendChild(card);
            });
        } else {
            container.innerHTML = '<div style="grid-column: 1/-1; text-align: center; color: var(--text-muted); padding: 20px;">No tables available within 2-hour window</div>';
        }
    } catch (err) {
        container.innerHTML = '<div style="grid-column: 1/-1; text-align: center; color: #ef4444; padding: 20px;">Failed to load tables</div>';
    }
}

async function submitAcceptLead(e) {
    e.preventDefault();
    const leadId = document.getElementById('accept_lead_id').value;
    const tableId = document.getElementById('accept_table_id').value;

    if (!tableId) {
        showToast("Please select a table", "error");
        return;
    }

    const res = await apiRequest('leads.confirm', { lead_id: leadId, table_id: tableId });
    if (res.success) {
        closeModal('acceptLeadModal');
        setTimeout(() => location.reload(), 800);
    }
}

async function markContacted(leadId) {
    const res = await apiRequest('leads.contacted', { lead_id: leadId });
    if (res.success) {
        setTimeout(() => location.reload(), 800);
    }
}

async function rejectLead(leadId) {
    const reason = prompt("Enter reason for rejecting booking (e.g. Fully Booked):", "Fully Booked");
    if (reason === null) return;

    const res = await apiRequest('leads.reject', { lead_id: leadId, reason: reason });
    if (res.success) {
        setTimeout(() => location.reload(), 800);
    }
}

async function cancelLead(leadId) {
    if (!confirm("Cancel this booking?")) return;
    const res = await apiRequest('leads.cancel', { lead_id: leadId });
    if (res.success) {
        setTimeout(() => location.reload(), 800);
    }
}

async function noShowLead(leadId) {
    if (!confirm("Mark this booking as No Show?")) return;
    const res = await apiRequest('leads.noshow', { lead_id: leadId });
    if (res.success) {
        setTimeout(() => location.reload(), 800);
    }
}

async function deleteLead(leadId) {
    if (!confirm("Delete this lead permanently?")) return;
    const res = await apiRequest('leads.delete', { lead_id: leadId });
    if (res.success) {
        setTimeout(() => location.reload(), 800);
    }
}

function filterLeads(statusFilter) {
    document.querySelectorAll('.chip-btn').forEach(btn => btn.classList.remove('active'));
    event.target.classList.add('active');

    const rows = document.querySelectorAll('.lead-row');
    rows.forEach(row => {
        if (statusFilter === 'all' || row.dataset.status === statusFilter) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

function searchLeads(query) {
    query = query.toLowerCase();
    const rows = document.querySelectorAll('.lead-row');
    rows.forEach(row => {
        const text = row.innerText.toLowerCase();
        row.style.display = text.includes(query) ? '' : 'none';
    });
}
</script>

<?php include 'includes/footer.php'; ?>
