<?php
require_once 'includes/functions.php';
portal_header("Welcome to IT Support BD Portal");
?>

<div class="text-center py-16">
    <h1 class="text-5xl font-extrabold text-gray-900 mb-4">Welcome to the AMPNM License Portal</h1>
    <p class="text-xl text-gray-600 mb-8">Your one-stop solution for network monitoring licenses.</p>
    <a href="products.php" class="btn-primary text-lg">Browse Licenses</a>
</div>

<div class="grid grid-cols-1 md:grid-cols-3 gap-8 mt-12">
    <div class="card text-center">
        <i class="fas fa-shield-alt text-5xl text-blue-500 mb-4"></i>
        <h2 class="text-2xl font-semibold mb-2">Secure Licensing</h2>
        <p class="text-gray-600">Get genuine and secure license keys for your AMPNM application.</p>
    </div>
    <div class="card text-center">
        <i class="fas fa-chart-line text-5xl text-green-500 mb-4"></i>
        <h2 class="text-2xl font-semibold mb-2">Flexible Plans</h2>
        <p class="text-gray-600">Choose from various license tiers to fit your network monitoring needs.</p>
    </div>
    <div class="card text-center">
        <i class="fas fa-headset text-5xl text-purple-500 mb-4"></i>
        <h2 class="text-2xl font-semibold mb-2">Dedicated Support</h2>
        <p class="text-gray-600">Access our dedicated support team for any licensing or product queries.</p>
    </div>
</div>

<?php portal_footer(); ?>