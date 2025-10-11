window.MapApp = window.MapApp || {};

MapApp.deviceManager = {
    pingSingleDevice: async (deviceId) => {
        const node = MapApp.state.nodes.get(deviceId);
        if (!node || node.deviceData.type === 'box') return;
        
        const oldStatus = node.deviceData.status;
        MapApp.state.nodes.update({ id: deviceId, icon: { ...node.icon, color: '#06b6d4' } });
        const result = await MapApp.api.post('check_device', { id: deviceId });
        const newStatus = result.status;

        if (newStatus !== oldStatus) {
            if (newStatus === 'critical' || newStatus === 'offline') {
                window.notyf.error({ message: `Device '${node.deviceData.name}' is now ${newStatus}.`, duration: 0, dismissible: true });
            } else if (newStatus === 'online' && (oldStatus === 'critical' || oldStatus === 'offline')) {
                window.notyf.success({ message: `Device '${node.deviceData.name}' is back online.`, duration: 3000 });
            }
        }

        const updatedDeviceData = { ...node.deviceData, status: newStatus, last_avg_time: result.last_avg_time, last_ttl: result.last_ttl, last_ping_output: result.last_ping_output };
        let label = updatedDeviceData.name;
        if (updatedDeviceData.show_live_ping && updatedDeviceData.status === 'online' && updatedDeviceData.last_avg_time !== null) {
            label += `\n${updatedDeviceData.last_avg_time}ms | TTL:${updatedDeviceData.last_ttl || 'N/A'}`;
        }
        MapApp.state.nodes.update({ id: deviceId, deviceData: updatedDeviceData, icon: { ...node.icon, color: MapApp.config.statusColorMap[newStatus] || MapApp.config.statusColorMap.unknown }, title: MapApp.utils.buildNodeTitle(updatedDeviceData), label: label });
    },

    performBulkRefresh: async () => {
        const icon = MapApp.ui.els.refreshStatusBtn.querySelector('i');
        icon.classList.add('fa-spin');
        
        const pingableNodes = MapApp.state.nodes.get({
            filter: (item) => item.deviceData && item.deviceData.ip && item.deviceData.type !== 'box'
        });

        if (pingableNodes.length === 0) {
            icon.classList.remove('fa-spin');
            return 0;
        }

        const pingPromises = pingableNodes.map(node => MapApp.deviceManager.pingSingleDevice(node.id));

        try {
            await Promise.all(pingPromises);
        } catch (error) {
            console.error("An error occurred during the bulk refresh process:", error);
            window.notyf.error("Some device checks may have failed during the refresh.");
        } finally {
            icon.classList.remove('fa-spin');
        }
        
        return pingableNodes.length;
    },

    setupAutoPing: (devices) => {
        Object.values(MapApp.state.pingIntervals).forEach(clearInterval);
        MapApp.state.pingIntervals = {};
        devices.forEach(device => {
            if (device.ping_interval > 0 && device.ip) {
                MapApp.state.pingIntervals[device.id] = setInterval(() => MapApp.deviceManager.pingSingleDevice(device.id), device.ping_interval * 1000);
            }
        });
    }
};