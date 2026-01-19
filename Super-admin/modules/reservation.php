<?php
session_start();
require_once __DIR__ . '/../../db/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    header('Location: ../auth/login.php');
    exit;
}

$pdo = get_pdo();
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'delete_reservation') {
            $id = $_POST['id'];
            try {
                $stmt = $pdo->prepare("DELETE FROM reservations WHERE id = ?");
                $stmt->execute([$id]);
                $message = "Reservation purged.";
            } catch (PDOException $e) {
                $error = "Error: " . $e->getMessage();
            }
        } elseif ($_POST['action'] === 'edit_reservation') {
            $id = $_POST['id'];
            $status = $_POST['status'];
            $name = $_POST['customer_name'];
            $email = $_POST['customer_email'];

            try {
                $stmt = $pdo->prepare("UPDATE reservations SET status = ?, customer_name = ?, customer_email = ? WHERE id = ?");
                $stmt->execute([$status, $name, $email, $id]);
                $message = "Reservation updated.";
            } catch (PDOException $e) {
                $error = "Error: " . $e->getMessage();
            }
        }
    }
}

$stmt = $pdo->query("SELECT r.*, f.name as facility_name FROM reservations r JOIN facilities f ON r.facility_id = f.id ORDER BY r.event_date DESC");
$reservations = $stmt->fetchAll();

$admin_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM SuperAdminLogin_tb WHERE id = ?");
$stmt->execute([$admin_id]);
$admin = $stmt->fetch();
$api_key = $admin['api_key'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reservation Management | Super Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/Dashboard.css?v=<?php echo time(); ?>">
    <style>
        .table-card {
            background: white;
            padding: 30px;
            border-radius: 24px;
            border: 1px solid #e2e8f0;
            overflow-x: auto;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1000px;
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
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .status-confirmed {
            background: #ecfdf5;
            color: #10b981;
        }

        .status-pending {
            background: #fffbeb;
            color: #f59e0b;
        }

        .status-cancelled {
            background: #fef2f2;
            color: #ef4444;
        }

        .btn-action {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
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
            background: #d4af37;
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
            <li class="nav-item"><a href="reservation.php" class="nav-link active"><i class="fas fa-calendar-check"></i>
                    Reservations</a></li>
            <li class="nav-item"><a href="management.php" class="nav-link"><i class="fas fa-tasks"></i> Operations</a>
            </li>
        </ul>
        <a href="../auth/logout.php" class="logout-btn"><i class="fas fa-power-off"></i> Log Out</a>
    </div>

    <div class="main-content">
        <div class="header">
            <div class="welcome-msg">
                <h1>Reservation Management</h1>
                <p>Master control for all bookings and facility schedules.</p>
            </div>
        </div>

        <div class="table-card">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Facility</th>
                        <th>Customer</th>
                        <th>Date & Time</th>
                        <th>Status</th>
                        <th>Amount</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reservations as $r): ?>
                        <tr>
                            <td>#
                                <?php echo $r['id']; ?>
                            </td>
                            <td style="font-weight: 600;">
                                <?php echo htmlspecialchars($r['facility_name']); ?>
                            </td>
                            <td>
                                <div>
                                    <?php echo htmlspecialchars($r['customer_name']); ?>
                                </div>
                                <small style="color: #94a3b8;">
                                    <?php echo htmlspecialchars($r['customer_email']); ?>
                                </small>
                            </td>
                            <td>
                                <div>
                                    <?php echo date('M d, Y', strtotime($r['event_date'])); ?>
                                </div>
                                <small style="color: #94a3b8;">
                                    <?php echo date('h:i A', strtotime($r['start_time'])); ?> -
                                    <?php echo date('h:i A', strtotime($r['end_time'])); ?>
                                </small>
                            </td>
                            <td><span class="status-badge status-<?php echo $r['status']; ?>">
                                    <?php echo $r['status']; ?>
                                </span></td>
                            <td style="font-weight: 700;">₱
                                <?php echo number_format($r['total_amount'], 2); ?>
                            </td>
                            <td>
                                <div style="display: flex; gap: 8px;">
                                    <button class="btn-action" style="color: #3b82f6; background: rgba(59, 130, 246, 0.1);"
                                        onclick='openEditModal(<?php echo json_encode($r); ?>)'><i
                                            class="fas fa-edit"></i></button>
                                    <button class="btn-action" style="color: #ef4444; background: rgba(239, 68, 68, 0.1);"
                                        onclick="openDeleteModal(<?php echo $r['id']; ?>)"><i
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
            <h3 style="margin-bottom: 20px;">Edit Reservation</h3>
            <form method="POST">
                <input type="hidden" name="action" value="edit_reservation">
                <input type="hidden" name="id" id="edit_id">
                <div class="form-group">
                    <label>Customer Name</label>
                    <input type="text" name="customer_name" id="edit_name" class="form-input" required>
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="customer_email" id="edit_email" class="form-input" required>
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status" id="edit_status" class="form-input">
                        <option value="pending">Pending</option>
                        <option value="confirmed">Confirmed</option>
                        <option value="cancelled">Cancelled</option>
                        <option value="completed">Completed</option>
                    </select>
                </div>
                <button type="submit" class="btn-submit">Save Changes</button>
                <button type="button" class="btn-submit" style="background: #ccc; margin-top: 10px;"
                    onclick="closeModal('editModal')">Cancel</button>
            </form>
        </div>
    </div>

    <!-- Delete Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content" style="text-align: center;">
            <i class="fas fa-trash-alt" style="font-size: 40px; color: #ef4444; margin-bottom: 15px;"></i>
            <h3>Purge Reservation?</h3>
            <p style="margin: 10px 0 25px; font-size: 14px;">This will permanently delete the reservation from the
                system database.</p>
            <form method="POST">
                <input type="hidden" name="action" value="delete_reservation">
                <input type="hidden" name="id" id="delete_id">
                <div style="display: flex; gap: 10px;">
                    <button type="button" class="btn-submit" style="background: #f1f5f9; color: #64748b;"
                        onclick="closeModal('deleteModal')">Abondon</button>
                    <button type="submit" class="btn-submit" style="background: #ef4444;">Purge Access</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openEditModal(r) {
            document.getElementById('edit_id').value = r.id;
            document.getElementById('edit_name').value = r.customer_name;
            document.getElementById('edit_email').value = r.customer_email;
            document.getElementById('edit_status').value = r.status;
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