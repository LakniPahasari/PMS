<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';
requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

$customerId = (int) ($_POST['customer_id']    ?? 0);
$stockIds   = $_POST['stock_id']              ?? [];
$itemQtys   = $_POST['item_qty']              ?? [];
$dosages    = $_POST['dosage']                ?? [];
$notes      = trim($_POST['special_notes']    ?? '');
$refill     = trim($_POST['next_refill_date'] ?? '');
$allergyChk = isset($_POST['allergy_checked']) ? 1 : 0;
$idVerified = isset($_POST['age_id_verified']) ? 1 : 0;

if ($customerId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Please select a customer.']);
    exit;
}
if (empty($stockIds)) {
    echo json_encode(['success' => false, 'message' => 'Please add at least one medicine.']);
    exit;
}
if ($refill !== '' && !DateTime::createFromFormat('Y-m-d', $refill)) {
    echo json_encode(['success' => false, 'message' => 'Please enter a valid refill date.']);
    exit;
}

// Validate line items
$items = [];
foreach ($stockIds as $i => $sid) {
    $sid = (int) $sid;
    $qty = (int) ($itemQtys[$i] ?? 0);
    if ($sid <= 0) {
        echo json_encode(['success' => false, 'message' => 'Please select a medicine for every row.']);
        exit;
    }
    if ($qty <= 0) {
        echo json_encode(['success' => false, 'message' => 'Quantity must be at least 1 for every medicine.']);
        exit;
    }
    $items[] = [
        'stock_id' => $sid,
        'prescribed_qty' => $qty,
        'dosage' => trim($dosages[$i] ?? ''),
    ];
}

// No duplicate medicines
if (count(array_unique(array_column($items, 'stock_id'))) !== count($items)) {
    echo json_encode(['success' => false, 'message' => 'The same medicine cannot appear twice. Adjust the quantity instead.']);
    exit;
}

try {
    $db   = getDB();
    $user = currentUser();

    // Verify customer
    $custStmt = $db->prepare("SELECT customer_id, name FROM CUSTOMER WHERE customer_id = ? AND account_active = 1 LIMIT 1");
    $custStmt->execute([$customerId]);
    $customer = $custStmt->fetch();
    if (!$customer) {
        echo json_encode(['success' => false, 'message' => 'Customer not found or account is inactive.']);
        exit;
    }

    // Verify each medicine
    $medStmt = $db->prepare("
        SELECT stock_id, medication_name, quantity AS stock_qty, requires_id_check, expiry_date
        FROM MEDICINE_STOCK WHERE stock_id = ? AND is_active = 1 LIMIT 1
    ");
    $resolvedItems = [];
    $needsIdCheck  = false;

    foreach ($items as $item) {
        $medStmt->execute([$item['stock_id']]);
        $med = $medStmt->fetch();
        if (!$med) {
            echo json_encode(['success' => false, 'message' => 'One or more medicines were not found or are inactive.']);
            exit;
        }
        if ($med['expiry_date'] && new DateTime($med['expiry_date']) < new DateTime()) {
            echo json_encode(['success' => false, 'message' => "'{$med['medication_name']}' has expired and cannot be dispensed. Mark it inactive or choose a different batch."]);
            exit;
        }
        if ($med['stock_qty'] < $item['prescribed_qty']) {
            echo json_encode(['success' => false, 'message' => "Insufficient stock for '{$med['medication_name']}'. Only {$med['stock_qty']} unit(s) available."]);
            exit;
        }
        if ($med['requires_id_check']) $needsIdCheck = true;

        $resolvedItems[] = [
            'stock_id'        => $item['stock_id'],
            'prescribed_qty'  => $item['prescribed_qty'],
            'dosage'          => $item['dosage'],
            'stock_qty'       => (int)$med['stock_qty'],
            'medication_name' => $med['medication_name'],
            'requires_id_check' => $med['requires_id_check'],
        ];
    }

    // Hard block: age-restricted medicine requires ID verified before proceeding
    if ($needsIdCheck && !$idVerified) {
        echo json_encode(['success' => false, 'message' => 'One or more medicines require age/ID verification. Please confirm you have checked the customer\'s ID before submitting.']);
        exit;
    }

    $db->beginTransaction();

    // Insert prescription header
    $db->prepare("
        INSERT INTO PRESCRIPTION
            (customer_id, system_user_id, special_notes, next_refill_date,
             allergy_checked, age_id_verified, status)
        VALUES (?, ?, ?, ?, ?, ?, 'pending')
    ")->execute([
        $customerId,
        $user['id'],
        $notes  ?: null,
        $refill ?: null,
        $allergyChk,
        $idVerified,
    ]);
    $prescriptionId = $db->lastInsertId();

    // Insert line items + alerts
    $itemStmt  = $db->prepare("INSERT INTO PRESCRIPTION_ITEM (prescription_id, stock_id, quantity, dosage) VALUES (?, ?, ?, ?)");
    $alertStmt = $db->prepare("INSERT INTO ALERT (alert_type, message, is_acknowledged, triggered_at) VALUES (?, ?, 0, NOW())");

    foreach ($resolvedItems as $ri) {
        $itemStmt->execute([$prescriptionId, $ri['stock_id'], $ri['prescribed_qty'], $ri['dosage'] ?: null]);

        $stockAfter = $ri['stock_qty'] - $ri['prescribed_qty'];
        if ($stockAfter < 10) {
            $alertStmt->execute([
                'low_stock',
                "Low stock: '{$ri['medication_name']}' will have {$stockAfter} unit(s) remaining after prescription #{$prescriptionId}."
            ]);
        }
    }

    $db->commit();

    $db->prepare("
        INSERT INTO AUDIT_LOG (system_user_id, action_type, target_table, target_id, timestamp)
        VALUES (?, 'prescription_created', 'PRESCRIPTION', ?, NOW())
    ")->execute([$user['id'], $prescriptionId]);

    echo json_encode(['success' => true, 'message' => "Prescription for {$customer['name']} created successfully.", 'prescription_id' => (int)$prescriptionId]);

} catch (PDOException $e) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
