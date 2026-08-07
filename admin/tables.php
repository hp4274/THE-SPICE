<?php
// admin/tables.php
require_once 'includes/db.php';
require_once 'models/Table.php';

// NOTE: form handling must run BEFORE includes/header.php prints any HTML,
// otherwise header('Location: ...') fails with "headers already sent".

// Handle Add Table Form Submission (Post/Redirect/Get so a refresh never re-adds a table)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_table') {
    $table_number = trim($_POST['table_number'] ?? '');
    $capacity = intval($_POST['capacity'] ?? 4);

    if (empty($table_number) || $capacity <= 0) {
        $redirect = 'tables.php?err=' . urlencode('Enter a table label and a capacity of at least 1.');
    } else {
        $dupe = $pdo->prepare("SELECT id FROM tables WHERE table_number = ?");
        $dupe->execute([$table_number]);
        if ($dupe->fetch()) {
            $redirect = 'tables.php?err=' . urlencode("Table \"$table_number\" already exists.");
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO tables (table_number, capacity, status) VALUES (?, ?, 'Available')");
                $stmt->execute([$table_number, $capacity]);
                AuditLog::log("Created Table", "Table", (int)$pdo->lastInsertId(), "Table $table_number ($capacity seats)");
                $redirect = 'tables.php?msg=' . urlencode("Table $table_number added successfully!");
            } catch (Exception $e) {
                $redirect = 'tables.php?err=' . urlencode('Could not add table: ' . $e->getMessage());
            }
        }
    }
    header('Location: ' . $redirect);
    exit();
}

include 'includes/header.php';

$success_msg = $_GET['msg'] ?? null;
$error_msg   = $_GET['err'] ?? null;

$tables = Table::getAll();
$available_tables = Table::getAvailable();

// Lookup of table id => table row, for resolving merge partners
$tables_by_id = [];
foreach ($tables as $t) {
    $tables_by_id[$t['id']] = $t;
}

// Which tables are merged INTO each primary table (primary id => secondary rows)
$merged_children = [];
foreach ($tables as $t) {
    if ($t['status'] === 'Merged' && !empty($t['merged_with'])) {
        $merged_children[$t['merged_with']][] = $t;
    }
}

// Fetch today's schedules/reservations per table
$todayDate = date('Y-m-d');
$schedStmt = $pdo->prepare("
    SELECT r.id, r.table_id, r.reservation_time, r.customer_name, r.status, r.guests, r.bill_id
    FROM reservations r
    WHERE r.reservation_date = ? AND r.table_id IS NOT NULL AND r.status NOT IN ('Cancelled')
    ORDER BY r.reservation_time ASC
");
$schedStmt->execute([$todayDate]);
$today_schedules = [];
foreach ($schedStmt->fetchAll() as $sRow) {
    $tid = $sRow['table_id'];
    if (!isset($today_schedules[$tid])) $today_schedules[$tid] = [];
    $today_schedules[$tid][] = $sRow;
}

// The live occupant of each table (Reserved / Arrived / Dining), so cards can act on it
$occStmt = $pdo->query("
    SELECT r.id, r.table_id, r.customer_name, r.guests, r.status, r.reservation_time, r.bill_id
    FROM reservations r
    WHERE r.table_id IS NOT NULL AND r.status IN ('Upcoming', 'Confirmed', 'Arrived', 'Dining')
    ORDER BY r.reservation_date ASC, r.reservation_time ASC
");
$table_occupant = [];
foreach (($occStmt ? $occStmt->fetchAll() : []) as $oRow) {
    if (!isset($table_occupant[$oRow['table_id']])) {
        $table_occupant[$oRow['table_id']] = $oRow;
    }
}
?>

<?php if ($success_msg): ?>
    <div class="flash-banner" style="background: rgba(16,185,129,0.12); color: #10B981; padding: 14px 20px; border-radius: var(--radius-md); font-weight: 700; border: 1px solid rgba(16,185,129,0.3); margin-bottom: 12px; display: flex; align-items: center; gap: 10px;">
        <i data-lucide="check-circle"></i> <?php echo htmlspecialchars($success_msg); ?>
    </div>
<?php endif; ?>

<?php if ($error_msg): ?>
    <div class="flash-banner" style="background: rgba(229,57,53,0.1); color: #E53935; padding: 14px 20px; border-radius: var(--radius-md); font-weight: 700; border: 1px solid rgba(229,57,53,0.3); margin-bottom: 12px; display: flex; align-items: center; gap: 10px;">
        <i data-lucide="alert-circle"></i> <?php echo htmlspecialchars($error_msg); ?>
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
        <div style="display: flex; gap: 12px; flex-wrap: wrap;">
            <button class="btn btn-secondary btn-sm" onclick="openCleanModal()">
                <i data-lucide="sparkles" style="width: 14px; color: #E53935;"></i> Clean Tables
            </button>
            <button class="btn btn-secondary btn-sm" onclick="openMergeModal()">
                <i data-lucide="layers" style="width: 14px; color: #3B82F6;"></i> Merge Tables
            </button>
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
        } elseif ($t['status'] === 'Merged') {
            $statusColor = '#3B82F6'; // Merged - Blue
            $statusBg = 'rgba(59, 130, 246, 0.1)';
            $statusShadow = 'rgba(59, 130, 246, 0.3)';
        } elseif ($t['status'] === 'Blocked') {
            $statusColor = '#64748B'; // Blocked - Slate
            $statusBg = 'rgba(100, 116, 139, 0.1)';
            $statusShadow = 'rgba(100, 116, 139, 0.3)';
        }
    ?>
    <div class="table-card-flip-container">
        <div class="table-card-inner" id="table-card-inner-<?php echo $t['id']; ?>">
            
            <!-- FRONT SIDE -->
            <div class="table-card-front">
                <div class="table-card" style="--status-color: <?php echo $statusColor; ?>; --status-bg: <?php echo $statusBg; ?>; --status-shadow: <?php echo $statusShadow; ?>;" id="table-card-<?php echo $t['id']; ?>">
                    <div class="table-card-top-bar"></div>

                    <!-- Top Row Action Icons -->
                    <div style="position: absolute; top: 14px; right: 14px; display: flex; gap: 6px; z-index: 5;">
                        <?php if ($t['status'] === 'Blocked'): ?>
                            <button type="button" class="action-btn" title="Unblock Table" onclick="unblockTable(event, <?php echo $t['id']; ?>)">
                                <i data-lucide="unlock" style="width: 14px; color: #10B981;"></i>
                            </button>
                        <?php else: ?>
                            <button type="button" class="action-btn" title="Block Table" onclick="blockTable(event, <?php echo $t['id']; ?>)">
                                <i data-lucide="lock" style="width: 14px; color: #64748B;"></i>
                            </button>
                        <?php endif; ?>
                        <?php if (!in_array($t['status'], ['Merged', 'Blocked', 'Maintenance'])): ?>
                            <button type="button" class="action-btn" title="Merge another table into this one" onclick="event.stopPropagation(); openMergeModal(<?php echo $t['id']; ?>)">
                                <i data-lucide="layers" style="width: 14px; color: #3B82F6;"></i>
                            </button>
                        <?php endif; ?>
                        <button type="button" class="action-btn" title="Edit Table Details" onclick="openEditModal(event, <?php echo $t['id']; ?>, '<?php echo htmlspecialchars($t['table_number']); ?>', <?php echo $t['capacity']; ?>)">
                            <i data-lucide="edit-2" style="width: 14px; color: var(--text-secondary);"></i>
                        </button>
                        <button type="button" class="action-btn" title="Delete Table" onclick="deleteTable(event, <?php echo $t['id']; ?>)">
                            <i data-lucide="trash-2" style="width: 14px; color: #E53935;"></i>
                        </button>
                    </div>

                    <!-- Center-Right Flip Arrow Button (View Time List) -->
                    <button type="button" title="View Today's Time List" onclick="flipTableCard(event, <?php echo $t['id']; ?>)" style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); background: rgba(229,57,53,0.1); color: var(--primary-red); border-radius: 50%; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; border: 1px solid rgba(229,57,53,0.25); cursor: pointer; z-index: 10; transition: all 0.2s;" onmouseover="this.style.background='var(--primary-red)'; this.style.color='#fff';" onmouseout="this.style.background='rgba(229,57,53,0.1)'; this.style.color='var(--primary-red)';">
                        <i data-lucide="chevron-right" style="width: 18px; height: 18px; stroke-width: 2.5;"></i>
                    </button>

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

                    <?php
                        $occ = $table_occupant[$t['id']] ?? null;
                        $children = $merged_children[$t['id']] ?? [];
                        $mergedInto = ($t['status'] === 'Merged' && !empty($t['merged_with']) && isset($tables_by_id[$t['merged_with']]))
                            ? $tables_by_id[$t['merged_with']]
                            : null;
                        $combinedCapacity = (int)$t['capacity'];
                        foreach ($children as $c) { $combinedCapacity += (int)$c['capacity']; }
                    ?>

                    <!-- Live Occupant / Merge Context -->
                    <?php if ($occ): ?>
                        <div class="table-occupant">
                            <i data-lucide="user" style="width: 12px; color: var(--primary-red);"></i>
                            <span><?php echo htmlspecialchars($occ['customer_name']); ?> · <?php echo (int)$occ['guests']; ?> Pax · <?php echo date('g:i A', strtotime($occ['reservation_time'])); ?></span>
                        </div>
                    <?php elseif (!empty($children)): ?>
                        <div class="table-occupant" style="color: #3B82F6;">
                            <i data-lucide="layers" style="width: 12px; color: #3B82F6;"></i>
                            <span>Merged with
                                <?php echo htmlspecialchars(implode(', ', array_map(fn($c) => $c['table_number'], $children))); ?>
                                · seats <?php echo $combinedCapacity; ?>
                            </span>
                        </div>
                    <?php elseif ($mergedInto): ?>
                        <div class="table-occupant" style="color: #3B82F6;">
                            <i data-lucide="link" style="width: 12px; color: #3B82F6;"></i>
                            <span>Merged into Table <?php echo htmlspecialchars($mergedInto['table_number']); ?></span>
                        </div>
                    <?php endif; ?>

                    <!-- Quick Status Action Buttons -->
                    <div style="margin-top: 16px; width: 100%; display: flex; gap: 8px; justify-content: center; flex-wrap: wrap;">
                        <?php if($t['status'] === 'Needs Cleaning' || $t['status'] === 'Cleaning'): ?>
                            <button class="btn btn-secondary btn-sm" style="width: 100%;" onclick="cleanTable(<?php echo $t['id']; ?>, this)">
                                <i data-lucide="check" style="width: 14px;"></i> Mark Cleaned
                            </button>
                        <?php elseif($t['status'] === 'Occupied' || $t['status'] === 'Dining'): ?>
                            <?php if ($occ && !empty($occ['bill_id'])): ?>
                                <a class="btn btn-primary btn-sm" style="width: 100%;" href="bills.php?focus=<?php echo (int)$occ['bill_id']; ?>">
                                    <i data-lucide="receipt" style="width: 14px;"></i> Bill & Pay
                                </a>
                            <?php endif; ?>
                            <button class="btn btn-danger btn-sm" style="width: 100%;" onclick="freeTable(<?php echo $t['id']; ?>, this)">
                                <i data-lucide="log-out" style="width: 14px;"></i> Free Table
                            </button>
                        <?php elseif($t['status'] === 'Reserved'): ?>
                            <?php if ($occ): ?>
                                <button class="btn btn-primary btn-sm" style="width: 100%;" onclick="seatReservation(<?php echo (int)$occ['id']; ?>, '<?php echo htmlspecialchars($occ['status'], ENT_QUOTES); ?>', this)">
                                    <i data-lucide="play" style="width: 14px;"></i>
                                    <?php echo $occ['status'] === 'Arrived' ? 'Seat Guest' : 'Mark Arrived'; ?>
                                </button>
                                <button class="btn btn-secondary btn-sm" style="width: 100%;" onclick="noShowReservation(<?php echo (int)$occ['id']; ?>, this)">
                                    <i data-lucide="user-x" style="width: 14px;"></i> No Show
                                </button>
                            <?php else: ?>
                                <a class="btn btn-secondary btn-sm" style="width: 100%;" href="reservations.php">
                                    <i data-lucide="calendar-check" style="width: 14px;"></i> View Reservations
                                </a>
                            <?php endif; ?>
                        <?php elseif($t['status'] === 'Available'): ?>
                            <button class="btn btn-secondary btn-sm" style="width: 100%;" onclick="seatWalkinForTable(<?php echo $t['id']; ?>, '<?php echo htmlspecialchars($t['table_number'], ENT_QUOTES); ?>')">
                                <i data-lucide="user-check" style="width: 14px;"></i> Seat Walk-in
                            </button>
                        <?php elseif($t['status'] === 'Merged'): ?>
                            <button class="btn btn-secondary btn-sm" style="width: 100%; color: #3B82F6; border-color: #3B82F6;" onclick="unmergeTable(<?php echo $t['id']; ?>, this)">
                                <i data-lucide="unlink" style="width: 14px;"></i> Unmerge Table
                            </button>
                        <?php elseif($t['status'] === 'Blocked'): ?>
                            <button class="btn btn-secondary btn-sm" style="width: 100%;" onclick="unblockTable(event, <?php echo $t['id']; ?>)">
                                <i data-lucide="unlock" style="width: 14px;"></i> Unblock Table
                            </button>
                        <?php else: ?>
                            <button class="btn btn-secondary btn-sm" style="width: 100%; opacity: 0.8;" disabled>
                                <?php echo htmlspecialchars($t['status']); ?>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- BACK SIDE: SCHEDULE & TIME LIST -->
            <div class="table-card-back">
                <div>
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; border-bottom: 1px solid rgba(0,0,0,0.06); padding-bottom: 8px;">
                        <div style="display: flex; align-items: center; gap: 6px; font-weight: 800; font-size: 0.95rem; color: var(--text-primary);">
                            <i data-lucide="calendar-clock" style="width: 16px; color: var(--primary-red);"></i> Table <?php echo htmlspecialchars($t['table_number']); ?> Time List
                        </div>
                        <button type="button" class="action-btn" title="Flip Back" onclick="flipTableCard(event, <?php echo $t['id']; ?>)">
                            <i data-lucide="chevron-left" style="width: 16px; height: 16px; stroke-width: 2.5; color: var(--text-secondary);"></i>
                        </button>
                    </div>

                    <div style="font-size: 0.72rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 10px;">
                        Today's Reservations & Availability
                    </div>

                    <div class="table-schedule-list" style="display: flex; flex-direction: column; gap: 8px; max-height: 170px; overflow-y: auto; padding-right: 4px;">
                        <?php 
                        $schedules = $today_schedules[$t['id']] ?? [];
                        if (!empty($schedules)):
                            foreach ($schedules as $sItem):
                                $formattedTime = date('h:i A', strtotime($sItem['reservation_time']));
                                $sStatus = $sItem['status'];
                                $badgeBg = '#8B5CF6'; // Reserved
                                if ($sStatus === 'Arrived' || $sStatus === 'Dining') $badgeBg = '#F97316';
                                elseif ($sStatus === 'Completed') $badgeBg = '#10B981';
                                elseif ($sStatus === 'No Show') $badgeBg = '#E53935';
                        ?>
                            <div style="display: flex; align-items: center; justify-content: space-between; background: rgba(0,0,0,0.03); padding: 8px 12px; border-radius: 8px; border-left: 3px solid <?php echo $badgeBg; ?>;">
                                <div>
                                    <div style="font-size: 0.82rem; font-weight: 800; color: var(--text-primary); display: flex; align-items: center; gap: 6px;">
                                        <i data-lucide="clock" style="width: 12px; color: var(--text-muted);"></i> <?php echo $formattedTime; ?>
                                    </div>
                                    <div style="font-size: 0.75rem; color: var(--text-secondary); margin-top: 2px;">
                                        <?php echo htmlspecialchars($sItem['customer_name']); ?> (<?php echo $sItem['guests']; ?> Guests)
                                    </div>
                                </div>
                                <span style="font-size: 0.68rem; font-weight: 800; padding: 2px 8px; border-radius: 10px; background: <?php echo $badgeBg; ?>; color: #fff;">
                                    <?php echo htmlspecialchars($sStatus); ?>
                                </span>
                            </div>
                        <?php 
                            endforeach;
                        else: 
                        ?>
                            <div style="text-align: center; padding: 18px 8px; color: var(--text-muted); font-size: 0.8rem; font-weight: 600; background: rgba(16,185,129,0.06); border-radius: 8px; border: 1px dashed rgba(16,185,129,0.3);">
                                <i data-lucide="check-circle-2" style="width: 20px; color: #10B981; margin: 0 auto 6px auto; display: block;"></i>
                                No Bookings Recorded Today<br><span style="font-size: 0.72rem; color: #10B981; font-weight: 700;">Table Available All Day</span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <button type="button" class="btn btn-secondary btn-sm" style="width: 100%; margin-top: 10px;" onclick="flipTableCard(event, <?php echo $t['id']; ?>)">
                    <i data-lucide="arrow-left" style="width: 14px;"></i> Back to Front
                </button>
            </div>

        </div>
    </div>
    <?php endforeach; ?>
</div>
<!-- Active Tables Cards Grid Ends -->

<!-- Modal: Edit Table -->
<div class="modal-overlay" id="editTableModal">
    <div class="modal-glass-content">
        <div class="modal-header">
            <div class="modal-title">
                <i data-lucide="edit-3" style="color: var(--primary-red);"></i> Edit Table Details
            </div>
            <button class="modal-close" onclick="closeModal('editTableModal')">
                <i data-lucide="x"></i>
            </button>
        </div>
        <form id="editTableForm" onsubmit="submitEditTable(event)">
            <input type="hidden" id="edit_table_id">
            
            <div class="form-group">
                <label class="form-label">Table Number / Label</label>
                <input type="text" id="edit_table_number" class="form-control" required placeholder="e.g. T1" style="padding: 12px;">
            </div>

            <div class="form-group">
                <label class="form-label">Seating Capacity</label>
                <input type="number" id="edit_capacity" min="1" max="50" class="form-control" required placeholder="e.g. 4" style="padding: 12px;">
            </div>

            <div style="margin-top: 24px; display: flex; gap: 12px; justify-content: flex-end;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editTableModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
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

            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px;">
                <div class="form-group">
                    <label class="form-label">Phone Number</label>
                    <input type="text" id="walkin_phone" class="form-control" placeholder="+1 555-0182">
                </div>
                <div class="form-group">
                    <label class="form-label">Adults ($19.99)</label>
                    <input type="number" id="walkin_adults" min="0" max="20" value="2" class="form-control" required oninput="updateWalkinEstimate()">
                </div>
                <div class="form-group">
                    <label class="form-label">Children ($9.99)</label>
                    <input type="number" id="walkin_children" min="0" max="20" value="0" class="form-control" required oninput="updateWalkinEstimate()">
                </div>
            </div>

            <!-- Live bill preview so staff can quote the guest before seating -->
            <div style="background: var(--light-pink); border-radius: var(--radius-md); padding: 12px 16px; display: flex; align-items: center; justify-content: space-between;">
                <div style="font-size: 0.78rem; font-weight: 700; color: var(--text-secondary); text-transform: uppercase;">Estimated Bill (incl. 8.5% tax)</div>
                <div style="font-size: 1.25rem; font-weight: 800; color: var(--primary-red);" id="walkin_estimate">$43.37</div>
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

<!-- Modal: Clean Tables -->
<div class="modal-overlay" id="cleanTableModal">
    <div class="modal-glass-content" style="max-width: 520px;">
        <div class="modal-header">
            <div class="modal-title" style="color: #E53935;">
                <i data-lucide="sparkles"></i> Clean Tables
            </div>
            <button class="modal-close" onclick="closeModal('cleanTableModal')">
                <i data-lucide="x"></i>
            </button>
        </div>
        <div style="margin-bottom: 16px;">
            <p style="font-size: 0.875rem; color: var(--text-muted); margin: 0 0 16px 0;">
                Select a table below to mark it clean & available for new guests:
            </p>
            <?php 
                $tables_to_clean = array_values(array_filter($tables, fn($t) => in_array($t['status'], ['Cleaning', 'Needs Cleaning'])));
            ?>
            <?php if (count($tables_to_clean) > 0): ?>
                <div style="display: flex; flex-direction: column; gap: 10px; max-height: 280px; overflow-y: auto;">
                    <?php foreach ($tables_to_clean as $tc): ?>
                        <div style="display: flex; align-items: center; justify-content: space-between; padding: 12px 16px; background: rgba(229,57,53,0.06); border: 1px solid rgba(229,57,53,0.2); border-radius: var(--radius-md);">
                            <div>
                                <div style="font-weight: 800; color: var(--text-primary);">Table <?php echo htmlspecialchars($tc['table_number']); ?></div>
                                <div style="font-size: 0.75rem; color: var(--text-muted);"><?php echo $tc['capacity']; ?> Seats • Waiting for cleaning</div>
                            </div>
                            <button class="btn btn-secondary btn-sm" style="color: #10B981; border-color: #10B981;" onclick="cleanTable(<?php echo $tc['id']; ?>, this)">
                                <i data-lucide="check" style="width: 14px;"></i> Mark Clean
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div style="margin-top: 20px; display: flex; justify-content: flex-end; gap: 10px;">
                    <button type="button" class="btn btn-primary" onclick="cleanAllTables(this)">
                        <i data-lucide="sparkles" style="width: 14px;"></i> Clean All Tables
                    </button>
                </div>
            <?php else: ?>
                <div class="empty-state" style="padding: 24px 0;">
                    <div class="empty-state-icon" style="color: #10B981;"><i data-lucide="check-circle"></i></div>
                    <div class="empty-state-title">All Tables Are Clean!</div>
                    <div class="empty-state-desc">No tables currently require cleaning. All available tables are ready for guests.</div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal: Merge Tables -->
<div class="modal-overlay" id="mergeTableModal">
    <div class="modal-glass-content" style="max-width: 500px;">
        <div class="modal-header">
            <div class="modal-title" style="color: #3B82F6;">
                <i data-lucide="layers"></i> Temporary Merge Tables
            </div>
            <button class="modal-close" onclick="closeModal('mergeTableModal')">
                <i data-lucide="x"></i>
            </button>
        </div>
        <form id="mergeTableForm" onsubmit="submitMergeTables(event)">
            <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 16px;">
                Temporarily merge a secondary table into a primary table for large parties. The secondary table will be linked until cleaned.
            </p>
            <div class="form-group">
                <label class="form-label">Primary Table (Main Seating Table)</label>
                <!-- Options are filled from a live tables.list fetch every time the modal opens -->
                <select id="merge_primary_id" class="form-control" required style="padding: 12px;" onchange="renderMergeSecondary()">
                    <option value="">Loading tables...</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Secondary Table (Free Table to Merge In)</label>
                <select id="merge_secondary_id" class="form-control" required style="padding: 12px;" onchange="renderMergeSummary()">
                    <option value="">-- Select Primary Table First --</option>
                </select>
                <div style="font-size: 0.725rem; color: var(--text-muted); margin-top: 4px;">
                    <i data-lucide="info" style="width: 12px; height: 12px; vertical-align: middle;"></i>
                    Only free tables are listed. Booked, occupied, cleaning, blocked and already-merged tables are excluded.
                </div>
            </div>

            <div id="merge_summary" style="display: none; background: rgba(59,130,246,0.08); border: 1px solid rgba(59,130,246,0.25); border-radius: var(--radius-md); padding: 12px 16px; margin-bottom: 4px; font-size: 0.82rem; font-weight: 700; color: #3B82F6;"></div>

            <div style="margin-top: 24px; display: flex; gap: 12px; justify-content: flex-end;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('mergeTableModal')">Cancel</button>
                <button type="submit" class="btn btn-primary" style="background: #3B82F6; border-color: #3B82F6;">
                    <i data-lucide="layers" style="width: 14px;"></i> Merge Tables
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openWalkinModal() {
    openModal('walkinModal');
}

function openCleanModal() {
    openModal('cleanTableModal');
}

// Merge modal state. The table lists are rebuilt from a live fetch on every open,
// so a table booked or freed a second ago can never be offered as a merge target.
let mergeTablesCache = [];
let mergePreferredPrimaryId = null;

const MERGE_PRIMARY_EXCLUDED = ['Merged', 'Blocked', 'Maintenance'];

async function openMergeModal(preferredPrimaryId = null) {
    mergePreferredPrimaryId = preferredPrimaryId ? String(preferredPrimaryId) : null;

    const primarySel = document.getElementById('merge_primary_id');
    const secondarySel = document.getElementById('merge_secondary_id');
    primarySel.innerHTML = '<option value="">Loading tables...</option>';
    secondarySel.innerHTML = '<option value="">-- Select Primary Table First --</option>';
    document.getElementById('merge_summary').style.display = 'none';

    openModal('mergeTableModal');

    try {
        const res = await fetch('api/router.php?action=tables.list', { cache: 'no-store' }).then(r => r.json());
        mergeTablesCache = (res && res.success && Array.isArray(res.data)) ? res.data : [];
    } catch (err) {
        mergeTablesCache = [];
    }
    renderMergePrimary();
}

function mergeTableLabel(t, withStatus) {
    return 'Table ' + t.table_number + ' (' + t.capacity + ' Seats' + (withStatus ? ' - ' + t.status : '') + ')';
}

function renderMergePrimary() {
    const sel = document.getElementById('merge_primary_id');
    const options = mergeTablesCache.filter(t => !MERGE_PRIMARY_EXCLUDED.includes(t.status));

    if (options.length === 0) {
        sel.innerHTML = '<option value="">No table can host a merge right now</option>';
        renderMergeSecondary();
        return;
    }

    sel.innerHTML = '<option value="">-- Select Primary Table --</option>';
    options.forEach(t => {
        const opt = document.createElement('option');
        opt.value = t.id;
        opt.textContent = mergeTableLabel(t, true);
        sel.appendChild(opt);
    });

    // Default to the table the admin acted on, when it is still a valid host
    if (mergePreferredPrimaryId) {
        const preferred = options.find(t => String(t.id) === mergePreferredPrimaryId);
        if (preferred) {
            sel.value = mergePreferredPrimaryId;
        } else {
            const raw = mergeTablesCache.find(t => String(t.id) === mergePreferredPrimaryId);
            showToast(raw ? 'Table ' + raw.table_number + ' is ' + raw.status + ' and cannot host a merge' : 'That table is no longer available', 'error');
        }
    }

    renderMergeSecondary();
}

function renderMergeSecondary() {
    const primaryId = document.getElementById('merge_primary_id').value;
    const sel = document.getElementById('merge_secondary_id');

    if (!primaryId) {
        sel.innerHTML = '<option value="">-- Select Primary Table First --</option>';
        renderMergeSummary();
        return;
    }

    // Free tables only, and never the primary itself
    const options = mergeTablesCache.filter(t => t.status === 'Available' && String(t.id) !== String(primaryId));

    if (options.length === 0) {
        sel.innerHTML = '<option value="">No free table available to merge in</option>';
        renderMergeSummary();
        return;
    }

    sel.innerHTML = '<option value="">-- Select Secondary Table --</option>';
    options.forEach(t => {
        const opt = document.createElement('option');
        opt.value = t.id;
        opt.textContent = mergeTableLabel(t, false);
        sel.appendChild(opt);
    });

    renderMergeSummary();
}

function renderMergeSummary() {
    const box = document.getElementById('merge_summary');
    const primary = mergeTablesCache.find(t => String(t.id) === document.getElementById('merge_primary_id').value);
    const secondary = mergeTablesCache.find(t => String(t.id) === document.getElementById('merge_secondary_id').value);

    if (!primary || !secondary) {
        box.style.display = 'none';
        return;
    }

    const combined = parseInt(primary.capacity) + parseInt(secondary.capacity);
    box.innerHTML = 'Table ' + secondary.table_number + ' merges into Table ' + primary.table_number
        + ' &nbsp;&rarr;&nbsp; seats ' + combined + ' guests';
    box.style.display = 'block';
}

function seatWalkinForTable(tableId, tableNumber) {
    const select = document.getElementById('walkin_table_id');
    if (select) select.value = tableId;
    openModal('walkinModal');
}

const allAvailableTablesList = <?php echo json_encode(array_values($available_tables)); ?>;
const ADULT_PRICE = 19.99;
const CHILD_PRICE = 9.99;
const TAX_RATE = 0.085;

function walkinPartySize() {
    const adults = parseInt(document.getElementById('walkin_adults').value) || 0;
    const children = parseInt(document.getElementById('walkin_children').value) || 0;
    return { adults, children, guests: adults + children };
}

function updateWalkinEstimate() {
    const { adults, children } = walkinPartySize();
    const subtotal = adults * ADULT_PRICE + children * CHILD_PRICE;
    const total = subtotal + Math.round(subtotal * TAX_RATE * 100) / 100;
    const el = document.getElementById('walkin_estimate');
    if (el) el.innerText = '$' + total.toFixed(2);
}

async function executeWalkinSeating(tableId, name, phone, party) {
    const res = await apiRequest('tables.walkin', {
        table_id: tableId,
        customer_name: name,
        phone: phone,
        guests: party.guests,
        adults: party.adults,
        children: party.children
    });
    if (res.success) {
        closeModal('walkinModal');
        smoothReload();
    }
}

async function submitWalkin(e) {
    e.preventDefault();
    const tableId = document.getElementById('walkin_table_id').value;
    const name = document.getElementById('walkin_name').value.trim();
    const phone = document.getElementById('walkin_phone').value.trim();
    const party = walkinPartySize();

    if (!tableId) {
        showToast("Please select a table", "error");
        return;
    }
    if (party.guests < 1) {
        showToast("Enter at least one adult or child", "error");
        return;
    }

    const selectedTable = allAvailableTablesList.find(t => t.id == tableId);
    if (selectedTable && parseInt(selectedTable.capacity) < party.guests) {
        if (typeof showCapacityWarningModal === 'function') {
            closeModal('walkinModal');
            showCapacityWarningModal({
                tableId: tableId,
                tableName: selectedTable.table_name || `Table ${selectedTable.table_number}`,
                tableCapacity: selectedTable.capacity,
                partySize: party.guests,
                availableTables: allAvailableTablesList,
                onSelectTable: (newTableId) => {
                    executeWalkinSeating(newTableId, name, phone, party);
                },
                onMergeTables: () => {
                    openMergeModal(tableId);
                },
                onProceedAnyway: () => {
                    executeWalkinSeating(tableId, name, phone, party);
                }
            });
        } else {
            // Fallback if realtime.js didn't load
            if (confirm(`⚠️ Table ${selectedTable.table_number} only seats ${selectedTable.capacity} but party has ${party.guests} guests. Proceed anyway?`)) {
                executeWalkinSeating(tableId, name, phone, party);
            }
        }
        return;
    }

    executeWalkinSeating(tableId, name, phone, party);
}

// Reserved-table card actions: walk the guest through Arrived -> Dining without leaving the floor plan
async function seatReservation(reservationId, currentStatus, btn) {
    const action = currentStatus === 'Arrived' ? 'reservations.seat' : 'reservations.arrive';
    return withBusy(btn, async () => {
        const res = await apiRequest(action, { reservation_id: reservationId });
        if (res.success) smoothReload();
    });
}

async function noShowReservation(reservationId, btn) {
    if (!confirm("Mark this booking as No Show and free the table?")) return;
    return withBusy(btn, async () => {
        const res = await apiRequest('reservations.noshow', { reservation_id: reservationId });
        if (res.success) smoothReload();
    });
}

async function submitMergeTables(e) {
    e.preventDefault();
    const primaryId = document.getElementById('merge_primary_id').value;
    const secondaryId = document.getElementById('merge_secondary_id').value;

    if (!primaryId || !secondaryId) {
        showToast("Select both a primary and a secondary table", "error");
        return;
    }
    if (primaryId === secondaryId) {
        showToast("Primary and secondary tables must be different!", "error");
        return;
    }

    const res = await apiRequest('tables.merge', { primary_table_id: primaryId, secondary_table_id: secondaryId });
    if (res.success) {
        closeModal('mergeTableModal');
        smoothReload();
    }
}

async function unmergeTable(tableId, btn) {
    if (!confirm("Unmerge this table and set it to Available?")) return;
    return withBusy(btn, async () => {
        const res = await apiRequest('tables.unmerge', { table_id: tableId });
        if (res.success) smoothReload();
    });
}

async function cleanAllTables(btn) {
    if (!confirm("Mark all dirty tables as clean and available?")) return;
    return withBusy(btn, async () => {
        const res = await apiRequest('tables.clean_all', {});
        if (res.success) {
            closeModal('cleanTableModal');
            smoothReload();
        }
    });
}

async function cleanTable(tableId, btn) {
    return withBusy(btn, async () => {
        const res = await apiRequest('tables.clean', { table_id: tableId });
        if (res.success) smoothReload();
    });
}

async function blockTable(e, tableId) {
    if (e && e.stopPropagation) e.stopPropagation();
    if(!confirm("Block this table from receiving reservations?")) return;
    return withBusy(e && e.currentTarget, async () => {
        const res = await apiRequest('tables.block', { table_id: tableId });
        if (res.success) smoothReload();
    });
}

async function unblockTable(e, tableId) {
    if (e && e.stopPropagation) e.stopPropagation();
    return withBusy(e && e.currentTarget, async () => {
        const res = await apiRequest('tables.unblock', { table_id: tableId });
        if (res.success) smoothReload();
    });
}

function openEditModal(e, tableId, tableNumber, capacity) {
    if (e && e.stopPropagation) e.stopPropagation();
    document.getElementById('edit_table_id').value = tableId;
    document.getElementById('edit_table_number').value = tableNumber;
    document.getElementById('edit_capacity').value = capacity;
    openModal('editTableModal');
}

async function submitEditTable(e) {
    e.preventDefault();
    const tableId = document.getElementById('edit_table_id').value;
    const tableNumber = document.getElementById('edit_table_number').value.trim();
    const capacity = parseInt(document.getElementById('edit_capacity').value);

    if (!tableId || !tableNumber || isNaN(capacity) || capacity < 1) {
        showToast("Please enter a valid table number and capacity", "error");
        return;
    }

    const res = await apiRequest('tables.edit', {
        table_id: tableId,
        table_number: tableNumber,
        capacity: capacity
    });

    if (res.success) {
        closeModal('editTableModal');
        smoothReload();
    }
}

async function deleteTable(e, tableId) {
    if (e && e.stopPropagation) e.stopPropagation();
    if(!confirm("Are you sure you want to delete this table?")) return;

    return withBusy(e && e.currentTarget, async () => {
        const res = await apiRequest('tables.delete', { table_id: tableId });
        if (res.success) smoothReload();
    });
}

// Closes out the table: completes the reservation and sends it to Cleaning.
// The API refuses while the bill is unpaid, so offer the override explicitly.
async function freeTable(tableId, btn) {
    if(!confirm("Close out this table? The booking will be completed and the table sent for cleaning.")) return;

    return withBusy(btn, async () => {
        const res = await apiRequest('tables.free', { table_id: tableId });
        if (res.success) {
            smoothReload();
            return;
        }
        if (res.bill_id) {
            if (confirm(`${res.message}\n\nGo to the bill now? Choose Cancel to free the table anyway and void the bill.`)) {
                window.location.href = 'bills.php?focus=' + res.bill_id;
            } else if (confirm("Free the table and VOID the unpaid bill? This cannot be undone.")) {
                const forced = await apiRequest('tables.free', { table_id: tableId, force: true });
                if (forced.success) smoothReload();
            }
        }
    });
}

function flipTableCard(event, tableId) {
    if (event && event.stopPropagation) event.stopPropagation();
    const inner = document.getElementById('table-card-inner-' + tableId);
    if (inner) {
        inner.classList.toggle('flipped');
        if (window.lucide && typeof window.lucide.createIcons === 'function') {
            window.lucide.createIcons();
        }
    }
}
window.flipTableCard = flipTableCard;

document.addEventListener('DOMContentLoaded', () => {
    updateWalkinEstimate();

    const urlParams = new URLSearchParams(window.location.search);
    const action = urlParams.get('action');

    // Drop one-shot flash params so a refresh doesn't repeat the banner
    if (urlParams.has('msg') || urlParams.has('err')) {
        if (window.history && window.history.replaceState) {
            window.history.replaceState({}, document.title, window.location.pathname);
        }
        document.querySelectorAll('.flash-banner').forEach(el => {
            setTimeout(() => el.classList.add('flash-out'), 4000);
        });
    }

    if (action) {
        if (window.history && window.history.replaceState) {
            window.history.replaceState({}, document.title, window.location.pathname);
        }
        if (action === 'walkin' || action === 'seat') {
            openWalkinModal();
        } else if (action === 'cleantable' || action === 'clean') {
            openCleanModal();
        } else if (action === 'mergetable' || action === 'merge') {
            // ?primary=<id> arrives from the capacity warning on other pages
            openMergeModal(urlParams.get('primary'));
        } else if (action === 'add') {
            openModal('addTableModal');
        }
    }
});
</script>

<?php include 'includes/footer.php'; ?>
