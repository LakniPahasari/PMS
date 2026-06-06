<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';
requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

$requestId = (int)($_POST['request_id'] ?? 0);
$status    = trim($_POST['status']      ?? '');

if ($requestId <= 0 || !in_array($status, ['reviewed', 'created'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters.']);
    exit;
}

try {
    $db   = getDB();
    $user = currentUser();

    $db->prepare("
        UPDATE PRESCRIPTION_REQUEST
        SET status = ?, reviewed_by = ?, reviewed_at = NOW()
        WHERE request_id = ?
    ")->execute([$status, $user['id'], $requestId]);

    $db->prepare("
        INSERT INTO AUDIT_LOG (system_user_id, action_type, target_table, target_id, details, timestamp)
        VALUES (?, 'request_updated', 'PRESCRIPTION_REQUEST', ?, ?, NOW())
    ")->execute([$user['id'], $requestId, json_encode(['status' => $status])]);

    echo json_encode(['success' => true]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
