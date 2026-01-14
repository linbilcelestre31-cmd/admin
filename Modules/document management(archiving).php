<?php
// Database Configuration
class Database
{
    private $host = "localhost";
    private $db_name = "legalmanagement";
    private $username = "root";
    private $password = "";
    public $conn;

    public function getConnection()
    {
        $this->conn = null;
        try {
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name, $this->username, $this->password);
            $this->conn->exec("set names utf8");
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $exception) {
            echo "Connection error: " . $exception->getMessage();
        }
        return $this->conn;
    }
}

// File upload configuration
define('UPLOAD_DIR', 'uploads/');
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
        $this->file_size = htmlspecialchars(strip_tags($this->file_size));
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
            $query .= " AND category = '" . htmlspecialchars($category) . "'";
        }
        $query .= " ORDER BY upload_date DESC";
        $stmt = $this->conn->prepare($query);
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

        if ($row) {
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

    $database = new Database();
    $db = $database->getConnection();
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
            break;

        case 'POST':
            // Handle file upload
            if (isset($_FILES['file'])) {
                $response = uploadFile();
                if ($response['success']) {
                    $document->name = $_POST['name'];
                    $document->category = $_POST['category'];
                    $document->file_path = $response['file_path'];
                    $document->file_size = $response['file_size'];
                    $document->description = $_POST['description'];
                    $document->upload_date = date('Y-m-d');

                    if ($document->create()) {
                        echo json_encode(["message" => "Document uploaded successfully."]);
                    } else {
                        echo json_encode(["message" => "Unable to upload document."]);
                    }
                } else {
                    echo json_encode(["message" => $response['message']]);
                }
            }
            // Move to trash
            elseif (isset($_POST['action']) && $_POST['action'] == 'trash') {
                $document->id = $_POST['id'];
                $document->deleted_date = date('Y-m-d');
                if ($document->moveToTrash()) {
                    echo json_encode(["message" => "Document moved to trash."]);
                } else {
                    echo json_encode(["message" => "Unable to move document to trash."]);
                }
            }
            // Restore from trash
            elseif (isset($_POST['action']) && $_POST['action'] == 'restore') {
                $document->id = $_POST['id'];
                if ($document->restore()) {
                    echo json_encode(["message" => "Document restored successfully."]);
                } else {
                    echo json_encode(["message" => "Unable to restore document."]);
                }
            }
            break;

        case 'DELETE':
            parse_str(file_get_contents("php://input"), $delete_vars);
            $document->id = $delete_vars['id'];
            if ($document->deletePermanent()) {
                echo json_encode(["message" => "Document permanently deleted."]);
            } else {
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

    $file = $_FILES['file'];
    $file_name = basename($file['name']);
    $file_tmp = $file['tmp_name'];
    $file_size = $file['size'];
    $file_error = $file['error'];

    // Check for errors
    if ($file_error !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'File upload error.'];
    }

    // Check file size
    if ($file_size > MAX_FILE_SIZE) {
        return ['success' => false, 'message' => 'File is too large.'];
    }

    // Check file type
    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    if (!in_array($file_ext, ALLOWED_TYPES)) {
        return ['success' => false, 'message' => 'File type not allowed.'];
    }

    // Generate unique filename
    $new_filename = uniqid() . '_' . time() . '.' . $file_ext;
    $file_path = $upload_dir . $new_filename;

    // Move file to upload directory
    if (move_uploaded_file($file_tmp, $file_path)) {
        return [
            'success' => true,
            'file_path' => $file_path,
            'file_size' => formatFileSize($file_size)
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
    <title>Loading - Atiéra</title>
    <link
        href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;700&family=Montserrat:wght@300;400&display=swap"
        rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="../assets/image/logo2.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/document.css?v=<?php echo time(); ?>">

    <style>
        /* Updated Financial Table Styles to match image and prevent squashing */
        .financial-table-container {
            width: 100%;
            overflow-x: auto;
            margin-top: 10px;
        }

        .financial-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.95rem;
        }

        .financial-table th {
            text-align: center;
            padding: 15px 12px;
            color: #fff;
            font-weight: 700;
            border-bottom: 2px solid #f1f5f9;
            white-space: nowrap;
            background-color: black;
        }

        .financial-table td {
            padding: 16px 12px;
            border-bottom: 1px solid #f8fafc;
            vertical-align: middle;
            color: #475569;
            line-height: 1.5;
            text-align: center;
        }

        .type-label {
            font-weight: 600;
            margin-left: 4px;
        }

        .btn-view-small {
            background: #fff;
            border: 1px solid #ccc;
            padding: 4px 8px;
            border-radius: 4px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 0.85rem;
            color: #333;
        }

        .btn-view-small i {
            font-size: 0.8rem;
        }

        .btn-view-small:hover {
            background: #f0f0f0;
        }

        /* PIN Security Styles */
        .pin-digit {
            width: 55px;
            height: 55px;
            margin: 0 6px;
            text-align: center;
            font-size: 26px;
            font-weight: 600;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            outline: none;
            transition: all 0.25s ease;
            background: #f8fafc;
            color: #0f172a;
        }

        .pin-digit:focus {
            border-color: #4a6cf7;
            background: #ffffff;
            box-shadow: 0 0 0 4px rgba(74, 108, 247, 0.1);
        }

        #passwordModal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(2, 6, 23, 0.4);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            align-items: center;
            justify-content: center;
            z-index: 99999;
        }

        .pin-container {
            background: #ffffff;
            width: 92%;
            max-width: 440px;
            border-radius: 32px;
            padding: 40px 30px;
            position: relative;
            box-shadow: 0 30px 80px rgba(2, 6, 23, 0.3);
            border: 1px solid #e2e8f0;
            text-align: center;
        }

        .pin-container h2 {
            margin: 0 0 10px;
            font-weight: 800;
            color: #0f172a;
            letter-spacing: -0.5px;
        }

        .pin-container p {
            margin: 0 0 30px;
            color: #64748b;
        }

        /* Shake animation for wrong PIN */
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            10%, 30%, 50%, 70%, 90% { transform: translateX(-10px); }
            20%, 40%, 60%, 80% { transform: translateX(10px); }
        }

        /* Enhanced Sidebar Styles */
        .sidebar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 0 15px 0;
            border-bottom: 2px solid #e2e8f0;
            margin-bottom: 20px;
        }

        .sidebar-header h3 {
            margin: 0;
            color: #1e293b;
            font-size: 1.1rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .sidebar-toggle {
            background: none;
            border: none;
            color: #64748b;
            cursor: pointer;
            padding: 8px;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .sidebar-toggle:hover {
            background: #f1f5f9;
            color: #1e293b;
        }

        .sidebar-menu {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .sidebar-menu li {
            margin-bottom: 4px;
        }

        .sidebar-menu a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            color: #475569;
            text-decoration: none;
            border-radius: 10px;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .sidebar-menu a:hover {
            background: #f8fafc;
            color: #1e293b;
            transform: translateX(4px);
        }

        .sidebar-menu a.active {
            background: linear-gradient(135deg, #4a6cf7, #6366f1);
            color: white;
            box-shadow: 0 4px 12px rgba(74, 108, 247, 0.3);
        }

        .sidebar-menu i {
            width: 20px;
            text-align: center;
            font-size: 0.9rem;
        }

        .sidebar-footer {
            margin-top: auto;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
        }

        .security-status {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 12px;
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-radius: 8px;
            font-size: 0.85rem;
            color: #166534;
            font-weight: 500;
        }

        .security-status.locked {
            background: #fef2f2;
            border-color: #fecaca;
            color: #991b1b;
        }

        .security-status i {
            font-size: 0.8rem;
        }

        /* Mobile responsive sidebar */
        @media (max-width: 768px) {
            .sidebar {
                position: fixed;
                left: -280px;
                top: 0;
                height: 100vh;
                width: 280px;
                z-index: 1000;
                transition: left 0.3s ease;
                background: white;
                box-shadow: 2px 0 10px rgba(0,0,0,0.1);
            }

            .sidebar.open {
                left: 0;
            }

            .sidebar-toggle {
                display: block;
            }
        }

        @media (min-width: 769px) {
            .sidebar-toggle {
                display: none;
            }
        }
    </style>
</head>

<body>
    <header>
        <div class="container">
            <div class="header-content">
                <div class="logo">Hotel<span>Archive</span></div>
                <nav>
                    <ul>
                        <li><a href="#" class="active">Dashboard</a></li>
                        <li><a href="#">Settings</a></li>
                        <li><a href="#">Help</a></li>
                        <li><a href="../Modules/facilities-reservation.php" onclick="window.runLoadingAnimation(() => { window.location.href = '../Modules/facilities-reservation.php'; }, true);">Back</a></li>
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
                    <li><a href="#" class="category-link active" data-category="all"><i class="fas fa-layer-group"></i> All Documents</a></li>
                    <li><a href="#" class="category-link" data-category="Financial Records"><i class="fas fa-dollar-sign"></i> Financial Records</a></li>
                    <li><a href="#" class="category-link" data-category="HR Documents"><i class="fas fa-users"></i> HR Documents</a></li>
                    <li><a href="#" class="category-link" data-category="Guest Records"><i class="fas fa-user-check"></i> Guest Records</a></li>
                    <li><a href="#" class="category-link" data-category="Inventory"><i class="fas fa-boxes"></i> Inventory</a></li>
                    <li><a href="#" class="category-link" data-category="Compliance"><i class="fas fa-shield-alt"></i> Compliance</a></li>
                    <li><a href="#" class="category-link" data-category="Marketing"><i class="fas fa-bullhorn"></i> Marketing</a></li>
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
                    <h2 id="contentTitle">Document Management</h2>
                </div>

                <!-- All Documents View -->
                <div class="category-content active" id="all-content">
                    <div class="tabs">
                        <div class="tab active" data-tab="active">Active Files</div>
                    </div>
                    <div class="tab-content active" id="active-tab">

                        <div class="file-grid" id="activeFiles"><!-- Active files will be populated here --></div>
                    </div>
                    <div class="tab-content" id="trash-tab">
                        <div class="file-grid" id="trashFiles"><!-- Trash files will be populated here --></div>
                    </div>
                </div>

                <!-- Financial Records View -->
                <div class="category-content" id="financial-records-content">

                    <div id="financialFiles"><!-- Financial records table will be populated here --></div>
                </div>

                <!-- HR Documents View -->
                <div class="category-content" id="hr-documents-content">

                    <div class="file-grid" id="hrFiles"><!-- HR files will be populated here --></div>
                </div>

                <!-- Guest Records View -->
                <div class="category-content" id="guest-records-content">

                    <div class="file-grid" id="guestFiles"><!-- Guest files will be populated here --></div>
                </div>

                <!-- Inventory View -->
                <div class="category-content" id="inventory-content">

                    <div class="file-grid" id="inventoryFiles"><!-- Inventory files will be populated here --></div>
                </div>

                <!-- Compliance View -->
                <div class="category-content" id="compliance-content">

                    <div class="file-grid" id="complianceFiles"><!-- Compliance files will be populated here -->
                    </div>
                </div>

                <!-- Marketing View -->
                <div class="category-content" id="marketing-content">

                    <div class="file-grid" id="marketingFiles"><!-- Marketing files will be populated here --></div>
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

    <!-- PIN Security Modal (SARILING PIN SECURITY) -->
    <div id="passwordModal">
        <div class="pin-container">
            <div style="margin-bottom: 25px;">
                <img src="../assets/image/logo.png" alt="Logo" style="width: 150px; height: auto;">
            </div>
            <h2>Archive Security</h2>
            <p>Enter your PIN to access this category</p>
            <form id="pinForm">
                <div style="display: flex; justify-content: center; margin-bottom: 30px;">
                    <input type="password" maxlength="1" class="pin-digit" id="archivePin1" required autofocus>
                    <input type="password" maxlength="1" class="pin-digit" id="archivePin2" required>
                    <input type="password" maxlength="1" class="pin-digit" id="archivePin3" required>
                    <input type="password" maxlength="1" class="pin-digit" id="archivePin4" required>
                </div>
                <div id="pinErrorMessage"
                    style="color: #ef4444; font-size: 0.9rem; margin-top: -20px; margin-bottom: 20px; display: none; font-weight: 600;">
                    Incorrect PIN. Access Denied.
                </div>
                <div style="display: flex; gap: 12px; justify-content: center;">
                    <button type="button" class="btn" id="pinCancelBtn"
                        style="padding: 12px 24px; border-radius: 12px;">Cancel</button>
                    <button type="submit" class="btn btn-primary"
                        style="padding: 12px 24px; border-radius: 12px; background: #4a6cf7;">Access Archive</button>
                </div>
            </form>
        </div>
    </div>

    <footer class="container">
        <p>Hotel & Restaurant Document Management System &copy; 2023</p>
    </footer>
    </div>
    </div>
    <script src="../assets/Javascript/document.js"></script>
    <script>
        // Add dummy/sample data for testing if API fails
        function loadDummyData(category) {
            return [];
        }


        // Category Navigation Handler with PIN Protection
        let targetCategory = null;
        let isAuthenticated = false;

        document.querySelectorAll('.category-link').forEach(link => {
            link.addEventListener('click', function (e) {
                e.preventDefault();
                targetCategory = this.getAttribute('data-category');

                if (!isAuthenticated && targetCategory !== 'all') {
                    showPinGate();
                } else {
                    switchCategory(this, targetCategory);
                }
            });
        });

        function showPinGate() {
            document.getElementById('passwordModal').style.display = 'flex';
            document.querySelectorAll('.pin-digit').forEach(input => input.value = '');
            document.getElementById('archivePin1').focus();
            document.getElementById('pinErrorMessage').style.display = 'none';
        }

        function switchCategory(linkElement, category) {
            const categoryNames = {
                'all': 'Document Management',
                'Financial Records': 'Financial Records',
                'HR Documents': 'HR Documents',
                'Guest Records': 'Guest Records',
                'Inventory': 'Inventory',
                'Compliance': 'Compliance',
                'Marketing': 'Marketing',
                'trash': 'Trash Bin'
            };

            // Update active state in sidebar
            document.querySelectorAll('.category-link').forEach(l => l.classList.remove('active'));
            linkElement.classList.add('active');

            // Update page title
            document.getElementById('contentTitle').textContent = categoryNames[category] || 'Document Management';

            // Hide all category contents and show selected
            document.querySelectorAll('.category-content').forEach(content => {
                content.classList.remove('active');
            });

            let contentId;
            switch (category) {
                case 'all': contentId = 'all-content'; break;
                case 'trash': contentId = 'trash-content'; break;
                case 'Financial Records': contentId = 'financial-records-content'; break;
                case 'HR Documents': contentId = 'hr-documents-content'; break;
                case 'Guest Records': contentId = 'guest-records-content'; break;
                case 'Inventory': contentId = 'inventory-content'; break;
                case 'Compliance': contentId = 'compliance-content'; break;
                case 'Marketing': contentId = 'marketing-content'; break;
                default: contentId = 'all-content';
            }
            const contentEl = document.getElementById(contentId);
            if (contentEl) {
                contentEl.classList.add('active');
            }

            // Load files for this category
            loadCategoryFiles(category);
        }

        // PIN Gate Logic with Session Management
        const archivePinDigits = document.querySelectorAll('.pin-digit');
        const correctArchivePin = '1234';
        let pinSessionTimeout = null;
        const SESSION_DURATION = 15 * 60 * 1000; // 15 minutes

        function startPinSession() {
            clearPinSession();
            pinSessionTimeout = setTimeout(() => {
                isAuthenticated = false;
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
                startPinSession();
            }
        }

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

        document.getElementById('pinForm').addEventListener('submit', function (e) {
            e.preventDefault();
            const enteredPin = Array.from(archivePinDigits).map(input => input.value).join('');

            if (enteredPin === correctArchivePin) {
                isAuthenticated = true;
                startPinSession();
                updateSecurityStatus(true);
                document.getElementById('passwordModal').style.display = 'none';
                if (targetCategory) {
                    const activeLink = document.querySelector(`.category-link[data-category="${targetCategory}"]`);
                    if (activeLink) switchCategory(activeLink, targetCategory);
                }
            } else {
                document.getElementById('pinErrorMessage').style.display = 'block';
                archivePinDigits.forEach(input => input.value = '');
                archivePinDigits[0].focus();
                // Add shake animation for wrong PIN
                document.querySelector('.pin-container').style.animation = 'shake 0.5s';
                setTimeout(() => {
                    document.querySelector('.pin-container').style.animation = '';
                }, 500);
            }
        });

        document.getElementById('pinCancelBtn').addEventListener('click', () => {
            document.getElementById('passwordModal').style.display = 'none';
        });

        // Function to load files by category
        function loadCategoryFiles(category) {
            const endpoint = category === 'trash' ?
                '?api=1&action=deleted' :
                category === 'all' ?
                    '?api=1&action=active' :
                    '?api=1&action=active&category=' + encodeURIComponent(category);

            const gridId = {
                'all': 'activeFiles',
                'Financial Records': 'financialFiles',
                'HR Documents': 'hrFiles',
                'Guest Records': 'guestFiles',
                'Inventory': 'inventoryFiles',
                'Compliance': 'complianceFiles',
                'Marketing': 'marketingFiles',
                'trash': 'allTrashFiles'
            }[category];

            // Special handling for Financial Records - fetch from external API
            if (category === 'Financial Records') {
                const grid = document.getElementById(gridId);
                grid.innerHTML = '<div style="text-align: center; padding: 3rem; color: #666; grid-column: 1/-1;"><p>⏳ Loading financial records...</p></div>';

                const renderFinancialTable = (data) => {
                    const tableContainer = document.getElementById(gridId);
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
                        const formattedDate = new Date(record.entry_date).toLocaleDateString('en-US', { year: 'numeric', month: '2-digit', day: '2-digit' });
                        const amountValue = parseFloat(record.total_debit || record.amount || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });

                        return `
                                        <tr>
                                            <td style="white-space: nowrap;">${formattedDate}</td>
                                            <td><span style="color: ${typeColor};" class="type-label">${type}</span></td>
                                            <td>${record.category || 'Revenue'}</td>
                                            <td style="min-width: 200px;">${record.description}</td>
                                            <td style="font-weight: 700; white-space: nowrap;">$${amountValue}</td>
                                            <td>${record.venue || 'Hotel'}</td>
                                            <td>
                                                <button class="btn-view-small" onclick='showFinancialDetails(${safeRecord})'>
                                                    <i class="fas fa-eye"></i> View
                                                </button>
                                            </td>
                                        </tr>
                                    `}).join('')}
                                </tbody>
                            </table>
                        </div>
                    `;
                };

                // Detailed view for financial records
                window.showFinancialDetails = function (record) {
                    const modal = document.getElementById('fileDetailsModal');
                    const content = document.getElementById('fileDetailsContent');

                    const type = record.type || (parseFloat(record.total_credit) > 0 ? 'Income' : 'Expense');
                    const typeColor = type.toLowerCase() === 'income' ? '#2ecc71' : '#e74c3c';

                    content.innerHTML = `
                        <div class="financial-details" style="padding: 10px;">
                            <div style="text-align: center; margin-bottom: 25px; border-bottom: 2px solid #f0f0f0; padding-bottom: 15px;">
                                <div style="font-size: 3rem; color: ${typeColor};"><i class="fas fa-file-invoice-dollar"></i></div>
                                <h2 style="margin: 10px 0;">Journal Entry: ${record.entry_number}</h2>
                                <span class="type-badge" style="background: ${typeColor}; color: white; padding: 4px 12px; border-radius: 20px;">${type.toUpperCase()}</span>
                            </div>
                            
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                                <div class="detail-item">
                                    <label style="display: block; font-weight: 600; color: #7f8c8d; font-size: 0.8rem; text-transform: uppercase;">Transaction Date</label>
                                    <div style="font-size: 1.1rem;">${new Date(record.entry_date).toLocaleDateString('en-US', { dateStyle: 'full' })}</div>
                                </div>
                                <div class="detail-item">
                                    <label style="display: block; font-weight: 600; color: #7f8c8d; font-size: 0.8rem; text-transform: uppercase;">Status</label>
                                    <div style="font-size: 1.1rem; text-transform: capitalize;">${record.status}</div>
                                </div>
                                <div class="detail-item">
                                    <label style="display: block; font-weight: 600; color: #7f8c8d; font-size: 0.8rem; text-transform: uppercase;">Total Debit</label>
                                    <div style="font-size: 1.2rem; font-weight: bold; color: #2c3e50;">$${parseFloat(record.total_debit).toLocaleString(undefined, { minimumFractionDigits: 2 })}</div>
                                </div>
                                <div class="detail-item">
                                    <label style="display: block; font-weight: 600; color: #7f8c8d; font-size: 0.8rem; text-transform: uppercase;">Total Credit</label>
                                    <div style="font-size: 1.2rem; font-weight: bold; color: #2c3e50;">$${parseFloat(record.total_credit).toLocaleString(undefined, { minimumFractionDigits: 2 })}</div>
                                </div>
                            </div>

                            <div class="detail-item" style="margin-bottom: 20px;">
                                <label style="display: block; font-weight: 600; color: #7f8c8d; font-size: 0.8rem; text-transform: uppercase;">Description</label>
                                <div style="font-size: 1rem; background: #f9f9f9; padding: 10px; border-radius: 6px; border-left: 4px solid #3498db;">${record.description}</div>
                            </div>

                            <div class="form-actions" style="margin-top: 30px;">
                                <button class="btn btn-primary" onclick="window.print()" style="background: #34495e;">
                                    <i class="fas fa-print"></i> Print Record
                                </button>
                                <button class="btn" onclick="document.getElementById('fileDetailsModal').style.display='none'">Close</button>
                            </div>
                        </div>
                    `;
                    modal.style.display = 'flex';
                };

                const fallbackData = [
                    { entry_date: '2025-10-24', type: 'Income', category: 'Room Revenue', description: 'Room 101 - Check-out payment', amount: 5500.00, venue: 'Hotel', total_debit: 5500, total_credit: 0, status: 'posted', entry_number: 'JE-001' },
                    { entry_date: '2025-10-24', type: 'Income', category: 'Food Sales', description: 'Restaurant Dinner Service', amount: 1250.75, venue: 'Restaurant', total_debit: 1250.75, total_credit: 0, status: 'posted', entry_number: 'JE-002' },
                    { entry_date: '2025-10-24', type: 'Expense', category: 'Payroll', description: 'October Staff Payroll', amount: 45000.00, venue: 'General', total_debit: 0, total_credit: 45000, status: 'posted', entry_number: 'JE-003' },
                    { entry_date: '2025-10-23', type: 'Expense', category: 'Utilities', description: 'Electricity bill', amount: 8500.00, venue: 'Hotel', total_debit: 0, total_credit: 8500, status: 'posted', entry_number: 'JE-004' },
                    { entry_date: '2025-10-23', type: 'Income', category: 'Event Booking', description: 'Grand Ballroom Wedding Deposit', amount: 15000.00, venue: 'Hotel', total_debit: 15000, total_credit: 0, status: 'posted', entry_number: 'JE-005' }
                ];

                /* 
                // FUTURE INTEGRATION: Fetch from the local API created in integ/fn.php
                // Link: ../integ/fn.php
                
                fetch('../integ/fn.php') // Use the local integration endpoint
                    .then(response => response.json())
                    .then(result => {
                        if (result.success && result.data && result.data.length > 0) {
                            renderFinancialTable(result.data);
                        } else {
                            renderFinancialTable(fallbackData);
                        }
                    })
                    .catch(error => {
                        console.error('API Error:', error);
                        renderFinancialTable(fallbackData);
                    });
                */

                // Currently fetching from external/mock API for demonstration
                // To switch to internal integration, uncomment the block above and adjust the endpoint
                fetch('https://financial.atierahotelandrestaurant.com/journal_entries_api')
                    .then(response => response.json())
                    .then(result => {
                        if (result.success && result.data && result.data.length > 0) {
                            renderFinancialTable(result.data);
                        } else {
                            renderFinancialTable(fallbackData);
                        }
                    })
                    .catch(error => {
                        console.error('API Error:', error);
                        renderFinancialTable(fallbackData);
                    });
                return;
            }

            // Placeholder for HR Documents Integration
            if (category === 'HR Documents') {
                /*
                const grid = document.getElementById(gridId);
                // FUTURE: Link your HR API here (e.g., ../integ/hr_fn.php)
                fetch('../integ/hr_fn.php')
                    .then(response => response.json())
                    .then(data => {
                        // RENDER LOGIC HERE
                    });
                return;
                */
            }

            // Placeholder for Guest Records Integration
            if (category === 'Guest Records') {
                /*
                const grid = document.getElementById(gridId);
                // FUTURE: Link your Guest Records API here
                fetch('../integ/guest_fn.php')
                    .then(response => response.json())
                    .then(data => {
                        // RENDER LOGIC HERE
                    });
                return;
                */
            }

            // Placeholder for Compliance Integration
            if (category === 'Compliance') {
                /*
                const grid = document.getElementById(gridId);
                // FUTURE: Link your Compliance API here
                fetch('../integ/compliance_fn.php')
                    .then(response => response.json())
                    .then(data => {
                        // RENDER LOGIC HERE
                    });
                return;
                */
            }

            // Placeholder for Marketing Integration
            if (category === 'Marketing') {
                /*
                const grid = document.getElementById(gridId);
                // FUTURE: Link your Marketing API here
                fetch('../integ/marketing_fn.php')
                    .then(response => response.json())
                    .then(data => {
                        // RENDER LOGIC HERE
                    });
                return;
                */
            }

            // Placeholder for Inventory Integration
            if (category === 'Inventory') {
                /*
                const grid = document.getElementById(gridId);
                // FUTURE: Link your Inventory API here (e.g., ../integ/inventory_fn.php)
                fetch('../integ/inventory_fn.php')
                    .then(response => response.json())
                    .then(data => {
                        // RENDER LOGIC HERE
                    });
                return;
                */
            }





            fetch(endpoint)
                .then(response => response.json())
                .then(data => {
                    const grid = document.getElementById(gridId);
                    grid.innerHTML = '';
                    if (!data || data.length === 0) {
                        grid.innerHTML = '<div style="text-align: center; padding: 4rem; color: #adb5bd; grid-column: 1/-1;"><i class="fas fa-layer-group" style="font-size: 3rem; margin-bottom: 1.5rem; display: block;"></i><p style="font-size: 1.2rem; font-weight: 500;">Coming Soon</p><p style="font-size: 0.9rem; margin-top: 0.5rem;">This category is reserved for future system integration.</p></div>';
                        return;
                    }
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
                                                <button class="btn-view-small" onclick='showFileDetails(${JSON.stringify(item).replace(/'/g, "&apos;")})'>
                                                    <i class="fas fa-eye"></i> View
                                                </button>
                                            </td>
                                        </tr>
                                    `).join('')}

                                </tbody>
                            </table>
                        </div>
                    `;
                })

                .catch(error => {
                    console.error('Fetch error:', error);


                    // Fallback to dummy data on error
                    const data = loadDummyData(category);
                    const grid = document.getElementById(gridId);
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
                                                <button class="btn-view-small" onclick='showFileDetails(${JSON.stringify(item).replace(/'/g, "&apos;")})'>
                                                    <i class="fas fa-eye"></i> View
                                                </button>
                                            </td>
                                        </tr>
                                    `).join('')}

                                </tbody>
                            </table>
                        </div>
                    `;


                });
        }

        // Utility: show file details in modal
        function showFileDetails(file) {
            const modal = document.getElementById('fileDetailsModal');
            const content = document.getElementById('fileDetailsContent');
            content.innerHTML = `
                 <h4 style="margin-top:0;">${file.name || 'Unnamed'}</h4>
                 <p><strong>Category:</strong> ${file.category || 'N/A'}</p>
                 <p><strong>Size:</strong> ${file.file_size || 'Unknown'}</p>
                 <p><strong>Uploaded:</strong> ${new Date(file.upload_date).toLocaleDateString()}</p>
                 <div style="margin-top:1rem;display:flex;gap:0.5rem;">
                     <a href="#" class="btn btn-primary" id="downloadLink">View / Download</a>
                     <button class="btn" id="closeDetails">Close</button>
                 </div>
             `;

            const downloadLink = document.getElementById('downloadLink');
            if (file.id) {
                downloadLink.setAttribute('href', '?api=1&action=download&id=' + encodeURIComponent(file.id));
                downloadLink.setAttribute('target', '_blank');
            } else {
                downloadLink.setAttribute('href', '#');
            }

            modal.style.display = 'block';

            // close button inside details
            document.getElementById('closeDetails').addEventListener('click', () => {
                modal.style.display = 'none';
            });
        }

        // Generic modal close handlers (for existing close spans)
        document.querySelectorAll('.modal .close').forEach(span => {
            span.addEventListener('click', function () {
                const m = this.closest('.modal');
                if (m) m.style.display = 'none';
            });
        });

        // Close modal when clicking outside
        window.addEventListener('click', function (event) {
            document.querySelectorAll('.modal').forEach(modal => {
                if (event.target === modal) modal.style.display = 'none';
            });
        });

        // Load initial content on page load
        window.addEventListener('load', () => {
            loadCategoryFiles('all');
            updateSecurityStatus(false);
            
            // Add sidebar toggle functionality
            const sidebarToggle = document.getElementById('sidebarToggle');
            const sidebar = document.querySelector('.sidebar');
            
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
        });

        // Security status update function
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

        // Reset session on user activity
        document.addEventListener('click', resetPinSession);
        document.addEventListener('keypress', resetPinSession);
        document.addEventListener('scroll', resetPinSession);
    </script>
    
    <!-- Loading Animation Script -->
    <script>
        // Select all elements with 'wave-text' class
        const waveTexts = document.querySelectorAll('.wave-text');

        waveTexts.forEach(textContainer => {
            const text = textContainer.textContent;
            textContainer.innerHTML = ''; // Clear existing text

            // Split text into letters and create spans
            [...text].forEach((letter, index) => {
                const span = document.createElement('span');
                span.textContent = letter === ' ' ? '\u00A0' : letter; // Handle spaces
                span.style.setProperty('--i', index); // Set custom property for delay
                textContainer.appendChild(span);
            });
        });

        // Hide loading screen after page loads
        window.addEventListener('load', function() {
            setTimeout(function() {
                document.body.classList.add('loaded');
            }, 5000); // 5 seconds loading time
        });
    </script>
</body>

</html>