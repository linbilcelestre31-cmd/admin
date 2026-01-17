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
$stmt = $pdo->prepare("SELECT api_key FROM SuperAdminLogin_tb WHERE id = ? AND is_active = 1");
$stmt->execute([$_SESSION['user_id']]);
$admin = $stmt->fetch();

if (!$admin || empty($admin['api_key'])) {
    die("Error: Super Admin API Key not found. Please contact the system administrator.");
}

$api_key = $admin['api_key'];

// Redirect to the main Legal Management module with the bypass parameters
// This will trigger the 'isSuperAdmin' logic we added to Modules/legalmanagement.php
$target_url = "../../Modules/legalmanagement.php?super_admin_session=true&bypass_key=" . urlencode($api_key);

header("Location: " . $target_url);
exit;
