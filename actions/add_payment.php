<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';
requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

$rxId     = (int)   ($_POST['prescription_id']  ?? 0);
$custId   = (int)   ($_POST['customer_id']       ?? 0);
$status   = trim(   $_POST['payment_status']     ?? '');
$method   = trim(   $_POST['payment_method']     ?? '');
$amount   = trim(   $_POST['amount']             ?? '');
$notes    = trim(   $_POST['notes']              ?? '');

$allowedStatuses = ['unpaid', 'awaiting_pickup', 'paid'];
$allowedMethods  = ['cash', 'card', ''];

if ($rxId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid prescription.']);
    exit;
}
if (!in_array($status, $allowedStatuses)) {
    echo json_encode(['success' => false, 'message' => 'Please select a payment status.']);
    exit;
}
if ($status === 'paid' && $method === '') {
    echo json_encode(['success' => false, 'message' => 'Please select a payment method (Cash or Card) for paid status.']);
    exit;
}
if ($amount !== '' && (!is_numeric($amount) || (float)$amount < 0)) {
    echo json_encode(['success' => false, 'message' => 'Please enter a valid amount.']);
    exit;
}

try {
    $db   = getDB();
    $user = currentUser();

    // Prevent duplicate payment for same prescription
    $dup = $db->prepare("SELECT payment_id FROM PAYMENT WHERE prescription_id = ? LIMIT 1");
    $dup->execute([$rxId]);
    if ($dup->fetch()) {
        echo json_encode(['success' => false, 'message' => 'A payment record already exists for this prescription. Use the edit option instead.']);
        exit;
    }

    // Verify prescription exists and get customer_id if not supplied
    $rxCheck = $db->prepare("SELECT prescription_id, customer_id FROM PRESCRIPTION WHERE prescription_id = ? LIMIT 1");
    $rxCheck->execute([$rxId]);
    $rx = $rxCheck->fetch();
    if (!$rx) {
        echo json_encode(['success' => false, 'message' => 'Prescription not found.']);
        exit;
    }
    if ($custId <= 0) $custId = (int)$rx['customer_id'];

    $paidAt = ($status === 'paid') ? date('Y-m-d H:i:s') : null;

    $db->prepare("
        INSERT INTO PAYMENT
            (prescription_id, amount, billed_date, payment_method, payment_status, notes, paid_at, system_user_id)
        VALUES (?, ?, CURDATE(), ?, ?, ?, ?, ?)
    ")->execute([
        $rxId,
        $amount !== '' ? (float)$amount : null,
        $method  ?: null,
        $status,
        $notes   ?: null,
        $paidAt,
        $user['id'],
    ]);

    $payId = $db->lastInsertId();

    $db->prepare("
        INSERT INTO AUDIT_LOG (system_user_id, action_type, target_table, target_id, details, timestamp)
        VALUES (?, 'payment_created', 'PAYMENT', ?, ?, NOW())
    ")->execute([$user['id'], $payId, json_encode(['prescription_id' => $rxId, 'status' => $status])]);

    echo json_encode(['success' => true, 'message' => 'Payment recorded successfully.']);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
