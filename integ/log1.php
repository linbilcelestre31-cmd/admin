<?php
/**
 * Logistics 1 API Integration
 * Fetches Inventory data from Logistics system
 */
require_once __DIR__ . '/protocol_handler.php';

function fetchInventoryData()
{
    $api_url = "https://logistics1.atierahotelandrestaurant.com/api/v2/inventory.php";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'ATIERA-AdminPortal/2.0');

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code === 200) {
        $data = json_decode($response, true);

        // Handle nested data from v2 structure
        if (isset($data['success']) && $data['success'] && isset($data['data'])) {
            return $data['data'];
        }
        if (isset($data['status']) && ($data['status'] === 'success' || $data['status'] === 'ok') && isset($data['data'])) {
            return $data['data'];
        }

        // Handle direct array response (v1 fallback)
        if (is_array($data)) {
            // Check if it's the raw data array or the wrapper
            return isset($data['data']) ? $data['data'] : $data;
        }
    }

    return null;
}

header('Content-Type: application/json');

// Handle actions (Edit/Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = $_POST['id'] ?? null;

    if ($action === 'delete') {
        ProtocolHandler::quarantine('Inventory', $id);
    } elseif ($action === 'restore') {
        ProtocolHandler::restore('Inventory', $id);
    }

    echo json_encode(['success' => true, 'message' => "Inventory asset #$id protocol $action completed."]);
    exit;
}

// Handle direct call
if (basename($_SERVER['PHP_SELF']) == 'log1.php' || isset($_GET['api'])) {
    $inventory = fetchInventoryData();

    // Support limit parameter
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 0;

    if (!$inventory) {
        // Fallback data
        $inventory = [
            ['inventory_id' => 101, 'item_name' => 'Premium Bed Sheets (King)', 'category' => 'Linens', 'quantity' => 150, 'unit' => 'Set', 'unit_price' => 2500.00],
            ['inventory_id' => 102, 'item_name' => 'Bath Towels (White)', 'category' => 'Linens', 'quantity' => 500, 'unit' => 'Pcs', 'unit_price' => 450.00],
            ['inventory_id' => 103, 'item_name' => 'Shampoo 50ml', 'category' => 'Toiletries', 'quantity' => 1200, 'unit' => 'Bottle', 'unit_price' => 45.00],
            ['inventory_id' => 104, 'item_name' => 'Soap Bar 30g', 'category' => 'Toiletries', 'quantity' => 1500, 'unit' => 'Pcs', 'unit_price' => 25.00],
            ['inventory_id' => 105, 'item_name' => 'Orange Juice 1L', 'category' => 'Beverages', 'quantity' => 250, 'unit' => 'Bottle', 'unit_price' => 120.00],
            ['inventory_id' => 106, 'item_name' => 'Cola Drink 330ml', 'category' => 'Beverages', 'quantity' => 300, 'unit' => 'Can', 'unit_price' => 45.00],
            ['inventory_id' => 107, 'item_name' => 'Housekeeping Cart', 'category' => 'Equipment', 'quantity' => 15, 'unit' => 'Unit', 'unit_price' => 15000.00],
            ['inventory_id' => 108, 'item_name' => 'Vacuum Cleaner', 'category' => 'Equipment', 'quantity' => 10, 'unit' => 'Unit', 'unit_price' => 12500.00],
            ['inventory_id' => 109, 'item_name' => 'Kitchen Detergent 5L', 'category' => 'Cleaning', 'quantity' => 50, 'unit' => 'Gallon', 'unit_price' => 850.00],
            ['inventory_id' => 110, 'item_name' => 'Toilet Paper Rolls', 'category' => 'Toiletries', 'quantity' => 2000, 'unit' => 'Roll', 'unit_price' => 18.00]
        ];
    }

    // Apply local quarantine filter
    $curr_action = $_GET['action'] ?? '';
    if ($curr_action === 'quarantined') {
        $inventory = ProtocolHandler::filterOnlyQuarantined('Inventory', $inventory, 'inventory_id');
    } else {
        $inventory = ProtocolHandler::filter('Inventory', $inventory, 'inventory_id');
    }

    if ($limit > 0 && is_array($inventory)) {
        $inventory = array_slice($inventory, 0, $limit);
    }

    // Standardize data for frontend (handle multiple key variants)
    if (is_array($inventory)) {
        $inventory = array_map(function ($item) {
            // Ensure ID exists
            if (!isset($item['id'])) {
                $item['id'] = $item['inventory_id'] ?? $item['item_id'] ?? 0;
            }
            // Ensure Name exists
            if (!isset($item['name'])) {
                $item['name'] = $item['item_name'] ?? $item['product_name'] ?? 'Unknown Item';
            }
            return $item;
        }, $inventory);
    }

    echo json_encode(['success' => true, 'data' => array_values($inventory)]);
}
?>