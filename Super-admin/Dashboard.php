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

// Fetch current superadmin details
$stmt = $pdo->prepare("SELECT * FROM `SuperAdminLogin_tb` WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$admin = $stmt->fetch();

$api_key = $admin['api_key'] ?? 'NO_KEY_FOUND';

// Define Modules for HR and other departments
$modules = [
    ['name' => 'HR1 - Recruitment', 'id' => 'HR1', 'icon' => 'user-plus', 'color' => '#3b82f6', 'url' => '../HR1/index.php'],
    ['name' => 'HR2 - Payroll', 'id' => 'HR2', 'icon' => 'money-check-dollar', 'color' => '#10b981', 'url' => '../HR2/index.php'],
    ['name' => 'HR3 - Training', 'id' => 'HR3', 'icon' => 'graduation-cap', 'color' => '#f59e0b', 'url' => '../HR3/index.php'],
    ['name' => 'HR4 - Employee Relations', 'id' => 'HR4', 'icon' => 'users-between-lines', 'color' => '#ef4444', 'url' => '../HR4/index.php'],
    ['name' => 'Legal Management', 'id' => 'LEGAL', 'icon' => 'scale-balanced', 'color' => '#8b5cf6', 'url' => '../Modules/legalmanagement.php'],
    ['name' => 'Financial Records', 'id' => 'FINANCE', 'icon' => 'chart-line', 'color' => '#ec4899', 'url' => '../Modules/financial.php'],
    ['name' => 'Document Archiving', 'id' => 'ARCHIVE', 'icon' => 'box-archive', 'color' => '#64748b', 'url' => '../Modules/document management(archiving).php'],
];

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin Command Center | ATIERA</title>
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
            padding: 40px;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 40px;
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
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 25px;
        }

        .module-card {
            background: var(--card-bg);
            padding: 30px;
            border-radius: 24px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            transition: all 0.3s;
            text-decoration: none;
            display: flex;
            flex-direction: column;
            gap: 20px;
            border: 1px solid #e2e8f0;
        }

        .module-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
            border-color: var(--primary-gold);
        }

        .module-icon {
            width: 60px;
            height: 60px;
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
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
    </style>
</head>

<body>
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
            <li class="nav-item">
                <a href="#" class="nav-link">
                    <i class="fas fa-users"></i> Administrators
                </a>
            </li>
            <li class="nav-item">
                <a href="#" class="nav-link">
                    <i class="fas fa-shield-halved"></i> Security Logs
                </a>
            </li>
            <li class="nav-item">
                <a href="#" class="nav-link">
                    <i class="fas fa-gear"></i> System Settings
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

        <h2 style="margin-bottom: 25px; font-size: 20px; color: var(--text-dark);">Department Gateways</h2>

        <div class="module-grid">
            <?php foreach ($modules as $module): ?>
                <a href="<?php echo htmlspecialchars($module['url']); ?>?bypass_key=<?php echo urlencode($api_key); ?>&super_admin_session=true"
                    class="module-card">
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
</body>

</html>