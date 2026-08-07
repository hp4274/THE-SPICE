<?php
// admin/reservations.php
require_once 'includes/db.php';
require_once 'models/Reservation.php';
require_once 'models/Lead.php';
require_once 'models/Table.php';
require_once 'models/Bill.php';
require_once 'services/SyncService.php';

// Handle Direct POST (Create New Reservation).
// Runs before header.php prints HTML so the redirect below actually fires.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'create_reservation') {
        $customerName = trim($_POST['customer_name']);
        $phone = trim($_POST['phone']);
        $guests = (int)$_POST['guests'];
        $date = $_POST['reservation_date'];
        $time = $_POST['reservation_time'];
        $tableId = (int)($_POST['table_id'] ?? 0);
        $email = trim($_POST['email'] ?? '');

        // 1. Create Lead
        $leadId = Lead::create([
            'customer_name' => $customerName,
            'phone' => $phone,
            'email' => $email ?: 'online@thespice.com',
            'guest_count' => $guests,
            'booking_date' => $date,
            'booking_time' => $time,
            'booking_source' => 'Admin Panel'
        ]);

        if ($tableId > 0) {
            SyncService::confirmLead($leadId, $tableId);
        } else {
            // Find first available table for that 2-hour window
            $availTables = Table::getAvailableForTime($date, $time);
            if (!empty($availTables)) {
                SyncService::confirmLead($leadId, $availTables[0]['id']);
            } else {
                Lead::updateStatus($leadId, 'Waiting List');
            }
        }

        header('Location: reservations.php?msg=created');
        exit();
    }
}

include 'includes/header.php';

// Fetch all reservations
$reservations = Reservation::getAll();
$available_tables = Table::getAvailable();

// Preload tables once instead of a query per row
$tables_by_id = [];
foreach (Table::getAll() as $tRow) {
    $tables_by_id[$tRow['id']] = $tRow;
}
?>

<!-- Filter Chips Header -->
<div class="glass-card" style="padding: 16px 24px; margin-bottom: 4px;">
    <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 14px;">
        <div class="filter-chips" style="margin: 0;">
            <button class="chip-btn active" onclick="filterReservations('all', event)">All (<?php echo count($reservations); ?>)</button>
            <button class="chip-btn" onclick="filterReservations('upcoming', event)">Upcoming</button>
            <button class="chip-btn" onclick="filterReservations('arrived', event)">Arrived</button>
            <button class="chip-btn" onclick="filterReservations('dining', event)">Dining</button>
            <button class="chip-btn" onclick="filterReservations('completed', event)">Completed</button>
            <button class="chip-btn" onclick="filterReservations('cancelled', event)">Cancelled</button>
            <button class="chip-btn" onclick="filterReservations('no-show', event)">No Show</button>
        </div>
        <div>
            <button class="btn btn-primary btn-sm" onclick="openModal('newReservationModal')">
                <i data-lucide="plus"></i> New Reservation
            </button>
        </div>
    </div>
</div>

<!-- Reservations Glass Table -->
<div class="glass-card">
    <div class="card-header">
        <h2 class="card-title"><i data-lucide="calendar"></i> Master Reservation Schedule</h2>
        <span class="badge-pill" style="font-size: 0.8rem; font-weight: 700; color: var(--primary-red); background: var(--light-pink); padding: 4px 12px; border-radius: var(--radius-pill);"><?php echo count($reservations); ?> Bookings</span>
    </div>

    <div class="table-responsive">
        <table id="reservationsTable">
            <thead>
                <tr>
                    <th>RES ID</th>
                    <th>CUSTOMER</th>
                    <th>CONTACT</th>
                    <th>GUESTS</th>
                    <th>DATE & TIME</th>
                    <th>TABLE</th>
                    <th>STATUS</th>
                    <th>ACTIONS</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($reservations) > 0): ?>
                    <?php foreach ($reservations as $res): 
                        $status = htmlspecialchars($res['status']);
                        $badgeClass = strtolower(str_replace(' ', '-', $status));
                        $table = ($res['table_id'] && isset($tables_by_id[$res['table_id']])) ? $tables_by_id[$res['table_id']] : null;
                    ?>
                    <tr class="res-row" data-status="<?php echo $badgeClass; ?>" id="res-row-<?php echo $res['id']; ?>">
                        <td style="font-weight: 800; color: var(--primary-red);">Res #<?php echo htmlspecialchars($res['id']); ?></td>
                        <td style="font-weight: 700; color: var(--text-primary);"><?php echo htmlspecialchars($res['customer_name']); ?></td>
                        <td style="font-weight: 600; font-size: 0.85rem;"><?php echo htmlspecialchars($res['phone']); ?></td>
                        <td>
                            <span style="background: rgba(255,255,255,0.8); padding: 4px 10px; border-radius: var(--radius-pill); font-weight: 700; font-size: 0.75rem; border: 1px solid rgba(0,0,0,0.06);">
                                <?php echo htmlspecialchars($res['guests']); ?> Pax
                            </span>
                        </td>
                        <td>
                            <div style="font-weight: 700; font-size: 0.85rem; color: var(--text-primary);"><?php echo htmlspecialchars($res['reservation_date']); ?></div>
                            <div style="font-size: 0.75rem; color: var(--primary-red); font-weight: 700;"><?php echo date('g:i A', strtotime($res['reservation_time'])); ?></div>
                        </td>
                        <td>
                            <?php if ($table): ?>
                                <span style="font-weight: 800; color: var(--primary-red); font-size: 0.85rem;"><?php echo htmlspecialchars($table['table_number']); ?></span>
                            <?php else: ?>
                                <span style="color: var(--text-muted); font-style: italic; font-size: 0.8rem;">Unassigned</span>
                            <?php endif; ?>
                        </td>
                        <td id="status-cell-<?php echo $res['id']; ?>">
                            <span class="status-badge status-<?php echo $badgeClass; ?>"><?php echo $status; ?></span>
                        </td>
                        <td>
                            <div class="action-btns">
                                <?php if ($status === 'Upcoming' || $status === 'Confirmed'): ?>
                                <button class="action-btn" title="Mark Arrived" onclick="updateStatus(<?php echo $res['id']; ?>, 'Arrived', this)">
                                    <i data-lucide="user-check" style="color: #10B981;"></i>
                                </button>
                                <button class="action-btn" title="Transfer Table" onclick="openChangeTableModal(<?php echo $res['id']; ?>, '<?php echo $res['reservation_date']; ?>', '<?php echo $res['reservation_time']; ?>')">
                                    <i data-lucide="arrow-right-left" style="color: #0284C7;"></i>
                                </button>
                                <button class="action-btn" title="Mark No Show" onclick="updateStatus(<?php echo $res['id']; ?>, 'No Show', this)">
                                    <i data-lucide="user-x" style="color: #64748B;"></i>
                                </button>
                                <button class="action-btn" title="Cancel Reservation" onclick="updateStatus(<?php echo $res['id']; ?>, 'Cancelled', this)">
                                    <i data-lucide="x-circle" style="color: #E53935;"></i>
                                </button>
                                <?php elseif ($status === 'Arrived'): ?>
                                <button class="action-btn" title="Seat Customer" onclick="updateStatus(<?php echo $res['id']; ?>, 'Dining', this)">
                                    <i data-lucide="play" style="color: var(--primary-red);"></i>
                                </button>
                                <button class="action-btn" title="Transfer Table" onclick="openChangeTableModal(<?php echo $res['id']; ?>, '<?php echo $res['reservation_date']; ?>', '<?php echo $res['reservation_time']; ?>')">
                                    <i data-lucide="arrow-right-left" style="color: #0284C7;"></i>
                                </button>
                                <button class="action-btn" title="Cancel Reservation" onclick="updateStatus(<?php echo $res['id']; ?>, 'Cancelled', this)">
                                    <i data-lucide="x-circle" style="color: #E53935;"></i>
                                </button>
                                <?php elseif ($status === 'Dining'): ?>
                                <button class="action-btn" title="Transfer Table" onclick="openChangeTableModal(<?php echo $res['id']; ?>, '<?php echo $res['reservation_date']; ?>', '<?php echo $res['reservation_time']; ?>')">
                                    <i data-lucide="arrow-right-left" style="color: #0284C7;"></i>
                                </button>
                                <?php if (!empty($res['bill_id'])): ?>
                                <a class="action-btn" title="Open Bill & Settle Payment" href="bills.php?focus=<?php echo (int)$res['bill_id']; ?>">
                                    <i data-lucide="receipt" style="color: var(--primary-red);"></i>
                                </a>
                                <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8">
                            <div class="empty-state">
                                <div class="empty-state-icon"><i data-lucide="calendar"></i></div>
                                <div class="empty-state-title">No Reservations</div>
                                <div class="empty-state-desc">There are no reservation records found.</div>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Change Table -->
<div class="modal-overlay" id="changeTableModal">
    <div class="modal-glass-content">
        <div class="modal-header">
            <div class="modal-title">
                <i data-lucide="arrow-right-left"></i> Transfer Customer Table
            </div>
            <button class="modal-close" onclick="closeModal('changeTableModal')">
                <i data-lucide="x"></i>
            </button>
        </div>
        <form id="changeTableForm" onsubmit="submitChangeTable(event)">
            <input type="hidden" id="change_res_id">
            
            <div class="form-group">
                <label class="form-label">Select Available Table (Smart 2-Hour Window Check)</label>
                <select id="change_new_table_id" class="form-control" required style="padding: 12px;">
                    <option value="">Loading available tables...</option>
                </select>
                <div style="font-size: 0.725rem; color: var(--text-muted); margin-top: 4px;">
                    <i data-lucide="clock" style="width: 12px; height: 12px; vertical-align: middle;"></i> Evaluates 2-hour buffer. Tables freed early are automatically available!
                </div>
            </div>

            <div style="margin-top: 24px; display: flex; gap: 12px; justify-content: flex-end;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('changeTableModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Transfer Table</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: New Reservation -->
<div class="modal-overlay" id="newReservationModal">
    <div class="modal-glass-content">
        <div class="modal-header">
            <div class="modal-title">
                <i data-lucide="calendar-plus"></i> New Reservation
            </div>
            <button class="modal-close" onclick="closeModal('newReservationModal')">
                <i data-lucide="x"></i>
            </button>
        </div>
        <form action="reservations.php" method="POST">
            <input type="hidden" name="action" value="create_reservation">
            
            <div class="form-group">
                <label class="form-label">Customer Name</label>
                <input type="text" name="customer_name" required placeholder="John Doe">
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                <div class="form-group">
                    <label class="form-label">Phone Number</label>
                    <input type="text" name="phone" required placeholder="+1 555-0192">
                </div>
                <div class="form-group">
                    <label class="form-label">Email Address</label>
                    <input type="email" name="email" placeholder="john@example.com" class="form-control">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px;">
                <div class="form-group">
                    <label class="form-label">Date</label>
                    <input type="date" name="reservation_date" min="<?php echo date('Y-m-d'); ?>" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Time</label>
                    <select name="reservation_time" class="form-control" required>
                        <option value="12:00">12:00 PM</option>
                        <option value="12:30">12:30 PM</option>
                        <option value="13:00">1:00 PM</option>
                        <option value="13:30">1:30 PM</option>
                        <option value="14:00">2:00 PM</option>
                        <option value="14:30">2:30 PM</option>
                        <option value="15:00">3:00 PM</option>
                        <option value="15:30">3:30 PM</option>
                        <option value="16:00">4:00 PM</option>
                        <option value="16:30">4:30 PM</option>
                        <option value="17:00">5:00 PM</option>
                        <option value="17:30">5:30 PM</option>
                        <option value="18:00">6:00 PM</option>
                        <option value="18:30">6:30 PM</option>
                        <option value="19:00" selected>7:00 PM</option>
                        <option value="19:30">7:30 PM</option>
                        <option value="20:00">8:00 PM</option>
                        <option value="20:30">8:30 PM</option>
                        <option value="21:00">9:00 PM</option>
                        <option value="21:30">9:30 PM</option>
                        <option value="22:00">10:00 PM</option>
                        <option value="22:30">10:30 PM</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Guests</label>
                    <input type="number" name="guests" min="1" value="2" required>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Assign Table (Optional)</label>
                <select name="table_id" class="form-control">
                    <option value="0">-- Auto-Assign Available Table --</option>
                    <?php foreach ($available_tables as $t): ?>
                        <option value="<?php echo $t['id']; ?>">Table <?php echo htmlspecialchars($t['table_number']); ?> (<?php echo $t['capacity']; ?> Seats)</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="margin-top: 24px; display: flex; gap: 12px; justify-content: flex-end;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('newReservationModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Create Reservation</button>
            </div>
        </form>
    </div>
</div>

<script>
<?php if (isset($_GET['action']) && $_GET['action'] === 'new'): ?>
document.addEventListener('DOMContentLoaded', () => {
    openModal('newReservationModal');
});
<?php endif; ?>

async function openChangeTableModal(resId, date, time) {
    document.getElementById('change_res_id').value = resId;
    const select = document.getElementById('change_new_table_id');
    select.innerHTML = '<option value="">Loading available tables...</option>';
    openModal('changeTableModal');

    try {
        const res = await fetch(`api/router.php?action=tables.available_for_time&date=${date}&time=${time}`).then(r => r.json());
        if (res.success && res.data.length > 0) {
            select.innerHTML = '<option value="">-- Select New Available Table --</option>';
            res.data.forEach(t => {
                select.innerHTML += `<option value="${t.id}">Table ${t.table_number} (${t.capacity} Seats)</option>`;
            });
        } else {
            select.innerHTML = '<option value="" disabled>No available tables in this time slot</option>';
        }
    } catch (err) {
        select.innerHTML = '<option value="">-- Select New Table --</option>';
    }
}

async function submitChangeTable(e) {
    e.preventDefault();
    const resId = document.getElementById('change_res_id').value;
    const newTableId = document.getElementById('change_new_table_id').value;

    if (!newTableId) {
        showToast("Please select a new table", "error");
        return;
    }

    const res = await apiRequest('reservations.change_table', { reservation_id: resId, new_table_id: newTableId });
    if(res.success) {
        closeModal('changeTableModal');
        smoothReload();
    }
}

const RES_ACTIONS = {
    'Cancelled': 'reservations.cancel',
    'Arrived': 'reservations.arrive',
    'Dining': 'reservations.seat',
    'No Show': 'reservations.noshow'
};

async function updateStatus(resId, newStatus, btn) {
    const action = RES_ACTIONS[newStatus];
    if(!action) return;
    if(!confirm(`Mark this reservation as ${newStatus}?`)) return;

    return withBusy(btn, async () => {
        const res = await apiRequest(action, { reservation_id: resId });
        if (res.success) smoothReload();
    });
}

function filterReservations(statusFilter, evt) {
    document.querySelectorAll('.chip-btn').forEach(btn => btn.classList.remove('active'));
    const trigger = (evt || window.event)?.currentTarget || (evt || window.event)?.target;
    if (trigger) trigger.classList.add('active');

    const rows = document.querySelectorAll('.res-row');
    rows.forEach(row => {
        row.style.display = (statusFilter === 'all' || row.dataset.status === statusFilter) ? '' : 'none';
    });
}
</script>

<?php include 'includes/footer.php'; ?>
