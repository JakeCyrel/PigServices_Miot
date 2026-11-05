<?php

require_once __DIR__ . '/auth.php';

$admin = require_role('admin');
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

}

$flash = take_flash();
$users = $pdo->query("SELECT id, full_name, email, phone, address, role, is_verified, created_at FROM users WHERE role <> 'admin' ORDER BY created_at DESC")->fetchAll();
$totalUsers = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role <> 'admin'")->fetchColumn();
$pendingCount = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role <> 'admin' AND is_verified = 0")->fetchColumn();
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Administration | Pig Services</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<header class="shell topbar">
    <a class="brand" href="admin_dashboard.php"><span class="brand-mark">P</span> Pig Services</a>
    <nav class="nav"><span><?= e($admin['full_name']) ?> · Administrator</span><a class="button light small" href="logout.php">Sign out</a></nav>
</header>
<main class="shell dashboard-page">
    <div class="page-heading">
        <div><span class="eyebrow">Administration center</span><h1>User management dashboard.</h1><p class="hero-copy">Review registrations, verify accounts, and keep user access organized.</p></div>
        <span class="role-badge">Administrator</span>
    </div>
    <?php if ($flash): ?><div class="alert <?= e($flash['type']) ?>"><?= e($flash['message']) ?></div><?php endif; ?>
    <?php foreach ($errors as $error): ?><div class="alert error"><?= e($error) ?></div><?php endforeach; ?>
    <div class="stats">
        <div class="stat"><small>Registered users</small><strong><?= $totalUsers ?></strong></div>
        <div class="stat"><small>Awaiting approval</small><strong><?= $pendingCount ?></strong></div>
        <div class="stat"><small>Access policy</small><strong>Approval required</strong></div>
    </div>
    <section class="panel admin-section">
        <div class="section-heading"><div><span class="eyebrow">User directory</span><h2>Registered accounts</h2></div><span class="tag"><?= $totalUsers ?> users</span></div>
        <?php if ($users): ?>
            <div class="table-wrap"><table><thead><tr><th>Name</th><th>Email</th><th>Phone</th><th>Address</th><th>Account type</th><th>Status</th></tr></thead><tbody><?php foreach ($users as $account): ?><tr><td><?= e($account['full_name']) ?></td><td><?= e($account['email']) ?></td><td><?= e($account['phone']) ?></td><td><?= e($account['address']) ?></td><td><?= e(str_replace('_', ' ', $account['role'])) ?></td><td><span class="status <?= (int) $account['is_verified'] === 1 ? 'approved' : 'pending' ?>"><?= (int) $account['is_verified'] === 1 ? 'Approved' : 'Pending' ?></span></td></tr><?php endforeach; ?></tbody></table></div>
        <?php else: ?><p class="empty-state">No user accounts have been created yet.</p><?php endif; ?>
    </section>
    <section class="panel admin-section admin-summary"><div><span class="eyebrow">Access control</span><h2>New registrations require approval.</h2><p>Users submit an account request, and the administrator reviews and approves it before the account can sign in.</p></div></section>
</body>
</html>
