<?php
require_once 'includes/functions.php';

// Ensure customer is logged in
if (!isCustomerLoggedIn()) {
    http_response_code(403);
    echo "Unauthorized access.";
    exit;
}

$license_key = $_GET['license_key'] ?? null;

if (!$license_key) {
    http_response_code(400);
    echo "License key is required.";
    exit;
}

// Get content for Dockerfile and docker-compose.yml
$dockerfile_content = getDockerfileContent();
$docker_compose_content = getDockerComposeContent($license_key);

// Read docker-entrypoint.sh content
$entrypoint_script_path = __DIR__ . '/docker-ampnm/docker-entrypoint.sh';
if (!file_exists($entrypoint_script_path)) {
    http_response_code(500);
    echo "Error: docker-entrypoint.sh not found.";
    exit;
}
$entrypoint_content = file_get_contents($entrypoint_script_path);

// Create a temporary zip file
$zip_file_name = 'ampnm-docker-setup-' . date('YmdHis') . '.zip';
$zip_file_path = sys_get_temp_dir() . '/' . $zip_file_name;

$zip = new ZipArchive();
if ($zip->open($zip_file_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
    http_response_code(500);
    echo "Error: Could not create zip file.";
    exit;
}

// Add files to the zip archive
$zip->addFromString('Dockerfile', $dockerfile_content);
$zip->addFromString('docker-compose.yml', $docker_compose_content);
$zip->addFromString('docker-entrypoint.sh', $entrypoint_content);

$zip->close();

// Set headers for download
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $zip_file_name . '"');
header('Content-Length: ' . filesize($zip_file_path));
header('Pragma: no-cache');
header('Expires: 0');

// Output the zip file and delete the temporary file
readfile($zip_file_path);
unlink($zip_file_path);

exit;