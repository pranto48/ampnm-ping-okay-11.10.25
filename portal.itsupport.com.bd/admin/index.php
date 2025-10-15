<?php
require_once '../includes/functions.php';

// Ensure admin is logged in
if (!isAdminLoggedIn()) {
    redirectToAdminLogin();
}

$pdo = getLicenseDbConnection();

// Fetch dashboard stats
$total_customers = $pdo->query("SELECT COUNT(*) FROM `customers`")->fetchColumn();
$total_products = $pdo->query("SELECT COUNT(*) FROM `products`")->fetchColumn();
$total_licenses = $pdo->query("SELECT COUNT(*) FROM `licenses`")->fetchColumn();
$total_orders = $pdo->query("SELECT COUNT(*) FROM `orders`")->fetchColumn();
$total_revenue = $pdo->query("SELECT SUM(total_amount) FROM `orders` WHERE status = 'completed'")->fetchColumn() ?: 0;

admin_header("Admin Dashboard");
?>

<h1 class="text-4xl font-bold text-blue-400 mb-8 text-center">Admin Dashboard</h1>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
    <div class="admin-card text-center">
        <i class="fas fa-users text-5xl text-green-400 mb-4"></i>
        <h2 class="text-2xl font-semibold mb-2">Total Customers</h2>
        <p class="text-4xl font-bold text-gray-100"><?= htmlspecialchars($total_customers) ?></p>
    </div>
    <div class="admin-card text-center">
        <i class="fas fa-box-open text-5xl text-purple-400 mb-4"></i>
        <h2 class="text-2xl font-semibold mb-2">Total Products</h2>
        <p class="text-4xl font-bold text-gray-100"><?= htmlspecialchars($total_products) ?></p>
    </div>
    <div class="admin-card text-center">
        <i class="fas fa-ticket-alt text-5xl text-yellow-400 mb-4"></i>
        <h2 class="text-2xl font-semibold mb-2">Total Licenses</h2>
        <p class="text-4xl font-bold text-gray-100"><?= htmlspecialchars($total_licenses) ?></p>
    </div>
    <div class="admin-card text-center">
        <i class="fas fa-shopping-bag text-5xl text-blue-400 mb-4"></i>
        <h2 class="text-2xl font-semibold mb-2">Total Orders</h2>
        <p class="text-4xl font-bold text-gray-100"><?= htmlspecialchars($total_orders) ?></p>
    </div>
    <div class="admin-card text-center">
        <i class="fas fa-dollar-sign text-5xl text-green-500 mb-4"></i>
        <h2 class="text-2xl font-semibold mb-2">Total Revenue</h2>
        <p class="text-4xl font-bold text-gray-100">$<?= htmlspecialchars(number_format($total_revenue, 2)) ?></p>
    </div>
</div>

<div class="admin-card mt-8">
    <h2 class="text-2xl font-semibold text-blue-400 mb-4">Quick Links</h2>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <a href="users.php" class="btn-admin-primary text-center">Manage Customers</a>
        <a href="license-manager.php" class="btn-admin-primary text-center">Manage Licenses</a>
        <a href="products.php" class="btn-admin-primary text-center">Manage Products</a>
    </div>
</div>

<?php admin_footer(); ?>