document.addEventListener('DOMContentLoaded', function() {
    const API_URL = 'api.php';
    const api = {
        get: (action, params = {}) => fetch(`${API_URL}?action=${action}&${new URLSearchParams(params)}`).then(res => res.json()),
        post: (action, body) => fetch(`${API_URL}?action=${action}`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) }).then(res => res.json())
    };

    let network = null, nodes = new vis.DataSet([]), edges = new vis.DataSet([]), currentMapId = null, pingIntervals = {};

    const mapWrapper = document.getElementById('network-map-wrapper'), mapSelector = document.getElementById('mapSelector'),
        newMapBtn = document.getElementById('newMapBtn'), deleteMapBtn = document.getElementById('deleteMapBtn'),
        mapContainer = document.getElementById('map-container'), noMapsContainer = document.getElementById('no-maps'),
        createFirstMapBtn = document.getElementById('createFirstMapBtn'), currentMapName = document.getElementById('currentMapName'),
        refreshStatusBtn = document.getElementById('refreshStatusBtn'), addDeviceBtn = document.getElementById('addDeviceBtn'),
        addEdgeBtn = document.getElementById('addEdgeBtn'), deleteModeBtn = document.getElementById('deleteModeBtn'),
        fullscreenBtn = document.getElementById('fullscreenBtn'), exportBtn = document.getElementById('exportBtn'),
        importBtn = document.getElementById('importBtn'), importFile = document.getElementById('importFile');

    const deviceModal = document.getElementById('deviceModal'), deviceForm = document.getElementById('deviceForm'),
        cancelBtn = document.getElementById('cancelBtn');
    const edgeModal = document.getElementById('edgeModal'), edgeForm = document.getElementById('edgeForm'),
        cancelEdgeBtn = document.getElementById('cancelEdgeBtn');

    const iconMap = { server: '\uf233', router: '\uf637', switch: '\uf796', printer: '\uf02f', nas: '\uf0a0', camera: '\uf030', other: '\uf108', firewall: '\uf3ed', ipphone: '\uf87d', punchdevice: '\uf2c2' };
    const statusColorMap = { online: '#22c55e', offline: '#ef4444', unknown: '#f59e0b' };
    const edgeColorMap = { cat5: '#a78bfa', fiber: '#f97316', wifi: '#38bdf8', radio: '#84cc16' };

    const createMap = async () => {
        const name = prompt("Enter a name for the new map:");
        if (name) { const newMap = await api.post('create_map', { name }); await loadMaps(); mapSelector.value = newMap.id; await switchMap(newMap.id); }
    };

    const loadMaps = async () => {
        const maps = await api.get('get_maps');
        mapSelector.innerHTML = '';
        if (maps.length > 0) {
            maps.forEach(map => { const option = document.createElement('option'); option.value = map.id; option.textContent = map.name; mapSelector.appendChild(option); });
            mapContainer.classList.remove('hidden'); noMapsContainer.classList.add('hidden'); return maps[0].id;
        } else { mapContainer.classList.add('hidden'); noMapsContainer.classList.remove('hidden'); return null; }
    };

    const switchMap = async (mapId) => {
        if (!mapId) { if (network) network.destroy(); network = null; nodes.clear(); edges.clear(); mapContainer.classList.add('hidden'); noMapsContainer.classList.remove('hidden'); return; }
        currentMapId = mapId; currentMapName.textContent = mapSelector.options[mapSelector.selectedIndex].text;
        const [deviceData, edgeData] = await Promise.all([api.get('get_devices', { map_id: mapId }), api.get('get_edges', { map_id: mapId })]);
        
        const visNodes = deviceData.map(d => ({
            id: d.id, label: d.name, title: `${d.name}<br>${d.ip}<br>Status: ${d.status}`, x: d.x, y: d.y,
            shape: 'icon', icon: { face: "'Font Awesome 6 Free'", weight: "900", code: iconMap[d.type] || iconMap.other, size: parseInt(d.icon_size) || 50, color: statusColorMap[d.status] || statusColorMap.unknown },
            font: { color: 'white', size: parseInt(d.name_text_size) || 14 },
            deviceData: d
        }));
        nodes.clear(); nodes.add(visNodes);

        const visEdges = edgeData.map(e => ({ id: e.id, from: e.source_id, to: e.target_id, connection_type: e.connection_type }));
        edges.clear(); edges.add(visEdges);
        updateEdgeStyles();
        setupAutoPing(deviceData);
        if (!network) initializeMap();
    };

    const initializeMap = () => {
        const container = document.getElementById('network-map');
        const data = { nodes, edges };
        const options = { physics: false, interaction: { hover: true }, edges: { smooth: true, width: 2 }, manipulation: { enabled: false, addEdge: async (edgeData, callback) => { const newEdge = await api.post('create_edge', { source_id: edgeData.from, target_id: edgeData.to, map_id: currentMapId }); edgeData.id = newEdge.id; callback(edgeData); updateEdgeStyles(); }, deleteNode: async (data, callback) => { if (confirm(`Delete ${data.nodes.length} device(s)?`)) { for (const nodeId of data.nodes) { await api.post('delete_device', { id: nodeId }); } callback(data); } }, deleteEdge: async (data, callback) => { if (confirm(`Delete ${data.edges.length} connection(s)?`)) { for (const edgeId of data.edges) { await api.post('delete_edge', { id: edgeId }); } callback(data); } } } };
        network = new vis.Network(container, data, options);
        network.on("dragEnd", async (params) => { if (params.nodes.length > 0) { const nodeId = params.nodes[0]; const position = network.getPositions([nodeId])[nodeId]; await api.post('update_device', { id: nodeId, updates: { x: position.x, y: position.y } }); } });
        network.on("doubleClick", (params) => { if (params.nodes.length > 0) openDeviceModal(params.nodes[0]); });
        network.on("click", (params) => { if (params.edges.length > 0) openEdgeModal(params.edges[0]); });
    };

    const openDeviceModal = (deviceId = null) => {
        deviceForm.reset();
        if (deviceId) {
            const node = nodes.get(deviceId);
            document.getElementById('modalTitle').textContent = 'Edit Device';
            document.getElementById('deviceId').value = node.id;
            document.getElementById('deviceName').value = node.label;
            document.getElementById('deviceIp').value = node.deviceData.ip;
            document.getElementById('deviceType').value = node.deviceData.type;
            document.getElementById('pingInterval').value = node.deviceData.ping_interval;
            document.getElementById('iconSize').value = node.deviceData.icon_size;
            document.getElementById('nameTextSize').value = node.deviceData.name_text_size;
        } else { document.getElementById('modalTitle').textContent = 'Add Device'; }
        deviceModal.classList.remove('hidden');
    };

    const openEdgeModal = (edgeId) => {
        const edge = edges.get(edgeId);
        document.getElementById('edgeId').value = edge.id;
        document.getElementById('connectionType').value = edge.connection_type || 'cat5';
        edgeModal.classList.remove('hidden');
    };

    const updateEdgeStyles = () => {
        const deviceStatusMap = new Map(nodes.get().map(d => [d.id, d.deviceData.status]));
        const edgeUpdates = edges.get().map(edge => {
            const sourceStatus = deviceStatusMap.get(edge.from);
            const targetStatus = deviceStatusMap.get(edge.to);
            const isOffline = sourceStatus === 'offline' || targetStatus === 'offline';
            const color = isOffline ? statusColorMap.offline : (edgeColorMap[edge.connection_type] || edgeColorMap.cat5);
            const dashes = edge.connection_type === 'wifi' || edge.connection_type === 'radio';
            return { id: edge.id, color, dashes };
        });
        edges.update(edgeUpdates);
    };

    const setupAutoPing = (devices) => {
        Object.values(pingIntervals).forEach(clearInterval);
        pingIntervals = {};
        devices.forEach(device => {
            if (device.ping_interval > 0) {
                pingIntervals[device.id] = setInterval(() => pingSingleDevice(device.id), device.ping_interval * 1000);
            }
        });
    };

    const pingSingleDevice = async (deviceId) => {
        const node = nodes.get(deviceId);
        if (!node) return;
        const result = await api.post('check_device', { id: deviceId });
        nodes.update({ id: deviceId, deviceData: { ...node.deviceData, status: result.status }, icon: { color: statusColorMap[result.status] }, title: `${node.label}<br>${node.deviceData.ip}<br>Status: ${result.status}` });
        updateEdgeStyles();
    };

    deviceForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const id = document.getElementById('deviceId').value;
        const deviceData = { name: document.getElementById('deviceName').value, ip: document.getElementById('deviceIp').value, type: document.getElementById('deviceType').value, map_id: currentMapId, ping_interval: document.getElementById('pingInterval').value || null, icon_size: document.getElementById('iconSize').value || 50, name_text_size: document.getElementById('nameTextSize').value || 14 };
        if (id) { await api.post('update_device', { id, updates: deviceData }); } else { await api.post('create_device', deviceData); }
        deviceModal.classList.add('hidden'); await switchMap(currentMapId);
    });

    edgeForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const id = document.getElementById('edgeId').value;
        const connection_type = document.getElementById('connectionType').value;
        await api.post('update_edge', { id, connection_type });
        edgeModal.classList.add('hidden');
        edges.update({ id, connection_type });
        updateEdgeStyles();
    });

    refreshStatusBtn.addEventListener('click', async () => {
        const icon = refreshStatusBtn.querySelector('i'); icon.classList.add('fa-spin');
        await api.post('ping_all_devices', { map_id: currentMapId });
        await switchMap(currentMapId);
        icon.classList.remove('fa-spin');
    });

    exportBtn.addEventListener('click', async () => {
        const devices = nodes.get({ fields: ['id', 'label', 'x', 'y', 'deviceData'] }).map(n => ({ id: n.id, name: n.label, ip: n.deviceData.ip, type: n.deviceData.type, x: n.x, y: n.y, ping_interval: n.deviceData.ping_interval, icon_size: n.deviceData.icon_size, name_text_size: n.deviceData.name_text_size }));
        const edgesToExport = edges.get({ fields: ['from', 'to', 'connection_type'] });
        const data = JSON.stringify({ devices, edges: edgesToExport }, null, 2);
        const blob = new Blob([data], { type: 'application/json' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a'); a.href = url; a.download = `${currentMapName.textContent.replace(/\s/g, '_')}.json`; a.click(); URL.revokeObjectURL(url);
    });

    importBtn.addEventListener('click', () => importFile.click());
    importFile.addEventListener('change', (e) => {
        const file = e.target.files[0]; if (!file) return;
        if (confirm('This will overwrite the current map. Are you sure?')) {
            const reader = new FileReader();
            reader.onload = async (event) => {
                try {
                    const data = JSON.parse(event.target.result);
                    await api.post('import_map', { map_id: currentMapId, ...data });
                    await switchMap(currentMapId);
                } catch (err) { alert('Failed to import map: ' + err.message); }
            };
            reader.readAsText(file);
        }
        importFile.value = '';
    });

    fullscreenBtn.addEventListener('click', () => { if (!document.fullscreenElement) { mapWrapper.requestFullscreen(); } else { document.exitFullscreen(); } });
    document.addEventListener('fullscreenchange', () => { const icon = fullscreenBtn.querySelector('i'); icon.classList.toggle('fa-expand', !document.fullscreenElement); icon.classList.toggle('fa-compress', !!document.fullscreenElement); });

    newMapBtn.addEventListener('click', createMap); createFirstMapBtn.addEventListener('click', createMap);
    deleteMapBtn.addEventListener('click', async () => { if (confirm(`Delete map "${mapSelector.options[mapSelector.selectedIndex].text}"?`)) { await api.post('delete_map', { id: currentMapId }); const firstMapId = await loadMaps(); await switchMap(firstMapId); } });
    mapSelector.addEventListener('change', (e) => switchMap(e.target.value));
    addDeviceBtn.addEventListener('click', () => openDeviceModal()); cancelBtn.addEventListener('click', () => deviceModal.classList.add('hidden'));
    addEdgeBtn.addEventListener('click', () => { network.addEdgeMode(); }); deleteModeBtn.addEventListener('click', () => { network.deleteSelected(); });
    cancelEdgeBtn.addEventListener('click', () => edgeModal.classList.add('hidden'));

    (async () => { const firstMapId = await loadMaps(); if (firstMapId) { await switchMap(firstMapId); } })();
});