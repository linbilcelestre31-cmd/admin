<?php
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

// fetch Correct secret (User's snippet said HR1, but we should probably handle both or use what's provided)
$stmt = $conn->prepare("
    SELECT secret_key 
    FROM department_secrets 
    WHERE department=? AND is_active=1 
    ORDER BY id DESC LIMIT 1
");
$dept = $payload['dept'] ?? 'HR1';
$stmt->bind_param("s", $dept);
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();

if (!$res)
    die("Secret not found for department: " . htmlspecialchars($dept));

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

// auto login to Super Admin session if requested or based on payload
$_SESSION['user_id'] = 1; // Assuming 1 is the Super Admin ID or similar
$_SESSION['email'] = $payload['email'];
$_SESSION['role'] = 'super_admin'; // Force Super Admin as requested
$_SESSION['full_name'] = 'Super Admin (SSO)';

// Set the same session variables used in other modules
$_SESSION['hr_user'] = [
    "email" => $payload['email'],
    "role" => $payload['role']
];

header("Location: Super-admin/Dashboard.php");
exit;
?>