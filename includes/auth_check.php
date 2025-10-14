<?php
// Include the main bootstrap file which handles DB checks and starts the session.
require_once __DIR__ . '/bootstrap.php';

// If the user is not logged in, redirect to the login page.
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Perform license check for authenticated users
require_once __DIR__ . '/license_check.php';

// Enforce license: If license is not valid and not unconfigured, redirect to license_required.php
// 'unconfigured' status is allowed to pass through for initial setup/testing of the licensing system.
if (isset($_SESSION['license_status']) && $_SESSION['license_status'] !== 'valid' && $_SESSION['license_status'] !== 'unconfigured') {
    // Prevent redirect loop if already on license_required.php
    if (basename($_SERVER['PHP_SELF']) !== 'license_required.php') {
        header('Location: license_required.php');
        exit;
    }
}
?>