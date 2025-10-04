<?php
require_once __DIR__ . '/../config.php';

// Function to execute ping command
function executePing($host, $count = 4) {
    // Basic validation and sanitization for the host
    if (empty($host) || !preg_match('/^[a-zA-Z0-9\.\-]+$/', $host)) {
        return ['output' => 'Invalid host provided.', 'return_code' => -1];
    }
    
    // Escape the host to prevent command injection
    $escaped_host = escapeshellarg($host);
    
    // Determine the correct ping command based on the OS
    if (stristr(PHP_OS, 'WIN')) {
        // Windows: -n for count
        $command = "ping -n $count $escaped_host";
    } else {
        // Linux/Mac: -c for count
        $command = "ping -c $count $escaped_host";
    }
    
    $output = shell_exec($command . ' 2>&1');
    $return_code = 0;
    
    // shell_exec doesn't provide a return code, so we use exec for that.
    // We redirect output to null to avoid printing it twice.
    exec($command . ' > /dev/null 2>&1', $exec_output, $return_code);
    
    return [
        'output' => $output,
        'return_code' => $return_code
    ];
}

// Function to parse ping output from different OS
function parsePingOutput($output) {
    $packetLoss = 100;
    $avgTime = 0;
    $minTime = 0;
    $maxTime = 0;
    
    // Regex for Windows
    if (preg_match('/Lost = \d+ \((\d+)% loss\)/', $output, $matches)) {
        $packetLoss = (int)$matches[1];
    }
    if (preg_match('/Minimum = (\d+)ms, Maximum = (\d+)ms, Average = (\d+)ms/', $output, $matches)) {
        $minTime = (float)$matches[1];
        $maxTime = (float)$matches[2];
        $avgTime = (float)$matches[3];
    }
    
    // Regex for Linux/Mac
    if (preg_match('/(\d+)% packet loss/', $output, $matches)) {
        $packetLoss = (int)$matches[1];
    }
    if (preg_match('/rtt min\/avg\/max\/mdev = ([\d.]+)\/([\d.]+)\/([\d.]+)\/([\d.]+) ms/', $output, $matches)) {
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

// Function to ping a single device and return structured data
function pingDevice($ip) {
    $pingResult = executePing($ip, 1); // Ping once for speed
    $parsedResult = parsePingOutput($pingResult['output']);
    $alive = ($pingResult['return_code'] === 0 && $parsedResult['packet_loss'] < 100);

    return [
        'ip' => $ip,
        'alive' => $alive,
        'time' => $alive ? $parsedResult['avg_time'] : null,
        'timestamp' => date('c'), // ISO 8601 format
        'error' => !$alive ? 'Host unreachable or timed out' : null
    ];
}

// Function to scan the network for devices using nmap
function scanNetwork($subnet) {
    // NOTE: This function requires 'nmap' to be installed on the server.
    // The web server user (e.g., www-data) may need permissions to run it.
    if (empty($subnet) || !preg_match('/^[a-zA-Z0-9\.\/]+$/', $subnet)) {
        // Default to a common local subnet if none is provided or if input is invalid
        $subnet = '192.168.1.0/24';
    }

    // Escape the subnet to prevent command injection
    $escaped_subnet = escapeshellarg($subnet);
    
    // Use nmap for a discovery scan (-sn: ping scan, -oG -: greppable output)
    $command = "nmap -sn $escaped_subnet -oG -";
    $output = @shell_exec($command);

    if (empty($output)) {
        return []; // nmap might not be installed or failed to run
    }

    $results = [];
    $lines = explode("\n", $output);
    foreach ($lines as $line) {
        if (strpos($line, 'Host:') === 0 && strpos($line, 'Status: Up') !== false) {
            $parts = preg_split('/\s+/', $line);
            $ip = $parts[1];
            $hostname = (isset($parts[2]) && $parts[2] !== '') ? trim($parts[2], '()') : null;
            
            $results[] = [
                'ip' => $ip,
                'hostname' => $hostname,
                'mac' => null, // nmap -sn doesn't always provide MAC, a privileged scan is needed
                'vendor' => null,
                'alive' => true
            ];
        }
    }
    return $results;
}

// Function to check if host is reachable via HTTP
function checkHttpConnectivity($host) {
    if (empty($host) || filter_var($host, FILTER_VALIDATE_IP) === false) {
        return ['success' => false, 'http_code' => 0, 'error' => 'Invalid IP address'];
    }
    $url = "http://$host";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_TIMEOUT, 2); // Reduced timeout for faster checks
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 2);
    
    curl_exec($ch);
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