<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Local Network Monitor</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        * {
            font-family: 'Inter', sans-serif;
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <div class="container mx-auto px-4 py-8">
        <?php
        // Database connection
        $servername = "localhost";
        $username = "root";
        $password = "";
        $dbname = "network_monitor";
        
        $conn = new mysqli($servername, $username, $password, $dbname);
        
        // Create database and table if they don't exist
        if ($conn->connect_error) {
            $conn = new mysqli($servername, $username, $password);
            if ($conn->connect_error) {
                die("Connection failed: " . $conn->connect_error);
            }
            
            // Create database
            $conn->query("CREATE DATABASE IF NOT EXISTS $dbname");
            $conn->select_db($dbname);
            
            // Create table
            $sql = "CREATE TABLE IF NOT EXISTS ping_results (
                id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                host VARCHAR(100) NOT NULL,
                packet_loss INT(3) NOT NULL,
                avg_time DECIMAL(10,2) NOT NULL,
                min_time DECIMAL(10,2) NOT NULL,
                max_time DECIMAL(10,2) NOT NULL,
                success BOOLEAN NOT NULL,
                output TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )";
            
            if (!$conn->query($sql)) {
                die("Error creating table: " . $conn->error);
            }
        }
        
        // Handle form submissions
        $pingResults = [];
        $networkStatus = true;
        $lastChecked = date('Y-m-d H:i:s');
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (isset($_POST['ping_host'])) {
                $host = $_POST['ping_host'];
                $count = $_POST['ping_count'] ?? 4;
                
                // Perform ping using system command
                $command = "ping -c $count " . escapeshellarg($host);
                $output = shell_exec($command);
                
                // Parse ping results
                $packetLoss = 0;
                $avgTime = 0;
                $minTime = 0;
                $maxTime = 0;
                $success = false;
                
                if ($output) {
                    // Parse packet loss
                    if (preg_match('/(\d+)% packet loss/', $output, $matches)) {
                        $packetLoss = (int)$matches[1];
                    }
                    
                    // Parse times
                    if (preg_match('/= ([\d.]+)\/([\d.]+)\/([\d.]+)\/([\d.]+) ms/', $output, $matches)) {
                        $minTime = (float)$matches[1];
                        $avgTime = (float)$matches[2];
                        $maxTime = (float)$matches[3];
                    }
                    
                    $success = $packetLoss < 100;
                    
                    // Store in database
                    $stmt = $conn->prepare("INSERT INTO ping_results (host, packet_loss, avg_time, min_time, max_time, success, output) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("sidddis", $host, $packetLoss, $avgTime, $minTime, $maxTime, $success, $output);
                    $stmt->execute();
                    
                    $pingResults[] = [
                        'host' => $host,
                        'packet_loss' => $packetLoss,
                        'avg_time' => $avgTime,
                        'min_time' => $minTime,
                        'max_time' => $maxTime,
                        'success' => $success,
                        'output' => $output,
                        'timestamp' => date('Y-m-d H:i:s')
                    ];
                }
            }
        }
        
        // Get ping history
        $history = [];
        $result = $conn->query("SELECT * FROM ping_results ORDER BY created_at DESC LIMIT 10");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $history[] = $row;
            }
        }
        ?>
        
        <div class="flex items-center justify-between mb-6">
            <div class="flex items-center gap-3">
                <svg class="h-8 w-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                </svg>
                <h1 class="text-3xl font-bold text-gray-800">Local Network Monitor</h1>
            </div>
            <span class="px-3 py-1 bg-green-100 text-green-800 rounded-full text-sm font-medium">
                Internet Online
            </span>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-medium text-gray-600">Internet Status</h3>
                    <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                    </svg>
                </div>
                <div class="text-2xl font-bold text-green-600 mt-2">Online</div>
                <p class="text-xs text-gray-500 mt-1">Internet connectivity</p>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-medium text-gray-600">Last Check</h3>
                    <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <div class="text-2xl font-bold text-gray-800 mt-2"><?php echo date('H:i:s'); ?></div>
                <p class="text-xs text-gray-500 mt-1">Last status check</p>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-medium text-gray-600">Local Devices</h3>
                    <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2"></path>
                    </svg>
                </div>
                <div class="text-2xl font-bold text-gray-800 mt-2">4/4</div>
                <p class="text-xs text-gray-500 mt-1">Devices online</p>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6 mb-8">
            <h2 class="text-xl font-semibold text-gray-800 mb-4">Ping Test</h2>
            <form method="POST" class="flex gap-4 mb-6">
                <input type="text" name="ping_host" placeholder="Enter hostname or IP (e.g., 192.168.1.1)" 
                       value="192.168.9.3" class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                <input type="number" name="ping_count" value="4" min="1" max="10" 
                       class="w-20 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 focus:ring-2 focus:ring-blue-500">
                    Ping
                </button>
            </form>

            <?php if (!empty($pingResults)): ?>
            <div class="space-y-4">
                <h3 class="text-lg font-medium text-gray-800">Latest Ping Result</h3>
                <?php foreach ($pingResults as $result): ?>
                <div class="border rounded-lg p-4">
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center gap-3">
                            <svg class="h-6 w-6 <?php echo $result['success'] ? 'text-green-500' : 'text-red-500'; ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.141 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0"></path>
                            </svg>
                            <span class="font-mono font-medium"><?php echo htmlspecialchars($result['host']); ?></span>
                        </div>
                        <span class="px-3 py-1 <?php echo $result['success'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?> rounded-full text-sm font-medium">
                            <?php echo $result['success'] ? 'Success' : 'Failed'; ?>
                        </span>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Packet Loss:</span>
                            <span class="<?php echo $result['packet_loss'] > 0 ? 'text-orange-600' : 'text-green-600'; ?> font-medium">
                                <?php echo $result['packet_loss']; ?>%
                            </span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Avg Time:</span>
                            <span class="font-medium"><?php echo $result['avg_time']; ?>ms</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Min Time:</span>
                            <span class="font-medium"><?php echo $result['min_time']; ?>ms</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Max Time:</span>
                            <span class="font-medium"><?php echo $result['max_time']; ?>ms</span>
                        </div>
                    </div>
                    
                    <?php if (!$result['success']): ?>
                    <div class="mt-3 p-3 bg-red-50 rounded-lg">
                        <pre class="text-sm text-red-600"><?php echo htmlspecialchars($result['output']); ?></pre>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-xl font-semibold text-gray-800 mb-4">Ping History</h2>
            
            <?php if (!empty($history)): ?>
            <div class="space-y-3">
                <?php foreach ($history as $item): ?>
                <div class="border rounded-lg p-4">
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center gap-3">
                            <svg class="h-5 w-5 <?php echo $item['success'] ? 'text-green-500' : 'text-red-500'; ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                            </svg>
                            <span class="font-mono text-sm font-medium"><?php echo htmlspecialchars($item['host']); ?></span>
                        </div>
                        <span class="text-sm text-gray-500">
                            <?php echo date('M j, H:i', strtotime($item['created_at'])); ?>
                        </span>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-2 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Packet Loss:</span>
                            <span class="<?php echo $item['packet_loss'] > 0 ? 'text-orange-600' : 'text-green-600'; ?>">
                                <?php echo $item['packet_loss']; ?>%
                            </span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Avg Time:</span>
                            <span><?php echo $item['avg_time']; ?>ms</span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="text-center py-8">
                <svg class="h-12 w-12 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                </svg>
                <p class="text-gray-500">No ping history yet. Perform some pings to see results here.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>