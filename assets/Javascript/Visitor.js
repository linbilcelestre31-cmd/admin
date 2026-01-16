// JavaScript for the Visitor Management System

// Data storage (in a real application, this would be a database)
const API_BASE_URL = '../integ/Vistor.php';
let hotelVisitors = JSON.parse(localStorage.getItem('hotelVisitors')) || [];
let restaurantVisitors = JSON.parse(localStorage.getItem('restaurantVisitors')) || [];
let settings = JSON.parse(localStorage.getItem('visitorSettings')) || {
    businessName: "Hotel & Restaurant",
    timezone: "UTC",
    dataRetention: 365
};

// Initialize the application
document.addEventListener('DOMContentLoaded', function () {
    // Set up navigation
    setupNavigation();

    // Set up tabs
    setupTabs();

    // Set up form submissions
    setupForms();

    // Initialize dashboard
    updateDashboard();

    // Load all data
    loadCurrentVisitors();
    loadHistory();

    // Load employees for host selection
    loadEmployeesForHosts();

    // Apply settings
    applySettings();
});

// Navigation setup
function setupNavigation() {
    const navLinks = document.querySelectorAll('.nav-link');
    const sidebarLinks = document.querySelectorAll('.sidebar-link');

    function handleNavigation(pageId) {
        showPage(pageId);

        // Update Nav Links
        navLinks.forEach(l => {
            const navPage = l.getAttribute('data-page');
            const mainPagePrefix = pageId.split('-')[0];
            if (navPage === pageId || navPage === mainPagePrefix || (navPage.includes('-') && navPage.startsWith(mainPagePrefix))) {
                l.classList.add('active');
            } else {
                l.classList.remove('active');
            }
        });

        // Update Sidebar Links
        sidebarLinks.forEach(l => {
            if (l.getAttribute('data-page') === pageId) {
                l.classList.add('active');
            } else {
                l.classList.remove('active');
            }
        });
    }

    navLinks.forEach(link => {
        link.addEventListener('click', function (e) {
            const pageId = this.getAttribute('data-page');
            if (!pageId) return;
            e.preventDefault();
            handleNavigation(pageId);
        });
    });

    sidebarLinks.forEach(link => {
        link.addEventListener('click', function (e) {
            const pageId = this.getAttribute('data-page');
            if (!pageId) return;
            e.preventDefault();
            handleNavigation(pageId);
        });
    });
}

// Tab navigation setup
function setupTabs() {
    const tabs = document.querySelectorAll('.tab');
    tabs.forEach(tab => {
        tab.addEventListener('click', function () {
            const tabId = this.getAttribute('data-tab');
            const parent = this.closest('.page');

            // Update active tab UI
            const siblingTabs = parent.querySelectorAll('.tab');
            siblingTabs.forEach(t => t.classList.remove('active'));
            this.classList.add('active');

            // Show corresponding content tab
            const tabContents = parent.querySelectorAll('.tab-content');
            tabContents.forEach(content => {
                if (content.id === `${tabId}-tab`) {
                    content.classList.add('active');
                } else {
                    content.classList.remove('active');
                }
            });

            // Trigger data refresh if needed for specific tabs
            if (tabId.includes('visitors')) {
                loadCurrentVisitors();
            } else if (tabId.includes('history')) {
                loadHistory();
            }
        });
    });
}

// Show report date range based on selection
const reportType = document.getElementById('report-type');
if (reportType) {
    reportType.addEventListener('change', function () {
        const customRange = document.getElementById('custom-date-range');
        if (this.value === 'custom') {
            customRange.style.display = 'block';
        } else {
            customRange.style.display = 'none';
        }
    });
}

// Show specific page
function showPage(pageId) {
    // Hide all pages
    const pages = document.querySelectorAll('.page');
    pages.forEach(page => page.classList.remove('active'));

    // Show requested page
    const targetPage = document.getElementById(pageId);
    if (targetPage) {
        targetPage.classList.add('active');

        // Load data if needed
        if (pageId === 'dashboard') {
            updateDashboard();
        } else if (pageId === 'hotel' || pageId === 'restaurant') {
            loadCurrentVisitors();
            loadHistory();
        }
    }
}

// Setup form submissions
function setupForms() {
    // Hotel check-in form
    const hotelCheckinForm = document.getElementById('hotel-checkin-form');
    if (hotelCheckinForm) {
        hotelCheckinForm.addEventListener('submit', function (e) {
            e.preventDefault();
            timeInHotelGuest();
        });
    }

    // Restaurant check-in form
    const restaurantCheckinForm = document.getElementById('restaurant-checkin-form');
    if (restaurantCheckinForm) {
        restaurantCheckinForm.addEventListener('submit', function (e) {
            e.preventDefault();
            timeInRestaurantVisitor();
        });
    }

    // Report form
    const reportForm = document.getElementById('report-form');
    if (reportForm) {
        reportForm.addEventListener('submit', function (e) {
            e.preventDefault();
            generateReport();
        });
    }

    // Settings form
    const settingsForm = document.getElementById('settings-form');
    if (settingsForm) {
        settingsForm.addEventListener('submit', function (e) {
            e.preventDefault();
            saveSettings();
        });
    }
}

// Time-in hotel guest
function timeInHotelGuest() {
    const form = document.getElementById('hotel-checkin-form');
    const formData = new FormData(form);

    // Prepare data for API
    const requestData = {
        full_name: formData.get('full_name'),
        email: formData.get('email'),
        phone: formData.get('phone'),
        room_number: formData.get('room_number'),
        host_id: formData.get('host_id'),
        time_in: formData.get('time_in'),
        notes: formData.get('notes')
    };

    fetch(API_BASE_URL, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(requestData)
    })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                showAlert('Guest time-in recorded successfully!', 'success');
                form.reset();
                updateDashboard();
                loadCurrentVisitors();
            } else {
                showAlert('Error: ' + data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('An error occurred while saving data.', 'error');
        });
}

// Time-in restaurant visitor
function timeInRestaurantVisitor() {
    const form = document.getElementById('restaurant-checkin-form');
    const formData = new FormData(form);

    const visitor = {
        id: Date.now(),
        name: formData.get('visitor-name'),
        phone: formData.get('visitor-phone'),
        partySize: parseInt(formData.get('party-size')),
        table: formData.get('table-number'),
        host: formData.get('restaurant-host'),
        notes: formData.get('restaurant-notes'),
        status: 'timed-in',
        checkinTime: new Date().toISOString(),
        checkoutTime: null
    };

    restaurantVisitors.push(visitor);
    localStorage.setItem('restaurantVisitors', JSON.stringify(restaurantVisitors));

    // Show success message
    showAlert('Visitor time-in recorded successfully!', 'success');

    // Reset form
    form.reset();

    // Update dashboard and tables
    updateDashboard();
    loadCurrentVisitors();
}

// Time-out hotel guest
// Time-out hotel guest
function timeOutHotelGuest(guestId) {
    showConfirmationModal('Are you sure you want to time-out this guest?', function () {
        // Check if it's an external record (starts with 'ext_')
        if (String(guestId).startsWith('ext_')) {
            alert('Cannot time-out external records. Please use the core system.');
            return;
        }

        fetch(API_BASE_URL, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'checkout',
                id: guestId
            })
        })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    showAlert('Guest time-out recorded successfully!', 'success');
                    loadCurrentVisitors(); // Refresh table
                } else {
                    showAlert('Error: ' + data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('An error occurred during time-out.', 'error');
            });
    });
}

// Time-out restaurant visitor
function timeOutRestaurantVisitor(visitorId) {
    const visitor = restaurantVisitors.find(v => v.id === visitorId);
    if (visitor) {
        visitor.status = 'timed-out';
        visitor.checkoutTime = new Date().toISOString();
        localStorage.setItem('restaurantVisitors', JSON.stringify(restaurantVisitors));

        showAlert('Visitor time-out recorded successfully!', 'success');
        updateDashboard();
        loadCurrentVisitors();
    }
}

// Update dashboard statistics
function updateDashboard() {
    const today = new Date().toDateString();

    // Hotel statistics
    const hotelToday = hotelVisitors.filter(guest => {
        const checkinDate = new Date(guest.checkinTime).toDateString();
        return checkinDate === today;
    }).length;

    const hotelCurrent = hotelVisitors.filter(guest => guest.status === 'timed-in').length;

    // Restaurant statistics
    const restaurantToday = restaurantVisitors.filter(visitor => {
        const checkinDate = new Date(visitor.checkinTime).toDateString();
        return checkinDate === today;
    }).length;

    const restaurantCurrent = restaurantVisitors.filter(visitor => visitor.status === 'timed-in').length;

    // Update DOM
    document.getElementById('hotel-today').textContent = hotelToday;
    document.getElementById('hotel-current').textContent = hotelCurrent;
    document.getElementById('restaurant-today').textContent = restaurantToday;
    document.getElementById('restaurant-current').textContent = restaurantCurrent;

    // Update recent activity
    updateRecentActivity();
}

// Update recent activity list
function updateRecentActivity() {
    const activityContainer = document.getElementById('recent-activity');
    if (!activityContainer) return;

    // Combine recent activities from both hotel and restaurant
    const allActivities = [
        ...hotelVisitors.map(guest => ({
            type: 'hotel',
            name: guest.name,
            action: guest.status === 'timed-in' ? 'time-in' : 'time-out',
            time: guest.status === 'timed-in' ? guest.checkinTime : guest.checkoutTime,
            details: `Room ${guest.room}`
        })),
        ...restaurantVisitors.map(visitor => ({
            type: 'restaurant',
            name: visitor.name,
            action: visitor.status === 'timed-in' ? 'time-in' : 'time-out',
            time: visitor.status === 'timed-in' ? visitor.checkinTime : visitor.checkoutTime,
            details: `Table ${visitor.table}, Party of ${visitor.partySize}`
        }))
    ];

    // Sort by time (newest first)
    allActivities.sort((a, b) => new Date(b.time) - new Date(a.time));

    // Get top 5
    const recentActivities = allActivities.slice(0, 5);

    // Update DOM
    activityContainer.innerHTML = '';
    if (recentActivities.length === 0) {
        activityContainer.innerHTML = '<p>No recent activity</p>';
        return;
    }

    recentActivities.forEach(activity => {
        const activityEl = document.createElement('div');
        activityEl.className = 'activity-item';
        activityEl.innerHTML = `
                    <strong>${activity.name}</strong> ${activity.action} at the ${activity.type}
                    <br><small>${activity.details} • ${formatTime(activity.time)}</small>
                    <hr>
                `;
        activityContainer.appendChild(activityEl);
    });
}

// Load current visitors into tables
function loadCurrentVisitors() {
    // 1. Hotel current visitors (from API)
    const hotelCurrentTable = document.getElementById('hotel-current-table');
    if (hotelCurrentTable) {
        const tbody = hotelCurrentTable.querySelector('tbody');
        fetch(API_BASE_URL)
            .then(response => response.json())
            .then(data => {
                tbody.innerHTML = '';
                if (data.status === 'success' && data.data && data.data.length > 0) {
                    data.data.forEach(guest => {
                        const row = document.createElement('tr');
                        row.innerHTML = `
                            <td>${guest.full_name || 'N/A'}</td>
                            <td>${guest.room_number || 'N/A'}</td>
                            <td>${formatDate(guest.checkin_date)}</td>
                            <td>${guest.checkout_date ? formatDate(guest.checkout_date) : 'Inside'}</td>
                            <td style="display: flex; gap: 8px;">
                                <button class="btn-action-view" onclick="viewVisitorDetails('${guest.id}')">
                                    <i class="fas fa-eye"></i> View
                                </button>
                                <button class="btn-action-timeout" onclick="timeOutHotelGuest('${guest.id}')">
                                    <i class="fas fa-sign-out-alt"></i> Time-out
                                </button>
                            </td>
                        `;
                        tbody.appendChild(row);
                    });
                } else {
                    tbody.innerHTML = '<tr><td colspan="5" style="text-align: center;">No current guests</td></tr>';
                }
            })
            .catch(error => {
                console.error('Error fetching visitors:', error);
                tbody.innerHTML = '<tr><td colspan="5" style="text-align: center;">Error loading data</td></tr>';
            });
    }

    // 2. Restaurant current visitors (from localStorage)
    const restaurantCurrentTable = document.getElementById('restaurant-current-table');
    if (restaurantCurrentTable) {
        const tbody = restaurantCurrentTable.querySelector('tbody');
        tbody.innerHTML = '';
        const currentVisitors = restaurantVisitors.filter(visitor => visitor.status === 'timed-in');

        if (currentVisitors.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" style="text-align: center;">No current visitors</td></tr>';
        } else {
            currentVisitors.forEach(visitor => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${visitor.name}</td>
                    <td>${visitor.partySize}</td>
                    <td>${visitor.table}</td>
                    <td>${formatTime(visitor.checkinTime)}</td>
                    <td>
                        <button class="btn-action-timeout" onclick="timeOutRestaurantVisitor(${visitor.id})">
                            <i class="fas fa-sign-out-alt"></i> Time-out
                        </button>
                    </td>
                `;
                tbody.appendChild(row);
            });
        }
    }
}

// Load visitor history into tables
function loadHistory() {
    // 1. Hotel History (Mock/Local for now, or you can fetch all from API)
    const hotelHistoryTable = document.getElementById('hotel-history-table');
    if (hotelHistoryTable) {
        const tbody = hotelHistoryTable.querySelector('tbody');
        tbody.innerHTML = '';

        // For demonstration, let's use the local array which might be empty if using only API
        if (hotelVisitors.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" style="text-align: center;">No history records</td></tr>';
        } else {
            hotelVisitors.forEach(guest => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${guest.name}</td>
                    <td>${guest.room}</td>
                    <td>${formatDate(guest.checkin)}</td>
                    <td>${guest.checkout ? formatDate(guest.checkout) : 'N/A'}</td>
                    <td><span class="status-badge status-${guest.status.replace('-', '')}">${guest.status}</span></td>
                `;
                tbody.appendChild(row);
            });
        }
    }

    // 2. Restaurant History
    const restaurantHistoryTable = document.getElementById('restaurant-history-table');
    if (restaurantHistoryTable) {
        const tbody = restaurantHistoryTable.querySelector('tbody');
        tbody.innerHTML = '';

        if (restaurantVisitors.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" style="text-align: center;">No history records</td></tr>';
        } else {
            restaurantVisitors.forEach(visitor => {
                const row = document.createElement('tr');
                const checkOutTime = visitor.checkoutTime ? formatTime(visitor.checkoutTime) : 'N/A';
                row.innerHTML = `
                    <td>${visitor.name}</td>
                    <td>${visitor.partySize}</td>
                    <td>${visitor.table}</td>
                    <td>${formatTime(visitor.checkinTime)}</td>
                    <td>${checkOutTime}</td>
                `;
                tbody.appendChild(row);
            });
        }
    }
}

// Function to view visitor details in a modal
function viewVisitorDetails(guestId) {
    fetch(API_BASE_URL)
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success' && data.data) {
                const guest = data.data.find(g => String(g.id) === String(guestId));
                if (guest) {
                    const detailsHtml = `
                    <div style="text-align: left; line-height: 1.6;">
                        <p><strong>Name:</strong> ${guest.full_name}</p>
                        <p><strong>Room:</strong> ${guest.room_number || 'N/A'}</p>
                        <p><strong>Email:</strong> ${guest.email || 'N/A'}</p>
                        <p><strong>Phone:</strong> ${guest.phone_number || 'N/A'}</p>
                        <p><strong>Check-in:</strong> ${guest.checkin_date}</p>
                        <p><strong>Status:</strong> ${guest.status}</p>
                        <p><strong>Notes:</strong> ${guest.notes || 'None'}</p>
                    </div>
                `;
                    // Use the custom details modal function we will create
                    if (typeof showDetailsModal === 'function') {
                        showDetailsModal('Visitor Details', detailsHtml);
                    } else {
                        alert('Visitor: ' + guest.full_name);
                    }
                }
            }
        });
}







// Generate reports
function generateReport() {
    const reportType = document.getElementById('report-type').value;
    const venue = document.getElementById('report-venue').value;
    const startDate = document.getElementById('start-date').value;
    const endDate = document.getElementById('end-date').value;

    let reportData = '';

    // Calculate date range based on report type
    let dateRange = { start: new Date(), end: new Date() };

    switch (reportType) {
        case 'daily':
            dateRange.start.setHours(0, 0, 0, 0);
            dateRange.end.setHours(23, 59, 59, 999);
            break;
        case 'weekly':
            // Start of week (Sunday)
            dateRange.start.setDate(dateRange.start.getDate() - dateRange.start.getDay());
            dateRange.start.setHours(0, 0, 0, 0);
            // End of week (Saturday)
            dateRange.end.setDate(dateRange.start.getDate() + 6);
            dateRange.end.setHours(23, 59, 59, 999);
            break;
        case 'monthly':
            // Start of month
            dateRange.start.setDate(1);
            dateRange.start.setHours(0, 0, 0, 0);
            // End of month
            dateRange.end.setMonth(dateRange.end.getMonth() + 1, 0);
            dateRange.end.setHours(23, 59, 59, 999);
            break;
        case 'custom':
            dateRange.start = new Date(startDate);
            dateRange.end = new Date(endDate);
            dateRange.end.setHours(23, 59, 59, 999);
            break;
    }

    // Filter data based on venue and date range
    let hotelData = [];
    let restaurantData = [];

    if (venue === 'all' || venue === 'hotel') {
        hotelData = hotelVisitors.filter(guest => {
            const checkinDate = new Date(guest.checkinTime);
            return checkinDate >= dateRange.start && checkinDate <= dateRange.end;
        });
    }

    if (venue === 'all' || venue === 'restaurant') {
        restaurantData = restaurantVisitors.filter(visitor => {
            const checkinDate = new Date(visitor.checkinTime);
            return checkinDate >= dateRange.start && checkinDate <= dateRange.end;
        });
    }

    // Generate report content
    reportData += `<h3>Report for ${formatDate(dateRange.start)} to ${formatDate(dateRange.end)}</h3>`;

    if (venue === 'all' || venue === 'hotel') {
        reportData += `<h4>Hotel Statistics</h4>`;
        reportData += `<p>Total Guests: ${hotelData.length}</p>`;
        reportData += `<p>Currently Time-in: ${hotelData.filter(g => g.status === 'timed-in').length}</p>`;

        if (hotelData.length > 0) {
            reportData += `<table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Room</th>
                                <th>Check-in</th>
                                <th>Check-out</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>`;

            hotelData.forEach(guest => {
                reportData += `
                            <tr>
                                <td>${guest.name}</td>
                                <td>${guest.room}</td>
                                <td>${formatDate(guest.checkin)}</td>
                                <td>${formatDate(guest.checkout)}</td>
                                <td>${guest.status}</td>
                            </tr>`;
            });

            reportData += `</tbody></table>`;
        }
    }

    if (venue === 'all' || venue === 'restaurant') {
        reportData += `<h4>Restaurant Statistics</h4>`;
        reportData += `<p>Total Visitors: ${restaurantData.length}</p>`;
        reportData += `<p>Total Covers: ${restaurantData.reduce((sum, visitor) => sum + visitor.partySize, 0)}</p>`;
        reportData += `<p>Currently Dining (Time-in): ${restaurantData.filter(v => v.status === 'timed-in').length}</p>`;

        if (restaurantData.length > 0) {
            reportData += `<table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Party Size</th>
                                <th>Table</th>
                                <th>Time-in</th>
                                <th>Time-out</th>
                            </tr>
                        </thead>
                        <tbody>`;

            restaurantData.forEach(visitor => {
                reportData += `
                            <tr>
                                <td>${visitor.name}</td>
                                <td>${visitor.partySize}</td>
                                <td>${visitor.table}</td>
                                <td>${formatTime(visitor.checkinTime)}</td>
                                <td>${visitor.checkoutTime ? formatTime(visitor.checkoutTime) : 'N/A'}</td>
                            </tr>`;
            });

            reportData += `</tbody></table>`;
        }
    }

    // Display report
    document.getElementById('report-data').innerHTML = reportData;
    document.getElementById('report-results').style.display = 'block';
}

// Save settings
function saveSettings() {
    const form = document.getElementById('settings-form');
    const formData = new FormData(form);

    settings.businessName = formData.get('business-name');
    settings.timezone = formData.get('timezone');
    settings.dataRetention = parseInt(formData.get('data-retention'));

    localStorage.setItem('visitorSettings', JSON.stringify(settings));

    showAlert('Settings saved successfully!', 'success');
    applySettings();
}

// Apply settings
function applySettings() {
    // Update business name in UI if needed
    const businessNameEl = document.querySelector('.logo');
    if (businessNameEl && settings.businessName) {
        businessNameEl.textContent = `${settings.businessName} Visitor Management`;
    }

    // Populate settings form
    const businessNameInput = document.getElementById('business-name');
    const timezoneSelect = document.getElementById('timezone');
    const dataRetentionInput = document.getElementById('data-retention');

    if (businessNameInput) businessNameInput.value = settings.businessName;
    if (timezoneSelect) timezoneSelect.value = settings.timezone;
    if (dataRetentionInput) dataRetentionInput.value = settings.dataRetention;
}

// Load employees for host selection from HR4 API
function loadEmployeesForHosts() {
    const hr4ApiUrl = '../integ/hr4_api.php?limit=10';
    const hostSelects = [
        document.getElementById('host_id'),
        document.getElementById('restaurant-host')
    ];

    fetch(hr4ApiUrl)
        .then(response => response.json())
        .then(res => {
            if (res.success && res.data) {
                hostSelects.forEach(select => {
                    if (!select) return;

                    // Clear existing options except first
                    const firstOption = select.options[0];
                    select.innerHTML = '';
                    select.appendChild(firstOption);

                    res.data.forEach(employee => {
                        const option = document.createElement('option');
                        option.value = employee.employee_id || employee.id;
                        const pos = employee.employment_details ? (employee.employment_details.job_title || 'Employee') : (employee.position || 'Employee');
                        option.textContent = `${employee.first_name} ${employee.last_name} (${pos})`;
                        select.appendChild(option);
                    });
                });
            }
        })
        .catch(error => {
            console.error('Error loading employees for hosts:', error);
        });
}

// Utility functions
function formatDate(dateString) {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    return date.toLocaleDateString();
}

function formatTime(dateString) {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

function showAlert(message, type) {
    // Remove existing alerts
    const existingAlerts = document.querySelectorAll('.alert');
    existingAlerts.forEach(alert => alert.remove());

    // Create new alert
    const alert = document.createElement('div');
    alert.className = `alert alert-${type}`;
    alert.textContent = message;

    // Insert at the top of the current page
    const currentPage = document.querySelector('.page.active');
    if (currentPage) {
        currentPage.insertBefore(alert, currentPage.firstChild);
    }

    // Auto remove after 5 seconds
    setTimeout(() => {
        if (alert.parentNode) {
            alert.parentNode.removeChild(alert);
        }
    }, 5000);
}