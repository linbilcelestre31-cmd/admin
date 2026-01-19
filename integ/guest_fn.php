<?php
/**
 * Guest Records API Integration
 */
require_once __DIR__ . '/protocol_handler.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
$json = @file_get_contents($external_url);
$data = [];

if ($json) {
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
                'upload_date' => $record['checkin_date'], // Mapping for consistency
                'file_size' => 0 // External record
            ];
        }
    }
}

// Fallback if empty or failed
if (empty($data)) {
    // Keep empty or add error indicator if needed, but for now return empty array if fetch fails
}

$data = ProtocolHandler::filter('Guest', $data, 'id');
echo json_encode(['success' => true, 'data' => array_values($data)]);
?>