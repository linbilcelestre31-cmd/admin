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
$data = [
    ['id' => 101, 'full_name' => 'John Guest', 'category' => 'VIP', 'status' => 'Checked-in'],
    ['id' => 102, 'full_name' => 'Jane Visitor', 'category' => 'Regular', 'status' => 'Checked-out']
];

$data = ProtocolHandler::filter('Guest', $data, 'id');
echo json_encode(['success' => true, 'data' => array_values($data)]);
?>