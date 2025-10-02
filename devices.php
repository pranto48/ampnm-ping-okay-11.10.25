<?php include 'header.php'; ?>

<div class="container mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-3xl font-bold text-white">Device Management</h1>
    </div>

    <div class="bg-slate-800 border border-slate-700 rounded-lg shadow-xl p-6 mb-8 text-center">
        <i class="fas fa-map-marked-alt text-cyan-400 text-3xl mb-3"></i>
        <h2 class="text-xl font-semibold text-white mb-2">Add and Manage Devices on the Map</h2>
        <p class="text-slate-400 mb-4">To add, edit, or position devices, please use the interactive Network Map.</p>
        <a href="map.php" class="inline-block px-6 py-2 bg-cyan-600 text-white rounded-lg hover:bg-cyan-700 focus:ring-2 focus:ring-cyan-500">
            <i class="fas fa-arrow-right mr-2"></i>Go to Network Map
        </a>
    </div>

    <div class="bg-slate-800 border border-slate-700 rounded-lg shadow-xl p-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-xl font-semibold text-white">Device List</h2>
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
                <p class="text-slate-500">No devices found. Add one to get started.</p>
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

<script src="assets/js/devices.js" defer></script>

<?php include 'footer.php'; ?>