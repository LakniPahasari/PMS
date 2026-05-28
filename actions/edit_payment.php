<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';
requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

$payId  = (int)   ($_POST['payment_id']      ?? 0);
$status = trim(   $_POST['payment_status']   ?? '');
$method = trim(   $_POST['payment_method']   ?? '');
$amount = trim(   $_POST['amount']           ?? '');
$notes  = trim(   $_POST['notes']            ?? '');

$allowedStatuses = ['unpaid', 'awaiting_pickup', 'paid'];

if ($payId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid payment ID.']);
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

    $cur = $db->prepare("SELECT payment_status, paid_at FROM PAYMENT WHERE payment_id = ? LIMIT 1");
    $cur->execute([$payId]);
    $existing = $cur->fetch();
    if (!$existing) {
        echo json_encode(['success' => false, 'message' => 'Payment record not found.']);
        exit;
    }

    // Set paid_at only on first transition to paid
    $paidAt = $existing['paid_at'];
    if ($status === 'paid' && !$paidAt) {
        $paidAt = date('Y-m-d H:i:s');
    } elseif ($status !== 'paid') {
        $paidAt = null;
    }

    $db->prepare("
        UPDATE PAYMENT
        SET payment_status = ?, payment_method = ?, amount = ?, notes = ?, paid_at = ?
        WHERE payment_id = ?
    ")->execute([
        $status,
        $method  ?: null,
        $amount !== '' ? (float)$amount : null,
        $notes   ?: null,
        $paidAt,
        $payId,
    ]);

    $db->prepare("
        INSERT INTO AUDIT_LOG (system_user_id, action_type, target_table, target_id, details, timestamp)
        VALUES (?, 'payment_updated', 'PAYMENT', ?, ?, NOW())
    ")->execute([$user['id'], $payId, json_encode([
        'old_status' => $existing['payment_status'],
        'new_status' => $status,
    ])]);

    echo json_encode(['success' => true, 'message' => 'Payment updated successfully.']);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
