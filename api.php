<?php
require_once 'config.php';

header('Content-Type: application/json');

$pdo = getDbConnection();
$action = $_GET['action'] ?? '';

switch ($action) {
    // --- Ping Service Endpoints ---
    case 'health':
        echo json_encode(['status' => 'ok', 'timestamp' => date('c')]);
        break;

    case 'ping_device':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $input = json_decode(file_get_contents('php://input'), true);
            $ip = $input['ip'] ?? '';
            if (empty($ip)) {
                http_response_code(400);
                echo json_encode(['error' => 'IP address is required']);
                exit;
            }
            $result = pingDevice($ip);
            echo json_encode($result);
        }
        break;

    case 'ping_all_devices':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $stmt = $pdo->prepare("SELECT id, ip, status, last_seen FROM devices WHERE enabled = TRUE");
            $stmt->execute();
            $devices = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($devices as $device) {
                $pingData = pingDevice($device['ip']);
                $status = $pingData['alive'] ? 'online' : 'offline';
                
                $updateStmt = $pdo->prepare("UPDATE devices SET status = ?, last_seen = ? WHERE id = ?");
                $updateStmt->execute([$status, ($status === 'online') ? date('Y-m-d H:i:s') : $device['last_seen'], $device['id']]);
            }
            
            echo json_encode(['success' => true, 'message' => 'All enabled devices have been pinged.', 'count' => count($devices)]);
        }
        break;

    case 'scan_network':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $input = json_decode(file_get_contents('php://input'), true);
            $subnet = $input['subnet'] ?? ''; // e.g., '192.168.1.0/24'
            
            $devices = scanNetwork($subnet);
            echo json_encode(['devices' => $devices]);
        }
        break;

    case 'check_device':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $input = json_decode(file_get_contents('php://input'), true);
            $deviceId = $input['id'] ?? 0;
            
            if (!$deviceId) {
                http_response_code(400);
                echo json_encode(['error' => 'Device ID is required']);
                exit;
            }
            
            $stmt = $pdo->prepare("SELECT * FROM devices WHERE id = ?");
            $stmt->execute([$deviceId]);
            $device = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$device) {
                http_response_code(404);
                echo json_encode(['error' => 'Device not found']);
                exit;
            }
            
            $pingResult = executePing($device['ip'], 1);
            $parsedResult = parsePingOutput($pingResult['output']);
            
            $status = 'offline';
            if ($pingResult['return_code'] === 0 || $parsedResult['packet_loss'] < 100) {
                $status = 'online';
            } else {
                $httpResult = checkHttpConnectivity($device['ip']);
                if ($httpResult['success']) {
                    $status = 'online';
                }
            }
            
            $last_seen = ($status === 'online') ? date('Y-m-d H:i:s') : $device['last_seen'];
            
            $stmt = $pdo->prepare("UPDATE devices SET status = ?, last_seen = ? WHERE id = ?");
            $stmt->execute([$status, $last_seen, $deviceId]);
            
            echo json_encode([
                'id' => $deviceId,
                'status' => $status,
                'last_seen' => $last_seen
            ]);
        }
        break;

    case 'get_device_details':
        $deviceId = $_GET['id'] ?? 0;
        if (!$deviceId) {
            http_response_code(400);
            echo json_encode(['error' => 'Device ID is required']);
            exit;
        }
        $stmt = $pdo->prepare("SELECT * FROM devices WHERE id = ?");
        $stmt->execute([$deviceId]);
        $device = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$device) {
            http_response_code(404);
            echo json_encode(['error' => 'Device not found']);
            exit;
        }
        $stmt = $pdo->prepare("SELECT * FROM ping_results WHERE host = ? ORDER BY created_at DESC LIMIT 20");
        $stmt->execute([$device['ip']]);
        $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['device' => $device, 'history' => $history]);
        break;

    // --- Device Management Endpoints ---
    case 'get_devices':
        $map_id = $_GET['map_id'] ?? null;
        if (!$map_id) { http_response_code(400); echo json_encode(['error' => 'Map ID is required']); exit; }
        $sql = "SELECT * FROM devices WHERE map_id = ? ORDER BY created_at ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$map_id]);
        $devices = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($devices);
        break;

    case 'create_device':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $input = json_decode(file_get_contents('php://input'), true);
            $sql = "INSERT INTO devices (name, ip, type, description, enabled, x, y, map_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $input['name'], $input['ip'], $input['type'],
                $input['description'] ?? null, $input['enabled'] ?? true, 
                $input['x'] ?? rand(50, 800), $input['y'] ?? rand(50, 500),
                $input['map_id']
            ]);
            $lastId = $pdo->lastInsertId();
            $stmt = $pdo->prepare("SELECT * FROM devices WHERE id = ?");
            $stmt->execute([$lastId]);
            $device = $stmt->fetch(PDO::FETCH_ASSOC);
            echo json_encode($device);
        }
        break;

    case 'update_device':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $input = json_decode(file_get_contents('php://input'), true);
            $id = $input['id'] ?? null;
            $updates = $input['updates'] ?? [];
            if (!$id || empty($updates)) { http_response_code(400); echo json_encode(['error' => 'Device ID and updates are required']); exit; }
            $allowed_fields = ['name', 'ip', 'type', 'description', 'enabled', 'x', 'y'];
            $fields = []; $params = [];
            foreach ($updates as $key => $value) { if (in_array($key, $allowed_fields)) { $fields[] = "$key = ?"; $params[] = $value; } }
            if (empty($fields)) { http_response_code(400); echo json_encode(['error' => 'No valid fields to update']); exit; }
            $params[] = $id;
            $sql = "UPDATE devices SET " . implode(', ', $fields) . " WHERE id = ?";
            $stmt = $pdo->prepare($sql); $stmt->execute($params);
            $stmt = $pdo->prepare("SELECT * FROM devices WHERE id = ?"); $stmt->execute([$id]);
            $device = $stmt->fetch(PDO::FETCH_ASSOC); echo json_encode($device);
        }
        break;

    case 'delete_device':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $input = json_decode(file_get_contents('php://input'), true);
            $id = $input['id'] ?? null;
            if (!$id) { http_response_code(400); echo json_encode(['error' => 'Device ID is required']); exit; }
            $stmt = $pdo->prepare("DELETE FROM devices WHERE id = ?"); $stmt->execute([$id]);
            echo json_encode(['success' => true, 'message' => 'Device deleted successfully']);
        }
        break;

    // --- Map Management Endpoints ---
    case 'get_maps':
        $stmt = $pdo->prepare("SELECT m.id, m.name, m.type, m.updated_at as lastModified, (SELECT COUNT(*) FROM devices WHERE map_id = m.id) as deviceCount FROM maps m ORDER BY m.created_at ASC");
        $stmt->execute();
        $maps = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($maps);
        break;

    case 'create_map':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $input = json_decode(file_get_contents('php://input'), true);
            $name = $input['name'] ?? ''; $type = $input['type'] ?? 'lan';
            if (empty($name)) { http_response_code(400); echo json_encode(['error' => 'Name is required']); exit; }
            $stmt = $pdo->prepare("INSERT INTO maps (name, type) VALUES (?, ?)"); $stmt->execute([$name, $type]);
            $lastId = $pdo->lastInsertId();
            $stmt = $pdo->prepare("SELECT id, name, type, updated_at as lastModified, 0 as deviceCount FROM maps WHERE id = ?"); $stmt->execute([$lastId]);
            $map = $stmt->fetch(PDO::FETCH_ASSOC); echo json_encode($map);
        }
        break;

    case 'delete_map':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $input = json_decode(file_get_contents('php://input'), true);
            $id = $input['id'] ?? null;
            if (!$id) { http_response_code(400); echo json_encode(['error' => 'Map ID is required']); exit; }
            $stmt = $pdo->prepare("DELETE FROM maps WHERE id = ?"); $stmt->execute([$id]);
            echo json_encode(['success' => true, 'message' => 'Map deleted successfully']);
        }
        break;
        
    // --- Edge Management Endpoints ---
    case 'get_edges':
        $map_id = $_GET['map_id'] ?? null;
        if (!$map_id) { http_response_code(400); echo json_encode(['error' => 'Map ID is required']); exit; }
        $stmt = $pdo->prepare("SELECT * FROM device_edges WHERE map_id = ?");
        $stmt->execute([$map_id]);
        $edges = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($edges);
        break;

    case 'create_edge':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $input = json_decode(file_get_contents('php://input'), true);
            $sql = "INSERT INTO device_edges (source_id, target_id, map_id) VALUES (?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$input['source_id'], $input['target_id'], $input['map_id']]);
            $lastId = $pdo->lastInsertId();
            $stmt = $pdo->prepare("SELECT * FROM device_edges WHERE id = ?");
            $stmt->execute([$lastId]);
            $edge = $stmt->fetch(PDO::FETCH_ASSOC);
            echo json_encode($edge);
        }
        break;

    case 'delete_edge':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $input = json_decode(file_get_contents('php://input'), true);
            $id = $input['id'] ?? null;
            if (!$id) { http_response_code(400); echo json_encode(['error' => 'Edge ID is required']); exit; }
            $stmt = $pdo->prepare("DELETE FROM device_edges WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true]);
        }
        break;

    default:
        http_response_code(404);
        echo json_encode(['error' => 'Invalid action']);
        break;
}
?>