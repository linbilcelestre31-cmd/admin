<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin Dashboard | Hotel & Restaurant System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="super-admin.css">
</head>

<body>
    <!-- Login Page -->
    <div id="loginPage" class="page active">
        <div class="login-container">
            <div class="login-header">
                <div class="logo">
                    <i class="fas fa-hotel"></i>
                    <h1>Hotel & Restaurant Admin</h1>
                </div>
                <h2>Super Admin Login</h2>
                <p>Access the complete management system</p>
            </div>

            <form id="loginForm" class="login-form">
                <div class="form-group">
                    <label for="username">
                        <i class="fas fa-user-shield"></i> Super Admin ID
                    </label>
                    <input type="text" id="username" placeholder="Enter admin ID" required>
                </div>

                <div class="form-group">
                    <label for="password">
                        <i class="fas fa-key"></i> Password
                    </label>
                    <input type="password" id="password" placeholder="Enter password" required>
                    <button type="button" class="show-password" id="showPassword">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>

                <div class="form-group">
                    <label for="twoFactor">
                        <i class="fas fa-mobile-alt"></i> 2FA Code (Optional)
                    </label>
                    <input type="text" id="twoFactor" placeholder="Enter 6-digit code">
                </div>

                <button type="submit" class="login-btn">
                    <i class="fas fa-sign-in-alt"></i> Login as Super Admin
                </button>

                <div class="security-notice">
                    <i class="fas fa-shield-alt"></i>
                    <p>Enhanced security enabled. All activities are logged.</p>
                </div>
            </form>

            <div class="login-footer">
                <p><i class="fas fa-exclamation-circle"></i> For authorized personnel only</p>
                <p class="version">v2.1.0 | Super Admin Portal</p>
            </div>
        </div>
    </div>

    <!-- Dashboard Page -->
    <div id="dashboardPage" class="page hidden">
        <!-- Navigation -->
        <nav class="navbar">
            <div class="nav-left">
                <div class="logo">
                    <i class="fas fa-crown"></i>
                    <span>SUPER ADMIN</span>
                </div>
            </div>

            <div class="nav-center">
                <h1>Hotel & Restaurant Management System</h1>
            </div>

            <div class="nav-right">
                <div class="admin-info">
                    <i class="fas fa-user-circle"></i>
                    <span id="adminName">Super Admin</span>
                    <div class="admin-menu">
                        <a href="#"><i class="fas fa-user-cog"></i> Profile</a>
                        <a href="#"><i class="fas fa-cog"></i> Settings</a>
                        <a href="#" id="logoutBtn"><i class="fas fa-sign-out-alt"></i> Logout</a>
                    </div>
                </div>
                <div class="notifications">
                    <i class="fas fa-bell"></i>
                    <span class="badge">3</span>
                </div>
            </div>
        </nav>

        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-menu">
                <a href="#" class="active" data-section="dashboard">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
                <a href="#" data-section="users">
                    <i class="fas fa-users-cog"></i> User Management
                </a>
                <a href="#" data-section="hotels">
                    <i class="fas fa-hotel"></i> Hotels
                </a>
                <a href="#" data-section="restaurants">
                    <i class="fas fa-utensils"></i> Restaurants
                </a>
                <a href="#" data-section="bookings">
                    <i class="fas fa-calendar-check"></i> Bookings
                </a>
                <a href="#" data-section="inventory">
                    <i class="fas fa-boxes"></i> Inventory
                </a>
                <a href="#" data-section="reports">
                    <i class="fas fa-chart-bar"></i> Reports & Analytics
                </a>
                <a href="#" data-section="settings">
                    <i class="fas fa-sliders-h"></i> System Settings
                </a>
                <a href="#" data-section="audit">
                    <i class="fas fa-clipboard-list"></i> Audit Logs
                </a>
            </div>

            <div class="system-status">
                <h4><i class="fas fa-server"></i> System Status</h4>
                <div class="status-item">
                    <span>API:</span>
                    <span class="status online">Online</span>
                </div>
                <div class="status-item">
                    <span>Database:</span>
                    <span class="status online">Active</span>
                </div>
                <div class="status-item">
                    <span>Last Backup:</span>
                    <span class="status">2 hours ago</span>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Dashboard Section (Default) -->
            <section id="dashboard" class="content-section active">
                <div class="section-header">
                    <h2><i class="fas fa-tachometer-alt"></i> Super Admin Dashboard</h2>
                    <div class="date-time" id="currentDateTime"></div>
                </div>

                <!-- Stats Cards -->
                <div class="stats-grid">
                    <div class="stat-card total-revenue">
                        <div class="stat-icon">
                            <i class="fas fa-money-bill-wave"></i>
                        </div>
                        <div class="stat-info">
                            <h3>Total Revenue</h3>
                            <p class="stat-number">$124,580</p>
                            <p class="stat-change">+12% this month</p>
                        </div>
                    </div>

                    <div class="stat-card total-users">
                        <div class="stat-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-info">
                            <h3>Total Users</h3>
                            <p class="stat-number">2,847</p>
                            <p class="stat-change">+45 this week</p>
                        </div>
                    </div>

                    <div class="stat-card active-bookings">
                        <div class="stat-icon">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                        <div class="stat-info">
                            <h3>Active Bookings</h3>
                            <p class="stat-number">156</p>
                            <p class="stat-change">+8 today</p>
                        </div>
                    </div>

                    <div class="stat-card occupancy">
                        <div class="stat-icon">
                            <i class="fas fa-bed"></i>
                        </div>
                        <div class="stat-info">
                            <h3>Occupancy Rate</h3>
                            <p class="stat-number">78%</p>
                            <p class="stat-change">+5% from yesterday</p>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="quick-actions">
                    <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
                    <div class="actions-grid">
                        <button class="action-btn" data-action="addUser">
                            <i class="fas fa-user-plus"></i>
                            <span>Add New Admin</span>
                        </button>
                        <button class="action-btn" data-action="viewReports">
                            <i class="fas fa-chart-pie"></i>
                            <span>Generate Reports</span>
                        </button>
                        <button class="action-btn" data-action="systemBackup">
                            <i class="fas fa-database"></i>
                            <span>System Backup</span>
                        </button>
                        <button class="action-btn" data-action="auditLogs">
                            <i class="fas fa-clipboard-check"></i>
                            <span>View Audit Logs</span>
                        </button>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="recent-activity">
                    <h3><i class="fas fa-history"></i> Recent Activity</h3>
                    <div class="activity-list">
                        <div class="activity-item">
                            <i class="fas fa-user-plus activity-icon new-user"></i>
                            <div class="activity-details">
                                <p>New hotel manager added</p>
                                <span class="activity-time">10 minutes ago</span>
                            </div>
                        </div>
                        <div class="activity-item">
                            <i class="fas fa-calendar-check activity-icon booking"></i>
                            <div class="activity-details">
                                <p>5 new bookings today</p>
                                <span class="activity-time">1 hour ago</span>
                            </div>
                        </div>
                        <div class="activity-item">
                            <i class="fas fa-cog activity-icon settings"></i>
                            <div class="activity-details">
                                <p>System settings updated</p>
                                <span class="activity-time">2 hours ago</span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- User Management Section -->
            <section id="users" class="content-section">
                <div class="section-header">
                    <h2><i class="fas fa-users-cog"></i> User Management</h2>
                    <button class="btn btn-primary" id="addUserBtn">
                        <i class="fas fa-plus"></i> Add User
                    </button>
                </div>
                <div class="users-table-container">
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
                            <!-- Users will be populated by JavaScript -->
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Hotels Management Section -->
            <section id="hotels" class="content-section">
                <div class="section-header">
                    <h2><i class="fas fa-hotel"></i> Hotel Management</h2>
                </div>
                <div class="section-content">
                    <p>Hotel management content will be displayed here.</p>
                </div>
            </section>

            <!-- Restaurants Management Section -->
            <section id="restaurants" class="content-section">
                <div class="section-header">
                    <h2><i class="fas fa-utensils"></i> Restaurant Management</h2>
                </div>
                <div class="section-content">
                    <p>Restaurant management content will be displayed here.</p>
                </div>
            </section>

            <!-- Bookings Management Section -->
            <section id="bookings" class="content-section">
                <div class="section-header">
                    <h2><i class="fas fa-calendar-check"></i> Bookings Management</h2>
                </div>
                <div class="section-content">
                    <p>Bookings management content will be displayed here.</p>
                </div>
            </section>

            <!-- Inventory Management Section -->
            <section id="inventory" class="content-section">
                <div class="section-header">
                    <h2><i class="fas fa-boxes"></i> Inventory Management</h2>
                </div>
                <div class="section-content">
                    <p>Inventory management content will be displayed here.</p>
                </div>
            </section>

            <!-- Reports Section -->
            <section id="reports" class="content-section">
                <div class="section-header">
                    <h2><i class="fas fa-chart-bar"></i> Reports & Analytics</h2>
                </div>
                <div class="section-content">
                    <p>Reports and analytics content will be displayed here.</p>
                </div>
            </section>

            <!-- Settings Section -->
            <section id="settings" class="content-section">
                <div class="section-header">
                    <h2><i class="fas fa-sliders-h"></i> System Settings</h2>
                </div>
                <div class="section-content">
                    <p>System settings content will be displayed here.</p>
                </div>
            </section>

            <!-- Audit Logs Section -->
            <section id="audit" class="content-section">
                <div class="section-header">
                    <h2><i class="fas fa-clipboard-list"></i> Audit Logs</h2>
                </div>
                <div class="section-content">
                    <p>Audit logs content will be displayed here.</p>
                </div>
            </section>
        </main>

        <!-- Modal for Adding User -->
        <div id="addUserModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>Add New User</h3>
                    <span class="close-modal">&times;</span>
                </div>
                <div class="modal-body">
                    <form id="addUserForm">
                        <div class="form-group">
                            <label>Full Name</label>
                            <input type="text" id="newUserName" required>
                        </div>
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" id="newUserEmail" required>
                        </div>
                        <div class="form-group">
                            <label>Role</label>
                            <select id="newUserRole" required>
                                <option value="">Select Role</option>
                                <option value="admin">Admin</option>
                                <option value="hotel_manager">Hotel Manager</option>
                                <option value="restaurant_manager">Restaurant Manager</option>
                                <option value="staff">Staff</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Temporary Password</label>
                            <input type="password" id="newUserPassword" required>
                        </div>
                        <button type="submit" class="btn btn-primary">Create User</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="super-admin.js"></script>
</body>

</html>