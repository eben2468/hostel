-- ===========================================================================
-- Complete Hostel Management System — Database Schema
-- MySQL / MariaDB 8+/10.4+
-- ===========================================================================

CREATE DATABASE IF NOT EXISTS chms_hostel
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE chms_hostel;

SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------------
-- Users & Authentication
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(150) NOT NULL,
    username      VARCHAR(80)  NOT NULL UNIQUE,
    email         VARCHAR(150) NOT NULL UNIQUE,
    phone         VARCHAR(30)  NULL,
    password      VARCHAR(255) NOT NULL,
    role          ENUM('admin','hostel_admin','finance','maintenance','security','student') NOT NULL DEFAULT 'student',
    hostel_id     INT          NULL,           -- NULL = global super admin; set = bound to one hostel
    avatar        VARCHAR(255) NULL,
    is_active     TINYINT(1)   NOT NULL DEFAULT 1,
    last_login_at DATETIME     NULL,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_users_hostel FOREIGN KEY (hostel_id) REFERENCES hostels(id) ON DELETE SET NULL,
    INDEX idx_users_role (role),
    INDEX idx_users_hostel (hostel_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS password_resets (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    email      VARCHAR(150) NOT NULL,
    token      VARCHAR(255) NOT NULL,
    expires_at DATETIME     NOT NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_pr_email (email)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS login_attempts (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    login      VARCHAR(150) NOT NULL,
    ip_address VARCHAR(64)  NULL,
    success    TINYINT(1)   NOT NULL DEFAULT 0,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_la_login (login)
) ENGINE=InnoDB;

-- Admin-assisted password reset requests: a student who cannot self-verify
-- submits a request here for a super/hostel admin to action.
CREATE TABLE IF NOT EXISTS password_reset_requests (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    student_id   INT          NULL,           -- students.id when the request matched a record
    hostel_id    INT          NULL,           -- for admin scoping
    student_code VARCHAR(40)  NOT NULL,       -- institutional student ID as typed
    full_name    VARCHAR(150) NOT NULL,
    contact      VARCHAR(150) NULL,           -- email/phone the student left to be reached on
    message      VARCHAR(255) NULL,
    status       ENUM('pending','resolved','rejected') NOT NULL DEFAULT 'pending',
    handled_by   INT          NULL,           -- users.id of the admin who acted
    handled_at   DATETIME     NULL,
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_prr_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE SET NULL,
    CONSTRAINT fk_prr_hostel  FOREIGN KEY (hostel_id)  REFERENCES hostels(id)  ON DELETE SET NULL,
    INDEX idx_prr_status (status),
    INDEX idx_prr_hostel (hostel_id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------------
-- Academic structure
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS academic_years (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(40) NOT NULL,           -- e.g. 2025/2026
    is_current TINYINT(1)  NOT NULL DEFAULT 0,
    created_at DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------------
-- Students
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS students (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    user_id       INT          NULL,
    hostel_id     INT          NULL,           -- hostel the student is registered/assigned to
    student_id    VARCHAR(40)  NOT NULL UNIQUE, -- institutional ID
    index_number  VARCHAR(40)  NULL,
    full_name     VARCHAR(150) NOT NULL,
    gender        ENUM('male','female','other') NOT NULL DEFAULT 'male',
    date_of_birth DATE         NULL,
    nationality   VARCHAR(80)  NULL,
    programme     VARCHAR(120) NULL,
    department    VARCHAR(120) NULL,
    level         VARCHAR(20)  NULL,
    phone         VARCHAR(30)  NULL,
    email         VARCHAR(150) NULL,
    address       VARCHAR(255) NULL,
    guardian_name         VARCHAR(150) NULL,
    guardian_phone        VARCHAR(30)  NULL,
    guardian_relationship VARCHAR(60)  NULL,
    blood_group   VARCHAR(10)  NULL,
    allergies     VARCHAR(255) NULL,
    emergency_contact VARCHAR(60) NULL,
    status        ENUM('active','suspended','graduated','inactive') NOT NULL DEFAULT 'active',
    photo         VARCHAR(255) NULL,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_students_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_students_hostel FOREIGN KEY (hostel_id) REFERENCES hostels(id) ON DELETE SET NULL,
    INDEX idx_students_status (status),
    INDEX idx_students_gender (gender),
    INDEX idx_students_hostel (hostel_id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------------
-- Hostel structure: hostels -> blocks -> floors -> rooms -> beds
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS hostels (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(150) NOT NULL,
    code        VARCHAR(40)  NOT NULL UNIQUE,
    type        ENUM('male','female','mixed','private','university') NOT NULL DEFAULT 'mixed',
    address     VARCHAR(255) NULL,
    digital_address VARCHAR(60) NULL,
    capacity    INT          NOT NULL DEFAULT 0,
    manager     VARCHAR(150) NULL,
    description TEXT         NULL,
    facilities  TEXT         NULL,            -- comma-separated
    image       VARCHAR(255) NULL,
    status      ENUM('active','inactive','archived') NOT NULL DEFAULT 'active',
    academic_year    VARCHAR(40) NULL,         -- current intake year, set by hostel admin
    semester         VARCHAR(40) NULL,         -- current semester, set by hostel admin
    applications_open TINYINT(1) NOT NULL DEFAULT 1, -- students may apply when 1
    -- Per-hostel Paystack account (set by the super admin) so each hostel
    -- collects its own payments. Falls back to the global system settings.
    paystack_public_key VARCHAR(255) NULL,
    paystack_secret_key VARCHAR(255) NULL,
    paystack_enabled TINYINT(1) NOT NULL DEFAULT 0, -- students may pay online when 1
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS blocks (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    hostel_id   INT          NOT NULL,
    name        VARCHAR(120) NOT NULL,
    code        VARCHAR(40)  NULL,
    gender      ENUM('male','female','mixed') NOT NULL DEFAULT 'mixed',
    description VARCHAR(255) NULL,
    status      ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_blocks_hostel FOREIGN KEY (hostel_id) REFERENCES hostels(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS floors (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    block_id    INT          NOT NULL,
    number      VARCHAR(20)  NOT NULL,
    description VARCHAR(255) NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_floors_block FOREIGN KEY (block_id) REFERENCES blocks(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS rooms (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    hostel_id    INT          NOT NULL,
    block_id     INT          NULL,
    floor_id     INT          NULL,
    room_number  VARCHAR(40)  NOT NULL,
    room_type    ENUM('single','double','triple','quad','deluxe','vip') NOT NULL DEFAULT 'double',
    capacity     INT          NOT NULL DEFAULT 1,
    occupied     INT          NOT NULL DEFAULT 0,
    price        DECIMAL(12,2) NOT NULL DEFAULT 0,
    features     TEXT         NULL,
    status       ENUM('available','occupied','reserved','maintenance','closed') NOT NULL DEFAULT 'available',
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_rooms_hostel FOREIGN KEY (hostel_id) REFERENCES hostels(id) ON DELETE CASCADE,
    CONSTRAINT fk_rooms_block  FOREIGN KEY (block_id)  REFERENCES blocks(id) ON DELETE SET NULL,
    CONSTRAINT fk_rooms_floor  FOREIGN KEY (floor_id)  REFERENCES floors(id) ON DELETE SET NULL,
    INDEX idx_rooms_status (status)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS beds (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    room_id    INT          NOT NULL,
    bed_number VARCHAR(20)  NOT NULL,
    status     ENUM('available','occupied','reserved','maintenance') NOT NULL DEFAULT 'available',
    student_id INT          NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_beds_room    FOREIGN KEY (room_id)    REFERENCES rooms(id) ON DELETE CASCADE,
    CONSTRAINT fk_beds_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------------
-- Applications & Allocations
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS applications (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    student_id      INT          NOT NULL,
    academic_year   VARCHAR(40)  NULL,
    semester        VARCHAR(40)  NULL,
    preferred_hostel_id INT      NULL,
    preferred_room_type ENUM('single','double','triple','quad','deluxe','vip') NULL,
    preferred_room_id   INT      NULL,
    medical_conditions  VARCHAR(255) NULL,
    special_needs   VARCHAR(255) NULL,
    remarks         TEXT         NULL,
    priority        INT          NOT NULL DEFAULT 0,
    status          ENUM('pending','approved','rejected','cancelled','waiting','expired') NOT NULL DEFAULT 'pending',
    reviewed_by     INT          NULL,
    reviewed_at     DATETIME     NULL,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_app_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    CONSTRAINT fk_app_hostel  FOREIGN KEY (preferred_hostel_id) REFERENCES hostels(id) ON DELETE SET NULL,
    CONSTRAINT fk_app_pref_room FOREIGN KEY (preferred_room_id) REFERENCES rooms(id) ON DELETE SET NULL,
    INDEX idx_app_status (status)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS allocations (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    student_id    INT          NOT NULL,
    room_id       INT          NOT NULL,
    bed_id        INT          NULL,
    application_id INT         NULL,
    academic_year VARCHAR(40)  NULL,
    semester      VARCHAR(40)  NULL,
    allocated_by  INT          NULL,
    -- 'suspended' parks an allocation that belonged to a term that is no longer
    -- the hostel's active session; switching back to that term restores it.
    status        ENUM('active','checked_in','checked_out','cancelled','transferred','suspended') NOT NULL DEFAULT 'active',
    -- The live status ('active'/'checked_in') an allocation held before it was
    -- parked as 'suspended', so a term switch-back restores it exactly.
    resume_status VARCHAR(20)  NULL,
    check_in_at   DATETIME     NULL,
    check_out_at  DATETIME     NULL,
    remarks       VARCHAR(255) NULL,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_alloc_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    CONSTRAINT fk_alloc_room    FOREIGN KEY (room_id)    REFERENCES rooms(id) ON DELETE CASCADE,
    CONSTRAINT fk_alloc_bed     FOREIGN KEY (bed_id)     REFERENCES beds(id) ON DELETE SET NULL,
    INDEX idx_alloc_status (status)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------------
-- Finance: fees, invoices, payments
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS fees (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(150) NOT NULL,
    amount        DECIMAL(12,2) NOT NULL DEFAULT 0,
    hostel_id     INT          NULL,
    room_type     ENUM('single','double','triple','quad','deluxe','vip') NULL,
    academic_year VARCHAR(40)  NULL,
    semester      VARCHAR(40)  NULL,
    due_date      DATE         NULL,
    status        ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_fees_hostel FOREIGN KEY (hostel_id) REFERENCES hostels(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS invoices (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    invoice_no    VARCHAR(40)  NOT NULL UNIQUE,
    student_id    INT          NOT NULL,
    allocation_id INT          NULL,
    academic_year VARCHAR(40)  NULL,
    semester      VARCHAR(40)  NULL,
    description   VARCHAR(255) NULL,
    amount        DECIMAL(12,2) NOT NULL DEFAULT 0,
    amount_paid   DECIMAL(12,2) NOT NULL DEFAULT 0,
    balance       DECIMAL(12,2) NOT NULL DEFAULT 0,
    due_date      DATE         NULL,
    status        ENUM('unpaid','partial','paid','cancelled') NOT NULL DEFAULT 'unpaid',
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_inv_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    INDEX idx_inv_status (status)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS payments (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    receipt_no    VARCHAR(40)  NOT NULL UNIQUE,
    invoice_id    INT          NULL,
    student_id    INT          NOT NULL,
    academic_year VARCHAR(40)  NULL,
    semester      VARCHAR(40)  NULL,
    amount        DECIMAL(12,2) NOT NULL DEFAULT 0,
    method        ENUM('cash','paystack','momo','bank_transfer','manual') NOT NULL DEFAULT 'cash',
    reference     VARCHAR(120) NULL,
    status        ENUM('pending','completed','failed','refunded') NOT NULL DEFAULT 'completed',
    verified_by   INT          NULL,
    paid_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_pay_invoice FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE SET NULL,
    CONSTRAINT fk_pay_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    INDEX idx_pay_status (status)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------------
-- Complaints & Maintenance
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS complaints (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    student_id  INT          NULL,
    category    ENUM('electrical','plumbing','furniture','internet','cleaning','security','noise','water','other') NOT NULL DEFAULT 'other',
    title       VARCHAR(150) NOT NULL,
    description TEXT         NULL,
    priority    ENUM('low','medium','high','urgent') NOT NULL DEFAULT 'medium',
    room_number VARCHAR(40)  NULL,
    photo       VARCHAR(255) NULL,
    status      ENUM('open','assigned','in_progress','waiting_parts','completed','rejected','closed') NOT NULL DEFAULT 'open',
    assigned_to INT          NULL,
    technician_notes TEXT    NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_comp_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE SET NULL,
    INDEX idx_comp_status (status)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------------
-- Visitors
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS visitors (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    student_id   INT          NULL,
    visitor_name VARCHAR(150) NOT NULL,
    phone        VARCHAR(30)  NULL,
    purpose      VARCHAR(255) NULL,
    visit_date   DATE         NULL,
    time_in      DATETIME     NULL,
    time_out     DATETIME     NULL,
    pass_code    VARCHAR(40)  NULL,
    status       ENUM('pending','approved','checked_in','checked_out','rejected','blacklisted') NOT NULL DEFAULT 'pending',
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_vis_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------------
-- Notices / Communication
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS notices (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    title       VARCHAR(200) NOT NULL,
    body        TEXT         NOT NULL,
    audience    ENUM('all','students','staff') NOT NULL DEFAULT 'all',
    hostel_id   INT          NULL,
    is_pinned   TINYINT(1)   NOT NULL DEFAULT 0,
    expires_at  DATE         NULL,
    created_by  INT          NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_notice_hostel FOREIGN KEY (hostel_id) REFERENCES hostels(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------------
-- Audit logs & settings
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS audit_logs (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT          NULL,
    role       VARCHAR(40)  NULL,
    action     VARCHAR(80)  NOT NULL,
    module     VARCHAR(80)  NULL,
    record_id  VARCHAR(40)  NULL,
    details    VARCHAR(255) NULL,
    ip_address VARCHAR(64)  NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_audit_user (user_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS settings (
    id     INT AUTO_INCREMENT PRIMARY KEY,
    `key`  VARCHAR(80) NOT NULL UNIQUE,
    value  TEXT        NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------------
-- Inventory / Assets
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS inventory (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(150) NOT NULL,
    category    ENUM('bed','mattress','table','chair','wardrobe','fan','fire_extinguisher','other') NOT NULL DEFAULT 'other',
    hostel_id   INT          NULL,
    room_id     INT          NULL,
    quantity    INT          NOT NULL DEFAULT 1,
    `condition` ENUM('new','good','fair','damaged','replaced') NOT NULL DEFAULT 'good',
    reorder_level INT        NOT NULL DEFAULT 0,
    notes       VARCHAR(255) NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_inv_hostel FOREIGN KEY (hostel_id) REFERENCES hostels(id) ON DELETE SET NULL,
    CONSTRAINT fk_inv_room   FOREIGN KEY (room_id)   REFERENCES rooms(id) ON DELETE SET NULL,
    INDEX idx_inventory_category (category)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------------
-- In-app notifications
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS notifications (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT          NOT NULL,
    title      VARCHAR(150) NOT NULL,
    body       VARCHAR(255) NULL,
    icon       VARCHAR(40)  NULL,
    link       VARCHAR(255) NULL,
    is_read    TINYINT(1)   NOT NULL DEFAULT 0,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_notif_user_read (user_id, is_read)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------------
-- Two-Factor Authentication (email one-time codes)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS two_factor_codes (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT          NOT NULL,
    code_hash  VARCHAR(255) NOT NULL,        -- the OTP is hashed, never stored in clear
    sent_to    VARCHAR(255) NOT NULL,        -- address(es) the code was delivered to
    attempts   TINYINT      NOT NULL DEFAULT 0,
    consumed_at DATETIME    NULL,            -- set the moment a code is accepted
    expires_at DATETIME     NOT NULL,
    ip_address VARCHAR(64)  NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_2fa_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_2fa_user (user_id, consumed_at)
) ENGINE=InnoDB;

-- Two-factor defaults for the System Settings screen.
INSERT IGNORE INTO settings (`key`, value) VALUES
    ('twofa_enabled',    '0'),   -- master switch
    ('twofa_roles',      ''),    -- CSV of roles that must complete 2FA, e.g. "admin,finance"
    ('twofa_recipients', '');    -- JSON map of role => override recipient email(s)

SET FOREIGN_KEY_CHECKS = 1;
