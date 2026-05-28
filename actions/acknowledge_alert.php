<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';
requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

$all     = !empty($_POST['all']);
$alertId = (int)($_POST['alert_id'] ?? 0);

try {
    $db = getDB();

    if ($all) {
        $db->exec("UPDATE ALERT SET is_acknowledged = 1 WHERE is_acknowledged = 0");
        echo json_encode(['success' => true, 'message' => 'All alerts acknowledged.']);
    } elseif ($alertId > 0) {
        $db->prepare("UPDATE ALERT SET is_acknowledged = 1 WHERE alert_id = ?")->execute([$alertId]);
        echo json_encode(['success' => true, 'message' => 'Alert acknowledged.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid alert ID.']);
    }

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
