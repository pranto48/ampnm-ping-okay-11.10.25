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

// --- License Validation Logic (Placeholder) ---
// In a real application, you would query your database (e.g., Supabase)
// to check if the license key is valid, active, not expired, etc.

// Example: Query Supabase for a license key
// For this example, we'll simulate a valid key 'YOUR_PRODUCT_LICENSE_KEY_HERE'
// and an invalid key 'INVALID_KEY'.
// You would replace this with actual database lookups.

$supabaseClient = getSupabaseClient();

try {
    // Example: Fetch license from a 'licenses' table in Supabase
    // This table would need to be created in your Supabase project.
    // It might have columns like 'key', 'is_active', 'expires_at'.
    $response = $supabaseClient->from('licenses')
                               ->select('*')
                               ->eq('key', $licenseKey)
                               ->limit(1)
                               ->single()
                               ->execute();

    $data = $response->data;

    if ($data && $data['is_active'] ?? false) {
        // Check expiration if applicable
        if (isset($data['expires_at']) && strtotime($data['expires_at']) < time()) {
            echo json_encode(['valid' => false, 'message' => 'License has expired.']);
        } else {
            echo json_encode(['valid' => true, 'message' => 'License is valid.']);
        }
    } else {
        echo json_encode(['valid' => false, 'message' => 'Invalid or inactive license key.']);
    }
} catch (Exception $e) {
    error_log("Supabase license validation error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['valid' => false, 'message' => 'License validation service error.']);
}

// --- End License Validation Logic ---
?>