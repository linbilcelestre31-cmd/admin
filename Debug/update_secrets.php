<?php
$conn = new mysqli("localhost", "root", "", "admin_new");
if ($conn->connect_error)
    die("Error: " . $conn->connect_error);

$departments = ['HR1', 'HR2', 'HR3', 'HR4', 'CORE1', 'CORE2', 'LOG1', 'LOG2'];
$secret = hash('sha256', 'hr_secret_key_2026'); // Same secret as requested

foreach ($departments as $dept) {
    $stmt = $conn->prepare("INSERT INTO department_secrets (department, secret_key) VALUES (?, ?) ON DUPLICATE KEY UPDATE secret_key = ?");
    $stmt->bind_param("sss", $dept, $secret, $secret);
    $stmt->execute();
}

echo "SSO Secrets updated successfully.";
?>