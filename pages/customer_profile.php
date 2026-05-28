<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';
requireLogin();

$db = getDB();
$id = (int) ($_GET['id'] ?? 0);

if ($id <= 0) { header('Location: /pages/customers.php'); exit; }

$stmt = $db->prepare("SELECT * FROM CUSTOMER WHERE customer_id = ? LIMIT 1");
$stmt->execute([$id]);
$c = $stmt->fetch();
if (!$c) { header('Location: /pages/customers.php'); exit; }

$age = null;
if ($c['date_of_birth']) {
    $age = (int)(new DateTime())->diff(new DateTime($c['date_of_birth']))->y;
}

// Prescription headers
$rxStmt = $db->prepare("
    SELECT p.prescription_id, p.status, p.allergy_checked, p.age_id_verified,
           p.special_notes, p.next_refill_date, p.created_at,
           GROUP_CONCAT(m.medication_name ORDER BY m.medication_name SEPARATOR ', ') AS medicines
    FROM PRESCRIPTION p
    JOIN PRESCRIPTION_ITEM pi ON pi.prescription_id = p.prescription_id
    JOIN MEDICINE_STOCK m     ON m.stock_id = pi.stock_id
    WHERE p.customer_id = ?
    GROUP BY p.prescription_id
    ORDER BY p.created_at DESC
");
$rxStmt->execute([$id]);
$history = $rxStmt->fetchAll();

// Prescription line items (for detail modal)
$itemStmt = $db->prepare("
    SELECT pi.prescription_id, pi.quantity AS prescribed_qty,
           m.medication_name, m.category, m.requires_id_check
    FROM PRESCRIPTION_ITEM pi
    JOIN MEDICINE_STOCK m ON m.stock_id = pi.stock_id
    WHERE pi.prescription_id IN (
        SELECT prescription_id FROM PRESCRIPTION WHERE customer_id = ?
    )
    ORDER BY pi.prescription_id, m.medication_name
");
$itemStmt->execute([$id]);
$allItems = [];
foreach ($itemStmt->fetchAll() as $row) {
    $allItems[$row['prescription_id']][] = $row;
}

// Build full rx data for JS
$rxData = [];
foreach ($history as $rx) {
    $rxData[$rx['prescription_id']] = [
        'id'             => $rx['prescription_id'],
        'status'         => $rx['status'],
        'allergy_checked'=> (bool)$rx['allergy_checked'],
        'age_id_verified'=> (bool)$rx['age_id_verified'],
        'special_notes'  => $rx['special_notes'] ?? '',
        'next_refill_date'=> $rx['next_refill_date'] ?? '',
        'created_at'     => date('d M Y', strtotime($rx['created_at'])),
        'items'          => $allItems[$rx['prescription_id']] ?? [],
    ];
}

$pageTitle = 'Customer Profile';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Back link -->
<div style="margin-bottom:20px;">
    <a href="/pages/customers.php" class="btn"
       style="background:var(--bg);border:1px solid var(--border);color:var(--text);font-size:13px;">
        ← Back to Customers
    </a>
</div>

<!-- Profile header card -->
<div class="card" style="margin-bottom:24px;">
    <div class="card-body" style="padding:24px;">
        <div style="display:flex; align-items:center; gap:20px; flex-wrap:wrap;">
            <div style="
                width:64px; height:64px; border-radius:50%;
                background:var(--primary); color:#fff;
                display:flex; align-items:center; justify-content:center;
                font-size:26px; font-weight:700; flex-shrink:0;">
                <?= strtoupper(substr($c['name'], 0, 1)) ?>
            </div>
            <div style="flex:1;">
                <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                    <h2 style="font-size:20px; font-weight:700;"><?= htmlspecialchars($c['name']) ?></h2>
                    <?php if ($c['account_active']): ?>
                        <span class="badge" style="background:#d1fae5;color:#065f46;">Active</span>
                    <?php else: ?>
                        <span class="badge" style="background:#f3f4f6;color:#6b7280;">Inactive</span>
                    <?php endif; ?>
                </div>
                <div style="color:var(--text-muted); font-size:13px; margin-top:4px;">
                    Customer #<?= $c['customer_id'] ?>
                    <?php if ($c['email']): ?>&nbsp;·&nbsp; <?= htmlspecialchars($c['email']) ?><?php endif; ?>
                </div>
            </div>
            <!-- Edit button -->
            <button class="btn btn-primary" id="openEditCustomer">✏️ Edit Customer</button>
        </div>
    </div>
</div>

<!-- Detail grid -->
<div class="two-col" style="margin-bottom:24px;">

    <div class="card">
        <div class="card-header">Personal Details</div>
        <div class="card-body" style="padding:20px;">
            <table style="width:100%; border-collapse:collapse;">
                <tr>
                    <td style="padding:8px 0;color:var(--text-muted);font-size:13px;width:140px;">Date of Birth</td>
                    <td style="padding:8px 0;font-size:13.5px;font-weight:500;">
                        <?= $c['date_of_birth'] ? date('d M Y', strtotime($c['date_of_birth'])) : '—' ?>
                    </td>
                </tr>
                <tr>
                    <td style="padding:8px 0;color:var(--text-muted);font-size:13px;">Age</td>
                    <td style="padding:8px 0;font-size:13.5px;font-weight:500;">
                        <?= $age !== null ? $age . ' years old' : '—' ?>
                    </td>
                </tr>
                <tr>
                    <td style="padding:8px 0;color:var(--text-muted);font-size:13px;">Address</td>
                    <td style="padding:8px 0;font-size:13.5px;">
                        <?= $c['address'] ? htmlspecialchars($c['address']) : '—' ?>
                    </td>
                </tr>
                <tr>
                    <td style="padding:8px 0;color:var(--text-muted);font-size:13px;">Email</td>
                    <td style="padding:8px 0;font-size:13.5px;">
                        <?= $c['email'] ? htmlspecialchars($c['email']) : '—' ?>
                    </td>
                </tr>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-header">Medical Information</div>
        <div class="card-body" style="padding:20px;">
            <?php if ($c['allergies']): ?>
            <div style="background:#fee2e2;border:1px solid #fca5a5;border-radius:8px;padding:12px 16px;margin-bottom:14px;">
                <div style="font-size:12px;font-weight:700;color:#991b1b;text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px;">⚠️ Known Allergies</div>
                <div style="font-size:13.5px;color:#7f1d1d;"><?= htmlspecialchars($c['allergies']) ?></div>
            </div>
            <?php else: ?>
            <div style="color:var(--text-muted);font-size:13px;margin-bottom:14px;">No known allergies recorded.</div>
            <?php endif; ?>

            <?php if ($c['medical_history']): ?>
            <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;padding:12px 16px;">
                <div style="font-size:12px;font-weight:700;color:#1e40af;text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px;">📋 Medical History / Conditions</div>
                <div style="font-size:13.5px;color:#1e3a8a;"><?= htmlspecialchars($c['medical_history']) ?></div>
            </div>
            <?php else: ?>
            <div style="color:var(--text-muted);font-size:13px;">No medical history recorded.</div>
            <?php endif; ?>
        </div>
    </div>

</div>

<!-- Medication history -->
<div class="card">
    <div class="card-header">
        Medication History
        <span style="color:var(--text-muted);font-size:12px;font-weight:400;">
            <?= count($history) ?> prescription<?= count($history) !== 1 ? 's' : '' ?>
        </span>
    </div>
    <div class="card-body">
        <?php if ($history): ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Medicines</th>
                        <th>Status</th>
                        <th>Allergy Checked</th>
                        <th>ID Verified</th>
                        <th>Next Refill</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($history as $rx): ?>
                    <tr>
                        <td>
                            <a href="#" class="rx-link"
                               data-id="<?= $rx['prescription_id'] ?>"
                               style="color:var(--primary);font-weight:600;text-decoration:underline;">
                                #<?= $rx['prescription_id'] ?>
                            </a>
                        </td>
                        <td title="<?= htmlspecialchars($rx['medicines']) ?>">
                            <?php
                            $meds = explode(', ', $rx['medicines']);
                            echo htmlspecialchars($meds[0]);
                            if (count($meds) > 1) {
                                echo ' <span style="color:var(--text-muted);font-size:12px;">+' . (count($meds) - 1) . ' more</span>';
                            }
                            ?>
                        </td>
                        <td><span class="badge badge-<?= $rx['status'] ?>"><?= $rx['status'] ?></span></td>
                        <td style="text-align:center;">
                            <?= $rx['allergy_checked'] ? '<span style="color:var(--success);">✓</span>' : '<span style="color:var(--text-muted);">—</span>' ?>
                        </td>
                        <td style="text-align:center;">
                            <?= $rx['age_id_verified'] ? '<span style="color:var(--success);">✓</span>' : '<span style="color:var(--text-muted);">—</span>' ?>
                        </td>
                        <td><?= $rx['next_refill_date'] ? date('d M Y', strtotime($rx['next_refill_date'])) : '<span style="color:var(--text-muted)">—</span>' ?></td>
                        <td><?= date('d M Y', strtotime($rx['created_at'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
            <div class="empty-state">No prescriptions on record for this customer.</div>
        <?php endif; ?>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════
     EDIT CUSTOMER MODAL
════════════════════════════════════════════════════════ -->
<div class="modal-backdrop" id="editBackdrop"></div>
<div class="modal" id="editCustomerModal">
    <div class="modal-header">
        <h3 class="modal-title">Edit Customer</h3>
        <button class="modal-close" id="closeEdit">&times;</button>
    </div>
    <div class="toast" id="editToast"></div>
    <form id="editCustomerForm" novalidate>
        <input type="hidden" name="customer_id" value="<?= $c['customer_id'] ?>">
        <div class="modal-body">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Full Name <span class="required">*</span></label>
                    <input type="text" name="name" class="form-control"
                           value="<?= htmlspecialchars($c['name'], ENT_QUOTES) ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Email Address</label>
                    <input type="email" name="email" class="form-control"
                           value="<?= htmlspecialchars($c['email'] ?? '', ENT_QUOTES) ?>">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Date of Birth <span class="required">*</span></label>
                    <input type="date" name="date_of_birth" class="form-control"
                           value="<?= htmlspecialchars($c['date_of_birth'] ?? '', ENT_QUOTES) ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Address <span class="required">*</span></label>
                    <input type="text" name="address" class="form-control"
                           value="<?= htmlspecialchars($c['address'] ?? '', ENT_QUOTES) ?>" required>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Medical History / Conditions</label>
                <textarea name="medical_history" class="form-control" rows="3"><?= htmlspecialchars($c['medical_history'] ?? '') ?></textarea>
            </div>
            <div class="form-group">
                <label class="form-label">Known Allergies</label>
                <textarea name="allergies" class="form-control" rows="2"><?= htmlspecialchars($c['allergies'] ?? '') ?></textarea>
            </div>
            <div class="form-group">
                <label class="form-label">Account Status</label>
                <label class="toggle-switch">
                    <input type="checkbox" name="account_active" id="edit_active"
                           <?= $c['account_active'] ? 'checked' : '' ?>>
                    <span class="toggle-track"><span class="toggle-thumb"></span></span>
                    <span class="toggle-label" id="activeLabel"><?= $c['account_active'] ? 'Active' : 'Inactive' ?></span>
                </label>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn" id="cancelEdit"
                style="background:var(--border);color:var(--text);">Cancel</button>
            <button type="submit" class="btn btn-primary">Save Changes</button>
        </div>
    </form>
</div>

<!-- ═══════════════════════════════════════════════════════
     PRESCRIPTION DETAIL MODAL
════════════════════════════════════════════════════════ -->
<div class="modal-backdrop" id="rxBackdrop"></div>
<div class="modal" id="rxDetailModal">
    <div class="modal-header">
        <h3 class="modal-title" id="rxModalTitle">Prescription Details</h3>
        <button class="modal-close" id="closeRx">&times;</button>
    </div>
    <div class="modal-body">

        <!-- Meta row -->
        <div style="display:flex; gap:16px; flex-wrap:wrap; margin-bottom:20px;">
            <div style="flex:1; min-width:120px;">
                <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:3px;">Date</div>
                <div style="font-size:13.5px;font-weight:500;" id="rx_date"></div>
            </div>
            <div style="flex:1; min-width:120px;">
                <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:3px;">Status</div>
                <div id="rx_status"></div>
            </div>
            <div style="flex:1; min-width:120px;">
                <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:3px;">Next Refill</div>
                <div style="font-size:13.5px;" id="rx_refill"></div>
            </div>
        </div>

        <!-- Checks row -->
        <div style="display:flex; gap:24px; margin-bottom:20px; flex-wrap:wrap;">
            <div style="display:flex; align-items:center; gap:8px; font-size:13px;">
                <span id="rx_allergy_icon" style="font-size:16px;"></span>
                <span>Allergy Checked</span>
            </div>
            <div style="display:flex; align-items:center; gap:8px; font-size:13px;">
                <span id="rx_id_icon" style="font-size:16px;"></span>
                <span>Age / ID Verified</span>
            </div>
        </div>

        <!-- Medicines table -->
        <div style="font-size:12px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px;">
            Medicines Prescribed
        </div>
        <div class="table-wrap" style="margin-bottom:20px;">
            <table id="rxItemsTable">
                <thead>
                    <tr>
                        <th>Medication</th>
                        <th>Category</th>
                        <th>Qty</th>
                        <th>ID Check</th>
                    </tr>
                </thead>
                <tbody id="rxItemsBody"></tbody>
            </table>
        </div>

        <!-- Notes -->
        <div id="rxNotesWrap" style="display:none;">
            <div style="font-size:12px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px;">
                Special Notes
            </div>
            <div id="rx_notes" style="font-size:13.5px;color:var(--text);background:var(--bg);border-radius:8px;padding:12px 14px;"></div>
        </div>

    </div>
    <div class="modal-footer">
        <button type="button" class="btn" id="closeRxBtn"
            style="background:var(--border);color:var(--text);">Close</button>
    </div>
</div>

<script>
const rxData = <?= json_encode($rxData) ?>;

// ── Edit Customer Modal ──────────────────────────────────
function openEdit() {
    document.getElementById('editBackdrop').classList.add('open');
    document.getElementById('editCustomerModal').classList.add('open');
}
function closeEdit() {
    document.getElementById('editBackdrop').classList.remove('open');
    document.getElementById('editCustomerModal').classList.remove('open');
    const t = document.getElementById('editToast');
    t.className = 'toast'; t.textContent = '';
}

document.getElementById('openEditCustomer').addEventListener('click', openEdit);
document.getElementById('closeEdit').addEventListener('click', closeEdit);
document.getElementById('cancelEdit').addEventListener('click', closeEdit);
document.getElementById('editBackdrop').addEventListener('click', closeEdit);

document.getElementById('edit_active').addEventListener('change', function () {
    document.getElementById('activeLabel').textContent = this.checked ? 'Active' : 'Inactive';
});

document.getElementById('editCustomerForm').addEventListener('submit', async function (e) {
    e.preventDefault();
    const btn = this.querySelector('[type="submit"]');
    btn.disabled = true; btn.textContent = 'Saving...';

    try {
        const res  = await fetch('/actions/edit_customer.php', { method: 'POST', body: new FormData(this) });
        const data = await res.json();
        const t    = document.getElementById('editToast');
        t.textContent = data.message;
        t.className   = 'toast show toast-' + (data.success ? 'success' : 'error');
        if (data.success) {
            setTimeout(() => location.reload(), 1500);
        } else {
            btn.disabled = false; btn.textContent = 'Save Changes';
        }
    } catch {
        document.getElementById('editToast').className = 'toast show toast-error';
        document.getElementById('editToast').textContent = 'Unexpected error. Please try again.';
        btn.disabled = false; btn.textContent = 'Save Changes';
    }
});

// ── Prescription Detail Modal ────────────────────────────
function openRx() {
    document.getElementById('rxBackdrop').classList.add('open');
    document.getElementById('rxDetailModal').classList.add('open');
}
function closeRx() {
    document.getElementById('rxBackdrop').classList.remove('open');
    document.getElementById('rxDetailModal').classList.remove('open');
}

document.getElementById('closeRx').addEventListener('click', closeRx);
document.getElementById('closeRxBtn').addEventListener('click', closeRx);
document.getElementById('rxBackdrop').addEventListener('click', closeRx);

const statusColours = {
    pending:   'background:#fef3c7;color:#92400e',
    approved:  'background:#d1fae5;color:#065f46',
    processed: 'background:#dbeafe;color:#1e40af',
    rejected:  'background:#fee2e2;color:#991b1b',
};

document.querySelectorAll('.rx-link').forEach(link => {
    link.addEventListener('click', function (e) {
        e.preventDefault();
        const rx = rxData[this.dataset.id];
        if (!rx) return;

        document.getElementById('rxModalTitle').textContent = 'Prescription #' + rx.id;
        document.getElementById('rx_date').textContent      = rx.created_at;
        document.getElementById('rx_refill').textContent    = rx.next_refill_date || '—';

        const sc = statusColours[rx.status] || '';
        document.getElementById('rx_status').innerHTML =
            `<span class="badge" style="${sc}">${rx.status}</span>`;

        document.getElementById('rx_allergy_icon').innerHTML =
            rx.allergy_checked ? '✅' : '❌';
        document.getElementById('rx_id_icon').innerHTML =
            rx.age_id_verified ? '✅' : '❌';

        // Build items table
        const tbody = document.getElementById('rxItemsBody');
        tbody.innerHTML = '';
        (rx.items || []).forEach(item => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td><strong>${item.medication_name}</strong></td>
                <td>${item.category || '<span style="color:var(--text-muted)">—</span>'}</td>
                <td>${item.prescribed_qty}</td>
                <td>${item.requires_id_check
                    ? '<span class="badge badge-age_restriction">Required</span>'
                    : '<span style="color:var(--text-muted)">No</span>'}</td>
            `;
            tbody.appendChild(tr);
        });

        // Notes
        const notesWrap = document.getElementById('rxNotesWrap');
        if (rx.special_notes) {
            document.getElementById('rx_notes').textContent = rx.special_notes;
            notesWrap.style.display = 'block';
        } else {
            notesWrap.style.display = 'none';
        }

        openRx();
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
