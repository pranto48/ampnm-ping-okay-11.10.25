<?php
// Include the main bootstrap file which handles DB checks and starts the session.
require_once __DIR__ . '/bootstrap.php';

// If the user is not logged in, redirect to the login page.
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// --- External License Validation ---
// This application's license key is now retrieved dynamically from the database.
// The external verification service URL is defined in config.php (LICENSE_API_URL)

$_SESSION['can_add_device'] = false; // Default to false
$_SESSION['license_message'] = 'License validation failed.';
$_SESSION['max_devices'] = 0; // Default max devices

// Retrieve the application license key dynamically
$app_license_key = getAppLicenseKey();

if (!$app_license_key) {
    $_SESSION['license_message'] = 'Application license key not configured.';
    // Redirect to license setup if key is missing, even if logged in (shouldn't happen if bootstrap works)
    header('Location: license_setup.php');
    exit;
}

try {
    $pdo = getDbConnection(); // Get DB connection for the AMPNM app

    // Get current device count for the logged-in user
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM devices WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $current_device_count = $stmt->fetchColumn();

    $ch = curl_init(LICENSE_API_URL); // Use the defined LICENSE_API_URL
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'app_license_key' => $app_license_key,
        'user_id' => $_SESSION['user_id'], // Pass the logged-in user's ID for user-specific checks
        'current_device_count' => $current_device_count // Pass the current device count
    ]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5); // 5-second timeout for the external API call

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        error_log("License API cURL Error: " . $curlError);
        $_SESSION['license_message'] = 'Failed to connect to license verification service.';
    } elseif ($httpCode !== 200) {
        error_log("License API HTTP Error: " . $httpCode . " - Response: " . $response);
        $_SESSION['license_message'] = 'License verification service returned an error.';
    } else {
        $licenseData = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("License API JSON Parse Error: " . json_last_error_msg() . " - Response: " . $response);
            $_SESSION['license_message'] = 'Invalid response from license verification service.';
        } elseif (isset($licenseData['success']) && $licenseData['success'] === true) {
            $_SESSION['max_devices'] = $licenseData['max_devices'] ?? 0;
            
            // Determine can_add_device locally based on max_devices and current count
            $_SESSION['can_add_device'] = ($current_device_count < $_SESSION['max_devices']);
            
            $_SESSION['license_message'] = $licenseData['message'] ?? 'License validated successfully.';
            if (!$_SESSION['can_add_device']) {
                $_SESSION['license_message'] = "License active, but you have reached your device limit ({$_SESSION['max_devices']} devices).";
            }

        } else {
            $_SESSION['license_message'] = $licenseData['message'] ?? 'License validation failed.';
        }
    }
} catch (Exception $e) {
    error_log("License API Exception: " . $e->getMessage());
    $_SESSION['license_message'] = 'An unexpected error occurred during license validation.';
}