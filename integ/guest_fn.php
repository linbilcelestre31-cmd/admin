<?php
/**
 * Guest Records API Integration
 */
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = $_POST['id'] ?? null;
    echo json_encode(['success' => true, 'message' => "Guest record #$id protocol $action completed."]);
    exit;
}

// GET handler
echo json_encode([
    'success' => true,
    'data' => [
        ['id' => 101, 'full_name' => 'John Guest', 'category' => 'VIP', 'status' => 'Checked-in'],
        ['id' => 102, 'full_name' => 'Jane Visitor', 'category' => 'Regular', 'status' => 'Checked-out']
    ]
]);
?>