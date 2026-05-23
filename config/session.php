<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function requireLogin() {
    if (empty($_SESSION['user_id'])) {
        header('Location: /auth/login.php');
        exit;
    }
}

function requireRole(...$roles) {
    requireLogin();
    if (!in_array($_SESSION['role'], $roles)) {
        header('Location: /pages/dashboard.php');
        exit;
    }
}

function currentUser() {
    return [
        'id'     => $_SESSION['user_id'] ?? null,
        'name'   => $_SESSION['name']    ?? '',
        'role'   => $_SESSION['role']    ?? '',
        'branch' => $_SESSION['branch']  ?? '',
    ];
}