-- ===========================================================================
-- Hall dues: payment account, dues notices & the application Reference ID
--
--   hostels.*      -> the bank / mobile-money account students pay dues into,
--                     the amounts freshers vs continuing students owe, and the
--                     notes an admin writes to explain them.
--   applications.* -> the "Reference ID" a student submits with their room
--                     application, its verification state, and the note a
--                     hostel admin leaves when cancelling or rejecting.
--
-- Deliberately contains NO "USE <database>" line so it runs against whichever
-- database is already selected. On shared hosting the database is named by the
-- control panel (hostel, cpaneluser_hostel, ...) and the MySQL user has no
-- rights to any other one, so a hardcoded name fails with
--     #1044 - Access denied for user '...' to database 'chms_hostel'
--
-- phpMyAdmin : select your database in the left sidebar first, then
--              Import -> choose this file -> Go.
-- Command line: mysql -u USER -p DATABASE < database/migration_hostel_dues.sql
--
-- Safe to run more than once.
-- ===========================================================================

-- ---------------------------------------------------------------------------
-- hostels: the dues payment account + the dues notice
-- ---------------------------------------------------------------------------
SET @has := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'hostels' AND COLUMN_NAME = 'dues_account_number'
);
SET @sql := IF(@has = 0,
    'ALTER TABLE hostels
        ADD COLUMN dues_bank_name         VARCHAR(120)  NULL,
        ADD COLUMN dues_account_name      VARCHAR(150)  NULL,
        ADD COLUMN dues_account_number    VARCHAR(60)   NULL,
        ADD COLUMN dues_branch            VARCHAR(120)  NULL,
        ADD COLUMN dues_momo_network      VARCHAR(40)   NULL,
        ADD COLUMN dues_momo_name         VARCHAR(150)  NULL,
        ADD COLUMN dues_momo_number       VARCHAR(40)   NULL,
        ADD COLUMN dues_instructions      TEXT          NULL,
        ADD COLUMN dues_fresher_amount    DECIMAL(12,2) NULL,
        ADD COLUMN dues_fresher_note      TEXT          NULL,
        ADD COLUMN dues_continuing_amount DECIMAL(12,2) NULL,
        ADD COLUMN dues_continuing_note   TEXT          NULL,
        ADD COLUMN dues_reference_required TINYINT(1) NOT NULL DEFAULT 1',
    'SELECT "hostels dues columns already exist"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------------------------------------------------------------------------
-- applications: the payment reference the student submits + the review note
-- ---------------------------------------------------------------------------
SET @has := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'applications' AND COLUMN_NAME = 'payment_reference'
);
SET @sql := IF(@has = 0,
    "ALTER TABLE applications
        ADD COLUMN student_type ENUM('fresher','continuing') NULL,
        ADD COLUMN payment_reference VARCHAR(80) NULL,
        ADD COLUMN payment_amount DECIMAL(12,2) NULL,
        ADD COLUMN payment_status ENUM('unverified','verified','not_found') NOT NULL DEFAULT 'unverified',
        ADD COLUMN payment_verified_by INT NULL,
        ADD COLUMN payment_verified_at DATETIME NULL,
        ADD COLUMN review_note TEXT NULL,
        ADD INDEX idx_app_payment_ref (payment_reference)",
    'SELECT "applications payment-reference columns already exist"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------------------------------------------------------------------------
-- Check: prints 13 and 7 when both tables were migrated.
-- ---------------------------------------------------------------------------
SELECT
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'hostels'
        AND COLUMN_NAME LIKE 'dues%')                       AS hostel_dues_columns,
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'applications'
        AND COLUMN_NAME IN ('student_type','payment_reference','payment_amount',
                            'payment_status','payment_verified_by','payment_verified_at',
                            'review_note'))                 AS application_dues_columns;
