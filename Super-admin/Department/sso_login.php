<?php
/**
 * ROBUST UNIVERSAL SSO RECEIVER - Version 5.0
 * Updated with debug mode and dynamic department fetching
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// 1. Database Connection Logic
if (file_exists("connections.php")) {
    require "connections.php";
} elseif (file_exists("../connections.php")) {
    require "../connections.php";
} elseif (file_exists("config.php")) {
    require "config.php";
} else {
    // Fallback if no connection file is found - adjust these to your actual DB
    $conn = new mysqli("localhost", "root", "", "hr3_db");
    if ($conn->connect_error) {
        die("Database connection failed: " . $conn->connect_error);
    }
}

session_start();

if (!isset($_GET['token'])) {
    die("Token missing. Please access via the Super Admin Dashboard.");
}

// 2. Decode & Parse Token
$decoded = base64_decode($_GET['token']);
$data = json_decode($decoded, true);
if (!$data || !isset($data['payload'], $data['signature'])) {
    die("Invalid token format or structure.");
}

$signature = $data['signature'];
$payloadRaw = $data['payload'];

// Normalize Payload (String vs Array)
if (is_string($payloadRaw)) {
    $payloadJson = $payloadRaw;
    $payload = json_decode($payloadJson, true);
} else {
    $payload = $payloadRaw;
    $payloadJson = json_encode($payload, JSON_UNESCAPED_SLASHES);
}

if (!$payload)
    die("Empty or invalid payload.");

// 3. Dynamic Secret Key Fetching
$dept = $payload['dept'] ?? '';
if (empty($dept))
    die("Department missing in token payload.");

// We check for exact match or space-removed match (e.g. 'CORE 2' -> 'CORE2')
$dept_alt = str_replace(' ', '', $dept);

$stmt = $conn->prepare("SELECT secret_key FROM department_secrets WHERE (department=? OR department=?) AND is_active=1 LIMIT 1");
$stmt->bind_param("ss", $dept, $dept_alt);
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();

if (!$res) {
    die("Security Error: Secret key for department '$dept' not found in database.");
}

$secret = trim($res['secret_key']);

/**
 * 4. THE SUPER HANDSHAKE (Signature Verification)
 * We check multiple possibilities to ensure the login works.
 */
$is_valid = false;

// Check A: Standard HMAC Match
if (hash_equals(hash_hmac("sha256", $payloadJson, $secret), $signature)) {
    $is_valid = true;
}
// Check B: Hashed Secret Match (Gateway signed with SHA256(secret))
elseif (hash_equals(hash_hmac("sha256", $payloadJson, hash('sha256', $secret)), $signature)) {
    $is_valid = true;
}
// Check C: Trimmed Payload Match
elseif (hash_equals(hash_hmac("sha256", trim($payloadJson), $secret), $signature)) {
    $is_valid = true;
}
// Check D: Common Hashing Fallbacks
else {
    foreach (['sha256', 'sha512', 'md5'] as $algo) {
        if (hash_equals(hash_hmac("sha256", $payloadJson, hash($algo, $secret)), $signature)) {
            $is_valid = true;
            break;
        }
    }
}

if (!$is_valid) {
    // Debug info (optional, remove in production if desired)
    die("Invalid or tampered token. Security handshake failed for $dept.");
}

// 5. Expiry Check
if ($payload['exp'] < time()) {
    die("Token expired. Please try again from the dashboard.");
}

// 6. AUTO LOGIN LOGIC
$_SESSION['user_id'] = $payload['user_id'] ?? 1;
$_SESSION['username'] = $payload['username'] ?? 'admin';
$_SESSION['name'] = $payload['name'] ?? 'Administrator';
$_SESSION['email'] = $payload['email'];
$_SESSION['role'] = $payload['role'];

// Module-specific session storage
$module_key = strtolower($dept_alt) . '_user';
$_SESSION[$module_key] = $payload;

// 7. Successful Redirection
$redirect = file_exists("../../Modules/dashboard.php") ? "../../Modules/dashboard.php" : "../Modules/dashboard.php";
header("Location: $redirect");
exit;
?>