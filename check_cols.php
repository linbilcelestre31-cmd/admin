<?php
require 'db/db.php';
try {
    $pdo = get_pdo();
    echo "Columns for reservations:\n";
    $stmt = $pdo->query('SHOW COLUMNS FROM reservations');
    while ($row = $stmt->fetch()) {
        echo $row['Field'] . "\n";
    }
    echo "\nColumns for maintenance_logs:\n";
    $stmt = $pdo->query('SHOW COLUMNS FROM maintenance_logs');
    while ($row = $stmt->fetch()) {
        echo $row['Field'] . "\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>