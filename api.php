<?php
require_once 'config.php';

header('Content-Type: application/json');

$pdo = getDbConnection();
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'ping':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $input = json_decode(file_get_contents('php://input'), true);
            $host = $input['host'] ?? '';
            $count = $input['count'] ?? 4;
            
            if (empty($host)) {
                echo json_encode(['error' => 'Host is required']);
                exit;
            }
            
            // Execute ping
            $pingResult = executePing($host, $count);
            $parsedResult = parsePingOutput($pingResult['output']);
            
            $success = ($pingResult['return_code'] === 0 || $parsedResult['packet_loss'] < 100);
            
            // Store in database
            $stmt = $pdo->prepare("INSERT INTO ping_results (host, packet_loss, avg_time, min_time, max_time, success, output) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $host,
                $parsedResult['packet_loss'],
                $parsedResult['avg_time'],
                $parsedResult['min_time'],
                $parsedResult['max_time'],
                $success ? 1 : 0,
                $pingResult['output']
            ]);
            
            echo json_encode([
                'host' => $host,
                'packet_loss' => $parsedResult['packet_loss'],
                'avg_time' => $parsedResult['avg_time'],
                'min_time' => $parsedResult['min_time'],
                'max_time' => $parsedResult['max_time'],
                'success' => $success,
                'output' => $pingResult['output'],
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        }
        break;
        
    case 'check_device':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $input = json_decode(file_get_contents('php://input'), true);
            $deviceId = $input['id'] ?? 0;
            
            if (!$deviceId) {
                echo json_encode(['error' => 'Device ID is required']);
                exit;
            }
            
            // Get device info
            $stmt = $pdo->prepare("SELECT * FROM devices WHERE id = ?");
            $stmt->execute([$deviceId]);
            $device = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$device) {
                echo json_encode(['error' => 'Device not found']);
                exit;
            }
            
            // Check device status
            $pingResult = executePing($device['ip'], 1);
            $parsedResult = parsePingOutput($pingResult['output']);
            
            $status = 'offline';
            if ($pingResult['return_code'] === 0 || $parsedResult['packet_loss'] < 100) {
                $status = 'online';
            } else {
                // Try HTTP as fallback
                $httpResult = checkHttpConnectivity($device['ip']);
                if ($httpResult['success']) {
                    $status = 'online';
                }
            }
            
            // Update device status
            $stmt = $pdo->prepare("UPDATE devices SET status = ?, last_seen = ? WHERE id = ?");
            $stmt->execute([$status, ($status === 'online') ? date('Y-m-d H:i:s') : $device['last_seen'], $deviceId]);
            
            echo json_encode([
                'id' => $deviceId,
                'status' => $status,
                'last_seen' => ($status === 'online') ? date('Y-m-d H:i:s') : $device['last_seen']
            ]);
        }
        break;
        
    case 'get_devices':
        $stmt = $pdo->prepare("SELECT * FROM devices ORDER BY ip");
        $stmt->execute();
        $devices = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($devices);
        break;
        
    case 'get_history':
        $limit = $_GET['limit'] ?? 20;
        $host = $_GET['host'] ?? '';
        
        $sql = "SELECT * FROM ping_results ";
        $params = [];
        
        if ($host) {
            $sql .= "WHERE host = ? ";
            $params[] = $host;
        }
        
        $sql .= "ORDER BY created_at DESC LIMIT ?";
        $params[] = (int)$limit;
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($history);
        break;

    case 'get_maps':
        $stmt = $pdo->prepare("
            SELECT 
                m.id, 
                m.name, 
                m.type, 
                m.updated_at as lastModified,
                (SELECT COUNT(*) FROM devices WHERE map_id = m.id) as deviceCount
            FROM maps m 
            ORDER BY m.created_at ASC
        ");
        $stmt->execute();
        $maps = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($maps);
        break;

    case 'create_map':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $input = json_decode(file_get_contents('php://input'), true);
            $name = $input['name'] ?? '';
            $type = $input['type'] ?? '';

            if (empty($name) || empty($type)) {
                http_response_code(400);
                echo json_encode(['error' => 'Name and type are required']);
                exit;
            }

            $stmt = $pdo->prepare("INSERT INTO maps (name, type) VALUES (?, ?)");
            $stmt->execute([$name, $type]);
            $lastId = $pdo->lastInsertId();

            $stmt = $pdo->prepare("
                SELECT id, name, type, updated_at as lastModified, 0 as deviceCount 
                FROM maps WHERE id = ?
            ");
            $stmt->execute([$lastId]);
            $map = $stmt->fetch(PDO::FETCH_ASSOC);
            echo json_encode($map);
        }
        break;

    case 'update_map':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $input = json_decode(file_get_contents('php://input'), true);
            $id = $input['id'] ?? null;
            $updates = $input['updates'] ?? [];

            if (!$id || empty($updates)) {
                http_response_code(400);
                echo json_encode(['error' => 'Map ID and updates are required']);
                exit;
            }

            $allowed_fields = ['name', 'type', 'description', 'is_default'];
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
            $sql = "UPDATE maps SET " . implode(', ', $fields) . " WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            $stmt = $pdo->prepare("
                SELECT 
                    m.id, 
                    m.name, 
                    m.type, 
                    m.updated_at as lastModified,
                    (SELECT COUNT(*) FROM devices WHERE map_id = m.id) as deviceCount
                FROM maps m 
                WHERE m.id = ?
            ");
            $stmt->execute([$id]);
            $map = $stmt->fetch(PDO::FETCH_ASSOC);
            echo json_encode($map);
        }
        break;

    case 'delete_map':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $input = json_decode(file_get_contents('php://input'), true);
            $id = $input['id'] ?? null;

            if (!$id) {
                http_response_code(400);
                echo json_encode(['error' => 'Map ID is required']);
                exit;
            }

            $stmt = $pdo->prepare("DELETE FROM maps WHERE id = ?");
            $stmt->execute([$id]);

            echo json_encode(['success' => true, 'message' => 'Map deleted successfully']);
        }
        break;
        
    default:
        echo json_encode(['error' => 'Invalid action']);
        break;
}
?>