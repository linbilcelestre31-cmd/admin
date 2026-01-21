<?php
require "/db.php";
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
    // payload came as array (your current case)
    $payload = $data['payload'];
    $payloadJson = json_encode($payload, JSON_UNESCAPED_SLASHES);
} elseif (is_string($data['payload'])) {
    // payload came as JSON string (future-safe)
    $payloadJson = $data['payload'];
    $payload = json_decode($payloadJson, true);
} else {
    die("Invalid payload format");
}

if (!$payload)
    die("Invalid payload");

// fetch HR1 secret
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
if (!hash_equals($check, $signature)) {
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

// auto login
$_SESSION['core2_user'] = [
    "email" => $payload['email'],
    "role" => $payload['role']
];

header("Location: ../admin/dashboard.php");
exit;