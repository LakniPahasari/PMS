<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/config/session.php';

if (!empty($_SESSION['customer_id'])) {
    header('Location: /customer/dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($email && $password) {
        $db   = getDB();
        $stmt = $db->prepare("SELECT customer_id, name, email, password_hash, account_active FROM CUSTOMER WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $customer = $stmt->fetch();

        if (!$customer || !$customer['password_hash']) {
            $error = 'No account found for this email. Please register first.';
        } elseif (!$customer['account_active']) {
            $error = 'Your account has been deactivated. Please contact the pharmacy.';
        } elseif (!password_verify($password, $customer['password_hash'])) {
            $error = 'Incorrect password. Please try again.';
        } else {
            session_regenerate_id(true);
            $_SESSION['customer_id']    = $customer['customer_id'];
            $_SESSION['customer_name']  = $customer['name'];
            $_SESSION['customer_email'] = $customer['email'];
            header('Location: /customer/dashboard.php');
            exit;
        }
    } else {
        $error = 'Please enter your email and password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Login — Drugs 4U</title>
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
            width: 100%; max-width: 400px; padding: 36px 32px;
        }
        .auth-title { font-size: 22px; font-weight: 700; margin-bottom: 6px; }
        .auth-sub   { font-size: 13px; color: #64748b; margin-bottom: 24px; }

        .form-group { margin-bottom: 16px; }
        .form-label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; }
        .form-control {
            width: 100%; padding: 10px 12px; border: 1px solid #e2e8f0;
            border-radius: 8px; font-size: 14px; outline: none;
            transition: border-color .15s;
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
        .auth-footer { text-align: center; margin-top: 20px; font-size: 13px; color: #64748b; }
        .auth-footer a { color: #2563eb; text-decoration: none; font-weight: 600; }
        .auth-footer a:hover { text-decoration: underline; }

        .portal-footer { text-align: center; padding: 20px; font-size: 12px; color: #94a3b8; }
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
        <div class="auth-title">Welcome back</div>
        <div class="auth-sub">Sign in to view your prescriptions and records</div>

        <?php if ($error): ?>
            <div class="error-box"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="/customer/login.php">
            <div class="form-group">
                <label class="form-label" for="email">Email Address</label>
                <input type="email" id="email" name="email" class="form-control"
                       placeholder="your@email.com"
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                       required autofocus>
            </div>
            <div class="form-group">
                <label class="form-label" for="password">Password</label>
                <input type="password" id="password" name="password" class="form-control"
                       placeholder="••••••••" required>
            </div>
            <button type="submit" class="btn-primary">Sign In</button>
        </form>

        <div class="auth-footer">
            Don't have an account?
            <a href="/customer/register.php">Register here</a>
        </div>
    </div>
</div>

<footer class="portal-footer">
    &copy; <?= date('Y') ?> Drugs 4U, Staffordshire &bull; Powered by PharmaTrack
</footer>

</body>
</html>
