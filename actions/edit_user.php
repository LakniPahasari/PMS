<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';
requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

$id       = (int)  ($_POST['user_id']  ?? 0);
$name     = trim(  $_POST['name']      ?? '');
$email    = trim(  $_POST['email']     ?? '');
$role     = trim(  $_POST['role']      ?? '');
$branch   = trim(  $_POST['branch']    ?? '');
$password = trim(  $_POST['password']  ?? '');
$active   = isset($_POST['is_active']) ? 1 : 0;

$allowedRoles = ['admin', 'pharmacist', 'store_manager'];

if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid user ID.']);
    exit;
}
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
if ($password !== '' && strlen($password) < 8) {
    echo json_encode(['success' => false, 'message' => 'New password must be at least 8 characters.']);
    exit;
}

try {
    $db = getDB();

    $cur = $db->prepare("SELECT name, role, is_active FROM SYSTEM_USER WHERE system_user_id = ? LIMIT 1");
    $cur->execute([$id]);
    $existing = $cur->fetch();
    if (!$existing) {
        echo json_encode(['success' => false, 'message' => 'User not found.']);
        exit;
    }

    // Duplicate email check (excluding self)
    $dup = $db->prepare("SELECT system_user_id FROM SYSTEM_USER WHERE email = ? AND system_user_id != ? LIMIT 1");
    $dup->execute([$email, $id]);
    if ($dup->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Another user with this email already exists.']);
        exit;
    }

    if ($password !== '') {
        $db->prepare("
            UPDATE SYSTEM_USER
            SET name = ?, email = ?, role = ?, branch = ?, is_active = ?, password_hash = ?
            WHERE system_user_id = ?
        ")->execute([$name, $email, $role, $branch, $active, password_hash($password, PASSWORD_DEFAULT), $id]);
    } else {
        $db->prepare("
            UPDATE SYSTEM_USER
            SET name = ?, email = ?, role = ?, branch = ?, is_active = ?
            WHERE system_user_id = ?
        ")->execute([$name, $email, $role, $branch, $active, $id]);
    }

    $actor   = currentUser();
    $changes = [];
    if ($existing['name']      !== $name)  $changes['name']      = ['before' => $existing['name'],      'after' => $name];
    if ($existing['role']      !== $role)  $changes['role']      = ['before' => $existing['role'],      'after' => $role];
    if ((int)$existing['is_active'] !== $active) $changes['is_active'] = ['before' => $existing['is_active'], 'after' => $active];

    $db->prepare("
        INSERT INTO AUDIT_LOG (system_user_id, action_type, target_table, target_id, details, timestamp)
        VALUES (?, 'user_updated', 'SYSTEM_USER', ?, ?, NOW())
    ")->execute([$actor['id'], $id, $changes ? json_encode($changes) : null]);

    echo json_encode(['success' => true, 'message' => "User '{$name}' updated successfully."]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
