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

// Check contracts table just in case
try {
    $db->query("SELECT case_id FROM contracts LIMIT 1");
    echo "contracts.case_id exists.\n";
} catch (PDOException $e) {
    echo "contracts.case_id missing, adding it...\n";
    try {
        $db->exec("ALTER TABLE contracts ADD COLUMN case_id VARCHAR(50) DEFAULT NULL AFTER name");
        echo "Successfully added contracts.case_id\n";
    } catch (PDOException $ex) {
        echo "Error adding column: " . $ex->getMessage() . "\n";
    }
}
?>