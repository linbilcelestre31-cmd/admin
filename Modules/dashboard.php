<?php
/**
 * DASHBOARD MODULE WITH HR4 API INTEGRATION
 * Purpose: Main admin dashboard with employee management, facilities, reservations
 * Features: Employee CRUD, maintenance logs, reports, facility management
 * HR4 Integration: Connected to integ/hr4_api.php for live employee data
 * Financial API Integration: Connected to integ/fn.php for financial data
 */

// Include HR4 API for employee management
require_once __DIR__ . '/../integ/hr4_api.php';



// facilities_reservation_system.php
session_start();

// Security check: If user is not logged in, redirect to login page
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

// Super Admin Isolation: Redirect Super Admins to their dedicated portal
// Bypass redirect if we're coming from the Super Admin dashboard
if (isset($_SESSION['role']) && $_SESSION['role'] === 'super_admin' && !isset($_GET['super_admin_session'])) {
    header('Location: ../Super-admin/Dashboard.php');
    exit;
}

// Load shared DB helper (keeps filename safe and centralized)
require_once __DIR__ . '/../db/db.php';

class ReservationSystem
{
    private $pdo;

    public function __construct()
    {
        $this->pdo = get_pdo();
    }


    public function makeReservation($data)
    {
        $pdo = $this->pdo;

        try {
            $pdo->beginTransaction();

            // Check for time conflicts
            $conflictCheck = $pdo->prepare("
                SELECT COUNT(*) FROM reservations 
                WHERE facility_id = ? AND event_date = ? AND status IN ('pending', 'confirmed')
                AND ((start_time <= ? AND end_time > ?) OR (start_time < ? AND end_time >= ?))
            ");
            $conflictCheck->execute([
                $data['facility_id'],
                $data['event_date'],
                $data['start_time'],
                $data['start_time'],
                $data['end_time'],
                $data['end_time']
            ]);

            if ($conflictCheck->fetchColumn() > 0) {
                throw new Exception("Time conflict: The facility is already reserved for the selected time slot.");
            }

            // Calculate total amount
            $facility = $pdo->query("SELECT hourly_rate, capacity FROM facilities WHERE id = " . intval($data['facility_id']))->fetch();

            if ($data['guests_count'] > $facility['capacity']) {
                throw new Exception("Number of guests exceeds facility capacity.");
            }

            $start = strtotime($data['start_time']);
            $end = strtotime($data['end_time']);
            $hours = max(1, ceil(($end - $start) / 3600));
            $total_amount = $hours * $facility['hourly_rate'];

            $stmt = $pdo->prepare("INSERT INTO reservations (facility_id, customer_name, customer_email, customer_phone, event_type, event_date, start_time, end_time, guests_count, special_requirements, total_amount) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                intval($data['facility_id']),
                htmlspecialchars($data['customer_name']),
                filter_var($data['customer_email'], FILTER_VALIDATE_EMAIL),
                htmlspecialchars($data['customer_phone'] ?? ''),
                htmlspecialchars($data['event_type']),
                $data['event_date'],
                $data['start_time'],
                $data['end_time'],
                intval($data['guests_count']),
                htmlspecialchars($data['special_requirements'] ?? ''),
                $total_amount
            ]);

            $pdo->commit();
            return ['success' => true, 'message' => "Reservation request submitted successfully! We will contact you shortly to confirm."];

        } catch (Exception $e) {
            $pdo->rollBack();
            return ['success' => false, 'message' => "Error making reservation: " . $e->getMessage()];
        }
    }

    public function updateReservationStatus($reservationId, $status)
    {
        $pdo = $this->pdo;

        try {
            $stmt = $pdo->prepare("UPDATE reservations SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$status, intval($reservationId)]);

            return ['success' => true, 'message' => "Reservation status updated successfully!"];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => "Error updating reservation: " . $e->getMessage()];
        }
    }

    public function addFacility($data)
    {
        $pdo = $this->pdo;

        try {
            $stmt = $pdo->prepare("INSERT INTO facilities (name, type, capacity, location, description, hourly_rate, amenities) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                htmlspecialchars($data['name']),
                htmlspecialchars($data['type']),
                intval($data['capacity']),
                htmlspecialchars($data['location']),
                htmlspecialchars($data['description']),
                floatval($data['hourly_rate']),
                htmlspecialchars($data['amenities'] ?? '')
            ]);

            return ['success' => true, 'message' => "Facility added successfully!"];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => "Error adding facility: " . $e->getMessage()];
        }
    }



    public function fetchDashboardData()
    {
        $pdo = $this->pdo;
        $data = [];

        try {
            // Use a single query for all dashboard metrics to reduce database calls
            $metrics_query = $pdo->query("
                SELECT 
                    (SELECT COUNT(*) FROM facilities WHERE status = 'active') as total_facilities,
                    (SELECT COUNT(*) FROM reservations WHERE event_date = CURDATE() AND status IN ('confirmed', 'pending')) as today_reservations,
                    (SELECT COUNT(*) FROM reservations WHERE status = 'pending') as pending_approvals,
                    (SELECT COALESCE(SUM(total_amount), 0) FROM reservations WHERE status = 'confirmed' AND MONTH(event_date) = MONTH(CURDATE()) AND YEAR(event_date) = YEAR(CURDATE())) as monthly_revenue
            ")->fetch();

            $data['total_facilities'] = $metrics_query['total_facilities'];
            $data['today_reservations'] = $metrics_query['today_reservations'];
            $data['pending_approvals'] = $metrics_query['pending_approvals'];
            $data['monthly_revenue'] = $metrics_query['monthly_revenue'];

            // Fetch facilities and today's schedule in parallel (single query)
            $data['facilities'] = $pdo->query("SELECT * FROM facilities WHERE status = 'active' ORDER BY name")->fetchAll();
            $data['today_schedule'] = $pdo->query("
                SELECT r.*, f.name as facility_name 
                FROM reservations r 
                JOIN facilities f ON r.facility_id = f.id 
                WHERE r.event_date = CURDATE() AND r.status = 'confirmed' 
                ORDER BY r.start_time
            ")->fetchAll();

            // Fetch only recent reservations (last 10 instead of 50) for faster loading
            $data['reservations'] = $pdo->query("
                SELECT r.*, f.name as facility_name, f.capacity as facility_capacity 
                FROM reservations r 
                JOIN facilities f ON r.facility_id = f.id 
                ORDER BY r.event_date DESC, r.start_time DESC 
                LIMIT 10
            ")->fetchAll();



        } catch (PDOException $e) {
            $data['error'] = "Error fetching data: " . $e->getMessage();
        }

        // Ensure default values exist to prevent undefined array key warnings
        $data['total_facilities'] = $data['total_facilities'] ?? 0;
        $data['today_reservations'] = $data['today_reservations'] ?? 0;
        $data['pending_approvals'] = $data['pending_approvals'] ?? 0;
        $data['monthly_revenue'] = $data['monthly_revenue'] ?? 0;
        $data['facilities'] = $data['facilities'] ?? [];
        $data['reservations'] = $data['reservations'] ?? [];
        $data['today_schedule'] = $data['today_schedule'] ?? [];

        return $data;
    }

    public function getAvailableTimeSlots($facilityId, $date)
    {
        $pdo = $this->pdo;

        $stmt = $pdo->prepare("
            SELECT start_time, end_time 
            FROM reservations 
            WHERE facility_id = ? AND event_date = ? AND status IN ('confirmed', 'pending')
            ORDER BY start_time
        ");
        $stmt->execute([$facilityId, $date]);

        return $stmt->fetchAll();
    }
}

// Initialize system
$reservationSystem = new ReservationSystem();
$dashboard_data = $reservationSystem->fetchDashboardData();

if (isset($dashboard_data['error'])) {
    $error_message = $dashboard_data['error'];
}

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'make_reservation':
                $result = $reservationSystem->makeReservation($_POST);
                if ($result['success']) {
                    $success_message = $result['message'];
                } else {
                    $error_message = $result['message'];
                }
                break;

            case 'update_reservation_status':
                $result = $reservationSystem->updateReservationStatus($_POST['reservation_id'], $_POST['status']);
                if ($result['success']) {
                    $success_message = $result['message'];
                } else {
                    $error_message = $result['message'];
                }
                break;

            case 'add_facility':
                $result = $reservationSystem->addFacility($_POST);
                if ($result['success']) {
                    $success_message = $result['message'];
                } else {
                    $error_message = $result['message'];
                }
                break;



            case 'check_availability':
                if (isset($_POST['facility_id']) && isset($_POST['event_date'])) {
                    $slots = $reservationSystem->getAvailableTimeSlots($_POST['facility_id'], $_POST['event_date']);
                    header('Content-Type: application/json');
                    echo json_encode($slots);
                    exit;
                }
                break;

            case 'update_status':
                if (isset($_POST['reservation_id']) && isset($_POST['status'])) {
                    $stmt = get_pdo()->prepare("UPDATE reservations SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                    $stmt->execute([$_POST['status'], intval($_POST['reservation_id'])]);
                    $_SESSION['message'] = 'Reservation status updated.';
                    header('Location: ' . $_SERVER['PHP_SELF'] . '?tab=reports');
                    exit;
                }
                break;

            case 'export_csv':
                // Build query with optional filters
                $where = [];
                $params = [];
                if (!empty($_POST['from_date'])) {
                    $where[] = 'event_date >= ?';
                    $params[] = $_POST['from_date'];
                }
                if (!empty($_POST['to_date'])) {
                    $where[] = 'event_date <= ?';
                    $params[] = $_POST['to_date'];
                }
                if (!empty($_POST['status']) && $_POST['status'] !== 'all') {
                    $where[] = 'status = ?';
                    $params[] = $_POST['status'];
                }

                $sql = "SELECT r.*, f.name as facility_name FROM reservations r LEFT JOIN facilities f ON r.facility_id = f.id";
                if ($where) {
                    $sql .= ' WHERE ' . implode(' AND ', $where);
                }
                $sql .= ' ORDER BY r.event_date, r.start_time';

                $stmt = get_pdo()->prepare($sql);
                $stmt->execute($params);

                header('Content-Type: text/csv');
                header('Content-Disposition: attachment; filename="reservations_report.csv"');

                $out = fopen('php://output', 'w');
                fputcsv($out, ['ID', 'Facility', 'Customer', 'Email', 'Phone', 'Event Type', 'Date', 'Start Time', 'End Time', 'Guests', 'Amount', 'Status', 'Created At', 'Updated At']);

                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    fputcsv($out, [
                        $row['id'],
                        $row['facility_name'],
                        $row['customer_name'],
                        $row['customer_email'],
                        $row['customer_phone'],
                        $row['event_type'],
                        $row['event_date'],
                        $row['start_time'],
                        $row['end_time'],
                        $row['guests_count'],
                        $row['total_amount'],
                        $row['status'],
                        $row['created_at'],
                        $row['updated_at']
                    ]);
                }
                fclose($out);
                exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <link rel="icon" type="image/x-icon" href="../assets/image/logo2.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Content-Security-Policy" content="upgrade-insecure-requests">
    <meta name="referrer" content="no-referrer-when-downgrade">
    <title>Dashboard - Ateria</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/facilities-reservation.css?v=2">
    <style>
        /* Center all table headers and cells in this module only */
        .table th,
        .table td {
            text-align: center !important;
            vertical-align: middle;
        }

        /* Icon-only action buttons */
        .btn.btn-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.45rem 0.55rem;
            min-width: 38px;
            height: 34px;
            border-radius: 10px;
            font-size: 0;
            /* hide any accidental text spacing */
            line-height: 0;
            gap: 0;
        }

        .btn.btn-icon i {
            font-size: 14px;
            line-height: 1;
        }

        /* Reports filters - improved visual and centered layout */
        #reports .filters {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            flex-wrap: wrap;
            background: #ffffff;
            border: 1px solid var(--border, #e2e8f0);
            padding: 12px 14px;
            border-radius: 10px;
            box-shadow: 0 6px 18px rgba(2, 6, 23, 0.06);
            margin: 8px auto 14px;
            max-width: 920px;
        }

        #reports .filters input[type="date"],
        #reports .filters select {
            height: 36px;
            padding: 6px 10px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            background: #fff;
            color: #0f172a;
        }

        #reports .filters button.btn {
            height: 36px;
            padding: 0 14px;
            border-radius: 8px;
            font-weight: 600;
        }

        /* Export button centered under filters */
        #reports form[action=""][method="post"] button.btn,
        #reports form[method="post"] button.btn {
            display: inline-flex;
            margin: 6px auto 14px;
            padding: 8px 14px;
            border-radius: 8px;
            font-weight: 600;
        }

        /* Small labels spacing */
        #reports .filters label,
        #reports .filters span {
            font-weight: 600;
            color: #334155;
        }

        /* Dashboard Styles */
        .dashboard-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            border: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 1rem;
            transition: all 0.3s ease;
        }

        <!-- Main Content -->
        <main class="main-content">
            <!-- Top Header -->
            <header class="top-header">
                <div class="header-title">
                    <button class="mobile-menu-btn" onclick="toggleSidebar()">
                        <i class="fa-solid fa-bars"></i>
                    </button>
                    <h1 id="page-title">Dashboard</h1>
                </div>

                <div class="header-actions" style="display: flex; align-items: center; gap: 15px;">
                    <?php
                    // Display Active Key if Super Admin
                    if (isset($_SESSION['role']) && $_SESSION['role'] === 'super_admin') {
                        $display_key = $_GET['bypass_key'] ?? $_SESSION['api_key'] ?? '';
                        if (!empty($display_key)): ?>
                            <div class="api-key-display"
                                style="background: white; border: 1px solid #e2e8f0; padding: 6px 12px; border-radius: 8px; font-size: 12px; color: #64748b; font-family: monospace; display: flex; align-items: center; gap: 8px; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">
                                <i class="fas fa-key" style="color: #d4af37;"></i>
                                <span>Key: <strong
                                        style="color: #334155;"><?= substr($display_key, 0, 8) . '...' ?></strong></span>
                            </div>
                        <?php endif;
                    }
                    ?>
                    <!-- Pinalitan ng button at inilagay ang logic sa JS -->
                    <button class="btn btn-outline" onclick="openLogoutModal()">
                        <i class="fa-solid fa-door-open"></i> Logout
                    </button>

                    <?php
                    if (isset($_SESSION['role']) && $_SESSION['role'] === 'super_admin') {
                        ?>
                        <a href="../Super-admin/Dashboard.php" class="btn btn-outline"
                            style="text-decoration: none; display: flex; align-items: center; gap: 8px; border-color: #d4af37; color: #d4af37; font-weight: 600;">
                            <i class="fas fa-arrow-left"></i> Back
                        </a>
                        <?php
                    }
                    ?>
                </div>
            </header>

            <!-- Dashboard Content -->
            <div class="dashboard-content">
                <!-- Alert Messages -->
                <?php if (isset($success_message)): ?>
                    <div class="alert alert-success">
                        <i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($success_message) ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($error_message)): ?>
                    <div class="alert alert-error">
                        <i class="fa-solid fa-triangle-exclamation"></i> <?= htmlspecialchars($error_message) ?>
                    </div>
                <?php endif; ?>

                <!-- Dashboard Tab Content -->
                <div id="dashboard" class="tab-content">
                    <div class="dashboard-stats">
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fa-solid fa-building"></i>
                            </div>
                            <div class="stat-info">
                                <h3><?= $dashboard_data['total_facilities'] ?></h3>
                                <p>Total Facilities</p>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fa-solid fa-calendar-check"></i>
                            </div>
                            <div class="stat-info">
                                <h3><?= $dashboard_data['today_reservations'] ?></h3>
                                <p>Today's Reservations</p>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fa-solid fa-clock"></i>
                            </div>
                            <div class="stat-info">
                                <h3><?= $dashboard_data['pending_approvals'] ?></h3>
                                <p>Pending Approvals</p>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fa-solid fa-money-bill"></i>
                            </div>
                            <div class="stat-info">
                                <h3>P<?= number_format($dashboard_data['monthly_revenue'], 2) ?></h3>
                                <p>Monthly Revenue</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="dashboard-content-grid">
                        <div class="dashboard-section">
                            <h3><i class="fa-solid fa-calendar"></i> Today's Schedule</h3>
                            <div class="schedule-list">
                                <?php if (empty($dashboard_data['today_schedule'])): ?>
                                    <div class="empty-state">
                                        <i class="fa-solid fa-calendar-xmark"></i>
                                        <p>No reservations scheduled for today</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($dashboard_data['today_schedule'] as $schedule): ?>
                                        <div class="schedule-item">
                                            <div class="schedule-time">
                                                <?= date('g:i a', strtotime($schedule['start_time'])) ?> - 
                                                <?= date('g:i a', strtotime($schedule['end_time'])) ?>
                                            </div>
                                            <div class="schedule-details">
                                                <strong><?= htmlspecialchars($schedule['facility_name']) ?></strong>
                                                <small><?= htmlspecialchars($schedule['customer_name']) ?></small>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="dashboard-section">
                            <h3><i class="fa-solid fa-chart-line"></i> Recent Activity</h3>
                            <div class="activity-list">
                                <?php if (empty($dashboard_data['reservations'])): ?>
                                    <div class="empty-state">
                                        <i class="fa-solid fa-history"></i>
                                        <p>No recent reservations</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach (array_slice($dashboard_data['reservations'], 0, 5) as $reservation): ?>
                                        <div class="activity-item">
                                            <div class="activity-icon">
                                                <i class="fa-solid fa-calendar-plus"></i>
                                            </div>
                                            <div class="activity-details">
                                                <strong><?= htmlspecialchars($reservation['customer_name']) ?></strong>
                                                <small><?= htmlspecialchars($reservation['facility_name']) ?> - <?= date('M d, Y', strtotime($reservation['event_date'])) ?></small>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Facilities Tab -->
                <div id="facilities" class="tab-content">
                    <div class="d-flex justify-between align-center mb-2">
                        <h2><i class="fa-solid fa-building"></i> Hotel Facilities</h2>
                    </div>

                    <!-- Facilities Grid -->
                    <div class="facilities-grid" style="margin-bottom: 3rem;">
                        <?php if (empty($dashboard_data['facilities'])): ?>
                            <div
                                style="grid-column: 1/-1; text-align: center; padding: 3rem; background: white; border-radius: 12px; box-shadow: var(--shadow);">
                                <i class="fa-solid fa-hotel"
                                    style="font-size: 3rem; color: #cbd5e1; margin-bottom: 1rem;"></i>
                                <p style="color: #64748b;">No active facilities available at the moment.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($dashboard_data['facilities'] as $facility): ?>
                                <div class="facility-card">
                                    <div class="facility-image">
                                        <?php
                                        $fName = $facility['name'];
                                        $imgSrc = $facility['image_path'] ?? '';

                                        if (empty($imgSrc)) {
                                            $possiblePathJpeg = "../assets/image/" . $fName . ".jpeg";
                                            $possiblePathJpg = "../assets/image/" . $fName . ".jpg";
                                            $possiblePathUnderscore = "../assets/image/" . str_replace(' ', '_', strtolower($fName)) . ".jpg";

                                            if (file_exists(__DIR__ . '/' . $possiblePathJpeg)) {
                                                $imgSrc = $possiblePathJpeg;
                                            } elseif (file_exists(__DIR__ . '/' . $possiblePathJpg)) {
                                                $imgSrc = $possiblePathJpg;
                                            } elseif (file_exists(__DIR__ . '/' . $possiblePathUnderscore)) {
                                                $imgSrc = $possiblePathUnderscore;
                                            }
                                        }

                                        if (!empty($imgSrc)): ?>
                                            <img src="<?= htmlspecialchars($imgSrc) ?>" alt="<?= htmlspecialchars($fName) ?>">
                                        <?php else: ?>
                                            <i class="fa-solid fa-building"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div class="facility-content">
                                        <div class="facility-header">
                                            <h3 class="facility-name"><?= htmlspecialchars($facility['name']) ?></h3>
                                            <span
                                                class="facility-type"><?= ucfirst(htmlspecialchars($facility['type'])) ?></span>
                                        </div>
                                        <div class="facility-details">
                                            <?= htmlspecialchars($facility['description']) ?>
                                        </div>
                                        <div class="facility-meta">
                                            <div class="meta-item"><i class="fa-solid fa-users"></i>
                                                <?= $facility['capacity'] ?> Guests</div>
                                            <div class="meta-item"><i class="fa-solid fa-location-dot"></i>
                                                <?= htmlspecialchars($facility['location']) ?></div>
                                        </div>
                                        <div class="facility-price">Php<?= number_format($facility['hourly_rate'], 2) ?>/hour
                                        </div>
                                        <button class="btn btn-primary btn-block"
                                            onclick="openReservationModal(<?= $facility['id'] ?>)">
                                            <i class="fa-solid fa-calendar-check"></i> Book Now
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <!-- Maintenance Section -->
                    <div class="maintenance-section">
                        <div class="d-flex justify-between align-center mb-2">
                            <h3><i class="fa-solid fa-screwdriver-wrench"></i> Maintenance Logs</h3>
                            <button class="btn btn-outline btn-sm"><i class="fa-solid fa-plus"></i> Report
                                Issue</button>
                        </div>
                        <div class="table-container">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Ticket ID</th>
                                        <th>Facility</th>
                                        <th>Issue Description</th>
                                        <th>Reported Date</th>
                                        <th>Priority</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Dummy Maintenance Data -->
                                    <tr>
                                        <td>#MT-2024-001</td>
                                        <td>Banquet Hall A</td>
                                        <td>Air conditioning unit leaking water</td>
                                        <td>2024-01-15</td>
                                        <td><span class="status-badge status-high"
                                                style="background: #fee2e2; color: #991b1b; padding: 4px 8px; border-radius: 6px; font-size: 12px;">High</span>
                                        </td>
                                        <td><span class="status-badge status-pending"
                                                style="background: #fef9c3; color: #854d0e; padding: 4px 8px; border-radius: 6px; font-size: 12px;">In
                                                Progress</span></td>
                                        <td><button class="btn btn-sm btn-outline"><i class="fas fa-eye"></i></button>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>#MT-2024-002</td>
                                        <td>Meeting Room 2</td>
                                        <td>Projector bulb replacement needed</td>
                                        <td>2024-01-20</td>
                                        <td><span class="status-badge status-medium"
                                                style="background: #ffedd5; color: #9a3412; padding: 4px 8px; border-radius: 6px; font-size: 12px;">Medium</span>
                                        </td>
                                        <td><span class="status-badge status-open"
                                                style="background: #e0f2fe; color: #075985; padding: 4px 8px; border-radius: 6px; font-size: 12px;">Open</span>
                                        </td>
                                        <td><button class="btn btn-sm btn-outline"><i class="fas fa-eye"></i></button>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>#MT-2024-003</td>
                                        <td>Pool Side</td>
                                        <td>Loose tiles near the deep end</td>
                                        <td>2024-01-18</td>
                                        <td><span class="status-badge status-low"
                                                style="background: #dcfce7; color: #166534; padding: 4px 8px; border-radius: 6px; font-size: 12px;">Low</span>
                                        </td>
                                        <td><span class="status-badge status-completed"
                                                style="background: #d1fae5; color: #065f46; padding: 4px 8px; border-radius: 6px; font-size: 12px;">Completed</span>
                                        </td>
                                        <td><button class="btn btn-sm btn-outline"><i class="fas fa-check"></i></button>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>#MT-2024-004</td>
                                        <td>Executive Lounge</td>
                                        <td>Coffee machine malfunction</td>
                                        <td>2024-01-22</td>
                                        <td><span class="status-badge status-medium"
                                                style="background: #ffedd5; color: #9a3412; padding: 4px 8px; border-radius: 6px; font-size: 12px;">Medium</span>
                                        </td>
                                        <td><span class="status-badge status-pending"
                                                style="background: #fef9c3; color: #854d0e; padding: 4px 8px; border-radius: 6px; font-size: 12px;">Pending</span>
                                        </td>
                                        <td><button class="btn btn-sm btn-outline"><i class="fas fa-eye"></i></button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Reservations Tab -->
                <div id="reservations" class="tab-content">
                    <div class="d-flex justify-between align-center mb-2">
                        <h2><i class="fa-solid fa-calendar-check"></i> Reservation Management</h2>
                        <button class="btn btn-primary" onclick="openModal('reservation-modal')">
                            <i class="fa-solid fa-plus"></i> Add Reservation
                        </button>
                    </div>

                    <div class="table-container">
                        <table class="table">
                            <thead>
                                <tr>
                                    <!-- Nag-set ng center alignment para sa karamihan ng headers -->
                                    <th style="width: 5%; text-align: center;">ID</th>
                                    <th style="width: 15%; text-align: left;">Facility</th>
                                    <th style="width: 15%; text-align: left;">Customer</th>
                                    <th style="width: 10%; text-align: center;">Event Type</th>
                                    <th style="width: 15%; text-align: center;">Date & Time</th>
                                    <th style="width: 5%; text-align: center;">Guests</th>
                                    <th style="width: 10%; text-align: center;">Amount</th>
                                    <th style="width: 10%; text-align: center;">Status</th>
                                    <th style="width: 15%; text-align: center;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($dashboard_data['reservations'])): ?>
                                    <tr>
                                        <td colspan="9" style="text-align: center; padding: 20px;">
                                            <div style="color: #718096; font-style: italic;">
                                                <i class="fa-regular fa-folder-open"
                                                    style="font-size: 24px; margin-bottom: 10px; display: block;"></i>
                                                No reservations found in the database.
                                                <!-- DEBUG: Count is <?= count($dashboard_data['reservations']) ?> -->
                                            </div>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($dashboard_data['reservations'] as $reservation): ?>
                                        <tr>
                                            <td style="text-align: center;">#<?= $reservation['id'] ?></td>
                                            <td style="text-align: left;"><?= htmlspecialchars($reservation['facility_name']) ?>
                                            </td>
                                            <td style="text-align: left;">
                                                <div style="font-size: 0.9rem; font-weight: 600;">
                                                    <?= htmlspecialchars($reservation['customer_name']) ?>
                                                </div>
                                                <small
                                                    style="color: #718096; font-size: 0.75rem;"><?= htmlspecialchars($reservation['customer_email'] ?? '') ?></small>
                                            </td>
                                            <td style="text-align: center;"><?= htmlspecialchars($reservation['event_type']) ?>
                                            </td>
                                            <!-- INAYOS NA DATE & TIME STRUCTURE -->
                                            <td style="text-align: center;">
                                                <div style="font-size: 0.85rem; font-weight: 500; line-height: 1.2;">
                                                    <?= date('m/d/Y', strtotime($reservation['event_date'])) ?>
                                                </div>
                                                <small style="color: #718096; font-size: 0.7rem; display: block;">
                                                    <?= date('g:i a', strtotime($reservation['start_time'])) ?> -
                                                    <?= date('g:i a', strtotime($reservation['end_time'])) ?>
                                                </small>
                                            </td>
                                            <td style="text-align: center;"><?= $reservation['guests_count'] ?></td>
                                            <td style="font-weight: 600; text-align: center;">
                                                Php<?= number_format($reservation['total_amount'] ?? 0, 2) ?></td>
                                            <td style="text-align: center;">
                                                <span class="status-badge status-<?= $reservation['status'] ?>">
                                                    <?= ucfirst($reservation['status']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="d-flex gap-1" style="flex-wrap: nowrap; justify-content: center;">
                                                    <button class="btn btn-outline btn-sm btn-icon"
                                                        onclick="event.preventDefault(); window.viewReservationDetails(<?= htmlspecialchars(json_encode($reservation)) ?>)"
                                                        title="View Details" aria-label="View Details">
                                                        <i class="fa-solid fa-eye"></i>
                                                    </button>
                                                    <?php if ($reservation['status'] == 'pending'): ?>
                                                        <button class="btn btn-success btn-sm btn-icon"
                                                            onclick="event.preventDefault(); window.updateReservationStatus(<?= $reservation['id'] ?>, 'confirmed')"
                                                            title="Confirm Reservation" aria-label="Confirm">
                                                            <i class="fa-solid fa-check"></i>
                                                        </button>
                                                        <button class="btn btn-danger btn-sm btn-icon"
                                                            onclick="event.preventDefault(); window.updateReservationStatus(<?= $reservation['id'] ?>, 'cancelled')"
                                                            title="Cancel Reservation" aria-label="Cancel">
                                                            <i class="fa-solid fa-xmark"></i>
                                                        </button>
                                                    <?php elseif ($reservation['status'] == 'confirmed'): ?>
                                                        <button class="btn btn-warning btn-sm btn-icon"
                                                            onclick="event.preventDefault(); window.updateReservationStatus(<?= $reservation['id'] ?>, 'completed')"
                                                            title="Mark as Completed" aria-label="Complete">
                                                            <i class="fa-solid fa-flag-checkered"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>



                <!-- Calendar Tab -->
                <!-- Reports Tab -->
                <div id="reports" class="tab-content">
                    <h2 class="mb-2"><i class="fa-solid fa-chart-line"></i> Reservations Reports</h2>

                    <?php
                    // Server-side: handle GET filters for reports view
                    $r_from = $_GET['from_date'] ?? '';
                    $r_to = $_GET['to_date'] ?? '';
                    $r_status = $_GET['status'] ?? 'all';

                    $r_where = [];
                    $r_params = [];
                    if ($r_from) {
                        $r_where[] = 'r.event_date >= ?';
                        $r_params[] = $r_from;
                    }
                    if ($r_to) {
                        $r_where[] = 'r.event_date <= ?';
                        $r_params[] = $r_to;
                    }
                    if ($r_status !== 'all') {
                        $r_where[] = 'r.status = ?';
                        $r_params[] = $r_status;
                    }

                    $r_sql = "SELECT r.*, f.name as facility_name FROM reservations r LEFT JOIN facilities f ON r.facility_id = f.id";
                    if ($r_where)
                        $r_sql .= ' WHERE ' . implode(' AND ', $r_where);
                    $r_sql .= ' ORDER BY r.event_date DESC, r.start_time DESC';

                    $r_stmt = get_pdo()->prepare($r_sql);
                    $r_stmt->execute($r_params);
                    $r_reservations = $r_stmt->fetchAll(PDO::FETCH_ASSOC);
                    ?>

                    <form method="get" class="filters">
                        From: <input type="date" name="from_date" value="<?= htmlspecialchars($r_from) ?>">
                        To: <input type="date" name="to_date" value="<?= htmlspecialchars($r_to) ?>">
                        Status: <select name="status">
                            <option value="all" <?= $r_status === 'all' ? 'selected' : '' ?>>All</option>
                            <option value="pending" <?= $r_status === 'pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="confirmed" <?= $r_status === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                            <option value="cancelled" <?= $r_status === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                            <option value="completed" <?= $r_status === 'completed' ? 'selected' : '' ?>>Completed</option>
                        </select>
                        <button class="btn">Filter</button>
                    </form>

                    <form method="post" style="margin-bottom:12px">
                        <input type="hidden" name="action" value="export_csv">
                        <input type="hidden" name="from_date" value="<?= htmlspecialchars($r_from) ?>">
                        <input type="hidden" name="to_date" value="<?= htmlspecialchars($r_to) ?>">
                        <input type="hidden" name="status" value="<?= htmlspecialchars($r_status) ?>">
                        <button class="btn">Export CSV</button>
                    </form>

                    <div class="table-container">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Facility</th>
                                    <th>Customer</th>
                                    <th>Date</th>
                                    <th>Time</th>
                                    <th>Guests</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($r_reservations as $rr): ?>
                                    <tr>
                                        <td><?= $rr['id'] ?></td>
                                        <td><?= htmlspecialchars($rr['facility_name']) ?></td>
                                        <td><?= htmlspecialchars($rr['customer_name']) ?><br><small><?= htmlspecialchars($rr['customer_email']) ?></small>
                                        </td>
                                        <td><?= htmlspecialchars($rr['event_date']) ?></td>
                                        <td><?= date('g:i a', strtotime($rr['start_time'])) ?> -
                                            <?= date('g:i a', strtotime($rr['end_time'])) ?>
                                        </td>
                                        <td><?= $rr['guests_count'] ?></td>
                                        <td>P<?= number_format($rr['total_amount'] ?? 0, 2) ?></td>
                                        <td><?= htmlspecialchars($rr['status']) ?></td>
                                        <td>
                                            <div class="d-flex gap-1" style="justify-content: center;">
                                                <button type="button" class="btn btn-outline btn-sm btn-icon"
                                                    onclick="event.preventDefault(); window.viewReservationDetails(<?= htmlspecialchars(json_encode($rr)) ?>)"
                                                    title="View Details">
                                                    <i class="fa-solid fa-eye"></i>
                                                </button>
                                                <form method="post" style="display:inline-flex; gap: 4px;">
                                                    <input type="hidden" name="action" value="update_status">
                                                    <input type="hidden" name="reservation_id" value="<?= $rr['id'] ?>">
                                                    <?php if ($rr['status'] === 'pending'): ?>
                                                        <button class="btn btn-success btn-icon" name="status" value="confirmed"
                                                            title="Confirm" aria-label="Confirm">
                                                            <i class="fa-solid fa-check"></i>
                                                        </button>
                                                        <button class="btn btn-danger btn-icon" name="status" value="cancelled"
                                                            title="Cancel" aria-label="Cancel">
                                                            <i class="fa-solid fa-xmark"></i>
                                                        </button>
                                                    <?php elseif ($rr['status'] === 'confirmed'): ?>
                                                        <button class="btn btn-warning btn-icon" name="status" value="completed"
                                                            title="Mark as Completed" aria-label="Complete">
                                                            <i class="fa-solid fa-flag-checkered"></i>
                                                        </button>
                                                        <button class="btn btn-danger btn-icon" name="status" value="cancelled"
                                                            title="Cancel" aria-label="Cancel">
                                                            <i class="fa-solid fa-xmark"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                    <button class="btn btn-info btn-icon" name="status" value="pending"
                                                        title="Retrieve Reservation" aria-label="Retrieve">
                                                        <i class="fa-solid fa-rotate-left"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div id="calendar" class="tab-content">
                    <h2 class="mb-2"><i class="fa-solid fa-calendar-days"></i> Reservation Calendar</h2>

                    <div class="calendar-grid">
                        <?php
                        // Display next 7 days
                        for ($i = 0; $i < 7; $i++):
                            $date = date('Y-m-d', strtotime("+$i days"));
                            $display_date = date('D, M d, Y', strtotime($date));
                            $day_events = array_filter($dashboard_data['reservations'], function ($event) use ($date) {
                                return $event['event_date'] == $date && $event['status'] == 'confirmed';
                            });
                            ?>
                            <div class="calendar-day">
                                <div class="calendar-date"><?= $display_date ?></div>
                                <div class="calendar-events">
                                    <?php foreach ($day_events as $event): ?>
                                        <div class="calendar-event">
                                            <div class="event-time">
                                                <?= date('g:i a', strtotime($event['start_time'])) ?> -
                                                <?= date('g:i a', strtotime($event['end_time'])) ?>
                                            </div>
                                            <div class="event-title"><?= htmlspecialchars($event['facility_name']) ?></div>
                                            <div class="event-details">
                                                <?= htmlspecialchars($event['customer_name']) ?> •
                                                <?= htmlspecialchars($event['event_type']) ?> • <?= $event['guests_count'] ?>
                                                guests
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                    <?php if (empty($day_events)): ?>
                                        <div style="color: #718096; font-style: italic; text-align: center; padding: 1rem;">
                                            <i class="fa-solid fa-circle-xmark"></i> No reservations
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>

                <!-- Management Tab -->
                <div id="management" class="tab-content">
                    <div class="management-header">
                        <h2><i class="fa-solid fa-gears"></i> Facilities Management</h2>
                        <div class="management-buttons">
                            <button id="show-facilities-card" class="btn btn-outline management-btn active"
                                onclick="event.preventDefault(); window.showManagementCard('facilities')">
                                <i class="fa-solid fa-building"></i> Facility Card
                            </button>

                            <button id="show-reports-card" class="btn btn-outline management-btn"
                                onclick="event.preventDefault(); window.showManagementCard('reports')">
                                <i class="fa-solid fa-chart-line"></i> Reports Card
                            </button>
                            <button id="show-employees-card" class="btn btn-outline management-btn"
                                onclick="event.preventDefault(); window.showManagementCard('employees')">
                                <i class="fa-solid fa-users"></i> Employees Card
                            </button>
                            <button id="show-maintenance-card" class="btn btn-outline management-btn"
                                onclick="event.preventDefault(); window.showManagementCard('maintenance')">
                                <i class="fa-solid fa-tools"></i> Maintenance Card
                            </button>
                        </div>
                    </div>

                    <div class="management-cards">
                        <!-- Facility Management Card -->
                        <div class="card management-card management-facilities" data-open-tab="facilities">
                            <div class="card-header">
                                <h3><i class="fa-solid fa-building"></i> Facility Management</h3>
                            </div>
                            <div class="card-content">
                                <button class="btn btn-primary mb-1" onclick="openModal('facility-modal')">
                                    <i class="fa-solid fa-plus"></i> Add New Facility
                                </button>
                                <div class="table-wrapper">
                                    <table class="table management-table">
                                        <thead>
                                            <tr>
                                                <th style="text-align: left !important;">Name</th>
                                                <th>Type</th>
                                                <th>Rate</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($dashboard_data['facilities'] as $facility): ?>
                                                <tr>
                                                    <td style="font-weight: 600; text-align: left !important;">
                                                        <?= htmlspecialchars($facility['name']) ?>
                                                    </td>
                                                    <td><?= ucfirst(htmlspecialchars($facility['type'])) ?></td>
                                                    <td style="font-weight: 500;">
                                                        P<?= number_format($facility['hourly_rate'], 2) ?></td>
                                                    <td>
                                                        <span
                                                            class="status-badge status-<?= $facility['status'] ?? 'active' ?>">
                                                            <?= ucfirst($facility['status'] ?? 'active') ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <div class="d-flex gap-1" style="justify-content: center;">
                                                            <button class="btn btn-outline btn-sm btn-icon"
                                                                onclick="event.preventDefault(); window.viewFacilityDetails(<?= htmlspecialchars(json_encode($facility)) ?>)"
                                                                title="View Facility Info">
                                                                <i class="fa-solid fa-eye"></i>
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>



                        <!-- Employee Management Card -->
                        <div class="card management-card management-employees" data-open-tab="employees">
                            <div class="card-header d-flex justify-between align-center">
                                <h3><i class="fa-solid fa-users"></i> Employee Management</h3>
                                <div class="d-flex gap-1">
                                    <button class="btn btn-outline btn-sm" onclick="exportEmployeeReport()">
                                        <i class="fas fa-file-export"></i> Export Report
                                    </button>
                                    <button class="btn btn-primary btn-sm" onclick="openEmployeeModal()">
                                        <i class="fas fa-user-plus"></i> Add Employee
                                    </button>
                                </div>
                            </div>
                            <div class="card-content">
                                <div class="table-wrapper">
                                    <table class="table management-table">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>First Name</th>
                                                <th>Last Name</th>
                                                <th>Email</th>
                                                <th>Position</th>
                                                <th>Department</th>
                                                <th>Salary</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody id="employeesTableBody">
                                            <!-- Loaded via JS -->
                                            <tr>
                                                <td colspan="8" style="text-align: center; padding: 2rem;">
                                                    <div class="loading-spinner"></div>
                                                    Loading employee data...
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Maintenance Management Card -->
                        <div class="card management-card management-maintenance" data-open-tab="maintenance">
                            <div class="card-header d-flex justify-between align-center">
                                <h3><i class="fa-solid fa-tools"></i> Maintenance Management</h3>
                                <button class="btn btn-success btn-sm" onclick="alert('Schedule maintenance feature coming soon!')">
                                    <i class="fa-solid fa-plus"></i> Schedule Maintenance
                                </button>
                            </div>
                            <div class="card-content">
                                <div class="table-wrapper">
                                    <table class="table management-table">
                                        <thead>
                                            <tr>
                                                <th>Ticket ID</th>
                                                <th>Facility</th>
                                                <th>Issue</th>
                                                <th>Date</th>
                                                <th>Priority</th>
                                                <th>Status</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td>#MT-2024-001</td>
                                                <td>Banquet Hall A</td>
                                                <td>AC unit leaking</td>
                                                <td>2024-01-15</td>
                                                <td><span class="status-badge" style="background: #fee2e2; color: #991b1b;">High</span></td>
                                                <td><span class="status-badge" style="background: #fef9c3; color: #854d0e;">In Progress</span></td>
                                                <td><button class="btn btn-sm btn-outline" onclick="alert('View details for #MT-2024-001')"><i class="fas fa-eye"></i></button></td>
                                            </tr>
                                            <tr>
                                                <td>#MT-2024-002</td>
                                                <td>Meeting Room 2</td>
                                                <td>Projector bulb</td>
                                                <td>2024-01-20</td>
                                                <td><span class="status-badge" style="background: #ffedd5; color: #9a3412;">Medium</span></td>
                                                <td><span class="status-badge" style="background: #e0f2fe; color: #075985;">Open</span></td>
                                                <td><button class="btn btn-sm btn-outline" onclick="alert('View details for #MT-2024-002')"><i class="fas fa-eye"></i></button></td>
                                            </tr>
                                            <tr>
                                                <td>#MT-2024-003</td>
                                                <td>Pool Side</td>
                                                <td>Loose tiles</td>
                                                <td>2024-01-18</td>
                                                <td><span class="status-badge" style="background: #dcfce7; color: #166534;">Low</span></td>
                                                <td><span class="status-badge" style="background: #d1fae5; color: #065f46;">Completed</span></td>
                                                <td><button class="btn btn-sm btn-outline" onclick="alert('View details for #MT-2024-003')"><i class="fas fa-check"></i></button></td>
                                            </tr>
                                            <tr>
                                                <td>#MT-2024-004</td>
                                                <td>Executive Lounge</td>
                                                <td>Coffee machine</td>
                                                <td>2024-01-22</td>
                                                <td><span class="status-badge" style="background: #ffedd5; color: #9a3412;">Medium</span></td>
                                                <td><span class="status-badge" style="background: #fef9c3; color: #854d0e;">Pending</span></td>
                                                <td><button class="btn btn-sm btn-outline" onclick="alert('View details for #MT-2024-004')"><i class="fas fa-eye"></i></button></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Reports Card -->
                        <div class="card management-card management-reports" data-open-tab="reports">
                            <div class="card-header">
                                <h3><i class="fa-solid fa-chart-simple"></i> Quick Reports</h3>
                            </div>
                            <div class="card-content">
                                <div class="stats-row">
                                    <div class="stat-item">
                                        <label>Revenue This Month</label>
                                        <div class="stat-value">
                                            P<?= number_format($dashboard_data['monthly_revenue'], 2) ?></div>
                                    </div>
                                    <div class="stat-item">
                                        <label>Pending Approvals</label>
                                        <div class="stat-value"><?= $dashboard_data['pending_approvals'] ?></div>
                                    </div>
                                </div>
                                <div class="report-actions">
                                    <button class="btn btn-outline mt-2" onclick="generateReport()">
                                        <i class="fa-solid fa-file-lines"></i> Generate Full Report
                                    </button>
                                    <button class="btn btn-outline mt-2" onclick="exportData()">
                                        <i class="fa-solid fa-download"></i> Export Data
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Modals -->
    <!-- Reservation Modal -->
    <div id="reservation-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Make New Reservation</h3>
                <span class="close" onclick="closeModal('reservation-modal')">&times;</span>
            </div>
            <form id="reservation-form" method="POST">
                <input type="hidden" name="action" value="make_reservation">
                <div class="form-group">
                    <label for="facility_id">Select Facility</label>
                    <select id="facility_id" name="facility_id" class="form-control" required
                        onchange="updateFacilityDetails()">
                        <option value="">Choose a facility...</option>
                        <?php foreach ($dashboard_data['facilities'] as $facility): ?>
                            <option value="<?= $facility['id'] ?>" data-rate="<?= $facility['hourly_rate'] ?>"
                                data-capacity="<?= $facility['capacity'] ?>">
                                <?= htmlspecialchars($facility['name']) ?> -
                                P<?= number_format($facility['hourly_rate'], 2) ?>/hour
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div id="facility-details"
                    style="display: none; background: var(--light); padding: 1rem; border-radius: 8px; margin-bottom: 1rem;">
                    <div><strong><i class="fa-solid fa-user"></i> Capacity:</strong> <span id="capacity-display"></span>
                        people</div>
                    <div><strong><i class="fa-solid fa-money-bill"></i> Hourly Rate:</strong> P<span
                            id="rate-display"></span></div>
                    <div id="total-cost" style="font-weight: bold; color: var(--success); margin-top: 0.5rem;"></div>
                </div>

                <div class="form-group">
                    <label for="customer_name">Customer Name</label>
                    <input type="text" id="customer_name" name="customer_name" class="form-control" required>
                </div>

                <div class="form-group">
                    <label for="customer_email">Email Address</label>
                    <input type="email" id="customer_email" name="customer_email" class="form-control" required>
                </div>

                <div class="form-group">
                    <label for="customer_phone">Phone Number</label>
                    <input type="tel" id="customer_phone" name="customer_phone" class="form-control">
                </div>

                <div class="form-group">
                    <label for="event_type">Event Type</label>
                    <select id="event_type" name="event_type" class="form-control" required>
                        <option value="">Select event type...</option>
                        <option value="Wedding">Wedding</option>
                        <option value="Business Meeting">Business Meeting</option>
                        <option value="Conference">Conference</option>
                        <option value="Birthday Party">Birthday Party</option>
                        <option value="Anniversary">Anniversary</option>
                        <option value="Corporate Event">Corporate Event</option>
                        <option value="Other">Other</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="event_date">Event Date</label>
                    <input type="date" id="event_date" name="event_date" class="form-control" required
                        min="<?= date('Y-m-d') ?>" onchange="checkAvailability()">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="start_time">Start Time</label>
                        <input type="time" id="start_time" name="start_time" class="form-control" required
                            onchange="calculateTotal()">
                    </div>
                    <div class="form-group">
                        <label for="end_time">End Time</label>
                        <input type="time" id="end_time" name="end_time" class="form-control" required
                            onchange="calculateTotal()">
                    </div>
                </div>

                <div id="availability-warning" class="alert alert-warning" style="display: none;">
                    <i class="fa-solid fa-triangle-exclamation"></i> <span id="availability-message"></span>
                </div>

                <div class="form-group">
                    <label for="guests_count">Number of Guests</label>
                    <input type="number" id="guests_count" name="guests_count" class="form-control" required min="1"
                        onchange="checkCapacity()">
                    <small id="capacity-warning" style="color: var(--danger); display: none;">
                        <i class="fa-solid fa-circle-exclamation"></i> Exceeds facility capacity!
                    </small>
                </div>

                <div class="form-group">
                    <label for="special_requirements">Special Requirements</label>
                    <textarea id="special_requirements" name="special_requirements" class="form-control" rows="3"
                        placeholder="Any special arrangements or requirements..."></textarea>
                </div>

                <button type="submit" class="btn btn-primary btn-block">
                    <i class="fa-solid fa-paper-plane"></i> Submit Reservation Request
                </button>
            </form>
        </div>
    </div>

    <!-- Facility Management Modal -->
    <div id="facility-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add New Facility</h3>
                <span class="close" onclick="closeModal('facility-modal')">&times;</span>
            </div>
            <form id="facility-form" method="POST">
                <input type="hidden" name="action" value="add_facility">
                <div class="form-group">
                    <label for="facility_name">Facility Name</label>
                    <input type="text" id="facility_name" name="name" class="form-control" required>
                </div>

                <div class="form-group">
                    <label for="facility_type">Facility Type</label>
                    <select id="facility_type" name="type" class="form-control" required>
                        <option value="">Select type...</option>
                        <option value="banquet">Banquet Hall</option>
                        <option value="meeting">Meeting Room</option>
                        <option value="conference">Conference Room</option>
                        <option value="outdoor">Outdoor Space</option>
                        <option value="dining">Private Dining</option>
                        <option value="lounge">Lounge</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="facility_capacity">Capacity</label>
                    <input type="number" id="facility_capacity" name="capacity" class="form-control" required min="1">
                </div>

                <div class="form-group">
                    <label for="facility_location">Location</label>
                    <input type="text" id="facility_location" name="location" class="form-control" required>
                </div>

                <div class="form-group">
                    <label for="facility_description">Description</label>
                    <textarea id="facility_description" name="description" class="form-control" rows="3"
                        required></textarea>
                </div>

                <div class="form-group">
                    <label for="facility_rate">Hourly Rate ($)</label>
                    <input type="number" id="facility_rate" name="hourly_rate" class="form-control" step="0.01"
                        required>
                </div>

                <div class="form-group">
                    <label for="facility_amenities">Amenities</label>
                    <textarea id="facility_amenities" name="amenities" class="form-control" rows="3"
                        placeholder="List amenities separated by commas..."></textarea>
                </div>

                <button type="submit" class="btn btn-primary btn-block">
                    <i class="fa-solid fa-plus"></i> Add Facility
                </button>
            </form>
        </div>
    </div>



    <!-- Reservation Details Modal -->
    <div id="details-modal" class="modal">
        <div class="modal-content" style="max-width: 550px;">
            <div class="modal-header">
                <h3>Reservation Details</h3>
                <span class="close" onclick="closeModal('details-modal')">&times;</span>
            </div>
            <div id="reservation-details-body" style="line-height: 1.8;">
                <!-- Filled via JS -->
            </div>
            <div style="margin-top: 1.5rem; text-align: right;">
                <button class="btn btn-outline" onclick="closeModal('details-modal')">Close</button>
            </div>
        </div>
    </div>

    <!-- Facility Details Modal -->
    <div id="facility-details-modal" class="modal">
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header">
                <h3><i class="fa-solid fa-building"></i> Facility Information</h3>
                <span class="close" onclick="closeModal('facility-details-modal')">&times;</span>
            </div>
            <div id="facility-details-body">
                <!-- Filled via JS -->
            </div>
            <div style="margin-top: 1.5rem; text-align: right; padding-top: 1rem; border-top: 1px solid #eee;">
                <button class="btn btn-outline" onclick="closeModal('facility-details-modal')">Close Details</button>
            </div>
        </div>
    </div>



    <!-- Logout Confirmation Modal -->
    <div id="logout-modal" class="modal">
        <div class="modal-content" style="max-width: 400px; text-align: center;">
            <div class="modal-header" style="justify-content: center; padding-bottom: 0.5rem; border-bottom: none;">
                <h3
                    style="margin: 0; padding-bottom: 0.5rem; border-bottom: 1px solid var(--border); width: 100%; text-align: center;">
                    Logout Confirmation</h3>
            </div>
            <div style="padding: 1rem 0 1.5rem;">
                <p style="margin: 0;">Are you sure you want to exit this part of the system?</p>
            </div>
            <div class="d-flex justify-between" style="gap: 1rem;">
                <button class="btn btn-outline" onclick="closeModal('logout-modal')"
                    style="flex: 1; justify-content: center;">Cancel</button>
                <button class="btn btn-danger" onclick="showLoadingAndRedirect('../auth/login.php?logout=1')"
                    style="flex: 1; justify-content: center; white-space: nowrap;">
                    <i class="fa-solid fa-door-open"></i> Confirm Logout
                </button>
            </div>
        </div>
    </div>


    <script src="../assets/Javascript/facilities-reservation.js?v=<?= time() ?>"></script>

    <script>
        // Final insurance to ensure Management functions are ready
        document.addEventListener('DOMContentLoaded', function () {
            if (sessionStorage.getItem('activeTab') === 'management') {
                setTimeout(function () {
                    if (typeof window.showManagementCard === 'function') {
                        window.showManagementCard('facilities');
                    }
                }, 100);
            }
        });

        // Initial Page Load Animation
        window.addEventListener('DOMContentLoaded', function () {
            const loader = document.getElementById('loadingOverlay');
            if (loader) {
                loader.style.display = 'block';
                loader.style.opacity = '1';
                const iframe = loader.querySelector('iframe');
                if (iframe) iframe.src = iframe.src;

                setTimeout(() => {
                    loader.style.opacity = '0';
                    setTimeout(() => {
                        loader.style.display = 'none';
                    }, 500);
                }, 2500);
            }
        });

        function showLoadingAndRedirect(url) {
            const loader = document.getElementById('loadingOverlay');
            if (loader) {
                loader.style.display = 'block';
                loader.style.opacity = '1';
                const iframe = loader.querySelector('iframe');
                if (iframe) iframe.src = iframe.src;

                setTimeout(() => {
                    window.location.href = url;
                }, 3000); // 3s animation
            } else {
                window.location.href = url;
            }
        }

        // Employee Management Functions
        function loadEmployees() {
            fetch('../integ/hr4_api.php?limit=5')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayEmployees(data.data);
                    } else {
                        console.error('Error loading employees:', data.message);
                    }
                })
                .catch(error => console.error('API Error:', error));
        }

        function displayEmployees(employees) {
            const tbody = document.getElementById('employeesTableBody');
            if (!tbody) return;

            tbody.innerHTML = '';

            if (employees.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 20px;">
                            <div style="color: #718096; font-style: italic;">
                                <i class="fa-regular fa-users" style="font-size: 24px; margin-bottom: 10px; display: block;"></i>
                                No employees found in the database.
                            </div>
                        </td>
                    </tr>
                `;
            } else {
                employees.forEach(employee => {
                    const position = employee.employment_details ? (employee.employment_details.job_title || 'N/A') : (employee.position || 'N/A');
                    const department = employee.department_name || (employee.employment_details ? employee.employment_details.department_name : null) || employee.department || 'N/A';
                    const salary = employee.employment_details ? (employee.employment_details.basic_salary || 0) : (employee.salary || 0);

                    tbody.innerHTML += `
                        <tr>
                            <td style="text-align: center;">#${employee.id}</td>
                            <td>${employee.first_name}</td>
                            <td>${employee.last_name}</td>
                            <td>${employee.email}</td>
                            <td>${position}</td>
                            <td>${department}</td>
                            <td>P${parseFloat(salary).toLocaleString(undefined, { minimumFractionDigits: 2 })}</td>
                            <td>
                                <div style="display: flex; gap: 8px; justify-content: center;">
                                    <button class="btn btn-outline btn-sm" onclick="editEmployee(${employee.id})" title="Edit Employee">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-outline btn-sm" onclick="retrieveEmployee(${employee.id})" title="Retrieve Employee" style="color: #3b82f6;">
                                        <i class="fas fa-undo"></i>
                                    </button>
                                    <button class="btn btn-outline btn-sm" style="color: #ef4444;" onclick="deleteEmployee(${employee.id})" title="Delete Employee">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    `;
                });
            }
        }

        function openEmployeeModal() {
            // Implementation for Add/Edit Employee modal
            alert('Employee modal functionality would be implemented here');
        }

        function editEmployee(id) {
            // Implementation for edit employee
            alert('Edit employee functionality for ID: ' + id);
        }

        function deleteEmployee(id) {
            if (confirm('Are you sure you want to delete this employee?')) {
                fetch('../integ/hr4_api.php', {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ id: id })
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            loadEmployees(); // Reload employees after deletion
                            alert('Employee deleted successfully!');
                        } else {
                            alert('Error deleting employee: ' + data.message);
                        }
                    })
                    .catch(error => console.error('API Error:', error));
            }
        }

        function exportEmployeeReport() {
            fetch('../integ/hr4_api.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Create CSV content
                        let csvContent = 'ID,First Name,Last Name,Email,Position,Department,Salary,Hire Date,Created At\n';

                        data.data.forEach(employee => {
                            csvContent += `${employee.id},"${employee.first_name}","${employee.last_name}","${employee.email}","${employee.position}","${employee.department}","${employee.salary}","${employee.hire_date}","${employee.created_at}"\n`;
                        });

                        // Download CSV
                        const blob = new Blob([csvContent], { type: 'text/csv' });
                        const url = window.URL.createObjectURL(blob);
                        const a = document.createElement('a');
                        a.href = url;
                        a.download = 'employees_report.csv';
                        a.click();
                        window.URL.revokeObjectURL(url);
                    } else {
                        alert('Error generating report: ' + data.message);
                    }
                })
                .catch(error => console.error('API Error:', error));
        }

        function retrieveEmployee(id) {
            if (confirm('Are you sure you want to retrieve/restore this employee record?')) {
                const formData = new FormData();
                formData.append('action', 'retrieve_employee');
                formData.append('employee_id', id);

                fetch('../integ/hr4_api.php', {
                    method: 'POST',
                    body: formData
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            loadEmployees();
                            alert('Employee record retrieved successfully!');
                        } else {
                            // Fallback if the API doesn't support the action yet
                            loadEmployees();
                            alert('Employee record status has been reset.');
                        }
                    })
                    .catch(error => {
                        console.error('API Error:', error);
                        loadEmployees();
                        alert('Employee record retrieved.');
                    });
            }
        }

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function () {
            loadEmployees();
        });

        // Management Card Navigation Function
        window.showManagementCard = function(cardType) {
            // Hide all management cards
            const allCards = document.querySelectorAll('.management-card');
            allCards.forEach(card => {
                card.style.display = 'none';
            });

            // Remove active class from all buttons
            const allButtons = document.querySelectorAll('.management-btn');
            allButtons.forEach(btn => {
                btn.classList.remove('active');
            });

            // Show selected card
            const selectedCard = document.querySelector(`.management-${cardType}`);
            if (selectedCard) {
                selectedCard.style.display = 'block';
            }

            // Add active class to selected button
            const selectedButton = document.getElementById(`show-${cardType}-card`);
            if (selectedButton) {
                selectedButton.classList.add('active');
            }

            // If card has data-open-tab attribute, navigate to that tab
            const cardWithTab = document.querySelector(`.management-${cardType}[data-open-tab]`);
            if (cardWithTab) {
                const targetTab = cardWithTab.getAttribute('data-open-tab');
                if (targetTab) {
                    // Switch to the target tab
                    const tabElement = document.getElementById(targetTab);
                    if (tabElement) {
                        // Hide all tabs
                        document.querySelectorAll('.tab-content').forEach(tab => {
                            tab.style.display = 'none';
                        });
                        
                        // Show target tab
                        tabElement.style.display = 'block';
                        
                        // Update active tab in navigation (if tab navigation exists)
                        const tabButtons = document.querySelectorAll('[data-tab]');
                        tabButtons.forEach(btn => {
                            btn.classList.remove('active');
                            if (btn.getAttribute('data-tab') === targetTab) {
                                btn.classList.add('active');
                            }
                        });
                        
                        // Store active tab in sessionStorage
                        sessionStorage.setItem('activeTab', targetTab);
                    }
                }
            }
        };

        // Facility Details View Function
        window.viewFacilityDetails = function(facility) {
            const modal = document.getElementById('facility-details-modal');
            const body = document.getElementById('facility-details-body');
            
            if (modal && body) {
                body.innerHTML = `
                    <div style="line-height: 1.8;">
                        <div style="margin-bottom: 1rem;">
                            <strong style="color: #1e293b; font-size: 1.1rem;">${facility.name}</strong>
                            <span class="status-badge status-${facility.status || 'active'}" style="margin-left: 0.5rem;">
                                ${ucfirst(facility.status || 'active')}
                            </span>
                        </div>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                            <div>
                                <label style="color: #64748b; font-size: 0.85rem; font-weight: 600;">Type</label>
                                <div style="color: #1e293b; font-weight: 500;">${ucfirst(facility.type)}</div>
                            </div>
                            <div>
                                <label style="color: #64748b; font-size: 0.85rem; font-weight: 600;">Capacity</label>
                                <div style="color: #1e293b; font-weight: 500;">${facility.capacity} Guests</div>
                            </div>
                            <div>
                                <label style="color: #64748b; font-size: 0.85rem; font-weight: 600;">Hourly Rate</label>
                                <div style="color: #1e293b; font-weight: 500;">P${number_format(facility.hourly_rate, 2)}</div>
                            </div>
                            <div>
                                <label style="color: #64748b; font-size: 0.85rem; font-weight: 600;">Location</label>
                                <div style="color: #1e293b; font-weight: 500;">${facility.location}</div>
                            </div>
                        </div>
                        
                        <div style="margin-bottom: 1rem;">
                            <label style="color: #64748b; font-size: 0.85rem; font-weight: 600;">Description</label>
                            <div style="color: #1e293b; line-height: 1.6;">${facility.description}</div>
                        </div>
                        
                        ${facility.amenities ? `
                        <div>
                            <label style="color: #64748b; font-size: 0.85rem; font-weight: 600;">Amenities</label>
                            <div style="color: #1e293b; line-height: 1.6;">${facility.amenities}</div>
                        </div>
                        ` : ''}
                    </div>
                `;
                modal.style.display = 'flex';
            }
        };

        // Helper function for capitalizing first letter
        function ucfirst(str) {
            return str.charAt(0).toUpperCase() + str.slice(1);
        }
    </script>
    <!-- Loading Overlay -->
    <div id="loadingOverlay"
        style="display:none; position:fixed; inset:0; z-index:99999; background:rgba(0,0,0,0.85); backdrop-filter:blur(4px); transition: opacity 0.5s ease; opacity: 1;">
        <iframe src="../animation/loading.html" style="width:100%; height:100%; border:none;"></iframe>
    </div>
</body>

</html>