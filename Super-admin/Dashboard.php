<?php
session_start();

// Security check: Only Super Admin can access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    header('Location: auth/login.php');
    exit;
}

require_once __DIR__ . '/../db/db.php';
$pdo = get_pdo();

// Ensure we are using the correct context (SuperAdminLogin_tb is in the main database)
$sa_table = 'SuperAdminLogin_tb';
try {
    $pdo->query("SELECT 1 FROM `$sa_table` LIMIT 1");
} catch (PDOException $e) {
    // Self-healing: Create the table if it's missing in the main database
    $sql = "CREATE TABLE IF NOT EXISTS `$sa_table` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `username` varchar(50) NOT NULL,
        `email` varchar(100) NOT NULL,
        `password_hash` varchar(255) NOT NULL,
        `full_name` varchar(100) NOT NULL,
        `api_key` varchar(255) DEFAULT NULL,
        `role` varchar(50) DEFAULT 'super_admin',
        `is_active` tinyint(1) DEFAULT 1,
        `last_login` datetime DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
        PRIMARY KEY (`id`),
        UNIQUE KEY `username` (`username`),
        UNIQUE KEY `email` (`email`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";
    $pdo->exec($sql);

    // Insert default admin if table was just created
    $default_pass = password_hash('password', PASSWORD_DEFAULT);
    $api_key = bin2hex(random_bytes(32));
    $pdo->exec("INSERT IGNORE INTO `$sa_table` (username, email, password_hash, full_name, api_key, role) 
               VALUES ('admin', 'atiera41001@gmail.com', '$default_pass', 'System Administrator', '$api_key', 'super_admin')");
}

// Fetch current superadmin details
$admin = false;
if (isset($_SESSION['user_id']) && is_numeric($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("SELECT * FROM `$sa_table` WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $admin = $stmt->fetch();
}

// Fallback if session ID is not in DB or is generic
if (!$admin) {
    $stmt = $pdo->query("SELECT * FROM `$sa_table` WHERE is_active = 1 LIMIT 1");
    $admin = $stmt->fetch();
}

// Final safety check to prevent warnings
if (!$admin) {
    $admin = [
        'full_name' => 'System Administrator',
        'api_key' => 'NO_KEY_FOUND',
        'username' => 'admin'
    ];
}

$api_key = $admin['api_key'] ?? 'NO_KEY_FOUND';

// Define Modules grouped by Department Cluster
$clusters = [
    'HR Cluster' => [
        ['name' => 'HR1', 'id' => 'HR1', 'icon' => 'user-plus', 'color' => '#3b82f6', 'url' => '../HR1/index.php'],
        ['name' => 'HR2', 'id' => 'HR2', 'icon' => 'money-check-dollar', 'color' => '#10b981', 'url' => '../HR2/index.php'],
        ['name' => 'HR3', 'id' => 'HR3', 'icon' => 'graduation-cap', 'color' => '#f59e0b', 'url' => '../HR3/index.php'],
        ['name' => 'HR4', 'id' => 'HR4', 'icon' => 'handshake', 'color' => 'linear-gradient(135deg, #8b5cf6, #d946ef)', 'url' => '../HR4/index.php'],
    ],
    'Core Cluster' => [
        ['name' => 'CORE 1', 'id' => 'CORE1', 'icon' => 'hotel', 'color' => '#6366f1', 'url' => '../CORE1/index.php'],
        ['name' => 'CORE 2', 'id' => 'CORE2', 'icon' => 'utensils', 'color' => '#f97316', 'url' => '../CORE2/index.php'],
    ],
    'Logistics Cluster' => [
        ['name' => 'Logistics 1', 'id' => 'LOG1', 'icon' => 'dolly', 'color' => '#d97706', 'url' => '../Logistics1/index.php'],
        ['name' => 'Logistics 2', 'id' => 'LOG2', 'icon' => 'warehouse', 'color' => '#7c3aed', 'url' => '../Logistics2/index.php'],
    ],
    'ADMIN' => [
        ['name' => 'ADMIN', 'id' => 'ADMIN_SYSTEM', 'icon' => 'shield-halved', 'color' => '#0f172a', 'url' => 'https://admin.atierahotelandrestaurant.com/admin/auth/login.php'],
    ]
];


?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin Command Center | ATIERA</title>
    <link rel="icon" type="image/x-icon" href="../assets/image/logo2.png">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-gold: #d4af37;
            --sidebar-bg: #0f172a;
            --main-bg: #f8fafc;
            --card-bg: #ffffff;
            --text-dark: #1e293b;
            --text-gray: #64748b;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Outfit', sans-serif;
        }

        body {
            display: flex;
            background: var(--main-bg);
            min-height: 100vh;
        }

        /* Hide scrollbar globally */
        * {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }

        *::-webkit-scrollbar {
            display: none;
        }

        /* Sidebar Styles */
        .sidebar {
            width: 280px;
            background: var(--sidebar-bg);
            color: white;
            padding: 30px 20px;
            display: flex;
            flex-direction: column;
            position: fixed;
            height: 100vh;
        }

        .sidebar-header {
            margin-bottom: 50px;
            text-align: center;
        }

        .sidebar-logo {
            font-size: 28px;
            font-weight: 700;
            letter-spacing: 5px;
            color: var(--primary-gold);
        }

        .nav-section-label {
            font-size: 10px;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            margin: 25px 0 10px 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .nav-section-label::after {
            content: '';
            flex: 1;
            height: 1px;
            background: rgba(148, 163, 184, 0.1);
        }

        .nav-list {
            list-style: none;
            flex-grow: 1;
        }

        .nav-item {
            margin-bottom: 10px;
        }

        .nav-link {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            color: #94a3b8;
            text-decoration: none;
            border-radius: 12px;
            transition: all 0.3s;
            gap: 15px;
        }

        .nav-link:hover,
        .nav-link.active {
            background: rgba(212, 175, 55, 0.1);
            color: var(--primary-gold);
        }

        /* Main Content */
        .main-content {
            margin-left: 280px;
            width: calc(100% - 280px);
            padding: 15px;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .welcome-msg h1 {
            font-size: 28px;
            color: var(--text-dark);
        }

        .welcome-msg p {
            color: var(--text-gray);
        }

        .api-key-badge {
            background: white;
            padding: 10px 20px;
            border-radius: 30px;
            border: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
            color: var(--text-dark);
        }

        .api-key-badge code {
            color: var(--primary-gold);
            font-weight: 600;
        }

        /* Module Grid */
        .module-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            gap: 20px;
        }

        .module-card {
            background: var(--card-bg);
            padding: 20px;
            border-radius: 20px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            transition: all 0.3s;
            text-decoration: none;
            display: flex;
            flex-direction: column;
            gap: 15px;
            border: 1px solid #e2e8f0;
        }

        .module-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
            border-color: var(--primary-gold);
        }

        .module-icon {
            width: 50px;
            height: 50px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: white;
        }

        .module-info h3 {
            font-size: 18px;
            color: var(--text-dark);
            margin-bottom: 5px;
        }

        .module-info p {
            font-size: 14px;
            color: var(--text-gray);
        }

        .bypass-status {
            font-size: 12px;
            color: #10b981;
            display: flex;
            align-items: center;
            gap: 5px;
            margin-top: auto;
        }

        /* Premium Card Styles for HR4 */
        .premium-card {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.9) 0%, rgba(245, 243, 255, 0.9) 100%);
            border: 1px solid rgba(139, 92, 246, 0.2);
            position: relative;
            overflow: hidden;
            z-index: 1;
        }

        .premium-card::before {
            content: '';
            position: absolute;
            top: -20%;
            right: -20%;
            width: 150px;
            height: 150px;
            background: radial-gradient(circle, rgba(139, 92, 246, 0.1) 0%, transparent 70%);
            z-index: -1;
            transition: all 0.5s ease;
        }

        .premium-card:hover::before {
            transform: scale(2);
            background: radial-gradient(circle, rgba(139, 92, 246, 0.2) 0%, transparent 70%);
        }

        .premium-card .module-icon {
            box-shadow: 0 10px 20px -5px rgba(139, 92, 246, 0.4);
            background-size: 200% 200% !important;
            animation: gradientMove 3s ease infinite;
        }

        .premium-card h3 {
            background: linear-gradient(to right, #1e293b, #8b5cf6);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            font-weight: 700;
        }

        .premium-badge {
            position: absolute;
            top: 20px;
            right: 20px;
            background: rgba(139, 92, 246, 0.1);
            color: #8b5cf6;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            border: 1px solid rgba(139, 92, 246, 0.2);
        }

        @keyframes gradientMove {
            0% {
                background-position: 0% 50%;
            }

            50% {
                background-position: 100% 50%;
            }

            100% {
                background-position: 0% 50%;
            }
        }

        /* Logout */
        .logout-btn {
            margin-top: auto;
            color: #ef4444;
            padding: 12px 15px;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
        }

        /* --- LOADING SCREEN STYLES --- */
        #loading-screen {
            position: fixed;
            inset: 0;
            background: url('../assets/image/loading.png') center/cover no-repeat;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            z-index: 99999;
            transition: opacity 0.8s ease, visibility 0.8s;
        }

        .loader-container {
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .brand-wrapper {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 20px;
        }

        .fire {
            position: relative;
            width: 60px;
            height: 60px;
        }

        .fire .flame {
            position: absolute;
            bottom: 0;
            left: 50%;
            width: 60px;
            height: 60px;
            border-radius: 50% 0 50% 50%;
            background: linear-gradient(-45deg, #d4af37, #fcf6ba, #aa771c);
            transform: translateX(-50%) rotate(-45deg);
            box-shadow: 0 0 10px #d4af37, 0 0 20px #aa771c;
            animation: burn 1s infinite alternate ease-in-out;
            opacity: 0.9;
        }

        .fire .flame:nth-child(2) {
            width: 40px;
            height: 40px;
            bottom: 5px;
            background: linear-gradient(-45deg, #fcf6ba, #fff);
            animation: burn 1.5s infinite alternate-reverse ease-in-out;
            z-index: 2;
        }

        @keyframes burn {
            0% {
                transform: translateX(-50%) rotate(-45deg) scale(1);
                border-radius: 50% 0 50% 50%;
            }

            50% {
                transform: translateX(-50%) rotate(-45deg) scale(1.1);
                border-radius: 40% 10% 40% 40%;
            }

            100% {
                transform: translateX(-50%) rotate(-45deg) scale(1);
                border-radius: 50% 0 50% 50%;
            }
        }

        .brand-text-loader {
            font-family: 'Cinzel', serif;
            font-size: 3.5rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 5px;
            background: linear-gradient(90deg, #bf953f, #fcf6ba, #b38728, #fbf5b7, #aa771c);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            animation: shine 5s infinite linear;
            background-size: 200%;
        }

        .tagline-loader {
            font-family: 'Montserrat', sans-serif;
            color: #E6C86E;
            font-weight: 700;
            letter-spacing: 6px;
            font-size: 0.8rem;
            text-transform: uppercase;
            margin-top: 15px;
            text-align: center;
        }

        .admin-wrapper-loader {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
            margin-top: 15px;
        }

        .admin-text-loader {
            font-family: 'Cinzel', serif;
            font-size: 1rem;
            color: #E6C86E;
            letter-spacing: 4px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .wave-text-loader span {
            display: inline-block;
            animation: letterWaveLoader 2s ease-in-out infinite;
            animation-delay: calc(0.1s * var(--i));
        }

        @keyframes letterWaveLoader {

            0%,
            100% {
                transform: translateY(0);
            }

            50% {
                transform: translateY(-5px);
            }
        }

        .center-line-loader {
            height: 1px;
            width: 40px;
            background: linear-gradient(90deg, transparent, #d4af37, transparent);
        }

        .loading-line-loader {
            width: 250px;
            height: 2px;
            background: rgba(255, 255, 255, 0.1);
            margin-top: 30px;
            position: relative;
            overflow: hidden;
            border-radius: 4px;
        }

        .loading-progress-loader {
            position: absolute;
            height: 100%;
            width: 0%;
            background: linear-gradient(90deg, #E6C86E, #fcf6ba, #E6C86E);
            box-shadow: 0 0 10px #E6C86E;
            animation: loadProgress 2.5s cubic-bezier(0.4, 0, 0.2, 1) forwards;
        }

        @keyframes loadProgress {
            0% {
                width: 0%;
            }

            100% {
                width: 100%;
            }
        }

        @keyframes shine {
            to {
                background-position: 200% center;
            }
        }
    </style>
</head>

<body>
    <!-- Loading Screen -->
    <div id="loading-screen">
        <div class="loader-container">
            <div class="brand-wrapper">
                <div class="fire">
                    <div class="flame"></div>
                    <div class="flame"></div>
                </div>
                <h1 class="brand-text-loader">ATIÉRA</h1>
            </div>
            <div class="tagline-loader wave-text-loader">Hotel & Restaurant</div>
            <div class="admin-wrapper-loader">
                <div class="center-line-loader"></div>
                <div class="admin-text-loader wave-text-loader">ADMINISTRATIVE</div>
                <div class="center-line-loader"></div>
            </div>
            <div class="loading-line-loader">
                <div class="loading-progress-loader"></div>
            </div>
        </div>
    </div>

    <script>
        // Wave Text Animation for Loader
        document.querySelectorAll('.wave-text-loader').forEach(container => {
            const text = container.textContent;
            container.innerHTML = '';
            [...text].forEach((letter, index) => {
                const span = document.createElement('span');
                span.textContent = letter === ' ' ? '\u00A0' : letter;
                span.style.setProperty('--i', index);
                container.appendChild(span);
            });
        });

        // Hide Loading Screen
        window.addEventListener('load', () => {
            const loader = document.getElementById('loading-screen');
            setTimeout(() => {
                loader.style.opacity = '0';
                setTimeout(() => {
                    loader.style.visibility = 'hidden';
                }, 800);
            }, 2000); // 2 seconds delay
        });
    </script>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h1 class="sidebar-logo">ATIÉRA</h1>
            <p style="font-size: 10px; color: #64748b; letter-spacing: 2px;">COMMAND CENTER</p>
        </div>

        <ul class="nav-list">
            <li class="nav-item">
                <a href="#" class="nav-link active">
                    <i class="fas fa-home"></i> Dashboard
                </a>
            </li>

            <div class="nav-section-label">Management & Operations</div>

            <li class="nav-item">
                <a href="../Modules/legalmanagement.php?bypass_key=<?php echo urlencode($api_key); ?>&super_admin_session=true"
                    class="nav-link">
                    <i class="fas fa-scale-balanced"></i> Legal Management
                </a>
            </li>
            <li class="nav-item">
                <a href="integ/fn_api.php?bypass_key=<?php echo urlencode($api_key); ?>&super_admin_session=true"
                    class="nav-link">
                    <i class="fas fa-chart-line"></i> Financial Records
                </a>
            </li>
            <li class="nav-item">
                <a href="modules/document.php?bypass_key=<?php echo urlencode($api_key); ?>&super_admin_session=true"
                    class="nav-link">
                    <i class="fas fa-box-archive"></i> Document Archiving
                </a>
            </li>
            <li class="nav-item">
                <a href="../Modules/Visitor-logs.php?bypass_key=<?php echo urlencode($api_key); ?>&super_admin_session=true"
                    class="nav-link">
                    <i class="fas fa-id-card-clip"></i> Visitor Logs
                </a>
            </li>
        </ul>

        <a href="auth/logout.php" class="logout-btn">
            <i class="fas fa-power-off"></i> System Shutdown
        </a>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="header">
            <div class="welcome-msg">
                <h1>Welcome Back, <?php echo htmlspecialchars($admin['full_name']); ?></h1>
                <p>Today is <?php echo date('l, F j, Y'); ?></p>
            </div>
            <div class="api-key-badge">
                <i class="fas fa-key"></i>
                <span>Active Key: <code><?php echo substr($api_key, 0, 8) . '...'; ?></code></span>
            </div>
        </div>

        <h2
            style="margin-bottom: 35px; font-size: 24px; color: var(--text-dark); border-bottom: 2px solid var(--primary-gold); display: inline-block; padding-bottom: 5px;">
            Department Gateways</h2>

        <!-- Module Clusters -->
        <div class="clusters-container" style="display: block; width: 100%;">
            <?php foreach ($clusters as $clusterName => $modules): ?>
                <div class="cluster-section" style="margin-bottom: 50px; display: block; clear: both; width: 100%;">
                    <h3
                        style="margin-bottom: 25px; font-size: 18px; color: var(--text-gray); text-transform: uppercase; letter-spacing: 2px; display: flex; align-items: center; gap: 10px;">
                        <div style="width: 10px; height: 10px; background: var(--primary-gold); border-radius: 50%;"></div>
                        <?php echo $clusterName; ?>
                    </h3>
                    <div class="module-grid">
                        <?php foreach ($modules as $module): ?>
                            <a href="<?php echo htmlspecialchars($module['url']); ?>?bypass_key=<?php echo urlencode($api_key); ?>&super_admin_session=true"
                                class="module-card <?php echo isset($module['premium']) ? 'premium-card' : ''; ?>"
                                id="module-<?php echo $module['id']; ?>">
                                <?php if (isset($module['premium'])): ?>
                                    <div class="premium-badge">Priority Module</div>
                                <?php endif; ?>
                                <div class="module-icon" style="background: <?php echo $module['color']; ?>;">
                                    <i class="fas fa-<?php echo $module['icon']; ?>"></i>
                                </div>
                                <div class="module-info">
                                    <h3><?php echo htmlspecialchars($module['name']); ?></h3>
                                    <p>Access the <?php echo $module['id']; ?> internal system with superuser privileges.</p>
                                </div>
                                <div class="bypass-status">
                                    <i class="fas fa-bolt"></i> Bypass Protocol Active
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- System Status -->
        <div
            style="margin-top: 50px; background: white; padding: 30px; border-radius: 24px; border: 1px solid #e2e8f0;">
            <h3 style="margin-bottom: 20px;">Cluster Connectivity</h3>
            <div style="display: flex; gap: 40px;">
                <div style="text-align: center;">
                    <div style="font-size: 24px; font-weight: 700; color: #10b981;">100%</div>
                    <div style="font-size: 12px; color: var(--text-gray);">API Sync</div>
                </div>
                <div style="text-align: center;">
                    <div style="font-size: 24px; font-weight: 700; color: #3b82f6;">Active</div>
                    <div style="font-size: 12px; color: var(--text-gray);">Bypass Service</div>
                </div>
                <div style="text-align: center;">
                    <div style="font-size: 24px; font-weight: 700; color: var(--primary-gold);">Secure</div>
                    <div style="font-size: 12px; color: var(--text-gray);">Encryption</div>
                </div>
            </div>
        </div>
    </div>

    <!-- HR1 Modal -->
    <div id="hr1Modal"
        style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.7); z-index:100000; justify-content:center; align-items:center; backdrop-filter:blur(5px);">
        <div
            style="background:white; width:90%; max-width:1000px; max-height:80vh; border-radius:30px; overflow:hidden; display:flex; flex-direction:column; box-shadow:0 25px 50px -12px rgba(0,0,0,0.5);">
            <div
                style="padding:30px; border-bottom:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center; background:#3b82f6; color:white;">
                <h2 style="font-size:24px; font-weight:700;"><i class="fas fa-user-plus"
                        style="color:white; margin-right:15px;"></i>HR1 ADMIN Dashboard</h2>
                <button id="closeHr1Modal"
                    style="background:none; border:none; color:white; font-size:24px; cursor:pointer;"><i
                        class="fas fa-times"></i></button>
            </div>
            <div id="hr1ListContainer" style="padding:30px; overflow-y:auto; background:#eff6ff; flex-grow:1;">
                <div style="text-align:center; padding:50px;">
                    <i class="fas fa-circle-notch fa-spin" style="font-size:40px; color:#3b82f6;"></i>
                    <p style="margin-top:20px; color:#64748b;">Synchronizing with HR Cluster...</p>
                </div>
            </div>
        </div>
    </div>



    <!-- Logistics Modal -->
    <div id="logisticsModal"
        style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.7); z-index:100000; justify-content:center; align-items:center; backdrop-filter:blur(5px);">
        <div
            style="background:white; width:90%; max-width:1000px; max-height:80vh; border-radius:30px; overflow:hidden; display:flex; flex-direction:column; box-shadow:0 25px 50px -12px rgba(0,0,0,0.5);">
            <div
                style="padding:30px; border-bottom:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center; background:#d97706; color:white;">
                <h2 style="font-size:24px; font-weight:700;"><i class="fas fa-boxes-stacked"
                        style="color:white; margin-right:15px;"></i>Logistics ADMIN Dashboard</h2>
                <button id="closeLogisticsModal"
                    style="background:none; border:none; color:white; font-size:24px; cursor:pointer;"><i
                        class="fas fa-times"></i></button>
            </div>
            <div id="logisticsListContainer" style="padding:30px; overflow-y:auto; background:#fff7ed; flex-grow:1;">
                <div style="text-align:center; padding:50px;">
                    <i class="fas fa-circle-notch fa-spin" style="font-size:40px; color:#d97706;"></i>
                    <p style="margin-top:20px; color:#64748b;">Synchronizing with Logistics Cluster...</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Financial Ledger Modal -->
    <div id="financialModal"
        style="display:none; position:fixed; inset:0; background:rgba(15, 23, 42, 0.85); z-index:100000; justify-content:center; align-items:center; backdrop-filter:blur(10px);">
        <div
            style="background:#ffffff; width:95%; max-width:1100px; max-height:90vh; border-radius:30px; overflow:hidden; display:flex; flex-direction:column; box-shadow:0 30px 60px -12px rgba(0,0,0,0.6); border: 1px solid rgba(255,255,255,0.1);">
            <div
                style="padding:25px 35px; border-bottom:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center; background:#1e293b; color:white;">
                <div style="display:flex; align-items:center; gap:15px;">
                    <div
                        style="width: 40px; height: 40px; background: rgba(212, 175, 55, 0.1); border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-chart-pie" style="color:var(--primary-gold); font-size: 20px;"></i>
                    </div>
                    <h2
                        style="font-size:24px; font-weight:700; font-family: 'Outfit', sans-serif; letter-spacing: -0.5px;">
                        Financial ADMIN Ledger</h2>
                </div>
                <button id="closeFinancialModal"
                    style="background:rgba(255,255,255,0.1); border:none; color:white; width: 36px; height: 36px; border-radius: 50%; cursor:pointer; display: flex; align-items: center; justify-content: center; transition: all 0.2s;"
                    onmouseover="this.style.background='rgba(255,255,255,0.2)'"
                    onmouseout="this.style.background='rgba(255,255,255,0.1)'">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div id="financialListContainer" style="padding:30px; overflow-y:auto; background:#f8fafc; flex-grow:1;">
                <div style="text-align:center; padding:50px;">
                    <div class="loader-container">
                        <i class="fas fa-circle-notch fa-spin" style="font-size:40px; color:var(--primary-gold);"></i>
                        <p style="margin-top:20px; color:#64748b; font-weight: 500;">Establishing encrypted link to
                            Financial cluster...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Financial Ledger Modal Handler
        const showFinancialLedger = function (e) {
            if (e) e.preventDefault();
            const modal = document.getElementById('financialModal');
            modal.style.display = 'flex';

            const container = document.getElementById('financialListContainer');
            container.innerHTML = `
                <div style="text-align:center; padding:50px;">
                    <i class="fas fa-circle-notch fa-spin" style="font-size:40px; color:var(--primary-gold);"></i>
                    <p style="margin-top:20px; color:#64748b;">Requesting ledger data...</p>
                </div>
            `;

            fetch('integ/fn_api.php')
                .then(response => response.json())
                .then(result => {
                    if (result.success && result.data) {
                        if (result.data.length === 0) {
                            container.innerHTML = '<div style="text-align:center; padding:50px; color:#64748b;"><i class="fas fa-file-invoice-dollar" style="font-size:40px; margin-bottom:20px; display:block;"></i>No records found in the financial system.</div>';
                            return;
                        }

                        let html = `
                            <div style="overflow-x:auto;">
                                <table style="width:100%; border-collapse:separate; border-spacing:0 12px;">
                                    <thead>
                                        <tr style="text-align:left; color:#94a3b8; font-size:12px; text-transform:uppercase; letter-spacing:1.5px; font-weight: 700;">
                                            <th style="padding:10px 20px;">Entry #</th>
                                            <th style="padding:10px 20px;">Date</th>
                                            <th style="padding:10px 20px;">Category</th>
                                            <th style="padding:10px 20px;">Description</th>
                                            <th style="padding:10px 20px;">Amount</th>
                                            <th style="padding:10px 20px;">Status</th>
                                            <th style="padding:10px 20px;">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                        `;

                        result.data.forEach(item => {
                            const statusColor = item.status === 'posted' ? '#10b981' : '#f59e0b';
                            const amountColor = item.type === 'Income' ? '#10b981' : '#ef4444';
                            const formattedAmount = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(item.amount || 0);

                            html += `
                                <tr style="background:white; box-shadow:0 4px 6px -1px rgba(0,0,0,0.05); border-radius:16px; transition: transform 0.2s;">
                                    <td style="padding:20px; font-weight:700; color:var(--primary-gold); border-radius:16px 0 0 16px;">
                                        #${item.entry_number || 'N/A'}
                                    </td>
                                    <td style="padding:20px; font-size:14px; color:#475569; font-weight: 500;">
                                        ${item.entry_date || 'N/A'}
                                    </td>
                                    <td style="padding:20px;">
                                        <span style="background:#f1f5f9; color:#64748b; padding:6px 14px; border-radius:20px; font-size:11px; font-weight:700; text-transform: uppercase;">
                                            ${item.category || 'General'}
                                        </span>
                                    </td>
                                    <td style="padding:20px; font-size:14px; color:#64748b; max-width:250px;">
                                        ${item.description || 'No description'}
                                    </td>
                                    <td style="padding:20px; font-weight:700; color:${amountColor}; font-size: 16px;">
                                        ${formattedAmount}
                                    </td>
                                    <td style="padding:20px;">
                                        <div style="display:flex; align-items:center; gap:10px;">
                                            <div style="width:10px; height:10px; border-radius:50%; background:${statusColor}; box-shadow: 0 0 10px ${statusColor}40;"></div>
                                            <span style="font-size:14px; color:#475569; font-weight: 600; text-transform:capitalize;">${item.status || 'pending'}</span>
                                        </div>
                                    </td>
                                    <td style="padding:20px; border-radius:0 16px 16px 0;">
                                        <a href="https://financial.atierahotelandrestaurant.com/index.php?bypass_key=<?php echo urlencode($api_key); ?>&super_admin_session=true" 
                                           target="_blank"
                                           style="display:inline-flex; align-items:center; justify-content: center; width: 40px; height: 40px; background:#f8fafc; color:#64748b; border-radius:10px; text-decoration:none; transition:all 0.2s; border: 1px solid #e2e8f0;" 
                                           onmouseover="this.style.background='#1e293b'; this.style.color='white'; this.style.borderColor='#1e293b'" 
                                           onmouseout="this.style.background='#f8fafc'; this.style.color='#64748b'; this.style.borderColor='#e2e8f0'"
                                           title="Access System">
                                            <i class="fas fa-external-link-alt"></i>
                                        </a>
                                    </td>
                                </tr>
                            `;
                        });

                        html += `
                                    </tbody>
                                </table>
                            </div>
                            <div style="margin-top: 25px; padding: 20px; background: #f1f5f9; border-radius: 15px; display: flex; align-items: center; justify-content: space-between;">
                                <div style="display: flex; align-items: center; gap: 15px;">
                                    <div style="width: 45px; height: 45px; background: white; border-radius: 12px; display: flex; align-items: center; justify-content: center; color: #10b981; font-size: 20px;">
                                        <i class="fas fa-shield-halved"></i>
                                    </div>
                                    <div>
                                        <h4 style="font-size: 14px; color: #1e293b; margin-bottom: 2px;">Encrypted Session Active</h4>
                                        <p style="font-size: 12px; color: #64748b;">Direct bypass protocol enabled for Super Admin</p>
                                    </div>
                                </div>
                                <a href="https://financial.atierahotelandrestaurant.com/index.php?bypass_key=<?php echo urlencode($api_key); ?>&super_admin_session=true" 
                                   target="_blank"
                                   style="background: #1e293b; color: white; padding: 12px 25px; border-radius: 12px; text-decoration: none; font-weight: 600; font-size: 14px; transition: all 0.3s;"
                                   onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 10px 15px -3px rgba(0,0,0,0.1)'"
                                   onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none'">
                                    Full System Access <i class="fas fa-arrow-right" style="margin-left: 8px;"></i>
                                </a>
                            </div>
                        `;
                        container.innerHTML = html;
                    } else {
                        container.innerHTML = '<div style="text-align:center; padding:50px; color:#ef4444;"><i class="fas fa-exclamation-triangle" style="font-size:40px; margin-bottom:20px; display:block;"></i>Unable to connect to the financial system. Please try again later.</div>';
                    }
                })
                .catch(error => {
                    console.error('Error fetching financial ledger:', error);
                    document.getElementById('financialListContainer').innerHTML = '<div style="text-align:center; padding:50px; color:#ef4444;"><i class="fas fa-wifi" style="font-size:40px; margin-bottom:20px; display:block;"></i>Network error occurred. The cluster might be unreachable.</div>';
                });
        };

        // Logistics Modal Handler
        const showLogistics = function (e) {
            if (e) e.preventDefault();
            const modal = document.getElementById('logisticsModal');
            modal.style.display = 'flex';

            const container = document.getElementById('logisticsListContainer');
            container.innerHTML = `
                <div style="text-align:center; padding:50px;">
                    <i class="fas fa-circle-notch fa-spin" style="font-size:40px; color:#d97706;"></i>
                    <p style="margin-top:20px; color:#64748b;">Synchronizing with Logistics Cluster...</p>
                </div>
            `;

            fetch('integ/log1_api.php')
                .then(response => response.json())
                .then(result => {
                    if (result.success && result.data) {
                        if (result.data.length === 0) {
                            container.innerHTML = '<div style="text-align:center; padding:50px; color:#64748b;"><i class="fas fa-box-open" style="font-size:40px; margin-bottom:20px; display:block;"></i>No inventory items found.</div>';
                            return;
                        }

                        let html = `
                            <div style="overflow-x:auto;">
                                <table style="width:100%; border-collapse:separate; border-spacing:0 10px;">
                                    <thead>
                                        <tr style="text-align:left; color:#64748b; font-size:14px; text-transform:uppercase; letter-spacing:1px;">
                                            <th style="padding:10px 20px;">Item ID</th>
                                            <th style="padding:10px 20px;">Item Name</th>
                                            <th style="padding:10px 20px;">Category</th>
                                            <th style="padding:10px 20px;">Stock</th>
                                            <th style="padding:10px 20px;">Status</th>
                                            <th style="padding:10px 20px;">Last Update</th>
                                            <th style="padding:10px 20px;">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                        `;

                        result.data.forEach(item => {
                            let statusColor = '#10b981'; // Green for In Stock
                            if (item.status === 'Low Stock') statusColor = '#f59e0b'; // Orange
                            if (item.status === 'Critical') statusColor = '#ef4444'; // Red

                            html += `
                                <tr style="background:white; box-shadow:0 2px 4px rgba(0,0,0,0.02); border-radius:12px;">
                                    <td style="padding:15px 20px; font-weight:700; color:#d97706;">
                                        ${item.item_id || 'N/A'}
                                    </td>
                                    <td style="padding:15px 20px; font-size:14px; color:#1e293b; font-weight:600;">
                                        ${item.item_name || 'N/A'}
                                    </td>
                                    <td style="padding:15px 20px;">
                                        <span style="background:#fff7ed; color:#d97706; padding:5px 12px; border-radius:20px; font-size:12px; font-weight:600;">
                                            ${item.category || 'General'}
                                        </span>
                                    </td>
                                    <td style="padding:15px 20px; font-weight:700; color:#475569;">
                                        ${item.quantity || 0} <span style="font-size:12px; font-weight:400;">${item.unit || ''}</span>
                                    </td>
                                    <td style="padding:15px 20px;">
                                        <div style="display:flex; align-items:center; gap:8px;">
                                            <div style="width:8px; height:8px; border-radius:50%; background:${statusColor};"></div>
                                            <span style="font-size:14px; color:${statusColor}; font-weight:600;">${item.status || 'Unknown'}</span>
                                        </div>
                                    </td>
                                    <td style="padding:15px 20px; color:#94a3b8; font-size:13px;">
                                        ${item.last_updated || 'N/A'}
                                    </td>
                                    <td style="padding:15px 20px;">
                                        <div style="display:flex; justify-content: flex-start;">
                                            <a href="../Logistics1/index.php?bypass_key=<?php echo urlencode($api_key); ?>&super_admin_session=true" 
                                               target="_blank"
                                               class="action-btn" 
                                               style="color: #3b82f6; text-decoration: none; background: #eff6ff; padding: 5px 10px; border-radius: 6px; font-weight: 600; font-size: 12px; display: inline-flex; align-items: center; gap: 5px;" 
                                               title="Access System">
                                                <i class="fas fa-external-link-alt"></i> Login Access
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            `;
                        });

                        html += `
                                    </tbody>
                                </table>
                            </div>
                        `;
                        container.innerHTML = html;
                    } else {
                        container.innerHTML = '<div style="text-align:center; padding:50px; color:#ef4444;"><i class="fas fa-exclamation-triangle" style="font-size:40px; margin-bottom:20px; display:block;"></i>Unable to connect to Logistics System.</div>';
                    }
                })
                .catch(error => {
                    console.error('Error fetching logistics:', error);
                    container.innerHTML = '<div style="text-align:center; padding:50px; color:#ef4444;"><i class="fas fa-wifi" style="font-size:40px; margin-bottom:20px; display:block;"></i>Network error occurred.</div>';
                });
        };

        // HR1 Modal Handler
        const showHr1 = function (e) {
            if (e) e.preventDefault();
            const modal = document.getElementById('hr1Modal');
            modal.style.display = 'flex';

            const container = document.getElementById('hr1ListContainer');
            container.innerHTML = `
                <div style="text-align:center; padding:50px;">
                    <i class="fas fa-circle-notch fa-spin" style="font-size:40px; color:#3b82f6;"></i>
                    <p style="margin-top:20px; color:#64748b;">Synchronizing with HR Cluster...</p>
                </div>
            `;

            fetch('integ/hr1_api.php')
                .then(response => response.json())
                .then(result => {
                    if (result.success && result.data) {
                        let html = `
                            <div style="overflow-x:auto;">
                                <table style="width:100%; border-collapse:separate; border-spacing:0 10px;">
                                    <thead>
                                        <tr style="text-align:left; color:#64748b; font-size:14px; text-transform:uppercase; letter-spacing:1px;">
                                            <th style="padding:10px 20px;">ID</th>
                                            <th style="padding:10px 20px;">Applicant Name</th>
                                            <th style="padding:10px 20px;">Position</th>
                                            <th style="padding:10px 20px;">Status</th>
                                            <th style="padding:10px 20px;">Date Applied</th>
                                            <th style="padding:10px 20px;">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                        `;

                        result.data.forEach(item => {
                            // Map API fields to display variables
                            const name = item.applicant_name || (item.first_name && item.last_name ? `${item.first_name} ${item.last_name}` : 'Unknown');
                            const position = item.position || item.job_title || 'N/A';
                            const date = item.date_applied || item.hire_date || item.created_at || 'N/A';
                            const status = item.status || 'Active';

                            html += `
                                <tr style="background:white; box-shadow:0 2px 4px rgba(0,0,0,0.02); border-radius:12px;">
                                    <td style="padding:15px 20px; font-weight:700; color:#3b82f6;">#${item.id}</td>
                                    <td style="padding:15px 20px;">${name}</td>
                                    <td style="padding:15px 20px;">${position}</td>
                                    <td style="padding:15px 20px;"><span style="background:#eff6ff; color:#3b82f6; padding:5px 10px; border-radius:15px; font-size:12px;">${status}</span></td>
                                    <td style="padding:15px 20px;">${date}</td>
                                     <td style="padding:15px 20px;">
                                        <div style="display:flex; justify-content: flex-start;">
                                            <a href="../HR1/index.php?bypass_key=<?php echo urlencode($api_key); ?>&super_admin_session=true" 
                                               target="_blank"
                                               class="action-btn" 
                                               style="color: #3b82f6; text-decoration: none; background: #eff6ff; padding: 5px 10px; border-radius: 6px; font-weight: 600; font-size: 12px; display: inline-flex; align-items: center; gap: 5px;" 
                                               title="Access System">
                                                <i class="fas fa-external-link-alt"></i> Login Access
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            `;
                        });
                        html += `</tbody></table></div>`;
                        container.innerHTML = html;
                    }
                });
        };



        // Financial Records Click Handler is attached below via module-FINANCE ID

        // Attach to Financial Records card
        if (document.getElementById('module-FINANCE')) {
            document.getElementById('module-FINANCE').addEventListener('click', showFinancialLedger);
        }

        // Attach to Logistics 1 Card
        if (document.getElementById('module-LOG1')) {
            document.getElementById('module-LOG1').addEventListener('click', showLogistics);
        }

        // Attach to HR1 Card
        if (document.getElementById('module-HR1')) {
            document.getElementById('module-HR1').addEventListener('click', showHr1);
        }



        document.getElementById('closeFinancialModal').addEventListener('click', function () {
            document.getElementById('financialModal').style.display = 'none';
        });

        document.getElementById('closeLogisticsModal').addEventListener('click', function () {
            document.getElementById('logisticsModal').style.display = 'none';
        });

        document.getElementById('closeHr1Modal').addEventListener('click', function () {
            document.getElementById('hr1Modal').style.display = 'none';
        });

        window.addEventListener('click', function (e) {
            const modals = ['financialModal', 'logisticsModal', 'hr1Modal'];
            modals.forEach(id => {
                const modal = document.getElementById(id);
                if (e.target === modal) modal.style.display = 'none';
            });
        });
    </script>
</body>

</html>