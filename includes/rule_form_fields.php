<?php
// Shared form fields for add and edit rule modals.
// $medicines and $categories must be defined in the including page.
?>
<div class="form-group">
    <label class="form-label">Rule Name <span class="required">*</span></label>
    <input type="text" name="rule_name" class="form-control"
           placeholder="e.g. Antibiotic Repeat Patient" required>
</div>

<div class="form-group">
    <label class="form-label">Description</label>
    <input type="text" name="description" class="form-control"
           placeholder="Optional — brief explanation of this rule's purpose">
</div>

<div class="form-row">
    <div class="form-group">
        <label class="form-label">Rule Type <span class="required">*</span></label>
        <select name="rule_type" class="form-control" required>
            <option value="">— Select type —</option>
            <option value="patient_frequency">Patient Frequency — same patient, same drug, too often</option>
            <option value="system_frequency">System Frequency — drug dispensed too much system-wide</option>
            <option value="quantity_threshold">Quantity Threshold — single prescription over limit</option>
            <option value="staff_volume">Staff Volume — staff member processing too many</option>
        </select>
    </div>
    <div class="form-group">
        <label class="form-label">Severity <span class="required">*</span></label>
        <select name="severity" class="form-control" required>
            <option value="warning">Warning</option>
            <option value="critical">Critical</option>
        </select>
    </div>
</div>

<div class="form-group">
    <label class="form-label">Applies To <span class="required">*</span></label>
    <select name="applies_to" class="form-control" required>
        <option value="all">All Medicines</option>
        <option value="category">Specific Category</option>
        <option value="medicine">Specific Medicine</option>
    </select>
</div>

<!-- Category filter -->
<div class="form-group field-group fg-category">
    <label class="form-label">Category <span class="required">*</span></label>
    <select name="category_filter" class="form-control">
        <option value="">— Select category —</option>
        <?php foreach ($categories as $cat): ?>
            <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option>
        <?php endforeach; ?>
    </select>
</div>

<!-- Specific medicine filter -->
<div class="form-group field-group fg-medicine">
    <label class="form-label">Medicine <span class="required">*</span></label>
    <select name="stock_id_filter" class="form-control">
        <option value="">— Select medicine —</option>
        <?php foreach ($medicines as $m): ?>
            <option value="<?= $m['stock_id'] ?>">
                <?= htmlspecialchars($m['medication_name']) ?>
                <?php if ($m['category']): ?>(<?= htmlspecialchars($m['category']) ?>)<?php endif; ?>
            </option>
        <?php endforeach; ?>
    </select>
</div>

<!-- Count + Days (patient_frequency, system_frequency, staff_volume) -->
<div class="form-row field-group fg-count-days">
    <div class="form-group">
        <label class="form-label">Threshold Count <span class="required">*</span></label>
        <input type="number" name="threshold_count" class="form-control" min="1" placeholder="e.g. 3">
        <div style="font-size:12px;color:var(--text-muted);margin-top:3px;">
            Trigger alert when this count is reached or exceeded
        </div>
    </div>
    <div class="form-group">
        <label class="form-label">Within Days <span class="required">*</span></label>
        <input type="number" name="threshold_days" class="form-control" min="1" placeholder="e.g. 30">
        <div style="font-size:12px;color:var(--text-muted);margin-top:3px;">
            Look-back window in days
        </div>
    </div>
</div>

<!-- Qty (quantity_threshold) -->
<div class="form-group field-group fg-qty">
    <label class="form-label">Maximum Units Per Prescription <span class="required">*</span></label>
    <input type="number" name="threshold_qty" class="form-control" min="1" placeholder="e.g. 30">
    <div style="font-size:12px;color:var(--text-muted);margin-top:3px;">
        Alert if a single prescription exceeds this quantity
    </div>
</div>

<!-- Hint box -->
<div style="background:var(--bg);border-radius:8px;padding:10px 14px;font-size:12.5px;color:var(--text-muted);margin-top:8px;">
    💡 Rules run automatically on every prescription save and dispensing event.
    Triggered rules generate an alert on the <strong>Alerts</strong> page.
</div>
