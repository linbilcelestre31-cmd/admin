<?php
/**
 * HR4 EMPLOYEE MANAGEMENT API
 * Purpose: Central API for employee CRUD operations across all modules
 * Live URL: https://hr1.atierahotelandrestaurant.com/api/hr4_api.php
 * Features: Add, Update, Delete, Fetch employees with proper error handling
 * Integration: Connected to all Modules (Visitor-logs, Dashboard, Document Management, Legal Management)
 * External API: Provides external functions for integrations
 */

$externalApiUrl = 'https://hr1.atierahotelandrestaurant.com/api/hr4_api.php';


header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key');



// Function to make API calls to live server
function callLiveAPI($method, $data = null) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, LIVE_API_URL);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json'
    ]);
    
    if ($method === 'POST' || $method === 'PUT') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    } elseif ($method === 'DELETE') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return [
        'success' => $httpCode === 200,
        'data' => json_decode($response, true),
        'http_code' => $httpCode,
        'response' => $response
    ];
}

// Database connection
require_once __DIR__ . '/../db/db.php';

try {
    $pdo = get_pdo();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()]);
    exit();
}

// Get request method
$method = $_SERVER['REQUEST_METHOD'];

if ($method == 'OPTIONS') {
    exit(0);
}

// Handle POST request for adding employee
if ($method == 'POST') {
    $json_input = file_get_contents('php://input');
    $data = json_decode($json_input, true);
    
    if (!$data) {
        echo json_encode(['success' => false, 'message' => 'Invalid JSON data']);
        exit();
    }
    
    // Validate required fields
    $required_fields = ['first_name', 'last_name', 'email', 'position', 'department', 'salary'];
    foreach ($required_fields as $field) {
        if (!isset($data[$field]) || empty(trim($data[$field]))) {
            echo json_encode(['success' => false, 'message' => "Missing required field: $field"]);
            exit();
        }
    }
    
    try {
        // Insert employee
        $stmt = $pdo->prepare("
            INSERT INTO employees (
                first_name, 
                last_name, 
                email, 
                position, 
                department, 
                salary, 
                hire_date, 
                created_at, 
                updated_at
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, 
                CURRENT_TIMESTAMP, 
                CURRENT_TIMESTAMP, 
                CURRENT_TIMESTAMP
            )
        ");
        
        $stmt->execute([
            trim($data['first_name']),
            trim($data['last_name']),
            trim($data['email']),
            trim($data['position']),
            trim($data['department']),
            floatval($data['salary'])
        ]);
        
        $employee_id = $pdo->lastInsertId();
        
        echo json_encode([
            'success' => true,
            'message' => 'Employee added successfully',
            'employee_id' => $employee_id,
            'data' => [
                'id' => $employee_id,
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'],
                'position' => $data['position'],
                'department' => $data['department'],
                'salary' => $data['salary'],
                'hire_date' => date('Y-m-d'),
                'created_at' => date('Y-m-d H:i:s')
            ]
        ]);
        
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error adding employee: ' . $e->getMessage()]);
    }
}

// Handle GET request for fetching employees
    $method = $_SERVER['REQUEST_METHOD'];

if ($method == 'OPTIONS') {
    exit(0);
}

// Handle POST request for adding employee
if ($method == 'POST') {
    $json_input = file_get_contents('php://input');
    $data = json_decode($json_input, true);
    
    if (!$data) {
        echo json_encode(['success' => false, 'message' => 'Invalid JSON data']);
        exit();
    }
    
    // Validate required fields
    $required_fields = ['first_name', 'last_name', 'email', 'position', 'department', 'salary'];
    foreach ($required_fields as $field) {
        if (!isset($data[$field]) || empty(trim($data[$field]))) {
            echo json_encode(['success' => false, 'message' => "Missing required field: $field"]);
            exit();
        }
    }
    
    try {
        // Insert employee
        $stmt = $pdo->prepare("
            INSERT INTO employees (
                first_name, 
                last_name, 
                email, 
                position, 
                department, 
                salary, 
                hire_date, 
                created_at, 
                updated_at
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, 
                CURRENT_TIMESTAMP, 
                CURRENT_TIMESTAMP, 
                CURRENT_TIMESTAMP
            )
        ");
        
        $stmt->execute([
            trim($data['first_name']),
            trim($data['last_name']),
            trim($data['email']),
            trim($data['position']),
            trim($data['department']),
            floatval($data['salary'])
        ]);
        
        $employee_id = $pdo->lastInsertId();
        
        echo json_encode([
            'success' => true,
            'message' => 'Employee added successfully',
            'employee_id' => $employee_id,
            'data' => [
                'id' => $employee_id,
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'],
                'position' => $data['position'],
                'department' => $data['department'],
                'salary' => $data['salary'],
                'hire_date' => date('Y-m-d'),
                'created_at' => date('Y-m-d H:i:s')
            ]
        ]);
        
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error adding employee: ' . $e->getMessage()]);
    }
}

// Handle GET request for fetching employees
elseif ($method == 'GET') {
    try {
        $stmt = $pdo->prepare("
            SELECT id, first_name, last_name, email, position, department, salary, 
                   DATE_FORMAT(hire_date, '%Y-%m-%d') as hire_date,
                   DATE_FORMAT(created_at, '%Y-%m-%d %H:%i:%s') as created_at,
                   DATE_FORMAT(updated_at, '%Y-%m-%d %H:%i:%s') as updated_at
            FROM employees 
            ORDER BY created_at DESC
        ");
        $stmt->execute();
        $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'message' => 'Employees retrieved successfully',
            'data' => $employees,
            'total' => count($employees)
        ]);
        
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error fetching employees: ' . $e->getMessage()]);
    }
}

// Handle PUT request for updating employee
elseif ($method == 'PUT') {
    $json_input = file_get_contents('php://input');
    $data = json_decode($json_input, true);
    
    if (!isset($data['id']) || empty($data['id'])) {
        echo json_encode(['success' => false, 'message' => 'Employee ID is required']);
        exit();
    }
    
    try {
        // Update employee
        $stmt = $pdo->prepare("
            UPDATE employees SET 
                first_name = ?, 
                last_name = ?, 
                email = ?, 
                position = ?, 
                department = ?, 
                salary = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        
        $stmt->execute([
            trim($data['first_name']),
            trim($data['last_name']),
            trim($data['email']),
            trim($data['position']),
            trim($data['department']),
            floatval($data['salary']),
            intval($data['id'])
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Employee updated successfully',
            'employee_id' => $data['id']
        ]);
        
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error updating employee: ' . $e->getMessage()]);
    }
}

// Handle DELETE request for deleting employee
elseif ($method == 'DELETE') {
    $json_input = file_get_contents('php://input');
    $data = json_decode($json_input, true);
    
    if (!isset($data['id']) || empty($data['id'])) {
        echo json_encode(['success' => false, 'message' => 'Employee ID is required']);
        exit();
    }
    
    try {
        $stmt = $pdo->prepare("DELETE FROM employees WHERE id = ?");
        $stmt->execute([intval($data['id'])]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Employee deleted successfully',
            'employee_id' => $data['id']
        ]);
        
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error deleting employee: ' . $e->getMessage()]);
    }
}

// Handle GET request with financial data parameter
elseif ($method == 'GET' && isset($_GET['financial']) && $_GET['financial'] === 'true') {
    // Fetch financial data from external API
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, FINANCIAL_API_URL);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'ATIERA-System/1.0');
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    header('Content-Type: application/json');
    if ($response !== false && $httpCode === 200) {
        $data = json_decode($response, true);
        echo json_encode([
            'success' => true,
            'data' => $data,
            'message' => 'Financial data retrieved successfully'
        ]);
    } else {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => 'Failed to retrieve financial data'
        ]);
    }
}

// Handle PUT request for updating employee
elseif ($method == 'PUT') {
    $json_input = file_get_contents('php://input');
    $data = json_decode($json_input, true);
    
    if (!isset($data['id']) || empty($data['id'])) {
        echo json_encode(['success' => false, 'message' => 'Employee ID is required']);
        exit();
    }
    
    try {
        // Update employee
        $stmt = $pdo->prepare("
            UPDATE employees SET 
                first_name = ?, 
                last_name = ?, 
                email = ?, 
                position = ?, 
                department = ?, 
                salary = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        
        $stmt->execute([
            trim($data['first_name']),
            trim($data['last_name']),
            trim($data['email']),
            trim($data['position']),
            trim($data['department']),
            floatval($data['salary']),
            intval($data['id'])
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Employee updated successfully',
            'employee_id' => $data['id']
        ]);
        
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error updating employee: ' . $e->getMessage()]);
    }
}

// Handle DELETE request for deleting employee
elseif ($method == 'DELETE') {
    $json_input = file_get_contents('php://input');
    $data = json_decode($json_input, true);
    
    if (!isset($data['id']) || empty($data['id'])) {
        echo json_encode(['success' => false, 'message' => 'Employee ID is required']);
        exit();
    }
    
    try {
        $stmt = $pdo->prepare("DELETE FROM employees WHERE id = ?");
        $stmt->execute([intval($data['id'])]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Employee deleted successfully',
            'employee_id' => $data['id']
        ]);
        
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error deleting employee: ' . $e->getMessage()]);
    }
}

else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>
