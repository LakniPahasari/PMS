<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';
requireLogin();

$db = getDB();

$rules = $db->query("
    SELECT dr.*, m.medication_name AS medicine_name
    FROM DETECTION_RULE dr
    LEFT JOIN MEDICINE_STOCK m ON m.stock_id = dr.stock_id_filter
    ORDER BY dr.is_active DESC, dr.severity DESC, dr.created_at DESC
")->fetchAll();

$medicines  = $db->query("SELECT stock_id, medication_name, category FROM MEDICINE_STOCK WHERE is_active = 1 ORDER BY medication_name")->fetchAll();
$categories = ['Antibiotic','Painkiller','Antihistamine','Antidepressant','Controlled Drug','Vitamin / Supplement','Other'];

$totalRules    = count($rules);
$activeRules   = count(array_filter($rules, fn($r) => $r['is_active']));
$criticalRules = count(array_filter($rules, fn($r) => $r['is_active'] && $r['severity'] === 'critical'));
$triggeredToday = $db->query("SELECT COUNT(*) FROM ALERT WHERE alert_type = 'rule_violation' AND DATE(triggered_at) = CURDATE()")->fetchColumn();

$ruleTypeLabels = [
    'patient_frequency'  => 'Patient Frequency',
    'system_frequency'   => 'System Frequency',
    'quantity_threshold' => 'Quantity Threshold',
    'staff_volume'       => 'Staff Volume',
];

function ruleDescription(array $r, string $medicineName = ''): string {
    $target = $r['applies_to'] === 'all'      ? 'any medicine'
            : ($r['applies_to'] === 'category'  ? "category: {$r['category_filter']}"
            : "medicine: {$medicineName}");
    return match($r['rule_type']) {
        'patient_frequency'  => "Alert if same patient receives [{$target}] more than {$r['threshold_count']} time(s) within {$r['threshold_days']} day(s)",
        'system_frequency'   => "Alert if [{$target}] is dispensed more than {$r['threshold_count']} time(s) system-wide within {$r['threshold_days']} day(s)",
        'quantity_threshold' => "Alert if a single prescription contains more than {$r['threshold_qty']} unit(s) of [{$target}]",
        'staff_volume'       => "Alert if same staff member processes more than {$r['threshold_count']} prescription(s) containing [{$target}] within {$r['threshold_days']} day(s)",
        default              => '—',
    };
}

$pageTitle = 'Detection Rules';
require_once __DIR__ . '/../includes/header.php';
?>

<style>
.rule-type-badge {
    font-size: 11px; padding: 3px 8px; border-radius: 20px; font-weight: 600;
    white-space: nowrap;
}
.badge-patient_frequency  { background:#dbeafe; color:#1e40af; }
.badge-system_frequency   { background:#fce7f3; color:#9d174d; }
.badge-quantity_threshold { background:#fef3c7; color:#92400e; }
.badge-staff_volume       { background:#ede9fe; color:#5b21b6; }
.badge-warning  { background:#fef3c7; color:#92400e; }
.badge-critical { background:#fee2e2; color:#991b1b; }
.rule-desc { font-size: 12.5px; color: var(--text-muted); margin-top: 3px; }
.field-group { display:none; }
.field-group.visible { display:block; }
</style>

<!-- Stat cards -->
<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);">
    <div class="stat-card blue">
        <div class="stat-icon">📋</div>
        <div><div class="stat-value"><?= $totalRules ?></div><div class="stat-label">Total Rules</div></div>
    </div>
    <div class="stat-card green">
        <div class="stat-icon">✅</div>
        <div><div class="stat-value"><?= $activeRules ?></div><div class="stat-label">Active Rules</div></div>
    </div>
    <div class="stat-card red">
        <div class="stat-icon">🚨</div>
        <div><div class="stat-value"><?= $criticalRules ?></div><div class="stat-label">Critical Rules</div></div>
    </div>
    <div class="stat-card yellow">
        <div class="stat-icon">🔔</div>
        <div><div class="stat-value"><?= $triggeredToday ?></div><div class="stat-label">Triggered Today</div></div>
    </div>
</div>

<!-- Toolbar -->
<div style="display:flex;align-items:center;gap:12px;margin-bottom:20px;">
    <div style="flex:1;">
        <div style="font-size:13px;color:var(--text-muted);">
            Rules run automatically when a prescription is created or processed. Any triggered rule generates an alert.
        </div>
    </div>
    <button class="btn btn-primary" id="openAddRule">+ New Rule</button>
</div>

<!-- Rules list -->
<div class="card">
    <div class="card-body">
        <?php if ($rules): ?>
        <div class="table-wrap">
            <table id="rulesTable">
                <thead>
                    <tr>
                        <th>Rule Name</th>
                        <th>Type</th>
                        <th>Condition</th>
                        <th>Severity</th>
                        <th style="text-align:center;">Active</th>
                        <th style="width:100px;text-align:center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rules as $r): ?>
                    <tr style="<?= !$r['is_active'] ? 'opacity:.5;' : '' ?>">
                        <td>
                            <strong><?= htmlspecialchars($r['rule_name']) ?></strong>
                            <?php if ($r['description']): ?>
                                <div class="rule-desc"><?= htmlspecialchars($r['description']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="rule-type-badge badge-<?= $r['rule_type'] ?>">
                                <?= $ruleTypeLabels[$r['rule_type']] ?? $r['rule_type'] ?>
                            </span>
                        </td>
                        <td>
                            <div class="rule-desc" style="color:var(--text);font-size:13px;">
                                <?= htmlspecialchars(ruleDescription($r, $r['medicine_name'] ?? '')) ?>
                            </div>
                        </td>
                        <td>
                            <span class="badge badge-<?= $r['severity'] ?>">
                                <?= ucfirst($r['severity']) ?>
                            </span>
                        </td>
                        <td style="text-align:center;">
                            <button class="btn toggle-btn" data-id="<?= $r['rule_id'] ?>" data-active="<?= $r['is_active'] ?>"
                                style="font-size:12px;padding:4px 12px;
                                       background:<?= $r['is_active'] ? '#d1fae5' : '#f3f4f6' ?>;
                                       color:<?= $r['is_active'] ? '#065f46' : '#6b7280' ?>;
                                       border:none;border-radius:20px;cursor:pointer;">
                                <?= $r['is_active'] ? 'On' : 'Off' ?>
                            </button>
                        </td>
                        <td style="text-align:center;display:flex;gap:6px;justify-content:center;">
                            <button class="btn-icon edit-btn" title="Edit rule"
                                data-id="<?= $r['rule_id'] ?>"
                                data-name="<?= htmlspecialchars($r['rule_name'], ENT_QUOTES) ?>"
                                data-desc="<?= htmlspecialchars($r['description'] ?? '', ENT_QUOTES) ?>"
                                data-type="<?= $r['rule_type'] ?>"
                                data-applies="<?= $r['applies_to'] ?>"
                                data-category="<?= htmlspecialchars($r['category_filter'] ?? '', ENT_QUOTES) ?>"
                                data-medicine="<?= $r['stock_id_filter'] ?? '' ?>"
                                data-count="<?= $r['threshold_count'] ?? '' ?>"
                                data-days="<?= $r['threshold_days'] ?? '' ?>"
                                data-qty="<?= $r['threshold_qty'] ?? '' ?>"
                                data-severity="<?= $r['severity'] ?>">✏️</button>
                            <button class="btn-icon delete-btn" title="Delete rule"
                                data-id="<?= $r['rule_id'] ?>"
                                data-name="<?= htmlspecialchars($r['rule_name'], ENT_QUOTES) ?>">🗑</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
            <div class="empty-state">
                No detection rules yet. Click <strong>+ New Rule</strong> to create your first rule.
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ── Add Rule Modal ───────────────────────────────────── -->
<div class="modal-backdrop" id="addBackdrop"></div>
<div class="modal" id="addRuleModal" style="max-width:560px;">
    <div class="modal-header">
        <h3 class="modal-title">New Detection Rule</h3>
        <button class="modal-close" data-close="add">&times;</button>
    </div>
    <div class="toast" id="addToast"></div>
    <form id="addRuleForm" novalidate>
        <div class="modal-body">
            <?php include __DIR__ . '/../includes/rule_form_fields.php'; ?>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn" data-close="add" style="background:var(--border);color:var(--text);">Cancel</button>
            <button type="submit" class="btn btn-primary">Save Rule</button>
        </div>
    </form>
</div>

<!-- ── Edit Rule Modal ──────────────────────────────────── -->
<div class="modal-backdrop" id="editBackdrop"></div>
<div class="modal" id="editRuleModal" style="max-width:560px;">
    <div class="modal-header">
        <h3 class="modal-title">Edit Rule</h3>
        <button class="modal-close" data-close="edit">&times;</button>
    </div>
    <div class="toast" id="editToast"></div>
    <form id="editRuleForm" novalidate>
        <input type="hidden" name="rule_id" id="edit_rule_id">
        <div class="modal-body">
            <?php include __DIR__ . '/../includes/rule_form_fields.php'; ?>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn" data-close="edit" style="background:var(--border);color:var(--text);">Cancel</button>
            <button type="submit" class="btn btn-primary">Save Changes</button>
        </div>
    </form>
</div>

<script>
const medicines  = <?= json_encode(array_column($medicines, null, 'stock_id')) ?>;
const categories = <?= json_encode($categories) ?>;

// ── Modal helpers ────────────────────────────────────────
function openModal(p) {
    document.getElementById(p + 'Backdrop').classList.add('open');
    document.getElementById(p + 'RuleModal').classList.add('open');
}
function closeModal(p) {
    document.getElementById(p + 'Backdrop').classList.remove('open');
    document.getElementById(p + 'RuleModal').classList.remove('open');
    document.getElementById(p + 'RuleForm').reset();
    const t = document.getElementById(p + 'Toast');
    t.className = 'toast'; t.textContent = '';
    updateConditionalFields(p);
}
function showToast(p, msg, type) {
    const t = document.getElementById(p + 'Toast');
    t.textContent = msg; t.className = 'toast show toast-' + type;
}

document.getElementById('openAddRule').addEventListener('click', () => { openModal('add'); updateConditionalFields('add'); });
document.querySelectorAll('[data-close]').forEach(btn => btn.addEventListener('click', () => closeModal(btn.dataset.close)));
document.getElementById('addBackdrop').addEventListener('click',  () => closeModal('add'));
document.getElementById('editBackdrop').addEventListener('click', () => closeModal('edit'));

// ── Conditional field visibility ─────────────────────────
function updateConditionalFields(prefix) {
    const form     = document.getElementById(prefix + 'RuleForm');
    const ruleType = form.querySelector('[name="rule_type"]')?.value   || '';
    const appliesTo= form.querySelector('[name="applies_to"]')?.value  || '';

    // Count + days shown for frequency and staff_volume rules
    const showCountDays = ['patient_frequency','system_frequency','staff_volume'].includes(ruleType);
    // Qty shown only for quantity_threshold
    const showQty = ruleType === 'quantity_threshold';

    form.querySelectorAll('.fg-count-days').forEach(el => el.classList.toggle('visible', showCountDays));
    form.querySelectorAll('.fg-qty').forEach(el => el.classList.toggle('visible', showQty));
    form.querySelectorAll('.fg-category').forEach(el => el.classList.toggle('visible', appliesTo === 'category'));
    form.querySelectorAll('.fg-medicine').forEach(el => el.classList.toggle('visible', appliesTo === 'medicine'));
}

['add','edit'].forEach(p => {
    const form = document.getElementById(p + 'RuleForm');
    form.querySelector('[name="rule_type"]')?.addEventListener('change',  () => updateConditionalFields(p));
    form.querySelector('[name="applies_to"]')?.addEventListener('change', () => updateConditionalFields(p));
});

// ── Populate edit modal ──────────────────────────────────
document.querySelectorAll('.edit-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        const f = document.getElementById('editRuleForm');
        document.getElementById('edit_rule_id').value         = btn.dataset.id;
        f.querySelector('[name="rule_name"]').value           = btn.dataset.name;
        f.querySelector('[name="description"]').value         = btn.dataset.desc;
        f.querySelector('[name="rule_type"]').value           = btn.dataset.type;
        f.querySelector('[name="applies_to"]').value          = btn.dataset.applies;
        f.querySelector('[name="category_filter"]').value     = btn.dataset.category;
        f.querySelector('[name="threshold_count"]').value     = btn.dataset.count;
        f.querySelector('[name="threshold_days"]').value      = btn.dataset.days;
        f.querySelector('[name="threshold_qty"]').value       = btn.dataset.qty;
        f.querySelector('[name="severity"]').value            = btn.dataset.severity;

        // Set medicine select
        const medSel = f.querySelector('[name="stock_id_filter"]');
        [...medSel.options].forEach(o => { o.selected = o.value == btn.dataset.medicine; });

        openModal('edit');
        updateConditionalFields('edit');
    });
});

// ── Toggle active ────────────────────────────────────────
document.querySelectorAll('.toggle-btn').forEach(btn => {
    btn.addEventListener('click', async () => {
        btn.disabled = true;
        const fd = new FormData();
        fd.append('rule_id', btn.dataset.id);
        fd.append('active',  btn.dataset.active === '1' ? '0' : '1');
        const res  = await fetch('/actions/toggle_rule.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) location.reload();
        else { alert(data.message); btn.disabled = false; }
    });
});

// ── Delete rule ──────────────────────────────────────────
document.querySelectorAll('.delete-btn').forEach(btn => {
    btn.addEventListener('click', async () => {
        if (!confirm(`Delete rule "${btn.dataset.name}"? This cannot be undone.`)) return;
        const fd = new FormData();
        fd.append('rule_id', btn.dataset.id);
        const res  = await fetch('/actions/delete_rule.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) location.reload();
        else alert(data.message);
    });
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
            setTimeout(() => { closeModal(prefix); location.reload(); }, 1200);
        } else {
            showToast(prefix, data.message, 'error');
            btn.disabled = false; btn.textContent = defaultText;
        }
    } catch {
        showToast(prefix, 'Unexpected error.', 'error');
        btn.disabled = false; btn.textContent = defaultText;
    }
}

document.getElementById('addRuleForm').addEventListener('submit', e => { e.preventDefault(); submitForm('addRuleForm', '/actions/add_rule.php', 'add', 'Save Rule'); });
document.getElementById('editRuleForm').addEventListener('submit', e => { e.preventDefault(); submitForm('editRuleForm', '/actions/edit_rule.php', 'edit', 'Save Changes'); });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
