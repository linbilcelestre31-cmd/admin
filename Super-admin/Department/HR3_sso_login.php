<?php
/**
 * REFINED SSO RECEIVER for HR3 - Version 7.0
 */
session_start();

/**
 * 1. RESILIENT CONNECTION LOGIC
 * This handles the "Access Denied" or "File Not Found" errors gracefully.
 */
$conn = null;

// Try config.php first
if (file_exists("config.php")) {
    @include "config.php";
}

// If still no connection, try connections.php
if (!isset($conn) || $conn->connect_error) {
    if (file_exists("connections.php")) {
        @include "connections.php";
    }
}

// FINAL FALLBACK: If both files fail, try a direct connection
if (!isset($conn) || $conn->connect_error) {
    // Attempting local fallback for troubleshooting
    $conn = new mysqli("localhost", "root", "", "hr3_db");

    if ($conn->connect_error) {
        die("<h2>Database Connection Error</h2>
             <p>The system could not connect to the database. Please check your credentials.</p>
             <p>Error: " . $conn->connect_error . "</p>");
    }
}

if (!isset($_GET['token']))
    die("Token missing");

// 2. Decode Token
$decoded = base64_decode($_GET['token']);
if (!$decoded)
    die("Invalid token encoding");

$data = json_decode($decoded, true);
if (!$data || !isset($data['payload'], $data['signature'])) {
    die("Invalid token structure");
}

$signature = $data['signature'];
$payloadJson = $data['payload'];
$payload = json_decode($payloadJson, true);

if (!$payload)
    die("Invalid payload data");

// 3. Fetch Secret Key
$stmt = $conn->prepare("SELECT secret_key FROM department_secrets WHERE department='HR3' AND is_active=1 ORDER BY id DESC LIMIT 1");
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();

// Handshake secret check
$secret = ($res) ? trim($res['secret_key']) : "hr3_secret_key_2026";

/**
 * 4. THE SUPER HANDSHAKE
 */
$is_valid = false;
if (hash_equals(hash_hmac("sha256", $payloadJson, $secret), $signature)) {
    $is_valid = true;
} elseif (hash_equals(hash_hmac("sha256", $payloadJson, hash('sha256', $secret)), $signature)) {
    $is_valid = true;
}

if (!$is_valid)
    die("Invalid signature. Security handshake failed for HR3.");

// 5. Expiry & Dept Validation
if ($payload['exp'] < time())
    die("Token expired");
if (($payload['dept'] ?? '') !== 'HR3')
    die("Invalid department access");

/**
 * 6. USER SYNC (Same as HR4)
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

// 7. Success - Set Session
$_SESSION['user_id'] = $user['id'];
$_SESSION['username'] = $user['username'];
$_SESSION['role'] = $user['role'];
$_SESSION['login_type'] = 'sso';

// Redirect to dashboard
$redirect_path = file_exists("../../Modules/dashboard.php") ? "../../Modules/dashboard.php" : "../dashboard.php";
header("Location: " . $redirect_path);
exit;
?>