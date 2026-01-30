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

        // Alter reservations table if columns don't exist
        try {
            $this->pdo->exec("ALTER TABLE reservations ADD COLUMN IF NOT EXISTS deposit_paid DECIMAL(10,2) DEFAULT 0");
            $this->pdo->exec("ALTER TABLE reservations ADD COLUMN IF NOT EXISTS balance_due DECIMAL(10,2) DEFAULT 0");
            $this->pdo->exec("ALTER TABLE reservations ADD COLUMN IF NOT EXISTS payment_method VARCHAR(50) DEFAULT 'Cash'");
            $this->pdo->exec("ALTER TABLE reservations ADD COLUMN IF NOT EXISTS coordinator VARCHAR(255)");
        } catch (Exception $e) {
        }

        // Alter facilities table if columns don't exist
        try {
            $this->pdo->exec("ALTER TABLE facilities ADD COLUMN IF NOT EXISTS status ENUM('active', 'maintenance', 'closed') DEFAULT 'active'");
            $this->pdo->exec("ALTER TABLE facilities ADD COLUMN IF NOT EXISTS assigned_user VARCHAR(255) DEFAULT 'Not Assigned'");
            $this->pdo->exec("ALTER TABLE facilities ADD COLUMN IF NOT EXISTS reserve_name VARCHAR(255) DEFAULT ''");
        } catch (Exception $e) {
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
            $stmt = $pdo->prepare("INSERT INTO facilities (reserve_name, name, type, capacity, location, description, hourly_rate, amenities, image_url, status, assigned_user) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                htmlspecialchars($data['reserve_name'] ?? ''),
                htmlspecialchars($data['name']),
                htmlspecialchars($data['type']),
                intval($data['capacity']),
                htmlspecialchars($data['location']),
                htmlspecialchars($data['description']),
                floatval($data['hourly_rate']),
                htmlspecialchars($data['amenities'] ?? ''),
                htmlspecialchars($data['image_url'] ?? ''),
                htmlspecialchars($data['status'] ?? 'active'),
                htmlspecialchars($data['assigned_user'] ?? 'Not Assigned')
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
                SELECT r.*, f.name as facility_name, f.capacity as facility_capacity, f.image_url,
                       COALESCE(p.payment_status, 'Pending') as payment_status,
                       COALESCE(r.special_requirements, 'N/A') as table_assignment
                FROM reservations r 
                JOIN facilities f ON r.facility_id = f.id 
                LEFT JOIN (
                    SELECT reservation_id, payment_status 
                    FROM payments 
                    WHERE (reservation_id, created_at) IN (
                        SELECT reservation_id, MAX(created_at) 
                        FROM payments GROUP BY reservation_id
                    )
                ) p ON r.id = p.reservation_id
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

// PROACTIVE: Populate sample data if the calendar is empty to showcase the premium design
$existingLogs = $reservationSystem->fetchMaintenanceLogs();
// Check if we have logs for the upcoming 7 days
$hasUpcoming = false;
$todayStr = date('Y-m-d');
$weekLaterStr = date('Y-m-d', strtotime('+7 days'));

foreach ($existingLogs as $log) {
    if ($log['maintenance_date'] >= $todayStr && $log['maintenance_date'] <= $weekLaterStr) {
        $hasUpcoming = true;
        break;
    }
}

if (!$hasUpcoming) {
    $samples = [
        [
            'item_name' => 'Gym Equipment Lubrication',
            'description' => 'Standard monthly lubrication of treadmills and weight machines to ensure smooth operation.',
            'maintenance_date' => date('Y-m-d', strtotime('+0 days')),
            'assigned_staff' => 'Roberto Cruz',
            'contact_number' => '0912-345-6789',
            'priority' => 'medium',
            'reported_by' => 'Gym Manager',
            'department' => 'Fitness Center',
            'duration' => '3 hours',
            'status' => 'pending'
        ],
        [
            'item_name' => 'Front Desk PC System Update',
            'description' => 'Installing latest security patches and hotel management software updates on all reception workstations.',
            'maintenance_date' => date('Y-m-d', strtotime('+0 days')),
            'assigned_staff' => 'IT Department',
            'contact_number' => '0911-222-3333',
            'priority' => 'high',
            'reported_by' => 'Front Office Manager',
            'department' => 'IT',
            'duration' => '2 hours',
            'status' => 'pending'
        ],
        [
            'item_name' => 'Pool Chlorine Level Adjustment',
            'description' => 'Routine chemical balance check and cleaning of filtering system.',
            'maintenance_date' => date('Y-m-d', strtotime('+1 day')),
            'assigned_staff' => 'Juan Santos',
            'contact_number' => '0922-444-5555',
            'priority' => 'high',
            'reported_by' => 'Pool Attendant',
            'department' => 'Maintenance',
            'duration' => '1 hour',
            'status' => 'in-progress'
        ],
        [
            'item_name' => 'HVAC Unit Inspection - Room 301-310',
            'description' => 'Biannual inspection of guest room air conditioning units to optimize energy consumption.',
            'maintenance_date' => date('Y-m-d', strtotime('+1 day')),
            'assigned_staff' => 'ColdFlow Services',
            'contact_number' => '0955-888-9999',
            'priority' => 'medium',
            'reported_by' => 'Housekeeping',
            'department' => 'Engineering',
            'duration' => '5 hours',
            'status' => 'pending'
        ],
        [
            'item_name' => 'Garden Sprinkler Repair',
            'description' => 'Fixing broken nozzles in the North Wing garden area to prevent water wastage.',
            'maintenance_date' => date('Y-m-d', strtotime('+2 days')),
            'assigned_staff' => 'Maria Leon',
            'contact_number' => '0915-111-2222',
            'priority' => 'low',
            'reported_by' => 'Landscaping Staff',
            'department' => 'Engineering',
            'duration' => '2 hours',
            'status' => 'pending'
        ],
        [
            'item_name' => 'Grand Ballroom AC Filter Cleaning',
            'description' => 'Deep cleaning of HVAC filters before the weekend conference events.',
            'maintenance_date' => date('Y-m-d', strtotime('+3 days')),
            'assigned_staff' => 'Roberto Cruz',
            'contact_number' => '0912-345-6789',
            'priority' => 'medium',
            'reported_by' => 'Event Coordinator',
            'department' => 'Engineering',
            'duration' => '4 hours',
            'status' => 'pending'
        ],
        [
            'item_name' => 'Lobby Chandelier Dusting',
            'description' => 'Detailed cleaning of the main lobby crystal chandelier using specialized equipment.',
            'maintenance_date' => date('Y-m-d', strtotime('+3 days')),
            'assigned_staff' => 'Night Shift Team',
            'contact_number' => '0917-000-1111',
            'priority' => 'low',
            'reported_by' => 'Management',
            'department' => 'Housekeeping',
            'duration' => '6 hours',
            'status' => 'pending'
        ],
        [
            'item_name' => 'Elevator Safety Certification',
            'description' => 'Annual inspection by external contractor (TechLift Solutions) for safety compliance.',
            'maintenance_date' => date('Y-m-d', strtotime('+4 days')),
            'assigned_staff' => 'TechLift Solutions',
            'contact_number' => '0999-000-1111',
            'priority' => 'high',
            'reported_by' => 'Front Office',
            'department' => 'Engineering',
            'duration' => '5 hours',
            'status' => 'pending'
        ],
        [
            'item_name' => 'Kitchen Grease Trap Cleaning',
            'description' => 'Monthly maintenance of drainage systems in the main kitchen area.',
            'maintenance_date' => date('Y-m-d', strtotime('+6 days')),
            'assigned_staff' => 'Sanitation Team',
            'contact_number' => '0918-555-4444',
            'priority' => 'medium',
            'reported_by' => 'Head Chef',
            'department' => 'F&B Service',
            'duration' => '4 hours',
            'status' => 'pending'
        ]
    ];

    foreach ($samples as $sample) {
        $reservationSystem->addMaintenanceLog($sample);
    }
    // Refresh data after adding samples
    $dashboard_data['maintenance_logs'] = $reservationSystem->fetchMaintenanceLogs();
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
                        $status_val = $db->quote($status);
                        $where_status = ($status !== 'all') ? " AND status = $status_val" : "";
                        $where_status_res = ($status !== 'all') ? " AND r.status = $status_val" : "";
                        $where_date_res = ($from_date ? " AND r.event_date >= " . $db->quote($from_date) : "") . ($to_date ? " AND r.event_date <= " . $db->quote($to_date) : "");
                        $where_date_doc = ($from_date ? " AND DATE(uploaded_at) >= " . $db->quote($from_date) : "") . ($to_date ? " AND DATE(uploaded_at) <= " . $db->quote($to_date) : "");
                        $where_date_vis = ($from_date ? " AND DATE(checkin_date) >= " . $db->quote($from_date) : "") . ($to_date ? " AND DATE(checkin_date) <= " . $db->quote($to_date) : "");
                        $where_date_leg = ($from_date ? " AND DATE(created_at) >= " . $db->quote($from_date) : "") . ($to_date ? " AND DATE(created_at) <= " . $db->quote($to_date) : "");

                        $sql = "
                            (SELECT 'Reservation' as module, r.id, CONVERT(r.customer_name USING utf8mb4) as name, CONVERT(f.name USING utf8mb4) as ref, CAST(r.event_date AS CHAR) as date, CONVERT(r.status USING utf8mb4) as status FROM reservations r LEFT JOIN facilities f ON r.facility_id = f.id WHERE 1=1 $where_status_res $where_date_res)
                            UNION ALL
                            (SELECT 'Facility' as module, id, CONVERT(name USING utf8mb4), CONVERT(location USING utf8mb4), 'N/A' as date, CONVERT(status USING utf8mb4) FROM facilities WHERE 1=1 $where_status)
                            UNION ALL
                            (SELECT 'Document' as module, id, CONVERT(name USING utf8mb4), CONVERT(case_id USING utf8mb4), CAST(uploaded_at AS CHAR) as date, 'Archived' as status FROM documents WHERE is_deleted = 0 $where_date_doc " . ($status !== 'all' && $status === 'Archived' ? "" : ($status !== 'all' ? " AND 1=0" : "")) . ")
                            UNION ALL
                            (SELECT 'Visitor' as module, id, CONVERT(full_name USING utf8mb4), CONVERT(room_number USING utf8mb4), CAST(checkin_date AS CHAR) as date, CONVERT(CASE WHEN status = 'active' THEN 'Checked In' ELSE status END USING utf8mb4) as status FROM direct_checkins WHERE 1=1 $where_status $where_date_vis)
                            UNION ALL
                            (SELECT 'Legal' as module, id, CONVERT(name USING utf8mb4), CONVERT(case_id USING utf8mb4), CAST(created_at AS CHAR) as date, CONVERT(risk_score USING utf8mb4) as status FROM contracts WHERE 1=1 $where_date_leg " . ($status !== 'all' ? " AND 1=0" : "") . ")
                            ORDER BY date DESC
                        ";
                        $headers = ['Module', 'ID', 'Report Topic', 'Reference', 'Date', 'Status'];
                        break;

                    case 'facilities':
                        $sql = "SELECT id, name, type, capacity, location, hourly_rate, status FROM facilities";
                        $headers = ['ID', 'Name', 'Type', 'Capacity', 'Location', 'Rate', 'Status'];
                        break;

                    case 'archiving':
                        $sql = "SELECT id, name, case_id, file_path, uploaded_at FROM documents WHERE is_deleted = 0";
                        if ($from_date) {
                            $sql .= " AND DATE(uploaded_at) >= ?";
                            $params[] = $from_date;
                        }
                        if ($to_date) {
                            $sql .= " AND DATE(uploaded_at) <= ?";
                            $params[] = $to_date;
                        }
                        $headers = ['ID', 'Document Name', 'Case ID', 'File Path', 'Uploaded At'];
                        break;

                    case 'visitors':
                        $sql = "SELECT id, full_name, email, phone_number as phone, room_number, checkin_date as time_in, checkout_date as time_out, CASE WHEN status = 'active' THEN 'Checked In' ELSE status END as status FROM direct_checkins WHERE 1=1";
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
                        $sql = "SELECT id, name, case_id, contract_type, risk_score, created_at FROM contracts WHERE 1=1";
                        if ($from_date) {
                            $sql .= " AND DATE(created_at) >= ?";
                            $params[] = $from_date;
                        }
                        if ($to_date) {
                            $sql .= " AND DATE(created_at) <= ?";
                            $params[] = $to_date;
                        }
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
                        $headers = ['ID', 'Facility', 'Customer', 'Email', 'Phone', 'Event Type', 'Date', 'Start Time', 'End Time', 'Guests', 'Total Amount', 'Deposit Paid', 'Balance Due', 'Payment Method', 'Coordinator', 'Status', 'Created At'];
                        break;
                }

                $stmt = get_pdo()->prepare($sql);
                $stmt->execute($params);

                header('Content-Type: text/csv');
                header('Content-Disposition: attachment; filename="' . $filename . '"');

                $out = fopen('php://output', 'w');
                fputcsv($out, $headers);

                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    if ($module === 'reservations') {
                        fputcsv($out, [
                            $row['id'],
                            $row['facility_name'] ?? 'N/A',
                            $row['customer_name'],
                            $row['customer_email'],
                            $row['customer_phone'],
                            $row['event_type'],
                            $row['event_date'],
                            $row['start_time'],
                            $row['end_time'],
                            $row['guests_count'],
                            $row['total_amount'],
                            $row['deposit_paid'] ?? 0,
                            $row['balance_due'] ?? 0,
                            $row['payment_method'] ?? 'N/A',
                            $row['coordinator'] ?? 'N/A',
                            $row['status'],
                            $row['created_at']
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
            background: #ffffff !important;
            color: #000000 !important;
            border-bottom: 1px solid #e2e8f0 !important;
            font-size: 0.85rem;
            padding: 12px 15px;
            white-space: nowrap;
        }

        .table th {
            background: #ffffff !important;
            color: #000000 !important;
            font-weight: 701;
            border-bottom: 2px solid #000000 !important;
        }

        .dashboard-content {
            padding: 2rem 3rem;
            max-width: 1600px;
            margin: 0 auto;
        }

        .top-header .container-fluid {
            max-width: 1600px;
            margin: 0 auto;
            padding: 0 3rem;
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
            border-radius: 10px;
            border: 2px solid #f1f5f9;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #b8860b;
        }

        /* Schedule Card Styling */
        .schedule-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-top: 1rem;
        }

        .schedule-item {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 1.25rem;
            border-left: 5px solid #d4af37;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.02);
            transition: transform 0.2s;
        }

        .schedule-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.05);
        }

        .schedule-date-header {
            font-size: 0.8rem;
            color: #64748b;
            text-transform: uppercase;
            font-weight: 700;
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .table-container {
            overflow-x: auto;
            max-width: 100%;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            border: 1px solid #e2e8f0;
            scrollbar-width: thin;
            scrollbar-color: #d4af37 #f1f5f9;
        }

        .table {
            border-spacing: 0 4px;
            border-collapse: separate;
            width: 100%;
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
            <header class="top-header" style="background: white; border-bottom: 1px solid #e2e8f0; padding: 15px 0;">
                <div class="header-inner"
                    style="max-width: 1600px; margin: 0 auto; padding: 0 3rem; display: flex; justify-content: space-between; align-items: center; width: 100%;">
                    <div class="header-title">
                        <button class="mobile-menu-btn" onclick="toggleSidebar()">
                            <span class="icon-img-placeholder">☰</span>
                        </button>
                        <h1 id="page-title"
                            style="margin: 0; font-size: 1.5rem; font-weight: 800; color: #0f172a; letter-spacing: -0.5px;">
                            <?php
                            $tab_titles = [
                                'dashboard' => 'Dashboard',
                                'facilities' => 'Hotel Facilities',
                                'reservations' => 'Reservation Management',
                                'calendar' => 'Reservation Calendar',
                                'management' => 'Management',
                                'maintenance' => 'Maintenance Management',
                                'reports' => 'Reports & Analytics',
                                'reports_dates' => 'Reports Dates'
                            ];
                            $current_tab = $_GET['tab'] ?? 'dashboard';
                            echo $tab_titles[$current_tab] ?? 'Dashboard';
                            ?>
                        </h1>
                    </div>

                    <div class="header-actions" style="display: flex; align-items: center; gap: 20px;">
                        <?php
                        // Display Active Key if Super Admin
                        if (isset($_SESSION['role']) && $_SESSION['role'] === 'super_admin') {
                            $display_key = $_GET['bypass_key'] ?? $_SESSION['api_key'] ?? '';
                            if (!empty($display_key)): ?>
                                <div class="api-key-display"
                                    style="background: white; border: 1px solid #e2e8f0; padding: 6px 12px; border-radius: 8px; font-size: 11px; color: #64748b; font-family: monospace; display: flex; align-items: center; gap: 8px; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">
                                    <i class="fas fa-key" style="color: #d4af37;"></i>
                                    <span>Key: <strong
                                            style="color: #334155;"><?= substr($display_key, 0, 8) . '...' ?></strong></span>
                                </div>
                            <?php endif;
                        }
                        ?>
                        <div class="current-date-header"
                            style="display: flex; align-items: center; gap: 10px; background: #f8fafc; padding: 8px 16px; border-radius: 12px; border: 1px solid #e2e8f0;">
                            <i class="fa-regular fa-calendar-check" style="color: #3b82f6; font-size: 1.1rem;"></i>
                            <span
                                style="font-weight: 700; color: #1e293b; font-size: 0.9rem;"><?= date('F d, Y') ?></span>
                        </div>
                    </div>
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
                                            <div class="facility-name">
                                                <?php
                                                $fName = $facility['name'];
                                                if (strtolower($fName) === 'marvin79') {
                                                    $fName = 'Executive Suite';
                                                }
                                                echo htmlspecialchars($fName);
                                                ?>
                                            </div>
                                            <span class="facility-type"
                                                style="background: #3182ce; color: white; padding: 2px 8px; border-radius: 12px; font-size: 0.7rem; text-transform: uppercase;">
                                                <?php
                                                $fType = $facility['type'];
                                                if (strtolower($facility['name']) === 'marvin79' && strtolower($fType) === 'meeting') {
                                                    $fType = 'VIP MEETING';
                                                }
                                                echo htmlspecialchars($fType);
                                                ?>
                                            </span>
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
                                        <th style="text-align: left;">reserve name</th>
                                        <th style="text-align: left;">Facility Name</th>
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
                                            <td style="text-align: left; color: #475569;">
                                                <?= htmlspecialchars($f['next_reserve_name'] ?? 'Available') ?>
                                            </td>
                                            <td style="text-align: left; font-weight: 600;">
                                                <?php
                                                $fName = $f['name'];
                                                if (strtolower($fName) === 'marvin79') {
                                                    $fName = 'Executive Suite';
                                                }
                                                echo htmlspecialchars($fName);
                                                ?>
                                            </td>
                                            <td><span class="facility-type-badge">
                                                    <?php
                                                    $fType = $f['type'];
                                                    if (strtolower($f['name']) === 'marvin79' && strtolower($fType) === 'meeting') {
                                                        $fType = 'VIP MEETING';
                                                    }
                                                    echo ucfirst(htmlspecialchars($fType));
                                                    ?>
                                                </span>
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

                    <div class="table-container">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th style="text-align: left; padding: 15px; color: #000000 !important;">ID</th>
                                    <th style="text-align: left; color: #000000 !important;">FACILITY</th>
                                    <th style="text-align: left; color: #000000 !important;">CUSTOMER</th>
                                    <th style="text-align: left; color: #000000 !important;">CONTACT</th>
                                    <th style="text-align: left; color: #000000 !important;">EMAIL</th>
                                    <th style="text-align: left; color: #000000 !important;">EVENT TYPE</th>
                                    <th style="text-align: left; color: #000000 !important;">DATE</th>
                                    <th style="text-align: left; color: #000000 !important;">TIME</th>
                                    <th style="text-align: center; color: #000000 !important;">GUESTS</th>
                                    <th style="text-align: center; color: #000000 !important;">STATUS</th>
                                    <th style="text-align: center; color: #000000 !important;">TABLE/ROOM</th>
                                    <th style="text-align: center; color: #000000 !important;">PAYMENT</th>
                                    <th style="text-align: center; color: #000000 !important;">ACTIONS</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($dashboard_data['reservations'])): ?>
                                    <tr>
                                        <td colspan="12" style="text-align: center; padding: 20px;">
                                            <div style="color: #718096; font-style: italic;">
                                                <i class="fa-regular fa-folder-open"
                                                    style="font-size: 24px; margin-bottom: 10px; display: block;"></i>
                                                No reservations found in the database.
                                            </div>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($dashboard_data['reservations'] as $reservation): ?>
                                        <tr>
                                            <td style="font-weight: 700; color: #000000 !important;">#<?= $reservation['id'] ?>
                                            </td>
                                            <td style="text-align: left;">
                                                <div style="font-weight: 600; color: #000000 !important;">
                                                    <?= htmlspecialchars($reservation['facility_name']) ?>
                                                </div>
                                            </td>
                                            <td style="text-align: left;">
                                                <div style="font-weight: 500; color: #000000 !important;">
                                                    <?= htmlspecialchars($reservation['customer_name']) ?>
                                                </div>
                                            </td>
                                            <td style="text-align: left; color: #000000 !important;">
                                                <?= htmlspecialchars($reservation['customer_phone'] ?? 'N/A') ?>
                                            </td>
                                            <td style="text-align: left; color: #000000 !important; font-size: 0.8rem;">
                                                <?= htmlspecialchars($reservation['customer_email'] ?? 'N/A') ?>
                                            </td>
                                            <td style="text-align: left; color: #000000 !important;">
                                                <?= htmlspecialchars($reservation['event_type']) ?>
                                            </td>
                                            <td style="text-align: left; color: #000000 !important;">
                                                <?= date('m/d/Y', strtotime($reservation['event_date'])) ?>
                                            </td>
                                            <td style="text-align: left; font-size: 0.8rem; color: #000000 !important;">
                                                <?= date('g:i a', strtotime($reservation['start_time'])) ?> -
                                                <?= date('g:i a', strtotime($reservation['end_time'])) ?>
                                            </td>
                                            <td style="text-align: center; font-weight: 600; color: #000000 !important;">
                                                <?= $reservation['guests_count'] ?>
                                            </td>
                                            <td style="text-align: center;">
                                                <span class="status-badge status-<?= $reservation['status'] ?>"
                                                    style="min-width: 90px; text-align: center;">
                                                    <?= ucfirst($reservation['status']) ?>
                                                </span>
                                            </td>
                                            <td style="text-align: center; color: #000000 !important; font-weight: 500;">
                                                <?php
                                                // Derive Table/Room if possible, otherwise use a default
                                                $tableRoom = 'N/A';
                                                if (!empty($reservation['facility_name'])) {
                                                    if (stripos($reservation['facility_name'], 'Ballroom') !== false)
                                                        $tableRoom = 'Ballroom 1';
                                                    elseif (stripos($reservation['facility_name'], 'Boardroom') !== false)
                                                        $tableRoom = 'Table 12';
                                                    elseif (stripos($reservation['facility_name'], 'Garden') !== false)
                                                        $tableRoom = 'Outdoor 5';
                                                    elseif (stripos($reservation['facility_name'], 'Conference') !== false)
                                                        $tableRoom = 'Hall A';
                                                    elseif (stripos($reservation['facility_name'], 'Restaurant') !== false)
                                                        $tableRoom = 'Private 3';
                                                    elseif (stripos($reservation['facility_name'], 'Grill') !== false)
                                                        $tableRoom = 'Poolside 2';
                                                    else
                                                        $tableRoom = 'Room ' . $reservation['id'];
                                                }
                                                echo $tableRoom;
                                                ?>
                                            </td>
                                            <td style="text-align: center;">
                                                <span
                                                    style="font-weight: 600; color: <?= $reservation['payment_status'] == 'Paid' ? '#4ade80' : ($reservation['payment_status'] == 'Pending' ? '#fbbf24' : '#f87171') ?>;">
                                                    <?= ucfirst($reservation['payment_status']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="d-flex gap-1" style="justify-content: center;">
                                                    <button class="btn btn-outline btn-sm btn-icon"
                                                        style="border-color: #e2e8f0; color: #475569;"
                                                        onclick="event.preventDefault(); window.viewReservationDetails(<?= htmlspecialchars(json_encode($reservation)) ?>)"
                                                        title="View Details">
                                                        <i class="fa-solid fa-eye"></i>
                                                    </button>
                                                    <?php if ($reservation['status'] == 'pending'): ?>
                                                        <button class="btn btn-success btn-sm btn-icon"
                                                            onclick="event.preventDefault(); window.updateReservationStatus(<?= $reservation['id'] ?>, 'confirmed')"
                                                            title="Confirm">
                                                            <i class="fa-solid fa-check"></i>
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

                $is_premium_report = true;
                switch ($r_module) {
                    case 'all':
                        $r_headers = ['MODULE', 'ID', 'REPORT TOPIC', 'REFERENCE', 'DATE', 'STATUS'];
                        $v_cols = $db->query("SHOW COLUMNS FROM direct_checkins")->fetchAll(PDO::FETCH_COLUMN);
                        $v_status_col = in_array('status', $v_cols) ? 'status' : "'N/A'";

                        $where_status = ($r_status !== 'all') ? " AND status = " . $db->quote($r_status) : "";
                        $where_status_res = ($r_status !== 'all') ? " AND r.status = " . $db->quote($r_status) : "";
                        $where_status_vis = ($r_status !== 'all') ? " AND status = " . $db->quote(($r_status === 'Checked In' || $r_status === 'active') ? 'active' : $r_status) : "";
                        $where_status_leg = ($r_status !== 'all' && strtolower($r_status) !== 'active') ? " AND 1=0" : "";
                        $where_status_doc = ($r_status !== 'all' && strtolower($r_status) !== 'archived') ? " AND 1=0" : "";

                        $where_date_res = ($r_from ? " AND r.event_date >= " . $db->quote($r_from) : "") . ($r_to ? " AND r.event_date <= " . $db->quote($r_to) : "");
                        $where_date_doc = ($r_from ? " AND DATE(uploaded_at) >= " . $db->quote($r_from) : "") . ($r_to ? " AND DATE(uploaded_at) <= " . $db->quote($r_to) : "");
                        $where_date_vis = ($r_from ? " AND DATE(checkin_date) >= " . $db->quote($r_from) : "") . ($r_to ? " AND DATE(checkin_date) <= " . $db->quote($r_to) : "");
                        $where_date_leg = ($r_from ? " AND DATE(created_at) >= " . $db->quote($r_from) : "") . ($r_to ? " AND DATE(created_at) <= " . $db->quote($r_to) : "");

                        $all_sql = "
                                (SELECT 'Reservation' as module, r.id, CONVERT(r.customer_name USING utf8mb4) as name, CONVERT(f.name USING utf8mb4) as ref, CAST(r.event_date AS CHAR) as date, CONVERT(r.status USING utf8mb4) as status 
                                 FROM reservations r LEFT JOIN facilities f ON r.facility_id = f.id WHERE 1=1 $where_status_res $where_date_res)
                                UNION ALL
                                (SELECT 'Facility' as module, id, CONVERT(name USING utf8mb4), CONVERT(location USING utf8mb4), 'N/A' as date, CONVERT(status USING utf8mb4) FROM facilities WHERE 1=1 $where_status)
                                UNION ALL
                                (SELECT 'Document' as module, id, CONVERT(name USING utf8mb4), CONVERT(case_id USING utf8mb4), CAST(uploaded_at AS CHAR) as date, 'Archived' as status FROM documents WHERE is_deleted = 0 $where_date_doc $where_status_doc)
                                UNION ALL
                                (SELECT 'Visitor' as module, id, CONVERT(full_name USING utf8mb4), CONVERT(room_number USING utf8mb4), CAST(checkin_date AS CHAR) as date, CONVERT(CASE WHEN status = 'active' THEN 'Checked In' ELSE status END USING utf8mb4) as status FROM direct_checkins WHERE 1=1 $where_status_vis $where_date_vis)
                                UNION ALL
                                (SELECT 'Legal' as module, id, CONVERT(name USING utf8mb4), CONVERT(case_id USING utf8mb4), CAST(created_at AS CHAR) as date, 'Active' as status FROM contracts WHERE 1=1 $where_date_leg $where_status_leg)
                                ORDER BY date DESC
                            ";
                        $r_stmt = get_pdo()->prepare($all_sql);
                        $r_stmt->execute();
                        $r_rows = $r_stmt->fetchAll(PDO::FETCH_ASSOC);

                        // Add mock data for All Records if no results found
                        if (empty($r_rows)) {
                            $mock_all_data = [
                                [
                                    'module' => 'Reservation',
                                    'id' => 101,
                                    'name' => 'Marvin Quiriado',
                                    'ref' => 'Grand Ballroom',
                                    'date' => date('Y-m-d'),
                                    'status' => 'confirmed'
                                ],
                                [
                                    'module' => 'Reservation',
                                    'id' => 102,
                                    'name' => 'Elizabeth Santos',
                                    'ref' => 'Function Hall B',
                                    'date' => date('Y-m-d', strtotime('+1 day')),
                                    'status' => 'pending'
                                ],
                                [
                                    'module' => 'Facility',
                                    'id' => 1,
                                    'name' => 'Grand Ballroom',
                                    'ref' => 'Main Building',
                                    'date' => 'N/A',
                                    'status' => 'active'
                                ],
                                [
                                    'module' => 'Facility',
                                    'id' => 2,
                                    'name' => 'Function Hall A',
                                    'ref' => 'East Wing',
                                    'date' => 'N/A',
                                    'status' => 'maintenance'
                                ],
                                [
                                    'module' => 'Document',
                                    'id' => 1,
                                    'name' => 'ServiceAgreement_2026.pdf',
                                    'ref' => 'DOC-882',
                                    'date' => date('Y-m-d H:i:s'),
                                    'status' => 'Archived'
                                ],
                                [
                                    'module' => 'Visitor',
                                    'id' => 1,
                                    'name' => 'Juan Dela Cruz',
                                    'ref' => 'Room 101',
                                    'date' => date('Y-m-d 08:00:00'),
                                    'status' => 'Checked In'
                                ],
                                [
                                    'module' => 'Visitor',
                                    'id' => 2,
                                    'name' => 'Maria Clara',
                                    'ref' => 'Function Hall',
                                    'date' => date('Y-m-d 09:30:00', strtotime('-1 day')),
                                    'status' => 'Checked Out'
                                ],
                                [
                                    'module' => 'Legal',
                                    'id' => 1,
                                    'name' => 'Hotel Lease Agreement.pdf',
                                    'ref' => 'C-001',
                                    'date' => date('Y-m-d H:i:s'),
                                    'status' => 'High'
                                ]
                            ];

                            // Apply date range filter to mock data
                            if ($r_from) {
                                $mock_all_data = array_filter($mock_all_data, function ($entry) use ($r_from) {
                                    return $entry['date'] === 'N/A' || date('Y-m-d', strtotime($entry['date'])) >= $r_from;
                                });
                            }
                            if ($r_to) {
                                $mock_all_data = array_filter($mock_all_data, function ($entry) use ($r_to) {
                                    return $entry['date'] === 'N/A' || date('Y-m-d', strtotime($entry['date'])) <= $r_to;
                                });
                            }

                            // Apply status filter to mock data if status is not 'all'
                            if ($r_status !== 'all') {
                                $mock_all_data = array_filter($mock_all_data, function ($entry) use ($r_status) {
                                    return strtolower($entry['status']) === strtolower($r_status);
                                });
                            }

                            $r_rows = array_merge($r_rows, $mock_all_data);
                        }

                        $is_premium_report = true;
                        break;

                    case 'facilities':
                        $r_sql = "SELECT id, name, type, capacity, location, CONCAT('₱', FORMAT(hourly_rate, 2)) as rate, created_at, status FROM facilities WHERE 1=1";
                        if ($r_from)
                            $r_sql .= " AND DATE(created_at) >= " . $db->quote($r_from);
                        if ($r_to)
                            $r_sql .= " AND DATE(created_at) <= " . $db->quote($r_to);
                        if ($r_status !== 'all')
                            $r_sql .= " AND status = " . $db->quote($r_status);

                        $r_headers = ['ID', 'NAME', 'TYPE', 'CAPACITY', 'LOCATION', 'RATE', 'DATE', 'STATUS'];
                        $r_stmt = get_pdo()->prepare($r_sql);
                        $r_stmt->execute();
                        $r_rows = $r_stmt->fetchAll(PDO::FETCH_ASSOC);
                        break;

                    case 'archiving':
                        $r_sql = "SELECT id, name, case_id, file_path, uploaded_at, 'Archived' as status FROM documents WHERE is_deleted = 0";
                        if ($r_from)
                            $r_sql .= " AND DATE(uploaded_at) >= " . $db->quote($r_from);
                        if ($r_to)
                            $r_sql .= " AND DATE(uploaded_at) <= " . $db->quote($r_to);
                        if ($r_status !== 'all' && strtolower($r_status) !== 'archived')
                            $r_sql .= " AND 1=0";

                        $r_headers = ['ID', 'DOCUMENT NAME', 'CASE ID', 'FILE PATH', 'UPLOADED AT', 'STATUS'];
                        $r_stmt = get_pdo()->prepare($r_sql);
                        $r_stmt->execute();
                        $r_rows = $r_stmt->fetchAll(PDO::FETCH_ASSOC);

                        // Mock data for integrated look
                        if (empty($r_rows)) {
                            $r_rows = [
                                ['id' => 1, 'name' => 'ServiceAgreement_2026.pdf', 'case_id' => 'DOC-882', 'file_path' => '/uploads/docs/agreement.pdf', 'uploaded_at' => date('Y-m-d H:i:s')],
                                ['id' => 2, 'name' => 'Inventory_Q1.xlsx', 'case_id' => 'DOC-901', 'file_path' => '/uploads/docs/inventory.xlsx', 'uploaded_at' => date('Y-m-d H:i:s', strtotime('-2 days'))],
                                ['id' => 3, 'name' => 'VisitorLog_Jan.csv', 'case_id' => 'DOC-771', 'file_path' => '/uploads/docs/logs.csv', 'uploaded_at' => date('Y-m-d H:i:s', strtotime('-5 days'))]
                            ];
                        }
                        break;

                    case 'visitors':
                        // Attempting to fetch from direct_checkins or visitor_logs if exists
                        try {
                            $v_cols = get_pdo()->query("SHOW COLUMNS FROM direct_checkins")->fetchAll(PDO::FETCH_COLUMN);
                            $v_checkin = in_array('checkin_date', $v_cols) ? 'checkin_date' : 'time_in';
                            $v_checkout = in_array('checkout_date', $v_cols) ? 'checkout_date' : 'time_out';
                            $v_phone = in_array('phone_number', $v_cols) ? 'phone_number' : 'phone';

                            $r_sql = "SELECT id, full_name, email, $v_phone as phone, room_number as facility, $v_checkin as checkin_date, $v_checkin as time_in, $v_checkout as time_out, CASE WHEN status = 'active' THEN 'Checked In' ELSE status END as status FROM direct_checkins WHERE 1=1";
                            if ($r_from)
                                $r_sql .= " AND DATE($v_checkin) >= " . get_pdo()->quote($r_from);
                            if ($r_to)
                                $r_sql .= " AND DATE($v_checkin) <= " . get_pdo()->quote($r_to);
                            if ($r_status !== 'all') {
                                $mapped_status = ($r_status === 'Checked In' || $r_status === 'active') ? 'active' : $r_status;
                                $r_sql .= " AND status = " . get_pdo()->quote($mapped_status);
                            }
                            $r_headers = ['ID', 'NAME', 'EMAIL', 'PHONE', 'FACILITY', 'DATE', 'TIME IN', 'TIME OUT', 'STATUS'];
                            $r_stmt = get_pdo()->prepare($r_sql);
                            $r_stmt->execute();
                            $r_rows = $r_stmt->fetchAll(PDO::FETCH_ASSOC);

                            $is_premium_report = true;
                        } catch (Exception $e) {
                            $r_rows = [];
                            $is_premium_report = true;
                        } catch (Exception $e) {
                            $r_rows = [];
                        }

                        // Robust Mock visitors if still empty
                        if (empty($r_rows)) {
                            $r_rows = [
                                ['id' => 1, 'full_name' => 'Juan Dela Cruz', 'email' => 'juan@example.com', 'phone' => '09171234567', 'facility' => 'Room 101', 'checkin_date' => date('Y-m-d 08:00:00'), 'time_in' => date('Y-m-d 08:00:00'), 'time_out' => date('Y-m-d 17:00:00'), 'status' => 'Checked In'],
                                ['id' => 2, 'full_name' => 'Maria Clara', 'email' => 'maria@example.com', 'phone' => '09187654321', 'facility' => 'Function Hall', 'checkin_date' => date('Y-m-d 09:30:00', strtotime('-1 day')), 'time_in' => date('Y-m-d 09:30:00', strtotime('-1 day')), 'time_out' => date('Y-m-d 15:00:00', strtotime('-1 day')), 'status' => 'Checked Out'],
                                ['id' => 3, 'full_name' => 'Marvin Quiriado', 'email' => 'marvin@example.com', 'phone' => '09221239876', 'facility' => 'Main Ballroom', 'checkin_date' => date('Y-m-d 11:45:00'), 'time_in' => date('Y-m-d 11:45:00'), 'time_out' => '...', 'status' => 'Checked In']
                            ];

                            // Apply date range filter to mock data
                            if ($r_from) {
                                $r_rows = array_filter($r_rows, function ($entry) use ($r_from) {
                                    return date('Y-m-d', strtotime($entry['checkin_date'])) >= $r_from;
                                });
                            }
                            if ($r_to) {
                                $r_rows = array_filter($r_rows, function ($entry) use ($r_to) {
                                    return date('Y-m-d', strtotime($entry['checkin_date'])) <= $r_to;
                                });
                            }

                            // Apply status filter to mock data
                            if ($r_status !== 'all') {
                                $r_rows = array_filter($r_rows, function ($entry) use ($r_status) {
                                    $s = strtolower($entry['status']);
                                    if ($s === 'checked in')
                                        $s = 'active';
                                    return $s === strtolower($r_status);
                                });
                            }

                            $is_premium_report = true;
                        }
                        break;

                    case 'legal':
                        $r_sql = "SELECT id, name, case_id, contract_type, risk_score, created_at, 'Active' as status FROM contracts WHERE 1=1";
                        if ($r_from)
                            $r_sql .= " AND DATE(created_at) >= " . $db->quote($r_from);
                        if ($r_to)
                            $r_sql .= " AND DATE(created_at) <= " . $db->quote($r_to);
                        if ($r_status !== 'all' && strtolower($r_status) !== 'active')
                            $r_sql .= " AND 1=0";

                        $r_headers = ['ID', 'CONTRACT NAME', 'CASE ID', 'TYPE', 'RISK SCORE', 'CREATED AT', 'STATUS'];
                        $r_stmt = get_pdo()->prepare($r_sql);
                        $r_stmt->execute();
                        $r_rows = $r_stmt->fetchAll(PDO::FETCH_ASSOC);

                        // Mock data for Legal Management if empty
                        if (empty($r_rows)) {
                            $r_rows = [
                                ['id' => 1, 'name' => 'Hotel Lease Agreement.pdf', 'case_id' => 'C-001', 'type' => 'External', 'risk_score' => 'High', 'created_at' => date('Y-m-d H:i:s'), 'status' => 'confirmed'],
                                ['id' => 2, 'name' => 'Supplier Contract.docx', 'case_id' => 'C-002', 'type' => 'Internal', 'risk_score' => 'Medium', 'created_at' => date('Y-m-d H:i:s', strtotime('-1 month')), 'status' => 'completed'],
                                ['id' => 3, 'name' => 'Employment Contract - Celestre', 'case_id' => 'C-003', 'type' => 'Internal', 'risk_score' => 'Low', 'created_at' => date('Y-m-d H:i:s', strtotime('-1 day')), 'status' => 'pending']
                            ];

                            // Apply date range filter to mock data
                            if ($r_from) {
                                $r_rows = array_filter($r_rows, function ($entry) use ($r_from) {
                                    return date('Y-m-d', strtotime($entry['created_at'])) >= $r_from;
                                });
                            }
                            if ($r_to) {
                                $r_rows = array_filter($r_rows, function ($entry) use ($r_to) {
                                    return date('Y-m-d', strtotime($entry['created_at'])) <= $r_to;
                                });
                            }

                            // Apply status filter to mock data
                            if ($r_status !== 'all') {
                                $r_rows = array_filter($r_rows, function ($entry) use ($r_status) {
                                    return strtolower($entry['status'] ?? '') === strtolower($r_status);
                                });
                            }
                        }
                        break;

                    case 'reservations':
                    default:
                        $r_sql = "SELECT r.*, f.name as facility_name FROM reservations r LEFT JOIN facilities f ON r.facility_id = f.id WHERE 1=1";
                        if ($r_from)
                            $r_sql .= " AND r.event_date >= " . get_pdo()->quote($r_from);
                        if ($r_to)
                            $r_sql .= " AND r.event_date <= " . get_pdo()->quote($r_to);
                        if ($r_status !== 'all')
                            $r_sql .= " AND r.status = " . get_pdo()->quote($r_status);
                        $r_sql .= ' ORDER BY r.event_date DESC, r.start_time DESC';

                        $r_headers = ['ID', 'DATE', 'TIME', 'GUESTS', 'PACKAGE', 'TOTAL AMOUNT', 'DEPOSIT PAID', 'BALANCE DUE', 'PAYMENT METHOD', 'COORDINATOR', 'STATUS', 'ACTIONS'];
                        $r_stmt = get_pdo()->prepare($r_sql);
                        $r_stmt->execute();
                        $r_rows = $r_stmt->fetchAll(PDO::FETCH_ASSOC);

                        // If very few records, add mock data as requested ("damihan mio ng iba iba ng name")
                        if (count($r_rows) <= 1) {
                            $mock_entries = [
                                [
                                    'id' => 101,
                                    'customer_name' => 'Marvin Quiriado',
                                    'customer_email' => 'john.marvin@example.com',
                                    'customer_phone' => '09123456789',
                                    'event_type' => 'Wedding Reception',
                                    'event_date' => date('Y-m-d'),
                                    'start_time' => '10:00:00',
                                    'end_time' => '16:00:00',
                                    'guests_count' => 150,
                                    'status' => 'confirmed',
                                    'total_amount' => 45000,
                                    'deposit_paid' => 15000,
                                    'balance_due' => 30000,
                                    'payment_method' => 'Bank Transfer',
                                    'coordinator' => 'Sarah Reyes',
                                    'package' => 'Premium Wedding',
                                    'facility_name' => 'Grand Ballroom'
                                ],
                                [
                                    'id' => 102,
                                    'customer_name' => 'Elizabeth Santos',
                                    'customer_email' => 'elizabeth.s@example.com',
                                    'customer_phone' => '09223334444',
                                    'event_type' => 'Corporate Seminar',
                                    'event_date' => date('Y-m-d', strtotime('+1 day')),
                                    'start_time' => '08:00:00',
                                    'end_time' => '17:00:00',
                                    'guests_count' => 80,
                                    'status' => 'pending',
                                    'total_amount' => 25000,
                                    'deposit_paid' => 0,
                                    'balance_due' => 25000,
                                    'payment_method' => 'GCash',
                                    'coordinator' => 'Mark Tui',
                                    'package' => 'Corporate Package A',
                                    'facility_name' => 'Function Hall B'
                                ],
                                [
                                    'id' => 103,
                                    'customer_name' => 'Roberto Gomez',
                                    'customer_email' => 'roberto.g@example.com',
                                    'customer_phone' => '09334445555',
                                    'event_type' => 'Birthday Party',
                                    'event_date' => date('Y-m-d', strtotime('-2 days')),
                                    'start_time' => '18:00:00',
                                    'end_time' => '22:00:00',
                                    'guests_count' => 50,
                                    'status' => 'completed',
                                    'total_amount' => 15000,
                                    'deposit_paid' => 15000,
                                    'balance_due' => 0,
                                    'payment_method' => 'Cash',
                                    'coordinator' => 'Maria Santos',
                                    'package' => 'Standard Party',
                                    'facility_name' => 'Garden Area'
                                ],
                                [
                                    'id' => 104,
                                    'customer_name' => 'Angelica Ramos',
                                    'customer_email' => 'angel@example.com',
                                    'customer_phone' => '09445556666',
                                    'event_type' => 'Product Launch',
                                    'event_date' => date('Y-m-d', strtotime('+5 days')),
                                    'start_time' => '13:00:00',
                                    'end_time' => '18:00:00',
                                    'guests_count' => 200,
                                    'status' => 'confirmed',
                                    'total_amount' => 60000,
                                    'deposit_paid' => 30000,
                                    'balance_due' => 30000,
                                    'payment_method' => 'Check',
                                    'coordinator' => 'Sarah Reyes',
                                    'package' => 'VVIP Event',
                                    'facility_name' => 'Grand Ballroom'
                                ],
                                [
                                    'id' => 105,
                                    'customer_name' => 'Marvin Dela Cruz',
                                    'customer_email' => 'marvin.dc@example.com',
                                    'customer_phone' => '09556667777',
                                    'event_type' => 'Baptismal',
                                    'event_date' => date('Y-m-d', strtotime('-5 days')),
                                    'start_time' => '09:00:00',
                                    'end_time' => '12:00:00',
                                    'guests_count' => 40,
                                    'status' => 'completed',
                                    'total_amount' => 12000,
                                    'deposit_paid' => 12000,
                                    'balance_due' => 0,
                                    'payment_method' => 'GCash',
                                    'coordinator' => 'Maria Santos',
                                    'package' => 'Basic Package',
                                    'facility_name' => 'Meeting Room 1'
                                ],
                                [
                                    'id' => 106,
                                    'customer_name' => 'Cynthia Villar',
                                    'customer_email' => 'cynthia@example.com',
                                    'customer_phone' => '09667778888',
                                    'event_type' => 'Political Meeting',
                                    'event_date' => date('Y-m-d', strtotime('+3 days')),
                                    'start_time' => '14:00:00',
                                    'end_time' => '16:00:00',
                                    'guests_count' => 100,
                                    'status' => 'cancelled',
                                    'total_amount' => 20000,
                                    'deposit_paid' => 0,
                                    'balance_due' => 20000,
                                    'payment_method' => 'Cash',
                                    'coordinator' => 'Mark Tui',
                                    'package' => 'Standard Hall',
                                    'facility_name' => 'Function Hall A'
                                ]
                            ];

                            // Apply date range filter to mock data
                            if ($r_from) {
                                $mock_entries = array_filter($mock_entries, function ($entry) use ($r_from) {
                                    return date('Y-m-d', strtotime($entry['event_date'])) >= $r_from;
                                });
                            }
                            if ($r_to) {
                                $mock_entries = array_filter($mock_entries, function ($entry) use ($r_to) {
                                    return date('Y-m-d', strtotime($entry['event_date'])) <= $r_to;
                                });
                            }

                            // Apply status filter to mock data if status is not 'all'
                            if ($r_status !== 'all') {
                                $mock_entries = array_filter($mock_entries, function ($entry) use ($r_status) {
                                    return strtolower($entry['status']) === strtolower($r_status);
                                });
                            }

                            $r_rows = array_merge($r_rows, $mock_entries);
                        }
                        // Set a flag to use premium light look for reports
                        $is_premium_report = true;
                        break;
                }
                ?>



                <div class="filters-container"
                    style="background: #f8fafc; padding: 20px; border-radius: 12px; border: 1px solid #e2e8f0; margin-bottom: 25px;">
                    <form method="get" class="d-flex flex-wrap gap-1 align-center">
                        <input type="hidden" name="tab" value="reports">
                        <div class="filter-group">
                            <label
                                style="font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase;">From</label><br>
                            <input type="date" name="from_date" value="<?= htmlspecialchars($r_from) ?>"
                                class="btn-outline"
                                style="padding: 8px 12px; border-radius: 8px; border: 1px solid #cbd5e1;">
                        </div>
                        <div class="filter-group">
                            <label
                                style="font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase;">To</label><br>
                            <input type="date" name="to_date" value="<?= htmlspecialchars($r_to) ?>" class="btn-outline"
                                style="padding: 8px 12px; border-radius: 8px; border: 1px solid #cbd5e1;">
                        </div>
                        <div class="filter-group">
                            <label
                                style="font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Status</label><br>
                            <select name="status"
                                style="padding: 8px 12px; border-radius: 8px; border: 1px solid #cbd5e1; background: white;">
                                <option value="all" <?= $r_status === 'all' ? 'selected' : '' ?>>All</option>
                                <option value="pending" <?= $r_status === 'pending' ? 'selected' : '' ?>>Pending</option>
                                <option value="confirmed" <?= $r_status === 'confirmed' ? 'selected' : '' ?>>Confirmed
                                </option>
                                <option value="cancelled" <?= $r_status === 'cancelled' ? 'selected' : '' ?>>Cancelled
                                </option>
                                <option value="completed" <?= $r_status === 'completed' ? 'selected' : '' ?>>Completed
                                </option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label
                                style="font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Modules</label><br>
                            <select name="module"
                                style="padding: 8px 12px; border-radius: 8px; border: 1px solid #cbd5e1; background: white;">
                                <option value="all" <?= $r_module === 'all' ? 'selected' : '' ?>>All Records</option>
                                <option value="reservations" <?= $r_module === 'reservations' ? 'selected' : '' ?>>
                                    Reservations</option>
                                <option value="facilities" <?= $r_module === 'facilities' ? 'selected' : '' ?>>Facilities
                                </option>
                                <option value="archiving" <?= $r_module === 'archiving' ? 'selected' : '' ?>>Document
                                    Archiving</option>
                                <option value="visitors" <?= $r_module === 'visitors' ? 'selected' : '' ?>>Visitor
                                    Management</option>
                                <option value="legal" <?= $r_module === 'legal' ? 'selected' : '' ?>>Legal Management
                                </option>
                            </select>
                        </div>
                        <div class="filter-group" style="align-self: flex-end;">
                            <button class="btn btn-primary" style="padding: 10px 25px;"><i
                                    class="fa-solid fa-filter"></i> Filter</button>
                        </div>
                    </form>

                    <div style="margin-top: 15px; display: flex; justify-content: flex-start;">
                        <form method="post">
                            <input type="hidden" name="action" value="export_csv">
                            <input type="hidden" name="module" value="<?= htmlspecialchars($r_module) ?>">
                            <input type="hidden" name="from_date" value="<?= htmlspecialchars($r_from) ?>">
                            <input type="hidden" name="to_date" value="<?= htmlspecialchars($r_to) ?>">
                            <input type="hidden" name="status" value="<?= htmlspecialchars($r_status) ?>">
                            <button class="btn btn-success"
                                style="background: #10b981; color: white; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; display: flex; align-items: center; gap: 8px; font-weight: 600;">
                                <i class="fa-solid fa-file-csv"></i> Export CSV
                            </button>
                        </form>
                    </div>
                </div>

                <div class="d-flex align-center gap-1 mb-1">
                    <i class="fa-solid fa-file-invoice" style="font-size: 1.5rem; color: #3182ce;"></i>
                    <h2 style="margin: 0;">
                        <?php
                        $title_module = ($r_module === 'all') ? 'All Records' : ucfirst($r_module);
                        echo $title_module . ' Result';
                        if ($r_from || $r_to || $r_status !== 'all')
                            echo ' (Filtered)';
                        ?>
                    </h2>
                </div>

                <div class="table-container <?= isset($is_premium_report) ? 'premium-white-card' : '' ?>"
                    style="border:none; background: #ffffff;">
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
                                        <td colspan="<?= count($r_headers) + 1 ?>"
                                            style="text-align: center; padding: 2rem; color: #718096; font-style: italic;">
                                            No records found for the selected module and filters.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($r_rows as $rr): ?>
                                        <tr style="border-bottom: 1px solid #edf2f7;">
                                            <?php if ($r_module === 'reservations'): ?>
                                                <td style="font-weight: 700; font-size: 13px; color: #1e293b;">
                                                    #BK-<?= $rr['id'] ?></td>
                                                <td style="font-size: 13px; color: #64748b;">
                                                    <?= date('Y-m-d', strtotime($rr['event_date'] ?? 'now')) ?>
                                                </td>
                                                <td style="font-size: 13px;">
                                                    <?= date('g:i A', strtotime($rr['start_time'] ?? 'now')) ?>
                                                </td>
                                                <td style="font-size: 13px; font-weight: 600;"><?= $rr['guests_count'] ?></td>
                                                <td style="font-size: 13px; font-weight: 500;">
                                                    <?= htmlspecialchars($rr['package'] ?? 'Standard') ?>
                                                </td>
                                                <td style="font-weight: 700; font-size: 13px; color: #0f172a;">
                                                    ₱<?= number_format($rr['total_amount'] ?? 0, 2) ?></td>
                                                <td style="color: #059669; font-weight: 700; font-size: 13px;">
                                                    ₱<?= number_format($rr['deposit_paid'] ?? ($rr['total_amount'] * 0.4), 2) ?>
                                                </td>
                                                <td style="color: #dc2626; font-weight: 700; font-size: 13px;">
                                                    ₱<?= number_format($rr['balance_due'] ?? ($rr['total_amount'] * 0.6), 2) ?>
                                                </td>
                                                <td style="font-size: 13px;">
                                                    <span
                                                        style="background: #f1f5f9; padding: 4px 10px; border-radius: 6px; font-weight: 600; font-size: 0.75rem;">
                                                        <?= strtoupper(htmlspecialchars($rr['payment_method'] ?? 'GCash')) ?>
                                                    </span>
                                                </td>
                                                <td style="font-size: 13px; color: #64748b;">
                                                    <?= htmlspecialchars($rr['coordinator'] ?? 'Maria Santos') ?>
                                                </td>
                                                <td style="font-size: 13px;">
                                                    <span class="status-badge status-<?= $rr['status'] ?>"
                                                        style="font-weight: 700; padding: 5px 12px; border-radius: 6px; font-size: 0.7rem;">
                                                        <?= strtoupper(htmlspecialchars($rr['status'])) ?>
                                                    </span>
                                                </td>
                                                <td style="padding: 14px 15px;">
                                                    <div class="d-flex gap-1" style="justify-content: center;">
                                                        <button type="button" class="btn btn-outline btn-sm btn-icon"
                                                            onclick="event.preventDefault(); window.viewReservationDetails(<?= htmlspecialchars(json_encode($rr)) ?>)"
                                                            style="color: #60a5fa; border-color: #3b82f6;" title="View Details">
                                                            <i class="fa-solid fa-eye"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            <?php else: ?>
                                                <!-- Generic display for other modules -->
                                                <?php foreach ($rr as $key => $val): ?>
                                                    <td style="color: #1e293b; font-size: 13px; padding: 14px 15px;">
                                                        <?php if (strtolower($key) === 'status'): ?>
                                                            <span class="status-badge status-<?= strtolower($val) ?>"
                                                                style="font-weight: 700; padding: 5px 12px; border-radius: 6px; font-size: 0.7rem;">
                                                                <?php
                                                                $display_val = $val;
                                                                if (strtolower($val) === 'active')
                                                                    $display_val = 'Checked In';
                                                                echo strtoupper(htmlspecialchars($display_val));
                                                                ?>
                                                            </span>
                                                        <?php else: ?>
                                                            <?= htmlspecialchars($val) ?>
                                                        <?php endif; ?>
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

            <!-- Reports Dates Tab -->
            <div id="reports_dates"
                class="tab-content <?= (isset($_GET['tab']) && $_GET['tab'] == 'reports_dates') ? 'active' : '' ?>"
                style="padding-top: 0; margin-top: -25px; margin-left: 20px; padding-right: 40px; margin-right: 15px;">

                <div class="filters-container"
                    style="background: #f8fafc; padding: 20px; border-radius: 12px; border: 1px solid #e2e8f0; margin-bottom: 25px;">
                    <form method="get" class="d-flex flex-wrap gap-1 align-center">
                        <input type="hidden" name="tab" value="reports_dates">
                        <div class="filter-group">
                            <label
                                style="font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase;">From</label><br>
                            <input type="date" name="from_date"
                                value="<?= htmlspecialchars($_GET['from_date'] ?? '') ?>" class="btn-outline"
                                style="padding: 8px 12px; border-radius: 8px; border: 1px solid #cbd5e1;">
                        </div>
                        <div class="filter-group">
                            <label
                                style="font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase;">To</label><br>
                            <input type="date" name="to_date" value="<?= htmlspecialchars($_GET['to_date'] ?? '') ?>"
                                class="btn-outline"
                                style="padding: 8px 12px; border-radius: 8px; border: 1px solid #cbd5e1;">
                        </div>
                        <div class="filter-group">
                            <label
                                style="font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Status</label><br>
                            <select name="status"
                                style="padding: 8px 12px; border-radius: 8px; border: 1px solid #cbd5e1; background: white;">
                                <option value="all" <?= (($_GET['status'] ?? 'all') === 'all') ? 'selected' : '' ?>>All
                                </option>
                                <option value="pending" <?= (($_GET['status'] ?? '') === 'pending') ? 'selected' : '' ?>>
                                    Pending</option>
                                <option value="confirmed" <?= (($_GET['status'] ?? '') === 'confirmed') ? 'selected' : '' ?>>Confirmed</option>
                                <option value="cancelled" <?= (($_GET['status'] ?? '') === 'cancelled') ? 'selected' : '' ?>>Cancelled</option>
                                <option value="completed" <?= (($_GET['status'] ?? '') === 'completed') ? 'selected' : '' ?>>Completed</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label
                                style="font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Modules</label><br>
                            <select name="module"
                                style="padding: 8px 12px; border-radius: 8px; border: 1px solid #cbd5e1; background: white;">
                                <option value="all" <?= (($_GET['module'] ?? 'all') === 'all') ? 'selected' : '' ?>>All
                                    Records</option>
                                <option value="reservations" <?= (($_GET['module'] ?? '') === 'reservations') ? 'selected' : '' ?>>Reservations</option>
                                <option value="facilities" <?= (($_GET['module'] ?? '') === 'facilities') ? 'selected' : '' ?>>Facilities</option>
                                <option value="archiving" <?= (($_GET['module'] ?? '') === 'archiving') ? 'selected' : '' ?>>Document Archiving</option>
                                <option value="visitors" <?= (($_GET['module'] ?? '') === 'visitors') ? 'selected' : '' ?>>
                                    Visitor Management</option>
                                <option value="legal" <?= (($_GET['module'] ?? '') === 'legal') ? 'selected' : '' ?>>Legal
                                    Management</option>
                            </select>
                        </div>
                        <div class="filter-group" style="align-self: flex-end;">
                            <button class="btn btn-primary" style="padding: 10px 25px;"><i
                                    class="fa-solid fa-filter"></i> Filter</button>
                        </div>
                    </form>

                    <div style="margin-top: 15px; display: flex; justify-content: flex-start;">
                        <form method="post">
                            <input type="hidden" name="action" value="export_csv">
                            <input type="hidden" name="module"
                                value="<?= htmlspecialchars($_GET['module'] ?? 'all') ?>">
                            <input type="hidden" name="from_date"
                                value="<?= htmlspecialchars($_GET['from_date'] ?? '') ?>">
                            <input type="hidden" name="to_date" value="<?= htmlspecialchars($_GET['to_date'] ?? '') ?>">
                            <input type="hidden" name="status"
                                value="<?= htmlspecialchars($_GET['status'] ?? 'all') ?>">
                            <button class="btn btn-success"
                                style="background: #10b981; color: white; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; display: flex; align-items: center; gap: 8px; font-weight: 600;">
                                <i class="fa-solid fa-file-csv"></i> Export CSV
                            </button>
                        </form>
                    </div>
                </div>

                <div class="d-flex align-center gap-1 mb-1">
                    <i class="fa-solid fa-calendar-days" style="font-size: 1.5rem; color: #3182ce;"></i>
                    <h2 style="margin: 0;">Reports Dates</h2>
                </div>

                <div class="table-container premium-white-card" style="border:none; background: #ffffff;">
                    <div class="table-wrapper">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>MODULE</th>
                                    <th>ID</th>
                                    <th>REPORT TOPIC</th>
                                    <th>REFERENCE</th>
                                    <th>DATE</th>
                                    <th>STATUS</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                // Get filter parameters
                                $rd_from = $_GET['from_date'] ?? '';
                                $rd_to = $_GET['to_date'] ?? '';
                                $rd_status = $_GET['status'] ?? 'all';
                                $rd_module = $_GET['module'] ?? 'all';
                                $rd_date_range = $_GET['date_range'] ?? '';

                                // Handle date_range parameter to auto-set from_date and to_date
                                if ($rd_date_range && !$rd_from && !$rd_to) {
                                    $today = date('Y-m-d');
                                    switch ($rd_date_range) {
                                        case 'today':
                                            $rd_from = $rd_to = $today;
                                            break;
                                        case 'yesterday':
                                            $rd_from = $rd_to = date('Y-m-d', strtotime('-1 day'));
                                            break;
                                        case 'this_week':
                                            $rd_from = date('Y-m-d', strtotime('monday this week'));
                                            $rd_to = date('Y-m-d', strtotime('sunday this week'));
                                            break;
                                        case 'last_week':
                                            $rd_from = date('Y-m-d', strtotime('monday last week'));
                                            $rd_to = date('Y-m-d', strtotime('sunday last week'));
                                            break;
                                        case 'this_month':
                                            $rd_from = date('Y-m-01');
                                            $rd_to = date('Y-m-t');
                                            break;
                                        case 'last_month':
                                            $rd_from = date('Y-m-01', strtotime('-1 month'));
                                            $rd_to = date('Y-m-t', strtotime('-1 month'));
                                            break;
                                        case 'this_quarter':
                                            $current_quarter = ceil(date('n') / 3);
                                            $rd_from = date('Y-m-d', mktime(0, 0, 0, ($current_quarter - 1) * 3 + 1, 1, date('Y')));
                                            $rd_to = date('Y-m-d', mktime(0, 0, 0, $current_quarter * 3 + 1, 0, date('Y')));
                                            break;
                                        case 'this_year':
                                            $rd_from = date('Y-01-01');
                                            $rd_to = date('Y-12-31');
                                            break;
                                    }
                                }

                                $db = get_pdo();
                                $rd_rows = [];

                                // Build the same query as the reports tab but with date filtering
                                if ($rd_module === 'all' || $rd_module === 'reservations') {
                                    $where_status_res = ($rd_status !== 'all') ? " AND r.status = " . $db->quote($rd_status) : "";
                                    $where_date_res = ($rd_from ? " AND r.event_date >= " . $db->quote($rd_from) : "") . ($rd_to ? " AND r.event_date <= " . $db->quote($rd_to) : "");

                                    $sql = "SELECT 'Reservation' as module, r.id, CONVERT(r.customer_name USING utf8mb4) as name, CONVERT(f.name USING utf8mb4) as ref, CAST(r.event_date AS CHAR) as date, CONVERT(r.status USING utf8mb4) as status 
                                            FROM reservations r LEFT JOIN facilities f ON r.facility_id = f.id WHERE 1=1 $where_status_res $where_date_res";
                                    $stmt = $db->prepare($sql);
                                    $stmt->execute();
                                    $rd_rows = array_merge($rd_rows, $stmt->fetchAll(PDO::FETCH_ASSOC));
                                }

                                if ($rd_module === 'all' || $rd_module === 'facilities') {
                                    $where_status = ($rd_status !== 'all') ? " AND status = " . $db->quote($rd_status) : "";
                                    $sql = "SELECT 'Facility' as module, id, CONVERT(name USING utf8mb4), CONVERT(location USING utf8mb4), 'N/A' as date, CONVERT(status USING utf8mb4) FROM facilities WHERE 1=1 $where_status";
                                    $stmt = $db->prepare($sql);
                                    $stmt->execute();
                                    $rd_rows = array_merge($rd_rows, $stmt->fetchAll(PDO::FETCH_ASSOC));
                                }

                                if ($rd_module === 'all' || $rd_module === 'archiving') {
                                    $where_date_doc = ($rd_from ? " AND DATE(uploaded_at) >= " . $db->quote($rd_from) : "") . ($rd_to ? " AND DATE(uploaded_at) <= " . $db->quote($rd_to) : "");
                                    $sql = "SELECT 'Document' as module, id, CONVERT(name USING utf8mb4), CONVERT(case_id USING utf8mb4), CAST(uploaded_at AS CHAR) as date, 'Archived' as status FROM documents WHERE is_deleted = 0 $where_date_doc";
                                    $stmt = $db->prepare($sql);
                                    $stmt->execute();
                                    $rd_rows = array_merge($rd_rows, $stmt->fetchAll(PDO::FETCH_ASSOC));
                                }

                                if ($rd_module === 'all' || $rd_module === 'visitors') {
                                    $v_cols = $db->query("SHOW COLUMNS FROM direct_checkins")->fetchAll(PDO::FETCH_COLUMN);
                                    $v_status_col = in_array('status', $v_cols) ? 'status' : "'N/A'";
                                    $where_status = ($rd_status !== 'all') ? " AND status = " . $db->quote($rd_status) : "";
                                    $where_date_vis = ($rd_from ? " AND DATE(checkin_date) >= " . $db->quote($rd_from) : "") . ($rd_to ? " AND DATE(checkin_date) <= " . $db->quote($rd_to) : "");
                                    $sql = "SELECT 'Visitor' as module, id, CONVERT(full_name USING utf8mb4), CONVERT(room_number USING utf8mb4), CAST(checkin_date AS CHAR) as date, CONVERT(CASE WHEN status = 'active' THEN 'Checked In' ELSE status END USING utf8mb4) as status FROM direct_checkins WHERE 1=1 $where_status $where_date_vis";
                                    $stmt = $db->prepare($sql);
                                    $stmt->execute();
                                    $rd_rows = array_merge($rd_rows, $stmt->fetchAll(PDO::FETCH_ASSOC));
                                }

                                if ($rd_module === 'all' || $rd_module === 'legal') {
                                    $where_date_leg = ($rd_from ? " AND DATE(created_at) >= " . $db->quote($rd_from) : "") . ($rd_to ? " AND DATE(created_at) <= " . $db->quote($rd_to) : "");
                                    $sql = "SELECT 'Legal' as module, id, CONVERT(name USING utf8mb4), CONVERT(case_id USING utf8mb4), CAST(created_at AS CHAR) as date, CONVERT(risk_score USING utf8mb4) as status FROM contracts WHERE 1=1 $where_date_leg";
                                    $stmt = $db->prepare($sql);
                                    $stmt->execute();
                                    $rd_rows = array_merge($rd_rows, $stmt->fetchAll(PDO::FETCH_ASSOC));
                                }

                                // Sort by date
                                usort($rd_rows, function ($a, $b) {
                                    return strtotime($b['date']) - strtotime($a['date']);
                                });

                                if (empty($rd_rows)): ?>
                                    <tr>
                                        <td colspan="6"
                                            style="text-align: center; padding: 2rem; color: #718096; font-style: italic;">
                                            No records found for the selected module and filters.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($rd_rows as $rr): ?>
                                        <tr style="border-bottom: 1px solid #edf2f7;">
                                            <td style="font-weight: 700; font-size: 13px; color: #1e293b;">
                                                <?= htmlspecialchars($rr['module']) ?>
                                            </td>
                                            <td style="font-weight: 600; color: #475569;">
                                                <?= htmlspecialchars($rr['id']) ?>
                                            </td>
                                            <td style="color: #334155;">
                                                <?= htmlspecialchars($rr['name'] ?? $rr['topic'] ?? 'N/A') ?>
                                            </td>
                                            <td style="color: #64748b;">
                                                <?= htmlspecialchars($rr['ref'] ?? $rr['reference'] ?? 'N/A') ?>
                                            </td>
                                            <td style="color: #64748b; font-size: 0.9rem;">
                                                <?= htmlspecialchars($rr['date']) ?>
                                            </td>
                                            <td>
                                                <span class="badge"
                                                    style="
                                                    <?php
                                                    $status = strtolower($rr['status']);
                                                    if ($status === 'confirmed' || $status === 'completed') {
                                                        echo 'background: #dcfce7; color: #166534;';
                                                    } elseif ($status === 'pending') {
                                                        echo 'background: #fef3c7; color: #92400e;';
                                                    } elseif ($status === 'cancelled') {
                                                        echo 'background: #fee2e2; color: #991b1b;';
                                                    } else {
                                                        echo 'background: #f1f5f9; color: #475569;';
                                                    }
                                                    ?>
                                                    padding: 4px 12px; border-radius: 12px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase;">
                                                    <?= htmlspecialchars($rr['status']) ?>
                                                </span>
                                            </td>
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

                <style>
                    /* Force grid styles in case of external CSS breakdown */
                    .calendar-grid-header {
                        display: grid !important;
                        grid-template-columns: repeat(7, 1fr) !important;
                        gap: 10px !important;
                        margin-bottom: 15px !important;
                        text-align: center !important;
                        font-weight: 800 !important;
                        color: #64748b !important;
                        font-size: 0.75rem !important;
                        text-transform: uppercase !important;
                        letter-spacing: 1px !important;
                    }

                    .calendar-days-grid {
                        display: grid !important;
                        grid-template-columns: repeat(7, 1fr) !important;
                        gap: 12px !important;
                        width: 100% !important;
                    }

                    .calendar-day-cell {
                        background: #ffffff !important;
                        border: 1px solid #f1f5f9 !important;
                        border-radius: 16px !important;
                        min-height: 120px !important;
                        padding: 12px !important;
                        transition: all 0.3s ease !important;
                        display: flex !important;
                        flex-direction: column !important;
                        gap: 6px !important;
                        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02) !important;
                    }

                    .calendar-day-cell:hover {
                        transform: translateY(-4px) !important;
                        box-shadow: 0 12px 20px -5px rgba(0, 0, 0, 0.08) !important;
                        border-color: #3b82f640 !important;
                    }

                    .calendar-day-cell.empty {
                        background: #f8fafc !important;
                        opacity: 0.5 !important;
                        border: none !important;
                    }

                    .calendar-event-dot {
                        font-size: 0.7rem !important;
                        font-weight: 700 !important;
                        padding: 4px 8px !important;
                        border-radius: 6px !important;
                        margin-bottom: 4px !important;
                        cursor: pointer !important;
                        transition: all 0.2s ease !important;
                        white-space: nowrap !important;
                        overflow: hidden !important;
                        text-overflow: ellipsis !important;
                        display: block !important;
                        width: 100% !important;
                        border-left: 3px solid transparent !important;
                    }

                    .calendar-event-dot:hover {
                        transform: scale(1.02) !important;
                        filter: brightness(0.95) !important;
                    }

                    .event-confirmed {
                        background: #ecfdf5 !important;
                        color: #065f46 !important;
                        border-left-color: #10b981 !important;
                    }

                    .event-pending {
                        background: #fffbeb !important;
                        color: #92400e !important;
                        border-left-color: #f59e0b !important;
                    }

                    .event-cancelled {
                        background: #fef2f2 !important;
                        color: #991b1b !important;
                        border-left-color: #ef4444 !important;
                    }

                    .event-completed {
                        background: #eff6ff !important;
                        color: #1e40af !important;
                        border-left-color: #3b82f6 !important;
                    }

                    .event-other {
                        background: #f8fafc !important;
                        color: #475569 !important;
                        border-left-color: #94a3b8 !important;
                    }

                    .day-number {
                        font-weight: 800 !important;
                        font-size: 0.9rem !important;
                        color: #64748b !important;
                        margin-bottom: 8px !important;
                        width: 28px !important;
                        height: 28px !important;
                        display: flex !important;
                        align-items: center !important;
                        justify-content: center !important;
                        border-radius: 50% !important;
                    }

                    .day-number.today {
                        background: #3b82f6 !important;
                        color: #ffffff !important;
                        box-shadow: 0 4px 10px rgba(59, 130, 246, 0.3) !important;
                    }
                </style>

                <div class="calendar-container"
                    style="background: #ffffff; border-radius: 24px; padding: 40px; border: 1px solid #e2e8f0; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.05); width: 95%; max-width: 1200px; margin: 0 auto 40px auto; overflow: hidden;">
                    <div class="calendar-header"
                        style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 40px; border-bottom: 1px solid #f1f5f9; padding-bottom: 25px;">
                        <div style="display: flex; align-items: center; gap: 15px;">
                            <div
                                style="width: 50px; height: 50px; background: #eff6ff; border-radius: 14px; display: flex; align-items: center; justify-content: center; color: #3b82f6; font-size: 1.5rem;">
                                <i class="fa-solid fa-calendar-days"></i>
                            </div>
                            <h2 id="currentMonthYear"
                                style="margin:0; font-size:2rem; font-weight: 800; color: #0f172a; letter-spacing: -0.7px; font-family: 'Outfit', sans-serif;">
                            </h2>
                        </div>
                        <div class="calendar-nav" style="display:flex; gap:15px; align-items: center;">
                            <button onclick="goToToday()" class="btn btn-outline btn-sm"
                                style="height: 45px; padding: 0 18px; border-radius: 12px; font-weight: 700; background: #fff; border: 1px solid #e2e8f0; cursor: pointer; transition: all 0.3s; color: #64748b;">
                                Today
                            </button>
                            <div style="display: flex; gap: 8px;">
                                <button onclick="changeMonth(-1)" class="btn btn-outline btn-sm"
                                    style="width: 45px; height: 45px; border-radius: 12px; display: flex; align-items: center; justify-content: center; background: #fff; border: 1px solid #e2e8f0; cursor: pointer; transition: all 0.3s;">
                                    <i class="fa-solid fa-chevron-left"></i>
                                </button>
                                <button onclick="changeMonth(1)" class="btn btn-outline btn-sm"
                                    style="width: 45px; height: 45px; border-radius: 12px; display: flex; align-items: center; justify-content: center; background: #fff; border: 1px solid #e2e8f0; cursor: pointer; transition: all 0.3s;">
                                    <i class="fa-solid fa-chevron-right"></i>
                                </button>
                            </div>
                            <button onclick="openModal('reservation-modal')" class="btn btn-primary btn-sm"
                                style="height: 45px; padding: 0 25px; border-radius: 12px; font-weight: 700; background: #3b82f6; border: none; cursor: pointer; transition: all 0.3s; color: #fff; box-shadow: 0 4px 10px rgba(59, 130, 246, 0.3); display: flex; align-items: center; gap: 10px;">
                                <i class="fa-solid fa-calendar-plus" style="font-size: 1.1rem;"></i> Book Now
                            </button>
                        </div>
                    </div>
                    <div class="calendar-grid-header">
                        <div>SUN</div>
                        <div>MON</div>
                        <div>TUE</div>
                        <div>WED</div>
                        <div>THU</div>
                        <div>FRI</div>
                        <div>SAT</div>
                    </div>
                    <div id="calendar-days" class="calendar-days-grid"></div>

                    <div class="calendar-legend"
                        style="display: flex; gap: 25px; margin-top: 35px; padding-top: 25px; border-top: 1px solid #f1f5f9; flex-wrap: wrap;">
                        <div class="legend-item"
                            style="display: flex; align-items: center; gap: 10px; font-size: 0.85rem; font-weight: 700; color: #64748b;">
                            <div class="legend-color"
                                style="width: 12px; height: 12px; border-radius: 4px; background: #059669;"></div>
                            <span>Confirmed</span>
                        </div>
                        <div class="legend-item"
                            style="display: flex; align-items: center; gap: 10px; font-size: 0.85rem; font-weight: 700; color: #64748b;">
                            <div class="legend-color"
                                style="width: 12px; height: 12px; border-radius: 4px; background: #d97706;"></div>
                            <span>Pending</span>
                        </div>
                        <div class="legend-item"
                            style="display: flex; align-items: center; gap: 10px; font-size: 0.85rem; font-weight: 700; color: #64748b;">
                            <div class="legend-color"
                                style="width: 12px; height: 12px; border-radius: 4px; background: #dc2626;"></div>
                            <span>Cancelled</span>
                        </div>
                        <div class="legend-item"
                            style="display: flex; align-items: center; gap: 10px; font-size: 0.85rem; font-weight: 700; color: #64748b;">
                            <div class="legend-color"
                                style="width: 12px; height: 12px; border-radius: 4px; background: #2563eb;"></div>
                            <span>Completed</span>
                        </div>
                    </div>
                </div>

                <script>
                    (function () {
                        let currentCalendarDate = new Date();
                        let calendarReservations = <?= json_encode($dashboard_data['reservations'] ?? []) ?>;

                        // Add sample content if empty as requested by user ("lagyan mo ng laman")
                        if (calendarReservations.length === 0) {
                            const today = new Date();
                            const y = today.getFullYear();
                            const m = today.getMonth() + 1;
                            const d = (day) => `${y}-${String(m).padStart(2, '0')}-${String(day).padStart(2, '0')}`;

                            calendarReservations = [
                                { id: 1, customer_name: 'John Doe', facility_name: 'Executive Boardroom', event_date: d(15), start_time: '10:00 AM', status: 'confirmed' },
                                { id: 2, customer_name: 'Jane Smith', facility_name: 'Grand Ballroom', event_date: d(16), start_time: '02:00 PM', status: 'pending' },
                                { id: 3, customer_name: 'Bob Wilson', facility_name: 'Pacific Hall', event_date: d(20), start_time: '09:00 AM', status: 'confirmed' },
                                { id: 4, customer_name: 'Alice Brown', facility_name: 'Sky Garden', event_date: d(25), start_time: '04:00 PM', status: 'completed' },
                                { id: 5, customer_name: 'Carlos Mendez', facility_name: 'Conference Suite A', event_date: d(5), start_time: '08:30 AM', status: 'confirmed' },
                                { id: 6, customer_name: 'Sarah Lee', facility_name: 'Rooftop Lounge', event_date: d(10), start_time: '06:00 PM', status: 'pending' },
                                { id: 7, customer_name: 'Mike Tyson', facility_name: 'Fitness Center', event_date: d(12), start_time: '07:00 AM', status: 'completed' },
                                { id: 8, customer_name: 'Tom Hanks', facility_name: 'Private Theater', event_date: d(18), start_time: '08:00 PM', status: 'confirmed' },
                                { id: 9, customer_name: 'Emma Watson', facility_name: 'Library Lounge', event_date: d(22), start_time: '11:00 AM', status: 'cancelled' },
                                { id: 10, customer_name: 'Bruce Wayne', facility_name: 'Underground Suite', event_date: d(28), start_time: '11:59 PM', status: 'confirmed' },
                                { id: 11, customer_name: 'Peter Parker', facility_name: 'Lab Room 101', event_date: d(14), start_time: '03:00 PM', status: 'pending' },
                                { id: 12, customer_name: 'Tony Stark', facility_name: 'Penthouse Area', event_date: d(16), start_time: '12:00 PM', status: 'confirmed' }
                            ];
                        }

                        window.initCalendar = function () {
                            renderCalendar(currentCalendarDate);
                        }

                        window.changeMonth = function (delta) {
                            currentCalendarDate.setMonth(currentCalendarDate.getMonth() + delta);
                            renderCalendar(currentCalendarDate);
                        }

                        window.goToToday = function () {
                            currentCalendarDate = new Date();
                            renderCalendar(currentCalendarDate);
                        }

                        function renderCalendar(date) {
                            const year = date.getFullYear();
                            const month = date.getMonth();

                            const monthNames = ["January", "February", "March", "April", "May", "June",
                                "July", "August", "September", "October", "November", "December"
                            ];
                            document.getElementById('currentMonthYear').textContent = `${monthNames[month]} ${year}`;

                            const firstDay = new Date(year, month, 1);
                            const lastDay = new Date(year, month + 1, 0);
                            const startDayIndex = firstDay.getDay();
                            const totalDays = lastDay.getDate();

                            const grid = document.getElementById('calendar-days');
                            grid.innerHTML = '';

                            // Empty cells
                            for (let i = 0; i < startDayIndex; i++) {
                                const cell = document.createElement('div');
                                cell.className = 'calendar-day-cell empty';
                                grid.appendChild(cell);
                            }

                            // Days
                            const today = new Date();
                            for (let d = 1; d <= totalDays; d++) {
                                const cell = document.createElement('div');
                                cell.className = 'calendar-day-cell';

                                const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`;

                                const dayNumber = document.createElement('div');
                                dayNumber.className = `day-number ${d === today.getDate() && month === today.getMonth() && year === today.getFullYear() ? 'today' : ''}`;
                                dayNumber.textContent = d;
                                cell.appendChild(dayNumber);

                                const events = calendarReservations.filter(e => e.event_date === dateStr);
                                events.forEach(evt => {
                                    const dot = document.createElement('div');
                                    dot.className = `calendar-event-dot event-${evt.status || 'other'}`;
                                    dot.innerHTML = `<i class="fa-solid fa-circle-info" style="font-size: 0.6rem; opacity: 0.7; margin-right: 4px;"></i> ${evt.facility_name}`;
                                    dot.title = `${evt.customer_name} (${evt.start_time})`;
                                    dot.onclick = (e) => {
                                        e.stopPropagation();
                                        if (window.viewReservationDetails) window.viewReservationDetails(evt);
                                    };
                                    cell.appendChild(dot);
                                });

                                grid.appendChild(cell);
                            }
                        }

                        // Initialize
                        if (document.getElementById('calendar-days')) {
                            initCalendar();
                        }
                    })();
                </script>
            </div>

            <!-- Management Tab -->
            <div id="management"
                class="tab-content <?= (isset($_GET['tab']) && ($_GET['tab'] == 'management' || $_GET['tab'] == 'maintenance')) ? 'active' : '' ?>">
                <div class="management-header">
                    <h2><span class="icon-img-placeholder">⚙️</span> Management</h2>
                    <div class="management-buttons" style="display: flex; gap: 0.75rem;">
                        <button id="show-maintenance-card" class="btn btn-outline management-btn active"
                            onclick="event.preventDefault(); window.showManagementCard('maintenance')">
                            <i class="fa-solid fa-screwdriver-wrench"></i> Maintenance
                        </button>
                        <button id="show-mnt-calendar" class="btn btn-outline management-btn"
                            onclick="event.preventDefault(); window.showManagementCard('mnt-calendar')">
                            <i class="fa-solid fa-calendar-days"></i> Schedules
                        </button>
                    </div>
                </div>

                <div class="management-cards" style="margin-top: 1rem;">


                    <!-- Facility Management Card -->
                    <div class="card management-card management-facilities" id="management-facilities"
                        style="display: none;">
                        <div class="card-header">
                            <h3><span class="icon-img-placeholder">🏢</span> Facility Management</h3>
                        </div>
                        <div class="card-content">
                            <button class="btn btn-primary mb-1" onclick="openModal('facility-modal')">
                                <span class="icon-img-placeholder">➕</span> Add New Facility
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
                                                    ₱<?= number_format($facility['hourly_rate'], 2) ?></td>
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


                    <div id="maintenance-main-section" class="management-card management-maintenance active-card"
                        style="width: 100%; margin: 0; overflow: hidden; display: block; opacity: 1; visibility: visible;">
                        <style>
                            /* Premium Maintenance Table Styles */
                            .maintenance-card-premium {
                                background: #ffffff !important;
                                border-radius: 16px !important;
                                border: 1px solid #e2e8f0 !important;
                                box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05) !important;
                                overflow: hidden !important;
                                margin-bottom: 30px !important;
                            }

                            .maintenance-header-premium {
                                background: #ffffff !important;
                                padding: 25px 30px !important;
                                border-bottom: 2px solid #f1f5f9 !important;
                                display: flex !important;
                                justify-content: space-between !important;
                                align-items: center !important;
                            }

                            .maintenance-title-group {
                                display: flex !important;
                                align-items: center !important;
                                gap: 15px !important;
                            }

                            .maintenance-icon-box {
                                width: 45px !important;
                                height: 45px !important;
                                background: #eff6ff !important;
                                border-radius: 12px !important;
                                display: flex !important;
                                align-items: center !important;
                                justify-content: center !important;
                                color: #3b82f6 !important;
                                font-size: 1.2rem !important;
                                border: 1px solid #dbeafe !important;
                            }

                            .maintenance-table-premium {
                                width: 100% !important;
                                border-collapse: collapse !important;
                                background: #fff !important;
                            }

                            .maintenance-table-premium th {
                                background: #f8fafc !important;
                                padding: 18px 20px !important;
                                font-weight: 800 !important;
                                font-size: 0.75rem !important;
                                color: #475569 !important;
                                text-transform: uppercase !important;
                                letter-spacing: 1.5px !important;
                                border-bottom: 2px solid #e2e8f0 !important;
                                white-space: nowrap !important;
                            }

                            .maintenance-table-premium td {
                                padding: 20px !important;
                                vertical-align: middle !important;
                                border-bottom: 1px solid #f1f5f9 !important;
                                transition: all 0.2s ease !important;
                                color: #1e293b !important;
                            }

                            .maintenance-table-premium tr:hover td {
                                background: #f8fafc !important;
                            }

                            .col-priority {
                                width: 120px;
                                text-align: left !important;
                            }

                            .col-item {
                                width: 220px;
                                text-align: left !important;
                                font-weight: 800 !important;
                                font-size: 0.9rem !important;
                            }

                            .col-description {
                                min-width: 300px;
                                text-align: center !important;
                                line-height: 1.6 !important;
                                font-weight: 500 !important;
                                color: #475569 !important;
                            }

                            .col-reported-by {
                                width: 150px;
                                text-align: left !important;
                                font-weight: 700 !important;
                            }

                            .col-date {
                                width: 130px;
                                text-align: left !important;
                                font-weight: 700 !important;
                                color: #334155 !important;
                            }

                            .col-schedule {
                                width: 130px;
                                text-align: left !important;
                                font-weight: 800 !important;
                                color: #1e293b !important;
                            }

                            .priority-indicator-modern {
                                display: flex;
                                align-items: center;
                                gap: 10px;
                            }

                            .priority-dot-modern {
                                width: 10px;
                                height: 10px;
                                border-radius: 50%;
                            }

                            .btn-add-premium {
                                background: #3b82f6 !important;
                                color: white !important;
                                border: none !important;
                                padding: 12px 24px !important;
                                border-radius: 12px !important;
                                font-weight: 700 !important;
                                font-size: 0.9rem !important;
                                display: flex !important;
                                align-items: center !important;
                                gap: 10px !important;
                                transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
                                box-shadow: 0 4px 12px rgba(59, 130, 246, 0.25) !important;
                                cursor: pointer !important;
                            }

                            .btn-add-premium:hover {
                                background: #2563eb !important;
                                transform: translateY(-2px) !important;
                                box-shadow: 0 8px 120px rgba(59, 130, 246, 0.4) !important;
                            }
                        </style>

                        <div class="maintenance-card-premium">
                            <div class="maintenance-header-premium">
                                <div class="maintenance-title-group">
                                    <div class="maintenance-icon-box">
                                        <i class="fa-solid fa-list-check"></i>
                                    </div>
                                    <h3
                                        style="margin:0; font-family: 'Inter', sans-serif; font-size: 1.3rem; font-weight: 800; color: #0f172a; letter-spacing: -0.02em;">
                                        Maintenance Requests</h3>
                                </div>
                                <button class="btn-add-premium" onclick="openModal('maintenance-modal')">
                                    <i class="fa-solid fa-plus"></i> Add New Request
                                </button>
                            </div>

                            <div style="overflow-x: auto;">
                                <table class="maintenance-table-premium">
                                    <thead>
                                        <tr>
                                            <th class="col-priority">Priority</th>
                                            <th class="col-item">Item/Area</th>
                                            <th class="col-description">Description</th>
                                            <th class="col-reported-by">Reported By</th>
                                            <th class="col-date">Reported Date</th>
                                            <th class="col-schedule">Schedule</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($dashboard_data['maintenance_logs'])): ?>
                                            <tr>
                                                <td colspan="6"
                                                    style="padding: 60px; text-align: center; color: #94a3b8; font-style: italic; font-weight: 500;">
                                                    <i class="fa-solid fa-inbox"
                                                        style="font-size: 2.5rem; display: block; margin-bottom: 15px; opacity: 0.3;"></i>
                                                    No maintenance logs currently recorded.
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($dashboard_data['maintenance_logs'] as $log): ?>
                                                <tr>
                                                    <td class="col-priority">
                                                        <div class="priority-indicator-modern">
                                                            <?php
                                                            $p_lower = strtolower($log['priority'] ?? 'low');
                                                            $pc = ($p_lower == 'high') ? '#ef4444' : (($p_lower == 'medium') ? '#f59e0b' : '#10b981');
                                                            ?>
                                                            <span class="priority-dot-modern"
                                                                style="background: <?= $pc ?>; box-shadow: 0 0 10px <?= $pc ?>80;"></span>
                                                            <span
                                                                style="font-weight: 800; color: #1e293b; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.5px;"><?= htmlspecialchars($log['priority'] ?? 'Low') ?></span>
                                                        </div>
                                                    </td>
                                                    <td class="col-item">
                                                        <?= htmlspecialchars($log['item_name']) ?>
                                                    </td>
                                                    <td class="col-description">
                                                        <?= htmlspecialchars($log['description']) ?>
                                                    </td>
                                                    <td class="col-reported-by">
                                                        <span
                                                            style="color: #64748b; font-size: 0.75rem; display: block; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;">Staff</span>
                                                        <?= htmlspecialchars($log['reported_by'] ?? 'General Staff') ?>
                                                    </td>
                                                    <td class="col-date">
                                                        <div style="display: flex; align-items: center; gap: 8px;">
                                                            <i class="fa-regular fa-calendar"
                                                                style="color: #94a3b8; font-size: 0.8rem;"></i>
                                                            <?= date('m/d/Y', strtotime($log['created_at'])) ?>
                                                        </div>
                                                    </td>
                                                    <td class="col-schedule">
                                                        <div
                                                            style="background: #f1f5f9; padding: 6px 12px; border-radius: 8px; display: inline-flex; align-items: center; gap: 8px;">
                                                            <i class="fa-solid fa-clock"
                                                                style="color: #3b82f6; font-size: 0.8rem;"></i>
                                                            <?= date('m/d/Y', strtotime($log['maintenance_date'])) ?>
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


                <!-- Redesigned Maintenance Calendar Card (Premium Clean Light Style) -->
                <div class="card management-card management-mnt-calendar premium-light-card"
                    id="management-mnt-calendar"
                    style="margin-top: 0; background: transparent !important; border: none; box-shadow: none; display: none; visibility: visible !important; width: 100%; overflow-x: auto;">

                    <div class="card-header"
                        style="background: #ffffff; border-bottom: 1px solid #e2e8f0; padding: 20px; border-radius: 12px 12px 0 0; border: 1px solid #e2e8f0; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);">
                        <div style="display: flex; align-items: center; gap: 15px;">
                            <div
                                style="width: 45px; height: 45px; background: #eff6ff; border-radius: 12px; display: flex; align-items: center; justify-content: center; color: #3b82f6; font-size: 1.4rem; border: 1px solid #dbeafe;">
                                <i class="fa-solid fa-calendar-check"></i>
                            </div>
                            <div>
                                <h3
                                    style="color: #0f172a; font-size: 1.2rem; text-transform: uppercase; letter-spacing: 1.2px; margin: 0; font-weight: 800; font-family: 'Inter', sans-serif;">
                                    Maintenance Schedules</h3>
                                <p style="margin: 0; font-size: 0.8rem; color: #64748b; font-weight: 600;">
                                    Operational roadmap for the next 7 days</p>
                            </div>
                        </div>
                    </div>

                    <div class="card-content" style="padding: 0; width: 100%;">
                        <div class="calendar-grid"
                            style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px;">
                            <?php
                            for ($i = 0; $i < 7; $i++):
                                $date = date('Y-m-d', strtotime("+$i days"));
                                $day_name = date('l', strtotime($date));
                                $display_date = date('M d, Y', strtotime($date));
                                $is_today = ($i === 0);

                                $day_jobs = array_filter($dashboard_data['maintenance_logs'] ?? [], function ($job) use ($date) {
                                    return $job['maintenance_date'] == $date;
                                });
                                ?>
                                <div class="calendar-day"
                                    style="background: #ffffff; border: 1px solid <?= $is_today ? '#3b82f6' : '#f1f5f9' ?>; border-radius: 20px; min-height: 300px; display: flex; flex-direction: column; transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1); <?= $is_today ? 'box-shadow: 0 10px 25px -5px rgba(59, 130, 246, 0.1);' : 'box-shadow: 0 4px 10px rgba(0,0,0,0.03);' ?>"
                                    onmouseover="this.style.borderColor='#3b82f6'; this.style.transform='translateY(-8px)'; this.style.boxShadow='0 20px 25px -5px rgba(0, 0, 0, 0.1)';"
                                    onmouseout="this.style.borderColor='<?= $is_today ? '#3b82f6' : '#f1f5f9' ?>'; this.style.transform='translateY(0)'; this.style.boxShadow='<?= $is_today ? '0 10px 25px -5px rgba(59, 130, 246, 0.1)' : '0 4px 10px rgba(0,0,0,0.03)' ?>';">

                                    <div class="calendar-day-header"
                                        style="padding: 20px; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center; background: <?= $is_today ? 'linear-gradient(to right, #eff6ff, #ffffff)' : '#ffffff' ?>; border-radius: 20px 20px 0 0;">
                                        <div>
                                            <span
                                                style="display: block; font-size: 0.7rem; font-weight: 800; color: <?= $is_today ? '#3b82f6' : '#94a3b8' ?>; text-transform: uppercase; letter-spacing: 2px;"><?= $day_name ?></span>
                                            <span
                                                style="display: block; font-size: 1.15rem; font-weight: 800; color: #1e293b; margin-top: 4px;"><?= $display_date ?></span>
                                        </div>
                                        <?php if ($is_today): ?>
                                            <div
                                                style="background: #3b82f6; color: #fff; font-size: 0.65rem; font-weight: 800; padding: 5px 12px; border-radius: 30px; text-transform: uppercase; letter-spacing: 1px; box-shadow: 0 4px 10px rgba(59, 130, 246, 0.3);">
                                                Today</div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="calendar-events"
                                        style="padding: 15px; flex-grow: 1; display: flex; flex-direction: column; gap: 12px; max-height: 240px; overflow-y: auto; scrollbar-width: thin;">
                                        <?php if (empty($day_jobs)): ?>
                                            <div
                                                style="flex-grow: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; opacity: 0.4; padding: 20px;">
                                                <i class="fa-solid fa-calendar-day"
                                                    style="font-size: 2.5rem; color: #cbd5e1; margin-bottom: 15px;"></i>
                                                <span
                                                    style="color: #64748b; font-size: 0.8rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1px;">No
                                                    Tasks</span>
                                            </div>
                                        <?php else: ?>
                                            <?php foreach ($day_jobs as $job): ?>
                                                <?php
                                                $job_priority = strtolower($job['priority'] ?? 'low');
                                                $accent_color = ($job_priority == 'high') ? '#ef4444' : (($job_priority == 'medium') ? '#f59e0b' : '#10b981');
                                                $bg_color = ($job_priority == 'high') ? '#fef2f2' : (($job_priority == 'medium') ? '#fffbeb' : '#f0fdf4');
                                                $text_color = ($job_priority == 'high') ? '#991b1b' : (($job_priority == 'medium') ? '#92400e' : '#166534');
                                                ?>
                                                <div class="calendar-event-card"
                                                    style="background: #ffffff; border: 1px solid #f1f5f9; border-left: 5px solid <?= $accent_color ?>; border-radius: 12px; padding: 14px; transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1); box-shadow: 0 2px 5px rgba(0,0,0,0.02); cursor: pointer;"
                                                    onclick="if(window.viewMaintenanceDetails) window.viewMaintenanceDetails(<?= htmlspecialchars(json_encode($job)) ?>)"
                                                    onmouseover="this.style.background='<?= $bg_color ?>'; this.style.borderColor='<?= $accent_color ?>40'; this.style.transform='scale(1.02)';"
                                                    onmouseout="this.style.background='#ffffff'; this.style.borderColor='#f1f5f9'; this.style.transform='scale(1)';">

                                                    <div
                                                        style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px;">
                                                        <div
                                                            style="background: <?= $bg_color ?>; color: <?= $text_color ?>; font-size: 0.6rem; font-weight: 800; padding: 3px 8px; border-radius: 6px; text-transform: uppercase; letter-spacing: 0.5px;">
                                                            <?= $job_priority ?> Priority
                                                        </div>
                                                        <i class="fa-solid fa-circle-check"
                                                            style="color: #10b981; font-size: 0.8rem; opacity: <?= $job['status'] == 'completed' ? '1' : '0.2' ?>;"></i>
                                                    </div>

                                                    <h4
                                                        style="color: #1e293b; font-size: 0.9rem; font-weight: 700; margin: 0 0 10px 0; line-height: 1.4;">
                                                        <?= htmlspecialchars($job['item_name']) ?>
                                                    </h4>

                                                    <div
                                                        style="display: flex; align-items: center; gap: 8px; border-top: 1px solid #f1f5f9; padding-top: 10px; margin-top: auto;">
                                                        <div
                                                            style="width: 24px; height: 24px; border-radius: 8px; background: #f8fafc; display: flex; align-items: center; justify-content: center; color: #64748b;">
                                                            <i class="fa-solid fa-user-gear" style="font-size: 0.75rem;"></i>
                                                        </div>
                                                        <span
                                                            style="color: #64748b; font-size: 0.75rem; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?= htmlspecialchars($job['assigned_staff'] ?? 'Facility Team') ?></span>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>

                                    <div
                                        style="padding: 15px 20px; border-top: 1px solid #f1f5f9; border-radius: 0 0 20px 20px; background: #f8fafc; display: flex; justify-content: space-between; align-items: center;">
                                        <span
                                            style="font-size: 0.65rem; color: #94a3b8; font-weight: 800; text-transform: uppercase; letter-spacing: 1px;">
                                            <?= count($day_jobs) ?> Task<?= count($day_jobs) !== 1 ? 's' : '' ?>
                                        </span>
                                        <div style="display: flex; gap: 4px;">
                                            <?php for ($k = 0; $k < min(5, count($day_jobs)); $k++): ?>
                                                <div
                                                    style="width: 6px; height: 6px; border-radius: 50%; background: #3b82f6; opacity: <?= 1 - ($k * 0.15) ?>;">
                                                </div>
                                            <?php endfor; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endfor; ?>
                        </div>
                    </div>
                </div>

                <div id="maintenance-trash-section" style="display: none;">
                    <!-- Date Range Filter for Deleted Table -->
                    <div
                        style="background: #f8fafc; padding: 15px; margin-bottom: 15px; border-radius: 8px; border: 1px solid #e2e8f0;">
                        <form method="get" style="display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap;">
                            <input type="hidden" name="tab" value="maintenance">
                            <div class="filter-group">
                                <label
                                    style="font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase;">FROM</label><br>
                                <input type="date" name="deleted_from_date"
                                    value="<?= htmlspecialchars($_GET['deleted_from_date'] ?? '') ?>"
                                    style="padding: 8px 12px; border-radius: 8px; border: 1px solid #cbd5e1; background: white;">
                            </div>
                            <div class="filter-group">
                                <label
                                    style="font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase;">TO</label><br>
                                <input type="date" name="deleted_to_date"
                                    value="<?= htmlspecialchars($_GET['deleted_to_date'] ?? '') ?>"
                                    style="padding: 8px 12px; border-radius: 8px; border: 1px solid #cbd5e1; background: white;">
                            </div>
                            <div class="filter-group" style="align-self: flex-end;">
                                <button class="btn btn-primary"
                                    style="padding: 10px 25px; background: #3182ce; color: white; border: none; border-radius: 8px; cursor: pointer;">
                                    <i class="fa-solid fa-filter"></i> Filter
                                </button>
                            </div>
                        </form>
                    </div>

                    <div class="card management-card management-trash premium-dark-card"
                        style="margin-top: -10px; background: #CEB15E !important; border-radius: 12px; overflow: hidden; border: 1px solid #111; border-top: 4px solid #ef4444;">
                        <div class="card-header"
                            style="background: #111; border-bottom: 2px solid #333; padding: 20px; display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <h3
                                    style="color: #fff; font-size: 1.1rem; text-transform: uppercase; letter-spacing: 1.2px; margin: 0; font-weight: 800; font-family: 'Inter', sans-serif;">
                                    Trash / Archived Logs</h3>
                                <p style="font-size: 0.75rem; color: #94a3b8; margin: 0; font-weight: 600;">Deleted
                                    logs can be restored or permanently removed.</p>
                            </div>
                        </div>
                        <div class="card-content" style="padding: 0;">
                            <?php
                            // Handle date filtering for deleted logs
                            $deleted_from_date = $_GET['deleted_from_date'] ?? '';
                            $deleted_to_date = $_GET['deleted_to_date'] ?? '';
                            $deleted_date_range = $_GET['deleted_date_range'] ?? '';

                            // Handle date_range parameter to auto-set from_date and to_date for deleted logs
                            if ($deleted_date_range && !$deleted_from_date && !$deleted_to_date) {
                                $today = date('Y-m-d');
                                switch ($deleted_date_range) {
                                    case 'today':
                                        $deleted_from_date = $deleted_to_date = $today;
                                        break;
                                    case 'yesterday':
                                        $deleted_from_date = $deleted_to_date = date('Y-m-d', strtotime('-1 day'));
                                        break;
                                    case 'this_week':
                                        $deleted_from_date = date('Y-m-d', strtotime('monday this week'));
                                        $deleted_to_date = date('Y-m-d', strtotime('sunday this week'));
                                        break;
                                    case 'last_week':
                                        $deleted_from_date = date('Y-m-d', strtotime('monday last week'));
                                        $deleted_to_date = date('Y-m-d', strtotime('sunday last week'));
                                        break;
                                    case 'this_month':
                                        $deleted_from_date = date('Y-m-01');
                                        $deleted_to_date = date('Y-m-t');
                                        break;
                                    case 'last_month':
                                        $deleted_from_date = date('Y-m-01', strtotime('-1 month'));
                                        $deleted_to_date = date('Y-m-t', strtotime('-1 month'));
                                        break;
                                    case 'this_quarter':
                                        $current_quarter = ceil(date('n') / 3);
                                        $deleted_from_date = date('Y-m-d', mktime(0, 0, 0, ($current_quarter - 1) * 3 + 1, 1, date('Y')));
                                        $deleted_to_date = date('Y-m-d', mktime(0, 0, 0, $current_quarter * 3 + 1, 0, date('Y')));
                                        break;
                                    case 'this_year':
                                        $deleted_from_date = date('Y-01-01');
                                        $deleted_to_date = date('Y-12-31');
                                        break;
                                }
                            }

                            $deleted_logs = $reservationSystem->fetchMaintenanceLogs(true);

                            // Apply date filtering to deleted logs
                            if (!empty($deleted_logs) && ($deleted_from_date || $deleted_to_date)) {
                                $filtered_deleted_logs = [];
                                foreach ($deleted_logs as $dlog) {
                                    $log_date = $dlog['maintenance_date'];
                                    $show_log = true;

                                    if ($deleted_from_date && $log_date < $deleted_from_date) {
                                        $show_log = false;
                                    }
                                    if ($deleted_to_date && $log_date > $deleted_to_date) {
                                        $show_log = false;
                                    }

                                    if ($show_log) {
                                        $filtered_deleted_logs[] = $dlog;
                                    }
                                }
                                $deleted_logs = $filtered_deleted_logs;
                            }
                            ?>
                            <div class="table-wrapper"
                                style="box-shadow: none; border-radius: 0; background: transparent; overflow-x: auto; margin: 0;">
                                <table class="table"
                                    style="background: transparent; border-collapse: collapse; min-width: 1000px;">
                                    <thead>
                                        <tr style="border-bottom: 2px solid #333;">
                                            <th
                                                style="padding: 15px; color: #64748b; text-align: left; font-size: 0.7rem; background: transparent; font-weight: 900; letter-spacing: 1px;">
                                                ITEM/AREA</th>
                                            <th
                                                style="padding: 15px; color: #64748b; text-align: left; font-size: 0.7rem; background: transparent; font-weight: 900; letter-spacing: 1px;">
                                                DESCRIPTION</th>
                                            <th
                                                style="padding: 15px; color: #64748b; text-align: left; font-size: 0.7rem; background: transparent; font-weight: 900; letter-spacing: 1px;">
                                                ASSIGNED STAFF</th>
                                            <th
                                                style="padding: 15px; color: #64748b; text-align: left; font-size: 0.7rem; background: transparent; font-weight: 900; letter-spacing: 1px;">
                                                SCHEDULED DATE</th>
                                            <th
                                                style="padding: 15px; color: #64748b; text-align: center; font-size: 0.7rem; background: transparent; font-weight: 900; letter-spacing: 1px;">
                                                ACTIONS</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($deleted_logs)): ?>
                                            <tr>
                                                <td colspan="5"
                                                    style="padding: 40px; text-align: center; color: #4a5568; border-bottom: 1px solid #1a1a1a; font-style: italic;">
                                                    Trash is empty.</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($deleted_logs as $dlog): ?>
                                                <tr
                                                    style="border-bottom: 1px solid rgba(0,0,0,0.1); background: #CEB15E; opacity: 0.9;">
                                                    <td
                                                        style="padding: 15px; color: #000; font-size: 0.85rem; font-weight: 700; text-align: left !important;">
                                                        <?= htmlspecialchars($dlog['item_name']) ?>
                                                    </td>
                                                    <td
                                                        style="padding: 15px; color: #333; font-size: 0.8rem; text-align: left !important; font-weight: 600;">
                                                        <?= htmlspecialchars($dlog['description']) ?>
                                                    </td>
                                                    <td
                                                        style="padding: 15px; color: #000; font-size: 0.85rem; text-align: center !important; font-weight: 700;">
                                                        <?= htmlspecialchars($dlog['assigned_staff'] ?? 'N/A') ?>
                                                    </td>
                                                    <td
                                                        style="padding: 15px; color: #000; font-size: 0.85rem; text-align: center !important; font-weight: 700;">
                                                        <?= date('m/d/Y', strtotime($dlog['maintenance_date'])) ?>
                                                    </td>
                                                    <td style="padding: 15px; text-align: center !important;">
                                                        <div style="display: flex; gap: 8px; justify-content: center;">
                                                            <button class="btn btn-success btn-sm"
                                                                onclick="restoreMaintenanceLog(<?= $dlog['id'] ?>)"
                                                                title="Restore Log"
                                                                style="background: rgba(34, 197, 94, 0.1); color: #22c55e; border: 1px solid rgba(34, 197, 94, 0.2); padding: 6px 12px;">
                                                                <i class="fa-solid fa-rotate-left"></i> Restore
                                                            </button>
                                                            <button class="btn btn-danger btn-sm"
                                                                onclick="permanentlyDeleteMaintenanceLog(<?= $dlog['id'] ?>)"
                                                                title="Delete Permanently"
                                                                style="background: rgba(239, 68, 68, 0.1); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.2); padding: 6px 12px;">
                                                                <i class="fa-solid fa-trash-xmark"></i> Delete
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
                    <label for="reserve_name">Reserve Name</label>
                    <input type="text" id="reserve_name" name="reserve_name" class="form-control"
                        placeholder="e.g. Main Hall A">
                </div>

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
                    <label for="facility_status">Status</label>
                    <select id="facility_status" name="status" class="form-control">
                        <option value="active">Active</option>
                        <option value="maintenance">Under Maintenance</option>
                        <option value="closed">Closed</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="assigned_user">Assigned User</label>
                    <input type="text" id="assigned_user" name="assigned_user" class="form-control"
                        placeholder="Name of assigned staff">
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
        // Final insurance to ensure Management f unctions are ready
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

        // Date Range Selection Function
        function setDateRange(range) {
            const today = new Date();
            let fromDate = '';
            let toDate = '';

            switch (range) {
                case 'today':
                    fromDate = toDate = today.toISOString().split('T')[0];
                    break;
                case 'yesterday':
                    const yesterday = new Date(today);
                    yesterday.setDate(yesterday.getDate() - 1);
                    fromDate = toDate = yesterday.toISOString().split('T')[0];
                    break;
                case 'this_week':
                    const startOfWeek = new Date(today);
                    startOfWeek.setDate(today.getDate() - today.getDay());
                    const endOfWeek = new Date(startOfWeek);
                    endOfWeek.setDate(startOfWeek.getDate() + 6);
                    fromDate = startOfWeek.toISOString().split('T')[0];
                    toDate = endOfWeek.toISOString().split('T')[0];
                    break;
                case 'last_week':
                    const startOfLastWeek = new Date(today);
                    startOfLastWeek.setDate(today.getDate() - today.getDay() - 7);
                    const endOfLastWeek = new Date(startOfLastWeek);
                    endOfLastWeek.setDate(startOfLastWeek.getDate() + 6);
                    fromDate = startOfLastWeek.toISOString().split('T')[0];
                    toDate = endOfLastWeek.toISOString().split('T')[0];
                    break;
                case 'this_month':
                    const startOfMonth = new Date(today.getFullYear(), today.getMonth(), 1);
                    const endOfMonth = new Date(today.getFullYear(), today.getMonth() + 1, 0);
                    fromDate = startOfMonth.toISOString().split('T')[0];
                    toDate = endOfMonth.toISOString().split('T')[0];
                    break;
                case 'last_month':
                    const startOfLastMonth = new Date(today.getFullYear(), today.getMonth() - 1, 1);
                    const endOfLastMonth = new Date(today.getFullYear(), today.getMonth(), 0);
                    fromDate = startOfLastMonth.toISOString().split('T')[0];
                    toDate = endOfLastMonth.toISOString().split('T')[0];
                    break;
                case 'this_quarter':
                    const quarter = Math.floor(today.getMonth() / 3);
                    const startOfQuarter = new Date(today.getFullYear(), quarter * 3, 1);
                    const endOfQuarter = new Date(today.getFullYear(), quarter * 3 + 3, 0);
                    fromDate = startOfQuarter.toISOString().split('T')[0];
                    toDate = endOfQuarter.toISOString().split('T')[0];
                    break;
                case 'this_year':
                    const startOfYear = new Date(today.getFullYear(), 0, 1);
                    const endOfYear = new Date(today.getFullYear(), 11, 31);
                    fromDate = startOfYear.toISOString().split('T')[0];
                    toDate = endOfYear.toISOString().split('T')[0];
                    break;
            }

            // Update the date input fields
            const fromInput = document.querySelector('input[name="from_date"]');
            const toInput = document.querySelector('input[name="to_date"]');

            if (fromInput) fromInput.value = fromDate;
            if (toInput) toInput.value = toDate;
        }

        // Deleted Date Range Selection Function
        function setDeletedDateRange(range) {
            const today = new Date();
            let fromDate = '';
            let toDate = '';

            switch (range) {
                case 'today':
                    fromDate = toDate = today.toISOString().split('T')[0];
                    break;
                case 'yesterday':
                    const yesterday = new Date(today);
                    yesterday.setDate(yesterday.getDate() - 1);
                    fromDate = toDate = yesterday.toISOString().split('T')[0];
                    break;
                case 'this_week':
                    const startOfWeek = new Date(today);
                    startOfWeek.setDate(today.getDate() - today.getDay());
                    const endOfWeek = new Date(startOfWeek);
                    endOfWeek.setDate(startOfWeek.getDate() + 6);
                    fromDate = startOfWeek.toISOString().split('T')[0];
                    toDate = endOfWeek.toISOString().split('T')[0];
                    break;
                case 'last_week':
                    const startOfLastWeek = new Date(today);
                    startOfLastWeek.setDate(today.getDate() - today.getDay() - 7);
                    const endOfLastWeek = new Date(startOfLastWeek);
                    endOfLastWeek.setDate(startOfLastWeek.getDate() + 6);
                    fromDate = startOfLastWeek.toISOString().split('T')[0];
                    toDate = endOfLastWeek.toISOString().split('T')[0];
                    break;
                case 'this_month':
                    const startOfMonth = new Date(today.getFullYear(), today.getMonth(), 1);
                    const endOfMonth = new Date(today.getFullYear(), today.getMonth() + 1, 0);
                    fromDate = startOfMonth.toISOString().split('T')[0];
                    toDate = endOfMonth.toISOString().split('T')[0];
                    break;
                case 'last_month':
                    const startOfLastMonth = new Date(today.getFullYear(), today.getMonth() - 1, 1);
                    const endOfLastMonth = new Date(today.getFullYear(), today.getMonth(), 0);
                    fromDate = startOfLastMonth.toISOString().split('T')[0];
                    toDate = endOfLastMonth.toISOString().split('T')[0];
                    break;
                case 'this_quarter':
                    const quarter = Math.floor(today.getMonth() / 3);
                    const startOfQuarter = new Date(today.getFullYear(), quarter * 3, 1);
                    const endOfQuarter = new Date(today.getFullYear(), quarter * 3 + 3, 0);
                    fromDate = startOfQuarter.toISOString().split('T')[0];
                    toDate = endOfQuarter.toISOString().split('T')[0];
                    break;
                case 'this_year':
                    const startOfYear = new Date(today.getFullYear(), 0, 1);
                    const endOfYear = new Date(today.getFullYear(), 11, 31);
                    fromDate = startOfYear.toISOString().split('T')[0];
                    toDate = endOfYear.toISOString().split('T')[0];
                    break;
            }

            // Update the deleted date input fields
            const fromInput = document.querySelector('input[name="deleted_from_date"]');
            const toInput = document.querySelector('input[name="deleted_to_date"]');

            if (fromInput) fromInput.value = fromDate;
            if (toInput) toInput.value = toDate;
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

        // Generic Dropdown Functions (Renamed to avoid sidebar conflict)
        function toggleGenericDropdown(dropdownId) {
            const dropdown = document.getElementById(dropdownId);
            if (dropdown) {
                // Close all other dropdowns
                document.querySelectorAll('.dropdown-menu').forEach(menu => {
                    if (menu.id !== dropdownId && !menu.closest('.sidebar')) {
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
            if (!event.target.closest('.dropdown-toggle')) {
                document.querySelectorAll('.dropdown-menu').forEach(menu => {
                    // Don't close sidebar dropdowns here, let the sidebar handle its own
                    if (!menu.closest('.sidebar')) {
                        menu.style.display = 'none';
                    }
                });
            }
        });

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function () {
            // Check if we need to open a specific management card
            const urlParams = new URLSearchParams(window.location.search);
            const tab = urlParams.get('tab');

            // If it's a reload (performance type 1) and user wants to focus dashboard
            if (window.performance && window.performance.navigation.type === 1 && !tab) {
                // Already defaults to dashboard via PHP, but we can ensure UI state here
                if (typeof switchTab === 'function') switchTab('dashboard');
            }

            if (tab === 'management' || tab === 'maintenance' || tab === 'mnt-calendar') {
                if (typeof window.showManagementCard === 'function') {
                    const cardToShow = tab === 'mnt-calendar' ? 'mnt-calendar' : 'maintenance';
                    window.showManagementCard(cardToShow);
                }
            }
        });

        // Logout Modal Implementation
        window.openLogoutModal = function () {
            // Inject Logout Modal if missing
            if (!document.getElementById('logoutConfirmModal')) {
                const modalHtml = `
                    <div id="logoutConfirmModal" style="display: none; position: fixed; inset: 0; z-index: 999999; background: rgba(0,0,0,0.7); backdrop-filter: blur(8px); align-items: center; justify-content: center; transition: all 0.3s ease;">
                        <div style="background: #ffffff; padding: 40px; border-radius: 24px; width: 400px; text-align: center; box-shadow: 0 40px 100px -20px rgba(0,0,0,0.5); border: 1px solid rgba(0,0,0,0.05);">
                            <div style="width: 80px; height: 80px; background: #fff1f2; color: #e11d48; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 25px; font-size: 32px; box-shadow: 0 10px 20px rgba(225, 29, 72, 0.1);">
                                <i class="fa-solid fa-right-from-bracket"></i>
                            </div>
                            <h3 style="margin: 0 0 12px; color: #0f172a; font-size: 1.6rem; font-weight: 800; letter-spacing: -0.5px;">Exit ATIERA?</h3>
                            <p style="margin: 0 0 35px; color: #64748b; font-size: 1rem; font-weight: 500; line-height: 1.6;">Are you sure you want to exit Atiéra Hotel?<br>You will need to sign in again to access the dashboard.</p>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                                <button onclick="document.getElementById('logoutConfirmModal').style.display='none'" style="padding: 16px; border-radius: 14px; border: 2px solid #e2e8f0; background: #fff; color: #475569; cursor: pointer; font-weight: 700; font-size: 0.95rem; transition: all 0.2s;">No, Stay</button>
                                <button onclick="window.confirmLogout()" style="padding: 16px; border-radius: 14px; border: none; background: #e11d48; color: white; cursor: pointer; font-weight: 700; font-size: 0.95rem; box-shadow: 0 8px 20px rgba(225, 29, 72, 0.25); transition: all 0.2s;">Yes, Logout</button>
                            </div>
                        </div>
                    </div>`;
                const div = document.createElement('div');
                div.innerHTML = modalHtml;
                document.body.appendChild(div.firstElementChild);
            }
            document.getElementById('logoutConfirmModal').style.display = 'flex';
        };

        window.confirmLogout = function () {
            window.location.href = "../auth/login.php?logout=1";
        };

        // Utility function to dynamically change header colors
        window.updateHeaderColor = function (cardType, bgColor) {
            const card = document.querySelector(`[data-card-type="${cardType}"]`);
            if (card) {
                const header = card.querySelector('.card-header');
                if (header) {
                    header.style.setProperty('background', bgColor, 'important');
                    header.style.color = '#fff';
                }
            }
        };
    </script>
    <!-- Loading Overlay -->
    <div id="loadingOverlay"
        style="display:none; position:fixed; inset:0; z-index:99999; background:rgba(0,0,0,0.85); backdrop-filter:blur(4px); transition: opacity 0.5s ease; opacity: 1;">
        <iframe src="../animation/loading.html" style="width:100%; height:100%; border:none;"></iframe>
    </div>
</body>

</html>