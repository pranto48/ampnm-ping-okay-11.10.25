document.addEventListener('DOMContentLoaded', function() {
    const API_URL = 'api.php';
    const devicesTableBody = document.getElementById('devicesTableBody');
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
        post: (action, body = {}) => fetch(`${API_URL}?action=${action}`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) }).then(res => res.json())
    };

    const statusClasses = {
        online: 'bg-green-500/20 text-green-400',
        warning: 'bg-yellow-500/20 text-yellow-400',
        critical: 'bg-red-500/20 text-red-400',
        offline: 'bg-slate-600/50 text-slate-400',
        unknown: 'bg-slate-600/50 text-slate-400'
    };

    const renderDeviceRow = (device) => {
        const statusClass = statusClasses[device.status] || statusClasses.unknown;
        const statusIndicatorClass = `status-indicator status-${device.status}`;
        const lastSeen = device.last_seen ? new Date(device.last_seen).toLocaleString() : 'Never';

        return `
            <tr data-id="${device.id}" class="border-b border-slate-700 hover:bg-slate-800/50">
                <td class="px-6 py-4 whitespace-nowrap"><div class="text-sm font-medium text-white">${device.name}</div><div class="text-sm text-slate-400 capitalize">${device.type}</div></td>
                <td class="px-6 py-4 whitespace-nowrap"><div class="text-sm text-slate-400 font-mono">${device.ip || 'N/A'}</div></td>
                <td class="px-6 py-4 whitespace-nowrap"><span class="px-2 inline-flex items-center gap-2 text-xs leading-5 font-semibold rounded-full ${statusClass}"><div class="${statusIndicatorClass}"></div>${device.status}</span></td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-400">${lastSeen}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                    <button class="details-device-btn text-cyan-400 hover:text-cyan-300 mr-3" data-id="${device.id}" title="View Details"><i class="fas fa-chart-line"></i></button>
                    <a href="map.php?map_id=${device.map_id}&edit_device_id=${device.id}" class="text-yellow-400 hover:text-yellow-300 mr-3" title="Edit Device"><i class="fas fa-edit"></i></a>
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
        
        const urlParams = new URLSearchParams(window.location.search);
        const mapId = urlParams.get('map_id');

        try {
            const devices = await api.get('get_devices', mapId ? { map_id: mapId } : {});
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
        
        detailsModalContent.innerHTML = `
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="bg-slate-900/50 p-4 rounded-lg border border-slate-700">
                    <h3 class="font-semibold text-white mb-3">Latency History (Last 20 Pings)</h3>
                    <div class="h-48"><canvas id="latencyChart"></canvas></div>
                </div>
                <div class="bg-slate-900/50 p-4 rounded-lg border border-slate-700">
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
            </div>
        `;

        const annotations = {};
        if (device.warning_latency_threshold) {
            annotations.warningLine = {
                type: 'line', yMin: device.warning_latency_threshold, yMax: device.warning_latency_threshold,
                borderColor: '#f59e0b', borderWidth: 2, borderDash: [6, 6],
                label: { content: 'Warning', enabled: true, position: 'end', backgroundColor: 'rgba(245, 158, 11, 0.8)' }
            };
        }
        if (device.critical_latency_threshold) {
            annotations.criticalLine = {
                type: 'line', yMin: device.critical_latency_threshold, yMax: device.critical_latency_threshold,
                borderColor: '#ef4444', borderWidth: 2, borderDash: [6, 6],
                label: { content: 'Critical', enabled: true, position: 'end', backgroundColor: 'rgba(239, 68, 68, 0.8)' }
            };
        }

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
                plugins: { 
                    legend: { labels: { color: '#cbd5e1' } },
                    annotation: { annotations: annotations }
                }
            }
        });

        detailsModalLoader.classList.add('hidden');
        detailsModalContent.classList.remove('hidden');
    };

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
                await loadDevices();
            } catch (error) { console.error('Failed to check device:', error); }
            finally { button.disabled = false; icon.classList.remove('fa-spin'); }
        }

        if (button.classList.contains('delete-device-btn')) {
            if (confirm('Are you sure you want to delete this device? This will also remove it from the map.')) {
                await api.post('delete_device', { id: deviceId });
                row.remove();
                if (devicesTableBody.children.length === 0) noDevicesMessage.classList.remove('hidden');
            }
        }
    });

    bulkCheckBtn.addEventListener('click', async () => {
        const icon = bulkCheckBtn.querySelector('i');
        icon.classList.add('fa-spin');
        bulkCheckBtn.disabled = true;
        try {
            const urlParams = new URLSearchParams(window.location.search);
            const mapId = urlParams.get('map_id');
            if (mapId) {
                await api.post('ping_all_devices', { map_id: mapId });
            } else {
                alert("Please select a map first.");
            }
            await loadDevices();
        } catch (error) {
            console.error('Failed to check all devices:', error);
            alert('An error occurred while checking all devices.');
        } finally {
            icon.classList.remove('fa-spin');
            bulkCheckBtn.disabled = false;
        }
    });

    closeDetailsModal.addEventListener('click', () => detailsModal.classList.add('hidden'));

    loadDevices();
});