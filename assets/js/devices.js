function initDevices() {
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
                    <a href="/map.php?map_id=${device.map_id}&edit_device_id=${device.id}" data-navigo class="text-yellow-400 hover:text-yellow-300 mr-3" title="Edit Device"><i class="fas fa-edit"></i></a>
                    <button class="check-device-btn text-green-400 hover:text-green-300 mr-3" data-id="${device.id}" title="Check Status"><i class="fas fa-sync"></i></button>
                    <button class="delete-device-btn text-red-500 hover:text-red-400" data-id="${device.id}" title="Delete Device"><i class="fas fa-trash"></i></button>
                </td>
            </tr>
        `;
    };

    const loadDevices = async (mapId) => {
        tableLoader.classList.remove('hidden');
        noDevicesMessage.classList.add('hidden');
        devicesTableBody.innerHTML = '';
        
        try {
            const devices = await api.get('get_devices', mapId ? { map_id: mapId } : {});
            if (devices.length > 0) {
                devicesTableBody.innerHTML = devices.map(renderDeviceRow).join('');
                window.router.updatePageLinks();
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

        const [details, uptimeData] = await Promise.all([
            api.get('get_device_details', { id: deviceId }),
            api.get('get_device_uptime', { id: deviceId })
        ]);
        const { device, history } = details;
        
        detailsModalTitle.textContent = `${device.name} (${device.ip || 'No IP'})`;
        
        const renderThreshold = (label, value, unit) => value ? `<strong>${label}:</strong> <span>${value}${unit}</span>` : '';
        const uptimeStatsHtml = device.ip
            ? uptimeData.uptime_24h !== null
                ? `
                    <strong class="text-slate-400">Uptime (24h):</strong> <span class="text-white font-semibold">${uptimeData.uptime_24h}%</span>
                    <strong class="text-slate-400">Uptime (7d):</strong> <span class="text-white font-semibold">${uptimeData.uptime_7d !== null ? uptimeData.uptime_7d + '%' : 'N/A'}</span>
                    <strong class="text-slate-400">Outages (24h):</strong> <span class="text-white font-semibold">${uptimeData.outages_24h}</span>
                `
                : '<span class="text-slate-500 col-span-2">Not enough data to calculate uptime.</span>'
            : '<span class="text-slate-500 col-span-2">Uptime not applicable (no IP).</span>';

        detailsModalContent.innerHTML = `
            <div class="grid grid-cols-1 md:grid-cols-5 gap-6">
                <div class="md:col-span-2 space-y-4">
                    <div>
                        <h3 class="text-lg font-semibold text-white mb-2 border-b border-slate-700 pb-1">Configuration</h3>
                        <div class="grid grid-cols-2 gap-x-4 gap-y-1 text-sm">
                            <strong class="text-slate-400">Type:</strong> <span class="text-white capitalize">${device.type}</span>
                            <strong class="text-slate-400">Map:</strong> <span class="text-white">${device.map_name}</span>
                            <strong class="text-slate-400">Ping Interval:</strong> <span class="text-white">${device.ping_interval ? `${device.ping_interval}s` : 'Disabled'}</span>
                            <strong class="text-slate-400">Live Ping:</strong> <span class="text-white">${device.show_live_ping ? 'Enabled' : 'Disabled'}</span>
                        </div>
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold text-white mb-2 border-b border-slate-700 pb-1">Status Thresholds</h3>
                        <div class="grid grid-cols-1 gap-x-4 gap-y-1 text-sm">
                            <div class="text-yellow-400">${renderThreshold('Warning Latency', device.warning_latency_threshold, 'ms')}</div>
                            <div class="text-yellow-400">${renderThreshold('Warning Packet Loss', device.warning_packetloss_threshold, '%')}</div>
                            <div class="text-red-400">${renderThreshold('Critical Latency', device.critical_latency_threshold, 'ms')}</div>
                            <div class="text-red-400">${renderThreshold('Critical Packet Loss', device.critical_packetloss_threshold, '%')}</div>
                        </div>
                    </div>
                     <div>
                        <h3 class="text-lg font-semibold text-white mb-2 border-b border-slate-700 pb-1">Uptime Statistics</h3>
                        <div class="grid grid-cols-2 gap-x-4 gap-y-1 text-sm">${uptimeStatsHtml}</div>
                    </div>
                </div>
                <div class="md:col-span-3">
                    <h3 class="text-lg font-semibold text-white mb-2 border-b border-slate-700 pb-1">Recent Latency (ms)</h3>
                    <div class="h-48 bg-slate-900/50 p-2 rounded-lg">
                        ${history.length > 0 ? '<canvas id="latencyChart"></canvas>' : '<div class="flex items-center justify-center h-full text-slate-500">No ping history available.</div>'}
                    </div>
                </div>
            </div>
        `;

        if (history.length > 0) {
            const chartCtx = document.getElementById('latencyChart').getContext('2d');
            const chartData = history.slice().reverse(); // oldest to newest
            latencyChart = new Chart(chartCtx, {
                type: 'line',
                data: {
                    labels: chartData.map(h => new Date(h.created_at).toLocaleTimeString()),
                    datasets: [{
                        label: 'Avg Time (ms)',
                        data: chartData.map(h => h.avg_time),
                        borderColor: '#22d3ee',
                        backgroundColor: 'rgba(34, 211, 238, 0.1)',
                        fill: true,
                        tension: 0.3,
                        pointRadius: 2,
                    }]
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    scales: { 
                        y: { beginAtZero: true, ticks: { color: '#94a3b8' }, grid: { color: '#334155' } },
                        x: { ticks: { color: '#94a3b8' }, grid: { color: '#334155' } }
                    },
                    plugins: { legend: { display: false } }
                }
            });
        }

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
                const currentMapId = document.getElementById('mapSelector')?.value;
                await loadDevices(currentMapId);
            } catch (error) { console.error('Failed to check device:', error); }
            finally { button.disabled = false; icon.classList.remove('fa-spin'); }
        }

        if (button.classList.contains('delete-device-btn')) {
            if (confirm('Are you sure you want to delete this device?')) {
                await api.post('delete_device', { id: deviceId });
                window.notyf.success('Device deleted successfully.');
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
            const mapId = document.getElementById('mapSelector')?.value;
            if (mapId) {
                const result = await api.post('ping_all_devices', { map_id: mapId });
                if (result.success && result.updated_devices) {
                    let changes = 0;
                    result.updated_devices.forEach(device => {
                        if (device.old_status !== device.status) {
                            changes++;
                            window.notyf.info(`'${device.name}' is now ${device.status}.`);
                        }
                    });
                    if (changes === 0 && result.updated_devices.length > 0) {
                        window.notyf.success('All device statuses are stable.');
                    }
                    await loadDevices(mapId); // Reload the table to show new statuses
                }
            } else {
                window.notyf.error("Please select a map first.");
            }
        } catch (error) {
            console.error('Failed to check all devices:', error);
        } finally {
            icon.classList.remove('fa-spin');
            bulkCheckBtn.disabled = false;
        }
    });

    closeDetailsModal.addEventListener('click', () => detailsModal.classList.add('hidden'));

    createMapSelector('map-selector-container', loadDevices).then(selector => {
        if (selector) {
            const urlParams = new URLSearchParams(window.location.search);
            const mapIdFromUrl = urlParams.get('map_id');
            if (mapIdFromUrl) {
                selector.value = mapIdFromUrl;
            }
            loadDevices(selector.value);
        }
    });
}