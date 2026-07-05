# Complete Hostel Management System (CHMS)

A modern, web-based hostel administration platform built with **plain PHP 8**, **MySQL/MariaDB**, **Tailwind CSS**, and **Alpine.js** — no frameworks.

## Features implemented

| Area | Module |
|------|--------|
| **Auth** | Login, student self-registration, forgot-password, logout, account lockout, CSRF protection, RBAC, audit logging |
| **Dashboards** | Distinct dashboards per role — admin/hostel (stats + charts), **finance** (revenue trend, methods, top debtors), **maintenance** (work queue by priority, by category), **security** (visitor queue + log), and a student portal |
| **Pagination** | Server-side pagination on students, payments, allocations, applications, complaints, visitors & audit logs, preserving search/filter in the URL |
| **Image uploads** | Validated student photos & profile avatars (JPG/PNG/WebP, ≤2 MB, real-image check), shown across lists, profiles & the topbar |
| **Students** | Full CRUD, search & filter, profile view, **bulk CSV import** |
| **Notifications** | In-app notifications with bell + unread badge, fired on applications, allocations, payments, complaints & notices |
| **Hostels** | CRUD with facilities, live occupancy cards |
| **Blocks & Floors** | Nested structure management under each hostel |
| **Rooms** | CRUD, auto bed creation, occupancy sync |
| **Applications** | Student applications, approve / reject / waiting-list |
| **Allocations** | Manual room allocation (transaction-safe), bed assignment, auto-invoice, check-in / check-out / cancel |
| **Room Transfers** | Move a student between rooms — releases old bed, assigns new, marks history |
| **Finance** | Invoices, payments (cash/Paystack/MoMo/bank), **live Paystack gateway** (cURL) with simulation fallback, student self-pay, printable + **PDF receipts** (FPDF) |
| **Email / SMS** | PHPMailer SMTP email + Arkesel/Hubtel SMS on key events; both log to file when unconfigured |
| **Complaints** | Submission + maintenance status workflow |
| **Visitors** | Registration, security approval, pass codes, check-in/out, blacklist |
| **Inventory** | Asset CRUD by hostel, condition tracking, low-stock alerts |
| **Reports** | Occupancy, financial summary, payment-method chart, complaint/application/gender stats, printable + **PDF export** |
| **Statements** | Per-student account statement PDF (invoices, payments, balance) |
| **Audit Logs** | Full action trail with module filter (admin) |
| **Settings** | Institution, currency, Paystack keys, SMTP/SMS, **enforced** maintenance mode (admin) |
| **Exports** | One-click CSV export of students, payments, invoices & audit logs |
| **Notices** | Notice board with audience targeting & pinning |
| **Profile** | Update details & change password |

Roles: `admin`, `hostel_admin`, `finance`, `maintenance`, `security`, `student`.

## Tech stack

- PHP 8.x (PDO, prepared statements, MVC-inspired structure)
- MySQL 8+ / MariaDB 10.4+
- Tailwind CSS (CDN), Alpine.js, Chart.js, Font Awesome
- Apache / XAMPP
- **Vendored libraries** (in `vendor/`, no Composer required): **FPDF** for PDF
  receipts and **PHPMailer** for SMTP email — both committed directly so the app
  runs on a machine without internet/Composer.

## Project structure

```
hostel/
├── app/
│   ├── controllers/     # Request handlers
│   ├── core/            # Router, Database, Auth, Controller, Model, Session, Csrf, View, Audit
│   ├── models/          # Active-record-lite models
│   ├── views/           # PHP templates (layouts + per-module)
│   └── helpers/         # Global helper functions
├── config/              # config.php, database.php, routes.php
├── database/            # schema.sql, seed.php
├── public/              # Front controller (index.php), .htaccess, assets, uploads
└── storage/logs/        # Error & app logs
```

## Setup (XAMPP on Windows)

1. **Start Apache + MySQL** from the XAMPP Control Panel.

2. **Create the database & schema** (from the project root):
   ```bash
   C:\xampp\mysql\bin\mysql.exe -u root < database/schema.sql
   ```

3. **Seed demo data** (creates demo accounts, a sample hostel, rooms & a student):
   ```bash
   C:\xampp\php\php.exe database/seed.php
   ```
   > The full schema (including the `inventory` and `notifications` tables) is in
   > `database/schema.sql`. If you set up the DB before those modules were added,
   > also run `database/migration_inventory.sql` and
   > `database/migration_notifications.sql`.

4. **Open the app**: <http://localhost/hostel/public/>

> The DB connection defaults to host `127.0.0.1`, user `root`, empty password
> (standard XAMPP). Change these in [`config/database.php`](config/database.php).
> The app uses its own database, **`chms_hostel`**, so it never touches any other
> project sharing this MySQL instance.

## Demo accounts

All demo accounts use the password **`password123`**.

| Username | Role |
|----------|------|
| `admin` | System Administrator |
| `hosteladmin` | Hostel Administrator |
| `finance` | Finance Officer |
| `maintenance` | Maintenance Officer |
| `security` | Security Officer |
| `student` | Student |

The seed also creates a **rich demo dataset** so dashboards, charts and reports are
populated out of the box: 3 hostels with rooms/beds, ~30 extra students (each can
log in with their **Student ID** as username, password `password123`), room
allocations, invoices, payments spread across the last 6 months, applications,
complaints, notices, visitors and inventory. Re-running `seed.php` is safe — the
demo block is guarded by a `demo_seeded` flag and won't duplicate.

## Security notes

- Passwords hashed with bcrypt (`password_hash`).
- All queries use PDO prepared statements.
- CSRF tokens on every state-changing form.
- Output escaped via the `e()` helper (XSS protection).
- Role-based middleware on every controller action.
- Failed-login lockout and full audit trail.

## Configuration

Key settings live in [`config/config.php`](config/config.php): app name, base URL,
currency, session timeout, and lockout policy.

### Enabling live Paystack payments

The payment flow auto-detects whether Paystack is configured:

- **No key set** → the student "Pay Now" button records an instant *simulated*
  payment (great for local testing).
- **Key set** → the student is redirected to the real Paystack checkout, and the
  payment is verified server-side on callback before the invoice is cleared.

To go live, sign in as **admin → Settings** and paste your Paystack **Secret Key**
(and Public Key). No code changes or Composer packages are required — the
integration in [`app/services/PaystackService.php`](app/services/PaystackService.php)
talks to the Paystack REST API directly over cURL. Set your test secret key
(`sk_test_…`) to try the full redirect/verify flow end-to-end.

### Email & SMS notifications

Student-facing events (payment received, application decision, room allocation,
complaint update) are delivered across three channels: **in-app**, **email**, and
**SMS**. Email/SMS degrade gracefully:

- **Email** — set the SMTP host/port/user/password under **Settings**. With no
  host configured, messages are written to `storage/logs/mail.log` instead of
  sent. Sending uses the vendored [PHPMailer](app/services/Mailer.php).
- **SMS** — choose a provider (Arkesel or Hubtel), sender ID and API key under
  **Settings**. With no key, messages are written to `storage/logs/sms.log`.
  See [`app/services/Sms.php`](app/services/Sms.php).

### PDF receipts

Every receipt has a **PDF** button (and the finance/admin payments list links to
it) generated by [`app/services/ReceiptPdf.php`](app/services/ReceiptPdf.php) using
FPDF — no external service needed.

## Extending

The architecture is modular: add a model in `app/models`, a controller in
`app/controllers`, views in `app/views/<module>`, and register routes in
`config/routes.php`. The remaining SRS modules (visitors, inventory, reports
export, backups, CMS, SMS/email) slot in the same way — the `visitors`,
`audit_logs`, and `settings` tables are already provisioned in the schema.
