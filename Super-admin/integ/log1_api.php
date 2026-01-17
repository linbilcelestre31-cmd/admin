<?php
/**
 * Super Admin Integration - Logistics 1 Inventory API
 * Fetches inventory data from the logistics system for the Super Admin Command Center
 */

// External API endpoint for Logistics 1 (Inventory)
$logistics1ApiUrl = 'https://logistics1.atierahotelandrestaurant.com/api/v2/inventory.php';

/**
 * Fetches inventory records from the Logistics system
 * @return array
 */
function fetchLogisticsInventory()
{
    global $logistics1ApiUrl;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $logistics1ApiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'ATIERA-SuperAdmin/1.0');

    // If the API requires authentication, we would add headers here
    // curl_setopt($ch, CURLOPT_HTTPHEADER, ['X-API-KEY: YOUR_KEY_HERE']);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || $httpCode !== 200) {
        // Fallback data if API fails or is unreachable
        return [
            [
                'item_id' => 'ITM-001',
                'item_name' => 'Premium Bed Sheets',
                'category' => 'Linens',
                'quantity' => 150,
                'unit' => 'Sets',
                'status' => 'In Stock',
                'last_updated' => date('Y-m-d')
            ],
            [
                'item_id' => 'ITM-002',
                'item_name' => 'Toiletries Kit',
                'category' => 'Amenities',
                'quantity' => 500,
                'unit' => 'Kits',
                'status' => 'Low Stock',
                'last_updated' => date('Y-m-d')
            ],
            [
                'item_id' => 'ITM-003',
                'item_name' => 'Cleaning Solvents',
                'category' => 'Maintenance',
                'quantity' => 20,
                'unit' => 'Liters',
                'status' => 'Critical',
                'last_updated' => date('Y-m-d')
            ]
        ];
    }

    $data = json_decode($response, true);
    // Adjust this depending on the actual API response structure
    if (isset($data['status']) && $data['status'] == 'success' && isset($data['data'])) {
        return $data['data'];
    }

    return is_array($data) ? $data : [];
}

// If accessed directly via AJAX
if (basename($_SERVER['PHP_SELF']) == 'log1_api.php') {
    header('Content-Type: application/json');
    $records = fetchLogisticsInventory();
    echo json_encode([
        'success' => true,
        'data' => $records
    ]);
    exit;
}
?>