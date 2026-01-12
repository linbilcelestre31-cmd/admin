<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hotel & Restaurant Financial Management</title>
    <link rel="icon" type="image/x-icon" href="../assets/image/logo2.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #2c3e50;
            --secondary: #3498db;
            --success: #2ecc71;
            --danger: #e74c3c;
            --warning: #f39c12;
            --light: #ecf0f1;
            --dark: #34495e;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: #f5f7fa;
            color: #333;
            line-height: 1.6;
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar Styles */
        .sidebar {
            width: 280px;
            background: linear-gradient(180deg, #1a365d 0%, #2d3748 100%);
            color: white;
            padding: 0;
            box-shadow: 4px 0 20px rgba(0, 0, 0, 0.1);
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            overflow-x: hidden;
            z-index: 100;
            left: 0;
            top: 0;
        }

        .sidebar::-webkit-scrollbar {
            width: 6px;
        }

        .sidebar::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.05);
        }

        .sidebar::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.2);
            border-radius: 3px;
        }

        .sidebar::-webkit-scrollbar-thumb:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        /* Sidebar Header with Burger & Back Button */
        .sidebar-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.75rem 0.5rem;
            gap: 0.5rem;
            background: rgba(0, 0, 0, 0.2);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .sidebar-burger-btn,
        .sidebar-back-btn {
            background: none;
            border: none;
            color: white;
            cursor: pointer;
            padding: 0.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            transition: all 0.3s ease;
            font-size: 0.9rem;
            font-weight: 600;
            min-width: 44px;
            height: 44px;
        }

        .sidebar-burger-btn:hover,
        .sidebar-back-btn:hover {
            background: rgba(255, 255, 255, 0.15);
            transform: scale(1.05);
        }

        .sidebar-burger-btn:active,
        .sidebar-back-btn:active {
            transform: scale(0.95);
        }

        /* Burger Menu Lines */
        .burger-line {
            display: block;
            width: 22px;
            height: 2.5px;
            background: currentColor;
            margin: 4px 0;
            border-radius: 2px;
            transition: all 0.3s ease;
        }

        .sidebar-burger-btn:hover .burger-line {
            background: #fbbf24;
        }

        .logo-area {
            padding: 1.5rem 1rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            background: rgba(255, 255, 255, 0.05);
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100px;
            flex: 1;
        }

        .logo-link {
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .logo {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            font-size: 1.25rem;
            font-weight: 700;
            transition: transform 0.3s ease;
            width: 100%;
        }

        .logo:hover {
            transform: scale(1.05);
        }

        .logo img {
            transition: filter 0.3s ease;
            max-width: 100%;
            height: auto;
            object-fit: contain;
        }

        .logo:hover img {
            filter: drop-shadow(0 0 10px rgba(251, 191, 36, 0.5));
        }

        .nav-section {
            padding: 1.5rem 0;
        }

        .nav-title {
            padding: 0.5rem 1.5rem 0.75rem;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: #94a3b8;
            font-weight: 700;
            margin-top: 0.5rem;
        }

        .nav-links {
            list-style: none;
        }

        .nav-links li {
            margin: 0.25rem 0.75rem;
            list-style: none;
        }

        .nav-links a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 0.875rem 1.25rem;
            color: #cbd5e0;
            text-decoration: none;
            border-radius: 10px;
            transition: all 0.3s ease;
            font-weight: 500;
            cursor: pointer;
            position: relative;
            width: 100%;
        }

        .nav-links a:hover {
            background: rgba(255, 255, 255, 0.15);
            color: white;
            transform: translateX(5px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }

        .nav-links a.active {
            background: linear-gradient(135deg, #3182ce 0%, #2563eb 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(49, 130, 206, 0.4);
            font-weight: 600;
            transform: translateX(5px);
        }

        .nav-links a.active::before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 4px;
            height: 60%;
            background: #fbbf24;
            border-radius: 0 4px 4px 0;
        }

        /* Main Content Container */
        .main-wrapper {
            flex: 1;
            margin-left: 280px;
            display: flex;
            flex-direction: column;
            width: calc(100% - 280px);
        }

        header {
            background: linear-gradient(135deg, var(--primary), var(--dark));
            color: white;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 90;
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
            gap: 20px;
        }

        .mobile-menu-btn {
            display: none;
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            padding: 0.5rem;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 50px;
            flex: 1;
            width: 100%;
        }

        header {
            background: linear-gradient(135deg, var(--primary), var(--dark));
            color: white;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 0;
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .logo i {
            font-size: 2rem;
        }

        .logo h1 {
            font-size: 1.8rem;
            font-weight: 600;
        }

        .nav-tabs {
            display: flex;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }

        .nav-tab {
            flex: 1;
            padding: 15px 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 600;
            color: var(--dark);
        }

        .nav-tab.active {
            background: var(--secondary);
            color: white;
        }

        .nav-tab:hover:not(.active) {
            background: var(--light);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .dashboard-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            text-align: center;
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-card.income {
            border-left: 5px solid var(--success);
        }

        .stat-card.expense {
            border-left: 5px solid var(--danger);
        }

        .stat-card.net {
            border-left: 5px solid var(--secondary);
        }

        .stat-card.journal {
            border-left: 5px solid var(--warning);
        }

        .stat-card h3 {
            font-size: 0.9rem;
            color: #7f8c8d;
            margin-bottom: 10px;
        }

        .stat-card .value {
            font-size: 1.8rem;
            font-weight: 700;
        }

        .controls {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .filters {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }

        select,
        input,
        button {
            padding: 10px 15px;
            border-radius: 5px;
            border: 1px solid #ddd;
            font-size: 0.9rem;
        }

        button {
            background-color: var(--secondary);
            color: white;
            border: none;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        button:hover {
            background-color: #2980b9;
        }

        .btn-add {
            background-color: var(--success);
        }

        .btn-add:hover {
            background-color: #27ae60;
        }

        .btn-journal {
            background-color: var(--warning);
        }

        .btn-journal:hover {
            background-color: #e67e22;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background-color: white;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 30px;
        }

        th,
        td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        th {
            background-color: var(--primary);
            color: white;
            font-weight: 600;
        }

        tr:hover {
            background-color: #f9f9f9;
        }

        .type-income {
            color: var(--success);
            font-weight: 600;
        }

        .type-expense {
            color: var(--danger);
            font-weight: 600;
        }

        .debit {
            color: var(--success);
            font-weight: 600;
        }

        .credit {
            color: var(--danger);
            font-weight: 600;
        }

        .actions {
            display: flex;
            gap: 10px;
        }

        .btn-edit {
            background-color: var(--warning);
            padding: 5px 10px;
            font-size: 0.8rem;
        }

        .btn-delete {
            background-color: var(--danger);
            padding: 5px 10px;
            font-size: 0.8rem;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            background-color: white;
            padding: 30px;
            border-radius: 10px;
            width: 90%;
            max-width: 800px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }

        .modal-header h2 {
            color: var(--primary);
        }

        .close {
            font-size: 1.5rem;
            cursor: pointer;
            color: #7f8c8d;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--dark);
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
        }

        .form-group textarea {
            height: 100px;
            resize: vertical;
        }

        .form-row {
            display: flex;
            gap: 15px;
        }

        .form-row .form-group {
            flex: 1;
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }

        .chart-container {
            background-color: white;
            border-radius: 10px;
            padding: 20px;
            margin-top: 30px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }

        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .chart-header h2 {
            color: var(--primary);
        }

        .chart {
            height: 300px;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
        }

        .bar-chart {
            width: 100%;
            padding: 20px 0;
        }

        .bar-group {
            display: flex;
            align-items: center;
            margin-bottom: 25px;
            gap: 15px;
        }

        .venue-label {
            min-width: 120px;
            font-weight: 600;
            color: #333;
            text-align: right;
        }

        .bars {
            flex: 1;
            display: grid;
            grid-template-columns: auto auto auto;
            height: 40px;
            gap: 2px;
            border-radius: 5px;
            overflow: hidden;
            background: #f0f0f0;
        }

        .bar {
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0 8px;
            color: white;
            font-weight: 700;
            font-size: 0.85rem;
            transition: all 0.3s ease;
            position: relative;
            white-space: nowrap;
        }

        .bar:hover {
            filter: brightness(1.1);
            z-index: 10;
        }

        .income-bar {
            background-color: var(--success);
            min-width: 60px;
        }

        .expense-bar {
            background-color: var(--danger);
            min-width: 60px;
        }

        .net-bar {
            background-color: var(--secondary);
            min-width: 60px;
        }

        .pie-chart {
            width: 300px;
            height: 300px;
            border-radius: 50%;
            background: conic-gradient(var(--success) 0% 30%,
                    var(--info) 30% 60%,
                    var(--warning) 60% 100%);
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .pie-center {
            width: 120px;
            height: 120px;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
        }

        /* Pie legend */
        .pie-chart-svg {
            gap: 18px;
        }

        .pie-legend {
            display: flex;
            flex-direction: column;
            gap: 8px;
            font-size: 0.95rem;
            color: #0f172a;
        }

        .pie-legend-item {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .pie-legend .swatch {
            width: 14px;
            height: 14px;
            border-radius: 3px;
            display: inline-block;
        }

        footer {
            text-align: center;
            margin-top: 40px;
            padding: 20px;
            color: #7f8c8d;
            font-size: 0.9rem;
        }

        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 15px;
            }

            .controls {
                flex-direction: column;
            }

            .filters {
                width: 100%;
            }

            table {
                display: block;
                overflow-x: auto;
            }

            .form-row {
                flex-direction: column;
                gap: 0;
            }

            .sidebar {
                position: fixed;
                left: -100%;
                z-index: 50;
                transition: left 0.3s ease;
                width: 280px;
                height: 100vh;
            }

            .sidebar.sidebar-open {
                left: 0;
            }

            .mobile-menu-overlay {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0, 0, 0, 0.5);
                z-index: 40;
            }

            .mobile-menu-overlay.sidebar-open {
                display: block;
            }

            .main-wrapper {
                margin-left: 0;
                width: 100%;
            }

            .mobile-menu-btn {
                display: flex;
            }

            header {
                padding: 15px;
            }
        }

        .dates {
            position: relative;
            right: 130%;
            top: 10px;
        }

        .site {
            position: relative;
            right: 10%;
            top: -15px;
            color: #fff;
            text-decoration: none;

            font-weight: bold;
            background-color: #3498db;
            padding: 10px 20px;
            border-radius: 5px;
            transition: background-color 0.3s ease;
        }

        .nav-section {}

        .nav-section-fn {
            position: relative;
            top: 5%;
        }
    </style>
</head>

<body>
    <!-- Mobile Menu Overlay -->

    <!-- Sidebar -->
    <nav class="sidebar">
        <div class="sidebar-header">
            <a href="../Modules/facilities-reservation.php" class="logo-link" title="Go to Dashboard">
                <div class="logo-area">
                    <div class="logo">
                        <img src="../assets/image/logo.png" alt="Atiéra Logo"
                            style="height:80px; width:auto; display:block; margin:0 auto;">
                    </div>
                </div>
            </a>
        </div>
        <div class="nav-section-fn">
            <h1 class="nav-title">Main Navigation</h1>
            <ul class="nav-links">
                <li><a href="#financial" onclick="switchTab('financial'); return false;" class="active">
                        <i class="fas fa-money-bill"></i> Financial Records
                    </a></li>
                <li><a href="#journal" onclick="switchTab('journal'); return false;">
                        <i class="fas fa-book"></i> Journal Entries
                    </a></li>
                <li><a href="#reports" onclick="switchTab('reports'); return false;">
                        <i class="fas fa-chart-bar"></i> Reports & Analytics
                    </a></li>
            </ul>
        </div>

    </nav>

    <!-- Main Content Wrapper -->
    <div class="main-wrapper">
        <!-- Top Header -->
        <header>
            <div class="header-content">
                <div class="logo">

                </div>
                <div class="date-display">
                    <span class="dates" id="current-date"></span>
                    <a class="site" href="../Modules/facilities-reservation.php">back</a>
                </div>
            </div>
        </header>

        <div class="container">


            <!-- Financial Records Tab -->
            <div class="tab-content active" id="financial-tab">
                <div class="dashboard-stats">
                    <div class="stat-card income">
                        <h3>Total Income</h3>
                        <div class="value" id="total-income">$0.00</div>
                    </div>
                    <div class="stat-card expense">
                        <h3>Total Expense</h3>
                        <div class="value" id="total-expense">$0.00</div>
                    </div>
                    <div class="stat-card net">
                        <h3>Net Profit</h3>
                        <div class="value" id="net-profit">$0.00</div>
                    </div>
                    <div class="stat-card journal">
                        <h3>Journal Entries</h3>
                        <div class="value" id="journal-count">0</div>
                    </div>
                </div>

                <div class="controls">
                    <div class="filters">
                        <select id="filter-type">
                            <option value="">All Types</option>
                            <option value="Income">Income</option>
                            <option value="Expense">Expense</option>
                        </select>
                        <select id="filter-venue">
                            <option value="">All Venues</option>
                            <option value="Hotel">Hotel</option>
                            <option value="Restaurant">Restaurant</option>
                            <option value="General">General</option>
                        </select>
                        <input type="date" id="filter-date">
                        <button id="apply-filters">Apply Filters</button>
                        <button id="reset-filters">Reset</button>
                    </div>
                    <button class="btn-add" id="add-transaction">
                        <i class="fas fa-plus"></i> Add Transaction
                    </button>
                </div>

                <div class="table-container">
                    <table id="financial-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Type</th>
                                <th>Category</th>
                                <th>Description</th>
                                <th>Amount</th>
                                <th>Venue</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="financial-table-body">
                            <!-- Data will be populated by JavaScript -->
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Journal Entries Tab -->
            <div class="tab-content" id="journal-tab">
                <div class="dashboard-stats">
                    <div class="stat-card income">
                        <h3>Total Debits</h3>
                        <div class="value" id="total-debits">$0.00</div>
                    </div>
                    <div class="stat-card expense">
                        <h3>Total Credits</h3>
                        <div class="value" id="total-credits">$0.00</div>
                    </div>
                    <div class="stat-card net">
                        <h3>Balance</h3>
                        <div class="value" id="journal-balance">$0.00</div>
                    </div>
                    <div class="stat-card journal">
                        <h3>Journal Entries</h3>
                        <div class="value" id="journal-entries-count">0</div>
                    </div>
                </div>

                <div class="controls">
                    <div class="filters">
                        <select id="filter-journal-account">
                            <option value="">All Accounts</option>
                            <option value="Cash">Cash</option>
                            <option value="Accounts Receivable">Accounts Receivable</option>
                            <option value="Room Revenue">Room Revenue</option>
                            <option value="Food Sales">Food Sales</option>
                            <option value="Payroll">Payroll</option>
                            <option value="Utilities">Utilities</option>
                            <option value="Equipment">Equipment</option>
                        </select>
                        <input type="date" id="filter-journal-date">
                        <button id="apply-journal-filters">Apply Filters</button>
                        <button id="reset-journal-filters">Reset</button>
                        <button id="refresh-journal-data" style="background-color: var(--secondary);">
                            <i class="fas fa-sync-alt"></i> Refresh Data
                        </button>
                    </div>
                    <button class="btn-journal" id="add-journal-entry">
                        <i class="fas fa-plus"></i> Add Journal Entry
                    </button>
                </div>

                <div class="table-container">
                    <table id="journal-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Account</th>
                                <th>Description</th>
                                <th>Debit</th>
                                <th>Credit</th>
                                <th>Reference</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="journal-table-body">
                            <!-- Data will be populated by JavaScript -->
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Reports Tab -->
            <div class="tab-content" id="reports-tab">
                <div class="chart-container">
                    <div class="chart-header">
                        <h2>Financial Overview</h2>
                        <select id="chart-type">
                            <option value="bar">Bar Chart</option>
                            <option value="pie">Pie Chart</option>
                        </select>
                    </div>
                    <div class="chart" id="financial-chart">
                        <!-- Chart will be rendered here -->
                    </div>
                </div>

                <div class="chart-container">
                    <div class="chart-header">
                        <h2>Journal Account Summary</h2>
                    </div>
                    <div class="chart" id="journal-chart">
                        <!-- Journal chart will be rendered here -->
                    </div>
                </div>
            </div>
        </div>

        <!-- Add/Edit Transaction Modal -->
        <div class="modal" id="transaction-modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 id="modal-title">Add Transaction</h2>
                    <span class="close">&times;</span>
                </div>
                <form id="transaction-form">
                    <input type="hidden" id="record-id">

                    <div class="form-group">
                        <label for="transaction-date">Date</label>
                        <input type="date" id="transaction-date" required>
                    </div>

                    <div class="form-group">
                        <label for="transaction-type">Type</label>
                        <select id="transaction-type" required>
                            <option value="">Select Type</option>
                            <option value="Income">Income</option>
                            <option value="Expense">Expense</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="transaction-category">Category</label>
                        <input type="text" id="transaction-category" required>
                    </div>

                    <div class="form-group">
                        <label for="transaction-description">Description</label>
                        <textarea id="transaction-description"></textarea>
                    </div>

                    <div class="form-group">
                        <label for="transaction-amount">Amount</label>
                        <input type="number" id="transaction-amount" step="0.01" required>
                    </div>

                    <div class="form-group">
                        <label for="transaction-venue">Venue</label>
                        <select id="transaction-venue" required>
                            <option value="">Select Venue</option>
                            <option value="Hotel">Hotel</option>
                            <option value="Restaurant">Restaurant</option>
                            <option value="General">General</option>
                        </select>
                    </div>

                    <div class="form-actions">
                        <button type="button" id="cancel-transaction">Cancel</button>
                        <button type="submit" id="save-transaction">Save Transaction</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Add/Edit Journal Entry Modal -->
        <div class="modal" id="journal-modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 id="journal-modal-title">Add Journal Entry</h2>
                    <span class="close">&times;</span>
                </div>
                <form id="journal-form">
                    <input type="hidden" id="journal-id">

                    <div class="form-group">
                        <label for="journal-date">Date</label>
                        <input type="date" id="journal-date" required>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="journal-account">Account</label>
                            <select id="journal-account" required>
                                <option value="">Select Account</option>
                                <option value="Cash">Cash</option>
                                <option value="Accounts Receivable">Accounts Receivable</option>
                                <option value="Room Revenue">Room Revenue</option>
                                <option value="Food Sales">Food Sales</option>
                                <option value="Payroll">Payroll</option>
                                <option value="Utilities">Utilities</option>
                                <option value="Equipment">Equipment</option>
                                <option value="Supplies">Supplies</option>
                                <option value="Maintenance">Maintenance</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="journal-type">Entry Type</label>
                            <select id="journal-type" required>
                                <option value="">Select Type</option>
                                <option value="Debit">Debit</option>
                                <option value="Credit">Credit</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="journal-description">Description</label>
                        <textarea id="journal-description" required></textarea>
                    </div>

                    <div class="form-group">
                        <label for="journal-amount">Amount</label>
                        <input type="number" id="journal-amount" step="0.01" required>
                    </div>

                    <div class="form-group">
                        <label for="journal-reference">Reference Number</label>
                        <input type="text" id="journal-reference">
                    </div>

                    <div class="form-actions">
                        <button type="button" id="cancel-journal">Cancel</button>
                        <button type="submit" id="save-journal">Save Journal Entry</button>
                    </div>
                </form>
            </div>
        </div>

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
                    <button class="btn" onclick="closeDetailsModal()"
                        style="background-color: var(--primary); color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer;">OK</button>
                </div>
            </div>
        </div>

        <script>
            // Modal Helper Functions (Custom Details)
            function showDetailsModal(title, content) {
                document.getElementById('details-modal-title').innerText = title;
                document.getElementById('details-modal-body').innerHTML = content;
                document.getElementById('details-modal').style.display = 'flex';
            }

            function closeDetailsModal() {
                document.getElementById('details-modal').style.display = 'none';
            }

            // Sidebar Toggle Functions
            function toggleSidebar() {
                const sidebar = document.querySelector('.sidebar');
                const overlay = document.querySelector('.mobile-menu-overlay');
                sidebar.classList.toggle('sidebar-open');
                overlay.classList.toggle('sidebar-open');
            }

            function closeSidebar() {
                const sidebar = document.querySelector('.sidebar');
                const overlay = document.querySelector('.mobile-menu-overlay');
                sidebar.classList.remove('sidebar-open');
                overlay.classList.remove('sidebar-open');
            }

            // Switch Tab Function
            function switchTab(tabName) {
                // Remove active class from all tabs
                document.querySelectorAll('.nav-tab').forEach(tab => tab.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));

                // Add active class to clicked tab
                const clickedTab = document.querySelector(`[data-tab="${tabName}"]`);
                if (clickedTab) {
                    clickedTab.classList.add('active');
                }

                // Show corresponding content
                const content = document.getElementById(`${tabName}-tab`);
                if (content) {
                    content.classList.add('active');
                }

                closeSidebar();
            }

            // Set current date
            document.getElementById('current-date').textContent = new Date().toLocaleDateString('en-US', {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });

            // Tab Navigation
            document.querySelectorAll('.nav-tab').forEach(tab => {
                tab.addEventListener('click', () => {
                    // Remove active class from all tabs and contents
                    document.querySelectorAll('.nav-tab').forEach(t => t.classList.remove('active'));
                    document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));

                    // Add active class to clicked tab and corresponding content
                    tab.classList.add('active');
                    document.getElementById(`${tab.dataset.tab}-tab`).classList.add('active');
                });
            });

            // API Configuration
            const API_BASE_URL = 'https://financial.atierahotelandrestaurant.com/journal_entries_api';

            // Sample data for financial records (keeping existing data)
            let financialRecords = [
                {
                    id: 1,
                    transaction_date: '2025-10-24',
                    type: 'Income',
                    category: 'Room Revenue',
                    description: 'Room 101 - Check-out payment',
                    amount: 5500.00,
                    venue: 'Hotel'
                },
                {
                    id: 2,
                    transaction_date: '2025-10-24',
                    type: 'Income',
                    category: 'Food Sales',
                    description: 'Restaurant Dinner Service',
                    amount: 1250.75,
                    venue: 'Restaurant'
                },
                {
                    id: 3,
                    transaction_date: '2025-10-24',
                    type: 'Expense',
                    category: 'Payroll',
                    description: 'October Staff Payroll',
                    amount: 45000.00,
                    venue: 'General'
                },
                {
                    id: 4,
                    transaction_date: '2025-10-23',
                    type: 'Expense',
                    category: 'Utilities',
                    description: 'Electricity bill',
                    amount: 8500.00,
                    venue: 'Hotel'
                },
                {
                    id: 5,
                    transaction_date: '2025-10-23',
                    type: 'Income',
                    category: 'Event Booking',
                    description: 'Grand Ballroom Wedding Deposit',
                    amount: 15000.00,
                    venue: 'Hotel'
                }
            ];

            // Journal entries will be loaded from API
            let journalEntries = [];

            // Fetch journal entries from API
            async function fetchJournalEntries() {
                try {
                    // Show loading indicator
                    const tableBody = document.getElementById('journal-table-body');
                    if (tableBody) {
                        tableBody.innerHTML = '<tr><td colspan="7" style="text-align: center;"><i class="fas fa-spinner fa-spin"></i> Loading journal entries...</td></tr>';
                    }

                    const response = await fetch(API_BASE_URL);

                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }

                    const data = await response.json();

                    if (data.success && data.data) {
                        // Transform API data to match our local format
                        journalEntries = data.data.map(entry => ({
                            id: entry.id,
                            date: entry.entry_date,
                            account: 'Cash', // Default account since API doesn't provide individual line details
                            description: entry.description,
                            debit: entry.total_debit,
                            credit: entry.total_credit,
                            reference: entry.entry_number,
                            status: entry.status,
                            created_at: entry.created_at,
                            updated_at: entry.updated_at
                        }));

                        console.log('Journal entries loaded from API:', journalEntries);
                        return journalEntries;
                    } else {
                        console.error('API response error:', data);
                        throw new Error('Invalid API response format');
                    }
                } catch (error) {
                    console.error('Error fetching journal entries:', error);

                    // Show error message
                    const tableBody = document.getElementById('journal-table-body');
                    if (tableBody) {
                        tableBody.innerHTML = `<tr><td colspan="7" style="text-align: center; color: var(--danger);">
                        <i class="fas fa-exclamation-triangle"></i> Error loading data: ${error.message}
                        <br><small>Using fallback data</small>
                    </td></tr>`;
                    }

                    // Fallback to sample data if API fails
                    journalEntries = [
                        {
                            id: 1,
                            date: '2025-10-24',
                            account: 'Cash',
                            description: 'Received payment for room booking',
                            debit: 5500.00,
                            credit: 0,
                            reference: 'INV-001'
                        },
                        {
                            id: 2,
                            date: '2025-10-24',
                            account: 'Room Revenue',
                            description: 'Room booking revenue',
                            debit: 0,
                            credit: 5500.00,
                            reference: 'INV-001'
                        }
                    ];
                    return journalEntries;
                }
            }

            // Modal elements
            const transactionModal = document.getElementById('transaction-modal');
            const journalModal = document.getElementById('journal-modal');
            const closeBtns = document.querySelectorAll('.close');
            const cancelTransactionBtn = document.getElementById('cancel-transaction');
            const cancelJournalBtn = document.getElementById('cancel-journal');
            const addTransactionBtn = document.getElementById('add-transaction');
            const addJournalBtn = document.getElementById('add-journal-entry');
            const transactionForm = document.getElementById('transaction-form');
            const journalForm = document.getElementById('journal-form');

            // Open modal for adding transaction
            addTransactionBtn.addEventListener('click', () => {
                document.getElementById('modal-title').textContent = 'Add Transaction';
                transactionForm.reset();
                document.getElementById('record-id').value = '';
                transactionModal.style.display = 'flex';
            });

            // Open modal for adding journal entry
            addJournalBtn.addEventListener('click', () => {
                document.getElementById('journal-modal-title').textContent = 'Add Journal Entry';
                journalForm.reset();
                document.getElementById('journal-id').value = '';
                journalModal.style.display = 'flex';
            });

            // Close modals
            function closeTransactionModal() {
                transactionModal.style.display = 'none';
            }

            function closeJournalModal() {
                journalModal.style.display = 'none';
            }

            closeBtns.forEach(btn => {
                btn.addEventListener('click', () => {
                    if (transactionModal.style.display === 'flex') closeTransactionModal();
                    if (journalModal.style.display === 'flex') closeJournalModal();
                });
            });

            cancelTransactionBtn.addEventListener('click', closeTransactionModal);
            cancelJournalBtn.addEventListener('click', closeJournalModal);

            // Close modals when clicking outside
            window.addEventListener('click', (e) => {
                if (e.target === transactionModal) closeTransactionModal();
                if (e.target === journalModal) closeJournalModal();
            });

            // Format currency
            function formatCurrency(amount) {
                return new Intl.NumberFormat('en-US', {
                    style: 'currency',
                    currency: 'USD'
                }).format(amount);
            }

            // Calculate and display financial stats
            function updateFinancialStats() {
                let totalIncome = 0;
                let totalExpense = 0;

                financialRecords.forEach(record => {
                    if (record.type === 'Income') {
                        totalIncome += parseFloat(record.amount);
                    } else {
                        totalExpense += parseFloat(record.amount);
                    }
                });

                const netProfit = totalIncome - totalExpense;

                document.getElementById('total-income').textContent = formatCurrency(totalIncome);
                document.getElementById('total-expense').textContent = formatCurrency(totalExpense);
                document.getElementById('net-profit').textContent = formatCurrency(netProfit);
                document.getElementById('journal-count').textContent = journalEntries.length;
            }

            // Calculate and display journal stats
            function updateJournalStats() {
                let totalDebits = 0;
                let totalCredits = 0;

                journalEntries.forEach(entry => {
                    totalDebits += parseFloat(entry.debit);
                    totalCredits += parseFloat(entry.credit);
                });

                const balance = totalDebits - totalCredits;

                document.getElementById('total-debits').textContent = formatCurrency(totalDebits);
                document.getElementById('total-credits').textContent = formatCurrency(totalCredits);
                document.getElementById('journal-balance').textContent = formatCurrency(balance);
                document.getElementById('journal-entries-count').textContent = journalEntries.length;
            }

            // Render financial records table
            function renderFinancialTable(records = financialRecords) {
                const tableBody = document.getElementById('financial-table-body');
                tableBody.innerHTML = '';

                if (records.length === 0) {
                    tableBody.innerHTML = '<tr><td colspan="7" style="text-align: center;">No records found</td></tr>';
                    return;
                }

                records.forEach(record => {
                    const row = document.createElement('tr');

                    row.innerHTML = `
                    <td>${new Date(record.transaction_date).toLocaleDateString()}</td>
                    <td><span class="type-${record.type.toLowerCase()}">${record.type}</span></td>
                    <td>${record.category}</td>
                    <td>${record.description}</td>
                    <td>${formatCurrency(record.amount)}</td>
                    <td>${record.venue}</td>
                    <td class="actions">
                        <button class="btn-edit" data-id="${record.id}" title="View Details">
                            <i class="fas fa-eye"></i> View
                        </button>
                    </td>
                `;

                    tableBody.appendChild(row);
                });

                // Add event listeners to view buttons
                document.querySelectorAll('.btn-edit').forEach(btn => {
                    btn.addEventListener('click', (e) => {
                        const id = parseInt(e.currentTarget.getAttribute('data-id'));
                        viewTransaction(id);
                    });
                });
            }

            // Render journal entries table
            function renderJournalTable(entries = journalEntries) {
                const tableBody = document.getElementById('journal-table-body');
                tableBody.innerHTML = '';

                if (entries.length === 0) {
                    tableBody.innerHTML = '<tr><td colspan="7" style="text-align: center;">No journal entries found</td></tr>';
                    return;
                }

                entries.forEach(entry => {
                    const row = document.createElement('tr');

                    // Format date properly
                    const entryDate = entry.date ? new Date(entry.date).toLocaleDateString() : 'N/A';

                    row.innerHTML = `
                    <td>${entryDate}</td>
                    <td>${entry.account || 'N/A'}</td>
                    <td>${entry.description || 'N/A'}</td>
                    <td><span class="debit">${entry.debit > 0 ? formatCurrency(entry.debit) : '-'}</span></td>
                    <td><span class="credit">${entry.credit > 0 ? formatCurrency(entry.credit) : '-'}</span></td>
                    <td>${entry.reference || 'N/A'}</td>
                    <td class="actions">
                        <button class="btn-edit" data-id="${entry.id}" title="View Details">
                            <i class="fas fa-eye"></i> View
                        </button>
                    </td>
                `;

                    tableBody.appendChild(row);
                });

                // Add event listeners to view buttons
                document.querySelectorAll('.btn-edit').forEach(btn => {
                    btn.addEventListener('click', (e) => {
                        const id = parseInt(e.currentTarget.getAttribute('data-id'));
                        viewJournalEntry(id);
                    });
                });
            }

            // View transaction details
            function viewTransaction(id) {
                const record = financialRecords.find(r => r.id === id);
                if (!record) return;

                const detailsHtml = `
                    <div style="line-height: 1.6;">
                        <p><strong>ID:</strong> ${record.id}</p>
                        <p><strong>Date:</strong> ${record.transaction_date}</p>
                        <p><strong>Type:</strong> ${record.type}</p>
                        <p><strong>Category:</strong> ${record.category}</p>
                        <p><strong>Description:</strong> ${record.description}</p>
                        <p><strong>Amount:</strong> ${formatCurrency(record.amount)}</p>
                        <p><strong>Venue:</strong> ${record.venue}</p>
                    </div>
                `;

                showDetailsModal('Transaction Details', detailsHtml);
            }

            // Edit transaction
            function editTransaction(id) {
                const record = financialRecords.find(r => r.id === id);
                if (!record) return;

                document.getElementById('modal-title').textContent = 'Edit Transaction';
                document.getElementById('record-id').value = record.id;
                document.getElementById('transaction-date').value = record.transaction_date;
                document.getElementById('transaction-type').value = record.type;
                document.getElementById('transaction-category').value = record.category;
                document.getElementById('transaction-description').value = record.description;
                document.getElementById('transaction-amount').value = record.amount;
                document.getElementById('transaction-venue').value = record.venue;

                transactionModal.style.display = 'flex';
            }

            // View journal entry details
            function viewJournalEntry(id) {
                const entry = journalEntries.find(e => e.id === id);
                if (!entry) return;

                const detailsHtml = `
                    <div style="line-height: 1.6;">
                        <p><strong>ID:</strong> ${entry.id}</p>
                        <p><strong>Date:</strong> ${entry.date}</p>
                        <p><strong>Account:</strong> ${entry.account}</p>
                        <p><strong>Description:</strong> ${entry.description}</p>
                        <p><strong>Debit:</strong> ${formatCurrency(entry.debit)}</p>
                        <p><strong>Credit:</strong> ${formatCurrency(entry.credit)}</p>
                        <p><strong>Reference:</strong> ${entry.reference}</p>
                        <p><strong>Status:</strong> ${entry.status || 'N/A'} </p>
                        <p><strong>Created:</strong> ${entry.created_at || 'N/A'} </p>
                    </div>
                `;

                showDetailsModal('Journal Entry Details', detailsHtml);
            }

            // Edit journal entry
            function editJournalEntry(id) {
                const entry = journalEntries.find(e => e.id === id);
                if (!entry) return;

                document.getElementById('journal-modal-title').textContent = 'Edit Journal Entry';
                document.getElementById('journal-id').value = entry.id;
                document.getElementById('journal-date').value = entry.date;
                document.getElementById('journal-account').value = entry.account;
                document.getElementById('journal-description').value = entry.description;
                document.getElementById('journal-reference').value = entry.reference;

                // Set the type and amount based on debit/credit
                if (entry.debit > 0) {
                    document.getElementById('journal-type').value = 'Debit';
                    document.getElementById('journal-amount').value = entry.debit;
                } else {
                    document.getElementById('journal-type').value = 'Credit';
                    document.getElementById('journal-amount').value = entry.credit;
                }

                journalModal.style.display = 'flex';
            }


            // Save transaction (add or update)
            transactionForm.addEventListener('submit', (e) => {
                e.preventDefault();

                const id = document.getElementById('record-id').value;
                const transactionDate = document.getElementById('transaction-date').value;
                const type = document.getElementById('transaction-type').value;
                const category = document.getElementById('transaction-category').value;
                const description = document.getElementById('transaction-description').value;
                const amount = parseFloat(document.getElementById('transaction-amount').value);
                const venue = document.getElementById('transaction-venue').value;

                if (id) {
                    // Update existing record
                    const index = financialRecords.findIndex(record => record.id === parseInt(id));
                    if (index !== -1) {
                        financialRecords[index] = {
                            id: parseInt(id),
                            transaction_date: transactionDate,
                            type,
                            category,
                            description,
                            amount,
                            venue
                        };
                    }
                } else {
                    // Add new record
                    const newId = financialRecords.length > 0
                        ? Math.max(...financialRecords.map(r => r.id)) + 1
                        : 1;

                    financialRecords.push({
                        id: newId,
                        transaction_date: transactionDate,
                        type,
                        category,
                        description,
                        amount,
                        venue
                    });
                }

                renderFinancialTable();
                updateFinancialStats();
                closeTransactionModal();
            });

            // Save journal entry (add or update)
            journalForm.addEventListener('submit', (e) => {
                e.preventDefault();

                const id = document.getElementById('journal-id').value;
                const date = document.getElementById('journal-date').value;
                const account = document.getElementById('journal-account').value;
                const description = document.getElementById('journal-description').value;
                const type = document.getElementById('journal-type').value;
                const amount = parseFloat(document.getElementById('journal-amount').value);
                const reference = document.getElementById('journal-reference').value;

                // Create entry object based on debit/credit
                const entry = {
                    date,
                    account,
                    description,
                    reference,
                    debit: type === 'Debit' ? amount : 0,
                    credit: type === 'Credit' ? amount : 0
                };

                if (id) {
                    // Update existing entry
                    const index = journalEntries.findIndex(e => e.id === parseInt(id));
                    if (index !== -1) {
                        entry.id = parseInt(id);
                        journalEntries[index] = entry;
                    }
                } else {
                    // Add new entry
                    const newId = journalEntries.length > 0
                        ? Math.max(...journalEntries.map(e => e.id)) + 1
                        : 1;

                    entry.id = newId;
                    journalEntries.push(entry);
                }

                renderJournalTable();
                updateJournalStats();
                updateFinancialStats();
                closeJournalModal();
            });

            // Filter functionality for financial records
            document.getElementById('apply-filters').addEventListener('click', applyFilters);
            document.getElementById('reset-filters').addEventListener('click', resetFilters);

            function applyFilters() {
                const typeFilter = document.getElementById('filter-type').value;
                const venueFilter = document.getElementById('filter-venue').value;
                const dateFilter = document.getElementById('filter-date').value;

                let filteredRecords = financialRecords;

                if (typeFilter) {
                    filteredRecords = filteredRecords.filter(record => record.type === typeFilter);
                }

                if (venueFilter) {
                    filteredRecords = filteredRecords.filter(record => record.venue === venueFilter);
                }

                if (dateFilter) {
                    filteredRecords = filteredRecords.filter(record => record.transaction_date === dateFilter);
                }

                renderFinancialTable(filteredRecords);
            }

            function resetFilters() {
                document.getElementById('filter-type').value = '';
                document.getElementById('filter-venue').value = '';
                document.getElementById('filter-date').value = '';
                renderFinancialTable();
            }

            // Filter functionality for journal entries
            document.getElementById('apply-journal-filters').addEventListener('click', applyJournalFilters);
            document.getElementById('reset-journal-filters').addEventListener('click', resetJournalFilters);
            document.getElementById('refresh-journal-data').addEventListener('click', async () => {
                await fetchJournalEntries();
                updateJournalStats();
                renderJournalTable();
                renderJournalChart();
            });

            function applyJournalFilters() {
                const accountFilter = document.getElementById('filter-journal-account').value;
                const dateFilter = document.getElementById('filter-journal-date').value;

                let filteredEntries = journalEntries;

                if (accountFilter) {
                    filteredEntries = filteredEntries.filter(entry => entry.account === accountFilter);
                }

                if (dateFilter) {
                    filteredEntries = filteredEntries.filter(entry => entry.date === dateFilter);
                }

                renderJournalTable(filteredEntries);
            }

            function resetJournalFilters() {
                document.getElementById('filter-journal-account').value = '';
                document.getElementById('filter-journal-date').value = '';
                renderJournalTable();
            }

            // Simple chart implementation
            function renderChart() {
                const chartElement = document.getElementById('financial-chart');
                const chartType = document.getElementById('chart-type').value;

                // Calculate data for chart
                const incomeByVenue = {};
                const expenseByVenue = {};

                financialRecords.forEach(record => {
                    if (record.type === 'Income') {
                        incomeByVenue[record.venue] = (incomeByVenue[record.venue] || 0) + record.amount;
                    } else {
                        expenseByVenue[record.venue] = (expenseByVenue[record.venue] || 0) + record.amount;
                    }
                });

                // Create a simple bar chart visualization
                let chartHTML = '';

                if (chartType === 'bar') {
                    const venues = ['Hotel', 'Restaurant', 'General'];
                    const maxValue = Math.max(
                        ...Object.values(incomeByVenue),
                        ...Object.values(expenseByVenue)
                    );

                    chartHTML = '<div class="bar-chart">';

                    venues.forEach(venue => {
                        const income = incomeByVenue[venue] || 0;
                        const expense = expenseByVenue[venue] || 0;
                        const net = income - expense;

                        chartHTML += `
                        <div class="bar-group">
                            <div class="venue-label">${venue}</div>
                            <div class="bars">
                                <div class="bar income-bar" style="width: ${(income / maxValue) * 100}%">
                                    <span class="bar-label">${formatCurrency(income)}</span>
                                </div>
                                <div class="bar expense-bar" style="width: ${(expense / maxValue) * 100}%">
                                    <span class="bar-label">${formatCurrency(expense)}</span>
                                </div>
                                <div class="bar net-bar" style="width: ${(Math.abs(net) / maxValue) * 100}%">
                                    <span class="bar-label">${formatCurrency(net)}</span>
                                </div>
                            </div>
                        </div>
                    `;
                    });

                    chartHTML += '</div>';
                } else {
                    // Pie chart for income by venue (SVG)
                    chartHTML = '';
                    const totalIncome = Object.values(incomeByVenue).reduce((a, b) => a + b, 0);

                    if (totalIncome > 0) {
                        const colors = ['#16a34a', '#ef4444', '#2563eb', '#f59e0b', '#7c3aed'];
                        const entries = Object.entries(incomeByVenue);
                        const cx = 150, cy = 150, r = 120;
                        let start = 0;
                        let svg = `<svg viewBox="0 0 ${cx * 2} ${cy * 2}" width="100%" height="300" role="img" aria-label="Income pie chart">`;
                        let legend = '';

                        entries.forEach(([venue, amount], i) => {
                            const angle = (amount / totalIncome) * 360;
                            const end = start + angle;
                            const largeArc = angle > 180 ? 1 : 0;
                            const rad = (a) => (a - 90) * Math.PI / 180; // offset so 0deg is at top
                            const x1 = cx + r * Math.cos(rad(start));
                            const y1 = cy + r * Math.sin(rad(start));
                            const x2 = cx + r * Math.cos(rad(end));
                            const y2 = cy + r * Math.sin(rad(end));

                            const path = `M ${cx} ${cy} L ${x1.toFixed(3)} ${y1.toFixed(3)} A ${r} ${r} 0 ${largeArc} 1 ${x2.toFixed(3)} ${y2.toFixed(3)} Z`;
                            const color = colors[i % colors.length];
                            svg += `<path d="${path}" fill="${color}" data-venue="${venue}" data-value="${amount}"></path>`;

                            const pct = (amount / totalIncome * 100).toFixed(1);
                            legend += `<div class="pie-legend-item"><span class="swatch" style="background:${color}"></span> ${venue}: ${pct}%</div>`;

                            start = end;
                        });

                        svg += `</svg>`;
                        chartHTML += `<div class="pie-chart-svg" style="display:flex;align-items:center;gap:20px;flex-wrap:wrap;">${svg}<div class="pie-legend">${legend}</div></div>`;
                    } else {
                        chartHTML += '<div class="no-data">No income data available</div>';
                    }
                }

                chartElement.innerHTML = chartHTML;
            }

            // Render journal account summary chart
            function renderJournalChart() {
                const chartElement = document.getElementById('journal-chart');

                // Calculate account balances
                const accountBalances = {};

                journalEntries.forEach(entry => {
                    if (!accountBalances[entry.account]) {
                        accountBalances[entry.account] = 0;
                    }

                    accountBalances[entry.account] += entry.debit - entry.credit;
                });

                // Create a simple bar chart for account balances
                let chartHTML = '<div class="bar-chart">';

                const maxBalance = Math.max(...Object.values(accountBalances).map(Math.abs));

                for (const [account, balance] of Object.entries(accountBalances)) {
                    const isPositive = balance >= 0;
                    const width = (Math.abs(balance) / maxBalance) * 100;

                    chartHTML += `
                    <div class="bar-group">
                        <div class="venue-label">${account}</div>
                        <div class="bars">
                            <div class="bar ${isPositive ? 'income-bar' : 'expense-bar'}" style="width: ${width}%">
                                <span class="bar-label">${formatCurrency(balance)}</span>
                            </div>
                        </div>
                    </div>
                `;
                }

                chartHTML += '</div>';
                chartElement.innerHTML = chartHTML;
            }

            // Initialize the application
            async function init() {
                // Load financial records (existing data)
                updateFinancialStats();
                renderFinancialTable();
                renderChart();

                // Load journal entries from API
                await fetchJournalEntries();
                updateJournalStats();
                renderJournalTable();
                renderJournalChart();

                // Update chart when type changes
                document.getElementById('chart-type').addEventListener('change', renderChart);
            }

            // Start the application
            init();
        </script>
</body>

</html>