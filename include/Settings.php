<?php
session_start();
// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

require_once __DIR__ . '/../db/db.php';
require_once __DIR__ . '/../PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../PHPMailer/src/Exception.php';
require_once __DIR__ . '/../PHPMailer/src/SMTP.php';
require_once __DIR__ . '/Config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$pdo = get_pdo();

$message = '';
$error = '';

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        /* Update User */
        if ($_POST['action'] === 'update_user') {
            $id = intval($_POST['user_id']);
            $username = trim($_POST['username']);
            $email = trim($_POST['email']);
            $full_name = trim($_POST['full_name']);
            $password = $_POST['password'];

            try {
                if (!empty($password)) {
                    // Update with password
                    $stmt = $pdo->prepare("UPDATE users SET username=?, email=?, full_name=?, password_hash=? WHERE id=?");
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt->execute([$username, $email, $full_name, $hashed_password, $id]);

                    // Send notification email
                    try {
                        $mail = new PHPMailer(true);
                        $mail->isSMTP();
                        $mail->Host = SMTP_HOST;
                        $mail->SMTPAuth = true;
                        $mail->Username = SMTP_USER;
                        $mail->Password = SMTP_PASS;
                        $mail->Port = SMTP_PORT;
                        $mail->SMTPOptions = array(
                            'ssl' => array('verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true)
                        );
                        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
                        $mail->addAddress($email, $full_name);
                        $mail->isHTML(true);
                        $mail->Subject = 'Security Notice: Your ATIERA Password was Updated';
                        $mail->Body = "
                            <div style=\"font-family: sans-serif; padding: 20px; color: #1e293b; max-width: 500px; margin: auto; border: 1px solid #e2e8f0; border-radius: 12px;\">
                                <h2 style=\"color: #0f172a;\">Password Changed</h2>
                                <p>Hello {$full_name},</p>
                                <p>This is a security notification to let you know that your password for the ATIERA Admin Panel has been updated by an administrator.</p>
                                <p>If you did not authorized this change, please contact your system administrator immediately.</p>
                                <div style=\"margin: 20px 0; text-align: center;\">
                                    <a href=\"" . getBaseUrl() . "/auth/login.php\" style=\"background: #1e40af; color: white; padding: 12px 24px; border-radius: 6px; text-decoration: none; font-weight: bold; display: inline-block;\">Go to Login</a>
                                </div>
                            </div>
                        ";
                        $mail->send();
                    } catch (Exception $e) {
                        // Email fail is secondary
                        error_log("Failed to send password change notification: " . $e->getMessage());
                    }
                } else {
                    // Update without password
                    $stmt = $pdo->prepare("UPDATE users SET username=?, email=?, full_name=? WHERE id=?");
                    $stmt->execute([$username, $email, $full_name, $id]);
                }
                $message = "User updated successfully!";
            } catch (PDOException $e) {
                $error = "Error updating user: " . $e->getMessage();
            }
        }
        /* Delete User */ elseif ($_POST['action'] === 'delete_user') {
            $id = intval($_POST['user_id']);
            // Prevent deleting self?
            if ($id == $_SESSION['user_id']) {
                $error = "You cannot delete your own account.";
            } else {
                try {
                    $stmt = $pdo->prepare("DELETE FROM users WHERE id=?");
                    $stmt->execute([$id]);
                    $message = "User deleted successfully!";
                } catch (PDOException $e) {
                    $error = "Error deleting user: " . $e->getMessage();
                }
            }
        }
        /* Create User */ elseif ($_POST['action'] === 'create_user') {
            $username = trim($_POST['username']);
            $email = trim($_POST['email']);
            $full_name = trim($_POST['full_name']);
            $password = $_POST['password'] ?? '';

            if (!empty($username) && !empty($email)) {
                try {
                    // Check if username or email already exists
                    $check = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
                    $check->execute([$username, $email]);
                    if ($check->fetch()) {
                        $error = "Error: A user with this username or email already exists.";
                    } else {
                        $pdo->beginTransaction();

                        $baseUrl = getBaseUrl();

                        if (!empty($password)) {
                            // 1. Insert user with manual password
                            $stmt = $pdo->prepare("INSERT INTO users (username, email, full_name, password_hash) VALUES (?, ?, ?, ?)");
                            $stmt->execute([$username, $email, $full_name, password_hash($password, PASSWORD_DEFAULT)]);
                            $newUserId = $pdo->lastInsertId();

                            // 2. Generate and save verification code
                            $code = (string) random_int(100000, 999999);
                            $expiresAt = (new DateTime('+24 hours'))->format('Y-m-d H:i:s');
                            $stmt = $pdo->prepare('INSERT INTO email_verifications (user_id, code, expires_at) VALUES (?, ?, ?)');
                            $stmt->execute([$newUserId, $code, $expiresAt]);

                            // 3. Send Email (Picture 1 Style + Activation Code & Link)
                            $mail = new PHPMailer(true);
                            $mail->isSMTP();
                            $mail->Host = SMTP_HOST;
                            $mail->SMTPAuth = true;
                            $mail->Username = SMTP_USER;
                            $mail->Password = SMTP_PASS;
                            $mail->Port = SMTP_PORT;
                            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                            $mail->SMTPOptions = array(
                                'ssl' => array('verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true)
                            );

                            $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
                            $mail->addAddress($email, $full_name);
                            $mail->isHTML(true);
                            $mail->Subject = 'New Account Created: ATIERA Admin Panel';

                            $loginUrl = $baseUrl . "/auth/login.php?verify_new=1&email=" . urlencode($email);

                            $mail->Body = "
                            <div style=\"font-family: sans-serif; padding: 20px; color: #1e293b; max-width: 500px; margin: auto; border: 1px solid #e2e8f0; border-radius: 12px;\">
                                <h2 style=\"color: #0f172a;\">Welcome to ATIERA</h2>
                                <p>Hello " . htmlspecialchars($full_name) . ",</p>
                                <p>An account has been created for you. Here are your credentials:</p>
                                <div style=\"background: #f8fafc; padding: 20px; border-radius: 8px; border: 1px solid #e2e8f0; margin: 20px 0;\">
                                    <p style=\"margin: 0;\"><strong>Username:</strong> " . htmlspecialchars($username) . "</p>
                                    <p style=\"margin: 5px 0 0;\"><strong>Password:</strong> <span style=\"color: #1e40af; font-weight: bold;\">" . htmlspecialchars($password) . "</span></p>
                                </div>
                                <p>To complete your registration and set your <strong>New Password</strong>, please use the code below:</p>
                                <div style=\"text-align: center; margin: 25px 0; background: #fff; padding: 15px; border-radius: 8px; border: 2px dashed #e2e8f0;\">
                                    <span style=\"font-size: 28px; font-weight: bold; letter-spacing: 8px; color: #1e40af;\">{$code}</span>
                                </div>
                                <div style=\"margin: 20px 0; text-align: center;\">
                                    <a href=\"{$loginUrl}\" style=\"background: #1e40af; color: white; padding: 12px 24px; border-radius: 6px; text-decoration: none; font-weight: bold; display: inline-block;\">Activate & Login</a>
                                </div>
                                <p style=\"font-size: 11px; color: #64748b; text-align: center;\">This security link and code expires in 24 hours.</p>
                            </div>
                        ";
                            $mail->send();
                            $message = "User created! Login details sent to <strong>" . htmlspecialchars($email) . "</strong>";
                        } else {
                            // 1. Insert user (placeholder password)
                            $stmt = $pdo->prepare("INSERT INTO users (username, email, full_name, password_hash) VALUES (?, ?, ?, 'PENDING_REGISTRATION')");
                            $stmt->execute([$username, $email, $full_name]);
                            $newUserId = $pdo->lastInsertId();

                            // 2. Generate Verification Code
                            $code = (string) random_int(100000, 999999);
                            $expiresAt = (new DateTime('+24 hours'))->format('Y-m-d H:i:s');
                            $stmt = $pdo->prepare('INSERT INTO email_verifications (user_id, code, expires_at) VALUES (?, ?, ?)');
                            $stmt->execute([$newUserId, $code, $expiresAt]);

                            // 3. Send Invitation
                            $mail = new PHPMailer(true);
                            $mail->isSMTP();
                            $mail->Host = SMTP_HOST;
                            $mail->SMTPAuth = true;
                            $mail->Username = SMTP_USER;
                            $mail->Password = SMTP_PASS;
                            $mail->Port = SMTP_PORT;
                            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                            $mail->SMTPOptions = array(
                                'ssl' => array('verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true)
                            );

                            $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
                            $mail->addAddress($email, $full_name);
                            $mail->isHTML(true);
                            $mail->Subject = 'Setup Your ATIERA Account Password';

                            $loginUrl = $baseUrl . "/auth/login.php?verify_new=1&email=" . urlencode($email);

                            $mail->Body = "
                            <div style=\"font-family: sans-serif; padding: 20px; color: #1e293b; max-width: 500px; margin: auto; border: 1px solid #e2e8f0; border-radius: 12px;\">
                                <h2 style=\"color: #0f172a;\">Setup Your Password</h2>
                                <p>Hello " . htmlspecialchars($full_name) . ",</p>
                                <p>You have been added as an administrator. To complete your account setup, please set your <strong>New Password</strong> using the code below:</p>
                                <div style=\"text-align: center; margin: 30px 0; background: #f8fafc; padding: 20px; border-radius: 8px; border: 2px dashed #e2e8f0;\">
                                    <span style=\"font-size: 32px; font-weight: bold; letter-spacing: 10px; color: #1e40af;\">{$code}</span>
                                </div>
                                <div style=\"margin: 20px 0; text-align: center;\">
                                    <a href=\"{$loginUrl}\" style=\"background: #1e40af; color: white; padding: 12px 24px; border-radius: 6px; text-decoration: none; font-weight: bold; display: inline-block;\">Set New Password</a>
                                </div>
                                <p style=\"font-size: 13px; color: #64748b; text-align: center;\">This security link and code will expire in 24 hours.</p>
                            </div>
                        ";
                            $mail->send();
                            $message = "Invitation sent to <strong>" . htmlspecialchars($email) . "</strong>! They can now activate their account.";
                        }
                        $pdo->commit();
                    }
                } catch (\Exception $e) {
                    if ($pdo->inTransaction())
                        $pdo->rollBack();
                    $error = "Mailer Error: " . (isset($mail) ? $mail->ErrorInfo : $e->getMessage());
                }
            }
        }
    }
}

// Fetch Users
$stmt = $pdo->query("SELECT * FROM users ORDER BY id DESC");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Settings - Admin</title>
    <link rel="icon" type="image/x-icon" href="../assets/image/logo2.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/facilities-reservation.css">
    <style>
        .icon-img-placeholder {
            display: inline-block;
        }

        .dashboard-layout .main-content {
            margin-left: 280px;
        }

        @media screen and (max-width: 991px) {
            .dashboard-layout .main-content {
                margin-left: 0;
            }
        }

        /* Modal logic fix for this page */
        .modal {
            display: none;
            align-items: center;
            justify-content: center;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(5px);
            z-index: 1000;
        }

        .modal.active {
            display: flex;
        }

        /* Loading Overlay Style */
        .loading-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(8px);
            z-index: 9999;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: white;
        }

        .spinner {
            width: 50px;
            height: 50px;
            border: 5px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 1s ease-in-out infinite;
            margin-bottom: 20px;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        .modal-content {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            width: 100%;
            max-width: 500px;
            position: relative;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
        }

        .close-modal {
            position: absolute;
            top: 1.5rem;
            right: 1.5rem;
            font-size: 1.5rem;
            cursor: pointer;
            color: #718096;
        }

        .form-group {
            margin-bottom: 1.25rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
        }
    </style>
</head>

<body class="dashboard-layout">
    <div class="container">
        <?php include 'sidebar.php'; ?>

        <main class="main-content">
            <header class="top-header">
                <div class="header-title">
                    <button class="mobile-menu-btn" onclick="toggleSidebar()">
                        <i class="fas fa-bars"></i>
                    </button>
                    <h1>Account Settings</h1>
                    <span style="color: #718096; margin-left: 10px; font-size: 0.9rem; font-weight: 400;">Manage Admin
                        Accounts and System Users</span>
                </div>
                <div class="header-actions">
                    <div class="user-info" style="display: flex; align-items: center; gap: 10px; font-weight: 600;">
                        <span class="icon-img-placeholder">👤</span> Admin
                    </div>
                </div>
            </header>

            <div class="dashboard-content">
                <?php if ($message): ?>
                    <div class="alert alert-success"
                        style="padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; background: #c6f6d5; color: #22543d; border: 1px solid #9ae6b4; display: flex; align-items: center; gap: 10px;">
                        <span class="icon-img-placeholder">✅</span> <?= htmlspecialchars($message) ?>
                    </div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-error"
                        style="padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; background: #fed7d7; color: #c53030; border: 1px solid #feb2b2; display: flex; align-items: center; gap: 10px;">
                        <span class="icon-img-placeholder">⚠️</span> <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <div class="card"
                    style="background: white; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); padding: 1.5rem;">
                    <div
                        style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem; border-bottom: 1px solid #edf2f7; padding-bottom: 1rem;">
                        <h3 style="color: #2d3748; font-size: 1.5rem; font-weight: 600;">Users List</h3>
                        <button class="btn btn-primary" onclick="openCreateModal()">
                            <span class="icon-img-placeholder">➕</span> Add User
                        </button>
                    </div>

                    <div class="table-container">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th style="text-align: center;">ID</th>
                                    <th style="text-align: center;">FULL NAME</th>
                                    <th style="text-align: center;">USERNAME</th>
                                    <th style="text-align: center;">EMAIL</th>

                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td style="text-align: center; font-weight: 600; color: #718096;">
                                            #<?= $user['id'] ?></td>
                                        <td style="text-align: center; font-weight: 500;">
                                            <?= htmlspecialchars($user['full_name']) ?>
                                        </td>
                                        <td style="text-align: center;"><?= htmlspecialchars($user['username']) ?></td>
                                        <td style="text-align: center;"><?= htmlspecialchars($user['email']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>

        <!-- Edit/Create User Modal -->
        <div class="modal" id="userModal">
            <div class="modal-content">
                <span class="close-modal" onclick="closeModal('userModal')">&times;</span>
                <h3 id="modalTitle" style="margin-top: 0; margin-bottom: 1.5rem; color: var(--primary);">Edit User</h3>
                <form method="POST" id="userForm">
                    <input type="hidden" name="action" id="formAction" value="update_user">
                    <input type="hidden" name="user_id" id="userId">

                    <div class="form-group">
                        <label>Full Name</label>
                        <input type="text" name="full_name" id="fullName" class="form-control" required
                            placeholder="Enter full name">
                    </div>

                    <div class="form-group">
                        <label>Username</label>
                        <input type="text" name="username" id="userName" class="form-control" required
                            placeholder="Choose a username">
                    </div>

                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" id="userEmail" class="form-control" required
                            placeholder="user@example.com">
                    </div>

                    <div class="form-group" id="passwordGroup">
                        <label>Password <small style="font-weight: 400; color: #718096;">(Leave blank to keep
                                unchanged)</small></label>
                        <input type="password" name="password" class="form-control" placeholder="Enter new password">
                    </div>

                    <button type="submit" class="btn btn-primary btn-block" style="margin-top: 1rem;">
                        <span class="icon-img-placeholder">💾</span> Save Changes
                    </button>
                </form>
            </div>
        </div>

        <!-- Delete Confirmation Modal -->
        <div class="modal" id="deleteModal">
            <div class="modal-content" style="max-width:400px; text-align:center;">
                <div style="color: #e53e3e; font-size: 3rem; margin-bottom: 1rem;">
                    <span class="icon-img-placeholder">⚠️</span>
                </div>
                <h3 style="margin-top: 0; color: #2d3748;">Delete User?</h3>
                <p style="color: #718096; margin-bottom: 1.5rem;">Are you sure you want to delete this user? This action
                    cannot be undone.</p>
                <form method="POST">
                    <input type="hidden" name="action" value="delete_user">
                    <input type="hidden" name="user_id" id="deleteUserId">
                    <div style="display:flex; gap:10px; justify-content:center;">
                        <button type="button" class="btn btn-outline" style="flex: 1;"
                            onclick="closeModal('deleteModal')">Cancel</button>
                        <button type="submit" class="btn btn-danger" style="flex: 1;">Delete User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Invitation Loading Overlay -->
    <div id="inviteLoadingOverlay" class="loading-overlay">
        <div class="spinner"></div>
        <h3 style="margin: 0; font-weight: 600; letter-spacing: 0.5px;">Sending Invitation...</h3>
        <p style="opacity: 0.8; margin-top: 10px;">Please wait while we set up the account.</p>
    </div>

    <script src="../assets/Javascript/facilities-reservation.js"></script>
    <script>
        // Form submission loading state
        document.getElementById('userForm').addEventListener('submit', function (e) {
            const action = document.getElementById('formAction').value;
            if (action === 'create_user') {
                document.getElementById('inviteLoadingOverlay').style.display = 'flex';
            }
        });
        function openEditModal(user) {
            document.getElementById('modalTitle').innerText = 'Edit User';
            document.getElementById('formAction').value = 'update_user';
            document.getElementById('userId').value = user.id;
            document.getElementById('fullName').value = user.full_name;
            document.getElementById('userName').value = user.username;
            document.getElementById('userEmail').value = user.email;
            document.getElementById('passwordGroup').style.display = 'block';

            document.getElementById('userModal').classList.add('active');
        }

        function openCreateModal() {
            document.getElementById('modalTitle').innerText = 'Add New User';
            document.getElementById('formAction').value = 'create_user';
            document.getElementById('userId').value = '';
            document.getElementById('userForm').reset();
            document.getElementById('userEmail').value = ''; // Ensure email is empty
            document.getElementById('passwordGroup').style.display = 'block';

            document.getElementById('userModal').classList.add('active');
        }

        function openDeleteModal(id) {
            document.getElementById('deleteUserId').value = id;
            document.getElementById('deleteModal').classList.add('active');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }

        // Close modal on outside click
        window.onclick = function (event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('active');
            }
        }
    </script>
</body>

</html>