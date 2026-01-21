<?php
/**
 * UNIVERSAL SSO LOGIN RECEIVER
 * Use this as a master template for all module sso-login.php files.
 */

// 1. Connection fix
if (file_exists("connections.php")) {
    require "connections.php";
} elseif (file_exists("config.php")) {
    require "config.php";
} else {
    // Fallback or manual config if needed
    $conn = new mysqli("localhost", "root", "", "admin_new");
}

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

// fetch department secret
$dept = $payload['dept'] ?? 'HR1';
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
    die("Secret not found for department: " . $dept);

$secret = $res['secret_key'];

// verify signature (Robust handshake)
$check = hash_hmac("sha256", $payloadJson, $secret);
$check_hashed_secret = hash_hmac("sha256", $payloadJson, hash('sha256', $secret));

if (!hash_equals($check, $signature) && !hash_equals($check_hashed_secret, $signature)) {
    die("Invalid or tampered token");
}

// expiry check
if ($payload['exp'] < time()) {
    die("Token expired");
}

// AUTO LOGIN LOGIC - Synchronized with main login system
$_SESSION['user_id'] = $payload['user_id'] ?? 1;
$_SESSION['username'] = $payload['username'] ?? ($payload['email'] ?? 'admin');
$_SESSION['name'] = $payload['name'] ?? 'Administrator';
$_SESSION['email'] = $payload['email'] ?? 'admin@atiera.com';
$_SESSION['role'] = $payload['role'] ?? 'super_admin';

// Department-specific session context
$session_key = strtolower($dept) . '_user';
$_SESSION[$session_key] = [
    "email" => $payload['email'],
    "role" => $payload['role']
];

// Redirect - adjust path as needed for your module
header("Location: ../../Modules/dashboard.php");
exit;
?>