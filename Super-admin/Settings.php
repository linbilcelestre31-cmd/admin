<?php
session_start();
require_once __DIR__ . '/../db/db.php';
require_once __DIR__ . '/../include/Config.php';

// Security check: Only Super Admin can access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    header('Location: auth/login.php');
    exit;
}

$pdo = get_pdo();
$admin_id = $_SESSION['user_id'];
$sa_table = 'SuperAdminLogin_tb';

// Fetch current admin details
$stmt = $pdo->prepare("SELECT * FROM `$sa_table` WHERE id = ?");
$stmt->execute([$admin_id]);
$admin = $stmt->fetch();

$message = '';
$error = '';

// Handle Settings Update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        /* Update Profile */
        if ($_POST['action'] === 'update_profile') {
            $full_name = trim($_POST['full_name']);
            $email = trim($_POST['email']);

            try {
                $stmt = $pdo->prepare("UPDATE `$sa_table` SET full_name = ?, email = ? WHERE id = ?");
                $stmt->execute([$full_name, $email, $admin_id]);
                $message = "Profile updated successfully!";
                // Refresh data
                $admin['full_name'] = $full_name;
                $admin['email'] = $email;
            } catch (PDOException $e) {
                $error = "Error updating profile: " . $e->getMessage();
            }
        }
        /* Update Password */ elseif ($_POST['action'] === 'update_password') {
            $current_pass = $_POST['current_password'];
            $new_pass = $_POST['new_password'];
            $confirm_pass = $_POST['confirm_password'];

            if ($new_pass !== $confirm_pass) {
                $error = "New passwords do not match.";
            } else {
                if (password_verify($current_pass, $admin['password_hash'])) {
                    $new_hash = password_hash($new_pass, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE `$sa_table` SET password_hash = ? WHERE id = ?");
                    $stmt->execute([$new_hash, $admin_id]);
                    $message = "Password updated successfully!";
                } else {
                    $error = "Incorrect current password.";
                }
            }
        }
        /* Regenerate API Key */ elseif ($_POST['action'] === 'regen_api') {
            $new_api = bin2hex(random_bytes(32));
            $stmt = $pdo->prepare("UPDATE `$sa_table` SET api_key = ? WHERE id = ?");
            $stmt->execute([$new_api, $admin_id]);
            $_SESSION['api_key'] = $new_api;
            $admin['api_key'] = $new_api;
            $message = "System Bypass Key regenerated successfully!";
        }
    }
}

// Fetch all system accounts (Super Admins)
$stmt = $pdo->query("SELECT id, username, email, full_name, created_at, is_active FROM `$sa_table` ORDER BY created_at DESC");
$accounts = $stmt->fetchAll();

$api_key = $admin['api_key'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin Settings | ATIERA</title>
    <link rel="icon" type="image/x-icon" href="../assets/image/logo2.png">
    <link
        href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;700&family=Montserrat:wght@400;700&family=Outfit:wght@300;400;600;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/Dashboard.css?v=<?php echo time(); ?>">
    <style>
        .settings-container {
            max-width: 1100px;
            margin: 0 auto;
        }

        .settings-tabs {
            display: flex;
            gap: 20px;
            margin-bottom: 30px;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 10px;
        }

        .tab-btn {
            background: none;
            border: none;
            padding: 10px 20px;
            font-size: 16px;
            font-weight: 600;
            color: var(--text-gray);
            cursor: pointer;
            position: relative;
            transition: all 0.3s;
        }

        .tab-btn.active {
            color: var(--primary-gold);
        }

        .tab-btn.active::after {
            content: '';
            position: absolute;
            bottom: -11px;
            left: 0;
            width: 100%;
            height: 3px;
            background: var(--primary-gold);
            border-radius: 3px 3px 0 0;
        }

        .settings-card {
            background: white;
            padding: 30px;
            border-radius: 24px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            margin-bottom: 30px;
        }

        .settings-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }

        .settings-card h2 {
            font-size: 20px;
            color: var(--text-dark);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .settings-card h2 i {
            color: var(--primary-gold);
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: var(--text-gray);
            margin-bottom: 8px;
        }

        .form-input {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            font-family: 'Outfit', sans-serif;
            font-size: 15px;
            outline: none;
            transition: all 0.3s;
        }

        .form-input:focus {
            border-color: var(--primary-gold);
            box-shadow: 0 0 0 4px rgba(212, 175, 55, 0.1);
        }

        .save-btn {
            background: linear-gradient(135deg, var(--primary-gold) 0%, #b8860b 100%);
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .save-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(212, 175, 55, 0.4);
        }

        .alert {
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 25px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            color: #10b981;
            border: 1px solid rgba(16, 185, 129, 0.2);
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
            border: 1px solid rgba(239, 68, 68, 0.2);
        }

        /* Account Table Styles */
        .account-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        .account-table th {
            text-align: left;
            padding: 15px;
            color: var(--text-gray);
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 1px;
            border-bottom: 1px solid #e2e8f0;
        }

        .account-table td {
            padding: 15px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 14px;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-active {
            background: rgba(16, 185, 129, 0.1);
            color: #10b981;
        }

        .api-regen-box {
            background: #f8fafc;
            padding: 20px;
            border-radius: 16px;
            border: 1px dashed var(--primary-gold);
            margin-top: 10px;
        }

        .api-key-text {
            word-break: break-all;
            font-family: monospace;
            background: white;
            padding: 10px;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            margin: 10px 0;
            display: block;
            font-size: 13px;
            color: var(--text-dark);
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
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h1 class="sidebar-logo">ATIÉRA</h1>
        </div>

        <ul class="nav-list">
            <li class="nav-item">
                <a href="Dashboard.php" class="nav-link">
                    <i class="fas fa-home"></i> Dashboard
                </a>
            </li>

            <div class="nav-section-label">Settings</div>
            <li class="nav-item">
                <a href="Settings.php" class="nav-link active">
                    <i class="fas fa-user-gear"></i> Account
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
            <i class="fas fa-power-off"></i> Log Out
        </a>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="header">
            <div class="welcome-msg">
                <h1>Account Settings</h1>
                <p>Manage your Super Admin credentials and security protocols.</p>
            </div>
            <a href="Dashboard.php" class="api-key-badge" style="text-decoration: none;">
                <i class="fas fa-arrow-left"></i>
                <span>Back to Dashboard</span>
            </a>
        </div>

        <div class="settings-container">
            <?php if ($message): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <div class="settings-tabs">
                <button class="tab-btn active" onclick="showTab('profile')">Account Details</button>
                <button class="tab-btn" onclick="showTab('accounts')">System Accounts List</button>
                <button class="tab-btn" onclick="showTab('protocols')">Security Protocols</button>
            </div>

            <!-- Profile Tab -->
            <div id="profile-tab" class="tab-content active">
                <div class="settings-grid">
                    <!-- Profile Settings -->
                    <div class="settings-card">
                        <h2><i class="fas fa-user-circle"></i> Personal Profile</h2>
                        <form method="POST">
                            <input type="hidden" name="action" value="update_profile">
                            <div class="form-group">
                                <label>Full Name</label>
                                <input type="text" name="full_name" class="form-input"
                                    value="<?php echo htmlspecialchars($admin['full_name']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Email Address</label>
                                <input type="email" name="email" class="form-input"
                                    value="<?php echo htmlspecialchars($admin['email']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Username</label>
                                <input type="text" class="form-input"
                                    value="<?php echo htmlspecialchars($admin['username']); ?>" disabled
                                    style="background: #f1f5f9;">
                            </div>
                            <button type="submit" class="save-btn">
                                Save Profile <i class="fas fa-save"></i>
                            </button>
                        </form>
                    </div>

                    <!-- Password Settings -->
                    <div class="settings-card">
                        <h2><i class="fas fa-shield-alt"></i> Security & Access</h2>
                        <form method="POST">
                            <input type="hidden" name="action" value="update_password">
                            <div class="form-group">
                                <label>Current Password</label>
                                <input type="password" name="current_password" class="form-input" placeholder="••••••••"
                                    required>
                            </div>
                            <div class="form-group">
                                <label>New Password</label>
                                <input type="password" name="new_password" class="form-input" placeholder="••••••••"
                                    required minlength="8">
                            </div>
                            <div class="form-group">
                                <label>Confirm New Password</label>
                                <input type="password" name="confirm_password" class="form-input" placeholder="••••••••"
                                    required>
                            </div>
                            <button type="submit" class="save-btn">
                                Update Password <i class="fas fa-key"></i>
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- List Accounts Tab -->
            <div id="accounts-tab" class="tab-content">
                <div class="settings-card">
                    <h2><i class="fas fa-users-shield"></i> Registered Super Administrators</h2>
                    <p style="color: var(--text-gray); font-size: 14px; margin-bottom: 25px;">Verified accounts with
                        master system access.</p>

                    <div style="overflow-x: auto;">
                        <table class="account-table">
                            <thead>
                                <tr>
                                    <th>Admin ID</th>
                                    <th>Full Name</th>
                                    <th>Username</th>
                                    <th>Email</th>
                                    <th>Joined Date</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($accounts as $acc): ?>
                                    <tr>
                                        <td style="font-weight: 700;">#<?php echo $acc['id']; ?></td>
                                        <td style="font-weight: 600; color: var(--text-dark);">
                                            <?php echo htmlspecialchars($acc['full_name']); ?>
                                            <?php if ($acc['id'] == $admin_id): ?>
                                                <span
                                                    style="font-size: 10px; background: #0f172a; color: white; padding: 2px 6px; border-radius: 4px; margin-left: 5px;">YOU</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>@<?php echo htmlspecialchars($acc['username']); ?></td>
                                        <td><?php echo htmlspecialchars($acc['email']); ?></td>
                                        <td style="color: var(--text-gray);">
                                            <?php echo date('M d, Y', strtotime($acc['created_at'])); ?>
                                        </td>
                                        <td>
                                            <span class="status-badge status-active">
                                                <i class="fas fa-check-circle"></i> Authorized
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Security Protocols Tab -->
            <div id="protocols-tab" class="tab-content">
                <div class="settings-card">
                    <h2><i class="fas fa-microchip"></i> System Integrity Protocols</h2>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 40px; align-items: start;">
                        <div>
                            <p style="font-size: 14px; color: var(--text-gray); line-height: 1.6; margin-bottom: 20px;">
                                Your <strong>System Bypass Key</strong> is the primary authentication token used to
                                access
                                integrated departmental systems (HR, Logistics, Finance).
                                Regenerating this key will immediately invalidate the previous key across all active
                                sessions.
                            </p>
                            <form method="POST"
                                onsubmit="return confirm('WARNING: All current bypass links will become invalid. Proceed?');">
                                <input type="hidden" name="action" value="regen_api">
                                <button type="submit" class="save-btn" style="background: #0f172a; color: white;">
                                    Regenerate Bypass Key <i class="fas fa-sync"></i>
                                </button>
                            </form>
                        </div>
                        <div class="api-regen-box">
                            <label
                                style="font-size: 12px; font-weight: 700; color: var(--primary-gold); text-transform: uppercase;">Active
                                Bypass Token</label>
                            <code class="api-key-text"><?php echo $api_key; ?></code>
                            <p style="font-size: 11px; color: #64748b; margin-top: 5px;">
                                <i class="fas fa-info-circle"></i> Keep this token confidential. It provides superuser
                                access to all ATIERA modules.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function showTab(tabId) {
            // Hide all contents
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            // Deactivate all buttons
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });

            // Show target content
            document.getElementById(tabId + '-tab').classList.add('active');
            // Activate target button
            event.currentTarget.classList.add('active');
        }
    </script>
</body>

</html>