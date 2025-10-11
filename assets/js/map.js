function initMap() {
    // Initialize all modules
    MapApp.ui.cacheElements();

    const {
        els
    } = MapApp.ui;
    const {
        api
    } = MapApp;
    const {
        state
    } = MapApp;
    const {
        mapManager
    } = MapApp;
    const {
        deviceManager
    } = MapApp;

    // Cleanup function for SPA navigation
    window.cleanup = () => {
        if (state.animationFrameId) {
            cancelAnimationFrame(state.animationFrameId);
            state.animationFrameId = null;
        }
        Object.values(state.pingIntervals).forEach(clearInterval);
        state.pingIntervals = {};
        if (state.globalRefreshIntervalId) {
            clearInterval(state.globalRefreshIntervalId);
            state.globalRefreshIntervalId = null;
        }
        if (state.network) {
            state.network.destroy();
            state.network = null;
        }
        window.cleanup = null;
    };

    // Event Listeners Setup
    els.deviceForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(els.deviceForm);
        const data = Object.fromEntries(formData.entries());
        const id = data.id;
        delete data.id;
        data.show_live_ping = document.getElementById('showLivePing').checked;

        try {
            if (id) {
                await api.post('update_device', { id, updates: data });
                window.notyf.success('Item updated.');
            } else {
                const numericFields = ['ping_interval', 'icon_size', 'name_text_size', 'warning_latency_threshold', 'warning_packetloss_threshold', 'critical_latency_threshold', 'critical_packetloss_threshold'];
                for (const key in data) {
                    if (numericFields.includes(key) && data[key] === '') data[key] = null;
                }
                if (data.ip === '') data.ip = null;
                
                const newDevice = await api.post('create_device', { ...data, map_id: state.currentMapId });
                const visNode = {
                    id: newDevice.id, label: newDevice.name, title: MapApp.utils.buildNodeTitle(newDevice), x: newDevice.x, y: newDevice.y,
                    shape: 'icon', icon: { face: "'Font Awesome 6 Free'", weight: "900", code: MapApp.config.iconMap[newDevice.type] || MapApp.config.iconMap.other, size: parseInt(newDevice.icon_size) || 50, color: MapApp.config.statusColorMap[newDevice.status] || MapApp.config.statusColorMap.unknown },
                    font: { color: 'white', size: parseInt(newDevice.name_text_size) || 14, multi: true }, deviceData: newDevice
                };
                if (newDevice.type === 'box') {
                    Object.assign(visNode, { shape: 'box', color: { background: 'rgba(49, 65, 85, 0.5)', border: '#475569' }, margin: 20, level: -1 });
                }
                state.nodes.add(visNode);
                window.notyf.success('Item created.');
            }
            els.deviceModal.classList.add('hidden');
        } catch (error) {
            console.error("Failed to save device:", error);
            window.notyf.error(error.message || "An error occurred while saving.");
        }
    });

    els.edgeForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const id = document.getElementById('edgeId').value;
        const connection_type = document.getElementById('connectionType').value;
        await api.post('update_edge', { id, connection_type });
        els.edgeModal.classList.add('hidden');
        state.edges.update({ id, connection_type, label: connection_type });
        window.notyf.success('Connection updated.');
    });

    els.scanForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const subnet = document.getElementById('subnetInput').value;
        if (!subnet) return;
        els.scanInitialMessage.classList.add('hidden');
        els.scanResults.innerHTML = '';
        els.scanLoader.classList.remove('hidden');
        try {
            const result = await api.post('scan_network', { subnet });
            els.scanResults.innerHTML = result.devices.map(device => `<div class="flex items-center justify-between p-2 border-b border-slate-700"><div><div class="font-mono text-white">${device.ip}</div><div class="text-sm text-slate-400">${device.hostname || 'N/A'}</div></div><button class="add-scanned-device-btn px-3 py-1 bg-cyan-600/50 text-cyan-300 rounded-lg hover:bg-cyan-600/80 text-sm" data-ip="${device.ip}" data-name="${device.hostname || device.ip}">Add</button></div>`).join('') || '<p class="text-center text-slate-500 py-4">No devices found.</p>';
        } catch (error) {
            els.scanResults.innerHTML = '<p class="text-center text-red-400 py-4">Scan failed. Ensure nmap is installed.</p>';
        } finally {
            els.scanLoader.classList.add('hidden');
        }
    });

    els.scanResults.addEventListener('click', (e) => {
        if (e.target.classList.contains('add-scanned-device-btn')) {
            const { ip, name } = e.target.dataset;
            els.scanModal.classList.add('hidden');
            MapApp.ui.openDeviceModal(null, { ip, name });
            e.target.textContent = 'Added';
            e.target.disabled = true;
        }
    });

    els.refreshStatusBtn.addEventListener('click', async () => {
        els.refreshStatusBtn.disabled = true;
        await deviceManager.performBulkRefresh();
        if (!els.liveRefreshToggle.checked) els.refreshStatusBtn.disabled = false;
    });

    els.liveRefreshToggle.addEventListener('change', (e) => {
        if (e.target.checked) {
            window.notyf.info(`Live status enabled. Updating every ${MapApp.config.REFRESH_INTERVAL_SECONDS} seconds.`);
            els.refreshStatusBtn.disabled = true;
            deviceManager.performBulkRefresh();
            state.globalRefreshIntervalId = setInterval(deviceManager.performBulkRefresh, MapApp.config.REFRESH_INTERVAL_SECONDS * 1000);
        } else {
            if (state.globalRefreshIntervalId) clearInterval(state.globalRefreshIntervalId);
            state.globalRefreshIntervalId = null;
            els.refreshStatusBtn.disabled = false;
            window.notyf.info('Live status disabled.');
        }
    });

    els.importBtn.addEventListener('click', () => els.importFile.click());
    els.importFile.addEventListener('change', (e) => {
        const file = e.target.files[0];
        if (!file) return;
        if (confirm('This will overwrite the current map. Are you sure?')) {
            const reader = new FileReader();
            reader.onload = async (event) => {
                try {
                    const data = JSON.parse(event.target.result);
                    await api.post('import_map', { map_id: state.currentMapId, ...data });
                    await mapManager.switchMap(state.currentMapId);
                    window.notyf.success('Map imported successfully.');
                } catch (err) {
                    window.notyf.error('Failed to import map: ' + err.message);
                }
            };
            reader.readText(file);
        }
        els.importFile.value = '';
    });

    els.fullscreenBtn.addEventListener('click', () => {
        if (!document.fullscreenElement) els.mapWrapper.requestFullscreen();
        else document.exitFullscreen();
    });
    document.addEventListener('fullscreenchange', () => {
        const icon = els.fullscreenBtn.querySelector('i');
        icon.classList.toggle('fa-expand', !document.fullscreenElement);
        icon.classList.toggle('fa-compress', !!document.fullscreenElement);
    });

    els.newMapBtn.addEventListener('click', mapManager.createMap);
    els.createFirstMapBtn.addEventListener('click', mapManager.createMap);
    els.deleteMapBtn.addEventListener('click', async () => {
        if (confirm(`Delete map "${els.mapSelector.options[els.mapSelector.selectedIndex].text}"?`)) {
            await api.post('delete_map', { id: state.currentMapId });
            const firstMapId = await mapManager.loadMaps();
            await mapManager.switchMap(firstMapId);
            window.notyf.success('Map deleted.');
        }
    });
    els.mapSelector.addEventListener('change', (e) => mapManager.switchMap(e.target.value));
    els.addDeviceBtn.addEventListener('click', () => MapApp.ui.openDeviceModal());
    els.cancelBtn.addEventListener('click', () => els.deviceModal.classList.add('hidden'));
    els.addEdgeBtn.addEventListener('click', () => {
        state.network.addEdgeMode();
        window.notyf.info('Click on a node to start a connection.');
    });
    els.cancelEdgeBtn.addEventListener('click', () => els.edgeModal.classList.add('hidden'));
    els.scanNetworkBtn.addEventListener('click', () => els.scanModal.classList.remove('hidden'));
    els.closeScanModal.addEventListener('click', () => els.scanModal.classList.add('hidden'));
    document.getElementById('deviceType').addEventListener('change', (e) => MapApp.ui.toggleDeviceModalFields(e.target.value));

    // Initial Load
    (async () => {
        els.liveRefreshToggle.checked = false;
        const urlParams = new URLSearchParams(window.location.search);
        const mapToLoad = urlParams.get('map_id');
        const firstMapId = await mapManager.loadMaps();
        const initialMapId = mapToLoad || firstMapId;
        if (initialMapId) {
            els.mapSelector.value = initialMapId;
            await mapManager.switchMap(initialMapId);
            const deviceToEdit = urlParams.get('edit_device_id');
            if (deviceToEdit && state.nodes.get(deviceToEdit)) {
                MapApp.ui.openDeviceModal(deviceToEdit);
                const newUrl = window.location.pathname + `?map_id=${initialMapId}`;
                history.replaceState(null, '', newUrl);
            }
        }
    })();
}