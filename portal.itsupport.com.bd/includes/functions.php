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
        <link rel="stylesheet" href="assets/css/portal-style.css">
    </head>
    <body class="flex flex-col min-h-screen">
        <nav class="glass-navbar py-4 shadow-lg sticky top-0 z-50">
            <div class="container mx-auto px-4 flex flex-col md:flex-row justify-between items-center">
                <a href="index.php" class="text-2xl font-bold text-primary-light mb-3 md:mb-0">
                    <i class="fas fa-shield-alt mr-2 text-blue-400"></i>IT Support BD Portal
                </a>
                <div class="flex flex-col md:flex-row space-y-2 md:space-y-0 md:space-x-4">';
    if (isCustomerLoggedIn()) {
        echo '<a href="products.php" class="nav-link text-secondary-light hover:text-blue-300 transition-colors">Products</a>
              <a href="dashboard.php" class="nav-link text-secondary-light hover:text-blue-300 transition-colors">Dashboard</a>
              <a href="cart.php" class="nav-link text-secondary-light hover:text-blue-300 transition-colors"><i class="fas fa-shopping-cart mr-1"></i> Cart</a>
              <a href="logout.php" class="nav-link text-secondary-light hover:text-blue-300 transition-colors"><i class="fas fa-sign-out-alt mr-1"></i> Logout (' . htmlspecialchars($_SESSION['customer_email']) . ')</a>';
    } else {
        echo '<a href="products.php" class="nav-link text-secondary-light hover:text-blue-300 transition-colors">Products</a>
              <a href="login.php" class="nav-link text-secondary-light hover:text-blue-300 transition-colors">Login</a>
              <a href="registration.php" class="nav-link text-secondary-light hover:text-blue-300 transition-colors">Register</a>';
    }
    echo '</div>
            </div>
        </nav>
        <main class="container mx-auto py-8 flex-grow">';
}

function portal_footer() {
    echo '</main>
        <footer class="text-center py-6 text-gray-300 text-sm mt-auto">
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
        <link rel="stylesheet" href="../assets/css/portal-style.css">
    </head>
    <body class="admin-body flex flex-col min-h-screen">
        <nav class="admin-navbar py-4 shadow-md sticky top-0 z-50">
            <div class="container mx-auto px-4 flex flex-col md:flex-row justify-between items-center">
                <a href="adminpanel.php" class="text-2xl font-bold text-blue-400 mb-3 md:mb-0">
                    <i class="fas fa-user-shield mr-2"></i>Admin Panel
                </a>
                <div class="flex flex-col md:flex-row space-y-2 md:space-y-0 md:space-x-4">';
    if (isAdminLoggedIn()) {
        echo '<a href="index.php" class="admin-nav-link">Dashboard</a>
              <a href="users.php" class="admin-nav-link">Customers</a>
              <a href="license-manager.php" class="admin-nav-link">Licenses</a>
              <a href="products.php" class="admin-nav-link">Products</a>
              <a href="../logout.php?admin=true" class="admin-nav-link"><i class="fas fa-sign-out-alt mr-1"></i> Logout (' . htmlspecialchars($_SESSION['admin_username']) . ')</a>';
    } else {
        echo '<a href="adminpanel.php" class="admin-nav-link">Login</a>';
    }
    echo '</div>
            </div>
        </nav>
        <main class="container mx-auto py-8 flex-grow">';
}

function admin_footer() {
    echo '</main>
        <footer class="text-center py-6 text-gray-400 text-sm mt-auto">
            <p>&copy; ' . date("Y") . ' IT Support BD Admin. All rights reserved.</p>
        </footer>
    </body>
    </html>';
}
?>