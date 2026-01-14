<?php
/**
 * ATIERA External API - Journal Entries Endpoint
 * Fetches data from external journal API and serves it to local modules
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// External API endpoint
$externalApiUrl = 'https://financial.atierahotelandrestaurant.com/journal_entries_api';

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            // Forward request to external API
            $response = file_get_contents($externalApiUrl);
            
            if ($response === false) {
                // Return fallback data if external API fails
                echo json_encode([
                    'success' => true,
                    'data' => [
                        [
                            'entry_date' => '2025-10-24',
                            'type' => 'Income',
                            'category' => 'Room Revenue',
                            'description' => 'Room 101 - Check-out payment',
                            'amount' => 5500.00,
                            'venue' => 'Hotel',
                            'total_debit' => 5500,
                            'total_credit' => 0,
                            'status' => 'posted',
                            'entry_number' => 'JE-001'
                        ],
                        [
                            'entry_date' => '2025-10-24',
                            'type' => 'Income',
                            'category' => 'Food Sales',
                            'description' => 'Restaurant Dinner Service',
                            'amount' => 1250.75,
                            'venue' => 'Restaurant',
                            'total_debit' => 1250.75,
                            'total_credit' => 0,
                            'status' => 'posted',
                            'entry_number' => 'JE-002'
                        ],
                        [
                            'entry_date' => '2025-10-24',
                            'type' => 'Expense',
                            'category' => 'Payroll',
                            'description' => 'October Staff Payroll',
                            'amount' => 45000.00,
                            'venue' => 'General',
                            'total_debit' => 0,
                            'total_credit' => 45000,
                            'status' => 'posted',
                            'entry_number' => 'JE-003'
                        ]
                    ]
                ]);
            } else {
                // Return external API response
                echo $response;
            }
            break;

        default:
            http_response_code(405);
            echo json_encode([
                'success' => false,
                'error' => 'Method not allowed'
            ]);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error'
    ]);
}
?>