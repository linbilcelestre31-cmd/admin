# Super Admin Portal 🔐

This directory contains the Super Admin management portal for the ATIERA Administrative System.

## Access Links

To access the Super Admin area, use the following links depending on your environment:

### Live Production (Unified Access)
*   **Login Page**: [https://admin.atierahotelandrestaurant.com/admin/Super-admin/auth/login.php](https://admin.atierahotelandrestaurant.com/admin/Super-admin/auth/login.php)
*   **Main Dashboard**: [https://admin.atierahotelandrestaurant.com/admin/Super-admin/Dashboard.php](https://admin.atierahotelandrestaurant.com/admin/Super-admin/Dashboard.php)

## 🏗️ Isolated Architecture
Following the latest directive, the Super Admin system uses a high-security isolated structure:
1.  **Isolated Table**: `SuperAdminLogin_tb` (Dedicated high-security table within the main database).
3.  **Cross-Module Bypass**: Enables instant access to HR1, HR2, HR3, HR4, Legal, for more departments without re-authenticating.

## 🚪 Step-by-Step Login Guide
1.  **Open the Login Page**: Go to the [Super Admin Login](https://admin.atierahotelandrestaurant.com/admin/Super-admin/auth/login.php).
2.  **Enter Credentials**: Type `admin` as username and `password` as password.
3.  **Check for OTP**:
    *   Check your Gmail (**atiera41001@gmail.com**) for the 6-digit code.
    *   **Bypass**: Look at the **Golden Box** on the activation screen for the **"System Bypass"** token.
4.  **Verify**: Copy the code, paste it into the field, and click **Verify Identity**.

### Default Credentials
*   **Username**: `admin`
*   **Password**: `password`
*   **Default Email**: `atiera41001@gmail.com` (for 2FA)

## 🔑 Super Admin Bypass Protocol (For other Groups)
To allow the Super Admin to access your group's system:
1.  Add `SuperAdminLogin_tb` to your existing database.
2.  Integrate the `handleSuperAdminBypass()` function from `integ/super_admin_bypass.php`.
3.  The Super Admin dashboard will push the session `api_key` to your system to authorize access.
