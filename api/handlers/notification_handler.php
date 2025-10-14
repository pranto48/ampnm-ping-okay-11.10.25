<?php
// This file is included by api.php and assumes $pdo, $action, and $input are available.
$current_user_id = $_SESSION['user_id'];

switch ($action) {
    case 'get_notification_settings':
        $stmt = $pdo->prepare("SELECT id, name, ip, notifications_enabled FROM devices WHERE user_id = ? ORDER BY name ASC");
        $stmt->execute([$current_user_id]);
        $devices = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($devices);
        break;

    case 'update_notification_settings':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id = $input['id'] ?? null;
            $enabled = $input['enabled'] ?? false;

            if (!$id) {
                http_response_code(400);
                echo json_encode(['error' => 'Device ID is required.']);
                exit;
            }

            $stmt = $pdo->prepare("UPDATE devices SET notifications_enabled = ? WHERE id = ? AND user_id = ?");
            $stmt->execute([$enabled ? 1 : 0, $id, $current_user_id]);
            
            echo json_encode(['success' => true]);
        }
        break;

    case 'test_smtp_settings':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $email = $input['email'] ?? null;
            if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                http_response_code(400);
                echo json_encode(['error' => 'A valid email address is required.']);
                exit;
            }
            
            // Temporarily override the NOTIFICATION_EMAIL for the test
            putenv("NOTIFICATION_EMAIL=$email");

            $subject = "Test Email from AMPNM";
            $body = "This is a test email to confirm your SMTP settings are configured correctly.<br>If you received this, you are ready to receive device status alerts.";
            
            $result = sendNotificationEmail($subject, $body);
            echo json_encode($result);
        }
        break;
}