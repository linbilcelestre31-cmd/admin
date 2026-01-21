<?php
/**
 * SSO LOGIN RECEIVER for HR3
 */

require "connections.php";
session_start();

if (!isset($_GET['token']))
    die("Token missing. Please access via the Administrative Dashboard.");

// 1. Decode & Parse Token
$decoded = base64_decode($_GET['token']);
if (!$decoded)
    die("Invalid token format.");

$data = json_decode($decoded, true);
if (!$data || !isset($data['payload'], $data['signature'])) {
    die("Invalid token structure.");
}

$signature = $data['signature'];

// 2. Normalize Payload
if (is_string($data['payload'])) {
    $payloadJson = $data['payload'];
    $payload = json_decode($payloadJson, true);
} else {
    $payload = $data['payload'];
    $payloadJson = json_encode($payload, JSON_UNESCAPED_SLASHES);
}

if (!$payload)
    die("Empty or invalid payload.");

// 3. Fetch Secret Key
$dept = $payload['dept'] ?? 'HR3';
$stmt = $conn->prepare("SELECT secret_key FROM department_secrets WHERE department=? AND is_active=1 LIMIT 1");
$stmt->bind_param("s", $dept);
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();

if (!$res)
    die("Secret key not found for $dept in database.");
$secret = $res['secret_key'];

// 4. Verify Signature
$check = hash_hmac("sha256", $payloadJson, $secret);
$check_plain = hash_hmac("sha256", $payloadJson, hash('sha256', $secret));

if (!hash_equals($check, $signature) && !hash_equals($check_plain, $signature)) {
    die("Invalid or tampered token.");
}

// 5. Expiry Check
if ($payload['exp'] < time()) {
    die("Login token expired. Please try again.");
}

// 6. AUTO LOGIN LOGIC
$email = $payload['email'];
$stmt = $conn->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
$stmt->bind_param("s", $email);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if ($user) {
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['name'] = $user['full_name'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['role'] = $user['role'];
} else {
    $_SESSION['user_id'] = 1;
    $_SESSION['username'] = 'admin';
    $_SESSION['role'] = 'super_admin';
    $_SESSION['email'] = $email;
    $_SESSION['name'] = 'Administrator';
}

$_SESSION['hr_user'] = [
    "email" => $email,
    "role" => $payload['role'] ?? 'super_admin'
];

header("Location: ../../Modules/dashboard.php");
exit;
?>