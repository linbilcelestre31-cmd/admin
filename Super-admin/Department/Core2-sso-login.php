<?php
/**
 * SSO LOGIN RECEIVER for CORE2
 * Updated to match user template and login logic
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
 * Accept both array and JSON-string payloads
 */
if (is_array($data['payload'])) {
    // payload came as array
    $payload = $data['payload'];
    $payloadJson = json_encode($payload, JSON_UNESCAPED_SLASHES);
} elseif (is_string($data['payload'])) {
    // payload came as JSON string
    $payloadJson = $data['payload'];
    $payload = json_decode($payloadJson, true);
} else {
    die("Invalid payload format");
}

if (!$payload)
    die("Invalid payload");

// fetch CORE2 secret
$stmt = $conn->prepare("
    SELECT secret_key
    FROM department_secrets
    WHERE department='CORE2' AND is_active=1
    ORDER BY id DESC LIMIT 1
");
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();

if (!$res)
    die("Secret not found");

$secret = $res['secret_key'];

// verify signature (CRITICAL)
$check = hash_hmac("sha256", $payloadJson, $secret);
// Robust check: also try hashed secret match in case DB stores plain but signed with hash (or vice-versa)
$check_hashed_secret = hash_hmac("sha256", $payloadJson, hash('sha256', $secret));

if (!hash_equals($check, $signature) && !hash_equals($check_hashed_secret, $signature)) {
    die("Invalid or tampered token");
}

// expiry check
if ($payload['exp'] < time()) {
    die("Token expired");
}

// department validation
if ($payload['dept'] !== 'CORE2') {
    die("Invalid department access");
}

// AUTO LOGIN LOGIC - Sync with your main login.php session variables
$_SESSION['user_id'] = $payload['user_id'] ?? 1;
$_SESSION['username'] = $payload['username'] ?? ($payload['email'] ?? 'admin');
$_SESSION['name'] = $payload['name'] ?? 'Administrator';
$_SESSION['email'] = $payload['email'] ?? 'admin@atiera.com';
$_SESSION['role'] = $payload['role'] ?? 'super_admin';

// Module-specific session
$_SESSION['core2_user'] = [
    "email" => $payload['email'],
    "role" => $payload['role']
];

// Redirect to dashboard
header("Location: ../../Modules/dashboard.php");
exit;
?>