<?php
/**
 * ROBUST SSO RECEIVER for HR3 - Version 3.0
 */

// 1. Connection
if (file_exists("connections.php")) {
    require "connections.php";
} elseif (file_exists("../connections.php")) {
    require "../connections.php";
}

session_start();

// Favicon fix to prevent 404 in console
echo '<html><head><title>HR3 Authentication</title><link rel="icon" type="image/png" href="https://hr1.atierahotelandrestaurant.com/assets/image/logo2.png"></head><body style="font-family:sans-serif; text-align:center; padding-top:50px;">';

if (!isset($_GET['token'])) {
    die("<h2>Access Denied</h2><p>Please login through the Super Admin Dashboard.</p>");
}

// 2. Decode & Parse
$decoded = base64_decode($_GET['token']);
$data = json_decode($decoded, true);

if (!$data || !isset($data['payload'], $data['signature'])) {
    die("<h2>Security Error</h2><p>Invalid token structure.</p>");
}

$signature = $data['signature'];
$payloadRaw = $data['payload'];

// 3. Normalized Payload
if (is_string($payloadRaw)) {
    $payloadJson = $payloadRaw;
    $payload = json_decode($payloadJson, true);
} else {
    $payload = $payloadRaw;
    $payloadJson = json_encode($payload, JSON_UNESCAPED_SLASHES);
}

// 4. Fetch Secret Key
$dept = $payload['dept'] ?? 'HR3';
$stmt = $conn->prepare("SELECT secret_key FROM department_secrets WHERE department=? AND is_active=1 LIMIT 1");
$stmt->bind_param("s", $dept);
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();
$secret = ($res) ? $res['secret_key'] : strtolower($dept) . '_secret_key_2026';

// 5. SUPER HANDSHAKE
$is_valid = false;
$check1 = hash_hmac("sha256", $payloadJson, $secret);
$check2 = hash_hmac("sha256", trim($payloadJson), $secret);
$check3 = hash_hmac("sha256", $payloadJson, hash('sha256', $secret));

if (hash_equals($check1, $signature) || hash_equals($check2, $signature) || hash_equals($check3, $signature)) {
    $is_valid = true;
}

if (!$is_valid) {
    die("<h2>Validation Failed</h2><p>Invalid or tampered token.</p>");
}

// 6. Check Expiry
if ($payload['exp'] < time()) {
    die("<h2>Session Expired</h2><p>Please try again.</p>");
}

// 7. Success - Set Session
$_SESSION['user_id'] = $payload['user_id'] ?? 1;
$_SESSION['email'] = $payload['email'];
$_SESSION['name'] = $payload['name'];
$_SESSION['role'] = $payload['role'];
$_SESSION['hr_user'] = $payload; // HR Context

// 8. Redirect
$redirect_path = file_exists("../../Modules/dashboard.php") ? "../../Modules/dashboard.php" : "../Modules/dashboard.php";
header("Location: " . $redirect_path);
exit;
?>
</body>

</html>