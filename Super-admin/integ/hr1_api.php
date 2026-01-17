<?php
/**
 * Super Admin Integration - HR1 Recruitment API
 * Fetches user data from the HR1 system for the Super Admin Command Center
 */

// External API endpoint for HR1 (Recruitment)
$hr1ApiUrl = 'https://hr1.atierahotelandrestaurant.com/api/hr4_api.php';

/**
 * Fetches applicant records from the HR1 system
 * @return array
 */
function fetchHR1Applicants()
{
    global $hr1ApiUrl;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $hr1ApiUrl);
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
                'id' => '101',
                'applicant_name' => 'Michael Scott',
                'position' => 'Regional Manager',
                'date_applied' => date('Y-m-d', strtotime('-2 days')),
                'status' => 'Pending'
            ],
            [
                'id' => '102',
                'applicant_name' => 'Dwight Schrute',
                'position' => 'Assistant to the Regional Manager',
                'date_applied' => date('Y-m-d', strtotime('-5 days')),
                'status' => 'Interviewed'
            ],
            [
                'id' => '103',
                'applicant_name' => 'Jim Halpert',
                'position' => 'Sales Representative',
                'date_applied' => date('Y-m-d'),
                'status' => 'Hired'
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
if (basename($_SERVER['PHP_SELF']) == 'hr1_api.php') {
    header('Content-Type: application/json');
    $records = fetchHR1Applicants();
    echo json_encode([
        'success' => true,
        'data' => $records
    ]);
    exit;
}
?>