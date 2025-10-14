<?php
// Database configuration using environment variables for Docker compatibility
// Forcing 127.0.0.1 as the host to resolve connection issues in the Docker environment.
define('DB_SERVER', '127.0.0.1');
define('DB_USERNAME', getenv('DB_USER') ?: 'root');
define('DB_PASSWORD', getenv('DB_PASSWORD') ?: '');
define('DB_NAME', getenv('DB_NAME') ?: 'network_monitor');

// License Management Configuration
// IMPORTANT: Replace 'http://localhost:8000/api/v1/validate-license' with the actual URL of your new PHP app's license validation API.
define('LICENSE_API_URL', getenv('LICENSE_API_URL') ?: 'http://localhost:8000/api/v1/validate-license');
// This will be the license key provided to your customers.
define('LICENSE_KEY', getenv('LICENSE_KEY') ?: 'YOUR_DEFAULT_LICENSE_KEY');


// Create database connection
function getDbConnection() {
    static $pdo = null;

    // If a connection exists, check if it's still alive.
    if ($pdo !== null) {
        try {
            $pdo->query("SELECT 1");
        } catch (PDOException $e) {
            // Error code 2006 is "MySQL server has gone away".
            // If that's the case, nullify the connection to force a reconnect.
            if (isset($e->errorInfo[1]) && $e->errorInfo[1] == 2006) {
                $pdo = null;
            } else {
                // For other errors, we can re-throw them.
                throw $e;
            }
        }
    }

    // If no connection exists (or it was lost), create a new one.
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_SERVER . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            $pdo = new PDO($dsn, DB_USERNAME, DB_PASSWORD, $options);
        } catch(PDOException $e) {
            // For a real application, you would log this error and show a generic message.
            // For this local tool, dying is acceptable to immediately see the problem.
            die("ERROR: Could not connect to the database. " . $e->getMessage());
        }
    }
    
    return $pdo;
}