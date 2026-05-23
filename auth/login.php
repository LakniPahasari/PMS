<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';

// Already logged in → go to dashboard
if (!empty($_SESSION['user_id'])) {
    header('Location: /pages/dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($email && $password) {
        $db   = getDB();
        $stmt = $db->prepare("SELECT * FROM SYSTEM_USER WHERE email = ? AND is_active = 1 LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            // Refresh session
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['system_user_id'];
            $_SESSION['name']    = $user['name'];
            $_SESSION['role']    = $user['role'];
            $_SESSION['branch']  = $user['branch'];

            // Update last_login
            $db->prepare("UPDATE SYSTEM_USER SET last_login = NOW() WHERE system_user_id = ?")
               ->execute([$user['system_user_id']]);

            // Audit log
            $db->prepare("INSERT INTO AUDIT_LOG (system_user_id, action_type, target_table, target_id, timestamp)
                          VALUES (?, 'login', 'SYSTEM_USER', ?, NOW())")
               ->execute([$user['system_user_id'], $user['system_user_id']]);

            header('Location: /pages/dashboard.php');
            exit;
        } else {
            $error = 'Invalid email or password.';
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
    <title>Login — PharmaTrack</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<div class="login-page">
    <div class="login-box">
        <div class="login-logo">
            <div class="logo-icon">⚕</div>
            <h2>PharmaTrack</h2>
            <p>Pharmacy Management System</p>
        </div>

        <?php if ($error): ?>
            <div class="login-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="/auth/login.php">
            <div class="form-group">
                <label class="form-label" for="email">Email Address</label>
                <input type="email" id="email" name="email" class="form-control"
                       placeholder="you@pharmacy.com"
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                       required autofocus>
            </div>
            <div class="form-group">
                <label class="form-label" for="password">Password</label>
                <input type="password" id="password" name="password" class="form-control"
                       placeholder="••••••••" required>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;margin-top:8px;">
                Sign In
            </button>
        </form>
    </div>
</div>
</body>
</html>
