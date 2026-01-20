# SSO Troubleshooting Guide

If you see the error **"Invalid or tampered token"**, it means the security handshake between Admin and HR3 failed.

### Why does this happen?
1. **Secret Mismatch**: The password (secret_key) used to sign the token in Admin is different from the one in HR3.
2. **Department Mismatch**: The HR3 system might be looking for a secret labeled 'HR1' instead of 'HR3'.
3. **Database Sync**: The Admin and HR3 systems have separate databases. They both need to have the same `secret_key`.

### How to Fix:
1. **Run Setup**: Open `https://admin.atierahotelandrestaurant.com/admin/Debug/setup_db.php` in your browser.
2. **Check HR3 DB**: Ensure the `department_secrets` table in your HR3 database has this entry:
   - department: `HR3`
   - secret_key: `hr3_secret_key_2026`
3. **Update HR3 Code**: Ensure your HR3 `sso-login.php` is looking for `HR3` department:
   ```php
   WHERE department='HR3' AND is_active=1
   ```

### Files in this Folder:
- `setup_db.php`: Fixes the Admin database secrets.
- `Readme.md`: This guide.
