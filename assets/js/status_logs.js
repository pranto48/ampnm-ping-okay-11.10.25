function initStatusLogs() {
    const API_URL = 'api.php';
    let statusLogChart = null;
    let liveInterval = null;

    const els = {
        mapSelector: document.getElementById('mapSelector'),
        deviceSelector: document.getElementById('deviceSelector'),
        periodSelector: document.getElementById('periodSelector'),
        chartTitle: document.getElementById('chartTitle'),
        chartLoader: document.getElementById('chartLoader'),
        chartContainer: document.getElementById('chartContainer'),
        statusLogChartCanvas: document.getElementById('statusLogChart'),
        noDataMessage: document.getElementById('noDataMessage'),
        detailedLogTableBody: document.getElementById('detailedLogTableBody'),
        detailedTableLoader: document.getElementById('detailedTableLoader'),
        noTableDataMessage: document.getElementById('noTableDataMessage'),
    };

    const state = {
        currentMapId: null,
        currentDeviceId: '',
        currentPeriod: '24h',
    };

    const api = {
        get: (action, params = {}) => fetch(`${API_URL}?action=${action}&${new URLSearchParams(params)}`).then(res => res.json())
    };

    const populateMapSelector = async () => {
        const maps = await api.get('get_maps');
        if (maps.length > 0) {
            els.mapSelector.innerHTML = maps.map(map => `<option value="${map.id}">${map.name}</option>`).join('');
            state.currentMapId = maps[0].id;
        } else {
            els.mapSelector.innerHTML = '<option>No maps found</option>';
        }
    };

    const populateDeviceSelector = async () => {
        if (!state.currentMapId) return;
        const devices = await api.get('get_devices', { map_id: state.currentMapId });
        els.deviceSelector.innerHTML = '<option value="">All Devices</option>' + 
            devices.map(d => `<option value="${d.id}">${d.name} (${d.ip || 'No IP'})</option>`).join('');
    };

    const loadData = async () => {
        if (liveInterval) clearInterval(liveInterval);
        
        // Show loaders
        els.chartLoader.classList.remove('hidden');
        els.chartContainer.classList.add('hidden');
        els.noDataMessage.classList.add('hidden');
        els.detailedTableLoader.classList.remove('hidden');
        els.detailedLogTableBody.innerHTML = '';
        els.noTableDataMessage.classList.add('hidden');

        if (statusLogChart) statusLogChart.destroy();

        const params = {
            map_id: state.currentMapId,
            device_id: state.currentDeviceId,
            period: state.currentPeriod
        };

        try {
            const [chartData, logData] = await Promise.all([
                api.get('get_status_logs', params),
                api.get('get_detailed_status_logs', params)
            ]);

            // Render Chart
            els.chartLoader.classList.add('hidden');
            if (chartData.length === 0) {
                els.noDataMessage.classList.remove('hidden');
            } else {
                els.chartContainer.classList.remove('hidden');
                const labels = chartData.map(d => d.time_group);
                const datasets = [
                    { label: 'Critical', data: chartData.map(d => d.critical_count), backgroundColor: '#ef4444' },
                    { label: 'Warning', data: chartData.map(d => d.warning_count), backgroundColor: '#f59e0b' },
                    { label: 'Offline', data: chartData.map(d => d.offline_count), backgroundColor: '#64748b' },
                ];
                statusLogChart = new Chart(els.statusLogChartCanvas, {
                    type: 'bar',
                    data: { labels, datasets },
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        plugins: { legend: { labels: { color: '#cbd5e1' } } },
                        scales: {
                            x: { type: 'time', time: { unit: state.currentPeriod === '24h' || state.currentPeriod === 'live' ? 'hour' : 'day' }, ticks: { color: '#94a3b8' }, grid: { color: '#334155' } },
                            y: { stacked: true, beginAtZero: true, ticks: { color: '#94a3b8', stepSize: 1 }, grid: { color: '#334155' } }
                        }
                    }
                });
            }

            // Render Detailed Log Table
            els.detailedTableLoader.classList.add('hidden');
            if (logData.length === 0) {
                els.noTableDataMessage.classList.remove('hidden');
            } else {
                const statusColors = {
                    online: 'text-green-400', warning: 'text-yellow-400', critical: 'text-red-400',
                    offline: 'text-slate-400', unknown: 'text-slate-500'
                };
                els.detailedLogTableBody.innerHTML = logData.map(log => {
                    const oldStatusHtml = log.old_status ? `<span class="${statusColors[log.old_status] || ''} capitalize">${log.old_status}</span>` : 'N/A';
                    const newStatusHtml = `<span class="${statusColors[log.status] || ''} capitalize font-bold">${log.status}</span>`;
                    return `
                        <tr class="border-b border-slate-700">
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-400">${new Date(log.created_at).toLocaleString()}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <div class="font-medium text-white">${log.device_name}</div>
                                <div class="text-slate-500 font-mono">${log.device_ip || ''}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">${oldStatusHtml} &rarr; ${newStatusHtml}</td>
                            <td class="px-6 py-4 text-sm text-slate-400 font-mono">${log.details || ''}</td>
                        </tr>
                    `;
                }).join('');
            }

        } catch (error) {
            console.error("Failed to load status log data:", error);
            els.chartLoader.classList.add('hidden');
            els.detailedTableLoader.classList.add('hidden');
            els.noDataMessage.classList.remove('hidden');
            els.noTableDataMessage.classList.remove('hidden');
        }

        if (state.currentPeriod === 'live') {
            liveInterval = setInterval(loadData, 30000);
        }
    };

    const updateFilters = () => {
        state.currentMapId = els.mapSelector.value;
        state.currentDeviceId = els.deviceSelector.value;
        loadData();
    };

    els.mapSelector.addEventListener('change', async () => {
        state.currentMapId = els.mapSelector.value;
        await populateDeviceSelector();
        state.currentDeviceId = ''; // Reset device filter
        loadData();
    });

    els.deviceSelector.addEventListener('change', updateFilters);

    els.periodSelector.addEventListener('click', (e) => {
        if (e.target.tagName === 'BUTTON') {
            state.currentPeriod = e.target.dataset.period;
            els.periodSelector.querySelectorAll('button').forEach(btn => btn.classList.remove('bg-slate-700', 'text-white'));
            e.target.classList.add('bg-slate-700', 'text-white');
            els.chartTitle.textContent = `Status Events in the Last ${e.target.textContent}`;
            if (state.currentPeriod === 'live') els.chartTitle.textContent = 'Live Status Events (Last 1 Hour)';
            loadData();
        }
    });

    // Initial Load
    (async () => {
        await populateMapSelector();
        await populateDeviceSelector();
        await loadData();
    })();
}