<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';
requireLogin();

$db = getDB();

$customers = $db->query("
    SELECT customer_id, name, email, date_of_birth, address, medical_history, allergies, account_active
    FROM CUSTOMER
    ORDER BY customer_id DESC
")->fetchAll();

// Calculate age from DOB
function calcAge(?string $dob): ?int {
    if (!$dob) return null;
    return (int)(new DateTime())->diff(new DateTime($dob))->y;
}

$pageTitle = 'Customers';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Toolbar -->
<div style="display:flex; align-items:center; gap:12px; margin-bottom:20px; flex-wrap:wrap;">
    <input type="text" id="customerSearch" class="form-control"
           placeholder="Search by name or ID..." style="max-width:280px;">
    <span style="color:var(--text-muted); font-size:13px; flex:1;">
        <?= count($customers) ?> customer<?= count($customers) !== 1 ? 's' : '' ?> total
    </span>
    <button class="btn btn-primary" id="openAddCustomer">+ Add New Customer</button>
</div>

<!-- Customers table -->
<div class="card">
    <div class="card-body">
        <?php if ($customers): ?>
        <div class="table-wrap">
            <table id="customersTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Date of Birth</th>
                        <th>Age</th>
                        <th>Email</th>
                        <th>Status</th>
                        <th style="width:100px; text-align:center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($customers as $c):
                        $age = calcAge($c['date_of_birth']);
                    ?>
                    <tr>
                        <td>#<?= $c['customer_id'] ?></td>
                        <td><?= htmlspecialchars($c['name']) ?></td>
                        <td><?= $c['date_of_birth'] ? date('d M Y', strtotime($c['date_of_birth'])) : '<span style="color:var(--text-muted)">—</span>' ?></td>
                        <td><?= $age !== null ? $age . ' yrs' : '<span style="color:var(--text-muted)">—</span>' ?></td>
                        <td><?= $c['email'] ? htmlspecialchars($c['email']) : '<span style="color:var(--text-muted)">—</span>' ?></td>
                        <td>
                            <?php if ($c['account_active']): ?>
                                <span class="badge" style="background:#d1fae5;color:#065f46;">Active</span>
                            <?php else: ?>
                                <span class="badge" style="background:#f3f4f6;color:#6b7280;">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align:center; display:flex; gap:6px; justify-content:center;">
                            <a href="/pages/customer_profile.php?id=<?= $c['customer_id'] ?>"
                               class="btn-icon" title="View profile">👁</a>
                            <button class="btn-icon edit-btn"
                                title="Edit customer"
                                data-id="<?= $c['customer_id'] ?>"
                                data-name="<?= htmlspecialchars($c['name'], ENT_QUOTES) ?>"
                                data-email="<?= htmlspecialchars($c['email'] ?? '', ENT_QUOTES) ?>"
                                data-dob="<?= htmlspecialchars($c['date_of_birth'] ?? '', ENT_QUOTES) ?>"
                                data-address="<?= htmlspecialchars($c['address'] ?? '', ENT_QUOTES) ?>"
                                data-history="<?= htmlspecialchars($c['medical_history'] ?? '', ENT_QUOTES) ?>"
                                data-allergies="<?= htmlspecialchars($c['allergies'] ?? '', ENT_QUOTES) ?>"
                                data-active="<?= $c['account_active'] ?>">
                                ✏️
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div id="noResultsMsg" style="display:none;" class="empty-state">
            No customers match your search.
        </div>
        <?php else: ?>
            <div class="empty-state">No customers yet. Add your first customer using the button above.</div>
        <?php endif; ?>
    </div>
</div>

<!-- ── Add Customer Modal ─────────────────────────────────── -->
<div class="modal-backdrop" id="addBackdrop"></div>
<div class="modal" id="addCustomerModal">
    <div class="modal-header">
        <h3 class="modal-title">Add New Customer</h3>
        <button class="modal-close" data-close="add">&times;</button>
    </div>
    <div class="toast" id="addToast"></div>
    <form id="addCustomerForm" novalidate>
        <div class="modal-body">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Full Name <span class="required">*</span></label>
                    <input type="text" name="name" class="form-control" placeholder="e.g. Amal Perera" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Email Address</label>
                    <input type="email" name="email" class="form-control" placeholder="e.g. amal@gmail.com">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Date of Birth <span class="required">*</span></label>
                    <input type="date" name="date_of_birth" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Address <span class="required">*</span></label>
                    <input type="text" name="address" class="form-control" placeholder="Residential address" required>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Medical History / Conditions</label>
                <textarea name="medical_history" class="form-control" rows="3"
                    placeholder="Known conditions, diagnoses, chronic illnesses..."></textarea>
            </div>
            <div class="form-group">
                <label class="form-label">Known Allergies</label>
                <textarea name="allergies" class="form-control" rows="2"
                    placeholder="e.g. Penicillin, Sulfa drugs, Aspirin..."></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn" data-close="add" style="background:var(--border);color:var(--text);">Cancel</button>
            <button type="submit" class="btn btn-primary">Save Customer</button>
        </div>
    </form>
</div>

<!-- ── Edit Customer Modal ────────────────────────────────── -->
<div class="modal-backdrop" id="editBackdrop"></div>
<div class="modal" id="editCustomerModal">
    <div class="modal-header">
        <h3 class="modal-title">Edit Customer</h3>
        <button class="modal-close" data-close="edit">&times;</button>
    </div>
    <div class="toast" id="editToast"></div>
    <form id="editCustomerForm" novalidate>
        <input type="hidden" name="customer_id" id="edit_customer_id">
        <div class="modal-body">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Full Name <span class="required">*</span></label>
                    <input type="text" name="name" id="edit_name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Email Address</label>
                    <input type="email" name="email" id="edit_email" class="form-control">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Date of Birth <span class="required">*</span></label>
                    <input type="date" name="date_of_birth" id="edit_dob" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Address <span class="required">*</span></label>
                    <input type="text" name="address" id="edit_address" class="form-control" required>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Medical History / Conditions</label>
                <textarea name="medical_history" id="edit_history" class="form-control" rows="3"></textarea>
            </div>
            <div class="form-group">
                <label class="form-label">Known Allergies</label>
                <textarea name="allergies" id="edit_allergies" class="form-control" rows="2"></textarea>
            </div>
            <div class="form-group">
                <label class="form-label">Account Status</label>
                <label class="toggle-switch">
                    <input type="checkbox" name="account_active" id="edit_active">
                    <span class="toggle-track"><span class="toggle-thumb"></span></span>
                    <span class="toggle-label" id="toggleLabel">Active</span>
                </label>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn" data-close="edit" style="background:var(--border);color:var(--text);">Cancel</button>
            <button type="submit" class="btn btn-primary">Save Changes</button>
        </div>
    </form>
</div>

<script>
// ── Search / filter ──────────────────────────────────────
document.getElementById('customerSearch').addEventListener('input', function () {
    const q    = this.value.toLowerCase().trim();
    const rows = document.querySelectorAll('#customersTable tbody tr');
    let visible = 0;
    rows.forEach(row => {
        const id   = row.cells[0].textContent.toLowerCase();
        const name = row.cells[1].textContent.toLowerCase();
        const show = !q || id.includes(q) || name.includes(q);
        row.style.display = show ? '' : 'none';
        if (show) visible++;
    });
    document.getElementById('noResultsMsg').style.display = visible === 0 ? 'block' : 'none';
});

// ── Modal helpers ────────────────────────────────────────
function openModal(id) {
    document.getElementById(id + 'CustomerModal').classList.add('open');
    document.getElementById(id + 'Backdrop').classList.add('open');
}
function closeModal(id) {
    document.getElementById(id + 'CustomerModal').classList.remove('open');
    document.getElementById(id + 'Backdrop').classList.remove('open');
    document.getElementById(id + 'CustomerForm').reset();
    const t = document.getElementById(id + 'Toast');
    t.className = 'toast'; t.textContent = '';
}
function showToast(id, message, type) {
    const t = document.getElementById(id + 'Toast');
    t.textContent = message;
    t.className   = 'toast show toast-' + type;
}

document.getElementById('openAddCustomer').addEventListener('click', () => openModal('add'));
document.querySelectorAll('[data-close]').forEach(btn =>
    btn.addEventListener('click', () => closeModal(btn.dataset.close))
);
document.getElementById('addBackdrop').addEventListener('click',  () => closeModal('add'));
document.getElementById('editBackdrop').addEventListener('click', () => closeModal('edit'));

// ── Populate edit modal ──────────────────────────────────
document.querySelectorAll('.edit-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.getElementById('edit_customer_id').value = btn.dataset.id;
        document.getElementById('edit_name').value        = btn.dataset.name;
        document.getElementById('edit_email').value       = btn.dataset.email;
        document.getElementById('edit_dob').value         = btn.dataset.dob;
        document.getElementById('edit_address').value     = btn.dataset.address;
        document.getElementById('edit_history').value     = btn.dataset.history;
        document.getElementById('edit_allergies').value   = btn.dataset.allergies;

        const isActive = btn.dataset.active === '1';
        document.getElementById('edit_active').checked   = isActive;
        document.getElementById('toggleLabel').textContent = isActive ? 'Active' : 'Inactive';

        openModal('edit');
    });
});

document.getElementById('edit_active').addEventListener('change', function () {
    document.getElementById('toggleLabel').textContent = this.checked ? 'Active' : 'Inactive';
});

// ── Generic form submit ───────────────────────────────────
async function submitForm(formId, action, toastId, submitBtnText) {
    const form = document.getElementById(formId);
    const btn  = form.querySelector('[type="submit"]');
    btn.disabled    = true;
    btn.textContent = 'Saving...';
    try {
        const res  = await fetch(action, { method: 'POST', body: new FormData(form) });
        const data = await res.json();
        const id   = toastId.replace('Toast', '');
        if (data.success) {
            showToast(id, data.message, 'success');
            setTimeout(() => { closeModal(id); location.reload(); }, 1500);
        } else {
            showToast(id, data.message, 'error');
            btn.disabled    = false;
            btn.textContent = submitBtnText;
        }
    } catch {
        showToast(toastId.replace('Toast',''), 'Unexpected error. Please try again.', 'error');
        btn.disabled    = false;
        btn.textContent = submitBtnText;
    }
}

document.getElementById('addCustomerForm').addEventListener('submit', e => {
    e.preventDefault();
    submitForm('addCustomerForm', '/actions/add_customer.php', 'addToast', 'Save Customer');
});
document.getElementById('editCustomerForm').addEventListener('submit', e => {
    e.preventDefault();
    submitForm('editCustomerForm', '/actions/edit_customer.php', 'editToast', 'Save Changes');
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
