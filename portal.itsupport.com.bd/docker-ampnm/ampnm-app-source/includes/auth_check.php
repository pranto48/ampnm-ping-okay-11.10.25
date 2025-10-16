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
$_SESSION['license_status_code'] = 'unknown'; // 'active', 'expired', 'grace_period', 'disabled', 'error', 'in_use'
$_SESSION['license_grace_period_end'] = null; // Timestamp when grace period ends

// Retrieve the application license key dynamically
$app_license_key = getAppLicenseKey();
$installation_id = getInstallationId(); // Retrieve the installation ID

if (!$app_license_key) {
    $_SESSION['license_message'] = 'Application license key not configured.';
    $_SESSION['license_status_code'] = 'disabled';
    // Redirect to license setup if key is missing, even if logged in (shouldn't happen if bootstrap works)
    header('Location: license_setup.php');
    exit;
}

if (!$installation_id) {
    $_SESSION['license_message'] = 'Application installation ID not found. Please re-run database setup.';
    $_SESSION['license_status_code'] = 'disabled';
    header('Location: database_setup.php'); // Redirect to setup to ensure ID is generated
    exit;
}

try {
    $pdo = getDbConnection(); // Get DB connection for the AMPNM app

    // Get current device count for the logged-in user
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM devices WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $current_device_count = $stmt->fetchColumn();

    $last_license_check = getLastLicenseCheck();
    // Changed re-verification interval from 1 month to 1 day
    $one_day_ago = strtotime('-1 day'); 
    $needs_reverification = true;

    if ($last_license_check && strtotime($last_license_check) > $one_day_ago) {
        // If last check was less than a day ago, assume valid unless grace period is active
        $needs_reverification = false;
    }

    // If grace period is active, check if it has expired
    if (isset($_SESSION['license_grace_period_end']) && $_SESSION['license_grace_period_end'] !== null) {
        if (time() > $_SESSION['license_grace_period_end']) {
            // Grace period over, disable app
            $_SESSION['license_status_code'] = 'disabled';
            $_SESSION['license_message'] = 'Your license has expired and the grace period has ended. Please purchase a new license.';
            header('Location: license_expired.php');
            exit;
        } else {
            // Still in grace period, but re-verify if needed
            $_SESSION['license_status_code'] = 'grace_period';
            $_SESSION['license_message'] = 'Your license has expired. You are in a grace period until ' . date('Y-m-d H:i', $_SESSION['license_grace_period_end']) . '. Please renew your license.';
            // Even in grace period, if it's been a day since last check, try to re-verify
            if ($needs_reverification) {
                error_log("DEBUG: Re-verifying license during grace period for user {$_SESSION['user_id']}.");
            }
        }
    }

    // Perform re-verification if needed or if no recent check
    if ($needs_reverification || !isset($_SESSION['license_status_code']) || $_SESSION['license_status_code'] === 'unknown') {
        $ch = curl_init(LICENSE_API_URL); // Use the defined LICENSE_API_URL
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'app_license_key' => (string)$app_license_key,
            'user_id' => (string)$_SESSION['user_id'],
            'current_device_count' => (string)(int)$current_device_count, // Ensure it's an integer then cast to string
            'installation_id' => (string)$installation_id
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
            $_SESSION['license_status_code'] = 'error';
            // If verification fails, start grace period if not already active
            if (!isset($_SESSION['license_grace_period_end']) || $_SESSION['license_grace_period_end'] === null) {
                $_SESSION['license_grace_period_end'] = strtotime('+1 month');
                $_SESSION['license_status_code'] = 'grace_period';
                $_SESSION['license_message'] = 'License verification failed. You are in a grace period until ' . date('Y-m-d H:i', $_SESSION['license_grace_period_end']) . '. Please check your connection or renew your license.';
            }
        } elseif ($httpCode !== 200) {
            error_log("License API HTTP Error: " . $httpCode . " - Response: " . $response);
            $_SESSION['license_message'] = 'License verification service returned an error.';
            $_SESSION['license_status_code'] = 'error';
            if (!isset($_SESSION['license_grace_period_end']) || $_SESSION['license_grace_period_end'] === null) {
                $_SESSION['license_grace_period_end'] = strtotime('+1 month');
                $_SESSION['license_status_code'] = 'grace_period';
                $_SESSION['license_message'] = 'License verification failed. You are in a grace period until ' . date('Y-m-d H:i', $_SESSION['license_grace_period_end']) . '. Please check your connection or renew your license.';
            }
        } else {
            $licenseData = json_decode($response, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log("License API JSON Parse Error: " . json_last_error_msg() . " - Response: " . $response);
                $_SESSION['license_message'] = 'Invalid response from license verification service.';
                $_SESSION['license_status_code'] = 'error';
                if (!isset($_SESSION['license_grace_period_end']) || $_SESSION['license_grace_period_end'] === null) {
                    $_SESSION['license_grace_period_end'] = strtotime('+1 month');
                    $_SESSION['license_status_code'] = 'grace_period';
                    $_SESSION['license_message'] = 'License verification failed. You are in a grace period until ' . date('Y-m-d H:i', $_SESSION['license_grace_period_end']) . '. Please check your connection or renew your license.';
                }
            } elseif (isset($licenseData['success']) && $licenseData['success'] === true) {
                $_SESSION['max_devices'] = $licenseData['max_devices'] ?? 0;
                
                // Determine can_add_device locally based on max_devices and current count
                $_SESSION['can_add_device'] = ($current_device_count < $_SESSION['max_devices']);
                
                $_SESSION['license_message'] = $licenseData['message'] ?? 'License validated successfully.';
                $_SESSION['license_status_code'] = 'active';
                $_SESSION['license_grace_period_end'] = null; // Clear grace period if license is active

                if (!$_SESSION['can_add_device']) {
                    $_SESSION['license_message'] = "License active, but you have reached your device limit ({$_SESSION['max_devices']} devices).";
                }
                setLastLicenseCheck(date('Y-m-d H:i:s')); // Update last check timestamp
            } else {
                // License verification failed (success is false)
                // Check the actual_status returned by the portal
                $actual_status = $licenseData['actual_status'] ?? 'expired'; // Default to expired if not specified

                if ($actual_status === 'revoked' || $actual_status === 'disabled' || $actual_status === 'in_use') { // 'in_use' is new
                    $_SESSION['license_message'] = $licenseData['message'] ?? 'Your license has been revoked or is in use by another server.';
                    $_SESSION['license_status_code'] = 'disabled';
                    $_SESSION['license_grace_period_end'] = null; // No grace period for revoked/in_use licenses
                    header('Location: license_expired.php'); // Redirect immediately
                    exit;
                } else {
                    // For 'expired' or other non-active statuses, proceed with grace period logic
                    $_SESSION['license_message'] = $licenseData['message'] ?? 'License validation failed.';
                    $_SESSION['license_status_code'] = 'expired';
                    if (!isset($_SESSION['license_grace_period_end']) || $_SESSION['license_grace_period_end'] === null) {
                        $_SESSION['license_grace_period_end'] = strtotime('+1 month');
                        $_SESSION['license_status_code'] = 'grace_period';
                        $_SESSION['license_message'] = 'Your license has expired. You are in a grace period until ' . date('Y-m-d H:i', $_SESSION['license_grace_period_end']) . '. Please renew your license.';
                    }
                }
            }
        }
    }
} catch (Exception $e) {
    error_log("License API Exception: " . $e->getMessage());
    $_SESSION['license_message'] = 'An unexpected error occurred during license validation.';
    $_SESSION['license_status_code'] = 'error';
    if (!isset($_SESSION['license_grace_period_end']) || $_SESSION['license_grace_period_end'] === null) {
        $_SESSION['license_grace_period_end'] = strtotime('+1 month');
        $_SESSION['license_status_code'] = 'grace_period';
        $_SESSION['license_message'] = 'License verification failed. You are in a grace period until ' . date('Y-m-d H:i', $_SESSION['license_grace_period_end']) . '. Please check your connection or renew your license.';
    }
}