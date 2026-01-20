<?php
require_once 'db/db.php';
$pdo = get_pdo();
$stmt = $pdo->query("DESCRIBE documents");
echo json_encode($stmt->fetchAll());
?>