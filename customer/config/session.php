<?php
if (session_status() === PHP_SESSION_NONE) session_start();

function requireCustomerLogin(): void {
    if (empty($_SESSION['customer_id'])) {
        header('Location: /customer/login.php');
        exit;
    }
}

function currentCustomer(): array {
    return [
        'id'    => $_SESSION['customer_id']    ?? null,
        'name'  => $_SESSION['customer_name']  ?? '',
        'email' => $_SESSION['customer_email'] ?? '',
    ];
}
