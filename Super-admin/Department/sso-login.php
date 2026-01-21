<?php
/**
 * SSO LOGIN RECEIVER for CORE2
 */

// 1. Connection fix: Use relative path instead of absolute /db.php
require "connections.php";
session_start();

// Optional: Add a favicon link for when the script stops (die statements)
echo '<html><head><link rel="icon" type="image/x-icon" href="../../assets/image/logo2.png"></head><body>';

if (!isset($_GET['token']))
    die("<div style='text-align:center; padding:50px; font-family:sans-serif;'><h2>Token missing</h2><p>Please access via Administrative Dashboard.</p></div>");

// decode token
$decoded = base64_decode($_GET['token']);
if (!$decoded)
    die("Invalid token format");

$data = json_decode($decoded, true);
if (!$data || !isset($data['payload'], $data['signature'])) {
    die("Invalid token structure");
}

$signature = $data['signature'];

/**
 * NORMALIZE PAYLOAD
 * Accept both array and JSON-string payloads
 */
if (is_string($data['payload'])) {
    $payloadJson = $data['payload'];
    $payload = json_decode($payloadJson, true);
} else {
    $payload = $data['payload'];
    $payloadJson = json_encode($payload, JSON_UNESCAPED_SLASHES);
}

if (!$payload)
    die("Invalid payload content");

// 2. Fetch Secret Key (Resilient fetch)
$dept = $payload['dept'] ?? 'CORE2';
$stmt = $conn->prepare("SELECT secret_key FROM department_secrets WHERE department=? AND is_active=1 LIMIT 1");
$stmt->bind_param("s", $dept);
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();

if (!$res)
    die("Secret key not found for $dept in database.");

$secret = $res['secret_key'];

// 3. Verify Signature (Dual handshake check)
$check = hash_hmac("sha256", $payloadJson, $secret);
$check_plain = hash_hmac("sha256", $payloadJson, hash('sha256', $secret));

if (!hash_equals($check, $signature) && !hash_equals($check_plain, $signature)) {
    die("Invalid or tampered token. Handshake failed.");
}

// 4. Expiry & Department Validation
if ($payload['exp'] < time()) {
    die("Login token expired. Please try again.");
}

if ($payload['dept'] !== 'CORE2' && $payload['dept'] !== 'CORE 2') {
    die("Invalid department access: " . htmlspecialchars($payload['dept']));
}

// 5. Auto Login Logic - Fetch full user details if possible
$email = $payload['email'];
$stmt = $conn->prepare("SELECT id, username, full_name, role FROM users WHERE email = ? LIMIT 1");
$stmt->bind_param("s", $email);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if ($user) {
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['name'] = $user['full_name'];
    $_SESSION['email'] = $email;
    $_SESSION['role'] = $user['role'];
} else {
    // Fallback for Super Admin
    $_SESSION['user_id'] = 1;
    $_SESSION['username'] = 'admin';
    $_SESSION['role'] = 'super_admin';
    $_SESSION['email'] = $email;
    $_SESSION['name'] = 'Administrator (SSO)';
}

$_SESSION['core2_user'] = [
    "email" => $email,
    "role" => $payload['role'] ?? 'super_admin'
];

// Target redirection (Ensure this points to the correct local dashboard)
header("Location: ../../Modules/dashboard.php");
exit;
?>
</body></html>