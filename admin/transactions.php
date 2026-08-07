<?php
// admin/transactions.php
require_once 'includes/db.php';
require_once 'models/Transaction.php';

include 'includes/header.php';

$transactions = Transaction::getAll();
?>

<!-- Filter & Search Header -->
<div class="glass-card" style="padding: 16px 24px; margin-bottom: 4px;">
    <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 14px;">
        <div class="filter-chips" style="margin: 0;">
            <button class="chip-btn active" onclick="filterTxns('all')">All Transactions (<?php echo count($transactions); ?>)</button>
            <button class="chip-btn" onclick="filterTxns('paid')">Paid</button>
            <button class="chip-btn" onclick="filterTxns('pending')">Pending</button>
            <button class="chip-btn" onclick="filterTxns('refunded')">Refunded</button>
        </div>
        <div style="display: flex; gap: 10px;">
            <input type="text" placeholder="Search bill # or method..." class="form-control" style="padding: 8px 16px; border-radius: var(--radius-pill); font-size: 0.825rem; width: 220px;" onkeyup="searchTxns(this.value)">
        </div>
    </div>
</div>

<!-- Transactions Glass Table -->
<div class="glass-card">
    <div class="card-header">
        <h2 class="card-title"><i data-lucide="history"></i> Transaction History</h2>
        <span class="badge-pill" style="font-size: 0.8rem; font-weight: 700; color: var(--primary-red); background: var(--light-pink); padding: 4px 12px; border-radius: var(--radius-pill);"><?php echo count($transactions); ?> Records</span>
    </div>

    <div class="table-responsive">
        <table id="transactionsTable">
            <thead>
                <tr>
                    <th>DATE & TIME</th>
                    <th>BILL NO.</th>
                    <th>PAYMENT METHOD</th>
                    <th>AMOUNT</th>
                    <th>STATUS</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($transactions) > 0): ?>
                    <?php foreach ($transactions as $txn): 
                        $status = htmlspecialchars($txn['status']);
                        $badgeClass = strtolower(str_replace(' ', '-', $status));
                        $billNo = $txn['bill_id'] ?? $txn['bill_no'] ?? $txn['bill_number'] ?? $txn['id'] ?? 'N/A';
                    ?>
                    <tr class="txn-row" data-status="<?php echo $badgeClass; ?>">
                        <td>
                            <div style="font-weight: 700; color: var(--text-primary);"><?php echo date('M d, Y', strtotime($txn['created_at'])); ?></div>
                            <div style="font-size: 0.725rem; color: var(--text-muted);"><?php echo date('g:i A', strtotime($txn['created_at'])); ?></div>
                        </td>
                        <td style="font-weight: 800; color: var(--primary-red);">Bill #<?php echo htmlspecialchars($billNo); ?></td>
                        <td style="font-weight: 600; color: var(--text-primary);"><?php echo htmlspecialchars($txn['payment_method']); ?></td>
                        <td style="font-weight: 800; font-size: 1rem; color: var(--text-primary);">$<?php echo number_format($txn['amount'], 2); ?></td>
                        <td><span class="status-badge status-<?php echo $badgeClass; ?>"><?php echo $status; ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5">
                            <div class="empty-state">
                                <div class="empty-state-icon"><i data-lucide="history"></i></div>
                                <div class="empty-state-title">No Transactions Recorded</div>
                                <div class="empty-state-desc">Transaction records will appear here as billing payments are completed.</div>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function filterTxns(statusFilter) {
    document.querySelectorAll('.chip-btn').forEach(btn => btn.classList.remove('active'));
    event.target.classList.add('active');

    const rows = document.querySelectorAll('.txn-row');
    rows.forEach(row => {
        if (statusFilter === 'all' || row.dataset.status === statusFilter) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

function searchTxns(query) {
    query = query.toLowerCase();
    const rows = document.querySelectorAll('.txn-row');
    rows.forEach(row => {
        const text = row.innerText.toLowerCase();
        row.style.display = text.includes(query) ? '' : 'none';
    });
}
</script>

<?php include 'includes/footer.php'; ?>
