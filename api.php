<?php
require_once 'config.php';

header('Content-Type: application/json');

$pdo = getDbConnection();
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'ping':
        // ... existing ping code
        break;
        
    case 'check_device':
        // ... existing check_device code
        break;
        
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
                $input['name'],
                $input['ip'],
                $input['type'],
                $input['location'] ?? null,
                $input['description'] ?? null,
                $input['enabled'] ?? true,
                $input['x'] ?? 0,
                $input['y'] ?? 0,
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

            if (!$id || empty($updates)) {
                http_response_code(400);
                echo json_encode(['error' => 'Device ID and updates are required']);
                exit;
            }

            $allowed_fields = ['name', 'ip', 'type', 'location', 'description', 'enabled', 'x', 'y', 'map_id'];
            $fields = [];
            $params = [];

            foreach ($updates as $key => $value) {
                if (in_array($key, $allowed_fields)) {
                    $fields[] = "$key = ?";
                    $params[] = $value;
                }
            }

            if (empty($fields)) {
                http_response_code(400);
                echo json_encode(['error' => 'No valid fields to update']);
                exit;
            }

            $params[] = $id;
            $sql = "UPDATE devices SET " . implode(', ', $fields) . " WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            $stmt = $pdo->prepare("SELECT * FROM devices WHERE id = ?");
            $stmt->execute([$id]);
            $device = $stmt->fetch(PDO::FETCH_ASSOC);
            echo json_encode($device);
        }
        break;

    case 'delete_device':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $input = json_decode(file_get_contents('php://input'), true);
            $id = $input['id'] ?? null;

            if (!$id) {
                http_response_code(400);
                echo json_encode(['error' => 'Device ID is required']);
                exit;
            }

            $stmt = $pdo->prepare("DELETE FROM devices WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true, 'message' => 'Device deleted successfully']);
        }
        break;
        
    case 'get_history':
        // ... existing history code
        break;

    case 'get_maps':
        // ... existing maps code
        break;

    case 'create_map':
        // ... existing maps code
        break;

    case 'update_map':
        // ... existing maps code
        break;

    case 'delete_map':
        // ... existing maps code
        break;
        
    default:
        echo json_encode(['error' => 'Invalid action']);
        break;
}
?>