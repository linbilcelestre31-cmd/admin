<?php
session_start();

// Security check: Only Super Admin can access this entry point
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    header('Location: ../auth/login.php');
    exit;
}

require_once __DIR__ . '/../../db/db.php';
$pdo = get_pdo();

// Fetch the API Key for the current Super Admin to use in the bypass
$api_key = '';

// Attempt 1: Check if it's already in the session
if (isset($_SESSION['api_key']) && !empty($_SESSION['api_key'])) {
    $api_key = $_SESSION['api_key'];
} else {
    // Attempt 2: Fetch from DB using the session ID
    $stmt = $pdo->prepare("SELECT api_key FROM SuperAdminLogin_tb WHERE id = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([$_SESSION['user_id']]);
    $admin = $stmt->fetch();

    if ($admin && !empty($admin['api_key'])) {
        $api_key = $admin['api_key'];
    } else {
        // Attempt 3: Fallback - Fetch the first available active Super Admin key
        // This is a safety measure for the Super Admin portal entry points
        $stmt = $pdo->query("SELECT api_key, id FROM SuperAdminLogin_tb WHERE is_active = 1 AND role = 'super_admin' LIMIT 1");
        $fallback = $stmt->fetch();

        if ($fallback) {
            if (empty($fallback['api_key'])) {
                // If key is empty, generate it on the fly (Self-healing)
                $new_key = bin2hex(random_bytes(32));
                $pdo->prepare("UPDATE SuperAdminLogin_tb SET api_key = ? WHERE id = ?")->execute([$new_key, $fallback['id']]);
                $api_key = $new_key;
            } else {
                $api_key = $fallback['api_key'];
            }
        }
    }
}

if (empty($api_key)) {
    die("Error: Super Admin API Key not found. Please log in again or contact the system administrator.");
}

// Redirect to the main Legal Management module with the bypass parameters
// This will trigger the 'isSuperAdmin' logic we added to Modules/legalmanagement.php
$target_url = "../../Modules/legalmanagement.php?super_admin_session=true&bypass_key=" . urlencode($api_key);

header("Location: " . $target_url);
exit;
