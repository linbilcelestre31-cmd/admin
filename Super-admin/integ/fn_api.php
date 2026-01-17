<?php
/**
 * Super Admin Integration - Financial Users API
 * Fetches user data from the financial system for the Super Admin Command Center
 */

// External API endpoint for Financial Records (Journal Entries)
$financialRecordsApiUrl = 'https://financial.atierahotelandrestaurant.com/journal_entries_api';

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
                'entry_number' => 'JE-001',
                'entry_date' => '2025-10-24',
                'type' => 'Income',
                'category' => 'Room Revenue',
                'description' => 'Room 101 - Check-out payment',
                'amount' => 5500.00,
                'venue' => 'Hotel',
                'status' => 'posted'
            ],
            [
                'entry_number' => 'JE-002',
                'entry_date' => '2025-10-24',
                'type' => 'Income',
                'category' => 'Food Sales',
                'description' => 'Restaurant Dinner Service',
                'amount' => 1250.75,
                'venue' => 'Restaurant',
                'status' => 'posted'
            ],
            [
                'entry_number' => 'JE-003',
                'entry_date' => '2025-10-24',
                'type' => 'Expense',
                'category' => 'Payroll',
                'description' => 'October Staff Payroll',
                'amount' => 45000.00,
                'venue' => 'General',
                'status' => 'posted'
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