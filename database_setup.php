<?php
// Database configuration using environment variables for Docker compatibility
$servername = getenv('DB_HOST') ?: 'localhost';
$username = 'root'; // Setup script needs root privileges to create DB and tables
$password = getenv('MYSQL_ROOT_PASSWORD') ?: ''; // Get root password from Docker env
$dbname = getenv('DB_NAME') ?: 'network_monitor';

function message($text, $is_error = false) {
    $color = $is_error ? '#ef4444' : '#22c55e';
    echo "<p style='color: $color; margin: 4px 0; font-family: monospace;'>$text</p>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Database Setup</title>
    <style>
        body { background-color: #0f172a; color: #cbd5e1; font-family: sans-serif; padding: 2rem; }
    </style>
</head>
<body>
<?php
try {
    // Connect to MySQL server (without selecting a database)
    $pdo = new PDO("mysql:host=$servername", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create database if it doesn't exist
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname`");
    message("Database '$dbname' checked/created successfully.");

    // Reconnect, this time selecting the new database
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // SQL statements for table creation
    $tables = [
        "CREATE TABLE IF NOT EXISTS `ping_results` (
            `id` INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `host` VARCHAR(100) NOT NULL,
            `packet_loss` INT(3) NOT NULL,
            `avg_time` DECIMAL(10,2) NOT NULL,
            `min_time` DECIMAL(10,2) NOT NULL,
            `max_time` DECIMAL(10,2) NOT NULL,
            `success` BOOLEAN NOT NULL,
            `output` TEXT,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        "CREATE TABLE IF NOT EXISTS `maps` (
            `id` INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(100) NOT NULL,
            `type` VARCHAR(50) NOT NULL,
            `description` TEXT,
            `is_default` BOOLEAN DEFAULT FALSE,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        "CREATE TABLE IF NOT EXISTS `devices` (
            `id` INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `ip` VARCHAR(15) NULL,
            `name` VARCHAR(100) NOT NULL,
            `status` ENUM('online', 'offline', 'unknown', 'warning', 'critical') DEFAULT 'unknown',
            `last_seen` TIMESTAMP NULL,
            `type` VARCHAR(50) NOT NULL DEFAULT 'server',
            `description` TEXT,
            `enabled` BOOLEAN DEFAULT TRUE,
            `x` DECIMAL(10, 4) NULL,
            `y` DECIMAL(10, 4) NULL,
            `map_id` INT(6) UNSIGNED,
            `ping_interval` INT(11) NULL,
            `icon_size` INT(11) DEFAULT 50,
            `name_text_size` INT(11) DEFAULT 14,
            `warning_latency_threshold` INT(11) NULL,
            `warning_packetloss_threshold` INT(11) NULL,
            `critical_latency_threshold` INT(11) NULL,
            `critical_packetloss_threshold` INT(11) NULL,
            `last_avg_time` DECIMAL(10, 2) NULL,
            `last_ttl` INT(11) NULL,
            `show_live_ping` BOOLEAN DEFAULT FALSE,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY `unique_ip_map` (`ip`, `map_id`),
            FOREIGN KEY (`map_id`) REFERENCES `maps`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        "CREATE TABLE IF NOT EXISTS `device_edges` (
            `id` INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `source_id` INT(6) UNSIGNED NOT NULL,
            `target_id` INT(6) UNSIGNED NOT NULL,
            `map_id` INT(6) UNSIGNED NOT NULL,
            `connection_type` VARCHAR(50) DEFAULT 'cat5',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`source_id`) REFERENCES `devices`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`target_id`) REFERENCES `devices`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`map_id`) REFERENCES `maps`(`id`) ON DELETE CASCADE,
            UNIQUE KEY `unique_edge` (`source_id`, `target_id`, `map_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
    ];

    // Execute table creation queries
    foreach ($tables as $sql) {
        $pdo->exec($sql);
        preg_match('/CREATE TABLE IF NOT EXISTS `(\w+)`/', $sql, $matches);
        $tableName = $matches[1] ?? 'unknown';
        message("Table '$tableName' checked/created successfully.");
    }

    // Check if any maps exist
    $stmt = $pdo->query("SELECT COUNT(*) FROM `maps`");
    $mapCount = $stmt->fetchColumn();

    if ($mapCount == 0) {
        $pdo->exec("INSERT INTO `maps` (name, type, is_default) VALUES ('Default LAN Map', 'lan', TRUE)");
        message("Created a default map as no maps were found.");
    }

    echo "<h2 style='color: #06b6d4; font-family: sans-serif;'>Database setup completed successfully!</h2>";

} catch (PDOException $e) {
    message("Database setup failed: " . $e->getMessage(), true);
    exit(1);
}
?>
    <a href="index.php" style="color: #22d3ee; text-decoration: none; font-size: 1.2rem;">&larr; Back to Dashboard</a>
</body>
</html>