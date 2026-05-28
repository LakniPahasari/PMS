<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';
requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

$id       = (int) ($_POST['stock_id']        ?? 0);
$name     = trim($_POST['medication_name']   ?? '');
$category = trim($_POST['category']          ?? '');
$batch    = trim($_POST['batch_number']      ?? '');
$qty      = $_POST['quantity'] ?? '';
$price    = trim($_POST['unit_price']        ?? '');
$expiry   = trim($_POST['expiry_date']       ?? '');
$supplier = trim($_POST['supplier']          ?? '');
$idCheck  = isset($_POST['requires_id_check']) ? 1 : 0;
$active   = isset($_POST['is_active'])         ? 1 : 0;

if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid stock ID.']);
    exit;
}
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
    $db = getDB();

    $check = $db->prepare("SELECT stock_id, medication_name, quantity, is_active FROM MEDICINE_STOCK WHERE stock_id = ? LIMIT 1");
    $check->execute([$id]);
    $existing = $check->fetch();

    if (!$existing) {
        echo json_encode(['success' => false, 'message' => 'Medicine not found.']);
        exit;
    }

    $oldQty    = (int)$existing['quantity'];
    $newQty    = (int)$qty;
    $oldActive = (int)$existing['is_active'];

    $db->prepare("
        UPDATE MEDICINE_STOCK
        SET medication_name = ?, category = ?, batch_number = ?,
            quantity = ?, unit_price = ?, expiry_date = ?,
            supplier = ?, requires_id_check = ?, is_active = ?
        WHERE stock_id = ?
    ")->execute([
        $name,
        $category ?: null,
        $batch    ?: null,
        $newQty,
        $price !== '' ? (float)$price : null,
        $expiry   ?: null,
        $supplier ?: null,
        $idCheck,
        $active,
        $id,
    ]);

    $user = currentUser();

    // Build change details for audit
    $changes = [];
    if ($oldQty !== $newQty) {
        $changes['quantity'] = ['before' => $oldQty, 'after' => $newQty];
    }
    if ($existing['medication_name'] !== $name) {
        $changes['medication_name'] = ['before' => $existing['medication_name'], 'after' => $name];
    }
    if ($oldActive !== $active) {
        $changes['is_active'] = ['before' => $oldActive === 1 ? 'Active' : 'Inactive', 'after' => $active === 1 ? 'Active' : 'Inactive'];
    }

    $db->prepare("
        INSERT INTO AUDIT_LOG (system_user_id, action_type, target_table, target_id, details, timestamp)
        VALUES (?, 'stock_updated', 'MEDICINE_STOCK', ?, ?, NOW())
    ")->execute([$user['id'], $id, $changes ? json_encode($changes) : null]);

    // Low stock alert when quantity drops below 10
    if ((int)$qty < 10 && (int)$existing['quantity'] >= 10) {
        $db->prepare("
            INSERT INTO ALERT (alert_type, message, is_acknowledged, triggered_at)
            VALUES ('low_stock', ?, 0, NOW())
        ")->execute(["Low stock: '{$name}' has dropped to {$qty} unit(s)."]);
    }

    echo json_encode(['success' => true, 'message' => "'{$name}' updated successfully."]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
