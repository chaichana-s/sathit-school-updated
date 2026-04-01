<?php
require_once __DIR__ . '/config.php';

try {
    // Check if updated_at already exists
    $result = $pdo->query("SHOW COLUMNS FROM attendance LIKE 'updated_at'");
    if ($result->rowCount() == 0) {
        $pdo->exec("ALTER TABLE attendance ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
        
        // Also update all existing rows' updated_at to match their created_at so they don't all look like they were modified right now
        $pdo->exec("UPDATE attendance SET updated_at = created_at");
        echo "Successfully added updated_at column to attendance table.";
    } else {
        echo "Column updated_at already exists in attendance table.";
    }
} catch (PDOException $e) {
    die("Migration failed: " . $e->getMessage());
}
?>
