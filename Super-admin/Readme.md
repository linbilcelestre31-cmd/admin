# Super Admin Portal 🔐

This directory contains the Super Admin management portal for the ATIERA Administrative System.

## Access Links

To access the Super Admin area, use the following links depending on your environment:

### Live Production
*   **Login Page**: [https://admin.atierahotelandrestaurant.com/admin/Super-admin/auth/login.php](https://admin.atierahotelandrestaurant.com/admin/Super-admin/auth/login.php)
*   **Dashboard**: [https://admin.atierahotelandrestaurant.com/admin/Super-admin/Dashboard.php](https://admin.atierahotelandrestaurant.com/admin/Super-admin/Dashboard.php)

### Local Development (XAMPP)
*   **Login Page**: `http://localhost/admin/Super-admin/auth/login.php`
*   **Dashboard**: `http://localhost/admin/Super-admin/Dashboard.php`

## Features
*   **2-Factor Authentication**: Integrated with PHPMailer for secure OTP verification.
*   **User Management**: Control all administrator and staff accounts.
*   **System Monitoring**: Real-time server and database status.
*   **Audit Logs**: Complete history of system actions.

## Security
Access to the dashboard is restricted to sessions with the `super_admin` role. Unauthorized attempts are automatically redirected to the login page.

### Default Credentials
*   **Username**: `admin`
*   **Password**: `password`
*   **Default Email**: `atiera41001@gmail.com` (for 2FA)

## 🚪 Step-by-Step Login Guide
1.  **Open the Login Page**: Go to the [Super Admin Login](https://admin.atierahotelandrestaurant.com/admin/Super-admin/auth/login.php).
2.  **Enter Credentials**: Type `admin` as username and `password` as password.
3.  **Check for OTP**:
    *   Check your Gmail (**atiera41001@gmail.com**) for the 6-digit code.
    *   **If the email doesn't arrive**: Look at the **Red Alert Box** on the login page. I have enabled a **"Development OTP"** display there so you can see the code immediately without checking your email.
4.  **Verify**: Copy the 6-digit code, paste it into the field, and click **Verify & Login**.

## 📧 Bakit walang email? (Troubleshooting)
1.  **Spam Folder**: Minsan napupunta ang email sa Spam/Junk folder.
2.  **SMTP Block**: Minsan hinaharang ng server ang automatic emails.
3.  **App Password**: Siguraduhing active ang "App Password" sa Google Account settings kung gagamit ng sariling account sa `Config.php`.
4.  **Bypass**: Gamitin muna ang **Development OTP** na lumalabas sa login screen para maka-access agad.

