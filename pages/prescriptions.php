<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';
requireLogin();

$db   = getDB();
$user = currentUser();

$prescriptions = $db->query("
    SELECT p.prescription_id, p.status, p.allergy_checked, p.age_id_verified,
           p.special_notes, p.rejection_reason, p.next_refill_date, p.created_at, p.processed_at,
           c.name AS customer_name, c.customer_id, c.allergies AS customer_allergies,
           GROUP_CONCAT(m.medication_name ORDER BY m.medication_name SEPARATOR ', ') AS medicines,
           MAX(m.requires_id_check) AS requires_id_check,
           MAX(pay.payment_status) AS payment_status,
           MAX(pay.payment_id)     AS payment_id
    FROM PRESCRIPTION p
    JOIN CUSTOMER c           ON c.customer_id     = p.customer_id
    JOIN PRESCRIPTION_ITEM pi ON pi.prescription_id = p.prescription_id
    JOIN MEDICINE_STOCK m     ON m.stock_id         = pi.stock_id
    LEFT JOIN PAYMENT pay     ON pay.prescription_id = p.prescription_id
    GROUP BY p.prescription_id
    ORDER BY p.created_at DESC
")->fetchAll();

// Pre-load all prescription line items (for view modal, edit pre-fill, validation)
$itemRows = $db->query("
    SELECT pi.prescription_id, pi.stock_id, pi.quantity AS prescribed_qty, pi.dosage,
           m.medication_name, m.category, m.requires_id_check, m.quantity AS stock_qty
    FROM PRESCRIPTION_ITEM pi
    JOIN MEDICINE_STOCK m ON m.stock_id = pi.stock_id
    ORDER BY pi.prescription_id, m.medication_name
")->fetchAll();

$rxItems = [];
foreach ($itemRows as $row) {
    $rxItems[$row['prescription_id']][] = $row;
}

// Build per-prescription data for JS (customer allergies + items)
$rxData = [];
foreach ($prescriptions as $rx) {
    $rxData[$rx['prescription_id']] = [
        'customer_allergies' => $rx['customer_allergies'] ?? '',
        'age_id_verified'    => (bool)$rx['age_id_verified'],
        'status'             => $rx['status'],
        'payment_status'     => $rx['payment_status'] ?? null,
    ];
}

$total = count($prescriptions);
$pending = $approved = $processed = $flagged = 0;
foreach ($prescriptions as $rx) {
    if ($rx['status'] === 'pending')   $pending++;
    if ($rx['status'] === 'approved')  $approved++;
    if ($rx['status'] === 'processed') $processed++;
    if ($rx['requires_id_check'] && !$rx['age_id_verified']) $flagged++;
}

$customers = $db->query("
    SELECT customer_id, name, allergies
    FROM CUSTOMER WHERE account_active = 1 ORDER BY name
")->fetchAll();

$medicines = $db->query("
    SELECT stock_id, medication_name, quantity, requires_id_check, expiry_date
    FROM MEDICINE_STOCK WHERE is_active = 1 ORDER BY medication_name
")->fetchAll();

$pageTitle = 'Prescriptions';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Stat cards -->
<div class="stats-grid">
    <div class="stat-card blue">
        <div class="stat-icon">📋</div>
        <div><div class="stat-value"><?= $total ?></div><div class="stat-label">Total</div></div>
    </div>
    <div class="stat-card yellow">
        <div class="stat-icon">⏳</div>
        <div><div class="stat-value"><?= $pending ?></div><div class="stat-label">Pending</div></div>
    </div>
    <div class="stat-card green">
        <div class="stat-icon">✅</div>
        <div><div class="stat-value"><?= $approved ?></div><div class="stat-label">Approved</div></div>
    </div>
    <div class="stat-card red">
        <div class="stat-icon">🔔</div>
        <div><div class="stat-value"><?= $flagged ?></div><div class="stat-label">ID Pending</div></div>
    </div>
</div>

<!-- Toolbar -->
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;">
    <span style="color:var(--text-muted);font-size:13px;"><?= $total ?> prescription<?= $total !== 1 ? 's' : '' ?> total</span>
    <button class="btn btn-primary" id="openAddPrescription">+ New Prescription</button>
</div>

<!-- Table -->
<div class="card">
    <div class="card-body">
        <?php if ($prescriptions): ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Customer</th>
                        <th>Medicines</th>
                        <th>Status</th>
                        <th>Payment</th>
                        <th>Allergy</th>
                        <th>ID Verified</th>
                        <th>Date</th>
                        <th style="width:110px;text-align:center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($prescriptions as $rx): ?>
                    <tr>
                        <td>#<?= $rx['prescription_id'] ?></td>
                        <td><?= htmlspecialchars($rx['customer_name']) ?></td>
                        <td>
                            <?php
                            $medList = explode(', ', $rx['medicines']);
                            echo htmlspecialchars($medList[0]);
                            if (count($medList) > 1)
                                echo ' <span style="color:var(--text-muted);font-size:12px;">+' . (count($medList) - 1) . ' more</span>';
                            ?>
                            <?php if ($rx['requires_id_check']): ?>
                                <span class="badge badge-age_restriction" style="margin-left:4px;">ID Req.</span>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge badge-<?= $rx['status'] ?>"><?= $rx['status'] ?></span></td>
                        <td>
                            <?php
                            $ps = $rx['payment_status'] ?? null;
                            if ($ps === 'paid'):
                            ?><span class="badge" style="background:#d1fae5;color:#065f46;">Paid</span><?php
                            elseif ($ps === 'awaiting_pickup'):
                            ?><span class="badge" style="background:#fef3c7;color:#92400e;">Awaiting</span><?php
                            elseif ($ps === 'unpaid'):
                            ?><a href="/pages/payment_form.php?rx=<?= $rx['prescription_id'] ?>" class="badge" style="background:#fee2e2;color:#991b1b;text-decoration:none;">Unpaid →</a><?php
                            else:
                            ?><a href="/pages/payment_form.php?rx=<?= $rx['prescription_id'] ?>" class="badge" style="background:#f3f4f6;color:#6b7280;text-decoration:none;">No Payment →</a><?php
                            endif; ?>
                        </td>
                        <td style="text-align:center;">
                            <?= $rx['allergy_checked'] ? '<span style="color:var(--success);">✓</span>' : '<span style="color:var(--text-muted);">—</span>' ?>
                        </td>
                        <td style="text-align:center;">
                            <?php if ($rx['requires_id_check']): ?>
                                <?= $rx['age_id_verified'] ? '<span style="color:var(--success);">✓</span>' : '<span class="badge badge-age_restriction">Pending</span>' ?>
                            <?php else: ?>
                                <span style="color:var(--text-muted);">N/A</span>
                            <?php endif; ?>
                        </td>
                        <td><?= date('d M Y', strtotime($rx['created_at'])) ?></td>
                        <td style="text-align:center;display:flex;gap:4px;justify-content:center;">
                            <button class="btn-icon view-btn" title="View"
                                data-id="<?= $rx['prescription_id'] ?>"
                                data-customer="<?= htmlspecialchars($rx['customer_name'], ENT_QUOTES) ?>"
                                data-status="<?= $rx['status'] ?>"
                                data-allergy="<?= $rx['allergy_checked'] ?>"
                                data-idverified="<?= $rx['age_id_verified'] ?>"
                                data-notes="<?= htmlspecialchars($rx['special_notes'] ?? '', ENT_QUOTES) ?>"
                                data-rejection="<?= htmlspecialchars($rx['rejection_reason'] ?? '', ENT_QUOTES) ?>"
                                data-refill="<?= $rx['next_refill_date'] ?? '' ?>"
                                data-date="<?= date('d M Y', strtotime($rx['created_at'])) ?>"
                                data-processed="<?= $rx['processed_at'] ? date('d M Y H:i', strtotime($rx['processed_at'])) : '' ?>">
                                👁
                            </button>
                            <button class="btn-icon edit-btn" title="Edit"
                                data-id="<?= $rx['prescription_id'] ?>"
                                data-customer="<?= htmlspecialchars($rx['customer_name'], ENT_QUOTES) ?>"
                                data-status="<?= $rx['status'] ?>"
                                data-allergy="<?= $rx['allergy_checked'] ?>"
                                data-idverified="<?= $rx['age_id_verified'] ?>"
                                data-idreq="<?= $rx['requires_id_check'] ?>"
                                data-notes="<?= htmlspecialchars($rx['special_notes'] ?? '', ENT_QUOTES) ?>"
                                data-rejection="<?= htmlspecialchars($rx['rejection_reason'] ?? '', ENT_QUOTES) ?>"
                                data-refill="<?= $rx['next_refill_date'] ?? '' ?>"
                                data-payment="<?= $rx['payment_status'] ?? '' ?>">
                                ✏️
                            </button>
                            <?php if ($rx['status'] === 'processed'): ?>
                            <a class="btn-icon" title="Invoice"
                               href="/pages/invoice.php?id=<?= $rx['prescription_id'] ?>">
                               🧾
                            </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
            <div class="empty-state">No prescriptions yet.</div>
        <?php endif; ?>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════
     VIEW MODAL
════════════════════════════════════════════════════════ -->
<div class="modal-backdrop" id="viewBackdrop"></div>
<div class="modal" id="viewPrescriptionModal">
    <div class="modal-header">
        <h3 class="modal-title" id="viewModalTitle">Prescription Details</h3>
        <button class="modal-close" data-close="view">&times;</button>
    </div>
    <div class="modal-body">
        <div style="display:flex;gap:14px;flex-wrap:wrap;margin-bottom:16px;">
            <div style="flex:1;min-width:110px;">
                <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:3px;">Customer</div>
                <div style="font-weight:600;" id="view_customer"></div>
            </div>
            <div style="flex:1;min-width:90px;">
                <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:3px;">Status</div>
                <div id="view_status"></div>
            </div>
            <div style="flex:1;min-width:90px;">
                <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:3px;">Date</div>
                <div id="view_date"></div>
            </div>
            <div style="flex:1;min-width:90px;" id="view_processed_wrap">
                <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:3px;">Processed At</div>
                <div id="view_processed"></div>
            </div>
        </div>
        <div style="display:flex;gap:20px;margin-bottom:16px;flex-wrap:wrap;">
            <div style="display:flex;align-items:center;gap:6px;font-size:13px;"><span id="view_allergy_icon"></span> Allergy Checked</div>
            <div style="display:flex;align-items:center;gap:6px;font-size:13px;"><span id="view_id_icon"></span> Age / ID Verified</div>
        </div>
        <div id="view_rejection_wrap" style="display:none;background:#fee2e2;border:1px solid #fca5a5;border-radius:8px;padding:10px 14px;margin-bottom:14px;font-size:13px;color:#991b1b;">
            <strong>Rejection Reason:</strong> <span id="view_rejection"></span>
        </div>
        <div style="font-size:11px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px;">Medicines</div>
        <div class="table-wrap" style="margin-bottom:16px;">
            <table>
                <thead><tr><th>Medication</th><th>Dosage</th><th>Qty</th><th>ID Check</th></tr></thead>
                <tbody id="viewItemsBody"></tbody>
            </table>
        </div>
        <div id="viewRefillWrap" style="margin-bottom:12px;font-size:13px;display:none;">
            <strong>Next Refill:</strong> <span id="view_refill"></span>
        </div>
        <div id="viewNotesWrap" style="display:none;">
            <div style="font-size:11px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px;">Special Notes</div>
            <div id="view_notes" style="background:var(--bg);border-radius:8px;padding:10px 14px;font-size:13px;"></div>
        </div>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn" data-close="view" style="background:var(--border);color:var(--text);">Close</button>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════
     ADD MODAL
════════════════════════════════════════════════════════ -->
<div class="modal-backdrop" id="addBackdrop"></div>
<div class="modal" id="addPrescriptionModal">
    <div class="modal-header">
        <h3 class="modal-title">New Prescription</h3>
        <button class="modal-close" data-close="add">&times;</button>
    </div>
    <div class="toast" id="addToast"></div>
    <form id="addPrescriptionForm" novalidate>
        <div class="modal-body">
            <div class="form-group">
                <label class="form-label">Customer <span class="required">*</span></label>
                <select name="customer_id" id="add_customer" class="form-control" required>
                    <option value="">— Select customer —</option>
                    <?php foreach ($customers as $c): ?>
                    <option value="<?= $c['customer_id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div id="customerAllergyBox" style="display:none;background:#fee2e2;border:1px solid #fca5a5;border-radius:8px;padding:10px 14px;margin-bottom:12px;font-size:13px;color:#991b1b;">
                <strong>⚠️ Customer Allergy Alert:</strong> <span id="customerAllergyText"></span>
            </div>

            <div style="margin-bottom:8px;"><label class="form-label">Medicines <span class="required">*</span></label></div>
            <div style="display:grid;grid-template-columns:2fr 65px 150px 36px;gap:6px;margin-bottom:6px;padding:0 2px;">
                <span style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.4px;">Medicine</span>
                <span style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.4px;">Qty</span>
                <span style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.4px;">Dosage / Instructions</span>
                <span></span>
            </div>
            <div id="addMedicineRows"></div>
            <button type="button" id="addMedRowBtn" class="btn btn-sm"
                style="background:var(--bg);border:1px dashed var(--border);color:var(--text-muted);margin-bottom:14px;">
                + Add Another Medicine
            </button>

            <div id="idCheckWarning" style="display:none;background:#ede9fe;border:1px solid #a78bfa;border-radius:8px;padding:10px 14px;margin-bottom:10px;font-size:13px;color:#5b21b6;">
                <strong>🪪 ID Verification Required:</strong> One or more medicines are age-restricted. You must confirm ID before submitting.
            </div>
            <div id="lowStockWarning" style="display:none;background:#fef3c7;border:1px solid #f59e0b;border-radius:8px;padding:10px 14px;margin-bottom:10px;font-size:13px;color:#92400e;">
                <strong>⚠️ Low Stock:</strong> One or more medicines have fewer than 10 units remaining.
            </div>
            <div id="medAllergyWarning" style="display:none;background:#fff7ed;border:1px solid #fdba74;border-radius:8px;padding:10px 14px;margin-bottom:10px;font-size:13px;color:#9a3412;">
                <strong>💊 Medicine Allergy Note:</strong> <span id="medAllergyText"></span>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Next Refill Date</label>
                    <input type="date" name="next_refill_date" class="form-control">
                </div>
                <div class="form-group" style="display:flex;flex-direction:column;gap:10px;justify-content:flex-end;">
                    <div>
                        <label class="form-label">Allergy Checked</label>
                        <label class="toggle-switch">
                            <input type="checkbox" name="allergy_checked" id="add_allergy">
                            <span class="toggle-track"><span class="toggle-thumb"></span></span>
                            <span class="toggle-label" id="addAllergyLabel">No</span>
                        </label>
                    </div>
                    <div id="idVerifyGroup" style="display:none;">
                        <label class="form-label">Age / ID Verified <span class="required">*</span></label>
                        <label class="toggle-switch">
                            <input type="checkbox" name="age_id_verified" id="add_idverified">
                            <span class="toggle-track"><span class="toggle-thumb"></span></span>
                            <span class="toggle-label" id="addIdVerLabel">No</span>
                        </label>
                    </div>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Special Notes</label>
                <textarea name="special_notes" class="form-control" rows="2" placeholder="Additional instructions..."></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn" data-close="add" style="background:var(--border);color:var(--text);">Cancel</button>
            <button type="submit" class="btn btn-primary">Save Prescription</button>
        </div>
    </form>
</div>

<!-- ═══════════════════════════════════════════════════════
     EDIT MODAL
════════════════════════════════════════════════════════ -->
<div class="modal-backdrop" id="editBackdrop"></div>
<div class="modal" id="editPrescriptionModal">
    <div class="modal-header">
        <h3 class="modal-title" id="editModalTitle">Edit Prescription</h3>
        <button class="modal-close" data-close="edit">&times;</button>
    </div>
    <div class="toast" id="editToast"></div>
    <form id="editPrescriptionForm" novalidate>
        <input type="hidden" name="prescription_id" id="edit_rx_id">
        <div class="modal-body">

            <!-- Customer summary -->
            <div style="background:var(--bg);border-radius:8px;padding:10px 14px;margin-bottom:16px;font-size:13px;">
                <strong>Customer:</strong> <span id="edit_rx_customer"></span>
            </div>

            <!-- Payment banner (shown when unpaid or no payment) -->
            <div id="editPaymentBanner" style="display:none;background:#fef3c7;border:1px solid #f59e0b;border-radius:8px;padding:10px 14px;margin-bottom:16px;align-items:center;justify-content:space-between;gap:10px;font-size:13px;">
                <span>💳 <strong>Payment not collected</strong> — this prescription is unpaid.</span>
                <a id="editPaymentLink" href="#" class="btn btn-primary" style="font-size:12px;padding:5px 12px;white-space:nowrap;">
                    Make Payment →
                </a>
            </div>

            <!-- Validation summary -->
            <div style="border:1px solid var(--border);border-radius:8px;margin-bottom:16px;overflow:hidden;">
                <div style="background:var(--bg);padding:8px 14px;font-size:11px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;border-bottom:1px solid var(--border);">
                    Validation Summary
                </div>
                <div id="validationContent" style="padding:12px 14px;font-size:13px;line-height:1.8;"></div>
            </div>

            <div class="form-group">
                <label class="form-label">Status</label>
                <select name="status" id="edit_status" class="form-control">
                    <option value="pending">Pending</option>
                    <option value="approved">Approved</option>
                    <option value="processed">Processed</option>
                    <option value="rejected">Rejected</option>
                </select>
            </div>

            <!-- Rejection reason (shown only when rejected) -->
            <div class="form-group" id="rejectionReasonGroup" style="display:none;">
                <label class="form-label">Rejection Reason <span class="required">*</span></label>
                <textarea name="rejection_reason" id="edit_rejection_reason" class="form-control" rows="2"
                    placeholder="Explain why this prescription is being rejected..."></textarea>
            </div>

            <!-- Medicines — always editable -->
            <div style="margin-bottom:8px;">
                <label class="form-label">Medicines</label>
            </div>
            <div style="display:grid;grid-template-columns:2fr 65px 150px 36px;gap:6px;margin-bottom:6px;padding:0 2px;">
                <span style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.4px;">Medicine</span>
                <span style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.4px;">Qty</span>
                <span style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.4px;">Dosage / Instructions</span>
                <span></span>
            </div>
            <div id="editMedicineRows"></div>
            <button type="button" id="addEditMedRowBtn" class="btn btn-sm"
                style="background:var(--bg);border:1px dashed var(--border);color:var(--text-muted);margin-bottom:14px;">
                + Add Another Medicine
            </button>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Next Refill Date</label>
                    <input type="date" name="next_refill_date" id="edit_refill" class="form-control">
                </div>
                <div class="form-group" style="display:flex;flex-direction:column;gap:12px;">
                    <div>
                        <label class="form-label">Allergy Checked</label>
                        <label class="toggle-switch">
                            <input type="checkbox" name="allergy_checked" id="edit_allergy">
                            <span class="toggle-track"><span class="toggle-thumb"></span></span>
                            <span class="toggle-label" id="editAllergyLabel">No</span>
                        </label>
                    </div>
                    <div id="editIdVerGroup">
                        <label class="form-label">Age / ID Verified</label>
                        <label class="toggle-switch">
                            <input type="checkbox" name="age_id_verified" id="edit_idverified">
                            <span class="toggle-track"><span class="toggle-thumb"></span></span>
                            <span class="toggle-label" id="editIdVerLabel">No</span>
                        </label>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Special Notes</label>
                <textarea name="special_notes" id="edit_notes" class="form-control" rows="2"></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn" data-close="edit" style="background:var(--border);color:var(--text);">Cancel</button>
            <button type="submit" class="btn btn-primary">Save Changes</button>
        </div>
    </form>
</div>

<script>
const customerData = <?= json_encode(array_column($customers, null, 'customer_id')) ?>;
const rxItems      = <?= json_encode($rxItems) ?>;
const rxData       = <?= json_encode($rxData) ?>;
const medicineData = <?= json_encode(array_column($medicines, null, 'stock_id')) ?>;

const medOptions = '<option value="">— Select medicine —</option>' +
    <?= json_encode(implode('', array_map(function($m) {
        $expired  = $m['expiry_date'] && new DateTime($m['expiry_date']) < new DateTime();
        $label    = htmlspecialchars($m['medication_name']);
        $stockTxt = $expired ? 'EXPIRED' : $m['quantity'] . ' in stock';
        return '<option value="' . $m['stock_id'] . '"'
            . ' data-idcheck="' . $m['requires_id_check'] . '"'
            . ' data-qty="'     . $m['quantity'] . '"'
            . ' data-expired="' . ($expired ? '1' : '0') . '"'
            . ($expired ? ' disabled' : '') . '>'
            . $label . ' (' . $stockTxt . ')'
            . ($expired ? ' ⛔' : '')
            . '</option>';
    }, $medicines))) ?>;

const statusStyle = {
    pending:   'background:#fef3c7;color:#92400e',
    approved:  'background:#d1fae5;color:#065f46',
    processed: 'background:#dbeafe;color:#1e40af',
    rejected:  'background:#fee2e2;color:#991b1b',
};

// ── Medicine row builders ────────────────────────────────
let addRowCount = 0;
function addMedicineRow(stockId = '', qty = '', dosage = '') {
    addRowCount++;
    const n = addRowCount;
    const div = document.createElement('div');
    div.className = 'add-med-row';
    div.style.cssText = 'display:grid;grid-template-columns:2fr 65px 150px 36px;gap:6px;margin-bottom:8px;align-items:center;';
    div.innerHTML = `
        <select name="stock_id[]" class="form-control add-med-select" required>${medOptions}</select>
        <input type="number" name="item_qty[]" class="form-control" min="1" placeholder="Qty" value="${qty}" required>
        <input type="text"   name="dosage[]"   class="form-control" placeholder="e.g. 500mg twice daily" value="${dosage}">
        <button type="button" class="btn-icon remove-add-row" style="color:var(--danger);font-size:16px;" title="Remove">✕</button>
    `;
    document.getElementById('addMedicineRows').appendChild(div);
    if (stockId) div.querySelector('.add-med-select').value = stockId;
    div.querySelector('.add-med-select').addEventListener('change', updateAddWarnings);
    div.querySelector('.remove-add-row').addEventListener('click', () => {
        if (document.querySelectorAll('.add-med-row').length > 1) { div.remove(); updateAddWarnings(); }
    });
}

function updateAddWarnings() {
    let needsId = false, hasLowStock = false;
    const allergyNotes = [];
    document.querySelectorAll('.add-med-select').forEach(sel => {
        const opt = sel.options[sel.selectedIndex];
        if (!opt || !opt.value) return;
        if (opt.dataset.idcheck === '1') needsId = true;
        if (parseInt(opt.dataset.qty || '0') < 10) hasLowStock = true;
        if (opt.dataset.allergy) allergyNotes.push(opt.dataset.allergy);
    });
    document.getElementById('idCheckWarning').style.display  = needsId     ? 'block' : 'none';
    document.getElementById('idVerifyGroup').style.display   = needsId     ? 'block' : 'none';
    document.getElementById('lowStockWarning').style.display = hasLowStock ? 'block' : 'none';
    const aw = document.getElementById('medAllergyWarning');
    if (allergyNotes.length) { document.getElementById('medAllergyText').textContent = allergyNotes.join(' | '); aw.style.display = 'block'; }
    else aw.style.display = 'none';
}

addMedicineRow();
document.getElementById('addMedRowBtn').addEventListener('click', () => addMedicineRow());

let editRowCount = 0;
function addEditMedicineRow(stockId = '', qty = '', dosage = '') {
    editRowCount++;
    const div = document.createElement('div');
    div.className = 'edit-med-row';
    div.style.cssText = 'display:grid;grid-template-columns:2fr 65px 150px 36px;gap:6px;margin-bottom:8px;align-items:center;';
    div.innerHTML = `
        <select name="stock_id[]" class="form-control edit-med-select" required>${medOptions}</select>
        <input type="number" name="item_qty[]" class="form-control" min="1" placeholder="Qty" value="${qty}" required>
        <input type="text"   name="dosage[]"   class="form-control" placeholder="e.g. 500mg twice daily" value="${dosage}">
        <button type="button" class="btn-icon remove-edit-row" style="color:var(--danger);font-size:16px;" title="Remove">✕</button>
    `;
    document.getElementById('editMedicineRows').appendChild(div);
    if (stockId) div.querySelector('.edit-med-select').value = stockId;
    div.querySelector('.remove-edit-row').addEventListener('click', () => {
        if (document.querySelectorAll('.edit-med-row').length > 1) div.remove();
    });
}
document.getElementById('addEditMedRowBtn').addEventListener('click', () => addEditMedicineRow());

// ── Validation summary builder ───────────────────────────
function buildValidationSummary(rxId, idVerified) {
    const info  = rxData[rxId] || {};
    const items = rxItems[rxId] || [];
    let html    = '';

    // Customer allergies
    if (info.customer_allergies) {
        html += `<div>⚠️ <strong>Customer Allergies:</strong> ${info.customer_allergies}</div>`;
    } else {
        html += `<div><span style="color:var(--success);">✓</span> No known allergies on record</div>`;
    }

    // Age / ID checks per medicine
    items.forEach(item => {
        if (item.requires_id_check) {
            const ok = idVerified;
            html += `<div>${ok ? '✅' : '❌'} <strong>ID Check:</strong> ${item.medication_name} — ${ok ? 'Verified' : '<span style="color:var(--danger);">Not yet verified</span>'}</div>`;
        }
    });

    // Stock levels per medicine
    items.forEach(item => {
        const med = medicineData[item.stock_id];
        if (!med) return;
        const inStock = parseInt(med.quantity);
        const needed  = parseInt(item.prescribed_qty);
        const ok      = inStock >= needed;
        const lowWarn = inStock < 10 ? ' ⚠️ Low' : '';
        html += `<div>${ok ? '✅' : '❌'} <strong>Stock:</strong> ${item.medication_name} — ${inStock} in stock, ${needed} needed${lowWarn}${!ok ? ' <span style="color:var(--danger);">— Insufficient</span>' : ''}</div>`;
    });

    document.getElementById('validationContent').innerHTML = html || '<div style="color:var(--text-muted)">No data available.</div>';
}

// ── Status change → show/hide rejection reason ───────────
document.getElementById('edit_status').addEventListener('change', function () {
    document.getElementById('rejectionReasonGroup').style.display = this.value === 'rejected' ? 'block' : 'none';
});

// ── Modal helpers ────────────────────────────────────────
function openModal(p) {
    document.getElementById(p + 'Backdrop').classList.add('open');
    document.getElementById(p + 'PrescriptionModal').classList.add('open');
}
function closeModal(p) {
    document.getElementById(p + 'Backdrop').classList.remove('open');
    document.getElementById(p + 'PrescriptionModal').classList.remove('open');
    if (p !== 'view') document.getElementById(p + 'PrescriptionForm').reset();
    const t = document.getElementById(p + 'Toast');
    if (t) { t.className = 'toast'; t.textContent = ''; }
    if (p === 'add') {
        document.getElementById('addMedicineRows').innerHTML = ''; addRowCount = 0; addMedicineRow();
        ['customerAllergyBox','idCheckWarning','lowStockWarning','medAllergyWarning','idVerifyGroup']
            .forEach(id => document.getElementById(id).style.display = 'none');
        document.getElementById('addAllergyLabel').textContent = 'No';
        document.getElementById('addIdVerLabel').textContent   = 'No';
    }
    if (p === 'edit') {
        document.getElementById('editMedicineRows').innerHTML = ''; editRowCount = 0;
        document.getElementById('rejectionReasonGroup').style.display = 'none';
    }
}
function showToast(p, msg, type) {
    const t = document.getElementById(p + 'Toast');
    t.textContent = msg; t.className = 'toast show toast-' + type;
}

document.getElementById('openAddPrescription').addEventListener('click', () => openModal('add'));
document.querySelectorAll('[data-close]').forEach(btn => btn.addEventListener('click', () => closeModal(btn.dataset.close)));
document.getElementById('addBackdrop').addEventListener('click',  () => closeModal('add'));
document.getElementById('editBackdrop').addEventListener('click', () => closeModal('edit'));
document.getElementById('viewBackdrop').addEventListener('click', () => closeModal('view'));

document.getElementById('add_customer').addEventListener('change', function () {
    const c = customerData[this.value];
    const box = document.getElementById('customerAllergyBox');
    if (c && c.allergies) { document.getElementById('customerAllergyText').textContent = c.allergies; box.style.display = 'block'; }
    else box.style.display = 'none';
});

document.getElementById('add_allergy').addEventListener('change',    function () { document.getElementById('addAllergyLabel').textContent  = this.checked ? 'Yes' : 'No'; });
document.getElementById('add_idverified').addEventListener('change', function () { document.getElementById('addIdVerLabel').textContent    = this.checked ? 'Yes' : 'No'; });
document.getElementById('edit_allergy').addEventListener('change',   function () { document.getElementById('editAllergyLabel').textContent = this.checked ? 'Yes' : 'No'; });
document.getElementById('edit_idverified').addEventListener('change',function () { document.getElementById('editIdVerLabel').textContent   = this.checked ? 'Yes' : 'No'; });

// ── VIEW button ──────────────────────────────────────────
document.querySelectorAll('.view-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        const rxId = btn.dataset.id;
        document.getElementById('viewModalTitle').textContent = 'Prescription #' + rxId;
        document.getElementById('view_customer').textContent  = btn.dataset.customer;
        document.getElementById('view_date').textContent      = btn.dataset.date;
        document.getElementById('view_allergy_icon').innerHTML = btn.dataset.allergy === '1'   ? '✅' : '❌';
        document.getElementById('view_id_icon').innerHTML      = btn.dataset.idverified === '1' ? '✅' : '❌';

        const sc = statusStyle[btn.dataset.status] || '';
        document.getElementById('view_status').innerHTML = `<span class="badge" style="${sc}">${btn.dataset.status}</span>`;

        const procWrap = document.getElementById('view_processed_wrap');
        if (btn.dataset.processed) {
            document.getElementById('view_processed').textContent = btn.dataset.processed;
            procWrap.style.display = 'block';
        } else { procWrap.style.display = 'none'; }

        const rejWrap = document.getElementById('view_rejection_wrap');
        if (btn.dataset.rejection) {
            document.getElementById('view_rejection').textContent = btn.dataset.rejection;
            rejWrap.style.display = 'block';
        } else { rejWrap.style.display = 'none'; }

        const tbody = document.getElementById('viewItemsBody');
        tbody.innerHTML = '';
        (rxItems[rxId] || []).forEach(item => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td><strong>${item.medication_name}</strong></td>
                <td>${item.dosage || '<span style="color:var(--text-muted)">—</span>'}</td>
                <td>${item.prescribed_qty}</td>
                <td>${item.requires_id_check ? '<span class="badge badge-age_restriction">Required</span>' : '<span style="color:var(--text-muted)">No</span>'}</td>
            `;
            tbody.appendChild(tr);
        });

        const refillWrap = document.getElementById('viewRefillWrap');
        if (btn.dataset.refill) { document.getElementById('view_refill').textContent = btn.dataset.refill; refillWrap.style.display = 'block'; }
        else refillWrap.style.display = 'none';

        const notesWrap = document.getElementById('viewNotesWrap');
        if (btn.dataset.notes) { document.getElementById('view_notes').textContent = btn.dataset.notes; notesWrap.style.display = 'block'; }
        else notesWrap.style.display = 'none';

        openModal('view');
    });
});

// ── EDIT button ──────────────────────────────────────────
document.querySelectorAll('.edit-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        const rxId = btn.dataset.id;
        document.getElementById('edit_rx_id').value             = rxId;
        document.getElementById('edit_rx_customer').textContent = btn.dataset.customer;
        document.getElementById('edit_refill').value            = btn.dataset.refill;
        document.getElementById('edit_notes').value             = btn.dataset.notes;
        document.getElementById('editModalTitle').textContent   = 'Edit Prescription #' + rxId;

        // Payment banner
        const payStatus  = btn.dataset.payment || '';
        const payBanner  = document.getElementById('editPaymentBanner');
        const payLink    = document.getElementById('editPaymentLink');
        if (!payStatus || payStatus === 'unpaid') {
            payBanner.style.display = 'flex';
            payLink.href = '/pages/payment_form.php?rx=' + rxId;
        } else {
            payBanner.style.display = 'none';
        }

        const statusSel = document.getElementById('edit_status');
        [...statusSel.options].forEach(o => { o.selected = o.value === btn.dataset.status; });

        const isRejected = btn.dataset.status === 'rejected';
        document.getElementById('rejectionReasonGroup').style.display = isRejected ? 'block' : 'none';
        document.getElementById('edit_rejection_reason').value = btn.dataset.rejection || '';

        const allergyChecked = btn.dataset.allergy === '1';
        document.getElementById('edit_allergy').checked         = allergyChecked;
        document.getElementById('editAllergyLabel').textContent = allergyChecked ? 'Yes' : 'No';

        const idVerified = btn.dataset.idverified === '1';
        document.getElementById('edit_idverified').checked     = idVerified;
        document.getElementById('editIdVerLabel').textContent  = idVerified ? 'Yes' : 'No';
        document.getElementById('editIdVerGroup').style.display = btn.dataset.idreq === '1' ? 'block' : 'none';

        // Medicines
        document.getElementById('editMedicineRows').innerHTML = ''; editRowCount = 0;
        const items = rxItems[rxId] || [];
        if (items.length) items.forEach(item => addEditMedicineRow(item.stock_id, item.prescribed_qty, item.dosage || ''));
        else addEditMedicineRow();

        // Validation summary
        buildValidationSummary(rxId, idVerified);

        openModal('edit');
    });
});

// Re-build validation summary when ID verified toggle changes in edit form
document.getElementById('edit_idverified').addEventListener('change', function () {
    const rxId = document.getElementById('edit_rx_id').value;
    if (rxId) buildValidationSummary(rxId, this.checked);
});

// ── Form submit ──────────────────────────────────────────
async function submitForm(formId, action, prefix, defaultBtnText) {
    const form = document.getElementById(formId);
    const btn  = form.querySelector('[type="submit"]');
    btn.disabled = true; btn.textContent = 'Saving...';
    try {
        const res  = await fetch(action, { method: 'POST', body: new FormData(form) });
        const data = await res.json();
        if (data.success) {
            showToast(prefix, data.message, 'success');
            setTimeout(() => { closeModal(prefix); location.reload(); }, 1500);
        } else {
            showToast(prefix, data.message, 'error');
            btn.disabled = false; btn.textContent = defaultBtnText;
        }
    } catch {
        showToast(prefix, 'Unexpected error. Please try again.', 'error');
        btn.disabled = false; btn.textContent = defaultBtnText;
    }
}

document.getElementById('addPrescriptionForm').addEventListener('submit', async e => {
    e.preventDefault();
    const form = document.getElementById('addPrescriptionForm');
    const btn  = form.querySelector('[type="submit"]');
    btn.disabled = true; btn.textContent = 'Saving...';
    try {
        const res  = await fetch('/actions/add_prescription.php', { method: 'POST', body: new FormData(form) });
        const data = await res.json();
        if (data.success) {
            showToast('add', data.message, 'success');
            const footer = document.querySelector('#addPrescriptionModal .modal-footer');
            footer.innerHTML = `
                <button type="button" class="btn" onclick="closeModal('add');location.reload();"
                    style="background:var(--border);color:var(--text);">Close</button>
                <a href="/pages/payment_form.php?rx=${data.prescription_id}"
                    class="btn btn-primary">Proceed to Payment &rarr;</a>
            `;
        } else {
            showToast('add', data.message, 'error');
            btn.disabled = false; btn.textContent = 'Save Prescription';
        }
    } catch {
        showToast('add', 'Unexpected error. Please try again.', 'error');
        btn.disabled = false; btn.textContent = 'Save Prescription';
    }
});
document.getElementById('editPrescriptionForm').addEventListener('submit', e => { e.preventDefault(); submitForm('editPrescriptionForm', '/actions/edit_prescription.php', 'edit', 'Save Changes'); });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
