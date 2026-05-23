<?php
/**
 * ONE-TIME SETUP SCRIPT
 * Visit this page once to set a real password for the test user.
 * DELETE this file after use.
 */
require_once __DIR__ . '/config/db.php';

$testPassword = 'Admin@123';
$hash = password_hash($testPassword, PASSWORD_BCRYPT);

$db = getDB();
$db->prepare("UPDATE SYSTEM_USER SET password_hash = ? WHERE email = 'sarah.johnson@pharmacy.com'")
   ->execute([$hash]);

echo "<h2>Setup complete!</h2>";
echo "<p>Login credentials:</p>";
echo "<ul>";
echo "<li><strong>Email:</strong> sarah.johnson@pharmacy.com</li>";
echo "<li><strong>Password:</strong> {$testPassword}</li>";
echo "</ul>";
echo "<p style='color:red;'><strong>Delete this file now!</strong></p>";
echo "<a href='/auth/login.php'>Go to Login</a>";
