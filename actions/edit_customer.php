<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';
requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

$id        = (int) ($_POST['customer_id']    ?? 0);
$name      = trim($_POST['name']             ?? '');
$email     = trim($_POST['email']            ?? '');
$dob       = trim($_POST['date_of_birth']    ?? '');
$address   = trim($_POST['address']          ?? '');
$history   = trim($_POST['medical_history']  ?? '');
$allergies = trim($_POST['allergies']        ?? '');
$active    = isset($_POST['account_active']) ? 1 : 0;

if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid customer ID.']);
    exit;
}
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

    // Fetch current values before update (for audit trail)
    $old = $db->prepare("SELECT name, email, date_of_birth, address, medical_history, allergies, account_active FROM CUSTOMER WHERE customer_id = ? LIMIT 1");
    $old->execute([$id]);
    $before = $old->fetch();

    if (!$before) {
        echo json_encode(['success' => false, 'message' => 'Customer not found.']);
        exit;
    }

    // Duplicate email (excluding this customer)
    if ($email !== '') {
        $dup = $db->prepare("SELECT customer_id FROM CUSTOMER WHERE email = ? AND customer_id != ? LIMIT 1");
        $dup->execute([$email, $id]);
        if ($dup->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Another customer already has this email.']);
            exit;
        }
    }

    $db->prepare("
        UPDATE CUSTOMER
        SET name = ?, email = ?, date_of_birth = ?, address = ?,
            medical_history = ?, allergies = ?, account_active = ?
        WHERE customer_id = ?
    ")->execute([
        $name,
        $email     ?: null,
        $dob,
        $address,
        $history   ?: null,
        $allergies ?: null,
        $active,
        $id,
    ]);

    // Build change summary for audit details
    $changes = [];
    $fields = [
        'name'           => 'Name',
        'email'          => 'Email',
        'date_of_birth'  => 'Date of Birth',
        'address'        => 'Address',
        'medical_history'=> 'Medical History',
        'allergies'      => 'Allergies',
        'account_active' => 'Account Status',
    ];
    $newValues = [
        'name'            => $name,
        'email'           => $email ?: null,
        'date_of_birth'   => $dob,
        'address'         => $address,
        'medical_history' => $history   ?: null,
        'allergies'       => $allergies ?: null,
        'account_active'  => $active,
    ];
    foreach ($fields as $key => $label) {
        $oldVal = (string)($before[$key] ?? '');
        $newVal = (string)($newValues[$key] ?? '');
        if ($oldVal !== $newVal) {
            $changes[$label] = ['before' => $before[$key], 'after' => $newValues[$key]];
        }
    }

    $user = currentUser();
    $db->prepare("
        INSERT INTO AUDIT_LOG (system_user_id, action_type, target_table, target_id, details, timestamp)
        VALUES (?, 'customer_updated', 'CUSTOMER', ?, ?, NOW())
    ")->execute([$user['id'], $id, empty($changes) ? null : json_encode($changes)]);

    echo json_encode(['success' => true, 'message' => "Customer '{$name}' updated successfully."]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
