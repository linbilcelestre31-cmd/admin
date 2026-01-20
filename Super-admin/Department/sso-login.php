<?php
/**
 * SSO LOGIN RECEIVER (TEMPLATE)
 * Copy this file to your module (e.g., HR3) and ensure the DB settings are correct.
 */

require "connections.php"; // Siguraduhin na ang connections.php ay tama ang settings sa db
session_start();

if (!isset($_GET['token']))
    die("Token missing. Please access this system via the Administrative Dashboard.");

// 1. Decode Token
$decoded = base64_decode($_GET['token']);
if (!$decoded)
    die("Invalid token format.");

$data = json_decode($decoded, true);
if (!$data || !isset($data['payload'], $data['signature'])) {
    die("Invalid token structure.");
}

$signature = $data['signature'];
$payload = $data['payload'];
$payloadJson = json_encode($payload, JSON_UNESCAPED_SLASHES);

// 2. Fetch Secret Key (Dito kukunin ang secret na 'hr3_secret_key_2026')
$dept = $payload['dept'] ?? 'HR3';
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
    die("Secret key not found for $dept in database.");

$secret = $res['secret_key'];

// 3. Verify Signature (Dual Check: Plain & Hash)
$check = hash_hmac("sha256", $payloadJson, $secret);
$check_plain = hash_hmac("sha256", $payloadJson, hash('sha256', $secret));

if (!hash_equals($check, $signature) && !hash_equals($check_plain, $signature)) {
    die("Invalid or tampered token. Security handshake failed.");
}

// 4. Expiry & Verification
if ($payload['exp'] < time()) {
    die("Login token expired. Please try again from the main dashboard.");
}

// 5. AUTO LOGIN LOGIC (Session Match)
/**
 * Dito niyo po imi-match ang session variables sa main login logic niyo.
 */
$email = $payload['email'];
$stmt = $conn->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
$stmt->bind_param("s", $email);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if ($user) {
    // Set system session
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['name'] = $user['full_name'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['role'] = $user['role'];
} else {
    // Fallback for Super Admin
    $_SESSION['user_id'] = 1;
    $_SESSION['username'] = 'admin';
    $_SESSION['role'] = 'super_admin';
    $_SESSION['email'] = $payload['email'];
    $_SESSION['name'] = 'Administrator';
}

// Extra session for module-specific check
$_SESSION['hr_user'] = [
    "email" => $payload['email'],
    "role" => $payload['role']
];

header("Location: dashboard.php");
exit;
?>