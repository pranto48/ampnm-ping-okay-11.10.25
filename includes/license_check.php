<?php
// This file provides functions to check a user's license status.

/**
 * Retrieves the active license for a given user ID.
 *
 * @param PDO $pdo The PDO database connection.
 * @param int $userId The ID of the user.
 * @return array|false The license data if found and active, otherwise false.
 */
function getUserLicense(PDO $pdo, int $userId) {
    $stmt = $pdo->prepare("SELECT * FROM licenses WHERE user_id = ? AND status = 'active' ORDER BY expires_at DESC LIMIT 1");
    $stmt->execute([$userId]);
    $license = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($license) {
        // Check if the license has expired
        if ($license['expires_at'] && strtotime($license['expires_at']) < time()) {
            // Update status to expired in DB
            $updateStmt = $pdo->prepare("UPDATE licenses SET status = 'expired', updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $updateStmt->execute([$license['id']]);
            return false; // License is expired
        }
        return $license;
    }
    return false;
}

/**
 * Checks if a user has an active license and if they can add more devices.
 *
 * @param PDO $pdo The PDO database connection.
 * @param int $userId The ID of the user.
 * @return array An associative array with 'can_add_device' (boolean) and 'message' (string).
 */
function checkDeviceAllowance(PDO $pdo, int $userId) {
    $license = getUserLicense($pdo, $userId);

    if (!$license) {
        return ['can_add_device' => false, 'message' => 'No active license found.'];
    }

    $maxDevices = $license['max_devices'];
    $currentDevices = $license['current_devices'];

    if ($currentDevices < $maxDevices) {
        return ['can_add_device' => true, 'message' => ''];
    } else {
        return ['can_add_device' => false, 'message' => "You have reached your device limit of {$maxDevices}."];
    }
}

/**
 * Updates the current_devices count for a user's active license.
 *
 * @param PDO $pdo The PDO database connection.
 * @param int $userId The ID of the user.
 * @return bool True on success, false on failure.
 */
function updateLicenseDeviceCount(PDO $pdo, int $userId) {
    $license = getUserLicense($pdo, $userId);
    if (!$license) {
        return false;
    }

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM devices WHERE user_id = ?");
    $stmt->execute([$userId]);
    $deviceCount = $stmt->fetchColumn();

    $updateStmt = $pdo->prepare("UPDATE licenses SET current_devices = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
    return $updateStmt->execute([$deviceCount, $license['id']]);
}
?>