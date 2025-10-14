<?php
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../includes/supabase_client.php';

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$licenseKey = $input['license_key'] ?? '';

if (empty($licenseKey)) {
    http_response_code(400);
    echo json_encode(['valid' => false, 'message' => 'License key is required.']);
    exit;
}

$supabaseClient = getSupabaseClient();

try {
    $response = $supabaseClient->from('licenses')
                               ->select('status, expires_at, max_devices, current_devices')
                               ->eq('license_key', $licenseKey)
                               ->limit(1)
                               ->single()
                               ->execute();

    $data = $response->data;

    if ($data) {
        $status = $data['status'] ?? 'unknown';
        $expiresAt = $data['expires_at'] ?? null;
        $maxDevices = $data['max_devices'] ?? 0;
        $currentDevices = $data['current_devices'] ?? 0;

        if ($status === 'active' || $status === 'free') {
            if ($expiresAt && strtotime($expiresAt) < time()) {
                // License has expired, update status in DB (handled by trigger, but good to check)
                echo json_encode(['valid' => false, 'message' => 'License has expired.']);
            } else {
                echo json_encode([
                    'valid' => true,
                    'message' => 'License is valid.',
                    'status' => $status,
                    'expires_at' => $expiresAt,
                    'max_devices' => $maxDevices,
                    'current_devices' => $currentDevices
                ]);
            }
        } else {
            echo json_encode(['valid' => false, 'message' => 'License is ' . $status . '.']);
        }
    } else {
        echo json_encode(['valid' => false, 'message' => 'Invalid license key.']);
    }
} catch (Exception $e) {
    error_log("Supabase license validation error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['valid' => false, 'message' => 'License validation service error.']);
}
?>