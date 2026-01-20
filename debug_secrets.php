<?php
require_once "db/db.php";
try {
    $pdo = get_pdo();
    $stmt = $pdo->query("SELECT * FROM department_secrets");
    $results = $stmt->fetchAll();
    echo "<pre>";
    print_r($results);
    echo "</pre>";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>