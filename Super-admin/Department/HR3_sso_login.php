<?php
/**
 * UNIVERSAL SSO LOGIN RECEIVER
 * Copy this file to your module's sso-login.php
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

/**
 * 2. PAYLOAD NORMALIZATION
 * We handle both String and Array payloads for maximum compatibility.
 */
if (is_string($data['payload'])) {
    // If payload is a string (new format), use it directly for signature check
    $payloadJson = $data['payload'];
    $payload = json_decode($payloadJson, true);
} else {
    // If payload is an array (old format), re-encode it for signature check
    $payload = $data['payload'];
    $payloadJson = json_encode($payload, JSON_UNESCAPED_SLASHES);
}

if (!$payload)
    die("Empty or invalid payload.");

// 3. Fetch Secret Key
$dept = $payload['dept'] ?? 'CORE2'; // Default to CORE2 if not set
$stmt = $conn->prepare("SELECT secret_key FROM department_secrets WHERE department=? AND is_active=1 LIMIT 1");
$stmt->bind_param("s", $dept);
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();

if (!$res)
    die("SSO Error: Secret key for $dept not found in database.");
$secret = $res['secret_key'];

/**
 * 4. SIGNATURE VERIFICATION
 * We check multiple possibilities to ensure the handshake.
 */
$verified = false;

// Case A: Exact Match (Standard)
if (hash_equals(hash_hmac("sha256", $payloadJson, $secret), $signature)) {
    $verified = true;
}
// Case B: Hashed Secret Match (Fallback for some implementations)
elseif (hash_equals(hash_hmac("sha256", $payloadJson, hash('sha256', $secret)), $signature)) {
    $verified = true;
}
// Case C: Trimmed Plain Match
elseif (hash_equals(hash_hmac("sha256", trim($payloadJson), $secret), $signature)) {
    $verified = true;
}

if (!$verified) {
    die("Invalid or tampered token. Security handshake failed.");
}

// 5. Expiry Check
if ($payload['exp'] < time()) {
    die("Login token expired. Please try again from the main dashboard.");
}

/**
 * 6. AUTO LOGIN LOGIC
 * Sync the local session with the user from the payload.
 */
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
    // Fallback for Super Admin if not in local DB
    $_SESSION['user_id'] = 1;
    $_SESSION['username'] = 'admin';
    $_SESSION['role'] = 'super_admin';
    $_SESSION['email'] = $email;
    $_SESSION['name'] = 'Administrator (SSO)';
}

// Set module-specific context
$_SESSION['sso_user'] = $payload;

// Redirect to module dashboard
header("Location: ../../Modules/dashboard.php");
exit;
?>