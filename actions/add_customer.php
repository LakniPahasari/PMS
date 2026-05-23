<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';
requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

$name       = trim($_POST['name']           ?? '');
$email      = trim($_POST['email']          ?? '');
$dob        = trim($_POST['date_of_birth']  ?? '');
$address    = trim($_POST['address']        ?? '');
$history    = trim($_POST['medical_history']?? '');
$allergies  = trim($_POST['allergies']      ?? '');

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

    // Check duplicate email
    if ($email !== '') {
        $exists = $db->prepare("SELECT customer_id FROM CUSTOMER WHERE email = ? LIMIT 1");
        $exists->execute([$email]);
        if ($exists->fetch()) {
            echo json_encode(['success' => false, 'message' => 'A customer with this email already exists.']);
            exit;
        }
    }

    $stmt = $db->prepare("
        INSERT INTO CUSTOMER (name, email, date_of_birth, address, medical_history, allergies, account_active)
        VALUES (?, ?, ?, ?, ?, ?, 1)
    ");
    $stmt->execute([
        $name,
        $email  ?: null,
        $dob    ?: null,
        $address    ?: null,
        $history    ?: null,
        $allergies  ?: null,
    ]);

    $newId = $db->lastInsertId();

    // Audit log
    $user = currentUser();
    $db->prepare("
        INSERT INTO AUDIT_LOG (system_user_id, action_type, target_table, target_id, timestamp)
        VALUES (?, 'customer_created', 'CUSTOMER', ?, NOW())
    ")->execute([$user['id'], $newId]);

    echo json_encode(['success' => true, 'message' => "Customer '{$name}' added successfully.", 'customer_id' => $newId]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
