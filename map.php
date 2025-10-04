<?php 
require_once 'includes/auth_check.php';
include 'header.php'; 
?>
<script src="https://unpkg.com/vis-network/standalone/umd/vis-network.min.js"></script>

<div class="container mx-auto px-4 py-8">
    <div id="map-selection" class="mb-6">
        <div class="flex items-center justify-between mb-4">
            <h1 class="text-3xl font-bold text-white">Network Map</h1>
            <div class="flex gap-4">
                <select id="mapSelector" class="bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500"></select>
                <button id="newMapBtn" class="px-4 py-2 bg-cyan-600 text-white rounded-lg hover:bg-cyan-700"><i class="fas fa-plus mr-2"></i>New Map</button>
                <button id="deleteMapBtn" class="px-4 py-2 bg-red-600/80 text-white rounded-lg hover:bg-red-700"><i class="fas fa-trash mr-2"></i>Delete Map</button>
            </div>
        </div>
    </div>

    <div id="map-container" class="hidden">
        <div id="map-controls" class="bg-slate-800 border border-slate-700 rounded-lg shadow-xl p-4 mb-6">
            <div class="flex items-center justify-between">
                <h2 id="currentMapName" class="text-xl font-semibold text-white"></h2>
                <div class="flex items-center gap-2">
                    <button id="scanNetworkBtn" class="px-3 py-2 bg-slate-700 text-slate-300 rounded-lg hover:bg-slate-600" title="Scan Network"><i class="fas fa-search"></i></button>
                    <button id="refreshStatusBtn" class="px-3 py-2 bg-slate-700 text-slate-300 rounded-lg hover:bg-slate-600" title="Refresh Device Statuses"><i class="fas fa-sync-alt"></i></button>
                    <button id="addDeviceBtn" class="px-3 py-2 bg-slate-700 text-slate-300 rounded-lg hover:bg-slate-600" title="Add Device"><i class="fas fa-plus"></i></button>
                    <button id="addEdgeBtn" class="px-3 py-2 bg-slate-700 text-slate-300 rounded-lg hover:bg-slate-600" title="Add Connection"><i class="fas fa-project-diagram"></i></button>
                    <button id="deleteModeBtn" class="px-3 py-2 bg-slate-700 text-slate-300 rounded-lg hover:bg-slate-600" title="Delete Selected"><i class="fas fa-trash-alt"></i></button>
                    <button id="exportBtn" class="px-3 py-2 bg-slate-700 text-slate-300 rounded-lg hover:bg-slate-600" title="Export Map"><i class="fas fa-download"></i></button>
                    <button id="importBtn" class="px-3 py-2 bg-slate-700 text-slate-300 rounded-lg hover:bg-slate-600" title="Import Map"><i class="fas fa-upload"></i></button>
                    <input type="file" id="importFile" class="hidden" accept=".json">
                    <button id="fullscreenBtn" class="px-3 py-2 bg-slate-700 text-slate-300 rounded-lg hover:bg-slate-600" title="Toggle Fullscreen"><i class="fas fa-expand"></i></button>
                </div>
            </div>
        </div>
        <div id="network-map-wrapper">
            <div id="network-map"></div>
            <div id="status-legend">
                <div class="legend-item"><div class="legend-dot" style="background-color: #22c55e;"></div><span>Online</span></div>
                <div class="legend-item"><div class="legend-dot" style="background-color: #f59e0b;"></div><span>Warning</span></div>
                <div class="legend-item"><div class="legend-dot" style="background-color: #ef4444;"></div><span>Critical</span></div>
                <div class="legend-item"><div class="legend-dot" style="background-color: #64748b;"></div><span>Offline</span></div>
                <div class="legend-item"><div class="legend-dot" style="background-color: #94a3b8;"></div><span>Unknown</span></div>
            </div>
        </div>
    </div>
    
    <div id="no-maps" class="text-center py-16 bg-slate-800 border border-slate-700 rounded-lg hidden">
        <i class="fas fa-map-signs text-slate-600 text-5xl mb-4"></i>
        <h2 class="text-2xl font-bold text-white mb-2">No Network Maps Found</h2>
        <p class="text-slate-400 mb-6">Create a map to start visualizing your network.</p>
        <button id="createFirstMapBtn" class="px-6 py-3 bg-cyan-600 text-white rounded-lg hover:bg-cyan-700 text-lg">Create Your First Map</button>
    </div>
</div>

<!-- Modals -->
<div id="deviceModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="bg-slate-800 rounded-lg shadow-xl p-6 w-full max-w-md">
        <h2 id="modalTitle" class="text-xl font-semibold text-white mb-4">Add Device</h2>
        <form id="deviceForm">
            <input type="hidden" id="deviceId" name="id">
            <div class="space-y-4">
                <div>
                    <label for="deviceName" class="block text-sm font-medium text-slate-400 mb-1">Name</label>
                    <input type="text" id="deviceName" name="name" placeholder="Device Name" class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500" required>
                </div>
                <div id="deviceIpWrapper">
                    <label for="deviceIp" class="block text-sm font-medium text-slate-400 mb-1">IP Address</label>
                    <input type="text" id="deviceIp" name="ip" placeholder="IP Address" class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500" required>
                </div>
                <div>
                    <label for="deviceType" class="block text-sm font-medium text-slate-400 mb-1">Type</label>
                    <select id="deviceType" name="type" class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500">
                        <option value="server">Server</option><option value="router">Router</option><option value="switch">Switch</option><option value="firewall">Firewall</option><option value="printer">Printer</option><option value="nas">NAS</option><option value="camera">CC Camera</option><option value="ipphone">IP Phone</option><option value="punchdevice">Punch Device</option><option value="wifi-router">WiFi Router</option><option value="radio-tower">Radio Tower</option><option value="rack">Networking Rack</option><option value="box">Box (Group)</option><option value="other">Other</option>
                    </select>
                </div>
                <div id="pingIntervalWrapper">
                    <label for="pingInterval" class="block text-sm font-medium text-slate-400 mb-1">Ping Interval (seconds)</label>
                    <input type="number" id="pingInterval" name="ping_interval" placeholder="e.g., 60 (optional)" class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500">
                </div>
                <fieldset id="thresholdsWrapper" class="border border-slate-600 rounded-lg p-4">
                    <legend class="text-sm font-medium text-slate-400 px-2">Status Thresholds (optional)</legend>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="warning_latency_threshold" class="block text-xs text-slate-400 mb-1">Warn Latency (ms)</label>
                            <input type="number" id="warning_latency_threshold" name="warning_latency_threshold" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-1.5 text-sm">
                        </div>
                        <div>
                            <label for="warning_packetloss_threshold" class="block text-xs text-slate-400 mb-1">Warn Packet Loss (%)</label>
                            <input type="number" id="warning_packetloss_threshold" name="warning_packetloss_threshold" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-1.5 text-sm">
                        </div>
                        <div>
                            <label for="critical_latency_threshold" class="block text-xs text-slate-400 mb-1">Critical Latency (ms)</label>
                            <input type="number" id="critical_latency_threshold" name="critical_latency_threshold" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-1.5 text-sm">
                        </div>
                        <div>
                            <label for="critical_packetloss_threshold" class="block text-xs text-slate-400 mb-1">Critical Packet Loss (%)</label>
                            <input type="number" id="critical_packetloss_threshold" name="critical_packetloss_threshold" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-1.5 text-sm">
                        </div>
                    </div>
                </fieldset>
                <div>
                    <label id="iconSizeLabel" for="iconSize" class="block text-sm font-medium text-slate-400 mb-1">Icon Size</label>
                    <input type="number" id="iconSize" name="icon_size" placeholder="e.g., 50" class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500">
                </div>
                <div>
                    <label id="nameTextSizeLabel" for="nameTextSize" class="block text-sm font-medium text-slate-400 mb-1">Name Text Size</label>
                    <input type="number" id="nameTextSize" name="name_text_size" placeholder="e.g., 14" class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500">
                </div>
                <div>
                    <label for="showLivePing" class="flex items-center text-sm font-medium text-slate-400">
                        <input type="checkbox" id="showLivePing" name="show_live_ping" class="h-4 w-4 rounded border-slate-500 bg-slate-700 text-cyan-600 focus:ring-cyan-500">
                        <span class="ml-2">Show live ping status on map</span>
                    </label>
                </div>
            </div>
            <div class="flex justify-end gap-4 mt-6">
                <button type="button" id="cancelBtn" class="px-4 py-2 bg-slate-700 text-slate-300 rounded-lg hover:bg-slate-600">Cancel</button>
                <button type="submit" id="saveBtn" class="px-4 py-2 bg-cyan-600 text-white rounded-lg hover:bg-cyan-700">Save</button>
            </div>
        </form>
    </div>
</div>

<div id="edgeModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="bg-slate-800 rounded-lg shadow-xl p-6 w-full max-w-sm">
        <h2 class="text-xl font-semibold text-white mb-4">Edit Connection</h2>
        <form id="edgeForm">
            <input type="hidden" id="edgeId">
            <select id="connectionType" class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500">
                <option value="cat5">CAT5 Cable</option><option value="fiber">Fiber Optic</option><option value="wifi">WiFi</option><option value="radio">Radio</option>
            </select>
            <div class="flex justify-end gap-4 mt-6">
                <button type="button" id="cancelEdgeBtn" class="px-4 py-2 bg-slate-700 text-slate-300 rounded-lg hover:bg-slate-600">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-cyan-600 text-white rounded-lg hover:bg-cyan-700">Save</button>
            </div>
        </form>
    </div>
</div>

<div id="scanModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="bg-slate-800 rounded-lg shadow-xl p-6 w-full max-w-2xl border border-slate-700">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-xl font-semibold text-white">Scan Network for Devices</h2>
            <button id="closeScanModal" class="text-slate-400 hover:text-white text-2xl">&times;</button>
        </div>
        <div class="bg-slate-900/50 p-4 rounded-lg border border-slate-700 mb-4">
            <form id="scanForm" class="flex flex-col sm:flex-row gap-4">
                <input type="text" id="subnetInput" placeholder="e.g., 192.168.1.0/24" value="192.168.1.0/24" class="flex-1 bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500">
                <button type="submit" id="startScanBtn" class="px-6 py-2 bg-cyan-600 text-white rounded-lg hover:bg-cyan-700">
                    <i class="fas fa-search mr-2"></i>Start Scan
                </button>
            </form>
        </div>
        <div id="scanResultWrapper" class="max-h-96 overflow-y-auto">
            <div id="scanLoader" class="text-center py-8 hidden"><div class="loader mx-auto"></div><p class="mt-2 text-slate-400">Scanning... this may take a moment.</p></div>
            <div id="scanResults"></div>
            <div id="scanInitialMessage" class="text-center py-8 text-slate-500">
                <i class="fas fa-network-wired text-4xl mb-4"></i>
                <p>Enter a subnet and start the scan to discover devices.</p>
                <p class="text-sm mt-2">(Requires <a href="https://nmap.org/" target="_blank" class="text-cyan-400 hover:underline">nmap</a> to be installed on the server)</p>
            </div>
        </div>
    </div>
</div>

<script src="assets/js/map.js" defer></script>

<?php include 'footer.php'; ?>