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
        * { font-family: 'Inter', sans-serif; }
        .status-indicator { width: 12px; height: 12px; border-radius: 50%; display: inline-block; }
        .status-online { background-color: #10B981; }
        .status-offline { background-color: #EF4444; }
        .status-unknown { background-color: #9CA3AF; }
        .loader { border: 4px solid #f3f3f3; border-top: 4px solid #3498db; border-radius: 50%; width: 24px; height: 24px; animation: spin 1s linear infinite; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
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
            <form id="addDeviceForm" class="flex flex-col sm:flex-row gap-4">
                <input type="text" name="ip" placeholder="IP Address (e.g., 192.168.1.100)" class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" required>
                <input type="text" name="name" placeholder="Device Name" class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" required>
                <select name="type" class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <option value="server">Server</option>
                    <option value="router">Router</option>
                    <option value="switch">Switch</option>
                    <option value="printer">Printer</option>
                    <option value="nas">NAS</option>
                    <option value="camera">Camera</option>
                    <option value="other">Other</option>
                </select>
                <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 focus:ring-2 focus:ring-blue-500">
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
                    <tbody class="bg-white divide-y divide-gray-200" id="devicesTableBody">
                        <!-- Devices will be loaded here by JavaScript -->
                    </tbody>
                </table>
                <div id="tableLoader" class="text-center py-8 hidden"><div class="loader mx-auto"></div></div>
                <div id="noDevicesMessage" class="text-center py-8 hidden">
                    <i class="fas fa-server text-gray-400 text-4xl mb-4"></i>
                    <p class="text-gray-500">No devices found. Add a device to get started.</p>
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
                post: (action, body) => fetch(`${API_URL}?action=${action}`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(body)
                }).then(res => res.json())
            };

            const renderDeviceRow = (device) => {
                const statusClass = device.status === 'online' ? 'bg-green-100 text-green-800' : device.status === 'offline' ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-800';
                const statusIndicatorClass = `status-indicator status-${device.status}`;
                const lastSeen = device.last_seen ? new Date(device.last_seen).toLocaleString() : 'Never';

                return `
                    <tr data-id="${device.id}">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm font-medium text-gray-900">${device.name}</div>
                            <div class="text-sm text-gray-500">${device.type}</div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-500 font-mono">${device.ip}</div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${statusClass}">
                                <div class="${statusIndicatorClass} mr-2 self-center"></div>
                                ${device.status}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${lastSeen}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <button class="check-device-btn text-indigo-600 hover:text-indigo-900 mr-3" data-id="${device.id}" title="Check Status">
                                <i class="fas fa-sync"></i>
                            </button>
                            <button class="delete-device-btn text-red-600 hover:text-red-900" data-id="${device.id}" title="Delete Device">
                                <i class="fas fa-trash"></i>
                            </button>
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
                    alert('Error loading devices. Please check the console.');
                } finally {
                    tableLoader.classList.add('hidden');
                }
            };

            addDeviceForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const formData = new FormData(addDeviceForm);
                const deviceData = {
                    name: formData.get('name'),
                    ip: formData.get('ip'),
                    type: formData.get('type'),
                    enabled: true
                };

                try {
                    const newDevice = await api.post('create_device', deviceData);
                    devicesTableBody.insertAdjacentHTML('beforeend', renderDeviceRow(newDevice));
                    addDeviceForm.reset();
                    noDevicesMessage.classList.add('hidden');
                } catch (error) {
                    console.error('Failed to add device:', error);
                    alert('Error adding device. Please check the console.');
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
                        const result = await api.post('check_device', { id: deviceId });
                        const updatedDevice = await api.get('get_devices').then(devices => devices.find(d => d.id == deviceId));
                        if (updatedDevice) {
                            row.outerHTML = renderDeviceRow(updatedDevice);
                        }
                    } catch (error) {
                        console.error('Failed to check device:', error);
                        alert('Error checking device.');
                    } finally {
                        // The button is gone after re-render, so no need to reset state
                    }
                }

                if (button.classList.contains('delete-device-btn')) {
                    if (confirm('Are you sure you want to delete this device?')) {
                        try {
                            await api.post('delete_device', { id: deviceId });
                            row.remove();
                            if (devicesTableBody.children.length === 0) {
                                noDevicesMessage.classList.remove('hidden');
                            }
                        } catch (error) {
                            console.error('Failed to delete device:', error);
                            alert('Error deleting device.');
                        }
                    }
                }
            });

            bulkCheckBtn.addEventListener('click', async () => {
                const icon = bulkCheckBtn.querySelector('i');
                const originalHtml = bulkCheckBtn.innerHTML;
                bulkCheckBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Checking...';
                bulkCheckBtn.disabled = true;

                try {
                    await api.post('ping_all_devices', {});
                    await loadDevices(); // Reload all devices to show updated statuses
                } catch (error) {
                    console.error('Failed to check all devices:', error);
                    alert('Error checking all devices.');
                } finally {
                    bulkCheckBtn.innerHTML = originalHtml;
                    bulkCheckBtn.disabled = false;
                }
            });

            // Initial load
            loadDevices();
        });
    </script>
</body>
</html>