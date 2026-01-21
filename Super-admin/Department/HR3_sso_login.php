<?php
/**
 * SSO LOGIN RECEIVER for HR3
 */

require "connections.php";
session_start();

if (!isset($_GET['token']))
    die("Token missing");

// decode token
$decoded = base64_decode($_GET['token']);
if (!$decoded)
    die("Invalid token");

$data = json_decode($decoded, true);
if (!$data || !isset($data['payload'], $data['signature'])) {
    die("Invalid token structure");
}

$signature = $data['signature'];

/**
 * NORMALIZE PAYLOAD
 */
if (is_array($data['payload'])) {
    $payload = $data['payload'];
    $payloadJson = json_encode($payload, JSON_UNESCAPED_SLASHES);
} elseif (is_string($data['payload'])) {
    $payloadJson = $data['payload'];
    $payload = json_decode($payloadJson, true);
} else {
    die("Invalid payload format");
}

if (!$payload)
    die("Invalid payload");

// fetch HR3 secret
$stmt = $conn->prepare("
    SELECT secret_key
    FROM department_secrets
    WHERE department='HR3' AND is_active=1
    ORDER BY id DESC LIMIT 1
");
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();

if (!$res)
    die("Secret not found");
$secret = $res['secret_key'];

// verify signature
$check = hash_hmac("sha256", $payloadJson, $secret);
$check_hashed_secret = hash_hmac("sha256", $payloadJson, hash('sha256', $secret));

if (!hash_equals($check, $signature) && !hash_equals($check_hashed_secret, $signature)) {
    die("Invalid or tampered token");
}

// expiry & dept validation
if ($payload['exp'] < time())
    die("Token expired");
if ($payload['dept'] !== 'HR3')
    die("Invalid department access");

// AUTO LOGIN LOGIC
$_SESSION['user_id'] = $payload['user_id'] ?? 1;
$_SESSION['username'] = $payload['username'] ?? ($payload['email'] ?? 'admin');
$_SESSION['name'] = $payload['name'] ?? 'Administrator';
$_SESSION['email'] = $payload['email'] ?? 'admin@atiera.com';
$_SESSION['role'] = $payload['role'] ?? 'super_admin';

$_SESSION['hr_user'] = [
    "email" => $payload['email'],
    "role" => $payload['role']
];

header("Location: ../../Modules/dashboard.php");
exit;
?>