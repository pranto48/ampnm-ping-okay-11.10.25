function initNotifications() {
    const API_URL = 'api.php';
    const deviceListContainer = document.getElementById('device-notification-list');
    const loader = document.getElementById('notificationListLoader');
    const testEmailForm = document.getElementById('testEmailForm');

    const api = {
        get: (action) => fetch(`${API_URL}?action=${action}`).then(res => res.json()),
        post: (action, body) => fetch(`${API_URL}?action=${action}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body)
        }).then(res => res.json())
    };

    const loadDeviceSettings = async () => {
        loader.classList.remove('hidden');
        deviceListContainer.innerHTML = '';
        try {
            const devices = await api.get('get_notification_settings');
            if (devices.length === 0) {
                deviceListContainer.innerHTML = '<p class="text-slate-500 text-center">No devices found. Please add devices on the Devices page.</p>';
                return;
            }
            
            devices.forEach(device => {
                const deviceEl = document.createElement('div');
                deviceEl.className = 'flex items-center justify-between p-3 bg-slate-900/50 rounded-lg';
                deviceEl.innerHTML = `
                    <div>
                        <div class="font-medium text-white">${device.name}</div>
                        <div class="text-sm text-slate-400 font-mono">${device.ip || 'No IP'}</div>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" class="sr-only peer notification-toggle" data-id="${device.id}" ${device.notifications_enabled ? 'checked' : ''}>
                        <div class="w-11 h-6 bg-slate-600 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-cyan-600"></div>
                    </label>
                `;
                deviceListContainer.appendChild(deviceEl);
            });
        } catch (error) {
            console.error('Failed to load device settings:', error);
            deviceListContainer.innerHTML = '<p class="text-red-400 text-center">Could not load device settings.</p>';
        } finally {
            loader.classList.add('hidden');
        }
    };

    deviceListContainer.addEventListener('change', async (e) => {
        if (e.target.classList.contains('notification-toggle')) {
            const deviceId = e.target.dataset.id;
            const enabled = e.target.checked;
            try {
                const result = await api.post('update_notification_settings', { id: deviceId, enabled: enabled });
                if (!result.success) {
                    window.notyf.error('Failed to update setting.');
                    e.target.checked = !enabled; // Revert on failure
                }
            } catch (error) {
                window.notyf.error('An error occurred.');
                e.target.checked = !enabled; // Revert on failure
            }
        }
    });

    testEmailForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const email = e.target.test_email.value;
        const button = e.target.querySelector('button[type="submit"]');
        button.disabled = true;
        button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Sending...';

        try {
            const result = await api.post('test_smtp_settings', { email });
            if (result.success) {
                window.notyf.success('Test email sent successfully!');
            } else {
                window.notyf.error(`Error: ${result.message}`);
            }
        } catch (error) {
            window.notyf.error('An unexpected error occurred.');
        } finally {
            button.disabled = false;
            button.innerHTML = '<i class="fas fa-paper-plane mr-2"></i>Send Test Email';
        }
    });

    loadDeviceSettings();
}