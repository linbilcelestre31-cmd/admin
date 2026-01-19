<?php
$conn = new mysqli("127.0.0.1", "root", "", "admin_new");
if ($conn->connect_error) {
    die("Error: " . $conn->connect_error);
}

// Create table
$conn->query("CREATE TABLE IF NOT EXISTS department_secrets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    department VARCHAR(50),
    secret_key VARCHAR(255),
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Clear existing
$conn->query("DELETE FROM department_secrets WHERE department IN ('HR1', 'HR3')");

// Insert keys
$conn->query("INSERT INTO department_secrets (department, secret_key) 
           VALUES ('HR1', SHA2('hr_secret_key_2026', 256))");
$conn->query("INSERT INTO department_secrets (department, secret_key) 
           VALUES ('HR3', SHA2('hr_secret_key_2026', 256))");

echo "Migration successful\n";
?>