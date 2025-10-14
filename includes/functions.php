<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Function to send a notification email
function sendNotificationEmail($subject, $body) {
    $mail = new PHPMailer(true);
    try {
        // Server settings from environment variables
        $mail->isSMTP();
        $mail->Host       = getenv('SMTP_HOST');
        $mail->SMTPAuth   = true;
        $mail->Username   = getenv('SMTP_USER');
        $mail->Password   = getenv('SMTP_PASS');
        $mail->SMTPSecure = getenv('SMTP_SECURE') ?: PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = getenv('SMTP_PORT') ?: 587;

        // Recipients
        $mail->setFrom(getenv('SMTP_FROM_EMAIL'), getenv('SMTP_FROM_NAME') ?: 'AMPNM Notifier');
        $mail->addAddress(getenv('NOTIFICATION_EMAIL'));

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->AltBody = strip_tags($body);

        $mail->send();
        return ['success' => true, 'message' => 'Email has been sent'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => "Message could not be sent. Mailer Error: {$mail->ErrorInfo}"];
    }
}

// Function to check a TCP port on a host
function checkPortStatus($host, $port, $timeout = 1) {
    $startTime = microtime(true);
    // The '@' suppresses warnings on connection failure, which we handle ourselves.
    $socket = @fsockopen($host, $port, $errno, $errstr, $timeout);
    $endTime = microtime(true);

    if ($socket) {
        fclose($socket);
        return [
            'success' => true,
            'time' => round(($endTime - $startTime) * 1000, 2), // time in ms
            'output' => "Successfully connected to $host on port $port."
        ];
    } else {
        return [
            'success' => false,
            'time' => 0,
            'output' => "Connection failed: $errstr (Error no: $errno)"
        ];
    }
}

// Function to execute ping command more efficiently
function executePing($host, $count = 4) {
    // Basic validation and sanitization for the host
    if (empty($host) || !preg_match('/^[a-zA-Z0-9\.\-]+$/', $host)) {
        return ['output' => 'Invalid host provided.', 'return_code' => -1, 'success' => false];
    }
    
    // Escape the host to prevent command injection
    $escaped_host = escapeshellarg($host);
    
    // Determine the correct ping command based on the OS, with timeouts
    if (stristr(PHP_OS, 'WIN')) {
        // Windows: -n for count, -w for timeout in ms
        $command = "ping -n $count -w 1000 $escaped_host";
    } else {
        // Linux/Mac: -c for count, -W for timeout in seconds
        $command = "ping -c $count -W 1 $escaped_host";
    }
    
    $output_array = [];
    $return_code = -1;
    
    // Use exec to get both output and return code in one call
    @exec($command . ' 2>&1', $output_array, $return_code);
    
    $output = implode("\n", $output_array);
    
    // Determine success more reliably. Return code 0 is good, but we also check for 100% packet loss.
    $success = ($return_code === 0 && strpos($output, '100% packet loss') === false && strpos($output, 'Lost = ' . $count) === false);

    return [
        'output' => $output,
        'return_code' => $return_code,
        'success' => $success
    ];
}

// Function to parse ping output from different OS
function parsePingOutput($output) {
    $packetLoss = 100;
    $avgTime = 0;
    $minTime = 0;
    $maxTime = 0;
    $ttl = null;
    
    // Regex for Windows
    if (preg_match('/Lost = \d+ \((\d+)% loss\)/', $output, $matches)) {
        $packetLoss = (int)$matches[1];
    }
    if (preg_match('/Minimum = (\d+)ms, Maximum = (\d+)ms, Average = (\d+)ms/', $output, $matches)) {
        $minTime = (float)$matches[1];
        $maxTime = (float)$matches[2];
        $avgTime = (float)$matches[3];
    }
    if (preg_match('/TTL=(\d+)/', $output, $matches)) {
        $ttl = (int)$matches[1];
    }
    
    // Regex for Linux/Mac
    if (preg_match('/(\d+)% packet loss/', $output, $matches)) {
        $packetLoss = (int)$matches[1];
    }
    if (preg_match('/rtt min\/avg\/max\/mdev = ([\d.]+)\/([\d.]+)\/([\d.]+)\/([\d.]+) ms/', $output, $matches)) {
        $minTime = (float)$matches[1];
        $avgTime = (float)$matches[2];
        $maxTime = (float)$matches[3];
    }
    if (preg_match('/ttl=(\d+)/', $output, $matches)) {
        $ttl = (int)$matches[1];
    }
    
    return [
        'packet_loss' => $packetLoss,
        'avg_time' => $avgTime,
        'min_time' => $minTime,
        'max_time' => $maxTime,
        'ttl' => $ttl
    ];
}

// Function to save a ping result to the database
function savePingResult($pdo, $host, $pingResult) {
    $parsed = parsePingOutput($pingResult['output']);
    $success = $pingResult['success'];

    $sql = "INSERT INTO ping_results (host, packet_loss, avg_time, min_time, max_time, success, output) VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $host,
        $parsed['packet_loss'],
        $parsed['avg_time'],
        $parsed['min_time'],
        $parsed['max_time'],
        $success,
        $pingResult['output']
    ]);
}

// Function to ping a single device and return structured data
function pingDevice($ip) {
    $pingResult = executePing($ip, 1); // Ping once for speed
    $parsedResult = parsePingOutput($pingResult['output']);
    $alive = $pingResult['success'];

    return [
        'ip' => $ip,
        'alive' => $alive,
        'time' => $alive ? $parsedResult['avg_time'] : null,
        'timestamp' => date('c'), // ISO 8601 format
        'error' => !$alive ? 'Host unreachable or timed out' : null
    ];
}

// Function to scan the network for devices using nmap
function scanNetwork($subnet) {
    // NOTE: This function requires 'nmap' to be installed on the server.
    // The web server user (e.g., www-data) may need permissions to run it.
    if (empty($subnet) || !preg_match('/^[a-zA-Z0-9\.\/]+$/', $subnet)) {
        // Default to a common local subnet if none is provided or if input is invalid
        $subnet = '192.168.1.0/24';
    }

    // Escape the subnet to prevent command injection
    $escaped_subnet = escapeshellarg($subnet);
    
    // Use nmap for a discovery scan (-sn: ping scan, -oG -: greppable output)
    $command = "nmap -sn $escaped_subnet -oG -";
    $output = @shell_exec($command);

    if (empty($output)) {
        return []; // nmap might not be installed or failed to run
    }

    $results = [];
    $lines = explode("\n", $output);
    foreach ($lines as $line) {
        if (strpos($line, 'Host:') === 0 && strpos($line, 'Status: Up') !== false) {
            $parts = preg_split('/\s+/', $line);
            $ip = $parts[1];
            $hostname = (isset($parts[2]) && $parts[2] !== '') ? trim($parts[2], '()') : null;
            
            $results[] = [
                'ip' => $ip,
                'hostname' => $hostname,
                'mac' => null, // nmap -sn doesn't always provide MAC, a privileged scan is needed
                'vendor' => null,
                'alive' => true
            ];
        }
    }
    return $results;
}

// Function to check if host is reachable via HTTP
function checkHttpConnectivity($host) {
    if (empty($host) || filter_var($host, FILTER_VALIDATE_IP) === false) {
        return ['success' => false, 'http_code' => 0, 'error' => 'Invalid IP address'];
    }
    $url = "http://$host";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_TIMEOUT, 2); // Reduced timeout for faster checks
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 2);
    
    curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    return [
        'success' => ($httpCode >= 200 && $httpCode < 400),
        'http_code' => $httpCode,
        'error' => $error
    ];
}

// --- Device Status Checking Logic ---

function getStatusFromPingResult($device, $pingResult, $parsedResult, &$details) {
    if (!$pingResult['success']) {
        $details = 'Device offline or unreachable.';
        return 'offline';
    }

    $status = 'online';
    $details = "Online with {$parsedResult['avg_time']}ms latency.";

    if ($device['critical_latency_threshold'] && $parsedResult['avg_time'] > $device['critical_latency_threshold']) {
        $status = 'critical';
        $details = "Critical latency: {$parsedResult['avg_time']}ms (>{$device['critical_latency_threshold']}ms).";
    } elseif ($device['critical_packetloss_threshold'] && $parsedResult['packet_loss'] > $device['critical_packetloss_threshold']) {
        $status = 'critical';
        $details = "Critical packet loss: {$parsedResult['packet_loss']}% (>{$device['critical_packetloss_threshold']}%).";
    } elseif ($device['warning_latency_threshold'] && $parsedResult['avg_time'] > $device['warning_latency_threshold']) {
        $status = 'warning';
        $details = "Warning latency: {$parsedResult['avg_time']}ms (>{$device['warning_latency_threshold']}ms).";
    } elseif ($device['warning_packetloss_threshold'] && $parsedResult['packet_loss'] > $device['warning_packetloss_threshold']) {
        $status = 'warning';
        $details = "Warning packet loss: {$parsedResult['packet_loss']}% (>{$device['warning_packetloss_threshold']}%).";
    }
    return $status;
}

function logStatusChange($pdo, $deviceId, $oldStatus, $newStatus, $details) {
    if ($oldStatus !== $newStatus) {
        $stmt = $pdo->prepare("INSERT INTO device_status_logs (device_id, old_status, status, details) VALUES (?, ?, ?, ?)");
        $stmt->execute([$deviceId, $oldStatus, $newStatus, $details]);
    }
}

function triggerNotification($device, $oldStatus, $newStatus, $details) {
    if ($oldStatus !== $newStatus && $device['notifications_enabled']) {
        $alert_statuses = ['offline', 'warning', 'critical'];
        $is_recovery = $newStatus === 'online' && in_array($oldStatus, $alert_statuses);

        if (in_array($newStatus, $alert_statuses) || $is_recovery) {
            $subject = "Device Alert: " . $device['name'] . " is now " . strtoupper($newStatus);
            if ($is_recovery) {
                $subject = "Device Recovery: " . $device['name'] . " is back ONLINE";
            }
            
            $body = "<b>Device:</b> " . htmlspecialchars($device['name']) . "<br>";
            $body .= "<b>IP Address:</b> " . htmlspecialchars($device['ip']) . "<br>";
            $body .= "<b>New Status:</b> " . strtoupper($newStatus) . "<br>";
            $body .= "<b>Previous Status:</b> " . strtoupper($oldStatus) . "<br>";
            $body .= "<b>Details:</b> " . htmlspecialchars($details) . "<br>";
            $body .= "<b>Time:</b> " . date('Y-m-d H:i:s') . "<br>";

            sendNotificationEmail($subject, $body);
        }
    }
}

/**
 * Checks a single device's status, updates the database, logs changes, and sends notifications.
 *
 * @param PDO $pdo The database connection object.
 * @param array $device The device data as an associative array.
 * @return array An array containing the result: ['status_changed' => bool, 'new_status' => string, 'details' => string]
 */
function checkAndUpdateDeviceStatus($pdo, $device) {
    $old_status = $device['status'];
    $new_status = 'unknown';
    $last_avg_time = null;
    $last_ttl = null;
    $last_seen = $device['last_seen'];
    $check_output = 'Device has no IP configured for checking.';
    $details = '';

    if (!empty($device['ip'])) {
        if (!empty($device['check_port']) && is_numeric($device['check_port'])) {
            $portCheckResult = checkPortStatus($device['ip'], $device['check_port']);
            $new_status = $portCheckResult['success'] ? 'online' : 'offline';
            $last_avg_time = $portCheckResult['time'];
            $check_output = $portCheckResult['output'];
            $details = $portCheckResult['success'] ? "Port {$device['check_port']} is open." : "Port {$device['check_port']} is closed.";
        } else {
            $pingResult = executePing($device['ip'], 1);
            savePingResult($pdo, $device['ip'], $pingResult);
            $parsedResult = parsePingOutput($pingResult['output']);
            $new_status = getStatusFromPingResult($device, $pingResult, $parsedResult, $details);
            $last_avg_time = $parsedResult['avg_time'] ?? null;
            $last_ttl = $parsedResult['ttl'] ?? null;
            $check_output = $pingResult['output'];
        }
    }

    if ($new_status !== 'offline') {
        $last_seen = date('Y-m-d H:i:s');
    }

    $status_changed = ($old_status !== $new_status);

    if ($status_changed) {
        logStatusChange($pdo, $device['id'], $old_status, $new_status, $details);
        triggerNotification($device, $old_status, $new_status, $details);
    }

    $updateStmt = $pdo->prepare("UPDATE devices SET status = ?, last_seen = ?, last_avg_time = ?, last_ttl = ? WHERE id = ?");
    $updateStmt->execute([$new_status, $last_seen, $last_avg_time, $last_ttl, $device['id']]);

    return [
        'status_changed' => $status_changed,
        'new_status' => $new_status,
        'details' => $details,
        'last_avg_time' => $last_avg_time,
        'last_ttl' => $last_ttl,
        'last_ping_output' => $check_output,
        'last_seen' => $last_seen
    ];
}