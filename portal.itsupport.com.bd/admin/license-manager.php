<?php
require_once '../includes/functions.php';

// Ensure admin is logged in
if (!isAdminLoggedIn()) {
    redirectToAdminLogin();
}

$pdo = getLicenseDbConnection();
$message = '';

// Handle generate license action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_license'])) {
    $customer_id = (int)$_POST['customer_id'];
    $product_id = (int)$_POST['product_id'];
    $status = $_POST['status'] ?? 'active';

    try {
        // Fetch product details to get max_devices and duration
        $stmt = $pdo->prepare("SELECT max_devices, license_duration_days FROM `products` WHERE id = ?");
        $stmt->execute([$product_id]);
        $product_details = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$product_details) {
            throw new Exception("Product details not found for ID: " . $product_id);
        }

        $max_devices = $product_details['max_devices'];
        $license_duration_days = $product_details['license_duration_days'];
        $expires_at = date('Y-m-d H:i:s', strtotime("+$license_duration_days days"));

        $license_key = generateLicenseKey();
        $stmt = $pdo->prepare("INSERT INTO `licenses` (customer_id, product_id, license_key, status, max_devices, expires_at, last_active_at) VALUES (?, ?, ?, ?, ?, ?, NOW())"); // Set last_active_at on generation
        $stmt->execute([$customer_id, $product_id, $license_key, $status, $max_devices, $expires_at]);
        $message = '<div class="alert-admin-success mb-4">License generated successfully: ' . htmlspecialchars($license_key) . '</div>';
    } catch (Exception $e) {
        $message = '<div class="alert-admin-error mb-4">Error generating license: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}

// Handle update license status/expiry
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_license'])) {
    $license_id = (int)$_POST['license_id'];
    $new_status = $_POST['new_status'] ?? 'active';
    $new_expires_at = $_POST['new_expires_at'] ?? null;
    $new_max_devices = (int)$_POST['new_max_devices'];

    try {
        $sql = "UPDATE `licenses` SET status = ?, expires_at = ?, max_devices = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$new_status, $new_expires_at, $new_max_devices, $license_id]);
        $message = '<div class="alert-admin-success mb-4">License updated successfully.</div>';
    } catch (PDOException $e) {
        $message = '<div class="alert-admin-error mb-4">Error updating license: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}

// Handle delete license action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_license'])) {
    $license_id = (int)$_POST['license_id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM `licenses` WHERE id = ?");
        $stmt->execute([$license_id]);
        $message = '<div class="alert-admin-success mb-4">License deleted successfully.</div>';
    } catch (PDOException $e) {
        $message = '<div class="alert-admin-error mb-4">Error deleting license: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}

// Fetch all licenses with customer and product info
$stmt_licenses = $pdo->query("
    SELECT l.*, c.email as customer_email, p.name as product_name
    FROM `licenses` l
    LEFT JOIN `customers` c ON l.customer_id = c.id
    LEFT JOIN `products` p ON l.product_id = p.id
    ORDER BY l.created_at DESC
");
$licenses = $stmt_licenses->fetchAll(PDO::FETCH_ASSOC);

// Fetch all customers for dropdown
$stmt_customers = $pdo->query("SELECT id, email FROM `customers` ORDER BY email ASC");
$customers = $stmt_customers->fetchAll(PDO::FETCH_ASSOC);

// Fetch all products for dropdown
$stmt_products = $pdo->query("SELECT id, name FROM `products` ORDER BY name ASC");
$products = $stmt_products->fetchAll(PDO::FETCH_ASSOC);

admin_header("Manage Licenses");
?>

<h1 class="text-4xl font-bold text-blue-400 mb-8 text-center">Manage Licenses</h1>

<?= $message ?>

<div class="admin-card mb-8 p-6">
    <h2 class="text-2xl font-semibold text-blue-400 mb-4">Generate New License</h2>
    <form action="license-manager.php" method="POST" class="space-y-4">
        <div>
            <label for="customer_id" class="block text-gray-300 text-sm font-bold mb-2">Assign to Customer:</label>
            <select id="customer_id" name="customer_id" class="form-admin-input" required>
                <option value="">-- Select Customer --</option>
                <?php foreach ($customers as $customer): ?>
                    <option value="<?= htmlspecialchars($customer['id']) ?>"><?= htmlspecialchars($customer['email']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="product_id" class="block text-gray-300 text-sm font-bold mb-2">Product Type:</label>
            <select id="product_id" name="product_id" class="form-admin-input" required>
                <option value="">-- Select Product --</option>
                <?php foreach ($products as $product): ?>
                    <option value="<?= htmlspecialchars($product['id']) ?>"><?= htmlspecialchars($product['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="status" class="block text-gray-300 text-sm font-bold mb-2">Initial Status:</label>
            <select id="status" name="status" class="form-admin-input">
                <option value="active">Active</option>
                <option value="free">Free</option>
                <option value="expired">Expired</option>
                <option value="revoked">Revoked</option>
            </select>
        </div>
        <button type="submit" name="generate_license" class="btn-admin-primary">
            <i class="fas fa-plus-circle mr-1"></i>Generate License
        </button>
    </form>
</div>

<div class="admin-card p-6">
    <h2 class="text-2xl font-semibold text-blue-400 mb-4">All Licenses</h2>
    <?php if (empty($licenses)): ?>
        <p class="text-center text-gray-400 py-8">No licenses generated yet.</p>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="min-w-full bg-gray-700 rounded-lg">
                <thead>
                    <tr class="bg-gray-600 text-gray-200 uppercase text-sm leading-normal">
                        <th class="py-3 px-6 text-left">Key</th>
                        <th class="py-3 px-6 text-left">Customer</th>
                        <th class="py-3 px-6 text-left">Product</th>
                        <th class="py-3 px-6 text-left">Status</th>
                        <th class="py-3 px-6 text-left">Max Devices</th>
                        <th class="py-3 px-6 text-left">Current Devices</th>
                        <th class="py-3 px-6 text-left">Last Active</th>
                        <th class="py-3 px-6 text-left">Expires At</th>
                        <th class="py-3 px-6 text-center">Actions</th>
                    </tr>
                </thead>
                <tbody class="text-gray-300 text-sm font-light">
                    <?php foreach ($licenses as $license): ?>
                        <tr class="border-b border-gray-600 hover:bg-gray-600">
                            <td class="py-3 px-6 text-left font-mono break-all"><?= htmlspecialchars($license['license_key']) ?></td>
                            <td class="py-3 px-6 text-left"><?= htmlspecialchars($license['customer_email'] ?: 'N/A') ?></td>
                            <td class="py-3 px-6 text-left"><?= htmlspecialchars($license['product_name'] ?: 'N/A') ?></td>
                            <td class="py-3 px-6 text-left">
                                <span class="py-1 px-3 rounded-full text-xs <?= $license['status'] == 'active' ? 'bg-green-500' : ($license['status'] == 'expired' ? 'bg-red-500' : 'bg-yellow-500') ?>">
                                    <?= htmlspecialchars(ucfirst($license['status'])) ?>
                                </span>
                            </td>
                            <td class="py-3 px-6 text-left"><?= htmlspecialchars($license['max_devices']) ?></td>
                            <td class="py-3 px-6 text-left"><?= htmlspecialchars($license['current_devices']) ?></td>
                            <td class="py-3 px-6 text-left"><?= $license['last_active_at'] ? date('Y-m-d H:i', strtotime($license['last_active_at'])) : 'Never' ?></td>
                            <td class="py-3 px-6 text-left"><?= $license['expires_at'] ? date('Y-m-d', strtotime($license['expires_at'])) : 'Never' ?></td>
                            <td class="py-3 px-6 text-center">
                                <button onclick="openEditLicenseModal(<?= htmlspecialchars(json_encode($license)) ?>)" class="btn-admin-primary text-xs px-3 py-1 mr-2">
                                    <i class="fas fa-edit mr-1"></i>Edit
                                </button>
                                <form action="license-manager.php" method="POST" onsubmit="return confirm('Are you sure you want to delete this license?');" class="inline-block">
                                    <input type="hidden" name="license_id" value="<?= htmlspecialchars($license['id']) ?>">
                                    <button type="submit" name="delete_license" class="btn-admin-danger text-xs px-3 py-1">
                                        <i class="fas fa-trash-alt mr-1"></i>Delete
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Edit License Modal -->
<div id="editLicenseModal" class="fixed inset-0 bg-gray-900 bg-opacity-75 flex items-center justify-center hidden">
    <div class="bg-gray-700 p-8 rounded-lg shadow-xl w-full max-w-md">
        <h2 class="text-2xl font-semibold text-blue-400 mb-4">Edit License</h2>
        <form action="license-manager.php" method="POST" class="space-y-4">
            <input type="hidden" name="license_id" id="edit_license_id">
            <div>
                <label for="edit_license_key" class="block text-gray-300 text-sm font-bold mb-2">License Key:</label>
                <input type="text" id="edit_license_key" class="form-admin-input" readonly>
            </div>
            <div>
                <label for="edit_customer_email" class="block text-gray-300 text-sm font-bold mb-2">Customer Email:</label>
                <input type="text" id="edit_customer_email" class="form-admin-input" readonly>
            </div>
            <div>
                <label for="edit_product_name" class="block text-gray-300 text-sm font-bold mb-2">Product:</label>
                <input type="text" id="edit_product_name" class="form-admin-input" readonly>
            </div>
            <div>
                <label for="new_status" class="block text-gray-300 text-sm font-bold mb-2">Status:</label>
                <select id="new_status" name="new_status" class="form-admin-input">
                    <option value="active">Active</option>
                    <option value="free">Free</option>
                    <option value="expired">Expired</option>
                    <option value="revoked">Revoked</option>
                </select>
            </div>
            <div>
                <label for="new_max_devices" class="block text-gray-300 text-sm font-bold mb-2">Max Devices:</label>
                <input type="number" id="new_max_devices" name="new_max_devices" class="form-admin-input" required>
            </div>
            <div>
                <label for="new_expires_at" class="block text-gray-300 text-sm font-bold mb-2">Expires At:</label>
                <input type="date" id="new_expires_at" name="new_expires_at" class="form-admin-input">
            </div>
            <div class="flex justify-end space-x-4">
                <button type="button" onclick="closeEditLicenseModal()" class="btn-admin-secondary">Cancel</button>
                <button type="submit" name="update_license" class="btn-admin-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openEditLicenseModal(license) {
        document.getElementById('edit_license_id').value = license.id;
        document.getElementById('edit_license_key').value = license.license_key;
        document.getElementById('edit_customer_email').value = license.customer_email || 'N/A';
        document.getElementById('edit_product_name').value = license.product_name || 'N/A';
        document.getElementById('new_status').value = license.status;
        document.getElementById('new_max_devices').value = license.max_devices;
        document.getElementById('new_expires_at').value = license.expires_at ? license.expires_at.split(' ')[0] : ''; // Extract date part
        document.getElementById('editLicenseModal').classList.remove('hidden');
    }

    function closeEditLicenseModal() {
        document.getElementById('editLicenseModal').classList.add('hidden');
    }
</script>

<?php admin_footer(); ?>