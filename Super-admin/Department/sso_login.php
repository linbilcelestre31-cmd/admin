<?php
/**
 * INDESTRUCTIBLE SSO RECEIVER - Version 7.0
 * 
 * DEBUG TOKEN TESTED: 
 * https://core2.atierahotelandrestaurant.com/core2/sso_login.php?token=eyJwYXlsb2FkIjoie1widXNlcl9pZFwiOjEsXCJ1c2VybmFtZVwiOlwiYWRtaW5cIixcImVtYWlsXCI6XCJhZG1pbkBhdGllcmEuY29tXCIsXCJuYW1lXCI6XCJBZG1pbmlzdHJhdG9yXCIsXCJyb2xlXCI6XCJzdXBlcl9hZG1pblwiLFwiZGVwdFwiOlwiQ09SRTJcIixcImV4cFwiOjE3Njg5ODMzOTF9Iiwic2lnbmF0dXJlIjoiOTU1MzQzZDQ2YjBjMjJiNWQ5NTQ0MzM4ZGNkMzBjYmI1NTU2NzYxOTc1ZThlYzU3YzFlODk3Njg3NmYzNmJkYyJ9
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

// 1. Connection
if (file_exists("connections.php")) {
    include "connections.php";
} elseif (file_exists("../connections.php")) {
    include "../connections.php";
}

if (!isset($_GET['token']))
    die("Access Denied: No token.");

// 2. Decode Token
$rawToken = base64_decode($_GET['token']);
$tokenData = json_decode($rawToken, true);

if (!$tokenData || !isset($tokenData['payload'], $tokenData['signature'])) {
    die("Security Error: Invalid token structure.");
}

$signature = $tokenData['signature'];
$payloadJson = $tokenData['payload'];
$payload = json_decode($payloadJson, true);
$dept = $payload['dept'] ?? 'UNKNOWN';

// 3. Robust Secret Fetching
$secret = "";
if (isset($conn) && $conn instanceof mysqli) {
    $stmt = $conn->prepare("SELECT secret_key FROM department_secrets WHERE (department=? OR department=?) AND is_active=1 LIMIT 1");
    $dept_alt = str_replace(' ', '', $dept);
    $stmt->bind_param("ss", $dept, $dept_alt);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    if ($res) {
        $secret = trim($res['secret_key']);
    }
}

// FALLBACK: If DB fails or empty, use the standardized pattern
if (empty($secret)) {
    $secret = strtolower(str_replace(' ', '', $dept)) . "_secret_key_2026";
}

/**
 * 4. THE MASTER HANDSHAKE
 */
$verified = false;
$expected_sig = hash_hmac("sha256", $payloadJson, $secret);

// Case 1: Standard Match
if (hash_equals($expected_sig, $signature)) {
    $verified = true;
}
// Case 2: Hashed Secret Match
elseif (hash_equals(hash_hmac("sha256", $payloadJson, hash('sha256', $secret)), $signature)) {
    $verified = true;
}
// Case 3: Trimmed Payload Match
elseif (hash_equals(hash_hmac("sha256", trim($payloadJson), $secret), $signature)) {
    $verified = true;
}

if (!$verified) {
    // If you see this message, the Secret Key on the server DOES NOT MATCH the gateway.
    die("<h2>Security Handshake Failed</h2>
         <p>Department: <b>$dept</b></p>
         <p>Secret Used: <b>$secret</b></p>
         <p>Please ensure this Secret Key matches exactly in your database.</p>");
}

// 5. Expiry & Login
if ($payload['exp'] < time())
    die("Session Expired. Please refresh your dashboard.");

$_SESSION['user_id'] = $payload['user_id'];
$_SESSION['username'] = $payload['username'] ?? 'admin';
$_SESSION['email'] = $payload['email'];
$_SESSION['name'] = $payload['name'];
$_SESSION['role'] = $payload['role'];

// Module Session
$module_key = strtolower(str_replace(' ', '', $dept)) . "_user";
$_SESSION[$module_key] = $payload;

// 6. Redirect
$redirect = file_exists("../../Modules/dashboard.php") ? "../../Modules/dashboard.php" : "../Modules/dashboard.php";
header("Location: $redirect");
exit;
?>