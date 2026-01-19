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
            max-width: 1000px;
            margin: 0 auto;
        }

        .settings-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-top: 30px;
        }

        .settings-card {
            background: white;
            padding: 30px;
            border-radius: 24px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
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
                    <i class="fas fa-check-circle"></i>
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

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

            <!-- System Protocols -->
            <div class="settings-card" style="margin-top: 30px;">
                <h2><i class="fas fa-microchip"></i> System Integrity Protocols</h2>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 40px; align-items: start;">
                    <div>
                        <p style="font-size: 14px; color: var(--text-gray); line-height: 1.6; margin-bottom: 20px;">
                            Your <strong>System Bypass Key</strong> is the primary authentication token used to access
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
</body>

</html>