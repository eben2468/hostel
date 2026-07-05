-- ===========================================================================
-- Migration: per-hostel application settings
--   academic_year / semester   -> set by the hostel admin, applied to new apps
--   applications_open           -> toggles whether students may apply
-- Safe to re-run.
-- ===========================================================================
USE chms_hostel;

SET @has := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = 'chms_hostel' AND TABLE_NAME = 'hostels' AND COLUMN_NAME = 'applications_open'
);
SET @sql := IF(@has = 0,
    'ALTER TABLE hostels
        ADD COLUMN academic_year    VARCHAR(40) NULL AFTER status,
        ADD COLUMN semester         VARCHAR(40) NULL AFTER academic_year,
        ADD COLUMN applications_open TINYINT(1) NOT NULL DEFAULT 1 AFTER semester',
    'SELECT "hostels application-settings columns already exist"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
