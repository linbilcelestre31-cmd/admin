<?php
/**
 * Compliance API Integration
 */
require_once __DIR__ . '/protocol_handler.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = $_POST['id'] ?? null;
    if ($action === 'delete')
        ProtocolHandler::quarantine('Compliance', $id);
    elseif ($action === 'restore')
        ProtocolHandler::restore('Compliance', $id);
    echo json_encode(['success' => true, 'message' => "Compliance record #$id protocol $action completed."]);
    exit;
}

// GET handler
$data = [
    ['id' => 201, 'name' => 'Safety Audit 2023', 'category' => 'Audit', 'status' => 'Passed'],
    ['id' => 202, 'name' => 'Legal Compliance Document', 'category' => 'Legal', 'status' => 'Pending']
];

$data = ProtocolHandler::filter('Compliance', $data, 'id');
echo json_encode(['success' => true, 'data' => array_values($data)]);
?>