<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';
requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

$id         = (int) ($_POST['customer_id']    ?? 0);
$name       = trim($_POST['name']             ?? '');
$email      = trim($_POST['email']            ?? '');
$dob        = trim($_POST['date_of_birth']    ?? '');
$address    = trim($_POST['address']          ?? '');
$history    = trim($_POST['medical_history']  ?? '');
$allergies  = trim($_POST['allergies']        ?? '');
$active     = isset($_POST['account_active']) ? 1 : 0;

if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid customer ID.']);
    exit;
}
if ($name === '') {
    echo json_encode(['success' => false, 'message' => 'Customer name is required.']);
    exit;
}
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Please enter a valid email address.']);
    exit;
}
if ($dob !== '' && !DateTime::createFromFormat('Y-m-d', $dob)) {
    echo json_encode(['success' => false, 'message' => 'Please enter a valid date of birth.']);
    exit;
}

try {
    $db = getDB();

    // Check customer exists
    $check = $db->prepare("SELECT customer_id FROM CUSTOMER WHERE customer_id = ? LIMIT 1");
    $check->execute([$id]);
    if (!$check->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Customer not found.']);
        exit;
    }

    // Check duplicate email (excluding this customer)
    if ($email !== '') {
        $dup = $db->prepare("SELECT customer_id FROM CUSTOMER WHERE email = ? AND customer_id != ? LIMIT 1");
        $dup->execute([$email, $id]);
        if ($dup->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Another customer already has this email.']);
            exit;
        }
    }

    $stmt = $db->prepare("
        UPDATE CUSTOMER
        SET name = ?, email = ?, date_of_birth = ?, address = ?,
            medical_history = ?, allergies = ?, account_active = ?
        WHERE customer_id = ?
    ");
    $stmt->execute([
        $name,
        $email      ?: null,
        $dob        ?: null,
        $address    ?: null,
        $history    ?: null,
        $allergies  ?: null,
        $active,
        $id,
    ]);

    // Audit log
    $user = currentUser();
    $db->prepare("
        INSERT INTO AUDIT_LOG (system_user_id, action_type, target_table, target_id, timestamp)
        VALUES (?, 'customer_updated', 'CUSTOMER', ?, NOW())
    ")->execute([$user['id'], $id]);

    echo json_encode(['success' => true, 'message' => "Customer '{$name}' updated successfully."]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
