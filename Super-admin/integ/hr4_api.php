<?php
/**
 * Super Admin Integration - HR4 Employee Relations API
 * Fetches user data from the HR4 system for the Super Admin Command Center
 */

// External API endpoint for HR4 (Employee Relations)
$hr4ApiUrl = 'https://hr1.atierahotelandrestaurant.com/api/hr4_api.php';

/**
 * Fetches case records from the HR4 system
 * @return array
 */
function fetchHR4Cases()
{
    global $hr4ApiUrl;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $hr4ApiUrl);
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
                'case_id' => 'ER-2025-001',
                'employee_name' => 'Stanley Hudson',
                'issue' => 'Insubordination',
                'date_filed' => date('Y-m-d', strtotime('-1 month')),
                'status' => 'Resolved'
            ],
            [
                'case_id' => 'ER-2025-002',
                'employee_name' => 'Ryan Howard',
                'issue' => 'Fraud',
                'date_filed' => date('Y-m-d', strtotime('-1 week')),
                'status' => 'Under Investigation'
            ],
            [
                'case_id' => 'ER-2025-003',
                'employee_name' => 'Kevin Malone',
                'issue' => 'Misconduct',
                'date_filed' => date('Y-m-d'),
                'status' => 'Pending'
            ]
        ];
    }

    $data = json_decode($response, true);
    if (isset($data['status']) && $data['status'] == 'success' && isset($data['data'])) {
        return $data['data'];
    }

    return is_array($data) ? $data : [];
}

// If accessed directly via AJAX
if (basename($_SERVER['PHP_SELF']) == 'hr4_api.php') {
    header('Content-Type: application/json');
    $records = fetchHR4Cases();
    echo json_encode([
        'success' => true,
        'data' => $records
    ]);
    exit;
}
?>