<?php
require_once 'config.php';

// Initialize database connection
$pdo = getDbConnection();

// Handle form submissions
$pingResults = [];
$devices = [];
$history = [];

// Add default devices if none exist
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM devices");
$stmt->execute();
$count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

if ($count == 0) {
    $defaultDevices = [
        ['192.168.1.1', 'Router'],
        ['192.168.1.10', 'Server'],
        ['192.168.1.20', 'Printer'],
        ['192.168.1.30', 'NAS']
    ];
    
    foreach ($defaultDevices as $device) {
        $stmt = $pdo->prepare("INSERT INTO devices (ip, name, status) VALUES (?, ?, 'unknown')");
        $stmt->execute($device);
    }
}

// Handle ping request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ping_host'])) {
    $host = $_POST['ping_host'];
    $count = isset($_POST['ping_count']) ? (int)$_POST['ping_count'] : 4;
    
    // Execute ping
    $pingResult = executePing($host, $count);
    $parsedResult = parsePingOutput($pingResult['output']);
    
    $success = ($pingResult['return_code'] === 0 || $parsedResult['packet_loss'] < 100);
    
    // Store in database
    $stmt = $pdo->prepare("INSERT INTO ping_results (host, packet_loss, avg_time, min_time, max_time, success, output) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $host,
        $parsedResult['packet_loss'],
        $parsedResult['avg_time'],
        $parsedResult['min_time'],
        $parsedResult['max_time'],
        $success ? 1 : 0,
        $pingResult['output']
    ]);
    
    $pingResults[] = [
        'host' => $host,
        'packet_loss' => $parsedResult['packet_loss'],
        'avg_time' => $parsedResult['avg_time'],
        'min_time' => $parsedResult['min_time'],
        'max_time' => $parsedResult['max_time'],
        'success' => $success,
        'output' => $pingResult['output'],
        'timestamp' => date('Y-m-d H:i:s')
    ];
}

// Handle device status check
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['check_devices'])) {
    $stmt = $pdo->prepare("SELECT * FROM devices");
    $stmt->execute();
    $devices = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($devices as &$device) {
        // Try ping first
        $pingResult = executePing($device['ip'], 1);
        $parsedResult = parsePingOutput($pingResult['output']);
        
        $status = 'offline';
        if ($pingResult['return_code'] === 0 || $parsedResult['packet_loss'] < 100) {
            $status = 'online';
        } else {
            // Try HTTP as fallback
            $httpResult = checkHttpConnectivity($device['ip']);
            if ($httpResult['success']) {
                $status = 'online';
            }
        }
        
        // Update device status
        $stmt = $pdo->prepare("UPDATE devices SET status = ?, last_seen = ? WHERE id = ?");
        $stmt->execute([$status, ($status === 'online') ? date('Y-m-d H:i:s') : $device['last_seen'], $device['id']]);
        
        $device['status'] = $status;
    }
}

// Get all devices
$stmt = $pdo->prepare("SELECT * FROM devices ORDER BY ip");
$stmt->execute();
$devices = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get ping history
$stmt = $pdo->prepare("SELECT * FROM ping_results ORDER BY created_at DESC LIMIT 10");
$stmt->execute();
$history = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate network status
$onlineDevices = 0;
foreach ($devices as $device) {
    if ($device['status'] === 'online') {
        $onlineDevices++;
    }
}
$networkStatus = $onlineDevices > 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Local Network Monitor</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        * {
            font-family: 'Inter', sans-serif;
        }
        .device-card {
            transition: all 0.3s ease;
        }
        .device-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }
        .status-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
        }
        .status-online {
            background-color: #10B981;
        }
        .status-offline {
            background-color: #EF4444;
        }
        .status-unknown {
            background-color: #9CA3AF;
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <div class="container mx-auto px-4 py-8">
        <div class="flex items-center justify-between mb-6">
            <div class="flex items-center gap-3">
                <i class="fas fa-network-wired text-blue-600 text-3xl"></i>
                <h1 class="text-3xl font-bold text-gray-800">Local Network Monitor</h1>
            </div>
            <span class="px-3 py-1 <?php echo $networkStatus ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?> rounded-full text-sm font-medium">
                <?php echo $networkStatus ? 'Network Online' : 'Network Offline'; ?>
            </span>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-medium text-gray-600">Network Status</h3>
                    <i class="fas fa-wifi text-gray-400"></i>
                </div>
                <div class="text-2xl font-bold <?php echo $networkStatus ? 'text-green-600' : 'text-red-600'; ?> mt-2">
                    <?php echo $networkStatus ? 'Online' : 'Offline'; ?>
                </div>
                <p class="text-xs text-gray-500 mt-1">Local network connectivity</p>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-medium text-gray-600">Devices Online</h3>
                    <i class="fas fa-server text-gray-400"></i>
                </div>
                <div class="text-2xl font-bold text-gray-800 mt-2"><?php echo $onlineDevices; ?>/<?php echo count($devices); ?></div>
                <p class="text-xs text-gray-500 mt-1">Active devices</p>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-medium text-gray-600">Last Check</h3>
                    <i class="fas fa-clock text-gray-400"></i>
                </div>
                <div class="text-2xl font-bold text-gray-800 mt-2"><?php echo date('H:i:s'); ?></div>
                <p class="text-xs text-gray-500 mt-1">Current time</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-xl font-semibold text-gray-800 mb-4">Ping Test</h2>
                <form method="POST" class="flex flex-col sm:flex-row gap-4 mb-6">
                    <input type="text" name="ping_host" placeholder="Enter hostname or IP (e.g., 192.168.1.1)" 
                           value="192.168.1.1" class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <input type="number" name="ping_count" value="4" min="1" max="10" 
                           class="w-20 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 focus:ring-2 focus:ring-blue-500">
                        <i class="fas fa-bolt mr-2"></i>Ping
                    </button>
                </form>

                <?php if (!empty($pingResults)): ?>
                <div class="space-y-4">
                    <h3 class="text-lg font-medium text-gray-800">Latest Ping Result</h3>
                    <?php foreach ($pingResults as $result): ?>
                    <div class="border rounded-lg p-4">
                        <div class="flex items-center justify-between mb-3">
                            <div class="flex items-center gap-3">
                                <i class="fas fa-<?php echo $result['success'] ? 'check-circle text-green-500' : 'times-circle text-red-500'; ?> text-xl"></i>
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
                            <pre class="text-sm text-red-600 whitespace-pre-wrap"><?php echo htmlspecialchars($result['output']); ?></pre>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-xl font-semibold text-gray-800">Network Devices</h2>
                    <form method="POST" class="inline">
                        <button type="submit" name="check_devices" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 text-sm">
                            <i class="fas fa-sync-alt mr-2"></i>Refresh
                        </button>
                    </form>
                </div>
                
                <?php if (!empty($devices)): ?>
                <div class="space-y-3">
                    <?php foreach ($devices as $device): ?>
                    <div class="device-card border rounded-lg p-4 flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="status-indicator status-<?php echo $device['status']; ?>"></div>
                            <div>
                                <div class="font-medium"><?php echo htmlspecialchars($device['name']); ?></div>
                                <div class="text-sm text-gray-500 font-mono"><?php echo htmlspecialchars($device['ip']); ?></div>
                            </div>
                        </div>
                        <div class="text-right">
                            <span class="px-2 py-1 rounded text-xs font-medium 
                                <?php 
                                if ($device['status'] === 'online') {
                                    echo 'bg-green-100 text-green-800';
                                } elseif ($device['status'] === 'offline') {
                                    echo 'bg-red-100 text-red-800';
                                } else {
                                    echo 'bg-gray-100 text-gray-800';
                                }
                                ?>">
                                <?php 
                                if ($device['status'] === 'online') {
                                    echo 'Online';
                                } elseif ($device['status'] === 'offline') {
                                    echo 'Offline';
                                } else {
                                    echo 'Unknown';
                                }
                                ?>
                            </span>
                            <?php if ($device['last_seen']): ?>
                            <div class="text-xs text-gray-500 mt-1">
                                Seen: <?php echo date('M j, H:i', strtotime($device['last_seen'])); ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="text-center py-8">
                    <i class="fas fa-server text-gray-400 text-4xl mb-4"></i>
                    <p class="text-gray-500">No devices found. Add devices to monitor your network.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6 mt-8">
            <h2 class="text-xl font-semibold text-gray-800 mb-4">Ping History</h2>
            
            <?php if (!empty($history)): ?>
            <div class="space-y-3">
                <?php foreach ($history as $item): ?>
                <div class="border rounded-lg p-4">
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center gap-3">
                            <i class="fas fa-<?php echo $item['success'] ? 'check-circle text-green-500' : 'times-circle text-red-500'; ?>"></i>
                            <span class="font-mono text-sm font-medium"><?php echo htmlspecialchars($item['host']); ?></span>
                        </div>
                        <div class="text-right">
                            <span class="text-sm text-gray-500">
                                <?php echo date('M j, H:i', strtotime($item['created_at'])); ?>
                            </span>
                            <span class="px-2 py-1 rounded text-xs font-medium ml-2
                                <?php 
                                if ($item['success']) {
                                    echo 'bg-green-100 text-green-800';
                                } else {
                                    echo 'bg-red-100 text-red-800';
                                }
                                ?>">
                                <?php echo $item['success'] ? 'Success' : 'Failed'; ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-2 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Loss:</span>
                            <span class="<?php echo $item['packet_loss'] > 0 ? 'text-orange-600' : 'text-green-600'; ?>">
                                <?php echo $item['packet_loss']; ?>%
                            </span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Avg:</span>
                            <span><?php echo $item['avg_time']; ?>ms</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Min:</span>
                            <span><?php echo $item['min_time']; ?>ms</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Max:</span>
                            <span><?php echo $item['max_time']; ?>ms</span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="text-center py-8">
                <i class="fas fa-history text-gray-400 text-4xl mb-4"></i>
                <p class="text-gray-500">No ping history yet. Perform some pings to see results here.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>