<?php
/**
 * REFINED SSO RECEIVER for HR1 - Version 6.0
 */
session_start();

// 1. Connection logic (Resilient)
if (file_exists("config.php")) {
    require_once "config.php";
} elseif (file_exists("connections.php")) {
    require_once "connections.php";
} else {
    // Fallback if no file is found (Adjust DB name if needed)
    $conn = new mysqli("localhost", "root", "", "");
}

if (!isset($_GET['token']))
    die("Token missing");

// 2. Decode Token
$rawToken = base64_decode($_GET['token']);
$data = json_decode($rawToken, true);

if (!$data || !isset($data['payload'], $data['signature'])) {
    die("Invalid token structure");
}

$signature = $data['signature'];
$payloadRaw = $data['payload'];

// Normalize Payload (handled as string for verification)
if (is_string($payloadRaw)) {
    $payloadJson = $payloadRaw;
    $payload = json_decode($payloadJson, true);
} else {
    $payload = $payloadRaw;
    $payloadJson = json_encode($payload, JSON_UNESCAPED_SLASHES);
}

// 3. Fetch Secret Key (Check 'HR1' and handle fallback)
$stmt = $conn->prepare("SELECT secret_key FROM department_secrets WHERE department='CORE1' AND is_active=1 LIMIT 1");
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();

// Use the key from DB or the standard pattern as fallback
$secret = ($res) ? trim($res['secret_key']) : "CORE1_secret_key_2026";

/**
 * 4. THE SUPER HANDSHAKE (Checks multiple ways to verify)
 */
$is_valid = false;
if (hash_equals(hash_hmac("sha256", $payloadJson, $secret), $signature)) {
    $is_valid = true;
} elseif (hash_equals(hash_hmac("sha256", $payloadJson, hash('sha256', $secret)), $signature)) {
    $is_valid = true;
} elseif (hash_equals(hash_hmac("sha256", trim($payloadJson), $secret), $signature)) {
    $is_valid = true;
}

if (!$is_valid) {
    die("Invalid signature. Security handshake failed.");
}

// 5. Validation
if ($payload['exp'] < time())
    die("Token expired");
if (($payload['dept'] ?? '') !== 'CORE1')
    die("Invalid department");

/**
 * 6. USER SYNC (Create user if not exists)
 */
$email = $payload['email'];
$stmt = $conn->prepare("SELECT id, username, role FROM users WHERE email=? LIMIT 1");
$stmt->bind_param("s", $email);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    $username = $payload['username'] ?? explode('@', $email)[0];
    $role = $payload['role'] ?? 'super_admin';
    $temp_pass = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);

    $ins = $conn->prepare("INSERT INTO users (username, email, password, role, created_at) VALUES (?, ?, ?, ?, NOW())");
    $ins->bind_param("ssss", $username, $email, $temp_pass, $role);
    $ins->execute();

    $user = ['id' => $ins->insert_id, 'username' => $username, 'role' => $role];
}

// 7. Successful Session Match
session_regenerate_id(true);
$_SESSION['user_id'] = $user['id'];
$_SESSION['username'] = $user['username'];
$_SESSION['role'] = $user['role'];
$_SESSION['login_type'] = 'sso';

// 8. Redirect
header("Location: ../dashboard.php");
exit;
?>