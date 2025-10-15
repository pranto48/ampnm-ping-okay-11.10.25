<?php
// This file is included by api.php and assumes $pdo, $action, and $input are available.
// It handles authentication-related API calls.

switch ($action) {
    case 'get_license_status':
        // This information is already set in the session by auth_check.php
        echo json_encode([
            'can_add_device' => $_SESSION['can_add_device'] ?? false,
            'max_devices' => $_SESSION['max_devices'] ?? 0,
            'license_message' => $_SESSION['license_message'] ?? 'License status unknown.'
        ]);
        break;
    // Add other auth-related actions here in the future
}