<?php
require_once 'includes/auth_check.php';
include 'header.php';
?>

<main id="app">
    <div class="container mx-auto px-4 py-8">
        <h1 class="text-3xl font-bold text-white mb-6">Email Notifications</h1>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- SMTP Settings -->
            <div class="lg:col-span-1">
                <div class="bg-slate-800 border border-slate-700 rounded-lg shadow-xl p-6">
                    <h2 class="text-xl font-semibold text-white mb-4">SMTP Server Settings</h2>
                    <p class="text-sm text-slate-400 mb-4">
                        These settings are managed in your <code class="bg-slate-900 text-cyan-400 px-1 rounded">docker-compose.yml</code> file for security.
                        Update the environment variables and rebuild the app to apply changes.
                    </p>
                    <form id="testEmailForm" class="space-y-4">
                        <div>
                            <label for="testEmail" class="block text-sm font-medium text-slate-300 mb-1">Send Test Email To</label>
                            <input type="email" id="testEmail" name="test_email" required class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500" value="<?= htmlspecialchars(getenv('NOTIFICATION_EMAIL')) ?>">
                        </div>
                        <button type="submit" class="w-full px-6 py-2 bg-cyan-600 text-white rounded-lg hover:bg-cyan-700">
                            <i class="fas fa-paper-plane mr-2"></i>Send Test Email
                        </button>
                    </form>
                </div>
            </div>

            <!-- Device Notification Toggles -->
            <div class="lg:col-span-2">
                <div class="bg-slate-800 border border-slate-700 rounded-lg shadow-xl p-6">
                    <h2 class="text-xl font-semibold text-white mb-4">Device Alert Preferences</h2>
                    <p class="text-sm text-slate-400 mb-4">
                        Enable email alerts for specific devices. You will be notified when a device's status changes to <span class="text-yellow-400">Warning</span>, <span class="text-red-400">Critical</span>, or <span class="text-slate-400">Offline</span>.
                    </p>
                    <div id="device-notification-list" class="space-y-2 max-h-96 overflow-y-auto">
                        <!-- Device list will be populated by JS -->
                    </div>
                    <div id="notificationListLoader" class="text-center py-8"><div class="loader mx-auto"></div></div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include 'footer.php'; ?>