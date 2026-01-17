<?php
/**
 * SUPER ADMIN BYPASS INTEGRATION TEMPLATE
 * 
 * Instructions for other departments (HR1, HR2, HR3, etc.):
 * 1. Add the table `SuperAdminLogin_tb` to your existing database.
 * 2. This file should be included in your login or dashboard logic to check for the bypass key.
 */

require_once __DIR__ . '/../db/db.php'; // Path to your database connection

/**
 * Function to handle Super Admin Bypass
 * Place this at the top of your login page or a dedicated bypass handler.
 */
function handleSuperAdminBypass()
{
    if (isset($_GET['bypass_key']) && isset($_GET['super_admin_session'])) {
        $pdo = get_pdo(); // Assuming this returns your PDO connection
        $bypass_key = $_GET['bypass_key'];

        // STEP: Check if this API key exists in your local SuperAdminLogin_tb
        // The Super Admin will have "pushed" or synchronized this key to your DB.
        try {
            $stmt = $pdo->prepare("SELECT * FROM `SuperAdminLogin_tb` WHERE api_key = ? AND is_active = 1 LIMIT 1");
            $stmt->execute([$bypass_key]);
            $sa_user = $stmt->fetch();

            if ($sa_user) {
                // BYPASS SUCCESS: Initialize your system's session
                session_start();
                $_SESSION['user_id'] = 'SUPER_ADMIN_VIEW';
                $_SESSION['role'] = 'super_admin';
                $_SESSION['full_name'] = $sa_user['full_name'];

                // Redirect to your main dashboard
                header('Location: ../dashboard.php');
                exit;
            } else {
                die("Security Error: Invalid Super Admin Bypass Key.");
            }
        } catch (PDOException $e) {
            // Handle table not found or other DB errors
            die("Integration Error: SuperAdminLogin_tb not found in your database.");
        }
    }
}

// Example usage:
// handleSuperAdminBypass();


/* SQL TO RUN ON OTHER DEPARTMENT DATABASES:

CREATE TABLE IF NOT EXISTS `SuperAdminLogin_tb` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `username` varchar(50) NOT NULL,
    `full_name` varchar(100) NOT NULL,
    `api_key` varchar(255) NOT NULL,
    `is_active` tinyint(1) DEFAULT 1,
    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`id`),
    UNIQUE KEY `api_key` (`api_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

*/
?>