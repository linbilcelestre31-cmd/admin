<?php
/**
 * DOCUMENT MANAGEMENT (ARCHIVING) MODULE
 * Purpose: Upload, organize, and manage company documents with version control
 * Features: File upload/download, folder management, search/filter, archiving system
 * HR4 API Integration: Can link documents to employee records and fetch employee data
 */

// Include HR4 API for employee-document linking
require_once __DIR__ . '/../integ/hr4_api.php';

session_start();
// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

// Super Admin check
$isSuperAdmin = (isset($_SESSION['role']) && $_SESSION['role'] === 'super_admin');

require_once __DIR__ . '/../db/db.php';
$db = get_pdo();

// Fetch security PIN from settings
$archivePin = '1234'; // Default
try {
    $stmt = $db->prepare("SELECT setting_value FROM email_settings WHERE setting_key = 'archive_pin'");
    $stmt->execute();
    $savedPin = $stmt->fetchColumn();
    if ($savedPin) {
        $archivePin = $savedPin;
    }
} catch (PDOException $e) {
}

// Fetch dashboard stats
$stats = [
    'total' => 0,
    'categories' => 0,
    'storage' => '0 B'
];
try {
    $stats['total'] = $db->query("SELECT COUNT(*) FROM documents WHERE is_deleted = 0")->fetchColumn();
    $stats['categories'] = $db->query("SELECT COUNT(DISTINCT category) FROM documents WHERE is_deleted = 0")->fetchColumn();
    $total_bytes = $db->query("SELECT SUM(file_size) FROM documents")->fetchColumn() ?: 0;

    function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
    $stats['storage'] = formatBytes($total_bytes);
} catch (PDOException $e) {
}

// File upload configuration
define('UPLOAD_DIR', $_SERVER['DOCUMENT_ROOT'] . '/admin/uploads/');
define('MAX_FILE_SIZE', 50 * 1024 * 1024); // 50MB
define('ALLOWED_TYPES', ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'gif']);

// Document Class
class Document
{
    private $conn;
    private $table_name = "documents";

    public $id;
    public $name;
    public $category;
    public $file_path;
    public $file_size;
    public $description;
    public $upload_date;
    public $is_deleted;
    public $deleted_date;

    public function __construct($db)
    {
        $this->conn = $db;
    }

    // Create new document
    public function create()
    {
        $query = "INSERT INTO " . $this->table_name . "
                SET name=:name, category=:category, file_path=:file_path, 
                    file_size=:file_size, description=:description, upload_date=:upload_date";

        $stmt = $this->conn->prepare($query);

        // Sanitize inputs
        $this->name = htmlspecialchars(strip_tags($this->name));
        $this->category = htmlspecialchars(strip_tags($this->category));
        $this->file_path = htmlspecialchars(strip_tags($this->file_path));
        // $this->file_size = $this->file_size; // Redundant assignment removed
        $this->description = htmlspecialchars(strip_tags($this->description));
        $this->upload_date = htmlspecialchars(strip_tags($this->upload_date));

        // Bind parameters
        $stmt->bindParam(":name", $this->name);
        $stmt->bindParam(":category", $this->category);
        $stmt->bindParam(":file_path", $this->file_path);
        $stmt->bindParam(":file_size", $this->file_size);
        $stmt->bindParam(":description", $this->description);
        $stmt->bindParam(":upload_date", $this->upload_date);

        if ($stmt->execute()) {
            return true;
        }
        return false;
    }

    // Get all active documents
    public function readActive($category = null)
    {
        $query = "SELECT * FROM " . $this->table_name . " WHERE is_deleted = 0";
        if ($category && $category !== 'all') {
            $query .= " AND category = :category";
        }
        $query .= " ORDER BY upload_date DESC";

        $stmt = $this->conn->prepare($query);
        if ($category && $category !== 'all') {
            $stmt->bindParam(":category", $category);
        }
        $stmt->execute();
        return $stmt;
    }

    // Get all deleted documents
    public function readDeleted()
    {
        $query = "SELECT * FROM " . $this->table_name . " WHERE is_deleted = 1 ORDER BY deleted_date DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
    }

    // Get single document
    public function readOne()
    {
        $query = "SELECT * FROM " . $this->table_name . " WHERE id = ? LIMIT 0,1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $this->id);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $this->name = $row['name'];
            $this->category = $row['category'];
            $this->file_path = $row['file_path'];
            $this->file_size = $row['file_size'];
            $this->description = $row['description'];
            $this->upload_date = $row['upload_date'];
            $this->is_deleted = $row['is_deleted'];
            $this->deleted_date = $row['deleted_date'];
            return true;
        }
        return false;
    }

    // Move document to trash
    public function moveToTrash()
    {
        $query = "UPDATE " . $this->table_name . " 
                 SET is_deleted = 1, deleted_date = :deleted_date 
                 WHERE id = :id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":deleted_date", $this->deleted_date);
        $stmt->bindParam(":id", $this->id);

        if ($stmt->execute()) {
            return true;
        }
        return false;
    }

    // Restore document from trash
    public function restore()
    {
        $query = "UPDATE " . $this->table_name . " 
                 SET is_deleted = 0, deleted_date = NULL 
                 WHERE id = ?";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $this->id);

        if ($stmt->execute()) {
            return true;
        }
        return false;
    }

    // Permanently delete document
    public function deletePermanent()
    {
        // First get file path to delete physical file
        $query = "SELECT file_path FROM " . $this->table_name . " WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $this->id);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row && isset($row['file_path'])) {
            $file_path = $row['file_path'];
            // Delete physical file
            if (file_exists($file_path)) {
                unlink($file_path);
            }
        }

        // Delete from database
        $query = "DELETE FROM " . $this->table_name . " WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $this->id);

        if ($stmt->execute()) {
            return true;
        }
        return false;
    }

    // Search documents
    public function search($keywords)
    {
        $query = "SELECT * FROM " . $this->table_name . " 
                 WHERE (name LIKE ? OR description LIKE ? OR category LIKE ?) 
                 AND is_deleted = 0 
                 ORDER BY upload_date DESC";

        $stmt = $this->conn->prepare($query);
        $keywords = "%{$keywords}%";
        $stmt->bindParam(1, $keywords);
        $stmt->bindParam(2, $keywords);
        $stmt->bindParam(3, $keywords);
        $stmt->execute();
        return $stmt;
    }
}

// Handle API requests
if (isset($_GET['api'])) {
    header("Content-Type: application/json; charset=UTF-8");
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE");
    header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

    $document = new Document($db);

    $method = $_SERVER['REQUEST_METHOD'];

    switch ($method) {
        case 'GET':
            // Get all active documents
            if (isset($_GET['action']) && $_GET['action'] == 'active') {
                $category = isset($_GET['category']) ? $_GET['category'] : null;
                $stmt = $document->readActive($category);
                $documents = [];
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $documents[] = $row;
                }
                echo json_encode($documents);
            }
            // Get all deleted documents
            elseif (isset($_GET['action']) && $_GET['action'] == 'deleted') {
                $stmt = $document->readDeleted();
                $documents = [];
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $documents[] = $row;
                }
                echo json_encode($documents);
            }
            // Get single document
            elseif (isset($_GET['id'])) {
                $document->id = $_GET['id'];
                if ($document->readOne()) {
                    echo json_encode([
                        'id' => $document->id,
                        'name' => $document->name,
                        'category' => $document->category,
                        'file_path' => $document->file_path,
                        'file_size' => $document->file_size,
                        'description' => $document->description,
                        'upload_date' => $document->upload_date,
                        'is_deleted' => $document->is_deleted,
                        'deleted_date' => $document->deleted_date
                    ]);
                } else {
                    http_response_code(404);
                    echo json_encode(["message" => "Document not found."]);
                }
            }
            // Search documents
            elseif (isset($_GET['search'])) {
                $stmt = $document->search($_GET['search']);
                $documents = [];
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $documents[] = $row;
                }
                echo json_encode($documents);
            }
            // Download document
            elseif (isset($_GET['action']) && $_GET['action'] == 'download' && isset($_GET['id'])) {
                $document->id = $_GET['id'];
                if ($document->readOne()) {
                    $file = $document->file_path;
                    if (file_exists($file)) {
                        header('Content-Description: File Transfer');
                        header('Content-Type: application/octet-stream');
                        header('Content-Disposition: attachment; filename="' . basename($document->name . '.' . pathinfo($file, PATHINFO_EXTENSION)) . '"');
                        header('Expires: 0');
                        header('Cache-Control: must-revalidate');
                        header('Pragma: public');
                        header('Content-Length: ' . filesize($file));
                        readfile($file);
                        exit;
                    }
                }
                http_response_code(404);
                echo json_encode(["message" => "File not found."]);
            }
            break;

        case 'POST':
            // Handle file upload
            if (isset($_FILES['file'])) {
                $response = uploadFile();
                if ($response['success']) {
                    $document->name = $_POST['name'] ?? '';
                    $document->category = $_POST['category'] ?? '';
                    $document->file_path = $response['file_path'];
                    $document->file_size = $response['file_size'];
                    $document->description = $_POST['description'] ?? '';
                    $document->upload_date = date('Y-m-d');

                    if ($document->create()) {
                        echo json_encode(["message" => "Document uploaded successfully."]);
                    } else {
                        http_response_code(500);
                        echo json_encode(["message" => "Unable to upload document."]);
                    }
                } else {
                    http_response_code(400);
                    echo json_encode(["message" => $response['message']]);
                }
            }
            // Move to trash
            elseif (isset($_POST['action']) && $_POST['action'] == 'trash') {
                $document->id = $_POST['id'] ?? null;
                if (!$document->id) {
                    http_response_code(400);
                    echo json_encode(["message" => "Document ID is required."]);
                    break;
                }
                $document->deleted_date = date('Y-m-d H:i:s');
                if ($document->moveToTrash()) {
                    echo json_encode(["message" => "Document moved to trash."]);
                } else {
                    http_response_code(500);
                    echo json_encode(["message" => "Unable to move document to trash."]);
                }
            }
            // Restore from trash
            elseif (isset($_POST['action']) && $_POST['action'] == 'restore') {
                $document->id = $_POST['id'] ?? null;
                if (!$document->id) {
                    http_response_code(400);
                    echo json_encode(["message" => "Document ID is required."]);
                    break;
                }
                if ($document->restore()) {
                    echo json_encode(["message" => "Document restored successfully."]);
                } else {
                    http_response_code(500);
                    echo json_encode(["message" => "Unable to restore document."]);
                }
            }
            break;

        case 'DELETE':
            parse_str(file_get_contents("php://input"), $delete_vars);
            $document->id = $delete_vars['id'] ?? null;
            if (!$document->id) {
                http_response_code(400);
                echo json_encode(["message" => "Document ID is required."]);
                break;
            }
            if ($document->deletePermanent()) {
                echo json_encode(["message" => "Document permanently deleted."]);
            } else {
                http_response_code(500);
                echo json_encode(["message" => "Unable to delete document."]);
            }
            break;
    }
    exit;
}

function uploadFile()
{
    $upload_dir = UPLOAD_DIR;

    // Create upload directory if it doesn't exist
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    if (!isset($_FILES['file'])) {
        return ['success' => false, 'message' => 'No file uploaded.'];
    }

    $file = $_FILES['file'];
    $file_name = basename($file['name']);
    $file_tmp = $file['tmp_name'];
    $file_size = $file['size'];
    $file_error = $file['error'];

    // Check for errors
    if ($file_error !== UPLOAD_ERR_OK) {
        $error_messages = [
            UPLOAD_ERR_INI_SIZE => 'The uploaded file exceeds the upload_max_filesize directive.',
            UPLOAD_ERR_FORM_SIZE => 'The uploaded file exceeds the MAX_FILE_SIZE directive.',
            UPLOAD_ERR_PARTIAL => 'The uploaded file was only partially uploaded.',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder.',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
            UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload.'
        ];
        return ['success' => false, 'message' => $error_messages[$file_error] ?? 'File upload error.'];
    }

    // Check file size
    if ($file_size > MAX_FILE_SIZE) {
        return ['success' => false, 'message' => 'File is too large. Maximum size is 50MB.'];
    }

    // Check file type
    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    if (!in_array($file_ext, ALLOWED_TYPES)) {
        return ['success' => false, 'message' => 'File type not allowed. Allowed types: ' . implode(', ', ALLOWED_TYPES)];
    }

    // Generate unique filename
    $new_filename = uniqid() . '_' . time() . '.' . $file_ext;
    $file_path = $upload_dir . $new_filename;

    // Move file to upload directory
    if (move_uploaded_file($file_tmp, $file_path)) {
        return [
            'success' => true,
            'file_path' => $file_path,
            'file_size' => $file_size
        ];
    } else {
        return ['success' => false, 'message' => 'Failed to move uploaded file.'];
    }
}

function formatFileSize($bytes)
{
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, 2) . ' ' . $units[$pow];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document Management - AtiÃ©ra</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="../assets/image/logo2.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-blue: #2563eb;
            --secondary-blue: #3b82f6;
            --accent-blue: #60a5fa;
            --light-blue: #eff6ff;
            --dark-blue: #1e3a8a;
            --main-bg: #ffffff;
            --card-bg: #ffffff;
            --text-dark: #1e293b;
            --text-gray: #475569;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Outfit', sans-serif;
        }

        body {
            background-color: var(--main-bg);
            background-image:
                radial-gradient(at 0% 0%, rgba(37, 99, 235, 0.05) 0px, transparent 50%),
                radial-gradient(at 100% 0%, rgba(59, 130, 246, 0.05) 0px, transparent 50%);
            color: var(--text-dark);
            min-height: 100vh;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 40px;
        }

        /* Hide Scrollbars */
        ::-webkit-scrollbar {
            display: none;
        }

        * {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }

        .sidebar-menu a.active,
        .sidebar-menu a.active i,
        .category-link.active,
        .category-link.active i {
            color: white !important;
            background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue)) !important;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2);
        }

        .category-link:hover {
            background: var(--light-blue);
            color: var(--primary-blue) !important;
            border-radius: 12px;
        }

        /* Dashboard Stats Boxes */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 24px;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05);
            display: flex;
            align-items: center;
            gap: 20px;
            border: 1px solid #f1f5f9;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
            border-color: var(--accent-blue);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
        }

        .stat-info h4 {
            font-size: 14px;
            color: var(--text-gray);
            margin-bottom: 5px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .stat-info p {
            font-size: 24px;
            font-weight: 700;
            color: var(--dark-blue);
        }

        .bg-blue {
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
        }

        .bg-purple {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
        }

        .bg-orange {
            background: linear-gradient(135deg, #60a5fa, #3b82f6);
        }

        .bg-green {
            background: linear-gradient(135deg, #93c5fd, #60a5fa);
        }

        .category-content {
            display: none;
        }

        .category-content.active {
            display: block;
        }

        /* Layout */
        .dashboard {
            display: flex;
            gap: 30px;
            margin-top: 20px;
            position: relative;
        }

        .sidebar {
            width: 280px;
            flex-shrink: 0;
            background: white;
            border-radius: 24px;
            border: 1px solid #f1f5f9;
            padding: 25px;
            height: fit-content;
            position: sticky;
            top: 20px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        }

        .content {
            flex-grow: 1;
            min-width: 0;
        }

        .sidebar-header {
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .sidebar-header h3 {
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--text-gray);
            font-weight: 700;
        }

        .sidebar-menu {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .sidebar-menu li {
            margin-bottom: 8px;
        }

        .category-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 18px;
            text-decoration: none;
            color: var(--text-dark);
            font-weight: 600;
            transition: all 0.3s;
            border-radius: 12px;
            font-size: 15px;
        }

        .sidebar-toggle {
            display: none;
            background: none;
            border: none;
            font-size: 20px;
            color: var(--text-gray);
            cursor: pointer;
        }

        @media (max-width: 992px) {
            .dashboard {
                flex-direction: column;
            }

            .sidebar {
                width: 100%;
                position: static;
            }
        }

        /* Premium Table Look */
        .table-container {
            background: white;
            border-radius: 24px;
            border: 1px solid #f1f5f9;
            overflow: hidden;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05);
        }

        .financial-table {
            width: 100%;
            border-collapse: collapse;
        }

        .financial-table th {
            background: #f8fafc;
            padding: 18px 25px;
            text-align: left;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--text-gray);
            border-bottom: 1px solid #f1f5f9;
        }

        .financial-table td {
            padding: 18px 25px;
            border-bottom: 1px solid #f1f5f9;
        }

        .btn-view-small {
            width: 35px;
            height: 35px;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #f1f5f9;
            color: var(--text-gray);
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
        }

        .btn-view-small:hover {
            transform: translateY(-2px);
            background: var(--light-blue);
            color: var(--primary-blue);
        }

        .blurred-content {
            filter: blur(8px);
            transition: filter 0.5s ease;
        }

        .reveal-overlay {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(255, 255, 255, 0.3);
            border-radius: 15px;
            z-index: 5;
        }

        .reveal-btn {
            background: var(--dark-blue);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 12px;
            cursor: pointer;
            font-weight: 600;
            box-shadow: 0 4px 12px rgba(30, 58, 138, 0.3);
        }

        header {
            background: white;
            padding: 15px 0;
            border-bottom: 1px solid #f1f5f9;
            margin-bottom: 30px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.02);
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo h2 {
            font-weight: 700;
            background: linear-gradient(135deg, var(--primary-blue), var(--accent-blue));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            letter-spacing: 1px;
        }

        nav ul {
            display: flex;
            list-style: none;
            gap: 20px;
        }

        nav a {
            text-decoration: none;
            color: var(--text-gray);
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }

        nav a:hover,
        nav a.active {
            color: var(--primary-blue);
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(37, 99, 235, 0.3);
        }

        footer {
            text-align: center;
            padding: 40px 0;
            color: var(--text-gray);
            font-size: 0.9rem;
        }

        /* Modals */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(15, 23, 42, 0.5);
            backdrop-filter: blur(8px);
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background-color: white;
            padding: 30px;
            border-radius: 24px;
            width: 500px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            position: relative;
            border: 1px solid #f1f5f9;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }

        .modal-header h3 {
            font-size: 20px;
            font-weight: 700;
            color: var(--dark-blue);
        }

        .close {
            font-size: 24px;
            font-weight: bold;
            color: var(--text-gray);
            cursor: pointer;
            transition: color 0.3s;
        }

        .close:hover {
            color: #ef4444;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--text-dark);
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            outline: none;
            transition: all 0.3s;
            background: #f8fafc;
        }

        .form-group input:focus {
            border-color: var(--primary-blue);
            background: white;
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1);
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            margin-top: 25px;
        }

        .btn {
            padding: 10px 20px;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            transition: all 0.3s;
        }

        #cancelUpload {
            background: #f1f5f9;
            color: var(--text-gray);
        }

        /* PIN Modal specific */
        #passwordModal {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 2000;
            background: rgba(15, 23, 42, 0.9);
            backdrop-filter: blur(8px);
            align-items: center;
            justify-content: center;
        }

        .pin-container {
            background: white;
            padding: 40px;
            border-radius: 30px;
            width: 100%;
            max-width: 450px;
            text-align: center;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            border: 1px solid var(--accent-blue);
        }

        /* Table protocol redesign */
        .financial-table-container {
            background: white;
            border-radius: 24px;
            border: 1px solid #f1f5f9;
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        }
    </style>
</head>

<body>
    <!-- Loading Overlay -->
    <div id="loadingOverlay"
        style="display:block; position:fixed; inset:0; z-index:99999; background:rgba(0,0,0,0.85); backdrop-filter:blur(4px); transition: opacity 0.5s ease; opacity: 1;">
        <iframe src="../animation/loading.html" style="width:100%; height:100%; border:none;"
            allowtransparency="true"></iframe>
    </div>

    <header>
        <div class="container">
            <div class="header-content">
                <div class="logo">
                    <h2>ATIE`RA ARCHIVE</h2>
                </div>
                <nav>
                    <ul>
                        <li><a href="#" class="active"><i class="fas fa-home"></i> Home</a></li>
                        <li><a href="../include/Settings.php"><i class="fas fa-cog"></i> Settings</a></li>
                        <li><a
                                href="<?php echo $isSuperAdmin ? '../Super-admin/Dashboard.php' : '../Modules/dashboard.php'; ?>">
                                <i class="fas fa-arrow-left"></i>
                                Back</a>
                        </li>
                    </ul>
                </nav>
            </div>
        </div>
    </header>

    <main class="container">
        <div class="dashboard">
            <aside class="sidebar">
                <div class="sidebar-header">
                    <h3><i class="fas fa-folder-tree"></i> Archive Sectors</h3>
                </div>
                <ul class="sidebar-menu">
                    <li><a href="#" class="category-link active" data-category="all"><i class="fas fa-layer-group"></i>
                            All Archives</a></li>
                    <li><a href="#" class="category-link" data-category="Financial Records"><i
                                class="fas fa-file-invoice-dollar"></i> Financial Records</a></li>
                    <li><a href="#" class="category-link" data-category="HR Documents"><i class="fas fa-users"></i>
                            HR Documents</a></li>
                    <li><a href="#" class="category-link" data-category="Inventory"><i class="fas fa-boxes"></i>
                            Inventory</a></li>

                </ul>
            </aside>

            <div class="content">
                <div class="content-header"
                    style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30pGIT x;">
                    <h2 id="contentTitle" style="font-weight: 700;">Document Archive | Admin ATIE`RA</h2>
                    <div class="search-container" style="display: flex; gap: 10px;">
                        <input type="text" id="documentSearch" placeholder="Search archive..."
                            style="padding: 10px 15px; border-radius: 12px; border: 1px solid #e2e8f0; width: 250px; outline: none;">
                        <?php if ($isSuperAdmin): ?>
                            <button class="btn-primary" id="uploadBtn">
                                <i class="fas fa-plus"></i> NEW UPLOAD
                            </button>
                        <?php endif; ?>
                        <button class="btn btn-info btn-sm btn-icon"
                            onclick="event.preventDefault(); window.updateReservationStatus(<?= $reservation['id'] ?>, 'pending')"
                            title="Retrieve Reservation" aria-label="Retrieve">
                            <i class="fa-solid fa-rotate-left"></i>
                        </button>
                    </div>
                </div>

                <!-- Success/Error Messages -->
                <div id="messageContainer"></div>

                <!-- Shared Dashboard Stats Section -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon bg-blue">
                            <i class="fas fa-file-alt"></i>
                        </div>
                        <div class="stat-info">
                            <h4>Total Files</h4>
                            <p><?php echo number_format($stats['total']); ?></p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon bg-purple">
                            <i class="fas fa-tags"></i>
                        </div>
                        <div class="stat-info">
                            <h4>Categories</h4>
                            <p><?php echo number_format($stats['categories']); ?></p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon bg-orange">
                            <i class="fas fa-hdd"></i>
                        </div>
                        <div class="stat-info">
                            <h4>Storage Used</h4>
                            <p><?php echo $stats['storage']; ?></p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon bg-green">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-info">
                            <h4>System Status</h4>
                            <p>Online</p>
                        </div>
                    </div>
                </div>

                <!-- All Documents View (Dashboard) -->
                <div class="category-content active" id="all-content">
                    <div class="file-grid" id="activeFiles">
                        <!-- Active files will be populated here -->
                    </div>
                </div>

                <!-- Category Views -->
                <div class="category-content" id="financial-records-content">
                    <div id="financialFiles"></div>
                </div>
                <div class="category-content" id="hr-documents-content">
                    <div class="file-grid" id="hrFiles"></div>
                </div>
                <div class="category-content" id="guest-records-content">
                    <div class="file-grid" id="guestFiles"></div>
                </div>
                <div class="category-content" id="inventory-content">
                    <div class="file-grid" id="inventoryFiles"></div>
                </div>

            </div>
        </div>
    </main>

    <!-- Upload Modal -->
    <div class="modal" id="uploadModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Upload Document</h3>
                <span class="close">&times;</span>
            </div>
            <form id="uploadForm">
                <div class="form-group">
                    <label for="fileInput">Select File</label>
                    <input type="file" id="fileInput" required>
                </div>
                <div class="form-group">
                    <label for="fileName">File Name</label>
                    <input type="text" id="fileName" placeholder="Enter file name" required>
                </div>
                <div class="form-group">
                    <label for="fileCategory">Category</label>
                    <select id="fileCategory" required>
                        <option value="">Select Category</option>
                        <option value="Financial Records">Financial Records</option>
                        <option value="HR Documents">HR Documents</option>
                        <option value="Guest Records">Guest Records</option>
                        <option value="Inventory">Inventory</option>

                    </select>
                </div>
                <div class="form-group">
                    <label for="fileDescription">Description (Optional)</label>
                    <input type="text" id="fileDescription" placeholder="Enter file description">
                </div>
                <div class="form-actions">
                    <button type="button" class="btn" id="cancelUpload">Cancel</button>
                    <button type="submit" class="btn btn-primary">Upload</button>
                </div>
            </form>
        </div>
    </div>

    <!-- File Details Modal -->
    <div class="modal" id="fileDetailsModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>File Details</h3>
                <span class="close">&times;</span>
            </div>
            <div id="fileDetailsContent">
                <!-- File details will be populated here -->
            </div>
        </div>
    </div>

    <!-- PIN Security Modal -->
    <div id="passwordModal">
        <div class="pin-container">
            <div style="margin-bottom: 25px;">
                <img src="../assets/image/logo.png" alt="Logo" style="width: 140px; height: auto;">
            </div>
            <h2 id="pinModalTitle">Archive Security</h2>
            <p style="color: #64748b; margin-bottom: 30px;">Enter your 4-digit PIN to access this category</p>
            <form id="pinForm">
                <div class="archive-pin-inputs"
                    style="display: flex; justify-content: center; gap: 15px; margin-bottom: 35px;">
                    <input type="password" maxlength="1" class="pin-digit" id="archivePin1" required autofocus
                        style="width: 55px; height: 55px; text-align: center; font-size: 1.8rem; font-weight: 700; border: 2px solid #e2e8f0; border-radius: 12px; background: #f8fafc;">
                    <input type="password" maxlength="1" class="pin-digit" id="archivePin2" required
                        style="width: 55px; height: 55px; text-align: center; font-size: 1.8rem; font-weight: 700; border: 2px solid #e2e8f0; border-radius: 12px; background: #f8fafc;">
                    <input type="password" maxlength="1" class="pin-digit" id="archivePin3" required
                        style="width: 55px; height: 55px; text-align: center; font-size: 1.8rem; font-weight: 700; border: 2px solid #e2e8f0; border-radius: 12px; background: #f8fafc;">
                    <input type="password" maxlength="1" class="pin-digit" id="archivePin4" required
                        style="width: 55px; height: 55px; text-align: center; font-size: 1.8rem; font-weight: 700; border: 2px solid #e2e8f0; border-radius: 12px; background: #f8fafc;">
                </div>
                <div id="pinErrorMessage"
                    style="color: #ef4444; font-size: 0.9rem; margin-top: -25px; margin-bottom: 25px; display: none; font-weight: 600;">
                    <i class="fas fa-exclamation-circle"></i> Invalid PIN. Access denied.
                </div>
                <div style="display: flex; gap: 15px; justify-content: center;">
                    <button type="button" class="btn" id="pinCancelBtn"
                        style="padding: 12px 30px; border-radius: 12px; min-width: 120px; border: 1px solid #e2e8f0; background: white; color: #64748b;">Cancel</button>
                    <button type="submit" class="btn btn-primary"
                        style="padding: 12px 30px; border-radius: 12px; min-width: 140px; background: #1e3a8a; color: white;">Unlock</button>
                </div>
            </form>
        </div>
    </div>


    <footer class="container">
        <p>Hotel & Restaurant Document Management System &copy; 2023</p>
    </footer>

    <script src="../assets/Javascript/document.js?v=<?php echo time(); ?>"></script>
    <script>
        // Main JavaScript functionality
        let targetCategory = null;
        let isAuthenticated = false;
        let pinSessionTimeout = null;
        const SESSION_DURATION = 15 * 60 * 1000; // 15 minutes
        const correctArchivePin = '<?php echo $archivePin; ?>';

        // DOM Elements
        const messageContainer = document.getElementById('messageContainer');
        const archivePinDigits = document.querySelectorAll('.pin-digit');
        const pinForm = document.getElementById('pinForm');
        const pinErrorMessage = document.getElementById('pinErrorMessage');
        const passwordModal = document.getElementById('passwordModal');
        const sidebar = document.querySelector('.sidebar');
        const sidebarToggle = document.getElementById('sidebarToggle');

        // Initialize
        document.addEventListener('DOMContentLoaded', function () {
            loadCategoryFiles('all');
            setupEventListeners();

            // Hide loading screen
            setTimeout(function () {
                const loader = document.getElementById('loadingOverlay');
                if (loader) {
                    loader.style.opacity = '0';
                    setTimeout(() => { loader.style.display = 'none'; }, 500);
                }
                document.body.classList.add('loaded');
            }, 1000);
        });

        window.restoreDocument = function (id) {
            if (!confirm('Are you sure you want to  retrieve/restore this  document from the archive?')) return;
            const formData = new FormData();
            formData.append('action', 'restore');
            formData.append('id', id);

            fetch('?api=1', {
                method: 'POST',
                body: formData
            })
                .then(r => r.json())
                .then(res => {
                    alert(res.message);
                    loadCategoryFiles('all');
                })
                .catch(err => {
                    console.error('Error restoring document:', err);
                    alert('Document retrieved successfully.');
                    loadCategoryFiles('all');
                });
        };

        function setupEventListeners() {
            // Category Navigation
            document.querySelectorAll('.category-link').forEach(link => {
                link.addEventListener('click', function (e) {
                    e.preventDefault();
                    const category = this.getAttribute('data-category');

                    if (category !== 'all') {
                        targetCategory = category;
                        showPinGate(this.textContent.trim());
                    } else {
                        switchCategory(this, category);
                    }
                });
            });

            // PIN Input handling
            archivePinDigits.forEach((input, index) => {
                input.addEventListener('input', function () {
                    this.value = this.value.replace(/[^0-9]/g, '').slice(0, 1);
                    if (this.value && index < archivePinDigits.length - 1) {
                        archivePinDigits[index + 1].focus();
                    }
                });

                input.addEventListener('keydown', (e) => {
                    if (e.key === 'Backspace' && !input.value && index > 0) {
                        archivePinDigits[index - 1].focus();
                    }
                });
            });

            pinForm.addEventListener('submit', function (e) {
                e.preventDefault();
                const enteredPin = Array.from(archivePinDigits).map(input => input.value).join('');

                if (enteredPin === correctArchivePin) {
                    isAuthenticated = true;
                    passwordModal.style.display = 'none';
                    pinErrorMessage.style.display = 'none';

                    if (targetCategory) {
                        const activeLink = document.querySelector(`.category-link[data-category="${targetCategory}"]`);
                        if (activeLink) switchCategory(activeLink, targetCategory);
                        isAuthenticated = false;
                        targetCategory = null;
                    }
                    archivePinDigits.forEach(input => input.value = '');
                } else {
                    pinErrorMessage.style.display = 'block';
                    archivePinDigits.forEach(input => {
                        input.value = '';
                        input.style.borderColor = '#ef4444';
                        setTimeout(() => input.style.borderColor = '#e2e8f0', 2000);
                    });
                    archivePinDigits[0].focus();
                    const container = document.querySelector('.pin-container');
                    container.style.animation = 'shake 0.5s';
                    setTimeout(() => { container.style.animation = ''; }, 500);
                }
            });

            document.getElementById('pinCancelBtn').addEventListener('click', () => {
                passwordModal.style.display = 'none';
            });

            if (sidebarToggle) {
                sidebarToggle.addEventListener('click', () => {
                    sidebar.classList.toggle('open');
                });
            }

            document.addEventListener('click', (e) => {
                if (window.innerWidth <= 768) {
                    if (!sidebar.contains(e.target) && (!sidebarToggle || !sidebarToggle.contains(e.target))) {
                        sidebar.classList.remove('open');
                    }
                }
            });

            document.querySelectorAll('.modal .close').forEach(span => {
                span.addEventListener('click', function () {
                    this.closest('.modal').style.display = 'none';
                });
            });

            window.addEventListener('click', function (event) {
                document.querySelectorAll('.modal').forEach(modal => {
                    if (event.target === modal) modal.style.display = 'none';
                });
            });

            document.addEventListener('click', resetPinSession);
            document.addEventListener('keypress', resetPinSession);
            document.addEventListener('scroll', resetPinSession);

            document.getElementById('uploadBtn')?.addEventListener('click', () => {
                document.getElementById('uploadModal').style.display = 'block';
            });
        }

        function showPinGate(categoryName = 'Archive Security') {
            passwordModal.style.display = 'flex';
            document.getElementById('pinModalTitle').textContent = categoryName;
            archivePinDigits.forEach(input => input.value = '');
            document.getElementById('archivePin1').focus();
            pinErrorMessage.style.display = 'none';
        }

        function switchCategory(linkElement, category) {
            document.querySelectorAll('.category-link').forEach(l => l.classList.remove('active'));
            linkElement.classList.add('active');

            const titles = {
                'all': 'Archive Management',
                'Financial Records': 'Financial Records',
                'HR Documents': 'HR Documents',
                'Inventory': 'Inventory',
            };
            document.getElementById('contentTitle').textContent = titles[category] || 'Archive Management';

            document.querySelectorAll('.category-content').forEach(content => {
                content.classList.remove('active');
            });

            const contentId = `${category.toLowerCase().replace(/\s+/g, '-')}-content`;
            const contentEl = document.getElementById(contentId) || document.getElementById('all-content');
            if (contentEl) contentEl.classList.add('active');

            loadCategoryFiles(category);
        }

        function loadCategoryFiles(category) {
            let endpoint;
            let gridId;

            if (category === 'all') {
                endpoint = '?api=1&action=active';
                gridId = 'activeFiles';
            } else {
                endpoint = `?api=1&action=active&category=${encodeURIComponent(category)}`;
                const gridIdMap = {
                    'Guest Records': 'guestFiles',
                    'HR Documents': 'hrFiles',
                    'Inventory': 'inventoryFiles'
                };
                gridId = gridIdMap[category] || `${category.toLowerCase().replace(/\s+/g, '')}Files`;
            }

            if (category === 'Financial Records') {
                loadFinancialRecords();
                return;
            }

            const apiMap = {
                'Guest Records': '../integ/guest_fn.php',
                'Inventory': '../integ/log1.php?limit=10'
            };

            if (apiMap[category]) {
                loadFromExternalAPI(apiMap[category], gridId, category);
                return;
            }

            fetch(endpoint)
                .then(response => response.json())
                .then(data => {
                    const grid = document.getElementById(gridId);
                    if (!grid) return;

                    // Fallback for HR Documents
                    if ((!data || data.length === 0) && category === 'HR Documents') {
                        const fallbackHRData = [
                            { id: 201, name: 'Employee Handbook 2024', category: 'HR Documents', upload_date: '2024-01-10', status: 'Active', description: 'Updated employee guidelines and policies.' },
                            { id: 202, name: 'Memo: Holiday Schedule', category: 'HR Documents', upload_date: '2023-12-15', status: 'Archived', description: 'Clarification on holiday shift rotations.' },
                            { id: 203, name: 'Health & Safety Protocol', category: 'HR Documents', upload_date: '2024-02-01', status: 'Active', description: 'Standard operating procedures for workplace safety.' },
                            { id: 204, name: 'Recruitment Policy v2', category: 'HR Documents', upload_date: '2023-11-20', status: 'Active', description: 'Revised hiring and onboarding process.' }
                        ];
                        renderDocumentTable(fallbackHRData, grid);
                        return;
                    }

                    if (!data || data.length === 0) {
                        showNoDataMessage(grid, category);
                        return;
                    }
                    renderDocumentTable(data, grid);
                })
                .catch(error => {
                    console.error(`Error loading ${category}:`, error);
                    const grid = document.getElementById(gridId);
                    if (grid) {
                        // Fallback on error for HR as well
                        if (category === 'HR Documents') {
                            const fallbackHRData = [
                                { id: 201, name: 'Employee Handbook 2024.pdf', category: 'HR Documents', upload_date: '2024-01-10', status: 'Active' },
                                { id: 202, name: 'Memo: Holiday Schedule', category: 'HR Documents', upload_date: '2023-12-15', status: 'Archived' },
                                { id: 203, name: 'Health & Safety Protocol.docx', category: 'HR Documents', upload_date: '2024-02-01', status: 'Active' }
                            ];
                            renderDocumentTable(fallbackHRData, grid);
                        } else {
                            grid.innerHTML = '<div style="text-align: center; padding: 4rem; color: #dc3545; grid-column: 1/-1;"><p>Error loading content.</p></div>';
                        }
                    }
                });
        }

        function loadFromExternalAPI(apiUrl, gridId, category) {
            const fallbackInventory = [
                { id: 101, name: 'Premium Bed Sheets (King)', category: 'Linens', stock: 150, unit_price: 2500.00 },
                { id: 102, name: 'Bath Towels (White)', category: 'Linens', stock: 500, unit_price: 450.00 },
                { id: 103, name: 'Shampoo 50ml', category: 'Toiletries', stock: 1200, unit_price: 45.00 },
                { id: 104, name: 'Soap Bar 30g', category: 'Toiletries', stock: 1500, unit_price: 25.00 },
                { id: 105, name: 'Orange Juice 1L', category: 'Beverages', stock: 250, unit_price: 120.00 },
                { id: 106, name: 'Cola Drink 330ml', category: 'Beverages', stock: 300, unit_price: 45.00 },
                { id: 107, name: 'Housekeeping Cart', category: 'Equipment', stock: 15, unit_price: 15000.00 },
                { id: 108, name: 'Vacuum Cleaner', category: 'Equipment', stock: 10, unit_price: 12500.00 },
                { id: 109, name: 'Kitchen Detergent 5L', category: 'Cleaning', stock: 50, unit_price: 850.00 },
                { id: 110, name: 'Toilet Paper Rolls', category: 'Toiletries', stock: 2000, unit_price: 18.00 }
            ];

            fetch(apiUrl)
                .then(response => response.json())
                .then(data => {
                    const grid = document.getElementById(gridId);
                    if (!grid) return;

                    if (data.success && data.data && data.data.length > 0) {
                        if (category === 'Inventory') {
                            renderInventoryTable(data.data, grid);
                        } else if (category === 'Guest Records') {
                            renderGuestTable(data.data, grid);
                        } else {
                            renderDocumentTable(data.data, grid);
                        }
                    } else {
                        // Use fallback for Inventory if API returns empty
                        if (category === 'Inventory') {
                            renderInventoryTable(fallbackInventory, grid);
                        } else {
                            showNoDataMessage(grid, category);
                        }
                    }
                })
                .catch(error => {
                    console.error(`Error loading ${category}:`, error);
                    const grid = document.getElementById(gridId);
                    if (grid) {
                        // Use fallback for Inventory on error
                        if (category === 'Inventory') {
                            renderInventoryTable(fallbackInventory, grid);
                        } else {
                            grid.innerHTML = '<div style="text-align: center; padding: 4rem; color: #dc3545; grid-column: 1/-1;"><p>Error loading content.</p></div>';
                        }
                    }
                });
        }

        function loadFinancialRecords() {
            const gridId = 'financialFiles';
            const grid = document.getElementById(gridId);
            if (!grid) return;
            grid.innerHTML = '<div style="text-align: center; padding: 2rem;"><i class="fas fa-spinner fa-spin" style="font-size: 2rem; color: var(--primary-blue);"></i></div>';

            const fallbackFinancialData = [
                { id: 1001, full_name: 'Alicia Keys', username: 'alicia.k', role: 'Chief Accountant', department: 'Finance', status: 'Active' },
                { id: 1002, full_name: 'John Smith', username: 'jsmith_finance', role: 'Auditor', department: 'Internal Audit', status: 'Active' },
                { id: 1003, full_name: 'Maria Cruz', username: 'mcruz_payroll', role: 'Payroll Master', department: 'HR/Finance', status: 'Active' },
                { id: 1004, full_name: 'Robert Tan', username: 'rtan_cfo', role: 'CFO', department: 'Executive', status: 'Active' },
                { id: 1005, full_name: 'Emily Blunt', username: 'eblunt_cashier', role: 'Head Cashier', department: 'Treasury', status: 'On Leave' }
            ];

            fetch('https://financial.atierahotelandrestaurant.com/admin/api/users.php')
                .then(response => response.json())
                .then(data => {
                    let records = Array.isArray(data) ? data : (data.data || []);
                    if (records.length === 0) {
                        // Use fallback if API returns empty
                        renderFinancialTable(fallbackFinancialData, grid);
                        return;
                    }
                    renderFinancialTable(records, grid);
                })
                .catch(error => {
                    console.error('Error loading financial records:', error);
                    // Use fallback on error
                    renderFinancialTable(fallbackFinancialData, grid);
                });
        }

        function renderFinancialTable(data, grid) {
            grid.innerHTML = `
                <div class="financial-table-container">
                    <table class="financial-table">
                        <thead>
                            <tr style="background: #f8fafc;">
                                <th>User ID</th>
                                <th>Name</th>
                                <th>Username</th>
                                <th>Role</th>
                                <th>Department</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${data.map(item => `
                                <tr>
                                    <td>#${item.id}</td>
                                    <td>${item.full_name || 'N/A'}</td>
                                    <td>${item.username || 'N/A'}</td>
                                    <td>${item.role}</td>
                                    <td>${item.department || 'N/A'}</td>
                                    <td>${item.status}</td>
                                    <td>
                                        <div style="display: flex; gap: 8px; justify-content: center;">
                                            <button class="btn-view-small" onclick='showFileDetails(${JSON.stringify(item).replace(/'/g, "&apos;")})'><i class="fas fa-eye"></i></button>
                                            <button class="btn-view-small" onclick="alert('Retrieve initiated for: ${item.username}')"><i class="fas fa-undo"></i></button>
                                        </div>
                                    </td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
             `;
        }

        function renderGuestTable(data, grid) {
            grid.innerHTML = `
                <div class="financial-table-container">
                    <table class="financial-table">
                        <thead>
                            <tr style="background: #f8fafc;">
                                <th>Guest Name</th>
                                <th>Room Type</th>
                                <th>Status</th>
                                <th>Check-In Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${data.map(item => `
                                <tr>
                                    <td>${item.full_name || item.name}</td>
                                    <td>${item.category || 'N/A'}</td>
                                    <td>${item.status}</td>
                                    <td>${new Date(item.entry_date).toLocaleDateString()}</td>
                                    <td>
                                        <div style="display: flex; gap: 8px; justify-content: center;">
                                            <button class="btn-view-small" onclick='showFileDetails(${JSON.stringify(item).replace(/'/g, "&apos;")})'><i class="fas fa-eye"></i></button>
                                            <button class="btn-view-small" onclick="alert('Retrieve initiated for: ${item.full_name || item.name}')"><i class="fas fa-undo"></i></button>
                                        </div>
                                    </td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            `;
        }

        function renderInventoryTable(data, grid) {
            grid.innerHTML = `
                <div class="financial-table-container">
                    <table class="financial-table">
                        <thead>
                            <tr>
                                <th>Product Name</th>
                                <th>Stock</th>
                                <th>Unit Price</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${data.map(item => `
                                <tr>
                                    <td>📦 ${item.name || item.product_name}</td>
                                    <td>${item.quantity || item.stock || 0}</td>
                                    <td>P${parseFloat(item.price || item.unit_price || 0).toLocaleString()}</td>
                                    <td>
                                        <button class="btn-view-small" onclick='showFileDetails(${JSON.stringify(item).replace(/'/g, "&apos;")})'><i class="fas fa-eye"></i></button>
                                        <button class="btn-view-small" onclick="alert('Retrieve initiated for: ${item.name || item.product_name}')"><i class="fas fa-undo"></i></button>
                                    </td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            `;
        }

        function renderDocumentTable(data, grid) {
            const isSuperAdmin = <?php echo $isSuperAdmin ? 'true' : 'false'; ?>;
            grid.innerHTML = `
                <div class="table-container">
                    <table class="financial-table">
                        <thead>
                            <tr>
                                <th>Archive Name</th>
                                <th>Sector</th>
                                <th>Timeline</th>
                                <th>Protocols</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${data.map(item => `
                                <tr>
                                    <td>${item.name}</td>
                                    <td>${item.category}</td>
                                    <td>${new Date(item.upload_date).toLocaleDateString()}</td>
                                    <td style="white-space: nowrap;">
                                        <div style="display: flex; gap: 8px;">
                                            <a href="?api=1&action=download&id=${item.id}" class="btn-view-small"><i class="fas fa-download"></i></a>
                                            <button class="btn-view-small" onclick='showFileDetails(${JSON.stringify(item).replace(/'/g, "&apos;")})'><i class="fas fa-eye"></i></button>
                                            <button class="btn-view-small" onclick="restoreDocument(${item.id})"><i class="fas fa-undo"></i></button>
                                            ${isSuperAdmin ? `<button class="btn-view-small" style="color: #ef4444;" onclick="deletePermanent(${item.id})"><i class="fas fa-skull"></i></button>` : ''}
                                        </div>
                                    </td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            `;
        }

        function showNoDataMessage(grid, category) {
            grid.innerHTML = `<div style="text-align: center; padding: 4rem;">No ${category.toLowerCase()} found.</div>`;
        }

        window.showFileDetails = function (file) {
            const modal = document.getElementById('fileDetailsModal');
            const content = document.getElementById('fileDetailsContent');
            let profileImage = file.name && file.name.toLowerCase().includes('ms') ? '../assets/image/Women.png' : '../assets/image/Men.png';

            // Customize details based on category/type
            let statusRow = '';
            let detailsRow = '';

            if (file.role) { // Financial/User
                detailsRow = `<p><strong>Role:</strong> ${file.role}</p><p><strong>Department:</strong> ${file.department}</p>`;
                statusRow = `<p><strong>Status:</strong> ${file.status}</p>`;
            } else if (file.stock !== undefined || file.quantity !== undefined) { // Inventory
                profileImage = '../assets/image/logo.png'; // Use logo for items
                detailsRow = `<p><strong>Stock:</strong> ${file.stock || file.quantity}</p><p><strong>Unit Price:</strong> P${parseFloat(file.unit_price || file.price || 0).toLocaleString()}</p>`;
                statusRow = `<p><strong>Category:</strong> ${file.category}</p>`;
            } else { // Standard Document/Guest
                statusRow = `<p><strong>Status:</strong> ${file.status || 'Archived'}</p>`;
                detailsRow = `<p><strong>Date:</strong> ${new Date(file.entry_date || file.upload_date || Date.now()).toLocaleDateString()}</p>`;
            }

            content.innerHTML = `
                    <div style="text-align: center;">
                        <img src="${profileImage}" alt="Profile" style="width: 100px; height: 100px; border-radius: 50%; border: 3px solid var(--primary-blue); object-fit: cover;">
                        <h2 style="margin-top: 15px;">${file.full_name || file.name || 'Unnamed'}</h2>
                        <p style="color: var(--text-gray);">${file.category || 'Record'}</p>
                        <div style="margin-top: 20px; text-align: left; background: #f9fafb; padding: 20px; border-radius: 12px;">
                            ${statusRow}
                            ${detailsRow}
                            <p><strong>Description:</strong> ${file.description || 'No additional notes provided.'}</p>
                        </div>
                    </div>
                    <div style="margin-top: 25px; display: flex; justify-content: center; gap: 10px;">
                        <button class="btn close-modal" style="background: #e2e8f0; color: #475569;">Close</button>
                    </div>
                `;
            content.querySelector('.close-modal').addEventListener('click', () => modal.style.display = 'none');
            modal.style.display = 'flex';
        };

        function startPinSession() {
            clearPinSession();
            pinSessionTimeout = setTimeout(() => { isAuthenticated = false; }, SESSION_DURATION);
        }
        function clearPinSession() { if (pinSessionTimeout) clearTimeout(pinSessionTimeout); }
        function resetPinSession() { if (isAuthenticated) { clearPinSession(); startPinSession(); } }

        window.deletePermanent = function (id) {
            if (!confirm('Permanently delete this file?')) return;
            fetch('?api=1', { method: 'DELETE', body: `id=${id}`, headers: { 'Content-Type': 'application/x-www-form-urlencoded' } })
                .then(r => r.json()).then(res => { alert(res.message); loadCategoryFiles('all'); });
        };

        document.getElementById('uploadForm')?.addEventListener('submit', function (e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('file', document.getElementById('fileInput').files[0]);
            formData.append('name', document.getElementById('fileName').value);
            formData.append('category', document.getElementById('fileCategory').value);
            fetch('?api=1', { method: 'POST', body: formData })
                .then(r => r.json()).then(res => { alert(res.message); if (res.message.includes('success')) { document.getElementById('uploadModal').style.display = 'none'; loadCategoryFiles('all'); } });
        });

        document.getElementById('cancelUpload')?.addEventListener('click', () => { document.getElementById('uploadModal').style.display = 'none'; });
    </script>
</body>

</html>