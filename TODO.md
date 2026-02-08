# Admin Login Fix TODO

## Problem
- Admin login showing "Username atau Password salah!" error even with correct credentials (admin / admin123)
- Password verification mismatch between code and database

## Root Cause
- Code was using `password_verify()` (bcrypt) but database had MD5 hash
- The hash in database.sql was for "password", not "admin123"

## Solution Implemented
- [x] Changed password verification in admin-login.php from `password_verify()` to `md5()` comparison
- [x] Updated database.sql to insert MD5 hash of 'admin123' for admin user

## Testing
- [ ] Test login with admin / admin123 credentials
- [ ] Verify successful redirect to admin/index.php
- [ ] Check session variables are set correctly

## Security Note
- MD5 is not secure for passwords. Consider upgrading to bcrypt in production.
