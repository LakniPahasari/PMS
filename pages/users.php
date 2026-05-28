<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';
requireLogin();

$db = getDB();

$users = $db->query("
    SELECT system_user_id, name, email, role, branch, is_active, last_login
    FROM SYSTEM_USER
    ORDER BY system_user_id DESC
")->fetchAll();

$roles    = ['admin', 'pharmacist', 'store_manager'];
$branches = ['Main Branch', 'North Branch', 'South Branch', 'East Branch', 'West Branch'];

$pageTitle = 'System Users';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Toolbar -->
<div style="display:flex;align-items:center;gap:12px;margin-bottom:20px;flex-wrap:wrap;">
    <input type="text" id="userSearch" class="form-control"
           placeholder="Search by name or email..." style="max-width:280px;">
    <span style="color:var(--text-muted);font-size:13px;flex:1;">
        <?= count($users) ?> user<?= count($users) !== 1 ? 's' : '' ?> total
    </span>
    <button class="btn btn-primary" id="openAddUser">+ Add User</button>
</div>

<!-- Users table -->
<div class="card">
    <div class="card-body">
        <?php if ($users): ?>
        <div class="table-wrap">
            <table id="usersTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Branch</th>
                        <th>Last Login</th>
                        <th>Status</th>
                        <th style="width:80px;text-align:center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                    <tr>
                        <td>#<?= $u['system_user_id'] ?></td>
                        <td><strong><?= htmlspecialchars($u['name']) ?></strong></td>
                        <td><?= htmlspecialchars($u['email']) ?></td>
                        <td>
                            <span class="badge" style="background:#ede9fe;color:#5b21b6;text-transform:capitalize;">
                                <?= str_replace('_', ' ', $u['role']) ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars($u['branch'] ?? '—') ?></td>
                        <td style="color:var(--text-muted);font-size:13px;">
                            <?= $u['last_login'] ? date('d M Y H:i', strtotime($u['last_login'])) : '—' ?>
                        </td>
                        <td>
                            <?php if ($u['is_active']): ?>
                                <span class="badge" style="background:#d1fae5;color:#065f46;">Active</span>
                            <?php else: ?>
                                <span class="badge" style="background:#f3f4f6;color:#6b7280;">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align:center;">
                            <button class="btn-icon edit-btn" title="Edit user"
                                data-id="<?= $u['system_user_id'] ?>"
                                data-name="<?= htmlspecialchars($u['name'], ENT_QUOTES) ?>"
                                data-email="<?= htmlspecialchars($u['email'], ENT_QUOTES) ?>"
                                data-role="<?= $u['role'] ?>"
                                data-branch="<?= htmlspecialchars($u['branch'] ?? '', ENT_QUOTES) ?>"
                                data-active="<?= $u['is_active'] ?>">
                                ✏️
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div id="noUserResults" style="display:none;" class="empty-state">No users match your search.</div>
        <?php else: ?>
            <div class="empty-state">No system users found.</div>
        <?php endif; ?>
    </div>
</div>

<!-- ── Add User Modal ───────────────────────────────────── -->
<div class="modal-backdrop" id="addBackdrop"></div>
<div class="modal" id="addUserModal">
    <div class="modal-header">
        <h3 class="modal-title">Add System User</h3>
        <button class="modal-close" data-close="add">&times;</button>
    </div>
    <div class="toast" id="addToast"></div>
    <form id="addUserForm" novalidate>
        <div class="modal-body">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Full Name <span class="required">*</span></label>
                    <input type="text" name="name" class="form-control" placeholder="e.g. Sarah Jones" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Email Address <span class="required">*</span></label>
                    <input type="email" name="email" class="form-control" placeholder="e.g. sarah@drugs4u.co.uk" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Role <span class="required">*</span></label>
                    <select name="role" class="form-control" required>
                        <option value="">— Select role —</option>
                        <?php foreach ($roles as $r): ?>
                            <option value="<?= $r ?>"><?= ucwords(str_replace('_', ' ', $r)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Branch <span class="required">*</span></label>
                    <select name="branch" class="form-control" required>
                        <option value="">— Select branch —</option>
                        <?php foreach ($branches as $b): ?>
                            <option value="<?= $b ?>"><?= $b ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Password <span class="required">*</span></label>
                    <input type="password" name="password" class="form-control" placeholder="Min. 8 characters" required minlength="8">
                </div>
                <div class="form-group">
                    <label class="form-label">Confirm Password <span class="required">*</span></label>
                    <input type="password" name="password_confirm" class="form-control" placeholder="Repeat password" required>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn" data-close="add" style="background:var(--border);color:var(--text);">Cancel</button>
            <button type="submit" class="btn btn-primary">Create User</button>
        </div>
    </form>
</div>

<!-- ── Edit User Modal ──────────────────────────────────── -->
<div class="modal-backdrop" id="editBackdrop"></div>
<div class="modal" id="editUserModal">
    <div class="modal-header">
        <h3 class="modal-title">Edit User</h3>
        <button class="modal-close" data-close="edit">&times;</button>
    </div>
    <div class="toast" id="editToast"></div>
    <form id="editUserForm" novalidate>
        <input type="hidden" name="user_id" id="edit_user_id">
        <div class="modal-body">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Full Name <span class="required">*</span></label>
                    <input type="text" name="name" id="edit_name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Email Address <span class="required">*</span></label>
                    <input type="email" name="email" id="edit_email" class="form-control" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Role <span class="required">*</span></label>
                    <select name="role" id="edit_role" class="form-control" required>
                        <?php foreach ($roles as $r): ?>
                            <option value="<?= $r ?>"><?= ucwords(str_replace('_', ' ', $r)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Branch <span class="required">*</span></label>
                    <select name="branch" id="edit_branch" class="form-control" required>
                        <?php foreach ($branches as $b): ?>
                            <option value="<?= $b ?>"><?= $b ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">New Password <span style="color:var(--text-muted);font-weight:400;">(leave blank to keep current)</span></label>
                <input type="password" name="password" class="form-control" placeholder="Min. 8 characters" minlength="8">
            </div>
            <div class="form-group">
                <label class="form-label">Account Status</label>
                <label class="toggle-switch">
                    <input type="checkbox" name="is_active" id="edit_active">
                    <span class="toggle-track"><span class="toggle-thumb"></span></span>
                    <span class="toggle-label" id="editActiveLabel">Active</span>
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
// ── Search ───────────────────────────────────────────────
document.getElementById('userSearch').addEventListener('input', function () {
    const q = this.value.toLowerCase().trim();
    const rows = document.querySelectorAll('#usersTable tbody tr');
    let visible = 0;
    rows.forEach(row => {
        const name  = row.cells[1]?.textContent.toLowerCase() ?? '';
        const email = row.cells[2]?.textContent.toLowerCase() ?? '';
        const show  = !q || name.includes(q) || email.includes(q);
        row.style.display = show ? '' : 'none';
        if (show) visible++;
    });
    document.getElementById('noUserResults').style.display = visible === 0 ? 'block' : 'none';
});

// ── Modal helpers ────────────────────────────────────────
function openModal(p) {
    document.getElementById(p + 'Backdrop').classList.add('open');
    document.getElementById(p + 'UserModal').classList.add('open');
}
function closeModal(p) {
    document.getElementById(p + 'Backdrop').classList.remove('open');
    document.getElementById(p + 'UserModal').classList.remove('open');
    document.getElementById(p + 'UserForm').reset();
    const t = document.getElementById(p + 'Toast');
    t.className = 'toast'; t.textContent = '';
}
function showToast(p, msg, type) {
    const t = document.getElementById(p + 'Toast');
    t.textContent = msg; t.className = 'toast show toast-' + type;
}

document.getElementById('openAddUser').addEventListener('click', () => openModal('add'));
document.querySelectorAll('[data-close]').forEach(btn =>
    btn.addEventListener('click', () => closeModal(btn.dataset.close))
);
document.getElementById('addBackdrop').addEventListener('click',  () => closeModal('add'));
document.getElementById('editBackdrop').addEventListener('click', () => closeModal('edit'));

// ── Edit modal populate ──────────────────────────────────
document.querySelectorAll('.edit-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.getElementById('edit_user_id').value = btn.dataset.id;
        document.getElementById('edit_name').value    = btn.dataset.name;
        document.getElementById('edit_email').value   = btn.dataset.email;

        [...document.getElementById('edit_role').options].forEach(o => { o.selected = o.value === btn.dataset.role; });
        [...document.getElementById('edit_branch').options].forEach(o => { o.selected = o.value === btn.dataset.branch; });

        const isActive = btn.dataset.active === '1';
        document.getElementById('edit_active').checked = isActive;
        document.getElementById('editActiveLabel').textContent = isActive ? 'Active' : 'Inactive';

        openModal('edit');
    });
});

document.getElementById('edit_active').addEventListener('change', function () {
    document.getElementById('editActiveLabel').textContent = this.checked ? 'Active' : 'Inactive';
});

// ── Form submit ──────────────────────────────────────────
async function submitForm(formId, action, prefix, defaultText) {
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
            btn.disabled = false; btn.textContent = defaultText;
        }
    } catch {
        showToast(prefix, 'Unexpected error. Please try again.', 'error');
        btn.disabled = false; btn.textContent = defaultText;
    }
}

document.getElementById('addUserForm').addEventListener('submit', e => {
    e.preventDefault();
    const pw  = e.target.querySelector('[name="password"]').value;
    const pw2 = e.target.querySelector('[name="password_confirm"]').value;
    if (pw !== pw2) { showToast('add', 'Passwords do not match.', 'error'); return; }
    submitForm('addUserForm', '/actions/add_user.php', 'add', 'Create User');
});
document.getElementById('editUserForm').addEventListener('submit', e => {
    e.preventDefault();
    submitForm('editUserForm', '/actions/edit_user.php', 'edit', 'Save Changes');
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
