<?php
header('Content-Type: application/json');

// Include the license service's database configuration
require_once __DIR__ . '/config.php';

$input = json_decode(file_get_contents('php://input'), true);

$app_license_key = $input['app_license_key'] ?? null;
$user_id = $input['user_id'] ?? null; // This is the user ID from your AMPNM app's local MySQL

if (!$app_license_key || !$user_id) {
    echo json_encode([
        'success' => false,
        'can_add_device' => false,
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
            'can_add_device' => false,
            'message' => 'Invalid or expired application license key.'
        ]);
        exit;
    }

    // 2. Check license status and expiry
    if ($license['status'] !== 'active' && $license['status'] !== 'free') {
        echo json_encode([
            'success' => false,
            'can_add_device' => false,
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
            'can_add_device' => false,
            'message' => 'License has expired.'
        ]);
        exit;
    }

    // For this response, we'll just return the max_devices and let the AMPNM app enforce it.
    echo json_encode([
        'success' => true,
        'can_add_device' => true, // The AMPNM app will check its local count against max_devices
        'message' => 'License is active.',
        'max_devices' => $license['max_devices'] ?? 1 // Provide max_devices to the AMPNM app
    ]);

} catch (Exception $e) {
    error_log("License verification error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'can_add_device' => false,
        'message' => 'An internal error occurred during license verification.'
    ]);
}
?>