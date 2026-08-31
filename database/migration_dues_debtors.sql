-- ===========================================================================
-- Hall dues debtors: carried-forward debt from previous semesters
--
-- An admin uploads the hall's debtors list (txt / csv / xlsx). Each upload is
-- one batch, so a wrong import can be removed in a single click. Students are
-- matched against it by student ID *or* phone number when they try to apply
-- for a room, and are told to settle the arrears first.
--
-- Deliberately contains NO "USE <database>" line so it runs against whichever
-- database is already selected. On shared hosting the database is named by the
-- control panel and the MySQL user has no rights to any other one.
--
-- phpMyAdmin : select your database in the left sidebar first, then
--              Import -> choose this file -> Go.
-- Command line: mysql -u USER -p DATABASE < database/migration_dues_debtors.sql
--
-- Safe to run more than once.
-- ===========================================================================

CREATE TABLE IF NOT EXISTS dues_debtor_batches (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    hostel_id     INT          NULL,           -- the hall the list belongs to
    filename      VARCHAR(255) NOT NULL,       -- original upload name, for the audit trail
    label         VARCHAR(150) NULL,           -- e.g. "2nd Semester 2025/2026 arrears"
    row_count     INT          NOT NULL DEFAULT 0,
    skipped_count INT          NOT NULL DEFAULT 0,
    uploaded_by   INT          NULL,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_debtor_batch_hostel FOREIGN KEY (hostel_id) REFERENCES hostels(id) ON DELETE CASCADE,
    INDEX idx_debtor_batch_hostel (hostel_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS dues_debtors (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    batch_id      INT          NOT NULL,
    hostel_id     INT          NULL,
    full_name     VARCHAR(150) NULL,
    student_no    VARCHAR(60)  NULL,           -- exactly as it appears in the file
    -- Normalised copies are what matching actually compares. Student IDs are
    -- upper-cased with punctuation stripped; phones keep their last 9 digits so
    -- "0548811774" and the "548811774" Excel leaves behind still match.
    student_no_norm VARCHAR(60) NULL,
    phone         VARCHAR(40)  NULL,
    phone_norm    VARCHAR(20)  NULL,
    room_label    VARCHAR(40)  NULL,
    amount        DECIMAL(12,2) NULL,
    academic_year VARCHAR(40)  NULL,
    semester      VARCHAR(40)  NULL,
    status        ENUM('outstanding','cleared') NOT NULL DEFAULT 'outstanding',
    cleared_by    INT          NULL,
    cleared_at    DATETIME     NULL,
    note          VARCHAR(255) NULL,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_debtor_batch FOREIGN KEY (batch_id) REFERENCES dues_debtor_batches(id) ON DELETE CASCADE,
    INDEX idx_debtor_hostel (hostel_id),
    INDEX idx_debtor_studentno (student_no_norm),
    INDEX idx_debtor_phone (phone_norm),
    INDEX idx_debtor_status (status)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------------
-- Align the new tables' collation with the existing `students` table.
--
-- Matching joins student_no_norm/phone_norm against students.student_id and
-- students.phone. If the server's default collation differs from the one the
-- original schema was built with, that comparison dies with
--     #1267 Illegal mix of collations
-- so rather than hardcoding a collation that may be wrong on any given host,
-- copy whatever `students` actually uses. Re-running this is harmless.
-- ---------------------------------------------------------------------------
SET @coll := (SELECT TABLE_COLLATION FROM information_schema.TABLES
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'students');
SET @cs := (SELECT CHARACTER_SET_NAME FROM information_schema.COLLATIONS
            WHERE COLLATION_NAME = @coll);

SET @sql := IF(@coll IS NULL OR @cs IS NULL,
    'SELECT "students table not found - skipping collation alignment"',
    CONCAT('ALTER TABLE dues_debtors CONVERT TO CHARACTER SET ', @cs, ' COLLATE ', @coll));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(@coll IS NULL OR @cs IS NULL,
    'SELECT "skipped"',
    CONCAT('ALTER TABLE dues_debtor_batches CONVERT TO CHARACTER SET ', @cs, ' COLLATE ', @coll));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------------------------------------------------------------------------
-- Check: prints 1 and 1 when both tables exist, and the matching collation.
-- ---------------------------------------------------------------------------
SELECT
    (SELECT COUNT(*) FROM information_schema.TABLES
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'dues_debtor_batches') AS batches_table,
    (SELECT COUNT(*) FROM information_schema.TABLES
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'dues_debtors')        AS debtors_table,
    (SELECT TABLE_COLLATION FROM information_schema.TABLES
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'dues_debtors')        AS debtors_collation,
    (SELECT TABLE_COLLATION FROM information_schema.TABLES
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'students')            AS students_collation;
