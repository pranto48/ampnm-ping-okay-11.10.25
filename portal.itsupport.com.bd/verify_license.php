<?php
header('Content-Type: application/json');

// Supabase Configuration (replace with your actual Supabase project details)
// These should ideally be loaded from environment variables in a production CPanel setup
define('SUPABASE_URL', 'https://eauhhlfldgzxcwslqemu.supabase.co'); // Your Supabase Project URL
define('SUPABASE_ANON_KEY', 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6ImVhdWhobGZsZGd6eGN3c2xxZW11Iiwicm9sZSI6ImFub24iLCJpYXQiOjE3NTQ3MDg5NTgsImV4cCI6MjA3MDI4NDk1OH0.4JA8LlaE3cwUDSwbzO7NsWX09KZL75UQ7W2a3EN4XMA'); // Your Supabase Anon Key

// Function to make cURL requests to Supabase
function supabase_request($method, $path, $body = null) {
    $ch = curl_init(SUPABASE_URL . '/rest/v1/' . $path);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'apikey: ' . SUPABASE_ANON_KEY,
        'Authorization: Bearer ' . SUPABASE_ANON_KEY,
        'Content-Type: application/json',
        'Prefer: return=representation' // To get the updated row back
    ]);

    if ($body) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        throw new Exception("Supabase cURL Error: " . $curlError);
    }
    
    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Supabase JSON Parse Error: " . json_last_error_msg() . " - Response: " . $response);
    }

    if ($httpCode >= 400) {
        throw new Exception("Supabase API Error (HTTP {$httpCode}): " . json_encode($data));
    }

    return $data;
}

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
    // 1. Fetch the license from Supabase
    $licenses = supabase_request('GET', 'licenses?license_key=eq.' . urlencode($app_license_key) . '&select=*');

    if (empty($licenses)) {
        echo json_encode([
            'success' => false,
            'can_add_device' => false,
            'message' => 'Invalid or expired application license key.'
        ]);
        exit;
    }

    $license = $licenses[0];

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
        // Optionally update status to 'expired' in Supabase here
        supabase_request('PATCH', 'licenses?id=eq.' . $license['id'], ['status' => 'expired']);
        echo json_encode([
            'success' => false,
            'can_add_device' => false,
            'message' => 'License has expired.'
        ]);
        exit;
    }

    // 3. Get current device count for this user from AMPNM's local database (via another API call if needed, or assume AMPNM sends it)
    // For simplicity, we'll assume the AMPNM app sends its current device count for the user,
    // or we can make another API call to AMPNM to get it.
    // For now, let's assume the external service *manages* the current_devices count directly in Supabase.
    // This means the AMPNM app needs to tell the external service when a device is added/deleted.
    // This is a more complex interaction.

    // Let's simplify: The external service will just validate the APP_LICENSE_KEY and its max_devices.
    // The AMPNM app will manage its own device count and check against the max_devices returned here.
    // This means the `current_devices` column in Supabase's `licenses` table is not directly used by this `verify_license.php` for enforcement.
    // It's more for reporting/admin in the external system.

    // For now, we'll just return the max_devices and let the AMPNM app enforce it.
    // If you want the external service to *track* current_devices, you'd need a separate API endpoint
    // for AMPNM to call when devices are added/deleted.

    // For this response, we'll just return the max_devices and a boolean indicating if the license is valid.
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