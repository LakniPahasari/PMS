<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';
requireLogin();

$db  = getDB();
$tab = $_GET['tab'] ?? 'inventory';

// ══════════════════════════════════════════════════════════
// INVENTORY FILTERS
// ══════════════════════════════════════════════════════════
$invCategory  = $_GET['inv_category']  ?? '';
$invStatus    = $_GET['inv_status']    ?? 'active';
$invLevel     = $_GET['inv_level']     ?? 'all';
$invSort      = $_GET['inv_sort']      ?? 'name';
$categories   = ['Antibiotic','Painkiller','Antihistamine','Antidepressant','Controlled Drug','Vitamin / Supplement','Other'];

$invWhere = ['1=1'];
$invParams = [];
if ($invCategory) { $invWhere[] = 'category = ?'; $invParams[] = $invCategory; }
if ($invStatus === 'active')   { $invWhere[] = 'is_active = 1'; }
if ($invStatus === 'inactive') { $invWhere[] = 'is_active = 0'; }
if ($invLevel === 'low')       { $invWhere[] = 'quantity > 0 AND quantity < 10'; }
if ($invLevel === 'out')       { $invWhere[] = 'quantity = 0'; }
if ($invLevel === 'expiring')  { $invWhere[] = 'expiry_date IS NOT NULL AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)'; }
if ($invLevel === 'expired')   { $invWhere[] = 'expiry_date IS NOT NULL AND expiry_date < CURDATE()'; }

$invOrderMap = [
    'name'      => 'medication_name ASC',
    'qty_asc'   => 'quantity ASC',
    'qty_desc'  => 'quantity DESC',
    'expiry'    => 'expiry_date ASC',
    'category'  => 'category ASC, medication_name ASC',
    'price'     => 'unit_price DESC',
];
$invOrder = $invOrderMap[$invSort] ?? 'medication_name ASC';

$invStmt = $db->prepare("SELECT * FROM MEDICINE_STOCK WHERE " . implode(' AND ', $invWhere) . " ORDER BY {$invOrder}");
$invStmt->execute($invParams);
$medicines = $invStmt->fetchAll();

// Inventory summary counts
$invSummary = $db->query("
    SELECT
        COUNT(*) AS total,
        SUM(is_active) AS active,
        SUM(quantity = 0 AND is_active = 1) AS out_of_stock,
        SUM(quantity > 0 AND quantity < 10 AND is_active = 1) AS low_stock,
        SUM(expiry_date IS NOT NULL AND expiry_date < CURDATE() AND is_active = 1) AS expired,
        SUM(expiry_date IS NOT NULL AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) AND is_active = 1) AS expiring_soon,
        ROUND(SUM(quantity * COALESCE(unit_price, 0)), 2) AS total_value
    FROM MEDICINE_STOCK
")->fetch();

// ══════════════════════════════════════════════════════════
// ALERT FILTERS
// ══════════════════════════════════════════════════════════
$alrtType   = $_GET['alrt_type']   ?? '';
$alrtStatus = $_GET['alrt_status'] ?? 'all';
$alrtPeriod = $_GET['alrt_period'] ?? 'today';
$alrtFrom   = $_GET['alrt_from']   ?? '';
$alrtTo     = $_GET['alrt_to']     ?? '';
$alrtSort   = $_GET['alrt_sort']   ?? 'newest';

$alrtWhere = ['1=1'];
$alrtParams = [];
if ($alrtType)              { $alrtWhere[] = 'alert_type = ?'; $alrtParams[] = $alrtType; }
if ($alrtStatus === 'unack') { $alrtWhere[] = 'is_acknowledged = 0'; }
if ($alrtStatus === 'ack')   { $alrtWhere[] = 'is_acknowledged = 1'; }
if ($alrtPeriod === 'today') { $alrtWhere[] = 'DATE(triggered_at) = CURDATE()'; }
elseif ($alrtPeriod === 'week')  { $alrtWhere[] = 'triggered_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)'; }
elseif ($alrtPeriod === 'month') { $alrtWhere[] = 'triggered_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)'; }
elseif ($alrtPeriod === 'custom' && $alrtFrom && $alrtTo) {
    $alrtWhere[] = 'DATE(triggered_at) BETWEEN ? AND ?';
    $alrtParams[] = $alrtFrom;
    $alrtParams[] = $alrtTo;
}
$alrtOrderMap = [
    'newest'   => 'triggered_at DESC',
    'oldest'   => 'triggered_at ASC',
    'type'     => 'alert_type ASC, triggered_at DESC',
    'unack'    => 'is_acknowledged ASC, triggered_at DESC',
];
$alrtOrder = $alrtOrderMap[$alrtSort] ?? 'triggered_at DESC';

$alrtStmt = $db->prepare("SELECT * FROM ALERT WHERE " . implode(' AND ', $alrtWhere) . " ORDER BY {$alrtOrder}");
$alrtStmt->execute($alrtParams);
$alerts = $alrtStmt->fetchAll();

$alrtSummary = $db->query("
    SELECT
        COUNT(*) AS total,
        SUM(is_acknowledged = 0) AS unacknowledged,
        SUM(alert_type = 'low_stock') AS low_stock,
        SUM(alert_type = 'age_restriction') AS age_restriction,
        SUM(alert_type = 'rule_violation') AS rule_violation
    FROM ALERT
    WHERE DATE(triggered_at) = CURDATE()
")->fetch();

// ══════════════════════════════════════════════════════════
// SALES FILTERS
// ══════════════════════════════════════════════════════════
$salePeriod = $_GET['sale_period'] ?? 'month';
$saleFrom   = $_GET['sale_from']   ?? '';
$saleTo     = $_GET['sale_to']     ?? '';
$saleStatus = $_GET['sale_status'] ?? 'all';
$saleMethod = $_GET['sale_method'] ?? 'all';

$saleWhere = ['1=1'];
$saleParams = [];
if ($salePeriod === 'today')  { $saleWhere[] = 'DATE(pay.billed_date) = CURDATE()'; }
elseif ($salePeriod === 'week')  { $saleWhere[] = 'pay.billed_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)'; }
elseif ($salePeriod === 'month') { $saleWhere[] = 'pay.billed_date >= DATE_FORMAT(CURDATE(),\'%Y-%m-01\')'; }
elseif ($salePeriod === 'last_month') { $saleWhere[] = 'DATE_FORMAT(pay.billed_date,\'%Y-%m\') = DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 MONTH),\'%Y-%m\')'; }
elseif ($salePeriod === 'year')  { $saleWhere[] = 'YEAR(pay.billed_date) = YEAR(CURDATE())'; }
elseif ($salePeriod === 'custom' && $saleFrom && $saleTo) {
    $saleWhere[] = 'DATE(pay.billed_date) BETWEEN ? AND ?';
    $saleParams[] = $saleFrom;
    $saleParams[] = $saleTo;
}
if ($saleStatus !== 'all') { $saleWhere[] = 'pay.payment_status = ?'; $saleParams[] = $saleStatus; }
if ($saleMethod !== 'all') { $saleWhere[] = 'pay.payment_method = ?'; $saleParams[] = $saleMethod; }

$saleWhereStr = implode(' AND ', $saleWhere);

$saleStmt = $db->prepare("
    SELECT pay.payment_id, pay.prescription_id, pay.amount, pay.payment_method,
           pay.payment_status, pay.billed_date, pay.paid_at,
           c.name AS customer_name, c.customer_id
    FROM PAYMENT pay
    JOIN PRESCRIPTION p ON p.prescription_id = pay.prescription_id
    JOIN CUSTOMER c     ON c.customer_id      = p.customer_id
    WHERE {$saleWhereStr}
    ORDER BY pay.billed_date DESC, pay.payment_id DESC
");
$saleStmt->execute($saleParams);
$payments = $saleStmt->fetchAll();

// Sales summary for filtered period
$saleSumStmt = $db->prepare("
    SELECT
        COUNT(*) AS total_transactions,
        SUM(CASE WHEN pay.payment_status='paid' THEN COALESCE(pay.amount,0) ELSE 0 END) AS total_revenue,
        SUM(pay.payment_status='paid')            AS paid_count,
        SUM(pay.payment_status='unpaid')          AS unpaid_count,
        SUM(pay.payment_status='awaiting_pickup') AS awaiting_count,
        SUM(CASE WHEN pay.payment_method='cash' AND pay.payment_status='paid' THEN COALESCE(pay.amount,0) ELSE 0 END) AS cash_revenue,
        SUM(CASE WHEN pay.payment_method='card' AND pay.payment_status='paid' THEN COALESCE(pay.amount,0) ELSE 0 END) AS card_revenue,
        SUM(pay.payment_status='unpaid' OR pay.payment_status='awaiting_pickup') AS outstanding_count,
        SUM(CASE WHEN pay.payment_status!='paid' THEN COALESCE(pay.amount,0) ELSE 0 END) AS outstanding_amount
    FROM PAYMENT pay
    JOIN PRESCRIPTION p ON p.prescription_id = pay.prescription_id
    JOIN CUSTOMER c     ON c.customer_id      = p.customer_id
    WHERE {$saleWhereStr}
");
$saleSumStmt->execute($saleParams);
$saleSum = $saleSumStmt->fetch();

// Monthly trend (last 6 months)
$monthlyTrend = $db->query("
    SELECT
        DATE_FORMAT(billed_date,'%Y-%m') AS ym,
        DATE_FORMAT(billed_date,'%b %Y') AS label,
        COUNT(*) AS transactions,
        SUM(CASE WHEN payment_status='paid' THEN COALESCE(amount,0) ELSE 0 END) AS revenue,
        SUM(payment_status='paid')            AS paid,
        SUM(payment_status='unpaid')          AS unpaid,
        SUM(payment_status='awaiting_pickup') AS awaiting
    FROM PAYMENT
    WHERE billed_date >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 5 MONTH),'%Y-%m-01')
    GROUP BY ym, label
    ORDER BY ym DESC
")->fetchAll();

// Top medicines by prescriptions in period
$topMeds = $db->query("
    SELECT m.medication_name, m.category,
           COUNT(DISTINCT pi.prescription_id) AS rx_count,
           SUM(pi.quantity) AS total_qty,
           ROUND(SUM(pi.quantity * COALESCE(m.unit_price,0)),2) AS est_revenue
    FROM PRESCRIPTION_ITEM pi
    JOIN MEDICINE_STOCK m ON m.stock_id = pi.stock_id
    JOIN PRESCRIPTION p   ON p.prescription_id = pi.prescription_id
    WHERE p.status = 'processed'
    GROUP BY pi.stock_id, m.medication_name, m.category
    ORDER BY rx_count DESC
    LIMIT 8
")->fetchAll();

$pageTitle = 'Reports';
require_once __DIR__ . '/../includes/header.php';

// Helper
function money(float $v): string { return '£' . number_format($v, 2); }
?>

<style>
/* ── Report tabs ─────────────────────────────────── */
.report-tabs { display:flex;gap:6px;margin-bottom:20px;flex-wrap:wrap; }
.report-tab {
    padding:9px 18px;border:1px solid var(--border);border-radius:8px;
    background:#fff;font-size:13.5px;font-weight:600;cursor:pointer;
    color:var(--text-muted);transition:all .15s;
}
.report-tab:hover  { border-color:var(--primary);color:var(--primary); }
.report-tab.active { background:var(--primary);color:#fff;border-color:var(--primary); }

/* ── Filter bar ──────────────────────────────────── */
.filter-bar {
    background:#fff;border-radius:10px;padding:14px 18px;
    border:1px solid var(--border);margin-bottom:18px;
    display:flex;align-items:flex-end;gap:12px;flex-wrap:wrap;
}
.filter-bar .filter-group { display:flex;flex-direction:column;gap:4px; }
.filter-bar label { font-size:11px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px; }
.filter-bar select, .filter-bar input {
    padding:7px 10px;border:1px solid var(--border);border-radius:7px;
    font-size:13px;background:#fff;min-width:130px;
}
.filter-bar .btn-filter {
    padding:8px 18px;background:var(--primary);color:#fff;
    border:none;border-radius:7px;font-size:13px;font-weight:600;cursor:pointer;
    white-space:nowrap;
}
.btn-print {
    padding:8px 18px;background:#fff;color:var(--text);
    border:1px solid var(--border);border-radius:7px;font-size:13px;
    font-weight:600;cursor:pointer;white-space:nowrap;
}

/* ── Report section ──────────────────────────────── */
.report-section { display:none; }
.report-section.active { display:block; }

/* ── Summary cards ───────────────────────────────── */
.sum-grid { display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:14px;margin-bottom:20px; }
.sum-card {
    background:#fff;border-radius:10px;padding:16px 18px;
    border:1px solid var(--border);
}
.sum-card .sum-val { font-size:22px;font-weight:800;color:var(--text); }
.sum-card .sum-lbl { font-size:12px;color:var(--text-muted);margin-top:4px; }
.sum-card.green .sum-val { color:#065f46; }
.sum-card.red   .sum-val { color:#991b1b; }
.sum-card.amber .sum-val { color:#92400e; }
.sum-card.blue  .sum-val { color:#1e40af; }

/* ── Table ───────────────────────────────────────── */
.card { background:#fff;border-radius:10px;box-shadow:var(--shadow);margin-bottom:20px; }
.card-header {
    padding:14px 18px;border-bottom:1px solid var(--border);
    font-weight:700;font-size:14px;display:flex;align-items:center;justify-content:space-between;
}
.table-wrap { overflow-x:auto; }
table { width:100%;border-collapse:collapse;font-size:13.5px; }
th { text-align:left;padding:10px 14px;background:#f8fafc;color:var(--text-muted);
     font-size:11px;text-transform:uppercase;letter-spacing:.5px;border-bottom:1px solid var(--border); }
td { padding:10px 14px;border-bottom:1px solid var(--border);vertical-align:middle; }
tr:last-child td { border-bottom:none; }
.badge { display:inline-block;padding:3px 9px;border-radius:20px;font-size:11.5px;font-weight:600; }
.empty-state { text-align:center;padding:40px;color:var(--text-muted); }

/* ── Two-col layout ──────────────────────────────── */
.two-col { display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:20px; }
@media(max-width:700px) { .two-col { grid-template-columns:1fr; } }

/* ── Print styles ────────────────────────────────── */
@media print {
    .sidebar, .top-header, .report-tabs, .filter-bar, .btn-print, .no-print { display:none !important; }
    .main-wrapper { margin-left:0 !important; }
    .main-content { padding:0 !important; }
    .report-section { display:block !important; }
}
</style>

<!-- Report type tabs -->
<div class="report-tabs no-print">
    <button class="report-tab <?= $tab==='inventory'?'active':'' ?>" data-tab="inventory">📦 Inventory Report</button>
    <button class="report-tab <?= $tab==='alerts'?'active':'' ?>"    data-tab="alerts">🔔 Alert Report</button>
    <button class="report-tab <?= $tab==='sales'?'active':'' ?>"     data-tab="sales">💰 Sales & Transactions</button>
</div>


<!-- ═══════════════════════════════════════════════════
     INVENTORY REPORT
════════════════════════════════════════════════════ -->
<div id="section-inventory" class="report-section <?= $tab==='inventory'?'active':'' ?>">

    <form method="GET" action="/pages/reports.php">
        <input type="hidden" name="tab" value="inventory">
        <div class="filter-bar">
            <div class="filter-group">
                <label>Category</label>
                <select name="inv_category">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= $c ?>" <?= $invCategory===$c?'selected':'' ?>><?= $c ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label>Status</label>
                <select name="inv_status">
                    <option value="all"      <?= $invStatus==='all'?'selected':'' ?>>All</option>
                    <option value="active"   <?= $invStatus==='active'?'selected':'' ?>>Active Only</option>
                    <option value="inactive" <?= $invStatus==='inactive'?'selected':'' ?>>Inactive Only</option>
                </select>
            </div>
            <div class="filter-group">
                <label>Stock Level</label>
                <select name="inv_level">
                    <option value="all"      <?= $invLevel==='all'?'selected':'' ?>>All</option>
                    <option value="low"      <?= $invLevel==='low'?'selected':'' ?>>Low Stock (&lt;10)</option>
                    <option value="out"      <?= $invLevel==='out'?'selected':'' ?>>Out of Stock</option>
                    <option value="expiring" <?= $invLevel==='expiring'?'selected':'' ?>>Expiring (30 days)</option>
                    <option value="expired"  <?= $invLevel==='expired'?'selected':'' ?>>Expired</option>
                </select>
            </div>
            <div class="filter-group">
                <label>Sort By</label>
                <select name="inv_sort">
                    <option value="name"     <?= $invSort==='name'?'selected':'' ?>>Name A–Z</option>
                    <option value="qty_asc"  <?= $invSort==='qty_asc'?'selected':'' ?>>Qty Low → High</option>
                    <option value="qty_desc" <?= $invSort==='qty_desc'?'selected':'' ?>>Qty High → Low</option>
                    <option value="expiry"   <?= $invSort==='expiry'?'selected':'' ?>>Expiry Date</option>
                    <option value="category" <?= $invSort==='category'?'selected':'' ?>>Category</option>
                    <option value="price"    <?= $invSort==='price'?'selected':'' ?>>Unit Price</option>
                </select>
            </div>
            <button type="submit" class="btn-filter">Apply Filters</button>
            <button type="button" class="btn-print" onclick="window.print()">🖨 Print</button>
        </div>
    </form>

    <!-- Summary cards -->
    <div class="sum-grid">
        <div class="sum-card blue">
            <div class="sum-val"><?= $invSummary['active'] ?></div>
            <div class="sum-lbl">Active Medicines</div>
        </div>
        <div class="sum-card red">
            <div class="sum-val"><?= $invSummary['out_of_stock'] ?></div>
            <div class="sum-lbl">Out of Stock</div>
        </div>
        <div class="sum-card amber">
            <div class="sum-val"><?= $invSummary['low_stock'] ?></div>
            <div class="sum-lbl">Low Stock (&lt;10)</div>
        </div>
        <div class="sum-card amber">
            <div class="sum-val"><?= $invSummary['expiring_soon'] ?></div>
            <div class="sum-lbl">Expiring &lt;30 Days</div>
        </div>
        <div class="sum-card red">
            <div class="sum-val"><?= $invSummary['expired'] ?></div>
            <div class="sum-lbl">Expired</div>
        </div>
        <div class="sum-card green">
            <div class="sum-val"><?= money((float)$invSummary['total_value']) ?></div>
            <div class="sum-lbl">Total Stock Value</div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            Inventory — <?= count($medicines) ?> record<?= count($medicines)!==1?'s':'' ?>
            <?php if ($invCategory || $invLevel!=='all' || $invStatus!=='active'): ?>
                <span style="font-size:12px;color:var(--text-muted);">Filtered</span>
            <?php endif; ?>
        </div>
        <div class="table-wrap">
            <?php if ($medicines): ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Medication Name</th>
                        <th>Category</th>
                        <th>Batch No.</th>
                        <th>Quantity</th>
                        <th>Unit Price</th>
                        <th>Stock Value</th>
                        <th>Expiry Date</th>
                        <th>Supplier</th>
                        <th>ID Check</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $today = new DateTime();
                    foreach ($medicines as $m):
                        $expDate  = $m['expiry_date'] ? new DateTime($m['expiry_date']) : null;
                        $isExpired = $expDate && $expDate < $today;
                        $daysLeft  = $expDate ? (int)$today->diff($expDate)->days * ($expDate >= $today ? 1 : -1) : null;
                        $stockVal  = (float)($m['quantity'] ?? 0) * (float)($m['unit_price'] ?? 0);
                    ?>
                    <tr>
                        <td style="color:var(--text-muted);">#<?= $m['stock_id'] ?></td>
                        <td><strong><?= htmlspecialchars($m['medication_name']) ?></strong></td>
                        <td><?= $m['category'] ? htmlspecialchars($m['category']) : '<span style="color:var(--text-muted)">—</span>' ?></td>
                        <td style="color:var(--text-muted);"><?= $m['batch_number'] ?: '—' ?></td>
                        <td>
                            <?php if ($m['quantity'] == 0): ?>
                                <span class="badge" style="background:#fee2e2;color:#991b1b;">Out of Stock</span>
                            <?php elseif ($m['quantity'] < 10): ?>
                                <span class="badge" style="background:#fef3c7;color:#92400e;"><?= $m['quantity'] ?> ⚠️</span>
                            <?php else: ?>
                                <?= $m['quantity'] ?>
                            <?php endif; ?>
                        </td>
                        <td><?= $m['unit_price'] !== null ? money((float)$m['unit_price']) : '—' ?></td>
                        <td style="font-weight:600;"><?= $stockVal > 0 ? money($stockVal) : '—' ?></td>
                        <td>
                            <?php if (!$expDate): ?>
                                <span style="color:var(--text-muted)">—</span>
                            <?php elseif ($isExpired): ?>
                                <span class="badge" style="background:#fee2e2;color:#991b1b;">Expired</span>
                            <?php elseif ($daysLeft <= 30): ?>
                                <span class="badge" style="background:#fef3c7;color:#92400e;"><?= date('d M Y',strtotime($m['expiry_date'])) ?> (<?= $daysLeft ?>d)</span>
                            <?php else: ?>
                                <?= date('d M Y',strtotime($m['expiry_date'])) ?>
                            <?php endif; ?>
                        </td>
                        <td><?= $m['supplier'] ? htmlspecialchars($m['supplier']) : '—' ?></td>
                        <td><?= $m['requires_id_check'] ? '<span class="badge" style="background:#ede9fe;color:#5b21b6;">Required</span>' : 'No' ?></td>
                        <td><?= $m['is_active'] ? '<span class="badge" style="background:#d1fae5;color:#065f46;">Active</span>' : '<span class="badge" style="background:#f3f4f6;color:#6b7280;">Inactive</span>' ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
                <div class="empty-state">No medicines match the selected filters.</div>
            <?php endif; ?>
        </div>
    </div>
</div>


<!-- ═══════════════════════════════════════════════════
     ALERT REPORT
════════════════════════════════════════════════════ -->
<div id="section-alerts" class="report-section <?= $tab==='alerts'?'active':'' ?>">

    <form method="GET" action="/pages/reports.php">
        <input type="hidden" name="tab" value="alerts">
        <div class="filter-bar">
            <div class="filter-group">
                <label>Period</label>
                <select name="alrt_period" onchange="this.form.querySelector('.custom-dates').style.display = this.value==='custom'?'contents':'none'">
                    <option value="today"  <?= $alrtPeriod==='today'?'selected':'' ?>>Today</option>
                    <option value="week"   <?= $alrtPeriod==='week'?'selected':'' ?>>Last 7 Days</option>
                    <option value="month"  <?= $alrtPeriod==='month'?'selected':'' ?>>Last 30 Days</option>
                    <option value="all"    <?= $alrtPeriod==='all'?'selected':'' ?>>All Time</option>
                    <option value="custom" <?= $alrtPeriod==='custom'?'selected':'' ?>>Custom Range</option>
                </select>
            </div>
            <span class="custom-dates" style="display:<?= $alrtPeriod==='custom'?'contents':'none' ?>">
                <div class="filter-group">
                    <label>From</label>
                    <input type="date" name="alrt_from" value="<?= htmlspecialchars($alrtFrom) ?>">
                </div>
                <div class="filter-group">
                    <label>To</label>
                    <input type="date" name="alrt_to" value="<?= htmlspecialchars($alrtTo) ?>">
                </div>
            </span>
            <div class="filter-group">
                <label>Type</label>
                <select name="alrt_type">
                    <option value=""               <?= !$alrtType?'selected':'' ?>>All Types</option>
                    <option value="low_stock"      <?= $alrtType==='low_stock'?'selected':'' ?>>Low Stock</option>
                    <option value="age_restriction"<?= $alrtType==='age_restriction'?'selected':'' ?>>Age Restriction</option>
                    <option value="rule_violation" <?= $alrtType==='rule_violation'?'selected':'' ?>>Rule Violation</option>
                </select>
            </div>
            <div class="filter-group">
                <label>Status</label>
                <select name="alrt_status">
                    <option value="all"   <?= $alrtStatus==='all'?'selected':'' ?>>All</option>
                    <option value="unack" <?= $alrtStatus==='unack'?'selected':'' ?>>Unacknowledged</option>
                    <option value="ack"   <?= $alrtStatus==='ack'?'selected':'' ?>>Acknowledged</option>
                </select>
            </div>
            <div class="filter-group">
                <label>Sort By</label>
                <select name="alrt_sort">
                    <option value="newest" <?= $alrtSort==='newest'?'selected':'' ?>>Newest First</option>
                    <option value="oldest" <?= $alrtSort==='oldest'?'selected':'' ?>>Oldest First</option>
                    <option value="type"   <?= $alrtSort==='type'?'selected':'' ?>>Type</option>
                    <option value="unack"  <?= $alrtSort==='unack'?'selected':'' ?>>Unacknowledged First</option>
                </select>
            </div>
            <button type="submit" class="btn-filter">Apply Filters</button>
            <button type="button" class="btn-print" onclick="window.print()">🖨 Print</button>
        </div>
    </form>

    <!-- Today's summary -->
    <div class="sum-grid">
        <div class="sum-card blue">
            <div class="sum-val"><?= $alrtSummary['total'] ?></div>
            <div class="sum-lbl">Today's Alerts</div>
        </div>
        <div class="sum-card red">
            <div class="sum-val"><?= $alrtSummary['unacknowledged'] ?></div>
            <div class="sum-lbl">Unacknowledged</div>
        </div>
        <div class="sum-card amber">
            <div class="sum-val"><?= $alrtSummary['low_stock'] ?></div>
            <div class="sum-lbl">Low Stock (Today)</div>
        </div>
        <div class="sum-card amber">
            <div class="sum-val"><?= $alrtSummary['age_restriction'] ?></div>
            <div class="sum-lbl">Age Restriction (Today)</div>
        </div>
        <div class="sum-card red">
            <div class="sum-val"><?= $alrtSummary['rule_violation'] ?></div>
            <div class="sum-lbl">Rule Violations (Today)</div>
        </div>
        <div class="sum-card">
            <div class="sum-val"><?= count($alerts) ?></div>
            <div class="sum-lbl">Matching Filter</div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            Alert Log — <?= count($alerts) ?> record<?= count($alerts)!==1?'s':'' ?>
        </div>
        <?php
        $typeStyles = [
            'low_stock'       => ['bg'=>'#fef3c7','text'=>'#92400e','label'=>'Low Stock'],
            'age_restriction' => ['bg'=>'#fee2e2','text'=>'#991b1b','label'=>'Age Restriction'],
            'rule_violation'  => ['bg'=>'#ede9fe','text'=>'#5b21b6','label'=>'Rule Violation'],
        ];
        ?>
        <div class="table-wrap">
            <?php if ($alerts): ?>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Type</th>
                        <th>Message</th>
                        <th>Date & Time</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($alerts as $a):
                        $ts = $typeStyles[$a['alert_type']] ?? ['bg'=>'#f3f4f6','text'=>'#6b7280','label'=>ucwords(str_replace('_',' ',$a['alert_type']))];
                    ?>
                    <tr style="<?= !$a['is_acknowledged'] ? 'font-weight:500;' : 'opacity:.65;' ?>">
                        <td style="color:var(--text-muted);"><?= $a['alert_id'] ?></td>
                        <td>
                            <span class="badge" style="background:<?= $ts['bg'] ?>;color:<?= $ts['text'] ?>;">
                                <?= $ts['label'] ?>
                            </span>
                        </td>
                        <td style="font-size:13px;max-width:420px;"><?= htmlspecialchars($a['message']) ?></td>
                        <td style="color:var(--text-muted);white-space:nowrap;font-size:13px;">
                            <?= date('d M Y', strtotime($a['triggered_at'])) ?><br>
                            <span style="font-size:11px;"><?= date('H:i:s', strtotime($a['triggered_at'])) ?></span>
                        </td>
                        <td>
                            <?php if ($a['is_acknowledged']): ?>
                                <span class="badge" style="background:#d1fae5;color:#065f46;">Acknowledged</span>
                            <?php else: ?>
                                <span class="badge" style="background:#fee2e2;color:#991b1b;">Pending</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
                <div class="empty-state">No alerts match the selected filters.</div>
            <?php endif; ?>
        </div>
    </div>
</div>


<!-- ═══════════════════════════════════════════════════
     SALES & TRANSACTIONS REPORT
════════════════════════════════════════════════════ -->
<div id="section-sales" class="report-section <?= $tab==='sales'?'active':'' ?>">

    <form method="GET" action="/pages/reports.php">
        <input type="hidden" name="tab" value="sales">
        <div class="filter-bar">
            <div class="filter-group">
                <label>Period</label>
                <select name="sale_period" onchange="this.form.querySelector('.sale-custom').style.display = this.value==='custom'?'contents':'none'">
                    <option value="today"      <?= $salePeriod==='today'?'selected':'' ?>>Today</option>
                    <option value="week"       <?= $salePeriod==='week'?'selected':'' ?>>This Week</option>
                    <option value="month"      <?= $salePeriod==='month'?'selected':'' ?>>This Month</option>
                    <option value="last_month" <?= $salePeriod==='last_month'?'selected':'' ?>>Last Month</option>
                    <option value="year"       <?= $salePeriod==='year'?'selected':'' ?>>This Year</option>
                    <option value="custom"     <?= $salePeriod==='custom'?'selected':'' ?>>Custom Range</option>
                </select>
            </div>
            <span class="sale-custom" style="display:<?= $salePeriod==='custom'?'contents':'none' ?>">
                <div class="filter-group">
                    <label>From</label>
                    <input type="date" name="sale_from" value="<?= htmlspecialchars($saleFrom) ?>">
                </div>
                <div class="filter-group">
                    <label>To</label>
                    <input type="date" name="sale_to" value="<?= htmlspecialchars($saleTo) ?>">
                </div>
            </span>
            <div class="filter-group">
                <label>Status</label>
                <select name="sale_status">
                    <option value="all"            <?= $saleStatus==='all'?'selected':'' ?>>All Status</option>
                    <option value="paid"           <?= $saleStatus==='paid'?'selected':'' ?>>Paid</option>
                    <option value="unpaid"         <?= $saleStatus==='unpaid'?'selected':'' ?>>Unpaid</option>
                    <option value="awaiting_pickup"<?= $saleStatus==='awaiting_pickup'?'selected':'' ?>>Awaiting Pickup</option>
                </select>
            </div>
            <div class="filter-group">
                <label>Method</label>
                <select name="sale_method">
                    <option value="all"  <?= $saleMethod==='all'?'selected':'' ?>>All Methods</option>
                    <option value="cash" <?= $saleMethod==='cash'?'selected':'' ?>>Cash</option>
                    <option value="card" <?= $saleMethod==='card'?'selected':'' ?>>Card</option>
                </select>
            </div>
            <button type="submit" class="btn-filter">Apply Filters</button>
            <button type="button" class="btn-print" onclick="window.print()">🖨 Print</button>
        </div>
    </form>

    <!-- Revenue dashboard -->
    <div class="sum-grid">
        <div class="sum-card green" style="border-left:4px solid #10b981;">
            <div class="sum-val"><?= money((float)$saleSum['total_revenue']) ?></div>
            <div class="sum-lbl">Revenue Collected</div>
        </div>
        <div class="sum-card blue">
            <div class="sum-val"><?= $saleSum['total_transactions'] ?></div>
            <div class="sum-lbl">Total Transactions</div>
        </div>
        <div class="sum-card green">
            <div class="sum-val"><?= $saleSum['paid_count'] ?></div>
            <div class="sum-lbl">Paid</div>
        </div>
        <div class="sum-card amber">
            <div class="sum-val"><?= $saleSum['awaiting_count'] ?></div>
            <div class="sum-lbl">Awaiting Pickup</div>
        </div>
        <div class="sum-card red">
            <div class="sum-val"><?= $saleSum['unpaid_count'] ?></div>
            <div class="sum-lbl">Unpaid</div>
        </div>
        <div class="sum-card red">
            <div class="sum-val"><?= money((float)$saleSum['outstanding_amount']) ?></div>
            <div class="sum-lbl">Outstanding Amount</div>
        </div>
    </div>

    <div class="two-col">
        <!-- Payment method breakdown -->
        <div class="card">
            <div class="card-header">💳 Payment Method Breakdown</div>
            <div style="padding:18px;">
                <?php
                $cashRev  = (float)$saleSum['cash_revenue'];
                $cardRev  = (float)$saleSum['card_revenue'];
                $totalRev = $cashRev + $cardRev;
                $cashPct  = $totalRev > 0 ? round($cashRev/$totalRev*100) : 0;
                $cardPct  = $totalRev > 0 ? 100 - $cashPct : 0;
                ?>
                <div style="margin-bottom:12px;">
                    <div style="display:flex;justify-content:space-between;margin-bottom:6px;font-size:13px;">
                        <span>💵 Cash</span>
                        <strong><?= money($cashRev) ?> (<?= $cashPct ?>%)</strong>
                    </div>
                    <div style="background:#e2e8f0;border-radius:20px;height:10px;">
                        <div style="background:#f59e0b;border-radius:20px;height:100%;width:<?= $cashPct ?>%;"></div>
                    </div>
                </div>
                <div>
                    <div style="display:flex;justify-content:space-between;margin-bottom:6px;font-size:13px;">
                        <span>💳 Card</span>
                        <strong><?= money($cardRev) ?> (<?= $cardPct ?>%)</strong>
                    </div>
                    <div style="background:#e2e8f0;border-radius:20px;height:10px;">
                        <div style="background:#2563eb;border-radius:20px;height:100%;width:<?= $cardPct ?>%;"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Top medicines -->
        <div class="card">
            <div class="card-header">💊 Top Medicines by Prescription Count</div>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Medicine</th><th style="text-align:center;">Rx</th><th>Est. Revenue</th></tr></thead>
                    <tbody>
                        <?php foreach (array_slice($topMeds, 0, 5) as $med): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($med['medication_name']) ?></strong>
                                <?php if ($med['category']): ?><div style="font-size:11.5px;color:var(--text-muted);"><?= htmlspecialchars($med['category']) ?></div><?php endif; ?>
                            </td>
                            <td style="text-align:center;font-weight:700;"><?= $med['rx_count'] ?></td>
                            <td><?= $med['est_revenue'] > 0 ? money((float)$med['est_revenue']) : '—' ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Monthly trend -->
    <?php if ($monthlyTrend): ?>
    <div class="card">
        <div class="card-header">📈 Monthly Trend (Last 6 Months)</div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Month</th>
                        <th style="text-align:center;">Transactions</th>
                        <th style="text-align:right;">Revenue</th>
                        <th style="text-align:center;">Paid</th>
                        <th style="text-align:center;">Unpaid</th>
                        <th style="text-align:center;">Awaiting</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($monthlyTrend as $row): ?>
                    <tr>
                        <td style="font-weight:600;"><?= htmlspecialchars($row['label']) ?></td>
                        <td style="text-align:center;"><?= $row['transactions'] ?></td>
                        <td style="text-align:right;font-weight:700;color:#065f46;"><?= money((float)$row['revenue']) ?></td>
                        <td style="text-align:center;"><span class="badge" style="background:#d1fae5;color:#065f46;"><?= $row['paid'] ?></span></td>
                        <td style="text-align:center;"><span class="badge" style="background:#fee2e2;color:#991b1b;"><?= $row['unpaid'] ?></span></td>
                        <td style="text-align:center;"><span class="badge" style="background:#fef3c7;color:#92400e;"><?= $row['awaiting'] ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- Full transactions table -->
    <div class="card">
        <div class="card-header">
            Transaction Detail — <?= count($payments) ?> record<?= count($payments)!==1?'s':'' ?>
        </div>
        <div class="table-wrap">
            <?php if ($payments): ?>
            <table>
                <thead>
                    <tr>
                        <th>Pay #</th>
                        <th>RX #</th>
                        <th>Customer</th>
                        <th>Amount</th>
                        <th>Method</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Paid At</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $payStyles = [
                        'paid'            => ['bg'=>'#d1fae5','text'=>'#065f46','label'=>'Paid'],
                        'awaiting_pickup' => ['bg'=>'#fef3c7','text'=>'#92400e','label'=>'Awaiting Pickup'],
                        'unpaid'          => ['bg'=>'#fee2e2','text'=>'#991b1b','label'=>'Unpaid'],
                    ];
                    foreach ($payments as $pay):
                        $ps = $payStyles[$pay['payment_status']] ?? ['bg'=>'#f3f4f6','text'=>'#6b7280','label'=>ucfirst($pay['payment_status'])];
                    ?>
                    <tr>
                        <td style="color:var(--text-muted);">#<?= $pay['payment_id'] ?></td>
                        <td style="font-weight:600;color:var(--primary);">RX-<?= $pay['prescription_id'] ?></td>
                        <td><?= htmlspecialchars($pay['customer_name']) ?></td>
                        <td style="font-weight:600;"><?= $pay['amount'] !== null ? money((float)$pay['amount']) : '—' ?></td>
                        <td>
                            <?php if ($pay['payment_method'] === 'cash'): ?>
                                <span class="badge" style="background:#fef3c7;color:#92400e;">💵 Cash</span>
                            <?php elseif ($pay['payment_method'] === 'card'): ?>
                                <span class="badge" style="background:#ede9fe;color:#5b21b6;">💳 Card</span>
                            <?php else: ?>
                                <span style="color:var(--text-muted)">—</span>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge" style="background:<?= $ps['bg'] ?>;color:<?= $ps['text'] ?>;"><?= $ps['label'] ?></span></td>
                        <td style="color:var(--text-muted);white-space:nowrap;"><?= date('d M Y', strtotime($pay['billed_date'])) ?></td>
                        <td style="color:var(--text-muted);font-size:12.5px;">
                            <?= $pay['paid_at'] ? date('d M Y H:i', strtotime($pay['paid_at'])) : '—' ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="background:#f8fafc;">
                        <td colspan="3" style="font-weight:700;padding:12px 14px;">Total (filtered)</td>
                        <td style="font-weight:700;padding:12px 14px;color:#065f46;"><?= money((float)$saleSum['total_revenue']) ?></td>
                        <td colspan="4"></td>
                    </tr>
                </tfoot>
            </table>
            <?php else: ?>
                <div class="empty-state">No transactions match the selected filters.</div>
            <?php endif; ?>
        </div>
    </div>

</div><!-- /section-sales -->

<script>
document.querySelectorAll('.report-tab').forEach(btn => {
    btn.addEventListener('click', () => {
        const t = btn.dataset.tab;
        document.querySelectorAll('.report-tab').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.report-section').forEach(s => s.classList.remove('active'));
        btn.classList.add('active');
        document.getElementById('section-' + t).classList.add('active');
        history.replaceState(null, '', '?tab=' + t);
        window.scrollTo({ top: 0 });
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
