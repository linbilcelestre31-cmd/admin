<?php
session_start();
require "../connections.php";

if (!isset($_SESSION['user_id']) || !isset($_GET['dept'])) {
    die("Unauthorized access or missing department.");
}

$dept = $_GET['dept'] ?? '';
$user_email = $_SESSION['email'] ?? 'admin@atiera.com';
$user_role = $_SESSION['role'] ?? 'super_admin';
$user_name = $_SESSION['name'] ?? 'Super Admin';

// 1. Ensure Table Exists
$conn->query("CREATE TABLE IF NOT EXISTS department_secrets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    department VARCHAR(50) UNIQUE,
    secret_key VARCHAR(255),
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// 2. Fetch Secret Key
$stmt = $conn->prepare("SELECT secret_key FROM department_secrets WHERE department=? AND is_active=1 LIMIT 1");
$stmt->bind_param("s", $dept);
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();

if (!$res) {
    // Auto-init for known departments if missing
    $auto_init_depts = ['HR1', 'HR2', 'HR3', 'HR4', 'CORE1', 'CORE2', 'LOG1', 'LOG2', 'FIN1'];
    if (in_array($dept, $auto_init_depts)) {
        $default_secret = strtolower($dept) . '_secret_key_2026';
        $ins = $conn->prepare("INSERT INTO department_secrets (department, secret_key) VALUES (?, ?) ON DUPLICATE KEY UPDATE secret_key=VALUES(secret_key)");
        $ins->bind_param("ss", $dept, $default_secret);
        $ins->execute();

        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
    } else {
        die("SSO Error: Secret not configured for $dept.");
    }
}

$secret = $res['secret_key'];

// 3. Prepare Payload
$payload = [
    "user_id" => $_SESSION['user_id'],
    "email" => $user_email,
    "name" => $user_name,
    "role" => $user_role,
    "dept" => $dept,
    "exp" => time() + 300 // 5 minutes
];

$payloadJson = json_encode($payload, JSON_UNESCAPED_SLASHES);
$signature = hash_hmac("sha256", $payloadJson, $secret);

// 4. Encode Token
$tokenData = [
    "payload" => $payload,
    "signature" => $signature
];
$token = base64_encode(json_encode($tokenData));

// 5. Define Target URLs
$sso_urls = [
    'HR1' => 'https://hr1.atierahotelandrestaurant.com/hr1/sso-login.php',
    'HR2' => 'https://hr2.atierahotelandrestaurant.com/hr2/sso-login.php',
    'HR3' => 'https://hr3.atierahotelandrestaurant.com/hr3/sso-login.php',
    'HR4' => 'https://hr4.atierahotelandrestaurant.com/hr4/sso-login.php',
    'CORE1' => 'https://core1.atierahotelandrestaurant.com/core1/sso-login.php',
    'CORE2' => 'core2.atierahotelandrestaurant.com/core2/sso-login.php',
    'LOG1' => 'https://logistics1.atierahotelandrestaurant.com/logistics1/sso-login.php',
    'LOG2' => 'https://logistics2.atierahotelandrestaurant.com/logistics2/sso-login.php',
    'FIN1' => 'https://finance1.atierahotelandrestaurant.com/finance1/sso-login.php',
];

$target_url = $sso_urls[$dept] ?? "https://" . strtolower($dept) . ".atierahotelandrestaurant.com/sso-login.php";

// Redirect
header("Location: $target_url?token=" . urlencode($token));
exit;
?>