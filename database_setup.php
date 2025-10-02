<?php
// Database setup script
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
if ($conn->query($sql) {
    echo "Database created successfully<br>";
} else {
    echo "Error creating database: " . $conn->error . "<br>";
}

// Select database
$conn->select_db($dbname);

// Create table
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

if ($conn->query($sql)) {
    echo "Table created successfully<br>";
} else {
    echo "Error creating table: " . $conn->error . "<br>";
}

$conn->close();
echo "Database setup completed successfully!";
?>