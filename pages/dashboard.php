<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';
requireLogin();

$db   = getDB();
$user = currentUser();

// ── Stat queries ─────────────────────────────────────────
$pending = $db->query("SELECT COUNT(*) FROM PRESCRIPTION WHERE status = 'pending'")->fetchColumn();
$lowStock = $db->query("SELECT COUNT(*) FROM MEDICINE_STOCK WHERE quantity < 20 AND is_active = 1")->fetchColumn();
$alerts  = $db->query("SELECT COUNT(*) FROM ALERT WHERE is_acknowledged = 0")->fetchColumn();
$customers = $db->query("SELECT COUNT(*) FROM CUSTOMER WHERE account_active = 1")->fetchColumn();

// ── Recent prescriptions ──────────────────────────────────
$recentPrescriptions = $db->query("
    SELECT p.prescription_id, c.name AS customer,
           GROUP_CONCAT(m.medication_name ORDER BY m.medication_name SEPARATOR ', ') AS medication_name,
           p.status, p.created_at
    FROM PRESCRIPTION p
    JOIN CUSTOMER c           ON c.customer_id      = p.customer_id
    JOIN PRESCRIPTION_ITEM pi ON pi.prescription_id = p.prescription_id
    JOIN MEDICINE_STOCK m     ON m.stock_id          = pi.stock_id
    GROUP BY p.prescription_id
    ORDER BY p.created_at DESC
    LIMIT 5
")->fetchAll();

// ── Recent alerts ─────────────────────────────────────────
$recentAlerts = $db->query("
    SELECT alert_type, message, is_acknowledged, triggered_at
    FROM ALERT
    ORDER BY triggered_at DESC
    LIMIT 5
")->fetchAll();

$pageTitle = 'Dashboard';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Stat cards -->
<div class="stats-grid">
    <div class="stat-card yellow">
        <div class="stat-icon">📋</div>
        <div>
            <div class="stat-value"><?= $pending ?></div>
            <div class="stat-label">Pending Prescriptions</div>
        </div>
    </div>
    <div class="stat-card red">
        <div class="stat-icon">💊</div>
        <div>
            <div class="stat-value"><?= $lowStock ?></div>
            <div class="stat-label">Low Stock Items</div>
        </div>
    </div>
    <div class="stat-card blue">
        <div class="stat-icon">🔔</div>
        <div>
            <div class="stat-value"><?= $alerts ?></div>
            <div class="stat-label">Unacknowledged Alerts</div>
        </div>
    </div>
    <div class="stat-card green">
        <div class="stat-icon">👤</div>
        <div>
            <div class="stat-value"><?= $customers ?></div>
            <div class="stat-label">Active Customers</div>
        </div>
    </div>
</div>

<!-- Two-column section -->
<div class="two-col">

    <!-- Recent Prescriptions -->
    <div class="card">
        <div class="card-header">
            Recent Prescriptions
            <a href="/pages/prescriptions.php" class="btn btn-primary btn-sm">View All</a>
        </div>
        <div class="card-body">
            <?php if ($recentPrescriptions): ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Customer</th>
                            <th>Medicine</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentPrescriptions as $rx): ?>
                        <tr>
                            <td><?= $rx['prescription_id'] ?></td>
                            <td><?= htmlspecialchars($rx['customer']) ?></td>
                            <td><?= htmlspecialchars($rx['medication_name']) ?></td>
                            <td>
                                <span class="badge badge-<?= $rx['status'] ?>">
                                    <?= $rx['status'] ?>
                                </span>
                            </td>
                            <td><?= date('d M Y', strtotime($rx['created_at'])) ?></td>
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

    <!-- Recent Alerts -->
    <div class="card">
        <div class="card-header">
            Recent Alerts
            <a href="/pages/alerts.php" class="btn btn-primary btn-sm">View All</a>
        </div>
        <div class="card-body">
            <?php if ($recentAlerts): ?>
                <?php foreach ($recentAlerts as $alert): ?>
                <div class="alert-item">
                    <div class="alert-dot <?= $alert['is_acknowledged'] ? 'ack' : '' ?>"></div>
                    <div>
                        <div class="alert-msg">
                            <span class="badge badge-<?= $alert['alert_type'] ?>" style="margin-right:6px;">
                                <?= str_replace('_', ' ', $alert['alert_type']) ?>
                            </span>
                            <?= htmlspecialchars($alert['message']) ?>
                        </div>
                        <div class="alert-time">
                            <?= date('d M Y, H:i', strtotime($alert['triggered_at'])) ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">No alerts.</div>
            <?php endif; ?>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
