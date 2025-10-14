<?php
require_once __DIR__ . '/../config.php';

// Function to validate the license key against an external API
function validateLicense($licenseKey) {
    // Skip validation if LICENSE_API_URL is not set or is a placeholder
    if (!defined('LICENSE_API_URL') || LICENSE_API_URL === 'http://localhost:8000/api/v1/validate-license') {
        error_log("DEBUG: License API URL is not configured. Skipping external license validation.");
        return ['status' => 'unconfigured', 'message' => 'License API not configured.'];
    }

    $ch = curl_init(LICENSE_API_URL);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['license_key' => $licenseKey]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5); // 5-second timeout for the API call

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        error_log("ERROR: License API call failed: " . $curlError);
        return ['status' => 'error', 'message' => 'Failed to connect to license server.'];
    }

    if ($httpCode !== 200) {
        error_log("ERROR: License API returned HTTP status " . $httpCode . ": " . $response);
        return ['status' => 'error', 'message' => 'License server error.'];
    }

    $data = json_decode($response, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("ERROR: Invalid JSON response from License API: " . $response);
        return ['status' => 'error', 'message' => 'Invalid response from license server.'];
    }

    // Expected API response: {'valid': true/false, 'message': '...' }
    if (isset($data['valid']) && $data['valid'] === true) {
        return ['status' => 'valid', 'message' => $data['message'] ?? 'License is valid.'];
    } else {
        return ['status' => 'invalid', 'message' => $data['message'] ?? 'License is invalid or expired.'];
    }
}

// Perform license check and store in session
if (!isset($_SESSION['license_status']) || !isset($_SESSION['license_last_checked']) || (time() - $_SESSION['license_last_checked'] > 3600)) { // Check every hour
    $licenseResult = validateLicense(LICENSE_KEY);
    $_SESSION['license_status'] = $licenseResult['status'];
    $_SESSION['license_message'] = $licenseResult['message'];
    $_SESSION['license_last_checked'] = time();
    error_log("DEBUG: License check performed. Status: " . $_SESSION['license_status'] . ", Message: " . $_SESSION['license_message']);
}
?>