<?php
// admin/bills.php
require_once 'includes/db.php';
require_once 'models/Bill.php';
require_once 'models/Reservation.php';

include 'includes/header.php';

// Fetch all bills
$bills = Bill::getAll();
?>

<!-- Filter Chips Header -->
<div class="glass-card" style="padding: 16px 24px; margin-bottom: 4px;">
    <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 14px;">
        <div class="filter-chips" style="margin: 0;">
            <button class="chip-btn active" onclick="filterBills('all')">All Bills (<?php echo count($bills); ?>)</button>
            <button class="chip-btn" onclick="filterBills('pending')">Pending</button>
            <button class="chip-btn" onclick="filterBills('active')">Active</button>
            <button class="chip-btn" onclick="filterBills('paid')">Paid</button>
            <button class="chip-btn" onclick="filterBills('refunded')">Refunded</button>
        </div>
        <div style="display: flex; gap: 10px;">
            <input type="text" placeholder="Search bill # or customer..." class="form-control" style="padding: 8px 16px; border-radius: var(--radius-pill); font-size: 0.825rem; width: 220px;" onkeyup="searchBills(this.value)">
        </div>
    </div>
</div>

<!-- Bills Glass Table -->
<div class="glass-card">
    <div class="card-header">
        <h2 class="card-title"><i data-lucide="receipt"></i> Bills & Payments</h2>
        <span class="badge-pill" style="font-size: 0.8rem; font-weight: 700; color: var(--primary-red); background: var(--light-pink); padding: 4px 12px; border-radius: var(--radius-pill);"><?php echo count($bills); ?> Records</span>
    </div>

    <div class="table-responsive">
        <table id="billsTable">
            <thead>
                <tr>
                    <th>BILL ID</th>
                    <th>RESERVATION</th>
                    <th>CUSTOMER</th>
                    <th>SUBTOTAL</th>
                    <th>TAX / GST</th>
                    <th>GRAND TOTAL</th>
                    <th>STATUS</th>
                    <th>ACTIONS</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($bills) > 0): ?>
                    <?php foreach ($bills as $bill): 
                        $status = htmlspecialchars($bill['status']);
                        $badgeClass = strtolower(str_replace(' ', '-', $status));
                        $reservation = $bill['reservation_id'] ? Reservation::getById($bill['reservation_id']) : null;
                    ?>
                    <tr class="bill-row" data-status="<?php echo $badgeClass; ?>" id="bill-row-<?php echo $bill['id']; ?>">
                        <td style="font-weight: 800; color: var(--primary-red);">Bill #<?php echo htmlspecialchars($bill['id']); ?></td>
                        <td>
                            <?php if ($reservation): ?>
                                <span style="font-weight: 700; color: var(--text-primary);">Res #<?php echo htmlspecialchars($reservation['id']); ?></span>
                            <?php else: ?>
                                <span style="color: var(--text-muted); font-style: italic;">Walk-in</span>
                            <?php endif; ?>
                        </td>
                        <td style="font-weight: 700; color: var(--text-primary);"><?php echo htmlspecialchars($bill['customer_name']); ?></td>
                        <td style="font-weight: 600; color: var(--text-secondary);">$<?php echo number_format($bill['subtotal'] ?? 0, 2); ?></td>
                        <td style="font-weight: 600; color: var(--text-secondary);">$<?php echo number_format($bill['gst'] ?? 0, 2); ?></td>
                        <td style="font-weight: 800; font-size: 1rem; color: var(--text-primary);">$<?php echo number_format($bill['grand_total'], 2); ?></td>
                        <td id="status-cell-<?php echo $bill['id']; ?>">
                            <span class="status-badge status-<?php echo $badgeClass; ?>"><?php echo $status; ?></span>
                        </td>
                        <td>
                            <div class="action-btns">
                                <?php if ($status === 'Pending' || $status === 'Active'): ?>
                                <button type="button" class="btn btn-primary btn-sm" onclick="openPayBillModal(<?php echo $bill['id']; ?>, '<?php echo htmlspecialchars($bill['customer_name']); ?>', <?php echo $bill['grand_total']; ?>)">
                                    <i data-lucide="dollar-sign" style="width: 14px;"></i> Pay Now
                                </button>
                                <?php elseif ($status === 'Paid'): ?>
                                <button type="button" class="btn btn-danger btn-sm" onclick="openRefundModal(<?php echo $bill['id']; ?>, '<?php echo htmlspecialchars($bill['customer_name']); ?>', <?php echo $bill['grand_total']; ?>)">
                                    <i data-lucide="rotate-ccw" style="width: 14px;"></i> Refund
                                </button>
                                <?php else: ?>
                                <button type="button" class="btn btn-secondary btn-sm" style="opacity: 0.7;" disabled>
                                    <?php echo $status; ?>
                                </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8">
                            <div class="empty-state">
                                <div class="empty-state-icon"><i data-lucide="receipt"></i></div>
                                <div class="empty-state-title">No Bills Found</div>
                                <div class="empty-state-desc">There are no bill records available currently.</div>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Pay Bill -->
<div class="modal-overlay" id="payBillModal">
    <div class="modal-glass-content">
        <div class="modal-header">
            <div class="modal-title">
                <i data-lucide="credit-card"></i> Settle Payment
            </div>
            <button class="modal-close" onclick="closeModal('payBillModal')">
                <i data-lucide="x"></i>
            </button>
        </div>
        <form id="payBillForm" onsubmit="submitPayBill(event)">
            <input type="hidden" id="pay_bill_id">
            
            <div style="background: var(--light-pink); border-radius: var(--radius-md); padding: 16px; margin-bottom: 20px; text-align: center;">
                <div style="font-size: 0.8rem; font-weight: 700; color: var(--text-secondary); text-transform: uppercase;">Amount Due</div>
                <div style="font-size: 2.2rem; font-weight: 800; color: var(--primary-red); margin-top: 2px;" id="pay_bill_amount_display">$0.00</div>
                <div style="font-size: 0.85rem; color: var(--text-muted); font-weight: 600; margin-top: 4px;" id="pay_bill_customer_display">Customer Name</div>
            </div>

            <div class="form-group">
                <label class="form-label">Payment Method</label>
                <select id="pay_method" class="form-control" required style="padding: 12px;">
                    <option value="Cash">Cash Payment</option>
                    <option value="Credit Card">Credit / Debit Card</option>
                    <option value="Online / UPI">Digital Wallet / UPI</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Amount Received ($)</label>
                <input type="number" step="0.01" id="pay_amount" class="form-control" required>
            </div>

            <div style="margin-top: 24px; display: flex; gap: 12px; justify-content: flex-end;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('payBillModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Complete Payment</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Refund Bill -->
<div class="modal-overlay" id="refundModal">
    <div class="modal-glass-content">
        <div class="modal-header">
            <div class="modal-title">
                <i data-lucide="rotate-ccw"></i> Issue Refund
            </div>
            <button class="modal-close" onclick="closeModal('refundModal')">
                <i data-lucide="x"></i>
            </button>
        </div>
        <form id="refundForm" onsubmit="submitRefund(event)">
            <input type="hidden" id="refund_bill_id">
            
            <div class="form-group">
                <label class="form-label">Refund Amount ($)</label>
                <input type="number" step="0.01" id="refund_amount" class="form-control" required>
            </div>

            <div class="form-group">
                <label class="form-label">Reason for Refund</label>
                <input type="text" id="refund_reason" class="form-control" placeholder="Customer Cancellation / Quality Issue" required>
            </div>

            <div style="margin-top: 24px; display: flex; gap: 12px; justify-content: flex-end;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('refundModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Issue Refund</button>
            </div>
        </form>
    </div>
</div>

<script>
function openPayBillModal(billId, customerName, grandTotal) {
    document.getElementById('pay_bill_id').value = billId;
    document.getElementById('pay_bill_amount_display').innerText = '$' + parseFloat(grandTotal).toFixed(2);
    document.getElementById('pay_bill_customer_display').innerText = 'Customer: ' + customerName;
    document.getElementById('pay_amount').value = parseFloat(grandTotal).toFixed(2);
    openModal('payBillModal');
}

function openRefundModal(billId, customerName, grandTotal) {
    document.getElementById('refund_bill_id').value = billId;
    document.getElementById('refund_amount').value = parseFloat(grandTotal).toFixed(2);
    openModal('refundModal');
}

async function submitPayBill(e) {
    e.preventDefault();
    const billId = document.getElementById('pay_bill_id').value;
    const method = document.getElementById('pay_method').value;
    const amount = parseFloat(document.getElementById('pay_amount').value);

    const res = await apiRequest('bills.pay', { bill_id: billId, payment_method: method, amount: amount });
    if(res.success) {
        closeModal('payBillModal');
        setTimeout(() => location.reload(), 800);
    }
}

async function submitRefund(e) {
    e.preventDefault();
    const billId = document.getElementById('refund_bill_id').value;
    const amount = parseFloat(document.getElementById('refund_amount').value);
    const reason = document.getElementById('refund_reason').value;

    const res = await apiRequest('bills.refund', { bill_id: billId, amount: amount, reason: reason });
    if(res.success) {
        closeModal('refundModal');
        setTimeout(() => location.reload(), 800);
    }
}

function filterBills(statusFilter) {
    document.querySelectorAll('.chip-btn').forEach(btn => btn.classList.remove('active'));
    event.target.classList.add('active');

    const rows = document.querySelectorAll('.bill-row');
    rows.forEach(row => {
        if (statusFilter === 'all' || row.dataset.status === statusFilter) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

function searchBills(query) {
    query = query.toLowerCase();
    const rows = document.querySelectorAll('.bill-row');
    rows.forEach(row => {
        const text = row.innerText.toLowerCase();
        row.style.display = text.includes(query) ? '' : 'none';
    });
}
</script>

<?php include 'includes/footer.php'; ?>
