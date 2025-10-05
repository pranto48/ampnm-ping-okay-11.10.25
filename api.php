<?php
require_once 'includes/functions.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$pdo = getDbConnection();
$action = $_GET['action'] ?? '';
$input = json_decode(file_get_contents('php://input'), true) ?? [];

if ($action === 'manual_ping') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $host = $input['host'] ?? '';
        if (empty($host)) {
            http_response_code(400);
            echo json_encode(['error' => 'Host is required']);
            exit;
        }
        $result = executePing($host);
        savePingResult($pdo, $host, $result['output'], $result['return_code']);
        echo json_encode($result);
    }
} else {
    http_response_code(404);
    echo json_encode(['error' => 'Invalid action']);
}
?>