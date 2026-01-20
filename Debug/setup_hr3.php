<?php
require_once "db/db.php";
try {
    $pdo = get_pdo();

    // Create department_secrets table if not exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS department_secrets (
      id INT AUTO_INCREMENT PRIMARY KEY,
      department VARCHAR(50) UNIQUE,
      secret_key VARCHAR(255),
      is_active TINYINT(1) DEFAULT 1,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Insert HR3 secret
    // SHA2('hr3_secret_key_2026', 256) in PHP is hash('sha256', 'hr3_secret_key_2026')
    $secret = hash('sha256', 'hr3_secret_key_2026');
    $stmt = $pdo->prepare("INSERT INTO department_secrets (department, secret_key) VALUES ('HR3', ?) ON DUPLICATE KEY UPDATE secret_key=VALUES(secret_key)");
    $stmt->execute([$secret]);

    echo "Database setup completed for HR3.";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>