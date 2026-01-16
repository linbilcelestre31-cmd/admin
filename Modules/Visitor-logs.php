<?php
/**
 * VISITOR LOGS MODULE
 * Purpose: Tracks and manages visitor entries/exits for security monitoring
 * Features: Log visitors, search/filter logs, export reports, security tracking
 * HR4 API Integration: Can fetch employee data for visitor host validation
 * Financial API Integration: Can fetch financial data for expense tracking
 */

// Include HR4 API for employee data integration
require_once __DIR__ . '/../integ/hr4_api.php';



// config.php - Database configuration

class Database
{
    private $host = "127.0.0.1";
    private $db_name = "admin_new";
    private $username = "admin_new";
    private $password = "123";
    public $conn;

    // Get database connection
    public function getConnection()
    {
        $this->conn = null;

        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name,
                $this->username,
                $this->password
            );
            $this->conn->exec("set names utf8mb4");
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $exception) {
            echo "Connection error: " . $exception->getMessage();
        }

        return $this->conn;
    }
}

// Helper functions
function executeQuery($sql, $params = [])
{
    $database = new Database();
    $db = $database->getConnection();

    try {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    } catch (PDOException $exception) {
        return false;
    }
}

function fetchAll($sql, $params = [])
{
    $stmt = executeQuery($sql, $params);
    if ($stmt) {
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    return [];
}

function fetchOne($sql, $params = [])
{
    $stmt = executeQuery($sql, $params);
    if ($stmt) {
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    return false;
}

function getLastInsertId()
{
    $database = new Database();
    $db = $database->getConnection();
    return $db->lastInsertId();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visitor Management System</title>
    <link rel="icon" type="image/x-icon" href="../assets/image/logo2.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/Visitors.css?v=<?php echo time(); ?>">
    <!-- Added styles for Reports read-panel (beautify only) -->
    <style>
        /* Read panel (side details) */
        .read-panel {
            display: none;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.08);
            padding: 20px;
            margin-top: 20px;
        }

        .read-panel.header {
            font-weight: 600;
        }

        .read-panel .rp-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid #eee;
            padding-bottom: 12px;
            margin-bottom: 16px;
        }

        .btn-back {
            background: #2d8cf0;
            color: #fff;
            border: none;
            padding: 8px 12px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
        }

        .rp-row {
            margin-bottom: 10px;
        }

        .rp-row .label {
            color: #6b7280;
            font-size: 13px;
            margin-bottom: 4px;
        }

        .rp-row .value {
            color: #111827;
            font-size: 15px;
            font-weight: 500;
        }

        /* Imitate nav-link styling for the back button to bypass JS selectors */
        .nav-item-back {
            color: rgba(255, 255, 255, 0.7);
            text-decoration: none;
            font-size: 16px;
            font-weight: 500;
            padding: 8px 12px;
            border-radius: 6px;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .nav-item-back:hover {
            color: #fff;
            background-color: rgba(255, 255, 255, 0.1);
        }
    </style>
</head>

<body>
    <header>
        <div class="container">
            <div class="header-content">
                <div class="logo">
                    <i class="fas fa-shield-halved"></i>
                    Atiera <span>Visitor Management</span>
                </div>
                <nav>
                    <ul>
                        <li><a href="#" class="nav-link active" data-page="dashboard">Dashboard</a></li>
                        <li><a href="#" class="nav-link" data-page="hotel-visitors">Hotel</a></li>
                        <li><a href="#" class="nav-link" data-page="restaurant-visitors">Restaurant</a></li>
                        <li><a href="#" class="nav-link" data-page="reports">Reports</a></li>
                        <li><a href="dashboard.php" class="nav-item-back">Back</a></li>
                    </ul>
                </nav>
            </div>
        </div>
    </header>

    <div class="container">
        <div class="main-content">
            <aside class="sidebar">
                <ul class="sidebar-menu">
                    <li><a href="#" class="sidebar-link active" data-page="dashboard"><i class="fas fa-chart-line"
                                style="margin-right: 12px;"></i>Dashboard</a></li>
                    <li><a href="#" class="sidebar-link" data-page="hotel"><i class="fas fa-hotel"
                                style="margin-right: 12px;"></i>Hotel Management</a></li>
                    <li><a href="#" class="sidebar-link" data-page="restaurant"><i class="fas fa-utensils"
                                style="margin-right: 12px;"></i>Restaurant Management</a></li>
                    <li><a href="#" class="sidebar-link" data-page="reports"><i class="fas fa-file-invoice"
                                style="margin-right: 12px;"></i>Reports</a></li>
                    <li><a href="#" class="sidebar-link" data-page="settings"><i class="fas fa-cog"
                                style="margin-right: 12px;"></i>Settings</a></li>
                </ul>
            </aside>

            <main class="content">
                <!-- Dashboard Page -->
                <div id="dashboard" class="page active">
                    <h1>Dashboard</h1>
                    <div class="stats-container">
                        <div class="stat-card">
                            <i class="fas fa-concierge-bell"></i>
                            <div class="stat-number" id="hotel-today">0</div>
                            <div class="stat-label">Hotel Today</div>
                        </div>
                        <div class="stat-card">
                            <i class="fas fa-utensils"></i>
                            <div class="stat-number" id="restaurant-today">0</div>
                            <div class="stat-label">Restaurant Today</div>
                        </div>
                        <div class="stat-card">
                            <i class="fas fa-user-clock"></i>
                            <div class="stat-number" id="hotel-current">0</div>
                            <div class="stat-label">Inside Hotel</div>
                        </div>
                        <div class="stat-card">
                            <i class="fas fa-chair"></i>
                            <div class="stat-number" id="restaurant-current">0</div>
                            <div class="stat-label">Inside Restaurant</div>
                        </div>
                    </div>

                    <div class="card">
                        <h2>Recent Activity</h2>
                        <div id="recent-activity">
                            <!-- Activity will be populated by JavaScript -->
                        </div>
                    </div>
                </div>

                <!-- Hotel Management Page -->
                <div id="hotel" class="page">
                    <h1>Hotel Management</h1>
                    <div class="tabs">
                        <div class="tab active" data-tab="hotel-visitors">Current Visitors</div>
                        <div class="tab" data-tab="hotel-checkin">Time-in</div>
                        <div class="tab" data-tab="hotel-history">Visitor History</div>
                    </div>

                    <div class="card">
                        <!-- Hotel Time-in Tab -->
                        <div class="tab-content" id="hotel-checkin-tab">
                            <h2><i class="fas fa-id-card-clip"></i> Guest Registration Form</h2>
                            <form id="hotel-checkin-form" method="post" action="#">
                                <div class="form-grid">
                                    <div class="form-group">
                                        <label for="full_name">Full Name</label>
                                        <input type="text" id="full_name" name="full_name" class="form-control"
                                            placeholder="Full name" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="email">Email</label>
                                        <input type="email" id="email" name="email" class="form-control"
                                            placeholder="Email address">
                                    </div>
                                    <div class="form-group">
                                        <label for="phone">Phone</label>
                                        <input type="text" id="phone" name="phone" class="form-control"
                                            placeholder="Phone number">
                                    </div>
                                    <div class="form-group">
                                        <label for="room_number">Room Number</label>
                                        <input type="text" id="room_number" name="room_number" class="form-control"
                                            placeholder="Room number">
                                    </div>
                                    <div class="form-group">
                                        <label for="host_id">Person to Visit (Host)</label>
                                        <select id="host_id" name="host_id" class="form-control">
                                            <option value="">Select Employee...</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="time_in">Time-in Date</label>
                                        <input type="datetime-local" id="time_in" name="time_in" class="form-control">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="notes">Notes</label>
                                    <textarea id="notes" name="notes" class="form-control" rows="4"
                                        placeholder="Notes..."></textarea>
                                </div>
                                <div class="form-group" style="margin-bottom: 2rem;">
                                    <button type="submit" class="btn btn-success" id="timein-submit">Time-in
                                        Guest</button>
                                </div>
                            </form>
                        </div>

                        <!-- Hotel Current Visitors Tab -->
                        <div class="tab-content active" id="hotel-visitors-tab">
                            <div style="margin-bottom: 25px;">
                                <button class="btn btn-success" onclick="activateTab('hotel-checkin')"
                                    style="background-color: #2ecc71; color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-weight: 600;">Time-in
                                    Guest</button>
                            </div>
                            <h2><i class="fas fa-users"></i> Current Guests</h2>
                            <div class="table-container">
                                <table id="hotel-current-table">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Room</th>
                                            <th>Check-in</th>
                                            <th>Check-out</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Hotel History Tab -->
                        <div class="tab-content" id="hotel-history-tab">
                            <h2><i class="fas fa-history"></i> Visitor History</h2>
                            <div class="form-group" style="max-width: 300px; margin-bottom: 20px;">
                                <label for="hotel-history-date">Filter by Date</label>
                                <input type="date" id="hotel-history-date" class="form-control">
                            </div>
                            <div class="table-container">
                                <table id="hotel-history-table">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Room</th>
                                            <th>Time-in</th>
                                            <th>Check-out</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Restaurant Management Page -->
                <div id="restaurant" class="page">
                    <h1>Restaurant Management</h1>
                    <div class="tabs">
                        <div class="tab active" data-tab="restaurant-visitors">Current Visitors</div>
                        <div class="tab" data-tab="restaurant-checkin">Time-in</div>
                        <div class="tab" data-tab="restaurant-history">Visitor History</div>
                    </div>

                    <div class="card">
                        <!-- Restaurant Time-in Tab -->
                        <div class="tab-content" id="restaurant-checkin-tab">
                            <h2><i class="fas fa-utensils"></i> Visitor Registration Form</h2>
                            <form id="restaurant-checkin-form">
                                <div class="form-grid">
                                    <div class="form-group">
                                        <label for="visitor-name">Full Name</label>
                                        <input type="text" id="visitor-name" name="visitor-name" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="visitor-phone">Phone</label>
                                        <input type="tel" id="visitor-phone" name="visitor-phone">
                                    </div>
                                    <div class="form-group">
                                        <label for="party-size">Party Size</label>
                                        <input type="number" id="party-size" name="party-size" min="1" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="table-number">Table Number</label>
                                        <input type="text" id="table-number" name="table-number" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="restaurant-host">Host / Waiter</label>
                                        <select id="restaurant-host" name="restaurant-host" class="form-control">
                                            <option value="">Select Employee...</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="restaurant-notes">Notes</label>
                                    <textarea id="restaurant-notes" name="restaurant-notes" rows="3"></textarea>
                                </div>
                                <div class="form-group" style="margin-top: 1rem; margin-bottom: 2rem;">
                                    <button type="submit" class="btn btn-success">Time-in Visitor</button>
                                </div>
                            </form>
                        </div>

                        <!-- Restaurant Current Visitors Tab -->
                        <div class="tab-content active" id="restaurant-visitors-tab">
                            <div style="margin-bottom: 25px;">
                                <button class="btn btn-success" onclick="activateTab('restaurant-checkin')"
                                    style="background-color: #2ecc71; color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-weight: 600;">Time-in
                                    Visitor</button>
                            </div>
                            <h2><i class="fas fa-users-rays"></i> Current Visitors</h2>
                            <div class="table-container">
                                <table id="restaurant-current-table">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Party Size</th>
                                            <th>Table</th>
                                            <th>Check-in Time</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Restaurant History Tab -->
                        <div class="tab-content" id="restaurant-history-tab">
                            <h2><i class="fas fa-clock-rotate-left"></i> Visitor History</h2>
                            <div class="form-group" style="max-width: 300px; margin-bottom: 20px;">
                                <label for="restaurant-history-date">Filter by Date</label>
                                <input type="date" id="restaurant-history-date" class="form-control">
                            </div>
                            <div class="table-container">
                                <table id="restaurant-history-table">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Party Size</th>
                                            <th>Table</th>
                                            <th>Check-in</th>
                                            <th>Check-out</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Reports Page -->
                <div id="reports" class="page">
                    <h1>Reports</h1>
                    <div class="card">
                        <h2>Generate Reports</h2>
                        <form id="report-form">
                            <div class="form-group">
                                <label for="report-type">Report Type</label>
                                <select id="report-type" name="report-type">
                                    <option value="daily">Daily Report</option>
                                    <option value="weekly">Weekly Report</option>
                                    <option value="monthly">Monthly Report</option>
                                    <option value="custom">Custom Date Range</option>
                                </select>
                            </div>
                            <div class="form-group" id="custom-date-range" style="display: none;">
                                <label for="start-date">Start Date</label>
                                <input type="date" id="start-date" name="start-date">
                                <label for="end-date">End Date</label>
                                <input type="date" id="end-date" name="end-date">
                            </div>
                            <div class="form-group">
                                <label for="report-venue">Venue</label>
                                <select id="report-venue" name="report-venue">
                                    <option value="all">All Venues</option>
                                    <option value="hotel">Hotel Only</option>
                                    <option value="restaurant">Restaurant Only</option>
                                </select>
                            </div>
                            <button type="submit" class="btn-success">Generate Report</button>
                        </form>
                    </div>

                    <div class="card" id="report-results" style="display: none;">
                        <h2>Report Results</h2>
                        <div id="report-data">
                            <!-- Report data will be displayed here -->
                        </div>
                    </div>

                    <!-- Read / Details panel for selected report item -->
                    <aside id="report-read-panel" class="read-panel" aria-hidden="true" style="display:none;">
                        <div class="rp-header">
                            <h3 style="margin:0;">Report Details</h3>
                            <button type="button" class="btn-back" id="rp-back-btn">Back</button>
                        </div>
                        <div id="rp-content">
                            <div class="rp-row">
                                <div class="label">Name</div>
                                <div class="value" id="rp-name">-</div>
                            </div>
                            <div class="rp-row">
                                <div class="label">Venue</div>
                                <div class="value" id="rp-venue">-</div>
                            </div>
                            <div class="rp-row">
                                <div class="label">Check-in</div>
                                <div class="value" id="rp-checkin">-</div>
                            </div>
                            <div class="rp-row">
                                <div class="label">Check-out</div>
                                <div class="value" id="rp-checkout">-</div>
                            </div>
                            <div class="rp-row">
                                <div class="label">Notes</div>
                                <div class="value" id="rp-notes">-</div>
                            </div>
                        </div>
                    </aside>
                </div>

                <!-- Settings Page -->
                <div id="settings" class="page">
                    <h1>System Settings</h1>
                    <div class="card">
                        <h2>General Settings</h2>
                        <form id="settings-form">
                            <div class="form-group">
                                <label for="business-name">Business Name</label>
                                <input type="text" id="business-name" name="business-name" value="Hotel & Restaurant">
                            </div>
                            <div class="form-group">
                                <label for="timezone">Timezone</label>
                                <select id="timezone" name="timezone">
                                    <option value="UTC">UTC</option>
                                    <option value="EST">Eastern Time</option>
                                    <option value="PST">Pacific Time</option>
                                    <!-- Add more timezones as needed -->
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="data-retention">Data Retention (days)</label>
                                <input type="number" id="data-retention" name="data-retention" value="365" min="30">
                            </div>
                            <button type="submit" class="btn-success">Save Settings</button>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Confirmation Modal -->
    <div id="confirmation-modal" class="modal">
        <div class="modal-content" style="max-width: 400px; text-align: center;">
            <div class="modal-header" style="justify-content: center; border-bottom: none;">
                <h2 style="color: #e74c3c;"><i class="fas fa-exclamation-triangle"></i> Confirm Action</h2>
            </div>
            <div class="modal-body" style="padding: 20px 0;">
                <p id="confirmation-message">Are you sure you want to proceed?</p>
            </div>
            <div class="modal-footer" style="display: flex; justify-content: center; gap: 10px; padding-top: 10px;">
                <button id="confirm-btn" class="btn-danger">Yes, Confirm</button>
                <button id="cancel-btn" class="btn-secondary" onclick="closeConfirmationModal()">Cancel</button>
            </div>
        </div>
    </div>

    <style>
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            animation: fadeIn 0.3s;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }

        .modal-content {
            background-color: #fefefe;
            margin: 15% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 80%;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            animation: slideDown 0.3s;
        }

        @keyframes slideDown {
            from {
                transform: translateY(-50px);
                opacity: 0;
            }

            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .btn-danger {
            background-color: #e74c3c;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            transition: background 0.3s;
        }

        .btn-danger:hover {
            background-color: #c0392b;
        }

        .btn-secondary {
            background-color: #95a5a6;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            transition: background 0.3s;
        }

        .btn-secondary:hover {
            background-color: #7f8c8d;
        }
    </style>

    <!-- Details Modal -->
    <div id="details-modal" class="modal">
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header"
                style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #eee; padding-bottom: 10px;">
                <h2 id="details-modal-title" style="color: var(--primary); margin: 0;">Details</h2>
                <button onclick="closeDetailsModal()"
                    style="background-color: #e74c3c; color: white; border: none; padding: 5px 10px; border-radius: 4px; cursor: pointer;">Close</button>
            </div>
            <div class="modal-body" id="details-modal-body" style="padding: 20px 0;">
                <!-- Content will be injected here -->
            </div>
            <div class="modal-footer" style="text-align: right; border-top: 1px solid #eee; padding-top: 15px;">
                <button class="btn-primary" onclick="closeDetailsModal()"
                    style="background-color: var(--primary); color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer;">OK</button>
            </div>
        </div>
    </div>

    <!-- corrected external script filename (fix typo) -->
    <script src="../assets/Javascript/Visitor.js"></script>

    <script>
        // Modal Helper Functions

        // --- Details Modal ---
        function showDetailsModal(title, content) {
            document.getElementById('details-modal-title').innerText = title;
            document.getElementById('details-modal-body').innerHTML = content;
            document.getElementById('details-modal').style.display = 'block';
        }

        function closeDetailsModal() {
            document.getElementById('details-modal').style.display = 'none';
        }

        // Modal Helper Functions
        function showConfirmationModal(message, callback) {
            document.getElementById('confirmation-message').innerText = message;
            const modal = document.getElementById('confirmation-modal');
            modal.style.display = 'block';

            const confirmBtn = document.getElementById('confirm-btn');
            // Remove existing event listeners to prevent multiple firings
            const newBtn = confirmBtn.cloneNode(true);
            confirmBtn.parentNode.replaceChild(newBtn, confirmBtn);

            newBtn.onclick = function () {
                callback();
                closeConfirmationModal();
            };
        }

        function closeConfirmationModal() {
            document.getElementById('confirmation-modal').style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function (event) {
            const confirmModal = document.getElementById('confirmation-modal');
            const detailsModal = document.getElementById('details-modal');

            if (event.target == confirmModal) {
                closeConfirmationModal();
            }
            if (event.target == detailsModal) {
                closeDetailsModal();
            }
        }

        // SHOW/HIDE PAGES from sidebar / top nav
        function showPage(pageId) {
            // hide all pages
            document.querySelectorAll('.page').forEach(function (p) { p.classList.remove('active'); });
            // show requested page
            const page = document.getElementById(pageId);
            if (page) page.classList.add('active');

            // update active state for any element with data-page
            document.querySelectorAll('[data-page]').forEach(function (el) {
                if (el.getAttribute('data-page') === pageId || el.getAttribute('data-page') === pageId + '-checkin' || el.getAttribute('data-page') === pageId + '-visitors') {
                    el.classList.add('active');
                } else {
                    el.classList.remove('active');
                }
            });
        }

        // Activate inner tab (tabName e.g. "hotel-checkin" => content id "hotel-checkin-tab")
        function activateTab(tabName) {
            document.querySelectorAll('.tabs .tab').forEach(function (t) { t.classList.remove('active'); });
            document.querySelectorAll('.tab-content').forEach(function (tc) { tc.classList.remove('active'); });
            const tab = document.querySelector('.tabs .tab[data-tab="' + tabName + '"]');
            if (tab) tab.classList.add('active');
            const tc = document.getElementById(tabName + '-tab');
            if (tc) tc.classList.add('active');

            // If Visitor.js is loaded, trigger data refresh
            if (typeof loadCurrentVisitors === 'function' && tabName.includes('visitors')) {
                loadCurrentVisitors();
            }
        }

        document.addEventListener('DOMContentLoaded', function () {
            // Sidebar / nav click handling — attach only to elements that have data-page
            document.querySelectorAll('a[data-page]').forEach(function (el) {
                el.addEventListener('click', function (e) {
                    const requested = this.getAttribute('data-page');
                    if (!requested) return; // do nothing if no data-page
                    e.preventDefault(); // only prevent when we handle SPA navigation

                    // If data-page is compound like "hotel-checkin" show main page 'hotel' and activate tab
                    if (requested.indexOf('-') !== -1) {
                        const parts = requested.split('-');
                        const main = parts[0]; // 'hotel' or 'restaurant'
                        showPage(main);
                        activateTab(requested);
                    } else {
                        // direct page id matches page div ids (dashboard, hotel, restaurant, reports, settings)
                        showPage(requested);
                    }
                });
            });

            // Tabs click handling inside pages
            document.querySelectorAll('.tabs .tab').forEach(function (tab) {
                tab.addEventListener('click', function () {
                    activateTab(this.getAttribute('data-tab'));
                });
            });

            // On load: trigger display based on existing sidebar active, otherwise default to dashboard
            var starter = document.querySelector('.sidebar-link.active') || document.querySelector('.nav-link.active');
            if (starter && starter.getAttribute('data-page')) {
                var p = starter.getAttribute('data-page');
                // reuse click logic to ensure inner tabs show
                starter.click();
            } else {
                showPage('dashboard');
            }

            // REPORT read-panel helpers (moved inside DOM ready to avoid null errors)
            function showReportRead(data) {
                var results = document.getElementById('report-results');
                if (results) results.style.display = 'none';
                var panel = document.getElementById('report-read-panel');
                if (!panel) return;
                panel.style.display = 'block';
                panel.setAttribute('aria-hidden', 'false');
                document.getElementById('rp-name').textContent = data.name || '-';
                document.getElementById('rp-venue').textContent = data.venue || '-';
                document.getElementById('rp-checkin').textContent = data.checkin || '-';
                document.getElementById('rp-checkout').textContent = data.checkout || '-';
                document.getElementById('rp-notes').textContent = data.notes || '-';
            }

            var backBtn = document.getElementById('rp-back-btn');
            if (backBtn) {
                backBtn.addEventListener('click', function () {
                    var panel = document.getElementById('report-read-panel');
                    if (panel) {
                        panel.style.display = 'none';
                        panel.setAttribute('aria-hidden', 'true');
                    }
                    var results = document.getElementById('report-results');
                    if (results) results.style.display = '';
                });
            }

            var reportData = document.getElementById('report-data');
            if (reportData) {
                reportData.addEventListener('click', function (e) {
                    var target = e.target;
                    if (target.classList && target.classList.contains('view-btn')) {
                        var row = target.closest('[data-item]');
                        var payload = row ? JSON.parse(row.getAttribute('data-item')) : {};
                        showReportRead(payload);
                    }
                });
            }
        });
    </script>
</body>

</html>