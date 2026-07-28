<?php
// admin/index.php
require_once 'includes/db.php';
require_once 'models/Lead.php';
require_once 'models/Reservation.php';
require_once 'models/Table.php';
require_once 'models/Bill.php';
require_once 'models/Transaction.php';

include 'includes/header.php';

$today = date('Y-m-d');

// 1. Live KPI Calculations from Database History
// Today's Revenue
$stmt = $pdo->prepare("SELECT SUM(amount) as total FROM transactions WHERE status='Paid' AND DATE(created_at) = ?");
$stmt->execute([$today]);
$revenue = (float)($stmt->fetch()['total'] ?? 0);

// Total Customers Served / Booked Today
$stmt = $pdo->prepare("SELECT SUM(guests) as total FROM reservations WHERE status IN ('Upcoming', 'Confirmed', 'Arrived', 'Seated', 'Completed', 'Dining') AND reservation_date = ?");
$stmt->execute([$today]);
$customers = (int)($stmt->fetch()['total'] ?? 0);

// Tables Summary
$all_tables = Table::getAll();
$total_tables = count($all_tables);
$available_tables = 0;
$occupied_tables = 0;
$cleaning_tables = 0;

foreach ($all_tables as $t) {
    if ($t['status'] === 'Available') $available_tables++;
    if ($t['status'] === 'Occupied' || $t['status'] === 'Dining') $occupied_tables++;
    if ($t['status'] === 'Cleaning' || $t['status'] === 'Needs Cleaning') $cleaning_tables++;
}

// Today's Reservations count
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM reservations WHERE reservation_date = ?");
$stmt->execute([$today]);
$reservations_count = (int)($stmt->fetch()['total'] ?? 0);

// Total Leads count
$stmt = $pdo->query("SELECT COUNT(*) as total FROM leads");
$total_leads_count = (int)($stmt->fetch()['total'] ?? 0);

// Pending Bills count
$stmt = $pdo->query("SELECT COUNT(*) as total FROM bills WHERE status IN ('Pending', 'Active')");
$pending_bills = (int)($stmt->fetch()['total'] ?? 0);

// 2. Daily Client Numbers for Current Month (Day 1 to End of Month)
$days_in_month = date('t');
$current_year_month = date('Y-m');
$current_month_name = date('F Y');

$daily_labels = [];
$daily_data = [];

for ($day = 1; $day <= $days_in_month; $day++) {
    $dateStr = sprintf("%s-%02d", $current_year_month, $day);
    $dayLabel = (string)$day;
    
    // Include all non-cancelled reservation statuses
    $stmt = $pdo->prepare("SELECT SUM(guests) as total FROM reservations WHERE status IN ('Upcoming', 'Confirmed', 'Arrived', 'Dining', 'Completed') AND reservation_date = ?");
    $stmt->execute([$dateStr]);
    $count = (int)($stmt->fetch()['total'] ?? 0);
    
    $daily_labels[] = $dayLabel;
    $daily_data[] = $count;
}

$total_monthly_clients = array_sum($daily_data);

$kpis = [
    [
        'label' => "Today's Revenue", 
        'value' => '$' . number_format($revenue, 2), 
        'icon' => 'dollar-sign', 
        'trend' => "Real-time Sales",
        'trend_type' => 'positive',
        'sparkline' => '<svg viewBox="0 0 100 30" width="80" height="24"><path d="M0,25 Q20,20 40,25 T80,12 L100,6" fill="none" stroke="#E53935" stroke-width="2.5" stroke-linecap="round"/></svg>'
    ],
    [
        'label' => 'Customers Served Today', 
        'value' => $customers . ' Pax', 
        'icon' => 'users', 
        'trend' => "$total_monthly_clients Pax ($current_month_name)",
        'trend_type' => 'positive',
        'sparkline' => '<svg viewBox="0 0 100 30" width="80" height="24"><path d="M0,22 Q20,26 40,14 T80,10 L100,4" fill="none" stroke="#E53935" stroke-width="2.5" stroke-linecap="round"/></svg>'
    ],
    [
        'label' => "Today's Bookings", 
        'value' => $reservations_count, 
        'icon' => 'calendar-check', 
        'trend' => "$total_leads_count Total Leads",
        'trend_type' => 'positive',
        'sparkline' => '<svg viewBox="0 0 100 30" width="80" height="24"><path d="M0,25 Q30,25 50,15 T100,5" fill="none" stroke="#E53935" stroke-width="2.5" stroke-linecap="round"/></svg>'
    ],
    [
        'label' => 'Pending Bills', 
        'value' => $pending_bills, 
        'icon' => 'receipt', 
        'trend' => $pending_bills > 0 ? 'Action Required' : 'All Settled',
        'trend_type' => $pending_bills > 0 ? 'warning' : 'positive',
        'sparkline' => '<svg viewBox="0 0 100 30" width="80" height="24"><path d="M0,20 Q40,25 70,12 T100,8" fill="none" stroke="#F59E0B" stroke-width="2.5" stroke-linecap="round"/></svg>'
    ]
];

// 3. Today's Reservations List
$stmt = $pdo->prepare("SELECT * FROM reservations WHERE reservation_date = ? ORDER BY reservation_time ASC");
$stmt->execute([$today]);
$reservations = $stmt->fetchAll();

if (empty($reservations)) {
    $stmt = $pdo->query("SELECT * FROM reservations ORDER BY created_at DESC LIMIT 6");
    $reservations = $stmt->fetchAll();
}
?>

<!-- Quick Actions Panel -->
<div class="glass-card" style="padding: 16px 24px; margin-bottom: 4px;">
    <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
        <div style="display: flex; align-items: center; gap: 8px; font-weight: 800; font-size: 0.9rem; color: var(--text-primary);">
            <i data-lucide="zap" style="color: var(--primary-red); width: 18px;"></i>
            <span>Quick Actions</span>
        </div>
        <div class="quick-actions-bar">
            <button class="btn btn-primary btn-sm" onclick="window.location.href='reservations.php?action=new'">
                <i data-lucide="plus" style="width: 14px;"></i> New Reservation
            </button>
            <button class="btn btn-secondary btn-sm" onclick="window.location.href='tables.php'">
                <i data-lucide="user-check" style="width: 14px;"></i> Walk-in Customer
            </button>
            <button class="btn btn-secondary btn-sm" onclick="window.location.href='bills.php'">
                <i data-lucide="credit-card" style="width: 14px;"></i> Generate Bill
            </button>
            <button class="btn btn-secondary btn-sm" onclick="window.location.href='tables.php?action=add'">
                <i data-lucide="grid-2x2" style="width: 14px;"></i> Add Table
            </button>
            <button class="btn btn-secondary btn-sm" onclick="window.location.href='sales.php'">
                <i data-lucide="download" style="width: 14px;"></i> Export Report
            </button>
        </div>
    </div>
</div>

<!-- KPI Cards Grid -->
<div class="kpi-grid">
    <?php foreach ($kpis as $kpi): ?>
    <div class="kpi-card">
        <div class="kpi-top">
            <div class="kpi-icon">
                <i data-lucide="<?php echo $kpi['icon']; ?>"></i>
            </div>
            <div class="kpi-details">
                <div class="kpi-label"><?php echo $kpi['label']; ?></div>
                <div class="kpi-value"><?php echo $kpi['value']; ?></div>
            </div>
        </div>
        <div class="kpi-bottom">
            <div class="kpi-trend <?php echo $kpi['trend_type']; ?>">
                <i data-lucide="trending-up" style="width: 12px; height: 12px;"></i>
                <span><?php echo $kpi['trend']; ?></span>
            </div>
            <div class="kpi-sparkline">
                <?php echo $kpi['sparkline']; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Dynamic Visual Charts Grid -->
<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-bottom: 20px;">
    <!-- Monthly Client Numbers Chart -->
    <div class="glass-card">
        <div class="card-header">
            <div>
                <h2 class="card-title"><i data-lucide="users"></i> Monthly Client Numbers (<?php echo $current_month_name; ?>)</h2>
                <div class="card-subtitle">Daily client count for each day of <?php echo $current_month_name; ?></div>
            </div>
            <span class="badge-pill" style="font-size: 0.8rem; font-weight: 700; color: var(--primary-red); background: var(--light-pink); padding: 4px 12px; border-radius: var(--radius-pill);">
                Total: <?php echo number_format($total_monthly_clients); ?> Pax This Month
            </span>
        </div>
        <div style="height: 260px; position: relative;">
            <canvas id="monthlyClientsChart"></canvas>
        </div>
    </div>

    <!-- Table Occupancy Doughnut Chart -->
    <div class="glass-card">
        <div class="card-header">
            <div>
                <h2 class="card-title"><i data-lucide="pie-chart"></i> Occupancy Rate</h2>
                <div class="card-subtitle">Real-time table utilization</div>
            </div>
        </div>
        <div style="height: 180px; position: relative; margin-top: 10px;">
            <canvas id="occupancyChart"></canvas>
        </div>
        <div style="display: flex; justify-content: space-around; margin-top: 16px; padding-top: 12px; border-top: 1px solid rgba(0,0,0,0.04);">
            <div style="text-align: center;">
                <div style="font-size: 1.1rem; font-weight: 800; color: var(--primary-red);"><?php echo $occupied_tables; ?></div>
                <div style="font-size: 0.725rem; color: var(--text-muted); font-weight: 600;">Occupied</div>
            </div>
            <div style="text-align: center;">
                <div style="font-size: 1.1rem; font-weight: 800; color: #10B981;"><?php echo $available_tables; ?></div>
                <div style="font-size: 0.725rem; color: var(--text-muted); font-weight: 600;">Available</div>
            </div>
            <div style="text-align: center;">
                <div style="font-size: 1.1rem; font-weight: 800; color: #64748B;"><?php echo $cleaning_tables; ?></div>
                <div style="font-size: 0.725rem; color: var(--text-muted); font-weight: 600;">Cleaning</div>
            </div>
        </div>
    </div>
</div>

<!-- Today's Reservations Table -->
<div class="glass-card">
    <div class="card-header">
        <h2 class="card-title"><i data-lucide="calendar"></i> Reservations Overview</h2>
        <a href="reservations.php" class="btn btn-secondary btn-sm">View All Reservations</a>
    </div>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>TIME</th>
                    <th>CUSTOMER</th>
                    <th>PARTY SIZE</th>
                    <th>STATUS</th>
                    <th>ACTIONS</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($reservations) > 0): ?>
                    <?php foreach ($reservations as $res): 
                        $status = htmlspecialchars($res['status']);
                        $badgeClass = strtolower(str_replace(' ', '-', $status));
                    ?>
                    <tr>
                        <td style="font-weight: 700; color: var(--primary-red);"><?php echo date('g:i A', strtotime($res['reservation_time'])); ?></td>
                        <td style="font-weight: 700;"><?php echo htmlspecialchars($res['customer_name']); ?></td>
                        <td><span style="background: rgba(255,255,255,0.8); padding: 4px 10px; border-radius: var(--radius-pill); font-weight: 700; font-size: 0.75rem; border: 1px solid rgba(0,0,0,0.06);"><?php echo htmlspecialchars($res['guests']); ?> Pax</span></td>
                        <td id="status-cell-<?php echo $res['id']; ?>">
                            <span class="status-badge status-<?php echo $badgeClass; ?>"><?php echo $status; ?></span>
                        </td>
                        <td>
                            <div class="action-btns">
                                <?php if ($status === 'Confirmed' || $status === 'Pending' || $status === 'Upcoming'): ?>
                                <button class="action-btn" title="Mark Arrived" onclick="updateReservationStatus(<?php echo $res['id']; ?>, 'Arrived')">
                                    <i data-lucide="user-check" style="color: #10B981;"></i>
                                </button>
                                <button class="action-btn" title="Cancel" onclick="updateReservationStatus(<?php echo $res['id']; ?>, 'Cancelled')">
                                    <i data-lucide="x-circle" style="color: #E53935;"></i>
                                </button>
                                <?php elseif ($status === 'Arrived'): ?>
                                <button class="action-btn" title="Seat Customer" onclick="updateReservationStatus(<?php echo $res['id']; ?>, 'Dining')">
                                    <i data-lucide="play" style="color: var(--primary-red);"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5">
                            <div class="empty-state">
                                <div class="empty-state-icon"><i data-lucide="calendar-x"></i></div>
                                <div class="empty-state-title">No Active Reservations</div>
                                <div class="empty-state-desc">You're all caught up! Click below to create a new reservation.</div>
                                <a href="reservations.php?action=new" class="btn btn-primary btn-sm">Create Reservation</a>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // 1. Monthly Client Numbers Chart (Daily breakdown for current month)
    const clientCtx = document.getElementById('monthlyClientsChart')?.getContext('2d');
    if (clientCtx) {
        new Chart(clientCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($daily_labels); ?>,
                datasets: [{
                    label: 'Daily Clients (Pax)',
                    data: <?php echo json_encode($daily_data); ?>,
                    backgroundColor: 'rgba(229, 57, 53, 0.85)',
                    hoverBackgroundColor: '#E53935',
                    borderRadius: 4,
                    barThickness: 10
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { 
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            title: (items) => 'Day ' + items[0].label + ' of <?php echo date('M Y'); ?>',
                            label: (item) => item.raw + ' Clients'
                        }
                    }
                },
                scales: {
                    x: { 
                        grid: { display: false },
                        title: { display: true, text: 'Day of Month (<?php echo date('M Y'); ?>)', font: { size: 11, weight: 'bold' } }
                    },
                    y: { 
                        grid: { color: 'rgba(0,0,0,0.04)' }, 
                        beginAtZero: true,
                        ticks: {
                            precision: 0,
                            stepSize: 1
                        },
                        title: { display: true, text: 'Clients Count', font: { size: 11, weight: 'bold' } }
                    }
                }
            }
        });
    }

    // 2. Occupancy Rate Doughnut Chart
    const occCtx = document.getElementById('occupancyChart')?.getContext('2d');
    if (occCtx) {
        const occ = <?php echo $occupied_tables; ?>;
        const avail = <?php echo $available_tables; ?>;
        const cln = <?php echo $cleaning_tables; ?>;
        new Chart(occCtx, {
            type: 'doughnut',
            data: {
                labels: ['Occupied', 'Available', 'Cleaning'],
                datasets: [{
                    data: [occ, avail, cln],
                    backgroundColor: ['#E53935', '#10B981', '#64748B'],
                    borderWidth: 0,
                    hoverOffset: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                cutout: '78%'
            }
        });
    }
});

async function updateReservationStatus(resId, newStatus) {
    if(!confirm(`Mark this reservation as ${newStatus}?`)) return;
    const action = newStatus === 'Cancelled' ? 'reservations.cancel' : (newStatus === 'Arrived' ? 'reservations.arrive' : 'reservations.seat');
    const res = await apiRequest(action, { reservation_id: resId });
    if(res.success) {
        setTimeout(() => location.reload(), 800);
    }
}
</script>

<?php include 'includes/footer.php'; ?>
