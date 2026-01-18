<?php
/**
 * Marketing API Integration
 */
require_once __DIR__ . '/protocol_handler.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = $_POST['id'] ?? null;
    if ($action === 'delete')
        ProtocolHandler::quarantine('Marketing', $id);
    elseif ($action === 'restore')
        ProtocolHandler::restore('Marketing', $id);
    echo json_encode(['success' => true, 'message' => "Marketing asset #$id protocol $action completed."]);
    exit;
}

// GET handler
$data = [
    ['id' => 301, 'name' => 'Summer Campaign 2024', 'category' => 'Campaign', 'status' => 'Active'],
    ['id' => 302, 'name' => 'Brand Identity Guidelines', 'category' => 'Branding', 'status' => 'Stable']
];

$data = ProtocolHandler::filter('Marketing', $data, 'id');
echo json_encode(['success' => true, 'data' => array_values($data)]);
?>