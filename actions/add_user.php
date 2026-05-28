<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';
requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

$name     = trim($_POST['name']     ?? '');
$email    = trim($_POST['email']    ?? '');
$role     = trim($_POST['role']     ?? '');
$branch   = trim($_POST['branch']   ?? '');
$password = $_POST['password']      ?? '';

$allowedRoles = ['admin', 'pharmacist', 'store_manager'];

if ($name === '') {
    echo json_encode(['success' => false, 'message' => 'Full name is required.']);
    exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Please enter a valid email address.']);
    exit;
}
if (!in_array($role, $allowedRoles)) {
    echo json_encode(['success' => false, 'message' => 'Please select a valid role.']);
    exit;
}
if ($branch === '') {
    echo json_encode(['success' => false, 'message' => 'Please select a branch.']);
    exit;
}
if (strlen($password) < 8) {
    echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters.']);
    exit;
}

try {
    $db = getDB();

    $dup = $db->prepare("SELECT system_user_id FROM SYSTEM_USER WHERE email = ? LIMIT 1");
    $dup->execute([$email]);
    if ($dup->fetch()) {
        echo json_encode(['success' => false, 'message' => 'A user with this email already exists.']);
        exit;
    }

    $db->prepare("
        INSERT INTO SYSTEM_USER (name, email, password_hash, role, branch, is_active)
        VALUES (?, ?, ?, ?, ?, 1)
    ")->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), $role, $branch]);

    $newId = $db->lastInsertId();
    $actor = currentUser();

    $db->prepare("
        INSERT INTO AUDIT_LOG (system_user_id, action_type, target_table, target_id, details, timestamp)
        VALUES (?, 'user_created', 'SYSTEM_USER', ?, ?, NOW())
    ")->execute([$actor['id'], $newId, json_encode(['name' => $name, 'role' => $role])]);

    echo json_encode(['success' => true, 'message' => "User '{$name}' created successfully."]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
