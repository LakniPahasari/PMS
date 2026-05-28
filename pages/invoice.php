<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';
requireLogin();

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    die('Invalid prescription ID.');
}

$db = getDB();

// Fetch prescription + customer + pharmacist
$stmt = $db->prepare("
    SELECT p.prescription_id, p.status, p.special_notes, p.next_refill_date,
           p.allergy_checked, p.age_id_verified, p.created_at, p.processed_at,
           c.name AS customer_name, c.date_of_birth, c.address, c.email,
           u.name AS pharmacist_name
    FROM PRESCRIPTION p
    JOIN CUSTOMER c ON c.customer_id = p.customer_id
    JOIN SYSTEM_USER u ON u.system_user_id = p.system_user_id
    WHERE p.prescription_id = ?
    LIMIT 1
");
$stmt->execute([$id]);
$rx = $stmt->fetch();

if (!$rx) {
    http_response_code(404);
    die('Prescription not found.');
}

// Fetch line items with unit price
$items = $db->prepare("
    SELECT pi.quantity, pi.dosage,
           m.medication_name, m.category, m.unit_price, m.batch_number
    FROM PRESCRIPTION_ITEM pi
    JOIN MEDICINE_STOCK m ON m.stock_id = pi.stock_id
    WHERE pi.prescription_id = ?
    ORDER BY m.medication_name
");
$items->execute([$id]);
$lineItems = $items->fetchAll();

// Total
$total = 0.0;
foreach ($lineItems as $li) {
    $total += (float)($li['unit_price'] ?? 0) * (int)$li['quantity'];
}

$pageTitle = 'Invoice #RX-' . str_pad($id, 5, '0', STR_PAD_LEFT);
require_once __DIR__ . '/../includes/header.php';
?>

<style>
/* ── Screen controls ──────────────────────────────────── */
.invoice-actions {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
    align-items: center;
}

/* ── Invoice card ─────────────────────────────────────── */
.invoice-wrap {
    background: #fff;
    border-radius: var(--radius);
    box-shadow: var(--shadow-md);
    max-width: 820px;
    margin: 0 auto;
    padding: 48px 52px;
}

.inv-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    border-bottom: 2px solid var(--primary);
    padding-bottom: 24px;
    margin-bottom: 28px;
}
.inv-brand { display: flex; flex-direction: column; gap: 4px; }
.inv-brand-name { font-size: 24px; font-weight: 800; color: var(--primary); letter-spacing: .5px; }
.inv-brand-sub  { font-size: 12px; color: var(--text-muted); }

.inv-meta { text-align: right; }
.inv-title { font-size: 28px; font-weight: 700; color: var(--text); letter-spacing: 1px; }
.inv-ref   { font-size: 13px; color: var(--text-muted); margin-top: 6px; }
.inv-ref span { font-weight: 600; color: var(--text); }

/* ── Details grid ─────────────────────────────────────── */
.inv-details {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 24px;
    margin-bottom: 32px;
}
.inv-section-label {
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .8px;
    color: var(--text-muted);
    margin-bottom: 8px;
}
.inv-field { font-size: 13.5px; color: var(--text); line-height: 1.7; }
.inv-field strong { font-weight: 600; }

/* ── Items table ──────────────────────────────────────── */
.inv-table-wrap { margin-bottom: 24px; }
.inv-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13.5px;
}
.inv-table thead tr {
    background: var(--primary);
    color: #fff;
}
.inv-table thead th {
    padding: 10px 14px;
    text-align: left;
    font-weight: 600;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: .4px;
}
.inv-table thead th:last-child { text-align: right; }
.inv-table tbody td {
    padding: 10px 14px;
    border-bottom: 1px solid var(--border);
    vertical-align: top;
}
.inv-table tbody td:last-child { text-align: right; font-weight: 500; }
.inv-table tbody tr:last-child td { border-bottom: none; }
.inv-table tfoot td {
    padding: 10px 14px;
    border-top: 2px solid var(--border);
    font-size: 13.5px;
}
.inv-table tfoot .total-label {
    text-align: right;
    color: var(--text-muted);
    font-weight: 600;
}
.inv-table tfoot .total-value {
    text-align: right;
    font-size: 17px;
    font-weight: 700;
    color: var(--primary);
}
.med-sub { font-size: 11.5px; color: var(--text-muted); margin-top: 2px; }

/* ── Footer strip ─────────────────────────────────────── */
.inv-footer {
    border-top: 1px solid var(--border);
    padding-top: 20px;
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    gap: 20px;
    margin-top: 28px;
}
.inv-notes { font-size: 12.5px; color: var(--text-muted); max-width: 60%; line-height: 1.6; }
.inv-sig { text-align: right; font-size: 12px; color: var(--text-muted); }
.inv-sig strong { display: block; color: var(--text); font-size: 13px; margin-bottom: 2px; }

/* ── Checks row ───────────────────────────────────────── */
.inv-checks {
    display: flex;
    gap: 24px;
    margin-bottom: 28px;
}
.inv-check-item {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 12.5px;
    color: var(--text-muted);
}
.inv-check-item .chk { font-size: 14px; }

/* ── Print styles ─────────────────────────────────────── */
@media print {
    .invoice-actions,
    .sidebar,
    .top-header,
    .main-wrapper > .top-header { display: none !important; }

    body { background: #fff; }
    .main-wrapper { margin-left: 0 !important; }
    .main-content { padding: 0 !important; }

    .invoice-wrap {
        box-shadow: none;
        border-radius: 0;
        max-width: 100%;
        padding: 32px 40px;
    }

    .inv-table tbody tr:nth-child(even) { background: #f9fafb; }
}
</style>

<!-- Screen toolbar -->
<div class="invoice-actions no-print">
    <a href="/pages/prescriptions.php" class="btn" style="background:var(--border);color:var(--text);">&larr; Back to Prescriptions</a>
    <button class="btn btn-primary" onclick="window.print()">🖨 Print / Save PDF</button>
</div>

<!-- Invoice -->
<div class="invoice-wrap" id="invoicePrintArea">

    <!-- Header -->
    <div class="inv-header">
        <div class="inv-brand">
            <span class="inv-brand-name">Drugs 4U</span>
            <span class="inv-brand-sub">Staffordshire, UK</span>
            <span class="inv-brand-sub">Registered Pharmacy &bull; PharmaTrack PMS</span>
        </div>
        <div class="inv-meta">
            <div class="inv-title">INVOICE</div>
            <div class="inv-ref">Invoice No: <span>RX-<?= str_pad($id, 5, '0', STR_PAD_LEFT) ?></span></div>
            <div class="inv-ref">Date Issued: <span><?= date('d M Y', strtotime($rx['processed_at'] ?? $rx['created_at'])) ?></span></div>
            <div class="inv-ref">Prescription #: <span><?= $rx['prescription_id'] ?></span></div>
        </div>
    </div>

    <!-- Bill-to + Prescription Info -->
    <div class="inv-details">
        <div>
            <div class="inv-section-label">Billed To</div>
            <div class="inv-field">
                <strong><?= htmlspecialchars($rx['customer_name']) ?></strong><br>
                <?php if ($rx['date_of_birth']): ?>
                    DOB: <?= date('d M Y', strtotime($rx['date_of_birth'])) ?><br>
                <?php endif; ?>
                <?php if ($rx['address']): ?>
                    <?= htmlspecialchars($rx['address']) ?><br>
                <?php endif; ?>
                <?php if ($rx['email']): ?>
                    <?= htmlspecialchars($rx['email']) ?>
                <?php endif; ?>
            </div>
        </div>
        <div>
            <div class="inv-section-label">Prescription Details</div>
            <div class="inv-field">
                Dispensed by: <strong><?= htmlspecialchars($rx['pharmacist_name']) ?></strong><br>
                Created: <?= date('d M Y', strtotime($rx['created_at'])) ?><br>
                Processed: <?= date('d M Y H:i', strtotime($rx['processed_at'])) ?><br>
                <?php if ($rx['next_refill_date']): ?>
                    Next Refill: <strong><?= date('d M Y', strtotime($rx['next_refill_date'])) ?></strong>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Compliance checks -->
    <div class="inv-checks">
        <div class="inv-check-item">
            <span class="chk"><?= $rx['allergy_checked'] ? '✅' : '⬜' ?></span>
            Allergy checked
        </div>
        <div class="inv-check-item">
            <span class="chk"><?= $rx['age_id_verified'] ? '✅' : '⬜' ?></span>
            ID / Age verified
        </div>
        <div class="inv-check-item">
            <span class="chk">✅</span>
            Status: <strong style="color:#065f46;margin-left:4px;">Processed</strong>
        </div>
    </div>

    <!-- Line items -->
    <div class="inv-table-wrap">
        <table class="inv-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Medication</th>
                    <th>Dosage</th>
                    <th>Qty</th>
                    <th>Unit Price</th>
                    <th>Subtotal</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($lineItems as $i => $li):
                    $unitPrice = (float)($li['unit_price'] ?? 0);
                    $subtotal  = $unitPrice * (int)$li['quantity'];
                ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td>
                        <strong><?= htmlspecialchars($li['medication_name']) ?></strong>
                        <?php if ($li['category']): ?>
                            <div class="med-sub"><?= htmlspecialchars($li['category']) ?></div>
                        <?php endif; ?>
                        <?php if ($li['batch_number']): ?>
                            <div class="med-sub">Batch: <?= htmlspecialchars($li['batch_number']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td><?= $li['dosage'] ? htmlspecialchars($li['dosage']) : '<span style="color:var(--text-muted)">—</span>' ?></td>
                    <td><?= (int)$li['quantity'] ?></td>
                    <td><?= $unitPrice > 0 ? '£' . number_format($unitPrice, 2) : '<span style="color:var(--text-muted)">—</span>' ?></td>
                    <td><?= $subtotal > 0 ? '£' . number_format($subtotal, 2) : '<span style="color:var(--text-muted)">—</span>' ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="5" class="total-label">Total Amount</td>
                    <td class="total-value"><?= $total > 0 ? '£' . number_format($total, 2) : '—' ?></td>
                </tr>
            </tfoot>
        </table>
    </div>

    <!-- Footer: notes + signature -->
    <div class="inv-footer">
        <div class="inv-notes">
            <?php if ($rx['special_notes']): ?>
                <strong style="color:var(--text);display:block;margin-bottom:4px;">Pharmacist Notes</strong>
                <?= htmlspecialchars($rx['special_notes']) ?>
            <?php else: ?>
                <em>No additional notes.</em>
            <?php endif; ?>
            <div style="margin-top:10px;font-size:11px;">
                This invoice is computer generated and does not require a physical signature.
                Please retain for your records.
            </div>
        </div>
        <div class="inv-sig">
            <strong><?= htmlspecialchars($rx['pharmacist_name']) ?></strong>
            Dispensing Pharmacist<br>
            Drugs 4U, Staffordshire
        </div>
    </div>

</div><!-- /.invoice-wrap -->

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
