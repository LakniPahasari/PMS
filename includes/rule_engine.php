<?php
/**
 * PharmaTrack Rule Engine
 * Checks all active DETECTION_RULE records against a dispensing event.
 *
 * $event = 'create'  → checks patient_frequency + quantity_threshold only
 * $event = 'process' → checks all four rule types
 */
/**
 * Returns an array of violation message strings (empty if none triggered).
 * Also inserts each violation into the ALERT table.
 */
function checkDispensingRules(
    PDO    $db,
    int    $prescriptionId,
    int    $customerId,
    int    $systemUserId,
    array  $items,
    string $event = 'process'
): array {
    $violations = [];

    $rules = $db->query("SELECT * FROM DETECTION_RULE WHERE is_active = 1")->fetchAll(PDO::FETCH_ASSOC);
    if (empty($rules)) return [];

    // Resolve human-readable names once
    $custRow  = $db->prepare("SELECT name FROM CUSTOMER    WHERE customer_id    = ? LIMIT 1");
    $staffRow = $db->prepare("SELECT name FROM SYSTEM_USER WHERE system_user_id = ? LIMIT 1");
    $custRow->execute([$customerId]);
    $staffRow->execute([$systemUserId]);
    $customerName = $custRow->fetchColumn()  ?: "Customer #{$customerId}";
    $staffName    = $staffRow->fetchColumn() ?: "Staff #{$systemUserId}";

    $alertStmt = $db->prepare("
        INSERT INTO ALERT (alert_type, message, is_acknowledged, triggered_at)
        VALUES ('rule_violation', ?, 0, NOW())
    ");

    $triggered = []; // de-duplicate: rule_id + stock_id combo

    foreach ($rules as $rule) {

        // Skip types not relevant to this event
        if ($event === 'create' &&
            !in_array($rule['rule_type'], ['patient_frequency', 'quantity_threshold'])) {
            continue;
        }

        foreach ($items as $item) {
            $stockId  = (int)($item['stock_id']                          ?? 0);
            $qty      = (int)($item['prescribed_qty'] ?? $item['qty']    ?? 0);
            $category = $item['category']                                ?? '';
            $medName  = $item['medication_name']                         ?? "Medicine #{$stockId}";

            // Does this rule apply to this medicine?
            if ($rule['applies_to'] === 'category') {
                if (empty($rule['category_filter']) || $rule['category_filter'] !== $category) continue;
            } elseif ($rule['applies_to'] === 'medicine') {
                if ((int)$rule['stock_id_filter'] !== $stockId) continue;
            }

            $key = $rule['rule_id'] . '-' . $stockId;
            if (isset($triggered[$key])) continue;
            $triggered[$key] = true;

            $tag      = strtoupper($rule['severity']); // WARNING or CRITICAL
            $ruleName = $rule['rule_name'];

            switch ($rule['rule_type']) {

                // ── Same patient receives same drug too frequently ────────
                // Counts all non-rejected prescriptions (any status) so the
                // alert fires at creation time, before dispensing occurs.
                case 'patient_frequency':
                    $s = $db->prepare("
                        SELECT COUNT(DISTINCT p.prescription_id)
                        FROM PRESCRIPTION p
                        JOIN PRESCRIPTION_ITEM pi ON pi.prescription_id = p.prescription_id
                        WHERE p.customer_id   = ?
                          AND pi.stock_id     = ?
                          AND p.status        != 'rejected'
                          AND p.created_at   >= DATE_SUB(NOW(), INTERVAL ? DAY)
                          AND p.prescription_id != ?
                    ");
                    $s->execute([$customerId, $stockId, $rule['threshold_days'], $prescriptionId]);
                    $count = (int)$s->fetchColumn();
                    if ($count >= (int)$rule['threshold_count']) {
                        $msg = "[$tag] Rule \"{$ruleName}\" — {$customerName} has been prescribed '{$medName}' {$count} time(s) in the last {$rule['threshold_days']} day(s) (limit: {$rule['threshold_count']}). Prescription #{$prescriptionId}.";
                        $alertStmt->execute([$msg]);
                        $violations[] = $msg;
                    }
                    break;

                // ── Drug dispensed too many times system-wide ────────────
                case 'system_frequency':
                    $s = $db->prepare("
                        SELECT COUNT(DISTINCT p.prescription_id)
                        FROM PRESCRIPTION p
                        JOIN PRESCRIPTION_ITEM pi ON pi.prescription_id = p.prescription_id
                        WHERE pi.stock_id     = ?
                          AND p.status        = 'processed'
                          AND p.processed_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                          AND p.prescription_id != ?
                    ");
                    $s->execute([$stockId, $rule['threshold_days'], $prescriptionId]);
                    $count = (int)$s->fetchColumn();
                    if ($count >= (int)$rule['threshold_count']) {
                        $msg = "[$tag] Rule \"{$ruleName}\" — '{$medName}' has been dispensed {$count} time(s) system-wide in the last {$rule['threshold_days']} day(s) (limit: {$rule['threshold_count']}). Prescription #{$prescriptionId}.";
                        $alertStmt->execute([$msg]);
                        $violations[] = $msg;
                    }
                    break;

                // ── Single prescription quantity exceeds threshold ────────
                case 'quantity_threshold':
                    if ($qty > (int)$rule['threshold_qty']) {
                        $msg = "[$tag] Rule \"{$ruleName}\" — Prescription #{$prescriptionId} contains {$qty} unit(s) of '{$medName}', exceeding the limit of {$rule['threshold_qty']} unit(s). Patient: {$customerName}.";
                        $alertStmt->execute([$msg]);
                        $violations[] = $msg;
                    }
                    break;

                // ── Same staff member processing too many in period ───────
                case 'staff_volume':
                    $s = $db->prepare("
                        SELECT COUNT(DISTINCT p.prescription_id)
                        FROM PRESCRIPTION p
                        JOIN PRESCRIPTION_ITEM pi ON pi.prescription_id = p.prescription_id
                        WHERE p.system_user_id = ?
                          AND pi.stock_id      = ?
                          AND p.status         = 'processed'
                          AND p.processed_at  >= DATE_SUB(NOW(), INTERVAL ? DAY)
                          AND p.prescription_id != ?
                    ");
                    $s->execute([$systemUserId, $stockId, $rule['threshold_days'], $prescriptionId]);
                    $count = (int)$s->fetchColumn();
                    if ($count >= (int)$rule['threshold_count']) {
                        $msg = "[$tag] Rule \"{$ruleName}\" — {$staffName} has processed {$count} prescription(s) containing '{$medName}' in the last {$rule['threshold_days']} day(s) (limit: {$rule['threshold_count']}). Prescription #{$prescriptionId} flagged for review.";
                        $alertStmt->execute([$msg]);
                        $violations[] = $msg;
                    }
                    break;
            }
        }
    }

    return $violations;
}
