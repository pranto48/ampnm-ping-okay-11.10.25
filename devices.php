<?php
require_once 'includes/auth_check.php';
include 'header.php';
?>

<main id="app">
    <div class="container mx-auto px-4 py-8">
        <div class="flex flex-col sm:flex-row items-center justify-between mb-6 gap-4">
            <h1 class="text-3xl font-bold text-white">Device Inventory</h1>
            <button id="createDeviceBtn" class="px-4 py-2 bg-cyan-600 text-white rounded-lg hover:bg-cyan-700">
                <i class="fas fa-plus mr-2"></i>Create New Device
            </button>
        </div>

        <div class="bg-slate-800 border border-slate-700 rounded-lg shadow-xl p-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-xl font-semibold text-white">All Devices</h2>
                <button id="bulkCheckBtn" class="px-4 py-2 bg-green-600/50 text-green-300 rounded-lg hover:bg-green-600/80">
                    <i class="fas fa-sync-alt mr-2"></i>Check All
                </button>
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead class="border-b border-slate-700">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-400 uppercase">Device</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-400 uppercase">IP Address</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-400 uppercase">Map</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-400 uppercase">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-400 uppercase">Last Seen</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-400 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="devicesTableBody"></tbody>
                </table>
                <div id="tableLoader" class="text-center py-8 hidden"><div class="loader mx-auto"></div></div>
                <div id="noDevicesMessage" class="text-center py-8 hidden">
                    <i class="fas fa-server text-slate-600 text-4xl mb-4"></i>
                    <p class="text-slate-500">No devices found. Create one to get started.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Device Details Modal -->
    <div id="detailsModal" class="fixed inset-0 bg-black bg-opacity-70 flex items-center justify-center z-50 hidden">
        <div class="bg-slate-800 rounded-lg shadow-xl p-6 w-full max-w-3xl border border-slate-700 transform transition-all">
            <div class="flex items-center justify-between mb-4">
                <h2 id="detailsModalTitle" class="text-2xl font-semibold text-white"></h2>
                <button id="closeDetailsModal" class="text-slate-400 hover:text-white text-2xl">&times;</button>
            </div>
            <div id="detailsModalContent" class="hidden"></div>
            <div id="detailsModalLoader" class="text-center py-16"><div class="loader mx-auto"></div></div>
        </div>
    </div>

    <!-- Add/Edit Device Modal -->
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
                        <input type="text" id="deviceIp" name="ip" placeholder="IP Address" class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500">
                    </div>
                    <div id="devicePortWrapper">
                        <label for="checkPort" class="block text-sm font-medium text-slate-400 mb-1">Service Port (Optional)</label>
                        <input type="number" id="checkPort" name="check_port" placeholder="e.g., 80 for HTTP" class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500">
                        <p class="text-xs text-slate-500 mt-1">If set, status is based on this port. If empty, it will use ICMP (ping).</p>
                    </div>
                    <div>
                        <label for="deviceType" class="block text-sm font-medium text-slate-400 mb-1">Type (Default Icon)</label>
                        <select id="deviceType" name="type" class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500">
                            <option value="box">Box (Group)</option>
                            <option value="camera">CC Camera</option>
                            <option value="cloud">Cloud</option>
                            <option value="database">Database</option>
                            <option value="firewall">Firewall</option>
                            <option value="ipphone">IP Phone</option>
                            <option value="laptop">Laptop/PC</option>
                            <option value="mobile">Mobile Phone</option>
                            <option value="nas">NAS</option>
                            <option value="rack">Networking Rack</option>
                            <option value="printer">Printer</option>
                            <option value="punchdevice">Punch Device</option>
                            <option value="radio-tower">Radio Tower</option>
                            <option value="router">Router</option>
                            <option value="server">Server</option>
                            <option value="switch">Switch</option>
                            <option value="tablet">Tablet</option>
                            <option value="wifi-router">WiFi Router</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div>
                        <label for="deviceMap" class="block text-sm font-medium text-slate-400 mb-1">Map Assignment</label>
                        <select id="deviceMap" name="map_id" class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500">
                            <!-- Populated by JS -->
                        </select>
                    </div>
                </div>
                <div class="flex justify-end gap-4 mt-6">
                    <button type="button" id="cancelBtn" class="px-4 py-2 bg-slate-700 text-slate-300 rounded-lg hover:bg-slate-600">Cancel</button>
                    <button type="submit" id="saveBtn" class="px-4 py-2 bg-cyan-600 text-white rounded-lg hover:bg-cyan-700">Save</button>
                </div>
            </form>
        </div>
    </div>
</main>

<?php include 'footer.php'; ?>