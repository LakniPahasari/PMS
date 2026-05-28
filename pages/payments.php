<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';
requireLogin();

$db = getDB();

// Prescriptions with no payment or unpaid (for "New Payment" modal)
$unpaidRx = $db->query("
    SELECT p.prescription_id, p.status, p.created_at,
           c.name AS customer_name,
           pay.payment_status
    FROM PRESCRIPTION p
    JOIN CUSTOMER c     ON c.customer_id      = p.customer_id
    LEFT JOIN PAYMENT pay ON pay.prescription_id = p.prescription_id
    WHERE pay.payment_id IS NULL OR pay.payment_status = 'unpaid'
    ORDER BY p.created_at DESC
")->fetchAll();

$payments = $db->query("
    SELECT pay.payment_id, pay.prescription_id, pay.amount,
           pay.payment_method, pay.payment_status, pay.notes,
           pay.billed_date, pay.paid_at,
           c.name AS customer_name, c.customer_id
    FROM PAYMENT pay
    JOIN PRESCRIPTION p ON p.prescription_id = pay.prescription_id
    JOIN CUSTOMER c     ON c.customer_id      = p.customer_id
    ORDER BY pay.billed_date DESC, pay.payment_id DESC
")->fetchAll();

$total    = count($payments);
$paid     = $unpaid = $awaiting = 0;
$revenue  = 0.0;
foreach ($payments as $pay) {
    if ($pay['payment_status'] === 'paid')            { $paid++;     $revenue += (float)($pay['amount'] ?? 0); }
    elseif ($pay['payment_status'] === 'unpaid')        $unpaid++;
    elseif ($pay['payment_status'] === 'awaiting_pickup') $awaiting++;
}

$pageTitle = 'Payments';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Stat cards -->
<div class="stats-grid">
    <div class="stat-card blue">
        <div class="stat-icon">💳</div>
        <div><div class="stat-value"><?= $total ?></div><div class="stat-label">Total Payments</div></div>
    </div>
    <div class="stat-card green">
        <div class="stat-icon">✅</div>
        <div><div class="stat-value"><?= $paid ?></div><div class="stat-label">Paid</div></div>
    </div>
    <div class="stat-card yellow">
        <div class="stat-icon">🕐</div>
        <div><div class="stat-value"><?= $awaiting ?></div><div class="stat-label">Awaiting Pickup</div></div>
    </div>
    <div class="stat-card red">
        <div class="stat-icon">🔴</div>
        <div><div class="stat-value"><?= $unpaid ?></div><div class="stat-label">Unpaid</div></div>
    </div>
</div>

<!-- Revenue card -->
<?php if ($revenue > 0): ?>
<div class="card" style="margin-bottom:20px;background:linear-gradient(135deg,#1d4ed8,#2563eb);color:#fff;">
    <div class="card-body" style="display:flex;align-items:center;gap:16px;">
        <span style="font-size:28px;">💰</span>
        <div>
            <div style="font-size:22px;font-weight:800;">£<?= number_format($revenue, 2) ?></div>
            <div style="font-size:13px;opacity:.85;">Total Revenue Collected</div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Toolbar -->
<div style="display:flex;align-items:center;gap:12px;margin-bottom:16px;flex-wrap:wrap;">
    <button class="btn btn-primary" id="openNewPayment">+ New Payment</button>
    <div style="display:flex;gap:6px;" id="statusTabs">
        <button class="btn tab-btn active" data-filter="all">All (<?= $total ?>)</button>
        <button class="btn tab-btn" data-filter="unpaid" style="background:#fee2e2;color:#991b1b;">Unpaid (<?= $unpaid ?>)</button>
        <button class="btn tab-btn" data-filter="awaiting_pickup" style="background:#fef3c7;color:#92400e;">Awaiting Pickup (<?= $awaiting ?>)</button>
        <button class="btn tab-btn" data-filter="paid" style="background:#d1fae5;color:#065f46;">Paid (<?= $paid ?>)</button>
    </div>
</div>

<!-- Payments table -->
<div class="card">
    <div class="card-body">
        <?php if ($payments): ?>
        <div class="table-wrap">
            <table id="paymentsTable">
                <thead>
                    <tr>
                        <th>Pay #</th>
                        <th>Prescription</th>
                        <th>Customer</th>
                        <th>Amount</th>
                        <th>Method</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th style="width:100px;text-align:center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($payments as $pay): ?>
                    <tr data-status="<?= $pay['payment_status'] ?>">
                        <td>#<?= $pay['payment_id'] ?></td>
                        <td>
                            <a href="/pages/prescriptions.php" style="color:var(--primary);font-weight:600;">
                                RX-<?= $pay['prescription_id'] ?>
                            </a>
                        </td>
                        <td><?= htmlspecialchars($pay['customer_name']) ?></td>
                        <td><?= $pay['amount'] !== null ? '£'.number_format((float)$pay['amount'], 2) : '<span style="color:var(--text-muted)">—</span>' ?></td>
                        <td>
                            <?php if ($pay['payment_method'] === 'cash'): ?>
                                <span class="badge" style="background:#fef3c7;color:#92400e;">💵 Cash</span>
                            <?php elseif ($pay['payment_method'] === 'card'): ?>
                                <span class="badge" style="background:#ede9fe;color:#5b21b6;">💳 Card</span>
                            <?php else: ?>
                                <span style="color:var(--text-muted)">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            $statusStyles = [
                                'paid'            => 'background:#d1fae5;color:#065f46',
                                'awaiting_pickup' => 'background:#fef3c7;color:#92400e',
                                'unpaid'          => 'background:#fee2e2;color:#991b1b',
                            ];
                            $statusLabels = [
                                'paid'            => 'Paid',
                                'awaiting_pickup' => 'Awaiting Pickup',
                                'unpaid'          => 'Unpaid',
                            ];
                            $s = $pay['payment_status'];
                            ?>
                            <span class="badge" style="<?= $statusStyles[$s] ?? '' ?>">
                                <?= $statusLabels[$s] ?? ucfirst($s) ?>
                            </span>
                        </td>
                        <td><?= date('d M Y', strtotime($pay['billed_date'])) ?></td>
                        <td style="text-align:center;display:flex;gap:6px;justify-content:center;">
                            <a href="/pages/payment_form.php?rx=<?= $pay['prescription_id'] ?>"
                               class="btn-icon" title="Edit payment">✏️</a>
                            <?php if ($pay['payment_status'] === 'paid'): ?>
                                <a href="/pages/invoice.php?id=<?= $pay['prescription_id'] ?>"
                                   class="btn-icon" title="View invoice">🧾</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div id="noPayResults" style="display:none;" class="empty-state">No payments match this filter.</div>
        <?php else: ?>
            <div class="empty-state">
                No payments recorded yet. Add a prescription and click <strong>Proceed to Payment</strong> to get started.
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ── New Payment Modal ──────────────────────────────── -->
<div class="modal-backdrop" id="newPayBackdrop"></div>
<div class="modal" id="newPayModal">
    <div class="modal-header">
        <h3 class="modal-title">Select Prescription to Pay</h3>
        <button class="modal-close" id="closeNewPay">&times;</button>
    </div>
    <div class="modal-body">
        <?php if ($unpaidRx): ?>
        <input type="text" id="unpaidSearch" class="form-control"
               placeholder="Search by customer or prescription #..."
               style="margin-bottom:14px;">
        <div style="display:flex;flex-direction:column;gap:6px;">
            <?php foreach ($unpaidRx as $urx):
                $ps = $urx['payment_status'] ?? null;
            ?>
            <a href="/pages/payment_form.php?rx=<?= $urx['prescription_id'] ?>"
               class="unpaid-rx-row"
               style="display:flex;align-items:center;justify-content:space-between;
                      padding:12px 14px;border:1px solid var(--border);border-radius:8px;
                      text-decoration:none;color:var(--text);gap:10px;transition:background .12s;"
               onmouseover="this.style.background='var(--bg)'" onmouseout="this.style.background=''">
                <div>
                    <strong>RX-<?= $urx['prescription_id'] ?></strong>
                    — <?= htmlspecialchars($urx['customer_name']) ?>
                    <div style="font-size:12px;color:var(--text-muted);margin-top:2px;">
                        <?= date('d M Y', strtotime($urx['created_at'])) ?>
                        &bull; Prescription: <span style="text-transform:capitalize;"><?= $urx['status'] ?></span>
                    </div>
                </div>
                <div style="display:flex;align-items:center;gap:8px;">
                    <?php if (!$ps): ?>
                        <span class="badge" style="background:#f3f4f6;color:#6b7280;">No Payment</span>
                    <?php else: ?>
                        <span class="badge" style="background:#fee2e2;color:#991b1b;">Unpaid</span>
                    <?php endif; ?>
                    <span style="color:var(--primary);font-size:18px;">&rsaquo;</span>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
            <div class="empty-state">All prescriptions have been paid or are awaiting pickup. Nothing pending.</div>
        <?php endif; ?>
    </div>
</div>

<style>
.tab-btn { font-size:13px; padding:7px 14px; border-radius:8px; }
.tab-btn.active { background:var(--primary) !important; color:#fff !important; }
</style>

<script>
// ── Status filter tabs ───────────────────────────────────
document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        const filter = btn.dataset.filter;
        const rows   = document.querySelectorAll('#paymentsTable tbody tr');
        let visible  = 0;
        rows.forEach(row => {
            const show = filter === 'all' || row.dataset.status === filter;
            row.style.display = show ? '' : 'none';
            if (show) visible++;
        });
        const noRes = document.getElementById('noPayResults');
        if (noRes) noRes.style.display = visible === 0 ? 'block' : 'none';
    });
});

// ── New Payment modal ────────────────────────────────────
document.getElementById('openNewPayment').addEventListener('click', () => {
    document.getElementById('newPayBackdrop').classList.add('open');
    document.getElementById('newPayModal').classList.add('open');
});
document.getElementById('newPayBackdrop').addEventListener('click', closeNewPay);
document.getElementById('closeNewPay').addEventListener('click', closeNewPay);
function closeNewPay() {
    document.getElementById('newPayBackdrop').classList.remove('open');
    document.getElementById('newPayModal').classList.remove('open');
}

// Search within the unpaid list
document.getElementById('unpaidSearch').addEventListener('input', function () {
    const q = this.value.toLowerCase().trim();
    document.querySelectorAll('.unpaid-rx-row').forEach(row => {
        row.style.display = !q || row.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
