function initMap() {
    const API_URL = 'api.php';
    // ... (rest of variables) ...
    let network = null, nodes = new vis.DataSet([]), edges = new vis.DataSet([]), currentMapId = null, pingIntervals = {};
    let animationFrameId = null;

    // Cleanup function to be called by the router before leaving the page
    window.cleanup = () => {
        if (animationFrameId) {
            cancelAnimationFrame(animationFrameId);
            animationFrameId = null;
        }
        Object.values(pingIntervals).forEach(clearInterval);
        pingIntervals = {};
        if (network) {
            network.destroy();
            network = null;
        }
        window.cleanup = null; // Deregister self
    };

    // ... (all other functions from the original file) ...
    
    // The final async IIFE needs to be called directly
    (async () => {
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
            }
        }
    })();
}