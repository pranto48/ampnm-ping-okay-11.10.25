<?php
// Set a long execution time for the script, as it may check many devices.
set_time_limit(300); // 5 minutes

// Bootstrap the application environment
require_once __DIR__ . '/../includes/bootstrap.php';

// This script is intended for command-line execution by a cron job.
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('This script can only be run from the command line.');
}

echo "--- Starting global device check at " . date('Y-m-d H:i:s') . " ---\n";

$pdo = getDbConnection();

// Get all users to check devices for each of them.
$userStmt = $pdo->query("SELECT id, username FROM users");
$users = $userStmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($users)) {
    echo "No users found. Exiting.\n";
    exit;
}

$total_checked = 0;
$total_changes = 0;

foreach ($users as $user) {
    $current_user_id = $user['id'];
    echo "Checking devices for user '{$user['username']}' (ID: $current_user_id)...\n";

    // Select all enabled, pingable devices for the current user
    $stmt = $pdo->prepare("SELECT * FROM devices WHERE enabled = TRUE AND user_id = ? AND ip IS NOT NULL AND ip != '' AND type != 'box'");
    $stmt->execute([$current_user_id]);
    $devices = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($devices)) {
        echo "No devices to check for this user.\n";
        continue;
    }

    $user_checked_count = 0;
    $user_status_changes = 0;

    foreach ($devices as $device) {
        echo "  - Checking {$device['name']} ({$device['ip']})... ";
        $result = checkAndUpdateDeviceStatus($pdo, $device);
        if ($result['status_changed']) {
            echo "Status changed to {$result['new_status']}.\n";
            $user_status_changes++;
        } else {
            echo "Status stable ({$result['new_status']}).\n";
        }
        $user_checked_count++;
    }
    
    echo "Finished for user '{$user['username']}'. Checked: $user_checked_count, Changes: $user_status_changes\n";
    $total_checked += $user_checked_count;
    $total_changes += $user_status_changes;
}

echo "--- Global device check finished. Total checked: $total_checked, Total changes: $total_changes ---\n\n";