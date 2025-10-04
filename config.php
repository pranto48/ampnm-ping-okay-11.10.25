<?php
// Database configuration using environment variables for Docker compatibility
define('DB_SERVER', getenv('DB_HOST') ?: 'localhost');
define('DB_USERNAME', getenv('DB_USER') ?: 'root');
define('DB_PASSWORD', getenv('DB_PASSWORD') ?: '');
define('DB_NAME', getenv('DB_NAME') ?: 'network_monitor');

// Create database connection
function getDbConnection() {
    try {
        $pdo = new PDO("mysql:host=" . DB_SERVER . ";dbname=" . DB_NAME, DB_USERNAME, DB_PASSWORD);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch(PDOException $e) {
        // For a real application, you would log this error and show a generic message.
        // For this local tool, dying is acceptable to immediately see the problem.
        die("ERROR: Could not connect to the database. " . $e->getMessage());
    }
}
?>