<?php

require_once __DIR__ . '/auth.php';

$user = require_login();
if ($user['role'] === 'admin') {
    redirect('admin_dashboard.php');
}
if ($user['role'] === 'veterinarian') {
    redirect('veterinary.php');
}
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'add_service' && $user['role'] === 'male_owner') {
        $serviceType = (string) ($_POST['service_type'] ?? 'breeding');
        $fields = [trim((string) ($_POST['pig_name'] ?? '')), trim((string) ($_POST['breed'] ?? '')), (int) ($_POST['age_months'] ?? 0), trim((string) ($_POST['location'] ?? '')), trim((string) ($_POST['description'] ?? ''))];
        if (!in_array($serviceType, ['breeding', 'veterinary'], true) || $fields[0] === '' || $fields[1] === '' || $fields[2] < 0 || $fields[3] === '' || $fields[4] === '') {
            $errors[] = 'Complete all service fields with valid values.';
        } else {
            $statement = $pdo->prepare('INSERT INTO services (owner_id, service_type, pig_name, breed, age_months, location, description) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $statement->execute([$user['id'], $serviceType, ...$fields]);
            flash('success', 'Your ' . $serviceType . ' service is now listed.');
            redirect('dashboard.php');
        }
    } elseif ($action === 'request_service' && in_array($user['role'], ['male_owner', 'female_owner'], true)) {
        $serviceId = (int) ($_POST['service_id'] ?? 0);
        $preferredDate = (string) ($_POST['preferred_date'] ?? '');
        $notes = trim((string) ($_POST['notes'] ?? ''));
        if ($serviceId < 1 || $preferredDate === '') {
            $errors[] = 'Choose a service and preferred date.';
        } else {
            $ownerStatement = $pdo->prepare('SELECT owner_id FROM services WHERE id = ? AND is_active = 1 LIMIT 1');
            $ownerStatement->execute([$serviceId]);
            $serviceOwnerId = (int) $ownerStatement->fetchColumn();
            if ($serviceOwnerId < 1) {
                $errors[] = 'That breeding service is no longer available.';
                $serviceOwnerId = 0;
            }
        }
        if (!$errors && $serviceOwnerId > 0) {
            $reference = 'PL-' . strtoupper(bin2hex(random_bytes(4)));
            $statement = $pdo->prepare('INSERT INTO requests (reference_no, service_id, female_owner_id, preferred_date, notes) VALUES (?, ?, ?, ?, ?)');
            $statement->execute([$reference, $serviceId, $user['id'], $preferredDate, $notes]);
            $requestId = (int) $pdo->lastInsertId();
            if ($notes !== '') {
                $messageStatement = $pdo->prepare('INSERT INTO messages (request_id, sender_id, recipient_id, message) VALUES (?, ?, ?, ?)');
                $messageStatement->execute([$requestId, $user['id'], $serviceOwnerId, $notes]);
            }
            flash('success', 'Request ' . $reference . ' submitted. You can now message the service owner.');
            redirect('messages.php?request_id=' . $requestId);
        }
    } elseif ($action === 'update_request' && in_array($user['role'], ['male_owner', 'veterinarian'], true)) {
        $status = (string) ($_POST['status'] ?? 'Pending');
        if (in_array($status, ['Approved', 'Rejected', 'Completed'], true)) {
            $statement = $pdo->prepare('UPDATE requests SET status = ? WHERE id = ? AND service_id IN (SELECT id FROM services WHERE owner_id = ?)');
            $statement->execute([$status, (int) ($_POST['request_id'] ?? 0), $user['id']]);
            flash($statement->rowCount() === 1 ? 'success' : 'error', $statement->rowCount() === 1 ? 'Service request updated.' : 'That request could not be updated.');
            redirect('dashboard.php');
        }
    } elseif ($action === 'cancel_request' && $user['role'] === 'female_owner') {
        $statement = $pdo->prepare("UPDATE requests SET status = 'Cancelled' WHERE id = ? AND female_owner_id = ? AND status = 'Pending'");
        $statement->execute([(int) ($_POST['request_id'] ?? 0), $user['id']]);
        flash($statement->rowCount() === 1 ? 'success' : 'error', $statement->rowCount() === 1 ? 'Service request cancelled.' : 'Only pending requests can be cancelled.');
        redirect('dashboard.php');
    } elseif ($action === 'verify_user' && $user['role'] === 'admin') {
        $statement = $pdo->prepare("UPDATE users SET is_verified = 1 WHERE id = ? AND role <> 'admin' AND is_verified = 0");
        $statement->execute([(int) $_POST['user_id']]);
        $approved = $statement->rowCount() === 1;
        flash($approved ? 'success' : 'error', $approved ? 'Account approved.' : 'That account was already approved or could not be found.');
        redirect('dashboard.php');
    } elseif ($action === 'update_request' && $user['role'] === 'admin') {
        $status = (string) ($_POST['status'] ?? 'Pending');
        if (in_array($status, ['Pending', 'Approved', 'Rejected', 'Completed', 'Cancelled'], true)) {
            $statement = $pdo->prepare('UPDATE requests SET status = ? WHERE id = ?');
            $statement->execute([$status, (int) $_POST['request_id']]);
            flash('success', 'Request status updated.');
            redirect('dashboard.php');
        }
    }
}

$flash = take_flash();
$services = $pdo->query("SELECT services.*, users.full_name AS owner_name, users.phone AS owner_phone FROM services JOIN users ON users.id = services.owner_id WHERE services.is_active = 1 AND services.service_type IN ('breeding', 'veterinary') ORDER BY services.created_at DESC")->fetchAll();
$breedingServices = array_values(array_filter($services, static fn (array $service): bool => ($service['service_type'] ?? 'breeding') === 'breeding'));
$veterinaryServices = array_values(array_filter($services, static fn (array $service): bool => ($service['service_type'] ?? 'breeding') === 'veterinary'));
$myRequests = [];
$incomingRequests = [];
$relatedRequests = [];
$pendingUsers = [];
if (in_array($user['role'], ['male_owner', 'female_owner'], true)) {
    $statement = $pdo->prepare('SELECT requests.*, services.pig_name, services.breed, services.service_type, users.full_name AS owner_name FROM requests JOIN services ON services.id = requests.service_id JOIN users ON users.id = services.owner_id WHERE requests.female_owner_id = ? ORDER BY requests.created_at DESC');
    $statement->execute([$user['id']]);
    $myRequests = $statement->fetchAll();

    $relatedStatement = $pdo->prepare('SELECT requests.*, services.pig_name, services.breed, services.service_type, users.full_name AS owner_name FROM requests JOIN services ON services.id = requests.service_id JOIN users ON users.id = services.owner_id WHERE requests.female_owner_id = ? OR services.owner_id = ? ORDER BY requests.created_at DESC');
    $relatedStatement->execute([$user['id'], $user['id']]);
    $relatedRequests = $relatedStatement->fetchAll();
}
if (in_array($user['role'], ['male_owner', 'veterinarian'], true)) {
    $statement = $pdo->prepare('SELECT requests.*, services.pig_name, services.service_type, users.full_name AS female_name, users.phone AS female_phone, users.address AS female_address FROM requests JOIN services ON services.id = requests.service_id JOIN users ON users.id = requests.female_owner_id WHERE services.owner_id = ? ORDER BY requests.created_at DESC');
    $statement->execute([$user['id']]);
    $incomingRequests = $statement->fetchAll();
} elseif ($user['role'] === 'admin') {
    $pendingUsers = $pdo->query("SELECT id, full_name, email, role, created_at FROM users WHERE is_verified = 0 ORDER BY created_at ASC")->fetchAll();
    $incomingRequests = $pdo->query('SELECT requests.*, services.pig_name, services.service_type, users.full_name AS female_name FROM requests JOIN services ON services.id = requests.service_id JOIN users ON users.id = requests.female_owner_id ORDER BY requests.created_at DESC')->fetchAll();
}
$totalUsers = 0;
if ($user['role'] === 'admin') {
    $totalUsers = (int) $pdo->query('SELECT COUNT(*) FROM users WHERE role <> \'admin\'')->fetchColumn();
}
$unreadMessages = 0;
try {
    $unreadStatement = $pdo->prepare('SELECT COUNT(*) FROM messages WHERE recipient_id = ? AND read_at IS NULL');
    $unreadStatement->execute([$user['id']]);
    $unreadMessages = (int) $unreadStatement->fetchColumn();
} catch (PDOException $exception) {
    $unreadMessages = 0;
}
?><!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Dashboard | Pig Services</title><link rel="stylesheet" href="assets/style.css"></head>
<body>
<header class="shell topbar"><a class="brand" href="dashboard.php"><span class="brand-mark">P</span> Pig Services</a><nav class="nav"><span><?= e($user['full_name']) ?></span><a class="button light small" href="veterinary.php">Veterinary services</a><a class="button light small" href="messages.php">Messages<?php if ($unreadMessages): ?><span class="notification-count"><?= $unreadMessages ?></span><?php endif; ?></a><a class="button light small" href="profile.php">My profile</a><a class="button light small" href="logout.php">Sign out</a></nav></header>
<main class="shell dashboard-page"><div class="page-heading"><div><span class="eyebrow">Pig Services dashboard</span><h1><?= $user['role'] === 'admin' ? 'Keep the marketplace moving.' : 'Good to see you, ' . e(strtok($user['full_name'], ' ')) . '.' ?></h1></div><span class="role-badge"><?= e(str_replace('_', ' ', $user['role'])) ?></span></div>
<?php if ($flash): ?><div class="alert success"><?= e($flash['message']) ?></div><?php endif; ?><?php foreach ($errors as $error): ?><div class="alert error"><?= e($error) ?></div><?php endforeach; ?>
<?php if ($user['role'] === 'admin'): ?><section class="admin-banner"><div><span class="eyebrow">Administration center</span><h2>Manage Pig Services activity</h2><p>Review new accounts, monitor service requests, and keep the marketplace trusted.</p></div><div class="admin-banner-mark">P</div></section><?php endif; ?>
<div class="stats"><div class="stat"><small>Active listings</small><strong><?= count($services) ?></strong></div><div class="stat"><small><?= $user['role'] === 'admin' ? 'Awaiting approval' : 'Your requests' ?></small><strong><?= $user['role'] === 'admin' ? count($pendingUsers) : count($myRequests) ?></strong></div><div class="stat"><small>Location</small><strong>Bindoy</strong></div></div>
<?php if ($user['role'] === 'male_owner'): ?><section class="panel form-panel"><span class="eyebrow">New listing</span><h2>Offer a service</h2><form class="form-grid" method="post"><input type="hidden" name="action" value="add_service"><label>Service category<select name="service_type"><option value="breeding">Breeding service</option></select></label><label>Service or pig name<input name="pig_name" required></label><label>Breed or specialty<input name="breed" placeholder="Example: Large White or Veterinary care" required></label><label>Age in months<input type="number" name="age_months" min="0" value="0" required></label><label>Location<input name="location" required></label><label class="wide">Description<textarea name="description" rows="3" required></textarea></label><button class="button primary" type="submit">Publish listing</button></form></section><?php endif; ?>
<?php if ($user['role'] === 'admin'): ?><section class="panel"><span class="eyebrow">Review queue</span><h2>Accounts awaiting approval</h2><?php if ($pendingUsers): ?><div class="table-wrap"><table><tr><th>Name</th><th>Email</th><th>Type</th><th></th></tr><?php foreach ($pendingUsers as $pending): ?><tr><td><?= e($pending['full_name']) ?></td><td><?= e($pending['email']) ?></td><td><?= e(str_replace('_', ' ', $pending['role'])) ?></td><td><form method="post"><input type="hidden" name="action" value="verify_user"><input type="hidden" name="user_id" value="<?= (int) $pending['id'] ?>"><button class="button primary small">Approve</button></form></td></tr><?php endforeach; ?></table></div><?php else: ?><p class="empty-state">All registered accounts are approved. New registrations will appear here for review.</p><?php endif; ?></section><?php endif; ?>
<section>
    <div class="section-heading">
        <div><span class="eyebrow">Marketplace</span><h2>Available breeding services</h2></div>
    </div>
    <div class="service-grid">
        <?php foreach ($breedingServices as $service): ?>
            <article class="service-card">
                <div class="service-top">
                    <span class="tag"><?= e($service['breed']) ?></span>
                    <span class="tag">Available</span>
                </div>
                <h3><?= e($service['pig_name']) ?></h3>
                <p><?= e($service['description']) ?></p>
                <small><?= e($service['location']) ?> · <?= (int) $service['age_months'] ?> months · <?= e($service['owner_name']) ?></small>
                <div class="service-contact">
                    <span>Owner contact</span>
                    <a href="tel:<?= e($service['owner_phone']) ?>"><?= e($service['owner_phone']) ?></a>
                </div>
                <?php if (in_array($user['role'], ['male_owner', 'female_owner'], true)): ?>
                    <form class="request-form" method="post">
                        <input type="hidden" name="action" value="request_service">
                        <input type="hidden" name="service_id" value="<?= (int) $service['id'] ?>">
                        <label>Preferred date<input type="date" name="preferred_date" min="<?= date('Y-m-d') ?>" required></label>
                        <label>Message<textarea name="notes" rows="3" maxlength="2000" placeholder="Write your request or details for this breeding service..."></textarea></label>
                        <button class="button primary small" type="submit">Request &amp; message</button>
                    </form>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
        <?php if (!$breedingServices): ?><p class="empty-state">No breeding services are listed yet.</p><?php endif; ?>
    </div>
</section>

<section>
    <div class="section-heading">
        <div><span class="eyebrow">Veterinary care</span><h2>Available veterinary services</h2></div>
    </div>
    <div class="service-grid">
        <?php foreach ($veterinaryServices as $service): ?>
            <article class="service-card">
                <div class="service-top">
                    <span class="tag">Veterinary care</span>
                    <span class="tag">Available</span>
                </div>
                <h3><?= e($service['pig_name']) ?></h3>
                <p><?= e($service['description']) ?></p>
                <small><?= e($service['location']) ?> · <?= e($service['owner_name']) ?></small>
                <div class="service-contact">
                    <span>Provider contact</span>
                    <a href="tel:<?= e($service['owner_phone']) ?>"><?= e($service['owner_phone']) ?></a>
                </div>
                <?php if (in_array($user['role'], ['male_owner', 'female_owner'], true)): ?>
                    <form class="request-form" method="post">
                        <input type="hidden" name="action" value="request_service">
                        <input type="hidden" name="service_id" value="<?= (int) $service['id'] ?>">
                        <label>Preferred date<input type="date" name="preferred_date" min="<?= date('Y-m-d') ?>" required></label>
                        <label>Message<textarea name="notes" rows="3" maxlength="2000" placeholder="Write your request or details for this veterinary service..."></textarea></label>
                        <button class="button primary small" type="submit">Request &amp; message</button>
                    </form>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
        <?php if (!$veterinaryServices): ?><p class="empty-state">No veterinary services are listed yet.</p><?php endif; ?>
    </div>
</section>

<section class="panel activity-panel">
    <span class="eyebrow">Activity</span>
    <h2><?= in_array($user['role'], ['male_owner', 'female_owner'], true) ? 'My requests and conversations' : 'Incoming service requests' ?></h2>
    <?php $activity = in_array($user['role'], ['male_owner', 'female_owner'], true) ? $relatedRequests : $incomingRequests; ?>
    <?php if ($activity): ?>
        <div class="table-wrap">
            <table>
                <tr>
                    <th>Reference</th>
                    <th>Service</th>
                    <th><?= in_array($user['role'], ['male_owner', 'female_owner'], true) ? 'Partner' : 'Female owner contact' ?></th>
                    <th>Date</th>
                    <th>Status</th>
                    <th>Messages</th>
                    <th>Action</th>
                </tr>
                <?php foreach ($activity as $request): ?>
                    <tr>
                        <td><?= e($request['reference_no']) ?></td>
                        <td><?= e($request['pig_name']) ?> <small class="table-subtitle"><?= e($request['service_type']) ?></small></td>
                        <td>
                            <?php if (in_array($user['role'], ['male_owner', 'veterinarian'], true)): ?>
                                <strong><?= e($request['female_name'] ?? '') ?></strong>
                                <small class="table-subtitle"><?= e($request['female_address'] ?? '') ?></small>
                                <a class="table-contact" href="tel:<?= e($request['female_phone'] ?? '') ?>"><?= e($request['female_phone'] ?? '') ?></a>
                            <?php else: ?>
                                <?= e($request['owner_name'] ?? '') ?>
                            <?php endif; ?>
                        </td>
                        <td><?= e($request['preferred_date']) ?></td>
                        <td><span class="status <?= strtolower($request['status']) ?>"><?= e($request['status']) ?></span></td>
                        <td><a class="button light small" href="messages.php?request_id=<?= (int) $request['id'] ?>">Open chat</a></td>
                        <td>
                            <?php if (in_array($user['role'], ['male_owner', 'veterinarian'], true) && $request['status'] !== 'Cancelled' && $request['status'] !== 'Rejected'): ?>
                                <form method="post" class="inline-form">
                                    <input type="hidden" name="action" value="update_request">
                                    <input type="hidden" name="request_id" value="<?= (int) $request['id'] ?>">
                                    <select name="status">
                                        <option <?= $request['status'] === 'Approved' ? 'selected' : '' ?>>Approved</option>
                                        <option <?= $request['status'] === 'Rejected' ? 'selected' : '' ?>>Rejected</option>
                                        <option <?= $request['status'] === 'Completed' ? 'selected' : '' ?>>Completed</option>
                                    </select>
                                    <button class="button light small" type="submit">Update</button>
                                </form>
                            <?php elseif ($user['role'] === 'female_owner' && $request['status'] === 'Pending'): ?>
                                <form method="post" class="inline-form">
                                    <input type="hidden" name="action" value="cancel_request">
                                    <input type="hidden" name="request_id" value="<?= (int) $request['id'] ?>">
                                    <button class="button light small" type="submit">Cancel</button>
                                </form>
                            <?php elseif ($user['role'] === 'male_owner' && $request['status'] === 'Pending'): ?>
                                <form method="post" class="inline-form">
                                    <input type="hidden" name="action" value="update_request">
                                    <input type="hidden" name="request_id" value="<?= (int) $request['id'] ?>">
                                    <select name="status">
                                        <option>Approved</option>
                                        <option>Rejected</option>
                                        <option>Completed</option>
                                    </select>
                                    <button class="button light small" type="submit">Update</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
    <?php else: ?>
        <p class="empty-state">No service requests yet.</p>
    <?php endif; ?>
</section>

</main><?php require __DIR__ . '/footer.php'; ?></body></html>
