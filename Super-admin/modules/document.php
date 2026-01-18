<?php
session_start();

// Security check: Only Super Admin can access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    header('Location: ../auth/login.php');
    exit;
}

require_once __DIR__ . '/../../db/db.php';
$pdo = get_pdo();

// Handle Actions (AJAX/POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];
    $id = $_POST['id'] ?? null;

    if ($action === 'delete') {
        $stmt = $pdo->prepare("UPDATE documents SET is_deleted = 1, deleted_date = NOW() WHERE id = ?");
        $success = $stmt->execute([$id]);
        echo json_encode(['success' => $success]);
        exit;
    }

    if ($action === 'restore') {
        $stmt = $pdo->prepare("UPDATE documents SET is_deleted = 0, deleted_date = NULL WHERE id = ?");
        $success = $stmt->execute([$id]);
        echo json_encode(['success' => $success]);
        exit;
    }

    if ($action === 'permanent_delete') {
        // First get path to delete file
        $stmt = $pdo->prepare("SELECT file_path FROM documents WHERE id = ?");
        $stmt->execute([$id]);
        $doc = $stmt->fetch();
        if ($doc && file_exists($doc['file_path'])) {
            unlink($doc['file_path']);
        }

        $stmt = $pdo->prepare("DELETE FROM documents WHERE id = ?");
        $success = $stmt->execute([$id]);
        echo json_encode(['success' => $success]);
        exit;
    }

    if ($action === 'edit') {
        $name = $_POST['name'];
        $category = $_POST['category'];
        $description = $_POST['description'];

        $stmt = $pdo->prepare("UPDATE documents SET name = ?, category = ?, description = ? WHERE id = ?");
        $success = $stmt->execute([$name, $category, $description, $id]);
        echo json_encode(['success' => $success]);
        exit;
    }
}

// Fetch stats
$stats = [
    'total' => $pdo->query("SELECT COUNT(*) FROM documents WHERE is_deleted = 0")->fetchColumn(),
    'trash' => $pdo->query("SELECT COUNT(*) FROM documents WHERE is_deleted = 1")->fetchColumn(),
    'storage_raw' => $pdo->query("SELECT SUM(file_size) FROM documents")->fetchColumn() ?: 0,
    'categories' => $pdo->query("SELECT COUNT(DISTINCT category) FROM documents")->fetchColumn()
];

function formatBytes($bytes, $precision = 2)
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}

$stats['storage'] = formatBytes($stats['storage_raw']);
$isSuperAdmin = true; // This page is exclusively for Super Admin
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Master Document Control - Atiéra</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="../../assets/image/logo2.png">
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

        .bg-red {
            background: linear-gradient(135deg, #ef4444, #b91c1c);
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
            border: 1px solid #e2e8f0;
            padding: 25px;
            height: fit-content;
            position: sticky;
            top: 20px;
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

        .sidebar-menu a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 15px;
            text-decoration: none;
            color: var(--text-dark);
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s;
            border-radius: 12px;
        }

        .sidebar-menu a i {
            font-size: 16px;
            color: var(--text-gray);
            width: 20px;
            text-align: center;
        }

        /* Header */
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
            transition: all 0.3s;
        }

        .btn-primary:hover {
            background: #7c3aed;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(139, 92, 246, 0.3);
        }

        footer {
            text-align: center;
            padding: 40px 0;
            color: var(--text-gray);
            font-size: 0.9rem;
        }

        /* Tables */
        .financial-table-container {
            background: white;
            border-radius: 20px;
            border: 1px solid #e2e8f0;
            overflow: hidden;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05);
        }

        .financial-table {
            width: 100%;
            border-collapse: collapse;
        }

        .financial-table th {
            background: #f8fafc;
            padding: 15px 20px;
            text-align: left;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #64748b;
            border-bottom: 1px solid #e2e8f0;
        }

        .financial-table td {
            padding: 15px 20px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 14px;
        }

        .financial-table tr:hover td {
            background: #f8fafc;
        }

        .btn-view-small {
            padding: 6px 10px;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            background: white;
            cursor: pointer;
            transition: all 0.2s;
            color: #64748b;
            font-size: 12px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .btn-view-small:hover {
            background: #f1f5f9;
            color: var(--primary-purple);
            border-color: var(--primary-purple);
        }

        /* Modals */
        .modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(4px);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .modal-content {
            background: white;
            width: 100%;
            max-width: 550px;
            border-radius: 24px;
            padding: 30px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            animation: modalFadeIn 0.3s ease-out;
        }

        @keyframes modalFadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
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
        }

        .close {
            cursor: pointer;
            font-size: 24px;
            color: #94a3b8;
            transition: color 0.3s;
        }

        .close:hover {
            color: #1e293b;
        }

        /* Form Controls */
        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #475569;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            outline: none;
            font-size: 14px;
            transition: all 0.3s;
        }

        .form-group input:focus {
            border-color: var(--primary-purple);
            box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1);
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            margin-top: 30px;
        }

        .btn {
            padding: 12px 24px;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            border: none;
            font-size: 14px;
        }

        .btn-secondary {
            background: #f1f5f9;
            color: #475569;
        }

        .btn-cancel {
            background: #fee2e2;
            color: #ef4444;
        }

        /* Toast */
        #toast {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: #1e293b;
            color: white;
            padding: 15px 30px;
            border-radius: 12px;
            display: none;
            z-index: 2000;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.3);
            animation: slideIn 0.3s forwards;
        }

        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }

            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        /* Blurred Content */
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
    </style>
</head>

<body>

    <header>
        <div class="container">
            <div class="header-content">
                <div class="logo">
                    <h2>ATIÉRA MASTER CONTROL</h2>
                </div>
                <nav>
                    <ul>
                        <li><a href="#"><i class="fas fa-shield-alt"></i> Security Center</a></li>
                        <li><a href="../../include/Settings.php"><i class="fas fa-cog"></i> Settings</a></li>
                        <li><a href="../Dashboard.php">
                                <i class="fas fa-arrow-left"></i>
                                Back to CommandCenter</a>
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
                    <h3>Archive Sectors</h3>
                </div>
                <ul class="sidebar-menu">
                    <li><a href="#" class="category-link active" data-category="all"><i class="fas fa-layer-group"></i>
                            All Archives</a></li>
                    <li><a href="#" class="category-link" data-category="Financial Records"><i
                                class="fas fa-file-invoice-dollar"></i> Financial</a></li>
                    <li><a href="#" class="category-link" data-category="HR Documents"><i class="fas fa-users"></i>
                            Human Resources</a></li>
                    <li><a href="#" class="category-link" data-category="Guest Records"><i
                                class="fas fa-user-check"></i>
                            Guests</a></li>
                    <li><a href="#" class="category-link" data-category="Inventory"><i class="fas fa-boxes"></i>
                            Inventory</a></li>
                    <li><a href="#" class="category-link" data-category="Compliance"><i class="fas fa-shield-alt"></i>
                            Compliance</a></li>
                    <li><a href="#" class="category-link" data-category="Marketing"><i class="fas fa-bullhorn"></i>
                            Marketing</a></li>
                    <li style="margin-top: 20px; border-top: 1px solid #f1f5f9; padding-top: 10px;">
                        <a href="#" class="category-link" data-category="deleted" style="color: #ef4444;">
                            <i class="fas fa-trash-alt" style="color: #ef4444;"></i> Trash Bin
                        </a>
                    </li>
                </ul>
            </aside>

            <div class="content">
                <div class="content-header"
                    style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
                    <h2 id="contentTitle" style="font-weight: 700;">Master Archive Control</h2>
                    <div class="search-container" style="display: flex; gap: 10px;">
                        <input type="text" id="documentSearch" placeholder="Search master records..."
                            style="padding: 10px 15px; border-radius: 12px; border: 1px solid #e2e8f0; width: 250px; outline: none;">
                    </div>
                </div>

                <!-- Shared Dashboard Stats Section -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon bg-blue">
                            <i class="fas fa-file-alt"></i>
                        </div>
                        <div class="stat-info">
                            <h4>Active Files</h4>
                            <p><?php echo number_format($stats['total']); ?></p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon bg-red">
                            <i class="fas fa-trash-alt"></i>
                        </div>
                        <div class="stat-info">
                            <h4>Trash Bin</h4>
                            <p><?php echo number_format($stats['trash']); ?></p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon bg-orange">
                            <i class="fas fa-hdd"></i>
                        </div>
                        <div class="stat-info">
                            <h4>Total Payload</h4>
                            <p><?php echo $stats['storage']; ?></p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon bg-green">
                            <i class="fas fa-shield-check"></i>
                        </div>
                        <div class="stat-info">
                            <h4>Access Level</h4>
                            <p>Super Admin</p>
                        </div>
                    </div>
                </div>

                <!-- Category Content Areas -->
                <div class="category-content active" id="all-content">
                    <div id="allFiles"></div>
                </div>
                <div class="category-content" id="financial-records-content">
                    <div id="financialFiles"></div>
                </div>
                <div class="category-content" id="hr-documents-content">
                    <div id="hrFiles"></div>
                </div>
                <div class="category-content" id="guest-records-content">
                    <div id="guestFiles"></div>
                </div>
                <div class="category-content" id="inventory-content">
                    <div id="inventoryFiles"></div>
                </div>
                <div class="category-content" id="compliance-content">
                    <div id="complianceFiles"></div>
                </div>
                <div class="category-content" id="marketing-content">
                    <div id="marketingFiles"></div>
                </div>
                <div class="category-content" id="deleted-content">
                    <div id="deletedFiles"></div>
                </div>
            </div>
        </div>
    </main>

    <!-- Edit Modal -->
    <div class="modal" id="editModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit Document Metadata</h3>
                <span class="close">&times;</span>
            </div>
            <form id="editForm">
                <input type="hidden" id="editId" name="id">
                <div class="form-group">
                    <label>Archive Name</label>
                    <input type="text" id="editName" name="name" required>
                </div>
                <div class="form-group">
                    <label>Category/Sector</label>
                    <select id="editCategory" name="category" required>
                        <option value="Financial Records">Financial Records</option>
                        <option value="HR Documents">HR Documents</option>
                        <option value="Guest Records">Guest Records</option>
                        <option value="Inventory">Inventory</option>
                        <option value="Compliance">Compliance</option>
                        <option value="Marketing">Marketing</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Metadata Description</label>
                    <textarea id="editDescription" name="description" rows="3"
                        placeholder="Enter file details..."></textarea>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary close">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- File Details Modal -->
    <div class="modal" id="detailsModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Archive Resource Details</h3>
                <span class="close">&times;</span>
            </div>
            <div id="detailsContent"></div>
            <div class="form-actions" style="margin-top: 30px;">
                <button class="btn btn-secondary close">Close</button>
            </div>
        </div>
    </div>

    <footer>
        <p>Atiéra Cloud Archive &copy; 2023 | Master Document Portal</p>
    </footer>

    <div id="toast">Protocol Executed Successfully</div>

    <script>
        let currentCategory = 'all';

        document.addEventListener('DOMContentLoaded', () => {
            loadDocuments('all');
            setupListeners();
        });

        function setupListeners() {
            // Category Links
            document.querySelectorAll('.category-link').forEach(link => {
                link.addEventListener('click', (e) => {
                    e.preventDefault();
                    const cat = link.getAttribute('data-category');
                    switchCategory(link, cat);
                });
            });

            // Modal Close
            document.querySelectorAll('.close').forEach(c => {
                c.onclick = () => {
                    document.querySelectorAll('.modal').forEach(m => m.style.display = 'none');
                }
            });

            // Edit Form Submit
            document.getElementById('editForm').onsubmit = (e) => {
                e.preventDefault();
                handleEdit(new FormData(e.target));
            };

            // Search
            document.getElementById('documentSearch').addEventListener('input', (e) => {
                const term = e.target.value.toLowerCase();
                filterTable(term);
            });
        }

        function switchCategory(link, cat) {
            document.querySelectorAll('.category-link').forEach(l => l.classList.remove('active'));
            link.classList.add('active');

            document.querySelectorAll('.category-content').forEach(c => c.classList.remove('active'));
            const contentId = `${cat.toLowerCase().replace(/\s+/g, '-')}-content`;
            const contentEl = document.getElementById(contentId) || document.getElementById('all-content');
            if (contentEl) contentEl.classList.add('active');

            const titles = {
                'all': 'Master Archive Control',
                'Financial Records': 'Financial Archives',
                'HR Documents': 'Human Resource Logs',
                'Guest Records': 'Guest Information Repository',
                'Inventory': 'Inventory Master Logs',
                'Compliance': 'Compliance & Legal Records',
                'Marketing': 'Marketing Campaign Assets',
                'deleted': 'Quarantined Archives (Trash)'
            };
            document.getElementById('contentTitle').textContent = titles[cat] || 'Master Archive Control';

            currentCategory = cat;
            loadDocuments(cat);
        }

        function loadDocuments(cat) {
            const gridId = (cat === 'all' ? 'allFiles' : (cat === 'deleted' ? 'deletedFiles' : `${cat.toLowerCase().replace(/\s+/g, '')}Files`));
            const grid = document.getElementById(gridId);
            if (!grid) return;

            grid.innerHTML = '<div style="text-align:center; padding: 40px; color: #64748b;"><i class="fas fa-spinner fa-spin"></i> Fetching records...</div>';

            const endpoint = cat === 'deleted' ?
                '../../Modules/document management(archiving).php?api=1&action=deleted' :
                (cat === 'all' ? '../../Modules/document management(archiving).php?api=1&action=active' : `../../Modules/document management(archiving).php?api=1&action=active&category=${encodeURIComponent(cat)}`);

            fetch(endpoint)
                .then(r => r.json())
                .then(data => {
                    if (!data || data.length === 0) {
                        grid.innerHTML = `<div style="text-align:center; padding: 60px; color: #94a3b8;"><i class="fas fa-folder-open" style="font-size: 3rem; margin-bottom: 20px;"></i><p>No records found in this sector.</p></div>`;
                    } else {
                        renderMasterTable(data, grid);
                    }
                })
                .catch(err => {
                    grid.innerHTML = `<div style="text-align:center; padding: 40px; color: #ef4444;"><i class="fas fa-exclamation-triangle"></i> Error connecting to database cluster.</div>`;
                });
        }

        function renderMasterTable(data, grid) {
            grid.innerHTML = `
                <div class="financial-table-container">
                    <table class="financial-table">
                        <thead>
                            <tr>
                                <th>Archive Resource</th>
                                <th>Sector</th>
                                <th>Payload</th>
                                <th>Timeline</th>
                                <th>Protocol Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${data.map(item => `
                                <tr>
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 12px;">
                                            <div style="width: 35px; height: 35px; background: #eff6ff; color: #3b82f6; border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                                                <i class="fas fa-file-pdf"></i>
                                            </div>
                                            <div>
                                                <div style="font-weight: 600;">${item.name}</div>
                                                <div style="font-size: 11px; color: #64748b;">${item.description || 'No metadata description'}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td><span style="font-size: 13px; color: #64748b;"><i class="fas fa-folder" style="margin-right: 5px;"></i> ${item.category}</span></td>
                                    <td>${formatBytes(item.file_size)}</td>
                                    <td>${new Date(item.upload_date).toLocaleDateString()}</td>
                                    <td>
                                        <div style="display: flex; gap: 8px;">
                                            <a href="../../Modules/document management(archiving).php?api=1&action=download&id=${item.id}" class="btn-view-small" title="Secure Fetch">
                                                <i class="fas fa-download"></i>
                                            </a>
                                            <button class="btn-view-small" onclick='showDetails(${JSON.stringify(item).replace(/'/g, "&apos;")})' title="View Analysis">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            ${item.is_deleted == 0 ? `
                                                <button class="btn-view-small" style="color: #f59e0b;" onclick='openEditModal(${JSON.stringify(item).replace(/'/g, "&apos;")})' title="Modify Metadata">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn-view-small" style="color: #ef4444;" onclick="handleProtocol('delete', ${item.id})" title="Quarantine Resource">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            ` : `
                                                <button class="btn-view-small" style="color: #10b981;" onclick="handleProtocol('restore', ${item.id})" title="Restore Resource">
                                                    <i class="fas fa-undo"></i>
                                                </button>
                                                <button class="btn-view-small" style="color: #64748b;" onclick="handleProtocol('permanent_delete', ${item.id})" title="Wipe Permanently">
                                                    <i class="fas fa-skull"></i>
                                                </button>
                                            `}
                                        </div>
                                    </td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            `;
        }

        window.handleProtocol = function (action, id) {
            const confirmMsg = action === 'permanent_delete' ?
                'WARNING: THIS ACTION IS ADIABATIC AND PERMANENT. WIPE RESOURCE?' :
                `EXECUTE ${action.toUpperCase()} PROTOCOL ON RESOURCE #${id}?`;

            if (!confirm(confirmMsg)) return;

            const fd = new FormData();
            fd.append('action', action);
            fd.append('id', id);

            fetch(window.location.href, { method: 'POST', body: fd })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        showToast(`Archive protocol ${action} completed successfully.`);
                        loadDocuments(currentCategory);
                    }
                });
        }

        window.openEditModal = function (item) {
            document.getElementById('editId').value = item.id;
            document.getElementById('editName').value = item.name;
            document.getElementById('editCategory').value = item.category;
            document.getElementById('editDescription').value = item.description || '';
            document.getElementById('editModal').style.display = 'flex';
        }

        function handleEdit(fd) {
            fd.append('action', 'edit');
            fetch(window.location.href, { method: 'POST', body: fd })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        showToast('Document metadata updated and synchronized.');
                        document.getElementById('editModal').style.display = 'none';
                        loadDocuments(currentCategory);
                    }
                });
        }

        window.showDetails = function (item) {
            const content = document.getElementById('detailsContent');
            content.innerHTML = `
                <div style="background: #f8fafc; padding: 20px; border-radius: 12px; margin-bottom: 20px;">
                    <h4 style="margin-bottom: 15px; color: var(--primary-purple);">${item.name}</h4>
                    <p style="margin-bottom: 10px;"><strong>Sector:</strong> ${item.category}</p>
                    <p style="margin-bottom: 10px;"><strong>Payload Size:</strong> ${formatBytes(item.file_size)}</p>
                    <p style="margin-bottom: 10px;"><strong>Timeline:</strong> ${new Date(item.upload_date).toLocaleString()}</p>
                    <p style="margin-bottom: 10px;"><strong>Status:</strong> ${item.is_deleted == 1 ? 'QUARANTINED' : 'ACTIVE'}</p>
                    ${item.description ? `<p style="margin-top: 15px; border-top: 1px solid #e2e8f0; padding-top: 15px;"><strong>Description:</strong><br>${item.description}</p>` : ''}
                </div>
            `;
            document.getElementById('detailsModal').style.display = 'flex';
        }

        function filterTable(term) {
            const rows = document.querySelectorAll('.financial-table tbody tr');
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(term) ? '' : 'none';
            });
        }

        function showToast(msg) {
            const t = document.getElementById('toast');
            t.textContent = msg;
            t.style.display = 'block';
            setTimeout(() => t.style.display = 'none', 3000);
        }

        function formatBytes(bytes) {
            if (bytes == 0) return '0 B';
            const k = 1024, sizes = ['B', 'KB', 'MB', 'GB'], i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }
    </script>

</body>

</html>