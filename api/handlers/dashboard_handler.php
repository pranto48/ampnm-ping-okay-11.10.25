<?php
// This file is included by api.php and assumes $pdo, $action, and $input are available.
$current_user_id = $_SESSION['user_id'];

if ($action === 'get_dashboard_data') {
    $map_id = $_GET['map_id'] ?? null;
    if (!$map_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Map ID is required']);
        exit;
    }

    // Get detailed stats for each status
    $stmt = $pdo->prepare("
        SELECT
            COUNT(*) as total,
            SUM(CASE WHEN status = 'online' THEN 1 ELSE 0 END) as online,
            SUM(CASE WHEN status = 'warning' THEN 1 ELSE 0 END) as warning,
            SUM(CASE WHEN status = 'critical' THEN 1 ELSE 0 END) as critical,
            SUM(CASE WHEN status = 'offline' THEN 1 ELSE 0 END) as offline
        FROM devices WHERE map_id = ? AND user_id = ?
    ");
    $stmt->execute([$map_id, $current_user_id]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Ensure counts are integers, not null
    $stats['online'] = $stats['online'] ?? 0;
    $stats['warning'] = $stats['warning'] ?? 0;
    $stats['critical'] = $stats['critical'] ?? 0;
    $stats['offline'] = $stats['offline'] ?? 0;

    // Get devices
    $stmt = $pdo->prepare("SELECT name, ip, status FROM devices WHERE map_id = ? AND user_id = ? ORDER BY name ASC LIMIT 10");
    $stmt->execute([$map_id, $current_user_id]);
    $devices = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get history
    $stmt = $pdo->prepare("
        SELECT p.* 
        FROM ping_results p
        JOIN devices d ON p.host = d.ip
        WHERE d.map_id = ? AND d.user_id = ?
        ORDER BY p.created_at DESC 
        LIMIT 5
    ");
    $stmt->execute([$map_id, $current_user_id]);
    $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'stats' => $stats,
        'devices' => $devices,
        'history' => $history
    ]);
}