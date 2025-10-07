document.addEventListener('DOMContentLoaded', function() {
    const API_URL = 'api.php';
    const historyChartCanvas = document.getElementById('historyChart');
    const historyTableBody = document.getElementById('historyTableBody');
    const chartLoader = document.getElementById('chartLoader');
    const tableLoader = document.getElementById('tableLoader');
    const chartContainer = document.getElementById('chartContainer');
    const filterForm = document.getElementById('historyFilterForm');
    const hostSelector = document.getElementById('hostSelector');
    const exportLink = document.getElementById('exportLink');

    let historyChart = null;

    const api = {
        get: (action, params = {}) => fetch(`${API_URL}?action=${action}&${new URLSearchParams(params)}`).then(res => res.json())
    };

    const loadHistoryData = async (host) => {
        chartLoader.classList.remove('hidden');
        tableLoader.classList.remove('hidden');
        chartContainer.classList.add('hidden');
        historyTableBody.innerHTML = '';

        try {
            const historyData = await api.get('get_ping_history', { host: host, limit: 100 });
            
            // Update table (show newest first)
            const reversedData = [...historyData].reverse();
            historyTableBody.innerHTML = reversedData.map(item => {
                const statusClass = item.success ? 'bg-green-500/20 text-green-400' : 'bg-red-500/20 text-red-400';
                const statusText = item.success ? 'Success' : 'Failed';
                const lossClass = item.packet_loss > 0 ? 'text-orange-400' : 'text-green-400';

                return `
                    <tr class="border-b border-slate-700 hover:bg-slate-800/50">
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-mono text-white">${item.host}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-400">${new Date(item.created_at).toLocaleString()}</td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${statusClass}">${statusText}</span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm ${lossClass}">${item.packet_loss}%</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-400">${item.avg_time}ms</td>
                    </tr>
                `;
            }).join('');

            // Update chart (show oldest to newest)
            if (historyChart) {
                historyChart.destroy();
            }

            const chartCtx = historyChartCanvas.getContext('2d');
            historyChart = new Chart(chartCtx, {
                type: 'line',
                data: {
                    labels: historyData.map(h => new Date(h.created_at).toLocaleTimeString()),
                    datasets: [
                        {
                            label: 'Avg Latency (ms)',
                            data: historyData.map(h => h.avg_time),
                            borderColor: '#22d3ee',
                            backgroundColor: 'rgba(34, 211, 238, 0.1)',
                            fill: true,
                            yAxisID: 'yLatency',
                            tension: 0.3,
                        },
                        {
                            label: 'Packet Loss (%)',
                            data: historyData.map(h => h.packet_loss),
                            borderColor: '#f43f5e',
                            backgroundColor: 'rgba(244, 63, 94, 0.1)',
                            fill: true,
                            yAxisID: 'yPacketLoss',
                            stepped: true,
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        yLatency: {
                            type: 'linear',
                            display: true,
                            position: 'left',
                            beginAtZero: true,
                            ticks: { color: '#94a3b8' },
                            grid: { color: '#334155' },
                            title: { display: true, text: 'Latency (ms)', color: '#22d3ee' }
                        },
                        yPacketLoss: {
                            type: 'linear',
                            display: true,
                            position: 'right',
                            min: 0,
                            max: 100,
                            ticks: { color: '#94a3b8' },
                            grid: { drawOnChartArea: false }, // only show grid for latency
                            title: { display: true, text: 'Packet Loss (%)', color: '#f43f5e' }
                        },
                        x: {
                            ticks: { color: '#94a3b8' },
                            grid: { color: '#334155' }
                        }
                    },
                    plugins: {
                        legend: { labels: { color: '#cbd5e1' } },
                        tooltip: {
                            mode: 'index',
                            intersect: false
                        }
                    },
                    interaction: {
                        mode: 'index',
                        intersect: false
                    }
                }
            });

            chartContainer.classList.remove('hidden');

        } catch (error) {
            console.error('Failed to load history:', error);
            historyTableBody.innerHTML = '<tr><td colspan="5" class="text-center text-red-400 py-4">Failed to load history data.</td></tr>';
        } finally {
            chartLoader.classList.add('hidden');
            tableLoader.classList.add('hidden');
        }
    };

    filterForm.addEventListener('submit', (e) => {
        e.preventDefault();
        const selectedHost = hostSelector.value;
        // Update URL without reloading
        const url = new URL(window.location);
        url.searchParams.set('host', selectedHost);
        window.history.pushState({}, '', url);
        
        // Update export link
        exportLink.href = `export.php?host=${encodeURIComponent(selectedHost)}`;

        loadHistoryData(selectedHost);
    });

    // Initial load
    const initialHost = new URLSearchParams(window.location.search).get('host') || '';
    loadHistoryData(initialHost);
});