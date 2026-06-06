<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/config/session.php';

if (!empty($_SESSION['customer_id'])) {
    header('Location: /customer/dashboard.php');
    exit;
}

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name']             ?? '');
    $email    = trim($_POST['email']            ?? '');
    $dob      = trim($_POST['date_of_birth']    ?? '');
    $address  = trim($_POST['address']          ?? '');
    $password = $_POST['password']              ?? '';
    $confirm  = $_POST['password_confirm']      ?? '';

    if (!$name || !$email || !$dob || !$address || !$password) {
        $error = 'All fields are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (!DateTime::createFromFormat('Y-m-d', $dob)) {
        $error = 'Please enter a valid date of birth.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $db   = getDB();

        // Check if email already exists in CUSTOMER table
        $existing = $db->prepare("SELECT customer_id, name, password_hash, account_active FROM CUSTOMER WHERE email = ? LIMIT 1");
        $existing->execute([$email]);
        $found = $existing->fetch();

        if ($found) {
            if (!$found['account_active']) {
                $error = 'This account has been deactivated. Please contact the pharmacy.';
            } elseif ($found['password_hash']) {
                $error = 'An account with this email already exists. <a href="/customer/login.php" style="color:#991b1b;">Sign in instead</a>.';
            } else {
                // Staff-registered customer claiming their account
                $db->prepare("
                    UPDATE CUSTOMER
                    SET password_hash = ?, registered_at = NOW()
                    WHERE customer_id = ?
                ")->execute([password_hash($password, PASSWORD_DEFAULT), $found['customer_id']]);

                session_regenerate_id(true);
                $_SESSION['customer_id']    = $found['customer_id'];
                $_SESSION['customer_name']  = $found['name'];
                $_SESSION['customer_email'] = $email;
                header('Location: /customer/dashboard.php');
                exit;
            }
        } else {
            // Brand-new customer — create full record
            $db->prepare("
                INSERT INTO CUSTOMER
                    (name, email, date_of_birth, address, password_hash, registered_at, account_active)
                VALUES (?, ?, ?, ?, ?, NOW(), 1)
            ")->execute([$name, $email, $dob, $address, password_hash($password, PASSWORD_DEFAULT)]);

            $newId = $db->lastInsertId();
            session_regenerate_id(true);
            $_SESSION['customer_id']    = $newId;
            $_SESSION['customer_name']  = $name;
            $_SESSION['customer_email'] = $email;
            header('Location: /customer/dashboard.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register — Drugs 4U Patient Portal</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
               background: #f1f5f9; color: #1e293b; font-size: 14px; min-height: 100vh;
               display: flex; flex-direction: column; }
        .portal-header {
            background: #fff; border-bottom: 1px solid #e2e8f0;
            padding: 14px 24px; display: flex; align-items: center; gap: 10px;
        }
        .portal-brand { font-size: 20px; font-weight: 800; color: #2563eb; }
        .portal-sub   { font-size: 12px; color: #64748b; margin-left: 4px; }
        .portal-body  { flex: 1; display: flex; align-items: center; justify-content: center; padding: 40px 16px; }
        .auth-card {
            background: #fff; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,.08);
            width: 100%; max-width: 480px; padding: 36px 32px;
        }
        .auth-title { font-size: 22px; font-weight: 700; margin-bottom: 6px; }
        .auth-sub   { font-size: 13px; color: #64748b; margin-bottom: 24px; }
        .form-row   { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
        .form-group { margin-bottom: 16px; }
        .form-label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; }
        .required   { color: #ef4444; }
        .form-control {
            width: 100%; padding: 10px 12px; border: 1px solid #e2e8f0;
            border-radius: 8px; font-size: 14px; outline: none; transition: border-color .15s;
        }
        .form-control:focus { border-color: #2563eb; }
        .btn-primary {
            width: 100%; padding: 11px; background: #2563eb; color: #fff;
            border: none; border-radius: 8px; font-size: 14px; font-weight: 600;
            cursor: pointer; transition: background .15s; margin-top: 4px;
        }
        .btn-primary:hover { background: #1d4ed8; }
        .error-box {
            background: #fee2e2; color: #991b1b; border: 1px solid #ef4444;
            border-radius: 8px; padding: 10px 14px; font-size: 13px; margin-bottom: 16px;
        }
        .info-box {
            background: #eff6ff; color: #1e40af; border: 1px solid #bfdbfe;
            border-radius: 8px; padding: 10px 14px; font-size: 12.5px; margin-bottom: 16px;
        }
        .auth-footer { text-align: center; margin-top: 20px; font-size: 13px; color: #64748b; }
        .auth-footer a { color: #2563eb; text-decoration: none; font-weight: 600; }
        .portal-footer { text-align: center; padding: 20px; font-size: 12px; color: #94a3b8; }
        @media (max-width: 480px) { .form-row { grid-template-columns: 1fr; } }
    </style>
</head>
<body>

<header class="portal-header">
    <span style="font-size:22px;">⚕</span>
    <span class="portal-brand">Drugs 4U</span>
    <span class="portal-sub">Patient Portal</span>
</header>

<div class="portal-body">
    <div class="auth-card">
        <div class="auth-title">Create your account</div>
        <div class="auth-sub">Access your prescription history and records online</div>

        <div class="info-box">
            💡 Already registered at our pharmacy? Use the same email address to claim your existing record.
        </div>

        <?php if ($error): ?>
            <div class="error-box"><?= $error ?></div>
        <?php endif; ?>

        <form method="POST" action="/customer/register.php">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Full Name <span class="required">*</span></label>
                    <input type="text" name="name" class="form-control"
                           placeholder="Amal Perera"
                           value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Date of Birth <span class="required">*</span></label>
                    <input type="date" name="date_of_birth" class="form-control"
                           value="<?= htmlspecialchars($_POST['date_of_birth'] ?? '') ?>" required>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Email Address <span class="required">*</span></label>
                <input type="email" name="email" class="form-control"
                       placeholder="your@email.com"
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label class="form-label">Home Address <span class="required">*</span></label>
                <input type="text" name="address" class="form-control"
                       placeholder="123 High Street, Staffordshire"
                       value="<?= htmlspecialchars($_POST['address'] ?? '') ?>" required>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Password <span class="required">*</span></label>
                    <input type="password" name="password" class="form-control"
                           placeholder="Min. 8 characters" required minlength="8">
                </div>
                <div class="form-group">
                    <label class="form-label">Confirm Password <span class="required">*</span></label>
                    <input type="password" name="password_confirm" class="form-control"
                           placeholder="Repeat password" required>
                </div>
            </div>
            <button type="submit" class="btn-primary">Create Account</button>
        </form>

        <div class="auth-footer">
            Already have an account? <a href="/customer/login.php">Sign in</a>
        </div>
    </div>
</div>

<footer class="portal-footer">
    &copy; <?= date('Y') ?> Drugs 4U, Staffordshire &bull; Powered by PharmaTrack
</footer>

</body>
</html>
