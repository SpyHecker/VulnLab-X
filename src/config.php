<?php
session_start();

// Enable error reporting to help with debugging (and demonstrating vulnerabilities!)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$dbPath = getenv('DB_PATH') ?: '/var/www/data/vulnmart.db';

try {
    $db = new PDO("sqlite:" . $dbPath);
    // Set errormode to exception
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database Connection failed: " . $e->getMessage());
}
?>
