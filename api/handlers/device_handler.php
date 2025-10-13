<?php
// This file is included by api.php and assumes $pdo, $action, and $input are available.
$current_user_id = $_SESSION['user_id'];

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
        $stmt = $pdo->prepare("INSERT INTO device_status_logs (device_id, status, details) VALUES (?, ?, ?)");
        $stmt->execute([$deviceId, $newStatus, $details]);
    }
}

switch ($action) {
    case 'ping_all_devices':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $map_id = $input['map_id'] ?? null;
            if (!$map_id) { http_response_code(400); echo json_encode(['error' => 'Map ID is required']); exit; }

            $stmt = $pdo->prepare("SELECT * FROM devices WHERE enabled = TRUE AND map_id = ? AND user_id = ? AND ip IS NOT NULL AND type != 'box'");
            $stmt->execute([$map_id, $current_user_id]);
            $devices = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $updated_devices = [];

            foreach ($devices as $device) {
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
                
                if ($new_status !== 'offline') { $last_seen = date('Y-m-d H:i:s'); }
                
                logStatusChange($pdo, $device['id'], $old_status, $new_status, $details);
                $updateStmt = $pdo->prepare("UPDATE devices SET status = ?, last_seen = ?, last_avg_time = ?, last_ttl = ? WHERE id = ? AND user_id = ?");
                $updateStmt->execute([$new_status, $last_seen, $last_avg_time, $last_ttl, $device['id'], $current_user_id]);

                $updated_devices[] = [
                    'id' => $device['id'],
                    'name' => $device['name'],
                    'old_status' => $old_status,
                    'status' => $new_status,
                    'last_seen' => $last_seen,
                    'last_avg_time' => $last_avg_time,
                    'last_ttl' => $last_ttl,
                    'last_ping_output' => $check_output
                ];
            }
            
            echo json_encode(['success' => true, 'updated_devices' => $updated_devices]);
        }
        break;

    case 'check_device':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $deviceId = $input['id'] ?? 0;
            if (!$deviceId) { http_response_code(400); echo json_encode(['error' => 'Device ID is required']); exit; }
            
            $stmt = $pdo->prepare("SELECT * FROM devices WHERE id = ? AND user_id = ?");
            $stmt->execute([$deviceId, $current_user_id]);
            $device = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$device) { http_response_code(404); echo json_encode(['error' => 'Device not found']); exit; }

            $old_status = $device['status'];
            $status = 'unknown';
            $last_seen = $device['last_seen'];
            $last_avg_time = null;
            $last_ttl = null;
            $check_output = 'Device has no IP configured for checking.';
            $details = '';

            if (!empty($device['ip'])) {
                if (!empty($device['check_port']) && is_numeric($device['check_port'])) {
                    $portCheckResult = checkPortStatus($device['ip'], $device['check_port']);
                    $status = $portCheckResult['success'] ? 'online' : 'offline';
                    $last_avg_time = $portCheckResult['time'];
                    $check_output = $portCheckResult['output'];
                    $details = $portCheckResult['success'] ? "Port {$device['check_port']} is open." : "Port {$device['check_port']} is closed.";
                } else {
                    $pingResult = executePing($device['ip'], 1);
                    savePingResult($pdo, $device['ip'], $pingResult);
                    $parsedResult = parsePingOutput($pingResult['output']);
                    $status = getStatusFromPingResult($device, $pingResult, $parsedResult, $details);
                    $last_avg_time = $parsedResult['avg_time'] ?? null;
                    $last_ttl = $parsedResult['ttl'] ?? null;
                    $check_output = $pingResult['output'];
                }
            }
            
            if ($status !== 'offline') { $last_seen = date('Y-m-d H:i:s'); }
            
            logStatusChange($pdo, $deviceId, $old_status, $status, $details);
            $stmt = $pdo->prepare("UPDATE devices SET status = ?, last_seen = ?, last_avg_time = ?, last_ttl = ? WHERE id = ? AND user_id = ?");
            $stmt->execute([$status, $last_seen, $last_avg_time, $last_ttl, $deviceId, $current_user_id]);
            
            echo json_encode(['id' => $deviceId, 'status' => $status, 'last_seen' => $last_seen, 'last_avg_time' => $last_avg_time, 'last_ttl' => $last_ttl, 'last_ping_output' => $check_output]);
        }
        break;

    case 'get_device_uptime':
        $deviceId = $_GET['id'] ?? 0;
        if (!$deviceId) { http_response_code(400); echo json_encode(['error' => 'Device ID is required']); exit; }
        
        $stmt = $pdo->prepare("SELECT ip FROM devices WHERE id = ? AND user_id = ?");
        $stmt->execute([$deviceId, $current_user_id]);
        $device = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$device || !$device['ip']) {
            echo json_encode(['uptime_24h' => null, 'uptime_7d' => null, 'outages_24h' => null]);
            exit;
        }
        $host = $device['ip'];

        $stmt = $pdo->prepare("SELECT COUNT(*) as total, SUM(success) as successful FROM ping_results WHERE host = ? AND created_at >= NOW() - INTERVAL 24 HOUR");
        $stmt->execute([$host]);
        $stats24h = $stmt->fetch(PDO::FETCH_ASSOC);
        $uptime24h = ($stats24h['total'] > 0) ? round(($stats24h['successful'] / $stats24h['total']) * 100, 2) : null;
        $outages24h = $stats24h['total'] - $stats24h['successful'];

        $stmt = $pdo->prepare("SELECT COUNT(*) as total, SUM(success) as successful FROM ping_results WHERE host = ? AND created_at >= NOW() - INTERVAL 7 DAY");
        $stmt->execute([$host]);
        $stats7d = $stmt->fetch(PDO::FETCH_ASSOC);
        $uptime7d = ($stats7d['total'] > 0) ? round(($stats7d['successful'] / $stats7d['total']) * 100, 2) : null;

        echo json_encode(['uptime_24h' => $uptime24h, 'uptime_7d' => $uptime7d, 'outages_24h' => $outages24h]);
        break;

    case 'get_device_details':
        $deviceId = $_GET['id'] ?? 0;
        if (!$deviceId) { http_response_code(400); echo json_encode(['error' => 'Device ID is required']); exit; }
        $stmt = $pdo->prepare("SELECT d.*, m.name as map_name FROM devices d JOIN maps m ON d.map_id = m.id WHERE d.id = ? AND d.user_id = ?");
        $stmt->execute([$deviceId, $current_user_id]);
        $device = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$device) { http_response_code(404); echo json_encode(['error' => 'Device not found']); exit; }
        $history = [];
        if ($device['ip']) {
            $stmt = $pdo->prepare("SELECT * FROM ping_results WHERE host = ? ORDER BY created_at DESC LIMIT 20");
            $stmt->execute([$device['ip']]);
            $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        echo json_encode(['device' => $device, 'history' => $history]);
        break;

    case 'get_devices':
        $map_id = $_GET['map_id'] ?? null;
        $sql = "
            SELECT 
                d.*, 
                m.name as map_name,
                p.output as last_ping_output
            FROM 
                devices d
            JOIN 
                maps m ON d.map_id = m.id
            LEFT JOIN 
                ping_results p ON p.id = (
                    SELECT id 
                    FROM ping_results 
                    WHERE host = d.ip 
                    ORDER BY created_at DESC 
                    LIMIT 1
                )
            WHERE d.user_id = ?
        ";
        $params = [$current_user_id];
        if ($map_id) { 
            $sql .= " AND d.map_id = ?"; 
            $params[] = $map_id; 
        }
        $sql .= " ORDER BY d.created_at ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $devices = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($devices);
        break;

    case 'create_device':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $sql = "INSERT INTO devices (user_id, name, ip, check_port, type, map_id, x, y, ping_interval, icon_size, name_text_size, icon_url, warning_latency_threshold, warning_packetloss_threshold, critical_latency_threshold, critical_packetloss_threshold, show_live_ping) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $current_user_id, $input['name'], $input['ip'] ?? null, $input['check_port'] ?? null, $input['type'], $input['map_id'],
                $input['x'] ?? null, $input['y'] ?? null,
                $input['ping_interval'] ?? null, $input['icon_size'] ?? 50, $input['name_text_size'] ?? 14, $input['icon_url'] ?? null,
                $input['warning_latency_threshold'] ?? null, $input['warning_packetloss_threshold'] ?? null,
                $input['critical_latency_threshold'] ?? null, $input['critical_packetloss_threshold'] ?? null,
                ($input['show_live_ping'] ?? false) ? 1 : 0
            ]);
            $lastId = $pdo->lastInsertId();
            $stmt = $pdo->prepare("SELECT * FROM devices WHERE id = ? AND user_id = ?");
            $stmt->execute([$lastId, $current_user_id]);
            $device = $stmt->fetch(PDO::FETCH_ASSOC);
            echo json_encode($device);
        }
        break;

    case 'update_device':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id = $input['id'] ?? null;
            $updates = $input['updates'] ?? [];
            if (!$id || empty($updates)) { http_response_code(400); echo json_encode(['error' => 'Device ID and updates are required']); exit; }
            $allowed_fields = ['name', 'ip', 'check_port', 'type', 'x', 'y', 'ping_interval', 'icon_size', 'name_text_size', 'icon_url', 'warning_latency_threshold', 'warning_packetloss_threshold', 'critical_latency_threshold', 'critical_packetloss_threshold', 'show_live_ping'];
            $fields = []; $params = [];
            foreach ($updates as $key => $value) {
                if (in_array($key, $allowed_fields)) {
                    $fields[] = "$key = ?";
                    if ($key === 'show_live_ping') {
                        $params[] = $value ? 1 : 0;
                    } else {
                        $params[] = ($value === '' || is_null($value)) ? null : $value;
                    }
                }
            }
            if (empty($fields)) { http_response_code(400); echo json_encode(['error' => 'No valid fields to update']); exit; }
            $params[] = $id; $params[] = $current_user_id;
            $sql = "UPDATE devices SET " . implode(', ', $fields) . " WHERE id = ? AND user_id = ?";
            $stmt = $pdo->prepare($sql); $stmt->execute($params);
            $stmt = $pdo->prepare("SELECT * FROM devices WHERE id = ? AND user_id = ?"); $stmt->execute([$id, $current_user_id]);
            $device = $stmt->fetch(PDO::FETCH_ASSOC); echo json_encode($device);
        }
        break;

    case 'delete_device':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id = $input['id'] ?? null;
            if (!$id) { http_response_code(400); echo json_encode(['error' => 'Device ID is required']); exit; }
            $stmt = $pdo->prepare("DELETE FROM devices WHERE id = ? AND user_id = ?"); $stmt->execute([$id, $current_user_id]);
            echo json_encode(['success' => true, 'message' => 'Device deleted successfully']);
        }
        break;

    case 'upload_device_icon':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $deviceId = $_POST['id'] ?? null;
            if (!$deviceId || !isset($_FILES['iconFile'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Device ID and icon file are required.']);
                exit;
            }
    
            $stmt = $pdo->prepare("SELECT id FROM devices WHERE id = ? AND user_id = ?");
            $stmt->execute([$deviceId, $current_user_id]);
            if (!$stmt->fetch()) {
                http_response_code(404);
                echo json_encode(['error' => 'Device not found or access denied.']);
                exit;
            }
    
            $uploadDir = __DIR__ . '/../../uploads/icons/';
            if (!is_dir($uploadDir)) {
                if (!mkdir($uploadDir, 0755, true)) {
                    http_response_code(500);
                    echo json_encode(['error' => 'Failed to create upload directory.']);
                    exit;
                }
            }
    
            $file = $_FILES['iconFile'];
            if ($file['error'] !== UPLOAD_ERR_OK) {
                http_response_code(500);
                echo json_encode(['error' => 'File upload error code: ' . $file['error']]);
                exit;
            }
    
            $fileInfo = new SplFileInfo($file['name']);
            $extension = strtolower($fileInfo->getExtension());
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp'];
            if (!in_array($extension, $allowedExtensions)) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid file type.']);
                exit;
            }

            $newFileName = 'device_' . $deviceId . '_' . time() . '.' . $extension;
            $uploadPath = $uploadDir . $newFileName;
            $urlPath = 'uploads/icons/' . $newFileName;
    
            if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
                $stmt = $pdo->prepare("UPDATE devices SET icon_url = ? WHERE id = ? AND user_id = ?");
                $stmt->execute([$urlPath, $deviceId, $current_user_id]);
                echo json_encode(['success' => true, 'url' => $urlPath]);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to save uploaded file.']);
            }
        }
        break;
}