<?php

require_once __DIR__ . '/auth.php';

$allowedPages = ['about', 'services', 'how-it-works', 'features', 'contact'];
$page = (string) ($_GET['page'] ?? 'about');
if (!in_array($page, $allowedPages, true)) {
    redirect('index.php');
}

$pageData = [
    'about' => ['eyebrow' => 'About Pig Services', 'title' => 'A clearer way to coordinate pig care.', 'intro' => 'Pig Services connects owners and qualified providers in one organized platform for breeding coordination and veterinary support.'],
    'services' => ['eyebrow' => 'Our Services', 'title' => 'Support for every stage of pig ownership.', 'intro' => 'Explore breeding connections and veterinary services designed for practical, local coordination.'],
    'how-it-works' => ['eyebrow' => 'How It Works', 'title' => 'A clear path from search to service.', 'intro' => 'Create a profile, find breeding or veterinary support, and coordinate directly with the service provider.'],
    'features' => ['eyebrow' => 'Platform Features', 'title' => 'Organized tools for confident decisions.', 'intro' => 'Everything is designed around clear profiles, reliable requests, and private transaction communication.'],
    'contact' => ['eyebrow' => 'Contact Us', 'title' => 'Let’s make local pig services easier.', 'intro' => 'Connect with the Pig Services community in Bindoy, Negros Oriental.'],
];
$currentPage = $pageData[$page];
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($currentPage['title']) ?> | Pig Services</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<header class="shell topbar landing-header">
    <a class="brand" href="index.php#home"><span class="brand-mark">P</span> Pig Services</a>
    <nav class="nav landing-nav"><a href="index.php#home">Home</a><a class="<?= $page === 'about' ? 'active' : '' ?>" href="site_page.php?page=about">About Us</a><a class="<?= $page === 'services' ? 'active' : '' ?>" href="site_page.php?page=services">Services</a><a class="<?= $page === 'how-it-works' ? 'active' : '' ?>" href="site_page.php?page=how-it-works">How It Works</a><a class="<?= $page === 'features' ? 'active' : '' ?>" href="site_page.php?page=features">Features</a><a class="<?= $page === 'contact' ? 'active' : '' ?>" href="site_page.php?page=contact">Contact Us</a><a class="button light small" href="index.php?mode=login#access">Sign in</a><a class="button primary small" href="index.php?mode=register#access">Create account</a></nav>
</header>
<main class="content-page">
    <section class="content-hero"><div class="shell"><span class="eyebrow"><?= e($currentPage['eyebrow']) ?></span><h1><?= e($currentPage['title']) ?></h1><p><?= e($currentPage['intro']) ?></p></div></section>
    <?php if ($page === 'about'): ?>
        <section class="shell content-section"><div class="content-narrow"><h2>Built for local owners and providers.</h2><p>Pig Services gives pig owners a trusted place to find breeding partners, request veterinary care, and communicate with the people involved in each transaction.</p><div class="content-grid"><div><strong>Local connection</strong><p>Designed for Bindoy and nearby communities, with service details that make location and contact information easy to understand.</p></div><div><strong>Clear coordination</strong><p>Requests, dates, status updates, and messages stay connected to the service they belong to.</p></div><div><strong>Health-conscious care</strong><p>Veterinary support can be requested for vaccinations, injections, deworming, disease care, and follow-up checkups.</p></div></div></div></section>
    <?php elseif ($page === 'services'): ?>
        <section class="shell content-section"><div class="content-showcase-grid"><article class="content-service-card"><img src="assets/images/Male%20Pig%20Services.png" alt="Male pig breeding services"><span class="showcase-number">01</span><h2>Male Pig Breeding</h2><p>Register and showcase male pigs so female owners can discover suitable partners by breed, location, and availability.</p></article><article class="content-service-card"><img src="assets/images/Female%20Pig%20Services.png" alt="Female pig breeding services"><span class="showcase-number">02</span><h2>Female Pig Breeding</h2><p>Find a suitable partner, submit a request, choose a preferred date, and message the service provider directly.</p></article><article class="content-service-card"><img src="assets/images/Vaccination%20And%20Injections.png" alt="Veterinary vaccination services"><span class="showcase-number">03</span><h2>Veterinary Services</h2><p>Request professional assistance for vaccination, injections, deworming, diagnosed disease treatment, and follow-up care.</p></article></div></section>
    <?php elseif ($page === 'how-it-works'): ?>
        <section class="shell content-section">
            <div class="step-list">
                <div><strong>01</strong><div><h2>Create your profile</h2><p>Register as a pig owner or veterinary service provider. Add current contact details so other users can identify and reach you.</p></div></div>
                <div><strong>02</strong><div><h2>Publish or find a service</h2><p>Providers can publish a breeding or veterinary listing. Owners can browse listings and check the service description, location, provider, and contact details.</p></div></div>
                <div><strong>03</strong><div><h2>Send a service request</h2><p>Select a listing, choose a preferred date, and include a message with the information the provider needs to respond.</p></div></div>
                <div><strong>04</strong><div><h2>Coordinate in one conversation</h2><p>The owner and provider can both reply in the request’s private message thread and follow its status as they coordinate the service.</p></div></div>
            </div>
            <div class="content-narrow" style="margin-top: 32px">
                <h2>Service area</h2>
                <p>Pig Services is currently focused on Bindoy and nearby communities in Negros Oriental. Confirm the provider’s location and availability before arranging a service.</p>
                <a class="button primary" href="index.php?mode=register#access">Create an account</a>
            </div>
        </section>
    <?php elseif ($page === 'features'): ?>
        <section class="shell content-section"><div class="content-grid feature-grid-large"><div><h2>Profiles</h2><p>Owners and providers can update their name, phone, address, email, and password securely.</p></div><div><h2>Service directory</h2><p>Separate breeding and veterinary service areas make it easier to find the right support.</p></div><div><h2>Requests</h2><p>Every request has a date, status, owner details, and a direct path to its conversation.</p></div><div><h2>Messaging</h2><p>Both sides can reply to individual messages and see clear dates and timestamps.</p></div></div></section>
    <?php else: ?>
        <section class="shell content-section contact-page"><div><span class="eyebrow">Community support</span><h2>Find Pig Services in Bindoy.</h2><p>For account or service questions, use your registered account to connect with the people involved in your transaction.</p><a class="button primary" href="index.php?mode=register#access">Create an account</a></div><div class="contact-details"><strong>Location</strong><span>Bindoy, Negros Oriental</span><strong>Email</strong><a href="mailto:info@pigservices.local">info@pigservices.local</a><strong>Phone</strong><a href="tel:+639171234567">+63 917 123 4567</a></div></section>
    <?php endif; ?>
</main>
<?php require __DIR__ . '/footer.php'; ?>
</body>
</html>
