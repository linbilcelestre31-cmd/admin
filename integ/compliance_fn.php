<?php
/**
 * Compliance API Integration
 */
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = $_POST['id'] ?? null;
    echo json_encode(['success' => true, 'message' => "Compliance record #$id protocol $action completed."]);
    exit;
}

// GET handler
echo json_encode([
    'success' => true,
    'data' => [
        ['id' => 201, 'name' => 'Safety Audit 2023', 'category' => 'Audit', 'status' => 'Passed'],
        ['id' => 202, 'name' => 'Legal Compliance Document', 'category' => 'Legal', 'status' => 'Pending']
    ]
]);
?>