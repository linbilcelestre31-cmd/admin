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
        ['name' => 'HR1', 'id' => 'HR1', 'icon' => 'user-plus', 'color' => '#3b82f6', 'url' => 'gateway.php?dept=HR1', 'pin' => '1234'],
        ['name' => 'HR2', 'id' => 'HR2', 'icon' => 'money-check-dollar', 'color' => '#10b981', 'url' => 'gateway.php?dept=HR2', 'pin' => '1234'],
        ['name' => 'HR3', 'id' => 'HR3', 'icon' => 'graduation-cap', 'color' => '#f59e0b', 'url' => 'gateway.php?dept=HR3', 'pin' => '1234'],
        ['name' => 'HR4', 'id' => 'HR4', 'icon' => 'handshake', 'color' => 'linear-gradient(135deg, #8b5cf6, #d946ef)', 'url' => 'gateway.php?dept=HR4', 'pin' => '1234'],
    ],
    'Core Cluster' => [
        ['name' => 'CORE 1', 'id' => 'CORE1', 'icon' => 'hotel', 'color' => '#6366f1', 'url' => '../CORE1/index.php', 'pin' => '1234'],
        ['name' => 'CORE 2', 'id' => 'CORE2', 'icon' => 'utensils', 'color' => '#f97316', 'url' => '../CORE2/index.php', 'pin' => '1234'],
    ],
    'Logistics Cluster' => [
        ['name' => 'Logistics 1', 'id' => 'LOG1', 'icon' => 'dolly', 'color' => '#d97706', 'url' => '../Logistics1/index.php', 'pin' => '1234'],
        ['name' => 'Logistics 2', 'id' => 'LOG2', 'icon' => 'warehouse', 'color' => '#7c3aed', 'url' => '../Logistics2/index.php', 'pin' => '1234'],
    ],
    'Financial Management' => [
        ['name' => 'Financial Management', 'id' => 'Financial Management', 'icon' => 'chart-line', 'color' => '#10b981', 'url' => 'https://financial.atierahotelandrestaurant.com/', 'pin' => '1234'],
    ],
    'Administrative' => [
        ['name' => 'Administrative', 'id' => 'Administrative', 'icon' => 'shield-halved', 'color' => '#000', 'url' => '../Modules/dashboard.php', 'pin' => '1234'],
    ]
];


?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin | ATIERA</title>
    <link rel="icon" type="image/x-icon" href="../assets/image/logo2.png">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/Dashboard.css?v=<?php echo time(); ?>">
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

    <!-- Financial Records Modal -->
    <div id="financialModal"
        style="display:none; position:fixed; inset:0; background:rgba(15, 23, 42, 0.85); z-index:100000; justify-content:center; align-items:center; backdrop-filter:blur(10px);">
        <div
            style="background:#ffffff; width:95%; max-width:1100px; max-height:90vh; border-radius:30px; overflow:hidden; display:flex; flex-direction:column; box-shadow:0 30px 60px -12px rgba(0,0,0,0.6); border: 1px solid rgba(255,255,255,0.1);">
            <div
                style="padding:25px 35px; border-bottom:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center; background:#0f172a; color:white;">
                <div style="display:flex; align-items:center; gap:15px;">
                    <div
                        style="width: 40px; height: 40px; background: rgba(212, 175, 55, 0.1); border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-chart-pie" style="color:var(--primary-gold); font-size: 20px;"></i>
                    </div>
                    <h2
                        style="font-size:24px; font-weight:700; font-family: 'Outfit', sans-serif; letter-spacing: -0.5px;">
                        Financial System Users</h2>
                </div>
                <button id="closeFinancialModal"
                    style="background:rgba(255,255,255,0.1); border:none; color:white; width: 36px; height: 36px; border-radius: 50%; cursor:pointer; display: flex; align-items: center; justify-content: center; transition: all 0.2s;">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div id="financialListContainer" style="padding:30px; overflow-y:auto; background:#f8fafc; flex-grow:1;">
                <div style="text-align:center; padding:50px;">
                    <i class="fas fa-circle-notch fa-spin" style="font-size:40px; color:var(--primary-gold);"></i>
                    <p style="margin-top:20px; color:#64748b;">Fetching users from Financial API...</p>
                </div>
            </div>
        </div>
    </div>

    <?php include 'Department/Security.php'; ?>


    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h1 class="sidebar-logo">ATIÉRA</h1>
            <div
                style="color: var(--primary-gold); font-size: 10px; font-weight: 700; letter-spacing: 2px; text-transform: uppercase; margin-top: 5px; opacity: 0.8; text-align: center; width: 100%;">
                Super Admin</div>
        </div>

        <ul class="nav-list">
            <li class="nav-item">
                <a href="#" class="nav-link active">
                    <i class="fas fa-home"></i> Dashboard
                </a>
            </li>

            <div class="nav-section-label">Settings</div>
            <li class="nav-item">
                <a href="Settings.php" class="nav-link">
                    <i class="fas fa-user-gear"></i> Account
                </a>
            </li>

            <li class="nav-item">
                <a href="auth/logout.php" class="nav-link" style="color: #ef4444;">
                    <i class="fas fa-power-off"></i> Log Out
                </a>
            </li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="header">
            <div class="welcome-msg">
                <h1>Super Admin</h1>
                <p>Today is <?php echo date('l, F j, Y'); ?></p>
            </div>

            <div class="header-actions" style="display: flex; align-items: center; gap: 15px;">
                <div class="api-key-display"
                    style="background: white; border: 1px solid #e2e8f0; padding: 6px 12px; border-radius: 8px; font-size: 12px; color: #64748b; font-family: monospace; display: flex; align-items: center; gap: 8px; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">
                    <i class="fas fa-key" style="color: #d4af37;"></i>
                    <span>Key: <strong style="color: #334155;"><?= substr($api_key, 0, 8) . '...' ?></strong></span>
                </div>
            </div>
        </div>
        <h2
            style="margin-bottom: 35px; font-size: 24px; color: var(--text-dark); border-bottom: 2px solid var(--primary-gold); display: inline-block; padding-bottom: 5px;">
            Department</h2>

        <!-- Module Clusters -->
        <div class="clusters-container" style="display: block; width: 100%;">
            <?php foreach ($clusters as $clusterName => $modules): ?>
                <div class="cluster-section" style="margin-bottom: 50px; display: block; clear: both; width: 100%;">
                    <h3
                        style="margin-bottom: 25px; font-size: 18px; color: var(--text-gray); text-transform: uppercase; letter-spacing: 2px; display: flex; align-items: center; gap: 10px;">
                        <div style="width: 10px; height: 10px; background: var(--primary-gold); border-radius: 50%;">
                        </div>
                        <?php echo $clusterName; ?>
                    </h3>
                    <div class="module-grid">
                        <?php foreach ($modules as $module):
                            $base_url = htmlspecialchars($module['url']);
                            $separator = (strpos($base_url, '?') !== false) ? '&' : '?';
                            // Calculate full URL with key regardless of JS action
                            $full_url = $base_url . $separator . 'bypass_key=' . urlencode($api_key) . '&super_admin_session=true';
                            // Determine href: if js_action is set, use #, otherwise use full URL
                            $href = isset($module['js_action']) ? '#' : $full_url;
                            ?>
                            <a href="#" onclick="openUniversalPermission(this, event)"
                                data-target-url="<?php echo $full_url; ?>"
                                data-name="<?php echo htmlspecialchars($module['name']); ?>"
                                data-icon="fas fa-<?php echo $module['icon']; ?>" data-color="<?php echo $module['color']; ?>"
                                            data-pin="<?php echo isset($module['pin']) ? $module['pin'] : '1234'; ?>"
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
                                    <p>Access the <?php echo $module['id']; ?> internal system with superuser privileges.
                                    </p>
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


    </div>

    <script>
        const showFinancialModal = function (e) {
            if (e) e.preventDefault();
            const modal = document.getElementById('financialModal');
            modal.style.display = 'flex';

            const container = document.getElementById('financialListContainer');

            fetch('integ/fn_api.php')
                .then(response => response.json())
                .then(result => {
                    if (result.success && result.data) {
                        let html = `
                            <div style="overflow-x:auto;">
                                <table style="width:100%; border-collapse:separate; border-spacing:0 10px;">
                                    <thead>
                                        <tr style="text-align:left; color:#64748b; font-size:14px; text-transform:uppercase; letter-spacing:1px;">
                                            <th style="padding:10px 20px;">ID</th>
                                            <th style="padding:10px 20px;">Username</th>
                                            <th style="padding:10px 20px;">Email</th>
                                            <th style="padding:10px 20px;">Role</th>
                                            <th style="padding:10px 20px;">Status</th>
                                            <th style="padding:10px 20px;">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                        `;

                        result.data.forEach(user => {
                            html += `
                                <tr style="background:white; box-shadow:0 2px 4px rgba(0,0,0,0.02); border-radius:12px;">
                                    <td style="padding:15px 20px; font-weight:700; color:#0f172a;">#${user.id || user.user_id || 'N/A'}</td>
                                    <td style="padding:15px 20px; font-weight:600;">${user.username || 'Unknown'}</td>
                                    <td style="padding:15px 20px;">${user.email || 'N/A'}</td>
                                    <td style="padding:15px 20px;"><span style="background:#eff6ff; color:#3b82f6; padding:5px 10px; border-radius:15px; font-size:12px;">${user.role || 'User'}</span></td>
                                    <td style="padding:15px 20px;">
                                        <div style="display:flex; align-items:center; gap:8px;">
                                            <div style="width:8px; height:8px; border-radius:50%; background:#10b981;"></div>
                                            <span style="font-size:14px; color:#10b981;">Active</span>
                                        </div>
                                    </td>
                                    <td style="padding:15px 20px;">
                                        <a href="https://financial.atierahotelandrestaurant.com/" target="_blank" 
                                           style="color: var(--primary-gold); text-decoration: none; background: rgba(212, 175, 55, 0.1); padding: 8px 15px; border-radius: 10px; font-weight: 600; font-size: 13px; display: inline-flex; align-items: center; gap: 8px;">
                                            <i class="fas fa-external-link-alt"></i> Access System
                                        </a>
                                    </td>
                                </tr>
                            `;
                        });

                        html += `
                                    </tbody>
                                </table>
                            </div>
                            <div style="margin-top: 30px; padding-top: 30px; border-top: 1px solid #e2e8f0; display: flex; justify-content: flex-end;">
                                <a href="https://financial.atierahotelandrestaurant.com/" target="_blank" 
                                   style="background: linear-gradient(135deg, var(--primary-gold), #b8860b); color: white; padding: 12px 25px; border-radius: 12px; text-decoration: none; font-weight: 700; font-size: 14px; display: inline-flex; align-items: center; gap: 10px; box-shadow: 0 4px 12px rgba(212, 175, 55, 0.3); transition: all 0.3s;">
                                    <i class="fas fa-unlock-alt"></i> Initialize Full System Access
                                </a>
                            </div>
                        `;
                        container.innerHTML = html;
                    } else {
                        container.innerHTML = '<div style="text-align:center; padding:50px; color:#ef4444;">Failed to load data from Financial API.</div>';
                    }
                })
                .catch(err => {
                    console.error('Error:', err);
                    container.innerHTML = '<div style="text-align:center; padding:50px; color:#ef4444;">Network error while connecting to Financial API.</div>';
                });
        };

        // Attach event listener to sidebar link
        const finLink = document.getElementById('sidebar-financial-records');
        if (finLink) {
            finLink.addEventListener('click', showFinancialModal);
        }

        document.getElementById('closeFinancialModal').addEventListener('click', () => {
            document.getElementById('financialModal').style.display = 'none';
        });

        window.addEventListener('click', function (e) {
            const modal = document.getElementById('financialModal');
            if (e.target === modal) modal.style.display = 'none';
        });

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

        // Hybrid Hiding Strategy: Hide after 800ms regardless of 'load' event, 
        // but try to hide on 'load' if it happens sooner.
        const hideLoader = () => {
            const loader = document.getElementById('loading-screen');
            if (loader && loader.style.opacity !== '0') {
                loader.style.opacity = '0';
                setTimeout(() => {
                    loader.style.visibility = 'hidden';
                }, 800);
            }
        };

        // Fallback: Force hide after 1.5 seconds if load event hasn't fired
        setTimeout(hideLoader, 1500);

        // Standard: Hide on window load
        window.addEventListener('load', hideLoader);

        // Immediate: If document is already complete
        if (document.readyState === 'complete') {
            hideLoader();
        }

</body >

</html >