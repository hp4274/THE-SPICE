<?php
// admin/tables.php
require_once 'includes/db.php';
require_once 'models/Table.php';

include 'includes/header.php';

// Handle Add Table Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_table') {
    $table_number = trim($_POST['table_number'] ?? '');
    $capacity = intval($_POST['capacity'] ?? 4);

    if (!empty($table_number) && $capacity > 0) {
        try {
            $stmt = $pdo->prepare("INSERT INTO tables (table_number, capacity, status) VALUES (?, ?, 'Available')");
            $stmt->execute([$table_number, $capacity]);
            $success_msg = "Table {$table_number} added successfully!";
        } catch (Exception $e) {
            $error_msg = "Error adding table: " . $e->getMessage();
        }
    }
}

$tables = Table::getAll();
$available_tables = Table::getAvailable();
?>

<?php if (isset($success_msg)): ?>
    <div style="background: rgba(16,185,129,0.12); color: #10B981; padding: 14px 20px; border-radius: var(--radius-md); font-weight: 700; border: 1px solid rgba(16,185,129,0.3); margin-bottom: 12px; display: flex; align-items: center; gap: 10px;">
        <i data-lucide="check-circle"></i> <?php echo $success_msg; ?>
    </div>
<?php endif; ?>

<!-- Floor Plan Header Actions -->
<div class="glass-card" style="padding: 16px 24px; margin-bottom: 4px;">
    <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 14px;">
        <div style="display: flex; align-items: center; gap: 12px;">
            <h2 style="font-size: 1.1rem; font-weight: 800; color: var(--text-primary); display: flex; align-items: center; gap: 8px;">
                <i data-lucide="grid-2x2" style="color: var(--primary-red);"></i> Active Floor Plan
            </h2>
            <span style="font-size: 0.8rem; font-weight: 700; color: var(--text-muted); background: rgba(0,0,0,0.04); padding: 4px 12px; border-radius: var(--radius-pill);">
                <?php echo count($tables); ?> Total Tables
            </span>
        </div>
        <div style="display: flex; gap: 12px;">
            <button class="btn btn-secondary btn-sm" onclick="openWalkinModal()">
                <i data-lucide="user-check" style="width: 14px;"></i> Seat Walk-in Customer
            </button>
            <button class="btn btn-primary btn-sm" onclick="openModal('addTableModal')">
                <i data-lucide="plus" style="width: 14px;"></i> Add Table
            </button>
        </div>
    </div>
</div>

<!-- Active Tables Cards Grid -->
<div class="table-grid">
    <?php foreach ($tables as $t): 
        // Determine status colors
        $statusColor = '#10B981'; // Available - Emerald Green
        $statusBg = 'rgba(16, 185, 129, 0.1)';
        $statusShadow = 'rgba(16, 185, 129, 0.25)';
        
        if ($t['status'] === 'Occupied' || $t['status'] === 'Dining') {
            $statusColor = '#F97316'; // Occupied - Orange
            $statusBg = 'rgba(249, 115, 22, 0.1)';
            $statusShadow = 'rgba(249, 115, 22, 0.3)';
        } elseif ($t['status'] === 'Reserved') {
            $statusColor = '#8B5CF6'; // Reserved - Purple
            $statusBg = 'rgba(139, 92, 246, 0.1)';
            $statusShadow = 'rgba(139, 92, 246, 0.3)';
        } elseif ($t['status'] === 'Needs Cleaning' || $t['status'] === 'Cleaning') {
            $statusColor = '#E53935'; // Cleaning - Red
            $statusBg = 'rgba(229, 57, 53, 0.1)';
            $statusShadow = 'rgba(229, 57, 53, 0.3)';
        } elseif ($t['status'] === 'Blocked') {
            $statusColor = '#64748B'; // Blocked - Slate
            $statusBg = 'rgba(100, 116, 139, 0.1)';
            $statusShadow = 'rgba(100, 116, 139, 0.3)';
        }
    ?>
    <div class="table-card" style="--status-color: <?php echo $statusColor; ?>; --status-bg: <?php echo $statusBg; ?>; --status-shadow: <?php echo $statusShadow; ?>;" id="table-card-<?php echo $t['id']; ?>">
        <div class="table-card-top-bar"></div>

        <!-- Top Row Action Icons -->
        <div style="position: absolute; top: 14px; right: 14px; display: flex; gap: 6px;">
            <?php if ($t['status'] === 'Blocked'): ?>
                <button class="action-btn" title="Unblock Table" onclick="unblockTable(<?php echo $t['id']; ?>)">
                    <i data-lucide="unlock" style="width: 14px; color: #10B981;"></i>
                </button>
            <?php else: ?>
                <button class="action-btn" title="Block Table" onclick="blockTable(<?php echo $t['id']; ?>)">
                    <i data-lucide="lock" style="width: 14px; color: #64748B;"></i>
                </button>
            <?php endif; ?>
            <button class="action-btn" title="Edit Table" onclick="editTable(<?php echo $t['id']; ?>, <?php echo $t['capacity']; ?>)">
                <i data-lucide="edit-2" style="width: 14px; color: var(--text-secondary);"></i>
            </button>
            <button class="action-btn" title="Delete Table" onclick="deleteTable(<?php echo $t['id']; ?>)">
                <i data-lucide="trash-2" style="width: 14px; color: #E53935;"></i>
            </button>
        </div>

        <!-- Circular Graphic Representation -->
        <div class="table-graphic">
            <div class="table-graphic-inner"><?php echo htmlspecialchars($t['table_number']); ?></div>
            <div class="table-graphic-label">TABLE</div>
        </div>

        <!-- Capacity Pill -->
        <div class="table-capacity">
            <i data-lucide="users" style="width: 14px; color: var(--primary-red);"></i>
            <span><?php echo htmlspecialchars($t['capacity']); ?> Seats</span>
        </div>

        <!-- Status Pill -->
        <div class="table-status-pill">
            <?php echo htmlspecialchars($t['status']); ?>
        </div>

        <!-- Quick Status Action Buttons -->
        <div style="margin-top: 16px; width: 100%; display: flex; gap: 8px; justify-content: center;">
            <?php if($t['status'] === 'Needs Cleaning' || $t['status'] === 'Cleaning'): ?>
                <button class="btn btn-secondary btn-sm" style="width: 100%;" onclick="cleanTable(<?php echo $t['id']; ?>)">
                    <i data-lucide="check" style="width: 14px;"></i> Mark Cleaned
                </button>
            <?php elseif($t['status'] === 'Occupied' || $t['status'] === 'Dining'): ?>
                <button class="btn btn-danger btn-sm" style="width: 100%;" onclick="freeTable(<?php echo $t['id']; ?>)">
                    <i data-lucide="log-out" style="width: 14px;"></i> Free Table
                </button>
            <?php elseif($t['status'] === 'Available'): ?>
                <button class="btn btn-secondary btn-sm" style="width: 100%;" onclick="seatWalkinForTable(<?php echo $t['id']; ?>, '<?php echo htmlspecialchars($t['table_number']); ?>')">
                    <i data-lucide="user-check" style="width: 14px;"></i> Seat Walk-in
                </button>
            <?php else: ?>
                <button class="btn btn-secondary btn-sm" style="width: 100%; opacity: 0.8;" disabled>
                    <?php echo htmlspecialchars($t['status']); ?>
                </button>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Modal: Seat Walk-in Customer -->
<div class="modal-overlay" id="walkinModal">
    <div class="modal-glass-content">
        <div class="modal-header">
            <div class="modal-title">
                <i data-lucide="user-check"></i> Seat Walk-in Customer
            </div>
            <button class="modal-close" onclick="closeModal('walkinModal')">
                <i data-lucide="x"></i>
            </button>
        </div>
        <form id="walkinForm" onsubmit="submitWalkin(event)">
            <div class="form-group">
                <label class="form-label">Select Available Table</label>
                <select id="walkin_table_id" class="form-control" required style="padding: 12px;">
                    <option value="">-- Select Table --</option>
                    <?php foreach ($available_tables as $t): ?>
                        <option value="<?php echo $t['id']; ?>">Table <?php echo htmlspecialchars($t['table_number']); ?> (<?php echo $t['capacity']; ?> Seats)</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Customer Name</label>
                <input type="text" id="walkin_name" class="form-control" required placeholder="e.g. Alex Smith">
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                <div class="form-group">
                    <label class="form-label">Phone Number</label>
                    <input type="text" id="walkin_phone" class="form-control" placeholder="+1 555-0182">
                </div>
                <div class="form-group">
                    <label class="form-label">Guest Count</label>
                    <input type="number" id="walkin_guests" min="1" max="20" value="2" class="form-control" required>
                </div>
            </div>

            <div style="margin-top: 24px; display: flex; gap: 12px; justify-content: flex-end;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('walkinModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Seat & Create Bill</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Add New Table -->
<div class="modal-overlay" id="addTableModal">
    <div class="modal-glass-content">
        <div class="modal-header">
            <div class="modal-title">
                <i data-lucide="grid-2x2"></i> Add New Table
            </div>
            <button class="modal-close" onclick="closeModal('addTableModal')">
                <i data-lucide="x"></i>
            </button>
        </div>
        <form action="tables.php" method="POST">
            <input type="hidden" name="action" value="add_table">
            
            <div class="form-group">
                <label class="form-label">Table Number / Label</label>
                <input type="text" name="table_number" required placeholder="e.g. T-12 or VIP-1">
            </div>

            <div class="form-group">
                <label class="form-label">Seating Capacity</label>
                <input type="number" name="capacity" min="1" max="20" value="4" required>
            </div>

            <div style="margin-top: 24px; display: flex; gap: 12px; justify-content: flex-end;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addTableModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Add Table</button>
            </div>
        </form>
    </div>
</div>

<script>
function openWalkinModal() {
    openModal('walkinModal');
}

function seatWalkinForTable(tableId, tableNumber) {
    const select = document.getElementById('walkin_table_id');
    if (select) select.value = tableId;
    openModal('walkinModal');
}

async function submitWalkin(e) {
    e.preventDefault();
    const tableId = document.getElementById('walkin_table_id').value;
    const name = document.getElementById('walkin_name').value;
    const phone = document.getElementById('walkin_phone').value;
    const guests = parseInt(document.getElementById('walkin_guests').value);

    const res = await apiRequest('tables.walkin', { table_id: tableId, customer_name: name, phone: phone, guests: guests });
    if (res.success) {
        closeModal('walkinModal');
        setTimeout(() => location.reload(), 800);
    }
}

async function cleanTable(tableId) {
    const res = await apiRequest('tables.clean', { table_id: tableId });
    if(res.success) {
        setTimeout(() => location.reload(), 800);
    }
}

async function blockTable(tableId) {
    if(!confirm("Block this table from receiving reservations?")) return;
    const res = await apiRequest('tables.block', { table_id: tableId });
    if(res.success) {
        setTimeout(() => location.reload(), 800);
    }
}

async function unblockTable(tableId) {
    const res = await apiRequest('tables.unblock', { table_id: tableId });
    if(res.success) {
        setTimeout(() => location.reload(), 800);
    }
}

async function editTable(tableId, currentCapacity) {
    const newCap = prompt("Enter new seating capacity for this table:", currentCapacity);
    if(newCap === null || newCap.trim() === '') return;
    
    const cap = parseInt(newCap);
    if(isNaN(cap) || cap < 1) {
        showToast("Invalid capacity number", "error");
        return;
    }
    
    const res = await apiRequest('tables.edit', { table_id: tableId, capacity: cap });
    if(res.success) {
        setTimeout(() => location.reload(), 800);
    }
}

async function deleteTable(tableId) {
    if(!confirm("Are you sure you want to delete this table?")) return;
    
    const res = await apiRequest('tables.delete', { table_id: tableId });
    if(res.success) {
        setTimeout(() => location.reload(), 800);
    }
}

async function freeTable(tableId) {
    if(!confirm("Mark this table as free and available?")) return;
    
    const res = await apiRequest('tables.free', { table_id: tableId });
    if(res.success) {
        setTimeout(() => location.reload(), 800);
    }
}
</script>

<?php include 'includes/footer.php'; ?>
