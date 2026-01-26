<?php
require 'db/db.php';
$db = get_pdo();

// Check documents table
try {
    $db->query("SELECT case_id FROM documents LIMIT 1");
    echo "documents.case_id exists.\n";
} catch (PDOException $e) {
    echo "documents.case_id missing, adding it...\n";
    try {
        $db->exec("ALTER TABLE documents ADD COLUMN case_id VARCHAR(50) DEFAULT NULL AFTER name");
        echo "Successfully added documents.case_id\n";
    } catch (PDOException $ex) {
        echo "Error adding column: " . $ex->getMessage() . "\n";
    }
}

// Check documents.uploaded_at
try {
    $db->query("SELECT uploaded_at FROM documents LIMIT 1");
    echo "documents.uploaded_at exists.\n";
} catch (PDOException $e) {
    echo "documents.uploaded_at missing, adding it...\n";
    try {
        $db->exec("ALTER TABLE documents ADD COLUMN uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
        echo "Successfully added documents.uploaded_at\n";
    } catch (PDOException $ex) {
        echo "Error: " . $ex->getMessage() . "\n";
    }
}
?>