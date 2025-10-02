<?php
require_once 'config.php';

$pdo = getDbConnection();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_device'])) {
        $ip = $_POST['ip'];
        $name = $_POST['name'];
        
        $stmt = $pdo->prepare("INSERT INTO devices (ip, name) VALUES (?, ?) ON DUPLICATE KEY UPDATE name = ?");
        $stmt->execute([$ip, $name, $name]);
    } elseif (isset($_POST['delete_device'])) {
        $id = $_POST['id'];
        $stmt = $pdo->prepare("DELETE FROM devices WHERE id = ?");
        $stmt->execute([$id]);
    } elseif (isset($_POST['bulk_check'])) {
        // This will be handled via AJAX
    }
}

// Get all devices
$stmt = $pdo->prepare("SELECT * FROM devices ORDER BY ip");
$stmt->execute();
$devices = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Device Management - Network Monitor</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        * {
            font-family: 'Inter', sans-serif;
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
                <i class="fas fa-server text-blue-600 text-3xl"></i>
                <h1 class="text-3xl font-bold text-gray-800">Device Management</h1>
            </div>
            <a href="index.php" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300">
                <i class="fas fa-arrow-left mr-2"></i>Back to Dashboard
            </a>
        </div>

        <div class="bg-white rounded-lg shadow p-6 mb-8">
            <h2 class="text-xl font-semibold text-gray-800 mb-4">Add New Device</h2>
            <form method="POST" class="flex flex-col sm:flex-row gap-4">
                <input type="text" name="ip" placeholder="IP Address (e.g., 192.168.1.100)" 
                       class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" required>
                <input type="text" name="name" placeholder="Device Name" 
                       class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" required>
                <button type="submit" name="add_device" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 focus:ring-2 focus:ring-blue-500">
                    <i class="fas fa-plus mr-2"></i>Add Device
                </button>
            </form>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-xl font-semibold text-gray-800">Network Devices</h2>
                <button id="bulkCheckBtn" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
                    <i class="fas fa-sync-alt mr-2"></i>Check All Devices
                </button>
            </div>
            
            <?php if (!empty($devices)): ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Device</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">IP Address</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last Seen</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200" id="devicesTable">
                        <?php foreach ($devices as $device): ?>
                        <tr data-id="<?php echo $device['id']; ?>">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($device['name']); ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-500 font-mono"><?php echo htmlspecialchars($device['ip']); ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                    <?php 
                                    if ($device['status'] === 'online') {
                                        echo 'bg-green-100 text-green-800';
                                    } elseif ($device['status'] === 'offline') {
                                        echo 'bg-red-100 text-red-800';
                                    } else {
                                        echo 'bg-gray-100 text-gray-800';
                                    }
                                    ?>">
                                    <div class="status-indicator status-<?php echo $device['status']; ?> mr-2"></div>
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
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo $device['last_seen'] ? date('M j, H:i', strtotime($device['last_seen'])) : 'Never'; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <button class="check-device-btn text-indigo-600 hover:text-indigo-900 mr-3" data-id="<?php echo $device['id']; ?>">
                                    <i class="fas fa-sync"></i>
                                </button>
                                <form method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this device?')">
                                    <input type="hidden" name="id" value="<?php echo $device['id']; ?>">
                                    <button type="submit" name="delete_device" class="text-red-600 hover:text-red-900">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="text-center py-8">
                <i class="fas fa-server text-gray-400 text-4xl mb-4"></i>
                <p class="text-gray-500">No devices found. Add devices to monitor your network.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Check single device
            document.querySelectorAll('.check-device-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const deviceId = this.getAttribute('data-id');
                    checkDevice(deviceId, this);
                });
            });
            
            // Bulk check all devices
            document.getElementById('bulkCheckBtn').addEventListener('click', function() {
                bulkCheckDevices();
            });
        });
        
        function checkDevice(deviceId, button) {
            const originalHtml = button.innerHTML;
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            button.disabled = true;
            
            fetch('api.php?action=check_device', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({id: deviceId})
            })
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    alert('Error: ' + data.error);
                } else {
                    // Update the device row
                    const row = document.querySelector(`tr[data-id="${deviceId}"]`);
                    if (row) {
                        const statusCell = row.querySelector('td:nth-child(3) span');
                        const lastSeenCell = row.querySelector('td:nth-child(4)');
                        
                        // Update status
                        statusCell.className = statusCell.className.replace(/bg-\w+-100 text-\w+-800/, 
                            data.status === 'online' ? 'bg-green-100 text-green-800' : 
                            data.status === 'offline' ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-800');
                        
                        statusCell.innerHTML = `
                            <div class="status-indicator status-${data.status} mr-2"></div>
                            ${data.status === 'online' ? 'Online' : data.status === 'offline' ? 'Offline' : 'Unknown'}
                        `;
                        
                        // Update last seen
                        lastSeenCell.textContent = data.last_seen ? new Date(data.last_seen).toLocaleString() : 'Never';
                    }
                }
            })
            .catch(error => {
                alert('Error checking device: ' + error.message);
            })
            .finally(() => {
                button.innerHTML = originalHtml;
                button.disabled = false;
            });
        }
        
        function bulkCheckDevices() {
            const button = document.getElementById('bulkCheckBtn');
            const originalHtml = button.innerHTML;
            button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Checking...';
            button.disabled = true;
            
            // Get all device IDs
            const deviceIds = Array.from(document.querySelectorAll('.check-device-btn')).map(btn => btn.getAttribute('data-id'));
            
            // Check each device sequentially
            let index = 0;
            function checkNext() {
                if (index < deviceIds.length) {
                    const deviceId = deviceIds[index];
                    const button = document.querySelector(`.check-device-btn[data-id="${deviceId}"]`);
                    
                    fetch('api.php?action=check_device', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({id: deviceId})
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (!data.error) {
                            // Update the device row
                            const row = document.querySelector(`tr[data-id="${deviceId}"]`);
                            if (row) {
                                const statusCell = row.querySelector('td:nth-child(3) span');
                                const lastSeenCell = row.querySelector('td:nth-child(4)');
                                
                                // Update status
                                statusCell.className = statusCell.className.replace(/bg-\w+-100 text-\w+-800/, 
                                    data.status === 'online' ? 'bg-green-100 text-green-800' : 
                                    data.status === 'offline' ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-800');
                                
                                statusCell.innerHTML = `
                                    <div class="status-indicator status-${data.status} mr-2"></div>
                                    ${data.status === 'online' ? 'Online' : data.status === 'offline' ? 'Offline' : 'Unknown'}
                                `;
                                
                                // Update last seen
                                lastSeenCell.textContent = data.last_seen ? new Date(data.last_seen).toLocaleString() : 'Never';
                            }
                        }
                        index++;
                        checkNext();
                    })
                    .catch(error => {
                        index++;
                        checkNext();
                    });
                } else {
                    button.innerHTML = '<i class="fas fa-sync-alt mr-2"></i>Check All Devices';
                    button.disabled = false;
                }
            }
            
            checkNext();
        }
    </script>
</body>
</html>