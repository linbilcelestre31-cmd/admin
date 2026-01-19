<?php
session_start();
require "../connections.php"; // Gamitin ang mysqli connection na ginawa natin

if (!isset($_SESSION['user_id']) || !isset($_GET['dept'])) {
    die("Unauthorized access or missing department.");
}

$dept = $_GET['dept'];
$user_email = $_SESSION['email'] ?? 'admin@atiera.com';
$user_role = $_SESSION['role'] ?? 'super_admin';

// 1. Kunin ang secret key para sa department
$stmt = $conn->prepare("SELECT secret_key FROM department_secrets WHERE department=? AND is_active=1 ORDER BY id DESC LIMIT 1");
$stmt->bind_param("s", $dept);
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();

if (!$res)
    die("SSO Error: No secret key found for $dept. Please run update_secrets.php first.");

$secret = $res['secret_key'];

// 2. I-prepare ang Payload (Token Data)
$payload = [
    "email" => $user_email,
    "role" => $user_role,
    "dept" => $dept,
    "exp" => time() + 300 // Valid for 5 minutes
];

$payloadJson = json_encode($payload, JSON_UNESCAPED_SLASHES);

// 3. I-sign ang Payload gamit ang HMAC-SHA256
$signature = hash_hmac("sha256", $payloadJson, $secret);

// 4. I-encode ang lahat sa isang Token string
$tokenData = [
    "payload" => $payload,
    "signature" => $signature
];
$token = base64_encode(json_encode($tokenData));

// 5. I-define ang Target SSO URLs (Base sa iyong request)
$sso_urls = [
    'HR1' => 'https://hr1.atierahotelandrestaurant.com/hr1/sso-login.php',
    'HR2' => 'https://hr2.atierahotelandrestaurant.com/hr2/sso-login.php',
    'HR3' => 'https://hr3.atierahotelandrestaurant.com/hr3/sso-login.php',
    'HR4' => 'https://hr4.atierahotelandrestaurant.com/hr4/sso-login.php',
    'CORE1' => 'https://core1.atierahotelandrestaurant.com/sso-login.php',
    'CORE2' => 'https://core2.atierahotelandrestaurant.com/sso-login.php'
];

$target_url = $sso_urls[$dept] ?? "https://" . strtolower($dept) . ".atierahotelandrestaurant.com/sso-login.php";

// 6. Redirect to the target system with the token
header("Location: $target_url?token=" . urlencode($token));
exit;
?>