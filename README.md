# Complete Hostel Management System (CHMS)

A modern, web-based hostel administration platform built with **plain PHP 8**, **MySQL/MariaDB**, **Tailwind CSS**, and **Alpine.js** — no frameworks.

## Features implemented

| Area | Module |
|------|--------|
| **Auth** | Login, student self-registration, forgot-password, logout, account lockout, CSRF protection, RBAC, audit logging |
| **Two-factor auth** | Optional email one-time codes at sign-in, switched on per role from Settings, with admin-set recipient mailboxes (Gmail SMTP supported) |
| **Dashboards** | Distinct dashboards per role — admin/hostel (stats + charts), **finance** (revenue trend, methods, top debtors), **maintenance** (work queue by priority, by category), **security** (visitor queue + log), and a student portal |
| **Pagination** | Server-side pagination on students, payments, allocations, applications, complaints, visitors & audit logs, preserving search/filter in the URL |
| **Image uploads** | Validated student photos & profile avatars (JPG/PNG/WebP, ≤2 MB, real-image check), shown across lists, profiles & the topbar |
| **Students** | Full CRUD, search & filter, profile view, **bulk CSV import** |
| **Notifications** | In-app notifications with bell + unread badge, fired on applications, allocations, payments, complaints & notices |
| **Hostels** | CRUD with facilities, live occupancy cards |
| **Blocks & Floors** | Nested structure management under each hostel |
| **Rooms** | CRUD, auto bed creation, occupancy sync |
| **Applications** | Student applications, approve / reject / waiting-list, **hall-dues Reference ID** submitted with the application and checked off by the hostel admin, cancel-with-note |
| **Allocations** | Manual room allocation (transaction-safe), bed assignment, auto-invoice, check-in / check-out / cancel |
| **Room Transfers** | Move a student between rooms — releases old bed, assigns new, marks history |
| **Finance** | Invoices, payments (cash/Paystack/MoMo/bank), **live Paystack gateway** (cURL) with simulation fallback, student self-pay, printable + **PDF receipts** (FPDF) |
| **Hall Dues** | Per-hostel bank/MoMo payment account, dues notice for fresh vs continuing students with admin notes, published to students on their Payments page and the application form |
| **Dues Debtors** | Upload the hall's arrears list (.xlsx/.csv/.txt); students matched by student ID *or* phone are blocked from applying until an admin marks the debt settled |
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

- Passwords hashed with bcrypt (`password_hash`), minimum length `MIN_PASSWORD_LENGTH`.
- All queries use PDO prepared statements.
- CSRF tokens on every state-changing form.
- Output escaped via the `e()` helper (XSS protection).
- Role-based middleware on every controller action.
- Failed-login lockout — per account **and** per source IP — plus a full audit trail.
- Optional email two-factor authentication, per role (see below).
- Uploads: extension allow-list, real-image check, random filenames, and PHP
  execution denied under `/uploads`.
- Session cookie is `HttpOnly`, `SameSite=Lax`, and `Secure` whenever the
  request arrives over TLS; `session.use_strict_mode` blocks session fixation.
- `Referer` is never followed as a redirect target — see `App\Core\Url::safeReferer()`.

### The web-root boundary (important when deploying)

Only `index.php` and everything under `public/` are meant to be reachable over
HTTP. Everything else — `app/`, `config/`, `database/`, `storage/`, `vendor/`
and **`.git/`** — sits beside them on disk because the app is installed at the
domain root on shared hosting, and is blocked by the root
[`.htaccess`](.htaccess).

`.git/` is the one that matters most. It is not just metadata: anyone can read
`.git/index` and `.git/objects/…` and rebuild the entire source tree from them,
`config/database.php` and its credentials included. Verify after every deploy:

```bash
curl -o /dev/null -w '%{http_code}\n' https://yourdomain/.git/config          # expect 403
curl -o /dev/null -w '%{http_code}\n' https://yourdomain/storage/logs/mail.log # expect 403
curl -o /dev/null -w '%{http_code}\n' https://yourdomain/database/schema.sql   # expect 403
```

Anything other than `403`/`404` means the host is ignoring `.htaccess`
(`AllowOverride None`) — ask them to enable it, or point the domain's document
root at `public/` instead. Better still, do not deploy by `git pull`: export a
clean copy (`git archive`) so `.git/` never reaches the server at all.

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

### Sending through Gmail or a school (Google Workspace) account

Google runs the same SMTP servers for personal Gmail and for Workspace domains,
so both are configured identically under **Settings → Email (SMTP)**:

| Field | Value |
|---|---|
| SMTP Host | `smtp.gmail.com` (or `smtp-relay.gmail.com` for a Workspace relay) |
| SMTP Port | `587` |
| SMTP Username | the **full address** — `hostel@gmail.com` *or* `noreply@vvu.edu.gh` |
| SMTP Password | the 16-character **App Password**, never the account password |
| From Address | the same account, or another address on the same domain |

Sending from the school domain is worth doing: mail from `@vvu.edu.gh` to
`@st.vvu.edu.gh` stays inside the same Workspace tenant, so codes are far less
likely to be delayed or filtered than mail from an outside address.

Two behaviours make the school case work:

- A username typed without a domain takes the domain from the From Address, so
  `noreply` + `noreply@vvu.edu.gh` signs in as `noreply@vvu.edu.gh`. Only when
  there is no other clue is `@gmail.com` assumed.
- A From Address on the **same domain** as the sign-in is sent as typed — the
  normal Workspace "Send mail as" alias. A **different** domain is replaced by
  the sign-in address and kept as the `Reply-To`, because Google silently
  rewrites a From it cannot verify.

> **If App Passwords are not offered** on a school account, a Workspace
> administrator has switched them off for the organisation. Ask IT either to
> permit App Passwords for that one sending account, or to enable the
> `smtp-relay.gmail.com` relay for the server — both work here.

Recipients are unrestricted: any valid address receives codes, including
`ebenezer-owusu@st.vvu.edu.gh` and other subdomain school addresses. Set them
per role under **Two-Factor Authentication**, or leave a role blank to use each
user's own account email.

### Two-factor authentication (email codes)

Selected roles must enter a 6-digit code emailed to them after their password is
accepted. Nothing is signed in until the code is verified — codes are stored
hashed, expire after 10 minutes, are single-use, and a wrong code counts towards
the same lockout as a wrong password.

Set it up as **admin → Settings**:

1. **Email (SMTP)** — see [Sending through Gmail or a school
   account](#sending-through-gmail-or-a-school-google-workspace-account) below.
2. Use **Save & send test** to confirm mail actually leaves the server.
3. **Two-Factor Authentication (Email)** — tick the roles that must be
   challenged. Leave a role's *Send codes to* box blank to use each user's own
   account email, or enter one or more comma-separated addresses to route that
   role's codes to fixed mailboxes (useful for shared administrator accounts).
4. Tick **Require an emailed code at sign-in** and save.

The master switch refuses to turn on while SMTP is unconfigured or no role is
selected, so an administrator cannot lock themselves out. Timings live in
[`config/config.php`](config/config.php) (`TWOFA_*`); the logic is in
[`app/services/TwoFactor.php`](app/services/TwoFactor.php).

On an existing database, run the migration once. It carries no `USE` statement,
so it applies to whichever database you point it at — on shared hosting the
database is named by the control panel and the MySQL user has rights to that one
only:

```bash
# local (XAMPP)
mysql -u root -p chms_hostel < database/migration_two_factor.sql

# live server — use your own database and user
mysql -u YOUR_USER -p YOUR_DATABASE < database/migration_two_factor.sql
```

In **phpMyAdmin**: click your database in the left sidebar *first*, then
**Import → choose the file → Go**. Importing without a database selected (or
against a hardcoded name) gives
`#1044 - Access denied for user '...' to database '...'`.

The migration ends with a check that prints `1` and `3` when it worked:

```sql
SELECT
    (SELECT COUNT(*) FROM information_schema.TABLES
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'two_factor_codes') AS two_factor_codes_table,
    (SELECT COUNT(*) FROM settings WHERE `key` LIKE 'twofa%')              AS twofa_settings;
```

If mail ever breaks while 2FA is on, it can be switched off straight from the
database so nobody stays locked out:

```sql
UPDATE settings SET value = '0' WHERE `key` = 'twofa_enabled';
```

### Hall dues & the application Reference ID

Each hostel collects its own hall dues off-platform (bank transfer or mobile
money) and uses the reference the transfer returns as proof of payment.

**Admins and hostel admins** set this up under **Payments → Invoices → Hall Dues
Setup** (`/fees`). A super admin picks the hostel first; a hostel admin only ever
sees their own. Three things live there:

| Section | What it does |
|---------|--------------|
| **Room Pricing** | Existing per-room-type accommodation prices |
| **Hall Dues Notice** | The amount **fresh** and **continuing** students each owe, with a free-text note explaining what it covers (line breaks are kept, so you can list the breakdown) |
| **Dues Payment Account** | The bank and/or mobile-money account students pay into, step-by-step payment instructions, and a switch for whether a Reference ID is mandatory on applications |

A live preview at the bottom of the page shows exactly what students will see.

**Students** see that panel on their **Payments** page and again on the
**room-application form**, with the card for their own category highlighted (read
from their academic level, and overridable on the form). They pay, then type the
**Reference ID** from their receipt or confirmation SMS into the application.
When the hostel has published an account and left the switch on, the application
will not submit without one.

**Hostel admins** review the references on the **Applications** list. Each row
shows the reference, the expected amount, a verification badge, and a red warning
when the same reference appears on another application. Two buttons record the
outcome of checking it against the account — *payment found* and *no payment
traced* — and both notify the student. Approve / waiting-list / reject work as
before, and a **Cancel** button opens a dialog that **requires a note**; that note
is delivered to the student with the cancellation and is shown against the
application in their own list. The applications CSV export carries the reference,
expected amount, check state and review note so they can be reconciled against a
bank statement in a spreadsheet.

Hostel isolation applies throughout: a hostel admin can only read or write their
own hostel's dues settings and can only review applications directed at it.

On an existing database, run the migration once. Like the two-factor migration it
carries no `USE` statement, so it applies to whichever database you point it at:

```bash
# local (XAMPP)
mysql -u root -p chms_hostel < database/migration_hostel_dues.sql

# live server — use your own database and user
mysql -u YOUR_USER -p YOUR_DATABASE < database/migration_hostel_dues.sql
```

In **phpMyAdmin**: click your database in the left sidebar *first*, then
**Import → choose the file → Go**.

The migration is safe to re-run and ends with a check that prints `13` and `7`
when it worked:

```sql
SELECT
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'hostels'
        AND COLUMN_NAME LIKE 'dues%')  AS hostel_dues_columns,
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'applications'
        AND COLUMN_NAME IN ('student_type','payment_reference','payment_amount',
                            'payment_status','payment_verified_by',
                            'payment_verified_at','review_note')) AS application_dues_columns;
```

Nothing is enforced until a hostel actually publishes an account, so existing
hostels keep working exactly as before until their admin fills the page in.

### Hall dues debtors (arrears from previous semesters)

Students who never paid a past semester's dues are stopped from applying for a
room until the debt is settled.

**Admins and hall admins** manage the list under **Dues Debtors** (`/debtors`).
Click **Upload List** and choose the hall's debtors file — **.xlsx, .csv, .txt or
.tsv**, up to 5 MB. Each upload is stored as one *batch*, so importing the wrong
file is undone by deleting that batch (its rows go with it).

Columns may be in **any order**: every cell is classified by what it looks like,
so a list typed differently next semester still imports.

| Read as | Recognised by |
|---|---|
| Student ID | mixed letters and digits, e.g. `226TR02000104` |
| Phone | 9–12 bare digits |
| Name | the longest mostly-alphabetic cell |
| Room | a short token such as `GF12`, `SF3` |
| Amount | a decimal such as `150.00` |

A heading like `"2ND SEMESTER, 2025/2026"` is picked up and applied to every row
beneath it, so one file can carry several semesters and a student listed in two
of them shows two debts. The ordinal is read in any of the usual phrasings —
`1ST SEMESTER`, `FIRST SEMESTER`, `SEMESTER 1`, `SEM 2`, `SEMESTER II`. If a
heading gives a year but no ordinal, the rows below it import with a blank
semester and the importer tells you how many; they still block the student, who
simply sees "a previous semester" instead of which one, and the semester can be
set on any row by editing it.

Title banners and column headers are ignored. Each row needs **at least a
student ID or a phone number**; anything unreadable is reported back after the
import rather than silently dropped.

**Matching.** A student is matched by **student ID or phone number**, so one
wrong field in the source list does not let anybody slip through:

- Student IDs compare case-insensitively with punctuation stripped.
- Phones compare on their **last 9 digits**. Ghanaian mobiles are 10 digits with
  a leading zero, but a spreadsheet that stored the column as a number drops it —
  `0548811774`, `548811774` and `+233548811774` all match each other.

Matching is scoped to the student's own hall. The check runs the moment a student
tries to apply, so a debtor who has not registered yet is blocked automatically
whenever they do sign up.

**Adding and editing by hand.** Not everything arrives as a file. **Add Debtor**
opens a form for a single entry — name, student ID, phone, room, amount,
academic year and semester — and the pencil icon on any row opens the same form
to correct one. Hand-typed rows are collected in their own batch ("Added by
hand") so they are just as traceable as an upload, and are never swept away when
an uploaded list is deleted.

At least one of **Student ID** or **Phone** is required, since that is what a
student is matched on; giving both is safest because either will catch them.
After saving, the confirmation says whether the row actually matches a
registered student — a row matching nobody blocks nothing until that person
signs up, and it is easy to mistype an ID and never notice. Opening an existing
row for editing shows the same thing, naming and linking whoever it currently
blocks. Corrections take effect at once: fixing a wrong phone number makes the
row start matching immediately.

The trash icon removes a single row, for a hand-entry made in error — deleting
its whole batch would otherwise take everything else with it.

**What the student sees.** A red panel on their Applications and Payments pages
listing each unpaid semester, the room and the amount, with the total; the *New
Application* button is disabled. Trying to reach the form anyway is refused with
a message naming the semesters and the total owed.

**Clearing a debt.** When the student pays, the admin clicks **Mark settled** on
that row and they can apply straight away. *Reopen* undoes a clearing made in
error. The list also shows which debtors already have an account on the system,
so you can see at a glance who the block will actually catch.

Staff are never blocked — a hall admin may be recording an application for
someone who has just paid at the desk — but the confirmation tells them the
student has arrears, so an unpaid debt is not approved by accident.

#### .xlsx without the `zip` extension

Reading .xlsx normally needs PHP's `zip` extension, which is off by default on
much shared hosting. The reader uses it when present and otherwise unpacks the
workbook itself using `zlib` (effectively always available), so uploads work
without changing `php.ini`. Old binary `.xls` files are *not* supported — open
the file in Excel and save it as **.xlsx** or **CSV**.

#### Migration

On an existing database, run the migration once. Like the others it carries no
`USE` statement, so it applies to whichever database you point it at:

```bash
# local (XAMPP)
mysql -u root -p chms_hostel < database/migration_dues_debtors.sql

# live server — use your own database and user
mysql -u YOUR_USER -p YOUR_DATABASE < database/migration_dues_debtors.sql
```

It creates `dues_debtor_batches` and `dues_debtors`, then aligns their collation
with the existing `students` table — matching joins the two, and a server whose
default collation differs from the one the original schema was built with would
otherwise fail with `#1267 Illegal mix of collations`. Safe to re-run; it ends by
printing both table counts and the two collations, which should agree.

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
