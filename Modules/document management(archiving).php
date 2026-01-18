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
    <title>Document Management - Atiéra</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="../assets/image/logo2.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-purple: #8b5cf6;
            --secondary-pink: #d946ef;
            --dark-blue: #0f172a;
            --main-bg: #f8fafc;
            --card-bg: #ffffff;
            --text-dark: #1e293b;
            --text-gray: #64748b;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Outfit', sans-serif;
        }

        body {
            background: var(--main-bg);
            color: var(--text-dark);
            min-height: 100vh;
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
            background: linear-gradient(135deg, var(--primary-purple), var(--secondary-pink)) !important;
            border-radius: 12px;
        }

        .category-link:hover {
            background: rgba(139, 92, 246, 0.1);
            color: var(--primary-purple) !important;
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
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            display: flex;
            align-items: center;
            gap: 20px;
            border: 1px solid #e2e8f0;
            transition: transform 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
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
            color: #64748b;
            margin-bottom: 5px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .stat-info p {
            font-size: 24px;
            font-weight: 700;
            color: #1e293b;
        }

        .bg-blue {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
        }

        .bg-purple {
            background: linear-gradient(135deg, #8b5cf6, #7c3aed);
        }

        .bg-orange {
            background: linear-gradient(135deg, #f59e0b, #d97706);
        }

        .bg-green {
            background: linear-gradient(135deg, #10b981, #059669);
        }

        /* Premium Table Look */
        .table-container {
            background: white;
            border-radius: 24px;
            border: 1px solid #e2e8f0;
            overflow: hidden;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
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
            border-bottom: 1px solid #e2e8f0;
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
            background: #eff6ff;
            color: #3b82f6;
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
        }

        header {
            background: white;
            padding: 15px 0;
            border-bottom: 1px solid #e2e8f0;
            margin-bottom: 30px;
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo h2 {
            font-weight: 700;
            background: linear-gradient(135deg, var(--primary-purple), var(--secondary-pink));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
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
            transition: color 0.3s;
        }

        nav a:hover,
        nav a.active {
            color: var(--primary-purple);
        }

        .btn-primary {
            background: var(--primary-purple);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
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
                    <h2>ATIÉRA ARCHIVE</h2>
                </div>
                <nav>
                    <ul>
                        <li><a href="#" class="active"><i class="fas fa-home"></i> Home</a></li>
                        <li><a href="../include/Settings.php"><i class="fas fa-cog"></i> Settings</a></li>
                        <li><a
                                href="<?php echo $isSuperAdmin ? '../Super-admin/Dashboard.php' : '../Modules/dashboard.php'; ?>">
                                <i class="fas fa-arrow-left"></i>
                                <?php echo $isSuperAdmin ? 'Command Center' : 'Back'; ?></a>
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
                    <h3><i class="fas fa-folder-tree"></i> Categories</h3>
                    <button class="sidebar-toggle" id="sidebarToggle">
                        <i class="fas fa-bars"></i>
                    </button>
                </div>
                <ul class="sidebar-menu">
                    <li><a href="#" class="category-link active" data-category="all"><i class="fas fa-layer-group"></i>
                            All Documents</a></li>
                    <li><a href="#" class="category-link" data-category="Financial Records" style="font-weight: 600;"><i
                                class="fas fa-file-invoice-dollar"></i> Financial Records</a></li>
                    <li><a href="#" class="category-link" data-category="HR Documents"><i class="fas fa-users"></i> HR
                            Documents</a></li>
                    <li><a href="#" class="category-link" data-category="Guest Records"><i
                                class="fas fa-user-check"></i> Guest Records</a></li>
                    <li><a href="#" class="category-link" data-category="Inventory"><i class="fas fa-boxes"></i>
                            Inventory</a></li>
                    <li><a href="#" class="category-link" data-category="Compliance"><i class="fas fa-shield-alt"></i>
                            Compliance</a></li>
                    <li><a href="#" class="category-link" data-category="Marketing"><i class="fas fa-bullhorn"></i>
                            Marketing</a></li>
                </ul>
                <!-- Sidebar footer removed as per user request -->
            </aside>

            <div class="content">
                <div class="content-header"
                    style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
                    <h2 id="contentTitle" style="font-weight: 700;">Archive Management</h2>
                    <div class="search-container" style="display: flex; gap: 10px;">
                        <input type="text" id="documentSearch" placeholder="Search archive..."
                            style="padding: 10px 15px; border-radius: 12px; border: 1px solid #e2e8f0; width: 250px; outline: none;">
                        <?php if ($isSuperAdmin): ?>
                            <button class="btn-primary" id="uploadBtn">
                                <i class="fas fa-plus"></i> NEW UPLOAD
                            </button>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Success/Error Messages -->
                <div id="messageContainer"></div>

                <!-- All Documents View (Dashboard) -->
                <div class="category-content active" id="all-content">
                    <!-- Dashboard Stats Section -->
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
                <div class="category-content" id="compliance-content">
                    <div class="file-grid" id="complianceFiles"></div>
                </div>
                <div class="category-content" id="marketing-content">
                    <div class="file-grid" id="marketingFiles"></div>
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
                        <option value="Compliance">Compliance</option>
                        <option value="Marketing">Marketing</option>
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

    <script src="../assets/Javascript/document.js"></script>
    <script>
        // Main JavaScript functionality
        let targetCategory = null;
        let isAuthenticated = false;
        let pinSessionTimeout = null;
        const SESSION_DURATION = 15 * 60 * 1000; // 15 minutes
        const correctArchivePin = '<?php echo $archivePin; ?>'; // In production, this should be stored securely

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

        function setupEventListeners() {
            // Category Navigation
            document.querySelectorAll('.category-link').forEach(link => {
                link.addEventListener('click', function (e) {
                    e.preventDefault();
                    const category = this.getAttribute('data-category');

                    // Require PIN for all specific categories except the main Dashboard
                    if (category !== 'all') {
                        targetCategory = category;
                        showPinGate(this.textContent.trim());
                    } else {
                        switchCategory(this, category);
                    }
                });
            });

            // Tab Navigation removed as per user request (Trash bin erased)

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

            // PIN Form submission
            pinForm.addEventListener('submit', function (e) {
                e.preventDefault();
                const enteredPin = Array.from(archivePinDigits).map(input => input.value).join('');

                if (enteredPin === correctArchivePin) {
                    isAuthenticated = true; // Temporary authentication for this navigation
                    passwordModal.style.display = 'none';
                    pinErrorMessage.style.display = 'none';

                    if (targetCategory) {
                        const activeLink = document.querySelector(`.category-link[data-category="${targetCategory}"]`);
                        if (activeLink) switchCategory(activeLink, targetCategory);
                        // Reset authentication so next click requires PIN again
                        isAuthenticated = false;
                        targetCategory = null;
                    }
                    // Reset inputs
                    archivePinDigits.forEach(input => input.value = '');
                } else {
                    pinErrorMessage.style.display = 'block';
                    archivePinDigits.forEach(input => {
                        input.value = '';
                        input.style.borderColor = '#ef4444';
                        setTimeout(() => input.style.borderColor = '#e2e8f0', 2000);
                    });
                    archivePinDigits[0].focus();

                    // Shake animation
                    const container = document.querySelector('.pin-container');
                    container.style.animation = 'shake 0.5s';
                    setTimeout(() => { container.style.animation = ''; }, 500);
                }
            });

            // Cancel PIN
            document.getElementById('pinCancelBtn').addEventListener('click', () => {
                passwordModal.style.display = 'none';
            });

            // Sidebar toggle
            if (sidebarToggle) {
                sidebarToggle.addEventListener('click', () => {
                    sidebar.classList.toggle('open');
                });
            }

            // Close sidebar when clicking outside on mobile
            document.addEventListener('click', (e) => {
                if (window.innerWidth <= 768) {
                    if (!sidebar.contains(e.target) && !sidebarToggle.contains(e.target)) {
                        sidebar.classList.remove('open');
                    }
                }
            });

            // Modal close handlers
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

            // Session reset on user activity
            document.addEventListener('click', resetPinSession);
            document.addEventListener('keypress', resetPinSession);
            document.addEventListener('scroll', resetPinSession);

            // Quick Actions Event Listeners
            document.getElementById('quickUpload')?.addEventListener('click', () => {
                document.getElementById('uploadModal').style.display = 'block';
            });

            document.getElementById('quickSearch')?.addEventListener('click', () => {
                const search = prompt('Enter keywords to search documents:');
                if (search) {
                    // Search results are shown in All Documents view, no PIN bypass needed for navigation here
                    // but if specific categories are loaded, they will ask for PIN via loadCategoryFiles if modified there.
                    // For now, search is global.

                    document.getElementById('contentTitle').textContent = `Search Results: "${search}"`;
                    document.querySelectorAll('.category-content').forEach(c => c.classList.remove('active'));
                    document.getElementById('all-content').classList.add('active');

                    fetch(`?api=1&search=${encodeURIComponent(search)}`)
                        .then(r => r.json())
                        .then(data => {
                            const grid = document.getElementById('activeFiles');
                            if (data && data.length > 0) {
                                renderDocumentTable(data, grid);
                            } else {
                                grid.innerHTML = `<div style="text-align: center; padding: 4rem; color: #64748b; grid-column: 1/-1;">No results found for "${search}"</div>`;
                            }
                        });
                }
            });

            document.getElementById('quickSettings')?.addEventListener('click', () => {
                window.location.href = '../include/Settings.php';
            });

            document.getElementById('quickExport')?.addEventListener('click', () => {
                alert('Generating document report...');
                window.location.href = '?api=1&action=active'; // Simple export as JSON for now
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
            // Update active state
            document.querySelectorAll('.category-link').forEach(l => l.classList.remove('active'));
            linkElement.classList.add('active');

            // Update title
            const titles = {
                'all': 'Archive Management',
                'Financial Records': 'Financial Records',
                'HR Documents': 'HR Documents',
                'Guest Records': 'Guest Records',
                'Inventory': 'Inventory',
                'Compliance': 'Compliance',
                'Marketing': 'Marketing'
            };
            document.getElementById('contentTitle').textContent = titles[category] || 'Archive Management';

            // Show appropriate content
            document.querySelectorAll('.category-content').forEach(content => {
                content.classList.remove('active');
            });

            const contentId = `${category.toLowerCase().replace(/\s+/g, '-')}-content`;
            const contentEl = document.getElementById(contentId) || document.getElementById('all-content');
            if (contentEl) contentEl.classList.add('active');

            // Load files
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
                gridId = `${category.toLowerCase().replace(/\s+/g, '')}Files`;
            }

            // Special handling for Financial Records
            if (category === 'Financial Records') {
                loadFinancialRecords();
                return;
            }

            // API integrations for other categories
            const apiMap = {
                'HR Documents': '../integ/hr_fn.php',
                'Guest Records': '../integ/guest_fn.php',
                'Inventory': '../integ/log1.php?limit=10',
                'Compliance': '../integ/compliance_fn.php',
                'Marketing': '../integ/marketing_fn.php'
            };

            if (apiMap[category]) {
                loadFromExternalAPI(apiMap[category], gridId, category);
                return;
            }

            // Default document loading
            fetch(endpoint)
                .then(response => response.json())
                .then(data => {
                    const grid = document.getElementById(gridId);
                    if (!grid) return;

                    if (!data || data.length === 0) {
                        showNoDataMessage(grid, category);
                        return;
                    }

                    renderDocumentTable(data, grid);
                })
                .catch(error => {
                    console.error('Error loading documents:', error);
                    const grid = document.getElementById(gridId);
                    if (grid) {
                        grid.innerHTML = `
                            <div style="text-align: center; padding: 4rem; color: #dc3545; grid-column: 1/-1;">
                                <i class="fas fa-exclamation-triangle" style="font-size: 3rem; margin-bottom: 1.5rem;"></i>
                                <p style="font-size: 1.2rem; font-weight: 500;">Error loading documents</p>
                                <p style="font-size: 0.9rem;">Please try again later.</p>
                            </div>
                        `;
                    }
                });
        }

        function loadFinancialRecords() {
            const grid = document.getElementById('financialFiles');
            const fallbackData = [
                {
                    entry_date: '2025-10-24',
                    type: 'Income',
                    category: 'Room Revenue',
                    description: 'Room 101 - Check-out payment',
                    amount: 5500.00,
                    venue: 'Hotel',
                    total_debit: 5500,
                    total_credit: 0,
                    status: 'posted',
                    entry_number: 'JE-001'
                },
                // Add more fallback data as needed
            ];

            // Try proxy API first
            fetch('../integ/fn.php')
                .then(response => response.json())
                .then(result => {
                    console.log('Financial Records Result:', result);
                    const data = (result && result.success && Array.isArray(result.data)) ? result.data :
                        (Array.isArray(result) ? result : fallbackData);
                    renderFinancialTable(data);
                })
                .catch(error => {
                    console.error('Financial API error:', error);
                    renderFinancialTable(fallbackData);
                });

            function renderFinancialTable(data) {
                const tableContainer = document.getElementById('financialFiles');
                if (!tableContainer) return;

                tableContainer.innerHTML = `
                    <div class="financial-table-container">
                        <table class="financial-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Date</th>
                                    <th>Type</th>
                                    <th>Category</th>
                                    <th>Description</th>
                                    <th>Amount</th>
                                    <th>Venue</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${data.map(record => {
                    const type = record.role || record.type || (parseFloat(record.total_credit) > 0 ? 'Income' : 'Expense');
                    const typeColor = (type.toLowerCase() === 'income' || type.toLowerCase() === 'admin') ? '#2ecc71' :
                        (type.toLowerCase() === 'staff' ? '#3498db' : '#e74c3c');
                    const safeRecord = JSON.stringify(record).replace(/'/g, "&apos;");
                    const dateVal = record.created_at || record.entry_date;
                    const formattedDate = dateVal ? new Date(dateVal).toLocaleDateString('en-US', {
                        year: 'numeric',
                        month: '2-digit',
                        day: '2-digit'
                    }) : 'N/A';
                    const amountValue = parseFloat(record.total_debit || record.amount || 0)
                        .toLocaleString(undefined, {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2
                        });

                    return `
                                        <tr>
                                            <td style="font-weight: 700;">#${record.id || record.entry_number || 'N/A'}</td>
                                            <td style="white-space: nowrap;">${formattedDate}</td>
                                            <td><span style="color: ${typeColor}; font-weight: 600; text-transform: capitalize;">${type}</span></td>
                                            <td>${record.department || record.category || 'N/A'}</td>
                                            <td style="min-width: 200px;">${record.full_name || record.description}</td>
                                            <td style="font-weight: 700; white-space: nowrap;">$${amountValue}</td>
                                            <td>${record.status || record.venue || 'N/A'}</td>
                                            <td style="white-space: nowrap;">
                                                <button class="btn-view-small" onclick='showFinancialDetails(${safeRecord})' title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="btn-view-small" onclick='window.print()' title="Print">
                                                    <i class="fas fa-print"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    `;
                }).join('')}
                            </tbody>
                        </table>
                    </div>
                `;
            }
        }

        function loadFromExternalAPI(apiUrl, gridId, category) {
            fetch(apiUrl)
                .then(response => response.json())
                .then(data => {
                    const grid = document.getElementById(gridId);
                    if (!grid) return;

                    if (data.success && data.data && data.data.length > 0) {
                        if (category === 'Inventory') {
                            renderInventoryTable(data.data, grid);
                        } else {
                            renderDocumentTable(data.data, grid);
                        }
                    } else {
                        showNoDataMessage(grid, category);
                    }
                })
                .catch(error => {
                    console.error(`Error loading ${category}:`, error);
                    const grid = document.getElementById(gridId);
                    if (grid) {
                        grid.innerHTML = `
                            <div style="text-align: center; padding: 4rem; color: #dc3545; grid-column: 1/-1;">
                                <i class="fas fa-exclamation-triangle" style="font-size: 3rem; margin-bottom: 1.5rem;"></i>
                                <p style="font-size: 1.2rem; font-weight: 500;">Error loading ${category}</p>
                                <p style="font-size: 0.9rem;">Please try again later.</p>
                            </div>
                        `;
                    }
                });
        }

        function renderInventoryTable(data, grid) {
            grid.innerHTML = `
                <div class="financial-table-container" style="grid-column: 1/-1;">
                    <table class="financial-table">
                        <thead>
                            <tr>
                                <th>Item ID</th>
                                <th>Product Name</th>
                                <th>Category</th>
                                <th>Stock Quantity</th>
                                <th>Unit Price</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${data.map(item => {
                const stock = parseInt(item.quantity || item.stock || 0);
                const statusColor = stock > 10 ? '#2ecc71' : (stock > 0 ? '#f1c40f' : '#e74c3c');
                const statusLabel = stock > 10 ? 'In Stock' : (stock > 0 ? 'Low Stock' : 'Out of Stock');
                const price = parseFloat(item.price || item.unit_price || 0).toLocaleString(undefined, { minimumFractionDigits: 2 });

                return `
                                <tr>
                                    <td style="font-weight: 700;">#${item.id || item.item_id || 'N/A'}</td>
                                    <td style="font-weight: 600;">📦 ${item.name || item.product_name}</td>
                                    <td>${item.category || 'General'}</td>
                                    <td style="font-weight: 700;">${stock}</td>
                                    <td style="font-weight: 700;">$${price}</td>
                                    <td><span style="color: ${statusColor}; font-weight: 600;">${statusLabel}</span></td>
                                    <td style="white-space: nowrap;">
                                        <button class="btn-view-small" onclick='showInventoryDetails(${JSON.stringify(item).replace(/'/g, "&apos;")})' title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="btn-view-small" title="Print/Export">
                                            <i class="fas fa-print"></i>
                                        </button>
                                    </td>
                                </tr>
                            `;
            }).join('')}
                        </tbody>
                    </table>
                </div>
            `;
        }

        function renderDocumentTable(data, grid) {
            const isSuperAdmin = <?php echo $isSuperAdmin ? 'true' : 'false'; ?>;
            grid.innerHTML = `
                <div class="table-container" style="grid-column: 1/-1;">
                    <table class="financial-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Category</th>
                                <th>Size</th>
                                <th>Upload Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${data.map(item => `
                                <tr>
                                    <td style="font-weight: 600; color: #1e293b;">
                                        <i class="fas fa-file-pdf" style="margin-right: 10px; color: #ef4444;"></i>${item.name}
                                    </td>
                                    <td><span style="font-size: 13px; color: #64748b;"><i class="fas fa-folder" style="margin-right: 5px;"></i> ${item.category}</span></td>
                                    <td>${item.file_size}</td>
                                    <td>${new Date(item.upload_date).toLocaleDateString()}</td>
                                    <td style="white-space: nowrap;">
                                        <button class="btn-view-small" onclick='showFileDetails(${JSON.stringify(item).replace(/'/g, "&apos;")})' title="View metadata">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <a href="?api=1&action=download&id=${encodeURIComponent(item.id)}" class="btn-view-small" title="Secure Download">
                                            <i class="fas fa-download"></i>
                                        </a>
                                        ${isSuperAdmin ? `
                                        <button class="btn-view-small" style="color: #ef4444;" onclick="deletePermanent(${item.id})" title="Wipe Data">
                                            <i class="fas fa-skull"></i>
                                        </button>
                                        ` : ''}
                                    </td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            `;
        }

        function showNoDataMessage(grid, category) {
            const icons = {
                'all': 'fas fa-layer-group',
                'HR Documents': 'fas fa-users',
                'Guest Records': 'fas fa-user-check',
                'Inventory': 'fas fa-boxes',
                'Compliance': 'fas fa-shield-alt',
                'Marketing': 'fas fa-bullhorn'
            };

            grid.innerHTML = `
                <div style="text-align: center; padding: 4rem; color: #adb5bd; grid-column: 1/-1;">
                    <i class="${icons[category] || 'fas fa-layer-group'}" style="font-size: 3rem; margin-bottom: 1.5rem;"></i>
                    <p style="font-size: 1.2rem; font-weight: 500;">No ${category.toLowerCase()} found</p>
                    <p style="font-size: 0.9rem;">Upload documents to see them here.</p>
                </div>
            `;
        }


        // Global functions for modals
        window.showFinancialDetails = function (record) {
            const modal = document.getElementById('fileDetailsModal');
            const content = document.getElementById('fileDetailsContent');

            const type = record.role || record.type || (parseFloat(record.total_credit) > 0 ? 'Income' : 'Expense');
            const typeColor = (type.toLowerCase() === 'income' || type.toLowerCase() === 'admin') ? '#2ecc71' :
                (type.toLowerCase() === 'staff' ? '#3498db' : '#e74c3c');
            const title = record.username ? `User: ${record.username}` : `Journal Entry: ${record.entry_number}`;

            content.innerHTML = `
                <div style="position: relative;">
                    <div id="financialSensitive" class="financial-details blurred-content" style="padding: 10px;">
                        <div style="text-align: center; margin-bottom: 25px; border-bottom: 2px solid #f0f0f0; padding-bottom: 15px;">
                            <div style="font-size: 3rem; color: ${typeColor};"><i class="fas ${record.username ? 'fa-user-shield' : 'fa-file-invoice-dollar'}"></i></div>
                            <h2 style="margin: 10px 0;">${title}</h2>
                            <span class="type-badge" style="background: ${typeColor}; color: white; padding: 4px 12px; border-radius: 20px;">${type.toUpperCase()}</span>
                        </div>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                            <div class="detail-item">
                                <label>${record.created_at ? 'Created At' : 'Transaction Date'}</label>
                                <div>${new Date(record.created_at || record.entry_date).toLocaleDateString('en-US', { dateStyle: 'full' })}</div>
                            </div>
                            <div class="detail-item">
                                <label>Status</label>
                                <div>${record.status}</div>
                            </div>
                            <div class="detail-item">
                                <label>${record.email ? 'Email' : 'Total Debit'}</label>
                                <div>${record.email || '$' + parseFloat(record.total_debit || 0).toLocaleString(undefined, { minimumFractionDigits: 2 })}</div>
                            </div>
                            <div class="detail-item">
                                <label>${record.phone ? 'Phone' : 'Total Credit'}</label>
                                <div>${record.phone || '$' + parseFloat(record.total_credit || 0).toLocaleString(undefined, { minimumFractionDigits: 2 })}</div>
                            </div>
                        </div>

                        <div class="detail-item" style="margin-bottom: 20px;">
                            <label>${record.full_name ? 'Full Name' : 'Description'}</label>
                            <div>${record.full_name || record.description}</div>
                        </div>
                    </div>
                    
                    <div class="reveal-overlay" id="financialReveal">
                        <button class="reveal-btn"><i class="fas fa-eye"></i> Click to Reveal Sensitive Info</button>
                    </div>
                </div>

                <div class="form-actions" style="margin-top: 30px;">
                    <button class="btn btn-primary" onclick="window.print()">
                        <i class="fas fa-print"></i> Print Record
                    </button>
                    <button class="btn close-modal">Close</button>
                </div>
            `;

            // Add reveal functionality
            const revealBtn = content.querySelector('#financialReveal');
            const sensitiveContent = content.querySelector('#financialSensitive');

            if (revealBtn && sensitiveContent) {
                revealBtn.addEventListener('click', function () {
                    this.style.display = 'none';
                    sensitiveContent.classList.remove('blurred-content');
                });
            }

            // Add close functionality
            content.querySelector('.close-modal').addEventListener('click', () => {
                modal.style.display = 'none';
            });

            modal.style.display = 'block';
        };

        window.showFileDetails = function (file) {
            const modal = document.getElementById('fileDetailsModal');
            const content = document.getElementById('fileDetailsContent');

            content.innerHTML = `
                <div style="position: relative;">
                    <div id="fileSensitive" class="blurred-content">
                        <h4 style="margin-top:0;">${file.name || 'Unnamed'}</h4>
                        <p><strong>Category:</strong> ${file.category || 'N/A'}</p>
                        <p><strong>Size:</strong> ${file.file_size || 'Unknown'}</p>
                        <p><strong>Uploaded:</strong> ${new Date(file.upload_date).toLocaleDateString()}</p>
                        ${file.description ? `<p><strong>Description:</strong> ${file.description}</p>` : ''}
                    </div>
                    <div class="reveal-overlay" id="fileReveal">
                        <button class="reveal-btn"><i class="fas fa-eye"></i> Click to Reveal Details</button>
                    </div>
                </div>
                <div style="margin-top:1rem;display:flex;gap:0.5rem;">
                    ${file.id ? `<a href="?api=1&action=download&id=${encodeURIComponent(file.id)}" class="btn btn-primary" target="_blank">Download</a>` : ''}
                    <button class="btn close-modal">Close</button>
                </div>
            `;

            // Add reveal functionality
            const revealBtn = content.querySelector('#fileReveal');
            const sensitiveContent = content.querySelector('#fileSensitive');

            if (revealBtn && sensitiveContent) {
                revealBtn.addEventListener('click', function () {
                    this.style.display = 'none';
                    sensitiveContent.classList.remove('blurred-content');
                });
            }

            // Add close functionality
            content.querySelector('.close-modal').addEventListener('click', () => {
                modal.style.display = 'none';
            });

            modal.style.display = 'block';
        };

        // Session management
        function startPinSession() {
            clearPinSession();
            pinSessionTimeout = setTimeout(() => {
                isAuthenticated = false;
                updateSecurityStatus(false);
                console.log('PIN session expired');
            }, SESSION_DURATION);
        }

        function clearPinSession() {
            if (pinSessionTimeout) {
                clearTimeout(pinSessionTimeout);
                pinSessionTimeout = null;
            }
        }

        function resetPinSession() {
            if (isAuthenticated) {
                clearPinSession();
                startPinSession();
            }
        }

        // Authentication display functions removed as status is no longer persistent

        window.showInventoryDetails = function (item) {
            const modal = document.getElementById('fileDetailsModal');
            const content = document.getElementById('fileDetailsContent');

            const stock = parseInt(item.quantity || item.stock || 0);
            const statusColor = stock > 10 ? '#2ecc71' : (stock > 0 ? '#f1c40f' : '#e74c3c');
            const statusLabel = stock > 10 ? 'In Stock' : (stock > 0 ? 'Low Stock' : 'Out of Stock');

            content.innerHTML = `
                <div style="position: relative;">
                    <div id="inventorySensitive" class="financial-details blurred-content" style="padding: 10px;">
                        <div style="text-align: center; margin-bottom: 25px; border-bottom: 2px solid #f0f0f0; padding-bottom: 15px;">
                            <div style="font-size: 3rem; color: #3b82f6;"><i class="fas fa-boxes"></i></div>
                            <h2 style="margin: 10px 0;">${item.name || item.product_name}</h2>
                            <span class="type-badge" style="background: ${statusColor}; color: white; padding: 4px 12px; border-radius: 20px;">${statusLabel}</span>
                        </div>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                            <div class="detail-item">
                                <label>Category</label>
                                <div>${item.category || 'General'}</div>
                            </div>
                            <div class="detail-item">
                                <label>Stock Level</label>
                                <div>${stock} units</div>
                            </div>
                            <div class="detail-item">
                                <label>Unit Price</label>
                                <div>$${parseFloat(item.price || item.unit_price || 0).toLocaleString(undefined, { minimumFractionDigits: 2 })}</div>
                            </div>
                            <div class="detail-item">
                                <label>Item ID</label>
                                <div>#${item.id || item.item_id || 'N/A'}</div>
                            </div>
                        </div>

                        ${item.description ? `
                        <div class="detail-item" style="margin-bottom: 20px;">
                            <label>Product Description</label>
                            <div>${item.description}</div>
                        </div>` : ''}
                    </div>
                    
                    <div class="reveal-overlay" id="inventoryReveal">
                        <button class="reveal-btn"><i class="fas fa-eye"></i> Click to Reveal Inventory Details</button>
                    </div>
                </div>

                <div class="form-actions" style="margin-top: 30px;">
                    <button class="btn btn-primary" onclick="window.print()">
                        <i class="fas fa-print"></i> Export Info
                    </button>
                    <button class="btn close-modal">Close</button>
                </div>
            `;

            // Add reveal functionality
            const revealBtn = content.querySelector('#inventoryReveal');
            const sensitiveContent = content.querySelector('#inventorySensitive');

            if (revealBtn && sensitiveContent) {
                revealBtn.addEventListener('click', function () {
                    this.style.display = 'none';
                    sensitiveContent.classList.remove('blurred-content');
                });
            }

            // Add close functionality
            content.querySelector('.close-modal').addEventListener('click', () => {
                modal.style.display = 'none';
            });

            modal.style.display = 'block';
        };

        window.deletePermanent = function (id) {
            if (!confirm('Are you sure you want to permanently delete this file? This action cannot be undone.')) return;
            fetch('?api=1', {
                method: 'DELETE',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `id=${id}`
            })
                .then(r => r.json())
                .then(res => {
                    alert(res.message);
                    loadCategoryFiles('all');
                });
        };

        // Document Search Functionality
        document.getElementById('documentSearch')?.addEventListener('input', function (e) {
            const term = e.target.value.toLowerCase();
            const rows = document.querySelectorAll('.financial-table tbody tr');
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(term) ? '' : 'none';
            });
        });

        document.getElementById('uploadBtn')?.addEventListener('click', () => {
            document.getElementById('uploadModal').style.display = 'block';
        });

        document.getElementById('uploadForm')?.addEventListener('submit', function (e) {
            e.preventDefault();
            const formData = new FormData();
            formData.append('file', document.getElementById('fileInput').files[0]);
            formData.append('name', document.getElementById('fileName').value);
            formData.append('category', document.getElementById('fileCategory').value);
            formData.append('description', document.getElementById('fileDescription').value || '');

            fetch('?api=1', {
                method: 'POST',
                body: formData
            })
                .then(r => r.json())
                .then(res => {
                    alert(res.message);
                    if (res.message.includes('successfully')) {
                        document.getElementById('uploadModal').style.display = 'none';
                        this.reset();
                        loadCategoryFiles('all');
                    }
                })
                .catch(error => {
                    console.error('Error uploading file:', error);
                    alert('An error occurred during upload.');
                });
        });

        document.getElementById('cancelUpload')?.addEventListener('click', () => {
            document.getElementById('uploadModal').style.display = 'none';
        });

        // Loading animation function
        window.runLoadingAnimation = function (callback, isRedirect = false) {
            const loader = document.getElementById('loadingOverlay');
            if (loader) {
                loader.style.display = 'block';
                loader.style.opacity = '1';

                setTimeout(() => {
                    if (callback) callback();
                    if (!isRedirect) {
                        loader.style.opacity = '0';
                        setTimeout(() => { loader.style.display = 'none'; }, 500);
                    }
                }, 2000); // 2 seconds
            } else {
                if (callback) callback();
            }
        };
    </script>
</body>

</html>