<?php

require_once __DIR__ . '/auth.php';

$message = '';
$adminExists = (bool) $pdo->query("SELECT 1 FROM users WHERE role = 'admin' LIMIT 1")->fetchColumn();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$adminExists) {
    $email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
    $password = (string) ($_POST['password'] ?? '');

    if (!$email) {
        $message = 'Enter a valid email address.';
    } elseif (strlen($password) < 12) {
        $message = 'Choose a password with at least 12 characters.';
    } else {
        $exists = $pdo->prepare('SELECT 1 FROM users WHERE email = ?');
        $exists->execute([$email]);

        if ($exists->fetchColumn()) {
            $message = 'That email address is already in use.';
        } else {
            $statement = $pdo->prepare('INSERT INTO users (full_name, email, phone, address, password_hash, role, is_verified) VALUES (?, ?, ?, ?, ?, ?, 1)');
            $statement->execute(['System Administrator', $email, '00000000000', 'Bindoy, Negros Oriental', password_hash($password, PASSWORD_DEFAULT), 'admin']);
            $adminExists = true;
            $message = 'Administrator created. Delete setup_admin.php now, then sign in.';
        }
    }
} elseif ($adminExists) {
    $message = 'An administrator already exists. Delete setup_admin.php.';
}
?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Admin Setup | Pig Services</title><link rel="stylesheet" href="assets/style.css"></head><body class="center-page"><main class="setup-card"><span class="eyebrow">Pig Services setup</span><h1>Administrator setup</h1><?php if ($message !== ''): ?><p role="status"><?= e($message) ?></p><?php endif; ?><?php if (!$adminExists): ?><form method="post"><label for="email">Administrator email</label><input id="email" name="email" type="email" autocomplete="email" required><label for="password">Password (at least 12 characters)</label><input id="password" name="password" type="password" autocomplete="new-password" minlength="12" required><button class="button primary" type="submit">Create administrator</button></form><?php endif; ?><p>Delete <code>setup_admin.php</code> as soon as setup is complete.</p><?php if ($adminExists): ?><a class="button primary" href="index.php?mode=login#access">Go to sign in</a><?php endif; ?></main><?php require __DIR__ . '/footer.php'; ?></body></html>
