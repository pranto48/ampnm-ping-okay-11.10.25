<?php include 'header.php'; ?>
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
                    <button id="refreshStatusBtn" class="px-3 py-2 bg-slate-700 text-slate-300 rounded-lg hover:bg-slate-600" title="Refresh Device Statuses"><i class="fas fa-sync-alt"></i></button>
                    <button id="addDeviceBtn" class="px-3 py-2 bg-slate-700 text-slate-300 rounded-lg hover:bg-slate-600" title="Add Device"><i class="fas fa-plus"></i></button>
                    <button id="addEdgeBtn" class="px-3 py-2 bg-slate-700 text-slate-300 rounded-lg hover:bg-slate-600" title="Add Connection"><i class="fas fa-project-diagram"></i></button>
                    <button id="deleteModeBtn" class="px-3 py-2 bg-slate-700 text-slate-300 rounded-lg hover:bg-slate-600" title="Delete Selected"><i class="fas fa-trash-alt"></i></button>
                    <button id="fullscreenBtn" class="px-3 py-2 bg-slate-700 text-slate-300 rounded-lg hover:bg-slate-600" title="Toggle Fullscreen"><i class="fas fa-expand"></i></button>
                </div>
            </div>
        </div>
        <div id="network-map-wrapper">
            <div id="network-map"></div>
            <div id="status-legend">
                <div class="legend-item"><div class="legend-dot" style="background-color: #22c55e;"></div><span>Online</span></div>
                <div class="legend-item"><div class="legend-dot" style="background-color: #f59e0b;"></div><span>Unknown</span></div>
                <div class="legend-item"><div class="legend-dot" style="background-color: #ef4444;"></div><span>Offline</span></div>
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

<!-- Device Editor Modal -->
<div id="deviceModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="bg-slate-800 rounded-lg shadow-xl p-6 w-full max-w-md">
        <h2 id="modalTitle" class="text-xl font-semibold text-white mb-4">Add Device</h2>
        <form id="deviceForm">
            <input type="hidden" id="deviceId" name="id">
            <div class="space-y-4">
                <input type="text" id="deviceName" name="name" placeholder="Device Name" class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500" required>
                <input type="text" id="deviceIp" name="ip" placeholder="IP Address" class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500" required>
                <select id="deviceType" name="type" class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500">
                    <option value="server">Server</option>
                    <option value="router">Router</option>
                    <option value="switch">Switch</option>
                    <option value="firewall">Firewall</option>
                    <option value="printer">Printer</option>
                    <option value="nas">NAS</option>
                    <option value="camera">CC Camera</option>
                    <option value="ipphone">IP Phone</option>
                    <option value="punchdevice">Punch Device</option>
                    <option value="other">Other</option>
                </select>
            </div>
            <div class="flex justify-end gap-4 mt-6">
                <button type="button" id="cancelBtn" class="px-4 py-2 bg-slate-700 text-slate-300 rounded-lg hover:bg-slate-600">Cancel</button>
                <button type="submit" id="saveBtn" class="px-4 py-2 bg-cyan-600 text-white rounded-lg hover:bg-cyan-700">Save</button>
            </div>
        </form>
    </div>
</div>

<script src="assets/js/map.js" defer></script>

<?php include 'footer.php'; ?>