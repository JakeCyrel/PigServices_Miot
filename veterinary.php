<?php

require_once __DIR__ . '/auth.php';

$user = require_login();
if ($user['role'] === 'admin') {
    redirect('admin_dashboard.php');
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user['role'] === 'veterinarian' && ($_POST['action'] ?? '') === 'add_veterinary_service') {
    $name = trim((string) ($_POST['pig_name'] ?? ''));
    $specialty = trim((string) ($_POST['breed'] ?? ''));
    $location = trim((string) ($_POST['location'] ?? ''));
    $description = trim((string) ($_POST['description'] ?? ''));
    if ($name === '' || $specialty === '' || $location === '' || $description === '') {
        $errors[] = 'Complete all veterinary service fields.';
    } else {
        $statement = $pdo->prepare("INSERT INTO services (owner_id, service_type, pig_name, breed, age_months, location, description) VALUES (?, 'veterinary', ?, ?, 0, ?, ?)");
        $statement->execute([$user['id'], $name, $specialty, $location, $description]);
        flash('success', 'Your veterinary service is now available.');
        redirect('veterinary.php');
    }
}

$services = $pdo->query("SELECT services.*, users.full_name AS owner_name, users.phone AS owner_phone FROM services JOIN users ON users.id = services.owner_id WHERE services.is_active = 1 AND services.service_type = 'veterinary' ORDER BY services.created_at DESC")->fetchAll();
$incomingRequests = [];
if ($user['role'] === 'veterinarian') {
    $statement = $pdo->prepare('SELECT requests.*, services.pig_name, users.full_name AS owner_name, users.phone AS owner_phone, users.address AS owner_address FROM requests JOIN services ON services.id = requests.service_id JOIN users ON users.id = requests.female_owner_id WHERE services.owner_id = ? ORDER BY requests.created_at DESC');
    $statement->execute([$user['id']]);
    $incomingRequests = $statement->fetchAll();
}
$unreadMessages = 0;
try {
    $statement = $pdo->prepare('SELECT COUNT(*) FROM messages WHERE recipient_id = ? AND read_at IS NULL');
    $statement->execute([$user['id']]);
    $unreadMessages = (int) $statement->fetchColumn();
} catch (PDOException $exception) {
    $unreadMessages = 0;
}
?><!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Veterinary Services | Pig Services</title><link rel="stylesheet" href="assets/style.css"></head>
<body>
<header class="shell topbar"><a class="brand" href="veterinary.php"><span class="brand-mark">P</span> Pig Services</a><nav class="nav"><a class="button light small" href="<?= $user['role'] === 'veterinarian' ? 'veterinary.php' : 'dashboard.php' ?>"><?= $user['role'] === 'veterinarian' ? 'Veterinary dashboard' : 'Breeding services' ?></a><a class="button light small" href="messages.php">Messages<?php if ($unreadMessages): ?><span class="notification-count"><?= $unreadMessages ?></span><?php endif; ?></a><a class="button light small" href="profile.php">My profile</a></nav></header>
<main class="shell dashboard-page"><div class="page-heading"><div><span class="eyebrow">Veterinary care</span><h1>Professional care for healthier animals.</h1><p class="hero-copy">Find veterinary support for prescribed injections, vitamins, deworming, vaccinations, diagnosed diseases, and follow-up checkups.</p></div><span class="role-badge">Veterinary services</span></div>
<?php if ($user['role'] === 'veterinarian'): ?><section class="panel form-panel"><span class="eyebrow">Provider tools</span><h2>Publish veterinary service</h2><?php foreach ($errors as $error): ?><div class="alert error"><?= e($error) ?></div><?php endforeach; ?><form class="form-grid" method="post"><input type="hidden" name="action" value="add_veterinary_service"><label>Service name<input name="pig_name" placeholder="Example: Farm vaccination visit" required></label><label>Specialty<input name="breed" value="Veterinary care" required></label><label>Location<input name="location" required></label><label class="wide">Description<textarea name="description" rows="3" placeholder="Describe the care you provide..." required></textarea></label><button class="button primary" type="submit">Publish veterinary service</button></form></section><?php endif; ?>
<section class="veterinary-list"><div class="section-heading"><div><span class="eyebrow">Available support</span><h2>Veterinary services</h2></div></div><div class="service-grid"><?php foreach ($services as $service): ?><article class="service-card"><div class="service-top"><span class="tag">Veterinary care</span><span class="tag">Available</span></div><h3><?= e($service['pig_name']) ?></h3><p><?= e($service['description']) ?></p><small><?= e($service['location']) ?> · <?= e($service['owner_name']) ?></small><div class="service-contact"><span>Provider contact</span><a href="tel:<?= e($service['owner_phone']) ?>"><?= e($service['owner_phone']) ?></a></div><?php if (in_array($user['role'], ['male_owner', 'female_owner'], true)): ?><form class="request-form" method="post" action="dashboard.php"><input type="hidden" name="action" value="request_service"><input type="hidden" name="service_id" value="<?= (int) $service['id'] ?>"><label>Preferred date<input type="date" name="preferred_date" min="<?= date('Y-m-d') ?>" required></label><label>Message to provider<textarea name="notes" rows="3" maxlength="2000" placeholder="Describe the care or transaction details..."></textarea></label><button class="button primary small" type="submit">Request &amp; message provider</button></form><?php endif; ?></article><?php endforeach; ?></div><?php if (!$services): ?><p class="empty-state">No veterinary services are listed yet.</p><?php endif; ?></section>
<?php if ($user['role'] === 'veterinarian'): ?><section class="panel activity-panel"><span class="eyebrow">Provider activity</span><h2>Owner requests</h2><?php if ($incomingRequests): ?><div class="table-wrap"><table><tr><th>Reference</th><th>Owner</th><th>Contact</th><th>Date</th><th>Status</th><th>Messages</th><th>Action</th></tr><?php foreach ($incomingRequests as $request): ?><tr><td><?= e($request['reference_no']) ?><small class="table-subtitle"><?= e($request['pig_name']) ?></small></td><td><?= e($request['owner_name']) ?></td><td><small class="table-subtitle"><?= e($request['owner_address']) ?></small><a class="table-contact" href="tel:<?= e($request['owner_phone']) ?>"><?= e($request['owner_phone']) ?></a></td><td><?= e($request['preferred_date']) ?></td><td><span class="status <?= strtolower($request['status']) ?>"><?= e($request['status']) ?></span></td><td><a class="button light small" href="messages.php?request_id=<?= (int) $request['id'] ?>">Open chat</a></td><td><form method="post" action="dashboard.php" class="inline-form"><input type="hidden" name="action" value="update_request"><input type="hidden" name="request_id" value="<?= (int) $request['id'] ?>"><select name="status"><option>Approved</option><option>Rejected</option><option>Completed</option></select><button class="button light small" type="submit">Update</button></form></td></tr><?php endforeach; ?></table></div><?php else: ?><p class="empty-state">No owner requests yet.</p><?php endif; ?></section><?php endif; ?>
    </main><?php require __DIR__ . '/footer.php'; ?>
</body>
</html>
