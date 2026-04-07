<?php
session_start();
require_once __DIR__ . '/../../db/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    header('Location: ../auth/login.php');
    exit;
}

$pdo = get_pdo();
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'delete_log') {
            $id = $_POST['id'];
            $stmt = $pdo->prepare("DELETE FROM maintenance_logs WHERE id = ?");
            $stmt->execute([$id]);
            $message = "Maintenance log purged.";
        } elseif ($_POST['action'] === 'edit_log') {
            $id = $_POST['id'];
            $item = $_POST['item_name'];
            $staff = $_POST['assigned_staff'];
            $status = $_POST['status'];

            $stmt = $pdo->prepare("UPDATE maintenance_logs SET item_name = ?, assigned_staff = ?, status = ? WHERE id = ?");
            $stmt->execute([$item, $staff, $status, $id]);
            $message = "Log updated.";
        }
    }
}

$stmt = $pdo->query("SELECT * FROM maintenance_logs ORDER BY maintenance_date DESC");
$logs = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Operations Management | Super Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/Dashboard.css?v=<?php echo time(); ?>">
    <style>
        .table-card {
            background: white;
            padding: 30px;
            border-radius: 24px;
            border: 1px solid #e2e8f0;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table th {
            text-align: left;
            padding: 15px;
            color: #64748b;
            font-size: 13px;
            text-transform: uppercase;
            border-bottom: 1px solid #e2e8f0;
        }

        .data-table td {
            padding: 15px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 14px;
        }

        .status-badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .status-completed {
            background: #ecfdf5;
            color: #10b981;
        }

        .status-pending {
            background: #fffbeb;
            color: #f59e0b;
        }

        .status-in-progress {
            background: #eff6ff;
            color: #3b82f6;
        }

        .modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.8);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 20px;
            width: 400px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-input {
            width: 100%;
            padding: 10px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
        }

        .btn-submit {
            width: 100%;
            padding: 12px;
            background: #0f172a;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
        }
    </style>
</head>

<body>
    <div class="sidebar">
        <div class="sidebar-header">
            <h1 class="sidebar-logo">ATIÉRA</h1>
            <div
                style="color: var(--primary-gold); font-size: 10px; font-weight: 700; letter-spacing: 2px; text-transform: uppercase; margin-top: 5px; opacity: 0.8; text-align: center; width: 100%;">
                Super Admin</div>
        </div>
        <ul class="nav-list">
            <li class="nav-item"><a href="../Dashboard.php" class="nav-link"><i class="fas fa-home"></i> Dashboard</a>
            </li>
            <div class="nav-section-label">Settings</div>
            <li class="nav-item"><a href="../Settings.php" class="nav-link"><i class="fas fa-user-gear"></i> Account</a>
            </li>
            <div class="nav-section-label">Management</div>
            <li class="nav-item"><a href="facilities.php" class="nav-link"><i class="fas fa-building"></i>
                    Facilities</a></li>
            <li class="nav-item"><a href="reservation.php" class="nav-link"><i class="fas fa-calendar-check"></i>
                    Reservations</a></li>
            <li class="nav-item"><a href="management.php" class="nav-link active"><i class="fas fa-tasks"></i>
                    Operations</a></li>
        </ul>
        <a href="../auth/logout.php" class="logout-btn"><i class="fas fa-power-off"></i> Log Out</a>
    </div>

    <div class="main-content">
        <div class="header">
            <div class="welcome-msg">
                <h1>Operations & Maintenance</h1>
                <p>Track facility upkeep and technical operations.</p>
            </div>
        </div>

        <div class="table-card">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Item/Task</th>
                        <th>Assigned Staff</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $l): ?>
                        <tr>
                            <td>#
                                <?php echo $l['id']; ?>
                            </td>
                            <td style="font-weight: 600;">
                                <?php echo htmlspecialchars($l['item_name']); ?>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($l['assigned_staff']); ?>
                            </td>
                            <td>
                                <?php echo date('M d, Y', strtotime($l['maintenance_date'])); ?>
                            </td>
                            <td><span class="status-badge status-<?php echo $l['status']; ?>">
                                    <?php echo $l['status']; ?>
                                </span></td>
                            <td>
                                <div style="display: flex; gap: 8px;">
                                    <button class="btn-submit"
                                        style="width: 32px; height: 32px; padding: 0; background: rgba(59, 130, 246, 0.1); color: #3b82f6;"
                                        onclick='openEditModal(<?php echo json_encode($l); ?>)'><i
                                            class="fas fa-edit"></i></button>
                                    <button class="btn-submit"
                                        style="width: 32px; height: 32px; padding: 0; background: rgba(239, 68, 68, 0.1); color: #ef4444;"
                                        onclick="openDeleteModal(<?php echo $l['id']; ?>)"><i
                                            class="fas fa-trash"></i></button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div id="editModal" class="modal">
        <div class="modal-content">
            <h3 style="margin-bottom: 20px;">Edit Operational Task</h3>
            <form method="POST">
                <input type="hidden" name="action" value="edit_log">
                <input type="hidden" name="id" id="edit_id">
                <div class="form-group">
                    <label>Item Name</label>
                    <input type="text" name="item_name" id="edit_item" class="form-input" required>
                </div>
                <div class="form-group">
                    <label>Assigned Staff</label>
                    <input type="text" name="assigned_staff" id="edit_staff" class="form-input" required>
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status" id="edit_status" class="form-input">
                        <option value="pending">Pending</option>
                        <option value="in-progress">In Progress</option>
                        <option value="completed">Completed</option>
                    </select>
                </div>
                <button type="submit" class="btn-submit">Update Log</button>
                <button type="button" class="btn-submit" style="background: #ccc; margin-top: 10px;"
                    onclick="closeModal('editModal')">Cancel</button>
            </form>
        </div>
    </div>

    <!-- Delete Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content" style="text-align: center;">
            <i class="fas fa-tools" style="font-size: 40px; color: #ef4444; margin-bottom: 15px;"></i>
            <h3>Delete Maintenance Record?</h3>
            <p style="margin: 10px 0 25px; font-size: 14px;">Warning: This will remove the technical record permanently.
            </p>
            <form method="POST">
                <input type="hidden" name="action" value="delete_log">
                <input type="hidden" name="id" id="delete_id">
                <div style="display: flex; gap: 10px;">
                    <button type="button" class="btn-submit" style="background: #f1f5f9; color: #64748b;"
                        onclick="closeModal('deleteModal')">Cancel</button>
                    <button type="submit" class="btn-submit" style="background: #ef4444;">Purge Record</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openEditModal(l) {
            document.getElementById('edit_id').value = l.id;
            document.getElementById('edit_item').value = l.item_name;
            document.getElementById('edit_staff').value = l.assigned_staff;
            document.getElementById('edit_status').value = l.status;
            document.getElementById('editModal').style.display = 'flex';
        }
        function openDeleteModal(id) {
            document.getElementById('delete_id').value = id;
            document.getElementById('deleteModal').style.display = 'flex';
        }
        function closeModal(id) {
            document.getElementById(id).style.display = 'none';
        }
    </script>
</body>

</html>