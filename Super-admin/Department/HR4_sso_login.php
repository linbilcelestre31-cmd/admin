<?php
/**
 * =====================================================
 * START SESSION (EXACT SAME AS auth.php)
 * =====================================================
 */
session_start();

/**
 * =====================================================
 * DB CONNECTION (SAME STYLE AS auth.php)
 * =====================================================
 */
require_once "config.php"; // MUST be same DB file used in auth.php

/**
 * =====================================================
 * TOKEN VALIDATION
 * =====================================================
 */
if (!isset($_GET['token'])) {
    die("Token missing");
}

$decoded = base64_decode($_GET['token'], true);
if ($decoded === false) {
    die("Invalid token");
}

$data = json_decode($decoded, true);
if (!$data || !isset($data['payload'], $data['signature'])) {
    die("Invalid token structure");
}

$signature = $data['signature'];

/**
 * Normalize payload
 */
if (is_array($data['payload'])) {
    $payload = $data['payload'];
    $payloadJson = json_encode($payload, JSON_UNESCAPED_SLASHES);
} else {
    $payloadJson = $data['payload'];
    $payload = json_decode($payloadJson, true);
}

if (!$payload) {
    die("Invalid payload");
}

/**
 * =====================================================
 * FETCH HR4 SECRET KEY
 * =====================================================
 */
$stmt = $conn->prepare("
    SELECT secret_key
    FROM department_secrets
    WHERE department = 'HR4'
      AND is_active = 1
    ORDER BY id DESC
    LIMIT 1
");
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();

if (!$res) {
    die("Secret key not found");
}

$secret = $res['secret_key'];

/**
 * =====================================================
 * VERIFY TOKEN
 * =====================================================
 */
$expected = hash_hmac("sha256", $payloadJson, $secret);
if (!hash_equals($expected, $signature)) {
    die("Invalid signature");
}

if ($payload['exp'] < time()) {
    die("Token expired");
}

if (($payload['dept'] ?? '') !== 'HR4') {
    die("Invalid department");
}

if (empty($payload['email'])) {
    die("Email missing");
}

/**
 * =====================================================
 * USER LOOKUP (SAME users TABLE AS auth.php)
 * =====================================================
 */
$email = $payload['email'];

$stmt = $conn->prepare("
    SELECT id, username, role
    FROM users
    WHERE email = ?
    LIMIT 1
");
$stmt->bind_param("s", $email);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

/**
 * CREATE USER IF NOT EXISTS
 */
if (!$user) {
    $username = $payload['username'] ?? explode('@', $email)[0];
    $role = $payload['role'] ?? 'super_admin';
    $password = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);

    $stmt = $conn->prepare("
        INSERT INTO users (username, email, password, role, created_at)
        VALUES (?, ?, ?, ?, NOW())
    ");
    $stmt->bind_param("ssss", $username, $email, $password, $role);
    $stmt->execute();

    $user = [
        'id' => $stmt->insert_id,
        'username' => $username,
        'role' => $role
    ];
}

/**
 * =====================================================
 * SET SESSION (EXACTLY LIKE auth.php)
 * =====================================================
 */
session_regenerate_id(true);

$_SESSION['user_id'] = $user['id'];
$_SESSION['username'] = $user['username'];
$_SESSION['role'] = $user['role'];

/**
 * OPTIONAL FLAGS
 */
$_SESSION['login_type'] = 'sso';
$_SESSION['sso_department'] = 'HR4';

/**
 * =====================================================
 * REDIRECT TO DASHBOARD
 * =====================================================
 */
header("Location: ../dashboard.php");
exit;
