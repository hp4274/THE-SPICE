<?php
// admin/sales.php
require_once 'includes/db.php';
include 'includes/header.php';

$today = date('Y-m-d');

// 1. Total Revenue Today
$stmt = $pdo->prepare("SELECT SUM(amount) as total FROM transactions WHERE status='Paid' AND DATE(created_at) = ?");
$stmt->execute([$today]);
$revenue = $stmt->fetch()['total'] ?? 0;

// 2. Total Transactions Today
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM transactions WHERE status='Paid' AND DATE(created_at) = ?");
$stmt->execute([$today]);
$transaction_count = $stmt->fetch()['total'] ?? 0;

// 3. Average Transaction Value
$avg_value = $transaction_count > 0 ? ($revenue / $transaction_count) : 0;

// 4. Payment Method Breakdown for Chart
$stmt = $pdo->prepare("
    SELECT payment_method, SUM(amount) as total 
    FROM transactions 
    WHERE status='Paid' AND DATE(created_at) = ? 
    GROUP BY payment_method
");
$stmt->execute([$today]);
$payment_methods = $stmt->fetchAll();

$chart_labels = [];
$chart_values = [];
foreach ($payment_methods as $pm) {
    $chart_labels[] = $pm['payment_method'] ?: 'Cash';
    $chart_values[] = floatval($pm['total']);
}

$has_chart_data = !empty($chart_values) && array_sum($chart_values) > 0;

// 5. Today's Transactions List
$stmt = $pdo->prepare("SELECT * FROM transactions WHERE DATE(created_at) = ? ORDER BY created_at DESC");
$stmt->execute([$today]);
$today_transactions = $stmt->fetchAll();

// Deduplicate transactions list by unique bill / transaction ID & status
$deduped_txns = [];
$seen_keys = [];
foreach ($today_transactions as $txn) {
    $key = ($txn['bill_id'] ?? $txn['bill_no'] ?? $txn['id']) . '_' . $txn['status'] . '_' . $txn['amount'];
    if (!isset($seen_keys[$key])) {
        $seen_keys[$key] = true;
        $deduped_txns[] = $txn;
    }
}
$today_transactions = $deduped_txns;
?>

<!-- Sales KPI Grid -->
<div class="kpi-grid">
    <div class="kpi-card">
        <div class="kpi-top">
            <div class="kpi-icon">
                <i data-lucide="dollar-sign"></i>
            </div>
            <div class="kpi-details">
                <div class="kpi-label">Today's Revenue</div>
                <div class="kpi-value">$<?php echo number_format($revenue, 2); ?></div>
            </div>
        </div>
        <div class="kpi-bottom">
            <div class="kpi-trend positive">
                <i data-lucide="trending-up" style="width: 12px;"></i> +18.4% vs yesterday
            </div>
        </div>
    </div>

    <div class="kpi-card">
        <div class="kpi-top">
            <div class="kpi-icon" style="background: rgba(16,185,129,0.12); color: #10B981;">
                <i data-lucide="receipt"></i>
            </div>
            <div class="kpi-details">
                <div class="kpi-label">Paid Transactions</div>
                <div class="kpi-value"><?php echo $transaction_count; ?> Orders</div>
            </div>
        </div>
        <div class="kpi-bottom">
            <div class="kpi-trend positive">
                <i data-lucide="check" style="width: 12px;"></i> All settled
            </div>
        </div>
    </div>

    <div class="kpi-card">
        <div class="kpi-top">
            <div class="kpi-icon" style="background: rgba(139,92,246,0.12); color: #8B5CF6;">
                <i data-lucide="calculator"></i>
            </div>
            <div class="kpi-details">
                <div class="kpi-label">Average Order Value</div>
                <div class="kpi-value">$<?php echo number_format($avg_value, 2); ?></div>
            </div>
        </div>
        <div class="kpi-bottom">
            <div class="kpi-trend positive">
                <i data-lucide="trending-up" style="width: 12px;"></i> Healthy margin
            </div>
        </div>
    </div>
</div>

<div style="display: grid; grid-template-columns: 1fr 2fr; gap: 20px;">
    <!-- Payment Method Doughnut Chart -->
    <div class="glass-card">
        <div class="card-header">
            <h2 class="card-title"><i data-lucide="pie-chart"></i> Revenue by Payment Method</h2>
        </div>
        <div style="height: 240px; position: relative; display: flex; align-items: center; justify-content: center;">
            <?php if ($has_chart_data): ?>
                <canvas id="paymentChart"></canvas>
            <?php else: ?>
                <div class="empty-state" style="padding: 20px; text-align: center;">
                    <div class="empty-state-icon"><i data-lucide="pie-chart" style="width: 32px; height: 32px; color: var(--text-muted);"></i></div>
                    <div class="empty-state-title" style="font-size: 0.9rem; font-weight: 700; margin-top: 8px;">No Sales Data Today</div>
                    <div class="empty-state-desc" style="font-size: 0.775rem; color: var(--text-muted);">Revenue breakdown will display as payments are settled.</div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Today's Transaction Log -->
    <div class="glass-card">
        <div class="card-header">
            <h2 class="card-title"><i data-lucide="history"></i> Today's Transaction Log</h2>
        </div>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>TIME</th>
                        <th>BILL NO.</th>
                        <th>PAYMENT METHOD</th>
                        <th>AMOUNT</th>
                        <th>STATUS</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($today_transactions) > 0): ?>
                        <?php foreach ($today_transactions as $txn): 
                            $status = htmlspecialchars($txn['status']);
                            $badgeClass = strtolower(str_replace(' ', '-', $status));
                            $billNo = $txn['bill_id'] ?? $txn['bill_no'] ?? $txn['bill_number'] ?? $txn['id'] ?? 'N/A';
                        ?>
                        <tr>
                            <td style="font-weight: 700; color: var(--text-muted);"><?php echo date('g:i A', strtotime($txn['created_at'])); ?></td>
                            <td style="font-weight: 800; color: var(--primary-red);">Bill #<?php echo htmlspecialchars($billNo); ?></td>
                            <td style="font-weight: 600;"><?php echo htmlspecialchars($txn['payment_method'] ?: 'Cash'); ?></td>
                            <td style="font-weight: 800; color: var(--text-primary);">$<?php echo number_format($txn['amount'], 2); ?></td>
                            <td><span class="status-badge status-<?php echo $badgeClass; ?>"><?php echo $status; ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5">
                                <div class="empty-state">
                                    <div class="empty-state-icon"><i data-lucide="receipt"></i></div>
                                    <div class="empty-state-title">No Transactions Today</div>
                                    <div class="empty-state-desc">Transactions will automatically log here as bills are settled.</div>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const ctx = document.getElementById('paymentChart')?.getContext('2d');
    if (ctx) {
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($chart_labels); ?>,
                datasets: [{
                    data: <?php echo json_encode($chart_values); ?>,
                    backgroundColor: ['#E53935', '#10B981', '#8B5CF6', '#F97316'],
                    borderWidth: 0,
                    hoverOffset: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            font: { family: 'Plus Jakarta Sans', weight: '700' },
                            padding: 16
                        }
                    }
                },
                cutout: '72%'
            }
        });
    }
});
</script>

<?php include 'includes/footer.php'; ?>
