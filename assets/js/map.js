document.addEventListener('DOMContentLoaded', function() {
    const API_URL = 'api.php';
    const api = {
        get: (action, params = {}) => fetch(`${API_URL}?action=${action}&${new URLSearchParams(params)}`).then(res => res.json()),
        post: (action, body) => fetch(`${API_URL}?action=${action}`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) }).then(res => res.json())
    };

    let network = null;
    let nodes = new vis.DataSet([]);
    let edges = new vis.DataSet([]);
    let currentMapId = null;

    const mapWrapper = document.getElementById('network-map-wrapper');
    const mapSelector = document.getElementById('mapSelector');
    const newMapBtn = document.getElementById('newMapBtn');
    const deleteMapBtn = document.getElementById('deleteMapBtn');
    const mapContainer = document.getElementById('map-container');
    const noMapsContainer = document.getElementById('no-maps');
    const createFirstMapBtn = document.getElementById('createFirstMapBtn');
    const currentMapName = document.getElementById('currentMapName');
    
    const refreshStatusBtn = document.getElementById('refreshStatusBtn');
    const addDeviceBtn = document.getElementById('addDeviceBtn');
    const addEdgeBtn = document.getElementById('addEdgeBtn');
    const deleteModeBtn = document.getElementById('deleteModeBtn');
    const fullscreenBtn = document.getElementById('fullscreenBtn');

    const deviceModal = document.getElementById('deviceModal');
    const deviceForm = document.getElementById('deviceForm');
    const cancelBtn = document.getElementById('cancelBtn');

    const iconMap = {
        server: '\uf233', router: '\uf637', switch: '\uf796', printer: '\uf02f',
        nas: '\uf0a0', camera: '\uf030', other: '\uf108', firewall: '\uf3ed',
        ipphone: '\uf87d', punchdevice: '\uf2c2'
    };
    const statusColorMap = {
        online: '#22c55e', offline: '#ef4444', unknown: '#f59e0b'
    };

    const createMap = async () => {
        const name = prompt("Enter a name for the new map:");
        if (name) {
            const newMap = await api.post('create_map', { name });
            await loadMaps();
            mapSelector.value = newMap.id;
            await switchMap(newMap.id);
        }
    };

    const loadMaps = async () => {
        const maps = await api.get('get_maps');
        mapSelector.innerHTML = '';
        if (maps.length > 0) {
            maps.forEach(map => {
                const option = document.createElement('option');
                option.value = map.id;
                option.textContent = map.name;
                mapSelector.appendChild(option);
            });
            mapContainer.classList.remove('hidden');
            noMapsContainer.classList.add('hidden');
            return maps[0].id;
        } else {
            mapContainer.classList.add('hidden');
            noMapsContainer.classList.remove('hidden');
            return null;
        }
    };

    const switchMap = async (mapId) => {
        if (!mapId) {
            if (network) network.destroy();
            network = null;
            nodes.clear();
            edges.clear();
            mapContainer.classList.add('hidden');
            noMapsContainer.classList.remove('hidden');
            return;
        }
        currentMapId = mapId;
        currentMapName.textContent = mapSelector.options[mapSelector.selectedIndex].text;
        const [deviceData, edgeData] = await Promise.all([
            api.get('get_devices', { map_id: mapId }),
            api.get('get_edges', { map_id: mapId })
        ]);
        
        const deviceStatusMap = new Map(deviceData.map(d => [d.id, d.status]));

        const visNodes = deviceData.map(d => ({
            id: d.id,
            label: d.name,
            title: `${d.name}<br>${d.ip}<br>Status: ${d.status}`,
            x: d.x, y: d.y,
            shape: 'icon',
            icon: {
                face: "'Font Awesome 6 Free'", weight: "900",
                code: iconMap[d.type] || iconMap.other, size: 50,
                color: statusColorMap[d.status] || statusColorMap.unknown
            },
            font: { color: 'white', size: 14 }
        }));
        nodes.clear();
        nodes.add(visNodes);

        const visEdges = edgeData.map(e => {
            const sourceStatus = deviceStatusMap.get(e.source_id);
            const targetStatus = deviceStatusMap.get(e.target_id);
            const isOffline = sourceStatus === 'offline' || targetStatus === 'offline';
            return {
                id: e.id, from: e.source_id, to: e.target_id,
                color: isOffline ? statusColorMap.offline : statusColorMap.online,
                dashes: true
            };
        });
        edges.clear();
        edges.add(visEdges);

        if (!network) initializeMap();
    };

    const initializeMap = () => {
        const container = document.getElementById('network-map');
        const data = { nodes, edges };
        const options = {
            physics: false,
            interaction: { hover: true },
            edges: { smooth: true, width: 2 },
            manipulation: {
                enabled: false,
                addEdge: async (edgeData, callback) => {
                    const newEdge = await api.post('create_edge', { source_id: edgeData.from, target_id: edgeData.to, map_id: currentMapId });
                    edgeData.id = newEdge.id;
                    edgeData.color = statusColorMap.online;
                    edgeData.dashes = true;
                    callback(edgeData);
                },
                deleteNode: async (data, callback) => {
                    if (confirm(`Delete ${data.nodes.length} device(s)?`)) {
                        for (const nodeId of data.nodes) { await api.post('delete_device', { id: nodeId }); }
                        callback(data);
                    }
                },
                deleteEdge: async (data, callback) => {
                    if (confirm(`Delete ${data.edges.length} connection(s)?`)) {
                        for (const edgeId of data.edges) { await api.post('delete_edge', { id: edgeId }); }
                        callback(data);
                    }
                }
            }
        };
        network = new vis.Network(container, data, options);

        network.on("dragEnd", async (params) => {
            if (params.nodes.length > 0) {
                const nodeId = params.nodes[0];
                const position = network.getPositions([nodeId])[nodeId];
                await api.post('update_device', { id: nodeId, updates: { x: position.x, y: position.y } });
            }
        });

        network.on("doubleClick", (params) => {
            if (params.nodes.length > 0) { openDeviceModal(params.nodes[0]); }
        });
    };

    const openDeviceModal = (deviceId = null) => {
        deviceForm.reset();
        if (deviceId) {
            const device = nodes.get(deviceId);
            document.getElementById('modalTitle').textContent = 'Edit Device';
            document.getElementById('deviceId').value = device.id;
            document.getElementById('deviceName').value = device.label;
            document.getElementById('deviceIp').value = device.title.split('<br>')[1];
            document.getElementById('deviceType').value = Object.keys(iconMap).find(key => iconMap[key] === device.icon.code) || 'other';
        } else {
            document.getElementById('modalTitle').textContent = 'Add Device';
        }
        deviceModal.classList.remove('hidden');
    };

    deviceForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const id = document.getElementById('deviceId').value;
        const deviceData = {
            name: document.getElementById('deviceName').value,
            ip: document.getElementById('deviceIp').value,
            type: document.getElementById('deviceType').value,
            map_id: currentMapId
        };

        if (id) {
            const updated = await api.post('update_device', { id, updates: deviceData });
            nodes.update({ id: updated.id, label: updated.name, title: `${updated.name}<br>${updated.ip}<br>Status: ${updated.status}`, icon: { code: iconMap[updated.type], color: statusColorMap[updated.status] } });
        } else {
            const created = await api.post('create_device', deviceData);
            nodes.add({ id: created.id, label: created.name, title: `${created.name}<br>${created.ip}<br>Status: ${created.status}`, x: created.x, y: created.y, shape: 'icon', icon: { face: "'Font Awesome 6 Free'", weight: "900", code: iconMap[created.type], size: 50, color: statusColorMap[created.status] }, font: { color: 'white', size: 14 } });
        }
        deviceModal.classList.add('hidden');
    });

    refreshStatusBtn.addEventListener('click', async () => {
        const icon = refreshStatusBtn.querySelector('i');
        icon.classList.add('fa-spin');
        await api.post('ping_all_devices', { map_id: currentMapId });
        const devices = await api.get('get_devices', { map_id: currentMapId });
        
        const deviceStatusMap = new Map(devices.map(d => [d.id, d.status]));
        const nodeUpdates = devices.map(d => ({
            id: d.id,
            title: `${d.name}<br>${d.ip}<br>Status: ${d.status}`,
            icon: { color: statusColorMap[d.status] }
        }));
        nodes.update(nodeUpdates);

        const edgeUpdates = edges.get().map(edge => {
            const sourceStatus = deviceStatusMap.get(edge.from);
            const targetStatus = deviceStatusMap.get(edge.to);
            const isOffline = sourceStatus === 'offline' || targetStatus === 'offline';
            return {
                id: edge.id,
                color: isOffline ? statusColorMap.offline : statusColorMap.online
            };
        });
        edges.update(edgeUpdates);

        icon.classList.remove('fa-spin');
    });

    fullscreenBtn.addEventListener('click', () => {
        if (!document.fullscreenElement) {
            mapWrapper.requestFullscreen().catch(err => alert(`Error: ${err.message}`));
        } else {
            document.exitFullscreen();
        }
    });
    document.addEventListener('fullscreenchange', () => {
        const icon = fullscreenBtn.querySelector('i');
        icon.classList.toggle('fa-expand', !document.fullscreenElement);
        icon.classList.toggle('fa-compress', !!document.fullscreenElement);
    });

    // Event Listeners
    newMapBtn.addEventListener('click', createMap);
    createFirstMapBtn.addEventListener('click', createMap);
    deleteMapBtn.addEventListener('click', async () => {
        if (confirm(`Are you sure you want to delete the map "${mapSelector.options[mapSelector.selectedIndex].text}"? This will also delete all its devices and connections.`)) {
            await api.post('delete_map', { id: currentMapId });
            const firstMapId = await loadMaps();
            await switchMap(firstMapId);
        }
    });
    mapSelector.addEventListener('change', (e) => switchMap(e.target.value));
    addDeviceBtn.addEventListener('click', () => openDeviceModal());
    cancelBtn.addEventListener('click', () => deviceModal.classList.add('hidden'));
    addEdgeBtn.addEventListener('click', () => { network.addEdgeMode(); });
    deleteModeBtn.addEventListener('click', () => { network.deleteSelected(); });

    // Initial Load
    (async () => {
        const firstMapId = await loadMaps();
        if (firstMapId) { await switchMap(firstMapId); }
    })();
});