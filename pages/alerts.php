<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';
requireLogin();

$db = getDB();

$alerts = $db->query("
    SELECT alert_id, alert_type, message, is_acknowledged, triggered_at
    FROM ALERT
    ORDER BY triggered_at DESC
")->fetchAll();

$total       = count($alerts);
$unacknowledged = 0;
foreach ($alerts as $a) {
    if (!$a['is_acknowledged']) $unacknowledged++;
}
$acknowledged = $total - $unacknowledged;

$typeStyles = [
    'low_stock'       => ['bg' => '#fef3c7', 'text' => '#92400e', 'label' => 'Low Stock'],
    'age_restriction' => ['bg' => '#fee2e2', 'text' => '#991b1b', 'label' => 'Age Restriction'],
    'expiry'          => ['bg' => '#fde68a', 'text' => '#78350f', 'label' => 'Expiry Warning'],
];

$pageTitle = 'Alerts';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Stat cards -->
<div class="stats-grid" style="grid-template-columns:repeat(3,1fr);">
    <div class="stat-card blue">
        <div class="stat-icon">🔔</div>
        <div><div class="stat-value"><?= $total ?></div><div class="stat-label">Total Alerts</div></div>
    </div>
    <div class="stat-card red">
        <div class="stat-icon">⚠️</div>
        <div><div class="stat-value"><?= $unacknowledged ?></div><div class="stat-label">Unacknowledged</div></div>
    </div>
    <div class="stat-card green">
        <div class="stat-icon">✅</div>
        <div><div class="stat-value"><?= $acknowledged ?></div><div class="stat-label">Acknowledged</div></div>
    </div>
</div>

<!-- Toolbar -->
<div style="display:flex;align-items:center;gap:10px;margin-bottom:16px;flex-wrap:wrap;">
    <div style="display:flex;gap:6px;" id="alertTabs">
        <button class="btn tab-btn active" data-filter="all">All (<?= $total ?>)</button>
        <button class="btn tab-btn" data-filter="0" style="background:#fee2e2;color:#991b1b;">Unacknowledged (<?= $unacknowledged ?>)</button>
        <button class="btn tab-btn" data-filter="1" style="background:#d1fae5;color:#065f46;">Acknowledged (<?= $acknowledged ?>)</button>
    </div>
    <?php if ($unacknowledged > 0): ?>
    <button class="btn" id="ackAllBtn"
        style="margin-left:auto;background:#fff;border:1px solid var(--border);color:var(--text);">
        ✓ Acknowledge All
    </button>
    <?php endif; ?>
</div>

<!-- Alerts list -->
<div class="card">
    <div class="card-body">
        <?php if ($alerts): ?>
        <div id="alertsList">
            <?php foreach ($alerts as $a):
                $ts = $typeStyles[$a['alert_type']] ?? ['bg' => '#f3f4f6', 'text' => '#6b7280', 'label' => ucwords(str_replace('_',' ',$a['alert_type']))];
            ?>
            <div class="alert-row" data-id="<?= $a['alert_id'] ?>" data-ack="<?= $a['is_acknowledged'] ?>"
                 style="display:flex;align-items:flex-start;gap:14px;padding:14px 16px;border-bottom:1px solid var(--border);<?= $a['is_acknowledged'] ? 'opacity:.55;' : '' ?>">
                <div style="flex-shrink:0;margin-top:2px;">
                    <?php if ($a['is_acknowledged']): ?>
                        <span style="color:var(--success);font-size:16px;">✅</span>
                    <?php else: ?>
                        <span style="width:10px;height:10px;border-radius:50%;background:var(--danger);display:inline-block;margin-top:3px;"></span>
                    <?php endif; ?>
                </div>
                <div style="flex:1;min-width:0;">
                    <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;flex-wrap:wrap;">
                        <span class="badge" style="background:<?= $ts['bg'] ?>;color:<?= $ts['text'] ?>;">
                            <?= $ts['label'] ?>
                        </span>
                        <span style="font-size:12px;color:var(--text-muted);">
                            <?= date('d M Y, H:i', strtotime($a['triggered_at'])) ?>
                        </span>
                    </div>
                    <div style="font-size:13.5px;color:var(--text);"><?= htmlspecialchars($a['message']) ?></div>
                </div>
                <?php if (!$a['is_acknowledged']): ?>
                <button class="btn ack-btn" data-id="<?= $a['alert_id'] ?>"
                    style="flex-shrink:0;font-size:12px;padding:5px 12px;background:#fff;border:1px solid var(--border);color:var(--text-muted);">
                    Acknowledge
                </button>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <div id="noAlertResults" style="display:none;" class="empty-state">No alerts match this filter.</div>
        <?php else: ?>
            <div class="empty-state">No alerts. Everything looks good! ✅</div>
        <?php endif; ?>
    </div>
</div>

<style>
.tab-btn { font-size:13px; padding:7px 14px; border-radius:8px; }
.tab-btn.active { background:var(--primary) !important; color:#fff !important; }
</style>

<script>
// ── Filter tabs ──────────────────────────────────────────
document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        const filter = btn.dataset.filter;
        const rows   = document.querySelectorAll('.alert-row');
        let visible  = 0;
        rows.forEach(row => {
            const show = filter === 'all' || row.dataset.ack === filter;
            row.style.display = show ? '' : 'none';
            if (show) visible++;
        });
        document.getElementById('noAlertResults').style.display = visible === 0 ? 'block' : 'none';
    });
});

// ── Single acknowledge ───────────────────────────────────
document.querySelectorAll('.ack-btn').forEach(btn => {
    btn.addEventListener('click', () => acknowledgeAlert(btn.dataset.id, btn));
});

async function acknowledgeAlert(alertId, btn) {
    if (btn) { btn.disabled = true; btn.textContent = '...'; }
    const fd = new FormData();
    fd.append('alert_id', alertId);
    try {
        const res  = await fetch('/actions/acknowledge_alert.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
            location.reload();
        }
    } catch {}
}

// ── Acknowledge all ──────────────────────────────────────
const ackAllBtn = document.getElementById('ackAllBtn');
if (ackAllBtn) {
    ackAllBtn.addEventListener('click', async () => {
        ackAllBtn.disabled = true; ackAllBtn.textContent = 'Working...';
        const fd = new FormData();
        fd.append('all', '1');
        try {
            const res  = await fetch('/actions/acknowledge_alert.php', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) location.reload();
        } catch {}
    });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
