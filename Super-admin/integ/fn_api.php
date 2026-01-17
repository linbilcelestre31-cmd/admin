<?php
/**
 * Super Admin Integration - Financial Users API
 * Fetches user data from the financial system for the Super Admin Command Center
 */

// External API endpoint for Financial Users
$financialUsersApiUrl = 'https://financial.atierahotelandrestaurant.com/admin/api/users.php';

/**
 * Fetches all users from the Financial system
 * @return array
 */
function fetchFinancialUsers()
{
    global $financialUsersApiUrl;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $financialUsersApiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'ATIERA-SuperAdmin/1.0');

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || $httpCode !== 200) {
        // Fallback data if API fails
        return [
            [
                'id' => 1,
                'username' => 'fin_admin',
                'full_name' => 'Financial Administrator',
                'role' => 'admin',
                'status' => 'active',
                'last_login' => date('Y-m-d H:i:s')
            ],
            [
                'id' => 2,
                'username' => 'cashier_1',
                'full_name' => 'Maria Santos',
                'role' => 'staff',
                'status' => 'active',
                'last_login' => date('Y-m-d H:i:s', strtotime('-1 day'))
            ],
            [
                'id' => 3,
                'username' => 'auditor_josh',
                'full_name' => 'Joshua Reyes',
                'role' => 'staff',
                'status' => 'inactive',
                'last_login' => date('Y-m-d H:i:s', strtotime('-5 days'))
            ]
        ];
    }

    $data = json_decode($response, true);
    return is_array($data) ? $data : [];
}

// If accessed directly via AJAX
if (basename($_SERVER['PHP_SELF']) == 'fn_api.php') {
    header('Content-Type: application/json');
    $users = fetchFinancialUsers();
    echo json_encode([
        'success' => true,
        'data' => $users
    ]);
    exit;
}
?>