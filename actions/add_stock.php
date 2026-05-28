<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';
requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

$name      = trim($_POST['medication_name'] ?? '');
$category  = trim($_POST['category']        ?? '');
$batch     = trim($_POST['batch_number']    ?? '');
$qty       = $_POST['quantity'] ?? '';
$price     = trim($_POST['unit_price']      ?? '');
$expiry    = trim($_POST['expiry_date']     ?? '');
$supplier  = trim($_POST['supplier']        ?? '');
$idCheck   = isset($_POST['requires_id_check']) ? 1 : 0;

if ($name === '') {
    echo json_encode(['success' => false, 'message' => 'Medication name is required.']);
    exit;
}
if ($qty === '' || !is_numeric($qty) || (int)$qty < 0) {
    echo json_encode(['success' => false, 'message' => 'Please enter a valid quantity (0 or more).']);
    exit;
}
if ($price !== '' && (!is_numeric($price) || (float)$price < 0)) {
    echo json_encode(['success' => false, 'message' => 'Please enter a valid unit price.']);
    exit;
}
if ($expiry !== '' && !DateTime::createFromFormat('Y-m-d', $expiry)) {
    echo json_encode(['success' => false, 'message' => 'Please enter a valid expiry date.']);
    exit;
}

try {
    $db   = getDB();
    $user = currentUser();

    // Duplicate medicine name check (case-insensitive)
    $dup = $db->prepare("SELECT stock_id FROM MEDICINE_STOCK WHERE LOWER(medication_name) = LOWER(?) LIMIT 1");
    $dup->execute([$name]);
    if ($dup->fetch()) {
        echo json_encode(['success' => false, 'message' => "A medicine named '{$name}' already exists. Edit it instead, or use a more specific name (e.g. include strength/dosage form)."]);
        exit;
    }

    $stmt = $db->prepare("
        INSERT INTO MEDICINE_STOCK
            (medication_name, category, batch_number, quantity, unit_price, expiry_date, supplier, requires_id_check, is_active)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)
    ");
    $stmt->execute([
        $name,
        $category ?: null,
        $batch    ?: null,
        (int)$qty,
        $price    !== '' ? (float)$price : null,
        $expiry   ?: null,
        $supplier ?: null,
        $idCheck,
    ]);

    $newId = $db->lastInsertId();

    // Audit log
    $db->prepare("
        INSERT INTO AUDIT_LOG (system_user_id, action_type, target_table, target_id, timestamp)
        VALUES (?, 'stock_created', 'MEDICINE_STOCK', ?, NOW())
    ")->execute([$user['id'], $newId]);

    // Low stock alert when quantity < 10
    if ((int)$qty < 10) {
        $db->prepare("
            INSERT INTO ALERT (alert_type, message, is_acknowledged, triggered_at)
            VALUES ('low_stock', ?, 0, NOW())
        ")->execute(["Low stock: '{$name}' has only {$qty} unit(s) remaining."]);
    }

    echo json_encode(['success' => true, 'message' => "'{$name}' added to stock successfully."]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
