<?php
require 'db/db.php';
$pdo = get_pdo();
$stmt = $pdo->query('SHOW COLUMNS FROM direct_checkins');
while ($row = $stmt->fetch()) {
    echo $row['Field'] . "\n";
}
?>