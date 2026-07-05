-- ===========================================================================
-- Migration: applications.preferred_room_id
-- Lets a student pick a specific preferred room when applying. Safe to re-run.
-- ===========================================================================
USE chms_hostel;

SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = 'chms_hostel' AND TABLE_NAME = 'applications' AND COLUMN_NAME = 'preferred_room_id'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE applications
        ADD COLUMN preferred_room_id INT NULL AFTER preferred_room_type,
        ADD INDEX idx_app_pref_room (preferred_room_id),
        ADD CONSTRAINT fk_app_pref_room FOREIGN KEY (preferred_room_id) REFERENCES rooms(id) ON DELETE SET NULL',
    'SELECT "applications.preferred_room_id already exists"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
