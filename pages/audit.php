<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';
requireLogin();

$db = getDB();

$logs = $db->query("
    SELECT al.log_id, al.action_type, al.target_table, al.target_id,
           al.details, al.timestamp,
           su.name AS user_name, su.role AS user_role
    FROM AUDIT_LOG al
    LEFT JOIN SYSTEM_USER su ON su.system_user_id = al.system_user_id
    ORDER BY al.timestamp DESC
    LIMIT 500
")->fetchAll();

$actionLabels = [
    'login'                => ['label' => 'Login',               'color' => '#ede9fe', 'text' => '#5b21b6'],
    'prescription_created' => ['label' => 'Prescription Added',  'color' => '#dbeafe', 'text' => '#1e40af'],
    'prescription_updated' => ['label' => 'Prescription Edited', 'color' => '#e0f2fe', 'text' => '#0369a1'],
    'stock_created'        => ['label' => 'Stock Added',         'color' => '#d1fae5', 'text' => '#065f46'],
    'stock_updated'        => ['label' => 'Stock Updated',       'color' => '#dcfce7', 'text' => '#166534'],
    'payment_created'      => ['label' => 'Payment Recorded',    'color' => '#fef3c7', 'text' => '#92400e'],
    'payment_updated'      => ['label' => 'Payment Updated',     'color' => '#fef9c3', 'text' => '#713f12'],
    'user_created'         => ['label' => 'User Created',        'color' => '#fce7f3', 'text' => '#9d174d'],
    'user_updated'         => ['label' => 'User Updated',        'color' => '#fdf2f8', 'text' => '#831843'],
];

$pageTitle = 'Audit Log';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Toolbar -->
<div style="display:flex;align-items:center;gap:12px;margin-bottom:20px;flex-wrap:wrap;">
    <input type="text" id="auditSearch" class="form-control"
           placeholder="Search by user, action or table..." style="max-width:300px;">
    <select id="auditFilter" class="form-control" style="max-width:200px;">
        <option value="">— All actions —</option>
        <?php foreach ($actionLabels as $key => $a): ?>
            <option value="<?= $key ?>"><?= $a['label'] ?></option>
        <?php endforeach; ?>
    </select>
    <span style="color:var(--text-muted);font-size:13px;flex:1;">
        Showing latest <?= count($logs) ?> entries
    </span>
</div>

<!-- Audit table -->
<div class="card">
    <div class="card-body">
        <?php if ($logs): ?>
        <div class="table-wrap">
            <table id="auditTable">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Timestamp</th>
                        <th>User</th>
                        <th>Action</th>
                        <th>Table</th>
                        <th>Record ID</th>
                        <th>Changes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log):
                        $a = $actionLabels[$log['action_type']] ?? ['label' => str_replace('_',' ',$log['action_type']), 'color' => '#f3f4f6', 'text' => '#6b7280'];
                        $details = $log['details'] ? json_decode($log['details'], true) : null;
                    ?>
                    <tr data-action="<?= $log['action_type'] ?>">
                        <td style="color:var(--text-muted);font-size:12px;"><?= $log['log_id'] ?></td>
                        <td style="font-size:13px;white-space:nowrap;">
                            <?= date('d M Y', strtotime($log['timestamp'])) ?><br>
                            <span style="color:var(--text-muted);font-size:11px;"><?= date('H:i:s', strtotime($log['timestamp'])) ?></span>
                        </td>
                        <td>
                            <?php if ($log['user_name']): ?>
                                <strong><?= htmlspecialchars($log['user_name']) ?></strong><br>
                                <span style="font-size:11px;color:var(--text-muted);text-transform:capitalize;"><?= str_replace('_',' ',$log['user_role'] ?? '') ?></span>
                            <?php else: ?>
                                <span style="color:var(--text-muted)">System</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge" style="background:<?= $a['color'] ?>;color:<?= $a['text'] ?>;">
                                <?= $a['label'] ?>
                            </span>
                        </td>
                        <td style="font-size:13px;color:var(--text-muted);"><?= htmlspecialchars($log['target_table'] ?? '—') ?></td>
                        <td style="font-size:13px;text-align:center;"><?= $log['target_id'] ? '#'.$log['target_id'] : '—' ?></td>
                        <td style="font-size:12px;max-width:260px;">
                            <?php if ($details): ?>
                                <?php foreach ($details as $field => $val): ?>
                                    <?php if (is_array($val) && isset($val['before'], $val['after'])): ?>
                                        <div style="margin-bottom:2px;">
                                            <span style="color:var(--text-muted);"><?= htmlspecialchars(ucwords(str_replace('_',' ',$field))) ?>:</span>
                                            <span style="color:#991b1b;text-decoration:line-through;"><?= htmlspecialchars($val['before']) ?></span>
                                            → <span style="color:#065f46;"><?= htmlspecialchars($val['after']) ?></span>
                                        </div>
                                    <?php else: ?>
                                        <div style="color:var(--text-muted);"><?= htmlspecialchars($field) ?>: <?= htmlspecialchars(is_array($val) ? json_encode($val) : $val) ?></div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <span style="color:var(--text-muted);">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div id="noAuditResults" style="display:none;" class="empty-state">No entries match your search.</div>
        <?php else: ?>
            <div class="empty-state">No audit log entries yet.</div>
        <?php endif; ?>
    </div>
</div>

<script>
function applyAuditFilter() {
    const q      = document.getElementById('auditSearch').value.toLowerCase().trim();
    const action = document.getElementById('auditFilter').value;
    const rows   = document.querySelectorAll('#auditTable tbody tr');
    let visible  = 0;
    rows.forEach(row => {
        const text       = row.textContent.toLowerCase();
        const rowAction  = row.dataset.action;
        const matchQ     = !q      || text.includes(q);
        const matchA     = !action || rowAction === action;
        const show       = matchQ && matchA;
        row.style.display = show ? '' : 'none';
        if (show) visible++;
    });
    document.getElementById('noAuditResults').style.display = visible === 0 ? 'block' : 'none';
}
document.getElementById('auditSearch').addEventListener('input',  applyAuditFilter);
document.getElementById('auditFilter').addEventListener('change', applyAuditFilter);
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
