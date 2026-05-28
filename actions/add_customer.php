<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';
requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

$name      = trim($_POST['name']            ?? '');
$email     = trim($_POST['email']           ?? '');
$dob       = trim($_POST['date_of_birth']   ?? '');
$address   = trim($_POST['address']         ?? '');
$history   = trim($_POST['medical_history'] ?? '');
$allergies = trim($_POST['allergies']       ?? '');

// Required field validation
if ($name === '') {
    echo json_encode(['success' => false, 'message' => 'Full name is required.']);
    exit;
}
if ($dob === '') {
    echo json_encode(['success' => false, 'message' => 'Date of birth is required.']);
    exit;
}
if (!DateTime::createFromFormat('Y-m-d', $dob)) {
    echo json_encode(['success' => false, 'message' => 'Please enter a valid date of birth.']);
    exit;
}
if ($address === '') {
    echo json_encode(['success' => false, 'message' => 'Address is required.']);
    exit;
}
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Please enter a valid email address.']);
    exit;
}

try {
    $db = getDB();

    // Duplicate check: same name + same date of birth
    $dup = $db->prepare("SELECT customer_id FROM CUSTOMER WHERE name = ? AND date_of_birth = ? LIMIT 1");
    $dup->execute([$name, $dob]);
    if ($dup->fetch()) {
        echo json_encode(['success' => false, 'message' => "A customer named '{$name}' with this date of birth already exists."]);
        exit;
    }

    // Duplicate email check
    if ($email !== '') {
        $dupEmail = $db->prepare("SELECT customer_id FROM CUSTOMER WHERE email = ? LIMIT 1");
        $dupEmail->execute([$email]);
        if ($dupEmail->fetch()) {
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
        $email     ?: null,
        $dob,
        $address,
        $history   ?: null,
        $allergies ?: null,
    ]);

    $newId = $db->lastInsertId();

    $user = currentUser();
    $db->prepare("
        INSERT INTO AUDIT_LOG (system_user_id, action_type, target_table, target_id, timestamp)
        VALUES (?, 'customer_created', 'CUSTOMER', ?, NOW())
    ")->execute([$user['id'], $newId]);

    echo json_encode(['success' => true, 'message' => "Customer '{$name}' registered successfully.", 'customer_id' => $newId]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
