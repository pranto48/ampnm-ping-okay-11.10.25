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
            $id = $input['id'] ?? null;
            if (!$id) { http_response_code(400); echo json_encode(['error' => 'Edge ID is required']); exit; }
            $stmt = $pdo->prepare("DELETE FROM device_edges WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true]);
        }
        break;
}