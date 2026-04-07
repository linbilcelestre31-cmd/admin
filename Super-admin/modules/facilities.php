<?php
session_start();
require_once __DIR__ . '/../../db/db.php';

// Security check: Only Super Admin can access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    header('Location: ../auth/login.php');
    exit;
}

$pdo = get_pdo();
$message = '';
$error = '';

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        /* Delete Facility */
        if ($_POST['action'] === 'delete_facility') {
            $id = $_POST['id'];
            try {
                $stmt = $pdo->prepare("DELETE FROM facilities WHERE id = ?");
                $stmt->execute([$id]);
                $message = "Facility has been permanently removed.";
            } catch (PDOException $e) {
                $error = "Error: " . $e->getMessage();
            }
        }
        /* Update Facility */ elseif ($_POST['action'] === 'edit_facility') {
            $id = $_POST['id'];
            $name = $_POST['name'];
            $type = $_POST['type'];
            $capacity = $_POST['capacity'];
            $location = $_POST['location'];
            $rate = $_POST['hourly_rate'];

            try {
                $stmt = $pdo->prepare("UPDATE facilities SET name = ?, type = ?, capacity = ?, location = ?, hourly_rate = ? WHERE id = ?");
                $stmt->execute([$name, $type, $capacity, $location, $rate, $id]);
                $message = "Facility details updated successfully.";
            } catch (PDOException $e) {
                $error = "Error: " . $e->getMessage();
            }
        }
    }
}

// Fetch Facilities
$stmt = $pdo->query("SELECT * FROM facilities ORDER BY name ASC");
$facilities = $stmt->fetchAll();

// Fetch admin details for sidebar
$admin_id = $_SESSION['user_id'];
$sa_table = 'SuperAdminLogin_tb';
$stmt = $pdo->prepare("SELECT * FROM `$sa_table` WHERE id = ?");
$stmt->execute([$admin_id]);
$admin = $stmt->fetch();
$api_key = $admin['api_key'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Facility Management | Super Admin</title>
    <link rel="icon" type="image/x-icon" href="../../assets/image/logo2.png">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/Dashboard.css?v=<?php echo time(); ?>">
    <style>
        .table-card {
            background: white;
            padding: 30px;
            border-radius: 24px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
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
            letter-spacing: 1px;
            border-bottom: 1px solid #e2e8f0;
        }

        .data-table td {
            padding: 20px 15px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 15px;
        }

        .action-btns {
            display: flex;
            gap: 10px;
        }

        .btn-action {
            width: 35px;
            height: 35px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: none;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-edit {
            background: rgba(59, 130, 246, 0.1);
            color: #3b82f6;
        }

        .btn-edit:hover {
            background: #3b82f6;
            color: white;
        }

        .btn-delete {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
        }

        .btn-delete:hover {
            background: #ef4444;
            color: white;
        }

        .modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.85);
            z-index: 10000;
            justify-content: center;
            align-items: center;
            backdrop-filter: blur(8px);
        }

        .modal-content {
            background: white;
            width: 90%;
            max-width: 500px;
            padding: 40px;
            border-radius: 30px;
            position: relative;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            font-size: 14px;
            margin-bottom: 5px;
            font-weight: 600;
        }

        .form-input {
            width: 100%;
            padding: 10px;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
        }

        .submit-btn {
            width: 100%;
            padding: 12px;
            background: #d4af37;
            color: white;
            border: none;
            border-radius: 12px;
            font-weight: 600;
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
            <li class="nav-item"><a href="facilities.php" class="nav-link active"><i class="fas fa-building"></i>
                    Facilities</a></li>
            <li class="nav-item"><a href="reservation.php" class="nav-link"><i class="fas fa-calendar-check"></i>
                    Reservations</a></li>
            <li class="nav-item"><a href="management.php" class="nav-link"><i class="fas fa-tasks"></i> Operations</a>
            </li>
        </ul>
        <a href="../auth/logout.php" class="logout-btn"><i class="fas fa-power-off"></i> Log Out</a>
    </div>

    <div class="main-content">
        <div class="header">
            <div class="welcome-msg">
                <h1>Facility Management</h1>
                <p>Monitor and manage all hotel facilities across the property.</p>
            </div>
        </div>

        <?php if ($message): ?>
            <div style="padding: 15px; background: #ecfdf5; color: #10b981; border-radius: 12px; margin-bottom: 20px;">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <div class="table-card">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Facility Name</th>
                        <th>Type</th>
                        <th>Capacity</th>
                        <th>Location</th>
                        <th>Rate/Hr</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($facilities as $f): ?>
                        <tr>
                            <td>#<?php echo $f['id']; ?></td>
                            <td style="font-weight: 600;"><?php echo htmlspecialchars($f['name']); ?></td>
                            <td><span
                                    style="background: #f1f5f9; padding: 4px 10px; border-radius: 20px; font-size: 12px;"><?php echo strtoupper($f['type']); ?></span>
                            </td>
                            <td><?php echo $f['capacity']; ?> Pax</td>
                            <td><?php echo htmlspecialchars($f['location']); ?></td>
                            <td style="font-weight: 700;">₱<?php echo number_format($f['hourly_rate'], 2); ?></td>
                            <td>
                                <div class="action-btns">
                                    <button class="btn-action btn-edit"
                                        onclick='openEditModal(<?php echo json_encode($f); ?>)'><i
                                            class="fas fa-edit"></i></button>
                                    <button class="btn-action btn-delete"
                                        onclick="openDeleteModal(<?php echo $f['id']; ?>)"><i
                                            class="fas fa-trash"></i></button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Edit Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <h2 style="margin-bottom: 20px;">Edit Facility</h2>
            <form method="POST">
                <input type="hidden" name="action" value="edit_facility">
                <input type="hidden" name="id" id="edit_id">
                <div class="form-group">
                    <label>Facility Name</label>
                    <input type="text" name="name" id="edit_name" class="form-input" required>
                </div>
                <div class="form-group">
                    <label>Type</label>
                    <select name="type" id="edit_type" class="form-input">
                        <option value="meeting">Meeting Room</option>
                        <option value="banquet">Banquet Hall</option>
                        <option value="outdoor">Outdoor Space</option>
                        <option value="dining">Dining Area</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Capacity</label>
                    <input type="number" name="capacity" id="edit_capacity" class="form-input" required>
                </div>
                <div class="form-group">
                    <label>Location</label>
                    <input type="text" name="location" id="edit_location" class="form-input" required>
                </div>
                <div class="form-group">
                    <label>Hourly Rate</label>
                    <input type="number" step="0.01" name="hourly_rate" id="edit_rate" class="form-input" required>
                </div>
                <button type="submit" class="submit-btn">Update Facility</button>
                <button type="button" class="submit-btn" style="background: #ccc; margin-top: 10px;"
                    onclick="closeModal('editModal')">Cancel</button>
            </form>
        </div>
    </div>

    <!-- Delete Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content" style="text-align: center;">
            <i class="fas fa-exclamation-triangle" style="font-size: 50px; color: #ef4444; margin-bottom: 20px;"></i>
            <h2>Confirm Removal</h2>
            <p style="margin: 15px 0 30px; color: #64748b;">Are you sure you want to permanently remove this facility?
                This action cannot be undone.</p>
            <form method="POST">
                <input type="hidden" name="action" value="delete_facility">
                <input type="hidden" name="id" id="delete_id">
                <div style="display: flex; gap: 10px;">
                    <button type="button" class="submit-btn" style="background: #f1f5f9; color: #64748b;"
                        onclick="closeModal('deleteModal')">Cancel</button>
                    <button type="submit" class="submit-btn" style="background: #ef4444;">Delete Permanently</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openEditModal(facility) {
            document.getElementById('edit_id').value = facility.id;
            document.getElementById('edit_name').value = facility.name;
            document.getElementById('edit_type').value = facility.type;
            document.getElementById('edit_capacity').value = facility.capacity;
            document.getElementById('edit_location').value = facility.location;
            document.getElementById('edit_rate').value = facility.hourly_rate;
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