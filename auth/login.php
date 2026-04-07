<?php
// Load shared database connection
require_once __DIR__ . '/../db/db.php';

// Load PHPMailer classes (needed for email verification)
require_once __DIR__ . '/../PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../PHPMailer/src/Exception.php';
require_once __DIR__ . '/../PHPMailer/src/SMTP.php';
require_once __DIR__ . '/../include/Config.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Start session
session_start();

// Handle logout
if (isset($_GET['logout'])) {
  session_destroy();
  header('Location: login.php');
  exit;
}

// Check if already logged in
if (isset($_SESSION['user_id'])) {
  header('Location: ../Modules/dashboard.php');
  exit;
}

$error_message = '';
$success_message = '';
$prefill_email = '';
$show_verify_modal = false;

// Surface success after verification
if (isset($_GET['verified']) && $_GET['verified'] === '1') {
  $success_message = 'Email verified. You can now sign in.';
}

// Show warning if email failed to send
if (isset($_GET['email_failed']) && $_GET['email_failed'] === '1') {
  $error_message = 'Email could not be sent, but your verification code was saved. Please use the "Resend code" button in the verification modal.';
}

// Handle invitation link
if (isset($_GET['verify_new']) && isset($_GET['email'])) {
  $prefill_email = $_GET['email'];
  $show_verify_modal = true;
  $success_message = 'Please enter the 6-digit code from your email and set your password.';
}

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username']) && isset($_POST['password'])) {
  $username = trim($_POST['username']);
  $password = $_POST['password'];

  if (!empty($username) && !empty($password)) {
    try {
      $pdo = get_pdo();

      // Check user credentials - try email first, then username
      $stmt = $pdo->prepare('SELECT id, full_name, username, email, password_hash FROM users WHERE email = :email OR username = :username LIMIT 1');
      $stmt->execute([':email' => $username, ':username' => $username]);
      $user = $stmt->fetch();

      if ($user) {
        // Verify password
        $stored_password = $user['password_hash'];
        $is_hash = str_starts_with($stored_password, '$2y$') || str_starts_with($stored_password, '$argon2');
        $valid = $is_hash ? password_verify($password, $stored_password) : hash_equals($stored_password, $password);

        if ($valid) {
          // Password is correct - show verification modal for all users
          // Store user info in session temporarily (will be set after verification)
          $_SESSION['temp_user_id'] = $user['id'];
          $_SESSION['temp_username'] = $user['username'];
          $_SESSION['temp_name'] = $user['full_name'];
          $_SESSION['temp_email'] = $user['email'];


          // Ensure email_verifications table exists
          try {
            $pdo->exec("
                            CREATE TABLE IF NOT EXISTS email_verifications (
                                id INT AUTO_INCREMENT PRIMARY KEY,
                                user_id INT NOT NULL,
                                code VARCHAR(16) NOT NULL,
                                expires_at DATETIME NOT NULL,
                                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                                INDEX (user_id),
                                INDEX (code),
                                CONSTRAINT fk_ev_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
                        ");
          } catch (\Exception $e) {
            // Table might already exist, ignore
          }

          // Generate and send verification code
          try {
            $code = (string) random_int(100000, 999999);
            $expiresAt = (new DateTime('+15 minutes'))->format('Y-m-d H:i:s');
            $stmt = $pdo->prepare('INSERT INTO email_verifications (user_id, code, expires_at) VALUES (:user_id, :code, :expires_at)');
            $stmt->execute([':user_id' => $user['id'], ':code' => $code, ':expires_at' => $expiresAt]);

            // Send email
            $mail = new PHPMailer(true);
            try {
              $mail->SMTPDebug = 0; // 0 = off, 2 = debug
              $mail->isSMTP();
              $mail->Host = SMTP_HOST;
              $mail->SMTPAuth = true;
              $mail->Username = SMTP_USER;
              $mail->Password = SMTP_PASS;
              $mail->Port = SMTP_PORT;
              $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
              $mail->Timeout = 10;

              // SSL Bypass
              $mail->SMTPOptions = array(
                'ssl' => array(
                  'verify_peer' => false,
                  'verify_peer_name' => false,
                  'allow_self_signed' => true
                )
              );

              $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
              $mail->addAddress($user['email'], $user['full_name'] ?: $user['email']);
              $mail->isHTML(true);
              $mail->Subject = 'Your ATIERA verification code';
              $mail->Body = "
                                <div style=\"font-family:Arial,sans-serif;font-size:14px;line-height:1.6;color:black\">
                                  <h2 style=\"margin:0 0 10px\">Verify your email</h2>
                                  <p>Hello " . htmlspecialchars($user['full_name'] ?: $user['email']) . ",</p>
                                  <p>Use the verification code below to sign in. It expires in 15 minutes.</p>
                                  <p style=\"font-size:18px;font-weight:700;letter-spacing:2px;background:#0f1c49;color:#fff;display:inline-block;padding:8px 12px;border-radius:8px\">{$code}</p>
                                  <p>If you didn't request this, you can ignore this email.</p>
                                  <p>— ATIERA</p>
                                </div>
                            ";
              $mail->AltBody = "Your ATIERA verification code is: {$code}\nThis code expires in 15 minutes.";
              $mail->send();
            } catch (\Exception $e) {
              error_log("Email send failed during login for {$user['email']}: " . $e->getMessage() . " (Mailer info: " . $mail->ErrorInfo . ")");
              $error_message = "Could not send verification email. Mailer error: " . $mail->ErrorInfo;
            }
          } catch (\Exception $e) {
            // Code generation or database insert failed
            // Still show modal, user can use resend
          }

          $prefill_email = $user['email'];
          $show_verify_modal = true;
          $success_message = 'Verification code sent to your email. Please check and enter the code.';
        } else {
          $error_message = 'Invalid password.';
        }
      } else {
        $error_message = 'Invalid email/username or account not found.';
      }
    } catch (\Exception $e) {
      $error_message = 'Database error. Please try again.';
    }
  } else {
    $error_message = 'Please enter both email and password.';
  }
}
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>ATIERA — Secure Login</title>
  <link rel="icon" href="../assets/image/logo2.png">
  <script src="https://cdn.tailwindcss.com"></script>

  <style>
    :root {
      --blue-600: #1b2f73;
      --blue-700: #15265e;
      --blue-800: #0f1c49;
      --blue-a: #2342a6;
      --gold: #d4af37;
      --ink: #0f172a;
      --muted: #64748b;
      --ring: 0 0 0 3px rgba(35, 66, 166, .28);
      --card-bg: rgba(255, 255, 255, .95);
      --card-border: rgba(226, 232, 240, .9);
      --wm-opa-light: .35;
      --wm-opa-dark: .55;
    }

    @media (prefers-color-scheme: dark) {
      :root {
        --ink: #e5e7eb;
        --muted: #9ca3af;
      }
    }

    /* ===== Background ===== */
    body {
      min-height: 100svh;
      margin: 0;
      color: var(--ink);
      background:
        radial-gradient(70% 60% at 8% 10%, rgba(255, 255, 255, .18) 0, transparent 60%),
        radial-gradient(40% 40% at 100% 0%, rgba(212, 175, 55, .08) 0, transparent 40%),
        linear-gradient(140deg, rgba(15, 28, 73, 1) 50%, rgba(255, 255, 255, 1) 50%);
    }

    html.dark body {
      background:
        radial-gradient(70% 60% at 8% 10%, rgba(212, 175, 55, .08) 0, transparent 60%),
        radial-gradient(40% 40% at 100% 0%, rgba(212, 175, 55, .12) 0, transparent 40%),
        linear-gradient(140deg, rgba(7, 12, 38, 1) 50%, rgba(11, 21, 56, 1) 50%);
      color: #e5e7eb;
    }

    /* ===== Watermark ===== */
    .bg-watermark {
      position: fixed;
      inset: 0;
      z-index: -1;
      display: grid;
      place-items: center;
      pointer-events: none;
    }

    .bg-watermark img {
      width: min(820px, 70vw);
      max-height: 68vh;
      object-fit: contain;
      opacity: var(--wm-opa-light);
      filter: drop-shadow(0 0 26px rgba(255, 255, 255, .40)) drop-shadow(0 14px 34px rgba(0, 0, 0, .25));
      transition: opacity .25s ease, filter .25s ease, transform .6s ease;
    }

    html.dark .bg-watermark img {
      opacity: var(--wm-opa-dark);
      filter: drop-shadow(0 0 34px rgba(255, 255, 255, .55)) drop-shadow(0 16px 40px rgba(0, 0, 0, .30));
    }

    .reveal {
      opacity: 0;
      transform: translateY(8px);
      animation: reveal .45s .05s both;
    }

    @keyframes reveal {
      to {
        opacity: 1;
        transform: none;
      }
    }

    /* ===== Card ===== */
    .card {
      background: var(--card-bg);
      -webkit-backdrop-filter: blur(12px);
      backdrop-filter: blur(12px);
      border: 1px solid var(--card-border);
      border-radius: 18px;
      box-shadow: 0 16px 48px rgba(2, 6, 23, .18);
    }

    html.dark .card {
      background: rgba(17, 24, 39, .92);
      border-color: rgba(71, 85, 105, .55);
      box-shadow: 0 16px 48px rgba(0, 0, 0, .5);
    }

    /* ===== Inputs ===== */
    .field {
      position: relative;
    }

    .input {
      width: 100%;
      border: 1px solid #e5e7eb;
      border-radius: 12px;
      background: #fff;
      padding: 1rem 2.6rem 1rem .95rem;
      outline: none;
      color: #0f172a;
      transition: border-color .15s, box-shadow .15s, background .15s;
    }

    .input:focus {
      border-color: var(--blue-a);
      box-shadow: var(--ring)
    }

    html.dark .input {
      background: #0b1220;
      border-color: #243041;
      color: #e5e7eb;
    }

    .float-label {
      position: absolute;
      left: .9rem;
      top: 50%;
      transform: translateY(-50%);
      padding: 0 .25rem;
      color: #94a3b8;
      pointer-events: none;
      background: transparent;
      transition: all .15s ease;
    }

    .input:focus+.float-label,
    .input:not(:placeholder-shown)+.float-label {
      top: 0;
      transform: translateY(-50%) scale(.92);
      color: var(--blue-a);
      background: #fff;
    }

    html.dark .input:focus+.float-label,
    html.dark .input:not(:placeholder-shown)+.float-label {
      background: #0b1220;
    }

    .icon-right {
      position: absolute;
      right: .6rem;
      top: 50%;
      transform: translateY(-50%);
      color: #64748b;
    }

    html.dark .icon-right {
      color: #94a3b8;
    }

    /* ===== Buttons ===== */
    .btn {
      width: 100%;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: .6rem;
      background: linear-gradient(180deg, var(--blue-600), var(--blue-800));
      color: #fff;
      font-weight: 800;
      border-radius: 14px;
      padding: .95rem 1rem;
      border: 1px solid rgba(255, 255, 255, .06);
      transition: transform .08s ease, filter .15s ease, box-shadow .2s ease;
      box-shadow: 0 8px 18px rgba(2, 6, 23, .18);
    }

    .btn:hover {
      filter: saturate(1.08);
      box-shadow: 0 12px 26px rgba(2, 6, 23, .26);
    }

    .btn:active {
      transform: translateY(1px) scale(.99);
    }

    .btn[disabled] {
      opacity: .85;
      cursor: not-allowed;
    }

    /* ===== Alerts (inline attempts/info) ===== */
    .alert {
      border-radius: 12px;
      padding: .65rem .8rem;
      font-size: .9rem
    }

    .alert-error {
      border: 1px solid #fecaca;
      background: #fef2f2;
      color: #b91c1c
    }

    .alert-info {
      border: 1px solid #c7d2fe;
      background: #eef2ff;
      color: #3730a3
    }

    html.dark .alert-error {
      background: #3f1b1b;
      border-color: #7f1d1d;
      color: #fecaca
    }

    html.dark .alert-info {
      background: #1e1b4b;
      border-color: #3730a3;
      color: #c7d2fe
    }

    /* ===== Popup animations (slow) ===== */
    @keyframes popSpring {
      0% {
        transform: scale(.92);
        opacity: 0
      }

      60% {
        transform: scale(1.04);
        opacity: 1
      }

      85% {
        transform: scale(.98)
      }

      100% {
        transform: scale(1)
      }
    }

    @keyframes fadeBackdrop {
      from {
        opacity: 0
      }

      to {
        opacity: 1
      }
    }

    @keyframes ripple {
      0% {
        transform: scale(.6);
        opacity: .35
      }

      70% {
        transform: scale(1.4);
        opacity: .18
      }

      100% {
        transform: scale(1.8);
        opacity: 0
      }
    }

    @keyframes slideUp {
      from {
        transform: translateY(6px)
      }

      to {
        transform: translateY(0)
      }
    }

    @keyframes shakeX {

      0%,
      100% {
        transform: translateX(0)
      }

      20% {
        transform: translateX(-8px)
      }

      40% {
        transform: translateX(6px)
      }

      60% {
        transform: translateX(-4px)
      }

      80% {
        transform: translateX(2px)
      }
    }

    @media (prefers-reduced-motion: reduce) {

      #popupCard,
      #popupBackdrop,
      #popupTitle,
      #popupMsg {
        animation: none !important
      }
    }

    .popup-success #popupIconWrap {
      background: linear-gradient(180deg, #16a34a, #15803d)
    }

    .popup-info #popupIconWrap {
      background: linear-gradient(180deg, #2563eb, #1d4ed8)
    }

    .popup-error #popupIconWrap {
      background: linear-gradient(180deg, #ef4444, #b91c1c)
    }

    .popup-goodbye #popupIconWrap {
      background: linear-gradient(180deg, var(--blue-600), var(--blue-800))
    }

    .typing::after {
      content: '|';
      margin-left: 2px;
      opacity: .6;
      animation: blink 1s steps(1) infinite;
    }

    @keyframes blink {
      50% {
        opacity: 0
      }
    }
  </style>
</head>

<body class="grid md:grid-cols-2 gap-0 place-items-center p-6 md:p-10">

  <!-- Watermark -->
  <div class="bg-watermark" aria-hidden="true">
    <img src="../assets/image/logo.png" alt="ATIERA watermark" id="wm">
  </div>

  <!-- Left panel -->
  <section class="hidden md:flex w-full h-full items-center justify-center">
    <div class="max-w-lg text-white px-6 reveal">
      <img src="../assets/image/logo.png" alt="ATIERA" class="w-56 mb-6 drop-shadow-xl select-none" draggable="false">
      <h1 class="text-4xl font-extrabold leading-tight tracking-tight">
        ATIERA <span style="color:var(--gold)">HOTEL & RESTAURANT</span> Management
      </h1>
      <p class="mt-4 text-white/90 text-lg">Secure • Fast • Intuitive</p>
    </div>
  </section>

  <!-- Right: Login -->
  <main class="w-full max-w-md md:ml-auto">
    <div id="card" class="card p-6 sm:p-8 reveal">
      <div class="flex items-center justify-between mb-4">
        <div class="md:hidden flex items-center gap-3">
          <img src="../assets/image/logo.png" alt="ATIERA" class="h-10 w-auto">
          <div>
            <div class="text-sm font-semibold leading-4">ATIERA Finance Suite</div>
            <div class="text-[10px] text-[color:var(--muted)]">Blue • White • <span class="font-medium"
                style="color:var(--gold)">Gold</span></div>
          </div>
        </div>
        <button id="modeBtn"
          class="px-3 py-2 rounded-lg border border-slate-200 text-sm hover:bg-white/60 dark:hover:bg-slate-800"
          aria-pressed="false" title="Toggle dark mode">🌓</button>
      </div>

      <h3 class="text-lg sm:text-xl font-semibold mb-1">Sign in</h3>
      <p class="text-sm text-slate-500 dark:text-slate-400 mb-4">Use your administrator credentials to continue.</p>

      <!-- Error and success messages -->
      <?php if (!empty($error_message)): ?>
        <div class="alert alert-error mb-2" role="alert">
          <?php echo htmlspecialchars($error_message); ?>
        </div>
      <?php endif; ?>

      <?php if (!empty($success_message)): ?>
        <div class="alert alert-info mb-4" role="status">
          <?php echo htmlspecialchars($success_message); ?>
        </div>
      <?php endif; ?>

      <form method="POST" class="space-y-4" novalidate>
        <!-- Email/Username -->
        <div class="field">
          <input id="username" name="username" type="email" autocomplete="email" class="input peer" placeholder=" "
            required aria-describedby="userHelp">
          <label for="username" class="float-label">Email Address</label>
          <span class="icon-right" aria-hidden="true">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
              <path
                d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"
                fill="currentColor" />
            </svg>
          </span>
          <p id="userHelp" class="mt-1 text-xs text-slate-500 dark:text-slate-400">e.g., <span
              class="font-mono">admin@atiera-hotel.com</span> or <span class="font-mono">admin</span></p>
        </div>

        <!-- Password -->
        <div>
          <div class="flex items-center justify-between mb-1">
            <label for="password" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Password</label>
            <span id="capsNote"
              class="hidden text-xs px-2 py-0.5 rounded bg-amber-50 border border-amber-300 text-amber-800 dark:bg-amber-900/30 dark:border-amber-700 dark:text-amber-200">Caps
              Lock is ON</span>
          </div>
          <div class="field">
            <input id="password" name="password" type="password" autocomplete="current-password"
              class="input pr-12 peer" placeholder=" " required>
            <label for="password" class="float-label">••••••••</label>
            <div class="icon-right flex items-center gap-1">
              <button type="button" id="togglePw"
                class="w-9 h-9 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 flex items-center justify-center"
                aria-label="Show password" aria-pressed="false" title="Show/Hide password">
                <svg id="eyeOn" width="18" height="18" viewBox="0 0 24 24" fill="none">
                  <path
                    d="M12 5C7 5 2.73 8.11 1 12c1.73 3.89 6 7 11 7s9.27-3.11 11-7c-1.73-3.89-6-7-11-7Zm0 11a4 4 0 1 1 4-4 4 4 0 0 1-4 4Z"
                    fill="currentColor" />
                </svg>
                <svg id="eyeOff" class="hidden" width="18" height="18" viewBox="0 0 24 24" fill="none">
                  <path
                    d="M3 4.27 4.28 3 21 19.72 19.73 21l-2.2-2.2A11.73 11.73 0 0 1 12 19c-5 0-9.27-3.11-11-7a12.71 12.71 0 0 1 4.1-4.73L3 4.27ZM12 7a5 5 0 0 1 5 5 5 5 0 0 1-.46 2.11L14.6 12.17A2.5 2.5 0 0 0 11.83 9.4L9.9 7.46A4.84 4.84 0 0 1 12 7Z"
                    fill="currentColor" />
                </svg>
              </button>
            </div>
          </div>

          <!-- Strength meter -->
          <div class="mt-2 flex items-center gap-2">
            <div class="h-1.5 flex-1 rounded bg-slate-200 dark:bg-slate-700 overflow-hidden" aria-hidden="true">
              <div id="pwBar" class="h-full w-1/12 bg-blue-600 dark:bg-blue-500"></div>
            </div>
            <span id="pwLabel" class="text-xs text-slate-500 dark:text-slate-400 w-14 text-right">weak</span>
          </div>
        </div>

        <!-- Submit -->
        <button id="submitBtn" type="submit" class="btn" aria-live="polite">
          <span id="btnText">Login</span>
        </button>

        <p class="text-xs text-center text-slate-500 dark:text-slate-400">© 2025 ATIERA BSIT 4101 CLUSTER 1</p>

      </form>
    </div>
  </main>

  <!-- ===== Center Popup (success/goodbye only; slow animations) ===== -->
  <div id="popupBackdrop" class="hidden fixed inset-0 z-[60] bg-black/40 backdrop-blur-[2px] will-change-[opacity]">
  </div>
  <div id="popupRoot" class="hidden fixed inset-0 z-[61] grid place-items-center pointer-events-none">
    <div id="popupCard" class="pointer-events-auto w-[92%] max-w-sm rounded-2xl p-6 card shadow-2xl opacity-0 scale-95"
      role="alertdialog" aria-modal="true" aria-labelledby="popupTitle" aria-describedby="popupMsg">
      <div id="popupIconWrap"
        class="mx-auto mb-3 w-14 h-14 rounded-full flex items-center justify-center text-white relative overflow-visible"
        style="background:linear-gradient(180deg,var(--blue-600),var(--blue-800)); box-shadow:0 10px 18px rgba(2,6,23,.18)">
        <svg id="popupIcon" width="26" height="26" viewBox="0 0 24 24" fill="none">
          <path d="M9.5 16.2 5.8 12.5l-1.3 1.3 5 5 10-10-1.3-1.3-8.7 8.7Z" fill="currentColor" />
        </svg>
        <span id="iconRipple" class="absolute inset-0 rounded-full border border-white/60 opacity-0"></span>
      </div>
      <h4 id="popupTitle" class="text-xl font-extrabold text-center" style="color:var(--gold)">Hello, Admin 👋</h4>
      <p id="popupMsg" class="mt-1 text-sm text-center text-slate-600 dark:text-slate-400 typing"></p>
      <div class="mt-4 flex justify-center">
        <button id="popupOkBtn" class="btn !w-auto px-4 py-2 text-sm">OK</button>
      </div>
    </div>
  </div>

  <!-- ===== Verify Email Modal ===== -->
  <div id="verifyBackdrop" class="hidden fixed inset-0 z-[70] bg-black/40 backdrop-blur-[2px]"></div>
  <div id="verifyModal" class="hidden fixed inset-0 z-[71] grid place-items-center">
    <div class="w-[92%] max-w-md card p-6">
      <div class="flex items-center justify-between mb-2">
        <h4 class="text-lg font-bold" style="color: black;">Verify your email</h4>
        <button id="closeVerify" class="px-2 py-1 rounded border text-sm"
          style="color: red; border-color: red;">✕</button>
      </div>
      <p class="text-sm text-slate-600 dark:text-slate-400 mb-3">Enter the 6-digit code sent to your email.</p>
      <form id="verifyForm" method="POST" class="space-y-3" novalidate>
        <input type="hidden" name="action" value="verify">
        <div>
          <label for="vemail" class="block text-sm font-medium mb-1 text-slate-700 dark:text-slate-300">Email</label>
          <input id="vemail" name="email" type="email" required class="input" placeholder="you@example.com"
            value="<?php echo htmlspecialchars($prefill_email); ?>" readonly>
        </div>
        <div>
          <label for="vcode" class="block text-sm font-medium mb-1 text-slate-700 dark:text-slate-300">Verification
            code</label>
          <input id="vcode" name="code" type="text" inputmode="numeric" maxlength="6" required class="input"
            placeholder="123456" autocomplete="one-time-code">
        </div>
        <div id="regPassFields" class="hidden space-y-3">
          <!-- New Password with Eye -->
          <div>
            <label for="regPass" class="block text-sm font-medium mb-1 text-slate-700 dark:text-slate-300">New
              Password</label>
            <div class="relative">
              <input id="regPass" name="new_password" type="password" class="input pr-10"
                placeholder="At least 6 characters">
              <button type="button"
                class="absolute right-2 h-full flex items-center justify-center text-slate-500 hover:text-slate-700 dark:text-slate-400"
                onclick="toggleVerifyPass('regPass', this)" style="top:0; bottom:0;">
                <svg class="eye-on w-5 h-5" width="20" height="20" viewBox="0 0 24 24" fill="none">
                  <path
                    d="M12 5C7 5 2.73 8.11 1 12c1.73 3.89 6 7 11 7s9.27-3.11 11-7c-1.73-3.89-6-7-11-7Zm0 11a4 4 0 1 1 4-4 4 4 0 0 1-4 4Z"
                    fill="currentColor" />
                </svg>
                <svg class="eye-off w-5 h-5 hidden" width="20" height="20" viewBox="0 0 24 24" fill="none">
                  <path
                    d="M3 4.27 4.28 3 21 19.72 19.73 21l-2.2-2.2A11.73 11.73 0 0 1 12 19c-5 0-9.27-3.11-11-7a12.71 12.71 0 0 1 4.1-4.73L3 4.27ZM12 7a5 5 0 0 1 5 5 5 5 0 0 1-.46 2.11L14.6 12.17A2.5 2.5 0 0 0 11.83 9.4L9.9 7.46A4.84 4.84 0 0 1 12 7Z"
                    fill="currentColor" />
                </svg>
              </button>
            </div>
          </div>
          <!-- Confirm Password with Eye -->
          <div>
            <label for="regPassConfirm"
              class="block text-sm font-medium mb-1 text-slate-700 dark:text-slate-300">Confirm Password</label>
            <div class="relative">
              <input id="regPassConfirm" name="confirm_password" type="password" class="input pr-10"
                placeholder="Repeat password">
              <button type="button"
                class="absolute right-2 h-full flex items-center justify-center text-slate-500 hover:text-slate-700 dark:text-slate-400"
                onclick="toggleVerifyPass('regPassConfirm', this)" style="top:0; bottom:0;">
                <svg class="eye-on w-5 h-5" width="20" height="20" viewBox="0 0 24 24" fill="none">
                  <path
                    d="M12 5C7 5 2.73 8.11 1 12c1.73 3.89 6 7 11 7s9.27-3.11 11-7c-1.73-3.89-6-7-11-7Zm0 11a4 4 0 1 1 4-4 4 4 0 0 1-4 4Z"
                    fill="currentColor" />
                </svg>
                <svg class="eye-off w-5 h-5 hidden" width="20" height="20" viewBox="0 0 24 24" fill="none">
                  <path
                    d="M3 4.27 4.28 3 21 19.72 19.73 21l-2.2-2.2A11.73 11.73 0 0 1 12 19c-5 0-9.27-3.11-11-7a12.71 12.71 0 0 1 4.1-4.73L3 4.27ZM12 7a5 5 0 0 1 5 5 5 5 0 0 1-.46 2.11L14.6 12.17A2.5 2.5 0 0 0 11.83 9.4L9.9 7.46A4.84 4.84 0 0 1 12 7Z"
                    fill="currentColor" />
                </svg>
              </button>
            </div>
          </div>
        </div>
        <div id="verifyMsg" class="text-xs min-h-[20px]"></div>
        <div class="flex items-center gap-2">
          <button type="submit" class="btn !w-auto px-4 text-white" id="verifySubmitBtn"
            style="color: white !important;">Verify & Complete</button>
          <button type="button" id="resendBtn" class="px-3 py-2 rounded-lg border text-sm" style="color: black;">Resend
            code</button>
        </div>
      </form>
    </div>
  </div>

  <script>
    const $ = (s, r = document) => r.querySelector(s);

    // Toggler for dynamic password fields in verify modal
    window.toggleVerifyPass = (id, btn) => {
      const inp = document.getElementById(id);
      const show = inp.type === 'password';
      inp.type = show ? 'text' : 'password';
      btn.querySelector('.eye-on').classList.toggle('hidden', show);
      btn.querySelector('.eye-off').classList.toggle('hidden', !show);
    };

    // Elements
    const form = $('#loginForm');
    const userEl = $('#username');
    const pwEl = $('#password');
    const toggle = $('#togglePw');
    const eyeOn = $('#eyeOn');
    const eyeOff = $('#eyeOff');
    const alertBox = $('#alert');
    const infoBox = $('#info');
    const submitBtn = $('#submitBtn');
    const btnText = $('#btnText');
    const capsNote = $('#capsNote');
    const pwBar = $('#pwBar');
    const pwLabel = $('#pwLabel');
    const modeBtn = $('#modeBtn');
    const wmImg = $('#wm');
    // Verify modal elements
    const verifyModal = $('#verifyModal');
    const verifyBackdrop = $('#verifyBackdrop');
    const openVerifyBtn = $('#openVerify');
    const closeVerifyBtn = $('#closeVerify');
    const verifyForm = $('#verifyForm');
    const resendBtn = $('#resendBtn');
    const vemail = $('#vemail');
    const vcode = $('#vcode');
    const verifyMsg = $('#verifyMsg');

    /* ---------- Dark mode toggle ---------- */
    modeBtn.addEventListener('click', () => {
      const root = document.documentElement;
      const dark = root.classList.toggle('dark');
      modeBtn.setAttribute('aria-pressed', String(dark));
      wmImg.style.transform = 'scale(1.01)'; setTimeout(() => wmImg.style.transform = '', 220);
    });

    /* ---------- Alerts helpers ---------- */
    const showError = (msg) => { alertBox.textContent = msg; alertBox.classList.remove('hidden'); };
    const hideError = () => alertBox.classList.add('hidden');
    const showInfo = (msg) => { infoBox.textContent = msg; infoBox.classList.remove('hidden'); };
    const hideInfo = () => infoBox.classList.add('hidden');

    // Auto-open verify modal if needed
    window.addEventListener('load', () => {
      const urlParams = new URLSearchParams(window.location.search);
      if (urlParams.get('verify_new') === '1' || <?php echo $show_verify_modal ? 'true' : 'false'; ?>) {
        verifyBackdrop.classList.remove('hidden');
        verifyModal.classList.remove('hidden');
        if (urlParams.get('verify_new') === '1') {
          $('#regPassFields').classList.remove('hidden');
          $('#verifySubmitBtn').textContent = 'Complete Registration';
        }
      }
    });

    /* ---------- Caps Lock chip ---------- */
    function caps(e) {
      const on = e.getModifierState && e.getModifierState('CapsLock');
      if (capsNote) capsNote.classList.toggle('hidden', !on);
    }
    pwEl.addEventListener('keyup', caps);
    pwEl.addEventListener('keydown', caps);

    /* ---------- Password meter ---------- */
    pwEl.addEventListener('input', () => {
      const v = pwEl.value;
      let score = 0;
      if (v.length >= 6) score++;
      if (/[A-Z]/.test(v)) score++;
      if (/[0-9]/.test(v)) score++;
      if (/[^A-Za-z0-9]/.test(v)) score++;
      const widths = ['12%', '38%', '64%', '88%', '100%'];
      const labels = ['weak', 'fair', 'okay', 'good', 'strong'];
      pwBar.style.width = widths[score];
      pwLabel.textContent = labels[score];
    });

    /* ---------- Show/Hide password ---------- */
    toggle.addEventListener('click', () => {
      const show = pwEl.type === 'password';
      pwEl.type = show ? 'text' : 'password';
      toggle.setAttribute('aria-pressed', String(show));
      toggle.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
      eyeOn.classList.toggle('hidden', show);
      eyeOff.classList.toggle('hidden', !show);
      pwEl.focus();
    });





    /* ---------- Auth + lockout ---------- */
    const MAX_TRIES = 5, LOCK_MS = 60_000;
    const triesKey = 'atiera_login_tries';
    const lockKey = 'atiera_login_lock';
    let lockTimer = null;

    const num = key => Number(localStorage.getItem(key) || '0');
    const setNum = (key, val) => localStorage.setItem(key, String(val));

    function mmss(ms) {
      const s = Math.max(0, Math.ceil(ms / 1000));
      const m = Math.floor(s / 60);
      const r = s % 60;
      return (m ? `${m}:${String(r).padStart(2, '0')}` : `${r}s`);
    }

    function startLockCountdown(until) {
      clearInterval(lockTimer);
      submitBtn.disabled = true;
      const tick = () => {
        const left = until - Date.now();
        if (left <= 0) {
          clearInterval(lockTimer);
          localStorage.removeItem(lockKey);
          setNum(triesKey, 0);
          submitBtn.disabled = false;
          btnText.textContent = 'Login';
          hideError(); hideInfo();
          return;
        }
        btnText.textContent = `Locked ${mmss(left)}`;
        showError(`Too many attempts. Try again in ${mmss(left)}.`);
      };
      tick();
      lockTimer = setInterval(tick, 250);
    }

    function checkLock() {
      const until = Number(localStorage.getItem(lockKey) || '0');
      if (until > Date.now()) { startLockCountdown(until); return true; }
      return false;
    }

    function startLoading() { submitBtn.disabled = true; btnText.textContent = 'Checking…'; }
    function stopLoading(ok = false) {
      if (ok) { btnText.textContent = 'Success'; }
      else { btnText.textContent = 'Login'; submitBtn.disabled = false; }
    }

    function shakeCard() {
      const card = document.getElementById('card');
      card.style.animation = 'shakeX .35s ease-in-out';
      setTimeout(() => card.style.animation = '', 360);
    }

    /* ---------- LOGIN SUBMIT ---------- */
    // Form is now handled by PHP POST method, no JavaScript needed

    // Resume countdown if locked
    checkLock();

    /* ---------- Verify Modal ---------- */
    function openVerify() {
      verifyModal.classList.remove('hidden');
      verifyBackdrop.classList.remove('hidden');

      const urlParams = new URLSearchParams(location.search);
      if (urlParams.get('verify_new') === '1') {
        document.getElementById('regPassFields').classList.remove('hidden');
        document.getElementById('verifySubmitBtn').textContent = 'Set Password & Login';
      } else {
        document.getElementById('regPassFields').classList.add('hidden');
        document.getElementById('verifySubmitBtn').textContent = 'Verify';
      }

      setTimeout(() => vcode?.focus(), 50);
    }
    function closeVerify() {
      verifyModal.classList.add('hidden');
      verifyBackdrop.classList.add('hidden');
      verifyMsg.textContent = '';
    }
    openVerifyBtn?.addEventListener('click', openVerify);
    closeVerifyBtn?.addEventListener('click', closeVerify);
    verifyBackdrop?.addEventListener('click', closeVerify);
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeVerify(); });

    // Auto-open from server flag or query (when coming from register)
    const serverShowVerify = <?php echo $show_verify_modal ? 'true' : 'false'; ?>;
    const urlParams = new URLSearchParams(location.search);
    if (serverShowVerify || urlParams.get('verify') === '1' || urlParams.get('verify_new') === '1') {
      const pre = '<?php echo htmlspecialchars($prefill_email, ENT_QUOTES); ?>' || urlParams.get('email') || '';
      if (pre) vemail.value = pre;
      openVerify();
    }

    // Resend verification code
    resendBtn?.addEventListener('click', async () => {
      verifyMsg.textContent = 'Sending...';
      verifyMsg.className = 'text-xs text-slate-500';
      const email = vemail.value.trim();
      if (!email) {
        verifyMsg.textContent = 'Enter your email first.';
        verifyMsg.className = 'text-xs text-red-600';
        return;
      }
      try {
        const res = await fetch('verify.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: new URLSearchParams({ action: 'resend', email })
        });
        const data = await res.json();
        if (data?.ok) {
          verifyMsg.textContent = data.message || 'Verification code sent to your email.';
          verifyMsg.className = 'text-xs text-green-600';
        } else {
          verifyMsg.textContent = data?.message || 'Failed to send verification code.';
          verifyMsg.className = 'text-xs text-red-600';
        }
      } catch {
        verifyMsg.textContent = 'Network error. Please try again.';
        verifyMsg.className = 'text-xs text-red-600';
      }
    });

    // Handle verification code input validation
    vcode?.addEventListener('input', function () {
      const code = this.value.trim();
      // Only allow numbers
      this.value = code.replace(/\D/g, '');

      // Show error if not 6 digits
      if (this.value.length > 0 && this.value.length !== 6) {
        verifyMsg.textContent = 'Please enter a 6-digit code.';
        verifyMsg.className = 'text-xs text-red-600';
      } else if (this.value.length === 6) {
        verifyMsg.textContent = '';
        verifyMsg.className = 'text-xs';
      }
    });

    vcode?.addEventListener('blur', function () {
      const code = this.value.trim();
      if (code.length > 0 && code.length !== 6) {
        verifyMsg.textContent = 'Please enter a 6-digit code.';
        verifyMsg.className = 'text-xs text-red-600';
      }
    });

    // Handle verification form submission via AJAX
    verifyForm?.addEventListener('submit', async (e) => {
      e.preventDefault();
      const email = vemail.value.trim();
      const code = vcode.value.trim();
      const regPassFields = $('#regPassFields');
      const isReg = !regPassFields.classList.contains('hidden');
      const submitBtn = document.getElementById('verifySubmitBtn');

      if (!email || !code || code.length !== 6 || !/^\d{6}$/.test(code)) {
        verifyMsg.textContent = 'Please enter a valid 6-digit code.';
        verifyMsg.className = 'text-xs text-red-600';
        vcode.focus();
        return;
      }

      const params = { action: isReg ? 'complete_registration' : 'verify', email, code };

      if (isReg) {
        const pass = $('#regPass').value;
        const conf = $('#regPassConfirm').value;
        if (!pass || pass.length < 6) {
          verifyMsg.textContent = 'Password must be at least 6 characters.';
          verifyMsg.className = 'text-xs text-red-600';
          return;
        }
        if (pass !== conf) {
          verifyMsg.textContent = 'Passwords do not match.';
          verifyMsg.className = 'text-xs text-red-600';
          return;
        }
        params.new_password = pass;
      }

      submitBtn.disabled = true;
      submitBtn.textContent = 'Verifying...';
      verifyMsg.textContent = 'Verifying code...';
      verifyMsg.className = 'text-xs text-slate-500';

      try {
        const res = await fetch('verify.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: new URLSearchParams(params)
        });
        const data = await res.json();

        if (data?.ok) {
          verifyMsg.textContent = data.message || 'Verification successful! Redirecting...';
          verifyMsg.className = 'text-xs text-green-600';

          // Redirect to dashboard
          setTimeout(() => {
            if (data.redirect) {
              window.location.href = data.redirect;
            } else {
              window.location.href = '../Modules/dashboard.php';
            }
          }, 1000);
        } else {
          verifyMsg.textContent = data?.message || 'Invalid or expired verification code.';
          verifyMsg.className = 'text-xs text-red-600';
          submitBtn.disabled = false;
          submitBtn.textContent = isReg ? 'Complete Registration' : 'Verify';
          vcode.value = '';
          vcode.focus();
        }
      } catch (err) {
        verifyMsg.textContent = 'Network error. Please try again.';
        verifyMsg.className = 'text-xs text-red-600';
        submitBtn.disabled = false;
        submitBtn.textContent = isReg ? 'Complete Registration' : 'Verify';
      }
    });
  </script>


</body>

</html>