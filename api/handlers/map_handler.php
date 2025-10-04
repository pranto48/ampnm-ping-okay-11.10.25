<?php
// This file is included by api.php and assumes $pdo, $action, and $input are available.

switch ($action) {
    case 'get_maps':
        $stmt = $pdo->prepare("SELECT m.id, m.name, m.type, m.updated_at as lastModified, (SELECT COUNT(*) FROM devices WHERE map_id = m.id) as deviceCount FROM maps m ORDER BY m.created_at ASC");
        $stmt->execute();
        $maps = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($maps);
        break;

    case 'create_map':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
            $id = $input['id'] ?? null;
            if (!$id) { http_response_code(400); echo json_encode(['error' => 'Map ID is required']); exit; }
            $stmt = $pdo->prepare("DELETE FROM maps WHERE id = ?"); $stmt->execute([$id]);
            echo json_encode(['success' => true, 'message' => 'Map deleted successfully']);
        }
        break;
        
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
            $sql = "INSERT INTO device_edges (source_id, target_id, map_id, connection_type) VALUES (?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$input['source_id'], $input['target_id'], $input['map_id'], $input['connection_type'] ?? 'cat5']);
            $lastId = $pdo->lastInsertId();
            $stmt = $pdo->prepare("SELECT * FROM device_edges WHERE id = ?");
            $stmt->execute([$lastId]);
            $edge = $stmt->fetch(PDO::FETCH_ASSOC);
            echo json_encode($edge);
        }
        break;

    case 'update_edge':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id = $input['id'] ?? null;
            $connection_type = $input['connection_type'] ?? 'cat5';
            if (!$id) { http_response_code(400); echo json_encode(['error' => 'Edge ID is required']); exit; }
            $stmt = $pdo->prepare("UPDATE device_edges SET connection_type = ? WHERE id = ?");
            $stmt->execute([$connection_type, $id]);
            $stmt = $pdo->prepare("SELECT * FROM device_edges WHERE id = ?");
            $stmt->execute([$id]);
            $edge = $stmt->fetch(PDO::FETCH_ASSOC);
            echo json_encode($edge);
        }
        break;

    case 'delete_edge':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id = $input['id'] ?? null;
            if (!$id) { http_response_code(400); echo json_encode(['error' => 'Edge ID is required']); exit; }
            $stmt = $pdo->prepare("DELETE FROM device_edges WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true]);
        }
        break;
    
    case 'import_map':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $map_id = $input['map_id'] ?? null;
            $devices = $input['devices'] ?? [];
            $edges = $input['edges'] ?? [];
            if (!$map_id) { http_response_code(400); echo json_encode(['error' => 'Map ID is required']); exit; }

            try {
                $pdo->beginTransaction();
                // Delete old data
                $stmt = $pdo->prepare("DELETE FROM device_edges WHERE map_id = ?"); $stmt->execute([$map_id]);
                $stmt = $pdo->prepare("DELETE FROM devices WHERE map_id = ?"); $stmt->execute([$map_id]);

                // Insert new devices
                $device_id_map = [];
                $sql = "INSERT INTO devices (name, ip, type, x, y, map_id, ping_interval, icon_size, name_text_size) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                foreach ($devices as $device) {
                    $stmt->execute([
                        $device['name'], $device['ip'], $device['type'], $device['x'], $device['y'], $map_id,
                        $device['ping_interval'] ?? null, $device['icon_size'] ?? 50, $device['name_text_size'] ?? 14
                    ]);
                    $new_id = $pdo->lastInsertId();
                    $device_id_map[$device['id']] = $new_id;
                }

                // Insert new edges
                $sql = "INSERT INTO device_edges (source_id, target_id, map_id, connection_type) VALUES (?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                foreach ($edges as $edge) {
                    $new_source_id = $device_id_map[$edge['from']] ?? null;
                    $new_target_id = $device_id_map[$edge['to']] ?? null;
                    if ($new_source_id && $new_target_id) {
                        $stmt->execute([$new_source_id, $new_target_id, $map_id, $edge['connection_type'] ?? 'cat5']);
                    }
                }
                $pdo->commit();
                echo json_encode(['success' => true, 'message' => 'Map imported successfully.']);
            } catch (Exception $e) {
                $pdo->rollBack();
                http_response_code(500);
                echo json_encode(['error' => 'Import failed: ' . $e->getMessage()]);
            }
        }
        break;
}