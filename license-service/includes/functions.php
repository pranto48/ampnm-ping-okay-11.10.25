<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database configuration
require_once __DIR__ . '/../config.php';

// Function to get database connection (defined in config.php)
// function getLicenseDbConnection() is already defined in config.php

// Function to generate a unique license key
function generateLicenseKey($prefix = 'AMPNM') {
    // Generate a UUID (Universally Unique Identifier)
    // This is a simple way to get a unique string. For stronger keys, consider more complex algorithms.
    $data = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // set version to 0100
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // set bits 6-7 to 10
    $uuid = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    return strtoupper($prefix . '-' . $uuid);
}

// --- Customer Authentication Functions ---
function authenticateCustomer($email, $password) {
    $pdo = getLicenseDbConnection();
    $stmt = $pdo->prepare("SELECT id, password, first_name, last_name, email FROM `customers` WHERE email = ?");
    $stmt->execute([$email]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($customer && password_verify($password, $customer['password'])) {
        $_SESSION['customer_id'] = $customer['id'];
        $_SESSION['customer_email'] = $customer['email'];
        $_SESSION['customer_name'] = $customer['first_name'] . ' ' . $customer['last_name'];
        return true;
    }
    return false;
}

function isCustomerLoggedIn() {
    return isset($_SESSION['customer_id']);
}

function logoutCustomer() {
    unset($_SESSION['customer_id']);
    unset($_SESSION['customer_email']);
    unset($_SESSION['customer_name']);
    session_destroy();
    session_start(); // Start a new session for potential new login
}

// --- Admin Authentication Functions ---
function authenticateAdmin($username, $password) {
    $pdo = getLicenseDbConnection();
    $stmt = $pdo->prepare("SELECT id, password, username, email FROM `admin_users` WHERE username = ?");
    $stmt->execute([$username]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($admin && password_verify($password, $admin['password'])) {
        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['admin_username'] = $admin['username'];
        $_SESSION['admin_email'] = $admin['email'];
        return true;
    }
    return false;
}

function isAdminLoggedIn() {
    return isset($_SESSION['admin_id']);
}

function logoutAdmin() {
    unset($_SESSION['admin_id']);
    unset($_SESSION['admin_username']);
    unset($_SESSION['admin_email']);
    session_destroy();
    session_start(); // Start a new session for potential new login
}

// --- Common Redirects ---
function redirectToLogin() {
    header('Location: login.php');
    exit;
}

function redirectToAdminLogin() {
    header('Location: adminpanel.php');
    exit;
}

function redirectToDashboard() {
    header('Location: dashboard.php');
    exit;
}

function redirectToAdminDashboard() {
    header('Location: admin/index.php'); // Assuming admin dashboard is in admin/index.php
    exit;
}

// --- Basic HTML Header/Footer for the portal ---
function portal_header($title = "IT Support BD Portal") {
    echo '<!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>' . htmlspecialchars($title) . '</title>
        <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
        <style>
            body { @apply bg-gray-100 text-gray-800; }
            .container { @apply mx-auto p-4; }
            .navbar { @apply bg-white shadow-md; }
            .nav-link { @apply text-gray-700 hover:text-blue-600 px-3 py-2 rounded-md text-sm font-medium; }
            .btn-primary { @apply bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded; }
            .btn-secondary { @apply bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded; }
            .card { @apply bg-white shadow-md rounded-lg p-6; }
            .form-input { @apply shadow-sm appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline; }
            .alert-success { @apply bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative; }
            .alert-error { @apply bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative; }
        </style>
    </head>
    <body>
        <nav class="navbar">
            <div class="container flex justify-between items-center">
                <a href="index.php" class="text-xl font-bold text-blue-600">IT Support BD Portal</a>
                <div class="flex space-x-4">';
    if (isCustomerLoggedIn()) {
        echo '<a href="products.php" class="nav-link">Products</a>
              <a href="dashboard.php" class="nav-link">Dashboard</a>
              <a href="cart.php" class="nav-link"><i class="fas fa-shopping-cart"></i> Cart</a>
              <a href="logout.php" class="nav-link">Logout (' . htmlspecialchars($_SESSION['customer_email']) . ')</a>';
    } else {
        echo '<a href="products.php" class="nav-link">Products</a>
              <a href="login.php" class="nav-link">Login</a>
              <a href="registration.php" class="nav-link">Register</a>';
    }
    echo '</div>
            </div>
        </nav>
        <main class="container py-8">';
}

function portal_footer() {
    echo '</main>
        <footer class="text-center py-4 text-gray-600 text-sm">
            <p>&copy; ' . date("Y") . ' IT Support BD. All rights reserved.</p>
        </footer>
    </body>
    </html>';
}

function admin_header($title = "Admin Panel") {
    echo '<!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>' . htmlspecialchars($title) . '</title>
        <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
        <style>
            body { @apply bg-gray-800 text-gray-100; }
            .container { @apply mx-auto p-4; }
            .admin-navbar { @apply bg-gray-900 shadow-md; }
            .admin-nav-link { @apply text-gray-300 hover:text-blue-400 px-3 py-2 rounded-md text-sm font-medium; }
            .btn-admin-primary { @apply bg-blue-700 hover:bg-blue-800 text-white font-bold py-2 px-4 rounded; }
            .btn-admin-danger { @apply bg-red-700 hover:bg-red-800 text-white font-bold py-2 px-4 rounded; }
            .admin-card { @apply bg-gray-700 shadow-md rounded-lg p-6; }
            .form-admin-input { @apply shadow-sm appearance-none border rounded w-full py-2 px-3 text-gray-100 leading-tight focus:outline-none focus:shadow-outline bg-gray-600 border-gray-500; }
            .alert-admin-success { @apply bg-green-700 text-white px-4 py-3 rounded relative; }
            .alert-admin-error { @apply bg-red-700 text-white px-4 py-3 rounded relative; }
        </style>
    </head>
    <body>
        <nav class="admin-navbar">
            <div class="container flex justify-between items-center">
                <a href="adminpanel.php" class="text-xl font-bold text-blue-400">Admin Panel</a>
                <div class="flex space-x-4">';
    if (isAdminLoggedIn()) {
        echo '<a href="adminpanel.php" class="admin-nav-link">Dashboard</a>
              <a href="users.php" class="admin-nav-link">Customers</a>
              <a href="license-manager.php" class="admin-nav-link">Licenses</a>
              <a href="logout.php?admin=true" class="admin-nav-link">Logout (' . htmlspecialchars($_SESSION['admin_username']) . ')</a>';
    } else {
        echo '<a href="adminpanel.php" class="admin-nav-link">Login</a>';
    }
    echo '</div>
            </div>
        </nav>
        <main class="container py-8">';
}

function admin_footer() {
    echo '</main>
        <footer class="text-center py-4 text-gray-400 text-sm">
            <p>&copy; ' . date("Y") . ' IT Support BD Admin. All rights reserved.</p>
        </footer>
    </body>
    </html>';
}
?>