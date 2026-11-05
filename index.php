<?php

require_once __DIR__ . '/auth.php';

if (current_user()) {
    redirect('dashboard.php');
}

$errors = [];
$mode = ($_GET['mode'] ?? '') === 'register' ? 'register' : 'login';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mode = $_POST['mode'] === 'register' ? 'register' : 'login';

    if ($mode === 'login') {
        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $statement = $pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $statement->execute([$email]);
        $user = $statement->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            $errors[] = 'The email or password is incorrect.';
        } else {
            session_regenerate_id(true);
            unset($user['password_hash']);
            $_SESSION['user'] = $user;
            redirect('dashboard.php');
        }
    } else {
        $fullName = trim((string) ($_POST['full_name'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $phone = trim((string) ($_POST['phone'] ?? ''));
        $address = trim((string) ($_POST['address'] ?? ''));
        $role = (string) ($_POST['role'] ?? 'female_owner');
        $password = (string) ($_POST['password'] ?? '');

        if ($fullName === '' || $email === '' || $phone === '' || $address === '' || $password === '') {
            $errors[] = 'Please complete every field.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Enter a valid email address.';
        } elseif (!in_array($role, ['male_owner', 'female_owner', 'veterinarian'], true)) {
            $errors[] = 'Choose a valid account type.';
        } elseif (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters.';
        } else {
            try {
                $statement = $pdo->prepare('INSERT INTO users (full_name, email, phone, address, password_hash, role, is_verified) VALUES (?, ?, ?, ?, ?, ?, 1)');
                $statement->execute([$fullName, $email, $phone, $address, password_hash($password, PASSWORD_DEFAULT), $role]);
                flash('success', 'Account created successfully. You can now sign in immediately.');
                redirect('index.php?mode=login#access');
            } catch (PDOException $exception) {
                $errors[] = $exception->getCode() === '23000' ? 'That email address is already registered.' : 'Unable to create the account right now.';
            }
        }
    }
}
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $mode === 'register' ? 'Create account' : 'Sign in' ?> | Pig Services</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <header class="shell topbar landing-header">
        <a class="brand" href="index.php#home"><span class="brand-mark">P</span> Pig Services</a>
        <nav class="nav landing-nav"><a href="index.php#home">Home</a><a href="site_page.php?page=about">About Us</a><a href="site_page.php?page=services">Services</a><a href="site_page.php?page=how-it-works">How It Works</a><a href="site_page.php?page=features">Features</a><a href="site_page.php?page=contact">Contact Us</a><a class="button light small" href="index.php?mode=login#access">Sign in</a><a class="button primary small" href="index.php?mode=register#access">Create account</a></nav>
    </header>
    <main id="home">
    <div class="shell auth-layout">
        <section class="auth-intro">
            <span class="eyebrow">A trusted local network</span>
            <h1>Better matches for healthier piglets.</h1>
            <p class="hero-copy">Welcome to a smarter way of managing pig breeding services. Whether you own a male pig or a female pig, Pig Services helps you find partners, submit requests, schedule appointments, and access veterinary assistance.</p>
            <div class="hero-actions"><a class="button primary" href="#services">Explore our services</a><span>Everything pig owners need in one convenient platform.</span></div>
        </section>
        <div class="hero-visual">
            <img src="assets/images/Veterinary%20Pig%20Services%20Farm%20Sign.png" alt="Veterinary pig services farm sign">
        </div>
        <section id="access" class="auth-modal <?= $mode === 'register' ? 'register-mode' : '' ?>" aria-hidden="true">
        <div class="panel auth-card auth-modal-dialog <?= $mode === 'register' ? 'register-card' : '' ?>">
            <button class="modal-close" type="button" aria-label="Close sign in panel" title="Close">&times;</button>
            <div class="auth-card-brand"><span class="brand-mark">P</span><span>Pig Services</span></div>
            <div class="tab-links"><a class="<?= $mode === 'login' ? 'active' : '' ?>" href="index.php?mode=login#access">Sign in</a><a class="<?= $mode === 'register' ? 'active' : '' ?>" href="index.php?mode=register#access">Create account</a></div>
            <?php if ($flash = take_flash()): ?><div class="alert success"><?= e($flash['message']) ?></div><?php endif; ?>
            <?php foreach ($errors as $error): ?><div class="alert error"><?= e($error) ?></div><?php endforeach; ?>
            <?php if ($mode === 'login'): ?>
                <h2>Welcome back</h2><p>Sign in to manage your Pig Services activity.</p>
                <form method="post"><input type="hidden" name="mode" value="login"><label>Email<input type="email" name="email" required autocomplete="email"></label><label>Password<div class="password-control"><input id="login-password" type="password" name="password" required autocomplete="current-password"><button class="password-toggle" type="button" data-target="login-password" aria-label="Show password" title="Show password">&#128065;</button></div></label><button class="button primary" type="submit">Sign in</button></form>
            <?php else: ?>
                <h2>Create your account</h2><p class="form-intro">Join the local network and start managing your breeding services with confidence.</p>
                <form class="register-form" method="post"><input type="hidden" name="mode" value="register"><div class="form-section-label">Personal information</div><div class="register-fields"><label>Full name<input name="full_name" required autocomplete="name"></label><label>Email address<input type="email" name="email" required autocomplete="email"></label><label>Phone number<input name="phone" required autocomplete="tel"></label><label>Address<input name="address" required autocomplete="street-address"></label></div><div class="form-section-label">Account access</div><div class="register-fields"><label>Account type<select name="role"><option value="female_owner">Female pig owner</option><option value="male_owner">Male pig owner</option><option value="veterinarian">Veterinary service provider</option></select></label><label>Password<div class="password-control"><input id="register-password" type="password" name="password" minlength="8" required autocomplete="new-password"><button class="password-toggle" type="button" data-target="register-password" aria-label="Show password" title="Show password">&#128065;</button></div></label></div><button class="button primary" type="submit">Create account</button></form>
            <?php endif; ?>
        </div>
        </section>
    </div>
    <section class="shell service-showcase" id="services">
        <div class="showcase-heading"><span class="eyebrow">One connected platform</span><h2>Everything pig owners need in one place.</h2><p>Discover services, connect with the right people, and keep every request organized from first message to appointment.</p></div>
        <div class="service-showcase-grid">
            <article class="showcase-card"><img src="assets/images/Male%20Pig%20Services.png" alt="Male pig breeding services"><div class="showcase-card-body"><span class="showcase-number">01</span><h3>Male Pig Breeding Services</h3><p>Register and showcase your male pigs. Help female pig owners discover available breeding partners based on breed, location, and availability.</p><a href="index.php?mode=register#access" class="text-link">Register as a service provider <span aria-hidden="true">&rarr;</span></a></div></article>
            <article class="showcase-card"><img src="assets/images/Female%20Pig%20Services.png" alt="Female pig breeding services"><div class="showcase-card-body"><span class="showcase-number">02</span><h3>Female Pig Breeding Services</h3><p>Find suitable male pigs, submit breeding requests, schedule services, and keep your transaction messages organized.</p><a href="index.php?mode=register#access" class="text-link">Create an owner account <span aria-hidden="true">&rarr;</span></a></div></article>
            <article class="showcase-card"><img src="assets/images/Vaccination%20And%20Injections.png" alt="Veterinary vaccination and injection services"><div class="showcase-card-body"><span class="showcase-number">03</span><h3>Veterinary Services</h3><p>Request veterinary assistance, report symptoms, schedule checkups, and access professional animal healthcare services.</p><div class="showcase-detail"><strong>Vaccination and Injections</strong><span>Request veterinary assessments for vaccinations, injections, and appropriate treatments to support pig health.</span></div><a href="index.php?mode=register" class="text-link">Access veterinary care <span aria-hidden="true">&rarr;</span></a></div></article>
        </div>
    </section>
    <section class="shell landing-section" id="about"><div class="landing-section-copy"><span class="eyebrow">About Pig Services</span><h2>A trusted digital space for local pig owners.</h2><p>Pig Services brings breeding coordination and veterinary support into one organized platform for Bindoy and nearby communities. It helps owners make informed connections, keep contact details clear, and follow every transaction from request to conversation.</p></div></section>
    <section class="landing-section landing-section-muted" id="how-it-works"><div class="shell"><div class="showcase-heading"><span class="eyebrow">How It Works</span><h2>Simple steps from discovery to care.</h2></div><div class="process-grid"><div><strong>01</strong><h3>Create an account</h3><p>Choose your owner or veterinary provider account type and keep your profile information current.</p></div><div><strong>02</strong><h3>Explore services</h3><p>Review breeding and veterinary service details, locations, and available contact information.</p></div><div><strong>03</strong><h3>Request and message</h3><p>Submit a request, send the first message, and reply in one private transaction thread.</p></div></div></div></section>
    <section class="shell landing-section" id="features"><div class="showcase-heading"><span class="eyebrow">Features</span><h2>Organized tools for everyday decisions.</h2></div><div class="feature-grid"><div><h3>Clear profiles</h3><p>Owners and service providers can update their contact details and account information.</p></div><div><h3>Service requests</h3><p>Keep preferred dates, status updates, and transaction history connected to each request.</p></div><div><h3>Private messaging</h3><p>Reply to specific messages with timestamps, notifications, and conversation history.</p></div></div></section>
    <section class="landing-section contact-section" id="contact"><div class="shell contact-inner"><div><span class="eyebrow">Contact Us</span><h2>Ready to make your next service connection easier?</h2><p>Start with a Pig Services account and connect with owners and providers in your community.</p></div><a class="button primary" href="index.php?mode=register">Create an account</a></div></section>
    <section class="shell landing-section legal-section" id="privacy"><div class="legal-copy"><div class="legal-title-row"><div><span class="eyebrow">Privacy Policy</span><h2>How Pig Services handles your information.</h2></div><span class="legal-date">Last updated: November 5, 2026</span></div><p class="legal-lead">We collect only the information needed to operate accounts, connect users, coordinate services, and support transaction communication.</p><div class="legal-grid"><div><span class="legal-index">01</span><h3>Information we collect</h3><p>This includes your name, email address, phone number, location, profile details, service listings, requests, and messages you choose to send.</p></div><div><span class="legal-index">02</span><h3>How information is used</h3><p>We use information to authenticate users, display relevant contact details for active transactions, organize requests, send notifications, and provide platform support.</p></div><div><span class="legal-index">03</span><h3>Your responsibility</h3><p>Keep your account information accurate, protect your password, and contact the administrator if you notice unauthorized activity.</p></div><div><span class="legal-index">04</span><h3>Information sharing</h3><p>We do not sell your personal information. Contact details may be visible to the other participant in a service transaction so coordination can happen safely.</p></div></div></div></section>
    <section class="landing-section landing-section-muted legal-section" id="terms"><div class="shell legal-copy"><div class="legal-title-row"><div><span class="eyebrow">Terms &amp; Conditions</span><h2>Use the platform with care and accountability.</h2></div><span class="legal-date">Effective: November 5, 2026</span></div><p class="legal-lead">By creating an account or using Pig Services, you agree to these rules for responsible service coordination.</p><div class="terms-list"><div><strong>1.</strong><p>Provide truthful account, contact, service, and availability information.</p></div><div><strong>2.</strong><p>Use the platform only for legitimate pig breeding and veterinary service coordination.</p></div><div><strong>3.</strong><p>Communicate respectfully and do not send harmful, misleading, or abusive content.</p></div><div><strong>4.</strong><p>Service providers are responsible for their listings, professional claims, appointments, and care decisions.</p></div><div><strong>5.</strong><p>Veterinary services should be provided by qualified professionals and follow appropriate assessments and treatments.</p></div><div><strong>6.</strong><p>Pig Services organizes requests and messages but does not guarantee transactions, diagnoses, treatments, outcomes, or agreements between users.</p></div></div></div></section>
    </main>
    <?php require __DIR__ . '/footer.php'; ?>
<script src="assets/app.js"></script>
</body>
</html>
