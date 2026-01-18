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
$total_active = $pdo->query("SELECT COUNT(*) FROM documents WHERE is_deleted = 0")->fetchColumn();
$total_deleted = $pdo->query("SELECT COUNT(*) FROM documents WHERE is_deleted = 1")->fetchColumn();
$total_storage = $pdo->query("SELECT SUM(file_size) FROM documents")->fetchColumn() ?: 0;

function formatBytes($bytes, $precision = 2)
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Master Document Control | Super Admin</title>
    <link rel="icon" type="image/x-icon" href="../../assets/image/logo2.png">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
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
            padding: 40px 20px;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 40px;
        }

        .header h1 {
            font-size: 32px;
            font-weight: 700;
            background: linear-gradient(135deg, var(--primary-purple), var(--secondary-pink));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .back-btn {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            color: var(--text-gray);
            font-weight: 600;
            transition: color 0.3s;
        }

        .back-btn:hover {
            color: var(--primary-purple);
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }

        .stat-card {
            background: var(--card-bg);
            padding: 25px;
            border-radius: 24px;
            border: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 20px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
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

        .stat-info h3 {
            font-size: 28px;
            font-weight: 700;
            color: var(--text-dark);
        }

        .stat-info p {
            color: var(--text-gray);
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        /* Tabs */
        .tabs {
            display: flex;
            gap: 30px;
            border-bottom: 2px solid #e2e8f0;
            margin-bottom: 30px;
        }

        .tab {
            padding: 15px 5px;
            cursor: pointer;
            font-weight: 600;
            color: var(--text-gray);
            position: relative;
            transition: color 0.3s;
        }

        .tab.active {
            color: var(--primary-purple);
        }

        .tab.active::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 100%;
            height: 2px;
            background: var(--primary-purple);
        }

        /* Table */
        .table-container {
            background: var(--card-bg);
            border-radius: 24px;
            border: 1px solid #e2e8f0;
            overflow: hidden;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: #f8fafc;
            padding: 18px 25px;
            text-align: left;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--text-gray);
            border-bottom: 1px solid #e2e8f0;
        }

        td {
            padding: 18px 25px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 15px;
        }

        tr:hover td {
            background: #fefaff;
        }

        .doc-name {
            font-weight: 600;
            color: var(--text-dark);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .doc-icon {
            width: 35px;
            height: 35px;
            background: #eff6ff;
            color: #3b82f6;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
        }

        .badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-active {
            background: #dcfce7;
            color: #15803d;
        }

        .badge-deleted {
            background: #fee2e2;
            color: #b91c1c;
        }

        .actions {
            display: flex;
            gap: 8px;
        }

        .action-btn {
            width: 35px;
            height: 35px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
            border: none;
            background: #f1f5f9;
            color: var(--text-gray);
        }

        .action-btn:hover {
            transform: translateY(-2px);
        }

        .btn-edit:hover {
            background: #fef3c7;
            color: #d97706;
        }

        .btn-delete:hover {
            background: #fee2e2;
            color: #ef4444;
        }

        .btn-restore:hover {
            background: #dcfce7;
            color: #10b981;
        }

        .btn-view:hover {
            background: #e0e7ff;
            color: #4338ca;
        }

        /* Modal */
        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }

        .modal {
            background: white;
            width: 500px;
            border-radius: 24px;
            padding: 30px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }

        .modal h2 {
            margin-bottom: 20px;
            font-size: 24px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--text-gray);
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            outline: none;
            transition: border-color 0.3s;
        }

        .form-group input:focus {
            border-color: var(--primary-purple);
        }

        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 15px;
            margin-top: 30px;
        }

        .btn {
            padding: 12px 25px;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            transition: all 0.3s;
        }

        .btn-cancel {
            background: #f1f5f9;
            color: var(--text-gray);
        }

        .btn-save {
            background: var(--primary-purple);
            color: white;
        }

        .btn-save:hover {
            background: #7c3aed;
        }

        #toast {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: var(--dark-blue);
            color: white;
            padding: 15px 30px;
            border-radius: 12px;
            display: none;
            animation: slideIn 0.3s ease-out;
            z-index: 2000;
        }

        @keyframes slideIn {
            from {
                transform: translateX(100%);
            }

            to {
                transform: translateX(0);
            }
        }
    </style>
</head>

<body>

    <div class="container">
        <div class="header">
            <div>
                <a href="../Dashboard.php" class="back-btn">
                    <i class="fas fa-arrow-left"></i> Back to CommandCenter
                </a>
                <h1>Master Document Control</h1>
            </div>
            <div style="text-align: right;">
                <p style="color: var(--text-gray); font-size: 14px;">System Health: <span
                        style="color: #10b981; font-weight: 700;">OPTIONAL</span></p>
                <p style="color: var(--text-gray); font-size: 12px;">Super Admin:
                    <?php echo htmlspecialchars($_SESSION['username']); ?></p>
            </div>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #3b82f6, #2563eb);">
                    <i class="fas fa-file-alt"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo number_format($total_active); ?></h3>
                    <p>Active Archives</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #ef4444, #b91c1c);">
                    <i class="fas fa-trash-alt"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo number_format($total_deleted); ?></h3>
                    <p>Quarantined Files</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #8b5cf6, #d946ef);">
                    <i class="fas fa-database"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo formatBytes($total_storage); ?></h3>
                    <p>Total Payload</p>
                </div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="tabs">
            <div class="tab active" data-view="active">Active Archives</div>
            <div class="tab" data-view="deleted">Trash Bin (Quarantine)</div>
        </div>

        <!-- Main Table -->
        <div class="table-container">
            <table id="docTable">
                <thead>
                    <tr>
                        <th>Document Name</th>
                        <th>Category</th>
                        <th>Size</th>
                        <th>Date Uploaded</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="docList">
                    <!-- Content will be loaded via JS -->
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 50px;">
                            <i class="fas fa-circle-notch fa-spin"
                                style="font-size: 30px; color: var(--primary-purple);"></i>
                            <p style="margin-top: 15px; color: var(--text-gray);">Decrypting Archive Data...</p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Edit Modal -->
    <div class="modal-overlay" id="editModalOverlay">
        <div class="modal">
            <h2>Edit Document Details</h2>
            <form id="editForm">
                <input type="hidden" id="editId" name="id">
                <div class="form-group">
                    <label>Document Name</label>
                    <input type="text" id="editName" name="name" required>
                </div>
                <div class="form-group">
                    <label>Category</label>
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
                    <label>Description</label>
                    <textarea id="editDescription" name="description" rows="3"></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-cancel" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn btn-save">Update Archive</button>
                </div>
            </form>
        </div>
    </div>

    <div id="toast">Operation Successful</div>

    <script>
        let currentView = 'active';
        let documents = [];

        async function loadData() {
            try {
                const response = await fetch('../../Modules/document management(archiving).php?api=1&action=' + (currentView === 'active' ? 'active' : 'deleted'));
                documents = await response.json();
                renderTable();
            } catch (error) {
                console.error('Error loading documents:', error);
                document.getElementById('docList').innerHTML = '<tr><td colspan="6" style="text-align: center; padding: 50px; color: #ef4444;">Failed to fetch data from cluster.</td></tr>';
            }
        }

        function renderTable() {
            const tbody = document.getElementById('docList');
            if (documents.length === 0) {
                tbody.innerHTML = `<tr><td colspan="6" style="text-align: center; padding: 50px; color: var(--text-gray);">No documents found in this sector.</td></tr>`;
                return;
            }

            tbody.innerHTML = documents.map(doc => `
            <tr>
                <td>
                    <div class="doc-name">
                        <div class="doc-icon"><i class="fas fa-file-pdf"></i></div>
                        <div>
                            <div>${doc.name}</div>
                            <div style="font-size: 11px; color: var(--text-gray); font-weight: 400;">${doc.description || 'No description'}</div>
                        </div>
                    </div>
                </td>
                <td><span style="font-size: 13px; color: var(--text-gray);"><i class="fas fa-folder" style="margin-right: 5px;"></i> ${doc.category}</span></td>
                <td>${formatBytes(doc.file_size)}</td>
                <td>${doc.upload_date}</td>
                <td><span class="badge ${doc.is_deleted == 1 ? 'badge-deleted' : 'badge-active'}">${doc.is_deleted == 1 ? 'Deleted' : 'Active'}</span></td>
                <td>
                    <div class="actions">
                        ${doc.is_deleted == 0 ? `
                            <button class="action-btn btn-view" onclick="window.open('../../Modules/document management(archiving).php?api=1&action=download&id=${doc.id}')" title="Download">
                                <i class="fas fa-download"></i>
                            </button>
                            <button class="action-btn btn-edit" onclick="openEditModal(${doc.id})" title="Edit Metadata">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="action-btn btn-delete" onclick="handleAction('delete', ${doc.id})" title="Send to Trash">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        ` : `
                            <button class="action-btn btn-restore" onclick="handleAction('restore', ${doc.id})" title="Restore Payload">
                                <i class="fas fa-undo"></i>
                            </button>
                            <button class="action-btn btn-delete" onclick="handleAction('permanent_delete', ${doc.id})" title="Wipe Permanently">
                                <i class="fas fa-skull"></i>
                            </button>
                        `}
                    </div>
                </td>
            </tr>
        `).join('');
        }

        async function handleAction(action, id) {
            if (!confirm('Are you sure you want to execute this protocol: ' + action.toUpperCase() + '?')) return;

            const formData = new FormData();
            formData.append('action', action);
            formData.append('id', id);

            const response = await fetch(window.location.href, {
                method: 'POST',
                body: formData
            });

            const result = await response.json();
            if (result.success) {
                showToast(action.charAt(0).toUpperCase() + action.slice(1) + ' protocol completed.');
                loadData();
            }
        }

        function openEditModal(id) {
            const doc = documents.find(d => d.id == id);
            if (!doc) return;

            document.getElementById('editId').value = doc.id;
            document.getElementById('editName').value = doc.name;
            document.getElementById('editCategory').value = doc.category;
            document.getElementById('editDescription').value = doc.description;

            document.getElementById('editModalOverlay').style.display = 'flex';
        }

        function closeModal() {
            document.getElementById('editModalOverlay').style.display = 'none';
        }

        document.getElementById('editForm').onsubmit = async (e) => {
            e.preventDefault();
            const formData = new FormData(e.target);
            formData.append('action', 'edit');

            const response = await fetch(window.location.href, {
                method: 'POST',
                body: formData
            });

            const result = await response.json();
            if (result.success) {
                showToast('Document metadata updated successfully.');
                closeModal();
                loadData();
            }
        };

        function showToast(msg) {
            const toast = document.getElementById('toast');
            toast.textContent = msg;
            toast.style.display = 'block';
            setTimeout(() => toast.style.display = 'none', 3000);
        }

        function formatBytes(bytes) {
            if (bytes === 0) return '0 B';
            const k = 1024;
            const sizes = ['B', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }

        // Tab switching
        document.querySelectorAll('.tab').forEach(tab => {
            tab.onclick = () => {
                document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
                tab.classList.add('active');
                currentView = tab.dataset.view;
                loadData();
            };
        });

        // Initial load
        loadData();
    </script>

</body>

</html>