<?php include 'header.php'; ?>
<script src="https://unpkg.com/vis-network/standalone/umd/vis-network.min.js"></script>
<style>
    #network-map { height: 75vh; background-color: #1e293b; border: 1px solid #334155; border-radius: 0.5rem; }
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
        <div class="bg-slate-800 border border-slate-700 rounded-lg shadow-xl p-4 mb-6">
            <div class="flex items-center justify-between">
                <h2 id="currentMapName" class="text-xl font-semibold text-white"></h2>
                <div class="flex items-center gap-4">
                    <button id="addDeviceBtn" class="px-4 py-2 bg-slate-700 text-slate-300 rounded-lg hover:bg-slate-600"><i class="fas fa-plus mr-2"></i>Add Device</button>
                    <button id="addEdgeBtn" class="px-4 py-2 bg-slate-700 text-slate-300 rounded-lg hover:bg-slate-600"><i class="fas fa-project-diagram mr-2"></i>Add Connection</button>
                    <button id="deleteModeBtn" class="px-4 py-2 bg-slate-700 text-slate-300 rounded-lg hover:bg-slate-600"><i class="fas fa-trash-alt mr-2"></i>Delete Mode</button>
                </div>
            </div>
        </div>
        <div id="network-map"></div>
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

    const mapSelector = document.getElementById('mapSelector');
    const newMapBtn = document.getElementById('newMapBtn');
    const deleteMapBtn = document.getElementById('deleteMapBtn');
    const mapContainer = document.getElementById('map-container');
    const noMapsContainer = document.getElementById('no-maps');
    const createFirstMapBtn = document.getElementById('createFirstMapBtn');
    const currentMapName = document.getElementById('currentMapName');
    
    const addDeviceBtn = document.getElementById('addDeviceBtn');
    const addEdgeBtn = document.getElementById('addEdgeBtn');
    const deleteModeBtn = document.getElementById('deleteModeBtn');

    const deviceModal = document.getElementById('deviceModal');
    const deviceForm = document.getElementById('deviceForm');
    const cancelBtn = document.getElementById('cancelBtn');

    const iconMap = {
        server: { code: '\uf233', color: '#34d399' }, // fa-server, green
        router: { code: '\uf637', color: '#60a5fa' }, // fa-route, blue
        switch: { code: '\uf796', color: '#a78bfa' }, // fa-ethernet, purple
        printer: { code: '\uf02f', color: '#f87171' }, // fa-print, red
        nas: { code: '\uf0a0', color: '#fbbf24' }, // fa-hdd, amber
        camera: { code: '\uf030', color: '#f472b6' }, // fa-camera, pink
        other: { code: '\uf108', color: '#9ca3af' } // fa-desktop, gray
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
            title: `${d.name}<br>${d.ip}`,
            x: d.x,
            y: d.y,
            shape: 'icon',
            icon: {
                face: "'Font Awesome 6 Free'",
                weight: "900",
                code: iconMap[d.type]?.code || iconMap.other.code,
                size: 50,
                color: iconMap[d.type]?.color || iconMap.other.color
            }
        }));
        nodes.clear();
        nodes.add(visNodes);

        const visEdges = edgeData.map(e => ({
            id: e.id,
            from: e.source_id,
            to: e.target_id
        }));
        edges.clear();
        edges.add(visEdges);

        if (!network) {
            initializeMap();
        }
    };

    const initializeMap = () => {
        const container = document.getElementById('network-map');
        const data = { nodes, edges };
        const options = {
            physics: false,
            interaction: { hover: true },
            edges: {
                color: { color: '#64748b', highlight: '#22d3ee', hover: '#22d3ee' },
                arrows: { to: { enabled: false } },
                smooth: true
            },
            manipulation: {
                enabled: false,
                addEdge: async (edgeData, callback) => {
                    const newEdge = await api.post('create_edge', { source_id: edgeData.from, target_id: edgeData.to, map_id: currentMapId });
                    edgeData.id = newEdge.id;
                    callback(edgeData);
                },
                deleteNode: async (data, callback) => {
                    if (confirm(`Delete ${data.nodes.length} device(s)?`)) {
                        for (const nodeId of data.nodes) {
                            await api.post('delete_device', { id: nodeId });
                        }
                        callback(data);
                    }
                },
                deleteEdge: async (data, callback) => {
                    if (confirm(`Delete ${data.edges.length} connection(s)?`)) {
                        for (const edgeId of data.edges) {
                            await api.post('delete_edge', { id: edgeId });
                        }
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
            if (params.nodes.length > 0) {
                openDeviceModal(params.nodes[0]);
            }
        });
    };

    const openDeviceModal = (deviceId = null) => {
        deviceForm.reset();
        if (deviceId) {
            const device = nodes.get(deviceId);
            document.getElementById('modalTitle').textContent = 'Edit Device';
            document.getElementById('deviceId').value = device.id;
            document.getElementById('deviceName').value = device.label;
            const ip = device.title.split('<br>')[1];
            document.getElementById('deviceIp').value = ip;
            const type = Object.keys(iconMap).find(key => iconMap[key].code === device.icon.code) || 'other';
            document.getElementById('deviceType').value = type;
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
            const icon = iconMap[updated.type] || iconMap.other;
            nodes.update({ id: updated.id, label: updated.name, title: `${updated.name}<br>${updated.ip}`, icon: { ...icon, face: "'Font Awesome 6 Free'", weight: "900" } });
        } else {
            const created = await api.post('create_device', deviceData);
            const icon = iconMap[created.type] || iconMap.other;
            nodes.add({ id: created.id, label: created.name, title: `${created.name}<br>${created.ip}`, x: created.x, y: created.y, shape: 'icon', icon: { ...icon, face: "'Font Awesome 6 Free'", weight: "900" } });
        }
        deviceModal.classList.add('hidden');
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
    addEdgeBtn.addEventListener('click', () => {
        network.addEdgeMode();
        addEdgeBtn.classList.add('bg-cyan-600');
    });
    deleteModeBtn.addEventListener('click', () => {
        network.deleteSelected();
    });

    // Initial Load
    (async () => {
        const firstMapId = await loadMaps();
        if (firstMapId) {
            await switchMap(firstMapId);
        }
    })();
});
</script>

<?php include 'footer.php'; ?>