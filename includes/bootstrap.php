<?php
// Copyright Integrity Check
$footerPath = __DIR__ . '/../footer.php';
$expectedHash = 'c85a22888b69b12880d1526b88a4120a'; // MD5 hash of the correct footer.php

if (!file_exists($footerPath) || md5_file($footerPath) !== $expectedHash) {
    die("Critical Error: Copyright information has been tampered with or removed. Please restore the original footer file to continue using the application.");
}

// This is the new central bootstrap file.
// It handles basic setup like loading functions and checking database integrity.

require_once __DIR__ . '/functions.php';

// This script should not run on the setup page itself to avoid a redirect loop.
if (basename($_SERVER['PHP_SELF']) !== 'database_setup.php') {
    try {
        $pdo = getDbConnection();
        // A simple query to check if the main 'users' table exists.
        // If this fails, we assume the database has not been initialized.
        $pdo->query("SELECT 1 FROM `users` LIMIT 1");
    } catch (PDOException $e) {
        // Check for the specific "table not found" error.
        if (strpos($e->getMessage(), 'Base table or view not found') !== false) {
            // The database is connected, but tables are missing. Redirect to setup.
            header('Location: database_setup.php');
            exit;
        } else {
            // A different, more serious database error occurred.
            die("A critical database error occurred: " . $e->getMessage());
        }
    }
}

// Start session management after DB check.
// This ensures sessions are available on all pages that include this bootstrap.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}