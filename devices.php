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

<script>
document.addEventListener('DOMContentLoaded', function() {
    const API_URL = 'api.php';
    const devicesTableBody = document.getElementById('devicesTableBody');
    const addDeviceForm = document.getElementById('addDeviceForm');
    const bulkCheckBtn = document.getElementById('bulkCheckBtn');
    const tableLoader = document.getElementById('tableLoader');
    const noDevicesMessage = document.getElementById('noDevicesMessage');

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
                <td class="px-6 py-4 whitespace-nowrap"><div class="text-sm font-medium text-white">${device.name}</div><div class="text-sm text-slate-400">${device.type}</div></td>
                <td class="px-6 py-4 whitespace-nowrap"><div class="text-sm text-slate-400 font-mono">${device.ip}</div></td>
                <td class="px-6 py-4 whitespace-nowrap"><span class="px-2 inline-flex items-center gap-2 text-xs leading-5 font-semibold rounded-full ${statusClass}"><div class="${statusIndicatorClass}"></div>${device.status}</span></td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-400">${lastSeen}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                    <button class="check-device-btn text-cyan-400 hover:text-cyan-300 mr-3" data-id="${device.id}" title="Check Status"><i class="fas fa-sync"></i></button>
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
        } catch (error) {
            console.error('Failed to load devices:', error);
        } finally {
            tableLoader.classList.add('hidden');
        }
    };

    addDeviceForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(addDeviceForm);
        const deviceData = { name: formData.get('name'), ip: formData.get('ip'), type: formData.get('type'), enabled: true };
        try {
            const newDevice = await api.post('create_device', deviceData);
            devicesTableBody.insertAdjacentHTML('beforeend', renderDeviceRow(newDevice));
            addDeviceForm.reset();
            noDevicesMessage.classList.add('hidden');
        } catch (error) {
            console.error('Failed to add device:', error);
        }
    });

    devicesTableBody.addEventListener('click', async (e) => {
        const button = e.target.closest('button');
        if (!button) return;
        const deviceId = button.dataset.id;
        const row = button.closest('tr');

        if (button.classList.contains('check-device-btn')) {
            const icon = button.querySelector('i');
            icon.classList.add('fa-spin');
            button.disabled = true;
            try {
                await api.post('check_device', { id: deviceId });
                const updatedDevice = await api.get('get_devices').then(devices => devices.find(d => d.id == deviceId));
                if (updatedDevice) row.outerHTML = renderDeviceRow(updatedDevice);
            } catch (error) {
                console.error('Failed to check device:', error);
            }
        }

        if (button.classList.contains('delete-device-btn')) {
            if (confirm('Are you sure you want to delete this device?')) {
                try {
                    await api.post('delete_device', { id: deviceId });
                    row.remove();
                    if (devicesTableBody.children.length === 0) noDevicesMessage.classList.remove('hidden');
                } catch (error) {
                    console.error('Failed to delete device:', error);
                }
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
        } catch (error) {
            console.error('Failed to check all devices:', error);
        } finally {
            bulkCheckBtn.innerHTML = originalHtml;
            bulkCheckBtn.disabled = false;
        }
    });

    loadDevices();
});
</script>

<?php include 'footer.php'; ?>