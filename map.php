<?php include 'header.php'; ?>
<script src="https://unpkg.com/vis-network/standalone/umd/vis-network.min.js"></script>
<style>
    #network-map-wrapper { position: relative; }
    #network-map { height: 75vh; background-color: #1e293b; border: 1px solid #334155; border-radius: 0.5rem; }
    #network-map-wrapper:fullscreen #network-map { height: 100vh; border-radius: 0; border: 0; }
    #network-map-wrapper:fullscreen #map-controls { display: none; } /* Hide normal controls in fullscreen */

    .vis-tooltip {
        position: absolute;
        visibility: hidden;
        padding: 5px;
        white-space: nowrap;
        font-family: 'Inter', sans-serif;
        font-size: 14px;
        color: #ffffff;
        background-color: #0f172a;
        border: 1px solid #334155;
        border-radius: 3px;
        z-index: 10;
    }
    
    #status-legend {
        position: absolute;
        bottom: 1.25rem;
        right: 1.25rem;
        background-color: rgba(15, 23, 42, 0.8);
        border: 1px solid #334155;
        border-radius: 0.5rem;
        padding: 0.75rem;
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
        z-index: 5;
    }
    .legend-item { display: flex; align-items: center; gap: 0.5rem; font-size: 0.875rem; }
    .legend-dot { width: 12px; height: 12px; border-radius: 50%; }
</style>

<div class="container mx-auto px-4 py-8">
    <div id="map-selection" class="mb-6">
        <div class="flex items-center justify-between mb-4">
            <h1 class="text-3xl font-bold text-white">Network Map</h1>
            <div class="flex gap-4">
                <select id="mapSelector" class="bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500"></select>
                <button id="newMapBtn" class="px-4 py-2 bg-cyan-600 text-white rounded-lg hover:bg-cyan-700"><i class="fas fa-plus mr-2"></i>New Map</button>
                <button id="deleteMapBtn" class="px-4 py-2 bg-red-600/80 text-white rounded-lg hover:bg-red-700"><i class="fas fa-trash mr-2"></i>Delete Map</button>
            </div>
        </div>
    </div>

    <div id="map-container" class="hidden">
        <div id="map-controls" class="bg-slate-800 border border-slate-700 rounded-lg shadow-xl p-4 mb-6">
            <div class="flex items-center justify-between">
                <h2 id="currentMapName" class="text-xl font-semibold text-white"></h2>
                <div class="flex items-center gap-2">
                    <button id="refreshStatusBtn" class="px-3 py-2 bg-slate-700 text-slate-300 rounded-lg hover:bg-slate-600" title="Refresh Device Statuses"><i class="fas fa-sync-alt"></i></button>
                    <button id="addDeviceBtn" class="px-3 py-2 bg-slate-700 text-slate-300 rounded-lg hover:bg-slate-600" title="Add Device"><i class="fas fa-plus"></i></button>
                    <button id="addEdgeBtn" class="px-3 py-2 bg-slate-700 text-slate-300 rounded-lg hover:bg-slate-600" title="Add Connection"><i class="fas fa-project-diagram"></i></button>
                    <button id="deleteModeBtn" class="px-3 py-2 bg-slate-700 text-slate-300 rounded-lg hover:bg-slate-600" title="Delete Selected"><i class="fas fa-trash-alt"></i></button>
                    <button id="fullscreenBtn" class="px-3 py-2 bg-slate-700 text-slate-300 rounded-lg hover:bg-slate-600" title="Toggle Fullscreen"><i class="fas fa-expand"></i></button>
                </div>
            </div>
        </div>
        <div id="network-map-wrapper">
            <div id="network-map"></div>
            <div id="status-legend">
                <div class="legend-item"><div class="legend-dot" style="background-color: #22c55e;"></div><span>Online</span></div>
                <div class="legend-item"><div class="legend-dot" style="background-color: #f59e0b;"></div><span>Unknown</span></div>
                <div class="legend-item"><div class="legend-dot" style="background-color: #ef4444;"></div><span>Offline</span></div>
            </div>
        </div>
    </div>
    
    <div id="no-maps" class="text-center py-16 bg-slate-800 border border-slate-700 rounded-lg hidden">
        <i class="fas fa-map-signs text-slate-600 text-5xl mb-4"></i>
        <h2 class="text-2xl font-bold text-white mb-2">No Network Maps Found</h2>
        <p class="text-slate-400 mb-6">Create a map to start visualizing your network.</p>
        <button id="createFirstMapBtn" class="px-6 py-3 bg-cyan-600 text-white rounded-lg hover:bg-cyan-700 text-lg">Create Your First Map</button>
    </div>
</div>

<!-- Device Editor Modal -->
<div id="deviceModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="bg-slate-800 rounded-lg shadow-xl p-6 w-full max-w-md">
        <h2 id="modalTitle" class="text-xl font-semibold text-white mb-4">Add Device</h2>
        <form id="deviceForm">
            <input type="hidden" id="deviceId" name="id">
            <div class="space-y-4">
                <input type="text" id="deviceName" name="name" placeholder="Device Name" class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500" required>
                <input type="text" id="deviceIp" name="ip" placeholder="IP Address" class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500" required>
                <select id="deviceType" name="type" class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500">
                    <option value="server">Server</option> <option value="router">Router</option> <option value="switch">Switch</option> <option value="printer">Printer</option> <option value="nas">NAS</option> <option value="camera">Camera</option> <option value="other">Other</option>
                </select>
            </div>
            <div class="flex justify-end gap-4 mt-6">
                <button type="button" id="cancelBtn" class="px-4 py-2 bg-slate-700 text-slate-300 rounded-lg hover:bg-slate-600">Cancel</button>
                <button type="submit" id="saveBtn" class="px-4 py-2 bg-cyan-600 text-white rounded-lg hover:bg-cyan-700">Save</button>
            </div>
        </form>
    </div>
</div>

<script>
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
    let animationFrameId = null;

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
        nas: '\uf0a0', camera: '\uf030', other: '\uf108'
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
        
        const visNodes = deviceData.map(d => ({
            id: d.id,
            label: d.name,
            title: `${d.name}<br>${d.ip}<br>Status: ${d.status}`,
            x: d.x,
            y: d.y,
            shape: 'icon',
            icon: {
                face: "'Font Awesome 6 Free'",
                weight: "900",
                code: iconMap[d.type] || iconMap.other,
                size: 50,
                color: statusColorMap[d.status] || statusColorMap.unknown
            }
        }));
        nodes.clear();
        nodes.add(visNodes);

        const visEdges = edgeData.map(e => ({
            id: e.id,
            from: e.source_id,
            to: e.target_id,
            dashes: true,
            arrows: { to: { enabled: true, scaleFactor: 0.7 } }
        }));
        edges.clear();
        edges.add(visEdges);

        if (!network) {
            initializeMap();
        }
        startEdgeAnimation();
    };

    const initializeMap = () => {
        const container = document.getElementById('network-map');
        const data = { nodes, edges };
        const options = {
            physics: false,
            interaction: { hover: true },
            edges: {
                color: { color: '#64748b', highlight: '#22d3ee', hover: '#22d3ee' },
                smooth: true,
                width: 2
            },
            manipulation: {
                enabled: false,
                addEdge: async (edgeData, callback) => {
                    const newEdge = await api.post('create_edge', { source_id: edgeData.from, target_id: edgeData.to, map_id: currentMapId });
                    edgeData.id = newEdge.id;
                    edgeData.dashes = true;
                    edgeData.arrows = { to: { enabled: true, scaleFactor: 0.7 } };
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

    let dashOffset = 0;
    const startEdgeAnimation = () => {
        if (animationFrameId) cancelAnimationFrame(animationFrameId);
        
        const animate = () => {
            dashOffset = (dashOffset - 1) % 20;
            const allEdges = edges.get({ fields: ['id'] });
            const updatedEdges = allEdges.map(edge => ({
                id: edge.id,
                dashes: [10, 10], // This is a trick to force re-render with new offset
            }));
            
            // This is a conceptual representation; vis.js canvas doesn't support dashOffset directly.
            // The 'dashes: true' provides a static dashed line. For true animation, a library extension or more complex canvas drawing would be needed.
            // We will keep the dashed lines as a visual indicator of connectivity.
            
            animationFrameId = requestAnimationFrame(animate);
        };
        // For now, we will use static dashed lines as the animation is complex.
        // animate(); 
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
            nodes.add({ id: created.id, label: created.name, title: `${created.name}<br>${created.ip}<br>Status: ${created.status}`, x: created.x, y: created.y, shape: 'icon', icon: { face: "'Font Awesome 6 Free'", weight: "900", code: iconMap[created.type], size: 50, color: statusColorMap[created.status] } });
        }
        deviceModal.classList.add('hidden');
    });

    refreshStatusBtn.addEventListener('click', async () => {
        const icon = refreshStatusBtn.querySelector('i');
        icon.classList.add('fa-spin');
        await api.post('ping_all_devices', { map_id: currentMapId });
        const devices = await api.get('get_devices', { map_id: currentMapId });
        const updates = devices.map(d => ({
            id: d.id,
            title: `${d.name}<br>${d.ip}<br>Status: ${d.status}`,
            icon: { color: statusColorMap[d.status] }
        }));
        nodes.update(updates);
        icon.classList.remove('fa-spin');
    });

    fullscreenBtn.addEventListener('click', () => {
        if (!document.fullscreenElement) {
            mapWrapper.requestFullscreen().catch(err => alert(`Error attempting to enable full-screen mode: ${err.message} (${err.name})`));
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
</script>

<?php include 'footer.php'; ?>