# Brew Co. QR Digital Menu: Customer Menu + Admin Panel

A QR-based digital menu for a coffee shop. Customers scan a QR code to see the menu.
Administrators manage categories, items, and users from a secure dashboard.
Every change in the admin appears on the customer menu immediately.

Built as an intern task for Violet Programming Company.

## Tech stack
- Native PHP 8 (PDO), MySQL, HTML5, CSS3, Bootstrap Icons
- Local server: WAMP

## Features
- Customer menu loaded from the database (active categories and items only)
- Secure admin login (hashed passwords, sessions, login attempt limit)
- Dashboard with KPI cards
- Categories: add, edit, delete, activate/deactivate
- Items: add, edit, delete, activate/deactivate, category, image upload, search and filters
- Users: add, edit, reset password, delete
- Responsive coffee/burgundy admin interface

## Setup
1. Copy the project folder to `C:\wamp64\www\qr_menu` and start Apache and MySQL in WAMP.
2. Open phpMyAdmin and import `database/brew_menu.sql`.
3. Copy `includes/config.sample.php` to `includes/config.php` and check the database settings.
4. Customer menu: `http://localhost/qr_menu/`
5. Admin panel: `http://localhost/qr_menu/admin/login.php`

## Demo login
- Username: `admin`
- Password: `tastesting123`