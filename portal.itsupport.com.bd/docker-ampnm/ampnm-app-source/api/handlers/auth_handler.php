<?php
// This file is included by api.php and assumes $pdo, $action, and $input are available.
// It handles authentication-related API calls.

switch ($action) {
    case 'get_license_status':
        // This information is already set in the session by auth_check.php
        echo json_encode([
            'can_add_device' => $_SESSION['can_add_device'] ?? false,
            'max_devices' => $_SESSION['max_devices'] ?? 0,
            'license_message' => $_SESSION['license_message'] ?? 'License status unknown.',
            'license_status_code' => $_SESSION['license_status_code'] ?? 'unknown',
            'license_grace_period_end' => $_SESSION['license_grace_period_end'] ?? null,
            'installation_id' => getInstallationId() // NEW: Return the installation ID
        ]);
        break;
    case 'force_license_recheck':
        // Clear the last_license_check timestamp to force an immediate re-verification
        setLastLicenseCheck(null);
        echo json_encode(['success' => true, 'message' => 'License re-check triggered.']);
        break;
}