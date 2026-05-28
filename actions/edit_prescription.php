<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';
requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

$id              = (int) ($_POST['prescription_id']   ?? 0);
$status          = trim($_POST['status']               ?? '');
$notes           = trim($_POST['special_notes']        ?? '');
$refill          = trim($_POST['next_refill_date']     ?? '');
$rejectionReason = trim($_POST['rejection_reason']     ?? '');
$allergyChk      = isset($_POST['allergy_checked'])    ? 1 : 0;
$idVerified      = isset($_POST['age_id_verified'])    ? 1 : 0;
$stockIds        = $_POST['stock_id']                  ?? [];
$itemQtys        = $_POST['item_qty']                  ?? [];
$dosages         = $_POST['dosage']                    ?? [];

$allowedStatuses = ['pending', 'approved', 'processed', 'rejected'];

if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid prescription ID.']);
    exit;
}
if (!in_array($status, $allowedStatuses)) {
    echo json_encode(['success' => false, 'message' => 'Invalid status value.']);
    exit;
}
if ($status === 'rejected' && $rejectionReason === '') {
    echo json_encode(['success' => false, 'message' => 'Please provide a reason for rejection.']);
    exit;
}
if ($refill !== '' && !DateTime::createFromFormat('Y-m-d', $refill)) {
    echo json_encode(['success' => false, 'message' => 'Please enter a valid refill date.']);
    exit;
}

try {
    $db = getDB();

    // Fetch current prescription status before updating
    $cur = $db->prepare("SELECT status FROM PRESCRIPTION WHERE prescription_id = ? LIMIT 1");
    $cur->execute([$id]);
    $current = $cur->fetch();
    if (!$current) {
        echo json_encode(['success' => false, 'message' => 'Prescription not found.']);
        exit;
    }
    $oldStatus = $current['status'];

    // Validate and resolve submitted medicine items if provided
    $resolvedItems = [];
    if (!empty($stockIds)) {
        $items = [];
        foreach ($stockIds as $i => $sid) {
            $sid = (int) $sid;
            $qty = (int) ($itemQtys[$i] ?? 0);
            if ($sid <= 0) { echo json_encode(['success' => false, 'message' => 'Please select a medicine for every row.']); exit; }
            if ($qty <= 0) { echo json_encode(['success' => false, 'message' => 'Quantity must be at least 1 for every medicine.']); exit; }
            $items[] = ['stock_id' => $sid, 'qty' => $qty, 'dosage' => trim($dosages[$i] ?? '')];
        }

        if (count(array_unique(array_column($items, 'stock_id'))) !== count($items)) {
            echo json_encode(['success' => false, 'message' => 'The same medicine cannot appear twice.']);
            exit;
        }

        $medStmt = $db->prepare("
            SELECT stock_id, medication_name, quantity AS stock_qty, requires_id_check, expiry_date
            FROM MEDICINE_STOCK WHERE stock_id = ? AND is_active = 1 LIMIT 1
        ");
        foreach ($items as $item) {
            $medStmt->execute([$item['stock_id']]);
            $med = $medStmt->fetch();
            if (!$med) { echo json_encode(['success' => false, 'message' => 'One or more medicines not found or inactive.']); exit; }
            if ($med['expiry_date'] && new DateTime($med['expiry_date']) < new DateTime()) {
                echo json_encode(['success' => false, 'message' => "'{$med['medication_name']}' has expired and cannot be dispensed. Mark it inactive or choose a different batch."]);
                exit;
            }
            $resolvedItems[] = array_merge($item, [
                'stock_qty'         => (int)$med['stock_qty'],
                'medication_name'   => $med['medication_name'],
                'requires_id_check' => $med['requires_id_check'],
            ]);
        }
    }

    // Stock check if transitioning to 'processed' for the first time
    if ($status === 'processed' && $oldStatus !== 'processed') {

        // Use submitted items if provided, otherwise load existing items from DB
        $itemsToProcess = $resolvedItems;
        if (empty($itemsToProcess)) {
            $existing = $db->prepare("
                SELECT pi.stock_id, pi.quantity AS qty, pi.dosage,
                       m.medication_name, m.quantity AS stock_qty, m.requires_id_check
                FROM PRESCRIPTION_ITEM pi
                JOIN MEDICINE_STOCK m ON m.stock_id = pi.stock_id
                WHERE pi.prescription_id = ?
            ");
            $existing->execute([$id]);
            $itemsToProcess = $existing->fetchAll();
            foreach ($itemsToProcess as &$it) {
                $it['stock_qty'] = (int)$it['stock_qty'];
            }
            unset($it);
        }

        // Block if any medicine has zero or insufficient stock
        foreach ($itemsToProcess as $ri) {
            if ($ri['stock_qty'] <= 0) {
                echo json_encode(['success' => false, 'message' => "Cannot process: '{$ri['medication_name']}' is out of stock."]);
                exit;
            }
            $prescribed = $ri['qty'] ?? $ri['prescribed_qty'] ?? 0;
            if ($ri['stock_qty'] < $prescribed) {
                echo json_encode(['success' => false, 'message' => "Cannot process: insufficient stock for '{$ri['medication_name']}'. Only {$ri['stock_qty']} unit(s) available."]);
                exit;
            }
        }
    }

    $db->beginTransaction();

    // Update prescription header
    $processedAt = ($status === 'processed' && $oldStatus !== 'processed') ? date('Y-m-d H:i:s') : null;

    $db->prepare("
        UPDATE PRESCRIPTION
        SET status = ?, special_notes = ?, next_refill_date = ?,
            allergy_checked = ?, age_id_verified = ?,
            rejection_reason = ?,
            processed_at = COALESCE(processed_at, ?)
        WHERE prescription_id = ?
    ")->execute([
        $status,
        $notes           ?: null,
        $refill          ?: null,
        $allergyChk,
        $idVerified,
        ($status === 'rejected' ? $rejectionReason : null),
        $processedAt,
        $id,
    ]);

    // Update line items if submitted
    if (!empty($resolvedItems)) {
        $db->prepare("DELETE FROM PRESCRIPTION_ITEM WHERE prescription_id = ?")->execute([$id]);
        $itemStmt  = $db->prepare("INSERT INTO PRESCRIPTION_ITEM (prescription_id, stock_id, quantity, dosage) VALUES (?, ?, ?, ?)");
        $alertStmt = $db->prepare("INSERT INTO ALERT (alert_type, message, is_acknowledged, triggered_at) VALUES (?, ?, 0, NOW())");

        foreach ($resolvedItems as $ri) {
            $itemStmt->execute([$id, $ri['stock_id'], $ri['qty'], $ri['dosage'] ?: null]);

            if ($ri['requires_id_check'] && !$idVerified) {
                $alertStmt->execute([
                    'age_restriction',
                    "ID verification required on prescription #{$id}: '{$ri['medication_name']}' requires customer ID/DOB check."
                ]);
            }
        }
    }

    // Deduct stock when first transitioning to 'processed'
    if ($status === 'processed' && $oldStatus !== 'processed') {
        $itemsToDeduct = !empty($resolvedItems) ? $resolvedItems : [];

        if (empty($itemsToDeduct)) {
            $existing = $db->prepare("SELECT stock_id, quantity AS qty FROM PRESCRIPTION_ITEM WHERE prescription_id = ?");
            $existing->execute([$id]);
            $itemsToDeduct = $existing->fetchAll();
        }

        $deductStmt = $db->prepare("UPDATE MEDICINE_STOCK SET quantity = quantity - ? WHERE stock_id = ?");
        $alertStmt2 = $db->prepare("INSERT INTO ALERT (alert_type, message, is_acknowledged, triggered_at) VALUES ('low_stock', ?, 0, NOW())");
        $checkStmt  = $db->prepare("SELECT medication_name, quantity FROM MEDICINE_STOCK WHERE stock_id = ?");

        foreach ($itemsToDeduct as $ri) {
            $prescribed = $ri['qty'] ?? $ri['prescribed_qty'] ?? 0;
            $deductStmt->execute([$prescribed, $ri['stock_id']]);

            // Check new stock level and alert if < 10
            $checkStmt->execute([$ri['stock_id']]);
            $updated = $checkStmt->fetch();
            if ($updated && $updated['quantity'] < 10) {
                $alertStmt2->execute([
                    "Low stock: '{$updated['medication_name']}' has {$updated['quantity']} unit(s) remaining after prescription #{$id} was processed."
                ]);
            }
        }
    }

    $db->commit();

    $user = currentUser();
    $db->prepare("
        INSERT INTO AUDIT_LOG (system_user_id, action_type, target_table, target_id, details, timestamp)
        VALUES (?, 'prescription_updated', 'PRESCRIPTION', ?, ?, NOW())
    ")->execute([$user['id'], $id, json_encode(['new_status' => $status, 'old_status' => $oldStatus])]);

    echo json_encode(['success' => true, 'message' => 'Prescription updated successfully.']);

} catch (PDOException $e) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
