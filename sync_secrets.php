<?php
// Sync secrets between admin_new and hr3_db
$secret_text = 'hr3_secret_key_2026';
$secret_hash = hash('sha256', $secret_text);

// 1. Update Admin Database
$conn_admin = new mysqli('127.0.0.1', 'admin_new', '123', 'admin_new');
if (!$conn_admin->connect_error) {
    $stmt = $conn_admin->prepare("INSERT INTO department_secrets (department, secret_key) VALUES ('HR3', ?) ON DUPLICATE KEY UPDATE secret_key=VALUES(secret_key)");
    $stmt->bind_param("s", $secret_hash);
    $stmt->execute();
    echo "Admin secret updated to hash.<br>";
}

// 2. Update HR3 Database
$conn_hr3 = new mysqli('127.0.0.1', 'root', '', 'hr3_db');
if (!$conn_hr3->connect_error) {
    $stmt = $conn_hr3->prepare("INSERT INTO department_secrets (department, secret_key) VALUES ('HR3', ?) ON DUPLICATE KEY UPDATE secret_key=VALUES(secret_key)");
    $stmt->bind_param("s", $secret_hash);
    $stmt->execute();
    echo "HR3 secret updated to hash.<br>";
} else {
    echo "HR3 DB connection failed: " . $conn_hr3->connect_error . "<br>";
}
?>