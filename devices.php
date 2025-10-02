<?php include 'header.php'; ?>

<div class="container mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-3xl font-bold text-white">Device Management</h1>
    </div>

    <div class="bg-slate-800 border border-slate-700 rounded-lg shadow-xl p-6 mb-8">
        <h2 class="text-xl font-semibold text-white mb-4">Add New Device</h2>
        <form id="addDeviceForm" class="grid grid-cols-1 sm:grid-cols-4 gap-4">
            <input type="text" name="ip" placeholder="IP Address" class="sm:col-span-1 bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500" required>
            <input type="text" name="name" placeholder="Device Name" class="sm:col-span-1 bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500" required>
            <select name="type" class="sm:col-span-1 bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500">
                <option value="server">Server</option> <option value="router">Router</option> <option value="switch">Switch</option> <option value="printer">Printer</option> <option value="nas">NAS</option> <option value="camera">Camera</option> <option value="other">Other</option>
            </select>
            <button type="submit" class="sm:col-span-1 px-6 py-2 bg-cyan-600 text-white rounded-lg hover:bg-cyan-700 focus:ring-2 focus:ring-cyan-500">
                <i class="fas fa-plus mr-2"></i>Add Device
            </button>
        </form>
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    const API_URL = 'api.php';
    const devicesTableBody = document.getElementById('devicesTableBody');
    const addDeviceForm = document.getElementById('addDeviceForm');
    const bulkCheckBtn = document.getElementById('bulkCheckBtn');
    const tableLoader = document.getElementById('tableLoader');
    const noDevicesMessage = document.getElementById('noDevicesMessage');

    const detailsModal = document.getElementById('detailsModal');
    const detailsModalTitle = document.getElementById('detailsModalTitle');
    const detailsModalContent = document.getElementById('detailsModalContent');
    const detailsModalLoader = document.getElementById('detailsModalLoader');
    const closeDetailsModal = document.getElementById('closeDetailsModal');
    let latencyChart = null;

    const api = {
        get: (action, params = {}) => fetch(`${API_URL}?action=${action}&${new URLSearchParams(params)}`).then(res => res.json()),
        post: (action, body) => fetch(`${API_URL}?action=${action}`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) }).then(res => res.json())
    };

    const renderDeviceRow = (device) => {
        const statusClass = device.status === 'online' ? 'bg-green-500/20 text-green-400' : device.status === 'offline' ? 'bg-red-500/20 text-red-400' : 'bg-slate-600/50 text-slate-400';
        const statusIndicatorClass = `status-indicator status-${device.status}`;
        const lastSeen = device.last_seen ? new Date(device.last_seen).toLocaleString() : 'Never';

        return `
            <tr data-id="${device.id}" class="border-b border-slate-700 hover:bg-slate-800/50">
                <td class="px-6 py-4 whitespace-nowrap"><div class="text-sm font-medium text-white">${device.name}</div><div class="text-sm text-slate-400 capitalize">${device.type}</div></td>
                <td class="px-6 py-4 whitespace-nowrap"><div class="text-sm text-slate-400 font-mono">${device.ip}</div></td>
                <td class="px-6 py-4 whitespace-nowrap"><span class="px-2 inline-flex items-center gap-2 text-xs leading-5 font-semibold rounded-full ${statusClass}"><div class="${statusIndicatorClass}"></div>${device.status}</span></td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-400">${lastSeen}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                    <button class="details-device-btn text-cyan-400 hover:text-cyan-300 mr-3" data-id="${device.id}" title="View Details"><i class="fas fa-chart-line"></i></button>
                    <button class="check-device-btn text-green-400 hover:text-green-300 mr-3" data-id="${device.id}" title="Check Status"><i class="fas fa-sync"></i></button>
                    <button class="delete-device-btn text-red-500 hover:text-red-400" data-id="${device.id}" title="Delete Device"><i class="fas fa-trash"></i></button>
                </td>
            </tr>
        `;
    };

    const loadDevices = async () => {
        tableLoader.classList.remove('hidden');
        noDevicesMessage.classList.add('hidden');
        devicesTableBody.innerHTML = '';
        try {
            const devices = await api.get('get_devices');
            if (devices.length > 0) {
                devicesTableBody.innerHTML = devices.map(renderDeviceRow).join('');
            } else {
                noDevicesMessage.classList.remove('hidden');
            }
        } catch (error) { console.error('Failed to load devices:', error); }
        finally { tableLoader.classList.add('hidden'); }
    };

    const openDetailsModal = async (deviceId) => {
        detailsModal.classList.remove('hidden');
        detailsModalContent.classList.add('hidden');
        detailsModalLoader.classList.remove('hidden');
        if (latencyChart) latencyChart.destroy();

        const { device, history } = await api.get('get_device_details', { id: deviceId });
        
        detailsModalTitle.textContent = `${device.name} (${device.ip})`;
        const statusClass = device.status === 'online' ? 'text-green-400' : 'text-red-400';
        
        detailsModalContent.innerHTML = `
            <div class="md:col-span-2 bg-slate-900/50 p-4 rounded-lg border border-slate-700">
                <h3 class="font-semibold text-white mb-3">Latency History (Last 20 Pings)</h3>
                <canvas id="latencyChart"></canvas>
            </div>
            <div class="md:col-span-2 bg-slate-900/50 p-4 rounded-lg border border-slate-700">
                <h3 class="font-semibold text-white mb-3">Recent Activity</h3>
                <div class="max-h-48 overflow-y-auto">
                    <table class="min-w-full">
                        ${history.map(h => `
                            <tr class="border-b border-slate-700/50">
                                <td class="py-2 pr-2 text-sm text-slate-400">${new Date(h.created_at).toLocaleString()}</td>
                                <td class="py-2 px-2 text-sm ${h.success ? 'text-green-400' : 'text-red-400'}">${h.success ? 'Success' : 'Failed'}</td>
                                <td class="py-2 pl-2 text-sm text-right">${h.avg_time}ms</td>
                            </tr>
                        `).join('') || '<tr><td colspan="3" class="text-center text-slate-500 py-4">No recent history</td></tr>'}
                    </table>
                </div>
            </div>
        `;

        const chartCtx = document.getElementById('latencyChart').getContext('2d');
        latencyChart = new Chart(chartCtx, {
            type: 'line',
            data: {
                labels: history.map(h => new Date(h.created_at).toLocaleTimeString()).reverse(),
                datasets: [{
                    label: 'Avg Latency (ms)',
                    data: history.map(h => h.avg_time).reverse(),
                    borderColor: '#22d3ee',
                    backgroundColor: 'rgba(34, 211, 238, 0.1)',
                    fill: true,
                    tension: 0.3,
                    pointBackgroundColor: '#22d3ee'
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                scales: {
                    y: { beginAtZero: true, ticks: { color: '#94a3b8' }, grid: { color: '#334155' } },
                    x: { ticks: { color: '#94a3b8' }, grid: { color: '#334155' } }
                },
                plugins: { legend: { labels: { color: '#cbd5e1' } } }
            }
        });

        detailsModalLoader.classList.add('hidden');
        detailsModalContent.classList.remove('hidden');
    };

    addDeviceForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(addDeviceForm);
        const deviceData = { name: formData.get('name'), ip: formData.get('ip'), type: formData.get('type'), enabled: true };
        const newDevice = await api.post('create_device', deviceData);
        devicesTableBody.insertAdjacentHTML('beforeend', renderDeviceRow(newDevice));
        addDeviceForm.reset();
        noDevicesMessage.classList.add('hidden');
    });

    devicesTableBody.addEventListener('click', async (e) => {
        const button = e.target.closest('button');
        if (!button) return;
        const deviceId = button.dataset.id;
        const row = button.closest('tr');

        if (button.classList.contains('details-device-btn')) {
            openDetailsModal(deviceId);
        }

        if (button.classList.contains('check-device-btn')) {
            const icon = button.querySelector('i');
            icon.classList.add('fa-spin');
            button.disabled = true;
            try {
                await api.post('check_device', { id: deviceId });
                const devices = await api.get('get_devices');
                const updatedDevice = devices.find(d => d.id == deviceId);
                if (updatedDevice) row.outerHTML = renderDeviceRow(updatedDevice);
            } catch (error) { console.error('Failed to check device:', error); }
            finally { button.disabled = false; icon.classList.remove('fa-spin'); }
        }

        if (button.classList.contains('delete-device-btn')) {
            if (confirm('Are you sure you want to delete this device?')) {
                await api.post('delete_device', { id: deviceId });
                row.remove();
                if (devicesTableBody.children.length === 0) noDevicesMessage.classList.remove('hidden');
            }
        }
    });

    bulkCheckBtn.addEventListener('click', async () => {
        const originalHtml = bulkCheckBtn.innerHTML;
        bulkCheckBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Checking...';
        bulkCheckBtn.disabled = true;
        try {
            await api.post('ping_all_devices', {});
            await loadDevices();
        } catch (error) { console.error('Failed to check all devices:', error); }
        finally { bulkCheckBtn.innerHTML = originalHtml; bulkCheckBtn.disabled = false; }
    });

    closeDetailsModal.addEventListener('click', () => detailsModal.classList.add('hidden'));

    loadDevices();
});
</script>

<?php include 'footer.php'; ?>