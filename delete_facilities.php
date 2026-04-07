<?php
require_once 'db/db.php';

echo "Attempting to connect to database...\n";

try {
    $pdo = get_pdo();
    echo "Database connection successful!\n";
    
    // First, check if the entries exist
    $check_stmt = $pdo->prepare('SELECT id, name, type FROM facilities WHERE name = ?');
    $check_stmt->execute(['LIN BIL IMPUESTO CELESTRE']);
    $entries = $check_stmt->fetchAll();
    
    if (empty($entries)) {
        echo "No entries found with name 'LIN BIL IMPUESTO CELESTRE'.\n";
    } else {
        echo "Found " . count($entries) . " entries to delete:\n";
        foreach ($entries as $entry) {
            echo "- ID: {$entry['id']}, Name: {$entry['name']}, Type: {$entry['type']}\n";
        }
        
        // Delete the entries
        $stmt = $pdo->prepare('DELETE FROM facilities WHERE name = ?');
        $stmt->execute(['LIN BIL IMPUESTO CELESTRE']);
        $count = $stmt->rowCount();
        
        echo "Successfully deleted $count entries with name 'LIN BIL IMPUESTO CELESTRE'.\n";
    }
    
    // Show remaining facilities to confirm deletion
    echo "\nRemaining facilities in database:\n";
    $remaining = $pdo->query('SELECT id, name, type, status FROM facilities ORDER BY name')->fetchAll();
    
    if (empty($remaining)) {
        echo "No facilities remaining in database.\n";
    } else {
        foreach ($remaining as $facility) {
            echo "- ID: {$facility['id']}, Name: {$facility['name']}, Type: {$facility['type']}, Status: {$facility['status']}\n";
        }
    }
    
} catch (PDOException $e) {
    echo "Database Error: " . $e->getMessage() . "\n";
    echo "Error Code: " . $e->getCode() . "\n";
    
    if ($e->getCode() == 2002) {
        echo "This usually means MySQL server is not running.\n";
        echo "Please start MySQL/XAMPP and try again.\n";
    }
} catch (Exception $e) {
    echo "General Error: " . $e->getMessage() . "\n";
}

echo "\nScript completed.\n";
?>
