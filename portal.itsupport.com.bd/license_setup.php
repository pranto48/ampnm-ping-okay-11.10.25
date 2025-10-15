<?php
$setup_message = '';
$config_file_path = __DIR__ . '/config.php';

// Function to update config.php with new DB credentials
function updateConfigFile($db_server, $db_name, $db_username, $db_password) {
    global $config_file_path;
    $content = <<<EOT
<?php
// External License Service Database Configuration
define('LICENSE_DB_SERVER', '{$db_server}');
define('LICENSE_DB_USERNAME', '{$db_username}');
define('LICENSE_DB_PASSWORD', '{$db_password}');
define('LICENSE_DB_NAME', '{$db_name}');

// Function to create database connection for the license service
function getLicenseDbConnection() {
    static \$pdo = null;

    if (\$pdo !== null) {
        try {
            \$pdo->query("SELECT 1");
        } catch (PDOException \$e) {
            if (isset(\$e->errorInfo[1]) && \$e->errorInfo[1] == 2006) {
                \$pdo = null;
            } else {
                throw \$e;
            }
        }
    }

    if (\$pdo === null) {
        try {
            \$dsn = "mysql:host=" . LICENSE_DB_SERVER . ";dbname=" . LICENSE_DB_NAME . ";charset=utf8mb4";
            \$options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            \$pdo = new PDO(\$dsn, LICENSE_DB_USERNAME, LICENSE_DB_PASSWORD, \$options);
        } catch(PDOException \$e) {
            // For a real application, you would log this error and show a generic message.
            // For this local tool, dying is acceptable to immediately see the problem.
            die("ERROR: Could not connect to the license database. " . \$e->getMessage());
        }
    }
    
    return \$pdo;
}
EOT;
    return file_put_contents($config_file_path, $content);
}

// Check if config.php exists and is configured
$is_configured = false;
if (file_exists($config_file_path)) {
    require_once $config_file_path;
    if (defined('LICENSE_DB_SERVER') && defined('LICENSE_DB_NAME')) {
        $is_configured = true;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'configure_db') {
        $db_server = $_POST['db_server'] ?? '';
        $db_name = $_POST['db_name'] ?? '';
        $db_username = $_POST['db_username'] ?? '';
        $db_password = $_POST['db_password'] ?? '';

        if (empty($db_server) || empty($db_name) || empty($db_username)) {
            $setup_message = '<p style="color: red;">All database fields except password are required.</p>';
        } else {
            try {
                // Attempt to connect to MySQL server (without selecting a database)
                $pdo_root = new PDO("mysql:host=$db_server", $db_username, $db_password);
                $pdo_root->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                // Create database if it doesn't exist
                $pdo_root->exec("CREATE DATABASE IF NOT EXISTS `{$db_name}`");
                $setup_message .= '<p style="color: green;">Database ' . htmlspecialchars($db_name) . ' checked/created successfully.</p>';

                // Update config.php
                if (updateConfigFile($db_server, $db_name, $db_username, $db_password)) {
                    $setup_message .= '<p style="color: green;">Configuration saved to config.php.</p>';
                    $is_configured = true;
                    // Reload config to use new settings
                    require_once $config_file_path;
                } else {
                    $setup_message .= '<p style="color: red;">Failed to write to config.php. Check file permissions.</p>';
                }

            } catch (PDOException $e) {
                $setup_message .= '<p style="color: red;">Database connection or creation failed: ' . htmlspecialchars($e->getMessage()) . '</p>';
            }
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'setup_tables' && $is_configured) {
        try {
            $pdo = getLicenseDbConnection();

            // Create licenses table
            $pdo->exec("CREATE TABLE IF NOT EXISTS `licenses` (
                `id` INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `license_key` VARCHAR(255) NOT NULL UNIQUE,
                `status` ENUM('active', 'free', 'expired', 'revoked') DEFAULT 'active',
                `issued_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `expires_at` TIMESTAMP NULL,
                `max_devices` INT(11) DEFAULT 1,
                `current_devices` INT(11) DEFAULT 0,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
            $setup_message .= '<p style="color: green;">Table `licenses` checked/created successfully.</p>';

            // Add initial license if provided
            $initial_license_key = $_POST['initial_license_key'] ?? '';
            $initial_max_devices = (int)($_POST['initial_max_devices'] ?? 1);
            $initial_expires_at = $_POST['initial_expires_at'] ?? '';

            if (!empty($initial_license_key)) {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM `licenses` WHERE license_key = ?");
                $stmt->execute([$initial_license_key]);
                if ($stmt->fetchColumn() == 0) {
                    $sql = "INSERT INTO `licenses` (license_key, max_devices, expires_at) VALUES (?, ?, ?)";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$initial_license_key, $initial_max_devices, empty($initial_expires_at) ? null : $initial_expires_at]);
                    $setup_message .= '<p style="color: green;">Initial license key added successfully.</p>';
                } else {
                    $setup_message .= '<p style="color: orange;">Initial license key already exists, skipped insertion.</p>';
                }
            }

            $setup_message .= '<p style="color: blue;">Database setup for license service completed!</p>';

        } catch (PDOException $e) {
            $setup_message .= '<p style="color: red;">Table creation or license insertion failed: ' . htmlspecialchars($e->getMessage()) . '</p>';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>License Service Setup</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background-color: #f4f4f4; color: #333; }
        .container { max-width: 600px; margin: 0 auto; background-color: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1, h2 { color: #0056b3; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input[type="text"], input[type="password"], input[type="number"], input[type="date"] {
            width: calc(100% - 22px); padding: 10px; margin-bottom: 15px; border: 1px solid #ddd; border-radius: 4px;
        }
        button { background-color: #007bff; color: white; padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; }
        button:hover { background-color: #0056b3; }
        .message { margin-top: 20px; padding: 10px; border-radius: 4px; }
        .message p { margin: 0; }
        .success { background-color: #d4edda; color: #155724; border-color: #c3e6cb; }
        .error { background-color: #f8d7da; color: #721c24; border-color: #f5c6cb; }
        .warning { background-color: #fff3cd; color: #856404; border-color: #ffeeba; }
    </style>
</head>
<body>
    <div class="container">
        <h1>License Service Setup</h1>
        <?php if (!empty($setup_message)): ?>
            <div class="message">
                <?= $setup_message ?>
            </div>
        <?php endif; ?>

        <?php if (!$is_configured): ?>
            <h2>Step 1: Configure Database Connection</h2>
            <form method="POST">
                <input type="hidden" name="action" value="configure_db">
                <label for="db_server">Database Host:</label>
                <input type="text" id="db_server" name="db_server" value="localhost" required>

                <label for="db_name">Database Name:</label>
                <input type="text" id="db_name" name="db_name" value="license_db" required>

                <label for="db_username">Database Username:</label>
                <input type="text" id="db_username" name="db_username" value="root" required>

                <label for="db_password">Database Password:</label>
                <input type="password" id="db_password" name="db_password">

                <button type="submit">Save Configuration & Create DB</button>
            </form>
        <?php else: ?>
            <p style="color: green;">Database configuration loaded from config.php.</p>
            <h2>Step 2: Setup License Tables & Add Initial License</h2>
            <form method="POST">
                <input type="hidden" name="action" value="setup_tables">
                <label for="initial_license_key">Initial License Key (Optional):</label>
                <input type="text" id="initial_license_key" name="initial_license_key" placeholder="e.g., PRO-LICENSE-XYZ">

                <label for="initial_max_devices">Max Devices (for initial license):</label>
                <input type="number" id="initial_max_devices" name="initial_max_devices" value="10" min="1">

                <label for="initial_expires_at">Expires At (YYYY-MM-DD, Optional):</label>
                <input type="date" id="initial_expires_at" name="initial_expires_at">

                <button type="submit">Setup Tables & Add License</button>
            </form>
            <p style="margin-top: 20px;"><a href="verify_license.php" target="_blank">Test verify_license.php endpoint</a></p>
        <?php endif; ?>
    </div>
</body>
</html>