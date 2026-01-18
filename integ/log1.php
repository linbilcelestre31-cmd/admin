<?php
/**
 * Logistics 1 API Integration
 * Fetches Inventory data from Logistics system
 */
function fetchInventoryData()
{
    $api_url = "https://logistics1.atierahotelandrestaurant.com/api/v2/inventory.php";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For local development if needed
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code === 200) {
        $data = json_decode($response, true);
        return $data;
    }

    return null;
}

header('Content-Type: application/json');

// Handle actions (Edit/Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = $_POST['id'] ?? null;

    // Mock success for integrated inventory actions
    echo json_encode(['success' => true, 'message' => "Inventory asset #$id protocol $action completed."]);
    exit;
}

// Handle direct call
if (basename($_SERVER['PHP_SELF']) == 'log1.php' || isset($_GET['api'])) {
    $inventory = fetchInventoryData();

    // Support limit parameter
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 0;

    if ($inventory) {
        if ($limit > 0 && is_array($inventory)) {
            $inventory = array_slice($inventory, 0, $limit);
        }
        echo json_encode(['success' => true, 'data' => $inventory]);
    } else {
        // Fallback data if API is down
        echo json_encode([
            'success' => true,
            'data' => [
                ['inventory_id' => 57, 'item_name' => 'Orange Juice 1L', 'category' => 'Beverages', 'quantity' => 250, 'unit' => 'Bottle'],
                ['inventory_id' => 56, 'item_name' => 'Cola Drink 330ml', 'category' => 'Beverages', 'quantity' => 300, 'unit' => 'Bottle']
            ]
        ]);
    }
}
?>