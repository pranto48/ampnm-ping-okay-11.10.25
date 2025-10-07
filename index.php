<?php
require_once 'includes/auth_check.php';
require_once 'includes/functions.php';
include 'header.php';

$pdo = getDbConnection();
$stmt = $pdo->prepare("SELECT id, name FROM maps ORDER BY name ASC");
$stmt->execute();
$maps = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container mx-auto px-4 py-8">
    <div class="flex flex-col sm:flex-row items-center justify-between mb-6 gap-4">
        <h1 class="text-3xl font-bold text-white">Dashboard</h1>
        <?php if (!empty($maps)): ?>
        <div class="flex items-center gap-2">
            <label for="mapSelector" class="text-slate-400">Map:</label>
            <select id="mapSelector" class="bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500">
                <?php foreach ($maps as $map): ?>
                    <option value="<?= $map['id'] ?>"><?= htmlspecialchars($map['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php else: ?>
        <a href="map.php" class="px-4 py-2 bg-cyan-600 text-white rounded-lg hover:bg-cyan-700">Create a Map to Get Started</a>
        <?php endif; ?>
    </div>

    <div id="dashboard-content">
        <div class="text-center py-16" id="dashboardLoader"><div class="loader mx-auto"></div></div>
        <div id="dashboard-widgets" class="hidden">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
                <!-- Status Chart -->
                <div class="lg:col-span-1 bg-slate-800/50 border border-slate-700 rounded-lg shadow-lg p-6 flex flex-col items-center justify-center">
                    <h3 class="text-lg font-semibold text-white mb-4">Device Status Overview</h3>
                    <div class="w-48 h-48 relative">
                        <canvas id="statusChart"></canvas>
                        <div id="totalDevicesText" class="absolute inset-0 flex flex-col items-center justify-center text-white">
                            <span class="text-4xl font-bold">--</span>
                            <span class="text-sm text-slate-400">Total Devices</span>
                        </div>
                    </div>
                </div>
                <!-- Status Counters -->
                <div class="lg:col-span-2 grid grid-cols-2 md:grid-cols-4 gap-6">
                    <div class="bg-slate-800/50 border border-slate-700 rounded-lg shadow-lg p-6 text-center">
                        <h3 class="text-sm font-medium text-slate-400">Online</h3>
                        <div id="onlineCount" class="text-4xl font-bold text-green-400 mt-2">--</div>
                    </div>
                    <div class="bg-slate-800/50 border border-slate-700 rounded-lg shadow-lg p-6 text-center">
                        <h3 class="text-sm font-medium text-slate-400">Warning</h3>
                        <div id="warningCount" class="text-4xl font-bold text-yellow-400 mt-2">--</div>
                    </div>
                    <div class="bg-slate-800/50 border border-slate-700 rounded-lg shadow-lg p-6 text-center">
                        <h3 class="text-sm font-medium text-slate-400">Critical</h3>
                        <div id="criticalCount" class="text-4xl font-bold text-red-400 mt-2">--</div>
                    </div>
                    <div class="bg-slate-800/50 border border-slate-700 rounded-lg shadow-lg p-6 text-center">
                        <h3 class="text-sm font-medium text-slate-400">Offline</h3>
                        <div id="offlineCount" class="text-4xl font-bold text-slate-500 mt-2">--</div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <!-- Ping Test -->
                <div class="bg-slate-800 border border-slate-700 rounded-lg shadow-xl p-6">
                    <h2 class="text-xl font-semibold text-white mb-4">Manual Ping Test</h2>
                    <form id="pingForm" class="flex flex-col sm:flex-row gap-4 mb-4">
                        <input type="text" id="pingHostInput" name="ping_host" placeholder="Enter hostname or IP" value="192.168.1.1" class="flex-1 px-4 py-2 bg-slate-900 border border-slate-600 rounded-lg focus:ring-2 focus:ring-cyan-500 focus:border-transparent">
                        <button type="submit" id="pingButton" class="px-6 py-2 bg-cyan-600 text-white rounded-lg hover:bg-cyan-700 focus:ring-2 focus:ring-cyan-500">
                            <i class="fas fa-bolt mr-2"></i>Ping
                        </button>
                    </form>
                    <div id="pingResultContainer" class="hidden mt-4">
                        <pre id="pingResultPre" class="bg-slate-900/50 text-white text-sm p-4 rounded-lg overflow-x-auto"></pre>
                    </div>
                </div>

                <!-- Network Devices -->
                <div class="bg-slate-800 border border-slate-700 rounded-lg shadow-xl p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-xl font-semibold text-white">Monitored Devices</h2>
                        <a href="devices.php" id="manageDevicesLink" class="px-4 py-2 bg-slate-700 text-slate-300 rounded-lg hover:bg-slate-600 text-sm">
                            <i class="fas fa-edit mr-2"></i>Manage
                        </a>
                    </div>
                    <div id="deviceList" class="space-y-3 max-h-60 overflow-y-auto"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="assets/js/dashboard.js" defer></script>

<?php include 'footer.php'; ?>