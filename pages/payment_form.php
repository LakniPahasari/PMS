<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';
requireLogin();

$rxId = (int)($_GET['rx'] ?? 0);
if ($rxId <= 0) { header('Location: /pages/payments.php'); exit; }

$db = getDB();

// Fetch prescription + customer
$stmt = $db->prepare("
    SELECT p.prescription_id, p.status, p.created_at,
           c.customer_id, c.name AS customer_name, c.date_of_birth, c.email, c.address
    FROM PRESCRIPTION p
    JOIN CUSTOMER c ON c.customer_id = p.customer_id
    WHERE p.prescription_id = ?
    LIMIT 1
");
$stmt->execute([$rxId]);
$rx = $stmt->fetch();
if (!$rx) { header('Location: /pages/payments.php'); exit; }

// Fetch line items with prices
$itemStmt = $db->prepare("
    SELECT pi.quantity, pi.dosage, m.medication_name, m.unit_price
    FROM PRESCRIPTION_ITEM pi
    JOIN MEDICINE_STOCK m ON m.stock_id = pi.stock_id
    WHERE pi.prescription_id = ?
    ORDER BY m.medication_name
");
$itemStmt->execute([$rxId]);
$lineItems = $itemStmt->fetchAll();

$calculatedTotal = 0.0;
foreach ($lineItems as $li) {
    $calculatedTotal += (float)($li['unit_price'] ?? 0) * (int)$li['quantity'];
}

// Check if payment already exists for this prescription
$payStmt = $db->prepare("SELECT * FROM PAYMENT WHERE prescription_id = ? LIMIT 1");
$payStmt->execute([$rxId]);
$existing = $payStmt->fetch();
$isEdit   = (bool)$existing;

$pageTitle = 'Payment — Prescription #' . $rxId;
require_once __DIR__ . '/../includes/header.php';
?>

<style>
.pay-grid {
    display: grid;
    grid-template-columns: 1fr 380px;
    gap: 24px;
    align-items: start;
}
@media (max-width: 820px) {
    .pay-grid { grid-template-columns: 1fr; }
}
.pay-summary-table { width: 100%; border-collapse: collapse; font-size: 13.5px; }
.pay-summary-table th {
    text-align: left; padding: 8px 12px;
    background: var(--bg); color: var(--text-muted);
    font-size: 11px; text-transform: uppercase; letter-spacing: .5px;
    border-bottom: 1px solid var(--border);
}
.pay-summary-table td { padding: 9px 12px; border-bottom: 1px solid var(--border); }
.pay-summary-table tfoot td {
    padding: 10px 12px; font-weight: 700; font-size: 15px;
    border-top: 2px solid var(--border); border-bottom: none;
}
.pay-total-label { color: var(--text-muted); text-align: right; }
.pay-total-value { color: var(--primary); }

.method-btns { display: flex; gap: 10px; }
.method-btn {
    flex: 1; padding: 10px; border: 2px solid var(--border);
    border-radius: 8px; background: #fff; cursor: pointer;
    text-align: center; font-size: 13px; font-weight: 600;
    color: var(--text-muted); transition: all .15s;
}
.method-btn:hover { border-color: var(--primary); color: var(--primary); }
.method-btn.selected { border-color: var(--primary); background: #eff6ff; color: var(--primary); }
.method-btn input { display: none; }

.status-options { display: flex; flex-direction: column; gap: 8px; }
.status-opt {
    display: flex; align-items: center; gap: 10px;
    padding: 10px 14px; border: 2px solid var(--border);
    border-radius: 8px; cursor: pointer; font-size: 13px; font-weight: 500;
    transition: all .15s;
}
.status-opt:hover { border-color: var(--primary); }
.status-opt.selected-unpaid    { border-color: #ef4444; background: #fef2f2; color: #991b1b; }
.status-opt.selected-awaiting  { border-color: #f59e0b; background: #fffbeb; color: #92400e; }
.status-opt.selected-paid      { border-color: #10b981; background: #ecfdf5; color: #065f46; }
.status-opt input { accent-color: var(--primary); }
</style>

<!-- Toolbar -->
<div style="display:flex; align-items:center; gap:12px; margin-bottom:20px;">
    <a href="/pages/prescriptions.php" class="btn" style="background:var(--border);color:var(--text);">&larr; Back</a>
    <h2 style="font-size:17px; font-weight:700; margin:0;">
        <?= $isEdit ? 'Update Payment' : 'Record Payment' ?> — Prescription #<?= $rxId ?>
    </h2>
    <?php if ($isEdit): ?>
        <span class="badge" style="<?= [
            'unpaid'          => 'background:#fee2e2;color:#991b1b',
            'awaiting_pickup' => 'background:#fef3c7;color:#92400e',
            'paid'            => 'background:#d1fae5;color:#065f46',
        ][$existing['payment_status']] ?? '' ?>">
            <?= ucwords(str_replace('_', ' ', $existing['payment_status'])) ?>
        </span>
    <?php endif; ?>
</div>

<div class="pay-grid">

    <!-- Left: Prescription summary -->
    <div class="card">
        <!-- Patient + prescription meta -->
        <div style="padding:20px 20px 16px;border-bottom:1px solid var(--border);display:flex;gap:32px;flex-wrap:wrap;">
            <div>
                <div style="font-size:10px;text-transform:uppercase;letter-spacing:.7px;color:var(--text-muted);margin-bottom:5px;">Patient</div>
                <div style="font-weight:700;font-size:15px;margin-bottom:2px;"><?= htmlspecialchars($rx['customer_name']) ?></div>
                <?php if ($rx['email']): ?>
                    <div style="color:var(--text-muted);font-size:13px;"><?= htmlspecialchars($rx['email']) ?></div>
                <?php endif; ?>
                <?php if ($rx['date_of_birth']): ?>
                    <div style="color:var(--text-muted);font-size:13px;">DOB: <?= date('d M Y', strtotime($rx['date_of_birth'])) ?></div>
                <?php endif; ?>
            </div>
            <div>
                <div style="font-size:10px;text-transform:uppercase;letter-spacing:.7px;color:var(--text-muted);margin-bottom:5px;">Prescription</div>
                <div style="font-size:13px;font-weight:600;">#<?= $rxId ?></div>
                <div style="font-size:13px;color:var(--text-muted);"><?= date('d M Y', strtotime($rx['created_at'])) ?></div>
                <div style="font-size:13px;color:var(--text-muted);text-transform:capitalize;"><?= $rx['status'] ?></div>
            </div>
        </div>

        <!-- Medicines table -->
        <div class="card-body">
        <table class="pay-summary-table">
                <thead>
                    <tr>
                        <th>Medication</th>
                        <th>Dosage</th>
                        <th style="text-align:center;">Qty</th>
                        <th style="text-align:right;">Unit Price</th>
                        <th style="text-align:right;">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lineItems as $li):
                        $unit = (float)($li['unit_price'] ?? 0);
                        $sub  = $unit * (int)$li['quantity'];
                    ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($li['medication_name']) ?></strong></td>
                        <td style="color:var(--text-muted);"><?= $li['dosage'] ? htmlspecialchars($li['dosage']) : '—' ?></td>
                        <td style="text-align:center;"><?= (int)$li['quantity'] ?></td>
                        <td style="text-align:right;"><?= $unit > 0 ? '£'.number_format($unit,2) : '—' ?></td>
                        <td style="text-align:right;"><?= $sub > 0 ? '£'.number_format($sub,2) : '—' ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="4" class="pay-total-label">Total</td>
                        <td class="pay-total-value"><?= $calculatedTotal > 0 ? '£'.number_format($calculatedTotal,2) : '—' ?></td>
                    </tr>
                </tfoot>
            </table>
        </div><!-- /.card-body -->
    </div>

    <!-- Right: Payment form -->
    <div class="card">
        <div style="padding:20px;">
            <div class="toast" id="payToast" style="margin-bottom:12px;"></div>

            <form id="paymentForm" novalidate>
                <input type="hidden" name="prescription_id" value="<?= $rxId ?>">
                <input type="hidden" name="customer_id"     value="<?= $rx['customer_id'] ?>">
                <?php if ($isEdit): ?>
                    <input type="hidden" name="payment_id" value="<?= $existing['payment_id'] ?>">
                <?php endif; ?>

                <!-- Status -->
                <div class="form-group" style="margin-bottom:20px;">
                    <label class="form-label">Payment Status <span class="required">*</span></label>
                    <div class="status-options">
                        <?php
                        $curStatus = $existing['payment_status'] ?? 'unpaid';
                        $statuses  = [
                            'unpaid'          => ['label' => 'Unpaid',           'icon' => '🔴', 'cls' => 'selected-unpaid'],
                            'awaiting_pickup' => ['label' => 'Awaiting Pickup',  'icon' => '🟡', 'cls' => 'selected-awaiting'],
                            'paid'            => ['label' => 'Paid',             'icon' => '🟢', 'cls' => 'selected-paid'],
                        ];
                        foreach ($statuses as $val => $s): ?>
                        <label class="status-opt <?= $curStatus === $val ? $s['cls'] : '' ?>" data-val="<?= $val ?>" data-cls="<?= $s['cls'] ?>">
                            <input type="radio" name="payment_status" value="<?= $val ?>"
                                <?= $curStatus === $val ? 'checked' : '' ?> required>
                            <span><?= $s['icon'] ?></span>
                            <span><?= $s['label'] ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Payment Method (shown when paid or card/cash) -->
                <div class="form-group" id="methodGroup" style="margin-bottom:20px;<?= $curStatus !== 'paid' ? 'display:none;' : '' ?>">
                    <label class="form-label">Payment Method</label>
                    <div class="method-btns">
                        <?php $curMethod = $existing['payment_method'] ?? ''; ?>
                        <label class="method-btn <?= $curMethod === 'cash' ? 'selected' : '' ?>" id="cashBtn">
                            <input type="radio" name="payment_method" value="cash" <?= $curMethod === 'cash' ? 'checked' : '' ?>>
                            💵 Cash
                        </label>
                        <label class="method-btn <?= $curMethod === 'card' ? 'selected' : '' ?>" id="cardBtn">
                            <input type="radio" name="payment_method" value="card" <?= $curMethod === 'card' ? 'checked' : '' ?>>
                            💳 Card
                        </label>
                    </div>
                </div>

                <!-- Amount -->
                <div class="form-group">
                    <label class="form-label">Amount (£)</label>
                    <input type="number" name="amount" id="amountInput" class="form-control"
                           min="0" step="0.01"
                           value="<?= $isEdit ? number_format((float)$existing['amount'], 2, '.', '') : ($calculatedTotal > 0 ? number_format($calculatedTotal, 2, '.', '') : '') ?>"
                           placeholder="<?= $calculatedTotal > 0 ? number_format($calculatedTotal, 2) : '0.00' ?>">
                    <?php if ($calculatedTotal > 0): ?>
                    <div style="font-size:12px;color:var(--text-muted);margin-top:4px;">
                        Calculated from medicines: £<?= number_format($calculatedTotal, 2) ?>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Notes -->
                <div class="form-group">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" class="form-control" rows="2"
                        placeholder="Optional payment notes..."><?= htmlspecialchars($existing['notes'] ?? '') ?></textarea>
                </div>

                <div style="display:flex; gap:10px; margin-top:20px;">
                    <a href="/pages/prescriptions.php" class="btn"
                        style="background:var(--border);color:var(--text);">Cancel</a>
                    <button type="submit" class="btn btn-primary" id="paySubmitBtn" style="flex:1;">
                        <?= $isEdit ? 'Update Payment' : 'Record Payment' ?>
                    </button>
                </div>
            </form>
        </div><!-- /padding wrapper -->
    </div>
</div>

<script>
// ── Status option highlight ──────────────────────────────
const statusOpts = document.querySelectorAll('.status-opt');
const methodGroup = document.getElementById('methodGroup');

statusOpts.forEach(opt => {
    opt.addEventListener('click', () => {
        statusOpts.forEach(o => o.className = 'status-opt');
        opt.classList.add(opt.dataset.cls);
        methodGroup.style.display = opt.dataset.val === 'paid' ? 'block' : 'none';
        if (opt.dataset.val !== 'paid') {
            document.querySelectorAll('[name="payment_method"]').forEach(r => r.checked = false);
            document.querySelectorAll('.method-btn').forEach(b => b.classList.remove('selected'));
        }
    });
});

// ── Method button highlight ──────────────────────────────
document.querySelectorAll('.method-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.method-btn').forEach(b => b.classList.remove('selected'));
        btn.classList.add('selected');
    });
});

// ── Form submit ──────────────────────────────────────────
document.getElementById('paymentForm').addEventListener('submit', async e => {
    e.preventDefault();
    const btn  = document.getElementById('paySubmitBtn');
    btn.disabled = true; btn.textContent = 'Saving...';

    const action = <?= $isEdit ? "'/actions/edit_payment.php'" : "'/actions/add_payment.php'" ?>;

    try {
        const res  = await fetch(action, { method: 'POST', body: new FormData(e.target) });
        const data = await res.json();
        const toast = document.getElementById('payToast');
        toast.textContent = data.message;
        toast.className   = 'toast show toast-' + (data.success ? 'success' : 'error');

        if (data.success) {
            setTimeout(() => { window.location.href = '/pages/payments.php'; }, 1200);
        } else {
            btn.disabled = false;
            btn.textContent = <?= $isEdit ? "'Update Payment'" : "'Record Payment'" ?>;
        }
    } catch {
        const toast = document.getElementById('payToast');
        toast.textContent = 'Unexpected error. Please try again.';
        toast.className   = 'toast show toast-error';
        btn.disabled = false;
        btn.textContent = <?= $isEdit ? "'Update Payment'" : "'Record Payment'" ?>;
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
