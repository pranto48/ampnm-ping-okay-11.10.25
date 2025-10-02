<?php
// Database configuration
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'network_monitor');

// Create database connection
function getDbConnection() {
    try {
        $pdo = new PDO("mysql:host=" . DB_SERVER . ";dbname=" . DB_NAME, DB_USERNAME, DB_PASSWORD);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch(PDOException $e) {
        die("ERROR: Could not connect. " . $e->getMessage());
    }
}

// Function to execute ping command
function executePing($host, $count = 4) {
    // Escape the host to prevent command injection
    $host = escapeshellarg($host);
    
    // Execute ping command
    $command = "ping -n $count $host"; // Windows
    if (stristr(PHP_OS, 'LINUX') || stristr(PHP_OS, 'DARWIN')) {
        $command = "ping -c $count $host"; // Linux/Mac
    }
    
    $output = shell_exec($command . ' 2>&1');
    $return_code = 0;
    
    // Try to get the return code
    exec($command . ' > /dev/null 2>&1', $null, $return_code);
    
    return [
        'output' => $output,
        'return_code' => $return_code
    ];
}

// Function to parse ping output
function parsePingOutput($output) {
    $packetLoss = 100;
    $avgTime = 0;
    $minTime = 0;
    $maxTime = 0;
    
    // Parse packet loss (Windows)
    if (preg_match('/Lost = (\d+)%/', $output, $matches)) {
        $packetLoss = (int)$matches[1];
    }
    // Parse packet loss (Linux/Mac)
    else if (preg_match('/(\d+)% packet loss/', $output, $matches)) {
        $packetLoss = (int)$matches[1];
    }
    
    // Parse times (Windows)
    if (preg_match('/Minimum = (\d+)ms, Maximum = (\d+)ms, Average = (\d+)ms/', $output, $matches)) {
        $minTime = (float)$matches[1];
        $maxTime = (float)$matches[2];
        $avgTime = (float)$matches[3];
    }
    // Parse times (Linux/Mac)
    else if (preg_match('/= ([\d.]+)\/([\d.]+)\/([\d.]+)\/([\d.]+) ms/', $output, $matches)) {
        $minTime = (float)$matches[1];
        $avgTime = (float)$matches[2];
        $maxTime = (float)$matches[3];
    }
    
    return [
        'packet_loss' => $packetLoss,
        'avg_time' => $avgTime,
        'min_time' => $minTime,
        'max_time' => $maxTime
    ];
}

// Function to check if host is reachable via HTTP
function checkHttpConnectivity($host) {
    $url = "http://$host";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
    
    $data = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    return [
        'success' => ($httpCode >= 200 && $httpCode < 400),
        'http_code' => $httpCode,
        'error' => $error
    ];
}
?>