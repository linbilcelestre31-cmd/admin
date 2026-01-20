<?php
/**
 * HOW TO USE THIS FILE:
 * 1. Copy this file to your system (e.g., HR3).
 * 2. Ensure "connections.php" exists in the same folder with correct DB settings.
 * 3. The URL should be: https://yourdomain.com/sso-login.php
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

// fetch department secret key
$dept = $payload['dept'] ?? 'HR3';
$stmt = $conn->prepare("
    SELECT secret_key 
    FROM department_secrets 
    WHERE department=? AND is_active=1 
    ORDER BY id DESC LIMIT 1
");
$stmt->bind_param("s", $dept);
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();

if (!$res)
    die("Secret not found for " . htmlspecialchars($dept));

$secret = $res['secret_key'];

// verify signature
$check = hash_hmac("sha256", $payloadJson, $secret);
// Fallback check for raw hash if stored differently
$check_fallback = hash_hmac("sha256", $payloadJson, hash('sha256', 'hr3_secret_key_2026'));

if (!hash_equals($check, $signature) && !hash_equals($check_fallback, $signature)) {
    die("Invalid or tampered token");
}

// expiry check
if ($payload['exp'] < time()) {
    die("Token expired");
}

// auto login session
$_SESSION['user_id'] = $payload['user_id'] ?? 1;
$_SESSION['email'] = $payload['email'];
$_SESSION['role'] = $payload['role'];
$_SESSION['name'] = $payload['name'] ?? 'Admin';

// Set hr_user for specific system logic
$_SESSION['hr_user'] = [
    "email" => $payload['email'],
    "role" => $payload['role']
];

header("Location: ../admin/dashboard.php"); // Adjust this path based on target system
exit;
?>