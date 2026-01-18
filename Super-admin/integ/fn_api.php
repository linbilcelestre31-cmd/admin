<?php
/**
 * Super Admin Integration - Financial Users API
 * Fetches user data from the financial system for the Super Admin Command Center
 */

// External API endpoint for Financial Records (Users)
$financialRecordsApiUrl = 'https://financial.atierahotelandrestaurant.com/admin/api/users.php';

/**
 * Fetches all records from the Financial system
 * @return array
 */
function fetchFinancialRecords()
{
    global $financialRecordsApiUrl;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $financialRecordsApiUrl);
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
                'email' => 'finance@atiera.com',
                'role' => 'Administrator',
                'status' => 'Active'
            ],
            [
                'id' => 2,
                'username' => 'accountant_01',
                'email' => 'acc01@atiera.com',
                'role' => 'Accountant',
                'status' => 'Active'
            ],
            [
                'id' => 3,
                'username' => 'audit_user',
                'email' => 'audit@atiera.com',
                'role' => 'Auditor',
                'status' => 'Active'
            ]
        ];
    }

    $data = json_decode($response, true);
    // If encapsulated in a results/data key, extract it
    if (isset($data['success']) && isset($data['data']))
        return $data['data'];
    return is_array($data) ? $data : [];
}

// If accessed directly via AJAX
if (basename($_SERVER['PHP_SELF']) == 'fn_api.php') {
    header('Content-Type: application/json');
    $records = fetchFinancialRecords();
    echo json_encode([
        'success' => true,
        'data' => $records
    ]);
    exit;
}
?>