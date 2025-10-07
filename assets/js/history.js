function initHistory() {
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
            
            const reversedData = [...historyData].reverse();
            historyTableBody.innerHTML = reversedData.map(item => `...`).join(''); // Assuming correct

            if (historyChart) historyChart.destroy();
            // ... Chart creation logic ...

            chartContainer.classList.remove('hidden');

        } catch (error) {
            console.error('Failed to load history:', error);
        } finally {
            chartLoader.classList.add('hidden');
            tableLoader.classList.add('hidden');
        }
    };

    const populateHostSelector = async () => {
        const devices = await api.get('get_devices');
        const hosts = [...new Set(devices.filter(d => d.ip).map(d => d.ip))].sort();
        hostSelector.innerHTML = '<option value="">All Hosts</option>' + hosts.map(h => `<option value="${h}">${h}</option>`).join('');
    };

    filterForm.addEventListener('submit', (e) => {
        e.preventDefault();
        const selectedHost = hostSelector.value;
        exportLink.href = `export.php?host=${encodeURIComponent(selectedHost)}`;
        loadHistoryData(selectedHost);
    });

    populateHostSelector().then(() => {
        const initialHost = new URLSearchParams(window.location.search).get('host') || '';
        if(initialHost) hostSelector.value = initialHost;
        loadHistoryData(initialHost);
    });
}