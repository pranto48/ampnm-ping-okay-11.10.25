document.addEventListener('DOMContentLoaded', function() {
    const API_URL = 'api.php';
    const mapSelector = document.getElementById('mapSelector');
    const dashboardLoader = document.getElementById('dashboardLoader');
    const dashboardWidgets = document.getElementById('dashboard-widgets');

    const networkStatusEl = document.getElementById('networkStatus');
    const devicesOnlineEl = document.getElementById('devicesOnline');
    const lastCheckEl = document.getElementById('lastCheck');
    const deviceListEl = document.getElementById('deviceList');
    const manageDevicesLink = document.getElementById('manageDevicesLink');

    const pingForm = document.getElementById('pingForm');
    const pingHostInput = document.getElementById('pingHostInput');
    const pingButton = document.getElementById('pingButton');
    const pingResultContainer = document.getElementById('pingResultContainer');
    const pingResultPre = document.getElementById('pingResultPre');

    const api = {
        get: (action, params = {}) => fetch(`${API_URL}?action=${action}&${new URLSearchParams(params)}`).then(res => res.json()),
        post: (action, body) => fetch(`${API_URL}?action=${action}`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) }).then(res => res.json())
    };

    const loadDashboard = async (mapId) => {
        if (!mapId) {
            dashboardLoader.classList.add('hidden');
            return;
        }
        dashboardLoader.classList.remove('hidden');
        dashboardWidgets.classList.add('hidden');
        manageDevicesLink.href = `devices.php?map_id=${mapId}`;

        try {
            const data = await api.get('get_dashboard_data', { map_id: mapId });
            
            // Update stats
            const isOnline = data.stats.online > 0;
            networkStatusEl.textContent = isOnline ? 'Systems Operational' : 'Systems Offline';
            networkStatusEl.className = `text-2xl font-bold mt-2 ${isOnline ? 'text-green-400' : 'text-red-400'}`;
            devicesOnlineEl.textContent = `${data.stats.online}/${data.stats.total}`;
            lastCheckEl.textContent = new Date().toLocaleTimeString();

            // Update device list
            deviceListEl.innerHTML = data.devices.map(device => `
                <div class="border border-slate-700 rounded-lg p-3 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="status-indicator status-${device.status}"></div>
                        <div>
                            <div class="font-medium text-white">${device.name}</div>
                            <div class="text-sm text-slate-400 font-mono">${device.ip}</div>
                        </div>
                    </div>
                </div>
            `).join('') || '<p class="text-slate-500 text-center">No devices on this map.</p>';

        } catch (error) {
            console.error("Failed to load dashboard data:", error);
            // You could show an error message here
        } finally {
            dashboardLoader.classList.add('hidden');
            dashboardWidgets.classList.remove('hidden');
        }
    };

    if (mapSelector) {
        mapSelector.addEventListener('change', () => {
            loadDashboard(mapSelector.value);
        });
        // Initial load
        loadDashboard(mapSelector.value);
    } else {
        dashboardLoader.classList.add('hidden');
    }

    // Manual Ping Logic
    pingForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const host = pingHostInput.value.trim();
        if (!host) return;

        pingButton.disabled = true;
        pingButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Pinging...';
        pingResultContainer.classList.remove('hidden');
        pingResultPre.textContent = `Pinging ${host}...`;

        try {
            const result = await api.post('manual_ping', { host });
            pingResultPre.textContent = result.output || `Error: ${result.error || 'Unknown error'}`;
        } catch (error) {
            pingResultPre.textContent = `Failed to perform ping. Check API connection.`;
        } finally {
            pingButton.disabled = false;
            pingButton.innerHTML = '<i class="fas fa-bolt mr-2"></i>Ping';
        }
    });
});