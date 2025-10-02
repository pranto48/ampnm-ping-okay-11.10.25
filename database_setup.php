<?php
// Database configuration
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "network_monitor";

// Create connection
$conn = new mysqli($servername, $username, $password);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create database
$sql = "CREATE DATABASE IF NOT EXISTS $dbname";
if ($conn->query($sql) === TRUE) {
    echo "Database created successfully<br>";
} else {
    echo "Error creating database: " . $conn->error . "<br>";
}

// Select database
$conn->select_db($dbname);

// Create ping_results table
$sql = "CREATE TABLE IF NOT EXISTS ping_results (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    host VARCHAR(100) NOT NULL,
    packet_loss INT(3) NOT NULL,
    avg_time DECIMAL(10,2) NOT NULL,
    min_time DECIMAL(10,2) NOT NULL,
    max_time DECIMAL(10,2) NOT NULL,
    success BOOLEAN NOT NULL,
    output TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if ($conn->query($sql) === TRUE) {
    echo "Table ping_results created successfully<br>";
} else {
    echo "Error creating table: " . $conn->error . "<br>";
}

// Create maps table
$sql = "CREATE TABLE IF NOT EXISTS maps (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    type VARCHAR(50) NOT NULL,
    description TEXT,
    is_default BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

if ($conn->query($sql) === TRUE) {
    echo "Table maps created successfully<br>";
} else {
    echo "Error creating table: " . $conn->error . "<br>";
}

// Create devices table
$sql = "CREATE TABLE IF NOT EXISTS devices (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ip VARCHAR(15) NOT NULL,
    name VARCHAR(100) NOT NULL,
    status ENUM('online', 'offline', 'unknown') DEFAULT 'unknown',
    last_seen TIMESTAMP NULL,
    type VARCHAR(50) NOT NULL DEFAULT 'server',
    description TEXT,
    enabled BOOLEAN DEFAULT TRUE,
    x DECIMAL(10, 4) NULL,
    y DECIMAL(10, 4) NULL,
    map_id INT(6) UNSIGNED,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_ip_map (ip, map_id),
    FOREIGN KEY (map_id) REFERENCES maps(id) ON DELETE CASCADE
)";

if ($conn->query($sql) === TRUE) {
    echo "Table devices created successfully<br>";
} else {
    echo "Error creating table: " . $conn->error . "<br>";
}

// Create device_edges table
$sql = "CREATE TABLE IF NOT EXISTS device_edges (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    source_id INT(6) UNSIGNED NOT NULL,
    target_id INT(6) UNSIGNED NOT NULL,
    map_id INT(6) UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (source_id) REFERENCES devices(id) ON DELETE CASCADE,
    FOREIGN KEY (target_id) REFERENCES devices(id) ON DELETE CASCADE,
    FOREIGN KEY (map_id) REFERENCES maps(id) ON DELETE CASCADE,
    UNIQUE KEY unique_edge (source_id, target_id, map_id)
)";

if ($conn->query($sql) === TRUE) {
    echo "Table device_edges created successfully<br>";
} else {
    echo "Error creating table: " . $conn->error . "<br>";
}


$conn->close();
echo "Database setup completed successfully!";
?>