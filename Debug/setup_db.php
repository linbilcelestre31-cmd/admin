<?php
require_once "connections.php";

$sql = "
CREATE TABLE IF NOT EXISTS department_secrets (
  id INT AUTO_INCREMENT PRIMARY KEY,
  department VARCHAR(50) UNIQUE,
  secret_key VARCHAR(255),
  is_active TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);";

if ($conn->query($sql)) {
    echo "Table department_secrets created successfully.<br>";
} else {
    echo "Error creating table: " . $conn->error . "<br>";
}

// SHA2('hr3_secret_key_2026', 256)
$secret = hash('sha256', 'hr3_secret_key_2026');
$stmt = $conn->prepare("INSERT INTO department_secrets (department, secret_key) VALUES ('HR3', ?) ON DUPLICATE KEY UPDATE secret_key=VALUES(secret_key)");
$stmt->bind_param("s", $secret);

if ($stmt->execute()) {
    echo "HR3 secret key inserted/updated successfully.<br>";
} else {
    echo "Error inserting record: " . $stmt->error . "<br>";
}
?>