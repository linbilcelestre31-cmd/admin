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
        $this->ensureMaintenanceTableExists();
    }

    private function ensureMaintenanceTableExists()
    {
        try {
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS maintenance_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                item_name VARCHAR(255) NOT NULL,
                description TEXT,
                maintenance_date DATE NOT NULL,
                assigned_staff VARCHAR(255) NOT NULL,
                contact_number VARCHAR(50),
                priority ENUM('low', 'medium', 'high') DEFAULT 'low',
                reported_by VARCHAR(255),
                department VARCHAR(100),
                duration VARCHAR(50),
                status ENUM('pending', 'in-progress', 'completed') DEFAULT 'pending',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            // Alter table if columns don't exist
            try {
                $this->pdo->exec("ALTER TABLE maintenance_logs ADD COLUMN IF NOT EXISTS department VARCHAR(100) AFTER reported_by");
                $this->pdo->exec("ALTER TABLE maintenance_logs ADD COLUMN IF NOT EXISTS duration VARCHAR(50) AFTER department");
            } catch (Exception $e) {
            }

            // Alter table if columns don't exist
            try {
                $this->pdo->exec("ALTER TABLE maintenance_logs ADD COLUMN IF NOT EXISTS priority ENUM('low', 'medium', 'high') DEFAULT 'low' AFTER contact_number");
                $this->pdo->exec("ALTER TABLE maintenance_logs ADD COLUMN IF NOT EXISTS reported_by VARCHAR(255) AFTER priority");
                $this->pdo->exec("ALTER TABLE maintenance_logs ADD COLUMN IF NOT EXISTS is_deleted TINYINT(1) DEFAULT 0");
            } catch (Exception $e) {
            }
        } catch (PDOException $e) {
            // Silently fail or log (app will handle missing table via try-catches in fetch methods)
        }
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
            $stmt = $pdo->prepare("INSERT INTO facilities (name, type, capacity, location, description, hourly_rate, amenities, image_url) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                htmlspecialchars($data['name']),
                htmlspecialchars($data['type']),
                intval($data['capacity']),
                htmlspecialchars($data['location']),
                htmlspecialchars($data['description']),
                floatval($data['hourly_rate']),
                htmlspecialchars($data['amenities'] ?? ''),
                htmlspecialchars($data['image_url'] ?? '')
            ]);

            return ['success' => true, 'message' => "Facility added successfully!"];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => "Error adding facility: " . $e->getMessage()];
        }
    }

    public function deleteFacility($id)
    {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM facilities WHERE id = ?");
            $stmt->execute([$id]);
            return ['success' => true, 'message' => 'Facility deleted successfully.'];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Error deleting facility: ' . $e->getMessage()];
        }
    }

    public function addMaintenanceLog($data)
    {
        $pdo = $this->pdo;
        try {
            // Check for duplicate entry (same item, staff, date) within the last hour to prevent double-submit
            $check = $pdo->prepare("SELECT id FROM maintenance_logs WHERE item_name = ? AND assigned_staff = ? AND maintenance_date = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR) AND is_deleted = 0 LIMIT 1");
            $check->execute([$data['item_name'], $data['assigned_staff'], $data['maintenance_date']]);
            if ($check->fetch()) {
                return ['success' => false, 'message' => "This maintenance log already exists!"];
            }

            $stmt = $pdo->prepare("INSERT INTO maintenance_logs (item_name, description, maintenance_date, assigned_staff, contact_number, priority, reported_by, department, duration, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                htmlspecialchars($data['item_name']),
                htmlspecialchars($data['description'] ?? ''),
                $data['maintenance_date'],
                htmlspecialchars($data['assigned_staff']),
                htmlspecialchars($data['contact_number'] ?? ''),
                $data['priority'] ?? 'low',
                htmlspecialchars($data['reported_by'] ?? 'Staff'),
                htmlspecialchars($data['department'] ?? 'Engineering'),
                htmlspecialchars($data['duration'] ?? '1 hour'),
                $data['status'] ?? 'pending'
            ]);
            return ['success' => true, 'message' => "Maintenance log added successfully!"];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => "Error adding maintenance log: " . $e->getMessage()];
        }
    }

    public function fetchMaintenanceLogs($deleted = false)
    {
        try {
            $where = $deleted ? "WHERE is_deleted = 1" : "WHERE is_deleted = 0";
            // Group by item_name, assigned_staff, and maintenance_date to avoid showing identical duplicates
            $query = $deleted ?
                "SELECT * FROM maintenance_logs $where ORDER BY created_at DESC" :
                "SELECT * FROM maintenance_logs $where GROUP BY item_name, assigned_staff, maintenance_date, description ORDER BY maintenance_date DESC, created_at DESC LIMIT 50";
            return $this->pdo->query($query)->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }

    public function deleteMaintenanceLog($id)
    {
        try {
            $stmt = $this->pdo->prepare("UPDATE maintenance_logs SET is_deleted = 1 WHERE id = ?");
            $stmt->execute([$id]);
            return ['success' => true, 'message' => 'Maintenance log moved to trash.'];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Error deleting maintenance log: ' . $e->getMessage()];
        }
    }

    public function restoreMaintenanceLog($id)
    {
        try {
            $stmt = $this->pdo->prepare("UPDATE maintenance_logs SET is_deleted = 0 WHERE id = ?");
            $stmt->execute([$id]);
            return ['success' => true, 'message' => 'Maintenance log restored successfully.'];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Error restoring maintenance log: ' . $e->getMessage()];
        }
    }

    public function permanentlyDeleteMaintenanceLog($id)
    {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM maintenance_logs WHERE id = ?");
            $stmt->execute([$id]);
            return ['success' => true, 'message' => 'Maintenance log permanently deleted.'];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Error permanently deleting maintenance log: ' . $e->getMessage()];
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
                    (SELECT COALESCE(SUM(total_amount), 0) FROM reservations WHERE status = 'confirmed' AND MONTH(event_date) = MONTH(CURDATE()) AND YEAR(event_date) = YEAR(CURDATE())) as monthly_revenue,
                    (SELECT COUNT(*) FROM maintenance_logs WHERE status != 'completed' AND is_deleted = 0) as pending_maintenance
            ")->fetch();

            $data['total_facilities'] = $metrics_query['total_facilities'];
            $data['today_reservations'] = $metrics_query['today_reservations'];
            $data['pending_approvals'] = $metrics_query['pending_approvals'];
            $data['monthly_revenue'] = $metrics_query['monthly_revenue'];
            $data['pending_maintenance'] = $metrics_query['pending_maintenance'];

            // Fetch facilities and today's schedule in parallel (single query)
            $data['facilities'] = $pdo->query("
                SELECT f.*, 
                (SELECT customer_name FROM reservations r WHERE r.facility_id = f.id AND r.event_date >= CURDATE() AND r.status = 'confirmed' ORDER BY r.event_date ASC, r.start_time ASC LIMIT 1) as next_reserve_name,
                'Not Assigned' as next_assigned_user
                FROM facilities f WHERE f.status = 'active' ORDER BY f.name
            ")->fetchAll();
            $data['today_schedule'] = $pdo->query("
                SELECT r.*, f.name as facility_name 
                FROM reservations r 
                JOIN facilities f ON r.facility_id = f.id 
                WHERE r.event_date = CURDATE() AND r.status = 'confirmed' 
                ORDER BY r.start_time
            ")->fetchAll();

            // Fetch only recent reservations (last 10 instead of 50) for faster loading
            $data['reservations'] = $pdo->query("
                SELECT r.*, f.name as facility_name, f.capacity as facility_capacity, f.image_url 
                FROM reservations r 
                JOIN facilities f ON r.facility_id = f.id 
                ORDER BY r.event_date DESC, r.start_time DESC 
                LIMIT 10
            ")->fetchAll();

            // Maintenance data (cached if possible)
            $data['maintenance_logs'] = $this->fetchMaintenanceLogs();

        } catch (PDOException $e) {
            $data['error'] = "Error fetching data: " . $e->getMessage();
        }

        // Ensure default values exist to prevent undefined array key warnings
        $data['total_facilities'] = $data['total_facilities'] ?? 0;
        $data['today_reservations'] = $data['today_reservations'] ?? 0;
        $data['pending_approvals'] = $data['pending_approvals'] ?? 0;
        $data['monthly_revenue'] = $data['monthly_revenue'] ?? 0;
        $data['pending_maintenance'] = $data['pending_maintenance'] ?? 0;
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

            case 'delete_facility':
                if (isset($_POST['facility_id'])) {
                    $result = $reservationSystem->deleteFacility($_POST['facility_id']);
                    if ($result['success']) {
                        $success_message = $result['message'];
                    } else {
                        $error_message = $result['message'];
                    }
                }
                break;

            case 'add_maintenance':
                $result = $reservationSystem->addMaintenanceLog($_POST);
                if ($result['success']) {
                    $_SESSION['success_message'] = $result['message'];
                    header("Location: dashboard.php?tab=maintenance");
                    exit;
                } else {
                    $error_message = $result['message'];
                }
                break;

            case 'delete_maintenance':
                if (isset($_POST['log_id'])) {
                    $result = $reservationSystem->deleteMaintenanceLog($_POST['log_id']);
                    if ($result['success']) {
                        $success_message = $result['message'];
                    } else {
                        $error_message = $result['message'];
                    }
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

            case 'restore_maintenance':
                if (isset($_POST['log_id'])) {
                    $result = $reservationSystem->restoreMaintenanceLog($_POST['log_id']);
                    if ($result['success']) {
                        $success_message = $result['message'];
                    } else {
                        $error_message = $result['message'];
                    }
                }
                break;

            case 'permanent_delete_maintenance':
                if (isset($_POST['log_id'])) {
                    $result = $reservationSystem->permanentlyDeleteMaintenanceLog($_POST['log_id']);
                    if ($result['success']) {
                        $success_message = $result['message'];
                    } else {
                        $error_message = $result['message'];
                    }
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
                $module = $_POST['module'] ?? 'reservations';
                $from_date = $_POST['from_date'] ?? '';
                $to_date = $_POST['to_date'] ?? '';
                $status = $_POST['status'] ?? 'all';

                $where = [];
                $params = [];
                $sql = "";
                $filename = $module . "_report.csv";
                $headers = [];

                switch ($module) {
                    case 'all':
                        $sql = "
                            (SELECT 'Reservation' as module, r.id, r.customer_name as name, f.name as ref, r.event_date as date, r.status FROM reservations r LEFT JOIN facilities f ON r.facility_id = f.id)
                            UNION ALL
                            (SELECT 'Facility' as module, id, name, location as ref, 'N/A' as date, status FROM facilities)
                            UNION ALL
                            (SELECT 'Document' as module, id, name, case_id as ref, CAST(uploaded_at AS CHAR) as date, 'Archived' as status FROM documents WHERE is_deleted = 0)
                            UNION ALL
                            (SELECT 'Visitor' as module, id, full_name as name, room_number as ref, CAST(checkin_date AS CHAR) as date, status FROM direct_checkins)
                            UNION ALL
                            (SELECT 'Legal' as module, id, name, case_id as ref, CAST(created_at AS CHAR) as date, CAST(risk_score AS CHAR) as status FROM contracts)
                            ORDER BY date DESC
                        ";
                        $headers = ['Module', 'ID', 'Name/Title', 'Reference', 'Date', 'Status'];
                        break;

                    case 'facilities':
                        $sql = "SELECT id, name, type, capacity, location, hourly_rate, status FROM facilities";
                        $headers = ['ID', 'Name', 'Type', 'Capacity', 'Location', 'Rate', 'Status'];
                        break;

                    case 'archiving':
                        $sql = "SELECT id, name, case_id, file_path, uploaded_at FROM documents WHERE is_deleted = 0";
                        $headers = ['ID', 'Document Name', 'Case ID', 'File Path', 'Uploaded At'];
                        break;

                    case 'visitors':
                        $sql = "SELECT id, full_name, email, phone_number as phone, room_number, checkin_date as time_in, checkout_date as time_out, status FROM direct_checkins WHERE 1=1";
                        if ($from_date) {
                            $sql .= " AND DATE(checkin_date) >= ?";
                            $params[] = $from_date;
                        }
                        if ($to_date) {
                            $sql .= " AND DATE(checkin_date) <= ?";
                            $params[] = $to_date;
                        }
                        $headers = ['ID', 'Name', 'Email', 'Phone', 'Facility', 'Time In', 'Time Out', 'Status'];
                        break;

                    case 'legal':
                        $sql = "SELECT id, name, case_id, contract_type, risk_score, created_at FROM contracts";
                        $headers = ['ID', 'Contract Name', 'Case ID', 'Type', 'Risk Score', 'Created At'];
                        break;

                    case 'reservations':
                    default:
                        if ($from_date) {
                            $where[] = 'event_date >= ?';
                            $params[] = $from_date;
                        }
                        if ($to_date) {
                            $where[] = 'event_date <= ?';
                            $params[] = $to_date;
                        }
                        if ($status !== 'all') {
                            $where[] = 'status = ?';
                            $params[] = $status;
                        }

                        $sql = "SELECT r.*, f.name as facility_name FROM reservations r LEFT JOIN facilities f ON r.facility_id = f.id";
                        if ($where) {
                            $sql .= ' WHERE ' . implode(' AND ', $where);
                        }
                        $sql .= ' ORDER BY r.event_date, r.start_time';
                        $headers = ['ID', 'Facility', 'Customer', 'Email', 'Phone', 'Event Type', 'Date', 'Start Time', 'End Time', 'Guests', 'Amount', 'Status', 'Created At', 'Updated At'];
                        break;
                }

                $stmt = get_pdo()->prepare($sql);
                $stmt->execute($params);

                header('Content-Type: text/csv');
                header('Content-Disposition: attachment; filename="' . $filename . '"');

                $out = fopen('php://output', 'w');
                fputcsv($out, $headers);

                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    // For reservations, we might want to pick specific columns if selective SELECT was used, 
                    // but since we used r.*, we format it. For others, we output as is.
                    if ($module === 'reservations') {
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
                    } else {
                        fputcsv($out, array_values($row));
                    }
                }
                fclose($out);
                exit;
        }
    }
}

// Fetch dashboard data AFTER handling any updates
$dashboard_data = $reservationSystem->fetchDashboardData();

if (isset($dashboard_data['error'])) {
    $error_message = $dashboard_data['error'];
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
        .table th,
        .table td {
            text-align: center !important;
            vertical-align: middle;
            font-size: 0.85rem;
            padding: 12px 15px;
            white-space: nowrap;
            border-bottom: 1px solid #edf2f7;
        }

        /* Gold Scrollbar Styles */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb {
            background: #d4af37;
            /* Gold */
            border-radius: 10px;
            border: 2px solid #f1f5f9;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #b8860b;
        }

        .table-container {
            scrollbar-width: thin;
            scrollbar-color: #d4af37 #f1f5f9;
        }

        .table {
            border-spacing: 0 4px;
            border-collapse: separate;
        }

        .table tr {
            transition: all 0.2s;
            background: white;
        }

        .table tr:hover {
            background: #f8fafc;
            transform: scale(1.002);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .table-container {
            overflow-x: auto;
            max-width: 100%;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            border: 1px solid #e2e8f0;
        }

        .table-wrapper {
            max-height: 600px;
            overflow-y: auto;
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

        .facility-type-badge {
            background: #edf2f7;
            color: #4a5568;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        /* View Switcher Enhancements */
        .btn-group button.active {
            color: var(--primary) !important;
            font-weight: 700 !important;
        }

        .btn-group button:not(.active):hover {
            background: rgba(255, 255, 255, 0.5) !important;
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
            margin: 0 auto 14px;
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

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }
    </style>
</head>

<body>
    <div class="container">
        <!-- Mobile Menu Overlay -->
        <div class="mobile-menu-overlay" onclick="closeSidebar()"></div>

        <!-- Sidebar -->
        <?php require_once __DIR__ . '/../include/sidebar.php'; ?>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Top Header -->
            <header class="top-header">
                <div class="header-title">
                    <button class="mobile-menu-btn" onclick="toggleSidebar()">
                        <span class="icon-img-placeholder">☰</span>
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
                        <span class="icon-img-placeholder">🚪</span> Logout
                    </button>

                </div>
            </header>

            <!-- Dashboard Content -->
            <div class="dashboard-content">
                <!-- Alert Messages -->
                <?php
                $success = $success_message ?? $_SESSION['success_message'] ?? null;
                $error = $error_message ?? $_SESSION['error_message'] ?? null;
                unset($_SESSION['success_message'], $_SESSION['error_message']);
                ?>

                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <span class="icon-img-placeholder">✔️</span> <?= htmlspecialchars($success) ?>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-error">
                        <span class="icon-img-placeholder">⚠️</span> <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <!-- Dashboard Tab -->
                <?php require_once __DIR__ . '/../include/dashboard.php'; ?>

                <!-- Facilities Tab -->
                <div id="facilities"
                    class="tab-content <?= (isset($_GET['tab']) && $_GET['tab'] == 'facilities') ? 'active' : '' ?>">
                    <div class="d-flex justify-between align-center mb-2">
                        <h2><span class="icon-img-placeholder">🏢</span> Hotel Facilities</h2>
                        <div class="d-flex gap-1 align-center">
                            <div class="btn-group"
                                style="display: flex; background: #edf2f7; padding: 4px; border-radius: 8px;">
                                <button id="btn-grid-view" class="btn btn-sm active"
                                    onclick="switchFacilityView('grid')"
                                    style="border-radius: 6px; padding: 6px 12px; transition: all 0.2s;">
                                    <i class="fa-solid fa-grip"></i> Grid
                                </button>
                                <button id="btn-list-view" class="btn btn-sm" onclick="switchFacilityView('list')"
                                    style="border-radius: 6px; padding: 6px 12px; transition: all 0.2s;">
                                    <i class="fa-solid fa-list"></i> List
                                </button>
                            </div>
                            <button class="btn btn-primary btn-sm" onclick="openModal('facility-modal')">
                                <span class="icon-img-placeholder">➕</span> Add Facility
                            </button>
                        </div>
                    </div>

                    <!-- Grid View -->
                    <div id="facility-grid-view">
                        <div class="facilities-grid">
                            <?php foreach ($dashboard_data['facilities'] as $facility): ?>
                                <div class="facility-card">
                                    <div class="facility-image">
                                        <?php
                                        // Image Logic
                                        $clean_name = trim($facility['name']);
                                        $name_title_case = ucwords(strtolower($clean_name));
                                        $name_snake = str_replace(' ', '_', strtolower($clean_name));
                                        $possible_files = [
                                            $name_title_case . '.jpeg',
                                            $name_title_case . '.jpg',
                                            $name_title_case . '.png',
                                            $clean_name . '.jpeg',
                                            $clean_name . '.jpg',
                                            $clean_name . '.png',
                                            $name_snake . '.jpeg',
                                            $name_snake . '.jpg',
                                            $name_snake . '.png',
                                            strtolower($clean_name) . '.jpg',
                                            strtolower($clean_name) . '.jpeg',
                                        ];

                                        $image_url = '';
                                        $is_placeholder = true;

                                        if (!empty($facility['image_url'])) {
                                            $image_url = $facility['image_url'];
                                            $is_placeholder = false;
                                        }

                                        if ($is_placeholder) {
                                            foreach ($possible_files as $file) {
                                                if (file_exists(__DIR__ . '/../assets/image/' . $file)) {
                                                    $image_url = '../assets/image/' . $file;
                                                    $is_placeholder = false;
                                                    break;
                                                }
                                            }
                                        }

                                        if ($is_placeholder) {
                                            $type_defaults = [
                                                'banquet' => 'Grand Ballroom.jpeg',
                                                'meeting' => 'executive_boardroom.jpg',
                                                'conference' => 'Pacific Conference Hall.jpeg',
                                                'outdoor' => 'Sky Garden.jpeg',
                                                'dining' => 'Harbor View Dining Room.jpeg',
                                                'lounge' => 'Sunset Lounge.jpeg'
                                            ];
                                            $type = strtolower($facility['type']);
                                            if (isset($type_defaults[$type]) && file_exists(__DIR__ . '/../assets/image/' . $type_defaults[$type])) {
                                                $image_url = '../assets/image/' . $type_defaults[$type];
                                                $is_placeholder = false;
                                            }
                                        }

                                        $placeholder_color = '1a365d';
                                        switch ($facility['type']) {
                                            case 'banquet':
                                                $placeholder_color = '764ba2';
                                                break;
                                            case 'meeting':
                                            case 'conference':
                                                $placeholder_color = '3182ce';
                                                break;
                                            case 'outdoor':
                                                $placeholder_color = '38a169';
                                                break;
                                        }

                                        if ($is_placeholder) {
                                            $image_url = "https://placehold.co/400x200/{$placeholder_color}/FFFFFF?text=" . urlencode($facility['name']);
                                        }
                                        ?>
                                        <img src="<?= htmlspecialchars($image_url) ?>"
                                            alt="<?= htmlspecialchars($facility['name']) ?>"
                                            style="width: 100%; height: 100%; object-fit: cover;">
                                    </div>
                                    <div class="facility-content">
                                        <div class="facility-header">
                                            <div class="facility-name"><?= htmlspecialchars($facility['name']) ?></div>
                                            <span class="facility-type"
                                                style="background: #3182ce; color: white; padding: 2px 8px; border-radius: 12px; font-size: 0.7rem; text-transform: uppercase;"><?= htmlspecialchars($facility['type']) ?></span>
                                        </div>
                                        <div style="display: flex; flex-direction: column; gap: 8px;">
                                            <button class="btn btn-outline btn-sm"
                                                onclick="viewFacilityDetails(<?= $facility['id'] ?>)">
                                                🔎 View Details
                                            </button>
                                            <button class="btn btn-primary btn-sm"
                                                onclick="openReservationModal(<?= $facility['id'] ?>)">
                                                <i class="fa-solid fa-calendar-plus"></i> Reserve Facility
                                            </button>
                                        </div>
                                        <div class="facility-details" style="margin-top: 10px;">
                                            <?= htmlspecialchars($facility['description']) ?>
                                        </div>
                                        <div class="facility-price">₱<?= number_format($facility['hourly_rate'], 2) ?>/hour
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Facilities Management (List of Facilities) -->
                    <div id="facility-list-view" style="display: none;">
                        <div class="d-flex justify-between align-center mb-2">
                            <h3><span class="icon-img-placeholder">🏢</span> Facilities Management</h3>
                        </div>
                        <div class="table-container">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th style="text-align: center;">ID</th>
                                        <th style="text-align: left;">Facility Name</th>
                                        <th style="text-align: left;">reserve name</th>
                                        <th>Type</th>
                                        <th style="text-align: center;">Capacity</th>
                                        <th style="text-align: left;">Location</th>
                                        <th>Rate</th>
                                        <th style="text-align: center;">Status</th>
                                        <th style="text-align: center;">Assigned User</th>
                                        <th style="text-align: center;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($dashboard_data['facilities'] as $f): ?>
                                        <tr>
                                            <td style="text-align: center;">#<?= $f['id'] ?></td>
                                            <td style="text-align: left; font-weight: 600;">
                                                <?= htmlspecialchars($f['name']) ?>
                                            </td>
                                            <td style="text-align: left; color: #475569;">
                                                <?= htmlspecialchars($f['next_reserve_name'] ?? 'Available') ?>
                                            </td>
                                            <td><span
                                                    class="facility-type-badge"><?= ucfirst(htmlspecialchars($f['type'])) ?></span>
                                            </td>
                                            <td style="text-align: center;"><?= $f['capacity'] ?> guests</td>
                                            <td style="text-align: left;"><?= htmlspecialchars($f['location']) ?></td>
                                            <td style="font-weight: 500; color: #059669;">
                                                ₱<?= number_format($f['hourly_rate'], 2) ?>/hr</td>
                                            <td style="text-align: center;">
                                                <span class="status-badge status-<?= $f['status'] ?? 'active' ?>">
                                                    <?= ucfirst($f['status'] ?? 'active') ?>
                                                </span>
                                            </td>
                                            <td style="text-align: center; color: #64748b; font-size: 0.9rem;">
                                                <?= htmlspecialchars($f['next_assigned_user'] ?? 'Unassigned') ?>
                                            </td>
                                            <td>
                                                <div style="display: flex; gap: 8px; justify-content: center;">
                                                    <button class="btn btn-outline btn-sm btn-icon"
                                                        onclick="event.preventDefault(); window.viewFacilityDetails(<?= htmlspecialchars(json_encode($f)) ?>)"
                                                        title="View Details">
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

                <!-- Reservations Tab -->
                <div id="reservations"
                    class="tab-content <?= (isset($_GET['tab']) && $_GET['tab'] == 'reservations') ? 'active' : '' ?>"
                    style="margin-top: -25px;">
                    <div class="d-flex justify-between align-center mb-1">
                        <h2><span class="icon-img-placeholder">📅</span> Reservation Management</h2>
                        <button class="btn btn-primary btn-sm" onclick="openModal('reservation-modal')">
                            <span class="icon-img-placeholder">➕</span> New Reservation
                        </button>
                    </div>

                    <div class="table-container premium-dark-card"
                        style="border-radius: 12px; overflow: hidden; margin-top: 15px;">
                        <div class="table-wrapper">
                            <table class="table">
                                <thead style="background: rgba(255,255,255,0.05);">
                                    <tr>
                                        <!-- Nag-set ng center alignment para sa karamihan ng headers -->
                                        <th>BOOKING ID</th>
                                        <th>FACILITY</th>
                                        <th>CUSTOMER</th>
                                        <th>CONTACT</th>
                                        <th>EMAIL</th>
                                        <th>EVENT TYPE</th>
                                        <th>DATE</th>
                                        <th>TIME</th>
                                        <th>GUESTS</th>
                                        <th>PACKAGE</th>
                                        <th>TOTAL AMOUNT</th>
                                        <th>DEPOSIT PAID</th>
                                        <th>BALANCE DUE</th>
                                        <th>PAYMENT METHOD</th>
                                        <th>COORDINATOR</th>
                                        <th>STATUS</th>
                                        <th>ACTIONS</th>
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
                                                <td>BK-2026-<?= str_pad($reservation['id'], 3, '0', STR_PAD_LEFT) ?></td>
                                                <td><?= htmlspecialchars($reservation['facility_name']) ?></td>
                                                <td><?= htmlspecialchars($reservation['customer_name']) ?></td>
                                                <td><?= htmlspecialchars($reservation['customer_phone'] ?? '0917XXXXXXX') ?>
                                                </td>
                                                <td><?= htmlspecialchars($reservation['customer_email']) ?></td>
                                                <td><?= htmlspecialchars($reservation['event_type']) ?></td>
                                                <td><?= htmlspecialchars($reservation['event_date']) ?></td>
                                                <td><?= date('g:i a', strtotime($reservation['start_time'] ?? 'now')) ?></td>
                                                <td><?= $reservation['guests_count'] ?></td>
                                                <td><?= htmlspecialchars($reservation['package'] ?? 'Standard Package') ?></td>
                                                <td>₱<?= number_format($reservation['total_amount'] ?? 0, 2) ?></td>
                                                <td>₱<?= number_format(($reservation['total_amount'] ?? 0) * 0.4, 2) ?></td>
                                                <td>₱<?= number_format(($reservation['total_amount'] ?? 0) * 0.6, 2) ?></td>
                                                <td><?= htmlspecialchars($reservation['payment_method'] ?? 'GCash') ?></td>
                                                <td><?= htmlspecialchars($reservation['coordinator'] ?? 'Maria Santos') ?></td>
                                                <td><span class="status-badge status-<?= $reservation['status'] ?>">
                                                        <?= ucfirst($reservation['status']) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="d-flex gap-1"
                                                        style="flex-wrap: nowrap; justify-content: center;">
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
                </div>

                <!-- Calendar Tab -->
                <!-- Reports Tab -->
                <div id="reports"
                    class="tab-content <?= (isset($_GET['tab']) && $_GET['tab'] == 'reports') ? 'active' : '' ?>"
                    style="padding-top: 0; margin-top: -25px; margin-left: 20px; padding-right: 40px; margin-right: 15px;">
                    <?php
                    // Database Self-Healing: Ensure case_id exists in documents
                    $db = get_pdo();
                    try {
                        $db->query("SELECT case_id FROM documents LIMIT 1");
                    } catch (PDOException $e) {
                        try {
                            $db->exec("ALTER TABLE documents ADD COLUMN case_id VARCHAR(50) DEFAULT NULL AFTER name");
                        } catch (PDOException $ex) {
                        }
                    }
                    try {
                        $db->query("SELECT uploaded_at FROM documents LIMIT 1");
                    } catch (PDOException $e) {
                        try {
                            $db->exec("ALTER TABLE documents ADD COLUMN uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
                        } catch (PDOException $ex) {
                        }
                    }
                    // Self-healing for direct_checkins
                    try {
                        $db->query("SELECT checkin_date FROM direct_checkins LIMIT 1");
                    } catch (PDOException $e) {
                        // If checkin_date is missing, maybe it's called checkin_time or time_in? 
                        // But based on Vistor.php, it should be checkin_date. 
                        // Let's ensure the table exists and has correct columns if we are querying it.
                    }
                    ?>

                    <?php
                    // Server-side: handle GET filters for reports view
                    $r_from = $_GET['from_date'] ?? '';
                    $r_to = $_GET['to_date'] ?? '';
                    $r_status = $_GET['status'] ?? 'all';
                    $r_module = $_GET['module'] ?? 'reservations';

                    $r_headers = [];
                    $r_rows = [];

                    switch ($r_module) {
                        case 'all':
                            $r_headers = ['MODULE', 'ID', 'NAME/TITLE', 'REFERENCE', 'DATE', 'STATUS'];
                            $v_cols = $db->query("SHOW COLUMNS FROM direct_checkins")->fetchAll(PDO::FETCH_COLUMN);
                            $v_checkin = in_array('checkin_date', $v_cols) ? 'checkin_date' : 'time_in';
                            $v_checkout = in_array('checkout_date', $v_cols) ? 'checkout_date' : 'time_out';
                            $v_phone = in_array('phone_number', $v_cols) ? 'phone_number' : 'phone';

                            $all_sql = "
                                (SELECT 'Reservation' as module, r.id, r.customer_name as name, f.name as ref, r.event_date as date, r.status 
                                 FROM reservations r LEFT JOIN facilities f ON r.facility_id = f.id)
                                UNION ALL
                                (SELECT 'Facility' as module, id, name, location as ref, 'N/A' as date, status FROM facilities)
                                UNION ALL
                                (SELECT 'Document' as module, id, name, case_id as ref, CAST(uploaded_at AS CHAR) as date, 'Archived' as status FROM documents WHERE is_deleted = 0)
                                UNION ALL
                                (SELECT 'Visitor' as module, id, full_name as name, room_number as ref, CAST($v_checkin AS CHAR) as date, status FROM direct_checkins)
                                UNION ALL
                                (SELECT 'Legal' as module, id, name, case_id as ref, CAST(created_at AS CHAR) as date, CAST(risk_score AS CHAR) as status FROM contracts)
                                ORDER BY date DESC
                            ";
                            $r_stmt = get_pdo()->prepare($all_sql);
                            $r_stmt->execute();
                            $r_rows = $r_stmt->fetchAll(PDO::FETCH_ASSOC);
                            break;

                        case 'facilities':
                            $r_sql = "SELECT id, name, type, capacity, location, CONCAT('₱', FORMAT(hourly_rate, 2)) as rate, status FROM facilities WHERE 1=1";
                            $r_headers = ['ID', 'NAME', 'TYPE', 'CAPACITY', 'LOCATION', 'RATE', 'STATUS'];
                            $r_stmt = get_pdo()->prepare($r_sql);
                            $r_stmt->execute();
                            $r_rows = $r_stmt->fetchAll(PDO::FETCH_ASSOC);
                            break;

                        case 'archiving':
                            $r_sql = "SELECT id, name, case_id, file_path, uploaded_at FROM documents WHERE is_deleted = 0";
                            $r_headers = ['ID', 'DOCUMENT NAME', 'CASE ID', 'FILE PATH', 'UPLOADED AT'];
                            $r_stmt = get_pdo()->prepare($r_sql);
                            $r_stmt->execute();
                            $r_rows = $r_stmt->fetchAll(PDO::FETCH_ASSOC);
                            break;

                        case 'visitors':
                            // Attempting to fetch from direct_checkins or visitor_logs if exists
                            try {
                                $v_cols = get_pdo()->query("SHOW COLUMNS FROM direct_checkins")->fetchAll(PDO::FETCH_COLUMN);
                                $v_checkin = in_array('checkin_date', $v_cols) ? 'checkin_date' : 'time_in';
                                $v_checkout = in_array('checkout_date', $v_cols) ? 'checkout_date' : 'time_out';
                                $v_phone = in_array('phone_number', $v_cols) ? 'phone_number' : 'phone';

                                $r_sql = "SELECT id, full_name, email, $v_phone as phone, room_number as facility, $v_checkin as time_in, $v_checkout as time_out, status FROM direct_checkins WHERE 1=1";
                                if ($r_from)
                                    $r_sql .= " AND DATE($v_checkin) >= " . get_pdo()->quote($r_from);
                                if ($r_to)
                                    $r_sql .= " AND DATE($v_checkin) <= " . get_pdo()->quote($r_to);
                                $r_headers = ['ID', 'NAME', 'EMAIL', 'PHONE', 'FACILITY', 'TIME IN', 'TIME OUT', 'STATUS'];
                                $r_stmt = get_pdo()->prepare($r_sql);
                                $r_stmt->execute();
                                $r_rows = $r_stmt->fetchAll(PDO::FETCH_ASSOC);
                            } catch (Exception $e) {
                                $r_rows = [];
                            }
                            break;

                        case 'legal':
                            $r_sql = "SELECT id, name, case_id, contract_type, risk_score, created_at FROM contracts";
                            $r_headers = ['ID', 'CONTRACT NAME', 'CASE ID', 'TYPE', 'RISK SCORE', 'CREATED AT'];
                            $r_stmt = get_pdo()->prepare($r_sql);
                            $r_stmt->execute();
                            $r_rows = $r_stmt->fetchAll(PDO::FETCH_ASSOC);
                            break;

                        case 'reservations':
                        default:
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
                            $r_rows = $r_stmt->fetchAll(PDO::FETCH_ASSOC);
                            // We will handle the specific reservations table layout in the render block
                            break;
                    }
                    ?>



                    <form method="get" class="filters">
                        <input type="hidden" name="tab" value="reports">
                        From: <input type="date" name="from_date" value="<?= htmlspecialchars($r_from) ?>">
                        To: <input type="date" name="to_date" value="<?= htmlspecialchars($r_to) ?>">
                        Status: <select name="status">
                            <option value="all" <?= $r_status === 'all' ? 'selected' : '' ?>>All</option>
                            <option value="pending" <?= $r_status === 'pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="confirmed" <?= $r_status === 'confirmed' ? 'selected' : '' ?>>Confirmed
                            </option>
                            <option value="cancelled" <?= $r_status === 'cancelled' ? 'selected' : '' ?>>Cancelled
                            </option>
                            <option value="completed" <?= $r_status === 'completed' ? 'selected' : '' ?>>Completed
                            </option>
                        </select>
                        Modules: <select name="module">
                            <option value="all" <?= $r_module === 'all' ? 'selected' : '' ?>>All Records</option>
                            <option value="reservations" <?= $r_module === 'reservations' ? 'selected' : '' ?>>Reservations
                            </option>
                            <option value="facilities" <?= $r_module === 'facilities' ? 'selected' : '' ?>>Facilities
                            </option>
                            <option value="archiving" <?= $r_module === 'archiving' ? 'selected' : '' ?>>Document Archiving
                            </option>
                            <option value="visitors" <?= $r_module === 'visitors' ? 'selected' : '' ?>>Visitor Management
                            </option>
                            <option value="legal" <?= $r_module === 'legal' ? 'selected' : '' ?>>Legal Management
                            </option>
                        </select>
                        Report Type: <select name="report_type">
                            <option value="all">All Records</option>
                            <option value="financial">Financial Records</option>
                            <option value="access_logs">Access Logs</option>
                            <option value="audit_trail">Audit Trail</option>
                        </select>
                        <button class="btn">Filter</button>
                    </form>

                    <form method="post" style="margin-bottom:12px">
                        <input type="hidden" name="action" value="export_csv">
                        <input type="hidden" name="module" value="<?= htmlspecialchars($r_module) ?>">
                        <input type="hidden" name="from_date" value="<?= htmlspecialchars($r_from) ?>">
                        <input type="hidden" name="to_date" value="<?= htmlspecialchars($r_to) ?>">
                        <input type="hidden" name="status" value="<?= htmlspecialchars($r_status) ?>">
                        <button class="btn">Export CSV</button>
                    </form>

                    <div class="d-flex align-center gap-1 mb-1">
                        <i class="fa-solid fa-file-invoice" style="font-size: 1.5rem; color: #3182ce;"></i>
                        <h2 style="margin: 0;"><?= ucfirst($r_module) ?> Reports</h2>
                    </div>

                    <div class="table-container">
                        <div class="table-wrapper">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <?php foreach ($r_headers as $h): ?>
                                            <th><?= $h ?></th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($r_rows)): ?>
                                        <tr>
                                            <td colspan="<?= count($r_headers) ?>"
                                                style="text-align: center; padding: 2rem; color: #718096; font-style: italic;">
                                                No records found for the selected module and filters.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($r_rows as $rr): ?>
                                            <tr style="border-bottom: 1px solid #edf2f7;">
                                                <?php if ($r_module === 'reservations'): ?>
                                                    <td
                                                        style="font-weight: 600; color: #1e293b; font-size: 12px; padding: 12px 15px;">
                                                        BK-2026-<?= str_pad($rr['id'], 3, '0', STR_PAD_LEFT) ?></td>
                                                    <td style="color: #1e293b; font-size: 12px; padding: 12px 15px;">
                                                        <?= htmlspecialchars($rr['facility_name']) ?>
                                                    </td>
                                                    <td style="color: #1e293b; font-size: 12px; padding: 12px 15px;">
                                                        <?= htmlspecialchars($rr['customer_name']) ?>
                                                    </td>
                                                    <td style="color: #1e293b; font-size: 12px; padding: 12px 15px;">
                                                        <?= htmlspecialchars($rr['customer_phone'] ?? '0917XXXXXXX') ?>
                                                    </td>
                                                    <td style="color: #1e293b; font-size: 12px; padding: 12px 15px;">
                                                        <?= htmlspecialchars($rr['customer_email']) ?>
                                                    </td>
                                                    <td style="color: #1e293b; font-size: 12px; padding: 12px 15px;">
                                                        <?= htmlspecialchars($rr['event_type']) ?>
                                                    </td>
                                                    <td style="color: #1e293b; font-size: 12px; padding: 12px 15px;">
                                                        <?= htmlspecialchars($rr['event_date']) ?>
                                                    </td>
                                                    <td style="color: #1e293b; font-size: 12px; padding: 12px 15px;">
                                                        <?= date('g:i a', strtotime($rr['start_time'] ?? 'now')) ?>
                                                    </td>
                                                    <td style="color: #1e293b; font-size: 12px; padding: 12px 15px;">
                                                        <?= $rr['guests_count'] ?>
                                                    </td>
                                                    <td style="color: #1e293b; font-size: 12px; padding: 12px 15px;">
                                                        <?= htmlspecialchars($rr['package'] ?? 'Standard Package') ?>
                                                    </td>
                                                    <td style="color: #1e293b; font-size: 12px; padding: 12px 15px;">
                                                        ₱<?= number_format($rr['total_amount'] ?? 0, 2) ?></td>
                                                    <td style="font-size: 12px; padding: 12px 15px;">
                                                        <span class="status-badge status-<?= $rr['status'] ?>">
                                                            <?= htmlspecialchars($rr['status']) ?>
                                                        </span>
                                                    </td>
                                                    <td style="padding: 12px 15px;">
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
                                                                    <button class="btn btn-success btn-icon" name="status"
                                                                        value="confirmed" title="Confirm" aria-label="Confirm">
                                                                        <i class="fa-solid fa-check"></i>
                                                                    </button>
                                                                    <button class="btn btn-danger btn-icon" name="status"
                                                                        value="cancelled" title="Cancel" aria-label="Cancel">
                                                                        <i class="fa-solid fa-xmark"></i>
                                                                    </button>
                                                                <?php elseif ($rr['status'] === 'confirmed'): ?>
                                                                    <button class="btn btn-warning btn-icon" name="status"
                                                                        value="completed" title="Mark as Completed"
                                                                        aria-label="Complete">
                                                                        <i class="fa-solid fa-flag-checkered"></i>
                                                                    </button>
                                                                    <button class="btn btn-danger btn-icon" name="status"
                                                                        value="cancelled" title="Cancel" aria-label="Cancel">
                                                                        <i class="fa-solid fa-xmark"></i>
                                                                    </button>
                                                                <?php endif; ?>
                                                            </form>
                                                        </div>
                                                    </td>
                                                <?php else: ?>
                                                    <!-- Generic display for other modules -->
                                                    <?php foreach ($rr as $key => $val): ?>
                                                        <td style="color: #1e293b; font-size: 12px; padding: 12px 15px;">
                                                            <?= htmlspecialchars($val) ?>
                                                        </td>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div id="calendar"
                    class="tab-content <?= (isset($_GET['tab']) && $_GET['tab'] == 'calendar') ? 'active' : '' ?>">
                    <h2 class="mb-2"><span class="icon-img-placeholder">📅</span> Reservation Calendar</h2>

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
                                            <div class="event-title"><?= htmlspecialchars($event['facility_name']) ?>
                                            </div>
                                            <div class="event-details">
                                                <?= htmlspecialchars($event['customer_name']) ?> •
                                                <?= htmlspecialchars($event['event_type']) ?> •
                                                <?= $event['guests_count'] ?>
                                                guests
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                    <?php if (empty($day_events)): ?>
                                        <div style="color: #718096; font-style: italic; text-align: center; padding: 1rem;">
                                            <span class="icon-img-placeholder">🚫</span> No reservations
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>

                <!-- Maintenance Tab (Formerly Management) -->
                <div id="maintenance"
                    class="tab-content <?= (isset($_GET['tab']) && ($_GET['tab'] == 'management' || $_GET['tab'] == 'maintenance')) ? 'active' : '' ?>">
                    <div class="management-header d-flex justify-between align-center mb-2">
                        <button id="btn-toggle-trash" class="btn btn-outline btn-sm" onclick="toggleMaintenanceTrash()">
                            <i class="fa-solid fa-trash-can"></i> View Trash
                        </button>
                    </div>



                    <div id="maintenance-main-section">
                        <!-- Maintenance & Status Card -->
                        <div class="card management-card management-maintenance active-card" style="margin-top: -10px;">
                            <div class="card-header d-flex justify-between align-center">
                                <h3><span class="icon-img-placeholder">🛠️</span> Maintenance & Deployed Staff</h3>
                                <div class="d-flex gap-1">
                                    <button class="btn btn-outline btn-sm" onclick="exportMaintenanceReport()">
                                        <i class="fas fa-file-export"></i> Export Report
                                    </button>
                                    <button class="btn btn-primary btn-sm" onclick="openModal('maintenance-modal')">
                                        <span class="icon-img-placeholder">➕</span> Log Maintenance Issue
                                    </button>
                                </div>
                            </div>
                            <div class="card-content">
                                <div class="d-flex justify-end align-center mb-1">
                                    <div
                                        style="background: #fff3cd; padding: 10px 15px; border-radius: 8px; border-left: 4px solid #ffc107;">
                                        <strong><span class="icon-img-placeholder">⚠️</span> Pending Tasks:</strong>
                                        <?= $dashboard_data['pending_maintenance'] ?? 0 ?>
                                    </div>
                                </div>
                                <div class="table-wrapper">
                                    <table class="table management-table">
                                        <thead>
                                            <tr>
                                                <th>PRIORITY</th>
                                                <th>ITEM/AREA</th>
                                                <th style="min-width: 150px;">DESCRIPTION</th>
                                                <th>REPORTED BY</th>
                                                <th>REPORTED DATE</th>
                                                <th>SCHEDULE</th>
                                                <th>ASSIGNED STAFF</th>
                                                <th>DEPARTMENT</th>
                                                <th>CONTACT</th>
                                                <!-- Actions kept for functionality but can be hidden if strict view-only is needed -->
                                                <th>ACTIONS</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($dashboard_data['maintenance_logs'])): ?>
                                                <tr>
                                                    <td colspan="12"
                                                        style="text-align: center; padding: 2rem; color: #718096; font-style: italic;">
                                                        No maintenance logs found.
                                                    </td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($dashboard_data['maintenance_logs'] as $log): ?>
                                                    <tr style="border-bottom: 1px solid #edf2f7;">
                                                        <td style="font-size: 12px; padding: 12px 15px;">
                                                            <div
                                                                style="display: flex; align-items: center; justify-content: flex-start; gap: 8px;">
                                                                <?php
                                                                $p_color = '#22c55e'; // low
                                                                if (($log['priority'] ?? '') == 'high')
                                                                    $p_color = '#ef4444';
                                                                if (($log['priority'] ?? '') == 'medium')
                                                                    $p_color = '#f59e0b';
                                                                ?>
                                                                <span
                                                                    style="width: 10px; height: 10px; border-radius: 50%; background: <?= $p_color ?>; box-shadow: 0 0 5px <?= $p_color ?>;"></span>
                                                                <span
                                                                    style="font-weight: 700; text-transform: uppercase; font-size: 0.7rem; color: <?= $p_color ?>;"><?= ucfirst($log['priority'] ?? 'Low') ?></span>
                                                            </div>
                                                        </td>
                                                        <td
                                                            style="font-weight: 600; text-align: left !important; color: #1e293b; font-size: 12px; padding: 12px 15px;">
                                                            <?= htmlspecialchars($log['item_name']) ?>
                                                        </td>
                                                        <td style="font-size: 12px; max-width: 150px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; text-align: left !important; color: #475569; padding: 12px 15px;"
                                                            title="<?= htmlspecialchars($log['description']) ?>">
                                                            <?= htmlspecialchars($log['description']) ?>
                                                        </td>
                                                        <td
                                                            style="font-weight: 500; color: #1e293b; font-size: 12px; padding: 12px 15px;">
                                                            <?= htmlspecialchars($log['reported_by'] ?? 'Staff') ?>
                                                        </td>
                                                        <td style="color: #1e293b; font-size: 12px; padding: 12px 15px;">
                                                            <?= date('m/d/Y', strtotime($log['created_at'])) ?>
                                                        </td>
                                                        <td style="font-size: 12px; color: #1e293b; padding: 12px 15px;">
                                                            <?= date('m/d/Y', strtotime($log['maintenance_date'])) ?>
                                                        </td>
                                                        <td
                                                            style="font-weight: 500; color: #1e293b; font-size: 12px; padding: 12px 15px;">
                                                            <?= htmlspecialchars($log['assigned_staff']) ?>
                                                        </td>
                                                        <td style="padding: 12px 15px;"><span
                                                                style="background: #e2e8f0; padding: 2px 8px; border-radius: 12px; font-size: 0.75rem; color: #475569;"><?= htmlspecialchars($log['department'] ?? 'General') ?></span>
                                                        </td>
                                                        <td style="font-size: 12px; color: #1e293b; padding: 12px 15px;">
                                                            <?= htmlspecialchars($log['contact_number'] ?? 'N/A') ?>
                                                        </td>

                                                        <td style="padding: 12px 15px;">
                                                            <div class="d-flex gap-1" style="justify-content: center;">
                                                                <button class="btn btn-icon btn-sm"
                                                                    style="background: #3182ce; color: white;"
                                                                    onclick="event.preventDefault(); window.viewMaintenanceDetails(<?= htmlspecialchars(json_encode($log)) ?>)"
                                                                    title="View Details">
                                                                    <i class="fa-solid fa-eye"></i>
                                                                </button>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>




                    <div id="maintenance-trash-section" style="display: none;">
                        <div class="card management-card" style="margin-top: -10px; border-top: 4px solid #ef4444;">
                            <div class="card-header d-flex justify-between align-center">
                                <h3><span class="icon-img-placeholder">🗑️</span> Trash / Archived Logs</h3>
                                <p style="font-size: 0.8rem; color: #64748b; margin: 0;">Deleted logs can be restored or
                                    permanently removed.</p>
                            </div>
                            <div class="card-content">
                                <?php $deleted_logs = $reservationSystem->fetchMaintenanceLogs(true); ?>
                                <div class="table-wrapper">
                                    <table class="table management-table">
                                        <thead>
                                            <tr>
                                                <th>ITEM/AREA</th>
                                                <th>DESCRIPTION</th>
                                                <th>ASSIGNED STAFF</th>
                                                <th>SCHEDULED DATE</th>
                                                <th>ACTIONS</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($deleted_logs)): ?>
                                                <tr>
                                                    <td colspan="5"
                                                        style="text-align: center; padding: 2rem; color: #718096; font-style: italic;">
                                                        Trash is empty.
                                                    </td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($deleted_logs as $dlog): ?>
                                                    <tr style="border-bottom: 1px solid #edf2f7; opacity: 0.8;">
                                                        <td
                                                            style="font-weight: 600; text-align: left !important; color: #1e293b; font-size: 12px; padding: 12px 15px;">
                                                            <?= htmlspecialchars($dlog['item_name']) ?>
                                                        </td>
                                                        <td
                                                            style="font-size: 12px; color: #475569; padding: 12px 15px; text-align: left;">
                                                            <?= htmlspecialchars($dlog['description']) ?>
                                                        </td>
                                                        <td style="font-size: 12px; color: #1e293b; padding: 12px 15px;">
                                                            <?= htmlspecialchars($dlog['assigned_staff'] ?? 'N/A') ?>
                                                        </td>
                                                        <td style="font-size: 12px; color: #1e293b; padding: 12px 15px;">
                                                            <?= date('m/d/Y', strtotime($dlog['maintenance_date'])) ?>
                                                        </td>
                                                        <td style="padding: 12px 15px;">
                                                            <div class="d-flex gap-1" style="justify-content: center;">
                                                                <button class="btn btn-success btn-sm btn-icon"
                                                                    onclick="restoreMaintenanceLog(<?= $dlog['id'] ?>)"
                                                                    title="Restore Log">
                                                                    <i class="fa-solid fa-rotate-left"></i>
                                                                </button>
                                                                <button class="btn btn-danger btn-sm btn-icon"
                                                                    onclick="permanentlyDeleteMaintenanceLog(<?= $dlog['id'] ?>)"
                                                                    title="Delete Permanently">
                                                                    <i class="fa-solid fa-xmark"></i>
                                                                </button>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div> <!-- End Management Tab -->
            </div> <!-- End dashboard-content -->
        </main> <!-- End main-content -->
    </div> <!-- End container -->

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
                                ₱<?= number_format($facility['hourly_rate'], 2) ?>/hour
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div id="facility-details"
                    style="display: none; background: var(--light); padding: 1rem; border-radius: 8px; margin-bottom: 1rem;">
                    <div><strong><span class="icon-img-placeholder">👤</span> Capacity:</strong> <span
                            id="capacity-display"></span> people</div>
                    <div><strong><span class="icon-img-placeholder">₱</span> Hourly Rate:</strong> ₱<span
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
                    <span class="icon-img-placeholder">⚠️</span> <span id="availability-message"></span>
                </div>

                <div class="form-group">
                    <label for="guests_count">Number of Guests</label>
                    <input type="number" id="guests_count" name="guests_count" class="form-control" required min="1"
                        onchange="checkCapacity()">
                    <small id="capacity-warning" style="color: var(--danger); display: none;">
                        <span class="icon-img-placeholder">🚨</span> Exceeds facility capacity!
                    </small>
                </div>

                <div class="form-group">
                    <label for="special_requirements">Special Requirements</label>
                    <textarea id="special_requirements" name="special_requirements" class="form-control" rows="3"
                        placeholder="Any special arrangements or requirements..."></textarea>
                </div>

                <button type="submit" class="btn btn-primary btn-block">
                    <span class="icon-img-placeholder">📩</span> Submit Reservation Request
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
                    <label for="facility_rate">Hourly Rate (₱)</label>
                    <input type="number" id="facility_rate" name="hourly_rate" class="form-control" step="0.01"
                        required>
                </div>

                <div class="form-group">
                    <label for="facility_image">Image URL (Optional)</label>
                    <input type="text" id="facility_image" name="image_url" class="form-control"
                        placeholder="https://example.com/image.jpg or path to image">
                </div>

                <div class="form-group">
                    <label for="facility_amenities">Amenities</label>
                    <textarea id="facility_amenities" name="amenities" class="form-control" rows="3"
                        placeholder="List amenities separated by commas..."></textarea>
                </div>

                <button type="submit" class="btn btn-primary btn-block">
                    <span class="icon-img-placeholder">➕</span> Add Facility
                </button>
            </form>
        </div>
    </div>

    <!-- Maintenance Modal -->
    <div id="maintenance-modal" class="modal">
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header">
                <h3>Log Maintenance/Repair</h3>
                <span class="close" onclick="closeModal('maintenance-modal')">&times;</span>
            </div>
            <form id="maintenance-form" method="POST">
                <input type="hidden" name="action" value="add_maintenance">

                <div class="form-group">
                    <label for="item_name">Item or Area to Fix</label>
                    <input type="text" id="item_name" name="item_name" class="form-control"
                        placeholder="e.g., Aircon Unit A, Room 302, Hallway Light" required>
                </div>

                <div class="form-group">
                    <label for="description">Issue/Problem Description</label>
                    <textarea id="description" name="description" class="form-control" rows="3"
                        placeholder="Describe what's wrong..."></textarea>
                </div>

                <div class="form-group">
                    <label for="maintenance_date">Target Repair Date</label>
                    <input type="date" id="maintenance_date" name="maintenance_date" class="form-control" required
                        min="<?= date('Y-m-d') ?>">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="reported_by">Reported By</label>
                        <input type="text" id="reported_by" name="reported_by" class="form-control"
                            placeholder="Who found the issue?" required>
                    </div>
                    <div class="form-group">
                        <label for="department">Department</label>
                        <input type="text" id="department" name="department" class="form-control"
                            placeholder="e.g., Engineering, HVAC" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="duration">Work Duration</label>
                        <input type="text" id="duration" name="duration" class="form-control"
                            placeholder="e.g., 2 hours" required>
                    </div>
                    <div class="form-group">
                        <label for="mnt_priority">Priority Level</label>
                        <select id="mnt_priority" name="priority" class="form-control">
                            <option value="low">Low (Routine)</option>
                            <option value="medium">Medium (Fixed soon)</option>
                            <option value="high">High (Urgent)</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label for="assigned_staff">Deployed Staff (Assigned To)</label>
                    <input type="text" id="assigned_staff" name="assigned_staff" class="form-control"
                        placeholder="Name of person to fix it" required>
                </div>

                <div class="form-group">
                    <label for="contact_number">Contact Number</label>
                    <input type="tel" id="contact_number" name="contact_number" class="form-control"
                        placeholder="Staff contact info">
                </div>

                <div class="form-group">
                    <label for="mnt_status">Initial Status</label>
                    <select id="mnt_status" name="status" class="form-control">
                        <option value="pending">Pending (Not Started)</option>
                        <option value="in-progress">In Progress</option>
                        <option value="completed">Completed</option>
                    </select>
                </div>

                <button type="submit" class="btn btn-primary btn-block">
                    <span class="icon-img-placeholder">💾</span> Save Maintenance Log
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
            <div style="margin-top: 1.5rem; text-align: right; pt: 1rem; border-top: 1px solid #eee;">
                <button class="btn btn-outline" onclick="closeModal('facility-details-modal')">Close Details</button>
            </div>
        </div>
    </div>

    <!-- Maintenance Details Modal -->
    <div id="maintenance-details-modal" class="modal">
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header">
                <h3><i class="fa-solid fa-screwdriver-wrench"></i> Maintenance Details</h3>
                <span class="close" onclick="closeModal('maintenance-details-modal')">&times;</span>
            </div>
            <div id="maintenance-details-body">
                <!-- Filled via JS -->
            </div>
            <div style="margin-top: 1.5rem; text-align: right; pt: 1rem; border-top: 1px solid #eee;">
                <button class="btn btn-outline" onclick="closeModal('maintenance-details-modal')">Close Window</button>
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
                    <span class="icon-img-placeholder">🚪</span> Confirm Logout
                </button>
            </div>
        </div>
    </div>


    <script src="../assets/Javascript/facilities-reservation.js?v=<?= time() ?>"></script>

    <script>
        // Final insurance to ensure Management functions are ready
        // Final insurance to ensure Management functions are ready
        document.addEventListener('DOMContentLoaded', function () {
            if (sessionStorage.getItem('activeTab') === 'maintenance' || sessionStorage.getItem('activeTab') === 'management') {
                setTimeout(function () {
                    if (typeof window.showManagementCard === 'function') {
                        // Default to maintenance card
                        const currentCard = document.querySelector('.management-card[style*="block"]') ? null : 'maintenance';
                        if (currentCard) window.showManagementCard(currentCard);
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
            const targets = [
                { id: 'employeesTableBody', columns: 8 },
                { id: 'facilitiesEmployeesTableBody', columns: 5 }
            ];

            targets.forEach(target => {
                const tbody = document.getElementById(target.id);
                if (!tbody) return;

                tbody.innerHTML = '';

                if (employees.length === 0) {
                    tbody.innerHTML = `
                        <tr>
                            <td colspan="${target.columns}" style="text-align: center; padding: 20px;">
                                <div style="color: #718096; font-style: italic;">
                                    <i class="fa-regular fa-users" style="font-size: 24px; margin-bottom: 10px; display: block;"></i>
                                    No employees found.
                                </div>
                            </td>
                        </tr>
                    `;
                } else {
                    employees.forEach(employee => {
                        const position = employee.employment_details ? (employee.employment_details.job_title || 'N/A') : (employee.position || 'N/A');
                        const department = employee.department_name || (employee.employment_details ? employee.employment_details.department_name : null) || employee.department || 'N/A';
                        const salary = employee.employment_details ? (employee.employment_details.basic_salary || 0) : (employee.salary || 0);

                        if (target.id === 'employeesTableBody') {
                            tbody.innerHTML += `
                                <tr>
                                    <td style="text-align: center;">#${employee.id}</td>
                                    <td>${employee.first_name || ''}</td>
                                    <td>${employee.last_name || ''}</td>
                                    <td>${employee.email}</td>
                                    <td>${position}</td>
                                    <td>${department}</td>
                                    <td>₱${parseFloat(salary).toLocaleString(undefined, { minimumFractionDigits: 2 })}</td>
                                    <td>
                                        <div style="display: flex; gap: 8px; justify-content: center;">
                                            <button class="btn btn-outline btn-sm btn-icon" onclick="viewEmployeeDetails(${employee.id})" title="View Details">
                                                <i class="fa-solid fa-eye"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            `;
                        } else {
                            tbody.innerHTML += `
                                <tr>
                                    <td style="text-align: center;">#${employee.id}</td>
                                    <td style="text-align: left;">${employee.email}</td>
                                    <td>${position}</td>
                                    <td>${department}</td>
                                    <td>
                                        <div style="display: flex; gap: 8px; justify-content: center;">
                                            <button class="btn btn-outline btn-sm btn-icon" onclick="viewEmployeeDetails(${employee.id})" title="View Details">
                                                <i class="fa-solid fa-eye"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            `;
                        }
                    });
                }
            });
        }

        function viewEmployeeDetails(id) {
            // Implementation for viewing employee details
            alert('View employee details functionality for ID: ' + id);
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

        // Dropdown Functions
        function toggleDropdown(dropdownId) {
            const dropdown = document.getElementById(dropdownId);
            if (dropdown) {
                // Close all other dropdowns
                document.querySelectorAll('.dropdown-menu').forEach(menu => {
                    if (menu.id !== dropdownId) {
                        menu.style.display = 'none';
                    }
                });

                // Toggle current dropdown
                dropdown.style.display = dropdown.style.display === 'none' ? 'block' : 'none';
            }
        }

        function closeDropdown(dropdownId) {
            const dropdown = document.getElementById(dropdownId);
            if (dropdown) {
                dropdown.style.display = 'none';
            }
        }

        // Close dropdowns when clicking outside
        document.addEventListener('click', function (event) {
            if (!event.target.matches('.dropdown-toggle')) {
                document.querySelectorAll('.dropdown-menu').forEach(menu => {
                    menu.style.display = 'none';
                });
            }
        });

        // Management Card Navigation Function
        window.showManagementCard = function (cardType) {
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

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function () {
            // loadEmployees(); // Removed as per request to remove employee card
        });
    </script>
    <!-- Loading Overlay -->
    <div id="loadingOverlay"
        style="display:none; position:fixed; inset:0; z-index:99999; background:rgba(0,0,0,0.85); backdrop-filter:blur(4px); transition: opacity 0.5s ease; opacity: 1;">
        <iframe src="../animation/loading.html" style="width:100%; height:100%; border:none;"></iframe>
    </div>
</body>

</html>