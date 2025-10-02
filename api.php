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

    // --- Device Management Endpoints ---
    case 'get_devices':
        $map_id = $_GET['map_id'] ?? null;
        $sql = "SELECT * FROM devices";
        $params = [];
        if ($map_id) {
            $sql .= " WHERE map_id = ?";
            $params[] = $map_id;
        }
        $sql .= " ORDER BY created_at ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $devices = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($devices);
        break;

    case 'create_device':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $input = json_decode(file_get_contents('php://input'), true);
            $sql = "INSERT INTO devices (name, ip, type, location, description, enabled, x, y, map_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $input['name'], $input['ip'], $input['type'],
                $input['location'] ?? null, $input['description'] ?? null,
                $input['enabled'] ?? true, $input['x'] ?? 0, $input['y'] ?? 0,
                $input['map_id'] ?? null
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
            $allowed_fields = ['name', 'ip', 'type', 'location', 'description', 'enabled', 'x', 'y', 'map_id'];
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
            $name = $input['name'] ?? ''; $type = $input['type'] ?? '';
            if (empty($name) || empty($type)) { http_response_code(400); echo json_encode(['error' => 'Name and type are required']); exit; }
            $stmt = $pdo->prepare("INSERT INTO maps (name, type) VALUES (?, ?)"); $stmt->execute([$name, $type]);
            $lastId = $pdo->lastInsertId();
            $stmt = $pdo->prepare("SELECT id, name, type, updated_at as lastModified, 0 as deviceCount FROM maps WHERE id = ?"); $stmt->execute([$lastId]);
            $map = $stmt->fetch(PDO::FETCH_ASSOC); echo json_encode($map);
        }
        break;

    case 'update_map':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $input = json_decode(file_get_contents('php://input'), true);
            $id = $input['id'] ?? null; $updates = $input['updates'] ?? [];
            if (!$id || empty($updates)) { http_response_code(400); echo json_encode(['error' => 'Map ID and updates are required']); exit; }
            $allowed_fields = ['name', 'type', 'description', 'is_default'];
            $fields = []; $params = [];
            foreach ($updates as $key => $value) { if (in_array($key, $allowed_fields)) { $fields[] = "$key = ?"; $params[] = $value; } }
            if (empty($fields)) { http_response_code(400); echo json_encode(['error' => 'No valid fields to update']); exit; }
            $params[] = $id;
            $sql = "UPDATE maps SET " . implode(', ', $fields) . " WHERE id = ?"; $stmt = $pdo->prepare($sql); $stmt->execute($params);
            $stmt = $pdo->prepare("SELECT m.id, m.name, m.type, m.updated_at as lastModified, (SELECT COUNT(*) FROM devices WHERE map_id = m.id) as deviceCount FROM maps m WHERE m.id = ?"); $stmt->execute([$id]);
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
        
    default:
        http_response_code(404);
        echo json_encode(['error' => 'Invalid action']);
        break;
}
?>