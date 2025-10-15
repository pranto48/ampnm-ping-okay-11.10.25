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

            // Create products table
            $pdo->exec("CREATE TABLE IF NOT EXISTS `products` (
                `id` INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `name` VARCHAR(255) NOT NULL,
                `description` TEXT,
                `price` DECIMAL(10, 2) NOT NULL,
                `max_devices` INT(11) DEFAULT 1,
                `license_duration_days` INT(11) DEFAULT 365, -- e.g., 365 for 1 year
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
            $setup_message .= '<p style="color: green;">Table `products` checked/created successfully.</p>';

            // Create customers table
            $pdo->exec("CREATE TABLE IF NOT EXISTS `customers` (
                `id` INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `email` VARCHAR(255) NOT NULL UNIQUE,
                `password` VARCHAR(255) NOT NULL,
                `first_name` VARCHAR(255),
                `last_name` VARCHAR(255),
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
            $setup_message .= '<p style="color: green;">Table `customers` checked/created successfully.</p>';

            // Create orders table
            $pdo->exec("CREATE TABLE IF NOT EXISTS `orders` (
                `id` INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `customer_id` INT(11) UNSIGNED NOT NULL,
                `order_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `total_amount` DECIMAL(10, 2) NOT NULL,
                `status` ENUM('pending', 'completed', 'failed', 'refunded') DEFAULT 'pending',
                `payment_intent_id` VARCHAR(255) NULL, -- For Stripe/PayPal integration
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
            $setup_message .= '<p style="color: green;">Table `orders` checked/created successfully.</p>';

            // Create order_items table
            $pdo->exec("CREATE TABLE IF NOT EXISTS `order_items` (
                `id` INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `order_id` INT(11) UNSIGNED NOT NULL,
                `product_id` INT(11) UNSIGNED NOT NULL,
                `quantity` INT(11) NOT NULL DEFAULT 1,
                `price` DECIMAL(10, 2) NOT NULL,
                `license_key_generated` VARCHAR(255) NULL, -- Store the generated license key here
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE,
                FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
            $setup_message .= '<p style="color: green;">Table `order_items` checked/created successfully.</p>';

            // Modify licenses table to link to customers and products
            // Add customer_id and product_id if they don't exist
            function columnExists($pdo, $table, $column) {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
                $stmt->execute([$table, $column]);
                return $stmt->fetchColumn() > 0;
            }

            if (!columnExists($pdo, 'licenses', 'customer_id')) {
                $pdo->exec("ALTER TABLE `licenses` ADD COLUMN `customer_id` INT(11) UNSIGNED NULL AFTER `id`;");
                $pdo->exec("ALTER TABLE `licenses` ADD CONSTRAINT `fk_licenses_customer_id` FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE SET NULL;");
                $setup_message .= '<p style="color: green;">Added `customer_id` to `licenses` table.</p>';
            }
            if (!columnExists($pdo, 'licenses', 'product_id')) {
                $pdo->exec("ALTER TABLE `licenses` ADD COLUMN `product_id` INT(11) UNSIGNED NULL AFTER `customer_id`;");
                $pdo->exec("ALTER TABLE `licenses` ADD CONSTRAINT `fk_licenses_product_id` FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE SET NULL;");
                $setup_message .= '<p style="color: green;">Added `product_id` to `licenses` table.</p>';
            }

            // Create admin_users table for the portal's admin panel
            $pdo->exec("CREATE TABLE IF NOT EXISTS `admin_users` (
                `id` INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `username` VARCHAR(255) NOT NULL UNIQUE,
                `password` VARCHAR(255) NOT NULL,
                `email` VARCHAR(255) NOT NULL UNIQUE,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
            $setup_message .= '<p style="color: green;">Table `admin_users` checked/created successfully.</p>';

            // Insert default admin user for the portal if not exists
            $admin_username = 'admin';
            $admin_email = 'admin@portal.itsupport.com.bd';
            $admin_password = 'adminpassword'; // Default password for portal admin

            $stmt = $pdo->prepare("SELECT id FROM `admin_users` WHERE username = ?");
            $stmt->execute([$admin_username]);
            if (!$stmt->fetch()) {
                $hashed_password = password_hash($admin_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO `admin_users` (username, password, email) VALUES (?, ?, ?)");
                $stmt->execute([$admin_username, $hashed_password, $admin_email]);
                $setup_message .= '<p style="color: green;">Default admin user for portal created: ' . htmlspecialchars($admin_username) . ' with password: ' . htmlspecialchars($admin_password) . '</p>';
            } else {
                $setup_message .= '<p style="color: orange;">Portal admin user already exists.</p>';
            }

            // Insert some sample products if they don't exist
            $sample_products = [
                ['name' => 'AMPNM Basic License (10 Devices / 1 Year)', 'description' => 'Basic license for up to 10 devices, valid for 1 year.', 'price' => 99.00, 'max_devices' => 10, 'license_duration_days' => 365],
                ['name' => 'AMPNM Pro License (50 Devices / 1 Year)', 'description' => 'Pro license for up to 50 devices, valid for 1 year.', 'price' => 299.00, 'max_devices' => 50, 'license_duration_days' => 365],
                ['name' => 'AMPNM Enterprise License (Unlimited Devices / 1 Year)', 'description' => 'Enterprise license for unlimited devices, valid for 1 year.', 'price' => 999.00, 'max_devices' => 99999, 'license_duration_days' => 365],
            ];

            foreach ($sample_products as $product_data) {
                $stmt = $pdo->prepare("SELECT id FROM `products` WHERE name = ?");
                $stmt->execute([$product_data['name']]);
                if (!$stmt->fetch()) {
                    $sql = "INSERT INTO `products` (name, description, price, max_devices, license_duration_days) VALUES (?, ?, ?, ?, ?)";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$product_data['name'], $product_data['description'], $product_data['price'], $product_data['max_devices'], $product_data['license_duration_days']]);
                    $setup_message .= '<p style="color: green;">Added sample product: ' . htmlspecialchars($product_data['name']) . '</p>';
                } else {
                    $setup_message .= '<p style="color: orange;">Sample product already exists: ' . htmlspecialchars($product_data['name']) . '</p>';
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
        input[type="text"], input[type="password"], input[type="email"], input[type="number"], input[type="date"] {
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
            <h2>Step 2: Setup License Tables & Add Initial Data</h2>
            <form method="POST">
                <input type="hidden" name="action" value="setup_tables">
                <p>This will create all necessary tables and add sample products and a default admin user for the portal.</p>
                <button type="submit">Setup Tables & Initial Data</button>
            </form>
            <p style="margin-top: 20px;"><a href="verify_license.php" target="_blank">Test verify_license.php endpoint</a></p>
        <?php endif; ?>
    </div>
</body>
</html>