<?php
require_once 'includes/auth_check.php';
include 'header.php';

// Ensure only admin can access this page
if (!isset($_SESSION['username']) || $_SESSION['username'] !== 'admin') {
    header('Location: index.php'); // Redirect non-admins
    exit;
}
?>

<main id="app">
    <div class="container mx-auto px-4 py-8">
        <h1 class="text-3xl font-bold text-white mb-6">License Management</h1>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <!-- Create/Edit License Form -->
            <div class="md:col-span-1">
                <div class="bg-slate-800 border border-slate-700 rounded-lg shadow-xl p-6">
                    <h2 id="licenseFormTitle" class="text-xl font-semibold text-white mb-4">Create New License</h2>
                    <form id="licenseForm" class="space-y-4">
                        <input type="hidden" id="licenseId" name="id">
                        <div>
                            <label for="licenseUserId" class="block text-sm font-medium text-slate-300 mb-1">User (Email)</label>
                            <select id="licenseUserId" name="user_id" required class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500">
                                <option value="">-- Select User --</option>
                                <!-- Users will be populated by JS -->
                            </select>
                        </div>
                        <div>
                            <label for="licenseKey" class="block text-sm font-medium text-slate-300 mb-1">License Key</label>
                            <input type="text" id="licenseKey" name="license_key" required class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500">
                        </div>
                        <div>
                            <label for="licenseStatus" class="block text-sm font-medium text-slate-300 mb-1">Status</label>
                            <select id="licenseStatus" name="status" class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500">
                                <option value="active">Active</option>
                                <option value="free">Free</option>
                                <option value="expired">Expired</option>
                                <option value="revoked">Revoked</option>
                            </select>
                        </div>
                        <div>
                            <label for="licenseIssuedAt" class="block text-sm font-medium text-slate-300 mb-1">Issued At</label>
                            <input type="datetime-local" id="licenseIssuedAt" name="issued_at" class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500">
                        </div>
                        <div>
                            <label for="licenseExpiresAt" class="block text-sm font-medium text-slate-300 mb-1">Expires At</label>
                            <input type="datetime-local" id="licenseExpiresAt" name="expires_at" class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500">
                        </div>
                        <div>
                            <label for="licenseMaxDevices" class="block text-sm font-medium text-slate-300 mb-1">Max Devices</label>
                            <input type="number" id="licenseMaxDevices" name="max_devices" min="1" required class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500">
                        </div>
                        <div class="flex justify-end gap-2">
                            <button type="button" id="cancelLicenseEditBtn" class="px-4 py-2 bg-slate-700 text-slate-300 rounded-lg hover:bg-slate-600 hidden">Cancel</button>
                            <button type="submit" id="saveLicenseBtn" class="px-6 py-2 bg-cyan-600 text-white rounded-lg hover:bg-cyan-700">
                                <i class="fas fa-save mr-2"></i>Save License
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- License List -->
            <div class="md:col-span-2">
                <div class="bg-slate-800 border border-slate-700 rounded-lg shadow-xl p-6">
                    <h2 class="text-xl font-semibold text-white mb-4">Existing Licenses</h2>
                    <div class="overflow-x-auto">
                        <table class="min-w-full">
                            <thead class="border-b border-slate-700">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-400 uppercase">User</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-400 uppercase">Key</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-400 uppercase">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-400 uppercase">Devices</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-400 uppercase">Expires</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-400 uppercase">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="licensesTableBody">
                                <!-- License rows will be inserted here by JavaScript -->
                            </tbody>
                        </table>
                        <div id="licensesLoader" class="text-center py-8"><div class="loader mx-auto"></div></div>
                        <div id="noLicensesMessage" class="text-center py-8 hidden">
                            <i class="fas fa-key text-slate-600 text-4xl mb-4"></i>
                            <p class="text-slate-500">No licenses found.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include 'footer.php'; ?>