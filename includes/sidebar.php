<?php
$user = currentUser();
$role = $user['role'];

// Current page for active link highlighting
$current = basename($_SERVER['PHP_SELF']);

$nav = [
    'all' => [
        ['href' => '/pages/dashboard.php',    'icon' => '🏠', 'label' => 'Dashboard'],
    ],
    'admin' => [
        ['href' => '/pages/customers.php',    'icon' => '👤', 'label' => 'Customers'],
        ['href' => '/pages/prescriptions.php','icon' => '📋', 'label' => 'Prescriptions'],
        ['href' => '/pages/stock.php',        'icon' => '💊', 'label' => 'Medicine Stock'],
        ['href' => '/pages/payments.php',     'icon' => '💳', 'label' => 'Payments'],
        ['href' => '/pages/users.php',        'icon' => '👥', 'label' => 'System Users'],
        ['href' => '/pages/audit.php',        'icon' => '📁', 'label' => 'Audit Log'],
        ['href' => '/pages/alerts.php',       'icon' => '🔔', 'label' => 'Alerts'],
    ],
    'pharmacist' => [
        ['href' => '/pages/customers.php',    'icon' => '👤', 'label' => 'Customers'],
        ['href' => '/pages/prescriptions.php','icon' => '📋', 'label' => 'Prescriptions'],
        ['href' => '/pages/stock.php',        'icon' => '💊', 'label' => 'Medicine Stock'],
        ['href' => '/pages/alerts.php',       'icon' => '🔔', 'label' => 'Alerts'],
    ],
    'store_manager' => [
        ['href' => '/pages/stock.php',        'icon' => '💊', 'label' => 'Medicine Stock'],
        ['href' => '/pages/payments.php',     'icon' => '💳', 'label' => 'Payments'],
        ['href' => '/pages/alerts.php',       'icon' => '🔔', 'label' => 'Alerts'],
    ],
];

$links = array_merge($nav['all'], $nav[$role] ?? []);
?>

<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <span class="brand-icon">⚕</span>
        <span class="brand-name">PharmaTrack</span>
    </div>

    <div class="sidebar-user">
        <div class="user-avatar"><?= strtoupper(substr($user['name'], 0, 1)) ?></div>
        <div class="user-info">
            <div class="user-name"><?= htmlspecialchars($user['name']) ?></div>
            <div class="user-role"><?= ucfirst(str_replace('_', ' ', $role)) ?></div>
        </div>
    </div>

    <nav class="sidebar-nav">
        <?php foreach ($links as $link): ?>
            <a href="<?= $link['href'] ?>"
               class="nav-link <?= ($current === basename($link['href'])) ? 'active' : '' ?>">
                <span class="nav-icon"><?= $link['icon'] ?></span>
                <span class="nav-label"><?= $link['label'] ?></span>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="sidebar-footer">
        <a href="/auth/logout.php" class="nav-link logout-link">
            <span class="nav-icon">🚪</span>
            <span class="nav-label">Logout</span>
        </a>
        <div class="branch-tag"><?= htmlspecialchars($user['branch']) ?></div>
    </div>
</aside>
