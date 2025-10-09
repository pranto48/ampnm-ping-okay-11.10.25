<?php
// This file is included by api.php and assumes $pdo, $action, and $input are available.
$current_user_id = $_SESSION['user_id'];

function getStatusFromPingResult($device, $pingResult, $parsedResult) {
    $isAlive = ($pingResult['return_code'] === 0 && $parsedResult['packet_loss'] < 100);
    if (!$isAlive) {
        return 'offline';
    }

    $status = 'online'; // Default to online if alive
    if ($device['critical_latency_threshold'] && $parsedResult['avg_time'] > $device['critical_latency_threshold']) {
        $status = 'critical';
    } elseif ($device['critical_packetloss_threshold'] && $parsedResult['packet_loss'] > $device['critical_packetloss_threshold']) {
        $status = 'critical';
    } elseif ($device['warning_latency_threshold'] && $parsedResult['avg_time'] > $device['warning_latency_threshold']) {
        $status = 'warning';
    } elseif ($device['warning_packetloss_threshold'] && $parsedResult['packet_loss'] > $device['warning_packetloss_threshold']) {
        $status = 'warning';
    }
    return $status;
}

switch ($action) {
    case 'ping_all_devices':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $map_id = $input['map_id'] ?? null;
            if (!$map_id) { http_response_code(400); echo json_encode(['error' => 'Map ID is required']); exit; }

            $stmt = $pdo->prepare("SELECT * FROM devices WHERE enabled = TRUE AND map_id = ? AND user_id = ? AND ip IS NOT NULL");
            $stmt->execute([$map_id, $current_user_id]);
            $devices = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $status_changes = [];

            foreach ($devices as $device) {
                $old_status = $device['status'];
                $pingResult = executePing($device['ip'], 1);
                savePingResult($pdo, $device['ip'], $pingResult['output'], $pingResult['return_code']);
                $parsedResult = parsePingOutput($pingResult['output']);
                $new_status = getStatusFromPingResult($device, $pingResult, $parsedResult);
                
                if ($new_status !== $old_status) {
                    $status_changes[] = [
                        'name' => $device['name'],
                        'old_status' => $old_status,
                        'new_status' => $new_status
                    ];
                }

                $last_avg_time = $parsedResult['avg_time'] ?? null;
                $last_ttl = $parsedResult['ttl'] ?? null;
                
                $updateStmt = $pdo->prepare("UPDATE devices SET status = ?, last_seen = ?, last_avg_time = ?, last_ttl = ? WHERE id = ? AND user_id = ?");
                $updateStmt->execute([$new_status, ($new_status !== 'offline') ? date('Y-m-d H:i:s') : $device['last_seen'], $last_avg_time, $last_ttl, $device['id'], $current_user_id]);
            }
            
            echo json_encode([
                'success' => true, 
                'message' => 'All enabled devices have been pinged.', 
                'count' => count($devices),
                'status_changes' => $status_changes
            ]);
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
            if (!$device['ip']) { echo json_encode(['id' => $deviceId, 'status' => 'unknown', 'last_seen' => $device['last_seen']]); exit; }

            $pingResult = executePing($device['ip'], 1);
            savePingResult($pdo, $device['ip'], $pingResult['output'], $pingResult['return_code']);
            $parsedResult = parsePingOutput($pingResult['output']);
            $status = getStatusFromPingResult($device, $pingResult, $parsedResult);
            $last_seen = ($status !== 'offline') ? date('Y-m-d H:i:s') : $device['last_seen'];
            $last_avg_time = $parsedResult['avg_time'] ?? null;
            $last_ttl = $parsedResult['ttl'] ?? null;
            
            $stmt = $pdo->prepare("UPDATE devices SET status = ?, last_seen = ?, last_avg_time = ?, last_ttl = ? WHERE id = ? AND user_id = ?");
            $stmt->execute([$status, $last_seen, $last_avg_time, $last_ttl, $deviceId, $current_user_id]);
            
            echo json_encode(['id' => $deviceId, 'status' => $status, 'last_seen' => $last_seen, 'last_avg_time' => $last_avg_time, 'last_ttl' => $last_ttl]);
        }
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
        $sql = "SELECT d.*, m.name as map_name FROM devices d JOIN maps m ON d.map_id = m.id WHERE d.user_id = ?";
        $params = [$current_user_id];
        if ($map_id) { $sql .= " AND d.map_id = ?"; $params[] = $map_id; }
        $sql .= " ORDER BY d.created_at ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $devices = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($devices);
        break;

    case 'create_device':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $sql = "INSERT INTO devices (user_id, name, ip, type, map_id, x, y, ping_interval, icon_size, name_text_size, warning_latency_threshold, warning_packetloss_threshold, critical_latency_threshold, critical_packetloss_threshold, show_live_ping) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $current_user_id, $input['name'], $input['ip'] ?? null, $input['type'], $input['map_id'],
                $input['x'] ?? null, $input['y'] ?? null,
                $input['ping_interval'] ?? null, $input['icon_size'] ?? 50, $input['name_text_size'] ?? 14,
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
            $allowed_fields = ['name', 'ip', 'type', 'x', 'y', 'ping_interval', 'icon_size', 'name_text_size', 'warning_latency_threshold', 'warning_packetloss_threshold', 'critical_latency_threshold', 'critical_packetloss_threshold', 'show_live_ping'];
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
}