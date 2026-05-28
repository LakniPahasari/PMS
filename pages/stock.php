<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';
requireLogin();

$db = getDB();

$medicines = $db->query("
    SELECT stock_id, medication_name, category, batch_number,
           quantity, unit_price, expiry_date, supplier,
           requires_id_check, is_active
    FROM MEDICINE_STOCK
    ORDER BY stock_id DESC
")->fetchAll();

// Counts for stat cards
$totalActive  = 0;
$lowStock     = 0;
$expiringSoon = 0;
$outOfStock   = 0;
$today        = new DateTime();

foreach ($medicines as $m) {
    if (!$m['is_active']) continue;
    $totalActive++;
    if ($m['quantity'] == 0) $outOfStock++;
    elseif ($m['quantity'] < 10) $lowStock++;
    if ($m['expiry_date']) {
        $diff = $today->diff(new DateTime($m['expiry_date']))->days;
        $isExpired = new DateTime($m['expiry_date']) < $today;
        if (!$isExpired && $diff <= 30) $expiringSoon++;
    }
}

$pageTitle = 'Medicine Stock';
require_once __DIR__ . '/../includes/header.php';
?>

<?php if ($lowStock > 0): ?>
<div id="lowStockBanner" style="
    background:#fef3c7; border:1px solid #f59e0b; border-radius:10px;
    padding:14px 20px; margin-bottom:20px;
    display:flex; align-items:center; justify-content:space-between; gap:12px;">
    <div style="display:flex; align-items:center; gap:10px;">
        <span style="font-size:20px;">⚠️</span>
        <div>
            <strong style="color:#92400e;">Low Stock Warning</strong>
            <div style="font-size:13px; color:#78350f; margin-top:2px;">
                <?= $lowStock ?> medicine<?= $lowStock > 1 ? 's are' : ' is' ?> below 10 units. Please reorder soon.
            </div>
        </div>
    </div>
    <button onclick="document.getElementById('lowStockBanner').style.display='none'"
        style="background:none;border:none;font-size:18px;cursor:pointer;color:#92400e;line-height:1;">✕</button>
</div>
<?php endif; ?>

<!-- Stat cards -->
<div class="stats-grid">
    <div class="stat-card green">
        <div class="stat-icon">💊</div>
        <div>
            <div class="stat-value"><?= $totalActive ?></div>
            <div class="stat-label">Active Medicines</div>
        </div>
    </div>
    <div class="stat-card red">
        <div class="stat-icon">📉</div>
        <div>
            <div class="stat-value"><?= $lowStock ?></div>
            <div class="stat-label">Low Stock (&lt;10 units)</div>
        </div>
    </div>
    <div class="stat-card yellow">
        <div class="stat-icon">📅</div>
        <div>
            <div class="stat-value"><?= $expiringSoon ?></div>
            <div class="stat-label">Expiring Within 30 Days</div>
        </div>
    </div>
    <div class="stat-card blue">
        <div class="stat-icon">🚫</div>
        <div>
            <div class="stat-value"><?= $outOfStock ?></div>
            <div class="stat-label">Out of Stock</div>
        </div>
    </div>
</div>

<!-- Toolbar -->
<div style="display:flex; align-items:center; gap:12px; margin-bottom:20px; flex-wrap:wrap;">
    <input type="text" id="stockSearch" class="form-control"
           placeholder="Search by name or category..." style="max-width:280px;">
    <span style="color:var(--text-muted); font-size:13px; flex:1;">
        <?= count($medicines) ?> medicine<?= count($medicines) !== 1 ? 's' : '' ?> total
    </span>
    <button class="btn btn-primary" id="openAddStock">+ Add Medicine</button>
</div>

<!-- Stock table -->
<div class="card">
    <div class="card-body">
        <?php if ($medicines): ?>
        <div class="table-wrap">
            <table id="stockTable">
                <thead>
                    <tr>
                        <th data-col="0" class="sortable-th">ID <span class="sort-arrow"></span></th>
                        <th data-col="1" class="sortable-th">Medication Name <span class="sort-arrow"></span></th>
                        <th data-col="2" class="sortable-th">Category <span class="sort-arrow"></span></th>
                        <th>Batch No.</th>
                        <th data-col="4" class="sortable-th">Qty <span class="sort-arrow"></span></th>
                        <th>Unit Price</th>
                        <th data-col="6" class="sortable-th">Expiry Date <span class="sort-arrow"></span></th>
                        <th>Supplier</th>
                        <th>ID Check</th>
                        <th>Status</th>
                        <th style="width:60px; text-align:center;">Edit</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($medicines as $m):
                        $expDate  = $m['expiry_date'] ? new DateTime($m['expiry_date']) : null;
                        $daysLeft = $expDate ? (int) $today->diff($expDate)->days * ($expDate >= $today ? 1 : -1) : null;
                        $isExpired = $expDate && $expDate < $today;
                    ?>
                    <tr>
                        <td>#<?= $m['stock_id'] ?></td>
                        <td><strong><?= htmlspecialchars($m['medication_name']) ?></strong></td>
                        <td><?= $m['category'] ? htmlspecialchars($m['category']) : '<span style="color:var(--text-muted)">—</span>' ?></td>
                        <td><?= $m['batch_number'] ? htmlspecialchars($m['batch_number']) : '<span style="color:var(--text-muted)">—</span>' ?></td>
                        <td>
                            <?php if ($m['quantity'] == 0): ?>
                                <span class="badge" style="background:#fee2e2;color:#991b1b;">Out of Stock</span>
                            <?php elseif ($m['quantity'] < 10): ?>
                                <span class="badge badge-low_stock"><?= $m['quantity'] ?> ⚠️</span>
                            <?php else: ?>
                                <?= $m['quantity'] ?>
                            <?php endif; ?>
                        </td>
                        <td><?= $m['unit_price'] !== null ? '£' . number_format($m['unit_price'], 2) : '<span style="color:var(--text-muted)">—</span>' ?></td>
                        <td>
                            <?php if (!$expDate): ?>
                                <span style="color:var(--text-muted)">—</span>
                            <?php elseif ($isExpired): ?>
                                <span class="badge badge-expiry">Expired</span>
                            <?php elseif ($daysLeft <= 30): ?>
                                <span class="badge badge-expiry"><?= date('d M Y', strtotime($m['expiry_date'])) ?> (<?= $daysLeft ?>d)</span>
                            <?php else: ?>
                                <?= date('d M Y', strtotime($m['expiry_date'])) ?>
                            <?php endif; ?>
                        </td>
                        <td><?= $m['supplier'] ? htmlspecialchars($m['supplier']) : '<span style="color:var(--text-muted)">—</span>' ?></td>
                        <td>
                            <?php if ($m['requires_id_check']): ?>
                                <span class="badge badge-age_restriction">Required</span>
                            <?php else: ?>
                                <span style="color:var(--text-muted)">No</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($m['is_active']): ?>
                                <span class="badge" style="background:#d1fae5;color:#065f46;">Active</span>
                            <?php else: ?>
                                <span class="badge" style="background:#f3f4f6;color:#6b7280;">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align:center;">
                            <button class="btn-icon edit-btn"
                                title="Edit medicine"
                                data-id="<?= $m['stock_id'] ?>"
                                data-name="<?= htmlspecialchars($m['medication_name'], ENT_QUOTES) ?>"
                                data-category="<?= htmlspecialchars($m['category'] ?? '', ENT_QUOTES) ?>"
                                data-batch="<?= htmlspecialchars($m['batch_number'] ?? '', ENT_QUOTES) ?>"
                                data-qty="<?= $m['quantity'] ?>"
                                data-price="<?= $m['unit_price'] ?? '' ?>"
                                data-expiry="<?= $m['expiry_date'] ?? '' ?>"
                                data-supplier="<?= htmlspecialchars($m['supplier'] ?? '', ENT_QUOTES) ?>"
                                data-idcheck="<?= $m['requires_id_check'] ?>"
                                data-active="<?= $m['is_active'] ?>">
                                ✏️
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div id="noStockResults" style="display:none;" class="empty-state">
            No medicines match your search.
        </div>
        <?php else: ?>
            <div class="empty-state">No medicines in stock yet. Add your first item using the button above.</div>
        <?php endif; ?>
    </div>
</div>

<!-- ── Add Medicine Modal ──────────────────────────────── -->
<div class="modal-backdrop" id="addBackdrop"></div>
<div class="modal" id="addStockModal">
    <div class="modal-header">
        <h3 class="modal-title">Add Medicine to Stock</h3>
        <button class="modal-close" data-close="add">&times;</button>
    </div>
    <div class="toast" id="addToast"></div>
    <form id="addStockForm" novalidate>
        <div class="modal-body">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Medication Name <span class="required">*</span></label>
                    <input type="text" name="medication_name" class="form-control"
                           placeholder="e.g. Amoxicillin 500mg" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Category</label>
                    <select name="category" class="form-control">
                        <option value="">— Select category —</option>
                        <option>Antibiotic</option>
                        <option>Painkiller</option>
                        <option>Antihistamine</option>
                        <option>Antidepressant</option>
                        <option>Controlled Drug</option>
                        <option>Vitamin / Supplement</option>
                        <option>Other</option>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Batch Number</label>
                    <input type="text" name="batch_number" class="form-control" placeholder="e.g. BT-20241101">
                </div>
                <div class="form-group">
                    <label class="form-label">Quantity <span class="required">*</span></label>
                    <input type="number" name="quantity" class="form-control" min="0" placeholder="0" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Unit Price (£)</label>
                    <input type="number" name="unit_price" class="form-control" min="0" step="0.01" placeholder="0.00">
                </div>
                <div class="form-group">
                    <label class="form-label">Expiry Date</label>
                    <input type="date" name="expiry_date" class="form-control">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Supplier</label>
                <input type="text" name="supplier" class="form-control" placeholder="e.g. Alliance Healthcare">
            </div>
            <div class="form-group">
                <label class="form-label">ID Check Required (Age-Restricted)</label>
                <label class="toggle-switch">
                    <input type="checkbox" name="requires_id_check" id="add_idcheck">
                    <span class="toggle-track"><span class="toggle-thumb"></span></span>
                    <span class="toggle-label" id="addIdLabel">No</span>
                </label>
                <div style="font-size:12px; color:var(--text-muted); margin-top:6px;">
                    Enable for age-restricted or controlled drugs. Staff will be prompted to verify customer ID.
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn" data-close="add"
                style="background:var(--border);color:var(--text);">Cancel</button>
            <button type="submit" class="btn btn-primary">Save Medicine</button>
        </div>
    </form>
</div>

<!-- ── Edit Medicine Modal ────────────────────────────────── -->
<div class="modal-backdrop" id="editBackdrop"></div>
<div class="modal" id="editStockModal">
    <div class="modal-header">
        <h3 class="modal-title">Edit Medicine</h3>
        <button class="modal-close" data-close="edit">&times;</button>
    </div>
    <div class="toast" id="editToast"></div>
    <form id="editStockForm" novalidate>
        <input type="hidden" name="stock_id" id="edit_stock_id">
        <div class="modal-body">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Medication Name <span class="required">*</span></label>
                    <input type="text" name="medication_name" id="edit_name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Category</label>
                    <select name="category" id="edit_category" class="form-control">
                        <option value="">— Select category —</option>
                        <option>Antibiotic</option>
                        <option>Painkiller</option>
                        <option>Antihistamine</option>
                        <option>Antidepressant</option>
                        <option>Controlled Drug</option>
                        <option>Vitamin / Supplement</option>
                        <option>Other</option>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Batch Number</label>
                    <input type="text" name="batch_number" id="edit_batch" class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label">Quantity <span class="required">*</span></label>
                    <input type="number" name="quantity" id="edit_qty" class="form-control" min="0" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Unit Price (£)</label>
                    <input type="number" name="unit_price" id="edit_price" class="form-control" min="0" step="0.01">
                </div>
                <div class="form-group">
                    <label class="form-label">Expiry Date</label>
                    <input type="date" name="expiry_date" id="edit_expiry" class="form-control">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Supplier</label>
                <input type="text" name="supplier" id="edit_supplier" class="form-control">
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">ID Check Required</label>
                    <label class="toggle-switch">
                        <input type="checkbox" name="requires_id_check" id="edit_idcheck">
                        <span class="toggle-track"><span class="toggle-thumb"></span></span>
                        <span class="toggle-label" id="editIdLabel">No</span>
                    </label>
                </div>
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <label class="toggle-switch">
                        <input type="checkbox" name="is_active" id="edit_active">
                        <span class="toggle-track"><span class="toggle-thumb"></span></span>
                        <span class="toggle-label" id="editActiveLabel">Active</span>
                    </label>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn" data-close="edit"
                style="background:var(--border);color:var(--text);">Cancel</button>
            <button type="submit" class="btn btn-primary">Save Changes</button>
        </div>
    </form>
</div>

<script>
// ── Modal helpers ────────────────────────────────────────
function openModal(prefix) {
    document.getElementById(prefix + 'Backdrop').classList.add('open');
    document.getElementById(prefix + 'StockModal').classList.add('open');
}
function closeModal(prefix) {
    document.getElementById(prefix + 'Backdrop').classList.remove('open');
    document.getElementById(prefix + 'StockModal').classList.remove('open');
    document.getElementById(prefix + 'StockForm').reset();
    const t = document.getElementById(prefix + 'Toast');
    t.className = 'toast'; t.textContent = '';
    if (prefix === 'add') {
        document.getElementById('addIdLabel').textContent = 'No';
    } else {
        document.getElementById('editIdLabel').textContent = 'No';
        document.getElementById('editActiveLabel').textContent = 'Active';
    }
}
function showToast(prefix, msg, type) {
    const t = document.getElementById(prefix + 'Toast');
    t.textContent = msg;
    t.className   = 'toast show toast-' + type;
}

document.getElementById('openAddStock').addEventListener('click', () => openModal('add'));
document.querySelectorAll('[data-close]').forEach(btn =>
    btn.addEventListener('click', () => closeModal(btn.dataset.close))
);
document.getElementById('addBackdrop').addEventListener('click',  () => closeModal('add'));
document.getElementById('editBackdrop').addEventListener('click', () => closeModal('edit'));

// Toggle labels
document.getElementById('add_idcheck').addEventListener('change', function () {
    document.getElementById('addIdLabel').textContent = this.checked ? 'Yes — ID Required' : 'No';
});
document.getElementById('edit_idcheck').addEventListener('change', function () {
    document.getElementById('editIdLabel').textContent = this.checked ? 'Yes — ID Required' : 'No';
});
document.getElementById('edit_active').addEventListener('change', function () {
    document.getElementById('editActiveLabel').textContent = this.checked ? 'Active' : 'Inactive';
});

// ── Populate edit modal ──────────────────────────────────
document.querySelectorAll('.edit-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.getElementById('edit_stock_id').value  = btn.dataset.id;
        document.getElementById('edit_name').value      = btn.dataset.name;
        document.getElementById('edit_batch').value     = btn.dataset.batch;
        document.getElementById('edit_qty').value       = btn.dataset.qty;
        document.getElementById('edit_price').value     = btn.dataset.price;
        document.getElementById('edit_expiry').value    = btn.dataset.expiry;
        document.getElementById('edit_supplier').value  = btn.dataset.supplier;

        const catSel = document.getElementById('edit_category');
        [...catSel.options].forEach(o => { o.selected = o.value === btn.dataset.category; });

        const idCheck = btn.dataset.idcheck === '1';
        document.getElementById('edit_idcheck').checked = idCheck;
        document.getElementById('editIdLabel').textContent = idCheck ? 'Yes — ID Required' : 'No';

        const isActive = btn.dataset.active === '1';
        document.getElementById('edit_active').checked = isActive;
        document.getElementById('editActiveLabel').textContent = isActive ? 'Active' : 'Inactive';

        openModal('edit');
    });
});

// ── Generic form submit ───────────────────────────────────
async function submitForm(formId, action, prefix, defaultBtnText) {
    const form = document.getElementById(formId);
    const btn  = form.querySelector('[type="submit"]');
    btn.disabled    = true;
    btn.textContent = 'Saving...';

    try {
        const res  = await fetch(action, { method: 'POST', body: new FormData(form) });
        const data = await res.json();

        if (data.success) {
            showToast(prefix, data.message, 'success');
            setTimeout(() => { closeModal(prefix); location.reload(); }, 1500);
        } else {
            showToast(prefix, data.message, 'error');
            btn.disabled    = false;
            btn.textContent = defaultBtnText;
        }
    } catch {
        showToast(prefix, 'Unexpected error. Please try again.', 'error');
        btn.disabled    = false;
        btn.textContent = defaultBtnText;
    }
}

document.getElementById('addStockForm').addEventListener('submit', e => {
    e.preventDefault();
    submitForm('addStockForm', '/actions/add_stock.php', 'add', 'Save Medicine');
});
document.getElementById('editStockForm').addEventListener('submit', e => {
    e.preventDefault();
    submitForm('editStockForm', '/actions/edit_stock.php', 'edit', 'Save Changes');
});

// ── Search / filter ──────────────────────────────────────
document.getElementById('stockSearch').addEventListener('input', function () {
    applyStockFilter();
});

function applyStockFilter() {
    const q    = document.getElementById('stockSearch').value.toLowerCase().trim();
    const rows = document.querySelectorAll('#stockTable tbody tr');
    let visible = 0;
    rows.forEach(row => {
        const name = row.cells[1]?.textContent.toLowerCase() ?? '';
        const cat  = row.cells[2]?.textContent.toLowerCase() ?? '';
        const show = !q || name.includes(q) || cat.includes(q);
        row.style.display = show ? '' : 'none';
        if (show) visible++;
    });
    const noRes = document.getElementById('noStockResults');
    if (noRes) noRes.style.display = visible === 0 ? 'block' : 'none';
}

// ── Sortable columns ─────────────────────────────────────
(function () {
    const table = document.getElementById('stockTable');
    if (!table) return;
    let sortCol = -1, sortAsc = true;

    table.querySelectorAll('th.sortable-th').forEach(th => {
        th.style.cursor = 'pointer';
        th.style.userSelect = 'none';
        th.addEventListener('click', () => {
            const col = parseInt(th.dataset.col, 10);
            if (sortCol === col) {
                sortAsc = !sortAsc;
            } else {
                sortCol = col; sortAsc = true;
            }
            // Update arrows
            table.querySelectorAll('th.sortable-th .sort-arrow').forEach(a => a.textContent = '');
            th.querySelector('.sort-arrow').textContent = sortAsc ? ' ↑' : ' ↓';
            sortTable(table, col, sortAsc);
        });
    });

    function sortTable(tbl, col, asc) {
        const tbody = tbl.querySelector('tbody');
        const rows  = Array.from(tbody.querySelectorAll('tr'));
        rows.sort((a, b) => {
            const av = cellVal(a, col);
            const bv = cellVal(b, col);
            if (!isNaN(av) && !isNaN(bv)) return asc ? av - bv : bv - av;
            return asc ? av.localeCompare(bv) : bv.localeCompare(av);
        });
        rows.forEach(r => tbody.appendChild(r));
        applyStockFilter();
    }

    function cellVal(row, col) {
        const cell = row.cells[col];
        if (!cell) return '';
        const txt = cell.textContent.trim();
        // For quantity column: extract number from badge text
        const n = parseFloat(txt.replace(/[^0-9.]/g, ''));
        return isNaN(n) ? txt.toLowerCase() : n;
    }
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
