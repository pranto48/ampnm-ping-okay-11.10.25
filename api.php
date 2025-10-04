<?php
require_once 'includes/functions.php';

header('Content-Type: application/json');

$pdo = getDbConnection();
$action = $_GET['action'] ?? '';
$input = json_decode(file_get_contents('php://input'), true) ?? [];

// Group actions by handler
$pingActions = ['manual_ping', 'scan_network', 'ping_device'];
$deviceActions = ['get_devices', 'create_device', 'update_device', 'delete_device', 'get_device_details', 'check_device', 'check_all_devices', 'ping_all_devices'];
$mapActions = ['get_maps', 'create_map', 'delete_map', 'get_edges', 'create_edge', 'update_edge', 'delete_edge', 'import_map'];

if (in_array($action, $pingActions)) {
    require __DIR__ . '/api/handlers/ping_handler.php';
} elseif (in_array($action, $deviceActions)) {
    require __DIR__ . '/api/handlers/device_handler.php';
} elseif (in_array($action, $mapActions)) {
    require __DIR__ . '/api/handlers/map_handler.php';
} elseif ($action === 'health') {
    echo json_encode(['status' => 'ok', 'timestamp' => date('c')]);
} else {
    http_response_code(404);
    echo json_encode(['error' => 'Invalid action']);
}