<?php
/**
 * ATIERA HR API Integration Endpoint
 * Fetches HR data from external HR API and serves it to local modules
 */

// Only run if this is accessed via web server
if (php_sapi_name() === 'cli') {
    echo "This script should be accessed via web server, not CLI.\n";
    exit(1);
}

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// External HR API endpoint
$externalApiUrl = 'https://hr1.atierahotelandrestaurant.com/api/hr4_api.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    switch ($method) {
        case 'GET':
            // Use cURL for better API handling
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $externalApiUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_USERAGENT, 'ATIERA-HR-System/1.0');
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($response === false || $httpCode !== 200) {
                // Return fallback HR data if external API fails
                echo json_encode([
                    'success' => true,
                    'data' => [
                        [
                            'employee_id' => 'EMP001',
                            'name' => 'Juan Dela Cruz',
                            'position' => 'Front Desk Manager',
                            'department' => 'Front Office',
                            'email' => 'juan.delacruz@atierahotel.com',
                            'phone' => '+63 912 345 6789',
                            'hire_date' => '2023-01-15',
                            'status' => 'Active',
                            'salary' => 25000.00,
                            'document_type' => 'Employment Contract',
                            'document_name' => 'Employment_Contract_Juan_Dela_Cruz.pdf',
                            'upload_date' => '2023-01-15',
                            'file_size' => '245 KB'
                        ],
                        [
                            'employee_id' => 'EMP002',
                            'name' => 'Maria Santos',
                            'position' => 'Housekeeping Supervisor',
                            'department' => 'Housekeeping',
                            'email' => 'maria.santos@atierahotel.com',
                            'phone' => '+63 913 456 7890',
                            'hire_date' => '2022-06-20',
                            'status' => 'Active',
                            'salary' => 18000.00,
                            'document_type' => 'NBI Clearance',
                            'document_name' => 'NBI_Clearance_Maria_Santos.pdf',
                            'upload_date' => '2024-01-10',
                            'file_size' => '156 KB'
                        ],
                        [
                            'employee_id' => 'EMP003',
                            'name' => 'Carlos Reyes',
                            'position' => 'Executive Chef',
                            'department' => 'Food & Beverage',
                            'email' => 'carlos.reyes@atierahotel.com',
                            'phone' => '+63 914 567 8901',
                            'hire_date' => '2021-03-10',
                            'status' => 'Active',
                            'salary' => 45000.00,
                            'document_type' => 'Training Certificate',
                            'document_name' => 'Culinary_Training_Certificate_Carlos_Reyes.pdf',
                            'upload_date' => '2023-08-22',
                            'file_size' => '1.2 MB'
                        ],
                        [
                            'employee_id' => 'EMP004',
                            'name' => 'Ana Martinez',
                            'position' => 'Accountant',
                            'department' => 'Finance',
                            'email' => 'ana.martinez@atierahotel.com',
                            'phone' => '+63 915 678 9012',
                            'hire_date' => '2022-11-05',
                            'status' => 'Active',
                            'salary' => 28000.00,
                            'document_type' => 'BIR Form 2316',
                            'document_name' => 'BIR_Form_2316_Ana_Martinez.pdf',
                            'upload_date' => '2024-01-05',
                            'file_size' => '89 KB'
                        ],
                        [
                            'employee_id' => 'EMP005',
                            'name' => 'Roberto Lim',
                            'position' => 'Maintenance Technician',
                            'department' => 'Engineering',
                            'email' => 'roberto.lim@atierahotel.com',
                            'phone' => '+63 916 789 0123',
                            'hire_date' => '2023-05-12',
                            'status' => 'Active',
                            'salary' => 15000.00,
                            'document_type' => 'Safety Training Certificate',
                            'document_name' => 'Safety_Training_Roberto_Lim.pdf',
                            'upload_date' => '2023-12-18',
                            'file_size' => '234 KB'
                        ]
                    ]
                ]);
            } else {
                // Return external API response
                header('Content-Type: application/json');
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
        'error' => 'Internal server error: ' . $e->getMessage()
    ]);
}
?>
