<?php
require_once "db/db.php";
try {
    $pdo = get_pdo();
    $stmt = $pdo->query("DESC users");
    print_r($stmt->fetchAll());
} catch (Exception $e) {
    echo $e->getMessage();
}
?>