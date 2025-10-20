<?php
require_once '../includes/functions.php';

// Ensure admin is logged in
if (!isAdminLoggedIn()) {
    redirectToAdminLogin();
}

$admin_id = $_SESSION['admin_id'];
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $host = trim($_POST['host'] ?? '');
    $port = trim($_POST['port'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? ''; // This could be '********' if masked
    $encryption = $_POST['encryption'] ?? 'tls';
    $from_email = trim($_POST['from_email'] ?? '');
    $from_name = trim($_POST['from_name'] ?? '');

    if (empty($host) || empty($port) || empty($username) || empty($from_email)) {
        $message = '<div class="alert-admin-error mb-4">Host, Port, Username, and From Email are required.</div>';
    } else {
        // If password is '********', it means no change was intended, so we pass the existing one.
        // The saveAdminSmtpSettings function handles this logic.
        if (saveAdminSmtpSettings($host, $port, $username, $password, $encryption, $from_email, $from_name)) {
            $message = '<div class="alert-admin-success mb-4">SMTP settings saved successfully!</div>';
        } else {
            $message = '<div class="alert-admin-error mb-4">Failed to save SMTP settings. Please check your input.</div>';
        }
    }
}

// Load current SMTP settings for display
$smtp_settings = getAdminSmtpSettings();

admin_header("SMTP Settings");
?>

<h1 class="text-4xl font-bold text-blue-400 mb-8 text-center">SMTP Settings</h1>

<div class="max-w-md mx-auto admin-card p-8">
    <?= $message ?>

    <form action="smtp_settings.php" method="POST" class="space-y-4">
        <div>
            <label for="host" class="block text-gray-300 text-sm font-bold mb-2">SMTP Host:</label>
            <input type="text" id="host" name="host" class="form-admin-input" value="<?= htmlspecialchars($smtp_settings['host'] ?? '') ?>" required>
        </div>
        <div>
            <label for="port" class="block text-gray-300 text-sm font-bold mb-2">SMTP Port:</label>
            <input type="number" id="port" name="port" class="form-admin-input" value="<?= htmlspecialchars($smtp_settings['port'] ?? '') ?>" required>
        </div>
        <div>
            <label for="username" class="block text-gray-300 text-sm font-bold mb-2">SMTP Username:</label>
            <input type="text" id="username" name="username" class="form-admin-input" value="<?= htmlspecialchars($smtp_settings['username'] ?? '') ?>" required>
        </div>
        <div>
            <label for="password" class="block text-gray-300 text-sm font-bold mb-2">SMTP Password:</label>
            <input type="password" id="password" name="password" class="form-admin-input" value="<?= htmlspecialchars($smtp_settings['password'] ?? '********') ?>" placeholder="Enter password or leave '********' to keep current">
        </div>
        <div>
            <label for="encryption" class="block text-gray-300 text-sm font-bold mb-2">Encryption:</label>
            <select id="encryption" name="encryption" class="form-admin-input">
                <option value="none" <?= (isset($smtp_settings['encryption']) && $smtp_settings['encryption'] === 'none') ? 'selected' : '' ?>>None</option>
                <option value="ssl" <?= (isset($smtp_settings['encryption']) && $smtp_settings['encryption'] === 'ssl') ? 'selected' : '' ?>>SSL</option>
                <option value="tls" <?= (isset($smtp_settings['encryption']) && $smtp_settings['encryption'] === 'tls') ? 'selected' : '' ?>>TLS</option>
            </select>
        </div>
        <div>
            <label for="from_email" class="block text-gray-300 text-sm font-bold mb-2">From Email Address:</label>
            <input type="email" id="from_email" name="from_email" class="form-admin-input" value="<?= htmlspecialchars($smtp_settings['from_email'] ?? '') ?>" required>
        </div>
        <div>
            <label for="from_name" class="block text-gray-300 text-sm font-bold mb-2">From Name (Optional):</label>
            <input type="text" id="from_name" name="from_name" class="form-admin-input" value="<?= htmlspecialchars($smtp_settings['from_name'] ?? '') ?>">
        </div>
        <button type="submit" class="btn-admin-primary w-full">
            <i class="fas fa-save mr-2"></i>Save Settings
        </button>
    </form>
</div>

<?php admin_footer(); ?>