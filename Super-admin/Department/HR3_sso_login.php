<?php
/**
 * REFINED SSO RECEIVER for HR3 - Version 6.0
 */
session_start();

// 1. Connection logic
if (file_exists("config.php")) {
    require_once "config.php";
} elseif (file_exists("connections.php")) {
    require_once "connections.php";
} else {
    $conn = new mysqli("localhost", "root", "", "hr3_db");
}

if (!isset($_GET['token']))
    die("Token missing");

// 2. Decode & Parse
$data = json_decode(base64_decode($_GET['token']), true);
if (!$data || !isset($data['payload'], $data['signature'])) {
    die("Invalid token structure");
}

$signature = $data['signature'];
$payloadJson = $data['payload'];
$payload = json_decode($payloadJson, true);

// 3. Secret Handshake Logic
$stmt = $conn->prepare("SELECT secret_key FROM department_secrets WHERE department='HR3' AND is_active=1 LIMIT 1");
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();
$secret = ($res) ? trim($res['secret_key']) : "hr3_secret_key_2026";

$is_valid = false;
if (hash_equals(hash_hmac("sha256", $payloadJson, $secret), $signature)) {
    $is_valid = true;
} elseif (hash_equals(hash_hmac("sha256", $payloadJson, hash('sha256', $secret)), $signature)) {
    $is_valid = true;
}

if (!$is_valid)
    die("Invalid signature. Security handshake failed for HR3.");

// 4. Validation & User Registry
if ($payload['exp'] < time())
    die("Token expired");

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

// 5. Success
$_SESSION['user_id'] = $user['id'];
$_SESSION['username'] = $user['username'];
$_SESSION['role'] = $user['role'];

header("Location: ../dashboard.php");
exit;
?>