<?php

require_once __DIR__ . '/auth.php';

$user = require_login();
if ($user['role'] === 'admin') {
    redirect('admin_dashboard.php');
}

$requestId = (int) ($_GET['request_id'] ?? $_POST['request_id'] ?? 0);
if ($requestId === 0) {
    $threadStatement = $pdo->prepare('SELECT requests.id, requests.reference_no, requests.status, services.pig_name, other_owner.full_name AS other_name, MAX(messages.created_at) AS last_message_at, SUM(CASE WHEN messages.recipient_id = ? AND messages.read_at IS NULL THEN 1 ELSE 0 END) AS unread_count FROM requests JOIN services ON services.id = requests.service_id JOIN users AS female_owner ON female_owner.id = requests.female_owner_id JOIN users AS other_owner ON other_owner.id = CASE WHEN requests.female_owner_id = ? THEN services.owner_id ELSE requests.female_owner_id END LEFT JOIN messages ON messages.request_id = requests.id WHERE requests.female_owner_id = ? OR services.owner_id = ? GROUP BY requests.id, requests.reference_no, requests.status, services.pig_name, other_owner.full_name ORDER BY last_message_at DESC, requests.created_at DESC');
    $threadStatement->execute([$user['id'], $user['id'], $user['id'], $user['id']]);
    $threads = $threadStatement->fetchAll();
    ?><!doctype html>
    <html lang="en">
    <head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Messages | Pig Services</title><link rel="stylesheet" href="assets/style.css"></head>
    <body>
    <header class="shell topbar"><a class="brand" href="dashboard.php"><span class="brand-mark">P</span> Pig Services</a><nav class="nav"><a class="button light small" href="dashboard.php">Dashboard</a><a class="button light small" href="profile.php">My profile</a></nav></header>
    <main class="shell messages-page"><div class="page-heading"><div><span class="eyebrow">Inbox</span><h1>Your conversations</h1><p class="hero-copy">Private messages connected to your service requests.</p></div></div><section class="panel inbox-card"><div class="section-heading"><div><span class="eyebrow">Transactions</span><h2>Message threads</h2></div></div><?php if ($threads): ?><div class="thread-list"><?php foreach ($threads as $thread): ?><a class="thread-row" href="messages.php?request_id=<?= (int) $thread['id'] ?>"><div class="thread-avatar"><?= e(strtoupper(substr($thread['other_name'], 0, 1))) ?></div><div class="thread-content"><strong><?= e($thread['other_name']) ?></strong><span><?= e($thread['pig_name']) ?> · <?= e($thread['reference_no']) ?></span></div><div class="thread-meta"><span class="status <?= strtolower($thread['status']) ?>"><?= e($thread['status']) ?></span><?php if ((int) $thread['unread_count'] > 0): ?><b class="notification-count"><?= (int) $thread['unread_count'] ?></b><?php endif; ?><?php if ($thread['last_message_at']): ?><time><?= e(date('M j, g:i A', strtotime($thread['last_message_at']))) ?></time><?php endif; ?></div></a><?php endforeach; ?></div><?php else: ?><p class="empty-state">You do not have any service conversations yet. Open a service request to start one.</p><?php endif; ?></section></main><?php require __DIR__ . '/footer.php'; ?>
    </body>
    </html><?php
    exit;
}
$requestStatement = $pdo->prepare('SELECT requests.id, requests.reference_no, requests.status, requests.preferred_date, services.pig_name, services.breed, users.full_name AS female_name, users.id AS female_id, male.full_name AS male_name, male.id AS male_id FROM requests JOIN services ON services.id = requests.service_id JOIN users ON users.id = requests.female_owner_id JOIN users AS male ON male.id = services.owner_id WHERE requests.id = ? AND (requests.female_owner_id = ? OR services.owner_id = ?) LIMIT 1');
$requestStatement->execute([$requestId, $user['id'], $user['id']]);
$request = $requestStatement->fetch();
if (!$request) {
    http_response_code(404);
    exit('Service request not found.');
}

$recipientId = (int) $request['female_id'] === (int) $user['id'] ? (int) $request['male_id'] : (int) $request['female_id'];
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $message = trim((string) ($_POST['message'] ?? ''));
    if ($message === '') {
        $errors[] = 'Write a message before sending.';
    } elseif (strlen($message) > 2000) {
        $errors[] = 'Messages must be 2,000 characters or fewer.';
    } else {
        $statement = $pdo->prepare('INSERT INTO messages (request_id, sender_id, recipient_id, message) VALUES (?, ?, ?, ?)');
        $statement->execute([$requestId, $user['id'], $recipientId, $message]);
        redirect('messages.php?request_id=' . $requestId);
    }
}

$readStatement = $pdo->prepare('UPDATE messages SET read_at = CURRENT_TIMESTAMP WHERE request_id = ? AND recipient_id = ? AND read_at IS NULL');
$readStatement->execute([$requestId, $user['id']]);
$messageStatement = $pdo->prepare('SELECT messages.*, users.full_name AS sender_name FROM messages JOIN users ON users.id = messages.sender_id WHERE messages.request_id = ? ORDER BY messages.created_at ASC, messages.id ASC');
$messageStatement->execute([$requestId]);
$messages = $messageStatement->fetchAll();
$otherName = (int) $request['female_id'] === (int) $user['id'] ? $request['male_name'] : $request['female_name'];
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Messages | Pig Services</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<header class="shell topbar">
    <a class="brand" href="dashboard.php"><span class="brand-mark">P</span> Pig Services</a>
    <nav class="nav"><a class="button light small" href="dashboard.php">Dashboard</a><a class="button light small" href="profile.php">My profile</a></nav>
</header>
<main class="shell messages-page">
    <div class="page-heading"><div><span class="eyebrow">Transaction conversation</span><h1>Messages with <?= e($otherName) ?></h1><p class="hero-copy">Keep questions, scheduling details, and updates together with this service request.</p></div><span class="status <?= strtolower($request['status']) ?>"><?= e($request['status']) ?></span></div>
    <section class="panel conversation-card">
        <div class="conversation-meta"><div><strong><?= e($request['pig_name']) ?></strong><span><?= e($request['breed']) ?> · Request <?= e($request['reference_no']) ?></span></div><time datetime="<?= e($request['preferred_date']) ?>">Preferred date: <?= e(date('M j, Y', strtotime($request['preferred_date']))) ?></time></div>
        <?php if ($errors): ?><div class="alert error"><?php foreach ($errors as $error): ?><?= e($error) ?><?php endforeach; ?></div><?php endif; ?>
        <div class="message-list"><?php if ($messages): ?><?php foreach ($messages as $item): ?><article class="message-bubble <?= (int) $item['sender_id'] === (int) $user['id'] ? 'mine' : 'theirs' ?>"><div class="message-author"><?= e((int) $item['sender_id'] === (int) $user['id'] ? 'You' : $item['sender_name']) ?></div><p><?= nl2br(e($item['message'])) ?></p><div class="message-footer"><time datetime="<?= e($item['created_at']) ?>"><?= e(date('M j, Y · g:i A', strtotime($item['created_at']))) ?></time><button class="reply-button" type="button" data-reply-author="<?= e($item['sender_name']) ?>" data-reply-text="<?= e($item['message']) ?>">Reply</button></div></article><?php endforeach; ?><?php else: ?><p class="empty-state">No messages yet. Start the conversation with <?= e($otherName) ?>.</p><?php endif; ?></div>
        <form class="message-composer" method="post"><input type="hidden" name="request_id" value="<?= (int) $request['id'] ?>"><label for="message">Write a message</label><div id="reply-context" class="reply-context" hidden></div><textarea id="message" name="message" rows="3" maxlength="2000" placeholder="Ask a question or share an update..." required></textarea><div class="composer-footer"><small>Messages are visible only to the owners involved in this request.</small><button class="button primary" type="submit">Send message</button></div></form>
    </section>
    </main><?php require __DIR__ . '/footer.php'; ?>
<script src="assets/app.js"></script>
</body>
</html>
