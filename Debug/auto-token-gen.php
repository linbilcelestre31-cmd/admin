<?php
/**
 * ====================================================================
 * SSO TOKEN AUTO-GENERATOR (TESTING TOOL)
 * ====================================================================
 * PAANO ITO GAMITIN:
 * 1. Piliin ang Department (e.g., HR3) na gusto mong i-login.
 * 2. I-click ang "Generate Token" button.
 * 3. Ang tool na ito ang kukuha ng 'Secret Key' sa database mo.
 * 4. Gagawa ito ng 'Signature' gamit ang HMAC-SHA256 para sa security.
 * 5. Ang resultang 'Token' ay ang mahabang string na nása Base64 format.
 * 
 * SAAN ITO NILALAGAY?
 * - Kung gagamit ka ng external link, ang token ay nilalagay sa URL:
 *   Example: sso-login.php?token=[DITO_ILALAGAY_ANG_GENERATED_TOKEN]
 * 
 * - O kaya, i-click lang ang "Direct Login Link" sa baba para ma-test agad.
 * ====================================================================
 */

session_start();
require "../connections.php"; // Kumokonekta sa database para makuha ang secret keys

// Default user data para sa testing (Kinukuha sa session kung nása Dashboard ka)
$user_email = $_SESSION['email'] ?? 'admin@atiera.com';
$user_role = $_SESSION['role'] ?? 'super_admin';

$departments = ['HR1', 'HR2', 'HR3', 'HR4', 'CORE1', 'CORE2'];
$selected_dept = $_POST['dept'] ?? 'HR3';

// 1. KUNIN ANG SECRET KEY MULA SA DATABASE
$stmt = $conn->prepare("SELECT secret_key FROM department_secrets WHERE department=? AND is_active=1 LIMIT 1");
$stmt->bind_param("s", $selected_dept);
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();

// Kung wala pang secret sa DB, awtomatiko itong gagawa ng default secret
if (!$res) {
    echo "<div style='color:orange;'>Initializing default secret for $selected_dept...</div>";
    $default_secret = ($selected_dept === 'HR3') ? hash('sha256', 'hr3_secret_key_2026') : hash('sha256', 'hr_secret_key_2026');
    $ins = $conn->prepare("INSERT INTO department_secrets (department, secret_key) VALUES (?, ?) ON DUPLICATE KEY UPDATE secret_key=VALUES(secret_key)");
    $ins->bind_param("ss", $selected_dept, $default_secret);
    $ins->execute();

    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
}

$secret = $res['secret_key'];

// 2. PAG-GENERATE NG TOKEN PAYLOAD (Dito nakalagay ang User Info)
$payload = [
    "email" => $user_email,
    "role" => $user_role,
    "dept" => $selected_dept,
    "exp" => time() + 3600 // Expire pagkalipas ng 1 oras (para sa testing)
];

// Gawing JSON string ang payload
$payloadJson = json_encode($payload, JSON_UNESCAPED_SLASHES);

// GUMAWA NG DIGITAL SIGNATURE (Ito ang nagpapatunay na valid at hindi tampered ang token)
$signature = hash_hmac("sha256", $payloadJson, $secret);

// I-combine ang Payload at Signature sa isang Base64 Token
$tokenData = [
    "payload" => $payload,
    "signature" => $signature
];
$token = base64_encode(json_encode($tokenData)); // ITO ANG "GENERATED TOKEN"

// 3. I-DEFINE ANG TARGET URL KUNG SAAN IPAPADALA ANG TOKEN
$sso_urls = [
    'HR1' => 'https://hr1.atierahotelandrestaurant.com/sso-login.php',
    'HR2' => 'https://hr2.atierahotelandrestaurant.com/sso-login.php',
    'HR3' => 'https://hr3.atierahotelandrestaurant.com/hr3/sso-login.php',
    'HR4' => 'https://hr4.atierahotelandrestaurant.com/sso-login.php',
    'CORE1' => 'https://core1.atierahotelandrestaurant.com/sso-login.php',
    'CORE2' => 'https://core2.atierahotelandrestaurant.com/sso-login.php'
];
$target_url = $sso_urls[$selected_dept] ?? "https://" . strtolower($selected_dept) . ".atierahotelandrestaurant.com/sso-login.php";
$test_link = "$target_url?token=" . urlencode($token);
?>

<!DOCTYPE html>
<html>

<head>
    <title>Auto Token Generator</title>
    <style>
        body {
            font-family: sans-serif;
            padding: 40px;
            background: #f0f2f5;
        }

        .card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            max-width: 800px;
            margin: auto;
        }

        h2 {
            color: #1b2f73;
            border-bottom: 2px solid #d4af37;
            padding-bottom: 10px;
        }

        select,
        button {
            padding: 10px;
            border-radius: 8px;
            border: 1px solid #ddd;
            margin: 5px 0;
        }

        button {
            background: #1b2f73;
            color: white;
            cursor: pointer;
            border: none;
            font-weight: bold;
        }

        .result-box {
            background: #f8f9fa;
            border: 1px solid #e1e4e8;
            padding: 15px;
            border-radius: 8px;
            font-family: monospace;
            word-break: break-all;
            margin-top: 20px;
        }

        .label {
            font-weight: bold;
            color: #555;
            margin-top: 10px;
            display: block;
        }

        .link {
            color: #2563eb;
            text-decoration: none;
            font-weight: bold;
            font-size: 1.1em;
        }
    </style>
</head>

<body>
    <div class="card">
        <h2>SSO Token Auto-Generator</h2>
        <form method="POST">
            <label>Select Department:</label><br>
            <select name="dept">
                <?php foreach ($departments as $d): ?>
                    <option value="<?= $d ?>" <?= $selected_dept === $d ? 'selected' : '' ?>>
                        <?= $d ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit">Generate Token</button>
        </form>

        <div class="result-box">
            <span class="label">Generated Token (Base64):</span>
            <div
                style="font-size: 0.8em; color: #444; background: #eee; padding: 10px; border-radius: 4px; margin: 5px 0;">
                <?= $token ?>
            </div>

            <span class="label">Payload (Decoded):</span>
            <pre style="background: #eee; padding: 10px; border-radius: 4px;"><?php print_r($payload); ?></pre>

            <span class="label">Direct Login Link:</span>
            <p>
                <a href="<?= $test_link ?>" target="_blank" class="link">Login to
                    <?= $selected_dept ?> Module &rarr;
                </a>
            </p>
        </div>

        <p style="font-size: 0.8em; color: #777; margin-top: 30px;">
            Note: This link uses the secret stored in your database for <strong>
                <?= $selected_dept ?>
            </strong>.
        </p>
    </div>
</body>

</html>