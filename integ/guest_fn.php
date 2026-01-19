<?php
error_reporting(0); // Suppress warnings to ensure valid JSON output
/**
 * Guest Records API Integration
 */
require_once __DIR__ . '/protocol_handler.php';
header('Content-Type: application/json');

$request_method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($request_method === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = $_POST['id'] ?? null;
    if ($action === 'delete')
        ProtocolHandler::quarantine('Guest', $id);
    elseif ($action === 'restore')
        ProtocolHandler::restore('Guest', $id);
    echo json_encode(['success' => true, 'message' => "Guest record #$id protocol $action completed."]);
    exit;
}

// GET handler
$external_url = 'https://core1.atierahotelandrestaurant.com/get_direct_checkins.php';

// Use cURL for better reliability
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $external_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Handle potential SSL issues locally
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$json = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$data = [];

if ($json && $http_code === 200) {
    $response = json_decode($json, true);
    if (isset($response['data']) && is_array($response['data'])) {
        foreach ($response['data'] as $record) {
            $data[] = [
                'id' => $record['id'],
                'name' => $record['guest_name'],
                'full_name' => $record['guest_name'],
                'category' => $record['room_type'] ?? 'Guest',
                'description' => "Room: " . ($record['room_number'] ?? 'N/A') . " | Status: " . ($record['status'] ?? 'Active'),
                'status' => $record['status'] ?? 'Unknown',
                'entry_date' => $record['checkin_date'],
                'upload_date' => $record['checkin_date'],
                'file_size' => 0
            ];
        }
    }
} else {
    // If external fetch fails, we might want to log it or return an empty list gracefully
    // error_log("Failed to fetch guest records: HTTP $http_code");
}

$data = ProtocolHandler::filter('Guest', $data, 'id');
echo json_encode(['success' => true, 'data' => array_values($data)]);
?>