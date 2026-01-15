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
    'trash' => 0,
    'storage' => '0 B'
];
try {
    $stats['total'] = $db->query("SELECT COUNT(*) FROM documents WHERE is_deleted = 0")->fetchColumn();
    $stats['trash'] = $db->query("SELECT COUNT(*) FROM documents WHERE is_deleted = 1")->fetchColumn();
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
    <link
        href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;700&family=Montserrat:wght@300;400&display=swap"
        rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="../assets/image/logo2.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/document.css?v=6">
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
                    <img src="../assets/image/logo.png" alt="Logo"
                        style="height: 40px; vertical-align: middle;">
                </div>
                <nav>
                    <ul>
                        <li><a href="#" class="active"><i class="fas fa-home"></i> Dashboard</a></li>
                        <li><a href="../include/Settings.php"
                                onclick="window.runLoadingAnimation(() => { window.location.href = '../include/Settings.php'; }, true);"><i
                                    class="fas fa-cog"></i> Settings</a></li>
                        <li><a href="../Modules/dashboard.php"
                                onclick="window.runLoadingAnimation(() => { window.location.href = '../Modules/dashboard.php'; }, true);"><i
                                    class="fas fa-arrow-left"></i> Back</a></li>
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
                    <li><a href="#" class="category-link" data-category="Financial Records"><i
                                class="fas fa-dollar-sign"></i> Financial Records</a></li>
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
                <div class="sidebar-footer">
                    <div class="security-status">
                        <i class="fas fa-lock" id="securityIcon"></i>
                        <span id="securityStatus">Secured</span>
                    </div>
                </div>
            </aside>

            <div class="content">
                <div class="content-header">
                    <h2 id="contentTitle">Archive Management</h2>
                    <div class="search-container" style="display: flex; gap: 10px;">
                        <input type="text" id="documentSearch" placeholder="Search in this view..."
                            style="padding: 8px 15px; border-radius: 20px; border: 1px solid #e2e8f0; width: 250px;">
                        <button class="btn btn-primary" id="uploadBtn" style="padding: 8px 20px; border-radius: 20px;">
                            <i class="fas fa-plus"></i> Upload
                        </button>
                    </div>
                </div>

                <!-- Success/Error Messages -->
                <div id="messageContainer"></div>

                <!-- All Documents View -->
                <div class="category-content active" id="all-content">
                    <div class="tabs">
                        <button class="tab active" data-tab="active">Active Files</button>
                        <button class="tab" data-tab="trash">Trash Bin</button>
                    </div>
                    <div class="tab-content active" id="active-tab">
                        <div class="file-grid" id="activeFiles">
                            <!-- Active files will be populated here -->
                        </div>
                    </div>
                    <div class="tab-content" id="trash-tab">
                        <div class="file-grid" id="trashFiles">
                            <!-- Trash files will be populated here -->
                        </div>
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
            <h2>Archive Security</h2>
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

    <!-- Dashboard Section -->
    <section class="dashboard-section">
        <div class="container">
            <div class="dashboard-header">
                <h2><i class="fas fa-chart-line"></i> Document Dashboard</h2>
                <p>Real-time insights and analytics for your document management system</p>
            </div>

            <div class="dashboard-grid">
                <!-- Total Documents Card -->
                <div class="dashboard-card">
                    <div class="card-icon icon-blue">
                        <i class="fas fa-file-alt" style="color: white; font-size: 1.5rem;"></i>
                    </div>
                    <h3><?php echo number_format($stats['total']); ?></h3>
                    <p>Active Documents</p>
                    <button class="btn btn-primary" style="width: 100%;"
                        onclick="document.querySelector('[data-category=\'all\']').click()">
                        <i class="fas fa-eye"></i> View All
                    </button>
                </div>

                <!-- Trash Card -->
                <div class="dashboard-card">
                    <div class="card-icon icon-red">
                        <i class="fas fa-trash-alt" style="color: white; font-size: 1.5rem;"></i>
                    </div>
                    <h3><?php echo number_format($stats['trash']); ?></h3>
                    <p>Files in Trash</p>
                    <button class="btn btn-danger" style="width: 100%;"
                        onclick="document.querySelector('[data-category=\'all\']').click(); setTimeout(() => document.querySelector('[data-tab=\'trash\']').click(), 100);">
                        <i class="fas fa-recycle"></i> Manage Trash
                    </button>
                </div>

                <!-- Storage Used Card -->
                <div class="dashboard-card">
                    <div class="card-icon icon-green">
                        <i class="fas fa-database" style="color: white; font-size: 1.5rem;"></i>
                    </div>
                    <h3><?php echo $stats['storage']; ?></h3>
                    <p>Storage Used</p>
                    <button class="btn btn-success" style="width: 100%;">
                        <i class="fas fa-chart-pie"></i> Analytics
                    </button>
                </div>

                <!-- Categories Card -->
                <div class="dashboard-card">
                    <div class="card-icon icon-gray">
                        <i class="fas fa-folder-open" style="color: #333; font-size: 1.5rem;"></i>
                    </div>
                    <h3>6</h3>
                    <p>Protected Categories</p>
                    <button class="btn btn-secondary" style="width: 100%;"
                        onclick="document.querySelector('.sidebar').scrollIntoView({behavior:'smooth'})">
                        <i class="fas fa-list"></i> View Categories
                    </button>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="quick-actions">
                <h3>Quick Actions</h3>
                <div class="quick-actions-grid">
                    <button class="btn btn-white" id="quickUpload"><i class="fas fa-upload"></i> Upload
                        Document</button>
                    <button class="btn btn-white" id="quickSearch"><i class="fas fa-search"></i> Search Files</button>
                    <button class="btn btn-white" id="quickExport"><i class="fas fa-download"></i> Export
                        Report</button>
                    <button class="btn btn-white" id="quickSettings"><i class="fas fa-cog"></i> Security
                        Settings</button>
                </div>
            </div>
        </div>
    </section>

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
            updateSecurityStatus(false);
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

                    if (!isAuthenticated && category !== 'all') {
                        targetCategory = category;
                        showPinGate();
                    } else {
                        switchCategory(this, category);
                    }
                });
            });

            // Tab Navigation
            document.querySelectorAll('.tab').forEach(tab => {
                tab.addEventListener('click', function () {
                    const tabId = this.getAttribute('data-tab');
                    document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
                    document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
                    this.classList.add('active');
                    document.getElementById(`${tabId}-tab`).classList.add('active');

                    if (tabId === 'trash') {
                        loadTrashFiles();
                    } else if (tabId === 'active') {
                        loadCategoryFiles('all');
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

            // PIN Form submission
            pinForm.addEventListener('submit', function (e) {
                e.preventDefault();
                const enteredPin = Array.from(archivePinDigits).map(input => input.value).join('');

                if (enteredPin === correctArchivePin) {
                    isAuthenticated = true;
                    startPinSession();
                    updateSecurityStatus(true);
                    passwordModal.style.display = 'none';
                    pinErrorMessage.style.display = 'none';

                    if (targetCategory) {
                        const activeLink = document.querySelector(`.category-link[data-category="${targetCategory}"]`);
                        if (activeLink) switchCategory(activeLink, targetCategory);
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
                    isAuthenticated = true; // Temporary permit for search
                    updateSecurityStatus(true);

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

        function showPinGate() {
            passwordModal.style.display = 'flex';
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
                'Inventory': '../integ/inventory_fn.php',
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

            // Try local API first
            fetch('../integ/fn.php')
                .then(response => response.json())
                .then(result => {
                    const data = (result.success && result.data && result.data.length > 0) ? result.data : fallbackData;
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
                    const type = record.type || (parseFloat(record.total_credit) > 0 ? 'Income' : 'Expense');
                    const typeColor = type.toLowerCase() === 'income' ? '#2ecc71' : '#e74c3c';
                    const safeRecord = JSON.stringify(record).replace(/'/g, "&apos;");
                    const formattedDate = new Date(record.entry_date).toLocaleDateString('en-US', {
                        year: 'numeric',
                        month: '2-digit',
                        day: '2-digit'
                    });
                    const amountValue = parseFloat(record.total_debit || record.amount || 0)
                        .toLocaleString(undefined, {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2
                        });

                    return `
                                        <tr>
                                            <td style="white-space: nowrap;">${formattedDate}</td>
                                            <td><span style="color: ${typeColor};" class="type-label">${type}</span></td>
                                            <td>${record.category || 'Revenue'}</td>
                                            <td style="min-width: 200px;">${record.description}</td>
                                            <td style="font-weight: 700; white-space: nowrap;">$${amountValue}</td>
                                            <td>${record.venue || 'Hotel'}</td>
                                            <td>
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
                        renderDocumentTable(data.data, grid);
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

        function renderDocumentTable(data, grid) {
            grid.innerHTML = `
                <div class="financial-table-container" style="grid-column: 1/-1;">
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
                                    <td style="font-weight: 600;">📄 ${item.name}</td>
                                    <td>${item.category}</td>
                                    <td>${item.file_size}</td>
                                    <td>${new Date(item.upload_date).toLocaleDateString()}</td>
                                    <td>
                                        <button class="btn-view-small" onclick='showFileDetails(${JSON.stringify(item).replace(/'/g, "&apos;")})' title="View">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <a href="?api=1&action=download&id=${encodeURIComponent(item.id)}" class="btn-view-small" title="Download">
                                            <i class="fas fa-download"></i>
                                        </a>
                                        <button class="btn-view-small" style="color: #ef4444;" onclick="moveToTrash(${item.id})" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
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

            const type = record.type || (parseFloat(record.total_credit) > 0 ? 'Income' : 'Expense');
            const typeColor = type.toLowerCase() === 'income' ? '#2ecc71' : '#e74c3c';

            content.innerHTML = `
                <div style="position: relative;">
                    <div id="financialSensitive" class="financial-details blurred-content" style="padding: 10px;">
                        <div style="text-align: center; margin-bottom: 25px; border-bottom: 2px solid #f0f0f0; padding-bottom: 15px;">
                            <div style="font-size: 3rem; color: ${typeColor};"><i class="fas fa-file-invoice-dollar"></i></div>
                            <h2 style="margin: 10px 0;">Journal Entry: ${record.entry_number}</h2>
                            <span class="type-badge" style="background: ${typeColor}; color: white; padding: 4px 12px; border-radius: 20px;">${type.toUpperCase()}</span>
                        </div>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                            <div class="detail-item">
                                <label>Transaction Date</label>
                                <div>${new Date(record.entry_date).toLocaleDateString('en-US', { dateStyle: 'full' })}</div>
                            </div>
                            <div class="detail-item">
                                <label>Status</label>
                                <div>${record.status}</div>
                            </div>
                            <div class="detail-item">
                                <label>Total Debit</label>
                                <div>$${parseFloat(record.total_debit).toLocaleString(undefined, { minimumFractionDigits: 2 })}</div>
                            </div>
                            <div class="detail-item">
                                <label>Total Credit</label>
                                <div>$${parseFloat(record.total_credit).toLocaleString(undefined, { minimumFractionDigits: 2 })}</div>
                            </div>
                        </div>

                        <div class="detail-item" style="margin-bottom: 20px;">
                            <label>Description</label>
                            <div>${record.description}</div>
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

        function updateSecurityStatus(authenticated) {
            const securityStatus = document.getElementById('securityStatus');
            const securityIcon = document.getElementById('securityIcon');
            const statusContainer = document.querySelector('.security-status');

            if (authenticated) {
                securityStatus.textContent = 'Authenticated';
                securityIcon.className = 'fas fa-unlock';
                statusContainer.classList.remove('locked');
            } else {
                securityStatus.textContent = 'Secured';
                securityIcon.className = 'fas fa-lock';
                statusContainer.classList.add('locked');
            }
        }

        // Trash Management Functions
        function loadTrashFiles() {
            fetch('?api=1&action=deleted')
                .then(r => r.json())
                .then(data => {
                    const grid = document.getElementById('trashFiles');
                    if (!data || data.length === 0) {
                        grid.innerHTML = `<div style="text-align: center; padding: 4rem; color: #64748b; grid-column: 1/-1;">Trash bin is empty</div>`;
                        return;
                    }
                    renderTrashTable(data, grid);
                });
        }

        function renderTrashTable(data, grid) {
            grid.innerHTML = `
                <div class="financial-table-container" style="grid-column: 1/-1;">
                    <table class="financial-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Deleted Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${data.map(item => `
                                <tr>
                                    <td>📄 ${item.name}</td>
                                    <td>${new Date(item.deleted_date).toLocaleDateString()}</td>
                                    <td>
                                        <button class="btn-view-small" onclick="restoreFile(${item.id})" style="color: #22c55e;" title="Restore">
                                            <i class="fas fa-undo"></i>
                                        </button>
                                        <button class="btn-view-small" onclick="deletePermanent(${item.id})" style="color: #ef4444;" title="Delete Permanently">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            `;
        }

        window.moveToTrash = function (id) {
            if (!confirm('Move this document to trash?')) return;
            const formData = new FormData();
            formData.append('action', 'trash');
            formData.append('id', id);
            fetch('?api=1', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(res => {
                    alert(res.message);
                    loadCategoryFiles('all');
                });
        };

        window.restoreFile = function (id) {
            const formData = new FormData();
            formData.append('action', 'restore');
            formData.append('id', id);
            fetch('?api=1', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(res => {
                    alert(res.message);
                    loadTrashFiles();
                });
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
                    loadTrashFiles();
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