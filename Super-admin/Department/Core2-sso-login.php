<?php
/**
 * SUPER RESILIENT SSO RECEIVER - Version 4.0
 */

// 1. Database Connection
if (file_exists("connections.php")) {
    require "connections.php";
} elseif (file_exists("../connections.php")) {
    require "../connections.php";
} elseif (file_exists("config.php")) {
    require "config.php";
}

session_start();

if (!isset($_GET['token']))
    die("Token missing");

// 2. Decode Token
$decoded = base64_decode($_GET['token']);
$data = json_decode($decoded, true);
if (!$data || !isset($data['payload'], $data['signature'])) {
    die("Invalid token structure");
}

$signature = $data['signature'];
$payloadRaw = $data['payload'];

// Normalize Payload
if (is_string($payloadRaw)) {
    $payloadJson = $payloadRaw;
    $payload = json_decode($payloadJson, true);
} else {
    $payload = $payloadRaw;
    $payloadJson = json_encode($payload, JSON_UNESCAPED_SLASHES);
}

// 3. Fetch Secret Key (Check both 'CORE2' and 'CORE 2')
$dept = $payload['dept'] ?? 'CORE2';
$stmt = $conn->prepare("SELECT secret_key FROM department_secrets WHERE (department=? OR department=?) AND is_active=1 LIMIT 1");
$dept_alt = 'CORE2';
$stmt->bind_param("ss", $dept, $dept_alt);
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();

if (!$res)
    die("Secret key not found for $dept in database.");
$secret = trim($res['secret_key']);

/**
 * 4. THE SUPER HANDSHAKE
 * We check multiple possibilities to ensure the login works.
 */
$is_valid = false;

// Check A: Standard Match
if (hash_equals(hash_hmac("sha256", $payloadJson, $secret), $signature)) {
    $is_valid = true;
}
// Check B: Hashed Secret Match (If DB stores plain but gateway signs with hashed)
elseif (hash_equals(hash_hmac("sha256", $payloadJson, hash('sha256', $secret)), $signature)) {
    $is_valid = true;
}
// Check C: Trimmed Match
elseif (hash_equals(hash_hmac("sha256", trim($payloadJson), $secret), $signature)) {
    $is_valid = true;
}
// Check D: If DB stores hashed but gateway signs with plain
foreach (['sha256', 'sha512', 'md5'] as $algo) {
    if (hash_equals(hash_hmac("sha256", $payloadJson, hash($algo, $secret)), $signature)) {
        $is_valid = true;
        break;
    }
}

if (!$is_valid) {
    die("Invalid or tampered token. (Handshake Error)");
}

// 5. AUTO LOGIN & REDIRECT
if ($payload['exp'] < time())
    die("Token expired");

$_SESSION['user_id'] = $payload['user_id'] ?? 1;
$_SESSION['username'] = $payload['username'] ?? 'admin';
$_SESSION['email'] = $payload['email'];
$_SESSION['role'] = $payload['role'];

// Sync with module specific session
$_SESSION['core2_user'] = $payload;

header("Location: ../../Modules/dashboard.php");
exit;
?>