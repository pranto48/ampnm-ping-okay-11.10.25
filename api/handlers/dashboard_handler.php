<?php
// This file is included by api.php and assumes $pdo, $action, and $input are available.

if ($action === 'get_dashboard_data') {
    $map_id = $_GET['map_id'] ?? null;
    if (!$map_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Map ID is required']);
        exit;
    }

    // Get stats
    $stmt = $pdo->prepare("SELECT COUNT(*) as total, SUM(CASE WHEN status = 'online' THEN 1 ELSE 0 END) as online FROM devices WHERE map_id = ?");
    $stmt->execute([$map_id]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['online'] = $stats['online'] ?? 0; // Handle case with no online devices

    // Get devices
    $stmt = $pdo->prepare("SELECT name, ip, status FROM devices WHERE map_id = ? ORDER BY name ASC LIMIT 10");
    $stmt->execute([$map_id]);
    $devices = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get history
    $stmt = $pdo->prepare("
        SELECT p.* 
        FROM ping_results p
        JOIN devices d ON p.host = d.ip
        WHERE d.map_id = ?
        ORDER BY p.created_at DESC 
        LIMIT 5
    ");
    $stmt->execute([$map_id]);
    $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'stats' => $stats,
        'devices' => $devices,
        'history' => $history
    ]);
}