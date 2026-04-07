<?php
require_once __DIR__ . '/../db/db.php';
$db = get_pdo();

// Renaming marvin79 to "Executive Meeting Room"
$oldName = 'marvin79';
$newName = 'Executive Meeting Room';
$newRate = 500.00;
$newCapacity = 5;

try {
    $stmt = $db->prepare("UPDATE facilities SET name = ?, hourly_rate = ?, capacity = ? WHERE name = ?");
    $stmt->execute([$newName, $newRate, $newCapacity, $oldName]);
    echo "Successfully updated facility name from '$oldName' to '$newName'.";
} catch (PDOException $e) {
    echo "Error updating facility: " . $e->getMessage();
}
?>