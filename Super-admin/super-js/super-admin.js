// Authentication and Session Management
class AuthManager {
    constructor() {
        this.currentUser = null;
        this.init();
    }

    init() {
        this.checkLoginStatus();
        this.setupEventListeners();
    }

    checkLoginStatus() {
        const user = localStorage.getItem('superAdminUser');
        if (user) {
            this.currentUser = JSON.parse(user);
            this.showDashboard();
            this.updateUI();
        } else {
            this.showLogin();
        }
    }

    setupEventListeners() {
        // Login Form
        const loginForm = document.getElementById('loginForm');
        if (loginForm) {
            loginForm.addEventListener('submit', (e) => this.handleLogin(e));
        }

        // Show Password Button
        const showPasswordBtn = document.getElementById('showPassword');
        if (showPasswordBtn) {
            showPasswordBtn.addEventListener('click', () => {
                const passwordInput = document.getElementById('password');
                const type = passwordInput.type === 'password' ? 'text' : 'password';
                passwordInput.type = type;
                showPasswordBtn.innerHTML = type === 'password'
                    ? '<i class="fas fa-eye"></i>'
                    : '<i class="fas fa-eye-slash"></i>';
            });
        }

        // Logout Button
        const logoutBtn = document.getElementById('logoutBtn');
        if (logoutBtn) {
            logoutBtn.addEventListener('click', (e) => {
                e.preventDefault();
                this.logout();
            });
        }
    }

    async handleLogin(e) {
        e.preventDefault();

        const username = document.getElementById('username').value;
        const password = document.getElementById('password').value;
        const twoFactor = document.getElementById('twoFactor')?.value;

        // Simulate API call
        const loginBtn = document.querySelector('.login-btn');
        const originalText = loginBtn.innerHTML;

        loginBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Authenticating...';
        loginBtn.disabled = true;

        try {
            // Simulate API delay
            await new Promise(resolve => setTimeout(resolve, 1500));

            // For demo purposes - in production, validate against your backend
            if (username === 'superadmin' && password === 'admin123') {
                const user = {
                    id: 'SA001',
                    name: 'Super Administrator',
                    email: 'superadmin@system.com',
                    role: 'super_admin',
                    permissions: ['all'],
                    loginTime: new Date().toISOString()
                };

                localStorage.setItem('superAdminUser', JSON.stringify(user));
                localStorage.setItem('sessionToken', 'demo-token-' + Date.now());
                localStorage.setItem('lastLogin', new Date().toISOString());

                // Log the login attempt
                this.logActivity('LOGIN', 'Super admin logged in');

                this.currentUser = user;
                this.showDashboard();
                this.updateUI();
            } else {
                throw new Error('Invalid credentials');
            }
        } catch (error) {
            this.showError('Authentication failed. Please check your credentials.');
        } finally {
            loginBtn.innerHTML = originalText;
            loginBtn.disabled = false;
        }
    }

    logout() {
        // Log the logout
        this.logActivity('LOGOUT', 'Super admin logged out');

        // Clear all storage
        localStorage.clear();
        sessionStorage.clear();

        // Redirect to login
        this.showLogin();
    }

    showLogin() {
        const loginPage = document.getElementById('loginPage');
        const dashboardPage = document.getElementById('dashboardPage');

        loginPage.classList.remove('hidden');
        loginPage.classList.add('active');
        dashboardPage.classList.remove('active');
        dashboardPage.classList.add('hidden');
    }

    showDashboard() {
        const loginPage = document.getElementById('loginPage');
        const dashboardPage = document.getElementById('dashboardPage');

        loginPage.classList.remove('active');
        loginPage.classList.add('hidden');
        dashboardPage.classList.remove('hidden');
        dashboardPage.classList.add('active');

        // Initialize dashboard if it hasn't been initialized yet
        if (!window.dashboardManager) {
            window.dashboardManager = new DashboardManager();
        } else {
            window.dashboardManager.loadDashboardData();
        }
    }

    updateUI() {
        const adminName = document.getElementById('adminName');
        if (adminName && this.currentUser) {
            adminName.textContent = this.currentUser.name;
        }
    }

    showError(message) {
        const errorDiv = document.createElement('div');
        errorDiv.className = 'error-message';
        errorDiv.innerHTML = `
                    <i class="fas fa-exclamation-circle"></i>
                    <span>${message}</span>
                `;

        const form = document.querySelector('.login-form');
        if (form) {
            form.insertBefore(errorDiv, form.firstChild);

            // Remove error after 5 seconds
            setTimeout(() => {
                errorDiv.remove();
            }, 5000);
        }
    }

    logActivity(action, details) {
        const activities = JSON.parse(localStorage.getItem('adminActivities') || '[]');
        activities.unshift({
            action,
            details,
            timestamp: new Date().toISOString(),
            user: this.currentUser?.name || 'System'
        });

        // Keep only last 100 activities
        if (activities.length > 100) {
            activities.pop();
        }

        localStorage.setItem('adminActivities', JSON.stringify(activities));
    }

    hasPermission(permission) {
        if (!this.currentUser) return false;
        return this.currentUser.permissions.includes('all') ||
            this.currentUser.permissions.includes(permission);
    }
}

// Dashboard Functionality
class DashboardManager {
    constructor() {
        this.init();
    }

    init() {
        this.setupNavigation();
        this.loadDashboardData();
        this.setupModals();
        this.setupEventListeners();
        this.updateDateTime();

        // Update time every minute
        setInterval(() => this.updateDateTime(), 60000);
    }

    setupNavigation() {
        const navLinks = document.querySelectorAll('.sidebar-menu a');
        navLinks.forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();

                // Remove active class from all links
                navLinks.forEach(l => l.classList.remove('active'));

                // Add active class to clicked link
                link.classList.add('active');

                // Show corresponding section
                const sectionId = link.getAttribute('data-section');
                this.showSection(sectionId);
            });
        });
    }

    showSection(sectionId) {
        // Hide all sections
        const sections = document.querySelectorAll('.content-section');
        sections.forEach(section => {
            section.classList.remove('active');
        });

        // Show selected section
        const targetSection = document.getElementById(sectionId);
        if (targetSection) {
            targetSection.classList.add('active');

            // Load section-specific data
            this.loadSectionData(sectionId);
        }
    }

    async loadDashboardData() {
        try {
            // Load users
            await this.loadUsers();

            // Load activities
            this.loadRecentActivities();

            // Load statistics
            this.loadStatistics();

        } catch (error) {
            console.error('Error loading dashboard data:', error);
        }
    }

    async loadUsers() {
        try {
            // Simulate API call
            const users = [
                {
                    id: 'U001',
                    name: 'John Doe',
                    email: 'john@hotel.com',
                    role: 'Hotel Manager',
                    status: 'active',
                    lastLogin: '2024-01-15 14:30'
                },
                {
                    id: 'U002',
                    name: 'Jane Smith',
                    email: 'jane@restaurant.com',
                    role: 'Restaurant Manager',
                    status: 'active',
                    lastLogin: '2024-01-15 10:15'
                },
                {
                    id: 'U003',
                    name: 'Mike Johnson',
                    email: 'mike@staff.com',
                    role: 'Staff',
                    status: 'inactive',
                    lastLogin: '2024-01-14 09:45'
                }
            ];

            const tableBody = document.getElementById('usersTableBody');
            if (tableBody) {
                tableBody.innerHTML = users.map(user => `
                            <tr>
                                <td>${user.id}</td>
                                <td>${user.name}</td>
                                <td>${user.email}</td>
                                <td><span class="role-badge ${user.role.toLowerCase().replace(' ', '-')}">${user.role}</span></td>
                                <td><span class="status ${user.status}">${user.status}</span></td>
                                <td>${user.lastLogin}</td>
                                <td class="actions">
                                    <button class="btn-icon edit-user" data-id="${user.id}">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn-icon delete-user" data-id="${user.id}">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                    <button class="btn-icon reset-password" data-id="${user.id}">
                                        <i class="fas fa-key"></i>
                                    </button>
                                </td>
                            </tr>
                        `).join('');
            }
        } catch (error) {
            console.error('Error loading users:', error);
        }
    }

    loadRecentActivities() {
        const activities = JSON.parse(localStorage.getItem('adminActivities') || '[]');
        const activityList = document.querySelector('.activity-list');

        if (activityList && activities.length > 0) {
            const recentActivities = activities.slice(0, 5);
            activityList.innerHTML = recentActivities.map(activity => `
                        <div class="activity-item">
                            <i class="fas fa-${this.getActivityIcon(activity.action)} activity-icon"></i>
                            <div class="activity-details">
                                <p>${activity.details}</p>
                                <span class="activity-time">${this.formatTime(activity.timestamp)}</span>
                            </div>
                        </div>
                    `).join('');
        }
    }

    getActivityIcon(action) {
        const icons = {
            'LOGIN': 'sign-in-alt',
            'LOGOUT': 'sign-out-alt',
            'CREATE': 'plus-circle',
            'UPDATE': 'edit',
            'DELETE': 'trash',
            'SETTINGS': 'cog'
        };
        return icons[action] || 'info-circle';
    }

    formatTime(timestamp) {
        const date = new Date(timestamp);
        const now = new Date();
        const diff = now - date;

        if (diff < 60000) return 'Just now';
        if (diff < 3600000) return `${Math.floor(diff / 60000)} minutes ago`;
        if (diff < 86400000) return `${Math.floor(diff / 3600000)} hours ago`;
        return date.toLocaleDateString();
    }

    loadStatistics() {
        // In a real application, this would fetch from an API
        const stats = {
            revenue: 124580,
            users: 2847,
            bookings: 156,
            occupancy: 78
        };

        // Update stats in UI
        document.querySelectorAll('.stat-number').forEach(stat => {
            const parent = stat.closest('.stat-card');
            if (parent.classList.contains('total-revenue')) {
                stat.textContent = `$${stats.revenue.toLocaleString()}`;
            } else if (parent.classList.contains('total-users')) {
                stat.textContent = stats.users.toLocaleString();
            } else if (parent.classList.contains('active-bookings')) {
                stat.textContent = stats.bookings.toLocaleString();
            } else if (parent.classList.contains('occupancy')) {
                stat.textContent = `${stats.occupancy}%`;
            }
        });
    }

    setupModals() {
        const modal = document.getElementById('addUserModal');
        const addUserBtn = document.getElementById('addUserBtn');
        const closeModal = document.querySelector('.close-modal');

        if (addUserBtn) {
            addUserBtn.addEventListener('click', () => {
                modal.style.display = 'flex';
            });
        }

        if (closeModal) {
            closeModal.addEventListener('click', () => {
                modal.style.display = 'none';
            });
        }

        // Close modal when clicking outside
        window.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.style.display = 'none';
            }
        });
    }

    setupEventListeners() {
        // Quick action buttons
        document.querySelectorAll('.action-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const action = e.currentTarget.getAttribute('data-action');
                this.handleQuickAction(action);
            });
        });

        // Add user form
        const addUserForm = document.getElementById('addUserForm');
        if (addUserForm) {
            addUserForm.addEventListener('submit', (e) => {
                e.preventDefault();
                this.addNewUser();
            });
        }
    }

    handleQuickAction(action) {
        switch (action) {
            case 'addUser':
                document.getElementById('addUserModal').style.display = 'flex';
                break;
            case 'viewReports':
                this.showSection('reports');
                break;
            case 'systemBackup':
                this.initiateBackup();
                break;
            case 'auditLogs':
                this.showSection('audit');
                break;
        }
    }

    async addNewUser() {
        const form = document.getElementById('addUserForm');
        const formData = new FormData(form);

        // Simulate API call
        try {
            // Show loading
            const submitBtn = form.querySelector('button[type="submit"]');
            const originalText = submitBtn.textContent;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating...';
            submitBtn.disabled = true;

            await new Promise(resolve => setTimeout(resolve, 1000));

            // Success
            this.showNotification('User created successfully!', 'success');

            // Close modal and reset form
            document.getElementById('addUserModal').style.display = 'none';
            form.reset();

            // Refresh users list
            await this.loadUsers();

            submitBtn.textContent = originalText;
            submitBtn.disabled = false;

        } catch (error) {
            this.showNotification('Error creating user', 'error');
        }
    }

    initiateBackup() {
        this.showNotification('System backup initiated...', 'info');

        // Simulate backup process
        setTimeout(() => {
            this.showNotification('Backup completed successfully!', 'success');
        }, 3000);
    }

    showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `notification ${type}`;
        notification.innerHTML = `
                    <i class="fas fa-${type === 'success' ? 'check-circle' : 'info-circle'}"></i>
                    <span>${message}</span>
                `;

        document.body.appendChild(notification);

        // Remove after 5 seconds
        setTimeout(() => {
            notification.remove();
        }, 5000);
    }

    updateDateTime() {
        const dateTimeElement = document.getElementById('currentDateTime');
        if (dateTimeElement) {
            const now = new Date();
            const options = {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            };
            dateTimeElement.textContent = now.toLocaleDateString('en-US', options);
        }
    }

    loadSectionData(sectionId) {
        switch (sectionId) {
            case 'users':
                this.loadUsers();
                break;
            case 'reports':
                this.loadReports();
                break;
            case 'audit':
                this.loadAuditLogs();
                break;
        }
    }

    loadReports() {
        // Load reports data
        console.log('Loading reports...');
    }

    loadAuditLogs() {
        // Load audit logs
        console.log('Loading audit logs...');
    }
}

// API Simulation for Backend Communication
class APIService {
    constructor() {
        this.baseURL = 'https://api.hotel-restaurant-system.com';
        this.token = localStorage.getItem('sessionToken');
    }

    async request(endpoint, options = {}) {
        const url = `${this.baseURL}${endpoint}`;

        const headers = {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${this.token}`,
            ...options.headers
        };

        const config = {
            ...options,
            headers
        };

        try {
            // Simulate API call
            return await this.simulateRequest(endpoint, config);
        } catch (error) {
            console.error('API Error:', error);
            throw error;
        }
    }

    async simulateRequest(endpoint, config) {
        // Simulate network delay
        await new Promise(resolve => setTimeout(resolve, 500));

        // Mock responses based on endpoint
        switch (endpoint) {
            case '/auth/login':
                if (config.body?.username === 'superadmin') {
                    return {
                        success: true,
                        data: {
                            user: {
                                id: 'SA001',
                                name: 'Super Admin',
                                role: 'super_admin'
                            },
                            token: 'demo-jwt-token'
                        }
                    };
                }
                throw new Error('Invalid credentials');

            case '/users':
                return {
                    success: true,
                    data: this.getMockUsers()
                };

            case '/hotels':
                return {
                    success: true,
                    data: this.getMockHotels()
                };

            case '/reports/summary':
                return {
                    success: true,
                    data: this.getMockReports()
                };

            default:
                return {
                    success: true,
                    data: { message: 'Request successful' }
                };
        }
    }

    getMockUsers() {
        return [
            {
                id: 'U001',
                name: 'John Doe',
                email: 'john@hotel.com',
                role: 'hotel_manager',
                status: 'active',
                createdAt: '2024-01-01',
                lastLogin: '2024-01-15 14:30:00'
            },
            {
                id: 'U002',
                name: 'Jane Smith',
                email: 'jane@restaurant.com',
                role: 'restaurant_manager',
                status: 'active',
                createdAt: '2024-01-02',
                lastLogin: '2024-01-15 10:15:00'
            }
        ];
    }

    getMockHotels() {
        return [
            {
                id: 'H001',
                name: 'Grand Luxury Hotel',
                location: 'New York',
                rooms: 150,
                occupancy: 85,
                status: 'active'
            }
        ];
    }

    getMockReports() {
        return {
            revenue: {
                monthly: 124580,
                weekly: 28560,
                daily: 4120
            },
            bookings: {
                total: 2847,
                confirmed: 2456,
                pending: 391
            },
            users: {
                total: 156,
                active: 142,
                new: 12
            }
        };
    }

    // Specific API methods
    async login(credentials) {
        return this.request('/auth/login', {
            method: 'POST',
            body: JSON.stringify(credentials)
        });
    }

    async getUsers(params = {}) {
        const query = new URLSearchParams(params).toString();
        return this.request(`/users?${query}`);
    }

    async createUser(userData) {
        return this.request('/users', {
            method: 'POST',
            body: JSON.stringify(userData)
        });
    }

    async getReports(dateRange) {
        return this.request('/reports/summary', {
            method: 'POST',
            body: JSON.stringify(dateRange)
        });
    }

    async backupSystem() {
        return this.request('/system/backup', {
            method: 'POST'
        });
    }

    async getAuditLogs(filters) {
        const query = new URLSearchParams(filters).toString();
        return this.request(`/audit/logs?${query}`);
    }
}

// Initialize everything when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    // Initialize auth manager
    window.authManager = new AuthManager();

    // Initialize dashboard if on dashboard page (already logged in)
    if (document.getElementById('dashboardPage').classList.contains('active')) {
        window.dashboardManager = new DashboardManager();
    }

    // Create global API instance
    window.apiService = new APIService();
});
