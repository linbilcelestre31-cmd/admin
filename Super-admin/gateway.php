<?php
/**
 * REFINED SSO GATEWAY
 */
session_start();
require "../connections.php";

if (!isset($_SESSION['user_id']) || !isset($_GET['dept'])) {
    die("Unauthorized access or missing department.");
}

$dept = $_GET['dept'] ?? '';
$user_id = $_SESSION['user_id'];
$user_email = $_SESSION['email'] ?? 'admin@atiera.com';
$user_role = $_SESSION['role'] ?? 'super_admin';
$user_name = $_SESSION['name'] ?? 'Super Admin';
$user_username = $_SESSION['username'] ?? 'admin';

// 1. Fetch Secret Key (Trimmed to prevent hidden space issues)
$stmt = $conn->prepare("SELECT secret_key FROM department_secrets WHERE (department=? OR department=?) AND is_active=1 LIMIT 1");
$dept_alt = str_replace(' ', '', $dept); // Handle 'CORE 2' vs 'CORE2'
$stmt->bind_param("ss", $dept, $dept_alt);
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();

if (!$res) {
    die("SSO Error: Secret key not found for department: $dept");
}

$secret = trim($res['secret_key']);

// 2. Prepare Payload
$payload = [
    "user_id" => $user_id,
    "username" => $user_username,
    "email" => $user_email,
    "name" => $user_name,
    "role" => $user_role,
    "dept" => $dept,
    "exp" => time() + 300 // 5 minutes
];

// 3. Create Signature (Exactly as the receiver expects)
$payloadJson = json_encode($payload, JSON_UNESCAPED_SLASHES);
$signature = hash_hmac("sha256", $payloadJson, $secret);

// 4. Encode Token
$tokenData = [
    "payload" => $payloadJson,
    "signature" => $signature
];
$token = base64_encode(json_encode($tokenData));

// 5. Define Target URLs
$sso_urls = [
    'HR1' => 'https://hr1.atierahotelandrestaurant.com/hr1/sso-login.php',
    'HR2' => 'https://hr2.atierahotelandrestaurant.com/hr2_competency/sso-login.php',
    'HR3' => 'https://hr3.atierahotelandrestaurant.com/hr3/sso-login.php',
    'HR4' => 'https://hr4.atierahotelandrestaurant.com/hr4/sso-login.php',
    'CORE1' => 'https://core1.atierahotelandrestaurant.com/core1/sso-login.php',
    'CORE2' => 'https://core2.atierahotelandrestaurant.com/core2/sso_login.php',
    'LOG1' => 'https://logistics1.atierahotelandrestaurant.com/log1/sso-login.php',
    'LOG2' => 'https://logistics2.atierahotelandrestaurant.com/logistics2/sso-login.php',
    'FIN1' => 'https://financial.atierahotelandrestaurant.com/sso-login.php',
];

$target_url = $sso_urls[strtoupper($dept_alt)] ?? "https://" . strtolower($dept_alt) . ".atierahotelandrestaurant.com/sso-login.php";

// Redirect
header("Location: $target_url?token=" . urlencode($token));
exit;
?>