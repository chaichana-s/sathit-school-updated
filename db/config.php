<?php
// Configuration for Database Connection

$db_host = 'localhost';
$db_name = 'satitwittaya_db';
$db_user = 'root'; // Default XAMPP user
$db_pass = '';     // Default XAMPP has no password

try {
    // Create a new PDO instance
    // Using charset=utf8mb4 to support full unicode and emojis safely
    $dsn = "mysql:host={$db_host};dbname={$db_name};charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Throw exceptions on errors
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Fetch associative arrays by default
        PDO::ATTR_EMULATE_PREPARES   => false,                  // Use true prepared statements to prevent SQL Injection
    ];

    $pdo = new PDO($dsn, $db_user, $db_pass, $options);
} catch (PDOException $e) {
    // If the database doesn't exist yet, we catch it here.
    // If we get an unknown database error (1049), we allow it to pass ONLY when running setup.php
    if ($e->getCode() == 1049 && basename($_SERVER['PHP_SELF']) == 'setup.php') {
        // We will create it in setup.php
        $dsn_no_db = "mysql:host={$db_host};charset=utf8mb4";
        $pdo = new PDO($dsn_no_db, $db_user, $db_pass, $options);
    } else {
        // Log the error securely without exposing details to the user
        error_log("Database connection failed: " . $e->getMessage());
        die("An error occurred connecting to the database. Please try again later or contact the administrator.");
    }
}
?>
