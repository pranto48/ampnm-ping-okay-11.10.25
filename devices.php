<?php
require_once 'includes/auth_check.php';
include 'header.php';
?>

<main id="app">
    <div class="container mx-auto px-4 py-8">
        <div class="flex flex-col sm:flex-row items-center justify-between mb-6 gap-4">
            <h1 class="text-3xl font-bold text-white">Device Management</h1>
            <div id="map-selector-container" class="flex items-center gap-2">
                <!-- Populated by JS -->
            </div>
        </div>

        <div class="bg-slate-800 border border-slate-700 rounded-lg shadow-xl p-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-xl font-semibold text-white">Device List</h2>
                <div class="flex items-center gap-4">
                    <a href="map.php" class="px-4 py-2 bg-cyan-600/50 text-cyan-300 rounded-lg hover:bg-cyan-600/80 text-sm">
                        <i class="fas fa-map-marked-alt mr-2"></i>View on Map
                    </a>
                    <button id="bulkCheckBtn" class="px-4 py-2 bg-green-600/50 text-green-300 rounded-lg hover:bg-green-600/80">
                        <i class="fas fa-sync-alt mr-2"></i>Check All
                    </button>
                </div>
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead class="border-b border-slate-700">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-400 uppercase">Device</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-400 uppercase">IP Address</th>
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
                    <p class="text-slate-500">No devices found on this map. Add one on the map page.</p>
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
</main>

<?php include 'footer.php'; ?>