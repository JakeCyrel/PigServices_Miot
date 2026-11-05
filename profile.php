<?php

require_once __DIR__ . '/auth.php';

$user = require_login();
if ($user['role'] === 'admin') {
    redirect('admin_dashboard.php');
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim((string) ($_POST['full_name'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $phone = trim((string) ($_POST['phone'] ?? ''));
    $address = trim((string) ($_POST['address'] ?? ''));
    $currentPassword = (string) ($_POST['current_password'] ?? '');
    $newPassword = (string) ($_POST['new_password'] ?? '');

    if ($fullName === '' || $email === '' || $phone === '' || $address === '') {
        $errors[] = 'Please complete all profile fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email address.';
    } elseif ($newPassword !== '' && $currentPassword === '') {
        $errors[] = 'Enter your current password before choosing a new password.';
    } elseif ($newPassword !== '' && strlen($newPassword) < 8) {
        $errors[] = 'The new password must be at least 8 characters.';
    } else {
        $emailCheck = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1');
        $emailCheck->execute([$email, $user['id']]);
        if ($emailCheck->fetch()) {
            $errors[] = 'That email address is already in use.';
        } else {
            if ($newPassword !== '') {
                $passwordCheck = $pdo->prepare('SELECT password_hash FROM users WHERE id = ? LIMIT 1');
                $passwordCheck->execute([$user['id']]);
                $account = $passwordCheck->fetch();
                if (!$account || !password_verify($currentPassword, $account['password_hash'])) {
                    $errors[] = 'Your current password is incorrect.';
                } else {
                    $statement = $pdo->prepare('UPDATE users SET full_name = ?, email = ?, phone = ?, address = ?, password_hash = ? WHERE id = ?');
                    $statement->execute([$fullName, $email, $phone, $address, password_hash($newPassword, PASSWORD_DEFAULT), $user['id']]);
                }
            } else {
                $statement = $pdo->prepare('UPDATE users SET full_name = ?, email = ?, phone = ?, address = ? WHERE id = ?');
                $statement->execute([$fullName, $email, $phone, $address, $user['id']]);
            }
            if (!$errors) {
                $_SESSION['user'] = array_merge($user, ['full_name' => $fullName, 'email' => $email, 'phone' => $phone, 'address' => $address]);
                flash('success', 'Your profile was updated successfully.');
                redirect('profile.php');
            }
        }
    }
}
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>My profile | Pig Services</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<header class="shell topbar">
    <a class="brand" href="dashboard.php"><span class="brand-mark">P</span> Pig Services</a>
    <nav class="nav"><a class="button light small" href="dashboard.php">Dashboard</a><a class="button light small" href="messages.php">Messages</a><a class="button light small" href="logout.php">Sign out</a></nav>
</header>
<main class="shell profile-page">
    <div class="page-heading"><div><span class="eyebrow">Account settings</span><h1>My profile</h1><p class="hero-copy">Keep your contact information current so other owners can connect with you confidently.</p></div><span class="role-badge"><?= e(str_replace('_', ' ', $user['role'])) ?></span></div>
    <?php if ($flash = take_flash()): ?><div class="alert success"><?= e($flash['message']) ?></div><?php endif; ?>
    <?php foreach ($errors as $error): ?><div class="alert error"><?= e($error) ?></div><?php endforeach; ?>
    <section class="panel profile-card">
        <div class="profile-heading"><div class="profile-avatar"><?= e(strtoupper(substr($user['full_name'], 0, 1))) ?></div><div><h2>Personal details</h2><p>These details are connected to your Pig Services account.</p></div></div>
        <form class="profile-form" method="post"><div class="profile-fields"><label>Full name<input name="full_name" value="<?= e($user['full_name']) ?>" required autocomplete="name"></label><label>Email address<input type="email" name="email" value="<?= e($user['email']) ?>" required autocomplete="email"></label><label>Phone number<input name="phone" value="<?= e($user['phone']) ?>" required autocomplete="tel"></label><label>Address<input name="address" value="<?= e($user['address']) ?>" required autocomplete="street-address"></label></div><div class="profile-divider"><span>Password</span><small>Enter your current password to set a new one.</small></div><div class="profile-fields"><label>Current password<div class="password-control"><input id="current-password" type="password" name="current_password" autocomplete="current-password"><button class="password-toggle" type="button" data-target="current-password" aria-label="Show password" title="Show password">&#128065;</button></div></label><label>New password<div class="password-control"><input id="new-password" type="password" name="new_password" minlength="8" autocomplete="new-password" placeholder="Optional"><button class="password-toggle" type="button" data-target="new-password" aria-label="Show password" title="Show password">&#128065;</button></div></label></div><div class="profile-actions"><a class="button light" href="dashboard.php">Cancel</a><button class="button primary" type="submit">Save changes</button></div></form>
    </section>
    </main><?php require __DIR__ . '/footer.php'; ?>
<script src="assets/app.js"></script>
</body>
</html>
