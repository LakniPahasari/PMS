<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/config/session.php';
requireCustomerLogin();

$customer = currentCustomer();
$db       = getDB();

// Full customer profile
$profStmt = $db->prepare("SELECT * FROM CUSTOMER WHERE customer_id = ? LIMIT 1");
$profStmt->execute([$customer['id']]);
$p = $profStmt->fetch();

// Prescriptions
$rxStmt = $db->prepare("
    SELECT p.prescription_id, p.status, p.created_at, p.processed_at,
           p.next_refill_date, p.special_notes, p.rejection_reason,
           GROUP_CONCAT(m.medication_name ORDER BY m.medication_name SEPARATOR ', ') AS medicines,
           MAX(pay.payment_status) AS payment_status,
           MAX(pay.amount)         AS amount,
           MAX(pay.payment_method) AS payment_method
    FROM PRESCRIPTION p
    JOIN PRESCRIPTION_ITEM pi ON pi.prescription_id = p.prescription_id
    JOIN MEDICINE_STOCK m     ON m.stock_id = pi.stock_id
    LEFT JOIN PAYMENT pay     ON pay.prescription_id = p.prescription_id
    WHERE p.customer_id = ?
    GROUP BY p.prescription_id
    ORDER BY p.created_at DESC
");
$rxStmt->execute([$customer['id']]);
$prescriptions = $rxStmt->fetchAll();

// Line items for view modal
$itemStmt = $db->prepare("
    SELECT pi.prescription_id, m.medication_name, pi.quantity, pi.dosage
    FROM PRESCRIPTION_ITEM pi
    JOIN MEDICINE_STOCK m ON m.stock_id = pi.stock_id
    WHERE pi.prescription_id IN (
        SELECT prescription_id FROM PRESCRIPTION WHERE customer_id = ?
    )
    ORDER BY m.medication_name
");
$itemStmt->execute([$customer['id']]);
$allItems = [];
foreach ($itemStmt->fetchAll() as $row) {
    $allItems[$row['prescription_id']][] = $row;
}

// Submissions
$reqStmt = $db->prepare("
    SELECT request_id, image_path, notes, status, created_at
    FROM PRESCRIPTION_REQUEST WHERE customer_id = ?
    ORDER BY created_at DESC
");
$reqStmt->execute([$customer['id']]);
$requests = $reqStmt->fetchAll();

// Audit log for this customer record
$auditStmt = $db->prepare("
    SELECT al.action_type, al.timestamp, su.name AS staff_name
    FROM AUDIT_LOG al
    LEFT JOIN SYSTEM_USER su ON su.system_user_id = al.system_user_id
    WHERE al.target_table IN ('CUSTOMER','PRESCRIPTION')
      AND al.target_id = ?
    ORDER BY al.timestamp DESC LIMIT 10
");
$auditStmt->execute([$customer['id']]);
$auditHistory = $auditStmt->fetchAll();

// Helpers
function calcAge(?string $dob): ?int {
    if (!$dob) return null;
    return (int)(new DateTime())->diff(new DateTime($dob))->y;
}
$age = calcAge($p['date_of_birth']);

$uploadSuccess = !empty($_GET['upload_success']);
$uploadError   = $_GET['upload_error'] ?? '';
$activeTab     = $_GET['tab'] ?? 'profile';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Health Portal — Drugs 4U</title>
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
       background: #f1f5f9; color: #1e293b; font-size: 14px; }
a { text-decoration: none; color: inherit; }

/* ── Header ─────────────────────────────────────── */
.portal-header {
    background: #fff; border-bottom: 1px solid #e2e8f0;
    padding: 0 24px; display: flex; align-items: center;
    height: 60px; gap: 12px; position: sticky; top: 0; z-index: 20;
}
.portal-brand { font-size: 18px; font-weight: 800; color: #2563eb; flex: 1; }
.portal-user  { font-size: 13px; color: #64748b; }
.portal-user strong { color: #1e293b; }
.btn-logout {
    padding: 7px 16px; background: #f1f5f9; border: 1px solid #e2e8f0;
    border-radius: 8px; font-size: 13px; cursor: pointer;
    color: #ef4444; font-weight: 600; text-decoration: none;
}

/* ── Tab nav ─────────────────────────────────────── */
.portal-nav {
    background: #fff; border-bottom: 2px solid #e2e8f0;
    display: flex; gap: 0; overflow-x: auto;
    position: sticky; top: 60px; z-index: 19;
    padding: 0 16px;
}
.tab-link {
    padding: 14px 20px; font-size: 13.5px; font-weight: 600;
    color: #64748b; border: none; background: none; cursor: pointer;
    border-bottom: 3px solid transparent; margin-bottom: -2px;
    white-space: nowrap; transition: color .15s, border-color .15s;
}
.tab-link:hover  { color: #2563eb; }
.tab-link.active { color: #2563eb; border-bottom-color: #2563eb; }

/* ── Layout ─────────────────────────────────────── */
.portal-body { max-width: 860px; margin: 0 auto; padding: 28px 16px 48px; }

/* ── Cards ──────────────────────────────────────── */
.card { background: #fff; border-radius: 12px;
        box-shadow: 0 1px 4px rgba(0,0,0,.08); margin-bottom: 20px; }
.card-header {
    padding: 16px 20px; border-bottom: 1px solid #e2e8f0;
    font-weight: 700; font-size: 14px;
    display: flex; align-items: center; justify-content: space-between;
}
.card-body { padding: 20px; }

/* ── Profile header ─────────────────────────────── */
.profile-hero {
    display: flex; align-items: center; gap: 20px;
    flex-wrap: wrap; padding: 24px 20px;
}
.profile-avatar {
    width: 64px; height: 64px; border-radius: 50%; background: #2563eb;
    color: #fff; display: flex; align-items: center; justify-content: center;
    font-size: 26px; font-weight: 700; flex-shrink: 0;
}
.profile-name  { font-size: 20px; font-weight: 700; }
.profile-meta  { font-size: 13px; color: #64748b; margin-top: 4px; }
.status-pill {
    display: inline-block; padding: 2px 10px; border-radius: 20px;
    font-size: 12px; font-weight: 600; margin-top: 6px;
}

/* ── Info grid ──────────────────────────────────── */
.info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.info-item label { display: block; font-size: 11px; font-weight: 700;
                   text-transform: uppercase; letter-spacing: .6px;
                   color: #94a3b8; margin-bottom: 4px; }
.info-item .val  { font-size: 13.5px; color: #1e293b; }
.info-item .val.muted { color: #94a3b8; font-style: italic; }

/* ── Alert boxes ────────────────────────────────── */
.box-red  { background:#fee2e2;border:1px solid #fca5a5;border-radius:8px;padding:12px 14px;color:#991b1b;font-size:13px;margin-top:12px; }
.box-blue { background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;padding:12px 14px;color:#1e40af;font-size:13px;margin-top:12px; }
.box-green{ background:#f0fdf4;border:1px solid #86efac;border-radius:8px;padding:12px 14px;color:#166534;font-size:13px;margin-top:12px; }

/* ── Toggle switches ────────────────────────────── */
.pref-row { display:flex;align-items:center;justify-content:space-between;
            padding:12px 0;border-bottom:1px solid #f1f5f9; }
.pref-row:last-child { border-bottom: none; }
.pref-label { font-size:13.5px; }
.pref-sub   { font-size:12px;color:#94a3b8;margin-top:2px; }
.toggle { position:relative;display:inline-block;width:44px;height:24px; }
.toggle input { opacity:0;width:0;height:0; }
.slider {
    position:absolute;inset:0;background:#cbd5e1;border-radius:24px;
    cursor:pointer;transition:.2s;
}
.slider:before {
    content:'';position:absolute;height:18px;width:18px;
    left:3px;bottom:3px;background:#fff;border-radius:50%;transition:.2s;
}
input:checked + .slider { background:#2563eb; }
input:checked + .slider:before { transform:translateX(20px); }

/* ── Table ──────────────────────────────────────── */
.table-wrap { overflow-x: auto; }
table { width:100%;border-collapse:collapse;font-size:13.5px; }
th { text-align:left;padding:10px 14px;background:#f8fafc;
     color:#64748b;font-size:11px;text-transform:uppercase;
     letter-spacing:.5px;border-bottom:1px solid #e2e8f0; }
td { padding:12px 14px;border-bottom:1px solid #e2e8f0;vertical-align:top; }
tr:last-child td { border-bottom:none; }
.badge { display:inline-block;padding:3px 9px;border-radius:20px;
         font-size:11.5px;font-weight:600;white-space:nowrap; }

/* ── Buttons ─────────────────────────────────────── */
.btn-edit {
    padding:5px 14px;background:#f8fafc;border:1px solid #e2e8f0;
    border-radius:6px;font-size:12.5px;font-weight:600;cursor:pointer;
    color:#2563eb;transition:background .12s;
}
.btn-edit:hover { background:#eff6ff; }
.btn-primary {
    padding:9px 20px;background:#2563eb;color:#fff;border:none;
    border-radius:8px;font-size:13.5px;font-weight:600;cursor:pointer;
}
.btn-primary:hover { background:#1d4ed8; }
.btn-secondary {
    padding:9px 20px;background:#f1f5f9;color:#1e293b;
    border:1px solid #e2e8f0;border-radius:8px;font-size:13.5px;
    font-weight:600;cursor:pointer;
}
.view-btn-sm {
    padding:5px 14px;background:#eff6ff;color:#1e40af;
    border:1px solid #bfdbfe;border-radius:6px;font-size:12.5px;
    font-weight:600;cursor:pointer;
}

/* ── Modals ─────────────────────────────────────── */
.modal-overlay {
    display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);
    z-index:100;align-items:center;justify-content:center;padding:16px;
}
.modal-box {
    background:#fff;border-radius:12px;width:100%;max-width:540px;
    max-height:90vh;overflow-y:auto;
    box-shadow:0 8px 32px rgba(0,0,0,.18);
}
.modal-hdr {
    display:flex;align-items:center;justify-content:space-between;
    padding:18px 20px;border-bottom:1px solid #e2e8f0;
}
.modal-hdr h3 { font-size:15px;font-weight:700; }
.modal-close { background:none;border:none;font-size:22px;cursor:pointer;color:#64748b; }
.modal-body  { padding:20px; }
.modal-foot  { padding:14px 20px;border-top:1px solid #e2e8f0;
               display:flex;gap:10px;justify-content:flex-end; }
.toast-msg { padding:10px 14px;border-radius:8px;font-size:13px;margin-bottom:14px;display:none; }
.toast-success { background:#d1fae5;color:#065f46;border:1px solid #6ee7b7;display:block; }
.toast-error   { background:#fee2e2;color:#991b1b;border:1px solid #fca5a5;display:block; }
.form-group { margin-bottom:14px; }
.form-label { display:block;font-size:13px;font-weight:600;margin-bottom:5px; }
.form-control {
    width:100%;padding:9px 12px;border:1px solid #e2e8f0;
    border-radius:8px;font-size:13.5px;outline:none;transition:border-color .15s;
}
.form-control:focus { border-color:#2563eb; }

/* ── Upload area ────────────────────────────────── */
.upload-area {
    width:100%;padding:28px;border:2px dashed #cbd5e1;border-radius:10px;
    background:#f8fafc;text-align:center;cursor:pointer;transition:border-color .15s;
}
.upload-area:hover { border-color:#2563eb; }

/* ── Help ───────────────────────────────────────── */
.faq-item { border-bottom:1px solid #f1f5f9;padding:14px 0; }
.faq-item:last-child { border-bottom:none; }
.faq-q { font-weight:700;font-size:13.5px;margin-bottom:5px; }
.faq-a { font-size:13px;color:#64748b;line-height:1.6; }

/* ── Audit history ──────────────────────────────── */
.audit-row { display:flex;gap:12px;align-items:flex-start;
             padding:10px 0;border-bottom:1px solid #f1f5f9;font-size:13px; }
.audit-row:last-child { border-bottom:none; }
.audit-dot { width:8px;height:8px;border-radius:50%;background:#cbd5e1;
             flex-shrink:0;margin-top:4px; }

@media(max-width:600px) {
    .info-grid { grid-template-columns:1fr; }
    .tab-link  { padding:12px 14px;font-size:12.5px; }
}
</style>
</head>
<body>

<!-- ── Header ──────────────────────────────────────── -->
<header class="portal-header">
    <span style="font-size:22px;">⚕</span>
    <span class="portal-brand">Drugs 4U — Patient Portal</span>
    <span class="portal-user" style="display:none" id="headerUser">
        <strong><?= htmlspecialchars($p['name']) ?></strong>
    </span>
    <a href="/customer/logout.php" class="btn-logout">Sign Out</a>
</header>

<!-- ── Tab navigation ──────────────────────────────── -->
<nav class="portal-nav" id="portalNav">
    <button class="tab-link" data-tab="profile">👤 Profile</button>
    <button class="tab-link" data-tab="prescriptions">
        📋 My Prescriptions
        <?php if (count($prescriptions)): ?>
            <span style="background:#dbeafe;color:#1e40af;border-radius:20px;
                         font-size:11px;padding:1px 7px;margin-left:4px;">
                <?= count($prescriptions) ?>
            </span>
        <?php endif; ?>
    </button>
    <button class="tab-link" data-tab="submissions">
        📤 Submissions
        <?php $pending = count(array_filter($requests, fn($r) => $r['status']==='pending')); ?>
        <?php if ($pending): ?>
            <span style="background:#fef3c7;color:#92400e;border-radius:20px;
                         font-size:11px;padding:1px 7px;margin-left:4px;">
                <?= $pending ?>
            </span>
        <?php endif; ?>
    </button>
    <button class="tab-link" data-tab="submit">📷 Submit Prescription</button>
    <button class="tab-link" data-tab="help">❓ Help</button>
</nav>

<div class="portal-body">

<!-- ════════════════════════════════════════════════
     TAB: PROFILE
════════════════════════════════════════════════ -->
<div id="tab-profile" class="portal-section">

    <!-- Profile hero -->
    <div class="card">
        <div class="profile-hero">
            <div class="profile-avatar"><?= strtoupper(substr($p['name'],0,1)) ?></div>
            <div style="flex:1;">
                <div class="profile-name"><?= htmlspecialchars($p['name']) ?></div>
                <?php if ($p['date_of_birth']): ?>
                <div class="profile-meta">
                    <?= date('d M Y', strtotime($p['date_of_birth'])) ?>
                    <?php if ($age !== null): ?>&bull; Age <?= $age ?><?php endif; ?>
                </div>
                <?php endif; ?>
                <span class="status-pill" style="background:<?= $p['account_active'] ? '#d1fae5' : '#fee2e2' ?>;
                      color:<?= $p['account_active'] ? '#065f46' : '#991b1b' ?>;">
                    <?= $p['account_active'] ? 'Active Account' : 'Inactive' ?>
                </span>
            </div>
        </div>
    </div>

    <!-- Contact Information -->
    <div class="card">
        <div class="card-header">
            📧 Contact Information
            <button class="btn-edit" onclick="openModal('contact')">✏️ Edit</button>
        </div>
        <div class="card-body">
            <div class="info-grid">
                <div class="info-item">
                    <label>Email Address</label>
                    <div class="val <?= $p['email'] ? '' : 'muted' ?>">
                        <?= $p['email'] ? htmlspecialchars($p['email']) : 'Not provided' ?>
                    </div>
                </div>
                <div class="info-item">
                    <label>Home Address</label>
                    <div class="val <?= $p['address'] ? '' : 'muted' ?>">
                        <?= $p['address'] ? htmlspecialchars($p['address']) : 'Not provided' ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Medical Information -->
    <div class="card">
        <div class="card-header">
            🏥 Medical Information
            <button class="btn-edit" onclick="openModal('medical')">✏️ Edit</button>
        </div>
        <div class="card-body">
            <?php if ($p['allergies']): ?>
                <div class="box-red"><strong>⚠️ Known Allergies:</strong> <?= htmlspecialchars($p['allergies']) ?></div>
            <?php endif; ?>
            <div class="info-grid" style="margin-top:<?= $p['allergies'] ? '16px' : '0' ?>;">
                <div class="info-item">
                    <label>Medical Conditions</label>
                    <div class="val <?= $p['medical_conditions'] ? '' : 'muted' ?>">
                        <?= $p['medical_conditions'] ? nl2br(htmlspecialchars($p['medical_conditions'])) : 'None recorded' ?>
                    </div>
                </div>
                <div class="info-item">
                    <label>Current Medications</label>
                    <div class="val <?= $p['current_medications'] ? '' : 'muted' ?>">
                        <?= $p['current_medications'] ? nl2br(htmlspecialchars($p['current_medications'])) : 'None recorded' ?>
                    </div>
                </div>
                <div class="info-item">
                    <label>Known Drug Sensitivities</label>
                    <div class="val <?= $p['drug_sensitivities'] ? '' : 'muted' ?>">
                        <?= $p['drug_sensitivities'] ? htmlspecialchars($p['drug_sensitivities']) : 'None recorded' ?>
                    </div>
                </div>
                <div class="info-item">
                    <label>Medical History</label>
                    <div class="val <?= $p['medical_history'] ? '' : 'muted' ?>">
                        <?= $p['medical_history'] ? htmlspecialchars($p['medical_history']) : 'None recorded' ?>
                    </div>
                </div>
                <div class="info-item">
                    <label>Primary Care Physician</label>
                    <div class="val <?= $p['primary_care_physician'] ? '' : 'muted' ?>">
                        <?= $p['primary_care_physician'] ? htmlspecialchars($p['primary_care_physician']) : 'Not provided' ?>
                    </div>
                </div>
                <div class="info-item">
                    <label>Preferred Pharmacy</label>
                    <div class="val <?= $p['preferred_pharmacy'] ? '' : 'muted' ?>">
                        <?= $p['preferred_pharmacy'] ? htmlspecialchars($p['preferred_pharmacy']) : 'Not specified' ?>
                    </div>
                </div>
                <div class="info-item">
                    <label>Pregnancy Status</label>
                    <div class="val">
                        <?= match($p['pregnancy_status'] ?? 'not_applicable') {
                            'yes'            => '🤰 Yes',
                            'no'             => 'No',
                            'unknown'        => 'Unknown',
                            default          => 'Not applicable',
                        } ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Communication Preferences -->
    <div class="card">
        <div class="card-header">🔔 Communication Preferences</div>
        <div class="card-body" style="padding:8px 20px;">
            <?php
            $prefs = [
                ['field'=>'comm_email_pickup',     'label'=>'Email when prescription is ready for pickup', 'sub'=>'We\'ll notify you when your medication is ready to collect'],
                ['field'=>'comm_email',             'label'=>'Email Notifications',                         'sub'=>'General updates and prescription status changes'],
                ['field'=>'comm_refill_reminders',  'label'=>'Refill Reminders',                            'sub'=>'Reminders when your prescription is due for renewal'],
                ['field'=>'comm_sms',               'label'=>'SMS Notifications',                           'sub'=>'Text message alerts (requires mobile number on file)'],
            ];
            foreach ($prefs as $pref): ?>
            <div class="pref-row">
                <div>
                    <div class="pref-label"><?= $pref['label'] ?></div>
                    <div class="pref-sub"><?= $pref['sub'] ?></div>
                </div>
                <label class="toggle">
                    <input type="checkbox" class="pref-toggle"
                           data-field="<?= $pref['field'] ?>"
                           <?= $p[$pref['field']] ? 'checked' : '' ?>>
                    <span class="slider"></span>
                </label>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Audit / Record Information -->
    <div class="card">
        <div class="card-header">📋 Record Information</div>
        <div class="card-body">
            <div class="info-grid" style="margin-bottom:16px;">
                <div class="info-item">
                    <label>Record Created</label>
                    <div class="val"><?= $p['registered_at'] ? date('d M Y H:i', strtotime($p['registered_at'])) : 'Registered by pharmacy' ?></div>
                </div>
                <div class="info-item">
                    <label>Last Updated</label>
                    <div class="val <?= $p['updated_at'] ? '' : 'muted' ?>">
                        <?= $p['updated_at'] ? date('d M Y H:i', strtotime($p['updated_at'])) : 'No updates yet' ?>
                    </div>
                </div>
                <div class="info-item">
                    <label>Account Status</label>
                    <div class="val">
                        <span class="badge" style="background:<?= $p['account_active'] ? '#d1fae5' : '#fee2e2' ?>;
                              color:<?= $p['account_active'] ? '#065f46' : '#991b1b' ?>;">
                            <?= $p['account_active'] ? 'Active' : 'Inactive' ?>
                        </span>
                    </div>
                </div>
                <div class="info-item">
                    <label>Date of Birth</label>
                    <div class="val"><?= $p['date_of_birth'] ? date('d M Y', strtotime($p['date_of_birth'])) : '—' ?></div>
                </div>
            </div>

            <?php if ($auditHistory): ?>
            <div style="font-size:11px;font-weight:700;text-transform:uppercase;
                        letter-spacing:.6px;color:#94a3b8;margin-bottom:10px;">
                Change History
            </div>
            <?php foreach ($auditHistory as $log):
                $actionLabel = str_replace('_', ' ', $log['action_type']);
            ?>
            <div class="audit-row">
                <div class="audit-dot"></div>
                <div style="flex:1;">
                    <span style="font-weight:600;text-transform:capitalize;"><?= htmlspecialchars($actionLabel) ?></span>
                    <?php if ($log['staff_name']): ?>
                        <span style="color:#64748b;"> by <?= htmlspecialchars($log['staff_name']) ?></span>
                    <?php endif; ?>
                    <div style="color:#94a3b8;font-size:12px;margin-top:2px;">
                        <?= date('d M Y H:i', strtotime($log['timestamp'])) ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

</div><!-- /tab-profile -->


<!-- ════════════════════════════════════════════════
     TAB: MY PRESCRIPTIONS
════════════════════════════════════════════════ -->
<div id="tab-prescriptions" class="portal-section" style="display:none;">
    <div class="card">
        <div class="card-header">
            📋 My Prescriptions
            <span style="font-size:13px;font-weight:400;color:#64748b;">
                <?= count($prescriptions) ?> record<?= count($prescriptions)!==1?'s':'' ?>
            </span>
        </div>
        <div class="card-body" style="padding:0;">
            <?php
            $statusSt = [
                'pending'   => ['bg'=>'#fef3c7','text'=>'#92400e','label'=>'Pending Review'],
                'approved'  => ['bg'=>'#d1fae5','text'=>'#065f46','label'=>'Approved'],
                'processed' => ['bg'=>'#dbeafe','text'=>'#1e40af','label'=>'Dispensed'],
                'rejected'  => ['bg'=>'#fee2e2','text'=>'#991b1b','label'=>'Rejected'],
            ];
            $paySt = [
                'paid'            => ['bg'=>'#d1fae5','text'=>'#065f46','label'=>'Paid'],
                'awaiting_pickup' => ['bg'=>'#fef3c7','text'=>'#92400e','label'=>'Ready for Pickup'],
                'unpaid'          => ['bg'=>'#fee2e2','text'=>'#991b1b','label'=>'Payment Due'],
            ];
            if ($prescriptions): ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Medicines</th>
                            <th>Status</th>
                            <th>Payment</th>
                            <th>Date</th>
                            <th>Next Refill</th>
                            <th style="text-align:center;">Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($prescriptions as $rx):
                            $ss = $statusSt[$rx['status']] ?? ['bg'=>'#f3f4f6','text'=>'#6b7280','label'=>ucfirst($rx['status'])];
                            $ps = $rx['payment_status'] ? ($paySt[$rx['payment_status']] ?? null) : null;
                        ?>
                        <tr>
                            <td style="font-weight:700;color:#2563eb;">#<?= $rx['prescription_id'] ?></td>
                            <td style="font-size:13px;">
                                <?= htmlspecialchars($rx['medicines']) ?>
                            </td>
                            <td>
                                <span class="badge" style="background:<?= $ss['bg'] ?>;color:<?= $ss['text'] ?>;">
                                    <?= $ss['label'] ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($ps): ?>
                                    <span class="badge" style="background:<?= $ps['bg'] ?>;color:<?= $ps['text'] ?>;">
                                        <?= $ps['label'] ?>
                                    </span>
                                    <?php if ($rx['amount']): ?>
                                        <div style="font-size:11.5px;color:#64748b;margin-top:2px;">
                                            £<?= number_format((float)$rx['amount'],2) ?>
                                            <?= $rx['payment_method'] ? '('.ucfirst($rx['payment_method']).')' : '' ?>
                                        </div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span style="color:#94a3b8;">—</span>
                                <?php endif; ?>
                            </td>
                            <td style="color:#64748b;white-space:nowrap;font-size:13px;">
                                <?= date('d M Y', strtotime($rx['created_at'])) ?>
                            </td>
                            <td style="font-size:13px;">
                                <?php if ($rx['next_refill_date']): ?>
                                    <?php $dl = (int)((strtotime($rx['next_refill_date'])-time())/86400); ?>
                                    <span style="color:<?= $dl<=7?'#991b1b':($dl<=30?'#92400e':'#065f46') ?>;font-weight:600;">
                                        <?= date('d M Y', strtotime($rx['next_refill_date'])) ?>
                                    </span>
                                    <div style="font-size:11.5px;color:#94a3b8;">
                                        <?= $dl < 0 ? 'Overdue' : "in {$dl} day(s)" ?>
                                    </div>
                                <?php else: ?><span style="color:#94a3b8;">—</span><?php endif; ?>
                            </td>
                            <td style="text-align:center;">
                                <button class="view-btn-sm view-rx-btn" data-id="<?= $rx['prescription_id'] ?>">
                                    View
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
                <div style="text-align:center;padding:40px 20px;color:#94a3b8;">
                    No prescriptions on record yet.<br>
                    <span style="font-size:12px;margin-top:6px;display:block;">
                        Visit Drugs 4U and ask a pharmacist to create your prescription.
                    </span>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>


<!-- ════════════════════════════════════════════════
     TAB: SUBMISSIONS
════════════════════════════════════════════════ -->
<div id="tab-submissions" class="portal-section" style="display:none;">
    <div class="card">
        <div class="card-header">
            📤 My Submissions
            <button class="btn-edit" onclick="switchTab('submit')" style="color:#2563eb;">
                + New Submission
            </button>
        </div>
        <div class="card-body" style="padding:0;">
            <?php if ($requests): ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Notes</th>
                            <th>Status</th>
                            <th style="text-align:center;">Image</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($requests as $req):
                            $reqSt = match($req['status']) {
                                'pending'  => ['bg'=>'#fef3c7','text'=>'#92400e','label'=>'Pending Review'],
                                'reviewed' => ['bg'=>'#dbeafe','text'=>'#1e40af','label'=>'Under Review'],
                                'created'  => ['bg'=>'#d1fae5','text'=>'#065f46','label'=>'Prescription Created'],
                                default    => ['bg'=>'#f3f4f6','text'=>'#6b7280','label'=>ucfirst($req['status'])],
                            };
                            $ext = strtolower(pathinfo($req['image_path'], PATHINFO_EXTENSION));
                        ?>
                        <tr>
                            <td style="color:#64748b;font-size:13px;white-space:nowrap;">
                                <?= date('d M Y', strtotime($req['created_at'])) ?><br>
                                <span style="font-size:11px;"><?= date('H:i', strtotime($req['created_at'])) ?></span>
                            </td>
                            <td style="font-size:13px;color:#64748b;">
                                <?= $req['notes'] ? htmlspecialchars($req['notes']) : '<span style="color:#cbd5e1;">—</span>' ?>
                            </td>
                            <td>
                                <span class="badge" style="background:<?= $reqSt['bg'] ?>;color:<?= $reqSt['text'] ?>;">
                                    <?= $reqSt['label'] ?>
                                </span>
                            </td>
                            <td style="text-align:center;">
                                <?php if ($ext === 'pdf'): ?>
                                    <a href="/uploads/prescriptions/<?= htmlspecialchars($req['image_path']) ?>"
                                       target="_blank" style="color:#2563eb;font-size:13px;font-weight:600;">
                                        📄 View PDF
                                    </a>
                                <?php else: ?>
                                    <a href="/uploads/prescriptions/<?= htmlspecialchars($req['image_path']) ?>"
                                       target="_blank">
                                        <img src="/uploads/prescriptions/<?= htmlspecialchars($req['image_path']) ?>"
                                             style="width:56px;height:56px;object-fit:cover;
                                                    border-radius:6px;border:1px solid #e2e8f0;">
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
                <div style="text-align:center;padding:40px 20px;color:#94a3b8;">
                    No submissions yet.<br>
                    <button onclick="switchTab('submit')"
                            style="margin-top:10px;padding:8px 18px;background:#2563eb;
                                   color:#fff;border:none;border-radius:8px;cursor:pointer;font-size:13px;">
                        Submit a Prescription Image
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>


<!-- ════════════════════════════════════════════════
     TAB: SUBMIT A PRESCRIPTION
════════════════════════════════════════════════ -->
<div id="tab-submit" class="portal-section" style="display:none;">

    <?php if ($uploadSuccess): ?>
        <div class="box-green" style="margin-bottom:16px;">
            ✅ Your prescription image has been submitted successfully. A pharmacist will review it shortly.
            You can track the status in the <button onclick="switchTab('submissions')"
                style="background:none;border:none;color:#166534;font-weight:700;cursor:pointer;
                       text-decoration:underline;font-size:inherit;">Submissions</button> tab.
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">📷 Submit a Prescription Image</div>
        <div class="card-body">
            <p style="font-size:13px;color:#64748b;margin-bottom:20px;line-height:1.6;">
                Upload a clear photo or scan of your paper prescription. Our pharmacists will review it
                and prepare your medication. You'll receive a notification when it's ready.
                <br><strong style="color:#1e293b;">Accepted formats:</strong> JPG, PNG, PDF — max 10 MB.
            </p>

            <?php if ($uploadError): ?>
                <div class="box-red" style="margin-bottom:16px;">❌ <?= htmlspecialchars($uploadError) ?></div>
            <?php endif; ?>

            <form method="POST" action="/customer/actions/upload_prescription.php"
                  enctype="multipart/form-data">
                <div class="form-group">
                    <label class="form-label">Prescription Image / Scan <span style="color:#ef4444;">*</span></label>
                    <div class="upload-area" onclick="document.getElementById('fileInput').click();">
                        <div style="font-size:32px;margin-bottom:8px;">📄</div>
                        <div style="font-weight:600;color:#475569;margin-bottom:4px;">
                            Click to upload or drag and drop
                        </div>
                        <div style="font-size:12px;color:#94a3b8;">JPG, PNG, PDF up to 10 MB</div>
                        <div id="fileChosen" style="margin-top:8px;font-size:13px;color:#2563eb;"></div>
                    </div>
                    <input type="file" id="fileInput" name="prescription_image"
                           accept=".jpg,.jpeg,.png,.webp,.pdf" required style="display:none;"
                           onchange="document.getElementById('fileChosen').textContent = this.files[0]?.name || '';">
                </div>
                <div class="form-group">
                    <label class="form-label">Additional Notes <span style="color:#94a3b8;font-weight:400;">(optional)</span></label>
                    <textarea name="notes" rows="3" class="form-control"
                              placeholder="e.g. Urgent, specific brand preferred, dosage questions, collection time..."></textarea>
                </div>
                <button type="submit" class="btn-primary" style="width:100%;">
                    Submit Prescription →
                </button>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">ℹ️ What happens next?</div>
        <div class="card-body">
            <div style="display:flex;flex-direction:column;gap:14px;">
                <?php foreach ([
                    ['🔍','Review','A pharmacist reviews your prescription image, usually within a few hours during opening hours.'],
                    ['💊','Preparation','Your medication is prepared and checked by our dispensary team.'],
                    ['📧','Notification','You receive an email notification when your medication is ready for collection.'],
                    ['🏪','Collection','Visit Drugs 4U Staffordshire to collect your medication and pay.'],
                ] as [$icon,$title,$text]): ?>
                <div style="display:flex;gap:14px;align-items:flex-start;">
                    <span style="font-size:22px;flex-shrink:0;"><?= $icon ?></span>
                    <div>
                        <div style="font-weight:700;margin-bottom:2px;"><?= $title ?></div>
                        <div style="font-size:13px;color:#64748b;"><?= $text ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>


<!-- ════════════════════════════════════════════════
     TAB: HELP
════════════════════════════════════════════════ -->
<div id="tab-help" class="portal-section" style="display:none;">

    <div class="card">
        <div class="card-header">📞 Contact Drugs 4U</div>
        <div class="card-body">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                <div>
                    <div style="font-size:11px;color:#94a3b8;font-weight:700;text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px;">Address</div>
                    <div style="font-size:13.5px;">Drugs 4U Pharmacy<br>Staffordshire, UK</div>
                </div>
                <div>
                    <div style="font-size:11px;color:#94a3b8;font-weight:700;text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px;">Opening Hours</div>
                    <div style="font-size:13.5px;">Mon–Fri: 9am – 6pm<br>Sat: 9am – 1pm</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">❓ Frequently Asked Questions</div>
        <div class="card-body" style="padding:8px 20px;">
            <?php foreach ([
                ['How do I know when my prescription is ready?',
                 'If you have email notifications enabled in your Profile, we\'ll send you an email when your medication is ready for pickup. You can also check the "Ready for Pickup" status in My Prescriptions.'],
                ['How long does it take to process a prescription?',
                 'Standard prescriptions are usually ready within 2–4 hours during opening hours. Urgent requests noted in your submission will be prioritised.'],
                ['Can I upload a prescription on behalf of someone else?',
                 'The patient portal is for individual patient accounts. For prescriptions on behalf of others, please visit the pharmacy in person.'],
                ['How do I request a repeat prescription?',
                 'Upload your most recent prescription image in the Submit Prescription tab with a note saying "repeat prescription". Our pharmacists will process it subject to GP approval.'],
                ['How do I update my medical information?',
                 'Go to the Profile tab and click Edit next to the Medical Information section. Keeping this up to date helps our pharmacists provide the safest care.'],
                ['What if I have an allergy to a prescribed medication?',
                 'Contact the pharmacy immediately by phone. You can also update your known allergies in the Profile tab so our pharmacists are always aware.'],
            ] as [$q, $a]): ?>
            <div class="faq-item">
                <div class="faq-q"><?= $q ?></div>
                <div class="faq-a"><?= $a ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

</div><!-- /portal-body -->


<!-- ════════════════════════════════════════════════
     MODAL: Edit Contact
════════════════════════════════════════════════ -->
<div class="modal-overlay" id="modal-contact">
    <div class="modal-box">
        <div class="modal-hdr">
            <h3>Edit Contact Information</h3>
            <button class="modal-close" onclick="closeModal('contact')">&times;</button>
        </div>
        <div class="modal-body">
            <div id="contact-toast" class="toast-msg"></div>
            <div class="form-group">
                <label class="form-label">Email Address</label>
                <input type="email" name="email" id="edit_email" class="form-control"
                       value="<?= htmlspecialchars($p['email'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label class="form-label">Home Address <span style="color:#ef4444;">*</span></label>
                <textarea name="address" id="edit_address" class="form-control" rows="3"><?= htmlspecialchars($p['address'] ?? '') ?></textarea>
            </div>
        </div>
        <div class="modal-foot">
            <button class="btn-secondary" onclick="closeModal('contact')">Cancel</button>
            <button class="btn-primary" onclick="saveSection('contact')">Save Changes</button>
        </div>
    </div>
</div>


<!-- ════════════════════════════════════════════════
     MODAL: Edit Medical
════════════════════════════════════════════════ -->
<div class="modal-overlay" id="modal-medical">
    <div class="modal-box">
        <div class="modal-hdr">
            <h3>Edit Medical Information</h3>
            <button class="modal-close" onclick="closeModal('medical')">&times;</button>
        </div>
        <div class="modal-body">
            <div id="medical-toast" class="toast-msg"></div>
            <?php $fields = [
                ['allergies',             'Known Allergies',           'textarea', 'e.g. Penicillin, Aspirin...'],
                ['medical_conditions',    'Medical Conditions',        'textarea', 'e.g. Type 2 Diabetes, Hypertension...'],
                ['current_medications',   'Current Medications',       'textarea', 'e.g. Metformin 500mg twice daily...'],
                ['drug_sensitivities',    'Known Drug Sensitivities',  'textarea', 'e.g. NSAIDs cause stomach upset...'],
                ['medical_history',       'Medical History',           'textarea', 'Previous conditions, surgeries, diagnoses...'],
                ['primary_care_physician','Primary Care Physician',    'text',     'e.g. Dr. Smith, Staffordshire GP Surgery'],
                ['preferred_pharmacy',    'Preferred Pharmacy',        'text',     'e.g. Drugs 4U Main Branch'],
            ];
            foreach ($fields as [$name, $label, $type, $ph]): ?>
            <div class="form-group">
                <label class="form-label"><?= $label ?></label>
                <?php if ($type === 'textarea'): ?>
                    <textarea name="<?= $name ?>" id="edit_<?= $name ?>" class="form-control"
                              rows="2" placeholder="<?= $ph ?>"><?= htmlspecialchars($p[$name] ?? '') ?></textarea>
                <?php else: ?>
                    <input type="text" name="<?= $name ?>" id="edit_<?= $name ?>"
                           class="form-control" placeholder="<?= $ph ?>"
                           value="<?= htmlspecialchars($p[$name] ?? '') ?>">
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
            <div class="form-group">
                <label class="form-label">Pregnancy Status</label>
                <select name="pregnancy_status" id="edit_pregnancy_status" class="form-control">
                    <option value="not_applicable" <?= ($p['pregnancy_status']??'not_applicable')==='not_applicable'?'selected':'' ?>>Not applicable</option>
                    <option value="no"             <?= ($p['pregnancy_status']??'')==='no'     ?'selected':'' ?>>No</option>
                    <option value="yes"            <?= ($p['pregnancy_status']??'')==='yes'    ?'selected':'' ?>>Yes</option>
                    <option value="unknown"        <?= ($p['pregnancy_status']??'')==='unknown'?'selected':'' ?>>Unknown / Prefer not to say</option>
                </select>
            </div>
        </div>
        <div class="modal-foot">
            <button class="btn-secondary" onclick="closeModal('medical')">Cancel</button>
            <button class="btn-primary" onclick="saveSection('medical')">Save Changes</button>
        </div>
    </div>
</div>


<!-- ════════════════════════════════════════════════
     MODAL: Prescription Details
════════════════════════════════════════════════ -->
<div class="modal-overlay" id="modal-rxview">
    <div class="modal-box" style="max-width:520px;">
        <div class="modal-hdr">
            <div>
                <div id="rxModalTitle" style="font-size:15px;font-weight:700;"></div>
                <div id="rxModalDate" style="font-size:12px;color:#64748b;margin-top:2px;"></div>
            </div>
            <button class="modal-close" onclick="closeModal('rxview')">&times;</button>
        </div>
        <div id="rxModalStatus" style="padding:10px 20px;border-bottom:1px solid #e2e8f0;
             display:flex;align-items:center;gap:10px;flex-wrap:wrap;font-size:13px;"></div>
        <div class="modal-body">
            <div style="font-size:11px;font-weight:700;text-transform:uppercase;
                        letter-spacing:.6px;color:#64748b;margin-bottom:10px;">Prescribed Medicines</div>
            <div id="rxModalMedicines"></div>
            <div id="rxModalNotesWrap" style="display:none;margin-top:18px;">
                <div style="font-size:11px;font-weight:700;text-transform:uppercase;
                            letter-spacing:.6px;color:#64748b;margin-bottom:8px;">Pharmacist Notes</div>
                <div id="rxModalNotes" class="box-blue"></div>
            </div>
            <div id="rxModalRejectionWrap" style="display:none;margin-top:18px;">
                <div style="font-size:11px;font-weight:700;text-transform:uppercase;
                            letter-spacing:.6px;color:#64748b;margin-bottom:8px;">Rejection Reason</div>
                <div id="rxModalRejection" class="box-red"></div>
            </div>
            <div id="rxModalRefillWrap" style="display:none;margin-top:18px;">
                <div style="font-size:11px;font-weight:700;text-transform:uppercase;
                            letter-spacing:.6px;color:#64748b;margin-bottom:6px;">Next Refill Date</div>
                <div id="rxModalRefill" style="font-size:14px;font-weight:700;color:#065f46;"></div>
            </div>
        </div>
        <div class="modal-foot">
            <button class="btn-secondary" onclick="closeModal('rxview')">Close</button>
        </div>
    </div>
</div>


<script>
const rxItems = <?= json_encode($allItems) ?>;
const rxData  = <?= json_encode(array_column($prescriptions, null, 'prescription_id')) ?>;

// ── Tab switching ────────────────────────────────────────
function switchTab(name) {
    document.querySelectorAll('.portal-section').forEach(s => s.style.display = 'none');
    document.querySelectorAll('.tab-link').forEach(t => t.classList.remove('active'));
    document.getElementById('tab-' + name).style.display = 'block';
    const btn = document.querySelector(`.tab-link[data-tab="${name}"]`);
    if (btn) btn.classList.add('active');
    history.replaceState(null, '', '?tab=' + name);
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

document.querySelectorAll('.tab-link').forEach(btn => {
    btn.addEventListener('click', () => switchTab(btn.dataset.tab));
});

// Restore tab from URL
switchTab('<?= htmlspecialchars($activeTab) ?>');

// ── Modal helpers ────────────────────────────────────────
function openModal(name) {
    document.getElementById('modal-' + name).style.display = 'flex';
}
function closeModal(name) {
    document.getElementById('modal-' + name).style.display = 'none';
    const t = document.getElementById(name + '-toast');
    if (t) { t.className = 'toast-msg'; t.textContent = ''; }
}
document.querySelectorAll('.modal-overlay').forEach(ov => {
    ov.addEventListener('click', e => { if (e.target === ov) ov.style.display = 'none'; });
});

// ── Save profile sections ────────────────────────────────
async function saveSection(section) {
    const toast = document.getElementById(section + '-toast');
    const fd    = new FormData();
    fd.append('section', section);

    if (section === 'contact') {
        fd.append('email',   document.getElementById('edit_email').value);
        fd.append('address', document.getElementById('edit_address').value);
    } else if (section === 'medical') {
        ['allergies','medical_conditions','current_medications','drug_sensitivities',
         'medical_history','primary_care_physician','preferred_pharmacy','pregnancy_status']
        .forEach(f => fd.append(f, document.getElementById('edit_' + f)?.value || ''));
    }

    try {
        const res  = await fetch('/customer/actions/update_profile.php', { method: 'POST', body: fd });
        const data = await res.json();
        toast.textContent = data.message;
        toast.className   = 'toast-msg ' + (data.success ? 'toast-success' : 'toast-error');
        if (data.success) setTimeout(() => { closeModal(section); location.reload(); }, 1200);
    } catch {
        toast.textContent = 'Unexpected error. Please try again.';
        toast.className   = 'toast-msg toast-error';
    }
}

// ── Communication pref auto-save ─────────────────────────
document.querySelectorAll('.pref-toggle').forEach(toggle => {
    toggle.addEventListener('change', async function () {
        const fd = new FormData();
        fd.append('section', 'comm');
        fd.append('field',   this.dataset.field);
        fd.append('value',   this.checked ? '1' : '0');
        await fetch('/customer/actions/update_profile.php', { method: 'POST', body: fd });
    });
});

// ── Prescription view modal ──────────────────────────────
const statusLabels = {
    pending:   { bg:'#fef3c7',text:'#92400e',label:'Pending Review' },
    approved:  { bg:'#d1fae5',text:'#065f46',label:'Approved' },
    processed: { bg:'#dbeafe',text:'#1e40af',label:'Dispensed' },
    rejected:  { bg:'#fee2e2',text:'#991b1b',label:'Rejected' },
};
const payLabels = {
    paid:            { bg:'#d1fae5',text:'#065f46',label:'Paid' },
    awaiting_pickup: { bg:'#fef3c7',text:'#92400e',label:'Ready for Pickup' },
    unpaid:          { bg:'#fee2e2',text:'#991b1b',label:'Payment Due' },
};

document.querySelectorAll('.view-rx-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        const rxId = btn.dataset.id;
        const rx   = rxData[rxId];
        if (!rx) return;

        document.getElementById('rxModalTitle').textContent = 'Prescription #' + rxId;
        document.getElementById('rxModalDate').textContent  =
            'Issued: ' + new Date(rx.created_at).toLocaleDateString('en-GB',{day:'2-digit',month:'short',year:'numeric'});

        const ss = statusLabels[rx.status] || {bg:'#f3f4f6',text:'#6b7280',label:rx.status};
        let strip = `<span class="badge" style="background:${ss.bg};color:${ss.text};">${ss.label}</span>`;
        if (rx.payment_status && payLabels[rx.payment_status]) {
            const ps = payLabels[rx.payment_status];
            strip += `<span class="badge" style="background:${ps.bg};color:${ps.text};">${ps.label}</span>`;
        }
        document.getElementById('rxModalStatus').innerHTML = strip;

        const items = rxItems[rxId] || [];
        let medHtml = items.length
            ? `<table style="width:100%;border-collapse:collapse;font-size:13.5px;">
                <tr style="border-bottom:1px solid #e2e8f0;">
                    <th style="text-align:left;padding:6px 10px;font-size:11px;color:#64748b;text-transform:uppercase;">Medicine</th>
                    <th style="text-align:center;padding:6px 10px;font-size:11px;color:#64748b;">Qty</th>
                    <th style="text-align:left;padding:6px 10px;font-size:11px;color:#64748b;text-transform:uppercase;">Dosage / Instructions</th>
                </tr>` +
              items.map(i => `<tr style="border-bottom:1px solid #f1f5f9;">
                <td style="padding:10px;">${i.medication_name}</td>
                <td style="padding:10px;text-align:center;">${i.quantity}</td>
                <td style="padding:10px;color:${i.dosage?'#1e293b':'#94a3b8'};">${i.dosage||'—'}</td>
              </tr>`).join('') + '</table>'
            : '<div style="color:#94a3b8;">No medicine details available.</div>';
        document.getElementById('rxModalMedicines').innerHTML = medHtml;

        const notesWrap = document.getElementById('rxModalNotesWrap');
        if (rx.special_notes) { document.getElementById('rxModalNotes').textContent = rx.special_notes; notesWrap.style.display = 'block'; }
        else notesWrap.style.display = 'none';

        const rejWrap = document.getElementById('rxModalRejectionWrap');
        if (rx.rejection_reason && rx.status === 'rejected') { document.getElementById('rxModalRejection').textContent = rx.rejection_reason; rejWrap.style.display = 'block'; }
        else rejWrap.style.display = 'none';

        const refillWrap = document.getElementById('rxModalRefillWrap');
        if (rx.next_refill_date) {
            document.getElementById('rxModalRefill').textContent =
                new Date(rx.next_refill_date).toLocaleDateString('en-GB',{day:'2-digit',month:'long',year:'numeric'});
            refillWrap.style.display = 'block';
        } else refillWrap.style.display = 'none';

        openModal('rxview');
    });
});
</script>

<footer style="text-align:center;padding:24px;font-size:12px;color:#94a3b8;">
    &copy; <?= date('Y') ?> Drugs 4U, Staffordshire &bull; Powered by PharmaTrack
</footer>
</body>
</html>
