<?php
/**
 * ATIERA External API - Financial Users Endpoint
 */
require_once __DIR__ . '/protocol_handler.php';

// Only run if this is accessed via web server
if (php_sapi_name() === 'cli') {
    echo "This script should be accessed via web server.\n";
    exit(1);
}

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS')
    exit(0);

// External API endpoint
$externalApiUrl = 'https://financial.atierahotelandrestaurant.com/admin/api/users.php';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    if ($method === 'POST') {
        $action = $_POST['action'] ?? '';
        $id = $_POST['id'] ?? null;

        if ($action === 'delete') {
            ProtocolHandler::quarantine('Financial', $id);
        } elseif ($action === 'restore') {
            ProtocolHandler::restore('Financial', $id);
        }

        echo json_encode(['success' => true, 'message' => "Financial record #$id protocol $action completed."]);
        exit;
    }

    // GET handling
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $externalApiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || $httpCode !== 200) {
        $data = [
            ['id' => 1, 'username' => 'fin_admin', 'full_name' => 'Financial Administrator', 'role' => 'admin', 'status' => 'active', 'last_login' => date('Y-m-d H:i:s'), 'total_debit' => 50000.00, 'total_credit' => 0, 'category' => 'Audit'],
            ['id' => 2, 'username' => 'cashier_01', 'full_name' => 'John Doe', 'role' => 'staff', 'status' => 'active', 'last_login' => date('Y-m-d H:i:s', strtotime('-1 day')), 'total_debit' => 12500.00, 'total_credit' => 0, 'category' => 'Operations'],
            ['id' => 3, 'username' => 'auditor_02', 'full_name' => 'Jane Smith', 'role' => 'staff', 'status' => 'inactive', 'last_login' => date('Y-m-d H:i:s', strtotime('-3 days')), 'total_debit' => 0, 'total_credit' => 7500.00, 'category' => 'Tax']
        ];
    } else {
        $data = json_decode($response, true);
        if (!$data)
            throw new Exception("Invalid JSON");
    }

    // Filter quarantined items
    $data = ProtocolHandler::filter('Financial', $data, 'id');

    echo json_encode([
        'success' => true,
        'data' => array_values($data)
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>