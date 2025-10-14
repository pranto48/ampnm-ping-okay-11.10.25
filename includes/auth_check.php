<?php
// Include the main bootstrap file which handles DB checks and starts the session.
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/license_check.php'; // Include the new license check utility

// If the user is not logged in, redirect to the login page.
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Fetch and store license information in the session
$pdo = getDbConnection();
$_SESSION['user_license'] = getUserLicense($pdo, $_SESSION['user_id']);
$_SESSION['can_add_device'] = checkDeviceAllowance($pdo, $_SESSION['user_id'])['can_add_device'];

// License check is now handled by the separate sales_licensing_app.
// The main application will query that service for license validation.
?>