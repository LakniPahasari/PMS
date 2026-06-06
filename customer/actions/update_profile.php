<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../config/session.php';
requireCustomerLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']); exit;
}

$customer = currentCustomer();
$section  = trim($_POST['section'] ?? '');

try {
    $db = getDB();

    // Verify account is still active
    $check = $db->prepare("SELECT customer_id FROM CUSTOMER WHERE customer_id = ? AND account_active = 1 LIMIT 1");
    $check->execute([$customer['id']]);
    if (!$check->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Account not found or inactive.']); exit;
    }

    // ── Contact ─────────────────────────────────────────────
    if ($section === 'contact') {
        $email   = trim($_POST['email']   ?? '');
        $address = trim($_POST['address'] ?? '');

        if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => 'Please enter a valid email address.']); exit;
        }
        if (!$address) {
            echo json_encode(['success' => false, 'message' => 'Address is required.']); exit;
        }

        // Check email not taken by another customer
        if ($email) {
            $dup = $db->prepare("SELECT customer_id FROM CUSTOMER WHERE email = ? AND customer_id != ? LIMIT 1");
            $dup->execute([$email, $customer['id']]);
            if ($dup->fetch()) {
                echo json_encode(['success' => false, 'message' => 'This email is already registered to another account.']); exit;
            }
        }

        $db->prepare("
            UPDATE CUSTOMER SET email = ?, address = ?, updated_at = NOW()
            WHERE customer_id = ?
        ")->execute([$email ?: null, $address, $customer['id']]);

        echo json_encode(['success' => true, 'message' => 'Contact details updated.']);

    // ── Medical ──────────────────────────────────────────────
    } elseif ($section === 'medical') {
        $allowedPregnancy = ['not_applicable', 'no', 'yes', 'unknown'];
        $pregnancy = trim($_POST['pregnancy_status'] ?? 'not_applicable');
        if (!in_array($pregnancy, $allowedPregnancy)) $pregnancy = 'not_applicable';

        $db->prepare("
            UPDATE CUSTOMER SET
                allergies             = ?,
                medical_conditions    = ?,
                current_medications   = ?,
                drug_sensitivities    = ?,
                medical_history       = ?,
                primary_care_physician= ?,
                preferred_pharmacy    = ?,
                pregnancy_status      = ?,
                updated_at            = NOW()
            WHERE customer_id = ?
        ")->execute([
            trim($_POST['allergies']              ?? '') ?: null,
            trim($_POST['medical_conditions']     ?? '') ?: null,
            trim($_POST['current_medications']    ?? '') ?: null,
            trim($_POST['drug_sensitivities']     ?? '') ?: null,
            trim($_POST['medical_history']        ?? '') ?: null,
            trim($_POST['primary_care_physician'] ?? '') ?: null,
            trim($_POST['preferred_pharmacy']     ?? '') ?: null,
            $pregnancy,
            $customer['id'],
        ]);

        echo json_encode(['success' => true, 'message' => 'Medical information updated.']);

    // ── Communication (single-field toggle auto-save) ────────
    } elseif ($section === 'comm') {
        $allowed = ['comm_email_pickup','comm_sms','comm_email','comm_refill_reminders'];
        $field   = trim($_POST['field'] ?? '');
        $value   = (int)(bool)($_POST['value'] ?? 0);

        if (!in_array($field, $allowed)) {
            echo json_encode(['success' => false, 'message' => 'Invalid preference field.']); exit;
        }

        $db->prepare("UPDATE CUSTOMER SET {$field} = ?, updated_at = NOW() WHERE customer_id = ?")
           ->execute([$value, $customer['id']]);

        echo json_encode(['success' => true]);

    } else {
        echo json_encode(['success' => false, 'message' => 'Unknown section.']);
    }

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
