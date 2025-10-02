<?php
require_once 'includes/functions.php';
include 'header.php';

$pdo = getDbConnection();

// Get all devices
$stmt = $pdo->prepare("SELECT * FROM devices ORDER BY ip");
$stmt->execute();
$devices = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get ping history
$stmt = $pdo->prepare("SELECT * FROM ping_results ORDER BY created_at DESC LIMIT 5");
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

<div class="container mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-3xl font-bold text-white">Dashboard</h1>
        <span class="px-3 py-1 flex items-center gap-2 <?php echo $networkStatus ? 'bg-green-500/20 text-green-400' : 'bg-red-500/20 text-red-400'; ?> rounded-full text-sm font-medium">
            <div class="status-indicator <?php echo $networkStatus ? 'status-online' : 'status-offline'; ?>"></div>
            <?php echo $networkStatus ? 'Network Online' : 'Network Offline'; ?>
        </span>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <!-- Stat Cards -->
        <div class="bg-slate-800/50 border border-slate-700 rounded-lg shadow-lg p-6">
            <div class="flex items-center justify-between">
                <h3 class="text-sm font-medium text-slate-400">Network Status</h3>
                <i class="fas fa-wifi text-cyan-400"></i>
            </div>
            <div class="text-2xl font-bold <?php echo $networkStatus ? 'text-green-400' : 'text-red-400'; ?> mt-2">
                <?php echo $networkStatus ? 'Systems Operational' : 'Systems Offline'; ?>
            </div>
        </div>
        <div class="bg-slate-800/50 border border-slate-700 rounded-lg shadow-lg p-6">
            <div class="flex items-center justify-between">
                <h3 class="text-sm font-medium text-slate-400">Devices Online</h3>
                <i class="fas fa-server text-cyan-400"></i>
            </div>
            <div class="text-2xl font-bold text-white mt-2"><?php echo $onlineDevices; ?>/<?php echo count($devices); ?></div>
        </div>
        <div class="bg-slate-800/50 border border-slate-700 rounded-lg shadow-lg p-6">
            <div class="flex items-center justify-between">
                <h3 class="text-sm font-medium text-slate-400">Last System Check</h3>
                <i class="fas fa-clock text-cyan-400"></i>
            </div>
            <div class="text-2xl font-bold text-white mt-2"><?php echo date('H:i:s'); ?></div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <!-- Ping Test -->
        <div class="bg-slate-800 border border-slate-700 rounded-lg shadow-xl p-6">
            <h2 class="text-xl font-semibold text-white mb-4">Manual Ping Test</h2>
            <form method="POST" action="index.php" class="flex flex-col sm:flex-row gap-4 mb-6">
                <input type="text" name="ping_host" placeholder="Enter hostname or IP" value="192.168.1.1" class="flex-1 px-4 py-2 bg-slate-900 border border-slate-600 rounded-lg focus:ring-2 focus:ring-cyan-500 focus:border-transparent">
                <button type="submit" class="px-6 py-2 bg-cyan-600 text-white rounded-lg hover:bg-cyan-700 focus:ring-2 focus:ring-cyan-500">
                    <i class="fas fa-bolt mr-2"></i>Ping
                </button>
            </form>
        </div>

        <!-- Network Devices -->
        <div class="bg-slate-800 border border-slate-700 rounded-lg shadow-xl p-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-xl font-semibold text-white">Monitored Devices</h2>
                <a href="devices.php" class="px-4 py-2 bg-slate-700 text-slate-300 rounded-lg hover:bg-slate-600 text-sm">
                    <i class="fas fa-edit mr-2"></i>Manage
                </a>
            </div>
            <div class="space-y-3 max-h-60 overflow-y-auto">
                <?php foreach ($devices as $device): ?>
                <div class="border border-slate-700 rounded-lg p-3 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="status-indicator status-<?php echo $device['status']; ?>"></div>
                        <div>
                            <div class="font-medium text-white"><?php echo htmlspecialchars($device['name']); ?></div>
                            <div class="text-sm text-slate-400 font-mono"><?php echo htmlspecialchars($device['ip']); ?></div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>