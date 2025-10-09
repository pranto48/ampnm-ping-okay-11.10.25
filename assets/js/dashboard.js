function initDashboard() {
    const API_URL = 'api.php';
    const dashboardLoader = document.getElementById('dashboardLoader');
    const dashboardWidgets = document.getElementById('dashboard-widgets');

    const statusChartCanvas = document.getElementById('statusChart');
    const totalDevicesText = document.getElementById('totalDevicesText');
    const onlineCountEl = document.getElementById('onlineCount');
    const warningCountEl = document.getElementById('warningCount');
    const criticalCountEl = document.getElementById('criticalCount');
    const offlineCountEl = document.getElementById('offlineCount');
    const deviceListEl = document.getElementById('deviceList');
    const manageDevicesLink = document.getElementById('manageDevicesLink');
    let statusChart = null;

    const pingForm = document.getElementById('pingForm');
    const pingHostInput = document.getElementById('pingHostInput');
    const pingButton = document.getElementById('pingButton');
    const pingResultContainer = document.getElementById('pingResultContainer');
    const pingResultPre = document.getElementById('pingResultPre');

    const api = {
        get: (action, params = {}) => fetch(`${API_URL}?action=${action}&${new URLSearchParams(params)}`).then(res => res.json()),
        post: (action, body) => fetch(`${API_URL}?action=${action}`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) }).then(res => res.json())
    };

    const loadDashboardData = async (mapId) => {
        if (!mapId) {
            dashboardLoader.classList.add('hidden');
            return;
        }
        dashboardLoader.classList.remove('hidden');
        dashboardWidgets.classList.add('hidden');
        manageDevicesLink.href = `/devices.php?map_id=${mapId}`;
        manageDevicesLink.setAttribute('data-navigo', true);
        window.router.updatePageLinks();

        try {
            const data = await api.get('get_dashboard_data', { map_id: mapId });
            
            totalDevicesText.querySelector('span:first-child').textContent = data.stats.total;
            onlineCountEl.textContent = data.stats.online;
            warningCountEl.textContent = data.stats.warning;
            criticalCountEl.textContent = data.stats.critical;
            offlineCountEl.textContent = data.stats.offline;

            if (statusChart) {
                statusChart.destroy();
            }
            const chartData = {
                labels: ['Online', 'Warning', 'Critical', 'Offline'],
                datasets: [{
                    data: [data.stats.online, data.stats.warning, data.stats.critical, data.stats.offline],
                    backgroundColor: ['#22c55e', '#f59e0b', '#ef4444', '#64748b'],
                    borderColor: '#1e293b',
                    borderWidth: 4,
                }]
            };
            statusChart = new Chart(statusChartCanvas, {
                type: 'doughnut',
                data: chartData,
                options: {
                    responsive: true,
                    cutout: '75%',
                    plugins: { legend: { display: false }, tooltip: { enabled: true } }
                }
            });

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
        } finally {
            dashboardLoader.classList.add('hidden');
            dashboardWidgets.classList.remove('hidden');
        }
    };

    createMapSelector('map-selector-container', loadDashboardData).then(selector => {
        if (selector) {
            loadDashboardData(selector.value);
        } else {
            dashboardLoader.classList.add('hidden');
        }
    });

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
}