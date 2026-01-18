<?php
/**
 * HR4 EMPLOYEE MANAGEMENT API
 * Purpose: Central API for employee CRUD operations across all modules
 * Live URL: https://hr1.atierahotelandrestaurant.com/api/hr4_api.php
 * Features: Add, Update, Delete, Fetch employees with proper error handling
 * Integration: Connected to all Modules (Visitor-logs, Dashboard, Document Management, Legal Management)
 */

// Configuration
if (!defined('LIVE_API_URL')) {
    define('LIVE_API_URL', 'https://hr1.atierahotelandrestaurant.com/api/hr4_api.php');
}

if (!defined('FINANCIAL_API_URL')) {
    define('FINANCIAL_API_URL', 'https://financial.atierahotelandrestaurant.com/journal_entries_api');
}

// Function to make API calls to live server
function callLiveAPI($method, $data = null)
{
    $ch = curl_init();

    $url = LIVE_API_URL;
    if ($method === 'GET' && !empty($data)) {
        $url .= '?' . http_build_query($data);
    }

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json'
    ]);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    } elseif ($method === 'PUT') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    } elseif ($method === 'DELETE') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        return [
            'success' => false,
            'message' => 'CURL Error: ' . $error
        ];
    }

    return [
        'success' => ($httpCode >= 200 && $httpCode < 300),
        'data' => json_decode($response, true),
        'http_code' => $httpCode,
        'raw_response' => $response
    ];
}

// Wrapper function to fetch employees with optional limit
function fetchAllEmployees($limit = 0)
{
    $data = [];
    if ($limit > 0) {
        $data['limit'] = $limit;
    }

    $result = callLiveAPI('GET', $data);
    if ($result['success'] && is_array($result['data'])) {
        $employees = $result['data'];
        // Ensure limit is respected even if API returns more
        if ($limit > 0 && count($employees) > $limit) {
            $employees = array_slice($employees, 0, $limit);
        }
        return $employees;
    }
    return [];
}

// Only execute the API logic if this file is called directly
if (basename($_SERVER['PHP_SELF']) == 'hr4_api.php') {
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, GET, OPTIONS, PUT, DELETE');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key');

    $method = $_SERVER['REQUEST_METHOD'];

    if ($method == 'OPTIONS') {
        exit(0);
    }

    if ($method == 'GET') {
        $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 0;
        $employees = fetchAllEmployees($limit);

        // Apply local quarantine filter
        require_once __DIR__ . '/protocol_handler.php';
        $employees = ProtocolHandler::filter('HR', $employees, 'id');

        echo json_encode([
            'success' => true,
            'message' => 'Employees retrieved successfully from live server',
            'data' => array_values($employees),
            'total' => count($employees)
        ]);
        exit();
    }

    // Proxy other methods if needed, or handle locally as fallback
    // For now, let's keep it simple as the user mainly wants to see the data

    $json_input = file_get_contents('php://input');
    $data = json_decode($json_input, true);

    if ($method == 'POST') {
        // Proxy to live API if desired, or handle local
        // User mainly asked for visibility, but let's make it robust
        $action = $data['action'] ?? $_POST['action'] ?? '';
        $id = $data['id'] ?? $_POST['id'] ?? null;

        if ($action === 'delete') {
            require_once __DIR__ . '/protocol_handler.php';
            ProtocolHandler::quarantine('HR', $id);
            echo json_encode(['success' => true, 'message' => "HR protocol delete completed."]);
            exit;
        } elseif ($action === 'restore') {
            require_once __DIR__ . '/protocol_handler.php';
            ProtocolHandler::restore('HR', $id);
            echo json_encode(['success' => true, 'message' => "HR protocol restore completed."]);
            exit;
        }

        $result = callLiveAPI('POST', $data);
        echo json_encode($result);
        exit();
    }

    if ($method == 'PUT') {
        $result = callLiveAPI('PUT', $data);
        echo json_encode($result);
        exit();
    }

    if ($method == 'DELETE') {
        $result = callLiveAPI('DELETE', $data);
        echo json_encode($result);
        exit();
    }

    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}
?>