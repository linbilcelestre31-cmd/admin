<?php
session_start();

// Security check: Only Super Admin can access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    header('Location: auth/login.php');
    exit;
}

require_once __DIR__ . '/../db/db.php';
$pdo = get_pdo();

// Fetch some basic stats for initial load
try {
    $total_users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $total_admins = $pdo->query("SELECT COUNT(*) FROM administrators")->fetchColumn();
    $total_docs = $pdo->query("SELECT COUNT(*) FROM documents")->fetchColumn();
} catch (PDOException $e) {
    $total_users = 0;
    $total_admins = 0;
    $total_docs = 0;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin Dashboard | ATIERA</title>
    <link rel="icon" type="image/x-icon" href="../assets/image/logo2.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/super-admin.css?v=<?php echo time(); ?>">
    <style>
        body {
            font-family: 'Outfit', sans-serif;
            background: #f8fafc;
        }

        .sidebar {
            background: #0f172a;
            color: white;
        }

        .sidebar-menu a {
            color: #94a3b8;
        }

        .sidebar-menu a:hover,
        .sidebar-menu a.active {
            background: #1e293b;
            color: #3b82f6;
            border-left-color: #3b82f6;
        }

        .navbar {
            background: white;
            border-bottom: 1px solid #e2e8f0;
        }

        .stat-card {
            border: none;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>

<body>
    <div id="dashboardPage" class="active">
        <!-- Navigation Bar -->
        <nav class="navbar">
            <div class="nav-left">
                <div class="logo">
                    <i class="fas fa-shield-check"></i>
                    <span>ATIERA SUPER_ADMIN</span>
                </div>
            </div>
            <div class="nav-center">
                <h1 id="currentSectionTitle">Dashboard Overview</h1>
            </div>
            <div class="nav-right">
                <div class="notifications">
                    <i class="fas fa-bell"></i>
                    <span class="badge">3</span>
                </div>
                <div class="admin-info" id="adminDropdown">
                    <i class="fas fa-user-circle"></i>
                    <span id="adminName"><?php echo htmlspecialchars($_SESSION['username'] ?? 'Super Admin'); ?></span>
                    <i class="fas fa-chevron-down" style="font-size: 0.8rem;"></i>
                    <div class="admin-menu">
                        <a href="#"><i class="fas fa-user"></i> My Profile</a>
                        <a href="#"><i class="fas fa-cog"></i> Settings</a>
                        <a href="auth/logout.php" id="logoutBtn"><i class="fas fa-sign-out-alt"></i> Logout</a>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-menu">
                <a href="#" class="active" data-section="overview">
                    <i class="fas fa-th-large"></i>
                    <span>Overview</span>
                </a>
                <a href="#" data-section="users">
                    <i class="fas fa-users-cog"></i>
                    <span>User Management</span>
                </a>
                <a href="#" data-section="reports">
                    <i class="fas fa-chart-line"></i>
                    <span>System Reports</span>
                </a>
                <a href="#" data-section="audit">
                    <i class="fas fa-history"></i>
                    <span>Audit Logs</span>
                </a>
                <a href="#" data-section="settings">
                    <i class="fas fa-tools"></i>
                    <span>System Config</span>
                </a>
            </div>

            <div class="system-status">
                <h4><i class="fas fa-server"></i> System Status</h4>
                <div class="status-item">
                    <span>Database</span>
                    <span class="status online">Online</span>
                </div>
                <div class="status-item">
                    <span>API Server</span>
                    <span class="status online">Online</span>
                </div>
                <div class="status-item">
                    <span>Storage</span>
                    <span>85% Free</span>
                </div>
            </div>
        </aside>

        <!-- Main Content Area -->
        <main class="main-content">
            <!-- Overview Section -->
            <section id="overview" class="content-section active">
                <div class="section-header">
                    <h2>Welcome back, Super Admin</h2>
                    <div class="date-time" id="currentDateTime"></div>
                </div>

                <div class="stats-grid">
                    <div class="stat-card total-revenue">
                        <div class="stat-icon"><i class="fas fa-dollar-sign"></i></div>
                        <div class="stat-info">
                            <h3>Total Revenue</h3>
                            <div class="stat-number">$124,580</div>
                            <div class="stat-change"><i class="fas fa-arrow-up"></i> 12% from last month</div>
                        </div>
                    </div>
                    <div class="stat-card total-users">
                        <div class="stat-icon"><i class="fas fa-users"></i></div>
                        <div class="stat-info">
                            <h3>Total Users</h3>
                            <div class="stat-number"><?php echo $total_users; ?></div>
                            <div class="stat-change">Active accounts</div>
                        </div>
                    </div>
                    <div class="stat-card active-bookings">
                        <div class="stat-icon"><i class="fas fa-calendar-check"></i></div>
                        <div class="stat-info">
                            <h3>Staff/Admins</h3>
                            <div class="stat-number"><?php echo $total_admins; ?></div>
                            <div class="stat-change">Internal access</div>
                        </div>
                    </div>
                    <div class="stat-card occupancy">
                        <div class="stat-icon"><i class="fas fa-file-invoice"></i></div>
                        <div class="stat-info">
                            <h3>Docs Archived</h3>
                            <div class="stat-number"><?php echo $total_docs; ?></div>
                            <div class="stat-change">Managed files</div>
                        </div>
                    </div>
                </div>

                <div class="quick-actions">
                    <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
                    <div class="actions-grid">
                        <div class="action-btn" data-action="addUser">
                            <i class="fas fa-user-plus"></i>
                            <span>Add New User</span>
                        </div>
                        <div class="action-btn" data-action="viewReports">
                            <i class="fas fa-file-chart-column"></i>
                            <span>Generate Reports</span>
                        </div>
                        <div class="action-btn" data-action="systemBackup">
                            <i class="fas fa-database"></i>
                            <span>Backup System</span>
                        </div>
                        <div class="action-btn" data-action="auditLogs">
                            <i class="fas fa-clipboard-list"></i>
                            <span>View Audit Logs</span>
                        </div>
                    </div>
                </div>

                <div class="recent-activity">
                    <h3><i class="fas fa-list-ul"></i> Recent System Activity</h3>
                    <div class="activity-list">
                        <!-- Loaded dynamically -->
                        <div class="activity-item">
                            <i class="fas fa-info-circle activity-icon settings"></i>
                            <div class="activity-details">
                                <p>System settings updated</p>
                                <span class="activity-time">Just now</span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Users Section -->
            <section id="users" class="content-section">
                <div class="section-header">
                    <h2>User Management</h2>
                    <button class="btn btn-primary" id="addUserBtn"><i class="fas fa-plus"></i> Add User</button>
                </div>
                <div class="data-table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Last Login</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="usersTableBody">
                            <!-- Populated by JS -->
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Other sections can be added here -->
        </main>
    </div>

    <!-- Modals -->
    <div id="addUserModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add New System User</h3>
                <span class="close-modal">&times;</span>
            </div>
            <div class="modal-body">
                <form id="addUserForm">
                    <div class="form-group" style="margin-bottom: 15px;">
                        <label>Full Name</label>
                        <input type="text" name="name" class="input-control" placeholder="Enter name" required
                            style="border: 1px solid #ddd; padding: 10px; width:100%; border-radius:8px;">
                    </div>
                    <div class="form-group" style="margin-bottom: 15px;">
                        <label>Email Address</label>
                        <input type="email" name="email" class="input-control" placeholder="Enter email" required
                            style="border: 1px solid #ddd; padding: 10px; width:100%; border-radius:8px;">
                    </div>
                    <div class="form-group" style="margin-bottom: 20px;">
                        <label>Access Role</label>
                        <select name="role" class="input-control"
                            style="border: 1px solid #ddd; padding: 10px; width:100%; border-radius:8px;">
                            <option value="hotel_manager">Hotel Manager</option>
                            <option value="restaurant_manager">Restaurant Manager</option>
                            <option value="staff">Standard Staff</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary"
                        style="width: 100%; justify-content: center; padding: 15px;">Create Account</button>
                </form>
            </div>
        </div>
    </div>

    <script src="super-js/super-admin.js?v=<?php echo time(); ?>"></script>
    <script>
        // Override the handleLogin in JS since we handle it in login.php
        if (window.authManager) {
            window.authManager.showDashboard = function () {
                // Do nothing, we are already here
            };
        }
    </script>
</body>

</html>