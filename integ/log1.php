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
            ['inventory_id' => 57, 'item_name' => 'Orange Juice 1L', 'category' => 'Beverages', 'quantity' => 250, 'unit' => 'Bottle'],
            ['inventory_id' => 56, 'item_name' => 'Cola Drink 330ml', 'category' => 'Beverages', 'quantity' => 300, 'unit' => 'Bottle']
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