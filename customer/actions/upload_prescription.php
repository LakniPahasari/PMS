<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../config/session.php';
requireCustomerLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /customer/dashboard.php');
    exit;
}

$customer = currentCustomer();
$notes    = trim($_POST['notes'] ?? '');

$allowedMime = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
$maxSize     = 10 * 1024 * 1024; // 10 MB

function redirect(string $error): void {
    header('Location: /customer/dashboard.php?upload_error=' . urlencode($error));
    exit;
}

if (empty($_FILES['prescription_image']) || $_FILES['prescription_image']['error'] !== UPLOAD_ERR_OK) {
    redirect('No file uploaded or upload failed. Please try again.');
}

$file = $_FILES['prescription_image'];

if ($file['size'] > $maxSize) {
    redirect('File is too large. Maximum allowed size is 10 MB.');
}

// Validate MIME type via finfo (not just extension)
$finfo    = new finfo(FILEINFO_MIME_TYPE);
$mimeType = $finfo->file($file['tmp_name']);

if (!in_array($mimeType, $allowedMime)) {
    redirect('Invalid file type. Please upload a JPG, PNG, or PDF.');
}

$ext      = match($mimeType) {
    'image/jpeg'      => 'jpg',
    'image/png'       => 'png',
    'image/webp'      => 'webp',
    'application/pdf' => 'pdf',
    default           => 'bin',
};

$filename  = 'rx_' . $customer['id'] . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
$uploadDir = __DIR__ . '/../../uploads/prescriptions/';
$destPath  = $uploadDir . $filename;

if (!move_uploaded_file($file['tmp_name'], $destPath)) {
    redirect('Failed to save the file. Please try again.');
}

try {
    $db = getDB();
    $db->prepare("
        INSERT INTO PRESCRIPTION_REQUEST (customer_id, image_path, notes, status, created_at)
        VALUES (?, ?, ?, 'pending', NOW())
    ")->execute([$customer['id'], $filename, $notes ?: null]);

    header('Location: /customer/dashboard.php?upload_success=1');
    exit;

} catch (PDOException $e) {
    // Remove uploaded file if DB insert fails
    @unlink($destPath);
    redirect('Database error. Please try again.');
}
