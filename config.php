<?php
// Database configuration for Supabase
function getDbConnection() {
    $db_url = getenv('SUPABASE_DB_URL');
    if (empty($db_url)) {
        die("ERROR: SUPABASE_DB_URL environment variable is not set.");
    }

    // Parse the database URL
    $db_parts = parse_url($db_url);
    
    $host = $db_parts['host'];
    $port = $db_parts['port'];
    $dbname = ltrim($db_parts['path'], '/');
    $user = $db_parts['user'];
    $password = $db_parts['pass'];

    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname;user=$user;password=$password";

    try {
        $pdo = new PDO($dsn);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch(PDOException $e) {
        die("ERROR: Could not connect to the Supabase database. " . $e->getMessage());
    }
}
?>