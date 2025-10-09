function initMap() {
    const API_URL = 'api.php';
    const api = {
        get: async (action, params = {}) => {
            const res = await fetch(`${API_URL}?action=${action}&${new URLSearchParams(params)}`);
            if (!res.ok) {
                const errorData = await res.json().catch(() => ({ error: 'Invalid JSON response from server' }));
                throw new Error(errorData.error || `HTTP error! status: ${res.status}`);
            }
            return res.json();
        },
        post: async (action, body) => {
            const res = await fetch(`${API_URL}?action=${action}`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) });
            if (!res.ok) {
                const errorData = await res.json().catch(() => ({ error: 'Invalid JSON response from server' }));
                throw new Error(errorData.error || `HTTP error! status: ${res.status}`);
            }
            return res.json();
        }
    };

    let network = null, nodes = new vis.DataSet([]), edges = new vis.DataSet([]), currentMapId = null, pingIntervals = {};
    let animationFrameId = null, tick = 0, globalRefreshIntervalId = null;
    const REFRESH_INTERVAL_SECONDS = 30;

    const mapWrapper = document.getElementById('network-map-wrapper'), mapSelector = document.getElementById('mapSelector'),
        newMapBtn = document.getElementById('newMapBtn'), deleteMapBtn = document.getElementById('deleteMapBtn'),
        mapContainer = document.getElementById('map-container'), noMapsContainer = document.getElementById('no-maps'),
        createFirstMapBtn = document.getElementById('createFirstMapBtn'), currentMapName = document.getElementById('currentMapName'),
        scanNetworkBtn = document.getElementById('scanNetworkBtn'), refreshStatusBtn = document.getElementById('refreshStatusBtn'),
        liveRefreshToggle = document.getElementById('liveRefreshToggle'),
        addDeviceBtn = document.getElementById('addDeviceBtn'), addEdgeBtn = document.getElementById('addEdgeBtn'),
        fullscreenBtn = document.getElementById('fullscreenBtn'),
        exportBtn = document.getElementById('exportBtn'), importBtn = document.getElementById('importBtn'),
        importFile = document.getElementById('importFile');

    const deviceModal = document.getElementById('deviceModal'), deviceForm = document.getElementById('deviceForm'),
        cancelBtn = document.getElementById('cancelBtn');
    const edgeModal = document.getElementById('edgeModal'), edgeForm = document.getElementById('edgeForm'),
        cancelEdgeBtn = document.getElementById('cancelEdgeBtn');
    const scanModal = document.getElementById('scanModal'), closeScanModal = document.getElementById('closeScanModal'),
        scanForm = document.getElementById('scanForm'), scanLoader = document.getElementById('scanLoader'),
        scanResults = document.getElementById('scanResults'), scanInitialMessage = document.getElementById('scanInitialMessage');

    const iconMap = { server: '\uf233', router: '\uf4d7', switch: '\uf796', printer: '\uf02f', nas: '\uf0a0', camera: '\uf030', other: '\uf108', firewall: '\uf3ed', ipphone: '\uf87d', punchdevice: '\uf2c2', 'wifi-router': '\uf1eb', 'radio-tower': '\uf519', rack: '\uf1b3', laptop: '\uf109', tablet: '\uf3fa', mobile: '\uf3cd', cloud: '\uf0c2', database: '\uf1c0', box: '\uf49e' };
    const statusColorMap = { online: '#22c55e', warning: '#f59e0b', critical: '#ef4444', offline: '#64748b', unknown: '#94a3b8' };
    const edgeColorMap = { cat5: '#a78bfa', fiber: '#f97316', wifi: '#38bdf8', radio: '#84cc16' };

    window.cleanup = () => {
        if (animationFrameId) { cancelAnimationFrame(animationFrameId); animationFrameId = null; }
        Object.values(pingIntervals).forEach(clearInterval); pingIntervals = {};
        if (globalRefreshIntervalId) { clearInterval(globalRefreshIntervalId); globalRefreshIntervalId = null; }
        if (network) { network.destroy(); network = null; }
        window.cleanup = null;
    };

    const populateLegend = () => {
        const legendContainer = document.getElementById('status-legend');
        if (!legendContainer) return;
        const statusOrder = ['online', 'warning', 'critical', 'offline', 'unknown'];
        legendContainer.innerHTML = statusOrder.map(status => {
            const color = statusColorMap[status];
            const label = status.charAt(0).toUpperCase() + status.slice(1);
            return `<div class="legend-item"><div class="legend-dot" style="background-color: ${color};"></div><span>${label}</span></div>`;
        }).join('');
    };

    const performBulkRefresh = async () => {
        const icon = refreshStatusBtn.querySelector('i');
        icon.classList.add('fa-spin');
        try {
            const result = await api.post('ping_all_devices', { map_id: currentMapId });

            if (result.status_changes && result.status_changes.length > 0) {
                result.status_changes.forEach(change => {
                    const { name, old_status, new_status } = change;
                    if (new_status === 'critical' || new_status === 'offline') {
                        window.notyf.error({ message: `Device '${name}' is now ${new_status}.`, duration: 5000, dismissible: true });
                    } else if (new_status === 'online' && (old_status === 'critical' || old_status === 'offline')) {
                        window.notyf.success({ message: `Device '${name}' is back online.`, duration: 3000 });
                    }
                });
            }

            const deviceData = await api.get('get_devices', { map_id: currentMapId });
            
            const updates = deviceData.map(d => {
                const node = nodes.get(d.id);
                if (!node) return null;

                let label = d.name;
                if (d.show_live_ping && d.status === 'online' && d.last_avg_time !== null) {
                    label += `\n${d.last_avg_time}ms | TTL:${d.last_ttl || 'N/A'}`;
                }
                
                return {
                    id: d.id,
                    deviceData: d,
                    icon: { ...node.icon, color: statusColorMap[d.status] || statusColorMap.unknown },
                    title: `${d.name}<br>${d.ip || 'No IP'}<br>Status: ${d.status}`,
                    label: label
                };
            }).filter(Boolean);

            if (updates.length > 0) {
                nodes.update(updates);
            }
            
            return result.count;
        } catch (error) {
            console.error("Bulk refresh failed:", error);
            window.notyf.error("Failed to refresh device statuses.");
        } finally {
            icon.classList.remove('fa-spin');
        }
        return 0;
    };

    const createMap = async () => {
        const name = prompt("Enter a name for the new map:");
        if (name) { 
            const newMap = await api.post('create_map', { name }); 
            await loadMaps(); 
            mapSelector.value = newMap.id; 
            await switchMap(newMap.id); 
            window.notyf.success(`Map "${name}" created.`);
        }
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
        if (animationFrameId) { cancelAnimationFrame(animationFrameId); animationFrameId = null; }
        if (!mapId) { if (network) network.destroy(); network = null; nodes.clear(); edges.clear(); mapContainer.classList.add('hidden'); noMapsContainer.classList.remove('hidden'); return; }
        
        currentMapId = mapId; currentMapName.textContent = mapSelector.options[mapSelector.selectedIndex].text;
        const [deviceData, edgeData] = await Promise.all([api.get('get_devices', { map_id: mapId }), api.get('get_edges', { map_id: mapId })]);
        
        const visNodes = deviceData.map(d => {
            let label = d.name;
            if (d.show_live_ping && d.status === 'online' && d.last_avg_time !== null) {
                label += `\n${d.last_avg_time}ms | TTL:${d.last_ttl || 'N/A'}`;
            }

            if (d.type === 'box') {
                return { id: d.id, label: d.name, title: d.name, x: d.x, y: d.y, shape: 'box', color: { background: 'rgba(49, 65, 85, 0.5)', border: '#475569' }, font: { color: 'white', size: parseInt(d.name_text_size) || 14 }, margin: 20, deviceData: d, level: -1 };
            }
            return { id: d.id, label: label, title: `${d.name}<br>${d.ip || 'No IP'}<br>Status: ${d.status}`, x: d.x, y: d.y, shape: 'icon', icon: { face: "'Font Awesome 6 Free'", weight: "900", code: iconMap[d.type] || iconMap.other, size: parseInt(d.icon_size) || 50, color: statusColorMap[d.status] || statusColorMap.unknown }, font: { color: 'white', size: parseInt(d.name_text_size) || 14, multi: true }, deviceData: d };
        });
        nodes.clear(); nodes.add(visNodes);

        const visEdges = edgeData.map(e => ({ id: e.id, from: e.source_id, to: e.target_id, connection_type: e.connection_type, label: e.connection_type }));
        edges.clear(); edges.add(visEdges);
        
        setupAutoPing(deviceData);
        if (!network) initializeMap();
        if (!animationFrameId) updateAndAnimateEdges();
    };

    const copyDevice = async (deviceId) => {
        const nodeToCopy = nodes.get(deviceId);
        if (!nodeToCopy) return;

        const originalDevice = nodeToCopy.deviceData;
        const position = network.getPositions([deviceId])[deviceId];

        const newDeviceData = {
            ...originalDevice,
            name: `Copy of ${originalDevice.name}`,
            ip: '', // Clear IP to avoid duplicates
            x: position.x + 50, // Offset the new device
            y: position.y + 50,
            map_id: currentMapId,
            status: 'unknown', // New device status is unknown
            last_seen: null,
            last_avg_time: null,
            last_ttl: null,
        };
        
        delete newDeviceData.id;
        delete newDeviceData.created_at;
        delete newDeviceData.updated_at;

        try {
            const createdDevice = await api.post('create_device', newDeviceData);
            window.notyf.success(`Device "${originalDevice.name}" copied.`);
            
            const visNode = {
                id: createdDevice.id,
                label: createdDevice.name,
                title: `${createdDevice.name}<br>${createdDevice.ip || 'No IP'}<br>Status: ${createdDevice.status}`,
                x: createdDevice.x,
                y: createdDevice.y,
                shape: 'icon',
                icon: { face: "'Font Awesome 6 Free'", weight: "900", code: iconMap[createdDevice.type] || iconMap.other, size: parseInt(createdDevice.icon_size) || 50, color: statusColorMap[createdDevice.status] || statusColorMap.unknown },
                font: { color: 'white', size: parseInt(createdDevice.name_text_size) || 14, multi: true },
                deviceData: createdDevice
            };
            if (createdDevice.type === 'box') {
                Object.assign(visNode, {
                    shape: 'box',
                    color: { background: 'rgba(49, 65, 85, 0.5)', border: '#475569' },
                    margin: 20,
                    level: -1
                });
            }
            nodes.add(visNode);
        } catch (error) {
            console.error("Failed to copy device:", error);
            window.notyf.error("Could not copy the device.");
        }
    };

    const initializeMap = () => {
        const container = document.getElementById('network-map');
        const contextMenu = document.getElementById('context-menu');
        populateLegend();
        const data = { nodes, edges };
        const options = { 
            physics: false, 
            interaction: { hover: true }, 
            edges: { smooth: true, width: 2, font: { color: '#ffffff', size: 12, align: 'top', strokeWidth: 0 } }, 
            manipulation: { 
                enabled: false, 
                addEdge: async (edgeData, callback) => { 
                    const newEdge = await api.post('create_edge', { source_id: edgeData.from, target_id: edgeData.to, map_id: currentMapId, connection_type: 'cat5' }); 
                    edgeData.id = newEdge.id; edgeData.label = 'cat5'; callback(edgeData); 
                    window.notyf.success('Connection added.');
                }
            } 
        };
        network = new vis.Network(container, data, options);
        network.on("dragEnd", async (params) => { if (params.nodes.length > 0) { const nodeId = params.nodes[0]; const position = network.getPositions([nodeId])[nodeId]; await api.post('update_device', { id: nodeId, updates: { x: position.x, y: position.y } }); } });
        network.on("doubleClick", (params) => { if (params.nodes.length > 0) openDeviceModal(params.nodes[0]); });
        network.on("click", (params) => { if (params.edges.length > 0) openEdgeModal(params.edges[0]); });

        const closeContextMenu = () => { contextMenu.style.display = 'none'; };
        network.on("oncontext", (params) => {
            params.event.preventDefault();
            const nodeId = network.getNodeAt(params.pointer.DOM);
            if (nodeId) {
                const node = nodes.get(nodeId);
                contextMenu.innerHTML = `
                    <div class="context-menu-item" data-action="edit" data-id="${nodeId}"><i class="fas fa-edit fa-fw mr-2"></i>Edit</div>
                    <div class="context-menu-item" data-action="copy" data-id="${nodeId}"><i class="fas fa-copy fa-fw mr-2"></i>Copy</div>
                    ${node.deviceData.ip ? `<div class="context-menu-item" data-action="ping" data-id="${nodeId}"><i class="fas fa-sync fa-fw mr-2"></i>Check Status</div>` : ''}
                    <div class="context-menu-item" data-action="delete" data-id="${nodeId}" style="color: #ef4444;"><i class="fas fa-trash-alt fa-fw mr-2"></i>Delete</div>
                `;
                contextMenu.style.left = `${params.event.pageX}px`;
                contextMenu.style.top = `${params.event.pageY}px`;
                contextMenu.style.display = 'block';
                document.addEventListener('click', closeContextMenu, { once: true });
            } else { closeContextMenu(); }
        });
        contextMenu.addEventListener('click', async (e) => {
            const target = e.target.closest('.context-menu-item');
            if (target) {
                const { action, id } = target.dataset;
                closeContextMenu();

                if (action === 'edit') {
                    openDeviceModal(id);
                } else if (action === 'ping') {
                    const icon = document.createElement('i');
                    icon.className = 'fas fa-spinner fa-spin';
                    target.prepend(icon);
                    pingSingleDevice(id).finally(() => icon.remove());
                } else if (action === 'copy') {
                    await copyDevice(id);
                } else if (action === 'delete') {
                    if (confirm('Are you sure you want to delete this device?')) {
                        await api.post('delete_device', { id });
                        window.notyf.success('Device deleted.');
                        nodes.remove(id);
                    }
                }
            }
        });
    };

    const toggleDeviceModalFields = (type) => {
        const isAnnotation = type === 'box';
        const isPingable = !isAnnotation;
        document.getElementById('deviceIpWrapper').style.display = isPingable ? 'block' : 'none';
        document.getElementById('pingIntervalWrapper').style.display = isPingable ? 'block' : 'none';
        document.getElementById('thresholdsWrapper').style.display = isPingable ? 'block' : 'none';
        document.getElementById('deviceIp').required = isPingable;
        document.getElementById('iconSizeLabel').textContent = isAnnotation ? 'Width' : 'Icon Size';
        document.getElementById('nameTextSizeLabel').textContent = isAnnotation ? 'Height' : 'Name Text Size';
    };

    const openDeviceModal = (deviceId = null, prefill = {}) => {
        deviceForm.reset();
        document.getElementById('deviceId').value = '';
        if (deviceId) {
            const node = nodes.get(deviceId);
            document.getElementById('modalTitle').textContent = 'Edit Item';
            document.getElementById('deviceId').value = node.id;
            document.getElementById('deviceName').value = node.deviceData.name;
            document.getElementById('deviceIp').value = node.deviceData.ip;
            document.getElementById('deviceType').value = node.deviceData.type;
            document.getElementById('pingInterval').value = node.deviceData.ping_interval;
            document.getElementById('iconSize').value = node.deviceData.icon_size;
            document.getElementById('nameTextSize').value = node.deviceData.name_text_size;
            document.getElementById('warning_latency_threshold').value = node.deviceData.warning_latency_threshold;
            document.getElementById('warning_packetloss_threshold').value = node.deviceData.warning_packetloss_threshold;
            document.getElementById('critical_latency_threshold').value = node.deviceData.critical_latency_threshold;
            document.getElementById('critical_packetloss_threshold').value = node.deviceData.critical_packetloss_threshold;
            document.getElementById('showLivePing').checked = node.deviceData.show_live_ping;
        } else {
            document.getElementById('modalTitle').textContent = 'Add Item';
            document.getElementById('deviceName').value = prefill.name || '';
            document.getElementById('deviceIp').value = prefill.ip || '';
        }
        toggleDeviceModalFields(document.getElementById('deviceType').value);
        deviceModal.classList.remove('hidden');
    };

    document.getElementById('deviceType').addEventListener('change', (e) => toggleDeviceModalFields(e.target.value));

    const openEdgeModal = (edgeId) => {
        const edge = edges.get(edgeId);
        document.getElementById('edgeId').value = edge.id;
        document.getElementById('connectionType').value = edge.connection_type || 'cat5';
        edgeModal.classList.remove('hidden');
    };

    const updateAndAnimateEdges = () => {
        tick++;
        const animatedDashes = [4 - (tick % 12), 8, tick % 12];
        const updates = [];
        const allEdges = edges.get();
        if (nodes.length > 0 && allEdges.length > 0) {
            const deviceStatusMap = new Map(nodes.get({ fields: ['id', 'deviceData'] }).map(d => [d.id, d.deviceData.status]));
            allEdges.forEach(edge => {
                const sourceStatus = deviceStatusMap.get(edge.from);
                const targetStatus = deviceStatusMap.get(edge.to);
                const isOffline = sourceStatus === 'offline' || targetStatus === 'offline';
                const isActive = sourceStatus === 'online' && targetStatus === 'online';
                const color = isOffline ? statusColorMap.offline : (edgeColorMap[edge.connection_type] || edgeColorMap.cat5);
                let dashes = false;
                if (isActive) { dashes = animatedDashes; } 
                else if (edge.connection_type === 'wifi' || edge.connection_type === 'radio') { dashes = [5, 5]; }
                updates.push({ id: edge.id, color, dashes });
            });
        }
        if (updates.length > 0) edges.update(updates);
        animationFrameId = requestAnimationFrame(updateAndAnimateEdges);
    };

    const setupAutoPing = (devices) => {
        Object.values(pingIntervals).forEach(clearInterval);
        pingIntervals = {};
        devices.forEach(device => {
            if (device.ping_interval > 0 && device.ip) {
                pingIntervals[device.id] = setInterval(() => pingSingleDevice(device.id), device.ping_interval * 1000);
            }
        });
    };

    const pingSingleDevice = async (deviceId) => {
        const node = nodes.get(deviceId);
        if (!node || node.deviceData.type === 'box') return;
        
        const oldStatus = node.deviceData.status;
        nodes.update({ id: deviceId, icon: { ...node.icon, color: '#06b6d4' } });
        const result = await api.post('check_device', { id: deviceId });
        const newStatus = result.status;

        if (newStatus !== oldStatus) {
            if (newStatus === 'critical' || newStatus === 'offline') {
                window.notyf.error({ message: `Device '${node.deviceData.name}' is now ${newStatus}.`, duration: 0, dismissible: true });
            } else if (newStatus === 'online' && (oldStatus === 'critical' || oldStatus === 'offline')) {
                window.notyf.success({ message: `Device '${node.deviceData.name}' is back online.`, duration: 3000 });
            }
        }

        const updatedDeviceData = { ...node.deviceData, status: newStatus, last_avg_time: result.last_avg_time, last_ttl: result.last_ttl };
        let label = updatedDeviceData.name;
        if (updatedDeviceData.show_live_ping && updatedDeviceData.status === 'online' && updatedDeviceData.last_avg_time !== null) {
            label += `\n${updatedDeviceData.last_avg_time}ms | TTL:${updatedDeviceData.last_ttl || 'N/A'}`;
        }
        nodes.update({ id: deviceId, deviceData: updatedDeviceData, icon: { ...node.icon, color: statusColorMap[newStatus] || statusColorMap.unknown }, title: `${updatedDeviceData.name}<br>${updatedDeviceData.ip || 'No IP'}<br>Status: ${newStatus}`, label: label });
    };

    deviceForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const formData = new FormData(deviceForm);
        const data = Object.fromEntries(formData.entries());
        const id = data.id;
        delete data.id;

        data.show_live_ping = document.getElementById('showLivePing').checked;

        try {
            if (id) {
                // For updates, we can send the whole data object. The backend handler is robust.
                await api.post('update_device', { id, updates: data });
                window.notyf.success('Item updated.');
            } else {
                // For creation, we need to sanitize empty strings to nulls for numeric/optional fields
                const numericFields = ['ping_interval', 'icon_size', 'name_text_size', 'warning_latency_threshold', 'warning_packetloss_threshold', 'critical_latency_threshold', 'critical_packetloss_threshold'];
                for (const key in data) {
                    if (numericFields.includes(key) && data[key] === '') {
                        data[key] = null;
                    }
                }
                if (data.ip === '') {
                    data.ip = null;
                }
                
                const createData = { ...data, map_id: currentMapId };
                await api.post('create_device', createData);
                window.notyf.success('Item created.');
            }
            deviceModal.classList.add('hidden');
            await switchMap(currentMapId);
        } catch (error) {
            console.error("Failed to save device:", error);
            window.notyf.error(error.message || "An error occurred while saving. Please try again.");
        }
    });

    edgeForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const id = document.getElementById('edgeId').value;
        const connection_type = document.getElementById('connectionType').value;
        await api.post('update_edge', { id, connection_type });
        edgeModal.classList.add('hidden');
        edges.update({ id, connection_type, label: connection_type });
        window.notyf.success('Connection updated.');
    });

    scanForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const subnet = document.getElementById('subnetInput').value;
        if (!subnet) return;
        scanInitialMessage.classList.add('hidden'); scanResults.innerHTML = ''; scanLoader.classList.remove('hidden');
        try {
            const result = await api.post('scan_network', { subnet });
            scanResults.innerHTML = result.devices.map(device => `<div class="flex items-center justify-between p-2 border-b border-slate-700"><div><div class="font-mono text-white">${device.ip}</div><div class="text-sm text-slate-400">${device.hostname || 'N/A'}</div></div><button class="add-scanned-device-btn px-3 py-1 bg-cyan-600/50 text-cyan-300 rounded-lg hover:bg-cyan-600/80 text-sm" data-ip="${device.ip}" data-name="${device.hostname || device.ip}">Add</button></div>`).join('') || '<p class="text-center text-slate-500 py-4">No devices found on this subnet.</p>';
        } catch (error) { scanResults.innerHTML = '<p class="text-center text-red-400 py-4">Scan failed. Ensure nmap is installed and the web server has permission to run it.</p>'; } 
        finally { scanLoader.classList.add('hidden'); }
    });

    scanResults.addEventListener('click', (e) => {
        if (e.target.classList.contains('add-scanned-device-btn')) {
            const { ip, name } = e.target.dataset;
            scanModal.classList.add('hidden'); openDeviceModal(null, { ip, name });
            e.target.textContent = 'Added'; e.target.disabled = true;
        }
    });

    refreshStatusBtn.addEventListener('click', async () => {
        refreshStatusBtn.disabled = true;
        const count = await performBulkRefresh();
        if (count > 0) {
            window.notyf.info(`Refreshed status for ${count} devices.`);
        }
        if (!liveRefreshToggle.checked) {
            refreshStatusBtn.disabled = false;
        }
    });

    liveRefreshToggle.addEventListener('change', (e) => {
        if (e.target.checked) {
            window.notyf.info(`Live status enabled. Updating every ${REFRESH_INTERVAL_SECONDS} seconds.`);
            refreshStatusBtn.disabled = true;
            performBulkRefresh();
            globalRefreshIntervalId = setInterval(performBulkRefresh, REFRESH_INTERVAL_SECONDS * 1000);
        } else {
            if (globalRefreshIntervalId) {
                clearInterval(globalRefreshIntervalId);
                globalRefreshIntervalId = null;
            }
            refreshStatusBtn.disabled = false;
            window.notyf.info('Live status disabled.');
        }
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
                    window.notyf.success('Map imported successfully.');
                } catch (err) { window.notyf.error('Failed to import map: ' + err.message); }
            };
            reader.readText(file);
        }
        importFile.value = '';
    });

    fullscreenBtn.addEventListener('click', () => { if (!document.fullscreenElement) { mapWrapper.requestFullscreen(); } else { document.exitFullscreen(); } });
    document.addEventListener('fullscreenchange', () => { const icon = fullscreenBtn.querySelector('i'); icon.classList.toggle('fa-expand', !document.fullscreenElement); icon.classList.toggle('fa-compress', !!document.fullscreenElement); });

    newMapBtn.addEventListener('click', createMap); createFirstMapBtn.addEventListener('click', createMap);
    deleteMapBtn.addEventListener('click', async () => { if (confirm(`Delete map "${mapSelector.options[mapSelector.selectedIndex].text}"?`)) { await api.post('delete_map', { id: currentMapId }); const firstMapId = await loadMaps(); await switchMap(firstMapId); window.notyf.success('Map deleted.'); } });
    mapSelector.addEventListener('change', (e) => switchMap(e.target.value));
    addDeviceBtn.addEventListener('click', () => openDeviceModal()); cancelBtn.addEventListener('click', () => deviceModal.classList.add('hidden'));
    addEdgeBtn.addEventListener('click', () => { network.addEdgeMode(); window.notyf.info('Click on a node to start a connection.'); });
    cancelEdgeBtn.addEventListener('click', () => edgeModal.classList.add('hidden'));
    scanNetworkBtn.addEventListener('click', () => scanModal.classList.remove('hidden'));
    closeScanModal.addEventListener('click', () => scanModal.classList.add('hidden'));

    (async () => {
        liveRefreshToggle.checked = false;
        const urlParams = new URLSearchParams(window.location.search);
        const mapToLoad = urlParams.get('map_id');
        const firstMapId = await loadMaps();
        const initialMapId = mapToLoad || firstMapId;
        if (initialMapId) {
            mapSelector.value = initialMapId;
            await switchMap(initialMapId);
            const deviceToEdit = urlParams.get('edit_device_id');
            if (deviceToEdit && nodes.get(deviceToEdit)) {
                openDeviceModal(deviceToEdit);
                const newUrl = window.location.pathname + `?map_id=${initialMapId}`;
                history.replaceState(null, '', newUrl);
            }
        }
    })();
}