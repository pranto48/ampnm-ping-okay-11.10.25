<?php
header('Content-Type: application/json');

// Include the license service's database configuration
require_once __DIR__ . '/config.php';

$input = json_decode(file_get_contents('php://input'), true);

$app_license_key = $input['app_license_key'] ?? null;
$user_id = $input['user_id'] ?? null; // This is the user ID from your AMPNM app's local MySQL
$current_device_count = $input['current_device_count'] ?? 0; // New: Receive current device count from AMPNM app

if (!$app_license_key || !$user_id) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing application license key or user ID.'
    ]);
    exit;
}

try {
    $pdo = getLicenseDbConnection();

    // 1. Fetch the license from MySQL
    $stmt = $pdo->prepare("SELECT * FROM `licenses` WHERE license_key = ?");
    $stmt->execute([$app_license_key]);
    $license = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$license) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid or expired application license key.'
        ]);
        exit;
    }

    // 2. Check license status and expiry
    if ($license['status'] !== 'active' && $license['status'] !== 'free') {
        echo json_encode([
            'success' => false,
            'message' => 'License is ' . $license['status'] . '.'
        ]);
        exit;
    }

    if ($license['expires_at'] && strtotime($license['expires_at']) < time()) {
        // Optionally update status to 'expired' in MySQL here
        $stmt = $pdo->prepare("UPDATE `licenses` SET status = 'expired', updated_at = NOW() WHERE id = ?");
        $stmt->execute([$license['id']]);
        echo json_encode([
            'success' => false,
            'message' => 'License has expired.'
        ]);
        exit;
    }

    // Update current_devices count in the license portal's database
    // This is crucial for the portal to keep track of device usage
    $stmt = $pdo->prepare("UPDATE `licenses` SET current_devices = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$current_device_count, $license['id']]);

    // Return max_devices to the AMPNM app, which will then calculate can_add_device
    echo json_encode([
        'success' => true,
        'message' => 'License is active.',
        'max_devices' => $license['max_devices'] ?? 1 // Provide max_devices to the AMPNM app
    ]);

} catch (Exception $e) {
    error_log("License verification error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An internal error occurred during license verification.'
    ]);
}
?>